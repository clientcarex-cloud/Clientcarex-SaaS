<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Module routes.
 *
 * HMVC (MX_Router → Modules::parse_routes) reads this file whenever the first
 * URI segment is "lead_sync", and resolves the target as "lead_sync/<value>".
 * That keeps the whole module self-contained — nothing is added to
 * application/config/routes.php.
 *
 * The instant-delivery endpoint therefore lives at:
 *
 *   POST /lead_sync/push/{token}
 *
 * The long form /lead_sync/lead_sync_push/index/{token} keeps working too;
 * both are exempted from CSRF in config/csrf_exclude_uris.php.
 */
$route['lead_sync/push/(:any)'] = 'lead_sync_push/index/$1';
