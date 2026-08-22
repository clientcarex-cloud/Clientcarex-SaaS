<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * WhatsApp (Official Cloud API) — schema + default options.
 *
 * MASTER registry  → whatsapp_connections / whatsapp_numbers
 *                    (tenant ↔ WABA/token, phone_number_id → tenant routing)
 * TENANT local     → whatsapp_api_* tables (messages, contacts, templates,
 *                    campaigns, recipients, bot rules)
 *
 * Idempotent: every statement is guarded, so the model's _ensure_schema()
 * may re-run this file at any time to self-heal.
 */

$CI = &get_instance();
$p  = db_prefix();

require_once(__DIR__ . '/helpers/whatsapp_helper.php');

/**
 * Schema probes that ask the server, never CodeIgniter's cache.
 *
 * CI caches list_tables() / list_fields() in $db->data_cache for the whole
 * request, and table_exists() / field_exists() read that cache. The first
 * probe in this file therefore froze a picture of the schema taken BEFORE any
 * CREATE TABLE below had run — so every table created here still looked
 * absent to the $wapi_add_column() calls that follow it, and each of those
 * ALTERs was silently skipped. That is how a fresh install ended up with the
 * base CREATE TABLE columns only and none of the later ones (is_registered,
 * credit_status, source, …), while the schema stamp at the bottom still
 * recorded the run as complete so it never self-healed.
 */
$wapi_table_exists = function ($table) use ($CI, $p) {
    return (bool) $CI->db->query(
        'SELECT 1 FROM information_schema.TABLES
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1',
        [$p . $table]
    )->num_rows();
};

$wapi_field_exists = function ($column, $table) use ($CI, $p) {
    return (bool) $CI->db->query(
        'SELECT 1 FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1',
        [$p . $table, $column]
    )->num_rows();
};

/**
 * Add a column only when it is missing — lets this file double as the upgrade
 * path for installs created before the column existed.
 */
$wapi_add_column = function ($table, $column, $ddl) use ($CI, $p, $wapi_table_exists, $wapi_field_exists) {
    if ($wapi_table_exists($table) && !$wapi_field_exists($column, $table)) {
        $CI->db->query("ALTER TABLE `{$p}{$table}` ADD COLUMN {$ddl}");
    }
};

/* ───────────────────── MASTER registry (provider) ───────────────────── */

