<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Hr extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('hr_model');
    }

    /**
     * Per-menu permission gate. Each HR section is its own staff-permission
     * feature (hr_employees, hr_attendance, ...) so access is granted menu-wise
     * rather than one blanket "view" unlocking everything. See
     * hr_permission_features() in hr.php for the full map.
     */
    protected function guard($feature, $capability = 'view')
    {
        if (!has_permission($feature, '', $capability) && !is_admin()) {
            access_denied($feature);
        }
    }

    protected function guard_payroll($capability = 'view')
    {
        $this->guard('hr_payroll', $capability);
    }

    /* ---------------------------------------------------------- Dashboard */

    public function index()
    {
        $this->guard('hr', 'view');

        // Birthday wishes. Today's celebrant(s) are ALWAYS shown on the HR
        // dashboard — publicly (celebratory) when wishing is on, or privately
        // (HR-only note) when it is off. Notifications only go out when on.
        $data['birthday_wishes_enabled'] = hr_birthday_wishes_enabled();
        $data['birthdays_today']         = $this->hr_model->birthdays_today();
        if ($data['birthday_wishes_enabled']) {
            hr_dispatch_birthday_notifications();
        }

        // Pre-payday reminder: when the global pay date is within 3 days, show a
        // finance summary card here and (idempotently) notify payroll staff.
        $data['payroll_reminder'] = null;
        if (has_permission('hr_payroll', '', 'view') || is_admin()) {
            $pay_date   = hr_pay_date_for_month(hr_effective_pay_day(null), (int) date('n'), (int) date('Y'));
            $days_until = (int) floor((strtotime($pay_date) - strtotime(date('Y-m-d'))) / 86400);
            if ($days_until >= 0 && $days_until <= 3) {
                $data['payroll_reminder'] = array_merge(
                    $this->hr_model->payroll_reminder_summary(),
                    ['pay_date' => $pay_date, 'days_until' => $days_until]
                );
            }
            hr_dispatch_payroll_reminder();
        }

        $data['title']          = _l('hr_dashboard');
        $data['stats']          = $this->hr_model->dashboard_stats();
        $data['departments']    = $this->hr_model->department_headcount();
        $data['birthdays']      = $this->hr_model->birthdays_this_month();
        $data['recent_joiners'] = $this->hr_model->recent_joiners();
        $data['expiring_docs']  = $this->hr_model->get_expiring_documents((int) get_option('hr_doc_expiry_alert_days'));
        $data['pending_leaves'] = $this->hr_model->get_leave_requests(['r.status' => 'pending']);

        $this->load->view('hr/dashboard', $data);
    }

    /* ---------------------------------------------------------- Employees */

    public function employees()
    {
        $this->guard('hr_employees', 'view');

        if ($this->input->is_ajax_request()) {
            $this->app->get_table_data(module_views_path('hr', 'employees_table'));
        }

        // Dashboard card deep-links land here with a pre-applied filter.
        $card_filter = $this->input->get('filter');
        $data['active_filter'] = in_array($card_filter, ['new_joiners', 'probation'], true) ? $card_filter : '';

        $this->load->model('departments_model');
        $data['title']        = _l('hr_employees');
        $data['departments']  = $this->departments_model->get();
        $data['designations'] = $this->hr_model->get_designations(true);
        $this->load->model('roles_model');
        $data['roles']        = $this->roles_model->get();

        // Bulk ID-card printing: ID-card Smart PDF templates + the roster the
        // picker offers. Guarded so HR does not hard-depend on the Smart PDF
        // module (feature simply hides when it is absent / user lacks access).
        $data['idcard_templates'] = [];
        $data['idcard_employees'] = [];
        if ((has_permission('smart_pdf', '', 'generate') || is_admin())
            && $this->db->table_exists(db_prefix() . 'smart_pdf_templates')) {
            $this->db->select('id, name');
            $this->db->where('active', 1);
            $this->db->order_by('name', 'asc');
            $templates = $this->db->get(db_prefix() . 'smart_pdf_templates')->result_array();

            // Prefer the ID-card group; fall back to every template so the user
            // is never blocked if their card template is named differently.
            $groups = $this->group_smart_pdf_templates($templates);
            $data['idcard_templates'] = !empty($groups['id']['items']) ? $groups['id']['items'] : $templates;

            $dept_names = [];
            foreach ($data['departments'] as $d) { $dept_names[(int) $d['departmentid']] = $d['name']; }
            $desg_names = [];
            foreach ($this->hr_model->get_designations() as $dg) { $desg_names[(int) $dg['id']] = $dg['name']; }

            foreach ($this->hr_model->get_employees() as $emp) {
                $data['idcard_employees'][] = [
                    'staffid'     => (int) $emp['staffid'],
                    'name'        => trim($emp['firstname'] . ' ' . $emp['lastname']),
                    'code'        => isset($emp['employee_code']) ? (string) $emp['employee_code'] : '',
                    'department'  => isset($emp['department_id']) && isset($dept_names[(int) $emp['department_id']]) ? $dept_names[(int) $emp['department_id']] : '',
                    'designation' => isset($emp['designation_id']) && isset($desg_names[(int) $emp['designation_id']]) ? $desg_names[(int) $emp['designation_id']] : '',
                ];
            }
        }

        $this->load->view('hr/employees', $data);
    }

    public function sync_employees()
    {
        $this->guard('hr_employees', 'create');
        $created = $this->hr_model->sync_employees();
        set_alert('success', $created . ' employee profile(s) created from existing staff');
        redirect(admin_url('hr/employees'));
    }

    /**
     * Download every employee (active + inactive) as an Excel-compatible CSV.
     * The file round-trips: edit it / append rows and feed it back to
     * import_employees to bulk-update or bulk-create.
     */
    public function export_employees()
    {
        $this->guard('hr_employees', 'view');

        $columns = $this->hr_model->employee_export_columns();
        $rows    = $this->hr_model->export_employee_rows();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="employees-export-' . date('Y-m-d') . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        // UTF-8 BOM so Excel renders non-ASCII names correctly
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $columns, ',', '"', '\\');
        foreach ($rows as $row) {
            $line = [];
            foreach ($columns as $c) {
                $line[] = $row[$c];
            }
            fputcsv($out, $line, ',', '"', '\\');
        }
        fclose($out);
        exit;
    }

    /**
     * Blank one-row sample of the import format.
     */
    public function import_employees_sample()
    {
        $this->guard('hr_employees', 'create');

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="employees-import-sample.csv"');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $this->hr_model->employee_export_columns(), ',', '"', '\\');
        fputcsv($out, [
            '', 'Asha', 'Nair', 'asha.nair@example.com', '+91 98765 43210', '1',
            'EMP0101', 'Nursing', 'Staff Nurse', 'permanent',
            '2026-01-15', '2026-04-15', 'Main Branch', 'manager@example.com',
            '1994-06-20', 'female', 'O+', 'single', 'Raman Nair',
            'asha.personal@example.com', '', '12 Park Street', '12 Park Street',
            'Raman Nair', 'Father', '+91 91234 56789',
            '', '', 'HDFC Bank', 'MG Road', '50100012345678',
            'HDFC0000123', '', '', '25000', 'B.Sc Nursing',
            '', 'active',
        ]);
        fclose($out);
        exit;
    }

    /**
     * Bulk import page. GET shows the upload form; POST parses the uploaded
     * sheet and dry-runs it, handing the browser every parsed cell so the
     * whole file can be reviewed and corrected in an editable grid before a
     * single record is written. The actual write happens in
     * import_employees_apply() once the user is happy with the preview.
     */
    public function import_employees()
    {
        $this->guard('hr_employees', 'create');

        $data['title']     = 'Import Employees';
        $data['columns']   = $this->hr_model->employee_export_columns();
        $data['meta']      = $this->hr_model->import_column_meta();
        $data['datalists'] = $this->hr_model->import_datalists();
        $data['rows']      = [];
        $data['filename']  = '';

        // NB: checked via request method, not input->post() — CI strips the
        // CSRF token after verifying, so a POST carrying only the file would
        // otherwise look empty.
        if (strtolower($this->input->method()) === 'post') {
            $temp_copy = '';
            $path      = '';
            $name      = '';

            if (!empty($_FILES['import_file']['tmp_name'])) {
                $path = $_FILES['import_file']['tmp_name'];
                $name = (string) ($_FILES['import_file']['name'] ?? '');
            } elseif (($b64 = (string) $this->input->post('import_file_b64', false)) !== '') {
                // Fallback path. On some hosts the multipart file part never
                // reaches PHP at all (file_uploads off, or a proxy/WAF stripping
                // it) — $_POST arrives fully populated but $_FILES is empty. The
                // browser then re-sends the bytes as an ordinary form field and
                // we rebuild the file here.
                $path = $this->import_file_from_base64($b64, $error);
                if ($path === false) {
                    $path = '';
                    set_alert('danger', $error);
                } else {
                    $temp_copy = $path;
                    $name      = (string) $this->input->post('import_file_name');
                }
            }

            if ($path !== '') {
                $rows = $this->parse_import_file($path, $data['columns'], $error);
                if ($rows === false) {
                    set_alert('danger', $error);
                } else {
                    // Drop the blank lines here (they carry no meaning for the
                    // editor); everything else goes to the grid as-is.
                    $data['rows'] = array_values(array_filter(
                        $this->hr_model->analyze_import_rows($rows),
                        function ($r) { return empty($r['empty']); }
                    ));
                    $data['filename'] = $name;
                    if (empty($data['rows'])) {
                        set_alert('warning', 'The file has a header row but no data rows.');
                    }
                }
                if ($temp_copy !== '') {
                    @unlink($temp_copy);
                }
            } elseif (empty($error)) {
                if (($upload_error = $this->import_upload_error()) !== '') {
                    // A file WAS sent but PHP threw it away. Saying "choose a
                    // file" here sends the user hunting for a problem that isn't
                    // in their file.
                    set_alert('danger', $upload_error);
                } else {
                    set_alert('warning', 'Please choose an .xlsx or .csv file to import.');
                }
            }
        }

        $this->load->view('hr/import_employees', $data);
    }

    /**
     * Live re-validation for the preview grid. Takes the grid's current rows
     * (JSON in a normal form field, so CI's CSRF check still sees a POST) and
     * returns the same rows re-judged — verdict, notes and per-cell problems —
     * without writing anything. Order is preserved so the browser can map the
     * answer back onto its rows by index.
     */
    public function import_employees_validate()
    {
        $this->guard('hr_employees', 'create');
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        header('Content-Type: application/json');
        $csrf = [$this->security->get_csrf_token_name() => $this->security->get_csrf_hash()];
        $rows = $this->import_rows_from_post($error);

        if ($rows === false) {
            echo json_encode(array_merge(['success' => false, 'message' => $error], $csrf));

            return;
        }

        echo json_encode(array_merge([
            'success' => true,
            'rows'    => $this->hr_model->analyze_import_rows($rows),
        ], $csrf));
    }

    /**
     * Write the rows the user kept in the preview grid. Everything is
     * re-validated server-side first — the grid is a convenience, never the
     * authority — so an edited cell can't slip past the same checks the
     * preview showed.
     */
    public function import_employees_apply()
    {
        $this->guard('hr_employees', 'create');
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        header('Content-Type: application/json');
        $csrf = [$this->security->get_csrf_token_name() => $this->security->get_csrf_hash()];
        $rows = $this->import_rows_from_post($error);

        if ($rows === false) {
            echo json_encode(array_merge(['success' => false, 'message' => $error], $csrf));

            return;
        }
        if (empty($rows)) {
            echo json_encode(array_merge(['success' => false, 'message' => 'No rows selected for import.'], $csrf));

            return;
        }

        $summary = $this->hr_model->import_employees($rows, false);

        echo json_encode(array_merge(['success' => true, 'summary' => $summary], $csrf));
    }

    /**
     * Why the browser sent a file but PHP has no temp copy of it. Returns ''
     * when nothing was actually submitted (a genuinely empty field) so the
     * caller can keep showing its "choose a file" prompt.
     *
     * @return string
     */
    protected function import_upload_error()
    {
        $limits = ' (server limits: upload_max_filesize = ' . ini_get('upload_max_filesize')
            . ', post_max_size = ' . ini_get('post_max_size') . ')';

        // post_max_size overflow: PHP discards the whole body, so $_POST and
        // $_FILES are BOTH empty even though the request carried megabytes.
        // $_POST alone being populated means the body was parsed fine — that is
        // a stripped file part, not a size problem, so both must be empty here.
        if (empty($_FILES) && empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            return 'The upload was larger than this server accepts, so nothing reached the importer'
                . $limits . '. Split the sheet into smaller files, or ask your host to raise the limits.';
        }

        $err = $_FILES['import_file']['error'] ?? UPLOAD_ERR_NO_FILE;

        // The form fields arrived but the file part did not: uploads are off at
        // the PHP level, or something between the browser and PHP removed it.
        if (empty($_FILES) && !empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            return 'The form reached the server but the file itself was stripped from the request'
                . ' before PHP saw it (file_uploads = ' . (ini_get('file_uploads') ? 'On' : 'Off')
                . '). Your browser will retry automatically on the next attempt; if it keeps failing,'
                . ' this needs a hosting/server fix.';
        }

        if ($err === UPLOAD_ERR_NO_FILE) {
            return '';
        }

        switch ($err) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return 'The file is larger than this server accepts for uploads' . $limits
                    . '. Split the sheet into smaller files, or ask your host to raise the limits.';
            case UPLOAD_ERR_PARTIAL:
                return 'The upload was interrupted and only part of the file arrived. Please try again.';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'The server has no writable temporary folder for uploads (PHP upload_tmp_dir), '
                    . 'so the file could not be received. This needs a hosting/server fix.';
            case UPLOAD_ERR_CANT_WRITE:
                return 'The server could not write the uploaded file to disk. This needs a hosting/server fix.';
            case UPLOAD_ERR_EXTENSION:
                return 'A PHP extension on the server blocked this upload. This needs a hosting/server fix.';
            default:
                return 'The file could not be uploaded (PHP upload error code ' . (int) $err . ')' . $limits . '.';
        }
    }

    /**
     * Rebuild the uploaded sheet from the base64 copy the browser sent as a
     * plain form field, and return the path to a temp file the reader can open.
     * Used only when the real multipart upload never made it to PHP.
     *
     * @return string|false
     */
    protected function import_file_from_base64($b64, &$error)
    {
        $this->load->helper('files');

        // FileReader gives a data: URL; accept a bare base64 payload too.
        if (strncmp($b64, 'data:', 5) === 0 && ($comma = strpos($b64, ',')) !== false) {
            $b64 = substr($b64, $comma + 1);
        }

        $bytes = base64_decode(preg_replace('/\s+/', '', $b64), true);
        if ($bytes === false || $bytes === '') {
            $error = 'The file could not be read from your browser. Please pick the file again and retry.';

            return false;
        }

        $path = app_temp_dir() . 'hr_import_' . (int) get_staff_user_id() . '_' . uniqid('', true) . '.tmp';
        if (file_put_contents($path, $bytes) === false) {
            $error = 'The server could not write a temporary copy of the file (' . app_temp_dir()
                . ' is not writable). This needs a hosting/server fix.';

            return false;
        }

        return $path;
    }

    /**
     * Decode the grid's `rows` payload into clean import rows: known columns
     * only (plus the `_row` line number used in messages), strings only.
     *
     * @return array|false
     */
    protected function import_rows_from_post(&$error)
    {
        $payload = $this->input->post('rows', false);
        $decoded = is_string($payload) ? json_decode($payload, true) : null;

        if (!is_array($decoded)) {
            $error = 'The grid data could not be read. Please reload the page and try again.';

            return false;
        }
        if (count($decoded) > 2000) {
            $error = 'Too many rows (' . count($decoded) . '). Please import at most 2000 rows at a time.';

            return false;
        }

        $columns = $this->hr_model->employee_export_columns();
        $rows    = [];
        foreach ($decoded as $i => $r) {
            if (!is_array($r)) {
                continue;
            }
            $clean = ['_row' => isset($r['_row']) ? (int) $r['_row'] : ($i + 2), '_uid' => (string) ($r['_uid'] ?? ('r' . $i))];
            foreach ($columns as $c) {
                $v          = $r[$c] ?? '';
                $clean[$c]  = is_scalar($v) ? (string) $v : '';
            }
            $rows[] = $clean;
        }

        return $rows;
    }

    /**
     * Read the uploaded sheet (.xlsx straight from Excel, or .csv — the reader
     * sniffs the container, so extension does not matter) into assoc rows keyed
     * by the canonical columns. Strips the BOM, detects , vs ; CSV delimiters
     * (Excel regional exports) and matches headers by name so column order
     * does not matter.
     *
     * @return array|false rows, or false with $error set
     */
    protected function parse_import_file($path, $columns, &$error)
    {
        require_once __DIR__ . '/../libraries/Hr_xlsx_reader.php';

        try {
            $matrix = (new Hr_xlsx_reader())->read_matrix($path);
        } catch (Exception $e) {
            $error = 'The uploaded file could not be read: ' . $e->getMessage()
                . ' Please upload an .xlsx workbook or a .csv file.';

            return false;
        }

        if (empty($matrix)) {
            $error = 'The uploaded file is empty.';

            return false;
        }

        $header = array_shift($matrix);

        $map = []; // column name => position in file
        foreach ($header as $pos => $h) {
            $h = mb_strtolower(trim((string) $h));
            if (in_array($h, $columns, true)) {
                $map[$h] = $pos;
            }
        }
        if (!isset($map['email']) && !isset($map['staff_id']) && !isset($map['employee_code'])) {
            $error = 'Header row not recognised — the file must keep the exported column names (need at least one of: staff_id, email, employee_code). Download the sample file for the expected format.';

            return false;
        }

        $rows = [];
        foreach ($matrix as $cells) {
            $row = [];
            foreach ($columns as $c) {
                $row[$c] = isset($map[$c], $cells[$map[$c]]) ? (string) $cells[$map[$c]] : '';
            }
            $rows[] = $row;
        }

        if (empty($rows)) {
            $error = 'No data rows found below the header.';

            return false;
        }
        if (count($rows) > 2000) {
            $error = 'Too many rows (' . count($rows) . '). Please import at most 2000 rows per file.';

            return false;
        }

        return $rows;
    }

    public function employee($staff_id)
    {
        $this->guard('hr_employees', 'view');

        $staff_id = (int) $staff_id;
        $employee = $this->hr_model->get_employee($staff_id);
        if (!$employee) {
            blank_page('Employee not found', 'danger');
        }
        if (!$employee['id']) {
            $this->hr_model->ensure_employee($staff_id);
            $employee = $this->hr_model->get_employee($staff_id);
        }

        $this->load->model('departments_model');
        $year = (int) date('Y');

        $data['title']         = $employee['firstname'] . ' ' . $employee['lastname'];
        $data['employee']      = $employee;
        $data['departments']   = $this->departments_model->get();
        $data['designations']  = $this->hr_model->get_designations();
        $this->load->model('roles_model');
        $data['roles']         = $this->roles_model->get();
        $data['staff_members'] = $this->hr_model->get_employees();
        $data['documents']     = $this->hr_model->get_documents($staff_id);
        $data['family']        = $this->hr_model->get_family_members($staff_id);
        $data['nominees']      = $this->hr_model->get_nominees($staff_id);
        $data['nominee_share_totals'] = $this->hr_model->nominee_share_totals($staff_id);
        // Resolved required-document checklist for THIS employee (role/employee
        // override aware) so HR can see submission compliance on the docs tab.
        $ess_cfg               = hr_ess_config_for_staff($staff_id, $employee['role'] ?? null);
        $data['ess_required']  = $ess_cfg['required_documents'];
        $data['components']    = $this->hr_model->get_salary_components(true);
        $data['structure']     = $this->hr_model->get_salary_structure($staff_id);
        $data['salary_preview'] = (has_permission('hr_payroll', '', 'view') || is_admin())
            ? $this->hr_model->salary_preview($staff_id) : null;
        $data['global_pay_day'] = hr_effective_pay_day(null);
        $data['shift']         = $this->hr_model->get_staff_shift($staff_id);
        $data['shifts']        = $this->hr_model->get_shifts(true);
        $data['leave_types']   = $this->hr_model->get_leave_types(true);
        $data['allocations']   = $this->hr_model->get_leave_allocations($year);
        $data['carried']       = $this->hr_model->get_leave_carried($year);
        $data['leave_used']    = $this->hr_model->get_leave_used($year);
        $data['leaves']        = $this->hr_model->get_leave_requests(['r.staff_id' => $staff_id]);
        $data['appraisals']    = $this->hr_model->get_appraisals($staff_id);

        // KRA & KPI: goals and the indicators measuring them. Split by type so
        // the tab can render the two sections and cross-link KPIs to their KRA.
        $kra_kpi_all           = $this->hr_model->get_kra_kpi($staff_id);
        $data['kras']          = array_values(array_filter($kra_kpi_all, function ($r) { return $r['entry_type'] === 'kra'; }));
        $data['kpis']          = array_values(array_filter($kra_kpi_all, function ($r) { return $r['entry_type'] === 'kpi'; }));
        $data['kra_kpi_totals'] = $this->hr_model->kra_kpi_weightage_totals($staff_id);
        $data['can_payroll']   = has_permission('hr_payroll', '', 'view') || is_admin();

        // Memos / Onboarding context (only loaded when the viewer may see them).
        $data['can_memos']      = has_permission('hr_memos', '', 'view') || is_admin();
        $data['can_onboarding'] = has_permission('hr_onboarding', '', 'view') || is_admin();
        $data['memos']          = $data['can_memos'] ? $this->hr_model->get_staff_memos($staff_id) : [];
        $data['onboarding']     = $data['can_onboarding'] ? $this->hr_model->get_active_onboarding_for_staff($staff_id) : null;
        $data['onboarding_items'] = $data['onboarding'] ? $this->hr_model->get_onboarding_items($data['onboarding']['id']) : [];

        // Smart PDF integration: active templates for the "Print Document"
        // menu (ID cards, letters, certificates...). Queried directly and
        // guarded so HR does not hard-depend on the Smart PDF module.
        $data['smart_pdf_templates'] = [];
        $data['smart_pdf_groups']    = [];
        if ((has_permission('smart_pdf', '', 'generate') || is_admin())
            && $this->db->table_exists(db_prefix() . 'smart_pdf_templates')) {
            $this->db->select('id, name');
            $this->db->where('active', 1);
            $this->db->order_by('name', 'asc');
            $data['smart_pdf_templates'] = $this->db->get(db_prefix() . 'smart_pdf_templates')->result_array();
            $data['smart_pdf_groups']    = $this->group_smart_pdf_templates($data['smart_pdf_templates']);
        }

        // current month attendance summary
        $summary = $this->hr_model->get_attendance_summary(date('n'), date('Y'));
        $att     = [];
        foreach ($summary as $s) {
            if ($s['staff_id'] == $staff_id) {
                $att[$s['status']] = $s;
            }
        }
        $data['attendance_summary'] = $att;

        $this->load->view('hr/employee', $data);
    }

    /**
     * Group active Smart PDF templates into labelled categories for the
     * "Print Document" mega-menu. Category is inferred from the template name
     * (templates only store a name), with an "Other Documents" catch-all so
     * non-HR templates still appear. Returns ordered, non-empty groups.
     *
     * @param array $templates rows of ['id' => .., 'name' => ..]
     * @return array
     */
    protected function group_smart_pdf_templates($templates)
    {
        $groups = [
            'id'         => ['label' => 'ID Cards',        'icon' => 'fa-id-card-o',   'items' => []],
            'letter'     => ['label' => 'Letters',         'icon' => 'fa-envelope-o',  'items' => []],
            'certificate'=> ['label' => 'Certificates',    'icon' => 'fa-certificate', 'items' => []],
            'agreement'  => ['label' => 'Agreements',      'icon' => 'fa-handshake-o', 'items' => []],
            'policy'     => ['label' => 'Policies',        'icon' => 'fa-book',        'items' => []],
            'statutory'  => ['label' => 'Statutory',       'icon' => 'fa-university',  'items' => []],
            'other'      => ['label' => 'Other Documents', 'icon' => 'fa-file-o',      'items' => []],
        ];

        foreach ($templates as $t) {
            $n = strtolower($t['name']);

            if (strpos($n, 'id card') !== false) {
                $cat = 'id';
            } elseif (strpos($n, 'form no') !== false || strpos($n, 'form 16') !== false) {
                $cat = 'statutory';
            } elseif (strpos($n, 'policy') !== false || strpos($n, 'handbook') !== false) {
                $cat = 'policy';
            } elseif (strpos($n, 'agreement') !== false || strpos($n, 'nda') !== false
                || strpos($n, 'non-disclosure') !== false || strpos($n, 'bond') !== false
                || strpos($n, 'code of conduct') !== false) {
                $cat = 'agreement';
            } elseif ((strpos($n, 'certificate') !== false && strpos($n, 'salary') === false)
                || strpos($n, 'internship') !== false || strpos($n, 'appreciation') !== false) {
                $cat = 'certificate';
            } elseif (strpos($n, 'letter') !== false || strpos($n, 'offer') !== false
                || strpos($n, 'noc') !== false || strpos($n, 'no objection') !== false
                || strpos($n, 'settlement') !== false || strpos($n, 'verification') !== false
                || strpos($n, 'salary certificate') !== false || strpos($n, 'invitation') !== false) {
                $cat = 'letter';
            } else {
                $cat = 'other';
            }

            $groups[$cat]['items'][] = $t;
        }

        return array_filter($groups, function ($g) {
            return count($g['items']) > 0;
        });
    }

    public function save_employee($staff_id)
    {
        $this->guard('hr_employees', 'edit');
        if (!$this->input->post()) {
            show_404();
        }

        $fields = [
            'employee_code', 'device_empcode', 'department_id', 'designation_id', 'employment_type', 'date_of_joining',
            'probation_end', 'work_location', 'reporting_to', 'date_of_birth', 'gender', 'blood_group',
            'marital_status', 'father_name', 'personal_email', 'alt_phone', 'present_address',
            'permanent_address', 'emergency_contact_name', 'emergency_contact_relation',
            'emergency_contact_phone', 'national_id', 'aadhaar_number', 'pan_number', 'bank_name', 'bank_branch',
            'bank_account_no', 'bank_ifsc', 'pf_uan', 'esi_number', 'qualifications', 'notes', 'status',
        ];

        $update = [];
        foreach ($fields as $f) {
            if ($this->input->post($f) !== null) {
                $val = $this->input->post($f);
                $update[$f] = ($val === '') ? null : $val;
            }
        }
        if ($this->input->post('basic_salary') !== null && (has_permission('hr_payroll', '', 'edit') || is_admin())) {
            $update['basic_salary'] = (float) $this->input->post('basic_salary');
        }

        // Aadhaar is stored digits-only so printed forms and lookups stay
        // consistent. A number that is not a valid 12-digit UIDAI is still
        // saved (never block the whole profile) but the user is warned.
        $bad_aadhaar = false;
        if (array_key_exists('aadhaar_number', $update)) {
            $clean = hr_normalize_aadhaar($update['aadhaar_number']);
            $update['aadhaar_number'] = ($clean === '') ? null : $clean;
            $bad_aadhaar = !hr_aadhaar_is_valid($clean);
        }

        // Staff (permission) role lives on tblstaff, not the HR profile. Mapped
        // from the Designation but kept independently editable on the profile.
        if ($this->input->post('role') !== null) {
            $role = (int) $this->input->post('role') ?: null;
            $this->db->where('staffid', (int) $staff_id)->update(db_prefix() . 'staff', ['role' => $role]);
        }

        $this->hr_model->save_employee((int) $staff_id, $update);
        set_alert('success', _l('updated_successfully', _l('hr_employee')));
        if ($bad_aadhaar) {
            set_alert('warning', 'Aadhaar saved, but it is not a valid 12-digit number — please double-check it.');
        }
        // Return to the tab the form was submitted from (profile / bank).
        $back_tab = preg_replace('/[^a-z_]/', '', (string) $this->input->post('back_tab'));
        redirect(admin_url('hr/employee/' . (int) $staff_id . ($back_tab !== '' ? '?tab=' . $back_tab : '')));
    }

    /* ------------------------------------------------------------ KRA & KPI */

    /**
     * Create or update one KRA / KPI of an employee. Posted from the
     * "KRA & KPI" tab; id = 0 (or absent) inserts. The rich-text fields come
     * from TinyMCE, so they are purified rather than escaped.
     */
    public function save_kra_kpi($staff_id)
    {
        $this->guard('hr_employees', 'edit');
        if (!$this->input->post()) {
            show_404();
        }

        $staff_id = (int) $staff_id;
        if (!$this->hr_model->get_employee($staff_id)) {
            show_404();
        }

        $id    = (int) $this->input->post('id');
        $type  = $this->input->post('entry_type') === 'kpi' ? 'kpi' : 'kra';
        $title = trim((string) $this->input->post('title'));

        // Editing must stay within the employee whose page was posted from.
        if ($id) {
            $existing = $this->hr_model->get_kra_kpi_item($id);
            if (!$existing || (int) $existing['staff_id'] !== $staff_id) {
                show_404();
            }
            // entry_type is never switched on edit; the stored one wins.
            $type = $existing['entry_type'];
        }

        if ($title === '') {
            set_alert('warning', 'A title is required.');
            redirect(admin_url('hr/employee/' . $staff_id . '?tab=krakpi'));
        }

        $statuses    = hr_kra_kpi_statuses();
        $frequencies = hr_kra_kpi_frequencies();
        $status      = (string) $this->input->post('status');
        $frequency   = (string) $this->input->post('frequency');
        $from        = trim((string) $this->input->post('period_from'));
        $to          = trim((string) $this->input->post('period_to'));
        $rating      = $this->input->post('rating');

        // A KPI may reference the KRA it measures; the parent must belong to
        // the same employee and be a KRA, otherwise the link is dropped.
        $parent_id = (int) $this->input->post('parent_id');
        if ($type !== 'kpi' || $parent_id === 0) {
            $parent_id = null;
        } else {
            $parent = $this->hr_model->get_kra_kpi_item($parent_id);
            if (!$parent || (int) $parent['staff_id'] !== $staff_id || $parent['entry_type'] !== 'kra') {
                $parent_id = null;
            }
        }

        // TinyMCE hands back an empty paragraph when the editor was only cleared,
        // which would otherwise render as a stray blank line under the title.
        $rich = function ($raw) {
            $clean = html_purify((string) $raw);
            $plain = trim(str_replace('&nbsp;', '', strip_tags($clean)));

            return ($plain === '' && stripos($clean, '<img') === false) ? null : $clean;
        };

        $data = [
            'entry_type'     => $type,
            'parent_id'      => $parent_id,
            'title'          => $title,
            'description'    => $rich($this->input->post('description', false)),
            'metric'         => trim((string) $this->input->post('metric')) ?: null,
            'target_value'   => trim((string) $this->input->post('target_value')) ?: null,
            'actual_value'   => trim((string) $this->input->post('actual_value')) ?: null,
            'weightage'      => min(100, max(0, (float) $this->input->post('weightage'))),
            'frequency'      => isset($frequencies[$frequency]) ? $frequency : 'annual',
            'period_from'    => preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) ? $from : null,
            'period_to'      => preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) ? $to : null,
            'status'         => isset($statuses[$status]) ? $status : 'not_started',
            'rating'         => ($rating === null || $rating === '') ? null : min(5, max(0, (float) $rating)),
            'review_remarks' => $rich($this->input->post('review_remarks', false)),
            'sort_order'     => (int) $this->input->post('sort_order'),
        ];

        $this->hr_model->save_kra_kpi($staff_id, $data, $id);
        set_alert('success', ($id ? strtoupper($type) . ' updated.' : strtoupper($type) . ' added.'));
        redirect(admin_url('hr/employee/' . $staff_id . '?tab=krakpi'));
    }

    public function delete_kra_kpi($staff_id, $id)
    {
        $this->guard('hr_employees', 'delete');
        $staff_id = (int) $staff_id;
        $item     = $this->hr_model->get_kra_kpi_item((int) $id);
        if (!$item || (int) $item['staff_id'] !== $staff_id) {
            show_404();
        }
        $this->hr_model->delete_kra_kpi((int) $id);
        set_alert('success', strtoupper($item['entry_type']) . ' removed.');
        redirect(admin_url('hr/employee/' . $staff_id . '?tab=krakpi'));
    }

    /* --------------------------------------------------- Family & Nominees */

    /**
     * Create or update one family member of an employee. Posted from the
     * "Family & Nominees" tab modal; $id = 0 (or absent) inserts.
     */
    public function save_family_member($staff_id)
    {
        $this->guard('hr_employees', 'edit');
        if (!$this->input->post()) {
            show_404();
        }

        $staff_id = (int) $staff_id;
        if (!$this->hr_model->get_employee($staff_id)) {
            show_404();
        }
        $id   = (int) $this->input->post('id');
        $name = trim((string) $this->input->post('name'));
        if ($name === '') {
            set_alert('warning', 'Family member name is required.');
            redirect(admin_url('hr/employee/' . $staff_id . '?tab=family'));
        }

        $aadhaar = hr_normalize_aadhaar($this->input->post('aadhaar_number'));
        $dob     = trim((string) $this->input->post('date_of_birth'));

        $data = [
            'name'                 => $name,
            'relation'             => trim((string) $this->input->post('relation')) ?: null,
            'date_of_birth'        => preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob) ? $dob : null,
            'gender'               => trim((string) $this->input->post('gender')) ?: null,
            'blood_group'          => trim((string) $this->input->post('blood_group')) ?: null,
            'occupation'           => trim((string) $this->input->post('occupation')) ?: null,
            'phone'                => trim((string) $this->input->post('phone')) ?: null,
            'aadhaar_number'       => $aadhaar ?: null,
            'is_dependent'         => $this->input->post('is_dependent') ? 1 : 0,
            'is_emergency_contact' => $this->input->post('is_emergency_contact') ? 1 : 0,
            'notes'                => trim((string) $this->input->post('notes')) ?: null,
        ];

        // Editing must stay within the employee whose page was posted from.
        if ($id) {
            $existing = $this->hr_model->get_family_member($id);
            if (!$existing || (int) $existing['staff_id'] !== $staff_id) {
                show_404();
            }
        }

        $this->hr_model->save_family_member($staff_id, $data, $id);
        set_alert('success', $id ? 'Family member updated.' : 'Family member added.');
        if ($aadhaar !== '' && !hr_aadhaar_is_valid($aadhaar)) {
            set_alert('warning', 'Aadhaar saved, but it is not a valid 12-digit number — please double-check it.');
        }
        redirect(admin_url('hr/employee/' . $staff_id . '?tab=family'));
    }

    public function delete_family_member($staff_id, $id)
    {
        $this->guard('hr_employees', 'delete');
        $staff_id = (int) $staff_id;
        $member   = $this->hr_model->get_family_member((int) $id);
        if (!$member || (int) $member['staff_id'] !== $staff_id) {
            show_404();
        }
        $this->hr_model->delete_family_member((int) $id);
        set_alert('success', 'Family member removed.');
        redirect(admin_url('hr/employee/' . $staff_id . '?tab=family'));
    }

    /**
     * Create or update one nominee. Share is clamped to 0-100; the per-scheme
     * total is only warned about (HR may be mid-way through entering them).
     */
    public function save_nominee($staff_id)
    {
        $this->guard('hr_employees', 'edit');
        if (!$this->input->post()) {
            show_404();
        }

        $staff_id = (int) $staff_id;
        if (!$this->hr_model->get_employee($staff_id)) {
            show_404();
        }
        $id   = (int) $this->input->post('id');
        $name = trim((string) $this->input->post('name'));
        if ($name === '') {
            set_alert('warning', 'Nominee name is required.');
            redirect(admin_url('hr/employee/' . $staff_id . '?tab=family'));
        }

        $purposes = hr_nominee_purposes();
        $purpose  = (string) $this->input->post('nominee_for');
        if (!isset($purposes[$purpose])) {
            $purpose = 'general';
        }

        $aadhaar = hr_normalize_aadhaar($this->input->post('aadhaar_number'));
        $dob     = trim((string) $this->input->post('date_of_birth'));
        $share   = (float) $this->input->post('share_percent');
        $share   = max(0, min(100, $share));

        $data = [
            'name'              => $name,
            'relation'          => trim((string) $this->input->post('relation')) ?: null,
            'date_of_birth'     => preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob) ? $dob : null,
            'nominee_for'       => $purpose,
            'share_percent'     => $share,
            'phone'             => trim((string) $this->input->post('phone')) ?: null,
            'aadhaar_number'    => $aadhaar ?: null,
            'address'           => trim((string) $this->input->post('address')) ?: null,
            'guardian_name'     => trim((string) $this->input->post('guardian_name')) ?: null,
            'guardian_relation' => trim((string) $this->input->post('guardian_relation')) ?: null,
            'notes'             => trim((string) $this->input->post('notes')) ?: null,
        ];

        if ($id) {
            $existing = $this->hr_model->get_nominee($id);
            if (!$existing || (int) $existing['staff_id'] !== $staff_id) {
                show_404();
            }
        }

        $this->hr_model->save_nominee($staff_id, $data, $id);
        set_alert('success', $id ? 'Nominee updated.' : 'Nominee added.');

        $totals = $this->hr_model->nominee_share_totals($staff_id);
        $total  = round($totals[$purpose] ?? 0, 2);
        if (abs($total - 100) > 0.01) {
            set_alert('warning', $purposes[$purpose]['label'] . ' nominee shares now total ' . rtrim(rtrim(number_format($total, 2), '0'), '.') . '% — they should add up to 100%.');
        }
        redirect(admin_url('hr/employee/' . $staff_id . '?tab=family'));
    }

    public function delete_nominee($staff_id, $id)
    {
        $this->guard('hr_employees', 'delete');
        $staff_id = (int) $staff_id;
        $nominee  = $this->hr_model->get_nominee((int) $id);
        if (!$nominee || (int) $nominee['staff_id'] !== $staff_id) {
            show_404();
        }
        $this->hr_model->delete_nominee((int) $id);
        set_alert('success', 'Nominee removed.');
        redirect(admin_url('hr/employee/' . $staff_id . '?tab=family'));
    }

    public function save_salary_structure($staff_id)
    {
        $this->guard_payroll('edit');
        if (!$this->input->post()) {
            show_404();
        }
        $this->hr_model->save_salary_structure((int) $staff_id, $this->input->post('component') ?: []);

        $update = [];
        if ($this->input->post('basic_salary') !== null) {
            $update['basic_salary'] = (float) $this->input->post('basic_salary');
        }
        // Per-employee salary payment day. Empty = follow the global setting
        // (stored as NULL); "0" = last day of month; 1-31 = a fixed day.
        $spd = $this->input->post('salary_payment_day');
        $update['salary_payment_day'] = ($spd === '' || $spd === null) ? null : max(0, min(31, (int) $spd));

        if (!empty($update)) {
            $this->hr_model->save_employee((int) $staff_id, $update);
        }
        set_alert('success', _l('updated_successfully', _l('hr_salary_structure')));
        redirect(admin_url('hr/employee/' . (int) $staff_id . '?tab=salary'));
    }

    /**
     * AJAX: upload / change the employee's profile photo.
     *
     * The in-page cropper posts a square-cropped image as a real multipart
     * file (Blob), so we reuse the core handler — it validates the extension,
     * writes the small_/thumb_ variants and updates tblstaff.profile_image,
     * keeping the photo consistent everywhere the CRM reads it.
     */
    public function save_employee_image($staff_id)
    {
        $this->guard('hr_employees', 'edit');
        $staff_id = (int) $staff_id;

        if (!$this->hr_model->get_employee($staff_id)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Employee not found']);
            die;
        }

        $ok = handle_staff_profile_image_upload($staff_id);

        header('Content-Type: application/json');
        echo json_encode([
            'success' => (bool) $ok,
            'image'   => staff_profile_image_url($staff_id, 'small'),
            'message' => $ok ? _l('updated_successfully', _l('hr_employee')) : _l('file_php_extension_blocked'),
        ]);
        die;
    }

    /**
     * Remove the employee's profile photo. Mirrors the core staff behaviour:
     * deletes the upload directory and clears tblstaff.profile_image.
     */
    public function remove_employee_image($staff_id)
    {
        $this->guard('hr_employees', 'edit');
        $staff_id = (int) $staff_id;

        if (file_exists(get_upload_path_by_type('staff') . $staff_id)) {
            delete_dir(get_upload_path_by_type('staff') . $staff_id);
        }
        $this->db->where('staffid', $staff_id);
        $this->db->update(db_prefix() . 'staff', ['profile_image' => null]);

        set_alert('success', _l('deleted', _l('hr_employee')));
        redirect(admin_url('hr/employee/' . $staff_id));
    }

    /* ---------------------------------------------------------- Documents */

    public function upload_document($staff_id)
    {
        $this->guard('hr_employees', 'edit');
        $staff_id = (int) $staff_id;

        if (empty($_FILES['document']['name'])) {
            set_alert('warning', 'No file selected');
            redirect(admin_url('hr/employee/' . $staff_id . '?tab=documents'));
        }

        $allowed = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'webp', 'xls', 'xlsx', 'txt'];
        $ext     = strtolower(pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) {
            set_alert('danger', 'File type not allowed');
            redirect(admin_url('hr/employee/' . $staff_id . '?tab=documents'));
        }

        $dir = hr_upload_dir($staff_id);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $filename = uniqid('doc_') . '.' . $ext;
        if (!move_uploaded_file($_FILES['document']['tmp_name'], $dir . $filename)) {
            set_alert('danger', 'Upload failed');
            redirect(admin_url('hr/employee/' . $staff_id . '?tab=documents'));
        }

        $this->hr_model->add_document([
            'staff_id'    => $staff_id,
            'doc_type'    => $this->input->post('doc_type'),
            'title'       => $this->input->post('title') ?: $_FILES['document']['name'],
            'file_name'   => $filename,
            'issue_date'  => $this->input->post('issue_date') ?: null,
            'expiry_date' => $this->input->post('expiry_date') ?: null,
            'uploaded_by' => get_staff_user_id(),
            // HR-uploaded documents are trusted, so they count as verified.
            'source'      => 'hr',
            'status'      => 'verified',
            'verified_by' => get_staff_user_id(),
            'verified_at' => date('Y-m-d H:i:s'),
        ]);

        set_alert('success', _l('added_successfully', _l('hr_documents')));
        redirect(admin_url('hr/employee/' . $staff_id . '?tab=documents'));
    }

    /**
     * HR verifies or rejects an employee-submitted document.
     */
    public function verify_document($id, $status)
    {
        $this->guard('hr_employees', 'edit');
        $doc = $this->hr_model->get_document((int) $id);
        if (!$doc) {
            show_404();
        }
        $this->hr_model->verify_document((int) $id, $status, (string) $this->input->post('note'));
        set_alert('success', _l('hr_documents') . ' ' . $status);
        redirect(admin_url('hr/employee/' . $doc['staff_id'] . '?tab=documents'));
    }

    public function download_document($id)
    {
        $this->guard('hr_employees', 'view');
        $doc = $this->hr_model->get_document((int) $id);
        if (!$doc) {
            show_404();
        }
        $path = hr_upload_dir($doc['staff_id']) . $doc['file_name'];
        if (!file_exists($path)) {
            show_404();
        }
        $this->load->helper('download');
        $safe_title = preg_replace('/[^\w\s\.\-]/u', '_', $doc['title']);
        force_download($safe_title . '.' . pathinfo($doc['file_name'], PATHINFO_EXTENSION), file_get_contents($path));
    }

    /**
     * Stream a document INLINE (not as a download) so the browser renders it in
     * the preview modal: PDFs in an iframe, images in an <img>. Only image/PDF
     * types are served here; office files have no inline viewer and use Download.
     */
    public function preview_document($id)
    {
        $this->guard('hr_employees', 'view');
        $doc = $this->hr_model->get_document((int) $id);
        if (!$doc) {
            show_404();
        }
        $path = hr_upload_dir($doc['staff_id']) . $doc['file_name'];
        if (!file_exists($path)) {
            show_404();
        }

        $ext   = strtolower(pathinfo($doc['file_name'], PATHINFO_EXTENSION));
        $mimes = [
            'pdf'  => 'application/pdf',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'webp' => 'image/webp',
            'gif'  => 'image/gif',
        ];
        if (!isset($mimes[$ext])) {
            show_404();
        }

        // Clear any Perfex output buffering so the binary is not corrupted, and
        // drop the admin CSP for this raw file response (same pattern as the
        // Smart PDF standalone output).
        while (ob_get_level()) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header_remove('Content-Security-Policy');
            header_remove('Content-Security-Policy-Report-Only');
            header_remove('X-Frame-Options');
            header('Content-Type: ' . $mimes[$ext]);
            header('Content-Disposition: inline; filename="' . preg_replace('/[^\w\s\.\-]/u', '_', $doc['title']) . '.' . $ext . '"');
            header('Content-Length: ' . filesize($path));
            header('X-Content-Type-Options: nosniff');
        }
        readfile($path);
        exit;
    }

    public function delete_document($id)
    {
        $this->guard('hr_employees', 'delete');
        $doc = $this->hr_model->get_document((int) $id);
        $this->hr_model->delete_document((int) $id);
        set_alert('success', _l('deleted', _l('hr_documents')));
        redirect(admin_url('hr/employee/' . ($doc ? $doc['staff_id'] : '') . '?tab=documents'));
    }

    /* --------------------------------------------------------- Attendance */

    public function attendance()
    {
        $this->guard('hr_attendance', 'view');

        $date = $this->input->get('date') ?: date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d');
        }

        // Dashboard cards deep-link here to spotlight one status for the day.
        $att_status = $this->input->get('status');
        $data['active_status'] = in_array($att_status, array_keys(hr_attendance_statuses()), true) ? $att_status : '';

        $data['title']      = _l('hr_attendance');
        $data['date']       = $date;
        $data['employees']  = $this->hr_model->get_active_employees();
        $data['attendance'] = $this->hr_model->get_day_attendance($date);
        $data['statuses']   = hr_attendance_statuses();
        $data['shift_map']  = $this->hr_model->get_current_shift_map();
        $data['etime_enabled'] = hr_etime_enabled();
        $data['holiday']    = null;
        foreach ($this->hr_model->get_holidays(date('Y', strtotime($date)), true) as $h) {
            if ($h['holiday_date'] === $date) {
                $data['holiday'] = $h;
            }
        }

        $this->load->view('hr/attendance', $data);
    }

    public function save_attendance()
    {
        $this->guard('hr_attendance', 'edit');
        if (!$this->input->is_ajax_request() || !$this->input->post()) {
            show_404();
        }

        $date = $this->input->post('date');
        $rows = $this->input->post('rows') ?: [];
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            echo json_encode(['success' => false, 'message' => 'Invalid date']);

            return;
        }

        $statuses = array_keys(hr_attendance_statuses());
        $saved    = 0;
        foreach ($rows as $row) {
            if (empty($row['staff_id']) || empty($row['status']) || !in_array($row['status'], $statuses)) {
                continue;
            }
            $this->hr_model->save_attendance_row((int) $row['staff_id'], $date, [
                'status'    => $row['status'],
                'check_in'  => !empty($row['check_in']) ? $row['check_in'] : null,
                'check_out' => !empty($row['check_out']) ? $row['check_out'] : null,
                'note'      => isset($row['note']) ? substr($row['note'], 0, 255) : null,
                'marked_by' => get_staff_user_id(),
            ]);
            $saved++;
        }

        echo json_encode([
            'success' => true,
            'message' => $saved . ' attendance record(s) saved',
            $this->security->get_csrf_token_name() => $this->security->get_csrf_hash(),
        ]);
    }

    /* -------------------------------- Biometric attendance machine (e-time) */

    /**
     * Test the e-timeoffice connection. Uses the values posted from the Settings
     * form (so an admin can verify before saving); a blank password falls back
     * to the stored one.
     */
    public function etime_test()
    {
        $this->guard('hr_attendance', 'edit');
        if (!$this->input->is_ajax_request() || !$this->input->post()) {
            show_404();
        }

        $cfg = [
            'base_url'     => trim((string) $this->input->post('base_url')) ?: 'https://api.etimeoffice.com/api/',
            'corporate_id' => trim((string) $this->input->post('corporate_id')),
            'username'     => trim((string) $this->input->post('username')),
            'password'     => (string) $this->input->post('password'),
        ];
        if ($cfg['password'] === '') {
            $cfg['password'] = (string) get_option('hr_etime_password');
        }
        if (!hr_etime_configured($cfg)) {
            echo json_encode([
                'success' => false,
                'message' => 'Enter Corporate ID, Username and Password first.',
                $this->security->get_csrf_token_name() => $this->security->get_csrf_hash(),
            ]);

            return;
        }

        $res = hr_etime_fetch_inout(date('Y-m-d'), date('Y-m-d'), 'ALL', $cfg);
        $n   = ($res['ok'] && isset($res['body']['InOutPunchData']) && is_array($res['body']['InOutPunchData']))
            ? count($res['body']['InOutPunchData']) : 0;

        echo json_encode([
            'success' => $res['ok'],
            'message' => $res['ok']
                ? ('Connection successful — API reachable. ' . $n . ' record(s) returned for today.')
                : $res['error'],
            $this->security->get_csrf_token_name() => $this->security->get_csrf_hash(),
        ]);
    }

    /**
     * Pull punches from the machine and rebuild attendance. mode=range uses the
     * posted from/to dates; mode=incremental pulls only-new records via the
     * saved MaxRecord.
     */
    public function etime_sync()
    {
        $this->guard('hr_attendance', 'edit');
        if (!$this->input->is_ajax_request() || !$this->input->post()) {
            show_404();
        }
        $csrf = [$this->security->get_csrf_token_name() => $this->security->get_csrf_hash()];

        if (!hr_etime_enabled()) {
            echo json_encode(array_merge(['success' => false, 'message' => 'Enable the biometric machine integration in HR Settings first.'], $csrf));

            return;
        }

        if ($this->input->post('mode') === 'incremental') {
            $r = hr_etime_pull_incremental();
            echo json_encode(array_merge([
                'success' => $r['ok'],
                'message' => $r['ok']
                    ? ($r['added'] . ' new punch(es) pulled; ' . $r['attendance_updated'] . ' attendance day(s) updated.')
                    : $r['error'],
            ], $csrf));

            return;
        }

        $from = (string) $this->input->post('from');
        $to   = (string) $this->input->post('to');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $from = date('Y-m-d');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $to = date('Y-m-d');
        }
        if (strtotime($from) > strtotime($to)) {
            [$from, $to] = [$to, $from];
        }
        if (((strtotime($to) - strtotime($from)) / 86400) > 62) {
            echo json_encode(array_merge(['success' => false, 'message' => 'Please choose a date range of 62 days or less.'], $csrf));

            return;
        }

        $s   = hr_etime_run_sync($from, $to);
        $msg = $s['ok']
            ? ($s['punches_added'] . ' punch(es) imported; ' . $s['attendance_updated'] . ' attendance day(s) updated'
                . (count($s['unmapped'])
                    ? '. Unmapped code(s): ' . implode(', ', array_slice($s['unmapped'], 0, 10)) . (count($s['unmapped']) > 10 ? '…' : '')
                    : '.'))
            : $s['error'];

        echo json_encode(array_merge(['success' => $s['ok'], 'message' => $msg], $csrf));
    }

    /**
     * Raw machine punch log viewer (date range + optional employee filter).
     */
    public function punches()
    {
        $this->guard('hr_attendance', 'view');

        $to   = $this->input->get('to');
        $from = $this->input->get('from');
        if (!$to || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $to = date('Y-m-d');
        }
        if (!$from || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $from = date('Y-m-d', strtotime('-6 days'));
        }
        $staff_id = (int) $this->input->get('staff_id') ?: null;

        $data['title']         = 'Machine Punch Log';
        $data['from']          = $from;
        $data['to']            = $to;
        $data['staff_id']      = $staff_id;
        $data['punches']        = $this->hr_model->get_punches($from, $to, $staff_id);
        $data['employees']      = $this->hr_model->get_active_employees();
        $data['etime_enabled']  = hr_etime_enabled();
        $data['last_sync']      = json_decode(get_option('hr_etime_last_sync'), true) ?: null;
        $data['unmapped_codes'] = $this->hr_model->get_unmapped_punch_codes();

        $this->load->view('hr/punches', $data);
    }

    /**
     * One-time mapping of machine codes to employees (posted as map[code]=staff_id).
     * Saves the code on the employee, attaches all stored orphan punches with
     * that code and rebuilds attendance for the affected dates.
     */
    public function map_punch_codes()
    {
        $this->guard('hr_attendance', 'edit');
        if (!$this->input->is_ajax_request() || !$this->input->post()) {
            show_404();
        }
        $csrf = [$this->security->get_csrf_token_name() => $this->security->get_csrf_hash()];

        // JSON payload (not map[code]=id) so PHP never mangles code keys
        // containing dots or spaces.
        $pairs = json_decode((string) $this->input->post('map_json'), true);
        if (!is_array($pairs) || empty($pairs)) {
            echo json_encode(array_merge(['success' => false, 'message' => 'Pick an employee for at least one machine code.'], $csrf));

            return;
        }

        $r   = $this->hr_model->map_punch_codes($pairs);
        $msg = $r['mapped'] . ' code(s) mapped; ' . $r['attached'] . ' punch(es) attached; '
            . $r['attendance_updated'] . ' attendance day(s) updated.';
        if (!empty($r['skipped'])) {
            $msg .= ' Skipped (no HR profile): ' . implode(', ', $r['skipped']) . '.';
        }

        echo json_encode(array_merge(['success' => $r['mapped'] > 0, 'message' => $msg], $csrf));
    }

    /* ----------------------------------------- Field attendance (remote punch) */

    /**
     * Punch log for staff working outside the office: who punched, when, where
     * (GPS + nearest saved site), with the captured photo and the approval
     * state. Filters: date range, employee, status, punch type.
     */
    public function field_attendance()
    {
        $this->guard('hr_field_attendance', 'view');

        $to   = $this->input->get('to');
        $from = $this->input->get('from');
        if (!$to || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $to = date('Y-m-d');
        }
        if (!$from || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $from = date('Y-m-d', strtotime('-6 days'));
        }
        if (strtotime($from) > strtotime($to)) {
            [$from, $to] = [$to, $from];
        }

        $status = $this->input->get('status');
        if (!array_key_exists((string) $status, hr_field_statuses())) {
            $status = '';
        }
        $type = $this->input->get('punch_type');
        if (!array_key_exists((string) $type, hr_field_punch_types())) {
            $type = '';
        }
        $staff_id = (int) $this->input->get('staff_id') ?: null;

        $data['title']      = _l('hr_field_attendance');
        $data['from']       = $from;
        $data['to']         = $to;
        $data['status']     = $status;
        $data['punch_type'] = $type;
        $data['staff_id']   = $staff_id;
        $data['punches']    = $this->hr_model->get_field_punches([
            'from' => $from, 'to' => $to, 'staff_id' => $staff_id,
            'status' => $status, 'punch_type' => $type,
        ]);
        $data['stats']      = $this->hr_model->get_field_punch_stats($from, $to);
        $data['employees']  = $this->hr_model->get_active_employees();
        $data['sites']      = $this->hr_model->get_field_sites();
        $data['cfg']        = hr_field_settings();
        $data['statuses']   = hr_field_statuses();
        $data['types']      = hr_field_punch_types();
        $data['purposes']   = hr_field_purposes();

        $this->load->view('hr/field_attendance', $data);
    }

    /**
     * Serve a punch photo to a reviewer.
     */
    public function field_punch_photo($id)
    {
        $this->guard('hr_field_attendance', 'view');

        $punch = $this->hr_model->get_field_punch((int) $id);
        if (!$punch || empty($punch['photo'])) {
            show_404();
        }
        hr_field_stream_photo($punch);
    }

    /**
     * Approve or reject one punch. Approving (re)builds that employee's daily
     * attendance from their approved punches; rejecting rebuilds too, so a
     * withdrawn punch stops counting.
     */
    public function field_punch_action($id, $status)
    {
        $this->guard('hr_field_attendance', 'edit');
        if (!$this->input->post()) {
            show_404();
        }
        if (!in_array($status, ['approved', 'rejected'], true)) {
            show_404();
        }

        $punch = $this->hr_model->get_field_punch((int) $id);
        if (!$punch) {
            show_404();
        }

        $this->hr_model->update_field_punch((int) $id, [
            'status'      => $status,
            'reviewed_by' => get_staff_user_id(),
            'reviewed_at' => date('Y-m-d H:i:s'),
            'review_note' => substr(trim((string) $this->input->post('review_note')), 0, 255) ?: null,
        ]);
        $this->hr_model->rebuild_field_attendance($punch['staff_id'], $punch['punch_date']);

        // Tell the employee what happened to their punch.
        add_notification([
            'description'     => $status === 'approved' ? 'hr_field_punch_approved_notification' : 'hr_field_punch_rejected_notification',
            'touserid'        => (int) $punch['staff_id'],
            'fromcompany'     => 0,
            'fromuserid'      => get_staff_user_id(),
            'link'            => 'hr/myhr/field_punch',
            'additional_data' => serialize([
                hr_field_punch_types()[$punch['punch_type']]['label'],
                _dt($punch['punch_at']),
            ]),
        ]);
        pusher_trigger_notification([(int) $punch['staff_id']]);
        $this->fire_field_punch_review_hook($punch, $status, (string) $this->input->post('review_note'));

        set_alert('success', 'Punch ' . $status . '.');
        redirect($this->field_attendance_referrer());
    }

    /**
     * SMS / WhatsApp / e-mail the employee the verdict on a field punch.
     * Shared by the single-row action and the bulk action.
     *
     * @param  array  $punch  row as it was BEFORE the review
     * @param  string $status 'approved' | 'rejected'
     * @param  string $note
     * @return void
     */
    protected function fire_field_punch_review_hook($punch, $status, $note = '')
    {
        $types = hr_field_punch_types();

        hr_fire_employee_hook('hr_field_punch_' . $status, (int) $punch['staff_id'], [
            'punch_type'    => $types[$punch['punch_type']]['label'] ?? (string) $punch['punch_type'],
            'punch_date'    => _d($punch['punch_date']),
            'punch_time'    => _dt($punch['punch_at']),
            'reviewer_name' => get_staff_full_name(get_staff_user_id()),
            'review_note'   => $note,
        ]);
    }

    /**
     * Approve or reject several punches at once (checkbox selection).
     */
    public function field_punch_bulk()
    {
        $this->guard('hr_field_attendance', 'edit');
        if (!$this->input->post()) {
            show_404();
        }

        $status = $this->input->post('bulk_status');
        if (!in_array($status, ['approved', 'rejected'], true)) {
            set_alert('warning', 'Choose Approve or Reject first.');
            redirect($this->field_attendance_referrer());
        }
        $ids = $this->input->post('punch_ids') ?: [];
        if (empty($ids)) {
            set_alert('warning', 'Select at least one punch.');
            redirect($this->field_attendance_referrer());
        }

        $touched = [];
        $done    = 0;
        foreach ($ids as $id) {
            $punch = $this->hr_model->get_field_punch((int) $id);
            if (!$punch) {
                continue;
            }
            $this->hr_model->update_field_punch((int) $id, [
                'status'      => $status,
                'reviewed_by' => get_staff_user_id(),
                'reviewed_at' => date('Y-m-d H:i:s'),
            ]);
            $touched[$punch['staff_id'] . '|' . $punch['punch_date']] = [$punch['staff_id'], $punch['punch_date']];
            $this->fire_field_punch_review_hook($punch, $status);
            $done++;
        }
        foreach ($touched as $t) {
            $this->hr_model->rebuild_field_attendance($t[0], $t[1]);
        }

        set_alert('success', $done . ' punch(es) ' . $status . '.');
        redirect($this->field_attendance_referrer());
    }

    public function delete_field_punch($id)
    {
        $this->guard('hr_field_attendance', 'delete');

        $punch = $this->hr_model->get_field_punch((int) $id);
        if (!$punch) {
            show_404();
        }
        $this->hr_model->delete_field_punch((int) $id);
        $this->hr_model->rebuild_field_attendance($punch['staff_id'], $punch['punch_date']);

        set_alert('success', _l('deleted', 'Field punch'));
        redirect($this->field_attendance_referrer());
    }

    /**
     * Keep the reviewer on the same filtered list after an action.
     */
    protected function field_attendance_referrer()
    {
        $ref = (string) $this->input->post('redirect_to');
        if ($ref !== '' && strpos($ref, admin_url('hr/field_attendance')) === 0) {
            return $ref;
        }

        return admin_url('hr/field_attendance');
    }

    /* ------------------------------------- Field attendance: work locations */

    public function field_sites()
    {
        $this->guard('hr_field_attendance', 'view');

        $data['title'] = 'Field Work Locations';
        $data['sites'] = $this->hr_model->get_field_sites();
        $data['cfg']   = hr_field_settings();

        $this->load->view('hr/field_sites', $data);
    }

    public function save_field_site()
    {
        $this->guard('hr_field_attendance', 'edit');
        if (!$this->input->post()) {
            show_404();
        }

        $id   = (int) $this->input->post('id') ?: null;
        $name = trim((string) $this->input->post('name'));
        $lat  = $this->input->post('latitude');
        $lng  = $this->input->post('longitude');

        if ($name === '' || $lat === '' || $lng === '' || !is_numeric($lat) || !is_numeric($lng)) {
            set_alert('warning', 'Location name, latitude and longitude are required.');
            redirect(admin_url('hr/field_sites'));
        }

        $radius = (int) $this->input->post('radius_m');
        $this->hr_model->save_field_site([
            'name'      => substr($name, 0, 150),
            'address'   => substr(trim((string) $this->input->post('address')), 0, 255) ?: null,
            'latitude'  => max(-90, min(90, (float) $lat)),
            'longitude' => max(-180, min(180, (float) $lng)),
            'radius_m'  => $radius > 0 ? min(50000, $radius) : 200,
            'is_active' => $this->input->post('is_active') ? 1 : 0,
        ], $id);

        set_alert('success', $id ? _l('updated_successfully', 'Location') : _l('added_successfully', 'Location'));
        redirect(admin_url('hr/field_sites'));
    }

    public function delete_field_site($id)
    {
        $this->guard('hr_field_attendance', 'delete');

        $this->hr_model->delete_field_site((int) $id);
        set_alert('success', _l('deleted', 'Location'));
        redirect(admin_url('hr/field_sites'));
    }

    public function attendance_register()
    {
        $this->guard('hr_attendance', 'view');

        $month = (int) ($this->input->get('month') ?: date('n'));
        $year  = (int) ($this->input->get('year') ?: date('Y'));
        $month = max(1, min(12, $month));

        $data['title']     = _l('hr_attendance') . ' Register';
        $data['month']     = $month;
        $data['year']      = $year;
        $data['employees'] = $this->hr_model->get_active_employees();
        $data['matrix']    = $this->hr_model->get_month_attendance($month, $year);
        $data['statuses']  = hr_attendance_statuses();
        $data['holidays']  = [];
        foreach ($this->hr_model->get_holidays($year, true) as $h) {
            if ((int) date('n', strtotime($h['holiday_date'])) === $month) {
                $data['holidays'][(int) date('j', strtotime($h['holiday_date']))] = $h['name'];
            }
        }

        $this->load->view('hr/attendance_register', $data);
    }

    /* ------------------------------------------------------------- Shifts */

    public function shifts()
    {
        $this->guard('hr_shifts', 'view');

        $data['title']     = _l('hr_shifts');
        $data['shifts']    = $this->hr_model->get_shifts();
        $data['employees'] = $this->hr_model->get_active_employees();
        $data['shift_map'] = $this->hr_model->get_current_shift_map();

        $this->load->view('hr/shifts', $data);
    }

    /**
     * Premium printable duty roster grouped by shift.
     */
    public function shift_sheet()
    {
        $this->guard('hr_shifts', 'view');

        $this->load->model('departments_model');
        $dept_names = [];
        foreach ($this->departments_model->get() as $d) {
            $dept_names[(int) $d['departmentid']] = $d['name'];
        }

        $data['title']      = 'Shift Sheet';
        $data['shifts']     = $this->hr_model->get_shifts();
        $data['employees']  = $this->hr_model->get_active_employees();
        $data['shift_map']  = $this->hr_model->get_current_shift_map();
        $data['dept_names'] = $dept_names;
        $data['company']    = get_option('invoice_company_name') ?: get_option('companyname');
        $logo_file          = get_option('company_logo_dark') ?: get_option('company_logo');
        $data['logo_url']   = $logo_file ? base_url('uploads/company/' . $logo_file) : '';

        $this->load->view('hr/shift_sheet', $data);
    }

    public function save_shift()
    {
        $this->guard('hr_shifts', 'edit');
        if (!$this->input->post()) {
            show_404();
        }
        $id   = $this->input->post('id') ?: null;
        $data = [
            'name'          => $this->input->post('name'),
            'start_time'    => $this->input->post('start_time'),
            'end_time'      => $this->input->post('end_time'),
            'break_minutes' => (int) $this->input->post('break_minutes'),
            'grace_minutes' => (int) $this->input->post('grace_minutes'),
            'week_off_days' => implode(',', $this->input->post('week_off_days') ?: []),
            'is_default'    => $this->input->post('is_default') ? 1 : 0,
            'is_active'     => $this->input->post('is_active') ? 1 : 0,
        ];
        $this->hr_model->save_shift($data, $id);
        set_alert('success', $id ? _l('updated_successfully', 'Shift') : _l('added_successfully', 'Shift'));
        redirect(admin_url('hr/shifts'));
    }

    public function delete_shift($id)
    {
        $this->guard('hr_shifts', 'delete');
        $this->hr_model->delete_shift((int) $id);
        set_alert('success', _l('deleted', 'Shift'));
        redirect(admin_url('hr/shifts'));
    }

    public function assign_shift()
    {
        $this->guard('hr_shifts', 'edit');
        if (!$this->input->post()) {
            show_404();
        }
        $staff_ids = $this->input->post('staff_ids') ?: [];
        $shift_id  = (int) $this->input->post('shift_id');
        $from      = $this->input->post('effective_from') ?: date('Y-m-d');
        $shift     = $this->hr_model->get_shift($shift_id);
        foreach ($staff_ids as $sid) {
            $this->hr_model->assign_shift((int) $sid, $shift_id, $from);
            if ($shift) {
                hr_fire_employee_hook('hr_shift_assigned', (int) $sid, [
                    'shift_name'     => (string) $shift['name'],
                    'shift_start'    => (string) $shift['start_time'],
                    'shift_end'      => (string) $shift['end_time'],
                    'effective_from' => _d($from),
                ]);
            }
        }
        set_alert('success', count($staff_ids) . ' employee(s) assigned to shift');
        redirect(admin_url('hr/shifts'));
    }

    /* ----------------------------------------------------------- Holidays */

    public function holidays()
    {
        $this->guard('hr_holidays', 'view');
        $year = (int) ($this->input->get('year') ?: date('Y'));

        $data['title']    = _l('hr_holidays');
        $data['year']     = $year;
        $data['holidays'] = $this->hr_model->get_holidays($year);

        $this->load->view('hr/holidays', $data);
    }

    public function save_holiday()
    {
        $this->guard('hr_holidays', 'edit');
        if (!$this->input->post()) {
            show_404();
        }
        $id = $this->input->post('id') ?: null;
        $this->hr_model->save_holiday([
            'name'         => $this->input->post('name'),
            'holiday_date' => $this->input->post('holiday_date'),
            'is_optional'  => $this->input->post('is_optional') ? 1 : 0,
        ], $id);
        set_alert('success', $id ? _l('updated_successfully', 'Holiday') : _l('added_successfully', 'Holiday'));
        redirect(admin_url('hr/holidays?year=' . date('Y', strtotime($this->input->post('holiday_date')))));
    }

    public function delete_holiday($id)
    {
        $this->guard('hr_holidays', 'delete');
        $this->hr_model->delete_holiday((int) $id);
        set_alert('success', _l('deleted', 'Holiday'));
        redirect(admin_url('hr/holidays'));
    }

    public function import_indian_holidays()
    {
        $this->guard('hr_holidays', 'edit');
        if (!$this->input->post()) {
            show_404();
        }
        $year  = (int) ($this->input->post('year') ?: date('Y'));
        $added = $this->hr_model->import_indian_holidays($year);
        if ($added > 0) {
            set_alert('success', $added . ' Indian holiday(s) added for ' . $year . '.');
        } else {
            set_alert('warning', 'No new holidays added for ' . $year . ' — they are already in the list.');
        }
        redirect(admin_url('hr/holidays?year=' . $year));
    }

    public function toggle_holiday()
    {
        $this->guard('hr_holidays', 'edit');
        if (!$this->input->is_ajax_request() || !$this->input->post()) {
            show_404();
        }
        $this->hr_model->toggle_holiday(
            (int) $this->input->post('id'),
            $this->input->post('active') ? 1 : 0
        );
        echo json_encode([
            'success'                                => true,
            $this->security->get_csrf_token_name()   => $this->security->get_csrf_hash(),
        ]);
    }

    /* -------------------------------------------------------------- Leave */

    public function leaves()
    {
        $this->guard('hr_leaves', 'view');

        // Pending is the default view — there is no "All" tab.
        $status = $this->input->get('status');
        if (!in_array($status, ['pending', 'approved', 'rejected', 'cancelled'], true)) {
            $status = 'pending';
        }
        $where = ['r.status' => $status];

        $data['title']       = _l('hr_leaves');
        $data['status']      = $status;
        $data['requests']    = $this->hr_model->get_leave_requests($where);
        $data['leave_types'] = $this->hr_model->get_leave_types();
        $data['employees']   = $this->hr_model->get_active_employees();

        // Multi-level approval context for the progress column / action buttons.
        $data['chain']       = hr_leave_chain();
        $data['role_names']  = [];
        $this->load->model('roles_model');
        foreach ($this->roles_model->get() as $r) {
            $data['role_names'][(int) $r['roleid']] = $r['name'];
        }

        $this->load->view('hr/leaves', $data);
    }

    public function apply_leave()
    {
        $this->guard('hr_leaves', 'create');
        if (!$this->input->post()) {
            show_404();
        }
        $is_half  = $this->input->post('is_half_day') ? 1 : 0;
        $from     = $this->input->post('from_date');
        $to       = $is_half ? $from : $this->input->post('to_date');
        $staff_id = (int) $this->input->post('staff_id');

        $proof = hr_store_leave_proof($staff_id);
        if ($proof['error']) {
            set_alert('danger', $proof['error']);
            redirect(admin_url('hr/leaves'));
        }

        $id = $this->hr_model->add_leave_request([
            'staff_id'      => $staff_id,
            'leave_type_id' => (int) $this->input->post('leave_type_id'),
            'from_date'     => $from,
            'to_date'       => $to,
            'is_half_day'   => $is_half,
            'reason'        => $this->input->post('reason'),
            'proof_file'    => $proof['file'],
        ]);
        set_alert($id ? 'success' : 'danger', $id ? _l('added_successfully', _l('hr_leave_request')) : 'Invalid date range');
        redirect(admin_url('hr/leaves'));
    }

    /**
     * Stream a leave request's proof-of-reason file for HR (inline preview for
     * images/PDFs, download for other types).
     */
    public function leave_proof($id)
    {
        $this->guard('hr_leaves', 'view');
        $request = $this->hr_model->get_leave_request((int) $id);
        if (!$request) {
            show_404();
        }
        hr_output_leave_proof($request);
    }

    /**
     * "Review before approving" popup body (AJAX). Shows what the employee has
     * in flight — Todo tasks, tickets, sales meetings, trainings, interviews —
     * plus who else in their department is already off on the same dates.
     */
    public function leave_review($id)
    {
        $this->guard('hr_leaves', 'view');

        $request = $this->hr_model->get_leave_request((int) $id);
        if (!$request) {
            show_404();
        }

        $data['request']    = $request;
        $data['review']     = hr_leave_review_data($request);
        $data['can_act']    = hr_can_action_leave_level($request, get_staff_user_id());
        $data['action_url'] = admin_url('hr/leave_action/');
        $data['proof_url']  = admin_url('hr/leave_proof/' . (int) $id);
        $data['chain']      = hr_leave_chain();
        $data['role_names'] = [];
        $this->load->model('roles_model');
        foreach ($this->roles_model->get() as $r) {
            $data['role_names'][(int) $r['roleid']] = $r['name'];
        }

        $this->load->view('hr/leave_review', $data);
    }

    public function leave_action($id, $status)
    {
        if (!in_array($status, ['approved', 'rejected', 'cancelled'])) {
            show_404();
        }
        $request = $this->hr_model->get_leave_request((int) $id);
        if (!$request) {
            show_404();
        }
        $note = $this->input->post('note') ?: '';

        if ($status === 'cancelled') {
            $this->guard('hr_leaves', 'edit');
            $this->hr_model->action_leave_request((int) $id, 'cancelled', $note);
            set_alert('success', _l('hr_leave_request') . ' cancelled');
            redirect(admin_url('hr/leaves'));
        }

        // Approve / reject: the current user must be able to act on the request's
        // current approval level. Each action clears just that one level — even
        // for admins — so the configured chain is always followed in full.
        if (!hr_can_action_leave_level($request, get_staff_user_id())) {
            access_denied('hr_leaves');
        }
        $result = $this->hr_model->leave_stage_action((int) $id, $status, get_staff_user_id(), $note);
        $label  = ['approved' => 'approved', 'advanced' => 'approved for this level', 'rejected' => 'rejected'];
        set_alert('success', _l('hr_leave_request') . ' ' . ($label[$result] ?? $status));
        redirect(admin_url('hr/leaves'));
    }

    public function delete_leave($id)
    {
        $this->guard('hr_leaves', 'delete');
        $this->hr_model->delete_leave_request((int) $id);
        set_alert('success', _l('deleted', _l('hr_leave_request')));
        redirect(admin_url('hr/leaves'));
    }

    public function save_leave_type()
    {
        $this->guard('hr_leaves', 'edit');
        if (!$this->input->post()) {
            show_404();
        }
        $id = $this->input->post('id') ?: null;
        $this->hr_model->save_leave_type([
            'name'              => $this->input->post('name'),
            'code'              => strtoupper($this->input->post('code')),
            'days_per_year'     => (float) $this->input->post('days_per_year'),
            'is_paid'           => $this->input->post('is_paid') ? 1 : 0,
            'carry_forward'     => $this->input->post('carry_forward') ? 1 : 0,
            'carry_forward_max' => (float) $this->input->post('carry_forward_max'),
            'is_emergency'      => $this->input->post('is_emergency') ? 1 : 0,
            'icon'              => trim((string) $this->input->post('icon')) ?: null,
            'color'             => $this->input->post('color') ?: '#7c3aed',
            'is_active'         => $this->input->post('is_active') ? 1 : 0,
        ], $id);
        set_alert('success', $id ? _l('updated_successfully', 'Leave type') : _l('added_successfully', 'Leave type'));
        redirect(admin_url('hr/leaves'));
    }

    public function delete_leave_type($id)
    {
        $this->guard('hr_leaves', 'delete');
        $deleted = $this->hr_model->delete_leave_type((int) $id);
        set_alert($deleted ? 'success' : 'warning', $deleted ? _l('deleted', 'Leave type') : 'Leave type is in use - deactivated instead');
        redirect(admin_url('hr/leaves'));
    }

    public function allocations()
    {
        $this->guard('hr_leaves', 'view');
        $year = (int) ($this->input->get('year') ?: date('Y'));

        $data['title']       = 'Leave Allocations';
        $data['year']        = $year;
        $data['employees']   = $this->hr_model->get_active_employees();
        $data['leave_types'] = $this->hr_model->get_leave_types(true);
        $data['allocations'] = $this->hr_model->get_leave_allocations($year);
        $data['leave_used']  = $this->hr_model->get_leave_used($year);
        $data['carried']     = $this->hr_model->get_leave_carried($year);

        $this->load->view('hr/allocations', $data);
    }

    public function save_allocations()
    {
        $this->guard('hr_leaves', 'edit');
        if (!$this->input->post()) {
            show_404();
        }
        $year  = (int) $this->input->post('year');
        $alloc = $this->input->post('alloc') ?: [];
        foreach ($alloc as $staff_id => $types) {
            foreach ($types as $type_id => $days) {
                if ($days === '') {
                    continue;
                }
                $this->hr_model->set_leave_allocation((int) $staff_id, (int) $type_id, $year, (float) $days);
            }
        }
        set_alert('success', _l('updated_successfully', 'Leave allocations'));
        redirect(admin_url('hr/allocations?year=' . $year));
    }

    public function carry_forward()
    {
        $this->guard('hr_leaves', 'edit');
        $year = (int) ($this->input->post('year') ?: $this->input->get('year') ?: date('Y'));
        $cells = $this->hr_model->carry_forward_leaves($year);
        if ($cells > 0) {
            set_alert('success', 'Unused leave from ' . ($year - 1) . ' carried forward into ' . $year . '.');
        } else {
            set_alert('warning', 'No carry-forward leave types are enabled. Enable "Carry Fwd" on a leave type first.');
        }
        redirect(admin_url('hr/allocations?year=' . $year));
    }

    /* ------------------------------------------------------------ Payroll */

    public function payroll()
    {
        $this->guard_payroll();

        $data['title']      = _l('hr_payroll');
        $data['runs']       = $this->hr_model->get_payroll_runs();
        $data['components'] = $this->hr_model->get_salary_components();

        $this->load->view('hr/payroll', $data);
    }

    public function generate_payroll()
    {
        $this->guard_payroll('create');
        if (!$this->input->post()) {
            show_404();
        }
        $month  = max(1, min(12, (int) $this->input->post('month')));
        $year   = (int) $this->input->post('year');
        $result = $this->hr_model->generate_payroll($month, $year);

        if ($result === 'finalized') {
            set_alert('warning', 'Payroll for this period is already finalized');
            redirect(admin_url('hr/payroll'));
        }
        set_alert('success', $result[1] . ' payslip(s) generated');
        redirect(admin_url('hr/payroll_run/' . $result[0]));
    }

    public function payroll_run($id)
    {
        $this->guard_payroll();
        $run = $this->hr_model->get_payroll_run((int) $id);
        if (!$run) {
            show_404();
        }
        $data['title']    = _l('hr_payroll') . ' - ' . date('F Y', strtotime(sprintf('%04d-%02d-01', $run['year'], $run['month'])));
        $data['run']      = $run;
        $data['payslips'] = $this->hr_model->get_payslips((int) $id);

        $this->load->view('hr/payroll_run', $data);
    }

    public function finalize_payroll($id)
    {
        $this->guard_payroll('edit');
        $this->hr_model->finalize_payroll((int) $id);
        set_alert('success', 'Payroll finalized');
        redirect(admin_url('hr/payroll_run/' . (int) $id));
    }

    public function delete_payroll_run($id)
    {
        $this->guard_payroll('delete');
        // Superadmins may delete finalized runs too; everyone else is limited to
        // draft runs.
        $ok = $this->hr_model->delete_payroll_run((int) $id, is_admin());
        set_alert($ok ? 'success' : 'warning', $ok ? 'Payroll run and all its payslips deleted' : 'Finalized runs can only be deleted by a superadmin');
        redirect(admin_url('hr/payroll'));
    }

    public function mark_paid($payslip_id)
    {
        $this->guard_payroll('edit');
        $this->hr_model->mark_payslip_paid(
            (int) $payslip_id,
            $this->input->post('payment_mode') ?: '',
            $this->input->post('paid_date') ?: null
        );
        $slip = $this->hr_model->get_payslip((int) $payslip_id);
        set_alert('success', 'Payslip marked as paid');
        redirect(admin_url('hr/payroll_run/' . ($slip ? $slip['run_id'] : '')));
    }

    public function delete_payslip($id)
    {
        $this->guard_payroll('delete');
        $slip = $this->hr_model->get_payslip((int) $id);
        $this->hr_model->delete_payslip((int) $id);
        set_alert('success', _l('deleted', _l('hr_payslip')));
        redirect(admin_url('hr/payroll_run/' . ($slip ? $slip['run_id'] : '')));
    }

    public function payslip($id)
    {
        $this->guard_payroll();
        $slip = $this->hr_model->get_payslip((int) $id);
        if (!$slip) {
            show_404();
        }
        $this->load->model('departments_model');
        $dept = null;
        if ($slip['department_id']) {
            $dept = $this->departments_model->get($slip['department_id']);
        }
        $data['title']      = _l('hr_payslip');
        $data['slip']       = $slip;
        $data['department'] = $dept;

        $this->load->view('hr/payslip', $data);
    }

    public function save_salary_component()
    {
        $this->guard_payroll('edit');
        if (!$this->input->post()) {
            show_404();
        }
        $id = $this->input->post('id') ?: null;
        $this->hr_model->save_salary_component([
            'name'          => $this->input->post('name'),
            'type'          => $this->input->post('type') === 'deduction' ? 'deduction' : 'earning',
            'calc_type'     => $this->input->post('calc_type') === 'percent_basic' ? 'percent_basic' : 'fixed',
            'default_value' => (float) $this->input->post('default_value'),
            'sort_order'    => (int) $this->input->post('sort_order'),
            'is_active'     => $this->input->post('is_active') ? 1 : 0,
        ], $id);
        set_alert('success', $id ? _l('updated_successfully', 'Component') : _l('added_successfully', 'Component'));
        // Adding a component from an employee's Salary tab returns there.
        $back = (int) $this->input->post('return_staff_id');
        redirect($back ? admin_url('hr/employee/' . $back . '?tab=salary') : admin_url('hr/payroll'));
    }

    public function delete_salary_component($id)
    {
        $this->guard_payroll('delete');
        $this->hr_model->delete_salary_component((int) $id);
        set_alert('success', _l('deleted', 'Component'));
        redirect(admin_url('hr/payroll'));
    }

    /* ---------------------------------------------------------- Trainings */

    public function trainings()
    {
        $this->guard('hr_trainings', 'view');
        $this->load->model('departments_model');

        $data['title']       = _l('hr_trainings');
        $data['trainings']   = $this->hr_model->get_trainings();
        $data['departments'] = $this->departments_model->get();

        $this->load->view('hr/trainings', $data);
    }

    public function save_training()
    {
        $this->guard('hr_trainings', 'edit');
        if (!$this->input->post()) {
            show_404();
        }
        $id       = $this->input->post('id') ?: null;
        $saved_id = $this->hr_model->save_training([
            'title'         => $this->input->post('title'),
            'trainer'       => $this->input->post('trainer'),
            'category'      => $this->input->post('category'),
            'start_date'    => $this->input->post('start_date') ?: null,
            'end_date'      => $this->input->post('end_date') ?: null,
            'department_id' => (int) $this->input->post('department_id') ?: null,
            'venue'         => $this->input->post('venue'),
            'description'   => $this->input->post('description'),
            'status'        => $this->input->post('status') ?: 'planned',
        ], $id);
        set_alert('success', $id ? _l('updated_successfully', 'Training') : _l('added_successfully', 'Training'));
        redirect(admin_url('hr/training/' . $saved_id));
    }

    public function training($id)
    {
        $this->guard('hr_trainings', 'view');
        $training = $this->hr_model->get_training((int) $id);
        if (!$training) {
            show_404();
        }
        $this->load->model('departments_model');

        $data['title']       = $training['title'];
        $data['training']    = $training;
        $data['attendees']   = $this->hr_model->get_training_attendees((int) $id);
        $data['employees']   = $this->hr_model->get_active_employees();
        $data['departments'] = $this->departments_model->get();

        $this->load->view('hr/training', $data);
    }

    public function save_training_attendees($id)
    {
        $this->guard('hr_trainings', 'edit');
        if (!$this->input->post()) {
            show_404();
        }
        $this->hr_model->set_training_attendees((int) $id, $this->input->post('staff_ids') ?: []);
        set_alert('success', _l('updated_successfully', 'Attendees'));
        redirect(admin_url('hr/training/' . (int) $id));
    }

    public function update_training_attendee($attendee_id)
    {
        $this->guard('hr_trainings', 'edit');
        if (!$this->input->post()) {
            show_404();
        }
        $this->hr_model->update_training_attendee((int) $attendee_id, [
            'attendee_status' => $this->input->post('attendee_status'),
            'score'           => $this->input->post('score'),
            'remarks'         => $this->input->post('remarks'),
        ]);
        set_alert('success', _l('updated_successfully', 'Attendee'));
        redirect(admin_url('hr/training/' . (int) $this->input->post('training_id')));
    }

    public function delete_training($id)
    {
        $this->guard('hr_trainings', 'delete');
        $this->hr_model->delete_training((int) $id);
        set_alert('success', _l('deleted', 'Training'));
        redirect(admin_url('hr/trainings'));
    }

    /* --------------------------------------------------------- Appraisals */

    public function appraisals()
    {
        $this->guard('hr_appraisals', 'view');

        $data['title']      = _l('hr_appraisals');
        $data['appraisals'] = $this->hr_model->get_appraisals();
        $data['employees']  = $this->hr_model->get_active_employees();

        $this->load->view('hr/appraisals', $data);
    }

    public function appraisal($id = null)
    {
        $this->guard('hr_appraisals', 'view');

        $appraisal = $id ? $this->hr_model->get_appraisal((int) $id) : null;
        if ($id && !$appraisal) {
            show_404();
        }

        $data['title']     = $appraisal ? 'Appraisal #' . $appraisal['id'] : 'New Appraisal';
        $data['appraisal'] = $appraisal;
        $data['employees'] = $this->hr_model->get_active_employees();
        $data['criteria']  = [
            'Clinical / Job Knowledge', 'Quality of Work', 'Punctuality & Attendance',
            'Patient Care & Empathy', 'Teamwork & Communication', 'Initiative & Leadership',
            'Adherence to Protocols & Safety',
        ];

        $this->load->view('hr/appraisal', $data);
    }

    public function save_appraisal()
    {
        $this->guard('hr_appraisals', 'edit');
        if (!$this->input->post()) {
            show_404();
        }
        $id      = $this->input->post('id') ?: null;
        $ratings = $this->input->post('ratings') ?: [];
        $clean   = [];
        $sum     = 0;
        $count   = 0;
        foreach ($ratings as $criterion => $score) {
            $score = max(0, min(5, (float) $score));
            $clean[$criterion] = $score;
            if ($score > 0) {
                $sum += $score;
                $count++;
            }
        }

        $saved_id = $this->hr_model->save_appraisal([
            'staff_id'       => (int) $this->input->post('staff_id'),
            'period_from'    => $this->input->post('period_from') ?: null,
            'period_to'      => $this->input->post('period_to') ?: null,
            'reviewer_id'    => (int) $this->input->post('reviewer_id') ?: get_staff_user_id(),
            'ratings'        => json_encode($clean),
            'overall_rating' => $count ? round($sum / $count, 1) : 0,
            'strengths'      => $this->input->post('strengths'),
            'improvements'   => $this->input->post('improvements'),
            'goals'          => $this->input->post('goals'),
            'status'         => $this->input->post('status') === 'completed' ? 'completed' : 'draft',
        ], $id);

        set_alert('success', $id ? _l('updated_successfully', 'Appraisal') : _l('added_successfully', 'Appraisal'));
        redirect(admin_url('hr/appraisal/' . $saved_id));
    }

    public function delete_appraisal($id)
    {
        $this->guard('hr_appraisals', 'delete');
        $this->hr_model->delete_appraisal((int) $id);
        set_alert('success', _l('deleted', 'Appraisal'));
        redirect(admin_url('hr/appraisals'));
    }

    /* -------------------------------------------------------------- Exits */

    public function exits()
    {
        $this->guard('hr_exits', 'view');

        // Dashboard "Open Exits" card lands here with ?status=open (pending +
        // cleared, i.e. not yet settled). Also accept a single status value.
        $status = $this->input->get('status');
        $data['active_status'] = in_array($status, ['open', 'pending', 'cleared', 'settled'], true) ? $status : '';

        $exits = $this->hr_model->get_exits();
        if ($data['active_status'] === 'open') {
            $exits = array_values(array_filter($exits, function ($x) {
                return in_array($x['status'], ['pending', 'cleared'], true);
            }));
        } elseif ($data['active_status'] !== '') {
            $exits = array_values(array_filter($exits, function ($x) use ($data) {
                return $x['status'] === $data['active_status'];
            }));
        }

        $data['title']     = _l('hr_exits');
        $data['exits']     = $exits;
        // include inactive so editing a settled exit still shows the employee
        $data['employees'] = $this->hr_model->get_employees(true);

        $this->load->view('hr/exits', $data);
    }

    public function save_exit()
    {
        $this->guard('hr_exits', 'edit');
        if (!$this->input->post()) {
            show_404();
        }
        $id        = $this->input->post('id') ?: null;
        $clearance = $this->input->post('clearance') ?: [];

        $this->hr_model->save_exit([
            'staff_id'          => (int) $this->input->post('staff_id'),
            'exit_type'         => $this->input->post('exit_type'),
            'notice_date'       => $this->input->post('notice_date') ?: null,
            'last_working_day'  => $this->input->post('last_working_day') ?: null,
            'reason'            => $this->input->post('reason'),
            'clearance'         => json_encode(array_values($clearance)),
            'settlement_amount' => (float) $this->input->post('settlement_amount'),
            'settlement_note'   => $this->input->post('settlement_note'),
            'status'            => $this->input->post('status') ?: 'pending',
        ], $id);

        set_alert('success', $id ? _l('updated_successfully', 'Exit record') : _l('added_successfully', 'Exit record'));
        redirect(admin_url('hr/exits'));
    }

    public function delete_exit($id)
    {
        $this->guard('hr_exits', 'delete');
        $this->hr_model->delete_exit((int) $id);
        set_alert('success', _l('deleted', 'Exit record'));
        redirect(admin_url('hr/exits'));
    }

    /* ------------------------------------------------------------ Notices */

    public function notices()
    {
        $this->guard('hr_notices', 'view');

        $this->load->model('roles_model');
        $data['title']     = _l('hr_notices');
        $data['notices']   = $this->hr_model->get_notices();
        $data['roles']     = $this->roles_model->get();
        $data['employees'] = $this->hr_model->get_active_employees();

        $this->load->view('hr/notices', $data);
    }

    public function save_notice()
    {
        $id = (int) $this->input->post('id');
        $this->guard('hr_notices', $id ? 'edit' : 'create');
        if (!$this->input->post()) {
            show_404();
        }

        $audience = $this->input->post('audience_type');
        if (!in_array($audience, ['all', 'roles', 'employees'], true)) {
            $audience = 'all';
        }

        $data = [
            'title'         => trim((string) $this->input->post('title')),
            'message'       => trim((string) $this->input->post('message', false)),
            'audience_type' => $audience,
            'role_ids'      => $audience === 'roles' ? implode(',', array_map('intval', $this->input->post('role_ids') ?: [])) : null,
            'staff_ids'     => $audience === 'employees' ? implode(',', array_map('intval', $this->input->post('staff_ids') ?: [])) : null,
            'priority'      => $this->input->post('priority') === 'high' ? 'high' : 'normal',
            'start_date'    => $this->input->post('start_date') ?: null,
            'end_date'      => $this->input->post('end_date') ?: null,
            'active'        => $this->input->post('active') ? 1 : 0,
        ];

        if ($data['title'] === '' || $data['message'] === '') {
            set_alert('warning', 'Please enter a title and message.');
            redirect(admin_url('hr/notices'));
        }

        $this->hr_model->save_notice($data, $id ?: null);
        set_alert('success', _l($id ? 'updated_successfully' : 'added_successfully', _l('hr_notices')));
        redirect(admin_url('hr/notices'));
    }

    public function delete_notice($id)
    {
        $this->guard('hr_notices', 'delete');
        $this->hr_model->delete_notice((int) $id);
        set_alert('success', _l('deleted', _l('hr_notices')));
        redirect(admin_url('hr/notices'));
    }

    /* ------------------------------------------- Suggestions / Feedback (mgmt) */

    public function feedback()
    {
        $this->guard('hr_feedback', 'view');

        $status = $this->input->get('status');
        $where  = [];
        if ($status && array_key_exists($status, hr_feedback_statuses())) {
            $where['f.status'] = $status;
        }

        $data['title']    = _l('hr_feedback');
        $data['status']   = $status;
        $data['feedback'] = $this->hr_model->get_feedback($where);
        $data['counts']   = $this->hr_model->feedback_status_counts();

        $this->load->view('hr/feedback', $data);
    }

    public function reply_feedback()
    {
        $this->guard('hr_feedback', 'edit');
        if (!$this->input->post()) {
            show_404();
        }
        $id  = (int) $this->input->post('id');
        $row = $this->hr_model->get_feedback_row($id);
        if (!$row) {
            show_404();
        }

        $status = $this->input->post('status');
        if (!array_key_exists($status, hr_feedback_statuses())) {
            $status = $row['status'];
        }
        $reply         = trim((string) $this->input->post('admin_reply'));
        $reply_changed = $reply !== (string) ($row['admin_reply'] ?? '') && $reply !== '';

        $update = ['status' => $status, 'admin_reply' => $reply !== '' ? $reply : null];
        if ($reply_changed) {
            $update['replied_by'] = get_staff_user_id();
            $update['replied_at'] = date('Y-m-d H:i:s');
        }
        $this->hr_model->update_feedback($id, $update);

        // Notify the submitter when management posts a new reply.
        if ($reply_changed && (int) $row['staff_id'] !== get_staff_user_id()) {
            add_notification([
                'description'     => 'hr_feedback_reply_notification',
                'touserid'        => (int) $row['staff_id'],
                'fromcompany'     => 1,
                'fromuserid'      => 0,
                'link'            => 'hr/myhr/feedback',
                'additional_data' => serialize([$row['subject']]),
            ]);
            pusher_trigger_notification([(int) $row['staff_id']]);
        }

        set_alert('success', 'Feedback updated.');
        redirect(admin_url('hr/feedback'));
    }

    public function delete_feedback($id)
    {
        $this->guard('hr_feedback', 'delete');
        $this->hr_model->delete_feedback((int) $id);
        set_alert('success', _l('deleted', _l('hr_feedback')));
        redirect(admin_url('hr/feedback'));
    }

    /* ----------------------------------------------------------- Benefits */

    public function benefits()
    {
        $this->guard('hr_benefits', 'view');

        $this->load->model('roles_model');
        $data['title']     = _l('hr_benefits');
        $data['benefits']  = $this->hr_model->get_benefits();
        $data['roles']     = $this->roles_model->get();
        $data['employees'] = $this->hr_model->get_active_employees();

        $this->load->view('hr/benefits', $data);
    }

    public function save_benefit()
    {
        $this->guard('hr_benefits', $this->input->post('id') ? 'edit' : 'create');
        if (!$this->input->post()) {
            show_404();
        }

        $type       = $this->input->post('benefit_type') === 'vesting' ? 'vesting' : 'standard';
        $applies_to = in_array($this->input->post('applies_to'), ['all', 'roles', 'employees'], true)
            ? $this->input->post('applies_to') : 'all';

        $save = [
            'name'          => $this->input->post('name'),
            'description'   => $this->input->post('description'),
            'benefit_type'  => $type,
            'vesting_years' => $type === 'vesting' ? (float) $this->input->post('vesting_years') : 0,
            'value_label'   => $this->input->post('value_label'),
            'icon'          => $this->input->post('icon') ?: 'fa-gift',
            'color'         => $this->input->post('color') ?: '#4f46e5',
            'applies_to'    => $applies_to,
            'role_ids'      => $applies_to === 'roles' ? json_encode(array_map('intval', $this->input->post('role_ids') ?: [])) : null,
            'staff_ids'     => $applies_to === 'employees' ? json_encode(array_map('intval', $this->input->post('staff_ids') ?: [])) : null,
            'is_active'     => $this->input->post('is_active') ? 1 : 0,
            'sort_order'    => (int) $this->input->post('sort_order'),
        ];

        $id = $this->input->post('id') ?: null;
        $this->hr_model->save_benefit($save, $id);
        set_alert('success', $id ? _l('updated_successfully', _l('hr_benefits')) : _l('added_successfully', _l('hr_benefits')));
        redirect(admin_url('hr/benefits'));
    }

    public function delete_benefit($id)
    {
        $this->guard('hr_benefits', 'delete');
        $this->hr_model->delete_benefit((int) $id);
        set_alert('success', _l('deleted', _l('hr_benefits')));
        redirect(admin_url('hr/benefits'));
    }

    /**
     * Quick show/hide toggle (AJAX) for the benefit cards — flips is_active.
     */
    public function toggle_benefit($id)
    {
        $this->guard('hr_benefits', 'edit');
        $benefit = $this->hr_model->get_benefit((int) $id);
        if (!$benefit) {
            echo json_encode(['success' => false]);

            return;
        }
        $new = $benefit['is_active'] ? 0 : 1;
        $this->hr_model->save_benefit(['is_active' => $new], (int) $id);
        echo json_encode(['success' => true, 'is_active' => $new]);
    }

    /* -------------------------------------------------------------- Perks */

    /**
     * Office perks / supplies procurement checklist (pantry snacks, beverages,
     * office-maintenance items, ...). HR lists things to order and tracks each
     * from "to order" → "ordered" → "received".
     */
    public function perks()
    {
        $this->guard('hr_perks', 'view');

        $status   = $this->input->get('status');
        $category = $this->input->get('category');
        $filters  = [];
        if ($status && array_key_exists($status, hr_perk_statuses())) {
            $filters['status'] = $status;
        }
        if ($category) {
            $filters['category'] = $category;
        }

        $data['title']      = _l('hr_perks');
        $data['status']     = $status;
        $data['category']   = $category;
        $data['items']      = $this->hr_model->get_perk_items($filters);
        $data['counts']     = $this->hr_model->perk_status_counts();
        $data['categories'] = hr_perk_categories();
        $data['statuses']   = hr_perk_statuses();
        $data['priorities'] = hr_perk_priorities();
        $data['employees']  = $this->hr_model->get_active_employees();

        $this->load->view('hr/perks', $data);
    }

    public function save_perk_item()
    {
        $id = $this->input->post('id') ?: null;
        $this->guard('hr_perks', $id ? 'edit' : 'create');
        if (!$this->input->post()) {
            show_404();
        }

        $priority = in_array($this->input->post('priority'), ['low', 'medium', 'high'], true)
            ? $this->input->post('priority') : 'medium';
        $status = array_key_exists($this->input->post('status'), hr_perk_statuses())
            ? $this->input->post('status') : 'pending';
        $category = trim((string) $this->input->post('category')) ?: 'Other';
        $needed_by = $this->input->post('needed_by');

        $save = [
            'title'       => trim((string) $this->input->post('title')),
            'category'    => $category,
            'quantity'    => trim((string) $this->input->post('quantity')) ?: null,
            'priority'    => $priority,
            'status'      => $status,
            'assigned_to' => (int) $this->input->post('assigned_to') ?: null,
            'needed_by'   => $needed_by ?: null,
            'notes'       => trim((string) $this->input->post('notes')) ?: null,
            'sort_order'  => (int) $this->input->post('sort_order'),
        ];

        if ($save['title'] === '') {
            set_alert('warning', _l('hr_perk_title_required'));
            redirect(admin_url('hr/perks'));
        }

        if (!$id) {
            $save['created_by'] = get_staff_user_id();
        }

        $saved_id = $this->hr_model->save_perk_item($save, $id);
        // Stamp ordered_at / received_at to match the chosen status without
        // clobbering timestamps already recorded on a prior transition.
        $this->hr_model->set_perk_status($saved_id, $status);
        set_alert('success', $id ? _l('updated_successfully', _l('hr_perks')) : _l('added_successfully', _l('hr_perks')));
        redirect(admin_url('hr/perks'));
    }

    public function delete_perk_item($id)
    {
        $this->guard('hr_perks', 'delete');
        $this->hr_model->delete_perk_item((int) $id);
        set_alert('success', _l('deleted', _l('hr_perks')));
        redirect(admin_url('hr/perks'));
    }

    /**
     * AJAX: advance/set a perk item's status (checklist tick). Returns the saved
     * status plus a rotated CSRF hash.
     */
    public function update_perk_status()
    {
        $this->guard('hr_perks', 'edit');
        if (!$this->input->is_ajax_request() || !$this->input->post()) {
            show_404();
        }
        $status = $this->hr_model->set_perk_status(
            (int) $this->input->post('id'),
            (string) $this->input->post('status')
        );
        echo json_encode([
            'success'                              => $status !== null,
            'status'                               => $status,
            'counts'                               => $this->hr_model->perk_status_counts(),
            $this->security->get_csrf_token_name() => $this->security->get_csrf_hash(),
        ]);
    }

    public function clear_received_perks()
    {
        $this->guard('hr_perks', 'delete');
        $deleted = $this->hr_model->clear_received_perks();
        set_alert('success', $deleted . ' ' . _l('hr_perk_received_cleared'));
        redirect(admin_url('hr/perks'));
    }

    /* ----------------------------------------------------- Employee Memos */

    public function memos()
    {
        $this->guard('hr_memos', 'view');

        $status = $this->input->get('status');
        $where  = [];
        if ($status && array_key_exists($status, hr_memo_statuses())) {
            $where['m.status'] = $status;
        }

        $data['title']        = _l('hr_memos');
        $data['active_status'] = $status;
        $data['memos']        = $this->hr_model->get_memos($where);
        $data['counts']       = $this->hr_model->memo_status_counts();
        $data['employees']    = $this->hr_model->get_active_employees();

        $this->load->view('hr/memos', $data);
    }

    public function memo($id)
    {
        $this->guard('hr_memos', 'view');
        $memo = $this->hr_model->get_memo((int) $id);
        if (!$memo) {
            show_404();
        }

        $data['title'] = $memo['subject'];
        $data['memo']  = $memo;

        $this->load->view('hr/memo', $data);
    }

    public function save_memo()
    {
        $id = (int) $this->input->post('id');
        $this->guard('hr_memos', $id ? 'edit' : 'create');
        if (!$this->input->post()) {
            show_404();
        }

        $staff_id = (int) $this->input->post('staff_id');
        $type     = $this->input->post('memo_type');
        $severity = $this->input->post('severity');
        if (!array_key_exists($type, hr_memo_types())) {
            $type = 'misconduct';
        }
        if (!array_key_exists($severity, hr_memo_severities())) {
            $severity = 'medium';
        }
        $subject = trim((string) $this->input->post('subject'));
        if ($staff_id <= 0 || $subject === '') {
            set_alert('warning', 'Please choose an employee and enter a subject.');
            redirect(admin_url('hr/memos'));
        }

        $data = [
            'staff_id'        => $staff_id,
            'memo_type'       => $type,
            'severity'        => $severity,
            'subject'         => mb_substr($subject, 0, 191),
            'description'     => $this->input->post('description', false),
            'incident_date'   => $this->input->post('incident_date') ?: null,
            'action_taken'    => $this->input->post('action_taken', false),
            'penalty_amount'  => (float) $this->input->post('penalty_amount'),
            'suspension_from' => $type === 'suspension' ? ($this->input->post('suspension_from') ?: null) : null,
            'suspension_to'   => $type === 'suspension' ? ($this->input->post('suspension_to') ?: null) : null,
        ];

        // Optional attachment (evidence / signed letter).
        if (!empty($_FILES['attachment']['name'])) {
            $ext = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, hr_memo_attachment_allowed(), true)) {
                set_alert('danger', 'Attachment type not allowed. Use PDF, image or Word.');
                redirect(admin_url('hr/memos'));
            }
            $dir = hr_memo_upload_dir($staff_id);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $filename = uniqid('memo_') . '.' . $ext;
            if (move_uploaded_file($_FILES['attachment']['tmp_name'], $dir . $filename)) {
                $data['attachment'] = $filename;
            }
        }

        $saved_id = $this->hr_model->save_memo($data, $id ?: null);

        // Notify the employee to review & acknowledge (new memos only).
        if (!$id) {
            $type_label = hr_memo_types()[$type]['label'];
            add_notification([
                'description'     => 'hr_memo_issued_notification',
                'touserid'        => $staff_id,
                'fromcompany'     => 1,
                'fromuserid'      => 0,
                'link'            => 'hr/myhr/memos',
                'additional_data' => serialize([$type_label, $subject]),
            ]);
            pusher_trigger_notification([$staff_id]);
        }

        set_alert('success', $id ? _l('updated_successfully', _l('hr_memo')) : _l('added_successfully', _l('hr_memo')));
        redirect(admin_url('hr/memo/' . $saved_id));
    }

    public function delete_memo($id)
    {
        $this->guard('hr_memos', 'delete');
        $this->hr_model->delete_memo((int) $id);
        set_alert('success', _l('deleted', _l('hr_memo')));
        redirect(admin_url('hr/memos'));
    }

    /**
     * Stream a memo attachment. Shared by the manager view and the employee's
     * own acknowledgement screen (ownership is enforced by the caller path).
     */
    public function memo_attachment($id)
    {
        $memo = $this->hr_model->get_memo((int) $id);
        if (!$memo) {
            show_404();
        }
        // Manager (hr_memos view) OR the employee the memo is about.
        $is_owner = (int) $memo['staff_id'] === (int) get_staff_user_id();
        if (!$is_owner && !has_permission('hr_memos', '', 'view') && !is_admin()) {
            access_denied('hr_memos');
        }
        if (empty($memo['attachment'])) {
            show_404();
        }
        $path = hr_memo_upload_dir($memo['staff_id']) . $memo['attachment'];
        if (!file_exists($path)) {
            show_404();
        }
        $ext   = strtolower(pathinfo($memo['attachment'], PATHINFO_EXTENSION));
        $mimes = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
        while (ob_get_level()) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header_remove('Content-Security-Policy');
            header_remove('Content-Security-Policy-Report-Only');
            header_remove('X-Frame-Options');
            if (isset($mimes[$ext])) {
                header('Content-Type: ' . $mimes[$ext]);
                header('Content-Disposition: inline; filename="memo.' . $ext . '"');
            } else {
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="memo.' . $ext . '"');
            }
            header('Content-Length: ' . filesize($path));
            header('X-Content-Type-Options: nosniff');
        }
        readfile($path);
        exit;
    }

    /* ------------------------------------------------------- Onboarding */

    public function onboarding()
    {
        $this->guard('hr_onboarding', 'view');

        $data['title']       = _l('hr_onboarding');
        $data['onboardings'] = $this->hr_model->get_onboardings();
        $data['templates']   = $this->hr_model->get_onboarding_templates(true);
        // Employees who do not yet have an onboarding record (candidates to start).
        $existing = array_column($data['onboardings'], 'staff_id');
        $data['employees'] = array_values(array_filter($this->hr_model->get_active_employees(), function ($e) use ($existing) {
            return !in_array($e['staffid'], $existing);
        }));
        $data['phases'] = hr_onboarding_phases();

        $this->load->view('hr/onboarding', $data);
    }

    public function start_onboarding()
    {
        $this->guard('hr_onboarding', 'create');
        if (!$this->input->post()) {
            show_404();
        }
        $staff_id    = (int) $this->input->post('staff_id');
        $template_id = (int) $this->input->post('template_id');
        if ($staff_id <= 0 || $template_id <= 0) {
            set_alert('warning', 'Please choose an employee and a template.');
            redirect(admin_url('hr/onboarding'));
        }
        $id = $this->hr_model->start_onboarding(
            $staff_id,
            $template_id,
            $this->input->post('start_date') ?: null,
            $this->input->post('target_date') ?: null
        );
        if (!$id) {
            set_alert('danger', 'Could not start onboarding (template not found).');
            redirect(admin_url('hr/onboarding'));
        }
        set_alert('success', 'Onboarding started.');
        redirect(admin_url('hr/onboarding_board/' . $id));
    }

    public function onboarding_board($id)
    {
        $this->guard('hr_onboarding', 'view');
        $ob = $this->hr_model->get_onboarding((int) $id);
        if (!$ob) {
            show_404();
        }
        $data['title']     = 'Onboarding — ' . trim($ob['firstname'] . ' ' . $ob['lastname']);
        $data['ob']        = $ob;
        $data['items']     = $this->hr_model->get_onboarding_items((int) $id);
        $data['phases']    = hr_onboarding_phases();
        $data['statuses']  = hr_onboarding_item_statuses();
        $data['employees'] = $this->hr_model->get_active_employees();

        $this->load->view('hr/onboarding_board', $data);
    }

    public function update_onboarding_status($id)
    {
        $this->guard('hr_onboarding', 'edit');
        $status = $this->input->post('status');
        if (in_array($status, ['in_progress', 'completed', 'cancelled'], true)) {
            $update = ['status' => $status];
            $update['completed_at'] = $status === 'completed' ? date('Y-m-d H:i:s') : null;
            $this->hr_model->update_onboarding((int) $id, $update);
        }
        set_alert('success', _l('updated_successfully', _l('hr_onboarding')));
        redirect(admin_url('hr/onboarding_board/' . (int) $id));
    }

    public function delete_onboarding($id)
    {
        $this->guard('hr_onboarding', 'delete');
        $this->hr_model->delete_onboarding((int) $id);
        set_alert('success', _l('deleted', _l('hr_onboarding')));
        redirect(admin_url('hr/onboarding'));
    }

    public function save_onboarding_item()
    {
        $this->guard('hr_onboarding', 'edit');
        if (!$this->input->post()) {
            show_404();
        }
        $onboarding_id = (int) $this->input->post('onboarding_id');
        $item_id       = (int) $this->input->post('id');
        $phase         = $this->input->post('phase');
        $data = [
            'title'       => mb_substr(trim((string) $this->input->post('title')), 0, 191),
            'description' => mb_substr(trim((string) $this->input->post('description')), 0, 255),
            'phase'       => array_key_exists($phase, hr_onboarding_phases()) ? $phase : 'first_day',
            'assigned_to' => (int) $this->input->post('assigned_to') ?: null,
            'due_date'    => $this->input->post('due_date') ?: null,
        ];
        if ($data['title'] === '') {
            set_alert('warning', 'Please enter a task title.');
            redirect(admin_url('hr/onboarding_board/' . $onboarding_id));
        }

        if ($item_id) {
            $this->hr_model->update_onboarding_item($item_id, $data);
        } else {
            $new_id = $this->hr_model->add_onboarding_item($onboarding_id, $data);
            // Notify an assignee that they own a task.
            if ($data['assigned_to']) {
                $ob = $this->hr_model->get_onboarding($onboarding_id);
                add_notification([
                    'description'     => 'hr_onboarding_task_notification',
                    'touserid'        => $data['assigned_to'],
                    'fromcompany'     => 1,
                    'fromuserid'      => 0,
                    'link'            => 'hr/onboarding_board/' . $onboarding_id,
                    'additional_data' => serialize([$data['title'], trim(($ob['firstname'] ?? '') . ' ' . ($ob['lastname'] ?? ''))]),
                ]);
                pusher_trigger_notification([$data['assigned_to']]);
            }
        }
        set_alert('success', _l('updated_successfully', 'Task'));
        redirect(admin_url('hr/onboarding_board/' . $onboarding_id));
    }

    public function onboarding_item_status($id)
    {
        $this->guard('hr_onboarding', 'edit');
        $this->hr_model->set_onboarding_item_status((int) $id, $this->input->post('status'));
        $item = $this->hr_model->get_onboarding_item((int) $id);
        if ($this->input->post('ajax')) {
            echo json_encode(['success' => true]);

            return;
        }
        redirect(admin_url('hr/onboarding_board/' . ($item ? $item['onboarding_id'] : '')));
    }

    public function delete_onboarding_item($id)
    {
        $this->guard('hr_onboarding', 'edit');
        $item = $this->hr_model->get_onboarding_item((int) $id);
        $this->hr_model->delete_onboarding_item((int) $id);
        if ($item) {
            $this->hr_model->refresh_onboarding_completion((int) $item['onboarding_id']);
        }
        set_alert('success', _l('deleted', 'Task'));
        redirect(admin_url('hr/onboarding_board/' . ($item ? $item['onboarding_id'] : '')));
    }

    /* ------------------------------------------- Onboarding templates */

    public function onboarding_templates()
    {
        $this->guard('hr_onboarding', 'edit');
        $this->load->model('roles_model');

        $data['title']     = 'Onboarding Templates';
        $data['templates'] = $this->hr_model->get_onboarding_templates();
        $data['phases']    = hr_onboarding_phases();

        // Pre-load items for each template for inline editing.
        foreach ($data['templates'] as &$t) {
            $t['items'] = $this->hr_model->get_template_items($t['id']);
        }
        unset($t);

        $this->load->view('hr/onboarding_templates', $data);
    }

    public function save_onboarding_template()
    {
        $this->guard('hr_onboarding', 'edit');
        if (!$this->input->post()) {
            show_404();
        }
        $id   = (int) $this->input->post('id');
        $name = trim((string) $this->input->post('name'));
        if ($name === '') {
            set_alert('warning', 'Please enter a template name.');
            redirect(admin_url('hr/onboarding_templates'));
        }
        $tpl_id = $this->hr_model->save_onboarding_template([
            'name'        => mb_substr($name, 0, 150),
            'description' => mb_substr(trim((string) $this->input->post('description')), 0, 255),
            'is_active'   => $this->input->post('is_active') ? 1 : 0,
        ], $id ?: null);

        // Items arrive as parallel arrays.
        $titles  = $this->input->post('item_title') ?: [];
        $descs   = $this->input->post('item_description') ?: [];
        $phases  = $this->input->post('item_phase') ?: [];
        $offsets = $this->input->post('item_offset') ?: [];
        $items   = [];
        foreach ($titles as $i => $title) {
            $items[] = [
                'title'           => $title,
                'description'     => $descs[$i] ?? '',
                'phase'           => $phases[$i] ?? 'first_day',
                'due_offset_days' => $offsets[$i] ?? 0,
            ];
        }
        $this->hr_model->set_template_items($tpl_id, $items);

        set_alert('success', _l('updated_successfully', 'Template'));
        redirect(admin_url('hr/onboarding_templates'));
    }

    public function delete_onboarding_template($id)
    {
        $this->guard('hr_onboarding', 'delete');
        $this->hr_model->delete_onboarding_template((int) $id);
        set_alert('success', _l('deleted', 'Template'));
        redirect(admin_url('hr/onboarding_templates'));
    }

    /* --------------------------------------------------------- Interviews */

    public function interviews()
    {
        $this->guard('hr_interviews', 'view');
        $this->load->model('departments_model');

        $status = $this->input->get('status');
        $where  = [];
        if ($status && array_key_exists($status, hr_interview_statuses())) {
            $where['iv.status'] = $status;
        }

        $data['title']         = _l('hr_interviews');
        $data['active_status'] = $status;
        $data['interviews']    = $this->hr_model->get_interviews($where);
        $data['counts']        = $this->hr_model->interview_status_counts();
        $data['designations']  = $this->hr_model->get_designations(true);
        $data['departments']   = $this->departments_model->get();
        $data['employees']     = $this->hr_model->get_active_employees();
        $data['zoom_ready']    = hr_zoom_configured();
        $data['meet_ready']    = hr_google_meet_configured();

        $this->load->view('hr/interviews', $data);
    }

    public function interview($id)
    {
        $this->guard('hr_interviews', 'view');
        $iv = $this->hr_model->get_interview((int) $id);
        if (!$iv) {
            show_404();
        }
        $interviewers = array_filter(array_map('intval', explode(',', (string) $iv['interviewer_ids'])));
        $names        = [];
        if (!empty($interviewers)) {
            foreach ($this->db->where_in('staffid', $interviewers)
                ->get(db_prefix() . 'staff')->result_array() as $s) {
                $names[(int) $s['staffid']] = trim($s['firstname'] . ' ' . $s['lastname']);
            }
        }

        $data['title']              = $iv['candidate_name'];
        $data['iv']                 = $iv;
        $data['interviewer_names']  = $names;

        $this->load->view('hr/interview', $data);
    }

    public function save_interview()
    {
        $id = (int) $this->input->post('id');
        $this->guard('hr_interviews', $id ? 'edit' : 'create');
        if (!$this->input->post()) {
            show_404();
        }

        $name = trim((string) $this->input->post('candidate_name'));
        if ($name === '') {
            set_alert('warning', 'Please enter the candidate name.');
            redirect(admin_url('hr/interviews'));
        }
        $platform = $this->input->post('platform');
        if (!array_key_exists($platform, hr_interview_platforms())) {
            $platform = 'google_meet';
        }
        $date = $this->input->post('scheduled_date');
        $time = $this->input->post('scheduled_time');
        $scheduled_at = ($date && $time) ? ($date . ' ' . $time . ':00') : ($date ? $date . ' 09:00:00' : null);

        $interviewer_ids = implode(',', array_map('intval', $this->input->post('interviewer_ids') ?: []));

        $data = [
            'candidate_name'   => mb_substr($name, 0, 150),
            'candidate_email'  => $this->input->post('candidate_email') ?: null,
            'candidate_phone'  => $this->input->post('candidate_phone') ?: null,
            'position'         => $this->input->post('position') ?: null,
            'designation_id'   => (int) $this->input->post('designation_id') ?: null,
            'department_id'    => (int) $this->input->post('department_id') ?: null,
            'round_no'         => max(1, (int) $this->input->post('round_no')),
            'round_name'       => $this->input->post('round_name') ?: null,
            'platform'         => $platform,
            'scheduled_at'     => $scheduled_at,
            'duration_minutes' => max(5, (int) $this->input->post('duration_minutes') ?: 30),
            'timezone'         => $this->input->post('timezone') ?: date_default_timezone_get(),
            'location'         => $this->input->post('location') ?: null,
            'interviewer_ids'  => $interviewer_ids,
            'meeting_url'      => $this->input->post('meeting_url') ?: null,
            'notes'            => $this->input->post('notes', false),
        ];

        // Optional resume upload.
        if (!empty($_FILES['resume']['name'])) {
            $ext = strtolower(pathinfo($_FILES['resume']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'], true)) {
                $dir = hr_interview_upload_dir();
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                $filename = uniqid('resume_') . '.' . $ext;
                if (move_uploaded_file($_FILES['resume']['tmp_name'], $dir . $filename)) {
                    $data['resume_file'] = $filename;
                }
            }
        }

        // Auto-create the online meeting when the platform is online, a link is
        // not already supplied, and the provider is configured. A failure is
        // surfaced but does not block saving (HR can add a link manually).
        $meeting_msg = '';
        $existing = $id ? $this->hr_model->get_interview($id) : null;
        $needs_meeting = in_array($platform, ['zoom', 'google_meet'], true)
            && empty($data['meeting_url'])
            && $scheduled_at
            && (!$existing || $existing['platform'] !== $platform || empty($existing['meeting_url']));

        if ($needs_meeting) {
            $configured = $platform === 'zoom' ? hr_zoom_configured() : hr_google_meet_configured();
            if ($configured) {
                $attendees = [];
                if ($data['candidate_email']) {
                    $attendees[] = $data['candidate_email'];
                }
                $res = hr_interview_create_meeting($platform, [
                    'topic'      => 'Interview: ' . $name . ($data['position'] ? ' — ' . $data['position'] : ''),
                    'start_time' => $scheduled_at,
                    'duration'   => $data['duration_minutes'],
                    'timezone'   => $data['timezone'],
                    'agenda'     => 'Interview round ' . $data['round_no'] . ($data['round_name'] ? ' (' . $data['round_name'] . ')' : ''),
                    'attendees'  => $attendees,
                ]);
                if ($res['ok'] && $res['url']) {
                    $data['meeting_url']         = $res['url'];
                    $data['meeting_id']          = $res['id'];
                    $data['meeting_password']    = $res['password'];
                    $data['provider_meeting_id'] = $res['id'];
                    $data['provider_host_url']   = $res['host_url'];
                    $meeting_msg = ' ' . hr_interview_platforms()[$platform]['label'] . ' meeting created.';
                } else {
                    $meeting_msg = ' (Could not auto-create the meeting: ' . $res['error'] . ' — add a link manually.)';
                }
            } else {
                $meeting_msg = ' (' . hr_interview_platforms()[$platform]['label'] . ' is not configured in HR Settings — add a link manually.)';
            }
        }

        $saved_id = $this->hr_model->save_interview($data, $id ?: null);

        // Notify interviewers + optionally email everyone the invite.
        $iv_full = $this->hr_model->get_interview($saved_id);
        $this->notify_interviewers($iv_full);
        if (get_option('hr_interview_send_invites') === '1') {
            $this->send_interview_invites($iv_full);
        }
        // Omni Messaging path — reaches the candidate over SMS / WhatsApp too,
        // and is independent of the module's own SMTP invite above.
        $this->fire_interview_hook($iv_full);

        set_alert(strpos($meeting_msg, 'Could not') !== false || strpos($meeting_msg, 'not configured') !== false ? 'warning' : 'success',
            ($id ? _l('updated_successfully', _l('hr_interview')) : _l('added_successfully', _l('hr_interview'))) . $meeting_msg);
        redirect(admin_url('hr/interview/' . $saved_id));
    }

    /**
     * Save interview outcome (status + feedback + rating + recommendation).
     */
    public function interview_feedback($id)
    {
        $this->guard('hr_interviews', 'edit');
        if (!$this->input->post()) {
            show_404();
        }
        $status = $this->input->post('status');
        $rec    = $this->input->post('recommendation');
        $this->hr_model->save_interview([
            'status'         => array_key_exists($status, hr_interview_statuses()) ? $status : 'completed',
            'rating'         => max(0, min(5, (float) $this->input->post('rating'))),
            'recommendation' => array_key_exists($rec, hr_interview_recommendations()) ? $rec : null,
            'feedback'       => $this->input->post('feedback', false),
        ], (int) $id);
        set_alert('success', _l('updated_successfully', 'Interview outcome'));
        redirect(admin_url('hr/interview/' . (int) $id));
    }

    public function delete_interview($id)
    {
        $this->guard('hr_interviews', 'delete');
        $this->hr_model->delete_interview((int) $id);
        set_alert('success', _l('deleted', _l('hr_interview')));
        redirect(admin_url('hr/interviews'));
    }

    public function interview_resume($id)
    {
        $this->guard('hr_interviews', 'view');
        $iv = $this->hr_model->get_interview((int) $id);
        if (!$iv || empty($iv['resume_file'])) {
            show_404();
        }
        $path = hr_interview_upload_dir() . $iv['resume_file'];
        if (!file_exists($path)) {
            show_404();
        }
        $this->load->helper('download');
        $ext  = pathinfo($iv['resume_file'], PATHINFO_EXTENSION);
        $safe = preg_replace('/[^\w\s\.\-]/u', '_', $iv['candidate_name']);
        force_download($safe . '_resume.' . $ext, file_get_contents($path));
    }

    /**
     * Re-send the meeting invite emails for an interview on demand.
     */
    public function resend_interview_invite($id)
    {
        $this->guard('hr_interviews', 'edit');
        $iv = $this->hr_model->get_interview((int) $id);
        if (!$iv) {
            show_404();
        }
        $sent = $this->send_interview_invites($iv);
        set_alert($sent ? 'success' : 'warning', $sent ? 'Invite emails sent.' : 'No valid recipients / SMTP not configured.');
        redirect(admin_url('hr/interview/' . (int) $id));
    }

    /**
     * Fire the interview hook. The recipient here is the CANDIDATE, not a staff
     * member, so {mobile_number} / {email} carry the candidate's details — the
     * default recipient type then reaches them with no configuration.
     *
     * @param  array|null $iv
     * @return void
     */
    protected function fire_interview_hook($iv)
    {
        if (empty($iv)) {
            return;
        }

        $interviewers = [];
        foreach (array_filter(array_map('intval', explode(',', (string) $iv['interviewer_ids']))) as $sid) {
            $interviewers[] = get_staff_full_name($sid);
        }

        hr_fire_hook('hr_interview_scheduled', [
            'candidate_name'     => (string) $iv['candidate_name'],
            'mobile_number'      => (string) ($iv['candidate_phone'] ?: ''),
            'email'              => (string) ($iv['candidate_email'] ?: ''),
            'position'           => (string) ($iv['position'] ?: ($iv['designation_name'] ?: '')),
            'round_no'           => (string) $iv['round_no'],
            'round_name'         => (string) ($iv['round_name'] ?: ''),
            'interview_datetime' => $iv['scheduled_at'] ? _dt($iv['scheduled_at']) : 'To be decided',
            'interview_mode'     => hr_interview_platforms()[$iv['platform']]['label'] ?? (string) $iv['platform'],
            'duration_minutes'   => (string) $iv['duration_minutes'],
            // An in-person round has a place, not a link — publish whichever
            // one the candidate actually needs under {meeting_link}.
            'meeting_link'       => (string) ($iv['platform'] === 'in_person' ? ($iv['location'] ?: '') : ($iv['meeting_url'] ?: '')),
            'meeting_id'         => (string) ($iv['meeting_id'] ?: ''),
            'meeting_password'   => (string) ($iv['meeting_password'] ?: ''),
            'interviewers'       => implode(', ', $interviewers),
        ]);
    }

    /**
     * Notify staff interviewers in-app that they are on an interview panel.
     */
    protected function notify_interviewers($iv)
    {
        $ids = array_filter(array_map('intval', explode(',', (string) $iv['interviewer_ids'])));
        if (empty($ids)) {
            return;
        }
        $when = $iv['scheduled_at'] ? _dt($iv['scheduled_at']) : 'TBD';
        foreach ($ids as $sid) {
            add_notification([
                'description'     => 'hr_interview_assigned_notification',
                'touserid'        => $sid,
                'fromcompany'     => 1,
                'fromuserid'      => 0,
                'link'            => 'hr/interview/' . $iv['id'],
                'additional_data' => serialize([$iv['candidate_name'], $when]),
            ]);
        }
        pusher_trigger_notification($ids);
    }

    /**
     * Email the meeting details to the candidate and each interviewer. Returns
     * true if at least one email was dispatched.
     */
    protected function send_interview_invites($iv)
    {
        $platform_label = hr_interview_platforms()[$iv['platform']]['label'] ?? $iv['platform'];
        $when   = $iv['scheduled_at'] ? _dt($iv['scheduled_at']) : 'To be decided';
        $join   = $iv['meeting_url'] ? '<p><strong>Join link:</strong> <a href="' . html_escape($iv['meeting_url']) . '">' . html_escape($iv['meeting_url']) . '</a></p>' : '';
        $pass   = $iv['meeting_password'] ? '<p><strong>Passcode:</strong> ' . html_escape($iv['meeting_password']) . '</p>' : '';
        $loc    = ($iv['platform'] === 'in_person' && $iv['location']) ? '<p><strong>Location:</strong> ' . html_escape($iv['location']) . '</p>' : '';
        $company = get_option('companyname') ?: 'Our Team';
        $sent    = false;

        // Candidate invite.
        if (!empty($iv['candidate_email'])) {
            $body = '<p>Dear ' . html_escape($iv['candidate_name']) . ',</p>'
                . '<p>Your interview' . ($iv['position'] ? ' for the position of <strong>' . html_escape($iv['position']) . '</strong>' : '') . ' has been scheduled.</p>'
                . '<p><strong>Date &amp; time:</strong> ' . html_escape($when) . '<br>'
                . '<strong>Mode:</strong> ' . html_escape($platform_label) . '<br>'
                . '<strong>Round:</strong> ' . (int) $iv['round_no'] . ($iv['round_name'] ? ' — ' . html_escape($iv['round_name']) : '') . '</p>'
                . $join . $pass . $loc
                . '<p>Please be available a few minutes early. We look forward to speaking with you.</p>'
                . '<p>Regards,<br>' . html_escape($company) . '</p>';
            $sent = hr_send_html_email($iv['candidate_email'], 'Interview scheduled — ' . $company, $body) || $sent;
        }

        // Interviewer copies.
        $ids = array_filter(array_map('intval', explode(',', (string) $iv['interviewer_ids'])));
        if (!empty($ids)) {
            foreach ($this->db->where_in('staffid', $ids)->get(db_prefix() . 'staff')->result_array() as $s) {
                if (empty($s['email'])) {
                    continue;
                }
                $body = '<p>Hi ' . html_escape($s['firstname']) . ',</p>'
                    . '<p>You are on the interview panel for <strong>' . html_escape($iv['candidate_name']) . '</strong>'
                    . ($iv['position'] ? ' (' . html_escape($iv['position']) . ')' : '') . '.</p>'
                    . '<p><strong>Date &amp; time:</strong> ' . html_escape($when) . '<br>'
                    . '<strong>Mode:</strong> ' . html_escape($platform_label) . '</p>'
                    . $join . $pass . $loc
                    . '<p><a href="' . admin_url('hr/interview/' . $iv['id']) . '">Open in HR</a></p>';
                $sent = hr_send_html_email($s['email'], 'Interview panel — ' . $iv['candidate_name'], $body) || $sent;
            }
        }

        if ($sent) {
            $this->hr_model->save_interview(['invite_sent' => 1], (int) $iv['id']);
        }

        return $sent;
    }

    /* ------------------------------------------------------------ Reports */

    public function reports()
    {
        $this->guard('hr_reports', 'view');

        $month = (int) ($this->input->get('month') ?: date('n'));
        $year  = (int) ($this->input->get('year') ?: date('Y'));
        $month = max(1, min(12, $month));
        $tab   = $this->input->get('tab') ?: 'attendance';

        $data['title']     = _l('hr_reports');
        $data['tab']       = $tab;
        $data['month']     = $month;
        $data['year']      = $year;
        $data['employees'] = $this->hr_model->get_active_employees();
        $data['statuses']  = hr_attendance_statuses();

        if ($tab === 'attendance') {
            $summary = $this->hr_model->get_attendance_summary($month, $year);
            $map     = [];
            foreach ($summary as $s) {
                $map[$s['staff_id']][$s['status']] = $s;
            }
            $data['summary'] = $map;
        } elseif ($tab === 'leave') {
            $data['leave_types'] = $this->hr_model->get_leave_types(true);
            $data['allocations'] = $this->hr_model->get_leave_allocations($year);
            $data['leave_used']  = $this->hr_model->get_leave_used($year);
        } elseif ($tab === 'payroll') {
            $this->guard_payroll();
            $data['runs'] = $this->hr_model->get_payroll_runs();
        } else { // headcount
            $data['departments'] = $this->hr_model->department_headcount();
            $data['by_type']     = $this->hr_model->headcount_by_type();
            $data['joins_exits'] = $this->hr_model->joins_exits_by_month();
        }

        $this->load->view('hr/reports', $data);
    }

    /* ----------------------------------------------------------- Settings */

    /**
     * Build an ESS override map from POST fields named
     * <prefix>_override[id] / _fields[id][] / _docs[id] / _checklist[id].
     * Only ids whose "override" box is checked are kept. Editable fields are
     * intersected with the curated whitelist. Shared by role (settings) and
     * employee (employee detail) override editors.
     */
    protected function collect_ess_overrides($prefix)
    {
        $override  = $this->input->post($prefix . '_override') ?: [];
        $fields    = $this->input->post($prefix . '_fields') ?: [];
        $docs      = $this->input->post($prefix . '_docs') ?: [];
        $checklist = $this->input->post($prefix . '_checklist') ?: [];

        $valid = array_keys(hr_self_field_options());
        $map   = [];
        foreach ($override as $id => $on) {
            $map[(string) (int) $id] = [
                'override'           => 1,
                'editable_fields'    => implode(',', array_values(array_intersect($fields[$id] ?? [], $valid))),
                'required_documents' => (string) ($docs[$id] ?? ''),
                'checklist_enabled'  => !empty($checklist[$id]) ? '1' : '0',
            ];
        }

        return $map;
    }

    /**
     * Save a single employee's ESS override (from the employee detail page).
     * Unchecking "override" removes their entry so they fall back to role/global.
     */
    public function save_ess_override($staff_id)
    {
        $this->guard('hr_employees', 'edit');
        if (!$this->input->post()) {
            show_404();
        }
        $staff_id  = (int) $staff_id;
        $map       = hr_ess_employee_config();
        $collected = $this->collect_ess_overrides('ess_emp');

        unset($map[(string) $staff_id]);
        if (isset($collected[(string) $staff_id])) {
            $map[(string) $staff_id] = $collected[(string) $staff_id];
        }
        update_option('hr_ess_employee_config', json_encode($map));

        set_alert('success', _l('settings_updated'));
        redirect(admin_url('hr/employee/' . $staff_id . '?tab=selfservice'));
    }

    public function settings()
    {
        if (!is_admin() && !has_permission('hr', '', 'edit')) {
            access_denied('hr');
        }

        if ($this->input->post()) {
            $options = [
                'hr_employee_code_prefix', 'hr_leave_year_start_month',
                'hr_default_week_off', 'hr_payroll_lop_basis', 'hr_doc_expiry_alert_days',
                'hr_self_editable_fields', 'hr_required_documents', 'hr_salary_payment_day',
                'hr_probation_alert_days',
            ];
            // checkbox-array options where "nothing checked" must save as empty
            $multi = ['hr_default_week_off', 'hr_self_editable_fields'];
            foreach ($options as $opt) {
                $value = $this->input->post($opt);
                if (in_array($opt, $multi)) {
                    $value = implode(',', $value ?: []);
                } elseif ($value === null) {
                    continue;
                } elseif (is_array($value)) {
                    $value = implode(',', $value);
                }
                update_option($opt, $value);
            }

            // role-wise weekly offs: only roles with "override" checked are
            // stored; every other role follows the global default
            $overrides = $this->input->post('role_override') ?: [];
            $role_days = $this->input->post('role_week_off') ?: [];
            $map       = [];
            foreach ($overrides as $role_id => $on) {
                $map[(string) (int) $role_id] = implode(',', array_map('intval', $role_days[$role_id] ?? []));
            }
            update_option('hr_role_week_off', json_encode($map));

            // global required-documents checklist on/off (list text is preserved)
            update_option('hr_required_documents_enabled', $this->input->post('hr_required_documents_enabled') ? '1' : '0');

            // birthday wishes feature on/off
            update_option('hr_birthday_wishes_enabled', $this->input->post('hr_birthday_wishes_enabled') ? '1' : '0');

            // show company logo on payslips
            update_option('hr_payslip_show_logo', $this->input->post('hr_payslip_show_logo') ? '1' : '0');

            // leave application rules (advance notice / backdating)
            update_option('hr_leave_min_notice_days', max(0, (int) $this->input->post('hr_leave_min_notice_days')));
            update_option('hr_leave_allow_backdated', $this->input->post('hr_leave_allow_backdated') ? '1' : '0');

            // multi-level leave approval chain. Each level targets a specific
            // user, a role (anyone in it), or "Anyone" (any leave approver).
            update_option('hr_leave_multilevel_enabled', $this->input->post('hr_leave_multilevel_enabled') ? '1' : '0');
            $chain_types  = $this->input->post('chain_type') ?: [];
            $chain_users  = $this->input->post('chain_user') ?: [];
            $chain_roles  = $this->input->post('chain_role') ?: [];
            $chain_labels = $this->input->post('chain_label') ?: [];
            $chain        = [];
            foreach ($chain_types as $i => $type) {
                $label = trim((string) ($chain_labels[$i] ?? ''));
                if ($type === 'user') {
                    $uid = (int) ($chain_users[$i] ?? 0);
                    if ($uid <= 0) {
                        continue; // a user level with no user picked is dropped
                    }
                    $chain[] = ['type' => 'user', 'user' => $uid, 'label' => $label];
                } elseif ($type === 'role') {
                    $chain[] = ['type' => 'role', 'role' => (int) ($chain_roles[$i] ?? 0), 'label' => $label];
                } else {
                    $chain[] = ['type' => 'any', 'label' => $label];
                }
            }
            update_option('hr_leave_approval_chain', json_encode($chain));

            // role-wise ESS overrides: only roles with "override" checked stored
            update_option('hr_ess_role_config', json_encode($this->collect_ess_overrides('ess_role')));

            // Interview integrations. Secrets (Zoom secret, Google SA JSON) are
            // only overwritten when a new value is supplied, so re-saving the
            // settings screen never wipes an existing credential.
            update_option('hr_interview_send_invites', $this->input->post('hr_interview_send_invites') ? '1' : '0');

            update_option('hr_zoom_enabled', $this->input->post('hr_zoom_enabled') ? '1' : '0');
            update_option('hr_zoom_account_id', trim((string) $this->input->post('hr_zoom_account_id')));
            update_option('hr_zoom_client_id', trim((string) $this->input->post('hr_zoom_client_id')));
            update_option('hr_zoom_host_email', trim((string) $this->input->post('hr_zoom_host_email')));
            $zoom_secret = trim((string) $this->input->post('hr_zoom_client_secret'));
            if ($zoom_secret !== '') {
                update_option('hr_zoom_client_secret', $zoom_secret);
            }

            update_option('hr_google_meet_enabled', $this->input->post('hr_google_meet_enabled') ? '1' : '0');
            update_option('hr_google_impersonate_email', trim((string) $this->input->post('hr_google_impersonate_email')));
            $google_json = trim((string) $this->input->post('hr_google_sa_json', false));
            if ($google_json !== '') {
                update_option('hr_google_sa_json', $google_json);
            }

            // Biometric attendance machine (e-timeoffice). The password is only
            // overwritten when a new value is supplied, so re-saving never wipes it.
            update_option('hr_etime_enabled', $this->input->post('hr_etime_enabled') ? '1' : '0');
            update_option('hr_etime_auto_sync', $this->input->post('hr_etime_auto_sync') ? '1' : '0');
            update_option('hr_etime_overwrite_manual', $this->input->post('hr_etime_overwrite_manual') ? '1' : '0');
            update_option('hr_etime_base_url', trim((string) $this->input->post('hr_etime_base_url')) ?: 'https://api.etimeoffice.com/api/');
            update_option('hr_etime_corporate_id', trim((string) $this->input->post('hr_etime_corporate_id')));
            update_option('hr_etime_username', trim((string) $this->input->post('hr_etime_username')));
            $etime_pass = (string) $this->input->post('hr_etime_password');
            if ($etime_pass !== '') {
                update_option('hr_etime_password', $etime_pass);
            }
            update_option('hr_etime_halfday_minutes', max(0, (int) $this->input->post('hr_etime_halfday_minutes')));
            $etime_window = (int) $this->input->post('hr_etime_sync_window_days');
            update_option('hr_etime_sync_window_days', (string) ($etime_window > 0 ? min(31, $etime_window) : 2));

            // Field attendance (punch in/out from outside the office).
            update_option('hr_field_enabled', $this->input->post('hr_field_enabled') ? '1' : '0');
            update_option('hr_field_require_location', $this->input->post('hr_field_require_location') ? '1' : '0');
            update_option('hr_field_require_photo', $this->input->post('hr_field_require_photo') ? '1' : '0');
            update_option('hr_field_auto_approve', $this->input->post('hr_field_auto_approve') ? '1' : '0');
            update_option('hr_field_update_attendance', $this->input->post('hr_field_update_attendance') ? '1' : '0');
            update_option('hr_field_overwrite_manual', $this->input->post('hr_field_overwrite_manual') ? '1' : '0');
            update_option('hr_field_reverse_geocode', $this->input->post('hr_field_reverse_geocode') ? '1' : '0');
            update_option('hr_field_notify_reviewers', $this->input->post('hr_field_notify_reviewers') ? '1' : '0');

            $geofence_mode = $this->input->post('hr_field_geofence_mode');
            update_option('hr_field_geofence_mode', in_array($geofence_mode, ['off', 'warn', 'block'], true) ? $geofence_mode : 'off');

            update_option('hr_field_max_accuracy_m', (string) max(0, (int) $this->input->post('hr_field_max_accuracy_m')));
            update_option('hr_field_halfday_minutes', (string) max(0, (int) $this->input->post('hr_field_halfday_minutes')));
            update_option('hr_field_min_gap_minutes', (string) max(0, min(120, (int) $this->input->post('hr_field_min_gap_minutes'))));

            set_alert('success', _l('settings_updated'));
            redirect(admin_url('hr/settings'));
        }

        $this->load->model('roles_model');
        $data['title'] = _l('hr_settings');
        $data['roles'] = $this->roles_model->get();
        // Active staff for the per-level "specific user" approver picker.
        $data['staff_members'] = $this->db->select('staffid, firstname, lastname')
            ->where('active', 1)->where('is_not_staff', 0)
            ->order_by('firstname', 'asc')
            ->get(db_prefix() . 'staff')->result_array();

        // Smart PDF document-templates installer (guarded so HR does not hard
        // depend on the module being installed).
        $data['smart_pdf_available']  = (has_permission('smart_pdf', '', 'create') || is_admin())
            && $this->db->table_exists(db_prefix() . 'smart_pdf_templates');
        $data['smart_pdf_tpl_count']  = $data['smart_pdf_available']
            ? (int) $this->db->count_all(db_prefix() . 'smart_pdf_templates')
            : 0;

        $this->load->view('hr/settings', $data);
    }

    /* ------------------------------------------------------- Designations */

    public function save_designation()
    {
        $this->guard('hr_employees', 'edit');
        if (!$this->input->post()) {
            show_404();
        }
        $id = $this->input->post('id') ?: null;
        $this->hr_model->save_designation([
            'name'          => $this->input->post('name'),
            'department_id' => (int) $this->input->post('department_id') ?: null,
            'role_id'       => (int) $this->input->post('role_id') ?: null,
            'is_active'     => $this->input->post('is_active') ? 1 : 0,
        ], $id);
        set_alert('success', $id ? _l('updated_successfully', 'Designation') : _l('added_successfully', 'Designation'));
        redirect(admin_url('hr/employees'));
    }

    public function delete_designation($id)
    {
        $this->guard('hr_employees', 'delete');
        $this->hr_model->delete_designation((int) $id);
        set_alert('success', _l('deleted', 'Designation'));
        redirect(admin_url('hr/employees'));
    }

    /* Inline add from the employee profile screen — returns JSON so the
       designation dropdown can be updated without a full page reload. */
    public function add_designation_inline()
    {
        if (!has_permission('hr_employees', '', 'edit') && !is_admin()) {
            echo json_encode(['success' => false, 'message' => _l('access_denied')]);
            die;
        }
        $name = trim((string) $this->input->post('name'));
        if ($name === '') {
            echo json_encode(['success' => false, 'message' => 'Designation name is required.']);
            die;
        }
        $role_id = (int) $this->input->post('role_id') ?: null;
        $id = $this->hr_model->save_designation([
            'name'          => $name,
            'department_id' => (int) $this->input->post('department_id') ?: null,
            'role_id'       => $role_id,
            'is_active'     => 1,
        ]);
        echo json_encode(['success' => true, 'id' => (int) $id, 'name' => $name, 'role_id' => $role_id]);
        die;
    }
}
