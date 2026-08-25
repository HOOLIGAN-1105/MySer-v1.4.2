<?php
/**
 * Миграции схемы БД — единый метод без версионных переходов.
 *
 * При активации плагина создаёт/добавляет все таблицы и колонки,
 * независимо от текущего состояния БД (IF NOT EXISTS / SHOW COLUMNS).
 *
 * @package MySer
 */

namespace MySer;

defined('ABSPATH') || exit;

class Migrator
{

    /**
     * Выполнить все миграции (идемпотентно).
     */
    public static function run()
    {
        global $wpdb;
        $prefix = $wpdb->prefix;
        $charset_collate = $wpdb->get_charset_collate();
        $charset = Database::get_charset();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // ----------------------------------------------------------------
        // 1. Основные таблицы
        // ----------------------------------------------------------------

        // Клиенты
        $sql = "CREATE TABLE IF NOT EXISTS {$prefix}myser_clients (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            last_name VARCHAR(100) NOT NULL,
            first_name VARCHAR(100) NOT NULL,
            middle_name VARCHAR(100) DEFAULT NULL,
            phone VARCHAR(50) DEFAULT NULL,
            other_phone VARCHAR(50) DEFAULT NULL COMMENT 'Доп. телефон',
            email VARCHAR(255) DEFAULT NULL,
            address TEXT DEFAULT NULL,
            type VARCHAR(50) DEFAULT 'individual',
            order_count INT UNSIGNED DEFAULT 0 COMMENT 'Счётчик заказов',
            is_problem_client TINYINT(1) DEFAULT 0 COMMENT '0=адекватный, 1=проблемный',
            notes TEXT DEFAULT NULL,
            subject_id INT UNSIGNED DEFAULT NULL,
            extra_data LONGTEXT DEFAULT NULL COMMENT 'JSON с личными и юр. данными',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        dbDelta($sql);

        // Заказы
        $sql = "CREATE TABLE IF NOT EXISTS {$prefix}myser_orders (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            doc_number VARCHAR(100) DEFAULT NULL,
            doc_date DATETIME DEFAULT NULL,
            client_id INT UNSIGNED NOT NULL,
            device_type VARCHAR(100) DEFAULT NULL,
            device_manufacturer VARCHAR(100) DEFAULT NULL,
            device_model VARCHAR(100) DEFAULT NULL,
            device_serial VARCHAR(100) DEFAULT NULL,
            client_complaint TEXT DEFAULT NULL,
            status_id INT UNSIGNED DEFAULT 1,
            grand_total DECIMAL(12,2) DEFAULT 0.00,
            subject_id INT UNSIGNED DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_client (client_id),
            INDEX idx_status (status_id),
            INDEX idx_doc_number (doc_number)
        ) $charset_collate;";
        dbDelta($sql);

