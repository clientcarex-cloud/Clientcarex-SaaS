<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: WhatsApp
Description: Official WhatsApp Cloud API — connect via Facebook, approved message templates, bulk campaigns, two-way chat inbox with live arrival notifications and auto-reply bot. SaaS central-app model: the provider owns one Meta App, tenants just click Connect.
Version: 2.1.0
Requires at least: 2.3.*
*/

define('WHATSAPP_MODULE_NAME', 'whatsapp');
define('WHATSAPP_MODULE_VERSION', '2.1.0');

// Bumped whenever install.php gains a table/column, so the model re-runs it.
if (!defined('WHATSAPP_SCHEMA_VERSION')) {
    define('WHATSAPP_SCHEMA_VERSION', '2.1.0');
}

// Graph API version used for every OAuth / Cloud API call. Bump in one place.
if (!defined('WHATSAPP_GRAPH_VERSION')) {
    define('WHATSAPP_GRAPH_VERSION', 'v21.0');
}

// OAuth scopes for WhatsApp Business (Embedded Signup / Facebook Login for Business).
if (!defined('WHATSAPP_OAUTH_SCOPES')) {
    define('WHATSAPP_OAUTH_SCOPES', 'whatsapp_business_management,whatsapp_business_messaging,business_management');
}

/**
 * Bridge helpers.
 *
 * These are loaded on EVERY request while the module is active — not just on
 * module pages — because the rest of the platform asks them whether WhatsApp
 * sends should go out over the tenant's own Cloud API number, and how the
 * 24-hour conversation should be billed. Both files are function definitions
 * only; the heavy helper + model are pulled in on demand.
 */
require_once __DIR__ . '/helpers/whatsapp_shared_helper.php';
require_once __DIR__ . '/helpers/whatsapp_credits_helper.php';
require_once __DIR__ . '/helpers/whatsapp_omni_helper.php';

/**
 * Inbox notifications — loaded everywhere too, because the webhook writes the
 * bell entries and every admin page injects the live notifier.
 */
require_once __DIR__ . '/helpers/whatsapp_notify_helper.php';

/**
 * Activation hook — build the schema + seed options.
 */
register_activation_hook(WHATSAPP_MODULE_NAME, 'whatsapp_module_activation_hook');
function whatsapp_module_activation_hook()
{
    $CI = &get_instance();
    require_once(__DIR__ . '/install.php');

    // Activating the module is what switches Omni Messaging's Official
    // WhatsApp channel onto the tenant's own number, so map their approved
    // templates onto that channel right away. No-ops until a WABA is actually
    // connected — the Omni tab and every template sync retry it.
    if (function_exists('whatsapp_omni_autosync_templates')) {
        whatsapp_omni_autosync_templates(true);
    }
}

/**
 * Register language files.
 */
register_language_files(WHATSAPP_MODULE_NAME, [WHATSAPP_MODULE_NAME]);

/**
 * Admin hooks.
 */
hooks()->add_action('admin_init', 'whatsapp_module_init_menu_items');
hooks()->add_action('admin_init', 'whatsapp_module_permissions');
hooks()->add_action('app_admin_head', 'whatsapp_add_head_components');
hooks()->add_action('app_admin_footer', 'whatsapp_add_footer_components');
hooks()->add_action('app_admin_footer', 'whatsapp_inject_inbox_notifier');
hooks()->add_action('after_cron_run', 'whatsapp_cron_automation');

/**
 * Dashboard tab → staff capability.
 *
 * One capability per tab, so a non-admin can be given exactly the tabs they
 * need (e.g. a receptionist gets Inbox + Send, marketing gets Bulk +
 * Templates). Order matters: the view renders the tabs in this order and
 * opens the first one the staff member is allowed to see.
 */
function whatsapp_tab_capabilities()
{
    return [
        'overview'  => 'view',
        'inbox'     => 'inbox',
        'send'      => 'send',
        'bulk'      => 'bulk',
        'templates' => 'templates',
        'bot'       => 'bot',
        'profile'   => 'profile',
        'contacts'  => 'contacts',
        'settings'  => 'settings',
    ];
}

/**
 * Staff capabilities.
 */
function whatsapp_module_permissions()
{
    $capabilities = [];
    $capabilities['capabilities'] = [
        'view'      => _l('wapi_perm_view'),
        'inbox'     => _l('wapi_perm_inbox'),
        'send'      => _l('wapi_perm_send'),
        'bulk'      => _l('wapi_perm_bulk'),
        'templates' => _l('wapi_perm_templates'),
        'bot'       => _l('wapi_perm_bot'),
        'profile'   => _l('wapi_perm_profile'),
        'contacts'  => _l('wapi_perm_contacts'),
        'settings'  => _l('wapi_perm_settings'),
    ];
    $capabilities['help'] = [
        'view'      => _l('wapi_perm_view_help'),
        'inbox'     => _l('wapi_perm_inbox_help'),
        'send'      => _l('wapi_perm_send_help'),
        'bulk'      => _l('wapi_perm_bulk_help'),
        'templates' => _l('wapi_perm_templates_help'),
        'bot'       => _l('wapi_perm_bot_help'),
        'profile'   => _l('wapi_perm_profile_help'),
        'contacts'  => _l('wapi_perm_contacts_help'),
        'settings'  => _l('wapi_perm_settings_help'),
    ];
    register_staff_capabilities(WHATSAPP_MODULE_NAME, $capabilities, _l('wapi_whatsapp'));
}