if (function_exists('whatsapp_is_master') ? whatsapp_is_master() : true) {

    if (!$wapi_table_exists('whatsapp_connections')) {
        $CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}whatsapp_connections` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `tenant_slug` VARCHAR(191) NOT NULL,
            `tenant_base_url` VARCHAR(500) DEFAULT NULL,
            `business_id` VARCHAR(64) DEFAULT NULL,
            `waba_id` VARCHAR(64) DEFAULT NULL,
            `waba_name` VARCHAR(191) DEFAULT NULL,
            `fb_user_id` VARCHAR(64) DEFAULT NULL,
            `fb_user_name` VARCHAR(191) DEFAULT NULL,
            `access_token` TEXT DEFAULT NULL,
            `token_expires` DATETIME DEFAULT NULL,
            `status` VARCHAR(20) NOT NULL DEFAULT 'active',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `tenant_slug` (`tenant_slug`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    }

    if (!$wapi_table_exists('whatsapp_numbers')) {
        $CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}whatsapp_numbers` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `phone_number_id` VARCHAR(64) NOT NULL,
            `tenant_slug` VARCHAR(191) NOT NULL,
            `waba_id` VARCHAR(64) DEFAULT NULL,
            `display_phone_number` VARCHAR(30) DEFAULT NULL,
            `verified_name` VARCHAR(191) DEFAULT NULL,
            `quality_rating` VARCHAR(20) DEFAULT NULL,
            `is_default` TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `phone_number_id` (`phone_number_id`),
            KEY `tenant_slug` (`tenant_slug`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    }

    // Live Cloud API health for each number (v1.1). Kept in the master registry
    // next to the routing data so tenants read it cross-database for free.
    $wapi_add_column('whatsapp_numbers', 'status', "`status` VARCHAR(30) DEFAULT NULL AFTER `quality_rating`");
    $wapi_add_column('whatsapp_numbers', 'code_verification_status', "`code_verification_status` VARCHAR(30) DEFAULT NULL AFTER `status`");
    $wapi_add_column('whatsapp_numbers', 'name_status', "`name_status` VARCHAR(30) DEFAULT NULL AFTER `code_verification_status`");
    $wapi_add_column('whatsapp_numbers', 'platform_type', "`platform_type` VARCHAR(30) DEFAULT NULL AFTER `name_status`");
    $wapi_add_column('whatsapp_numbers', 'throughput_level', "`throughput_level` VARCHAR(30) DEFAULT NULL AFTER `platform_type`");
    $wapi_add_column('whatsapp_numbers', 'messaging_limit_tier', "`messaging_limit_tier` VARCHAR(40) DEFAULT NULL AFTER `throughput_level`");
    $wapi_add_column('whatsapp_numbers', 'health_can_send', "`health_can_send` VARCHAR(20) DEFAULT NULL AFTER `messaging_limit_tier`");
    $wapi_add_column('whatsapp_numbers', 'health_detail', "`health_detail` VARCHAR(500) DEFAULT NULL AFTER `health_can_send`");
    $wapi_add_column('whatsapp_numbers', 'is_registered', "`is_registered` TINYINT(1) NOT NULL DEFAULT 0 AFTER `health_detail`");
    $wapi_add_column('whatsapp_numbers', 'last_error', "`last_error` VARCHAR(500) DEFAULT NULL AFTER `is_registered`");
    $wapi_add_column('whatsapp_numbers', 'last_checked_at', "`last_checked_at` DATETIME DEFAULT NULL AFTER `last_error`");

    $wapi_add_column('whatsapp_connections', 'last_checked_at', "`last_checked_at` DATETIME DEFAULT NULL AFTER `status`");
    $wapi_add_column('whatsapp_connections', 'webhook_subscribed', "`webhook_subscribed` TINYINT(1) NOT NULL DEFAULT 0 AFTER `last_checked_at`");

    // Shared credit line (v1.2) — when the provider pays Meta on the tenant's
    // behalf, the tenant is never asked for a card during signup.
    $wapi_add_column('whatsapp_connections', 'credit_status', "`credit_status` VARCHAR(20) NOT NULL DEFAULT 'self' AFTER `webhook_subscribed`");
    $wapi_add_column('whatsapp_connections', 'credit_line_id', "`credit_line_id` VARCHAR(64) DEFAULT NULL AFTER `credit_status`");
    $wapi_add_column('whatsapp_connections', 'allocation_config_id', "`allocation_config_id` VARCHAR(64) DEFAULT NULL AFTER `credit_line_id`");
    $wapi_add_column('whatsapp_connections', 'credit_error', "`credit_error` VARCHAR(500) DEFAULT NULL AFTER `allocation_config_id`");
    $wapi_add_column('whatsapp_connections', 'credit_shared_at', "`credit_shared_at` DATETIME DEFAULT NULL AFTER `credit_error`");

    // Cached Meta usage/cost per tenant (v1.4) — a Graph call per tenant is far
    // too slow to run inline while rendering the console.
    $wapi_add_column('whatsapp_connections', 'usage_messages', "`usage_messages` INT(11) DEFAULT NULL AFTER `credit_shared_at`");
    $wapi_add_column('whatsapp_connections', 'usage_cost', "`usage_cost` DECIMAL(14,4) DEFAULT NULL AFTER `usage_messages`");
    $wapi_add_column('whatsapp_connections', 'usage_currency', "`usage_currency` VARCHAR(10) DEFAULT NULL AFTER `usage_cost`");
    $wapi_add_column('whatsapp_connections', 'usage_days', "`usage_days` INT(11) DEFAULT NULL AFTER `usage_currency`");
    $wapi_add_column('whatsapp_connections', 'usage_source', "`usage_source` VARCHAR(20) DEFAULT NULL AFTER `usage_days`");
    $wapi_add_column('whatsapp_connections', 'usage_error', "`usage_error` VARCHAR(300) DEFAULT NULL AFTER `usage_source`");
    $wapi_add_column('whatsapp_connections', 'usage_synced_at', "`usage_synced_at` DATETIME DEFAULT NULL AFTER `usage_error`");

    // Central-app resync (v2.1) — which Meta App issued the stored token, and
    // why the last resync judged the connection unusable. A token minted by a
    // previous Meta App keeps looking valid in the registry long after the
    // provider swapped the app, so the audit result is recorded per tenant.
    $wapi_add_column('whatsapp_connections', 'token_app_id', "`token_app_id` VARCHAR(64) DEFAULT NULL AFTER `token_expires`");
    $wapi_add_column('whatsapp_connections', 'sync_error', "`sync_error` VARCHAR(500) DEFAULT NULL AFTER `usage_synced_at`");
    $wapi_add_column('whatsapp_connections', 'synced_at', "`synced_at` DATETIME DEFAULT NULL AFTER `sync_error`");

    /* ───────── Shared number ("our WhatsApp") grants — v2.0 ───────── */

    /**
     * One row per tenant the provider lends its OWN WhatsApp number to.
     *
     * The tenant never connects a WABA and never sees a token: it simply sends
     * through a provider-owned phone_number_id, restricted to an approved
     * template allowlist and metered here.
     */
    if (!$wapi_table_exists('whatsapp_shared_grants')) {
        $CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}whatsapp_shared_grants` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `tenant_slug` VARCHAR(191) NOT NULL,
            `client_id` INT(11) DEFAULT NULL,
            `tenant_name` VARCHAR(191) DEFAULT NULL,
            `enabled` TINYINT(1) NOT NULL DEFAULT 0,
            `phone_number_id` VARCHAR(64) DEFAULT NULL,
            `billing_mode` VARCHAR(10) NOT NULL DEFAULT 'credits',
            `daily_limit` INT(11) NOT NULL DEFAULT 0,
            `monthly_limit` INT(11) NOT NULL DEFAULT 0,
            `allow_send` TINYINT(1) NOT NULL DEFAULT 1,
            `allow_bulk` TINYINT(1) NOT NULL DEFAULT 1,
            `allow_hooks` TINYINT(1) NOT NULL DEFAULT 1,
            `template_mode` VARCHAR(10) NOT NULL DEFAULT 'selected',
            `notes` VARCHAR(500) DEFAULT NULL,
            `created_by` INT(11) DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `tenant_slug` (`tenant_slug`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    }

    // The approved templates a tenant may use. Only consulted when the grant's
    // template_mode is 'selected' — 'all' hands over the whole approved library.
    if (!$wapi_table_exists('whatsapp_shared_templates')) {
        $CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}whatsapp_shared_templates` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `tenant_slug` VARCHAR(191) NOT NULL,
            `template_name` VARCHAR(191) NOT NULL,
            `language` VARCHAR(20) NOT NULL DEFAULT 'en',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `grant_template` (`tenant_slug`,`template_name`,`language`),
            KEY `tenant_slug` (`tenant_slug`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    }

    // Daily counters per tenant. One row per tenant per day keeps the quota
    // check a single indexed read while staying auditable month by month.
    if (!$wapi_table_exists('whatsapp_shared_usage')) {
        $CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}whatsapp_shared_usage` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `tenant_slug` VARCHAR(191) NOT NULL,
            `usage_date` DATE NOT NULL,
            `messages` INT(11) NOT NULL DEFAULT 0,
            `conversations` INT(11) NOT NULL DEFAULT 0,
            `credits` INT(11) NOT NULL DEFAULT 0,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `tenant_day` (`tenant_slug`,`usage_date`),
            KEY `usage_date` (`usage_date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    }

    // Provider-side switches for the shared number. `whatsapp_shared_enabled`
    // is the global kill switch: turning it off suspends every grant at once
    // without editing them.
    add_option('whatsapp_shared_enabled', '0');
    add_option('whatsapp_shared_number', '');
    add_option('whatsapp_shared_brand', '');

    // Provider billing profile. The system user token is master-only and is
    // never handed to tenant context (see whatsapp_provider_billing()).
    add_option('whatsapp_business_id', '');
    add_option('whatsapp_system_user_token', '');
    add_option('whatsapp_credit_line_id', '');
    add_option('whatsapp_credit_currency', 'USD');
    add_option('whatsapp_share_credit_line', '0');

    // Central app credentials + fixed public URLs (captured in master context so
    // they include the custom admin prefix + correct host). add_option() is a
    // no-op when the option already exists. NOTE: core get_option() returns ''
    // (never false) for missing options — do not guard with `=== false`.
    add_option('whatsapp_app_id', '');
    add_option('whatsapp_app_secret', '');
    add_option('whatsapp_config_id', '');
    add_option('whatsapp_verify_token', bin2hex(random_bytes(16)));
    add_option('whatsapp_webhook_url', admin_url('whatsapp/webhook'));
    add_option('whatsapp_oauth_callback_url', admin_url('whatsapp/oauth_callback'));
    add_option('whatsapp_last_resync_at', '');
    add_option('whatsapp_last_resync_app_id', '');

    // Self-heal: installs created while a broken guard skipped the seed above
    // have the option row with an empty value — backfill it.
    if (get_option('whatsapp_verify_token') === '') {
        update_option('whatsapp_verify_token', bin2hex(random_bytes(16)));
    }
    if (get_option('whatsapp_webhook_url') === '') {
        update_option('whatsapp_webhook_url', admin_url('whatsapp/webhook'));
    }
    if (get_option('whatsapp_oauth_callback_url') === '') {
        update_option('whatsapp_oauth_callback_url', admin_url('whatsapp/oauth_callback'));
    }
}

