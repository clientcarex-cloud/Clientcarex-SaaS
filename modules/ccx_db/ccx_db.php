<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: CCX DB
Description: Database Management and Comparison Tool
Version: 1.0.0
Requires at least: 2.3.*
*/

define('CCX_DB_MODULE_NAME', 'ccx_db');

$CI = &get_instance();

/**
 * Encrypt a backup credential for storage. Uses the CRM encryption library
 * (key = APP_ENC_KEY from app-config, NOT stored in the database), so a
 * database-only compromise cannot reveal the credentials.
 * Encrypted values are prefixed with "ccxenc:" so they are detectable on read.
 */
function ccx_db_encrypt_secret($value)
{
    if ($value === null || $value === '') {
        return '';
    }
    $CI = &get_instance();
    $CI->load->library('encryption');
    return 'ccxenc:' . $CI->encryption->encrypt($value);
}

/**
 * Read + decrypt a backup credential option.
 * Legacy plaintext values are transparently re-saved encrypted on first read.
 */
function ccx_db_get_secret($option_name)
{
    $raw = get_option($option_name);
    if ($raw === '' || $raw === null || $raw === false) {
        return '';
    }
    if (strpos($raw, 'ccxenc:') === 0) {
        $CI = &get_instance();
        $CI->load->library('encryption');
        $decrypted = $CI->encryption->decrypt(substr($raw, strlen('ccxenc:')));
        return $decrypted === false ? '' : $decrypted;
    }
    // Legacy plaintext value — migrate to encrypted storage
    update_option($option_name, ccx_db_encrypt_secret($raw));
    return $raw;
}

/**
 * Register activation module hook
 */
register_activation_hook(CCX_DB_MODULE_NAME, 'ccx_db_activation_hook');

function ccx_db_activation_hook()
{
    $CI = &get_instance();
    require_once(__DIR__ . '/install.php');
}

/**
 * Register language files, must be registered if the module is using languages
 */
register_language_files(CCX_DB_MODULE_NAME, [CCX_DB_MODULE_NAME]);

/**
 * Init module menu items in setup in admin_init hook
 */
hooks()->add_action('admin_init', 'ccx_db_init_menu_items');

function ccx_db_init_menu_items()
{
    $CI = &get_instance();

    if (has_permission('settings', '', 'view')) {
        $CI->app_menu->add_setup_menu_item('ccx-db-options', [
            'name' => 'CCX DB',
            'href' => admin_url('ccx_db'),
            'position' => 65,
        ]);
    }
}

/**
 * Register cron job for auto backups
 */
hooks()->add_action('after_cron_run', 'ccx_db_auto_backup_cron');

// Override Perfex cron throttle from 300s (5min) to 60s (1min)
hooks()->add_filter('cron_functions_execute_seconds', function () {
    return 60;
});

/**
 * Destination folder for backup files, structured per day and backup type:
 *   12-July-2026-CCX-Backups/Full Backup
 *   12-July-2026-CCX-Backups/Incremental Backups
 * Used by both the cron and the admin controller (manual/queue backups).
 */
function ccx_db_backup_run_folder($backup_type = 'full')
{
    $main = date('d-F-Y') . '-CCX-Backups';
    $sub = ($backup_type === 'incremental') ? 'Incremental Backups' : 'Full Backup';
    return $main . '/' . $sub;
}

/**
 * Cheap per-table change signatures, read from information_schema in one query.
 *
 * Replaces a per-table CHECKSUM TABLE + SELECT COUNT(*) loop (two full table scans
 * each). Returns the same shape the caller expects:
 *   [ table_name => ['checksum' => <signature>, 'row_count' => <approx rows>] ]
 *
 * A table is considered changed when its signature differs from the stored one.
 * UPDATE_TIME is the real signal; AUTO_INCREMENT and DATA_LENGTH catch the cases
 * where a storage engine reports a stale UPDATE_TIME. If UPDATE_TIME is unavailable
 * the signature is made unique so the table is always dumped (fail safe, not silent).
 *
 * @param object $db          CI database instance for the tenant/master connection
 * @param array  $table_names
 * @return array
 */
function ccx_db_table_signatures($db, array $table_names)
{
    $signatures = [];
    if (empty($table_names)) {
        return $signatures;
    }

    $escaped = [];
    foreach ($table_names as $tname) {
        $escaped[] = $db->escape($tname);
    }

    $rows = [];
    try {
        $rows = $db->query(
            "SELECT TABLE_NAME, TABLE_ROWS, UPDATE_TIME, CREATE_TIME, AUTO_INCREMENT, DATA_LENGTH, INDEX_LENGTH
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (" . implode(',', $escaped) . ")"
        )->result();
    } catch (\Throwable $e) {
        log_message('error', '[CCX Backup] Signature query failed, falling back to full dump: ' . $e->getMessage());
        $rows = [];
    }

    $meta = [];
    foreach ($rows as $r) {
        $meta[$r->TABLE_NAME] = $r;
    }

    foreach ($table_names as $tname) {
        if (!isset($meta[$tname]) || $meta[$tname]->UPDATE_TIME === null) {
            // No metadata (or engine reports no write time) → always treat as changed
            $signatures[$tname] = [
                'checksum'  => 'unknown-' . microtime(true) . '-' . $tname,
                'row_count' => isset($meta[$tname]) ? (int) $meta[$tname]->TABLE_ROWS : 0,
            ];
            continue;
        }

        $m = $meta[$tname];
        $signatures[$tname] = [
            'checksum' => md5(implode('|', [
                (string) $m->UPDATE_TIME,
                (string) $m->CREATE_TIME,
                (string) $m->AUTO_INCREMENT,
                (string) $m->DATA_LENGTH,
                (string) $m->INDEX_LENGTH,
            ])),
            'row_count' => (int) $m->TABLE_ROWS,
        ];
    }

    return $signatures;
}

/**
 * Prune tblccx_auto_backup_logs.
 *
 * This replaces an earlier COUNT(*) + SELECT * + one-DELETE-per-row loop. The table
 * has no index on `action`, so each cron tick full-scanned it twice (128 MB / 68k rows
 * here), sorted the TEXT `message` column on disk, and then issued one DELETE per row.
 * With the cron throttle lowered to 60s that ran essentially non-stop and was, on its
 * own, thousands of queries per second against the master database.
 *
 * Everything below is index-driven and bounded: at most a couple of dozen statements.
 *
 * @param object|null $CI
 * @param int $keep_heartbeats how many heartbeat rows to retain
 * @param int $keep_days       retention for real backup log rows
 */
