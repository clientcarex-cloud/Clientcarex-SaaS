<?php

defined('BASEPATH') or exit('No direct script access allowed');

$aColumns = [
    'code',
    'description',
    'credits',
    'usage_count',
    'valid_from',
    'valid_until',
    'active',
];

$sIndexColumn = 'id';
$sTable       = db_prefix() . 'ccx_msgs_coupons';
$join         = [];
$where        = [];

$result = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, [
    'id',
    'usage_limit',
    'per_client_limit',
    'expiry_days',
    'notes',
]);

$output  = $result['output'];
$rResult = $result['rResult'];

// Channel display labels & colors
$channel_badges = [
    'sms'           => ['label' => 'SMS',           'bg' => '#eef2ff', 'color' => '#4f46e5'],
    'whatsapp'      => ['label' => 'WhatsApp',      'bg' => '#f0fdf4', 'color' => '#16a34a'],
    'email'         => ['label' => 'Email',         'bg' => '#fffbeb', 'color' => '#d97706'],
    'aicall'        => ['label' => 'AI Call',       'bg' => '#faf5ff', 'color' => '#7c3aed'],
];

foreach ($rResult as $aRow) {
    $row = [];

    // Code
    $row[] = '<span style="font-family:monospace; font-weight:700; font-size:14px; letter-spacing:1px; color:#1e293b;">' . htmlspecialchars($aRow['code']) . '</span>';

    // Description
    $desc = htmlspecialchars(mb_substr($aRow['description'] ?? '', 0, 60));
    if (strlen($aRow['description'] ?? '') > 60) $desc .= '…';
    $row[] = '<span style="color:#6b7280; font-size:13px;">' . ($desc ?: '<em style="color:#d1d5db;">—</em>') . '</span>';

    // Credits
    $credits = json_decode($aRow['credits'], true);
    $credits_html = '';
    if (is_array($credits) && !empty($credits)) {
        foreach ($credits as $ch => $cnt) {
            $badge = isset($channel_badges[$ch]) ? $channel_badges[$ch] : ['label' => ucfirst($ch), 'bg' => '#f3f4f6', 'color' => '#374151'];
            $credits_html .= '<span style="display:inline-block; padding:2px 8px; border-radius:6px; font-size:11px; font-weight:600; background:' . $badge['bg'] . '; color:' . $badge['color'] . '; margin:1px 2px;">' . $badge['label'] . ': ' . number_format($cnt) . '</span>';
        }
    } else {
        $credits_html = '<span style="color:#d1d5db;">—</span>';
    }
    $row[] = $credits_html;

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
    $actions .= '<a href="#" onclick="editCoupon(' . $aRow['id'] . '); return false;" class="btn btn-default btn-icon" data-toggle="tooltip" title="Edit"><i class="fa fa-pencil-square-o"></i></a>';
    $actions .= '<a href="#" onclick="viewClaims(' . $aRow['id'] . '); return false;" class="btn btn-info btn-icon" data-toggle="tooltip" title="View Claims"><i class="fa fa-list-ul"></i></a>';
    $actions .= '<a href="' . admin_url('ccx_msgs/coupon_delete/' . $aRow['id']) . '" class="btn btn-danger btn-icon _delete" data-toggle="tooltip" title="Delete"><i class="fa fa-trash"></i></a>';
    $actions .= '</div>';
    $row[] = $actions;

    $row['DT_RowClass'] = 'has-row-options';
    $output['aaData'][] = $row;
}