/* ─────────────────────── TENANT tables (local) ──────────────────────── */
/* The master also gets these so the provider can use the module itself.  */

if (!$wapi_table_exists('whatsapp_api_messages')) {
    $CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}whatsapp_api_messages` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `phone_number_id` VARCHAR(64) DEFAULT NULL,
        `direction` ENUM('incoming','outgoing') NOT NULL,
        `phone` VARCHAR(30) NOT NULL,
        `contact_name` VARCHAR(191) DEFAULT NULL,
        `type` VARCHAR(20) NOT NULL DEFAULT 'text',
        `body` TEXT DEFAULT NULL,
        `template_name` VARCHAR(191) DEFAULT NULL,
        `media_id` VARCHAR(80) DEFAULT NULL,
        `media_mime` VARCHAR(100) DEFAULT NULL,
        `caption` TEXT DEFAULT NULL,
        `wamid` VARCHAR(128) DEFAULT NULL,
        `status` ENUM('queued','accepted','sent','delivered','read','failed','received') NOT NULL DEFAULT 'queued',
        `error_message` VARCHAR(500) DEFAULT NULL,
        `is_bot_reply` TINYINT(1) NOT NULL DEFAULT 0,
        `campaign_id` INT(11) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `wamid` (`wamid`),
        KEY `phone` (`phone`),
        KEY `campaign_id` (`campaign_id`),
        KEY `phone_number_id` (`phone_number_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
}

