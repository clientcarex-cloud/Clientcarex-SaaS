<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Staging Host → Master Recognition
 *
 * When the current request comes from a staging host (e.g. healtho.tech) and
 * APP_BASE_URL_DEFAULT still points to production (clientcarex.com), the SaaS
 * module's host detection doesn't recognise the staging host as "master" and
 * falls through to a custom-domain lookup. If the staging hostname happens to
 * exist in the perfex_saas_companies.custom_domain column, the request is
 * misidentified as a tenant → DSN decryption fails → 503.
 *
 * Defining PERFEX_SAAS_ALTERNATIVE_HOST for the staging host makes
 * perfex_saas_get_tenant_info_by_host() return false (= master) immediately,
 * preventing the erroneous tenant detection.
 *
 * This block must run before perfex_saas_init() which is called from
 * modules/perfex_saas/config/app-config.php.
 */
if (!defined('PERFEX_SAAS_ALTERNATIVE_HOST')) {
    $_ccx_staging_env = getenv('CCX_STAGING_HOSTS');

    // app_env_value() has already loaded .env by this point (called for
    // APP_BASE_URL_DEFAULT in application/config/app-config.php), so the
    // value is available via getenv().
    if ($_ccx_staging_env === false || trim((string) $_ccx_staging_env) === '') {
        // Fallback: also check $_ENV / $_SERVER in case putenv() was blocked
        $_ccx_staging_env = $_ENV['CCX_STAGING_HOSTS'] ?? ($_SERVER['CCX_STAGING_HOSTS'] ?? '');
    }

    if (is_string($_ccx_staging_env) && trim($_ccx_staging_env) !== '') {
        $_ccx_current_host = strtolower(explode(':', ($_SERVER['HTTP_HOST'] ?? ''), 2)[0]);
        if (str_starts_with($_ccx_current_host, 'www.')) {
            $_ccx_current_host = substr($_ccx_current_host, 4);
        }

        $_ccx_staging_list = array_map(
            'strtolower',
            array_filter(array_map('trim', explode(',', $_ccx_staging_env)))
        );

        if (in_array($_ccx_current_host, $_ccx_staging_list, true)) {
            define('PERFEX_SAAS_ALTERNATIVE_HOST', $_ccx_current_host);
        }
    }

    unset($_ccx_staging_env, $_ccx_current_host, $_ccx_staging_list);
}

/**
 * Custom Admin URL for Master Account Security
 * 
 * This changes the admin panel URL from /admin/ to a unique hard-to-guess path.
 * Only affects the master account (clientcarex.com). Tenants continue using /admin/.
 * 
 * Master Admin URL: https://clientcarex.com/dfwenhj34jh5E-h3jh28dhgdsu2/admin/
 * 
 * NOTE: The '&' from the original URL was replaced with '-' because CodeIgniter
 * does not allow '&' in URI paths (permitted_uri_chars security filter).
 */
if (!defined('CUSTOM_ADMIN_URL')) {
    define('CUSTOM_ADMIN_URL', 'dfwenhj34jh5E-h3jh28dhgdsu2/admin');
}