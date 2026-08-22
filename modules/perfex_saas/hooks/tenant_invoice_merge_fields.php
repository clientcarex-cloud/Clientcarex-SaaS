<?php

defined('BASEPATH') or exit('No direct script access allowed');

if (!$is_tenant) return;


hooks()->add_filter('invoice_merge_fields', function ($fields, $payload) {

    if (isset($fields['{invoice_link}'])) {
        $invoice_id = $payload['id'];
        $invoice = $payload['invoice'];

        // Ensure invoice link use subdomain or custom domain when available 
        $fields['{invoice_link}']       = perfex_saas_tenant_base_url(perfex_saas_tenant(), 'invoice/' . $invoice_id . '/' . $invoice->hash);
    }

    return $fields;
}, 10000, 2);