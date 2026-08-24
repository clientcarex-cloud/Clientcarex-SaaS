<?php defined('BASEPATH') or exit('No direct script access allowed');

class Companies extends AdminController
{

    /**
     * @inheritDoc
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Display the list of all companies.
     */
    function index()
    {
        // Load the model first so it is available for AJAX table generation
        $this->load->model('perfex_saas_model');

        // Check for permission
        if (!staff_can('view', 'perfex_saas_companies')) {
            return access_denied('perfex_saas_companies');
        }

        // Return the table data for ajax request
        if ($this->input->is_ajax_request()) {
            // $this->app->get_table_data(module_views_path(PERFEX_SAAS_MODULE_NAME, 'companies/table'));
        }

        // Show list of comapnies
        // $data['companies'] = $this->perfex_saas_model->companies();


        // Simple Pagination
        $page = $this->input->get('page') ? $this->input->get('page') : 1;
        $search = $this->input->get('q');
        $limit = 25;
        $offset = ($page - 1) * $limit;

        $this->db->select(perfex_saas_table('companies') . '.*, ' . db_prefix() . 'clients.company as client_name, ' . db_prefix() . 'clients.userid as client_id');
        $this->db->from(perfex_saas_table('companies'));
        $this->db->join(db_prefix() . 'clients', db_prefix() . 'clients.userid = ' . perfex_saas_table('companies') . '.clientid', 'left');

        if (!empty($search)) {
            $this->db->group_start();
            $this->db->like(perfex_saas_table('companies') . '.name', $search);
            $this->db->or_like(db_prefix() . 'clients.company', $search);
            $this->db->or_like(perfex_saas_table('companies') . '.status', $search);
            $this->db->group_end();
        }

        // Clone DB for caching count query as using count_all_results with active record clears the state
        $temp_db = clone $this->db;
        $total_rows = $temp_db->count_all_results();

        $this->db->limit($limit, $offset);
        $this->db->order_by(perfex_saas_table('companies') . '.created_at', 'DESC');
        $companies = $this->db->get()->result();

        // Parse — wrapped in try-catch to prevent one bad company from crashing the page
        foreach ($companies as $key => $company) {
            try {
                $companies[$key] = $this->perfex_saas_model->parse_company($company);
            } catch (\Throwable $e) {
                log_message('error', 'SaaS parse_company error for company #' . ($company->id ?? '?') . ': ' . $e->getMessage());
                $companies[$key]->metadata = new stdClass();
            }
            try {
                $companies[$key]->invoice = $this->perfex_saas_model->get_company_invoice($company->clientid);
            } catch (\Throwable $e) {
                log_message('error', 'SaaS get_company_invoice error for client #' . ($company->clientid ?? '?') . ': ' . $e->getMessage());
                $companies[$key]->invoice = null;
            }
        }
        $data['companies'] = $companies;
        $data['total_pages'] = ceil($total_rows / $limit);
        $data['current_page'] = $page;
        $data['search'] = $search;

        $data['title'] = _l('perfex_saas_companies');
        $this->load->view('companies/manage', $data);
    }

    /**
     * Create a new company.
     */
    function create()
    {
        // Check permission to creat
        if (!staff_can('create', 'perfex_saas_companies')) {
            return access_denied('perfex_saas_companies');
        }

        // Save company data
        if ($this->input->post()) {

            // Perform validation and save the new company
            $this->load->library('form_validation');
            $this->form_validation->set_rules('name', _l('perfex_saas_name'), 'required');
            $this->form_validation->set_rules('clientid', _l('perfex_saas_customer'), 'required');
            $this->form_validation->set_rules('db_scheme', _l('perfex_saas_db_scheme'), 'required');

            if ($this->form_validation->run() !== false) {

                $form_data = $this->input->post(NULL, true);

                try {

                    // Get the client invoice
                    $invoice = $this->perfex_saas_model->get_company_invoice($form_data['clientid']);

                    // Require that the client the company is beign created for by admin has an invoice
                    if (!isset($invoice->db_scheme) || empty($invoice->db_scheme)) {
                        throw new \Exception(_l('perfex_saas_no_invoice_client'), 1);
                    }

                    $form_data['dsn'] = '';
                    $db_scheme = $form_data['db_scheme'];

                    // Use the invoice package db scheme
                    if ($db_scheme == 'package') {

                        $form_data['dsn'] = ''; // Dsn will be determined in model base on invoice package
                    }

                    if ($db_scheme == 'single' || $db_scheme == 'multitenancy') {

                        $invoice->db_scheme = $db_scheme; // make temporary update of the invoice scheme reflecting the selected scheme
                        $form_data['dsn'] = ''; // Dsn will be determined in model base on the invoice scheme
                    }

                    // Use provided custom database.
                    if ($db_scheme == 'shard') {

                        // Validate the provided custom db credentails
                        $validation = perfex_saas_is_valid_dsn($form_data['db_pools']);
                        $dsn_string = perfex_saas_dsn_to_string($form_data['db_pools']);

                        if ($validation !== true) {
                            // str replace was done to ensure text wrapping in notifications alert
                            throw new \Exception($validation . ' using dsn: ' . str_ireplace(';', "; ", $dsn_string), 1);
                        }

                        // Add the credential to dsn
                        $form_data['dsn'] = $dsn_string;
                    }

                    // Save only, deployment will be made as another job
                    $_id = $this->perfex_saas_model->create_or_update_company($form_data, $invoice);
                    if ($_id) {

                        set_alert('success', _l('added_successfully', _l('perfex_saas_company')));
                        return redirect(admin_url(PERFEX_SAAS_ROUTE_NAME . '/companies'));
                    }

                    // Log error
                    log_message('error', _l('perfex_saas_error_completing_action') . ':' . ($this->db->error() ?? ''));

                    throw new \Exception(_l('perfex_saas_error_completing_action'), 1);
                } catch (\Exception $e) {

                    set_alert('danger', $e->getMessage());
                    return perfex_saas_redirect_back();
                }
            }
        }

        // Show form to create a new company
        $data['title'] = _l('perfex_saas_companies');
        $this->load->view('companies/form', $data);
    }

