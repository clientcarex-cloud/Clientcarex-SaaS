<?php

defined('BASEPATH') or exit('No direct script access allowed');

// Install script for CCX DB module

// Create auto backup settings table
if (!$CI->db->table_exists(db_prefix() . 'ccx_auto_backup_settings')) {
    $CI->db->query("CREATE TABLE IF NOT EXISTS `" . db_prefix() . "ccx_auto_backup_settings` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `tenant_slug` VARCHAR(255) NOT NULL,
        `auto_backup_enabled` TINYINT(1) NOT NULL DEFAULT 1,
        `last_backup_at` DATETIME DEFAULT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `tenant_slug` (`tenant_slug`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ";");
}

// Create auto backup logs table
if (!$CI->db->table_exists(db_prefix() . 'ccx_auto_backup_logs')) {
    $CI->db->query("CREATE TABLE IF NOT EXISTS `" . db_prefix() . "ccx_auto_backup_logs` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `tenant_slug` VARCHAR(255) DEFAULT NULL,
        `action` VARCHAR(100) NOT NULL,
        `method` VARCHAR(50) DEFAULT NULL,
        `status` VARCHAR(20) NOT NULL DEFAULT 'success',
        `message` TEXT DEFAULT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `tenant_slug` (`tenant_slug`),
        KEY `created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ";");
}

// Backup configuration options
add_option('ccx_backup_method', 'ftp');

// FTP settings
add_option('ccx_backup_ftp_host', '');
add_option('ccx_backup_ftp_port', '21');
add_option('ccx_backup_ftp_username', '');
add_option('ccx_backup_ftp_password', '');
add_option('ccx_backup_ftp_path', '/backups');

// Google Drive API settings
add_option('ccx_backup_gdrive_client_id', '');
add_option('ccx_backup_gdrive_client_secret', '');
add_option('ccx_backup_gdrive_refresh_token', '');
add_option('ccx_backup_gdrive_folder_id', '');

// Cron schedule settings
add_option('ccx_backup_cron_frequency', 'daily');
add_option('ccx_backup_cron_schedule', '0 2 * * *');
add_option('ccx_backup_last_cron_run', '');

// Advanced settings
add_option('ccx_backup_retention_days', '30');
add_option('ccx_backup_auto_delete', '1');
add_option('ccx_backup_compression', '1');
add_option('ccx_backup_max_backups', '10');
add_option('ccx_backup_notify_email', '');
add_option('ccx_backup_notify_on_failure', '1');

// Incremental backup settings
add_option('ccx_backup_type', 'full'); // full | incremental
add_option('ccx_backup_full_every_n', '7'); // Force full backup every N incremental runs

// Table checksums for incremental backup
if (!$CI->db->table_exists(db_prefix() . 'ccx_backup_checksums')) {
    $CI->db->query("CREATE TABLE IF NOT EXISTS `" . db_prefix() . "ccx_backup_checksums` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `tenant_slug` VARCHAR(255) NOT NULL,
        `table_name` VARCHAR(255) NOT NULL,
        `row_count` INT(11) NOT NULL DEFAULT 0,
        `checksum` VARCHAR(64) DEFAULT NULL,
        `last_full_backup` DATETIME DEFAULT NULL,
        `incremental_count` INT(11) NOT NULL DEFAULT 0,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `tenant_table` (`tenant_slug`, `table_name`),
        KEY `tenant_slug` (`tenant_slug`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ";");
}
