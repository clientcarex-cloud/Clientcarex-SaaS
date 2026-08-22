<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Smart_pdf extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('smart_pdf_model');
    }

    /* List all smart pdf templates as a card grid with live thumbnails */
    public function index()
    {
        if (!has_permission('smart_pdf', '', 'view')) {
            access_denied(_l('smart_pdf'));
        }

        $data['title']     = _l('smart_pdf');
        $data['stats']     = $this->smart_pdf_model->get_stats();
        $data['templates'] = $this->smart_pdf_model->get_all();
        $this->load->view('manage', $data);
    }

    /* ---------------------------------------------------------------------
     * Branding Center — one per-tenant profile (colours, logo, company name)
     * applied to EVERY generated document (see Smart_pdf_model branding_*).
     * ------------------------------------------------------------------- */

    public function branding($template_id = '')
    {
        if (!has_permission('smart_pdf', '', 'view')) {
            access_denied(_l('smart_pdf'));
        }

        // Per-template mode when a valid template id is given.
        $template = null;
        if ($template_id !== '') {
            $template = $this->smart_pdf_model->get($template_id);
            if (!$template) {
                show_404();
            }
        }

        if ($this->input->post()) {
            if (!has_permission('smart_pdf', '', 'edit') && !is_admin()) {
                access_denied(_l('smart_pdf'));
            }
            if ($template) {
                $this->smart_pdf_model->save_template_branding($template->id, $this->input->post());
                set_alert('success', _l('smart_pdf_branding_tpl_saved'));
                redirect(admin_url('smart_pdf/branding/' . $template->id));
            }
            $this->smart_pdf_model->save_branding($this->input->post());
            set_alert('success', _l('smart_pdf_branding_saved'));
            redirect(admin_url('smart_pdf/branding'));
        }

        if ($template) {
            $branding             = $this->smart_pdf_model->get_template_branding($template);
            $data['template']     = $template;
            $data['title']        = _l('smart_pdf_branding') . ' - ' . $template->name;
            $data['preview_url']  = admin_url('smart_pdf/preview/' . $template->id);
        } else {
            $branding             = $this->smart_pdf_model->get_branding();
            $data['template']     = null;
            $data['title']        = _l('smart_pdf_branding');
            $data['preview_url']  = admin_url('smart_pdf/branding_preview');
        }

        $data['branding']        = $branding;
        $data['fonts']           = $this->smart_pdf_model->branding_font_choices();
        $data['crm_logo_url']    = $this->smart_pdf_model->branding_logo_url(array_merge($branding, ['logo_source' => 'crm']));
        $data['custom_logo_url'] = !empty($branding['logo_file']) ? $this->smart_pdf_model->asset_url($branding['logo_file']) : '';
        $this->load->view('branding', $data);
    }

    /* Standalone sample document for the (global) Branding Center preview iframe */
    public function branding_preview()
    {
        if (!has_permission('smart_pdf', '', 'view')) {
            access_denied(_l('smart_pdf'));
        }

        $sample = $this->load->view('branding_sample', ['b' => $this->smart_pdf_model->get_branding()], true);
        $sample = $this->inject_before_head_end($sample, $this->smart_pdf_model->branding_style_block());
        $this->serve_standalone($sample);
    }

    /* AJAX: upload a custom document-branding logo into company uploads */
    public function upload_brand_logo()
    {
        if (!has_permission('smart_pdf', '', 'edit') && !is_admin()) {
            ajax_access_denied();
        }

        $path = get_upload_path_by_type('company');
        if (!is_dir($path)) {
            @mkdir($path, 0755, true);
        }

        $this->load->library('upload');
        $this->upload->initialize([
            'upload_path'   => $path,
            'allowed_types' => 'jpg|jpeg|png|gif|svg|webp',
            'max_size'      => 3072,
            'file_name'     => 'smart_pdf_brand_' . uniqid(),
            'encrypt_name'  => false,
        ]);

        if (!$this->upload->do_upload('logo')) {
            echo json_encode(['success' => false, 'message' => trim(strip_tags($this->upload->display_errors()))]);

            return;
        }

        $file = $this->upload->data('file_name');
        echo json_encode([
            'success' => true,
            'file'    => $file,
            'url'     => $this->smart_pdf_model->asset_url($file),
        ]);
    }

    /* Reset branding to shipped defaults */
    public function reset_branding()
    {
        if (!has_permission('smart_pdf', '', 'edit') && !is_admin()) {
            access_denied(_l('smart_pdf'));
        }
        $this->smart_pdf_model->reset_branding();
        set_alert('success', _l('smart_pdf_branding_reset_done'));
        redirect(admin_url('smart_pdf/branding'));
    }

    /* Add or edit smart pdf template (one-time HTML setup) */
    public function template($id = '')
    {
        if (!has_permission('smart_pdf', '', 'view')) {
            access_denied(_l('smart_pdf'));
        }

        if ($this->input->post()) {
            // CRITICAL: Re-fetch 'content' WITHOUT XSS filtering.
            // global_xss_filtering strips inline CSS/JS-looking markup which
            // breaks imported HTML files (brochures, styled forms, etc).
            $content = $this->input->post('content', false);
            $content = $content === null ? '' : $content;

            $submitted_fields = json_decode($this->input->post('fields_json', false), true);
            $fields           = $this->smart_pdf_model->sync_fields($content, $submitted_fields);

            $data = [
                'name'        => $this->input->post('name'),
                'description' => $this->input->post('description'),
                'content'     => $content,
                'fields'      => json_encode($fields, JSON_UNESCAPED_UNICODE),
                'paper_size'  => $this->input->post('paper_size') ?: 'A4',
                'orientation' => $this->input->post('orientation') == 'L' ? 'L' : 'P',
                'active'      => $this->input->post('active') ? 1 : 0,
            ];

            if ($id == '') {
                if (!has_permission('smart_pdf', '', 'create')) {
                    access_denied(_l('smart_pdf'));
                }
                $id = $this->smart_pdf_model->add($data);
                if ($id) {
                    set_alert('success', _l('added_successfully', _l('smart_pdf_template_lowercase')));
                    redirect(admin_url('smart_pdf/template/' . $id));
                }
            } else {
                if (!has_permission('smart_pdf', '', 'edit')) {
                    access_denied(_l('smart_pdf'));
                }
                $success = $this->smart_pdf_model->update($data, $id);
                if ($success) {
                    set_alert('success', _l('updated_successfully', _l('smart_pdf_template_lowercase')));
                }
                redirect(admin_url('smart_pdf/template/' . $id));
            }
        }

        if ($id == '') {
            $data['title'] = _l('new_smart_pdf_template');
        } else {
            $data['template'] = $this->smart_pdf_model->get($id);
            if (!$data['template']) {
                blank_page(_l('smart_pdf_template_not_found'));
            }
            $data['fields'] = $this->smart_pdf_model->get_fields($data['template']);
            $data['title']  = _l('edit_smart_pdf_template');
        }

        $data['system_tags']   = $this->smart_pdf_model->system_tag_names();
        $data['agent_tags']    = $this->smart_pdf_model->agent_tag_names();
        $data['patient_tags']  = $this->smart_pdf_model->patient_tag_names();
        $data['employee_tags'] = $this->smart_pdf_model->employee_tag_names();

        $this->load->view('template', $data);
    }

    /**
     * One-click install of the bundled HR document templates (offer letters,
     * ID cards, certificates, agreements, policies, Form 16...). Idempotent -
     * re-running only adds templates that are not already present. Triggered
     * from HR Settings and the Smart PDF toolbar.
     */
    public function install_hr_templates()
    {
        if (!has_permission('smart_pdf', '', 'create')) {
            access_denied(_l('smart_pdf'));
        }

        // Overwrite mode re-applies bundled HTML to existing templates (e.g.
        // to pick up the company logo / design changes) and needs edit rights.
        $overwrite = (bool) $this->input->get('overwrite');
        if ($overwrite && !has_permission('smart_pdf', '', 'edit')) {
            access_denied(_l('smart_pdf'));
        }

        $result = $this->smart_pdf_model->install_sample_templates('hr', $overwrite);

        set_alert('success', sprintf(
            _l('smart_pdf_hr_install_done'),
            (int) $result['installed'],
            (int) $result['updated'],
            (int) $result['skipped']
        ));

        $referer = $this->input->server('HTTP_REFERER');
        redirect($referer ?: admin_url('smart_pdf'));
    }

    /* Duplicate an existing template */
    public function clone_template($id)
    {
        if (!has_permission('smart_pdf', '', 'create')) {
            access_denied(_l('smart_pdf'));
        }

        $new_id = $this->smart_pdf_model->clone_template($id);
        if ($new_id) {
            set_alert('success', _l('smart_pdf_cloned'));
            redirect(admin_url('smart_pdf/template/' . $new_id));
        }

        set_alert('warning', _l('smart_pdf_template_not_found'));
        redirect(admin_url('smart_pdf'));
    }

    /* Delete smart pdf template */
    public function delete($id)
    {
        if (!has_permission('smart_pdf', '', 'delete')) {
            access_denied(_l('smart_pdf'));
        }

        if (!$id) {
            redirect(admin_url('smart_pdf'));
        }

        $response = $this->smart_pdf_model->delete($id);
        if ($response == true) {
            set_alert('success', _l('deleted', _l('smart_pdf_template_lowercase')));
        } else {
            set_alert('warning', _l('problem_deleting', _l('smart_pdf_template_lowercase')));
        }
        redirect(admin_url('smart_pdf'));
    }

    /* Field definitions for the generate popup (AJAX) */
    public function get_fields($id)
    {
        if (!has_permission('smart_pdf', '', 'view')) {
            echo json_encode(['success' => false, 'message' => _l('access_denied')]);

            return;
        }

        $template = $this->smart_pdf_model->get($id);
        if (!$template) {
            echo json_encode(['success' => false, 'message' => _l('smart_pdf_template_not_found')]);

            return;
        }

        // Re-sync against the current HTML before building the popup: tags that
        // are only spelled differently ({{Doctor Name}} vs {{doctor_name}})
        // collapse into one input, and templates saved before that behaviour
        // existed get it without having to be opened and re-saved.
        $fields = $this->smart_pdf_model->sync_fields(
            $template->content,
            $this->smart_pdf_model->get_fields($template)
        );

        $fields = array_values(array_filter($fields, function ($field) {
            return empty($field['ignored']);
        }));

        // Role-named inputs ({{AgentName}}, {{AgentPhoto}}, {{HospitalName}}...)
        // arrive pre-filled from the logged-in staff member and this tenant.
        // Still ordinary editable fields — this is a suggestion, not a lock.
        $autofill = $this->smart_pdf_model->autofill_values();
        foreach ($fields as &$field) {
            $field['auto_value'] = $this->smart_pdf_model->field_autofill_value($field, $autofill);
        }
        unset($field);

        $patient_keys  = array_map([$this->smart_pdf_model, 'tag_key'], $this->smart_pdf_model->patient_tag_names());
        $employee_keys = array_map([$this->smart_pdf_model, 'tag_key'], $this->smart_pdf_model->employee_tag_names());

        $has_patient_tags  = false;
        $has_employee_tags = false;
        foreach ($fields as $field) {
            $key = $this->smart_pdf_model->tag_key($field['tag']);
            if (in_array($key, $patient_keys)) {
                $has_patient_tags = true;
            }
            if (in_array($key, $employee_keys)) {
                $has_employee_tags = true;
            }
        }

        echo json_encode([
            'success'           => true,
            'id'                => $template->id,
            'name'              => $template->name,
            'fields'            => $fields,
            'has_patient_tags'  => $has_patient_tags,
            'has_employee_tags' => $has_employee_tags,
        ]);
    }

    /* Search patients by name / mobile / MR number for autofill (AJAX) */
    public function search_patient()
    {
        if (!has_permission('smart_pdf', '', 'view')) {
            ajax_access_denied();
        }
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $q = trim((string) $this->input->post('q'));
        if (strlen($q) < 2) {
            echo json_encode([]);

            return;
        }

        echo json_encode($this->smart_pdf_model->search_patients($q));
    }

    /* Patient tag values for autofill after selecting a patient (AJAX) */
    public function patient_tags($patient_id)
    {
        if (!has_permission('smart_pdf', '', 'view')) {
            ajax_access_denied();
        }
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        echo json_encode($this->smart_pdf_model->get_patient_tag_values($patient_id));
    }

    /* Search employees by name / code / email for autofill (AJAX) */
    public function search_employee()
    {
        if (!has_permission('smart_pdf', '', 'view')) {
            ajax_access_denied();
        }
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $q = trim((string) $this->input->post('q'));
        if (strlen($q) < 2) {
            echo json_encode([]);

            return;
        }

        echo json_encode($this->smart_pdf_model->search_employees($q));
    }

    /* Employee tag values for autofill after selecting an employee (AJAX) */
    public function employee_tags($staff_id)
    {
        if (!has_permission('smart_pdf', '', 'view')) {
            ajax_access_denied();
        }
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        echo json_encode($this->smart_pdf_model->get_employee_tag_values($staff_id));
    }

    /* Generation history of one template (AJAX) */
    public function history($template_id)
    {
        if (!has_permission('smart_pdf', '', 'view')) {
            ajax_access_denied();
        }
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $rows = $this->smart_pdf_model->get_generations($template_id);
        foreach ($rows as &$row) {
            $row['document_no'] = 'DOC-' . str_pad($row['id'], 5, '0', STR_PAD_LEFT);
            $row['created_at']  = _dt($row['created_at']);
            // Avatar of the staff member who generated it (empty when they
            // have no profile picture — the list then shows the name only).
            $row['staff_photo'] = $this->smart_pdf_model->staff_photo_url($row['staff_id']);
        }

        echo json_encode($rows);
    }

    /* Stored values of one generation for the Reuse action (AJAX) */
    public function generation($id)
    {
        if (!has_permission('smart_pdf', '', 'view')) {
            ajax_access_denied();
        }
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $generation = $this->smart_pdf_model->get_generation($id);
        if (!$generation) {
            echo json_encode(['success' => false]);

            return;
        }

        $values = json_decode($generation->values, true);

        echo json_encode([
            'success'     => true,
            'template_id' => $generation->template_id,
            'patient_id'  => $generation->patient_id,
            'values'      => is_array($values) ? $values : [],
        ]);
    }

    /**
     * Standalone sample preview of a saved template (card thumbnails and
     * the zoom modal load this in an iframe). Served as its own document
     * so template scripts/fonts run outside the admin page CSP.
     */
    public function preview($id)
    {
        if (!has_permission('smart_pdf', '', 'view')) {
            access_denied(_l('smart_pdf'));
        }

        $template = $this->smart_pdf_model->get($id);
        if (!$template) {
            show_404();
        }

        $merged = $this->smart_pdf_model->sample_merge($template);
        $this->output_print($template, $merged, false);
    }

    /**
     * Sample preview of UNSAVED editor content (the template form posts the
     * current HTML here, targeted at the preview modal iframe).
     */
    public function preview_draft()
    {
        if (!has_permission('smart_pdf', '', 'view')) {
            access_denied(_l('smart_pdf'));
        }

        if (!$this->input->post()) {
            redirect(admin_url('smart_pdf'));
        }

        // Same XSS-filter bypass as template(): imported HTML must arrive intact.
        $content = $this->input->post('content', false);
        $content = $content === null ? '' : $content;

        $submitted_fields = json_decode($this->input->post('fields_json', false), true);
        $fields           = $this->smart_pdf_model->sync_fields($content, $submitted_fields);

        $template = (object) [
            'name'        => $this->input->post('name') ?: _l('smart_pdf_live_preview'),
            'content'     => $content,
            'fields'      => json_encode($fields, JSON_UNESCAPED_UNICODE),
            'paper_size'  => $this->input->post('paper_size') ?: 'A4',
            'orientation' => $this->input->post('orientation') == 'L' ? 'L' : 'P',
        ];

        $merged = $this->smart_pdf_model->sample_merge($template);
        $this->output_print($template, $merged, false);
    }

    /* Merge submitted values and output Preview / Print view or PDF download */
    public function generate()
    {
        if (!has_permission('smart_pdf', '', 'generate')) {
            access_denied(_l('smart_pdf'));
        }

        if (!$this->input->post()) {
            redirect(admin_url('smart_pdf'));
        }

        $id       = $this->input->post('template_id');
        $template = $this->smart_pdf_model->get($id);

        if (!$template) {
            show_404();
        }

        $values = $this->input->post('tags');
        if (!is_array($values)) {
            $values = [];
        }

        $mode = $this->input->post('mode');

        if ($mode == 'preview') {
            $merged = $this->smart_pdf_model->merge($template, $values, _l('smart_pdf_preview_document_no'));
            $this->output_print($template, $merged, false);

            return;
        }

        // Prints are logged for the audit history; the generation id
        // doubles as the {{document_no}} value. Users download PDFs via
        // the browser print dialog (Save as PDF) - pixel-perfect.
        $patient_id    = (int) $this->input->post('patient_id');
        $employee_id   = (int) $this->input->post('employee_id');
        $generation_id = $this->smart_pdf_model->add_generation($template->id, $values, 'print', $patient_id, $employee_id);
        $document_no   = 'DOC-' . str_pad($generation_id, 5, '0', STR_PAD_LEFT);

        $merged = $this->smart_pdf_model->merge($template, $values, $document_no);

        $this->output_print($template, $merged, true);
    }

    /**
     * Bulk ID-card sheet: lay many employees' cards on shared pages so a whole
     * team can be printed on a few sheets instead of one card per page.
     *
     * Each card is rendered as its own isolated <iframe srcdoc> containing the
     * exact single-card document build_print_document() produces — so every
     * card keeps pixel-perfect fidelity and templates with clashing CSS (the
     * front and back designs reuse the same class names) never bleed into each
     * other. The iframes flow through a print-friendly grid; when a back
     * template is chosen all fronts print first, then all backs in the SAME
     * order so the two passes line up for double-sided printing.
     *
     * Expects POST: template_id (front), back_template_id (optional),
     * employee_ids[] (staff ids), cut_guides (optional).
     */
    public function bulk_generate()
    {
        if (!has_permission('smart_pdf', '', 'generate')) {
            access_denied(_l('smart_pdf'));
        }

        if (!$this->input->post()) {
            redirect(admin_url('smart_pdf'));
        }

        $front = $this->smart_pdf_model->get((int) $this->input->post('template_id'));
        if (!$front) {
            show_404();
        }

        $back_id = (int) $this->input->post('back_template_id');
        $back    = $back_id ? $this->smart_pdf_model->get($back_id) : null;

        // Staff ids to print. Accept an array (checkbox list) or a comma list.
        $ids = $this->input->post('employee_ids');
        if (!is_array($ids)) {
            $ids = array_filter(array_map('trim', explode(',', (string) $ids)));
        }
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $ids = array_filter($ids, function ($v) { return $v > 0; });

        if (empty($ids)) {
            blank_page(_l('smart_pdf_bulk_no_employees'), 'warning');

            return;
        }

        // Safety cap so a runaway selection cannot build a monster document.
        $ids = array_slice($ids, 0, 300);

        $show_guides = $this->input->post('cut_guides') ? true : false;

        // Build one isolated card (iframe) per employee/side. Fronts first so
        // that, with a back template, the second pass mirrors the first.
        $front_cells = '';
        $back_cells  = '';
        foreach ($ids as $staff_id) {
            $values = $this->smart_pdf_model->get_employee_tag_values($staff_id);
            if (empty($values)) {
                continue;
            }

            // One audit/generation row per employee; its id is the document_no
            // shared by that employee's front and back card.
            $generation_id = $this->smart_pdf_model->add_generation($front->id, $values, 'print', null, $staff_id);
            $document_no   = 'DOC-' . str_pad($generation_id, 5, '0', STR_PAD_LEFT);

            $front_doc    = $this->build_print_document($front, $this->smart_pdf_model->merge($front, $values, $document_no), false);
            $front_cells .= $this->bulk_card_cell($front_doc, $front);

            if ($back) {
                $back_doc    = $this->build_print_document($back, $this->smart_pdf_model->merge($back, $values, $document_no), false);
                $back_cells .= $this->bulk_card_cell($back_doc, $back);
            }
        }

        if ($front_cells === '') {
            blank_page(_l('smart_pdf_bulk_no_employees'), 'warning');

            return;
        }

        $this->serve_standalone($this->bulk_sheet_document($front, $front_cells, $back_cells, $show_guides));
    }

    /**
     * One card cell for the bulk sheet: the single-card document embedded via
     * an isolated iframe sized to the template's own card dimensions.
     *
     * @param string $doc full single-card HTML document
     * @param object $template used for card sizing
     * @return string
     */
    protected function bulk_card_cell($doc, $template)
    {
        list($w, $h) = $this->card_dimensions($template);

        return '<div class="spdf-cell" style="width:' . $w . ';height:' . $h . ';">'
            . '<iframe class="spdf-card-frame" scrolling="no" srcdoc="' . htmlspecialchars($doc, ENT_QUOTES) . '"'
            . ' style="width:' . $w . ';height:' . $h . ';"></iframe>'
            . '</div>';
    }

    /**
     * Best-effort physical card size for a template. ID-card designs declare
     * their size in the template's own @page rule (e.g. "54mm 85.6mm"); fall
     * back to a standard CR80 portrait card when none is found.
     *
     * @param object $template
     * @return array [width, height] as CSS lengths
     */
    protected function card_dimensions($template)
    {
        if (!empty($template->content)
            && preg_match('/@page\s*{[^}]*\bsize\s*:\s*([0-9.]+\s*(?:mm|cm|in|px))\s+([0-9.]+\s*(?:mm|cm|in|px))/i', $template->content, $m)) {
            return [preg_replace('/\s+/', '', $m[1]), preg_replace('/\s+/', '', $m[2])];
        }

        return ['54mm', '85.6mm'];
    }

    /**
     * Assemble the outer printable sheet that holds the card cells. A4 portrait
     * with a small margin; cards flow left-to-right and never split across a
     * page. Fronts and backs are separated by a hard page break so a duplex
     * print aligns the two passes.
     *
     * @param object $template front template (for the title)
     * @param string $front_cells
     * @param string $back_cells
     * @param bool $show_guides dashed cut guides around each card
     * @return string
     */
    protected function bulk_sheet_document($template, $front_cells, $back_cells, $show_guides)
    {
        $guide_css = $show_guides ? 'outline:0.2mm dashed #b9c2c2; outline-offset:1mm;' : '';

        $body = '<div class="spdf-sheet">' . $front_cells . '</div>';
        if ($back_cells !== '') {
            $body .= '<div class="spdf-sheet spdf-backs">' . $back_cells . '</div>';
        }

        $toolbar = '<div class="spdf-bulk-toolbar">'
            . '<span class="spdf-bulk-hint">' . _l('smart_pdf_bulk_toolbar_hint') . '</span>'
            . '<button type="button" onclick="window.print()">&#128424; ' . _l('smart_pdf_print') . '</button>'
            . '</div>';

        return '<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>' . html_escape($template->name) . '</title>
<style>
  * { box-sizing:border-box; }
  html,body { margin:0; padding:0; background:#e6eaea; }
  .spdf-bulk-toolbar {
    position:sticky; top:0; z-index:10; display:flex; align-items:center; gap:14px;
    background:#0e4d54; color:#fff; padding:10px 18px; font-family:Arial,Helvetica,sans-serif; font-size:13px;
  }
  .spdf-bulk-toolbar button {
    margin-left:auto; background:#c2a24d; color:#20200f; border:0; border-radius:5px;
    padding:7px 16px; font-size:13px; font-weight:700; cursor:pointer;
  }
  .spdf-sheet {
    display:flex; flex-wrap:wrap; align-content:flex-start; justify-content:center;
    gap:6mm; padding:8mm; margin:14px auto; background:#fff; width:210mm; min-height:297mm;
    box-shadow:0 6px 22px rgba(0,0,0,.14);
  }
  .spdf-cell { flex:0 0 auto; overflow:hidden; ' . $guide_css . ' }
  .spdf-card-frame { border:0; display:block; }
  @media print {
    html,body { background:#fff; }
    .spdf-bulk-toolbar { display:none !important; }
    @page { size:A4 portrait; margin:8mm; }
    .spdf-sheet {
      width:auto; min-height:0; margin:0; padding:0; gap:6mm;
      box-shadow:none; justify-content:flex-start;
    }
    .spdf-backs { break-before:page; page-break-before:always; }
    .spdf-cell { break-inside:avoid; page-break-inside:avoid; }
  }
</style>
</head>
<body>' . $toolbar . $body . '
<script>
  // Auto-open the print dialog once every card iframe has rendered (fonts and
  // images included). Falls back to a timeout so a slow/blocked asset never
  // leaves the sheet un-printed.
  (function () {
    var frames = Array.prototype.slice.call(document.querySelectorAll(".spdf-card-frame"));
    var pending = frames.length;
    var done = false;
    function go() { if (done) { return; } done = true; setTimeout(function () { window.print(); }, 350); }
    if (!pending) { go(); return; }
    frames.forEach(function (f) {
      if (f.contentDocument && f.contentDocument.readyState === "complete") { if (--pending === 0) { go(); } return; }
      f.addEventListener("load", function () { if (--pending === 0) { go(); } });
    });
    setTimeout(go, 4000);
  })();
</script>
</body>
</html>';
    }

    /**
     * Render the merged HTML as a standalone page; optionally opens the
     * browser print dialog automatically (Print mode vs Preview mode).
     */
    protected function output_print($template, $merged, $auto_print = true)
    {
        $this->serve_standalone($this->build_print_document($template, $merged, $auto_print));
    }

    /**
     * Build the full standalone (branded) HTML document for one merged
     * template — the exact markup output_print serves, returned as a string so
     * it can also be embedded elsewhere (e.g. one card per iframe in the bulk
     * ID-card sheet). Optionally injects the auto-print script.
     *
     * @param object $template
     * @param string $merged already tag-merged content
     * @param bool $auto_print
     * @return string
     */
    protected function build_print_document($template, $merged, $auto_print = true)
    {
        $merged = $this->wrap_full_document($template, $merged);

        // Per-tenant branding: inject the palette/logo/name overrides into the
        // document head AFTER the template's own <style>, so a later :root wins
        // and the letterhead controls apply to every document with no template
        // edits. Uses the template's own override when enabled, else the global
        // profile. Covers generate, preview and card thumbnails (all route here).
        $branding = $this->smart_pdf_model->effective_branding($template);
        $merged   = $this->inject_before_head_end($merged, $this->smart_pdf_model->branding_style_block($branding));

        if ($auto_print) {
            $merged = $this->inject_before_body_end($merged, $this->auto_print_script());
        }

        return $merged;
    }

    /**
     * Auto-print script.
     *
     * The print dialog must not open before the document has actually been
     * laid out and painted: Chrome snapshots the page when print() is called,
     * so firing it on plain "load" produced a blank first preview (the user
     * then had to close the dialog and hit Ctrl+P again). Gate the call on
     * load + web fonts + every image decoded, then let two animation frames
     * pass so the first paint is on screen. Every wait is timeout-guarded so a
     * blocked/broken asset can never leave the document un-printed.
     *
     * @return string
     */
    protected function auto_print_script()
    {
        return '<script>
(function () {
  var fired = false;
  function print_now() {
    if (fired) { return; }
    fired = true;
    // Two frames + a short settle so layout and the first paint are committed
    // before the browser snapshots the page for the print preview.
    requestAnimationFrame(function () {
      requestAnimationFrame(function () {
        setTimeout(function () { window.print(); }, 250);
      });
    });
  }

  function images_ready() {
    var imgs = Array.prototype.slice.call(document.images || []).filter(function (i) {
      return !i.complete;
    });
    if (!imgs.length) { return Promise.resolve(); }

    return Promise.all(imgs.map(function (img) {
      return new Promise(function (resolve) {
        // resolve on error too - a broken image must not block the dialog
        img.addEventListener("load", resolve);
        img.addEventListener("error", resolve);
      });
    }));
  }

  function fonts_ready() {
    if (!document.fonts || !document.fonts.ready) { return Promise.resolve(); }

    return document.fonts.ready.catch(function () {});
  }

  function when_ready() {
    Promise.all([images_ready(), fonts_ready()]).then(print_now, print_now);
  }

  // Hard ceiling: print anyway if something never settles.
  setTimeout(print_now, 8000);

  if (document.readyState === "complete") {
    when_ready();
  } else {
    window.addEventListener("load", when_ready);
  }
})();
</script>';
    }

    /**
     * Wrap bare template content in a printable HTML document (full HTML
     * imports pass through untouched).
     */
    protected function wrap_full_document($template, $html)
    {
        if (stripos($html, '<html') !== false) {
            return $html;
        }

        $orientation = $template->orientation == 'L' ? 'landscape' : 'portrait';

        return '<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>' . html_escape($template->name) . '</title>
<style>
@page { size: ' . $template->paper_size . ' ' . $orientation . '; margin: 12mm; }
body { font-family: Arial, Helvetica, sans-serif; }
</style>
</head>
<body>' . $html . '</body>
</html>';
    }

    protected function inject_before_body_end($html, $snippet)
    {
        if (stripos($html, '</body>') !== false) {
            return str_ireplace('</body>', $snippet . '</body>', $html);
        }

        return $html . $snippet;
    }

    /**
     * Insert a snippet just before </head> (so it wins over the template's own
     * <style>). Falls back to just after <body> or the document start.
     */
    protected function inject_before_head_end($html, $snippet)
    {
        if (stripos($html, '</head>') !== false) {
            return str_ireplace('</head>', $snippet . '</head>', $html);
        }
        if (stripos($html, '<body') !== false) {
            return preg_replace_callback('/<body[^>]*>/i', function ($m) use ($snippet) {
                return $m[0] . $snippet;
            }, $html, 1);
        }

        return $snippet . $html;
    }

    /**
     * Serve a standalone (non-admin) document.
     *
     * Imported templates may ship their own scripts/fonts (e.g. blob:
     * font loading), which the global ccx_security CSP blocks and the
     * template's bundle then errors out. The document is authored by
     * permission-gated staff (same trust as print_templates), so drop
     * the CSP for this standalone response only.
     */
    protected function serve_standalone($html)
    {
        if (!headers_sent()) {
            header_remove('Content-Security-Policy');
            header_remove('Content-Security-Policy-Report-Only');
        }

        $this->load->view('output', ['html' => $html]);
    }
}