    /**
     * Edit an existing company.
     *
     * @param string $id The ID of the company to edit
     */
    function edit($id)
    {
        if (!staff_can('edit', 'perfex_saas_companies')) {
            return access_denied('perfex_saas_companies');
        }

        if ($this->input->post()) {

            // Make some validation
            $this->load->library('form_validation');
            $this->form_validation->set_rules('id', _l('id'), 'required');
            $this->form_validation->set_rules('name', _l('perfex_saas_name'), 'required');
            $this->form_validation->set_rules('clientid', _l('perfex_saas_customer'), 'required');

            if ($this->form_validation->run() !== false) {

                $form_data = $this->input->post(NULL, true);

                try {

                    // Get the client invoice
                    $invoice = $this->perfex_saas_model->get_company_invoice($form_data['clientid']);

                    // Save and make deployment another job
                    $_id = $this->perfex_saas_model->create_or_update_company($form_data, $invoice);
                    if ($_id) {

                        set_alert('success', _l('updated_successfully', _l('perfex_saas_company')));
                        return redirect(admin_url(PERFEX_SAAS_ROUTE_NAME . '/companies'));
                    }

                    // Log error
                    log_message('error', _l('perfex_saas_error_completing_action') . ':' . ($this->db->error() ?? ''));

                    throw new \Exception(_l('perfex_saas_error_completing_action'), 1);
                } catch (\Throwable $th) {

                    set_alert('danger', $th->getMessage());
                    return perfex_saas_redirect_back();
                }
            }
        }

        // Show form to edit the new company
        $data['company'] = $this->perfex_saas_model->companies($id);

        // Get the client invoice/package to identify default modules
        $invoice = $this->perfex_saas_model->get_company_invoice($data['company']->clientid);
        $data['package_modules'] = $invoice->modules ?? [];

        // Fetch remote status
        $data['tenant_module_status'] = $this->get_tenant_modules_status($data['company']);

        $data['title'] = _l('perfex_saas_companies');
        $this->load->view('companies/form', $data);
    }

    /**
     * Change (upgrade/downgrade) a tenant's subscription package from the
     * master admin company edit page.
     *
     * The heavy lifting is delegated to Perfex_saas_model::generate_company_invoice()
     * which performs every safety validation before touching anything:
     * instance-count limit, per-resource quota fit for EVERY instance owned by
     * the client, trial proration and Stripe subscription handling — and only
     * then cancels the previous package invoice and generates the new one.
     * Every change (or attempt reaching the billing stage) is written to the
     * core activity log and to a persistent per-company change history shown
     * on the edit page.
     */
    public function change_subscription()
    {
        if (!staff_can('edit', 'perfex_saas_companies')) {
            return access_denied('perfex_saas_companies');
        }

        if (!$this->input->post()) {
            show_404();
        }

        // generate_company_invoice() runs against these core models on the CI instance
        $this->load->model('perfex_saas_model');
        $this->load->model('currencies_model');
        $this->load->model('invoices_model');
        $this->load->model('payment_modes_model');
        $this->load->model('clients_model');

        $company_id = (int) $this->input->post('company_id', true);
        $packageid  = (int) $this->input->post('packageid', true);
        $note       = trim((string) $this->input->post('change_note', true));

        $redirect_back = admin_url(PERFEX_SAAS_ROUTE_NAME . '/companies/edit/' . $company_id);

        try {
            $company = $this->perfex_saas_model->companies($company_id);
            if (!$company || empty($company->clientid)) {
                throw new \Exception(_l('perfex_saas_subscription_change_no_client'), 1);
            }

            $package = $this->perfex_saas_model->packages($packageid);
            if (!$package || (isset($package->status) && $package->status != '1')) {
                throw new \Exception(_l('perfex_saas_subscription_change_no_package'), 1);
            }

            // Snapshot the current subscription BEFORE the change for the audit trail
            $old_invoice = $this->perfex_saas_model->get_company_invoice($company->clientid);

            $invoice = $this->perfex_saas_model->generate_company_invoice($company->clientid, $packageid);
            $invoice = is_array($invoice) ? (object) $invoice : $invoice;

            // Audit trail — activity log + per-company history
            $this->log_subscription_change($company, $old_invoice, $package, $invoice, $note);

            if (isset($invoice->action_url)) {
                // Stripe-billed package: the new subscription activates only after
                // checkout is completed (the customer can pay from the client portal).
                set_alert('warning', _l('perfex_saas_subscription_change_pending_payment'));
                return redirect($redirect_back);
            }

            set_alert('success', _l('perfex_saas_subscription_changed_successfully', $package->name));
            return redirect($redirect_back);
        } catch (\Throwable $th) {
            set_alert('danger', $th->getMessage());
            return redirect($redirect_back);
        }
    }

