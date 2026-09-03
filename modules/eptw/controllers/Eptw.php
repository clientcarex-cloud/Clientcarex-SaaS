<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * ePTW — operations: dashboard, register, the permit form and permit page,
 * every workflow action, documents, gas tests, print/PDF, export and reports.
 *
 * Every mutating endpoint re-checks its permission in the model; hiding a
 * button is never the access control.
 */
class Eptw extends AdminController
{
    public function __construct()
    {
        parent::__construct();

        $this->load->model('eptw/eptw_model', 'setup');
        $this->load->model('eptw/eptw_permits_model', 'permits');
        $this->load->model('eptw/eptw_reports_model', 'reports');

        if (!eptw_can_access()) {
            access_denied('ePTW');
        }
    }

    /* ═══════════════════════════ Dashboard ══════════════════════════ */

    public function index()
    {
        $data['cards']     = $this->reports->cards();
        $data['high_risk'] = $this->reports->high_risk_panel();
        $data['simops']    = $this->reports->simops_panel();
        $data['charts']    = $this->reports->charts(30);
        $data['queue']     = $this->permits->my_queue();
        $data['expiring']  = $this->permits->register(['view' => 'expiring'], 8);
        $data['expired']   = $this->permits->register(['view' => 'expired'], 8);
        $data['recent']    = $this->permits->register([], 8);
        $data['mine']      = $this->permits->register(['view' => 'mine'], 6);
        $data['title']     = 'ePTW Dashboard';

        $this->load->view('dashboard', $data);
    }

    /* ═══════════════════════════ Register ═══════════════════════════ */

    public function register()
    {
        $filters = [];
        foreach (['q', 'view', 'status', 'project', 'area', 'type', 'contractor', 'engineer', 'from', 'to', 'risk'] as $key) {
            $filters[$key] = (string) $this->input->get($key);
        }
        $per_page = 50;
        $page     = max(1, (int) $this->input->get('page'));
        $total    = 0;

        $data['rows']     = $this->permits->register($filters, $per_page, ($page - 1) * $per_page, $total);
        $data['total']    = $total;
        $data['page']     = $page;
        $data['pages']    = max(1, (int) ceil($total / $per_page));
        $data['filters']  = $filters;
        $data['title']    = 'Permit register';

        $this->load->view('register', $data);
    }

    /** CSV of the register with the current filters — opens straight in Excel. */
    public function export()
    {
        if (!eptw_can('register')) {
            access_denied('ePTW');
        }
        $filters = [];
        foreach (['q', 'view', 'status', 'project', 'area', 'type', 'contractor', 'engineer', 'from', 'to', 'risk'] as $key) {
            $filters[$key] = (string) $this->input->get($key);
        }
        $rows = $this->permits->register($filters, 5000, 0);
        $out  = $this->reports->export_rows($rows);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="eptw-permit-register-' . date('Y-m-d') . '.csv"');
        $fh = fopen('php://output', 'w');
        fwrite($fh, "\xEF\xBB\xBF");
        foreach ($out as $line) {
            fputcsv($fh, $line);
        }
        fclose($fh);
        exit;
    }

    /* ═══════════════════════════ Permit form ════════════════════════ */

