<?php
defined('BASEPATH') or exit('No direct script access allowed');

if (!function_exists('create_subscription_invoice_data')) {
    /**
     * Extend the core create_subscription_invoice_data function and add price description to each invoice item
     *
     * @param mixed $subscription
     * @param mixed $invoice
     * @return mixed
     */
    function create_subscription_invoice_data($subscription, $invoice)
    {
        if (perfex_saas_is_tenant())
            return _create_subscription_invoice_data($subscription, $invoice);

        $CI = &get_instance();
        if ($invoice instanceof stdClass && defined('CLIENTS_AREA') && !empty($subscription->stripe_subscription_id))
            $invoice = $CI->stripe_subscriptions->get_upcoming_invoice($subscription->stripe_subscription_id);

        $subscription_local_package_id = null;
        $cache_id = $subscription->id;

        $items = $invoice->lines->data;

        $reparse = false;
        $default_item_desc = '';
        $i = 0;
        foreach ($items as $key => $item) {
            try {
                $price = $item->price ?? $item['price'] ?? null;
                if (!$price) {
                    $price_id = $item->pricing->price_details->price ?? $item['pricing']['price_details']['price'] ?? null;
                    if ($price_id) {
                        $price = \Stripe\Price::retrieve($price_id);
                    }
                }
                if ($price) {
                    $metadata = $price->metadata ?? (object) $price['metadata'];
                    $group = $metadata->group ?? '';
                    if ($group == 'pcrm_saas') { // We only want to add to SaaS subscription invoice only

                        $meta_desc = $metadata->description ?? '';
                        $default_desc = trim($item['description']);
                        $is_default = ($metadata->resources ?? '') === 'default';
                        if ($is_default) {
                            if ($i !== 0)
                                $reparse = true;
                            $default_item_desc = $price->nickname;
                        }

                        $desc = $meta_desc;
                        if (!starts_with($default_desc, $item->quantity ?? $item['quantity']))
                            $desc .= "<br/>" . $default_desc;

                        $invoice->lines->data[$key]['description'] = $desc;
                        if (!$subscription_local_package_id)
                            $subscription_local_package_id = $metadata->package_id ?? '';
                    }
                }
            } catch (\Throwable $th) {
            }
            $i++;
        }

        if ($reparse && !empty($default_item_desc)) {
            foreach ($items as $key => $item) {
                $qty = $item->quantity ?? $item['quantity'] ?? '';

                $pattern = sprintf(
                    '/\bon\s*(%s|%s\s*[^a-zA-Z0-9]+\s*%s)\b/i',
                    preg_quote($default_item_desc, '/'),
                    preg_quote($qty, '/'),
                    preg_quote($default_item_desc, '/')
                );

                $invoice->lines->data[$key]['description'] = preg_replace(
                    $pattern,
                    '',
                    $invoice->lines->data[$key]['description']
                );
            }
        }

        if ($subscription_local_package_id) {
            hooks()->add_filter('subscription_invoice_data', function ($new_invoice_data) use ($subscription_local_package_id, $cache_id) {
                if ($cache_id == $new_invoice_data['subscription_id']) {
                    $new_invoice_data[perfex_saas_column('packageid')] = $subscription_local_package_id;
                }
                return $new_invoice_data;
            });
        }

        return _create_subscription_invoice_data($subscription, $invoice);
    }
}

// Only return here after declaring the function to ensure the overriden function is also available for the tenants usign stripe
if ($is_tenant)
    return;

$CI = &get_instance();

hooks()->add_action('admin_init', function () {
    if (staff_can('view', 'perfex_saas_packages')) {
        $CI = &get_instance();
        /*$CI->app_menu->add_sidebar_children_item(PERFEX_SAAS_MODULE_WHITELABEL_NAME, [
            'slug' => PERFEX_SAAS_MODULE_WHITELABEL_NAME . '_stripe_pricing',
            'name' => _l('perfex_saas_stripe_pricing'),
            'icon' => 'fa fa-list',
            'href' => admin_url(PERFEX_SAAS_ROUTE_NAME . '/stripe_pricing'),
            'position' => 6,
        ]);*/
    }
});


/**
 * Shadowed libraries
 */
$CI->load->library(PERFEX_SAAS_MODULE_NAME . '/' . PERFEX_SAAS_MODULE_NAME . '_custom_stripe_subscriptions');
$CI->stripe_subscriptions = $CI->perfex_saas_custom_stripe_subscriptions;


/**
 * Make neccessary patch to Perfex for the stripe integration
 *
 * @param bool $forward
 * @return void
 */
