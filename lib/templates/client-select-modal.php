<!-- ============================================================ -->
<!-- СУБМОДАЛКА: ВЫБОР КЛИЕНТА -->
<!-- ============================================================ -->
<div id="client-select-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.35); backdrop-filter:blur(3px); z-index:100000; justify-content:center; align-items:center; animation:fadeIn 0.2s ease;">
    <div id="client-select-modal" style="background:#ffffff; border-radius:14px; padding:24px 28px; width:600px; max-width:94%; max-height:80vh; display:flex; flex-direction:column; box-shadow:0 16px 48px rgba(0,0,0,0.2);">
        
        <!-- HEADER -->
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; padding-bottom:12px; border-bottom:2px solid #f0f2f5; flex-shrink:0;">
            <h3 style="margin:0; font-size:18px; font-weight:700; color:#1a1a2e;"> Выберите клиента</h3>
            <button onclick="myser_close_client_select()" style="cursor:pointer; font-size:24px; line-height:1; color:#8a9aa8; background:none; border:none; padding:0 4px; transition:color 0.2s, transform 0.2s;">✕</button>
        </div>
        
        <!-- ПОИСК -->
        <div style="display:flex; gap:8px; margin-bottom:14px; flex-shrink:0;">
            <input type="text" id="client-search-input" class="form-control" placeholder="Поиск по имени, телефону, email..." style="flex:1;" onkeyup="myser_filter_client_list()">
            <button type="button" class="btn btn-secondary" id="add-client-in-select-modal" onclick="myser_add_client_from_select_modal()" style="padding:8px 16px; flex-shrink:0;">➕ Добавить</button>
        </div>
        
        <!-- СПИСОК КЛИЕНТОВ -->
        <div id="client-select-list" style="flex:1; overflow-y:auto; min-height:200px; max-height:400px; border:1px solid #e2e8f0; border-radius:8px; padding:4px;">
            <div style="text-align:center; color:#8a9aa8; padding:30px 0;">Загрузка клиентов...</div>
        </div>
        
        <!-- FOOTER -->
        <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:14px; padding-top:12px; border-top:1px solid #f0f2f5; flex-shrink:0;">
            <button type="button" class="btn btn-secondary" onclick="myser_close_client_select()">Отмена</button>
        </div>
    </div>
</div>

<style>
#client-select-list .client-item {
    display:flex;
    justify-content:space-between;
    align-items:center;
    padding:10px 14px;
    margin:2px 0;
    border-radius:6px;
    cursor:pointer;
    transition:background 0.15s;
    border-bottom:1px solid #f5f7fa;
}
#client-select-list .client-item:hover {
    background:#f0f4ff;
}
#client-select-list .client-item:last-child {
    border-bottom:none;
}
#client-select-list .client-item .client-name {
    font-weight:600;
    color:#1a1a2e;
}
#client-select-list .client-item .client-details {
    font-size:13px;
    color:#6b7a8a;
}
#client-select-list .client-item .client-actions {
    display:flex;
    gap:6px;
}
#client-select-list .client-item .btn-select {
    padding:4px 14px;
    font-size:13px;
    background:#6c5ce7;
    color:#fff;
    border:none;
    border-radius:4px;
    cursor:pointer;
    transition:background 0.15s;
}
#client-select-list .client-item .btn-select:hover {
    background:#5a4bd1;
}
#client-select-list .client-item .btn-select.selected {
    background:#38a169;
    cursor:default;
}
#client-select-list .client-item .btn-select.selected:hover {
    background:#2f855a;
}
</style>

<script>
// Глобальные переменные
var clientSelectData = [];
var clientSelectSelectedId = null;

// Открыть субмодалку выбора клиента
function myser_open_client_select() {
    var overlay = document.getElementById('client-select-overlay');
    if (!overlay) {
        console.error('client-select-overlay not found');
        return;
    }
    overlay.style.display = 'flex';
    document.getElementById('client-search-input').value = '';
    myser_load_client_list();
}

// Закрыть субмодалку
function myser_close_client_select() {
    var overlay = document.getElementById('client-select-overlay');
    if (overlay) overlay.style.display = 'none';
}

