<?php

defined('BASEPATH') or exit('No direct script access allowed');

// Module name
$lang['ccx_security']                    = 'CCX Security';
$lang['ccx_security_dashboard']          = 'Security Dashboard';
$lang['ccx_security_settings']           = 'Security Settings';
$lang['ccx_security_audit_log']          = 'Audit Log';
$lang['ccx_security_blocked_ips']        = 'Blocked IPs';

// Dashboard
$lang['ccx_security_score']              = 'Security Score';
$lang['ccx_security_score_excellent']    = 'Excellent';
$lang['ccx_security_score_good']         = 'Good';
$lang['ccx_security_score_fair']         = 'Fair';
$lang['ccx_security_score_poor']         = 'Poor';
$lang['ccx_security_enabled_features']   = 'Security Features';
$lang['ccx_security_recent_events']      = 'Recent Security Events';
$lang['ccx_security_total_blocked']      = 'Total Blocked IPs';
$lang['ccx_security_total_events']       = 'Total Events (24h)';
$lang['ccx_security_failed_logins']      = 'Failed Logins (24h)';

// Feature names (existing)
$lang['ccx_security_http_headers']       = 'HTTP Security Headers';
$lang['ccx_security_http_headers_desc']  = 'Inject security headers (X-Frame-Options, CSP, HSTS, etc.) to protect against clickjacking, MIME sniffing, and XSS attacks.';
$lang['ccx_security_devtools_block']     = 'DevTools Protection';
$lang['ccx_security_devtools_block_desc']= 'Block browser developer tools (F12, Ctrl+Shift+I, right-click inspect) to prevent code inspection.';
$lang['ccx_security_xss_protection']     = 'XSS Protection';
$lang['ccx_security_xss_protection_desc']= 'Sanitize user inputs and outputs to prevent Cross-Site Scripting attacks.';
$lang['ccx_security_csrf_hardening']     = 'CSRF Hardening';
$lang['ccx_security_csrf_hardening_desc']= 'Enforce Cross-Site Request Forgery tokens on all form submissions and AJAX requests.';
$lang['ccx_security_file_upload_scan']   = 'File Upload Security';
$lang['ccx_security_file_upload_scan_desc'] = 'Validate file MIME types, block executable uploads, and scan for embedded PHP code.';
$lang['ccx_security_session_protection'] = 'Session Security';
$lang['ccx_security_session_protection_desc'] = 'Protect sessions with fixation prevention, timeout enforcement, and concurrent session detection.';
$lang['ccx_security_brute_force']        = 'Brute Force Protection';
$lang['ccx_security_brute_force_desc']   = 'Lock out IP addresses after repeated failed login attempts.';
$lang['ccx_security_sql_monitor']        = 'SQL Injection Monitor';
$lang['ccx_security_sql_monitor_desc']   = 'Monitor and log suspicious SQL patterns in user inputs to detect injection attempts.';
$lang['ccx_security_audit_log_feature']  = 'Activity Audit Log';
$lang['ccx_security_audit_log_desc']     = 'Log all security-relevant events for compliance and forensic analysis.';
$lang['ccx_security_right_click']        = 'Right-Click Block';
$lang['ccx_security_right_click_desc']   = 'Disable the right-click context menu across the admin panel.';

// ─── NEW: Enterprise Feature Names ───
$lang['ccx_security_2fa']               = 'Two-Factor Auth (2FA)';
$lang['ccx_security_2fa_desc']          = 'Require a time-based one-time password (TOTP) from an authenticator app for admin login. SOC2/HIPAA/ISO 27001 compliant.';
$lang['ccx_security_ip_whitelist']      = 'IP Whitelist';
$lang['ccx_security_ip_whitelist_desc'] = 'Restrict admin access to authorized IP addresses and CIDR ranges. Block all other traffic at the network level.';
$lang['ccx_security_password_policy']   = 'Password Policy';
$lang['ccx_security_password_policy_desc'] = 'Enforce enterprise password complexity, expiry, and reuse prevention per PCI-DSS and NIST 800-63 standards.';
$lang['ccx_security_session_tracking']  = 'Session Tracking';
$lang['ccx_security_session_tracking_desc'] = 'Monitor active sessions, detect concurrent logins, and allow admins to terminate suspicious sessions remotely.';
$lang['ccx_security_compliance']        = 'Compliance';