    /**
     * Write the subscription change audit trail.
     *
     * 1. Core activity log (tblactivity_log) — system-wide, records acting staff.
     * 2. Company metadata `subscription_change_log` — persistent history rendered
     *    on the company edit page (bounded to the last 50 entries).
     *
     * @param object      $company
     * @param object|null $old_invoice Invoice snapshot before the change
     * @param object      $new_package
     * @param object      $new_invoice Result of generate_company_invoice()
     * @param string      $note
     * @return void
     */
    private function log_subscription_change($company, $old_invoice, $new_package, $new_invoice, $note = '')
    {
        $from = $old_invoice->name ?? _l('perfex_saas_subscription_change_none');
        $pending = isset($new_invoice->action_url);

        log_activity('SaaS Subscription Changed [Company: ' . $company->name . ' (#' . $company->id . '), Client ID: ' . $company->clientid
            . ', From: ' . $from . ', To: ' . $new_package->name
            . ($pending ? ', Pending Payment' : '')
            . ($note !== '' ? ', Note: ' . $note : '') . ']');

        // The history must never break the actual change — log failures silently.
        try {
            $fresh    = $this->perfex_saas_model->companies($company->id);
            $metadata = (object) ($fresh->metadata ?? new stdClass());

            $log   = array_values((array) ($metadata->subscription_change_log ?? []));
            $log[] = [
                'date'            => date('Y-m-d H:i:s'),
                'staff_id'        => get_staff_user_id(),
                'staff_name'      => get_staff_full_name(),
                'from_package_id' => $old_invoice->{perfex_saas_column('packageid')} ?? '',
                'from_package'    => $from,
                'to_package_id'   => $new_package->id,
                'to_package'      => $new_package->name,
                'invoice_id'      => (!empty($new_invoice->id) && empty($new_invoice->is_mock)) ? $new_invoice->id : '',
                'pending_payment' => $pending ? 1 : 0,
                'note'            => $note,
            ];
            if (count($log) > 50) {
                $log = array_slice($log, -50);
            }
            $metadata->subscription_change_log = $log;

            $this->perfex_saas_model->add_or_update('companies', [
                'id'       => $company->id,
                'metadata' => json_encode($metadata),
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'SaaS subscription change: could not write company change log: ' . $e->getMessage());
        }
    }

    function custom_domain()
    {
        if (!staff_can('edit', 'perfex_saas_companies')) {
            return access_denied('perfex_saas_companies');
        }

        if ($this->input->post()) {
            try {

                $form_data = $this->input->post(NULL, true);
                $id = $form_data['id'];
                $company = $this->perfex_saas_model->companies($id);
                $data = ['id' => $company->id, 'metadata' => ['pending_custom_domain' => '']];
                $custom_domain = $company->metadata->pending_custom_domain;
                $approve = !empty($form_data['approve']);
                if ($approve) {
                    $data['custom_domain'] = $custom_domain;
                }

                // Get the client invoice
                $invoice = $this->perfex_saas_model->get_company_invoice($company->clientid);

                // Save and make deployment another job
                $_id = $this->perfex_saas_model->create_or_update_company($data, $invoice);
                if ($_id) {

                    perfex_saas_send_customdomain_request_notice($company, $custom_domain, $invoice, $approve ? 'approved' : 'rejected', ['extra_note' => $form_data['extra_note']]);

                    set_alert('success', _l('updated_successfully', _l('perfex_saas_custom_domain')));
                    return redirect(admin_url(PERFEX_SAAS_ROUTE_NAME . '/companies/edit/' . $_id));
                }

                // Log error
                log_message('error', _l('perfex_saas_error_completing_action') . ':' . ($this->db->error() ?? ''));

                throw new \Exception(_l('perfex_saas_error_completing_action'), 1);
            } catch (\Throwable $th) {

                set_alert('danger', $th->getMessage());
                return perfex_saas_redirect_back();
            }
        }
    }

    /**
     * Delete a company.
     *
     * @param string $id The ID of the company to delete
     * @return mixed Result of the deletion
     */
    function delete($id)
    {
        if (!staff_can('delete', 'perfex_saas_companies')) {
            return access_denied('perfex_saas_companies');
        }

        $id = (int) $id;
        $removed = $this->perfex_saas_model->delete_company($id);
        if ($removed)
            set_alert('success', _l('deleted', _l('perfex_saas_company')) . ($removed !== true ? ' ' . _l('perfex_saas_with_error') . ': ' . $removed : ''));
        else
            set_alert('danger', _l('perfex_saas_error_completing_action') . (is_string($removed) ? $removed : ''));

        return redirect(admin_url(PERFEX_SAAS_ROUTE_NAME . '/companies'));
    }

    /**
     * Update DSN of a company
     *
     * @return void
     */
    public function update_dsn()
    {
        if (!staff_can('edit', 'perfex_saas_companies')) {
            return access_denied('perfex_saas_companies');
        }

        try {
            $id = (int) $this->input->post('company_id', true);
            $dsn = $this->input->post('db_pools', false);

            if (empty($id) || empty($dsn))
                throw new \Exception(_l('perfex_saas_error_completing_action'), 1);

            $data = ['id' => $id];

            // Validate the provided custom db credentails
            $validation = perfex_saas_is_valid_dsn($dsn);
            $dsn_string = perfex_saas_dsn_to_string($dsn);

            if ($validation !== true) {
                // str replace was done to ensure text wrapping in notifications alert
                throw new \Exception($validation . ' using dsn: ' . str_ireplace(';', "; ", $dsn_string), 1);
            }

            // Add the credential to dsn
            $data['dsn'] = $dsn_string;

            // Get the client invoice
            $company = $this->perfex_saas_model->companies($id);
            $invoice = $this->perfex_saas_model->get_company_invoice($company->clientid);

            //Update
            $_id = $this->perfex_saas_model->create_or_update_company($data, $invoice);
            if (!$_id)
                throw new \Exception(_l('perfex_saas_error_completing_action'), 1);

            echo json_encode([
                'status' => 'success',
                'message' => _l('updated_successfully', _l('perfex_saas_company'))
            ]);
            exit;
        } catch (\Throwable $th) {

            echo json_encode([
                'status' => 'danger',
                'message' => $th->getMessage()
            ]);
            exit;
        }
    }


    /**
     * Method to handle deploy service for a company instance (AJAX)
     *
     * @param string $company_id
     * @return void
     */
    public function deploy($company_id = '')
    {

        echo json_encode(perfex_saas_deployer($company_id));
        exit();
    }

    /**
     * Create a bridge for tenant admins to access the client portal and or other instance belonging to the same owner.
     *
     * This method allows tenant admins to access the client portal, providing a bridge
     * between the two roles. It generates a unique authentication code and redirects
     * the user to the client portal or target tenant url with the authentication code and redirect parameters.
     *
     * @return void
     */
    public function client_portal_bridge()
    {
        // Available to ALL logged-in tenant staff (not just super admins) so
        // any staffer can reach the SaaS Customer Success portal.
        if (perfex_saas_is_tenant()) {
            $tenant = perfex_saas_tenant();
            $clientid = $tenant->clientid;
            if ($clientid) {
                $auth_code = perfex_saas_generate_magic_auth_code($clientid);
                $redirect = $this->input->get('redirect', true);
                $target = $this->input->get('target', true);

                if ($auth_code) {

                    $query = 'billing/my_account/magic_auth?auth_code=' . urlencode($auth_code);

                    if ($target === 'tenant') {
                        return redirect($redirect . $query);
                    }

                    // Prepare data for the client portal frame
                    // URL-encode redirect & source_url to preserve special chars (e.g. ? in clients/?companies)
                    $url = perfex_saas_default_base_url($query . '&redirect=' . urlencode($redirect) . '&source_url=' . urlencode(current_url()));

                    // Carry the real tenant staff member across the hop. The
                    // portal signs everyone in as the client's primary contact,
                    // so without this a ticket opened there is attributed to the
                    // account owner regardless of who actually opened it.
                    $actor_token = perfex_saas_portal_actor_token();
                    if ($actor_token !== '') {
                        $url .= '&actor=' . urlencode($actor_token);
                    }

                    // When cross domain not allow, and tenant using custom domain, open in new tab instead.
                    $support_custom_domain_magic_login = perfex_saas_tenant_is_enabled('cross_domain_bridge');
                    if ($tenant->http_identification_type === PERFEX_SAAS_TENANT_MODE_DOMAIN && !$support_custom_domain_magic_login) {
                        $url = $url . '&cross_domain=1';
                        return redirect($url);
                    }

                    $data = [
                        'url' => $url,
                        // Support PIN card (pro_support "Tenant Access" gate) — only on
                        // the Pro Tickets / customer-success page (redirect=clients/pro_tickets),
                        // and only when the shared codebase ships the pro_support module.
                        'show_support_pin' => is_file(FCPATH . 'modules/pro_support/helpers/pro_support_helper.php')
                            && stripos((string) $redirect, 'pro_tickets') !== false,
                    ];
                    return $this->load->view("client_portal_framer", $data);
                }
                perfex_saas_redirect_back();
            }
        }
    }

    /**
     * Lightweight JSON endpoint polled by the tenant admin to keep the
     * "Support" unread badge + notifications live. Returns the count of unread
     * staff replies on this tenant's support tickets (read cross-DB from the
     * master Pro Tickets helpdesk).
     *
     * @return void
     */
    public function tenant_support_unread()
    {
        // Read-only poll: hand the MySQL session row lock back straight away so
        // this doesn't serialise the user's other in-flight AJAX calls.
        ccx_release_session_lock();

        header('Content-Type: application/json');
        header('Cache-Control: no-store, no-cache, must-revalidate');

        $count    = 0;
        $items    = [];
        $feedback = [];
        if (perfex_saas_is_tenant() && is_admin() && function_exists('perfex_saas_support_unread_count')) {
            $count = (int) perfex_saas_support_unread_count(true);
            if (function_exists('perfex_saas_support_unread_items')) {
                $items = perfex_saas_support_unread_items(6);
            }
            // Staff-closed tickets still waiting for a satisfaction rating —
            // drives the real-time "how did we do?" toast on tenant admin pages.
            if (function_exists('perfex_saas_support_feedback_pending')) {
                $feedback = perfex_saas_support_feedback_pending(3);
            }
        }

        $payload = json_encode(['count' => $count, 'items' => $items, 'feedback' => $feedback], JSON_INVALID_UTF8_SUBSTITUTE);
        echo $payload === false ? json_encode(['count' => $count, 'items' => [], 'feedback' => []]) : $payload;
    }

    /**
     * Smart Ticket capture endpoint for the tenant ADMIN panel (AJAX).
     *
     * Lives here (perfex_saas, always active on tenants) rather than in the
     * pro_tickets module, which may not be activated on a tenant's own CRM.
     * Creates a support ticket in the SaaS provider's (MASTER) helpdesk on
     * behalf of this tenant's client record, with the annotated screenshot
     * embedded inline. Writes cross-DB via perfex_saas_raw_query (bound params).
     *
     * NOTE: by design this bypasses the provider's core tickets_model pipeline
     * (SLA / auto-assign / email) — deliberate trade-off for a direct cross-DB
     * write from the tenant.
     *
     * @return void
     */
    public function tenant_smart_ticket_submit()
    {
        header('Content-Type: application/json; charset=utf-8');

        // The widget's UI strings live in the pro_tickets language file, which
        // isn't auto-registered when that module is inactive on the tenant.
        $lang_path = FCPATH . 'modules/pro_tickets/';
        if (is_file($lang_path . 'language/english/pro_tickets_lang.php')) {
            $this->lang->load('pro_tickets', 'english', false, true, $lang_path);
        }

        $fail = function ($msg) {
            echo json_encode(['success' => false, 'message' => $msg]);
            exit;
        };

        if (!$this->input->is_ajax_request()) {
            show_404();
        }
        if (!perfex_saas_is_tenant() || !is_staff_logged_in()) {
            $fail(_l('access_denied'));
        }

        $tenant   = function_exists('perfex_saas_tenant') ? perfex_saas_tenant() : null;
        $clientid = (int) ($tenant->clientid ?? 0);
        if (!$clientid) {
            $fail(_l('pro_tickets_smart_saas_no_client'));
        }

        $message    = trim((string) $this->input->post('message', false));
        $department = (int) $this->input->post('department');
        $priority   = (int) $this->input->post('priority');
        $subject    = trim((string) $this->input->post('subject'));
        $source_url = trim((string) $this->input->post('source_url'));

        if ($subject === '' && $message !== '') {
            $first   = trim(strtok(strip_tags($message), "\n"));
            $subject = mb_substr($first, 0, 60) . (mb_strlen($first) > 60 ? '…' : '');
        }
        if ($subject === '' || $message === '') {
            $fail(_l('pro_tickets_smart_err_issue'));
        }
        if (!$department) {
            $fail(_l('pro_tickets_smart_err_department'));
        }
        $subject = mb_substr($subject, 0, 191);

        $prefix = perfex_saas_master_db_prefix();

        // Attribute the ticket to the staff member who actually raised it, not
        // to the tenant's account owner. When that person already has a contact
        // record on the provider side (matched by e-mail — typically the case
        // for the tenant admin, who IS the primary contact) the ticket is linked
        // to it; otherwise it carries their own name + e-mail with contactid 0,
        // exactly how core represents a submitter without a portal account. That
        // also routes the provider's replies to the person who reported it.
        $actor     = perfex_saas_current_staff_actor();
        $contact   = null;
        $contactid = 0;
        $c_name    = '';
        $c_email   = '';

        if ($actor && $actor['email'] !== '') {
            $contact = perfex_saas_raw_query_row(
                "SELECT id, firstname, lastname, email FROM {$prefix}contacts WHERE userid = ? AND LOWER(email) = ? LIMIT 1",
                [], true, false, [$clientid, strtolower($actor['email'])]
            );
            $c_name  = $actor['name'] !== '' ? $actor['name'] : $actor['email'];
            $c_email = $actor['email'];
        }

        // No identifiable staff member (should not happen — the endpoint is
        // gated on a logged-in tenant staff) → fall back to the tenant's contact.
        if (!$contact && $c_email === '') {
            $contact = perfex_saas_raw_query_row(
                "SELECT id, firstname, lastname, email FROM {$prefix}contacts WHERE userid = ? AND is_primary = 1 LIMIT 1",
                [], true, false, [$clientid]
            );
            if (!$contact) {
                $contact = perfex_saas_raw_query_row(
                    "SELECT id, firstname, lastname, email FROM {$prefix}contacts WHERE userid = ? ORDER BY id ASC LIMIT 1",
                    [], true, false, [$clientid]
                );
            }
        }

        if ($contact) {
            $contactid = (int) $contact->id;
            $c_name    = trim($contact->firstname . ' ' . $contact->lastname);
            $c_email   = (string) $contact->email;
        }

        // Ticket body: context header + customer text. The screenshot is stored
        // as a real attachment (below) rather than inline — the customer views
        // this ticket through the client-portal single view, which runs the body
        // through HTMLPurifier and strips data: URIs, so an inline base64 image
        // would silently vanish.
        $body = '<p style="color:#64748b;font-size:12px;margin:0 0 6px;">' . html_escape(_l('pro_tickets_smart_reported_from_admin')) . '</p>';
        if ($source_url !== '') {
            $body .= '<p style="font-size:12px;margin:0 0 10px;"><strong>' . html_escape(_l('pro_tickets_smart_page')) . ':</strong> ' . html_escape($source_url) . '</p>';
        }
        $body .= '<div>' . nl2br(html_escape($message)) . '</div>';

        $ticketkey = app_generate_hash();
        $now       = date('Y-m-d H:i:s');

        $sql = "INSERT INTO {$prefix}tickets
                (userid, contactid, name, email, department, priority, status, ticketkey, subject, message, `date`, adminread, clientread)
                VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, 0, 1)";
        $params = [
            $clientid,
            $contactid,
            $c_name,
            $c_email,
            $department,
            $priority ?: 2,
            $ticketkey,
            $subject,
            $body,
            $now,
        ];

        perfex_saas_raw_query($sql, [], false, true, null, false, true, $params);

        // Authoritative success signal: read the row back by its unique key.
        $row       = perfex_saas_raw_query_row(
            "SELECT ticketid FROM {$prefix}tickets WHERE ticketkey = ? LIMIT 1",
            [], true, false, [$ticketkey]
        );
        $ticket_id = $row ? (int) $row->ticketid : 0;

        if (!$ticket_id) {
            $fail(_l('something_went_wrong'));
        }

        // Save the screenshot as a real ticket attachment on the MASTER install.
        // NB: do NOT use TICKET_ATTACHMENTS_FOLDER here — perfex_saas
        // my_constants.php redefines that constant per-tenant, so on a tenant
        // request it points inside uploads/tenants/<slug>/ where the master
        // helpdesk (which owns this ticket row) would never find the file.
        // The codebase/filesystem is shared, so write straight into the
        // provider's own ticket uploads dir.
        if (isset($_FILES['attachments']) && !empty($_FILES['attachments']['tmp_name'][0])) {
            $tmp = $_FILES['attachments']['tmp_name'][0];
            if (is_uploaded_file($tmp)) {
                $info = @getimagesize($tmp);
                if ($info && !empty($info['mime']) && strpos($info['mime'], 'image/') === 0) {
                    $ext = ($info['mime'] === 'image/png') ? 'png'
                        : (($info['mime'] === 'image/jpeg') ? 'jpg'
                        : (($info['mime'] === 'image/webp') ? 'webp' : 'img'));
                    $base  = defined('PERFEX_SAAS_UPLOAD_BASE_DIR') ? PERFEX_SAAS_UPLOAD_BASE_DIR : 'uploads/';
                    $dir   = FCPATH . $base . 'ticket_attachments/' . $ticket_id . '/';
                    $fname = 'smart-' . time() . '-' . mt_rand(1000, 9999) . '.' . $ext;

                    if (!is_dir($dir)) {
                        @mkdir($dir, 0755, true);
                        @fopen($dir . 'index.html', 'w');
                    }
                    if (is_dir($dir) && is_writable($dir) && @move_uploaded_file($tmp, $dir . $fname)) {
                        @chmod($dir . $fname, 0644);
                        perfex_saas_raw_query(
                            "INSERT INTO {$prefix}ticket_attachments (ticketid, file_name, filetype, dateadded) VALUES (?, ?, ?, ?)",
                            [], false, true, null, false, true,
                            [$ticket_id, $fname, $info['mime'], date('Y-m-d H:i:s')]
                        );
                    } else {
                        log_message('error', 'Tenant Smart Ticket: could not write screenshot to ' . $dir);
                    }
                }
            }
        }

        echo json_encode([
            'success'   => true,
            'ticket_id' => $ticket_id,
            'message'   => _l('pro_tickets_smart_saas_created'),
            'url'       => admin_url('billing/my_account?redirect=clients/pro_tickets/ticket/' . $ticket_id),
        ]);
        exit;
    }

