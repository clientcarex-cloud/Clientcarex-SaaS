<?php

defined('BASEPATH') or exit('No direct script access allowed');

$aColumns = [
    db_prefix() . 'clients.company',
    db_prefix() . 'ccx_msgs_pricing.plan_name',
    'amount',
    db_prefix() . 'ccx_msgs_recharge_logs.status',
    'gateway_used',
    'gateway_txn_id',
    'invoice_id',
    db_prefix() . 'ccx_msgs_recharge_logs.created_at',
];

$sIndexColumn = 'id';
$sTable       = db_prefix() . 'ccx_msgs_recharge_logs';

$join = [
    'LEFT JOIN ' . db_prefix() . 'clients ON ' . db_prefix() . 'clients.userid = ' . db_prefix() . 'ccx_msgs_recharge_logs.client_id',
    'LEFT JOIN ' . db_prefix() . 'ccx_msgs_pricing ON ' . db_prefix() . 'ccx_msgs_pricing.id = ' . db_prefix() . 'ccx_msgs_recharge_logs.plan_id',
    'LEFT JOIN ' . db_prefix() . 'invoices ON ' . db_prefix() . 'invoices.id = ' . db_prefix() . 'ccx_msgs_recharge_logs.invoice_id',
];

$where  = [];

$result = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, [
    db_prefix() . 'ccx_msgs_recharge_logs.id',
    db_prefix() . 'invoices.hash',
    db_prefix() . 'ccx_msgs_pricing.currency_id',
]);

$output  = $result['output'];
$rResult = $result['rResult'];

// Pre-load currencies
$base_currency = get_base_currency();
$CI =& get_instance();
$CI->load->model('currencies_model');

// Safe check for cart_items feature
$has_cart_col = false;
if ($CI->db->table_exists(db_prefix() . 'ccx_msgs_checkout_sessions')) {
    $has_cart_col = $CI->db->field_exists('cart_items', db_prefix() . 'ccx_msgs_checkout_sessions');
}

// Cache: invoice_id → cart details
$cart_cache = [];

