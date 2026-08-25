<?php
namespace MySer;

defined('ABSPATH') || exit;

/**
 * AJAX-обработчики для подразделений
 *
 * @package MySer
 */
class Departments_Handler extends Ajax_Handler
{
    public static function register_hooks()
    {
        $actions = [
            'myser_get_departments',
            'myser_get_department',
            'myser_save_department',
            'myser_delete_department',
            'myser_check_prefix',
        ];
        foreach ($actions as $action) {
            add_action('wp_ajax_' . $action, [self::class, str_replace('myser_', '', $action)]);
        }
    }

    /**
     * Получить список подразделений
     */
    public static function get_departments()
    {
        self::verify_nonce();
        self::check_permissions();
        global $wpdb;
        $table = $wpdb->prefix . 'myser_departments';
        $staff_table = $wpdb->prefix . 'myser_staff';
        $results = $wpdb->get_results(
            "SELECT d.*, " .
            "(SELECT COUNT(*) FROM `$staff_table` s WHERE JSON_CONTAINS(s.department, CAST(d.id AS CHAR))) AS staff_count " .
            "FROM `$table` d ORDER BY d.short_name ASC",
            ARRAY_A
        );
        wp_send_json_success($results ?: []);
    }

    /**
     * Получить одно подразделение по ID
     */
    public static function get_department()
    {
        self::verify_nonce();
        self::check_permissions();
        global $wpdb;
        $id = intval($_POST['dep_id'] ?? 0);
        if ($id <= 0) {
            wp_send_json_error(['message' => 'Не указан ID подразделения']);
        }
        $table = $wpdb->prefix . 'myser_departments';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM `$table` WHERE id = %d", $id), ARRAY_A);
        if ($row) {
            wp_send_json_success($row);
        } else {
            wp_send_json_error(['message' => 'Подразделение не найдено']);
        }
    }