    /**
     * Tenant admin → Pro AI Chat floating assistant API (route
     * admin/billing/ai_chat). Thin bridge like the Smart Ticket endpoint above:
     * all logic lives in modules/pro_ai_chat (shared codebase), which is NOT
     * required to be activated on the tenant — the helper is required by path
     * and handles its own guards, cross-DB access and JSON output.
     *
     * @return void
     */
    public function tenant_ai_chat()
    {
        $helper = FCPATH . 'modules/pro_ai_chat/helpers/pro_ai_chat_helper.php';
        if (!is_file($helper)) {
            show_404();
        }
        require_once $helper;

        pro_ai_chat_widget_api($this);
    }

    /**
     * Support PIN endpoint for the tenant ADMIN (route admin/billing/support_pin).
     *
     * The tenant super admin generates a short-lived 6-digit PIN on the
     * Billing & Support page and reads it out to the provider's support agent;
     * the agent redeems it on the master Pro Support console
     * (pro_support/verify_pin), which unlocks the "Tenant Access" edit button
     * there for one hour.
     *
     * Rows live in the MASTER database (<master>pro_support_pins — the same
     * table the pro_support module's model creates), written cross-DB via
     * perfex_saas_raw_query with bound params, like the Smart Ticket bridge.
     *
     * Actions (POST 'action'): 'status' (default) | 'generate'
     *
     * @return void
     */
    public function tenant_support_pin()
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');

