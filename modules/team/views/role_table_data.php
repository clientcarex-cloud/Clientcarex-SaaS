<?php

defined('BASEPATH') or exit('No direct script access allowed');

$aColumns = [
    'name',
];

$sIndexColumn = 'roleid';
$sTable = db_prefix() . 'roles';

$result = data_tables_init($aColumns, $sIndexColumn, $sTable, [], [], [
    'roleid',
]);

$output = $result['output'];
$rResult = $result['rResult'];

foreach ($rResult as $aRow) {
    $row = [];

    for ($i = 0; $i < count($aColumns); $i++) {
        $_data = $aRow[$aColumns[$i]];
        if ($aColumns[$i] == 'name') {
            $_data = '<a href="' . admin_url('team/role/' . $aRow['roleid']) . '">' . e($_data) . '</a>';
            $_data .= '<span class="mleft10">';
            $_data .= '<span class="badge">' . _l('role_total_users') . ': ' . total_rows(db_prefix() . 'staff', [
                'role' => $aRow['roleid'],
            ]) . '</span>';
            $_data .= '</span>';

            $_data .= '<div class="row-options">';
            $_data .= '<a href="' . admin_url('team/role/' . $aRow['roleid']) . '">' . _l('view') . '</a>';


            $_data .= '</div>';
        }
        $row[] = $_data;
    }

    $row['DT_RowClass'] = 'has-row-options';
    $output['aaData'][] = $row;
}

echo json_encode($output);
die();
