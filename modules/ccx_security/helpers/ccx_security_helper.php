<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Get a CCX Security setting value
 *
 * @param string $key     Setting key (without ccx_security_ prefix)
 * @param mixed  $default Default value if not found
 * @return mixed
 */
function ccx_security_get_setting($key, $default = '')
{
    $full_key = 'ccx_security_' . $key;
    $value = get_option($full_key);
    return ($value !== '' && $value !== null && $value !== false) ? $value : $default;
}

/**
 * Check if a security feature is enabled
 *
 * @param string $feature Feature key (e.g., 'http_headers_enabled')
 * @return bool
 */
function ccx_security_is_enabled($feature)
{
    // Global kill switch
    if (get_option('ccx_security_enabled') !== '1') {
        return false;
    }
    return ccx_security_get_setting($feature, '0') === '1';
}

/**
 * Get the current tenant/company name for display purposes
 *
 * In a SaaS multi-tenant setup, this returns the tenant's company name.
 * On the master instance, it returns 'Master'.
 *
 * @return string
 */
function ccx_security_get_tenant_name()
{
    // Check if running as a SaaS tenant
    if (function_exists('perfex_saas_is_tenant') && perfex_saas_is_tenant()) {
        $tenant = function_exists('perfex_saas_tenant') ? perfex_saas_tenant() : null;
        if ($tenant && !empty($tenant->name)) {
            return $tenant->name;
        }
    }

    // Fallback: use the company name from options (works for both master and tenant with DB isolation)
    $company = get_option('companyname');
    if (!empty($company)) {
        return $company;
    }

    return 'Master';
}

/**
 * Check if the current instance is a tenant (not master)
 *
 * @return bool
 */
function ccx_security_is_tenant()
{
    return function_exists('perfex_saas_is_tenant') && perfex_saas_is_tenant();
}

/**
 * Build the CSP `frame-ancestors` source list for admin pages.
 *
 * In a multi-tenant SaaS setup the master console embeds each tenant's admin panel in an
 * iframe (the "instance preview" / client viewer — see perfex_saas client themes). On
 * production the master (e.g. healtho.pro) and the tenant (e.g. mmch.healtho.pro) live on
 * different subdomains, so a blanket `frame-ancestors 'self'` / `X-Frame-Options: SAMEORIGIN`
 * blocks that cross-origin embed and the browser shows "This content is blocked. Contact the
 * site owner to fix the issue." (On path-based dev like healtho.tech/<slug>/ everything is
 * same-origin, which is why it only breaks in production.)
 *
 * We therefore allow framing from `'self'` plus the SaaS master host(s): the configured
 * default host, the optional alternative host, and any staging hosts. These all come from the
 * perfex_saas configuration, so nothing is hardcoded and the allowlist stays correct across
 * production, staging and dev. Arbitrary third-party framing remains blocked.
 *
 * @return string Space-separated CSP source list, e.g. "'self' healtho.pro"
 */
function ccx_security_frame_ancestors()
{
    $sources = ["'self'"];

    $push = function ($host) use (&$sources) {
        if ($host === '' || in_array($host, $sources, true)) {
            return;
        }
        $sources[] = $host;
    };

    $add = function ($host) use ($push) {
        if (!$host) {
            return;
        }
        $host = strtolower(trim((string) $host));
        // Tolerate a full URL slipping through — reduce it to just the host.
        if (strpos($host, '://') !== false) {
            $host = (string) parse_url($host, PHP_URL_HOST);
        }
        $host = explode(':', $host, 2)[0];
        if ($host === '') {
            return;
        }
        $push($host);

        // Tenants live on subdomains of the master host (e.g. <slug>.healtho.pro). The embed
        // works both ways — the master console frames a tenant's admin panel AND a tenant's
        // billing/my_account page frames the master client portal back (client_portal_bridge /
        // client_portal_framer). For the latter the master must accept framing from the tenant
        // subdomain, so allow `*.host` in addition to the bare host. Skip IPs / single-label
        // hosts (e.g. localhost) where a wildcard subdomain is meaningless. Custom-domain
        // tenants are not covered here — they intentionally fall back to opening in a new tab.
        if (strpos($host, '.') !== false && !filter_var($host, FILTER_VALIDATE_IP) && strpos($host, '*') === false) {
            $push('*.' . $host);
        }
    };

    // SaaS master (super) host — the console that embeds tenant admin panels.
    if (function_exists('perfex_saas_get_saas_default_host')) {
        $add(perfex_saas_get_saas_default_host());
    }

    // Optional alternative master front door (e.g. a staging host bound at runtime).
    if (function_exists('perfex_saas_get_saas_alternative_host')) {
        $add(perfex_saas_get_saas_alternative_host());
    }

    // Any additional configured staging hosts.
    if (function_exists('ccx_runtime_staging_hosts')) {
        foreach ((array) ccx_runtime_staging_hosts() as $staging_host) {
            $add($staging_host);
        }
    }

    return implode(' ', $sources);
}

