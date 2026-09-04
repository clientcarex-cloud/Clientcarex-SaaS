<?php

defined('BASEPATH') or exit('No direct script access allowed');

/** GATE KEEPERS ***/
if (perfex_saas_is_tenant()) return;

if (get_option('perfex_saas_cpanel_enabled') != '1') return;

/** Load library */
$CI->load->library(PERFEX_SAAS_MODULE_NAME . '/integrations/cpanel_api');


/******** HELPERS *******/
if (!function_exists('perfex_saas_cpanel_enable_addondomain')) {
    /**
     * Check if addon domain is enabled for cpanel
     *
     * @return bool
     */
    function perfex_saas_cpanel_enable_addondomain()
    {
        return get_option('perfex_saas_cpanel_enable_addondomain') == '1';
    }
}

if (!function_exists('perfex_saas_cpanel_allowed_for_tenant')) {
    /**
     * Check if tenant can use cpanel
     *
     * @param object $tenant
     * @return bool
     */
    function perfex_saas_cpanel_allowed_for_tenant($tenant)
    {
        $package_invoice = perfex_saas_get_client_package_invoice($tenant->clientid);
        $scheme = $package_invoice->db_scheme ?? '';
        return $scheme === 'cpanel';
    }
}

if (!function_exists('perfex_saas_get_cpanel')) {
    /**
     * Get cpanel api library instance
     *
     * @return Cpanel_api $cpanel
     */
    function perfex_saas_get_cpanel()
    {
        $CI = &get_instance();
        $user = get_option('perfex_saas_cpanel_username');

        // Set appropriately if user have added custom cpanel db_prefix
        $db_prefix = get_option('perfex_saas_cpanel_db_prefix') ?? '';
        $db_prefix = empty($db_prefix) ? $user : $db_prefix;
        $prefix = (empty($db_prefix) ? '' : $db_prefix . '_') . PERFEX_SAAS_MODULE_NAME_SHORT . '_';

        // decrypt() returns FALSE when the stored value was written under a
        // different application encryption key. Passing that on produces an
        // empty Basic auth password and cPanel answers with its login page,
        // which reads as "invalid credentials" rather than "unreadable secret".
        $stored_password = get_option('perfex_saas_cpanel_password');
        $password = $CI->encryption->decrypt($stored_password);

        if ($password === false) {
            log_message('error', 'perfex_saas cpanel: stored password could not be decrypted (' . (empty($stored_password) ? 'no password saved' : 'the application encryption key changed since it was saved') . '). Re-enter and save the cPanel password in SaaS settings > Integrations.');
            $password = '';
        }

        $CI->cpanel_api->init(
            $user,
            $password,
            get_option('perfex_saas_cpanel_login_domain'),
            get_option('perfex_saas_cpanel_port'),
            $prefix
        );
        $CI->cpanel_api->mainDomain = get_option('perfex_saas_cpanel_primary_domain');
        return $CI->cpanel_api;
    }
}

if (!function_exists('perfex_saas_cpanel_document_root')) {
    /**
     * Document root the tenant sub/addon domains must be pointed at.
     *
     * cPanel's addsubdomain/addaddondomain treat an EMPTY "dir" as "make one
     * up", and create the vhost against a brand new, empty
     * /public_html/<slug> folder. Apache then answers every request on that
     * subdomain — including /admin/ — with its own plain "Not Found", because
     * the CRM's index.php and .htaccess are not in that folder at all.
     * Falling back to FCPATH (this installation's own web root) keeps the
     * vhost pointed at the CRM when the setting was never filled in.
     *
     * @return string
     */
    function perfex_saas_cpanel_document_root()
    {
        $root_dir = trim((string) get_option('perfex_saas_cpanel_document_root'));

        return $root_dir === '' ? FCPATH : $root_dir;
    }
}

