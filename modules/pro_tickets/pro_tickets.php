<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Pro Tickets
Description: Modern, fully automated corporate helpdesk built on the native CRM ticket engine — SLA policies with breach escalation, smart auto-assignment, Kanban board, live dashboard, internal notes, watchers, activity timeline and auto-close, all on top of the existing tickets tables.
Version: 1.0.0
Requires at least: 2.3.*
*/

define('PRO_TICKETS_MODULE_NAME', 'pro_tickets');
define('PRO_TICKETS_MODULE_VERSION', '1.0.0');

// Plain-function helpers (SLA engine, automation, activity log) are needed from
// admin pages, the customer portal, email piping and cron alike, so load them
// unconditionally rather than via $CI->load->helper on module pages only.
require_once __DIR__ . '/helpers/pro_tickets_helper.php';

/**
 * Activation — create the extension tables and seed defaults.
 * Core ticket tables (tbltickets, tblticket_replies, ...) are never touched.
 */
register_activation_hook(PRO_TICKETS_MODULE_NAME, 'pro_tickets_activation_hook');
function pro_tickets_activation_hook()
{
    require_once __DIR__ . '/install.php';
}

register_uninstall_hook(PRO_TICKETS_MODULE_NAME, 'pro_tickets_uninstall_hook');
function pro_tickets_uninstall_hook()
{
    require_once __DIR__ . '/uninstall.php';
}

register_language_files(PRO_TICKETS_MODULE_NAME, [PRO_TICKETS_MODULE_NAME]);

/* ─────────────────────────── Admin UI hooks ─────────────────────────────── */

hooks()->add_action('admin_init', 'pro_tickets_module_init_menu_items');
hooks()->add_action('admin_init', 'pro_tickets_module_permissions');
hooks()->add_action('app_admin_head', 'pro_tickets_add_head_components');
hooks()->add_action('app_admin_footer', 'pro_tickets_add_footer_components');

// Real-time "new customer reply" toast notifications, injected on every admin
// page so staff are alerted wherever they are.
hooks()->add_action('app_admin_footer', 'pro_tickets_inject_reply_notifier');

// Smart Ticket launcher for the provider's OWN staff — the keyboard shortcut
// (Ctrl+Alt+N · ⌥⇧N on Mac) works on every master admin page so an issue in any CRM
// module can be captured, annotated and filed as an internal ticket without
// leaving the screen it happened on.
hooks()->add_action('app_admin_footer', 'pro_tickets_inject_smart_ticket_admin');

// NOTE: the TENANT admin-panel Smart Ticket launcher (report an issue to the
// SaaS provider from anywhere in the tenant CRM) is intentionally NOT wired
// here — it lives in modules/perfex_saas/hooks/tenant_smart_ticket.php because
// this module is usually inactive on a tenant's own CRM, so a hook registered
// here would never load on tenant admin pages. The injector above skips tenant
// instances for exactly that reason, so the two never collide.

/* ─────────────────────── Client portal (customer area) ──────────────────── */
// Repoint the customer-area "Support" nav to the modern Pro Tickets portal so
// the whole client-facing ticket experience matches the admin helpdesk.
hooks()->add_filter('theme_menu_items', 'pro_tickets_client_theme_menu');

// Smart Ticket capture widget — inject on EVERY customer-area page so the
// keyboard shortcut (Ctrl+Alt+N · ⌥⇧N on Mac) and the floating launcher work portal-wide,
// not only on the Pro Tickets pages.
hooks()->add_action('app_customers_footer', 'pro_tickets_smart_ticket_customers_footer');

/* ────────────────────────── Automation hooks ────────────────────────────── */
/*
 * Every ticket entering the system — admin area, customer portal, email
 * piping, lead conversion — flows through these core hooks, so the SLA and
 * automation engine covers all channels with zero configuration.
 */

hooks()->add_action('ticket_created', 'pro_tickets_on_ticket_created');
hooks()->add_action('after_ticket_reply_added', 'pro_tickets_on_reply_added');
hooks()->add_action('after_ticket_status_changed', 'pro_tickets_on_status_changed');
hooks()->add_action('after_ticket_deleted', 'pro_tickets_on_ticket_deleted');

// Piggy-back the core cron: SLA warnings/breaches, escalation, auto-assign
// backfill and auto-close run on every cron tick (internally throttled).
hooks()->add_action('after_cron_run', 'pro_tickets_run_automation');

/**
 * Staff capabilities.
 */
function pro_tickets_module_permissions()
{
    $capabilities = [];
    $capabilities['capabilities'] = [
        'view'     => _l('permission_view') . '(' . _l('permission_global') . ')',
        'create'   => _l('permission_create'),
        'edit'     => _l('permission_edit'),
        'delete'   => _l('permission_delete'),
        'settings' => _l('pro_tickets_perm_settings'),
    ];
    register_staff_capabilities(PRO_TICKETS_MODULE_NAME, $capabilities, _l('pro_tickets'));
}

