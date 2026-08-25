<?php
/**
 * AJAX обработчики для справочников
 * Эндпоинты: myser_search_reference, myser_save_reference
 */
if (!defined('ABSPATH')) {
    exit;
}

class Myser_AJAX_Reference {
    
    /**
     * Поиск по справочнику
     */
    public static function search() {
        check_ajax_referer('myser_nonce', 'nonce');
        
        if (!current_user_can('edit_others_posts')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }
        
        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $per_page = 20;
        $offset = ($page - 1) * $per_page;
        
        $allowed_types = ['devices', 'brands', 'components', 'colors'];
        if (!in_array($type, $allowed_types)) {
            wp_send_json_error(['message' => 'Недопустимый тип справочника']);
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'myser_' . $type;
        
        // Проверяем существование таблицы
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
        if (!$table_exists) {
            wp_send_json_success([
                'items' => [],
                'total' => 0,
                'total_pages' => 1,
                'current_page' => 1
            ]);
            return;
        }
        
        // Построение запроса
        $where = '';
        $params = [];
        if (!empty($search)) {
            $where = "WHERE name LIKE %s";
            $params[] = '%' . $wpdb->esc_like($search) . '%';
        }
        
        // Получаем общее количество
        $count_sql = "SELECT COUNT(*) FROM {$table} {$where}";
        if (!empty($params)) {
            $count_sql = $wpdb->prepare($count_sql, $params);
        }
        $total = $wpdb->get_var($count_sql);
        $total_pages = ceil($total / $per_page);
        
        // Получаем элементы
        $sql = "SELECT id, name";
        
        // Для цветов добавляем color_code
        if ($type === 'colors') {
            $sql .= ", color_code";
        }
        
        $sql .= " FROM {$table} {$where} ORDER BY name LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = $offset;
        
        $sql = $wpdb->prepare($sql, $params);
        $items = $wpdb->get_results($sql, ARRAY_A);
        
        wp_send_json_success([
            'items' => $items,
            'total' => intval($total),
            'total_pages' => intval($total_pages),
            'current_page' => intval($page)
        ]);
    }
    
    /**
     * Сохранение нового элемента справочника
     */
    public static function save() {
        check_ajax_referer('myser_nonce', 'nonce');
        
        if (!current_user_can('edit_others_posts')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }
        
        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';
        $name = isset($_POST['name']) ? trim(sanitize_text_field($_POST['name'])) : '';
        $hex_code = isset($_POST['hex_code']) ? sanitize_text_field($_POST['hex_code']) : '';
        
        if (empty($type) || empty($name)) {
            wp_send_json_error(['message' => 'Тип и название обязательны']);
        }
        
        $allowed_types = ['devices', 'brands', 'components', 'colors'];
        if (!in_array($type, $allowed_types)) {
            wp_send_json_error(['message' => 'Недопустимый тип справочника']);
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'myser_' . $type;
        
        // Проверяем существование таблицы
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
        if (!$table_exists) {
            wp_send_json_error(['message' => 'Таблица не найдена']);
            return;
        }
        
        // Проверяем дубликат
        $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE name = %s", $name));
        if ($existing) {
            wp_send_json_error(['message' => 'Элемент с таким названием уже существует']);
            return;
        }
        
        $data = [
            'name' => $name,
            'sort_order' => 0,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ];
        
        // Для цветов добавляем hex_code
        if ($type === 'colors' && !empty($hex_code)) {
            $data['hex_code'] = $hex_code;
        }
        
        $result = $wpdb->insert($table, $data);
        
        if ($result === false) {
            wp_send_json_error(['message' => 'Ошибка сохранения: ' . $wpdb->last_error]);
            return;
        }
        
        $insert_id = $wpdb->insert_id;
        
        wp_send_json_success([
            'id' => $insert_id,
            'name' => $name,
            'hex_code' => $hex_code,
            'message' => 'Элемент успешно добавлен'
        ]);
    }
}

// Регистрируем AJAX хуки
add_action('wp_ajax_myser_search_reference', ['Myser_AJAX_Reference', 'search']);
add_action('wp_ajax_myser_save_reference', ['Myser_AJAX_Reference', 'save']);
