<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Forms
Description: Modern smart form builder - Q&A, Yes/No, True/False, Terms acceptance, ratings & more. Shareable public links with post-submit quick feedback. Ideal for onboarding, process implementation and acknowledgements.
Version: 1.0.0
Requires at least: 2.3.*
*/

define('SMART_FORMS_MODULE_NAME', 'smart_forms');

hooks()->add_action('admin_init', 'smart_forms_module_init_menu_items');
hooks()->add_action('admin_init', 'smart_forms_module_permissions');

/**
 * Register activation module hook — creates the database tables
 */
register_activation_hook(SMART_FORMS_MODULE_NAME, 'smart_forms_module_activation_hook');

function smart_forms_module_activation_hook()
{
    $CI = &get_instance();
    require_once(__DIR__ . '/install.php');
}

/**
 * Register language files
 */
register_language_files(SMART_FORMS_MODULE_NAME, [SMART_FORMS_MODULE_NAME]);

/**
 * Sidebar menu item
 */
function smart_forms_module_init_menu_items()
{
    $CI = &get_instance();

    if (has_permission('smart_forms', '', 'view') || is_admin()) {
        $CI->app_menu->add_sidebar_menu_item('smart_forms', [
            'name'     => _l('smart_forms'),
            'href'     => admin_url('smart_forms'),
            'icon'     => 'fa-brands fa-wpforms',
            'position' => 29,
        ]);
    }
}

/**
 * Staff permissions
 */
function smart_forms_module_permissions()
{
    $capabilities = [];

    $capabilities['capabilities'] = [
        'view'   => _l('permission_view') . '(' . _l('permission_global') . ')',
        'create' => _l('permission_create'),
        'edit'   => _l('permission_edit'),
        'delete' => _l('permission_delete'),
    ];

    register_staff_capabilities('smart_forms', $capabilities, _l('smart_forms'));
}
