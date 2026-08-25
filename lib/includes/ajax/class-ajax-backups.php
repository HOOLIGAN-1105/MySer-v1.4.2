<?php
namespace MySer;

defined('ABSPATH') || exit;

/**
 * AJAX-обработчики для бекапов
 *
 * @package MySer
 */
class Backups_Handler extends Ajax_Handler
{
    public static function register_hooks()
    {
        $actions = [
            'myser_export_backup',
            'myser_import_backup',
            'myser_list_backups',
            'myser_delete_backup',
            'myser_download_backup',
            'myser_delete_backups',
            'myser_clean_backups',
        ];
        foreach ($actions as $action) {
            add_action('wp_ajax_' . $action, [self::class, str_replace('myser_', '', $action)]);
        }
    }

    /**
     * Экспорт бекапа
     */
    public static function export_backup()
    {
        self::verify_nonce();
        self::check_permissions();

        $format = sanitize_text_field(($_POST['format'] ?? 'sql'));
        if (!in_array($format, ['sql', 'csv', 'mdb'])) {
            wp_send_json_error(['message' => 'Неверный формат. Доступны: sql, csv, mdb']);
        }

        $backup   = Backup::get();
        $result   = false;
        $filename = '';

        try {
            if ($format === 'sql') {
                $result   = $backup->export_sql();
                $filename = basename($result);
            } else if ($format === 'csv') {
                $result   = $backup->export_csv();
                $filename = basename($result);
            } else if ($format === 'mdb') {
                $result   = $backup->export_mdb();
                $filename = basename($result);
            }

            if ($result) {
                Logger::get()->info('Бекап создан через AJAX', ['format' => $format, 'file' => $filename]);
                wp_send_json_success(
                    [
                        'message'      => 'Бекап создан',
                        'file'         => $filename,
                        'download_url' => admin_url('admin-ajax.php?action=myser_download_backup&file='.urlencode($filename).'&nonce='.wp_create_nonce('myser_download_backup')),
                    ]
                );
            } else {
                wp_send_json_error(['message' => 'Ошибка создания бекапа']);
            }
        } catch (\Exception $e) {
            Logger::get()->error('Ошибка экспорта бекапа', ['error' => $e->getMessage()]);
            wp_send_json_error(['message' => 'Ошибка: '.$e->getMessage()]);
        }//end try

    }//end export_backup()


    /**
     * Импорт бекапа
     */
    public static function import_backup()
    {
        self::verify_nonce();
        self::check_permissions();

        if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(['message' => 'Файл не загружен или произошла ошибка']);
        }

        $file = $_FILES['backup_file'];
        $ext  = pathinfo($file['name'], PATHINFO_EXTENSION);

        if (!in_array($ext, ['sql', 'zip', 'mdb'])) {
            wp_send_json_error(['message' => 'Неподдерживаемый формат. Используйте .sql, .zip или .mdb']);
        }

        $backup     = Backup::get();
        $upload_dir = $backup->get_backup_dir();
        $dest       = $upload_dir.basename($file['name']);

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            Logger::get()->error('Не удалось переместить загруженный файл', ['file' => $file['name']]);
            wp_send_json_error(['message' => 'Не удалось сохранить файл']);
        }

        try {
            $success = false;
            $error_msg = 'Ошибка импорта бекапа';

            if ($ext === 'sql') {
                $result  = $backup->import_sql($dest);
                $success = ($result === true);
                if (is_array($result) && !empty($result['errors'])) {
                    $error_msg = 'Импорт завершён с ошибками: '.implode('; ', $result['errors']);
                }
            } else if ($ext === 'zip') {
                $success = ($backup->import_csv($dest) === true);
            } else if ($ext === 'mdb') {
                $success = ($backup->import_mdb($dest) === true);
            }

            if ($success) {
                Logger::get()->info('Бекап импортирован', ['file' => $file['name']]);
                wp_send_json_success(['message' => 'Бекап успешно импортирован']);
            } else {
                Logger::get()->error('Ошибка импорта бекапа', ['message' => $error_msg]);
                wp_send_json_error(['message' => $error_msg]);
            }
        } catch (\Exception $e) {
            Logger::get()->error('Ошибка импорта бекапа', ['error' => $e->getMessage()]);
            wp_send_json_error(['message' => 'Ошибка: '.$e->getMessage()]);
        }

    }//end import_backup()


    /**
     * Список бекапов
     */
    public static function list_backups()
    {
        self::verify_nonce();
        self::check_permissions();

        $backup = Backup::get();
        $list   = $backup->list_backups();

        wp_send_json_success(
            [
                'items' => $list,
                'total' => count($list),
            ]
        );

    }//end list_backups()


    /**
     * Удаление бекапа
     */
    public static function delete_backup()
    {
        self::verify_nonce();
        self::check_permissions();

        $filename = sanitize_file_name(($_POST['filename'] ?? ''));
        if (empty($filename)) {
            wp_send_json_error(['message' => 'Имя файла не указано']);
        }

        $backup     = Backup::get();
        $backup_dir = $backup->get_backup_dir();
        $file_path  = $backup_dir . $filename;

        if (!file_exists($file_path)) {
            wp_send_json_error(['message' => 'Файл не найден']);
        }

        if (unlink($file_path)) {
            Logger::get()->info('Бекап удалён', ['file' => $filename]);
            wp_send_json_success(['message' => 'Бекап удалён']);
        } else {
            wp_send_json_error(['message' => 'Не удалось удалить файл']);
        }

    }//end delete_backup()


    /**
     * Скачивание бекапа
     */
    public static function download_backup()
    {
        $nonce = $_GET['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'myser_download_backup')) {
            wp_die('Неверный nonce');
        }

        if (!current_user_can('manage_options')) {
            wp_die('Недостаточно прав');
        }

        $filename = sanitize_file_name(($_GET['file'] ?? ''));
        if (empty($filename)) {
            wp_die('Файл не указан');
        }

        $backup     = Backup::get();
        $backup_dir = $backup->get_backup_dir();
        $file_path  = $backup_dir . $filename;

        if (!file_exists($file_path)) {
            wp_die('Файл не найден');
        }

        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($file_path));
        readfile($file_path);
        exit;

    }//end download_backup()


    /**
     * Массовое удаление бекапов
     */
    public static function delete_backups()
    {
        self::verify_nonce();
        self::check_permissions();

        $filenames = $_POST['filenames'] ?? [];
        if (empty($filenames) || !is_array($filenames)) {
            wp_send_json_error(['message' => 'Файлы не указаны']);
        }

        $backup     = Backup::get();
        $backup_dir = $backup->get_backup_dir();
        $deleted    = 0;
        $errors     = [];

        foreach ($filenames as $filename) {
            $filename  = sanitize_file_name($filename);
            $file_path = $backup_dir . $filename;
            if (file_exists($file_path) && unlink($file_path)) {
                $deleted++;
            } else {
                $errors[] = $filename;
            }
        }

        Logger::get()->info('Массовое удаление бекапов', ['deleted' => $deleted, 'errors' => $errors]);
        wp_send_json_success([
            'message' => "Удалено файлов: $deleted",
            'deleted' => $deleted,
            'errors'  => $errors,
        ]);

    }//end delete_backups()

}//end class
