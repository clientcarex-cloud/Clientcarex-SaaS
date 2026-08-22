<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * WhatsApp (Official Cloud API) — helper functions.
 *
 * CENTRAL (SaaS) MODEL
 * --------------------
 * One Meta App is owned by the SaaS provider and configured once in the MASTER
 * account (App ID / Secret / Embedded-Signup Config ID / verify token).
 * Tenants never create a developer account — they just click "Connect with
 * Facebook". App credentials live in the master options table and are read
 * cross-database by tenants, mirroring the `meta` / `meta_automation` modules.
 *
 * The connected WABAs + tokens live in a MASTER registry
 * (tblwhatsapp_connections / tblwhatsapp_numbers) keyed by tenant slug, so the
 * single central webhook can route each event (by phone_number_id) to the
 * right tenant. Messages, templates, campaigns and bot rules stay in each
 * TENANT database.
 *
 * On a non-SaaS single install everything resolves to the local DB and the
 * install behaves as one self-owned app.
 */

/* ───────────────────────────── Context ──────────────────────────────────── */

if (!function_exists('whatsapp_saas_available')) {
    function whatsapp_saas_available()
    {
        return function_exists('perfex_saas_is_tenant');
    }
}

if (!function_exists('whatsapp_is_tenant')) {
    /** True only when running inside a SaaS tenant. */
    function whatsapp_is_tenant()
    {
        return whatsapp_saas_available() && perfex_saas_is_tenant();
    }
}

if (!function_exists('whatsapp_is_master')) {
    /** Master account, or any non-SaaS single instance. */
    function whatsapp_is_master()
    {
        return !whatsapp_is_tenant();
    }
}

if (!function_exists('whatsapp_current_slug')) {
    /** Slug of the active tenant, or 'master'. */
    function whatsapp_current_slug()
    {
        if (whatsapp_is_tenant() && function_exists('perfex_saas_tenant_slug')) {
            return perfex_saas_tenant_slug() ?: 'master';
        }
        return 'master';
    }
}

/* ─────────────────────── Central app configuration ──────────────────────── */

if (!function_exists('whatsapp_central_option_names')) {
    function whatsapp_central_option_names()
    {
        return [
            'whatsapp_app_id', 'whatsapp_app_secret', 'whatsapp_config_id',
            'whatsapp_verify_token', 'whatsapp_webhook_url', 'whatsapp_oauth_callback_url',
            // Billing model — tenants need to know whether the provider pays.
            'whatsapp_share_credit_line', 'whatsapp_credit_currency',
        ];
    }
}

if (!function_exists('whatsapp_central_config')) {
    /**
     * The provider's central app config. Read locally on the master, or
     * cross-database from the master options on a tenant.
     *
     * @return array{app_id:string, app_secret:string, config_id:string, verify_token:string, webhook_url:string, oauth_callback_url:string}
     */
    function whatsapp_central_config($fresh = false)
    {
        static $cfg = null;
        if ($fresh) {
            $cfg = null;
        }
        if ($cfg !== null) {
            return $cfg;
        }

        $raw = [];
        if (whatsapp_is_tenant()) {
            $raw = whatsapp_read_master_options(whatsapp_central_option_names());
        } else {
            $raw = whatsapp_read_local_options(whatsapp_central_option_names());
        }

        $cfg = [
            'app_id'             => (string) ($raw['whatsapp_app_id'] ?? ''),
            'app_secret'         => (string) ($raw['whatsapp_app_secret'] ?? ''),
            'config_id'          => (string) ($raw['whatsapp_config_id'] ?? ''),
            'verify_token'       => (string) ($raw['whatsapp_verify_token'] ?? ''),
            'webhook_url'        => (string) ($raw['whatsapp_webhook_url'] ?? ''),
            'oauth_callback_url' => (string) ($raw['whatsapp_oauth_callback_url'] ?? ''),
            'share_credit_line'  => (string) ($raw['whatsapp_share_credit_line'] ?? '') === '1',
            'credit_currency'    => (string) ($raw['whatsapp_credit_currency'] ?? '') ?: 'USD',
        ];
        return $cfg;
    }
}

if (!function_exists('whatsapp_central_config_reset')) {
    /**
     * Drop the per-request copy of the central config.
     *
     * Anything that WRITES the credentials must call this, otherwise the rest of
     * the request keeps answering with the app that was configured when the page
     * started — which is exactly how a freshly saved App ID goes on looking like
     * the old one.
     */
    function whatsapp_central_config_reset()
    {
        whatsapp_central_config(true);
    }
}

/* ─────────────────── Provider billing (shared credit line) ──────────────── */

if (!function_exists('whatsapp_provider_billing')) {
    /**
     * Provider-side billing credentials. MASTER CONTEXT ONLY — the system user
     * token is never exposed to tenants, and every credit-sharing call happens
     * on the master (the OAuth callback already runs there).
     *
     * @return array{business_id:string, system_token:string, credit_line_id:string, currency:string, enabled:bool}
     */
    function whatsapp_provider_billing()
    {
        if (!whatsapp_is_master()) {
            return ['business_id' => '', 'system_token' => '', 'credit_line_id' => '', 'currency' => 'USD', 'enabled' => false];
        }
        return [
            'business_id'    => (string) get_option('whatsapp_business_id'),
            'system_token'   => (string) get_option('whatsapp_system_user_token'),
            'credit_line_id' => (string) get_option('whatsapp_credit_line_id'),
            'currency'       => (string) get_option('whatsapp_credit_currency') ?: 'USD',
            'enabled'        => (string) get_option('whatsapp_share_credit_line') === '1',
        ];
    }
}

if (!function_exists('whatsapp_credit_sharing_ready')) {
    /** True when the provider can actually pay for a tenant's messaging. */
    function whatsapp_credit_sharing_ready()
    {
        $b = whatsapp_provider_billing();
        return $b['enabled'] && $b['system_token'] !== '' && $b['credit_line_id'] !== '';
    }
}

if (!function_exists('whatsapp_credit_status_badge')) {
    function whatsapp_credit_status_badge($status)
    {
        $map = [
            'shared'   => ['#16a34a', 'Provider billed'],
            'pending'  => ['#f59e0b', 'Pending'],
            'failed'   => ['#ef4444', 'Sharing failed'],
            'self'     => ['#6b7280', 'Tenant billed'],
        ];
        $s = (string) $status;
        if (!isset($map[$s])) {
            return whatsapp_badge('#6b7280', 'Tenant billed');
        }
        return whatsapp_badge($map[$s][0], $map[$s][1]);
    }
}

if (!function_exists('whatsapp_read_local_options')) {
    /**
     * Read options straight from this account's options table.
     *
     * NOT get_option(): every one of these is seeded with autoload = 1, so core
     * answers them from a copy taken when the request booted. Save new
     * credentials and anything that re-reads them in the SAME request — the
     * resync below, a diagnostics run, an OAuth start — is handed the app that
     * was configured a moment ago. Reading the row is the only way to be sure
     * the console is describing what is actually stored.
     *
     * @param string[] $names
     * @return array<string,string>
     */
    function whatsapp_read_local_options($names)
    {
        $CI     = &get_instance();
        $values = array_fill_keys($names, '');

        $rows = $CI->db->select('name, value')
            ->where_in('name', $names)
            ->get(db_prefix() . 'options')
            ->result();

        foreach ($rows as $row) {
            $values[$row->name] = (string) $row->value;
        }
        return $values;
    }
}

if (!function_exists('whatsapp_read_master_options')) {
    /**
     * Read option values from the master DB (tenant context).
     *
     * @param string[] $names
     * @return array<string,string>
     */
    function whatsapp_read_master_options($names)
    {
        $values = [];
        if (!function_exists('perfex_saas_raw_query_row') || !function_exists('perfex_saas_master_db_prefix')) {
            return $values;
        }
        $prefix = perfex_saas_master_db_prefix();
        foreach ($names as $name) {
            $row = perfex_saas_raw_query_row(
                "SELECT value FROM {$prefix}options WHERE name = ? LIMIT 1",
                [], true, true, [$name]
            );
            $values[$name] = ($row && isset($row->value)) ? $row->value : '';
        }
        return $values;
    }
}