// ─── 2FA Strings ───
$lang['ccx_security_2fa_setup']          = '2FA Setup';
$lang['ccx_security_2fa_verify']         = 'Verify 2FA';
$lang['ccx_security_2fa_setup_success']  = 'Two-Factor Authentication has been successfully activated!';
$lang['ccx_security_2fa_disabled']       = 'Two-Factor Authentication has been disabled.';
$lang['ccx_security_2fa_invalid_code']   = 'Invalid verification code. Please try again.';
$lang['ccx_security_2fa_required']       = 'Two-Factor Authentication is required for your account.';

// ─── IP Whitelist Strings ───
$lang['ccx_security_add_whitelist']      = 'Add to Whitelist';
$lang['ccx_security_whitelist_added']    = 'IP address added to whitelist successfully.';
$lang['ccx_security_whitelist_removed']  = 'IP address removed from whitelist.';
$lang['ccx_security_whitelist_duplicate']= 'This IP address is already whitelisted.';

// ─── Active Sessions Strings ───
$lang['ccx_security_active_sessions']    = 'Active Sessions';
$lang['ccx_security_session_killed']     = 'Session terminated successfully.';

// Settings labels (existing)
$lang['ccx_security_bf_max_attempts']    = 'Max Failed Attempts';
$lang['ccx_security_bf_lockout_minutes'] = 'Lockout Duration (minutes)';
$lang['ccx_security_bf_notify_admin']    = 'Notify Admin on Lockout';
$lang['ccx_security_session_timeout']    = 'Session Timeout (minutes)';
$lang['ccx_security_concurrent_sessions']= 'Allow Concurrent Sessions';
$lang['ccx_security_hsts']               = 'HSTS (Force HTTPS)';
$lang['ccx_security_csp_mode']           = 'CSP Mode';
$lang['ccx_security_csp_permissive']     = 'Permissive (Recommended)';
$lang['ccx_security_csp_strict']         = 'Strict';
$lang['ccx_security_csp_report_only']    = 'Report Only';
$lang['ccx_security_csp_disabled']       = 'Disabled';
$lang['ccx_security_x_frame']           = 'X-Frame-Options';
$lang['ccx_security_referrer_policy']    = 'Referrer Policy';
$lang['ccx_security_blocked_extensions'] = 'Blocked File Extensions';
$lang['ccx_security_max_upload_mb']      = 'Max Upload Size (MB)';
$lang['ccx_security_audit_retention']    = 'Audit Log Retention (days)';
$lang['ccx_security_inspect_message']    = 'DevTools Block Message';

// Audit log
$lang['ccx_security_event_type']         = 'Event Type';
$lang['ccx_security_event_description']  = 'Description';
$lang['ccx_security_event_ip']           = 'IP Address';
$lang['ccx_security_event_user']         = 'User';
$lang['ccx_security_event_severity']     = 'Severity';
$lang['ccx_security_event_timestamp']    = 'Timestamp';
$lang['ccx_security_event_uri']          = 'Request URI';

// Event types
$lang['ccx_security_evt_login_success']  = 'Login Success';
$lang['ccx_security_evt_login_failed']   = 'Login Failed';
$lang['ccx_security_evt_ip_blocked']     = 'IP Blocked';
$lang['ccx_security_evt_ip_unblocked']   = 'IP Unblocked';
$lang['ccx_security_evt_sql_injection']  = 'SQL Injection Attempt';
$lang['ccx_security_evt_xss_attempt']    = 'XSS Attempt';
$lang['ccx_security_evt_file_blocked']   = 'File Upload Blocked';
$lang['ccx_security_evt_settings_change']= 'Settings Changed';
$lang['ccx_security_evt_session_timeout']= 'Session Timeout';
$lang['ccx_security_evt_csrf_violation'] = 'CSRF Violation';
$lang['ccx_security_evt_2fa_setup']      = '2FA Setup';
$lang['ccx_security_evt_2fa_verified']   = '2FA Verified';
$lang['ccx_security_evt_2fa_failed']     = '2FA Failed';
$lang['ccx_security_evt_whitelist_denied']= 'IP Whitelist Denied';
$lang['ccx_security_evt_password_expired']= 'Password Expired';
$lang['ccx_security_evt_session_killed'] = 'Session Terminated';

