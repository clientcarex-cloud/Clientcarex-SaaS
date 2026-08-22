<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: CCX Theme
Description: Custom Theme Options for Perfex CRM
Version: 1.0.0
Requires at least: 2.3.*
*/

define('CCX_THEME_MODULE_NAME', 'ccx_theme');

$CI = &get_instance();

/**
 * Register activation module hook
 */
register_activation_hook(CCX_THEME_MODULE_NAME, 'ccx_theme_activation_hook');

function ccx_theme_activation_hook()
{
    $CI = &get_instance();
    require_once(__DIR__ . '/install.php');
}

/**
 * Register language files, must be registered if the module is using languages
 */
register_language_files(CCX_THEME_MODULE_NAME, [CCX_THEME_MODULE_NAME]);

/**
 * Init module menu items in setup in admin_init hook
 */
hooks()->add_action('admin_init', 'ccx_theme_init_menu_items');

function ccx_theme_init_menu_items()
{
    $CI = &get_instance();

    if (has_permission('settings', '', 'view')) {
        $CI->app_menu->add_setup_menu_item('ccx-theme-options', [
            'name' => 'CCX Theme',
            'href' => admin_url('ccx_theme'),
            'position' => 60,
        ]);
    }
}

/**
 * Inject CSS into admin head
 */
hooks()->add_action('app_admin_head', 'ccx_theme_admin_head');

function ccx_theme_admin_head()
{
    // Logic to inject CSS will be added here later
    // For now, we can check if the helper exists and use it, or just leave it empty
    if (function_exists('ccx_theme_get_admin_css')) {
        echo ccx_theme_get_admin_css();
    }
}

/**
 * Inject CSS into customers area head
 */
hooks()->add_action('app_customers_head', 'ccx_theme_customers_head');

function ccx_theme_customers_head()
{
    // Logic to inject CSS will be added here later
    if (function_exists('ccx_theme_get_customers_css')) {
        echo ccx_theme_get_customers_css();
    }
}

// Load helper
$CI->load->helper(CCX_THEME_MODULE_NAME . '/ccx_theme');
