<?php
namespace MySer;

defined('ABSPATH') || exit;

/**
 * AJAX-обработчик для сотрудников
 * 
 * Включает CRUD сотрудников, сетки заработка и назначения сеток.
 *
 * @package MySer
 */
class Staff_Handler extends Ajax_Handler
{
    /**
     * Регистрирует все AJAX-обработчики для сотрудников
     */
    public static function register_hooks()
    {
        $actions = [
            'myser_get_staff',
            'myser_get_staff_member',
            'myser_save_staff',
            'myser_delete_staff',
            'myser_get_salary_grids',
            'myser_save_salary_grid',
            'myser_delete_salary_grid',
            'myser_get_staff_list',
            'myser_get_staff_assignments',
            'myser_save_staff_assignment',
            'myser_delete_staff_assignment',
        ];

        foreach ($actions as $action) {
            add_action('wp_ajax_' . $action, [self::class, str_replace('myser_', '', $action)]);
        }
    }

    // ========== Staff CRUD ==========

    public static function get_staff()
    {
        self::verify_nonce();
        global $wpdb;
        $tables   = Database::get_tables();
        $page     = intval(($_POST['page'] ?? 1));
        $per_page = intval(($_POST['per_page'] ?? 20));
        $search   = sanitize_text_field(($_POST['search'] ?? ''));
        $offset   = (($page - 1) * $per_page);

        $where  = ['1=1'];
        $params = [];
        if (!empty($search)) {
            $where[]  = '(staff_name LIKE %s OR email LIKE %s OR mobile_phone LIKE %s)';
            $like     = '%' . $wpdb->esc_like($search) . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $where_clause = implode(' AND ', $where);

        try {
            if (empty($params)) {
                $total = $wpdb->get_var("SELECT COUNT(*) FROM {$tables['staff']} WHERE $where_clause");
            } else {
                $total = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$tables['staff']} WHERE $where_clause", $params));
            }

            if (empty($params)) {
                $sql = $wpdb->prepare("SELECT * FROM {$tables['staff']} WHERE $where_clause ORDER BY id DESC LIMIT %d OFFSET %d", $per_page, $offset);
            } else {
                $sql = $wpdb->prepare(
                    "SELECT * FROM {$tables['staff']} WHERE $where_clause ORDER BY id DESC LIMIT %d OFFSET %d",
                    array_merge($params, [$per_page, $offset])
                );
            }

            $staff = $wpdb->get_results($sql);

            // Разрешаем JSON department в названия подразделений
            if ($staff) {
                $all_dept_ids = [];
                foreach ($staff as $s) {
                    if (!empty($s->department)) {
                        $ids = json_decode($s->department, true);
                        if (is_array($ids)) {
                            foreach ($ids as $did) {
                                $all_dept_ids[] = intval($did);
                            }
                        }
                    }
                }
                $dept_map = [];
                if (!empty($all_dept_ids)) {
                    $all_dept_ids = array_unique($all_dept_ids);
                    $placeholders = implode(',', array_fill(0, count($all_dept_ids), '%d'));
                    $dept_table = $wpdb->prefix . 'myser_departments';
                    $dept_rows = $wpdb->get_results($wpdb->prepare(
                        "SELECT id, full_name FROM `{$dept_table}` WHERE id IN ($placeholders)",
                        $all_dept_ids
                    ));
                    foreach ($dept_rows as $dr) {
                        $dept_map[$dr->id] = $dr->full_name;
                    }
                }
                // Подменяем department на строку с названиями
                foreach ($staff as $s) {
                    if (!empty($s->department)) {
                        $decoded = json_decode($s->department, true);
                        if (is_array($decoded) && !empty($decoded)) {
                            // Новый формат: JSON-массив ID
                            $names = [];
                            foreach ($decoded as $did) {
                                $did = intval($did);
                                if (isset($dept_map[$did])) {
                                    $names[] = $dept_map[$did];
                                }
                            }
                            $s->department = !empty($names) ? implode(', ', $names) : '';
                        } elseif (is_array($decoded) && empty($decoded)) {
                            // Пустой массив — нет подразделений
                            $s->department = '';
                        } else {
                            // Старый формат: обычная строка (название подразделения)
                            // Оставляем как есть, если не удалось декодировать
                        }
                    } else {
                        $s->department = '';
                    }
                }
            }

            wp_send_json_success([
                'items'        => $staff,
                'total'        => (int) $total,
                'pages'        => ceil($total / $per_page),
                'current_page' => $page,
            ]);
        } catch (\Exception $e) {
            Logger::get()->error('Ошибка получения сотрудников', ['error' => $e->getMessage()]);
            wp_send_json_error(['message' => 'Ошибка БД: ' . $e->getMessage()]);
        }
    }

