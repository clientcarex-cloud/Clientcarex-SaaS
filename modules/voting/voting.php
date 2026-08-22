<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Voting
Description: Live audience voting for webinars & events. Create a poll, share one short link — it shows the question, a QR code to scan and the live result chart, all updating in real-time while the admin drives the questions.
Version: 1.0.0
Requires at least: 2.3.*
*/

define('VOTING_MODULE_NAME', 'voting');

hooks()->add_action('admin_init', 'voting_module_init_menu_items');
hooks()->add_action('admin_init', 'voting_module_permissions');

/**
 * Register activation module hook — creates the database tables
 */
register_activation_hook(VOTING_MODULE_NAME, 'voting_module_activation_hook');

function voting_module_activation_hook()
{
    $CI = &get_instance();
    require_once(__DIR__ . '/install.php');
}

/**
 * Register language files
 */
register_language_files(VOTING_MODULE_NAME, [VOTING_MODULE_NAME]);

/**
 * Sidebar menu item
 */
function voting_module_init_menu_items()
{
    $CI = &get_instance();

    if (has_permission('voting', '', 'view') || is_admin()) {
        $CI->app_menu->add_sidebar_menu_item('voting', [
            'name'     => _l('voting'),
            'href'     => admin_url('voting'),
            'icon'     => 'fa-solid fa-square-poll-vertical',
            'position' => 30,
        ]);
    }
}

/**
 * Staff permissions
 */
function voting_module_permissions()
{
    $capabilities = [];

    $capabilities['capabilities'] = [
        'view'   => _l('permission_view') . '(' . _l('permission_global') . ')',
        'create' => _l('permission_create'),
        'edit'   => _l('permission_edit'),
        'delete' => _l('permission_delete'),
    ];

    register_staff_capabilities('voting', $capabilities, _l('voting'));
}
