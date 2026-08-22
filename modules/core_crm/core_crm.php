<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Core CRM
Description: Hides default Perfex CRM menus (Estimates, Contracts, Projects, Sales, KB, Utilities, Setup)
Version: 1.0.3
Requires at least: 2.3.*
*/

if (!defined('CORE_CRM_MODULE_NAME')) {
    define('CORE_CRM_MODULE_NAME', 'core_crm');
}

/*
 * All hook registration lives in bootstrap.php so it can also be loaded from
 * application/config/my_hooks.php for tenants where this module is not in the
 * perfex_saas loadable-modules list. The bootstrap is guarded against double
 * inclusion.
 */
require_once __DIR__ . '/bootstrap.php';
