<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Authentication extends ClientsController
{
    public function __construct()
    {
        parent::__construct();
        hooks()->do_action('clients_authentication_constructor', $this);
    }

    public function index()
    {
        $this->login();
    }

    // Added for backward compatibilies
    public function admin()
    {
        redirect(admin_url('authentication'));
    }

    public function login()
    {
        if (is_client_logged_in()) {
            redirect(site_url());
        }

        $this->form_validation->set_rules('password', _l('clients_login_password'), 'required');
        $this->form_validation->set_rules('email', _l('clients_login_email'), 'trim|required|valid_email');

        if (show_recaptcha_in_customers_area()) {
            $this->form_validation->set_rules('g-recaptcha-response', 'Captcha', 'callback_recaptcha');
        }
        if ($this->form_validation->run() !== false) {
            $this->load->model('Authentication_model');

            $success = $this->Authentication_model->login(
                $this->input->post('email'),
                $this->input->post('password', false),
                $this->input->post('remember'),
                false
            );

            if (is_array($success) && isset($success['memberinactive'])) {
                set_alert('danger', _l('inactive_account'));
                redirect(site_url('authentication/login'));
            } elseif ($success == false) {
                set_alert('danger', _l('client_invalid_username_or_password'));
                redirect(site_url('authentication/login'));
            }

            if ($this->input->post('language') && $this->input->post('language') != '') {
                set_contact_language($this->input->post('language'));
            }

            $this->load->model('announcements_model');
            $this->announcements_model->set_announcements_as_read_except_last_one(get_contact_user_id());

            hooks()->do_action('after_contact_login');

            maybe_redirect_to_previous_url();
            redirect(site_url());
        }
        if (get_option('allow_registration') == 1) {
            $data['title'] = _l('clients_login_heading_register');
        } else {
            $data['title'] = _l('clients_login_heading_no_register');
        }
        $data['bodyclass'] = 'customers_login';

        $this->data($data);
        $this->view('login');
        $this->layout();
    }

    public function register()
    {
        // Allow registration when a SaaS package plan link is used (ps_plan parameter).
        // Check POST (hidden form field), GET (URL param), and session (saved from initial visit).
        $ps_plan_key = function_exists('perfex_saas_route_id_prefix')
            ? perfex_saas_route_id_prefix('plan')
            : 'ps_plan';
        $has_saas_plan = !empty($this->input->post_get($ps_plan_key, true))
                      || !empty($this->session->{$ps_plan_key});

        // Allow access for OTP verification (user is logged in but needs to verify email)
        $otp_pending = $this->session->userdata('otp_verification_pending');

        if (!$has_saas_plan && !$otp_pending && (get_option('allow_registration') != 1 || is_client_logged_in())) {
            redirect(site_url());
        }

        // Hide default Perfex header/footer for clean registration page
        $this->disableNavigation();
        $this->disableSubMenu();
        $this->disableFooter();

        $requiredFields = get_required_fields_for_registration();
       
        $honeypot = get_option('enable_honeypot_spam_validation') == 1;

        $fields = [
            'firstname' => $honeypot ? 'firstnamemjxw' : 'firstname',
            'lastname'  => $honeypot ? 'lastnamemjxw' : 'lastname',
            'email'     => $honeypot ? 'emailmjxw' : 'email',
            'company'   => $honeypot ? 'companymjxw' : 'company',
        ];

        if (get_option('company_is_required') == 1) {
            $this->form_validation->set_rules($fields['company'], _l('client_company'), 'required');
        }

        $emailRules = 'trim|is_unique[' . db_prefix() . 'contacts.email]|valid_email';

        foreach(['contact', 'company'] as $fieldsKey) {
            foreach($requiredFields[$fieldsKey] as $key => $field) {
                $formKey = strafter($key, '_');

                if(isset($fields[$formKey])) {
                    $formKey = $fields[$formKey];
                }
                
                if($key !== 'contact_email'){
                    if($field['is_required']) {
                        $this->form_validation->set_rules($formKey, $field['label'], 'required');
                    }
                } else {
                    if($field['is_required']) {
                        $emailRules .= '|required';
                    }

                    $this->form_validation->set_rules($formKey, $field['label'], $emailRules);
                }
            }
        }

        if (is_gdpr() && get_option('gdpr_enable_terms_and_conditions') == 1) {
            $this->form_validation->set_rules(
                'accept_terms_and_conditions',
                _l('terms_and_conditions'),
                'required',
                ['required' => _l('terms_and_conditions_validation')]
            );
        }
       
        $this->form_validation->set_rules('password', _l('clients_register_password'), 'required');
        $this->form_validation->set_rules('passwordr', _l('clients_register_password_repeat'), 'required|matches[password]');

        if (show_recaptcha_in_customers_area()) {
            $this->form_validation->set_rules('g-recaptcha-response', 'Captcha', 'callback_recaptcha');
        }

        $custom_fields = get_custom_fields('customers', [
            'show_on_client_portal' => 1,
            'required'              => 1,
        ]);

        $custom_fields_contacts = get_custom_fields('contacts', [
            'show_on_client_portal' => 1,
            'required'              => 1,
        ]);

        foreach ($custom_fields as $field) {
            $field_name = 'custom_fields[' . $field['fieldto'] . '][' . $field['id'] . ']';
            if ($field['type'] == 'checkbox' || $field['type'] == 'multiselect') {
                $field_name .= '[]';
            }
            $this->form_validation->set_rules($field_name, $field['name'], 'required');
        }

        foreach ($custom_fields_contacts as $field) {
            $field_name = 'custom_fields[' . $field['fieldto'] . '][' . $field['id'] . ']';
            if ($field['type'] == 'checkbox' || $field['type'] == 'multiselect') {
                $field_name .= '[]';
            }
            $this->form_validation->set_rules($field_name, $field['name'], 'required');
        }

        if ($this->input->post()) {
            // ══════ CANARY TEST — REMOVE AFTER DEBUGGING ══════
            // This writes DIRECTLY to a file with ZERO dependencies on any module.
            // If this file does NOT appear after signup → code is NOT deployed to the server.
            $canary_file = APPPATH . 'logs/ccx_canary_test.log';
            $canary_data = '[' . date('Y-m-d H:i:s') . '] CANARY: Form POST received!' . PHP_EOL;
            $canary_data .= '  IP: ' . $this->input->ip_address() . PHP_EOL;
            $canary_data .= '  POST keys: ' . implode(', ', array_keys($this->input->post())) . PHP_EOL;
            $canary_data .= '  honeypot=' . ($honeypot ? 'YES' : 'NO') . PHP_EOL;
            // Log field values (first 50 chars each) for diagnosis
            foreach ($this->input->post() as $k => $v) {
                if (is_string($v)) {
                    $canary_data .= '  POST[' . $k . '] = ' . mb_substr($v, 0, 50) . PHP_EOL;
                }
            }
            $canary_data .= '  ---' . PHP_EOL;
            @file_put_contents($canary_file, $canary_data, FILE_APPEND | LOCK_EX);
            // ══════ END CANARY TEST ══════

            if ($honeypot &&
                count(array_filter($this->input->post(['email', 'firstname', 'lastname', 'company']))) > 0) {
                show_404();
            }

            // CCX SignUp: Track registration attempt initiated
            hooks()->do_action('ccx_signup_attempt_initiated', $this->input->post());

            // Pre-validation debug logger
            $debugLogFile = APPPATH . 'logs/register_debug.log';
            $regDebugLog = function($msg) use ($debugLogFile) {
                $timestamp = date('Y-m-d H:i:s');
                $line = "[{$timestamp}] REGISTER_DEBUG: {$msg}" . PHP_EOL;
                @file_put_contents($debugLogFile, $line, FILE_APPEND | LOCK_EX);
            };

            $validationResult = $this->form_validation->run();
            $regDebugLog('Validation result: ' . var_export($validationResult, true));

            if ($validationResult === false) {
                $regDebugLog('VALIDATION FAILED! Errors: ' . validation_errors(' | ', ' | '));
                // CCX SignUp: Track failed registration attempt
                hooks()->do_action('ccx_signup_attempt_failed', $this->input->post());
            }

            if ($validationResult !== false) {

                try {
                    $data      = $this->input->post();
                    $countryId = is_numeric($data['country']) ? $data['country'] : 0;

                    $regDebugLog('POST data received. Email: ' . ($data[$fields['email']] ?? 'N/A') . ', Country: ' . $countryId);

                    if (is_automatic_calling_codes_enabled()) {
                        $customerCountry = get_country($countryId);

                        if ($customerCountry) {
                            $callingCode = '+' . ltrim($customerCountry->calling_code, '+');

                            if (startsWith($data['contact_phonenumber'], $customerCountry->calling_code)) { // with calling code but without the + prefix
                                $data['contact_phonenumber'] = '+' . $data['contact_phonenumber'];
                            } elseif (!startsWith($data['contact_phonenumber'], $callingCode)) {
                                $data['contact_phonenumber'] = $callingCode . $data['contact_phonenumber'];
                            }
                        }
                    }

                    define('CONTACT_REGISTERING', true);
                    $regDebugLog('CONTACT_REGISTERING defined. Calling clients_model->add()...');

                    $clientid = $this->clients_model->add([
                          'billing_street'      => $data['address'],
                          'billing_city'        => $data['city'],
                          'billing_state'       => $data['state'],
                          'billing_zip'         => $data['zip'],
                          'billing_country'     => $countryId,
                          'firstname'           => $data[$fields['firstname']],
                          'lastname'            => $data[$fields['lastname']],
                          'email'               => $data[$fields['email']],
                          'contact_phonenumber' => $data['contact_phonenumber'] ,
                          'website'             => $data['website'],
                          'title'               => $data['title'],
                          'password'            => $data['passwordr'],
                          'company'             => $data[$fields['company']],
                          'vat'                 => isset($data['vat']) ? $data['vat'] : '',
                          'phonenumber'         => $data['phonenumber'],
                          'country'             => $data['country'],
                          'city'                => $data['city'],
                          'address'             => $data['address'],
                          'zip'                 => $data['zip'],
                          'state'               => $data['state'],
                          'custom_fields'       => isset($data['custom_fields']) && is_array($data['custom_fields']) ? $data['custom_fields'] : [],
                          'default_language'    => (get_contact_language() != '') ? get_contact_language() : get_option('active_language'),
                    ], true);

                    $regDebugLog('clients_model->add() returned: ' . var_export($clientid, true));

                    if ($clientid) {
                        // Step 1: after_client_register hook
                        $regDebugLog('Step 1: Firing after_client_register hook...');
                        try {
                            hooks()->do_action('after_client_register', $clientid);
                            $regDebugLog('Step 1: Hook completed OK');
                        } catch (\Throwable $e) {
                            $regDebugLog('Step 1: Hook FAILED - ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
                            log_activity('Registration hook error: ' . $e->getMessage());
                        }

                        // Step 2: Check if confirmation is required
                        $requireConfirmation = get_option('customers_register_require_confirmation');
                        $regDebugLog('Step 2: Require confirmation = ' . var_export($requireConfirmation, true));

                        if ($requireConfirmation == '1') {
                            $regDebugLog('Step 2a: Sending admin notification emails (confirmation path)...');
                            try {
                                send_customer_registered_email_to_administrators($clientid);
                                $regDebugLog('Step 2a: Admin emails sent OK');
                            } catch (\Throwable $e) {
                                $regDebugLog('Step 2a: Admin emails FAILED - ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
                                log_activity('Failed to send admin notification email on registration: ' . $e->getMessage());
                            }

                            $regDebugLog('Step 2b: Calling require_confirmation()...');
                            try {
                                $this->clients_model->require_confirmation($clientid);
                                $regDebugLog('Step 2b: require_confirmation() OK');
                            } catch (\Throwable $e) {
                                $regDebugLog('Step 2b: require_confirmation FAILED - ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
                            }

                            $regDebugLog('Step 2c: Redirecting to login (confirmation required)...');
                            set_alert('success', _l('customer_register_account_confirmation_approval_notice'));
                            redirect(site_url('authentication/login'));
                        }

                        // Step 3: Auto-login
                        $regDebugLog('Step 3: Loading authentication_model for auto-login...');
                        $this->load->model('authentication_model');

                        $redUrl = site_url('verification');
                        $logged_in = false;

                        try {
                            $regDebugLog('Step 3a: Attempting auto-login for: ' . $data[$fields['email']]);
                            $logged_in = $this->authentication_model->login(
                                $data[$fields['email']],
                                $this->input->post('password', false),
                                false,
                                false
                            );
                            $regDebugLog('Step 3a: Auto-login result: ' . var_export($logged_in, true));
                        } catch (\Throwable $e) {
                            $logged_in = false;
                            $regDebugLog('Step 3a: Auto-login FAILED - ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
                            log_activity('Auto-login after registration failed: ' . $e->getMessage());
                        }

                        if ($logged_in) {
                            $regDebugLog('Step 4: Login successful, firing after_client_register_logged_in hook...');
                            try {
                                hooks()->do_action('after_client_register_logged_in', $clientid);
                            } catch (\Throwable $e) {
                                $regDebugLog('Step 4: after_client_register_logged_in hook FAILED - ' . get_class($e) . ': ' . $e->getMessage());
                            }
                        } else {
                            $regDebugLog('Step 4: Auto-login failed or returned inactive status');
                        }

                        set_alert('success', _l('clients_successfully_registered'));

                        // OTP verification: render Step 3 directly (no redirect)
                        // Note: OTP email is already sent by Clients_model during contact creation
                        $primary_contact_id = get_primary_contact_user_id($clientid);
                        $regDebugLog('Step 4a: OTP flow - primary_contact_id = ' . var_export($primary_contact_id, true));

                        $otp_email = $data[$fields['email']];
                        $otp_phone = isset($data['contact_phonenumber']) ? $data['contact_phonenumber'] : '';

                        // Store in session as backup
                        $this->session->set_userdata('otp_verification_pending', true);
                        $this->session->set_userdata('otp_user_email', $otp_email);
                        $this->session->set_userdata('otp_user_phone', $otp_phone);

                        // Step 5: Send admin notification
                        $regDebugLog('Step 5: Sending admin notification emails...');
                        try {
                            send_customer_registered_email_to_administrators($clientid);
                            $regDebugLog('Step 5: Admin emails sent OK');
                        } catch (\Throwable $e) {
                            $regDebugLog('Step 5: Admin emails FAILED - ' . $e->getMessage());
                            log_activity('Failed to send admin notification email on registration: ' . $e->getMessage());
                        }

                        // RENDER DIRECTLY — do NOT redirect (avoids session loss from constructor logout)
                        $regDebugLog('Step 6: Rendering OTP step directly (no redirect)');
                        $regDebugLog('=== REGISTRATION COMPLETE ===');

                        $data2 = [];
                        $data2['title']     = _l('clients_register_heading');
                        $data2['bodyclass'] = 'register';
                        $data2['honeypot']  = $honeypot;
                        $data2['fields']    = $fields;
                        $data2['otp_verification_pending'] = true;
                        $data2['otp_user_email']           = $otp_email;
                        $data2['otp_user_phone']           = $otp_phone;
                        $data2['otp_contact_id']           = $primary_contact_id;

                        $this->data($data2);
                        $this->view('register');
                        $this->layout();
                        return; // Stop execution — page is rendered
                    } else {
                        $regDebugLog('ERROR: clients_model->add() returned falsy. DB insert failed.');
                        $regDebugLog('Last DB error: ' . json_encode($this->db->error()));
                        set_alert('danger', _l('clients_register_account_failed', 'Registration failed. Please try again.'));
                        redirect(site_url('authentication/register'));
                    }
                } catch (\Throwable $e) {
                    // LAST RESORT: catch absolutely everything
                    $regDebugLog('FATAL ERROR: ' . get_class($e) . ': ' . $e->getMessage());
                    $regDebugLog('File: ' . $e->getFile() . ':' . $e->getLine());
                    $regDebugLog('Trace: ' . $e->getTraceAsString());
                    log_activity('FATAL registration error: ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
                    set_alert('danger', 'An error occurred during registration. Please try again or contact support.');
                    redirect(site_url('authentication/register'));
                }
            }
        }

        $data['requiredFields'] = $requiredFields;
        $data['title']     = _l('clients_register_heading');
        $data['bodyclass'] = 'register';
        $data['honeypot']  = $honeypot;
        $data['fields']    = $fields;

        // OTP verification data
        $data['otp_verification_pending'] = $this->session->userdata('otp_verification_pending') ? true : false;
        $data['otp_user_email']           = $this->session->userdata('otp_user_email') ?: '';
        $data['otp_user_phone']           = $this->session->userdata('otp_user_phone') ?: '';

        $this->data($data);
        $this->view('register');
        $this->layout();
    }

    /**
     * AJAX: Track signup form step completion (per-step saving)
     * Called from register.php JS when user transitions between steps.
     */
    public function track_signup_step()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $step = (int) $this->input->post('step');

        if ($step === 1) {
            // Step 1 completed — save partial data
            $honeypot = get_option('enable_honeypot_spam_validation') == 1;
            $company_field = $honeypot ? 'companymjxw' : 'company';

            $ps_plan_key = function_exists('perfex_saas_route_id_prefix')
                ? perfex_saas_route_id_prefix('plan')
                : 'ps_plan';

            $step_data = [
                'company_name' => $this->input->post($company_field, true) ?: '',
                'plan_slug'    => $this->input->post($ps_plan_key, true)
                                  ?: ($this->input->get($ps_plan_key, true) ?: ''),
            ];

            // Fire the hook so the ccx_signup module saves it
            hooks()->do_action('ccx_signup_step1_completed', $step_data);

            header('Content-Type: application/json');
            echo json_encode([
                'success'    => true,
                'csrf_token' => $this->security->get_csrf_hash(),
            ]);
            exit;
        }

        header('Content-Type: application/json');
        echo json_encode([
            'success'    => false,
            'message'    => 'Unknown step.',
            'csrf_token' => $this->security->get_csrf_hash(),
        ]);
        exit;
    }

    public function forgot_password()
    {
        if (is_client_logged_in()) {
            redirect(site_url());
        }

        $this->form_validation->set_rules(
            'email',
            _l('customer_forgot_password_email'),
            'trim|required|valid_email|callback_contact_email_exists'
        );

        if ($this->input->post()) {
            if ($this->form_validation->run() !== false) {
                $this->load->model('Authentication_model');
                $success = $this->Authentication_model->forgot_password($this->input->post('email'));
                if (is_array($success) && isset($success['memberinactive'])) {
                    set_alert('danger', _l('inactive_account'));
                } elseif ($success == true) {
                    set_alert('success', _l('check_email_for_resetting_password'));
                } else {
                    set_alert('danger', _l('error_setting_new_password_key'));
                }
                redirect(site_url('authentication/forgot_password'));
            }
        }
        $data['title'] = _l('customer_forgot_password');
        $this->data($data);
        $this->view('forgot_password');

        $this->layout();
    }

    public function reset_password($staff, $userid, $new_pass_key)
    {
        $this->load->model('Authentication_model');
        if (!$this->Authentication_model->can_reset_password($staff, $userid, $new_pass_key)) {
            set_alert('danger', _l('password_reset_key_expired'));
            redirect(site_url('authentication/login'));
        }

        $this->form_validation->set_rules('password', _l('customer_reset_password'), 'required');
        $this->form_validation->set_rules('passwordr', _l('customer_reset_password_repeat'), 'required|matches[password]');
        if ($this->input->post()) {
            if ($this->form_validation->run() !== false) {
                hooks()->do_action('before_user_reset_password', [
                    'staff'  => $staff,
                    'userid' => $userid,
                ]);
                $success = $this->Authentication_model->reset_password(
                    0,
                    $userid,
                    $new_pass_key,
                    $this->input->post('passwordr', false)
                );
                if (is_array($success) && $success['expired'] == true) {
                    set_alert('danger', _l('password_reset_key_expired'));
                } elseif ($success == true) {
                    hooks()->do_action('after_user_reset_password', [
                        'staff'  => $staff,
                        'userid' => $userid,
                    ]);
                    set_alert('success', _l('password_reset_message'));
                } else {
                    set_alert('danger', _l('password_reset_message_fail'));
                }
                redirect(site_url('authentication/login'));
            }
        }
        $data['title'] = _l('admin_auth_reset_password_heading');
        $this->data($data);
        $this->view('reset_password');
        $this->layout();
    }

    public function logout()
    {
        $this->load->model('authentication_model');
        $this->authentication_model->logout(false);
        hooks()->do_action('after_client_logout');
        redirect(site_url('authentication/login'));
    }

    public function contact_email_exists($email = '')
    {
        $this->db->where('email', $email);
        $total_rows = $this->db->count_all_results(db_prefix() . 'contacts');

        if ($total_rows == 0) {
            $this->form_validation->set_message('contact_email_exists', _l('auth_reset_pass_email_not_found'));

            return false;
        }

        return true;
    }

    public function recaptcha($str = '')
    {
        return do_recaptcha_validation($str);
    }

    public function change_language($lang = '')
    {
        if (is_language_disabled()) {
            redirect(site_url());
        }

        set_contact_language($lang);

        redirect(previous_url() ?: $_SERVER['HTTP_REFERER']);
    }
}
