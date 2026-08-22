<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * WhatsApp (Official Cloud API) model.
 *
 * Two data layers (see helpers/whatsapp_helper.php):
 *   • MASTER registry  → whatsapp_connections / whatsapp_numbers
 *                        (tenant ↔ WABA/token, phone_number_id → tenant routing)
 *   • TENANT local     → whatsapp_api_* (messages, contacts, templates,
 *                        campaigns, recipients, bot rules)
 *
 * Registry reads/writes transparently target the master DB: directly when on
 * the master, or cross-database via perfex_saas_raw_query when on a tenant.
 */
class Whatsapp_model extends App_Model
{
    private $p;

    public function __construct()
    {
        parent::__construct();
        $this->p = db_prefix();
        $this->load->helper('whatsapp/whatsapp');
        $this->_ensure_schema();
    }

    private function _ensure_schema()
    {
        // Tenant-local tables must exist wherever the module runs, and the
        // schema stamp re-runs install.php once after a module upgrade so new
        // columns land on installs that were created before they existed.
        $version = defined('WHATSAPP_SCHEMA_VERSION') ? WHATSAPP_SCHEMA_VERSION : '1.4.0';
        if (!$this->db->table_exists($this->p . 'whatsapp_api_messages')
            || (string) get_option('whatsapp_schema_version') !== $version) {
            require(module_dir_path('whatsapp') . 'install.php');
        }
    }

    /* ════════════════════ master-access plumbing ════════════════════ */

    /** Master table prefix for raw SQL (local prefix on master, master prefix on tenant). */
    private function mp()
    {
        if (whatsapp_is_master()) {
            return db_prefix();
        }
        return function_exists('perfex_saas_master_db_prefix') ? perfex_saas_master_db_prefix() : db_prefix();
    }

    private function master_all($sql, $params = [])
    {
        if (whatsapp_is_master()) {
            $q = $this->db->query($sql, $params);
            return $q ? $q->result() : [];
        }
        $res = perfex_saas_raw_query($sql, [], true, true, null, false, true, $params);
        return is_array($res) ? $res : [];
    }

    private function master_row($sql, $params = [])
    {
        if (whatsapp_is_master()) {
            $q = $this->db->query($sql, $params);
            return $q ? $q->row() : null;
        }
        $row = perfex_saas_raw_query_row($sql, [], true, true, $params);
        return $row ?: null;
    }

    private function master_exec($sql, $params = [])
    {
        if (whatsapp_is_master()) {
            return $this->db->query($sql, $params);
        }
        return perfex_saas_raw_query($sql, [], false, true, null, false, true, $params);
    }

    /* ════════════════════ master registry ════════════════════ */

    public function registry_get_connection($slug)
    {
        $mp = $this->mp();
        return $this->master_row("SELECT * FROM {$mp}whatsapp_connections WHERE tenant_slug = ? LIMIT 1", [$slug]);
    }

    public function registry_store_connection($slug, $data)
    {
        $mp = $this->mp();
        $this->master_exec(
            "INSERT INTO {$mp}whatsapp_connections
                (tenant_slug, tenant_base_url, business_id, waba_id, waba_name, fb_user_id, fb_user_name, access_token, token_expires, status)
             VALUES (?,?,?,?,?,?,?,?,?, 'active')
             ON DUPLICATE KEY UPDATE
                tenant_base_url=VALUES(tenant_base_url), business_id=VALUES(business_id),
                waba_id=VALUES(waba_id), waba_name=VALUES(waba_name),
                fb_user_id=VALUES(fb_user_id), fb_user_name=VALUES(fb_user_name),
                access_token=VALUES(access_token), token_expires=VALUES(token_expires),
                status='active', updated_at=CURRENT_TIMESTAMP",
            [
                $slug,
                $data['tenant_base_url'] ?? null,
                $data['business_id'] ?? null,
                $data['waba_id'] ?? null,
                $data['waba_name'] ?? null,
                $data['fb_user_id'] ?? null,
                $data['fb_user_name'] ?? null,
                $data['access_token'] ?? null,
                $data['token_expires'] ?? null,
            ]
        );
    }

    /**
     * Drop the "reconnect required" marker a central-app resync left behind.
     *
     * Called after a successful reconnect. The columns are guarded because a
     * tenant writes this registry cross-database and the MASTER only self-heals
     * its own schema when the provider opens the module.
     */
    public function registry_clear_sync_error($slug)
    {
        $mp   = $this->mp();
        $cols = [];
        foreach ($this->master_all("SHOW COLUMNS FROM {$mp}whatsapp_connections") as $row) {
            $r    = (array) $row;
            $name = $r['Field'] ?? ($r['field'] ?? null);
            if ($name) {
                $cols[$name] = true;
            }
        }
        if (!isset($cols['sync_error'])) {
            return;
        }
        $this->master_exec(
            "UPDATE {$mp}whatsapp_connections SET sync_error = NULL, token_app_id = NULL WHERE tenant_slug = ?",
            [$slug]
        );
    }

    public function registry_delete_connection($slug)
    {
        $mp = $this->mp();
        $this->master_exec("DELETE FROM {$mp}whatsapp_connections WHERE tenant_slug = ?", [$slug]);
        $this->master_exec("DELETE FROM {$mp}whatsapp_numbers WHERE tenant_slug = ?", [$slug]);
    }

    public function registry_get_number($phone_number_id)
    {
        $mp = $this->mp();
        return $this->master_row("SELECT * FROM {$mp}whatsapp_numbers WHERE phone_number_id = ? LIMIT 1", [$phone_number_id]);
    }

    public function registry_numbers_for_tenant($slug)
    {
        $mp = $this->mp();
        return $this->master_all(
            "SELECT * FROM {$mp}whatsapp_numbers WHERE tenant_slug = ? ORDER BY is_default DESC, display_phone_number ASC",
            [$slug]
        );
    }

    /**
     * Columns that actually exist on the master registry table. A tenant writes
     * cross-database, so it must never reference a health column the master has
     * not self-healed yet.
     */
    private function registry_number_columns()
    {
        static $cols = null;
        if ($cols !== null) {
            return $cols;
        }
        $mp   = $this->mp();
        $cols = [];
        foreach ($this->master_all("SHOW COLUMNS FROM {$mp}whatsapp_numbers") as $row) {
            $r = (array) $row;
            $name = $r['Field'] ?? ($r['field'] ?? null);
            if ($name) {
                $cols[$name] = true;
            }
        }
        return $cols;
    }

    /**
     * Upsert a number. Only `phone_number_id`, `tenant_slug` and `is_default`
     * are positional — every other key is written when the master registry has
     * that column, so partial updates (health-only) are safe.
     */
    public function registry_store_number($data)
    {
        $mp        = $this->mp();
        $available = $this->registry_number_columns();

        $optional = [
            'waba_id', 'display_phone_number', 'verified_name', 'quality_rating',
            'status', 'code_verification_status', 'name_status', 'platform_type',
            'throughput_level', 'messaging_limit_tier', 'health_can_send',
            'health_detail', 'is_registered', 'last_error', 'last_checked_at',
        ];

        $columns = ['phone_number_id', 'tenant_slug', 'is_default'];
        $values  = [
            $data['phone_number_id'],
            $data['tenant_slug'],
            isset($data['is_default']) ? (int) $data['is_default'] : 0,
        ];
        $updates = ['tenant_slug=VALUES(tenant_slug)'];

        foreach ($optional as $col) {
            if (!array_key_exists($col, $data) || (!empty($available) && !isset($available[$col]))) {
                continue;
            }
            $columns[] = $col;
            $values[]  = $data[$col];
            $updates[] = $col . '=VALUES(' . $col . ')';
        }
        $updates[] = 'updated_at=CURRENT_TIMESTAMP';

        $this->master_exec(
            "INSERT INTO {$mp}whatsapp_numbers (" . implode(', ', $columns) . ")
             VALUES (" . implode(',', array_fill(0, count($columns), '?')) . ")
             ON DUPLICATE KEY UPDATE " . implode(', ', $updates),
            $values
        );
    }

    public function registry_set_default_number($slug, $phone_number_id)
    {
        $mp = $this->mp();
        $this->master_exec("UPDATE {$mp}whatsapp_numbers SET is_default = 0 WHERE tenant_slug = ?", [$slug]);
        $this->master_exec(
            "UPDATE {$mp}whatsapp_numbers SET is_default = 1 WHERE tenant_slug = ? AND phone_number_id = ?",
            [$slug, $phone_number_id]
        );
    }

    /** All tenant connections (master overview). */
    public function registry_all_connections()
    {
        $mp = $this->mp();
        return $this->master_all(
            "SELECT c.*,
                    (SELECT COUNT(*) FROM {$mp}whatsapp_numbers n WHERE n.tenant_slug = c.tenant_slug) AS number_count,
                    (SELECT COUNT(*) FROM {$mp}whatsapp_numbers n WHERE n.tenant_slug = c.tenant_slug AND n.is_registered = 1) AS active_count
             FROM {$mp}whatsapp_connections c ORDER BY c.updated_at DESC"
        );
    }

    /** Connection owning a WABA (used to route template-status webhooks). */
    public function registry_get_connection_by_waba($waba_id)
    {
        $mp = $this->mp();
        return $this->master_row("SELECT * FROM {$mp}whatsapp_connections WHERE waba_id = ? LIMIT 1", [$waba_id]);
    }

    /**
     * Default (or any) number of the current tenant.
     *
     * An account with no number of its own falls back to the provider's shared
     * number when one has been granted, so every existing caller (Send, Bulk,
     * the Omni bridge) keeps working without knowing which mode it is in.
     */
    public function default_number()
    {
        $numbers = $this->registry_numbers_for_tenant(whatsapp_current_slug());
        foreach ($numbers as $n) {
            if ((int) $n->is_default === 1) {
                return $n;
            }
        }
        if (!empty($numbers)) {
            return $numbers[0];
        }

        return (function_exists('whatsapp_shared_active') && whatsapp_shared_active())
            ? whatsapp_shared_number()
            : null;
    }

    /** Access token used for API calls on a given sender number. */
    public function token_for_number($phone_number_id)
    {
        $number = $this->registry_get_number($phone_number_id);
        if (!$number) {
            return null;
        }
        $conn = $this->registry_get_connection($number->tenant_slug);
        return ($conn && !empty($conn->access_token)) ? $conn->access_token : null;
    }

    private function connection()
    {
        return $this->registry_get_connection(whatsapp_current_slug());
    }

    /**
     * May the current account use this sender number, and how?
     *
     * Every Cloud API call in this model takes a `phone_number_id` that
     * ultimately arrives from a form post, while `token_for_number()` happily
     * resolves the token of WHOEVER owns that number. Without this check a
     * tenant who learns another account's number id could send — or worse,
     * deregister — on it. That was always true; lending the provider's number
     * to tenants makes the id common knowledge, so the check is mandatory.
     *
     * @param string $mode 'send'   — put a message through the number
     *                     'manage' — change the number or its WABA (register,
     *                                profile, display name, templates)
     */
    private function number_permitted($phone_number_id, $mode = 'send')
    {
        $phone_number_id = (string) $phone_number_id;
        if ($phone_number_id === '') {
            return false;
        }

        $number = $this->registry_get_number($phone_number_id);
        if (!$number) {
            return false;
        }

        // Own number — full control.
        if ((string) $number->tenant_slug === whatsapp_current_slug()) {
            return true;
        }

        // The provider's shared number may be SENT through, never managed: the
        // tenant does not own that WhatsApp Business Account.
        if ($mode === 'send' && function_exists('whatsapp_shared_active') && whatsapp_shared_active()) {
            $shared = whatsapp_shared_number();

            return $shared && (string) $shared->phone_number_id === $phone_number_id;
        }

        return false;
    }

    /** Uniform refusal for a number this account has no business touching. */
    private function number_denied($mode = 'send')
    {
        if ($mode === 'manage' && function_exists('whatsapp_shared_block_reason')) {
            $reason = whatsapp_shared_block_reason();
            if ($reason !== '') {
                return ['error' => $reason];
            }
        }

        return ['error' => 'That WhatsApp number does not belong to this account.'];
    }

    /* ════════════════ shared number ("our WhatsApp") grants ════════════════ */

    /**
     * One tenant's shared-number policy, or null.
     *
     * Lives in the MASTER registry so a tenant reads it cross-database and can
     * never edit it — the whole point is that the provider owns the policy.
     */
    public function shared_grant($slug)
    {
        $mp = $this->mp();
        if (!$this->shared_tables_ready()) {
            return null;
        }

        return $this->master_row("SELECT * FROM {$mp}whatsapp_shared_grants WHERE tenant_slug = ? LIMIT 1", [$slug]);
    }

