<?php

defined('BASEPATH') or exit('No direct script access allowed');

if (!$CI->db->table_exists(db_prefix() . 'sms_wa_email')) {
  $CI->db->query('CREATE TABLE `' . db_prefix() . 'sms_wa_email` (
      `id` int(11) NOT NULL,
      `name` varchar(150) NOT NULL,
      `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ';');

  $CI->db->query('ALTER TABLE `' . db_prefix() . 'sms_wa_email`
      ADD PRIMARY KEY (`id`);');

  $CI->db->query('ALTER TABLE `' . db_prefix() . 'sms_wa_email`
      MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;');
}

if (!$CI->db->table_exists(db_prefix() . 'sms_wa_email_templates')) {
  $CI->db->query('CREATE TABLE `' . db_prefix() . 'sms_wa_email_templates` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `type` varchar(50) NOT NULL COMMENT "sms, whatsapp, email",
      `title` varchar(255) NOT NULL,
      `content` text DEFAULT NULL,
      `is_attachment` tinyint(1) NOT NULL DEFAULT 0,
      `active` tinyint(1) NOT NULL DEFAULT 1,
      `is_default` tinyint(1) NOT NULL DEFAULT 0,
      `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `type` (`type`),
      KEY `active` (`active`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ';');
}

if (!$CI->db->table_exists(db_prefix() . 'sms_wa_email_company_limits')) {
  $CI->db->query('CREATE TABLE `' . db_prefix() . 'sms_wa_email_company_limits` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `client_id` int(11) NOT NULL,
      `sms_limit` int(11) NOT NULL DEFAULT 0,
      `sms_count` int(11) NOT NULL DEFAULT 0,
      `wa_limit` int(11) NOT NULL DEFAULT 0,
      `wa_count` int(11) NOT NULL DEFAULT 0,
      `email_limit` int(11) NOT NULL DEFAULT 0,
      `email_count` int(11) NOT NULL DEFAULT 0,
      `ai_call_limit` int(11) NOT NULL DEFAULT 0,
      `ai_call_count` int(11) NOT NULL DEFAULT 0,
      `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `client_id` (`client_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ';');
}

// Migration: add message_subtype column to templates if missing
if ($CI->db->table_exists(db_prefix() . 'sms_wa_email_templates')) {
  if (!$CI->db->field_exists('message_subtype', db_prefix() . 'sms_wa_email_templates')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'sms_wa_email_templates` ADD `message_subtype` varchar(20) NOT NULL DEFAULT "transactional" COMMENT "promotional, transactional" AFTER `type`;');
  }
  if (!$CI->db->field_exists('msg_template_id', db_prefix() . 'sms_wa_email_templates')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'sms_wa_email_templates` ADD `msg_template_id` varchar(100) DEFAULT NULL COMMENT "DLT / provider template ID" AFTER `content`;');
  }
  if (!$CI->db->field_exists('sample_content', db_prefix() . 'sms_wa_email_templates')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'sms_wa_email_templates` ADD `sample_content` text DEFAULT NULL COMMENT "Sample message content for reference" AFTER `msg_template_id`;');
  }
  if (!$CI->db->field_exists('header_id', db_prefix() . 'sms_wa_email_templates')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'sms_wa_email_templates` ADD `header_id` varchar(100) DEFAULT NULL COMMENT "Header ID for the template" AFTER `msg_template_id`;');
  }
  if (!$CI->db->field_exists('subject', db_prefix() . 'sms_wa_email_templates')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'sms_wa_email_templates` ADD `subject` varchar(255) DEFAULT NULL COMMENT "Email subject line" AFTER `content`;');
  }
  if (!$CI->db->field_exists('from_name', db_prefix() . 'sms_wa_email_templates')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'sms_wa_email_templates` ADD `from_name` varchar(255) DEFAULT NULL COMMENT "Email sender display name" AFTER `subject`;');
  }
  // AI Call Agent fields
  if (!$CI->db->field_exists('voice_type', db_prefix() . 'sms_wa_email_templates')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'sms_wa_email_templates` ADD `voice_type` varchar(20) DEFAULT NULL COMMENT "tts or voice_note" AFTER `from_name`;');
  }
  if (!$CI->db->field_exists('voice_note_id', db_prefix() . 'sms_wa_email_templates')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'sms_wa_email_templates` ADD `voice_note_id` varchar(100) DEFAULT NULL COMMENT "External voice note identifier" AFTER `voice_type`;');
  }
  if (!$CI->db->field_exists('voice_note_file', db_prefix() . 'sms_wa_email_templates')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'sms_wa_email_templates` ADD `voice_note_file` varchar(255) DEFAULT NULL COMMENT "Uploaded voice note audio file path" AFTER `voice_note_id`;');
  }
  if (!$CI->db->field_exists('voice_type_id', db_prefix() . 'sms_wa_email_templates')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'sms_wa_email_templates` ADD `voice_type_id` varchar(100) DEFAULT NULL COMMENT "Voice type identifier" AFTER `voice_note_file`;');
  }
  if (!$CI->db->field_exists('retry_count', db_prefix() . 'sms_wa_email_templates')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'sms_wa_email_templates` ADD `retry_count` int(3) DEFAULT 0 COMMENT "Number of retry attempts" AFTER `voice_type_id`;');
  }
  if (!$CI->db->field_exists('retry_interval', db_prefix() . 'sms_wa_email_templates')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'sms_wa_email_templates` ADD `retry_interval` int(5) DEFAULT 0 COMMENT "Seconds between retries" AFTER `retry_count`;');
  }
  if (!$CI->db->field_exists('language', db_prefix() . 'sms_wa_email_templates')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'sms_wa_email_templates` ADD `language` varchar(30) DEFAULT NULL COMMENT "Language for AI call: english, hindi, telugu, marathi, hinglish" AFTER `retry_interval`;');
  }
  // Official WhatsApp Cloud API mapping — used when the WhatsApp module is
  // connected, so this template is delivered as the tenant's own approved
  // Meta template instead of through a third-party gateway.
  if (!$CI->db->field_exists('wa_template_name', db_prefix() . 'sms_wa_email_templates')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'sms_wa_email_templates` ADD `wa_template_name` varchar(191) DEFAULT NULL COMMENT "Approved WhatsApp Cloud API template name" AFTER `language`;');
  }
  if (!$CI->db->field_exists('wa_template_language', db_prefix() . 'sms_wa_email_templates')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'sms_wa_email_templates` ADD `wa_template_language` varchar(20) DEFAULT NULL COMMENT "Approved template language code, e.g. en" AFTER `wa_template_name`;');
  }
  if (!$CI->db->field_exists('wa_params', db_prefix() . 'sms_wa_email_templates')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'sms_wa_email_templates` ADD `wa_params` varchar(500) DEFAULT NULL COMMENT "Comma-separated tags mapped to {{1}}..{{n}}" AFTER `wa_template_language`;');
  }
  // Rows created and maintained by the WhatsApp module's auto-map engine.
  // Editing such a template in the modal clears the flag, so the engine stops
  // refreshing it from Meta.
  if (!$CI->db->field_exists('wa_auto_synced', db_prefix() . 'sms_wa_email_templates')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'sms_wa_email_templates` ADD `wa_auto_synced` tinyint(1) NOT NULL DEFAULT 0 COMMENT "1 = created/maintained by the Cloud API auto-map engine" AFTER `wa_params`;');
  }
  if (!$CI->db->field_exists('wa_synced_at', db_prefix() . 'sms_wa_email_templates')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'sms_wa_email_templates` ADD `wa_synced_at` datetime DEFAULT NULL COMMENT "Last time the auto-map engine touched this row" AFTER `wa_auto_synced`;');
  }
}

