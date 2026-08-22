<?php

defined('BASEPATH') or exit('No direct script access allowed');

if (!$CI->db->table_exists(db_prefix() . 'ccx_theme_options')) {
    // We might not need a dedicated table if we use update_option, but keeping this file for standard structure
    // or if we decide to create a custom table later.
    // For now, we will rely on Perfex's options table which is standard for settings.
}

/**
 * Set default options for CCX Theme
 */
// Admin Side Menus
add_option('ccx_theme_menu_bg', '#000000');
add_option('ccx_theme_menu_user_welcome_bg', '#000000');
add_option('ccx_theme_menu_active_color', '#000000');
add_option('ccx_theme_menu_submenu_links', '#000000');
add_option('ccx_theme_menu_submenu_open_bg', '#eeeeee');

add_option('ccx_theme_header_bg', '#ffffff');
add_option('ccx_theme_menu_active_subitem_bg', '#ffffff');
add_option('ccx_theme_menu_user_welcome_text', '#ffffff');
add_option('ccx_theme_menu_links', '#ffffff');
add_option('ccx_theme_menu_active_bg', '#ffffff');

// Tabs section
add_option('ccx_theme_tabs_bg', '#f1faff');

// Modals section
add_option('ccx_theme_modal_header_bg', '#f1faff');
add_option('ccx_theme_modal_header_color', '#000000');
add_option('ccx_theme_modal_close_btn_color', '#000000');
add_option('ccx_theme_modal_white_text_color', '#000000');
