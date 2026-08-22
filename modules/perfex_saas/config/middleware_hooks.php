<?php

defined('BASEPATH') or exit('No direct script access allowed');

// This code fixes first time installation issue where error 500 might be experienced on some setup immediately after fresh install
if (!defined('PERFEX_SAAS_MODULE_NAME') && !isset($_GET['reload'])) {
    $url = ($_SERVER['REQUEST_URI'] ?? '');
    $url = (empty($_GET) ? explode('?', $url)[0] . '?reload=1' : $url . '&reload=1');
    header('Location: ' . $url);
    exit();
}

// Include the middlewares
require_once(__DIR__ . '/../helpers/perfex_saas_middleware_helper.php');

/**
 * Detect the global tenant and define the database credential or use default db credentials.
 * We have to run this detection here as the stored DB credentials are ecnrypted and thus we need the Encryption library to decrypt.
 * Encryption library can not be in config because of race effect (db_prefix function by perfex) when loading at such early time.
 * Thus we move the segments here. 
 */
$GLOBALS['_encryption'] = load_class('Encryption');
$dsn = ['host' => '', 'user' => '', 'password' => '', 'dbname' => ''];

// Check if the its a tenant and use the tenant dsn
if (isset($GLOBALS[PERFEX_SAAS_MODULE_NAME . '_tenant'])) {
    try {
        $decrypted_dsn = $GLOBALS['_encryption']->decrypt($GLOBALS[PERFEX_SAAS_MODULE_NAME . '_tenant']->dsn);

        // Guard: if decryption returned empty/falsy, the stored DSN is blank or the encryption key changed
        if (empty($decrypted_dsn)) {
            // On staging/dev servers, allow fallback to master DB with tenant prefix
            // instead of blocking access. Tenant DSNs are typically neutralized
            // (set to empty) during staging clone — this lets the Edit button in
            // CCX Support work by using the master DB with the tenant's table prefix.
            if (defined('PERFEX_SAAS_ALTERNATIVE_HOST') || _ccx_middleware_is_staging_host()) {
                log_message('info', 'Perfex SaaS DSN Staging Fallback: empty DSN for tenant "' . ($GLOBALS[PERFEX_SAAS_MODULE_NAME . '_tenant']->slug ?? 'unknown') . '" — using master DB with tenant prefix.');
                // $dsn stays as default empty values → lines below will use APP_DB_*_DEFAULT
            } else {
                throw new \Exception('Decryption returned empty DSN for tenant: ' . ($GLOBALS[PERFEX_SAAS_MODULE_NAME . '_tenant']->slug ?? 'unknown'));
            }
        } else {
            $GLOBALS[PERFEX_SAAS_MODULE_NAME . '_tenant']->dsn = $decrypted_dsn;
            $dsn = (array)perfex_saas_parse_dsn($decrypted_dsn);
        }
    } catch (\Throwable $dsn_error) {
        // Log the error for admin debugging
        log_message('error', 'Perfex SaaS DSN Error: ' . $dsn_error->getMessage());

        // On staging servers, allow fallback to master DB instead of hard 503
        if (defined('PERFEX_SAAS_ALTERNATIVE_HOST') || _ccx_middleware_is_staging_host()) {
            log_message('info', 'Perfex SaaS DSN Staging Fallback: decryption failed for tenant "' . ($GLOBALS[PERFEX_SAAS_MODULE_NAME . '_tenant']->slug ?? 'unknown') . '" — using master DB with tenant prefix.');
            // $dsn stays as default empty values → lines below will use APP_DB_*_DEFAULT
        } else {
            // A tenant with a broken DSN cannot function at all — falling back to master DB
            // would cause cascading errors (tenant prefix applied to master tables).
            // Show a clean error page and stop execution.
            perfex_saas_show_tenant_error(
                'Service Temporarily Unavailable',
                'This instance is currently being configured. Please try again shortly or contact support.',
                503,
                '404',
                'dsn_error'
            );
        }
    }
}

