<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: ePTW
Description: Electronic Permit to Work — digitises the full permit lifecycle for hazardous work: 17 permit templates, permit numbering, approval workflow with e-signatures, the permit register, extensions, suspensions, closure, document archiving, SIMOPS conflict detection, hazard suggestions, dashboards and reports.
Version: 1.1.0
Requires at least: 2.3.*
*/

define('EPTW_MODULE_NAME', 'eptw');
define('EPTW_MODULE_VERSION', '1.1.0');

require_once __DIR__ . '/helpers/eptw_helper.php';

register_language_files(EPTW_MODULE_NAME, [EPTW_MODULE_NAME]);

/* ─────────────────────────── Install / upgrade ──────────────────────────── */

register_activation_hook(EPTW_MODULE_NAME, 'eptw_module_activation_hook');

function eptw_module_activation_hook()
{
    require(__DIR__ . '/install.php');
}

function eptw_maybe_upgrade_schema()
{
    if (get_option('eptw_schema_version') === EPTW_MODULE_VERSION) {
        return;
    }

    require(__DIR__ . '/install.php');
    update_option('eptw_schema_version', EPTW_MODULE_VERSION);
}

/* ──────────────────────────── Admin UI hooks ────────────────────────────── */

hooks()->add_action('admin_init', 'eptw_module_init_menu_items');
hooks()->add_action('app_admin_head', 'eptw_add_head_components');
hooks()->add_action('app_admin_footer', 'eptw_add_footer_components');
hooks()->add_filter('module_eptw_action_links', 'eptw_module_action_links');
hooks()->add_action('after_cron_run', 'eptw_cron');
// Late priority so the entries stay gone even if another module rebuilds the menu array.
hooks()->add_filter('sidebar_menu_items', 'eptw_hide_core_menu_items', 99999);
hooks()->add_action('admin_init', 'eptw_default_landing');

function eptw_module_init_menu_items()
{
    eptw_maybe_upgrade_schema();
    eptw_register_permissions();

    $home = eptw_home_url();
    if ($home === null) {
        return;
    }

    $CI = &get_instance();

    $CI->app_menu->add_sidebar_menu_item('eptw', [
        'name'     => 'ePTW',
        'href'     => $home,
        'icon'     => 'fa-solid fa-file-shield',
        'position' => 8,
    ]);

    $children = [
        'eptw-dashboard' => [eptw_perm('eptw_dashboard'), 'Dashboard', 'eptw'],
        'eptw-register'  => [eptw_perm('eptw_register'), 'Permit register', 'eptw/register'],
        'eptw-new'       => [eptw_can('create'), 'New permit', 'eptw/permit'],
        'eptw-approvals' => [eptw_perm('eptw_approvals'), 'Pending approvals', 'eptw/register?view=pending'],
        'eptw-reports'   => [eptw_can('reports'), 'Reports', 'eptw/reports'],
        'eptw-setup'     => [eptw_can('setup'), 'Setup', eptw_setup_url()],
    ];
    $position = 0;
    foreach ($children as $slug => $child) {
        $position++;
        if (!$child[0]) {
            continue;
        }
        $CI->app_menu->add_sidebar_children_item('eptw', [
            'slug'     => $slug,
            'name'     => $child[1],
            'href'     => admin_url($child[2]),
            'position' => $position,
        ]);
    }
}

/** First setup screen the current staff member may open. */
function eptw_setup_url()
{
    $features = eptw_setup_features();

    return count($features) ? eptw_menu_permissions()[$features[0]]['url'] : 'eptw/eptw_setup';
}

/**
 * Menu-wise entries in Staff → Permissions (and Roles), one per ePTW menu.
 * Perfex administrators hold all of them implicitly.
 */
function eptw_register_permissions()
{
    foreach (eptw_menu_permissions() as $feature => $menu) {
        register_staff_capabilities($feature, ['capabilities' => $menu['caps']], 'ePTW — ' . $menu['name']);
    }
}

