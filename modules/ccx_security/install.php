<?php

defined('BASEPATH') or exit('No direct script access allowed');

$CI = &get_instance();

// ─── Table: ccx_security_login_attempts ───
if (!$CI->db->table_exists(db_prefix() . 'ccx_security_login_attempts')) {
    $CI->db->query("CREATE TABLE IF NOT EXISTS `" . db_prefix() . "ccx_security_login_attempts` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `ip_address` VARCHAR(45) NOT NULL,
        `email` VARCHAR(255) DEFAULT NULL,
        `user_agent` TEXT DEFAULT NULL,
        `success` TINYINT(1) NOT NULL DEFAULT 0,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_ip` (`ip_address`),
        KEY `idx_created` (`created_at`),
        KEY `idx_ip_success` (`ip_address`, `success`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ";");
}

// ─── Table: ccx_security_audit_log ───
if (!$CI->db->table_exists(db_prefix() . 'ccx_security_audit_log')) {
    $CI->db->query("CREATE TABLE IF NOT EXISTS `" . db_prefix() . "ccx_security_audit_log` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `event_type` VARCHAR(50) NOT NULL,
        `description` TEXT NOT NULL,
        `ip_address` VARCHAR(45) DEFAULT NULL,
        `user_agent` TEXT DEFAULT NULL,
        `staff_id` INT(11) DEFAULT NULL,
        `tenant_name` VARCHAR(255) DEFAULT NULL,
        `request_uri` VARCHAR(500) DEFAULT NULL,
        `request_method` VARCHAR(10) DEFAULT NULL,
        `severity` ENUM('info','warning','critical') NOT NULL DEFAULT 'info',
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_event_type` (`event_type`),
        KEY `idx_severity` (`severity`),
        KEY `idx_created` (`created_at`),
        KEY `idx_ip` (`ip_address`),
        KEY `idx_staff` (`staff_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ";");
}

// Self-healing: add tenant_name column if missing (for existing installs)
if ($CI->db->table_exists(db_prefix() . 'ccx_security_audit_log')) {
    if (!$CI->db->field_exists('tenant_name', db_prefix() . 'ccx_security_audit_log')) {
        $CI->db->query("ALTER TABLE `" . db_prefix() . "ccx_security_audit_log` ADD COLUMN `tenant_name` VARCHAR(255) DEFAULT NULL AFTER `staff_id`;");
    }
}

// ─── Table: ccx_security_blocked_ips ───
if (!$CI->db->table_exists(db_prefix() . 'ccx_security_blocked_ips')) {
    $CI->db->query("CREATE TABLE IF NOT EXISTS `" . db_prefix() . "ccx_security_blocked_ips` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `ip_address` VARCHAR(45) NOT NULL,
        `reason` VARCHAR(255) NOT NULL DEFAULT 'Brute force protection',
        `blocked_until` DATETIME DEFAULT NULL,
        `is_permanent` TINYINT(1) NOT NULL DEFAULT 0,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `idx_ip_unique` (`ip_address`),
        KEY `idx_blocked_until` (`blocked_until`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ";");
}

// ═══════════════════════════════════════════════════════════════
// ─── NEW ENTERPRISE TABLES ───
// ═══════════════════════════════════════════════════════════════

// ─── Table: ccx_security_2fa_secrets ───
if (!$CI->db->table_exists(db_prefix() . 'ccx_security_2fa_secrets')) {
    $CI->db->query("CREATE TABLE IF NOT EXISTS `" . db_prefix() . "ccx_security_2fa_secrets` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `staff_id` INT(11) NOT NULL,
        `secret` VARCHAR(64) NOT NULL,
        `recovery_codes` TEXT DEFAULT NULL,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `verified_at` DATETIME DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `idx_staff_unique` (`staff_id`),
        KEY `idx_active` (`is_active`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ";");
}

// ─── Table: ccx_security_ip_whitelist ───
if (!$CI->db->table_exists(db_prefix() . 'ccx_security_ip_whitelist')) {
    $CI->db->query("CREATE TABLE IF NOT EXISTS `" . db_prefix() . "ccx_security_ip_whitelist` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `ip_address` VARCHAR(50) NOT NULL,
        `label` VARCHAR(255) DEFAULT NULL,
        `is_cidr` TINYINT(1) NOT NULL DEFAULT 0,
        `added_by` INT(11) DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `idx_ip_unique` (`ip_address`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ";");
}

// ─── Table: ccx_security_password_history ───
if (!$CI->db->table_exists(db_prefix() . 'ccx_security_password_history')) {
    $CI->db->query("CREATE TABLE IF NOT EXISTS `" . db_prefix() . "ccx_security_password_history` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `staff_id` INT(11) NOT NULL,
        `password_hash` VARCHAR(255) NOT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_staff` (`staff_id`),
        KEY `idx_created` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ";");
}

// ─── Table: ccx_security_active_sessions ───
if (!$CI->db->table_exists(db_prefix() . 'ccx_security_active_sessions')) {
    $CI->db->query("CREATE TABLE IF NOT EXISTS `" . db_prefix() . "ccx_security_active_sessions` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `staff_id` INT(11) NOT NULL,
        `session_id` VARCHAR(128) NOT NULL,
        `ip_address` VARCHAR(45) DEFAULT NULL,
        `user_agent` TEXT DEFAULT NULL,
        `ua_hash` VARCHAR(32) DEFAULT NULL,
        `device_info` VARCHAR(255) DEFAULT NULL,
        `geo_city` VARCHAR(100) DEFAULT NULL,
        `geo_region` VARCHAR(100) DEFAULT NULL,
        `geo_country` VARCHAR(100) DEFAULT NULL,
        `geo_isp` VARCHAR(255) DEFAULT NULL,
        `last_activity` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `idx_fingerprint` (`staff_id`, `ip_address`, `ua_hash`),
        KEY `idx_staff` (`staff_id`),
        KEY `idx_last_activity` (`last_activity`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ";");
}

// ─── Self-healing: Add new columns to active_sessions for existing installs ───
if ($CI->db->table_exists(db_prefix() . 'ccx_security_active_sessions')) {
    $sessions_table = db_prefix() . 'ccx_security_active_sessions';

    // Add ua_hash column for browser fingerprinting
    if (!$CI->db->field_exists('ua_hash', $sessions_table)) {
        $CI->db->query("ALTER TABLE `{$sessions_table}` ADD COLUMN `ua_hash` VARCHAR(32) DEFAULT NULL AFTER `user_agent`;");
    }

    // Add geolocation columns
    if (!$CI->db->field_exists('geo_city', $sessions_table)) {
        $CI->db->query("ALTER TABLE `{$sessions_table}` ADD COLUMN `geo_city` VARCHAR(100) DEFAULT NULL AFTER `device_info`;");
    }
    if (!$CI->db->field_exists('geo_region', $sessions_table)) {
        $CI->db->query("ALTER TABLE `{$sessions_table}` ADD COLUMN `geo_region` VARCHAR(100) DEFAULT NULL AFTER `geo_city`;");
    }
    if (!$CI->db->field_exists('geo_country', $sessions_table)) {
        $CI->db->query("ALTER TABLE `{$sessions_table}` ADD COLUMN `geo_country` VARCHAR(100) DEFAULT NULL AFTER `geo_region`;");
    }
    if (!$CI->db->field_exists('geo_isp', $sessions_table)) {
        $CI->db->query("ALTER TABLE `{$sessions_table}` ADD COLUMN `geo_isp` VARCHAR(255) DEFAULT NULL AFTER `geo_country`;");
    }

    // Drop old session_id unique key (sessions now deduplicated by staff+ip+ua_hash)
    $indexes = $CI->db->query("SHOW INDEX FROM `{$sessions_table}` WHERE Key_name = 'idx_session_unique'")->result();
    if (!empty($indexes)) {
        $CI->db->query("ALTER TABLE `{$sessions_table}` DROP INDEX `idx_session_unique`;");
    }

    // Add compound fingerprint unique key if not exists
    $fp_indexes = $CI->db->query("SHOW INDEX FROM `{$sessions_table}` WHERE Key_name = 'idx_fingerprint'")->result();
    if (empty($fp_indexes)) {
        // Clean up duplicates first before adding unique constraint
        $CI->db->query("DELETE t1 FROM `{$sessions_table}` t1 INNER JOIN `{$sessions_table}` t2 WHERE t1.id < t2.id AND t1.staff_id = t2.staff_id AND t1.ip_address = t2.ip_address AND IFNULL(t1.ua_hash,'') = IFNULL(t2.ua_hash,'');");
        $CI->db->query("ALTER TABLE `{$sessions_table}` ADD UNIQUE KEY `idx_fingerprint` (`staff_id`, `ip_address`, `ua_hash`);");
    }
}

// ─── Self-healing: Add password_changed_at to ccx_security_password_history tracking ───
// We store password change dates in our own table to avoid modifying core tblstaff.
// The latest entry in ccx_security_password_history for a staff_id IS the change date.

// ═══════════════════════════════════════════════════════════════
// ─── INSERT DEFAULT SETTINGS INTO tbloptions ───
// ═══════════════════════════════════════════════════════════════

$default_settings = [
    // Master toggles (existing)
    'ccx_security_enabled'                 => '1',
    'ccx_security_http_headers_enabled'    => '1',
    'ccx_security_devtools_block_enabled'  => '0',
    'ccx_security_xss_protection_enabled'  => '1',
    'ccx_security_csrf_hardening_enabled'  => '1',
    'ccx_security_file_upload_scan_enabled' => '1',
    'ccx_security_session_protection_enabled' => '1',
    'ccx_security_brute_force_enabled'     => '1',
    'ccx_security_sql_monitor_enabled'     => '1',
    'ccx_security_audit_log_enabled'       => '1',
    'ccx_security_right_click_block'       => '0',

    // Brute force config (existing)
    'ccx_security_bf_max_attempts'         => '5',
    'ccx_security_bf_lockout_minutes'      => '15',
    'ccx_security_bf_notify_admin'         => '1',

    // Session config (existing)
    'ccx_security_session_timeout_minutes' => '480',
    'ccx_security_concurrent_sessions'     => '0',
    // Fallback session ID rotation interval, only used when the framework's own
    // rotation (config sess_time_to_update) has been switched off. 0 disables it.
    'ccx_security_session_regenerate_seconds' => '300',

    // HTTP header config (existing)
    'ccx_security_hsts_enabled'            => '0',
    'ccx_security_csp_mode'                => 'permissive',
    'ccx_security_x_frame_options'         => 'SAMEORIGIN',
    'ccx_security_referrer_policy'         => 'strict-origin-when-cross-origin',

    // File upload config (existing)
    'ccx_security_blocked_extensions'      => 'php,phtml,php3,php4,php5,php7,phar,sh,bash,exe,bat,cmd,cgi,pl,py,jsp,asp,aspx',
    'ccx_security_max_upload_mb'           => '10',

    // Audit log config (existing)
    'ccx_security_audit_retention_days'    => '90',

    // Anti-inspect message (existing)
    'ccx_security_inspect_message'         => 'Developer tools are disabled for security reasons.',

    // ─── NEW: Two-Factor Authentication ───
    'ccx_security_2fa_enabled'             => '0',
    'ccx_security_2fa_enforce_all'         => '0',

    // ─── NEW: IP Whitelisting ───
    'ccx_security_ip_whitelist_enabled'    => '0',

    // ─── NEW: Password Policy ───
    'ccx_security_password_policy_enabled' => '0',
    'ccx_security_pw_min_length'           => '12',
    'ccx_security_pw_require_upper'        => '1',
    'ccx_security_pw_require_lower'        => '1',
    'ccx_security_pw_require_number'       => '1',
    'ccx_security_pw_require_special'      => '1',
    'ccx_security_pw_expiry_days'          => '90',
    'ccx_security_pw_history_count'        => '5',

    // ─── NEW: Active Sessions ───
    'ccx_security_max_active_sessions'     => '3',
    'ccx_security_session_tracking_enabled' => '0',
];

foreach ($default_settings as $key => $value) {
    // add_option() is idempotent — it only inserts if the option doesn't already exist
    // It also handles the 'autoload' column properly for Perfex's option cache
    add_option($key, $value);
}
