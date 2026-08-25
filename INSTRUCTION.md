# Инструкция для разработчиков MySer

**Версия:** 1.3.3  
**Дата:** 2026-08-11

---

## Содержание

1. [Общее описание](#общее-описание)
2. [Структура проекта](#структура-проекта)
3. [Классы ядра](#классы-ядра)
4. [AJAX-обработчики](#ajax-обработчики)
5. [База данных](#база-данных)
6. [Миграции](#миграции)
7. [Логирование](#логирование)
8. [Стиль кода](#стиль-кода)
9. [Работа с сервером](#работа-с-сервером)

---

## Общее описание

MySer — WordPress-плагин для управления сервисным центром. Написан на PHP 8.0+ с использованием объектно-ориентированного подхода. Плагин использует собственные таблицы в базе данных и интегрируется с WordPress через хуки, фильтры, AJAX-обработчики и админ-панель.

---

## Структура проекта

```
myser/
├── assets/              # Публичные ассеты (фронтенд)
│   ├── admin/           # Админ-панель
│   │   ├── css/         # Стили админки
│   │   ├── js/          # Скрипты админки
│   │   └── images/      # Иконки и изображения
│   ├── css/             # Стили фронтенда (онлайн-запись)
│   └── js/              # Скрипты фронтенда
├── languages/           # Файлы перевода (.pot, .po, .mo)
├── lib/                 # Ядро плагина
│   ├── admin/           # Классы админ-панели
│   ├── includes/        # Ядро (PHP-классы)
│   │   ├── ajax/        # AJAX-обработчики
│   │   ├── activator.php
│   │   ├── ajax-handler.php
│   │   ├── database.php
│   │   ├── logger.php
│   │   ├── migrator.php
│   │   └── ...
│   ├── templates/       # HTML-шаблоны
│   └── traits/          # Трейты
├── myser.php            # Главный файл плагина
├── uninstall.php        # Деинсталляция
├── README.md            # Документация
├── USER_GUIDE.md        # Руководство пользователя
├── INSTALL.md           # Инструкция по установке
├── CHANGELOG.md         # История изменений
└── INSTRUCTION.md       # Инструкция для разработчиков
```

---

## Классы ядра

### Главный класс `MySer_Plugin` (myser.php)

- Singleton
- Инициализация плагина через `run()`
- Подключение классов ядра
- Регистрация хуков активации/деактивации

### `Activator` (activator.php)

- Запуск миграций при активации плагина
- Вызов `Migrator::run()`
- Установка начальных данных

### `Ajax_Handler` (ajax-handler.php)

- Базовый класс для всех AJAX-обработчиков
- Методы `verify_nonce()`, `check_permissions()`, `send_response()`
- Регистрация хуков через `register_hooks()`

**Важно:** В дочерних классах используйте `protected static` для методов `verify_nonce()` и `check_permissions()`, так как `private static` не наследуется.

### `Database` (database.php)

- Работа с БД: получение таблиц, запросы, CRUD
- Все 15 таблиц: clients, orders, staff, departments, subjects, subject_roles, salary_grids, staff_salary_grids, statuses, services, items, order_services, order_stock, roles, work_status
- Методы для получения записей по ID
- Унифицированные методы `query()`, `get_results()`, `get_var()`

### `Logger` (logger.php)

- Система логирования с уровнями: debug, info, warning, error, critical, off
- Логи хранятся в `wp-content/uploads/myser-logs/`
- Автоматическая очистка старых логов (по умолчанию 7 дней)

### `Migrator` (migrator.php)

- Единая схема БД без версионных переходов
- `run()` создаёт все таблицы за один проход
- Защитные `ALTER TABLE ADD COLUMN IF NOT EXISTS`
- Начальные данные: статусы, роли

---

## AJAX-обработчики

Все обработчики находятся в `lib/includes/ajax/class-ajax-{module}.php` и наследуют `Ajax_Handler`.

**Прогресс выноса модулей:**

| Модуль | Класс | Файл | Статус |
|--------|-------|------|--------|
| Departments | `Departments_Handler` | `class-ajax-departments.php` | ✅ Вынесен |
| Backups | `Backups_Handler` | `class-ajax-backups.php` | ✅ Вынесен |
| Clients | `Clients_Handler` | `class-ajax-clients.php` | ✅ Вынесен |
| Staff | `Staff_Handler` | `class-ajax-staff.php` | ✅ Вынесен |
| Orders | `Orders_Handler` | `class-ajax-orders.php` | ✅ Вынесен |
| Products | `Products_Handler` | `class-ajax-products.php` | ⏳ Шаблон готов |
| Reports | `Reports_Handler` | `class-ajax-reports.php` | ⏳ Шаблон готов |
| Settings | `Settings_Handler` | `class-ajax-settings.php` | ⏳ Шаблон готов |

**Стандартные методы в каждом обработчике:**
- `register_hooks()` — регистрация AJAX-действий
- `get_items()` — получение списка
- `get_item()` — получение одной записи
- `save_item()` — сохранение/обновление
- `delete_item()` — удаление

---

## База данных

### Список таблиц (15)

1. `myser_clients` — Клиенты
2. `myser_orders` — Заказы
3. `myser_staff` — Сотрудники
4. `myser_departments` — Подразделения
5. `myser_subjects` — Субъекты (универсальная таблица)
6. `myser_subject_roles` — Роли субъектов
7. `myser_salary_grids` — Сетки заработка
8. `myser_staff_salary_grids` — Назначения сеток сотрудникам
9. `myser_statuses` — Статусы заказов
10. `myser_services` — Услуги
11. `myser_items` — Товары/Запчасти
12. `myser_order_services` — Услуги в заказе
13. `myser_order_stock` — Товары в заказе
14. `myser_roles` — Справочник ролей
15. `myser_work_status` — Справочник статусов работы

### Ключевые связи

- `myser_clients.subject_id` → `myser_subjects.id`
- `myser_staff.subject_id` → `myser_subjects.id`
- `myser_orders.subject_id` → `myser_subjects.id`
- `myser_staff.department` → JSON-массив ID из `myser_departments`
- `myser_staff_salary_grids.staff_id` → `myser_staff.id`
- `myser_staff_salary_grids.grid_id` → `myser_salary_grids.id`

### Индексы

- `myser_orders`: idx_client, idx_status, idx_doc_number
- `myser_staff`: idx_staff_name, idx_email, idx_supervisor
- `myser_subjects`: subject_type, last_name, email, mobile_phone

---

## Миграции

Мигратор (`lib/includes/migrator.php`) работает по принципу "одна миграция → финальная структура":

1. `CREATE TABLE IF NOT EXISTS` для всех таблиц
2. Защитные `ALTER TABLE ADD COLUMN IF NOT EXISTS` для обратной совместимости
3. Начальные данные: статусы работы, роли

**При добавлении новых полей:**
- Добавлять колонку в `CREATE TABLE`
- Добавлять защитный `ALTER TABLE ADD COLUMN IF NOT EXISTS`
- Не удалять существующие колонки

---

## Логирование

Используйте `MySer\Logger`:

```php
use MySer\Logger;

Logger::debug('Отладочное сообщение');
Logger::info('Информационное сообщение');
Logger::warning('Предупреждение');
Logger::error('Ошибка');
Logger::critical('Критическая ошибка');
```

Уровни логирования настраиваются в админке (вкладка «Системные»).

---

## Стиль кода

- PHP 8.0+ с типизацией (`strict_types=1`)
- Использовать `$wpdb->prepare()` для всех запросов
- Проверка `nonce` во всех AJAX-запросах
- Экранирование вывода через `esc_*()` функции
- PSR-12 стиль (можно гибко)
- Документирование методов через PHPDoc

---

## Работа с сервером

### Локальная разработка

- Рабочая папка: `C:\Users\HOOLIGAN\.nanobot\workspace\myser`
- Сервер: `C:\OSPanel\home\MySer\wp-content\plugins\myser`

### Деплой

1. Правим файлы в workspace
2. Копируем на сервер через `Copy-Item`
3. Сбрасываем OPcache (перезапуск веб-сервера OpenServer)

### Бэкапы

- Перед правками делать бэкап папки myser с меткой времени
- При повреждении — восстанавливать из бэкапа
- Не использовать PowerShell-скрипты с regex для редактирования PHP-файлов (особенно в Windows-1251) — могут повредить файлы

### Инструменты

- `write_file` — предпочтительный инструмент для редактирования (полная перезапись)
- `apply_patch` и `edit_file` ненадёжны в этом workspace
- `read_file` — для чтения (лимит ~100 MB)

---

**Дата обновления:** 2026-08-11