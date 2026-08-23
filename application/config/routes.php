<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| URI ROUTING
| -------------------------------------------------------------------------
| This file lets you re-map URI requests to specific controller functions.
|
| Typically there is a one-to-one relationship between a URL string
| and its corresponding controller class/method. The segments in a
| URL normally follow this pattern:
|
|   example.com/class/method/id/
|
| In some instances, however, you may want to remap this relationship
| so that a different class/function is called than the one
| corresponding to the URL.
|
| Please see the user guide for complete details:
|
|   http://codeigniter.com/user_guide/general/routing.html
|
| -------------------------------------------------------------------------
| RESERVED ROUTES
| -------------------------------------------------------------------------
|
| There are three reserved routes:
|
|   $route['default_controller'] = 'welcome';
|
| This route indicates which controller class should be loaded if the
| URI contains no data. In the above example, the "welcome" class
| would be loaded.
|
|   $route['404_override'] = 'errors/page_missing';
|
| This route will tell the Router which controller/method to use if those
| provided in the URL cannot be matched to a valid route.
|
|   $route['translate_uri_dashes'] = FALSE;
|
| This is not exactly a route, but allows you to automatically route
| controller and method names that contain dashes. '-' isn't a valid
| class or method name character, so it requires translation.
| When you set this option to TRUE, it will replace ALL dashes in the
| controller and method URI segments.
|
| Examples: my-controller/index -> my_controller/index
|       my-controller/my-method -> my_controller/my_method
*/

$route['default_controller']   = 'clients';
$route['404_override']         = '';
$route['translate_uri_dashes'] = false;

/**
 * Dashboard clean route
 */
$route['admin'] = 'admin/dashboard';

/**
 * Misc controller routes
 */
$route['admin/access_denied'] = 'admin/misc/access_denied';
$route['admin/not_found']     = 'admin/misc/not_found';

/**
 * Staff Routes
 */
$route['admin/profile']           = 'admin/staff/profile';
$route['admin/profile/(:num)']    = 'admin/staff/profile/$1';
$route['admin/tasks/view/(:any)'] = 'admin/tasks/index/$1';

/**
 * Items search rewrite
 */
$route['admin/items/search'] = 'admin/invoice_items/search';

/**
 * In case if client access directly to url without the arguments redirect to clients url
 */
$route['/'] = 'clients';

/**
 * @deprecated
 */
$route['viewinvoice/(:num)/(:any)'] = 'invoice/index/$1/$2';

/**
 * @since 2.0.0
 */
$route['invoice/(:num)/(:any)'] = 'invoice/index/$1/$2';

/**
 * @deprecated
 */
$route['viewestimate/(:num)/(:any)'] = 'estimate/index/$1/$2';

/**
 * @since 2.0.0
 */
$route['estimate/(:num)/(:any)'] = 'estimate/index/$1/$2';
$route['subscription/(:any)']    = 'subscription/index/$1';

/**
 * @deprecated
 */
$route['viewproposal/(:num)/(:any)'] = 'proposal/index/$1/$2';

/**
 * @since 2.0.0
 */
$route['proposal/(:num)/(:any)'] = 'proposal/index/$1/$2';

/**
 * @since 2.0.0
 */
$route['contract/(:num)/(:any)'] = 'contract/index/$1/$2';

/**
 * @since 2.0.0
 */
$route['knowledge-base']                 = 'knowledge_base/index';
$route['knowledge-base/search']          = 'knowledge_base/search';
$route['knowledge-base/article']         = 'knowledge_base/index';
$route['knowledge-base/article/(:any)']  = 'knowledge_base/article/$1';
$route['knowledge-base/category']        = 'knowledge_base/index';
$route['knowledge-base/category/(:any)'] = 'knowledge_base/category/$1';

/**
 * @deprecated 2.2.0
 */
if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'add_kb_answer') === false) {
    $route['knowledge-base/(:any)']         = 'knowledge_base/article/$1';
    $route['knowledge_base/(:any)']         = 'knowledge_base/article/$1';
    $route['clients/knowledge_base/(:any)'] = 'knowledge_base/article/$1';
    $route['clients/knowledge-base/(:any)'] = 'knowledge_base/article/$1';
}

/**
 * @deprecated 2.2.0
 * Fallback for auth clients area, changed in version 2.2.0
 */
$route['clients/reset_password']  = 'authentication/reset_password';
$route['clients/forgot_password'] = 'authentication/forgot_password';
$route['clients/logout']          = 'authentication/logout';
$route['clients/register']        = 'authentication/register';
$route['clients/login']           = 'authentication/login';

// Aliases for short routes
$route['reset_password']  = 'authentication/reset_password';
$route['forgot_password'] = 'authentication/forgot_password';
$route['login']           = 'authentication/login';
$route['logout']          = 'authentication/logout';
$route['register']        = 'authentication/register';

/**
 * Terms and conditions and Privacy Policy routes
 */
$route['terms-and-conditions'] = 'terms_and_conditions';
$route['privacy-policy']       = 'privacy_policy';

/**
 * @since 2.3.0
 * Routes for admin/modules URL because Modules.php class is used in application/third_party/MX
 */
$route['admin/modules']               = 'admin/mods';
$route['admin/modules/(:any)']        = 'admin/mods/$1';
$route['admin/modules/(:any)/(:any)'] = 'admin/mods/$1/$2';

// Public single ticket route
$route['forms/tickets/(:any)'] = 'forms/public_ticket/$1';

/**
 * @since  2.3.0
 * Route for clients set password URL, because it's using the same controller for staff to
 * If user addded block /admin by .htaccess this won't work, so we need to rewrite the URL
 * In future if there is implementation for clients set password, this route should be removed
 */
