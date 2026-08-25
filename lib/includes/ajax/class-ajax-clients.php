<?php
namespace MySer;

defined('ABSPATH') || exit;

/**
 * AJAX-обработчики для клиентов
 *
 * @package MySer
 */
class Clients_Handler extends Ajax_Handler
{
    public static function register_hooks()
    {
        $actions = [
            'myser_get_clients',
            'myser_get_client',
            'myser_save_client',
            'myser_delete_client',
        ];
        foreach ($actions as $action) {
            add_action('wp_ajax_' . $action, [self::class, str_replace('myser_', '', $action)]);
        }
    }

    public static function get_clients()
    {
        self::verify_nonce();
        global $wpdb;
        $tables   = Database::get_tables();
        $page     = intval(($_POST['page'] ?? 1));
        $per_page = intval(($_POST['per_page'] ?? 20));
        $search   = sanitize_text_field(($_POST['search'] ?? ''));
        $offset   = (($page - 1) * $per_page);

        Logger::get()->debug('Запрос клиентов', ['page' => $page, 'search' => $search]);

        $where  = ['1=1'];
        $params = [];
        if (!empty($search)) {
            $where[]  = '(last_name LIKE %s OR first_name LIKE %s OR middle_name LIKE %s OR phone LIKE %s OR email LIKE %s)';
            $like     = '%'.$wpdb->esc_like($search).'%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $where_clause = implode(' AND ', $where);

        try {
            if (empty($params)) {
                $count_sql = "SELECT COUNT(*) FROM {$tables['clients']} WHERE $where_clause";
                $total     = $wpdb->get_var($count_sql);
            } else {
                $count_sql = $wpdb->prepare("SELECT COUNT(*) FROM {$tables['clients']} WHERE $where_clause", $params);
                $total     = $wpdb->get_var($count_sql);
            }

            if (empty($params)) {
                $sql = "SELECT * FROM {$tables['clients']} WHERE $where_clause ORDER BY id DESC LIMIT %d OFFSET %d";
                $sql = $wpdb->prepare($sql, $per_page, $offset);
            } else {
                $sql = $wpdb->prepare(
                    "SELECT * FROM {$tables['clients']} WHERE $where_clause ORDER BY id DESC LIMIT %d OFFSET %d",
                    array_merge($params, [$per_page, $offset])
                );
            }

            $clients = $wpdb->get_results($sql);
            
            // Добавляем поле service_discount_percent из extra_data
            foreach ($clients as &$client) {
                $extra = json_decode($client->extra_data, true);
                $client->service_discount_percent = isset($extra['service_discount_percent']) ? (int)$extra['service_discount_percent'] : 0;
            }
            
            Logger::get()->debug('Клиенты получены', ['count' => count($clients), 'total' => $total]);

            wp_send_json_success(
                [
                    'items'        => $clients,
                    'total'        => (int) $total,
                    'pages'        => ceil($total / $per_page),
                    'current_page' => $page,
                ]
            );
        } catch (\Exception $e) {
            Logger::get()->error('Ошибка получения клиентов', ['error' => $e->getMessage()]);
            wp_send_json_error(['message' => 'Ошибка БД: '.$e->getMessage()]);
        }//end try

    }//end get_clients()


    public static function get_client()
    {
        self::verify_nonce();
        $id = intval(($_POST['client_id'] ?? 0));
        if ($id <= 0) {
            wp_send_json_error(['message' => __('Invalid client ID', 'myser')]);
        }

        $client = Database::get_client($id);
        if ($client) {
            wp_send_json_success($client);
        } else {
            wp_send_json_error(['message' => __('Client not found', 'myser')]);
        }

    }//end get_client()


    public static function save_client()
    {
        self::verify_nonce();
        global $wpdb;
        $tables = Database::get_tables();
        // Тип клиента: из JS приходит 'person'/'company', в БД 'individual'/'company'
        $client_type = sanitize_text_field($_POST['client_type'] ?? 'individual');
        $type_db     = ($client_type === 'person') ? 'individual' : $client_type;

        // Адрес: если передан как строка, используем её, иначе собираем из частей
        $address_raw = sanitize_textarea_field($_POST['address'] ?? '');
        if (empty($address_raw)) {
            $parts = [];
            foreach (['city', 'street', 'house'] as $key) {
                $val = sanitize_text_field($_POST[$key] ?? '');
                if ($val !== '') {
                    $parts[] = $val;
                }
            }
            $address = implode(', ', $parts);
        } else {
            $address = $address_raw;
        }

        // Дополнительные поля — в extra_data (JSON)
        $extra_fields = [
            'company_name'           => sanitize_text_field($_POST['company_name'] ?? ''),
            'legal_form'             => sanitize_text_field($_POST['legal_form'] ?? ''),
            'city'                   => sanitize_text_field($_POST['city'] ?? ''),
            'street'                 => sanitize_text_field($_POST['street'] ?? ''),
            'house'                  => sanitize_text_field($_POST['house'] ?? ''),
            'service_discount_percent' => sanitize_text_field($_POST['service_discount_percent'] ?? ''),
        ];

        $id = intval(($_POST['id'] ?? 0));

        // Если обновляем, сохраняем старый extra_data
        $existing_extra = [];
        if ($id > 0) {
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT extra_data FROM {$tables['clients']} WHERE id = %d",
                $id
            ));
            if ($row && $row->extra_data) {
                $decoded = json_decode($row->extra_data, true);
                if (is_array($decoded)) {
                    $existing_extra = $decoded;
                }
            }
        }
        $extra_data_merged = array_merge($existing_extra, $extra_fields);
        $extra_data_json   = !empty($extra_data_merged) ? wp_json_encode($extra_data_merged, JSON_UNESCAPED_UNICODE) : '';