if (!function_exists('whatsapp_is_configured')) {
    /** Provider has set App ID + Secret (centrally). */
    function whatsapp_is_configured()
    {
        $c = whatsapp_central_config();
        return $c['app_id'] !== '' && $c['app_secret'] !== '';
    }
}

if (!function_exists('whatsapp_redirect_uri')) {
    /** Fixed OAuth redirect (on the master domain) registered in the Meta App. */
    function whatsapp_redirect_uri()
    {
        $c = whatsapp_central_config();
        if ($c['oauth_callback_url'] !== '') {
            return $c['oauth_callback_url'];
        }
        return admin_url('whatsapp/oauth_callback'); // master / single-instance fallback
    }
}

if (!function_exists('whatsapp_webhook_url')) {
    /** Fixed webhook callback (on the master domain) registered in the Meta App. */
    function whatsapp_webhook_url()
    {
        $c = whatsapp_central_config();
        if ($c['webhook_url'] !== '') {
            return $c['webhook_url'];
        }
        return admin_url('whatsapp/webhook');
    }
}

/* ───────────────────────────── Graph API ───────────────────────────────── */

if (!function_exists('whatsapp_graph_base')) {
    function whatsapp_graph_base()
    {
        return 'https://graph.facebook.com/' . WHATSAPP_GRAPH_VERSION . '/';
    }
}

if (!function_exists('whatsapp_login_url')) {
    /**
     * Facebook OAuth dialog URL (Facebook Login for Business). Uses the central
     * app + the fixed master redirect. When the provider has created an
     * Embedded-Signup configuration, its config_id drives the WhatsApp signup
     * flow; otherwise plain scopes are requested.
     *
     * $state is the signed tenant token (see whatsapp_make_state()).
     */
    function whatsapp_login_url($state)
    {
        $c = whatsapp_central_config();
        $params = [
            'client_id'     => $c['app_id'],
            'redirect_uri'  => whatsapp_redirect_uri(),
            'state'         => $state,
            'response_type' => 'code',
        ];
        if ($c['config_id'] !== '') {
            $params['config_id'] = $c['config_id'];
            $params['override_default_response_type'] = 'true';
        } else {
            $params['scope'] = WHATSAPP_OAUTH_SCOPES;
        }
        return 'https://www.facebook.com/' . WHATSAPP_GRAPH_VERSION . '/dialog/oauth?' . http_build_query($params);
    }
}

if (!function_exists('whatsapp_graph_get')) {
    function whatsapp_graph_get($path, $params = [], $token = null)
    {
        $url = whatsapp_graph_base() . ltrim($path, '/');
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        $headers = $token ? ['Authorization: Bearer ' . $token] : [];
        return whatsapp_http_request($url, 'GET', [], $headers);
    }
}

if (!function_exists('whatsapp_graph_post')) {
    /** Form-encoded POST (OAuth / subscribed_apps style endpoints). */
    function whatsapp_graph_post($path, $params = [], $token = null)
    {
        $headers = $token ? ['Authorization: Bearer ' . $token] : [];
        return whatsapp_http_request(whatsapp_graph_base() . ltrim($path, '/'), 'POST', $params, $headers);
    }
}

if (!function_exists('whatsapp_graph_post_json')) {
    /** JSON POST — the Cloud API /{phone_number_id}/messages shape. */
    function whatsapp_graph_post_json($path, $payload, $token)
    {
        return whatsapp_http_request(
            whatsapp_graph_base() . ltrim($path, '/'),
            'POST',
            json_encode($payload),
            ['Content-Type: application/json', 'Authorization: Bearer ' . $token]
        );
    }
}

if (!function_exists('whatsapp_graph_delete')) {
    function whatsapp_graph_delete($path, $params = [], $token = null)
    {
        $url = whatsapp_graph_base() . ltrim($path, '/');
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        $headers = $token ? ['Authorization: Bearer ' . $token] : [];
        return whatsapp_http_request($url, 'DELETE', [], $headers);
    }
}

if (!function_exists('whatsapp_http_request')) {
    /** Low-level cURL wrapper. Returns decoded array or ['error'=>msg]. */
    function whatsapp_http_request($url, $method = 'GET', $body = [], $headers = [])
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($body) ? http_build_query($body) : $body);
        } elseif ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        }
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        $response = curl_exec($ch);
        $err      = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return ['error' => $err ?: 'Request failed'];
        }
        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            return ['error' => 'Invalid response from Meta'];
        }
        if (isset($decoded['error'])) {
            $e       = $decoded['error'];
            $message = $e['error_user_title'] ?? ($e['message'] ?? 'Graph API error');
            $details = $e['error_data']['details'] ?? ($e['error_user_msg'] ?? '');
            if ($details !== '' && stripos($message, $details) === false) {
                $message .= ' — ' . $details;
            }
            return [
                'error'         => $message,
                'error_code'    => isset($e['code']) ? (string) $e['code'] : '',
                'error_subcode' => isset($e['error_subcode']) ? (string) $e['error_subcode'] : '',
                'raw'           => $decoded,
            ];
        }
        return $decoded;
    }
}

