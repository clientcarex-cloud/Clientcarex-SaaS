<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Ccx_db extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        if (!has_permission('settings', '', 'view')) {
            access_denied('CCX DB');
        }
        $this->load->helper('perfex_saas/perfex_saas');
    }

    public function reinstall_module_action()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $slug_input = $this->input->post('slug');
        $slugs_input = $this->input->post('slugs');
        
        $slugs = [];
        if (!empty($slugs_input) && is_array($slugs_input)) {
            $slugs = $slugs_input;
        } elseif (!empty($slug_input)) {
            $slugs = is_array($slug_input) ? $slug_input : [$slug_input];
        }

        $module_name = $this->input->post('module_name');
        $debug = [];
        $errors = [];
        $successes = [];

        if (empty($slugs) || !$module_name) {
            echo json_encode(['success' => false, 'message' => 'Missing parameters: Tenant or Module']);
            return;
        }

        $module_path = APP_MODULES_PATH . $module_name . '/install.php';
        if (!file_exists($module_path)) {
            echo json_encode(['success' => false, 'message' => 'install.php not found for module ' . $module_name]);
            return;
        }

        $CI = &get_instance();
        $original_db = $CI->db;

        foreach ($slugs as $slug) {
            // Get DB Connection
            $db_info = $this->_get_db_connection($slug);
            if (isset($db_info['error'])) {
                $errors[] = "$slug: " . $db_info['error'];
                continue;
            }

            $tenant_db = $db_info['conn'];

            // SWAP DB
            $CI->db = $tenant_db;

            $prefix_used = $db_info['prefix']; 
            if (isset($db_info['prefix'])) {
                $debug[$slug]['prefix_used'] = $db_info['prefix'];
            }

            $debug[$slug]['slug'] = $slug;
            $debug[$slug]['ci_db_prefix'] = $CI->db->dbprefix;
            $debug[$slug]['db_database'] = $CI->db->database;

            if (function_exists('db_prefix')) {
                $debug[$slug]['helper_db_prefix'] = db_prefix();
            }

            // Execute install.php
            $error = null;
            $temp_install_path = APP_MODULES_PATH . $module_name . '/install_temp_' . $slug . '_' . time() . '.php';

            try {
                // Read install.php content
                $install_content = file_get_contents($module_path);

                // Replace db_prefix() with the actual tenant prefix
                $install_content = str_replace('db_prefix()', "'" . $prefix_used . "'", $install_content);

                // Write to temp file
                if (file_put_contents($temp_install_path, $install_content) === false) {
                    throw new Exception("Failed to write temporary install file.");
                }

                // Include the temp file
                include($temp_install_path);

            } catch (Throwable $e) {
                $error = $e->getMessage();
            } catch (Exception $e) {
                $error = $e->getMessage();
            } finally {
                // Clean up temp file
                if (file_exists($temp_install_path)) {
                    @unlink($temp_install_path);
                }
            }

            // Restore DB
            $CI->db = $original_db;

            // Close tenant connection if it's not master
            if (is_object($tenant_db) && $slug !== 'master') {
                $tenant_db->close();
            }

            if ($error) {
                $errors[] = "$slug: " . $error;
            } else {
                $successes[] = $slug;
            }
        }

        if (!empty($errors)) {
            $msg = 'Re-installed for ' . count($successes) . ' tenants. Errors (' . count($errors) . '): ' . implode(', ', $errors);
            echo json_encode(['success' => (count($successes) > 0), 'message' => $msg, 'debug' => $debug]);
        } else {
            echo json_encode(['success' => true, 'message' => 'Module re-installed successfully for ' . count($successes) . ' tenants.', 'debug' => $debug]);
        }
    }

    public function compare_db()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $source_slug = $this->input->post('source_slug');
        $dest_slug = $this->input->post('dest_slug');

        if ($source_slug == $dest_slug) {
            echo json_encode(['error' => 'Source and Destination databases cannot be the same.']);
            return;
        }

        $source_db = $this->_get_db_connection($source_slug);
        $dest_db = $this->_get_db_connection($dest_slug);

        if (isset($source_db['error']) || isset($dest_db['error'])) {
            echo json_encode(['error' => 'Connection failed: ' . ($source_db['error'] ?? '') . ' ' . ($dest_db['error'] ?? '')]);
            return;
        }

        $source_schema = $this->_get_db_schema($source_db['conn'], $source_db['prefix']);
        $dest_schema = $this->_get_db_schema($dest_db['conn'], $dest_db['prefix']);

        // Compare Schemas
        $comparison = [
            'missing_in_dest' => [],
            'missing_in_source' => [],
            'schema_mismatch' => [],
            'row_count_mismatch' => []
        ];

        // Check tables in source
        foreach ($source_schema as $table => $info) {
            if (!isset($dest_schema[$table])) {
                $comparison['missing_in_dest'][] = $table;
                continue;
            }

            // Check row counts
            if ($info['rows'] != $dest_schema[$table]['rows']) {
                $comparison['row_count_mismatch'][] = [
                    'table' => $table,
                    'source_rows' => $info['rows'],
                    'dest_rows' => $dest_schema[$table]['rows']
                ];
            }

            // Check columns (Basic count check for now, can be expanded)
            if (count($info['columns']) != count($dest_schema[$table]['columns'])) {
                $comparison['schema_mismatch'][] = [
                    'table' => $table,
                    'issue' => 'Column count mismatch',
                    'source_cols' => count($info['columns']),
                    'dest_cols' => count($dest_schema[$table]['columns'])
                ];
            } else {
                // Deep column check
                $diff_cols = array_diff($info['columns'], $dest_schema[$table]['columns']);
                if (!empty($diff_cols)) {
                    $comparison['schema_mismatch'][] = [
                        'table' => $table,
                        'issue' => 'Column name mismatch',
                        'diff' => implode(', ', $diff_cols)
                    ];
                }
            }
        }

        // Check tables in dest
        foreach ($dest_schema as $table => $info) {
            if (!isset($source_schema[$table])) {
                $comparison['missing_in_source'][] = $table;
            }
        }

        // Close connections
        if (is_object($source_db['conn']))
            $source_db['conn']->close();
        if (is_object($dest_db['conn']))
            $dest_db['conn']->close();

        $this->load->view('ccx_db/compare_result', ['comparison' => $comparison]);
    }

    private function _get_db_connection($slug)
    {
        if ($slug == 'master') {
            return ['conn' => $this->db, 'prefix' => ''];
        }

        $companies = $this->perfex_saas_model->companies($slug); // Not efficient but works for single lookup if slug is ID, but we have slug.
        // Perfex_saas_model::companies() expects ID potentially. 
        // Let's use get_company_by_slug
        $company = $this->perfex_saas_model->get_company_by_slug($slug);

        if (!$company) {
            return ['error' => 'Company not found'];
        }

        $dsn = perfex_saas_get_company_dsn($company);

        // Fix: Strip healtho_ps_ from slug for prefix generation to avoid double prefixing
        $clean_slug = str_replace('healtho_ps_', '', $company->slug);
        $prefix = perfex_saas_tenant_db_prefix($clean_slug);

        log_message('error', 'CCX DB: Connecting to tenant slug: ' . $company->slug);
        log_message('error', 'CCX DB: Clean slug: ' . $clean_slug);
        log_message('error', 'CCX DB: Generated prefix: ' . $prefix);
        log_message('error', 'CCX DB: DSN dbname: ' . ($dsn['dbname'] ?? 'N/A'));

        try {
            $temp_db = perfex_saas_load_ci_db_from_dsn($dsn, ['dbprefix' => $prefix]);

            if ($temp_db && $temp_db->conn_id) {
                return ['conn' => $temp_db, 'prefix' => $prefix];
            }
        } catch (Throwable $e) {
            return ['error' => 'Connection Exception: ' . $e->getMessage()];
        } catch (Exception $e) {
            return ['error' => 'Connection Exception: ' . $e->getMessage()];
        }

        return ['error' => 'Connection failed'];
    }

    private function _get_db_schema($db, $prefix)
    {
        $tables = [];
        $query = $db->query("SHOW TABLES LIKE '$prefix%'");
        $rows = $query->result_array();

        foreach ($rows as $row) {
            $table_name = array_values($row)[0];
            // Normalize table name (remove prefix for comparison)
            $normalized_name = $table_name;
            if (!empty($prefix) && strpos($table_name, $prefix) === 0) {
                $normalized_name = substr($table_name, strlen($prefix));
            }

            // Get Row Count
            $count_query = $db->query("SELECT COUNT(*) as c FROM `$table_name`");
            $count = $count_query->row()->c;

            // Get Columns
            $cols_query = $db->query("SHOW COLUMNS FROM `$table_name`");
            $cols = [];
            foreach ($cols_query->result() as $col) {
                $cols[] = $col->Field;
            }

            $tables[$normalized_name] = [
                'real_name' => $table_name,
                'rows' => $count,
                'columns' => $cols
            ];
        }
        return $tables;
    }

    public function get_tables_json($slug)
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $db = $this->_get_db_connection($slug);
        if (isset($db['error'])) {
            echo json_encode(['error' => $db['error']]);
            return;
        }

        $schema = $this->_get_db_schema($db['conn'], $db['prefix']);
        $tables = array_keys($schema);
        sort($tables);

        if (is_object($db['conn']))
            $db['conn']->close();

        echo json_encode($tables);
    }

    public function copy_db_action()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $source_slug = $this->input->post('source_slug');
        $dest_slug = $this->input->post('dest_slug');

        if ($source_slug == $dest_slug) {
            echo json_encode(['error' => 'Source and Destination cannot be the same.']);
            return;
        }

        try {
            $source_db = $this->_get_db_connection($source_slug);
            $dest_db = $this->_get_db_connection($dest_slug);

            if (isset($source_db['error']) || isset($dest_db['error'])) {
                throw new Exception('Connection failed: ' . ($source_db['error'] ?? '') . ' ' . ($dest_db['error'] ?? ''));
            }

            // Get all tables from source
            $schema = $this->_get_db_schema($source_db['conn'], $source_db['prefix']);
            $tables = array_keys($schema);

            // Disable FK checks in Dest
            $dest_db['conn']->query('SET FOREIGN_KEY_CHECKS=0');

            $errors = [];
            foreach ($tables as $table) {
                try {
                    $this->_copy_table($source_db, $dest_db, $table);
                } catch (Exception $e) {
                    $errors[] = "Failed to copy $table: " . $e->getMessage();
                }
            }

            // Enable FK checks
            $dest_db['conn']->query('SET FOREIGN_KEY_CHECKS=1');

            // Close connections
            if (is_object($source_db['conn']))
                $source_db['conn']->close();
            if (is_object($dest_db['conn']))
                $dest_db['conn']->close();

            if (!empty($errors)) {
                echo json_encode(['success' => false, 'message' => 'Completed with errors: ' . implode(', ', $errors)]);
            } else {
                echo json_encode(['success' => true, 'message' => 'Database copied successfully!']);
            }

        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function copy_table_action()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $source_slug = $this->input->post('source_slug');
        $table_name = $this->input->post('table_name');
        $dest_slug = $this->input->post('dest_slug');

        if ($source_slug == $dest_slug) {
            echo json_encode(['error' => 'Source and Destination cannot be the same.']);
            return;
        }

        try {
            $source_db = $this->_get_db_connection($source_slug);
            $dest_db = $this->_get_db_connection($dest_slug);

            if (isset($source_db['error']) || isset($dest_db['error'])) {
                throw new Exception('Connection failed: ' . ($source_db['error'] ?? '') . ' ' . ($dest_db['error'] ?? ''));
            }

            $dest_db['conn']->query('SET FOREIGN_KEY_CHECKS=0');
            $this->_copy_table($source_db, $dest_db, $table_name);
            $dest_db['conn']->query('SET FOREIGN_KEY_CHECKS=1');

            if (is_object($source_db['conn']))
                $source_db['conn']->close();
            if (is_object($dest_db['conn']))
                $dest_db['conn']->close();

            echo json_encode(['success' => true, 'message' => "Table $table_name copied successfully!"]);

        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    private function _copy_table($source_db, $dest_db, $table_name)
    {
        $s_conn = $source_db['conn'];
        $d_conn = $dest_db['conn'];
        $s_prefix = $source_db['prefix'];
        $d_prefix = $dest_db['prefix'];

        $source_real_table = $s_prefix . $table_name;
        $dest_real_table = $d_prefix . $table_name;

        // 1. Get Create SQL
        $query = $s_conn->query("SHOW CREATE TABLE `$source_real_table`");
        $row = $query->row_array();
        if (!$row)
            throw new Exception("Table $source_real_table not found in source.");

        $create_sql = $row['Create Table'];

        // 2. Adjust Table Name and FK references in SQL
        // Replace source table name with dest table name
        $create_sql = str_replace("`$source_real_table`", "`$dest_real_table`", $create_sql);

        // Replace prefix references (for Foreign Keys)
        // This is a bit risky but standard for this controlled environment
        if ($s_prefix !== $d_prefix) {
            // Replace `prefix_ with `dest_prefix_
            if (!empty($s_prefix)) {
                $create_sql = str_replace("`$s_prefix", "`$d_prefix", $create_sql);
            }
        }

        // 3. Drop Dest Table
        $d_conn->query("DROP TABLE IF EXISTS `$dest_real_table`");

        // 4. Create Dest Table
        if (!$d_conn->query($create_sql)) {
            throw new Exception("Failed to create table $dest_real_table. Error: " . $d_conn->error()['message']);
        }

        // 5. Compute common columns to safely insert data
        $scols_q = $s_conn->query("SHOW COLUMNS FROM `$source_real_table`");
        $scols = [];
        foreach ($scols_q->result() as $c)
            $scols[] = $c->Field;

        $dcols_q = $d_conn->query("SHOW COLUMNS FROM `$dest_real_table`");
        $dcols = [];
        foreach ($dcols_q->result() as $c)
            $dcols[] = $c->Field;

        $common_cols = array_intersect($scols, $dcols);
        if (empty($common_cols))
            return; // Nothing to copy

        // 6. Copy Data (Chunked)
        $batch_size = 1000;
        $offset = 0;

        $cols_str = '`' . implode('`, `', $common_cols) . '`';

        do {
            $data_query = $s_conn->query("SELECT $cols_str FROM `$source_real_table` LIMIT $batch_size OFFSET $offset");
            $rows = $data_query->result_array();

            if (empty($rows))
                break;

            // Batch insert
            // CodeIgniter's insert_batch is nice but we are using potentially different DB objects/drivers, 
            // and we want to be raw for speed.

            $values = [];
            foreach ($rows as $row) {
                $row_vals = [];
                foreach ($row as $val) {
                    if ($val === null) {
                        $row_vals[] = 'NULL';
                    } else {
                        $row_vals[] = "'" . $d_conn->escape_str($val) . "'";
                    }
                }
                $values[] = "(" . implode(',', $row_vals) . ")";
            }

            if (!empty($values)) {
                $in_sql = "INSERT INTO `$dest_real_table` ($cols_str) VALUES " . implode(',', $values);
                if (!$d_conn->query($in_sql)) {
                    throw new Exception("Failed to insert data into $dest_real_table. Error: " . $d_conn->error()['message']);
                }
            }

            $offset += $batch_size;

        } while (count($rows) == $batch_size);
    }


    public function index()
    {
        $data['title'] = 'CCX Database Manager';

        // Fetch Master DB Info
        $master_db_name = $this->db->database;
        $data['master_db'] = [
            'name' => 'Master DB (' . $master_db_name . ')',
            'slug' => 'master',
            'is_master' => true,
            'stats' => $this->_get_db_stats($this->db, '')
        ];

        // Fetch Tenants
        $tenants = [];
        if (table_exists('perfex_saas_companies')) {
            $this->load->model('perfex_saas/perfex_saas_model');
            $companies = $this->perfex_saas_model->companies();

            foreach ($companies as $company) {
                $tenant_stats = $this->_get_tenant_stats($company);
                $tenants[] = [
                    'name' => $company->name . ' (' . $company->slug . ')',
                    'slug' => $company->slug,
                    'is_master' => false,
                    'stats' => $tenant_stats,
                    'company' => $company
                ];
            }
        }
        $data['tenants'] = $tenants;

        // Get all system modules for the dropdown
        $data['system_modules'] = $this->app_modules->get();

        $this->load->view('ccx_db/dashboard', $data);
    }

    public function cron_diagnostics()
    {
        if ($this->input->get('debug_crash') == '1') {
            ini_set('display_errors', 1);
            ini_set('display_startup_errors', 1);
            error_reporting(E_ALL);
            @set_time_limit(0);

            echo "<h1>Cron Crash Debugger</h1><pre>";
            $this->load->model('cron_model');

            $methods = ['staff_reminders', 'events', 'tasks_reminders', 'recurring_tasks', 'proposals', 'invoice_overdue', 'invoice_due', 'estimate_expiration', 'contracts_expiration_check', 'contracts_sign_reminder_check', 'autoclose_tickets', 'recurring_invoices', 'recurring_expenses', 'auto_import_imap_tickets', 'check_leads_email_integration', 'delete_activity_log', 'send_scheduled_emails', 'stop_task_timers', 'non_billed_tasks_notification'];

            foreach ($methods as $method) {
                echo "Executing $method... ";
                try {
                    $m = method_exists($this->cron_model, $method) ? $method : '_'.$method;
                    if (method_exists($this->cron_model, $m)) {
                        $reflection = new ReflectionMethod($this->cron_model, $m);
                        $reflection->setAccessible(true);
                        $reflection->invoke($this->cron_model);
                        echo "[OK]\n";
                    } else {
                        echo "[Not Found]\n";
                    }
                } catch (\Throwable $e) {
                    die("\n[CRASHED!] => " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
                }
            }

            echo "Executing email queue... ";
            try { $this->email->send_queue(); echo "[OK]\n"; } catch (\Throwable $e) { die("\n[CRASHED!] => " . $e->getMessage()); }

            echo "Executing after_cron_run hooks... ";
            try {
                hooks()->do_action('after_cron_run', true);
                echo "[OK]\n";
            } catch (\Throwable $e) {
                die("\n[CRASHED IN AFTER_CRON_RUN!] => " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            }

            die("\nAll standard and extended cron jobs executed perfectly.");
        }

        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $last_cron_run = get_option('last_cron_run');
        $last_ccx_incr_run = get_option('ccx_backup_last_incr_run');
        $last_ccx_full_run = get_option('ccx_backup_last_full_run');

        $format_date = function($date_string) {
            if (empty($date_string)) return 'Never';
            $time = is_numeric($date_string) ? (int)$date_string : strtotime($date_string);
            return $time ? date('j M Y, g:i A', $time) : 'Never';
        };

        // Check if cron has run in the last 10 minutes (600 seconds) for it to be considered "healthy"
        $is_cron_healthy = false;
        if (!empty($last_cron_run)) {
            $last_time = is_numeric($last_cron_run) ? (int)$last_cron_run : strtotime($last_cron_run);
            if (time() - $last_time <= 900) { // 15 mins
                $is_cron_healthy = true;
            }
            $last_cron_run_formatted = date('j M Y, g:i A', $last_time);
        } else {
            $last_cron_run_formatted = 'Never';
        }

        $server_php_path = PHP_BINDIR . '/php'; // Tries to guess PHP path
        if (!file_exists($server_php_path)) {
            $server_php_path = 'php'; 
        }

        $wget_command = 'wget -q -O- ' . site_url('cron/index') . ' >/dev/null 2>&1';
        $curl_command = 'curl -s -o /dev/null ' . site_url('cron/index');
        $php_command  = $server_php_path . ' ' . FCPATH . 'index.php cron index';

        // Get latest heartbeat
        $heartbeat_query = $this->db->where('action', 'cron_heartbeat')
            ->order_by('id', 'DESC')
            ->limit(1)
            ->get(db_prefix() . 'ccx_auto_backup_logs');
        $latest_heartbeat = $heartbeat_query->row();
        $heartbeat_msg = $latest_heartbeat ? $format_date($latest_heartbeat->created_at) . ' - ' . $latest_heartbeat->message : 'No heartbeat found';

        echo json_encode([
            'success' => true,
            'is_healthy' => $is_cron_healthy,
            'last_perfex_cron' => $last_cron_run_formatted,
            'last_ccx_incr_run' => $format_date($last_ccx_incr_run),
            'last_ccx_full_run' => $format_date($last_ccx_full_run),
            'latest_heartbeat' => $heartbeat_msg,
            'wget_command' => $wget_command,
            'curl_command' => $curl_command,
            'php_command'  => $php_command
        ]);
        return;
    }

    private function _get_tenant_stats($company)
    {
        try {
            // Get DSN and prefix for tenant
            $dsn = perfex_saas_get_company_dsn($company);
            $prefix = perfex_saas_tenant_db_prefix($company->slug);

            // Connect to tenant DB
            // Note: This might be heavy if there are many tenants. 
            // In a real production environment with hundreds of tenants, 
            // this should be cached or loaded via AJAX on demand.
            // For now, we'll try to connect dynamically.

            // We use a temporary connection
            $temp_db = perfex_saas_load_ci_db_from_dsn($dsn, ['dbprefix' => $prefix]);

            if ($temp_db && $temp_db->conn_id) {
                $stats = $this->_get_db_stats($temp_db, $prefix);
                $temp_db->close();
                return $stats;
            } else {
                return ['error' => 'Could not connect'];
            }

        } catch (Throwable $e) {
            return ['error' => $e->getMessage()];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function _get_db_stats($db_instance, $prefix)
    {
        // Get generic DB stats
        // Size query (approximation for MySQL)
        $db_name = $db_instance->database;
        $sql = "SELECT 
            SUM(data_length + index_length) / 1024 / 1024 AS size_mb, 
            COUNT(*) as table_count 
            FROM information_schema.TABLES 
            WHERE table_schema = '$db_name'";

        // If we are sharing the same DB (multitenancy), we might want to filter by prefix if possible,
        // but information_schema shows physical tables. 
        // For multitenancy (prefix-based), we should filter tables by name.

        if (!empty($prefix)) {
            $sql .= " AND table_name LIKE '$prefix%'";
        }

        $query = $db_instance->query($sql);
        $result = $query->row();

        return [
            'size_mb' => isset($result->size_mb) ? round($result->size_mb, 2) : 0,
            'table_count' => isset($result->table_count) ? $result->table_count : 0
        ];
    }

    /**
     * Redirect helper used by the backup form which posts to this endpoint
     * and opens the actual backup URL in a new tab/window.
     */
    public function backup_redirect()
    {
        $slug = $this->input->post('backup_slug');
        if (empty($slug)) {
            show_404();
        }

        // The form targets _blank, so sending a Location header will navigate the new tab to the backup URL
        $backup_url = admin_url('ccx_db/backup/' . $slug);
        redirect($backup_url);
    }

    /**
     * Generate a simple SQL dump for the requested database (master or tenant slug)
     * Streams the output to avoid large memory usage where possible.
     *
     * @param string $slug
     */
    public function backup($slug = '')
    {
        if (empty($slug)) {
            show_404();
        }

        // Increase limits for large databases
        @ini_set('memory_limit', '-1');
        @set_time_limit(0);

        $db_info = $this->_get_db_connection($slug);
        if (isset($db_info['error'])) {
            show_error('Database connection error: ' . $db_info['error']);
        }

        $db = $db_info['conn'];

        // Prepare filename
        $filename = 'ccx_db_' . $slug . '_' . date('Y-m-d_H-i-s') . '.sql';

        // Send headers for download
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Start output
        echo "-- Database dump for: {$slug}\n";
        echo "-- Generated: " . date('c') . "\n\n";
        echo "SET FOREIGN_KEY_CHECKS=0;\n\n";

        $schema = $this->_get_db_schema($db, $db_info['prefix']);

        foreach ($schema as $name => $info) {
            $real_table = $info['real_name'];

            // Get CREATE TABLE
            $q = $db->query("SHOW CREATE TABLE `" . $real_table . "`");
            $row = $q->row_array();
            $create_sql = $row['Create Table'] ?? null;
            if ($create_sql) {
                echo "DROP TABLE IF EXISTS `{$real_table}`;\n";
                echo $create_sql . ";\n\n";
            }

            // Dump data in chunks
            $batch = 1000;
            $offset = 0;
            $cols = $info['columns'];
            if (empty($cols))
                continue;
            $cols_list = '`' . implode('`, `', $cols) . '`';

            do {
                $res = $db->query("SELECT $cols_list FROM `{$real_table}` LIMIT {$batch} OFFSET {$offset}");
                $rows = $res->result_array();
                if (empty($rows))
                    break;

                $values = [];
                foreach ($rows as $r) {
                    $vals = [];
                    foreach ($r as $v) {
                        if ($v === null) {
                            $vals[] = 'NULL';
                        } else {
                            $vals[] = "'" . $db->escape_str($v) . "'";
                        }
                    }
                    $values[] = '(' . implode(',', $vals) . ')';
                }

                if (!empty($values)) {
                    echo "INSERT INTO `{$real_table}` ($cols_list) VALUES \n" . implode(",\n", $values) . ";\n\n";
                    // Flush output
                    if (function_exists('ob_flush')) {
                        @ob_flush();
                    }
                    if (function_exists('flush')) {
                        @flush();
                    }
                }

                $offset += $batch;
            } while (count($rows) == $batch);
        }

        echo "SET FOREIGN_KEY_CHECKS=1;\n";

        // Close connection if temporary
        if (is_object($db) && $slug !== 'master') {
            $db->close();
        }

        // Stop further processing
        exit;
    }

    /**
     * Handle uploaded SQL file and restore into the target database (master or tenant)
     * Expects multipart POST with 'backup_file' and 'dest_slug'
     */
    public function restore_action()
    {
        // Only allow POST
        if ($this->input->method(true) !== 'POST') {
            show_404();
        }

        // Simple permission check
        if (!has_permission('settings', '', 'view')) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            return;
        }

        // Check inputs
        $dest_slug = $this->input->post('dest_slug');
        if (empty($dest_slug) || !isset($_FILES['backup_file'])) {
            echo json_encode(['success' => false, 'message' => 'Missing parameters or file']);
            return;
        }

        // Increase limits
        @ini_set('memory_limit', '-1');
        @set_time_limit(0);

        $file = $_FILES['backup_file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'File upload error']);
            return;
        }

        $tmp_path = $file['tmp_name'];
        if (!is_uploaded_file($tmp_path) && !file_exists($tmp_path)) {
            echo json_encode(['success' => false, 'message' => 'Uploaded file not found']);
            return;
        }

        // Get DB connection for destination
        $db_info = $this->_get_db_connection($dest_slug);
        if (isset($db_info['error'])) {
            echo json_encode(['success' => false, 'message' => 'DB connection error: ' . $db_info['error']]);
            return;
        }

        $db = $db_info['conn'];

        // Try to open file and execute statements line by line
        $handle = fopen($tmp_path, 'r');
        if ($handle === false) {
            echo json_encode(['success' => false, 'message' => 'Could not open uploaded file']);
            return;
        }

        $errors = [];
        $statement = '';

        // Disable foreign key checks while restoring
        @$db->query('SET FOREIGN_KEY_CHECKS=0');

        while (!feof($handle)) {
            $line = fgets($handle);
            if ($line === false)
                break;

            $trim = trim($line);
            // Skip comments and empty lines
            if ($trim === '' || strpos($trim, '--') === 0 || strpos($trim, '/*') === 0 || strpos($trim, '#') === 0) {
                continue;
            }

            $statement .= $line;

            // Check for end of statement - semicolon at end after trimming
            if (substr(rtrim($trim), -1) === ';') {
                // Execute statement
                try {
                    if (!@$db->query($statement)) {
                        $err = $db->error();
                        $errors[] = isset($err['message']) ? $err['message'] : 'Unknown DB error';
                    }
                } catch (Throwable $e) {
                    $errors[] = $e->getMessage();
                }

                // Reset
                $statement = '';
            }
        }

        fclose($handle);

        // Re-enable foreign key checks
        @$db->query('SET FOREIGN_KEY_CHECKS=1');

        // Close connection if temporary
        if (is_object($db) && $dest_slug !== 'master') {
            $db->close();
        }

        // Remove temp upload file if present (PHP will usually clean it up)
        if (file_exists($tmp_path)) {
            @unlink($tmp_path);
        }

        if (!empty($errors)) {
            echo json_encode(['success' => false, 'message' => 'Restore completed with errors: ' . implode(' | ', $errors)]);
            return;
        }

        echo json_encode(['success' => true, 'message' => 'Database restored successfully']);
        return;
    }
    public function get_tenant_modules_json($slug)
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $db_info = $this->_get_db_connection($slug);
        if (isset($db_info['error'])) {
            echo json_encode(['error' => $db_info['error']]);
            return;
        }

        $db = $db_info['conn'];
        $prefix = $db_info['prefix']; // This is usually empty for within the tenant DB connection if we use the DSN properly, but let's check table existence.

        // We need to check if tblmodules exists
        // In DSN connection, prefix might be handled by CI, so we just use 'tblmodules' usually
        // But if we are in a shared DB setup, we might need the prefix.
        // Let's try simple 'tblmodules' first as _get_db_connection should set the prefix in the DB object.

        // Check if table exists
        if (!$db->table_exists('modules')) {
            echo json_encode(['error' => 'Table modules not found in tenant database.']);
            if (is_object($db) && $slug !== 'master')
                $db->close();
            return;
        }

        $modules = $db->get('modules')->result_array();

        if (is_object($db) && $slug !== 'master')
            $db->close();

        echo json_encode($modules);
    }

    public function update_tenant_module_action()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $slug = $this->input->post('slug');
        $id = $this->input->post('id');
        $active = $this->input->post('active');

        if (!$slug || !$id) {
            echo json_encode(['success' => false, 'message' => 'Missing parameters']);
            return;
        }

        $db_info = $this->_get_db_connection($slug);
        if (isset($db_info['error'])) {
            echo json_encode(['success' => false, 'message' => $db_info['error']]);
            return;
        }

        $db = $db_info['conn'];

        $data = ['active' => $active];
        $db->where('id', $id);
        if ($db->update('modules', $data)) {
            echo json_encode(['success' => true, 'message' => 'Module updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update module']);
        }

        if (is_object($db) && $slug !== 'master')
            $db->close();
    }
    /**
     * Helper to get table names from a module's install.php file
     */
    private function _get_module_tables($module_name)
    {
        $module_path = APP_MODULES_PATH . $module_name . '/install.php';
        if (!file_exists($module_path)) {
            return [];
        }

        $content = file_get_contents($module_path);
        $tables = [];

        // Regex to find CREATE TABLE statements
        // Matches `db_prefix() . 'tablename'` or `tbltablename`
        // We need to be flexible.
        // Pattern 1: `CREATE TABLE [IF NOT EXISTS] `[prefix]tablename`
        // Pattern 2: `CREATE TABLE [IF NOT EXISTS] `tablename`

        // Let's look for "CREATE TABLE" and then try to extract the table name
        // PHP's regex might be tricky if quotes/concatenation are used differently.
        // A robust way often used in Perfex is scanning for specific db_prefix usage.

        // Standard Perfex pattern: db_prefix() . 'tablename'
        // Pattern: /db_prefix\(\)\s*\.\s*['"]([a-zA-Z0-9_]+)['"]/
        preg_match_all("/db_prefix\(\)\s*\.\s*['\"]([a-zA-Z0-9_]+)['\"]/", $content, $matches);
        if (!empty($matches[1])) {
            $tables = array_merge($tables, $matches[1]);
        }

        // Hardcoded 'tbl...' pattern
        // Pattern: /['`](tbl[a-zA-Z0-9_]+)['`]/
        preg_match_all("/['`](tbl[a-zA-Z0-9_]+)['`]/", $content, $matches_hardcoded);
        if (!empty($matches_hardcoded[1])) {
            // Remove 'tbl' prefix to normalize if we are going to prepend it later, 
            // OR keep it if we treat it as full name.
            // The system uses db_prefix() which defaults to 'tbl'.
            // If the regex caught 'tblsomething', we should check if db_prefix is 'tbl'.
            $tables = array_merge($tables, $matches_hardcoded[1]);
        }

        return array_unique($tables);
    }

    public function export_module_action($slug = '', $module_name = '')
    {
        // Fallback to POST/GET if not valid in arguments (CI3 URI segments)
        if (empty($slug)) {
            $slug = $this->input->post('slug');
            if (empty($slug))
                $slug = $this->input->get('slug');
        }
        if (empty($module_name)) {
            $module_name = $this->input->post('module_name');
            if (empty($module_name))
                $module_name = $this->input->get('module_name');
        }

        if (empty($slug) || empty($module_name)) {
            show_404();
        }

        // Increase limits
        @ini_set('memory_limit', '-1');
        @set_time_limit(0);

        $tables_raw = $this->_get_module_tables($module_name);
        if (empty($tables_raw)) {
            show_error('No tables found in install.php for module: ' . $module_name);
        }

        $db_info = $this->_get_db_connection($slug);
        if (isset($db_info['error'])) {
            show_error('Database connection error: ' . $db_info['error']);
        }

        $db = $db_info['conn'];
        $prefix = $db_info['prefix'];

        // Filter valid tables and map to real names
        $valid_tables = [];
        // We need to check if these tables actually exist in the DB
        // The _get_module_tables returns 'tablename' (without prefix implies db_prefix w/o 'tbl' usually, OR 'tbltablename')
        // We need to construct the real table name in the tenant DB.

        // Tenant Prefix Logic:
        // If tenant is master: db_prefix() . table (or just table if it has tbl)
        // If tenant is not master: tenant_prefix . table 

        // Let's just list all tables in the DB and fuzzy match?
        // Or construct derived names?
        // perfex_saas_tenant_db_prefix($slug) returns 'tenant_slug_'
        // The tables in install.php might be 'appointments' (implied tblappointments) or 'tblappointments'

        $schema = $this->_get_db_schema($db, $prefix); // Keys are normalized names (without prefix) if prefix is passed
        // But _get_db_schema logic:
        // $normalized_name = substr($table_name, strlen($prefix));
        // So if prefix is 'tenant_1_', table is 'tenant_1_tblappointments', normalized is 'tblappointments'

        // Our $tables_raw might contain 'appointments' or 'tblappointments'.
        // We should normalize $tables_raw to 'tbl...' format if possible, or matches schema keys.

        foreach ($tables_raw as $t) {
            // If $t is 'appointments', map to 'tblappointments' ? 
            // Perfex usually assumes 'tbl' prefix.
            $candidate = $t;
            if (strpos($t, 'tbl') !== 0) {
                $candidate = 'tbl' . $t;
            }

            // Check if $candidate exists in schema keys
            // Schema keys from _get_db_schema are stripped of 'tenant_prefix_', so they look like 'tblappointments'
            if (array_key_exists($candidate, $schema)) {
                $valid_tables[] = $schema[$candidate]['real_name'];
            } elseif (array_key_exists($t, $schema)) {
                $valid_tables[] = $schema[$t]['real_name'];
            }
        }

        if (empty($valid_tables)) {
            show_error('None of the module tables were found in the database.');
        }

        // Prepare filename
        $filename = 'ccx_module_' . $module_name . '_' . $slug . '_' . date('Y-m-d_H-i-s') . '.sql';

        // Send headers
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo "-- Module Export: {$module_name}\n";
        echo "-- Tenant: {$slug}\n";
        echo "-- Generated: " . date('c') . "\n\n";
        echo "SET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach ($valid_tables as $real_table) {
            // Get CREATE TABLE
            $q = $db->query("SHOW CREATE TABLE `" . $real_table . "`");
            $row = $q->row_array();
            $create_sql = $row['Create Table'] ?? null;
            if ($create_sql) {
                echo "DROP TABLE IF EXISTS `{$real_table}`;\n";
                echo $create_sql . ";\n\n";
            }

            // Dump data
            $batch = 1000;
            $offset = 0;

            // Get columns to ensure order
            $cols_q = $db->query("SHOW COLUMNS FROM `$real_table`");
            $cols = [];
            foreach ($cols_q->result() as $col)
                $cols[] = $col->Field;
            if (empty($cols))
                continue;

            $cols_list = '`' . implode('`, `', $cols) . '`';

            do {
                $res = $db->query("SELECT $cols_list FROM `{$real_table}` LIMIT {$batch} OFFSET {$offset}");
                $rows = $res->result_array();
                if (empty($rows))
                    break;

                $values = [];
                foreach ($rows as $r) {
                    $vals = [];
                    foreach ($r as $v) {
                        if ($v === null)
                            $vals[] = 'NULL';
                        else
                            $vals[] = "'" . $db->escape_str($v) . "'";
                    }
                    $values[] = '(' . implode(',', $vals) . ')';
                }

                if (!empty($values)) {
                    echo "INSERT INTO `{$real_table}` ($cols_list) VALUES \n" . implode(",\n", $values) . ";\n\n";
                }

                $offset += $batch;
            } while (count($rows) == $batch);
        }

        echo "SET FOREIGN_KEY_CHECKS=1;\n";

        if (is_object($db) && $slug !== 'master') {
            $db->close();
        }
        exit;
    }

    public function import_module_action()
    {
        // Only allow POST
        if ($this->input->method(true) !== 'POST') {
            show_404();
        }

        $dest_slug = $this->input->post('dest_slug');
        $module_name = $this->input->post('module_name');

        if (empty($dest_slug) || empty($module_name) || !isset($_FILES['backup_file'])) {
            echo json_encode(['success' => false, 'message' => 'Missing parameters: Destination or Module Name or File']);
            return;
        }

        // Increase limits
        @ini_set('memory_limit', '-1');
        @set_time_limit(0);

        $file = $_FILES['backup_file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'File upload error: ' . $file['error']]);
            return;
        }

        $tmp_path = $file['tmp_name'];
        if (!is_uploaded_file($tmp_path) && !file_exists($tmp_path)) {
            echo json_encode(['success' => false, 'message' => 'Uploaded file not found']);
            return;
        }

        log_message('error', "[Ccx_db Import] Starting import. Module: $module_name, Dest: $dest_slug");

        // Validation: Check tables in module vs tables in SQL
        $expected_tables = $this->_get_module_tables($module_name);

        // Normalize expected to 'tbl' prefix for comparison if not already
        $normalized_expected = [];
        foreach ($expected_tables as $t) {
            if (strpos($t, 'tbl') !== 0)
                $normalized_expected[] = 'tbl' . $t;
            else
                $normalized_expected[] = $t;
        }
        $expected_tables = $normalized_expected;

        log_message('error', "[Ccx_db Import] Expected Tables: " . implode(', ', $expected_tables));

        if (empty($expected_tables)) {
            echo json_encode(['success' => false, 'message' => "Module '$module_name' has no tables defined (or install.php not found)."]);
            return;
        }

        $dest_db_info = $this->_get_db_connection($dest_slug);
        if (isset($dest_db_info['error'])) {
            echo json_encode(['success' => false, 'message' => $dest_db_info['error']]);
            return;
        }
        $db = $dest_db_info['conn'];
        $dest_prefix = $dest_db_info['prefix'];

        log_message('error', "[Ccx_db Import] Destination Prefix: $dest_prefix");

        // Re-read file and process line by line
        $handle = fopen($tmp_path, 'r');
        $temp_out = tempnam(sys_get_temp_dir(), 'ccx_import_');
        $handle_out = fopen($temp_out, 'w');

        $stats = [
            'tables' => [],
            'rows_inserted' => 0,
            'tables_renamed' => []
        ];

        $found_any = false;

        while (!feof($handle)) {
            $line = fgets($handle);
            if ($line === false)
                break;

            // Replace logic
            // We match everything in backticks
            $line = preg_replace_callback('/`([a-zA-Z0-9_]+)`/', function ($m) use ($expected_tables, $dest_prefix, &$stats, &$found_any) {
                $full_name = $m[1];
                foreach ($expected_tables as $exp) { // $exp = 'tblappointments'
                    // Check if full_name ends with exp
                    // If full_name is 'tblappointments', it ends with exp.
                    // If 'tenant_1_tblappointments', it ends with exp.
                    if (substr($full_name, -strlen($exp)) === $exp) {
                        $found_any = true;

                        // Prevent Double Prefix (e.g. dev_ + dev_tblitems -> dev_dev_tblitems)
                        // If $exp matches the END of $full_name, we replace the whole $full_name with new prefix + exp.
                        // BUT if dest_prefix is 'dev_' and $full_name is ALREADY 'dev_tblitems', 
                        // we should just return 'dev_tblitems'. 

                        // Let's Construct the Desired Name
                        $desired_name = $dest_prefix . $exp;

                        // Fix: If dest_prefix ends in 'tbl' and exp starts with 'tbl', dedup 'tbl'
                        // (Usually prefix is 'tenant_' and exp is 'tblfoo', so 'tenant_tblfoo' is fine)
                        // But if prefix is 'tbl' (master?) and exp is 'tblfoo', we get 'tbltblfoo'.
                        if (substr($dest_prefix, -3) === 'tbl' && substr($exp, 0, 3) === 'tbl') {
                            $desired_name = $dest_prefix . substr($exp, 3);
                        }

                        if (!in_array($exp, $stats['tables'])) {
                            $stats['tables'][] = $exp;
                            $stats['tables_renamed'][$full_name] = $desired_name;
                        }
                        return "`$desired_name`";
                    }
                }
                return $m[0]; // No change
            }, $line);

            // Count inserts
            if (stripos($line, 'INSERT INTO') !== false) {
                $stats['rows_inserted'] += substr_count($line, '),(') + 1;
            }

            fwrite($handle_out, $line);
        }

        fclose($handle);
        fclose($handle_out);

        if (!$found_any) {
            unlink($temp_out);
            $msg = "Validation Failed: The uploaded file does not appear to contain tables for module '{$module_name}'. <br>Expected: " . implode(', ', $expected_tables);
            log_message('error', "[Ccx_db Import] $msg");
            echo json_encode(['success' => false, 'message' => $msg]);
            return;
        }

        log_message('error', "[Ccx_db Import] Tables Renamed: " . print_r($stats['tables_renamed'], true));

        // Now run the processed file
        $db->query('SET FOREIGN_KEY_CHECKS=0');

        $handle_sql = fopen($temp_out, 'r');
        $sql = '';
        $errors = [];
        $statement_count = 0;

        while (!feof($handle_sql)) {
            $line = fgets($handle_sql);
            if ($line === false)
                break;

            // Fix Collation Compatibility
            $line = str_replace(['utf8mb4_0900_ai_ci', 'utf8mb4_0900_as_cs'], 'utf8mb4_general_ci', $line);

            // Allow retry by ignoring duplicates
            $line = str_replace('INSERT INTO', 'INSERT IGNORE INTO', $line);

            $trim = trim($line);
            if ($trim === '' || strpos($trim, '--') === 0)
                continue;

            $sql .= $line;
            if (substr(rtrim($trim), -1) === ';') {
                $statement_count++;
                if (!$db->query($sql)) {
                    $err = $db->error();
                    $errors[] = $err['message'];
                    log_message('error', "[Ccx_db Import] SQL Error: " . $err['message'] . " | Statement: " . substr($sql, 0, 100) . "...");
                }
                $sql = '';
            }
        }
        fclose($handle_sql);
        unlink($temp_out);

        $db->query('SET FOREIGN_KEY_CHECKS=1');

        if (is_object($db) && $dest_slug !== 'master')
            $db->close();

        if (!empty($errors)) {
            echo json_encode(['success' => false, 'message' => 'Errors: ' . implode(', ', $errors)]);
        } else {
            $msg = "Import Successful!<br>";
            $msg .= "Tables updated: " . count($stats['tables']) . "<br>";
            $msg .= "Approx records inserted: " . $stats['rows_inserted'] . "<br>";
            $msg .= "Statements Executed: $statement_count<br><hr>";

            // Preview Data
            $msg .= "<h4>Data Preview:</h4>";
            foreach ($stats['tables'] as $tbl) {
                // Construct Real Table Name for Preview
                $real_table = $dest_prefix . $tbl;
                if (substr($dest_prefix, -3) === 'tbl' && substr($tbl, 0, 3) === 'tbl') {
                    $real_table = $dest_prefix . substr($tbl, 3);
                }

                $msg .= "<strong>Table: {$real_table}</strong><br>";
                $res = $db->query("SELECT * FROM `{$real_table}` LIMIT 3");
                if ($res) {
                    $rows = $res->result_array();
                    if (!empty($rows)) {
                        $msg .= "<div class='table-responsive'><table class='table table-striped table-bordered table-xs'>";
                        // Header
                        $msg .= "<thead><tr>";
                        foreach (array_keys($rows[0]) as $col) {
                            $msg .= "<th>{$col}</th>";
                        }
                        $msg .= "</tr></thead>";
                        // Body
                        $msg .= "<tbody>";
                        foreach ($rows as $row) {
                            $msg .= "<tr>";
                            foreach ($row as $val) {
                                // Truncate long values
                                $disp = strip_tags((string) $val);
                                if (strlen($disp) > 50)
                                    $disp = substr($disp, 0, 50) . '...';
                                $msg .= "<td>{$disp}</td>";
                            }
                            $msg .= "</tr>";
                        }
                        $msg .= "</tbody></table></div>";
                    } else {
                        $msg .= "<em>No records found.</em><br>";
                    }
                } else {
                    $msg .= "<em>Error querying table.</em><br>";
                }
                $msg .= "<br>";
            }

            // Post-Import Fix for Tests Master
            if ($module_name === 'tests_master') {
                // 1. Get or Create 'Tests' group in destination
                $groups_tbl = $dest_prefix . 'items_groups';
                $items_tbl = $dest_prefix . 'items'; // Hardcode expected items table

                // Verify items table exists
                if ($db->table_exists($items_tbl)) {
                    $res = $db->query("SELECT id FROM `$groups_tbl` WHERE name = 'Tests'");
                    $group_row = $res ? $res->row() : null;

                    $tests_group_id = 0;
                    if ($group_row) {
                        $tests_group_id = $group_row->id;
                    } else {
                        $db->query("INSERT INTO `$groups_tbl` (name) VALUES ('Tests')");
                        $tests_group_id = $db->insert_id();
                    }

                    if ($tests_group_id) {
                        // Table names
                        $tmpl_word = $dest_prefix . 'tests_word_templates';
                        $tmpl_fixed = $dest_prefix . 'tests_fixed_templates';

                        // Flags: test_method_id > 0, or linked in templates
                        $sql_fix = "UPDATE `$items_tbl` 
                                    SET group_id = $tests_group_id 
                                    WHERE id IN (SELECT test_id FROM `$tmpl_word`) 
                                       OR id IN (SELECT test_id FROM `$tmpl_fixed`)
                                       OR test_method_id > 0
                                       OR is_blood_sample_required = 1";

                        $db->query($sql_fix);
                        $affected = $db->affected_rows();
                        if ($affected > 0) {
                            $msg .= "<br><strong>Post-Import Fix:</strong> Assigned $affected items to 'Tests' group (ID: $tests_group_id).<br>";
                        }
                    }
                }
            }

            echo json_encode(['success' => true, 'message' => $msg]);
        }
    }

    // =========================================================================
    // AUTO TENANTS DATA BACKUP
    // =========================================================================

    /**
     * Self-heal: ensure the auto backup settings table exists
     */
    private function _ensure_auto_backup_table()
    {
        if (!$this->db->table_exists(db_prefix() . 'ccx_auto_backup_settings')) {
            $this->db->query("CREATE TABLE IF NOT EXISTS `" . db_prefix() . "ccx_auto_backup_settings` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `tenant_slug` VARCHAR(255) NOT NULL,
                `auto_backup_enabled` TINYINT(1) NOT NULL DEFAULT 1,
                `last_backup_at` DATETIME DEFAULT NULL,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `tenant_slug` (`tenant_slug`)
            ) ENGINE=InnoDB DEFAULT CHARSET=" . $this->db->char_set . ";");
        } else {
            // Self-heal: add last_backup_at column if missing
            if (!$this->db->field_exists('last_backup_at', db_prefix() . 'ccx_auto_backup_settings')) {
                $this->db->query("ALTER TABLE `" . db_prefix() . "ccx_auto_backup_settings` ADD COLUMN `last_backup_at` DATETIME DEFAULT NULL AFTER `auto_backup_enabled`");
            }
        }
    }

    /**
     * AJAX: Get all tenants with their auto-backup enabled status.
     * Auto-creates rows (default enabled) for tenants missing from the table.
     */
    public function get_auto_backup_tenants()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $this->_ensure_auto_backup_table();

        // Fetch all companies
        if (!table_exists('perfex_saas_companies')) {
            echo json_encode(['error' => 'SaaS module not found']);
            return;
        }

        $this->load->model('perfex_saas/perfex_saas_model');
        $companies = $this->perfex_saas_model->companies();

        $results = [];

        // Master DB is always first in the list — self-heal its settings row
        $master_row = $this->db->where('tenant_slug', 'master')
            ->get(db_prefix() . 'ccx_auto_backup_settings')
            ->row();
        if (!$master_row) {
            $this->db->insert(db_prefix() . 'ccx_auto_backup_settings', [
                'tenant_slug' => 'master',
                'auto_backup_enabled' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
        $results[] = [
            'name' => 'Master DB (' . $this->db->database . ')',
            'slug' => 'master',
            'auto_backup_enabled' => $master_row ? (int) $master_row->auto_backup_enabled : 1,
            'last_backup_at' => $master_row->last_backup_at ?? null,
        ];

        foreach ($companies as $company) {
            // Check if row exists
            $row = $this->db->where('tenant_slug', $company->slug)
                ->get(db_prefix() . 'ccx_auto_backup_settings')
                ->row();

            if (!$row) {
                // Auto-create with enabled = 1
                $this->db->insert(db_prefix() . 'ccx_auto_backup_settings', [
                    'tenant_slug' => $company->slug,
                    'auto_backup_enabled' => 1,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                $enabled = 1;
                $last_backup_at = null;
            } else {
                $enabled = (int) $row->auto_backup_enabled;
                $last_backup_at = $row->last_backup_at ?? null;
            }

            $results[] = [
                'name' => $company->name,
                'slug' => $company->slug,
                'auto_backup_enabled' => $enabled,
                'last_backup_at' => $last_backup_at,
            ];
        }

        // Include both cron schedules for next-backup computation
        $incr_cron_schedule = get_option('ccx_backup_incr_cron_schedule') ?: '* * * * *';
        $full_cron_schedule = get_option('ccx_backup_full_cron_schedule') ?: '0 2 * * *';

        echo json_encode([
            'tenants' => $results,
            'incr_cron_schedule' => $incr_cron_schedule,
            'full_cron_schedule' => $full_cron_schedule,
            'server_time' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * AJAX POST: Toggle auto-backup enabled/disabled for a tenant.
     */
    public function toggle_auto_backup()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $slug = $this->input->post('slug');
        $enabled = (int) $this->input->post('enabled');

        if (empty($slug)) {
            echo json_encode(['success' => false, 'message' => 'Missing tenant slug']);
            return;
        }

        $this->_ensure_auto_backup_table();

        // Upsert
        $exists = $this->db->where('tenant_slug', $slug)
            ->count_all_results(db_prefix() . 'ccx_auto_backup_settings');

        if ($exists) {
            $this->db->where('tenant_slug', $slug)
                ->update(db_prefix() . 'ccx_auto_backup_settings', [
                    'auto_backup_enabled' => $enabled,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
        } else {
            $this->db->insert(db_prefix() . 'ccx_auto_backup_settings', [
                'tenant_slug' => $slug,
                'auto_backup_enabled' => $enabled,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Auto backup ' . ($enabled ? 'enabled' : 'disabled') . ' for ' . $slug
        ]);
    }

    /**
     * AJAX: Return all backup configuration options as JSON.
     */
    public function get_backup_settings()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $settings = [
            'ccx_backup_method' => get_option('ccx_backup_method') ?: 'ftp',
            'ccx_backup_ftp_host' => get_option('ccx_backup_ftp_host'),
            'ccx_backup_ftp_port' => get_option('ccx_backup_ftp_port') ?: '21',
            'ccx_backup_ftp_username' => get_option('ccx_backup_ftp_username'),
            // Secrets are write-only: never sent to the browser, only an "is set" flag
            'ccx_backup_ftp_password' => '',
            'ccx_backup_ftp_password_set' => ccx_db_get_secret('ccx_backup_ftp_password') !== '',
            'ccx_backup_ftp_path' => get_option('ccx_backup_ftp_path') ?: '/backups',
            'ccx_backup_gdrive_client_id' => get_option('ccx_backup_gdrive_client_id'),
            'ccx_backup_gdrive_client_secret' => '',
            'ccx_backup_gdrive_client_secret_set' => ccx_db_get_secret('ccx_backup_gdrive_client_secret') !== '',
            'ccx_backup_gdrive_refresh_token' => '',
            'ccx_backup_gdrive_refresh_token_set' => ccx_db_get_secret('ccx_backup_gdrive_refresh_token') !== '',
            'ccx_backup_gdrive_folder_id' => get_option('ccx_backup_gdrive_folder_id'),
            // Dual schedule
            'ccx_backup_incr_cron_frequency' => get_option('ccx_backup_incr_cron_frequency') ?: 'every_minute',
            'ccx_backup_incr_cron_schedule' => get_option('ccx_backup_incr_cron_schedule') ?: '* * * * *',
            'ccx_backup_full_cron_frequency' => get_option('ccx_backup_full_cron_frequency') ?: 'daily',
            'ccx_backup_full_cron_schedule' => get_option('ccx_backup_full_cron_schedule') ?: '0 2 * * *',
            // Advanced settings
            'ccx_backup_retention_days' => get_option('ccx_backup_retention_days') ?: '30',
            'ccx_backup_auto_delete' => get_option('ccx_backup_auto_delete') ?: '0',
            'ccx_backup_compression' => get_option('ccx_backup_compression') ?: '0',
            'ccx_backup_max_backups' => get_option('ccx_backup_max_backups') ?: '10',
            'ccx_backup_notify_email' => get_option('ccx_backup_notify_email') ?: '',
            'ccx_backup_notify_on_failure' => get_option('ccx_backup_notify_on_failure') ?: '0',
            'ccx_backup_notify_on_success' => get_option('ccx_backup_notify_on_success') ?: '0',
        ];

        echo json_encode($settings);
    }

    /**
     * AJAX POST: Save backup configuration options.
     */
    public function save_backup_settings()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $fields = [
            'ccx_backup_method',
            'ccx_backup_ftp_host',
            'ccx_backup_ftp_port',
            'ccx_backup_ftp_username',
            'ccx_backup_ftp_path',
            'ccx_backup_gdrive_client_id',
            'ccx_backup_gdrive_folder_id',
            // Dual schedule
            'ccx_backup_incr_cron_frequency',
            'ccx_backup_incr_cron_schedule',
            'ccx_backup_full_cron_frequency',
            'ccx_backup_full_cron_schedule',
            // Advanced settings
            'ccx_backup_retention_days',
            'ccx_backup_max_backups',
            'ccx_backup_notify_email',
        ];

        foreach ($fields as $field) {
            $value = $this->input->post($field);
            if ($value !== null) {
                if (is_array($value)) {
                    $value = json_encode($value);
                }
                update_option($field, $value);
            }
        }

        // Secret fields: stored encrypted, blank means "keep the saved value"
        $secrets = [
            'ccx_backup_ftp_password',
            'ccx_backup_gdrive_client_secret',
            'ccx_backup_gdrive_refresh_token',
        ];
        foreach ($secrets as $secret) {
            $value = $this->input->post($secret, false);
            if ($value !== null && $value !== '') {
                update_option($secret, ccx_db_encrypt_secret($value));
            }
        }

        // Handle checkbox fields (not sent when unchecked)
        $checkboxes = [
            'ccx_backup_auto_delete',
            'ccx_backup_compression',
            'ccx_backup_notify_on_failure',
            'ccx_backup_notify_on_success',
        ];
        foreach ($checkboxes as $cb) {
            $val = $this->input->post($cb) ? '1' : '0';
            update_option($cb, $val);
        }

        echo json_encode(['success' => true, 'message' => 'Backup settings saved successfully']);
    }

    /**
     * Self-heal: ensure the backup logs table exists
     */
    private function _ensure_backup_logs_table()
    {
        if (!$this->db->table_exists(db_prefix() . 'ccx_auto_backup_logs')) {
            $this->db->query("CREATE TABLE IF NOT EXISTS `" . db_prefix() . "ccx_auto_backup_logs` (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=" . $this->db->char_set . ";");
        } else {
            // Self-heal: legacy ENUM('success','error') can't store the 'skipped' status
            $col = $this->db->query("SHOW COLUMNS FROM `" . db_prefix() . "ccx_auto_backup_logs` LIKE 'status'")->row();
            if ($col && stripos($col->Type, 'enum') !== false) {
                $this->db->query("ALTER TABLE `" . db_prefix() . "ccx_auto_backup_logs` MODIFY `status` VARCHAR(20) NOT NULL DEFAULT 'success'");
            }
        }
    }

    /**
     * Insert a log entry
     */
    private function _log_backup_event($action, $method, $status, $message, $tenant_slug = null)
    {
        $this->_ensure_backup_logs_table();
        $this->db->insert(db_prefix() . 'ccx_auto_backup_logs', [
            'tenant_slug' => $tenant_slug,
            'action' => $action,
            'method' => $method,
            'status' => $status,
            'message' => $message,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * AJAX POST: Test backup connection by uploading a small test file.
     */
    public function test_backup_connection()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $methods = $this->input->post('method');
        if (empty($methods)) {
            echo json_encode(['success' => false, 'message' => 'No backup method selected']);
            return;
        }
        if (!is_array($methods)) {
            $methods = [$methods];
        }

        $results = [];
        $overall_success = true;

        if (in_array('ftp', $methods)) {
            try {
                $host = $this->input->post('ftp_host');
                $port = (int) ($this->input->post('ftp_port') ?: 21);
                $user = $this->input->post('ftp_username');
                $pass = $this->input->post('ftp_password', false);
                if ($pass === null || $pass === '') {
                    // Field left blank — test with the saved (encrypted) password
                    $pass = ccx_db_get_secret('ccx_backup_ftp_password');
                }
                $path = $this->input->post('ftp_path') ?: '/';

                $host = preg_replace('#^(ftps?://)#i', '', trim($host));
                $host = rtrim($host, '/');
                if (strpos($host, ':') !== false) {
                    $parts = explode(':', $host);
                    $host = $parts[0];
                    if (!empty($parts[1]) && is_numeric($parts[1])) {
                        $port = (int) $parts[1];
                    }
                }

                if (empty($host) || empty($user)) {
                    throw new Exception('FTP Host and Username are required');
                }

                $test_content = "CCX Auto Backup Test File\nGenerated: " . date('Y-m-d H:i:s') . "\nThis file can be safely deleted.";
                $test_filename = 'ccx_backup_test_ftp_' . date('Ymd_His') . '.txt';
                $tmp_file = tempnam(sys_get_temp_dir(), 'ccx_test_');
                file_put_contents($tmp_file, $test_content);

                @error_clear_last();
                $ftp = @ftp_connect($host, $port, 15);
                if (!$ftp) {
                    $last_error = error_get_last();
                    $detail = $last_error ? ' — PHP Error: ' . $last_error['message'] : '';
                    throw new Exception('Could not connect to FTP server at ' . $host . ':' . $port . $detail);
                }

                @error_clear_last();
                $login = @ftp_login($ftp, $user, $pass);
                if (!$login) {
                    $last_error = error_get_last();
                    $detail = $last_error ? ' — PHP Error: ' . $last_error['message'] : '';
                    ftp_close($ftp);
                    throw new Exception('FTP login failed for user "' . $user . '". Check username and password.' . $detail);
                }

                ftp_pasv($ftp, true);

                $path = rtrim($path, '/');
                if (!empty($path) && $path !== '/') {
                    if (!@ftp_chdir($ftp, $path)) {
                        $parts = explode('/', trim($path, '/'));
                        $currentDir = '';
                        foreach ($parts as $part) {
                            $currentDir .= '/' . $part;
                            if (!@ftp_chdir($ftp, $currentDir)) {
                                @ftp_mkdir($ftp, $currentDir);
                                if (!@ftp_chdir($ftp, $currentDir)) {
                                    ftp_close($ftp);
                                    throw new Exception('Could not create/access directory: ' . $currentDir);
                                }
                            }
                        }
                    }
                }

                $remote_file = $path . '/' . $test_filename;
                $upload = @ftp_put($ftp, $remote_file, $tmp_file, FTP_ASCII);
                if (!$upload) {
                    ftp_close($ftp);
                    throw new Exception('Failed to upload test file to: ' . $remote_file);
                }

                ftp_close($ftp);
                @unlink($tmp_file);

                $msg = 'FTP connection successful! Test file "' . $test_filename . '" uploaded to ' . $host . ':' . $path;
                $this->_log_backup_event('test_connection', 'ftp', 'success', $msg);
                $results[] = '<strong>FTP:</strong> <span class="text-success"><i class="fa fa-check"></i> ' . $msg . '</span>';
            } catch (Exception $e) {
                if (isset($tmp_file) && file_exists($tmp_file)) @unlink($tmp_file);
                $this->_log_backup_event('test_connection', 'ftp', 'error', $e->getMessage());
                $results[] = '<strong>FTP:</strong> <span class="text-danger"><i class="fa fa-times"></i> ' . $e->getMessage() . '</span>';
                $overall_success = false;
            }
        }

        if (in_array('google_drive', $methods)) {
            try {
                $client_id = $this->input->post('gdrive_client_id');
                $client_secret = $this->input->post('gdrive_client_secret', false);
                if ($client_secret === null || $client_secret === '') {
                    $client_secret = ccx_db_get_secret('ccx_backup_gdrive_client_secret');
                }
                $refresh_token = $this->input->post('gdrive_refresh_token', false);
                if ($refresh_token === null || $refresh_token === '') {
                    $refresh_token = ccx_db_get_secret('ccx_backup_gdrive_refresh_token');
                }
                $folder_id = $this->input->post('gdrive_folder_id');

                if (empty($client_id) || empty($client_secret) || empty($refresh_token)) {
                    throw new Exception('Client ID, Client Secret, and Refresh Token are required');
                }

                $token_url = 'https://oauth2.googleapis.com/token';
                $token_data = [
                    'client_id' => $client_id,
                    'client_secret' => $client_secret,
                    'refresh_token' => $refresh_token,
                    'grant_type' => 'refresh_token',
                ];

                $ch = curl_init($token_url);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($token_data));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                $token_response = curl_exec($ch);
                $curl_error = curl_error($ch);
                curl_close($ch);

                if (!$token_response) throw new Exception('Failed to contact Google OAuth: ' . $curl_error);

                $token_json = json_decode($token_response, true);
                if (empty($token_json['access_token'])) {
                    $error_desc = $token_json['error_description'] ?? ($token_json['error'] ?? 'Unknown error');
                    throw new Exception('Failed to get access token: ' . $error_desc);
                }
                $access_token = $token_json['access_token'];

                $test_content = "CCX Auto Backup Test File\nGenerated: " . date('Y-m-d H:i:s');
                $test_filename = 'ccx_backup_test_gdrive_' . date('Ymd_His') . '.txt';

                $metadata = ['name' => $test_filename, 'mimeType' => 'text/plain'];
                if (!empty($folder_id)) {
                    $metadata['parents'] = [$folder_id];
                }

                $boundary = 'ccx_boundary_' . uniqid();
                $body = "--{$boundary}\r\n";
                $body .= "Content-Type: application/json; charset=UTF-8\r\n\r\n";
                $body .= json_encode($metadata) . "\r\n";
                $body .= "--{$boundary}\r\n";
                $body .= "Content-Type: text/plain\r\n\r\n";
                $body .= $test_content . "\r\n";
                $body .= "--{$boundary}--";

                $upload_url = 'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart';
                $ch = curl_init($upload_url);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Authorization: Bearer ' . $access_token,
                    'Content-Type: multipart/related; boundary=' . $boundary,
                ]);
                $upload_response = curl_exec($ch);
                $upload_error = curl_error($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($http_code !== 200) {
                    $err = json_decode($upload_response, true);
                    $err_msg = $err['error']['message'] ?? ($upload_error ?: 'Upload failed (HTTP ' . $http_code . ')');
                    throw new Exception('Google Drive upload failed: ' . $err_msg);
                }

                $file_data = json_decode($upload_response, true);
                $uploaded_file_id = $file_data['id'] ?? null;

                if ($uploaded_file_id) {
                    $delete_url = 'https://www.googleapis.com/drive/v3/files/' . $uploaded_file_id;
                    $ch = curl_init($delete_url);
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'Authorization: Bearer ' . $access_token,
                    ]);
                    curl_exec($ch);
                    curl_close($ch);
                }

                $msg = 'Google Drive connection successful! Test file uploaded and removed.';
                $this->_log_backup_event('test_connection', 'google_drive', 'success', $msg);
                $results[] = '<strong>Google Drive:</strong> <span class="text-success"><i class="fa fa-check"></i> ' . $msg . '</span>';
            } catch (Exception $e) {
                $this->_log_backup_event('test_connection', 'google_drive', 'error', $e->getMessage());
                $results[] = '<strong>Google Drive:</strong> <span class="text-danger"><i class="fa fa-times"></i> ' . $e->getMessage() . '</span>';
                $overall_success = false;
            }
        }

        echo json_encode(['success' => $overall_success, 'message' => implode('<br><br>', $results)]);
    }

    /**
     * AJAX: Return backup logs as JSON. Supports optional filters.
     */
    public function get_backup_logs()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $this->_ensure_backup_logs_table();

        $limit = (int) ($this->input->get('limit') ?: 100);
        $action_filter = $this->input->get('action'); // 'test_connection', 'auto_backup', etc.

        $this->db->order_by('created_at', 'DESC');
        $this->db->limit($limit);

        if (!empty($action_filter)) {
            $this->db->where('action', $action_filter);
        }

        $logs = $this->db->get(db_prefix() . 'ccx_auto_backup_logs')->result_array();

        echo json_encode($logs);
    }

    /**
     * AJAX POST: Delete all backup logs.
     */
    public function delete_all_backup_logs()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $this->_ensure_backup_logs_table();
        $this->db->truncate(db_prefix() . 'ccx_auto_backup_logs');

        echo json_encode(['success' => true, 'message' => 'All backup logs deleted successfully']);
    }

    // =========================================================================
    // SESSION CLEANUP (master + every tenant)
    //
    // CodeIgniter stores sessions in MySQL and never reclaims the space: rows are
    // deleted by garbage collection but InnoDB keeps the pages, so tblsessions grows
    // to hundreds of MB for a few thousand live rows. Every request reads and writes
    // this table under a row lock, so the bloat is paid on every page load.
    // =========================================================================

    /**
     * Every database this module can reach: master first, then each tenant.
     *
     * @return array [['slug' => ..., 'label' => ...], ...]
     */
    private function _session_targets()
    {
        $targets = [['slug' => 'master', 'label' => 'Master DB (' . $this->db->database . ')']];

        if (table_exists('perfex_saas_companies')) {
            $this->load->model('perfex_saas/perfex_saas_model');
            foreach ($this->perfex_saas_model->companies() as $company) {
                $targets[] = [
                    'slug'  => $company->slug,
                    'label' => $company->name . ' (' . $company->slug . ')',
                ];
            }
        }

        return $targets;
    }

    /**
     * Row counts and on-disk footprint of one sessions table.
     *
     * @param object $conn  CI database instance
     * @param int    $idle_seconds  age above which a session counts as expired
     * @return array
     */
    private function _session_table_stats($conn, $idle_seconds)
    {
        $out = ['table' => '', 'exists' => false, 'rows' => 0, 'expired' => 0, 'size' => 0, 'free' => 0, 'error' => ''];

        // With db_debug on (APP_ENV != production) CI renders an error page and exits
        // on any failed query, which would kill the whole AJAX response. Report instead.
        $prev_debug = $conn->db_debug;
        $conn->db_debug = false;

        try {
            $table = $conn->dbprefix . 'sessions';
            $out['table'] = $table;

            if (!$conn->table_exists('sessions')) {
                return $out;
            }
            $out['exists'] = true;

            $row = $conn->query("SELECT COUNT(*) AS c FROM `{$table}`")->row();
            $out['rows'] = $row ? (int) $row->c : 0;

            // 'timestamp' is the CI3 session column; guard in case of a custom schema
            $has_timestamp = false;
            foreach ($conn->query("SHOW COLUMNS FROM `{$table}`")->result() as $col) {
                if ($col->Field === 'timestamp') {
                    $has_timestamp = true;
                    break;
                }
            }
            if ($has_timestamp) {
                $row = $conn->query(
                    "SELECT COUNT(*) AS c FROM `{$table}` WHERE `timestamp` < (UNIX_TIMESTAMP() - " . (int) $idle_seconds . ")"
                )->row();
                $out['expired'] = $row ? (int) $row->c : 0;
            } else {
                $out['error'] = 'no timestamp column — only "clear all" is possible';
            }

            $row = $conn->query(
                "SELECT (DATA_LENGTH + INDEX_LENGTH) AS sz, DATA_FREE AS df
                 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = " . $conn->escape($table)
            )->row();
            if ($row) {
                $out['size'] = (float) $row->sz;
                $out['free'] = (float) $row->df;
            }
        } catch (\Throwable $e) {
            $out['error'] = $e->getMessage();
        } finally {
            $conn->db_debug = $prev_debug;
        }

        return $out;
    }

    /**
     * AJAX: current session footprint of every database, so the operator can see
     * what the cleanup is going to touch before running it.
     */
    public function get_sessions_overview()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        @set_time_limit(300);

        $idle_seconds = (int) ($this->input->post('idle_hours') ?: 24) * 3600;
        $rows = [];
        $total_rows = $total_expired = $total_size = 0;

        foreach ($this->_session_targets() as $target) {
            $db_info = $this->_get_db_connection($target['slug']);
            if (isset($db_info['error'])) {
                $rows[] = [
                    'slug' => $target['slug'], 'label' => $target['label'], 'exists' => false,
                    'rows' => 0, 'expired' => 0, 'size' => 0, 'error' => $db_info['error'],
                ];
                continue;
            }

            $stats = $this->_session_table_stats($db_info['conn'], $idle_seconds);
            $rows[] = [
                'slug'    => $target['slug'],
                'label'   => $target['label'],
                'exists'  => $stats['exists'],
                'table'   => $stats['table'],
                'rows'    => $stats['rows'],
                'expired' => $stats['expired'],
                'size'    => $stats['size'],
                'free'    => $stats['free'],
                'error'   => $stats['error'],
            ];

            $total_rows    += $stats['rows'];
            $total_expired += $stats['expired'];
            $total_size    += $stats['size'];

            if ($target['slug'] !== 'master' && isset($db_info['conn'])) {
                $db_info['conn']->close();
            }
        }

        echo json_encode([
            'success'       => true,
            'idle_hours'    => (int) ($idle_seconds / 3600),
            'rows'          => $rows,
            'total_rows'    => $total_rows,
            'total_expired' => $total_expired,
            'total_size'    => $total_size,
        ]);
    }

    /**
     * AJAX: delete session rows across the master and every tenant, then rebuild the
     * tables so the disk space is actually returned.
     *
     * mode = 'expired' (default) deletes sessions idle for longer than idle_hours.
     * mode = 'all'                empties every sessions table — this signs every user
     *                             out everywhere. The session running this request is
     *                             preserved so the operator is not logged out mid-run.
     */
    public function clear_sessions()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }
        if (!is_admin()) {
            echo json_encode(['success' => false, 'message' => 'Only administrators can clear sessions.']);
            return;
        }

        @set_time_limit(0);
        @ini_set('memory_limit', '512M');

        $mode         = $this->input->post('mode') === 'all' ? 'all' : 'expired';
        $idle_hours   = (int) ($this->input->post('idle_hours') ?: 24);
        $idle_seconds = max(1, $idle_hours) * 3600;
        $optimize     = $this->input->post('optimize') !== '0';
        $current_sid  = session_id();

        $results = [];
        $total_deleted = 0;
        $total_reclaimed = 0;

        foreach ($this->_session_targets() as $target) {
            $slug   = $target['slug'];
            $result = [
                'slug' => $slug, 'label' => $target['label'], 'deleted' => 0,
                'size_before' => 0, 'size_after' => 0, 'reclaimed' => 0, 'status' => 'ok', 'message' => '',
            ];

            $db_info = $this->_get_db_connection($slug);
            if (isset($db_info['error'])) {
                $result['status']  = 'error';
                $result['message'] = $db_info['error'];
                $results[] = $result;
                continue;
            }

            $conn  = $db_info['conn'];
            $stats = $this->_session_table_stats($conn, $idle_seconds);

            if (!$stats['exists']) {
                $result['status']  = 'skipped';
                $result['message'] = 'no sessions table';
                $results[] = $result;
                if ($slug !== 'master') $conn->close();
                continue;
            }

            $table = $stats['table'];
            $result['size_before'] = $stats['size'];

            $needs_optimize = true;
            $prev_debug = $conn->db_debug;
            $conn->db_debug = false; // report query failures, never render CI's error page

            try {
                if ($mode === 'all') {
                    if ($slug === 'master' && $current_sid !== '') {
                        // keep the operator signed in
                        $conn->query("DELETE FROM `{$table}` WHERE `id` <> " . $conn->escape($current_sid));
                        $result['deleted'] = (int) $conn->affected_rows();
                        $result['message'] = 'your own session was kept';
                    } else {
                        $result['deleted'] = $stats['rows'];
                        // TRUNCATE recreates the tablespace, so the space comes back
                        // immediately and no rebuild is needed afterwards
                        $conn->query("TRUNCATE TABLE `{$table}`");
                        $needs_optimize = false;
                    }
                } else {
                    if ($stats['error'] !== '') {
                        throw new \Exception($stats['error']);
                    }
                    // batched so a large backlog never becomes one long-locking transaction
                    for ($i = 0; $i < 100; $i++) {
                        $conn->query(
                            "DELETE FROM `{$table}` WHERE `timestamp` < (UNIX_TIMESTAMP() - " . (int) $idle_seconds . ") LIMIT 20000"
                        );
                        $affected = (int) $conn->affected_rows();
                        $result['deleted'] += $affected;
                        if ($affected < 20000) {
                            break;
                        }
                    }
                }

                $db_error = $conn->error();
                if (!empty($db_error['message'])) {
                    throw new \Exception($db_error['message']);
                }

                if ($optimize && $needs_optimize && $result['deleted'] > 0) {
                    // InnoDB only returns freed pages to the filesystem on a rebuild
                    $conn->query("OPTIMIZE TABLE `{$table}`");
                }

                $after = $this->_session_table_stats($conn, $idle_seconds);
                $result['size_after'] = $after['size'];
                $result['reclaimed'] = max(0, $result['size_before'] - $after['size']);

                $total_deleted   += $result['deleted'];
                $total_reclaimed += $result['reclaimed'];
            } catch (\Throwable $e) {
                $result['status']  = 'error';
                $result['message'] = $e->getMessage();
            } finally {
                $conn->db_debug = $prev_debug;
            }

            $results[] = $result;
            if ($slug !== 'master') {
                $conn->close();
            }
        }

        log_activity('CCX DB: cleared sessions (' . $mode . ') across ' . count($results)
            . ' databases — ' . $total_deleted . ' rows removed');

        echo json_encode([
            'success'         => true,
            'mode'            => $mode,
            'idle_hours'      => $idle_hours,
            'results'         => $results,
            'total_deleted'   => $total_deleted,
            'total_reclaimed' => $total_reclaimed,
        ]);
    }

    // =========================================================================
    // BACKUP EXECUTION ENGINE
    // =========================================================================

    /**
     * Generate a SQL dump for a tenant and write it to a temp file.
     * Returns the temp file path on success, or false on error.
     */
    private function _generate_sql_dump_to_file($slug)
    {
        @ini_set('memory_limit', '-1');
        @set_time_limit(0);

        $db_info = $this->_get_db_connection($slug);
        if (isset($db_info['error'])) {
            return ['error' => 'DB connection failed for ' . $slug . ': ' . $db_info['error']];
        }

        $db = $db_info['conn'];
        $filename = 'ccx_db_' . $slug . '_' . date('Y-m-d_H-i-s') . '.sql';
        $tmp_file = tempnam(sys_get_temp_dir(), 'ccx_dump_');

        $handle = fopen($tmp_file, 'w');
        if (!$handle) {
            return ['error' => 'Could not create temp file for ' . $slug];
        }

        fwrite($handle, "-- Database dump for: {$slug}\n");
        fwrite($handle, "-- Generated: " . date('c') . "\n\n");
        fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n\n");

        // Master: dump only master-prefixed tables (tbl%) so tenant tables that may
        // share the same physical database are never duplicated into the master backup
        $dump_prefix = ($slug === 'master') ? db_prefix() : $db_info['prefix'];

        $schema = $this->_get_db_schema($db, $dump_prefix);

        foreach ($schema as $name => $info) {
            $real_table = $info['real_name'];

            // Get CREATE TABLE
            $q = $db->query("SHOW CREATE TABLE `" . $real_table . "`");
            $row = $q->row_array();
            $create_sql = $row['Create Table'] ?? null;
            if ($create_sql) {
                fwrite($handle, "DROP TABLE IF EXISTS `{$real_table}`;\n");
                fwrite($handle, $create_sql . ";\n\n");
            }

            // Dump data in chunks
            $batch = 1000;
            $offset = 0;
            $cols = $info['columns'];
            if (empty($cols)) continue;
            $cols_list = '`' . implode('`, `', $cols) . '`';

            do {
                $res = $db->query("SELECT $cols_list FROM `{$real_table}` LIMIT {$batch} OFFSET {$offset}");
                $rows = $res->result_array();
                if (empty($rows)) break;

                $values = [];
                foreach ($rows as $r) {
                    $vals = [];
                    foreach ($r as $v) {
                        if ($v === null) {
                            $vals[] = 'NULL';
                        } else {
                            $vals[] = "'" . $db->escape_str($v) . "'";
                        }
                    }
                    $values[] = '(' . implode(',', $vals) . ')';
                }

                if (!empty($values)) {
                    fwrite($handle, "INSERT INTO `{$real_table}` ($cols_list) VALUES \n" . implode(",\n", $values) . ";\n\n");
                }

                $offset += $batch;
            } while (count($rows) == $batch);
        }

        fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($handle);

        // Close tenant DB connection
        if (is_object($db) && $slug !== 'master') {
            $db->close();
        }

        return ['file' => $tmp_file, 'filename' => $filename];
    }

    /**
     * Upload a file to the configured FTP server.
     */
    /**
     * Upload a file to the configured FTP server.
     * @param string $subfolder Optional subfolder like 'backup_2026-03-25_00-30-00'
     */
    private function _upload_file_to_ftp($local_file, $remote_filename, $subfolder = '')
    {
        $host = get_option('ccx_backup_ftp_host');
        $port = (int) (get_option('ccx_backup_ftp_port') ?: 21);
        $user = get_option('ccx_backup_ftp_username');
        $pass = ccx_db_get_secret('ccx_backup_ftp_password');
        $path = get_option('ccx_backup_ftp_path') ?: '/';

        // Strip protocol prefix
        $host = preg_replace('#^(ftps?://)#i', '', trim($host));
        $host = rtrim($host, '/');

        $ftp = @ftp_connect($host, $port, 30);
        if (!$ftp) {
            $err = error_get_last();
            throw new Exception('FTP connect failed to ' . $host . ':' . $port . ($err ? ' — ' . $err['message'] : ''));
        }

        if (!@ftp_login($ftp, $user, $pass)) {
            ftp_close($ftp);
            throw new Exception('FTP login failed for user "' . $user . '"');
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

        if (!$upload) {
            throw new Exception('FTP upload failed for file: ' . $remote_filename);
        }

        return $remote_file;
    }

    /**
     * Upload a file to the configured Google Drive.
     */
    private function _upload_file_to_gdrive($local_file, $remote_filename)
    {
        $client_id = get_option('ccx_backup_gdrive_client_id');
        $client_secret = ccx_db_get_secret('ccx_backup_gdrive_client_secret');
        $refresh_token = ccx_db_get_secret('ccx_backup_gdrive_refresh_token');
        $folder_id = get_option('ccx_backup_gdrive_folder_id');

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
        if (empty($token_json['access_token'])) {
            $err = $token_json['error_description'] ?? ($token_json['error'] ?? 'Unknown error');
            throw new Exception('Google Drive auth failed: ' . $err);
        }

        $access_token = $token_json['access_token'];

        // Upload file
        $file_content = file_get_contents($local_file);
        $metadata = ['name' => $remote_filename, 'mimeType' => 'application/sql'];
        if (!empty($folder_id)) {
            $metadata['parents'] = [$folder_id];
        }

        $boundary = 'ccx_boundary_' . uniqid();
        $body = "--{$boundary}\r\n";
        $body .= "Content-Type: application/json; charset=UTF-8\r\n\r\n";
        $body .= json_encode($metadata) . "\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: application/sql\r\n\r\n";
        $body .= $file_content . "\r\n";
        $body .= "--{$boundary}--";

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
            $err_msg = $err['error']['message'] ?? 'HTTP ' . $http_code;
            throw new Exception('Google Drive upload failed: ' . $err_msg);
        }

        return true;
    }

    /**
     * AJAX POST: Run auto backup for all enabled tenants (manual trigger).
     * Also callable as cron: /admin/ccx_db/run_auto_backup?cron_key=xxx
     */
    public function run_auto_backup()
    {
        @ini_set('memory_limit', '-1');
        @set_time_limit(0);

        // Allow cron (non-AJAX) access with a simple key check
        $is_cron = !$this->input->is_ajax_request();

        $this->_ensure_auto_backup_table();

        $methods_opt = get_option('ccx_backup_method');
        $methods = json_decode($methods_opt, true);
        if (!is_array($methods)) {
            $methods = [$methods_opt ?: 'ftp'];
        }
        $methods_str = implode(', ', $methods);

        log_message('error', '[CCX Backup] run_auto_backup START — methods: ' . $methods_str);

        // Get all enabled tenants
        $tenants = $this->db->where('auto_backup_enabled', 1)
            ->get(db_prefix() . 'ccx_auto_backup_settings')
            ->result();

        if (empty($tenants)) {
            $msg = 'No tenants with auto backup enabled.';
            log_message('error', '[CCX Backup] ' . $msg);
            $this->_log_backup_event('auto_backup', $methods_str, 'success', $msg);
            if (!$is_cron) {
                echo json_encode(['success' => true, 'message' => $msg, 'results' => []]);
            }
            return;
        }

        log_message('error', '[CCX Backup] Found ' . count($tenants) . ' enabled tenants');

        // Structured destination: DD-Month-YYYY-CCX-Backups/Full Backup
        $run_folder = $this->_backup_run_folder('full');
        log_message('error', '[CCX Backup] Run folder: ' . $run_folder);

        $results = [];
        $success_count = 0;
        $error_count = 0;

        foreach ($tenants as $tenant) {
            $slug = $tenant->tenant_slug;
            $steps = [];

            try {
                // 1. Generate SQL dump
                $steps[] = '[' . date('H:i:s') . '] Generating SQL dump for ' . $slug . '...';
                log_message('error', '[CCX Backup] Generating SQL dump for ' . $slug);
                $dump = $this->_generate_sql_dump_to_file($slug);
                if (isset($dump['error'])) {
                    throw new Exception($dump['error']);
                }

                $local_file = $dump['file'];
                $remote_filename = $dump['filename'];
                $file_size = filesize($local_file);
                $steps[] = '[' . date('H:i:s') . '] SQL dump generated: ' . $remote_filename . ' (' . round($file_size / 1024 / 1024, 2) . ' MB)';
                log_message('error', '[CCX Backup] SQL dump generated for ' . $slug . ': ' . $remote_filename . ' (' . round($file_size / 1024 / 1024, 2) . ' MB)');

                // 2. Upload to configured destination(s) (into run folder)
                $upload_details = [];
                $upload_errors = [];

                foreach ($methods as $m) {
                    try {
                        if ($m === 'ftp') {
                            $steps[] = '[' . date('H:i:s') . '] Uploading to FTP (folder: ' . $run_folder . ')...';
                            log_message('error', '[CCX Backup] Uploading ' . $slug . ' to FTP folder: ' . $run_folder);
                            $remote_path = $this->_upload_file_to_ftp($local_file, $remote_filename, $run_folder);
                            $upload_details[] = 'FTP: ' . $remote_path;
                            $steps[] = '[' . date('H:i:s') . '] Uploaded to FTP successfully';
                        } elseif ($m === 'google_drive') {
                            $gdrive_name = str_replace('/', '_', $run_folder) . '_' . $remote_filename;
                            $steps[] = '[' . date('H:i:s') . '] Uploading to Google Drive (folder: ' . $run_folder . ')...';
                            log_message('error', '[CCX Backup] Uploading ' . $slug . ' to Google Drive folder: ' . $run_folder);
                            $this->_upload_file_to_gdrive($local_file, $gdrive_name);
                            $upload_details[] = 'GDrive: ' . $gdrive_name;
                            $steps[] = '[' . date('H:i:s') . '] Uploaded to Google Drive successfully';
                        }
                    } catch (Exception $m_e) {
                        $steps[] = '[' . date('H:i:s') . '] ERROR uploading to ' . strtoupper($m) . ': ' . $m_e->getMessage();
                        $upload_errors[] = strtoupper($m) . ' Error: ' . $m_e->getMessage();
                    }
                }

                if (empty($upload_details) && !empty($upload_errors)) {
                    // All configured methods failed
                    throw new Exception('All uploads failed: ' . implode(' | ', $upload_errors));
                }

                $upload_detail = implode(' | ', $upload_details);
                if (!empty($upload_errors)) {
                    $upload_detail .= ' (Partial failures: ' . implode(' | ', $upload_errors) . ')';
                }

                $steps[] = '[' . date('H:i:s') . '] ' . $upload_detail;

                // 3. Clean up temp file
                @unlink($local_file);

                // 4. Update last_backup_at
                $this->db->where('tenant_slug', $slug)
                    ->update(db_prefix() . 'ccx_auto_backup_settings', [
                        'last_backup_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);

                // 5. Log success
                $steps[] = '[' . date('H:i:s') . '] SUCCESS';
                $msg = 'Backup successful for ' . $slug . '. ' . $upload_detail;
                $log_msg = $msg . "\n--- Debug Steps ---\n" . implode("\n", $steps);
                $this->_log_backup_event('auto_backup', $methods_str, 'success', $log_msg, $slug);
                log_message('error', '[CCX Backup] SUCCESS for ' . $slug);
                $results[] = ['slug' => $slug, 'status' => 'success', 'message' => $msg, 'steps' => $steps];
                $success_count++;

            } catch (Exception $e) {
                // Clean up temp file on error
                if (isset($local_file) && file_exists($local_file)) {
                    @unlink($local_file);
                }
                $steps[] = '[' . date('H:i:s') . '] ERROR: ' . $e->getMessage();
                $err_msg = 'Backup failed for ' . $slug . ': ' . $e->getMessage();
                $log_msg = $err_msg . "\n--- Debug Steps ---\n" . implode("\n", $steps);
                $this->_log_backup_event('auto_backup', $methods_str, 'error', $log_msg, $slug);
                log_message('error', '[CCX Backup] FAILED for ' . $slug . ': ' . $e->getMessage());
                $results[] = ['slug' => $slug, 'status' => 'error', 'message' => $err_msg, 'steps' => $steps];
                $error_count++;
            }
        }

        $summary = "Backup completed. Success: {$success_count}, Errors: {$error_count}, Total: " . count($tenants);
        log_message('error', '[CCX Backup] ' . $summary);

        if (!$is_cron) {
            echo json_encode([
                'success' => $error_count === 0,
                'message' => $summary,
                'results' => $results,
            ]);
        }
    }

    /**
     * AJAX POST: Run backup for a single tenant (manual trigger).
     */
    public function run_single_tenant_backup()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        @ini_set('memory_limit', '-1');
        @set_time_limit(0);

        $slug = $this->input->post('slug');
        if (empty($slug)) {
            echo json_encode(['success' => false, 'message' => 'Missing tenant slug']);
            return;
        }

        echo json_encode($this->_run_tenant_backup($slug, $this->_backup_run_folder('full')));
    }

    /**
     * Structured destination folder: DD-Month-YYYY-CCX-Backups/<Full Backup|Incremental Backups>
     */
    private function _backup_run_folder($backup_type = 'full')
    {
        if (function_exists('ccx_db_backup_run_folder')) {
            return ccx_db_backup_run_folder($backup_type);
        }
        return date('d-F-Y') . '-CCX-Backups/' . ($backup_type === 'incremental' ? 'Incremental Backups' : 'Full Backup');
    }

    /**
     * Resolve configured backup destination methods (ftp / google_drive).
     */
    private function _get_backup_methods()
    {
        $methods_opt = get_option('ccx_backup_method');
        $methods = json_decode($methods_opt, true);
        if (!is_array($methods)) {
            $methods = [$methods_opt ?: 'ftp'];
        }
        return $methods;
    }

    /**
     * Core backup routine for one tenant: dump -> upload -> log.
     * Returns ['success' => bool, 'message' => string, 'steps' => array] instead of echoing,
     * so it can be shared by the single-tenant endpoint and the background queue worker.
     */
    private function _run_tenant_backup($slug, $run_folder)
    {
        $methods = $this->_get_backup_methods();
        $methods_str = implode(', ', $methods);

        $steps = [];
        $steps[] = '[' . date('H:i:s') . '] START backup for ' . $slug . ' | methods: ' . $methods_str;
        log_message('error', '[CCX Backup] START backup for ' . $slug . ' | methods: ' . $methods_str);

        try {
            $steps[] = '[' . date('H:i:s') . '] Generating SQL dump...';
            log_message('error', '[CCX Backup] Generating SQL dump for ' . $slug);
            $dump = $this->_generate_sql_dump_to_file($slug);
            if (isset($dump['error'])) {
                throw new Exception($dump['error']);
            }

            $local_file = $dump['file'];
            $remote_filename = $dump['filename'];
            $file_size = filesize($local_file);
            $steps[] = '[' . date('H:i:s') . '] SQL dump generated: ' . $remote_filename . ' (' . round($file_size / 1024 / 1024, 2) . ' MB)';
            log_message('error', '[CCX Backup] SQL dump generated for ' . $slug . ': ' . round($file_size / 1024 / 1024, 2) . ' MB');

            $upload_details = [];
            $upload_errors = [];

            foreach ($methods as $m) {
                try {
                    if ($m === 'ftp') {
                        $steps[] = '[' . date('H:i:s') . '] Uploading to FTP (folder: ' . $run_folder . ')...';
                        log_message('error', '[CCX Backup] Uploading ' . $slug . ' to FTP folder: ' . $run_folder);
                        $remote_path = $this->_upload_file_to_ftp($local_file, $remote_filename, $run_folder);
                        $upload_details[] = 'FTP: ' . $remote_path;
                        $steps[] = '[' . date('H:i:s') . '] Uploaded to FTP successfully';
                    } elseif ($m === 'google_drive') {
                        $gdrive_name = str_replace('/', '_', $run_folder) . '_' . $remote_filename;
                        $steps[] = '[' . date('H:i:s') . '] Uploading to Google Drive (folder: ' . $run_folder . ')...';
                        log_message('error', '[CCX Backup] Uploading ' . $slug . ' to Google Drive folder: ' . $run_folder);
                        $this->_upload_file_to_gdrive($local_file, $gdrive_name);
                        $upload_details[] = 'GDrive: ' . $gdrive_name;
                        $steps[] = '[' . date('H:i:s') . '] Uploaded to Google Drive successfully';
                    }
                } catch (Exception $m_e) {
                    $steps[] = '[' . date('H:i:s') . '] ERROR uploading to ' . strtoupper($m) . ': ' . $m_e->getMessage();
                    $upload_errors[] = strtoupper($m) . ' Error: ' . $m_e->getMessage();
                }
            }

            if (empty($upload_details) && !empty($upload_errors)) {
                // All configured methods failed
                throw new Exception('All uploads failed: ' . implode(' | ', $upload_errors));
            }

            $detail = implode(' | ', $upload_details);
            if (!empty($upload_errors)) {
                $detail .= ' (Partial failures: ' . implode(' | ', $upload_errors) . ')';
            }

            $steps[] = '[' . date('H:i:s') . '] ' . $detail;

            @unlink($local_file);

            // A long dump/upload can outlive the MySQL connection — reconnect before writing results
            if (method_exists($this->db, 'reconnect')) {
                @$this->db->reconnect();
            }

            // Update last_backup_at
            $this->_ensure_auto_backup_table();
            $this->db->where('tenant_slug', $slug)
                ->update(db_prefix() . 'ccx_auto_backup_settings', [
                    'last_backup_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

            $steps[] = '[' . date('H:i:s') . '] SUCCESS';
            $msg = 'Backup successful for ' . $slug . '. ' . $detail;
            $log_msg = $msg . "\n--- Debug Steps ---\n" . implode("\n", $steps);
            $this->_log_backup_event('auto_backup', $methods_str, 'success', $log_msg, $slug);
            log_message('error', '[CCX Backup] SUCCESS for ' . $slug);

            return ['success' => true, 'message' => $msg, 'steps' => $steps];

        } catch (Exception $e) {
            if (isset($local_file) && file_exists($local_file)) {
                @unlink($local_file);
            }
            if (method_exists($this->db, 'reconnect')) {
                @$this->db->reconnect();
            }

            // Deleted tenant: disable its auto backup and log as 'skipped' so it never
            // raises a failure alert email again
            if (stripos($e->getMessage(), 'Company not found') !== false) {
                $this->_ensure_auto_backup_table();
                $this->db->where('tenant_slug', $slug)
                    ->update(db_prefix() . 'ccx_auto_backup_settings', [
                        'auto_backup_enabled' => 0,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                $steps[] = '[' . date('H:i:s') . '] Company not found — tenant appears deleted, auto backup disabled';
                $skip_msg = 'Backup skipped for ' . $slug . ': tenant appears deleted (Company not found) — auto backup has been disabled';
                $log_msg = $skip_msg . "\n--- Debug Steps ---\n" . implode("\n", $steps);
                $this->_log_backup_event('auto_backup', $methods_str, 'skipped', $log_msg, $slug);
                log_message('error', '[CCX Backup] SKIPPED (deleted tenant) ' . $slug);

                return ['success' => false, 'skipped' => true, 'message' => $skip_msg, 'steps' => $steps];
            }

            $steps[] = '[' . date('H:i:s') . '] ERROR: ' . $e->getMessage();
            $err_msg = 'Backup failed for ' . $slug . ': ' . $e->getMessage();
            $log_msg = $err_msg . "\n--- Debug Steps ---\n" . implode("\n", $steps);
            $this->_log_backup_event('auto_backup', $methods_str, 'error', $log_msg, $slug);
            log_message('error', '[CCX Backup] FAILED for ' . $slug . ': ' . $e->getMessage());

            return ['success' => false, 'message' => $err_msg, 'steps' => $steps];
        }
    }

    /**
     * Self-heal: ensure the backup queue table exists
     */
    private function _ensure_backup_queue_table()
    {
        if (!$this->db->table_exists(db_prefix() . 'ccx_backup_queue')) {
            $this->db->query("CREATE TABLE IF NOT EXISTS `" . db_prefix() . "ccx_backup_queue` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `run_folder` VARCHAR(64) NOT NULL,
                `tenant_slug` VARCHAR(191) NOT NULL,
                `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
                `claim_token` VARCHAR(64) DEFAULT NULL,
                `message` TEXT DEFAULT NULL,
                `steps` MEDIUMTEXT DEFAULT NULL,
                `created_at` DATETIME DEFAULT NULL,
                `started_at` DATETIME DEFAULT NULL,
                `finished_at` DATETIME DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `status_idx` (`status`),
                KEY `run_folder_idx` (`run_folder`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
    }

    /**
     * Mark queue items stuck in 'running' (dead worker) as failed so they never block the queue.
     */
    private function _release_stale_queue_items()
    {
        $table = db_prefix() . 'ccx_backup_queue';
        $this->db->query("UPDATE `{$table}` SET `status` = 'error',
            `message` = 'Marked as failed: worker did not report completion within 60 minutes',
            `finished_at` = NOW()
            WHERE `status` = 'running' AND `started_at` < DATE_SUB(NOW(), INTERVAL 60 MINUTE)");
    }

    /**
     * AJAX POST: Create (or resume) the bulk backup queue for all enabled tenants.
     * Returns immediately — no backup work happens in this request.
     */
    public function enqueue_all_backups()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $this->_ensure_auto_backup_table();
        $this->_ensure_backup_queue_table();
        $this->_release_stale_queue_items();

        $table = db_prefix() . 'ccx_backup_queue';

        // Resume an unfinished queue instead of duplicating it
        $existing = $this->db->select('run_folder')
            ->where_in('status', ['pending', 'running'])
            ->order_by('id', 'ASC')
            ->limit(1)
            ->get($table)
            ->row();

        if ($existing) {
            $run_folder = $existing->run_folder;
            $total = $this->db->where('run_folder', $run_folder)->count_all_results($table);
            log_message('error', '[CCX Backup] Resuming existing backup queue: ' . $run_folder);
            echo json_encode(['success' => true, 'resumed' => true, 'run_folder' => $run_folder, 'total' => $total, 'dest_folder' => $this->_backup_run_folder('full')]);
            return;
        }

        $tenants = $this->db->where('auto_backup_enabled', 1)
            ->order_by("tenant_slug = 'master'", 'DESC', false)
            ->order_by('id', 'ASC')
            ->get(db_prefix() . 'ccx_auto_backup_settings')
            ->result();

        if (empty($tenants)) {
            echo json_encode(['success' => false, 'message' => 'No tenants with auto backup enabled.']);
            return;
        }

        // run_folder here is only the queue GROUPING KEY (unique per run) — the actual
        // destination folder is computed per item at process time (_backup_run_folder)
        $run_folder = 'bulkrun_' . date('Y-m-d_H-i-s');
        foreach ($tenants as $tenant) {
            $this->db->insert($table, [
                'run_folder' => $run_folder,
                'tenant_slug' => $tenant->tenant_slug,
                'status' => 'pending',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        log_message('error', '[CCX Backup] Queued ' . count($tenants) . ' tenants for bulk backup, queue run: ' . $run_folder);
        echo json_encode(['success' => true, 'resumed' => false, 'run_folder' => $run_folder, 'total' => count($tenants), 'dest_folder' => $this->_backup_run_folder('full')]);
    }

    /**
     * AJAX POST: Process ONE pending queue item as a background worker.
     * Replies to the browser within a second, closes the connection, and then keeps
     * running the actual dump/upload — so proxy timeouts (Cloudflare 524) cannot kill it.
     */
    public function process_backup_queue()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        @ini_set('memory_limit', '-1');
        @set_time_limit(0);
        ignore_user_abort(true);

        // Release the session lock NOW: status polls and other admin requests must not
        // queue behind this worker (that lock cascade is what caused 524s + logouts before)
        if (function_exists('session_write_close')) {
            @session_write_close();
        }

        $this->_ensure_backup_queue_table();
        $this->_release_stale_queue_items();

        $table = db_prefix() . 'ccx_backup_queue';

        // Never run two heavy backups in parallel
        $running = $this->db->where('status', 'running')->order_by('id', 'ASC')->limit(1)->get($table)->row();
        if ($running) {
            echo json_encode(['success' => true, 'busy' => true, 'running' => $running->tenant_slug]);
            return;
        }

        // Atomically claim the next pending item
        $token = uniqid('claim_', true);
        $this->db->query("UPDATE `{$table}` SET `status` = 'running', `claim_token` = " . $this->db->escape($token) . ", `started_at` = NOW()
            WHERE `status` = 'pending' ORDER BY `id` ASC LIMIT 1");
        $item = $this->db->where('claim_token', $token)->get($table)->row();

        if (!$item) {
            echo json_encode(['success' => true, 'done' => true]);
            return;
        }

        // Respond and disconnect the client — everything below runs after the HTTP
        // response is already closed, outside any proxy timeout window
        $this->_respond_and_continue([
            'success' => true,
            'started' => $item->tenant_slug,
            'queue_id' => (int) $item->id,
        ]);

        log_message('error', '[CCX Backup] Queue worker processing ' . $item->tenant_slug . ' (queue id ' . $item->id . ')');

        // item->run_folder is just the queue grouping key — the destination is the
        // structured day folder, computed when the backup actually runs
        $result = $this->_run_tenant_backup($item->tenant_slug, $this->_backup_run_folder('full'));

        $this->db->where('id', $item->id)->update($table, [
            'status' => $result['success'] ? 'success' : (!empty($result['skipped']) ? 'skipped' : 'error'),
            'message' => $result['message'],
            'steps' => json_encode($result['steps']),
            'finished_at' => date('Y-m-d H:i:s'),
        ]);

        // Last item of the run? Send the completion summary email (if enabled)
        $remaining = $this->db->where('run_folder', $item->run_folder)
            ->where_in('status', ['pending', 'running'])
            ->count_all_results($table);
        if ($remaining == 0) {
            $this->_send_queue_completion_email($item->run_folder);
        }
    }

    /**
     * Send the bulk-queue completion summary email (opt-in via "Email on Success").
     */
    private function _send_queue_completion_email($queue_run)
    {
        if (get_option('ccx_backup_notify_on_success') != '1') {
            return;
        }
        if (!function_exists('ccx_db_send_success_notification')) {
            return;
        }

        $items = $this->db->where('run_folder', $queue_run)
            ->order_by('id', 'ASC')
            ->get(db_prefix() . 'ccx_backup_queue')
            ->result();

        if (empty($items)) {
            return;
        }

        $results = [];
        foreach ($items as $it) {
            $results[] = [
                'slug' => $it->tenant_slug,
                'status' => $it->status,
                'message' => $it->message,
            ];
        }

        ccx_db_send_success_notification(
            get_instance(),
            'full',
            $this->_backup_run_folder('full'),
            $this->_get_backup_methods(),
            $results
        );
    }

    /**
     * AJAX GET: Lightweight status poll for the backup queue.
     */
    public function backup_queue_status()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        // Read-only endpoint — never hold the session lock
        if (function_exists('session_write_close')) {
            @session_write_close();
        }

        $this->_ensure_backup_queue_table();
        $table = db_prefix() . 'ccx_backup_queue';

        $run_folder = $this->input->get('run_folder');
        if (empty($run_folder)) {
            $latest = $this->db->select('run_folder')->order_by('id', 'DESC')->limit(1)->get($table)->row();
            $run_folder = $latest->run_folder ?? '';
        }

        $items = $this->db->where('run_folder', $run_folder)->order_by('id', 'ASC')->get($table)->result();

        $counts = ['pending' => 0, 'running' => 0, 'success' => 0, 'error' => 0, 'skipped' => 0];
        $out = [];
        foreach ($items as $it) {
            if (isset($counts[$it->status])) {
                $counts[$it->status]++;
            }
            $out[] = [
                'id' => (int) $it->id,
                'slug' => $it->tenant_slug,
                'status' => $it->status,
                'message' => $it->message,
                'steps' => $it->steps ? json_decode($it->steps) : [],
                'started_at' => $it->started_at,
                'finished_at' => $it->finished_at,
            ];
        }

        echo json_encode([
            'success' => true,
            'run_folder' => $run_folder,
            'total' => count($items),
            'counts' => $counts,
            'items' => $out,
        ]);
    }

    /**
     * Send a JSON response to the client and close the connection, while this PHP
     * process keeps executing whatever comes after the call.
     */
    private function _respond_and_continue(array $payload)
    {
        ignore_user_abort(true);
        @set_time_limit(0);

        $body = json_encode($payload);

        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', '1');
        }

        header('Content-Type: application/json');
        header('Content-Length: ' . strlen($body));
        header('Connection: close');
        echo $body;
        flush();

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } elseif (function_exists('litespeed_finish_request')) {
            litespeed_finish_request();
        }
    }

    /**
     * Server Performance monitoring page
     */
    public function server_performance()
    {
        $data['title'] = 'Server Performance';
        $this->load->view('ccx_db/server_performance', $data);
    }

    /**
     * AJAX endpoint — returns live server stats as JSON
     */
    public function server_stats_json()
    {
        header('Content-Type: application/json');

        try {
            $stats = [];

            // ─── CPU Load Averages ───
            $stats['cpu'] = ['load_1' => 0, 'load_5' => 0, 'load_15' => 0, 'cores' => 1, 'usage_pct' => 0];
            if (function_exists('sys_getloadavg')) {
                $load = @sys_getloadavg();
                if ($load !== false) {
                    $stats['cpu']['load_1']  = round($load[0], 2);
                    $stats['cpu']['load_5']  = round($load[1], 2);
                    $stats['cpu']['load_15'] = round($load[2], 2);
                }
            }
            // Number of CPU cores
            $cores = 1;
            if (is_readable('/proc/cpuinfo')) {
                $cpuinfo = @file_get_contents('/proc/cpuinfo');
                if ($cpuinfo !== false) {
                    $cores = max(1, substr_count($cpuinfo, 'processor'));
                }
            } elseif (PHP_OS === 'Darwin' && function_exists('shell_exec')) {
                $cores = (int) @shell_exec('sysctl -n hw.ncpu 2>/dev/null');
                if ($cores < 1) $cores = 1;
            }
            $stats['cpu']['cores'] = $cores;
            $stats['cpu']['usage_pct'] = $cores > 0 ? min(100, round(($stats['cpu']['load_1'] / $cores) * 100, 1)) : 0;

            // ─── Memory ───
            $stats['memory'] = ['total_mb' => 0, 'used_mb' => 0, 'free_mb' => 0, 'usage_pct' => 0];
            if (is_readable('/proc/meminfo')) {
                $meminfo = @file_get_contents('/proc/meminfo');
                if ($meminfo !== false) {
                    preg_match('/MemTotal:\s+(\d+)/', $meminfo, $mt);
                    preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $ma);
                    if (empty($ma)) {
                        preg_match('/MemFree:\s+(\d+)/', $meminfo, $ma);
                    }
                    $total_kb = isset($mt[1]) ? (int)$mt[1] : 0;
                    $avail_kb = isset($ma[1]) ? (int)$ma[1] : 0;
                    $used_kb  = $total_kb - $avail_kb;
                    if ($total_kb > 0) {
                        $stats['memory'] = [
                            'total_mb'  => round($total_kb / 1024, 1),
                            'used_mb'   => round($used_kb / 1024, 1),
                            'free_mb'   => round($avail_kb / 1024, 1),
                            'usage_pct' => round(($used_kb / $total_kb) * 100, 1),
                        ];
                    }
                }
            } elseif (PHP_OS === 'Darwin' && function_exists('shell_exec')) {
                $total_bytes = (int) @shell_exec('sysctl -n hw.memsize 2>/dev/null');
                $vm = @shell_exec('vm_stat 2>/dev/null');
                $page_size = (int) @shell_exec('sysctl -n hw.pagesize 2>/dev/null');
                $free_pages = 0;
                $inactive_pages = 0;
                if ($vm) {
                    if (preg_match('/Pages free:\s+(\d+)/', $vm, $fp)) $free_pages = (int)$fp[1];
                    if (preg_match('/Pages inactive:\s+(\d+)/', $vm, $ip)) $inactive_pages = (int)$ip[1];
                }
                if ($total_bytes > 0 && $page_size > 0) {
                    $avail_bytes = ($free_pages + $inactive_pages) * $page_size;
                    $used_bytes = $total_bytes - $avail_bytes;
                    $stats['memory'] = [
                        'total_mb'  => round($total_bytes / 1024 / 1024, 1),
                        'used_mb'   => round($used_bytes / 1024 / 1024, 1),
                        'free_mb'   => round($avail_bytes / 1024 / 1024, 1),
                        'usage_pct' => round(($used_bytes / $total_bytes) * 100, 1),
                    ];
                }
            }
            // Fallback: try 'free' command
            if ($stats['memory']['total_mb'] == 0 && function_exists('shell_exec')) {
                $free_output = @shell_exec('free -m 2>/dev/null');
                if ($free_output && preg_match('/Mem:\s+(\d+)\s+(\d+)\s+(\d+)/', $free_output, $fm)) {
                    $total = (int)$fm[1];
                    $used  = (int)$fm[2];
                    $free  = (int)$fm[3];
                    if ($total > 0) {
                        $stats['memory'] = [
                            'total_mb'  => $total,
                            'used_mb'   => $used,
                            'free_mb'   => $free,
                            'usage_pct' => round(($used / $total) * 100, 1),
                        ];
                    }
                }
            }

            // ─── Disk ───
            $disk_total = @disk_total_space('/');
            $disk_free  = @disk_free_space('/');
            $disk_used  = ($disk_total && $disk_free) ? $disk_total - $disk_free : 0;
            $stats['disk'] = [
                'total_gb'  => $disk_total ? round($disk_total / 1024 / 1024 / 1024, 2) : 0,
                'used_gb'   => round($disk_used / 1024 / 1024 / 1024, 2),
                'free_gb'   => $disk_free ? round($disk_free / 1024 / 1024 / 1024, 2) : 0,
                'usage_pct' => ($disk_total && $disk_total > 0) ? round(($disk_used / $disk_total) * 100, 1) : 0,
            ];

            // ─── MySQL Stats ───
            $stats['mysql'] = [
                'threads_connected' => 0,
                'threads_running'   => 0,
                'questions'         => 0,
                'slow_queries'      => 0,
                'uptime'            => 0,
                'uptime_human'      => '',
                'queries_per_sec'   => 0,
                'open_tables'       => 0,
                'table_locks_waited'=> 0,
            ];
            try {
                $vars = [
                    'Threads_connected', 'Threads_running', 'Questions',
                    'Slow_queries', 'Uptime', 'Open_tables', 'Table_locks_waited'
                ];
                foreach ($vars as $var) {
                    $q = $this->db->query("SHOW GLOBAL STATUS LIKE ?", [$var]);
                    if ($q) {
                        $r = $q->row();
                        if ($r) {
                            $key = strtolower($var);
                            $stats['mysql'][$key] = (int) $r->Value;
                        }
                    }
                }
                $uptime = max(1, $stats['mysql']['uptime']);
                $stats['mysql']['queries_per_sec'] = round($stats['mysql']['questions'] / $uptime, 1);
                $days    = floor($uptime / 86400);
                $hours   = floor(($uptime % 86400) / 3600);
                $minutes = floor(($uptime % 3600) / 60);
                $stats['mysql']['uptime_human'] = "{$days}d {$hours}h {$minutes}m";
            } catch (Exception $e) {
                log_message('error', '[CCX Performance] MySQL stats error: ' . $e->getMessage());
            }

            // ─── MySQL Process List ───
            $stats['processes'] = [];
            try {
                $q = $this->db->query("SHOW PROCESSLIST");
                if ($q) {
                    $rows = $q->result_array();
                    foreach ($rows as $row) {
                        $stats['processes'][] = [
                            'id'      => isset($row['Id']) ? $row['Id'] : '',
                            'user'    => isset($row['User']) ? $row['User'] : '',
                            'host'    => isset($row['Host']) ? $row['Host'] : '',
                            'db'      => isset($row['db']) ? $row['db'] : '',
                            'command' => isset($row['Command']) ? $row['Command'] : '',
                            'time'    => isset($row['Time']) ? $row['Time'] : '',
                            'state'   => isset($row['State']) ? $row['State'] : '',
                            'info'    => isset($row['Info']) ? substr((string)$row['Info'], 0, 120) : '',
                        ];
                    }
                }
            } catch (Exception $e) {
                log_message('error', '[CCX Performance] Process list error: ' . $e->getMessage());
            }

            // ─── PHP Memory ───
            $stats['php'] = [
                'memory_usage_mb'      => round(memory_get_usage(true) / 1024 / 1024, 2),
                'memory_peak_usage_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            ];

            // ─── Tenant MySQL Process Lists ───
            $stats['tenant_processes'] = [];
            try {
                $this->load->model('perfex_saas/perfex_saas_model');
                $companies = $this->perfex_saas_model->companies();
                if (!empty($companies)) {
                    foreach ($companies as $company) {
                        $slug = $company->slug;
                        $tenant_entry = [
                            'slug'      => $slug,
                            'company'   => isset($company->company) ? $company->company : $slug,
                            'processes' => [],
                            'error'     => null,
                        ];
                        try {
                            $db_info = $this->_get_db_connection($slug);
                            if (isset($db_info['error'])) {
                                $tenant_entry['error'] = $db_info['error'];
                            } else {
                                $tdb = $db_info['conn'];
                                $tq = $tdb->query("SHOW PROCESSLIST");
                                if ($tq) {
                                    foreach ($tq->result_array() as $row) {
                                        $tenant_entry['processes'][] = [
                                            'id'      => isset($row['Id']) ? $row['Id'] : '',
                                            'user'    => isset($row['User']) ? $row['User'] : '',
                                            'host'    => isset($row['Host']) ? $row['Host'] : '',
                                            'db'      => isset($row['db']) ? $row['db'] : '',
                                            'command' => isset($row['Command']) ? $row['Command'] : '',
                                            'time'    => isset($row['Time']) ? $row['Time'] : '',
                                            'state'   => isset($row['State']) ? $row['State'] : '',
                                            'info'    => isset($row['Info']) ? substr((string)$row['Info'], 0, 120) : '',
                                        ];
                                    }
                                }
                                // Close tenant connection
                                if ($slug !== 'master' && is_object($tdb)) {
                                    $tdb->close();
                                }
                            }
                        } catch (Exception $e) {
                            $tenant_entry['error'] = $e->getMessage();
                        }
                        $stats['tenant_processes'][] = $tenant_entry;
                    }
                }
            } catch (Exception $e) {
                log_message('error', '[CCX Performance] Tenant processes error: ' . $e->getMessage());
            }

            // ─── Server Time ───
            $stats['server_time'] = date('Y-m-d H:i:s');

            echo json_encode($stats);

        } catch (Throwable $e) {
            echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
        } catch (Exception $e) {
            echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
        }
    }

    /* ══════════════════════════════════════════════════════════════════════
     *  ON-BOARDING CAPACITY MODEL
     *
     *  How many more tenants can this server take? Every number below is
     *  measured live — cores, load, RAM, disk, MySQL limits and the real
     *  per-tenant footprint. Each constraint is converted into "how many
     *  tenants does this one allow", and the smallest of them is the wall.
     *
     *  Nothing here is hard-coded to this server: move the app to a bigger
     *  box, or let the tenants grow, and the answer moves with it.
     * ══════════════════════════════════════════════════════════════════════ */

    /** Safe steady-state utilisation of any resource before users feel it. */
    const CAP_CPU_TARGET      = 0.60;
    /** Connections held back for cron, node_server, backups, replication. */
    const CAP_CONN_RESERVE    = 25;
    /** Share of total RAM never handed out to tenants (OS + page cache). */
    const CAP_RAM_RESERVE_PCT = 0.15;
    /** Data + backup copies + uploads per unit of database size. */
    const CAP_DISK_FACTOR     = 3;
    /** "Safe to sell" is this fraction of the hard wall. */
    const CAP_SAFE_FACTOR     = 0.70;
    /** Tenant footprint scan is expensive — reuse it for this long. */
    const CAP_TENANT_CACHE_TTL = 900;

    public function capacity_json()
    {
        header('Content-Type: application/json');

        try {
            $ps      = $this->_cap_process_table();
            $cores   = $this->_cap_cpu_cores();
            $mem     = $this->_cap_mem_info();
            $load    = function_exists('sys_getloadavg') ? (@sys_getloadavg() ?: [0, 0, 0]) : [0, 0, 0];
            $mysql   = $this->_cap_mysql_metrics();
            $tenants = $this->_cap_tenant_footprint();

            $active = max(1, $tenants['active']);   // divisor guard
            $peaks  = $this->_cap_track_peaks($load, $mysql);

            // Steady-state load: the 5m/15m averages, or the best peak ever seen.
            $load_ref = max((float) $load[1], (float) $load[2], (float) $peaks['load']);
            $qps_ref  = max((float) $mysql['qps_avg'], (float) $peaks['qps']);
            $conn_ref = max((int) $mysql['max_used_connections'], (int) $peaks['conns'], 1);

            // ─── Per-tenant unit cost (all measured, all divided by live tenants) ───
            $per = [
                'db_mb'   => round($tenants['avg_mb'], 1),
                'tables'  => (int) round($tenants['avg_tables']),
                'load'    => round($load_ref / $active, 4),
                'qps'     => round($qps_ref / $active, 1),
                'conns'   => round($conn_ref / $active, 2),
                'ram_mb'  => 0,
            ];

            $workers_per_tenant  = max(0.5, $ps['php_workers'] / $active);
            $per['ram_mb']       = round($tenants['avg_mb'] + ($ps['php_avg_mb'] * $workers_per_tenant), 1);

            $con = [];

            // ─── A. CPU / application load ───────────────────────────────
            // Confidence is low while the server is idle: extrapolating from
            // load 0.1 is arithmetic, not evidence. Say so rather than hide it.
            $cpu_budget = $cores * self::CAP_CPU_TARGET;
            $cpu_conf   = $load_ref >= ($cores * 0.25) ? 'high'
                        : ($load_ref >= ($cores * 0.08) ? 'medium' : 'low');
            $con[] = [
                'key'        => 'cpu',
                'label'      => 'CPU / application load',
                'current'    => round($load_ref, 2) . ' load',
                'limit'      => round($cpu_budget, 2) . ' load (' . (int) (self::CAP_CPU_TARGET * 100) . '% of ' . $cores . ' cores)',
                'tenants'    => $per['load'] > 0.0005 ? (int) floor($cpu_budget / $per['load']) : null,
                'confidence' => $cpu_conf,
                'tunable'    => true,
                'note'       => $cpu_conf === 'low'
                    ? 'Measured while the server was near idle — this ceiling is an extrapolation. Leave this page open during peak hours and it sharpens itself.'
                    : 'Based on ' . round($per['load'], 4) . ' load units per active tenant.',
            ];

            // ─── B. MySQL connections ────────────────────────────────────
            $conn_usable = max(1, $mysql['max_connections'] - self::CAP_CONN_RESERVE);
            $con[] = [
                'key'        => 'connections',
                'label'      => 'MySQL connections',
                'current'    => $conn_ref . ' peak used',
                'limit'      => $conn_usable . ' usable of ' . $mysql['max_connections'],
                'tenants'    => $per['conns'] > 0 ? (int) floor($conn_usable / $per['conns']) : null,
                'confidence' => $conn_ref > 5 ? 'high' : 'medium',
                'tunable'    => true,
                'note'       => self::CAP_CONN_RESERVE . ' connections held back for cron, node_server and backups. Each request opens the master plus its tenant.',
            ];

            // ─── C. RAM ──────────────────────────────────────────────────
            // What MySQL has *reserved* but not yet touched is not free RAM.
            $bp_headroom = max(0, $mysql['buffer_pool_mb'] - $ps['mysql_rss_mb']);
            $ram_reserve = $mem['total_mb'] * self::CAP_RAM_RESERVE_PCT;
            $ram_grow    = max(0, $mem['free_mb'] - $ram_reserve - $bp_headroom);
            $con[] = [
                'key'        => 'ram',
                'label'      => 'RAM',
                'current'    => round($mem['used_mb'] / 1024, 1) . ' GB used of ' . round($mem['total_mb'] / 1024, 1) . ' GB',
                'limit'      => round($ram_grow / 1024, 1) . ' GB available to grow into',
                'tenants'    => $per['ram_mb'] > 0 ? $tenants['active'] + (int) floor($ram_grow / $per['ram_mb']) : null,
                'confidence' => 'high',
                'tunable'    => false,
                'note'       => round($per['ram_mb'], 1) . ' MB per tenant (database + its share of PHP workers). '
                    . round($bp_headroom / 1024, 1) . ' GB is already promised to the InnoDB buffer pool.',
            ];

            // ─── D. InnoDB buffer pool (keeps tenants off the disk) ───────
            $con[] = [
                'key'        => 'buffer_pool',
                'label'      => 'InnoDB buffer pool',
                'current'    => round($tenants['total_mb'], 1) . ' MB of tenant data',
                'limit'      => round($mysql['buffer_pool_mb'] * 0.85 / 1024, 1) . ' GB cacheable',
                'tenants'    => $tenants['avg_mb'] > 0 ? (int) floor(($mysql['buffer_pool_mb'] * 0.85) / $tenants['avg_mb']) : null,
                'confidence' => 'high',
                'tunable'    => true,
                'note'       => 'Tenants fully held in memory at today\'s average size of ' . round($tenants['avg_mb'], 1) . ' MB. This number falls as tenants mature.',
            ];

            // ─── E. table_open_cache ─────────────────────────────────────
            $con[] = [
                'key'        => 'table_cache',
                'label'      => 'MySQL table_open_cache',
                'current'    => number_format($mysql['open_tables']) . ' tables open',
                'limit'      => number_format($mysql['table_open_cache']),
                'tenants'    => $tenants['avg_tables'] > 0 ? (int) floor($mysql['table_open_cache'] / $tenants['avg_tables']) : null,
                'confidence' => 'high',
                'tunable'    => true,
                'note'       => 'Each tenant carries about ' . (int) round($tenants['avg_tables']) . ' tables. Past this the cache thrashes and every page reopens files.',
            ];

            // ─── F. open_files_limit ─────────────────────────────────────
            $files_per_tenant = max(1, $tenants['avg_tables'] * 2);   // .ibd + descriptor
            $con[] = [
                'key'        => 'open_files',
                'label'      => 'MySQL open_files_limit',
                'current'    => number_format((int) ($tenants['total_tables'] * 2)) . ' file handles',
                'limit'      => number_format($mysql['open_files_limit']),
                'tenants'    => (int) floor($mysql['open_files_limit'] / $files_per_tenant),
                'confidence' => 'high',
                'tunable'    => true,
                'note'       => 'InnoDB keeps roughly two handles per table with file-per-table storage.',
            ];

            // ─── G. Disk ─────────────────────────────────────────────────
            $disk_per_tenant = max(1, $tenants['avg_mb'] * self::CAP_DISK_FACTOR);
            $con[] = [
                'key'        => 'disk',
                'label'      => 'Disk',
                'current'    => $mem['disk_used_gb'] . ' GB used of ' . $mem['disk_total_gb'] . ' GB',
                'limit'      => round($mem['disk_free_gb'] * 0.80, 1) . ' GB usable',
                'tenants'    => (int) floor(($mem['disk_free_gb'] * 1024 * 0.80) / $disk_per_tenant),
                'confidence' => 'medium',
                'tunable'    => false,
                'note'       => round($disk_per_tenant, 1) . ' MB per tenant (database × ' . self::CAP_DISK_FACTOR
                    . ' for backup copies and uploads). Imaging-heavy tenants will exceed this.',
            ];

            // ─── The wall = the smallest ceiling ─────────────────────────
            $valid = array_filter(array_map(function ($x) { return $x['tenants']; }, $con), function ($v) {
                return $v !== null && $v > 0;
            });
            $hard_max = !empty($valid) ? (int) min($valid) : 0;
            foreach ($con as $i => $x) {
                $con[$i]['binding'] = ($x['tenants'] !== null && $x['tenants'] === $hard_max);
            }

            $safe    = (int) floor($hard_max * self::CAP_SAFE_FACTOR);
            $used_pc = $hard_max > 0 ? round(($tenants['active'] / $hard_max) * 100, 1) : 100;

            if     ($used_pc < 50) { $status = 'healthy';  }
            elseif ($used_pc < 75) { $status = 'moderate'; }
            elseif ($used_pc < 90) { $status = 'tight';    }
            else                   { $status = 'critical'; }

            // ─── Blockers: what is holding the number down, and by how much ─
            $blockers = $this->_cap_blockers($mysql, $per, $tenants, $ps);
            $mult = 1.0;
            foreach ($blockers as $b) { $mult *= $b['multiplier']; }
            $mult = min(3.0, $mult);   // stay credible

            // After tuning: config walls move to their recommended values,
            // hardware walls do not.
            $tuned = [];
            foreach ($con as $x) {
                if ($x['tenants'] === null || $x['tenants'] <= 0) continue;
                switch ($x['key']) {
                    case 'cpu':
                        $tuned[] = (int) floor($x['tenants'] * $mult); break;
                    case 'connections':
                        $tuned[] = $per['conns'] > 0 ? (int) floor((500 - self::CAP_CONN_RESERVE) / $per['conns']) : $x['tenants']; break;
                    case 'table_cache':
                        $tuned[] = $tenants['avg_tables'] > 0 ? (int) floor(40000 / $tenants['avg_tables']) : $x['tenants']; break;
                    case 'open_files':
                        $tuned[] = (int) floor(100000 / $files_per_tenant); break;
                    default:
                        $tuned[] = $x['tenants'];
                }
            }
            $tuned_max = !empty($tuned) ? (int) min($tuned) : $hard_max;

            echo json_encode([
                'verdict' => [
                    'onboarded'      => $tenants['active'],
                    'registered'     => $tenants['total'],
                    'safe'           => $safe,
                    'hard_max'       => $hard_max,
                    'can_add_now'    => max(0, $safe - $tenants['active']),
                    'used_pct'       => $used_pc,
                    'status'         => $status,
                    'tuned_safe'     => (int) floor($tuned_max * self::CAP_SAFE_FACTOR),
                    'tuned_max'      => $tuned_max,
                    'tuned_multiple' => round($hard_max > 0 ? $tuned_max / $hard_max : 1, 1),
                ],
                'constraints' => $con,
                'per_tenant'  => $per,
                'blockers'    => $blockers,
                'basis' => [
                    'cores'          => $cores,
                    'load_now'       => round((float) $load[0], 2),
                    'load_ref'       => round($load_ref, 2),
                    'qps_now'        => $mysql['qps_now'],
                    'qps_ref'        => round($qps_ref, 1),
                    'php_workers'    => $ps['php_workers'],
                    'php_avg_mb'     => $ps['php_avg_mb'],
                    'confidence'     => $cpu_conf,
                    'peak_since'     => $peaks['since'],
                    'peak_load_at'   => $peaks['load_at'],
                    'tenant_scan'    => $tenants['from_cache'] ? 'cached ' . $tenants['cached_age'] . 's ago' : 'live',
                    'tenant_errors'  => $tenants['errors'],
                    'server_time'    => date('Y-m-d H:i:s'),
                ],
            ]);

        } catch (Throwable $e) {
            echo json_encode(['error' => 'Capacity error: ' . $e->getMessage()]);
        } catch (Exception $e) {
            echo json_encode(['error' => 'Capacity error: ' . $e->getMessage()]);
        }
    }

    /**
     * Config and code problems that are suppressing capacity right now.
     * Each carries the multiplier it would return to the CPU ceiling.
     */
    private function _cap_blockers($mysql, $per, $tenants, $ps)
    {
        $out = [];

        if (defined('ENVIRONMENT') && ENVIRONMENT !== 'production') {
            $out[] = [
                'severity'   => 'critical',
                'title'      => 'APP_ENV is "' . ENVIRONMENT . '" on a live server',
                'detail'     => 'display_errors is on, CodeIgniter runs in database debug mode and log_threshold becomes 1 — every request writes log lines to disk.',
                'fix'        => 'Set APP_ENV=production in .env, then clear application/logs.',
                'multiplier' => 1.30,
            ];
        }

        if ($mysql['session_driver'] === 'database') {
            $out[] = [
                'severity'   => 'critical',
                'title'      => 'Sessions are stored in MySQL',
                'detail'     => 'CodeIgniter holds a row lock on the session for the whole request, so a page firing several AJAX calls serialises them. This caps concurrency per user regardless of how idle the server looks.',
                'fix'        => 'SESS_DRIVER=redis with SESS_SAVE_PATH=tcp://127.0.0.1:6379?database=0',
                'multiplier' => 1.50,
            ];
        }

        if ($per['qps'] > 20) {
            $out[] = [
                'severity'   => 'critical',
                'title'      => 'Each tenant generates ' . $per['qps'] . ' queries/sec of background load',
                'detail'     => 'That is polling and cron, not people. Background load scales with tenant count whether anyone is logged in or not, which is what caps on-boarding well below the hardware limit.',
                'fix'        => 'Throttle the polling endpoints (token system, dashboard widgets, chat) and make sure no cron tick overruns its interval.',
                'multiplier' => 1.35,
            ];
        }

        if ($mysql['tmp_disk_ratio'] > 0.25) {
            $out[] = [
                'severity'   => 'warning',
                'title'      => round($mysql['tmp_disk_ratio'] * 100) . '% of temporary tables spill to disk',
                'detail'     => 'tmp_table_size is ' . round($mysql['tmp_table_mb']) . ' MB. Report pages and large list views materialise on disk instead of in memory.',
                'fix'        => 'tmp_table_size = 256M and max_heap_table_size = 256M (raise both together).',
                'multiplier' => 1.15,
            ];
        }

        if ($mysql['full_join_rate'] > 0.5) {
            $out[] = [
                'severity'   => 'warning',
                'title'      => number_format($mysql['select_full_join']) . ' joins ran without an index',
                'detail'     => 'Full joins scan whole tables. The cost per page grows with tenant data, so this constraint tightens over time on its own.',
                'fix'        => 'Enable log_queries_not_using_indexes for an hour, then EXPLAIN the top offenders and add the missing indexes.',
                'multiplier' => 1.20,
            ];
        }

        if ($mysql['max_connections'] < 300) {
            $out[] = [
                'severity'   => 'warning',
                'title'      => 'max_connections is only ' . $mysql['max_connections'],
                'detail'     => 'This is a configuration wall, not a hardware one. A traffic burst returns "Too many connections" long before the CPU is stressed, so it caps tenant count for no good reason.',
                'fix'        => 'max_connections = 500 in my.cnf (with ' . $mysql['max_connections'] . ' now and ' . round($mysql['buffer_pool_mb'] / 1024, 1) . ' GB of buffer pool, this box has the RAM for it).',
                'multiplier' => 1.0,
            ];
        }

        if (!$mysql['performance_schema']) {
            $out[] = [
                'severity'   => 'info',
                'title'      => 'performance_schema is OFF',
                'detail'     => 'Without it the server cannot attribute load to a specific query or tenant, so the CPU ceiling above stays an extrapolation instead of a measurement.',
                'fix'        => 'performance_schema = ON in my.cnf, restart MySQL, then reload this page after ~30 minutes of normal traffic.',
                'multiplier' => 1.0,
            ];
        }

        if ($ps['php_workers'] > 0 && $ps['php_workers'] <= 10) {
            $out[] = [
                'severity'   => 'info',
                'title'      => 'Only ' . $ps['php_workers'] . ' PHP-FPM workers are running',
                'detail'     => 'pm.max_children is not readable from PHP. If it is set low it caps how many CRM pages can be served at once, and no amount of tenant head-room helps.',
                'fix'        => 'Check pm.max_children in the PHP-FPM pool config; with ' . $ps['php_avg_mb'] . ' MB per worker this box can afford far more.',
                'multiplier' => 1.0,
            ];
        }

        return $out;
    }

    /** CPU core count. */
    private function _cap_cpu_cores()
    {
        if (is_readable('/proc/cpuinfo')) {
            $info = @file_get_contents('/proc/cpuinfo');
            if ($info !== false) return max(1, substr_count($info, 'processor'));
        }
        if (PHP_OS === 'Darwin' && function_exists('shell_exec')) {
            return max(1, (int) @shell_exec('sysctl -n hw.ncpu 2>/dev/null'));
        }
        return 1;
    }

    /** RAM + disk in one place. */
    private function _cap_mem_info()
    {
        $out = ['total_mb' => 0, 'used_mb' => 0, 'free_mb' => 0];

        if (is_readable('/proc/meminfo')) {
            $mi = @file_get_contents('/proc/meminfo');
            if ($mi !== false) {
                preg_match('/MemTotal:\s+(\d+)/', $mi, $mt);
                if (!preg_match('/MemAvailable:\s+(\d+)/', $mi, $ma)) {
                    preg_match('/MemFree:\s+(\d+)/', $mi, $ma);
                }
                $total = isset($mt[1]) ? (int) $mt[1] : 0;
                $avail = isset($ma[1]) ? (int) $ma[1] : 0;
                if ($total > 0) {
                    $out['total_mb'] = round($total / 1024, 1);
                    $out['free_mb']  = round($avail / 1024, 1);
                    $out['used_mb']  = round(($total - $avail) / 1024, 1);
                }
            }
        }
        if ($out['total_mb'] == 0 && function_exists('shell_exec')) {
            $free = @shell_exec('free -m 2>/dev/null');
            if ($free && preg_match('/Mem:\s+(\d+)\s+(\d+)\s+(\d+)/', $free, $m)) {
                $out['total_mb'] = (int) $m[1];
                $out['used_mb']  = (int) $m[2];
                $out['free_mb']  = (int) $m[3];
            }
        }

        $dt = @disk_total_space('/');
        $df = @disk_free_space('/');
        $out['disk_total_gb'] = $dt ? round($dt / 1073741824, 1) : 0;
        $out['disk_free_gb']  = $df ? round($df / 1073741824, 1) : 0;
        $out['disk_used_gb']  = ($dt && $df) ? round(($dt - $df) / 1073741824, 1) : 0;

        return $out;
    }

    /** PHP-FPM worker count / size and the MySQL resident set, from one ps call. */
    private function _cap_process_table()
    {
        $out = ['php_workers' => 0, 'php_avg_mb' => 0, 'mysql_rss_mb' => 0];
        if (!function_exists('shell_exec')) return $out;

        $raw = @shell_exec('ps -eo rss=,comm= 2>/dev/null');
        if (empty($raw)) return $out;

        $php_kb = 0;
        $my_kb  = 0;
        foreach (explode("\n", $raw) as $line) {
            $line = trim($line);
            if ($line === '') continue;
            $parts = preg_split('/\s+/', $line, 2);
            if (count($parts) < 2) continue;
            $kb   = (int) $parts[0];
            $name = strtolower(trim($parts[1]));
            if (strpos($name, 'php-fpm') !== false || strpos($name, 'php-cgi') !== false) {
                $php_kb += $kb;
                $out['php_workers']++;
            } elseif ($name === 'mariadbd' || $name === 'mysqld' || strpos($name, 'mysqld') !== false) {
                $my_kb += $kb;
            }
        }
        if ($out['php_workers'] > 0) {
            $out['php_avg_mb'] = round(($php_kb / 1024) / $out['php_workers'], 1);
        }
        $out['mysql_rss_mb'] = round($my_kb / 1024, 1);

        return $out;
    }

    /** Everything the capacity model needs out of MySQL, in two queries. */
    private function _cap_mysql_metrics()
    {
        $out = [
            'max_connections' => 151, 'max_used_connections' => 0, 'threads_connected' => 0,
            'table_open_cache' => 2000, 'open_tables' => 0, 'open_files_limit' => 1024,
            'buffer_pool_mb' => 128, 'tmp_table_mb' => 16, 'performance_schema' => false,
            'questions' => 0, 'uptime' => 1, 'qps_avg' => 0, 'qps_now' => 0,
            'created_tmp_tables' => 0, 'created_tmp_disk_tables' => 0, 'tmp_disk_ratio' => 0,
            'select_full_join' => 0, 'full_join_rate' => 0,
            'session_driver' => 'unknown',
        ];

        $vars = [];
        $q = $this->db->query("SHOW GLOBAL VARIABLES WHERE Variable_name IN
            ('max_connections','table_open_cache','open_files_limit','innodb_buffer_pool_size','tmp_table_size','performance_schema')");
        if ($q) {
            foreach ($q->result_array() as $r) {
                $vars[strtolower($r['Variable_name'])] = $r['Value'];
            }
        }
        $status = [];
        $q = $this->db->query("SHOW GLOBAL STATUS WHERE Variable_name IN
            ('Max_used_connections','Threads_connected','Open_tables','Questions','Uptime',
             'Created_tmp_tables','Created_tmp_disk_tables','Select_full_join')");
        if ($q) {
            foreach ($q->result_array() as $r) {
                $status[strtolower($r['Variable_name'])] = $r['Value'];
            }
        }

        if (isset($vars['max_connections']))         $out['max_connections']  = (int) $vars['max_connections'];
        if (isset($vars['table_open_cache']))        $out['table_open_cache'] = (int) $vars['table_open_cache'];
        if (isset($vars['open_files_limit']))        $out['open_files_limit'] = (int) $vars['open_files_limit'];
        if (isset($vars['innodb_buffer_pool_size'])) $out['buffer_pool_mb']   = round($vars['innodb_buffer_pool_size'] / 1048576, 1);
        if (isset($vars['tmp_table_size']))          $out['tmp_table_mb']     = round($vars['tmp_table_size'] / 1048576, 1);
        if (isset($vars['performance_schema']))      $out['performance_schema'] = (strtoupper($vars['performance_schema']) === 'ON');

        foreach (['max_used_connections', 'threads_connected', 'open_tables', 'questions', 'uptime',
                  'created_tmp_tables', 'created_tmp_disk_tables', 'select_full_join'] as $k) {
            if (isset($status[$k])) $out[$k] = (int) $status[$k];
        }

        $out['uptime']  = max(1, $out['uptime']);
        $out['qps_avg'] = round($out['questions'] / $out['uptime'], 1);
        if ($out['created_tmp_tables'] > 0) {
            $out['tmp_disk_ratio'] = round($out['created_tmp_disk_tables'] / $out['created_tmp_tables'], 3);
        }
        $out['full_join_rate'] = round($out['select_full_join'] / $out['uptime'], 2);

        $out['session_driver'] = strtolower((string) config_item('sess_driver'));

        return $out;
    }

    /**
     * Real per-tenant footprint — tables and bytes, measured by connecting to
     * each tenant database. Cached, because it is the expensive part.
     */
    private function _cap_tenant_footprint()
    {
        $cached = get_option('ccx_capacity_tenant_cache');
        if (!empty($cached)) {
            $c = json_decode($cached, true);
            if (is_array($c) && isset($c['at']) && (time() - $c['at']) < self::CAP_TENANT_CACHE_TTL) {
                $c['data']['from_cache'] = true;
                $c['data']['cached_age'] = time() - $c['at'];
                return $c['data'];
            }
        }

        $out = [
            'total' => 0, 'active' => 0, 'inactive' => 0, 'scanned' => 0,
            'total_mb' => 0, 'total_tables' => 0, 'avg_mb' => 0, 'avg_tables' => 0,
            'errors' => [], 'tenants' => [], 'from_cache' => false, 'cached_age' => 0,
        ];

        $this->load->model('perfex_saas/perfex_saas_model');
        $rows = $this->db->select('slug, status')->get(perfex_saas_table('companies'))->result();

        foreach ($rows as $row) {
            $out['total']++;
            $is_active = (strtolower((string) $row->status) === 'active');
            $is_active ? $out['active']++ : $out['inactive']++;

            try {
                $info = $this->_get_db_connection($row->slug);
                if (isset($info['error'])) {
                    $out['errors'][] = $row->slug . ': ' . $info['error'];
                    continue;
                }
                $tdb = $info['conn'];
                $q   = $tdb->query("SELECT COUNT(*) AS tbls,
                                           COALESCE(SUM(data_length + index_length), 0) AS bytes
                                    FROM information_schema.TABLES
                                    WHERE TABLE_SCHEMA = DATABASE()");
                if ($q && ($r = $q->row())) {
                    $mb = round($r->bytes / 1048576, 1);
                    $out['total_mb']     += $mb;
                    $out['total_tables'] += (int) $r->tbls;
                    $out['scanned']++;
                    $out['tenants'][] = [
                        'slug'   => $row->slug,
                        'status' => $is_active ? 'active' : 'inactive',
                        'mb'     => $mb,
                        'tables' => (int) $r->tbls,
                    ];
                }
                if ($row->slug !== 'master' && is_object($tdb)) {
                    $tdb->close();
                }
            } catch (Throwable $e) {
                $out['errors'][] = $row->slug . ': ' . $e->getMessage();
            } catch (Exception $e) {
                $out['errors'][] = $row->slug . ': ' . $e->getMessage();
            }
        }

        if ($out['scanned'] > 0) {
            $out['avg_mb']     = round($out['total_mb'] / $out['scanned'], 2);
            $out['avg_tables'] = round($out['total_tables'] / $out['scanned'], 1);
        }
        // Never divide by zero downstream, and never claim a tenant is free.
        if ($out['avg_mb'] <= 0)     $out['avg_mb']     = 1;
        if ($out['avg_tables'] <= 0) $out['avg_tables'] = 1;

        // autoload 0 — this blob must never join the options loaded on every request.
        update_option('ccx_capacity_tenant_cache', json_encode(['at' => time(), 'data' => $out]), 0);

        return $out;
    }

    /**
     * Remember the busiest moment this server has ever shown us. An idle
     * sample cannot tell you the ceiling; the peak can. Written only when a
     * record is actually broken, so this stays cheap on a 5s poll.
     */
    private function _cap_track_peaks($load, &$mysql)
    {
        $now  = time();
        $peak = [
            'since' => date('Y-m-d H:i'), 'load' => 0, 'load_at' => '',
            'qps' => 0, 'qps_at' => '', 'conns' => 0, 'conns_at' => '',
            'last_questions' => 0, 'last_ts' => 0,
        ];

        $stored = get_option('ccx_capacity_peaks');
        if (!empty($stored)) {
            $s = json_decode($stored, true);
            if (is_array($s)) $peak = array_merge($peak, $s);
        }

        // Instantaneous q/s from the delta between polls — far more useful
        // than Questions/Uptime, which is flattened by hours of idle time.
        $dirty = false;
        if (!empty($peak['last_ts']) && $mysql['questions'] >= $peak['last_questions']) {
            $dt = $now - (int) $peak['last_ts'];
            if ($dt >= 2) {
                $mysql['qps_now'] = round(($mysql['questions'] - $peak['last_questions']) / $dt, 1);
            }
        }
        if (empty($peak['last_ts']) || ($now - (int) $peak['last_ts']) >= 10) {
            $peak['last_questions'] = $mysql['questions'];
            $peak['last_ts']        = $now;
            $dirty = true;
        }

        $load_ref = max((float) $load[1], (float) $load[2]);
        if ($load_ref > (float) $peak['load']) {
            $peak['load']    = round($load_ref, 2);
            $peak['load_at'] = date('Y-m-d H:i');
            $dirty = true;
        }
        $qps_seen = max((float) $mysql['qps_avg'], (float) $mysql['qps_now']);
        if ($qps_seen > (float) $peak['qps']) {
            $peak['qps']    = round($qps_seen, 1);
            $peak['qps_at'] = date('Y-m-d H:i');
            $dirty = true;
        }
        if ((int) $mysql['max_used_connections'] > (int) $peak['conns']) {
            $peak['conns']    = (int) $mysql['max_used_connections'];
            $peak['conns_at'] = date('Y-m-d H:i');
            $dirty = true;
        }

        if ($dirty) {
            update_option('ccx_capacity_peaks', json_encode($peak), 0);
        }

        return $peak;
    }
}