        // Сотрудники
        $sql = "CREATE TABLE IF NOT EXISTS {$prefix}myser_staff (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            staff_name VARCHAR(255) NOT NULL COMMENT 'Полное ФИО',
            staff_short_name VARCHAR(100) DEFAULT NULL COMMENT 'Краткое имя',
            use_in_schedule TINYINT(1) DEFAULT 1,
            mobile_phone VARCHAR(50) DEFAULT NULL,
            work_phone VARCHAR(50) DEFAULT NULL,
            home_phone VARCHAR(50) DEFAULT NULL,
            birth_day DATE DEFAULT NULL,
            email VARCHAR(255) DEFAULT NULL,
            work_start_date DATE DEFAULT NULL,
            staff_position VARCHAR(100) DEFAULT NULL,
            specialization VARCHAR(255) DEFAULT NULL,
            department VARCHAR(100) DEFAULT NULL,
            work_status VARCHAR(50) DEFAULT NULL,
            company VARCHAR(100) DEFAULT NULL,
            branch VARCHAR(100) DEFAULT NULL,
            supervisor_id INT UNSIGNED DEFAULT NULL,
            tabel_number VARCHAR(50) DEFAULT NULL,
            passport TEXT DEFAULT NULL,
            registration_address TEXT DEFAULT NULL,
            real_address TEXT DEFAULT NULL,
            family_status VARCHAR(50) DEFAULT NULL,
            kids TINYINT UNSIGNED DEFAULT 0,
            car VARCHAR(100) DEFAULT NULL,
            driving_licence VARCHAR(100) DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            salary DECIMAL(12,2) DEFAULT 0.00,
            percent_service DECIMAL(5,2) DEFAULT 0.00,
            percent_products DECIMAL(5,2) DEFAULT 0.00,
            subject_id INT UNSIGNED DEFAULT NULL,
            subject_roles VARCHAR(500) DEFAULT NULL COMMENT 'Роли через запятую',
            extra_data LONGTEXT DEFAULT NULL COMMENT 'JSON с личными и юр. данными',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_staff_name (staff_name(100)),
            INDEX idx_email (email),
            INDEX idx_supervisor (supervisor_id)
        ) $charset_collate;";
        dbDelta($sql);

        // ----------------------------------------------------------------
        // 2. Защитные ALTER — добавляем колонки, если таблицы уже были
        // ----------------------------------------------------------------
        $alter_columns = [
            'myser_staff' => [
                'subject_id'    => "ADD COLUMN `subject_id` INT UNSIGNED DEFAULT NULL",
                'subject_roles' => "ADD COLUMN `subject_roles` VARCHAR(500) DEFAULT NULL COMMENT 'Роли через запятую'",
                'extra_data'    => "ADD COLUMN `extra_data` LONGTEXT DEFAULT NULL COMMENT 'JSON с личными и юр. данными'",
                'work_status'   => "ADD COLUMN `work_status` VARCHAR(50) DEFAULT NULL",
            ],
            'myser_clients' => [
                'subject_id'         => "ADD COLUMN `subject_id` INT UNSIGNED DEFAULT NULL",
                'extra_data'         => "ADD COLUMN `extra_data` LONGTEXT DEFAULT NULL COMMENT 'JSON с личными и юр. данными'",
                'other_phone'        => "ADD COLUMN `other_phone` VARCHAR(50) DEFAULT NULL COMMENT 'Доп. телефон'",
                'is_problem_client'  => "ADD COLUMN `is_problem_client` TINYINT(1) DEFAULT 0 COMMENT '0=адекватный, 1=проблемный'",
                'order_count'        => "ADD COLUMN `order_count` INT UNSIGNED DEFAULT 0 COMMENT 'Счётчик заказов'",
                'status'             => "ADD COLUMN `status` VARCHAR(20) DEFAULT 'new' COMMENT 'Статус клиента: new, regular, permanent'",
            ],
            'myser_orders' => [
                'subject_id' => "ADD COLUMN `subject_id` INT UNSIGNED DEFAULT NULL",
            ],
            'myser_brands' => [
                'description' => "ADD COLUMN `description` TEXT DEFAULT NULL AFTER `name`",
            ],
            'myser_components' => [
                'description' => "ADD COLUMN `description` TEXT DEFAULT NULL AFTER `name`",
            ],
            'myser_departments' => [
                'dep_type' => "ADD COLUMN `dep_type` VARCHAR(20) DEFAULT 'branch' COMMENT 'Тип: head, branch, remote' AFTER `notes`",
                'logo'     => "ADD COLUMN `logo` VARCHAR(500) DEFAULT NULL COMMENT 'Логотип подразделения' AFTER `is_default`",
                'stamp'    => "ADD COLUMN `stamp` VARCHAR(500) DEFAULT NULL COMMENT 'Печать подразделения' AFTER `logo`",
            ],
        ];
        foreach ($alter_columns as $table => $columns) {
            $full_table = $prefix . $table;
            foreach ($columns as $col_name => $alter_sql) {
                $col_exists = $wpdb->get_results($wpdb->prepare(
                    "SHOW COLUMNS FROM `$full_table` LIKE %s",
                    $col_name
                ));
                if (empty($col_exists)) {
                    $wpdb->query("ALTER TABLE `$full_table` $alter_sql");
                }
            }
        }

        // ----------------------------------------------------------------
        // 3. Таблица брендов (создаём, если отсутствует)
        // ----------------------------------------------------------------
        $sql = "CREATE TABLE IF NOT EXISTS {$prefix}myser_brands (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(255) NOT NULL,
            `description` TEXT DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) $charset_collate;";
        dbDelta($sql);

        // ----------------------------------------------------------------
        // 4. Субъекты
        // ----------------------------------------------------------------
        $sql = "CREATE TABLE IF NOT EXISTS {$prefix}myser_subjects (
            `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `subject_type` VARCHAR(50) NOT NULL,
            `last_name` VARCHAR(100) NOT NULL,
            `first_name` VARCHAR(100) NOT NULL,
            `middle_name` VARCHAR(100) DEFAULT NULL,
            `display_name` VARCHAR(255) DEFAULT NULL,
            `short_name` VARCHAR(100) DEFAULT NULL,
            `full_name_without_lastname` VARCHAR(255) DEFAULT NULL,
            `company_name` VARCHAR(255) DEFAULT NULL,
            `number_int` INT(11) DEFAULT NULL,
            `number_float` VARCHAR(50) DEFAULT NULL,
            `birth_date` DATE DEFAULT NULL,
            `email` VARCHAR(255) DEFAULT NULL,
            `mobile_phone` VARCHAR(30) DEFAULT NULL,
            `work_phone` VARCHAR(30) DEFAULT NULL,
            `home_phone` VARCHAR(30) DEFAULT NULL,
            `registration_address` TEXT DEFAULT NULL,
            `real_address` TEXT DEFAULT NULL,
            `passport` TEXT DEFAULT NULL,
            `tax_id` VARCHAR(50) DEFAULT NULL,
            `snils` VARCHAR(50) DEFAULT NULL,
            `notes` TEXT DEFAULT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `subject_type` (`subject_type`),
            KEY `last_name` (`last_name`),
            KEY `email` (`email`),
            KEY `mobile_phone` (`mobile_phone`)
        ) $charset;";
        $wpdb->query($sql);

        // ----------------------------------------------------------------
        // 4. Роли субъектов
        // ----------------------------------------------------------------
        $sql = "CREATE TABLE IF NOT EXISTS {$prefix}myser_subject_roles (
            `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `subject_id` INT(11) UNSIGNED NOT NULL,
            `role` VARCHAR(50) NOT NULL,
            `is_active` TINYINT(1) DEFAULT 1,
            `assigned_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `subject_role` (`subject_id`, `role`),
            KEY `role` (`role`),
            KEY `is_active` (`is_active`)
        ) $charset;";
        $wpdb->query($sql);

        // ----------------------------------------------------------------
        // 5. Справочники
        // ----------------------------------------------------------------

        // Статусы ремонта
        $table = $prefix . 'myser_statuses';
        $wpdb->query("CREATE TABLE IF NOT EXISTS `$table` (
            `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(50) NOT NULL,
            `color` VARCHAR(7) DEFAULT '#6c757d',
            `sort_order` INT(11) DEFAULT 0,
            PRIMARY KEY (`id`),
            UNIQUE KEY `name` (`name`)
        ) $charset");
        $statuses = [
            ['Новый', '#17a2b8'],
            ['В работе', '#ffc107'],
            ['Ожидает запчасти', '#fd7e14'],
            ['Готов', '#28a745'],
            ['Выдан', '#20c997'],
            ['Отменён', '#dc3545']
        ];
        foreach ($statuses as $i => $status) {
            $wpdb->query($wpdb->prepare("INSERT IGNORE INTO `$table` (name, color, sort_order) VALUES (%s, %s, %d)", $status[0], $status[1], $i));
        }

        // Статусы работы
        $table = $prefix . 'myser_work_status';
        $wpdb->query("CREATE TABLE IF NOT EXISTS `$table` (
            `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(50) NOT NULL,
            `sort_order` INT(11) DEFAULT 0,
            PRIMARY KEY (`id`),
            UNIQUE KEY `name` (`name`)
        ) $charset");
        $statuses = ['Работает', 'На больничном', 'Уволен', 'Отпуск', 'Командировка'];
        foreach ($statuses as $i => $status) {
            $wpdb->query($wpdb->prepare("INSERT IGNORE INTO `$table` (name, sort_order) VALUES (%s, %d)", $status, $i));
        }

        // Роли (справочник)
        $table = $prefix . 'myser_roles';
        $wpdb->query("CREATE TABLE IF NOT EXISTS `$table` (
            `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(50) NOT NULL,
            `sort_order` INT(11) DEFAULT 0,
            PRIMARY KEY (`id`),
            UNIQUE KEY `name` (`name`)
        ) $charset");
        $roles = ['Администратор', 'Менеджер', 'Техник', 'Сотрудник', 'Мастер', 'Тех.инженер', 'IT-инженер', 'Бухгалтерия', 'Склад', 'Руководитель'];
        foreach ($roles as $i => $role) {
            $wpdb->query($wpdb->prepare("INSERT IGNORE INTO `$table` (name, sort_order) VALUES (%s, %d)", $role, $i));
        }

        // ----------------------------------------------------------------
        // 6. Подразделения
        // ----------------------------------------------------------------
        $sql = "CREATE TABLE IF NOT EXISTS {$prefix}myser_departments (
            `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `short_name` VARCHAR(100) NOT NULL COMMENT 'Краткое название (Центральный, Тверской)',
            `full_name` VARCHAR(500) DEFAULT NULL COMMENT 'Полное наименование',
            `order_prefix` VARCHAR(10) DEFAULT NULL COMMENT 'Префикс номера заказа',
            `city` VARCHAR(100) DEFAULT NULL,
            `address` TEXT DEFAULT NULL COMMENT 'Юридический адрес',
            `address_fact` TEXT DEFAULT NULL COMMENT 'Фактический адрес',
            `work_phone` VARCHAR(50) DEFAULT NULL,
            `email` VARCHAR(255) DEFAULT NULL,
            `staff_count` INT(11) NOT NULL DEFAULT 0,
            `inn` VARCHAR(20) DEFAULT NULL,
            `kpp` VARCHAR(20) DEFAULT NULL,
            `ogrn` VARCHAR(30) DEFAULT NULL,
            `okpo` VARCHAR(30) DEFAULT NULL,
            `okvd` VARCHAR(100) DEFAULT NULL,
            `bank_account` VARCHAR(50) DEFAULT NULL,
            `bank_name` VARCHAR(255) DEFAULT NULL,
            `bank_corr` VARCHAR(50) DEFAULT NULL,
            `bank_bic` VARCHAR(20) DEFAULT NULL,
            `accountant` VARCHAR(255) DEFAULT NULL,
            `director` VARCHAR(255) DEFAULT NULL COMMENT 'Руководитель (кратко)',
            `director_full` VARCHAR(500) DEFAULT NULL COMMENT 'ФИО руководителя полностью',
            `director_position` VARCHAR(255) DEFAULT NULL,
            `director_vlice` VARCHAR(255) DEFAULT NULL COMMENT 'Действует на основании (в лице)',
            `director_dobased` VARCHAR(255) DEFAULT NULL COMMENT 'На основании документа',
            `notes` TEXT DEFAULT NULL,
            `status` TINYINT(1) DEFAULT 1 COMMENT '1=активно, 0=неактивно',
            `is_default` TINYINT(1) DEFAULT 0 COMMENT '1=головное подразделение по умолчанию',
            `logo` VARCHAR(500) DEFAULT NULL COMMENT 'Логотип подразделения',
            `stamp` VARCHAR(500) DEFAULT NULL COMMENT 'Печать подразделения',
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) $charset;";
        dbDelta($sql);

        // Доп. колонки для departments (если таблица уже существовала без них)
        $dep_table = $prefix . 'myser_departments';
        // staff_count
        $col = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `$dep_table` LIKE %s", 'staff_count'));
        if (empty($col)) {
            $wpdb->query("ALTER TABLE `$dep_table` ADD COLUMN `staff_count` INT(11) NOT NULL DEFAULT 0 AFTER `email`");
        }
        // order_prefix
        $col = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `$dep_table` LIKE %s", 'order_prefix'));
        if (empty($col)) {
            $wpdb->query("ALTER TABLE `$dep_table` ADD COLUMN `order_prefix` VARCHAR(10) DEFAULT NULL COMMENT 'Префикс номера заказа' AFTER `full_name`");
        }
        // dep_type
        $col = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `$dep_table` LIKE %s", 'dep_type'));
        if (empty($col)) {
            $wpdb->query("ALTER TABLE `$dep_table` ADD COLUMN `dep_type` VARCHAR(20) DEFAULT 'branch' COMMENT 'Тип: head, branch, remote' AFTER `notes`");
        }
        // logo (если существует logo_url, переименовать в logo)
        $col = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `$dep_table` LIKE %s", 'logo_url'));
        if (!empty($col)) {
            $wpdb->query("ALTER TABLE `$dep_table` CHANGE COLUMN `logo_url` `logo` VARCHAR(500) DEFAULT NULL COMMENT 'Логотип подразделения'");
        }
        // stamp (если существует stamp_url, переименовать в stamp)
        $col = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `$dep_table` LIKE %s", 'stamp_url'));
        if (!empty($col)) {
            $wpdb->query("ALTER TABLE `$dep_table` CHANGE COLUMN `stamp_url` `stamp` VARCHAR(500) DEFAULT NULL COMMENT 'Печать подразделения'");
        }
        // is_default (если нет — добавить)
        $col = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `$dep_table` LIKE %s", 'is_default'));
        if (empty($col)) {
            $wpdb->query("ALTER TABLE `$dep_table` ADD COLUMN `is_default` TINYINT(1) DEFAULT 0 COMMENT '1=головное подразделение по умолчанию'");
        }
        // logo (если нет ни logo, ни logo_url)
        $col = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `$dep_table` LIKE %s", 'logo'));
        if (empty($col)) {
            $wpdb->query("ALTER TABLE `$dep_table` ADD COLUMN `logo` VARCHAR(500) DEFAULT NULL COMMENT 'Логотип подразделения' AFTER `is_default`");
        }
        // stamp
        $col = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `$dep_table` LIKE %s", 'stamp'));
        if (empty($col)) {
            $wpdb->query("ALTER TABLE `$dep_table` ADD COLUMN `stamp` VARCHAR(500) DEFAULT NULL COMMENT 'Печать подразделения' AFTER `logo`");
        }

        // ----------------------------------------------------------------
        // 7. Бренды (справочник)
        // ----------------------------------------------------------------
        $table = $prefix . 'myser_brands';
        $wpdb->query("CREATE TABLE IF NOT EXISTS `$table` (
            `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(100) NOT NULL,
            `description` TEXT,
            `sort_order` INT(11) DEFAULT 0,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `name` (`name`)
        ) $charset");

        // Добавляем колонку description, если её нет
        $check_column = $wpdb->get_results("SHOW COLUMNS FROM `$table` LIKE 'description'");
        if (empty($check_column)) {
            $wpdb->query("ALTER TABLE `$table` ADD COLUMN `description` TEXT AFTER `name`");
        }

        // Проверяем, есть ли уже данные в таблице брендов
        $brand_count = $wpdb->get_var("SELECT COUNT(*) FROM `$table`");
        if ($brand_count < 20) {
            $brands = [
                'AEG', 'Akai', 'AOC', 'Apple', 'Ardo', 'Ariston', 'Bauknecht', 'BBK',
                'Beko', 'BenQ', 'Bosch', 'BQ', 'Braun', 'Candy', 'Canon', 'Casio',
                'CENTEK', 'Daewoo Electronics', 'Darina', 'Dell', 'DeLonghi', 'Delta',
                'Dexp', 'Digma', 'Dreame', 'Dyson', 'Ecovacs', 'Electrolux', 'Erisson',
                'Gaggenau', 'Galaxy', 'Garlyn', 'Google', 'Gorenje', 'Gorizont', 'Grundig',
                'Haier', 'Hansa', 'Hartens', 'Hi', 'Hisense', 'Hitachi', 'Honor',
                'Horizont', 'Hoover', 'Hotpoint-Ariston', 'Huawei', 'Hyundai', 'Indesit',
                'iRobot', 'Irbis', 'Jura', 'JVC', 'Kenwood', 'Korting', 'Krups',
                'Küppersbusch', 'LEFF', 'Lenovo', 'LG', 'Liebherr', 'Logitech',
                'MARUSYA', 'Midea', 'Miele', 'Moulinex', 'Nespresso', 'Nintendo',
                'Novex', 'Oursson', 'Okean(Океан)', 'Oniks', 'Panasonic', 'Philips',
                'Pioneer', 'Polar', 'Polaris', 'Realme', 'REDMOND', 'Remington',
                'Roborock', 'Rowenta', 'Rolsen', 'Rubin', 'Saeco', 'Samsung',
                'Sber', 'Schaub Lorenz', 'Sharp', 'Siemens', 'Simfer', 'Skyworth',
                'Smeg', 'Sony', 'Supra', 'Tecno', 'Tesler', 'TCL', 'Thomas',
                'Thomson', 'Topdevice', 'Toshiba', 'TP-Link', 'Tricolor(Триколор)',
                'Tuvio', 'Vestel', 'Vitek', 'Vityaz(Витязь)', 'Whirlpool', 'Xiaomi',
                'Yandex', 'Zanussi', 'Аквариус', 'Бирюса', 'Витязь', 'Гарнизон',
                'Кейсберри', 'Лысьва'
            ];
            sort($brands, SORT_STRING | SORT_FLAG_CASE);
            foreach ($brands as $i => $brand) {
                $wpdb->query($wpdb->prepare("INSERT IGNORE INTO `$table` (name, sort_order) VALUES (%s, %d)", $brand, $i));
            }
        }

        // ----------------------------------------------------------------
        // 7.1. Девайсы (справочник типов устройств)
        // ----------------------------------------------------------------
        $table = $prefix . 'myser_devices';
        $wpdb->query("CREATE TABLE IF NOT EXISTS `$table` (
            `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(100) NOT NULL,
            `description` TEXT,
            `sort_order` INT(11) DEFAULT 0,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `name` (`name`)
        ) $charset");

        // Добавляем колонку description, если её нет (без IF NOT EXISTS для совместимости)
        $check_column = $wpdb->get_results("SHOW COLUMNS FROM `$table` LIKE 'description'");
        if (empty($check_column)) {
            $wpdb->query("ALTER TABLE `$table` ADD COLUMN `description` TEXT AFTER `name`");
        }

        // Проверяем, есть ли уже данные в таблице девайсов
        $device_count = $wpdb->get_var("SELECT COUNT(*) FROM `$table`");
        if ($device_count < 10) {
            $devices = [
                'Аэрогриль', 'Блендер', 'Бойлер', 'Варочная панель', 'Вентилятор',
                'Видеодомофон', 'Водонагреватель', 'Вытяжка', 'Гладильная доска',
                'Гладильная система', 'Гриль', 'Духовой шкаф', 'Игровая приставка',
                'Диспоузер', 'Йогуртница', 'Камеры видеонаблюдения', 'Камины электрические',
                'Климатическая техника', 'Книги электронные', 'Комбайны кухонные',
                'Компьютеры', 'Конвекторы', 'Кондиционеры', 'Кофеварки/кофемашины',
                'Кулеры для воды', 'Кухонные весы', 'Кухонные комбайны', 'Кухонные мойки',
                'Массажёры', 'Метеостанции', 'Микроволновые печи (СВЧ)', 'Миксеры',
                'Мониторы', 'Морозильные камеры', 'Мультиварки', 'Ноутбуки',
                'Обогреватели', 'Овощерезки', 'Осушители воздуха', 'Отпариватели',
                'Очистители воздуха', 'Парогенераторы', 'Пароварки', 'Планшет',
                'Плиты', 'Погодные станции', 'Полотенцесушители', 'Посудомоечные машины',
                'Пылесос', 'Радиатор', 'Рисоварки', 'Робот-пылесос', 'Роутер',
                'Системы безопасности', 'Системы умного дома', 'Сканер',
                'Соковыжималки', 'Стиральные машины', 'Су-вид', 'Сушилки для белья',
                'Смартфон', 'Смарт-часы', 'Сушилки для обуви', 'Дегидратор',
                'Стерилизаторы', 'Тепловентиляторы', 'Термопот', 'Тостер',
                'Телевизор', 'Термокастрюли', 'Увлажнители воздуха', 'Умные колонки',
                'Утюги', 'Фены', 'Фильтры для воды', 'Фотоаппарат', 'Фритюрницы',
                'Фонари', 'Хлебопечки', 'Холодильники', 'Цифровые рамки',
                'Чайники', 'Швейные машины', 'Электрические зубные щётки',
                'Электрические плиты', 'Электроинструмент', 'Эпиляторы', 'Яйцеварки'
            ];
            sort($devices, SORT_STRING | SORT_FLAG_CASE);
            foreach ($devices as $i => $device) {
                $wpdb->query($wpdb->prepare("INSERT IGNORE INTO `$table` (name, sort_order) VALUES (%s, %d)", $device, $i));
            }
        }

        // ----------------------------------------------------------------
        // 7.2. Комплектация (справочник комплектующих)
        // ----------------------------------------------------------------
        $table = $prefix . 'myser_components';
        $wpdb->query("CREATE TABLE IF NOT EXISTS `$table` (
            `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(100) NOT NULL,
            `description` TEXT,
            `sort_order` INT(11) DEFAULT 0,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `name` (`name`)
        ) $charset");

        // Добавляем колонку description, если её нет
        $check_column = $wpdb->get_results("SHOW COLUMNS FROM `$table` LIKE 'description'");
        if (empty($check_column)) {
            $wpdb->query("ALTER TABLE `$table` ADD COLUMN `description` TEXT AFTER `name`");
        }

        $components = [
            'Аппарат', 'Сет.шнур', 'ПДУ', 'Ножка(и)',
            'Внешние крепления', 'З/У', 'Упаковка',
            'Гарантийный талон', 'Инструкция'
        ];
        sort($components, SORT_STRING | SORT_FLAG_CASE);
        foreach ($components as $i => $component) {
            $wpdb->query($wpdb->prepare("INSERT IGNORE INTO `$table` (name, sort_order) VALUES (%s, %d)", $component, $i));
        }

        // ----------------------------------------------------------------
        // 7.3. Цвета (справочник цветов)
        // ----------------------------------------------------------------
        $table = $prefix . 'myser_colors';
        $wpdb->query("CREATE TABLE IF NOT EXISTS `$table` (
            `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(100) NOT NULL,
            `color_code` VARCHAR(7) DEFAULT '#000000' COMMENT 'HEX-код цвета',
            `sort_order` INT(11) DEFAULT 0,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `name` (`name`)
        ) $charset");

        // Добавляем колонку color_code, если её нет
        $check_column = $wpdb->get_results("SHOW COLUMNS FROM `$table` LIKE 'color_code'");
        if (empty($check_column)) {
            $wpdb->query("ALTER TABLE `$table` ADD COLUMN `color_code` VARCHAR(7) DEFAULT '#000000' COMMENT 'HEX-код цвета' AFTER `name`");
        }

        // Добавляем базовые цвета
        $colors = [
            ['Белый', '#FFFFFF'],
            ['Черный', '#000000'],
            ['Серый', '#808080'],
            ['Красный', '#FF0000'],
            ['Синий', '#0000FF'],
            ['Зеленый', '#008000'],
            ['Желтый', '#FFFF00'],
            ['Оранжевый', '#FFA500'],
            ['Фиолетовый', '#800080'],
            ['Розовый', '#FFC0CB'],
            ['Коричневый', '#A52A2A'],
            ['Золотой', '#FFD700'],
            ['Серебряный', '#C0C0C0']
        ];
usort($colors, function($a, $b) {
    return strcasecmp($a[0], $b[0]);
});
foreach ($colors as $i => $color) {
    $wpdb->query($wpdb->prepare(
        "INSERT IGNORE INTO `$table` (name, color_code, sort_order) VALUES (%s, %s, %d)",
        $color[0], $color[1], $i
    ));
}
        // ----------------------------------------------------------------
        // 8. Сетки заработка
        // ----------------------------------------------------------------
        $table = $prefix . 'myser_salary_grids';
        $wpdb->query("CREATE TABLE IF NOT EXISTS `$table` (
            `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(255) NOT NULL,
            `percent` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            `sort_order` INT(11) DEFAULT 0,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) $charset");

        $table = $prefix . 'myser_staff_salary_grids';
        $wpdb->query("CREATE TABLE IF NOT EXISTS `$table` (
            `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `staff_id` INT(11) UNSIGNED NOT NULL,
            `grid_id` INT(11) UNSIGNED NOT NULL,
            `condition_type` VARCHAR(50) DEFAULT 'custom',
            `condition_value` VARCHAR(100) DEFAULT NULL,
            `custom_percent` DECIMAL(5,2) DEFAULT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `staff_id` (`staff_id`),
            KEY `grid_id` (`grid_id`)
        ) $charset");

        // Записываем версию схемы
        Database::update_db_version(MYSER_VERSION);
    }
}