/**
 * Check if the current request is on a staging/dev host.
 * Reads CCX_STAGING_HOSTS env var directly — ccx_runtime_flags module
 * may not be loaded yet at this early middleware stage.
 */
function _ccx_middleware_is_staging_host(): bool
{
    static $result = null;
    if ($result !== null) {
        return $result;
    }

    $result = false;
    $stagingEnv = getenv('CCX_STAGING_HOSTS');
    if ($stagingEnv === false || trim((string) $stagingEnv) === '') {
        $stagingEnv = $_ENV['CCX_STAGING_HOSTS'] ?? ($_SERVER['CCX_STAGING_HOSTS'] ?? '');
    }

    if (is_string($stagingEnv) && trim($stagingEnv) !== '') {
        $currentHost = strtolower(explode(':', ($_SERVER['HTTP_HOST'] ?? ''), 2)[0]);
        if (str_starts_with($currentHost, 'www.')) {
            $currentHost = substr($currentHost, 4);
        }
        $stagingList = array_map('strtolower', array_filter(array_map('trim', explode(',', $stagingEnv))));
        $result = in_array($currentHost, $stagingList, true);
    }

    return $result;
}

// Define database credentials (fall back to _DEFAULT constants when DSN is empty)
defined('APP_DB_HOSTNAME') or define('APP_DB_HOSTNAME', empty($dsn['host']) ? APP_DB_HOSTNAME_DEFAULT : $dsn['host']);
defined('APP_DB_USERNAME') or define('APP_DB_USERNAME', empty($dsn['user']) ? APP_DB_USERNAME_DEFAULT : $dsn['user']);
defined('APP_DB_PASSWORD') or define('APP_DB_PASSWORD', empty($dsn['password']) ? APP_DB_PASSWORD_DEFAULT : $dsn['password']);
defined('APP_DB_NAME')     or define('APP_DB_NAME',     empty($dsn['dbname']) ? APP_DB_NAME_DEFAULT : $dsn['dbname']);
if (perfex_saas_is_tenant())
    define('APP_DB_PREFIX',  perfex_saas_tenant_db_prefix(perfex_saas_tenant_slug()));

// Run middlewares for the tenant. i.e permission and module control. Also add important hooks.
perfex_saas_middleware();




/******************* EARLY TIME RQUIRED HOOKS **********************************/
/**
 * Early time hooks for email template.
 * Must be placed here in hooks to ensure its loaded with perfex email template loading.
 */
hooks()->add_filter('register_merge_fields', 'perfex_saas_email_template_merge_fields');
function perfex_saas_email_template_merge_fields($fields)
{
    $fields[] =  'perfex_saas/merge_fields/perfex_saas_company_merge_fields';
    return $fields;
}

/**
 * Media file folder.
 * Set max number for priority to ensure the function is more or less the last to be called.
 * However, we nee to set the hook in early part of execution to ensure its availability to other script using media folder.
 */
hooks()->add_filter('get_media_folder', 'perfex_saas_set_media_folder_hook', PHP_INT_MAX);
function perfex_saas_set_media_folder_hook($data)
{
    $tenant_slug = perfex_saas_is_tenant() ? perfex_saas_tenant_slug() : perfex_saas_master_tenant_slug();
    if (empty($tenant_slug)) throw new \Exception("Media Error: Error Processing Request", 1);

    return $data . '/' . $tenant_slug;
}

/********OTHER MIDDLEWARE SPECIFIC HOOKS ******/
$folder_path = __DIR__ . '/my_hooks/';
$feature_hook_files = glob($folder_path . '*.php');
$feature_hook_files = hooks()->apply_filters('perfex_saas_extra_middleware_hook_files', $feature_hook_files);
foreach ($feature_hook_files as $file) {
    if (is_file($file)) {
        require_once $file;
    }
}