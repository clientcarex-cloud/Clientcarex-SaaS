<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Smart_forms extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('smart_forms/smart_forms_model', 'smart_forms_model');
    }

    /* Forms dashboard / listing */
    public function index()
    {
        if (!has_permission('smart_forms', '', 'view') && !is_admin()) {
            access_denied('smart_forms');
        }

        $data['title']   = _l('smart_forms');
        $data['forms']   = $this->smart_forms_model->get_forms();
        $data['summary'] = $this->smart_forms_model->get_summary();

        $this->load->view('manage', $data);
    }

    /* Create / edit a form (builder) */
    public function form($id = '')
    {
        if (!has_permission('smart_forms', '', 'view') && !is_admin()) {
            access_denied('smart_forms');
        }

        if ($this->input->post()) {
            $post = $this->input->post();

            // Questions arrive as a JSON blob from the builder. Re-fetch
            // without XSS filtering so option text/terms content survives,
            // views escape on output.
            $questions = json_decode($this->input->post('questions', false), true);
            $post['questions'] = is_array($questions) ? $questions : [];

            if ($id == '') {
                if (!has_permission('smart_forms', '', 'create') && !is_admin()) {
                    access_denied('smart_forms');
                }
                $new_id = $this->smart_forms_model->add_form($post);
                if ($new_id) {
                    set_alert('success', _l('smart_forms_saved'));
                    redirect(admin_url('smart_forms/form/' . $new_id));
                }
            } else {
                if (!has_permission('smart_forms', '', 'edit') && !is_admin()) {
                    access_denied('smart_forms');
                }
                $this->smart_forms_model->update_form($id, $post);
                set_alert('success', _l('smart_forms_saved'));
                redirect(admin_url('smart_forms/form/' . $id));
            }
        }

        $data['title'] = ($id == '') ? _l('smart_forms_new_form') : _l('smart_forms_edit_form');

        if ($id != '') {
            $data['form'] = $this->smart_forms_model->get_forms($id);
            if (!$data['form']) {
                show_404();
            }
        }

        $data['question_types'] = $this->smart_forms_model->question_types();

        $this->load->view('builder', $data);
    }

    /* Submissions listing + per-question insights */
    public function submissions($form_id)
    {
        if (!has_permission('smart_forms', '', 'view') && !is_admin()) {
            access_denied('smart_forms');
        }

        $form = $this->smart_forms_model->get_forms($form_id);
        if (!$form) {
            show_404();
        }

        $data['title']       = _l('smart_forms_submissions') . ' — ' . $form->title;
        $data['form']        = $form;
        $data['stats']       = $this->smart_forms_model->get_form_stats($form_id);
        $data['submissions'] = $this->smart_forms_model->get_submissions($form_id);
        $data['qstats']      = $this->smart_forms_model->get_question_stats($form_id);

        $this->load->view('submissions', $data);
    }

    /* Single submission detail (AJAX, rendered in a modal) */
    public function submission($id)
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }
        if (!has_permission('smart_forms', '', 'view') && !is_admin()) {
            ajax_access_denied();
        }

        $submission = $this->smart_forms_model->get_submission($id);
        if (!$submission) {
            echo json_encode(['success' => false]);

            return;
        }

        $answers = [];
        foreach ($submission->answers as $a) {
            $value   = $a->answer_value;
            $decoded = json_decode((string) $value, true);
            $entry   = [
                'label' => $a->label,
                'type'  => $a->question_type,
                'value' => $value,
            ];

            if ($a->question_type === 'file' && is_array($decoded)) {
                $files = [];
                foreach ($decoded as $idx => $f) {
                    if (!empty($f['name'])) {
                        $files[] = [
                            'name' => $f['name'],
                            'url'  => admin_url('smart_forms/download_file/' . $a->id . '/' . $idx),
                        ];
                    }
                }
                $entry['files'] = $files;
                $entry['value'] = count($files) . ' file(s)';
            } elseif ($a->question_type === 'credentials' && is_array($decoded)) {
                $entry['credentials'] = [
                    'username' => isset($decoded['username']) ? $decoded['username'] : '',
                    'password' => $this->smart_forms_model->decrypt_credential(isset($decoded['password_enc']) ? $decoded['password_enc'] : ''),
                ];
                $entry['value'] = '';
            } elseif (is_array($decoded)) {
                $entry['value'] = implode(', ', array_map(function ($v) { return is_scalar($v) ? (string) $v : json_encode($v); }, $decoded));
            }

            $answers[] = $entry;
        }

        echo json_encode([
            'success'          => true,
            'id'               => $submission->id,
            'respondent_name'  => $submission->respondent_name ?: 'Anonymous',
            'respondent_email' => $submission->respondent_email ?: '',
            'created_at'       => _dt($submission->created_at),
            'duration_seconds' => $submission->duration_seconds,
            'feedback_rating'  => $submission->feedback_rating,
            'feedback_comment' => $submission->feedback_comment,
            'answers'          => $answers,
        ]);
    }

    /* Delete a submission (AJAX) */
    public function delete_submission($id)
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }
        if (!has_permission('smart_forms', '', 'delete') && !is_admin()) {
            echo json_encode(['success' => false]);

            return;
        }

        $this->smart_forms_model->delete_submission($id);
        echo json_encode(['success' => true]);
    }

    /* Duplicate a form */
    public function duplicate($id)
    {
        if (!has_permission('smart_forms', '', 'create') && !is_admin()) {
            access_denied('smart_forms');
        }

        $new_id = $this->smart_forms_model->duplicate_form($id);
        if ($new_id) {
            set_alert('success', _l('smart_forms_duplicated'));
            redirect(admin_url('smart_forms/form/' . $new_id));
        }

        set_alert('warning', _l('smart_forms_form_not_found'));
        redirect(admin_url('smart_forms'));
    }

    /* Delete a form */
    public function delete($id)
    {
        if (!has_permission('smart_forms', '', 'delete') && !is_admin()) {
            access_denied('smart_forms');
        }

        $this->smart_forms_model->delete_form($id);
        set_alert('success', _l('deleted', _l('smart_forms_form_lowercase')));
        redirect(admin_url('smart_forms'));
    }

    /* Toggle active/closed (AJAX) */
    public function toggle_status($id)
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }
        if (!has_permission('smart_forms', '', 'edit') && !is_admin()) {
            echo json_encode(['success' => false]);

            return;
        }

        $form = $this->smart_forms_model->get_forms($id);
        if (!$form) {
            echo json_encode(['success' => false]);

            return;
        }

        $new_status = ($form->status === 'active') ? 'closed' : 'active';
        $this->db->where('id', (int) $id)->update(db_prefix() . 'smart_forms', ['status' => $new_status]);

        echo json_encode(['success' => true, 'new_status' => $new_status]);
    }

    /* Assignments & tracking page (patients / customers, due dates, TAT) */
    public function assignments($form_id)
    {
        if (!has_permission('smart_forms', '', 'view') && !is_admin()) {
            access_denied('smart_forms');
        }

        $form = $this->smart_forms_model->get_forms($form_id);
        if (!$form) {
            show_404();
        }

        $data['title']       = 'Assignments — ' . $form->title;
        $data['form']        = $form;
        $data['assignments'] = $this->smart_forms_model->get_assignments($form_id);
        $data['astats']      = $this->smart_forms_model->get_assignment_stats($form_id);

        $this->load->view('assignments', $data);
    }

    /* Search patients or customer contacts for the assign panel (AJAX) */
    public function search_targets()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }
        if (!has_permission('smart_forms', '', 'view') && !is_admin()) {
            ajax_access_denied();
        }

        $type = $this->input->post('type');
        $q    = trim((string) $this->input->post('q'));

        if (strlen($q) < 2) {
            echo json_encode([]);

            return;
        }

        if ($type === 'patient') {
            echo json_encode($this->smart_forms_model->search_patients($q));
        } else {
            echo json_encode($this->smart_forms_model->search_contacts($q));
        }
    }

    /* Create an assignment (AJAX) */
    public function add_assignment()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }
        if (!has_permission('smart_forms', '', 'create') && !is_admin()) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);

            return;
        }

        $form_id = (int) $this->input->post('form_id');
        $form    = $this->smart_forms_model->get_forms($form_id);
        if (!$form) {
            echo json_encode(['success' => false, 'message' => 'Form not found']);

            return;
        }

        $id = $this->smart_forms_model->add_assignment($form_id, [
            'target_type' => $this->input->post('target_type'),
            'target_id'   => $this->input->post('target_id'),
            'name'        => $this->input->post('name'),
            'email'       => $this->input->post('email'),
            'phone'       => $this->input->post('phone'),
            'due_date'    => $this->input->post('due_date'),
        ]);

        $assignment = $this->smart_forms_model->get_assignment($id);

        echo json_encode([
            'success' => true,
            'id'      => $id,
            'link'    => site_url('form/' . $form->share_token . '?a=' . $assignment->token),
        ]);
    }

    /* Delete an assignment (AJAX) */
    public function delete_assignment($id)
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }
        if (!has_permission('smart_forms', '', 'delete') && !is_admin()) {
            echo json_encode(['success' => false]);

            return;
        }

        $this->smart_forms_model->delete_assignment($id);
        echo json_encode(['success' => true]);
    }

    /* Download an uploaded answer file (permission-gated) */
    public function download_file($answer_id, $idx = 0)
    {
        if (!has_permission('smart_forms', '', 'view') && !is_admin()) {
            access_denied('smart_forms');
        }

        $answer = $this->db->where('id', (int) $answer_id)->get(db_prefix() . 'smart_forms_answers')->row();
        if (!$answer) {
            show_404();
        }

        $files = json_decode((string) $answer->answer_value, true);
        if (!is_array($files) || !isset($files[(int) $idx])) {
            show_404();
        }

        $entry = $files[(int) $idx];
        // basename() guards against traversal in stored values
        $stored = basename(isset($entry['file']) ? $entry['file'] : '');
        $submission = $this->db->where('id', $answer->submission_id)->get(db_prefix() . 'smart_forms_submissions')->row();
        if (!$stored || !$submission) {
            show_404();
        }

        $path = FCPATH . 'uploads/smart_forms/' . (int) $submission->form_id . '/' . $stored;
        if (!is_file($path)) {
            show_404();
        }

        $this->load->helper('download');
        force_download(isset($entry['name']) ? $entry['name'] : $stored, file_get_contents($path));
    }

    /* Export submissions CSV */
    public function export_csv($form_id)
    {
        if (!has_permission('smart_forms', '', 'view') && !is_admin()) {
            access_denied('smart_forms');
        }

        $export = $this->smart_forms_model->export_submissions_csv($form_id);
        if (!$export) {
            set_alert('warning', _l('smart_forms_form_not_found'));
            redirect(admin_url('smart_forms'));
        }

        $filename = 'form_' . url_title($export['form_title'], '-', true) . '_' . date('Y-m-d') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM for Excel

        fputcsv($output, $export['headers']);
        foreach ($export['rows'] as $row) {
            fputcsv($output, $row);
        }

        fclose($output);
        die;
    }
}