if (!function_exists('whatsapp_error_hint')) {
    /**
     * Translate a Cloud API error into a plain-English cause + the fix, and the
     * UI action that resolves it.
     *
     * @param  string|int $code    Meta error code (e.g. 133010)
     * @param  string     $message raw message, used to sniff the code when absent
     * @return array{title:string,hint:string,action:string}|null
     */
    function whatsapp_error_hint($code, $message = '')
    {
        $code = trim((string) $code);
        // Meta prefixes the message with "(#133010) …" — recover the code when
        // only the text survived (older log rows).
        if ($code === '' && preg_match('/\(#(\d+)\)/', (string) $message, $m)) {
            $code = $m[1];
        }
        if ($code === '') {
            return null;
        }

        $map = [
            // ── Registration / number state ──
            '133010' => ['Number not registered on the Cloud API',
                'This phone number belongs to the WhatsApp Business Account but has never been registered for Cloud API messaging. Register it with a 6-digit PIN to start sending.', 'register'],
            '133005' => ['Two-step verification PIN mismatch',
                'This number already has a two-step verification PIN. Enter that exact PIN, or reset it in WhatsApp Manager → Phone numbers → Two-step verification.', 'register'],
            '133006' => ['Number not verified',
                'Complete phone-number verification in WhatsApp Manager (Meta sends an SMS/voice code) before registering it.', 'verify'],
            '133008' => ['Too many registration attempts',
                'Meta rate-limited registration for this number. Wait a few minutes before trying again.', 'wait'],
            '133009' => ['PIN entered too quickly',
                'Meta throttles rapid PIN attempts. Wait about 5 minutes and try again.', 'wait'],
            '133015' => ['Number is being deregistered',
                'The number is mid-deregistration. Wait about 5 minutes, then register it again.', 'wait'],
            '133016' => ['Number recently deleted',
                'This number was deleted from a WABA very recently. Wait about 5 minutes before registering it again.', 'wait'],
            '133000' => ['Deregistration failed',
                'The number could not be deregistered. Retry in a few minutes.', 'wait'],
            '131031' => ['Account restricted',
                'The WhatsApp Business Account is locked or restricted, usually after a policy violation. Check Account Quality in WhatsApp Manager.', 'quality'],
            '131042' => ['Billing not set up',
                'No valid payment method is attached to the business account. Add one in WhatsApp Manager → Billing & payments.', 'billing'],
            '131045' => ['Certificate not registered',
                'The number needs registering with its display-name certificate. Re-register the number.', 'register'],

            // ── Token / permissions ──
            '190' => ['Access token expired or invalid',
                'The Facebook token stored for this connection is no longer valid. Reconnect the WhatsApp account.', 'reconnect'],
            '102' => ['Session expired',
                'The login session behind this token ended. Reconnect the WhatsApp account.', 'reconnect'],
            '200' => ['Missing permission',
                'The token lacks whatsapp_business_messaging / whatsapp_business_management. Reconnect and grant every permission on the Facebook screen.', 'reconnect'],
            '10'  => ['Permission not granted',
                'The app does not have permission for this action. Reconnect and approve all requested permissions.', 'reconnect'],
            '3'   => ['App capability missing',
                'The Meta App lacks the required capability. Check Advanced Access for the WhatsApp permissions.', 'reconnect'],

            // ── Delivery ──
            '131030' => ['Recipient not in the allowed list',
                'The Meta App is still in development mode, so only numbers added to the test recipient list can receive messages. Switch the app to Live.', 'live'],
            '131047' => ['24-hour window closed',
                'The customer has not messaged you in the last 24 hours, so only an approved template can be sent.', 'template'],
            '131026' => ['Message undeliverable',
                'The recipient number is not on WhatsApp, cannot receive the message, or the number format is wrong (country code missing).', 'number'],
            '131051' => ['Unsupported message type',
                'This message type is not supported on the Cloud API.', ''],
            '131052' => ['Media download failed',
                'Meta could not download the media URL. Make sure it is publicly reachable over HTTPS.', ''],
            '131053' => ['Media upload failed',
                'The media file could not be uploaded — check the file size and format.', ''],
            '131056' => ['Pair rate limit',
                'Too many messages sent between this number and the recipient in a short time. Slow the send rate.', 'wait'],
            '130429' => ['Cloud API rate limit',
                'The number exceeded its throughput limit. Reduce the batch size or spread the campaign out.', 'wait'],
            '130472' => ['User excluded from the experiment',
                'Meta withheld this marketing message as part of its user-experience limits. Nothing to fix.', ''],
            '131048' => ['Spam rate limit',
                'Messaging is limited because of low quality signals from recipients. Improve template quality and reduce volume.', 'quality'],
            '131049' => ['Marketing message limit',
                'Meta limits marketing templates per user per day. Retry later or use a utility template.', 'wait'],
            '80007'  => ['Rate limit reached',
                'The WABA hit its request rate limit. Retry after a short pause.', 'wait'],
            '368'    => ['Temporarily blocked for policy violations',
                'The account is temporarily blocked. Review Account Quality in WhatsApp Manager.', 'quality'],

            // ── Templates ──
            '132000' => ['Template parameter count mismatch',
                'The number of variables sent does not match the approved template. Re-sync templates and check the variable list.', 'template'],
            '132001' => ['Template does not exist',
                'No approved template with that name exists in that language. Re-sync templates or pick the right language.', 'template'],
            '132005' => ['Translated text too long',
                'The resolved template text exceeds the allowed length. Shorten the variable values.', 'template'],
            '132007' => ['Template format policy violation',
                'The variable values break the template formatting rules (e.g. newlines, tabs, or 4+ consecutive spaces).', 'template'],
            '132012' => ['Parameter format mismatch',
                'A variable value does not match the format of the example approved with the template.', 'template'],
            '132015' => ['Template paused',
                'Meta paused this template because of poor quality feedback. Use another template.', 'template'],
            '132016' => ['Template disabled',
                'This template was disabled for quality reasons and can no longer be sent.', 'template'],
            '132068' => ['Flow is blocked', 'The WhatsApp Flow attached to this template is blocked.', 'template'],
            '133004' => ['Meta server temporarily unavailable',
                'WhatsApp servers are busy or under maintenance. Retry shortly.', 'wait'],

            // ── Generic ──
            '100' => ['Invalid parameter',
                'Meta rejected one of the request parameters. Check the recipient number format and template variables.', ''],
            '0'   => ['Authentication failed', 'Meta could not authenticate the request. Reconnect the account.', 'reconnect'],
            '1'   => ['Unknown API error', 'Meta returned an unspecified error. Retry shortly.', 'wait'],
            '2'   => ['Temporary Meta outage', 'A temporary problem on Meta’s side. Retry shortly.', 'wait'],
            '4'   => ['App rate limit', 'The Meta App hit its API call limit. Retry later.', 'wait'],
            '33'  => ['Object not found',
                'The phone number or WABA id is not visible to this token. Reconnect the account.', 'reconnect'],
        ];

        if (!isset($map[$code])) {
            return null;
        }
        return ['title' => $map[$code][0], 'hint' => $map[$code][1], 'action' => $map[$code][2]];
    }
}

if (!function_exists('whatsapp_number_status_meta')) {
    /**
     * Presentation metadata for a registry number's live Cloud API state.
     *
     * @param  object $n row from whatsapp_numbers
     * @return array{key:string,color:string,label:string,detail:string}
     */
    function whatsapp_number_status_meta($n)
    {
        $status = strtoupper((string) ($n->status ?? ''));

        $map = [
            'CONNECTED'    => ['#16a34a', 'Active',          'Registered on the Cloud API and able to send.'],
            'PENDING'      => ['#f59e0b', 'Pending',         'Registration is still being processed by Meta.'],
            'UNVERIFIED'   => ['#f59e0b', 'Unverified',      'The number has not completed Meta’s phone verification.'],
            'FLAGGED'      => ['#f59e0b', 'Flagged',         'Quality dropped to low — messaging limits may be reduced.'],
            'RATE_LIMITED' => ['#f59e0b', 'Rate limited',    'The number is temporarily throttled by Meta.'],
            'RESTRICTED'   => ['#ef4444', 'Restricted',      'The number hit its messaging limit or a policy restriction.'],
            'DISCONNECTED' => ['#ef4444', 'Disconnected',    'The number is no longer connected to the Cloud API.'],
            'BANNED'       => ['#ef4444', 'Banned',          'Meta banned this number for policy violations.'],
            'DELETED'      => ['#ef4444', 'Deleted',         'The number was removed from the WhatsApp Business Account.'],
            'MIGRATED'     => ['#0ea5e9', 'Migrated',        'The number moved to another WhatsApp Business Account.'],
            'UNKNOWN'      => ['#6b7280', 'Unknown',         'Meta did not report a state for this number.'],
        ];

        if ($status === '') {
            return ['key' => 'unchecked', 'color' => '#6b7280', 'label' => 'Not checked',
                    'detail' => 'Run a health check to pull this number’s live state from Meta.'];
        }
        if (!isset($map[$status])) {
            return ['key' => strtolower($status), 'color' => '#6b7280',
                    'label' => ucfirst(strtolower(str_replace('_', ' ', $status))), 'detail' => ''];
        }
        // Anything other than CONNECTED means the number cannot send right now.
        return ['key' => strtolower($status), 'color' => $map[$status][0],
                'label' => $map[$status][1], 'detail' => $map[$status][2]];
    }
}

if (!function_exists('whatsapp_number_unregistered')) {
    /**
     * True only when Meta has actually told us the number cannot send. A row
     * that has never been health-checked is "unknown", not "broken" — otherwise
     * every number looks unregistered the first time the column exists.
     */
    function whatsapp_number_unregistered($n)
    {
        return !empty($n->last_checked_at) && (int) ($n->is_registered ?? 0) !== 1;
    }
}

if (!function_exists('whatsapp_number_status_badge')) {
    function whatsapp_number_status_badge($n)
    {
        $m = whatsapp_number_status_meta($n);
        $dot = '<span class="wapi-dot" style="background:' . $m['color'] . '"></span>';
        return '<span class="wapi-badge wapi-badge-dot" title="' . e($m['detail']) . '" style="background:' . $m['color']
            . '14;color:' . $m['color'] . ';border:1px solid ' . $m['color'] . '40">' . $dot . e($m['label']) . '</span>';
    }
}

if (!function_exists('whatsapp_quality_badge')) {
    function whatsapp_quality_badge($rating)
    {
        $r = strtoupper((string) $rating);
        $map = [
            'GREEN'  => ['#16a34a', 'High'],
            'YELLOW' => ['#f59e0b', 'Medium'],
            'RED'    => ['#ef4444', 'Low'],
            'UNKNOWN' => ['#6b7280', 'Unknown'],
            'NA'     => ['#6b7280', '—'],
        ];
        if ($r === '' || !isset($map[$r])) {
            return '<span class="wapi-muted">—</span>';
        }
        return whatsapp_badge($map[$r][0], $map[$r][1]);
    }
}

if (!function_exists('whatsapp_health_badge')) {
    /** health_status.can_send_message → AVAILABLE / LIMITED / BLOCKED */
    function whatsapp_health_badge($can_send)
    {
        $v = strtoupper((string) $can_send);
        $map = [
            'AVAILABLE' => ['#16a34a', 'Can send'],
            'LIMITED'   => ['#f59e0b', 'Limited'],
            'BLOCKED'   => ['#ef4444', 'Blocked'],
        ];
        if (!isset($map[$v])) {
            return '<span class="wapi-muted">—</span>';
        }
        return whatsapp_badge($map[$v][0], $map[$v][1]);
    }
}