if (!$CI->db->table_exists(db_prefix() . 'ccx_hook_template_map')) {
  $CI->db->query('CREATE TABLE `' . db_prefix() . 'ccx_hook_template_map` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `hook_key` varchar(100) NOT NULL,
      `channel` varchar(50) NOT NULL COMMENT "sms, whatsapp, email, ai_call_agent",
      `template_id` int(11) NOT NULL,
      `active` tinyint(1) NOT NULL DEFAULT 1,
      `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `hook_channel` (`hook_key`, `channel`),
      KEY `hook_key` (`hook_key`),
      KEY `channel` (`channel`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ';');
}

// Migration: add recipient_type and recipient_value columns to hook template map
if ($CI->db->table_exists(db_prefix() . 'ccx_hook_template_map')) {
  if (!$CI->db->field_exists('recipient_type', db_prefix() . 'ccx_hook_template_map')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'ccx_hook_template_map` ADD `recipient_type` varchar(20) NOT NULL DEFAULT "patient" COMMENT "patient, staff, role" AFTER `template_id`;');
  }
  if (!$CI->db->field_exists('recipient_value', db_prefix() . 'ccx_hook_template_map')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'ccx_hook_template_map` ADD `recipient_value` varchar(100) DEFAULT NULL COMMENT "staff_id or role_id depending on recipient_type" AFTER `recipient_type`;');
  }
  // Drop old unique key (hook_key, channel) — same hook+channel can now have multiple recipients
  $indexes = $CI->db->query('SHOW INDEX FROM `' . db_prefix() . 'ccx_hook_template_map` WHERE Key_name = "hook_channel"')->result();
  if (!empty($indexes)) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'ccx_hook_template_map` DROP INDEX `hook_channel`;');
  }
}

