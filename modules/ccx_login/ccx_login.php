<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: CCX Login
Description: Custom login page footer (Asset/View Container)
Version: 1.0.0
Requires at least: 2.3.*
*/

define('CCX_LOGIN_MODULE_NAME', 'ccx_login');

// Hook removed as it is now handled globally in application/helpers/my_functions_helper.php
// hooks()->add_action('before_admin_login_form_close', 'ccx_login_footer');

function ccx_login_footer()
{
    $CI = &get_instance();
    echo $CI->load->view('ccx_login/login_footer', [], true);
}

/**
 * Register activation module hook
 */
register_activation_hook(CCX_LOGIN_MODULE_NAME, 'ccx_login_activation_hook');

function ccx_login_activation_hook()
{
    $CI = &get_instance();
    require_once(__DIR__ . '/install.php');
}

/**
 * Register module menu items in setup in admin_init hook
 */
hooks()->add_action('admin_init', 'ccx_login_init_menu_items');

function ccx_login_init_menu_items()
{
    $CI = &get_instance();

    if (has_permission('settings', '', 'view')) {
        $CI->app_menu->add_setup_menu_item('ccx-login-settings', [
            'name' => 'CCX Login Settings',
            'href' => admin_url('ccx_login/settings'),
            'position' => 65,
        ]);
    }
}
