<?php
/**
 * Шаблон страницы клиентов
 *
 * @package MySer
 */

defined('ABSPATH') || exit;
$settings = get_option('myser_settings', []);
$nonce    = wp_create_nonce('myser_nonce');
?>
<div class="wrap myser-wrap" id="myser-clients-page">
    <!-- Верхний ряд: заголовок | версия | ребут -->
    <div class="myser-page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
        <h1 style="margin: 0;">
            <img src="<?php echo MYSER_PLUGIN_URL; ?>assets/admin/images/icons/clients.svg" class="myser-icon" alt="">
            <?php _e('Клиенты', 'myser'); ?>
        </h1>
        <div style="font-size: 0.9em; color: #0073aa; text-align: center; flex: 1;">
            MySer v<?php echo MYSER_VERSION; ?>
        </div>
        <div style="text-align: right; min-width: 150px;">
            <button type="button" class="button button-secondary" id="myser-reboot-btn" onclick="myser_reboot_plugin()">♻️ <?php _e('Ребут плагина', 'myser'); ?></button>
            <span id="myser-reboot-status" style="display: block; margin-top: 4px; font-size: 12px;"></span>
        </div>
    </div>

    <!-- Нижний ряд: кнопка Добавить клиента + поиск -->
    <div class="myser-toolbar" style="display: flex; gap: 10px; align-items: center; margin-bottom: 15px; flex-wrap: wrap;">
        <button type="button" class="button button-primary" id="add-client-btn">+ <?php _e('Добавить клиента', 'myser'); ?></button>
        <div class="myser-search-form" style="display: flex; gap: 5px; flex: 1;">
            <input type="text" id="clients-search" placeholder="<?php _e('Поиск по имени, телефону, email...', 'myser'); ?>" style="flex: 1;">
            <button class="button" onclick="myser_load_clients(1)"><?php _e('Найти', 'myser'); ?></button>
            <button class="button" onclick="document.getElementById('clients-search').value=''; myser_load_clients(1);"><?php _e('Сбросить', 'myser'); ?></button>
        </div>
        <div class="myser-pagination-info" id="clients-total-info">
            <?php _e('Всего: 0', 'myser'); ?>
        </div>
    </div>

    <div class="myser-table-container">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th><?php _e('Фамилия', 'myser'); ?></th>
                    <th><?php _e('Имя', 'myser'); ?></th>
                    <th><?php _e('Телефон', 'myser'); ?></th>
                    <th><?php _e('Доп.телефон', 'myser'); ?></th>
                    <th><?php _e('Статус', 'myser'); ?></th>
                    <th><?php _e('Скидка, %', 'myser'); ?></th>
                    <th><?php _e('Заказы', 'myser'); ?></th>
                    <th><?php _e('Адекватность', 'myser'); ?></th>
                    <th><?php _e('Действия', 'myser'); ?></th>
                </tr>
            </thead>
            <tbody id="clients-tbody">
                <tr><td colspan="10"><?php _e('Загрузка...', 'myser'); ?></td></tr>
            </tbody>
        </table>
        <div class="tablenav">
            <div class="tablenav-pages" id="clients-pagination"></div>
        </div>
    </div>
</div>

<!-- Модальное окно клиента (подключается из отдельного файла) -->
<?php include MYSER_PLUGIN_DIR . 'lib/templates/client-modal.php'; ?>

<style>
/* Стили для хедера */
.myser-page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}
.myser-page-header h1 {
    display: flex;
    align-items: center;
    gap: 10px;
}
.myser-version {
    font-size: 14px;
    color: #666;
    font-weight: normal;
    background: #f0f0f0;
    padding: 2px 10px;
    border-radius: 12px;
    margin-left: 10px;
}
.myser-header-actions {
    display: flex;
    gap: 10px;
    align-items: center;
}
.myser-icon {
    width: 24px;
    height: 24px;
}

