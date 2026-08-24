<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Cashfree Payments gateway
 *
 * Uses the Cashfree PG Orders API together with the v3 hosted checkout SDK.
 * The customer is handed off to Cashfree, then returned to the callback route
 * where the order is verified server side before the payment is recorded.
 *
 * @see https://docs.cashfree.com/reference/pg-new-apis-endpoint
 */
class Cashfree_gateway extends App_gateway
{
    public bool $processingFees = true;

    private $sandbox_endpoint = 'https://sandbox.cashfree.com/pg/';

    private $production_endpoint = 'https://api.cashfree.com/pg/';

    private $api_version = '2023-08-01';

    public function __construct()
    {
        /**
         * Call App_gateway __construct function
         */
        parent::__construct();

        /**
         * REQUIRED
         * Gateway unique id
         * The ID must be alpha/alphanumeric
         */
        $this->setId('cashfree');

        /**
         * REQUIRED
         * Gateway name
         */
        $this->setName('Cashfree');

        /**
         * Add gateway settings
         */
        $this->setSettings([
            [
                'name'      => 'app_id',
                'encrypted' => true,
                'label'     => 'payment_gateway_cashfree_app_id',
            ],
            [
                'name'      => 'secret_key',
                'encrypted' => true,
                'label'     => 'payment_gateway_cashfree_secret_key',
                'after'     => '<div class="alert alert-info mtop15">'
                                . '<b>' . 'Webhook URL' . '</b><br />'
                                . '<code>' . site_url('gateways/cashfree/webhook') . '</code>'
                                . '<p class="tw-mt-2 no-mbot">Add this URL in your Cashfree merchant dashboard under Developers &raquo; Webhooks and subscribe to the payment success/failed events. Payments are recorded even if the customer closes the browser before returning.</p>'
                                . '</div>',
            ],
            [
                'name'          => 'description_dashboard',
                'label'         => 'settings_paymentmethod_description',
                'type'          => 'textarea',
                'default_value' => 'Payment for Invoice {invoice_number}',
            ],
            [
                'name'          => 'currencies',
                'label'         => 'settings_paymentmethod_currencies',
                'default_value' => 'INR',
            ],
            [
                'name'          => 'test_mode_enabled',
                'type'          => 'yes_no',
                'default_value' => 1,
                'label'         => 'settings_paymentmethod_testing_mode',
            ],
        ]);
    }

    /**
     * REQUIRED FUNCTION
     * Creates the Cashfree order and hands the customer over to the hosted checkout
     *
     * @param  array $data
     * @return mixed
     */
    public function process_payment($data)
    {
        $invoice     = $data['invoice'];
        $invoiceUrl  = site_url('invoice/' . $invoice->id . '/' . $invoice->hash);
        $reference   = $data['payment_attempt']->reference;
        $contact     = $this->getInvoiceContact($invoice);

        $note = mb_substr(
            str_replace('{invoice_number}', format_invoice_number($invoice->id), $this->getSetting('description_dashboard')),
            0,
            200
        );

        $payload = [
            'order_id'         => $this->buildOrderId($invoice->id, $reference),
            'order_amount'     => round((float) $data['amount'], 2),
            'order_currency'   => $invoice->currency_name ?: 'INR',
            'customer_details' => [
                'customer_id'    => 'ccx_client_' . $invoice->clientid,
                'customer_name'  => mb_substr($contact['name'], 0, 100),
                'customer_email' => $contact['email'],
                'customer_phone' => $contact['phonenumber'],
            ],
            'order_meta' => [
                'return_url' => site_url('gateways/cashfree/callback/' . $invoice->id . '/' . $invoice->hash . '/' . $reference),
                'notify_url' => site_url('gateways/cashfree/webhook'),
            ],
        ];

        if ($note !== '') {
            $payload['order_note'] = $note;
        }

        try {
            $response = $this->request('orders', $payload, 'POST');
        } catch (Exception $e) {
            set_alert('warning', $e->getMessage());
            redirect($invoiceUrl);
            die;
        }

        if (empty($response['payment_session_id'])) {
            set_alert('warning', $response['message'] ?? 'Cashfree did not return a payment session, please try again.');
            redirect($invoiceUrl);
            die;
        }

        echo $this->checkoutHtml($response['payment_session_id'], $data);
        die;
    }