foreach ($rResult as $aRow) {
    $row = [];
    $invoice_id = $aRow['invoice_id'];

    // ═══ Fetch cart data for this row ═══
    $cart_count = 0;
    $cart_html = '';
    if ($has_cart_col && !empty($invoice_id)) {
        if (!isset($cart_cache[$invoice_id])) {
            $cart_session = $CI->db->select('cart_items')
                ->where('invoice_id', $invoice_id)
                ->get(db_prefix() . 'ccx_msgs_checkout_sessions')
                ->row();

            if ($cart_session && !empty($cart_session->cart_items)) {
                $cart_arr = json_decode($cart_session->cart_items, true);
                if (is_array($cart_arr) && count($cart_arr) > 1) {
                    $detail_rows = '';
                    foreach ($cart_arr as $ci) {
                        $ci_plan = $CI->db->where('id', $ci['plan_id'])->get(db_prefix() . 'ccx_msgs_pricing')->row();
                        if (!$ci_plan) continue;
                        $ci_currency_id = isset($ci_plan->currency_id) ? (int) $ci_plan->currency_id : 0;
                        $ci_currency = ($ci_currency_id > 0) ? $CI->currencies_model->get($ci_currency_id) : $base_currency;
                        if (!$ci_currency) $ci_currency = $base_currency;

                        $ci_price = (float)$ci_plan->price;
                        $ci_discount = isset($ci_plan->discount_percent) ? (float)$ci_plan->discount_percent : 0;
                        if ($ci_discount > 0) {
                            $ci_price = $ci_price - ($ci_price * ($ci_discount / 100));
                        }

                        $type_label = ucfirst(str_replace('_', ' ', isset($ci_plan->message_type) ? $ci_plan->message_type : ''));
                        $subtype_label = (isset($ci_plan->message_subtype) && $ci_plan->message_subtype == 'transactional') ? 'Transactional' : 'Promotional';
                        $msg_count = isset($ci_plan->message_count) ? number_format($ci_plan->message_count) : '0';
                        $expiry = isset($ci_plan->expiry_days) ? $ci_plan->expiry_days : '-';

                        $detail_rows .= '<tr>'
                            . '<td style="padding:8px 12px; font-weight:600;">' . htmlspecialchars($ci_plan->plan_name) . '</td>'
                            . '<td style="padding:8px 12px;"><span class="label label-default">' . $type_label . '</span></td>'
                            . '<td style="padding:8px 12px;"><span class="label ' . ($subtype_label == 'Transactional' ? 'label-info' : 'label-warning') . '">' . $subtype_label . '</span></td>'
                            . '<td style="padding:8px 12px;">' . $msg_count . ' credits</td>'
                            . '<td style="padding:8px 12px;">' . $expiry . ' days</td>'
                            . '<td style="padding:8px 12px; font-weight:600;">' . app_format_money($ci_price, $ci_currency->name) . '</td>'
                            . '</tr>';
                    }
                    $cart_cache[$invoice_id] = [
                        'count' => count($cart_arr),
                        'html'  => '<div style="padding:10px 20px 10px 40px;">'
                            . '<div style="font-weight:700; font-size:13px; margin-bottom:8px; color:#4a5568;"><i class="fa fa-shopping-cart" style="margin-right:6px; color:#6366f1;"></i>All Items in this Cart (' . count($cart_arr) . ' plans)</div>'
                            . '<table class="table table-condensed" style="margin-bottom:0; background:#f8fafc; border-radius:6px;">'
                            . '<thead><tr style="font-size:11px; text-transform:uppercase; color:#94a3b8;">'
                            . '<th style="padding:6px 12px;">Plan</th>'
                            . '<th style="padding:6px 12px;">Channel</th>'
                            . '<th style="padding:6px 12px;">Type</th>'
                            . '<th style="padding:6px 12px;">Credits</th>'
                            . '<th style="padding:6px 12px;">Validity</th>'
                            . '<th style="padding:6px 12px;">Price</th>'
                            . '</tr></thead>'
                            . '<tbody>' . $detail_rows . '</tbody>'
                            . '</table></div>',
                    ];
                } else {
                    $cart_cache[$invoice_id] = ['count' => 0, 'html' => ''];
                }
            } else {
                $cart_cache[$invoice_id] = ['count' => 0, 'html' => ''];
            }
        }
        $cart_count = $cart_cache[$invoice_id]['count'];
        $cart_html = $cart_cache[$invoice_id]['html'];
    }

    // Client
    $row[] = '<a href="' . admin_url('clients/client/' . $aRow['id']) . '">' . $aRow[db_prefix() . 'clients.company'] . '</a>';

    // Plan — with expand arrow + Cart badge (all inside the same column)
    $plan_cell = '';
    if ($cart_count > 1) {
        $escaped_html = htmlspecialchars($cart_html, ENT_QUOTES, 'UTF-8');
        $plan_cell .= '<a href="#" class="rl-expand-btn" data-cart-html="' . $escaped_html . '" title="View all ' . $cart_count . ' cart items" style="margin-right:8px;"><i class="fa fa-chevron-right"></i></a>';
    }
    $plan_cell .= $aRow[db_prefix() . 'ccx_msgs_pricing.plan_name'];
    if ($cart_count > 1) {
        $plan_cell .= ' <span class="label label-primary" style="font-size:10px; margin-left:4px;"><i class="fa fa-shopping-cart" style="margin-right:2px;"></i>Cart (' . $cart_count . ')</span>';
    }
    $row[] = $plan_cell;

    // Amount
    $log_currency_id = isset($aRow[db_prefix() . 'ccx_msgs_pricing.currency_id']) ? (int) $aRow[db_prefix() . 'ccx_msgs_pricing.currency_id'] : 0;
    $log_currency = ($log_currency_id > 0) ? $CI->currencies_model->get($log_currency_id) : $base_currency;
    if (!$log_currency) { $log_currency = $base_currency; }
    $row[] = app_format_money($aRow['amount'], $log_currency->name);

    // Status
    $status = $aRow[db_prefix() . 'ccx_msgs_recharge_logs.status'];
    if ($status == 'paid') {
        $row[] = '<span class="label label-success">' . _l('paid') . '</span>';
    } elseif ($status == 'failed') {
        $row[] = '<span class="label label-danger">' . _l('failed') . '</span>';
    } elseif ($status == 'initiated') {
        $row[] = '<span class="label label-info"><i class="fa fa-eye" style="margin-right:3px;"></i>' . _l('ccx_msgs_rl_initiated') . '</span>';
    } else {
        $row[] = '<span class="label label-warning">' . _l('pending') . '</span>';
    }

    // Gateway Used
    $gateway = $aRow['gateway_used'];
    if (!empty($gateway)) {
        $row[] = '<span class="label label-default" style="font-size:12px;">' . ucfirst(htmlspecialchars($gateway)) . '</span>';
    } else {
        $row[] = '<span style="color:#a0aec0;">—</span>';
    }

    // Gateway Transaction ID
    $txn_id = $aRow['gateway_txn_id'];
    if (!empty($txn_id)) {
        $short_txn = strlen($txn_id) > 20 ? substr($txn_id, 0, 20) . '…' : $txn_id;
        $row[] = '<span title="' . htmlspecialchars($txn_id) . '" style="cursor:help; font-family:monospace; font-size:12px;">' . htmlspecialchars($short_txn) . '</span>';
    } else {
        $row[] = '<span style="color:#a0aec0;">—</span>';
    }

    // Invoice
    if (!empty($aRow['invoice_id'])) {
        $row[] = '<a href="' . admin_url('invoices/list_invoices/' . $aRow['invoice_id']) . '" target="_blank">' . format_invoice_number($aRow['invoice_id']) . '</a>';
    } else {
        $row[] = '-';
    }

    // Date
    $row[] = _dt($aRow[db_prefix() . 'ccx_msgs_recharge_logs.created_at']);

    $row['DT_RowClass'] = 'has-row-options';
    $output['aaData'][] = $row;
}
