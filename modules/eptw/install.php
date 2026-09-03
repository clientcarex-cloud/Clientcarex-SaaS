<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * ePTW — schema, options and seed data.
 *
 * Safe to run repeatedly: it is the activation hook and it runs again by
 * itself whenever the module version moves (the self-heal in eptw.php).
 * Nothing here drops or rewrites existing rows.
 */

$CI = &get_instance();
$p  = db_prefix();

foreach (eptw_default_options() as $eptw_option => $eptw_value) {
    add_option($eptw_option, $eptw_value);
}

$CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}eptw_projects` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(20) NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `client_name` VARCHAR(150) NOT NULL DEFAULT '',
    `description` TEXT DEFAULT NULL,
    `camera_mode` VARCHAR(20) NOT NULL DEFAULT 'inherit',
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

$CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}eptw_areas` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `project_id` INT(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 = shared by all projects',
    `code` VARCHAR(20) NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    KEY `project_id` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

$CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}eptw_contractors` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(20) NOT NULL DEFAULT '',
    `name` VARCHAR(150) NOT NULL,
    `contact_name` VARCHAR(150) NOT NULL DEFAULT '',
    `phone` VARCHAR(40) NOT NULL DEFAULT '',
    `email` VARCHAR(150) NOT NULL DEFAULT '',
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

$CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}eptw_permit_types` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(10) NOT NULL,
    `name` VARCHAR(120) NOT NULL,
    `description` VARCHAR(255) NOT NULL DEFAULT '',
    `icon` VARCHAR(60) NOT NULL DEFAULT 'fa-solid fa-file-shield',
    `color` VARCHAR(10) NOT NULL DEFAULT '#2563eb',
    `high_risk` TINYINT(1) NOT NULL DEFAULT 0,
    `gas_test_required` TINYINT(1) NOT NULL DEFAULT 0,
    `isolation_required` TINYINT(1) NOT NULL DEFAULT 0,
    `default_validity_hours` INT(11) NOT NULL DEFAULT 12,
    `hazards` MEDIUMTEXT DEFAULT NULL,
    `controls` MEDIUMTEXT DEFAULT NULL,
    `extra_fields` MEDIUMTEXT DEFAULT NULL,
    `ppe` TEXT DEFAULT NULL,
    `approvals` VARCHAR(255) NOT NULL DEFAULT '',
    `keywords` TEXT DEFAULT NULL,
    `sort_order` INT(11) NOT NULL DEFAULT 0,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

$CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}eptw_team` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `staff_id` INT(11) NOT NULL,
    `role` VARCHAR(20) NOT NULL DEFAULT 'engineer',
    `project_ids` TEXT DEFAULT NULL COMMENT 'JSON list, empty = all projects',
    `contractor_id` INT(11) UNSIGNED NOT NULL DEFAULT 0,
    `phone` VARCHAR(40) NOT NULL DEFAULT '',
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `staff_id` (`staff_id`),
    KEY `role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

$CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}eptw_permits` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `permit_no` VARCHAR(60) DEFAULT NULL,
    `serial` INT(11) NOT NULL DEFAULT 0,
    `serial_key` VARCHAR(60) NOT NULL DEFAULT '',
    `source` VARCHAR(10) NOT NULL DEFAULT 'digital' COMMENT 'digital | paper | import',
    `project_id` INT(11) UNSIGNED NOT NULL DEFAULT 0,
    `area_id` INT(11) UNSIGNED NOT NULL DEFAULT 0,
    `permit_type_id` INT(11) UNSIGNED NOT NULL DEFAULT 0,
    `contractor_id` INT(11) UNSIGNED NOT NULL DEFAULT 0,
    `subcontractor` VARCHAR(150) NOT NULL DEFAULT '',
    `work_order` VARCHAR(60) NOT NULL DEFAULT '',
    `work_title` VARCHAR(191) NOT NULL DEFAULT '',
    `work_description` TEXT DEFAULT NULL,
    `location` VARCHAR(191) NOT NULL DEFAULT '',
    `equipment_tag` VARCHAR(120) NOT NULL DEFAULT '',
    `shift` VARCHAR(10) NOT NULL DEFAULT 'day',
    `workers_count` INT(11) NOT NULL DEFAULT 0,
    `start_at` DATETIME DEFAULT NULL,
    `end_at` DATETIME DEFAULT NULL,
    `original_end_at` DATETIME DEFAULT NULL,
    `engineer_id` INT(11) NOT NULL DEFAULT 0 COMMENT 'initiator / performing authority',
    `permit_holder` VARCHAR(150) NOT NULL DEFAULT '',
    `supervisor` VARCHAR(150) NOT NULL DEFAULT '',
    `contact_no` VARCHAR(40) NOT NULL DEFAULT '',
    `area_authority_id` INT(11) NOT NULL DEFAULT 0,
    `hse_officer_id` INT(11) NOT NULL DEFAULT 0,
    `coordinator_id` INT(11) NOT NULL DEFAULT 0 COMMENT 'permit issuer',
    `approver_id` INT(11) NOT NULL DEFAULT 0,
    `status` VARCHAR(25) NOT NULL DEFAULT 'draft',
    `risk_level` VARCHAR(10) NOT NULL DEFAULT 'low',
    `high_risk` TINYINT(1) NOT NULL DEFAULT 0,
    `simops_flag` TINYINT(1) NOT NULL DEFAULT 0,
    `simops_notes` TEXT DEFAULT NULL,
    `simops_approved_by` INT(11) NOT NULL DEFAULT 0,
    `extension_count` INT(11) NOT NULL DEFAULT 0,
    `hazards` MEDIUMTEXT DEFAULT NULL COMMENT 'JSON name => yes/no',
    `extra_hazards` TEXT DEFAULT NULL,
    `controls` MEDIUMTEXT DEFAULT NULL COMMENT 'JSON item => {v: yes|no|na, r: remark}',
    `extra` MEDIUMTEXT DEFAULT NULL COMMENT 'JSON type-specific fields',
    `ppe` TEXT DEFAULT NULL COMMENT 'JSON list',
    `toolbox` MEDIUMTEXT DEFAULT NULL COMMENT 'JSON attendees',
    `ra_ref` VARCHAR(80) NOT NULL DEFAULT '',
    `isolation_required` TINYINT(1) NOT NULL DEFAULT 0,
    `isolation_type` VARCHAR(80) NOT NULL DEFAULT '',
    `isolation_cert_no` VARCHAR(80) NOT NULL DEFAULT '',
    `loto_applied` TINYINT(1) NOT NULL DEFAULT 0,
    `zero_energy_verified` TINYINT(1) NOT NULL DEFAULT 0,
    `isolation_authority` VARCHAR(150) NOT NULL DEFAULT '',
    `lock_tag_numbers` VARCHAR(255) NOT NULL DEFAULT '',
    `gas_test_required` TINYINT(1) NOT NULL DEFAULT 0,
    `weather` VARCHAR(80) NOT NULL DEFAULT '',
    `remarks` TEXT DEFAULT NULL,
    `number_requested_at` DATETIME DEFAULT NULL,
    `issued_at` DATETIME DEFAULT NULL,
    `issued_by` INT(11) NOT NULL DEFAULT 0,
    `activated_at` DATETIME DEFAULT NULL,
    `suspended_at` DATETIME DEFAULT NULL,
    `suspend_reason` VARCHAR(255) NOT NULL DEFAULT '',
    `closed_at` DATETIME DEFAULT NULL,
    `closed_by` INT(11) NOT NULL DEFAULT 0,
    `closure` TEXT DEFAULT NULL COMMENT 'JSON closure checklist',
    `docs_complete` TINYINT(1) NOT NULL DEFAULT 0,
    `return_reason` VARCHAR(255) NOT NULL DEFAULT '',
    `cancel_reason` VARCHAR(255) NOT NULL DEFAULT '',
    `expiry_notified` TINYINT(1) NOT NULL DEFAULT 0,
    `created_by` INT(11) NOT NULL DEFAULT 0,
    `created_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `permit_no` (`permit_no`),
    KEY `status` (`status`),
    KEY `project_area` (`project_id`, `area_id`),
    KEY `permit_type_id` (`permit_type_id`),
    KEY `contractor_id` (`contractor_id`),
    KEY `engineer_id` (`engineer_id`),
    KEY `window` (`start_at`, `end_at`),
    KEY `serial_key` (`serial_key`, `serial`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

$CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}eptw_events` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `permit_id` INT(11) UNSIGNED NOT NULL,
    `staff_id` INT(11) NOT NULL DEFAULT 0,
    `event` VARCHAR(30) NOT NULL,
    `from_status` VARCHAR(25) NOT NULL DEFAULT '',
    `to_status` VARCHAR(25) NOT NULL DEFAULT '',
    `note` TEXT DEFAULT NULL,
    `data` TEXT DEFAULT NULL,
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    KEY `permit_id` (`permit_id`),
    KEY `event` (`event`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

$CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}eptw_approvals` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `permit_id` INT(11) UNSIGNED NOT NULL,
    `step` VARCHAR(20) NOT NULL COMMENT 'initiator | area_authority | hse | manager | coordinator',
    `sort_order` INT(11) NOT NULL DEFAULT 0,
    `staff_id` INT(11) NOT NULL DEFAULT 0,
    `name` VARCHAR(150) NOT NULL DEFAULT '' COMMENT 'paper approvals: who signed',
    `decision` VARCHAR(10) NOT NULL DEFAULT 'pending' COMMENT 'pending | approved | rejected',
    `remarks` TEXT DEFAULT NULL,
    `signature` MEDIUMTEXT DEFAULT NULL COMMENT 'data-url PNG',
    `decided_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `permit_id` (`permit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

$CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}eptw_gas_tests` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `permit_id` INT(11) UNSIGNED NOT NULL,
    `tested_at` DATETIME NOT NULL,
    `o2` DECIMAL(6,2) DEFAULT NULL,
    `lel` DECIMAL(6,2) DEFAULT NULL,
    `h2s` DECIMAL(8,2) DEFAULT NULL,
    `co` DECIMAL(8,2) DEFAULT NULL,
    `so2` DECIMAL(8,2) DEFAULT NULL,
    `nh3` DECIMAL(8,2) DEFAULT NULL,
    `tester` VARCHAR(150) NOT NULL DEFAULT '',
    `result` VARCHAR(10) NOT NULL DEFAULT 'safe' COMMENT 'safe | unsafe',
    `remarks` VARCHAR(255) NOT NULL DEFAULT '',
    `created_by` INT(11) NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `permit_id` (`permit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

$CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}eptw_documents` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `permit_id` INT(11) UNSIGNED NOT NULL,
    `doc_type` VARCHAR(30) NOT NULL DEFAULT 'other',
    `file_name` VARCHAR(191) NOT NULL,
    `original_name` VARCHAR(191) NOT NULL,
    `mime` VARCHAR(100) NOT NULL DEFAULT '',
    `size` INT(11) NOT NULL DEFAULT 0,
    `note` VARCHAR(255) NOT NULL DEFAULT '',
    `uploaded_by` INT(11) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    KEY `permit_id` (`permit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

$CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}eptw_extensions` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `permit_id` INT(11) UNSIGNED NOT NULL,
    `requested_by` INT(11) NOT NULL DEFAULT 0,
    `reason` TEXT DEFAULT NULL,
    `old_end_at` DATETIME DEFAULT NULL,
    `new_end_at` DATETIME DEFAULT NULL,
    `status` VARCHAR(10) NOT NULL DEFAULT 'pending' COMMENT 'pending | approved | rejected',
    `decided_by` INT(11) NOT NULL DEFAULT 0,
    `decided_at` DATETIME DEFAULT NULL,
    `decision_note` VARCHAR(255) NOT NULL DEFAULT '',
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    KEY `permit_id` (`permit_id`),
    KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

$CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}eptw_revalidations` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `permit_id` INT(11) UNSIGNED NOT NULL,
    `shift` VARCHAR(10) NOT NULL DEFAULT 'day',
    `from_at` DATETIME DEFAULT NULL,
    `to_at` DATETIME DEFAULT NULL,
    `area_authority` VARCHAR(150) NOT NULL DEFAULT '',
    `issuer` VARCHAR(150) NOT NULL DEFAULT '',
    `hse` VARCHAR(150) NOT NULL DEFAULT '',
    `gas_test_ok` TINYINT(1) NOT NULL DEFAULT 1,
    `notes` VARCHAR(255) NOT NULL DEFAULT '',
    `created_by` INT(11) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    KEY `permit_id` (`permit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

$CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}eptw_simops_rules` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `type_a` VARCHAR(10) NOT NULL,
    `type_b` VARCHAR(10) NOT NULL,
    `severity` VARCHAR(10) NOT NULL DEFAULT 'warn' COMMENT 'warn | block',
    `description` VARCHAR(255) NOT NULL DEFAULT '',
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    KEY `pair` (`type_a`, `type_b`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

// table_exists() caches the table list on first call in a request; the tables
// above were created after that, so refresh it before anything depends on it.
unset($CI->db->data_cache['table_names']);

// ── Seed the 17 permit templates (only the ones not yet present, by code) ──
require_once __DIR__ . '/data/permit_types_seed.php';

$eptw_existing_codes = [];
foreach ($CI->db->select('code')->get($p . 'eptw_permit_types')->result() as $eptw_row) {
    $eptw_existing_codes[] = $eptw_row->code;
}

$eptw_sort = 0;
foreach (eptw_permit_types_seed() as $eptw_type) {
    $eptw_sort += 10;
    if (in_array($eptw_type['code'], $eptw_existing_codes, true)) {
        continue;
    }
    $CI->db->insert($p . 'eptw_permit_types', [
        'code'                   => $eptw_type['code'],
        'name'                   => $eptw_type['name'],
        'description'            => $eptw_type['description'],
        'icon'                   => $eptw_type['icon'],
        'color'                  => $eptw_type['color'],
        'high_risk'              => (int) $eptw_type['high_risk'],
        'gas_test_required'      => (int) $eptw_type['gas_test_required'],
        'isolation_required'     => (int) $eptw_type['isolation_required'],
        'default_validity_hours' => (int) $eptw_type['default_validity_hours'],
        'hazards'                => json_encode($eptw_type['hazards']),
        'controls'               => json_encode($eptw_type['controls']),
        'extra_fields'           => json_encode($eptw_type['extra_fields']),
        'ppe'                    => json_encode($eptw_type['ppe']),
        'approvals'              => json_encode($eptw_type['approvals']),
        'keywords'               => json_encode($eptw_type['keywords']),
        'sort_order'             => $eptw_sort,
        'active'                 => 1,
    ]);
}

// ── Seed SIMOPS rules once ──
if ((int) $CI->db->count_all($p . 'eptw_simops_rules') === 0) {
    foreach (eptw_simops_rules_seed() as $eptw_rule) {
        $CI->db->insert($p . 'eptw_simops_rules', [
            'type_a'      => $eptw_rule[0],
            'type_b'      => $eptw_rule[1],
            'severity'    => $eptw_rule[2],
            'description' => $eptw_rule[3],
            'active'      => 1,
        ]);
    }
}

// ── Placeholder configuration so the first screen is not empty ──
if ((int) $CI->db->count_all($p . 'eptw_projects') === 0) {
    $CI->db->insert($p . 'eptw_projects', ['code' => 'ALPHA', 'name' => 'Project Alpha', 'active' => 1, 'created_at' => date('Y-m-d H:i:s')]);
    $eptw_project_id = (int) $CI->db->insert_id();
    foreach ([['ZA', 'Zone A'], ['ZB', 'Zone B'], ['A01', 'Area 01']] as $eptw_area) {
        $CI->db->insert($p . 'eptw_areas', ['project_id' => $eptw_project_id, 'code' => $eptw_area[0], 'name' => $eptw_area[1], 'active' => 1]);
    }
}
if ((int) $CI->db->count_all($p . 'eptw_contractors') === 0) {
    $CI->db->insert($p . 'eptw_contractors', ['code' => 'CTR-A', 'name' => 'Contractor A', 'active' => 1]);
    $CI->db->insert($p . 'eptw_contractors', ['code' => 'CTR-B', 'name' => 'Contractor B', 'active' => 1]);
}

_maybe_create_upload_path(FCPATH . 'uploads/eptw');
_maybe_create_upload_path(FCPATH . 'uploads/eptw/permits');
