<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: ePTW
Description: Electronic Permit to Work — digitises the full permit lifecycle for hazardous work: 17 permit templates, permit numbering, approval workflow with e-signatures, the permit register, extensions, suspensions, closure, document archiving, SIMOPS conflict detection, hazard suggestions, dashboards and reports.
Version: 1.0.0
Requires at least: 2.3.*
*/

define('EPTW_MODULE_NAME', 'eptw');
define('EPTW_MODULE_VERSION', '1.0.0');

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

function eptw_module_init_menu_items()
{
    eptw_maybe_upgrade_schema();

    if (!eptw_can_access()) {
        return;
    }

    $CI = &get_instance();

    $CI->app_menu->add_sidebar_menu_item('eptw', [
        'name'     => 'ePTW',
        'href'     => admin_url('eptw'),
        'icon'     => 'fa-solid fa-file-shield',
        'position' => 8,
    ]);

    $CI->app_menu->add_sidebar_children_item('eptw', [
        'slug'     => 'eptw-dashboard',
        'name'     => 'Dashboard',
        'href'     => admin_url('eptw'),
        'position' => 1,
    ]);
    $CI->app_menu->add_sidebar_children_item('eptw', [
        'slug'     => 'eptw-register',
        'name'     => 'Permit register',
        'href'     => admin_url('eptw/register'),
        'position' => 2,
    ]);
    if (eptw_can('create')) {
        $CI->app_menu->add_sidebar_children_item('eptw', [
            'slug'     => 'eptw-new',
            'name'     => 'New permit',
            'href'     => admin_url('eptw/permit'),
            'position' => 3,
        ]);
    }
    if (eptw_can('review') || eptw_can('issue')) {
        $CI->app_menu->add_sidebar_children_item('eptw', [
            'slug'     => 'eptw-approvals',
            'name'     => 'Pending approvals',
            'href'     => admin_url('eptw/register?view=pending'),
            'position' => 4,
        ]);
    }
    if (eptw_can('reports')) {
        $CI->app_menu->add_sidebar_children_item('eptw', [
            'slug'     => 'eptw-reports',
            'name'     => 'Reports',
            'href'     => admin_url('eptw/reports'),
            'position' => 5,
        ]);
    }
    if (eptw_can('setup')) {
        $CI->app_menu->add_sidebar_children_item('eptw', [
            'slug'     => 'eptw-setup',
            'name'     => 'Setup',
            'href'     => admin_url('eptw/eptw_setup'),
            'position' => 6,
        ]);
    }
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
