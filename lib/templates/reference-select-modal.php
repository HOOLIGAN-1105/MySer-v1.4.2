<?php
/**
 * Универсальная модалка выбора из справочника
 * Аналог client-select-modal.php
 */
if (!defined('ABSPATH')) {
    exit;
}
?>

<!-- Модалка выбора из справочника -->
<div id="reference-select-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:100010; justify-content:center; align-items:center;">
    <div style="background:#ffffff; border-radius:14px; padding:24px 28px; width:600px; max-width:94%; max-height:80vh; display:flex; flex-direction:column; box-shadow:0 16px 48px rgba(0,0,0,0.2);">

        <!-- HEADER -->
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; padding-bottom:12px; border-bottom:2px solid #f0f2f5; flex-shrink:0;">
            <h3 id="reference-select-title" style="margin:0; font-size:18px; font-weight:700; color:#1a1a2e;">Выбор из справочника</h3>
            <button onclick="myser_close_reference_select()" style="cursor:pointer; font-size:24px; line-height:1; color:#8a9aa8; background:none; border:none; padding:0 4px; transition:color 0.2s, transform 0.2s;">✕</button>
        </div>

        <!-- ПОИСК -->
        <div style="display:flex; gap:8px; margin-bottom:14px; flex-shrink:0;">
            <input type="text" id="reference-search-input" class="form-control" placeholder="Поиск..." style="flex:1;" onkeyup="myser_reference_search()">
            <button type="button" class="btn btn-secondary" id="reference-add-btn" onclick="myser_reference_add_item()" style="padding:8px 16px; flex-shrink:0;">➕ Добавить</button>
        </div>

        <!-- СПИСОК -->
        <div id="reference-list" style="flex:1; overflow-y:auto; min-height:200px; max-height:400px; border:1px solid #e2e8f0; border-radius:8px; padding:4px;">
            <div style="padding:20px; text-align:center; color:#999;">Загрузка...</div>
        </div>

        <!-- ПАГИНАЦИЯ -->
        <div id="reference-pagination" style="display:flex; justify-content:space-between; align-items:center; margin-top:15px; padding-top:10px; border-top:1px solid #eee; flex-shrink:0;">
            <button class="button" onclick="myser_reference_prev_page()">← Назад</button>
            <span id="reference-page-info">Страница 1</span>
            <button class="button" onclick="myser_reference_next_page()">Вперёд →</button>
        </div>

        <!-- КНОПКИ ДЕЙСТВИЙ (только для множественного выбора) -->
        <div id="reference-actions" style="display:none; justify-content:flex-end; gap:10px; margin-top:15px; padding-top:10px; border-top:1px solid #eee; flex-shrink:0;">
            <button class="button button-primary" id="reference-save-btn" onclick="myser_reference_save()" style="background:#0073aa; color:#fff; border:none; padding:6px 20px; border-radius:4px; cursor:pointer;">Сохранить</button>
        </div>
    </div>
</div>

