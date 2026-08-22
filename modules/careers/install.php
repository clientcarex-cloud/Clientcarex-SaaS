<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Careers — schema, options and starter data.
 *
 * Everything the module owns lives in careers_* tables. Re-running this file is
 * safe: the schema is create-if-missing, options are add-if-absent (core's
 * add_option() is a no-op when the key exists) and the seeds only fire into
 * empty tables, so an existing installation keeps its own departments.
 */

careers_ensure_schema();

$CI = &get_instance();
$p  = db_prefix();

// ── Options ──
// add_option() ignores keys that already exist, so this doubles as the upgrade
// path for options introduced after the first release. get_option() returns ''
// (never false) for a missing key, so nothing here can be guarded on false.
foreach (careers_default_options() as $careers_option => $careers_value) {
    add_option($careers_option, $careers_value);
}

// The keyed website API was removed in favour of the embed endpoint; drop the
// shared secret an earlier install minted, so no dead credential is left behind.
$CI->db->where('name', 'careers_api_key')->delete(db_prefix() . 'options');

if ((string) get_option('careers_company_name') === '') {
    update_option('careers_company_name', (string) get_option('companyname'));
}

// ── Starter departments ──
// A recruiter opening the module for the first time can publish immediately;
// these are ordinary rows and can be renamed or deleted.
if ((int) $CI->db->count_all($p . 'careers_departments') === 0) {
    $careers_now = date('Y-m-d H:i:s');

    $CI->db->insert_batch($p . 'careers_departments', [
        ['name' => 'Engineering',      'slug' => 'engineering',      'description' => 'Product engineering, QA and DevOps.',            'color' => '#0d9488', 'active' => 1, 'sort_order' => 1, 'created_at' => $careers_now],
        ['name' => 'Product',          'slug' => 'product',          'description' => 'Product management, design and research.',       'color' => '#7c3aed', 'active' => 1, 'sort_order' => 2, 'created_at' => $careers_now],
        ['name' => 'Sales',            'slug' => 'sales',            'description' => 'Business development and channel sales.',        'color' => '#2563eb', 'active' => 1, 'sort_order' => 3, 'created_at' => $careers_now],
        ['name' => 'Customer Success', 'slug' => 'customer-success',  'description' => 'Implementation, onboarding and account care.',   'color' => '#0284c7', 'active' => 1, 'sort_order' => 4, 'created_at' => $careers_now],
        ['name' => 'Support',          'slug' => 'support',          'description' => 'Technical support and helpdesk.',                'color' => '#d97706', 'active' => 1, 'sort_order' => 5, 'created_at' => $careers_now],
        ['name' => 'Operations',       'slug' => 'operations',       'description' => 'Delivery, logistics and internal operations.',   'color' => '#ea580c', 'active' => 1, 'sort_order' => 6, 'created_at' => $careers_now],
        ['name' => 'Finance & HR',     'slug' => 'finance-hr',       'description' => 'Finance, people operations and administration.', 'color' => '#16a34a', 'active' => 1, 'sort_order' => 7, 'created_at' => $careers_now],
        ['name' => 'Marketing',        'slug' => 'marketing',        'description' => 'Brand, content and demand generation.',          'color' => '#db2777', 'active' => 1, 'sort_order' => 8, 'created_at' => $careers_now],
    ]);
}

// ── Starter location ──
// The company's own address, so the first posting already carries a location
// (and therefore valid JobPosting structured data on the website).
if ((int) $CI->db->count_all($p . 'careers_locations') === 0) {
    $CI->db->insert($p . 'careers_locations', [
        'name'        => (string) get_option('invoice_company_name') ?: 'Head Office',
        'city'        => (string) get_option('invoice_company_city'),
        'state'       => (string) get_option('invoice_company_state'),
        'country'     => (string) careers_opt('careers_default_country'),
        'address'     => (string) get_option('invoice_company_address'),
        'postal_code' => (string) get_option('invoice_company_postal_code'),
        'active'      => 1,
        'sort_order'  => 1,
        'created_at'  => date('Y-m-d H:i:s'),
    ]);
}

// ── Uploads directory ──
// Created up front so the first application never fails on a missing folder,
// and index.html'd so a mis-configured server cannot list stored resumes.
$careers_upload_dir = careers_upload_path();
if (!is_dir($careers_upload_dir)) {
    @mkdir($careers_upload_dir, 0755, true);
}
if (is_dir($careers_upload_dir) && !is_file($careers_upload_dir . 'index.html')) {
    @file_put_contents($careers_upload_dir . 'index.html', '');
}
