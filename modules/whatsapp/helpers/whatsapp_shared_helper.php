<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * SHARED WHATSAPP NUMBER — "send on the provider's account".
 *
 * The rest of this module assumes a tenant connects its OWN WhatsApp Business
 * Account through Embedded Signup. That is the right model for a customer who
 * wants their own branded sender, but it is a wall for everyone else: a Meta
 * business verification, a display-name review and a payment method before the
 * first message ever goes out.
 *
 * This file adds the other half of the product. The provider (master account)
 * can lend its OWN connected number to a tenant:
 *
 *   • the tenant never connects anything, and never sees a token, an app
 *     secret or a WABA id — the send simply resolves to a provider-owned
 *     `phone_number_id` and the token is read server-side out of the master
 *     registry, exactly like the central app credentials already are;
 *   • the tenant may only send APPROVED templates the provider has explicitly
 *     put on its allowlist, so nothing unreviewed can ever leave the
 *     provider's number and hurt its quality rating;
 *   • the provider decides how it is paid for: `free` (a perk of the plan,
 *     bounded by a message cap) or `credits` (billed against the tenant's
 *     WhatsApp balance in CCX Msgs, per 24-hour conversation, exactly like an
 *     own-number tenant).
 *
 * A tenant's own connection ALWAYS wins. The moment they connect a WABA of
 * their own the grant stops applying, without anyone having to switch it off.
 *
 * DATA (all in the MASTER database, read cross-DB by tenants):
 *   tblwhatsapp_shared_grants     one row per tenant, the whole policy
 *   tblwhatsapp_shared_templates  the approved-template allowlist
 *   tblwhatsapp_shared_usage      per-tenant daily counters (quota + reporting)
 *
 * Everything here is lazy: this file is required from the module bootstrap on
 * every request, so nothing may touch the database until it is asked a
 * question that actually needs an answer.
 */

if (!function_exists('whatsapp_shared_load_core')) {
    /** Pull in the module's main helper on demand (bootstrap stays cheap). */
    function whatsapp_shared_load_core()
    {
        require_once __DIR__ . '/whatsapp_helper.php';
    }
}

if (!function_exists('whatsapp_shared_model')) {
    /** Load the module model on demand (it owns the master-DB plumbing). */
    function whatsapp_shared_model()
    {
        whatsapp_shared_load_core();

        $CI = &get_instance();
        if (!isset($CI->whatsapp_model)) {
            $CI->load->model('whatsapp/whatsapp_model');
        }

        return $CI->whatsapp_model;
    }
}

/* ─────────────────────────── Provider settings ──────────────────────────── */

if (!function_exists('whatsapp_shared_settings')) {
    /**
     * The provider-side switches, readable from a tenant.
     *
     * `enabled` is a global kill switch — turning it off suspends every grant
     * at once without editing any of them.
     *
     * @return array{enabled:bool, number:string, brand:string}
     */
    function whatsapp_shared_settings()
    {
        static $settings = null;
        if ($settings !== null) {
            return $settings;
        }

        whatsapp_shared_load_core();

        $names = ['whatsapp_shared_enabled', 'whatsapp_shared_number', 'whatsapp_shared_brand'];
        $raw   = [];

        if (whatsapp_is_tenant()) {
            $raw = whatsapp_read_master_options($names);
        } else {
            foreach ($names as $n) {
                $raw[$n] = get_option($n);
            }
        }

        $settings = [
            'enabled' => (string) ($raw['whatsapp_shared_enabled'] ?? '') === '1',
            'number'  => (string) ($raw['whatsapp_shared_number'] ?? ''),
            'brand'   => (string) ($raw['whatsapp_shared_brand'] ?? ''),
        ];

        return $settings;
    }
}

if (!function_exists('whatsapp_shared_brand')) {
    /**
     * The name the tenant sees on the shared number ("Sending on <brand>'s
     * WhatsApp"). Falls back to the provider's company name so a provider that
     * never fills the field still gets a branded string rather than "the
     * provider".
     */
    function whatsapp_shared_brand()
    {
        $s = whatsapp_shared_settings();
        if ($s['brand'] !== '') {
            return $s['brand'];
        }

        // On a tenant `companyname` is the TENANT's name, which would read as
        // if they owned the number — only trust it on the master.
        if (!whatsapp_is_tenant()) {
            $own = (string) get_option('companyname');
            if ($own !== '') {
                return $own;
            }
        }

        return 'our';
    }
}

/* ──────────────────────────────── The grant ─────────────────────────────── */

