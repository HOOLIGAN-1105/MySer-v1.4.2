<?php
namespace MySer;

defined('ABSPATH') || exit;

/**
 * Класс для обработки AJAX-запросов
 *
 * Регистрирует все обработчики для административных и публичных AJAX-запросов.
 * Включает операции с клиентами, заказами, бекапами и ребутом.
 *
 * @package MySer
 */
class Ajax_Handler
{


    /**
     * Инициализирует все AJAX-обработчики
     *
     * Регистрирует действия для wp_ajax_* и wp_ajax_nopriv_*.
     *
     * @return void
     */
    public static function init()
    {
        $actions = [
            'myser_reboot',
            'myser_custom_uninstall',
        ];
        foreach ($actions as $action) {
            add_action('wp_ajax_'.$action, [self::class, str_replace('myser_', '', $action)]);
        }

        // Загрузка модульных обработчиков
        require_once __DIR__ . '/ajax/class-ajax-departments.php';
        Departments_Handler::register_hooks();
        require_once __DIR__ . '/ajax/class-ajax-backups.php';
        Backups_Handler::register_hooks();
        require_once __DIR__ . '/ajax/class-ajax-clients.php';
        Clients_Handler::register_hooks();
        require_once __DIR__ . '/ajax/class-ajax-staff.php';
        Staff_Handler::register_hooks();
        require_once __DIR__ . '/ajax/class-ajax-orders.php';
        Orders_Handler::register_hooks();
        require_once __DIR__ . '/ajax/class-ajax-reference.php';
        \MySer\Includes\Ajax\AjaxReference::init();

    }//end init()


