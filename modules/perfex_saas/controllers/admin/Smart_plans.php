<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once(__DIR__ . '/Smart_plans_trait.php');
require_once(__DIR__ . '/../../libraries/Perfex_saas_xlsx.php');

/**
 * Smart Plans — bulk export/import of SaaS packages via Excel/CSV.
 *
 * Download every plan as a tidy spreadsheet, edit it offline, then upload it to
 * push the changes back. All heavy lifting lives in Smart_plans_trait; this
 * controller only wires HTTP to it. The classic Packages and Pricing Plans
 * screens are untouched.
 */
class Smart_plans extends AdminController
{
    use Smart_plans_trait;

    /**
     * @inheritDoc
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Landing page: export button, import upload form and the field guide.
     *
     * @return void
     */
    public function index()
    {
        if (!staff_can('view', 'perfex_saas_packages')) {
            return access_denied('perfex_saas_packages');
        }

        // Pick up an import summary handed over from import() via flashdata (PRG).
        $import_result = $this->session->flashdata('smart_plans_result') ?: null;

        $this->render_index($import_result);
    }

    /**
     * Export every plan as a styled .xlsx workbook.
     *
     * @return void
     */
    public function export()
    {
        if (!staff_can('view', 'perfex_saas_packages')) {
            return access_denied('perfex_saas_packages');
        }

        $export = $this->smart_plans_export_grid();

        $xlsx  = new Perfex_saas_xlsx();
        $bytes = $xlsx->build_grid($export['grid'], $export['opts']);

        $filename = 'smart-plans-' . date('Y-m-d') . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($bytes));
        header('Cache-Control: max-age=0');