<script>
(function() {
    'use strict';

    // Состояние
    var state = {
        type: '',
        search: '',
        page: 1,
        total_pages: 1,
        items: [],
        callback: null,
        title: ''
    };

    // КЕШ СПРАВОЧНИКОВ
    var referenceCache = {};

    // Флаг блокировки повторных запросов
    var isLoading = false;

    // Открыть модалку
    window.myser_open_reference_select = function(type, callback, title) {
        console.log('myser_open_reference_select вызвана, type:', type);

        var modal = document.getElementById('reference-select-modal');
        if (!modal) {
            console.error('Модалка справочника не найдена');
            return;
        }

        // Если уже открыта такая же модалка с тем же типом - не перезагружаем
        if (state.type === type && modal.style.display === 'flex') {
            return;
        }

        state.type = type;
        state.callback = callback || function(id, name) {};
        state.title = title || getTypeTitle(type);
        state.search = '';
        state.page = 1;
        state.selectedIds = [];

        document.getElementById('reference-select-title').textContent = 'Выбор: ' + state.title;
        document.getElementById('reference-search-input').value = '';

        // Для множественного выбора показываем подсказку и кнопки
        var actionsDiv = document.getElementById('reference-actions');
        if (type === 'components') {
            // Удаляем старую подсказку, если есть
            var oldHint = document.getElementById('reference-multi-hint');
            if (oldHint) oldHint.remove();
            
            var hint = document.createElement('div');
            hint.id = 'reference-multi-hint';
            hint.style.cssText = 'font-size:12px; color:#666; margin-bottom:8px; padding:4px 8px; background:#f0f8ff; border-radius:4px;';
            hint.textContent = ' Для множественного выбора зажмите Ctrl и кликайте по элементам';
            var header = document.querySelector('#reference-select-modal .reference-modal-header');
            if (header) {
                header.after(hint);
            }
            if (actionsDiv) {
                actionsDiv.style.display = 'flex';
            }
        } else {
            // Для других типов скрываем кнопки
            var oldHint = document.getElementById('reference-multi-hint');
            if (oldHint) oldHint.remove();
            if (actionsDiv) {
                actionsDiv.style.display = 'none';
            }
        }

        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';

        loadReferenceItems();
    };

    // Закрыть модалку
    window.myser_close_reference_select = function() {
        var modal = document.getElementById('reference-select-modal');
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }
    };

    // Поиск
    window.myser_reference_search = function() {
        state.search = document.getElementById('reference-search-input').value.trim();
        state.page = 1;
        loadReferenceItems();
    };

    // Загрузить элементы
    function loadReferenceItems() {
        var list = document.getElementById('reference-list');
        
        // Проверяем кеш
        var cacheKey = state.type + '_' + state.search + '_' + state.page;
        if (referenceCache[cacheKey]) {
            renderReferenceItems(referenceCache[cacheKey]);
            return;
        }

        // Если уже идет загрузка - не делаем новый запрос
        if (isLoading) {
            console.log('Загрузка уже выполняется, пропускаем');
            return;
        }

        isLoading = true;
        list.innerHTML = '<div style="padding:20px; text-align:center; color:#999;">Загрузка...</div>';

        var data = {
            action: 'myser_get_reference_items',
            nonce: myser_ajax ? myser_ajax.nonce : '',
            type: state.type,
            search: state.search,
            page: state.page
        };

        if (typeof jQuery !== 'undefined') {
            jQuery.post(myser_ajax.ajaxurl, data, function(response) {
                isLoading = false;
                console.log('AJAX response:', response);
                if (response.success) {
                    // Сохраняем в кеш
                    referenceCache[cacheKey] = response.data;
                    renderReferenceItems(response.data);
                } else {
                    console.log('Response error:', response);
                    list.innerHTML = '<div style="padding:20px; text-align:center; color:#999;">Ошибка загрузки: ' + (response.data?.message || '') + '</div>';
                }
            }).fail(function(jqXHR, textStatus, errorThrown) {
                isLoading = false;
                console.log('AJAX error:', textStatus, errorThrown);
                list.innerHTML = '<div style="padding:20px; text-align:center; color:#999;">Ошибка сети</div>';
            });
        } else {
            fetch(myser_ajax.ajaxurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams(data)
            })
            .then(response => response.json())
            .then(response => {
                isLoading = false;
                if (response.success) {
                    referenceCache[cacheKey] = response.data;
                    renderReferenceItems(response.data);
                } else {
                    list.innerHTML = '<div style="padding:20px; text-align:center; color:#999;">Ошибка загрузки</div>';
                }
            })
            .catch(function() {
                isLoading = false;
                list.innerHTML = '<div style="padding:20px; text-align:center; color:#999;">Ошибка сети</div>';
            });
        }
    }

    // Отобразить элементы
    function renderReferenceItems(data) {
        var list = document.getElementById('reference-list');
        list.innerHTML = '';

        if (!data.items || data.items.length === 0) {
            list.innerHTML = '<div style="padding:20px; text-align:center; color:#999;">Ничего не найдено</div>';
            return;
        }

        state.items = data.items;
        state.total_pages = data.pages || 1;

        data.items.forEach(function(item) {
            var div = document.createElement('div');
            div.className = 'reference-item';
            div.style.cssText = 'padding:10px 15px; cursor:pointer; border-bottom:1px solid #f0f0f0; transition:background 0.2s; display:flex; justify-content:space-between; align-items:center;';
            div.dataset.id = item.id;

            // Для множественного выбора подсвечиваем выбранные
            var isSelected = state.type === 'components' && state.selectedIds && state.selectedIds.indexOf(item.id) !== -1;
            if (isSelected) {
                div.style.background = '#e3f2fd';
                div.style.borderLeft = '3px solid #1976d2';
            }

            var nameSpan = document.createElement('div');
            nameSpan.className = 'reference-name';

            // Для множественного выбора добавляем только текст (без чекбокса)
            var textSpan = document.createElement('span');
            textSpan.textContent = item.name;
            nameSpan.appendChild(textSpan);

            // Для цветов добавляем превью
            if (state.type === 'colors' && item.color_code) {
                var colorPreview = document.createElement('span');
                colorPreview.style.cssText = 'display:inline-block; width:20px; height:20px; border-radius:4px; border:1px solid #ddd; margin-right:10px; vertical-align:middle; background-color:' + item.color_code + ';';
                nameSpan.prepend(colorPreview);
            }

            var actionsDiv = document.createElement('div');

            if (state.type === 'components') {
                // Для множественного выбора
                var selectBtn = document.createElement('button');
                selectBtn.className = 'btn-select';
                selectBtn.textContent = isSelected ? 'Убрать' : 'Выбрать';
                selectBtn.style.cssText = 'padding:4px 12px; background:' + (isSelected ? '#dc3545' : '#0073aa') + '; color:#fff; border:none; border-radius:4px; cursor:pointer; font-size:13px;';
                selectBtn.onclick = function(e) {
                    e.stopPropagation();
                    toggleSelection(item.id);
                };
                actionsDiv.appendChild(selectBtn);

                // Клик на строке для множественного выбора
                div.onclick = function(e) {
                    if (e.ctrlKey || e.metaKey) {
                        toggleSelection(item.id);
                    }
                };
            } else {
                // Обычный выбор для других типов
                var selectBtn = document.createElement('button');
                selectBtn.className = 'btn-select';
                selectBtn.textContent = 'Выбрать';
                selectBtn.style.cssText = 'padding:4px 12px; background:#0073aa; color:#fff; border:none; border-radius:4px; cursor:pointer; font-size:13px;';
                selectBtn.onclick = function(e) {
                    e.stopPropagation();
                    if (typeof state.callback === 'function') {
                        state.callback(item.id, item.name);
                    }
                    myser_close_reference_select();
                };
                actionsDiv.appendChild(selectBtn);

                // Двойной клик для обычного выбора
                div.ondblclick = function() {
                    if (typeof state.callback === 'function') {
                        state.callback(item.id, item.name);
                    }
                    myser_close_reference_select();
                };
            }

            div.appendChild(nameSpan);
            div.appendChild(actionsDiv);

            div.onmouseenter = function() {
                if (!this.style.background || this.style.background === 'transparent' || this.style.background === '') {
                    this.style.background = '#f5f5f5';
                }
            };
            div.onmouseleave = function() {
                if (this.style.background === '#f5f5f5') {
                    this.style.background = 'transparent';
                }
            };

            list.appendChild(div);
        });

        // Функция переключения выбора для множественного режима
        function toggleSelection(id) {
            if (!state.selectedIds) {
                state.selectedIds = [];
            }
            var index = state.selectedIds.indexOf(id);
            if (index !== -1) {
                state.selectedIds.splice(index, 1);
            } else {
                state.selectedIds.push(id);
            }
            // Обновляем список
            loadReferenceItems();
            // Обновляем поле ввода с выбранными значениями
            updateSelectedDisplay();
        }

        // Обновление отображения выбранных значений
        function updateSelectedDisplay() {
            if (state.type !== 'components' || !state.callback) return;

            var selectedNames = [];
            var selectedIds = state.selectedIds || [];

            // Находим имена выбранных элементов
            state.items.forEach(function(item) {
                if (selectedIds.indexOf(item.id) !== -1) {
                    selectedNames.push(item.name);
                }
            });

            // Вызываем callback с массивом ID и строкой имён
            if (typeof state.callback === 'function') {
                state.callback(selectedIds, selectedNames.join(', '));
            }
        }

        // Сохраняем функции в глобальную область для доступа извне
        window.myser_update_selected_display = updateSelectedDisplay;
        window.myser_toggle_selection = toggleSelection;

        // Обновляем пагинацию
        var pageInfo = document.getElementById('reference-page-info');
        pageInfo.textContent = 'Страница ' + state.page + ' из ' + state.total_pages;

        var prevBtn = document.querySelector('#reference-pagination .button:first-child');
        var nextBtn = document.querySelector('#reference-pagination .button:last-child');
        if (prevBtn) prevBtn.style.display = state.page <= 1 ? 'none' : 'inline-block';
        if (nextBtn) nextBtn.style.display = state.page >= state.total_pages ? 'none' : 'inline-block';
    }

    // Пагинация
    window.myser_reference_prev_page = function() {
        if (state.page > 1) {
            state.page--;
            loadReferenceItems();
        }
    };

    window.myser_reference_next_page = function() {
        if (state.page < state.total_pages) {
            state.page++;
            loadReferenceItems();
        }
    };

    // Добавить новый элемент
    window.myser_reference_add_item = function() {
        var name = prompt('Введите название для ' + state.title + ':');
        if (!name || name.trim() === '') return;

        var data = {
            action: 'myser_save_reference',
            nonce: myser_ajax ? myser_ajax.nonce : '',
            type: state.type,
            name: name.trim()
        };

        // Для цветов запрашиваем hex
        if (state.type === 'colors') {
            var hex = prompt('Введите HEX-код цвета (например, #FF0000):');
            if (hex) data.hex_code = hex.trim();
        }

        if (typeof jQuery !== 'undefined') {
            jQuery.post(myser_ajax.ajaxurl, data, function(response) {
                if (response.success) {
                    // Очищаем кеш для этого типа
                    for (var key in referenceCache) {
                        if (key.startsWith(state.type + '_')) {
                            delete referenceCache[key];
                        }
                    }
                    if (typeof myser_show_toast === 'function') {
                        myser_show_toast(state.title + ' добавлен!', 'success');
                    }
                    loadReferenceItems();
                } else {
                    alert('Ошибка: ' + (response.data.message || 'Неизвестная ошибка'));
                }
            });
        }
    };

    // Вспомогательная функция для заголовка
    function getTypeTitle(type) {
        var titles = {
            'devices': 'Устройство',
            'brands': 'Бренд',
            'components': 'Комплектация',
            'colors': 'Цвет'
        };
        return titles[type] || type;
    }

    // Закрытие по клику на фон
    document.addEventListener('click', function(e) {
        var modal = document.getElementById('reference-select-modal');
        if (e.target === modal) {
            myser_close_reference_select();
        }
    });

    // Закрытие по ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            var modal = document.getElementById('reference-select-modal');
            if (modal && modal.style.display === 'flex') {
                myser_close_reference_select();
            }
        }
    });

    // Сохранение выбранных элементов
    window.myser_reference_save = function() {
        // Если тип 'components' и есть выбранные элементы
        if (state.type === 'components') {
            // Вызываем функцию обновления через глобальную ссылку
            if (typeof window.myser_update_selected_display === 'function') {
                window.myser_update_selected_display();
            }
            // Закрываем модалку после сохранения
            myser_close_reference_select();
        } else {
            // Для других типов просто закрываем
            myser_close_reference_select();
        }
    };

    // Закрытие по Enter
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            var modal = document.getElementById('reference-select-modal');
            if (modal && modal.style.display === 'flex') {
                e.preventDefault();
                // Проверяем, не введён ли текст в поле поиска
                var searchInput = document.getElementById('reference-search-input');
                if (document.activeElement === searchInput) {
                    // Если фокус на поле поиска - выполняем поиск
                    myser_reference_search();
                } else {
                    // Иначе сохраняем
                    myser_reference_save();
                }
            }
        }
    });

})();
</script>