if (!function_exists('whatsapp_shared_grant')) {
    /**
     * The grant row for a tenant, or null.
     *
     * Deliberately unfiltered — it reports what the provider configured, even
     * when the global switch is off. Use whatsapp_shared_enabled() to ask
     * whether it currently applies.
     *
     * @param string|null $slug defaults to the current context
     * @return object|null
     */
    function whatsapp_shared_grant($slug = null)
    {
        static $cache = [];

        whatsapp_shared_load_core();

        $slug = $slug === null ? whatsapp_current_slug() : $slug;
        if (array_key_exists($slug, $cache)) {
            return $cache[$slug];
        }

        $cache[$slug] = null;
        try {
            $cache[$slug] = whatsapp_shared_model()->shared_grant($slug);
        } catch (Exception $e) {
            // A tenant whose master has not been upgraded yet simply has no
            // grant — never let that break an unrelated page.
        }

        return $cache[$slug];
    }
}

if (!function_exists('whatsapp_shared_enabled')) {
    /**
     * True when the current account is supposed to send on the provider's
     * number: the provider's global switch is on, this tenant has an enabled
     * grant, and they have NOT connected a WABA of their own.
     *
     * The master itself never runs in shared mode — it IS the provider.
     */
    function whatsapp_shared_enabled()
    {
        static $enabled = null;
        if ($enabled !== null) {
            return $enabled;
        }

        whatsapp_shared_load_core();

        $enabled = false;

        if (!whatsapp_is_tenant()) {
            return $enabled;
        }

        $settings = whatsapp_shared_settings();
        if (!$settings['enabled']) {
            return $enabled;
        }

        $grant = whatsapp_shared_grant();
        if (!$grant || (int) $grant->enabled !== 1) {
            return $enabled;
        }

        // An own connection always wins — the tenant graduated to their own
        // number and the grant quietly stops applying.
        try {
            $own = whatsapp_shared_model()->registry_get_connection(whatsapp_current_slug());
            if ($own && !empty($own->access_token)) {
                return $enabled;
            }
        } catch (Exception $e) {
            return $enabled;
        }

        $enabled = true;

        return $enabled;
    }
}

if (!function_exists('whatsapp_shared_number')) {
    /**
     * The provider-owned number this tenant sends through.
     *
     * The grant may pin one; otherwise the provider's configured default is
     * used, and failing that the provider's own default number. The row is
     * always re-read from the registry so a number that has since been removed
     * resolves to null instead of a dangling id.
     *
     * @return object|null registry row (tblwhatsapp_numbers)
     */
    function whatsapp_shared_number()
    {
        static $number = false;
        if ($number !== false) {
            return $number;
        }

        $number = null;

        try {
            $model    = whatsapp_shared_model();
            $grant    = whatsapp_shared_grant();
            $settings = whatsapp_shared_settings();

            $candidates = array_filter([
                $grant ? (string) $grant->phone_number_id : '',
                $settings['number'],
            ]);

            foreach ($candidates as $pnid) {
                $row = $model->registry_get_number($pnid);
                if ($row && whatsapp_shared_is_provider_number($row)) {
                    $number = $row;

                    return $number;
                }
            }

            // Nothing pinned — fall back to the provider's own default number.
            // The registry orders defaults first, so the head of the list is it.
            $own    = $model->registry_numbers_for_tenant(whatsapp_shared_provider_slug());
            $number = $own[0] ?? null;
        } catch (Exception $e) {
            $number = null;
        }

        return $number;
    }
}

if (!function_exists('whatsapp_shared_provider_slug')) {
    /** Registry slug the provider's own numbers are filed under. */
    function whatsapp_shared_provider_slug()
    {
        return function_exists('perfex_saas_master_tenant_slug') ? perfex_saas_master_tenant_slug() : 'master';
    }
}

if (!function_exists('whatsapp_shared_is_provider_number')) {
    /** True when a registry number row belongs to the provider, not a tenant. */
    function whatsapp_shared_is_provider_number($number)
    {
        return $number && (string) $number->tenant_slug === whatsapp_shared_provider_slug();
    }
}

if (!function_exists('whatsapp_shared_active')) {
    /**
     * True when shared sending is not just configured but actually usable —
     * i.e. a provider number resolved and Meta has not reported it as unable
     * to send. This is the gate every send path asks.
     */
    function whatsapp_shared_active()
    {
        if (!whatsapp_shared_enabled()) {
            return false;
        }

        $number = whatsapp_shared_number();

        return $number !== null && !whatsapp_number_unregistered($number);
    }
}

