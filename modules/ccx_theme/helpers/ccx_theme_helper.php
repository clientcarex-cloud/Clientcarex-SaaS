<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Get Admin CSS based on settings
 * @return string
 */
function ccx_theme_get_admin_css()
{
    $css = '<style>';

    // --- Admin Side Menus ---
    $menu_bg = get_option('ccx_theme_menu_bg');
    $menu_links = get_option('ccx_theme_menu_links');
    $menu_active_bg = get_option('ccx_theme_menu_active_bg');
    $menu_active_color = get_option('ccx_theme_menu_active_color');
    $menu_submenu_open_bg = get_option('ccx_theme_menu_submenu_open_bg');
    $menu_submenu_links = get_option('ccx_theme_menu_submenu_links');
    $menu_active_subitem_bg = get_option('ccx_theme_menu_active_subitem_bg');
    $menu_user_welcome_bg = get_option('ccx_theme_menu_user_welcome_bg');
    $menu_user_welcome_text = get_option('ccx_theme_menu_user_welcome_text');

    if (!empty($menu_bg)) {
        $css .= '.admin .sidebar { background: ' . $menu_bg . ' !important; }';
        $css .= '.admin #side-menu { background: ' . $menu_bg . ' !important; }';
        $css .= '.admin #setup-menu { background: ' . $menu_bg . ' !important; }';
    }
    if (!empty($menu_links)) {
        $css .= '.admin #setup-menu > li > a, .admin #side-menu > li > a { color: ' . $menu_links . ' !important; }';
        $css .= '.admin #setup-menu > li > a > i, .admin #side-menu > li > a > i { color: ' . $menu_links . ' !important; }';
    }
    if (!empty($menu_active_bg)) {
        $css .= '.admin #side-menu li.active > a, .admin #setup-menu li.active > a { background-color: ' . $menu_active_bg . ' !important; }';
        // Hover effect for main items sharing the active bg color or slightly different if users want, 
        // usually users expect hover to match active or be distinct. For now, let's map hover to active bg or darken it?
        // The user asked for "hover effect link and bg color". We don't have explicit hover inputs, so we might infer or add them.
        // For now, let's check if we can assume hover usage. 
        // Perfex default behavior: hover often lightens/darkens. 
        // Let's apply the same active background on hover for now to ensure consistency if requested "hover effect link and bg color".
        $css .= '.admin #side-menu > li > a:hover, .admin #setup-menu > li > a:hover { background-color: ' . $menu_active_bg . ' !important; }';
    }
    if (!empty($menu_active_color)) {
        $css .= '.admin #side-menu li.active > a, .admin #side-menu li.active > a > i { color: ' . $menu_active_color . ' !important; }';
        $css .= '.admin #setup-menu li.active > a, .admin #setup-menu li.active > a > i { color: ' . $menu_active_color . ' !important; }';
        // Apply to hover as well
        $css .= '.admin #side-menu > li > a:hover, .admin #setup-menu > li > a:hover { color: ' . $menu_active_color . ' !important; }';
        $css .= '.admin #side-menu > li > a:hover > i, .admin #setup-menu > li > a:hover > i { color: ' . $menu_active_color . ' !important; }';
    }
    if (!empty($menu_submenu_open_bg)) {
        $css .= '.admin #side-menu ul.nav-second-level { background: ' . $menu_submenu_open_bg . ' !important; }';
        $css .= '.admin #setup-menu ul.nav-second-level { background: ' . $menu_submenu_open_bg . ' !important; }';
    }
    if (!empty($menu_submenu_links)) {
        $css .= '.admin #side-menu ul.nav-second-level li a { color: ' . $menu_submenu_links . ' !important; }';
        $css .= '.admin #setup-menu ul.nav-second-level li a { color: ' . $menu_submenu_links . ' !important; }';
    }
    if (!empty($menu_active_subitem_bg)) {
        $css .= '.admin #side-menu ul.nav-second-level li.active a { background-color: ' . $menu_active_subitem_bg . ' !important; }';
        $css .= '.admin #setup-menu ul.nav-second-level li.active a { background-color: ' . $menu_active_subitem_bg . ' !important; }';
        // Hover for subitems
        $css .= '.admin #side-menu ul.nav-second-level li a:hover { background-color: ' . $menu_active_subitem_bg . ' !important; }';
        $css .= '.admin #setup-menu ul.nav-second-level li a:hover { background-color: ' . $menu_active_subitem_bg . ' !important; }';
    }
    if (!empty($menu_user_welcome_bg)) {
        $css .= '#side-menu li.header-profile { background: ' . $menu_user_welcome_bg . ' !important; }';
    }
    if (!empty($menu_user_welcome_text)) {
        $css .= '#side-menu li.header-profile, #side-menu li.header-profile a { color: ' . $menu_user_welcome_text . ' !important; }';
    }

    // Top Header
    $header_bg = get_option('ccx_theme_header_bg');
    $header_links = get_option('ccx_theme_header_links');
    if (!empty($header_bg)) {
        $css .= '.admin #header { background: ' . $header_bg . ' !important; }';
    }
    if (!empty($header_links)) {
        $css .= '.admin #header a, .admin #header i { color: ' . $header_links . ' !important; }';
    }

    // --- Buttons ---
    $btn_default_bg = get_option('ccx_theme_btn_default_bg');
    if (!empty($btn_default_bg)) {
        $css .= '.btn-default { background-color: ' . $btn_default_bg . ' !important; border-color: ' . $btn_default_bg . ' !important; }';
    }
    $btn_primary_bg = get_option('ccx_theme_btn_primary_bg');
    if (!empty($btn_primary_bg)) {
        $css .= '.btn-primary { background-color: ' . $btn_primary_bg . ' !important; border-color: ' . $btn_primary_bg . ' !important; }';
    }
    $btn_info_bg = get_option('ccx_theme_btn_info_bg');
    if (!empty($btn_info_bg)) {
        $css .= '.btn-info { background-color: ' . $btn_info_bg . ' !important; border-color: ' . $btn_info_bg . ' !important; }';
    }
    $btn_success_bg = get_option('ccx_theme_btn_success_bg');
    if (!empty($btn_success_bg)) {
        $css .= '.btn-success { background-color: ' . $btn_success_bg . ' !important; border-color: ' . $btn_success_bg . ' !important; }';
    }
    $btn_danger_bg = get_option('ccx_theme_btn_danger_bg');
    if (!empty($btn_danger_bg)) {
        $css .= '.btn-danger { background-color: ' . $btn_danger_bg . ' !important; border-color: ' . $btn_danger_bg . ' !important; }';
    }

    // --- Tabs ---
    $tabs_bg = get_option('ccx_theme_tabs_bg');
    $tabs_links = get_option('ccx_theme_tabs_links_color');
    $tabs_hover = get_option('ccx_theme_tabs_hover_color');
    $tabs_active_border = get_option('ccx_theme_tabs_active_border_color');
    $tabs_border = get_option('ccx_theme_tabs_border_color');

    if (!empty($tabs_bg)) {
        $css .= '.nav-tabs { background: ' . $tabs_bg . ' !important; }';
    }
    if (!empty($tabs_links)) {
        $css .= '.nav-tabs>li>a { color: ' . $tabs_links . ' !important; }';
    }
    if (!empty($tabs_hover)) {
        $css .= '.nav-tabs>li>a:hover, .nav-tabs>li.active>a, .nav-tabs>li.active>a:focus, .nav-tabs>li.active>a:hover { color: ' . $tabs_hover . ' !important; }';
    }
    if (!empty($tabs_active_border)) {
        $css .= '.nav-tabs { border-bottom: 1px solid ' . $tabs_active_border . ' !important; }';
        $css .= '.nav-tabs>li.active>a, .nav-tabs>li.active>a:focus, .nav-tabs>li.active>a:hover { border: 1px solid ' . $tabs_active_border . ' !important; border-bottom-color: transparent !important; }';
    }
    if (!empty($tabs_border)) {
        // This might be tricky as bootstrap tabs don't have a single border color variable easily accessible
        // mostly handled by active border
    }

    // --- Modals ---
    $modal_header_bg = get_option('ccx_theme_modal_header_bg');
    $modal_header_color = get_option('ccx_theme_modal_header_color');
    $modal_close_btn = get_option('ccx_theme_modal_close_btn_color');
    $modal_body_bg = get_option('ccx_theme_modal_body_bg');

    if (!empty($modal_header_bg)) {
        $css .= '.modal-header { background: ' . $modal_header_bg . ' !important; }';
    }
    if (!empty($modal_header_color)) {
        $css .= '.modal-title { color: ' . $modal_header_color . ' !important; }';
    }
    if (!empty($modal_close_btn)) {
        $css .= '.modal-header .close { color: ' . $modal_close_btn . ' !important; opacity: 1; }';
    }
    if (!empty($modal_body_bg)) {
        $css .= '.modal-body { background: ' . $modal_body_bg . ' !important; }';
    }

    // --- Texts ---
    $text_muted = get_option('ccx_theme_text_muted');
    $text_danger = get_option('ccx_theme_text_danger');
    $text_warning = get_option('ccx_theme_text_warning');
    $text_info = get_option('ccx_theme_text_info');
    $text_success = get_option('ccx_theme_text_success');
    $text_dark = get_option('ccx_theme_text_dark');

    if (!empty($text_muted)) {
        $css .= '.text-muted { color: ' . $text_muted . ' !important; }';
    }
    if (!empty($text_danger)) {
        $css .= '.text-danger { color: ' . $text_danger . ' !important; }';
    }
    if (!empty($text_warning)) {
        $css .= '.text-warning { color: ' . $text_warning . ' !important; }';
    }
    if (!empty($text_info)) {
        $css .= '.text-info { color: ' . $text_info . ' !important; }';
    }
    if (!empty($text_success)) {
        $css .= '.text-success { color: ' . $text_success . ' !important; }';
    }
    if (!empty($text_dark)) {
        $css .= '.text-dark { color: ' . $text_dark . ' !important; }';
    }

    // --- Alerts ---
    // Info
    $alert_info_text = get_option('ccx_theme_alert_info_text');
    $alert_info_bg = get_option('ccx_theme_alert_info_bg');
    if (!empty($alert_info_text)) {
        $css .= '.alert-info { color: ' . $alert_info_text . ' !important; }';
    }
    if (!empty($alert_info_bg)) {
        $css .= '.alert-info { background-color: ' . $alert_info_bg . ' !important; }';
    }

    // Danger
    $alert_danger_text = get_option('ccx_theme_alert_danger_text');
    $alert_danger_bg = get_option('ccx_theme_alert_danger_bg');
    if (!empty($alert_danger_text)) {
        $css .= '.alert-danger { color: ' . $alert_danger_text . ' !important; }';
    }
    if (!empty($alert_danger_bg)) {
        $css .= '.alert-danger { background-color: ' . $alert_danger_bg . ' !important; }';
    }

    // Warning
    $alert_warning_text = get_option('ccx_theme_alert_warning_text');
    $alert_warning_bg = get_option('ccx_theme_alert_warning_bg');
    if (!empty($alert_warning_text)) {
        $css .= '.alert-warning { color: ' . $alert_warning_text . ' !important; }';
    }
    if (!empty($alert_warning_bg)) {
        $css .= '.alert-warning { background-color: ' . $alert_warning_bg . ' !important; }';
    }

    // Success
    $alert_success_text = get_option('ccx_theme_alert_success_text');
    $alert_success_bg = get_option('ccx_theme_alert_success_bg');
    if (!empty($alert_success_text)) {
        $css .= '.alert-success { color: ' . $alert_success_text . ' !important; }';
    }
    if (!empty($alert_success_bg)) {
        $css .= '.alert-success { background-color: ' . $alert_success_bg . ' !important; }';
    }

    // --- Others ---
    $login_bg = get_option('ccx_theme_admin_login_bg');
    if (!empty($login_bg)) {
        $css .= 'body.login_admin { background: ' . $login_bg . ' !important; }';
    }

    $main_bg = get_option('ccx_theme_main_bg');
    if (!empty($main_bg)) {
        $css .= 'body { background-color: ' . $main_bg . ' !important; }';
    }

    $main_text = get_option('ccx_theme_main_text_color');
    if (!empty($main_text)) {
        $css .= 'body { color: ' . $main_text . ' !important; }';
    }

    $input_bg = get_option('ccx_theme_input_bg');
    if (!empty($input_bg)) {
        $css .= '.form-control { background-color: ' . $input_bg . ' !important; }';
    }

    $input_border = get_option('ccx_theme_input_border');
    if (!empty($input_border)) {
        $css .= '.form-control { border-color: ' . $input_border . ' !important; }';
    }

    $hr_color = get_option('ccx_theme_hr_color');
    if (!empty($hr_color)) {
        $css .= 'hr { border-top-color: ' . $hr_color . ' !important; }';
    }

    $link_color = get_option('ccx_theme_link_color');
    if (!empty($link_color)) {
        $css .= 'a { color: ' . $link_color . ' !important; }';
    }

    $link_hover = get_option('ccx_theme_link_hover_color');
    if (!empty($link_hover)) {
        $css .= 'a:hover, a:focus { color: ' . $link_hover . ' !important; }';
    }

    $panel_bg = get_option('ccx_theme_panel_bg');
    if (!empty($panel_bg)) {
        $css .= '.panel { background-color: ' . $panel_bg . ' !important; }';
    }

    $panel_heading_color = get_option('ccx_theme_panel_heading_color');
    if (!empty($panel_heading_color)) {
        $css .= '.panel-default > .panel-heading { color: ' . $panel_heading_color . ' !important; }';
    }

    $table_heading_color = get_option('ccx_theme_table_headings_color');
    if (!empty($table_heading_color)) {
        $css .= '.table > thead > tr > th { color: ' . $table_heading_color . ' !important; }';
    }

    $table_heading_bg = get_option('ccx_theme_table_headings_bg');
    if (!empty($table_heading_bg)) {
        $css .= '.table > thead > tr > th { background-color: ' . $table_heading_bg . ' !important; }';
    }

    $table_content_bg = get_option('ccx_theme_table_content_bg');
    if (!empty($table_content_bg)) {
        $css .= '.table > tbody > tr > td { background-color: ' . $table_content_bg . ' !important; }';
    }

    // --- Tags ---
    $tag_bg = get_option('ccx_theme_tag_bg');
    if (!empty($tag_bg)) {
        $css .= '.label-default { background-color: ' . $tag_bg . ' !important; } .tag { background-color: ' . $tag_bg . ' !important; }';
    }

    $css .= '</style>';
    return $css;
}