        $data   = [
            'last_name'          => sanitize_text_field(($_POST['last_name'] ?? '')),
            'first_name'         => sanitize_text_field(($_POST['first_name'] ?? '')),
            'middle_name'        => sanitize_text_field(($_POST['middle_name'] ?? '')),
            'phone'              => sanitize_text_field(($_POST['phone'] ?? '')),
            'other_phone'        => sanitize_text_field(($_POST['other_phone'] ?? '')),
            'email'              => sanitize_email(($_POST['email'] ?? '')),
            'address'            => $address,
            'type'               => $type_db,
            'is_problem_client'  => intval(($_POST['is_problem_client'] ?? 0)),
            'notes'              => sanitize_textarea_field(($_POST['notes'] ?? '')),
            'extra_data'         => $extra_data_json,
        ];

        try {
            // Синхронизация с таблицей subjects
            $subject_id = self::sync_subject('client', $data, ($id > 0 ? $id : null));
            if ($subject_id) {
                $data['subject_id'] = $subject_id;
            }

            if ($id > 0) {
                $wpdb->update($tables['clients'], $data, ['id' => $id]);
                Logger::get()->info('Клиент обновлён', ['id' => $id, 'subject_id' => $subject_id]);
                wp_send_json_success(['message' => 'Клиент обновлён', 'id' => $id, 'subject_id' => $subject_id]);
            } else {
                $wpdb->insert($tables['clients'], $data);
                $new_id = $wpdb->insert_id;
                Logger::get()->info('Клиент создан', ['id' => $new_id, 'subject_id' => $subject_id]);
                wp_send_json_success(['message' => 'Клиент создан', 'id' => $new_id, 'subject_id' => $subject_id]);
            }
        } catch (\Exception $e) {
            Logger::get()->error('Ошибка сохранения клиента', ['error' => $e->getMessage()]);
            wp_send_json_error(['message' => 'Ошибка БД: '.$e->getMessage()]);
        }

    }//end save_client()


    public static function delete_client()
    {
        self::verify_nonce();
        global $wpdb;
        $tables = Database::get_tables();
        $id     = intval(($_POST['client_id'] ?? 0));
        if ($id <= 0) {
            wp_send_json_error(['message' => __('Invalid client ID', 'myser')]);
        }

        try {
            $result = $wpdb->delete($tables['clients'], ['id' => $id]);
            if ($result) {
                Logger::get()->info('Клиент удалён', ['id' => $id]);
                wp_send_json_success(['message' => 'Клиент удалён']);
            } else {
                wp_send_json_error(['message' => 'Не удалось удалить клиента']);
            }
        } catch (\Exception $e) {
            Logger::get()->error('Ошибка удаления клиента', ['error' => $e->getMessage()]);
            wp_send_json_error(['message' => 'Ошибка БД: '.$e->getMessage()]);
        }

    }//end delete_client()

}//end class