if (!$CI->db->table_exists(db_prefix() . 'ccx_hook_trigger_logs')) {
  $CI->db->query('CREATE TABLE `' . db_prefix() . 'ccx_hook_trigger_logs` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `hook_key` varchar(100) NOT NULL,
      `channel` varchar(50) NOT NULL,
      `send_type` varchar(30) NOT NULL DEFAULT "hook" COMMENT "hook, campaign, auto_scheduler",
      `template_id` int(11) DEFAULT NULL,
      `recipient` varchar(255) DEFAULT NULL,
      `message_preview` text DEFAULT NULL,
      `status` varchar(30) NOT NULL DEFAULT "pending" COMMENT "success, failed, insufficient_balance, expired, no_template",
      `error_message` text DEFAULT NULL,
      `staff_id` int(11) DEFAULT NULL,
      `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `hook_key` (`hook_key`),
      KEY `channel` (`channel`),
      KEY `send_type` (`send_type`),
      KEY `status` (`status`),
      KEY `created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ';');
}

// Add send_type column if table exists but column doesn't (upgrade path)
if ($CI->db->table_exists(db_prefix() . 'ccx_hook_trigger_logs')) {
  if (!$CI->db->field_exists('send_type', db_prefix() . 'ccx_hook_trigger_logs')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'ccx_hook_trigger_logs` ADD COLUMN `send_type` varchar(30) NOT NULL DEFAULT "hook" COMMENT "hook, campaign, auto_scheduler" AFTER `channel`');
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'ccx_hook_trigger_logs` ADD KEY `send_type` (`send_type`)');
  }

  // Upgrade path: widen error_message (was varchar(500)) so full SMTP logs fit
  $err_col = $CI->db->query('SHOW COLUMNS FROM `' . db_prefix() . 'ccx_hook_trigger_logs` LIKE "error_message"')->row();
  if ($err_col && stripos($err_col->Type, 'varchar') !== false) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'ccx_hook_trigger_logs` MODIFY `error_message` TEXT NULL');
  }
}

if (!$CI->db->table_exists(db_prefix() . 'sms_wa_email_campaigns')) {
  $CI->db->query('CREATE TABLE `' . db_prefix() . 'sms_wa_email_campaigns` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `name` varchar(255) NOT NULL,
      `channel` varchar(50) NOT NULL COMMENT "sms, official_whatsapp, email",
      `template_id` int(11) NOT NULL,
      `schedule_date` datetime NOT NULL,
      `status` varchar(20) NOT NULL DEFAULT "pending" COMMENT "pending, processing, completed, cancelled, failed",
      `filters_json` text DEFAULT NULL COMMENT "JSON representation of patient filters used",
      `total_targets` int(11) NOT NULL DEFAULT 0,
      `processed_count` int(11) NOT NULL DEFAULT 0,
      `success_count` int(11) NOT NULL DEFAULT 0,
      `failed_count` int(11) NOT NULL DEFAULT 0,
      `created_by` int(11) NOT NULL,
      `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `status` (`status`),
      KEY `schedule_date` (`schedule_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ';');
}

if (!$CI->db->table_exists(db_prefix() . 'sms_wa_email_campaign_logs')) {
  $CI->db->query('CREATE TABLE `' . db_prefix() . 'sms_wa_email_campaign_logs` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `campaign_id` int(11) NOT NULL,
      `patient_id` int(11) NOT NULL,
      `recipient` varchar(255) DEFAULT NULL,
      `status` varchar(30) NOT NULL DEFAULT "pending" COMMENT "success, failed",
      `error_message` varchar(500) DEFAULT NULL,
      `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `campaign_id` (`campaign_id`),
      KEY `patient_id` (`patient_id`),
      KEY `status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ';');
}