if (!function_exists('whatsapp_tier_label')) {
    /** MESSAGING_LIMIT_TIER_1K → "1K / 24h" */
    function whatsapp_tier_label($tier)
    {
        $t = strtoupper((string) $tier);
        if ($t === '') {
            return '—';
        }
        $t = str_replace('MESSAGING_LIMIT_TIER_', '', $t);
        if ($t === 'UNLIMITED') {
            return 'Unlimited';
        }
        return $t . ' / 24h';
    }
}

if (!function_exists('whatsapp_http_probe')) {
    /**
     * Raw GET that reports what actually happened on the wire — status code,
     * redirects and the literal body. Used to prove whether Meta's webhook
     * verification can reach this server, which a JSON-decoding helper cannot
     * tell you (an HTML login page or a WAF block is not JSON).
     *
     * @return array{code:int, body:string, effective_url:string, redirected:bool, error:string}
     */
    function whatsapp_http_probe($url, $timeout = 15)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false); // a redirect IS the finding
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'facebookplatform/1.0 (+http://developers.facebook.com)');

        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $eff  = (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL);
        $err  = curl_error($ch);
        curl_close($ch);

        return [
            'code'          => $code,
            'body'          => $body === false ? '' : substr((string) $body, 0, 2000),
            'effective_url' => $eff,
            'redirected'    => $code >= 300 && $code < 400,
            'error'         => $body === false ? ($err ?: 'Request failed') : '',
        ];
    }
}

if (!function_exists('whatsapp_app_token')) {
    /** App access token (app_id|app_secret) — used for app-level subscriptions. */
    function whatsapp_app_token()
    {
        $c = whatsapp_central_config();
        if ($c['app_id'] === '' || $c['app_secret'] === '') {
            return '';
        }
        return $c['app_id'] . '|' . $c['app_secret'];
    }
}

if (!function_exists('whatsapp_webhook_fields')) {
    /** Webhook fields this module needs subscribed on the app. */
    function whatsapp_webhook_fields()
    {
        return ['messages', 'message_template_status_update'];
    }
}

if (!function_exists('whatsapp_fetch_media_binary')) {
    /**
     * Resolve a Cloud API media id to its temporary URL, then download the
     * binary with the Bearer token (the lookaside URL requires it).
     *
     * @return array{body:string, mime:string}|array{error:string}
     */
    function whatsapp_fetch_media_binary($media_id, $token)
    {
        $meta = whatsapp_graph_get($media_id, [], $token);
        if (isset($meta['error'])) {
            return ['error' => $meta['error']];
        }
        if (empty($meta['url'])) {
            return ['error' => 'Media URL unavailable'];
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $meta['url']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token]);
        $body = curl_exec($ch);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($body === false || $body === '') {
            return ['error' => $err ?: 'Media download failed'];
        }
        return ['body' => $body, 'mime' => $meta['mime_type'] ?? 'application/octet-stream'];
    }
}

if (!function_exists('whatsapp_media_kind')) {
    /**
     * Map an uploaded file's MIME type to the Cloud API message type and the
     * size ceiling Meta enforces for it. Returns null when Meta would reject
     * the type outright, so the send fails fast with a clear reason.
     *
     * @return array{kind:string,max:int}|null
     */
    function whatsapp_media_kind($mime)
    {
        $mb  = 1024 * 1024;
        $map = [
            'image/jpeg' => ['image', 5 * $mb],
            'image/png'  => ['image', 5 * $mb],
            'image/webp' => ['sticker', 500 * 1024],
            'video/mp4'  => ['video', 16 * $mb],
            'video/3gpp' => ['video', 16 * $mb],
            'audio/aac'  => ['audio', 16 * $mb],
            'audio/mp4'  => ['audio', 16 * $mb],
            'audio/mpeg' => ['audio', 16 * $mb],
            'audio/amr'  => ['audio', 16 * $mb],
            'audio/ogg'  => ['audio', 16 * $mb],
            'text/plain'      => ['document', 100 * $mb],
            'application/pdf' => ['document', 100 * $mb],
            'application/msword'            => ['document', 100 * $mb],
            'application/vnd.ms-excel'      => ['document', 100 * $mb],
            'application/vnd.ms-powerpoint' => ['document', 100 * $mb],
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'   => ['document', 100 * $mb],
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'         => ['document', 100 * $mb],
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => ['document', 100 * $mb],
        ];
        if (!isset($map[$mime])) {
            return null;
        }
        return ['kind' => $map[$mime][0], 'max' => $map[$mime][1]];
    }
}

if (!function_exists('whatsapp_upload_media_file')) {
    /**
     * Upload a local file to POST /{phone_number_id}/media and return the
     * media id to reference in a message. Multipart only — this endpoint
     * accepts neither JSON nor a raw body, so it cannot go through
     * whatsapp_http_request (which form-encodes arrays).
     *
     * @return array{media_id:string}|array{error:string,error_code?:string}
     */
    function whatsapp_upload_media_file($phone_number_id, $tmp_path, $mime, $filename, $token)
    {
        $ch = curl_init(whatsapp_graph_base() . $phone_number_id . '/media');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token],
            CURLOPT_POSTFIELDS     => [
                'messaging_product' => 'whatsapp',
                'type'              => $mime,
                'file'              => new CURLFile($tmp_path, $mime, $filename !== '' ? $filename : 'file'),
            ],
        ]);
        $response = curl_exec($ch);
        $err      = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return ['error' => $err ?: 'Media upload failed'];
        }
        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            return ['error' => 'Invalid response from Meta'];
        }
        if (isset($decoded['error'])) {
            $e = $decoded['error'];
            return [
                'error'      => $e['error_user_title'] ?? ($e['message'] ?? 'Media upload failed'),
                'error_code' => isset($e['code']) ? (string) $e['code'] : '',
            ];
        }
        if (empty($decoded['id'])) {
            return ['error' => 'Meta did not return a media id.'];
        }
        return ['media_id' => (string) $decoded['id']];
    }
}

/* ──────────────────────── HMAC signing / tokens ─────────────────────────── */

if (!function_exists('whatsapp_verify_signature')) {
    /** Validate Meta's X-Hub-Signature-256 against the raw body + app secret. */
    function whatsapp_verify_signature($raw_body, $signature_header)
    {
        $secret = whatsapp_central_config()['app_secret'];
        if (empty($secret)) {
            return true;
        }
        if (empty($signature_header) || strpos($signature_header, 'sha256=') !== 0) {
            return false;
        }
        return hash_equals('sha256=' . hash_hmac('sha256', $raw_body, $secret), $signature_header);
    }
}

if (!function_exists('whatsapp_make_state')) {
    /**
     * Build a signed OAuth state carrying the tenant identity, so the central
     * master callback knows which tenant connected and where to send them back.
     */
    function whatsapp_make_state($payload)
    {
        $secret = whatsapp_central_config()['app_secret'] ?: 'whatsapp-fallback-secret';
        $payload['ts'] = time();
        $body = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
        $sig  = hash_hmac('sha256', $body, $secret);
        return $body . '.' . $sig;
    }
}

if (!function_exists('whatsapp_read_state')) {
    /** Verify + decode a signed state. Returns array payload or null. */
    function whatsapp_read_state($state)
    {
        if (!is_string($state) || strpos($state, '.') === false) {
            return null;
        }
        list($body, $sig) = explode('.', $state, 2);
        $secret = whatsapp_central_config()['app_secret'] ?: 'whatsapp-fallback-secret';
        if (!hash_equals(hash_hmac('sha256', $body, $secret), $sig)) {
            return null;
        }
        $json = base64_decode(strtr($body, '-_', '+/'));
        $data = json_decode($json, true);
        if (!is_array($data) || empty($data['ts']) || (time() - (int) $data['ts']) > 1800) {
            return null; // expired (30 min)
        }
        return $data;
    }
}

if (!function_exists('whatsapp_sign_payload')) {
    /** HMAC signature for the master→tenant forward (and tenant verification). */
    function whatsapp_sign_payload($raw)
    {
        $secret = whatsapp_central_config()['app_secret'] ?: 'whatsapp-fallback-secret';
        return 'sha256=' . hash_hmac('sha256', $raw, $secret);
    }
}

