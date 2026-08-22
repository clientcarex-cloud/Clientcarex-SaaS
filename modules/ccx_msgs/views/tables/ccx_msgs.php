<?php

defined('BASEPATH') or exit('No direct script access allowed');

$aColumns = [
    'company',
    db_prefix() . 'ccx_msgs_allocations.sms_promo_count',
    db_prefix() . 'ccx_msgs_allocations.sms_trans_count',
    db_prefix() . 'ccx_msgs_allocations.sms_promo_expiry',
    db_prefix() . 'ccx_msgs_allocations.sms_trans_expiry',
    db_prefix() . 'ccx_msgs_allocations.sms_promo_active',
    db_prefix() . 'ccx_msgs_allocations.sms_trans_active',
    db_prefix() . 'ccx_msgs_allocations.sms_promo_header',
    db_prefix() . 'ccx_msgs_allocations.sms_trans_header',

    db_prefix() . 'ccx_msgs_allocations.whatsapp_promo_count',
    db_prefix() . 'ccx_msgs_allocations.whatsapp_trans_count',
    db_prefix() . 'ccx_msgs_allocations.whatsapp_promo_expiry',
    db_prefix() . 'ccx_msgs_allocations.whatsapp_trans_expiry',
    db_prefix() . 'ccx_msgs_allocations.whatsapp_promo_active',
    db_prefix() . 'ccx_msgs_allocations.whatsapp_trans_active',
    db_prefix() . 'ccx_msgs_allocations.whatsapp_promo_header',
    db_prefix() . 'ccx_msgs_allocations.whatsapp_trans_header',

    db_prefix() . 'ccx_msgs_allocations.email_promo_count',
    db_prefix() . 'ccx_msgs_allocations.email_trans_count',
    db_prefix() . 'ccx_msgs_allocations.email_promo_expiry',
    db_prefix() . 'ccx_msgs_allocations.email_trans_expiry',
    db_prefix() . 'ccx_msgs_allocations.email_promo_active',
    db_prefix() . 'ccx_msgs_allocations.email_trans_active',
    db_prefix() . 'ccx_msgs_allocations.email_promo_header',
    db_prefix() . 'ccx_msgs_allocations.email_trans_header',

    db_prefix() . 'ccx_msgs_allocations.aicall_promo_count',
    db_prefix() . 'ccx_msgs_allocations.aicall_trans_count',
    db_prefix() . 'ccx_msgs_allocations.aicall_promo_expiry',
    db_prefix() . 'ccx_msgs_allocations.aicall_trans_expiry',
    db_prefix() . 'ccx_msgs_allocations.aicall_promo_active',
    db_prefix() . 'ccx_msgs_allocations.aicall_trans_active',
    db_prefix() . 'ccx_msgs_allocations.aicall_promo_header',
    db_prefix() . 'ccx_msgs_allocations.aicall_trans_header',
];

$sIndexColumn = 'userid';
$sTable = db_prefix() . 'clients';

$join = [
    'LEFT JOIN ' . db_prefix() . 'ccx_msgs_allocations ON ' . db_prefix() . 'ccx_msgs_allocations.client_id = ' . db_prefix() . 'clients.userid'
];

$where = [];

$result = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, [
    db_prefix() . 'clients.userid as userid'
]);

$output = $result['output'];
$rResult = $result['rResult'];

$format_expiry = function ($date) {
    if (!$date)
        return _l('ccx_msgs_no_expiry');
    $today = new DateTime(date('Y-m-d'));
    $exp = new DateTime($date);
    if ($today > $exp && $today->format('Y-m-d') !== $exp->format('Y-m-d')) {
        return _d($date) . ' <span class="text-danger">(' . _l('ccx_msgs_expired') . ')</span>';
    } else {
        $diff = $today->diff($exp);
        return _d($date) . ' <span class="text-success">(' . $diff->days . ' ' . _l('ccx_msgs_days_left') . ')</span>';
    }
};

$build_cell = function ($p_c, $p_e, $t_c, $t_e, $p_active, $t_active, $p_header, $t_header) use ($format_expiry) {
    $p_c = $p_c !== null ? $p_c : 0;
    $t_c = $t_c !== null ? $t_c : 0;
    $p_active = $p_active !== null ? (int) $p_active : 1;
    $t_active = $t_active !== null ? (int) $t_active : 1;

    // Promotional
    $p_status = $p_active ? '<span class="label label-success" style="font-size:9px;">Active</span>' : '<span class="label label-danger" style="font-size:9px;">Inactive</span>';
    $html = '<strong>' . _l('ccx_msgs_promo') . ':</strong> <span class="text-info">' . $p_c . '</span> ' . $p_status . '<br>';
    if ($p_header) {
        $html .= '<small class="text-muted"><i class="fa fa-tag"></i> ' . htmlspecialchars($p_header) . '</small><br>';
    }
    $html .= '<small class="text-muted">' . _l('ccx_msgs_expiry') . ': ' . $format_expiry($p_e) . '</small><br>';

    // Transactional
    $t_status = $t_active ? '<span class="label label-success" style="font-size:9px;">Active</span>' : '<span class="label label-danger" style="font-size:9px;">Inactive</span>';
    $html .= '<strong>' . _l('ccx_msgs_trans') . ':</strong> <span class="text-info">' . $t_c . '</span> ' . $t_status . '<br>';
    if ($t_header) {
        $html .= '<small class="text-muted"><i class="fa fa-tag"></i> ' . htmlspecialchars($t_header) . '</small><br>';
    }
    $html .= '<small class="text-muted">' . _l('ccx_msgs_expiry') . ': ' . $format_expiry($t_e) . '</small>';
    return $html;
};

