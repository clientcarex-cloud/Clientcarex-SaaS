<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Pro Tickets — extension schema + defaults.
 *
 * Core Perfex ticket tables (tbltickets, tblticket_replies, tbltickets_status,
 * tbltickets_priorities, tbldepartments, tblservices, ...) are reused as-is and
 * never altered. Everything module-specific lives in pro_tickets_* tables.
 */

pro_tickets_ensure_schema();

// ── Default options (only added when missing) ──
add_option('pro_tickets_auto_assign', 'round_robin');          // off | round_robin | least_busy
add_option('pro_tickets_auto_assign_role', '0');               // 0 = any role, else restrict to this role
add_option('pro_tickets_auto_close_enabled', '1');
add_option('pro_tickets_auto_close_days', '7');                // answered tickets idle N days → closed
add_option('pro_tickets_sla_warning_pct', '80');               // warn when N% of SLA time consumed
add_option('pro_tickets_bump_priority_on_breach', '1');        // escalate priority when SLA breached
add_option('pro_tickets_notify_watchers', '1');
add_option('pro_tickets_smart_admin_enabled', '1');    // Ctrl+Alt+N reporter on admin pages
add_option('pro_tickets_cron_last_run', '0');

// Greeting/signature template the new-ticket message box starts from.
pro_tickets_seed_default_templates();

$CI = &get_instance();
$p  = db_prefix();

// ── Seed helpdesk departments (core tbldepartments, insert-if-missing) ──
// Departments drive routing, auto-assignment scope and SLA policy matching,
// so a sensible SaaS/IT-company set ships out of the box. Existing
// departments are never touched; matching is by name (case-insensitive).
$pro_tickets_default_departments = [
    'General Support',
    'Technical Support',
    'Billing & Subscriptions',
    'Sales & Pre-Sales',
    'Onboarding & Training',
    'Customer Success',
    'Product & Feature Requests',
    'Security & Compliance',
    'Product Development',
    'Implementation',
];

foreach ($pro_tickets_default_departments as $pro_tickets_department) {
    $exists = $CI->db->query(
        "SELECT departmentid FROM `{$p}departments` WHERE LOWER(name) = LOWER(?) LIMIT 1",
        [$pro_tickets_department]
    )->row();

    if (!$exists) {
        $CI->db->insert($p . 'departments', [
            'name'           => $pro_tickets_department,
            'hidefromclient' => 0,
        ]);
    }
}

// ── Seed default SLA policies when none exist ──

if ($CI->db->count_all($p . 'pro_tickets_sla') == 0) {
    // Core default priorities: 1 Low, 2 Medium, 3 High.
    $CI->db->insert_batch($p . 'pro_tickets_sla', [
        [
            'name'            => 'Urgent — High priority',
            'department_id'   => 0,
            'priority_id'     => 3,
            'frt_minutes'     => 60,
            'res_minutes'     => 480,
            'escalate_to'     => 0,
            'active'          => 1,
            'sort_order'      => 1,
            'created_at'      => date('Y-m-d H:i:s'),
        ],
        [
            'name'            => 'Standard — Medium priority',
            'department_id'   => 0,
            'priority_id'     => 2,
            'frt_minutes'     => 240,
            'res_minutes'     => 1440,
            'escalate_to'     => 0,
            'active'          => 1,
            'sort_order'      => 2,
            'created_at'      => date('Y-m-d H:i:s'),
        ],
        [
            'name'            => 'Relaxed — Low / any priority',
            'department_id'   => 0,
            'priority_id'     => 0,
            'frt_minutes'     => 480,
            'res_minutes'     => 4320,
            'escalate_to'     => 0,
            'active'          => 1,
            'sort_order'      => 3,
            'created_at'      => date('Y-m-d H:i:s'),
        ],
    ]);
}