        $fail = function ($msg) {
            echo json_encode(['success' => false, 'message' => $msg]);
            exit;
        };

        if (!$this->input->is_ajax_request()) {
            show_404();
        }
        if (!perfex_saas_is_tenant() || !is_admin()) {
            $fail(_l('access_denied'));
        }

        $slug = perfex_saas_tenant_slug();
        if (!$slug) {
            $fail('No tenant context.');
        }

        $prefix = perfex_saas_master_db_prefix();
        $table  = $prefix . 'pro_support_pins';

        // Same definition as Pro_support_model::ensure_schema() — either side
        // may run first, so both create the table idempotently.
        perfex_saas_raw_query(
            "CREATE TABLE IF NOT EXISTS `{$table}` (
                `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                `slug` VARCHAR(190) NOT NULL,
                `pin` VARCHAR(12) NOT NULL,
                `status` VARCHAR(20) NOT NULL DEFAULT 'active',
                `attempts` INT(11) NOT NULL DEFAULT 0,
                `created_by` INT(11) NOT NULL DEFAULT 0,
                `created_by_name` VARCHAR(190) NOT NULL DEFAULT '',
                `created_at` DATETIME NOT NULL,
                `expires_at` DATETIME NOT NULL,
                `verified_by` INT(11) DEFAULT NULL,
                `verified_by_name` VARCHAR(190) NOT NULL DEFAULT '',
                `verified_at` DATETIME DEFAULT NULL,
                `access_until` DATETIME DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `slug_status` (`slug`, `status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            [], false, false
        );

        $action = (string) $this->input->post('action') ?: 'status';

        if ($action === 'generate') {
            perfex_saas_raw_query(
                "UPDATE `{$table}` SET status = 'revoked' WHERE slug = ? AND status = 'active'",
                [], false, true, null, false, true, [$slug]
            );

            $pin = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            perfex_saas_raw_query(
                "INSERT INTO `{$table}`
                 (slug, pin, status, created_by, created_by_name, created_at, expires_at)
                 VALUES (?, ?, 'active', ?, ?, ?, ?)",
                [], false, true, null, false, true,
                [
                    $slug,
                    $pin,
                    (int) get_staff_user_id(),
                    get_staff_full_name(),
                    date('Y-m-d H:i:s'),
                    date('Y-m-d H:i:s', time() + 3600),
                ]
            );
        }

        $row = perfex_saas_raw_query_row(
            "SELECT * FROM `{$table}` WHERE slug = ? AND status = 'active' ORDER BY id DESC LIMIT 1",
            [], true, false, [$slug]
        );

