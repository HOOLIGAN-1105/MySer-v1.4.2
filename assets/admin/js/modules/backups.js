// ========== Backups Module ==========

(function($) {
    'use strict';

    // Проверка наличия ajaxurl
    if (typeof ajaxurl === 'undefined') {
        var ajaxurl = myser_ajax ? myser_ajax.ajaxurl : '';
    }

    // ========== Выбор всех ==========

    $(document).on('change', '#myser-backup-select-all', function() {
        $('.myser-backup-checkbox').prop('checked', $(this).prop('checked'));
    });

    // ========== Массовое удаление ==========

    $(document).on('click', '#myser-backup-delete-selected', function() {
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
            _ajax_nonce: myser_ajax ? myser_ajax.nonce : '',
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

    // ========== Импорт ==========

    $(document).on('change', '#myser-import-file', function() {
        var form = $(this).closest('form');
        var formData = new FormData(form[0]);
        var nonce = myser_ajax ? myser_ajax.nonce : '';
        formData.append('_ajax_nonce', nonce);
        
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

    // ========== Экспорт бекапа ==========

    window.myser_export_backup = function(format) {
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
        
        $.post(ajaxurl, {
            action: 'myser_export_backup',
            format: format,
            _ajax_nonce: myser_ajax ? myser_ajax.nonce : ''
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
    };

    // ========== Удаление бекапа ==========

    window.myser_delete_backup = function(filename) {
        if (!filename) {
            alert('❌ Имя файла не указано');
            return;
        }
        
        console.log('Удаление бекапа:', filename);
        console.log('ajaxurl:', ajaxurl);
        
        $.post(ajaxurl, {
            action: 'myser_delete_backup',
            _ajax_nonce: myser_ajax ? myser_ajax.nonce : '',
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
    };

})(jQuery);