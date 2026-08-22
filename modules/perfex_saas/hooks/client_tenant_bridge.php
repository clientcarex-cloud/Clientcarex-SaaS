<?php

defined('BASEPATH') or exit('No direct script access allowed');

$client_bridge = $is_tenant ? perfex_saas_tenant_is_enabled('client_bridge') : (int)perfex_saas_get_options('perfex_saas_enable_client_bridge');
if ($client_bridge) {

    if ($is_tenant) {
        // Add account menu items for saas management — merged into profile dropdown.
        hooks()->add_action('admin_init', function () use ($CI) {
            if (is_admin()) {

                $tenant = perfex_saas_tenant();

                $child_menu_items = [
                    [
                        'slug'     => 'saas_client_overview',
                        'name'     => _l('perfex_saas_client_menu_overview'),
                        'href'     => admin_url('billing/my_account?redirect=clients/?companies'),
                        'icon'     => 'fa fa-tachometer-alt',
                        'position' => 1,
                        'badge'    => [],
                    ],
                    [
                        'slug'     => 'saas_client_subscription',
                        'name'     => _l('perfex_saas_client_menu_subscription'),
                        'href'     => admin_url('billing/my_account?redirect=clients/?subscription'),
                        'icon'     => 'fa fa-credit-card',
                        'position' => 2,
                        'badge'    => [],
                    ], [
                        'slug'     => 'saas_client_invoices',
                        'name'     => _l('perfex_saas_client_menu_invoices'),
                        'href'     => admin_url('billing/my_account?redirect=clients/invoices'),
                        'icon'     => 'fa fa-file-invoice',
                        'position' => 3,
                        'badge'    => [],
                    ],
                    [
                        'slug'     => 'saas_client_tickets',
                        'name'     => _l('perfex_saas_customer_success'),
                        'href'     => admin_url('billing/my_account?redirect=clients/pro_tickets'),
                        'icon'     => 'fa-solid fa-hand-holding-heart',
                        'position' => 10,
                        'badge'    => [],
                    ]
                ];

                if (perfex_saas_tenant_get_super_option('show_subscriptions_in_customers_area') == '1' && !empty($tenant->package_invoice->stripe_subscription_id)) {
                    $child_menu_items[] = [
                        'slug'     => 'saas_client_invoices',
                        'name'     => _l('subscriptions'),
                        'href'     => admin_url('billing/my_account?redirect=clients/subscriptions'),
                        'icon'     => 'fa fa-sync-alt',
                        'position' => 2.5,
                        'badge'    => [],
                    ];
                }

                $child_menu_items[] = [
                    'slug'     => 'saas_client_company_info',
                    'name'     => _l('client_company_info'),
                    'href'     => admin_url('billing/my_account?redirect=clients/company'),
                    'icon'     => 'fa fa-building',
                    'position' => 2.5,
                    'badge'    => [],
                ];

                $child_menu_items = hooks()->apply_filters('perfex_saas_tenant_account_menu', $child_menu_items);

                if (!empty($child_menu_items)) {

                    usort($child_menu_items, function ($a, $b) {
                        $posA = $a['position'] ?? PHP_INT_MAX;
                        $posB = $b['position'] ?? PHP_INT_MAX;

                        return $posA <=> $posB;
                    });

                    // Store in global for profile dropdown rendering in aside.php
                    $GLOBALS['perfex_saas_profile_account_items'] = $child_menu_items;
                }
            }
        });





    }


    if (!$is_tenant && $is_client) {

        $invoice = null;
        $on_trial_and_ended = false;
        $invoice_overdue = false;
        try {
            $invoice = $CI->perfex_saas_model->get_company_invoice(get_client_user_id());
            $on_trial_and_ended = isset($invoice->on_trial) && $invoice->on_trial && perfex_saas_get_days_until($invoice->duedate) <= 0;
            $invoice_overdue = $invoice && !$invoice->is_private && perfex_saas_is_invoice_overdue_for_payment($invoice);
        } catch (\Throwable $e) {
            log_message('error', 'SaaS client bridge invoice lookup failed: ' . $e->getMessage());
        }
        $has_outstanding = !$invoice || $on_trial_and_ended || $invoice_overdue;

        $GLOBALS['has_outstanding'] = $has_outstanding;
        // Disable nav and footer when using magic_auth from one of the tenant instance. i.e client preview
        hooks()->add_action('clients_init', function () use ($has_outstanding) {
            $CI = &get_instance();
            $is_magic_auth = $CI->session->has_userdata('magic_auth');
            if ($is_magic_auth) {
                $companies = $CI->perfex_saas_model->companies();
                if (empty($companies)) {
                    $CI->session->unset_userdata('magic_auth');
                    return;
                }
                $cross_domain = $CI->session->userdata('magic_auth')['cross_domain'] ?? false;
                if (!$cross_domain && !$has_outstanding && (int)get_option('perfex_saas_enable_client_menu_in_bridge') !== 1) {
                    $CI->disableNavigation();
                    $CI->disableSubMenu();
                    $CI->disableFooter();
                }
            }
        });


        // Redirect to the primary instance and Auto login is not is saas client and not running in magic auth
        $route_class = $CI->router->fetch_class();
        if (
            !$CI->input->is_ajax_request() &&
            $route_class !== 'authentication' &&
            !$CI->session->has_userdata('magic_auth') &&
            !$has_outstanding &&
            !is_subclass_of($CI->router->fetch_class(), 'AdminController')
        ) {
            // Get primary instance
            $primary_active_instance = null;
            $companies = $CI->perfex_saas_model->companies();
            foreach ($companies as $key => $company) {
                if ($company->status === PERFEX_SAAS_STATUS_ACTIVE) {
                    $primary_active_instance = $company;
                }
            }

            // Auto signin and redirect
            if ($primary_active_instance && perfex_saas_contact_can_magic_auth($primary_active_instance)) {
                $urlmode = 'auto';

                // Forward the current page that will be viewed to originally by the client
                $query = uri_string();
                if (!empty($query) && $query !== '/') {
                    $query = $query . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');
                    $query = '?redirect=' . urlencode('billing/my_account?redirect=' . $query);
                }

                redirect(site_url('clients/ps_magic/' . $primary_active_instance->slug . '/' . $urlmode) . $query);
                exit;
            }
        }

        // Hide the back button on the invoice view when under tenant bridge. This will force user to use the side bar menu or browser nav
        hooks()->add_action('after_left_panel_invoicehtml', function ($invoice) use ($CI) {
            if ($CI->session->has_userdata('magic_auth')) {
                echo '<script>document.querySelector(".action-button.go-to-portal").remove();</script>';
            }
        });
    }

    // Redirect to saas login after logging out from tenant
    if ($is_tenant) {

        $is_magic_auth = false;
        $CI->load->helper('cookie');

        // Track magic auth session
        if (isset($_GET['_magic_auth_session'])) {
            set_cookie([
                'name'  => 'magic_auth',
                'value' => '1',
                'httponly' => true,
                'expire' => 0
            ]);

            // Remove the signature from the url and redirect
            $current_url = $_SERVER['REQUEST_URI'];
            $url_components = parse_url($current_url);
            parse_str($url_components['query'], $query_params);
            unset($query_params['_magic_auth_session']);
            $new_query_string = http_build_query($query_params);
            $new_url = current_url(); // Get current url without query //$url_components['path'];
            if (!empty($new_query_string)) $new_url .= '?' . urldecode($new_query_string);
            redirect($new_url);
        }

        // Determine if active use is admin or not be it logs out
        hooks()->add_action('before_staff_logout', function () use (&$is_admin, &$is_magic_auth) {
            $is_admin = is_admin();
            $is_magic_auth = (int)get_cookie('magic_auth') === 1;
        });
        // Redirect to saas client logout page if magic auth and redirect back otherwise.
        // The ensure the saas client session is also terminated.
        hooks()->add_action('after_user_logout', function () use (&$is_admin, &$is_magic_auth) {
            if ($is_admin) {
                $query = $is_magic_auth ? '' : '?sso_redirect=' . admin_url();
                delete_cookie('magic_auth');
                delete_cookie('autologin');
                redirect(perfex_saas_default_base_url('authentication/logout' . $query), []);
            }
        });
    } else {

        // Remove any magic auth session after every standard proper login
        hooks()->add_action('after_contact_login', function () use ($CI) {
            $CI->session->unset_userdata('magic_auth');
        });

        // Handle redirection back to instance admin after logging out from saas client portal
        hooks()->add_action('after_client_logout', function () use ($CI) {
            $redirect = $CI->input->get('sso_redirect');
            $CI->session->unset_userdata('magic_auth');
            if (!empty($redirect)) {
                redirect($redirect);
            }
        });
    }
}