/**
 * Single gate used by the controller, the view and the menu.
 */
function whatsapp_can($capability)
{
    return is_admin() || staff_can($capability, WHATSAPP_MODULE_NAME);
}

/**
 * Whether the current staff member may see one dashboard tab.
 */
function whatsapp_can_tab($tab)
{
    $map = whatsapp_tab_capabilities();

    return isset($map[$tab]) ? whatsapp_can($map[$tab]) : false;
}

/**
 * Whether the current staff member may open the module at all — true as soon
 * as any single tab is permitted.
 */
function whatsapp_staff_can_access()
{
    foreach (whatsapp_tab_capabilities() as $capability) {
        if (whatsapp_can($capability)) {
            return true;
        }
    }

    return false;
}

/**
 * Sidebar menu.
 */
function whatsapp_module_init_menu_items()
{
    if (!whatsapp_staff_can_access()) {
        return;
    }

    $CI = &get_instance();
    $CI->app_menu->add_sidebar_menu_item('whatsapp-module', [
        'name'     => _l('wapi_whatsapp'),
        'icon'     => 'fa-brands fa-whatsapp',
        'href'     => admin_url('whatsapp'),
        'position' => 27, // Communication cluster
    ]);
}

/**
 * Detect whether the current request targets this module's admin controller.
 *
 * Prefix-agnostic: the master account serves admin under a custom security
 * prefix which shifts the raw URI segments, so trust the routed controller
 * class first and fall back to a regex on the URI.
 */
function whatsapp_is_module_page()
{
    $CI = &get_instance();

    if (isset($CI->router) && method_exists($CI->router, 'fetch_class')
        && $CI->router->fetch_class() === WHATSAPP_MODULE_NAME) {
        return true;
    }

    return (bool) preg_match('#(^|/)admin/' . WHATSAPP_MODULE_NAME . '(/|$)#', $CI->uri->uri_string());
}

/**
 * CSS in <head> on module pages.
 */
function whatsapp_add_head_components()
{
    if (whatsapp_is_module_page()) {
        $css = __DIR__ . '/assets/css/whatsapp.css';
        $v   = 'v1.' . (file_exists($css) ? filemtime($css) : time());
        echo '<link href="' . module_dir_url(WHATSAPP_MODULE_NAME, 'assets/css/whatsapp.css') . '?' . $v . '" rel="stylesheet" type="text/css" />';
    }
}

/**
 * JS in footer on module pages.
 */
function whatsapp_add_footer_components()
{
    if (whatsapp_is_module_page()) {
        $js = __DIR__ . '/assets/js/whatsapp.js';
        $v  = 'v1.' . (file_exists($js) ? filemtime($js) : time());
        echo '<script src="' . module_dir_url(WHATSAPP_MODULE_NAME, 'assets/js/whatsapp.js') . '?' . $v . '"></script>';
    }
}

/**
 * Inject the live inbox notifier on EVERY admin page.
 *
 * The point of the feature is that an inbound WhatsApp message reaches whoever
 * is on duty wherever they are in the CRM, so this deliberately does not check
 * whatsapp_is_module_page(). It stays cheap through option-only guards (Perfex
 * caches the options table per request) before anything touches the DB:
 * nothing renders for staff without the Inbox capability, and nothing renders
 * on an install Meta has never delivered a message to.
 */
function whatsapp_inject_inbox_notifier()
{
    if (!function_exists('whatsapp_can') || !whatsapp_can('inbox')) {
        return;
    }

    $settings = whatsapp_notify_settings();
    if (!$settings['enabled'] || (!$settings['toast'] && !$settings['desktop'] && !$settings['sound'])) {
        return;
    }

    // No webhook has ever landed here → there is no inbox to watch yet, so the
    // queries are skipped entirely on installs that never connected WhatsApp.
    // The module's own pages are exempt: Settings → Test notification has to
    // work before the first customer ever writes in.
    if ((string) get_option('whatsapp_last_webhook_at') === '' && !whatsapp_is_module_page()) {
        return;
    }

    $CI = &get_instance();
    $CI->load->model('whatsapp/whatsapp_model');

    $initial       = $CI->whatsapp_model->get_unread_total();
    $initial_items = $CI->whatsapp_model->get_unread_inbox_items(6);
    $endpoint      = admin_url('whatsapp/unread_inbox');
    $inbox_url     = admin_url('whatsapp');

    include __DIR__ . '/views/_notify_script.php';
}

/**
 * Cron tick — drip-sends bulk campaign queues (self-throttled).
 */
function whatsapp_cron_automation()
{
    whatsapp_run_automation(60);
}

/**
 * Process scheduled + running campaigns in bounded batches.
 *
 * @param int  $throttle_seconds minimum gap between runs (0 = always)
 * @param bool $manual           true when triggered from the UI (bypasses throttle)
 */
function whatsapp_run_automation($throttle_seconds = 60, $manual = false)
{
    if (!$manual) {
        $last = get_option('whatsapp_last_cron_run');
        if ($last !== '' && $last !== false && (time() - (int) $last) < $throttle_seconds) {
            return;
        }
    }
    update_option('whatsapp_last_cron_run', time());

    $CI = &get_instance();
    $CI->load->model('whatsapp/whatsapp_model');
    $CI->whatsapp_model->process_campaign_queue();
}
