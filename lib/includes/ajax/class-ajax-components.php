<?php
/**
 * AJAX-обработчик для справочника комплектующих и комбинаций комплектации.
 *
 * @package MySer
 */

namespace MySer\Ajax;

defined('ABSPATH') || exit;

class Components_Handler
{
    /**
     * Регистрация AJAX-хуков.
     */
    public static function register_hooks()
    {
        add_action('wp_ajax_myser_get_components', [__CLASS__, 'get_components']);
        add_action('wp_ajax_myser_add_component', [__CLASS__, 'add_component']);
        add_action('wp_ajax_myser_get_component_combinations', [__CLASS__, 'get_combinations']);
        add_action('wp_ajax_myser_save_component_combination', [__CLASS__, 'save_combination']);
        add_action('wp_ajax_myser_delete_component_combination', [__CLASS__, 'delete_combination']);
        add_action('wp_ajax_myser_search_components', [__CLASS__, 'search_components']);
    }

    /**
     * Проверка nonce и прав.
     */
    protected static function verify_request()
    {
        $nonce = $_POST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'myser_ajax_nonce')) {
            wp_send_json_error(['message' => 'Invalid nonce'], 403);
        }
        if (!current_user_can('myser_access')) {
            wp_send_json_error(['message' => 'Access denied'], 403);
        }
    }

    /**
     * Получить список всех компонентов (для автодополнения).
     */
    public static function get_components()
    {
        self::verify_request();
        global $wpdb;

        $table = $wpdb->prefix . 'myser_components';
        $results = $wpdb->get_results(
            "SELECT id, name, sort_order FROM `$table` ORDER BY sort_order ASC"
        );

        wp_send_json_success(['components' => $results]);
    }

    /**
     * Поиск компонентов по подстроке (для автодополнения).
     */
    public static function search_components()
    {
        self::verify_request();
        global $wpdb;

        $query = trim($_POST['query'] ?? '');
        if (empty($query)) {
            wp_send_json_success(['components' => []]);
            return;
        }

        $table = $wpdb->prefix . 'myser_components';
        $like = '%' . $wpdb->esc_like($query) . '%';
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, name FROM `$table` WHERE name LIKE %s ORDER BY name ASC LIMIT 20",
                $like
            )
        );

        wp_send_json_success(['components' => $results]);
    }

    /**
     * Добавить новый компонент в справочник (если ещё не существует).
     */
    public static function add_component()
    {
        self::verify_request();
        global $wpdb;

        $name = trim($_POST['name'] ?? '');
        if (empty($name)) {
            wp_send_json_error(['message' => 'Название компонента не может быть пустым']);
            return;
        }

        $table = $wpdb->prefix . 'myser_components';

        // Проверяем, существует ли уже
        $existing = $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM `$table` WHERE name = %s", $name)
        );
        if ($existing) {
            wp_send_json_success([
                'component' => ['id' => $existing, 'name' => $name],
                'exists' => true
            ]);
            return;
        }

        // Получаем максимальный sort_order для вставки в конец
        $max_order = (int) $wpdb->get_var("SELECT MAX(sort_order) FROM `$table`");
        $sort_order = $max_order + 1;

        $inserted = $wpdb->insert(
            $table,
            [
                'name' => $name,
                'sort_order' => $sort_order,
                'created_at' => current_time('mysql'),
            ],
            ['%s', '%d', '%s']
        );

        if ($inserted) {
            $id = $wpdb->insert_id;
            wp_send_json_success([
                'component' => ['id' => $id, 'name' => $name],
                'exists' => false
            ]);
        } else {
            wp_send_json_error(['message' => 'Ошибка при добавлении компонента']);
        }
    }

    /**
     * Получить все сохранённые комбинации комплектации.
     */
    public static function get_combinations()
    {
        self::verify_request();
        global $wpdb;

        $table = $wpdb->prefix . 'myser_component_combinations';
        $results = $wpdb->get_results(
            "SELECT id, name, components, created_at, updated_at 
             FROM `$table` 
             ORDER BY name ASC"
        );

        wp_send_json_success(['combinations' => $results]);
    }

    /**
     * Сохранить комбинацию комплектации (создать или обновить).
     * Комбинации должны быть уникальны по составу (сортируем компоненты).
     */
    public static function save_combination()
    {
        self::verify_request();
        global $wpdb;

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $name = trim($_POST['name'] ?? '');
        $components_raw = $_POST['components'] ?? '';

        if (empty($name)) {
            wp_send_json_error(['message' => 'Название комбинации не может быть пустым']);
            return;
        }

        // Разбираем компоненты из строки (через запятую) или из массива
        if (is_array($components_raw)) {
            $components_array = array_map('trim', $components_raw);
        } else {
            $components_array = array_map('trim', explode(',', $components_raw));
        }
        $components_array = array_filter($components_array); // убираем пустые
        $components_array = array_unique($components_array);
        sort($components_array); // сортируем для уникальности по составу

        $components_string = implode(', ', $components_array);

        if (empty($components_string)) {
            wp_send_json_error(['message' => 'Добавьте хотя бы один компонент']);
            return;
        }

        $table = $wpdb->prefix . 'myser_component_combinations';

        // Проверяем уникальность по составу (исключаем текущую запись при обновлении)
        $duplicate_check = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM `$table` WHERE components = %s AND id != %d",
                $components_string,
                $id
            )
        );
        if ($duplicate_check) {
            wp_send_json_error(['message' => 'Комбинация с таким составом уже существует']);
            return;
        }

        $data = [
            'name' => $name,
            'components' => $components_string,
            'updated_at' => current_time('mysql'),
        ];
        $format = ['%s', '%s', '%s'];

        if ($id > 0) {
            // Обновление существующей
            $updated = $wpdb->update(
                $table,
                $data,
                ['id' => $id],
                $format,
                ['%d']
            );
            if ($updated !== false) {
                wp_send_json_success(['id' => $id, 'message' => 'Комбинация обновлена']);
            } else {
                wp_send_json_error(['message' => 'Ошибка при обновлении комбинации']);
            }
        } else {
            // Создание новой
            $data['created_at'] = current_time('mysql');
            $format = ['%s', '%s', '%s', '%s'];

            $inserted = $wpdb->insert(
                $table,
                $data,
                $format
            );
            if ($inserted) {
                wp_send_json_success([
                    'id' => $wpdb->insert_id,
                    'message' => 'Комбинация сохранена'
                ]);
            } else {
                wp_send_json_error(['message' => 'Ошибка при создании комбинации']);
            }
        }
    }

    /**
     * Удалить комбинацию комплектации.
     */
    public static function delete_combination()
    {
        self::verify_request();
        global $wpdb;

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        if ($id <= 0) {
            wp_send_json_error(['message' => 'Неверный ID']);
            return;
        }

        $table = $wpdb->prefix . 'myser_component_combinations';
        $deleted = $wpdb->delete(
            $table,
            ['id' => $id],
            ['%d']
        );

        if ($deleted) {
            wp_send_json_success(['message' => 'Комбинация удалена']);
        } else {
            wp_send_json_error(['message' => 'Ошибка при удалении комбинации']);
        }
    }
}
