<?php
/**
 * Шаблон страницы справочника (myser-reference)
 *
 * @package MySer
 * @var string $type        Тип справочника (devices|brands|components)
 * @var string $search      Поисковый запрос
 * @var array  $items       Список записей
 * @var array  $combinations Комбинации для компонентов
 * @var array  $tabs        Список вкладок
 */
defined('ABSPATH') || exit;
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
            <button type="button" class="button button-secondary" id="myser-reboot-btn" onclick="myser_reboot_plugin()">♻️ <?php _e('Ребут плагина', 'myser'); ?></button>
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