if (!$wapi_table_exists('whatsapp_api_contacts')) {
    $CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}whatsapp_api_contacts` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `phone` VARCHAR(30) NOT NULL,
        `name` VARCHAR(191) DEFAULT NULL,
        `profile_name` VARCHAR(191) DEFAULT NULL,
        `last_incoming_at` DATETIME DEFAULT NULL,
        `last_outgoing_at` DATETIME DEFAULT NULL,
        `unread_count` INT(11) NOT NULL DEFAULT 0,
        `opted_out` TINYINT(1) NOT NULL DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `phone` (`phone`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
}

if (!$wapi_table_exists('whatsapp_api_templates')) {
    $CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}whatsapp_api_templates` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `template_id` VARCHAR(64) DEFAULT NULL,
        `name` VARCHAR(191) NOT NULL,
        `language` VARCHAR(20) NOT NULL DEFAULT 'en',
        `category` VARCHAR(30) DEFAULT NULL,
        `status` VARCHAR(30) DEFAULT NULL,
        `rejected_reason` VARCHAR(60) DEFAULT NULL,
        `quality_score` VARCHAR(20) DEFAULT NULL,
        `components` LONGTEXT DEFAULT NULL,
        `body_text` TEXT DEFAULT NULL,
        `variables_count` INT(11) NOT NULL DEFAULT 0,
        `has_media_header` TINYINT(1) NOT NULL DEFAULT 0,
        `last_synced_at` DATETIME DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `name_language` (`name`,`language`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
}

