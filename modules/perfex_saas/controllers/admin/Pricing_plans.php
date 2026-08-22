<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once(__DIR__ . '/Pricing_plans_trait.php');

/**
 * Pricing Plans — a smart, bulk-oriented manager for SaaS packages organised by
 * plan group (HIMS / LIMS / CIMS / RIS ...) and by invoice recurring period.
 *
 * This screen is additive; the classic Packages screen/form remains untouched.
 */
class Pricing_plans extends AdminController
{
    use Pricing_plans_trait;

    /**
     * @inheritDoc
     */
    public function __construct()
    {
        parent::__construct();

        // invoices_model is needed by the safe-delete check; client_groups_model
        // is loaded lazily inside the trait helpers.
        $this->load->model('invoices_model');
    }

    /**
     * Main Pricing Plans manager: period tabs + group/plan matrix + bulk tools.
     *
     * @return void
     */
    public function index()
    {
        if (!staff_can('view', 'perfex_saas_packages')) {
            return access_denied('perfex_saas_packages');
        }

        $packages = $this->perfex_saas_model->packages();

        $customer_counts = [];
        $package_col = perfex_saas_column('packageid');
        $counts_query = $this->db->query("SELECT $package_col as packageid, COUNT(DISTINCT clientid) as customer_count FROM " . db_prefix() . "invoices WHERE $package_col IS NOT NULL GROUP BY $package_col");
        if ($counts_query) {
            foreach ($counts_query->result_array() as $c) {
                $customer_counts[$c['packageid']] = (int)$c['customer_count'];
            }
        }

        $data['title']           = _l('perfex_saas_pricing_plans');
        $data['customer_counts'] = $customer_counts;
        $data['matrix']          = $this->perfex_saas_model->packages_grouped_for_matrix($packages);
        $data['groups']          = $this->perfex_saas_model->pricing_plan_groups();
        $data['packages']        = $packages;
        $data['all_modules']     = $this->perfex_saas_model->modules();
        $data['default_modules'] = $this->perfex_saas_model->default_modules();
        $data['limit_keys']      = array_values(PERFEX_SAAS_LIMIT_FILTERS_TABLES_MAP);
        $data['period_presets']  = $this->pricing_plans_period_presets();

        $this->load->view('pricing_plans/manage', $data);
    }

    /**
     * Internal "modern material" read-only overview of all plans, organised by
     * group and billing period, with Excel / Print / PDF export. Not public.
     *
     * @return void
     */
    public function overview()
    {
        if (!staff_can('view', 'perfex_saas_packages')) {
            return access_denied('perfex_saas_packages');
        }

        $packages = $this->perfex_saas_model->packages();
        $matrix   = $this->perfex_saas_model->packages_grouped_for_matrix($packages);

        $data['title']    = _l('perfex_saas_pricing_plans_overview');
        $data['matrix']   = $matrix;
        $data['rows']     = $this->pricing_plans_export_rows($matrix);
        $data['currency'] = get_base_currency();

        $this->load->view('pricing_plans/overview', $data);
    }

    /**
     * Export all plans as an Excel (.xls) file.
     *
     * @return void
     */
    public function export_excel()
    {
        if (!staff_can('view', 'perfex_saas_packages')) {
            return access_denied('perfex_saas_packages');
        }

        $matrix = $this->perfex_saas_model->packages_grouped_for_matrix();
        $rows   = $this->pricing_plans_export_rows($matrix);

        $filename = 'pricing-plans-' . date('Y-m-d') . '.xls';
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $this->load->view('pricing_plans/excel_report', [
            'rows'     => $rows,
            'currency' => get_base_currency(),
        ]);
    }

    /**
     * Export all plans as a downloadable PDF (reuses the app TCPDF infrastructure).
     *
     * @return void
     */
    public function export_pdf()
    {
        if (!staff_can('view', 'perfex_saas_packages')) {
            return access_denied('perfex_saas_packages');
        }

        $matrix = $this->perfex_saas_model->packages_grouped_for_matrix();
        $rows   = $this->pricing_plans_export_rows($matrix);

        $pdf = app_pdf(
            'pricing_plans_report',
            module_libs_path(PERFEX_SAAS_MODULE_NAME, 'pdf/Pricing_plans_pdf'),
            ['rows' => $rows, 'title' => _l('perfex_saas_pricing_plans')]
        );

        $pdf->Output('pricing-plans-' . date('Y-m-d') . '.pdf', 'D');
    }

    /**
     * Create or rename a plan group (AJAX). Synced with core customer groups.
     *
     * @return void
     */
    public function save_group()
    {
        if (!staff_can('create', 'perfex_saas_packages') && !staff_can('edit', 'perfex_saas_packages')) {
            return access_denied('perfex_saas_packages');
        }

        $id   = $this->input->post('id', true);
        $name = $this->input->post('name', true);

        echo json_encode($this->pricing_plans_save_group($id, $name));
        exit;
    }