/**
 * Get the real client IP address, supporting proxies and load balancers
 *
 * @return string
 */
function ccx_security_get_client_ip()
{
    $headers = [
        'HTTP_CF_CONNECTING_IP',     // Cloudflare
        'HTTP_X_FORWARDED_FOR',      // Standard proxy
        'HTTP_X_REAL_IP',            // Nginx proxy
        'HTTP_CLIENT_IP',            // Shared internet
        'REMOTE_ADDR',               // Direct connection
    ];

    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = $_SERVER[$header];
            // X-Forwarded-For may contain multiple IPs; take the first
            if (strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Deep sanitize input value
 * Strips HTML tags, SQL dangerous characters, and normalizes whitespace
 *
 * @param mixed $input Input to sanitize
 * @return mixed
 */
function ccx_security_sanitize_input($input)
{
    if (is_array($input)) {
        return array_map('ccx_security_sanitize_input', $input);
    }

    if (!is_string($input)) {
        return $input;
    }

    // Remove null bytes
    $input = str_replace(chr(0), '', $input);

    // Strip HTML/PHP tags
    $input = strip_tags($input);

    // Convert special characters to HTML entities
    $input = htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    // Remove common SQL injection characters
    $input = str_replace(['--', '/*', '*/', '@@', 'char(', 'nchar('], '', $input);

    return trim($input);
}

/**
 * Check if input string contains suspicious SQL injection patterns
 *
 * @param string $input The input to check
 * @return array ['is_suspicious' => bool, 'patterns_found' => array]
 */
function ccx_security_is_suspicious_input($input)
{
    if (!is_string($input) || strlen($input) < 5) {
        return ['is_suspicious' => false, 'patterns_found' => []];
    }

    $suspicious_patterns = [
        '/\bUNION\s+(ALL\s+)?SELECT\b/i'                => 'UNION SELECT',
        '/\bSELECT\s+.*\bFROM\s+.*\bWHERE\b/i'         => 'SELECT FROM WHERE',
        '/\bINSERT\s+INTO\b/i'                          => 'INSERT INTO',
        '/\bDELETE\s+FROM\b/i'                          => 'DELETE FROM',
        '/\bDROP\s+(TABLE|DATABASE|INDEX)\b/i'           => 'DROP statement',
        '/\bALTER\s+TABLE\b/i'                          => 'ALTER TABLE',
        '/\bEXEC(UTE)?\s*\(/i'                           => 'EXECUTE',
        '/\bxp_cmdshell\b/i'                             => 'xp_cmdshell',
        '/\bLOAD_FILE\s*\(/i'                            => 'LOAD_FILE',
        '/\bINTO\s+(OUT|DUMP)FILE\b/i'                   => 'INTO OUTFILE',
        '/\bBENCHMARK\s*\(/i'                            => 'BENCHMARK',
        '/\bSLEEP\s*\(\s*\d+\s*\)/i'                    => 'SLEEP()',
        '/\bOR\s+1\s*=\s*1\b/i'                         => 'OR 1=1',
        '/\bOR\s+[\'\"]?\s*=\s*[\'\"]?\b/i'               => 'OR tautology',
        '/[\'\"];?\s*--/i'                                => 'SQL comment injection',
        '/\/\*.*?\*\//s'                                 => 'Block comment',
        '/\b(CONCAT|CHAR|ASCII|SUBSTRING|HEX)\s*\(/i'   => 'String function abuse',
        '/\bINFORMATION_SCHEMA\b/i'                      => 'INFORMATION_SCHEMA access',
        '/\bSYS\.(USER|DATABASE)\b/i'                    => 'System table access',
    ];

    $found = [];
    foreach ($suspicious_patterns as $pattern => $label) {
        if (preg_match($pattern, $input)) {
            $found[] = $label;
        }
    }

    return [
        'is_suspicious' => !empty($found),
        'patterns_found' => $found,
    ];
}

/**
 * Check if an uploaded file is safe
 *
 * @param array $file $_FILES element
 * @return array ['safe' => bool, 'reason' => string]
 */
function ccx_security_check_file_upload($file)
{
    if (empty($file) || empty($file['name'])) {
        return ['safe' => false, 'reason' => 'No file provided'];
    }

    // 1. Check extension against blocked list
    $blocked_ext = ccx_security_get_setting('blocked_extensions', 'php,phtml,php3,php4,php5,php7,phar,sh,bash,exe,bat,cmd,cgi,pl,py,jsp,asp,aspx');
    $blocked_arr = array_map('trim', explode(',', strtolower($blocked_ext)));
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (in_array($ext, $blocked_arr)) {
        return ['safe' => false, 'reason' => 'Blocked file extension: .' . $ext];
    }

    // 2. Check for double extensions (e.g., shell.php.jpg)
    $filename_parts = explode('.', $file['name']);
    if (count($filename_parts) > 2) {
        foreach ($filename_parts as $part) {
            if (in_array(strtolower($part), $blocked_arr)) {
                return ['safe' => false, 'reason' => 'Suspicious double extension detected: ' . $file['name']];
            }
        }
    }

    // 3. Check file content for PHP signatures
    if (isset($file['tmp_name']) && file_exists($file['tmp_name'])) {
        $content = file_get_contents($file['tmp_name'], false, null, 0, 4096);
        $php_signatures = [
            '<?php',
            '<?=',
            '<? ',
            '#!/',
            '<%',
            '<script language="php"',
            'eval(',
            'base64_decode(',
            'system(',
            'exec(',
            'passthru(',
            'shell_exec(',
            'popen(',
            'proc_open(',
        ];

        foreach ($php_signatures as $sig) {
            if (stripos($content, $sig) !== false) {
                return ['safe' => false, 'reason' => 'Malicious code signature detected in file content'];
            }
        }
    }

    // 4. Check MIME type validation
    if (isset($file['tmp_name']) && file_exists($file['tmp_name']) && function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $dangerous_mimes = [
            'application/x-httpd-php',
            'application/x-php',
            'text/x-php',
            'application/x-executable',
            'application/x-sharedlib',
            'application/x-shellscript',
            'application/x-msdos-program',
        ];

        if (in_array($mime, $dangerous_mimes)) {
            return ['safe' => false, 'reason' => 'Dangerous MIME type detected: ' . $mime];
        }
    }

    // 5. Max file size check
    $max_mb = (int) ccx_security_get_setting('max_upload_mb', '10');
    if ($max_mb > 0 && isset($file['size']) && $file['size'] > ($max_mb * 1024 * 1024)) {
        return ['safe' => false, 'reason' => 'File exceeds maximum allowed size of ' . $max_mb . 'MB'];
    }

    return ['safe' => true, 'reason' => ''];
}

/**
 * Calculate the overall security score (0-100)
 *
 * @return int
 */
function ccx_security_get_score()
{
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
        // New enterprise features
        '2fa_enabled'                => 15,
        'ip_whitelist_enabled'       => 8,
        'password_policy_enabled'    => 10,
        'session_tracking_enabled'   => 7,
    ];

    $score = 0;
    foreach ($features as $feature => $weight) {
        if (ccx_security_is_enabled($feature)) {
            $score += $weight;
        }
    }

    return min($score, 100);
}

/**
 * Get the security score label and color
 *
 * @param int $score
 * @return array ['label' => string, 'color' => string]
 */
function ccx_security_score_info($score)
{
    if ($score >= 85) {
        return ['label' => _l('ccx_security_score_excellent'), 'color' => '#10b981'];
    } elseif ($score >= 65) {
        return ['label' => _l('ccx_security_score_good'), 'color' => '#3b82f6'];
    } elseif ($score >= 40) {
        return ['label' => _l('ccx_security_score_fair'), 'color' => '#f59e0b'];
    } else {
        return ['label' => _l('ccx_security_score_poor'), 'color' => '#ef4444'];
    }
}

/**
 * Generate a random nonce for Content-Security-Policy
 *
 * @return string Base64-encoded nonce
 */
function ccx_security_generate_csp_nonce()
{
    static $nonce = null;
    if ($nonce === null) {
        $nonce = base64_encode(random_bytes(16));
    }
    return $nonce;
}

/**
 * Log a security event
 *
 * @param string $type     Event type
 * @param string $desc     Description
 * @param string $severity 'info', 'warning', or 'critical'
 * @param bool   $force    If true, bypass audit_log_enabled check (for critical events)
 */
function ccx_security_log_event($type, $desc, $severity = 'info', $force = false)
{
    // Always log critical/forced events even if audit log is disabled
    if (!$force && !ccx_security_is_enabled('audit_log_enabled')) {
        return;
    }

    // Still respect the global kill switch (unless forced)
    if (!$force && get_option('ccx_security_enabled') !== '1') {
        return;
    }

    $CI = &get_instance();

    // Check if the audit log table exists before inserting
    if (!$CI->db->table_exists(db_prefix() . 'ccx_security_audit_log')) {
        return;
    }

    $staff_id = null;
    if (function_exists('get_staff_user_id')) {
        $staff_id = get_staff_user_id();
    }

    // Capture the tenant/company name for multi-tenant context
    $tenant_name = ccx_security_get_tenant_name();

    $CI->db->insert(db_prefix() . 'ccx_security_audit_log', [
        'event_type'     => $type,
        'description'    => $desc,
        'ip_address'     => ccx_security_get_client_ip(),
        'user_agent'     => isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 500) : '',
        'staff_id'       => $staff_id,
        'tenant_name'    => $tenant_name,
        'request_uri'    => isset($_SERVER['REQUEST_URI']) ? substr($_SERVER['REQUEST_URI'], 0, 500) : '',
        'request_method' => isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '',
        'severity'       => $severity,
        'created_at'     => date('Y-m-d H:i:s'),
    ]);
}

