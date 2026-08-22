<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Business Profile
Description:  Consolidated General and Company settings module (superadmin only, accessible from the user menu).
Version: 1.0.0
Requires at least: 2.3.*
*/

define('BIZ_PROFILE_MODULE_NAME', 'biz_profile');

/**
 * Register activation module hook
 */
register_activation_hook(BIZ_PROFILE_MODULE_NAME, 'biz_profile_module_activation_hook');

function biz_profile_module_activation_hook()
{
    $CI = &get_instance();
    require_once(__DIR__ . '/install.php');
}

/**
 * Register language files, must be registered if the module is using languages
 */
register_language_files(BIZ_PROFILE_MODULE_NAME, [BIZ_PROFILE_MODULE_NAME]);

/**
 * The module is intentionally NOT registered in the main sidebar menu.
 * It is linked from the user profile dropdown (superadmin only) as "Business Profile"
 * — see application/views/admin/includes/aside.php and header.php.
 * Access is restricted to superadmins in the controller via is_admin().
 */