/**
 * Get Customers CSS based on settings
 * @return string
 */
function ccx_theme_get_customers_css()
{
    $css = '<style>';

    $login_bg = get_option('ccx_theme_customers_login_bg');
    if (!empty($login_bg)) {
        $css .= 'body.customers_login { background: ' . $login_bg . ' !important; }';
    }

    $c_menu_bg = get_option('ccx_theme_customers_menu_bg');
    if (!empty($c_menu_bg)) {
        $css .= '.navbar-default { background-color: ' . $c_menu_bg . ' !important; }';
    }
    $c_menu_links = get_option('ccx_theme_customers_menu_links');
    if (!empty($c_menu_links)) {
        $css .= '.navbar-default .navbar-nav>li>a { color: ' . $c_menu_links . ' !important; }';
    }

    $c_footer_bg = get_option('ccx_theme_customers_footer_bg');
    if (!empty($c_footer_bg)) {
        $css .= 'footer { background-color: ' . $c_footer_bg . ' !important; }';
    }

    $c_footer_text = get_option('ccx_theme_customers_footer_text');
    if (!empty($c_footer_text)) {
        $css .= 'footer { color: ' . $c_footer_text . ' !important; }';
        $css .= 'footer p { color: ' . $c_footer_text . ' !important; }';
    }

    $css .= '</style>';
    return $css;
}
