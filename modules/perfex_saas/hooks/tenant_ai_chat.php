<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Tenant-side "Pro AI Chat" floating assistant.
 *
 * Injects a floating AI-chat button + panel on every tenant admin page, giving
 * staff the software guide / training assistant everywhere, WITHOUT requiring
 * the pro_ai_chat module to be activated on the tenant instance.
 *
 * Placed in perfex_saas/hooks/ (same reasoning as tenant_smart_ticket.php and
 * tenant_pro_support.php): pro_ai_chat is the PROVIDER's module and is
 * typically not active on a tenant CRM, so a hook registered inside it would
 * never load on tenant admin pages. The widget assets live in the shared
 * modules/pro_ai_chat tree; AJAX goes same-origin to the middleware bridge
 * route admin/billing/ai_chat (Companies::tenant_ai_chat), which reads/writes
 * the master database cross-DB.
 *
 * Availability is fully master-controlled: the pro_ai_chat_enabled option and
 * the all/selected tenant targeting are read from the master DB on each admin
 * page load (single query, banner-module style).
 */

// Tenant instances only — the master has the full Pro AI Chat module UI.
if (!$is_tenant) {
    return;
}

hooks()->add_action('app_admin_footer', function () {

    if (!function_exists('perfex_saas_is_tenant') || !perfex_saas_is_tenant()) {
        return;
    }
    if (!function_exists('is_staff_logged_in') || !is_staff_logged_in()) {
        return;
    }

    $helper = FCPATH . 'modules/pro_ai_chat/helpers/pro_ai_chat_helper.php';
    if (!is_file($helper)) {
        return; // pro_ai_chat assets not present on this instance
    }
    require_once $helper;

    // Master kill-switch + tenant targeting (one master-DB query per page).
    try {
        if (!pro_ai_chat_enabled_for_tenant()) {
            return;
        }
        $pac_settings = pro_ai_chat_get_settings();
    } catch (\Throwable $e) {
        log_message('error', 'Tenant Pro AI Chat availability check failed: ' . $e->getMessage());

        return;
    }

    $CI = &get_instance();

    // The widget's UI strings ship with pro_ai_chat; load them explicitly since
    // that module's language file isn't auto-registered when it's inactive.
    $lang_path = FCPATH . 'modules/pro_ai_chat/';
    if (is_file($lang_path . 'language/english/pro_ai_chat_lang.php')) {
        $CI->lang->load('pro_ai_chat', 'english', false, true, $lang_path);
    }

    $pac_endpoint       = admin_url('billing/ai_chat');
    $pac_assistant_name = $pac_settings['pro_ai_chat_assistant_name'] !== ''
        ? $pac_settings['pro_ai_chat_assistant_name'] : 'HealthO AI Agent';
    $pac_welcome        = $pac_settings['pro_ai_chat_welcome'];
    $pac_suggestions    = array_slice(array_values(array_filter(array_map(
        'trim',
        preg_split('/\r\n|\r|\n/', (string) ($pac_settings['pro_ai_chat_suggestions'] ?? ''))
    ))), 0, 6);

    $widget = FCPATH . 'modules/pro_ai_chat/views/tenant_widget.php';
    if (is_file($widget)) {
        include $widget;
    }
});