if (!$wapi_table_exists('whatsapp_api_campaigns')) {
    $CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}whatsapp_api_campaigns` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(191) NOT NULL,
        `phone_number_id` VARCHAR(64) DEFAULT NULL,
        `template_name` VARCHAR(191) NOT NULL,
        `template_language` VARCHAR(20) NOT NULL DEFAULT 'en',
        `template_params` LONGTEXT DEFAULT NULL,
        `header_media_url` VARCHAR(500) DEFAULT NULL,
        `total_count` INT(11) NOT NULL DEFAULT 0,
        `sent_count` INT(11) NOT NULL DEFAULT 0,
        `delivered_count` INT(11) NOT NULL DEFAULT 0,
        `read_count` INT(11) NOT NULL DEFAULT 0,
        `failed_count` INT(11) NOT NULL DEFAULT 0,
        `status` ENUM('draft','scheduled','running','paused','completed','cancelled') NOT NULL DEFAULT 'draft',
        `scheduled_at` DATETIME DEFAULT NULL,
        `batch_size` INT(11) NOT NULL DEFAULT 20,
        `created_by` INT(11) DEFAULT NULL,
        `started_at` DATETIME DEFAULT NULL,
        `completed_at` DATETIME DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
}

if (!$wapi_table_exists('whatsapp_api_campaign_recipients')) {
    $CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}whatsapp_api_campaign_recipients` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `campaign_id` INT(11) NOT NULL,
        `phone` VARCHAR(30) NOT NULL,
        `name` VARCHAR(191) DEFAULT NULL,
        `params` LONGTEXT DEFAULT NULL,
        `wamid` VARCHAR(128) DEFAULT NULL,
        `status` ENUM('pending','sent','delivered','read','failed','skipped') NOT NULL DEFAULT 'pending',
        `error` VARCHAR(500) DEFAULT NULL,
        `sent_at` DATETIME DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `campaign_id` (`campaign_id`),
        KEY `wamid` (`wamid`),
        KEY `campaign_status` (`campaign_id`,`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
}

if (!$wapi_table_exists('whatsapp_api_bot_rules')) {
    $CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}whatsapp_api_bot_rules` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(191) NOT NULL,
        `trigger_type` ENUM('welcome','keyword','away','default') NOT NULL DEFAULT 'keyword',
        `match_type` ENUM('contains','exact','starts_with','any') NOT NULL DEFAULT 'contains',
        `keywords` TEXT DEFAULT NULL,
        `response_text` LONGTEXT DEFAULT NULL,
        `enabled` TINYINT(1) NOT NULL DEFAULT 1,
        `priority` INT(11) NOT NULL DEFAULT 0,
        `hits` INT(11) NOT NULL DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // Starter rules (disabled except welcome, so nothing fires unexpectedly).
    $CI->db->insert($p . 'whatsapp_api_bot_rules', [
        'name' => 'Welcome message', 'trigger_type' => 'welcome', 'match_type' => 'any',
        'response_text' => "Hello! 👋 Thanks for reaching out. How can we help you today?",
        'enabled' => 1, 'priority' => 100,
    ]);
    $CI->db->insert($p . 'whatsapp_api_bot_rules', [
        'name' => 'Working hours', 'trigger_type' => 'keyword', 'match_type' => 'contains',
        'keywords' => 'timing, hours, open, opening',
        'response_text' => 'Our working hours are 9:00 AM – 6:00 PM, Monday to Saturday.',
        'enabled' => 0, 'priority' => 50,
    ]);
    $CI->db->insert($p . 'whatsapp_api_bot_rules', [
        'name' => 'Away message', 'trigger_type' => 'away', 'match_type' => 'any',
        'response_text' => "We're currently away. We'll get back to you as soon as we're online again.",
        'enabled' => 0, 'priority' => 10,
    ]);
    $CI->db->insert($p . 'whatsapp_api_bot_rules', [
        'name' => 'Default reply', 'trigger_type' => 'default', 'match_type' => 'any',
        'response_text' => 'Thanks for your message! A member of our team will reply shortly.',
        'enabled' => 0, 'priority' => 0,
    ]);
}

