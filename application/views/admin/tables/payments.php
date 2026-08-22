<?php

defined('BASEPATH') or exit('No direct script access allowed');

$hasPermissionDelete = staff_can('delete', 'payments');

$aColumns = [
    db_prefix() . 'invoicepaymentrecords.id as id',
    'invoiceid',
    'paymentmode',
    'transactionid',
    get_sql_select_client_company(),
    'CONCAT(' . db_prefix() . 'staff.firstname, " ", ' . db_prefix() . 'staff.lastname) as staff_name',
    'amount',
    db_prefix() . 'invoicepaymentrecords.date as date',
];

$join = [
    'LEFT JOIN ' . db_prefix() . 'invoices ON ' . db_prefix() . 'invoices.id = ' . db_prefix() . 'invoicepaymentrecords.invoiceid',
    'LEFT JOIN ' . db_prefix() . 'clients ON ' . db_prefix() . 'clients.userid = ' . db_prefix() . 'invoices.clientid',
    'LEFT JOIN ' . db_prefix() . 'currencies ON ' . db_prefix() . 'currencies.id = ' . db_prefix() . 'invoices.currency',
    'LEFT JOIN ' . db_prefix() . 'payment_modes ON ' . db_prefix() . 'payment_modes.id = ' . db_prefix() . 'invoicepaymentrecords.paymentmode',
    'LEFT JOIN ' . db_prefix() . 'staff ON ' . db_prefix() . 'staff.staffid = ' . db_prefix() . 'invoicepaymentrecords.staff_id',
];

$where = [];
if ($clientid != '') {
    array_push($where, 'AND ' . db_prefix() . 'clients.userid=' . $this->ci->db->escape_str($clientid));
}

if (staff_cant('view', 'payments')) {
    $whereUser = '';
    $whereUser .= 'AND (invoiceid IN (SELECT id FROM ' . db_prefix() . 'invoices WHERE (addedfrom=' . get_staff_user_id() . ' AND addedfrom IN (SELECT staff_id FROM ' . db_prefix() . 'staff_permissions WHERE feature = "invoices" AND capability="view_own")))';
    if (get_option('allow_staff_view_invoices_assigned') == 1) {
        $whereUser .= ' OR invoiceid IN (SELECT id FROM ' . db_prefix() . 'invoices WHERE sale_agent=' . get_staff_user_id() . ')';
    }
    $whereUser .= ')';
    array_push($where, $whereUser);
}

$sIndexColumn = 'id';
$sTable = db_prefix() . 'invoicepaymentrecords';

$result = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, [
    'clientid',
    db_prefix() . 'currencies.name as currency_name',
    db_prefix() . 'payment_modes.name as payment_mode_name',
    db_prefix() . 'payment_modes.id as paymentmodeid',
    'paymentmethod',
]);

$output = $result['output'];
$rResult = $result['rResult'];

$this->ci->load->model('payment_modes_model');
$payment_gateways = $this->ci->payment_modes_model->get_payment_gateways(true);

foreach ($rResult as $aRow) {
    $row = [];

    $link = admin_url('payments/payment/' . $aRow['id']);

    $options = icon_btn('payments/payment/' . $aRow['id'], 'fa-regular fa-pen-to-square');

    if ($hasPermissionDelete) {
        $options .= icon_btn('payments/delete/' . $aRow['id'], 'fa fa-remove', 'btn-danger _delete');
    }

    $numberOutput = '<a href="' . $link . '" class="tw-font-medium">' . e($aRow['id']) . '</a>';

    $numberOutput .= '<div class="row-options">';
    $numberOutput .= '<a href="' . $link . '">' . _l('view') . '</a>';
    if ($hasPermissionDelete) {
        $numberOutput .= ' | <a href="' . admin_url('payments/delete/' . $aRow['id']) . '" class="_delete">' . _l('delete') . '</a>';
    }
    $numberOutput .= '</div>';

    $row[] = $numberOutput;

    $row[] = '<a href="' . admin_url('invoices/list_invoices/' . $aRow['invoiceid']) . '">' . e(format_invoice_number($aRow['invoiceid'])) . '</a>';

    $outputPaymentMode = e($aRow['payment_mode_name']);

    // Since version 1.0.1
    if (is_null($aRow['paymentmodeid'])) {
        foreach ($payment_gateways as $gateway) {
            if ($aRow['paymentmode'] == $gateway['id']) {
                $outputPaymentMode = e($gateway['name']);
            }
        }
    }

    if (!empty($aRow['paymentmethod'])) {
        $outputPaymentMode .= ' - ' . e($aRow['paymentmethod']);
    }
    $row[] = $outputPaymentMode;

    $row[] = e($aRow['transactionid']);

    $row[] = '<a href="' . admin_url('clients/client/' . $aRow['clientid']) . '">' . e($aRow['company']) . '</a>';

    $staff_name_output = $aRow['staff_name'];
    if (empty($staff_name_output)) {
        $this->ci->db->select('CONCAT(' . db_prefix() . 'staff.firstname, " ", ' . db_prefix() . 'staff.lastname) as staff_name');
        $this->ci->db->from(db_prefix() . 'activity_log');
        $this->ci->db->join(db_prefix() . 'staff', db_prefix() . 'staff.staffid = ' . db_prefix() . 'activity_log.staffid', 'left');
        $this->ci->db->like('description', 'Payment Recorded [ID:' . $aRow['id'] . ',');
        $this->ci->db->order_by('date', 'ASC');
        $this->ci->db->limit(1);
        $activity = $this->ci->db->get()->row();

        $staff_name_output = ($activity && $activity->staff_name) ? $activity->staff_name : 'System/Online';
    }
    $row[] = e((string) $staff_name_output);

    $row[] = '<span class="tw-font-medium">' . e(app_format_money($aRow['amount'], $aRow['currency_name'])) . '</span>';

    $row[] = e(_d($aRow['date']));

    $row['DT_RowClass'] = 'has-row-options';

    $output['aaData'][] = $row;
}
