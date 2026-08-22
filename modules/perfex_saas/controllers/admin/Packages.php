<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once(__DIR__ . '/Packages_trait.php');

class Packages extends AdminController
{
    use Packages_trait;

    /**
     * @inheritDoc
     */
    public function __construct()
    {
        parent::__construct();

        // Load essential models and libraries
        $this->load->model('currencies_model');
        $this->load->model('invoices_model');
        $this->load->model('payment_modes_model');
    }

    /**
     * Display the list of packages
     *
     * @return void
     */
    public function index()
    {

        if (!staff_can('view', 'perfex_saas_packages')) {
            return access_denied('perfex_saas_packages');
        }

        $data['title'] = _l('perfex_saas_packages');
        $data['packages'] = $this->perfex_saas_model->packages();
        $this->load->view('packages/manage', $data);
    }

    public function pricing()
    {
        if (!staff_can('edit', 'perfex_saas_packages')) {
            return access_denied('perfex_saas_packages');
        }

        $id = '';

        $default_package = $this->perfex_saas_model->default_package();
        if (!$default_package) {

            $this->db->where('is_private !=', 1);
            $packages = $this->perfex_saas_model->packages();
            if (!empty($packages[0])) {
                $this->perfex_saas_model->mark_package_as_default($packages[0]->id);
                $default_package = $this->perfex_saas_model->default_package();
            }
        }

        if ($default_package) $id = $default_package->id;

        if ($this->input->post()) {
            // Handle package update
            $this->create_or_edit_package($id);
        }

        // Load package data and display edit form
        $data['title'] = _l('perfex_saas_packages');
        $data['staff']     = $this->staff_model->get('', ['active' => 1]);

        $this->load->model('taxes_model');
        $data['taxes'] = $this->taxes_model->get();
        $data['all_payment_modes'] = $this->payment_modes_model->get();

        if ($default_package)
            $data['package'] = $default_package;

        $this->load->view('packages/form', $data);
    }

    /**
     * Create a new package
     *
     * @return void
     */
    public function create()
    {

        if (!staff_can('create', 'perfex_saas_packages')) {
            return access_denied('perfex_saas_packages');
        }

        if ($this->input->post()) {
            // Handle package create
            $this->create_or_edit_package();
        }

        // Display package create form
        $data['title'] = _l('perfex_saas_packages');
        $data['staff']     = $this->staff_model->get('', ['active' => 1]);

        $this->load->model('taxes_model');
        $data['taxes'] = $this->taxes_model->get();
        $data['all_payment_modes'] = $this->payment_modes_model->get();

        $this->load->view('packages/form', $data);
    }

    /**
     * Edit an existing package
     *
     * @param string $id Package ID
     * @return void
     */
    public function edit($id)
    {
        if (!staff_can('edit', 'perfex_saas_packages')) {
            return access_denied('perfex_saas_packages');
        }

        if ($this->input->post()) {
            // Handle package update
            $this->create_or_edit_package($id);
        }

        // Load package data and display edit form
        $data['title'] = _l('perfex_saas_packages');
        $data['package'] = $this->perfex_saas_model->packages($id);
        $data['staff']     = $this->staff_model->get('', ['active' => 1]);

        $this->load->model('taxes_model');
        $data['taxes'] = $this->taxes_model->get();
        $data['all_payment_modes'] = $this->payment_modes_model->get();

        $this->load->view('packages/form', $data);
    }

    /**
     * Handle creating or editing of package.
     * This method is used since both create and package has very little variation in logic. DRY
     *
     * @param string $id Edit Package ID (optional)
     * @return void
     * @throws Exception Can throw DB validation/connection error and others
     */
    private function create_or_edit_package($id = '')
    {
        $form_data = $this->get_package_data_from_post_request();

        $response = $this->create_or_edit_package_from_data($form_data, $id);
        if (isset($response['message'])) {
            set_alert($response['status'] ?? 'danger', $response['message']);
        }
        if (isset($response['redirect_url']))
            return redirect($response['redirect_url']);

        return perfex_saas_redirect_back();
    }

    /**
     * Clone a package
     *
     * @param string $id Package ID
     * @return void
     */
    public function clone($id)
    {
        if (!staff_can('create', 'perfex_saas_packages')) {
            return access_denied('perfex_saas_packages');
        }

        if (!empty($id)) {
            $clone_id = $this->perfex_saas_model->clone('packages', (int)$id);

            hooks()->do_action('perfex_saas_after_package_clone', ['id' => $id, 'new_clone_id' => $clone_id]);
        }

        return redirect(admin_url(PERFEX_SAAS_ROUTE_NAME . '/packages'));
    }

    /**
     * Delete a package
     *
     * @return void
     */
    public function delete()
    {

        if (!staff_can('delete', 'perfex_saas_packages')) {
            return access_denied('perfex_saas_packages');
        }

        $id = (int)$this->input->post('id', true);

        $response = $this->delete_or_deactivate_package($id);

        if ($response)
            set_alert($response['status'], $response['message']);

        return redirect(admin_url(PERFEX_SAAS_ROUTE_NAME . '/packages'));
    }

    /**
     * Method to add a company/client to a saas package by admin
     *
     * @return void
     */
    public function add_user_to_package()
    {
        if ($this->input->post()) {
            // Perform edit and add new
            $this->load->library('form_validation');

            // Set validation rules
            $this->form_validation->set_rules('clientid', _l('perfex_saas_customer'), 'required');
            $this->form_validation->set_rules('packageid', _l('perfex_saas_package'), 'required');

            if ($this->form_validation->run() !== false) {
                $packageid = $this->input->post('packageid', true);
                $clientid = $this->input->post('clientid', true);

                try {
                    // Generate company invoice
                    $invoice = $this->perfex_saas_model->generate_company_invoice($clientid, $packageid);
                    if (isset($invoice->action_url))
                        return redirect($invoice->action_url);

                    if (!empty($invoice)) {
                        set_alert('success', _l('added_successfully', _l('perfex_saas_customer')));

                        if (empty($invoice->is_mock))
                            return redirect(admin_url('invoices/list_invoices/' . $invoice->id));
                    }
                } catch (\Throwable $th) {
                    set_alert('danger', $th->getMessage());
                }
            }
        }

        // Redirect back if no post data or validation failed
        return redirect(admin_url(PERFEX_SAAS_ROUTE_NAME . '/packages'));
    }

    public function test_db()
    {
        $pool = $this->input->post('db_pools');

        try {
            if (empty($pool)) {
                throw new \Exception(_l('perfex_saas_empty_data'), 1);
            }

            $pool =  [
                'host' => $pool['host'],
                'user' => $pool['user'],
                'password' => $pool['password'],
                'dbname' => $pool['dbname'],
            ];

            //test the db connection
            $valid = perfex_saas_is_valid_dsn($pool);
            if ($valid !== true) {

                throw new \Exception("Connection Error: $valid", 1);
            }

            echo json_encode([
                'status' => 'success',
                'message' => 'Valid'
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
}