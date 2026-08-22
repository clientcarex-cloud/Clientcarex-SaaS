<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * WhatsApp (Official Cloud API) — 24-hour conversation credit engine.
 *
 * WHY THIS EXISTS
 * ---------------
 * Every other Omni Messaging channel (SMS, Email, AI Call) is billed per
 * message, so `_ccx_decrement_channel_balance()` is called once per send.
 * WhatsApp is not: Meta bills a 24-HOUR CONVERSATION. Once a conversation of a
 * given category is open with a contact, every further message inside that
 * window costs nothing. Charging one credit per message would therefore
 * overcharge a tenant many times over on a drip campaign or a chat thread.
 *
 * So, whenever the WhatsApp module is active and connected, the whole platform
 * switches the `whatsapp` channel to conversation billing:
 *
 *   • marketing        → 1 credit from the PROMOTIONAL balance, per 24h window
 *   • utility / auth   → 1 credit from the TRANSACTIONAL balance, per 24h window
 *   • service          → FREE (the customer opened the window by messaging us,
 *                        and Meta stopped charging for service conversations)
 *
 * Windows live in the tenant-local `whatsapp_api_conversations` table, so the
 * count is auditable: each row is one billed (or free) 24-hour conversation and
 * carries the number of messages that rode inside it.
 *
 * The actual balance columns stay where they always were — the tenant's row in
 * the master `ccx_msgs_allocations` table — so the Omni Messaging balance cards,
 * the recharge wizard and the master console all keep working unchanged.
 */

if (!function_exists('whatsapp_credits_load_core')) {
    /** Pull in the module's main helper on demand (bootstrap stays cheap). */
    function whatsapp_credits_load_core()
    {
        require_once __DIR__ . '/whatsapp_helper.php';
    }
}

if (!function_exists('whatsapp_conversations_table')) {
    function whatsapp_conversations_table()
    {
        return db_prefix() . 'whatsapp_api_conversations';
    }
}

