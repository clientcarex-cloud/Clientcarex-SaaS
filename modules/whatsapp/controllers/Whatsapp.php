<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Whatsapp extends AdminController
{
    /** server-to-server / unauthenticated endpoints (skip admin auth) */
    private $public_actions = ['webhook', 'ingest', 'oauth_callback'];
    private $is_public_request = false;

    public function __construct()
    {
        $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        foreach ($this->public_actions as $a) {
            if (strpos($uri, 'whatsapp/' . $a) !== false) {
                $this->is_public_request = true;
                break;
            }
        }

        if ($this->is_public_request) {
            App_Controller::__construct(); // DB + helpers, no admin login
        } else {
            parent::__construct();
        }

        $this->load->helper('whatsapp/whatsapp');
        $this->load->model('whatsapp/whatsapp_model');
    }

    /* ─────────────────────── permission gates ─────────────────────── */

    private function _can_access()
    {
        return whatsapp_staff_can_access();
    }

    /** Overview tab — stats, numbers, diagnostics, activity chart. */
    private function _can_view()
    {
        return whatsapp_can('view');
    }

    /** Inbox tab — reading threads and message history. */
    private function _can_inbox()
    {
        return whatsapp_can('inbox');
    }

    private function _can_send()
    {
        return whatsapp_can('send');
    }

    private function _can_bulk()
    {
        return whatsapp_can('bulk');
    }

    /** Templates tab — sync / create / delete approved templates. */
    private function _can_templates()
    {
        return whatsapp_can('templates');
    }

    private function _can_bot()
    {
        return whatsapp_can('bot');
    }

    /** Profile tab — public business profile + display name. */
    private function _can_profile()
    {
        return whatsapp_can('profile');
    }

    /** Contacts tab — contact list + opt-out. */
    private function _can_contacts()
    {
        return whatsapp_can('contacts');
    }

    private function _can_settings()
    {
        return whatsapp_can('settings');
    }

    /** JSON reply with a fresh CSRF hash so the SPA keeps working. */
    private function _json($data)
    {
        $data['csrf_hash'] = $this->security->get_csrf_hash();
        header('Content-Type: application/json');
        echo json_encode($data);
    }

    private function _result($res, $success_message)
    {
        return isset($res['error'])
            ? ['success' => false, 'message' => $res['error']]
            : ['success' => true, 'message' => $success_message];
    }

    /**
     * Refuse an action that would change a WhatsApp Business Account this
     * account does not own.
     *
     * While a tenant sends on the provider's shared number, everything that
     * touches the WABA — templates, business profile, display name, number
     * registration, disconnecting — belongs to the provider. The UI hides
     * those controls; this makes the refusal real, because hiding a button is
     * not access control.
     *
     * @return bool true when the request was answered (caller must return)
     */
    private function _blocked_by_shared()
    {
        if (!function_exists('whatsapp_shared_block_reason')) {
            return false;
        }

        $reason = whatsapp_shared_block_reason();
        if ($reason === '') {
            return false;
        }

        $this->_json([
            'success' => false,
            'title'   => _l('wapi_shared_managed_title'),
            'message' => $reason,
        ]);

        return true;
    }

    /**
     * Gate one kind of traffic on the shared number ('send' | 'bulk').
     * A tenant on their own number is never affected.
     *
     * @return bool true when the request was answered
     */
    private function _shared_traffic_blocked($what)
    {
        if (!function_exists('whatsapp_shared_enabled') || !whatsapp_shared_enabled()) {
            return false;
        }
        if (whatsapp_shared_can($what)) {
            return false;
        }

        $status = whatsapp_shared_status();
        $this->_json([
            'success' => false,
            'title'   => _l('wapi_shared_managed_title'),
            'message' => $status['reason'] ?: sprintf(_l('wapi_shared_traffic_denied'), $status['brand']),
        ]);

        return true;
    }

    /* ─────────────────────────── Dashboard ─────────────────────────── */

    public function index()
    {
        if (!$this->_can_access()) {
            access_denied('WhatsApp');
        }

        $slug = whatsapp_current_slug();

        $data['is_configured'] = whatsapp_is_configured();
        $data['central']       = whatsapp_central_config();
        $data['connection']    = $this->whatsapp_model->registry_get_connection($slug);
        $data['numbers']       = $this->whatsapp_model->registry_numbers_for_tenant($slug);
        $data['stats']         = $this->whatsapp_model->get_stats();
        $data['country_code']  = get_option('whatsapp_default_country_code') ?: '91';

        /**
         * Shared number ("our WhatsApp"). The mirrored template library is
         * refreshed before anything reads the template table, so a template the
         * provider granted a moment ago shows up on this very page load — and,
         * on an account whose grant was revoked, the leftovers are dropped. The
         * call self-throttles to one pull every 5 minutes and is a no-op on an
         * account that never had a grant.
         */
        $data['shared'] = whatsapp_shared_status();
        whatsapp_shared_sync_templates();

        // Per-tab data is only queried when the staff member may see that tab,
        // so a restricted role never receives rows it cannot open. Templates
        // also feed the Send / Bulk / Inbox pickers.
        $data['templates'] = ($this->_can_templates() || $this->_can_send() || $this->_can_bulk() || $this->_can_inbox())
            ? $this->whatsapp_model->get_templates()
            : [];
        $data['campaigns']    = $this->_can_bulk() ? $this->whatsapp_model->get_campaigns() : [];
        $data['rules']        = $this->_can_bot() ? $this->whatsapp_model->get_rules() : [];
        $data['bot_settings'] = $this->whatsapp_model->get_bot_settings();
        $data['contacts']     = $this->_can_contacts() ? $this->whatsapp_model->get_contacts() : [];
        $data['notify']       = whatsapp_notify_settings();
        $data['notify_staff'] = $this->_can_settings() ? whatsapp_notify_inbox_staff() : [];
        $data['series']       = $this->_can_view() ? $this->whatsapp_model->get_activity_series(14) : [];

        // Health older than 10 minutes is refreshed in the background by the JS
        // (a live Graph round-trip per number is too slow to block the page).
        $data['health_stale'] = true;
        foreach ($data['numbers'] as $n) {
            if (!empty($n->last_checked_at) && (time() - strtotime($n->last_checked_at)) < 600) {
                $data['health_stale'] = false;
                break;
            }
        }

        // Master-only: provider app credentials + tenant overview. The console
        // holds the app secret and system-user token, so it additionally needs
        // the Settings capability — $is_master drives the view block.
        $data['is_master'] = whatsapp_is_master() && $this->_can_settings();

        if ($data['is_master']) {
            // Self-heal: guarantee a verify token + absolute URLs exist even on
            // installs where the seed didn't run (core get_option() returns ''
            // for missing options, so blank means "not seeded yet").
            if (get_option('whatsapp_verify_token') === '') {
                update_option('whatsapp_verify_token', bin2hex(random_bytes(16)));
            }
            if (get_option('whatsapp_webhook_url') === '') {
                update_option('whatsapp_webhook_url', admin_url('whatsapp/webhook'));
            }
            if (get_option('whatsapp_oauth_callback_url') === '') {
                update_option('whatsapp_oauth_callback_url', admin_url('whatsapp/oauth_callback'));
            }

            // Read back through the config helper, which goes to the options
            // TABLE — get_option() would answer these autoloaded names from the
            // copy core took before the self-heal above ran.
            $central = whatsapp_central_config(true);
            $data['central']      = $central;
            $data['app_id']       = $central['app_id'];
            $data['app_secret']   = $central['app_secret'];
            $data['config_id']    = $central['config_id'];
            $data['verify_token'] = $central['verify_token'];
            $data['webhook_url']  = $central['webhook_url'];
            $data['callback_url'] = $central['oauth_callback_url'];
            $data['last_resync']  = (string) get_option('whatsapp_last_resync_at');
            $data['connections']  = $this->whatsapp_model->registry_all_connections();
            $data['billing']      = whatsapp_provider_billing();
            $data['billing_ready'] = whatsapp_credit_sharing_ready();

            // Provider console for the shared number.
            $data = array_merge($data, $this->_shared_console_data());
        }

        $data['title']     = _l('wapi_whatsapp');
        $data['bodyclass'] = 'whatsapp-module';
        $this->load->view('dashboard', $data);
    }

    /* ────────── Master: shared number ("our WhatsApp") console ────────── */

    /** Only the provider may touch grants — never a tenant, however permissioned. */
    private function _require_provider()
    {
        if (!whatsapp_is_master() || !$this->_can_settings()) {
            ajax_access_denied();
        }
    }

    /** Data every render of the console needs. */
    private function _shared_console_data()
    {
        $grants = [];
        foreach ($this->whatsapp_model->shared_grants_all() as $g) {
            $grants[$g->tenant_slug] = $g;
        }

        return [
            'shared_settings'  => whatsapp_shared_settings(),
            'shared_grants'    => $grants,
            'shared_tenants'   => $this->whatsapp_model->shared_tenants(),
            'shared_numbers'   => $this->whatsapp_model->registry_numbers_for_tenant(whatsapp_shared_provider_slug()),
            'shared_templates' => $this->whatsapp_model->shared_provider_templates(),
        ];
    }

    /** Global switches: on/off, the brand tenants see, the default number. */
    public function save_shared_settings()
    {
        $this->_require_provider();

        update_option('whatsapp_shared_enabled', $this->input->post('shared_enabled') ? '1' : '0');
        update_option('whatsapp_shared_brand', trim((string) $this->input->post('shared_brand', true)));
        update_option('whatsapp_shared_number', trim((string) $this->input->post('shared_number', true)));

        $this->_json(['success' => true, 'message' => _l('wapi_shared_settings_saved')]);
    }

    /** Re-render just the tenant table after a change. */
    public function shared_console()
    {
        $this->_require_provider();
        $this->_json([
            'success' => true,
            'html'    => $this->load->view('partials/shared_table', $this->_shared_console_data(), true),
        ]);
    }

    /** The configure-a-tenant modal (blank slug = pick a tenant). */
    public function shared_grant_modal($slug = '')
    {
        $this->_require_provider();

        $slug = trim(urldecode((string) $slug));
        $data = $this->_shared_console_data();

        $data['slug']  = $slug;
        $data['grant'] = $slug !== '' ? $this->whatsapp_model->shared_grant($slug) : null;

        $data['allowed'] = [];
        foreach ($slug !== '' ? $this->whatsapp_model->shared_grant_templates($slug) : [] as $t) {
            $data['allowed'][$t->template_name . '|' . $t->language] = true;
        }

        // Slugs already granted, so "add tenant" cannot create a duplicate.
        $data['taken'] = array_keys($data['shared_grants']);

        $this->_json([
            'success' => true,
            'html'    => $this->load->view('partials/shared_grant_modal', $data, true),
        ]);
    }

    public function save_shared_grant()
    {
        $this->_require_provider();

        $slug = trim((string) $this->input->post('tenant_slug', true));
        if ($slug === '') {
            $this->_json(['success' => false, 'message' => _l('wapi_shared_pick_tenant')]);
            return;
        }
        if ($slug === whatsapp_shared_provider_slug()) {
            $this->_json(['success' => false, 'message' => _l('wapi_shared_not_self')]);
            return;
        }

        // Resolve the tenant so the grant carries a readable name and the
        // client id the CCX Msgs balance is keyed by.
        $tenant = null;
        foreach ($this->whatsapp_model->shared_tenants() as $t) {
            if ((string) $t->slug === $slug) {
                $tenant = $t;
                break;
            }
        }

        // A pinned number must be one of OUR numbers — never a tenant's.
        $number = trim((string) $this->input->post('phone_number_id', true));
        if ($number !== '') {
            $row = $this->whatsapp_model->registry_get_number($number);
            if (!whatsapp_shared_is_provider_number($row)) {
                $this->_json(['success' => false, 'message' => _l('wapi_shared_bad_number')]);
                return;
            }
        }

        $this->whatsapp_model->shared_grant_save($slug, [
            'client_id'       => $tenant ? (int) $tenant->clientid : null,
            'tenant_name'     => $tenant ? $tenant->name : $slug,
            'enabled'         => (int) $this->input->post('enabled'),
            'phone_number_id' => $number,
            'billing_mode'    => $this->input->post('billing_mode', true),
            'daily_limit'     => $this->input->post('daily_limit', true),
            'monthly_limit'   => $this->input->post('monthly_limit', true),
            'allow_send'      => (int) $this->input->post('allow_send'),
            'allow_bulk'      => (int) $this->input->post('allow_bulk'),
            'allow_hooks'     => (int) $this->input->post('allow_hooks'),
            'template_mode'   => $this->input->post('template_mode', true),
            'notes'           => $this->input->post('notes', true),
        ]);

        $this->whatsapp_model->shared_grant_set_templates($slug, (array) $this->input->post('templates'));

        $this->_json([
            'success' => true,
            'message' => sprintf(_l('wapi_shared_grant_saved'), $tenant ? $tenant->name : $slug),
        ]);
    }

    /** Quick enable/disable straight from the table. */
    public function toggle_shared_grant($slug)
    {
        $this->_require_provider();

        $slug  = trim(urldecode((string) $slug));
        $grant = $this->whatsapp_model->shared_grant($slug);
        if (!$grant) {
            $this->_json(['success' => false, 'message' => _l('wapi_shared_no_grant')]);
            return;
        }

        $this->whatsapp_model->shared_grant_save($slug, array_merge((array) $grant, [
            'enabled' => (int) $grant->enabled === 1 ? 0 : 1,
        ]));

        $this->_json(['success' => true, 'message' => _l('wapi_shared_grant_updated')]);
    }

    public function delete_shared_grant($slug)
    {
        $this->_require_provider();
        $this->whatsapp_model->shared_grant_delete(trim(urldecode((string) $slug)));
        $this->_json(['success' => true, 'message' => _l('wapi_shared_grant_removed')]);
    }

    /**
     * Tenant side: pull the provider's allowlisted templates now instead of
     * waiting for the 5-minute throttle.
     */
    public function shared_sync()
    {
        if (!$this->_can_templates()) {
            ajax_access_denied();
        }
        if (!whatsapp_shared_enabled()) {
            $this->_json(['success' => false, 'message' => _l('wapi_shared_not_on')]);
            return;
        }

        $res = whatsapp_shared_sync_templates(true);
        $this->_json([
            'success' => true,
            'message' => sprintf(_l('wapi_shared_templates_synced'), (int) $res['synced']),
        ]);
    }

    /* ───────────────── Master: provider credentials ────────────────── */

    public function save_credentials()
    {
        if (!whatsapp_is_master() || !$this->_can_settings()) {
            ajax_access_denied();
        }
        update_option('whatsapp_app_id', trim($this->input->post('app_id', true)));
        update_option('whatsapp_app_secret', trim($this->input->post('app_secret', true)));
        update_option('whatsapp_config_id', trim($this->input->post('config_id', true)));
        $vt = trim($this->input->post('verify_token', true));
        if ($vt !== '') {
            update_option('whatsapp_verify_token', $vt);
        } elseif (get_option('whatsapp_verify_token') === '') {
            update_option('whatsapp_verify_token', bin2hex(random_bytes(16)));
        }
        // (Re)capture the absolute callback/webhook URLs in master context so
        // they include any custom admin prefix and the correct host.
        update_option('whatsapp_webhook_url', admin_url('whatsapp/webhook'));
        update_option('whatsapp_oauth_callback_url', admin_url('whatsapp/oauth_callback'));

        // Core answers autoloaded options from a copy taken when the request
        // booted, so without this the rest of THIS request — including a resync
        // fired straight after the save — still sees the previous app.
        whatsapp_central_config_reset();

        $this->_json(['success' => true, 'message' => _l('wapi_credentials_saved')]);
    }

    /**
     * Push the credentials that are stored right now through the whole system:
     * URLs, the Meta App itself, the webhook subscription and every tenant's
     * stored token. This is what makes a changed central app take effect
     * instead of leaving the console describing the previous one.
     */
    public function resync_app()
    {
        if (!whatsapp_is_master() || !$this->_can_settings()) {
            ajax_access_denied();
        }
        // One Graph round-trip per tenant per number — a provider with many
        // connected accounts must not be cut off half way through.
        @set_time_limit(600);

        $report = $this->whatsapp_model->resync_central_app();

        $this->_json([
            'success' => empty($report['aborted']),
            'report'  => $report,
            'message' => empty($report['aborted'])
                ? sprintf(_l('wapi_resync_done'), (int) $report['counts']['ok'], (int) $report['counts']['stale'])
                : _l('wapi_resync_aborted'),
        ]);
    }

    /* ─────────── Master: provider billing / shared credit line ─────────── */

    public function save_billing()
    {
        if (!whatsapp_is_master() || !$this->_can_settings()) {
            ajax_access_denied();
        }
        update_option('whatsapp_business_id', preg_replace('/\D+/', '', (string) $this->input->post('business_id', true)));
        update_option('whatsapp_credit_line_id', preg_replace('/\D+/', '', (string) $this->input->post('credit_line_id', true)));

        $currency = strtoupper(preg_replace('/[^A-Za-z]/', '', (string) $this->input->post('credit_currency', true)));
        update_option('whatsapp_credit_currency', strlen($currency) === 3 ? $currency : 'USD');
        update_option('whatsapp_share_credit_line', $this->input->post('share_credit_line') ? '1' : '0');

        // A blank token field means "leave the stored one alone" — the form
        // never renders the secret back to the browser.
        $token = trim((string) $this->input->post('system_user_token', true));
        if ($token !== '') {
            update_option('whatsapp_system_user_token', $token);
        }

        $this->_json(['success' => true, 'message' => _l('wapi_billing_saved')]);
    }

    /** Load the provider's extended credit lines from Meta for the picker. */
    public function credit_lines()
    {
        if (!whatsapp_is_master() || !$this->_can_settings()) {
            ajax_access_denied();
        }
        $res = $this->whatsapp_model->list_credit_lines();
        if (isset($res['error'])) {
            $hint = whatsapp_error_hint($res['error_code'] ?? '', $res['error']);
            $this->_json(['success' => false, 'message' => $res['error'], 'hint' => $hint['hint'] ?? '']);
            return;
        }
        $this->_json([
            'success'   => true,
            'lines'     => $res['lines'],
            'diagnosis' => $res['diagnosis'] ?? null,
            'selected'  => get_option('whatsapp_credit_line_id'),
        ]);
    }

    /** Master console: (re)attach one tenant's WABA to the provider credit line. */
    public function share_credit($slug)
    {
        if (!whatsapp_is_master() || !$this->_can_settings()) {
            ajax_access_denied();
        }
        $res = $this->whatsapp_model->share_credit_line($slug);
        if (isset($res['error'])) {
            $hint = whatsapp_error_hint($res['error_code'] ?? '', $res['error']);
            $this->_json(['success' => false, 'message' => $res['error'], 'hint' => $hint['hint'] ?? '']);
            return;
        }
        $this->_json(['success' => true, 'message' => _l('wapi_credit_shared')]);
    }

    /** Master console: refresh every tenant's usage + cost from Meta. */
    public function refresh_usage()
    {
        if (!whatsapp_is_master() || !$this->_can_settings()) {
            ajax_access_denied();
        }
        $days = (int) $this->input->post('days');
        $days = in_array($days, [7, 30, 90], true) ? $days : 30;

        $res = $this->whatsapp_model->sync_all_tenant_usage($days);
        $this->_json([
            'success' => empty($res['errors']),
            'message' => sprintf(_l('wapi_usage_synced'), (int) $res['synced'], $days)
                . (!empty($res['errors']) ? ' — ' . implode(' | ', array_slice($res['errors'], 0, 2)) : ''),
        ]);
    }

    /* ───────────── Master: webhook configuration & self-test ───────────── */

    /** What Meta has registered, plus a live reachability probe of our URL. */
    public function webhook_check()
    {
        if (!whatsapp_is_master() || !$this->_can_settings()) {
            ajax_access_denied();
        }
        $this->_json(['success' => true, 'report' => $this->whatsapp_model->webhook_report()]);
    }

    /** Register or repair the app-level webhook subscription on Meta. */
    public function webhook_fix()
    {
        if (!whatsapp_is_master() || !$this->_can_settings()) {
            ajax_access_denied();
        }
        // Meta verifies the URL before accepting it — fail fast with the real
        // reason rather than Meta's generic "URL couldn't be validated".
        $test = $this->whatsapp_model->webhook_self_test();
        if (empty($test['ok'])) {
            $this->_json([
                'success' => false,
                'message' => _l('wapi_webhook_selftest_failed'),
                'hint'    => $test['detail'] ?? '',
            ]);
            return;
        }

        $res = $this->whatsapp_model->webhook_subscribe();
        if (isset($res['error'])) {
            $hint = whatsapp_error_hint($res['error_code'] ?? '', $res['error']);
            $this->_json(['success' => false, 'message' => $res['error'], 'hint' => $hint['hint'] ?? '']);
            return;
        }
        $this->_json(['success' => true, 'message' => _l('wapi_webhook_subscribed')]);
    }

    /** Master console: detach a tenant's connection. */
    public function master_disconnect($slug)
    {
        if (!whatsapp_is_master() || !$this->_can_settings()) {
            ajax_access_denied();
        }
        $this->whatsapp_model->registry_delete_connection($slug);
        $this->_json(['success' => true, 'message' => _l('wapi_disconnected')]);
    }

    /* ─────────────────────── OAuth (connect) ───────────────────────── */

    /** Tenant (or master itself) starts the Facebook connect flow. */
    public function connect()
    {
        if (!$this->_can_settings()) {
            access_denied('WhatsApp');
        }
        if (!whatsapp_is_configured()) {
            set_alert('warning', _l('wapi_provider_not_ready'));
            redirect(admin_url('whatsapp'));
        }

        $state = whatsapp_make_state([
            'slug'       => whatsapp_current_slug(),
            'admin_base' => admin_url(), // tenant admin root (for return + ingest)
            'nonce'      => bin2hex(random_bytes(8)),
        ]);

        redirect(whatsapp_login_url($state));
    }

    /**
     * Central OAuth callback (master domain). The visitor authenticated with
     * Facebook, not our admin — trust is established by the signed state.
     */
    public function oauth_callback()
    {
        $err   = $this->input->get('error');
        $state = whatsapp_read_state($this->input->get('state'));

        if (!$state) {
            show_error('Invalid or expired authorization state.', 403);
            return;
        }
        $return_url = rtrim($state['admin_base'], '/') . '/whatsapp';

        if ($err) {
            redirect($return_url . '?whatsapp_error=' . urlencode($this->input->get('error_description') ?: $err));
            return;
        }

        $code = $this->input->get('code');
        if (!$code) {
            redirect($return_url . '?whatsapp_error=' . urlencode('Missing authorization code'));
            return;
        }

        $c = whatsapp_central_config();

        // 1) code → user token
        $short = whatsapp_graph_get('oauth/access_token', [
            'client_id'     => $c['app_id'],
            'redirect_uri'  => whatsapp_redirect_uri(),
            'client_secret' => $c['app_secret'],
            'code'          => $code,
        ]);
        if (isset($short['error'])) {
            redirect($return_url . '?whatsapp_error=' . urlencode($short['error']));
            return;
        }

        // 2) → long-lived token (~60 days)
        $long = whatsapp_graph_get('oauth/access_token', [
            'grant_type'        => 'fb_exchange_token',
            'client_id'         => $c['app_id'],
            'client_secret'     => $c['app_secret'],
            'fb_exchange_token' => $short['access_token'],
        ]);
        $token   = $long['access_token'] ?? $short['access_token'];
        $expires = isset($long['expires_in']) ? date('Y-m-d H:i:s', time() + (int) $long['expires_in']) : null;

        // 3) profile
        $me = whatsapp_graph_get('me', ['fields' => 'id,name'], $token);

        // 4) discover WABAs + phone numbers, subscribe webhooks
        $prov = $this->whatsapp_model->provision_tenant_wabas($state['slug'], $token);

        // 5) store the connection in the master registry
        $this->whatsapp_model->registry_store_connection($state['slug'], [
            'tenant_base_url' => $state['admin_base'],
            'waba_id'         => $prov['waba_id'] ?? null,
            'waba_name'       => $prov['waba_name'] ?? null,
            'fb_user_id'      => $me['id'] ?? null,
            'fb_user_name'    => $me['name'] ?? null,
            'access_token'    => $token,
            'token_expires'   => $expires,
        ]);
        // The token is fresh from the CURRENT central app, so any stale marker a
        // resync wrote against the previous one is now answered.
        $this->whatsapp_model->registry_clear_sync_error($state['slug']);

        // 6) Put the tenant on OUR credit line so Meta invoices the provider and
        //    the tenant is never asked for a card (the Wati/BSP billing model).
        $warnings = $prov['errors'];
        if (whatsapp_credit_sharing_ready() && !empty($prov['waba_id'])) {
            $share = $this->whatsapp_model->share_credit_line($state['slug'], $prov['waba_id']);
            if (isset($share['error'])) {
                $warnings[] = 'Billing: ' . $share['error'];
            }
        } else {
            $this->whatsapp_model->set_credit_status($state['slug'], 'self');
        }

        $q = '?whatsapp_connected=1';
        if (!empty($warnings)) {
            $q .= '&whatsapp_warn=' . urlencode(implode(' | ', array_slice($warnings, 0, 3)));
        }
        redirect($return_url . $q);
    }

    public function disconnect()
    {
        if (!$this->_can_settings()) {
            ajax_access_denied();
        }
        if ($this->_blocked_by_shared()) {
            return;
        }
        $this->whatsapp_model->registry_delete_connection(whatsapp_current_slug());
        $this->_json(['success' => true, 'message' => _l('wapi_disconnected')]);
    }

    /** Re-discover WABA phone numbers with the stored token. */
    public function refresh_numbers()
    {
        if (!$this->_can_settings()) {
            ajax_access_denied();
        }
        if ($this->_blocked_by_shared()) {
            return;
        }
        $slug = whatsapp_current_slug();
        $conn = $this->whatsapp_model->registry_get_connection($slug);
        if (!$conn || empty($conn->access_token)) {
            $this->_json(['success' => false, 'message' => _l('wapi_not_connected')]);
            return;
        }
        $prov = $this->whatsapp_model->provision_tenant_wabas($slug, $conn->access_token);
        $this->_json([
            'success' => empty($prov['errors']),
            'message' => sprintf(_l('wapi_numbers_refreshed'), (int) $prov['numbers'])
                . (!empty($prov['errors']) ? ' — ' . implode(' | ', array_slice($prov['errors'], 0, 2)) : ''),
        ]);
    }

    /* ──────────────── Number health / registration ─────────────────── */

    /** Live Graph pull for every number: registration, quality, tier, health. */
    public function check_numbers()
    {
        if (!$this->_can_view()) {
            ajax_access_denied();
        }
        $res = $this->whatsapp_model->sync_number_health();

        $message = sprintf(
            _l('wapi_health_checked'),
            (int) $res['checked'],
            (int) $res['registered']
        );
        if (!empty($res['errors'])) {
            $message .= ' — ' . implode(' | ', array_slice($res['errors'], 0, 2));
        }

        $this->_json([
            'success'    => empty($res['errors']),
            'message'    => $message,
            'checked'    => (int) $res['checked'],
            'registered' => (int) $res['registered'],
        ]);
    }

    /**
     * Register a number for Cloud API messaging — the fix for
     * "(#133010) Account not registered".
     */
    public function register_number()
    {
        if (!$this->_can_settings()) {
            ajax_access_denied();
        }
        if ($this->_blocked_by_shared()) {
            return;
        }
        $res = $this->whatsapp_model->register_number(
            trim($this->input->post('phone_number_id', true)),
            $this->input->post('pin', true)
        );

        if (isset($res['error'])) {
            $hint = whatsapp_error_hint($res['error_code'] ?? '', $res['error']);
            $this->_json([
                'success' => false,
                'message' => $res['error'],
                'hint'    => $hint['hint'] ?? '',
                'title'   => $hint['title'] ?? '',
            ]);
            return;
        }
        $this->_json([
            'success' => (int) $res['registered'] === 1,
            'message' => (int) $res['registered'] === 1
                ? _l('wapi_number_registered')
                : sprintf(_l('wapi_number_register_pending'), $res['status']),
        ]);
    }

    public function deregister_number()
    {
        if (!$this->_can_settings()) {
            ajax_access_denied();
        }
        if ($this->_blocked_by_shared()) {
            return;
        }
        $res = $this->whatsapp_model->deregister_number(trim($this->input->post('phone_number_id', true)));
        if (isset($res['error'])) {
            $hint = whatsapp_error_hint($res['error_code'] ?? '', $res['error']);
            $this->_json(['success' => false, 'message' => $res['error'], 'hint' => $hint['hint'] ?? '']);
            return;
        }
        $this->_json(['success' => true, 'message' => _l('wapi_number_deregistered')]);
    }

    /** Everything Meta reports for one number (details drawer). */
    public function number_details($phone_number_id)
    {
        if (!$this->_can_view()) {
            ajax_access_denied();
        }
        $profile = $this->whatsapp_model->number_profile($phone_number_id);
        if (!$profile) {
            $this->_json(['success' => false, 'message' => 'Unknown number']);
            return;
        }
        $html = $this->load->view('partials/number_details', $profile, true);
        $this->_json(['success' => true, 'html' => $html]);
    }

    /** Connection self-test + failure breakdown, rendered client-side. */
    public function diagnostics()
    {
        if (!$this->_can_view()) {
            ajax_access_denied();
        }
        // Refresh live health first when the cached state is stale.
        if ($this->input->get('refresh') === '1') {
            $this->whatsapp_model->sync_number_health();
        }

        $diag = $this->whatsapp_model->get_diagnostics();
        $rows = [];
        foreach ($diag['numbers'] as $n) {
            $rows[] = [
                'phone_number_id' => $n->phone_number_id,
                'display'         => (string) $n->display_phone_number,
                'status_html'     => whatsapp_number_status_badge($n),
                'quality_html'    => whatsapp_quality_badge($n->quality_rating ?? ''),
                'health_html'     => whatsapp_health_badge($n->health_can_send ?? ''),
                'tier'            => whatsapp_tier_label($n->messaging_limit_tier ?? ''),
                'checked'         => whatsapp_time_ago($n->last_checked_at ?? null),
                'registered'      => (int) ($n->is_registered ?? 0),
                'last_error'      => (string) ($n->last_error ?? ''),
            ];
        }

        $this->_json([
            'success'  => true,
            'checks'   => $diag['checks'],
            'numbers'  => $rows,
            'failures' => $diag['failures'],
        ]);
    }

    public function set_default_number()
    {
        if (!$this->_can_settings()) {
            ajax_access_denied();
        }
        if ($this->_blocked_by_shared()) {
            return;
        }
        $id = trim($this->input->post('phone_number_id', true));
        if ($id === '') {
            $this->_json(['success' => false, 'message' => 'Missing number']);
            return;
        }
        $this->whatsapp_model->registry_set_default_number(whatsapp_current_slug(), $id);
        $this->_json(['success' => true, 'message' => _l('wapi_default_number_set')]);
    }

    /** Message activity series for the overview chart. */
    public function activity()
    {
        if (!$this->_can_view()) {
            ajax_access_denied();
        }
        $days = (int) $this->input->get('days');
        $days = in_array($days, [7, 14, 30, 90], true) ? $days : 14;

        $this->_json([
            'success' => true,
            'days'    => $days,
            'series'  => $this->whatsapp_model->get_activity_series($days),
        ]);
    }

    /* ──────────────── Business profile (branding) ──────────────────── */

    /** Current public profile for one number, read live from Meta. */
    public function profile($phone_number_id)
    {
        if (!$this->_can_profile()) {
            ajax_access_denied();
        }
        $res = $this->whatsapp_model->get_business_profile($phone_number_id);
        if (isset($res['error'])) {
            $hint = whatsapp_error_hint($res['error_code'] ?? '', $res['error']);
            $this->_json(['success' => false, 'message' => $res['error'], 'hint' => $hint['hint'] ?? '']);
            return;
        }

        $n = $res['number'];
        $this->_json([
            'success'              => true,
            'profile'              => $res['profile'],
            'display_name'         => (string) $n->verified_name,
            'display_phone_number' => (string) $n->display_phone_number,
            'name_status'          => whatsapp_name_status_meta($n->name_status ?? ''),
        ]);
    }

    /**
     * Save the public profile. Accepts an optional `picture` file in the same
     * multipart request, which is uploaded to Meta first for its handle.
     */
    public function save_profile()
    {
        if (!$this->_can_profile()) {
            ajax_access_denied();
        }
        if ($this->_blocked_by_shared()) {
            return;
        }
        $phone_number_id = trim($this->input->post('phone_number_id', true));
        if ($phone_number_id === '') {
            $this->_json(['success' => false, 'message' => 'Choose a phone number first.']);
            return;
        }

        $handle = null;
        if (!empty($_FILES['picture']['name'])) {
            $upload = $this->whatsapp_model->upload_profile_picture($phone_number_id, $_FILES['picture']);
            if (isset($upload['error'])) {
                $this->_json(['success' => false, 'message' => $upload['error']]);
                return;
            }
            $handle = $upload['handle'];
        }

        $res = $this->whatsapp_model->save_business_profile($phone_number_id, [
            'about'       => (string) $this->input->post('about'),
            'description' => (string) $this->input->post('description'),
            'address'     => (string) $this->input->post('address'),
            'email'       => (string) $this->input->post('email', true),
            'vertical'    => (string) $this->input->post('vertical', true),
            'websites'    => [
                (string) $this->input->post('website_1', true),
                (string) $this->input->post('website_2', true),
            ],
        ], $handle);

        if (isset($res['error'])) {
            $hint = whatsapp_error_hint($res['error_code'] ?? '', $res['error']);
            $this->_json(['success' => false, 'message' => $res['error'], 'hint' => $hint['hint'] ?? '']);
            return;
        }
        $this->_json([
            'success' => true,
            'message' => $handle ? _l('wapi_profile_saved_with_picture') : _l('wapi_profile_saved'),
        ]);
    }

    /** Submit a new display name for Meta review. */
    public function request_display_name()
    {
        if (!$this->_can_profile()) {
            ajax_access_denied();
        }
        if ($this->_blocked_by_shared()) {
            return;
        }
        $res = $this->whatsapp_model->request_display_name(
            trim($this->input->post('phone_number_id', true)),
            (string) $this->input->post('display_name', true)
        );
        if (isset($res['error'])) {
            $hint = whatsapp_error_hint($res['error_code'] ?? '', $res['error']);
            $this->_json(['success' => false, 'message' => $res['error'], 'hint' => $hint['hint'] ?? '']);
            return;
        }
        $this->_json(['success' => true, 'message' => _l('wapi_display_name_submitted')]);
    }

    /* ─────────────────────────── Templates ─────────────────────────── */

    public function sync_templates()
    {
        if (!$this->_can_templates()) {
            ajax_access_denied();
        }
        // On the shared number the library is the provider's allowlist, not a
        // WABA this account can pull from — sync that instead.
        if (whatsapp_shared_enabled()) {
            $this->shared_sync();
            return;
        }
        $res     = $this->whatsapp_model->sync_templates();
        $message = sprintf(_l('wapi_templates_synced'), $res['synced'] ?? 0);

        // A fresh library is the moment to re-map the Official WhatsApp channel
        // in Omni Messaging, so newly approved templates are pickable in Hooks
        // straight away.
        if (!isset($res['error']) && function_exists('whatsapp_omni_autosync_templates')) {
            $omni  = whatsapp_omni_autosync_templates(true);
            $parts = array_filter([
                $omni['imported'] ? $omni['imported'] . ' added' : '',
                $omni['linked'] ? $omni['linked'] . ' mapped' : '',
                $omni['updated'] ? $omni['updated'] . ' updated' : '',
                $omni['retired'] ? $omni['retired'] . ' deactivated' : '',
            ]);
            if ($parts) {
                $message .= ' Omni Messaging templates: ' . implode(', ', $parts) . '.';
            }
        }

        $this->_json($this->_result($res, $message));
    }

    public function create_template()
    {
        if (!$this->_can_templates()) {
            ajax_access_denied();
        }
        if ($this->_blocked_by_shared()) {
            return;
        }
        $res = $this->whatsapp_model->create_template([
            'name'          => $this->input->post('name', true),
            'language'      => $this->input->post('language', true),
            'category'      => $this->input->post('category', true),
            'header_text'   => $this->input->post('header_text', true),
            'body_text'     => $this->input->post('body_text'),
            'footer_text'   => $this->input->post('footer_text', true),
            'samples'       => (array) $this->input->post('samples'),
            'header_sample' => $this->input->post('header_sample', true),
        ]);
        $this->_json($this->_result($res, _l('wapi_template_submitted')));
    }

    /**
     * One template's full detail — rendered drawer plus the flat payload the
     * edit form prefills from (so View and Edit share a single round-trip).
     */
    public function template_details($id)
    {
        if (!$this->_can_templates()) {
            ajax_access_denied();
        }
        $tpl = $this->whatsapp_model->get_template_row($id);
        if (!$tpl) {
            $this->_json(['success' => false, 'message' => 'Unknown template']);
            return;
        }
        $this->_json([
            'success'  => true,
            'html'     => $this->load->view('partials/template_details', ['tpl' => $tpl], true),
            'template' => whatsapp_template_form_payload($tpl),
        ]);
    }

    /** Edit an existing template and resubmit it to Meta for review. */
    public function update_template()
    {
        if (!$this->_can_templates()) {
            ajax_access_denied();
        }
        if ($this->_blocked_by_shared()) {
            return;
        }
        $res = $this->whatsapp_model->update_template((int) $this->input->post('id'), [
            'category'      => $this->input->post('category', true),
            'header_text'   => $this->input->post('header_text', true),
            'body_text'     => $this->input->post('body_text'),
            'footer_text'   => $this->input->post('footer_text', true),
            'samples'       => (array) $this->input->post('samples'),
            'header_sample' => $this->input->post('header_sample', true),
        ]);
        $this->_json($this->_result($res, _l('wapi_template_updated')));
    }

    public function delete_template()
    {
        if (!$this->_can_templates()) {
            ajax_access_denied();
        }
        if ($this->_blocked_by_shared()) {
            return;
        }
        $res = $this->whatsapp_model->delete_template(trim($this->input->post('name', true)));
        $this->_json($this->_result($res, _l('wapi_template_deleted')));
    }

    /** Template picker feed — shared by the Templates, Send, Bulk and Inbox tabs. */
    public function templates_json()
    {
        if (!$this->_can_templates() && !$this->_can_send() && !$this->_can_bulk() && !$this->_can_inbox()) {
            ajax_access_denied();
        }
        $this->_json(['success' => true, 'templates' => $this->whatsapp_model->get_templates(true)]);
    }

    /* ──────────────────────── Single send ──────────────────────────── */

    public function send_single()
    {
        if (!$this->_can_send()) {
            ajax_access_denied();
        }
        if ($this->_shared_traffic_blocked('send')) {
            return;
        }
        $number = $this->whatsapp_model->default_number();
        $phone_number_id = trim($this->input->post('phone_number_id', true)) ?: ($number ? $number->phone_number_id : '');
        if ($phone_number_id === '') {
            $this->_json(['success' => false, 'message' => _l('wapi_not_connected')]);
            return;
        }

        $to = whatsapp_normalize_phone($this->input->post('to', true));
        if ($to === '' || strlen($to) < 8) {
            $this->_json(['success' => false, 'message' => _l('wapi_invalid_number')]);
            return;
        }

        $mode = $this->input->post('mode', true) === 'template' ? 'template' : 'text';
        if ($mode === 'template') {
            $tpl_name = trim($this->input->post('template_name', true));
            $tpl_lang = trim($this->input->post('template_language', true)) ?: 'en';
            $params   = array_filter(array_map('trim', explode(',', (string) $this->input->post('params', true))), 'strlen');

            // A template with an IMAGE/VIDEO/DOCUMENT header cannot go out
            // without its header media — catch it here instead of surfacing
            // Meta's cryptic "132012 parameter format mismatch".
            $header_media = trim((string) $this->input->post('header_media_url', true));
            $tpl_row      = $this->whatsapp_model->get_template($tpl_name, $tpl_lang);
            if ($tpl_row && (int) $tpl_row->has_media_header === 1 && $header_media === '') {
                $this->_json(['success' => false, 'message' => _l('wapi_template_media_required')]);
                return;
            }

            // A template send opens (or rides inside) a billable 24-hour
            // conversation — gate it against the WhatsApp credit balance.
            $category = whatsapp_conversation_category_for($tpl_name, $tpl_lang, 'trans');
            $gate     = whatsapp_credits_precheck($to, $category);
            if ($gate !== true) {
                $this->_json([
                    'success' => false,
                    'title'   => _l('wapi_credits_exhausted_title'),
                    'message' => $gate['error'],
                    'hint'    => _l('wapi_credits_exhausted_hint'),
                ]);
                return;
            }

            $res  = $this->whatsapp_model->send_template($phone_number_id, $to, $tpl_name, $tpl_lang, array_values($params), $header_media);
            $body = $this->whatsapp_model->render_template_preview($tpl_name, $tpl_lang, array_values($params));
            $type = 'template';
        } else {
            $body = trim((string) $this->input->post('message'));
            if ($body === '') {
                $this->_json(['success' => false, 'message' => _l('wapi_message_required')]);
                return;
            }
            $contact = $this->whatsapp_model->get_contact($to);
            if (!$contact || !whatsapp_session_window_open($contact->last_incoming_at)) {
                // Say *why* it is closed — "never messaged you" and "messaged
                // you three days ago" need very different actions from the user.
                $never = !$contact || empty($contact->last_incoming_at);
                $hint  = $never
                    ? _l('wapi_window_never_hint')
                    : sprintf(_l('wapi_window_expired_hint'), whatsapp_time_ago($contact->last_incoming_at));

                if ($never && (string) get_option('whatsapp_last_webhook_at') === '') {
                    $hint .= ' ' . _l('wapi_window_no_webhook_hint');
                }

                $this->_json([
                    'success'       => false,
                    'window_closed' => true,
                    'title'         => _l('wapi_window_closed_badge'),
                    'message'       => _l('wapi_window_closed'),
                    'hint'          => $hint,
                ]);
                return;
            }
            $res  = $this->whatsapp_model->send_text($phone_number_id, $to, $body);
            $type = 'text';
            $tpl_name = null;
            // Free-form only ever leaves inside the customer's own window, and
            // Meta does not bill service conversations — recorded, never charged.
            $category = 'service';
        }

        $this->whatsapp_model->upsert_contact($to, ['last_outgoing_at' => date('Y-m-d H:i:s')]);
        $this->whatsapp_model->log_message([
            'phone_number_id' => $phone_number_id,
            'direction'       => 'outgoing',
            'phone'           => $to,
            'type'            => $type,
            'body'            => $body,
            'template_name'   => $type === 'template' ? $tpl_name : null,
            'wamid'           => $res['wamid'] ?? null,
            'status'          => isset($res['error']) ? 'failed' : 'accepted',
            'error_message'   => isset($res['error']) ? substr($res['error'], 0, 480) : null,
            'error_code'      => $res['error_code'] ?? null,
        ]);

        if (isset($res['error'])) {
            $hint = whatsapp_error_hint($res['error_code'] ?? '', $res['error']);
            $this->_json([
                'success' => false,
                'message' => $res['error'],
                'title'   => $hint['title'] ?? '',
                'hint'    => $hint['hint'] ?? '',
                'action'  => $hint['action'] ?? '',
            ]);
            return;
        }

        whatsapp_credits_commit($to, $category, ['source' => 'send']);

        $this->_json(['success' => true, 'message' => _l('wapi_message_sent'), 'phone' => $to]);
    }

    /* ──────────────────────── Chat inbox ───────────────────────────── */

    public function get_chat_threads()
    {
        if (!$this->_can_inbox()) {
            ajax_access_denied();
        }
        $scope   = $this->input->get('scope', true) === 'all' ? 'all' : 'conversations';
        $search  = (string) $this->input->get('search', true);
        $threads = $this->whatsapp_model->get_chat_threads(200, $scope, $search);

        foreach ($threads as $t) {
            $t->window_open = whatsapp_session_window_open($t->last_incoming_at);
            $t->last_ago    = whatsapp_time_ago($t->last_time);
        }
        $this->_json([
            'success' => true,
            'threads' => $threads,
            'scope'   => $scope,
            'summary' => $this->whatsapp_model->get_inbox_summary(),
        ]);
    }

    public function get_chat_messages()
    {
        if (!$this->_can_inbox()) {
            ajax_access_denied();
        }
        $phone = whatsapp_normalize_phone($this->input->get('phone', true) ?: $this->input->post('phone', true));
        if ($phone === '') {
            $this->_json(['success' => false, 'message' => _l('wapi_invalid_number')]);
            return;
        }
        $contact  = $this->whatsapp_model->get_contact($phone);
        $messages = $this->whatsapp_model->get_chat_messages($phone);
        $this->whatsapp_model->mark_read($phone);
        $this->_json([
            'success'     => true,
            'messages'    => $messages,
            'window_open' => $contact ? whatsapp_session_window_open($contact->last_incoming_at) : false,
            'contact'     => $contact,
        ]);
    }

    /**
     * Feed for the global inbox notifier (views/_notify_script.php).
     *
     * Polled from EVERY admin page, so it stays deliberately lean: no CSRF
     * rotation, no view data, and a hard 0 for staff who cannot open the inbox
     * rather than an access-denied page the poller would have to parse.
     */
    public function unread_inbox()
    {
        // Read-only poll: hand the MySQL session row lock back straight away so
        // this doesn't serialise the user's other in-flight AJAX calls. The
        // CCX helper is optional — it is not defined on every install, and an
        // unguarded call fataled this endpoint (500) on every poll.
        if (function_exists('ccx_release_session_lock')) {
            ccx_release_session_lock();
        } elseif (function_exists('session_write_close')) {
            @session_write_close();
        }

        header('Content-Type: application/json');
        header('Cache-Control: no-store, no-cache, must-revalidate');

        $settings = whatsapp_notify_settings();
        if (!$this->_can_inbox() || !$settings['enabled']) {
            echo json_encode(['count' => 0, 'items' => []]);
            return;
        }

        $payload = json_encode([
            'count' => $this->whatsapp_model->get_unread_total(),
            'items' => $this->whatsapp_model->get_unread_inbox_items(6),
        ], JSON_INVALID_UTF8_SUBSTITUTE);

        echo $payload === false ? json_encode(['count' => 0, 'items' => []]) : $payload;
    }

    public function save_notify_settings()
    {
        if (!$this->_can_settings()) {
            ajax_access_denied();
        }
        $staff = $this->input->post('staff');
        $saved = whatsapp_notify_save_settings([
            'enabled'    => $this->input->post('enabled'),
            'toast'      => $this->input->post('toast'),
            'desktop'    => $this->input->post('desktop'),
            'sound'      => $this->input->post('sound'),
            'bell'       => $this->input->post('bell'),
            'recipients' => $this->input->post('recipients', true),
            'staff'      => is_array($staff) ? $staff : array_filter(explode(',', (string) $staff)),
            'throttle'   => $this->input->post('throttle'),
        ]);

        $this->_json(['success' => true, 'message' => _l('wapi_settings_saved'), 'settings' => $saved]);
    }

    public function send_chat()
    {
        // Replying from the inbox needs both the tab and the send right.
        if (!$this->_can_inbox() || !$this->_can_send()) {
            ajax_access_denied();
        }
        // Reuses the single-send pipeline (window check included).
        $this->send_single();
    }

    /**
     * Inbox attachment send — image / video / audio / document as a free-form
     * message. Media follows the same rule as text: it may only travel inside
     * the customer's open 24-hour session window.
     */
    public function send_chat_media()
    {
        if (!$this->_can_inbox() || !$this->_can_send()) {
            ajax_access_denied();
        }
        $number = $this->whatsapp_model->default_number();
        $phone_number_id = trim($this->input->post('phone_number_id', true)) ?: ($number ? $number->phone_number_id : '');
        if ($phone_number_id === '') {
            $this->_json(['success' => false, 'message' => _l('wapi_not_connected')]);
            return;
        }

        $to = whatsapp_normalize_phone($this->input->post('to', true));
        if ($to === '' || strlen($to) < 8) {
            $this->_json(['success' => false, 'message' => _l('wapi_invalid_number')]);
            return;
        }

        $contact = $this->whatsapp_model->get_contact($to);
        if (!$contact || !whatsapp_session_window_open($contact->last_incoming_at)) {
            $this->_json([
                'success'       => false,
                'window_closed' => true,
                'title'         => _l('wapi_window_closed_badge'),
                'message'       => _l('wapi_window_closed'),
            ]);
            return;
        }

        if (empty($_FILES['file']['name'])) {
            $this->_json(['success' => false, 'message' => _l('wapi_attach_no_file')]);
            return;
        }

        $upload = $this->whatsapp_model->upload_media($phone_number_id, $_FILES['file']);
        if (isset($upload['error'])) {
            $this->_json(['success' => false, 'message' => $upload['error']]);
            return;
        }

        $caption = trim((string) $this->input->post('caption'));
        // Meta rejects captions on audio and stickers — don't log one that
        // never actually went out.
        if (!in_array($upload['kind'], ['image', 'video', 'document'], true)) {
            $caption = '';
        }
        $res = $this->whatsapp_model->send_media(
            $phone_number_id,
            $to,
            $upload['kind'],
            $upload['media_id'],
            $caption,
            (string) $_FILES['file']['name']
        );

        $this->whatsapp_model->upsert_contact($to, ['last_outgoing_at' => date('Y-m-d H:i:s')]);
        $this->whatsapp_model->log_message([
            'phone_number_id' => $phone_number_id,
            'direction'       => 'outgoing',
            'phone'           => $to,
            'type'            => $upload['kind'],
            'body'            => $caption,
            'media_id'        => $upload['media_id'],
            'media_mime'      => $upload['mime'],
            'caption'         => $caption !== '' ? $caption : null,
            'wamid'           => $res['wamid'] ?? null,
            'status'          => isset($res['error']) ? 'failed' : 'accepted',
            'error_message'   => isset($res['error']) ? substr($res['error'], 0, 480) : null,
            'error_code'      => $res['error_code'] ?? null,
        ]);

        if (isset($res['error'])) {
            $hint = whatsapp_error_hint($res['error_code'] ?? '', $res['error']);
            $this->_json([
                'success' => false,
                'message' => $res['error'],
                'title'   => $hint['title'] ?? '',
                'hint'    => $hint['hint'] ?? '',
                'action'  => $hint['action'] ?? '',
            ]);
            return;
        }

        // Free-form media rides the customer's own window — a service
        // conversation, recorded but never charged (same as free text).
        whatsapp_credits_commit($to, 'service', ['source' => 'send']);

        $this->_json(['success' => true, 'message' => _l('wapi_message_sent'), 'phone' => $to]);
    }

    /** Proxy an inbound media file (the lookaside URL needs the Bearer token). */
    public function media($message_id)
    {
        if (!$this->_can_inbox()) {
            access_denied('WhatsApp');
        }
        $msg = $this->whatsapp_model->get_message($message_id);
        if (!$msg || empty($msg->media_id)) {
            show_404();
            return;
        }
        $number = $msg->phone_number_id ? $this->whatsapp_model->registry_get_number($msg->phone_number_id) : null;
        $token  = $number
            ? $this->whatsapp_model->token_for_number($number->phone_number_id)
            : (($n = $this->whatsapp_model->default_number()) ? $this->whatsapp_model->token_for_number($n->phone_number_id) : null);
        if (!$token) {
            show_error('WhatsApp is not connected.', 400);
            return;
        }
        $bin = whatsapp_fetch_media_binary($msg->media_id, $token);
        if (isset($bin['error'])) {
            show_error(e($bin['error']), 502);
            return;
        }
        header('Content-Type: ' . $bin['mime']);
        header('Content-Length: ' . strlen($bin['body']));
        header('Content-Disposition: inline; filename="whatsapp-media-' . (int) $message_id . '"');
        // The chat re-renders while polling; without caching every render
        // re-downloads the file from Meta through this proxy.
        header('Cache-Control: private, max-age=86400');
        echo $bin['body'];
    }

    public function toggle_optout()
    {
        if (!$this->_can_contacts()) {
            ajax_access_denied();
        }
        $phone = whatsapp_normalize_phone($this->input->post('phone', true));
        if ($phone === '') {
            $this->_json(['success' => false, 'message' => _l('wapi_invalid_number')]);
            return;
        }
        $this->whatsapp_model->set_optout($phone, (int) $this->input->post('opted_out'));
        $this->_json(['success' => true]);
    }

    /* ──────────────────────── Bulk campaigns ───────────────────────── */

    public function recipients_preview()
    {
        if (!$this->_can_bulk()) {
            ajax_access_denied();
        }
        $recipients = $this->whatsapp_model->build_recipients(
            $this->input->post('source', true),
            (string) $this->input->post('manual_numbers')
        );
        $this->_json(['success' => true, 'count' => count($recipients)]);
    }

    public function create_campaign()
    {
        if (!$this->_can_bulk()) {
            ajax_access_denied();
        }
        if ($this->_shared_traffic_blocked('bulk')) {
            return;
        }
        $params = json_decode((string) $this->input->post('params'), true);
        $res = $this->whatsapp_model->create_campaign([
            'name'              => $this->input->post('name', true),
            'phone_number_id'   => $this->input->post('phone_number_id', true),
            'template_name'     => $this->input->post('template_name', true),
            'template_language' => $this->input->post('template_language', true),
            'params'            => is_array($params) ? $params : [],
            'header_media_url'  => $this->input->post('header_media_url', true),
            'source'            => $this->input->post('source', true),
            'manual_numbers'    => (string) $this->input->post('manual_numbers'),
            'scheduled_at'      => $this->input->post('scheduled_at', true),
            'batch_size'        => $this->input->post('batch_size', true),
        ], get_staff_user_id());

        if (isset($res['error'])) {
            $this->_json(['success' => false, 'message' => $res['error']]);
            return;
        }
        // Kick the first batch immediately so the campaign starts without
        // waiting for the next cron tick.
        if (($res['status'] ?? '') === 'running') {
            whatsapp_run_automation(0, true);
        }
        $this->_json(['success' => true, 'message' => sprintf(_l('wapi_campaign_created'), (int) $res['recipients'])]);
    }

    public function campaign_action($id)
    {
        if (!$this->_can_bulk()) {
            ajax_access_denied();
        }
        $action = $this->input->post('action', true);
        $map    = ['start' => 'running', 'resume' => 'running', 'pause' => 'paused', 'cancel' => 'cancelled'];
        if (!isset($map[$action])) {
            $this->_json(['success' => false, 'message' => 'Unknown action']);
            return;
        }
        $this->whatsapp_model->set_campaign_status($id, $map[$action]);
        if ($map[$action] === 'running') {
            whatsapp_run_automation(0, true);
        }
        $this->_json(['success' => true]);
    }

    public function delete_campaign($id)
    {
        if (!$this->_can_bulk()) {
            ajax_access_denied();
        }
        $campaign = $this->whatsapp_model->get_campaign($id);
        if ($campaign && in_array($campaign->status, ['running', 'scheduled'], true)) {
            $this->_json(['success' => false, 'message' => _l('wapi_campaign_stop_first')]);
            return;
        }
        $this->whatsapp_model->delete_campaign($id);
        $this->_json(['success' => true, 'message' => _l('wapi_campaign_deleted')]);
    }

    public function campaign_status($id)
    {
        if (!$this->_can_bulk()) {
            ajax_access_denied();
        }
        $campaign = $this->whatsapp_model->get_campaign($id);
        if (!$campaign) {
            $this->_json(['success' => false]);
            return;
        }
        $this->_json(['success' => true, 'campaign' => $campaign]);
    }

    public function campaign_details($id)
    {
        if (!$this->_can_bulk()) {
            ajax_access_denied();
        }
        $campaign = $this->whatsapp_model->get_campaign($id);
        if (!$campaign) {
            $this->_json(['success' => false]);
            return;
        }
        $this->_json([
            'success'    => true,
            'campaign'   => $campaign,
            'recipients' => $this->whatsapp_model->get_campaign_recipients($id),
        ]);
    }

    /** Manual queue tick from the UI ("Process queue now"). */
    public function run_queue()
    {
        if (!$this->_can_bulk()) {
            ajax_access_denied();
        }
        whatsapp_run_automation(0, true);
        $this->_json(['success' => true, 'message' => _l('wapi_queue_processed')]);
    }

    /* ─────────────────────────── Bot rules ─────────────────────────── */

    public function get_rule($id)
    {
        if (!$this->_can_bot()) {
            ajax_access_denied();
        }
        $rule = $this->whatsapp_model->get_rule($id);
        $this->_json(['success' => (bool) $rule, 'rule' => $rule]);
    }

    public function save_rule()
    {
        if (!$this->_can_bot()) {
            ajax_access_denied();
        }
        $id  = (int) $this->input->post('id');
        $res = $this->whatsapp_model->save_rule($this->input->post(), $id ?: null);
        $this->_json($this->_result($res, _l('wapi_rule_saved')));
    }

    public function toggle_rule($id)
    {
        if (!$this->_can_bot()) {
            ajax_access_denied();
        }
        $this->whatsapp_model->set_rule_enabled($id, (int) $this->input->post('enabled'));
        $this->_json(['success' => true]);
    }

    public function delete_rule($id)
    {
        if (!$this->_can_bot()) {
            ajax_access_denied();
        }
        $this->whatsapp_model->delete_rule($id);
        $this->_json(['success' => true, 'message' => _l('wapi_rule_deleted')]);
    }

    public function save_bot_settings()
    {
        if (!$this->_can_bot()) {
            ajax_access_denied();
        }
        $this->whatsapp_model->save_bot_settings($this->input->post());
        $this->_json(['success' => true, 'message' => _l('wapi_bot_settings_saved')]);
    }

    /* ─────────────────────────── Settings ──────────────────────────── */

    public function save_settings()
    {
        if (!$this->_can_settings()) {
            ajax_access_denied();
        }
        $cc = preg_replace('/\D+/', '', (string) $this->input->post('default_country_code', true));
        update_option('whatsapp_default_country_code', $cc !== '' ? $cc : '91');
        $this->_json(['success' => true, 'message' => _l('wapi_settings_saved')]);
    }

    /* ────────────── Central webhook (master domain) ────────────────── */

    public function webhook()
    {
        // Verification handshake (GET). Meta sends hub.* which PHP maps to hub_*.
        if ($this->input->server('REQUEST_METHOD') === 'GET') {
            if ($this->input->get('hub_mode') === 'subscribe'
                && $this->input->get('hub_verify_token') === whatsapp_central_config()['verify_token']) {
                echo $this->input->get('hub_challenge');
            } else {
                $this->output->set_status_header(403);
                echo 'Verification failed';
            }
            return;
        }

        $raw = file_get_contents('php://input');

        // Record the hit before validating, so a signature/secret problem is
        // distinguishable from "Meta never called us at all".
        whatsapp_note_webhook('received');

        if (!whatsapp_verify_signature($raw, $this->input->get_request_header('X-Hub-Signature-256'))) {
            whatsapp_note_webhook('rejected');
            $this->output->set_status_header(403);
            echo 'Invalid signature';
            return;
        }
        $this->output->set_status_header(200);

        $payload = json_decode($raw, true);
        if (is_array($payload) && ($payload['object'] ?? '') === 'whatsapp_business_account') {
            foreach (($payload['entry'] ?? []) as $entry) {
                $waba_id = $entry['id'] ?? null;
                foreach (($entry['changes'] ?? []) as $change) {
                    $field = $change['field'] ?? '';
                    $value = $change['value'] ?? [];
                    if (!in_array($field, ['messages', 'message_template_status_update'], true) || !is_array($value)) {
                        continue;
                    }
                    $this->_route_change($field, $value, $waba_id);
                }
            }
        }
        echo 'EVENT_RECEIVED';
    }

    /**
     * Route one webhook change to the owning tenant: process locally if it's
     * the current context, otherwise forward (signed) to the tenant's ingest
     * endpoint. Messages route by phone_number_id, template updates by WABA.
     */
    private function _route_change($field, $value, $waba_id)
    {
        $slug = null;
        if ($field === 'messages') {
            $phone_number_id = $value['metadata']['phone_number_id'] ?? null;
            if (!$phone_number_id) {
                return;
            }
            $number = $this->whatsapp_model->registry_get_number($phone_number_id);
            if ($number) {
                $slug = $number->tenant_slug;
            }
        }
        if ($slug === null && $waba_id) {
            $conn = $this->whatsapp_model->registry_get_connection_by_waba($waba_id);
            if ($conn) {
                $slug = $conn->tenant_slug;
            }
        }
        if ($slug === null) {
            return; // not connected to any tenant
        }

        // Same context (single install, or the master's own number) → local.
        if ($slug === whatsapp_current_slug()) {
            $this->whatsapp_model->ingest_change($field, $value);
            return;
        }

        // Forward to the owning tenant.
        $conn = $this->whatsapp_model->registry_get_connection($slug);
        if (!$conn || empty($conn->tenant_base_url)) {
            return;
        }
        $ingest_url = rtrim($conn->tenant_base_url, '/') . '/whatsapp/ingest';
        $body = json_encode(['field' => $field, 'value' => $value, 'ts' => time()]);
        whatsapp_http_request($ingest_url, 'POST', $body, [
            'Content-Type: application/json',
            'X-Whatsapp-Signature: ' . whatsapp_sign_payload($body),
        ]);
    }

    /**
     * Tenant ingest endpoint — receives a signed forward from the central
     * webhook and processes the change in this tenant's context.
     */
    public function ingest()
    {
        $raw = file_get_contents('php://input');
        $sig = $this->input->get_request_header('X-Whatsapp-Signature');

        if (empty($sig) || !hash_equals(whatsapp_sign_payload($raw), $sig)) {
            $this->output->set_status_header(403);
            echo json_encode(['success' => false, 'error' => 'invalid signature']);
            return;
        }

        $data = json_decode($raw, true);
        if (!is_array($data) || empty($data['field']) || !isset($data['value'])) {
            echo json_encode(['success' => false, 'error' => 'bad payload']);
            return;
        }

        whatsapp_note_webhook('received');
        $this->whatsapp_model->ingest_change($data['field'], $data['value']);
        echo json_encode(['success' => true]);
    }
}
