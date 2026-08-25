<?php
/**
 * Шаблон страницы "Заказы"
 */
if (!defined('ABSPATH')) {
    exit;
}

// Определяем переменную для AJAX
if (!isset($myser_ajax)) {
    $myser_ajax = array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('myser_nonce')
    );
}

// Подключаем субмодалку выбора клиента
$client_select_modal = MYSER_PLUGIN_DIR . 'lib/templates/client-select-modal.php';
if (file_exists($client_select_modal)) {
    include $client_select_modal;
}

// Подключаем модалку клиентов
$client_modal = MYSER_PLUGIN_DIR . 'lib/templates/client-modal.php';
if (file_exists($client_modal)) {
    include $client_modal;
}

// Подключаем модалку выбора из справочника
$reference_modal = MYSER_PLUGIN_DIR . 'lib/templates/reference-select-modal.php';
if (file_exists($reference_modal)) {
    include $reference_modal;
}
?>
<script>
    var myser_ajax = <?php echo json_encode($myser_ajax); ?>;
</script>
<div class="wrap myser-admin-wrap">
    <div class="myser-page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
        <h1 style="margin: 0;">
            <img src="<?php echo MYSER_PLUGIN_URL; ?>assets/admin/images/icons/orders.svg" class="myser-icon" alt="">
            <?php _e('Заказы', 'myser'); ?>
        </h1>
        <div style="font-size: 0.9em; color: #0073aa; text-align: center; flex: 1;">
            MySer v<?php echo MYSER_VERSION; ?>
        </div>
        <div style="text-align: right; min-width: 150px;">
            <button class="button button-secondary" id="myser-reboot-btn" onclick="myser_reboot_plugin()">♻️ Ребут плагина</button>
            <span id="myser-reboot-status" style="display: block; margin-top: 4px; font-size: 12px;"></span>
        </div>
    </div>
    
    <div class="myser-filter-row" style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px; flex-wrap: wrap;">
        <button class="button button-primary" onclick="myser_open_order_modal()">+ <?php _e('Добавить заказ', 'myser'); ?></button>
        <input type="text" id="myser-search" placeholder="<?php _e('Поиск по номеру, клиенту, модели...', 'myser'); ?>" style="flex:1; min-width:200px;">
        <select id="myser-status-filter">
            <option value=""><?php _e('Все статусы', 'myser'); ?></option>
            <?php
            global $wpdb;
            $statuses = $wpdb->get_results("SELECT id, name, color FROM {$wpdb->prefix}myser_statuses ORDER BY sort_order");
            foreach ($statuses as $s) {
                echo '<option value="'.esc_attr($s->id).'">'.esc_html($s->name).'</option>';
            }
            ?>
        </select>
        <label style="font-weight:bold;color:#e326b4;">С даты:</label> <input type="date" id="myser-date-from">
        <label style="font-weight:bold;color:#e326b4;">По дату:</label> <input type="date" id="myser-date-to">
        <button class="button" id="myser-apply-filters"><?php _e('Применить', 'myser'); ?></button>
        <button class="button" onclick="myser_reset_order_filters()"><?php _e('Сбросить', 'myser'); ?></button>
    </div>
    
    <div class="myser-table-wrap" id="myser-orders-table-wrap">
        <table id="myser-orders-table">
            <thead>
                <tr>
                    <th>№ заказа</th>
                    <th>Дата</th>
                    <th>Клиент</th>
                    <th>Устройство</th>
                    <th>Статус</th>
                    <th>Сумма</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody id="myser-orders-body">
                <tr><td colspan="7">Загрузка...</td></tr>
            </tbody>
        </table>
        <div id="myser-orders-pagination" style="margin-top:10px;"></div>
    </div>
</div>

