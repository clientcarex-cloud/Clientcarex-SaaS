<?php

/*
 * Inject sidebar menu and links for banner module
 */
hooks()->add_action('admin_init', function () {

    // Check if the banner module is active
    if (get_instance()->app_modules->is_active('banner')) {
        // Check permissions for different actions
        if (has_permission('banner', get_staff_user_id(), 'view') || has_permission('news_ticker', get_staff_user_id(), 'view') || has_permission('banner_setting', get_staff_user_id(), 'view')) {
            get_instance()->app_menu->add_sidebar_menu_item('banner', [
                'slug' => 'banner_management',
                'name' => _l('banner_management'),
                'position' => 47,
                'icon' => 'fa-regular fa-images menu-icon',
            ]);
        }

        // Add sidebar child items based on permissions
        if (has_permission('banner', get_staff_user_id(), 'view')) {
            get_instance()->app_menu->add_sidebar_children_item('banner', [
                'slug' => 'banner_link',
                'name' => _l('banner'),
                'href' => admin_url('banner'),
                'position' => 1,
            ]);
        }

        if (has_permission('news_ticker', get_staff_user_id(), 'view')) {
            get_instance()->app_menu->add_sidebar_children_item('banner', [
                'slug' => 'news_ticker',
                'name' => _l('news_ticker'),
                'href' => admin_url('banner/news_ticker'),
                'position' => 2,
            ]);
        }

        // Master-only: cross-tenant banner publishing (SaaS control panel).
        // Requires the perfex_saas module active and the current context to be
        // the master account (not a tenant, not a plain non-SaaS install).
        $is_master_admin = is_admin()
            && function_exists('perfex_saas_is_tenant')
            && !perfex_saas_is_tenant();
        if ($is_master_admin) {
            get_instance()->app_menu->add_sidebar_children_item('banner', [
                'slug' => 'tenant_banner',
                'name' => _l('tenant_banner'),
                'href' => admin_url('banner/banner_tenant'),
                'position' => 3,
            ]);
        }

        if (has_permission('banner_setting', get_staff_user_id(), 'view')) {
            get_instance()->app_menu->add_sidebar_children_item('banner', [
                'slug' => 'banner_settings',
                'name' => _l('settings'),
                'href' => admin_url('settings?group=banner'),
                'position' => 6,
            ]);
        }

        // Add settings tab
        if (has_permission('banner_setting', get_staff_user_id(), 'view')) {
            if (BN_CTL_PERFEX_VERSION) {
                get_instance()->app->add_settings_section_child('other', 'banner', [
                    'name' => _l('banner'),
                    'view' => 'banner/settings/banner_settings',
                    'icon' => 'fa-regular fa-images menu-icon',
                    'position' => 30,
                ]);
            } else {
                get_instance()->app_tabs->add_settings_tab('banner', [
                    'name' => _l('banner'),
                    'view' => 'banner/settings/banner_settings',
                    'icon' => 'fa-regular fa-images menu-icon',
                    'position' => 30,
                ]);
            }
        }
    }

});

hooks()->add_action('module_deactivated', function ($module_name) {
    /* if (BANNER_MODULE == $module_name['system_name']) {
        // Check if 'uri' key exists to avoid the error
        $module_headers = get_instance()->app_modules->get(BANNER_MODULE)['headers'];
        $url = isset($module_headers['uri']) ? basename($module_headers['uri']) : 'default-url';
        $url .= '-' . trim(preg_replace(['#/admin.*#','#https?://#', '/[^a-zA-Z0-9]+/'], ['', '', '-'], current_full_url()), '-');

        write_file(TEMP_FOLDER . $url . '.lic', '');
        echo '<script>
            var _bncss = "' . $url . '.lic"' . ';
            sessionStorage.setItem(_bncss, "");
        </script>';
    } */
});
