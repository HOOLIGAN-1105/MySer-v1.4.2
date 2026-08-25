<?php
namespace MySer;

class Activator {
    public static function activate() {
        // Создаём таблицу departments напрямую
        self::create_departments_table();

        // Остальные таблицы создаём через мигратор
        if (class_exists('\MySer\Migrator')) {
            \MySer\Migrator::run();
        }

        self::add_default_settings();
    }

    public static function create_departments_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'myser_departments';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS `$table_name` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `short_name` VARCHAR(100) NOT NULL,
            `full_name` VARCHAR(255) NOT NULL,
            `dep_type` ENUM('head','branch','remote') NOT NULL DEFAULT 'head',
            `city` VARCHAR(100) DEFAULT NULL,
            `address` TEXT DEFAULT NULL,
            `address_fact` TEXT DEFAULT NULL,
            `work_phone` VARCHAR(20) DEFAULT NULL,
            `email` VARCHAR(255) DEFAULT NULL,
            `inn` VARCHAR(20) DEFAULT NULL,
            `kpp` VARCHAR(20) DEFAULT NULL,
            `ogrn` VARCHAR(20) DEFAULT NULL,
            `okpo` VARCHAR(20) DEFAULT NULL,
            `okvd` VARCHAR(20) DEFAULT NULL,
            `bank_account` VARCHAR(50) DEFAULT NULL,
            `bank_name` VARCHAR(255) DEFAULT NULL,
            `bank_bic` VARCHAR(20) DEFAULT NULL,
            `bank_corr` VARCHAR(50) DEFAULT NULL,
            `director` VARCHAR(100) DEFAULT NULL,
            `director_full` VARCHAR(255) DEFAULT NULL,
            `director_position` VARCHAR(100) DEFAULT NULL,
            `director_based` VARCHAR(255) DEFAULT NULL,
            `accountant` VARCHAR(255) DEFAULT NULL,
            `notes` TEXT DEFAULT NULL,
            `status` TINYINT(1) DEFAULT 1,
            `order_prefix` VARCHAR(10) DEFAULT NULL,
            `staff_count` INT DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    // create_tables() удалён — всё перенесено в Migrator

    public static function add_default_settings() {
        if (!get_option('myser_settings')) {
            add_option('myser_settings', ['items_per_page' => 20]);
        }
    }
}
