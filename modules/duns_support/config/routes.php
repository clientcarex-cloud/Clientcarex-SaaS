<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Module-owned public routes (HMVC reads this file whenever the first URI
 * segment is "duns_support" and resolves targets as duns_support/<value>).
 *
 * The pretty ad URL /duns-support/... needs a hyphen, which only a core route
 * can provide — see application/config/routes.php. These keep the landing page
 * reachable at /duns_support/... even without that core route.
 */
$route['duns_support']                              = 'duns_public/index';
$route['duns_support/(order)']                      = 'duns_public/index/$1';
$route['duns_support/(pay|status)/([a-f0-9]+)']     = 'duns_public/index/$1/$2';
