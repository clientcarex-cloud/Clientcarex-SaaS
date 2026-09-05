<?php

defined('BASEPATH') or exit('No direct script access allowed');

$aColumns = [
    'firstname',
    'e.employee_code',
    'd.name as department_name',
    'dg.name as designation_name',
    'e.employment_type',
    'e.date_of_joining',
    'e.status as hr_status',
    db_prefix() . 'staff.last_login as last_login',
];

$sIndexColumn = 'staffid';
$sTable       = db_prefix() . 'staff';

$join = [
    'LEFT JOIN ' . db_prefix() . 'hr_employees e ON e.staff_id = ' . db_prefix() . 'staff.staffid',
    'LEFT JOIN ' . db_prefix() . 'departments d ON d.departmentid = e.department_id',
    'LEFT JOIN ' . db_prefix() . 'hr_designations dg ON dg.id = e.designation_id',
];

$where = ['AND ' . db_prefix() . 'staff.is_not_staff = 0'];

$status = $this->ci->input->post('status');
if (is_array($status)) {
    $status = $status[0];
}
if ($status !== null && $status !== '') {
    array_push($where, 'AND ' . db_prefix() . 'staff.active = ' . ($status == '0' ? '0' : '1'));
}

if ($this->ci->input->post('department')) {
    array_push($where, 'AND e.department_id = ' . (int) $this->ci->input->post('department'));
}

if ($this->ci->input->post('employment_type')) {
    array_push($where, 'AND e.employment_type = "' . $this->ci->db->escape_str($this->ci->input->post('employment_type')) . '"');
}

// Dashboard card deep-links (?filter=...). The DataTable posts back to
// window.location.href so the query string survives every reload.
$card_filter = $this->ci->input->get('filter');
if ($card_filter === 'new_joiners') {
    array_push($where, 'AND e.date_of_joining >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)');
} elseif ($card_filter === 'probation') {
    array_push($where, 'AND e.probation_end >= CURDATE()');
}

$result = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, [
    'lastname',
    'staffid',
    db_prefix() . 'staff.email as staff_email',
    'phonenumber',
    'active',
]);

$output  = $result['output'];
$rResult = $result['rResult'];

$type_labels = [
    'permanent' => 'Permanent', 'contract' => 'Contract', 'probation' => 'Probation',
    'consultant' => 'Consultant', 'part_time' => 'Part Time', 'intern' => 'Intern',
];

foreach ($rResult as $aRow) {
    $row = [];

    $name = html_escape($aRow['firstname'] . ' ' . $aRow['lastname']);
    $url  = admin_url('hr/employee/' . $aRow['staffid']);

    $nameHtml = '<div style="display:flex;align-items:center;">';
    $nameHtml .= '<div style="margin-right:10px;">' . staff_profile_image($aRow['staffid'], ['staff-profile-image-small']) . '</div>';
    $nameHtml .= '<div><a href="' . $url . '" style="font-weight:bold;">' . $name . '</a>';
    $nameHtml .= '<div class="text-muted" style="font-size:12px;">' . html_escape($aRow['staff_email']) . '</div></div></div>';
    $row[] = $nameHtml;

    $row[] = $aRow['employee_code'] ? html_escape($aRow['employee_code']) : '<span class="text-muted">-</span>';
    $row[] = $aRow['department_name'] ? html_escape($aRow['department_name']) : '<span class="text-muted">-</span>';
    $row[] = $aRow['designation_name'] ? html_escape($aRow['designation_name']) : '<span class="text-muted">-</span>';
    $row[] = $aRow['employment_type'] ? ($type_labels[$aRow['employment_type']] ?? html_escape($aRow['employment_type'])) : '<span class="text-muted">-</span>';
    $row[] = $aRow['date_of_joining'] ? _d($aRow['date_of_joining']) : '<span class="text-muted">-</span>';

    if ($aRow['active'] != 1) {
        $row[] = '<span class="label label-default" style="border-radius:15px;padding:5px 15px;">Inactive</span>';
    } elseif ($aRow['hr_status'] === 'on_notice') {
        $row[] = '<span class="label label-warning" style="border-radius:15px;padding:5px 15px;">On Notice</span>';
    } elseif ($aRow['hr_status'] === 'exited') {
        $row[] = '<span class="label label-danger" style="border-radius:15px;padding:5px 15px;">Exited</span>';
    } else {
        $row[] = '<span class="label label-success" style="border-radius:15px;padding:5px 15px;">Active</span>';
    }

    if (!empty($aRow['last_login']) && $aRow['last_login'] !== '0000-00-00 00:00:00') {
        $row[] = '<span data-toggle="tooltip" title="' . _dt($aRow['last_login']) . '">' . time_ago($aRow['last_login']) . '</span>';
    } else {
        $row[] = '<span class="text-muted">Never</span>';
    }

    $options = '<a href="' . $url . '" class="btn btn-default btn-icon" data-toggle="tooltip" title="HR Profile"><i class="fa fa-id-card"></i></a>';
    if (has_permission('staff', '', 'view') || is_admin()) {
        $options .= ' <a href="' . admin_url('staff/member/' . $aRow['staffid']) . '" class="btn btn-default btn-icon" data-toggle="tooltip" title="CRM Profile"><i class="fa fa-user"></i></a>';
    }
    $row[] = $options;

    $output['aaData'][] = $row;
}