if (!function_exists('whatsapp_credits_ensure_schema')) {
    /**
     * Self-heal the conversation ledger. Cheap after the first call in a
     * request, and it means an install that upgraded the module files without
     * re-activating still bills correctly.
     */
    function whatsapp_credits_ensure_schema()
    {
        static $done = false;
        if ($done) {
            return true;
        }

        $CI    = &get_instance();
        $table = whatsapp_conversations_table();

        if (!$CI->db->table_exists($table)) {
            $CI->db->query("CREATE TABLE IF NOT EXISTS `{$table}` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `phone` VARCHAR(30) NOT NULL,
                `category` VARCHAR(20) NOT NULL DEFAULT 'utility',
                `bucket` VARCHAR(10) DEFAULT NULL,
                `opened_at` DATETIME NOT NULL,
                `expires_at` DATETIME NOT NULL,
                `messages_count` INT(11) NOT NULL DEFAULT 0,
                `credits_charged` INT(11) NOT NULL DEFAULT 0,
                `source` VARCHAR(30) DEFAULT NULL,
                `last_message_at` DATETIME DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `phone_category` (`phone`,`category`,`expires_at`),
                KEY `expires_at` (`expires_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        }

        $done = $CI->db->table_exists($table);

        return $done;
    }
}

/* ─────────────────────────── Categories ─────────────────────────────────── */

if (!function_exists('whatsapp_conversation_categories')) {
    /** Meta's conversation categories → the allocation bucket each one bills to. */
    function whatsapp_conversation_categories()
    {
        return [
            'marketing'      => 'promo',
            'utility'        => 'trans',
            'authentication' => 'trans',
            'service'        => null, // free — the customer opened the window
        ];
    }
}

if (!function_exists('whatsapp_conversation_bucket')) {
    /**
     * Allocation bucket for a category: 'promo', 'trans', or null when the
     * conversation is free and must never touch a balance.
     */
    function whatsapp_conversation_bucket($category)
    {
        $map = whatsapp_conversation_categories();
        $c   = strtolower((string) $category);

        return array_key_exists($c, $map) ? $map[$c] : 'trans';
    }
}

if (!function_exists('whatsapp_conversation_category_label')) {
    function whatsapp_conversation_category_label($category)
    {
        $map = [
            'marketing'      => 'Marketing',
            'utility'        => 'Utility',
            'authentication' => 'Authentication',
            'service'        => 'Service (free)',
        ];
        $c = strtolower((string) $category);

        return $map[$c] ?? ucfirst($c ?: 'utility');
    }
}

if (!function_exists('whatsapp_conversation_category_for')) {
    /**
     * Resolve the billing category for one outbound send.
     *
     * Priority:
     *   1. The approved Cloud API template's own category (Meta is the
     *      authority — MARKETING / UTILITY / AUTHENTICATION).
     *   2. The Omni Messaging subtype (promotional → marketing).
     *   3. Utility.
     *
     * A free-form (non-template) message can only be sent inside an open
     * customer-service window, so it is always 'service'.
     *
     * @param string      $template_name Cloud API template name ('' = free-form)
     * @param string|null $language      template language
     * @param string      $subtype       'promo' | 'trans' (Omni Messaging subtype)
     */
    function whatsapp_conversation_category_for($template_name = '', $language = null, $subtype = 'trans')
    {
        if ((string) $template_name === '') {
            return 'service';
        }

        $CI    = &get_instance();
        $table = db_prefix() . 'whatsapp_api_templates';

        if ($CI->db->table_exists($table)) {
            $CI->db->where('name', $template_name);
            if (!empty($language)) {
                $CI->db->where('language', $language);
            }
            $row = $CI->db->limit(1)->get($table)->row();
            if ($row && !empty($row->category)) {
                $cat = strtolower((string) $row->category);
                if (array_key_exists($cat, whatsapp_conversation_categories())) {
                    return $cat;
                }
            }
        }

        return $subtype === 'promo' ? 'marketing' : 'utility';
    }
}

/* ──────────────────────── Conversation windows ──────────────────────────── */

if (!function_exists('whatsapp_conversation_open_row')) {
    /**
     * The still-open 24-hour conversation of this category with this contact,
     * or null when the next message would open a new (billable) one.
     */
    function whatsapp_conversation_open_row($phone, $category)
    {
        if (!whatsapp_credits_ensure_schema()) {
            return null;
        }

        $CI = &get_instance();

        return $CI->db
            ->where('phone', $phone)
            ->where('category', strtolower((string) $category))
            ->where('expires_at >', date('Y-m-d H:i:s'))
            ->order_by('expires_at', 'DESC')
            ->limit(1)
            ->get(whatsapp_conversations_table())
            ->row();
    }
}

if (!function_exists('whatsapp_service_window_open')) {
    /**
     * True while the customer-service window is open — the contact messaged us
     * less than 24 hours ago, so free-form text is allowed and free.
     */
    function whatsapp_service_window_open($phone)
    {
        $CI    = &get_instance();
        $table = db_prefix() . 'whatsapp_api_contacts';

        if (!$CI->db->table_exists($table)) {
            return false;
        }

        $contact = $CI->db->select('last_incoming_at')->where('phone', $phone)->limit(1)->get($table)->row();
        if (!$contact || empty($contact->last_incoming_at)) {
            return false;
        }

        return (time() - strtotime($contact->last_incoming_at)) < 86400;
    }
}

if (!function_exists('whatsapp_conversation_would_charge')) {
    /** Would the next message of this category cost a credit? */
    function whatsapp_conversation_would_charge($phone, $category)
    {
        if (whatsapp_conversation_bucket($category) === null) {
            return false; // service conversations are always free
        }

        return whatsapp_conversation_open_row($phone, $category) === null;
    }
}

/* ────────────────────────── Balance gateway ─────────────────────────────── */

if (!function_exists('whatsapp_credits_precheck')) {
    /**
     * Balance gate for one WhatsApp send, conversation-aware.
     *
     * A message that rides inside an already-open window costs nothing, so it
     * is NOT blocked by a zero balance — only an admin-disabled channel stops
     * it. A message that would open a new window is gated exactly like any
     * other channel: active, unexpired, count > 0.
     *
     * @return true|array ['status' => ..., 'error' => ...]
     */
    function whatsapp_credits_precheck($phone, $category)
    {
        /**
         * Shared (provider-owned) number.
         *
         * The provider's message cap is its cost guard and applies in BOTH
         * billing modes — a `free` grant is bounded by it, and a `credits`
         * grant still cannot exceed the throughput the provider lends. On a
         * free grant nothing is drawn from the tenant's own balance, but the
         * account's own channel switch is still honoured: "WhatsApp off" has
         * to mean off whoever is paying.
         */
        if (function_exists('whatsapp_shared_active') && whatsapp_shared_active()) {
            $quota = whatsapp_shared_quota_check();
            if ($quota !== true) {
                return $quota;
            }

            if (whatsapp_shared_is_free()) {
                if (function_exists('ccx_channel_send_enabled') && !ccx_channel_send_enabled('whatsapp')) {
                    return ['status' => 'inactive', 'error' => 'WhatsApp sending is switched off for this account'];
                }

                return true;
            }
        }

        $bucket = whatsapp_conversation_bucket($category);
        if ($bucket === null) {
            // Free (service) conversations never touch _ccx_check_channel_balance(),
            // so the superadmin's channel switch has to be honoured here too —
            // "WhatsApp off" must mean off, billed or not.
            if (function_exists('ccx_channel_send_enabled') && !ccx_channel_send_enabled('whatsapp')) {
                return ['status' => 'inactive', 'error' => 'WhatsApp sending is switched off for this account'];
            }

            return true; // free
        }

        $charges = whatsapp_conversation_would_charge($phone, $category);
        $check   = _ccx_check_channel_balance('whatsapp', $bucket);

        if ($check === true) {
            return true;
        }

        // "No allocation row" means billing is simply not provisioned for this
        // install (master / non-SaaS) — never block messaging over it.
        if (($check['status'] ?? '') === 'insufficient_balance'
            && stripos($check['error'] ?? '', 'No message allocation') !== false) {
            return true;
        }

        // A disabled channel always blocks. An empty/expired balance only
        // blocks the message that would actually open a new conversation.
        if (($check['status'] ?? '') === 'inactive' || $charges) {
            return $check;
        }

        return true;
    }
}

if (!function_exists('whatsapp_credits_commit')) {
    /**
     * Record a delivered WhatsApp message against its 24-hour conversation and
     * charge one credit when it opened a new one. Call this ONLY after Meta has
     * accepted the send.
     *
     * @param  array $meta ['source' => hook|campaign|auto_scheduler|bulk|chat|bot]
     * @return array ['charged' => bool, 'state' => 'new'|'open'|'free', 'bucket' => string|null]
     */
    function whatsapp_credits_commit($phone, $category, $meta = [])
    {
        $category = strtolower((string) $category);
        $bucket   = whatsapp_conversation_bucket($category);
        $now      = date('Y-m-d H:i:s');
        $source   = isset($meta['source']) ? substr((string) $meta['source'], 0, 30) : null;

        if (!whatsapp_credits_ensure_schema()) {
            return ['charged' => false, 'state' => 'free', 'bucket' => $bucket];
        }

        $CI   = &get_instance();
        $open = whatsapp_conversation_open_row($phone, $category);

        if ($open) {
            $CI->db->where('id', $open->id)->set('messages_count', 'messages_count + 1', false)
                ->update(whatsapp_conversations_table(), ['last_message_at' => $now]);

            // Metered even though it is free — the provider's cap counts
            // MESSAGES put through its number, not conversations opened.
            if (function_exists('whatsapp_shared_record_usage')) {
                whatsapp_shared_record_usage(1, 0, 0);
            }

            return ['charged' => false, 'state' => 'open', 'bucket' => $bucket];
        }

        // Free (service) windows are still recorded — the ledger is what the
        // Conversations panel reports, and "free" is a fact worth showing.
        // A `free` shared grant means the PROVIDER absorbs the cost, so the
        // tenant's own balance is never touched.
        $charge = $bucket !== null
            && !(function_exists('whatsapp_shared_is_free') && whatsapp_shared_is_free());

        $CI->db->insert(whatsapp_conversations_table(), [
            'phone'           => $phone,
            'category'        => $category,
            'bucket'          => $bucket,
            'opened_at'       => $now,
            'expires_at'      => date('Y-m-d H:i:s', time() + 86400),
            'messages_count'  => 1,
            'credits_charged' => $charge ? 1 : 0,
            'source'          => $source,
            'last_message_at' => $now,
        ]);

        if ($charge) {
            _ccx_decrement_channel_balance('whatsapp', $bucket);
        }

        if (function_exists('whatsapp_shared_record_usage')) {
            whatsapp_shared_record_usage(1, 1, $charge ? 1 : 0);
        }

        return ['charged' => $charge, 'state' => 'new', 'bucket' => $bucket];
    }
}

/* ─────────────────────────────── Reporting ──────────────────────────────── */

if (!function_exists('whatsapp_credits_stats')) {
    /**
     * Conversation ledger summary for the Omni Messaging → Official WhatsApp
     * panel.
     *
     * @param  int $days window for the "period" figures
     * @return array
     */
    function whatsapp_credits_stats($days = 30)
    {
        $empty = [
            'open_now'          => 0,
            'opened_today'      => 0,
            'credits_today'     => 0,
            'credits_period'    => 0,
            'messages_period'   => 0,
            'conversations'     => 0,
            'by_category'       => [],
            'saved_messages'    => 0,
            'days'              => (int) $days,
        ];

        if (!whatsapp_credits_ensure_schema()) {
            return $empty;
        }

        $CI    = &get_instance();
        $table = whatsapp_conversations_table();
        $since = date('Y-m-d H:i:s', strtotime('-' . max(1, (int) $days) . ' days'));

        $row = $CI->db->select(
            "COUNT(*) AS conversations,
             SUM(CASE WHEN expires_at > NOW() THEN 1 ELSE 0 END) AS open_now,
             SUM(CASE WHEN DATE(opened_at) = CURDATE() THEN 1 ELSE 0 END) AS opened_today,
             SUM(CASE WHEN DATE(opened_at) = CURDATE() THEN credits_charged ELSE 0 END) AS credits_today,
             SUM(credits_charged) AS credits_period,
             SUM(messages_count) AS messages_period",
            false
        )->where('opened_at >=', $since)->get($table)->row();

        if (!$row) {
            return $empty;
        }

        $stats = [
            'open_now'        => (int) $row->open_now,
            'opened_today'    => (int) $row->opened_today,
            'credits_today'   => (int) $row->credits_today,
            'credits_period'  => (int) $row->credits_period,
            'messages_period' => (int) $row->messages_period,
            'conversations'   => (int) $row->conversations,
            'by_category'     => [],
            // Messages that rode inside an already-open window — i.e. what
            // per-message billing would have charged and this engine did not.
            'saved_messages'  => max(0, (int) $row->messages_period - (int) $row->credits_period),
            'days'            => (int) $days,
        ];

        $by = $CI->db->select('category, COUNT(*) AS conversations, SUM(credits_charged) AS credits, SUM(messages_count) AS messages', false)
            ->where('opened_at >=', $since)
            ->group_by('category')
            ->get($table)->result();

        foreach ($by as $b) {
            $stats['by_category'][] = [
                'category'      => $b->category,
                'label'         => whatsapp_conversation_category_label($b->category),
                'conversations' => (int) $b->conversations,
                'credits'       => (int) $b->credits,
                'messages'      => (int) $b->messages,
            ];
        }

        return $stats;
    }
}

if (!function_exists('whatsapp_credits_recent')) {
    /** Most recent conversation windows, newest first. */
    function whatsapp_credits_recent($limit = 20)
    {
        if (!whatsapp_credits_ensure_schema()) {
            return [];
        }

        $CI = &get_instance();

        return $CI->db->order_by('opened_at', 'DESC')->limit((int) $limit)
            ->get(whatsapp_conversations_table())->result();
    }
}
