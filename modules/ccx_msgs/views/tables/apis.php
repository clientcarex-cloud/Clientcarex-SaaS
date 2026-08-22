<?php

defined('BASEPATH') or exit('No direct script access allowed');

$tbl = db_prefix() . 'ccx_msgs_apis';

$aColumns = [
    $tbl . '.api_name as api_name',
    $tbl . '.message_type as message_type',
    $tbl . '.message_subtype as message_subtype',
    $tbl . '.api_scope as api_scope',
    $tbl . '.api_url as api_url',
    $tbl . '.request_method as request_method',
    $tbl . '.auth_type as auth_type',
    $tbl . '.active as active',
    $tbl . '.is_default as is_default',
];

$sIndexColumn = 'id';
$sTable = db_prefix() . 'ccx_msgs_apis';

$where = [];

// Server-side filters
if ($this->ci->input->post('message_type') && $this->ci->input->post('message_type') != '') {
    array_push($where, 'AND ' . db_prefix() . 'ccx_msgs_apis.message_type = "' . $this->ci->db->escape_str($this->ci->input->post('message_type')) . '"');
}
if ($this->ci->input->post('message_subtype') && $this->ci->input->post('message_subtype') != '') {
    array_push($where, 'AND ' . db_prefix() . 'ccx_msgs_apis.message_subtype = "' . $this->ci->db->escape_str($this->ci->input->post('message_subtype')) . '"');
}
if ($this->ci->input->post('api_scope') && $this->ci->input->post('api_scope') != '') {
    array_push($where, 'AND ' . db_prefix() . 'ccx_msgs_apis.api_scope = "' . $this->ci->db->escape_str($this->ci->input->post('api_scope')) . '"');
}

$join = [
    'LEFT JOIN ' . db_prefix() . 'clients ON ' . db_prefix() . 'clients.userid = ' . db_prefix() . 'ccx_msgs_apis.client_id',
];

$result = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, [
    'id',
    db_prefix() . 'clients.company as client_name',
    'client_id',
]);

$output = $result['output'];
$rResult = $result['rResult'];

foreach ($rResult as $aRow) {
    $row = [];

    // API Name + row-options actions
    $api_name = '<strong>' . htmlspecialchars($aRow['api_name']) . '</strong>';
    $api_name .= '<div class="row-options">';
    if (has_permission('ccx_msgs', '', 'edit') || is_admin()) {
        $api_name .= '<a href="#" onclick="edit_api(' . $aRow['id'] . '); return false;">' . _l('edit') . '</a>';
    }
    $api_name .= ' | <a href="#" onclick="test_api(' . $aRow['id'] . '); return false;" data-test-id="' . $aRow['id'] . '"><i class="fa fa-play"></i> Test</a>';
    $api_name .= ' | <a href="' . admin_url('ccx_msgs/api_logs/' . $aRow['id']) . '"><i class="fa fa-list-alt"></i> Logs</a>';
    if (has_permission('ccx_msgs', '', 'delete') || is_admin()) {
        $api_name .= ' | <a href="' . admin_url('ccx_msgs/delete_api/' . $aRow['id']) . '" class="text-danger _delete">' . _l('delete') . '</a>';
    }
    $api_name .= '</div>';
    $row[] = $api_name;

    // Message Type
    $type_labels = [
        'sms' => '<span class="label label-info">SMS</span>',
        'whatsapp' => '<span class="label label-success">WhatsApp</span>',
        'email' => '<span class="label label-warning">Email</span>',
        'aicall' => '<span class="label" style="background:#9c27b0;color:#fff;">AI Call</span>',
    ];
    $row[] = isset($type_labels[$aRow['message_type']]) ? $type_labels[$aRow['message_type']] : $aRow['message_type'];

    // Sub-type
    $subtype_labels = [
        'promotional' => '<span class="label label-default" style="background:#607d8b;">Promotional</span>',
        'transactional' => '<span class="label label-primary">Transactional</span>',
    ];
    $row[] = isset($subtype_labels[$aRow['message_subtype']]) ? $subtype_labels[$aRow['message_subtype']] : $aRow['message_subtype'];

    // Scope
    $scope = isset($aRow['api_scope']) ? $aRow['api_scope'] : 'global';
    if ($scope === 'client' && !empty($aRow['client_name'])) {
        $row[] = '<span class="label" style="background:#ff9800;color:#fff;"><i class="fa fa-user"></i> ' . htmlspecialchars($aRow['client_name']) . '</span>';
    } else {
        $row[] = '<span class="label" style="background:#03a9f4;color:#fff;"><i class="fa fa-globe"></i> Global</span>';
    }

    // URL (truncated)
    $display_url = strlen($aRow['api_url']) > 50 ? substr($aRow['api_url'], 0, 50) . '...' : $aRow['api_url'];
    $row[] = '<span class="api-url-cell" title="' . htmlspecialchars($aRow['api_url']) . '">' . htmlspecialchars($display_url) . '</span>';

    // Method
    $method_class = 'badge-method-' . strtolower($aRow['request_method']);
    $row[] = '<span class="badge-method ' . $method_class . '">' . $aRow['request_method'] . '</span>';

    // Auth Type
    $auth_labels = [
        'none' => '<span class="text-muted">None</span>',
        'bearer' => '<span class="label label-info"><i class="fa fa-key"></i> Bearer</span>',
        'api_key' => '<span class="label label-default"><i class="fa fa-key"></i> API Key</span>',
        'basic' => '<span class="label label-warning"><i class="fa fa-lock"></i> Basic</span>',
    ];
    $row[] = isset($auth_labels[$aRow['auth_type']]) ? $auth_labels[$aRow['auth_type']] : $aRow['auth_type'];

    // Active
    $active_label = $aRow['active'] == 1
        ? '<span class="label label-success">Active</span>'
        : '<span class="label label-danger">Inactive</span>';
    $row[] = $active_label;

    // Default
    if ($aRow['is_default'] == 1) {
        $row[] = '<span class="label label-warning" style="font-size: 12px;"><i class="fa fa-star"></i> ' . _l('ccx_msgs_is_default') . '</span>';
    } else {
        if (has_permission('ccx_msgs', '', 'edit') || is_admin()) {
            $row[] = '<a href="#" onclick="set_default(' . $aRow['id'] . '); return false;" class="text-muted" style="font-size: 12px;"><i class="fa fa-star-o"></i> ' . _l('ccx_msgs_set_default') . '</a>';
        } else {
            $row[] = '<span class="text-muted">—</span>';
        }
    }

    $output['aaData'][] = $row;
}