    public function permit($id = 0)
    {
        $id     = (int) $id;
        $permit = $id ? $this->permits->get($id) : null;

        if ($id && (!$permit || !$this->permits->can_edit($permit))) {
            set_alert('warning', 'That permit cannot be edited any more.');

            return redirect(admin_url('eptw/view/' . $id));
        }
        if (!$id && !eptw_can('create')) {
            access_denied('ePTW');
        }

        if ($this->input->post()) {
            $post   = $this->input->post(null, false);
            $result = $this->permits->save($post, $id);
            if (is_string($result)) {
                set_alert('warning', $result);

                return redirect(admin_url('eptw/permit' . ($id ? '/' . $id : '?type=' . (int) ($post['permit_type_id'] ?? 0))));
            }
            $fresh = $this->permits->get($result);

            if (($post['after'] ?? '') === 'submit') {
                $r = $this->permits->request_number($fresh, (string) ($post['submit_note'] ?? ''));
                set_alert(is_string($r) ? 'warning' : 'success', is_string($r) ? $r : 'Permit saved and the permit number requested.');
            } elseif (($post['after'] ?? '') === 'issue' && eptw_can('issue')) {
                $r = $this->permits->issue($fresh, (string) ($post['submit_note'] ?? 'Direct issue by the PTW Coordinator'));
                set_alert(is_string($r) ? 'warning' : 'success', is_string($r) ? $r : 'Permit saved and the permit number issued.');
            } else {
                set_alert('success', $id ? 'Permit saved.' : 'Draft saved. Nothing is valid on site until a permit number is issued.');
            }

            return redirect(admin_url('eptw/view/' . $result));
        }

        $type_id = $permit ? (int) $permit->permit_type_id : (int) $this->input->get('type');
        $type    = $type_id ? eptw_permit_type($type_id) : null;

        $data['permit']      = $permit;
        $data['type']        = $type;
        $data['types']       = eptw_permit_types();
        $data['projects']    = eptw_projects();
        $data['contractors'] = eptw_contractors();
        $data['areas']       = $permit ? eptw_areas($permit->project_id) : eptw_areas((int) ($this->input->get('project') ?: (count(eptw_projects()) ? eptw_projects()[0]->id : 0)));
        $data['staff_aa']    = eptw_team_by_role('area_authority');
        $data['staff_hse']   = eptw_team_by_role('hse');
        $data['engineers']   = eptw_team_by_role('engineer');
        $data['title']       = $permit ? 'Edit ' . ($permit->permit_no ?: 'draft') : ($type ? 'New ' . $type->name : 'New permit');

        $this->load->view('permit_form', $data);
    }

    /** JSON: areas of a project (for the form's cascading select). */
    public function areas_json($project_id = 0)
    {
        $out = [];
        foreach (eptw_areas((int) $project_id) as $area) {
            $out[] = ['id' => (int) $area->id, 'name' => $area->name, 'code' => $area->code, 'shared' => (int) $area->project_id === 0];
        }

        return $this->json(['ok' => true, 'areas' => $out]);
    }

    /** JSON: hazard / control / PPE suggestions for the description typed so far. */
    public function suggest()
    {
        $type = eptw_permit_type((int) $this->input->post('permit_type_id'));
        if (!$type) {
            return $this->json(['ok' => false]);
        }
        $area = '';
        if ((int) $this->input->post('area_id')) {
            $row  = $this->setup->area((int) $this->input->post('area_id'));
            $area = $row ? $row->name : '';
        }
        $s = eptw_suggest($type, (string) $this->input->post('work_description') . ' ' . (string) $this->input->post('work_title'), $area);
        $s['hazard_keys']  = array_map([$this->permits, 'hkey'], $s['hazards']);
        $s['control_keys'] = array_map([$this->permits, 'hkey'], $s['controls']);

        return $this->json(['ok' => true] + $s);
    }

    /** JSON: SIMOPS conflicts for a window before the permit is even saved. */
    public function simops_preview()
    {
        $draft = (object) [
            'id'             => (int) $this->input->post('permit_id'),
            'project_id'     => (int) $this->input->post('project_id'),
            'area_id'        => (int) $this->input->post('area_id'),
            'permit_type_id' => (int) $this->input->post('permit_type_id'),
            'start_at'       => date('Y-m-d H:i:s', strtotime(str_replace('T', ' ', (string) $this->input->post('start_at')) ?: 'now')),
            'end_at'         => date('Y-m-d H:i:s', strtotime(str_replace('T', ' ', (string) $this->input->post('end_at')) ?: '+12 hours')),
        ];
        if (!$draft->area_id || !$draft->permit_type_id) {
            return $this->json(['ok' => true, 'conflicts' => []]);
        }
        $conflicts = $this->permits->simops_check($draft);
        foreach ($conflicts as &$c) {
            $c['window'] = eptw_dt($c['start_at']) . ' → ' . eptw_dt($c['end_at']);
            $c['url']    = admin_url('eptw/view/' . $c['permit_id']);
        }

        return $this->json(['ok' => true, 'conflicts' => $conflicts]);
    }

    /* ═══════════════════════════ Permit page ════════════════════════ */

