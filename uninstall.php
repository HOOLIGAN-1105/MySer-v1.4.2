<?php
/**
 * Uninstall
 *
 * @package MySer
 */

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Удаляем таблицы
global $wpdb;
$tables = [
    $wpdb->prefix.'myser_clients',
    $wpdb->prefix.'myser_orders',
    $wpdb->prefix.'myser_statuses',
    $wpdb->prefix.'myser_services',
    $wpdb->prefix.'myser_items',
    $wpdb->prefix.'myser_staff',
    $wpdb->prefix.'myser_order_items',
    $wpdb->prefix.'myser_order_services',
    $wpdb->prefix.'myser_subjects',
    $wpdb->prefix.'myser_subject_roles',
    $wpdb->prefix.'myser_roles',
    $wpdb->prefix.'myser_work_status',
    $wpdb->prefix.'myser_departments',
    $wpdb->prefix.'myser_salary_grids',
    $wpdb->prefix.'myser_staff_salary_grids',
    // Справочники
    $wpdb->prefix.'myser_brands',
    $wpdb->prefix.'myser_devices',
    $wpdb->prefix.'myser_components',
    $wpdb->prefix.'myser_colors',
];

foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS $table");
}

// Удаляем опции
delete_option('myser_settings');
delete_option('myser_version');
delete_option('myser_db_version');
