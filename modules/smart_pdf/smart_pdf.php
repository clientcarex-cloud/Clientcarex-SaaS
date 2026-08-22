<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Smart PDF
Description: Import HTML templates with {{tags}} and generate dynamic PDF / Print documents (forms, brochures, letters, etc). Smart auto-fill tags, patient integration, live preview, generation history and audit numbering.
Version: 2.0.0
Requires at least: 2.3.*
*/

define('SMART_PDF_MODULE_NAME', 'smart_pdf');

hooks()->add_action('admin_init', 'smart_pdf_module_init_menu_items');
hooks()->add_action('admin_init', 'smart_pdf_permissions');

/**
 * Register activation module hook
 */
register_activation_hook(SMART_PDF_MODULE_NAME, 'smart_pdf_module_activation_hook');

function smart_pdf_module_activation_hook()
{
    $CI = &get_instance();
    require_once(__DIR__ . '/install.php');
}

/**
 * Register language files, must be registered if the module is using languages
 */
register_language_files(SMART_PDF_MODULE_NAME, [SMART_PDF_MODULE_NAME]);

/**
 * Init module menu items in admin_init hook
 */
function smart_pdf_module_init_menu_items()
{
    $CI = &get_instance();

    if (has_permission('smart_pdf', '', 'view')) {
        $CI->app_menu->add_sidebar_menu_item('smart_pdf', [
            'name'     => _l('smart_pdf'),
            'href'     => admin_url('smart_pdf'),
            'icon'     => 'fa-regular fa-file-pdf',
            'position' => 30,
        ]);
    }
}

/**
 * Init module permissions in admin_init hook
 */
function smart_pdf_permissions()
{
    $capabilities = [];

    $capabilities['capabilities'] = [
        'view'     => _l('permission_view') . '(' . _l('permission_global') . ')',
        'create'   => _l('permission_create'),
        'edit'     => _l('permission_edit'),
        'delete'   => _l('permission_delete'),
        'generate' => _l('smart_pdf_perm_generate'),
    ];

    register_staff_capabilities('smart_pdf', $capabilities, _l('smart_pdf'));
}
