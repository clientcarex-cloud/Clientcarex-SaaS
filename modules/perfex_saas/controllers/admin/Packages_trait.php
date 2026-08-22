<?php

defined('BASEPATH') or exit('No direct script access allowed');

trait Packages_trait
{
    public function get_package_data_from_post_request()
    {
        $CI = &get_instance();

        // Validate request
        $CI->load->library('form_validation');
        $CI->form_validation->set_rules('name', _l('perfex_saas_name'), 'required');
        $CI->form_validation->set_rules('price', _l('perfex_saas_price'), 'required');
        if ($CI->form_validation->run() == false) {
            return;
        }

        $form_data = $CI->input->post(NULL, true);

        // Check boxes
        $form_data['is_private'] = $CI->input->post('is_private', true) ?? '0';
        $form_data['is_default'] = $CI->input->post('is_default', true) ?? '0';
        $form_data['status'] = $CI->input->post('status', true) ?? '0';

        return $form_data;
    }

    /**
     * Handle creating or editing of package.
     * This method is used since both create and package has very little variation in logic. DRY
     *
     * @param array $form_data
     * @param string $id Edit Package ID (optional)
     * @return array
     * @throws Exception Can throw DB validation/connection error and others
     */
    public function create_or_edit_package_from_data($form_data, $id = '')
    {
        $CI = &get_instance();

        $package = null;
        if (!empty($id)) {
            $package = $CI->perfex_saas_model->packages((int)$id);
        }

        $form_data['price'] = (float)$form_data['price'];
        $form_data['metadata'] = isset($form_data['metadata']) ? (array)$form_data['metadata'] : [];
        $form_data['modules'] = json_encode(isset($form_data['modules']) ? (array)$form_data['modules'] : []);
        $form_data['description'] = html_purify($form_data['description']);

        // Slug management
        $slug = isset($form_data['slug']) && !empty($form_data['slug']) ? $form_data['slug'] : $form_data['name'];
        $slug_delimiter = stripos($slug, '_') === false ? '-' : '_'; // Backward compact to support old packages already using underscore
        $slug = perfex_saas_generate_unique_slug($slug, 'packages', $id, 0, ['skip_table_compact' => true, 'max_length' => 150, 'delimiter' => $slug_delimiter]);
        $form_data['slug'] = $slug;

        // Domain check boxes
        if (!isset($form_data['metadata']['enable_subdomain']))
            $form_data['metadata']['enable_subdomain'] = '';
        if (!isset($form_data['metadata']['enable_custom_domain']))
            $form_data['metadata']['enable_custom_domain'] = '';
        if (!isset($form_data['metadata']['autoapprove_custom_domain']))
            $form_data['metadata']['autoapprove_custom_domain'] = '';
        if (!isset($form_data['metadata']['is_liftetime_deal']))
            $form_data['metadata']['is_liftetime_deal'] = '';

        $db_scheme = $form_data['db_scheme'];
        $_db_pools = [];

        try {

            // Ensure private package is not marked as default.
            if (isset($form_data['is_private']) && isset($form_data['is_default']) && $form_data['is_private'] == '1' && $form_data['is_default'] == '1')
                throw new \Exception(_l('perfex_saas_no_private_default_package'), 1);

            $pools = $form_data['db_pools'];
            // Emty the db_pool variable
            $form_data['db_pools'] = '';

            // Handle the provided database pool.
            if (!in_array($db_scheme, ['multitenancy', 'single'])) {

                $db_pools_string = [];
                $fit_pool_list = [];

                if (!empty($pools)) {

                    // Sort and filter the pools to ensure uniqueness
                    for ($i = 0; $i < count($pools['host']); $i++) {
                        if (!empty($pools['host'][$i]) && !empty($pools['user'][$i]) && !empty($pools['dbname'][$i])) {

                            $pool =  [
                                'host' => $pools['host'][$i],
                                'user' => $pools['user'][$i],
                                'password' => $pools['password'][$i],
                                'dbname' => $pools['dbname'][$i],
                            ];

                            $dsn_string = perfex_saas_dsn_to_string($pool);

                            if (!in_array($dsn_string, $db_pools_string)) {
                                $db_pools_string[] = $dsn_string;
                                $_db_pools[] = $pool;
                            }
                        }
                    }

                    // Loop through the unique pools and test each db credentials
                    for ($j = 0; $j < count($_db_pools); $j++) {

                        $pool = $_db_pools[$j];

                        //test the db connection
                        $valid = perfex_saas_is_valid_dsn($pool);
                        if ($valid !== true) {

                            throw new \Exception("Connection Error: $valid", 1);
                        }

                        $fit_pool_list[] = $pool;
                    }
                }

                // All fine, encrypt all provided dbs
                $form_data['db_pools'] = $CI->encryption->encrypt(json_encode($fit_pool_list));
            }

            $metadata = (array)$form_data['metadata'];
            if ($package)
                $metadata = array_merge((array)$package->metadata, $metadata);
            $form_data['metadata'] = $metadata;

            $form_data = hooks()->apply_filters('perfex_saas_before_package_update', $form_data);

            $form_data['metadata'] = json_encode($form_data['metadata']);


            // Create or update the package
            $_id = $CI->perfex_saas_model->add_or_update('packages', $form_data);
            if ($_id) {

                if ($form_data['is_default'] == '1')
                    $CI->perfex_saas_model->mark_package_as_default($_id);

                $single_pricing_mode = perfex_saas_is_single_package_mode();

                // Update package invoices to reflect payment modes
                if (!empty($id) && !empty($package)) {

                    // Get the latest
                    $package = $CI->perfex_saas_model->packages((int)$id);

                    $new_payment_modes = serialize($metadata['invoice']['allowed_payment_modes']);
                    $payment_modes = serialize($package->metadata->invoice->allowed_payment_modes);

                    // Update relevant invoice payment methods
                    $CI->invoices_model->db
                        ->where(perfex_saas_column('packageid'), $id)
                        ->where('allowed_payment_modes', $payment_modes) // We only want to update invoice which intact package payment modes
                        ->where("`status` != '" . Invoices_model::STATUS_CANCELLED . "'")
                        ->update('invoices', ['allowed_payment_modes' => $new_payment_modes]);

                    perfex_saas_trigger_cron_process(PERFEX_SAAS_CRON_PROCESS_PACKAGE, $_id);

                    hooks()->do_action('perfex_saas_after_package_update', $package);
                }

                $message = _l(empty($id) ? 'added_successfully' : 'updated_successfully', _l($single_pricing_mode ? 'perfex_saas_pricing' : 'perfex_saas_package'));
                $url = $single_pricing_mode ? uri_string() : admin_url(PERFEX_SAAS_ROUTE_NAME . '/packages/edit/' . $_id);
                return ['redirect_url' => $url, 'message' => $message, 'status' => 'success', 'id' => $_id];
            }
        } catch (\Exception $e) {

            $CI->session->set_flashdata('db_pools', $_db_pools);
            return ['message' => $e->getMessage(), 'status' => 'danger'];
        }

        return [];
    }

    /**
     * Delete or deactive a package when not deleteable.
     *
     * @param int $id
     * @return mixed Array or false
     */
    public function delete_or_deactivate_package($id)
    {
        $CI = &get_instance();

        if (!empty($id)) {

            // Check for invoices attached to the plan
            $CI->invoices_model->db->limit(1);
            $invoices = $CI->invoices_model->get('', [perfex_saas_column('packageid') => $id]);
            if (!empty($invoices)) {
                $CI->perfex_saas_model->add_or_update('packages', ['id' => $id, 'status' => '0']);
                return ['status' => 'danger', 'message' => _l('perfex_saas_can_not_delete_package_with_invoices')];
            }

            if ($CI->perfex_saas_model->delete('packages', $id)) {
                return ['status' => 'success', 'message' => _l('deleted', _l('perfex_saas_package'))];
            }
        }

        return false;
    }
}