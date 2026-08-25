// ========== Reference Module ==========

(function($) {
    'use strict';

    // ========== Добавление записи ==========

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

    // ========== Редактирование записи ==========

    window.myser_edit_reference_item = function(type, id) {
        var typeLabels = {
            'devices': 'Девайс',
            'brands': 'Бренд',
            'components': 'Комплектующую'
        };
        var label = typeLabels[type] || type;
        
        $.ajax({
            url: myser_ajax.ajaxurl,
            type: 'POST',
            data: {
                action: 'myser_get_reference_item',
                type: type,
                id: id,
                nonce: myser_ajax.nonce
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

    // ========== Удаление записи ==========

    window.myser_delete_reference_item = function(type, id) {
        if (!confirm('Удалить запись #' + id + '?')) return;
        
        $.ajax({
            url: myser_ajax.ajaxurl,
            type: 'POST',
            data: {
                action: 'myser_delete_reference_item',
                type: type,
                id: id,
                nonce: myser_ajax.nonce
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

    // ========== Инициализация ==========

    $(document).ready(function() {
        // Закрытие модалки по кнопке Отмена
        $('#myser-reference-modal-cancel').on('click', function() {
            $('#myser-reference-modal').hide();
        });

        // Закрытие по клику на оверлей
        $(window).on('click', function(e) {
            if ($(e.target).is('#myser-reference-modal')) {
                $('#myser-reference-modal').hide();
            }
        });

        // Сохранение записи
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
                nonce: myser_ajax.nonce
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
                url: myser_ajax.ajaxurl,
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

        // Enter в поле названия
        $('#myser-reference-modal-name').on('keypress', function(e) {
            if (e.which === 13) {
                $('#myser-reference-modal-save').click();
            }
        });
    });

})(jQuery);