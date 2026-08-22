<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Mailbox — plain-function engine.
 *
 * Access gates, credential crypto, provider presets for the connect wizard,
 * the IMAP pull engine (incremental, UID-based) and the SMTP send engine
 * (PHPMailer). Loaded unconditionally from mailbox.php because cron and the
 * admin area both need it.
 */

/* ─────────────────────────── Access control ─────────────────────────────── */

/**
 * Superadmin — may manage accounts, assignments and open every mailbox.
 */
function mailbox_staff_is_super()
{
    return is_admin();
}

/**
 * Any access at all: superadmin, or staff with the view capability, or staff
 * with at least one assigned account.
 */
function mailbox_staff_can_access()
{
    if (!is_staff_logged_in()) {
        return false;
    }

    if (mailbox_staff_is_super() || staff_can('view', MAILBOX_MODULE_NAME)) {
        return true;
    }

    return count(mailbox_accounts_for_staff(get_staff_user_id())) > 0;
}

/**
 * Accounts the given staff member may use. Superadmin sees every account;
 * everyone else only the ones assigned to them. Credentials are never
 * included in the returned rows.
 *
 * @return array of stdClass rows
 */
function mailbox_accounts_for_staff($staff_id)
{
    static $cache = [];

    $staff_id = (int) $staff_id;
    if (isset($cache[$staff_id])) {
        return $cache[$staff_id];
    }

    if (!mailbox_ensure_schema()) {
        return $cache[$staff_id] = [];
    }

    $CI = &get_instance();
    $p  = db_prefix();

    if (is_admin($staff_id)) {
        $rows = $CI->db->query(
            "SELECT id, name, email, from_name, provider, active, last_sync_at, last_sync_error, smtp_ok, imap_ok
             FROM `{$p}mailbox_accounts` ORDER BY name ASC"
        )->result();
    } else {
        $rows = $CI->db->query(
            "SELECT a.id, a.name, a.email, a.from_name, a.provider, a.active, a.last_sync_at, a.last_sync_error, a.smtp_ok, a.imap_ok
             FROM `{$p}mailbox_accounts` a
             JOIN `{$p}mailbox_account_staff` s ON s.account_id = a.id
             WHERE s.staff_id = ? AND a.active = 1
             ORDER BY a.name ASC",
            [$staff_id]
        )->result();
    }

    return $cache[$staff_id] = $rows;
}

/**
 * May the current staff member use (read/send from) this account?
 */
function mailbox_staff_can_use_account($account_id)
{
    foreach (mailbox_accounts_for_staff(get_staff_user_id()) as $a) {
        if ((int) $a->id === (int) $account_id) {
            return true;
        }
    }

    return false;
}

/* ───────────────────────── Credential crypto ────────────────────────────── */

function mailbox_encrypt($value)
{
    if ($value === '' || $value === null) {
        return '';
    }
    $CI = &get_instance();
    $CI->load->library('encryption');

    return $CI->encryption->encrypt($value);
}

function mailbox_decrypt($value)
{
    if ($value === '' || $value === null) {
        return '';
    }
    $CI = &get_instance();
    $CI->load->library('encryption');

    $decrypted = $CI->encryption->decrypt($value);

    return $decrypted === false ? '' : $decrypted;
}

/* ─────────────────────────────── Schema ─────────────────────────────────── */

/**
 * Create / heal the mailbox_* tables. Safe to call on every request path
 * that touches the module (cheap static guard).
 */