/**
 * Sync primary contact password to the tenant admin
 */
function perfex_saas_sync_contact_password_to_tenant($contact_id)
{
    if (is_array($contact_id) && isset($contact_id['userid'])) {
        // Handle after_user_reset_password hook
        if (!isset($contact_id['staff']) || $contact_id['staff'] == true) return;
        $contact_id = $contact_id['userid'];
    }

    $CI = &get_instance();
    $contact = $CI->db->where('id', $contact_id)->get(db_prefix() . 'contacts')->row();
    
    // Only sync if the contact exists and is a primary contact
    if (!$contact || $contact->is_primary != 1) return;

    $CI->load->model('perfex_saas/perfex_saas_model');
    // Get all tenants for this client
    $companies = $CI->perfex_saas_model->companies($contact->userid);
    if (empty($companies)) return;

    foreach ($companies as $company) {
        // Only sync to active instances
        if ($company->status !== 'active') continue;
        
        $dsn = perfex_saas_get_company_dsn($company);
        if (!$dsn) continue;

        $tenant_dbprefix = perfex_saas_tenant_db_prefix($company->slug);
        $table = $tenant_dbprefix . "staff";
        
        $password = $contact->password;
        
        // Update the primary admin's password in the tenant database
        $query = "UPDATE `$table` SET `password` = " . $CI->db->escape($password) . " WHERE `admin` = '1' AND `active` = '1' ORDER BY `staffid` ASC LIMIT 1";
        try {
            perfex_saas_raw_query($query, $dsn, false, true);
        } catch (\Throwable $th) {
            log_message('error', 'Error syncing contact password to tenant: ' . $th->getMessage());
        }
    }
}

hooks()->add_action('contact_updated', 'perfex_saas_sync_contact_password_to_tenant');
hooks()->add_action('after_user_reset_password', 'perfex_saas_sync_contact_password_to_tenant');