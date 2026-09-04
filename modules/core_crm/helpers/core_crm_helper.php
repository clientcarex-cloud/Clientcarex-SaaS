<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Core CRM shared helpers.
 *
 * Loaded from both the module bootstrap (core_crm.php) and the controller so
 * the functions are always available to the hooks, controller and views,
 * regardless of module load order on a given tenant.
 */

if (!function_exists('core_crm_managed_menus')) {
    /**
     * Single source of truth for the menu slugs this module controls.
     *
     * @return array slug => human label
     */
    function core_crm_managed_menus()
    {
        return [
            'estimate_request' => 'Estimate Requests',
            'contracts'        => 'Contracts',
            'projects'         => 'Projects',
            'sales'            => 'Sales',
            'knowledge-base'   => 'Knowledge Base',
            'utilities'        => 'Utilities',
            'setup'            => 'Setup', // handled via CSS, not the sidebar filter
            'subscriptions'    => 'Subscriptions',
            'expenses'         => 'Expenses',
            'support'          => 'Support / Tickets',
            'leads'            => 'Leads',
            'reports'          => 'Reports',
            'tasks'            => 'Tasks',
        ];
    }
}

if (!function_exists('core_crm_option_enabled')) {
    /**
     * Resolve whether a Core CRM toggle is "on".
     *
     * Design intent (also documented in the settings view): a missing option
     * defaults to ENABLED ("hidden & restricted") for security, so freshly
     * provisioned tenants where install.php never ran still get the menus
     * locked down. Only an explicit '0' turns the toggle off.
     *
     * @param string $key full option key, e.g. core_crm_hide_sales
     * @return bool
     */
    function core_crm_option_enabled($key)
    {
        $value = get_option($key);

        // Missing (false) / empty / null => default ON. Explicit '0' => OFF.
        if ($value === false || $value === '' || $value === null) {
            return true;
        }

        return (string) $value !== '0';
    }
}