function mailbox_ensure_schema()
{
    static $ensured = null;
    if ($ensured !== null) {
        return $ensured;
    }

    $CI = &get_instance();
    $p  = db_prefix();

    try {
        if (!$CI->db->table_exists($p . 'mailbox_accounts')) {
            $CI->db->query("CREATE TABLE `{$p}mailbox_accounts` (
                `id` INT NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(150) NOT NULL,
                `email` VARCHAR(191) NOT NULL,
                `from_name` VARCHAR(150) DEFAULT '',
                `signature` TEXT NULL,
                `provider` VARCHAR(30) DEFAULT 'custom',
                `smtp_host` VARCHAR(191) DEFAULT '',
                `smtp_port` INT DEFAULT 465,
                `smtp_encryption` VARCHAR(10) DEFAULT 'ssl',
                `smtp_username` VARCHAR(191) DEFAULT '',
                `smtp_password` TEXT NULL,
                `imap_host` VARCHAR(191) DEFAULT '',
                `imap_port` INT DEFAULT 993,
                `imap_encryption` VARCHAR(10) DEFAULT 'ssl',
                `imap_username` VARCHAR(191) DEFAULT '',
                `imap_password` TEXT NULL,
                `imap_folder` VARCHAR(100) DEFAULT 'INBOX',
                `imap_sent_folder` VARCHAR(100) DEFAULT '',
                `imap_validate_cert` TINYINT(1) DEFAULT 0,
                `active` TINYINT(1) DEFAULT 1,
                `smtp_ok` TINYINT(1) DEFAULT 0,
                `imap_ok` TINYINT(1) DEFAULT 0,
                `last_uid` INT DEFAULT 0,
                `initial_synced` TINYINT(1) DEFAULT 0,
                `last_sync_at` DATETIME NULL,
                `last_sync_error` TEXT NULL,
                `created_by` INT DEFAULT 0,
                `created_at` DATETIME NULL,
                PRIMARY KEY (`id`),
                KEY `active` (`active`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        }

        if (!$CI->db->table_exists($p . 'mailbox_account_staff')) {
            $CI->db->query("CREATE TABLE `{$p}mailbox_account_staff` (
                `id` INT NOT NULL AUTO_INCREMENT,
                `account_id` INT NOT NULL,
                `staff_id` INT NOT NULL,
                `created_at` DATETIME NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `account_staff` (`account_id`, `staff_id`),
                KEY `staff_id` (`staff_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        }

        if (!$CI->db->table_exists($p . 'mailbox_messages')) {
            $CI->db->query("CREATE TABLE `{$p}mailbox_messages` (
                `id` INT NOT NULL AUTO_INCREMENT,
                `account_id` INT NOT NULL,
                `folder` VARCHAR(20) NOT NULL DEFAULT 'inbox',
                `orig_folder` VARCHAR(20) DEFAULT NULL,
                `uid` INT NOT NULL DEFAULT 0,
                `message_id` VARCHAR(255) DEFAULT '',
                `in_reply_to` VARCHAR(255) DEFAULT '',
                `refs` TEXT NULL,
                `thread_id` INT NOT NULL DEFAULT 0,
                `subject` VARCHAR(500) DEFAULT '',
                `from_name` VARCHAR(191) DEFAULT '',
                `from_email` VARCHAR(191) DEFAULT '',
                `to_emails` TEXT NULL,
                `cc_emails` TEXT NULL,
                `bcc_emails` TEXT NULL,
                `reply_to` VARCHAR(191) DEFAULT '',
                `snippet` VARCHAR(300) DEFAULT '',
                `body_html` LONGTEXT NULL,
                `body_plain` LONGTEXT NULL,
                `has_attachments` TINYINT(1) DEFAULT 0,
                `is_read` TINYINT(1) DEFAULT 0,
                `is_starred` TINYINT(1) DEFAULT 0,
                `sent_by` INT DEFAULT 0,
                `message_date` DATETIME NULL,
                `created_at` DATETIME NULL,
                PRIMARY KEY (`id`),
                KEY `account_folder_date` (`account_id`, `folder`, `message_date`),
                KEY `account_uid` (`account_id`, `uid`),
                KEY `message_id` (`message_id`(191)),
                KEY `thread_id` (`thread_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        }

        if (!$CI->db->table_exists($p . 'mailbox_attachments')) {
            $CI->db->query("CREATE TABLE `{$p}mailbox_attachments` (
                `id` INT NOT NULL AUTO_INCREMENT,
                `message_id` INT NOT NULL,
                `file_name` VARCHAR(255) NOT NULL,
                `stored_name` VARCHAR(255) NOT NULL,
                `mime` VARCHAR(150) DEFAULT '',
                `size` INT DEFAULT 0,
                `created_at` DATETIME NULL,
                PRIMARY KEY (`id`),
                KEY `message_id` (`message_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        }

        // Corporate tables + the extra columns on the two tables above.
        if (function_exists('mailbox_pro_ensure_schema')) {
            mailbox_pro_ensure_schema();
        }

        return $ensured = true;
    } catch (Exception $e) {
        log_activity('Mailbox schema error: ' . $e->getMessage());

        return $ensured = false;
    }
}

/* ─────────────────────── Provider presets (wizard) ──────────────────────── */

/**
 * Presets that power the interactive connect guide. Each provider carries
 * ready-made SMTP/IMAP endpoints plus step-by-step instructions rendered in
 * the wizard (app passwords, where to enable IMAP, etc.).
 */
function mailbox_provider_presets()
{
    return [
        'gmail' => [
            'label' => 'Gmail / Google Workspace',
            'icon'  => 'fa-brands fa-google',
            'smtp'  => ['host' => 'smtp.gmail.com', 'port' => 465, 'encryption' => 'ssl'],
            'imap'  => ['host' => 'imap.gmail.com', 'port' => 993, 'encryption' => 'ssl'],
            // Gmail copies SMTP-sent mail to its Sent folder by itself —
            // appending again would duplicate every outgoing message.
            'sent_folder' => '',
            'app_password_url' => 'https://myaccount.google.com/apppasswords',
            'steps' => [
                _l('mailbox_guide_gmail_1'),
                _l('mailbox_guide_gmail_2'),
                _l('mailbox_guide_gmail_3'),
                _l('mailbox_guide_gmail_4'),
            ],
        ],
        'outlook' => [
            'label' => 'Outlook / Microsoft 365',
            'icon'  => 'fa-brands fa-microsoft',
            'smtp'  => ['host' => 'smtp.office365.com', 'port' => 587, 'encryption' => 'tls'],
            'imap'  => ['host' => 'outlook.office365.com', 'port' => 993, 'encryption' => 'ssl'],
            'sent_folder' => 'Sent Items',
            'app_password_url' => 'https://account.live.com/proofs/AppPassword',
            'steps' => [
                _l('mailbox_guide_outlook_1'),
                _l('mailbox_guide_outlook_2'),
                _l('mailbox_guide_outlook_3'),
            ],
        ],
        'zoho' => [
            'label' => 'Zoho Mail',
            'icon'  => 'fa-solid fa-z',
            'smtp'  => ['host' => 'smtp.zoho.com', 'port' => 465, 'encryption' => 'ssl'],
            'imap'  => ['host' => 'imap.zoho.com', 'port' => 993, 'encryption' => 'ssl'],
            'sent_folder' => 'Sent',
            'app_password_url' => 'https://accounts.zoho.com/home#security/app_password',
            'steps' => [
                _l('mailbox_guide_zoho_1'),
                _l('mailbox_guide_zoho_2'),
                _l('mailbox_guide_zoho_3'),
            ],
        ],
        'yahoo' => [
            'label' => 'Yahoo Mail',
            'icon'  => 'fa-brands fa-yahoo',
            'smtp'  => ['host' => 'smtp.mail.yahoo.com', 'port' => 465, 'encryption' => 'ssl'],
            'imap'  => ['host' => 'imap.mail.yahoo.com', 'port' => 993, 'encryption' => 'ssl'],
            'sent_folder' => 'Sent',
            'app_password_url' => 'https://login.yahoo.com/myaccount/security/app-password',
            'steps' => [
                _l('mailbox_guide_yahoo_1'),
                _l('mailbox_guide_yahoo_2'),
            ],
        ],
        'titan' => [
            'label' => 'Titan (GoDaddy / Hostinger)',
            'icon'  => 'fa-solid fa-envelope-circle-check',
            'smtp'  => ['host' => 'smtp.titan.email', 'port' => 465, 'encryption' => 'ssl'],
            'imap'  => ['host' => 'imap.titan.email', 'port' => 993, 'encryption' => 'ssl'],
            'sent_folder' => 'Sent',
            'app_password_url' => '',
            'steps' => [
                _l('mailbox_guide_titan_1'),
                _l('mailbox_guide_titan_2'),
            ],
        ],
        'custom' => [
            'label' => _l('mailbox_provider_custom'),
            'icon'  => 'fa-solid fa-server',
            'smtp'  => ['host' => '', 'port' => 465, 'encryption' => 'ssl'],
            'imap'  => ['host' => '', 'port' => 993, 'encryption' => 'ssl'],
            'sent_folder' => 'Sent',
            'app_password_url' => '',
            'steps' => [
                _l('mailbox_guide_custom_1'),
                _l('mailbox_guide_custom_2'),
                _l('mailbox_guide_custom_3'),
            ],
        ],
    ];
}

/* ───────────────────────────── Send engine ──────────────────────────────── */

/**
 * Send an email through the account's own SMTP server via PHPMailer.
 *
 * $data keys: to[], cc[], bcc[], reply_to, subject, body_html,
 *             attachments[] (each: ['path' =>, 'name' =>]),
 *             in_reply_to, references (threading headers).
 *
 * @return array ['success' => bool, 'message_id' => string, 'error' => string, 'raw' => string]
 */
function mailbox_send_mail($account, array $data)
{
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = $account->smtp_host;
        $mail->Port       = (int) $account->smtp_port;
        $mail->SMTPAuth   = true;
        $mail->Username   = $account->smtp_username !== '' ? $account->smtp_username : $account->email;
        $mail->Password   = mailbox_decrypt($account->smtp_password);
        $mail->CharSet    = PHPMailer\PHPMailer\PHPMailer::CHARSET_UTF8;
        $mail->Timeout    = 20;

        if ($account->smtp_encryption === 'ssl') {
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($account->smtp_encryption === 'tls') {
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->SMTPSecure = '';
            $mail->SMTPAutoTLS = false;
        }

        $mail->setFrom($account->email, $account->from_name !== '' ? $account->from_name : $account->name);

        foreach ($data['to'] as $addr) {
            $mail->addAddress($addr);
        }
        foreach (($data['cc'] ?? []) as $addr) {
            $mail->addCC($addr);
        }
        foreach (($data['bcc'] ?? []) as $addr) {
            $mail->addBCC($addr);
        }

        // Compliance archive: every outgoing message gets a silent copy when
        // the company configured an archive address. Recipients never see it,
        // and it is deliberately applied here — the one place all outbound
        // mail (composer, scheduled send, rules, auto-replies) funnels through.
        if (function_exists('mailbox_compliance_bcc')) {
            $archive = mailbox_compliance_bcc();
            if ($archive !== '' && mb_strtolower($archive) !== mb_strtolower((string) $account->email)) {
                $mail->addBCC($archive);
            }
        }

        if (!empty($data['reply_to'])) {
            $mail->addReplyTo($data['reply_to']);
        }

        // Machine-generated mail (out-of-office, rule forwards) must announce
        // itself so the other side's responder does not answer back.
        if (!empty($data['auto_submitted'])) {
            $mail->addCustomHeader('Auto-Submitted', 'auto-replied');
            $mail->addCustomHeader('X-Auto-Response-Suppress', 'All');
            $mail->addCustomHeader('Precedence', 'auto_reply');
        }

        // Threading headers so replies stay in the conversation on the
        // recipient's side too.
        if (!empty($data['in_reply_to'])) {
            $mail->addCustomHeader('In-Reply-To', '<' . trim($data['in_reply_to'], '<>') . '>');
        }
        if (!empty($data['references'])) {
            $mail->addCustomHeader('References', $data['references']);
        }

        $mail->Subject = $data['subject'];
        $mail->isHTML(true);
        $mail->Body    = $data['body_html'];
        $mail->AltBody = trim(strip_tags(preg_replace('#<br\s*/?>#i', "\n", $data['body_html'])));

        foreach (($data['attachments'] ?? []) as $att) {
            if (is_file($att['path'])) {
                $mail->addAttachment($att['path'], $att['name']);
            }
        }

        $mail->send();

        return [
            'success'    => true,
            'message_id' => trim($mail->getLastMessageID(), '<>'),
            'raw'        => $mail->getSentMIMEMessage(),
            'error'      => '',
        ];
    } catch (Exception $e) {
        return [
            'success'    => false,
            'message_id' => '',
            'raw'        => '',
            'error'      => $mail->ErrorInfo !== '' ? $mail->ErrorInfo : $e->getMessage(),
        ];
    }
}

/**
 * Live SMTP credential check (connect + authenticate, nothing sent).
 *
 * Runs staged probes (DNS → TCP → TLS → SMTP auth) so a failure pinpoints
 * the exact layer — a blocked outbound port, a non-TLS port answering, or
 * bad credentials all produce the same generic PHPMailer message otherwise.
 *
 * @return array ['success' => bool, 'error' => string, 'debug' => string[]]
 */
function mailbox_test_smtp($account)
{
    $host  = trim((string) $account->smtp_host);
    $port  = (int) $account->smtp_port;
    $enc   = $account->smtp_encryption;
    $debug = [];

    // Stage 1: DNS resolution.
    if ($host === '') {
        return ['success' => false, 'error' => 'SMTP host is empty.', 'debug' => $debug];
    }
    $ip = gethostbyname($host);
    if ($ip === $host && !filter_var($host, FILTER_VALIDATE_IP)) {
        $debug[] = "DNS: cannot resolve \"{$host}\" from this server";

        return ['success' => false, 'error' => "DNS lookup failed — \"{$host}\" does not resolve from this server.", 'debug' => $debug];
    }
    $debug[] = "DNS: {$host} → {$ip}";

    // Stage 2: raw TCP reachability (independent of TLS and credentials).
    $errno  = 0;
    $errstr = '';
    $start  = microtime(true);
    $sock   = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, 10);
    $ms     = (int) round((microtime(true) - $start) * 1000);
    if (!$sock) {
        $detail  = $errstr !== '' ? "[{$errno}] {$errstr}" : 'no response (timeout)';
        $debug[] = "TCP: connect to {$host}:{$port} FAILED after {$ms}ms — {$detail}";
        $error   = $errstr === '' || stripos($errstr, 'timed out') !== false
            ? "TCP connection to {$host}:{$port} timed out — outbound port {$port} is almost certainly blocked by this server's firewall/hosting provider (IMAP works because port 993 is not blocked). Ask the host to open outbound SMTP, or use its local relay."
            : "TCP connection to {$host}:{$port} failed: {$detail}";

        return ['success' => false, 'error' => $error, 'debug' => $debug];
    }
    $debug[] = "TCP: connect to {$host}:{$port} OK ({$ms}ms)";
    stream_set_timeout($sock, 10);

    // Stage 3: what the port actually speaks (implicit TLS vs plaintext banner).
    if ($enc === 'ssl') {
        $crypto = @stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        if ($crypto !== true) {
            $last    = error_get_last();
            $debug[] = 'TLS: SSL handshake FAILED — '
                . ($last ? $last['message'] : 'no OpenSSL response (handshake hung; data likely dropped by a firewall)');
            fclose($sock);

            // Second opinion: a real implicit-SSL port sends NOTHING before the
            // handshake. If a plaintext banner answers on a fresh connection,
            // either the port is a STARTTLS port (banner names the real host)
            // or a firewall redirected us to its own mail server (banner names
            // someone else) — the wording below tells the two apart.
            $probe = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, 8);
            $plain = '';
            if ($probe) {
                stream_set_timeout($probe, 8);
                $line = fgets($probe, 512);
                fclose($probe);
                $plain   = $line !== false ? trim($line) : '';
                $debug[] = "Plaintext probe on port {$port}: " . ($plain !== '' ? $plain : 'connection accepted but silent');
            } else {
                $debug[] = "Plaintext probe on port {$port}: connect failed";
            }

            if ($plain !== '' && stripos($plain, $host) !== false) {
                $error = "Port {$port} answers in plaintext (STARTTLS), not implicit SSL — switch Encryption to TLS for this port, or use the provider's SSL port.";
            } elseif ($plain !== '') {
                $error = "Outbound SMTP on port {$port} is being INTERCEPTED — \"{$plain}\" answered instead of {$host}. The hosting firewall redirects outbound SMTP to its own mail server (cPanel \"SMTP Restrictions\" / CSF SMTP_BLOCK). Ask the host to exempt this server or disable the restriction.";
            } else {
                $error = "Port {$port} accepts the TCP connection but the SSL handshake gets no reply — a firewall is accepting the connection and silently dropping the data. Outbound SMTP is blocked at the hosting level; ask the provider to open outbound port {$port}.";
            }

            return ['success' => false, 'error' => $error, 'debug' => $debug];
        }
        $debug[] = 'TLS: implicit SSL handshake OK';
    }
    $banner  = fgets($sock, 512);
    $debug[] = 'Banner: ' . ($banner !== false ? trim($banner) : '(none received)');
    fclose($sock);

    // Stage 4: full PHPMailer connect + authenticate, transcript captured.
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host        = $host;
        $mail->Port        = $port;
        $mail->SMTPAuth    = true;
        $mail->Username    = $account->smtp_username !== '' ? $account->smtp_username : $account->email;
        $mail->Password    = mailbox_decrypt($account->smtp_password);
        $mail->Timeout     = 15;
        $mail->SMTPDebug = PHPMailer\PHPMailer\SMTP::DEBUG_CONNECTION;
        // AUTH LOGIN sends username+password base64-encoded on the two
        // CLIENT -> SERVER lines that follow; AUTH PLAIN/XOAUTH2 inline them.
        $redact            = 0;
        $mail->Debugoutput = function ($str, $level) use (&$debug, &$redact) {
            $line = trim($str);
            if ($redact > 0 && stripos($line, 'CLIENT -> SERVER:') !== false) {
                $line = 'CLIENT -> SERVER: ******** (credential redacted)';
                $redact--;
            } elseif (preg_match('/AUTH (PLAIN|XOAUTH2)/i', $line)) {
                $line = preg_replace('/(AUTH (?:PLAIN|XOAUTH2))\s+\S+/i', '$1 ********', $line);
            }
            if (stripos($line, 'AUTH LOGIN') !== false) {
                $redact = 2;
            }
            $debug[] = $line;
        };

        if ($enc === 'ssl') {
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($enc === 'tls') {
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->SMTPSecure  = '';
            $mail->SMTPAutoTLS = false;
        }

        if (!$mail->smtpConnect()) {
            return ['success' => false, 'error' => $mail->ErrorInfo ?: 'SMTP connect failed', 'debug' => $debug];
        }
        $mail->smtpClose();

        return ['success' => true, 'error' => '', 'debug' => $debug];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $mail->ErrorInfo !== '' ? $mail->ErrorInfo : $e->getMessage(), 'debug' => $debug];
    }
}

/**
 * Live IMAP credential check.
 *
 * @return array ['success' => bool, 'error' => string, 'folders' => array]
 */
function mailbox_test_imap($account)
{
    if (!function_exists('imap_open')) {
        return ['success' => false, 'error' => _l('mailbox_imap_ext_missing'), 'folders' => []];
    }

    try {
        $imap = new app\services\imap\Imap(
            $account->imap_username !== '' ? $account->imap_username : $account->email,
            mailbox_decrypt($account->imap_password),
            $account->imap_host,
            $account->imap_encryption,
            (string) $account->imap_port,
            (bool) $account->imap_validate_cert
        );

        $folders = $imap->getSelectableFolders();

        return ['success' => true, 'error' => '', 'folders' => array_values($folders)];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage(), 'folders' => []];
    }
}

/* ───────────────────────────── Sync engine ──────────────────────────────── */

/**
 * Pull new mail for one account over IMAP. Incremental by UID: the first run
 * imports the last N days, every later run only fetches UIDs above the stored
 * high-water mark.
 *
 * @param  object $account  full row including encrypted credentials
 * @param  bool   $force    ignore the per-account throttle
 * @return array ['imported' => int, 'error' => string]
 */
function mailbox_sync_account($account, $force = false)
{
    $CI = &get_instance();
    $p  = db_prefix();

    if (!function_exists('imap_open')) {
        return ['imported' => 0, 'error' => _l('mailbox_imap_ext_missing')];
    }
    if ($account->imap_host === '' || !$account->active) {
        return ['imported' => 0, 'error' => ''];
    }

    // Per-account throttle so webmail polling can call this freely.
    $interval = max(60, (int) get_option('mailbox_sync_interval'));
    if (!$force && $account->last_sync_at && (time() - strtotime($account->last_sync_at)) < $interval) {
        return ['imported' => 0, 'error' => ''];
    }

    // Claim the sync slot first so parallel requests don't double-import.
    $CI->db->where('id', $account->id)->update($p . 'mailbox_accounts', [
        'last_sync_at' => date('Y-m-d H:i:s'),
    ]);

    $imported = 0;

    try {
        $imap = new app\services\imap\Imap(
            $account->imap_username !== '' ? $account->imap_username : $account->email,
            mailbox_decrypt($account->imap_password),
            $account->imap_host,
            $account->imap_encryption,
            (string) $account->imap_port,
            (bool) $account->imap_validate_cert
        );

        $connection = $imap->createConnection();
        $mailbox    = $connection->getMailbox($account->imap_folder !== '' ? $account->imap_folder : 'INBOX');
        $batch      = max(5, (int) get_option('mailbox_sync_batch'));

        if (!$account->initial_synced) {
            // First run: last N days, newest last so the UID watermark ends
            // correct even when the batch cap kicks in.
            $days     = max(1, (int) get_option('mailbox_initial_days'));
            $since    = new DateTimeImmutable('-' . $days . ' days');
            $messages = $mailbox->getMessages(
                new Ddeboer\Imap\Search\Date\Since($since),
                SORTDATE,
                false
            );
        } else {
            $messages = $mailbox->getMessageSequence(($account->last_uid + 1) . ':*');
        }

        $lastUid = (int) $account->last_uid;

        foreach ($messages as $message) {
            $uid = (int) $message->getNumber();

            // "N:*" always matches at least the newest message, even when its
            // UID is below N — skip anything already imported.
            if ($uid <= $lastUid && $account->initial_synced) {
                continue;
            }

            if (mailbox_import_message($account, $message, $uid)) {
                $imported++;
            }

            $lastUid = max($lastUid, $uid);

            if ($imported >= $batch) {
                break;
            }
        }

        $CI->db->where('id', $account->id)->update($p . 'mailbox_accounts', [
            'last_uid'        => $lastUid,
            'initial_synced'  => 1,
            'imap_ok'         => 1,
            'last_sync_error' => null,
        ]);

        return ['imported' => $imported, 'error' => ''];
    } catch (Exception $e) {
        $CI->db->where('id', $account->id)->update($p . 'mailbox_accounts', [
            'last_sync_error' => $e->getMessage(),
        ]);

        return ['imported' => $imported, 'error' => $e->getMessage()];
    }
}

/**
 * Normalize a mail header date to the CRM's timezone.
 *
 * A Date: header carries the SENDER's UTC offset, and DateTime::format() keeps
 * that wall clock — so a mail sent 11:45 +05:30 by a server writing UTC lands
 * as 06:15. Everything else in the CRM (App_Model sets the configured timezone
 * as PHP's default) is stored in CRM local time, which is why only mailbox
 * timestamps looked wrong. Convert on the absolute instant, so senders in any
 * timezone all come out right.
 *
 * @param  mixed $date  DateTimeInterface from the IMAP message, or null
 * @return string       'Y-m-d H:i:s' in the CRM timezone
 */
function mailbox_local_datetime($date)
{
    if (!$date instanceof DateTimeInterface) {
        return date('Y-m-d H:i:s');
    }

    try {
        $local = new DateTime('@' . $date->getTimestamp());   // UTC instant
        $local->setTimezone(new DateTimeZone(date_default_timezone_get()));

        return $local->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        return $date->format('Y-m-d H:i:s');
    }
}

/**
 * Store one IMAP message (and its attachments) locally. Duplicate-safe via
 * (account, uid) and Message-ID checks.
 *
 * @return bool  true when a new row was inserted
 */
function mailbox_import_message($account, $message, $uid)
{
    $CI = &get_instance();
    $p  = db_prefix();

    $exists = $CI->db->query(
        "SELECT id FROM `{$p}mailbox_messages` WHERE account_id = ? AND uid = ? AND uid > 0 LIMIT 1",
        [$account->id, $uid]
    )->row();
    if ($exists) {
        return false;
    }

    try {
        $messageId = (string) $message->getId();

        if ($messageId !== '') {
            $dupe = $CI->db->query(
                "SELECT id FROM `{$p}mailbox_messages` WHERE account_id = ? AND message_id = ? LIMIT 1",
                [$account->id, $messageId]
            )->row();
            if ($dupe) {
                return false;
            }
        }

        $from      = $message->getFrom();
        $bodyHtml  = $message->getBodyHtml();
        $bodyPlain = $message->getBodyText();
        $date      = $message->getDate();
        $inReplyTo = $message->getInReplyTo();
        $refs      = $message->getReferences();

        // A message sent from this very account (e.g. via another client)
        // showing up in INBOX subfolders is still inbox here; only the
        // configured folder is synced so folder is always 'inbox'.
        $row = [
            'account_id'      => $account->id,
            'folder'          => 'inbox',
            'uid'             => $uid,
            'message_id'      => mb_substr($messageId, 0, 255),
            'in_reply_to'     => mb_substr(trim(implode(' ', (array) $inReplyTo), '<>'), 0, 255),
            'refs'            => implode(' ', (array) $refs),
            'subject'         => mb_substr((string) $message->getSubject(), 0, 500),
            'from_name'       => $from ? mb_substr((string) $from->getName(), 0, 191) : '',
            'from_email'      => $from ? mb_substr((string) $from->getAddress(), 0, 191) : '',
            'to_emails'       => mailbox_addresses_to_json($message->getTo()),
            'cc_emails'       => mailbox_addresses_to_json($message->getCc()),
            'bcc_emails'      => '[]',
            'reply_to'        => mailbox_first_address($message->getReplyTo()),
            'body_html'       => $bodyHtml,
            'body_plain'      => $bodyPlain,
            'snippet'         => mailbox_snippet($bodyPlain !== null && $bodyPlain !== '' ? $bodyPlain : (string) $bodyHtml),
            'has_attachments' => 0,
            'is_read'         => $message->isSeen() ? 1 : 0,
            'is_starred'      => 0,
            'sent_by'         => 0,
            'message_date'    => mailbox_local_datetime($date),
            'created_at'      => date('Y-m-d H:i:s'),
        ];

        $row['thread_id'] = mailbox_resolve_thread_id($account->id, $row['in_reply_to'], $row['refs'], $row['subject']);

        $CI->db->insert($p . 'mailbox_messages', $row);
        $localId = $CI->db->insert_id();

        if (!$row['thread_id']) {
            $CI->db->where('id', $localId)->update($p . 'mailbox_messages', ['thread_id' => $localId]);
        }

        // Attachments — stored under uploads/mailbox/{account}/{message}/.
        $attachments = $message->getAttachments();
        if (count($attachments)) {
            $saved = 0;
            foreach ($attachments as $attachment) {
                $filename = (string) $attachment->getFilename();
                if ($filename === '') {
                    $filename = 'attachment-' . ($saved + 1);
                }
                $content = $attachment->getDecodedContent();
                if ($content === null || $content === '') {
                    continue;
                }
                if (mailbox_store_attachment($account->id, $localId, $filename, $content)) {
                    $saved++;
                }
            }
            if ($saved > 0) {
                $CI->db->where('id', $localId)->update($p . 'mailbox_messages', ['has_attachments' => 1]);
                $row['has_attachments'] = 1;
            }
        }

        // Corporate automation runs on the stored row (rules see attachments
        // too, which is why this sits after the attachment loop).
        if (function_exists('mailbox_after_import')) {
            mailbox_after_import($account, $localId, $row);
        }

        return true;
    } catch (Exception $e) {
        log_activity('Mailbox import error (account ' . $account->id . ', uid ' . $uid . '): ' . $e->getMessage());

        return false;
    }
}

/**
 * Cron hook — sync every active account, globally throttled.
 */
function mailbox_run_cron_sync()
{
    if (!function_exists('imap_open') || !mailbox_ensure_schema()) {
        return;
    }

    $interval = max(60, (int) get_option('mailbox_sync_interval'));
    if (time() - (int) get_option('mailbox_cron_last_run') < $interval) {
        return;
    }
    update_option('mailbox_cron_last_run', time());

    $CI = &get_instance();
    $p  = db_prefix();

    $accounts = $CI->db->query(
        "SELECT * FROM `{$p}mailbox_accounts` WHERE active = 1 AND imap_host != ''"
    )->result();

    foreach ($accounts as $account) {
        mailbox_sync_account($account);
    }
}

/* ────────────────────────────── Utilities ───────────────────────────────── */

/**
 * uploads/mailbox/{account}/{message}/ — created on demand, indexed off.
 */
function mailbox_upload_dir($account_id, $message_id)
{
    $base = FCPATH . 'uploads' . DIRECTORY_SEPARATOR . 'mailbox';
    $dir  = $base . DIRECTORY_SEPARATOR . (int) $account_id . DIRECTORY_SEPARATOR . (int) $message_id;

    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
        if (!is_file($base . DIRECTORY_SEPARATOR . 'index.html')) {
            @file_put_contents($base . DIRECTORY_SEPARATOR . 'index.html', '');
        }
    }

    return $dir . DIRECTORY_SEPARATOR;
}

/**
 * Persist one attachment blob + DB row. Files get a random stored name; the
 * original name only lives in the DB and is applied on download.
 */
function mailbox_store_attachment($account_id, $message_id, $file_name, $content)
{
    $CI = &get_instance();
    $p  = db_prefix();

    $ext    = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    $ext    = preg_replace('/[^a-z0-9]/', '', $ext);
    $stored = bin2hex(random_bytes(16)) . ($ext !== '' ? '.' . $ext : '');
    $dir    = mailbox_upload_dir($account_id, $message_id);

    if (@file_put_contents($dir . $stored, $content) === false) {
        return false;
    }

    $CI->db->insert($p . 'mailbox_attachments', [
        'message_id'  => $message_id,
        'file_name'   => mb_substr($file_name, 0, 255),
        'stored_name' => $stored,
        'mime'        => '',
        'size'        => strlen($content),
        'created_at'  => date('Y-m-d H:i:s'),
    ]);

    return true;
}

/**
 * Thread resolution: an In-Reply-To / References hit wins; otherwise a
 * normalized-subject match on the same account keeps loose clients threaded.
 *
 * @return int  thread id, or 0 when the message starts a new thread
 */
function mailbox_resolve_thread_id($account_id, $in_reply_to, $refs, $subject)
{
    $CI = &get_instance();
    $p  = db_prefix();

    $candidates = [];
    if ($in_reply_to !== '') {
        $candidates[] = $in_reply_to;
    }
    foreach (preg_split('/\s+/', (string) $refs, -1, PREG_SPLIT_NO_EMPTY) as $ref) {
        $candidates[] = trim($ref, '<>');
    }
    $candidates = array_values(array_unique(array_filter($candidates)));

    if (count($candidates)) {
        $in  = implode(',', array_fill(0, count($candidates), '?'));
        $row = $CI->db->query(
            "SELECT thread_id, id FROM `{$p}mailbox_messages`
             WHERE account_id = ? AND message_id IN ($in) LIMIT 1",
            array_merge([$account_id], $candidates)
        )->row();
        if ($row) {
            return (int) ($row->thread_id ?: $row->id);
        }
    }

    $normalized = mailbox_normalize_subject($subject);
    if ($normalized !== '' && $normalized !== mb_strtolower(trim((string) $subject))) {
        // Only join by subject when the subject actually carried a Re:/Fwd:
        // prefix — plain identical subjects must not merge unrelated mail.
        $row = $CI->db->query(
            "SELECT thread_id, id FROM `{$p}mailbox_messages`
             WHERE account_id = ? AND LOWER(TRIM(subject)) IN (?, ?)
             ORDER BY id DESC LIMIT 1",
            [$account_id, $normalized, 're: ' . $normalized]
        )->row();
        if ($row) {
            return (int) ($row->thread_id ?: $row->id);
        }
    }

    return 0;
}

function mailbox_normalize_subject($subject)
{
    $subject = mb_strtolower(trim((string) $subject));
    while (preg_match('/^(re|fw|fwd|aw|sv)\s*(\[\d+\])?\s*:\s*/i', $subject)) {
        $subject = preg_replace('/^(re|fw|fwd|aw|sv)\s*(\[\d+\])?\s*:\s*/i', '', $subject);
    }

    return trim($subject);
}

/**
 * ddeboer EmailAddress[] → JSON [{name, email}, ...] for storage.
 */
function mailbox_addresses_to_json($addresses)
{
    $out = [];
    foreach ((array) $addresses as $address) {
        $out[] = [
            'name'  => (string) $address->getName(),
            'email' => (string) $address->getAddress(),
        ];
    }

    return json_encode($out, JSON_UNESCAPED_UNICODE);
}

function mailbox_first_address($addresses)
{
    foreach ((array) $addresses as $address) {
        return mb_substr((string) $address->getAddress(), 0, 191);
    }

    return '';
}

/**
 * Plain-text preview for list rows.
 */
function mailbox_snippet($text)
{
    $text = strip_tags(preg_replace('#<(style|script)[^>]*>.*?</\1>#si', ' ', (string) $text));
    $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode($text, ENT_QUOTES, 'UTF-8')));

    return mb_substr($text, 0, 200);
}

/**
 * Comma / semicolon separated address input → clean unique email array.
 */
function mailbox_parse_address_input($input)
{
    $out = [];
    foreach (preg_split('/[,;]+/', (string) $input, -1, PREG_SPLIT_NO_EMPTY) as $part) {
        $email = trim($part);
        // Allow "Name <email>" form.
        if (preg_match('/<([^>]+)>/', $email, $m)) {
            $email = trim($m[1]);
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $out[] = mb_strtolower($email);
        }
    }

    return array_values(array_unique($out));
}

/**
 * Best-effort copy of an outgoing message into the account's IMAP sent
 * folder so other clients see it too. Never fatal.
 */
function mailbox_append_to_sent($account, $rawMime)
{
    if (!function_exists('imap_open') || $account->imap_host === '' || $account->imap_sent_folder === '' || $rawMime === '') {
        return;
    }

    try {
        $imap = new app\services\imap\Imap(
            $account->imap_username !== '' ? $account->imap_username : $account->email,
            mailbox_decrypt($account->imap_password),
            $account->imap_host,
            $account->imap_encryption,
            (string) $account->imap_port,
            (bool) $account->imap_validate_cert
        );
        $connection = $imap->createConnection();
        $connection->getMailbox($account->imap_sent_folder)->addMessage($rawMime, '\\Seen');
    } catch (Exception $e) {
        // Sent copy is cosmetic — log and move on.
        log_activity('Mailbox sent-folder append failed (account ' . $account->id . '): ' . $e->getMessage());
    }
}
