<?php
namespace MySer;

defined('ABSPATH') || exit;

/**
 * Обработчик AJAX-запросов для заказов
 *
 * @package MySer
 */
class Orders_Handler extends Ajax_Handler
{

    /**
     * Регистрирует хуки WordPress для AJAX-обработчиков заказов
     */
    public static function register_hooks()
    {
        add_action('wp_ajax_myser_get_orders', [self::class, 'get_orders']);
        add_action('wp_ajax_myser_get_order', [self::class, 'get_order']);
        add_action('wp_ajax_myser_save_order', [self::class, 'save_order']);
        add_action('wp_ajax_myser_delete_order', [self::class, 'delete_order']);
    }


    public static function get_orders()
    {
        self::verify_nonce();
        global $wpdb;
        $tables   = Database::get_tables();
        $page     = intval(($_POST['page'] ?? 1));
        $per_page = intval(($_POST['per_page'] ?? 20));
        $search   = sanitize_text_field(($_POST['search'] ?? ''));
        $offset   = (($page - 1) * $per_page);

        Logger::get()->debug('Запрос заказов', ['page' => $page, 'search' => $search]);

        $where  = ['1=1'];
        $params = [];
        if (!empty($search)) {
            $where[]  = '(doc_number LIKE %s OR client_complaint LIKE %s)';
            $like     = '%'.$wpdb->esc_like($search).'%';
            $params[] = $like;
            $params[] = $like;
        }

        $where_clause = implode(' AND ', $where);

        try {
            if (empty($params)) {
                $count_sql = "SELECT COUNT(*) FROM {$tables['orders']} WHERE $where_clause";
                $total     = $wpdb->get_var($count_sql);
            } else {
                $count_sql = $wpdb->prepare("SELECT COUNT(*) FROM {$tables['orders']} WHERE $where_clause", $params);
                $total     = $wpdb->get_var($count_sql);
            }

            if (empty($params)) {
                $sql = "SELECT * FROM {$tables['orders']} WHERE $where_clause ORDER BY id DESC LIMIT %d OFFSET %d";
                $sql = $wpdb->prepare($sql, $per_page, $offset);
            } else {
                $sql = $wpdb->prepare(
                    "SELECT * FROM {$tables['orders']} WHERE $where_clause ORDER BY id DESC LIMIT %d OFFSET %d",
                    array_merge($params, [$per_page, $offset])
                );
            }

            $orders = $wpdb->get_results($sql);
            Logger::get()->debug('Заказы получены', ['count' => count($orders), 'total' => $total]);

            wp_send_json_success(
                [
                    'items'        => $orders,
                    'total'        => (int) $total,
                    'pages'        => ceil($total / $per_page),
                    'current_page' => $page,
                ]
            );
        } catch (\Exception $e) {
            Logger::get()->error('Ошибка получения заказов', ['error' => $e->getMessage()]);
            wp_send_json_error(['message' => 'Ошибка БД: '.$e->getMessage()]);
        }//end try

    }//end get_orders()


    public static function get_order()
    {
        self::verify_nonce();
        $id = intval(($_POST['order_id'] ?? 0));
        if ($id <= 0) {
            wp_send_json_error(['message' => 'Invalid order ID']);
        }

        $order = Database::get_order($id);
        if ($order) {
            wp_send_json_success($order);
        } else {
            wp_send_json_error(['message' => 'Order not found']);
        }

    }//end get_order()


    public static function save_order()
    {
        self::verify_nonce();
        global $wpdb;
        $tables = Database::get_tables();

        $doc_number = sanitize_text_field(($_POST['doc_number'] ?? ''));
        if (empty($doc_number)) {
            $doc_number = 'MYS-'.date('Ymd').'-'.rand(1000, 9999);
        }

        $client_id = intval(($_POST['client_id'] ?? 0));

        // Получаем subject_id клиента
        $subject_id = null;
        if ($client_id > 0) {
            $subject_id = $wpdb->get_var($wpdb->prepare(
                "SELECT subject_id FROM {$tables['clients']} WHERE id = %d",
                $client_id
            ));
        }

        $data = [
            'doc_number'          => $doc_number,
            'doc_date'            => current_time('mysql'),
            'client_id'           => $client_id,
            'subject_id'          => $subject_id,
            'device_type'         => sanitize_text_field(($_POST['device_type'] ?? '')),
            'device_manufacturer' => sanitize_text_field(($_POST['device_manufacturer'] ?? '')),
            'device_model'        => sanitize_text_field(($_POST['device_model'] ?? '')),
            'device_serial'       => sanitize_text_field(($_POST['device_serial'] ?? '')),
            'client_complaint'    => sanitize_textarea_field(($_POST['client_complaint'] ?? '')),
            'status_id'           => intval(($_POST['status_id'] ?? 1)),
            'grand_total'         => floatval(($_POST['grand_total'] ?? 0)),
        ];

        $id = intval(($_POST['id'] ?? 0));
        try {
            if ($id > 0) {
                $wpdb->update($tables['orders'], $data, ['id' => $id]);
                Logger::get()->info('Заказ обновлён', ['id' => $id, 'subject_id' => $subject_id]);
                wp_send_json_success(['message' => 'Заказ обновлён', 'id' => $id]);
            } else {
                $wpdb->insert($tables['orders'], $data);
                $new_id = $wpdb->insert_id;
                Logger::get()->info('Заказ создан', ['id' => $new_id, 'doc_number' => $doc_number, 'subject_id' => $subject_id]);
                wp_send_json_success(['message' => 'Заказ создан', 'id' => $new_id]);
            }
        } catch (\Exception $e) {
            Logger::get()->error('Ошибка сохранения заказа', ['error' => $e->getMessage()]);
            wp_send_json_error(['message' => 'Ошибка БД: '.$e->getMessage()]);
        }

    }//end save_order()


    public static function delete_order()
    {
        self::verify_nonce();
        global $wpdb;
        $tables = Database::get_tables();
        $id     = intval(($_POST['order_id'] ?? 0));
        if ($id <= 0) {
            wp_send_json_error(['message' => 'Invalid order ID']);
        }

        try {
            $result = $wpdb->delete($tables['orders'], ['id' => $id]);
            if ($result) {
                Logger::get()->info('Заказ удалён', ['id' => $id]);
                wp_send_json_success(['message' => 'Заказ удалён']);
            } else {
                wp_send_json_error(['message' => 'Не удалось удалить заказ']);
            }
        } catch (\Exception $e) {
            Logger::get()->error('Ошибка удаления заказа', ['error' => $e->getMessage()]);
            wp_send_json_error(['message' => 'Ошибка БД: '.$e->getMessage()]);
        }

    }//end delete_order()

}//end class