// ═══════════════════════════════════════════════════════════════
// ─── TOTP / TWO-FACTOR AUTHENTICATION FUNCTIONS ───
// ═══════════════════════════════════════════════════════════════

/**
 * Generate a TOTP secret key (20 bytes, base32 encoded)
 *
 * @return string Base32-encoded secret (32 chars)
 */
function ccx_security_generate_totp_secret()
{
    $bytes = random_bytes(20);
    return ccx_security_base32_encode($bytes);
}

/**
 * Base32 encode a string
 *
 * @param string $data Raw binary data
 * @return string Base32-encoded string
 */
function ccx_security_base32_encode($data)
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $binary = '';
    foreach (str_split($data) as $char) {
        $binary .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
    }

    $result = '';
    $chunks = str_split($binary, 5);
    foreach ($chunks as $chunk) {
        $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
        $result .= $alphabet[bindec($chunk)];
    }

    return $result;
}

/**
 * Base32 decode a string
 *
 * @param string $base32 Base32-encoded string
 * @return string Raw binary data
 */
function ccx_security_base32_decode($base32)
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $base32 = strtoupper(rtrim($base32, '='));
    $binary = '';

    foreach (str_split($base32) as $char) {
        $pos = strpos($alphabet, $char);
        if ($pos === false) {
            continue;
        }
        $binary .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
    }

    $result = '';
    $bytes = str_split($binary, 8);
    foreach ($bytes as $byte) {
        if (strlen($byte) === 8) {
            $result .= chr(bindec($byte));
        }
    }

    return $result;
}