<!-- ============================================================ -->
<!-- СТИЛЬНАЯ МОДАЛКА ЗАКАЗА -->
<!-- ============================================================ -->
<style>
    #order-modal {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }
    #order-modal .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
        margin-bottom: 16px;
    }
    #order-modal .form-grid .full-width {
        grid-column: 1 / -1;
    }
    #order-modal .form-group {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    #order-modal .form-group label {
        font-size: 13px;
        font-weight: 600;
        color: #1e1e2f;
        letter-spacing: 0.3px;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    #order-modal .form-group label .badge {
        font-size: 10px;
        font-weight: 400;
        background: #eef2f7;
        color: #5a6b7c;
        padding: 1px 8px;
        border-radius: 12px;
    }
    #order-modal .form-control {
        width: 100%;
        padding: 10px 12px;
        border: 1.5px solid #e2e8f0;
        border-radius: 8px;
        font-size: 14px;
        transition: border-color 0.2s, box-shadow 0.2s;
        background: #fafbfc;
        color: #1a1a2e;
        box-sizing: border-box;
    }
    #order-modal .form-control:focus {
        border-color: #6c5ce7;
        box-shadow: 0 0 0 3px rgba(108, 92, 231, 0.15);
        outline: none;
        background: #fff;
    }
    #order-modal .form-control:disabled,
    #order-modal .form-control[readonly] {
        background: #f1f3f5;
        color: #6b7a8a;
        cursor: not-allowed;
    }
    #order-modal select.form-control {
        appearance: auto;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%236b7a8a' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 12px center;
        padding-right: 36px;
    }
    #order-modal textarea.form-control {
        resize: vertical;
        min-height: 56px;
        font-family: inherit;
    }
    #order-modal .btn-group {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        margin-top: 24px;
        padding-top: 20px;
        border-top: 1px solid #edf2f7;
    }
    #order-modal .btn {
        padding: 10px 24px;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 500;
        border: none;
        cursor: pointer;
        transition: all 0.2s;
    }
    #order-modal .btn-secondary {
        background: #f1f3f5;
        color: #2d3748;
    }
    #order-modal .btn-secondary:hover {
        background: #e2e8f0;
    }
    #order-modal .btn-primary {
        background: #6c5ce7;
        color: #fff;
        box-shadow: 0 2px 8px rgba(108, 92, 231, 0.3);
    }
    #order-modal .btn-primary:hover {
        background: #5a4bd1;
        box-shadow: 0 4px 12px rgba(108, 92, 231, 0.4);
        transform: translateY(-1px);
    }
    #order-modal .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 16px;
        border-bottom: 2px solid #f0f2f5;
    }
    #order-modal .modal-header h2 {
        margin: 0;
        font-size: 20px;
        font-weight: 700;
        color: #1a1a2e;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    #order-modal .modal-header h2 .emoji {
        font-size: 24px;
    }
    #order-modal .modal-close {
        cursor: pointer;
        font-size: 28px;
        line-height: 1;
        color: #8a9aa8;
        transition: color 0.2s, transform 0.2s;
        background: none;
        border: none;
        padding: 0 4px;
    }
    #order-modal .modal-close:hover {
        color: #e53e3e;
        transform: rotate(90deg);
    }
    #order-modal .section-title {
        font-size: 13px;
        font-weight: 600;
        color: #6b7a8a;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        margin: 16px 0 12px 0;
        padding-bottom: 6px;
        border-bottom: 1px dashed #e2e8f0;
    }
    @media (max-width: 640px) {
        #order-modal .form-grid {
            grid-template-columns: 1fr;
        }
        #order-modal .form-grid .full-width {
            grid-column: 1;
        }
        #order-modal {
            padding: 16px;
        }
        #order-modal .btn-group {
            flex-direction: column;
        }
        #order-modal .btn-group .btn {
            width: 100%;
            text-align: center;
        }
    }
</style>