$prefix = db_prefix() . 'ccx_msgs_allocations.';

/**
 * Tenants the provider lends its own WhatsApp number to (WhatsApp module ≥ 2.0).
 *
 * A `free` grant means those WhatsApp messages never touch the balance shown in
 * this row, so the credits column alone would be misleading — the badge says so
 * right where the balance is read. Keyed by client id; grants without one (a
 * tenant added before the column was populated) simply do not annotate a row.
 */
$shared_grants = [];
$shared_table  = db_prefix() . 'whatsapp_shared_grants';

// NOTE: this file is include_once'd from App::get_table_data(), so `$this` is
// the App library, not the controller — the CI instance has to be fetched.
$ccx_ci = &get_instance();

if ($ccx_ci->db->table_exists($shared_table)) {
    // Raw query on purpose — data_tables_init() has just used the query
    // builder, and a plain query() cannot inherit any leftover state from it.
    $ccx_rows = $ccx_ci->db->query(
        "SELECT client_id, enabled, billing_mode FROM `{$shared_table}` WHERE client_id > 0"
    )->result();

    foreach ($ccx_rows as $g) {
        $shared_grants[(int) $g->client_id] = $g;
    }
}
$shared_console_url = admin_url('whatsapp');

foreach ($rResult as $aRow) {
    $row = [];

    $company = $aRow['company'];
    $company .= '<div class="row-options">';
    $company .= '<a href="#" onclick="edit_allocation(' . $aRow['userid'] . '); return false;">' . _l('ccx_msgs_edit') . '</a>';
    $company .= '</div>';

    $row[] = $company;

    // SMS
    $row[] = $build_cell(
        $aRow[$prefix . 'sms_promo_count'],
        $aRow[$prefix . 'sms_promo_expiry'],
        $aRow[$prefix . 'sms_trans_count'],
        $aRow[$prefix . 'sms_trans_expiry'],
        $aRow[$prefix . 'sms_promo_active'],
        $aRow[$prefix . 'sms_trans_active'],
        $aRow[$prefix . 'sms_promo_header'],
        $aRow[$prefix . 'sms_trans_header']
    );

    // WhatsApp
    $whatsapp_cell = $build_cell(
        $aRow[$prefix . 'whatsapp_promo_count'],
        $aRow[$prefix . 'whatsapp_promo_expiry'],
        $aRow[$prefix . 'whatsapp_trans_count'],
        $aRow[$prefix . 'whatsapp_trans_expiry'],
        $aRow[$prefix . 'whatsapp_promo_active'],
        $aRow[$prefix . 'whatsapp_trans_active'],
        $aRow[$prefix . 'whatsapp_promo_header'],
        $aRow[$prefix . 'whatsapp_trans_header']
    );

    if (isset($shared_grants[(int) $aRow['userid']])) {
        $g    = $shared_grants[(int) $aRow['userid']];
        $free = (string) $g->billing_mode === 'free';
        $on   = (int) $g->enabled === 1;

        $whatsapp_cell .= '<br><a href="' . $shared_console_url . '" class="label '
            . (!$on ? 'label-default' : ($free ? 'label-success' : 'label-info'))
            . '" style="font-size:9px;display:inline-block;margin-top:6px;" title="'
            . htmlspecialchars(!$on
                ? 'Shared WhatsApp number access is suspended for this tenant.'
                : ($free
                    ? 'Sends on our WhatsApp number — free, so these credits are not used.'
                    : 'Sends on our WhatsApp number — billed one credit per 24-hour conversation.'))
            . '"><i class="fa fa-handshake-o"></i> ' . _l('ccx_msgs_shared_wa') . ': '
            . ($on ? ($free ? _l('ccx_msgs_shared_wa_free') : _l('ccx_msgs_shared_wa_credits')) : _l('ccx_msgs_shared_wa_off'))
            . '</a>';
    }

    $row[] = $whatsapp_cell;

    // Email
    $row[] = $build_cell(
        $aRow[$prefix . 'email_promo_count'],
        $aRow[$prefix . 'email_promo_expiry'],
        $aRow[$prefix . 'email_trans_count'],
        $aRow[$prefix . 'email_trans_expiry'],
        $aRow[$prefix . 'email_promo_active'],
        $aRow[$prefix . 'email_trans_active'],
        $aRow[$prefix . 'email_promo_header'],
        $aRow[$prefix . 'email_trans_header']
    );

    // AI Call
    $row[] = $build_cell(
        $aRow[$prefix . 'aicall_promo_count'],
        $aRow[$prefix . 'aicall_promo_expiry'],
        $aRow[$prefix . 'aicall_trans_count'],
        $aRow[$prefix . 'aicall_trans_expiry'],
        $aRow[$prefix . 'aicall_promo_active'],
        $aRow[$prefix . 'aicall_trans_active'],
        $aRow[$prefix . 'aicall_promo_header'],
        $aRow[$prefix . 'aicall_trans_header']
    );

    $output['aaData'][] = $row;
}
