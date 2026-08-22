<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Forms (smart_forms) module — database schema.
 * Table names are prefixed smart_forms_* to avoid any clash with the
 * core Perfex Forms controller / web-to-lead tables.
 */

$CI = &get_instance();
$p  = db_prefix();

// ── Forms ──
if (!$CI->db->table_exists($p . 'smart_forms')) {
    $CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}smart_forms` (
        `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `title` VARCHAR(255) NOT NULL,
        `description` TEXT DEFAULT NULL,
        `category` VARCHAR(50) NOT NULL DEFAULT 'general' COMMENT 'onboarding | process | training | acknowledgement | general',
        `status` ENUM('draft','active','closed') NOT NULL DEFAULT 'draft',
        `share_token` VARCHAR(64) DEFAULT NULL,
        `require_identity` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'ask respondent name/email',
        `one_per_user` TINYINT(1) NOT NULL DEFAULT 0,
        `show_progress` TINYINT(1) NOT NULL DEFAULT 1,
        `collect_feedback` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'ask quick feedback after submit',
        `feedback_prompt` VARCHAR(255) DEFAULT NULL,
        `success_title` VARCHAR(255) DEFAULT NULL,
        `success_message` TEXT DEFAULT NULL,
        `redirect_url` VARCHAR(500) DEFAULT NULL,
        `max_submissions` INT(11) DEFAULT NULL COMMENT 'NULL = unlimited',
        `starts_at` DATETIME DEFAULT NULL,
        `expires_at` DATETIME DEFAULT NULL,
        `created_by` INT(11) DEFAULT NULL,
        `updated_by` INT(11) DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `share_token` (`share_token`),
        KEY `status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
}

// ── Questions ──
if (!$CI->db->table_exists($p . 'smart_forms_questions')) {
    $CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}smart_forms_questions` (
        `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `form_id` INT(11) UNSIGNED NOT NULL,
        `question_type` VARCHAR(30) NOT NULL DEFAULT 'short_text' COMMENT 'section, short_text, long_text, number, date, yes_no, true_false, terms, rating, scale, radio, checkbox, dropdown',
        `label` TEXT NOT NULL,
        `description` TEXT DEFAULT NULL,
        `options` LONGTEXT DEFAULT NULL COMMENT 'JSON array for radio/checkbox/dropdown',
        `settings` LONGTEXT DEFAULT NULL COMMENT 'JSON: max_stars, terms_text, must_accept, min_label, max_label, ...',
        `is_required` TINYINT(1) NOT NULL DEFAULT 0,
        `sort_order` INT(11) NOT NULL DEFAULT 0,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `form_id` (`form_id`),
        KEY `sort_order` (`sort_order`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
}

// ── Submissions (one row per completed fill; quick feedback lives here too) ──
if (!$CI->db->table_exists($p . 'smart_forms_submissions')) {
    $CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}smart_forms_submissions` (
        `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `form_id` INT(11) UNSIGNED NOT NULL,
        `respondent_name` VARCHAR(255) DEFAULT NULL,
        `respondent_email` VARCHAR(255) DEFAULT NULL,
        `staff_id` INT(11) DEFAULT NULL,
        `ip_address` VARCHAR(45) DEFAULT NULL,
        `user_agent` VARCHAR(500) DEFAULT NULL,
        `duration_seconds` INT(11) DEFAULT NULL,
        `feedback_rating` TINYINT(1) DEFAULT NULL COMMENT 'post-submit quick feedback 1-5',
        `feedback_comment` VARCHAR(500) DEFAULT NULL,
        `feedback_at` DATETIME DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `form_id` (`form_id`),
        KEY `created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
}

// ── Assignments (form sent to a specific patient / customer / anonymous draft) ──
if (!$CI->db->table_exists($p . 'smart_forms_assignments')) {
    $CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}smart_forms_assignments` (
        `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `form_id` INT(11) UNSIGNED NOT NULL,
        `target_type` VARCHAR(20) NOT NULL DEFAULT 'anonymous' COMMENT 'patient | contact | anonymous',
        `target_id` INT(11) DEFAULT NULL COMMENT 'patient: tblclients.userid, contact: tblcontacts.id',
        `name` VARCHAR(255) DEFAULT NULL,
        `email` VARCHAR(255) DEFAULT NULL,
        `phone` VARCHAR(50) DEFAULT NULL,
        `token` VARCHAR(64) NOT NULL COMMENT 'personal access token appended as ?a=',
        `due_date` DATETIME DEFAULT NULL,
        `status` VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending | in_progress | completed',
        `draft_data` LONGTEXT DEFAULT NULL COMMENT 'JSON of saved answers',
        `draft_progress` TINYINT(3) NOT NULL DEFAULT 0,
        `draft_saved_at` DATETIME DEFAULT NULL,
        `submission_id` INT(11) UNSIGNED DEFAULT NULL,
        `started_at` DATETIME DEFAULT NULL,
        `completed_at` DATETIME DEFAULT NULL,
        `created_by` INT(11) DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `token` (`token`),
        KEY `form_id` (`form_id`),
        KEY `status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
}

// ── Self-healing columns for upgrades ──
if (!$CI->db->field_exists('layout', $p . 'smart_forms')) {
    $CI->db->query("ALTER TABLE `{$p}smart_forms` ADD COLUMN `layout` VARCHAR(10) NOT NULL DEFAULT 'single' COMMENT 'single | steps (each section header starts a wizard step)' AFTER `show_progress`");
}

// ── Chat system removed (v2.1): drop leftovers from older installs ──
if ($CI->db->table_exists($p . 'smart_forms_messages')) {
    $CI->db->query("DROP TABLE `{$p}smart_forms_messages`");
}
if ($CI->db->field_exists('enable_chat', $p . 'smart_forms')) {
    $CI->db->query("ALTER TABLE `{$p}smart_forms` DROP COLUMN `enable_chat`");
}

// ── Answers ──
if (!$CI->db->table_exists($p . 'smart_forms_answers')) {
    $CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}smart_forms_answers` (
        `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `submission_id` INT(11) UNSIGNED NOT NULL,
        `question_id` INT(11) UNSIGNED NOT NULL,
        `answer_value` TEXT DEFAULT NULL,
        `answer_numeric` DECIMAL(10,2) DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `submission_id` (`submission_id`),
        KEY `question_id` (`question_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
}
