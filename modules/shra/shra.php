<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: SHRA
Description: Stallion Horse Riding Academy — rider self-registration via QR, premium membership & course-completion PDFs, one-screen package billing, trainer attendance and a leakage-proof leads desk for calling agents.
Version: 1.5.3
Requires at least: 2.3.*
*/

define('SHRA_MODULE_NAME', 'shra');
define('SHRA_MODULE_VERSION', '1.5.3');

register_language_files(SHRA_MODULE_NAME, [SHRA_MODULE_NAME]);

hooks()->add_action('admin_init', 'shra_module_init_menu_items');
hooks()->add_action('admin_init', 'shra_module_permissions');
hooks()->add_action('app_admin_head', 'shra_add_head_components');
hooks()->add_action('app_admin_footer', 'shra_add_footer_components');
hooks()->add_filter('module_shra_action_links', 'shra_module_action_links');
hooks()->add_filter('sidebar_menu_items', 'shra_hide_customers_menu');
hooks()->add_action('admin_init', 'shra_default_landing');
hooks()->add_action('lead_created', 'shra_leads_on_core_lead_created');
hooks()->add_action('lead_status_changed', 'shra_leads_on_core_status_changed');
hooks()->add_action('before_lead_deleted', 'shra_leads_before_core_delete');
hooks()->add_action('after_cron_run', 'shra_leads_cron');

// Online payments on /join — see the block at the bottom of this file.
hooks()->add_filter('get_option', 'shra_master_gateway_option', 5, 2);
hooks()->add_action('after_payment_added', 'shra_join_payment_added');
hooks()->add_filter('before_client_view_invoice', 'shra_join_invoice_redirect');

register_activation_hook(SHRA_MODULE_NAME, 'shra_module_activation_hook');

function shra_module_activation_hook()
{
    // require, not require_once: install.php declares nothing, it is idempotent by
    // design, and it may legitimately have to run twice in one process — e.g. once
    // for the master via shra_maybe_upgrade_schema() and again for a tenant during
    // a SaaS remote activation. require_once would silently skip the second run and
    // leave the tenant with an activated module and no tables.
    require(__DIR__ . '/install.php');
}

$CI = &get_instance();
$CI->load->helper(SHRA_MODULE_NAME . '/shra');

/* ───────────────────────────── Access ────────────────────────────────── */

function shra_can($capability = 'view')
{
    if (is_admin()) {
        return true;
    }

    return has_permission('shra', '', $capability);
}

/* Trainers: attendance capability OR any module capability */
function shra_can_attendance()
{
    return is_admin() || has_permission('shra', '', 'attendance') || has_permission('shra', '', 'view');
}

function shra_can_billing()
{
    return is_admin() || has_permission('shra', '', 'billing');
}

function shra_can_access()
{
    return is_admin()
        || has_permission('shra', '', 'view')
        || has_permission('shra', '', 'billing')
        || has_permission('shra', '', 'attendance')
        || shra_leads_can('own');
}

/** Home screen for the current user: agents land on their lead queue. */
function shra_home_url()
{
    if (is_admin() || has_permission('shra', '', 'view')) {
        return admin_url('shra');
    }
    if (shra_leads_can('own')) {
        return admin_url('shra/shra_leads');
    }

    return admin_url(has_permission('shra', '', 'billing') ? 'shra/billing' : 'shra/attendance');
}

/* ───────────────────────────── Menu ──────────────────────────────────── */

function shra_module_init_menu_items()
{
    shra_maybe_upgrade_schema();

    if (!shra_can_access()) {
        return;
    }

    $CI = &get_instance();

    $CI->app_menu->add_sidebar_menu_item('shra', [
        'name'     => _l('shra'),
        'href'     => admin_url('shra'),
        'icon'     => 'fa-solid fa-horse-head',
        'position' => 1,
    ]);

    // Single sidebar entry — the module's own tab bar handles in-page navigation.
}

/**
 * Riders are managed inside SHRA (each rider is still a CRM customer behind
 * the scenes), so the core "Customers" menu only duplicates the Riders tab.
 */
function shra_hide_customers_menu($items)
{
    unset($items['customers']);
    // SHRA is the home screen (see shra_default_landing), so the core Dashboard entry is redundant
    unset($items['dashboard']);

    return $items;
}

/**
 * The academy desk is the home screen: the core dashboard (admin/) sends
 * staff who can use SHRA straight to the module. Any other page is untouched,
 * and the core dashboard stays reachable at admin/dashboard?core=1.
 */
