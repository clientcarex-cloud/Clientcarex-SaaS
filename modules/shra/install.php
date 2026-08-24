<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * SHRA module — schema + seed data (riders, billing, attendance, leads).
 * Safe to run repeatedly (activation + schema self-heal on admin_init).
 */

$CI = &get_instance();
$p  = db_prefix();

// ── Riders (one per person; linked to the core customer / contact) ──
if (!$CI->db->table_exists($p . 'shra_riders')) {
    $CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}shra_riders` (
        `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `rider_no` VARCHAR(20) NOT NULL,
        `client_id` INT(11) DEFAULT NULL COMMENT 'tblclients.userid',
        `contact_id` INT(11) DEFAULT NULL COMMENT 'tblcontacts.id',
        `rider_type` VARCHAR(10) NOT NULL DEFAULT 'learner' COMMENT 'learner | guest',
        `full_name` VARCHAR(191) NOT NULL,
        `guardian_name` VARCHAR(191) DEFAULT NULL,
        `guardian_relationship` VARCHAR(60) DEFAULT NULL,
        `mobile` VARCHAR(30) NOT NULL,
        `email` VARCHAR(191) DEFAULT NULL,
        `gender` VARCHAR(20) DEFAULT NULL,
        `dob` DATE DEFAULT NULL,
        `place_of_birth` VARCHAR(191) DEFAULT NULL,
        `address` TEXT DEFAULT NULL,
        `marital_status` VARCHAR(20) DEFAULT NULL COMMENT 'single | married | divorced | other',
        `riding_level` VARCHAR(30) NOT NULL DEFAULT 'beginner',
        `preferred_package_id` INT(11) UNSIGNED DEFAULT NULL,
        `is_minor` TINYINT(1) NOT NULL DEFAULT 0,
        `terms_accepted` TINYINT(1) NOT NULL DEFAULT 0,
        `terms_accepted_by` VARCHAR(191) DEFAULT NULL COMMENT 'rider or guardian name',
        `terms_accepted_at` DATETIME DEFAULT NULL,
        `membership_no` VARCHAR(30) DEFAULT NULL,
        `membership_issued_at` DATETIME DEFAULT NULL,
        `status` VARCHAR(15) NOT NULL DEFAULT 'active' COMMENT 'active | inactive',
        `source` VARCHAR(10) NOT NULL DEFAULT 'staff' COMMENT 'self | staff',
        `ip_address` VARCHAR(45) DEFAULT NULL,
        `notes` TEXT DEFAULT NULL,
        `created_by` INT(11) DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `rider_no` (`rider_no`),
        KEY `client_id` (`client_id`),
        KEY `mobile` (`mobile`),
        KEY `rider_type` (`rider_type`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
}

