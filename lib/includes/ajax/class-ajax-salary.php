<?php
namespace MySer;

defined('ABSPATH') || exit;

/**
 * AJAX-обработчики для зарплатных сеток
 *
 * @package MySer
 */
class Salary_Handler extends Ajax_Handler
{
    public static function register_hooks()
    {
        $actions = [
            'myser_get_salary_grids',
            'myser_save_salary_grid',
            'myser_delete_salary_grid',
            'myser_get_staff_list',
            'myser_get_staff_assignments',
            'myser_save_staff_assignment',
            'myser_delete_staff_assignment',
        ];
        foreach ($actions as $action) {
            add_action('wp_ajax_' . $action, [self::class, str_replace('myser_', '', $action)]);
        }
    }

    // TODO: перенести get_salary_grids() из ajax-handler.php
    // TODO: перенести save_salary_grid() из ajax-handler.php
    // TODO: перенести delete_salary_grid() из ajax-handler.php
    // TODO: перенести get_staff_list() из ajax-handler.php
    // TODO: перенести get_staff_assignments() из ajax-handler.php
    // TODO: перенести save_staff_assignment() из ajax-handler.php
    // TODO: перенести delete_staff_assignment() из ajax-handler.php
}
