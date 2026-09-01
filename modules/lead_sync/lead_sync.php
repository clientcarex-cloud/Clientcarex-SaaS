<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Lead Sync
Description: Automatic lead capture from Google Sheets — point it at the sheet your Meta / Instagram / Google lead ads land in and every new row becomes a CRM lead within minutes, mapped, de-duplicated and assigned to an agent. Works with any tenant and any lead source that can write to a sheet.
Version: 1.0.0
Requires at least: 2.3.*
*/

define('LEAD_SYNC_MODULE_NAME', 'lead_sync');
define('LEAD_SYNC_MODULE_VERSION', '1.0.0');

// Lookups, schema and the credential helpers are needed by the admin screens,
// the public webhook and the cron alike, so this is required here rather than
// loaded per controller.
require_once __DIR__ . '/helpers/lead_sync_helper.php';

register_language_files(LEAD_SYNC_MODULE_NAME, [LEAD_SYNC_MODULE_NAME]);

/* ─────────────────────────── Install / upgrade ──────────────────────────── */

register_activation_hook(LEAD_SYNC_MODULE_NAME, 'lead_sync_module_activation_hook');

function lead_sync_module_activation_hook()
{
    // require, not require_once: install.php declares nothing, it is idempotent
    // by design, and it may legitimately run twice in one request — once for the
    // version self-heal and again for a SaaS remote activation.
    require(__DIR__ . '/install.php');
}

/**
 * Bring an existing installation up to the current schema without anyone
 * running a migration — the same pattern the other modules here use.
 */
function lead_sync_maybe_upgrade_schema()
{
    if (get_option('lead_sync_schema_version') === LEAD_SYNC_MODULE_VERSION) {
        return;
    }

    require(__DIR__ . '/install.php');
    update_option('lead_sync_schema_version', LEAD_SYNC_MODULE_VERSION);
}

/* ──────────────────────────── Admin UI hooks ────────────────────────────── */

hooks()->add_action('admin_init', 'lead_sync_module_permissions');
hooks()->add_action('admin_init', 'lead_sync_module_init_menu_items');
hooks()->add_action('app_admin_head', 'lead_sync_add_head_components');
hooks()->add_action('app_admin_footer', 'lead_sync_add_footer_components');
hooks()->add_filter('module_lead_sync_action_links', 'lead_sync_module_action_links');

// Polling rides the core cron: every connection whose interval has elapsed is
// fetched and imported. Instant delivery goes through the webhook instead.
hooks()->add_action('after_cron_run', 'lead_sync_cron');

function lead_sync_module_permissions()
{
    register_staff_capabilities('lead_sync', [
        'capabilities' => [
            'view'   => _l('permission_view') . '(' . _l('permission_global') . ')',
            'create' => _l('permission_create'),
            'edit'   => _l('permission_edit'),
            'delete' => _l('permission_delete'),
        ],
    ], _l('lead_sync'));
}

function lead_sync_module_init_menu_items()
{
    lead_sync_maybe_upgrade_schema();

    if (!lead_sync_can_access()) {
        return;
    }

    get_instance()->app_menu->add_sidebar_menu_item('lead_sync', [
        'name'     => _l('lead_sync'),
        'href'     => admin_url('lead_sync'),
        'icon'     => 'fa-solid fa-file-import',
        'position' => 46, // immediately under the core Leads entry
    ]);
}

function lead_sync_module_action_links($actions)
{
    $actions[] = '<a href="' . admin_url('lead_sync') . '">' . _l('lead_sync_connections') . '</a>';
    $actions[] = '<a href="' . admin_url('lead_sync/settings') . '">' . _l('lead_sync_settings') . '</a>';

    return $actions;
}

/* ───────────────────────────────── Assets ───────────────────────────────── */

function lead_sync_is_module_page()
{
    return get_instance()->router->fetch_class() === 'lead_sync';
}

function lead_sync_asset_ver($relative)
{
    $path = module_dir_path(LEAD_SYNC_MODULE_NAME, $relative);

    return LEAD_SYNC_MODULE_VERSION . '.' . (is_file($path) ? filemtime($path) : 0);
}

function lead_sync_add_head_components()
{
    if (!lead_sync_is_module_page()) {
        return;
    }

    echo '<link href="' . module_dir_url(LEAD_SYNC_MODULE_NAME, 'assets/css/lead_sync.css')
        . '?v=' . lead_sync_asset_ver('assets/css/lead_sync.css') . '" rel="stylesheet" type="text/css" />';
}

function lead_sync_add_footer_components()
{
    if (!lead_sync_is_module_page()) {
        return;
    }

    echo '<script src="' . module_dir_url(LEAD_SYNC_MODULE_NAME, 'assets/js/lead_sync.js')
        . '?v=' . lead_sync_asset_ver('assets/js/lead_sync.js') . '"></script>';
}

/* ────────────────────────────────── Cron ────────────────────────────────── */

/**
 * Poll every connection that is due.
 *
 * The core cron fires far more often than any sheet needs to be read, so the
 * pacing lives on the connection (interval_minutes) and this only keeps a short
 * floor so two overlapping cron runs cannot double-fetch.
 */
function lead_sync_cron()
{
    if (lead_sync_opt('lead_sync_enabled') !== '1') {
        return;
    }

    $CI = &get_instance();
    if (!$CI->db->table_exists(db_prefix() . 'lead_sync_connections')) {
        return;
    }

    $last = (string) get_option('lead_sync_last_cron');
    if ($last !== '' && (time() - strtotime($last)) < 60) {
        return;
    }
    update_option('lead_sync_last_cron', date('Y-m-d H:i:s'));

    $CI->load->model('lead_sync/lead_sync_model');

    foreach ($CI->lead_sync_model->due_connections() as $connection) {
        try {
            $CI->lead_sync_model->run($connection, 'cron');
        } catch (Throwable $e) {
            // One broken sheet must never stop the others, or the rest of cron.
            log_activity('Lead Sync cron error on "' . $connection->name . '": ' . $e->getMessage());
        }
    }

    try {
        $CI->lead_sync_model->purge_old_runs((int) lead_sync_opt('lead_sync_log_retention_days'));
    } catch (Throwable $e) {
        log_activity('Lead Sync log purge error: ' . $e->getMessage());
    }
}