    public function view($id)
    {
        $permit = $this->permits->get((int) $id);
        if (!$permit || !$this->permits->can_view($permit)) {
            show_404();
        }
        $type = eptw_permit_type($permit->permit_type_id);
        $me   = eptw_me();

        $data['permit']        = $permit;
        $data['type']          = $type;
        $data['approvals']     = $this->permits->approvals($permit->id);
        $data['events']        = $this->permits->events($permit->id);
        $data['documents']     = $this->permits->documents($permit->id);
        $data['gas_tests']     = $this->permits->gas_tests($permit->id);
        $data['extensions']    = $this->permits->extensions($permit->id);
        $data['revalidations'] = $this->permits->revalidations($permit->id);
        $data['missing_docs']  = $this->permits->missing_docs($permit->id);
        $data['conflicts']     = in_array($permit->status, array_merge(['draft', 'returned'], eptw_pending_statuses(), eptw_live_statuses()), true) ? $this->permits->simops_check($permit) : [];
        $data['project']       = $this->setup->project($permit->project_id);
        $data['can']           = [
            'edit'           => $this->permits->can_edit($permit),
            'submit'         => $this->permits->can_edit($permit) && in_array($permit->status, ['draft', 'returned'], true),
            'issue'          => eptw_can('issue') && in_array($permit->status, ['draft', 'returned', 'number_requested', 'under_review'], true),
            'return'         => (eptw_can('review') || eptw_can('issue')) && in_array($permit->status, eptw_pending_statuses(), true),
            'activate'       => (eptw_can('status') || eptw_role() === 'area_authority') && $permit->status === 'issued',
            'suspend'        => (eptw_can('status') || in_array(eptw_role(), ['hse', 'area_authority'], true)) && in_array($permit->status, ['issued', 'active', 'active_extended', 'on_hold'], true),
            'hold'           => eptw_can('status') && in_array($permit->status, ['issued', 'active', 'active_extended'], true),
            'resume'         => eptw_can('status') && in_array($permit->status, ['suspended', 'on_hold', 'on_hold_simops'], true),
            'extend'         => eptw_can('extend_request') && in_array($permit->status, ['issued', 'active', 'active_extended'], true) && (int) $permit->pending_extensions === 0,
            'extend_approve' => eptw_can('extend_approve'),
            'close'          => eptw_can('status') && in_array($permit->status, eptw_live_statuses(), true),
            'cancel'         => (eptw_can('status') || $this->permits->can_edit($permit)) && !in_array($permit->status, eptw_closed_statuses(), true),
            'archive'        => eptw_can('status') && in_array($permit->status, ['closed', 'cancelled'], true),
            'upload'         => eptw_can('upload') && !in_array($permit->status, ['cancelled', 'archived'], true),
            'gas_test'       => eptw_can('gas_test') && in_array($permit->status, array_merge(eptw_pending_statuses(), eptw_live_statuses(), ['draft', 'returned']), true),
            'revalidate'     => (eptw_can('status') || in_array(eptw_role(), ['area_authority', 'hse'], true)) && in_array($permit->status, eptw_live_statuses(), true),
            'toolbox'        => eptw_can('upload') && !in_array($permit->status, ['cancelled', 'archived'], true),
            'paper'          => eptw_can('issue') && in_array($permit->status, eptw_pending_statuses(), true),
            'delete'         => eptw_role() === 'admin',
            'remark'         => eptw_can('remark'),
        ];
        $data['sign_steps'] = [];
        foreach ($data['approvals'] as $ap) {
            if ($ap->decision === 'pending' && $this->permits->can_sign($permit, $ap->step)) {
                $data['sign_steps'][] = $ap->step;
            }
        }
        $data['title'] = $permit->permit_no ?: ('Draft — ' . $permit->work_title);

        $this->load->view('permit_view', $data);
    }