    /**
     * Сохранить (добавить/обновить) подразделение
     */
    public static function save_department()
    {
        self::verify_nonce();
        self::check_permissions();
        global $wpdb;
        $table = $wpdb->prefix . 'myser_departments';
        $id = intval($_POST['dep_id'] ?? 0);

        $dep_type = sanitize_text_field($_POST['dep_type'] ?? '');
        $order_prefix_raw = sanitize_text_field($_POST['order_prefix'] ?? '');
        $short_name = sanitize_text_field($_POST['short_name'] ?? '');
        $full_name = sanitize_text_field($_POST['full_name'] ?? '');

        // 1. Автопреобразование: строчные → заглавные
        $order_prefix = strtoupper($order_prefix_raw);

        // 2. Определяем, был ли префикс введён вручную
        $is_manual_prefix = !empty($order_prefix_raw);

        // 3. Получаем текущие данные для редактирования
        $current = null;
        $old_dep_type = null;
        $old_order_prefix = null;
        if ($id > 0) {
            $current = $wpdb->get_row($wpdb->prepare("SELECT dep_type, order_prefix FROM `$table` WHERE id = %d", $id), ARRAY_A);
            if (!$current) {
                wp_send_json_error(['message' => 'Подразделение не найдено']);
            }
            $old_dep_type = $current['dep_type'];
            $old_order_prefix = $current['order_prefix'];
        }

        // 4. Обработка создания нового подразделения
        if ($id === 0) {
            $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM `$table`");
            if ($count === 0) {
                // Первое подразделение — Головной
                $dep_type = 'head';
                if (!$is_manual_prefix) {
                    $order_prefix = 'MS';
                }
            } else {
                // Для всех последующих — тип по выбору пользователя (branch/remote)
                if (empty($dep_type) || $dep_type === 'head') {
                    $dep_type = 'branch';
                }
                // Генерируем префикс из full_name, если не указан вручную
                if (!$is_manual_prefix) {
                    if ($dep_type === 'head') {
                        $order_prefix = 'MS';
                    } elseif (in_array($dep_type, ['branch', 'remote']) && !empty($full_name)) {
                        $order_prefix = self::generate_unique_prefix($full_name, $id);
                    } elseif (!empty($short_name)) {
                        $order_prefix = self::generate_unique_prefix($short_name, $id);
                    }
                }
            }
        } else {
            // 5. Обработка редактирования — при смене типа префикс НЕ МЕНЯЕТСЯ
            if ($old_dep_type !== $dep_type) {
                // Если префикс не был введён вручную при редактировании, используем старый
                if (!$is_manual_prefix && !empty($old_order_prefix)) {
                    $order_prefix = $old_order_prefix;
                } elseif (!$is_manual_prefix && empty($old_order_prefix)) {
                    // Если старого префикса не было, генерируем для нового типа
                    if ($dep_type === 'head') {
                        $order_prefix = 'MS';
                    } elseif (in_array($dep_type, ['branch', 'remote']) && !empty($full_name)) {
                        $order_prefix = self::generate_unique_prefix($full_name, $id);
                    } else {
                        $order_prefix = self::generate_unique_prefix($short_name, $id);
                    }
                }
            }

            // Если меняется тип на head
            if ($dep_type === 'head' && $old_dep_type !== 'head') {
                // Найти текущее головное и переключить его на branch
                $wpdb->update(
                    $table,
                    ['dep_type' => 'branch'],
                    ['dep_type' => 'head']
                );
            }

            // Если пытаются сменить головное на что-то другое
            if ($old_dep_type === 'head' && $dep_type !== 'head') {
                wp_send_json_error(['message' => 'Нельзя изменить тип головного подразделения. Чтобы назначить новое головное, выберите другой тип для другого подразделения.']);
            }
        }

        // 6. Финальная проверка и генерация, если префикс всё ещё пустой
        if (empty($order_prefix)) {
            if ($dep_type === 'head') {
                $order_prefix = 'MS';
            } else {
                $order_prefix = self::generate_unique_prefix($full_name ?: $short_name, $id);
            }
        }

        // 7. Проверка уникальности (если префикс был введён вручную или сгенерирован)
        if (!empty($order_prefix)) {
            // Убеждаемся, что префикс ровно 2 символа
            $order_prefix = substr($order_prefix, 0, 2);
            if (strlen($order_prefix) < 2) {
                $order_prefix = str_pad($order_prefix, 2, 'X');
            }

            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM `$table` WHERE order_prefix = %s AND id != %d",
                $order_prefix, $id
            ));
            if ($exists > 0) {
                // Если занят, генерируем новый
                $order_prefix = self::generate_unique_prefix($full_name ?: $short_name, $id);
            }
        }

        // 8. Финальная проверка длины префикса
        if (strlen($order_prefix) < 2) {
            $order_prefix = str_pad($order_prefix, 2, 'X');
        }
        $order_prefix = substr($order_prefix, 0, 2);

        $data = [
            'short_name'        => $short_name,
            'full_name'         => $full_name,
            'order_prefix'      => $order_prefix,
            'city'              => sanitize_text_field($_POST['city'] ?? ''),
            'address'           => sanitize_textarea_field($_POST['address'] ?? ''),
            'address_fact'      => sanitize_textarea_field($_POST['address_fact'] ?? ''),
            'work_phone'        => sanitize_text_field($_POST['work_phone'] ?? ''),
            'email'             => sanitize_email($_POST['email'] ?? ''),
            'inn'               => sanitize_text_field($_POST['inn'] ?? ''),
            'kpp'               => sanitize_text_field($_POST['kpp'] ?? ''),
            'ogrn'              => sanitize_text_field($_POST['ogrn'] ?? ''),
            'okpo'              => sanitize_text_field($_POST['okpo'] ?? ''),
            'okvd'              => sanitize_text_field($_POST['okvd'] ?? ''),
            'bank_account'      => sanitize_text_field($_POST['bank_account'] ?? ''),
            'bank_name'         => sanitize_text_field($_POST['bank_name'] ?? ''),
            'bank_bic'          => sanitize_text_field($_POST['bank_bic'] ?? ''),
            'bank_corr'         => sanitize_text_field($_POST['bank_corr'] ?? ''),
            'director'          => sanitize_text_field($_POST['director'] ?? ''),
            'director_full'     => sanitize_text_field($_POST['director_full'] ?? ''),
            'director_position' => sanitize_text_field($_POST['director_position'] ?? ''),
            'director_based'    => sanitize_text_field($_POST['director_based'] ?? ''),
            'accountant'        => sanitize_text_field($_POST['accountant'] ?? ''),
            'notes'             => sanitize_textarea_field($_POST['notes'] ?? ''),
            'status'            => intval($_POST['status'] ?? 1),
            'dep_type'          => $dep_type,
        ];

        // Добавляем logo только если оно передано и не пустое
        $logo_value = sanitize_text_field($_POST['logo'] ?? '');
        if (!empty($logo_value)) {
            $data['logo'] = $logo_value;
        }

        if ($id === 0) {
            $result = $wpdb->insert($table, $data);
            $new_id = $wpdb->insert_id;
            if ($result) {
                self::update_department_staff_counts();
                wp_send_json_success(['id' => $new_id, 'message' => 'Подразделение добавлено']);
            } else {
                wp_send_json_error(['message' => 'Ошибка добавления подразделения']);
            }
        } else {
            $result = $wpdb->update($table, $data, ['id' => $id]);
            if ($result !== false) {
                self::update_department_staff_counts();
                wp_send_json_success(['id' => $id, 'message' => 'Подразделение обновлено']);
            } else {
                wp_send_json_error(['message' => 'Ошибка обновления подразделения']);
            }
        }
    }

    /**
     * Удалить подразделение
     */
    public static function delete_department()
    {
        self::verify_nonce();
        self::check_permissions();
        global $wpdb;
        $id = intval($_POST['dep_id'] ?? 0);
        if ($id <= 0) {
            wp_send_json_error(['message' => 'Не указан ID подразделения']);
        }
        $table = $wpdb->prefix . 'myser_departments';
        $current = $wpdb->get_row($wpdb->prepare("SELECT dep_type FROM `$table` WHERE id = %d", $id), ARRAY_A);
        if (!$current) {
            wp_send_json_error(['message' => 'Подразделение не найдено']);
        }
        if ($current['dep_type'] === 'head') {
            wp_send_json_error(['message' => 'Нельзя удалить головное подразделение']);
        }
        $result = $wpdb->delete($table, ['id' => $id]);
        if ($result) {
            self::update_department_staff_counts();
            wp_send_json_success(['message' => 'Подразделение удалено']);
        } else {
            wp_send_json_error(['message' => 'Ошибка удаления подразделения']);
        }
    }

    /**
     * Пересчитывает и сохраняет количество сотрудников для каждого подразделения
     */
    protected static function update_department_staff_counts()
    {
        global $wpdb;
        $dept_table = $wpdb->prefix . 'myser_departments';
        $staff_table = $wpdb->prefix . 'myser_staff';

        $departments = $wpdb->get_results("SELECT id FROM `$dept_table`", ARRAY_A);
        foreach ($departments as $dept) {
            $dept_id = $dept['id'];
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM `$staff_table` WHERE JSON_CONTAINS(department, %s)",
                (string) $dept_id
            ));
            $wpdb->update($dept_table, ['staff_count' => (int) $count], ['id' => $dept_id]);
        }
    }

    /**
     * Проверка уникальности префикса (для JS)
     */
    public static function check_prefix()
    {
        self::verify_nonce();
        self::check_permissions();
        
        global $wpdb;
        $table = $wpdb->prefix . 'myser_departments';
        $prefix = strtoupper(sanitize_text_field($_POST['prefix'] ?? ''));
        $exclude_id = intval($_POST['exclude_id'] ?? 0);
        
        if (empty($prefix)) {
            wp_send_json_error(['message' => 'Префикс не указан']);
        }
        
        $taken = self::is_prefix_taken($prefix, $exclude_id);
        wp_send_json_success(['taken' => $taken]);
    }

    /**
     * Генерирует уникальный префикс из названия
     * Перебирает варианты (первые 2 буквы, затем со сдвигом, затем с цифрами)
     */
    private static function generate_unique_prefix($name, $exclude_id = 0)
    {
        if (empty($name)) {
            return 'MS';
        }

        $transliterated = self::transliterate($name);
        $latin = preg_replace('/[^a-zA-Z]/u', '', $transliterated);
        
        if (empty($latin)) {
            return 'MS';
        }
        
        $base = strtoupper($latin);
        
        // Пробуем варианты со сдвигом
        for ($i = 0; $i < strlen($base) - 1; $i++) {
            $candidate = substr($base, $i, 2);
            if (strlen($candidate) === 2 && !self::is_prefix_taken($candidate, $exclude_id)) {
                return $candidate;
            }
        }
        
        // Если все варианты заняты — добавляем цифры
        $suffix = 1;
        while ($suffix <= 99) {
            $candidate = substr($base, 0, 2) . $suffix;
            if (!self::is_prefix_taken($candidate, $exclude_id)) {
                return $candidate;
            }
            $suffix++;
        }
        
        return 'MS';
    }

    /**
     * Проверяет, занят ли префикс
     */
    private static function is_prefix_taken($prefix, $exclude_id = 0)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'myser_departments';
        
        $sql = "SELECT COUNT(*) FROM `$table` WHERE order_prefix = %s";
        $params = [$prefix];
        
        if ($exclude_id > 0) {
            $sql .= " AND id != %d";
            $params[] = $exclude_id;
        }
        
        return (int) $wpdb->get_var($wpdb->prepare($sql, $params)) > 0;
    }

    /**
     * Транслитерация кириллицы в латиницу
     */
    private static function transliterate($text)
    {
        $cyrillic = [
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e',
            'ё' => 'yo', 'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k',
            'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r',
            'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'ts',
            'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch', 'ъ' => '', 'ы' => 'y', 'ь' => '',
            'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
            'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D', 'Е' => 'E',
            'Ё' => 'YO', 'Ж' => 'ZH', 'З' => 'Z', 'И' => 'I', 'Й' => 'Y', 'К' => 'K',
            'Л' => 'L', 'М' => 'M', 'Н' => 'N', 'О' => 'O', 'П' => 'P', 'Р' => 'R',
            'С' => 'S', 'Т' => 'T', 'У' => 'U', 'Ф' => 'F', 'Х' => 'H', 'Ц' => 'TS',
            'Ч' => 'CH', 'Ш' => 'SH', 'Щ' => 'SCH', 'Ъ' => '', 'Ы' => 'Y', 'Ь' => '',
            'Э' => 'E', 'Ю' => 'YU', 'Я' => 'YA'
        ];
        
        return strtr($text, $cyrillic);
    }
}