function shra_default_landing()
{
    $CI = &get_instance();
    if ($CI->router->fetch_class() !== 'dashboard' || $CI->router->fetch_method() !== 'index') {
        return;
    }
    if ($CI->input->get('core') || $CI->input->is_ajax_request() || !shra_can_access()) {
        return;
    }
    redirect(shra_home_url());
}

/* ───────────────────────────── Permissions ───────────────────────────── */

function shra_module_permissions()
{
    $capabilities = [];

    $capabilities['capabilities'] = [
        'view'       => _l('permission_view') . '(' . _l('permission_global') . ')',
        'create'     => _l('permission_create'),
        'edit'       => _l('permission_edit'),
        'delete'     => _l('permission_delete'),
        'billing'    => _l('shra_permission_billing'),
        'attendance' => _l('shra_permission_attendance'),
        'leads_own'     => _l('shra_permission_leads_own'),
        'leads_all'     => _l('shra_permission_leads_all'),
        'leads_manage'  => _l('shra_permission_leads_manage'),
        'leads_reports' => _l('shra_permission_leads_reports'),
    ];

    register_staff_capabilities('shra', $capabilities, _l('shra'));
}

function shra_module_action_links($actions)
{
    $actions[] = '<a href="' . admin_url('shra') . '">' . _l('shra_dashboard') . '</a>';
    $actions[] = '<a href="' . admin_url('shra/shra_leads') . '">Leads</a>';
    $actions[] = '<a href="' . admin_url('shra/settings') . '">' . _l('shra_settings') . '</a>';

    return $actions;
}

/* ───────────────────────────── Assets ────────────────────────────────── */

function shra_is_module_page()
{
    return in_array(get_instance()->router->fetch_class(), ['shra', 'shra_leads', 'shra_training']);
}

function shra_asset_ver($relative)
{
    $path = module_dir_path(SHRA_MODULE_NAME, $relative);

    return SHRA_MODULE_VERSION . '.' . (is_file($path) ? filemtime($path) : 0);
}

function shra_add_head_components()
{
    if (!shra_is_module_page()) {
        return;
    }

    echo '<link rel="preconnect" href="https://fonts.googleapis.com">';
    echo '<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">';
    echo '<link href="' . module_dir_url(SHRA_MODULE_NAME, 'assets/css/shra.css') . '?v=' . shra_asset_ver('assets/css/shra.css') . '" rel="stylesheet" type="text/css" />';
}

function shra_add_footer_components()
{
    if (!shra_is_module_page()) {
        return;
    }

    // Lead modals on every SHRA page — the header "New lead" button, and the
    // billing lead match, must work outside the leads tab. Printed before the
    // scripts because shra_leads.js binds to the forms as it loads.
    if (shra_leads_can('own')) {
        extract(shra_lead_modal_vars());
        include module_dir_path(SHRA_MODULE_NAME, 'views/leads/partials/modals.php');
    }

    echo '<script src="' . module_dir_url(SHRA_MODULE_NAME, 'assets/js/shra.js') . '?v=' . shra_asset_ver('assets/js/shra.js') . '"></script>';
    echo '<script src="' . module_dir_url(SHRA_MODULE_NAME, 'assets/js/shra_leads.js') . '?v=' . shra_asset_ver('assets/js/shra_leads.js') . '"></script>';

    if (get_instance()->router->fetch_class() === 'shra_training') {
        echo '<script src="' . module_dir_url(SHRA_MODULE_NAME, 'assets/js/shra_training.js') . '?v=' . shra_asset_ver('assets/js/shra_training.js') . '"></script>';
    }
}

/* ───────────────────────────── Self-Training ─────────────────────────── */

/**
 * The Self-Training card, ready to echo on a home screen.
 *
 * Deliberately self-contained — it loads its own model and renders its own
 * partial — so a host view adds the whole feature with a single echo and no
 * controller change. Returns '' (and costs one table_exists) whenever the
 * feature is off, the tables have not landed yet, or the viewer cannot train.
 *
 * @return string
 */
function shra_training_card()
{
    if (!function_exists('shra_training_can') || !shra_training_can()) {
        return '';
    }
    if (get_option('shra_training_enabled') === '0') {
        return '';
    }

    $CI = &get_instance();
    if (!$CI->db->table_exists(db_prefix() . 'shra_training_modules')) {
        return '';
    }

    $CI->load->model('shra/shra_training_model', 'shra_training');
    $me = (int) get_staff_user_id();

    $modules = $CI->shra_training->modules(true);
    if (!count($modules)) {
        return '';
    }

    $overall = $CI->shra_training->overall($me);
    $stats   = $CI->shra_training->module_stats($me);
    $badges  = shra_training_badges($overall);
    $cheer   = shra_training_cheer($overall['percent'], get_staff_full_name($me));

    ob_start();
    include module_dir_path(SHRA_MODULE_NAME, 'views/training/_card.php');

    return ob_get_clean();
}