// Cloud API error code behind a failed send (v1.1) — drives the fix hints.
$wapi_add_column('whatsapp_api_messages', 'error_code', "`error_code` VARCHAR(20) DEFAULT NULL AFTER `error_message`");

// Why Meta rejected a template + its quality score (v1.8) — both are pulled by
// sync_templates() and shown on the Templates tab so a rejection can be fixed
// and resubmitted instead of guessed at.
$wapi_add_column('whatsapp_api_templates', 'rejected_reason', "`rejected_reason` VARCHAR(60) DEFAULT NULL AFTER `status`");
$wapi_add_column('whatsapp_api_templates', 'quality_score', "`quality_score` VARCHAR(20) DEFAULT NULL AFTER `rejected_reason`");

/**
 * Where a cached template came from (v2.0).
 *
 * 'own'    — pulled from the tenant's own WABA by sync_templates().
 * 'shared' — mirrored from the PROVIDER's approved library because the tenant
 *            sends on the provider's number. These rows are read-only: the
 *            tenant does not own the WABA and may not edit or delete them.
 */
$wapi_add_column('whatsapp_api_templates', 'source', "`source` VARCHAR(10) NOT NULL DEFAULT 'own' AFTER `last_synced_at`");

/**
 * 24-hour conversation ledger (v1.6).
 *
 * Meta bills a CONVERSATION, not a message: once a window of a given category
 * is open with a contact, every further message inside it is free. One row =
 * one 24-hour window, so credits are charged once per window and the whole
 * thing stays auditable. See helpers/whatsapp_credits_helper.php.
 */
