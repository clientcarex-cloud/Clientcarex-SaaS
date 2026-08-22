<?php

defined('BASEPATH') or exit('No direct script access allowed');

$aColumns = [
    'id',
    'name',
    'channel',
    'schedule_date',
    'total_targets',
    'status'
];

$sIndexColumn = 'id';
$sTable       = db_prefix() . 'sms_wa_email_campaigns';

// Channel-wise permission: only list campaigns on channels this staff member
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

$result = data_tables_init($aColumns, $sIndexColumn, $sTable, [], [$channel_where], [
    'processed_count',
    'success_count',
    'failed_count'
]);

$output  = $result['output'];
$rResult = $result['rResult'];

foreach ($rResult as $aRow) {
    $row = [];

    $row[] = $aRow['id'];

    $row[] = '<a href="' . admin_url('sms_wa_email/campaigns/view/' . $aRow['id']) . '">' . $aRow['name'] . '</a>';

    $channel_label = '<span class="label label-default">' . strtoupper(str_replace('_', ' ', $aRow['channel'])) . '</span>';
    if ($aRow['channel'] == 'sms') {
        $channel_label = '<span class="label label-primary">SMS</span>';
    } elseif (strpos($aRow['channel'], 'whatsapp') !== false) {
        $channel_label = '<span class="label label-success">WHATSAPP</span>';
    } elseif ($aRow['channel'] == 'email') {
        $channel_label = '<span class="label label-warning">EMAIL</span>';
    }
    $row[] = $channel_label;

    // Schedule Date with time-left tag (12hr AM/PM format)
    $schedule_html = date('d M Y, h:i A', strtotime($aRow['schedule_date']));
    $schedule_ts = strtotime($aRow['schedule_date']);
    $now_ts = time();

    if ($aRow['status'] == 'pending') {
        if ($schedule_ts > $now_ts) {
            $diff = $schedule_ts - $now_ts;
            if ($diff < 60) {
                $time_left = $diff . 's left';
            } elseif ($diff < 3600) {
                $time_left = round($diff / 60) . 'm left';
            } elseif ($diff < 86400) {
                $time_left = round($diff / 3600, 1) . 'h left';
            } else {
                $time_left = round($diff / 86400) . 'd left';
            }
            $schedule_html .= ' <span class="label label-info" style="font-size:10px;"><i class="fa fa-clock-o"></i> ' . $time_left . '</span>';
        } else {
            $schedule_html .= ' <span class="label label-warning" style="font-size:10px;"><i class="fa fa-exclamation-triangle"></i> Overdue</span>';
        }
    } elseif ($aRow['status'] == 'completed') {
        $schedule_html .= ' <span class="label label-success" style="font-size:10px;"><i class="fa fa-check"></i> Done</span>';
    }
    $row[] = $schedule_html;

    $targets_html = '<b>' . $aRow['total_targets'] . '</b>';
    if ($aRow['status'] != 'pending') {
        $targets_html .= '<br><small class="text-success"><i class="fa fa-check"></i> ' . $aRow['success_count'];
        $targets_html .= ' &nbsp; <span class="text-danger"><i class="fa fa-times"></i> ' . $aRow['failed_count'] . '</span></small>';
    }
    $row[] = $targets_html;

    $status_label = '';
    switch ($aRow['status']) {
        case 'pending':
            $status_label = '<span class="label label-warning">Pending</span>';
            break;
        case 'processing':
            $status_label = '<span class="label label-info">Processing...</span>';
            break;
        case 'completed':
            $status_label = '<span class="label label-success">Completed</span>';
            break;
        case 'failed':
            $status_label = '<span class="label label-danger">Failed</span>';
            break;
        case 'cancelled':
            $status_label = '<span class="label label-default">Cancelled</span>';
            break;
    }
    $row[] = $status_label;

    // View button only — no delete
    $options = '<a href="' . admin_url('sms_wa_email/campaigns/view/' . $aRow['id']) . '" class="btn btn-default btn-icon" data-toggle="tooltip" title="View Details"><i class="fa fa-eye"></i></a>';
    $row[]   = $options;

    $output['aaData'][] = $row;
}