function ccx_db_prune_backup_logs($CI = null, $keep_heartbeats = 50, $keep_days = 30)
{
    $CI    = $CI ?: get_instance();
    $table = db_prefix() . 'ccx_auto_backup_logs';

    if (!$CI->db->table_exists($table)) {
        return;
    }

    // Self-heal: without this index every read of the table is a full scan
    $has_index = false;
    foreach ($CI->db->query("SHOW INDEX FROM `$table`")->result() as $idx) {
        if ($idx->Key_name === 'action_id') {
            $has_index = true;
            break;
        }
    }
    if (!$has_index) {
        $CI->db->query("ALTER TABLE `$table` ADD INDEX `action_id` (`action`, `id`)");
        log_message('error', '[CCX Backup] Added missing index action_id on ' . $table);
    }

    // Keep only the newest N heartbeat rows: one indexed lookup for the cut-off id,
    // then batched deletes so a large backlog never becomes one huge transaction.
    $cutoff = $CI->db->query(
        "SELECT `id` FROM `$table` WHERE `action` = 'cron_heartbeat'
         ORDER BY `id` DESC LIMIT 1 OFFSET " . (int) $keep_heartbeats
    )->row();

    if ($cutoff) {
        for ($i = 0; $i < 20; $i++) {
            $CI->db->query(
                "DELETE FROM `$table` WHERE `action` = 'cron_heartbeat' AND `id` <= " . (int) $cutoff->id . " LIMIT 5000"
            );
            if ($CI->db->affected_rows() < 5000) {
                break;
            }
        }
    }

    // Retention for the actual backup log rows (these had no cleanup at all)
    $CI->db->query(
        "DELETE FROM `$table`
         WHERE `action` <> 'cron_heartbeat'
           AND `created_at` < DATE_SUB(NOW(), INTERVAL " . (int) $keep_days . " DAY)
         LIMIT 5000"
    );
}

