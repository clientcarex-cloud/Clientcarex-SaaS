<?php

defined('BASEPATH') or exit('No direct script access allowed');

$menus = [
    'estimate_request',
    'contracts',
    'projects',
    'sales',
    'knowledge-base',
    'utilities',
    'setup',
    'subscriptions',
    'expenses',
    'support',
    'leads',
    'reports',
    'tasks'
];

foreach ($menus as $menu) {
    if (get_option('core_crm_hide_' . $menu) === false) {
        add_option('core_crm_hide_' . $menu, '1');
    }

    if (get_option('core_crm_restrict_' . $menu) === false) {
        add_option('core_crm_restrict_' . $menu, '1');
    }
}