/* Toast-уведомления */
#myser-toast-container {
	position: fixed;
	top: 32px;
	right: 20px;
	z-index: 999999;
	display: flex;
	flex-direction: column;
	gap: 10px;
}
.myser-toast {
	color: #fff;
	padding: 12px 24px;
	border-radius: 4px;
	font-size: 14px;
	box-shadow: 0 2px 8px rgba(0,0,0,0.2);
	animation: myserToastIn 0.3s ease-out;
	min-width: 250px;
	text-align: center;
}
.myser-toast.success { background: #46b450; }
.myser-toast.error { background: #dc3232; }
@keyframes myserToastIn {
	from { opacity: 0; transform: translateX(100px); }
	to { opacity: 1; transform: translateX(0); }
}
@keyframes myserToastOut {
	from { opacity: 1; transform: translateX(0); }
	to { opacity: 0; transform: translateX(100px); }
}
</style>

<script>
let clients_current_page = 1;
let clients_total_pages = 1;

// Toast-уведомление
function showMyserToast(message, type) {
	type = type || 'success';
	var container = document.getElementById('myser-toast-container');
	if (!container) {
		container = document.createElement('div');
		container.id = 'myser-toast-container';
		document.body.appendChild(container);
	}
	var toast = document.createElement('div');
	toast.className = 'myser-toast ' + type;
	toast.textContent = message;
	container.appendChild(toast);
	setTimeout(function() {
		toast.style.animation = 'myserToastOut 0.3s ease-out forwards';
		setTimeout(function() { toast.remove(); }, 300);
	}, 3000);
}

// Переключение полей физлицо/юрлицо
function myser_toggle_client_fields() {
    const type = document.getElementById('client-type').value;
    document.getElementById('client-person-name').style.display = type === 'person' ? 'grid' : 'none';
    document.getElementById('client-company-name').style.display = type === 'company' ? 'grid' : 'none';
}

// Загрузка списка
function myser_load_clients(page = 1) {
    console.log('myser_load_clients called, page:', page);
    clients_current_page = page;
    const search = document.getElementById('clients-search').value;
    jQuery.ajax({
        url: myser_ajax.ajaxurl,
        type: 'POST',
        data: {
            action: 'myser_get_clients',
            nonce: myser_ajax.nonce,
            page: page,
            per_page: 20,
            search: search
        },
        dataType: 'json',
        cache: false
    }).done(function(response) {
        console.log('myser_load_clients response:', response);
        console.log('response.data:', response.data);
        console.log('response.data.items:', response.data.items);
        if (response.success) {
            let html = '';
            if (!response.data.items || response.data.items.length === 0) {
                html = '<tr><td colspan="10"><?php _e('Нет клиентов', 'myser'); ?></td></tr>';
            } else {
                response.data.items.forEach(function(c) {
                    // Определяем статус и цвет
                    let statusText, statusColor;
                    if (c.status === 'permanent') {
                        statusText = '<?php _e('Постоянный', 'myser'); ?>';
                        statusColor = '#FF8C00'; // оранжевый
                    } else if (c.status === 'regular') {
                        statusText = '<?php _e('Регулярный', 'myser'); ?>';
                        statusColor = '#20B2AA'; // бирюзовый
                    } else {
                        statusText = '<?php _e('Новый', 'myser'); ?>';
                        statusColor = '#4169E1'; // синий
                    }
                    const adequacyLabel = c.is_problem_client == 1 ? '⚠️ <?php _e('Проблемный', 'myser'); ?>' : '✅ <?php _e('Адекватный', 'myser'); ?>';
                    html += `<tr>
                        <td>${c.id}</td>
                        <td>${c.last_name || '—'}</td>
                        <td>${c.first_name || '—'}</td>
                        <td>${c.phone || '—'}</td>
                        <td>${c.other_phone || '—'}</td>
                        <td><span style="color: ${statusColor}; font-weight: bold;">${statusText}</span></td>
                        <td>${c.service_discount_percent || 0}%</td>
                        <td>${c.order_count || 0}</td>
                        <td>${adequacyLabel}</td>
                        <td>
                            <button class="button button-small" onclick="myser_open_client_modal(${c.id})">✏️</button>
                            <button class="button button-small" onclick="myser_delete_client(${c.id})" style="color:red;">❌</button>
                        </td>
                    </tr>`;
                });
            }
            document.getElementById('clients-tbody').innerHTML = html;

            clients_total_pages = response.data.pages || 1;
            document.getElementById('clients-total-info').innerHTML = '<?php _e('Всего', 'myser'); ?>: ' + (response.data.total || 0);
            
            let pagination_html = `<span><?php _e('Страница', 'myser'); ?> ${clients_current_page} <?php _e('из', 'myser'); ?> ${clients_total_pages}</span>`;
            for (let i = 1; i <= Math.min(clients_total_pages, 10); i++) {
                pagination_html += `<button class="button button-small" onclick="myser_load_clients(${i})" ${i === clients_current_page ? 'disabled' : ''}>${i}</button>`;
            }
            document.getElementById('clients-pagination').innerHTML = pagination_html;
        } else {
            alert('<?php _e('Ошибка', 'myser'); ?>: ' + (response.data?.message || '<?php _e('Неизвестная ошибка', 'myser'); ?>'));
        }
    });
}

// ========== Модальное окно ==========
function myser_open_client_modal(id = null) {
    document.getElementById('clients-search').value = '';
    document.getElementById('client-modal-overlay').style.display = 'flex';
    if (id) {
        document.getElementById('client-modal-title').textContent = '✏️ <?php _e('Редактировать клиента', 'myser'); ?>';
        document.getElementById('client-edit-id').value = id;
        jQuery.ajax({
            url: myser_ajax.ajaxurl,
            type: 'POST',
            data: {
                action: 'myser_get_client',
                nonce: myser_ajax.nonce,
                client_id: id
            },
            dataType: 'json',
            cache: false
        }).done(function(response) {
            if (response.success) {
                const c = response.data;
                document.getElementById('client-type').value = c.client_type || 'person';
                document.getElementById('client-last-name').value = c.last_name || '';
                document.getElementById('client-first-name').value = c.first_name || '';
                document.getElementById('client-middle-name').value = c.middle_name || '';
                document.getElementById('client-company').value = c.company_name || '';
                document.getElementById('client-legal-form').value = c.legal_form || '';
                document.getElementById('client-phone').value = c.phone || '';
                document.getElementById('client-other-phone').value = c.other_phone || '';
                document.getElementById('client-email').value = c.email || '';
                document.getElementById('client-city').value = c.city || '';
                document.getElementById('client-street').value = c.street || '';
                document.getElementById('client-house').value = c.house || '';
                document.getElementById('client-problem').checked = c.is_problem_client == 1;
                document.getElementById('client-discount').value = c.service_discount_percent || 0;
                document.getElementById('client-notes').value = c.notes || '';
                myser_toggle_client_fields();
            }
        });
    } else {
        document.getElementById('client-modal-title').textContent = '+ <?php _e('Добавить клиента', 'myser'); ?>';
        document.getElementById('client-edit-id').value = '';
        document.getElementById('client-type').value = 'person';
        document.getElementById('client-last-name').value = '';
        document.getElementById('client-first-name').value = '';
        document.getElementById('client-middle-name').value = '';
        document.getElementById('client-company').value = '';
        document.getElementById('client-legal-form').value = '';
        document.getElementById('client-phone').value = '';
        document.getElementById('client-other-phone').value = '';
        document.getElementById('client-email').value = '';
        document.getElementById('client-city').value = '';
        document.getElementById('client-street').value = '';
        document.getElementById('client-house').value = '';
        document.getElementById('client-problem').checked = false;
        document.getElementById('client-discount').value = 0;
        document.getElementById('client-notes').value = '';
        myser_toggle_client_fields();
    }
}

function myser_close_client_modal() {
    document.getElementById('client-modal-overlay').style.display = 'none';
}

// ========== Сохранение ==========
function myser_save_client_from_modal() {
    const id = document.getElementById('client-edit-id').value;
    const client_type = document.getElementById('client-type').value;
    const first_name = document.getElementById('client-first-name').value.trim();
    const last_name = document.getElementById('client-last-name').value.trim();
    const middle_name = document.getElementById('client-middle-name').value.trim();
    const company_name = document.getElementById('client-company').value.trim();
    const legal_form = document.getElementById('client-legal-form').value;
    const phone = document.getElementById('client-phone').value.trim();
    const other_phone = document.getElementById('client-other-phone').value.trim();
    const email = document.getElementById('client-email').value.trim();
    const city = document.getElementById('client-city').value.trim();
    const street = document.getElementById('client-street').value.trim();
    const house = document.getElementById('client-house').value.trim();
    const is_problem = document.getElementById('client-problem').checked ? 1 : 0;
    const discount = parseFloat(document.getElementById('client-discount').value) || 0;
    const notes = document.getElementById('client-notes').value.trim();

    if (!first_name && client_type === 'person') {
        alert('<?php _e('Имя обязательно для заполнения', 'myser'); ?>');
        return;
    }
    if (!company_name && client_type === 'company') {
        alert('<?php _e('Название компании обязательно для заполнения', 'myser'); ?>');
        return;
    }

    const data = {
        action: 'myser_save_client',
        nonce: myser_ajax.nonce,
        client_type: client_type,
        first_name: first_name,
        last_name: last_name,
        middle_name: middle_name,
        company_name: company_name,
        legal_form: legal_form,
        phone: phone,
        other_phone: other_phone,
        email: email,
        city: city,
        street: street,
        house: house,
        is_problem_client: is_problem,
        service_discount_percent: discount,
        notes: notes
    };
    if (id) data.id = id;

    jQuery.ajax({
        url: myser_ajax.ajaxurl,
        type: 'POST',
        data: data,
        dataType: 'json',
        cache: false
    }).done(function(response) {
        if (response.success) {
            showMyserToast(response.data.message || '<?php _e('Клиент сохранён', 'myser'); ?>', 'success');
            myser_close_client_modal();
            // Сбрасываем поиск и перезагружаем первую страницу
            document.getElementById('clients-search').value = '';
            myser_load_clients(1);
        } else {
            alert('<?php _e('Ошибка', 'myser'); ?>: ' + (response.data?.message || '<?php _e('Неизвестная ошибка', 'myser'); ?>'));
        }
    }).fail(function() {
        alert('<?php _e('Ошибка соединения с сервером', 'myser'); ?>');
    });
}

// ========== Удаление ==========
function myser_delete_client(id) {
    if (!confirm('<?php _e('Удалить клиента?', 'myser'); ?>')) return;
    jQuery.ajax({
        url: myser_ajax.ajaxurl,
        type: 'POST',
        data: {
            action: 'myser_delete_client',
            nonce: myser_ajax.nonce,
            client_id: id
        },
        dataType: 'json',
        cache: false
    }).done(function(response) {
        if (response.success) {
            showMyserToast(response.data.message || '<?php _e('Клиент удалён', 'myser'); ?>', 'success');
            myser_load_clients(clients_current_page);
        } else {
            alert('<?php _e('Ошибка', 'myser'); ?>: ' + (response.data?.message || '<?php _e('Неизвестная ошибка', 'myser'); ?>'));
        }
    });
}

// Инициализация
jQuery(document).ready(function() {
    myser_load_clients(1);
    
    // Кнопка "Добавить"
    document.getElementById('add-client-btn').addEventListener('click', function() {
        myser_open_client_modal();
    });

    // Закрытие по клику на оверлей
    document.getElementById('client-modal-overlay').addEventListener('click', function(e) {
        if (e.target === this) myser_close_client_modal();
    });

    // Поиск по Enter
    document.getElementById('clients-search').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            myser_load_clients(1);
        }
    });
});
</script>
