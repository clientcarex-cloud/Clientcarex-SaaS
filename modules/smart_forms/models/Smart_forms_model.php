<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Smart_forms_model extends App_Model
{
    private $forms_table;
    private $questions_table;
    private $submissions_table;
    private $answers_table;
    private $assignments_table;

    public function __construct()
    {
        parent::__construct();
        $p = db_prefix();
        $this->forms_table       = $p . 'smart_forms';
        $this->questions_table   = $p . 'smart_forms_questions';
        $this->submissions_table = $p . 'smart_forms_submissions';
        $this->answers_table     = $p . 'smart_forms_answers';
        $this->assignments_table = $p . 'smart_forms_assignments';

        // Self-healing: ensure tables/columns exist (v1 installs get v2 schema);
        // enable_chat present means chat leftovers still need dropping
        if (!$this->db->table_exists($this->forms_table)
            || !$this->db->table_exists($this->assignments_table)
            || $this->db->field_exists('enable_chat', $this->forms_table)
            || !$this->db->field_exists('layout', $this->forms_table)) {
            $CI = &get_instance();
            require(module_dir_path('smart_forms', 'install.php'));
        }
    }

    /**
     * Question types catalogue used by the builder and renderers.
     */
    public function question_types()
    {
        return [
            'short_text' => ['name' => 'Short Answer',       'icon' => 'fa-solid fa-minus'],
            'long_text'  => ['name' => 'Paragraph',          'icon' => 'fa-solid fa-align-left'],
            'yes_no'     => ['name' => 'Yes / No',           'icon' => 'fa-solid fa-toggle-on'],
            'true_false' => ['name' => 'True / False',       'icon' => 'fa-solid fa-scale-balanced'],
            'terms'      => ['name' => 'Terms & Conditions', 'icon' => 'fa-solid fa-file-contract'],
            'rating'     => ['name' => 'Star Rating',        'icon' => 'fa-solid fa-star'],
            'scale'      => ['name' => 'Scale (1-10)',       'icon' => 'fa-solid fa-sliders'],
            'radio'      => ['name' => 'Multiple Choice',    'icon' => 'fa-regular fa-circle-dot'],
            'checkbox'   => ['name' => 'Checkboxes',         'icon' => 'fa-regular fa-square-check'],
            'dropdown'   => ['name' => 'Dropdown',           'icon' => 'fa-solid fa-caret-down'],
            'number'     => ['name' => 'Number',             'icon' => 'fa-solid fa-hashtag'],
            'date'       => ['name' => 'Date',               'icon' => 'fa-regular fa-calendar'],
            'file'       => ['name' => 'File Upload',        'icon' => 'fa-solid fa-cloud-arrow-up'],
            'credentials'=> ['name' => 'Credentials',        'icon' => 'fa-solid fa-key'],
            'section'    => ['name' => 'Section Header',     'icon' => 'fa-solid fa-heading'],
        ];
    }

    // ═══════════════════════════════════════════
    //  FORMS CRUD
    // ═══════════════════════════════════════════

    public function get_forms($id = null)
    {
        if ($id !== null) {
            $form = $this->db->where('id', (int) $id)->get($this->forms_table)->row();
            if ($form) {
                $form->questions        = $this->get_questions($form->id);
                $form->question_count   = count($form->questions);
                $form->submission_count = $this->count_submissions($form->id);
            }

            return $form;
        }

        $forms = $this->db->order_by('created_at', 'DESC')->get($this->forms_table)->result();

        foreach ($forms as $form) {
            $form->question_count   = $this->db->where('form_id', $form->id)->count_all_results($this->questions_table);
            $form->submission_count = $this->count_submissions($form->id);
        }

        return $forms;
    }

    public function add_form($data)
    {
        $row = $this->_form_row($data);

        $row['share_token'] = $this->_generate_share_token();
        $row['created_by']  = get_staff_user_id();

        $this->db->insert($this->forms_table, $row);
        $form_id = $this->db->insert_id();

        if ($form_id && isset($data['questions'])) {
            $this->_save_questions($form_id, $data['questions']);
        }

        log_activity('Smart Form Created [ID: ' . $form_id . ', Title: ' . $row['title'] . ']');

        return $form_id;
    }

    public function update_form($id, $data)
    {
        $row               = $this->_form_row($data);
        $row['updated_by'] = get_staff_user_id();

        $this->db->where('id', (int) $id)->update($this->forms_table, $row);

        if (isset($data['questions'])) {
            $this->db->where('form_id', (int) $id)->delete($this->questions_table);
            $this->_save_questions($id, $data['questions']);
        }

        log_activity('Smart Form Updated [ID: ' . $id . ']');

        return true;
    }

    private function _form_row($data)
    {
        return [
            'title'            => $data['title'],
            'description'      => isset($data['description']) && $data['description'] !== '' ? $data['description'] : null,
            'category'         => isset($data['category']) ? $data['category'] : 'general',
            'status'           => in_array($data['status'] ?? 'draft', ['draft', 'active', 'closed']) ? ($data['status'] ?? 'draft') : 'draft',
            'require_identity' => !empty($data['require_identity']) ? 1 : 0,
            'one_per_user'     => !empty($data['one_per_user']) ? 1 : 0,
            'show_progress'    => !empty($data['show_progress']) ? 1 : 0,
            'layout'           => (isset($data['layout']) && $data['layout'] === 'steps') ? 'steps' : 'single',
            'collect_feedback' => !empty($data['collect_feedback']) ? 1 : 0,
            'feedback_prompt'  => !empty($data['feedback_prompt']) ? $data['feedback_prompt'] : null,
            'success_title'    => !empty($data['success_title']) ? $data['success_title'] : null,
            'success_message'  => !empty($data['success_message']) ? $data['success_message'] : null,
            'redirect_url'     => !empty($data['redirect_url']) ? $data['redirect_url'] : null,
            'max_submissions'  => !empty($data['max_submissions']) ? (int) $data['max_submissions'] : null,
            'starts_at'        => !empty($data['starts_at']) ? str_replace('T', ' ', $data['starts_at']) : null,
            'expires_at'       => !empty($data['expires_at']) ? str_replace('T', ' ', $data['expires_at']) : null,
        ];
    }

    public function delete_form($id)
    {
        $id = (int) $id;

        $submissions = $this->db->select('id')->where('form_id', $id)->get($this->submissions_table)->result();
        foreach ($submissions as $s) {
            $this->db->where('submission_id', $s->id)->delete($this->answers_table);
        }

        $this->db->where('form_id', $id)->delete($this->assignments_table);
        $this->db->where('form_id', $id)->delete($this->submissions_table);
        $this->db->where('form_id', $id)->delete($this->questions_table);
        $this->db->where('id', $id)->delete($this->forms_table);

        log_activity('Smart Form Deleted [ID: ' . $id . ']');

        return true;
    }

    public function duplicate_form($id)
    {
        $form = $this->get_forms($id);
        if (!$form) {
            return false;
        }

        $row = [
            'title'            => $form->title . ' (Copy)',
            'description'      => $form->description,
            'category'         => $form->category,
            'status'           => 'draft',
            'require_identity' => $form->require_identity,
            'one_per_user'     => $form->one_per_user,
            'show_progress'    => $form->show_progress,
            'layout'           => $form->layout,
            'collect_feedback' => $form->collect_feedback,
            'feedback_prompt'  => $form->feedback_prompt,
            'success_title'    => $form->success_title,
            'success_message'  => $form->success_message,
            'redirect_url'     => $form->redirect_url,
            'max_submissions'  => $form->max_submissions,
            'share_token'      => $this->_generate_share_token(),
            'created_by'       => get_staff_user_id(),
        ];

        $this->db->insert($this->forms_table, $row);
        $new_id = $this->db->insert_id();

        if ($new_id) {
            foreach ($form->questions as $q) {
                $this->db->insert($this->questions_table, [
                    'form_id'       => $new_id,
                    'question_type' => $q->question_type,
                    'label'         => $q->label,
                    'description'   => $q->description,
                    'options'       => $q->options,
                    'settings'      => $q->settings,
                    'is_required'   => $q->is_required,
                    'sort_order'    => $q->sort_order,
                ]);
            }
        }

        return $new_id;
    }

    // ═══════════════════════════════════════════
    //  QUESTIONS
    // ═══════════════════════════════════════════

    public function get_questions($form_id)
    {
        return $this->db->where('form_id', (int) $form_id)
            ->order_by('sort_order', 'ASC')
            ->order_by('id', 'ASC')
            ->get($this->questions_table)
            ->result();
    }

    private function _save_questions($form_id, $questions)
    {
        if (!is_array($questions)) {
            return;
        }

        $valid_types = array_keys($this->question_types());

        foreach ($questions as $order => $q) {
            if (empty($q['label']) || !in_array($q['question_type'] ?? '', $valid_types)) {
                continue;
            }

            $this->db->insert($this->questions_table, [
                'form_id'       => (int) $form_id,
                'question_type' => $q['question_type'],
                'label'         => $q['label'],
                'description'   => !empty($q['description']) ? $q['description'] : null,
                'options'       => !empty($q['options']) ? json_encode(array_values((array) $q['options']), JSON_UNESCAPED_UNICODE) : null,
                'settings'      => !empty($q['settings']) ? json_encode($q['settings'], JSON_UNESCAPED_UNICODE) : null,
                'is_required'   => !empty($q['is_required']) ? 1 : 0,
                'sort_order'    => $order,
            ]);
        }
    }

    // ═══════════════════════════════════════════
    //  PUBLIC ACCESS
    // ═══════════════════════════════════════════

    /**
     * Fetch an active, in-window form by its share token (public fill page).
     */
    public function get_form_by_token($token)
    {
        if (empty($token)) {
            return null;
        }

        $form = $this->db->where('share_token', $token)
            ->where('status', 'active')
            ->get($this->forms_table)
            ->row();

        if (!$form) {
            return null;
        }

        if ($form->starts_at && strtotime($form->starts_at) > time()) {
            return null;
        }
        if ($form->expires_at && strtotime($form->expires_at) < time()) {
            return null;
        }
        if ($form->max_submissions && $this->count_submissions($form->id) >= $form->max_submissions) {
            return null;
        }

        $form->questions = $this->get_questions($form->id);

        return $form;
    }

    public function has_user_submitted($form_id, $ip_address, $email = null)
    {
        $count = $this->db->where('form_id', (int) $form_id)
            ->where('ip_address', $ip_address)
            ->count_all_results($this->submissions_table);

        if ($count > 0) {
            return true;
        }

        if (!empty($email)) {
            $count = $this->db->where('form_id', (int) $form_id)
                ->where('respondent_email', $email)
                ->count_all_results($this->submissions_table);
            if ($count > 0) {
                return true;
            }
        }

        return false;
    }

    // ═══════════════════════════════════════════
    //  SUBMISSIONS
    // ═══════════════════════════════════════════

    public function add_submission($form_id, $data)
    {
        $this->db->insert($this->submissions_table, [
            'form_id'          => (int) $form_id,
            'respondent_name'  => isset($data['respondent_name']) ? $data['respondent_name'] : null,
            'respondent_email' => isset($data['respondent_email']) ? $data['respondent_email'] : null,
            'staff_id'         => !empty($data['staff_id']) ? (int) $data['staff_id'] : null,
            'ip_address'       => $this->input->ip_address(),
            'user_agent'       => substr((string) $this->input->user_agent(), 0, 500),
            'duration_seconds' => isset($data['duration_seconds']) ? (int) $data['duration_seconds'] : null,
        ]);

        $submission_id = $this->db->insert_id();

        if ($submission_id && !empty($data['answers'])) {
            foreach ($data['answers'] as $question_id => $value) {
                $this->db->insert($this->answers_table, [
                    'submission_id'  => $submission_id,
                    'question_id'    => (int) $question_id,
                    'answer_value'   => is_array($value) ? json_encode(array_values($value), JSON_UNESCAPED_UNICODE) : $value,
                    'answer_numeric' => (!is_array($value) && is_numeric($value)) ? (float) $value : null,
                ]);
            }
        }

        return $submission_id;
    }

    /**
     * Store the post-submit quick feedback. Only writes once per submission.
     */
    public function save_quick_feedback($submission_id, $rating, $comment = null)
    {
        $rating = (int) $rating;
        if ($rating < 1 || $rating > 5) {
            return false;
        }

        $this->db->where('id', (int) $submission_id)
            ->where('feedback_rating IS NULL', null, false)
            ->update($this->submissions_table, [
                'feedback_rating'  => $rating,
                'feedback_comment' => $comment !== null && $comment !== '' ? substr($comment, 0, 500) : null,
                'feedback_at'      => date('Y-m-d H:i:s'),
            ]);

        return $this->db->affected_rows() > 0;
    }

    public function get_submissions($form_id)
    {
        return $this->db->where('form_id', (int) $form_id)
            ->order_by('created_at', 'DESC')
            ->get($this->submissions_table)
            ->result();
    }

    public function get_submission($id)
    {
        $submission = $this->db->where('id', (int) $id)->get($this->submissions_table)->row();

        if ($submission) {
            $submission->answers = $this->db->select('a.*, q.label, q.question_type, q.options as q_options, q.settings as q_settings, q.sort_order')
                ->from($this->answers_table . ' a')
                ->join($this->questions_table . ' q', 'q.id = a.question_id', 'left')
                ->where('a.submission_id', $submission->id)
                ->order_by('q.sort_order', 'ASC')
                ->get()
                ->result();
        }

        return $submission;
    }

    public function delete_submission($id)
    {
        $this->db->where('submission_id', (int) $id)->delete($this->answers_table);
        $this->db->where('id', (int) $id)->delete($this->submissions_table);

        return true;
    }

    public function count_submissions($form_id)
    {
        return $this->db->where('form_id', (int) $form_id)->count_all_results($this->submissions_table);
    }

    // ═══════════════════════════════════════════
    //  STATS
    // ═══════════════════════════════════════════

    /**
     * Dashboard summary cards for the manage page.
     */
    public function get_summary()
    {
        $summary = [
            'total_forms'       => $this->db->count_all_results($this->forms_table),
            'active_forms'      => $this->db->where('status', 'active')->count_all_results($this->forms_table),
            'total_submissions' => $this->db->count_all_results($this->submissions_table),
            'today_submissions' => $this->db->where('DATE(created_at)', date('Y-m-d'))->count_all_results($this->submissions_table),
            'avg_feedback'      => 0,
        ];

        $row = $this->db->select('AVG(feedback_rating) as avg_rating')
            ->where('feedback_rating IS NOT NULL', null, false)
            ->get($this->submissions_table)
            ->row();
        $summary['avg_feedback'] = $row && $row->avg_rating ? round((float) $row->avg_rating, 1) : 0;

        return $summary;
    }

    /**
     * Per-form stats: submission totals, avg duration and quick-feedback score.
     */
    public function get_form_stats($form_id)
    {
        $form_id = (int) $form_id;

        $row = $this->db->select('COUNT(*) as total, AVG(duration_seconds) as avg_duration, AVG(feedback_rating) as avg_feedback, SUM(feedback_rating IS NOT NULL) as feedback_count', false)
            ->where('form_id', $form_id)
            ->get($this->submissions_table)
            ->row();

        return [
            'total'          => $row ? (int) $row->total : 0,
            'avg_duration'   => $row && $row->avg_duration ? (int) round($row->avg_duration) : 0,
            'avg_feedback'   => $row && $row->avg_feedback ? round((float) $row->avg_feedback, 1) : 0,
            'feedback_count' => $row ? (int) $row->feedback_count : 0,
        ];
    }

    /**
     * Per-question aggregates for the submissions page (PHP-side so that
     * checkbox JSON arrays are handled too). Fine at this scale.
     */
    public function get_question_stats($form_id)
    {
        $questions = $this->get_questions($form_id);
        if (empty($questions)) {
            return [];
        }

        $answers = $this->db->select('a.question_id, a.answer_value, a.answer_numeric')
            ->from($this->answers_table . ' a')
            ->join($this->submissions_table . ' s', 's.id = a.submission_id')
            ->where('s.form_id', (int) $form_id)
            ->get()
            ->result();

        $by_question = [];
        foreach ($answers as $a) {
            $by_question[$a->question_id][] = $a;
        }

        $stats = [];
        foreach ($questions as $q) {
            if ($q->question_type === 'section') {
                continue;
            }

            $rows = isset($by_question[$q->id]) ? $by_question[$q->id] : [];
            $stat = [
                'question' => $q,
                'total'    => count($rows),
                'average'  => null,
                'choices'  => [],
            ];

            if (in_array($q->question_type, ['rating', 'scale', 'number'])) {
                $sum = 0;
                $n   = 0;
                foreach ($rows as $r) {
                    if ($r->answer_numeric !== null) {
                        $sum += (float) $r->answer_numeric;
                        $n++;
                    }
                }
                $stat['average'] = $n ? round($sum / $n, 1) : null;
            }

            if (in_array($q->question_type, ['yes_no', 'true_false', 'terms', 'radio', 'dropdown', 'checkbox', 'rating', 'scale'])) {
                foreach ($rows as $r) {
                    $values = [$r->answer_value];
                    if ($q->question_type === 'checkbox') {
                        $decoded = json_decode((string) $r->answer_value, true);
                        $values  = is_array($decoded) ? $decoded : $values;
                    }
                    foreach ($values as $v) {
                        $v = (string) $v;
                        if ($v === '') {
                            continue;
                        }
                        if (!isset($stat['choices'][$v])) {
                            $stat['choices'][$v] = 0;
                        }
                        $stat['choices'][$v]++;
                    }
                }
                arsort($stat['choices']);
            }

            $stats[] = $stat;
        }

        return $stats;
    }

    // ═══════════════════════════════════════════
    //  EXPORT
    // ═══════════════════════════════════════════

    public function export_submissions_csv($form_id)
    {
        $form = $this->get_forms($form_id);
        if (!$form) {
            return null;
        }

        $headers = ['#', 'Name', 'Email', 'Submitted At', 'Duration (sec)', 'Quick Feedback (1-5)', 'Feedback Comment'];
        foreach ($form->questions as $q) {
            if ($q->question_type !== 'section') {
                $headers[] = $q->label;
            }
        }

        $rows = [];
        foreach ($this->get_submissions($form_id) as $s) {
            $answers_map = [];
            foreach ($this->db->where('submission_id', $s->id)->get($this->answers_table)->result() as $a) {
                $value = $a->answer_value;
                $decoded = json_decode((string) $value, true);
                if (is_array($decoded)) {
                    if (isset($decoded['username'])) {
                        // credentials — export the username only, never the password
                        $value = $decoded['username'];
                    } else {
                        $parts = [];
                        foreach ($decoded as $item) {
                            if (is_array($item)) {
                                $parts[] = isset($item['name']) ? $item['name'] : json_encode($item);
                            } else {
                                $parts[] = (string) $item;
                            }
                        }
                        $value = implode(', ', $parts);
                    }
                }
                $answers_map[$a->question_id] = $value;
            }

            $row = [
                $s->id,
                $s->respondent_name ?: 'Anonymous',
                $s->respondent_email ?: '-',
                $s->created_at,
                $s->duration_seconds ?: '',
                $s->feedback_rating ?: '',
                $s->feedback_comment ?: '',
            ];

            foreach ($form->questions as $q) {
                if ($q->question_type !== 'section') {
                    $row[] = isset($answers_map[$q->id]) ? $answers_map[$q->id] : '';
                }
            }

            $rows[] = $row;
        }

        return ['headers' => $headers, 'rows' => $rows, 'form_title' => $form->title];
    }

    // ═══════════════════════════════════════════
    //  ASSIGNMENTS (link forms to patients / customers, due date, TAT)
    // ═══════════════════════════════════════════

    public function add_assignment($form_id, $data)
    {
        $this->db->insert($this->assignments_table, [
            'form_id'     => (int) $form_id,
            'target_type' => in_array($data['target_type'] ?? '', ['patient', 'contact', 'anonymous']) ? $data['target_type'] : 'anonymous',
            'target_id'   => !empty($data['target_id']) ? (int) $data['target_id'] : null,
            'name'        => isset($data['name']) ? substr((string) $data['name'], 0, 255) : null,
            'email'       => isset($data['email']) ? substr((string) $data['email'], 0, 255) : null,
            'phone'       => isset($data['phone']) ? substr((string) $data['phone'], 0, 50) : null,
            'token'       => $this->_generate_assignment_token(),
            'due_date'    => !empty($data['due_date']) ? str_replace('T', ' ', $data['due_date']) : null,
            'created_by'  => is_staff_logged_in() ? get_staff_user_id() : null,
        ]);

        return $this->db->insert_id();
    }

    public function get_assignment($id)
    {
        return $this->db->where('id', (int) $id)->get($this->assignments_table)->row();
    }

    public function get_assignment_by_token($token, $form_id = null)
    {
        if (empty($token)) {
            return null;
        }
        $this->db->where('token', $token);
        if ($form_id !== null) {
            $this->db->where('form_id', (int) $form_id);
        }

        return $this->db->get($this->assignments_table)->row();
    }

    /**
     * Assignments of a form, newest first. TAT = completed_at - created_at.
     */
    public function get_assignments($form_id)
    {
        $rows = $this->db->where('form_id', (int) $form_id)
            ->order_by('created_at', 'DESC')
            ->get($this->assignments_table)
            ->result();

        foreach ($rows as $r) {
            $r->tat_seconds = ($r->completed_at) ? max(0, strtotime($r->completed_at) - strtotime($r->created_at)) : null;
            $r->is_overdue  = ($r->due_date && $r->status !== 'completed' && strtotime($r->due_date) < time());
        }

        return $rows;
    }

    public function get_assignment_stats($form_id)
    {
        $rows = $this->get_assignments($form_id);

        $stats = ['total' => count($rows), 'completed' => 0, 'in_progress' => 0, 'pending' => 0, 'overdue' => 0, 'avg_tat' => null];
        $tats  = [];

        foreach ($rows as $r) {
            if ($r->status === 'completed') {
                $stats['completed']++;
                if ($r->tat_seconds !== null) {
                    $tats[] = $r->tat_seconds;
                }
            } elseif ($r->status === 'in_progress') {
                $stats['in_progress']++;
            } else {
                $stats['pending']++;
            }
            if ($r->is_overdue) {
                $stats['overdue']++;
            }
        }

        if ($tats) {
            $stats['avg_tat'] = (int) round(array_sum($tats) / count($tats));
        }

        return $stats;
    }

    public function delete_assignment($id)
    {
        $this->db->where('id', (int) $id)->delete($this->assignments_table);

        return true;
    }

    /**
     * Save (or update) a respondent draft against an assignment.
     */
    public function save_draft($assignment_id, $answers, $progress)
    {
        $update = [
            'draft_data'     => json_encode($answers, JSON_UNESCAPED_UNICODE),
            'draft_progress' => max(0, min(100, (int) $progress)),
            'draft_saved_at' => date('Y-m-d H:i:s'),
        ];

        $assignment = $this->get_assignment($assignment_id);
        if (!$assignment || $assignment->status === 'completed') {
            return false;
        }

        if ($assignment->status === 'pending') {
            $update['status'] = 'in_progress';
        }
        if (!$assignment->started_at) {
            $update['started_at'] = date('Y-m-d H:i:s');
        }

        $this->db->where('id', (int) $assignment_id)->update($this->assignments_table, $update);

        return true;
    }

    /**
     * Mark an assignment completed after its submission is stored.
     */
    public function complete_assignment($assignment_id, $submission_id)
    {
        $this->db->where('id', (int) $assignment_id)
            ->where('status !=', 'completed')
            ->update($this->assignments_table, [
                'status'        => 'completed',
                'submission_id' => (int) $submission_id,
                'completed_at'  => date('Y-m-d H:i:s'),
                'draft_data'    => null,
            ]);

        return $this->db->affected_rows() > 0;
    }

    // ═══════════════════════════════════════════
    //  PATIENT / CUSTOMER SEARCH (admin assign modal)
    // ═══════════════════════════════════════════

    /**
     * Patients are Perfex clients enriched by the patients module
     * (same approach as smart_pdf) — works even without that module.
     */
    public function search_patients($q)
    {
        $c  = db_prefix() . 'clients';
        $pe = db_prefix() . 'patients_extra';
        $has_extra = $this->db->table_exists($pe);

        $this->db->select($c . '.userid as id, ' . $c . '.company as name, ' . $c . '.phonenumber as phone');
        $this->db->select('(SELECT email FROM ' . db_prefix() . 'contacts WHERE userid=' . $c . '.userid AND is_primary=1 LIMIT 1) as email');
        if ($has_extra) {
            $this->db->select($pe . '.mr_number, ' . $pe . '.mobile_number');
        }
        $this->db->from($c);
        if ($has_extra) {
            $this->db->join($pe, $pe . '.patient_id = ' . $c . '.userid', 'left');
        }
        $this->db->group_start();
        $this->db->like($c . '.company', $q);
        $this->db->or_like($c . '.phonenumber', $q);
        if ($has_extra) {
            $this->db->or_like($pe . '.mobile_number', $q);
            $this->db->or_like($pe . '.mr_number', $q);
        }
        $this->db->group_end();
        $this->db->order_by($c . '.userid', 'desc');
        $this->db->limit(10);

        return $this->db->get()->result_array();
    }

    /**
     * Customer contacts (tblcontacts joined to tblclients).
     */
    public function search_contacts($q)
    {
        $ct = db_prefix() . 'contacts';
        $cl = db_prefix() . 'clients';

        $this->db->select($ct . '.id, CONCAT(' . $ct . '.firstname, " ", ' . $ct . '.lastname) as name, ' . $ct . '.email, ' . $ct . '.phonenumber as phone, ' . $cl . '.company', false);
        $this->db->from($ct);
        $this->db->join($cl, $cl . '.userid = ' . $ct . '.userid', 'left');
        $this->db->where($ct . '.active', 1);
        $this->db->group_start();
        $this->db->like($ct . '.firstname', $q);
        $this->db->or_like($ct . '.lastname', $q);
        $this->db->or_like($ct . '.email', $q);
        $this->db->or_like($ct . '.phonenumber', $q);
        $this->db->or_like($cl . '.company', $q);
        $this->db->group_end();
        $this->db->limit(10);

        return $this->db->get()->result_array();
    }

    // ═══════════════════════════════════════════
    //  CREDENTIALS (encrypted at rest)
    // ═══════════════════════════════════════════

    public function encrypt_credential($plain)
    {
        if ($plain === '' || $plain === null) {
            return '';
        }
        $this->load->library('encryption');

        return $this->encryption->encrypt($plain);
    }

    public function decrypt_credential($cipher)
    {
        if ($cipher === '' || $cipher === null) {
            return '';
        }
        $this->load->library('encryption');
        $plain = $this->encryption->decrypt($cipher);

        return $plain === false ? '' : $plain;
    }

    // ═══════════════════════════════════════════
    //  SIGNING (quick feedback endpoint is public — sign the submission id)
    // ═══════════════════════════════════════════

    public function sign_submission($submission_id, $share_token)
    {
        return substr(hash_hmac('sha256', 'sf:' . (int) $submission_id, $share_token . '_sf_salt_q9'), 0, 12);
    }

    public function verify_submission_sig($submission_id, $share_token, $sig)
    {
        return hash_equals($this->sign_submission($submission_id, $share_token), (string) $sig);
    }

    // ═══════════════════════════════════════════
    //  HELPERS
    // ═══════════════════════════════════════════

    private function _generate_assignment_token()
    {
        $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        do {
            $token = '';
            for ($i = 0; $i < 16; $i++) {
                $token .= $chars[random_int(0, 61)];
            }
            $exists = $this->db->where('token', $token)->count_all_results($this->assignments_table);
        } while ($exists > 0);

        return $token;
    }

    private function _generate_share_token()
    {
        $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        do {
            $token = '';
            for ($i = 0; $i < 8; $i++) {
                $token .= $chars[random_int(0, 61)];
            }
            $exists = $this->db->where('share_token', $token)->count_all_results($this->forms_table);
        } while ($exists > 0);

        return $token;
    }
}
