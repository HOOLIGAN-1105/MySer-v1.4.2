<?php
namespace MySer;

defined('ABSPATH') || exit;

/**
 * Класс администрирования плагина MySer
 *
 * @package MySer
 */
class Admin_Menu
{

    public static function init()
    {
        add_action('admin_menu', [self::class, 'add_menu_pages']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_scripts']);
        add_action('admin_post_myser_clear_logs', [self::class, 'clear_logs']);
        add_action('admin_post_myser_download_log', [self::class, 'download_log']);
        add_action('admin_post_myser_save_log_settings', [self::class, 'save_log_settings']);
        add_action('admin_init', [self::class, 'register_settings']);
        add_action('admin_head', [self::class, 'output_theme_css']);
    }

    public static function register_settings()
    {
        register_setting(
            'myser_settings_group',
            'myser_settings',
            ['sanitize_callback' => [self::class, 'sanitize_settings']]
        );
    }

    public static function sanitize_settings($input)
    {
        $output = [];
        $output['company_name'] = sanitize_text_field($input['company_name'] ?? '');
        $output['company_phone'] = sanitize_text_field($input['company_phone'] ?? '');
        $output['company_email'] = sanitize_email($input['company_email'] ?? '');
        $output['company_address'] = sanitize_textarea_field($input['company_address'] ?? '');
        $output['order_prefix'] = sanitize_text_field($input['order_prefix'] ?? 'MYS');
        $output['items_per_page'] = intval($input['items_per_page'] ?? 20);
        $output['currency'] = sanitize_text_field($input['currency'] ?? 'RUB');
        $output['tax_rate'] = floatval($input['tax_rate'] ?? 0);
        $output['enable_notifications'] = isset($input['enable_notifications']) ? 1 : 0;
        $output['uninstall_behavior'] = sanitize_text_field($input['uninstall_behavior'] ?? 'keep');
        $output['log_level'] = sanitize_text_field($input['log_level'] ?? 'error');
        $output['log_retention_days'] = intval($input['log_retention_days'] ?? 7);
        $output['logo_url'] = esc_url_raw($input['logo_url'] ?? '');
        $output['theme_primary'] = sanitize_hex_color($input['theme_primary'] ?? '#0073aa');
        $output['theme_font'] = sanitize_text_field($input['theme_font'] ?? 'inherit');
        $output['button_bg'] = sanitize_hex_color($input['button_bg'] ?? '#0073aa');
        $output['button_text'] = sanitize_hex_color($input['button_text'] ?? '#ffffff');
        $output['button_hover'] = sanitize_hex_color($input['button_hover'] ?? '#005a87');
        $output['table_rows'] = intval($input['table_rows'] ?? 20);
        $output['department_head'] = sanitize_text_field($input['department_head'] ?? '');
        $output['order_numbering'] = sanitize_text_field($input['order_numbering'] ?? 'sequential');
        return $output;
    }

    public static function add_menu_pages()
    {
        add_menu_page(
            __('MySer', 'myser'),
            __('MySer', 'myser'),
            'manage_options',
            'myser-dashboard',
            [self::class, 'render_dashboard'],
            'dashicons-admin-tools',
            30
        );

        $pages = [
            'myser-dashboard' => __('Дашборд', 'myser'),
            'myser-orders' => __('Заказы', 'myser'),
            'myser-clients' => __('Клиенты', 'myser'),
            'myser-services' => __('Услуги', 'myser'),
            'myser-stock' => __('Склад', 'myser'),
            'myser-staff' => __('Сотрудники', 'myser'),
            'myser-reference' => __('Справочник', 'myser'),
            'myser-settings' => __('⚙️ Настройки', 'myser'),
            'myser-logs' => __('Логи', 'myser'),
            'myser-backups' => __('Бекапы', 'myser'),
        ];

        foreach ($pages as $slug => $title) {
            add_submenu_page(
                'myser-dashboard',
                $title,
                $title,
                'manage_options',
                $slug,
                [self::class, 'render_' . str_replace('myser-', '', $slug)]
            );
        }
    }

    public static function enqueue_scripts($hook)
    {
        if (strpos($hook, 'myser-') === false) {
            return;
        }

        if (strpos($hook, 'myser-settings') !== false) {
            wp_enqueue_media();
            wp_enqueue_script('myser-settings', MYSER_PLUGIN_URL . 'assets/admin/js/settings.js', ['myser-admin'], MYSER_VERSION, true);
        }

        wp_enqueue_script('jquery');
        wp_enqueue_style('myser-admin', MYSER_PLUGIN_URL . 'assets/admin/css/admin.css', [], MYSER_VERSION);
        wp_enqueue_script('myser-admin', MYSER_PLUGIN_URL . 'assets/admin/js/admin.js', ['jquery'], MYSER_VERSION, true);

        // Загружаем модули для конкретных страниц
        if (strpos($hook, 'myser-orders') !== false) {
            wp_enqueue_script('myser-orders', MYSER_PLUGIN_URL . 'assets/admin/js/modules/orders.js', ['myser-admin'], MYSER_VERSION, true);
            // Подключаем clients.js для функционала добавления клиента из модалки заказа
            wp_enqueue_script('myser-clients', MYSER_PLUGIN_URL . 'assets/admin/js/modules/clients.js', ['myser-admin'], MYSER_VERSION, true);
        }
        if (strpos($hook, 'myser-clients') !== false) {
            wp_enqueue_script('myser-clients', MYSER_PLUGIN_URL . 'assets/admin/js/modules/clients.js', ['myser-admin'], MYSER_VERSION, true);
        }
        if (strpos($hook, 'myser-staff') !== false) {
            wp_enqueue_script('myser-staff', MYSER_PLUGIN_URL . 'assets/admin/js/modules/staff.js', ['myser-admin'], MYSER_VERSION, true);
        }

        wp_localize_script(
            'myser-admin',
            'myser_ajax',
            [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('myser_nonce'),
                'plugin_url' => MYSER_PLUGIN_URL,
            ]
        );
    }

    public static function render_dashboard()
    {
        include MYSER_PLUGIN_DIR . 'lib/templates/dashboard.php';
    }

    public static function render_orders()
    {
        include MYSER_PLUGIN_DIR . 'lib/templates/orders.php';
    }

    public static function render_clients()
    {
        include MYSER_PLUGIN_DIR . 'lib/templates/clients.php';
    }

    public static function render_services()
    {
        include MYSER_PLUGIN_DIR . 'lib/templates/services.php';
    }

    public static function render_stock()
    {
        include MYSER_PLUGIN_DIR . 'lib/templates/stock.php';
    }

    public static function render_staff()
    {
        include MYSER_PLUGIN_DIR . 'lib/templates/staff.php';
    }

    public static function render_settings()
    {
        include MYSER_PLUGIN_DIR . 'lib/templates/settings.php';
    }

    public static function render_logs()
    {
        $logger = Logger::get();
        $settings = get_option('myser_settings', []);
        $date = isset($_GET['log_date']) ? sanitize_text_field($_GET['log_date']) : date('Y-m-d');
        $dates = $logger->get_log_dates();
        $logs = $logger->get_logs($date, 10);
        include MYSER_PLUGIN_DIR . 'lib/templates/logs.php';
    }

    public static function render_backups()
    {
        $backup = Backup::get();
        $backupFiles = $backup->list_backups();
        $settings = get_option('myser_settings', []);
        include MYSER_PLUGIN_DIR . 'lib/templates/backups.php';
    }

    public static function render_reference()
    {
        global $wpdb;
        $type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : 'devices';
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $table_name = '';

        switch ($type) {
            case 'brands':
                $table_name = $wpdb->prefix . 'myser_brands';
                break;
            case 'components':
                $table_name = $wpdb->prefix . 'myser_components';
                break;
            default:
                $type = 'devices';
                $table_name = $wpdb->prefix . 'myser_devices';
        }

        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
            $wpdb->show_errors(true);
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE $table_name (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                name varchar(255) NOT NULL,
                description text DEFAULT '',
                PRIMARY KEY  (id)
            ) $charset_collate;";
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }

        $where = '';
        if (!empty($search)) {
            $search_like = '%' . $wpdb->esc_like($search) . '%';
            $where = $wpdb->prepare(" WHERE name LIKE %s OR description LIKE %s", $search_like, $search_like);
        }

        $items = $wpdb->get_results("SELECT * FROM $table_name $where ORDER BY name ASC");

        $combinations = [];
        if ($type === 'components') {
            $combo_table = $wpdb->prefix . 'myser_component_combinations';
            if ($wpdb->get_var("SHOW TABLES LIKE '$combo_table'") === $combo_table) {
                $combinations = $wpdb->get_results("SELECT * FROM $combo_table ORDER BY name ASC");
            }
        }

        $tabs = [
            'devices' => __('Девайсы', 'myser'),
            'brands' => __('Бренды', 'myser'),
            'components' => __('Комплектующие', 'myser'),
        ];

        ?>
        <div class="wrap myser-wrap" id="myser-reference-page">
            <!-- Верхний ряд: заголовок | версия | ребут -->
            <div class="myser-page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h1 style="margin: 0;">
                    <img src="<?php echo MYSER_PLUGIN_URL; ?>assets/admin/images/icons/book.svg" class="myser-icon" alt="" style="width: 32px; height: 32px; vertical-align: middle;">
                    <?php _e('Справочник', 'myser'); ?>
                </h1>
                <div style="font-size: 0.9em; color: #0073aa; text-align: center; flex: 1;">
                    MySer v<?php echo MYSER_VERSION; ?>
                </div>
                <div style="text-align: right; min-width: 150px;">
                    <button type="button" class="button button-secondary" id="myser-reboot-btn">♻️ <?php _e('Ребут плагина', 'myser'); ?></button>
                    <span id="myser-reboot-status" style="display: block; margin-top: 4px; font-size: 12px;"></span>
                </div>
            </div>

            <nav class="nav-tab-wrapper">
                <?php foreach ($tabs as $tab_key => $tab_label) : ?>
                    <a href="?page=myser-reference&type=<?php echo esc_attr($tab_key); ?>"
                       class="nav-tab <?php echo $type === $tab_key ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html($tab_label); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <form method="get" style="margin-bottom: 20px; margin-top: 20px;">
                <input type="hidden" name="page" value="myser-reference">
                <input type="hidden" name="type" value="<?php echo esc_attr($type); ?>">
                <input type="text" name="s" placeholder="<?php esc_attr_e('Поиск...', 'myser'); ?>"
                       value="<?php echo esc_attr($search); ?>" style="width: 300px;">
                <button type="submit" class="button"><?php esc_html_e('Искать', 'myser'); ?></button>
                <a href="?page=myser-reference&type=<?php echo esc_attr($type); ?>" class="button">
                    <?php esc_html_e('Сбросить', 'myser'); ?>
                </a>
                <button type="button" class="button button-primary"
                        onclick="myser_add_reference_item('<?php echo esc_attr($type); ?>')" style="float:right;">
                    <?php esc_html_e('➕ Добавить', 'myser'); ?>
                </button>
            </form>

            <?php if (empty($items)) : ?>
                <p><?php esc_html_e('Записей не найдено.', 'myser'); ?></p>
            <?php else : ?>
                <!-- ОБЁРТКА СКРОЛЛА -->
                <div style="max-height: 420px; overflow-y: auto; border: 1px solid #ccd0d4; border-radius: 4px;">
                    <table class="wp-list-table widefat fixed striped" style="margin-bottom: 0;">
                        <thead style="position: sticky; top: 0; background: #f1f1f1; z-index: 2;">
                            <tr>
                                <th style="width: 50px;">ID</th>
                                <th><?php esc_html_e('Название', 'myser'); ?></th>
                                <?php if ($type !== 'components') : ?>
                                    <th><?php esc_html_e('Описание', 'myser'); ?></th>
                                <?php endif; ?>
                                <th style="width: 120px;"><?php esc_html_e('Действия', 'myser'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item) : ?>
                                <tr>
                                    <td><?php echo esc_html($item->id); ?></td>
                                    <td><?php echo esc_html($item->name); ?></td>
                                    <?php if ($type !== 'components') : ?>
                                        <td><?php echo esc_html($item->description ?? ''); ?></td>
                                    <?php endif; ?>
                                    <td>
                                        <button class="button button-small"
                                                onclick="myser_edit_reference_item('<?php echo esc_attr($type); ?>', <?php echo esc_attr($item->id); ?>)">
                                            ✏️
                                        </button>
                                        <button class="button button-small" style="color:red;"
                                                onclick="myser_delete_reference_item('<?php echo esc_attr($type); ?>', <?php echo esc_attr($item->id); ?>)">
                                            ✕
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <!-- /ОБЁРТКА СКРОЛЛА -->
            <?php endif; ?>

            <?php if ($type === 'components' && !empty($combinations)) : ?>
                <h2 style="margin-top: 30px;"><?php esc_html_e('Комбинации', 'myser'); ?></h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Название', 'myser'); ?></th>
                            <th><?php esc_html_e('Состав', 'myser'); ?></th>
                            <th style="width: 120px;"><?php esc_html_e('Действия', 'myser'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($combinations as $combo) : ?>
                            <tr>
                                <td><?php echo esc_html($combo->name); ?></td>
                                <td><?php echo esc_html($combo->components); ?></td>
                                <td>
                                    <button class="button button-small"
                                            onclick="myser_edit_component_combination(<?php echo esc_attr($combo->id); ?>)">
                                        ✏️
                                    </button>
                                    <button class="button button-small" style="color:red;"
                                            onclick="myser_delete_component_combination(<?php echo esc_attr($combo->id); ?>)">
                                        ✕
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Модальное окно для справочника -->
        <div id="myser-reference-modal"
             style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
            <div style="background:#fff; border-radius:8px; padding:30px; width:500px; max-width:90%; box-shadow:0 4px 20px rgba(0,0,0,0.3);">
                <h2 id="myser-reference-modal-title" style="margin:0 0 20px 0;"><?php esc_html_e('Добавить запись', 'myser'); ?></h2>

                <input type="hidden" id="myser-reference-modal-type">
                <input type="hidden" id="myser-reference-modal-id">

                <div style="margin-bottom:15px;">
                    <label for="myser-reference-modal-name" style="display:block; font-weight:bold; margin-bottom:5px;">
                        <?php esc_html_e('Название', 'myser'); ?> *
                    </label>
                    <input type="text" id="myser-reference-modal-name"
                           style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
                </div>

                <div id="myser-reference-modal-description-row" style="margin-bottom:15px;">
                    <label for="myser-reference-modal-description" style="display:block; font-weight:bold; margin-bottom:5px;">
                        <?php esc_html_e('Описание', 'myser'); ?>
                    </label>
                    <textarea id="myser-reference-modal-description" rows="3"
                              style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;"></textarea>
                </div>

                <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:20px;">
                    <button type="button" id="myser-reference-modal-cancel" class="button">
                        <?php esc_html_e('Отмена', 'myser'); ?>
                    </button>
                    <button type="button" id="myser-reference-modal-save" class="button button-primary">
                        <?php esc_html_e('Сохранить', 'myser'); ?>
                    </button>
                </div>
            </div>
        </div>

        <script>
            // Ребут плагина
            jQuery(document).ready(function($) {
                $('#myser-reboot-btn').on('click', function() {
                    myser_reboot_plugin();
                });
            });
            jQuery(document).ready(function($) {
                window.myser_add_reference_item = function(type) {
                    var modal = document.getElementById('myser-reference-modal');
                    if (!modal) return;
                    var typeLabels = {
                        'devices': 'Девайс',
                        'brands': 'Бренд',
                        'components': 'Комплектующую'
                    };
                    var label = typeLabels[type] || type;
                    document.getElementById('myser-reference-modal-title').textContent = '➕ Добавить ' + label;
                    document.getElementById('myser-reference-modal-type').value = type;
                    document.getElementById('myser-reference-modal-id').value = '';
                    document.getElementById('myser-reference-modal-name').value = '';
                    document.getElementById('myser-reference-modal-description').value = '';
                    var descRow = document.getElementById('myser-reference-modal-description-row');
                    if (descRow) {
                        descRow.style.display = (type === 'components') ? 'none' : 'block';
                    }
                    modal.style.display = 'flex';
                };

                window.myser_edit_reference_item = function(type, id) {
                    var typeLabels = {
                        'devices': 'Девайс',
                        'brands': 'Бренд',
                        'components': 'Комплектующую'
                    };
                    var label = typeLabels[type] || type;
                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: {
                            action: 'myser_get_reference_item',
                            type: type,
                            id: id,
                            nonce: '<?php echo wp_create_nonce('myser_nonce'); ?>'
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                document.getElementById('myser-reference-modal-title').textContent = '✏️ Редактировать ' + label;
                                document.getElementById('myser-reference-modal-type').value = type;
                                document.getElementById('myser-reference-modal-id').value = id;
                                document.getElementById('myser-reference-modal-name').value = response.data.name || '';
                                document.getElementById('myser-reference-modal-description').value = response.data.description || '';
                                var descRow = document.getElementById('myser-reference-modal-description-row');
                                if (descRow) {
                                    descRow.style.display = (type === 'components') ? 'none' : 'block';
                                }
                                document.getElementById('myser-reference-modal').style.display = 'flex';
                            } else {
                                alert('Ошибка загрузки: ' + (response.data.message || 'Неизвестная ошибка'));
                            }
                        },
                        error: function() {
                            alert('Ошибка соединения с сервером');
                        }
                    });
                };

                window.myser_delete_reference_item = function(type, id) {
                    if (!confirm('Удалить запись #' + id + '?')) return;
                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: {
                            action: 'myser_delete_reference_item',
                            type: type,
                            id: id,
                            nonce: '<?php echo wp_create_nonce('myser_nonce'); ?>'
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                alert('Запись удалена');
                                location.reload();
                            } else {
                                alert('Ошибка: ' + (response.data.message || 'Неизвестная ошибка'));
                            }
                        },
                        error: function() {
                            alert('Ошибка соединения с сервером');
                        }
                    });
                };

                $('#myser-reference-modal-cancel').on('click', function() {
                    $('#myser-reference-modal').hide();
                });

                $(window).on('click', function(e) {
                    if ($(e.target).is('#myser-reference-modal')) {
                        $('#myser-reference-modal').hide();
                    }
                });

                $('#myser-reference-modal-save').on('click', function() {
                    var type = $('#myser-reference-modal-type').val();
                    var id = $('#myser-reference-modal-id').val();
                    var name = $('#myser-reference-modal-name').val().trim();
                    var description = $('#myser-reference-modal-description').val().trim();

                    if (!name) {
                        alert('Название обязательно для заполнения');
                        return;
                    }

                    var data = {
                        action: 'myser_save_reference_item',
                        type: type,
                        name: name,
                        nonce: '<?php echo wp_create_nonce('myser_nonce'); ?>'
                    };

                    if (type !== 'components') {
                        data.description = description;
                    }

                    if (id) {
                        data.id = id;
                    }

                    var $btn = $(this);
                    $btn.prop('disabled', true).text('Сохранение...');

                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: data,
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                alert('Запись сохранена');
                                location.reload();
                            } else {
                                alert('Ошибка: ' + (response.data.message || 'Неизвестная ошибка'));
                                $btn.prop('disabled', false).text('Сохранить');
                            }
                        },
                        error: function() {
                            alert('Ошибка соединения с сервером');
                            $btn.prop('disabled', false).text('Сохранить');
                        }
                    });
                });

                $('#myser-reference-modal-name').on('keypress', function(e) {
                    if (e.which === 13) {
                        $('#myser-reference-modal-save').click();
                    }
                });
            });
        </script>
        <?php
    }

    public static function clear_logs()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        check_admin_referer('myser_nonce', 'nonce');
        Logger::get()->clear_logs();
        wp_redirect(add_query_arg('cleared', '1', wp_get_referer()));
        exit;
    }

    public static function download_log()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        check_admin_referer('myser_nonce', 'nonce');
        $logger = Logger::get();
        $date = isset($_GET['log_date']) ? sanitize_text_field($_GET['log_date']) : date('Y-m-d');
        $logs = $logger->get_logs($date, 1000);
        $content = implode("\n", $logs);
        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="myser-log-' . $date . '.txt"');
        echo esc_textarea($content);
        exit;
    }

    public static function save_log_settings()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        check_admin_referer('myser_save_log_settings', 'myser_log_nonce');
        $settings = get_option('myser_settings', []);
        $settings['log_level'] = sanitize_text_field($_POST['log_level'] ?? 'error');
        $settings['log_retention_days'] = intval($_POST['log_retention_days'] ?? 7);
        update_option('myser_settings', $settings);
        wp_redirect(add_query_arg('saved', '1', wp_get_referer()));
        exit;
    }

    public static function output_theme_css()
    {
        $settings = get_option('myser_settings', []);
        $primary = !empty($settings['theme_primary']) ? $settings['theme_primary'] : '#0073aa';
        $font = !empty($settings['theme_font']) ? $settings['theme_font'] : 'inherit';
        ?>
        <style>
            .myser-theme-primary { color: <?php echo esc_attr($primary); ?>; }
            .myser-theme-bg-primary { background-color: <?php echo esc_attr($primary); ?>; }
            .myser-theme-border-primary { border-color: <?php echo esc_attr($primary); ?>; }
            .wrap-myser { font-family: <?php echo esc_attr($font); ?>; }
        </style>
        <?php
    }

}
