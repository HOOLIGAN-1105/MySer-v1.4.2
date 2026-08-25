<?php
/**
 * Класс для работы с базой данных плагина MySer
 *
 * Предоставляет методы для получения имён таблиц, выполнения запросов,
 * получения записей клиентов, заказов, сотрудников, подразделений,
 * субъектов, ролей, сеток заработка и других сущностей.
 *
 * @package MySer
 */

namespace MySer;

defined('ABSPATH') || exit;

class Database
{
    /**
     * Возвращает массив имён существующих таблиц с префиксом WordPress
     *
     * @return array Ассоциативный массив с ключами: clients, orders, statuses, services, items, staff, order_stock, order_services, departments, subjects, subject_roles, work_status, roles, salary_grids, staff_salary_grids
     */
    public static function get_tables()
    {
        global $wpdb;
        $prefix = $wpdb->prefix;

        // Ассоциативный массив: ключ => имя таблицы
        $all_tables = [
            'clients'                => $prefix . 'myser_clients',
            'orders'                 => $prefix . 'myser_orders',
            'order_items'            => $prefix . 'myser_order_items',
            'services'               => $prefix . 'myser_services',
            'statuses'               => $prefix . 'myser_statuses',
            'payments'               => $prefix . 'myser_payments',
            'departments'            => $prefix . 'myser_departments',
            'staff'                  => $prefix . 'myser_staff',
            'subjects'               => $prefix . 'myser_subjects',
            'subject_roles'          => $prefix . 'myser_subject_roles',
            'settings'               => $prefix . 'myser_settings',
            'device_models'          => $prefix . 'myser_device_models',
            'component_types'        => $prefix . 'myser_component_types',
            'components'             => $prefix . 'myser_components',
            'component_combinations' => $prefix . 'myser_component_combinations',
            'repair_types'           => $prefix . 'myser_repair_types',
            'prices'                 => $prefix . 'myser_prices',
            'order_status_history'   => $prefix . 'myser_order_status_history',
            'calls'                  => $prefix . 'myser_calls',
            'sms'                    => $prefix . 'myser_sms',
            'staff_roles'            => $prefix . 'myser_staff_roles',
            'salary_grids'           => $prefix . 'myser_salary_grids',
            'staff_salary_grids'     => $prefix . 'myser_staff_salary_grids',
            'work_status'            => $prefix . 'myser_work_status',
            'roles'                  => $prefix . 'myser_roles',
            'clients_categories'     => $prefix . 'myser_clients_categories',
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

    /**
     * Возвращает charset/collate для таблиц
     *
     * @return string
     */
    public static function get_charset()
    {
        global $wpdb;
        return $wpdb->get_charset_collate();
    }

    // ========== Общие методы запросов ==========

    /**
     * Выполняет произвольный SQL-запрос с подготовкой параметров
     *
     * @param string $sql    SQL-запрос с плейсхолдерами %d, %s
     * @param array  $params Параметры для подготовки
     * @return int|false Число затронутых строк или false при ошибке
     */
    public static function query($sql, $params = [])
    {
        global $wpdb;
        if (empty($params)) {
            return $wpdb->query($sql);
        } else {
            return $wpdb->query($wpdb->prepare($sql, ...$params));
        }
    }

    /**
     * Возвращает результаты запроса в виде массива объектов
     *
     * @param string $sql    SQL-запрос с плейсхолдерами %d, %s
     * @param array  $params Параметры для подготовки
     * @return array|null Массив объектов или null при ошибке
     */
    public static function get_results($sql, $params = [])
    {
        global $wpdb;
        if (empty($params)) {
            return $wpdb->get_results($sql);
        } else {
            return $wpdb->get_results($wpdb->prepare($sql, ...$params));
        }
    }

    /**
     * Возвращает одно значение из результата запроса
     *
     * @param string $sql    SQL-запрос с плейсхолдерами %d, %s
     * @param array  $params Параметры для подготовки
     * @return string|null Значение или null при ошибке
     */
    public static function get_var($sql, $params = [])
    {
        global $wpdb;
        if (empty($params)) {
            return $wpdb->get_var($sql);
        } else {
            return $wpdb->get_var($wpdb->prepare($sql, ...$params));
        }
    }

    /**
     * Возвращает одну строку из результата запроса
     *
     * @param string $sql    SQL-запрос с плейсхолдерами %d, %s
     * @param array  $params Параметры для подготовки
     * @return object|null Объект строки или null при ошибке
     */
    public static function get_row($sql, $params = [])
    {
        global $wpdb;
        if (empty($params)) {
            return $wpdb->get_row($sql);
        } else {
            return $wpdb->get_row($wpdb->prepare($sql, ...$params));
        }
    }

    /**
     * Возвращает колонку из результата запроса
     *
     * @param string $sql    SQL-запрос с плейсхолдерами %d, %s
     * @param array  $params Параметры для подготовки
     * @return array|null Массив значений колонки или null при ошибке
     */
    public static function get_col($sql, $params = [])
    {
        global $wpdb;
        if (empty($params)) {
            return $wpdb->get_col($sql);
        } else {
            return $wpdb->get_col($wpdb->prepare($sql, ...$params));
        }
    }

    // ========== Получение записей по ID ==========

    /**
     * Получить клиента по ID
     *
     * @param int $id ID клиента
     * @return object|null Объект клиента или null
     */
    public static function get_client($id)
    {
        global $wpdb;
        $tables = self::get_tables();
        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$tables['clients']} WHERE id = %d", $id)
        );
    }