// ── Auto-Cycle Schedulers ──────────────────────────────────────────────
if (!$CI->db->table_exists(db_prefix() . 'sms_wa_email_auto_schedulers')) {
  $CI->db->query('CREATE TABLE `' . db_prefix() . 'sms_wa_email_auto_schedulers` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `name` varchar(255) NOT NULL,
      `channel` varchar(50) NOT NULL COMMENT "sms, official_whatsapp, email",
      `template_id` int(11) NOT NULL,
      `time_slots` text NOT NULL COMMENT "JSON array of HH:MM times e.g. [\"10:00\",\"13:00\"]",
      `filters_json` text DEFAULT NULL COMMENT "JSON patient filters (same as campaigns)",
      `is_active` tinyint(1) NOT NULL DEFAULT 1,
      `total_sent_today` int(11) NOT NULL DEFAULT 0,
      `last_run_at` datetime DEFAULT NULL,
      `last_run_date` date DEFAULT NULL COMMENT "Tracks the date of last daily reset",
      `created_by` int(11) NOT NULL,
      `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `is_active` (`is_active`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ';');
}

// ── CRM e-mail routing defaults ────────────────────────────────────────
// Activating the module IS the opt-in: from here on every e-mail the CRM
// sends is billed and logged on the Email channel. The switches live on the
// Email tab → CRM Routing (helpers/sms_wa_email_crm_email_helper.php).
//
// add_option() is a no-op once the row exists, and get_option() answers ''
// (never false) for a name that was never added — so the empty-string
// self-heal below is what actually repairs a half-written row.
foreach ([
  'sms_wa_email_route_crm_email'             => '1',
  'sms_wa_email_crm_email_use_api_smtp'      => '1',
  'sms_wa_email_crm_email_block_no_balance'  => '1',
  'sms_wa_email_crm_email_fallback_smtp'     => '1',
  'sms_wa_email_crm_email_reply_to_original' => '1',
] as $sms_wa_email_opt => $sms_wa_email_default) {
  add_option($sms_wa_email_opt, $sms_wa_email_default);
  if (get_option($sms_wa_email_opt) === '') {
    update_option($sms_wa_email_opt, $sms_wa_email_default);
  }
}

// Grandfather everyone who already had the old single `view` permission into
// the new feature-wise + channel-wise capabilities (see the module file).
if (function_exists('sms_wa_email_migrate_legacy_permissions')) {
  sms_wa_email_migrate_legacy_permissions();
}

// One-time: give every registered hook a default EMAIL template so the Hooks
// panel is usable out of the box. Guarded by its own option — an admin who
// deletes the seeded rows must not get them back on the next schema upgrade,
// and the "Load Hook Templates" button re-runs it on demand anyway.
// (get_option() returns '' — never false — for a name that was never added.)
if (get_option('sms_wa_email_hook_email_templates_seeded') !== '1') {
  // Activation runs install.php without the module bootstrap, so the seeder is
  // required here rather than assumed.
  $sms_wa_email_seeder = __DIR__ . '/helpers/sms_wa_email_email_seeder_helper.php';
  if (is_file($sms_wa_email_seeder)) {
    require_once($sms_wa_email_seeder);
  }
  if (function_exists('sms_wa_email_seed_hook_email_templates')) {
    sms_wa_email_seed_hook_email_templates();
    update_option('sms_wa_email_hook_email_templates_seeded', '1');
  }
}

if (!$CI->db->table_exists(db_prefix() . 'sms_wa_email_auto_scheduler_logs')) {
  $CI->db->query('CREATE TABLE `' . db_prefix() . 'sms_wa_email_auto_scheduler_logs` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `scheduler_id` int(11) NOT NULL,
      `patient_id` int(11) NOT NULL,
      `recipient` varchar(255) DEFAULT NULL,
      `time_slot` varchar(10) NOT NULL COMMENT "HH:MM of the cycle that sent this",
      `run_date` date NOT NULL COMMENT "Calendar date of this run",
      `status` varchar(30) NOT NULL DEFAULT "pending" COMMENT "success, failed",
      `error_message` varchar(500) DEFAULT NULL,
      `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `dedup_per_day` (`scheduler_id`, `patient_id`, `run_date`),
      KEY `scheduler_id` (`scheduler_id`),
      KEY `run_date` (`run_date`),
      KEY `status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ';');
}