if (!function_exists('whatsapp_shared_can')) {
    /**
     * Whether one kind of traffic is permitted on the shared number.
     *
     * @param string $what 'send' (single sends) | 'bulk' (campaigns) | 'hooks'
     *                     (Omni Messaging hooks, campaigns, auto-schedulers)
     */
    function whatsapp_shared_can($what)
    {
        if (!whatsapp_shared_active()) {
            return false;
        }

        $grant  = whatsapp_shared_grant();
        $column = 'allow_' . $what;

        return $grant && (int) ($grant->$column ?? 1) === 1;
    }
}

if (!function_exists('whatsapp_shared_billing_mode')) {
    /** 'free' or 'credits'. */
    function whatsapp_shared_billing_mode()
    {
        $grant = whatsapp_shared_grant();

        return ($grant && (string) $grant->billing_mode === 'free') ? 'free' : 'credits';
    }
}

if (!function_exists('whatsapp_shared_is_free')) {
    /** True when the provider absorbs the cost of this tenant's messages. */
    function whatsapp_shared_is_free()
    {
        return whatsapp_shared_active() && whatsapp_shared_billing_mode() === 'free';
    }
}

/* ─────────────────────────── Template allowlist ─────────────────────────── */

if (!function_exists('whatsapp_shared_allowed_templates')) {
    /**
     * The provider templates this tenant may send, as
     * ['name|language' => ['name' => .., 'language' => ..]].
     *
     * An empty array under template_mode = 'selected' means the provider has
     * granted the number but not yet picked any template — nothing can be sent
     * yet, which is the safe reading.
     */
    function whatsapp_shared_allowed_templates()
    {
        static $allowed = null;
        if ($allowed !== null) {
            return $allowed;
        }

        $allowed = [];
        $grant   = whatsapp_shared_grant();
        if (!$grant) {
            return $allowed;
        }

        try {
            $model = whatsapp_shared_model();

            if ((string) $grant->template_mode === 'all') {
                foreach ($model->shared_provider_templates() as $t) {
                    $allowed[$t->name . '|' . $t->language] = ['name' => $t->name, 'language' => $t->language];
                }

                return $allowed;
            }

            foreach ($model->shared_grant_templates(whatsapp_current_slug()) as $t) {
                $allowed[$t->template_name . '|' . $t->language] = [
                    'name'     => $t->template_name,
                    'language' => $t->language,
                ];
            }
        } catch (Exception $e) {
            $allowed = [];
        }

        return $allowed;
    }
}