    /**
     * Получить заказ по ID
     *
     * @param int $id ID заказа
     * @return object|null Объект заказа или null
     */
    public static function get_order($id)
    {
        global $wpdb;
        $tables = self::get_tables();
        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$tables['orders']} WHERE id = %d", $id)
        );
    }

    /**
     * Получить сотрудника по ID
     *
     * @param int $id ID сотрудника
     * @return object|null Объект сотрудника или null
     */
    public static function get_staff($id)
    {
        global $wpdb;
        $tables = self::get_tables();
        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$tables['staff']} WHERE id = %d", $id)
        );
    }

    /**
     * Получить подразделение по ID
     *
     * @param int $id ID подразделения
     * @return object|null Объект подразделения или null
     */
    public static function get_department($id)
    {
        global $wpdb;
        $tables = self::get_tables();
        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$tables['departments']} WHERE id = %d", $id)
        );
    }

    /**
     * Получить субъекта по ID
     *
     * @param int $id ID субъекта
     * @return object|null Объект субъекта или null
     */
    public static function get_subject($id)
    {
        global $wpdb;
        $tables = self::get_tables();
        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$tables['subjects']} WHERE id = %d", $id)
        );
    }

    /**
     * Получить сетку заработка по ID
     *
     * @param int $id ID сетки
     * @return object|null Объект сетки или null
     */
    public static function get_salary_grid($id)
    {
        global $wpdb;
        $tables = self::get_tables();
        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$tables['salary_grids']} WHERE id = %d", $id)
        );
    }

    /**
     * Получить назначение сетки сотруднику по ID
     *
     * @param int $id ID назначения
     * @return object|null Объект назначения или null
     */
    public static function get_staff_salary_assignment($id)
    {
        global $wpdb;
        $tables = self::get_tables();
        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$tables['staff_salary_grids']} WHERE id = %d", $id)
        );
    }

    /**
     * Получить статус работы по ID
     *
     * @param int $id ID статуса
     * @return object|null Объект статуса или null
     */
    public static function get_work_status($id)
    {
        global $wpdb;
        $tables = self::get_tables();
        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$tables['work_status']} WHERE id = %d", $id)
        );
    }

    /**
     * Получить роль по ID
     *
     * @param int $id ID роли
     * @return object|null Объект роли или null
     */
    public static function get_role($id)
    {
        global $wpdb;
        $tables = self::get_tables();
        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$tables['roles']} WHERE id = %d", $id)
        );
    }

    // ========== Получение списков ==========

    /**
     * Получить роли субъекта
     *
     * @param int  $subject_id  ID субъекта
     * @param bool $active_only Только активные роли
     * @return array|null Массив объектов ролей или null
     */
    public static function get_subject_roles($subject_id, $active_only = true)
    {
        global $wpdb;
        $tables = self::get_tables();
        $sql = "SELECT * FROM {$tables['subject_roles']} WHERE subject_id = %d";
        if ($active_only) {
            $sql .= " AND is_active = 1";
        }
        return $wpdb->get_results($wpdb->prepare($sql, $subject_id));
    }

    /**
     * Получить названия ролей субъекта (массив строк)
     *
     * @param int $subject_id ID субъекта
     * @return array|null Массив названий ролей или null
     */
    public static function get_subject_role_names($subject_id)
    {
        global $wpdb;
        $tables = self::get_tables();
        return $wpdb->get_col(
            $wpdb->prepare(
                "SELECT role FROM {$tables['subject_roles']} WHERE subject_id = %d AND is_active = 1",
                $subject_id
            )
        );
    }

    /**
     * Получить все назначения сеток сотрудникам
     *
     * @param int|null $staff_id ID сотрудника (если null — все)
     * @return array|null Массив объектов назначений или null
     */
    public static function get_staff_salary_assignments($staff_id = null)
    {
        global $wpdb;
        $tables = self::get_tables();
        $sql = "SELECT a.*, g.name as grid_name, g.percent as grid_percent, s.staff_name 
                FROM {$tables['staff_salary_grids']} a
                LEFT JOIN {$tables['salary_grids']} g ON g.id = a.grid_id
                LEFT JOIN {$tables['staff']} s ON s.id = a.staff_id";
        if ($staff_id) {
            $sql .= $wpdb->prepare(" WHERE a.staff_id = %d", $staff_id);
        }
        $sql .= " ORDER BY s.staff_name ASC, g.sort_order ASC";
        return $wpdb->get_results($sql);
    }

    /**
     * Получить список всех сотрудников (простой список)
     *
     * @param bool $with_short_name Включать короткое имя
     * @return array|null Массив объектов сотрудников или null
     */
    public static function get_staff_list($with_short_name = false)
    {
        global $wpdb;
        $tables = self::get_tables();
        $fields = "id, staff_name" . ($with_short_name ? ", staff_short_name" : "");
        return $wpdb->get_results(
            "SELECT $fields FROM {$tables['staff']} ORDER BY staff_name ASC"
        );
    }

    /**
     * Получить список всех подразделений (простой список)
     *
     * @return array|null Массив объектов подразделений или null
     */
    public static function get_departments_list()
    {
        global $wpdb;
        $tables = self::get_tables();
        return $wpdb->get_results(
            "SELECT id, short_name, full_name, order_prefix, staff_count, dep_type, status 
             FROM {$tables['departments']} 
             ORDER BY short_name ASC"
        );
    }

    /**
     * Получить список всех сеток заработка
     *
     * @return array|null Массив объектов сеток или null
     */
    public static function get_salary_grids_list()
    {
        global $wpdb;
        $tables = self::get_tables();
        return $wpdb->get_results(
            "SELECT * FROM {$tables['salary_grids']} ORDER BY sort_order ASC, id ASC"
        );
    }

    // ========== Версия БД ==========

    /**
     * Получить текущую версию схемы БД
     *
     * @return string Версия (semver)
     */
    public static function get_db_version()
    {
        return get_option('myser_db_version', '1.0.0');
    }

    /**
     * Обновить версию схемы БД
     *
     * @param string $version Новая версия
     */
    public static function update_db_version($version)
    {
        update_option('myser_db_version', $version);
    }

    /**
     * Проверить, нужно ли обновить схему
     *
     * @return bool True если требуется обновление
     */
    public static function needs_upgrade()
    {
        $current = self::get_db_version();
        $target = defined('MYSER_VERSION') ? MYSER_VERSION : '1.0.0';
        return version_compare($current, $target, '<');
    }

    // ========== Удаление таблиц ==========

    /**
     * Удаляет все таблицы плагина
     */
    public static function drop_tables()
    {
        global $wpdb;
        $tables = self::get_tables();
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
    }

    /**
     * Проверяет существование таблицы
     *
     * @param string $table_name Имя таблицы
     * @return bool True если таблица существует
     */
    public static function table_exists($table_name)
    {
        global $wpdb;
        $result = $wpdb->get_var(
            $wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $table_name
            )
        );
        return !empty($result);
    }

    /**
     * Проверяет существование колонки в таблице
     *
     * @param string $table_name  Имя таблицы
     * @param string $column_name Имя колонки
     * @return bool True если колонка существует
     */
    public static function column_exists($table_name, $column_name)
    {
        global $wpdb;
        $result = $wpdb->get_results(
            $wpdb->prepare(
                "SHOW COLUMNS FROM `$table_name` LIKE %s",
                $column_name
            )
        );
        return !empty($result);
    }
}
