<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Get the instances marked as demo.
 * Return array of slug of the instances.
 *
 * @return array
 */
function perfex_saas_demo_instances()
{
    $key = 'perfex_saas_demo_instance';
    $instances = get_option($key);
    $instances = empty($instances) ? [] : json_decode($instances);
    return array_filter($instances);
}

/**
 * Get the client id of the demo instances
 *
 * @return array
 */
function perfex_saas_demo_instances_clients()
{
    $demo_instances = perfex_saas_demo_instances();

    // Ensure $demo_instances is an array
    if (is_array($demo_instances) && count($demo_instances) > 0) {

        $CI = &get_instance();
        $CI->db->select('clientid');
        $CI->db->from(perfex_saas_table('companies'));
        $CI->db->where_in('slug', $demo_instances);  // 'slug IN (...)'
        $query = $CI->db->get();
        return array_column($query->result_array(), 'clientid');
    }

    return [];
}

/**
 * Check if the logged client is demo account
 *
 * @param mixed $clientid
 * @return bool
 */
function perfex_saas_client_is_demo_account($clientid = null)
{
    $clientid = $clientid ?? (string)get_client_user_id();
    return  /*!is_admin() && */ is_client_logged_in() && in_array($clientid, perfex_saas_demo_instances_clients());
}

/**
 * Function to manage demo instances resetting.
 *
 * @return void
 */
function perfex_saas_reset_demo_instances()
{
    if (perfex_saas_is_tenant()) return;

    $history_key = 'perfex_saas_demo_instance_last_reset_time';
    $remaining_seconds = perfex_saas_demo_seconds_until_reset();
    if ($remaining_seconds > 0) { // If remaining seconds is greater than zero, exit (not time yet)
        return;
    }

    $CI = &get_instance();
    $instances = perfex_saas_demo_instances();

    foreach ($instances as $slug) {
        $company = $CI->perfex_saas_model->get_company_by_slug($slug);
        if (empty($company->slug)) continue;

        try {
            defined('PERFEX_SAAS_DEPLOYING_DEMO') or define('PERFEX_SAAS_DEPLOYING_DEMO', true);
            perfex_saas_remove_company($company, true);
            perfex_saas_deploy_company($company, true);

            // Run cron for modules setup
            $cron_url = perfex_saas_tenant_cron_url($company, true, true);
            $cron_req = perfex_saas_http_request($cron_url, ['timeout' => 10, 'user_agent' => get_option('perfex_saas_cron_user_agent')]);
            if (!$cron_req || empty($cron_req['response'])) {

                // Install modules by triggering cron
                perfex_saas_trigger_module_install('*', $company->slug);
            }
        } catch (\Throwable $th) {
            log_message('error', $th->getMessage());
        }
    }

    // Update the last reset timestamp
    update_option($history_key, time());
}

/**
 * Check if a the active tenant or given tenant is marked as demo instance.
 *
 * @param object|null $tenant
 * @return bool
 */
function perfex_saas_tenant_is_demo_instance(?object $tenant = null)
{
    if (perfex_saas_is_tenant()) {
        $tenant = $tenant ?? perfex_saas_tenant();
        $instances = perfex_saas_tenant_get_super_option('perfex_saas_demo_instance');
        $instances = empty($instances) ? [] : json_decode($instances);
        return !empty($tenant->slug) && in_array($tenant->slug, $instances);
    }

    if (!$tenant || empty($tenant->slug)) return false;

    return in_array($tenant->slug, perfex_saas_demo_instances());
}

/**
 * Function to render the demo instance credentials on demo instances
 *
 * @return void
 */
function perfex_saas_render_demo_instance_credentials()
{

    if (!perfex_saas_tenant_is_demo_instance()) return;

    $CI = &get_instance();
    $credentials = (array)json_decode(perfex_saas_tenant_get_super_option('perfex_saas_demo_instance_credentials') ?? '', true);
    $credentials = array_filter($credentials, fn ($a) => !empty($a));
    $CI->load->view(
        PERFEX_SAAS_MODULE_NAME . '/includes/demo_instance_credentials',
        [
            'credentials' => $credentials,
            'note' => perfex_saas_tenant_get_super_option('perfex_saas_demo_instance_credentials_note')
        ]
    );
}

function perfex_saas_filter_demo_instance_seed_tables($payload)
{
    // Lock check for demo seeding
    if (!defined('PERFEX_SAAS_DEPLOYING_DEMO')) {
        return $payload;
    }

    // Ensure enabled
    if ((int)get_option('perfex_saas_demo_seeding_clone_mode') !== 1) return $payload;

    // Dont do this if seeding source is master crm
    if (!isset($payload['source_is_super_admin']) || $payload['source_is_super_admin'] == true) return $payload;

    // Ensure the tenant is demo
    if (!isset($payload['company']) || !perfex_saas_tenant_is_demo_instance($payload['company'])) {
        return $payload;
    }

    // Add all the table from the source
    $seed_tables = [];
    $source_dbprefix = perfex_saas_master_db_prefix();

    $source_dsn = $payload['source_dsn'];
    $source_dbprefix = $payload['dbprefix'];

    $db = perfex_saas_load_ci_db_from_dsn($source_dsn, ['dbprefix' => $source_dbprefix]);
    $tables = perfex_saas_get_db_tables_with_prefix($source_dbprefix, $db);

    $restricted_tables = [$source_dbprefix . 'sessions'];
    foreach ($tables as $table) {
        if (!str_starts_with($table, $source_dbprefix) || in_array($table, $restricted_tables)) continue;
        $seed_tables[] = $table;
    }

    $payload['seed_tables'] = $seed_tables;

    return $payload;
}

/**
 * Get seconds remaining until the next demo reset.
 *
 * @return int Seconds left, or 0 if expired.
 */
function perfex_saas_demo_seconds_until_reset()
{
    $key = 'perfex_saas_demo_instance';
    $reset_key = $key . '_reset_hour';
    $history_key = $key . '_last_reset_time';

    if (perfex_saas_is_tenant()) {
        // If called from tenant context, read from super admin
        $options = perfex_saas_get_options([$reset_key, $history_key], true);
        $hours_interval    = (int) ($options[$reset_key] ?? 0);
        $last_reset_stamp  = (int) ($options[$history_key] ?? 0);
    } else {
        $hours_interval    = (int) get_option($reset_key);
        $last_reset_stamp  = (int) get_option($history_key);
    }

    if (empty($hours_interval) || empty($last_reset_stamp)) {
        return 0; // No schedule set
    }

    $next_reset = $last_reset_stamp + ($hours_interval * 3600);
    $remaining  = $next_reset - time();

    return $remaining > 0 ? $remaining : 0;
}

/**
 * Get seconds remaining until the next full hour.
 *
 * @return int
 */
function perfex_saas_seconds_until_next_hour()
{
    // Current timestamp
    $now = time();

    // Convert to the next hour o'clock timestamp
    $next_hour = strtotime(date('Y-m-d H:00:00', $now)) + 3600;

    // Seconds remaining
    return $next_hour - $now;
}