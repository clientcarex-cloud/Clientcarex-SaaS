<?php

defined('BASEPATH') or exit('No direct script access allowed');

if ($is_tenant) return;

hooks()->add_filter('before_invoice_added', function ($payload) {

    // We only want to run this in cron to save on performance.
    if (!defined('CRON')) return $payload;

    $data = $payload['data'];
    $is_recurring_from = $data['is_recurring_from'] ?? '';
    if (empty($is_recurring_from)) return $payload;

    $clientid = $data['clientid'];
    // Check if the parent invoice in list of invoices marked as cancel after subscription ends
    $promised_cancel_recurring_invoice = perfex_saas_get_or_save_client_metadata($clientid)['will_cancel_invoice_at_period_end'] ?? '';
    $promised_to_cancel = $promised_cancel_recurring_invoice == $is_recurring_from;
    if (!$promised_to_cancel) return $payload;

    $CI = &get_instance();

    try {

        // Confirm it is not a stripe invoice
        $invoice = $CI->invoices_model->get($is_recurring_from);
        if (!$invoice || ((int)$invoice->subscription_id > 0)) return $payload;

        $CI->perfex_saas_model->cancel_company_invoice($clientid, 'immediately', $invoice);
    } catch (\Throwable $th) {

        $CI->db->where('id', $is_recurring_from);
        $CI->db->where('subscription_id <=', 0);
        $CI->db->update(db_prefix() . 'invoices', [
            'recurring' => 0,
        ]);

        log_message('error', $th->getMessage());
    }

    // ensure the child invoice does not proceed
    throw new \Exception("Child invoice can not be created due to terminating parent invoices", 1);
});