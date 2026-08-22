<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Ccx_security extends AdminController
{
    public function __construct()
    {
        parent::__construct();

        // Only master admins can access — tenants get security enforced silently
        if (!is_admin() || ccx_security_is_tenant()) {
            access_denied('CCX Security');
        }

        $this->load->model('ccx_security/ccx_security_model');
        $this->load->helper('ccx_security/ccx_security');
    }

    /**
     * Security Dashboard
     */
    public function index()
    {
        $data['title'] = _l('ccx_security_dashboard');
        $data['tenant_name'] = ccx_security_get_tenant_name();
        $data['is_tenant'] = ccx_security_is_tenant();
        $data['stats'] = $this->ccx_security_model->get_security_stats();
        $data['score'] = ccx_security_get_score();
        $data['score_info'] = ccx_security_score_info($data['score']);

        // Feature toggles for dashboard cards
        $data['features'] = [
            [
                'key'     => 'http_headers_enabled',
                'name'    => _l('ccx_security_http_headers'),
                'desc'    => _l('ccx_security_http_headers_desc'),
                'icon'    => 'fa fa-globe',
                'color'   => '#3b82f6',
            ],
            [
                'key'     => 'devtools_block_enabled',
                'name'    => _l('ccx_security_devtools_block'),
                'desc'    => _l('ccx_security_devtools_block_desc'),
                'icon'    => 'fa fa-code',
                'color'   => '#8b5cf6',
            ],
            [
                'key'     => 'xss_protection_enabled',
                'name'    => _l('ccx_security_xss_protection'),
                'desc'    => _l('ccx_security_xss_protection_desc'),
                'icon'    => 'fa fa-ban',
                'color'   => '#ef4444',
            ],
            [
                'key'     => 'csrf_hardening_enabled',
                'name'    => _l('ccx_security_csrf_hardening'),
                'desc'    => _l('ccx_security_csrf_hardening_desc'),
                'icon'    => 'fa fa-lock',
                'color'   => '#f59e0b',
            ],
            [
                'key'     => 'file_upload_scan_enabled',
                'name'    => _l('ccx_security_file_upload_scan'),
                'desc'    => _l('ccx_security_file_upload_scan_desc'),
                'icon'    => 'fa fa-upload',
                'color'   => '#10b981',
            ],
            [
                'key'     => 'session_protection_enabled',
                'name'    => _l('ccx_security_session_protection'),
                'desc'    => _l('ccx_security_session_protection_desc'),
                'icon'    => 'fa fa-key',
                'color'   => '#06b6d4',
            ],
            [
                'key'     => 'brute_force_enabled',
                'name'    => _l('ccx_security_brute_force'),
                'desc'    => _l('ccx_security_brute_force_desc'),
                'icon'    => 'fa fa-user-secret',
                'color'   => '#ec4899',
            ],
            [
                'key'     => 'sql_monitor_enabled',
                'name'    => _l('ccx_security_sql_monitor'),
                'desc'    => _l('ccx_security_sql_monitor_desc'),
                'icon'    => 'fa fa-database',
                'color'   => '#f97316',
            ],
            [
                'key'     => 'audit_log_enabled',
                'name'    => _l('ccx_security_audit_log_feature'),
                'desc'    => _l('ccx_security_audit_log_desc'),
                'icon'    => 'fa fa-list-alt',
                'color'   => '#64748b',
            ],
            [
                'key'     => 'right_click_block',
                'name'    => _l('ccx_security_right_click'),
                'desc'    => _l('ccx_security_right_click_desc'),
                'icon'    => 'fa fa-mouse-pointer',
                'color'   => '#a855f7',
            ],
            // ─── New Enterprise Features ───
            [
                'key'     => '2fa_enabled',
                'name'    => _l('ccx_security_2fa'),
                'desc'    => _l('ccx_security_2fa_desc'),
                'icon'    => 'fa fa-shield',
                'color'   => '#0ea5e9',
            ],
            [
                'key'     => 'ip_whitelist_enabled',
                'name'    => _l('ccx_security_ip_whitelist'),
                'desc'    => _l('ccx_security_ip_whitelist_desc'),
                'icon'    => 'fa fa-map-marker',
                'color'   => '#14b8a6',
            ],
            [
                'key'     => 'password_policy_enabled',
                'name'    => _l('ccx_security_password_policy'),
                'desc'    => _l('ccx_security_password_policy_desc'),
                'icon'    => 'fa fa-asterisk',
                'color'   => '#e11d48',
            ],
            [
                'key'     => 'session_tracking_enabled',
                'name'    => _l('ccx_security_session_tracking'),
                'desc'    => _l('ccx_security_session_tracking_desc'),
                'icon'    => 'fa fa-desktop',
                'color'   => '#7c3aed',
            ],
        ];

        $this->load->view('ccx_security/dashboard', $data);
    }

    /**
     * Save security settings via AJAX or POST
     */
    public function save_settings()
    {
        if ($this->input->is_ajax_request() || $this->input->post()) {
            $post = $this->input->post();

            $modal_toggles = [
                'ccx_security_enabled',
                'ccx_security_hsts_enabled',
                'ccx_security_bf_notify_admin',
                'ccx_security_concurrent_sessions',
                // New toggles
                'ccx_security_2fa_enforce_all',
                'ccx_security_pw_require_upper',
                'ccx_security_pw_require_lower',
                'ccx_security_pw_require_number',
                'ccx_security_pw_require_special',
            ];

            foreach ($modal_toggles as $toggle) {
                $value = isset($post[$toggle]) ? '1' : '0';
                update_option($toggle, $value);
            }

            $text_settings = [
                'ccx_security_bf_max_attempts',
                'ccx_security_bf_lockout_minutes',
                'ccx_security_session_timeout_minutes',
                'ccx_security_csp_mode',
                'ccx_security_x_frame_options',
                'ccx_security_referrer_policy',
                'ccx_security_blocked_extensions',
                'ccx_security_max_upload_mb',
                'ccx_security_audit_retention_days',
                'ccx_security_inspect_message',
                // New settings
                'ccx_security_pw_min_length',
                'ccx_security_pw_expiry_days',
                'ccx_security_pw_history_count',
                'ccx_security_max_active_sessions',
            ];

            foreach ($text_settings as $key) {
                if (isset($post[$key])) {
                    update_option($key, $this->input->post($key, true));
                }
            }

            ccx_security_log_event('settings_changed', 'Security settings updated by admin', 'info');

            if ($this->input->is_ajax_request()) {
                echo json_encode(['success' => true, 'message' => _l('ccx_security_settings_saved')]);
                exit;
            }

            set_alert('success', _l('ccx_security_settings_saved'));
            redirect(admin_url('ccx_security'));
        }
    }

    /**
     * Audit Log page
     */
    public function audit_log()
    {
        $data['title'] = _l('ccx_security_audit_log');
        $data['tenant_name'] = ccx_security_get_tenant_name();
        $data['is_tenant'] = ccx_security_is_tenant();

        $filters = [
            'event_type' => $this->input->get('event_type'),
            'severity'   => $this->input->get('severity'),
            'date_from'  => $this->input->get('date_from'),
            'date_to'    => $this->input->get('date_to'),
            'search'     => $this->input->get('search'),
            'limit'      => 50,
            'offset'     => (int) $this->input->get('offset'),
        ];

        $result = $this->ccx_security_model->get_audit_log($filters);
        $data['logs'] = $result['data'];
        $data['total'] = $result['total'];
        $data['filters'] = $filters;

        $data['event_types'] = [
            'login_success', 'login_failed', 'login_blocked',
            'ip_blocked', 'ip_unblocked',
            'sql_injection_attempt', 'xss_attempt',
            'file_upload_blocked', 'settings_changed',
            'session_ip_change', '2fa_setup', '2fa_verified',
            '2fa_failed', 'ip_whitelist_denied', 'password_expired',
            'session_terminated',
        ];

        $this->load->view('ccx_security/audit_log', $data);
    }

    /**
     * Blocked IPs page
     */
    public function blocked_ips()
    {
        $data['title'] = _l('ccx_security_blocked_ips');
        $data['tenant_name'] = ccx_security_get_tenant_name();
        $data['is_tenant'] = ccx_security_is_tenant();
        $data['blocked'] = $this->ccx_security_model->get_blocked_ips();

        $this->load->view('ccx_security/blocked_ips', $data);
    }

    /**
     * Unblock an IP
     */
    public function unblock_ip($id)
    {
        $id = (int) $id;
        if ($id > 0) {
            $this->ccx_security_model->unblock_ip($id);
            ccx_security_log_event('ip_unblocked', 'IP unblocked by admin (record ID: ' . $id . ')', 'info');
            set_alert('success', _l('ccx_security_ip_unblocked_success'));
        }
        redirect(admin_url('ccx_security/blocked_ips'));
    }

    /**
     * Manually block an IP
     */
    public function block_ip()
    {
        $ip = $this->input->post('ip_address', true);
        $reason = $this->input->post('reason', true) ?: 'Manual block by admin';
        $permanent = $this->input->post('permanent') ? true : false;
        $duration = $permanent ? 0 : (int) ($this->input->post('duration_minutes') ?: 60);

        if (!empty($ip) && filter_var($ip, FILTER_VALIDATE_IP)) {
            $this->ccx_security_model->block_ip($ip, $reason, $permanent ? 0 : $duration);
            ccx_security_log_event('ip_blocked', 'IP manually blocked by admin: ' . $ip . ' — ' . $reason, 'warning');
            set_alert('success', _l('ccx_security_ip_blocked_success'));
        } else {
            set_alert('danger', 'Invalid IP address.');
        }

        redirect(admin_url('ccx_security/blocked_ips'));
    }

    /**
     * Clear audit log
     */
    public function clear_audit_log()
    {
        $this->ccx_security_model->clear_audit_log(0);
        ccx_security_log_event('audit_log_cleared', 'Audit log cleared by admin', 'info');
        set_alert('success', _l('ccx_security_log_cleared'));
        redirect(admin_url('ccx_security/audit_log'));
    }

    /**
     * Toggle a single feature via AJAX
     */
    public function toggle_feature()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $key = $this->input->post('key');
        $full_key = 'ccx_security_' . $key;
        $current = get_option($full_key);
        $new_value = ($current === '1') ? '0' : '1';

        update_option($full_key, $new_value);
        ccx_security_log_event('settings_changed', 'Toggled ' . $full_key . ' to ' . $new_value, 'info');

        echo json_encode([
            'success'   => true,
            'new_value' => $new_value,
            'score'     => ccx_security_get_score(),
            'csrf_hash' => $this->security->get_csrf_hash(),
        ]);
    }


    // ═══════════════════════════════════════════════════════════════
    // ─── TWO-FACTOR AUTHENTICATION ───
    // ═══════════════════════════════════════════════════════════════

    /**
     * 2FA Setup page — Generate secret and QR code
     */
    public function setup_2fa($staff_id = null)
    {
        if (!$staff_id) {
            $staff_id = get_staff_user_id();
        }

        $data['title'] = _l('ccx_security_2fa_setup');
        $data['tenant_name'] = ccx_security_get_tenant_name();
        $data['is_tenant'] = ccx_security_is_tenant();
        $data['staff_id'] = $staff_id;

        // Handle reset request — delete existing secret and start fresh
        if ($this->input->get('reset') === '1') {
            $this->ccx_security_model->disable_2fa($staff_id);
            $this->session->unset_userdata('_ccx_2fa_verified');
            ccx_security_log_event('2fa_reset', '2FA reset initiated for staff ID: ' . $staff_id, 'info');
            set_alert('warning', 'Your old 2FA has been removed from the system. Please also manually delete the old entry from your authenticator app (Google Authenticator / Authy), then scan the new QR code below.');
            redirect(admin_url('ccx_security/setup_2fa/' . $staff_id));
            return;
        }

        // Check if already set up AND verified
        $existing = $this->ccx_security_model->get_2fa_secret($staff_id);
        if ($existing && $existing->verified_at) {
            $data['already_setup'] = true;
            $data['setup_date'] = $existing->verified_at;
        } else {
            $data['already_setup'] = false;

            // Reuse existing unverified secret (don't regenerate on page refresh)
            if ($existing && !$existing->verified_at) {
                $secret = $existing->secret;
                $recovery_codes = json_decode($existing->recovery_codes, true) ?: [];
            } else {
                // Generate new secret + recovery codes
                $secret = ccx_security_generate_totp_secret();
                $recovery_codes = ccx_security_generate_recovery_codes(10);
                $this->ccx_security_model->save_2fa_secret($staff_id, $secret, $recovery_codes);
            }

            // Get staff email for QR
            $staff = get_staff($staff_id);
            $email = $staff ? $staff->email : 'admin@example.com';
            $issuer = get_option('companyname') ?: 'CCX Security';

            $uri = ccx_security_totp_uri($secret, $email, $issuer);

            $data['secret'] = $secret;
            $data['qr_code'] = ccx_security_generate_qr_svg($uri);
            $data['recovery_codes'] = $recovery_codes;
        }

        $this->load->view('ccx_security/setup_2fa', $data);
    }

    /**
     * Verify 2FA code during setup (first-time verification)
     */
    public function verify_2fa_setup()
    {
        $staff_id = $this->input->post('staff_id') ?: get_staff_user_id();
        $code = $this->input->post('code', true);

        $valid = $this->ccx_security_model->verify_2fa_code($staff_id, $code);

        if ($valid) {
            $this->ccx_security_model->mark_2fa_verified($staff_id);
            ccx_security_log_event('2fa_setup', '2FA successfully configured for staff ID: ' . $staff_id, 'info');
            set_alert('success', _l('ccx_security_2fa_setup_success'));
        } else {
            ccx_security_log_event('2fa_failed', '2FA setup verification failed for staff ID: ' . $staff_id, 'warning');
            set_alert('danger', _l('ccx_security_2fa_invalid_code'));
        }

        redirect(admin_url('ccx_security/setup_2fa/' . $staff_id));
    }

    /**
     * Verify 2FA code during login (intercept page POST handler)
     */
    public function verify_2fa_login()
    {
        $staff_id = $this->session->userdata('_ccx_2fa_pending_staff');
        $code = $this->input->post('code', true);
        $is_recovery = $this->input->post('use_recovery') ? true : false;

        if (!$staff_id) {
            redirect(admin_url('authentication'));
            return;
        }

        $valid = false;
        if ($is_recovery) {
            $valid = $this->ccx_security_model->use_recovery_code($staff_id, $code);
        } else {
            $valid = $this->ccx_security_model->verify_2fa_code($staff_id, $code);
        }

        if ($valid) {
            // Mark 2FA as passed for this session
            $this->session->set_userdata('_ccx_2fa_verified', true);
            $this->session->unset_userdata('_ccx_2fa_pending_staff');
            ccx_security_log_event('2fa_verified', '2FA verified for staff ID: ' . $staff_id, 'info');
            redirect(admin_url());
        } else {
            ccx_security_log_event('2fa_failed', '2FA verification failed for staff ID: ' . $staff_id, 'warning', true);
            set_alert('danger', _l('ccx_security_2fa_invalid_code'));
            redirect(admin_url('ccx_security/verify_2fa'));
        }
    }

    /**
     * 2FA verification page (login intercept)
     */
    public function verify_2fa()
    {
        $staff_id = $this->session->userdata('_ccx_2fa_pending_staff');
        if (!$staff_id) {
            redirect(admin_url());
            return;
        }

        $data['title'] = _l('ccx_security_2fa_verify');
        $data['tenant_name'] = ccx_security_get_tenant_name();
        $data['is_tenant'] = ccx_security_is_tenant();
        $data['staff_id'] = $staff_id;

        $this->load->view('ccx_security/verify_2fa', $data);
    }

    /**
     * Disable 2FA for a staff member (admin action)
     */
    public function disable_2fa($staff_id)
    {
        $staff_id = (int) $staff_id;
        if ($staff_id > 0) {
            $this->ccx_security_model->disable_2fa($staff_id);
            $this->session->unset_userdata('_ccx_2fa_verified');
            ccx_security_log_event('2fa_disabled', '2FA disabled by admin for staff ID: ' . $staff_id, 'warning');
            set_alert('success', '2FA has been disabled. Please also manually delete the old entry from your authenticator app (Google Authenticator / Authy) to avoid confusion.');
        }
        redirect(admin_url('ccx_security/setup_2fa/' . $staff_id));
    }


    // ═══════════════════════════════════════════════════════════════
    // ─── IP WHITELIST ───
    // ═══════════════════════════════════════════════════════════════

    /**
     * IP Whitelist management page
     */
    public function ip_whitelist()
    {
        $data['title'] = _l('ccx_security_ip_whitelist');
        $data['tenant_name'] = ccx_security_get_tenant_name();
        $data['is_tenant'] = ccx_security_is_tenant();
        $data['whitelist'] = $this->ccx_security_model->get_ip_whitelist();
        $data['current_ip'] = ccx_security_get_client_ip();

        $this->load->view('ccx_security/ip_whitelist', $data);
    }

    /**
     * Add an IP to the whitelist
     */
    public function add_whitelist_ip()
    {
        $ip = $this->input->post('ip_address', true);
        $label = $this->input->post('label', true) ?: '';
        $is_cidr = (strpos($ip, '/') !== false);

        if (!empty($ip)) {
            // Validate IP or CIDR
            $valid = false;
            if ($is_cidr) {
                $parts = explode('/', $ip, 2);
                $valid = filter_var($parts[0], FILTER_VALIDATE_IP) && isset($parts[1]) && is_numeric($parts[1]);
            } else {
                $valid = filter_var($ip, FILTER_VALIDATE_IP);
            }

            if ($valid) {
                $result = $this->ccx_security_model->add_ip_whitelist($ip, $label, $is_cidr, get_staff_user_id());
                if ($result) {
                    ccx_security_log_event('ip_whitelisted', 'IP whitelisted: ' . $ip . ' (' . $label . ')', 'info');
                    set_alert('success', _l('ccx_security_whitelist_added'));
                } else {
                    set_alert('warning', _l('ccx_security_whitelist_duplicate'));
                }
            } else {
                set_alert('danger', 'Invalid IP address or CIDR range.');
            }
        }

        redirect(admin_url('ccx_security/ip_whitelist'));
    }

    /**
     * Remove an IP from the whitelist
     */
    public function remove_whitelist_ip($id)
    {
        $id = (int) $id;
        if ($id > 0) {
            $this->ccx_security_model->remove_ip_whitelist($id);
            ccx_security_log_event('ip_whitelist_removed', 'IP removed from whitelist (ID: ' . $id . ')', 'info');
            set_alert('success', _l('ccx_security_whitelist_removed'));
        }
        redirect(admin_url('ccx_security/ip_whitelist'));
    }


    // ═══════════════════════════════════════════════════════════════
    // ─── ACTIVE SESSIONS ───
    // ═══════════════════════════════════════════════════════════════

    /**
     * Active Sessions management page
     */
    public function active_sessions()
    {
        $data['title'] = _l('ccx_security_active_sessions');
        $data['tenant_name'] = ccx_security_get_tenant_name();
        $data['is_tenant'] = ccx_security_is_tenant();

        // Clean up stale/orphaned sessions before displaying
        $this->ccx_security_model->cleanup_expired_sessions();

        $data['sessions'] = $this->ccx_security_model->get_active_sessions();
        $data['current_session'] = session_id();

        $this->load->view('ccx_security/active_sessions', $data);
    }

    /**
     * Terminate a specific session
     */
    public function kill_session($id)
    {
        $id = (int) $id;
        if ($id > 0) {
            $this->ccx_security_model->terminate_session_by_id($id);
            ccx_security_log_event('session_terminated', 'Session terminated by admin (record ID: ' . $id . ')', 'warning');
            set_alert('success', _l('ccx_security_session_killed'));
        }
        redirect(admin_url('ccx_security/active_sessions'));
    }


    // ═══════════════════════════════════════════════════════════════
    // ─── AUDIT LOG EXPORT ───
    // ═══════════════════════════════════════════════════════════════

    /**
     * Export audit log as CSV
     */
    public function export_audit_log()
    {
        $format = $this->input->get('format') ?: 'csv';

        $filters = [
            'event_type' => $this->input->get('event_type'),
            'severity'   => $this->input->get('severity'),
            'date_from'  => $this->input->get('date_from'),
            'date_to'    => $this->input->get('date_to'),
            'search'     => $this->input->get('search'),
        ];

        $logs = $this->ccx_security_model->get_audit_log_for_export($filters);

        if ($format === 'csv') {
            $this->_export_csv($logs);
        } else {
            // Simple HTML download for PDF-like format
            $this->_export_html($logs);
        }
    }

    /**
     * Export as CSV
     */
    private function _export_csv($logs)
    {
        $filename = 'ccx_security_audit_log_' . date('Y-m-d_His') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        // Header row
        fputcsv($output, [
            'ID', 'Event Type', 'Severity', 'Description', 'IP Address',
            'Staff', 'Tenant', 'Request URI', 'Method', 'User Agent', 'Timestamp'
        ]);

        foreach ($logs as $log) {
            fputcsv($output, [
                $log->id,
                $log->event_type,
                $log->severity,
                $log->description,
                $log->ip_address,
                $log->staff_name ?? 'System',
                $log->tenant_name ?? 'Master',
                $log->request_uri,
                $log->request_method,
                $log->user_agent,
                $log->created_at,
            ]);
        }

        fclose($output);
        exit;
    }

    /**
     * Export as downloadable HTML report
     */
    private function _export_html($logs)
    {
        $filename = 'ccx_security_audit_report_' . date('Y-m-d_His') . '.html';
        $company = get_option('companyname') ?: 'CCX Security';

        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Security Audit Report</title>';
        $html .= '<style>body{font-family:Inter,Arial,sans-serif;margin:40px;color:#1e293b;}';
        $html .= 'h1{color:#0f172a;border-bottom:3px solid #3b82f6;padding-bottom:10px;}';
        $html .= '.meta{color:#64748b;font-size:13px;margin-bottom:30px;}';
        $html .= 'table{width:100%;border-collapse:collapse;font-size:12px;}';
        $html .= 'th{background:#1e293b;color:#fff;padding:10px 8px;text-align:left;}';
        $html .= 'td{padding:8px;border-bottom:1px solid #e2e8f0;}';
        $html .= 'tr:nth-child(even){background:#f8fafc;}';
        $html .= '.sev-info{background:#dbeafe;color:#1d4ed8;padding:2px 8px;border-radius:4px;font-size:11px;}';
        $html .= '.sev-warning{background:#fef3c7;color:#92400e;padding:2px 8px;border-radius:4px;font-size:11px;}';
        $html .= '.sev-critical{background:#fee2e2;color:#991b1b;padding:2px 8px;border-radius:4px;font-size:11px;}';
        $html .= '.footer{margin-top:30px;text-align:center;color:#94a3b8;font-size:11px;border-top:1px solid #e2e8f0;padding-top:15px;}';
        $html .= '</style></head><body>';
        $html .= '<h1>🔒 Security Audit Report</h1>';
        $html .= '<div class="meta"><strong>' . htmlspecialchars($company) . '</strong> — Generated: ' . date('F j, Y \a\t g:i A') . ' — Total Records: ' . count($logs) . '</div>';
        $html .= '<table><thead><tr><th>#</th><th>Severity</th><th>Event</th><th>Description</th><th>IP</th><th>User</th><th>Timestamp</th></tr></thead><tbody>';

        foreach ($logs as $i => $log) {
            $sev_class = 'sev-' . $log->severity;
            $html .= '<tr>';
            $html .= '<td>' . ($i + 1) . '</td>';
            $html .= '<td><span class="' . $sev_class . '">' . ucfirst($log->severity) . '</span></td>';
            $html .= '<td>' . htmlspecialchars($log->event_type) . '</td>';
            $html .= '<td>' . htmlspecialchars(substr($log->description, 0, 200)) . '</td>';
            $html .= '<td>' . htmlspecialchars($log->ip_address ?? '') . '</td>';
            $html .= '<td>' . htmlspecialchars($log->staff_name ?? 'System') . '</td>';
            $html .= '<td>' . $log->created_at . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';
        $html .= '<div class="footer">This report was generated by CCX Security Module. All timestamps are in server timezone.<br/>© ' . date('Y') . ' ' . htmlspecialchars($company) . '</div>';
        $html .= '</body></html>';

        echo $html;
        exit;
    }


    // ═══════════════════════════════════════════════════════════════
    // ─── COMPLIANCE CHECKLIST ───
    // ═══════════════════════════════════════════════════════════════

    /**
     * Security Compliance Checklist page
     */
    public function compliance()
    {
        $data['title'] = _l('ccx_security_compliance');
        $data['tenant_name'] = ccx_security_get_tenant_name();
        $data['is_tenant'] = ccx_security_is_tenant();
        $data['checklist'] = ccx_security_get_compliance_checklist();
        $data['score'] = ccx_security_get_score();
        $data['score_info'] = ccx_security_score_info($data['score']);

        // Calculate compliance by category
        $categories = [];
        foreach ($data['checklist'] as $item) {
            $cat = $item['category'];
            if (!isset($categories[$cat])) {
                $categories[$cat] = ['total' => 0, 'passed' => 0];
            }
            $categories[$cat]['total']++;
            if ($item['passed']) {
                $categories[$cat]['passed']++;
            }
        }
        $data['categories'] = $categories;

        $this->load->view('ccx_security/compliance', $data);
    }


    // ═══════════════════════════════════════════════════════════════
    // ─── TENANTS SECURITY (Master Only) ───
    // ═══════════════════════════════════════════════════════════════

    /**
     * Tenants Security overview page — shows all tenants with their security posture
     * Master account only.
     */
    public function tenants_security()
    {
        // Master-only guard
        if (ccx_security_is_tenant()) {
            access_denied('CCX Security — Tenants Security');
        }

        $data['title'] = _l('ccx_security_tenants_security');
        $data['tenant_name'] = ccx_security_get_tenant_name();
        $data['is_tenant'] = false;

        // Ensure ccx_security module is installed/synced on all tenants via SaaS cron
        if (function_exists('perfex_saas_trigger_module_install')) {
            perfex_saas_trigger_module_install(CCX_SECURITY_MODULE_NAME);
        }

        // Load all tenants with security summary
        $data['tenants'] = $this->ccx_security_model->get_all_tenants_with_security();

        // Aggregate stats for header cards
        $total_tenants = count($data['tenants']);
        $active_tenants = 0;
        $total_sessions = 0;
        $total_score = 0;
        $scored_count = 0;

        foreach ($data['tenants'] as $t) {
            if (isset($t->status) && $t->status === 'active') {
                $active_tenants++;
                $total_sessions += $t->security_summary->active_sessions;
                $total_score += $t->security_summary->score;
                if ($t->security_summary->score > 0 || $t->security_summary->features_enabled > 0) {
                    $scored_count++;
                }
            }
        }

        $data['stats'] = [
            'total_tenants'   => $total_tenants,
            'active_tenants'  => $active_tenants,
            'total_sessions'  => $total_sessions,
            'avg_score'       => $scored_count > 0 ? round($total_score / $scored_count) : 0,
        ];

        // Feature definitions for the detail view
        $data['feature_definitions'] = [
            'http_headers_enabled'       => ['name' => 'HTTP Headers',       'icon' => 'fa-globe',        'color' => '#3b82f6'],
            'devtools_block_enabled'     => ['name' => 'DevTools Block',     'icon' => 'fa-code',         'color' => '#8b5cf6'],
            'xss_protection_enabled'     => ['name' => 'XSS Protection',    'icon' => 'fa-ban',          'color' => '#ef4444'],
            'csrf_hardening_enabled'     => ['name' => 'CSRF Hardening',    'icon' => 'fa-lock',         'color' => '#f59e0b'],
            'file_upload_scan_enabled'   => ['name' => 'File Scan',         'icon' => 'fa-upload',       'color' => '#10b981'],
            'session_protection_enabled' => ['name' => 'Session Security',  'icon' => 'fa-key',          'color' => '#06b6d4'],
            'brute_force_enabled'        => ['name' => 'Brute Force',       'icon' => 'fa-user-secret',  'color' => '#ec4899'],
            'sql_monitor_enabled'        => ['name' => 'SQL Monitor',       'icon' => 'fa-database',     'color' => '#f97316'],
            'audit_log_enabled'          => ['name' => 'Audit Log',         'icon' => 'fa-list-alt',     'color' => '#64748b'],
            'right_click_block'          => ['name' => 'Right-Click',       'icon' => 'fa-mouse-pointer','color' => '#a855f7'],
            '2fa_enabled'                => ['name' => '2FA',               'icon' => 'fa-shield',       'color' => '#0ea5e9'],
            'ip_whitelist_enabled'       => ['name' => 'IP Whitelist',      'icon' => 'fa-map-marker',   'color' => '#14b8a6'],
            'password_policy_enabled'    => ['name' => 'Password Policy',   'icon' => 'fa-asterisk',     'color' => '#e11d48'],
            'session_tracking_enabled'   => ['name' => 'Session Tracking',  'icon' => 'fa-desktop',      'color' => '#7c3aed'],
        ];

        $this->load->view('ccx_security/tenants_security', $data);
    }

    /**
     * AJAX: Get active sessions for a specific tenant
     */
    public function tenant_sessions_ajax()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        if (ccx_security_is_tenant()) {
            echo json_encode(['success' => false, 'error' => 'Master only']);
            exit;
        }

        $slug = $this->input->get('slug', true);
        if (empty($slug)) {
            echo json_encode(['success' => false, 'error' => 'Missing slug']);
            exit;
        }

        $sessions = $this->ccx_security_model->get_tenant_active_sessions_cross_db($slug);

        $html = '';
        if ($sessions === null) {
            $html = '<div class="text-center text-muted" style="padding:20px;"><i class="fa fa-exclamation-triangle"></i> Unable to connect to tenant database.</div>';
        } elseif (empty($sessions)) {
            $html = '<div class="text-center text-muted" style="padding:20px;"><i class="fa fa-desktop"></i> ' . _l('ccx_security_no_sessions') . '</div>';
        } else {
            $html .= '<div class="table-responsive"><table class="table table-striped" style="font-size:12px;margin-bottom:0;">';
            $html .= '<thead><tr><th>Staff</th><th>Email</th><th>IP Address</th><th>Device</th><th>Last Activity</th><th>Started</th></tr></thead><tbody>';
            foreach ($sessions as $s) {
                $mins_ago = round((time() - strtotime($s->last_activity)) / 60);
                $activity_str = $mins_ago < 1 ? 'Just now' : ($mins_ago < 60 ? $mins_ago . ' min ago' : round($mins_ago / 60) . 'h ago');
                $is_idle = $mins_ago > 30;
                $status_badge = $is_idle
                    ? '<span class="label label-warning" style="font-size:10px;">Idle</span>'
                    : '<span class="label" style="background:#7c3aed;color:#fff;font-size:10px;">Active</span>';

                $html .= '<tr>';
                $html .= '<td><strong>' . htmlspecialchars($s->staff_name ?? 'Unknown') . '</strong> ' . $status_badge . '</td>';
                $html .= '<td>' . htmlspecialchars($s->staff_email ?? '') . '</td>';
                $html .= '<td><code style="font-size:11px;">' . htmlspecialchars($s->ip_address ?? '') . '</code></td>';
                $html .= '<td>' . htmlspecialchars($s->device_info ?? ccx_security_parse_device($s->user_agent ?? '')) . '</td>';
                $html .= '<td>' . $activity_str . '</td>';
                $html .= '<td>' . date('M d, H:i', strtotime($s->created_at)) . '</td>';
                $html .= '</tr>';
            }
            $html .= '</tbody></table></div>';
        }

        echo json_encode([
            'success' => true,
            'html'    => $html,
            'count'   => is_array($sessions) ? count($sessions) : 0,
        ]);
        exit;
    }

    /**
     * AJAX: Get security detail options for a specific tenant
     */
    public function tenant_security_detail()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        if (ccx_security_is_tenant()) {
            echo json_encode(['success' => false]);
            exit;
        }

        $slug = $this->input->get('slug', true);
        if (empty($slug)) {
            echo json_encode(['success' => false, 'error' => 'Missing slug']);
            exit;
        }

        $options = $this->ccx_security_model->get_tenant_security_options($slug);

        echo json_encode([
            'success' => $options !== null,
            'options' => $options,
        ]);
        exit;
    }

    /**
     * AJAX: Bulk apply security settings to multiple selected tenants.
     *
     * Applies ONLY the explicit feature states chosen in the bulk modal to the
     * tenants the admin selected. Reuses the same safe, per-key delete-then-insert
     * writer used by the individual save (apply_security_settings_to_tenant), so
     * exactly one option row per key is written and duplicates self-heal.
     * Master account only.
     */
    public function bulk_apply_security()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        if (ccx_security_is_tenant()) {
            echo json_encode(['success' => false, 'error' => 'Master only']);
            exit;
        }

        $slugs    = $this->input->post('tenant_slugs');
        $settings = $this->input->post('settings');

        if (empty($slugs) || !is_array($slugs)) {
            echo json_encode([
                'success'   => false,
                'error'     => 'No tenants selected.',
                'csrf_hash' => $this->security->get_csrf_hash(),
            ]);
            exit;
        }

        if (empty($settings) || !is_array($settings)) {
            echo json_encode([
                'success'   => false,
                'error'     => 'No features selected to apply.',
                'csrf_hash' => $this->security->get_csrf_hash(),
            ]);
            exit;
        }

        // Sanitize: only allow ccx_security_* keys with values of '0' or '1'
        // (identical whitelist to the individual save, so nothing unexpected is written).
        $clean_settings = [];
        foreach ($settings as $key => $value) {
            if (strpos($key, 'ccx_security_') === 0 && in_array($value, ['0', '1'], true)) {
                $clean_settings[$key] = $value;
            }
        }

        if (empty($clean_settings)) {
            echo json_encode([
                'success'   => false,
                'error'     => 'No valid settings to apply.',
                'csrf_hash' => $this->security->get_csrf_hash(),
            ]);
            exit;
        }

        $success_count = 0;
        $fail_count    = 0;
        $failed_slugs  = [];

        foreach ($slugs as $slug) {
            $slug = trim((string) $slug);
            if ($slug === '') {
                continue;
            }

            $result = $this->ccx_security_model->apply_security_settings_to_tenant($slug, $clean_settings);
            if ($result) {
                $success_count++;
            } else {
                $fail_count++;
                $failed_slugs[] = $slug;
            }
        }

        ccx_security_log_event(
            'settings_changed',
            'Bulk security applied to ' . $success_count . ' tenant(s). Keys: ' . implode(', ', array_keys($clean_settings))
                . ($fail_count > 0 ? '. Failed for ' . $fail_count . ' (' . implode(', ', $failed_slugs) . ')' : ''),
            'info'
        );

        echo json_encode([
            'success'       => true,
            'success_count' => $success_count,
            'fail_count'    => $fail_count,
            'failed_slugs'  => $failed_slugs,
            'csrf_hash'     => $this->security->get_csrf_hash(),
        ]);
        exit;
    }

    /**
     * AJAX: Save security settings for a specific tenant (individual toggle saves)
     */
    public function save_tenant_security()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        if (ccx_security_is_tenant()) {
            echo json_encode(['success' => false, 'error' => 'Master only']);
            exit;
        }

        $slug = $this->input->post('slug', true);
        $settings = $this->input->post('settings');

        if (empty($slug) || empty($settings) || !is_array($settings)) {
            echo json_encode(['success' => false, 'error' => 'Invalid request data.']);
            exit;
        }

        // Sanitize: only allow ccx_security_* keys with values of '0' or '1'
        $clean_settings = [];
        foreach ($settings as $key => $value) {
            if (strpos($key, 'ccx_security_') === 0 && in_array($value, ['0', '1'])) {
                $clean_settings[$key] = $value;
            }
        }

        if (empty($clean_settings)) {
            echo json_encode(['success' => false, 'error' => 'No valid settings to save.']);
            exit;
        }

        $result = $this->ccx_security_model->apply_security_settings_to_tenant($slug, $clean_settings);

        if ($result) {
            ccx_security_log_event(
                'settings_changed',
                'Tenant security settings updated for ' . $slug . '. Keys: ' . implode(', ', array_keys($clean_settings)),
                'info'
            );
        }

        echo json_encode([
            'success'   => $result,
            'csrf_hash' => $this->security->get_csrf_hash(),
            'error'     => $result ? null : 'Failed to save settings to tenant database.',
        ]);
        exit;
    }
}