if (!function_exists('perfex_saas_cpanel_setup_tenant_db')) {
    /**
     * Create database and user for the tenant slug
     *
     * @param string $slug
     * @return array
     */
    function perfex_saas_cpanel_setup_tenant_db($slug)
    {

        $CI = &get_instance();
        $cpanel = perfex_saas_get_cpanel();

        $db_host = 'localhost';
        $db_user = $cpanel->addPrefix($slug);
        $db_name = $cpanel->addPrefix($slug);

        if (!function_exists('random_string')) {
            $CI->load->helper('string');
        }
        $db_password = random_string('alnum', 16);


        try {
            $cpanel->createDatabase($db_name);
        } catch (\Throwable $th) {
            log_message('error', $th->getMessage());
        }

        try {
            $cpanel->createDatabaseUser($db_user, $db_password);
        } catch (\Throwable $th) {
            log_message('error', $th->getMessage());
        }

        try {
            $cpanel->setDatabaseUserPrivileges($db_user, $db_name);
        } catch (\Throwable $th) {
            log_message('error', $th->getMessage());
        }

        $db = [
            'host' => $db_host,
            'user' => $db_user,
            'password' => $db_password,
            'dbname' => $db_name,
        ];
        return $db;
    };
}

if (!function_exists('perfex_saas_cpanel_rollback')) {
    /**
     * Rollback tenant db and addon domain setup
     *
     * @param object $tenant
     * @return void
     */
    function perfex_saas_cpanel_rollback($tenant)
    {
        if (!perfex_saas_cpanel_allowed_for_tenant($tenant)) return;

        $cpanel = perfex_saas_get_cpanel();

        $slug = $tenant->slug;
        $db_name = $slug;
        $db_user = $slug;

        try {
            $cpanel->deleteDatabase($db_name);
            $cpanel->deleteDatabaseUser($db_user);
        } catch (\Throwable $th) {
            log_message('error', $th->getMessage());
        }

        if (!perfex_saas_cpanel_enable_addondomain()) return;

        /// Remove subdomain
        try {
            $cpanel->deleteSubdomain($slug, perfex_saas_get_saas_default_host());
        } catch (\Throwable $th) {
            log_message('error', $th->getMessage());
        }
        try {
            perfex_saas_cpanel_delete_domain($cpanel, $slug);
        } catch (\Throwable $th) {
            log_message('error', $th->getMessage());
        }

        // Remove the custom domain
        if (!empty($tenant->custom_domain)) {
            try {
                perfex_saas_cpanel_delete_domain($cpanel, $slug . 'addon', $tenant->custom_domain);
            } catch (\Throwable $th) {
                log_message('error', $th->getMessage());
            }
        }
    };
}

if (!function_exists('perfex_saas_cpanel_delete_domain')) {
    /**
     * Function to delete addon domain
     *
     * @param Cpanel_api $cpanel
     * @param string $slug
     * @param mixed $domain
     * @return mixed
     */
    function perfex_saas_cpanel_delete_domain($cpanel, $slug, $domain = null)
    {
        $root_domain = perfex_saas_get_saas_default_host();
        $domain = $domain ?? $slug . '.' . $root_domain;
        $cpanel_main_domain = $cpanel->mainDomain;
        $subdomain = $slug; // . ($cpanel_main_domain === $root_domain ? '' : '.' . $root_domain);
        return $cpanel->deleteAddonDomain($domain, $subdomain, $cpanel_main_domain);
    }
}



/**** HOOKS *******/

// Add cpanel as option to package db scheme
hooks()->add_filter('perfex_saas_module_db_schemes', function ($schemes) {
    $schemes[] = ['key' => 'cpanel', 'label' => _l('perfex_saas_cpanel_scheme')];
    return $schemes;
});

// Add cpanel as option to tenant db scheme
hooks()->add_filter('perfex_saas_module_db_schemes_alt', function ($schemes) {
    $schemes[] = ['key' => 'cpanel', 'label' => _l('perfex_saas_cpanel_scheme')];
    return $schemes;
});

// Create dsn: db user and password and db  e.t.c
hooks()->add_filter('perfex_saas_module_maybe_create_tenant_dsn', function ($data) {

    $tenant = $data['tenant'];
    $invoice = $data['invoice'];

    if ($invoice->db_scheme === 'cpanel') {

        $data['dsn'] = perfex_saas_cpanel_setup_tenant_db($tenant->slug);
    }
    return $data;
});

