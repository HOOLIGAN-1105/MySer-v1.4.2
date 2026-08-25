/**
 * Модуль управления клиентами
 *
 * @package MySer
 */

(function($) {
    'use strict';

    // Состояние
    let currentPage = 1;
    let totalPages = 1;

    // ========== Глобальная функция для кнопки в субмодалке ==========
    window.myser_add_client_from_select_modal = function() {
        console.log('myser_add_client_from_select_modal вызвана');

        // Закрываем субмодалку
        const selectOverlay = document.getElementById('client-select-overlay');
        if (selectOverlay) {
            selectOverlay.style.display = 'none';
        }

        // Открываем модалку клиента через существующую функцию openModal
        if (typeof openModal === 'function') {
            openModal();
        } else {
            console.error('Функция openModal не определена');
            alert('Ошибка: функция добавления клиента не загружена');
        }
    };

    // ========== Загрузка списка ==========
    function loadClients(page = 1) {
        currentPage = page;
        const searchInput = document.getElementById('clients-search');
        const search = searchInput ? searchInput.value : '';

        $.ajax({
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
            if (response.success) {
                renderTable(response.data);
                renderPagination(response.data);
            } else {
                alert('Ошибка: ' + (response.data?.message || 'Неизвестная ошибка'));
            }
        }).fail(function() {
            alert('Ошибка соединения с сервером');
        });
    }

    function renderTable(data) {
        const tbody = document.getElementById('clients-tbody');
        let html = '';

        if (!data.items || data.items.length === 0) {
            html = '<tr><td colspan="10">Нет клиентов</td></tr>';
        } else {
            data.items.forEach(function(c) {
                // Статус и цвет
                let statusText, statusColor;
                if (c.status === 'permanent') {
                    statusText = 'Постоянный';
                    statusColor = '#FF8C00';
                } else if (c.status === 'regular') {
                    statusText = 'Регулярный';
                    statusColor = '#20B2AA';
                } else {
                    statusText = 'Новый';
                    statusColor = '#4169E1';
                }

                const adequacyLabel = c.is_problem_client == 1 ? '⚠️ Проблемный' : '✅ Адекватный';
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
                        <button class="button button-small" onclick="MySerClients.openModal(${c.id})">✏️</button>
                        <button class="button button-small" onclick="MySerClients.deleteClient(${c.id})" style="color:red;">❌</button>
                    </td>
                </tr>`;
            });
        }
        tbody.innerHTML = html;
        document.getElementById('clients-total-info').innerHTML = 'Всего: ' + (data.total || 0);
    }

    function renderPagination(data) {
        totalPages = data.pages || 1;
        const container = document.getElementById('clients-pagination');
        let html = `<span>Страница ${currentPage} из ${totalPages}</span>`;

        for (let i = 1; i <= Math.min(totalPages, 10); i++) {
            html += `<button class="button button-small" onclick="MySerClients.loadPage(${i})" ${i === currentPage ? 'disabled' : ''}>${i}</button>`;
        }
        container.innerHTML = html;
    }

    // ========== Модальное окно ==========
    function openModal(id = null) {
        // Сбрасываем поиск при открытии (только если элемент существует)
        const searchInput = document.getElementById('clients-search');
        if (searchInput) {
            searchInput.value = '';
        }

        const overlay = document.getElementById('client-modal-overlay');
        if (overlay) {
            overlay.style.display = 'flex';
        }

        if (id) {
            const titleEl = document.getElementById('client-modal-title');
            if (titleEl) {
                titleEl.textContent = '✏️ Редактировать клиента';
            }
            const editIdEl = document.getElementById('client-edit-id');
            if (editIdEl) {
                editIdEl.value = id;
            }

            $.ajax({
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
                    toggleFields();
                }
            });
        } else {
            // Добавление нового клиента
            const titleEl = document.getElementById('client-modal-title');
            if (titleEl) {
                titleEl.textContent = '+ Добавить клиента';
            }
            const editIdEl = document.getElementById('client-edit-id');
            if (editIdEl) {
                editIdEl.value = '';
            }
            const typeEl = document.getElementById('client-type');
            if (typeEl) {
                typeEl.value = 'person';
            }
            const lastNameEl = document.getElementById('client-last-name');
            if (lastNameEl) {
                lastNameEl.value = '';
            }
            const firstNameEl = document.getElementById('client-first-name');
            if (firstNameEl) {
                firstNameEl.value = '';
            }
            const middleNameEl = document.getElementById('client-middle-name');
            if (middleNameEl) {
                middleNameEl.value = '';
            }
            const companyEl = document.getElementById('client-company');
            if (companyEl) {
                companyEl.value = '';
            }
            const legalFormEl = document.getElementById('client-legal-form');
            if (legalFormEl) {
                legalFormEl.value = '';
            }
            const phoneEl = document.getElementById('client-phone');
            if (phoneEl) {
                phoneEl.value = '';
            }
            const otherPhoneEl = document.getElementById('client-other-phone');
            if (otherPhoneEl) {
                otherPhoneEl.value = '';
            }
            const emailEl = document.getElementById('client-email');
            if (emailEl) {
                emailEl.value = '';
            }
            const cityEl = document.getElementById('client-city');
            if (cityEl) {
                cityEl.value = '';
            }
            const streetEl = document.getElementById('client-street');
            if (streetEl) {
                streetEl.value = '';
            }
            const houseEl = document.getElementById('client-house');
            if (houseEl) {
                houseEl.value = '';
            }
            const problemEl = document.getElementById('client-problem');
            if (problemEl) {
                problemEl.checked = false;
            }
            const discountEl = document.getElementById('client-discount');
            if (discountEl) {
                discountEl.value = 0;
            }
            const notesEl = document.getElementById('client-notes');
            if (notesEl) {
                notesEl.value = '';
            }
            toggleFields();
        }
    }

    function closeModal() {
        document.getElementById('client-modal-overlay').style.display = 'none';
    }

    // ========== Переключение полей ==========
    function toggleFields() {
        const typeEl = document.getElementById('client-type');
        if (!typeEl) return;
        const type = typeEl.value;
        const personNameEl = document.getElementById('client-person-name');
        if (personNameEl) {
            personNameEl.style.display = type === 'person' ? 'grid' : 'none';
        }
        const companyNameEl = document.getElementById('client-company-name');
        if (companyNameEl) {
            companyNameEl.style.display = type === 'company' ? 'grid' : 'none';
        }
    }

    // ========== Сохранение ==========
    function saveClient() {
        const id = document.getElementById('client-edit-id').value;
        const clientType = document.getElementById('client-type').value;
        const firstName = document.getElementById('client-first-name').value.trim();
        const lastName = document.getElementById('client-last-name').value.trim();
        const middleName = document.getElementById('client-middle-name').value.trim();
        const companyName = document.getElementById('client-company').value.trim();
        const legalForm = document.getElementById('client-legal-form').value;
        const phone = document.getElementById('client-phone').value.trim();
        const otherPhone = document.getElementById('client-other-phone').value.trim();
        const email = document.getElementById('client-email').value.trim();
        const city = document.getElementById('client-city').value.trim();
        const street = document.getElementById('client-street').value.trim();
        const house = document.getElementById('client-house').value.trim();
        const isProblem = document.getElementById('client-problem').checked ? 1 : 0;
        const discount = parseFloat(document.getElementById('client-discount').value) || 0;
        const notes = document.getElementById('client-notes').value.trim();

        if (!firstName && clientType === 'person') {
            alert('Имя обязательно для заполнения');
            return;
        }
        if (!companyName && clientType === 'company') {
            alert('Название компании обязательно для заполнения');
            return;
        }

        const data = {
            action: 'myser_save_client',
            nonce: myser_ajax.nonce,
            client_type: clientType,
            first_name: firstName,
            last_name: lastName,
            middle_name: middleName,
            company_name: companyName,
            legal_form: legalForm,
            phone: phone,
            other_phone: otherPhone,
            email: email,
            city: city,
            street: street,
            house: house,
            is_problem_client: isProblem,
            service_discount_percent: discount,
            notes: notes
        };
        if (id) data.id = id;

        $.ajax({
            url: myser_ajax.ajaxurl,
            type: 'POST',
            data: data,
            dataType: 'json',
            cache: false
        }).done(function(response) {
            if (response.success) {
                showMyserToast(response.data.message || 'Клиент сохранён', 'success');
                closeModal();
                // Сбрасываем поиск и перезагружаем первую страницу
                document.getElementById('clients-search').value = '';
                loadClients(1);
            } else {
                alert('Ошибка: ' + (response.data?.message || 'Неизвестная ошибка'));
            }
        }).fail(function() {
            alert('Ошибка соединения с сервером');
        });
    }

    // ========== Удаление ==========
    function deleteClient(id) {
        if (!confirm('Удалить клиента?')) return;

        $.ajax({
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
                showMyserToast(response.data.message || 'Клиент удалён', 'success');
                loadClients(currentPage);
            } else {
                alert('Ошибка: ' + (response.data?.message || 'Неизвестная ошибка'));
            }
        });
    }

    // ========== Экспорт публичного API ==========
    window.MySerClients = {
        loadPage: loadClients,
        openModal: openModal,
        closeModal: closeModal,
        saveClient: saveClient,
        deleteClient: deleteClient,
        toggleFields: toggleFields
    };

    // ========== Инициализация ==========
    $(document).ready(function() {
        // Проверяем, есть ли на странице элементы для работы с клиентами
        const hasClientsTbody = document.getElementById('clients-tbody');
        if (hasClientsTbody) {
            loadClients(1);

            // Кнопка "Добавить"
            const addBtn = document.getElementById('add-client-btn');
            if (addBtn) {
                addBtn.addEventListener('click', function() {
                    openModal();
                });
            }

            // Закрытие по клику на оверлей
            const overlay = document.getElementById('client-modal-overlay');
            if (overlay) {
                overlay.addEventListener('click', function(e) {
                    if (e.target === this) closeModal();
                });
            }

            // Поиск по Enter
            const searchInput = document.getElementById('clients-search');
            if (searchInput) {
                searchInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        loadClients(1);
                    }
                });
            }

            // Кнопка "Найти"
            const searchBtn = document.querySelector('#clients-page-search-btn');
            if (searchBtn) {
                searchBtn.addEventListener('click', function() {
                    loadClients(1);
                });
            }

            // Кнопка "Сбросить"
            const resetBtn = document.querySelector('#clients-page-reset-btn');
            if (resetBtn) {
                resetBtn.addEventListener('click', function() {
                    const searchInput2 = document.getElementById('clients-search');
                    if (searchInput2) {
                        searchInput2.value = '';
                    }
                    loadClients(1);
                });
            }

            // Переключение типа клиента
            const clientType = document.getElementById('client-type');
            if (clientType) {
                clientType.addEventListener('change', toggleFields);
            }

            // Кнопка сохранения в модалке
            const saveBtn = document.querySelector('#client-modal-overlay .button-primary');
            if (saveBtn) {
                saveBtn.addEventListener('click', saveClient);
            }
        }

        // ========================================
        // Экспорт функций в глобальную область
        // ========================================
        // Сохраняем ссылку на openModal в глобальную область
        if (typeof window.myser_open_client_modal_impl === 'undefined') {
            window.myser_open_client_modal_impl = openModal;
        } else {
            // Если уже определена, просто обновляем
            window.myser_open_client_modal_impl = openModal;
        }

        // Обработчик кнопки "+ Добавить" в субмодалке выбора клиента (всегда активен)
        $(document).on('click', '#add-client-in-select-modal', function(e) {
            e.preventDefault();
            if (typeof openModal === 'function') {
                openModal();
            } else {
                console.error('Функция openModal не определена');
                alert('Ошибка: функция добавления клиента не загружена');
            }
        });
    });

})(jQuery);