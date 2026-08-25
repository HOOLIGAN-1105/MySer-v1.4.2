<?php
/**
 * MySer AJAX Reference Class
 *
 * Handles reference data (brands, devices, components) AJAX requests
 */

namespace MySer\Includes\Ajax;

if (!defined('ABSPATH')) {
    exit;
}

class AjaxReference {

    private static $allowed_types = ['brands', 'devices', 'components', 'colors'];

    public static function init() {
        add_action('wp_ajax_myser_get_reference_item', [self::class, 'get_item']);
        add_action('wp_ajax_myser_get_reference_items', [self::class, 'get_items']);
        add_action('wp_ajax_myser_save_reference_item', [self::class, 'save_item']);
        add_action('wp_ajax_myser_save_reference', [self::class, 'save']);
        add_action('wp_ajax_myser_delete_reference_item', [self::class, 'delete_item']);
    }

    /**
     * Get list of reference items with pagination and search
     */
    public static function get_items() {
        // Отладочный вывод (закомментировано для production)
        // error_log('=== AJAX get_items called ===');
        // error_log('POST data: ' . print_r($_POST, true));

        check_ajax_referer('myser_nonce', 'nonce');

        if (!current_user_can('edit_others_posts')) {
            wp_send_json_error(['message' => __('Permission denied', 'myser')]);
        }

        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
        $per_page = isset($_POST['per_page']) ? (int)$_POST['per_page'] : 20;

        // error_log("Type: $type, Search: $search, Page: $page");

        if (!in_array($type, self::$allowed_types, true)) {
            wp_send_json_error(['message' => __('Invalid reference type', 'myser')]);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'myser_' . $type;
        // error_log("Table: $table");

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            wp_send_json_error(['message' => __('Table not found', 'myser')]);
        }

        // Build where clause
        $where = '';
        $params = [];
        if (!empty($search)) {
            $where = "WHERE name LIKE %s";
            $params[] = '%' . $wpdb->esc_like($search) . '%';
        }

        // Get total count
        $count_query = "SELECT COUNT(*) FROM `$table` $where";
        if (!empty($params)) {
            $count_query = $wpdb->prepare($count_query, $params);
        }
        $total = (int)$wpdb->get_var($count_query);

        // Get items with pagination
        $offset = ($page - 1) * $per_page;
        // Для цветов добавляем color_code
        if ($type === 'colors') {
            $items_query = "SELECT id, name, color_code FROM `$table` $where ORDER BY name ASC LIMIT %d OFFSET %d";
        } else {
            $items_query = "SELECT id, name, description FROM `$table` $where ORDER BY name ASC LIMIT %d OFFSET %d";
        }

        $query_params = $params;
        $query_params[] = $per_page;
        $query_params[] = $offset;

        if (!empty($params)) {
            $items_query = $wpdb->prepare($items_query, $query_params);
        } else {
            $items_query = $wpdb->prepare($items_query, $per_page, $offset);
        }

        $items = $wpdb->get_results($items_query, ARRAY_A);

        wp_send_json_success([
            'items' => $items,
            'total' => $total,
            'pages' => ceil($total / $per_page),
            'current_page' => $page,
            'per_page' => $per_page
        ]);
    }

