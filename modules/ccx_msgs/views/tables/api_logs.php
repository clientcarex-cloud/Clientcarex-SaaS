<?php

defined('BASEPATH') or exit('No direct script access allowed');

$aColumns = [
    '1', // bulk actions checkbox
    db_prefix() . 'ccx_msgs_apis.api_name',
    db_prefix() . 'ccx_msgs_api_logs.tenant_name',
    db_prefix() . 'ccx_msgs_api_logs.status',
    db_prefix() . 'ccx_msgs_api_logs.response_code',
    db_prefix() . 'ccx_msgs_api_logs.triggered_by',
    db_prefix() . 'ccx_msgs_api_logs.execution_time_ms',
    db_prefix() . 'ccx_msgs_api_logs.created_at',
];

$sIndexColumn = db_prefix() . 'ccx_msgs_api_logs.id';
$sTable = db_prefix() . 'ccx_msgs_api_logs';

$join = [
    'LEFT JOIN ' . db_prefix() . 'ccx_msgs_apis ON ' . db_prefix() . 'ccx_msgs_apis.id = ' . db_prefix() . 'ccx_msgs_api_logs.api_id',
];

$where = [];

// Server-side filters
if ($this->ci->input->post('api_id') && $this->ci->input->post('api_id') != '') {
    array_push($where, 'AND ' . db_prefix() . 'ccx_msgs_api_logs.api_id = ' . intval($this->ci->input->post('api_id')));
}
if ($this->ci->input->post('status') && $this->ci->input->post('status') != '') {
    array_push($where, 'AND ' . db_prefix() . 'ccx_msgs_api_logs.status = "' . $this->ci->db->escape_str($this->ci->input->post('status')) . '"');
}

$additionalSelect = [
    db_prefix() . 'ccx_msgs_api_logs.id as log_id',
];

$result = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, $additionalSelect);

$output = $result['output'];
$rResult = $result['rResult'];

foreach ($rResult as $aRow) {
    $row = [];

    // Helper to safely access column values - try both prefixed and unprefixed keys
    $get = function ($col, $table = '') use ($aRow) {
        if ($table && isset($aRow[$table . '.' . $col])) {
            return $aRow[$table . '.' . $col];
        }
        if (isset($aRow[$col])) {
            return $aRow[$col];
        }
        // Try with db_prefix
        $logs_table = db_prefix() . 'ccx_msgs_api_logs';
        $apis_table = db_prefix() . 'ccx_msgs_apis';
        if (isset($aRow[$logs_table . '.' . $col])) {
            return $aRow[$logs_table . '.' . $col];
        }
        if (isset($aRow[$apis_table . '.' . $col])) {
            return $aRow[$apis_table . '.' . $col];
        }
        return '';
    };

    // API Name + View Detail row-option
    $api_name_val = $get('api_name');
    $api_name_val = $api_name_val ? $api_name_val : 'Unknown';
    $log_id = isset($aRow['log_id']) ? $aRow['log_id'] : $get('id');

    // Checkbox for bulk select
    $row[] = '<input type="checkbox" class="log-checkbox" value="' . $log_id . '">';

    $api_name_cell = '<strong>' . htmlspecialchars($api_name_val) . '</strong>';
    $api_name_cell .= '<div class="row-options">';
    $api_name_cell .= '<a href="#" onclick="view_log_detail(' . $log_id . '); return false;"><i class="fa fa-eye"></i> View Detail</a>';
    if (has_permission('ccx_msgs', '', 'delete') || is_admin()) {
        $api_name_cell .= ' | <a href="' . admin_url('ccx_msgs/delete_api_log/' . $log_id) . '" class="text-danger _delete">' . _l('delete') . '</a>';
    }
    $api_name_cell .= '</div>';
    $row[] = $api_name_cell;

    // Tenant Name
    $tenant_name_val = $get('tenant_name');
    if (!empty($tenant_name_val)) {
        $row[] = '<span style="display:inline-block;background:#ede9fe;color:#7c3aed;padding:3px 10px;border-radius:12px;font-size:11px;font-weight:600;">' . htmlspecialchars($tenant_name_val) . '</span>';
    } else {
        $row[] = '<span style="color:#aaa;">—</span>';
    }

    // Status badge
    $status_val = $get('status');
    $status_class = 'log-badge-' . $status_val;
    $row[] = '<span class="' . $status_class . '">' . strtoupper($status_val) . '</span>';

    // Response Code
    $resp_code = (int) $get('response_code');
    $code_color = ($resp_code >= 200 && $resp_code < 300) ? '#2e7d32' : '#c62828';
    $row[] = '<span style="font-weight:600;color:' . $code_color . ';">' . ($resp_code ?: '—') . '</span>';

    // Triggered By
    $triggered = $get('triggered_by');
    $staff_name = '—';
    if (!empty($triggered)) {
        $staff_name = get_staff_full_name($triggered);
    }
    $row[] = $staff_name;

    // Execution Time
    $exec_time = $get('execution_time_ms');
    $row[] = $exec_time ? $exec_time . ' ms' : '—';

    // Date
    $created_at = $get('created_at');
    $row[] = $created_at ? _dt($created_at) : '—';

    $output['aaData'][] = $row;
}
