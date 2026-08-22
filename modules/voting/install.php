<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Voting module — database schema.
 * Live audience polls: one short code per poll, admin drives the active
 * question, audience votes from the same public link, results are live.
 */

$CI = &get_instance();
$p  = db_prefix();

// ── Polls ──
if (!$CI->db->table_exists($p . 'voting_polls')) {
    $CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}voting_polls` (
        `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `title` VARCHAR(255) NOT NULL,
        `description` TEXT DEFAULT NULL,
        `code` VARCHAR(12) NOT NULL COMMENT 'short public code, e.g. K7PM → /vote/K7PM',
        `status` ENUM('draft','live','ended') NOT NULL DEFAULT 'draft',
        `active_question_id` INT(11) UNSIGNED DEFAULT NULL COMMENT 'NULL while live = hold screen',
        `allow_revote` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'voter may change answer while question is active',
        `collect_names` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'ask voters for their name before voting',
        `created_by` INT(11) DEFAULT NULL,
        `updated_by` INT(11) DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `code` (`code`),
        KEY `status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
}

// ── Questions ──
if (!$CI->db->table_exists($p . 'voting_questions')) {
    $CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}voting_questions` (
        `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `poll_id` INT(11) UNSIGNED NOT NULL,
        `question` TEXT NOT NULL,
        `sort_order` INT(11) NOT NULL DEFAULT 0,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `poll_id` (`poll_id`),
        KEY `sort_order` (`sort_order`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
}

// ── Answer options ──
if (!$CI->db->table_exists($p . 'voting_options')) {
    $CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}voting_options` (
        `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `question_id` INT(11) UNSIGNED NOT NULL,
        `label` VARCHAR(500) NOT NULL,
        `sort_order` INT(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        KEY `question_id` (`question_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
}

// ── Votes (one row per voter per question; revote updates option_id) ──
if (!$CI->db->table_exists($p . 'voting_votes')) {
    $CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}voting_votes` (
        `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `poll_id` INT(11) UNSIGNED NOT NULL,
        `question_id` INT(11) UNSIGNED NOT NULL,
        `option_id` INT(11) UNSIGNED NOT NULL,
        `voter_token` VARCHAR(64) NOT NULL COMMENT 'anonymous device token generated client-side',
        `voter_name` VARCHAR(120) DEFAULT NULL COMMENT 'only filled when poll.collect_names is on',
        `ip_address` VARCHAR(45) DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_voter_question` (`question_id`, `voter_token`),
        KEY `poll_id` (`poll_id`),
        KEY `option_id` (`option_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
}

// ── Devices that opened the voter link (audience reach counter) ──
if (!$CI->db->table_exists($p . 'voting_devices')) {
    $CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}voting_devices` (
        `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `poll_id` INT(11) UNSIGNED NOT NULL,
        `voter_token` VARCHAR(64) NOT NULL,
        `first_seen` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `last_seen` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_poll_device` (`poll_id`, `voter_token`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
}

// ── Self-healing columns for upgrades (module activated before v1.1) ──
if (!$CI->db->field_exists('collect_names', $p . 'voting_polls')) {
    $CI->db->query("ALTER TABLE `{$p}voting_polls` ADD COLUMN `collect_names` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'ask voters for their name before voting' AFTER `allow_revote`");
}
if (!$CI->db->field_exists('voter_name', $p . 'voting_votes')) {
    $CI->db->query("ALTER TABLE `{$p}voting_votes` ADD COLUMN `voter_name` VARCHAR(120) DEFAULT NULL COMMENT 'only filled when poll.collect_names is on' AFTER `voter_token`");
}