/* ───────────────────────────── Leads ↔ core CRM hooks ───────────────── */

/** Native Perfex lead (web-to-lead, email integration, manual) → adopt into the SHRA desk. */
function shra_leads_on_core_lead_created($lead_id)
{
    if (is_array($lead_id)) {
        $lead_id = $lead_id['lead_id'] ?? 0;
    }
    if (!is_numeric($lead_id) || !$lead_id) {
        return;
    }
    $CI = &get_instance();
    if (!$CI->db->table_exists(db_prefix() . 'shra_lead_ext')) {
        return;
    }
    $CI->load->model('shra/shra_leads_model');
    $CI->shra_leads_model->adopt_core_lead((int) $lead_id);
}

/** Status changed from the native leads UI → keep our stage key in sync. */
function shra_leads_on_core_status_changed($data)
{
    if (empty($data['lead_id']) || !isset($data['new_status'])) {
        return;
    }
    $CI = &get_instance();
    if (!$CI->db->table_exists(db_prefix() . 'shra_lead_ext')) {
        return;
    }
    $new = $data['new_status'];
    if (!is_numeric($new)) {
        $row = $CI->db->where('name', $new)->get(db_prefix() . 'leads_status')->row();
        $new = $row ? $row->id : 0;
    }
    $key = shra_lead_stage_key_from_status($new);
    $ext = $CI->db->where('lead_id', (int) $data['lead_id'])->get(db_prefix() . 'shra_lead_ext')->row();
    if ($ext && $ext->stage_key !== $key) {
        $CI->db->where('lead_id', (int) $data['lead_id'])->update(db_prefix() . 'shra_lead_ext', ['stage_key' => $key]);
    }
}

/** Leads with frozen revenue cannot be deleted (audit trail). */
function shra_leads_before_core_delete($lead_id)
{
    $CI = &get_instance();
    if (!$CI->db->table_exists(db_prefix() . 'shra_lead_attribution')) {
        return;
    }
    if ($CI->db->where('lead_id', (int) $lead_id)->get(db_prefix() . 'shra_lead_attribution')->row()) {
        set_alert('danger', 'This lead has revenue credited to an agent and cannot be deleted.');
        redirect(admin_url('leads'));
    }
}

function shra_leads_cron()
{
    $CI = &get_instance();
    if (!$CI->db->table_exists(db_prefix() . 'shra_lead_ext')) {
        return;
    }
    $CI->load->model('shra/shra_leads_model');
    $CI->shra_leads_model->run_cron();
}

/* ───────────────────────────── Schema self-heal ──────────────────────── */

function shra_maybe_upgrade_schema()
{
    if (get_option('shra_schema_version') === SHRA_MODULE_VERSION) {
        return;
    }

    require(__DIR__ . '/install.php');
    update_option('shra_schema_version', SHRA_MODULE_VERSION);
}

/* ═══════════════════ Online payments on the public /join ═══════════════════ */

/**
 * Serve the MASTER account's gateway credentials to this tenant.
 *
 * The academy is a SaaS tenant; the gateways are configured once on the master
 * (admin/settings?group=payment_gateways) and every tenant collects through
 * them. Rather than copying the keys into the tenant options table, the value is
 * swapped in at read time — so the SHRA checkout, the gateway callback and the
 * gateway webhook (three separate requests, all going through get_option) see
 * the same credentials, and nothing secret is ever written to the tenant.
 *
 * Only the gateways ticked in SHRA settings are redirected; every other option
 * is returned untouched.
 *
 * @param  string $value Tenant value
 * @param  string $name  Option name
 * @return string
 */