$route['authentication/set_password/(:num)/(:num)/(:any)'] = 'admin/authentication/set_password/$1/$2/$3';

// For backward compatilibilty
$route['survey/(:num)/(:any)'] = 'surveys/participate/index/$1/$2';

// Short URL for public feedback forms (SMS/WhatsApp DLT friendly)
$route['fb/(:any)'] = 'feedback/feedback_public/public_form/$1';

// Short URL for public Smart Forms (smart_forms module)
$route['form/(:any)'] = 'smart_forms/smart_forms_public/fill/$1';

// Short URLs for SHRA rider self-registration (shra module)
// /join/{token}                              membership form
// /join/{token}/verify/{rider_no}[/{cert}]   QR verification
// /join/{token}/done|pdf/{rider_no}/{sig}    success page / membership PDF
$route['join/(:any)']                         = 'shra/shra_public/register/$1';
$route['join/(:any)/(:any)/(:any)']           = 'shra/shra_public/register/$1/$2/$3';
$route['join/(:any)/(:any)/(:any)/(:any)']    = 'shra/shra_public/register/$1/$2/$3/$4';

// Short URLs for public live voting (voting module)
// /vote/{code} = telecast screen (question + QR + live results)
// /v/{code}    = voter ballot (vote only, no results exposed)
$route['vote/(:any)'] = 'voting/voting_public/live/$1';
$route['v/(:any)']    = 'voting/voting_public/ballot/$1';

// Short URL for self kiosk QR codes (self_kiosk module)
$route['sk/(:any)'] = 'self_kiosk/kiosk/go/$1';

// Short URL for token display screens (token_system module)
$route['td/(:any)'] = 'token_system/token_display/short/$1';

// Public meeting scheduling (pro_sales module) — order matters, the token and
// the slug are catch-alls, so every specific path must be listed above them.
// /meet/{token} = the invitee's own meeting page (view / reschedule / cancel)
// /book/{slug}  = public booking page (Calendly-style slot picker)
$route['meet/ics/(:any)']   = 'pro_sales/pro_sales_public/ics/$1';
$route['meet/slots/(:any)'] = 'pro_sales/pro_sales_public/meeting_slots/$1';
$route['meet/(:any)']       = 'pro_sales/pro_sales_public/meeting/$1';
$route['book/slots/(:any)'] = 'pro_sales/pro_sales_public/page_slots/$1';
$route['book/(:any)']       = 'pro_sales/pro_sales_public/book/$1';

// Public ebook funnel (pro_ebook module) — order matters, the slug is the catch-all
// /ebook/thanks/{token} = delivery page, /ebook/dl/{token} = PDF stream,
// /ebook/wa/{token}     = community redirect, /ebook/{slug} = landing page
$route['ebook/thanks/(:any)'] = 'pro_ebook/pro_ebook_public/success/$1';
$route['ebook/dl/(:any)']     = 'pro_ebook/pro_ebook_public/download/$1';
$route['ebook/wa/(:any)']     = 'pro_ebook/pro_ebook_public/wa/$1';
$route['ebook/(:any)']        = 'pro_ebook/pro_ebook_public/page/$1';

// Public subscription portal (pro_services module)
// /services/{token} = the customer's own subscription page — plan, billing
// history and a Pay button that hands off to the CRM invoice payment page.
$route['services/(:any)'] = 'pro_services/pro_services_public/portal/$1';

/**
 * PWA Manifest route
 * Serves the Web App Manifest dynamically with company name
 */
$route['pwa/manifest'] = 'pwa_manifest/index';

/**
 * Include custom routes BEFORE custom admin catch-all
 * This ensures specific module routes (SaaS, etc.) take precedence over the catch-all
 */
if (file_exists(APPPATH . 'config/my_routes.php')) {
    include_once(APPPATH . 'config/my_routes.php');
}

/**
 * Custom Admin URL Support for Master Account Security
 * When CUSTOM_ADMIN_URL is defined, duplicate all admin/* routes under the custom path.
 * This only affects the master account; tenants continue using /admin/.
 * IMPORTANT: This must come AFTER my_routes.php so specific routes take precedence.
 */
if (defined('CUSTOM_ADMIN_URL') && CUSTOM_ADMIN_URL !== 'admin') {
    $customAdmin = CUSTOM_ADMIN_URL;

    // Core admin routes under custom URL
    $route[$customAdmin]                        = 'admin/dashboard';
    $route[$customAdmin . '/access_denied']     = 'admin/misc/access_denied';
    $route[$customAdmin . '/not_found']         = 'admin/misc/not_found';
    $route[$customAdmin . '/profile']           = 'admin/staff/profile';
    $route[$customAdmin . '/profile/(:num)']    = 'admin/staff/profile/$1';
    $route[$customAdmin . '/tasks/view/(:any)'] = 'admin/tasks/index/$1';
    $route[$customAdmin . '/items/search']      = 'admin/invoice_items/search';
    $route[$customAdmin . '/modules']               = 'admin/mods';
    $route[$customAdmin . '/modules/(:any)']        = 'admin/mods/$1';
    $route[$customAdmin . '/modules/(:any)/(:any)'] = 'admin/mods/$1/$2';

    // Catch-all for any other admin sub-routes under the custom URL
    $route[$customAdmin . '/(:any)']                          = 'admin/$1';
    $route[$customAdmin . '/(:any)/(:any)']                   = 'admin/$1/$2';
    $route[$customAdmin . '/(:any)/(:any)/(:any)']            = 'admin/$1/$2/$3';
    $route[$customAdmin . '/(:any)/(:any)/(:any)/(:any)']     = 'admin/$1/$2/$3/$4';
    $route[$customAdmin . '/(:any)/(:any)/(:any)/(:any)/(:any)'] = 'admin/$1/$2/$3/$4/$5';
}
