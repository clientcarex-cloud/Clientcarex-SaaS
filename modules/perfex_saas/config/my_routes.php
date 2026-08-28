<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once('middleware_hooks.php');

if (perfex_saas_is_tenant()) {

    $tenant = perfex_saas_tenant();

    /**
     * Tenant root URL -> the ClientcareX marketing site.
     *
     * A tenant host has nothing meaningful to serve at "/": the CRM lives under
     * /admin and the customer portal under /clients, both of which redirect to
     * their own login. So bounce the bare URL to the master site rather than
     * leaving it on a dead end.
     *
     * This replaces an earlier attempt that set:
     *
     *     $route['default_controller'] = 'admin/dashboard';
     *
     * That never worked. CodeIgniter reads default_controller as class/method,
     * NOT directory/class - see CI_Router::_set_default_controller(), which does
     * sscanf($this->default_controller, '%[^/]/%s', $class, $method) and then
     * looks for application/controllers/Admin.php. No such file exists (admin is
     * a directory), so the router gave up and every tenant root URL answered 404.
     * The companion $route['/'] line was dead too: _parse_routes() only runs for
     * a non-empty URI, so an empty one goes straight to the default controller.
     *
     * default_controller is restored to the app-wide 'clients' so that any other
     * code path falling back to it resolves to a real controller instead of 404.
     */
    $route['default_controller'] = 'clients';

    if (isset($_SERVER['REQUEST_URI'])) {
        $request_path = trim((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

        // Path-mode tenants are reached at /{slug}/ps, so that prefix - and
        // nothing after it - is their root.
        if ($tenant->http_identification_type === PERFEX_SAAS_TENANT_MODE_PATH) {
            $tenant_root = perfex_saas_tenant_url_signature(perfex_saas_clean_slug($tenant->slug, 'url'));
            if (strcasecmp($request_path, $tenant_root) === 0) {
                $request_path = '';
            }
        }

        if ($request_path === '') {
            perfex_saas_redirect_to_master_site();
        }
    }

    $route['admin/billing/my_account'] = 'perfex_saas/admin/companies/client_portal_bridge';

    // Polled by the tenant admin to keep the Support unread badge live
    $route['admin/billing/support_unread'] = 'perfex_saas/admin/companies/tenant_support_unread';
    // Smart Ticket capture (tenant admin → provider helpdesk, cross-DB)
    $route['admin/billing/smart_ticket_submit'] = 'perfex_saas/admin/companies/tenant_smart_ticket_submit';
    // Pro AI Chat floating assistant (tenant admin → master knowledge base, cross-DB)
    $route['admin/billing/ai_chat'] = 'perfex_saas/admin/companies/tenant_ai_chat';
    // Support PIN (tenant admin → master pro_support pins, cross-DB)
    $route['admin/billing/support_pin'] = 'perfex_saas/admin/companies/tenant_support_pin';
    // TEMPORARY: Support badge diagnostics page (remove once verified)
    $route['admin/billing/support_debug'] = 'perfex_saas/admin/companies/tenant_support_debug';
    // TEMPORARY: Client-portal iframe bridge diagnostics (remove once verified)
    $route['admin/billing/bridge_debug'] = 'perfex_saas/admin/companies/tenant_bridge_debug';

    $route['billing/my_account/magic_auth'] = 'perfex_saas/authentication/tenant_admin_magic_auth';

    // Custom modules management page for tenants
    unset($route['admin/modules']);
    unset($route['admin/modules/(:any)']);
    unset($route['admin/modules/(:any)/(:any)']);

    $route['admin/apps/modules'] = 'perfex_saas/admin/tenant_modules_page';
    $route['admin/apps/modules/(:any)'] = 'perfex_saas/admin/tenant_modules_page/$1';
    $route['admin/apps/modules/(:any)/(:any)/(:any)'] = 'perfex_saas/admin/tenant_modules_page/$1/$2/$3';

    // Ensure this custom routes is defined if the tenant is identified by request uri segment
    if ($tenant->http_identification_type === PERFEX_SAAS_TENANT_MODE_PATH) {
        $tenant_slug = perfex_saas_clean_slug($tenant->slug, 'url');
        $tenant_route_sig = perfex_saas_tenant_url_signature($tenant_slug); //i.e $tenant_route_sig

        // Clone existing static routes with saas id prefix
        foreach ($route as $key => $value) {
            $new_key = $tenant_route_sig . "/" . ($key == '/' ? '' : $key);
            $route[$new_key] = $value;
        }
    }
}

if (!perfex_saas_is_tenant()) {

    /**
     * Block /admin/ URL for master account when custom admin URL is configured.
     * This prevents attackers from finding the admin panel at the default /admin/ path.
     * Tenants are not affected (they don't enter this block).
     */
    if (defined('CUSTOM_ADMIN_URL') && CUSTOM_ADMIN_URL !== 'admin') {
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        $request_path = parse_url($request_uri, PHP_URL_PATH);
        // Check if the request starts with /admin/ or is exactly /admin
        if (preg_match('#^/admin(/|$)#i', $request_path)) {
            header('HTTP/1.0 404 Not Found');
            echo '<!DOCTYPE html><html><head><title>404 Not Found</title></head><body><h1>Not Found</h1><p>The requested URL was not found on this server.</p></body></html>';
            exit;
        }
    }


    /** Landing page handling */
    $landing_options = perfex_saas_get_options(['perfex_saas_landing_page_url']);
    $landing_page_url = $landing_options['perfex_saas_landing_page_url'] ?? '';
    if ($landing_page_url && filter_var($landing_page_url, FILTER_VALIDATE_URL)) {
        $method = 'proxy';
        $route['/'] = 'perfex_saas/landing/' . $method;
        $route['default_controller'] = 'perfex_saas/landing/' . $method;
        $route['404_override']         = 'perfex_saas/landing/show_404';

        // ensure the user is redirected to client portal after logging in and not landing page
        hooks()->add_action('after_contact_login', function () {
            $CI = &get_instance();
            if (!$CI->session->has_userdata('red_url')) {
                $CI->session->set_userdata([
                    'red_url' => site_url('clients/'),
                ]);
            }
        });
    } else {
        /**
         * Custom Homepage for Master Account
         * Serve homepage/index.html directly at the root URL (clientcarex.com/)
         */
        if (isset($_SERVER['REQUEST_URI'])) {
            $request_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            if ($request_path === '/' || $request_path === '') {
                $homepage_file = FCPATH . 'homepage/index.html';
                if (file_exists($homepage_file)) {
                    readfile($homepage_file);
                    exit;
                }
            }
        }
    }
    /** Ends Landing page handling */

    // Admin perefex saas routes i.e pacakages and companies/instances management
    // @todo Remove below lines in favour of whitelabel options
    $route['admin/perfex_saas/pricing'] = 'perfex_saas/admin/packages/pricing';
    $route['admin/perfex_saas/(:any)'] = 'perfex_saas/admin/$1';
    $route['admin/perfex_saas/(:any)/(:any)'] = 'perfex_saas/admin/$1/$2';
    $route['admin/perfex_saas/(:any)/(:any)/(:any)'] = 'perfex_saas/admin/$1/$2/$3';
    $route['admin/perfex_saas/(:any)/(:any)/(:any)/(:any)'] = 'perfex_saas/admin/$1/$2/$3/$4';

    // Admin perefex saas routes i.e pacakages and companies/instances management
    // Rewrite route for more whitelabelling
    $route['admin/' . PERFEX_SAAS_ROUTE_NAME . '/pricing'] = 'perfex_saas/admin/packages/pricing';
    $route['admin/' . PERFEX_SAAS_ROUTE_NAME . '/(:any)'] = 'perfex_saas/admin/$1';
    $route['admin/' . PERFEX_SAAS_ROUTE_NAME . '/(:any)/(:any)'] = 'perfex_saas/admin/$1/$2';
    $route['admin/' . PERFEX_SAAS_ROUTE_NAME . '/(:any)/(:any)/(:any)'] = 'perfex_saas/admin/$1/$2/$3';
    $route['admin/' . PERFEX_SAAS_ROUTE_NAME . '/(:any)/(:any)/(:any)/(:any)'] = 'perfex_saas/admin/$1/$2/$3/$4';

    // Custom Admin URL support — duplicate SaaS routes under the custom admin path
    if (defined('CUSTOM_ADMIN_URL') && CUSTOM_ADMIN_URL !== 'admin') {
        $ca = CUSTOM_ADMIN_URL;

        $route[$ca . '/perfex_saas/pricing'] = 'perfex_saas/admin/packages/pricing';
        $route[$ca . '/perfex_saas/(:any)'] = 'perfex_saas/admin/$1';
        $route[$ca . '/perfex_saas/(:any)/(:any)'] = 'perfex_saas/admin/$1/$2';
        $route[$ca . '/perfex_saas/(:any)/(:any)/(:any)'] = 'perfex_saas/admin/$1/$2/$3';
        $route[$ca . '/perfex_saas/(:any)/(:any)/(:any)/(:any)'] = 'perfex_saas/admin/$1/$2/$3/$4';

        $route[$ca . '/' . PERFEX_SAAS_ROUTE_NAME . '/pricing'] = 'perfex_saas/admin/packages/pricing';
        $route[$ca . '/' . PERFEX_SAAS_ROUTE_NAME . '/(:any)'] = 'perfex_saas/admin/$1';
        $route[$ca . '/' . PERFEX_SAAS_ROUTE_NAME . '/(:any)/(:any)'] = 'perfex_saas/admin/$1/$2';
        $route[$ca . '/' . PERFEX_SAAS_ROUTE_NAME . '/(:any)/(:any)/(:any)'] = 'perfex_saas/admin/$1/$2/$3';
        $route[$ca . '/' . PERFEX_SAAS_ROUTE_NAME . '/(:any)/(:any)/(:any)/(:any)'] = 'perfex_saas/admin/$1/$2/$3/$4';
    }

    // API route
    $route[PERFEX_SAAS_ROUTE_NAME . '/api/(:any)'] = 'perfex_saas/api/api/$1';
    $route[PERFEX_SAAS_ROUTE_NAME . '/api/(:any)/(:any)'] = 'perfex_saas/api/api/$1/$2';
    $route[PERFEX_SAAS_ROUTE_NAME . '/api/(:any)/(:any)/(:any)'] = 'perfex_saas/api/api/$1/$2/$3';


    // Client routes
    $route['clients/packages/(:any)/select'] = 'perfex_saas/perfex_saas_client/subscribe/$1';
    $route['clients/my_account'] = 'perfex_saas/perfex_saas_client/my_account';
    $route['clients/my_account/cancel_subscription'] = 'perfex_saas/perfex_saas_client/cancel_saas_subscription';
    $route['clients/my_account/resume_subscription'] = 'perfex_saas/perfex_saas_client/resume_saas_subscription';
    $route['clients/companies'] = 'perfex_saas/perfex_saas_client/companies';
    $route['clients/companies/(:any)'] = 'perfex_saas/perfex_saas_client/$1';
    $route['clients/companies/(:any)/(:any)'] = 'perfex_saas/perfex_saas_client/$1/$2';

    $route['clients/ps_magic/(:any)'] = 'perfex_saas/authentication/magic_auth/$1';
    $route['clients/ps_magic/(:any)/(:any)'] = 'perfex_saas/authentication/magic_auth/$1/$2';

    $route['billing/my_account/magic_auth'] = 'perfex_saas/authentication/client_magic_auth';
}