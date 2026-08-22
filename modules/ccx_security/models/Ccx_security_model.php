<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Ccx_security_model extends App_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    // ─── Audit Log ───

    /**
     * Get paginated audit log entries
     *
     * @param array $filters  Optional filters: event_type, severity, date_from, date_to, limit, offset
     * @return array ['data' => rows, 'total' => count]
     */
    public function get_audit_log($filters = [])
    {
        // ─── Count query (clean, separate) ───
        $this->db->from(db_prefix() . 'ccx_security_audit_log al');
        $this->_apply_audit_log_filters($filters);
        $total = $this->db->count_all_results();

        // ─── Data query (clean, separate) ───
        $this->db->select('al.*, CONCAT(s.firstname, " ", s.lastname) as staff_name');
        $this->db->from(db_prefix() . 'ccx_security_audit_log al');
        $this->db->join(db_prefix() . 'staff s', 's.staffid = al.staff_id', 'left');
        $this->_apply_audit_log_filters($filters);

        $limit = isset($filters['limit']) ? (int)$filters['limit'] : 50;
        $offset = isset($filters['offset']) ? (int)$filters['offset'] : 0;

        $this->db->order_by('al.created_at', 'DESC');
        $this->db->limit($limit, $offset);

        $data = $this->db->get()->result();

        return ['data' => $data, 'total' => $total];
    }

    /**
     * Get audit log for export (no limit)
     *
     * @param array $filters
     * @return array
     */
    public function get_audit_log_for_export($filters = [])
    {
        $this->db->select('al.*, CONCAT(s.firstname, " ", s.lastname) as staff_name');
        $this->db->from(db_prefix() . 'ccx_security_audit_log al');
        $this->db->join(db_prefix() . 'staff s', 's.staffid = al.staff_id', 'left');
        $this->_apply_audit_log_filters($filters);
        $this->db->order_by('al.created_at', 'DESC');

        return $this->db->get()->result();
    }

    /**
     * Apply common audit log filters to the current query builder state
     *
     * @param array $filters
     */
    private function _apply_audit_log_filters($filters)
    {
        if (!empty($filters['event_type'])) {
            $this->db->where('al.event_type', $filters['event_type']);
        }
        if (!empty($filters['severity'])) {
            $this->db->where('al.severity', $filters['severity']);
        }
        if (!empty($filters['date_from'])) {
            $this->db->where('al.created_at >=', $filters['date_from'] . ' 00:00:00');
        }
        if (!empty($filters['date_to'])) {
            $this->db->where('al.created_at <=', $filters['date_to'] . ' 23:59:59');
        }
        if (!empty($filters['search'])) {
            $this->db->group_start();
            $this->db->like('al.description', $filters['search']);
            $this->db->or_like('al.ip_address', $filters['search']);
            $this->db->or_like('al.request_uri', $filters['search']);
            $this->db->group_end();
        }
    }

    /**
     * Clear old audit log entries
     *
     * @param int $retention_days  Number of days to keep
     * @return int Number of rows deleted
     */
    public function clear_audit_log($retention_days = 0)
    {
        if ($retention_days > 0) {
            $cutoff = date('Y-m-d H:i:s', strtotime("-{$retention_days} days"));
            $this->db->where('created_at <', $cutoff);
            $this->db->delete(db_prefix() . 'ccx_security_audit_log');
            return $this->db->affected_rows();
        }

        // Clear all — use truncate (CI3 blocks delete without WHERE)
        $this->db->truncate(db_prefix() . 'ccx_security_audit_log');
        return $this->db->affected_rows();
    }

    // ─── Login Attempts / Brute Force ───

    /**
     * Record a login attempt
     */
    public function record_login_attempt($ip, $email = '', $success = false)
    {
        $this->db->insert(db_prefix() . 'ccx_security_login_attempts', [
            'ip_address' => $ip,
            'email'      => $email,
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 500) : '',
            'success'    => $success ? 1 : 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Count recent failed login attempts for an IP
     */
    public function count_failed_attempts($ip, $minutes = 15)
    {
        $since = date('Y-m-d H:i:s', strtotime("-{$minutes} minutes"));
        return $this->db->where('ip_address', $ip)
            ->where('success', 0)
            ->where('created_at >=', $since)
            ->count_all_results(db_prefix() . 'ccx_security_login_attempts');
    }

    /**
     * Check if an IP is currently blocked
     */
    public function is_ip_blocked($ip)
    {
        $row = $this->db->where('ip_address', $ip)
            ->group_start()
                ->where('is_permanent', 1)
                ->or_where('blocked_until >', date('Y-m-d H:i:s'))
            ->group_end()
            ->get(db_prefix() . 'ccx_security_blocked_ips')
            ->row();

        return $row;
    }

    /**
     * Block an IP address
     */
    public function block_ip($ip, $reason = 'Brute force protection', $minutes = 15)
    {
        $data = [
            'ip_address'    => $ip,
            'reason'        => $reason,
            'is_permanent'  => ($minutes <= 0) ? 1 : 0,
            'blocked_until' => ($minutes > 0) ? date('Y-m-d H:i:s', strtotime("+{$minutes} minutes")) : null,
            'created_at'    => date('Y-m-d H:i:s'),
        ];

        $existing = $this->db->where('ip_address', $ip)->get(db_prefix() . 'ccx_security_blocked_ips')->row();
        if ($existing) {
            $this->db->where('id', $existing->id);
            return $this->db->update(db_prefix() . 'ccx_security_blocked_ips', $data);
        }

        return $this->db->insert(db_prefix() . 'ccx_security_blocked_ips', $data);
    }

    /**
     * Unblock an IP by record ID
     */
    public function unblock_ip($id)
    {
        $this->db->where('id', $id);
        return $this->db->delete(db_prefix() . 'ccx_security_blocked_ips');
    }

    /**
     * Get all blocked IPs
     */
    public function get_blocked_ips()
    {
        return $this->db->order_by('created_at', 'DESC')
            ->get(db_prefix() . 'ccx_security_blocked_ips')
            ->result();
    }

    // ═══════════════════════════════════════════════════════════════
    // ─── TWO-FACTOR AUTHENTICATION ───
    // ═══════════════════════════════════════════════════════════════

    /**
     * Save a 2FA secret for a staff member
     */
    public function save_2fa_secret($staff_id, $secret, $recovery_codes = [])
    {
        $data = [
            'staff_id'       => $staff_id,
            'secret'         => $secret,
            'recovery_codes' => json_encode($recovery_codes),
            'is_active'      => 1,
            'verified_at'    => null,
            'created_at'     => date('Y-m-d H:i:s'),
        ];

        // Remove existing entry
        $this->db->where('staff_id', $staff_id)->delete(db_prefix() . 'ccx_security_2fa_secrets');

        return $this->db->insert(db_prefix() . 'ccx_security_2fa_secrets', $data);
    }

    /**
     * Get 2FA secret for a staff member
     */
    public function get_2fa_secret($staff_id)
    {
        return $this->db->where('staff_id', $staff_id)
            ->where('is_active', 1)
            ->get(db_prefix() . 'ccx_security_2fa_secrets')
            ->row();
    }

    /**
     * Mark 2FA as verified (first successful verification after setup)
     */
    public function mark_2fa_verified($staff_id)
    {
        $this->db->where('staff_id', $staff_id);
        return $this->db->update(db_prefix() . 'ccx_security_2fa_secrets', [
            'verified_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Verify a TOTP code for a staff member
     */
    public function verify_2fa_code($staff_id, $code)
    {
        $record = $this->get_2fa_secret($staff_id);
        if (!$record) {
            return false;
        }

        return ccx_security_verify_totp($record->secret, $code);
    }

    /**
     * Use a recovery code (one-time use)
     */
    public function use_recovery_code($staff_id, $code)
    {
        $record = $this->get_2fa_secret($staff_id);
        if (!$record) {
            return false;
        }

        $codes = json_decode($record->recovery_codes, true);
        if (!is_array($codes)) {
            return false;
        }

        $code = strtoupper(trim($code));
        $index = array_search($code, $codes);

        if ($index === false) {
            return false;
        }

        // Remove used code
        unset($codes[$index]);
        $codes = array_values($codes);

        $this->db->where('staff_id', $staff_id);
        $this->db->update(db_prefix() . 'ccx_security_2fa_secrets', [
            'recovery_codes' => json_encode($codes),
        ]);

        return true;
    }

    /**
     * Disable 2FA for a staff member
     */
    public function disable_2fa($staff_id)
    {
        $this->db->where('staff_id', $staff_id);
        return $this->db->delete(db_prefix() . 'ccx_security_2fa_secrets');
    }

    /**
     * Check if a staff member has 2FA set up and verified
     */
    public function has_2fa_setup($staff_id)
    {
        $record = $this->get_2fa_secret($staff_id);
        return $record && $record->verified_at !== null;
    }


    // ═══════════════════════════════════════════════════════════════
    // ─── IP WHITELIST ───
    // ═══════════════════════════════════════════════════════════════

    /**
     * Get all whitelisted IPs
     */
    public function get_ip_whitelist()
    {
        return $this->db->select('w.*, CONCAT(s.firstname, " ", s.lastname) as added_by_name')
            ->from(db_prefix() . 'ccx_security_ip_whitelist w')
            ->join(db_prefix() . 'staff s', 's.staffid = w.added_by', 'left')
            ->order_by('w.created_at', 'DESC')
            ->get()
            ->result();
    }

    /**
     * Add an IP to the whitelist
     */
    public function add_ip_whitelist($ip, $label = '', $is_cidr = false, $added_by = null)
    {
        // Check for duplicate
        $exists = $this->db->where('ip_address', $ip)
            ->get(db_prefix() . 'ccx_security_ip_whitelist')
            ->row();

        if ($exists) {
            return false;
        }

        return $this->db->insert(db_prefix() . 'ccx_security_ip_whitelist', [
            'ip_address' => $ip,
            'label'      => $label,
            'is_cidr'    => $is_cidr ? 1 : 0,
            'added_by'   => $added_by,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Remove an IP from the whitelist
     */
    public function remove_ip_whitelist($id)
    {
        $this->db->where('id', $id);
        return $this->db->delete(db_prefix() . 'ccx_security_ip_whitelist');
    }


    // ═══════════════════════════════════════════════════════════════
    // ─── PASSWORD HISTORY ───
    // ═══════════════════════════════════════════════════════════════

    /**
     * Add a password hash to the history
     */
    public function add_password_history($staff_id, $password_hash)
    {
        $this->db->insert(db_prefix() . 'ccx_security_password_history', [
            'staff_id'      => $staff_id,
            'password_hash' => $password_hash,
            'created_at'    => date('Y-m-d H:i:s'),
        ]);

        // Trim old entries beyond the configured limit
        $keep = (int) ccx_security_get_setting('pw_history_count', '5');
        $entries = $this->db->where('staff_id', $staff_id)
            ->order_by('created_at', 'DESC')
            ->get(db_prefix() . 'ccx_security_password_history')
            ->result();

        if (count($entries) > $keep) {
            $to_delete = array_slice($entries, $keep);
            foreach ($to_delete as $old) {
                $this->db->where('id', $old->id);
                $this->db->delete(db_prefix() . 'ccx_security_password_history');
            }
        }
    }

    /**
     * Check if a password was used before (checks against hashes)
     */
    public function is_password_reused($staff_id, $new_password)
    {
        $entries = $this->db->where('staff_id', $staff_id)
            ->order_by('created_at', 'DESC')
            ->get(db_prefix() . 'ccx_security_password_history')
            ->result();

        foreach ($entries as $entry) {
            if (password_verify($new_password, $entry->password_hash)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get when the last password change was for a staff member
     */
    public function get_last_password_change($staff_id)
    {
        $row = $this->db->where('staff_id', $staff_id)
            ->order_by('created_at', 'DESC')
            ->limit(1)
            ->get(db_prefix() . 'ccx_security_password_history')
            ->row();

        return $row ? $row->created_at : null;
    }

    /**
     * Check if a staff member's password has expired
     */
    public function is_password_expired($staff_id)
    {
        $expiry_days = (int) ccx_security_get_setting('pw_expiry_days', '90');
        if ($expiry_days <= 0) {
            return false;
        }

        $last_change = $this->get_last_password_change($staff_id);
        if (!$last_change) {
            // No history = consider it not expired (grace period)
            return false;
        }

        $expiry_date = date('Y-m-d H:i:s', strtotime($last_change . " +{$expiry_days} days"));
        return date('Y-m-d H:i:s') > $expiry_date;
    }


    // ═══════════════════════════════════════════════════════════════
    // ─── ACTIVE SESSIONS ───
    // ═══════════════════════════════════════════════════════════════

    /**
     * Register / update an active session.
     * Uses staff_id + IP + user_agent hash as the "browser fingerprint" to avoid
     * duplicates caused by CI regenerating session_id every few minutes.
     */
    public function register_session($staff_id, $session_id, $ip = '', $user_agent = '')
    {
        $device = ccx_security_parse_device($user_agent);
        $ua_hash = md5($user_agent); // fingerprint for same browser

        // Look for existing session with same staff + IP + browser
        $existing = $this->db->where('staff_id', $staff_id)
            ->where('ip_address', $ip)
            ->where('ua_hash', $ua_hash)
            ->get(db_prefix() . 'ccx_security_active_sessions')
            ->row();

        if ($existing) {
            $this->db->where('id', $existing->id);
            return $this->db->update(db_prefix() . 'ccx_security_active_sessions', [
                'session_id'    => $session_id, // keep session_id current for kill
                'last_activity' => date('Y-m-d H:i:s'),
            ]);
        }

        // New session — look up geolocation
        $geo = $this->lookup_ip_geolocation($ip);

        return $this->db->insert(db_prefix() . 'ccx_security_active_sessions', [
            'staff_id'      => $staff_id,
            'session_id'    => $session_id,
            'ip_address'    => $ip,
            'user_agent'    => substr($user_agent, 0, 500),
            'ua_hash'       => $ua_hash,
            'device_info'   => $device,
            'geo_city'      => $geo['city'] ?? '',
            'geo_region'    => $geo['region'] ?? '',
            'geo_country'   => $geo['country'] ?? '',
            'geo_isp'       => $geo['isp'] ?? '',
            'last_activity' => date('Y-m-d H:i:s'),
            'created_at'    => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Look up geolocation data for an IP address using ip-api.com (free, no key needed)
     * Returns array with city, region, country, isp
     */
    public function lookup_ip_geolocation($ip)
    {
        $default = ['city' => '', 'region' => '', 'country' => '', 'isp' => ''];

        // Skip private/local IPs
        if (empty($ip) || $ip === '127.0.0.1' || $ip === '::1' || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            $default['city'] = 'Local Network';
            return $default;
        }

        try {
            $url = 'http://ip-api.com/json/' . urlencode($ip) . '?fields=status,city,regionName,country,isp';
            $context = stream_context_create(['http' => ['timeout' => 3]]);
            $response = @file_get_contents($url, false, $context);

            if ($response) {
                $data = json_decode($response, true);
                if (isset($data['status']) && $data['status'] === 'success') {
                    return [
                        'city'    => $data['city'] ?? '',
                        'region'  => $data['regionName'] ?? '',
                        'country' => $data['country'] ?? '',
                        'isp'     => $data['isp'] ?? '',
                    ];
                }
            }
        } catch (\Throwable $e) {
            log_message('error', 'CCX Security — Geo lookup failed for ' . $ip . ': ' . $e->getMessage());
        }

        return $default;
    }

    /**
     * Get all active sessions for a specific staff member
     */
    public function get_active_sessions($staff_id = null)
    {
        $this->db->select('s.*, CONCAT(st.firstname, " ", st.lastname) as staff_name, st.email as staff_email');
        $this->db->from(db_prefix() . 'ccx_security_active_sessions s');
        $this->db->join(db_prefix() . 'staff st', 'st.staffid = s.staff_id', 'left');

        if ($staff_id) {
            $this->db->where('s.staff_id', $staff_id);
        }

        $this->db->order_by('s.last_activity', 'DESC');
        return $this->db->get()->result();
    }

    /**
     * Terminate a session by its session_id string
     * Destroys both the real CI session AND our tracking record
     */
    public function terminate_session($session_id)
    {
        // 1. Destroy the REAL CI session from the database
        $this->_destroy_ci_session($session_id);

        // 2. Remove our tracking record
        $this->db->where('session_id', $session_id);
        return $this->db->delete(db_prefix() . 'ccx_security_active_sessions');
    }

    /**
     * Terminate a session by our tracking record ID
     * Destroys both the real CI session AND our tracking record
     */
    public function terminate_session_by_id($id)
    {
        // First, get the session_id from our tracking table
        $record = $this->db->where('id', $id)
            ->get(db_prefix() . 'ccx_security_active_sessions')
            ->row();

        if ($record && !empty($record->session_id)) {
            // 1. Destroy the REAL CI session from the database
            $this->_destroy_ci_session($record->session_id);
        }

        // 2. Remove our tracking record
        $this->db->where('id', $id);
        return $this->db->delete(db_prefix() . 'ccx_security_active_sessions');
    }

    /**
     * Destroy a real CI session from the session storage.
     * Perfex CRM uses database sessions (driver=database, table=sessions/tblsessions).
     * This forces the target user to be logged out on their next request.
     *
     * @param string $session_id The PHP/CI session ID to destroy
     */
    private function _destroy_ci_session($session_id)
    {
        if (empty($session_id)) {
            return;
        }

        // CI3 database session stores in: db_prefix() + sess_save_path
        // Perfex default: tblsessions
        $CI = &get_instance();

        // Try the configured session table
        $sess_table = db_prefix() . 'sessions';

        if ($CI->db->table_exists($sess_table)) {
            $CI->db->where('id', $session_id);
            $CI->db->delete($sess_table);
        }

        // Also try without prefix (some setups use 'sessions' directly)
        if ($CI->db->table_exists('sessions') && 'sessions' !== $sess_table) {
            $CI->db->where('id', $session_id);
            $CI->db->delete('sessions');
        }
    }

    /**
     * Enforce session limit for a staff member (remove oldest sessions over limit)
     */
    public function enforce_session_limit($staff_id, $max_sessions = 3)
    {
        $sessions = $this->db->where('staff_id', $staff_id)
            ->order_by('last_activity', 'DESC')
            ->get(db_prefix() . 'ccx_security_active_sessions')
            ->result();

        if (count($sessions) > $max_sessions) {
            $to_remove = array_slice($sessions, $max_sessions);
            foreach ($to_remove as $old) {
                // Destroy the real CI session first
                $this->_destroy_ci_session($old->session_id);
                // Then remove tracking record
                $this->db->where('id', $old->id);
                $this->db->delete(db_prefix() . 'ccx_security_active_sessions');
            }
        }
    }

    /**
     * Clean up stale sessions (inactive for more than 24 hours)
     */
    public function cleanup_expired_sessions()
    {
        $cutoff = date('Y-m-d H:i:s', strtotime('-24 hours'));
        $this->db->where('last_activity <', $cutoff);
        $this->db->delete(db_prefix() . 'ccx_security_active_sessions');
    }

    /**
     * Remove session entry for current session on logout
     */
    public function remove_current_session()
    {
        $session_id = session_id();
        if ($session_id) {
            $this->db->where('session_id', $session_id);
            $this->db->delete(db_prefix() . 'ccx_security_active_sessions');
        }
    }


    // ─── Dashboard Stats ───

    /**
     * Get dashboard statistics
     */
    public function get_security_stats()
    {
        $last_24h = date('Y-m-d H:i:s', strtotime('-24 hours'));
        $last_7d = date('Y-m-d H:i:s', strtotime('-7 days'));

        // Total blocked IPs (active)
        $blocked_count = $this->db
            ->group_start()
                ->where('is_permanent', 1)
                ->or_where('blocked_until >', date('Y-m-d H:i:s'))
            ->group_end()
            ->count_all_results(db_prefix() . 'ccx_security_blocked_ips');

        // Events last 24h
        $events_24h = $this->db
            ->where('created_at >=', $last_24h)
            ->count_all_results(db_prefix() . 'ccx_security_audit_log');

        // Failed logins last 24h
        $failed_24h = $this->db
            ->where('success', 0)
            ->where('created_at >=', $last_24h)
            ->count_all_results(db_prefix() . 'ccx_security_login_attempts');

        // Critical events last 7 days
        $critical_7d = $this->db
            ->where('severity', 'critical')
            ->where('created_at >=', $last_7d)
            ->count_all_results(db_prefix() . 'ccx_security_audit_log');

        // Active sessions count
        $active_sessions = 0;
        if ($this->db->table_exists(db_prefix() . 'ccx_security_active_sessions')) {
            $active_sessions = $this->db
                ->count_all_results(db_prefix() . 'ccx_security_active_sessions');
        }

        // 2FA adoption rate
        $total_staff = $this->db->count_all_results(db_prefix() . 'staff');
        $staff_with_2fa = 0;
        if ($this->db->table_exists(db_prefix() . 'ccx_security_2fa_secrets')) {
            $staff_with_2fa = $this->db
                ->where('is_active', 1)
                ->where('verified_at IS NOT NULL', null, false)
                ->count_all_results(db_prefix() . 'ccx_security_2fa_secrets');
        }

        // Whitelisted IPs count
        $whitelisted_ips = 0;
        if ($this->db->table_exists(db_prefix() . 'ccx_security_ip_whitelist')) {
            $whitelisted_ips = $this->db
                ->count_all_results(db_prefix() . 'ccx_security_ip_whitelist');
        }

        // Recent 10 events
        $recent_events = $this->db
            ->select('al.*, CONCAT(s.firstname, " ", s.lastname) as staff_name')
            ->from(db_prefix() . 'ccx_security_audit_log al')
            ->join(db_prefix() . 'staff s', 's.staffid = al.staff_id', 'left')
            ->order_by('al.created_at', 'DESC')
            ->limit(10)
            ->get()
            ->result();

        return [
            'blocked_ips'      => $blocked_count,
            'events_24h'       => $events_24h,
            'failed_logins'    => $failed_24h,
            'critical_7d'      => $critical_7d,
            'active_sessions'  => $active_sessions,
            'total_staff'      => $total_staff,
            'staff_with_2fa'   => $staff_with_2fa,
            'whitelisted_ips'  => $whitelisted_ips,
            'recent_events'    => $recent_events,
        ];
    }

    /**
     * Cleanup old login attempts and expired blocks
     */
    public function cleanup_old_records($days = 30)
    {
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        // Purge old login attempts
        $this->db->where('created_at <', $cutoff);
        $this->db->delete(db_prefix() . 'ccx_security_login_attempts');

        // Purge expired (non-permanent) IP blocks
        $this->db->where('is_permanent', 0);
        $this->db->where('blocked_until <', date('Y-m-d H:i:s'));
        $this->db->delete(db_prefix() . 'ccx_security_blocked_ips');
    }


    // ═══════════════════════════════════════════════════════════════
    // ─── TENANTS SECURITY (Master Only — Cross-DB Queries) ───
    // ═══════════════════════════════════════════════════════════════

    /**
     * Get all tenants with their security summary data.
     * Loads the SaaS model to retrieve tenant list, then queries each tenant's
     * database for security posture data.
     *
     * @return array Array of tenant objects with security_summary attached
     */
    public function get_all_tenants_with_security()
    {
        // Guard: only available on master
        if (function_exists('perfex_saas_is_tenant') && perfex_saas_is_tenant()) {
            return [];
        }

        $CI = &get_instance();
        $CI->load->model('perfex_saas/perfex_saas_model');

        // skip_parsing = true so we control parsing ourselves
        $tenants = $CI->perfex_saas_model->companies('', true);

        if (empty($tenants)) {
            return [];
        }

        $results = [];
        foreach ($tenants as $tenant) {
            $t = (object) $tenant;

            // Parse the company once (decrypts DSN, parses metadata)
            try {
                $t = $CI->perfex_saas_model->parse_company($t);
            } catch (\Throwable $e) {
                // If parse fails, still add the tenant with an error
                $t->security_summary = (object) [
                    'score' => 0, 'features_enabled' => 0, 'features_total' => 14,
                    'active_sessions' => 0, 'staff_with_2fa' => 0, 'total_staff' => 0,
                    'error' => 'Parse error: ' . $e->getMessage(),
                ];
                $results[] = $t;
                continue;
            }

            $summary = [
                'score'            => 0,
                'features_enabled' => 0,
                'features_total'   => 14,
                'active_sessions'  => 0,
                'staff_with_2fa'   => 0,
                'total_staff'      => 0,
                'error'            => null,
            ];

            // Only query active tenants with a valid DSN
            if (!empty($t->dsn) && isset($t->status) && $t->status === 'active') {
                try {
                    $dsn_array = perfex_saas_parse_dsn($t->dsn);
                    $db_prefix = perfex_saas_tenant_db_prefix($t->slug);

                    $summary = $this->_query_tenant_security_summary($dsn_array, $db_prefix);
                } catch (\Throwable $e) {
                    $summary['error'] = $e->getMessage();
                    log_message('error', 'CCX Security — Tenant security query failed for ' . ($t->name ?? $t->slug) . ': ' . $e->getMessage());
                }
            } elseif (empty($t->dsn)) {
                $summary['error'] = 'No DSN configured';
            }

            $t->security_summary = (object) $summary;
            $results[] = $t;
        }

        return $results;
    }

    /**
     * Query a tenant's database for security summary data
     *
     * @param array  $dsn_array  Parsed DSN credentials
     * @param string $db_prefix  Tenant's table prefix
     * @return array Security summary data
     */
    private function _query_tenant_security_summary($dsn_array, $db_prefix)
    {
        $summary = [
            'score'            => 0,
            'features_enabled' => 0,
            'features_total'   => 14,
            'active_sessions'  => 0,
            'staff_with_2fa'   => 0,
            'total_staff'      => 0,
            'error'            => null,
        ];

        // Read all ccx_security_* options from the tenant's options table
        $options_table = $db_prefix . 'options';
        $query = "SELECT `name`, `value` FROM `{$options_table}` WHERE `name` LIKE 'ccx_security_%'";
        $rows = perfex_saas_raw_query($query, $dsn_array, true, false);

        $opts = [];
        if (!empty($rows)) {
            foreach ($rows as $row) {
                $opts[$row->name] = $row->value;
            }
        }

        // Calculate score (same logic as helper)
        $features = [
            'http_headers_enabled'       => 10,
            'devtools_block_enabled'     => 3,
            'xss_protection_enabled'     => 10,
            'csrf_hardening_enabled'     => 10,
            'file_upload_scan_enabled'   => 8,
            'session_protection_enabled' => 7,
            'brute_force_enabled'        => 10,
            'sql_monitor_enabled'        => 7,
            'audit_log_enabled'          => 3,
            'right_click_block'          => 2,
            '2fa_enabled'                => 15,
            'ip_whitelist_enabled'       => 8,
            'password_policy_enabled'    => 10,
            'session_tracking_enabled'   => 7,
        ];

        $global_enabled = ($opts['ccx_security_enabled'] ?? '0') === '1';
        $score = 0;
        $enabled_count = 0;

        if ($global_enabled) {
            foreach ($features as $key => $weight) {
                $opt_key = 'ccx_security_' . $key;
                if (($opts[$opt_key] ?? '0') === '1') {
                    $score += $weight;
                    $enabled_count++;
                }
            }
        }

        $summary['score'] = min($score, 100);
        $summary['features_enabled'] = $enabled_count;

        // Active sessions count
        $sessions_table = $db_prefix . 'ccx_security_active_sessions';
        try {
            $sess_q = "SELECT COUNT(*) as cnt FROM `{$sessions_table}`";
            $sess_result = perfex_saas_raw_query($sess_q, $dsn_array, true, false);
            if (!empty($sess_result)) {
                $summary['active_sessions'] = (int) ($sess_result[0]->cnt ?? 0);
            }
        } catch (\Throwable $e) {
            // Table may not exist yet for this tenant
        }

        // Staff count and 2FA adoption
        $staff_table = $db_prefix . 'staff';
        try {
            $staff_q = "SELECT COUNT(*) as cnt FROM `{$staff_table}` WHERE `active` = 1";
            $staff_result = perfex_saas_raw_query($staff_q, $dsn_array, true, false);
            if (!empty($staff_result)) {
                $summary['total_staff'] = (int) ($staff_result[0]->cnt ?? 0);
            }
        } catch (\Throwable $e) {
            // Ignore
        }

        $twofa_table = $db_prefix . 'ccx_security_2fa_secrets';
        try {
            $twofa_q = "SELECT COUNT(*) as cnt FROM `{$twofa_table}` WHERE `is_active` = 1 AND `verified_at` IS NOT NULL";
            $twofa_result = perfex_saas_raw_query($twofa_q, $dsn_array, true, false);
            if (!empty($twofa_result)) {
                $summary['staff_with_2fa'] = (int) ($twofa_result[0]->cnt ?? 0);
            }
        } catch (\Throwable $e) {
            // Table may not exist
        }

        return $summary;
    }

    /**
     * Get active sessions for a specific tenant by connecting to their database
     *
     * @param string $tenant_slug  The tenant slug
     * @return array|null  Array of session objects or null on error
     */
    public function get_tenant_active_sessions_cross_db($tenant_slug)
    {
        if (function_exists('perfex_saas_is_tenant') && perfex_saas_is_tenant()) {
            return null;
        }

        $CI = &get_instance();
        $CI->load->model('perfex_saas/perfex_saas_model');

        $company = $CI->perfex_saas_model->get_company_by_slug($tenant_slug);
        if (!$company || empty($company->dsn)) {
            return null;
        }

        try {
            $dsn_array = perfex_saas_parse_dsn($company->dsn);
            $db_prefix = perfex_saas_tenant_db_prefix($tenant_slug);

            $sessions_table = $db_prefix . 'ccx_security_active_sessions';
            $staff_table = $db_prefix . 'staff';

            $query = "SELECT s.*, CONCAT(st.firstname, ' ', st.lastname) as staff_name, st.email as staff_email
                      FROM `{$sessions_table}` s
                      LEFT JOIN `{$staff_table}` st ON st.staffid = s.staff_id
                      ORDER BY s.last_activity DESC";

            $sessions = perfex_saas_raw_query($query, $dsn_array, true, false);
            return $sessions ?: [];
        } catch (\Throwable $e) {
            log_message('error', 'CCX Security — Failed to get tenant sessions for ' . $tenant_slug . ': ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get security options for a specific tenant
     *
     * @param string $tenant_slug
     * @return array|null
     */
    public function get_tenant_security_options($tenant_slug)
    {
        if (function_exists('perfex_saas_is_tenant') && perfex_saas_is_tenant()) {
            return null;
        }

        $CI = &get_instance();
        $CI->load->model('perfex_saas/perfex_saas_model');

        $company = $CI->perfex_saas_model->get_company_by_slug($tenant_slug);
        if (!$company || empty($company->dsn)) {
            return null;
        }

        try {
            $dsn_array = perfex_saas_parse_dsn($company->dsn);
            $db_prefix = perfex_saas_tenant_db_prefix($tenant_slug);

            $options_table = $db_prefix . 'options';
            $query = "SELECT `name`, `value` FROM `{$options_table}` WHERE `name` LIKE 'ccx_security_%'";
            $rows = perfex_saas_raw_query($query, $dsn_array, true, false);

            $opts = [];
            if (!empty($rows)) {
                foreach ($rows as $row) {
                    $opts[$row->name] = $row->value;
                }
            }
            return $opts;
        } catch (\Throwable $e) {
            log_message('error', 'CCX Security — Failed to get tenant options for ' . $tenant_slug . ': ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Apply security settings from master to a specific tenant's database
     *
     * @param string $tenant_slug
     * @param array  $settings  Key-value pairs of ccx_security_* options
     * @return bool
     */
    public function apply_security_settings_to_tenant($tenant_slug, $settings)
    {
        if (function_exists('perfex_saas_is_tenant') && perfex_saas_is_tenant()) {
            return false;
        }

        $CI = &get_instance();
        $CI->load->model('perfex_saas/perfex_saas_model');

        $company = $CI->perfex_saas_model->get_company_by_slug($tenant_slug);
        if (!$company || empty($company->dsn)) {
            return false;
        }

        try {
            $dsn_array = perfex_saas_parse_dsn($company->dsn);
            $db_prefix = perfex_saas_tenant_db_prefix($tenant_slug);
            $options_table = $db_prefix . 'options';

            $queries = [];
            foreach ($settings as $key => $value) {
                $escaped_key = addslashes($key);
                $escaped_val = addslashes($value);

                // The tenant `options` table has only a NON-unique index on `name`
                // (id is the primary key), so `ON DUPLICATE KEY UPDATE` never fires and
                // every re-apply would silently INSERT a duplicate row, leaving get_option()
                // reading the stale value. Delete-then-insert guarantees exactly one row
                // with the current value and self-heals any duplicates from the old code.
                $queries[] = "DELETE FROM `{$options_table}` WHERE `name` = '{$escaped_key}'";
                $queries[] = "INSERT INTO `{$options_table}` (`name`, `value`) VALUES ('{$escaped_key}', '{$escaped_val}')";
            }

            if (!empty($queries)) {
                perfex_saas_raw_query($queries, $dsn_array, false, true, null, false, false);
            }

            return true;
        } catch (\Throwable $e) {
            log_message('error', 'CCX Security — Failed to apply settings to tenant ' . $tenant_slug . ': ' . $e->getMessage());
            return false;
        }
    }
}