/**
 * Generate a TOTP code for the given secret
 *
 * @param string $secret Base32-encoded secret
 * @param int    $time_step Time step counter (default: current 30-second window)
 * @return string 6-digit TOTP code
 */
function ccx_security_generate_totp_code($secret, $time_step = null)
{
    if ($time_step === null) {
        $time_step = floor(time() / 30);
    }

    $key = ccx_security_base32_decode($secret);

    // Pack time as 8-byte big-endian unsigned long
    $time_bytes = pack('N*', 0, $time_step);

    // HMAC-SHA1
    $hash = hash_hmac('sha1', $time_bytes, $key, true);

    // Dynamic truncation
    $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
    $code = (
        ((ord($hash[$offset]) & 0x7F) << 24) |
        ((ord($hash[$offset + 1]) & 0xFF) << 16) |
        ((ord($hash[$offset + 2]) & 0xFF) << 8) |
        (ord($hash[$offset + 3]) & 0xFF)
    );

    return str_pad($code % 1000000, 6, '0', STR_PAD_LEFT);
}

/**
 * Verify a TOTP code against a secret with ±1 window tolerance
 *
 * @param string $secret Base32-encoded secret
 * @param string $code   6-digit code to verify
 * @param int    $window Number of windows to check in each direction (default: 1)
 * @return bool
 */
