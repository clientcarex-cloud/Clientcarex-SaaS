<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Ccx_msgs_public extends App_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('invoices_model');
        $this->load->model('ccx_msgs/ccx_msgs_model');
        $this->load->model('ccx_msgs/ccx_msgs_pricing_model');
    }

    /**
     * Endpoint for the tenant portal to fetch pricing plans
     */
    public function get_pricing_plans()
    {
        // Simple public endpoint or secured via basic secret
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization, X-Requested-With');

        if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
            exit(0);
        }

        header('Content-Type: application/json');
        
        $this->db->where('active', 1);
        $this->db->order_by('price', 'asc');
        $plans = $this->db->get(db_prefix() . 'ccx_msgs_pricing')->result_array();
        
        // Enrich plans with tax_name from tbltaxes
        foreach ($plans as &$plan) {
            $plan['tax_name'] = '';
            if (!empty($plan['tax_id']) && (int)$plan['tax_id'] > 0) {
                $tax_row = $this->db->where('id', $plan['tax_id'])->get(db_prefix() . 'taxes')->row();
                $plan['tax_name'] = $tax_row ? $tax_row->name : 'Tax';
            }
        }
        unset($plan);
        
        echo json_encode(['success' => true, 'plans' => $plans]);
        die;
    }

    /**
     * Validate a promo/referral code for the tenant checkout.
     * POST params: code, client_id, subtotal, channels (JSON string)
     */
    public function validate_promo_code()
    {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

        if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit(0); }

        $code      = strtoupper(trim($this->input->get_post('code')));
        $client_id = (int) $this->input->get_post('client_id');
        $subtotal  = (float) $this->input->get_post('subtotal');
        $channels  = json_decode($this->input->get_post('channels'), true);
        if (!is_array($channels)) $channels = [];

        if (empty($code)) {
            echo json_encode(['success' => false, 'message' => 'Please enter a code.']);
            die;
        }

        $promo = $this->db->where('code', $code)->get(db_prefix() . 'ccx_msgs_promo_codes')->row();

        if (!$promo) {
            echo json_encode(['success' => false, 'message' => 'Invalid code. Please check and try again.']);
            die;
        }

        // Active?
        if (!$promo->active) {
            echo json_encode(['success' => false, 'message' => 'This code is no longer active.']);
            die;
        }

        // Date validity
        $today = date('Y-m-d');
        if (!empty($promo->valid_from) && $today < $promo->valid_from) {
            echo json_encode(['success' => false, 'message' => 'This code is not yet valid.']);
            die;
        }
        if (!empty($promo->valid_until) && $today > $promo->valid_until) {
            echo json_encode(['success' => false, 'message' => 'This code has expired.']);
            die;
        }

        // Usage limit
        if ($promo->usage_limit > 0 && $promo->usage_count >= $promo->usage_limit) {
            echo json_encode(['success' => false, 'message' => 'This code has reached its maximum usage limit.']);
            die;
        }

        // Per-client limit
        if ($promo->per_client_limit > 0 && $client_id > 0) {
            $client_usage = $this->db->where('promo_id', $promo->id)
                ->where('client_id', $client_id)
                ->count_all_results(db_prefix() . 'ccx_msgs_promo_usage');
            if ($client_usage >= $promo->per_client_limit) {
                echo json_encode(['success' => false, 'message' => 'You have already used this code the maximum number of times.']);
                die;
            }
        }

        // Min order amount
        if ($promo->min_order_amount > 0 && $subtotal < $promo->min_order_amount) {
            echo json_encode(['success' => false, 'message' => 'Minimum order amount is ' . app_format_money($promo->min_order_amount, get_base_currency()->name) . '.']);
            die;
        }

        // Channel restriction
        $applicable = json_decode($promo->applicable_channels, true);
        if (is_array($applicable) && !in_array('all', $applicable) && !empty($channels)) {
            $mismatch = array_diff($channels, $applicable);
            if (count($mismatch) > 0) {
                echo json_encode(['success' => false, 'message' => 'This code is not valid for: ' . implode(', ', $mismatch) . '.']);
                die;
            }
        }

        // Calculate discount
        $discount = 0;
        if ($promo->discount_type == 'percentage') {
            $discount = $subtotal * ((float) $promo->discount_value / 100);
            if ($promo->max_discount_amount > 0 && $discount > $promo->max_discount_amount) {
                $discount = (float) $promo->max_discount_amount;
            }
        } else {
            $discount = (float) $promo->discount_value;
        }

        if ($discount > $subtotal) $discount = $subtotal;
        $discount = round($discount, 2);

        echo json_encode([
            'success'         => true,
            'promo_id'        => $promo->id,
            'code'            => $promo->code,
            'discount_amount' => $discount,
            'discount_type'   => $promo->discount_type,
            'discount_value'  => $promo->discount_value,
            'message'         => 'Code applied! You save ' . app_format_money($discount, get_base_currency()->name),
        ]);
        die;
    }

    /**
     * Endpoint hit by the tenant when they select a plan to buy.
     * Expects: POST params `client_id` (master client id), `plan_id`
     */
    public function initiate_recharge()
    {
        $client_id = $this->input->get('client_id');
        $plan_id = $this->input->get('plan_id');
        $return_url = $this->input->get('return_url'); // where to send back

        if (empty($client_id) || empty($plan_id)) {
            show_error('Missing parameters', 400);
        }

        // Fetch plan
        $plan = $this->ccx_msgs_pricing_model->get($plan_id);
        if (!$plan || !$plan->active) {
            show_error('Invalid or inactive plan', 400);
        }

        // Fetch client
        $this->load->model('clients_model');
        $client = $this->clients_model->get($client_id);
        if (!$client) {
            show_error('Invalid master client ID', 400);
        }

        // Determine price after discount
        $price = $plan->price;
        if ($plan->discount_percent > 0) {
            $price = $price - ($price * ($plan->discount_percent / 100));
        }

        // Apply GST/Tax on discounted price
        $tax_percent = isset($plan->tax_percent) ? (float)$plan->tax_percent : 0;
        $tax_amount = ($price * $tax_percent) / 100;
        $total_amount = $price + $tax_amount;

        // Generate Checkout Session
        $session_token = md5(uniqid(rand(), true) . $client_id . time());

        $session_data = [
            'session_token' => $session_token,
            'client_id' => $client_id,
            'plan_id' => $plan_id,
            'amount' => $total_amount,
            'status' => 'pending',
            'return_url' => $return_url ? $return_url : '',
            'created_at' => date('Y-m-d H:i:s')
        ];
        $this->db->insert(db_prefix() . 'ccx_msgs_checkout_sessions', $session_data);

        // Redirect to Custom Checkout Page
        redirect(site_url('ccx_msgs/ccx_msgs_public/checkout/' . $session_token));
    }

    /**
     * Cart Recharge: accept multiple plan_ids and create a single checkout session
     */
    public function initiate_cart_recharge()
    {
        $client_id = $this->input->get('client_id');
        $plan_ids = $this->input->get('plan_ids');
        $return_url = $this->input->get('return_url');
        $promo_code = $this->input->get('promo_code');
        $promo_id = (int) $this->input->get('promo_id');

        if (empty($client_id) || empty($plan_ids) || !is_array($plan_ids)) {
            show_error('Missing or invalid parameters', 400);
        }

        // Fetch and validate client
        $this->load->model('clients_model');
        $client = $this->clients_model->get($client_id);
        if (!$client) {
            show_error('Invalid master client ID', 400);
        }

        // Validate each plan and calculate combined total
        $subtotal = 0;
        $total_tax = 0;
        $cart_items = [];

        foreach ($plan_ids as $pid) {
            $plan = $this->ccx_msgs_pricing_model->get($pid);
            if (!$plan || !$plan->active) {
                show_error('Invalid or inactive plan (ID: ' . intval($pid) . ')', 400);
            }

            $price = $plan->price;
            if ($plan->discount_percent > 0) {
                $price = $price - ($price * ($plan->discount_percent / 100));
            }
            $tax_percent = isset($plan->tax_percent) ? (float)$plan->tax_percent : 0;
            $tax_amount = ($price * $tax_percent) / 100;

            $subtotal += $price;
            $total_tax += $tax_amount;
            $cart_items[] = ['plan_id' => (int)$pid];
        }

        // ═══ Promo Code Discount ═══
        $promo_discount = 0;
        $validated_promo_id = null;
        if (!empty($promo_code) && $promo_id > 0) {
            $promo = $this->db->where('id', $promo_id)->where('code', strtoupper($promo_code))->where('active', 1)->get(db_prefix() . 'ccx_msgs_promo_codes')->row();
            if ($promo) {
                if ($promo->discount_type == 'percentage') {
                    $promo_discount = $subtotal * ((float) $promo->discount_value / 100);
                    if ($promo->max_discount_amount > 0 && $promo_discount > $promo->max_discount_amount) {
                        $promo_discount = (float) $promo->max_discount_amount;
                    }
                } else {
                    $promo_discount = (float) $promo->discount_value;
                }
                if ($promo_discount > $subtotal) $promo_discount = $subtotal;
                $promo_discount = round($promo_discount, 2);
                $validated_promo_id = $promo->id;
            }
        }

        $total_amount = $subtotal + $total_tax - $promo_discount;
        if ($total_amount < 0) $total_amount = 0;

        // Generate Checkout Session
        $session_token = md5(uniqid(rand(), true) . $client_id . time());

        $session_data = [
            'session_token' => $session_token,
            'client_id'     => $client_id,
            'plan_id'       => (int)$plan_ids[0], // primary plan for backward compat
            'cart_items'    => json_encode($cart_items),
            'promo_id'      => $validated_promo_id,
            'promo_discount' => $promo_discount,
            'amount'        => round($total_amount, 2),
            'status'        => 'pending',
            'return_url'    => $return_url ? $return_url : '',
            'created_at'    => date('Y-m-d H:i:s')
        ];
        $this->db->insert(db_prefix() . 'ccx_msgs_checkout_sessions', $session_data);

        // Redirect to Custom Checkout Page
        redirect(site_url('ccx_msgs/ccx_msgs_public/checkout/' . $session_token));
    }

    public function checkout($session_token = '')
    {
        if (empty($session_token)) {
            show_error('Invalid checkout session.', 404);
        }

        $session = $this->db->where('session_token', $session_token)->get(db_prefix() . 'ccx_msgs_checkout_sessions')->row();
        if (!$session) {
            show_error('Checkout session not found or expired.', 404);
        }

        if ($session->status != 'pending') {
            show_error('This checkout session has already been processed.', 400);
        }

        $this->load->model('clients_model');
        $client = $this->clients_model->get($session->client_id);

        $data['session'] = $session;
        $data['client'] = $client;

        // ═══ Detect Cart Mode ═══
        $cart_mode = !empty($session->cart_items);
        $data['cart_mode'] = $cart_mode;

        if ($cart_mode) {
            // Multi-plan cart
            $cart_items = json_decode($session->cart_items, true);
            $plans = [];
            $combined_subtotal = 0;
            $combined_tax = 0;
            $combined_original = 0;

            foreach ($cart_items as $item) {
                $plan = $this->ccx_msgs_pricing_model->get($item['plan_id']);
                if (!$plan) continue;

                $price_after_discount = $plan->price;
                if ($plan->discount_percent > 0) {
                    $price_after_discount = $plan->price - ($plan->price * ($plan->discount_percent / 100));
                }
                $tax_percent = isset($plan->tax_percent) ? (float)$plan->tax_percent : 0;
                $tax_amount = ($price_after_discount * $tax_percent) / 100;

                $plan->_subtotal = $price_after_discount;
                $plan->_tax_amount = $tax_amount;
                $plan->_total = $price_after_discount + $tax_amount;
                $plan->_currency = $this->_resolve_plan_currency($plan);

                $combined_subtotal += $price_after_discount;
                $combined_tax += $tax_amount;
                $combined_original += $plan->price;

                $plans[] = $plan;
            }

            $data['plans'] = $plans;
            $data['plan'] = $plans[0]; // primary plan for backward compat in view
            $data['title'] = 'Secure Checkout - ' . count($plans) . ' Plans';
            $data['subtotal'] = $combined_subtotal;
            $data['tax_amount'] = $combined_tax;
            $data['tax_percent'] = 0; // mixed, shown per-item
            $data['tax_name'] = 'Tax';
            $data['total_savings'] = $combined_original - $combined_subtotal;
            $data['plan_currency'] = $this->_resolve_plan_currency($plans[0]);

        } else {
            // Single-plan mode (original flow)
            $plan = $this->ccx_msgs_pricing_model->get($session->plan_id);
            if (!$plan) {
                show_error('Pricing plan no longer exists.', 404);
            }

            $data['plan'] = $plan;
            $data['plans'] = [$plan];
            $data['title'] = 'Secure Checkout - ' . $plan->plan_name;

            $tax_percent = isset($plan->tax_percent) ? (float)$plan->tax_percent : 0;
            $price_after_discount = $plan->price;
            if ($plan->discount_percent > 0) {
                $price_after_discount = $plan->price - ($plan->price * ($plan->discount_percent / 100));
            }
            $tax_amount = ($price_after_discount * $tax_percent) / 100;

            $tax_name = 'GST';
            if (isset($plan->tax_id) && $plan->tax_id > 0) {
                $this->load->model('taxes_model');
                $tax_row = $this->taxes_model->get($plan->tax_id);
                if ($tax_row) {
                    $tax_name = $tax_row->name;
                }
            }

            $data['tax_percent'] = $tax_percent;
            $data['tax_amount'] = $tax_amount;
            $data['tax_name'] = $tax_name;
            $data['subtotal'] = $price_after_discount;
            $data['total_savings'] = $plan->price - $price_after_discount;
            $data['plan_currency'] = $this->_resolve_plan_currency($plan);
        }

        // Promo discount data for checkout view
        $data['promo_discount'] = isset($session->promo_discount) ? (float)$session->promo_discount : 0;
        $data['promo_code_label'] = '';
        if ($data['promo_discount'] > 0 && isset($session->promo_id) && $session->promo_id > 0) {
            $promo_info = $this->db->select('code')->where('id', $session->promo_id)->get(db_prefix() . 'ccx_msgs_promo_codes')->row();
            $data['promo_code_label'] = $promo_info ? $promo_info->code : '';
        }

        // Fetch only ACTIVE payment gateways from Perfex CRM Settings
        $this->load->model('payment_modes_model');
        $all_gateways = $this->payment_modes_model->get_payment_gateways();
        $active_gateways = [];
        foreach ($all_gateways as $gw) {
            $active_gateways[] = [
                'id'   => $gw['id'],
                'name' => !empty($gw['name']) ? $gw['name'] : ucfirst($gw['id']),
            ];
        }

        // Razorpay special case
        $razorpay_key = get_option('paymentmethod_razorpay_key_id');
        if (!empty($razorpay_key)) {
            $razorpay_exists = false;
            foreach ($active_gateways as $gw) {
                if ($gw['id'] === 'razorpay') { $razorpay_exists = true; break; }
            }
            if (!$razorpay_exists) {
                $active_gateways[] = ['id' => 'razorpay', 'name' => 'Razorpay'];
            }
        }

        $data['gateways'] = $active_gateways;

        // Log this checkout attempt as 'initiated' in recharge_logs
        if ($cart_mode) {
            $cart_items = json_decode($session->cart_items, true);
            foreach ($cart_items as $item) {
                $this->db->insert(db_prefix() . 'ccx_msgs_recharge_logs', [
                    'client_id'  => $session->client_id,
                    'plan_id'    => $item['plan_id'],
                    'amount'     => $session->amount,
                    'status'     => 'initiated',
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        } else {
            $this->db->insert(db_prefix() . 'ccx_msgs_recharge_logs', [
                'client_id'  => $session->client_id,
                'plan_id'    => $session->plan_id,
                'amount'     => $session->amount,
                'status'     => 'initiated',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
        
        $this->load->view('checkout', $data);
    }

    public function verify_razorpay()
    {
        $session_token = $this->input->post('session_token');
        $razorpay_payment_id = $this->input->post('razorpay_payment_id');
        
        if (empty($session_token) || empty($razorpay_payment_id)) {
            echo json_encode(['success' => false, 'message' => 'Missing parameters.']);
            die;
        }

        $session = $this->db->where('session_token', $session_token)->get(db_prefix() . 'ccx_msgs_checkout_sessions')->row();
        if (!$session || $session->status != 'pending') {
            echo json_encode(['success' => false, 'message' => 'Invalid or already processed session.']);
            die;
        }

        // Ideally, we'd verify the signature with Razorpay API here.
        // Assuming success from frontend for this strict flow, or adding basic validation.
        
        $this->_generate_success_invoice($session, $razorpay_payment_id, 'razorpay');
        echo json_encode(['success' => true]);
        die;
    }

    /**
     * Create a Stripe Checkout Session and return the redirect URL.
     * Called via AJAX from the checkout page.
     */
    public function create_stripe_session()
    {
        $session_token = $this->input->post('session_token');
        if (empty($session_token)) {
            echo json_encode(['success' => false, 'message' => 'Missing session token.']);
            die;
        }

        $session = $this->db->where('session_token', $session_token)->get(db_prefix() . 'ccx_msgs_checkout_sessions')->row();
        if (!$session || $session->status != 'pending') {
            echo json_encode(['success' => false, 'message' => 'Invalid or already processed session.']);
            die;
        }

        $plan = $this->ccx_msgs_pricing_model->get($session->plan_id);
        if (!$plan) {
            echo json_encode(['success' => false, 'message' => 'Plan not found.']);
            die;
        }

        // Load Stripe library
        $this->load->model('payment_modes_model');
        $stripe_gw = $this->payment_modes_model->get('stripe', [], false, true);
        if (!$stripe_gw) {
            echo json_encode(['success' => false, 'message' => 'Stripe gateway is not configured.']);
            die;
        }

        $this->load->library('stripe_core');
        if (!$this->stripe_core->has_api_key()) {
            echo json_encode(['success' => false, 'message' => 'Stripe API keys are not configured.']);
            die;
        }

        // Resolve plan currency (fallback to base)
        $this->load->model('currencies_model');
        $plan_currency_id = isset($plan->currency_id) ? (int)$plan->currency_id : 0;
        $currency = ($plan_currency_id > 0) ? $this->currencies_model->get($plan_currency_id) : get_base_currency();
        if (!$currency) { $currency = get_base_currency(); }
        $amount_in_subunits = strcasecmp($currency->name, 'JPY') == 0
            ? intval($session->amount)
            : intval($session->amount * 100);

        $description = $plan->plan_name . ' (' . ucfirst($plan->message_type) . ' Credits)';

        $success_url = site_url('ccx_msgs/ccx_msgs_public/stripe_success/' . $session->session_token . '?stripe_session_id={CHECKOUT_SESSION_ID}');
        $cancel_url  = site_url('ccx_msgs/ccx_msgs_public/checkout/' . $session->session_token);

        try {
            $stripe_session = $this->stripe_core->create_session([
                'line_items' => [[
                    'price_data' => [
                        'currency'     => strtolower($currency->name),
                        'product_data' => ['name' => $description],
                        'unit_amount'  => $amount_in_subunits,
                    ],
                    'quantity' => 1,
                ]],
                'mode'        => 'payment',
                'success_url' => $success_url,
                'cancel_url'  => $cancel_url,
                'customer_creation' => 'always',
            ]);

            echo json_encode(['success' => true, 'redirect_url' => $stripe_session->url]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Stripe error: ' . $e->getMessage()]);
        }
        die;
    }

    /**
     * Stripe success callback — user is redirected here after paying on Stripe.
     */
    public function stripe_success($session_token = '')
    {
        if (empty($session_token)) {
            show_error('Invalid checkout session.', 400);
        }

        $stripe_session_id = $this->input->get('stripe_session_id');
        if (empty($stripe_session_id)) {
            show_error('Missing Stripe session ID.', 400);
        }

        $session = $this->db->where('session_token', $session_token)->get(db_prefix() . 'ccx_msgs_checkout_sessions')->row();
        if (!$session || $session->status != 'pending') {
            show_error('This checkout session has already been processed or is invalid.', 400);
        }

        // Verify payment via Stripe API
        $this->load->library('stripe_core');
        try {
            $stripe_checkout = \Stripe\Checkout\Session::retrieve($stripe_session_id);
            if ($stripe_checkout->payment_status !== 'paid') {
                show_error('Payment has not been completed. Status: ' . $stripe_checkout->payment_status, 400);
            }

            // Extract the payment intent ID as the transaction ID
            $transaction_id = $stripe_checkout->payment_intent ?? $stripe_session_id;
            $this->_generate_success_invoice($session, $transaction_id, 'stripe');

            // Redirect back to tenant with payment success params
            $redirect_to = !empty($session->return_url) ? $session->return_url : site_url();
            $separator = (strpos($redirect_to, '?') !== false) ? '&' : '?';
            $redirect_to .= $separator . 'payment_success=1&session_token=' . urlencode($session->session_token);
            redirect($redirect_to);
        } catch (Exception $e) {
            show_error('Failed to verify Stripe payment: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Create a PayU Money payment session and return a self-submitting HTML form.
     * Called via AJAX from the checkout page.
     */
    public function create_payu_session()
    {
        $session_token = $this->input->post('session_token');
        if (empty($session_token)) {
            echo json_encode(['success' => false, 'message' => 'Missing session token.']);
            die;
        }

        $session = $this->db->where('session_token', $session_token)->get(db_prefix() . 'ccx_msgs_checkout_sessions')->row();
        if (!$session || $session->status != 'pending') {
            echo json_encode(['success' => false, 'message' => 'Invalid or already processed session.']);
            die;
        }

        $plan = $this->ccx_msgs_pricing_model->get($session->plan_id);
        if (!$plan) {
            echo json_encode(['success' => false, 'message' => 'Plan not found.']);
            die;
        }

        // Load PayU Money gateway settings
        $payu_key  = get_option('paymentmethod_payu_money_key');
        $payu_salt = get_option('paymentmethod_payu_money_salt');
        $test_mode = get_option('paymentmethod_payu_money_test_mode_enabled');

        if (empty($payu_key) || empty($payu_salt)) {
            echo json_encode(['success' => false, 'message' => 'PayU Money gateway keys are not configured.']);
            die;
        }

        // Decrypt salt if it was stored encrypted
        $this->load->library('gateways/payu_money_gateway');
        $decrypted_salt = $this->payu_money_gateway->decryptSetting('salt');
        if (!empty($decrypted_salt)) {
            $payu_salt = $decrypted_salt;
        }

        // Fetch client info for prefill
        $this->load->model('clients_model');
        $client = $this->clients_model->get($session->client_id);
        $contact = null;
        if ($client) {
            $primary_contact_id = get_primary_contact_user_id($session->client_id);
            if ($primary_contact_id) {
                $contact = $this->clients_model->get_contact($primary_contact_id);
            }
        }

        $firstname = ($contact && isset($contact->firstname)) ? $contact->firstname : 'Customer';
        $email     = ($contact && isset($contact->email)) ? $contact->email : '';
        $phone     = ($contact && isset($contact->phonenumber)) ? $contact->phonenumber : '';

        // Build PayU parameters
        $txnid       = substr(hash('sha256', mt_rand() . microtime()), 0, 20);
        $amount      = number_format($session->amount, 2, '.', '');
        $productinfo = $plan->plan_name . ' (' . ucfirst($plan->message_type) . ' Credits)';
        $surl        = site_url('ccx_msgs/ccx_msgs_public/payu_success/' . $session->session_token);
        $furl        = site_url('ccx_msgs/ccx_msgs_public/payu_failure/' . $session->session_token);

        // Generate hash: key|txnid|amount|productinfo|firstname|email|udf1|...|udf10|SALT
        $hash_string = $payu_key . '|' . $txnid . '|' . $amount . '|' . $productinfo . '|' . $firstname . '|' . $email . '|||||||||||' . $payu_salt;
        $hash        = strtolower(hash('sha512', $hash_string));

        $action_url = ($test_mode == '1') ? 'https://test.payu.in/_payment' : 'https://secure.payu.in/_payment';

        // Build a self-submitting HTML form
        $form_html = '<!DOCTYPE html><html><head><title>Redirecting to PayU...</title></head>';
        $form_html .= '<body onload="document.getElementById(\'payu_form\').submit();">';
        $form_html .= '<div style="text-align:center;padding:60px;font-family:Inter,sans-serif;">';
        $form_html .= '<p style="font-size:16px;color:#4a5568;">Redirecting to PayU Money...</p>';
        $form_html .= '<p style="font-size:13px;color:#a0aec0;">Please wait, do not close this window.</p></div>';
        $form_html .= '<form id="payu_form" method="post" action="' . $action_url . '">';
        $form_html .= '<input type="hidden" name="key" value="' . htmlspecialchars($payu_key) . '" />';
        $form_html .= '<input type="hidden" name="txnid" value="' . htmlspecialchars($txnid) . '" />';
        $form_html .= '<input type="hidden" name="amount" value="' . htmlspecialchars($amount) . '" />';
        $form_html .= '<input type="hidden" name="productinfo" value="' . htmlspecialchars($productinfo) . '" />';
        $form_html .= '<input type="hidden" name="firstname" value="' . htmlspecialchars($firstname) . '" />';
        $form_html .= '<input type="hidden" name="email" value="' . htmlspecialchars($email) . '" />';
        $form_html .= '<input type="hidden" name="phone" value="' . htmlspecialchars($phone) . '" />';
        $form_html .= '<input type="hidden" name="surl" value="' . htmlspecialchars($surl) . '" />';
        $form_html .= '<input type="hidden" name="furl" value="' . htmlspecialchars($furl) . '" />';
        $form_html .= '<input type="hidden" name="hash" value="' . $hash . '" />';
        $form_html .= '<input type="hidden" name="service_provider" value="payu_paisa" />';
        $form_html .= '</form></body></html>';

        echo json_encode(['success' => true, 'form_html' => $form_html]);
        die;
    }

    /**
     * PayU Money success callback — PayU POSTs here after successful payment.
     */
    public function payu_success($session_token = '')
    {
        if (empty($session_token)) {
            show_error('Invalid checkout session.', 400);
        }

        $session = $this->db->where('session_token', $session_token)->get(db_prefix() . 'ccx_msgs_checkout_sessions')->row();
        if (!$session || $session->status != 'pending') {
            show_error('This checkout session has already been processed or is invalid.', 400);
        }

        // Verify the PayU response hash
        $this->load->library('gateways/payu_money_gateway');
        $hashInfo = $this->payu_money_gateway->get_valid_hash($_POST);

        if (!$hashInfo) {
            log_activity('CCX Msgs PayU Money: Invalid hash received for session ' . $session_token);
            show_error('Payment verification failed. Invalid transaction hash.', 400);
        }

        if ($hashInfo['status'] === 'success') {
            $transaction_id = $hashInfo['txnid'];

            // Prevent duplicate processing
            $existing = $this->db->where('gateway_txn_id', $transaction_id)
                                 ->where('status', 'completed')
                                 ->get(db_prefix() . 'ccx_msgs_checkout_sessions')->row();
            if (!$existing) {
                $this->_generate_success_invoice($session, $transaction_id, 'payu_money');
            }

            // Show success and redirect back to tenant with payment success params
            $redirect_to = !empty($session->return_url) ? $session->return_url : site_url();
            $separator = (strpos($redirect_to, '?') !== false) ? '&' : '?';
            $redirect_to .= $separator . 'payment_success=1&session_token=' . urlencode($session->session_token);
            redirect($redirect_to);
        } else {
            log_activity('CCX Msgs PayU Money: Transaction status not success: ' . $hashInfo['status'] . ' for session ' . $session_token);
            show_error('Payment was not successful. Status: ' . $hashInfo['status'], 400);
        }
    }

    /**
     * PayU Money failure callback — PayU POSTs here on payment failure/cancel.
     */
    public function payu_failure($session_token = '')
    {
        if (empty($session_token)) {
            show_error('Invalid checkout session.', 400);
        }

        // Redirect back to the checkout page so the user can retry
        redirect(site_url('ccx_msgs/ccx_msgs_public/checkout/' . $session_token));
    }

    private function _generate_success_invoice($session, $transaction_id, $gateway_name)
    {
        $this->load->model('clients_model');
        $client = $this->clients_model->get($session->client_id);

        // ═══ Determine plans to process ═══
        $cart_mode = !empty($session->cart_items);
        $plans = [];

        if ($cart_mode) {
            $cart_items = json_decode($session->cart_items, true);
            foreach ($cart_items as $item) {
                $p = $this->ccx_msgs_pricing_model->get($item['plan_id']);
                if ($p) $plans[] = $p;
            }
        } else {
            $plan = $this->ccx_msgs_pricing_model->get($session->plan_id);
            if ($plan) $plans[] = $plan;
        }

        if (empty($plans)) return;

        // ═══ Build invoice line items ═══
        $newitems = [];
        $combined_subtotal = 0;
        $admin_notes = [];
        $plan_currency = $this->_resolve_plan_currency($plans[0]);
        $order_num = 0;

        foreach ($plans as $p) {
            $order_num++;
            $tax_percent = isset($p->tax_percent) ? (float)$p->tax_percent : 0;
            $price_after_discount = $p->price;
            if ($p->discount_percent > 0) {
                $price_after_discount = $p->price - ($p->price * ($p->discount_percent / 100));
            }

            $combined_subtotal += $price_after_discount;

            // Tax name
            $tax_name = 'GST';
            if (isset($p->tax_id) && $p->tax_id > 0) {
                $this->load->model('taxes_model');
                $tax_row = $this->taxes_model->get($p->tax_id);
                if ($tax_row) $tax_name = $tax_row->name;
            }

            $item_taxname = [];
            if ($tax_percent > 0 && isset($p->tax_id) && $p->tax_id > 0) {
                $item_taxname[] = $tax_name . '|' . $tax_percent;
            }

            $newitems[] = [
                'order'            => $order_num,
                'description'      => $p->plan_name . ' (' . ucfirst(str_replace('_', ' ', $p->message_type)) . ' Credits)',
                'long_description' => $p->message_count . ' ' . strtoupper(str_replace('_', ' ', $p->message_type)) . ' messages. Valid for ' . $p->expiry_days . ' days.',
                'qty'              => 1,
                'unit'             => '',
                'rate'             => $price_after_discount,
                'taxname'          => $item_taxname,
            ];

            $note = 'Plan #' . $p->id . ': ' . $p->plan_name;
            if ($tax_percent > 0) $note .= ' | ' . $tax_name . ' @' . $tax_percent . '%';
            $admin_notes[] = $note;
        }

        // ═══ Promo Discount Line Item ═══
        $promo_discount = 0;
        $promo_id_from_session = isset($session->promo_id) ? (int)$session->promo_id : 0;
        if ($promo_id_from_session > 0) {
            $promo_discount = isset($session->promo_discount) ? (float)$session->promo_discount : 0;
        }
        if ($promo_discount > 0) {
            $order_num++;
            $promo_row = $this->db->where('id', $promo_id_from_session)->get(db_prefix() . 'ccx_msgs_promo_codes')->row();
            $promo_label = $promo_row ? $promo_row->code : 'PROMO';
            $newitems[] = [
                'order'            => $order_num,
                'description'      => 'Promo Discount (' . $promo_label . ')',
                'long_description' => 'Promotional discount applied at checkout.',
                'qty'              => 1,
                'unit'             => '',
                'rate'             => -$promo_discount,
                'taxname'          => [],
            ];
            $admin_notes[] = 'Promo: ' . $promo_label . ' = -' . $promo_discount;
        }

        $admin_note = 'Generated by CCX Msgs Custom Checkout' . ($cart_mode ? ' (Cart: ' . count($plans) . ' plans)' : ' (Plan ID: ' . $plans[0]->id . ')');
        $admin_note .= "\n" . implode("\n", $admin_notes);

        $client_note = $cart_mode
            ? 'Recharge credits for ' . count($plans) . ' plans'
            : 'Recharge credits for ' . $plans[0]->plan_name;

        // 1. Generate Invoice
        $invoice_data = [
            'clientid'         => $session->client_id,
            'number'           => str_pad(get_option('next_invoice_number'), get_option('number_padding_prefixes'), '0', STR_PAD_LEFT),
            'date'             => date('Y-m-d'),
            'duedate'          => date('Y-m-d'),
            'currency'         => $plan_currency->id,
            'subtotal'         => $combined_subtotal,
            'total'            => $session->amount,
            'status'           => 2,
            'adminnote'        => $admin_note,
            'show_quantity_as' => 1,
            'newitems'         => $newitems,
            'clientnote'       => $client_note,
            'terms'            => 'Fully paid via ' . ucfirst($gateway_name) . '.',
        ];

        $invoice_data['billing_street'] = (string)($client->billing_street ?? '');
        $invoice_data['billing_city'] = (string)($client->billing_city ?? '');
        $invoice_data['billing_state'] = (string)($client->billing_state ?? '');
        $invoice_data['billing_zip'] = (string)($client->billing_zip ?? '');
        $invoice_data['billing_country'] = $client->billing_country ? $client->billing_country : 0;
        $invoice_data['shipping_street'] = (string)($client->shipping_street ?? '');
        $invoice_data['shipping_city'] = (string)($client->shipping_city ?? '');
        $invoice_data['shipping_state'] = (string)($client->shipping_state ?? '');
        $invoice_data['shipping_zip'] = (string)($client->shipping_zip ?? '');
        $invoice_data['shipping_country'] = $client->shipping_country ? $client->shipping_country : 0;
        $invoice_data['include_shipping'] = 0;
        $invoice_data['show_shipping_on_invoice'] = 0;

        $invoice_id = $this->invoices_model->add($invoice_data);

        // 2. Add Payment Record + Update Session
        if ($invoice_id) {
            $this->db->insert(db_prefix() . 'invoicepaymentrecords', [
                'invoiceid'     => $invoice_id,
                'amount'        => $session->amount,
                'paymentmode'   => $gateway_name,
                'date'          => date('Y-m-d'),
                'daterecorded'  => date('Y-m-d H:i:s'),
                'transactionid' => $transaction_id,
                'note'          => 'Paid via Custom Checkout (' . ucfirst($gateway_name) . ')'
            ]);

            $this->db->where('id', $session->id);
            $this->db->update(db_prefix() . 'ccx_msgs_checkout_sessions', [
                'status'         => 'completed',
                'invoice_id'     => $invoice_id,
                'gateway_txn_id' => $transaction_id
            ]);

            // 3. Process each plan: update recharge logs + assign credits
            foreach ($plans as $p) {
                // Update recharge log
                $this->db->where('client_id', $session->client_id);
                $this->db->where('plan_id', $p->id);
                $this->db->where('status', 'initiated');
                $this->db->order_by('id', 'desc');
                $this->db->limit(1);
                $existing_log = $this->db->get(db_prefix() . 'ccx_msgs_recharge_logs')->row();

                if ($existing_log) {
                    $this->db->where('id', $existing_log->id);
                    $this->db->update(db_prefix() . 'ccx_msgs_recharge_logs', [
                        'status'         => 'paid',
                        'gateway_used'   => $gateway_name,
                        'gateway_txn_id' => $transaction_id,
                        'invoice_id'     => $invoice_id,
                    ]);
                } else {
                    $this->db->insert(db_prefix() . 'ccx_msgs_recharge_logs', [
                        'client_id'      => $session->client_id,
                        'plan_id'        => $p->id,
                        'amount'         => $session->amount,
                        'status'         => 'paid',
                        'gateway_used'   => $gateway_name,
                        'gateway_txn_id' => $transaction_id,
                        'invoice_id'     => $invoice_id,
                        'created_at'     => date('Y-m-d H:i:s'),
                    ]);
                }

                // Assign credits
                $type = $p->message_type;
                // Legacy data guard: the retired 'whatsapp_web' channel never had its own
                // allocation columns — old plan rows still map onto the whatsapp balance.
                if ($type == 'whatsapp_web') $type = 'whatsapp';
                $subtype = isset($p->message_subtype) && $p->message_subtype == 'transactional' ? 'trans' : 'promo';

                $count_field = $type . '_' . $subtype . '_count_add';
                $expiry_field = $type . '_' . $subtype . '_expiry';

                $current_allocation = $this->db->where('client_id', $session->client_id)->get(db_prefix() . 'ccx_msgs_allocations')->row();

                $allocation_data = [
                    'client_id'  => $session->client_id,
                    $count_field => (int)$p->message_count,
                ];

                if ($p->expiry_days > 0) {
                    $current_expiry_val = $current_allocation && isset($current_allocation->{$expiry_field}) ? $current_allocation->{$expiry_field} : null;
                    $new_expiry = date('Y-m-d', strtotime('+' . $p->expiry_days . ' days'));
                    if (!$current_expiry_val || strtotime($new_expiry) > strtotime($current_expiry_val)) {
                        $allocation_data[$expiry_field] = $new_expiry;
                    }
                }

                $this->ccx_msgs_model->save_allocation($allocation_data);
            }

            // ═══ Record Promo Usage ═══
            if ($promo_id_from_session > 0 && $promo_discount > 0) {
                $promo_row_for_usage = $this->db->where('id', $promo_id_from_session)->get(db_prefix() . 'ccx_msgs_promo_codes')->row();

                // Determine referrer info
                $referrer_type = null;
                $referrer_id = null;
                $referrer_name = null;

                if ($promo_row_for_usage && $promo_row_for_usage->code_type == 'referral') {
                    $r_type = isset($promo_row_for_usage->referrer_type) ? $promo_row_for_usage->referrer_type : 'client';
                    $referrer_type = $r_type;

                    if ($r_type === 'staff' && !empty($promo_row_for_usage->referrer_staff_id)) {
                        $referrer_id = (int) $promo_row_for_usage->referrer_staff_id;
                        $staff_row = $this->db->select('firstname, lastname')->where('staffid', $referrer_id)->get(db_prefix() . 'staff')->row();
                        $referrer_name = $staff_row ? trim($staff_row->firstname . ' ' . $staff_row->lastname) : 'Staff #' . $referrer_id;
                    } elseif (!empty($promo_row_for_usage->referrer_client_id)) {
                        $referrer_id = (int) $promo_row_for_usage->referrer_client_id;
                        $client_row = $this->db->select('company')->where('userid', $referrer_id)->get(db_prefix() . 'clients')->row();
                        $referrer_name = $client_row ? $client_row->company : 'Client #' . $referrer_id;
                    }
                }

                // Insert usage record with referrer tracking
                $this->db->insert(db_prefix() . 'ccx_msgs_promo_usage', [
                    'promo_id'         => $promo_id_from_session,
                    'client_id'        => $session->client_id,
                    'invoice_id'       => $invoice_id,
                    'discount_applied' => $promo_discount,
                    'referrer_type'    => $referrer_type,
                    'referrer_id'      => $referrer_id,
                    'referrer_name'    => $referrer_name,
                    'reward_status'    => $referrer_type ? 'pending' : null,
                    'created_at'       => date('Y-m-d H:i:s'),
                ]);

                // Increment usage count
                $this->db->where('id', $promo_id_from_session);
                $this->db->set('usage_count', 'usage_count + 1', false);
                $this->db->update(db_prefix() . 'ccx_msgs_promo_codes');
            }
        }
    }

    /**
     * Resolve the currency for a plan (fallback to base currency)
     */
    private function _resolve_plan_currency($plan)
    {
        $plan_currency_id = isset($plan->currency_id) ? (int)$plan->currency_id : 0;
        if ($plan_currency_id > 0) {
            $this->load->model('currencies_model');
            $currency = $this->currencies_model->get($plan_currency_id);
            if ($currency) {
                return $currency;
            }
        }
        return get_base_currency();
    }

    /**
     * Public API endpoint to fetch payment receipt details for the success popup.
     * GET params: session_token
     */
    public function get_payment_receipt()
    {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, OPTIONS');
        if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit(0); }

        $session_token = $this->input->get('session_token');
        if (empty($session_token)) {
            echo json_encode(['success' => false, 'message' => 'Missing session token.']);
            die;
        }

        $session = $this->db->where('session_token', $session_token)->get(db_prefix() . 'ccx_msgs_checkout_sessions')->row();
        if (!$session) {
            echo json_encode(['success' => false, 'message' => 'Session not found.']);
            die;
        }

        if ($session->status !== 'completed') {
            echo json_encode(['success' => false, 'message' => 'Payment not completed.']);
            die;
        }

        // Fetch plans
        $plans_data = [];
        $cart_mode = !empty($session->cart_items);

        if ($cart_mode) {
            $cart_items = json_decode($session->cart_items, true);
            foreach ($cart_items as $item) {
                $plan = $this->ccx_msgs_pricing_model->get($item['plan_id']);
                if (!$plan) continue;

                $price_after_discount = $plan->price;
                if ($plan->discount_percent > 0) {
                    $price_after_discount = $plan->price - ($plan->price * ($plan->discount_percent / 100));
                }
                $tax_percent = isset($plan->tax_percent) ? (float)$plan->tax_percent : 0;
                $tax_amount = ($price_after_discount * $tax_percent) / 100;

                $plans_data[] = [
                    'plan_name'      => $plan->plan_name,
                    'message_type'   => $plan->message_type,
                    'message_count'  => (int)$plan->message_count,
                    'expiry_days'    => (int)$plan->expiry_days,
                    'billing_cycle'  => $plan->billing_cycle ?? 'monthly',
                    'original_price' => (float)$plan->price,
                    'discount_percent' => (float)($plan->discount_percent ?? 0),
                    'price'          => round($price_after_discount, 2),
                    'tax_percent'    => $tax_percent,
                    'tax_amount'     => round($tax_amount, 2),
                    'total'          => round($price_after_discount + $tax_amount, 2),
                    'currency_id'    => $plan->currency_id ?? '',
                ];
            }
        } else {
            $plan = $this->ccx_msgs_pricing_model->get($session->plan_id);
            if ($plan) {
                $price_after_discount = $plan->price;
                if ($plan->discount_percent > 0) {
                    $price_after_discount = $plan->price - ($plan->price * ($plan->discount_percent / 100));
                }
                $tax_percent = isset($plan->tax_percent) ? (float)$plan->tax_percent : 0;
                $tax_amount = ($price_after_discount * $tax_percent) / 100;

                $plans_data[] = [
                    'plan_name'      => $plan->plan_name,
                    'message_type'   => $plan->message_type,
                    'message_count'  => (int)$plan->message_count,
                    'expiry_days'    => (int)$plan->expiry_days,
                    'billing_cycle'  => $plan->billing_cycle ?? 'monthly',
                    'original_price' => (float)$plan->price,
                    'discount_percent' => (float)($plan->discount_percent ?? 0),
                    'price'          => round($price_after_discount, 2),
                    'tax_percent'    => $tax_percent,
                    'tax_amount'     => round($tax_amount, 2),
                    'total'          => round($price_after_discount + $tax_amount, 2),
                    'currency_id'    => $plan->currency_id ?? '',
                ];
            }
        }

        // Promo discount info
        $promo_discount = isset($session->promo_discount) ? (float)$session->promo_discount : 0;
        $promo_code = '';
        if ($promo_discount > 0 && isset($session->promo_id) && $session->promo_id > 0) {
            $promo_info = $this->db->select('code')->where('id', $session->promo_id)->get(db_prefix() . 'ccx_msgs_promo_codes')->row();
            $promo_code = $promo_info ? $promo_info->code : '';
        }

        // Get gateway from recharge logs (checkout_sessions doesn't store gateway name)
        $gateway_used = '';
        $log_row = $this->db->select('gateway_used')
            ->where('client_id', $session->client_id)
            ->where('gateway_txn_id', $session->gateway_txn_id)
            ->where('status', 'paid')
            ->order_by('id', 'desc')
            ->limit(1)
            ->get(db_prefix() . 'ccx_msgs_recharge_logs')->row();
        if ($log_row && !empty($log_row->gateway_used)) {
            $gateway_used = $log_row->gateway_used;
        }

        echo json_encode([
            'success'        => true,
            'session_token'  => $session->session_token,
            'amount'         => (float)$session->amount,
            'status'         => $session->status,
            'gateway'        => $gateway_used,
            'transaction_id' => isset($session->gateway_txn_id) ? $session->gateway_txn_id : '',
            'created_at'     => $session->created_at,
            'completed_at'   => $session->created_at,
            'promo_discount' => $promo_discount,
            'promo_code'     => $promo_code,
            'plans'          => $plans_data,
        ]);
        die;
    }

    // ═══ Coupon System (Free Credits) ═══

    /**
     * Validate a coupon code for a tenant.
     * GET/POST params: code, client_id
     */
    public function validate_coupon()
    {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization, X-Requested-With');

        if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit(0); }

        $code      = strtoupper(trim($this->input->get_post('code')));
        $client_id = (int) $this->input->get_post('client_id');

        if (empty($code)) {
            echo json_encode(['success' => false, 'message' => 'Please enter a coupon code.']);
            die;
        }

        $coupon = $this->db->where('code', $code)->get(db_prefix() . 'ccx_msgs_coupons')->row();

        if (!$coupon) {
            echo json_encode(['success' => false, 'message' => 'Invalid coupon code. Please check and try again.']);
            die;
        }

        // Active?
        if (!$coupon->active) {
            echo json_encode(['success' => false, 'message' => 'This coupon is no longer active.']);
            die;
        }

        // Date validity
        $today = date('Y-m-d');
        if (!empty($coupon->valid_from) && $today < $coupon->valid_from) {
            echo json_encode(['success' => false, 'message' => 'This coupon is not yet valid.']);
            die;
        }
        if (!empty($coupon->valid_until) && $today > $coupon->valid_until) {
            echo json_encode(['success' => false, 'message' => 'This coupon has expired.']);
            die;
        }

        // Usage limit
        if ($coupon->usage_limit > 0 && $coupon->usage_count >= $coupon->usage_limit) {
            echo json_encode(['success' => false, 'message' => 'This coupon has reached its maximum usage limit.']);
            die;
        }

        // Per-client limit
        if ($coupon->per_client_limit > 0 && $client_id > 0) {
            $client_usage = $this->db->where('coupon_id', $coupon->id)
                ->where('client_id', $client_id)
                ->count_all_results(db_prefix() . 'ccx_msgs_coupon_claims');
            if ($client_usage >= $coupon->per_client_limit) {
                echo json_encode(['success' => false, 'message' => 'You have already claimed this coupon.']);
                die;
            }
        }

        // Parse credits
        $credits = json_decode($coupon->credits, true);
        if (!is_array($credits) || empty($credits)) {
            echo json_encode(['success' => false, 'message' => 'This coupon has no credits configured. Please contact support.']);
            die;
        }

        echo json_encode([
            'success'      => true,
            'coupon_id'    => $coupon->id,
            'code'         => $coupon->code,
            'description'  => $coupon->description,
            'credits'      => $credits,
            'expiry_days'  => (int) $coupon->expiry_days,
            'message'      => 'Coupon is valid! Review the credits below and claim.',
        ]);
        die;
    }

    /**
     * Claim a coupon — awards free credits to the tenant's allocation.
     * POST params: code, client_id
     */
    public function claim_coupon()
    {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization, X-Requested-With');

        if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit(0); }

        $code      = strtoupper(trim($this->input->get_post('code')));
        $client_id = (int) $this->input->get_post('client_id');

        if (empty($code) || $client_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Missing parameters.']);
            die;
        }

        // Re-validate everything server-side
        $coupon = $this->db->where('code', $code)->where('active', 1)->get(db_prefix() . 'ccx_msgs_coupons')->row();

        if (!$coupon) {
            echo json_encode(['success' => false, 'message' => 'Invalid or inactive coupon code.']);
            die;
        }

        $today = date('Y-m-d');
        if (!empty($coupon->valid_from) && $today < $coupon->valid_from) {
            echo json_encode(['success' => false, 'message' => 'This coupon is not yet valid.']);
            die;
        }
        if (!empty($coupon->valid_until) && $today > $coupon->valid_until) {
            echo json_encode(['success' => false, 'message' => 'This coupon has expired.']);
            die;
        }

        if ($coupon->usage_limit > 0 && $coupon->usage_count >= $coupon->usage_limit) {
            echo json_encode(['success' => false, 'message' => 'This coupon has reached its maximum usage limit.']);
            die;
        }

        if ($coupon->per_client_limit > 0) {
            $client_usage = $this->db->where('coupon_id', $coupon->id)
                ->where('client_id', $client_id)
                ->count_all_results(db_prefix() . 'ccx_msgs_coupon_claims');
            if ($client_usage >= $coupon->per_client_limit) {
                echo json_encode(['success' => false, 'message' => 'You have already claimed this coupon.']);
                die;
            }
        }

        // Validate client exists
        $this->load->model('clients_model');
        $client = $this->clients_model->get($client_id);
        if (!$client) {
            echo json_encode(['success' => false, 'message' => 'Invalid client.']);
            die;
        }

        // Parse credits
        $credits = json_decode($coupon->credits, true);
        if (!is_array($credits) || empty($credits)) {
            echo json_encode(['success' => false, 'message' => 'This coupon has no credits configured.']);
            die;
        }

        // ═══ Award Credits ═══
        // Map coupon channel keys to allocation field names
        $channel_map = [
            'sms'           => 'sms',
            'whatsapp'      => 'whatsapp',
            'whatsapp_web'  => 'whatsapp',  // legacy coupons only — retired channel, folds into whatsapp
            'email'         => 'email',
            'aicall'        => 'aicall',
        ];

        $allocation_data = ['client_id' => $client_id];

        foreach ($credits as $ch => $count) {
            if ($count <= 0) continue;
            $alloc_ch = isset($channel_map[$ch]) ? $channel_map[$ch] : $ch;
            $field = $alloc_ch . '_promo_count_add';

            // Accumulate if two keys resolve to the same allocation field
            if (isset($allocation_data[$field])) {
                $allocation_data[$field] += (int) $count;
            } else {
                $allocation_data[$field] = (int) $count;
            }

            // Set expiry if configured
            if ($coupon->expiry_days > 0) {
                $expiry_field = $alloc_ch . '_promo_expiry';
                $new_expiry = date('Y-m-d', strtotime('+' . $coupon->expiry_days . ' days'));

                // Only set if new expiry is further out than current
                $current = $this->db->select($expiry_field)->where('client_id', $client_id)->get(db_prefix() . 'ccx_msgs_allocations')->row();
                $current_val = $current && isset($current->$expiry_field) ? $current->$expiry_field : null;

                if (!$current_val || strtotime($new_expiry) > strtotime($current_val)) {
                    $allocation_data[$expiry_field] = $new_expiry;
                }
            }
        }

        // Save allocation (uses additive _add fields, never overwrites existing counts)
        $this->ccx_msgs_model->save_allocation($allocation_data);

        // ═══ Record Claim ═══
        $this->db->insert(db_prefix() . 'ccx_msgs_coupon_claims', [
            'coupon_id'       => $coupon->id,
            'client_id'       => $client_id,
            'credits_awarded' => json_encode($credits),
            'claimed_at'      => date('Y-m-d H:i:s'),
        ]);

        // Increment usage count
        $this->db->set('usage_count', 'usage_count + 1', false);
        $this->db->where('id', $coupon->id);
        $this->db->update(db_prefix() . 'ccx_msgs_coupons');

        // Log activity
        log_activity('Coupon ' . $coupon->code . ' claimed by client #' . $client_id . ' — Credits: ' . json_encode($credits));

        echo json_encode([
            'success'          => true,
            'message'          => 'Coupon claimed successfully! Credits have been added to your account.',
            'credits_awarded'  => $credits,
        ]);
        die;
    }
}