function ccx_db_auto_backup_cron()
{
    $CI = &get_instance();

    // Ensure SaaS helpers are available in cron context
    $CI->load->helper('perfex_saas/perfex_saas');

    @ini_set('memory_limit', '-1');
    @set_time_limit(0);

    // Check if backup settings table exists
    if (!$CI->db->table_exists(db_prefix() . 'ccx_auto_backup_settings')) {
        return;
    }

    $method = get_option('ccx_backup_method') ?: 'ftp';

    // Self-heal: ensure the Master DB row exists so it is always part of backups
    $master_exists = $CI->db->where('tenant_slug', 'master')
        ->count_all_results(db_prefix() . 'ccx_auto_backup_settings');
    if (!$master_exists) {
        $CI->db->insert(db_prefix() . 'ccx_auto_backup_settings', [
            'tenant_slug' => 'master',
            'auto_backup_enabled' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        log_message('error', '[CCX Backup] Self-healed Master DB auto-backup row (enabled)');
    }

    // Get all enabled tenants (Master DB first, so its backup is never starved by tenant runs)
    $tenants = $CI->db->where('auto_backup_enabled', 1)
        ->order_by("tenant_slug = 'master'", 'DESC', false)
        ->order_by('id', 'ASC')
        ->get(db_prefix() . 'ccx_auto_backup_settings')
        ->result();

    if (empty($tenants)) {
        return;
    }

    // Ensure logs table exists
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
    } else {
        // Self-heal: legacy ENUM('success','error') can't store the 'skipped' status
        $status_col = $CI->db->query("SHOW COLUMNS FROM `" . db_prefix() . "ccx_auto_backup_logs` LIKE 'status'")->row();
        if ($status_col && stripos($status_col->Type, 'enum') !== false) {
            $CI->db->query("ALTER TABLE `" . db_prefix() . "ccx_auto_backup_logs` MODIFY `status` VARCHAR(20) NOT NULL DEFAULT 'success'");
        }
    }

    $now = time();

    // ── Self-heal: ensure new dual-schedule options exist (migration from old single-schedule) ──
    $old_schedule = get_option('ccx_backup_cron_schedule');
    if (!empty($old_schedule) && empty(get_option('ccx_backup_incr_cron_schedule'))) {
        // Migrate: old single schedule becomes the incremental schedule
        update_option('ccx_backup_incr_cron_schedule', '* * * * *');
        add_option('ccx_backup_incr_cron_schedule', '* * * * *');
        update_option('ccx_backup_incr_cron_frequency', 'every_minute');
        add_option('ccx_backup_incr_cron_frequency', 'every_minute');
        log_message('error', '[CCX Backup] Migrated old schedule to dual-schedule. Incremental: * * * * *');
    }
    if (empty(get_option('ccx_backup_full_cron_schedule'))) {
        update_option('ccx_backup_full_cron_schedule', '0 2 * * *');
        add_option('ccx_backup_full_cron_schedule', '0 2 * * *');
        update_option('ccx_backup_full_cron_frequency', 'daily');
        add_option('ccx_backup_full_cron_frequency', 'daily');
        log_message('error', '[CCX Backup] Self-healed full schedule: 0 2 * * *');
    }

    // ── Evaluate INCREMENTAL schedule ──
    $incr_schedule = get_option('ccx_backup_incr_cron_schedule') ?: '* * * * *';
    $last_incr_run = get_option('ccx_backup_last_incr_run');
    $run_incr = !empty($incr_schedule) && ccx_db_should_run_cron($incr_schedule, $last_incr_run, $now);

    // ── Evaluate FULL schedule ──
    $full_schedule = get_option('ccx_backup_full_cron_schedule') ?: '0 2 * * *';
    $last_full_run = get_option('ccx_backup_last_full_run');
    $run_full = !empty($full_schedule) && ccx_db_should_run_cron($full_schedule, $last_full_run, $now);

    // ── Diagnostic heartbeat log (logs every cron invocation to DB for debugging) ──
    $heartbeat_msg = sprintf(
        'Cron heartbeat | incr_schedule=%s last_incr=%s run_incr=%s | full_schedule=%s last_full=%s run_full=%s | now=%s',
        $incr_schedule,
        $last_incr_run ?: 'NEVER',
        $run_incr ? 'YES' : 'NO',
        $full_schedule,
        $last_full_run ?: 'NEVER',
        $run_full ? 'YES' : 'NO',
        date('Y-m-d H:i:s', $now)
    );
    log_message('error', '[CCX Backup] ' . $heartbeat_msg);

    // Log heartbeat to DB (keep only latest to avoid bloat — upsert approach)
    $CI->db->insert(db_prefix() . 'ccx_auto_backup_logs', [
        'tenant_slug' => null,
        'action' => 'cron_heartbeat',
        'method' => $method,
        'status' => ($run_incr || $run_full) ? 'success' : 'error',
        'message' => $heartbeat_msg,
        'created_at' => date('Y-m-d H:i:s', $now),
    ]);

    // Auto-cleanup old heartbeat logs (keep only last 50) + retention for backup rows
    ccx_db_prune_backup_logs($CI);

    if (!$run_incr && !$run_full) {
        return;
    }

    // Determine which backup types to execute this tick
    $runs_to_execute = [];
    if ($run_incr) {
        update_option('ccx_backup_last_incr_run', date('Y-m-d H:i:s', $now));
        add_option('ccx_backup_last_incr_run', date('Y-m-d H:i:s', $now));
        $runs_to_execute[] = [
            'type' => 'incremental',
            'folder' => ccx_db_backup_run_folder('incremental'),
        ];
        log_message('error', '[CCX Backup] Incremental backup schedule triggered');
    }
    if ($run_full) {
        update_option('ccx_backup_last_full_run', date('Y-m-d H:i:s', $now));
        add_option('ccx_backup_last_full_run', date('Y-m-d H:i:s', $now));
        $runs_to_execute[] = [
            'type' => 'full',
            'folder' => ccx_db_backup_run_folder('full'),
        ];
        log_message('error', '[CCX Backup] Full backup schedule triggered');
    }

    foreach ($runs_to_execute as $run) {
        $backup_type = $run['type'];
        $run_folder  = $run['folder'];
        $run_results = [];

        log_message('error', '[CCX Backup] Cron run folder: ' . $run_folder . ' | type: ' . $backup_type);

        foreach ($tenants as $tenant) {
            $slug = $tenant->tenant_slug;

            try {
                // Execute backup with explicit type
                $result = ccx_db_execute_tenant_backup($CI, $slug, $method, $run_folder, $backup_type);

                if ($result['success']) {
                    $CI->db->where('tenant_slug', $slug)
                        ->update(db_prefix() . 'ccx_auto_backup_settings', [
                            'last_backup_at' => date('Y-m-d H:i:s'),
                            'updated_at' => date('Y-m-d H:i:s'),
                        ]);
                }

                // Deleted tenants log as 'skipped' (never 'error') so they don't trigger failure alerts
                $log_status = !empty($result['skipped']) ? 'skipped' : ($result['success'] ? 'success' : 'error');

                $log_message = $result['message'];
                if (!empty($result['steps'])) {
                    $log_message .= "\n--- Debug Steps ---\n" . implode("\n", $result['steps']);
                }
                $CI->db->insert(db_prefix() . 'ccx_auto_backup_logs', [
                    'tenant_slug' => $slug,
                    'action' => 'auto_backup',
                    'method' => $method,
                    'status' => $log_status,
                    'message' => $log_message,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);

                $run_results[] = ['slug' => $slug, 'status' => $log_status, 'message' => $result['message']];

            } catch (Exception $e) {
                $CI->db->insert(db_prefix() . 'ccx_auto_backup_logs', [
                    'tenant_slug' => $slug,
                    'action' => 'auto_backup',
                    'method' => $method,
                    'status' => 'error',
                    'message' => 'Cron backup exception for ' . $slug . ': ' . $e->getMessage(),
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
                $run_results[] = ['slug' => $slug, 'status' => 'error', 'message' => 'Exception: ' . $e->getMessage()];
            }
        }

        // Success summary email — FULL runs only (incrementals can fire every minute and would spam)
        if ($backup_type === 'full') {
            ccx_db_send_success_notification($CI, $backup_type, $run_folder, $method, $run_results);
        }
    }

    // Run auto-cleanup of old backups
    ccx_db_auto_cleanup_old_backups($CI, $method);

    // Send email notification on failure if enabled
    ccx_db_send_failure_notification($CI);
}

/**
 * Execute backup for a single tenant (used by cron)
 * Returns detailed step-by-step logs for debugging.
 * Supports full and incremental backups.
 * @param string $backup_type 'full' or 'incremental' — passed explicitly by cron
 */
function ccx_db_execute_tenant_backup($CI, $slug, $method, $run_folder = null, $backup_type = 'full')
{
    $steps = []; // Collect step-by-step debug info

    $is_incremental = ($backup_type === 'incremental');

    $steps[] = '[' . date('H:i:s') . '] Backup type: ' . $backup_type;
    log_message('error', '[CCX Backup] Backup type: ' . $backup_type);

    // Self-heal: ensure checksums table exists
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
        $steps[] = '[' . date('H:i:s') . '] Created checksums table (first run)';
    }

    // For incremental: if no prior checksums exist, force full to establish baseline
    if ($is_incremental) {
        $inc_row = $CI->db->select('incremental_count')
            ->where('tenant_slug', $slug)
            ->limit(1)
            ->get(db_prefix() . 'ccx_backup_checksums')
            ->row();

        if (!$inc_row) {
            $is_incremental = false;
            $steps[] = '[' . date('H:i:s') . '] No prior checksums found — forcing FULL backup to establish baseline';
        } else {
            $steps[] = '[' . date('H:i:s') . '] Proceeding with INCREMENTAL backup';
        }
    }

    $actual_type = $is_incremental ? 'incremental' : 'full';

    // Destination folder always follows the ACTUAL type — an incremental request is
    // upgraded to full for the baseline run, and its file must land in "Full Backup"
    $run_folder = ccx_db_backup_run_folder($actual_type);

    $steps[] = '[' . date('H:i:s') . '] START ' . strtoupper($actual_type) . ' backup for tenant: ' . $slug . ' | method: ' . $method;
    log_message('error', '[CCX Backup] START ' . $actual_type . ' backup for tenant: ' . $slug . ' | method: ' . $method);

    // ── Master DB: uses the default connection, no company/DSN lookup ──
    if ($slug === 'master') {
        $db = $CI->db;
        // Only master-prefixed tables (tbl%) — tenant tables that may share the same
        // physical database use "{slug}_tbl" prefixes and must not be duplicated here
        $prefix = db_prefix();
        $steps[] = '[' . date('H:i:s') . '] Master DB backup — database: ' . $db->database . ' | prefix: ' . $prefix;
        log_message('error', '[CCX Backup] Master DB backup — database: ' . $db->database);
    } else {

    // Load the perfex_saas model to get DB connection info
    if (!class_exists('Perfex_saas_model')) {
        $CI->load->model('perfex_saas/perfex_saas_model');
        $steps[] = '[' . date('H:i:s') . '] Loaded Perfex_saas_model';
    }

    $company = $CI->perfex_saas_model->get_company_by_slug($slug);
    if (!$company) {
        // Tenant no longer exists (deleted) — disable its auto backup so it stops
        // producing errors and failure alert emails on every run
        $CI->db->where('tenant_slug', $slug)
            ->update(db_prefix() . 'ccx_auto_backup_settings', [
                'auto_backup_enabled' => 0,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        $steps[] = '[' . date('H:i:s') . '] Company not found for slug: ' . $slug . ' — tenant appears deleted, auto backup disabled';
        log_message('error', '[CCX Backup] Company not found for slug: ' . $slug . ' — auto backup disabled');
        return ['success' => false, 'skipped' => true, 'message' => 'Company not found: ' . $slug . ' — tenant appears deleted, auto backup has been disabled', 'steps' => $steps];
    }
    $steps[] = '[' . date('H:i:s') . '] Company found: ' . $company->name . ' (slug: ' . $company->slug . ', id: ' . $company->id . ')';
    log_message('error', '[CCX Backup] Company found: ' . $company->name . ' | slug: ' . $company->slug);

    // Use the same DSN approach as the controller's _get_db_connection()
    try {
        $steps[] = '[' . date('H:i:s') . '] Resolving DSN via perfex_saas_get_company_dsn()...';
        $dsn = perfex_saas_get_company_dsn($company);
        $steps[] = '[' . date('H:i:s') . '] DSN resolved — host: ' . ($dsn['host'] ?? 'N/A') . ' | dbname: ' . ($dsn['dbname'] ?? 'N/A');
        log_message('error', '[CCX Backup] DSN resolved — host: ' . ($dsn['host'] ?? 'N/A') . ' | dbname: ' . ($dsn['dbname'] ?? 'N/A'));

        // Strip healtho_ps_ from slug for prefix generation to avoid double prefixing
        $clean_slug = str_replace('healtho_ps_', '', $company->slug);
        $prefix = perfex_saas_tenant_db_prefix($clean_slug);
        $steps[] = '[' . date('H:i:s') . '] DB prefix: ' . $prefix . ' (clean_slug: ' . $clean_slug . ')';
        log_message('error', '[CCX Backup] DB prefix: ' . $prefix . ' | clean_slug: ' . $clean_slug);

        $steps[] = '[' . date('H:i:s') . '] Connecting to tenant DB...';
        $db = perfex_saas_load_ci_db_from_dsn($dsn, ['dbprefix' => $prefix]);

        if (!$db || !$db->conn_id) {
            $steps[] = '[' . date('H:i:s') . '] ERROR: DB connection returned null or no conn_id';
            log_message('error', '[CCX Backup] DB connection failed for ' . $slug . ' — conn object: ' . ($db ? 'exists' : 'null') . ' | conn_id: ' . ($db && $db->conn_id ? 'yes' : 'no'));
            return ['success' => false, 'message' => 'DB connection failed for ' . $slug, 'steps' => $steps];
        }
        $steps[] = '[' . date('H:i:s') . '] DB connected — database: ' . $db->database;
        log_message('error', '[CCX Backup] DB connected for ' . $slug . ' — database: ' . $db->database);

    } catch (\Throwable $e) {
        $steps[] = '[' . date('H:i:s') . '] EXCEPTION during DB connection: ' . $e->getMessage();
        log_message('error', '[CCX Backup] DB exception for ' . $slug . ': ' . $e->getMessage());
        return ['success' => false, 'message' => 'DB connection exception for ' . $slug . ': ' . $e->getMessage(), 'steps' => $steps];
    }

    } // end master/tenant connection branch

    // Get all tables matching the prefix (master: tbl%, tenant: {slug}_tbl%)
    $tables_query = $db->query("SHOW TABLES LIKE '{$prefix}%'");
    $all_tables = $tables_query->result_array();
    $all_table_names = [];
    foreach ($all_tables as $t) {
        $all_table_names[] = array_values($t)[0];
    }
    $steps[] = '[' . date('H:i:s') . '] Found ' . count($all_table_names) . ' tables with prefix "' . $prefix . '"';
    log_message('error', '[CCX Backup] ' . $slug . ': Found ' . count($all_table_names) . ' tables');

    if (count($all_table_names) === 0) {
        if ($slug !== 'master') {
            $db->close();
        }
        $steps[] = '[' . date('H:i:s') . '] ERROR: No tables found! Check prefix or DB connection.';
        return ['success' => false, 'message' => 'No tables found for ' . $slug . ' with prefix "' . $prefix . '"', 'steps' => $steps];
    }

    // Compute a change signature for every table.
    //
    // This used to run `CHECKSUM TABLE` + `SELECT COUNT(*)` per table. Both are full
    // table scans, and they ran for every table of every tenant on every tick — with
    // the incremental schedule at * * * * * that meant continuously reading the entire
    // dataset of the whole platform. It pushed ~1000 queries/second, tens of millions
    // of scanned rows per minute, and (worst of all) flushed every user's data out of
    // the InnoDB buffer pool, so ordinary CRM pages were permanently reading from disk.
    //
    // information_schema gives the same "has this table changed?" answer from metadata
    // for the price of ONE query per database. UPDATE_TIME is the authoritative signal;
    // when it is NULL (InnoDB reports NULL until the table is written to after a server
    // restart) the table is treated as changed, which is the safe direction. The daily
    // FULL backup remains the backstop for the 1-second granularity of UPDATE_TIME.
    $current_checksums = ccx_db_table_signatures($db, $all_table_names);

    // Determine which tables to dump
    $tables_to_dump = $all_table_names; // Default: all (full backup)
    $skipped_tables = 0;

    if ($is_incremental) {
        // Load stored checksums for this tenant
        $stored = $CI->db->where('tenant_slug', $slug)
            ->get(db_prefix() . 'ccx_backup_checksums')
            ->result();

        $stored_map = [];
        foreach ($stored as $s) {
            $stored_map[$s->table_name] = [
                'checksum' => $s->checksum,
                'row_count' => (int)$s->row_count,
            ];
        }

        // Filter to only changed tables
        $tables_to_dump = [];
        foreach ($all_table_names as $tname) {
            $cur = $current_checksums[$tname];
            $old = isset($stored_map[$tname]) ? $stored_map[$tname] : null;

            // Compare the signature only. row_count now comes from information_schema
            // and is an *estimate* for InnoDB — comparing it would mark tables as
            // changed whenever the optimiser refreshes its statistics. The signature
            // already folds in write time, size and auto-increment.
            if (!$old || $cur['checksum'] !== $old['checksum']) {
                $tables_to_dump[] = $tname;
            } else {
                $skipped_tables++;
            }
        }

        $steps[] = '[' . date('H:i:s') . '] INCREMENTAL: ' . count($tables_to_dump) . ' changed tables, ' . $skipped_tables . ' unchanged (skipped)';
        log_message('error', '[CCX Backup] ' . $slug . ': Incremental — ' . count($tables_to_dump) . ' changed, ' . $skipped_tables . ' skipped');

        if (empty($tables_to_dump)) {
            if ($slug !== 'master') {
                $db->close();
            }
            // Update incremental counter
            $CI->db->where('tenant_slug', $slug)
                ->set('incremental_count', 'incremental_count + 1', false)
                ->update(db_prefix() . 'ccx_backup_checksums');

            $steps[] = '[' . date('H:i:s') . '] No changes detected — skipping backup for ' . $slug;
            log_message('error', '[CCX Backup] ' . $slug . ': No changes, backup skipped');
            return ['success' => true, 'message' => 'No changes detected for ' . $slug . ' — backup skipped', 'steps' => $steps];
        }
    }

    // Generate SQL dump to temp file. The day folder is shared by every run of that
    // day, so the timestamp in the filename is what keeps runs from overwriting each other
    $filename = 'ccx_db_' . $slug . '_' . date('Y-m-d_H-i-s') . '.sql';
    $tmp_file = tempnam(sys_get_temp_dir(), 'ccx_cron_dump_');
    $handle = fopen($tmp_file, 'w');

    if (!$handle) {
        $steps[] = '[' . date('H:i:s') . '] ERROR: Could not create temp file: ' . $tmp_file;
        log_message('error', '[CCX Backup] Could not create temp file for ' . $slug);
        return ['success' => false, 'message' => 'Could not create temp file for ' . $slug, 'steps' => $steps];
    }
    $steps[] = '[' . date('H:i:s') . '] Temp file created: ' . $tmp_file . ' | Filename: ' . $filename;

    fwrite($handle, "-- Database " . strtoupper($actual_type) . " dump for: {$slug}\n");
    fwrite($handle, "-- Generated by CCX Auto Backup Cron: " . date('c') . "\n");
    fwrite($handle, "-- Type: " . strtoupper($actual_type) . " | Tables: " . count($tables_to_dump) . "/" . count($all_table_names) . "\n\n");
    fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n\n");

    $total_rows = 0;
    $table_count = count($tables_to_dump);
    foreach ($tables_to_dump as $table_name) {

        // Get CREATE TABLE
        $q = $db->query("SHOW CREATE TABLE `{$table_name}`");
        $row = $q->row_array();
        $create_sql = $row['Create Table'] ?? null;
        if ($create_sql) {
            fwrite($handle, "DROP TABLE IF EXISTS `{$table_name}`;\n");
            fwrite($handle, $create_sql . ";\n\n");
        }

        // Dump data
        $batch = 1000;
        $offset = 0;
        $table_rows = 0;

        // Get columns
        $col_result = $db->query("SHOW COLUMNS FROM `{$table_name}`")->result_array();
        $cols = array_column($col_result, 'Field');
        if (empty($cols)) continue;
        $cols_list = '`' . implode('`, `', $cols) . '`';

        do {
            $res = $db->query("SELECT {$cols_list} FROM `{$table_name}` LIMIT {$batch} OFFSET {$offset}");
            $rows = $res->result_array();
            if (empty($rows)) break;

            $values = [];
            foreach ($rows as $r) {
                $vals = [];
                foreach ($r as $v) {
                    $vals[] = ($v === null) ? 'NULL' : "'" . $db->escape_str($v) . "'";
                }
                $values[] = '(' . implode(',', $vals) . ')';
            }

            if (!empty($values)) {
                fwrite($handle, "INSERT INTO `{$table_name}` ({$cols_list}) VALUES \n" . implode(",\n", $values) . ";\n\n");
            }

            $table_rows += count($rows);
            $offset += $batch;
        } while (count($rows) == $batch);

        $total_rows += $table_rows;
    }

    fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
    fclose($handle);
    if ($slug !== 'master') {
        $db->close();
    }

    $file_size = filesize($tmp_file);
    $file_size_mb = round($file_size / 1024 / 1024, 2);
    $steps[] = '[' . date('H:i:s') . '] SQL dump complete — ' . $table_count . ' tables, ~' . $total_rows . ' rows, ' . $file_size_mb . ' MB';
    log_message('error', '[CCX Backup] ' . $slug . ': SQL dump complete — ' . $table_count . ' tables, ~' . $total_rows . ' rows, ' . $file_size_mb . ' MB');

    // GZip compression if enabled
    $compression = get_option('ccx_backup_compression');
    if ($compression == '1' && function_exists('gzencode')) {
        $steps[] = '[' . date('H:i:s') . '] Compressing with GZip...';
        $sql_content = file_get_contents($tmp_file);
        $gz_file = $tmp_file . '.gz';
        file_put_contents($gz_file, gzencode($sql_content, 9));
        @unlink($tmp_file);
        $tmp_file = $gz_file;
        $filename .= '.gz';
        $gz_size = round(filesize($tmp_file) / 1024 / 1024, 2);
        $steps[] = '[' . date('H:i:s') . '] Compressed: ' . $file_size_mb . ' MB → ' . $gz_size . ' MB';
        log_message('error', '[CCX Backup] ' . $slug . ': Compressed: ' . $file_size_mb . ' MB -> ' . $gz_size . ' MB');
    }

    // Upload to destination
    try {
        $methods = json_decode($method, true);
        if (!is_array($methods)) {
            $methods = [$method ?: 'ftp'];
        }

        $upload_details = [];
        $upload_errors = [];

        foreach ($methods as $m) {
            try {
                if ($m === 'ftp') {
                    $steps[] = '[' . date('H:i:s') . '] Uploading to FTP (folder: ' . $run_folder . ')...';
                    log_message('error', '[CCX Backup] ' . $slug . ': Uploading to FTP folder: ' . $run_folder);
                    $remote_path = ccx_db_upload_to_ftp($tmp_file, $filename, $run_folder);
                    $upload_details[] = 'FTP: ' . $remote_path;
                    $steps[] = '[' . date('H:i:s') . '] Uploaded to FTP';
                } elseif ($m === 'google_drive') {
                    $steps[] = '[' . date('H:i:s') . '] Uploading to Google Drive (folder: ' . $run_folder . ')...';
                    log_message('error', '[CCX Backup] ' . $slug . ': Uploading to Google Drive folder: ' . $run_folder);
                    ccx_db_upload_to_gdrive($tmp_file, $filename, $run_folder);
                    $upload_details[] = 'GDrive: ' . $filename;
                    $steps[] = '[' . date('H:i:s') . '] Uploaded to Google Drive';
                }
            } catch (Exception $m_e) {
                $steps[] = '[' . date('H:i:s') . '] ERROR uploading to ' . strtoupper($m) . ': ' . $m_e->getMessage();
                $upload_errors[] = strtoupper($m) . ' Error: ' . $m_e->getMessage();
            }
        }

        if (empty($upload_details) && !empty($upload_errors)) {
            throw new Exception('All uploads failed: ' . implode(' | ', $upload_errors));
        }

        $detail = implode(' | ', $upload_details);
        if (!empty($upload_errors)) {
            $detail .= ' (Partial failures: ' . implode(' | ', $upload_errors) . ')';
        }

        $steps[] = '[' . date('H:i:s') . '] ' . $detail;

        @unlink($tmp_file);

        // Store checksums for incremental tracking
        $now = date('Y-m-d H:i:s');
        foreach ($current_checksums as $tname => $chk_data) {
            $CI->db->query(
                "INSERT INTO `" . db_prefix() . "ccx_backup_checksums` 
                    (`tenant_slug`, `table_name`, `row_count`, `checksum`, `last_full_backup`, `incremental_count`, `created_at`, `updated_at`)
                VALUES (?, ?, ?, ?, ?, 0, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    `row_count` = VALUES(`row_count`),
                    `checksum` = VALUES(`checksum`),
                    `last_full_backup` = IF(? = 'full', VALUES(`last_full_backup`), `last_full_backup`),
                    `incremental_count` = IF(? = 'full', 0, `incremental_count` + 1),
                    `updated_at` = VALUES(`updated_at`)",
                [$slug, $tname, $chk_data['row_count'], $chk_data['checksum'], $now, $now, $now, $actual_type, $actual_type]
            );
        }
        $steps[] = '[' . date('H:i:s') . '] Checksums stored (' . count($current_checksums) . ' tables)';

        $steps[] = '[' . date('H:i:s') . '] SUCCESS — ' . strtoupper($actual_type) . ' backup complete for ' . $slug;
        log_message('error', '[CCX Backup] SUCCESS (' . $actual_type . ') for ' . $slug . ': ' . $detail);
        return ['success' => true, 'message' => ucfirst($actual_type) . ' backup successful for ' . $slug . '. ' . $detail, 'steps' => $steps];

    } catch (Exception $e) {
        @unlink($tmp_file);
        $steps[] = '[' . date('H:i:s') . '] UPLOAD EXCEPTION: ' . $e->getMessage();
        log_message('error', '[CCX Backup] Upload failed for ' . $slug . ': ' . $e->getMessage());
        return ['success' => false, 'message' => 'Upload failed for ' . $slug . ': ' . $e->getMessage(), 'steps' => $steps];
    }
}

/**
 * FTP upload (used by cron)
 * @param string $subfolder Optional subfolder like 'backup_2026-03-25_00-30-00'
 */
function ccx_db_upload_to_ftp($local_file, $remote_filename, $subfolder = '')
{
    $host = get_option('ccx_backup_ftp_host');
    $port = (int) (get_option('ccx_backup_ftp_port') ?: 21);
    $user = get_option('ccx_backup_ftp_username');
    $pass = ccx_db_get_secret('ccx_backup_ftp_password');
    $path = get_option('ccx_backup_ftp_path') ?: '/';

    $host = preg_replace('#^(ftps?://)#i', '', trim($host));
    $host = rtrim($host, '/');

    $ftp = @ftp_connect($host, $port, 30);
    if (!$ftp) throw new Exception('FTP connect failed to ' . $host);

    if (!@ftp_login($ftp, $user, $pass)) {
        ftp_close($ftp);
        throw new Exception('FTP login failed');
    }

    ftp_pasv($ftp, true);

    // Build full path: base_path/subfolder
    $path = rtrim($path, '/');
    if (!empty($subfolder)) {
        $path .= '/' . trim($subfolder, '/');
    }

    // Navigate/create directory recursively
    if (!empty($path) && $path !== '/') {
        if (!@ftp_chdir($ftp, $path)) {
            $parts = explode('/', trim($path, '/'));
            $currentDir = '';
            foreach ($parts as $part) {
                $currentDir .= '/' . $part;
                if (!@ftp_chdir($ftp, $currentDir)) {
                    @ftp_mkdir($ftp, $currentDir);
                    @ftp_chdir($ftp, $currentDir);
                }
            }
        }
    }

    $remote_file = $path . '/' . $remote_filename;
    $upload = @ftp_put($ftp, $remote_file, $local_file, FTP_BINARY);
    ftp_close($ftp);

    if (!$upload) throw new Exception('FTP upload failed for: ' . $remote_filename);

    return $remote_file;
}

/**
 * Google Drive upload (used by cron)
 * @param string $subfolder Optional subfolder name, prepended to filename
 */
function ccx_db_upload_to_gdrive($local_file, $remote_filename, $subfolder = '')
{
    // Prefix filename with folder name for organization on GDrive
    // (slashes from the structured path are flattened to underscores)
    if (!empty($subfolder)) {
        $remote_filename = str_replace('/', '_', trim($subfolder, '/')) . '_' . $remote_filename;
    }

    $client_id = get_option('ccx_backup_gdrive_client_id');
    $client_secret = ccx_db_get_secret('ccx_backup_gdrive_client_secret');
    $refresh_token = ccx_db_get_secret('ccx_backup_gdrive_refresh_token');
    $folder_id = get_option('ccx_backup_gdrive_folder_id');

    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'client_id' => $client_id,
        'client_secret' => $client_secret,
        'refresh_token' => $refresh_token,
        'grant_type' => 'refresh_token',
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $token_response = curl_exec($ch);
    curl_close($ch);

    $token_json = json_decode($token_response, true);
    if (empty($token_json['access_token'])) {
        throw new Exception('Google Drive auth failed: ' . ($token_json['error_description'] ?? 'Unknown'));
    }

    $access_token = $token_json['access_token'];
    $file_content = file_get_contents($local_file);

    $metadata = ['name' => $remote_filename, 'mimeType' => 'application/sql'];
    if (!empty($folder_id)) $metadata['parents'] = [$folder_id];

    $boundary = 'ccx_cron_' . uniqid();
    $body = "--{$boundary}\r\nContent-Type: application/json; charset=UTF-8\r\n\r\n" . json_encode($metadata) . "\r\n--{$boundary}\r\nContent-Type: application/sql\r\n\r\n" . $file_content . "\r\n--{$boundary}--";

    $ch = curl_init('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $access_token,
        'Content-Type: multipart/related; boundary=' . $boundary,
    ]);
    $upload_response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
        $err = json_decode($upload_response, true);
        throw new Exception('Google Drive upload failed: ' . ($err['error']['message'] ?? 'HTTP ' . $http_code));
    }

    return true;
}

/**
 * Check if cron should run based on schedule and last run time
 */
function ccx_db_should_run_cron($cron_expression, $last_run, $now)
{
    if (empty($last_run)) {
        return true; // Never run before
    }

    $last_run_time = strtotime($last_run);
    if (!$last_run_time) {
        return true;
    }

    $elapsed_seconds = $now - $last_run_time;
    $elapsed_minutes = $elapsed_seconds / 60;

    // Parse cron to determine interval
    $parts = explode(' ', trim($cron_expression));
    if (count($parts) < 5) {
        return $elapsed_minutes >= 1440; // Default: daily
    }

    $minute = $parts[0];
    $hour = $parts[1];
    $dom = $parts[2];
    $month = $parts[3];
    $dow = $parts[4];

    // Every minute
    if ($minute === '*' && $hour === '*') {
        return $elapsed_minutes >= 1;
    }

    // Every N minutes (e.g., "*/5 * * * *")
    if (strpos($minute, '*/') === 0 && $hour === '*') {
        $interval = (int) substr($minute, 2);
        return $elapsed_minutes >= max($interval, 1);
    }

    // Every hour at specific minute (e.g., "0 * * * *")
    if ($hour === '*' && $minute !== '*') {
        return $elapsed_minutes >= 60;
    }

    // Every N hours (e.g., "0 */6 * * *")
    if (strpos($hour, '*/') === 0) {
        $interval = (int) substr($hour, 2);
        return $elapsed_minutes >= ($interval * 60);
    }

    // Weekly
    if ($dow !== '*') {
        return $elapsed_minutes >= 10080; // 7 days
    }

    // Monthly
    if ($dom !== '*') {
        return $elapsed_minutes >= 43200; // 30 days
    }

    // Daily (specific hour, e.g., "0 2 * * *")
    return $elapsed_minutes >= 1440; // 24 hours
}

/**
 * Auto-cleanup old backups from FTP based on retention days and max backups per tenant.
 */
function ccx_db_auto_cleanup_old_backups($CI, $method)
{
    $auto_delete = get_option('ccx_backup_auto_delete');
    if ($auto_delete != '1') {
        return;
    }

    $retention_days = (int)(get_option('ccx_backup_retention_days') ?: 30);
    $max_backups = (int)(get_option('ccx_backup_max_backups') ?: 10);
    $cutoff_date = date('Y-m-d', strtotime("-{$retention_days} days"));

    log_message('error', '[CCX Backup] Auto-cleanup: retention=' . $retention_days . ' days, max=' . $max_backups . '/tenant, method=' . $method);

    try {
        if ($method === 'ftp') {
            ccx_db_cleanup_ftp($retention_days, $max_backups, $cutoff_date, $CI);
        } elseif ($method === 'google_drive') {
            ccx_db_cleanup_gdrive($retention_days, $max_backups, $cutoff_date, $CI);
        }
    } catch (\Throwable $e) {
        log_message('error', '[CCX Backup] Cleanup error: ' . $e->getMessage());
        $CI->db->insert(db_prefix() . 'ccx_auto_backup_logs', [
            'tenant_slug' => null,
            'action' => 'auto_cleanup',
            'method' => $method,
            'status' => 'error',
            'message' => 'Cleanup error: ' . $e->getMessage(),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}

/**
 * Delete old backup folders from FTP based on retention days and max backups.
 * Folders are identified by naming convention: backup_YYYY-MM-DD_HH-II-SS
 */
function ccx_db_cleanup_ftp($retention_days, $max_backups, $cutoff_date, $CI)
{
    $host = get_option('ccx_backup_ftp_host');
    $port = (int)(get_option('ccx_backup_ftp_port') ?: 21);
    $user = get_option('ccx_backup_ftp_username');
    $pass = ccx_db_get_secret('ccx_backup_ftp_password');
    $path = get_option('ccx_backup_ftp_path') ?: '/';

    $host = preg_replace('#^(ftps?://)#i', '', trim($host));
    $host = rtrim($host, '/');

    $ftp = @ftp_connect($host, $port, 30);
    if (!$ftp) return;

    if (!@ftp_login($ftp, $user, $pass)) {
        ftp_close($ftp);
        return;
    }
    ftp_pasv($ftp, true);

    $path = rtrim($path, '/');
    $list = @ftp_nlist($ftp, $path);
    if (!is_array($list) || empty($list)) {
        ftp_close($ftp);
        return;
    }

    // Collect backup folders with dates
    $backup_folders = [];
    foreach ($list as $item) {
        $basename = basename($item);
        $folder_date = null;

        // New structure: DD-Month-YYYY-CCX-Backups (e.g. 12-July-2026-CCX-Backups)
        if (preg_match('/^(\d{1,2})-([A-Za-z]+)-(\d{4})-CCX-Backups$/', $basename, $m)) {
            $ts = strtotime($m[1] . ' ' . $m[2] . ' ' . $m[3]);
            if ($ts) {
                $folder_date = date('Y-m-d', $ts);
            }
        }
        // Legacy: backup_YYYY-MM-DD_HH-II-SS / full_backup_... / incr_backup_...
        elseif (preg_match('/^(?:full_|incr_)?backup_(\d{4}-\d{2}-\d{2})_\d{2}-\d{2}-\d{2}$/', $basename, $m)) {
            $folder_date = $m[1];
        }

        if ($folder_date !== null) {
            $backup_folders[] = [
                'path' => $path . '/' . $basename,
                'basename' => $basename,
                'date' => $folder_date,
            ];
        }
    }

    if (empty($backup_folders)) {
        ftp_close($ftp);
        return;
    }

    // Sort by date descending (newest first)
    usort($backup_folders, function ($a, $b) {
        $cmp = strcmp($b['date'], $a['date']);
        return $cmp !== 0 ? $cmp : strcmp($b['basename'], $a['basename']);
    });

    $deleted = 0;

    foreach ($backup_folders as $idx => $folder) {
        $should_delete = false;

        // Rule 1: Delete if older than retention period
        if ($folder['date'] < $cutoff_date) {
            $should_delete = true;
        }

        // Rule 2: Delete if exceeds max backup count (keep newest N)
        if ($idx >= $max_backups) {
            $should_delete = true;
        }

        if ($should_delete) {
            // Recursively delete the folder and its contents
            if (ccx_db_ftp_rmdir_recursive($ftp, $folder['path'])) {
                $deleted++;
                log_message('error', '[CCX Backup] Cleanup: Deleted folder: ' . $folder['basename'] . ' (date: ' . $folder['date'] . ')');
            }
        }
    }

    ftp_close($ftp);

    if ($deleted > 0) {
        $CI->db->insert(db_prefix() . 'ccx_auto_backup_logs', [
            'tenant_slug' => null,
            'action' => 'auto_cleanup',
            'method' => 'ftp',
            'status' => 'success',
            'message' => 'Auto-cleanup: Deleted ' . $deleted . ' old backup folder(s) from FTP. Retention: ' . $retention_days . ' days, Max: ' . $max_backups . ' folders.',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        log_message('error', '[CCX Backup] Cleanup complete: deleted ' . $deleted . ' folders');
    }
}

/**
 * Recursively delete a folder and its contents via FTP.
 */
function ccx_db_ftp_rmdir_recursive($ftp, $dir)
{
    $files = @ftp_nlist($ftp, $dir);
    if (is_array($files)) {
        foreach ($files as $file) {
            $basename = basename($file);
            if ($basename === '.' || $basename === '..') continue;

            // Try to delete as file first
            if (!@ftp_delete($ftp, $file)) {
                // If delete fails, it might be a directory — recurse
                ccx_db_ftp_rmdir_recursive($ftp, $file);
            }
        }
    }
    return @ftp_rmdir($ftp, $dir);
}

/**
 * Delete old backup files from Google Drive based on retention days.
 */
function ccx_db_cleanup_gdrive($retention_days, $max_backups, $cutoff_date, $CI)
{
    $client_id = get_option('ccx_backup_gdrive_client_id');
    $client_secret = ccx_db_get_secret('ccx_backup_gdrive_client_secret');
    $refresh_token = ccx_db_get_secret('ccx_backup_gdrive_refresh_token');
    $folder_id = get_option('ccx_backup_gdrive_folder_id');

    if (empty($client_id) || empty($refresh_token)) return;

    // Get access token
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'client_id' => $client_id,
        'client_secret' => $client_secret,
        'refresh_token' => $refresh_token,
        'grant_type' => 'refresh_token',
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $token_response = curl_exec($ch);
    curl_close($ch);

    $token_json = json_decode($token_response, true);
    if (empty($token_json['access_token'])) return;
    $access_token = $token_json['access_token'];

    // List files in folder with name matching ccx_db_
    $query = "name contains 'ccx_db_'";
    if (!empty($folder_id)) {
        $query .= " and '" . $folder_id . "' in parents";
    }
    $query .= " and trashed = false";

    $list_url = 'https://www.googleapis.com/drive/v3/files?q=' . urlencode($query) . '&fields=files(id,name,createdTime)&pageSize=500';
    $ch = curl_init($list_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $access_token]);
    $list_response = curl_exec($ch);
    curl_close($ch);

    $list_data = json_decode($list_response, true);
    if (empty($list_data['files'])) return;

    $tenant_files = [];
    $deleted = 0;

    foreach ($list_data['files'] as $file) {
        $name = $file['name'];
        // Unanchored: uploaded names carry a flattened folder prefix, e.g.
        // "12-July-2026-CCX-Backups_Full Backup_ccx_db_slug_2026-07-12_16-01-04.sql"
        if (!preg_match('/ccx_db_(.+)_(\d{4}-\d{2}-\d{2})_\d{2}-\d{2}-\d{2}\.sql(\.gz)?$/', $name, $m)) {
            continue;
        }
        $slug = $m[1];
        $file_date = $m[2];

        // Delete files older than retention period
        if ($file_date < $cutoff_date) {
            $del_url = 'https://www.googleapis.com/drive/v3/files/' . $file['id'];
            $ch = curl_init($del_url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $access_token]);
            curl_exec($ch);
            curl_close($ch);
            $deleted++;
            log_message('error', '[CCX Backup] GDrive cleanup: Deleted expired file: ' . $name);
            continue;
        }

        if (!isset($tenant_files[$slug])) {
            $tenant_files[$slug] = [];
        }
        $tenant_files[$slug][] = ['id' => $file['id'], 'date' => $file_date, 'name' => $name];
    }

    // Enforce max backups per tenant
    foreach ($tenant_files as $slug => $files) {
        if (count($files) <= $max_backups) continue;

        usort($files, function ($a, $b) {
            return strcmp($b['date'], $a['date']);
        });

        $to_delete = array_slice($files, $max_backups);
        foreach ($to_delete as $f) {
            $del_url = 'https://www.googleapis.com/drive/v3/files/' . $f['id'];
            $ch = curl_init($del_url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $access_token]);
            curl_exec($ch);
            curl_close($ch);
            $deleted++;
            log_message('error', '[CCX Backup] GDrive cleanup: Deleted excess file for ' . $slug . ': ' . $f['name']);
        }
    }

    if ($deleted > 0) {
        $CI->db->insert(db_prefix() . 'ccx_auto_backup_logs', [
            'tenant_slug' => null,
            'action' => 'auto_cleanup',
            'method' => 'google_drive',
            'status' => 'success',
            'message' => 'Auto-cleanup: Deleted ' . $deleted . ' old backup(s) from Google Drive.',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}

/**
 * Send email notification when backup failures occur.
 */
function ccx_db_send_failure_notification($CI)
{
    $notify_on_failure = get_option('ccx_backup_notify_on_failure');
    if ($notify_on_failure != '1') return;

    $notify_email = get_option('ccx_backup_notify_email');
    if (empty($notify_email)) return;

    // Check for recent failures (last 5 minutes).
    // Deleted tenants ("Company not found") are excluded — they log as 'skipped'
    // and are auto-disabled, so they must never raise a failure alert.
    $since = date('Y-m-d H:i:s', strtotime('-5 minutes'));
    $failures = $CI->db->where('status', 'error')
        ->where('action', 'auto_backup')
        ->where('created_at >=', $since)
        ->not_like('message', 'Company not found')
        ->get(db_prefix() . 'ccx_auto_backup_logs')
        ->result();

    if (empty($failures)) return;

    $subject = '[CCX DB] Backup Failure Alert - ' . count($failures) . ' failure(s)';
    $body = '<h3>CCX Auto Backup - Failure Alert</h3>';
    $body .= '<p>The following backup(s) failed at ' . date('Y-m-d H:i:s') . ':</p><ul>';
    foreach ($failures as $f) {
        $body .= '<li><strong>' . htmlspecialchars($f->tenant_slug) . '</strong>: ' . htmlspecialchars($f->message) . '</li>';
    }
    $body .= '</ul><p>Please check the Backup Logs in your admin panel for more details.</p>';

    @$CI->load->library('email');
    $CI->email->from(get_option('smtp_email') ?: 'noreply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'), get_option('companyname'));
    $CI->email->to($notify_email);
    $CI->email->subject($subject);
    $CI->email->message($body);
    $CI->email->send();

    log_message('error', '[CCX Backup] Failure notification sent to: ' . $notify_email);
}

/**
 * Send a success summary email after a backup run completes (opt-in via
 * the "Email on Success" setting — ccx_backup_notify_on_success).
 * @param array $results Array of ['slug' => ..., 'status' => success|error|skipped, 'message' => ...]
 */
function ccx_db_send_success_notification($CI, $backup_type, $run_folder, $method, $results)
{
    if (get_option('ccx_backup_notify_on_success') != '1') return;
    if (empty($results)) return;

    $success_count = 0;
    $error_count = 0;
    $skipped_count = 0;
    foreach ($results as $r) {
        if ($r['status'] === 'success') $success_count++;
        elseif ($r['status'] === 'skipped') $skipped_count++;
        else $error_count++;
    }

    $method_str = is_array($method) ? implode(', ', $method) : $method;

    $subject = '[CCX DB] ' . ucfirst($backup_type) . ' Backup Completed — ' . $success_count . '/' . count($results) . ' successful';

    $intro = 'Backup run finished at <strong>' . date('Y-m-d H:i:s') . '</strong><br>';
    $intro .= 'Type: <strong>' . strtoupper($backup_type) . '</strong> | Destination folder: <strong>' . htmlspecialchars($run_folder) . '</strong> | Method: <strong>' . htmlspecialchars($method_str) . '</strong><br>';
    $intro .= 'Success: <strong>' . $success_count . '</strong> | Errors: <strong>' . $error_count . '</strong> | Skipped: <strong>' . $skipped_count . '</strong> | Total: <strong>' . count($results) . '</strong>';

    ccx_db_send_backup_summary_email($CI, $subject, $intro, $results);
}

/**
 * Shared HTML summary email sender for backup runs.
 * @param array $rows Array of ['slug' => ..., 'status' => ..., 'message' => ...]
 */
function ccx_db_send_backup_summary_email($CI, $subject, $intro, $rows)
{
    $notify_email = get_option('ccx_backup_notify_email');
    if (empty($notify_email)) return;

    $body = '<h3>CCX Auto Backup — Run Summary</h3>';
    $body .= '<p>' . $intro . '</p>';
    $body .= '<table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;font-family:Arial,sans-serif;font-size:13px">';
    $body .= '<tr style="background:#f0f0f0"><th>#</th><th>Tenant</th><th>Status</th><th>Details</th></tr>';
    foreach ($rows as $i => $r) {
        $color = $r['status'] === 'success' ? '#27ae60' : ($r['status'] === 'skipped' ? '#f39c12' : '#e74c3c');
        $body .= '<tr>'
            . '<td>' . ($i + 1) . '</td>'
            . '<td><strong>' . htmlspecialchars($r['slug']) . '</strong></td>'
            . '<td style="color:' . $color . ';font-weight:bold">' . strtoupper(htmlspecialchars($r['status'])) . '</td>'
            . '<td>' . htmlspecialchars($r['message']) . '</td>'
            . '</tr>';
    }
    $body .= '</table>';
    $body .= '<p>Full step-by-step logs are available under Backup Logs in the admin panel.</p>';

    @$CI->load->library('email');
    $CI->email->from(get_option('smtp_email') ?: 'noreply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'), get_option('companyname'));
    $CI->email->to($notify_email);
    $CI->email->subject($subject);
    $CI->email->message($body);
    $CI->email->send();

    log_message('error', '[CCX Backup] Summary email sent to: ' . $notify_email . ' — ' . $subject);
}
