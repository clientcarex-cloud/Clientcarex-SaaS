<?php

defined('BASEPATH') or exit('No direct script access allowed');

/* ══════════════════════════════════════════════════════════════════════
   CRM E-MAIL ROUTING

   Every e-mail Perfex sends — mail templates, an invoice with its PDF, a
   ticket reply, a password reset, staff notifications, anything drained
   from the mail queue — leaves through App_Email::send(). While Omni
   Messaging is active that single choke point is handed to this file, so
   a CRM e-mail travels the exact same road as a hook, campaign or
   auto-scheduler send:

       balance gate → Email API sender → deliver → credit → send log

   The message itself is never rebuilt here: recipients, subject, body,
   attachments, cc/bcc and the header/footer wrap core already applied are
   left untouched, and only the transport (SMTP host / credentials /
   sender) is swapped for the Email channel's own. That is what keeps
   invoice PDFs and every merge field intact.

   Nothing in here fires for sends that Omni Messaging bills elsewhere —
   Ccx_msgs_api_model::send_smtp_email() raises an in-flight flag, so hook,
   campaign, scheduler and OTP e-mails are billed once, by their own
   engine, not twice.
   ══════════════════════════════════════════════════════════════════════ */

/**
 * hook_key written to tblccx_hook_trigger_logs for a routed CRM e-mail.
 * Not a registered hook — the logs UI labels it explicitly.
 */
define('SMS_WA_EMAIL_CRM_HOOK_KEY', 'crm_email');

/**
 * Option reader with a real default: core get_option() answers '' — never
 * false — for a name that was never added, so `?:` and `=== false` both
 * misread "never configured" (see the get_option empty-string gotcha).
 *
 * @param  string $name
 * @param  string $default
 * @return string
 */
function sms_wa_email_crm_option($name, $default = '1')
{
    $value = get_option($name);

    return ($value === '' || $value === null) ? $default : (string) $value;
}

/**
 * Master switch: route CRM e-mail through Omni Messaging.
 * Defaults ON — activating the module is the opt-in.
 */
function sms_wa_email_crm_email_enabled()
{
    return sms_wa_email_crm_option('sms_wa_email_route_crm_email', '1') === '1';
}

/**
 * Is there anything to route WITH?
 *
 * An installation that has neither a message allocation row nor a default
 * Email API has no credit ledger and no sender to swap in — billing every
 * password reset against a ledger that does not exist would just block the
 * mail. Routing stays inert until one of the two exists.
 *
 * @return array{ok:bool,allocation:object|null,api:object|null}
 */
function sms_wa_email_crm_email_context()
{
    $allocation = function_exists('_ccx_get_allocation') ? _ccx_get_allocation() : null;
    $api        = function_exists('_ccx_get_default_api') ? _ccx_get_default_api('email', 'transactional') : null;

    return [
        'ok'         => ($allocation || $api),
        'allocation' => $allocation,
        'api'        => $api,
    ];
}

/**
 * Remember which core mail template is being sent, so the send log names
 * it instead of showing every CRM e-mail as an anonymous "system" mail.
 * Consumed (and cleared) by the dispatcher below.
 *
 * Filter: before_email_template_send — returns its payload untouched.
 *
 * @param  array $hook_data
 * @return array
 */
function sms_wa_email_crm_capture_template_source($hook_data)
{
    if (isset($hook_data['template'])) {
        $template = $hook_data['template'];
        $source   = '';

        if (is_object($template)) {
            $source = !empty($template->slug) ? $template->slug : (!empty($template->name) ? $template->name : '');
        }

        if ($source !== '') {
            $GLOBALS['ccx_omni_crm_email_source'] = $source;
        }
    }

    return $hook_data;
}

/**
 * Take the pending source label, clearing it so the next e-mail in the
 * same request cannot inherit it.
 *
 * @return string
 */