    protected static function verify_nonce()
    {
        $nonce = $_POST['_ajax_nonce'] ?? $_POST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'myser_nonce')) {
            Logger::get()->warning('Неверный nonce в AJAX', ['action' => ($_POST['action'] ?? 'unknown')]);
            wp_send_json_error(['message' => __('Nonce verification failed', 'myser')]);
        }

    }//end verify_nonce()


    protected static function check_permissions()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Недостаточно прав', 'myser')]);
        }

    }//end check_permissions()


    /**
     * Синхронизирует клиента/сотрудника с таблицей subjects.
     * Автоматически генерирует display_name и short_name.
     *
     * @param  string      $type      'client' или 'staff'
     * @param  array       $data      Данные субъекта
     * @param  int|null    $client_id ID клиента (если обновление)
     * @return int|null               subject_id
     */
    protected static function sync_subject($type, $data, $client_id=null) {
        global $wpdb;
        $subjects_table = $wpdb->prefix . 'myser_subjects';
        $roles_table    = $wpdb->prefix . 'myser_subject_roles';

        $last_name   = ($data['last_name'] ?? '');
        $first_name  = ($data['first_name'] ?? '');
        $middle_name = ($data['middle_name'] ?? '');
        $email       = ($data['email'] ?? '');
        $phone       = ($data['phone'] ?? '');
        $address     = ($data['address'] ?? '');
        $notes       = ($data['notes'] ?? '');

        // Генерируем display_name
        $display_name = trim($last_name.' '.$first_name.' '.$middle_name);

        // Генерируем short_name: Фамилия И.О.
        $short_name = $last_name;
        if (!empty($first_name)) {
            $short_name .= ' '.mb_substr($first_name, 0, 1).'.';
        }
        if (!empty($middle_name)) {
            $short_name .= mb_substr($middle_name, 0, 1).'.';
        }

        // full_name_without_lastname: Имя Отчество
        $full_name_without_lastname = trim($first_name.' '.$middle_name);

        $subject_data = [
            'subject_type'              => $type,
            'last_name'                 => $last_name,
            'first_name'                => $first_name,
            'middle_name'               => $middle_name,
            'display_name'              => $display_name,
            'short_name'                => $short_name,
            'full_name_without_lastname' => $full_name_without_lastname,
            'email'                     => $email,
            'mobile_phone'              => $phone,
            'registration_address'      => $address,
            'notes'                     => $notes,
        ];

        // Ищем существующий subject_id
        $existing_subject_id = null;
        if ($client_id) {
            $clients_table = $wpdb->prefix . 'myser_clients';
            $existing_subject_id = $wpdb->get_var($wpdb->prepare(
                "SELECT subject_id FROM `$clients_table` WHERE id = %d",
                $client_id
            ));
        }

        if ($existing_subject_id) {
            // Обновляем существующий subject
            $wpdb->update($subjects_table, $subject_data, ['id' => $existing_subject_id]);
            return $existing_subject_id;
        } else {
            // Создаём новый subject
            $wpdb->insert($subjects_table, $subject_data);
            $new_subject_id = $wpdb->insert_id;

            // Добавляем роль
            if ($new_subject_id) {
                // Проверяем, нет ли уже такой роли
                $has_role = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM `$roles_table` WHERE subject_id = %d AND role = %s",
                    $new_subject_id, $type
                ));
                if (!$has_role) {
                    $wpdb->insert($roles_table, [
                        'subject_id'  => $new_subject_id,
                        'role'        => $type,
                        'assigned_at' => current_time('mysql'),
                    ]);
                }
            }

            return $new_subject_id;
        }

    }//end sync_subject()


    /**
     * Сохраняет роли сотрудника в myser_subject_roles.
     * Принимает массив ролей из $_POST['roles'][].
     *
     * @param int $staff_id ID сотрудника
     */
    protected static function handle_staff_roles($staff_id) {
        global $wpdb;
        $tables      = Database::get_tables();
        $roles_table = $wpdb->prefix . 'myser_subject_roles';

        // Получаем subject_id сотрудника
        $subject_id = $wpdb->get_var($wpdb->prepare(
            "SELECT subject_id FROM {$tables['staff']} WHERE id = %d",
            $staff_id
        ));

        if (!$subject_id) {
            // Создаём subject если нет
            $staff = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$tables['staff']} WHERE id = %d",
                $staff_id
            ));
            if (!$staff) return;

            $subjects_table = $wpdb->prefix . 'myser_subjects';
            $parts = explode(' ', ($staff->staff_name ?? ''));
            $last_name   = $parts[0] ?? '';
            $first_name  = $parts[1] ?? '';
            $middle_name = $parts[2] ?? '';
            $display_name = trim($last_name.' '.$first_name.' '.$middle_name);
            $short_name = $last_name;
            if (!empty($first_name)) $short_name .= ' '.mb_substr($first_name, 0, 1).'.';
            if (!empty($middle_name)) $short_name .= mb_substr($middle_name, 0, 1).'.';

            $wpdb->insert($subjects_table, [
                'subject_type' => 'staff',
                'last_name'    => $last_name,
                'first_name'   => $first_name,
                'middle_name'  => $middle_name,
                'display_name' => $display_name,
                'short_name'   => $short_name,
                'email'        => $staff->email ?? '',
                'mobile_phone' => $staff->mobile_phone ?? '',
            ]);
            $subject_id = $wpdb->insert_id;

            // Обновляем subject_id в staff
            $wpdb->update($tables['staff'], ['subject_id' => $subject_id], ['id' => $staff_id]);
        }

        // Если роли переданы — обновляем
        if (isset($_POST['roles']) && is_array($_POST['roles'])) {
            $new_roles = array_map('sanitize_text_field', $_POST['roles']);

            // Получаем текущие роли
            $current_roles = $wpdb->get_col($wpdb->prepare(
                "SELECT role FROM `$roles_table` WHERE subject_id = %d",
                $subject_id
            ));

            // Роли которые нужно добавить
            $to_add = array_diff($new_roles, $current_roles);
            foreach ($to_add as $role) {
                $wpdb->insert($roles_table, [
                    'subject_id'  => $subject_id,
                    'role'        => $role,
                    'is_active'   => 1,
                    'assigned_at' => current_time('mysql'),
                ]);
            }

            // Роли которые нужно убрать (деактивировать)
            $to_remove = array_diff($current_roles, $new_roles);
            foreach ($to_remove as $role) {
                $wpdb->delete($roles_table, [
                    'subject_id' => $subject_id,
                    'role'       => $role,
                ]);
            }

            Logger::get()->info('Роли сотрудника обновлены через handle_staff_roles', [
                'staff_id'   => $staff_id,
                'subject_id' => $subject_id,
                'added'      => $to_add,
                'removed'    => $to_remove,
            ]);
        }

    }//end handle_staff_roles()


    /**
     * Синхронизирует subject_roles в myser_staff из myser_subject_roles.
     * Вызывается после сохранения сотрудника и при изменении ролей.
     *
     * @param int $staff_id ID сотрудника
     */
    protected static function sync_staff_roles($staff_id) {
        global $wpdb;
        $tables       = Database::get_tables();
        $roles_table  = $wpdb->prefix . 'myser_subject_roles';

        // Получаем subject_id сотрудника
        $subject_id = $wpdb->get_var($wpdb->prepare(
            "SELECT subject_id FROM {$tables['staff']} WHERE id = %d",
            $staff_id
        ));

        if (!$subject_id) {
            return;
        }

        // Получаем все активные роли через запятую
        $roles = $wpdb->get_var($wpdb->prepare(
            "SELECT GROUP_CONCAT(role ORDER BY role SEPARATOR ', ') FROM `$roles_table` WHERE subject_id = %d AND is_active = 1",
            $subject_id
        ));

        // Обновляем колонку subject_roles в staff
        $wpdb->update(
            $tables['staff'],
            ['subject_roles' => $roles ?: null],
            ['id' => $staff_id]
        );

        Logger::get()->info('Роли сотрудника синхронизированы', [
            'staff_id'   => $staff_id,
            'subject_id' => $subject_id,
            'roles'      => $roles ?: 'none',
        ]);

    }//end sync_staff_roles()


    /**
     * Обновляет количество сотрудников в подразделениях
     */
    protected static function update_department_staff_counts() {
        global $wpdb;
        $departments_table = $wpdb->prefix . 'myser_departments';
        $staff_table = $wpdb->prefix . 'myser_staff';

        // Получаем все подразделения
        $departments = $wpdb->get_results("SELECT id FROM `$departments_table`");
        foreach ($departments as $dept) {
            // Считаем сотрудников в этом подразделении
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM `$staff_table` WHERE department LIKE %s",
                '%"' . $dept->id . '"%'
            ));
            // Обновляем количество
            $wpdb->update(
                $departments_table,
                ['staff_count' => $count],
                ['id' => $dept->id]
            );
        }
    }


    public static function reboot()
    {
        self::verify_nonce();
        self::check_permissions();
        Logger::get()->info('Запущен ребут плагина через AJAX');
        try {
            include_once MYSER_PLUGIN_DIR.'lib/includes/activator.php';
            Activator::activate();
            Logger::get()->info('Ребут успешно выполнен через AJAX');
            wp_send_json_success(['message' => 'Плагин перезагружен!']);
        } catch (\Exception $e) {
            Logger::get()->critical('Ошибка ребута через AJAX', ['error' => $e->getMessage()]);
            wp_send_json_error(['message' => 'Ошибка ребута: '.$e->getMessage()]);
        }

    }//end reboot()


    /**
     * Обработчик кастомного удаления плагина
     */
    public static function custom_uninstall()
    {
        self::verify_nonce();
        self::check_permissions();

        $action_mode   = sanitize_text_field($_POST['action_mode']);
        $create_backup = (int) $_POST['create_backup'];

        // Если выбран режим "Оставить данные" — просто удаляем плагин
        if ($action_mode === 'keep') {
            // Устанавливаем глобальный флаг для uninstall.php
            $GLOBALS['myser_keep_data'] = true;
            // Запускаем удаление плагина через WordPress
            $deleted = delete_plugins(['myser/myser.php']);
            if (is_wp_error($deleted)) {
                wp_send_json_error(['message' => $deleted->get_error_message()]);
            } else {
                wp_send_json_success(['redirect' => admin_url('plugins.php?deleted=true')]);
            }
        }

        // Если "Удалить все данные"
        if ($action_mode === 'delete') {
            // Создаём бекап, если нужно
            if ($create_backup) {
                // Используем существующий класс Backup (если доступен)
                if (class_exists('MySer\Backup')) {
                    $backup = new MySer\Backup();
                    $result = $backup->export_backup('sql');
                    // SQL по умолчанию
                    if (!$result) {
                        wp_send_json_error(['message' => 'Не удалось создать бекап.']);
                    }
                } else {
                    // Если класс не найден, просто продолжаем без бекапа
                    error_log('MySer: Класс Backup не найден для создания бекапа при удалении.');
                }
            }

            // Удаляем таблицы
            include_once MYSER_PLUGIN_DIR.'lib/includes/database.php';
            MySer\Database::drop_tables();

            // Теперь удаляем плагин, данные уже удалены
            // Устанавливаем флаг, чтобы uninstall.php не удалял таблицы повторно
            $GLOBALS['myser_keep_data'] = true;
            $deleted                    = delete_plugins(['myser/myser.php']);
            if (is_wp_error($deleted)) {
                wp_send_json_error(['message' => $deleted->get_error_message()]);
            } else {
                wp_send_json_success(['redirect' => admin_url('plugins.php?deleted=true')]);
            }
        }//end if

        wp_send_json_error(['message' => 'Неизвестный режим']);

    }//end custom_uninstall()


}//end class