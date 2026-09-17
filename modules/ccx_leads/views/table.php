<?php

defined('BASEPATH') or exit('No direct script access allowed');

$aColumns = [
    '1', // Bulk actions
    db_prefix() . 'leads.id as id',
    db_prefix() . 'leads.name as name',
    db_prefix() . 'leads.email as email',
    db_prefix() . 'leads.phonenumber as phonenumber',
    db_prefix() . 'leads.assigned as assigned',
    db_prefix() . 'leads.status as status',
    db_prefix() . 'leads.lastcontact as lastcontact',
    db_prefix() . 'leads.dateadded as dateadded',
];

$sIndexColumn = 'id';
$sTable = db_prefix() . 'leads';

$where = [];

if ($this->ci->input->post('custom_view')) {
    $custom_view = $this->ci->input->post('custom_view');

    if ($custom_view == 'junk') {
        array_push($where, 'AND junk = 1');
    } elseif ($custom_view == 'lost') {
        array_push($where, 'AND lost = 1');
    } elseif (strpos($custom_view, 'source_') === 0) {
        $source_id = intval(str_replace('source_', '', $custom_view));
        array_push($where, 'AND ' . db_prefix() . 'leads.source = ' . $source_id);
        array_push($where, 'AND junk = 0 AND lost = 0');
    } elseif (is_numeric($custom_view)) {
        array_push($where, 'AND ' . db_prefix() . 'leads.status = ' . $custom_view);
        // Exclude junk and lost when filtering by a specific status
        array_push($where, 'AND junk = 0 AND lost = 0');
    } elseif ($custom_view == 'all' || $custom_view == '') {
        // Default "All" behaviour: show all leads EXCEPT junk and lost
        array_push($where, 'AND junk = 0 AND lost = 0');
    }
} else {
    // Initial load default: exclude junk and lost
    array_push($where, 'AND junk = 0 AND lost = 0');
}

$result = data_tables_init($aColumns, $sIndexColumn, $sTable, [], $where, [
    // Additional columns to fetch but not display
    'junk',
    'lost',
    'source'
]);

$output = $result['output'];
$rResult = $result['rResult'];

foreach ($rResult as $aRow) {
    $row = [];

    // Bulk actions
    $row[] = '<div class="checkbox"><input type="checkbox" value="' . $aRow['id'] . '"><label></label></div>';

    $row[] = $aRow['id'];

    // Name
    $nameRow = '<a href="#" onclick="ccx_lead_profile(' . $aRow['id'] . '); return false;">' . $aRow['name'] . '</a>';
    $nameRow .= '<div class="row-options">';
    $nameRow .= '<a href="#" onclick="ccx_lead_profile(' . $aRow['id'] . '); return false;">' . _l('view') . '</a>';
    $nameRow .= ' | <a href="#" onclick="ccx_lead_profile(' . $aRow['id'] . '); return false;">' . _l('edit') . '</a>';
    $nameRow .= '</div>';
    $row[] = $nameRow;

    $row[] = ($aRow['email'] != '' ? '<a href="mailto:' . $aRow['email'] . '">' . $aRow['email'] . '</a>' : '');
    $row[] = ($aRow['phonenumber'] != '' ? '<a href="tel:' . $aRow['phonenumber'] . '">' . $aRow['phonenumber'] . '</a>' : '');

    // Assigned
    $assignedOutput = '';
    if ($aRow['assigned'] != 0) {
        $full_name = get_staff_full_name($aRow['assigned']);
        $assignedOutput = '<a href="' . admin_url('profile/' . $aRow['assigned']) . '">' . staff_profile_image($aRow['assigned'], [
            'staff-profile-image-small',
        ]) . '</a>';
        // Add line break for split view
        $assignedOutput .= '<br /><a href="' . admin_url('profile/' . $aRow['assigned']) . '">' . $full_name . '</a>';
    }
    $row[] = $assignedOutput;

    // Status
    // Status
    $CI = &get_instance();
    $status = null;
    if (is_numeric($aRow['status'])) {
        $status = $CI->leads_model->get_status($aRow['status']);
    }

    $statusOutput = '';
    if ($status && is_object($status)) {
        $statusOutput = '<span class="label label-default inline-block" style="color:' . $status->color . ';border:1px solid ' . $status->color . '">' . $status->name . '</span>';
    } else {
        $statusOutput = $aRow['status'];
    }
    $row[] = $statusOutput;

    $row[] = ($aRow['lastcontact'] ? '<span class="text-has-action" data-toggle="tooltip" data-title="' . _dt($aRow['lastcontact']) . '">' . time_ago($aRow['lastcontact']) . '</span><br /><span class="text-muted">' . _dt($aRow['lastcontact']) . '</span>' : '');

    $row[] = ($aRow['dateadded'] ? '<span class="text-has-action" data-toggle="tooltip" data-title="' . _dt($aRow['dateadded']) . '">' . time_ago($aRow['dateadded']) . '</span><br /><span class="text-muted">' . _dt($aRow['dateadded']) . '</span>' : '');

    $output['aaData'][] = $row;
}
