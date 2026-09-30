<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Public DUNS landing page and checkout — no authentication.
 *
 *   /duns-support                  landing page + order form (the ad URL)
 *   /duns-support/order            POST: create the order, then → pay
 *   /duns-support/pay/{ref}        choose a gateway → hand off to the gateway
 *   /duns-support/status/{ref}     order tracking / thank-you page
 *
 * {ref} is a random 32-hex token stored on the order, so the links need no
 * signature. /duns-support is a core route; /duns_support/... (module route)
 * reaches the same pages.
 */
class Duns_public extends App_Controller
{
    public function __construct()
    {
        parent::__construct();
        duns_maybe_upgrade_schema();
        $this->load->model('duns_support/duns_support_model');
    }

    public function index($action = '', $ref = '')
    {
        if (get_option('duns_landing_enabled') != '1' && !is_staff_logged_in()) {
            return $this->error('Unavailable', 'This page is not available right now. Please check back soon.');
        }

        switch ($action) {
            case '':
                return $this->landing();
            case 'order':
                return $this->order();
            case 'pay':
                return $this->pay($ref);
            case 'status':
                return $this->status($ref);
        }

        show_404();
    }

    private function error($title, $message)
    {
        $this->output->set_status_header(404);
        $this->load->view('public/error', ['title' => $title, 'message' => $message, 'landing' => duns_landing()]);
    }

    private function landing(array $errors = [], array $old = [])
    {
        $plans = $this->duns_support_model->get_plans(true);

        // Remember where the visitor came from; the form carries it through the POST
        $track = [];
        foreach (['utm_source', 'utm_medium', 'utm_campaign', 'fbclid'] as $k) {
            $track[$k] = (string) ($old[$k] ?? $this->input->get($k, true) ?? '');
        }

        $featured = null;
        foreach ($plans as $pl) {
            if ($pl->is_featured) {
                $featured = $pl;
            }
        }
        $pick = (int) ($old['plan_id'] ?? $this->input->get('plan') ?? 0) ?: ($featured->id ?? ($plans[0]->id ?? 0));

        $this->load->view('public/landing', [
            'title'    => 'Get DUNS Number in 1 Hour — ' . duns_landing()['brand'],
            'landing'  => duns_landing(),
            'plans'    => $plans,
            'pick'     => $pick,
            'errors'   => $errors,
            'old'      => $old,
            'track'    => $track,
            'can_pay'  => count(duns_pay_gateways()) > 0,
            'min_price' => count($plans) ? min(array_map(function ($p) { return (float) $p->price; }, $plans)) : 0,
        ]);
    }

    private function order()
    {
        if ($this->input->method() !== 'post') {
            redirect(duns_public_url());
        }
        $post = $this->input->post(null, true);

        // Honeypot — bots fill every field; people never see this one
        if (!empty($post['website'])) {
            redirect(duns_public_url());
        }

        $file   = $_FILES['coi'] ?? null;
        $errors = $this->duns_support_model->validate_order($post, $file);
        if (count($errors)) {
            return $this->landing($errors, $post);
        }

        $order = $this->duns_support_model->create_order($post, $file, [
            'utm_source'   => $post['utm_source'] ?? '',
            'utm_medium'   => $post['utm_medium'] ?? '',
            'utm_campaign' => $post['utm_campaign'] ?? '',
            'fbclid'       => $post['fbclid'] ?? '',
            'ip'           => $this->input->ip_address(),
        ]);
        if (is_string($order)) {
            return $this->landing(['form' => $order], $post);
        }

        redirect(duns_public_url('pay/' . $order->ref));
    }

    private function pay($ref)
    {
        $order = $this->duns_support_model->get_by_ref($ref);
        if (!$order) {
            return $this->error('Order not found', 'We could not find this order. Please check the link in your email.');
        }
        if ($order->status !== 'pending_payment') {
            redirect(duns_public_url('status/' . $order->ref));
        }

        $gateways = duns_pay_gateways();
        $errors   = [];

        if ($this->input->method() === 'post' && count($gateways)) {
            $gateway = (string) $this->input->post('gateway', true);
            if (!isset($gateways[$gateway])) {
                $errors[] = 'Please choose how you would like to pay.';
            } else {
                $invoice = $this->duns_support_model->prepare_invoice($order, $gateway);
                if (is_string($invoice)) {
                    $errors[] = $invoice;
                } else {
                    return $this->hand_off($invoice, $gateway);
                }
            }
        }

        $this->load->view('public/pay', [
            'title'    => 'Secure payment — ' . $order->order_no,
            'landing'  => duns_landing(),
            'order'    => $order,
            'files'    => $this->duns_support_model->get_files($order->id),
            'gateways' => $gateways,
            'errors'   => $errors,
        ]);
    }

    /**
     * process_payment() ends the request itself (the gateway redirects or
     * prints its checkout), so reaching the line after it means it never started.
     */
    private function hand_off($invoice, $gateway)
    {
        $this->load->model('payments_model');
        $amount = get_invoice_total_left_to_pay($invoice->id, $invoice->total);

        $this->payments_model->process_payment([
            'paymentmode' => $gateway,
            'amount'      => $amount,
            'invoiceid'   => $invoice->id,
        ], $invoice->id);

        return $this->error('Payment unavailable', 'We could not open the payment page just now. Please try again in a minute, or contact us on WhatsApp.');
    }

    private function status($ref)
    {
        $order = $this->duns_support_model->get_by_ref($ref);
        if (!$order) {
            return $this->error('Order not found', 'We could not find this order. Please check the link in your email.');
        }

        // The ad Purchase event must fire exactly once per order, however often the page is opened
        $fire_purchase = in_array($order->status, ['paid', 'processing', 'completed'], true)
            && !$order->purchase_tracked
            && $this->duns_support_model->mark_purchase_tracked($order->id);

        $this->load->view('public/status', [
            'title'         => 'Order ' . $order->order_no . ' — ' . duns_landing()['brand'],
            'landing'       => duns_landing(),
            'order'         => $order,
            'fire_purchase' => $fire_purchase,
            'can_pay'       => count(duns_pay_gateways()) > 0,
        ]);
    }
}
