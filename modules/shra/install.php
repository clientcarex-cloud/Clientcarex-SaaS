<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * SHRA module — schema + seed data.
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

// ── Upload dir for logo / PDFs ──
$shra_dir = FCPATH . 'uploads/shra/';
if (!is_dir($shra_dir)) {
    @mkdir($shra_dir, 0755, true);
    @file_put_contents($shra_dir . 'index.html', '');
}