function perfex_saas_stripe_setup_patch($forward)
{
    $find = 'function create_subscription_invoice_data';
    $replace = 'function _create_subscription_invoice_data';
    $file = APPPATH . 'helpers/subscriptions_helper.php';
    if ($forward)
        replace_in_file($file, $find, $replace);
    else
        replace_in_file($file, $replace, $find);
}

function perfex_saas_stripe_package_recurring_is_over_three_years($package)
{
    $interval = 'month';
    $interval_count = $package->metadata->invoice->recurring;

    if ($interval_count == 'custom') {
        $interval = $package->metadata->invoice->repeat_type_custom;
        $interval_count = $package->metadata->invoice->repeat_every_custom;
    }

    // Define how many months each interval corresponds to
    $months_per_interval = [
        'day' => 1 / 30, // Approximate month value for a day
        'week' => 1 / 4.33, // Approximate month value for a week
        'month' => 1,
        'year' => 12,
    ];

    // Calculate total months based on interval
    $total_months = isset($months_per_interval[$interval]) ? $interval_count * $months_per_interval[$interval] : 0;

    // Return true if interval count exceeds 3 years (36 months), otherwise false
    return $total_months > 36;
}

hooks()->add_action('perfex_saas_after_installer_run', function () {
    perfex_saas_stripe_setup_patch(true);
});

hooks()->add_action('perfex_saas_after_uninstaller_run', function ($clean) {
    perfex_saas_stripe_setup_patch(false);
});

if (strpos(uri_string(), 'admin/subscriptions/edit') !== false && !empty($_POST)) {
    $subscription_id = explode('/', uri_string());
    $subscription_id = end($subscription_id);
    if (!empty($metadata = perfex_saas_search_client_metadata('subscription_id', (int) $subscription_id))) {
        // alway enforce 1 as quantity for SaaS subscription items to prevent it from being updated in stripe model
        $_POST['quantity'] = 1;
    }
}

// Attempt to sync to stripe when a package is updated.
hooks()->add_action('perfex_saas_after_package_update', function ($package) {
    $CI = &get_instance();
    if (($package->metadata->stripe->enabled ?? '') == '1')
        $CI->perfex_saas_stripe_model->setup_package_on_stripe($package);
});

// Attempt to remove stripe settings from cloned package
hooks()->add_filter('perfex_saas_package_clone_filter', function ($data) {
    $metadata = json_decode($data['entity_data']['metadata'], true);
    if ($data['entity'] == 'packages' && isset($metadata['stripe'])) {
        unset($metadata['stripe']);
        $data['entity_data']['metadata'] = json_encode($metadata);
    }
    return $data;
});


// Hook into Perfex's app_init lifecycle event
// This runs early in the request lifecycle before controller methods execute.
/*hooks()->add_action('app_init', function () {
    $CI = &get_instance();
    $controller = $CI->router->fetch_class();
    $method = $CI->router->fetch_method();
    @todo Test further
    if ($controller === 'stripe' && $method === 'webhook_endpoint') {
        perfex_saas_custom_stripe_webhook_endpoint();
    }
});*/

