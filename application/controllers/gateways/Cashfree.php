<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * @property-read Cashfree_gateway $cashfree_gateway
 */
class Cashfree extends App_Controller
{
    /**
     * Customer is sent back here by Cashfree after the checkout finished
     * The order is always re-verified against the Cashfree API, the query string is never trusted
     *
     * @param  int    $invoice_id
     * @param  string $invoice_hash
     * @param  string $reference Payment attempt reference
     *
     * @return mixed
     */
    public function callback($invoice_id, $invoice_hash, $reference)
    {
        check_invoice_restrictions($invoice_id, $invoice_hash);

        $this->load->model('invoices_model');
        $invoice = $this->invoices_model->get($invoice_id);

        load_client_language($invoice->clientid);

        try {
            $result = $this->recordPayment($invoice_id, $reference);
        } catch (Exception $e) {
            set_alert('danger', $e->getMessage());
            redirect(site_url('invoice/' . $invoice_id . '/' . $invoice_hash));
        }

        if ($result['status'] === 'recorded') {
            set_alert('success', _l('online_payment_recorded_success'));
        } elseif ($result['status'] === 'duplicate') {
            set_alert('success', _l('online_payment_recorded_success'));
        } elseif ($result['status'] === 'not_saved') {
            set_alert('danger', _l('online_payment_recorded_success_fail_database'));
        } elseif ($result['status'] === 'pending') {
            set_alert('warning', _l('payment_received_awaiting_confirmation'));
        } else {
            set_alert('danger', _l('invoice_payment_record_failed'));
        }

        redirect(site_url('invoice/' . $invoice_id . '/' . $invoice_hash));
    }

    /**
     * Cashfree webhook endpoint
     * Records the payment even when the customer never returns to the invoice
     *
     * @return mixed
     */
    public function webhook()
    {
        $rawBody   = file_get_contents('php://input');
        $signature = $this->input->get_request_header('x-webhook-signature', true);
        $timestamp = $this->input->get_request_header('x-webhook-timestamp', true);

        if (!$this->cashfree_gateway->verifyWebhookSignature($signature, $timestamp, $rawBody)) {
            log_activity('Cashfree webhook signature verification failed.');
            show_error('Invalid signature', 401);

            return;
        }

        $payload = json_decode($rawBody, true);
        $orderId = $payload['data']['order']['order_id'] ?? null;
        $parsed  = $this->cashfree_gateway->parseOrderId($orderId);

        if (!$parsed) {
            log_activity('Cashfree webhook received with an unrecognized order id: ' . $orderId);

            return;
        }

        try {
            $result = $this->recordPayment($parsed['invoice_id'], $parsed['reference']);
        } catch (Exception $e) {
            log_activity('Cashfree webhook failed for order ' . $orderId . '. ' . $e->getMessage());

            return;
        }

        log_activity('Cashfree webhook processed for order ' . $orderId . '. Result: ' . $result['status']);
    }

    /**
     * Verify the Cashfree order and record the payment when it is paid
     * Safe to call more than once for the same order
     *
     * @param  int    $invoiceId
     * @param  string $reference
     *
     * @return array
     */
    private function recordPayment($invoiceId, $reference)
    {
        $orderId = $this->cashfree_gateway->buildOrderId($invoiceId, $reference);
        $order   = $this->cashfree_gateway->getOrder($orderId);

        if (($order['order_status'] ?? '') !== 'PAID') {
            return ['status' => ($order['order_status'] ?? '') === 'ACTIVE' ? 'pending' : 'failed'];
        }

        $payment = $this->cashfree_gateway->findSuccessfulPayment(
            $this->cashfree_gateway->getOrderPayments($orderId)
        );

        if (!$payment) {
            return ['status' => 'pending'];
        }

        $transactionId = (string) $payment['cf_payment_id'];

        $this->load->model('payments_model');

        if ($this->payments_model->transaction_exists($transactionId, $invoiceId)) {
            return ['status' => 'duplicate'];
        }

        $success = $this->cashfree_gateway->addPayment([
            'amount'                    => $payment['payment_amount'],
            'invoiceid'                 => $invoiceId,
            'paymentmethod'             => $payment['payment_group'] ?? 'Cashfree',
            'transactionid'             => $transactionId,
            'payment_attempt_reference' => $reference,
        ]);

        return ['status' => $success ? 'recorded' : 'not_saved'];
    }
}