function sms_wa_email_crm_take_source()
{
    $source = isset($GLOBALS['ccx_omni_crm_email_source']) ? (string) $GLOBALS['ccx_omni_crm_email_source'] : '';
    unset($GLOBALS['ccx_omni_crm_email_source']);

    return $source;
}

/**
 * The dispatcher App_Email::send() hands every outgoing CRM e-mail to.
 *
 * @param  object   $mailer   the App_Email instance holding the built message
 * @param  callable $deliver  performs the real send (parent::send())
 * @return bool               what App_Email::send() must return
 */
function sms_wa_email_crm_email_dispatch($mailer, $deliver)
{
    // A send Omni Messaging is already billing (hook / campaign / scheduler
    // / OTP / API test) — it must pass through untouched and uncharged.
    if (!empty($GLOBALS['ccx_omni_email_inflight'])) {
        return (bool) call_user_func($deliver);
    }

    if (!sms_wa_email_crm_email_enabled()) {
        return (bool) call_user_func($deliver);
    }

    if (!method_exists($mailer, 'ccx_envelope')) {
        return (bool) call_user_func($deliver);
    }

    $envelope = $mailer->ccx_envelope();
    $source   = sms_wa_email_crm_take_source();

    // No recipient: let core fail it in its own way, with its own error.
    if (empty($envelope['to'])) {
        return (bool) call_user_func($deliver);
    }

    $context = sms_wa_email_crm_email_context();
    if (!$context['ok']) {
        return (bool) call_user_func($deliver);
    }

    $recipients = implode(', ', $envelope['to']);
    $preview    = sms_wa_email_crm_preview($envelope, $source);
    $units      = max(1, count($envelope['to']));

    // ── Balance gate ─────────────────────────────────────────────────
    $balance = function_exists('_ccx_check_channel_balance')
        ? _ccx_check_channel_balance('email', 'trans')
        : true;

    if ($balance !== true) {
        $block = sms_wa_email_crm_option('sms_wa_email_crm_email_block_no_balance', '1') === '1';

        if ($block) {
            sms_wa_email_crm_log(
                isset($balance['status']) ? $balance['status'] : 'insufficient_balance',
                $recipients,
                $preview,
                isset($balance['error']) ? $balance['error'] : 'Email balance unavailable'
            );

            return false;
        }

        // Admin chose delivery over billing: send on the CRM's own settings
        // and log it as unbilled rather than dropping the mail.
        $sent = (bool) call_user_func($deliver);
        sms_wa_email_crm_log(
            $sent ? 'success' : 'failed',
            $recipients,
            $preview,
            'Sent without billing — ' . (isset($balance['error']) ? $balance['error'] : 'no balance')
        );

        return $sent;
    }

    // ── Transport: the Email channel's own sender ────────────────────
    $api      = $context['api'];
    $snapshot = null;
    if ($api && sms_wa_email_crm_option('sms_wa_email_crm_email_use_api_smtp', '1') === '1') {
        $snapshot = sms_wa_email_crm_apply_transport($mailer, $api, $envelope);
    }

    // ── Deliver ──────────────────────────────────────────────────────
    $started = microtime(true);
    $GLOBALS['ccx_omni_email_inflight'] = true;

    try {
        $sent = (bool) call_user_func($deliver);
    } catch (\Throwable $e) {
        $sent  = false;
        $error = 'PHP error during send: ' . $e->getMessage() . ' in ' . basename($e->getFile()) . ':' . $e->getLine();
    } finally {
        unset($GLOBALS['ccx_omni_email_inflight']);
    }

    if (!isset($error)) {
        $error = $sent ? null : sms_wa_email_crm_mailer_error($mailer);
    }

    // The message survives a failed send (core only clears on success), so
    // a dead relay can be retried on the installation's own SMTP instead of
    // taking password resets down with it.
    $fallback_used = false;
    if (!$sent
        && $snapshot
        && sms_wa_email_crm_option('sms_wa_email_crm_email_fallback_smtp', '1') === '1') {
        sms_wa_email_crm_restore_transport($mailer, $snapshot);
        $snapshot = null;

        $GLOBALS['ccx_omni_email_inflight'] = true;
        try {
            $sent = (bool) call_user_func($deliver);
        } catch (\Throwable $e) {
            $sent = false;
        } finally {
            unset($GLOBALS['ccx_omni_email_inflight']);
        }

        $fallback_used = true;
        $error         = 'Email API sender failed (' . $error . ') → retried on this installation\'s own SMTP: '
            . ($sent ? 'delivered' : 'failed again');
    }

    if ($snapshot) {
        sms_wa_email_crm_restore_transport($mailer, $snapshot);
    }

    // ── Credit + log ─────────────────────────────────────────────────
    if ($sent && function_exists('_ccx_decrement_channel_balance')) {
        for ($i = 0; $i < $units; $i++) {
            _ccx_decrement_channel_balance('email', 'trans');
        }
    }

    sms_wa_email_crm_log(
        $sent ? 'success' : 'failed',
        $recipients,
        $preview,
        $error
    );

    // Mirror onto the master's API log so a provider can see tenant sends
    // next to every other call made through that Email API.
    if ($api && !empty($api->id) && function_exists('_ccx_log_to_master_api_logs')) {
        _ccx_log_to_master_api_logs($api, [
            'status'            => $sent ? 'success' : 'failed',
            'response_code'     => $sent ? 200 : 0,
            'response_body'     => $sent
                ? ('CRM email sent to ' . $recipients . ($fallback_used ? ' (via installation SMTP fallback)' : ''))
                : (string) $error,
            'execution_time_ms' => (int) round((microtime(true) - $started) * 1000),
            'request_url'       => 'SMTP → ' . $recipients,
            'request_payload'   => json_encode([
                'to'      => $envelope['to'],
                'subject' => $envelope['subject'],
                'source'  => $source !== '' ? $source : 'system',
            ]),
        ], $recipients, sms_wa_email_crm_staff_id());
    }

    return $sent;
}