/* ───────────────────────── Phone normalisation ──────────────────────────── */

if (!function_exists('whatsapp_normalize_phone')) {
    /**
     * Bare international digits, the shape the Cloud API expects in `to`.
     * Normalizes a phone number to international format.
     */
    function whatsapp_normalize_phone($phone, $country_code = null)
    {
        $phone = trim((string) $phone);
        if ($phone === '') {
            return '';
        }
        $digits = preg_replace('/\D+/', '', $phone);
        if ($digits === '') {
            return '';
        }
        // 00-international prefix
        if (strpos($digits, '00') === 0) {
            $digits = substr($digits, 2);
        }
        // National trunk prefix — strip BEFORE the length check, otherwise an
        // 11-digit "0"-prefixed national number is mistaken for international
        // (no country code starts with 0, so this is always safe).
        if (strpos($digits, '0') === 0) {
            $digits = ltrim($digits, '0');
        }
        if ($country_code === null || $country_code === '') {
            $country_code = get_option('whatsapp_default_country_code') ?: '91';
        }
        $country_code = preg_replace('/\D+/', '', (string) $country_code);

        // Already carries a country code (11+ digits) → pass through.
        if (strlen($digits) >= 11) {
            return $digits;
        }
        return $country_code . $digits;
    }
}

if (!function_exists('whatsapp_template_body_text')) {
    /** Extract BODY text from a template's components array. */
    function whatsapp_template_body_text($components)
    {
        foreach ((array) $components as $c) {
            if (strtoupper($c['type'] ?? '') === 'BODY') {
                return (string) ($c['text'] ?? '');
            }
        }
        return '';
    }
}

if (!function_exists('whatsapp_template_variables_count')) {
    /** Count {{n}} placeholders in a template body. */
    function whatsapp_template_variables_count($body_text)
    {
        if (!preg_match_all('/\{\{\s*(\d+)\s*\}\}/', (string) $body_text, $m)) {
            return 0;
        }
        return count(array_unique($m[1]));
    }
}

if (!function_exists('whatsapp_template_variable_indexes')) {
    /** Sorted unique {{n}} indexes used in a piece of template text. */
    function whatsapp_template_variable_indexes($text)
    {
        if (!preg_match_all('/\{\{\s*(\d+)\s*\}\}/', (string) $text, $m)) {
            return [];
        }
        $ix = array_values(array_unique(array_map('intval', $m[1])));
        sort($ix);
        return $ix;
    }
}

if (!function_exists('whatsapp_template_variable_error')) {
    /**
     * The two variable rules that are outright fatal: body variables must be
     * numbered {{1}}..{{n}} with no gaps (otherwise the sample array can never
     * line up), and a text header takes exactly one variable, {{1}}.
     *
     * Meta's softer conventions — no variable at the very start or end of the
     * body, none adjacent — are only warned about in the composer, never
     * blocked here: they are review guidance rather than a hard API rule, and
     * blocking them would make an already-approved template uneditable.
     *
     * @return string empty when the text is acceptable
     */
    function whatsapp_template_variable_error($body_text, $header_text = '')
    {
        $ix = whatsapp_template_variable_indexes($body_text);
        $n  = count($ix);
        if ($n > 0 && $ix !== range(1, $n)) {
            return 'Body variables must run from {{1}} to {{' . $n . '}} with no gaps — renumber them.';
        }

        $hix = whatsapp_template_variable_indexes($header_text);
        if (!empty($hix) && $hix !== [1]) {
            return 'A text header takes at most one variable, and it must be {{1}}.';
        }
        return '';
    }
}

if (!function_exists('whatsapp_template_examples')) {
    /**
     * The sample values Meta reviews a template against — one per {{n}} in the
     * text. What the form supplied wins, then whatever was already approved,
     * and only then a generic placeholder, so an edit that just rewords the
     * body never resets its examples.
     */
    function whatsapp_template_examples($text, $provided = [], $previous = [])
    {
        $count    = count(whatsapp_template_variable_indexes($text));
        $provided = array_values((array) $provided);
        $previous = array_values((array) $previous);

        $out = [];
        for ($i = 0; $i < $count; $i++) {
            $value = isset($provided[$i]) ? trim((string) $provided[$i]) : '';
            if ($value === '') {
                $value = isset($previous[$i]) ? trim((string) $previous[$i]) : '';
            }
            $out[] = $value !== '' ? $value : 'Sample ' . ($i + 1);
        }
        return $out;
    }
}

