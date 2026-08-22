<?php

defined('BASEPATH') or exit('No direct script access allowed');

$aColumns = [
    'code',
    'code_type',
    'discount_type',
    'discount_value',
    'usage_count',
    'valid_from',
    'valid_until',
    'active',
];

$sIndexColumn = 'id';
$sTable       = db_prefix() . 'ccx_msgs_promo_codes';
$join         = [];
$where        = [];

$result = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, [
    'id',
    'usage_limit',
    'per_client_limit',
    'max_discount_amount',
    'min_order_amount',
    'applicable_channels',
    'referrer_type',
    'referrer_client_id',
    'referrer_staff_id',
    'notes',
]);

$output  = $result['output'];
$rResult = $result['rResult'];

foreach ($rResult as $aRow) {
    $row = [];

    // Code
    $row[] = '<span style="font-family:monospace; font-weight:700; font-size:14px; letter-spacing:1px; color:#1e293b;">' . htmlspecialchars($aRow['code']) . '</span>';

    // Type
    $type = $aRow['code_type'];
    $type_html = '';
    if ($type == 'referral') {
        $type_html = '<span class="label label-info"><i class="fa fa-share-alt" style="margin-right:3px;"></i>Referral</span>';
        // Show referrer name
        $ref_type = isset($aRow['referrer_type']) ? $aRow['referrer_type'] : 'client';
        $referrer_name = '';
        if ($ref_type === 'staff' && !empty($aRow['referrer_staff_id'])) {
            $CI = &get_instance();
            $s = $CI->db->select('firstname, lastname')->where('staffid', $aRow['referrer_staff_id'])->get(db_prefix() . 'staff')->row();
            $referrer_name = $s ? trim($s->firstname . ' ' . $s->lastname) : 'Staff #' . $aRow['referrer_staff_id'];
            $type_html .= '<br><small style="color:#64748b;"><i class="fa fa-user-circle-o" style="margin-right:2px;"></i>' . htmlspecialchars($referrer_name) . ' (Staff)</small>';
        } elseif (!empty($aRow['referrer_client_id'])) {
            $CI = &get_instance();
            $c = $CI->db->select('company')->where('userid', $aRow['referrer_client_id'])->get(db_prefix() . 'clients')->row();
            $referrer_name = $c ? $c->company : 'Client #' . $aRow['referrer_client_id'];
            $type_html .= '<br><small style="color:#64748b;"><i class="fa fa-building-o" style="margin-right:2px;"></i>' . htmlspecialchars($referrer_name) . '</small>';
        }
    } else {
        $type_html = '<span class="label label-primary"><i class="fa fa-tag" style="margin-right:3px;"></i>Promo</span>';
    }
    $row[] = $type_html;

    // Discount
    if ($aRow['discount_type'] == 'percentage') {
        $disc_label = number_format($aRow['discount_value'], 0) . '%';
        if ($aRow['max_discount_amount'] > 0) {
            $disc_label .= ' <small style="color:#94a3b8;">(max ' . app_format_money($aRow['max_discount_amount'], get_base_currency()->name) . ')</small>';
        }
    } else {
        $disc_label = app_format_money($aRow['discount_value'], get_base_currency()->name);
    }
    $row[] = $disc_label;

    // Usage
    $usage_label = $aRow['usage_count'];
    if ($aRow['usage_limit'] > 0) {
        $usage_label .= ' / ' . $aRow['usage_limit'];
    } else {
        $usage_label .= ' / ∞';
    }
    $row[] = $usage_label;

    // Validity
    $validity = '';
    if (!empty($aRow['valid_from'])) {
        $validity .= _d($aRow['valid_from']);
    }
    if (!empty($aRow['valid_until'])) {
        $validity .= ($validity ? ' → ' : '') . _d($aRow['valid_until']);
        if (strtotime($aRow['valid_until']) < time()) {
            $validity .= ' <span class="label label-danger" style="font-size:10px;">Expired</span>';
        }
    }
    if (empty($validity)) $validity = '<span style="color:#94a3b8;">No limit</span>';
    $row[] = $validity;

    // Status
    if ($aRow['active'] == 1) {
        $row[] = '<span class="label label-success">Active</span>';
    } else {
        $row[] = '<span class="label label-default">Inactive</span>';
    }

    // Actions
    $actions = '<div class="tw-flex tw-items-center" style="gap:6px;">';
    $actions .= '<a href="#" onclick="editPromoCode(' . $aRow['id'] . '); return false;" class="btn btn-default btn-icon" data-toggle="tooltip" title="Edit"><i class="fa fa-pencil-square-o"></i></a>';
    $actions .= '<a href="' . admin_url('ccx_msgs/promo_code_delete/' . $aRow['id']) . '" class="btn btn-danger btn-icon _delete" data-toggle="tooltip" title="Delete"><i class="fa fa-trash"></i></a>';
    $actions .= '</div>';
    $row[] = $actions;

    $row['DT_RowClass'] = 'has-row-options';
    $output['aaData'][] = $row;
}