/**
 * Swap the mailer's transport for the Email API's own SMTP sender, keeping
 * the built message (recipients, subject, body, attachments) as it is.
 *
 * The mailer engine is deliberately NOT switched: set_mailer_engine()
 * clears the message, which would wipe the very e-mail being sent. Both
 * engines speak SMTP, so only the connection settings change.
 *
 * @param  object $mailer
 * @param  object $api
 * @param  array  $envelope
 * @return array|null  snapshot to hand to sms_wa_email_crm_restore_transport(), or null when nothing was changed
 */
function sms_wa_email_crm_apply_transport($mailer, $api, $envelope = [])
{
    // "Use CRM Email Settings" APIs send on this installation's own SMTP —
    // which is exactly what the mailer is already configured with.
    if (!empty($api->use_crm_smtp)) {
        return null;
    }

    $host = isset($api->smtp_host) ? trim((string) $api->smtp_host) : '';
    if ($host === '') {
        return null;
    }

    $CI = &get_instance();

    $snapshot = [
        'protocol'     => $mailer->protocol,
        'smtp_host'    => $mailer->smtp_host,
        'smtp_port'    => $mailer->smtp_port,
        'smtp_user'    => $mailer->smtp_user,
        'smtp_pass'    => $mailer->smtp_pass,
        'smtp_crypto'  => $mailer->smtp_crypto,
        'smtp_timeout' => $mailer->smtp_timeout,
    ];

    $port = isset($api->smtp_port) ? (int) $api->smtp_port : 587;
    if ($port <= 0) {
        $port = 587;
    }

    $username = isset($api->smtp_username) ? trim((string) $api->smtp_username) : '';
    if ($username === '') {
        $username = isset($api->smtp_from_email) ? trim((string) $api->smtp_from_email) : '';
    }

    $password = isset($api->smtp_password) ? (string) $api->smtp_password : '';
    if ($password !== '') {
        $decrypted = $CI->encryption->decrypt($password);
        if ($decrypted !== false) {
            $password = $decrypted;
        }
    }

    // The port disambiguates the TLS mode: 465 is implicit TLS, and STARTTLS
    // ports cannot do an implicit-TLS handshake (mirrors Ccx_msgs_api_model).
    $encryption = isset($api->smtp_encryption) ? strtolower((string) $api->smtp_encryption) : '';
    if ($port === 465 && $encryption !== 'ssl') {
        $encryption = 'ssl';
    } elseif (in_array($port, [587, 25], true) && $encryption === 'ssl') {
        $encryption = 'tls';
    }

    $mailer->set_protocol('smtp');
    $mailer->set_smtp_host($host);
    $mailer->set_smtp_port($port);
    $mailer->set_smtp_user($username);
    $mailer->set_smtp_pass($password);
    $mailer->set_smtp_crypto($encryption);
    $mailer->set_smtp_timeout(15);

    // A CRM whose own mail runs on Gmail/Microsoft OAuth leaves the shared
    // PHPMailer instance locked to XOAUTH2 — plain credentials would be
    // offered with the wrong mechanism.
    if (is_object($mailer->phpmailer) && $mailer->phpmailer->AuthType === 'XOAUTH2') {
        $snapshot['auth_type']       = 'XOAUTH2';
        $mailer->phpmailer->AuthType = '';
    }

    // A relay only accepts envelopes it is authorised for, so the sender
    // becomes the API's — with the CRM's original address kept as Reply-To
    // so answers still land in the tenant's inbox.
    $from_email = isset($api->smtp_from_email) ? trim((string) $api->smtp_from_email) : '';
    if ($from_email === '') {
        $from_email = $username;
    }

    if ($from_email !== '') {
        $original_from = isset($envelope['from']) ? $envelope['from'] : '';
        $from_name     = isset($envelope['from_name']) && $envelope['from_name'] !== ''
            ? $envelope['from_name']
            : get_option('companyname');

        // Kept so a fallback retry goes back out under the CRM's own address —
        // the installation's SMTP would not be authorised for the API's.
        if ($original_from !== '') {
            $snapshot['from']      = $original_from;
            $snapshot['from_name'] = $from_name;
        }

        $mailer->from($from_email, $from_name);

        if ($original_from !== ''
            && strcasecmp($original_from, $from_email) !== 0
            && empty($envelope['reply_to'])
            && sms_wa_email_crm_option('sms_wa_email_crm_email_reply_to_original', '1') === '1') {
            $mailer->reply_to($original_from, $from_name);
        }
    }

    return $snapshot;
}