/**
 * Sidebar menu.
 */
function pro_tickets_module_init_menu_items()
{
    if (!pro_tickets_staff_can_access()) {
        return;
    }

    $CI = &get_instance();
    $CI->app_menu->add_sidebar_menu_item('pro-tickets-module', [
        'name'     => _l('pro_tickets'),
        'icon'     => 'fa-solid fa-headset',
        'href'     => admin_url('pro_tickets'),
        'position' => 27,
    ]);
}

/**
 * Repoint the customer-area "Support" menu item to the modern Pro Tickets
 * client portal (clients/pro_tickets) instead of the legacy clients/tickets.
 * Runs only in the client theme, so the admin area is unaffected.
 *
 * @param  array $items theme menu items keyed by slug
 * @return array
 */
function pro_tickets_client_theme_menu($items)
{
    if (isset($items['support'])) {
        $items['support']['href'] = site_url('clients/pro_tickets');
    }

    return $items;
}

/**
 * Detect whether the current request targets this module's admin controller.
 * Prefix-agnostic — the master admin panel can be served under a custom
 * security prefix (same approach as pro_analytics / meta modules).
 */
function pro_tickets_is_module_page()
{
    $CI = &get_instance();

    if (isset($CI->router) && method_exists($CI->router, 'fetch_class')
        && $CI->router->fetch_class() === PRO_TICKETS_MODULE_NAME) {
        return true;
    }

    return (bool) preg_match('#(^|/)admin/' . PRO_TICKETS_MODULE_NAME . '(/|$)#', $CI->uri->uri_string());
}

/**
 * CSS in <head> on module pages.
 */
function pro_tickets_add_head_components()
{
    if (pro_tickets_is_module_page()) {
        $css = __DIR__ . '/assets/css/pro_tickets.css';
        $v   = 'v1.' . (file_exists($css) ? filemtime($css) : time());
        echo '<link href="' . module_dir_url(PRO_TICKETS_MODULE_NAME, 'assets/css/pro_tickets.css') . '?' . $v . '" rel="stylesheet" type="text/css" />';
    }
}

/**
 * JS in footer on module pages.
 */
function pro_tickets_add_footer_components()
{
    if (pro_tickets_is_module_page()) {
        $js = __DIR__ . '/assets/js/pro_tickets.js';
        $v  = 'v1.' . (file_exists($js) ? filemtime($js) : time());
        echo '<script src="' . module_dir_url(PRO_TICKETS_MODULE_NAME, 'assets/js/pro_tickets.js') . '?' . $v . '"></script>';
    }
}

/**
 * Inject the real-time customer-reply toast notifier on admin pages.
 *
 * Runs on the provider / standalone side only — on SaaS tenant instances the
 * perfex_saas "Customer Success" notifier already covers tenant support, so we
 * skip here to avoid a duplicate notification stack.
 */
function pro_tickets_inject_reply_notifier()
{
    if (function_exists('perfex_saas_is_tenant') && perfex_saas_is_tenant()) {
        return;
    }
    if (!function_exists('pro_tickets_staff_can_access') || !pro_tickets_staff_can_access()) {
        return;
    }

    $initial       = pro_tickets_staff_unread_reply_count();
    $initial_items = pro_tickets_staff_unread_reply_items(6);
    $endpoint      = admin_url('pro_tickets/unread_replies');
    $ticket_base   = admin_url('pro_tickets/ticket/');

    include __DIR__ . '/views/_notify_script.php';
}

/**
 * Inject the Smart Ticket capture widget on every customer-area page.
 *
 * Loads only for logged-in contacts that can open support tickets, so the
 * keyboard shortcut + floating launcher follow the customer everywhere in the
 * portal. Departments/priorities are fetched fresh here (the globally-shared
 * view vars from App_clients_area_constructor aren't in scope inside a hook).
 */
function pro_tickets_smart_ticket_customers_footer()
{
    if (!function_exists('is_client_logged_in') || !is_client_logged_in()) {
        return;
    }
    if (!function_exists('has_contact_permission') || !has_contact_permission('support')) {
        return;
    }

    $CI = &get_instance();
    $CI->load->model('departments_model');
    $CI->load->model('tickets_model');

    $pst_departments = [];
    foreach ($CI->departments_model->get(false, true) as $d) {
        $pst_departments[] = ['id' => $d['departmentid'], 'name' => $d['name']];
    }

    $pst_priorities = [];
    foreach ($CI->tickets_model->get_priority() as $p) {
        $pst_priorities[] = ['id' => $p['priorityid'], 'name' => ticket_priority_translate($p['priorityid'])];
    }

    $pst_default_priority = hooks()->apply_filters('new_ticket_priority_selected', 2);

    include __DIR__ . '/views/clients/_smart_ticket_loader.php';
}

