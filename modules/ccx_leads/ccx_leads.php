<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: CCX Leads
Description: A modern leads management system (Perfex Leads Clone with Modern UI)
Version: 1.0.0
Requires at least: 2.3.*
*/

define('CCX_LEADS_MODULE_NAME', 'ccx_leads');
define('CCX_LEADS_VERSION', '1.0.2');

hooks()->add_action('admin_init', 'ccx_leads_module_init_menu_items');
hooks()->add_action('app_admin_head', 'ccx_leads_add_head_components');
hooks()->add_action('app_admin_footer', 'ccx_leads_load_js');

/**
 * Register activation module hook
 */
register_activation_hook(CCX_LEADS_MODULE_NAME, 'ccx_leads_module_activation_hook');

function ccx_leads_module_activation_hook()
{
    $CI = &get_instance();
    require_once(__DIR__ . '/install.php');
}

/**
 * Register language files, must be registered if the module is using languages
 */
register_language_files(CCX_LEADS_MODULE_NAME, [CCX_LEADS_MODULE_NAME]);

/**
 * Init module menu items in setup in admin_init hook
 * @return null
 */
function ccx_leads_module_init_menu_items()
{
    $CI = &get_instance();

    if (has_permission('leads', '', 'view')) {
        $CI->app_menu->add_sidebar_menu_item('ccx-leads', [
            'name' => 'CCX Leads',
            'href' => admin_url('ccx_leads'),
            'icon' => 'fa-solid fa-users-viewfinder', // Modern icon
            'position' => 30, // Position near standard leads
        ]);
    }
}

/**
 * Add head components
 */
function ccx_leads_add_head_components()
{
    $CI = &get_instance();
    $view = $CI->router->fetch_class();
    if ($view == 'ccx_leads') {
        echo '<link href="' . module_dir_url(CCX_LEADS_MODULE_NAME, 'assets/css/ccx_leads.css') . '?v=' . CCX_LEADS_VERSION . '"  rel="stylesheet" type="text/css" />';
    }
}

/**
 * Load module JS
 */
function ccx_leads_load_js()
{
    $CI = &get_instance();
    $view = $CI->router->fetch_class();
    if ($view == 'ccx_leads') {
        echo '<script src="' . module_dir_url(CCX_LEADS_MODULE_NAME, 'assets/js/ccx_leads.js') . '?v=' . CCX_LEADS_VERSION . '"></script>';
    }
}