/**
 * An ePTW site runs on contractors and projects, not CRM customers, so the
 * Customers entry is dropped from the sidebar, and the core Dashboard entry
 * with it — the ePTW dashboard is the home screen (see eptw_default_landing).
 *
 * Both are hidden only for staff who may open the ePTW dashboard, so anyone
 * else keeps a working home link. Nothing is blocked:
 * admin/clients stays reachable so links into a client record still work, and
 * the core dashboard stays reachable at admin/dashboard?core=1.
 */
function eptw_hide_core_menu_items($items)
{
    if (!eptw_perm('eptw_dashboard')) {
        return $items;
    }

    foreach (['customers', 'dashboard'] as $slug) {
        if (isset($items[$slug])) {
            unset($items[$slug]);
            continue;
        }

        // Another module may have re-keyed the array; fall back to the slug attribute.
        foreach ($items as $key => $item) {
            if (isset($item['slug']) && $item['slug'] === $slug) {
                unset($items[$key]);
            }
        }
    }

    return $items;
}

/**
 * The permit desk is the home screen: the core dashboard (admin/) sends staff
 * who may open the ePTW dashboard straight to it. Any other page is
 * untouched, and the core dashboard stays reachable at admin/dashboard?core=1.
 */
function eptw_default_landing()
{
    $CI = &get_instance();

    if ($CI->router->fetch_class() !== 'dashboard' || $CI->router->fetch_method() !== 'index') {
        return;
    }

    if ($CI->input->get('core') || $CI->input->is_ajax_request() || !eptw_perm('eptw_dashboard')) {
        return;
    }

    redirect(admin_url('eptw'));
}

function eptw_module_action_links($actions)
{
    $actions[] = '<a href="' . admin_url('eptw') . '">Dashboard</a>';
    $actions[] = '<a href="' . admin_url('eptw/eptw_setup') . '">Setup</a>';

    return $actions;
}

/* ───────────────────────────────── Assets ───────────────────────────────── */

function eptw_is_module_page()
{
    return in_array(get_instance()->router->fetch_class(), ['eptw', 'eptw_setup'], true);
}

function eptw_asset_ver($relative)
{
    $path = module_dir_path(EPTW_MODULE_NAME, $relative);

    return EPTW_MODULE_VERSION . '.' . (is_file($path) ? filemtime($path) : 0);
}

function eptw_add_head_components()
{
    if (!eptw_is_module_page()) {
        return;
    }

    echo '<link href="' . module_dir_url(EPTW_MODULE_NAME, 'assets/css/eptw.css')
        . '?v=' . eptw_asset_ver('assets/css/eptw.css') . '" rel="stylesheet" type="text/css" />';
}

function eptw_add_footer_components()
{
    if (!eptw_is_module_page()) {
        return;
    }

    echo '<script src="' . base_url('assets/plugins/Chart.js/Chart.bundle.min.js') . '"></script>';
    echo '<script src="' . base_url('assets/plugins/signature-pad/signature_pad.min.js') . '"></script>';
    echo '<script src="' . module_dir_url(EPTW_MODULE_NAME, 'assets/js/eptw.js')
        . '?v=' . eptw_asset_ver('assets/js/eptw.js') . '"></script>';
}

/* ────────────────────────────────── Cron ────────────────────────────────── */

/**
 * Housekeeping that must not wait for a human: start permits whose window
 * has opened, warn about permits expiring soon, flag the ones that expired
 * while still active, and tidy old drafts.
 */
function eptw_cron()
{
    $CI = &get_instance();
    if (!$CI->db->table_exists(db_prefix() . 'eptw_permits')) {
        return;
    }

    $last = (string) get_option('eptw_last_cron');
    if ($last !== '' && (time() - strtotime($last)) < 300) {
        return;
    }
    update_option('eptw_last_cron', date('Y-m-d H:i:s'));

    $CI->load->model('eptw/eptw_permits_model');

    try {
        $CI->eptw_permits_model->cron_pass();
    } catch (Throwable $e) {
        log_activity('ePTW cron error: ' . $e->getMessage());
    }
}