    /**
     * Get a single reference item
     */
    public static function get_item() {
        check_ajax_referer('myser_nonce', 'nonce');

        if (!current_user_can('edit_others_posts')) {
            wp_send_json_error(['message' => __('Permission denied', 'myser')]);
        }

        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

        if (!in_array($type, self::$allowed_types, true)) {
            wp_send_json_error(['message' => __('Invalid reference type', 'myser')]);
        }

        if (!$id) {
            wp_send_json_error(['message' => __('Invalid ID', 'myser')]);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'myser_' . $type;

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            wp_send_json_error(['message' => __('Table not found', 'myser')]);
        }

        $item = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM `$table` WHERE id = %d",
            $id
        ), ARRAY_A);

        if (!$item) {
            wp_send_json_error(['message' => __('Item not found', 'myser')]);
        }

        wp_send_json_success($item);
    }

    /**
     * Create a reference item from the reference selection modal
     */
    public static function save() {
        check_ajax_referer('myser_nonce', 'nonce');

        if (!current_user_can('edit_others_posts')) {
            wp_send_json_error(['message' => __('Permission denied', 'myser')]);
        }

        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';

        if (!in_array($type, self::$allowed_types, true)) {
            wp_send_json_error(['message' => __('Invalid reference type', 'myser')]);
        }

        if (empty($name)) {
            wp_send_json_error(['message' => __('Name is required', 'myser')]);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'myser_' . $type;

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            wp_send_json_error(['message' => __('Table not found', 'myser')]);
        }

        $duplicate = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM `$table` WHERE name = %s",
            $name
        ));

        if ($duplicate > 0) {
            wp_send_json_error(['message' => __('Name already exists', 'myser')]);
        }

        if ($type === 'colors') {
            $color_code = isset($_POST['hex_code']) ? sanitize_text_field($_POST['hex_code']) : '';
            if (empty($color_code)) {
                wp_send_json_error(['message' => __('Color code is required', 'myser')]);
            }
            if (!preg_match('/^#?[0-9a-fA-F]{6}$/', $color_code)) {
                wp_send_json_error(['message' => __('Invalid color format', 'myser')]);
            }
            if ($color_code[0] !== '#') {
                $color_code = '#' . $color_code;
            }
            $inserted = $wpdb->insert($table, [
                'name' => $name,
                'color_code' => $color_code
            ]);
        } else {
            $description = isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '';
            $inserted = $wpdb->insert($table, [
                'name' => $name,
                'description' => $description
            ]);
        }

        if (!$inserted) {
            wp_send_json_error(['message' => __('Failed to create item', 'myser')]);
        }

        wp_send_json_success([
            'message' => __('Saved successfully', 'myser'),
            'id' => $wpdb->insert_id
        ]);
    }

    /**
     * Save a reference item (create or update)
     */
    public static function save_item() {
        check_ajax_referer('myser_nonce', 'nonce');

        if (!current_user_can('edit_others_posts')) {
            wp_send_json_error(['message' => __('Permission denied', 'myser')]);
        }

        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $description = isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '';

        if (!in_array($type, self::$allowed_types, true)) {
            wp_send_json_error(['message' => __('Invalid reference type', 'myser')]);
        }

        if (empty($name)) {
            wp_send_json_error(['message' => __('Name is required', 'myser')]);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'myser_' . $type;

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            wp_send_json_error(['message' => __('Table not found', 'myser')]);
        }

        // Check for duplicate name
        $duplicate = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM `$table` WHERE name = %s AND id != %d",
            $name,
            $id
        ));

        if ($duplicate > 0) {
            wp_send_json_error(['message' => __('Name already exists', 'myser')]);
        }

        $data = [
            'name' => $name,
            'description' => $description
        ];

        if ($id > 0) {
            // Update existing
            $updated = $wpdb->update($table, $data, ['id' => $id]);
            if ($updated === false) {
                wp_send_json_error(['message' => __('Failed to update item', 'myser')]);
            }
        } else {
            // Insert new
            $inserted = $wpdb->insert($table, $data);
            if (!$inserted) {
                wp_send_json_error(['message' => __('Failed to create item', 'myser')]);
            }
            $id = $wpdb->insert_id;
        }

        wp_send_json_success([
            'message' => __('Saved successfully', 'myser'),
            'id' => $id
        ]);
    }

    /**
     * Delete a reference item
     */
    public static function delete_item() {
        check_ajax_referer('myser_nonce', 'nonce');

        if (!current_user_can('edit_others_posts')) {
            wp_send_json_error(['message' => __('Permission denied', 'myser')]);
        }

        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

        if (!in_array($type, self::$allowed_types, true)) {
            wp_send_json_error(['message' => __('Invalid reference type', 'myser')]);
        }

        if (!$id) {
            wp_send_json_error(['message' => __('Invalid ID', 'myser')]);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'myser_' . $type;

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            wp_send_json_error(['message' => __('Table not found', 'myser')]);
        }

        $deleted = $wpdb->delete($table, ['id' => $id]);

        if ($deleted === false) {
            wp_send_json_error(['message' => __('Failed to delete item', 'myser')]);
        }

        wp_send_json_success(['message' => __('Deleted successfully', 'myser')]);
    }
}