/**
 * Inject the Smart Ticket capture widget on every MASTER admin page.
 *
 * Gives the provider's own staff the same shortcut-driven reporter the tenants
 * and customers get, so a bug in any CRM module can be filed straight from the
 * page it happened on. Submits to admin/pro_tickets/smart_create, which files
 * the report as an internal ticket in this helpdesk.
 *
 * Skipped on SaaS tenant instances — modules/perfex_saas/hooks/
 * tenant_smart_ticket.php owns that side and routes cross-DB to the provider.
 */
function pro_tickets_inject_smart_ticket_admin()
{
    if (!pro_tickets_smart_admin_available()) {
        return;
    }

    $CI = &get_instance();
    $CI->load->model('departments_model');
    $CI->load->model('tickets_model');

    // Every department is offered here (unlike the customer/tenant paths, which
    // hide client-invisible desks): an internal report is routed by whoever
    // owns the broken module, and "Product Development" is typically hidden
    // from clients.
    $pst_departments = [];
    foreach ($CI->departments_model->get() as $d) {
        $pst_departments[] = ['id' => (int) $d['departmentid'], 'name' => $d['name']];
    }

    // No desks configured → nothing to route an internal report to.
    if (empty($pst_departments)) {
        return;
    }

    usort($pst_departments, function ($a, $b) {
        return strcasecmp($a['name'], $b['name']);
    });

    $pst_priorities = [];
    foreach ($CI->tickets_model->get_priority() as $p) {
        $pst_priorities[] = ['id' => (int) $p['priorityid'], 'name' => ticket_priority_translate($p['priorityid'])];
    }

    // Internal reports are module defects far more often than support requests,
    // so preselect the product desk, then the technical desk. Left empty when
    // neither exists, which lets the picker default to its first option.
    $pst_default_department = '';
    foreach (['product development', 'technical support'] as $preferred) {
        foreach ($pst_departments as $d) {
            if (stripos($d['name'], $preferred) !== false) {
                $pst_default_department = $d['id'];
                break 2;
            }
        }
    }

    $pst_url              = admin_url('pro_tickets/smart_create');
    $pst_image_format     = 'image/jpeg';   // keep the upload well inside PHP limits
    $pst_image_quality    = 0.72;
    $pst_fab_position     = 'left';
    $pst_default_priority = hooks()->apply_filters('new_ticket_priority_selected', 2);

    include __DIR__ . '/views/clients/_smart_ticket_loader.php';
}

/* ─────────────────────── Admin dashboard widget ─────────────────────────── */

hooks()->add_action('after_dashboard', 'pro_tickets_dashboard_widget', 6);

/**
 * Helpdesk widget on the main admin dashboard: SLA breach / at-risk alerts,
 * opened-vs-solved trend, status distribution and a "resolve next" queue of
 * the tickets closest to (or past) their SLA deadline.
 */
function pro_tickets_dashboard_widget()
{
    if (!pro_tickets_staff_can_access()) {
        return;
    }

    $CI = &get_instance();

    // Never let a widget failure blank the whole admin dashboard.
    try {
        $p = db_prefix();
        pro_tickets_ensure_schema();

        $CI->load->model('pro_tickets/pro_tickets_model');
        $dash = $CI->pro_tickets_model->get_dashboard();

        $staff_id = (int) get_staff_user_id();
        $my_open  = (int) ($CI->db->query(
            "SELECT COUNT(*) AS cnt FROM `{$p}tickets`
             WHERE assigned = ? AND status != 5 AND merged_ticket_id IS NULL",
            [$staff_id]
        )->row()->cnt ?? 0);

        // Tickets to resolve next: open, SLA breached or at risk, closest
        // deadline first.
        $attention = $CI->db->query(
            "SELECT t.ticketid, t.subject, t.status, t.assigned,
                    m.sla_id, m.frt_due, m.res_due, m.first_replied_at, m.resolved_at,
                    m.frt_breached, m.res_breached, m.sla_warned, m.created_at,
                    st.name AS status_name, st.statuscolor,
                    CONCAT(s.firstname, ' ', s.lastname) AS assignee
             FROM `{$p}tickets` t
             JOIN `{$p}pro_tickets_meta` m ON m.ticket_id = t.ticketid
             LEFT JOIN `{$p}tickets_status` st ON st.ticketstatusid = t.status
             LEFT JOIN `{$p}staff` s ON s.staffid = t.assigned
             WHERE t.status != 5 AND t.merged_ticket_id IS NULL
               AND (m.frt_breached = 1 OR m.res_breached = 1 OR m.sla_warned = 1)
             ORDER BY COALESCE(m.res_due, m.frt_due) ASC
             LIMIT 7"
        )->result();

        $CI->load->view('pro_tickets/dashboard_widget', [
            'dash'      => $dash,
            'my_open'   => $my_open,
            'attention' => $attention,
        ]);
    } catch (Throwable $e) {
        log_activity('Pro Tickets dashboard widget failed to render [' . $e->getMessage() . ']');
    }
}