// Загрузить список клиентов
function myser_load_client_list() {
    var list = document.getElementById('client-select-list');
    if (!list) return;
    list.innerHTML = '<div style="text-align:center; color:#8a9aa8; padding:30px 0;">Загрузка...</div>';

    jQuery.ajax({
        url: myser_ajax.ajaxurl,
        type: 'POST',
        data: {
            action: 'myser_get_clients',
            nonce: myser_ajax.nonce
        },
        dataType: 'json',
        success: function(response) {
            console.log('Response from myser_get_clients:', response);
            if (response.success) {
                // Проверяем структуру данных
                var data = response.data;
                // Если data - объект с полем items, берем items
                if (data && data.items && Array.isArray(data.items)) {
                    clientSelectData = data.items;
                } else if (data && Array.isArray(data)) {
                    clientSelectData = data;
                } else {
                    clientSelectData = [];
                    console.warn('Неожиданный формат данных:', data);
                }
                // Получить текущий выбранный ID
                var currentId = document.getElementById('order-client-id')?.value;
                clientSelectSelectedId = currentId || null;
                myser_render_client_list();
            } else {
                list.innerHTML = '<div style="text-align:center; color:#e53e3e; padding:30px 0;">Ошибка загрузки клиентов: ' + (response.data?.message || '') + '</div>';
            }
        },
        error: function(xhr, status, error) {
            list.innerHTML = '<div style="text-align:center; color:#e53e3e; padding:30px 0;">Ошибка сети: ' + error + '</div>';
            console.error('Ошибка загрузки клиентов:', error);
        }
    });
}

// Отфильтровать список
function myser_filter_client_list() {
    myser_render_client_list();
}

// Отрисовать список клиентов
function myser_render_client_list() {
    var list = document.getElementById('client-select-list');
    if (!list) return;

    var search = document.getElementById('client-search-input')?.value?.toLowerCase() || '';
    var filtered = clientSelectData.filter(function(client) {
        // Собираем полное имя для поиска
        var fullName = getClientFullName(client).toLowerCase();
        var phone = (client.phone || client.phone_number || '').toLowerCase();
        var email = (client.email || '').toLowerCase();
        return fullName.includes(search) || phone.includes(search) || email.includes(search);
    });

    if (filtered.length === 0) {
        list.innerHTML = '<div style="text-align:center; color:#8a9aa8; padding:30px 0;">Ничего не найдено</div>';
        return;
    }

    var html = '';
    filtered.forEach(function(client) {
        var isSelected = (client.id == clientSelectSelectedId);
        var clientName = getClientFullName(client);
        var details = [];
        if (client.phone || client.phone_number) {
            details.push(' ' + (client.phone || client.phone_number));
        }
        if (client.email) details.push(' ✉' + client.email);
        var detailsStr = details.join(' · ');

        html += '<div class="client-item" data-id="' + client.id + '">';
        html += '    <div>';
        html += '        <div class="client-name">' + escapeHtml(clientName) + '</div>';
        if (detailsStr) {
            html += '        <div class="client-details">' + escapeHtml(detailsStr) + '</div>';
        }
        html += '    </div>';
        html += '    <div class="client-actions">';
        if (isSelected) {
            html += '        <button class="btn-select selected" disabled>✓ Выбран</button>';
        } else {
            html += '        <button class="btn-select" onclick="myser_select_client(' + client.id + ')">Выбрать</button>';
        }
        html += '    </div>';
        html += '</div>';
    });
    list.innerHTML = html;
}

// Вспомогательная функция для получения полного имени клиента
function getClientFullName(client) {
    var parts = [];
    if (client.last_name) parts.push(client.last_name);
    if (client.first_name) parts.push(client.first_name);
    if (client.middle_name) parts.push(client.middle_name);

    if (parts.length > 0) {
        return parts.join(' ');
    }

    // Если нет полей с именем, пробуем другие варианты
    return client.name || client.client_name || client.full_name || client.title || 'Без имени';
}

// Выбрать клиента
function myser_select_client(clientId) {
    var client = clientSelectData.find(function(c) { return c.id == clientId; });
    if (!client) return;

    // Получаем полное имя клиента
    var clientName = getClientFullName(client);

    // Обновить основное поле
    document.getElementById('order-client').value = clientName;
    document.getElementById('order-client-id').value = client.id;
    clientSelectSelectedId = client.id;

    // Перерисовать список
    myser_render_client_list();

    // Закрыть субмодалку
    myser_close_client_select();
}

// Вспомогательная функция для экранирования HTML
function escapeHtml(text) {
    if (!text) return '';
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Обновить список после добавления клиента (вызывается из clients.js)
function myser_refresh_client_list() {
    myser_load_client_list();
}
</script>