// ── Packages (price list) ──
if (!$CI->db->table_exists($p . 'shra_packages')) {
    $CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}shra_packages` (
        `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(100) NOT NULL,
        `audience` VARCHAR(10) NOT NULL DEFAULT 'children' COMMENT 'children | adults',
        `sessions` INT(11) NOT NULL DEFAULT 1,
        `duration_min` INT(11) NOT NULL DEFAULT 30,
        `per_session` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `price` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `is_guest` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'guest ride — no membership / certificate',
        `is_featured` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'best value badge',
        `validity_days` INT(11) DEFAULT NULL COMMENT 'NULL = no expiry',
        `active` TINYINT(1) NOT NULL DEFAULT 1,
        `sort_order` INT(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        KEY `audience` (`audience`),
        KEY `active` (`active`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
}

// ── Enrollments (a purchased package = sessions wallet) ──
if (!$CI->db->table_exists($p . 'shra_enrollments')) {
    $CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}shra_enrollments` (
        `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `enrollment_no` VARCHAR(20) NOT NULL,
        `rider_id` INT(11) UNSIGNED NOT NULL,
        `package_id` INT(11) UNSIGNED DEFAULT NULL,
        `package_name` VARCHAR(100) NOT NULL,
        `audience` VARCHAR(10) NOT NULL DEFAULT 'children',
        `is_guest` TINYINT(1) NOT NULL DEFAULT 0,
        `sessions_total` INT(11) NOT NULL DEFAULT 1,
        `sessions_used` INT(11) NOT NULL DEFAULT 0,
        `duration_min` INT(11) NOT NULL DEFAULT 30,
        `list_price` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `discount_percent` DECIMAL(5,2) NOT NULL DEFAULT 0,
        `discount_amount` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `total` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `paid_amount` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `payment_mode` VARCHAR(100) DEFAULT NULL,
        `invoice_id` INT(11) DEFAULT NULL,
        `start_date` DATE DEFAULT NULL,
        `expires_at` DATE DEFAULT NULL,
        `status` VARCHAR(15) NOT NULL DEFAULT 'active' COMMENT 'active | completed | expired | cancelled',
        `completed_at` DATETIME DEFAULT NULL,
        `certificate_no` VARCHAR(30) DEFAULT NULL,
        `certificate_issued_at` DATETIME DEFAULT NULL,
        `certificate_issued_by` INT(11) DEFAULT NULL,
        `notes` VARCHAR(500) DEFAULT NULL,
        `created_by` INT(11) DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `enrollment_no` (`enrollment_no`),
        KEY `rider_id` (`rider_id`),
        KEY `status` (`status`),
        KEY `invoice_id` (`invoice_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
}

// ── Attendance (one row per class session attended) ──
if (!$CI->db->table_exists($p . 'shra_attendance')) {
    $CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}shra_attendance` (
        `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `enrollment_id` INT(11) UNSIGNED NOT NULL,
        `rider_id` INT(11) UNSIGNED NOT NULL,
        `session_no` INT(11) NOT NULL DEFAULT 1,
        `session_date` DATE NOT NULL,
        `session_time` TIME DEFAULT NULL,
        `trainer_id` INT(11) DEFAULT NULL COMMENT 'tblstaff.staffid',
        `horse_name` VARCHAR(100) DEFAULT NULL,
        `notes` VARCHAR(500) DEFAULT NULL,
        `marked_by` INT(11) DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `enrollment_id` (`enrollment_id`),
        KEY `rider_id` (`rider_id`),
        KEY `session_date` (`session_date`),
        KEY `trainer_id` (`trainer_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
}

// ── Trainers (academy instructors — not necessarily CRM staff) ──
if (!$CI->db->table_exists($p . 'shra_trainers')) {
    $CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}shra_trainers` (
        `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(191) NOT NULL,
        `mobile` VARCHAR(30) DEFAULT NULL,
        `specialty` VARCHAR(191) DEFAULT NULL,
        `staff_id` INT(11) DEFAULT NULL COMMENT 'optional link to tblstaff.staffid',
        `active` TINYINT(1) NOT NULL DEFAULT 1,
        `sort_order` INT(11) NOT NULL DEFAULT 0,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `staff_id` (`staff_id`),
        KEY `active` (`active`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // Migrate: attendance.trainer_id used to point at tblstaff.staffid. Create a
    // trainer row for every staff member already referenced and repoint the rows.
    // Guard the query object: with db_debug off (production) a failed query is
    // FALSE, and ->result() on it would abort the whole activation.
    $used = $CI->db->query("SELECT DISTINCT trainer_id FROM `{$p}shra_attendance` WHERE trainer_id IS NOT NULL");
    $used = $used ? $used->result() : [];
    foreach ($used as $u) {
        $st = $CI->db->select('staffid, firstname, lastname, phonenumber')->where('staffid', $u->trainer_id)->get($p . 'staff')->row();
        $CI->db->insert($p . 'shra_trainers', [
            'name'     => $st ? trim($st->firstname . ' ' . $st->lastname) : 'Trainer #' . $u->trainer_id,
            'mobile'   => $st ? $st->phonenumber : null,
            'staff_id' => (int) $u->trainer_id,
            'active'   => 1,
        ]);
        $tid = $CI->db->insert_id();
        $CI->db->where('trainer_id', $u->trainer_id)->update($p . 'shra_attendance', ['trainer_id' => $tid]);
    }
}

// ── Self-heal: duplicate-bill guard token ──
if (!$CI->db->field_exists('bill_token', $p . 'shra_enrollments')) {
    $CI->db->query("ALTER TABLE `{$p}shra_enrollments` ADD COLUMN `bill_token` VARCHAR(40) DEFAULT NULL AFTER `invoice_id`, ADD UNIQUE KEY `bill_token` (`bill_token`)");
}

// ── Self-heal: per-session guard — one rider should not be marked twice on a day by accident ──
if (!$CI->db->field_exists('forced', $p . 'shra_attendance')) {
    $CI->db->query("ALTER TABLE `{$p}shra_attendance` ADD COLUMN `forced` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'second session on the same day, confirmed by staff' AFTER `notes`");
}

// ── Payment modes: make sure the counter has Cash and UPI ──
foreach (['Cash', 'UPI'] as $pm_name) {
    $exists = $CI->db->where('LOWER(name)', strtolower($pm_name))->get($p . 'payment_modes')->row();
    if (!$exists) {
        $CI->db->insert($p . 'payment_modes', [
            'name'                => $pm_name,
            'description'         => $pm_name === 'UPI' ? 'UPI / QR payment at the counter' : 'Cash at the counter',
            'active'              => 1,
            'expenses_only'       => 0,
            'invoices_only'       => 0,
            'show_on_pdf'         => 0,
            'selected_by_default' => $pm_name === 'Cash' ? 1 : 0,
        ]);
    } elseif ((int) $exists->active !== 1) {
        $CI->db->where('id', $exists->id)->update($p . 'payment_modes', ['active' => 1]);
    }
}

// ── Self-heal: plan chosen on the public form ──
if (!$CI->db->field_exists('preferred_package_id', $p . 'shra_riders')) {
    $CI->db->query("ALTER TABLE `{$p}shra_riders` ADD COLUMN `preferred_package_id` INT(11) UNSIGNED DEFAULT NULL AFTER `riding_level`");
}

// ── Options (add_option is a no-op when the key exists) ──
$shra_defaults = [
    'shra_schema_version'       => '0',
    'shra_public_token'         => bin2hex(random_bytes(6)),
    'shra_academy_name'         => 'Stallion Horse Riding Academy',
    'shra_tagline'              => 'Ride with confidence. Learn with pride.',
    'shra_logo'                 => '',
    'shra_offer_active'         => '1',
    'shra_offer_percent'        => '30',
    'shra_offer_label'          => 'Limited time offer',
    'shra_offer_ends'           => '',
    'shra_minor_age'            => '18',
    'shra_riding_levels'        => "Beginner\nNovice\nIntermediate\nAdvanced\nCompetitive",
    'shra_chief_instructor'     => 'Chief Instructor',
    'shra_director'             => 'Director',
    'shra_contact_line'         => '',
    'shra_next_rider_no'        => '1',
    'shra_next_enrollment_no'   => '1',
    'shra_next_membership_no'   => '1',
    'shra_next_certificate_no'  => '1',
    'shra_auto_certificate'     => '1',
    'shra_terms'                => "1. Horse riding is an adventure activity that carries inherent risk. Riders participate at their own risk and agree to follow every instruction given by the academy's trainers.\n2. A riding helmet and closed-toe footwear are mandatory for every session. The academy may refuse a session to any rider who is not suitably dressed.\n3. Sessions are on a first-come, first-served basis. There are no fixed time slots; riders are advised to arrive early.\n4. Package sessions are personal, non-transferable and non-refundable. Unused sessions lapse after the validity period, if any.\n5. Riders must disclose any medical condition (heart, back, pregnancy, epilepsy etc.) that may affect their safety before mounting.\n6. The academy is not responsible for loss of personal belongings on the premises.\n7. Riders and visitors agree to treat horses, staff and fellow riders with respect. Any mistreatment of horses will end the membership immediately.\n8. For riders under the age of 18 a parent or legal guardian must accept these terms on the rider's behalf and remain contactable during sessions.\n9. Photographs and videos taken at the academy may be used for promotion unless the rider opts out in writing.",
];

foreach ($shra_defaults as $k => $v) {
    add_option($k, $v);
}

// ── Seed price list (only when empty) ──
$count = (int) $CI->db->count_all($p . 'shra_packages');
if ($count === 0) {
    $seed = [
        // audience, name, sessions, duration, per session, price, guest, featured, validity, sort
        ['children', 'Guest ride', 1, 20, 1000, 1000, 1, 0, null, 1],
        ['children', 'Weekends', 8, 30, 1000, 8000, 0, 0, null, 2],
        ['children', 'Monthly', 16, 30, 900, 14400, 0, 0, null, 3],
        ['children', 'Quarterly', 48, 30, 800, 38400, 0, 0, null, 4],
        ['children', 'Annual package', 192, 30, 700, 134400, 0, 1, null, 5],
        ['adults', 'Guest ride', 1, 20, 1200, 1200, 1, 0, null, 11],
        ['adults', 'Weekends', 8, 30, 1200, 9600, 0, 0, null, 12],
        ['adults', 'Monthly', 16, 30, 1100, 17600, 0, 0, null, 13],
        ['adults', 'Quarterly', 48, 30, 1000, 48000, 0, 0, null, 14],
        ['adults', 'Annual package', 192, 30, 800, 153600, 0, 1, null, 15],
    ];
    foreach ($seed as $s) {
        $CI->db->insert($p . 'shra_packages', [
            'audience'      => $s[0],
            'name'          => $s[1],
            'sessions'      => $s[2],
            'duration_min'  => $s[3],
            'per_session'   => $s[4],
            'price'         => $s[5],
            'is_guest'      => $s[6],
            'is_featured'   => $s[7],
            'validity_days' => $s[8],
            'sort_order'    => $s[9],
            'active'        => 1,
        ]);
    }
}

// ── Base currency: INR with the Rupee symbol ──
// The academy bills in Rupees. Create INR if it is missing and make it the
// base currency. Perfex refuses to change the base currency once invoices /
// payments exist in the old one — in that case INR is still added and the
// admin can switch it manually under Setup → Finance → Currencies.
$inr = $CI->db->where('name', 'INR')->get($p . 'currencies')->row();
if (!$inr) {
    $CI->db->insert($p . 'currencies', [
        'symbol'             => '₹',
        'name'               => 'INR',
        'decimal_separator'  => '.',
        'thousand_separator' => ',',
        'placement'          => 'before',
        'isdefault'          => 0,
    ]);
    $inr = $CI->db->where('id', $CI->db->insert_id())->get($p . 'currencies')->row();
} elseif ($inr->symbol !== '₹') {
    $CI->db->where('id', $inr->id)->update($p . 'currencies', ['symbol' => '₹']);
}

if ($inr && (int) $inr->isdefault !== 1) {
    // Do NOT use currencies_model->make_base_currency(): it walks
    // $app->get_tables_with_currency(), a list built once when the App library
    // was constructed. When the SaaS superadmin activates the module remotely
    // the App library was constructed against the master database, so that list
    // still carries the master's `tbl` prefix — every lookup then hits a table
    // that does not exist in the tenant, the query returns false and ->row()
    // aborts activation before Perfex ever flips tblmodules.active to 1.
    $base = $CI->db->where('isdefault', 1)->get($p . 'currencies')->row();
    $in_use = false;
    foreach ([
        'invoices' => 'currency',
        'expenses' => 'currency',
        'proposals' => 'currency',
        'estimates' => 'currency',
        'clients' => 'default_currency',
        'creditnotes' => 'currency',
        'subscriptions' => 'currency',
    ] as $table => $field) {
        if (!$base) {
            break;
        }
        if (!$CI->db->table_exists($p . $table)) {
            continue;
        }
        if ($CI->db->where($field, $base->id)->get($p . $table)->row()) {
            $in_use = true;
            break;
        }
    }

    if ($in_use) {
        log_activity('SHRA: could not switch base currency to INR — transactions exist in the current base currency. Change it manually under Setup → Finance → Currencies.');
    } else {
        $CI->db->where('id', $inr->id)->update($p . 'currencies', ['isdefault' => 1]);
        $CI->db->where('id !=', $inr->id)->update($p . 'currencies', ['isdefault' => 0]);
        log_activity('SHRA: base currency set to INR (₹)');
    }
}

// ── Upload dir for logo / PDFs ──
$shra_dir = FCPATH . 'uploads/shra/';
if (!is_dir($shra_dir)) {
    @mkdir($shra_dir, 0755, true);
    @file_put_contents($shra_dir . 'index.html', '');
}

/* ═══════════════════════════════════════════════════════════════════════
 * v1.3.0 — Leads management (calling agents, visits, revenue attribution)
 * ═══════════════════════════════════════════════════════════════════════ */

// ── Lead extension (1:1 with tblleads) ──
if (!$CI->db->table_exists($p . 'shra_lead_ext')) {
    $CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}shra_lead_ext` (
        `lead_id` INT(11) NOT NULL COMMENT 'tblleads.id',
        `phone_norm` VARCHAR(20) NOT NULL COMMENT 'digits only, dedupe key',
        `stage_key` VARCHAR(30) NOT NULL DEFAULT 'new',
        `rider_for` VARCHAR(10) NOT NULL DEFAULT 'self' COMMENT 'self | child | both',
        `rider_age` TINYINT(3) UNSIGNED DEFAULT NULL,
        `audience` VARCHAR(10) DEFAULT NULL COMMENT 'children | adults',
        `interest_package_id` INT(11) UNSIGNED DEFAULT NULL,
        `expected_value` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `next_action_at` DATETIME DEFAULT NULL COMMENT 'required while open',
        `next_action_type` VARCHAR(12) NOT NULL DEFAULT 'call' COMMENT 'call | whatsapp | visit | other',
        `visit_date` DATE DEFAULT NULL,
        `visit_slot` VARCHAR(40) DEFAULT NULL,
        `visit_reminder_id` INT(11) DEFAULT NULL,
        `visited_at` DATETIME DEFAULT NULL,
        `visited_by` INT(11) DEFAULT NULL,
        `confirmed_at` DATETIME DEFAULT NULL,
        `first_contact_at` DATETIME DEFAULT NULL,
        `no_show_count` TINYINT(3) UNSIGNED NOT NULL DEFAULT 0,
        `call_attempts` SMALLINT(5) UNSIGNED NOT NULL DEFAULT 0,
        `last_outcome` VARCHAR(30) DEFAULT NULL,
        `lost_reason` VARCHAR(40) DEFAULT NULL,
        `lost_note` VARCHAR(255) DEFAULT NULL,
        `is_stale` TINYINT(1) NOT NULL DEFAULT 0,
        `sla_notified` TINYINT(1) NOT NULL DEFAULT 0,
        `rider_id` INT(11) UNSIGNED DEFAULT NULL,
        `first_enrollment_id` INT(11) UNSIGNED DEFAULT NULL,
        `won_at` DATETIME DEFAULT NULL,
        `campaign` VARCHAR(80) DEFAULT NULL,
        `referrer_rider_id` INT(11) UNSIGNED DEFAULT NULL,
        `ip_address` VARCHAR(45) DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`lead_id`),
        UNIQUE KEY `phone_norm` (`phone_norm`),
        KEY `stage_key` (`stage_key`),
        KEY `next_action_at` (`next_action_at`),
        KEY `visit_date` (`visit_date`),
        KEY `rider_id` (`rider_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
}

// ── Append-only lead audit ──
if (!$CI->db->table_exists($p . 'shra_lead_events')) {
    $CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}shra_lead_events` (
        `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        `lead_id` INT(11) NOT NULL,
        `staff_id` INT(11) DEFAULT NULL,
        `event_type` VARCHAR(30) NOT NULL,
        `outcome` VARCHAR(30) DEFAULT NULL,
        `from_value` VARCHAR(60) DEFAULT NULL,
        `to_value` VARCHAR(60) DEFAULT NULL,
        `note` TEXT DEFAULT NULL,
        `meta` TEXT DEFAULT NULL COMMENT 'json',
        `ip` VARCHAR(45) DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `lead_created` (`lead_id`, `created_at`),
        KEY `staff_created` (`staff_id`, `created_at`),
        KEY `type_created` (`event_type`, `created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
}

// ── Frozen revenue credit ──
if (!$CI->db->table_exists($p . 'shra_lead_attribution')) {
    $CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}shra_lead_attribution` (
        `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `lead_id` INT(11) NOT NULL,
        `agent_id` INT(11) NOT NULL COMMENT 'tblstaff.staffid credited',
        `rider_id` INT(11) UNSIGNED DEFAULT NULL,
        `enrollment_id` INT(11) UNSIGNED NOT NULL,
        `invoice_id` INT(11) DEFAULT NULL,
        `kind` VARCHAR(10) NOT NULL DEFAULT 'first' COMMENT 'first | repeat',
        `amount_billed` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `amount_paid` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `source_id` INT(11) DEFAULT NULL,
        `credited_by` INT(11) DEFAULT NULL,
        `credited_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `locked` TINYINT(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (`id`),
        UNIQUE KEY `enrollment_id` (`enrollment_id`),
        KEY `agent_credited` (`agent_id`, `credited_at`),
        KEY `lead_id` (`lead_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
}

// ── Monthly targets per agent ──
if (!$CI->db->table_exists($p . 'shra_lead_targets')) {
    $CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}shra_lead_targets` (
        `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `staff_id` INT(11) NOT NULL,
        `month` CHAR(7) NOT NULL COMMENT 'YYYY-MM',
        `calls_target` INT(11) NOT NULL DEFAULT 0,
        `visits_target` INT(11) NOT NULL DEFAULT 0,
        `revenue_target` DECIMAL(15,2) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        UNIQUE KEY `staff_month` (`staff_id`, `month`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
}

// ── Per-source spend (CPL / ROI) ──
if (!$CI->db->table_exists($p . 'shra_lead_sources_meta')) {
    $CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}shra_lead_sources_meta` (
        `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `source_id` INT(11) NOT NULL,
        `monthly_cost` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `active` TINYINT(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (`id`),
        UNIQUE KEY `source_id` (`source_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
}

// ── Advance / part payments collected by an agent on a call ──
if (!$CI->db->table_exists($p . 'shra_lead_payments')) {
    $CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}shra_lead_payments` (
        `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `lead_id` INT(11) NOT NULL,
        `staff_id` INT(11) DEFAULT NULL COMMENT 'agent who collected it',
        `amount` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `method` VARCHAR(40) DEFAULT NULL COMMENT 'UPI | Cash | Card | Bank transfer | …',
        `reference` VARCHAR(80) DEFAULT NULL COMMENT 'UPI ref / receipt no',
        `note` VARCHAR(255) DEFAULT NULL,
        `file` VARCHAR(160) DEFAULT NULL COMMENT 'screenshot in uploads/shra/lead_payments/',
        `file_name` VARCHAR(160) DEFAULT NULL COMMENT 'name the agent uploaded',
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `lead_created` (`lead_id`, `created_at`),
        KEY `staff_created` (`staff_id`, `created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
}

// Running total on the lead, so lists and rows need no join.
if (!$CI->db->field_exists('paid_amount', $p . 'shra_lead_ext')) {
    $CI->db->query("ALTER TABLE `{$p}shra_lead_ext` ADD COLUMN `paid_amount` DECIMAL(15,2) NOT NULL DEFAULT 0 COMMENT 'advance collected before billing' AFTER `expected_value`,
        ADD COLUMN `last_payment_at` DATETIME DEFAULT NULL AFTER `paid_amount`");
}

// ── Payment screenshots (served only through Shra_leads::payment_file) ──
$pay_dir = FCPATH . 'uploads/shra/lead_payments/';
if (!is_dir($pay_dir)) {
    @mkdir($pay_dir, 0755, true);
}
@file_put_contents($pay_dir . 'index.html', '');
@file_put_contents($pay_dir . '.htaccess', "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n");

// ── Funnel stages → core tblleads_status (keeps native Perfex leads working) ──
$stage_defs = [
    'new'              => ['New', 10, '#5b8def'],
    'prospect'         => ['Prospect', 12, '#6b7a99'],
    'enquired'         => ['Enquired', 14, '#17a2b8'],
    'contacted'        => ['Contacted', 20, '#8e7cc3'],
    'no_response'      => ['No Response', 24, '#8d6e63'],
    'callback_request' => ['Call back Request', 26, '#d1477a'],
    'followup'         => ['Follow-up', 30, '#d4a017'],
    'visit_scheduled'  => ['Visit Scheduled', 40, '#e67e22'],
    'visited'          => ['Visited', 50, '#2e86c1'],
    'confirmed'        => ['Visited & Confirmed', 60, '#1e8449'],
    'won'              => ['Customer', 1000, '#7cb342'],
];
// Read straight from the options table, not get_option(): the App library
// caches autoloaded options at construction, so a remote (impersonated)
// install would inherit the master instance's status ids here.
$stage_map_row = $CI->db->select('value')->where('name', 'shra_lead_stage_map')->get($p . 'options')->row();
$stage_map = json_decode((string) ($stage_map_row->value ?? ''), true) ?: [];
foreach ($stage_defs as $key => $d) {
    $row = null;
    if (!empty($stage_map[$key])) {
        $row = $CI->db->where('id', (int) $stage_map[$key])->get($p . 'leads_status')->row();
    }
    if (!$row && $key === 'won') {
        $row = $CI->db->where('isdefault', 1)->get($p . 'leads_status')->row();
    }
    if (!$row) {
        $row = $CI->db->where('name', $d[0])->get($p . 'leads_status')->row();
    }
    if (!$row) {
        $CI->db->insert($p . 'leads_status', ['name' => $d[0], 'statusorder' => $d[1], 'color' => $d[2], 'isdefault' => $key === 'won' ? 1 : 0]);
        $id = $CI->db->insert_id();
    } else {
        $id = $row->id;
        if ($key !== 'won') {
            $CI->db->where('id', $id)->update($p . 'leads_status', ['statusorder' => $d[1]]);
        }
    }
    $stage_map[$key] = (int) $id;
}
update_option('shra_lead_stage_map', json_encode($stage_map));

// ── Lead sources ──
foreach (['Walk-in', 'Phone Inquiry', 'Instagram', 'Facebook', 'Google', 'WhatsApp', 'Referral', 'School Tie-up', 'Event / Camp', 'Justdial', 'Website QR', 'Other'] as $src) {
    $exists = $CI->db->where('name', $src)->get($p . 'leads_sources')->row();
    if (!$exists) {
        $CI->db->insert($p . 'leads_sources', ['name' => $src]);
    }
}

// ── Roles (only created when missing; admins can edit them under Setup → Roles) ──
$role_defs = [
    'SHRA Calling Agent' => ['shra' => ['leads_own']],
    'SHRA Sales Manager' => ['shra' => ['view', 'leads_own', 'leads_all', 'leads_manage', 'leads_reports', 'billing']],
];
foreach ($role_defs as $rname => $perms) {
    $exists = $CI->db->where('name', $rname)->get($p . 'roles')->row();
    if (!$exists) {
        $CI->db->insert($p . 'roles', ['name' => $rname, 'permissions' => serialize($perms)]);
    }
}

// ── Lead options ──
$lead_defaults = [
    'shra_lead_sla_minutes'          => '120',
    'shra_lead_stale_days'           => '7',
    'shra_lead_auto_assign'          => '1',
    'shra_lead_agent_pool'           => '[]',
    'shra_lead_phone_country'        => '91',
    'shra_lead_repeat_credit_months' => '12',
    'shra_lead_manager_digest'       => '1',
    'shra_lead_visit_slots'          => "Sat 07:00-08:00\nSat 08:00-09:00\nSat 16:00-17:00\nSat 17:00-18:00\nSun 07:00-08:00\nSun 08:00-09:00\nSun 16:00-17:00\nSun 17:00-18:00\nWeekday (any time)",
    'shra_lead_payment_methods'      => "UPI\nCash\nCard\nBank transfer\nCheque",
    'shra_lead_lost_reasons'         => "Price too high\nDistance / location\nTiming doesn't suit\nChild too young\nJoined a competitor\nNo response after 5+ calls\nNot interested anymore\nOther",
    'shra_lead_wa_templates'         => "Intro|Hello {name}, this is {agent} from {academy}. Thank you for your interest in horse riding! May I share our packages and a good time for a visit?\nVisit reminder|Hi {name}, reminder of your visit to {academy} on {visit}. Please wear full-length trousers and closed shoes. See you there!\nOffer|Hi {name}, {academy} has a limited-time offer on packages this month. Shall I reserve a slot for you?",
    'shra_lead_last_cron'            => '0',
    'shra_lead_last_digest'          => '',
    'shra_lead_public_enabled'       => '1',
    'shra_lead_import_map'           => '{}',
    // Ad landing page (/inquire)
    'shra_lead_landing_phone'        => '9908480010',
    'shra_lead_landing_location'     => 'The Wilderness Retreat, Kokapet, Hyderabad',
    'shra_lead_landing_maps_url'     => '',
    'shra_lead_landing_instagram'    => 'https://www.instagram.com/stallionhorseriding/',
    'shra_lead_landing_reels'        => "DUs_7fKk4kE\nDUVtHcEEnZR\nDcOWWidzl0q\nDcJTWs0smrv\nDcBUVBvhifG\nDbqKR3ZTrzr",
    'shra_lead_landing_min_age'      => '4',
    'shra_lead_meta_pixel_id'        => '',
    'shra_lead_gads_id'              => '',
    'shra_lead_gads_label'           => '',
    'shra_lead_ga4_id'               => '',
    'shra_lead_ig_cache'             => '',
    'shra_lead_landing_map_query'    => 'The Wilderness Retreat, Kokapet, Hyderabad',
    'shra_lead_landing_map_embed'    => '',
];
foreach ($lead_defaults as $k => $v) {
    add_option($k, $v);
}