function ccx_security_verify_totp($secret, $code, $window = 1)
{
    $time_step = floor(time() / 30);

    for ($i = -$window; $i <= $window; $i++) {
        $expected = ccx_security_generate_totp_code($secret, $time_step + $i);
        if (hash_equals($expected, str_pad($code, 6, '0', STR_PAD_LEFT))) {
            return true;
        }
    }

    return false;
}

/**
 * Generate the TOTP provisioning URI for QR code
 *
 * @param string $secret  Base32 secret
 * @param string $email   User email / account name
 * @param string $issuer  Application name
 * @return string otpauth:// URI
 */
function ccx_security_totp_uri($secret, $email, $issuer = 'CCX Security')
{
    return 'otpauth://totp/' . rawurlencode($issuer) . ':' . rawurlencode($email)
        . '?secret=' . $secret
        . '&issuer=' . rawurlencode($issuer)
        . '&digits=6'
        . '&period=30';
}

/**
 * Generate a QR code container for client-side rendering
 * Uses qrcode.js library loaded in the view to render the QR code in-browser
 * No external API dependency.
 *
 * @param string $data Data to encode (otpauth:// URI)
 * @param int    $size QR code size in pixels
 * @return string HTML markup with data attribute for JS rendering
 */
function ccx_security_generate_qr_svg($data, $size = 250)
{
    $escaped = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return '<div id="ccx_qr_container" data-qr="' . $escaped . '" style="display:inline-block;padding:10px;background:#fff;border:4px solid #e5e7eb;border-radius:12px;"></div>';
}

/**
 * Generate recovery codes for 2FA backup
 *
 * @param int $count Number of codes to generate (default: 10)
 * @return array Array of recovery codes
 */
function ccx_security_generate_recovery_codes($count = 10)
{
    $codes = [];
    for ($i = 0; $i < $count; $i++) {
        // Format: XXXX-XXXX-XXXX (12 chars alphanumeric)
        $code = strtoupper(substr(bin2hex(random_bytes(6)), 0, 12));
        $codes[] = substr($code, 0, 4) . '-' . substr($code, 4, 4) . '-' . substr($code, 8, 4);
    }
    return $codes;
}


// ═══════════════════════════════════════════════════════════════
// ─── IP WHITELIST / CIDR FUNCTIONS ───
// ═══════════════════════════════════════════════════════════════

/**
 * Check if an IP address falls within a CIDR range
 *
 * @param string $ip   IP address to check
 * @param string $cidr CIDR notation (e.g., 192.168.1.0/24)
 * @return bool
 */
