<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Client-portal (customer area) routes for the modern Pro Tickets support desk.
 *
 * These map clients/pro_tickets* URLs to the module's Clients_pro_tickets
 * controller. They must be defined here (loaded from application/config/my_routes.php)
 * because MX per-module route parsing only fires when the first URI segment is the
 * module name, whereas these live under the core "clients" segment.
 */
$route['clients/pro_tickets']                 = 'pro_tickets/clients_pro_tickets/index';
$route['clients/pro_tickets/status/(:num)']   = 'pro_tickets/clients_pro_tickets/index/$1';
$route['clients/pro_tickets/department/(:any)/status/(:num)'] = 'pro_tickets/clients_pro_tickets/department/$1/$2';
$route['clients/pro_tickets/department/(:any)'] = 'pro_tickets/clients_pro_tickets/department/$1';
$route['clients/pro_tickets/open']            = 'pro_tickets/clients_pro_tickets/open';
$route['clients/pro_tickets/smart_create']    = 'pro_tickets/clients_pro_tickets/smart_create';
$route['clients/pro_tickets/ticket/(:num)']   = 'pro_tickets/clients_pro_tickets/ticket/$1';
$route['clients/pro_tickets/feedback']        = 'pro_tickets/clients_pro_tickets/feedback';