/**
 * Put back whatever sms_wa_email_crm_apply_transport() changed — the mailer
 * is a shared singleton, so the next e-mail in this request must find the
 * installation's own settings.
 *
 * @param  object $mailer
 * @param  array  $snapshot
 * @return void
 */
function sms_wa_email_crm_restore_transport($mailer, $snapshot)
{
    if (empty($snapshot)) {
        return;
    }

    $mailer->set_protocol($snapshot['protocol']);
    $mailer->set_smtp_host($snapshot['smtp_host']);
    $mailer->set_smtp_port($snapshot['smtp_port']);
    $mailer->set_smtp_user($snapshot['smtp_user']);
    $mailer->set_smtp_pass($snapshot['smtp_pass']);
    $mailer->set_smtp_crypto($snapshot['smtp_crypto']);
    $mailer->set_smtp_timeout($snapshot['smtp_timeout']);

    if (!empty($snapshot['auth_type']) && is_object($mailer->phpmailer)) {
        $mailer->phpmailer->AuthType = $snapshot['auth_type'];
    }

    if (!empty($snapshot['from'])) {
        $mailer->from($snapshot['from'], isset($snapshot['from_name']) ? $snapshot['from_name'] : '');
    }
}

/**
 * One-line log preview: which core e-mail it was, its subject, and the
 * opening of the body with the HTML taken off.
 *
 * @param  array  $envelope
 * @param  string $source
 * @return string
 */
