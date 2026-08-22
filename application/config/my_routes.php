<?php defined('BASEPATH') or exit('No direct script access allowed');//perfex-saas:start:my_routes.php
//dont remove/change above line
require_once(FCPATH.'modules/perfex_saas/config/my_routes.php');
//dont remove/change below line
//perfex-saas:end:my_routes.php

// Public report download route (for WhatsApp sharing with 24hr token expiry)
$route['report/download/(:any)'] = 'transcriptor_public/download/$1';

// Custom Unified Login
$route['login'] = 'unified_login/index';
$route['unified_login/(:any)'] = 'unified_login/$1';
$route['unified_login'] = 'unified_login/index';

// Modern Pro Tickets client-portal support desk
if (file_exists(FCPATH . 'modules/pro_tickets/config/my_routes.php')) {
    require_once(FCPATH . 'modules/pro_tickets/config/my_routes.php');
}