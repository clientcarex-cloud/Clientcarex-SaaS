<?php

defined('BASEPATH') or exit('No direct script access allowed');

$aColumns = [
    'id',
    'name',
    'channel',
    'time_slots',
    'total_sent_today',
    'is_active',
    'last_run_at'
];

$sIndexColumn = 'id';
$sTable       = db_prefix() . 'sms_wa_email_auto_schedulers';

// Channel-wise permission: only list schedulers on channels this staff member
// may use. Rows store the UI spelling, so "whatsapp" must match
// "official_whatsapp" too.
$allowed_channels = [];
foreach (array_keys(sms_wa_email_allowed_channels()) as $ch) {
    $allowed_channels[] = $ch;
    if ($ch === 'whatsapp') {
        $allowed_channels[] = 'official_whatsapp';
    }
}
$channel_where = empty($allowed_channels)
    ? 'AND 1 = 0'
    : 'AND channel IN (' . implode(',', array_map(function ($c) {
        return "'" . $c . "'";
    }, $allowed_channels)) . ')';

$result = data_tables_init($aColumns, $sIndexColumn, $sTable, [], [$channel_where], []);

$output  = $result['output'];
$rResult = $result['rResult'];

foreach ($rResult as $aRow) {
    $row = [];

    $row[] = $aRow['id'];

    $row[] = '<a href="' . admin_url('sms_wa_email/auto_schedulers/view/' . $aRow['id']) . '">' . htmlspecialchars($aRow['name']) . '</a>';

    // Channel
    $channel_label = '<span class="label label-default">' . strtoupper(str_replace('_', ' ', $aRow['channel'])) . '</span>';
    if ($aRow['channel'] == 'sms') {
        $channel_label = '<span class="label label-primary">SMS</span>';
    } elseif (strpos($aRow['channel'], 'whatsapp') !== false) {
        $channel_label = '<span class="label label-success">WHATSAPP</span>';
    } elseif ($aRow['channel'] == 'email') {
        $channel_label = '<span class="label label-warning">EMAIL</span>';
    }
    $row[] = $channel_label;

    // Time Slots
    $slots = json_decode($aRow['time_slots'], true);
    $slots_html = '';
    if (is_array($slots) && count($slots) > 0) {
        foreach ($slots as $slot) {
            $formatted = date('g:i A', strtotime('2000-01-01 ' . $slot));
            $slots_html .= '<span class="label label-info" style="margin-right:3px; font-size:11px;">' . $formatted . '</span>';
        }
    } else {
        $slots_html = '<span class="text-muted">None</span>';
    }
    $row[] = $slots_html;

    // Sent Today
    $row[] = '<b>' . (int)$aRow['total_sent_today'] . '</b>';

    // Status
    if ($aRow['is_active'] == 1) {
        $row[] = '<span class="label label-success"><i class="fa fa-check"></i> Active</span>';
    } else {
        $row[] = '<span class="label label-default"><i class="fa fa-pause"></i> Paused</span>';
    }

    // Last Run
    if ($aRow['last_run_at']) {
        $row[] = '<small>' . date('d M Y, h:i A', strtotime($aRow['last_run_at'])) . '</small>';
    } else {
        $row[] = '<small class="text-muted">Never</small>';
    }

    // Options
    $options  = '<a href="' . admin_url('sms_wa_email/auto_schedulers/view/' . $aRow['id']) . '" class="btn btn-default btn-icon" data-toggle="tooltip" title="View"><i class="fa fa-eye"></i></a>';
    $options .= ' <a href="' . admin_url('sms_wa_email/auto_schedulers/edit/' . $aRow['id']) . '" class="btn btn-default btn-icon" data-toggle="tooltip" title="Edit"><i class="fa fa-pencil"></i></a>';
    $row[] = $options;

    $output['aaData'][] = $row;
}