// Detect change in custom domain and add/delete in cpanel accordingly if there is valid change
hooks()->add_filter('perfex_saas_module_tenant_data_payload', function ($payload) {

    $data = $payload['data'];
    $tenant = $payload['tenant'];
    $invoice = $payload['invoice'];

    $addondomain_mode = get_option('perfex_saas_cpanel_addondomain_mode');
    $addondomain_mode = empty($addondomain_mode) ? 'all' : $addondomain_mode;

    $can_use_custom_domain = (int)($invoice->metadata->enable_custom_domain ?? 0) && in_array($addondomain_mode, ['customdomain', 'all']);

    // We only want to run this only when updating and not new.
    if (!$tenant || !perfex_saas_cpanel_enable_addondomain() || !$can_use_custom_domain) return $payload;

    if (!perfex_saas_cpanel_allowed_for_tenant($tenant)) return $payload;

    $new_domain = $data['custom_domain'] ?? '';
    $old_domain = $tenant->custom_domain ?? '';
    $slug = $tenant->slug ?? $data['slug'];
    // Create new custom domain when updating
    if ($new_domain !== $old_domain) {

        $cpanel = perfex_saas_get_cpanel();

        if (!empty($old_domain)) {
            // Remove old custom domain
            try {
                perfex_saas_cpanel_delete_domain($cpanel, $slug . 'addon', $old_domain);
            } catch (\Throwable $th) {
                log_message('error', $th->getMessage());
                get_instance()->session->set_flashdata('message-danger', $th->getMessage());
            }
        }

        // Create new addon entry
        if (!empty($new_domain)) {
            try {
                // createAddonDomain() takes ($domain, $subdomain, $dir) — passing
                // $root_domain third pointed the vhost at a "clientcarex.com"
                // folder that does not exist, and the extra argument was dropped.
                $cpanel->createAddonDomain($new_domain, $slug . 'addon', perfex_saas_cpanel_document_root());
            } catch (\Throwable $th) {
                $payload['data']['custom_domain'] = '';
                $payload['error'] = $th->getMessage();
                log_message('error', $th->getMessage());
            }
        }
    }

    return $payload;
}, 3);

// Create subdomain if supported in package and or custom domain
hooks()->add_action('perfex_saas_module_tenant_deployed', function ($data) {
    $tenant = $data['tenant'];
    $invoice = $data['invoice'];

    if (!perfex_saas_cpanel_enable_addondomain()) return;

    if (!perfex_saas_cpanel_allowed_for_tenant($tenant)) return;

    $cpanel = perfex_saas_get_cpanel();
    $root_dir = perfex_saas_cpanel_document_root();
    $root_domain = perfex_saas_get_saas_default_host();

    $slug = $tenant->slug;
    $subdomain = $slug . '.' . $root_domain;
    $custom_domain = $tenant->custom_domain;

    $addondomain_mode = get_option('perfex_saas_cpanel_addondomain_mode');
    $addondomain_mode = empty($addondomain_mode) ? 'all' : $addondomain_mode;

    $can_use_subdomain = (int)($invoice->metadata->enable_subdomain ?? 0) && in_array($addondomain_mode, ['subdomain', 'all']);
    $can_use_custom_domain = (int)($invoice->metadata->enable_custom_domain ?? 0) && in_array($addondomain_mode, ['customdomain', 'all']);

    if (!$can_use_custom_domain && !$can_use_subdomain) return;

    // Create subdomain as addon domain for the tenant, this allow inheriting of ssl.
    if ($can_use_subdomain) {
        try {
            $cpanel->createSubdomain($slug, $root_domain, $root_dir);
        } catch (\Throwable $th) {
            log_message('error', $th->getMessage());
            get_instance()->session->set_flashdata('message-danger', $th->getMessage());
        }
    }

    // Create custom domain as addon domain
    if (!empty($custom_domain) && $can_use_custom_domain) {
        try {
            $cpanel->createAddonDomain($custom_domain, $slug . 'addon', $root_dir);
        } catch (\Throwable $th) {
            log_message('error', $th->getMessage());
            get_instance()->session->set_flashdata('message-danger', $th->getMessage());
        }
    }

    // Try trigger autoSSL if enabled on the cpanel account
    try {
        $cpanel->autoSSL();
    } catch (\Throwable $th) {
        log_message('error', $th->getMessage());
        $cpanel->throwException = false;
        if ($can_use_subdomain)
            $cpanel->generateSSL($subdomain);
        if ($can_use_custom_domain)
            $cpanel->generateSSL($custom_domain);
        $cpanel->throwException = true;
    }
}, 2);

// Remove tenant db, subdomain, addondomain and db
hooks()->add_action('perfex_saas_module_tenant_removed', 'perfex_saas_cpanel_rollback');
hooks()->add_action('perfex_saas_module_tenant_removal_failed', 'perfex_saas_cpanel_rollback');
hooks()->add_action('perfex_saas_module_tenant_deploy_failed', 'perfex_saas_cpanel_rollback');
