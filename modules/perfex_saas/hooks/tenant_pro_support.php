<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Tenant-side "Pro Support" screen-share agent.
 *
 * Injects the Pro Support agent (acceptance popup + live DOM mirror + remote
 * control receiver) on every tenant admin page, so the SaaS provider's master
 * operator can request a live screen-share and — once a tenant staffer accepts
 * the popup — view and control the tenant's admin screen.
 *
 * Placed here (perfex_saas/hooks) rather than in the pro_support module because
 * that module is the PROVIDER's console and is never activated on a tenant CRM;
 * a hook registered inside it would never load on tenant admin pages. The agent
 * assets live in the shared modules/pro_support/assets tree and talk straight
 * to the master's public agent endpoints (pro_support/agent/*), authenticated
 * with a day-scoped HMAC signature minted here for this tenant's slug.
 */

// Tenant instances only — a provider / standalone admin has no upstream desk.
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

    $helper = FCPATH . 'modules/pro_support/helpers/pro_support_helper.php';
    if (!is_file($helper)) {
        return; // pro_support assets not present on this instance
    }
    require_once $helper;

    if (!function_exists('perfex_saas_tenant_slug') || !function_exists('pro_support_signature')) {
        return;
    }

    $slug = perfex_saas_tenant_slug();
    if (!$slug) {
        return;
    }

    // rrweb is loaded lazily by agent.js only once a session actually starts
    // (~140KB — never a cost on ordinary page views). The vendored file is
    // served from THIS tenant's own origin (same shared codebase).
    $rrweb_v = @filemtime(FCPATH . 'modules/pro_support/assets/vendor/rrweb.min.js') ?: time();

    $cfg = [
        'api'       => pro_support_agent_api_base(),
        'slug'      => $slug,
        'sig'       => pro_support_signature($slug, 0),
        'staffId'   => (int) get_staff_user_id(),
        'staffName' => get_staff_full_name(),
        'rrweb'     => module_dir_url('pro_support', 'assets/vendor/rrweb.min.js') . '?v1.' . $rrweb_v,
        'ws'        => pro_support_ws_url(),
    ];

    $css_url = module_dir_url('pro_support', 'assets/css/agent.css');
    $js_url  = module_dir_url('pro_support', 'assets/js/agent.js');
    $css_v   = @filemtime(FCPATH . 'modules/pro_support/assets/css/agent.css') ?: time();
    $js_v    = @filemtime(FCPATH . 'modules/pro_support/assets/js/agent.js') ?: time();

    echo '<link href="' . $css_url . '?v1.' . $css_v . '" rel="stylesheet" type="text/css" />' . "\n";
    echo '<script>window.PSS_AGENT_CFG = ' . json_encode($cfg, JSON_UNESCAPED_SLASHES) . ';</script>' . "\n";
    echo '<script src="' . $js_url . '?v1.' . $js_v . '"></script>' . "\n";
});

/*
 * TEMPORARY diagnostics — an INDEPENDENT hook so it renders even when the agent
 * injection above bails early (helper missing, empty slug, etc.). Trigger with
 * ?pss_debug=1 on any tenant admin page. Remove this block + debug.js once the
 * handshake is confirmed working.
 */
hooks()->add_action('app_admin_footer', function () {
    if (!function_exists('perfex_saas_is_tenant') || !perfex_saas_is_tenant()) {
        return;
    }
    if (!function_exists('is_staff_logged_in') || !is_staff_logged_in()) {
        return;
    }
    if (!get_instance()->input->get('pss_debug')) {
        return;
    }

    // Report the injection preconditions to the console, then load the panel.
    $helper_present = is_file(FCPATH . 'modules/pro_support/helpers/pro_support_helper.php');
    $precond = [
        'is_tenant'         => true,
        'staff_logged_in'   => true,
        'helper_present'    => $helper_present,
        'slug'              => function_exists('perfex_saas_tenant_slug') ? perfex_saas_tenant_slug() : null,
        'cfg_should_inject' => $helper_present && function_exists('perfex_saas_tenant_slug') && perfex_saas_tenant_slug(),
    ];
    echo '<script>console.log("[pro-support] injection preconditions", ' . json_encode($precond) . ');</script>' . "\n";

    if ($helper_present) {
        $dbg_url = module_dir_url('pro_support', 'assets/js/debug.js');
        $dbg_v   = @filemtime(FCPATH . 'modules/pro_support/assets/js/debug.js') ?: time();
        echo '<script src="' . $dbg_url . '?v1.' . $dbg_v . '"></script>' . "\n";
    }
});
