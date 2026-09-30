<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * DUNS Support — schema, seed plans and default settings.
 * Idempotent: runs on activation and again from the version self-heal.
 *
 * Nothing here is guarded by table_exists(): CI caches the table list on its
 * first call, so a table created earlier in the same request would look
 * missing. CREATE TABLE IF NOT EXISTS + a COUNT(*) seed guard avoid that.
 */

$CI = &get_instance();
$p  = db_prefix();

$CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}duns_plans` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `delivery_hours` INT(11) NOT NULL DEFAULT 12,
    `price` DECIMAL(15,2) NOT NULL DEFAULT 0,
    `compare_price` DECIMAL(15,2) DEFAULT NULL COMMENT 'struck-through price, optional',
    `badge` VARCHAR(40) DEFAULT NULL,
    `features` TEXT DEFAULT NULL COMMENT 'one per line',
    `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order` INT(11) NOT NULL DEFAULT 0,
    `created_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

$CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}duns_orders` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_no` VARCHAR(30) DEFAULT NULL,
    `ref` VARCHAR(40) NOT NULL COMMENT 'unguessable public tracking token',
    `plan_id` INT(11) UNSIGNED DEFAULT NULL,
    `plan_name` VARCHAR(100) NOT NULL,
    `delivery_hours` INT(11) NOT NULL DEFAULT 12,
    `amount` DECIMAL(15,2) NOT NULL DEFAULT 0,
    `company_name` VARCHAR(191) NOT NULL,
    `cin` VARCHAR(40) DEFAULT NULL,
    `director_name` VARCHAR(191) NOT NULL,
    `director_email` VARCHAR(191) NOT NULL,
    `director_mobile` VARCHAR(30) NOT NULL,
    `work_email` VARCHAR(191) NOT NULL,
    `notes` TEXT DEFAULT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'pending_payment'
        COMMENT 'pending_payment | paid | processing | completed | refunded | cancelled',
    `duns_number` VARCHAR(20) DEFAULT NULL,
    `client_id` INT(11) DEFAULT NULL,
    `invoice_id` INT(11) DEFAULT NULL,
    `gateway` VARCHAR(40) DEFAULT NULL,
    `amount_paid` DECIMAL(15,2) NOT NULL DEFAULT 0,
    `paid_at` DATETIME DEFAULT NULL,
    `due_at` DATETIME DEFAULT NULL COMMENT 'paid_at + delivery_hours',
    `completed_at` DATETIME DEFAULT NULL,
    `purchase_tracked` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'ad Purchase event fired once',
    `assigned_to` INT(11) DEFAULT NULL,
    `utm_source` VARCHAR(100) DEFAULT NULL,
    `utm_medium` VARCHAR(100) DEFAULT NULL,
    `utm_campaign` VARCHAR(191) DEFAULT NULL,
    `fbclid` VARCHAR(255) DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `ref` (`ref`),
    KEY `status` (`status`),
    KEY `invoice_id` (`invoice_id`),
    KEY `director_email` (`director_email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

$CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}duns_order_files` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id` INT(11) UNSIGNED NOT NULL,
    `doc_type` VARCHAR(30) NOT NULL DEFAULT 'coi' COMMENT 'coi | other',
    `file_name` VARCHAR(191) NOT NULL COMMENT 'stored name on disk',
    `original_name` VARCHAR(191) NOT NULL,
    `mime` VARCHAR(100) DEFAULT NULL,
    `size` INT(11) NOT NULL DEFAULT 0,
    `uploaded_by` INT(11) DEFAULT NULL COMMENT 'staff id, NULL = customer',
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    KEY `order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

$CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}duns_order_events` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id` INT(11) UNSIGNED NOT NULL,
    `type` VARCHAR(20) NOT NULL DEFAULT 'note' COMMENT 'note | status | payment | email | system',
    `message` TEXT NOT NULL,
    `staff_id` INT(11) DEFAULT NULL,
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    KEY `order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

// ── Seed the three launch plans (only into an empty table) ──
if ((int) $CI->db->query("SELECT COUNT(*) AS c FROM `{$p}duns_plans`")->row()->c === 0) {
    $now = date('Y-m-d H:i:s');
    $CI->db->insert_batch($p . 'duns_plans', [
        [
            'name' => 'Express', 'delivery_hours' => 1, 'price' => 10000, 'compare_price' => null,
            'badge' => 'Fastest', 'is_featured' => 0, 'active' => 1, 'sort_order' => 1, 'created_at' => $now,
            'features' => "DUNS number within 1 hour\nPriority handling by a dedicated expert\nDelivered to your email\nValid for Google Play & Apple\n100% money-back guarantee",
        ],
        [
            'name' => 'Priority', 'delivery_hours' => 6, 'price' => 5000, 'compare_price' => null,
            'badge' => 'Most popular', 'is_featured' => 1, 'active' => 1, 'sort_order' => 2, 'created_at' => $now,
            'features' => "DUNS number within 6 hours\nDelivered to your email\nValid for Google Play & Apple\n100% money-back guarantee",
        ],
        [
            'name' => 'Standard', 'delivery_hours' => 12, 'price' => 2500, 'compare_price' => null,
            'badge' => 'Best value', 'is_featured' => 0, 'active' => 1, 'sort_order' => 3, 'created_at' => $now,
            'features' => "DUNS number within 12 hours\nDelivered to your email\nValid for Google Play & Apple\n100% money-back guarantee",
        ],
    ]);
}

// ── Default settings (add_option never overwrites an existing value) ──
$duns_defaults = [
    'duns_landing_enabled'  => '1',
    'duns_brand_name'       => get_option('companyname'),
    'duns_support_phone'    => '',
    'duns_support_whatsapp' => '',
    'duns_support_email'    => get_option('smtp_email'),
    'duns_notify_emails'    => get_option('smtp_email'),
    'duns_hero_title'       => 'Get your DUNS Number in as little as 1 hour',
    'duns_hero_subtitle'    => 'For Google Play Console, Apple Developer Program and every other place that asks for a D-U-N-S number. 100% valid, delivered straight to your email — or your money back.',
    'duns_meta_pixel_id'    => '',
    'duns_ga4_id'           => '',
    'duns_gads_id'          => '',
    'duns_gads_label'       => '',
    'duns_pay_enabled'      => '1',
    'duns_pay_use_master'   => '1',
    'duns_pay_gateways'     => '[]',
    'duns_email_customer'   => '1',
    'duns_terms_url'        => '',
];
foreach ($duns_defaults as $duns_k => $duns_v) {
    add_option($duns_k, $duns_v, 0);
}

// ── Private upload folder for the incorporation certificates ──
$duns_dir = function_exists('duns_upload_dir') ? duns_upload_dir() : FCPATH . 'uploads/duns_support/';
if (is_dir($duns_dir)) {
    if (!is_file($duns_dir . 'index.html')) {
        @file_put_contents($duns_dir . 'index.html', '');
    }
    if (!is_file($duns_dir . '.htaccess')) {
        @file_put_contents($duns_dir . '.htaccess', "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n");
    }
}
