<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Public-facing form controller — no authentication required.
 *
 * Short URL: /form/{share_token} (routed in application/config/routes.php)
 * Personal assigned links append ?a={assignment_token} which unlocks
 * prefill, save-as-draft and due-date tracking.
 *
 * After a successful submit the success page collects an optional quick
 * feedback (1-5 + comment); the submission id is HMAC-signed with the
 * form's share token so it cannot be tampered.
 */
class Smart_forms_public extends App_Controller
{
    private $upload_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'zip', 'ppt', 'pptx'];
    private $upload_max_bytes = 10485760; // 10 MB

    public function __construct()
    {
        parent::__construct();
        $this->load->model('smart_forms/smart_forms_model', 'smart_forms_model');
    }

    /* Display + submit a public form */
    public function fill($token = '')
    {
        $form = $this->smart_forms_model->get_form_by_token($token);

        if (!$form) {
            $this->_render_error_page('Form Not Available', 'This form does not exist, is not published yet, or is no longer accepting responses.');

            return;
        }

        // Personal assignment link (?a= on GET, hidden _a field on POST)
        $a_token    = $this->input->post('_a') ?: $this->input->get('a');
        $assignment = null;
        if ($a_token) {
            $assignment = $this->smart_forms_model->get_assignment_by_token($a_token, $form->id);
            if (!$assignment) {
                $this->_render_error_page('Invalid Link', 'This personal form link is not valid. Please contact the sender for a fresh link.');

                return;
            }
            if ($assignment->status === 'completed') {
                $this->_render_error_page('Already Completed', 'This form has already been submitted from this personal link. Thank you!');

                return;
            }
        }

        // One submission per person (by IP / email) — skipped for assigned
        // links, which are limited per-assignment instead.
        if (!$assignment && !empty($form->one_per_user)) {
            $email = $this->input->post('respondent_email') ?: null;
            if ($this->smart_forms_model->has_user_submitted($form->id, $this->input->ip_address(), $email)) {
                $this->_render_error_page('Already Submitted', 'You have already submitted a response for this form. Only one submission per person is allowed.');

                return;
            }
        }

        if ($this->input->post()) {
            $this->_handle_submit($form, $assignment);

            return;
        }

        $data['title']      = $form->title;
        $data['form']       = $form;
        $data['assignment'] = $assignment;
        $data['draft']      = ($assignment && $assignment->draft_data) ? (json_decode($assignment->draft_data, true) ?: []) : [];
        $this->load->view('public_fill', $data);
    }

    private function _handle_submit($form, $assignment = null)
    {
        // Answers arrive as q_{question_id}; re-fetch without XSS filtering
        // (views escape on output) so free text survives intact.
        $post = $this->input->post(null, false);

        $answers = [];
        foreach ($form->questions as $q) {
            if ($q->question_type === 'section') {
                continue;
            }

            $key      = 'q_' . $q->id;
            $settings = json_decode((string) $q->settings, true) ?: [];

            // ── File uploads ──
            if ($q->question_type === 'file') {
                $files = $this->_process_uploads($form->id, $key, $settings);
                if (is_string($files)) {
                    $this->_render_error_page('Upload Failed', $files);

                    return;
                }
                if ($q->is_required && !count($files)) {
                    $this->_render_error_page('Missing Required Answer', 'A required file upload was left empty. Please go back and attach the file.');

                    return;
                }
                if (count($files)) {
                    $answers[$q->id] = $files;
                }

                continue;
            }

            // ── Credentials (username + password, password encrypted) ──
            if ($q->question_type === 'credentials') {
                $user = isset($post[$key . '_user']) ? trim((string) $post[$key . '_user']) : '';
                $pass = isset($post[$key . '_pass']) ? (string) $post[$key . '_pass'] : '';

                if ($q->is_required && ($user === '' || $pass === '')) {
                    $this->_render_error_page('Missing Required Answer', 'A required credentials field was left incomplete. Please go back and fill both username and password.');

                    return;
                }
                if ($user !== '' || $pass !== '') {
                    $answers[$q->id] = [
                        'username'     => substr($user, 0, 255),
                        'password_enc' => $this->smart_forms_model->encrypt_credential($pass),
                    ];
                }

                continue;
            }

            $value = isset($post[$key]) ? $post[$key] : null;

            if (is_array($value)) {
                $value = array_values(array_filter(array_map('trim', array_map('strval', $value)), 'strlen'));
            } elseif ($value !== null) {
                $value = trim((string) $value);
            }

            $empty = ($value === null || $value === '' || (is_array($value) && !count($value)));

            // Server-side required validation (client validates too)
            if ($q->is_required && $empty) {
                $this->_render_error_page('Missing Required Answer', 'A required question was left unanswered. Please go back and complete the form.');

                return;
            }

            // Terms with must_accept: Decline blocks the submission
            if ($q->question_type === 'terms' && !$empty) {
                if (!empty($settings['must_accept']) && $value !== 'Accepted') {
                    $this->_render_error_page('Terms Not Accepted', 'You must accept the terms and conditions to submit this form.');

                    return;
                }
            }

            if (!$empty) {
                $answers[$q->id] = $value;
            }
        }

        $submission_data = [
            'respondent_name'  => isset($post['respondent_name']) ? substr(trim((string) $post['respondent_name']), 0, 255) : null,
            'respondent_email' => isset($post['respondent_email']) ? substr(trim((string) $post['respondent_email']), 0, 255) : null,
            'duration_seconds' => isset($post['duration_seconds']) ? (int) $post['duration_seconds'] : null,
            'answers'          => $answers,
        ];

        // Assigned links: trust the assignment identity over free-typed values
        if ($assignment && $assignment->name) {
            $submission_data['respondent_name']  = $assignment->name;
            $submission_data['respondent_email'] = $assignment->email;
        }

        if (is_staff_logged_in()) {
            $submission_data['staff_id'] = get_staff_user_id();
        }

        $submission_id = $this->smart_forms_model->add_submission($form->id, $submission_data);

        if (!$submission_id) {
            $this->_render_error_page('Something Went Wrong', 'Your response could not be saved. Please try again.');

            return;
        }

        if ($assignment) {
            $this->smart_forms_model->complete_assignment($assignment->id, $submission_id);
        }

        $data['title']         = $form->success_title ?: 'Thank You!';
        $data['form']          = $form;
        $data['submission_id'] = $submission_id;
        $data['sig']           = $this->smart_forms_model->sign_submission($submission_id, $form->share_token);
        $this->load->view('public_success', $data);
    }

    /**
     * Validate + store multi-file uploads for one question.
     * Returns array of ['name' => original, 'file' => stored] or an
     * error string.
     */
    private function _process_uploads($form_id, $field, $settings)
    {
        if (empty($_FILES[$field]) || !is_array($_FILES[$field]['name'])) {
            return [];
        }

        $max_files = !empty($settings['max_files']) ? (int) $settings['max_files'] : 3;
        $dir       = FCPATH . 'uploads/smart_forms/' . (int) $form_id . '/';

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
            @file_put_contents($dir . 'index.html', '');
        }

        $stored = [];
        $names  = $_FILES[$field]['name'];

        foreach ($names as $i => $orig_name) {
            if ($_FILES[$field]['error'][$i] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if (count($stored) >= $max_files) {
                break;
            }
            if ($_FILES[$field]['error'][$i] !== UPLOAD_ERR_OK) {
                return 'One of the files failed to upload. Please try again.';
            }
            if ($_FILES[$field]['size'][$i] > $this->upload_max_bytes) {
                return 'File "' . html_escape($orig_name) . '" exceeds the 10 MB limit.';
            }

            $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
            if (!in_array($ext, $this->upload_exts)) {
                return 'File type ".' . html_escape($ext) . '" is not allowed.';
            }

            $safe_base = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($orig_name));
            $filename  = bin2hex(random_bytes(8)) . '_' . $safe_base;

            if (!move_uploaded_file($_FILES[$field]['tmp_name'][$i], $dir . $filename)) {
                return 'Could not store an uploaded file. Please try again.';
            }

            $stored[] = ['name' => $orig_name, 'file' => $filename];
        }

        return $stored;
    }

    /* Save-as-draft (AJAX). Creates an anonymous assignment when needed. */
    public function save_draft()
    {
        header('Content-Type: application/json');

        $token = (string) $this->input->post('token');
        $form  = $this->smart_forms_model->get_form_by_token($token);

        if (!$form) {
            echo json_encode(['success' => false]);

            return;
        }

        $answers  = json_decode((string) $this->input->post('answers', false), true);
        $progress = (int) $this->input->post('progress');
        if (!is_array($answers)) {
            $answers = [];
        }

        $a_token    = (string) $this->input->post('a');
        $assignment = $a_token ? $this->smart_forms_model->get_assignment_by_token($a_token, $form->id) : null;

        $created = false;
        if (!$assignment) {
            $id = $this->smart_forms_model->add_assignment($form->id, [
                'target_type' => 'anonymous',
                'name'        => $this->input->post('respondent_name') ?: null,
                'email'       => $this->input->post('respondent_email') ?: null,
            ]);
            $assignment = $this->smart_forms_model->get_assignment($id);
            $created    = true;
        }

        $ok = $this->smart_forms_model->save_draft($assignment->id, $answers, $progress);

        echo json_encode([
            'success'    => (bool) $ok,
            'a'          => $assignment->token,
            'resume_url' => site_url('form/' . $form->share_token . '?a=' . $assignment->token),
            'created'    => $created,
        ]);
    }

    /* Post-submit quick feedback (AJAX from the success page) */
    public function quick_feedback()
    {
        $token         = (string) $this->input->post('token');
        $submission_id = (int) $this->input->post('sid');
        $sig           = (string) $this->input->post('sig');
        $rating        = (int) $this->input->post('rating');
        $comment       = $this->input->post('comment', false);

        header('Content-Type: application/json');

        // Look the form up directly — feedback must work even if the form
        // just hit its max_submissions cap with this very submission.
        $form = $this->db->where('share_token', $token)
            ->get(db_prefix() . 'smart_forms')
            ->row();

        if (!$form || !$submission_id || !$this->smart_forms_model->verify_submission_sig($submission_id, $form->share_token, $sig)) {
            echo json_encode(['success' => false]);

            return;
        }

        $saved = $this->smart_forms_model->save_quick_feedback($submission_id, $rating, is_string($comment) ? trim($comment) : null);

        echo json_encode(['success' => (bool) $saved]);
    }

    private function _render_error_page($title, $message)
    {
        $data['title']   = $title;
        $data['message'] = $message;
        $this->load->view('public_error', $data);
    }
}