function ccx_security_ip_in_cidr($ip, $cidr)
{
    if (strpos($cidr, '/') === false) {
        // Not CIDR, exact match
        return $ip === $cidr;
    }

    list($subnet, $bits) = explode('/', $cidr, 2);
    $bits = (int) $bits;

    if ($bits < 0 || $bits > 32) {
        return false;
    }

    $ip_long = ip2long($ip);
    $subnet_long = ip2long($subnet);

    if ($ip_long === false || $subnet_long === false) {
        return false;
    }

    $mask = -1 << (32 - $bits);
    return ($ip_long & $mask) === ($subnet_long & $mask);
}

/**
 * Check if an IP is in the whitelist
 *
 * @param string $ip IP address
 * @return bool
 */
function ccx_security_check_ip_whitelist($ip)
{
    $CI = &get_instance();

    if (!$CI->db->table_exists(db_prefix() . 'ccx_security_ip_whitelist')) {
        return true; // No table = allow all
    }

    $whitelist = $CI->db->get(db_prefix() . 'ccx_security_ip_whitelist')->result();

    if (empty($whitelist)) {
        return true; // Empty whitelist = allow all (fail-open)
    }

    foreach ($whitelist as $entry) {
        if ($entry->is_cidr) {
            if (ccx_security_ip_in_cidr($ip, $entry->ip_address)) {
                return true;
            }
        } else {
            if ($ip === $entry->ip_address) {
                return true;
            }
        }
    }

    return false;
}


// ═══════════════════════════════════════════════════════════════
// ─── PASSWORD POLICY FUNCTIONS ───
// ═══════════════════════════════════════════════════════════════

/**
 * Check if a password meets the configured policy
 *
 * @param string $password The password to check
 * @return array ['passed' => bool, 'errors' => array]
 */
function ccx_security_check_password_policy($password)
{
    $errors = [];

    $min_length = (int) ccx_security_get_setting('pw_min_length', '12');
    if (strlen($password) < $min_length) {
        $errors[] = sprintf('Password must be at least %d characters long', $min_length);
    }

    if (ccx_security_get_setting('pw_require_upper', '1') === '1') {
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter';
        }
    }

    if (ccx_security_get_setting('pw_require_lower', '1') === '1') {
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter';
        }
    }

    if (ccx_security_get_setting('pw_require_number', '1') === '1') {
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number';
        }
    }

    if (ccx_security_get_setting('pw_require_special', '1') === '1') {
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = 'Password must contain at least one special character (!@#$%^&* etc.)';
        }
    }

    return [
        'passed' => empty($errors),
        'errors' => $errors,
    ];
}

/**
 * Calculate password strength score (0-100)
 *
 * @param string $password
 * @return array ['score' => int, 'label' => string, 'color' => string]
 */
function ccx_security_password_strength($password)
{
    $score = 0;
    $len = strlen($password);

    // Length scoring
    if ($len >= 8) $score += 10;
    if ($len >= 12) $score += 15;
    if ($len >= 16) $score += 10;
    if ($len >= 20) $score += 5;

    // Complexity
    if (preg_match('/[A-Z]/', $password)) $score += 15;
    if (preg_match('/[a-z]/', $password)) $score += 10;
    if (preg_match('/[0-9]/', $password)) $score += 15;
    if (preg_match('/[^A-Za-z0-9]/', $password)) $score += 20;

    // Variety bonus
    $unique = count(array_unique(str_split($password)));
    if ($unique > 6) $score += min(($unique - 6), 10);

    $score = min($score, 100);

    if ($score >= 80) {
        return ['score' => $score, 'label' => 'Strong', 'color' => '#10b981'];
    } elseif ($score >= 50) {
        return ['score' => $score, 'label' => 'Moderate', 'color' => '#f59e0b'];
    } else {
        return ['score' => $score, 'label' => 'Weak', 'color' => '#ef4444'];
    }
}

/**
 * Parse a user-agent string into a simple device description
 *
 * @param string $user_agent
 * @return string
 */
