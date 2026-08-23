<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: SHRA
Description: Stallion Horse Riding Academy — rider self-registration via QR, premium membership & course-completion PDFs, one-screen package billing and trainer attendance.
Version: 1.2.0
Requires at least: 2.3.*
*/

define('SHRA_MODULE_NAME', 'shra');
define('SHRA_MODULE_VERSION', '1.2.0');

register_language_files(SHRA_MODULE_NAME, [SHRA_MODULE_NAME]);

hooks()->add_action('admin_init', 'shra_module_init_menu_items');
hooks()->add_action('admin_init', 'shra_module_permissions');
hooks()->add_action('app_admin_head', 'shra_add_head_components');
hooks()->add_action('app_admin_footer', 'shra_add_footer_components');
hooks()->add_filter('module_shra_action_links', 'shra_module_action_links');
hooks()->add_filter('sidebar_menu_items', 'shra_hide_customers_menu');
hooks()->add_action('admin_init', 'shra_default_landing');

register_activation_hook(SHRA_MODULE_NAME, 'shra_module_activation_hook');

function shra_module_activation_hook()
{
    require_once(__DIR__ . '/install.php');
}

$CI = &get_instance();
$CI->load->helper(SHRA_MODULE_NAME . '/shra');

/* ───────────────────────────── Access ────────────────────────────────── */

function shra_can($capability = 'view')
{
    if (is_admin()) {
        return true;
    }

    return has_permission('shra', '', $capability);
}

/* Trainers: attendance capability OR any module capability */
function shra_can_attendance()
{
    return is_admin() || has_permission('shra', '', 'attendance') || has_permission('shra', '', 'view');
}

function shra_can_billing()
{
    return is_admin() || has_permission('shra', '', 'billing');
}

function shra_can_access()
{
    return is_admin()
        || has_permission('shra', '', 'view')
        || has_permission('shra', '', 'billing')
        || has_permission('shra', '', 'attendance');
}

/* ───────────────────────────── Menu ──────────────────────────────────── */

function shra_module_init_menu_items()
{
    shra_maybe_upgrade_schema();

    if (!shra_can_access()) {
        return;
    }

    $CI = &get_instance();

    $CI->app_menu->add_sidebar_menu_item('shra', [
        'name'     => _l('shra'),
        'href'     => admin_url('shra'),
        'icon'     => 'fa-solid fa-horse-head',
        'position' => 1,
    ]);

    // Single sidebar entry — the module's own tab bar handles in-page navigation.
}

/**
 * Riders are managed inside SHRA (each rider is still a CRM customer behind
 * the scenes), so the core "Customers" menu only duplicates the Riders tab.
 */
function shra_hide_customers_menu($items)
{
    unset($items['customers']);
    // SHRA is the home screen (see shra_default_landing), so the core Dashboard entry is redundant
    unset($items['dashboard']);

    return $items;
}

/**
 * The academy desk is the home screen: the core dashboard (admin/) sends
 * staff who can use SHRA straight to the module. Any other page is untouched,
 * and the core dashboard stays reachable at admin/dashboard?core=1.
 */
function shra_default_landing()
{
    $CI = &get_instance();
    if ($CI->router->fetch_class() !== 'dashboard' || $CI->router->fetch_method() !== 'index') {
        return;
    }
    if ($CI->input->get('core') || $CI->input->is_ajax_request() || !shra_can_access()) {
        return;
    }
    redirect(admin_url('shra'));
}

/* ───────────────────────────── Permissions ───────────────────────────── */

function shra_module_permissions()
{
    $capabilities = [];

    $capabilities['capabilities'] = [
        'view'       => _l('permission_view') . '(' . _l('permission_global') . ')',
        'create'     => _l('permission_create'),
        'edit'       => _l('permission_edit'),
        'delete'     => _l('permission_delete'),
        'billing'    => _l('shra_permission_billing'),
        'attendance' => _l('shra_permission_attendance'),
    ];

    register_staff_capabilities('shra', $capabilities, _l('shra'));
}

function shra_module_action_links($actions)
{
    $actions[] = '<a href="' . admin_url('shra') . '">' . _l('shra_dashboard') . '</a>';
    $actions[] = '<a href="' . admin_url('shra/settings') . '">' . _l('shra_settings') . '</a>';

    return $actions;
}

/* ───────────────────────────── Assets ────────────────────────────────── */

function shra_is_module_page()
{
    return get_instance()->router->fetch_class() === 'shra';
}

function shra_asset_ver($relative)
{
    $path = module_dir_path(SHRA_MODULE_NAME, $relative);

    return SHRA_MODULE_VERSION . '.' . (is_file($path) ? filemtime($path) : 0);
}

function shra_add_head_components()
{
    if (!shra_is_module_page()) {
        return;
    }

    echo '<link rel="preconnect" href="https://fonts.googleapis.com">';
    echo '<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">';
    echo '<link href="' . module_dir_url(SHRA_MODULE_NAME, 'assets/css/shra.css') . '?v=' . shra_asset_ver('assets/css/shra.css') . '" rel="stylesheet" type="text/css" />';
}

function shra_add_footer_components()
{
    if (!shra_is_module_page()) {
        return;
    }

    echo '<script src="' . module_dir_url(SHRA_MODULE_NAME, 'assets/js/shra.js') . '?v=' . shra_asset_ver('assets/js/shra.js') . '"></script>';
}

/* ───────────────────────────── Schema self-heal ──────────────────────── */

function shra_maybe_upgrade_schema()
{
    if (get_option('shra_schema_version') === SHRA_MODULE_VERSION) {
        return;
    }

    require_once(__DIR__ . '/install.php');
    update_option('shra_schema_version', SHRA_MODULE_VERSION);
}