// Blocked IPs
$lang['ccx_security_ip_address']         = 'IP Address';
$lang['ccx_security_block_reason']       = 'Reason';
$lang['ccx_security_blocked_until']      = 'Blocked Until';
$lang['ccx_security_block_permanent']    = 'Permanent';
$lang['ccx_security_unblock']            = 'Unblock';
$lang['ccx_security_block_ip']           = 'Block IP';
$lang['ccx_security_add_block']          = 'Add IP Block';

// Actions
$lang['ccx_security_save_settings']      = 'Save Settings';
$lang['ccx_security_clear_log']          = 'Clear Audit Log';
$lang['ccx_security_run_scan']           = 'Run Security Scan';
$lang['ccx_security_export']             = 'Export';
$lang['ccx_security_export_csv']         = 'Export CSV';
$lang['ccx_security_export_report']      = 'Export Report';

// Messages
$lang['ccx_security_settings_saved']     = 'Security settings saved successfully.';
$lang['ccx_security_ip_blocked_success'] = 'IP address blocked successfully.';
$lang['ccx_security_ip_unblocked_success'] = 'IP address unblocked successfully.';
$lang['ccx_security_log_cleared']        = 'Audit log cleared successfully.';
$lang['ccx_security_master_only']        = 'This feature is only available on the master account.';

// ─── Compliance Strings ───
$lang['ccx_security_compliance_title']   = 'Security Compliance Checklist';
$lang['ccx_security_compliance_owasp']   = 'OWASP Top 10';
$lang['ccx_security_compliance_soc2']    = 'SOC 2';
$lang['ccx_security_compliance_hipaa']   = 'HIPAA';
$lang['ccx_security_compliance_passed']  = 'Passed';
$lang['ccx_security_compliance_failed']  = 'Not Met';

// ─── Tenants Security (Master Only) ───
$lang['ccx_security_tenants_security']       = 'Tenants Security';
$lang['ccx_security_tenant_name']            = 'Tenant';
$lang['ccx_security_tenant_status']          = 'Status';
$lang['ccx_security_features_enabled']       = 'Features Enabled';
$lang['ccx_security_tenant_score']           = 'Security Score';
$lang['ccx_security_2fa_adoption']           = '2FA Adoption';
$lang['ccx_security_bulk_apply']             = 'Bulk Apply Security';
$lang['ccx_security_bulk_apply_confirm']     = 'This will apply the master account security settings to all selected tenants. Existing tenant-specific settings will be overwritten. Continue?';
$lang['ccx_security_bulk_apply_success']     = 'Security settings applied successfully to %d tenant(s).';
$lang['ccx_security_bulk_apply_failed']      = 'Failed to apply settings to some tenants. Check the error log for details.';
$lang['ccx_security_view_sessions']          = 'Sessions';
$lang['ccx_security_no_tenants']             = 'No tenants found.';
$lang['ccx_security_tenant_sessions']        = 'Tenant Sessions';
$lang['ccx_security_kill_tenant_session']    = 'Terminate Session';
$lang['ccx_security_tenant_overview']        = 'Tenant Security Overview';
$lang['ccx_security_loading']                = 'Loading...';
$lang['ccx_security_no_sessions']            = 'No active sessions';
$lang['ccx_security_tenant_detail']          = 'Security Details';
$lang['ccx_security_total_tenants']          = 'Total Tenants';
$lang['ccx_security_tenants_secured']        = 'Tenants Secured';
$lang['ccx_security_total_tenant_sessions']  = 'Total Sessions';
$lang['ccx_security_avg_score']              = 'Avg. Score';