    /**
     * One endpoint for every workflow action. Always POST; answers JSON for
     * AJAX callers and a redirect back to the permit page otherwise.
     */
    public function act($id, $action)
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }
        $permit = $this->permits->get((int) $id);
        if (!$permit || !$this->permits->can_view($permit)) {
            show_404();
        }
        $in = $this->input->post(null, false) ?: [];
        $ok = 'Unknown action.';

        switch ($action) {
            case 'submit':
                $ok = $this->permits->request_number($permit, (string) ($in['note'] ?? ''));
                $msg = 'Permit number requested. The reviewers have been notified.';
                break;
            case 'decide':
                $ok  = $this->permits->decide($permit, (string) ($in['step'] ?? ''), (string) ($in['decision'] ?? ''), (string) ($in['remarks'] ?? ''), (string) ($in['signature'] ?? ''));
                $msg = ($in['decision'] ?? '') === 'approved' ? 'Signed. Thank you.' : 'Returned to the engineer for correction.';
                break;
            case 'paper_approval':
                $ok  = $this->permits->record_paper_approval($permit, (string) ($in['step'] ?? ''), (string) ($in['name'] ?? ''), (string) ($in['remarks'] ?? ''));
                $msg = 'Paper approval recorded.';
                break;
            case 'issue':
                $ok  = $this->permits->issue($permit, (string) ($in['note'] ?? ''), (string) ($in['signature'] ?? ''));
                $fresh = $this->permits->get($permit->id);
                $msg = 'Permit number ' . ($fresh->permit_no ?? '') . ' issued.' . ($fresh && $fresh->status === 'on_hold_simops' ? ' A blocking SIMOPS conflict put it on hold — resolve it before work starts.' : '');
                break;
            case 'return':
                $ok  = $this->permits->return_for_correction($permit, (string) ($in['reason'] ?? ''));
                $msg = 'Returned to the engineer.';
                break;
            case 'activate':
                $ok  = $this->permits->activate($permit, (string) ($in['note'] ?? ''));
                $msg = 'Work started — the permit is active.';
                break;
            case 'suspend':
                $ok  = $this->permits->suspend($permit, (string) ($in['reason'] ?? ''), (string) ($in['note'] ?? ''));
                $msg = 'Permit suspended. Everyone on the permit has been notified.';
                break;
            case 'hold':
                $ok  = $this->permits->hold($permit, (string) ($in['note'] ?? ''));
                $msg = 'Permit put on hold.';
                break;
            case 'resume':
                $ok  = $this->permits->resume($permit, (string) ($in['note'] ?? ''));
                $msg = 'Permit resumed.';
                break;
            case 'extend':
                $ok  = $this->permits->request_extension($permit, (string) ($in['new_end_at'] ?? ''), (string) ($in['reason'] ?? ''));
                $msg = eptw_can('extend_approve') ? 'Permit extended.' : 'Extension requested — the PTW Coordinator has been notified.';
                break;
            case 'decide_extension':
                $ok  = $this->permits->decide_extension($permit, (int) ($in['extension_id'] ?? 0), (string) ($in['decision'] ?? ''), (string) ($in['note'] ?? ''));
                $msg = ($in['decision'] ?? '') === 'approved' ? 'Extension approved.' : 'Extension rejected.';
                break;
            case 'close':
                $ok  = $this->permits->close($permit, (array) ($in['closure'] ?? []), (string) ($in['note'] ?? ''));
                $fresh = $this->permits->get($permit->id);
                $msg = $fresh && $fresh->status === 'closed_docs_pending' ? 'Permit closed. Closure documents are still pending upload.' : 'Permit closed.';
                break;
            case 'cancel':
                $ok  = $this->permits->cancel($permit, (string) ($in['reason'] ?? ''));
                $msg = 'Permit cancelled.';
                break;
            case 'archive':
                $ok  = $this->permits->archive($permit);
                $msg = 'Permit archived.';
                break;
            case 'remark':
                $ok  = $this->permits->add_remark($permit, (string) ($in['text'] ?? ''));
                $msg = 'Remark added.';
                break;
            case 'gas_test':
                $ok  = $this->permits->add_gas_test($permit, $in);
                $msg = $ok === 'unsafe' ? 'Gas test recorded — readings are OUTSIDE the safe limits. Consider suspending the permit.' : 'Gas test recorded.';
                if ($ok === 'unsafe') {
                    $ok = true;
                }
                break;
            case 'gas_test_delete':
                $ok  = $this->permits->delete_gas_test($permit, (int) ($in['gas_test_id'] ?? 0));
                $msg = 'Gas test removed.';
                break;
            case 'revalidate':
                $ok  = $this->permits->add_revalidation($permit, $in);
                $msg = 'Shift revalidation recorded.';
                break;
            case 'toolbox':
                $ok  = $this->permits->save_toolbox($permit, $in);
                $msg = 'Toolbox talk recorded.';
                break;
            case 'delete':
                if (eptw_role() !== 'admin') {
                    $ok = 'Only an ePTW administrator can delete a permit.';
                } else {
                    $this->permits->delete($permit->id);
                    set_alert('success', 'Permit deleted.');

                    return $this->input->is_ajax_request() ? $this->json(['ok' => true, 'redirect' => admin_url('eptw/register')]) : redirect(admin_url('eptw/register'));
                }
                break;
            case 'document_delete':
                $ok  = $this->permits->delete_document($permit, (int) ($in['document_id'] ?? 0));
                $msg = 'Document removed.';
                break;
            default:
                $msg = '';
        }

        if ($this->input->is_ajax_request()) {
            return $this->json(['ok' => $ok === true, 'message' => $ok === true ? $msg : (string) $ok, 'redirect' => admin_url('eptw/view/' . $permit->id)]);
        }
        set_alert($ok === true ? 'success' : 'warning', $ok === true ? $msg : (string) $ok);

        return redirect(admin_url('eptw/view/' . $permit->id));
    }

    /* ═══════════════════════════ Documents ══════════════════════════ */

    public function upload($id)
    {
        $permit = $this->permits->get((int) $id);
        if (!$permit || !$this->permits->can_view($permit)) {
            show_404();
        }
        $type  = (string) $this->input->post('doc_type');
        $note  = (string) $this->input->post('note');
        $files = $_FILES['files'] ?? null;
        $done  = 0;
        $err   = '';

        if ($files && is_array($files['name'])) {
            foreach ($files['name'] as $i => $name) {
                $one = ['name' => $name, 'type' => $files['type'][$i], 'tmp_name' => $files['tmp_name'][$i], 'error' => $files['error'][$i], 'size' => $files['size'][$i]];
                if ($one['error'] === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                $r = $this->permits->add_document($permit, $one, $type, $note);
                if ($r === true) {
                    $done++;
                } else {
                    $err = $r;
                }
            }
        } elseif (!empty($_FILES['file'])) {
            $r = $this->permits->add_document($permit, $_FILES['file'], $type, $note);
            if ($r === true) {
                $done++;
            } else {
                $err = $r;
            }
        } else {
            $err = 'Choose a file first.';
        }

        $ok  = $done > 0;
        $msg = $ok ? $done . ' file(s) uploaded.' . ($err !== '' ? ' One failed: ' . $err : '') : $err;
        if ($this->input->is_ajax_request()) {
            return $this->json(['ok' => $ok, 'message' => $msg]);
        }
        set_alert($ok ? 'success' : 'warning', $msg);

        return redirect(admin_url('eptw/view/' . $permit->id . '#documents'));
    }

    public function document($doc_id)
    {
        $doc = $this->permits->document((int) $doc_id);
        if (!$doc) {
            show_404();
        }
        $permit = $this->permits->get($doc->permit_id);
        if (!$permit || !$this->permits->can_view($permit)) {
            show_404();
        }
        $abs = eptw_upload_dir($permit->id) . basename($doc->file_name);
        if (!is_file($abs)) {
            show_404();
        }
        $inline = in_array(strtolower(pathinfo($doc->file_name, PATHINFO_EXTENSION)), ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp'], true);
        header('Content-Type: ' . ($doc->mime ?: 'application/octet-stream'));
        header('Content-Length: ' . filesize($abs));
        header('Content-Disposition: ' . ($inline && $this->input->get('dl') === null ? 'inline' : 'attachment') . '; filename="' . str_replace('"', '', $doc->original_name) . '"');
        readfile($abs);
        exit;
    }

    /* ═══════════════════════════ Print / PDF ════════════════════════ */

    private function print_data($id)
    {
        $permit = $this->permits->get((int) $id);
        if (!$permit || !$this->permits->can_view($permit)) {
            show_404();
        }

        return [
            'permit'        => $permit,
            'type'          => eptw_permit_type($permit->permit_type_id),
            'approvals'     => $this->permits->approvals($permit->id),
            'gas_tests'     => $this->permits->gas_tests($permit->id),
            'extensions'    => $this->permits->extensions($permit->id),
            'revalidations' => $this->permits->revalidations($permit->id),
            'documents'     => $this->permits->documents($permit->id),
            'company'       => eptw_opt('eptw_company_name') ?: get_option('companyname'),
        ];
    }

    /** Browser print view (print-friendly HTML). */
    public function print_permit($id)
    {
        $data           = $this->print_data($id);
        $data['for_pdf'] = false;
        $this->load->view('print/permit', $data);
    }

    /** The same form as a PDF via TCPDF. */
    public function pdf($id)
    {
        $data           = $this->print_data($id);
        $data['for_pdf'] = true;
        $html           = $this->load->view('print/permit', $data, true);

        require_once module_dir_path(EPTW_MODULE_NAME, 'libraries/Eptw_pdf.php');
        $pdf = new Eptw_pdf($data['permit'], $data['company']);
        $pdf->render($html);
        $pdf->Output(($data['permit']->permit_no ?: 'permit-draft-' . $data['permit']->id) . '.pdf', $this->input->get('dl') !== null ? 'D' : 'I');
    }

    /* ═══════════════════════════ Reports ════════════════════════════ */

    public function reports()
    {
        if (!eptw_can('reports')) {
            access_denied('ePTW reports');
        }
        $names  = $this->reports->report_names();
        $report = (string) $this->input->get('report');
        if (!isset($names[$report])) {
            $report = 'monthly';
        }
        $from = (string) $this->input->get('from') ?: date('Y-m-01');
        $to   = (string) $this->input->get('to') ?: date('Y-m-d');

        $data['names']  = $names;
        $data['report'] = $report;
        $data['from']   = $from;
        $data['to']     = $to;
        $data['result'] = $this->reports->report($report, $from, $to);
        $data['title']  = 'ePTW reports';

        if ($this->input->get('export') !== null) {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="eptw-' . $report . '-' . date('Y-m-d') . '.csv"');
            $fh = fopen('php://output', 'w');
            fwrite($fh, "\xEF\xBB\xBF");
            $rows = $data['result']['rows'];
            if ($data['result']['kind'] === 'list') {
                fputcsv($fh, ['Permit No', 'Type', 'Project', 'Area', 'Contractor', 'Work', 'Engineer', 'Start', 'End', 'Status', 'Risk', 'Extensions', 'SIMOPS']);
                foreach ($rows as $r) {
                    fputcsv($fh, [$r->permit_no, $r->type_name, $r->project_name, $r->area_name, $r->contractor_name, $r->work_title, $r->engineer_name, $r->start_at, $r->end_at, eptw_status_label($r->status), ucfirst($r->risk_level), $r->extension_count, $r->simops_flag ? 'Yes' : 'No']);
                }
            } elseif ($data['result']['kind'] === 'monthly') {
                fputcsv($fh, ['Month', 'Created', 'Issued', 'Closed', 'Cancelled', 'Suspended', 'High risk', 'Extensions', 'SIMOPS']);
                foreach ($rows as $r) {
                    fputcsv($fh, [$r->label, $r->total, $r->issued, $r->closed, $r->cancelled, $r->suspended, $r->high_risk, $r->extensions, $r->simops]);
                }
            } else {
                fputcsv($fh, ['Name', 'Total', 'Active', 'Suspended', 'Closed', 'High risk', 'Extensions', 'SIMOPS']);
                foreach ($rows as $r) {
                    fputcsv($fh, [$r->label, $r->total, $r->active, $r->suspended, $r->closed, $r->high_risk, $r->extensions, $r->simops]);
                }
            }
            fclose($fh);
            exit;
        }

        $this->load->view('reports', $data);
    }

    /* ═══════════════════════════ Utilities ══════════════════════════ */

    private function json($payload)
    {
        return $this->output->set_content_type('application/json')->set_output(json_encode($payload));
    }
}