function sms_wa_email_crm_preview($envelope, $source = '')
{
    $subject = isset($envelope['subject']) ? trim((string) $envelope['subject']) : '';
    $body    = isset($envelope['body']) ? (string) $envelope['body'] : '';

    $body = trim(preg_replace('/\s+/', ' ', strip_tags($body)));

    $preview = '';
    if ($source !== '') {
        $preview .= '[' . $source . '] ';
    }
    $preview .= $subject !== '' ? $subject : '(no subject)';
    if ($body !== '') {
        $preview .= ' — ' . $body;
    }

    return mb_substr($preview, 0, 500);
}

/**
 * Staff member behind the send, when there is one — cron runs, gateway
 * callbacks and the customer area have none.
 *
 * @return int
 */
function sms_wa_email_crm_staff_id()
{
    if (function_exists('is_staff_logged_in') && is_staff_logged_in()) {
        return (int) get_staff_user_id();
    }

    return 0;
}

/**
 * Best available reason for a failed send, for the log's error column.
 *
 * @param  object $mailer
 * @return string
 */
function sms_wa_email_crm_mailer_error($mailer)
{
    $error = '';

    try {
        if (is_object($mailer->phpmailer) && !empty($mailer->phpmailer->ErrorInfo)) {
            $error = (string) $mailer->phpmailer->ErrorInfo;
        }
    } catch (\Throwable $e) {
        $error = '';
    }

    if ($error === '' && method_exists($mailer, 'print_debugger')) {
        $error = trim(strip_tags($mailer->print_debugger(['headers'])));
    }

    return $error !== '' ? mb_substr($error, 0, 2000) : 'Email sending failed.';
}

/**
 * Write the send log this module's Logs viewer reads.
 *
 * @param  string      $status
 * @param  string      $recipients
 * @param  string      $preview
 * @param  string|null $error
 * @return void
 */
function sms_wa_email_crm_log($status, $recipients, $preview, $error = null)
{
    if (!function_exists('_ccx_log_hook_trigger')) {
        return;
    }

    _ccx_log_hook_trigger(
        SMS_WA_EMAIL_CRM_HOOK_KEY,
        'email',
        null,
        mb_substr($recipients, 0, 255),
        $preview,
        $status,
        $error,
        sms_wa_email_crm_staff_id(),
        'crm'
    );
}

/**
 * Counters for the CRM Routing panel.
 *
 * Hook trigger logs are purged after 48 hours by cron, so "total" means
 * "as far back as the log still goes" — same as every other counter on
 * this page.
 *
 * @return array{today:int,today_failed:int,total:int,blocked:int}
 */
function sms_wa_email_crm_email_stats()
{
    $CI    = &get_instance();
    $table = db_prefix() . 'ccx_hook_trigger_logs';

    $stats = ['today' => 0, 'today_failed' => 0, 'total' => 0, 'blocked' => 0];

    if (!$CI->db->table_exists($table) || !$CI->db->field_exists('send_type', $table)) {
        return $stats;
    }

    $row = $CI->db->select("
            COUNT(*) AS total,
            SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) AS today,
            SUM(CASE WHEN DATE(created_at) = CURDATE() AND status = 'failed' THEN 1 ELSE 0 END) AS today_failed,
            SUM(CASE WHEN status NOT IN ('success', 'failed') THEN 1 ELSE 0 END) AS blocked", false)
        ->where('send_type', 'crm')
        ->where('channel', 'email')
        ->get($table)
        ->row();

    if ($row) {
        $stats['today']        = (int) $row->today;
        $stats['today_failed'] = (int) $row->today_failed;
        $stats['total']        = (int) $row->total;
        $stats['blocked']      = (int) $row->blocked;
    }

    return $stats;
}
