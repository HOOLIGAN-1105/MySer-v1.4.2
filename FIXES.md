# MySer — Исправления (2026-08-21)

## Баг 1: Клиенты не отображаются после добавления

**Симптом:** после добавления клиента тост появляется, но список клиентов не обновляется — только после F5.

**Причина:** после сохранения не сбрасывался поиск и не перезагружалась первая страница.

**Исправление:**

Файл: `lib/templates/clients.php`

В функции `myser_save_client()` после успешного ответа:

```php
if (response.success) {
    showMyserToast(response.data.message || 'Клиент сохранён', 'success');
    myser_close_client_modal();
    // Сбрасываем поиск и перезагружаем первую страницу
    document.getElementById('clients-search').value = '';
    myser_load_clients(1);
}
```

---

## Баг 2: Логотип подразделения затирается при сохранении

**Симптом:** после редактирования подразделения логотип исчезает (становится пустым).

**Причина:** в AJAX-запросе не передавалось поле `logo`.

**Исправление 1:**

Файл: `assets/admin/js/settings.js`

В функции сохранения подразделения (строка ~447-474) добавить поле `logo`:

```javascript
$('#save-department-btn').on('click', function() {
    var id = $('#dep-id').val();
    $.post(ajaxurl, {
        action: 'myser_save_department',
        nonce: myser_ajax.nonce,
        dep_id: id,
        // ... другие поля ...
        status: $('#dep-status').val(),
        logo: $('#dep-logo').val(),  // <-- добавить эту строку
    });
});
```

**Исправление 2 (защита от затирания):**

Файл: `lib/includes/ajax/class-ajax-departments.php`

В функции `save_department()` убрать `logo` из основного массива `$data` и добавить условное обновление:

```php
$data = [
    // ... все поля, кроме logo ...
];

// Добавляем logo только если оно передано и не пустое
$logo_value = sanitize_text_field($_POST['logo'] ?? '');
if (!empty($logo_value)) {
    $data['logo'] = $logo_value;
}
```

---

## Баг 3: Логотип не отображается на дашборде

**Симптом:** на главной странице (dashboard) вместо логотипа показывается иконка по умолчанию, даже если логотип загружен.

**Причина:** в `dashboard.php` использовалось поле `logo_url`, а в БД колонка называется `logo`.

**Исправление:**

Файл: `lib/templates/dashboard.php`

```php
if ($head_department) {
    $company_name = $head_department->full_name;
    $logo_url = $head_department->logo ?? '';  // было $head_department->logo_url
}
```

---

## Баг 4: Database::get_tables() возвращает неправильный формат

**Симптом:** ошибка `Undefined array key "clients"` в AJAX-обработчиках.

**Причина:** метод возвращал обычный массив, а не ассоциативный.

**Исправление:**

Файл: `lib/includes/database.php`

```php
public static function get_tables()
{
    global $wpdb;
    $prefix = $wpdb->prefix;
    
    $all_tables = [
        'clients'                => $prefix . 'myser_clients',
        'staff'                  => $prefix . 'myser_staff',
        'departments'            => $prefix . 'myser_departments',
        // ... остальные таблицы с ключами
    ];
    
    // Фильтруем только существующие таблицы
    $existing_tables = [];
    foreach ($all_tables as $key => $table) {
        $result = $wpdb->get_var("SHOW TABLES LIKE '$table'");
        if ($result === $table) {
            $existing_tables[$key] = $table;
        }
    }
    
    return $existing_tables;
}
```

---

## Баг 5: Ошибка миграции Unknown column 'is_default'

**Симптом:** при ребуте плагина ошибка `Unknown column 'is_default'`.

**Причина:** в `migrator.php` пытались добавить колонку `logo` AFTER `is_default`, но колонка `is_default` ещё не существовала.

**Исправление:**

Файл: `lib/includes/migrator.php`

Перед добавлением `logo` и `stamp` сначала проверить и создать `is_default`:

```php
// is_default (если нет — добавить)
$col = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `$dep_table` LIKE %s", 'is_default'));
if (empty($col)) {
    $wpdb->query("ALTER TABLE `$dep_table` ADD COLUMN `is_default` TINYINT(1) DEFAULT 0 COMMENT '1=головное подразделение по умолчанию'");
}
// затем logo и stamp
```

---

## Примечания

- Все PHP/JS/CSS файлы в UTF-8 без BOM
- При деплое копировать файлы на сервер и сбрасывать OPcache
- Не править файлы напрямую на сервере — сначала в workspace, потом копировать
