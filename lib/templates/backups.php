<?php
defined('ABSPATH') || exit;

$nonce = wp_create_nonce('myser_nonce');
$backup = \MySer\Backup::get();
$backupFiles = $backup->list_backups();
$tables = \MySer\Database::get_tables();
?>
<div class="wrap">
    <div class="myser-page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
        <h1 style="margin: 0;">
            <img src="<?php echo MYSER_PLUGIN_URL; ?>assets/admin/images/icons/backup.svg" class="myser-icon" alt=""> 
            Бекапы MySer
        </h1>
        <div style="font-size: 0.9em; color: #0073aa; text-align: center; flex: 1;">
            MySer v<?php echo MYSER_VERSION; ?>
        </div>
        <div style="text-align: right; min-width: 150px;">
            <button class="button button-secondary" id="myser-reboot-btn" onclick="myser_reboot_plugin()">♻️ Ребут плагина</button>
            <span id="myser-reboot-status" style="display: block; margin-top: 4px; font-size: 12px;"></span>
        </div>
    </div>

    <!-- Статистика -->
    <div style="background: #f0f8ff; padding: 15px; border: 1px solid #b8d4e8; border-radius: 4px; margin-bottom: 20px;">
        <p style="margin: 0;">
            <strong>Статистика:</strong>
            Всего таблиц: <?php echo count($tables); ?> |
            Бекапов: <?php echo count($backupFiles); ?>
        </p>
    </div>

    <!-- Работа с данными -->
    <div class="myser-actions-row" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center; margin: 20px 0; padding: 15px; background: #f5f5f5; border-radius: 4px;">
        <span style="font-weight: bold;">Работа с данными:</span>
        
        <!-- Экспорт SQL -->
        <button class="button" onclick="myser_export_backup('sql')"> SQL-дамп</button>
        
        <!-- Экспорт CSV -->
        <button class="button" onclick="myser_export_backup('csv')"> CSV (ZIP)</button>
        
        <!-- Импорт -->
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" enctype="multipart/form-data" style="display: inline;">
            <?php wp_nonce_field('myser_import_backup', 'myser_import_nonce'); ?>
            <input type="hidden" name="action" value="myser_import_backup">
            <input type="file" name="backup_file" accept=".sql,.zip,.mdb" style="display: none;" id="myser-import-file">
            <button type="button" class="button" onclick="document.getElementById('myser-import-file').click();"> Импорт из файла</button>
        </form>
    </div>

    <!-- Список бекапов -->
    <div class="myser-backups-list">
        <?php if (empty($backupFiles)) : ?>
            <p style="color: #888;">Нет созданных бекапов.</p>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 30px;"><input type="checkbox" id="myser-backup-select-all"></th>
                        <th>Имя файла</th>
                        <th style="width: 120px;">Размер</th>
                        <th style="width: 160px;">Дата</th>
                        <th style="width: 120px;">Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($backupFiles as $file) : 
                        // Определяем иконку по расширению
                        $ext = pathinfo($file->name, PATHINFO_EXTENSION);
                        $icon = '';
                        if ($ext === 'zip') $icon = '';
                        elseif ($ext === 'mdb') $icon = '️';
                    ?>
                        <tr>
                            <td><input type="checkbox" class="myser-backup-checkbox" value="<?php echo esc_attr($file->name); ?>"></td>
                            <td><?php echo $icon; ?> <?php echo esc_html($file->name); ?></td>
                            <td><?php echo size_format($file->size, 2); ?></td>
                            <td><?php echo esc_html($file->date); ?></td>
                            <td>
                                <a href="<?php echo admin_url('admin-ajax.php?action=myser_download_backup&file=' . urlencode($file->name) . '&nonce=' . wp_create_nonce('myser_download_backup')); ?>" 
                                   class="button button-small">⬇ Скачать</a>
                                <button class="button button-small button-danger" 
                                        onclick="if(confirm('Удалить бекап?')){myser_delete_backup('<?php echo esc_attr($file->name); ?>')}">
                                    ✕
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div style="margin-top: 10px; display: flex; gap: 10px; align-items: center;">
        <button class="button button-danger" id="myser-backup-delete-selected">️ Удалить выбранные</button>
    </div>
</div>

<script>
// Проверка наличия ajaxurl
if (typeof ajaxurl === 'undefined') {
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
}