if (!$wapi_table_exists('whatsapp_api_conversations')) {
    $CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}whatsapp_api_conversations` (
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

/* ───────────────────────── Tenant options ───────────────────────────── */

add_option('whatsapp_default_country_code', '91');
add_option('whatsapp_bot_settings', json_encode([
    'enabled'        => 0,
    'business_hours' => 0,
    'open_time'      => '09:00',
    'close_time'     => '18:00',
    'days'           => [1, 2, 3, 4, 5, 6],
    'tz_offset'      => '+05:30',
]));
add_option('whatsapp_last_cron_run', '');
add_option('whatsapp_schema_version', '');

// Last pull of the provider's shared template library (throttle stamp).
add_option('whatsapp_shared_sync_at', '');

// Inbox arrival notifications (v1.7) — see helpers/whatsapp_notify_helper.php.
add_option('whatsapp_notify_settings', json_encode([
    'enabled'    => 1,
    'toast'      => 1,
    'desktop'    => 1,
    'sound'      => 1,
    'bell'       => 1,
    'recipients' => 'inbox',
    'staff'      => [],
    'throttle'   => 90,
]));
// Per-contact bell throttle stamps ({phone: unix_ts}).
add_option('whatsapp_notify_last', '');

// Webhook receipt tracking — proves whether Meta can actually reach us.
add_option('whatsapp_last_webhook_at', '');
add_option('whatsapp_last_webhook_rejected_at', '');
add_option('whatsapp_webhook_count', '0');

/* ────── Timezone correction for DB-clock timestamps (v1.7, one-time) ────── */

/**
 * Until v1.7 the message/campaign rows let MySQL fill `created_at` from its
 * CURRENT_TIMESTAMP default. MySQL runs on the DB server's timezone while
 * everything those values are rendered or compared against (time_ago, "today",
 * the activity chart) comes from PHP on the account timezone — so on a host
 * where the two differ, a message that had just arrived showed up hours old.
 *
 * New rows are stamped from PHP now. This shifts the existing ones by the
 * measured clock difference so history reads correctly too. Runs once.
 */
add_option('whatsapp_tz_corrected', '');

if ((string) get_option('whatsapp_tz_corrected') !== '1') {
    $wapi_db_now = $CI->db->query('SELECT NOW() AS n')->row();
    $wapi_offset = 0;

    if ($wapi_db_now && !empty($wapi_db_now->n)) {
        // Both strings are parsed in the PHP timezone, so the difference is the
        // pure clock gap between the two servers.
        $wapi_offset = strtotime(date('Y-m-d H:i:s')) - strtotime($wapi_db_now->n);
        // Round to whole minutes — a slow query must not look like a skew.
        $wapi_offset = (int) round($wapi_offset / 60) * 60;
    }

    if (abs($wapi_offset) >= 60) {
        foreach (['whatsapp_api_messages', 'whatsapp_api_campaigns', 'whatsapp_api_campaign_recipients', 'whatsapp_api_contacts'] as $wapi_tz_table) {
            if (!$wapi_table_exists($wapi_tz_table)) {
                continue;
            }
            // updated_at is shifted in the same statement, otherwise its
            // ON UPDATE CURRENT_TIMESTAMP would re-stamp it on DB time.
            $wapi_sets = ['`created_at` = DATE_ADD(`created_at`, INTERVAL ? SECOND)'];
            $wapi_bind = [$wapi_offset];
            if ($wapi_field_exists('updated_at', $wapi_tz_table)) {
                $wapi_sets[] = '`updated_at` = DATE_ADD(`updated_at`, INTERVAL ? SECOND)';
                $wapi_bind[] = $wapi_offset;
            }
            $CI->db->query(
                "UPDATE `{$p}{$wapi_tz_table}` SET " . implode(', ', $wapi_sets) . ' WHERE `created_at` IS NOT NULL',
                $wapi_bind
            );
        }
        log_activity('WhatsApp: shifted stored timestamps by ' . $wapi_offset . 's to match the account timezone.');
    }

    update_option('whatsapp_tz_corrected', '1');
}

/* ────────── Tab-wise permissions backfill (v1.5.0, one-time) ────────── */

/**
 * v1.5.0 split the five coarse capabilities into one per dashboard tab.
 * Without a backfill, staff who already had WhatsApp access would silently
 * lose the Inbox / Contacts / Templates / Profile tabs, so grant the new
 * capabilities to whoever effectively had them under the old model:
 *
 *   inbox, contacts  → anyone who held ANY whatsapp capability
 *   templates        → the old sync was open to all, create/delete needed settings
 *   profile          → was gated on settings
 */
add_option('whatsapp_perms_backfilled', '');

if ((string) get_option('whatsapp_perms_backfilled') !== '1') {
    $wapi_perm_table = $p . 'staff_permissions';

    if ($wapi_table_exists('staff_permissions')) {
        $wapi_existing = $CI->db->select('staff_id, capability')
            ->where('feature', 'whatsapp')
            ->get($wapi_perm_table)->result();

        $wapi_by_staff = [];
        foreach ($wapi_existing as $row) {
            $wapi_by_staff[(int) $row->staff_id][] = (string) $row->capability;
        }

        foreach ($wapi_by_staff as $wapi_staff_id => $wapi_caps) {
            $wapi_grant = ['inbox', 'contacts', 'templates'];
            if (in_array('settings', $wapi_caps, true)) {
                $wapi_grant[] = 'profile';
            }

            foreach (array_diff($wapi_grant, $wapi_caps) as $wapi_cap) {
                $CI->db->insert($wapi_perm_table, [
                    'staff_id'   => $wapi_staff_id,
                    'feature'    => 'whatsapp',
                    'capability' => $wapi_cap,
                ]);
            }
        }
    }

    update_option('whatsapp_perms_backfilled', '1');
}

// Stamp the schema so the model only re-runs this file after an upgrade.
update_option('whatsapp_schema_version', defined('WHATSAPP_SCHEMA_VERSION') ? WHATSAPP_SCHEMA_VERSION : '1.5.0');

/**
 * Drop CI's schema cache so the REST of this request sees what was just
 * created. Without it, code running later in the same page load (the shared
 * template mirror checks for the `source` column, the model checks its own
 * tables) keeps answering from the pre-install picture and quietly no-ops.
 */
$CI->db->data_cache = [];
