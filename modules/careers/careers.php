<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Careers
Description: Corporate recruitment suite — publish jobs, internships, apprenticeships and every other kind of opening, receive applications from the public careers website through a secured API, and run candidates through a full ATS pipeline with screening questions, interviews, ratings, notes and automated candidate emails.
Version: 1.0.0
Requires at least: 2.3.*
*/

define('CAREERS_MODULE_NAME', 'careers');
define('CAREERS_MODULE_VERSION', '1.0.0');

// Lookups, schema, uploads and the public row shapes are needed by the admin
// pages, the website API controller and cron alike, so the helper is required
// here rather than loaded per controller.
require_once __DIR__ . '/helpers/careers_helper.php';

register_language_files(CAREERS_MODULE_NAME, [CAREERS_MODULE_NAME]);

/* ─────────────────────────── Install / upgrade ──────────────────────────── */

register_activation_hook(CAREERS_MODULE_NAME, 'careers_module_activation_hook');

function careers_module_activation_hook()
{
    require_once __DIR__ . '/install.php';
}

/* ──────────────────────────── Admin UI hooks ────────────────────────────── */

hooks()->add_action('admin_init', 'careers_module_permissions');
hooks()->add_action('admin_init', 'careers_module_init_menu_items');
hooks()->add_action('app_admin_head', 'careers_add_head_components');
hooks()->add_action('app_admin_footer', 'careers_add_footer_components');
hooks()->add_filter('module_careers_action_links', 'careers_module_action_links');

// Housekeeping rides the core cron: expired postings close themselves,
// interview reminders go out and retention rules are applied.
hooks()->add_action('after_cron_run', 'careers_run_automation');

/**
 * Staff capabilities.
 *
 * Recruitment data is sensitive (salary expectations, personal contact details),
 * so this is an independent group rather than an extension of any other module.
 * `settings` additionally gates the embed widget switch and the departments /
 * locations masters.
 */
function careers_module_permissions()
{
    $capabilities = [];
    $capabilities['capabilities'] = [
        'view'     => _l('permission_view') . '(' . _l('permission_global') . ')',
        'create'   => _l('permission_create'),
        'edit'     => _l('permission_edit'),
        'delete'   => _l('permission_delete'),
        'settings' => _l('careers_perm_settings'),
    ];

    register_staff_capabilities(CAREERS_MODULE_NAME, $capabilities, _l('careers'));
}

/**
 * Sidebar menu. The schema self-heal sits here because admin_init is the first
 * hook that runs with a database on every admin request — an installation that
 * updates the module files without re-activating still gets its new columns.
 */
function careers_module_init_menu_items()
{
    careers_maybe_upgrade_schema();

    if (!careers_can_access()) {
        return;
    }

    $CI = &get_instance();

    $CI->app_menu->add_sidebar_menu_item('careers', [
        'name'     => _l('careers'),
        'href'     => admin_url('careers'),
        'icon'     => 'fa-solid fa-user-tie',
        'position' => 42,
    ]);

    $children = [
        ['slug' => 'careers_dashboard',    'name' => _l('careers_dashboard'),    'href' => admin_url('careers'),               'position' => 5],
        ['slug' => 'careers_jobs',         'name' => _l('careers_openings'),     'href' => admin_url('careers/jobs'),          'position' => 10],
        ['slug' => 'careers_applications', 'name' => _l('careers_applications'), 'href' => admin_url('careers/applications'),  'position' => 15],
        ['slug' => 'careers_pipeline',     'name' => _l('careers_pipeline'),     'href' => admin_url('careers/pipeline'),      'position' => 20],
        ['slug' => 'careers_interviews',   'name' => _l('careers_interviews'),   'href' => admin_url('careers/interviews'),    'position' => 25],
    ];

    if (careers_can_settings()) {
        $children[] = ['slug' => 'careers_setup',    'name' => _l('careers_setup'),    'href' => admin_url('careers/setup'),    'position' => 30];
        $children[] = ['slug' => 'careers_settings', 'name' => _l('careers_settings'), 'href' => admin_url('careers/settings'), 'position' => 35];
    }

    foreach ($children as $child) {
        $CI->app_menu->add_sidebar_children_item('careers', $child);
    }
}

/**
 * "Careers" quick link in the modules list.
 */
function careers_module_action_links($actions)
{
    $actions[] = '<a href="' . admin_url('careers') . '">' . _l('careers') . '</a>';

    return $actions;
}

function careers_is_module_page()
{
    return get_instance()->router->fetch_class() === 'careers';
}

/**
 * Cache-buster for a module asset. The module version alone is not enough:
 * assets change far more often than the version does, and the file's mtime
 * changes on every deploy, so the URL always changes with it.
 */
function careers_asset_ver($relative)
{
    $path = module_dir_path(CAREERS_MODULE_NAME, $relative);
    $time = is_file($path) ? filemtime($path) : 0;

    return CAREERS_MODULE_VERSION . '.' . $time;
}

function careers_add_head_components()
{
    if (!careers_is_module_page()) {
        return;
    }

    echo '<link href="' . module_dir_url(CAREERS_MODULE_NAME, 'assets/css/careers.css')
        . '?v=' . careers_asset_ver('assets/css/careers.css') . '" rel="stylesheet" type="text/css" />';
}

function careers_add_footer_components()
{
    if (!careers_is_module_page()) {
        return;
    }

    echo '<script src="' . module_dir_url(CAREERS_MODULE_NAME, 'assets/js/careers.js')
        . '?v=' . careers_asset_ver('assets/js/careers.js') . '"></script>';
}

/* ───────────────────────────── Automation ───────────────────────────────── */

/**
 * Cron housekeeping, throttled to once every five minutes because the core
 * cron can fire far more often than that.
 *
 *   1. Postings past their deadline are closed (they already stopped showing
 *      on the website — this is what makes the admin list agree with it).
 *   2. Interview reminders to candidate and interviewers, 24h ahead.
 *   3. Retention: rejected candidates older than the configured window are
 *      purged along with their resume file.
 */
function careers_run_automation()
{
    $last = (string) get_option('careers_last_cron');

    if ($last !== '' && (time() - strtotime($last)) < 300) {
        return;
    }
    update_option('careers_last_cron', date('Y-m-d H:i:s'));

    $CI = &get_instance();
    $CI->load->model('careers/careers_model');

    try {
        if (careers_opt_bool('careers_auto_close_expired')) {
            $CI->careers_model->close_expired_jobs();
        }

        $CI->careers_model->send_interview_reminders();

        $retention = (int) careers_opt('careers_retention_days');
        if ($retention > 0) {
            $CI->careers_model->purge_old_applications($retention);
        }
    } catch (Throwable $e) {
        log_activity('Careers cron error: ' . $e->getMessage());
    }
}