if (!function_exists('whatsapp_shared_template_allowed')) {
    /**
     * Gate for one outgoing template. Language is matched leniently: Meta
     * templates are keyed by name+language, but a caller that only knows the
     * name (a legacy Omni mapping, say) must still be able to send an
     * unambiguous one.
     */
    function whatsapp_shared_template_allowed($name, $language = null)
    {
        $name = (string) $name;
        if ($name === '') {
            return false;
        }

        $allowed = whatsapp_shared_allowed_templates();
        if ($language !== null && $language !== '' && isset($allowed[$name . '|' . $language])) {
            return true;
        }

        foreach ($allowed as $entry) {
            if ($entry['name'] === $name) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('whatsapp_shared_sync_templates')) {
    /**
     * Mirror the allowed provider templates into the tenant's own template
     * cache, tagged `source = 'shared'`.
     *
     * Everything downstream — the Send/Bulk pickers, the Omni auto-map engine,
     * render_template_preview() — already reads that table, so mirroring keeps
     * one code path instead of branching every consumer. Rows are pruned when
     * the provider narrows the allowlist, and the tenant's own templates
     * (source = 'own') are never touched.
     *
     * @param  bool $force skip the 5-minute throttle
     * @return array{synced:int, removed:int, skipped:bool}
     */
    function whatsapp_shared_sync_templates($force = false)
    {
        $out = ['synced' => 0, 'removed' => 0, 'skipped' => true];

        if (!whatsapp_shared_enabled()) {
            // Not (or no longer) on the shared number — drop any leftovers so a
            // tenant whose grant was revoked, or who graduated to their own
            // WABA, stops seeing the provider's templates. The stamp is what
            // proves anything was ever mirrored, so an account that never had a
            // grant does no work at all here.
            if ((string) get_option('whatsapp_shared_sync_at') !== '') {
                $out['removed'] = whatsapp_shared_purge_templates();
                $out['skipped'] = false;
                update_option('whatsapp_shared_sync_at', '');
            }

            return $out;
        }

        if (!$force) {
            $last = (string) get_option('whatsapp_shared_sync_at');
            if ($last !== '' && (time() - (int) $last) < 300) {
                return $out;
            }
        }
        update_option('whatsapp_shared_sync_at', time());
        $out['skipped'] = false;

        $CI    = &get_instance();
        $table = db_prefix() . 'whatsapp_api_templates';
        if (!$CI->db->table_exists($table) || !$CI->db->field_exists('source', $table)) {
            return $out;
        }

        $allowed = whatsapp_shared_allowed_templates();
        $keep    = [];

        try {
            $provider = whatsapp_shared_model()->shared_provider_templates();
        } catch (Exception $e) {
            return $out;
        }

        foreach ($provider as $t) {
            $key = $t->name . '|' . $t->language;
            if (!isset($allowed[$key])) {
                continue;
            }
            $keep[] = $key;

            $row = [
                'template_id'      => $t->template_id,
                'name'             => $t->name,
                'language'         => $t->language,
                'category'         => $t->category,
                'status'           => $t->status,
                'rejected_reason'  => null,
                'quality_score'    => null,
                'components'       => $t->components,
                'body_text'        => $t->body_text,
                'variables_count'  => (int) $t->variables_count,
                'has_media_header' => (int) $t->has_media_header,
                'last_synced_at'   => date('Y-m-d H:i:s'),
                'source'           => 'shared',
            ];

            $existing = $CI->db->where('name', $t->name)->where('language', $t->language)
                ->get($table)->row();

            if ($existing) {
                // Never overwrite a template the tenant owns on their own WABA
                // — same name, different account, and theirs is authoritative.
                if ((string) ($existing->source ?? 'own') !== 'shared') {
                    continue;
                }
                $CI->db->where('id', $existing->id)->update($table, $row);
            } else {
                $CI->db->insert($table, $row);
            }
            $out['synced']++;
        }

        $out['removed'] = whatsapp_shared_purge_templates($keep);

        return $out;
    }
}

if (!function_exists('whatsapp_shared_purge_templates')) {
    /**
     * Delete mirrored templates that are no longer granted.
     *
     * @param  array $keep 'name|language' keys to preserve ([] = purge all)
     * @return int   rows removed
     */
    function whatsapp_shared_purge_templates($keep = [])
    {
        $CI    = &get_instance();
        $table = db_prefix() . 'whatsapp_api_templates';
        if (!$CI->db->table_exists($table) || !$CI->db->field_exists('source', $table)) {
            return 0;
        }

        $rows    = $CI->db->where('source', 'shared')->get($table)->result();
        $removed = 0;

        foreach ($rows as $row) {
            if (in_array($row->name . '|' . $row->language, $keep, true)) {
                continue;
            }
            $CI->db->where('id', $row->id)->delete($table);
            $removed++;
        }

        return $removed;
    }
}

/* ───────────────────────────── Quota + metering ─────────────────────────── */

if (!function_exists('whatsapp_shared_usage')) {
    /**
     * What this tenant has used on the shared number.
     *
     * @return array{today:int, month:int, credits_month:int, conversations_month:int,
     *               daily_limit:int, monthly_limit:int,
     *               daily_left:int|null, monthly_left:int|null}
     */
    function whatsapp_shared_usage($slug = null)
    {
        $slug  = $slug === null ? whatsapp_current_slug() : $slug;
        $grant = whatsapp_shared_grant($slug);

        $usage = [
            'today'               => 0,
            'month'               => 0,
            'credits_month'       => 0,
            'conversations_month' => 0,
            'daily_limit'         => $grant ? (int) $grant->daily_limit : 0,
            'monthly_limit'       => $grant ? (int) $grant->monthly_limit : 0,
            'daily_left'          => null,
            'monthly_left'        => null,
        ];

        try {
            $stats = whatsapp_shared_model()->shared_usage_summary($slug);
            $usage = array_merge($usage, $stats);
        } catch (Exception $e) {
            // Counters unavailable → treat as zero used, never as over quota.
        }

        if ($usage['daily_limit'] > 0) {
            $usage['daily_left'] = max(0, $usage['daily_limit'] - $usage['today']);
        }
        if ($usage['monthly_limit'] > 0) {
            $usage['monthly_left'] = max(0, $usage['monthly_limit'] - $usage['month']);
        }

        return $usage;
    }
}

if (!function_exists('whatsapp_shared_quota_check')) {
    /**
     * Message-cap gate for the shared number, in the same shape as
     * _ccx_check_channel_balance().
     *
     * This is the provider's cost guard, and it applies in BOTH billing modes:
     * a free grant is bounded by it, and a credits grant still cannot exceed
     * whatever throughput the provider is willing to lend.
     *
     * @return true|array{status:string, error:string}
     */
    function whatsapp_shared_quota_check()
    {
        $usage = whatsapp_shared_usage();

        if ($usage['daily_limit'] > 0 && $usage['today'] >= $usage['daily_limit']) {
            return [
                'status' => 'quota_exceeded',
                'error'  => 'Daily WhatsApp limit reached on the shared number ('
                            . $usage['daily_limit'] . ' messages/day).',
            ];
        }

        if ($usage['monthly_limit'] > 0 && $usage['month'] >= $usage['monthly_limit']) {
            return [
                'status' => 'quota_exceeded',
                'error'  => 'Monthly WhatsApp limit reached on the shared number ('
                            . $usage['monthly_limit'] . ' messages/month).',
            ];
        }

        return true;
    }
}

if (!function_exists('whatsapp_shared_record_usage')) {
    /**
     * Add one send to the tenant's daily counters. Called from
     * whatsapp_credits_commit() so every accepted message — hook, campaign,
     * single send — is metered in exactly one place.
     */
    function whatsapp_shared_record_usage($messages = 1, $conversations = 0, $credits = 0)
    {
        if (!whatsapp_shared_enabled()) {
            return;
        }

        try {
            whatsapp_shared_model()->shared_usage_add(
                whatsapp_current_slug(),
                (int) $messages,
                (int) $conversations,
                (int) $credits
            );
        } catch (Exception $e) {
            // Metering must never break a delivered message.
        }
    }
}

/* ──────────────────────────────── UI status ─────────────────────────────── */

if (!function_exists('whatsapp_shared_status')) {
    /**
     * Everything the tenant-facing UI needs to describe the shared number in
     * one call.
     *
     * @return array
     */
    function whatsapp_shared_status()
    {
        $enabled = whatsapp_shared_enabled();
        $grant   = $enabled ? whatsapp_shared_grant() : null;
        $number  = $enabled ? whatsapp_shared_number() : null;

        $status = [
            'enabled'      => $enabled,
            'active'       => whatsapp_shared_active(),
            'brand'        => whatsapp_shared_brand(),
            'billing_mode' => $enabled ? whatsapp_shared_billing_mode() : '',
            'number'       => $number,
            'number_label' => $number ? (string) ($number->display_phone_number ?: $number->phone_number_id) : '',
            'templates'    => $enabled ? count(whatsapp_shared_allowed_templates()) : 0,
            'usage'        => $enabled ? whatsapp_shared_usage() : null,
            'allow_send'   => whatsapp_shared_can('send'),
            'allow_bulk'   => whatsapp_shared_can('bulk'),
            'allow_hooks'  => whatsapp_shared_can('hooks'),
            'reason'       => '',
        ];

        if ($enabled && !$status['active']) {
            $status['reason'] = $number === null
                ? 'The shared WhatsApp number has not been set up by ' . $status['brand'] . ' yet.'
                : 'The shared WhatsApp number is temporarily unable to send. ' . $status['brand'] . ' has been notified.';
        } elseif ($enabled && $status['templates'] === 0) {
            $status['reason'] = 'No approved templates have been shared with this account yet.';
        }

        return $status;
    }
}

if (!function_exists('whatsapp_shared_block_reason')) {
    /**
     * Why an account-management action is refused while on the shared number.
     *
     * The tenant does not own the WhatsApp Business Account, so everything
     * that would change it — templates, business profile, display name, number
     * registration, disconnecting — has to be refused server-side and not just
     * hidden in the UI.
     *
     * @return string '' when the action is allowed
     */
    function whatsapp_shared_block_reason()
    {
        if (!whatsapp_shared_enabled()) {
            return '';
        }

        return 'This account sends on ' . whatsapp_shared_brand()
             . "'s WhatsApp number, so its WhatsApp Business Account settings, templates and profile are managed by "
             . whatsapp_shared_brand() . '. Connect your own WhatsApp Business Account to manage these yourself.';
    }
}

if (!function_exists('whatsapp_shared_mode_badge')) {
    /** Small coloured badge for a billing mode. */
    function whatsapp_shared_mode_badge($mode)
    {
        whatsapp_shared_load_core();

        return (string) $mode === 'free'
            ? whatsapp_badge('#16a34a', 'Free')
            : whatsapp_badge('#2563eb', 'Credits');
    }
}
