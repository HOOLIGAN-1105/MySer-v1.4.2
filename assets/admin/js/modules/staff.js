// ========== Staff Module ==========

let staff_current_page = 1;
let staff_total_pages = 1;
let staffPersonalData = {};
let staffLegalData = {};

// ========================================
// Загрузка списка сотрудников
// ========================================
function myser_load_staff(page = 1) {
    staff_current_page = page;
    const search = document.getElementById('staff-search').value;
    jQuery.post(myser_ajax.ajaxurl, {
        action: 'myser_get_staff',
        nonce: myser_ajax.nonce,
        page: page,
        per_page: 20,
        search: search
    }, function(response) {
        if (response.success) {
            let html = '';
            if (response.data.items.length === 0) {
                html = '<tr><td colspan="9">Нет сотрудников</td></tr>';
            } else {
                response.data.items.forEach(function(staff) {
                    const status = staff.work_status || staff.status || 'works';
                    const statusMap = {
                        works: { bg: '#d4edda', text: 'Работает' },
                        sick: { bg: '#fff3cd', text: 'На больничном' },
                        fired: { bg: '#f8d7da', text: 'Уволен' },
                        vacation: { bg: '#cce5ff', text: 'Отпуск' },
                        business_trip: { bg: '#e2d9f3', text: 'Командировка' }
                    };
                    const style = statusMap[status] || { bg: '#e2e3e5', text: status };
                    const bgColor = style.bg;
                    const statusText = style.text;
                    html += `<tr style="background-color:${bgColor}; color:#000000; cursor:pointer;" onclick="myser_open_staff_modal(${staff.id})">
                        <td>${staff.id}</td>
                        <td>${staff.staff_name || ''}</td>
                        <td>${staff.staff_position || ''}</td>
                        <td>${staff.specialization || ''}</td>
                        <td>${staff.department || ''}</td>
                        <td>${staff.subject_roles || ''}</td>
                        <td>${statusText}</td>
                        <td>${staff.work_start_date || ''}</td>
                        <td>
                            <button class="button button-small" onclick="event.stopPropagation(); myser_open_staff_modal(${staff.id})" style="color:inherit;">✏️</button>
                             <button class="button button-small" onclick="event.stopPropagation(); myser_delete_staff(${staff.id})" style="color:red;">❌</button>
                        </td>
                    </tr>`;
                });
            }
            document.getElementById('staff-tbody').innerHTML = html;
            
            staff_total_pages = response.data.pages || 1;
            let pagination_html = `<span>Страница ${staff_current_page} из ${staff_total_pages}</span>`;
            for (let i = 1; i <= Math.min(staff_total_pages, 10); i++) {
                pagination_html += `<button class="button button-small" onclick="myser_load_staff(${i})" ${i === staff_current_page ? 'disabled' : ''}>${i}</button>`;
            }
            document.querySelector('#staff-table-wrap .pagination').innerHTML = pagination_html;
        } else {
            alert('Ошибка: ' + (response.data?.message || 'Неизвестная ошибка'));
        }
    });
}

// ========================================
// Загрузка подразделений в select
// ========================================
function loadDepartmentsToSelect(selectedIds) {
    selectedIds = selectedIds || [];
    jQuery.post(myser_ajax.ajaxurl, {
        action: 'myser_get_departments',
        nonce: myser_ajax.nonce
    }, function(response) {
        const select = document.getElementById('staff-subdivision');
        select.innerHTML = '';
        if (response.success && response.data) {
            response.data.forEach(function(dep) {
                const option = document.createElement('option');
                option.value = dep.id;
                option.textContent = dep.full_name || dep.short_name;
                if (selectedIds.indexOf(String(dep.id)) !== -1) {
                    option.selected = true;
                }
                select.appendChild(option);
            });
        } else {
            select.innerHTML = '<option value="">— Нет подразделений —</option>';
        }
    });
}