function shra_master_gateway_option($value, $name)
{
    // The filter is registered before the module helper is loaded — a gateway option
    // read in that window has to fall through untouched.
    if (strncmp($name, 'paymentmethod_', 14) !== 0 || !function_exists('shra_pay_selected_ids')) {
        return $value;
    }

    static $map = null;

    if ($map === null) {
        $map = [];

        // Guard the bootstrap: options are read long before the module tables exist
        // on a fresh install, and re-entering this filter must never recurse.
        static $building = false;
        if ($building) {
            return $value;
        }
        $building = true;

        // Nothing to bridge on the master itself (or on a plain, non-SaaS install) —
        // there the gateway options already ARE the master's.
        $is_tenant = function_exists('perfex_saas_is_tenant') && perfex_saas_is_tenant();

        // None of these names start with paymentmethod_, so the filter short-circuits above.
        if ($is_tenant && get_option('shra_pay_use_master') == '1') {
            $ids = shra_pay_selected_ids();
            if (count($ids)) {
                $master = shra_master_options('paymentmethod');

                // Gateway ids nest — `paypal` is a prefix of `paypal_checkout` and
                // `paypal_braintree` — so an option belongs to the LONGEST id that
                // matches it, never simply to the first. Every gateway writes a
                // `_initialized` row, which is what makes the id list discoverable
                // here without loading the gateway libraries (that would re-enter
                // this filter).
                $known = [];
                foreach ($master as $key => $val) {
                    if (preg_match('/^paymentmethod_(.+)_initialized$/', $key, $m)) {
                        $known[] = $m[1];
                    }
                }
                $known = array_unique(array_merge($known, $ids));
                usort($known, function ($a, $b) { return strlen($b) - strlen($a); });

                foreach ($master as $key => $val) {
                    foreach ($known as $owner) {
                        if (strncmp($key, 'paymentmethod_' . $owner . '_', strlen($owner) + 15) === 0) {
                            if (in_array($owner, $ids, true)) {
                                $map[$key] = $val;
                            }
                            break;
                        }
                    }
                }
            }
        }

        $building = false;
    }

    return array_key_exists($name, $map) ? $map[$name] : $value;
}

/**
 * A payment landed on an invoice — if it belongs to a /join checkout, the rider
 * has paid, so create the enrollment now (it is deliberately not created at
 * checkout time, so abandoned checkouts leave no phantom sessions wallet).
 */
function shra_join_payment_added($payment_id)
{
    $CI = &get_instance();
    if (!$CI->db->table_exists(db_prefix() . 'shra_join_checkouts')) {
        return;
    }

    $payment = $CI->db->select('invoiceid')->where('id', (int) $payment_id)
        ->get(db_prefix() . 'invoicepaymentrecords')->row();
    if (!$payment) {
        return;
    }

    $CI->load->model('shra/shra_model');
    $CI->shra_model->fulfil_join_checkout((int) $payment->invoiceid);
}

/**
 * Gateways send the rider back to the core invoice page when the checkout ends,
 * whether it succeeded or not. For a /join checkout that page is the wrong
 * ending — put the (logged-out) rider back on the academy's own pages: the
 * success page once money has landed, the checkout again if it has not, so a
 * cancelled or failed payment can simply be retried. Staff and signed-in
 * clients still get the normal invoice view.
 */
function shra_join_invoice_redirect($invoice)
{
    $CI = &get_instance();
    if (is_staff_logged_in() || is_client_logged_in() || !$CI->db->table_exists(db_prefix() . 'shra_join_checkouts')) {
        return $invoice;
    }

    $checkout = $CI->db->where('invoice_id', (int) $invoice->id)->get(db_prefix() . 'shra_join_checkouts')->row();
    if (!$checkout) {
        return $invoice;
    }

    $rider = $CI->db->select('rider_no')->where('id', (int) $checkout->rider_id)->get(db_prefix() . 'shra_riders')->row();
    if (!$rider) {
        // Lead-based checkout: no rider exists until the payment is confirmed.
        // Paid → fulfil has already created the rider by the time this filter
        // runs, so landing here means the payment did not go through — put the
        // visitor back on their lead checkout to retry (or walk away, staying a lead).
        if (!empty($checkout->lead_id)) {
            $paid = (float) $CI->db->select_sum('amount')->where('invoiceid', (int) $invoice->id)
                ->get(db_prefix() . 'invoicepaymentrecords')->row()->amount;
            $step = $paid > 0 ? 'done' : 'pay';
            redirect(site_url('join/' . $step . '/L' . $checkout->lead_id . '/' . shra_sign('lead-pay|' . $checkout->lead_id)));
        }

        return $invoice;
    }

    $paid = (float) $CI->db->select_sum('amount')->where('invoiceid', (int) $invoice->id)
        ->get(db_prefix() . 'invoicepaymentrecords')->row()->amount;

    $step = $paid > 0 ? 'done' : 'pay';
    redirect(site_url('join/' . $step . '/' . $rider->rider_no . '/' . shra_sign($rider->rider_no)));
}