        echo $bytes;
        exit;
    }

    /**
     * Export every plan as a plain CSV (a lighter alternative to .xlsx).
     *
     * @return void
     */
    public function export_csv()
    {
        if (!staff_can('view', 'perfex_saas_packages')) {
            return access_denied('perfex_saas_packages');
        }

        $export = $this->smart_plans_export_grid();

        $filename = 'smart-plans-' . date('Y-m-d') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $out = fopen('php://output', 'w');
        // UTF-8 BOM so Excel opens accented text correctly.
        fwrite($out, "\xEF\xBB\xBF");
        foreach ($export['grid'] as $row) {
            fputcsv($out, $row, ',', '"', '\\');
        }
        fclose($out);
        exit;
    }

    /**
     * Handle the uploaded spreadsheet and apply the changes.
     *
     * @return void
     */
    public function import()
    {
        if (!staff_can('edit', 'perfex_saas_packages')) {
            return access_denied('perfex_saas_packages');
        }

        $redirect = admin_url(PERFEX_SAAS_ROUTE_NAME . '/smart_plans');

        if (!$this->input->post()) {
            return redirect($redirect);
        }

        try {
            if (empty($_FILES['import_file']['name']) || !is_uploaded_file($_FILES['import_file']['tmp_name'] ?? '')) {
                throw new \Exception(_l('perfex_saas_smart_plans_err_no_file'));
            }

            if (!empty($_FILES['import_file']['error'])) {
                throw new \Exception(_l('perfex_saas_smart_plans_err_upload'));
            }

            $ext = strtolower(pathinfo($_FILES['import_file']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['xlsx', 'csv'], true)) {
                throw new \Exception(_l('perfex_saas_smart_plans_err_bad_type'));
            }

            $xlsx   = new Perfex_saas_xlsx();
            $matrix = $xlsx->read_matrix($_FILES['import_file']['tmp_name']);

            if (empty($matrix)) {
                throw new \Exception(_l('perfex_saas_smart_plans_err_empty'));
            }

            $result = $this->smart_plans_apply_import($matrix);

            if (!empty($result['ok'])) {
                // Hand the full summary to the redirected page (Post/Redirect/Get).
                $this->session->set_flashdata('smart_plans_result', $result);
                set_alert($result['errors'] > 0 ? 'warning' : 'success', $result['message']);
            } else {
                set_alert('danger', $result['message']);
            }
        } catch (\Throwable $e) {
            set_alert('danger', $e->getMessage());
        }

        return redirect($redirect);
    }

    /**
     * Real-time import: stream per-plan progress as newline-delimited JSON so the
     * browser can show a live progress bar, each plan's result and exactly what
     * changed (price, modules, limits, …) while the work is happening.
     *
     * Each line is one JSON object: {event, ...payload}. Events are
     * 'phase', 'start', 'module', 'progress', 'done' and 'fatal'.
     *
     * @return void
     */
    public function import_stream()
    {
        if (!staff_can('edit', 'perfex_saas_packages')) {
            return access_denied('perfex_saas_packages');
        }

        $this->stream_open();

        try {
            if (empty($_FILES['import_file']['name']) || !is_uploaded_file($_FILES['import_file']['tmp_name'] ?? '')) {
                throw new \Exception(_l('perfex_saas_smart_plans_err_no_file'));
            }
            if (!empty($_FILES['import_file']['error'])) {
                throw new \Exception(_l('perfex_saas_smart_plans_err_upload'));
            }

            $ext = strtolower(pathinfo($_FILES['import_file']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['xlsx', 'csv'], true)) {
                throw new \Exception(_l('perfex_saas_smart_plans_err_bad_type'));
            }

            $this->stream_send('phase', ['stage' => 'reading', 'message' => _l('perfex_saas_smart_plans_phase_reading')]);

            $xlsx   = new Perfex_saas_xlsx();
            $matrix = $xlsx->read_matrix($_FILES['import_file']['tmp_name']);

            if (empty($matrix)) {
                throw new \Exception(_l('perfex_saas_smart_plans_err_empty'));
            }

            $this->stream_send('phase', ['stage' => 'applying', 'message' => _l('perfex_saas_smart_plans_phase_applying')]);

            $emit = function ($event, $payload) {
                $this->stream_send($event, $payload);
            };

            $result = $this->smart_plans_apply_import($matrix, $emit);

            // smart_plans_apply_import only emits 'done' on the happy path; a pre-flight
            // failure (no ID row / no plan columns) returns ok=false without events.
            if (empty($result['ok'])) {
                $this->stream_send('fatal', ['message' => $result['message']]);
            }
        } catch (\Throwable $e) {
            $this->stream_send('fatal', ['message' => $e->getMessage()]);
        }

        exit;
    }

    /**
     * Prepare the response for line-by-line streaming: kill buffering/compression
     * so each flushed line reaches the browser immediately.
     *
     * @return void
     */
    private function stream_open()
    {
        @ini_set('zlib.output_compression', '0');
        @ini_set('output_buffering', '0');
        @ini_set('implicit_flush', '1');

        // Unwind any output buffers Perfex/CI started so nothing is held back.
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        header('Content-Type: application/x-ndjson; charset=utf-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('X-Accel-Buffering: no'); // disable nginx proxy buffering

        ob_implicit_flush(true);
    }

    /**
     * Emit one streamed event as a JSON line and flush it to the client.
     *
     * @param string $event
     * @param array  $payload
     * @return void
     */
    private function stream_send($event, array $payload = [])
    {
        echo json_encode(array_merge(['event' => $event], $payload)) . "\n";
        flush();
    }

    /**
     * Render the Smart Plans page, optionally with an import-result summary.
     *
     * @param array|null $import_result
     * @return void
     */
    private function render_index($import_result = null)
    {
        $data['title']         = _l('perfex_saas_smart_plans');
        $data['columns']       = $this->smart_plans_guide_columns();
        $data['plans_count']   = count($this->perfex_saas_model->packages());
        $data['import_result'] = $import_result;

        $this->load->view('smart_plans/manage', $data);
    }
}