function ccx_security_parse_device($user_agent)
{
    if (empty($user_agent)) {
        return 'Unknown Device';
    }

    // Detect browser
    $browser = 'Unknown Browser';
    if (stripos($user_agent, 'Firefox') !== false) {
        $browser = 'Firefox';
    } elseif (stripos($user_agent, 'Edg') !== false) {
        $browser = 'Edge';
    } elseif (stripos($user_agent, 'Chrome') !== false) {
        $browser = 'Chrome';
    } elseif (stripos($user_agent, 'Safari') !== false) {
        $browser = 'Safari';
    } elseif (stripos($user_agent, 'Opera') !== false || stripos($user_agent, 'OPR') !== false) {
        $browser = 'Opera';
    }

    // Detect OS
    $os = 'Unknown OS';
    if (stripos($user_agent, 'Windows') !== false) {
        $os = 'Windows';
    } elseif (stripos($user_agent, 'Mac') !== false) {
        $os = 'macOS';
    } elseif (stripos($user_agent, 'Linux') !== false) {
        $os = 'Linux';
    } elseif (stripos($user_agent, 'Android') !== false) {
        $os = 'Android';
    } elseif (stripos($user_agent, 'iPhone') !== false || stripos($user_agent, 'iPad') !== false) {
        $os = 'iOS';
    }

    return $browser . ' on ' . $os;
}


// ═══════════════════════════════════════════════════════════════
// ─── COMPLIANCE CHECKLIST FUNCTIONS ───
// ═══════════════════════════════════════════════════════════════

/**
 * Get the security compliance checklist
 *
 * @return array Array of compliance items with pass/fail status
 */
