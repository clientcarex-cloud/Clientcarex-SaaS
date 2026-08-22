<?php
defined('BASEPATH') or exit('No direct script access allowed');

$aColumns = [
    'plan_name',
    'message_type',
    'message_subtype',
    'price',
    'message_count',
    'expiry_days',
    'discount_percent',
];

$sIndexColumn = 'id';
$sTable = db_prefix() . 'ccx_msgs_pricing';

$where = [];
$billing_cycle = $this->ci->input->post('billing_cycle');
if ($billing_cycle) {
    array_push($where, 'AND billing_cycle = "' . $this->ci->db->escape_str($billing_cycle) . '"');
}
$message_type = $this->ci->input->post('message_type');
if ($message_type) {
    array_push($where, 'AND message_type = "' . $this->ci->db->escape_str($message_type) . '"');
}
$message_subtype = $this->ci->input->post('message_subtype');
if ($message_subtype) {
    array_push($where, 'AND message_subtype = "' . $this->ci->db->escape_str($message_subtype) . '"');
}

$result = data_tables_init($aColumns, $sIndexColumn, $sTable, [], $where, [
    'active',
    'offer_description',
    'id',
    'currency_id'
]);

$output = $result['output'];
$rResult = $result['rResult'];

// Pre-load base currency and currencies model for per-plan currency resolution
$base_currency = get_base_currency();
$CI =& get_instance();
$CI->load->model('currencies_model');

foreach ($rResult as $aRow) {
    $row = [];

    // Plan Name
    $plan_name = $aRow['plan_name'];
    if ($aRow['active'] == 0) {
        $plan_name .= ' <span class="label label-danger mleft5">' . _l('inactive') . '</span>';
    }

    $plan_name .= '<div class="row-options">';
    if (has_permission('ccx_msgs', '', 'edit') || is_admin()) {
        $plan_name .= '<a href="#" onclick="edit_plan(' . $aRow['id'] . '); return false;">' . _l('edit') . '</a>';
    }
    if (has_permission('ccx_msgs', '', 'create') || is_admin()) {
        $plan_name .= ' | <a href="#" onclick="duplicate_plan(' . $aRow['id'] . '); return false;"><i class="fa fa-clone"></i> ' . _l('duplicate') . '</a>';
    }
    if (has_permission('ccx_msgs', '', 'delete') || is_admin()) {
        $plan_name .= ' | <a href="' . admin_url('ccx_msgs/delete_plan/' . $aRow['id']) . '" class="text-danger _delete">' . _l('delete') . '</a>';
    }
    $plan_name .= '</div>';

    // Additional Offer Descriptions can be appended here
    if (!empty($aRow['offer_description'])) {
        $plan_name .= '<br/><small class="text-info">' . $aRow['offer_description'] . '</small>';
    }

    $row[] = $plan_name;

    // Type with colored badges
    $type_label = '';
    $badge_style = '';
    switch ($aRow['message_type']) {
        case 'sms':
            $type_label = '<i class="fa fa-comment"></i> ' . _l('ccx_msgs_sms');
            $badge_style = 'background: linear-gradient(135deg, #03a9f4, #0288d1); color: #fff; padding: 4px 12px; border-radius: 12px; font-size: 12px; display: inline-block;';
            break;
        case 'whatsapp':
            $type_label = '<i class="fab fa-whatsapp"></i> ' . _l('ccx_msgs_whatsapp');
            $badge_style = 'background: linear-gradient(135deg, #25d366, #128c7e); color: #fff; padding: 4px 12px; border-radius: 12px; font-size: 12px; display: inline-block;';
            break;
        case 'email':
            $type_label = '<i class="fa fa-envelope"></i> ' . _l('ccx_msgs_email');
            $badge_style = 'background: linear-gradient(135deg, #ff9800, #f57c00); color: #fff; padding: 4px 12px; border-radius: 12px; font-size: 12px; display: inline-block;';
            break;
        case 'aicall':
            $type_label = '<i class="fa fa-phone"></i> ' . _l('ccx_msgs_aicall');
            $badge_style = 'background: linear-gradient(135deg, #9c27b0, #7b1fa2); color: #fff; padding: 4px 12px; border-radius: 12px; font-size: 12px; display: inline-block;';
            break;
    }
    $row[] = '<span style="' . $badge_style . '">' . $type_label . '</span>';

    // Subtype badge (Promotional / Transactional)
    $subtype = isset($aRow['message_subtype']) ? $aRow['message_subtype'] : 'promotional';
    if ($subtype == 'transactional') {
        $row[] = '<span style="background: linear-gradient(135deg, #6366f1, #4f46e5); color: #fff; padding: 4px 12px; border-radius: 12px; font-size: 11px; display: inline-block; font-weight: 500;"><i class="fa fa-exchange" style="margin-right:3px;"></i> ' . _l('ccx_msgs_trans') . '</span>';
    } else {
        $row[] = '<span style="background: linear-gradient(135deg, #f59e0b, #d97706); color: #fff; padding: 4px 12px; border-radius: 12px; font-size: 11px; display: inline-block; font-weight: 500;"><i class="fa fa-bullhorn" style="margin-right:3px;"></i> ' . _l('ccx_msgs_promo') . '</span>';
    }

    // Price, Discount & Final Amount
    $count = (int) $aRow['message_count'];
    $price = (float) $aRow['price'];
    $discount = (float) $aRow['discount_percent'];
    $discount_amount = $price * ($discount / 100);
    $final_price = $price - $discount_amount;

    // Resolve currency per plan (fallback to base currency)
    $plan_currency_id = isset($aRow['currency_id']) ? (int) $aRow['currency_id'] : 0;
    $plan_currency = ($plan_currency_id > 0) ? $CI->currencies_model->get($plan_currency_id) : $base_currency;
    if (!$plan_currency) {
        $plan_currency = $base_currency;
    }

    $price_formatted = app_format_money($price, $plan_currency);
    $final_formatted = app_format_money($final_price, $plan_currency);

    $cpm = 0;
    if ($count > 0 && $final_price > 0) {
        $cpm = $final_price / $count;
    }
    $cpm_formatted = app_format_money($cpm, $plan_currency);

    if ($discount > 0) {
        $row[] = '<s class="text-muted">' . $price_formatted . '</s> <strong class="text-success">' . $final_formatted . '</strong>'
            . '<br><small class="text-muted">' . $cpm_formatted . ' per msg</small>';
    } else {
        $row[] = '<strong>' . $price_formatted . '</strong>'
            . '<br><small class="text-muted">' . $cpm_formatted . ' per msg</small>';
    }

    // Count
    $row[] = number_format($aRow['message_count']);

    // Expiry Days
    $row[] = $aRow['expiry_days'] . ' Days';

    // Discount
    if ($discount > 0) {
        $savings_formatted = app_format_money($discount_amount, $plan_currency);
        $row[] = $discount . '% <br><small class="text-success">Save ' . $savings_formatted . '</small>';
    } else {
        $row[] = '0%';
    }

    $output['aaData'][] = $row;
}