if (!function_exists('whatsapp_template_has_media_header')) {
    function whatsapp_template_has_media_header($components)
    {
        foreach ((array) $components as $c) {
            if (strtoupper($c['type'] ?? '') === 'HEADER'
                && in_array(strtoupper($c['format'] ?? ''), ['IMAGE', 'VIDEO', 'DOCUMENT'], true)) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('whatsapp_template_components')) {
    /** Decode a cached template row's components column into an array. */
    function whatsapp_template_components($template)
    {
        if (is_array($template)) {
            return $template;
        }
        $raw     = is_object($template) ? ($template->components ?? '') : (string) $template;
        $decoded = json_decode((string) $raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('whatsapp_template_component')) {
    /** First component of $type (HEADER / BODY / FOOTER / BUTTONS), or null. */
    function whatsapp_template_component($components, $type)
    {
        $type = strtoupper($type);
        foreach ((array) $components as $c) {
            if (strtoupper($c['type'] ?? '') === $type) {
                return $c;
            }
        }
        return null;
    }
}

if (!function_exists('whatsapp_template_footer_text')) {
    function whatsapp_template_footer_text($components)
    {
        $c = whatsapp_template_component($components, 'FOOTER');
        return (string) ($c['text'] ?? '');
    }
}

if (!function_exists('whatsapp_template_buttons')) {
    /** Button definitions of a template (empty array when it has none). */
    function whatsapp_template_buttons($components)
    {
        $c = whatsapp_template_component($components, 'BUTTONS');
        return isset($c['buttons']) && is_array($c['buttons']) ? $c['buttons'] : [];
    }
}

if (!function_exists('whatsapp_template_button_type_label')) {
    function whatsapp_template_button_type_label($type)
    {
        $map = [
            'QUICK_REPLY'    => 'Quick reply',
            'URL'            => 'URL',
            'PHONE_NUMBER'   => 'Phone number',
            'COPY_CODE'      => 'Copy code',
            'OTP'            => 'OTP',
            'FLOW'           => 'Flow',
            'CATALOG'        => 'Catalog',
            'MPM'            => 'Multi-product',
        ];
        $t = strtoupper(trim((string) $type));
        return $map[$t] ?? ($t !== '' ? ucfirst(strtolower(str_replace('_', ' ', $t))) : '—');
    }
}

if (!function_exists('whatsapp_template_build_edit_components')) {
    /**
     * Rebuild a template's components for an edit, starting from what Meta
     * already has rather than from scratch.
     *
     * Everything the edit form cannot express survives untouched: a media or
     * location header (whose uploaded sample handle cannot be recreated here)
     * and the entire BUTTONS component. Sample values typed in the form win,
     * falling back to the approved ones, so an edit that only rewords the body
     * does not reset its examples.
     *
     * @param array $existing components as cached from Meta
     * @param array $data     header_text, body_text, footer_text, samples[], header_sample
     */
    function whatsapp_template_build_edit_components($existing, $data)
    {
        $components = [];

        // ── HEADER ──
        $old_header  = whatsapp_template_component($existing, 'HEADER');
        $old_format  = $old_header ? strtoupper($old_header['format'] ?? 'TEXT') : '';
        $header_text = trim((string) ($data['header_text'] ?? ''));
        if ($old_header && $old_format !== 'TEXT') {
            $components[] = $old_header;
        } elseif ($header_text !== '') {
            $header = ['type' => 'HEADER', 'format' => 'TEXT', 'text' => $header_text];
            if (whatsapp_template_variables_count($header_text) > 0) {
                // A text header takes exactly one variable, so exactly one sample.
                $prev              = $old_header['example']['header_text'] ?? [];
                $header['example'] = ['header_text' => whatsapp_template_examples(
                    $header_text,
                    [$data['header_sample'] ?? ''],
                    is_array($prev) ? $prev : []
                )];
            }
            $components[] = $header;
        }

        // ── BODY ──
        $body           = trim((string) ($data['body_text'] ?? ''));
        $body_component = ['type' => 'BODY', 'text' => $body];
        if (whatsapp_template_variables_count($body) > 0) {
            $old_body = whatsapp_template_component($existing, 'BODY');
            $prev     = $old_body['example']['body_text'][0] ?? [];
            $body_component['example'] = ['body_text' => [whatsapp_template_examples(
                $body,
                $data['samples'] ?? [],
                is_array($prev) ? $prev : []
            )]];
        }
        $components[] = $body_component;

        // ── FOOTER ──
        $footer_text = trim((string) ($data['footer_text'] ?? ''));
        if ($footer_text !== '') {
            $components[] = ['type' => 'FOOTER', 'text' => $footer_text];
        }

        // ── BUTTONS ── omitting them would delete them at Meta.
        $old_buttons = whatsapp_template_component($existing, 'BUTTONS');
        if ($old_buttons) {
            $components[] = $old_buttons;
        }

        return $components;
    }
}

if (!function_exists('whatsapp_template_editable')) {
    /**
     * Meta accepts an edit only while the template is APPROVED, REJECTED or
     * PAUSED. One still in review (PENDING) or disabled cannot be changed —
     * see whatsapp_template_edit_block_reason() for the message to show.
     */
    function whatsapp_template_editable($status)
    {
        return in_array(strtoupper((string) $status), ['APPROVED', 'REJECTED', 'PAUSED'], true);
    }
}

if (!function_exists('whatsapp_template_edit_block_reason')) {
    /** Why this template cannot be edited right now (empty when it can). */
    function whatsapp_template_edit_block_reason($status)
    {
        $s = strtoupper((string) $status);
        if (whatsapp_template_editable($s)) {
            return '';
        }
        if ($s === 'PENDING' || $s === 'IN_APPEAL' || $s === 'PENDING_DELETION') {
            return 'This template is still under review at Meta. Wait for the review to finish, then edit it.';
        }
        if ($s === 'DISABLED') {
            return 'Meta has disabled this template — it cannot be edited. Create a new template instead.';
        }
        return 'This template cannot be edited in its current state (' . ($s ?: 'unknown') . '). Sync templates and try again.';
    }
}

if (!function_exists('whatsapp_template_rejected_reason')) {
    /**
     * Human label + fix for Meta's rejected_reason enum, so a rejected
     * template can actually be corrected instead of guessed at.
     */
    function whatsapp_template_rejected_reason($code)
    {
        $c = strtoupper(trim((string) $code));
        $map = [
            'ABUSIVE_CONTENT' => [
                'Abusive content',
                'The wording was read as abusive, threatening or discriminatory. Rewrite the body in neutral, factual language.',
            ],
            'INCORRECT_CATEGORY' => [
                'Incorrect category',
                'The content does not match the category it was submitted under. Switch between Marketing and Utility to match what the message actually does.',
            ],
            'INVALID_FORMAT' => [
                'Invalid format',
                'Formatting is off — usually unbalanced or out-of-order {{1}} {{2}} placeholders, a variable at the very start or end of the body, or missing sample values.',
            ],
            'PROMOTIONAL' => [
                'Promotional content',
                'A Utility template cannot carry promotional wording. Remove the offer/marketing language or resubmit it as Marketing.',
            ],
            'SCAM' => [
                'Flagged as scam',
                'The content resembles a known scam pattern. Drop urgency, prize, payment-request or credential-request wording.',
            ],
            'TAG_CONTENT_MISMATCH' => [
                'Tag / content mismatch',
                'The template tag does not describe the message. Align the category and wording with the actual purpose of the message.',
            ],
            'NONE' => ['No reason given', 'Meta did not return a specific reason. Review the body against WhatsApp\'s template guidelines and resubmit.'],
        ];
        if (isset($map[$c])) {
            return ['label' => $map[$c][0], 'hint' => $map[$c][1]];
        }
        if ($c === '') {
            return ['label' => '', 'hint' => ''];
        }
        return ['label' => ucfirst(strtolower(str_replace('_', ' ', $c))), 'hint' => 'Review the body against WhatsApp\'s template guidelines and resubmit.'];
    }
}

/* ─────────────────────────── View utilities ────────────────────────────── */

if (!function_exists('whatsapp_badge')) {
    function whatsapp_badge($color, $label)
    {
        return '<span class="wapi-badge" style="background:' . $color . '18;color:' . $color . ';border:1px solid ' . $color . '40">' . e($label) . '</span>';
    }
}

if (!function_exists('whatsapp_message_status_badge')) {
    function whatsapp_message_status_badge($status)
    {
        $map = [
            'queued'    => ['#6b7280', 'Queued'],
            'accepted'  => ['#0ea5e9', 'Accepted'],
            'sent'      => ['#0ea5e9', 'Sent'],
            'delivered' => ['#16a34a', 'Delivered'],
            'read'      => ['#7c3aed', 'Read'],
            'failed'    => ['#ef4444', 'Failed'],
            'received'  => ['#16a34a', 'Received'],
        ];
        $info = $map[$status] ?? ['#6b7280', ucfirst((string) $status)];
        return whatsapp_badge($info[0], $info[1]);
    }
}

if (!function_exists('whatsapp_template_status_badge')) {
    function whatsapp_template_status_badge($status)
    {
        $s = strtoupper((string) $status);
        $map = [
            'APPROVED' => ['#16a34a', 'Approved'],
            'PENDING'  => ['#f59e0b', 'Pending'],
            'REJECTED' => ['#ef4444', 'Rejected'],
            'PAUSED'   => ['#f59e0b', 'Paused'],
            'DISABLED' => ['#6b7280', 'Disabled'],
        ];
        $info = $map[$s] ?? ['#6b7280', ucfirst(strtolower($s ?: 'unknown'))];
        return whatsapp_badge($info[0], $info[1]);
    }
}

if (!function_exists('whatsapp_template_quality_badge')) {
    /** Meta's per-template quality score (GREEN / YELLOW / RED / UNKNOWN). */
    function whatsapp_template_quality_badge($score)
    {
        $s = strtoupper((string) $score);
        $map = [
            'GREEN'  => ['#16a34a', 'High'],
            'YELLOW' => ['#f59e0b', 'Medium'],
            'RED'    => ['#ef4444', 'Low'],
        ];
        $info = $map[$s] ?? ['#6b7280', 'Unknown'];
        return whatsapp_badge($info[0], $info[1]);
    }
}

if (!function_exists('whatsapp_format_close_index')) {
    /**
     * Byte offset of the delimiter that closes the one at $open, or -1.
     * WhatsApp only opens a run when real content follows and only closes it on
     * a delimiter that content runs right up to — "* 3 * 4" stays arithmetic.
     */
    function whatsapp_format_close_index($text, $ch, $open)
    {
        $len = strlen($text);
        if ($open + 1 >= $len) {
            return -1;
        }
        $next = $text[$open + 1];
        if ($next === $ch || preg_match('/\s/', $next)) {
            return -1;
        }
        for ($j = $open + 2; $j < $len; $j++) {
            if ($text[$j] === $ch && !preg_match('/\s/', $text[$j - 1])) {
                return $j;
            }
        }
        return -1;
    }
}

if (!function_exists('whatsapp_format_inline')) {
    /**
     * Bold / italic / strikethrough, nested to any depth. Scanned byte-wise, but
     * only ASCII delimiters are ever compared and everything else is buffered
     * whole, so multi-byte text (₹, Devanagari, emoji) survives intact.
     */
    function whatsapp_format_inline($text)
    {
        $tags = ['*' => 'strong', '_' => 'em', '~' => 's'];
        $out  = '';
        $buf  = '';
        $len  = strlen($text);

        for ($i = 0; $i < $len; $i++) {
            $ch    = $text[$i];
            $close = isset($tags[$ch]) ? whatsapp_format_close_index($text, $ch, $i) : -1;
            if ($close > -1) {
                $out .= e($buf) . '<' . $tags[$ch] . '>'
                    . whatsapp_format_inline(substr($text, $i + 1, $close - $i - 1))
                    . '</' . $tags[$ch] . '>';
                $buf = '';
                $i   = $close;
            } else {
                $buf .= $ch;
            }
        }

        return $out . e($buf);
    }
}

if (!function_exists('whatsapp_format_blocks')) {
    /** Line-level formats — a run of like lines becomes one list or quote block. */
    function whatsapp_format_blocks($src)
    {
        $bullet = '/^[ \t]*[*-][ \t]+(.*)$/';
        $number = '/^[ \t]*(\d{1,3})[.)][ \t]+(.*)$/';
        $quote  = '/^[ \t]*>[ \t]?(.*)$/';

        $items = function ($lines) {
            $html = '';
            foreach ($lines as $line) {
                $html .= '<li>' . whatsapp_format_inline($line) . '</li>';
            }
            return $html;
        };

        $lines = explode("\n", $src);
        $n     = count($lines);
        $parts = [];
        $i     = 0;

        while ($i < $n) {
            if (preg_match($bullet, $lines[$i], $m)) {
                $rows = [];
                do {
                    $rows[] = $m[1];
                    $i++;
                } while ($i < $n && preg_match($bullet, $lines[$i], $m));
                $parts[] = [true, '<ul class="wapi-fmt-list">' . $items($rows) . '</ul>'];
            } elseif (preg_match($number, $lines[$i], $m)) {
                $start = (int) $m[1];
                $rows  = [];
                do {
                    $rows[] = $m[2];
                    $i++;
                } while ($i < $n && preg_match($number, $lines[$i], $m));
                $parts[] = [true, '<ol class="wapi-fmt-list" start="' . $start . '">' . $items($rows) . '</ol>'];
            } elseif (preg_match($quote, $lines[$i], $m)) {
                $rows = [];
                do {
                    $rows[] = whatsapp_format_inline($m[1]);
                    $i++;
                } while ($i < $n && preg_match($quote, $lines[$i], $m));
                $parts[] = [true, '<div class="wapi-fmt-quote">' . implode('<br>', $rows) . '</div>'];
            } else {
                $parts[] = [false, whatsapp_format_inline($lines[$i])];
                $i++;
            }
        }

        // The bubble is white-space:pre-wrap, so only keep the newline between two
        // plain lines — a block element breaks the line itself, and the separator
        // would show up as a stray empty line under it. A blank line touching a
        // block has nothing left to break, so it becomes the <br> it stood for.
        $out       = '';
        $prevBlock = true;
        $total     = count($parts);

        foreach ($parts as $k => $part) {
            $isBlock = $part[0];
            $blank   = !$isBlock && $part[1] === ''
                && (($k > 0 && $parts[$k - 1][0]) || ($k + 1 < $total && $parts[$k + 1][0]));

            if ($blank) {
                $out .= '<br>';
                $prevBlock = true;
                continue;
            }
            if ($k > 0 && !$isBlock && !$prevBlock) {
                $out .= "\n";
            }
            $out .= $part[1];
            $prevBlock = $isBlock;
        }

        return $out;
    }
}

if (!function_exists('whatsapp_format_text')) {
    /**
     * WhatsApp markup → HTML, for previews only. Mirrors waFormat() in
     * assets/js/whatsapp.js — keep the two in step. Escapes everything it does
     * not turn into markup, so the result is safe to echo unescaped.
     */
    function whatsapp_format_text($text)
    {
        // Private-use character: code spans are lifted out behind it so nothing
        // inside them is formatted, then put back once everything else is done.
        $mark = "\u{E000}";
        $src  = str_replace($mark, '', str_replace(["\r\n", "\r"], "\n", (string) $text));
        $kept = [];

        $hold = function ($html) use (&$kept, $mark) {
            $kept[] = $html;
            return $mark . (count($kept) - 1) . $mark;
        };

        $src = preg_replace_callback('/```(.*?)```/s', function ($m) use ($hold) {
            return $hold('<code class="wapi-fmt-mono">' . e($m[1]) . '</code>');
        }, $src);
        $src = preg_replace_callback('/`([^`\n]+)`/', function ($m) use ($hold) {
            return $hold('<code class="wapi-fmt-code">' . e($m[1]) . '</code>');
        }, $src);

        return preg_replace_callback('/' . $mark . '(\d+)' . $mark . '/u', function ($m) use ($kept) {
            return $kept[(int) $m[1]] ?? '';
        }, whatsapp_format_blocks($src));
    }
}

if (!function_exists('whatsapp_template_preview_html')) {
    /**
     * WhatsApp-style bubble for a template's components — what the recipient
     * actually sees, variables left as {{n}} so the shape stays obvious.
     */
    function whatsapp_template_preview_html($components)
    {
        $header  = whatsapp_template_component($components, 'HEADER');
        $body    = whatsapp_template_body_text($components);
        $footer  = whatsapp_template_footer_text($components);
        $buttons = whatsapp_template_buttons($components);

        $html = '<div class="wapi-tpl-preview"><div class="wapi-bubble-row wapi-bubble-in"><div class="wapi-bubble">';

        if ($header) {
            $format = strtoupper($header['format'] ?? 'TEXT');
            if ($format === 'TEXT') {
                $html .= '<div class="wapi-tpl-preview-header">' . whatsapp_format_text((string) ($header['text'] ?? '')) . '</div>';
            } else {
                $icon = $format === 'VIDEO' ? 'fa-video' : ($format === 'DOCUMENT' ? 'fa-file-lines' : ($format === 'LOCATION' ? 'fa-location-dot' : 'fa-image'));
                $html .= '<div class="wapi-tpl-preview-media"><i class="fa ' . $icon . '"></i> ' . e(ucfirst(strtolower($format))) . ' header</div>';
            }
        }

        $html .= '<div class="wapi-bubble-text">' . whatsapp_format_text($body) . '</div>';

        if ($footer !== '') {
            $html .= '<div class="wapi-tpl-preview-footer">' . e($footer) . '</div>';
        }
        $html .= '</div></div>';

        if (!empty($buttons)) {
            $html .= '<div class="wapi-tpl-preview-buttons">';
            foreach ($buttons as $b) {
                $type = strtoupper($b['type'] ?? '');
                $icon = $type === 'URL' ? 'fa-arrow-up-right-from-square' : ($type === 'PHONE_NUMBER' ? 'fa-phone' : ($type === 'COPY_CODE' ? 'fa-copy' : 'fa-reply'));
                $html .= '<span class="wapi-tpl-preview-btn"><i class="fa ' . $icon . '"></i> ' . e((string) ($b['text'] ?? $type)) . '</span>';
            }
            $html .= '</div>';
        }

        return $html . '</div>';
    }
}

if (!function_exists('whatsapp_template_form_payload')) {
    /**
     * Flatten a cached template row into the shape the edit form needs.
     * Fields the form cannot safely rebuild (media header, buttons) come back
     * as read-only facts — the model carries them through an edit untouched.
     */
    function whatsapp_template_form_payload($tpl)
    {
        $components = whatsapp_template_components($tpl);
        $header     = whatsapp_template_component($components, 'HEADER');
        $format     = $header ? strtoupper($header['format'] ?? 'TEXT') : '';
        $status     = strtoupper((string) ($tpl->status ?? ''));
        $reason     = whatsapp_template_rejected_reason($tpl->rejected_reason ?? '');

        $buttons     = [];
        $button_defs = [];
        foreach (whatsapp_template_buttons($components) as $b) {
            $buttons[]     = trim(((string) ($b['text'] ?? '')) . ' (' . strtolower(str_replace('_', ' ', (string) ($b['type'] ?? ''))) . ')');
            $button_defs[] = [
                'text' => (string) ($b['text'] ?? ''),
                'type' => strtoupper((string) ($b['type'] ?? '')),
            ];
        }

        // Approved sample values, so the live preview and the edit form start
        // from what Meta already reviewed instead of blank inputs.
        $body_samples   = whatsapp_template_component($components, 'BODY')['example']['body_text'][0] ?? [];
        $header_samples = $header['example']['header_text'] ?? [];

        return [
            'id'                  => (int) $tpl->id,
            'name'                => (string) $tpl->name,
            'language'            => (string) $tpl->language,
            'category'            => strtoupper((string) $tpl->category),
            'status'              => $status,
            'header_format'       => $format,
            'header_text'         => $format === 'TEXT' ? (string) ($header['text'] ?? '') : '',
            'media_header'        => ($format !== '' && $format !== 'TEXT'),
            'body_text'           => (string) $tpl->body_text,
            'footer_text'         => whatsapp_template_footer_text($components),
            'buttons'             => $buttons,
            'button_defs'         => $button_defs,
            'variables'           => (int) $tpl->variables_count,
            'samples'             => array_map('strval', array_values((array) $body_samples)),
            'header_sample'       => (string) (is_array($header_samples) ? reset($header_samples) : $header_samples),
            'editable'            => whatsapp_template_editable($status),
            'edit_block'          => whatsapp_template_edit_block_reason($status),
            // Meta refuses a category change while a template is approved.
            'can_change_category' => whatsapp_template_editable($status) && $status !== 'APPROVED',
            'rejected_label'      => $reason['label'],
            'rejected_hint'       => $reason['hint'],
        ];
    }
}

if (!function_exists('whatsapp_campaign_status_badge')) {
    function whatsapp_campaign_status_badge($status)
    {
        $map = [
            'draft'     => ['#6b7280', 'Draft'],
            'scheduled' => ['#0ea5e9', 'Scheduled'],
            'running'   => ['#f59e0b', 'Running'],
            'paused'    => ['#f59e0b', 'Paused'],
            'completed' => ['#16a34a', 'Completed'],
            'cancelled' => ['#ef4444', 'Cancelled'],
        ];
        $info = $map[$status] ?? ['#6b7280', ucfirst((string) $status)];
        return whatsapp_badge($info[0], $info[1]);
    }
}

if (!function_exists('whatsapp_time_ago')) {
    function whatsapp_time_ago($datetime)
    {
        if (empty($datetime)) {
            return '—';
        }
        $ts   = is_numeric($datetime) ? $datetime : strtotime($datetime);
        if (!$ts) {
            return '—';
        }
        // A stamp in the future is a clock difference, not a real one — show it
        // as "just now" instead of a nonsensical negative age.
        $diff = max(0, time() - $ts);
        if ($diff < 60)      return 'just now';
        if ($diff < 3600)    return floor($diff / 60) . 'm ago';
        if ($diff < 86400)   return floor($diff / 3600) . 'h ago';
        if ($diff < 2592000) return floor($diff / 86400) . 'd ago';
        return date('M j, Y', $ts);
    }
}

/* ─────────────────── Business profile (branding) ────────────────────────── */

if (!function_exists('whatsapp_business_verticals')) {
    /** Business categories Meta accepts for `vertical`, with readable labels. */
    function whatsapp_business_verticals()
    {
        return [
            'UNDEFINED'     => '— not set —',
            'HEALTH'        => 'Medical & Health',
            'PROF_SERVICES' => 'Professional Services',
            'BEAUTY'        => 'Beauty, Spa & Salon',
            'EDU'           => 'Education',
            'FINANCE'       => 'Finance & Banking',
            'GOVT'          => 'Public Service',
            'GROCERY'       => 'Grocery & Convenience',
            'HOTEL'         => 'Hotel & Lodging',
            'NONPROFIT'     => 'Non-profit',
            'RESTAURANT'    => 'Restaurant',
            'RETAIL'        => 'Shopping & Retail',
            'TRAVEL'        => 'Travel & Transport',
            'APPAREL'       => 'Clothing & Apparel',
            'AUTO'          => 'Automotive',
            'ENTERTAIN'     => 'Entertainment',
            'EVENT_PLAN'    => 'Event Planning & Service',
            'NOT_A_BIZ'     => 'Not a business',
            'OTHER'         => 'Other',
        ];
    }
}

if (!function_exists('whatsapp_profile_limits')) {
    /** Meta's field length caps — enforced here so a save never round-trips. */
    function whatsapp_profile_limits()
    {
        return ['about' => 139, 'description' => 512, 'address' => 256, 'email' => 128, 'website' => 256];
    }
}

if (!function_exists('whatsapp_upload_handle')) {
    /**
     * Meta's resumable upload, used to turn a local image into the
     * `profile_picture_handle` the business-profile endpoint expects.
     *
     *   1. POST /{app_id}/uploads?file_length&file_type   → upload session id
     *   2. POST /{session_id} with the raw bytes           → { h: handle }
     *
     * Step 2 authenticates with `OAuth <token>`, not `Bearer` — that is what
     * Meta's resumable upload protocol specifies.
     *
     * @return array{handle:string}|array{error:string}
     */
    function whatsapp_upload_handle($app_id, $binary, $mime, $token)
    {
        if ($app_id === '' || $binary === '') {
            return ['error' => 'Upload is not configured.'];
        }

        $session = whatsapp_http_request(
            whatsapp_graph_base() . $app_id . '/uploads?' . http_build_query([
                'file_length' => strlen($binary),
                'file_type'   => $mime,
            ]),
            'POST',
            [],
            ['Authorization: Bearer ' . $token]
        );
        if (isset($session['error'])) {
            return ['error' => $session['error']];
        }
        if (empty($session['id'])) {
            return ['error' => 'Meta did not return an upload session.'];
        }

        $res = whatsapp_http_request(
            whatsapp_graph_base() . $session['id'],
            'POST',
            $binary,
            ['Authorization: OAuth ' . $token, 'file_offset: 0']
        );
        if (isset($res['error'])) {
            return ['error' => $res['error']];
        }
        if (empty($res['h'])) {
            return ['error' => 'Meta did not return an image handle.'];
        }
        return ['handle' => $res['h']];
    }
}

if (!function_exists('whatsapp_name_status_meta')) {
    /** Display-name review state → colour + label + what it means. */
    function whatsapp_name_status_meta($status)
    {
        $s = strtoupper((string) $status);
        $map = [
            'APPROVED'                      => ['#16a34a', 'Approved', 'This display name is live on WhatsApp.'],
            'AVAILABLE_WITHOUT_REVIEW'      => ['#16a34a', 'Available', 'The name can be used without further review.'],
            'PENDING_REVIEW'                => ['#f59e0b', 'In review', 'Meta is reviewing the requested name — usually a couple of days.'],
            'DECLINED'                      => ['#ef4444', 'Declined', 'Meta rejected the name for breaching its display-name policy.'],
            'EXPIRED'                       => ['#6b7280', 'Expired', 'The review request expired — submit the name again.'],
            'NONE'                          => ['#6b7280', 'Not set', 'No display name has been submitted for this number.'],
        ];
        if ($s === '' || !isset($map[$s])) {
            return ['color' => '#6b7280', 'label' => '—', 'detail' => ''];
        }
        return ['color' => $map[$s][0], 'label' => $map[$s][1], 'detail' => $map[$s][2]];
    }
}

if (!function_exists('whatsapp_note_webhook')) {
    /**
     * Remember that Meta actually called us. Without this a broken webhook is
     * indistinguishable from "nobody has messaged us yet" — the inbox just
     * stays empty and the 24-hour window never opens.
     *
     * @param string $kind received|rejected
     */
    function whatsapp_note_webhook($kind = 'received')
    {
        $now = date('Y-m-d H:i:s');
        if ($kind === 'rejected') {
            update_option('whatsapp_last_webhook_rejected_at', $now);
            return;
        }
        update_option('whatsapp_last_webhook_at', $now);
        update_option('whatsapp_webhook_count', (int) get_option('whatsapp_webhook_count') + 1);
    }
}

if (!function_exists('whatsapp_number_graph_fields')) {
    /**
     * Fields pulled for every phone number. `status` is the authoritative
     * registration state (CONNECTED = registered on the Cloud API), which is
     * what error 133010 "Account not registered" reflects.
     */
    function whatsapp_number_graph_fields()
    {
        return 'id,display_phone_number,verified_name,quality_rating,status,'
             . 'code_verification_status,name_status,platform_type,throughput,'
             . 'messaging_limit_tier,is_official_business_account,account_mode';
    }
}

if (!function_exists('whatsapp_session_window_open')) {
    /** 24-hour customer-service window: open while the last inbound is < 24h old. */
    function whatsapp_session_window_open($last_incoming_at)
    {
        if (empty($last_incoming_at)) {
            return false;
        }
        return (time() - strtotime($last_incoming_at)) < 86400;
    }
}