        $valid   = $row && strtotime($row->expires_at) > time();
        $granted = $row && $row->verified_at && $row->access_until && strtotime($row->access_until) > time();

        echo json_encode([
            'success'          => true,
            'has_pin'          => (bool) $valid,
            'pin'              => $valid ? (string) $row->pin : '',
            'expires_in'       => $valid ? max(0, strtotime($row->expires_at) - time()) : 0,
            'verified'         => (bool) $granted,
            'verified_by_name' => $granted ? (string) $row->verified_by_name : '',
            'access_remaining' => $granted ? max(0, strtotime($row->access_until) - time()) : 0,
        ]);
        exit;
    }

    /**
     * TEMPORARY diagnostics page for the Support unread badge / real-time
     * notifications. Open at admin/billing/support_debug while logged in as the
     * tenant super admin. Remove once the feature is verified.
     *
     * @return void
     */
    public function tenant_support_debug()
    {
        if (!perfex_saas_is_tenant() || !is_admin()) {
            echo 'Not a tenant admin context.';
            return;
        }

        $diag = [];
        $diag['is_tenant']     = perfex_saas_is_tenant() ? 'yes' : 'no';
        $diag['is_admin']      = is_admin() ? 'yes' : 'no';

        $tenant = function_exists('perfex_saas_tenant') ? perfex_saas_tenant() : null;
        $clientid = (int) ($tenant->clientid ?? 0);
        $diag['tenant_slug']   = $tenant->slug ?? '(none)';
        $diag['clientid']      = $clientid ?: '(none)';

        $prefix = perfex_saas_master_db_prefix();
        $dsn    = perfex_saas_master_dsn();
        $diag['master_prefix'] = $prefix;
        $diag['master_db']     = $dsn['dbname'] ?? '(unknown)';

        // Ticket-based unread count (the current badge metric)
        $ticketCount = 0;
        $replyCount  = 0;
        $err = null;
        try {
            if ($clientid) {
                $row = perfex_saas_raw_query_row(
                    "SELECT COUNT(*) AS c FROM {$prefix}tickets WHERE userid = ? AND clientread = 0",
                    [], true, false, [$clientid]
                );
                $ticketCount = $row ? (int) $row->c : 0;

                // Reply-based unread count (staff replies newer than the client's last reply)
                $row2 = perfex_saas_raw_query_row(
                    "SELECT COUNT(*) AS c
                       FROM {$prefix}ticket_replies r
                       JOIN {$prefix}tickets t ON t.ticketid = r.ticketid
                      WHERE t.userid = ? AND t.clientread = 0 AND r.admin IS NOT NULL
                        AND r.date > COALESCE((SELECT MAX(r2.date) FROM {$prefix}ticket_replies r2 WHERE r2.ticketid = t.ticketid AND r2.admin IS NULL), t.date)",
                    [], true, false, [$clientid]
                );
                $replyCount = $row2 ? (int) $row2->c : 0;
            }
        } catch (\Throwable $e) {
            $err = $e->getMessage();
        }
        $diag['unread_ticket_count'] = $ticketCount;
        $diag['unread_reply_count']  = $replyCount;
        $diag['query_error']         = $err ?: '(none)';

        // Helper output
        $helperCount = function_exists('perfex_saas_support_unread_count') ? perfex_saas_support_unread_count(true) : 'fn missing';
        $items       = function_exists('perfex_saas_support_unread_items') ? perfex_saas_support_unread_items(6) : [];
        $diag['helper_count'] = $helperCount;
        $diag['items_returned'] = count($items);

        // json_encode sanity (the thing that can break the injected JS)
        $json = json_encode(array_values($items), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        $diag['json_encode_ok']    = $json === false ? 'FALSE (would break JS)' : 'ok';
        $diag['json_last_error']   = json_last_error_msg();
        $diag['endpoint_url']      = admin_url('billing/support_unread');

        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><meta charset="utf-8"><title>Support badge debug</title>';
        echo '<style>body{font:14px/1.5 -apple-system,Segoe UI,Roboto,sans-serif;max-width:900px;margin:30px auto;padding:0 16px;color:#0f172a}h1{font-size:20px}table{border-collapse:collapse;width:100%;margin:16px 0}td,th{border:1px solid #e2e8f0;padding:8px 10px;text-align:left;vertical-align:top}th{background:#f8fafc;width:230px}pre{background:#0f172a;color:#e2e8f0;padding:14px;border-radius:8px;overflow:auto;font-size:12px}.ok{color:#16a34a;font-weight:700}.bad{color:#dc2626;font-weight:700}</style>';
        echo '<h1>Support badge / real-time debug</h1>';
        echo '<p>Temporary diagnostics. Delete <code>tenant_support_debug()</code> + its route once verified.</p>';
        echo '<p style="background:#fffbeb;border:1px solid #fde68a;padding:10px 12px;border-radius:8px">'
            . '<b>To test real-time:</b> open any normal admin page (e.g. the Dashboard), open the browser console, and watch for <code>[saas-support]</code> logs. '
            . 'You can force a check by running <code>__saasSupportPoll()</code> and inspect state with <code>__saasSupportState()</code>. '
            . 'Then post a staff reply and switch back to the tab — a toast should appear within ~15s (or instantly on tab focus).</p>';
        echo '<table>';
        foreach ($diag as $k => $v) {
            $val = is_scalar($v) ? (string) $v : json_encode($v);
            $cls = '';
            if ($k === 'json_encode_ok') { $cls = $v === 'ok' ? 'ok' : 'bad'; }
            if ($k === 'query_error')    { $cls = $v === '(none)' ? 'ok' : 'bad'; }
            echo '<tr><th>' . htmlspecialchars($k) . '</th><td class="' . $cls . '">' . htmlspecialchars($val) . '</td></tr>';
        }
        echo '</table>';

        echo '<h3>Items (what the toasts would show)</h3>';
        echo '<pre>' . htmlspecialchars(json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)) . '</pre>';

        echo '<h3>Desktop notification test</h3>';
        echo '<p>Tests the browser/OS path directly (independent of the poll). Permission: <b id="perm">…</b></p>';
        echo '<button onclick="reqPerm()">Request permission</button> <button onclick="testNotif()">Send test desktop notification</button><pre id="nout">—</pre>';
        echo '<script>'
            . 'function upd(){document.getElementById("perm").textContent=(window.Notification?Notification.permission:"unsupported");}'
            . 'upd();'
            . 'function reqPerm(){if(!window.Notification){document.getElementById("nout").textContent="Notification API unsupported";return;}Notification.requestPermission().then(function(p){document.getElementById("nout").textContent="permission = "+p;upd();});}'
            . 'function testNotif(){var o=document.getElementById("nout");if(!window.Notification){o.textContent="Notification API unsupported";return;}if(Notification.permission!=="granted"){o.textContent="Not granted ("+Notification.permission+") — click Request permission first, and check macOS System Settings ▸ Notifications ▸ Google Chrome.";return;}try{var n=new Notification("Test desktop notification",{body:"If you can see this, desktop notifications work."});n.onclick=function(){window.focus();n.close();};o.textContent="Fired. If nothing appeared, it is blocked at the OS level (macOS System Settings ▸ Notifications ▸ Google Chrome, or Focus/Do-Not-Disturb).";}catch(e){o.textContent="new Notification() threw: "+e;}}'
            . '</script>';

        echo '<h3>Live endpoint test</h3>';
        echo '<button onclick="t()">Fetch ' . htmlspecialchars(admin_url('billing/support_unread')) . '</button><pre id="out">click to test…</pre>';
        echo '<script>function t(){fetch("' . admin_url('billing/support_unread') . '",{headers:{"X-Requested-With":"XMLHttpRequest"},credentials:"same-origin",cache:"no-store"}).then(function(r){return r.text().then(function(b){document.getElementById("out").textContent="HTTP "+r.status+" ct:"+r.headers.get("content-type")+"\n\n"+b;});}).catch(function(e){document.getElementById("out").textContent="FETCH ERROR: "+e;});}</script>';
    }

    /**
     * Get the status of modules in the tenant's database.
     *
     * @param object $company
     * @return array [system_name => active (d/1)]
     */
    private function get_tenant_modules_status($company)
    {
        try {
            // Get invoice to determine DSN
            $invoice = $this->perfex_saas_model->get_company_invoice($company->clientid);
            $dsn = perfex_saas_get_company_dsn($company, $invoice);

            // Connect and query
            $tenant_dbprefix = perfex_saas_tenant_db_prefix($company->slug);
            $table = $tenant_dbprefix . 'modules';

            // Check if table exists first to avoid errors on fresh/broken tenants
            $check_table = perfex_saas_raw_query("SHOW TABLES LIKE '$table'", $dsn, true);
            if (empty($check_table))
                return [];

            $rows = perfex_saas_raw_query("SELECT module_name, active FROM $table", $dsn, true);

            $status_map = [];
            foreach ($rows as $row) {
                $status_map[$row->module_name] = $row->active;
            }
            return $status_map;

        } catch (\Throwable $e) {
            log_message('error', 'Failed to fetch tenant module status: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Ajax endpoint to update a module's status in the tenant's database.
     */
    public function update_tenant_module_status()
    {
        $this->load->model('perfex_saas_model');

        if (!staff_can('edit', 'perfex_saas_companies')) {
            access_denied('perfex_saas_companies');
        }

        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $company_id = $this->input->post('company_id');
        $module_name = $this->input->post('module_name');
        $status = (int) $this->input->post('status'); // 1 for active, 0 for inactive

        try {
            $company = $this->perfex_saas_model->companies($company_id);
            if (!$company) {
                throw new \Exception("Company not found");
            }

            // 1. Update Company Metadata (admin_approved_modules / admin_disabled_modules)
            $metadata = (object) $company->metadata;
            $admin_approved_modules = isset($metadata->admin_approved_modules) ? (array) $metadata->admin_approved_modules : [];
            $admin_disabled_modules = isset($metadata->admin_disabled_modules) ? (array) $metadata->admin_disabled_modules : [];
            // The customer-facing "disabled modules" list. perfex_saas_tenant_modules()
            // subtracts it *after* merging the approved list, so a module sitting in here
            // can never be activated no matter what the admin approves.
            $disabled_modules = isset($metadata->disabled_modules) ? (array) $metadata->disabled_modules : [];

            if ($status === 1) {
                // Activate: Add to approved, remove from disabled
                if (!in_array($module_name, $admin_approved_modules)) {
                    $admin_approved_modules[] = $module_name;
                }
                $admin_disabled_modules = array_diff($admin_disabled_modules, [$module_name]);
                $disabled_modules = array_diff($disabled_modules, [$module_name]);
            } else {
                // Deactivate: Add to disabled, remove from approved
                if (!in_array($module_name, $admin_disabled_modules)) {
                    $admin_disabled_modules[] = $module_name;
                }
                $admin_approved_modules = array_diff($admin_approved_modules, [$module_name]);
            }

            // Clean up arrays
            // array_values: array_diff/array_filter keep the original keys, and a gapped
            // array json_encodes to an object rather than a list.
            $metadata->admin_approved_modules = array_values(array_unique(array_filter($admin_approved_modules)));
            $metadata->admin_disabled_modules = array_values(array_unique(array_filter($admin_disabled_modules)));
            $metadata->disabled_modules = array_values(array_unique(array_filter($disabled_modules)));

            // Save metadata
            $update_data = [
                'id' => $company_id,
                'metadata' => json_encode($metadata)
            ];
            // We use create_or_update_company to save. Note: this might trigger other hooks, but it's the standard way.
            // However, create_or_update_company requires 'clientid' and other fields if we use it directly as is.
            // Inspecting create_or_update_company: it seems to handle partial updates if ID is present?
            // Let's use the model's update method directly if possible to avoid validation overhead of full update, 
            // OR just use create_or_update_company with minimal data if it supports it.
            // Looking at the code for create_or_update_company (I assume standard logic), usually it updates. 
            // BUT, to be safe and avoid "required fields" errors from the model validation if any, 
            // I will use add_or_update method if available (seen in Cron model usage elsewhere: $this->perfex_saas_model->add_or_update)
            // or just manual update via DB if strictly metadata. 
            // Let's check `perfex_saas_model` usage in `Perfex_saas_cron_model.php`...
            // line 313: $this->perfex_saas_model->add_or_update('companies', ['id' => $company->id, 'metadata' => json_encode($metadata)]);
            // This suggests `add_or_update` is the correct low-level method.

            $this->perfex_saas_model->add_or_update('companies', $update_data);

            // Reload the company object to ensure we have the FRESH metadata for the impersonation context
            $company = $this->perfex_saas_model->companies($company_id);

            // Attach package_invoice to the company object so perfex_saas_tenant_modules()
            // can correctly compute the full list of allowed modules (package + admin approved).
            // Without this, the impersonation context only sees admin_approved_modules from metadata,
            // causing all package-assigned modules to be deactivated.
            $package_invoice = $this->perfex_saas_model->get_company_invoice($company->clientid);
            if ($package_invoice) {
                $company->package_invoice = $package_invoice;
            }

            // 2. Trigger Installation/Activation via Impersonation
            // This ensures install.php runs and DB is updated correctly.
            $debug_output = "";
            $applied = false;
            perfex_saas_impersonate_instance($company, function () use ($module_name, $status, &$debug_output, &$applied) {

                $CI = &get_instance();
                $table = db_prefix() . 'modules';

                // Pre-check: App_modules::activate() caches the tblmodules rows of the
                // instance it was constructed against — the master here — so it skips its
                // own INSERT and the "active = 1" UPDATE then matches no row in the tenant.
                // Seed the row ourselves, with the version from the module headers so the
                // tenant's own cron does not immediately see a database upgrade as due.
                if ($status === 1) {
                    $exists = $CI->db->where('module_name', $module_name)->count_all_results($table);
                    if ($exists == 0) {
                        $module = $CI->app_modules->get($module_name);
                        $CI->db->insert($table, [
                            'module_name' => $module_name,
                            'installed_version' => $module['headers']['version'] ?? '0.0.0',
                            'active' => 0
                        ]);
                    }
                }

                // Compile output to debug
                ob_start();
                perfex_saas_setup_modules_for_tenant(true);
                $debug_output = ob_get_clean();

                // perfex_saas_setup_modules_for_tenant() swallows a failing activation hook
                // (module install.php) per module, so read the result back instead of
                // reporting a success the tenant database does not agree with.
                $row = $CI->db->where('module_name', $module_name)->get($table)->row();

                // Recover from the helper's failed-upgrade path: it deletes the module
                // row and calls activate() again, but App_modules caches tblmodules from
                // the instance it was constructed against (the master, here), so that
                // activate() skips its INSERT and its "active = 1" UPDATE matches nothing.
                // The row is then simply gone.
                if ($status === 1 && !$row) {
                    $module = $CI->app_modules->get($module_name);
                    $CI->db->insert($table, [
                        'module_name' => $module_name,
                        'installed_version' => $module['headers']['version'] ?? '0.0.0',
                        'active' => 1,
                    ]);
                    $debug_output .= '<br/>[check] module row had been dropped by the failed-upgrade path — restored it';
                    $row = $CI->db->where('module_name', $module_name)->get($table)->row();
                }

                $applied = ((int) ($row->active ?? 0) === 1) === ($status === 1);

                // Distinguish "the installer failed" from "the module was never in the
                // tenant's allowed list", which are fixed in completely different places.
                $allowed = perfex_saas_tenant_modules(perfex_saas_tenant(), false, false, false, false, true);
                $debug_output .= '<br/>[check] allowed for tenant: ' . (in_array($module_name, $allowed) ? 'yes' : 'NO')
                    . ' | tblmodules row: ' . ($row ? ('active=' . $row->active . ', version=' . $row->installed_version) : 'MISSING')
                    . '<br/>[check] resolved list: ' . implode(', ', $allowed);
            });

            if (!$applied) {
                // Pull the module's own line out of the verbose install log — that is
                // where the swallowed activation-hook error is reported.
                $reason = [];
                foreach (preg_split('/<br\s*\/?>|\r?\n/i', $debug_output) as $line) {
                    $line = trim(strip_tags($line));
                    if ($line !== '' && stripos($line, $module_name) !== false && stripos($line, 'error') !== false) {
                        $reason[] = $line;
                    }
                }
                $reason = implode(' | ', array_slice($reason, 0, 3));

                if ($reason === '' && stripos($debug_output, 'allowed for tenant: NO') !== false) {
                    $reason = $module_name . ' is not in the list of modules resolved for this tenant (package + approved), so it was never activated.';
                }

                log_message('error', "Remote Module Activation: $module_name not applied for {$company->slug}. Debug: " . strip_tags($debug_output));

                echo json_encode([
                    'success' => false,
                    'error' => 'The tenant database still reports ' . $module_name . ' as ' . ($status === 1 ? 'inactive' : 'active') . '.'
                        . ($reason !== '' ? ' ' . $reason : ' No error was reported by the installer — see the browser console for the full output.'),
                    'debug' => $debug_output,
                ]);
                return;
            }

            echo json_encode(['success' => true, 'debug' => $debug_output]);

        } catch (\Throwable $e) {
            log_message('error', "Remote Module Activation Error: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Ajax endpoint to get the tenant superadmin credentials (email).
     */
    public function get_tenant_credentials($company_id)
    {
        if (!staff_can('edit', 'perfex_saas_companies')) {
            access_denied('perfex_saas_companies');
        }

        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        try {
            $this->load->model('perfex_saas_model');
            $company = $this->perfex_saas_model->companies($company_id);
            if (!$company) throw new \Exception("Company not found");

            $this->db->where('userid', $company->clientid);
            $this->db->where('is_primary', 1);
            $contact = $this->db->get(db_prefix() . 'contacts')->row();

            if ($contact) {
                echo json_encode(['success' => true, 'email' => $contact->email]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Primary contact not found']);
            }
        } catch (\Throwable $th) {
            echo json_encode(['success' => false, 'message' => $th->getMessage()]);
        }
    }

    /**
     * Ajax endpoint to set the superadmin credentials for a tenant.
     */
    public function set_password()
    {
        if (!staff_can('edit', 'perfex_saas_companies')) {
            access_denied('perfex_saas_companies');
        }

        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $company_id = $this->input->post('company_id');
        $password = $this->input->post('password', false);
        $email = $this->input->post('email', true);

        if (empty($company_id) || empty($email)) {
            echo json_encode(['success' => false, 'message' => _l('perfex_saas_error_completing_action')]);
            return;
        }

        try {
            $this->load->model('perfex_saas_model');
            $company = $this->perfex_saas_model->companies($company_id);
            if (!$company) throw new \Exception("Company not found");

            // Prepare update payload
            $update_data = ['email' => $email];
            if (!empty($password)) {
                $update_data['password'] = app_hash_password($password);
            }

            // Update master CRM primary contact
            $this->db->where('userid', $company->clientid);
            $this->db->where('is_primary', 1);
            $contact = $this->db->get(db_prefix() . 'contacts')->row();

            if ($contact) {
                $this->db->where('id', $contact->id);
                $this->db->update(db_prefix() . 'contacts', $update_data);
            }

            // Update tenant superadmin
            $invoice = $this->perfex_saas_model->get_company_invoice($company->clientid);
            $dsn = perfex_saas_get_company_dsn($company, $invoice);
            if ($dsn) {
                $tenant_dbprefix = perfex_saas_tenant_db_prefix($company->slug);
                $table = $tenant_dbprefix . "staff";
                
                $set_clause = "`email` = " . $this->db->escape($email);
                if (!empty($password)) {
                    $set_clause .= ", `password` = " . $this->db->escape($update_data['password']);
                }
                
                $query = "UPDATE `$table` SET $set_clause WHERE `admin` = '1' AND `active` = '1' ORDER BY `staffid` ASC LIMIT 1";
                perfex_saas_raw_query($query, $dsn, false, true);
            }

            echo json_encode(['success' => true, 'message' => 'Credentials updated successfully']);
        } catch (\Throwable $th) {
            echo json_encode(['success' => false, 'message' => $th->getMessage()]);
        }
    }
}