    /**
     * The grant tables only exist once the MASTER has run the v2.0 schema. A
     * tenant on a newer module than its master must degrade to "no grant"
     * instead of throwing on every page.
     */
    private function shared_tables_ready()
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }

        try {
            if (whatsapp_is_master()) {
                $ready = $this->db->table_exists($this->p . 'whatsapp_shared_grants');
            } else {
                $mp    = $this->mp();
                $ready = $this->master_row("SHOW TABLES LIKE '{$mp}whatsapp_shared_grants'") !== null;
            }
        } catch (Exception $e) {
            $ready = false;
        }

        return $ready;
    }

    /** Every grant, with this month's usage attached (master console). */
    public function shared_grants_all()
    {
        if (!$this->shared_tables_ready()) {
            return [];
        }

        $mp    = $this->mp();
        $month = date('Y-m-01');

        return $this->master_all(
            "SELECT g.*,
                    (SELECT COUNT(*) FROM {$mp}whatsapp_shared_templates t WHERE t.tenant_slug = g.tenant_slug) AS template_count,
                    (SELECT COALESCE(SUM(u.messages), 0) FROM {$mp}whatsapp_shared_usage u
                      WHERE u.tenant_slug = g.tenant_slug AND u.usage_date >= ?) AS used_month,
                    (SELECT COALESCE(SUM(u.credits), 0) FROM {$mp}whatsapp_shared_usage u
                      WHERE u.tenant_slug = g.tenant_slug AND u.usage_date >= ?) AS credits_month,
                    (SELECT COALESCE(SUM(u.messages), 0) FROM {$mp}whatsapp_shared_usage u
                      WHERE u.tenant_slug = g.tenant_slug AND u.usage_date = CURDATE()) AS used_today
             FROM {$mp}whatsapp_shared_grants g
             ORDER BY g.enabled DESC, g.tenant_slug ASC",
            [$month, $month]
        );
    }

    /**
     * Create or update a grant. Master context only — the caller is the
     * provider console.
     *
     * @param array $data enabled, phone_number_id, billing_mode, daily_limit,
     *                    monthly_limit, allow_send, allow_bulk, allow_hooks,
     *                    template_mode, notes, client_id, tenant_name
     */
    public function shared_grant_save($slug, $data)
    {
        $mp = $this->mp();

        $this->master_exec(
            "INSERT INTO {$mp}whatsapp_shared_grants
                (tenant_slug, client_id, tenant_name, enabled, phone_number_id, billing_mode,
                 daily_limit, monthly_limit, allow_send, allow_bulk, allow_hooks,
                 template_mode, notes, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE
                client_id=VALUES(client_id), tenant_name=VALUES(tenant_name),
                enabled=VALUES(enabled), phone_number_id=VALUES(phone_number_id),
                billing_mode=VALUES(billing_mode), daily_limit=VALUES(daily_limit),
                monthly_limit=VALUES(monthly_limit), allow_send=VALUES(allow_send),
                allow_bulk=VALUES(allow_bulk), allow_hooks=VALUES(allow_hooks),
                template_mode=VALUES(template_mode), notes=VALUES(notes),
                updated_at=CURRENT_TIMESTAMP",
            [
                $slug,
                isset($data['client_id']) ? (int) $data['client_id'] : null,
                isset($data['tenant_name']) ? substr((string) $data['tenant_name'], 0, 190) : null,
                !empty($data['enabled']) ? 1 : 0,
                (string) ($data['phone_number_id'] ?? '') ?: null,
                ($data['billing_mode'] ?? '') === 'free' ? 'free' : 'credits',
                max(0, (int) ($data['daily_limit'] ?? 0)),
                max(0, (int) ($data['monthly_limit'] ?? 0)),
                !empty($data['allow_send']) ? 1 : 0,
                !empty($data['allow_bulk']) ? 1 : 0,
                !empty($data['allow_hooks']) ? 1 : 0,
                ($data['template_mode'] ?? '') === 'all' ? 'all' : 'selected',
                isset($data['notes']) ? substr((string) $data['notes'], 0, 490) : null,
                get_staff_user_id(),
            ]
        );
    }

    public function shared_grant_delete($slug)
    {
        $mp = $this->mp();
        $this->master_exec("DELETE FROM {$mp}whatsapp_shared_grants WHERE tenant_slug = ?", [$slug]);
        $this->master_exec("DELETE FROM {$mp}whatsapp_shared_templates WHERE tenant_slug = ?", [$slug]);
    }

    /** The template allowlist rows for one tenant. */
    public function shared_grant_templates($slug)
    {
        if (!$this->shared_tables_ready()) {
            return [];
        }

        $mp = $this->mp();

        return $this->master_all(
            "SELECT * FROM {$mp}whatsapp_shared_templates WHERE tenant_slug = ? ORDER BY template_name ASC",
            [$slug]
        );
    }

    /**
     * Replace a tenant's allowlist.
     *
     * @param array $templates ['name|language', ...] as posted by the console
     */
    public function shared_grant_set_templates($slug, $templates)
    {
        $mp = $this->mp();
        $this->master_exec("DELETE FROM {$mp}whatsapp_shared_templates WHERE tenant_slug = ?", [$slug]);

        $seen = [];
        foreach ((array) $templates as $entry) {
            $parts    = explode('|', (string) $entry, 2);
            $name     = trim($parts[0]);
            $language = trim($parts[1] ?? 'en') ?: 'en';
            $key      = $name . '|' . $language;

            if ($name === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $this->master_exec(
                "INSERT IGNORE INTO {$mp}whatsapp_shared_templates (tenant_slug, template_name, language) VALUES (?,?,?)",
                [$slug, $name, $language]
            );
        }
    }

    /**
     * The provider's own APPROVED template library, read from the master
     * database. This is what a shared tenant may be given access to.
     */
    public function shared_provider_templates()
    {
        $mp = $this->mp();

        return $this->master_all(
            "SELECT template_id, name, language, category, status, components, body_text,
                    variables_count, has_media_header
             FROM {$mp}whatsapp_api_templates
             WHERE status = 'APPROVED'
             ORDER BY name ASC"
        );
    }

    /** Add one metered send to a tenant's daily counters. */
    public function shared_usage_add($slug, $messages = 1, $conversations = 0, $credits = 0)
    {
        if (!$this->shared_tables_ready()) {
            return;
        }

        $mp = $this->mp();
        $this->master_exec(
            "INSERT INTO {$mp}whatsapp_shared_usage (tenant_slug, usage_date, messages, conversations, credits)
             VALUES (?, CURDATE(), ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                messages = messages + VALUES(messages),
                conversations = conversations + VALUES(conversations),
                credits = credits + VALUES(credits)",
            [$slug, (int) $messages, (int) $conversations, (int) $credits]
        );
    }

    /**
     * Today's and this month's usage for one tenant.
     *
     * @return array{today:int, month:int, credits_month:int, conversations_month:int}
     */
    public function shared_usage_summary($slug)
    {
        $empty = ['today' => 0, 'month' => 0, 'credits_month' => 0, 'conversations_month' => 0];
        if (!$this->shared_tables_ready()) {
            return $empty;
        }

        $mp  = $this->mp();
        $row = $this->master_row(
            "SELECT
                COALESCE(SUM(CASE WHEN usage_date = CURDATE() THEN messages ELSE 0 END), 0) AS today,
                COALESCE(SUM(messages), 0) AS month,
                COALESCE(SUM(credits), 0) AS credits_month,
                COALESCE(SUM(conversations), 0) AS conversations_month
             FROM {$mp}whatsapp_shared_usage
             WHERE tenant_slug = ? AND usage_date >= ?",
            [$slug, date('Y-m-01')]
        );

        if (!$row) {
            return $empty;
        }

        return [
            'today'               => (int) $row->today,
            'month'               => (int) $row->month,
            'credits_month'       => (int) $row->credits_month,
            'conversations_month' => (int) $row->conversations_month,
        ];
    }

    /**
     * Every SaaS tenant the provider could lend the number to (master only).
     *
     * @return array [{slug, name, clientid, status}]
     */
    public function shared_tenants()
    {
        if (!function_exists('perfex_saas_table')) {
            return [];
        }

        $table = perfex_saas_table('companies');
        if (!$this->db->table_exists($table)) {
            return [];
        }

        return $this->db->select('slug, name, clientid, status')
            ->order_by('name', 'ASC')
            ->get($table)->result();
    }

    /* ════════════════ OAuth provisioning (master context) ════════════════ */

    /**
     * After token exchange, discover the WhatsApp Business Accounts the user
     * granted, register their phone numbers to the tenant and subscribe the
     * central app to each WABA's webhooks.
     *
     * @return array{wabas:int, numbers:int, errors:array}
     */
    public function provision_tenant_wabas($slug, $token)
    {
        $c = whatsapp_central_config();
        $errors = [];

        // Granted WABA ids come from the token's granular scopes.
        $debug = whatsapp_graph_get('debug_token', [
            'input_token'  => $token,
            'access_token' => $c['app_id'] . '|' . $c['app_secret'],
        ]);
        $waba_ids = [];
        foreach (($debug['data']['granular_scopes'] ?? []) as $gs) {
            if (in_array($gs['scope'] ?? '', ['whatsapp_business_management', 'whatsapp_business_messaging'], true)) {
                foreach (($gs['target_ids'] ?? []) as $id) {
                    $waba_ids[$id] = $id;
                }
            }
        }
        // Fallback discovery through the user's businesses.
        if (empty($waba_ids)) {
            $biz = whatsapp_graph_get('me/businesses', ['limit' => 50], $token);
            foreach (($biz['data'] ?? []) as $b) {
                $owned = whatsapp_graph_get($b['id'] . '/owned_whatsapp_business_accounts', ['limit' => 50], $token);
                foreach (($owned['data'] ?? []) as $w) {
                    $waba_ids[$w['id']] = $w['id'];
                }
            }
        }
        if (empty($waba_ids)) {
            return ['wabas' => 0, 'numbers' => 0, 'errors' => ['No WhatsApp Business Account was shared during signup.']];
        }

        $existing_default = null;
        foreach ($this->registry_numbers_for_tenant($slug) as $n) {
            if ((int) $n->is_default === 1) {
                $existing_default = $n->phone_number_id;
            }
        }

        $numbers_count = 0;
        $first_waba    = null;
        $first_name    = null;
        foreach ($waba_ids as $waba_id) {
            $info = whatsapp_graph_get($waba_id, ['fields' => 'id,name'], $token);
            if (isset($info['error'])) {
                $errors[] = 'WABA ' . $waba_id . ': ' . $info['error'];
                continue;
            }
            if ($first_waba === null) {
                $first_waba = $waba_id;
                $first_name = $info['name'] ?? null;
            }

            // Subscribe the central app to this WABA (messages + template webhooks).
            $sub = whatsapp_graph_post($waba_id . '/subscribed_apps', [], $token);
            if (isset($sub['error'])) {
                $errors[] = ($info['name'] ?? $waba_id) . ' webhook subscription: ' . $sub['error'];
            }

            $nums = $this->graph_number_read($waba_id . '/phone_numbers', $token, true);
            if (isset($nums['error'])) {
                $errors[] = ($info['name'] ?? $waba_id) . ' numbers: ' . $nums['error'];
                continue;
            }
            foreach (($nums['data'] ?? []) as $num) {
                $row = array_merge($this->map_number_fields($num), [
                    'phone_number_id' => $num['id'],
                    'tenant_slug'     => $slug,
                    'waba_id'         => $waba_id,
                    'is_default'      => ($existing_default === null && $numbers_count === 0) ? 1 : 0,
                    'last_checked_at' => date('Y-m-d H:i:s'),
                ]);
                $this->registry_store_number($row);
                $numbers_count++;

                if (empty($row['is_registered'])) {
                    $errors[] = ($num['display_phone_number'] ?? $num['id'])
                        . ' is not registered on the Cloud API — register it before sending.';
                }
            }
        }

        return [
            'wabas'     => count($waba_ids),
            'numbers'   => $numbers_count,
            'waba_id'   => $first_waba,
            'waba_name' => $first_name,
            'errors'    => $errors,
        ];
    }

    /* ═══════════ provider billing (shared credit line) ═══════════ */

    /**
     * The provider's extended credit lines on their own Meta Business.
     * This is the pot the tenants' messaging gets charged to, exactly like
     * Wati / AiSensy / Interakt — the tenant never attaches a card.
     *
     * Requires the provider to be an approved Meta Tech Provider with a
     * verified business and an extended credit line issued by Meta.
     */
    public function list_credit_lines()
    {
        $b = whatsapp_provider_billing();
        if ($b['business_id'] === '' || $b['system_token'] === '') {
            return ['error' => 'Set your Meta Business (portfolio) ID and system user token first.'];
        }

        $res = whatsapp_graph_get($b['business_id'] . '/extendedcredits', [
            'fields' => 'id,legal_entity_name,credit_available_amount,max_balance,is_active',
            'limit'  => 50,
        ], $b['system_token']);

        // Field availability on this edge varies by account type — fall back to
        // the ids alone rather than failing the whole picker.
        if (isset($res['error']) && (string) ($res['error_code'] ?? '') === '100') {
            $res = whatsapp_graph_get($b['business_id'] . '/extendedcredits', [
                'fields' => 'id,legal_entity_name',
                'limit'  => 50,
            ], $b['system_token']);
        }

        if (isset($res['error'])) {
            return ['error' => $res['error'], 'error_code' => $res['error_code'] ?? ''];
        }

        $lines = $res['data'] ?? [];

        // An empty list is the common outcome and is NOT self-explanatory — it
        // can mean a wrong business id, an unverified business, a token from a
        // different business, or simply that Meta has not issued a credit line.
        // Work out which, so the console can say so.
        return [
            'lines'     => $lines,
            'diagnosis' => empty($lines) ? $this->diagnose_credit_lines() : null,
        ];
    }

    /**
     * Why did the credit-line list come back empty?
     *
     * @return array{reason:string, business:array|null, businesses:array, detail:string}
     */
    public function diagnose_credit_lines()
    {
        $b = whatsapp_provider_billing();
        $out = ['reason' => 'no_credit_line', 'business' => null, 'businesses' => [], 'detail' => ''];

        // 1. Is the token usable at all?
        $me = whatsapp_graph_get('me', ['fields' => 'id,name'], $b['system_token']);
        if (isset($me['error'])) {
            $out['reason'] = 'token_invalid';
            $out['detail'] = $me['error'];
            return $out;
        }

        // 2. Which businesses can this token actually see? Handy when the id
        //    pasted in belongs to a different portfolio.
        $mine = whatsapp_graph_get('me/businesses', ['fields' => 'id,name,verification_status', 'limit' => 25], $b['system_token']);
        if (!isset($mine['error'])) {
            foreach (($mine['data'] ?? []) as $biz) {
                $out['businesses'][] = [
                    'id'                  => $biz['id'] ?? '',
                    'name'                => $biz['name'] ?? '',
                    'verification_status' => $biz['verification_status'] ?? '',
                ];
            }
        }

        // 3. Does the configured business id resolve, and is it verified?
        $biz = whatsapp_graph_get($b['business_id'], ['fields' => 'id,name,verification_status'], $b['system_token']);
        if (isset($biz['error'])) {
            $biz = whatsapp_graph_get($b['business_id'], ['fields' => 'id,name'], $b['system_token']);
        }
        if (isset($biz['error'])) {
            $out['reason'] = 'business_unreadable';
            $out['detail'] = $biz['error'];
            return $out;
        }

        $out['business'] = [
            'id'                  => $biz['id'] ?? $b['business_id'],
            'name'                => $biz['name'] ?? '',
            'verification_status' => $biz['verification_status'] ?? '',
        ];

        $verification = strtolower((string) ($biz['verification_status'] ?? ''));
        if ($verification !== '' && $verification !== 'verified') {
            $out['reason'] = 'not_verified';
            $out['detail'] = $verification;
        }
        return $out;
    }

    /**
     * Attach the provider's credit line to a tenant's WABA so Meta invoices the
     * provider, not the tenant.
     *
     * Meta endpoint: POST /{extended_credit_line_id}/whatsapp_credit_sharing_and_attach
     * with waba_id + waba_currency, called with the PROVIDER's system user
     * token (the credit line belongs to the provider's business).
     */
    public function share_credit_line($slug, $waba_id = null, $currency = null)
    {
        $b = whatsapp_provider_billing();
        if (!whatsapp_is_master()) {
            return ['error' => 'Credit sharing runs on the provider account only.'];
        }
        if (!$b['enabled']) {
            return ['error' => 'Credit line sharing is switched off in the provider console.'];
        }
        if ($b['system_token'] === '' || $b['credit_line_id'] === '') {
            return ['error' => 'Add your system user token and select a credit line first.'];
        }

        $conn = $this->registry_get_connection($slug);
        if (!$conn) {
            return ['error' => 'That tenant has no WhatsApp connection.'];
        }
        $waba_id = $waba_id ?: $conn->waba_id;
        if (empty($waba_id)) {
            return ['error' => 'The tenant has no WhatsApp Business Account to attach.'];
        }

        $res = whatsapp_graph_post($b['credit_line_id'] . '/whatsapp_credit_sharing_and_attach', [
            'waba_id'       => $waba_id,
            'waba_currency' => $currency ?: $b['currency'],
        ], $b['system_token']);

        if (isset($res['error'])) {
            $this->set_credit_status($slug, 'failed', [
                'credit_line_id' => $b['credit_line_id'],
                'credit_error'   => substr($res['error'], 0, 480),
            ]);
            return ['error' => $res['error'], 'error_code' => $res['error_code'] ?? ''];
        }

        $this->set_credit_status($slug, 'shared', [
            'credit_line_id'       => $b['credit_line_id'],
            'allocation_config_id' => $res['allocation_config_id'] ?? null,
            'credit_error'         => null,
            'credit_shared_at'     => date('Y-m-d H:i:s'),
        ]);

        return ['shared' => true, 'allocation_config_id' => $res['allocation_config_id'] ?? null];
    }

    /** Persist the billing state of one tenant connection. */
    public function set_credit_status($slug, $status, $extra = [])
    {
        $mp     = $this->mp();
        $fields = array_merge(['credit_status' => $status], $extra);

        $set    = [];
        $params = [];
        foreach ($fields as $col => $val) {
            $set[]    = $col . ' = ?';
            $params[] = $val;
        }
        $params[] = $slug;

        $this->master_exec(
            "UPDATE {$mp}whatsapp_connections SET " . implode(', ', $set) . " WHERE tenant_slug = ?",
            $params
        );
    }

    /* ════════════════ number health / registration ════════════════ */

    /**
     * Read a phone number (or a WABA's number list) with the extended field
     * set. Graph rejects the whole request when it does not recognise a single
     * field, so an "invalid parameter" answer retries with the core fields —
     * the registration status still comes through on older API versions.
     */
    private function graph_number_read($path, $token, $edge = false)
    {
        $params = ['fields' => whatsapp_number_graph_fields()];
        if ($edge) {
            $params['limit'] = 100;
        }
        $res = whatsapp_graph_get($path, $params, $token);

        if (isset($res['error']) && (string) ($res['error_code'] ?? '') === '100') {
            $params['fields'] = 'id,display_phone_number,verified_name,quality_rating,status';
            $res = whatsapp_graph_get($path, $params, $token);
        }
        return $res;
    }

    /** Graph phone-number payload → registry columns. */
    private function map_number_fields($num)
    {
        $status = strtoupper((string) ($num['status'] ?? ''));

        return [
            'display_phone_number'     => $num['display_phone_number'] ?? null,
            'verified_name'            => $num['verified_name'] ?? null,
            'quality_rating'           => $num['quality_rating'] ?? null,
            'status'                   => $status ?: null,
            'code_verification_status' => $num['code_verification_status'] ?? null,
            'name_status'              => $num['name_status'] ?? null,
            'platform_type'            => $num['platform_type'] ?? null,
            'throughput_level'         => $num['throughput']['level'] ?? null,
            'messaging_limit_tier'     => $num['messaging_limit_tier'] ?? null,
            // CONNECTED is the only state in which the Cloud API accepts sends;
            // anything else is what surfaces as "(#133010) Account not registered".
            'is_registered'            => $status === 'CONNECTED' ? 1 : 0,
        ];
    }

    /**
     * Pull each of the tenant's numbers straight from Meta and refresh the
     * registry: registration state, quality, messaging tier, throughput and the
     * (best-effort) health_status entity report.
     *
     * @return array{checked:int,registered:int,errors:array}
     */
    public function sync_number_health($slug = null)
    {
        $slug = $slug ?: whatsapp_current_slug();
        $conn = $this->registry_get_connection($slug);
        if (!$conn || empty($conn->access_token)) {
            return ['checked' => 0, 'registered' => 0, 'errors' => ['WhatsApp is not connected.']];
        }

        $checked = 0;
        $ok      = 0;
        $errors  = [];

        foreach ($this->registry_numbers_for_tenant($slug) as $n) {
            $info = $this->graph_number_read($n->phone_number_id, $conn->access_token);

            if (isset($info['error'])) {
                $this->registry_store_number([
                    'phone_number_id' => $n->phone_number_id,
                    'tenant_slug'     => $slug,
                    'last_error'      => substr($info['error'], 0, 480),
                    'last_checked_at' => date('Y-m-d H:i:s'),
                ]);
                $errors[] = ($n->display_phone_number ?: $n->phone_number_id) . ': ' . $info['error'];
                continue;
            }

            $row = array_merge($this->map_number_fields($info), [
                'phone_number_id' => $n->phone_number_id,
                'tenant_slug'     => $slug,
                'last_error'      => null,
                'last_checked_at' => date('Y-m-d H:i:s'),
            ]);

            // health_status is a newer field and is not available on every WABA
            // — treat a failure here as "unknown", never as a check failure.
            $health = whatsapp_graph_get($n->phone_number_id, ['fields' => 'health_status'], $conn->access_token);
            if (!isset($health['error']) && !empty($health['health_status'])) {
                $hs = $health['health_status'];
                $row['health_can_send'] = $hs['can_send_message'] ?? null;
                $row['health_detail']   = substr($this->summarise_health_entities($hs), 0, 480) ?: null;
            }

            $this->registry_store_number($row);
            $checked++;
            if (!empty($row['is_registered'])) {
                $ok++;
            }
        }

        $mp = $this->mp();
        $this->master_exec(
            "UPDATE {$mp}whatsapp_connections SET last_checked_at = ? WHERE tenant_slug = ?",
            [date('Y-m-d H:i:s'), $slug]
        );

        return ['checked' => $checked, 'registered' => $ok, 'errors' => $errors];
    }

    /** Flatten health_status.entities[].errors[] into one readable line. */
    private function summarise_health_entities($health_status)
    {
        $out = [];
        foreach (($health_status['entities'] ?? []) as $entity) {
            if (strtoupper($entity['can_send_message'] ?? '') === 'AVAILABLE') {
                continue;
            }
            foreach (($entity['errors'] ?? []) as $err) {
                $line = trim(($err['error_description'] ?? '') . ' ' . ($err['possible_solution'] ?? ''));
                if ($line !== '') {
                    $out[] = $line;
                }
            }
        }
        return implode(' ', array_unique($out));
    }

    /**
     * Register a number for Cloud API messaging — the fix for error 133010.
     * The PIN becomes (or must match) the number's two-step verification PIN.
     */
    public function register_number($phone_number_id, $pin)
    {
        $number = $this->registry_get_number($phone_number_id);
        if (!$number || $number->tenant_slug !== whatsapp_current_slug()) {
            return ['error' => 'Unknown phone number for this account.'];
        }
        $token = $this->token_for_number($phone_number_id);
        if (!$token) {
            return ['error' => 'WhatsApp is not connected.'];
        }
        $pin = preg_replace('/\D+/', '', (string) $pin);
        if (strlen($pin) !== 6) {
            return ['error' => 'The registration PIN must be exactly 6 digits.'];
        }

        $res = whatsapp_graph_post_json($phone_number_id . '/register', [
            'messaging_product' => 'whatsapp',
            'pin'               => $pin,
        ], $token);

        if (isset($res['error'])) {
            $this->registry_store_number([
                'phone_number_id' => $phone_number_id,
                'tenant_slug'     => $number->tenant_slug,
                'last_error'      => substr($res['error'], 0, 480),
                'last_checked_at' => date('Y-m-d H:i:s'),
            ]);
            return ['error' => $res['error'], 'error_code' => $res['error_code'] ?? ''];
        }

        // Re-read the live state so the UI reflects Meta, not our optimism.
        $this->sync_number_health($number->tenant_slug);
        $fresh = $this->registry_get_number($phone_number_id);

        return [
            'registered' => $fresh ? (int) $fresh->is_registered : 1,
            'status'     => $fresh ? (string) $fresh->status : 'CONNECTED',
        ];
    }

    /** Release a number from the Cloud API (it can be registered again later). */
    public function deregister_number($phone_number_id)
    {
        $number = $this->registry_get_number($phone_number_id);
        if (!$number || $number->tenant_slug !== whatsapp_current_slug()) {
            return ['error' => 'Unknown phone number for this account.'];
        }
        $token = $this->token_for_number($phone_number_id);
        if (!$token) {
            return ['error' => 'WhatsApp is not connected.'];
        }
        $res = whatsapp_graph_post_json($phone_number_id . '/deregister', [], $token);
        if (isset($res['error'])) {
            return ['error' => $res['error'], 'error_code' => $res['error_code'] ?? ''];
        }
        $this->sync_number_health($number->tenant_slug);
        return ['deregistered' => true];
    }

    /** Everything Meta knows about one number, for the details drawer. */
    public function number_profile($phone_number_id)
    {
        $number = $this->registry_get_number($phone_number_id);
        if (!$number || $number->tenant_slug !== whatsapp_current_slug()) {
            return null;
        }
        $token = $this->token_for_number($phone_number_id);
        $live  = $token
            ? $this->graph_number_read($phone_number_id, $token)
            : ['error' => 'Not connected'];

        // Opening the drawer is an explicit "show me the truth" — refresh the
        // cached row from the live read before rendering it.
        if (!isset($live['error'])) {
            $this->registry_store_number(array_merge($this->map_number_fields($live), [
                'phone_number_id' => $phone_number_id,
                'tenant_slug'     => $number->tenant_slug,
                'last_checked_at' => date('Y-m-d H:i:s'),
            ]));
            $number = $this->registry_get_number($phone_number_id);
        }

        return [
            'number' => $number,
            'live'   => isset($live['error']) ? null : $live,
            'error'  => $live['error'] ?? null,
        ];
    }

    /* ══════════════ tenant usage & cost (Meta analytics) ══════════════ */

    /**
     * Messages billed and what they cost, straight from Meta's analytics for
     * one tenant's WABA.
     *
     * Meta moved template messaging from conversation-based to per-message
     * pricing, so `pricing_analytics` is tried first and `conversation_analytics`
     * is the fallback for accounts still reporting the old way.
     *
     * @return array{messages:int,cost:float,currency:string,source:string}|array{error:string}
     */
    public function fetch_tenant_usage($slug, $days = 30)
    {
        $conn = $this->registry_get_connection($slug);
        if (!$conn || empty($conn->access_token) || empty($conn->waba_id)) {
            return ['error' => 'Not connected.'];
        }

        $end   = time();
        $start = $end - ((int) $days * 86400);

        // 1) Per-message pricing (current model).
        $res = whatsapp_graph_get($conn->waba_id, [
            'fields' => 'pricing_analytics.start(' . $start . ').end(' . $end . ').granularity(DAILY)',
        ], $conn->access_token);

        $points = $res['pricing_analytics']['data'][0]['data_points'] ?? null;
        $source = 'pricing';

        // 2) Conversation-based pricing (legacy accounts).
        if ($points === null) {
            $res = whatsapp_graph_get($conn->waba_id, [
                'fields' => 'conversation_analytics.start(' . $start . ').end(' . $end
                          . ').granularity(DAILY).phone_numbers([]).dimensions(["CONVERSATION_CATEGORY"])',
            ], $conn->access_token);
            $points = $res['conversation_analytics']['data'][0]['data_points'] ?? null;
            $source = 'conversation';
        }

        if ($points === null) {
            return ['error' => $res['error'] ?? 'Meta returned no analytics for this account.'];
        }

        $messages = 0;
        $cost     = 0.0;
        $currency = '';
        foreach ($points as $p) {
            // `volume` on pricing_analytics, `conversation` on the legacy edge.
            $messages += (int) ($p['volume'] ?? ($p['conversation'] ?? 0));
            $cost     += (float) ($p['cost'] ?? 0);
            if ($currency === '' && !empty($p['currency'])) {
                $currency = (string) $p['currency'];
            }
        }

        return [
            'messages' => $messages,
            'cost'     => round($cost, 4),
            'currency' => $currency,
            'source'   => $source,
        ];
    }

    /** Fetch + cache usage for every connected tenant. */
    public function sync_all_tenant_usage($days = 30, $limit = 50)
    {
        $mp       = $this->mp();
        $fallback = whatsapp_provider_billing()['currency'];
        $synced   = 0;
        $errors   = [];

        foreach (array_slice($this->registry_all_connections(), 0, $limit) as $conn) {
            $usage = $this->fetch_tenant_usage($conn->tenant_slug, $days);

            if (isset($usage['error'])) {
                $this->master_exec(
                    "UPDATE {$mp}whatsapp_connections
                     SET usage_error = ?, usage_synced_at = ? WHERE tenant_slug = ?",
                    [substr($usage['error'], 0, 290), date('Y-m-d H:i:s'), $conn->tenant_slug]
                );
                $errors[] = $conn->tenant_slug . ': ' . $usage['error'];
                continue;
            }

            $this->master_exec(
                "UPDATE {$mp}whatsapp_connections
                 SET usage_messages = ?, usage_cost = ?, usage_currency = ?, usage_days = ?,
                     usage_source = ?, usage_error = NULL, usage_synced_at = ?
                 WHERE tenant_slug = ?",
                [
                    $usage['messages'],
                    $usage['cost'],
                    $usage['currency'] !== '' ? $usage['currency'] : $fallback,
                    (int) $days,
                    $usage['source'],
                    date('Y-m-d H:i:s'),
                    $conn->tenant_slug,
                ]
            );
            $synced++;
        }

        return ['synced' => $synced, 'errors' => $errors];
    }

    /* ══════════════ webhook configuration & self-test ══════════════ */

    /**
     * What Meta actually has registered on the app — the callback URL it will
     * POST to and the fields it is subscribed to. This is the ground truth that
     * "no webhook received" cannot tell you on its own.
     */
    public function webhook_app_subscription()
    {
        $token = whatsapp_app_token();
        if ($token === '') {
            return ['error' => 'Set the App ID and App Secret first.'];
        }
        $c   = whatsapp_central_config();
        $res = whatsapp_graph_get($c['app_id'] . '/subscriptions', ['access_token' => $token]);
        if (isset($res['error'])) {
            return ['error' => $res['error'], 'error_code' => $res['error_code'] ?? ''];
        }

        foreach (($res['data'] ?? []) as $sub) {
            if (($sub['object'] ?? '') !== 'whatsapp_business_account') {
                continue;
            }
            $fields = [];
            foreach (($sub['fields'] ?? []) as $f) {
                $fields[] = is_array($f) ? ($f['name'] ?? '') : (string) $f;
            }
            return [
                'registered'   => true,
                'callback_url' => (string) ($sub['callback_url'] ?? ''),
                'active'       => !empty($sub['active']),
                'fields'       => array_values(array_filter($fields)),
            ];
        }
        return ['registered' => false, 'callback_url' => '', 'active' => false, 'fields' => []];
    }

    /**
     * Call our own webhook URL exactly the way Meta verifies it, and check we
     * echo the challenge back. Proves whether the endpoint is publicly
     * reachable and not being eaten by a redirect, WAF, auth wall or CSRF.
     */
    public function webhook_self_test()
    {
        $c   = whatsapp_central_config();
        $url = whatsapp_webhook_url();
        if ($url === '') {
            return ['ok' => false, 'reason' => 'no_url', 'detail' => 'No webhook URL is configured.'];
        }
        if ($c['verify_token'] === '') {
            return ['ok' => false, 'reason' => 'no_token', 'detail' => 'No verify token is configured.'];
        }

        $challenge = 'wapi' . random_int(100000, 999999);
        $probe     = whatsapp_http_probe($url . '?' . http_build_query([
            'hub.mode'         => 'subscribe',
            'hub.verify_token' => $c['verify_token'],
            'hub.challenge'    => $challenge,
        ]));

        // NOTE: merge with array_merge, never the `+` union operator — `+` keeps
        // the LEFT value on a key collision, so a pre-seeded 'ok' => false would
        // survive the success path and report a healthy endpoint as broken.
        $result = ['url' => $url, 'code' => $probe['code'], 'ok' => false];

        if ($probe['error'] !== '') {
            return array_merge($result, ['reason' => 'unreachable', 'detail' => $probe['error']
                . ' — the server could not reach its own webhook URL. Check DNS, the SSL certificate and any firewall.']);
        }
        if ($probe['redirected']) {
            return array_merge($result, ['reason' => 'redirect', 'detail' => 'The URL redirects to ' . ($probe['effective_url'] ?: 'another address')
                . '. Meta does not follow redirects — register the final URL instead.']);
        }
        if ($probe['code'] === 401 || $probe['code'] === 403) {
            return array_merge($result, ['reason' => 'blocked', 'detail' => 'The endpoint answered ' . $probe['code']
                . '. Something in front of the site (WAF, basic auth, firewall rule) or a CSRF check is rejecting the request before it reaches the module.']);
        }
        if ($probe['code'] === 404) {
            return array_merge($result, ['reason' => 'not_found', 'detail' => 'The endpoint answered 404 — the webhook URL is wrong or the module route is not reachable at that path.']);
        }
        if ($probe['code'] !== 200) {
            return array_merge($result, ['reason' => 'status', 'detail' => 'The endpoint answered HTTP ' . $probe['code'] . ' instead of 200.']);
        }
        if (trim($probe['body']) !== $challenge) {
            $body = trim($probe['body']);
            return array_merge($result, ['reason' => 'wrong_body', 'detail' => 'The endpoint answered 200 but did not echo the challenge back'
                . ($body === '' ? ' (empty response).' : ' — it returned: ' . substr($body, 0, 120) . '…')
                . ' That usually means a login page, error page or cache is being served instead of the module.']);
        }

        return array_merge($result, ['ok' => true, 'reason' => 'ok',
            'detail' => 'The webhook endpoint is publicly reachable and answered Meta\'s verification challenge correctly.']);
    }

    /**
     * Register (or repair) the app-level webhook subscription. Meta calls the
     * callback URL to verify it before accepting, so run the self-test first.
     */
    public function webhook_subscribe()
    {
        $token = whatsapp_app_token();
        if ($token === '') {
            return ['error' => 'Set the App ID and App Secret first.'];
        }
        $c = whatsapp_central_config();
        if ($c['verify_token'] === '') {
            return ['error' => 'No verify token is configured.'];
        }

        $res = whatsapp_graph_post($c['app_id'] . '/subscriptions', [
            'object'       => 'whatsapp_business_account',
            'callback_url' => whatsapp_webhook_url(),
            'verify_token' => $c['verify_token'],
            'fields'       => implode(',', whatsapp_webhook_fields()),
            'access_token' => $token,
        ]);
        if (isset($res['error'])) {
            return ['error' => $res['error'], 'error_code' => $res['error_code'] ?? ''];
        }
        return ['subscribed' => true];
    }

    /** Combined webhook report for the provider console. */
    public function webhook_report()
    {
        $sub    = $this->webhook_app_subscription();
        $expect = whatsapp_webhook_url();

        $report = [
            'expected_url' => $expect,
            'self_test'    => $this->webhook_self_test(),
            'last_at'      => (string) get_option('whatsapp_last_webhook_at'),
            'rejected_at'  => (string) get_option('whatsapp_last_webhook_rejected_at'),
            'count'        => (int) get_option('whatsapp_webhook_count'),
            'missing_fields' => [],
        ];

        if (isset($sub['error'])) {
            $report['subscription_error'] = $sub['error'];
            return $report;
        }

        $report['registered']   = !empty($sub['registered']);
        $report['callback_url'] = $sub['callback_url'];
        $report['active']       = !empty($sub['active']);
        $report['fields']       = $sub['fields'];
        // Compare ignoring a trailing slash — Meta stores it verbatim.
        $report['url_matches']  = rtrim($sub['callback_url'], '/') === rtrim($expect, '/');

        foreach (whatsapp_webhook_fields() as $f) {
            if (!in_array($f, $sub['fields'], true)) {
                $report['missing_fields'][] = $f;
            }
        }
        return $report;
    }

    /* ═════════════ central app resync (credentials changed) ═════════════ */

    /**
     * Re-run the whole install against the credentials stored RIGHT NOW.
     *
     * Changing the central Meta App does not change anything Meta already holds,
     * and it does not change anything this registry already holds. Everything
     * downstream keeps pointing at the previous app until it is pushed again:
     *
     *   • the callback / OAuth URLs were captured when they were last saved;
     *   • the app-level webhook subscription lives on the OLD app — the new one
     *     has none, so nothing inbound is ever delivered;
     *   • every tenant's stored access token was MINTED BY THE OLD APP. It keeps
     *     looking perfectly valid in the registry, which is exactly why the
     *     console appears to be "showing old details" after a credential swap;
     *   • each WABA's subscribed_apps entry belongs to the old app too.
     *
     * This walks all four, repairs what can be repaired without the tenant, and
     * marks the rest `stale` so both consoles say "reconnect" instead of
     * silently failing later. Master context only.
     *
     * @return array{steps:array,tenants:array,counts:array,app:array,aborted:bool}
     */
    public function resync_central_app()
    {
        // The credentials may have been written earlier in this same request.
        whatsapp_central_config_reset();
        $c = whatsapp_central_config();

        $steps = [];
        $step  = function ($key, $label, $state, $detail) use (&$steps) {
            $steps[] = ['key' => $key, 'label' => $label, 'state' => $state, 'detail' => $detail];
        };
        $out = function ($aborted = false) use (&$steps) {
            return ['steps' => $steps, 'tenants' => [], 'counts' => ['ok' => 0, 'stale' => 0, 'warn' => 0],
                    'app' => [], 'aborted' => $aborted];
        };

        if ($c['app_id'] === '' || $c['app_secret'] === '') {
            $step('credentials', 'Central Meta App', 'fail',
                'No App ID / App Secret is stored. Save the credentials above first — nothing else can be checked without them.');
            return $out(true);
        }

        /* 1 ─ Re-derive the URLs from THIS host, not from whatever was stored. */
        $webhook  = admin_url('whatsapp/webhook');
        $callback = admin_url('whatsapp/oauth_callback');
        $changed  = [];
        // Compare against the config (read from the options TABLE), never
        // get_option() — these names are autoloaded and core would answer from
        // the copy it took when the request booted.
        if (rtrim($c['webhook_url'], '/') !== rtrim($webhook, '/')) {
            update_option('whatsapp_webhook_url', $webhook);
            $changed[] = 'webhook URL';
        }
        if (rtrim($c['oauth_callback_url'], '/') !== rtrim($callback, '/')) {
            update_option('whatsapp_oauth_callback_url', $callback);
            $changed[] = 'OAuth redirect URI';
        }
        if ($c['verify_token'] === '') {
            update_option('whatsapp_verify_token', bin2hex(random_bytes(16)));
            $changed[] = 'verify token';
        }
        if (!empty($changed)) {
            whatsapp_central_config_reset();
            $c = whatsapp_central_config();
        }
        $step('urls', 'Callback URLs', 'ok', empty($changed)
            ? 'The stored webhook URL and OAuth redirect already match this server.'
            : 'Re-captured the ' . implode(' and ', $changed) . ' from this server. Register the same values in the new Meta App.');

        /* 2 ─ Do the App ID and Secret actually belong together, and to a live app? */
        $app_token = whatsapp_app_token();
        $app       = whatsapp_graph_get($c['app_id'], ['fields' => 'id,name,link', 'access_token' => $app_token]);
        if (isset($app['error'])) {
            $step('app', 'App credentials', 'fail',
                'Meta rejected the stored App ID + Secret pair: ' . $app['error']
                . ' — re-copy both from App settings → Basic. Nothing below can be repaired until this passes.');
            return $out(true);
        }
        $app_name = (string) ($app['name'] ?? '');
        $step('app', 'App credentials', 'ok',
            'Meta answered as ' . ($app_name !== '' ? '“' . $app_name . '” ' : '') . '(App ID ' . $c['app_id']
            . '). The stored App ID and App Secret are a matching, live pair.');

        /* 3 ─ Embedded Signup configuration (drives the tenant Connect dialog). */
        if ($c['config_id'] === '') {
            $step('config', 'Embedded Signup config', 'warn',
                'No Config ID is stored, so tenants get the plain OAuth dialog instead of WhatsApp Embedded Signup. '
                . 'Create a configuration under Facebook Login for Business and paste its ID above.');
        } else {
            $cfg = whatsapp_graph_get($c['config_id'], ['access_token' => $app_token]);
            $step('config', 'Embedded Signup config', isset($cfg['error']) ? 'warn' : 'ok',
                isset($cfg['error'])
                    ? 'Config ID ' . $c['config_id'] . ' could not be read back: ' . $cfg['error']
                      . ' — confirm it belongs to THIS app under Facebook Login for Business → Configurations.'
                    : 'Config ID ' . $c['config_id'] . ' resolves on this app'
                      . (!empty($cfg['name']) ? ' (“' . $cfg['name'] . '”).' : '.'));
        }

        /* 4 ─ Push the webhook onto the NEW app and read back what Meta stored. */
        $self = $this->webhook_self_test();
        if (empty($self['ok'])) {
            $step('webhook', 'Webhook registration', 'fail',
                'The webhook URL could not verify itself, so Meta will refuse to accept it: '
                . ($self['detail'] ?? '') . ' Fix that first, then run this again.');
        } else {
            $sub = $this->webhook_subscribe();
            if (isset($sub['error'])) {
                $step('webhook', 'Webhook registration', 'fail',
                    'Meta refused the webhook subscription: ' . $sub['error']);
            } else {
                $read   = $this->webhook_app_subscription();
                $fields = isset($read['fields']) ? $read['fields'] : [];
                $missing = [];
                foreach (whatsapp_webhook_fields() as $f) {
                    if (!in_array($f, $fields, true)) {
                        $missing[] = $f;
                    }
                }
                $step('webhook', 'Webhook registration', empty($missing) ? 'ok' : 'warn',
                    'Registered ' . whatsapp_webhook_url() . ' on App ID ' . $c['app_id']
                    . (empty($missing)
                        ? ' and subscribed to ' . implode(', ', $fields) . '.'
                        : ', but Meta reports these fields are still missing: ' . implode(', ', $missing) . '.'));
            }
        }

        /* 5 ─ Audit every stored tenant token against the new app. */
        $tenants = [];
        $counts  = ['ok' => 0, 'stale' => 0, 'warn' => 0];

        foreach ($this->registry_all_connections() as $conn) {
            $row = [
                'slug'    => $conn->tenant_slug,
                'waba'    => $conn->waba_name ?: $conn->waba_id,
                'state'   => 'ok',
                'detail'  => '',
                'numbers' => (int) ($conn->number_count ?? 0),
            ];

            $audit = $this->audit_connection_token($conn, $c['app_id'], $app_token);
            $row['state']  = $audit['state'];
            $row['detail'] = $audit['detail'];

            if ($audit['state'] === 'ok') {
                // The WABA's subscription belongs to the app that made it — the
                // new app has to be subscribed again before events flow.
                $resub = whatsapp_graph_post($conn->waba_id . '/subscribed_apps', [], $conn->access_token);
                if (isset($resub['error'])) {
                    $row['state']  = 'warn';
                    $row['detail'] = 'Token is valid on the new app, but re-subscribing the WABA failed: ' . $resub['error'];
                } else {
                    $health = $this->sync_number_health($conn->tenant_slug);
                    $row['numbers'] = (int) $health['checked'];
                    $row['detail']  = 'Token belongs to the new app. WABA re-subscribed; '
                        . (int) $health['registered'] . ' of ' . (int) $health['checked'] . ' number(s) registered.'
                        . (!empty($health['errors']) ? ' ' . implode(' ', array_slice($health['errors'], 0, 2)) : '');
                }
                $this->master_exec(
                    "UPDATE {$this->mp()}whatsapp_connections
                        SET webhook_subscribed = ? WHERE tenant_slug = ?",
                    [isset($resub['error']) ? 0 : 1, $conn->tenant_slug]
                );
            }

            $counts[isset($counts[$row['state']]) ? $row['state'] : 'warn']++;

            // Only a foreign / dead token makes the connection unusable. A WABA
            // that would not re-subscribe is worth recording, but the account is
            // still on the new app and must not be told to reconnect.
            $this->master_exec(
                "UPDATE {$this->mp()}whatsapp_connections
                    SET status = ?, token_app_id = ?, sync_error = ?, synced_at = ?
                  WHERE tenant_slug = ?",
                [
                    $row['state'] === 'stale' ? 'stale' : 'active',
                    $audit['token_app_id'] !== '' ? $audit['token_app_id'] : null,
                    $row['state'] === 'ok' ? null : substr($row['detail'], 0, 480),
                    date('Y-m-d H:i:s'),
                    $conn->tenant_slug,
                ]
            );

            $tenants[] = $row;
        }

        if (empty($tenants)) {
            $step('tenants', 'Connected accounts', 'warn',
                'No account is connected yet, so there is nothing to re-point at the new app.');
        } else {
            $step('tenants', 'Connected accounts', $counts['ok'] === count($tenants) ? 'ok' : 'warn',
                $counts['ok'] . ' of ' . count($tenants) . ' connection(s) work on the new app.'
                . ($counts['stale'] > 0
                    ? ' ' . $counts['stale'] . ' still hold a token from the previous Meta App and must click Connect again — they are listed below and now show a reconnect warning on their own WhatsApp page.'
                    : '')
                . ($counts['warn'] > 0 ? ' ' . $counts['warn'] . ' connected but could not be fully re-subscribed.' : ''));
        }

        update_option('whatsapp_last_resync_at', date('Y-m-d H:i:s'));
        update_option('whatsapp_last_resync_app_id', $c['app_id']);

        return [
            'steps'   => $steps,
            'tenants' => $tenants,
            'counts'  => $counts,
            'app'     => ['id' => $c['app_id'], 'name' => $app_name],
            'aborted' => false,
        ];
    }

    /**
     * Which Meta App minted a stored tenant token, and is it still usable?
     *
     * `debug_token` is the only honest answer here: a token issued by the
     * previous app is not "expired", it is simply foreign, and every Cloud API
     * call made with it fails with an opaque OAuth error instead.
     *
     * @return array{state:string, detail:string, token_app_id:string}
     */
    private function audit_connection_token($conn, $app_id, $app_token)
    {
        if (empty($conn->access_token)) {
            return ['state' => 'stale', 'token_app_id' => '',
                    'detail' => 'No access token is stored for this account — it has to connect again.'];
        }

        $debug = whatsapp_graph_get('debug_token', [
            'input_token'  => $conn->access_token,
            'access_token' => $app_token,
        ]);
        if (isset($debug['error'])) {
            return ['state' => 'stale', 'token_app_id' => '',
                    'detail' => 'The new app cannot inspect this token (' . $debug['error']
                        . '), which is what a token minted by a different Meta App looks like. The account must reconnect.'];
        }

        $d       = isset($debug['data']) ? $debug['data'] : [];
        $tok_app = (string) ($d['app_id'] ?? '');

        if ($tok_app !== '' && $tok_app !== (string) $app_id) {
            return ['state' => 'stale', 'token_app_id' => $tok_app,
                    'detail' => 'The stored token was issued by Meta App ' . $tok_app . ', not by ' . $app_id
                        . '. It cannot be used by the new app — the account must click Connect once more.'];
        }
        if (empty($d['is_valid'])) {
            return ['state' => 'stale', 'token_app_id' => $tok_app,
                    'detail' => 'Meta reports this token as invalid'
                        . (!empty($d['error']['message']) ? ': ' . $d['error']['message'] : '.') . ' The account must reconnect.'];
        }

        // Keep the registry's expiry honest while we have the truth in hand.
        $expires = (int) ($d['expires_at'] ?? 0);
        if ($expires > 0) {
            $this->master_exec(
                "UPDATE {$this->mp()}whatsapp_connections SET token_expires = ? WHERE tenant_slug = ?",
                [date('Y-m-d H:i:s', $expires), $conn->tenant_slug]
            );
        }

        return ['state' => 'ok', 'token_app_id' => $tok_app, 'detail' => ''];
    }

    /* ══════════════ business profile (branding) ══════════════ */

    /** Fields exposed on a number's public WhatsApp business profile. */
    private function profile_fields()
    {
        return 'about,address,description,email,profile_picture_url,websites,vertical';
    }

    /** Read the live public profile customers see when they open the chat. */
    public function get_business_profile($phone_number_id)
    {
        $number = $this->registry_get_number($phone_number_id);
        if (!$number || $number->tenant_slug !== whatsapp_current_slug()) {
            return ['error' => 'Unknown phone number for this account.'];
        }
        $token = $this->token_for_number($phone_number_id);
        if (!$token) {
            return ['error' => 'WhatsApp is not connected.'];
        }

        $res = whatsapp_graph_get($phone_number_id . '/whatsapp_business_profile', [
            'fields' => $this->profile_fields(),
        ], $token);
        if (isset($res['error'])) {
            return ['error' => $res['error'], 'error_code' => $res['error_code'] ?? ''];
        }

        $profile = $res['data'][0] ?? [];
        return [
            'profile' => [
                'about'               => (string) ($profile['about'] ?? ''),
                'address'             => (string) ($profile['address'] ?? ''),
                'description'         => (string) ($profile['description'] ?? ''),
                'email'               => (string) ($profile['email'] ?? ''),
                'vertical'            => (string) ($profile['vertical'] ?? ''),
                'websites'            => array_values((array) ($profile['websites'] ?? [])),
                'profile_picture_url' => (string) ($profile['profile_picture_url'] ?? ''),
            ],
            'number' => $number,
        ];
    }

    /**
     * Write the public profile. Only keys actually supplied are sent, so a
     * partial save never blanks a field the form did not render.
     *
     * @param array       $data   about/address/description/email/vertical/websites
     * @param string|null $handle profile_picture_handle from a resumable upload
     */
    public function save_business_profile($phone_number_id, $data, $handle = null)
    {
        $number = $this->registry_get_number($phone_number_id);
        if (!$number || $number->tenant_slug !== whatsapp_current_slug()) {
            return ['error' => 'Unknown phone number for this account.'];
        }
        $token = $this->token_for_number($phone_number_id);
        if (!$token) {
            return ['error' => 'WhatsApp is not connected.'];
        }

        $limits  = whatsapp_profile_limits();
        $payload = ['messaging_product' => 'whatsapp'];

        foreach (['about', 'address', 'description', 'email'] as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }
            $value = trim((string) $data[$key]);
            if (isset($limits[$key]) && mb_strlen($value) > $limits[$key]) {
                return ['error' => ucfirst($key) . ' is limited to ' . $limits[$key] . ' characters.'];
            }
            if ($key === 'email' && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                return ['error' => 'That email address is not valid.'];
            }
            $payload[$key] = $value;
        }

        if (array_key_exists('vertical', $data)) {
            $vertical = strtoupper(trim((string) $data['vertical']));
            if ($vertical !== '' && !array_key_exists($vertical, whatsapp_business_verticals())) {
                return ['error' => 'Unknown business category.'];
            }
            if ($vertical !== '') {
                $payload['vertical'] = $vertical;
            }
        }

        if (array_key_exists('websites', $data)) {
            $websites = [];
            foreach ((array) $data['websites'] as $url) {
                $url = trim((string) $url);
                if ($url === '') {
                    continue;
                }
                if (!preg_match('#^https?://#i', $url)) {
                    $url = 'https://' . $url;
                }
                if (mb_strlen($url) > $limits['website']) {
                    return ['error' => 'Website URLs are limited to ' . $limits['website'] . ' characters.'];
                }
                if (!filter_var($url, FILTER_VALIDATE_URL)) {
                    return ['error' => 'One of the website addresses is not a valid URL.'];
                }
                $websites[] = $url;
            }
            // Meta accepts at most two.
            $payload['websites'] = array_slice($websites, 0, 2);
        }

        if ($handle) {
            $payload['profile_picture_handle'] = $handle;
        }

        $res = whatsapp_graph_post_json($phone_number_id . '/whatsapp_business_profile', $payload, $token);
        if (isset($res['error'])) {
            return ['error' => $res['error'], 'error_code' => $res['error_code'] ?? ''];
        }
        return ['saved' => true];
    }

    /**
     * Upload a profile picture and return the handle to attach to the profile.
     *
     * @param array $file one entry from $_FILES
     */
    public function upload_profile_picture($phone_number_id, $file)
    {
        // Checked here as well as in save_business_profile(): the upload alone
        // spends the owning account's token and Meta upload quota.
        if (!$this->number_permitted($phone_number_id, 'manage')) {
            return $this->number_denied('manage');
        }
        $token = $this->token_for_number($phone_number_id);
        if (!$token) {
            return ['error' => 'WhatsApp is not connected.'];
        }
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['error' => 'No image was uploaded.'];
        }
        if (!empty($file['error'])) {
            return ['error' => 'The image failed to upload (code ' . (int) $file['error'] . ').'];
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size > 5 * 1024 * 1024) {
            return ['error' => 'The image must be 5 MB or smaller.'];
        }

        $info = @getimagesize($file['tmp_name']);
        if (!$info) {
            return ['error' => 'That file is not a readable image.'];
        }
        $mime = $info['mime'] ?? '';
        if (!in_array($mime, ['image/jpeg', 'image/png'], true)) {
            return ['error' => 'Use a JPG or PNG image.'];
        }
        if ($info[0] < 192 || $info[1] < 192) {
            return ['error' => 'The image must be at least 192 × 192 pixels.'];
        }
        // WhatsApp crops to a circle — a non-square image loses its edges.
        if (abs($info[0] - $info[1]) > max(2, $info[0] * 0.02)) {
            return ['error' => 'Use a square image — WhatsApp crops the profile picture to a circle.'];
        }

        $binary = file_get_contents($file['tmp_name']);
        if ($binary === false || $binary === '') {
            return ['error' => 'The uploaded image could not be read.'];
        }

        $app_id = whatsapp_central_config()['app_id'];
        $res    = whatsapp_upload_handle($app_id, $binary, $mime, $token);
        if (isset($res['error'])) {
            return ['error' => $res['error']];
        }
        return ['handle' => $res['handle']];
    }

    /**
     * Submit a new display name. Meta reviews it against its display-name
     * policy before it goes live; the current state is `name_status`.
     */
    public function request_display_name($phone_number_id, $new_name)
    {
        $number = $this->registry_get_number($phone_number_id);
        if (!$number || $number->tenant_slug !== whatsapp_current_slug()) {
            return ['error' => 'Unknown phone number for this account.'];
        }
        $token = $this->token_for_number($phone_number_id);
        if (!$token) {
            return ['error' => 'WhatsApp is not connected.'];
        }

        $new_name = trim((string) $new_name);
        if (mb_strlen($new_name) < 3) {
            return ['error' => 'The display name must be at least 3 characters.'];
        }
        if (mb_strlen($new_name) > 75) {
            return ['error' => 'The display name is limited to 75 characters.'];
        }

        // Scalar edge parameter — form-encoded, unlike the JSON profile payload.
        $res = whatsapp_graph_post($phone_number_id, ['new_display_name' => $new_name], $token);
        if (isset($res['error'])) {
            return ['error' => $res['error'], 'error_code' => $res['error_code'] ?? ''];
        }

        $this->sync_number_health($number->tenant_slug);
        return ['submitted' => true];
    }

    /* ════════════════════ diagnostics ════════════════════ */

    /**
     * Connection self-test: credentials, token validity, webhook subscription,
     * number registration and template availability. Each check is
     * ok | warn | fail with a human explanation and (where it exists) the
     * UI action that fixes it.
     */
    public function get_diagnostics()
    {
        $checks = [];
        $slug   = whatsapp_current_slug();
        $conn   = $this->registry_get_connection($slug);
        $c      = whatsapp_central_config();

        $add = function ($key, $label, $state, $detail, $action = '') use (&$checks) {
            $checks[] = ['key' => $key, 'label' => $label, 'state' => $state, 'detail' => $detail, 'action' => $action];
        };

        // 1. Provider credentials
        if (!whatsapp_is_configured()) {
            $add('provider', 'Provider app', 'fail', 'The central Meta App credentials are missing. Set the App ID and Secret in the provider console.', 'credentials');
        } else {
            $add('provider', 'Provider app', 'ok', 'Central Meta App configured (App ID ' . substr($c['app_id'], 0, 6) . '…).');
        }

        // 2. Account connection
        if (!$conn) {
            $add('connection', 'Account connection', 'fail', 'No WhatsApp Business Account is connected to this workspace.', 'connect');
            return ['checks' => $checks, 'numbers' => [], 'failures' => []];
        }
        $add('connection', 'Account connection', 'ok',
            'Connected to ' . ($conn->waba_name ?: $conn->waba_id) . ($conn->fb_user_name ? ' by ' . $conn->fb_user_name : '') . '.');

        // 2b. Connection left behind by a central-app change. The registry row
        //     looks healthy — WABA, numbers, everything — but the token it holds
        //     was minted by the provider's PREVIOUS Meta App, so every call made
        //     with it fails with an opaque OAuth error. Only a reconnect fixes it.
        if ((string) ($conn->status ?? 'active') === 'stale') {
            $add('app_change', 'Meta App changed', 'fail',
                ($conn->sync_error ?: 'The stored access token no longer belongs to the central Meta App this system now uses.')
                . ' Nothing can be sent or received until you connect again — everything else below is reporting the old app.',
                'reconnect');
        }

        // 3. Access token — expiry window, then a live probe
        if (!empty($conn->token_expires)) {
            $left = strtotime($conn->token_expires) - time();
            if ($left <= 0) {
                $add('token', 'Access token', 'fail', 'The stored token expired on ' . $conn->token_expires . '. Reconnect the account.', 'reconnect');
            } elseif ($left < 7 * 86400) {
                $add('token', 'Access token', 'warn', 'The token expires in ' . max(1, (int) floor($left / 86400)) . ' day(s) — reconnect soon to avoid an outage.', 'reconnect');
            } else {
                $add('token', 'Access token', 'ok', 'Valid until ' . $conn->token_expires . '.');
            }
        }

        // 4. Webhook subscription on the WABA
        if (!empty($conn->waba_id)) {
            $subs = whatsapp_graph_get($conn->waba_id . '/subscribed_apps', [], $conn->access_token);
            if (isset($subs['error'])) {
                $hint = whatsapp_error_hint($subs['error_code'] ?? '', $subs['error']);
                $add('webhook', 'Webhook subscription', 'fail',
                    $subs['error'] . ($hint ? ' — ' . $hint['hint'] : ''), $hint['action'] ?? '');
            } else {
                $subscribed = !empty($subs['data']);
                $this->master_exec(
                    "UPDATE {$this->mp()}whatsapp_connections SET webhook_subscribed = ? WHERE tenant_slug = ?",
                    [$subscribed ? 1 : 0, $slug]
                );
                $add('webhook', 'Webhook subscription', $subscribed ? 'ok' : 'fail',
                    $subscribed
                        ? 'The app is subscribed — incoming messages and delivery receipts will arrive.'
                        : 'The app is not subscribed to this WhatsApp Business Account, so no incoming messages or delivery receipts will arrive. Reconnect to re-subscribe.',
                    $subscribed ? '' : 'reconnect');
            }
        }

        // 5. Numbers + registration (the 133010 surface)
        $numbers      = $this->registry_numbers_for_tenant($slug);
        $unregistered = [];
        foreach ($numbers as $n) {
            if (whatsapp_number_unregistered($n)) {
                $unregistered[] = $n->display_phone_number ?: $n->phone_number_id;
            }
        }
        if (empty($numbers)) {
            $add('numbers', 'Phone numbers', 'fail', 'No phone number is attached to this account. Refresh numbers, or add one in WhatsApp Manager.', 'refresh');
        } elseif (!empty($unregistered)) {
            $add('numbers', 'Phone numbers', 'fail',
                count($unregistered) . ' of ' . count($numbers) . ' number(s) are not registered on the Cloud API ('
                . implode(', ', $unregistered) . '). Sending fails with "(#133010) Account not registered" until you register them.',
                'register');
        } else {
            $add('numbers', 'Phone numbers', 'ok', count($numbers) . ' number(s) registered and able to send.');
        }

        // 6. Messaging health reported by Meta
        foreach ($numbers as $n) {
            $can = strtoupper((string) ($n->health_can_send ?? ''));
            if ($can === 'BLOCKED' || $can === 'LIMITED') {
                $add('health_' . $n->phone_number_id,
                    'Messaging health · ' . ($n->display_phone_number ?: $n->phone_number_id),
                    $can === 'BLOCKED' ? 'fail' : 'warn',
                    $n->health_detail ?: 'Meta reports this number as ' . strtolower($can) . ' for sending.',
                    'quality');
            }
            if (strtoupper((string) ($n->quality_rating ?? '')) === 'RED') {
                $add('quality_' . $n->phone_number_id,
                    'Quality rating · ' . ($n->display_phone_number ?: $n->phone_number_id),
                    'warn', 'Quality is low. Meta may reduce this number\'s messaging limit — review recent template content and opt-outs.', 'quality');
            }
        }

        // 6b. Has Meta ever actually delivered a webhook to us? Subscription
        //     can look perfect while every POST is bounced before it reaches
        //     the controller (CSRF, WAF, redirect, wrong URL).
        $last_hook     = (string) get_option('whatsapp_last_webhook_at');
        $last_rejected = (string) get_option('whatsapp_last_webhook_rejected_at');
        $hook_count    = (int) get_option('whatsapp_webhook_count');
        $has_inbound   = (int) $this->db->where('direction', 'incoming')->count_all_results($this->p . 'whatsapp_api_messages') > 0;

        if ($last_rejected !== '' && ($last_hook === '' || strtotime($last_rejected) >= strtotime($last_hook))) {
            $add('webhook_delivery', 'Webhook delivery', 'fail',
                'Meta reached this server at ' . $last_rejected . ' but the payload failed signature verification. '
                . 'The App Secret stored here does not match the Meta App sending the events — re-enter it in the provider console.',
                'credentials');
        } elseif ($last_hook === '') {
            $add('webhook_delivery', 'Webhook delivery', $has_inbound ? 'warn' : 'fail',
                'No webhook has ever been received from Meta. Until one arrives, incoming messages cannot appear in the Inbox '
                . 'and the 24-hour reply window can never open. Check that the Webhook URL registered in your Meta App exactly matches '
                . 'the one shown in the provider console, that it is subscribed to the "messages" field, and that nothing in front of '
                . 'the site (WAF, redirect, basic auth) is blocking the POST.', 'webhook');
        } else {
            $age = time() - strtotime($last_hook);
            $add('webhook_delivery', 'Webhook delivery', 'ok',
                $hook_count . ' webhook event(s) received — most recently ' . whatsapp_time_ago($last_hook)
                . ($age > 604800 ? '. Nothing for over a week; that is normal only if nobody has messaged you.' : '.'));
        }

        // 7. Who pays Meta for this tenant's messaging
        $credit = (string) ($conn->credit_status ?? 'self');
        if ($credit === 'shared') {
            $add('billing', 'Messaging billing', 'ok',
                'Messaging is charged to your provider\'s WhatsApp credit line — no payment method is needed on this account.');
        } elseif ($credit === 'failed') {
            $add('billing', 'Messaging billing', 'warn',
                'The provider credit line could not be attached' . (!empty($conn->credit_error) ? ': ' . $conn->credit_error : '')
                . '. Until it is, Meta bills this WhatsApp Business Account directly, so it needs its own payment method.', 'billing');
        } elseif ($c['share_credit_line']) {
            $add('billing', 'Messaging billing', 'warn',
                'Your provider bills messaging centrally, but this account is not attached to their credit line yet. Reconnect, or ask your provider to attach it.', 'reconnect');
        } else {
            $add('billing', 'Messaging billing', 'ok',
                'Meta bills this WhatsApp Business Account directly — keep a valid payment method on it in WhatsApp Manager.', 'billing');
        }

        // 8. Approved templates
        $approved = (int) $this->db->where('status', 'APPROVED')->count_all_results($this->p . 'whatsapp_api_templates');
        if ($approved === 0) {
            $add('templates', 'Message templates', 'warn',
                'No approved templates are cached. Sync templates — without one you cannot start a conversation or run a campaign.', 'templates');
        } else {
            $add('templates', 'Message templates', 'ok', $approved . ' approved template(s) ready to send.');
        }

        return [
            'checks'   => $checks,
            'numbers'  => $numbers,
            'failures' => $this->get_failure_breakdown(),
        ];
    }

    /**
     * Recent send failures grouped by Cloud API error code, each with the
     * plain-English cause and fix.
     */
    public function get_failure_breakdown($days = 7, $limit = 8)
    {
        $since = date('Y-m-d H:i:s', strtotime('-' . (int) $days . ' days'));
        $sql   = "SELECT COALESCE(NULLIF(error_code,''),'') AS error_code,
                         COUNT(*) AS total,
                         MAX(created_at) AS last_at,
                         SUBSTRING_INDEX(GROUP_CONCAT(error_message ORDER BY id DESC SEPARATOR '||'), '||', 1) AS sample
                  FROM {$this->p}whatsapp_api_messages
                  WHERE status = 'failed' AND created_at >= ?
                  GROUP BY COALESCE(NULLIF(error_code,''),'')
                  ORDER BY total DESC
                  LIMIT " . (int) $limit;
        $q    = $this->db->query($sql, [$since]);
        $rows = $q ? $q->result() : [];

        foreach ($rows as $r) {
            $hint = whatsapp_error_hint($r->error_code, $r->sample);
            $r->title  = $hint['title'] ?? ($r->sample ?: 'Send failed');
            $r->hint   = $hint['hint'] ?? '';
            $r->action = $hint['action'] ?? '';
        }
        return $rows;
    }

    /** Daily in/out volume for the overview sparkline. */
    public function get_activity_series($days = 14)
    {
        $since = date('Y-m-d', strtotime('-' . ((int) $days - 1) . ' days'));
        $sql   = "SELECT DATE(created_at) AS d,
                         SUM(direction = 'incoming') AS incoming,
                         SUM(direction = 'outgoing') AS outgoing,
                         SUM(status = 'failed') AS failed
                  FROM {$this->p}whatsapp_api_messages
                  WHERE created_at >= ?
                  GROUP BY DATE(created_at)";
        $q  = $this->db->query($sql, [$since . ' 00:00:00']);
        $by = [];
        foreach (($q ? $q->result() : []) as $r) {
            $by[$r->d] = $r;
        }

        $out = [];
        for ($i = (int) $days - 1; $i >= 0; $i--) {
            $day = date('Y-m-d', strtotime('-' . $i . ' days'));
            $outgoing = isset($by[$day]) ? (int) $by[$day]->outgoing : 0;
            $failed   = isset($by[$day]) ? (int) $by[$day]->failed : 0;

            $out[] = [
                'date'     => $day,
                'label'    => date('M j', strtotime($day)),
                'dow'      => date('D', strtotime($day)),
                'incoming' => isset($by[$day]) ? (int) $by[$day]->incoming : 0,
                'outgoing' => $outgoing,
                'failed'   => $failed,
                // Failures are a subset of outgoing — split them out so the
                // stack never double-counts a message.
                'sent'     => max(0, $outgoing - $failed),
            ];
        }
        return $out;
    }

    /* ════════════════════ Cloud API sending ════════════════════ */

    /**
     * Low-level send. Returns ['wamid'=>..] or ['error'=>..].
     */
    public function api_send($phone_number_id, $payload)
    {
        // Whose number is this? A sender id arrives from a form post, and the
        // token is resolved from the registry, so ownership has to be settled
        // before anything is signed with someone else's credentials.
        if (!$this->number_permitted($phone_number_id, 'send')) {
            return $this->number_denied('send');
        }

        $token = $this->token_for_number($phone_number_id);
        if (!$token) {
            return ['error' => 'WhatsApp is not connected. Connect your account first.'];
        }

        // Pre-flight: a number Meta has not registered will always answer with
        // "(#133010) Account not registered" — fail fast with the actual fix.
        // Only an explicit non-CONNECTED state blocks — an unknown/blank status
        // (older row, or a token without management scope) still tries the send.
        $number = $this->registry_get_number($phone_number_id);
        if ($number && !empty($number->status) && strtoupper($number->status) !== 'CONNECTED'
            && (int) ($number->is_registered ?? 0) === 0) {
            return [
                'error'      => '(#133010) Account not registered — ' . ($number->display_phone_number ?: $phone_number_id)
                                . ' is not registered on the WhatsApp Cloud API.',
                'error_code' => '133010',
            ];
        }

        $payload['messaging_product'] = 'whatsapp';
        $res = whatsapp_graph_post_json($phone_number_id . '/messages', $payload, $token);
        if (isset($res['error'])) {
            // A registration/token failure invalidates our cached health — mark
            // the number so the UI stops claiming it is active.
            if (in_array($res['error_code'] ?? '', ['133010', '131031', '190'], true)) {
                $this->registry_store_number([
                    'phone_number_id' => $phone_number_id,
                    'tenant_slug'     => $number ? $number->tenant_slug : whatsapp_current_slug(),
                    'is_registered'   => ($res['error_code'] ?? '') === '133010' ? 0 : (int) ($number->is_registered ?? 0),
                    'last_error'      => substr($res['error'], 0, 480),
                    'last_checked_at' => date('Y-m-d H:i:s'),
                ]);
            }
            return ['error' => $res['error'], 'error_code' => $res['error_code'] ?? ''];
        }
        return ['wamid' => $res['messages'][0]['id'] ?? null];
    }

    /**
     * Free-form (non-template) messaging is off on the shared number.
     *
     * Meta only allows it inside a 24-hour window the customer opened, and
     * those inbound messages land in the PROVIDER's inbox — the number is
     * theirs. So a shared tenant has no window to reply inside, and letting
     * them send arbitrary text on a number other tenants also use is exactly
     * what the approved-template allowlist exists to prevent.
     */
    private function free_form_blocked()
    {
        return function_exists('whatsapp_shared_active') && whatsapp_shared_active();
    }

    private function free_form_block_reason()
    {
        $brand = function_exists('whatsapp_shared_brand') ? whatsapp_shared_brand() : 'the provider';

        return 'Only approved templates can be sent on ' . $brand
             . "'s shared WhatsApp number. Connect your own WhatsApp Business Account to send free-form messages.";
    }

    public function send_text($phone_number_id, $to, $text)
    {
        if ($this->free_form_blocked()) {
            return ['error' => $this->free_form_block_reason()];
        }

        return $this->api_send($phone_number_id, [
            'to'   => $to,
            'type' => 'text',
            'text' => ['body' => $text, 'preview_url' => true],
        ]);
    }

    /**
     * Validate + upload one browser file to Meta for this number.
     *
     * @param  array $file one entry from $_FILES
     * @return array{media_id:string,kind:string,mime:string}|array{error:string}
     */
    public function upload_media($phone_number_id, $file)
    {
        if (!$this->number_permitted($phone_number_id, 'send')) {
            return $this->number_denied('send');
        }
        if ($this->free_form_blocked()) {
            return ['error' => $this->free_form_block_reason()];
        }
        $token = $this->token_for_number($phone_number_id);
        if (!$token) {
            return ['error' => 'WhatsApp is not connected. Connect your account first.'];
        }
        if (!empty($file['error'])) {
            return ['error' => 'The file failed to upload (code ' . (int) $file['error'] . ').'];
        }
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['error' => 'No file was uploaded.'];
        }

        // Trust the bytes, not the browser-supplied type.
        $mime = '';
        if (function_exists('finfo_open') && ($fi = finfo_open(FILEINFO_MIME_TYPE))) {
            $mime = (string) finfo_file($fi, $file['tmp_name']);
            finfo_close($fi);
        }
        if ($mime === '' || $mime === 'application/octet-stream') {
            $mime = (string) ($file['type'] ?? '');
        }

        $kind = whatsapp_media_kind($mime);
        if (!$kind) {
            return ['error' => sprintf(
                'WhatsApp does not accept this file type (%s). Use JPG/PNG images, MP4 video, common audio formats, or PDF/Office documents.',
                $mime ?: 'unknown'
            )];
        }
        if ((int) ($file['size'] ?? 0) > $kind['max']) {
            return ['error' => sprintf(
                'This %s is too large — WhatsApp allows up to %s MB for %s files.',
                $kind['kind'],
                rtrim(rtrim(number_format($kind['max'] / 1048576, 1), '0'), '.'),
                $kind['kind']
            )];
        }

        $res = whatsapp_upload_media_file($phone_number_id, $file['tmp_name'], $mime, (string) ($file['name'] ?? ''), $token);
        if (isset($res['error'])) {
            return ['error' => $res['error']];
        }
        return ['media_id' => $res['media_id'], 'kind' => $kind['kind'], 'mime' => $mime];
    }

    /**
     * Send an already-uploaded media object as a free-form message.
     *
     * @param string $kind image|video|audio|document|sticker
     */
    public function send_media($phone_number_id, $to, $kind, $media_id, $caption = '', $filename = '')
    {
        if ($this->free_form_blocked()) {
            return ['error' => $this->free_form_block_reason()];
        }

        $media = ['id' => $media_id];
        // Meta rejects captions on audio and stickers.
        if ($caption !== '' && in_array($kind, ['image', 'video', 'document'], true)) {
            $media['caption'] = $caption;
        }
        if ($kind === 'document' && $filename !== '') {
            $media['filename'] = $filename;
        }
        return $this->api_send($phone_number_id, [
            'to'   => $to,
            'type' => $kind,
            $kind  => $media,
        ]);
    }

    /**
     * @param array  $body_params      plain strings for {{1}}..{{n}}
     * @param string $header_media_url optional link for IMAGE/VIDEO/DOCUMENT header
     */
    public function send_template($phone_number_id, $to, $name, $language, $body_params = [], $header_media_url = '')
    {
        // On the provider's shared number only the templates the provider put
        // on this tenant's allowlist may go out — an unreviewed send would
        // damage the quality rating of a number everyone else is using too.
        if (function_exists('whatsapp_shared_active') && whatsapp_shared_active()
            && !whatsapp_shared_template_allowed($name, $language)) {
            return ['error' => 'Template "' . $name . '" is not one of the approved templates shared with this account.'];
        }

        $components = [];
        if ($header_media_url !== '') {
            $tpl  = $this->get_template($name, $language);
            $comp = $tpl ? (json_decode($tpl->components, true) ?: []) : [];
            $format = 'image';
            foreach ($comp as $cmp) {
                if (strtoupper($cmp['type'] ?? '') === 'HEADER') {
                    $format = strtolower($cmp['format'] ?? 'image');
                }
            }
            $components[] = [
                'type'       => 'header',
                'parameters' => [[
                    'type'  => $format,
                    $format => ['link' => $header_media_url],
                ]],
            ];
        }
        if (!empty($body_params)) {
            $components[] = [
                'type'       => 'body',
                'parameters' => array_map(function ($v) {
                    return ['type' => 'text', 'text' => (string) $v];
                }, array_values($body_params)),
            ];
        }
        $payload = [
            'to'       => $to,
            'type'     => 'template',
            'template' => [
                'name'     => $name,
                'language' => ['code' => $language ?: 'en'],
            ],
        ];
        if (!empty($components)) {
            $payload['template']['components'] = $components;
        }
        return $this->api_send($phone_number_id, $payload);
    }

    /* ════════════════════ messages / contacts (tenant) ════════════════════ */

    public function log_message($data)
    {
        $defaults = [
            'direction' => 'outgoing', 'type' => 'text', 'status' => 'queued',
            // Stamped from PHP, never left to the column's CURRENT_TIMESTAMP
            // default: MySQL runs on the DB server's timezone while every time
            // this row is compared against (time_ago, "today", the activity
            // chart) comes from PHP on the app timezone. Mixing the two made
            // a message that just arrived read as hours old.
            'created_at' => date('Y-m-d H:i:s'),
        ];
        $this->db->insert($this->p . 'whatsapp_api_messages', array_merge($defaults, $data));
        return $this->db->insert_id();
    }

    public function message_exists_by_wamid($wamid)
    {
        if (empty($wamid)) {
            return false;
        }
        return $this->db->where('wamid', $wamid)->count_all_results($this->p . 'whatsapp_api_messages') > 0;
    }

    public function get_message($id)
    {
        return $this->db->where('id', (int) $id)->get($this->p . 'whatsapp_api_messages')->row();
    }

    public function get_contact($phone)
    {
        return $this->db->where('phone', $phone)->get($this->p . 'whatsapp_api_contacts')->row();
    }

    public function upsert_contact($phone, $data = [])
    {
        $existing = $this->get_contact($phone);
        if ($existing) {
            // Never blank an already-known name with an empty update.
            foreach (['name', 'profile_name'] as $k) {
                if (array_key_exists($k, $data) && ($data[$k] === '' || $data[$k] === null)) {
                    unset($data[$k]);
                }
            }
            if (!empty($data)) {
                $this->db->where('id', $existing->id)->update($this->p . 'whatsapp_api_contacts', $data);
            }
            return $existing->id;
        }
        $data['phone'] = $phone;
        // App timezone, not the DB clock — see log_message().
        $data['created_at'] = $data['created_at'] ?? date('Y-m-d H:i:s');
        $this->db->insert($this->p . 'whatsapp_api_contacts', $data);
        return $this->db->insert_id();
    }

    public function increment_unread($phone)
    {
        $this->db->query(
            "UPDATE {$this->p}whatsapp_api_contacts SET unread_count = unread_count + 1 WHERE phone = ?",
            [$phone]
        );
    }

    public function mark_read($phone)
    {
        $this->db->where('phone', $phone)->update($this->p . 'whatsapp_api_contacts', ['unread_count' => 0]);
    }

    public function set_optout($phone, $opted_out)
    {
        $this->upsert_contact($phone, []);
        $this->db->where('phone', $phone)->update($this->p . 'whatsapp_api_contacts', ['opted_out' => $opted_out ? 1 : 0]);
    }

    public function get_contacts($limit = 300)
    {
        return $this->db->order_by('GREATEST(COALESCE(last_incoming_at, "1970-01-01"), COALESCE(last_outgoing_at, "1970-01-01"))', 'DESC', false)
            ->limit($limit)->get($this->p . 'whatsapp_api_contacts')->result();
    }

    /**
     * Inbox threads: conversations synthesised from the flat message log
     * (campaign blasts excluded), joined with contacts for names, unread
     * counts and the 24-hour session window.
     */
    /**
     * Inbox threads, synthesised from the flat message log.
     *
     * The last message is resolved by joining the newest row per phone rather
     * than concatenating bodies — GROUP_CONCAT silently skips NULL bodies (a
     * media message or a failed send), which made the preview show a stale
     * message, and it truncated at the first newline.
     *
     * @param string $scope  conversations = exclude campaign blasts (default),
     *                       all = include them
     * @param string $search optional phone / name filter
     */
    public function get_chat_threads($limit = 100, $scope = 'conversations', $search = '')
    {
        $where  = "m.phone != ''";
        $params = [];
        if ($scope !== 'all') {
            $where .= ' AND m.campaign_id IS NULL';
        }

        $filter = '';
        $search = trim((string) $search);
        if ($search !== '') {
            $filter = "WHERE (t.phone LIKE ? OR COALESCE(c.name, c.profile_name, lm.contact_name, '') LIKE ?)";
            $like   = '%' . $search . '%';
            $params = [$like, $like];
        }

        $sql = "SELECT t.phone,
                       COALESCE(NULLIF(c.name,''), NULLIF(c.profile_name,''), NULLIF(lm.contact_name,'')) AS contact_name,
                       lm.body        AS last_message,
                       lm.type        AS last_type,
                       lm.direction   AS last_direction,
                       lm.status      AS last_status,
                       lm.media_id    AS last_media_id,
                       lm.campaign_id AS last_campaign_id,
                       t.last_time,
                       t.total_messages,
                       COALESCE(c.unread_count, 0) AS unread_count,
                       c.last_incoming_at,
                       COALESCE(c.opted_out, 0) AS opted_out
                FROM (
                    SELECT phone, MAX(id) AS last_id, MAX(created_at) AS last_time, COUNT(*) AS total_messages
                    FROM {$this->p}whatsapp_api_messages m
                    WHERE {$where}
                    GROUP BY phone
                ) t
                JOIN {$this->p}whatsapp_api_messages lm ON lm.id = t.last_id
                LEFT JOIN {$this->p}whatsapp_api_contacts c ON c.phone = t.phone
                {$filter}
                ORDER BY t.last_time DESC
                LIMIT " . (int) $limit;

        $q = $this->db->query($sql, $params);
        return $q ? $q->result() : [];
    }

    /**
     * Why is the inbox empty? Distinguishes "nothing has happened yet" from
     * "everything you sent was a campaign blast, which this view hides".
     */
    public function get_inbox_summary()
    {
        $t = $this->p . 'whatsapp_api_messages';
        return [
            'total'      => (int) $this->db->count_all($t),
            'incoming'   => (int) $this->db->where('direction', 'incoming')->count_all_results($t),
            'outgoing'   => (int) $this->db->where('direction', 'outgoing')->count_all_results($t),
            'campaign'   => (int) $this->db->where('campaign_id IS NOT NULL', null, false)->count_all_results($t),
            'failed'     => (int) $this->db->where('status', 'failed')->count_all_results($t),
            'webhook_at' => (string) get_option('whatsapp_last_webhook_at'),
        ];
    }

    /**
     * Unread inbound messages for the notifier: the newest incoming message of
     * every contact that still has an unread count, newest first.
     *
     * Polled from every admin page, so it stays one indexed query — the
     * contacts table carries the unread counter, and only those phones are
     * joined back to the message log.
     *
     * @return array [['id'=>…, 'phone'=>…, 'name'=>…, 'snippet'=>…, 'time'=>…], …]
     */
    public function get_unread_inbox_items($limit = 6)
    {
        $sql = "SELECT m.id, m.phone, m.body, m.type, m.created_at,
                       COALESCE(NULLIF(c.name,''), NULLIF(c.profile_name,''), NULLIF(m.contact_name,'')) AS name
                FROM {$this->p}whatsapp_api_contacts c
                JOIN {$this->p}whatsapp_api_messages m ON m.id = (
                    SELECT MAX(m2.id) FROM {$this->p}whatsapp_api_messages m2
                    WHERE m2.phone = c.phone AND m2.direction = 'incoming'
                )
                WHERE c.unread_count > 0
                ORDER BY m.id DESC
                LIMIT " . (int) $limit;

        $q    = $this->db->query($sql);
        $rows = $q ? $q->result() : [];

        $items = [];
        foreach ($rows as $r) {
            $items[] = [
                'id'      => (int) $r->id,
                'phone'   => (string) $r->phone,
                'name'    => (string) $r->name,
                'snippet' => function_exists('whatsapp_notify_snippet')
                    ? whatsapp_notify_snippet($r->body, $r->type)
                    : (string) $r->body,
                'time'    => whatsapp_time_ago($r->created_at),
            ];
        }

        return $items;
    }

    /** Total unread inbound messages across every contact. */
    public function get_unread_total()
    {
        $row = $this->db->select_sum('unread_count')->get($this->p . 'whatsapp_api_contacts')->row();

        return (int) ($row->unread_count ?? 0);
    }

    public function get_chat_messages($phone, $limit = 100)
    {
        $rows = $this->db->where('phone', $phone)
            ->order_by('id', 'DESC')->limit($limit)
            ->get($this->p . 'whatsapp_api_messages')->result();
        return array_reverse($rows);
    }

    /* ════════════════════ templates (tenant) ════════════════════ */

    public function get_templates($only_approved = false)
    {
        if ($only_approved) {
            $this->db->where('status', 'APPROVED');
        }
        return $this->db->order_by('name', 'ASC')->get($this->p . 'whatsapp_api_templates')->result();
    }

    public function get_template($name, $language = null)
    {
        $this->db->where('name', $name);
        if ($language !== null) {
            $this->db->where('language', $language);
        }
        return $this->db->get($this->p . 'whatsapp_api_templates')->row();
    }

    /** One cached template by local row id (view / edit drawer). */
    public function get_template_row($id)
    {
        return $this->db->where('id', (int) $id)->get($this->p . 'whatsapp_api_templates')->row();
    }

    /** Pull the WABA's template library into the local cache table. */
    public function sync_templates()
    {
        $conn = $this->connection();
        if (!$conn || empty($conn->access_token) || empty($conn->waba_id)) {
            return ['error' => 'WhatsApp is not connected.'];
        }
        $res = whatsapp_graph_get($conn->waba_id . '/message_templates', [
            'fields' => 'id,name,language,status,category,components,rejected_reason,quality_score',
            'limit'  => 200,
        ], $conn->access_token);
        if (isset($res['error'])) {
            return ['error' => $res['error']];
        }

        // Full refresh — the table is a cache of the remote library. Only the
        // account's OWN rows are cleared: templates mirrored from a provider's
        // shared library belong to whatsapp_shared_sync_templates().
        $this->db->where('source !=', 'shared')->or_where('source IS NULL', null, false)
            ->delete($this->p . 'whatsapp_api_templates');

        $count = 0;
        foreach (($res['data'] ?? []) as $t) {
            $components = $t['components'] ?? [];
            $body       = whatsapp_template_body_text($components);
            // quality_score comes back as {score, date} — only the score matters here.
            $quality = $t['quality_score']['score'] ?? null;
            $this->db->insert($this->p . 'whatsapp_api_templates', [
                'template_id'      => $t['id'] ?? null,
                'name'             => $t['name'] ?? '',
                'language'         => $t['language'] ?? 'en',
                'category'         => $t['category'] ?? null,
                'status'           => $t['status'] ?? null,
                'rejected_reason'  => $t['rejected_reason'] ?? null,
                'quality_score'    => is_string($quality) ? $quality : null,
                'components'       => json_encode($components),
                'body_text'        => $body,
                'variables_count'  => whatsapp_template_variables_count($body),
                'has_media_header' => whatsapp_template_has_media_header($components) ? 1 : 0,
                'last_synced_at'   => date('Y-m-d H:i:s'),
                'source'           => 'own',
            ]);
            $count++;
        }
        return ['synced' => $count];
    }

    /**
     * Submit a new template to Meta for approval.
     *
     * @param array $data name, language, category, header_text, body_text,
     *                    footer_text, samples[], header_sample
     */
    public function create_template($data)
    {
        $conn = $this->connection();
        if (!$conn || empty($conn->access_token) || empty($conn->waba_id)) {
            return ['error' => 'WhatsApp is not connected.'];
        }

        $name   = strtolower(preg_replace('/[^a-z0-9_]+/', '_', strtolower(trim($data['name'] ?? ''))));
        $body   = trim($data['body_text'] ?? '');
        $header = trim((string) ($data['header_text'] ?? ''));
        if ($name === '' || $body === '') {
            return ['error' => 'Template name and body are required.'];
        }
        // Meta's variable rules, checked before the round-trip so the reply
        // names the actual fix instead of a generic Graph rejection.
        $var_error = whatsapp_template_variable_error($body, $header);
        if ($var_error !== '') {
            return ['error' => $var_error];
        }

        $components = [];
        if ($header !== '') {
            $header_component = ['type' => 'HEADER', 'format' => 'TEXT', 'text' => $header];
            if (whatsapp_template_variables_count($header) > 0) {
                // A header variable without its sample is an instant rejection.
                $header_component['example'] = ['header_text' => whatsapp_template_examples($header, [$data['header_sample'] ?? ''])];
            }
            $components[] = $header_component;
        }
        $body_component = ['type' => 'BODY', 'text' => $body];
        if (whatsapp_template_variables_count($body) > 0) {
            $body_component['example'] = ['body_text' => [whatsapp_template_examples($body, $data['samples'] ?? [])]];
        }
        $components[] = $body_component;
        if (!empty($data['footer_text'])) {
            $components[] = ['type' => 'FOOTER', 'text' => trim($data['footer_text'])];
        }

        $res = whatsapp_graph_post_json($conn->waba_id . '/message_templates', [
            'name'                  => $name,
            'language'              => $data['language'] ?: 'en',
            'category'              => in_array($data['category'] ?? '', ['MARKETING', 'UTILITY', 'AUTHENTICATION'], true) ? $data['category'] : 'MARKETING',
            'allow_category_change' => true,
            'components'            => $components,
        ], $conn->access_token);

        if (isset($res['error'])) {
            return ['error' => $res['error']];
        }
        $this->sync_templates();
        return ['id' => $res['id'] ?? null, 'name' => $name];
    }

    /**
     * Edit an existing template and push it back to Meta for review — the
     * fix-and-resubmit path for a REJECTED template, and the change path for
     * an APPROVED or PAUSED one.
     *
     * The payload is rebuilt from the CACHED components, not from scratch, so
     * everything the form cannot express survives the edit: a media header
     * (whose uploaded sample handle cannot be recreated here) and the whole
     * BUTTONS component are carried through untouched. Name and language are
     * immutable at Meta and are never sent.
     *
     * @param int   $id   local row id
     * @param array $data header_text, body_text, footer_text, category,
     *                    samples[], header_sample
     */
    public function update_template($id, $data)
    {
        $conn = $this->connection();
        if (!$conn || empty($conn->access_token) || empty($conn->waba_id)) {
            return ['error' => 'WhatsApp is not connected.'];
        }

        $row = $this->get_template_row($id);
        if (!$row) {
            return ['error' => 'Unknown template.'];
        }
        if (empty($row->template_id)) {
            return ['error' => 'This template has no Meta ID cached yet. Sync templates and try again.'];
        }
        if (!whatsapp_template_editable($row->status)) {
            return ['error' => whatsapp_template_edit_block_reason($row->status)];
        }

        $body = trim((string) ($data['body_text'] ?? ''));
        if ($body === '') {
            return ['error' => 'Template body is required.'];
        }
        $var_error = whatsapp_template_variable_error($body, $data['header_text'] ?? '');
        if ($var_error !== '') {
            return ['error' => $var_error];
        }

        $components = whatsapp_template_build_edit_components(whatsapp_template_components($row), [
            'header_text'   => $data['header_text'] ?? '',
            'body_text'     => $body,
            'footer_text'   => $data['footer_text'] ?? '',
            'samples'       => $data['samples'] ?? [],
            'header_sample' => $data['header_sample'] ?? '',
        ]);
        $vars    = whatsapp_template_variables_count($body);
        $payload = ['components' => $components];

        // Meta rejects a category change while the template is APPROVED.
        $category = strtoupper(trim((string) ($data['category'] ?? '')));
        if (in_array($category, ['MARKETING', 'UTILITY', 'AUTHENTICATION'], true)
            && strtoupper((string) $row->status) !== 'APPROVED'
            && $category !== strtoupper((string) $row->category)) {
            $payload['category'] = $category;
        }

        $res = whatsapp_graph_post_json($row->template_id, $payload, $conn->access_token);
        if (isset($res['error'])) {
            return ['error' => $res['error']];
        }

        // Reflect the edit locally straight away (an accepted edit re-enters
        // review), then pull Meta's own state so the cache is authoritative.
        $this->db->where('id', $row->id)->update($this->p . 'whatsapp_api_templates', [
            'components'       => json_encode($components),
            'body_text'        => $body,
            'variables_count'  => $vars,
            'has_media_header' => whatsapp_template_has_media_header($components) ? 1 : 0,
            'category'         => $payload['category'] ?? $row->category,
            'status'           => 'PENDING',
            'rejected_reason'  => null,
        ]);
        $this->sync_templates();

        return ['updated' => true, 'name' => $row->name];
    }

    public function delete_template($name)
    {
        $conn = $this->connection();
        if (!$conn || empty($conn->access_token) || empty($conn->waba_id)) {
            return ['error' => 'WhatsApp is not connected.'];
        }
        $res = whatsapp_graph_delete($conn->waba_id . '/message_templates', ['name' => $name], $conn->access_token);
        if (isset($res['error'])) {
            return ['error' => $res['error']];
        }
        $this->db->where('name', $name)->delete($this->p . 'whatsapp_api_templates');
        return ['deleted' => true];
    }

    /* ════════════════════ webhook ingestion (tenant context) ════════════════════ */

    /**
     * Process one webhook change value in the owning tenant's context.
     * Handles inbound messages, delivery statuses and template status updates.
     */
    public function ingest_change($field, $value)
    {
        if ($field === 'message_template_status_update') {
            $this->_ingest_template_status($value);
            return;
        }
        if ($field !== 'messages' || !is_array($value)) {
            return;
        }

        $phone_number_id = $value['metadata']['phone_number_id'] ?? null;

        // Delivery receipts for outgoing messages.
        foreach (($value['statuses'] ?? []) as $status) {
            $this->_ingest_status($status);
        }

        // Inbound messages.
        $profiles = [];
        foreach (($value['contacts'] ?? []) as $c) {
            if (!empty($c['wa_id'])) {
                $profiles[$c['wa_id']] = $c['profile']['name'] ?? '';
            }
        }
        foreach (($value['messages'] ?? []) as $msg) {
            $this->_ingest_inbound($msg, $profiles, $phone_number_id);
        }
    }

    private function _ingest_template_status($value)
    {
        $name = $value['message_template_name'] ?? '';
        $lang = $value['message_template_language'] ?? '';
        $event = strtoupper($value['event'] ?? '');
        if ($name === '' || $event === '') {
            return;
        }
        $map = ['APPROVED' => 'APPROVED', 'REJECTED' => 'REJECTED', 'PENDING' => 'PENDING', 'PAUSED' => 'PAUSED', 'DISABLED' => 'DISABLED'];
        if (!isset($map[$event])) {
            return;
        }

        // The rejection reason rides on the same event — keep it so the
        // Templates tab can explain the failure and offer a resubmit.
        $reason = strtoupper(trim((string) ($value['reason'] ?? '')));
        $update = ['status' => $map[$event]];
        if ($map[$event] === 'REJECTED') {
            $update['rejected_reason'] = $reason !== '' ? $reason : 'NONE';
        } elseif ($map[$event] === 'APPROVED') {
            $update['rejected_reason'] = null;
        }

        // A template approved for the first time is not in the cache at all —
        // pull the library so the row (body, variables, category) exists before
        // anything downstream tries to use it.
        $this->db->where('name', $name);
        if ($lang !== '') {
            $this->db->where('language', $lang);
        }
        $known = $this->db->limit(1)->get($this->p . 'whatsapp_api_templates')->row();

        if (!$known) {
            if ($map[$event] !== 'APPROVED') {
                return;
            }
            $this->sync_templates();
        } else {
            $this->db->where('name', $name);
            if ($lang !== '') {
                $this->db->where('language', $lang);
            }
            $this->db->update($this->p . 'whatsapp_api_templates', $update);
        }

        // Approval (or withdrawal) changes what the Official WhatsApp channel
        // in Omni Messaging can deliver, so re-map it right here — the tenant
        // never has to open a page for a newly approved template to become
        // usable in hooks.
        if (function_exists('whatsapp_omni_autosync_templates')) {
            whatsapp_omni_autosync_templates(true);
        }
    }

    /** Rank used so late/duplicate receipts never downgrade a status. */
    private function status_rank($status)
    {
        $ranks = ['queued' => 0, 'accepted' => 1, 'pending' => 1, 'sent' => 2, 'delivered' => 3, 'read' => 4];
        return $ranks[$status] ?? 0;
    }

    private function _ingest_status($status)
    {
        $wamid  = $status['id'] ?? null;
        $new    = strtolower($status['status'] ?? '');
        if (!$wamid || $new === '') {
            return;
        }
        $error = null;
        $error_code = null;
        if ($new === 'failed') {
            $err        = $status['errors'][0] ?? [];
            $error      = substr(trim(($err['title'] ?? '') . ' ' . ($err['error_data']['details'] ?? '')), 0, 480) ?: 'Send failed';
            $error_code = isset($err['code']) ? (string) $err['code'] : null;
        }

        // Message log.
        $msg = $this->db->where('wamid', $wamid)->get($this->p . 'whatsapp_api_messages')->row();
        if ($msg) {
            $update = [];
            if ($new === 'failed') {
                $update = ['status' => 'failed', 'error_message' => $error, 'error_code' => $error_code];
            } elseif ($this->status_rank($new) > $this->status_rank($msg->status)) {
                $update = ['status' => $new];
            }
            if (!empty($update)) {
                $this->db->where('id', $msg->id)->update($this->p . 'whatsapp_api_messages', $update);
            }
        }

        // Campaign recipient + rollup counters.
        $recipient = $this->db->where('wamid', $wamid)->get($this->p . 'whatsapp_api_campaign_recipients')->row();
        if ($recipient) {
            $old_rank = $this->status_rank($recipient->status);
            if ($new === 'failed' && $recipient->status !== 'failed') {
                $this->db->where('id', $recipient->id)->update($this->p . 'whatsapp_api_campaign_recipients', ['status' => 'failed', 'error' => $error]);
                $this->db->query("UPDATE {$this->p}whatsapp_api_campaigns SET failed_count = failed_count + 1 WHERE id = ?", [$recipient->campaign_id]);
            } elseif (in_array($new, ['delivered', 'read'], true) && $this->status_rank($new) > $old_rank) {
                $this->db->where('id', $recipient->id)->update($this->p . 'whatsapp_api_campaign_recipients', ['status' => $new]);
                if ($new === 'delivered' || $old_rank < $this->status_rank('delivered')) {
                    $this->db->query("UPDATE {$this->p}whatsapp_api_campaigns SET delivered_count = delivered_count + 1 WHERE id = ?", [$recipient->campaign_id]);
                }
                if ($new === 'read') {
                    $this->db->query("UPDATE {$this->p}whatsapp_api_campaigns SET read_count = read_count + 1 WHERE id = ?", [$recipient->campaign_id]);
                }
            }
        }
    }

    private function _ingest_inbound($msg, $profiles, $phone_number_id)
    {
        $wamid = $msg['id'] ?? null;
        $from  = $msg['from'] ?? '';
        if ($from === '' || ($wamid && $this->message_exists_by_wamid($wamid))) {
            return; // duplicate delivery
        }

        $type    = $msg['type'] ?? 'unknown';
        $body    = '';
        $media   = ['id' => null, 'mime' => null, 'caption' => null];
        switch ($type) {
            case 'text':
                $body = $msg['text']['body'] ?? '';
                break;
            case 'button':
                $body = $msg['button']['text'] ?? '';
                break;
            case 'interactive':
                $i    = $msg['interactive'] ?? [];
                $body = $i['button_reply']['title'] ?? ($i['list_reply']['title'] ?? '');
                break;
            case 'reaction':
                $body = $msg['reaction']['emoji'] ?? '';
                break;
            case 'location':
                $loc  = $msg['location'] ?? [];
                $body = 'Location: ' . ($loc['latitude'] ?? '?') . ', ' . ($loc['longitude'] ?? '?');
                break;
            case 'image': case 'video': case 'audio': case 'document': case 'sticker':
                $m = $msg[$type] ?? [];
                $media = ['id' => $m['id'] ?? null, 'mime' => $m['mime_type'] ?? null, 'caption' => $m['caption'] ?? null];
                $body  = $media['caption'] ?? '';
                break;
            case 'contacts':
                $body = 'Shared a contact card';
                break;
        }

        $profile_name = $profiles[$from] ?? '';
        $is_first = $this->db->where('phone', $from)->where('direction', 'incoming')
            ->count_all_results($this->p . 'whatsapp_api_messages') === 0;

        $this->upsert_contact($from, [
            'profile_name'     => $profile_name,
            'last_incoming_at' => date('Y-m-d H:i:s'),
        ]);
        $this->increment_unread($from);

        // Inbound STOP/UNSUBSCRIBE handling → opt out of bulk campaigns.
        $trimmed = strtoupper(trim($body));
        if (in_array($trimmed, ['STOP', 'UNSUBSCRIBE', 'STOP ALL'], true)) {
            $this->set_optout($from, true);
        } elseif (in_array($trimmed, ['START', 'SUBSCRIBE'], true)) {
            $this->set_optout($from, false);
        }

        $this->log_message([
            'phone_number_id' => $phone_number_id,
            'direction'       => 'incoming',
            'phone'           => $from,
            'contact_name'    => $profile_name,
            'type'            => $type,
            'body'            => $body,
            'media_id'        => $media['id'],
            'media_mime'      => $media['mime'],
            'caption'         => $media['caption'],
            'wamid'           => $wamid,
            'status'          => 'received',
        ]);

        // Alert the staff who watch the inbox. A reaction is not a message
        // anyone needs to be pulled out of their work for.
        if ($type !== 'reaction' && function_exists('whatsapp_notify_inbound')) {
            $contact = $this->get_contact($from);
            $name    = $contact ? (string) ($contact->name ?: $contact->profile_name) : $profile_name;
            whatsapp_notify_inbound($from, $name !== '' ? $name : $profile_name, $body, $type);
        }

        // Auto-reply bot (never replies to reactions).
        if ($type !== 'reaction') {
            $this->handle_bot($phone_number_id, $from, $body, $is_first);
        }
    }

    /* ════════════════════ auto-reply bot ════════════════════ */

    public function get_bot_settings()
    {
        $raw = get_option('whatsapp_bot_settings');
        $def = $raw ? json_decode($raw, true) : [];
        return array_merge([
            'enabled' => 0, 'business_hours' => 0, 'open_time' => '09:00',
            'close_time' => '18:00', 'days' => [1, 2, 3, 4, 5, 6], 'tz_offset' => '+05:30',
        ], is_array($def) ? $def : []);
    }

    public function save_bot_settings($data)
    {
        $clean = [
            'enabled'        => !empty($data['enabled']) ? 1 : 0,
            'business_hours' => !empty($data['business_hours']) ? 1 : 0,
            'open_time'      => preg_match('/^\d{2}:\d{2}$/', $data['open_time'] ?? '') ? $data['open_time'] : '09:00',
            'close_time'     => preg_match('/^\d{2}:\d{2}$/', $data['close_time'] ?? '') ? $data['close_time'] : '18:00',
            'days'           => array_values(array_map('intval', (array) ($data['days'] ?? []))),
            'tz_offset'      => preg_match('/^[+-]\d{2}:\d{2}$/', $data['tz_offset'] ?? '') ? $data['tz_offset'] : '+05:30',
        ];
        update_option('whatsapp_bot_settings', json_encode($clean));
        return true;
    }

    public function get_rules($enabled_only = false)
    {
        if ($enabled_only) {
            $this->db->where('enabled', 1);
        }
        return $this->db->order_by('priority', 'DESC')->order_by('id', 'ASC')
            ->get($this->p . 'whatsapp_api_bot_rules')->result();
    }

    public function get_rule($id)
    {
        return $this->db->where('id', (int) $id)->get($this->p . 'whatsapp_api_bot_rules')->row();
    }

    public function save_rule($data, $id = null)
    {
        $clean = [
            'name'          => trim($data['name'] ?? ''),
            'trigger_type'  => in_array($data['trigger_type'] ?? '', ['welcome', 'keyword', 'away', 'default'], true) ? $data['trigger_type'] : 'keyword',
            'match_type'    => in_array($data['match_type'] ?? '', ['contains', 'exact', 'starts_with', 'any'], true) ? $data['match_type'] : 'contains',
            'keywords'      => trim($data['keywords'] ?? ''),
            'response_text' => trim($data['response_text'] ?? ''),
            'enabled'       => !empty($data['enabled']) ? 1 : 0,
            'priority'      => (int) ($data['priority'] ?? 0),
        ];
        if ($clean['name'] === '' || $clean['response_text'] === '') {
            return ['error' => 'Rule name and response are required.'];
        }
        if ($id) {
            $this->db->where('id', (int) $id)->update($this->p . 'whatsapp_api_bot_rules', $clean);
            return ['id' => (int) $id];
        }
        $this->db->insert($this->p . 'whatsapp_api_bot_rules', $clean);
        return ['id' => $this->db->insert_id()];
    }

    public function set_rule_enabled($id, $enabled)
    {
        $this->db->where('id', (int) $id)->update($this->p . 'whatsapp_api_bot_rules', ['enabled' => $enabled ? 1 : 0]);
    }

    public function delete_rule($id)
    {
        $this->db->where('id', (int) $id)->delete($this->p . 'whatsapp_api_bot_rules');
    }

    private function within_business_hours($settings)
    {
        $tz  = $settings['tz_offset'] ?: '+05:30';
        try {
            $now = new DateTime('now', new DateTimeZone($tz));
        } catch (\Throwable $e) {
            $now = new DateTime();
        }
        $day = (int) $now->format('N'); // 1=Mon..7=Sun
        if (!in_array($day, (array) $settings['days'], true)) {
            return false;
        }
        $time = $now->format('H:i');
        return $time >= $settings['open_time'] && $time <= $settings['close_time'];
    }

    private function keyword_matches($rule, $text)
    {
        if ($rule->match_type === 'any') {
            return true;
        }
        $text     = mb_strtolower(trim($text));
        $keywords = array_filter(array_map('trim', explode(',', mb_strtolower((string) $rule->keywords))));
        foreach ($keywords as $kw) {
            if ($kw === '') {
                continue;
            }
            if ($rule->match_type === 'exact' && $text === $kw) {
                return true;
            }
            if ($rule->match_type === 'starts_with' && strpos($text, $kw) === 0) {
                return true;
            }
            if ($rule->match_type === 'contains' && strpos($text, $kw) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Rule precedence: welcome (first inbound only) → keyword → away (outside
     * business hours) → default.
     */
    public function match_rule($text, $is_first_message, $settings)
    {
        $rules = $this->get_rules(true);
        $by_type = ['welcome' => [], 'keyword' => [], 'away' => [], 'default' => []];
        foreach ($rules as $r) {
            $by_type[$r->trigger_type][] = $r;
        }

        if ($is_first_message && !empty($by_type['welcome'])) {
            return $by_type['welcome'][0];
        }
        foreach ($by_type['keyword'] as $r) {
            if ($this->keyword_matches($r, $text)) {
                return $r;
            }
        }
        if (!empty($by_type['away']) && !empty($settings['business_hours']) && !$this->within_business_hours($settings)) {
            return $by_type['away'][0];
        }
        return $by_type['default'][0] ?? null;
    }

    public function handle_bot($phone_number_id, $phone, $text, $is_first_message)
    {
        $settings = $this->get_bot_settings();
        if (empty($settings['enabled']) || empty($phone_number_id)) {
            return;
        }
        $rule = $this->match_rule($text, $is_first_message, $settings);
        if (!$rule) {
            return;
        }

        $res = $this->send_text($phone_number_id, $phone, $rule->response_text);
        $this->db->query("UPDATE {$this->p}whatsapp_api_bot_rules SET hits = hits + 1 WHERE id = ?", [$rule->id]);
        $this->upsert_contact($phone, ['last_outgoing_at' => date('Y-m-d H:i:s')]);
        $this->log_message([
            'phone_number_id' => $phone_number_id,
            'direction'       => 'outgoing',
            'phone'           => $phone,
            'type'            => 'text',
            'body'            => $rule->response_text,
            'wamid'           => $res['wamid'] ?? null,
            'status'          => isset($res['error']) ? 'failed' : 'accepted',
            'error_message'   => isset($res['error']) ? substr($res['error'], 0, 480) : null,
            'error_code'      => $res['error_code'] ?? null,
            'is_bot_reply'    => 1,
        ]);

        // A bot reply only ever leaves inside the customer's own 24-hour window,
        // which Meta does not bill — recorded in the ledger, never charged.
        if (!isset($res['error']) && function_exists('whatsapp_credits_commit')) {
            whatsapp_credits_commit($phone, 'service', ['source' => 'bot']);
        }
    }

    /* ════════════════════ bulk campaigns ════════════════════ */

    public function get_campaigns($limit = 100)
    {
        return $this->db->order_by('id', 'DESC')->limit($limit)
            ->get($this->p . 'whatsapp_api_campaigns')->result();
    }

    public function get_campaign($id)
    {
        return $this->db->where('id', (int) $id)->get($this->p . 'whatsapp_api_campaigns')->row();
    }

    public function get_campaign_recipients($campaign_id, $limit = 500)
    {
        return $this->db->where('campaign_id', (int) $campaign_id)
            ->order_by('id', 'ASC')->limit($limit)
            ->get($this->p . 'whatsapp_api_campaign_recipients')->result();
    }

    /**
     * Resolve a recipient source into [ ['phone'=>..., 'name'=>...], ... ]
     * (deduped on the normalised phone, opted-out contacts removed).
     */
    public function build_recipients($source, $manual_text = '')
    {
        $raw = [];
        switch ($source) {
            case 'manual':
                foreach (preg_split('/[\r\n]+/', (string) $manual_text) as $line) {
                    $line = trim($line);
                    if ($line === '') {
                        continue;
                    }
                    $parts = array_map('trim', explode(',', $line, 2));
                    $raw[] = ['phone' => $parts[0], 'name' => $parts[1] ?? ''];
                }
                break;

            case 'leads':
                $rows = $this->db->select('name, phonenumber')
                    ->where('phonenumber IS NOT NULL')->where('phonenumber !=', '')
                    ->get($this->p . 'leads')->result();
                foreach ($rows as $r) {
                    $raw[] = ['phone' => $r->phonenumber, 'name' => $r->name];
                }
                break;

            case 'patients':
                // Patients are tblclients rows; the authoritative mobile is
                // tblpatients_extra.mobile_number with tblclients.phonenumber fallback.
                $sql = "SELECT c.company AS name,
                               COALESCE(NULLIF(pe.mobile_number, ''), c.phonenumber) AS phone
                        FROM {$this->p}clients c
                        LEFT JOIN {$this->p}patients_extra pe ON pe.patient_id = c.userid
                        WHERE c.active = 1
                          AND COALESCE(NULLIF(pe.mobile_number, ''), c.phonenumber) IS NOT NULL
                          AND COALESCE(NULLIF(pe.mobile_number, ''), c.phonenumber) != ''";
                if (!$this->db->table_exists($this->p . 'patients_extra')) {
                    $sql = "SELECT c.company AS name, c.phonenumber AS phone
                            FROM {$this->p}clients c
                            WHERE c.active = 1 AND c.phonenumber IS NOT NULL AND c.phonenumber != ''";
                }
                $q = $this->db->query($sql);
                foreach (($q ? $q->result() : []) as $r) {
                    $raw[] = ['phone' => $r->phone, 'name' => $r->name];
                }
                break;

            case 'contacts':
                $rows = $this->db->select("CONCAT(firstname, ' ', lastname) AS name, phonenumber")
                    ->where('active', 1)
                    ->where('phonenumber IS NOT NULL')->where('phonenumber !=', '')
                    ->get($this->p . 'contacts')->result();
                foreach ($rows as $r) {
                    $raw[] = ['phone' => $r->phonenumber, 'name' => $r->name];
                }
                break;
        }

        // Opted-out numbers.
        $optouts = [];
        foreach ($this->db->select('phone')->where('opted_out', 1)->get($this->p . 'whatsapp_api_contacts')->result() as $o) {
            $optouts[$o->phone] = true;
        }

        $seen = [];
        $out  = [];
        foreach ($raw as $r) {
            $phone = whatsapp_normalize_phone($r['phone']);
            if ($phone === '' || strlen($phone) < 8 || isset($seen[$phone]) || isset($optouts[$phone])) {
                continue;
            }
            $seen[$phone] = true;
            $out[] = ['phone' => $phone, 'name' => trim((string) $r['name'])];
            if (count($out) >= 20000) {
                break; // hard safety cap
            }
        }
        return $out;
    }

    /**
     * Create a campaign + its recipient queue.
     *
     * $data: name, phone_number_id, template_name, template_language,
     *        params (array of ['mode'=>'static'|'merge','value'=>...]),
     *        header_media_url, source, manual_numbers, scheduled_at, batch_size
     */
    public function create_campaign($data, $staff_id)
    {
        $tpl = $this->get_template($data['template_name'] ?? '', $data['template_language'] ?? null);
        if (!$tpl) {
            return ['error' => 'Unknown template. Sync templates first.'];
        }
        if (strtoupper((string) $tpl->status) !== 'APPROVED') {
            return ['error' => 'Only approved templates can be used for bulk messaging.'];
        }
        if ((int) $tpl->has_media_header === 1 && trim($data['header_media_url'] ?? '') === '') {
            return ['error' => 'This template has a media header — a header media URL is required.'];
        }

        // Sender number: explicit choice, or the tenant's default number.
        $phone_number_id = trim($data['phone_number_id'] ?? '');
        if ($phone_number_id === '') {
            $default = $this->default_number();
            $phone_number_id = $default ? $default->phone_number_id : '';
        }
        if ($phone_number_id === '') {
            return ['error' => 'No WhatsApp sending number available. Connect your account first.'];
        }

        $recipients = $this->build_recipients($data['source'] ?? 'manual', $data['manual_numbers'] ?? '');
        if (empty($recipients)) {
            return ['error' => 'No valid recipients found (opted-out and duplicate numbers are skipped).'];
        }

        $params = [];
        foreach ((array) ($data['params'] ?? []) as $pm) {
            $params[] = [
                'mode'  => (($pm['mode'] ?? 'static') === 'merge') ? 'merge' : 'static',
                'value' => (string) ($pm['value'] ?? ''),
            ];
        }

        $scheduled_at = null;
        $status       = 'running';
        if (!empty($data['scheduled_at'])) {
            $ts = strtotime($data['scheduled_at']);
            if ($ts && $ts > time()) {
                $scheduled_at = date('Y-m-d H:i:s', $ts);
                $status       = 'scheduled';
            }
        }

        $this->db->insert($this->p . 'whatsapp_api_campaigns', [
            'name'              => trim($data['name'] ?? '') ?: ('Campaign ' . date('M j H:i')),
            'phone_number_id'   => $phone_number_id,
            'template_name'     => $tpl->name,
            'template_language' => $tpl->language,
            'template_params'   => json_encode($params),
            'header_media_url'  => trim($data['header_media_url'] ?? '') ?: null,
            'total_count'       => count($recipients),
            'status'            => $status,
            'scheduled_at'      => $scheduled_at,
            'batch_size'        => min(max((int) ($data['batch_size'] ?? 20), 5), 200),
            'created_by'        => (int) $staff_id,
            'started_at'        => $status === 'running' ? date('Y-m-d H:i:s') : null,
            'created_at'        => date('Y-m-d H:i:s'), // app timezone — see log_message()
        ]);
        $campaign_id = $this->db->insert_id();

        $batch = [];
        foreach ($recipients as $r) {
            $batch[] = [
                'campaign_id' => $campaign_id,
                'phone'       => $r['phone'],
                'name'        => $r['name'],
                'params'      => json_encode($this->resolve_params($params, $r)),
                'status'      => 'pending',
                'created_at'  => date('Y-m-d H:i:s'), // app timezone — see log_message()
            ];
            if (count($batch) >= 500) {
                $this->db->insert_batch($this->p . 'whatsapp_api_campaign_recipients', $batch);
                $batch = [];
            }
        }
        if (!empty($batch)) {
            $this->db->insert_batch($this->p . 'whatsapp_api_campaign_recipients', $batch);
        }

        return ['id' => $campaign_id, 'recipients' => count($recipients), 'status' => $status];
    }

    /** Fill template variables for one recipient (merge tags: name / phone). */
    private function resolve_params($params, $recipient)
    {
        $out = [];
        foreach ($params as $pm) {
            if ($pm['mode'] === 'merge') {
                if ($pm['value'] === 'name') {
                    $out[] = $recipient['name'] !== '' ? $recipient['name'] : 'there';
                } else {
                    $out[] = $recipient['phone'];
                }
            } else {
                $out[] = $pm['value'];
            }
        }
        return $out;
    }

    public function set_campaign_status($id, $status)
    {
        $allowed = ['running', 'paused', 'cancelled'];
        if (!in_array($status, $allowed, true)) {
            return false;
        }
        $update = ['status' => $status];
        if ($status === 'running') {
            $campaign = $this->get_campaign($id);
            if ($campaign && empty($campaign->started_at)) {
                $update['started_at'] = date('Y-m-d H:i:s');
            }
        }
        $this->db->where('id', (int) $id)->update($this->p . 'whatsapp_api_campaigns', $update);
        return true;
    }

    public function delete_campaign($id)
    {
        $this->db->where('campaign_id', (int) $id)->delete($this->p . 'whatsapp_api_campaign_recipients');
        $this->db->where('campaign_id', (int) $id)->update($this->p . 'whatsapp_api_messages', ['campaign_id' => null]);
        $this->db->where('id', (int) $id)->delete($this->p . 'whatsapp_api_campaigns');
    }

    /**
     * Cron tick: activate due scheduled campaigns, then drip one bounded batch
     * per running campaign. Called via whatsapp_run_automation().
     */
    public function process_campaign_queue()
    {
        // Scheduled → running.
        $this->db->query(
            "UPDATE {$this->p}whatsapp_api_campaigns
             SET status = 'running', started_at = COALESCE(started_at, NOW())
             WHERE status = 'scheduled' AND scheduled_at IS NOT NULL AND scheduled_at <= NOW()"
        );

        $running = $this->db->where('status', 'running')->limit(5)
            ->get($this->p . 'whatsapp_api_campaigns')->result();

        foreach ($running as $campaign) {
            $this->process_campaign_batch($campaign);
        }
    }

    public function process_campaign_batch($campaign)
    {
        // A grant revoked (or suspended) while a campaign was already running
        // pauses it rather than burning the queue into per-recipient failures.
        if (function_exists('whatsapp_shared_enabled') && whatsapp_shared_enabled()
            && !whatsapp_shared_can('bulk')) {
            $this->db->where('id', $campaign->id)->update($this->p . 'whatsapp_api_campaigns', ['status' => 'paused']);

            return;
        }

        $pending = $this->db->where('campaign_id', $campaign->id)->where('status', 'pending')
            ->order_by('id', 'ASC')->limit(max((int) $campaign->batch_size, 5))
            ->get($this->p . 'whatsapp_api_campaign_recipients')->result();

        if (empty($pending)) {
            $this->db->where('id', $campaign->id)->update($this->p . 'whatsapp_api_campaigns', [
                'status'       => 'completed',
                'completed_at' => date('Y-m-d H:i:s'),
            ]);
            return;
        }

        // Meta bills the 24-hour conversation, so the campaign charges one
        // credit per recipient it actually opens a window with — a contact
        // already inside an open window of this category rides along free.
        $category = function_exists('whatsapp_conversation_category_for')
            ? whatsapp_conversation_category_for($campaign->template_name, $campaign->template_language, 'trans')
            : null;

        foreach ($pending as $r) {
            // Late opt-outs are honoured mid-campaign.
            $contact = $this->get_contact($r->phone);
            if ($contact && (int) $contact->opted_out === 1) {
                $this->db->where('id', $r->id)->update($this->p . 'whatsapp_api_campaign_recipients', ['status' => 'skipped', 'error' => 'Opted out']);
                continue;
            }

            // Out of credits is an account-level fault: pause rather than burn
            // the rest of the queue into failures.
            if ($category !== null) {
                $gate = whatsapp_credits_precheck($r->phone, $category);
                if ($gate !== true) {
                    $this->db->where('id', $r->id)->update($this->p . 'whatsapp_api_campaign_recipients', [
                        'status' => 'failed',
                        'error'  => substr($gate['error'], 0, 480),
                    ]);
                    $this->db->query("UPDATE {$this->p}whatsapp_api_campaigns SET failed_count = failed_count + 1, status = 'paused' WHERE id = ?", [$campaign->id]);
                    return;
                }
            }

            $params = json_decode($r->params, true) ?: [];
            $res = $this->send_template(
                $campaign->phone_number_id,
                $r->phone,
                $campaign->template_name,
                $campaign->template_language,
                $params,
                (string) $campaign->header_media_url
            );

            if (isset($res['error'])) {
                $this->db->where('id', $r->id)->update($this->p . 'whatsapp_api_campaign_recipients', [
                    'status' => 'failed',
                    'error'  => substr($res['error'], 0, 480),
                ]);
                $this->db->query("UPDATE {$this->p}whatsapp_api_campaigns SET failed_count = failed_count + 1 WHERE id = ?", [$campaign->id]);
                $this->log_message([
                    'phone_number_id' => $campaign->phone_number_id,
                    'direction'       => 'outgoing',
                    'phone'           => $r->phone,
                    'contact_name'    => $r->name,
                    'type'            => 'template',
                    'body'            => null,
                    'template_name'   => $campaign->template_name,
                    'status'          => 'failed',
                    'error_message'   => substr($res['error'], 0, 480),
                    'error_code'      => $res['error_code'] ?? null,
                    'campaign_id'     => $campaign->id,
                ]);

                // Account-level faults (unregistered number, dead token, locked
                // account) fail every remaining recipient — pause instead of
                // burning the whole queue.
                if (in_array($res['error_code'] ?? '', ['133010', '190', '131031', '133005'], true)) {
                    $this->db->where('id', $campaign->id)->update($this->p . 'whatsapp_api_campaigns', ['status' => 'paused']);
                    return;
                }
            } else {
                $this->db->where('id', $r->id)->update($this->p . 'whatsapp_api_campaign_recipients', [
                    'status'  => 'sent',
                    'wamid'   => $res['wamid'],
                    'sent_at' => date('Y-m-d H:i:s'),
                ]);
                $this->db->query("UPDATE {$this->p}whatsapp_api_campaigns SET sent_count = sent_count + 1 WHERE id = ?", [$campaign->id]);
                $this->upsert_contact($r->phone, [
                    'name'             => $r->name,
                    'last_outgoing_at' => date('Y-m-d H:i:s'),
                ]);
                $this->log_message([
                    'phone_number_id' => $campaign->phone_number_id,
                    'direction'       => 'outgoing',
                    'phone'           => $r->phone,
                    'contact_name'    => $r->name,
                    'type'            => 'template',
                    'body'            => $this->render_template_preview($campaign->template_name, $campaign->template_language, $params),
                    'template_name'   => $campaign->template_name,
                    'wamid'           => $res['wamid'],
                    'status'          => 'accepted',
                    'campaign_id'     => $campaign->id,
                ]);

                if ($category !== null) {
                    whatsapp_credits_commit($r->phone, $category, ['source' => 'bulk']);
                }
            }
            usleep(200000); // ~5 msg/sec drip inside the batch
        }

        // Close out immediately when this batch drained the queue.
        $left = $this->db->where('campaign_id', $campaign->id)->where('status', 'pending')
            ->count_all_results($this->p . 'whatsapp_api_campaign_recipients');
        if ($left === 0) {
            $this->db->where('id', $campaign->id)->update($this->p . 'whatsapp_api_campaigns', [
                'status'       => 'completed',
                'completed_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    /** Human-readable body with {{n}} substituted, for the message log. */
    public function render_template_preview($name, $language, $params)
    {
        $tpl  = $this->get_template($name, $language);
        $body = $tpl ? (string) $tpl->body_text : ('[' . $name . ']');
        foreach (array_values($params) as $i => $v) {
            $body = preg_replace('/\{\{\s*' . ($i + 1) . '\s*\}\}/', $v, $body);
        }
        return $body;
    }

    /* ════════════════════ stats ════════════════════ */

    public function get_stats()
    {
        $today   = date('Y-m-d');
        $numbers = $this->registry_numbers_for_tenant(whatsapp_current_slug());
        $active  = 0;
        $checked = 0;
        foreach ($numbers as $n) {
            if (!empty($n->last_checked_at)) {
                $checked++;
            }
            if ((int) ($n->is_registered ?? 0) === 1) {
                $active++;
            }
        }

        return [
            'numbers'         => count($numbers),
            'numbers_active'  => $active,
            'numbers_checked' => $checked,
            'failed'         => (int) $this->db->where('status', 'failed')->count_all_results($this->p . 'whatsapp_api_messages'),
            'messages_total' => (int) $this->db->count_all($this->p . 'whatsapp_api_messages'),
            'messages_today' => (int) $this->db->where('created_at >=', $today . ' 00:00:00')->count_all_results($this->p . 'whatsapp_api_messages'),
            'incoming'       => (int) $this->db->where('direction', 'incoming')->count_all_results($this->p . 'whatsapp_api_messages'),
            'outgoing'       => (int) $this->db->where('direction', 'outgoing')->count_all_results($this->p . 'whatsapp_api_messages'),
            'templates'      => (int) $this->db->where('status', 'APPROVED')->count_all_results($this->p . 'whatsapp_api_templates'),
            'campaigns'      => (int) $this->db->count_all($this->p . 'whatsapp_api_campaigns'),
            'unread'         => (int) ($this->db->select_sum('unread_count')->get($this->p . 'whatsapp_api_contacts')->row()->unread_count ?? 0),
        ];
    }
}
