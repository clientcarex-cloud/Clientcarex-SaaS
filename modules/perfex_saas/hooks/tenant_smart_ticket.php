<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Tenant-side "Smart Ticket" capture launcher (admin panel).
 *
 * Lets tenant staff report an issue to the SaaS provider's Customer Success
 * helpdesk from ANYWHERE in their CRM admin (keyboard shortcut + floating
 * button), capturing an annotated screenshot.
 *
 * Placed in perfex_saas/hooks/ so it auto-loads on every instance regardless of
 * whether the pro_tickets module is activated on the tenant — that module is
 * the provider's helpdesk and is typically NOT active on a tenant's own CRM,
 * which is exactly why the previous pro_tickets-hosted injector never fired on
 * tenant pages like /admin/doctors.
 *
 * The widget assets + loader live in the pro_tickets module (shared codebase);
 * we reference them by path/URL and submit cross-DB to the master helpdesk via
 * Companies::tenant_smart_ticket_submit (route admin/billing/smart_ticket_submit).
 */

// Tenant instances only — a provider / standalone admin has no upstream desk.
if (!$is_tenant) {
    return;
}

hooks()->add_action('app_admin_footer', function () {

    if (!function_exists('perfex_saas_is_tenant') || !perfex_saas_is_tenant()) {
        return;
    }
    if (!function_exists('is_staff_logged_in') || !is_staff_logged_in()) {
        return;
    }
    if (!function_exists('perfex_saas_raw_query')) {
        return;
    }

    // The submit endpoint is only reachable (middleware-exempt) when the client
    // bridge / instance switch is enabled, so don't show a widget that can't
    // submit — this matches the gating on the tenant Support integration.
    if (function_exists('perfex_saas_tenant_is_enabled')
        && !perfex_saas_tenant_is_enabled('client_bridge')
        && !perfex_saas_tenant_is_enabled('instance_switch')) {
        return;
    }

    $loader = FCPATH . 'modules/pro_tickets/views/clients/_smart_ticket_loader.php';
    if (!is_file($loader)) {
        return; // pro_tickets assets not present on this instance
    }

    $CI = &get_instance();

    // The widget's UI strings ship with pro_tickets; load them explicitly since
    // that module's language file isn't auto-registered when it's inactive.
    $lang_path = FCPATH . 'modules/pro_tickets/';
    if (is_file($lang_path . 'language/english/pro_tickets_lang.php')) {
        $CI->lang->load('pro_tickets', 'english', false, true, $lang_path);
    }

    $prefix = function_exists('perfex_saas_master_db_prefix') ? perfex_saas_master_db_prefix() : 'tbl';

    // Provider (master) departments + priorities for the picker.
    $pst_departments = [];
    $pst_priorities  = [];
    try {
        $rows = perfex_saas_raw_query(
            "SELECT departmentid, name FROM {$prefix}departments WHERE hidefromclient = 0 ORDER BY name",
            [], true
        );
        foreach (($rows ?: []) as $r) {
            $pst_departments[] = ['id' => (int) $r->departmentid, 'name' => $r->name];
        }

        $prows = perfex_saas_raw_query(
            "SELECT priorityid, name FROM {$prefix}tickets_priorities ORDER BY priorityid",
            [], true
        );
        foreach (($prows ?: []) as $r) {
            $pst_priorities[] = ['id' => (int) $r->priorityid, 'name' => $r->name];
        }
    } catch (\Throwable $e) {
        log_message('error', 'Tenant Smart Ticket (master lookup) failed: ' . $e->getMessage());
        return;
    }

    // No provider departments configured → nothing to route to; stay silent.
    if (empty($pst_departments)) {
        return;
    }

    $pst_url              = admin_url('billing/smart_ticket_submit');
    $pst_image_format     = 'image/jpeg';
    $pst_image_quality    = 0.72;
    $pst_fab_position     = 'left'; // clear the SaaS support chat button (bottom-right)
    $pst_default_priority = hooks()->apply_filters('new_ticket_priority_selected', 2);

    include $loader;
});