if (!function_exists('perfex_saas_custom_stripe_webhook_endpoint')) {
    function perfex_saas_custom_stripe_webhook_endpoint()
    {
        $CI = &get_instance();
        $CI->load->library('stripe_core');
        $CI->load->library('stripe_subscriptions');
        $CI->load->model('subscriptions_model');
        $CI->load->model('invoices_model');
        $CI->load->model('payments_model');

        $payload = $CI->input->raw_input_stream;
        $event = null;

        if (!isset($_SERVER['HTTP_STRIPE_SIGNATURE'])) {
            return;
        }

        try {
            $event = $CI->stripe_core->construct_event($payload, get_option('stripe_webhook_signing_secret'));
        } catch (\UnexpectedValueException $e) {
            http_response_code(400);
            exit();
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            http_response_code(400);
            exit();
        }

        try {
            $subscriptionMetaKey = 'pcrm-subscription-hash';

            // Detect prorated invoices (subscription change mid-cycle)
            if ($event->type === 'invoice.payment_succeeded') {
                $invoice = $event->data->object;

                $crmSubscriptionItem = null;
                $saasInvoice = false;

                // Fetch all invoice lines
                $invoice->lines->data = perfex_saas_stripe_fetch_all_invoice_lines($invoice->id);

                // Normal check first (metadata on line item)
                foreach ($invoice->lines->data as $item) {

                    if (isset($item->price->metadata->group)) {
                        $saasInvoice = $item->price->metadata->group === 'pcrm_saas';
                    }

                    if (isset($item->metadata[$subscriptionMetaKey])) {
                        $crmSubscriptionItem = $item;
                        break;
                    }
                }

                if (!$saasInvoice)
                    return;

                if (!isset($invoice->charge) && isset($invoice->status) && $invoice->status === 'paid') {
                    $invoice->charge = $invoice->number ?? $invoice->id;
                }

                // If not found, check subscription details
                if (is_null($crmSubscriptionItem) && isset($invoice->subscription)) {
                    $subscription = \Stripe\Subscription::retrieve($invoice->subscription);

                    if (isset($subscription->metadata[$subscriptionMetaKey])) {
                        $crmSubscriptionItem = (object) [
                            'metadata' => $subscription->metadata,
                            'period' => (object) [
                                'end' => $invoice->lines->data[0]->period->end ?? null
                            ]
                        ];
                    }
                }

                // If still found, process as subscription invoice
                if (!is_null($crmSubscriptionItem)) {
                    $dbSubscription = $CI->subscriptions_model->get_by_hash($crmSubscriptionItem->metadata[$subscriptionMetaKey]);

                    if ($dbSubscription) {
                        if (!$CI->stripe_gateway->is_test()) {
                            $CI->db->where('userid', $dbSubscription->clientid);
                            $CI->db->update('clients', ['stripe_id' => $invoice->customer]);
                        }

                        $CI->subscriptions_model->update($dbSubscription->id, [
                            'next_billing_cycle' => $crmSubscriptionItem->period->end
                        ]);

                        $new_invoice_data = create_subscription_invoice_data($dbSubscription, $invoice);

                        if (!defined('STRIPE_SUBSCRIPTION_INVOICE')) {
                            define('STRIPE_SUBSCRIPTION_INVOICE', true);
                        }

                        $id = $CI->invoices_model->add($new_invoice_data);

                        if ($id) {
                            $CI->db->where('id', $id)->update(db_prefix() . 'invoices', [
                                'addedfrom' => $dbSubscription->created_from
                            ]);

                            if (!$CI->payments_model->transaction_exists($invoice->charge)) {
                                $CI->payments_model->add([
                                    'paymentmode' => 'stripe',
                                    'amount' => $new_invoice_data['total'],
                                    'invoiceid' => $id,
                                    'transactionid' => $invoice->charge
                                ], $dbSubscription->id);
                            }

                            $update = [
                                'status' => 'active',
                                'stripe_subscription_id' => $invoice->subscription
                            ];

                            if (empty($dbSubscription->date_subscribed)) {
                                $update['date_subscribed'] = date('Y-m-d H:i:s');
                            }

                            if (empty($dbSubscription->date)) {
                                $update['date'] = date('Y-m-d');
                            }

                            $CI->subscriptions_model->update($dbSubscription->id, $update);
                        }
                        http_response_code(201);
                        exit();
                    }
                }
            }
        } catch (\Exception $e) {
            log_message('error', 'Stripe webhook error: ' . $e->getMessage());
        }
    }
}

if (!function_exists('perfex_saas_stripe_fetch_all_invoice_lines')) {
    /**
     * Fetch all invoice line items from Stripe for a given invoice ID
     *
     * @param string $invoiceId
     * @return array
     */
    function perfex_saas_stripe_fetch_all_invoice_lines($invoiceId)
    {
        $allLines = [];
        $startingAfter = null;

        do {
            $params = [
                'limit' => 100, // Max Stripe limit
            ];

            if ($startingAfter) {
                $params['starting_after'] = $startingAfter;
            }

            $lines = \Stripe\Invoice::allLines($invoiceId, $params);
            $allLines = array_merge($allLines, $lines->data);

            $startingAfter = $lines->has_more ? end($lines->data)->id : null;
        } while ($startingAfter);

        // Expand price details
        foreach ($allLines as $key => $lineItem) {

            if (isset($lineItem->price) && is_object($lineItem->price))
                continue;

            $priceId = $lineItem->pricing->price_details->price ?? null;

            // Fetch price and its associated product if not already in cache
            if ($priceId && !isset($objectCache[$priceId])) {
                try {
                    $objectCache[$priceId] = \Stripe\Price::retrieve($priceId, ['expand' => ['product']]);
                } catch (\Stripe\Exception\ApiErrorException $e) {
                    // Log and handle API errors, then set a flag or null to avoid re-retrieving
                    error_log("Error retrieving price $priceId: " . $e->getMessage());
                    $objectCache[$priceId] = null;
                }
            }

            // Attach the full price object from the cache to the line item
            if ($priceId && isset($objectCache[$priceId])) {
                $allLines[$key]->price = $objectCache[$priceId];
            }
        }

        return $allLines;
    }
}