function ccx_security_get_compliance_checklist()
{
    $items = [];

    // ─── OWASP Top 10 Mitigations ───
    $items[] = [
        'category' => 'OWASP Top 10',
        'id'       => 'A01',
        'name'     => 'A01: Broken Access Control',
        'desc'     => 'Session protection, IP binding, and concurrent session limits',
        'passed'   => ccx_security_is_enabled('session_protection_enabled') && ccx_security_is_enabled('session_tracking_enabled'),
        'features' => ['session_protection_enabled', 'session_tracking_enabled'],
    ];
    $items[] = [
        'category' => 'OWASP Top 10',
        'id'       => 'A02',
        'name'     => 'A02: Cryptographic Failures',
        'desc'     => 'HSTS enforcement and secure transport headers',
        'passed'   => ccx_security_is_enabled('http_headers_enabled') && get_option('ccx_security_hsts_enabled') === '1',
        'features' => ['http_headers_enabled'],
    ];
    $items[] = [
        'category' => 'OWASP Top 10',
        'id'       => 'A03',
        'name'     => 'A03: Injection',
        'desc'     => 'SQL injection monitoring and input sanitization',
        'passed'   => ccx_security_is_enabled('sql_monitor_enabled') && ccx_security_is_enabled('xss_protection_enabled'),
        'features' => ['sql_monitor_enabled', 'xss_protection_enabled'],
    ];
    $items[] = [
        'category' => 'OWASP Top 10',
        'id'       => 'A04',
        'name'     => 'A04: Insecure Design',
        'desc'     => 'CSRF hardening and Content Security Policy',
        'passed'   => ccx_security_is_enabled('csrf_hardening_enabled') && ccx_security_is_enabled('http_headers_enabled'),
        'features' => ['csrf_hardening_enabled', 'http_headers_enabled'],
    ];
    $items[] = [
        'category' => 'OWASP Top 10',
        'id'       => 'A05',
        'name'     => 'A05: Security Misconfiguration',
        'desc'     => 'HTTP security headers and permissions policy',
        'passed'   => ccx_security_is_enabled('http_headers_enabled'),
        'features' => ['http_headers_enabled'],
    ];
    $items[] = [
        'category' => 'OWASP Top 10',
        'id'       => 'A07',
        'name'     => 'A07: Identification & Authentication Failures',
        'desc'     => 'Multi-factor authentication and brute force protection',
        'passed'   => ccx_security_is_enabled('brute_force_enabled') && ccx_security_is_enabled('2fa_enabled'),
        'features' => ['brute_force_enabled', '2fa_enabled'],
    ];
    $items[] = [
        'category' => 'OWASP Top 10',
        'id'       => 'A08',
        'name'     => 'A08: Software & Data Integrity',
        'desc'     => 'File upload scanning and malware detection',
        'passed'   => ccx_security_is_enabled('file_upload_scan_enabled'),
        'features' => ['file_upload_scan_enabled'],
    ];
    $items[] = [
        'category' => 'OWASP Top 10',
        'id'       => 'A09',
        'name'     => 'A09: Security Logging & Monitoring',
        'desc'     => 'Comprehensive audit logging with retention',
        'passed'   => ccx_security_is_enabled('audit_log_enabled'),
        'features' => ['audit_log_enabled'],
    ];

    // ─── SOC 2 Controls ───
    $items[] = [
        'category' => 'SOC 2',
        'id'       => 'CC6.1',
        'name'     => 'CC6.1: Logical Access',
        'desc'     => 'Multi-factor authentication for admin access',
        'passed'   => ccx_security_is_enabled('2fa_enabled'),
        'features' => ['2fa_enabled'],
    ];
    $items[] = [
        'category' => 'SOC 2',
        'id'       => 'CC6.2',
        'name'     => 'CC6.2: Authentication Mechanisms',
        'desc'     => 'Password policy enforcement with complexity requirements',
        'passed'   => ccx_security_is_enabled('password_policy_enabled'),
        'features' => ['password_policy_enabled'],
    ];
    $items[] = [
        'category' => 'SOC 2',
        'id'       => 'CC6.3',
        'name'     => 'CC6.3: Access Restrictions',
        'desc'     => 'IP whitelisting and network-level access control',
        'passed'   => ccx_security_is_enabled('ip_whitelist_enabled'),
        'features' => ['ip_whitelist_enabled'],
    ];
    $items[] = [
        'category' => 'SOC 2',
        'id'       => 'CC7.2',
        'name'     => 'CC7.2: Monitoring Activities',
        'desc'     => 'Real-time security event monitoring and alerting',
        'passed'   => ccx_security_is_enabled('audit_log_enabled') && ccx_security_is_enabled('sql_monitor_enabled'),
        'features' => ['audit_log_enabled', 'sql_monitor_enabled'],
    ];

    // ─── HIPAA Technical Safeguards ───
    $items[] = [
        'category' => 'HIPAA',
        'id'       => '164.312(a)',
        'name'     => '§164.312(a): Access Control',
        'desc'     => 'Unique user identification and emergency access procedures',
        'passed'   => ccx_security_is_enabled('session_protection_enabled') && ccx_security_is_enabled('brute_force_enabled'),
        'features' => ['session_protection_enabled', 'brute_force_enabled'],
    ];
    $items[] = [
        'category' => 'HIPAA',
        'id'       => '164.312(b)',
        'name'     => '§164.312(b): Audit Controls',
        'desc'     => 'Hardware, software, and procedural audit mechanisms',
        'passed'   => ccx_security_is_enabled('audit_log_enabled'),
        'features' => ['audit_log_enabled'],
    ];
    $items[] = [
        'category' => 'HIPAA',
        'id'       => '164.312(d)',
        'name'     => '§164.312(d): Person/Entity Authentication',
        'desc'     => 'Multi-factor authentication to verify user identity',
        'passed'   => ccx_security_is_enabled('2fa_enabled') && ccx_security_is_enabled('password_policy_enabled'),
        'features' => ['2fa_enabled', 'password_policy_enabled'],
    ];
    $items[] = [
        'category' => 'HIPAA',
        'id'       => '164.312(e)',
        'name'     => '§164.312(e): Transmission Security',
        'desc'     => 'HSTS enforcement and encrypted transport',
        'passed'   => ccx_security_is_enabled('http_headers_enabled') && get_option('ccx_security_hsts_enabled') === '1',
        'features' => ['http_headers_enabled'],
    ];

    return $items;
}
