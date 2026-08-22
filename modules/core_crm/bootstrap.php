<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
 * Core CRM bootstrap.
 *
 * This file registers the sidebar hide / access-restrict hooks. It is loaded
 * from TWO places:
 *
 *   1. modules/core_crm/core_crm.php  - the normal module init file, used when
 *      core_crm is in the tenant's loadable-modules list.
 *
 *   2. application/config/my_hooks.php - loaded on EVERY request for EVERY
 *      tenant, regardless of perfex_saas per-tenant module gating.
 *
 * Reason: in the perfex_saas multi-tenant setup,
 * perfex_saas_filter_tenant_loadable_modules() strips modules that are not in
 * the tenant's allowed list before their init files are required. When that
 * happens the module's hooks never register and the menus are never hidden,
 * even though the settings controller is still reachable via routing. Loading
 * this bootstrap from my_hooks.php guarantees the hide/restrict logic always
 * runs, while still reading the per-tenant core_crm_* options.
 *
 * Guarded so it is safe to include more than once per request.
 */

if (defined('CORE_CRM_BOOTSTRAPPED')) {
    return;
}
define('CORE_CRM_BOOTSTRAPPED', true);

if (!defined('CORE_CRM_MODULE_NAME')) {
    define('CORE_CRM_MODULE_NAME', 'core_crm');
}

// Shared helpers: core_crm_managed_menus(), core_crm_option_enabled().
require_once __DIR__ . '/helpers/core_crm_helper.php';

if (!function_exists('core_crm_hide_menus')) {
    /**
     * Remove the configured top-level menus from the admin sidebar.
     */
    function core_crm_hide_menus($items)
    {
        foreach (core_crm_managed_menus() as $slug => $label) {
            // 'setup' is removed via CSS, not from the sidebar items array.
            if ($slug === 'setup') {
                continue;
            }

            if (!core_crm_option_enabled('core_crm_hide_' . $slug)) {
                continue;
            }

            // Fast path: array keyed by slug.
            if (isset($items[$slug])) {
                unset($items[$slug]);
                continue;
            }

            // Fallback: match on the slug attribute regardless of array key.
            foreach ($items as $key => $item) {
                if (isset($item['slug']) && $item['slug'] === $slug) {
                    unset($items[$key]);
                }
            }
        }

        return $items;
    }
}

if (!function_exists('core_crm_restrict_access')) {
    /**
     * Deny direct URL access to restricted controllers.
     */
    function core_crm_restrict_access()
    {
        $CI = &get_instance();

        // Map of slug => restricted URL segments (admin/<segment>).
        $restricted_map = [
            'estimate_request' => ['estimate_request'],
            'contracts'        => ['contracts'],
            'projects'         => ['projects'],
            'sales'            => ['sales', 'invoices', 'proposals', 'estimates', 'credit_notes', 'payments'],
            'knowledge-base'   => ['knowledge_base'],
            'utilities'        => ['utilities'],
            'subscriptions'    => ['subscriptions'],
            'support'          => ['tickets'],
            'leads'            => ['leads'],
            'reports'          => ['reports'],
            'tasks'            => ['tasks'],
        ];

        $uri = $CI->uri->uri_string();

        foreach ($restricted_map as $slug => $segments) {
            if (!core_crm_option_enabled('core_crm_restrict_' . $slug)) {
                continue;
            }

            foreach ($segments as $segment) {
                if (strpos($uri, 'admin/' . $segment) === 0) {
                    access_denied('Restricted by Core CRM');
                }
            }
        }
    }
}

if (!function_exists('core_crm_hide_setup_css')) {
    /**
     * Hide the Setup menu item via CSS (it is rendered outside the sidebar filter).
     */
    function core_crm_hide_setup_css()
    {
        if (core_crm_option_enabled('core_crm_hide_setup')) {
            echo '<style>#setup-menu-item { display: none !important; }</style>';
        }
    }
}

/**
 * Register the hooks. Guarded so double-inclusion (module init + my_hooks)
 * does not register the callbacks twice.
 */
if (function_exists('hooks') && !defined('CORE_CRM_HOOKS_REGISTERED')) {
    define('CORE_CRM_HOOKS_REGISTERED', true);

    // High priority so Core CRM gets the final say after other modules
    // (e.g. menus_sections at priority 1000) rebuild the menu array.
    hooks()->add_filter('sidebar_menu_items', 'core_crm_hide_menus', 99999);
    hooks()->add_action('app_admin_head', 'core_crm_hide_setup_css');
    hooks()->add_action('admin_init', 'core_crm_restrict_access');
}