    public static function get_staff_member()
    {
        self::verify_nonce();
        global $wpdb;
        $tables = Database::get_tables();
        $id     = intval(($_POST['staff_id'] ?? 0));
        if ($id <= 0) {
            wp_send_json_error(['message' => 'Invalid staff ID']);
        }

        $member = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tables['staff']} WHERE id = %d", $id));
        if ($member) {
            // department хранится как JSON-массив ID подразделений
            if (!empty($member->department)) {
                $decoded = json_decode($member->department, true);
                $member->department_ids = is_array($decoded) ? $decoded : [];
            } else {
                $member->department_ids = [];
            }
            wp_send_json_success($member);
        } else {
            wp_send_json_error(['message' => 'Staff not found']);
        }
    }

    public static function save_staff()
    {
        self::verify_nonce();
        global $wpdb;
        $tables = Database::get_tables();

        $staff_name = sanitize_text_field(($_POST['staff_name'] ?? ''));

        // Авто-генерация short_name из staff_name
        $staff_short_name = sanitize_text_field(($_POST['staff_short_name'] ?? ''));
        if (empty($staff_short_name) && !empty($staff_name)) {
            $parts = explode(' ', $staff_name);
            $staff_short_name = $parts[0];
            if (isset($parts[1])) {
                $staff_short_name .= ' ' . mb_substr($parts[1], 0, 1) . '.';
            }
            if (isset($parts[2])) {
                $staff_short_name .= mb_substr($parts[2], 0, 1) . '.';
            }
        }

        $data = [
            'staff_name'           => $staff_name,
            'staff_short_name'     => $staff_short_name,
            'use_in_schedule'      => intval(($_POST['use_in_schedule'] ?? 1)),
            'mobile_phone'         => sanitize_text_field(($_POST['mobile_phone'] ?? '')),
            'work_phone'           => sanitize_text_field(($_POST['work_phone'] ?? '')),
            'home_phone'           => sanitize_text_field(($_POST['home_phone'] ?? '')),
            'birth_day'            => sanitize_text_field(($_POST['birth_day'] ?? null)),
            'email'                => sanitize_email(($_POST['email'] ?? '')),
            'work_start_date'      => sanitize_text_field(($_POST['work_start_date'] ?? null)),
            'staff_position'       => sanitize_text_field(($_POST['staff_position'] ?? '')),
            'specialization'       => sanitize_text_field(($_POST['specialization'] ?? '')),
            'department'           => json_encode(array_map('intval', ($_POST['department_ids'] ?? []))),
            'work_status'          => sanitize_text_field(($_POST['status'] ?? $_POST['work_status'] ?? 'works')),
            'branch'               => sanitize_text_field(($_POST['branch'] ?? '')),
            'supervisor_id'        => intval(($_POST['supervisor_id'] ?? 0)) ?: null,
            'tabel_number'         => sanitize_text_field(($_POST['tabel_number'] ?? '')),
            'passport'             => sanitize_textarea_field(($_POST['passport'] ?? '')),
            'registration_address' => sanitize_textarea_field(($_POST['registration_address'] ?? '')),
            'real_address'         => sanitize_textarea_field(($_POST['real_address'] ?? '')),
            'family_status'        => sanitize_text_field(($_POST['family_status'] ?? '')),
            'kids'                 => intval(($_POST['kids'] ?? 0)),
            'car'                  => sanitize_text_field(($_POST['car'] ?? '')),
            'driving_licence'      => sanitize_text_field(($_POST['driving_licence'] ?? '')),
            'notes'                => sanitize_textarea_field(($_POST['notes'] ?? '')),
            'salary'               => floatval(($_POST['salary'] ?? 0)),
            'percent_service'      => floatval(($_POST['percent_service'] ?? 0)),
            'percent_products'     => floatval(($_POST['percent_products'] ?? 0)),
            'extra_data'           => sanitize_text_field(($_POST['extra_data'] ?? '')),
        ];

        $id = intval(($_POST['id'] ?? 0));
        try {
            if ($id > 0) {
                $wpdb->update($tables['staff'], $data, ['id' => $id]);
                // Сохраняем роли, если переданы
                parent::handle_staff_roles($id);
                // Синхронизируем subject_roles из roles_table
                parent::sync_staff_roles($id);
                Logger::get()->info('Сотрудник обновлён', ['id' => $id]);
                parent::update_department_staff_counts();
                wp_send_json_success(['message' => 'Сотрудник обновлён', 'id' => $id]);
            } else {
                $wpdb->insert($tables['staff'], $data);
                $new_id = $wpdb->insert_id;
                // Сохраняем роли, если переданы
                parent::handle_staff_roles($new_id);
                // Синхронизируем subject_roles из roles_table
                parent::sync_staff_roles($new_id);
                Logger::get()->info('Сотрудник создан', ['id' => $new_id]);
                parent::update_department_staff_counts();
                wp_send_json_success(['message' => 'Сотрудник создан', 'id' => $new_id]);
            }
        } catch (\Exception $e) {
            Logger::get()->error('Ошибка сохранения сотрудника', ['error' => $e->getMessage()]);
            wp_send_json_error(['message' => 'Ошибка БД: ' . $e->getMessage()]);
        }
    }

    public static function delete_staff()
    {
        self::verify_nonce();
        global $wpdb;
        $tables = Database::get_tables();
        $id     = intval(($_POST['staff_id'] ?? 0));
        if ($id <= 0) {
            wp_send_json_error(['message' => 'Invalid staff ID']);
        }

        try {
            $result = $wpdb->delete($tables['staff'], ['id' => $id]);
            if ($result) {
                Logger::get()->info('Сотрудник удалён', ['id' => $id]);
                parent::update_department_staff_counts();
                wp_send_json_success(['message' => 'Сотрудник удалён']);
            } else {
                wp_send_json_error(['message' => 'Не удалось удалить сотрудника']);
            }
        } catch (\Exception $e) {
            Logger::get()->error('Ошибка удаления сотрудника', ['error' => $e->getMessage()]);
            wp_send_json_error(['message' => 'Ошибка БД: ' . $e->getMessage()]);
        }
    }

    // ──────────────────────────────────────────────
    //  Сетки заработка сотрудников
    // ──────────────────────────────────────────────

    public static function get_salary_grids()
    {
        self::verify_nonce();
        self::check_permissions();
        global $wpdb;
        $table = $wpdb->prefix . 'myser_salary_grids';
        $grids = $wpdb->get_results("SELECT * FROM `$table` ORDER BY sort_order ASC, id ASC");
        wp_send_json_success($grids ?: []);
    }

    public static function save_salary_grid()
    {
        self::verify_nonce();
        self::check_permissions();
        global $wpdb;
        $table = $wpdb->prefix . 'myser_salary_grids';

        $id      = intval(($_POST['grid_id'] ?? 0));
        $name    = sanitize_text_field(($_POST['name'] ?? ''));
        $percent = floatval(($_POST['percent'] ?? 0));
        $sort    = intval(($_POST['sort_order'] ?? 0));

        if (empty($name)) {
            wp_send_json_error(['message' => 'Название сетки обязательно']);
        }

        $data = [
            'name'       => $name,
            'percent'    => $percent,
            'sort_order' => $sort,
        ];

        if ($id > 0) {
            $wpdb->update($table, $data, ['id' => $id]);
            wp_send_json_success(['message' => 'Сетка обновлена', 'id' => $id]);
        } else {
            $wpdb->insert($table, $data);
            wp_send_json_success(['message' => 'Сетка создана', 'id' => $wpdb->insert_id]);
        }
    }

    public static function delete_salary_grid()
    {
        self::verify_nonce();
        self::check_permissions();
        global $wpdb;
        $id = intval(($_POST['grid_id'] ?? 0));
        if ($id <= 0) {
            wp_send_json_error(['message' => 'Неверный ID сетки']);
        }

        $grids_table    = $wpdb->prefix . 'myser_salary_grids';
        $staff_table    = $wpdb->prefix . 'myser_staff_salary_grids';

        // Удаляем начисления
        $wpdb->delete($staff_table, ['grid_id' => $id]);
        // Удаляем сетку
        $wpdb->delete($grids_table, ['id' => $id]);

        wp_send_json_success(['message' => 'Сетка удалена']);
    }

    // ──────────────────────────────────────────────
    //  Назначения сеток сотрудникам
    // ──────────────────────────────────────────────

    public static function get_staff_list()
    {
        self::verify_nonce();
        self::check_permissions();
        global $wpdb;
        $table = $wpdb->prefix . 'myser_staff';
        $staff = $wpdb->get_results("SELECT id, staff_name FROM `$table` ORDER BY staff_name ASC");
        wp_send_json_success($staff ?: []);
    }

    public static function get_staff_assignments()
    {
        self::verify_nonce();
        self::check_permissions();
        global $wpdb;
        $staff_table     = $wpdb->prefix . 'myser_staff';
        $grids_table     = $wpdb->prefix . 'myser_salary_grids';
        $assign_table    = $wpdb->prefix . 'myser_staff_salary_grids';

        $assignments = $wpdb->get_results("
            SELECT
                a.id,
                a.staff_id,
                s.staff_name,
                a.grid_id,
                g.name AS grid_name,
                g.percent AS grid_percent,
                a.condition_type,
                a.condition_value,
                a.custom_percent
            FROM `$assign_table` a
            LEFT JOIN `$staff_table` s ON s.id = a.staff_id
            LEFT JOIN `$grids_table` g ON g.id = a.grid_id
            ORDER BY s.staff_name ASC, g.sort_order ASC
        ");

        wp_send_json_success($assignments ?: []);
    }

    public static function save_staff_assignment()
    {
        self::verify_nonce();
        self::check_permissions();
        global $wpdb;
        $table = $wpdb->prefix . 'myser_staff_salary_grids';

        $id              = intval(($_POST['assignment_id'] ?? 0));
        $staff_id        = intval(($_POST['staff_id'] ?? 0));
        $grid_id         = intval(($_POST['grid_id'] ?? 0));
        $condition_type_raw = $_POST['condition_type'] ?? 'custom';
        if (is_array($condition_type_raw)) {
            $condition_type = implode(',', array_map('sanitize_text_field', $condition_type_raw));
        } else {
            $condition_type = sanitize_text_field($condition_type_raw);
        }
        $condition_value = sanitize_text_field(($_POST['condition_value'] ?? ''));
        $custom_percent  = $_POST['custom_percent'] !== '' ? floatval($_POST['custom_percent']) : null;

        if ($staff_id <= 0 || $grid_id <= 0) {
            wp_send_json_error(['message' => 'Сотрудник и сетка обязательны']);
        }

        $data = [
            'staff_id'        => $staff_id,
            'grid_id'         => $grid_id,
            'condition_type'  => $condition_type,
            'condition_value' => $condition_value ?: null,
            'custom_percent'  => $custom_percent,
        ];

        if ($id > 0) {
            $wpdb->update($table, $data, ['id' => $id]);
            wp_send_json_success(['message' => 'Назначение обновлено', 'id' => $id]);
        } else {
            $wpdb->insert($table, $data);
            wp_send_json_success(['message' => 'Назначение создано', 'id' => $wpdb->insert_id]);
        }
    }

    public static function delete_staff_assignment()
    {
        self::verify_nonce();
        self::check_permissions();
        global $wpdb;
        $id = intval(($_POST['assignment_id'] ?? 0));
        if ($id <= 0) {
            wp_send_json_error(['message' => 'Неверный ID начисления']);
        }
        $table = $wpdb->prefix . 'myser_staff_salary_grids';
        $wpdb->delete($table, ['id' => $id]);
        wp_send_json_success(['message' => 'Назначение удалено']);
    }
}