// ========================================
// Основная модалка
// ========================================
function myser_open_staff_modal(id = null) {
    const overlay = document.getElementById('staff-modal-overlay');
    overlay.style.display = 'flex';
    
    if (id) {
        // Редактирование
        document.getElementById('staff-modal-title').textContent = '✏️ Редактировать сотрудника';
        document.getElementById('staff-edit-id').value = id;
        
        jQuery.post(myser_ajax.ajaxurl, {
            action: 'myser_get_staff_member',
            nonce: myser_ajax.nonce,
            staff_id: id
        }, function(response) {
            if (response.success) {
                const s = response.data;
                const nameParts = (s.staff_name || '').split(' ');
                document.getElementById('staff-last-name').value = nameParts[0] || '';
                document.getElementById('staff-first-name').value = nameParts[1] || '';
                document.getElementById('staff-middle-name').value = nameParts[2] || '';
                document.getElementById('staff-position').value = s.staff_position || '';
                document.getElementById('staff-specialization').value = s.specialization || '';
                document.getElementById('staff-work-date').value = s.work_start_date || '';
                document.getElementById('staff-status').value = s.work_status || s.status || 'works';
                document.getElementById('staff-mobile-phone-main').value = s.mobile_phone || '';

                // Подразделения
                const selectedDepartments = (s.department_ids || []).map(String);
                loadDepartmentsToSelect(selectedDepartments);

                // Роли
                const currentRoles = (s.subject_roles || '').split(',').map(r => r.trim());
                document.querySelectorAll('input[name="staff-roles[]"]').forEach(cb => {
                    cb.checked = currentRoles.includes(cb.value);
                });

                // Extra data
                if (s.extra_data) {
                    try {
                        const extra = JSON.parse(s.extra_data);
                        if (extra.personal) {
                            staffPersonalData = extra.personal;
                            document.getElementById('staff-marital-status').value = extra.personal.marital_status || '';
                            document.getElementById('staff-children-count').value = extra.personal.children_count || '';
                            document.getElementById('staff-birth-date').value = extra.personal.birth_date || '';
                            document.getElementById('staff-personal-email').value = extra.personal.personal_email || '';
                            document.getElementById('staff-mobile-phone').value = extra.personal.mobile_phone || '';
                            document.getElementById('staff-mobile-phone-main').value = extra.personal.mobile_phone || '';
                            document.getElementById('staff-work-phone').value = extra.personal.work_phone || '';
                            document.getElementById('staff-home-phone').value = extra.personal.home_phone || '';
                            document.getElementById('staff-reg-address').value = extra.personal.registration_address || '';
                            document.getElementById('staff-real-address').value = extra.personal.real_address || '';
                            document.getElementById('staff-personal-notes').value = extra.personal.personal_notes || '';
                        }
                        if (extra.legal) {
                            staffLegalData = extra.legal;
                            document.getElementById('staff-employment-role').value = extra.legal.employment_role || '';
                            document.getElementById('staff-vehicle').value = extra.legal.vehicle || '';
                            document.getElementById('staff-driver-license').value = extra.legal.driver_license || '';
                            document.getElementById('staff-passport').value = extra.legal.passport || '';
                            document.getElementById('staff-tax-id').value = extra.legal.tax_id || '';
                            document.getElementById('staff-snils').value = extra.legal.snils || '';
                            document.getElementById('staff-pdms').value = extra.legal.pdms || '';
                            document.getElementById('staff-employee-id').value = extra.legal.employee_id || '';
                        }
                    } catch(e) {}
                }
            }
        });
    } else {
        // Добавление
        document.getElementById('staff-modal-title').textContent = '➕ Добавить сотрудника';
        document.getElementById('staff-edit-id').value = '';
        document.getElementById('staff-last-name').value = '';
        document.getElementById('staff-first-name').value = '';
        document.getElementById('staff-middle-name').value = '';
        document.getElementById('staff-position').value = '';
        document.getElementById('staff-specialization').value = '';
        document.getElementById('staff-work-date').value = '';
        document.getElementById('staff-status').value = 'works';
        document.getElementById('staff-mobile-phone-main').value = '';
        document.querySelectorAll('input[name="staff-roles[]"]').forEach(cb => { cb.checked = false; });
        loadDepartmentsToSelect([]);
        staffPersonalData = {};
        staffLegalData = {};
    }
}

function myser_close_staff_modal() {
    document.getElementById('staff-modal-overlay').style.display = 'none';
}

// ========================================
// Сохранение сотрудника
// ========================================
function myser_save_staff_from_modal() {
    const id = document.getElementById('staff-edit-id').value;
    const last_name = document.getElementById('staff-last-name').value.trim();
    const first_name = document.getElementById('staff-first-name').value.trim();
    const middle_name = document.getElementById('staff-middle-name').value.trim();

    if (!first_name) {
        alert('Имя обязательно для заполнения');
        return;
    }

    const staff_name = [last_name, first_name, middle_name].filter(Boolean).join(' ');
    
    const roles = [];
    document.querySelectorAll('input[name="staff-roles[]"]:checked').forEach(cb => {
        roles.push(cb.value);
    });
    
    const extraData = {
        personal: staffPersonalData,
        legal: staffLegalData
    };
    
    const deptSelect = document.getElementById('staff-subdivision');
    const department_ids = [];
    for (let i = 0; i < deptSelect.options.length; i++) {
        if (deptSelect.options[i].selected) {
            department_ids.push(deptSelect.options[i].value);
        }
    }
    
    const data = {
        action: 'myser_save_staff',
        nonce: myser_ajax.nonce,
        staff_name: staff_name,
        staff_position: document.getElementById('staff-position').value.trim(),
        specialization: document.getElementById('staff-specialization').value.trim(),
        'department_ids[]': department_ids,
        work_start_date: document.getElementById('staff-work-date').value,
        status: document.getElementById('staff-status').value,
        roles: roles,
        extra_data: JSON.stringify(extraData),
        mobile_phone: document.getElementById('staff-mobile-phone-main').value.trim()
    };
    
    if (id) data.id = id;
    
    jQuery.post(myser_ajax.ajaxurl, data, function(response) {
        if (response.success) {
            myser_close_staff_modal();
            myser_load_staff(staff_current_page);
        } else {
            alert('Ошибка: ' + (response.data?.message || 'Неизвестная ошибка'));
        }
    });
}

