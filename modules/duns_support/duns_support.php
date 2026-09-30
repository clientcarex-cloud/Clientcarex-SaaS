<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: DUNS Support
Description: Sell D-U-N-S number registration as a service — a public, ad-ready landing page (/duns-support) with plan picker, document upload and online checkout, plus an orders desk to track payment, the delivery deadline and hand over the DUNS number.
Version: 1.0.0
Requires at least: 2.3.*
*/

define('DUNS_MODULE_NAME', 'duns_support');
define('DUNS_MODULE_VERSION', '1.0.0');

// Needed by the admin screens, the public landing page and the payment hooks
// alike — the payment callback and the gateway webhook are requests of their
// own that never touch a module controller.
require_once __DIR__ . '/helpers/duns_support_helper.php';

register_language_files(DUNS_MODULE_NAME, [DUNS_MODULE_NAME]);

/* ─────────────────────────── Install / upgrade ──────────────────────────── */

register_activation_hook(DUNS_MODULE_NAME, 'duns_module_activation_hook');

function duns_module_activation_hook()
{
    // require, not require_once: install.php declares nothing and is idempotent,
    // and it may run twice in one request (self-heal + SaaS remote activation).
    require(__DIR__ . '/install.php');
}

/** Bring an existing install up to the current schema without a migration. */
function duns_maybe_upgrade_schema()
{
    if (get_option('duns_schema_version') === DUNS_MODULE_VERSION) {
        return;
    }

    require(__DIR__ . '/install.php');
    update_option('duns_schema_version', DUNS_MODULE_VERSION);
}

/* ──────────────────────────────── Hooks ─────────────────────────────────── */

hooks()->add_action('admin_init', 'duns_module_permissions');
hooks()->add_action('admin_init', 'duns_module_init_menu_items');
hooks()->add_action('app_admin_head', 'duns_add_head_components');
hooks()->add_filter('module_duns_support_action_links', 'duns_module_action_links');

// Payments — see the block at the bottom of this file.
hooks()->add_filter('get_option', 'duns_master_gateway_option', 5, 2);
hooks()->add_action('after_payment_added', 'duns_payment_added');
hooks()->add_filter('before_client_view_invoice', 'duns_invoice_redirect');

function duns_module_permissions()
{
    register_staff_capabilities('duns_support', [
        'capabilities' => [
            'view'   => _l('permission_view') . ' (' . _l('permission_global') . ')',
            'edit'   => _l('permission_edit'),
            'delete' => _l('permission_delete'),
        ],
    ], _l('duns_support'));
}

function duns_module_init_menu_items()
{
    duns_maybe_upgrade_schema();

    if (!duns_can('view')) {
        return;
    }

    $CI = &get_instance();
    $CI->app_menu->add_sidebar_menu_item('duns_support', [
        'name'     => _l('duns_support'),
        'href'     => admin_url('duns_support'),
        'icon'     => 'fa-solid fa-id-card',
        'position' => 12,
        'badge'    => [],
    ]);
}

function duns_add_head_components()
{
    $CI = &get_instance();
    if ($CI->router->fetch_module() === DUNS_MODULE_NAME) {
        echo '<link href="' . module_dir_url(DUNS_MODULE_NAME, 'assets/css/duns_admin.css') . '?v=' . DUNS_MODULE_VERSION . '" rel="stylesheet" type="text/css" />';
    }
}

function duns_module_action_links($actions)
{
    $actions[] = '<a href="' . admin_url('duns_support') . '">Orders</a>';
    $actions[] = '<a href="' . admin_url('duns_support/settings') . '">Settings</a>';
    $actions[] = '<a href="' . duns_public_url() . '" target="_blank">Landing page</a>';

    return $actions;
}

/* ════════════════════════════ Payments ═════════════════════════════════
 * The order raises a core invoice the moment the checkout starts, so the
 * gateway has something to attach the payment to. Money landing on that
 * invoice (browser callback or webhook — both end in after_payment_added)
 * moves the order into the delivery queue and starts the delivery clock.
 * Same design as the SHRA /join checkout.
 */

/**
 * On a SaaS tenant, serve the MASTER account's credentials for the gateways
 * ticked in DUNS settings, at read time — the checkout, the gateway callback
 * and the webhook all read through get_option(), and no key is ever written
 * into the tenant's own options. On the master (or a plain install) this is a
 * no-op: its gateway options already are the master's.
 */
function duns_master_gateway_option($value, $name)
{
    if (strncmp($name, 'paymentmethod_', 14) !== 0 || !function_exists('duns_pay_selected_ids')) {
        return $value;
    }

    static $map = null;

    if ($map === null) {
        static $building = false;
        if ($building) {
            return $value;
        }
        $building = true;
        $map      = [];

        $is_tenant = function_exists('perfex_saas_is_tenant') && perfex_saas_is_tenant();

        if ($is_tenant && get_option('duns_pay_use_master') == '1') {
            $ids = duns_pay_selected_ids();
            if (count($ids)) {
                $master = duns_master_options('paymentmethod');

                // Gateway ids nest (paypal / paypal_checkout), so an option belongs
                // to the LONGEST id it starts with. `_initialized` rows list the ids
                // without loading gateway libraries (which would re-enter this filter).
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

/** A payment landed on an invoice — if it is a DUNS order, queue it for delivery. */
function duns_payment_added($payment_id)
{
    $CI = &get_instance();
    $payment = $CI->db->select('invoiceid')->where('id', (int) $payment_id)
        ->get(db_prefix() . 'invoicepaymentrecords')->row();
    if (!$payment) {
        return;
    }

    $CI->load->model('duns_support/duns_support_model');
    $CI->duns_support_model->on_invoice_paid((int) $payment->invoiceid);
}

/**
 * Gateways send the buyer back to the core invoice page when the checkout
 * ends, paid or not. A logged-out DUNS buyer belongs on the order tracking
 * page instead — it shows "paid" or offers the checkout again.
 */
function duns_invoice_redirect($invoice)
{
    if (is_staff_logged_in() || is_client_logged_in()) {
        return $invoice;
    }

    $CI    = &get_instance();
    $order = $CI->db->select('ref')->where('invoice_id', (int) $invoice->id)
        ->get(db_prefix() . 'duns_orders')->row();
    if ($order) {
        redirect(duns_public_url('status/' . $order->ref));
    }

    return $invoice;
}
