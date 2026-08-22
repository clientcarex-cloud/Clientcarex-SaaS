<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Team Management
Description: A modern Team Management module for Perfex CRM.
Version: 1.0.0
Requires at least: 2.3.*
*/

define('TEAM_MODULE_NAME', 'team');

hooks()->add_action('admin_init', 'team_module_init_menu_items');
hooks()->add_action('app_admin_head', 'team_module_add_head_components');

hooks()->add_action('admin_init', 'team_module_permissions');

/**
 * Register language files, must be registered if the module is using languages
 */
register_language_files(TEAM_MODULE_NAME, [TEAM_MODULE_NAME]);

/**
 * Init team module permissions in setup in admin_init hook
 */
function team_module_permissions()
{
    $capabilities = [];

    $capabilities['capabilities'] = [
        'view'   => _l('permission_view') . '(' . _l('permission_global') . ')',
        'create' => _l('permission_create'),
        'edit'   => _l('permission_edit'),
        'delete' => _l('permission_delete'),
    ];

    register_staff_capabilities('team', $capabilities, 'Team Management');

    $capabilities_roles = [];
    $capabilities_roles['capabilities'] = [
        'view'   => _l('permission_view') . '(' . _l('permission_global') . ')',
        'create' => _l('permission_create'),
        'edit'   => _l('permission_edit'),
        'delete' => _l('permission_delete'),
    ];
    register_staff_capabilities('team_roles', $capabilities_roles, 'Team Roles');
}

/**
 * Init team module menu items in setup in admin_init hook
 * @return null
 */
function team_module_init_menu_items()
{
    $CI = &get_instance();

    if (has_permission('team', '', 'view') || has_permission('staff', '', 'view')) {
        $CI->app_menu->add_sidebar_menu_item('team', [
            'name' => 'Team',
            'href' => admin_url('team'),
            'position' => 10,
            'icon' => 'fa fa-users',
        ]);
    }
}

/**
 * Add head components
 */
function team_module_add_head_components()
{
    // Check if we are in the team module controller
    $CI = &get_instance();
    if ($CI->router->fetch_class() == 'team') {
        $version = filemtime(module_dir_path('team', 'assets/css/team.css'));
        echo '<link href="' . module_dir_url('team', 'assets/css/team.css?v=' . $version) . '" rel="stylesheet" type="text/css" />';
    }
}