    /**
     * Retrieve an order from Cashfree
     *
     * @param  string $orderId
     * @return array
     */
    public function getOrder($orderId)
    {
        return $this->request('orders/' . rawurlencode($orderId));
    }

    /**
     * Retrieve all payment attempts made against a Cashfree order
     *
     * @param  string $orderId
     * @return array
     */
    public function getOrderPayments($orderId)
    {
        $payments = $this->request('orders/' . rawurlencode($orderId) . '/payments');

        return is_array($payments) ? $payments : [];
    }

    /**
     * Find the successful payment within an order payments response
     *
     * @param  array $payments
     * @return array|null
     */
    public function findSuccessfulPayment($payments)
    {
        foreach ($payments as $payment) {
            if (isset($payment['payment_status']) && $payment['payment_status'] === 'SUCCESS') {
                return $payment;
            }
        }

        return null;
    }

    /**
     * Build the Cashfree order id for the given invoice/payment attempt
     * Cashfree only allows alphanumeric characters, underscores and hyphens
     *
     * @param  int    $invoiceId
     * @param  string $reference Payment attempt reference
     * @return string
     */
    public function buildOrderId($invoiceId, $reference)
    {
        return 'ccx' . (int) $invoiceId . '_' . $reference;
    }

    /**
     * Extract the invoice id and the payment attempt reference from a Cashfree order id
     *
     * @param  string $orderId
     * @return array|null [invoice_id, reference]
     */
    public function parseOrderId($orderId)
    {
        if (!preg_match('/^ccx(\d+)_([a-zA-Z0-9]+)$/', (string) $orderId, $matches)) {
            return null;
        }

        return ['invoice_id' => (int) $matches[1], 'reference' => $matches[2]];
    }

    /**
     * Verify the signature Cashfree sends along with each webhook call
     *
     * @param  string $signature Value of the x-webhook-signature header
     * @param  string $timestamp Value of the x-webhook-timestamp header
     * @param  string $rawBody   Raw, unparsed request body
     * @return bool
     */
    public function verifyWebhookSignature($signature, $timestamp, $rawBody)
    {
        if (empty($signature) || empty($timestamp)) {
            return false;
        }

        $expected = base64_encode(
            hash_hmac('sha256', $timestamp . $rawBody, $this->decryptSetting('secret_key'), true)
        );

        return hash_equals($expected, (string) $signature);
    }

    /**
     * Perform a request against the Cashfree API
     *
     * @param  string $uri
     * @param  array|null $payload
     * @param  string $method
     * @return array
     * @throws Exception
     */
    public function request($uri, $payload = null, $method = 'GET')
    {
        $ch = curl_init($this->getEndpoint() . $uri);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 45,
            CURLOPT_HTTPHEADER     => [
                'x-client-id: ' . $this->decryptSetting('app_id'),
                'x-client-secret: ' . $this->decryptSetting('secret_key'),
                'x-api-version: ' . $this->api_version,
                'Accept: application/json',
                'Content-Type: application/json',
            ],
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }

        $body      = curl_exec($ch);
        $curlError = curl_error($ch);
        $status    = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false) {
            throw new Exception('Cashfree connection error: ' . $curlError);
        }

        $decoded = json_decode($body, true);

        if (!is_array($decoded)) {
            throw new Exception('Cashfree returned an unreadable response (HTTP ' . $status . ')');
        }

        if ($status >= 400) {
            throw new Exception('Cashfree: ' . ($decoded['message'] ?? 'request failed (HTTP ' . $status . ')'));
        }

        return $decoded;
    }

    /**
     * Whether the gateway operates against the sandbox
     *
     * @return bool
     */
    public function isTestMode()
    {
        return $this->getSetting('test_mode_enabled') == '1';
    }

    private function getEndpoint()
    {
        return $this->isTestMode() ? $this->sandbox_endpoint : $this->production_endpoint;
    }

    /**
     * Resolve the contact details Cashfree requires for the order
     * Cashfree rejects orders without a customer email and phone number
     *
     * @param  object $invoice
     * @return array
     */
    private function getInvoiceContact($invoice)
    {
        $contact = null;

        if (is_client_logged_in()) {
            $contact = $this->ci->clients_model->get_contact(get_contact_user_id());
        } else {
            $contacts = $this->ci->clients_model->get_contacts($invoice->clientid);
            if (count($contacts) > 0) {
                $contact = (object) $contacts[0];
            }
        }

        $name        = $contact ? trim($contact->firstname . ' ' . $contact->lastname) : '';
        $email       = $contact && $contact->email ? $contact->email : '';
        $phonenumber = $contact && $contact->phonenumber ? preg_replace('/[^0-9]/', '', $contact->phonenumber) : '';

        // Cashfree expects the national number without the country code prefix
        if (strlen($phonenumber) > 10) {
            $phonenumber = substr($phonenumber, -10);
        }

        return [
            'name'        => $name !== '' ? $name : 'Customer',
            'email'       => $email !== '' ? $email : get_option('smtp_email'),
            'phonenumber' => strlen($phonenumber) === 10 ? $phonenumber : '9999999999',
        ];
    }

    /**
     * Interstitial page that loads the Cashfree checkout SDK and hands the customer over
     *
     * @param  string $paymentSessionId
     * @param  array  $data
     * @return string
     */
    private function checkoutHtml($paymentSessionId, $data)
    {
        $invoice = $data['invoice'];

        ob_start(); ?>
<?php echo payment_gateway_head(_l('payment_for_invoice')); ?>
<body class="gateway-cashfree">
    <div class="container">
        <div class="col-md-8 col-md-offset-2 mtop30">
            <div class="mbot30 text-center">
                <?php echo payment_gateway_logo(); ?>
            </div>
            <div class="row">
                <div class="panel_s">
                    <div class="panel-heading">
                        <h4 class="panel-title">
                            <?php echo _l('payment_for_invoice'); ?> -
                            <?php echo e(_l('payment_total', app_format_money($data['amount'], $invoice->currency_name))); ?>
                        </h4>
                        <a href="<?php echo site_url('invoice/' . $invoice->id . '/' . $invoice->hash); ?>">
                            <?php echo e(format_invoice_number($invoice->id)); ?>
                        </a>
                    </div>
                    <div class="panel-body text-center">
                        <?php if ($this->processingFees) { ?>
                            <h4><?php echo _l('payment_attempt_amount') . ': ' . e(app_format_money($data['payment_attempt']->amount, $invoice->currency_name)); ?></h4>
                            <h4><?php echo _l('payment_attempt_fee') . ': ' . e(app_format_money($data['payment_attempt']->fee, $invoice->currency_name)); ?></h4>
                            <hr />
                        <?php } ?>
                        <p id="cashfree-status"><?php echo _l('wait_text'); ?></p>
                        <p>
                            <a href="<?php echo site_url('invoice/' . $invoice->id . '/' . $invoice->hash); ?>">
                                <?php echo _l('go_back'); ?>
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php echo payment_gateway_scripts(); ?>
    <script src="https://sdk.cashfree.com/js/v3/cashfree.js"></script>
    <script>
    (function() {
        try {
            var cashfree = Cashfree({
                mode: <?php echo json_encode($this->isTestMode() ? 'sandbox' : 'production'); ?>
            });

            cashfree.checkout({
                paymentSessionId: <?php echo json_encode($paymentSessionId); ?>,
                redirectTarget: '_self'
            });
        } catch (e) {
            document.getElementById('cashfree-status').innerHTML =
                'Unable to open the Cashfree checkout. Please go back and try again.';
        }
    })();
    </script>
<?php echo payment_gateway_footer();

        return ob_get_clean();
    }
}
