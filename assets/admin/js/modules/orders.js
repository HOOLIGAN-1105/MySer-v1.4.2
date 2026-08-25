// ========== Orders Module ==========
// MySer Orders Module v1.0

jQuery(document).ready(function($) {

    let orders_current_page = 1;
    let orders_total_pages = 1;

    // ========================================
    // Загрузка списка заказов
    // ========================================
    window.myser_load_orders = function(page = 1) {
        orders_current_page = page;
        const search = document.getElementById('myser-search')?.value || '';
        const status = document.getElementById('myser-status-filter')?.value || '';
        const date_from = document.getElementById('myser-date-from')?.value || '';
        const date_to = document.getElementById('myser-date-to')?.value || '';

        $.post(myser_ajax.ajaxurl, {
            action: 'myser_get_orders',
            nonce: myser_ajax.nonce,
            page: page,
            per_page: 20,
            search: search,
            status_id: status,
            date_from: date_from,
            date_to: date_to
        }, function(response) {
            if (response.success) {
                let html = '';
                if (response.data.items.length === 0) {
                    html = '<tr><td colspan="7">Нет заказов</td></tr>';
                } else {
                    response.data.items.forEach(function(order) {
                        const statusColor = order.status_color || '#6c757d';
                        const statusName = order.status_name || '—';
                        html += '<tr>' +
                            '<td><strong>' + (order.doc_number || '—') + '</strong></td>' +
                            '<td>' + (order.doc_date || '—') + '</td>' +
                            '<td>' + (order.client_name || '—') + '</td>' +
                            '<td>' + (order.device_model || '—') + '</td>' +
                            '<td><span style="background:' + statusColor + '; padding:2px 8px; border-radius:3px; color:#fff;">' + statusName + '</span></td>' +
                            '<td>' + (order.grand_total || '0') + '</td>' +
                            '<td>' +
                                '<button class="button button-small" onclick="myser_open_order_modal(' + order.id + ')">✏️</button> ' +
                                '<button class="button button-small" onclick="myser_delete_order(' + order.id + ')" style="color:red;">❌</button>' +
                            '</td>' +
                        '</tr>';
                    });
                }
                document.getElementById('myser-orders-body').innerHTML = html;

                orders_total_pages = response.data.pages || 1;
                let pagination_html = '<span>Страница ' + orders_current_page + ' из ' + orders_total_pages + '</span>';
                for (let i = 1; i <= Math.min(orders_total_pages, 10); i++) {
                    pagination_html += '<button class="button button-small" onclick="myser_load_orders(' + i + ')" ' + (i === orders_current_page ? 'disabled' : '') + '>' + i + '</button>';
                }
                document.getElementById('myser-orders-pagination').innerHTML = pagination_html;
            } else {
                alert('Ошибка: ' + (response.data?.message || 'Неизвестная ошибка'));
            }
        }).fail(function() {
            alert('Ошибка загрузки заказов');
        });
    };

    // ========================================
    // Загрузка списка мастеров
    // ========================================
    function loadMasters() {
        const select = document.getElementById('order-master');
        if (!select) return;

        select.innerHTML = '<option value="">Загрузка мастеров...</option>';

        $.post(myser_ajax.ajaxurl, {
            action: 'myser_get_staff',
            nonce: myser_ajax.nonce,
            page: 1,
            per_page: 100
        }, function(response) {
            if (response.success && response.data.items) {
                select.innerHTML = '<option value="">— Выберите мастера —</option>';
                response.data.items.forEach(function(staff) {
                    const option = document.createElement('option');
                    option.value = staff.id;
                    option.textContent = staff.staff_name || staff.name || 'Без имени';
                    select.appendChild(option);
                });
            } else {
                select.innerHTML = '<option value="">Нет мастеров</option>';
                console.warn('Мастера не найдены');
            }
        }).fail(function() {
            select.innerHTML = '<option value="">Ошибка загрузки</option>';
            console.error('Ошибка загрузки мастеров');
        });
    }

    // ========================================
    // Открытие модалки заказа
    // ========================================
    window.myser_open_order_modal = function(id = null) {
        const overlay = document.getElementById('order-modal-overlay');
        if (!overlay) {
            console.error('Modal overlay not found');
            return;
        }
        overlay.style.display = 'flex';

        loadMasters();

        if (!id) {
            // Новый заказ
            var titleEl = document.getElementById('order-modal-title');
            if (titleEl) titleEl.textContent = '➕ Добавить заказ';
            
            var editId = document.getElementById('order-edit-id');
            if (editId) editId.value = '';
            
            // Очистка полей
            var clientId = document.getElementById('order-client-id');
            if (clientId) clientId.value = '';
            
            var client = document.getElementById('order-client');
            if (client) client.value = '';
            
            var deviceId = document.getElementById('order-device-id');
            if (deviceId) deviceId.value = '';
            
            var device = document.getElementById('order-device');
            if (device) device.value = '';
            
            var brandId = document.getElementById('order-brand-id');
            if (brandId) brandId.value = '';
            
            var brand = document.getElementById('order-brand');
            if (brand) brand.value = '';
            
            var componentsId = document.getElementById('order-components-id');
            if (componentsId) componentsId.value = '';
            
            var components = document.getElementById('order-components');
            if (components) components.value = '';
            
            var colorId = document.getElementById('order-color-id');
            if (colorId) colorId.value = '';
            
            var color = document.getElementById('order-color');
            if (color) color.value = '';
            
            var serial = document.getElementById('order-serial');
            if (serial) serial.value = '';
            
            var version = document.getElementById('order-version');
            if (version) version.value = '';
            
            var status = document.getElementById('order-status');
            if (status) status.value = 'new';
            
            var repairType = document.getElementById('order-repair-type');
            if (repairType) repairType.value = 'service';
            
            var promisedDate = document.getElementById('order-promised-date');
            if (promisedDate) promisedDate.value = '';
            
            var estimatedCost = document.getElementById('order-estimated-cost');
            if (estimatedCost) estimatedCost.value = '';
            
            var reportedDefect = document.getElementById('order-reported-defect');
            if (reportedDefect) reportedDefect.value = '';
            
            var actualDefect = document.getElementById('order-actual-defect');
            if (actualDefect) actualDefect.value = '';
            
            var saleDate = document.getElementById('order-sale-date');
            if (saleDate) saleDate.value = '';
            
            var lastRepairDate = document.getElementById('order-last-repair-date');
            if (lastRepairDate) lastRepairDate.value = '';
        } else {
            // Редактирование
            var titleEl = document.getElementById('order-modal-title');
            if (titleEl) titleEl.textContent = '✏️ Редактировать заказ';
            
            var editId = document.getElementById('order-edit-id');
            if (editId) editId.value = id;
            // Загрузка данных будет позже
        }
    };

    // ========================================
    // Закрытие модалки заказа
    // ========================================
    window.myser_close_order_modal = function() {
        const overlay = document.getElementById('order-modal-overlay');
        if (overlay) {
            overlay.style.display = 'none';
        }
    };

    // ========================================
    // Открытие субмодалки выбора клиента
    // ========================================
    window.myser_open_client_select = function() {
        const overlay = document.getElementById('client-select-overlay');
        if (!overlay) {
            console.warn('Субмодалка выбора клиента не найдена');
            alert('Функция выбора клиента будет реализована позже');
            return;
        }
        overlay.style.display = 'flex';
        const searchInput = document.getElementById('client-search-input');
        if (searchInput) searchInput.value = '';
        if (typeof myser_load_client_list === 'function') {
            myser_load_client_list();
        }
    };

    // ========================================
    // Сохранение заказа
    // ========================================
    window.myser_save_order = function() {
        // Собираем данные из формы
        var data = {
            action: 'myser_save_order',
            nonce: myser_ajax.nonce,
            id: document.getElementById('order-edit-id')?.value || '',
            client_id: document.getElementById('order-client-id')?.value || '',
            master_id: document.getElementById('order-master')?.value || '',
            device_id: document.getElementById('order-device-id')?.value || '',
            brand_id: document.getElementById('order-brand-id')?.value || '',
            components_id: document.getElementById('order-components-id')?.value || '',
            color_id: document.getElementById('order-color-id')?.value || '',
            serial: document.getElementById('order-serial')?.value || '',
            version: document.getElementById('order-version')?.value || '',
            status: document.getElementById('order-status')?.value || 'new',
            repair_type: document.getElementById('order-repair-type')?.value || 'service',
            sale_date: document.getElementById('order-sale-date')?.value || '',
            last_repair_date: document.getElementById('order-last-repair-date')?.value || '',
            promised_date: document.getElementById('order-promised-date')?.value || '',
            estimated_cost: document.getElementById('order-estimated-cost')?.value || '',
            reported_defect: document.getElementById('order-reported-defect')?.value || '',
            actual_defect: document.getElementById('order-actual-defect')?.value || ''
        };

        // Проверка обязательных полей
        if (!data.client_id) {
            alert('Пожалуйста, выберите клиента');
            return;
        }

        // Блокируем кнопку
        var saveBtn = document.querySelector('#order-modal .btn-primary');
        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.textContent = 'Сохранение...';
        }

        $.post(myser_ajax.ajaxurl, data, function(response) {
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.textContent = ' Сохранить заказ';
            }

            if (response.success) {
                if (typeof myser_show_toast === 'function') {
                    myser_show_toast('Заказ сохранён!', 'success');
                } else {
                    alert('Заказ сохранён!');
                }
                window.myser_close_order_modal();
                window.myser_load_orders(orders_current_page);
            } else {
                alert('Ошибка: ' + (response.data?.message || 'Неизвестная ошибка'));
            }
        }).fail(function() {
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.textContent = ' Сохранить заказ';
            }
            alert('Ошибка сети при сохранении заказа');
        });
    };

    // ========================================
    // Удаление заказа
    // ========================================
    window.myser_delete_order = function(id) {
        if (!confirm('Удалить заказ?')) return;

        $.post(myser_ajax.ajaxurl, {
            action: 'myser_delete_order',
            nonce: myser_ajax.nonce,
            id: id
        }, function(response) {
            if (response.success) {
                if (typeof myser_show_toast === 'function') {
                    myser_show_toast('Заказ удалён', 'success');
                }
                window.myser_load_orders(orders_current_page);
            } else {
                alert('Ошибка удаления заказа');
            }
        }).fail(function() {
            alert('Ошибка удаления заказа');
        });
    };

    // ========================================
    // Сброс фильтров
    // ========================================
    window.myser_reset_order_filters = function() {
        var search = document.getElementById('myser-search');
        if (search) search.value = '';
        var status = document.getElementById('myser-status-filter');
        if (status) status.value = '';
        var dateFrom = document.getElementById('myser-date-from');
        if (dateFrom) dateFrom.value = '';
        var dateTo = document.getElementById('myser-date-to');
        if (dateTo) dateTo.value = '';
        window.myser_load_orders(1);
    };

    // ========================================
    // Инициализация
    // ========================================
    // Закрытие модалки по клику на оверлей
    const overlay = document.getElementById('order-modal-overlay');
    if (overlay) {
        overlay.addEventListener('click', function(e) {
            if (e.target === this) {
                window.myser_close_order_modal();
            }
        });
    }

    // Применение фильтров
    const applyBtn = document.getElementById('myser-apply-filters');
    if (applyBtn) {
        applyBtn.addEventListener('click', function() {
            window.myser_load_orders(1);
        });
    }

    // Поиск по Enter
    const searchInput = document.getElementById('myser-search');
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                window.myser_load_orders(1);
            }
        });
    }

    // Загрузка данных
    window.myser_load_orders(1);

});