    /**
     * Delete a plan group (AJAX). Also removes the matching customer group.
     *
     * @return void
     */
    public function delete_group()
    {
        if (!staff_can('delete', 'perfex_saas_packages')) {
            return access_denied('perfex_saas_packages');
        }

        $id = (int)$this->input->post('id', true);

        echo json_encode($this->pricing_plans_delete_group($id));
        exit;
    }

    /**
     * Assign a single plan to a group (AJAX), used by the per-plan group selector.
     *
     * @return void
     */
    public function assign_group()
    {
        if (!staff_can('edit', 'perfex_saas_packages')) {
            return access_denied('perfex_saas_packages');
        }

        $package_id = (int)$this->input->post('package_id', true);
        $group_id   = $this->input->post('group_id', true);

        $ok = $this->perfex_saas_model->set_package_plan_group($package_id, $group_id);

        echo json_encode([
            'success' => (bool)$ok,
            'message' => $ok ? _l('updated_successfully', _l('perfex_saas_pricing_plans_group')) : _l('perfex_saas_empty_data'),
        ]);
        exit;
    }

    /**
     * Save inline matrix edits (price + active status) for many plans at once.
     *
     * @return void
     */
    public function bulk_save()
    {
        if (!staff_can('edit', 'perfex_saas_packages')) {
            return access_denied('perfex_saas_packages');
        }

        if ($this->input->post()) {
            $count = $this->pricing_plans_bulk_save($this->input->post('plans', true));
            set_alert('success', _l('perfex_saas_pricing_plans_bulk_done', $count));
        }

        return redirect(admin_url(PERFEX_SAAS_ROUTE_NAME . '/pricing_plans'));
    }

    /**
     * Run a bulk action over the selected plans.
     *
     * @return void
     */
    public function bulk_action()
    {
        if (!staff_can('edit', 'perfex_saas_packages')) {
            return access_denied('perfex_saas_packages');
        }

        if ($this->input->post()) {
            $action = $this->input->post('bulk_action', true);
            $ids    = $this->input->post('selected', true);
            $params = [
                'price'   => $this->input->post('price', true),
                'percent' => $this->input->post('percent', true),
                'group'   => $this->input->post('group', true),
            ];

            $response = $this->pricing_plans_bulk_action($action, $ids, $params);
            set_alert($response['status'], $response['message']);
        }

        return redirect(admin_url(PERFEX_SAAS_ROUTE_NAME . '/pricing_plans'));
    }

    /**
     * Bulk update pricing, user/seat limits and modules across selected plans.
     *
     * @return void
     */
    public function bulk_update_fields()
    {
        if (!staff_can('edit', 'perfex_saas_packages')) {
            return access_denied('perfex_saas_packages');
        }

        if ($this->input->post()) {
            $ids    = $this->input->post('selected', true);
            $fields = [
                'set_price'                    => $this->input->post('set_price', true),
                'price'                        => $this->input->post('price', true),
                'limitations'                  => $this->input->post('limitations', true),
                'set_modules'                  => $this->input->post('set_modules', true),
                'modules'                      => $this->input->post('modules', true),
                'set_disabled_default_modules' => $this->input->post('set_disabled_default_modules', true),
                'disabled_default_modules'     => $this->input->post('disabled_default_modules', true),
            ];

            $response = $this->pricing_plans_bulk_update_fields($ids, $fields);
            set_alert($response['status'], $response['message']);
        }

        return redirect(admin_url(PERFEX_SAAS_ROUTE_NAME . '/pricing_plans'));
    }

    /**
     * Auto-generate recurring-period variants from a base plan.
     *
     * @return void
     */
    public function generate_variants()
    {
        if (!staff_can('create', 'perfex_saas_packages')) {
            return access_denied('perfex_saas_packages');
        }

        if ($this->input->post()) {
            $base_id = (int)$this->input->post('base_id', true);
            $periods = $this->input->post('periods', true);
            $opts    = [
                'group_id'       => $this->input->post('group_id', true),
                'price_map'      => $this->input->post('price_map', true) ?: [],
                'multiplier_map' => $this->input->post('multiplier_map', true) ?: [],
            ];

            $response = $this->pricing_plans_generate_variants($base_id, $periods, $opts);
            set_alert($response['status'], $response['message']);
        }

        return redirect(admin_url(PERFEX_SAAS_ROUTE_NAME . '/pricing_plans'));
    }

    /**
     * Clone every plan in a source group into a target group / period.
     *
     * @return void
     */
    public function clone_group()
    {
        if (!staff_can('create', 'perfex_saas_packages')) {
            return access_denied('perfex_saas_packages');
        }

        if ($this->input->post()) {
            $response = $this->pricing_plans_clone_group(
                $this->input->post('source_group_id', true),
                $this->input->post('target_group_id', true),
                $this->input->post('target_period', true) ?: ''
            );
            set_alert($response['status'], $response['message']);
        }

        return redirect(admin_url(PERFEX_SAAS_ROUTE_NAME . '/pricing_plans'));
    }
}
