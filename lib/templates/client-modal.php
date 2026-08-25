<!-- Модальное окно добавления/редактирования клиента -->
<div id="client-modal-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:100000; justify-content:center; align-items:center;">
    <div style="background:#fff; border-radius:8px; padding:25px; width:650px; max-width:90%; max-height:90vh; overflow-y:auto; box-shadow:0 4px 20px rgba(0,0,0,0.3);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3 id="client-modal-title" style="margin:0;">➕ Добавить клиента</h3>
            <span onclick="document.getElementById('client-modal-overlay').style.display='none'" style="cursor:pointer; font-size:24px; line-height:1;">&times;</span>
        </div>
        <input type="hidden" id="client-edit-id">

        <!-- Основное: тип клиента -->
        <div style="margin-bottom:15px;">
            <label style="display:block; margin-bottom:5px; font-weight:600;">Тип клиента</label>
            <select id="client-type" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;" onchange="myser_toggle_client_fields()">
                <option value="person">Физическое лицо</option>
                <option value="company">Юридическое лицо</option>
            </select>
        </div>

        <!-- Общие поля -->
        <fieldset style="border:1px solid #e0e0e0; border-radius:4px; padding:15px; margin-bottom:15px;">
            <legend style="font-weight:600; padding:0 10px;">Основная информация</legend>
            
            <!-- Физлицо: ФИО -->
            <div id="client-person-name" style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:15px;">
                <div>
                    <label style="display:block; margin-bottom:5px; font-weight:600;">Фамилия</label>
                    <input type="text" id="client-last-name" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
                </div>
                <div>
                    <label style="display:block; margin-bottom:5px; font-weight:600;">Имя *</label>
                    <input type="text" id="client-first-name" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;" required>
                </div>
                <div>
                    <label style="display:block; margin-bottom:5px; font-weight:600;">Отчество</label>
                    <input type="text" id="client-middle-name" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
                </div>
            </div>

            <!-- Юрлицо: реквизиты -->
            <div id="client-company-name" style="display:none; grid-template-columns:1fr 1fr; gap:15px; margin-top:15px;">
                <div>
                    <label style="display:block; margin-bottom:5px; font-weight:600;">Название компании *</label>
                    <input type="text" id="client-company" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
                </div>
                <div>
                    <label style="display:block; margin-bottom:5px; font-weight:600;">Форма собственности</label>
                    <select id="client-legal-form" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
                        <option value="">Не указано</option>
                        <option value="ooo">ООО</option>
                        <option value="ip">ИП</option>
                        <option value="zao">ЗАО</option>
                        <option value="oao">ОАО</option>
                        <option value="other">Другое</option>
                    </select>
                </div>
            </div>

            <!-- Контакты -->
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-top:15px;">
                <div>
                    <label style="display:block; margin-bottom:5px; font-weight:600;">Телефон</label>
                    <input type="text" id="client-phone" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;" placeholder="+7 (999) 123-45-67">
                </div>
                <div>
                    <label style="display:block; margin-bottom:5px; font-weight:600;">Доп. телефон</label>
                    <input type="text" id="client-other-phone" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;" placeholder="+7 (999) 123-45-67">
                </div>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-top:15px;">
                <div>
                    <label style="display:block; margin-bottom:5px; font-weight:600;">Email</label>
                    <input type="email" id="client-email" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;" placeholder="client@example.com">
                </div>
                <div>
                    <label style="display:block; margin-bottom:5px; font-weight:600;">&nbsp;</label>
                    <label style="display:flex; align-items:center; gap:8px; padding-top:6px;">
                        <input type="checkbox" id="client-problem" value="1">
                        <span>⚠️ Проблемный клиент</span>
                    </label>
                </div>
            </div>

            <!-- Адрес -->
            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:15px; margin-top:15px;">
                <div>
                    <label style="display:block; margin-bottom:5px; font-weight:600;">Город</label>
                    <input type="text" id="client-city" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
                </div>
                <div>
                    <label style="display:block; margin-bottom:5px; font-weight:600;">Улица</label>
                    <input type="text" id="client-street" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
                </div>
                <div>
                    <label style="display:block; margin-bottom:5px; font-weight:600;">Дом</label>
                    <input type="text" id="client-house" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
                </div>
            </div>

            <!-- Скидка -->
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-top:15px;">
                <div>
                    <label style="display:block; margin-bottom:5px; font-weight:600;">Скидка на услуги (%)</label>
                    <div style="display:flex; gap:8px; align-items:center;">
                        <input type="number" id="client-discount" min="0" max="100" value="0" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px; transition: background-color 0.3s;">
                    </div>
                    <div id="suggested-discount-info" style="font-size:12px; color:#666; margin-top:4px;"></div>
                </div>
                <div></div>
            </div>

            <!-- Заметки -->
            <div style="margin-top:15px;">
                <label style="display:block; margin-bottom:5px; font-weight:600;">Заметки</label>
                <textarea id="client-notes" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px; min-height:60px;" placeholder="Дополнительная информация..."></textarea>
            </div>
        </fieldset>

        <!-- Кнопки -->
        <div style="display:flex; gap:10px; justify-content:flex-end;">
            <button class="button" onclick="document.getElementById('client-modal-overlay').style.display='none'">Отмена</button>
            <button class="button button-primary" onclick="myser_save_client_from_modal()">Сохранить</button>
        </div>
    </div>
</div>