jQuery(document).ready(function($) {
    // Выбор всех
    $('#myser-backup-select-all').on('change', function() {
        $('.myser-backup-checkbox').prop('checked', $(this).prop('checked'));
    });

    // Массовое удаление
    $('#myser-backup-delete-selected').on('click', function() {
        var selected = [];
        $('.myser-backup-checkbox:checked').each(function() {
            selected.push($(this).val());
        });
        if (selected.length === 0) {
            alert('Выберите файлы для удаления');
            return;
        }
        if (!confirm('Удалить выбранные бекапы (' + selected.length + ' шт.)?')) {
            return;
        }
        $.post(ajaxurl, {
            action: 'myser_delete_backups',
            _ajax_nonce: '<?php echo $nonce; ?>',
            filenames: selected
        }, function(response) {
            if (response.success) {
                alert(response.data.message);
                location.reload();
            } else {
                alert('❌ ' + (response.data.message || 'Ошибка удаления'));
            }
        }).fail(function(xhr) {
            console.error('Ошибка удаления:', xhr);
            alert('❌ Ошибка сети при удалении');
        });
    });

    // Обработка импорта
    $('#myser-import-file').on('change', function() {
        var form = $(this).closest('form');
        var formData = new FormData(form[0]);
        formData.append('_ajax_nonce', '<?php echo $nonce; ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    alert('✅ ' + response.data.message);
                    location.reload();
                } else {
                    alert('❌ ' + (response.data.message || 'Ошибка импорта'));
                }
            },
            error: function(xhr) {
                console.error('Ошибка импорта:', xhr);
                alert('❌ Ошибка сети при импорте');
            }
        });
    });
});

// Экспорт бекапа
function myser_export_backup(format) {
    if (!format) {
        alert('❌ Не указан формат экспорта');
        return;
    }
    
    var btn = event && event.target ? event.target : document.querySelector('[onclick*="myser_export_backup(\'' + format + '\')"]');
    var originalText = btn ? btn.textContent : '';
    
    if (btn) {
        btn.textContent = '⏳ ...';
        btn.disabled = true;
    }
    
    console.log('Экспорт бекапа. Формат:', format);
    console.log('ajaxurl:', ajaxurl);
    
    jQuery.post(ajaxurl, {
        action: 'myser_export_backup',
        format: format,
        _ajax_nonce: '<?php echo $nonce; ?>'
    }, function(response) {
        console.log('Ответ сервера:', response);
        
        if (btn) {
            btn.textContent = originalText || (format === 'sql' ? ' SQL-дамп' : ' CSV (ZIP)');
            btn.disabled = false;
        }
        
        if (response.success) {
            alert('✅ ' + response.data.message);
            if (response.data.download_url) {
                setTimeout(function() {
                    window.location.href = response.data.download_url;
                }, 1000);
            }
            setTimeout(function() {
                location.reload();
            }, 3000);
        } else {
            alert('❌ ' + (response.data.message || 'Ошибка создания бекапа'));
        }
    }).fail(function(xhr) {
        console.error('Ошибка AJAX:', xhr);
        console.error('Статус:', xhr.status);
        console.error('Ответ:', xhr.responseText);
        
        if (btn) {
            btn.textContent = originalText || (format === 'sql' ? ' SQL-дамп' : ' CSV (ZIP)');
            btn.disabled = false;
        }
        alert('❌ Ошибка сети. Подробности в консоли (F12)');
    });
}

// Удаление бекапа
function myser_delete_backup(filename) {
    if (!filename) {
        alert('❌ Имя файла не указано');
        return;
    }
    
    console.log('Удаление бекапа:', filename);
    console.log('ajaxurl:', ajaxurl);
    
    jQuery.post(ajaxurl, {
        action: 'myser_delete_backup',
        _ajax_nonce: '<?php echo $nonce; ?>',
        filename: filename
    }, function(response) {
        console.log('Ответ сервера:', response);
        
        if (response.success) {
            alert('✅ ' + response.data.message);
            location.reload();
        } else {
            alert('❌ ' + (response.data.message || 'Ошибка удаления'));
        }
    }).fail(function(xhr) {
        console.error('Ошибка AJAX:', xhr);
        console.error('Статус:', xhr.status);
        console.error('Ответ:', xhr.responseText);
        alert('❌ Ошибка сети. Подробности в консоли (F12)');
    });
}
</script>

<style>
.myser-actions-row .button {
    margin: 0;
}
.myser-actions-row form {
    margin: 0;
}
.button-danger {
    background: #dc3232 !important;
    border-color: #dc3232 !important;
    color: #fff !important;
}
.button-danger:hover {
    background: #c62828 !important;
    border-color: #c62828 !important;
}
</style>