// ========================================
// Удаление сотрудника
// ========================================
function myser_delete_staff(id) {
    if (!confirm('Удалить сотрудника?')) return;
    jQuery.post(myser_ajax.ajaxurl, {
        action: 'myser_delete_staff',
        nonce: myser_ajax.nonce,
        staff_id: id
    }, function(response) {
        if (response.success) {
            myser_load_staff(staff_current_page);
        } else {
            alert('Ошибка: ' + (response.data?.message || 'Неизвестная ошибка'));
        }
    });
}

// ========================================
// Личные данные (суб-модалка)
// ========================================
function myser_open_staff_personal_modal() {
    document.getElementById('staff-mobile-phone').value = document.getElementById('staff-mobile-phone-main').value;
    document.getElementById('staff-personal-modal-overlay').style.display = 'flex';
}

function myser_close_staff_personal_modal() {
    document.getElementById('staff-personal-modal-overlay').style.display = 'none';
}

function myser_save_staff_personal_data() {
    staffPersonalData = {
        marital_status: document.getElementById('staff-marital-status').value,
        children_count: document.getElementById('staff-children-count').value,
        birth_date: document.getElementById('staff-birth-date').value,
        personal_email: document.getElementById('staff-personal-email').value.trim(),
        mobile_phone: document.getElementById('staff-mobile-phone').value.trim(),
        work_phone: document.getElementById('staff-work-phone').value.trim(),
        home_phone: document.getElementById('staff-home-phone').value.trim(),
        registration_address: document.getElementById('staff-reg-address').value.trim(),
        real_address: document.getElementById('staff-real-address').value.trim(),
        personal_notes: document.getElementById('staff-personal-notes').value.trim()
    };
    document.getElementById('staff-mobile-phone-main').value = staffPersonalData.mobile_phone;
    myser_close_staff_personal_modal();
}

// ========================================
// Юридическая информация (суб-модалка)
// ========================================
function myser_open_staff_legal_modal() {
    document.getElementById('staff-legal-modal-overlay').style.display = 'flex';
}

function myser_close_staff_legal_modal() {
    document.getElementById('staff-legal-modal-overlay').style.display = 'none';
}

function myser_save_staff_legal_data() {
    staffLegalData = {
        employment_role: document.getElementById('staff-employment-role').value,
        vehicle: document.getElementById('staff-vehicle').value.trim(),
        driver_license: document.getElementById('staff-driver-license').value.trim(),
        passport: document.getElementById('staff-passport').value.trim(),
        tax_id: document.getElementById('staff-tax-id').value.trim(),
        snils: document.getElementById('staff-snils').value.trim(),
        pdms: document.getElementById('staff-pdms').value.trim(),
        employee_id: document.getElementById('staff-employee-id').value.trim()
    };
    myser_close_staff_legal_modal();
}

// ========================================
// Закрытие по клику на оверлей
// ========================================
document.addEventListener('DOMContentLoaded', function() {
    // Основная модалка
    const mainOverlay = document.getElementById('staff-modal-overlay');
    if (mainOverlay) {
        mainOverlay.addEventListener('click', function(e) {
            if (e.target === this) myser_close_staff_modal();
        });
    }
    
    // Личные данные
    const personalOverlay = document.getElementById('staff-personal-modal-overlay');
    if (personalOverlay) {
        personalOverlay.addEventListener('click', function(e) {
            if (e.target === this) myser_close_staff_personal_modal();
        });
    }
    
    // Юридическая информация
    const legalOverlay = document.getElementById('staff-legal-modal-overlay');
    if (legalOverlay) {
        legalOverlay.addEventListener('click', function(e) {
            if (e.target === this) myser_close_staff_legal_modal();
        });
    }
    
    // Загрузка данных
    myser_load_staff(1);
});

// Поддержка поиска по Enter
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('staff-search');
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                myser_load_staff(1);
            }
        });
    }
});