<!-- ============================================================ -->
<!-- МОДАЛКА -->
<!-- ============================================================ -->
<div id="order-modal-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.45); backdrop-filter:blur(4px); z-index:99999; justify-content:center; align-items:center; animation:fadeIn 0.25s ease;">
    <div id="order-modal" style="background:#ffffff; border-radius:16px; padding:28px 32px; width:780px; max-width:96%; max-height:92vh; overflow-y:auto; box-shadow:0 20px 60px rgba(0,0,0,0.25);">
        
        <!-- HEADER -->
        <div class="modal-header">
            <h2><span class="emoji"></span> <span id="order-modal-title">Добавить заказ</span></h2>
            <button class="modal-close" onclick="myser_close_order_modal()">✕</button>
        </div>

        <!-- HIDDEN -->
        <input type="hidden" id="order-edit-id" value="">

        <!-- ========================================================== -->
        <!-- ПОЛЯ -->
        <!-- ========================================================== -->

        <!-- Строка 1: Клиент + Мастер -->
        <div class="form-grid">
            <div class="form-group">
                <label> Клиент <span class="badge">обязательно</span></label>
                <div style="display:flex; gap:6px;">
                    <input type="text" id="order-client" class="form-control" placeholder="Выберите клиента..." readonly ondblclick="myser_open_client_select()">
                    <input type="hidden" id="order-client-id" value="">
                    <button type="button" class="btn btn-secondary" onclick="myser_open_client_select()" style="padding:8px 14px; flex-shrink:0;"> Выбрать</button>
                </div>
            </div>
            <div class="form-group">
                <label> Мастер <span class="badge">назначение</span></label>
                <select id="order-master" class="form-control">
                    <option value="">— Выберите мастера —</option>
                </select>
            </div>
        </div>

        <!-- Строка 2: Устройство + Бренд -->
        <div class="form-grid">
            <div class="form-group">
                <label> Устройство</label>
                <div style="display:flex; gap:6px;">
                    <input type="text" id="order-device" class="form-control" placeholder="Выберите устройство..." readonly ondblclick="myser_open_reference_select('devices', function(id, name) { document.getElementById('order-device-id').value = id; document.getElementById('order-device').value = name; })">
                    <input type="hidden" id="order-device-id" value="">
                    <button type="button" class="btn btn-secondary" onclick="myser_open_reference_select('devices', function(id, name) { document.getElementById('order-device-id').value = id; document.getElementById('order-device').value = name; })" style="padding:8px 14px; flex-shrink:0;"> Выбрать</button>
                </div>
            </div>
            <div class="form-group">
                <label>️ Бренд</label>
                <div style="display:flex; gap:6px;">
                    <input type="text" id="order-brand" class="form-control" placeholder="Выберите бренд..." readonly ondblclick="myser_open_reference_select('brands', function(id, name) { document.getElementById('order-brand-id').value = id; document.getElementById('order-brand').value = name; })">
                    <input type="hidden" id="order-brand-id" value="">
                    <button type="button" class="btn btn-secondary" onclick="myser_open_reference_select('brands', function(id, name) { document.getElementById('order-brand-id').value = id; document.getElementById('order-brand').value = name; })" style="padding:8px 14px; flex-shrink:0;"> Выбрать</button>
                </div>
            </div>
        </div>
        <!-- Строка 2.5: Комплектация + Цвет -->
        <div class="form-grid" style="grid-template-columns: 1fr 1fr;">
            <div class="form-group">
                <label> Комплектация</label>
                <div style="display:flex; gap:6px;">
                    <input type="text" id="order-components" class="form-control" placeholder="Выберите комплектацию..." readonly ondblclick="myser_open_reference_select('components', function(id, name) { document.getElementById('order-components-id').value = id; document.getElementById('order-components').value = name; })">
                    <input type="hidden" id="order-components-id" value="">
                    <button type="button" class="btn btn-secondary" onclick="myser_open_reference_select('components', function(id, name) { document.getElementById('order-components-id').value = id; document.getElementById('order-components').value = name; })" style="padding:8px 14px; flex-shrink:0;"> Выбрать</button>
                </div>
            </div>
            <div class="form-group">
                <label> Цвет</label>
                <div style="display:flex; gap:6px;">
                    <input type="text" id="order-color" class="form-control" placeholder="Выберите цвет..." readonly ondblclick="myser_open_reference_select('colors', function(id, name) { document.getElementById('order-color-id').value = id; document.getElementById('order-color').value = name; })">
                    <input type="hidden" id="order-color-id" value="">
                    <button type="button" class="btn btn-secondary" onclick="myser_open_reference_select('colors', function(id, name) { document.getElementById('order-color-id').value = id; document.getElementById('order-color').value = name; })" style="padding:8px 14px; flex-shrink:0;"> Выбрать</button>
                </div>
            </div>
        </div>
        <div class="form-grid" style="grid-template-columns: 1fr 1fr;">
            <div class="form-group">
                <label> Серийный номер</label>
                <input type="text" id="order-serial" class="form-control" placeholder="SN / IMEI / S/N...">
            </div>
            <div class="form-group">
                <label> Версия / Код</label>
                <input type="text" id="order-version" class="form-control" placeholder="Версия прошивки, код модели...">
            </div>
        </div>

        <!-- Строка 3: Статус ремонта + Тип ремонта -->
        <div class="form-grid">
            <div class="form-group">
                <label> Статус ремонта</label>
                <select id="order-status" class="form-control">
                    <option value="new"> Новый</option>
                    <option value="in_progress"> В работе</option>
                    <option value="awaiting_parts"> Ожидает запчасти</option>
                    <option value="ready"> Готов</option>
                    <option value="issued">✅ Выдан</option>
                    <option value="cancelled"> Отменён</option>
                </select>
            </div>
            <div class="form-group">
                <label>️ Тип ремонта</label>
                <select id="order-repair-type" class="form-control">
                    <option value="service">Сервисный</option>
                    <option value="pretrade">Предторговый</option>
                    <option value="warranty">Гарантийный</option>
                    <option value="repeat">Повторный</option>
                    <option value="paid">Платный</option>
                    <option value="paid_diagnostic">Платная диагностика</option>
                </select>
            </div>
        </div>

        <!-- Строка 4: Дата продажи (при гарантийном) + Дата крайнего ремонта (при повторном) -->
        <div class="form-grid">
            <div class="form-group" id="order-sale-date-group" style="display:none;">
                <label> Дата продажи <span class="badge">для гарантии</span></label>
                <input type="date" id="order-sale-date" class="form-control">
            </div>
            <div class="form-group" id="order-last-repair-date-group" style="display:none;">
                <label> Дата крайнего ремонта <span class="badge">повторный</span></label>
                <input type="date" id="order-last-repair-date" class="form-control">
            </div>
        </div>

        <!-- Строка 5: Обещанный срок + Предварительная стоимость -->
        <div class="form-grid">
            <div class="form-group">
                <label>⏳ Обещанный срок</label>
                <input type="date" id="order-promised-date" class="form-control">
            </div>
            <div class="form-group">
                <label> Предварительная стоимость</label>
                <input type="number" id="order-estimated-cost" class="form-control" placeholder="0.00" step="0.01">
            </div>
        </div>

        <!-- Строка 6: Заявленная неисправность (полная ширина) -->
        <div class="form-grid">
            <div class="form-group full-width">
                <label> Заявленная неисправность</label>
                <textarea id="order-reported-defect" class="form-control" placeholder="Что клиент сказал? Подробно..." rows="2"></textarea>
            </div>
        </div>

        <!-- Строка 7: Фактическая неисправность (полная ширина, только для мастеров) -->
        <div class="form-grid">
            <div class="form-group full-width">
                <label> Фактическая неисправность <span class="badge">только для мастеров</span></label>
                <textarea id="order-actual-defect" class="form-control" placeholder="Что нашли по факту? Скрыто от клиента..." rows="2"></textarea>
            </div>
        </div>

        <!-- ========================================================== -->
        <!-- КНОПКИ -->
        <!-- ========================================================== -->
        <div class="btn-group">
            <button class="btn btn-secondary" onclick="myser_close_order_modal()">Отмена</button>
            <button class="btn btn-primary" onclick="myser_save_order()"> Сохранить заказ</button>
        </div>

    </div>
</div>

<script>
    // ================================================================
    // Логика показа/скрытия полей в зависимости от типа ремонта
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        const repairType = document.getElementById('order-repair-type');
        const saleGroup = document.getElementById('order-sale-date-group');
        const lastRepairGroup = document.getElementById('order-last-repair-date-group');

        function toggleRepairFields() {
            const val = repairType.value;
            saleGroup.style.display = (val === 'warranty') ? 'block' : 'none';
            lastRepairGroup.style.display = (val === 'repeat') ? 'block' : 'none';
        }

        if (repairType) {
            repairType.addEventListener('change', toggleRepairFields);
            toggleRepairFields(); // initial
        }

        // Заглушки для клиентских функций (будут переопределены в orders.js)
        window.myser_open_client_select = function() {
            alert('Выбор клиента будет реализован позже');
        };
        window.myser_add_client_from_order = function() {
            alert('Добавление клиента будет реализовано позже');
        };
    });
</script>

<style>
    @keyframes fadeIn {
        from { opacity: 0; transform: scale(0.97); }
        to { opacity: 1; transform: scale(1); }
    }
</style>
