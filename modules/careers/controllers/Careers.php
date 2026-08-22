<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Careers — admin area.
 *
 * Screens: dashboard, openings, job editor, applications (table), pipeline
 * (kanban), application detail, interviews, setup (departments & locations)
 * and settings. Every mutating endpoint re-checks its capability; the sidebar
 * hiding a link is a convenience, never the access control.
 */
class Careers extends AdminController
{
    public function __construct()
    {
        parent::__construct();

        $this->load->model('careers/careers_model');

        if (!careers_can_access()) {
            access_denied('Careers');
        }
    }

    /* ═══════════════════════════ Dashboard ════════════════════════════════ */

    public function index()
    {
        $data['dashboard'] = $this->careers_model->dashboard($this->input->get('days') ?: 30);
        $data['title']     = _l('careers');

        $this->load->view('dashboard', $data);
    }

    /**
     * Live poller for the header badge and the "new application" toast.
     * Returns only what the UI needs to render a notification.
     */
    public function live()
    {
        $since = (string) $this->input->get('since');
        $rows  = [];

        foreach ($this->careers_model->applications_since($since) as $application) {
            $rows[] = [
                'id'        => (int) $application->id,
                'name'      => $application->name,
                'job_title' => $application->job_title,
                'url'       => admin_url('careers/application/' . $application->id),
                'ago'       => careers_time_ago($application->created_at),
                'created'   => $application->created_at,
            ];
        }

        $this->json([
            'success'  => true,
            'now'      => date('Y-m-d H:i:s'),
            'new_total' => (int) $this->db->where('stage', 'new')->count_all_results(db_prefix() . 'careers_applications'),
            'items'    => $rows,
        ]);
    }

    /* ═════════════════════════════ Openings ═══════════════════════════════ */

    public function jobs()
    {
        $filters = [
            'status'     => $this->input->get('status'),
            'type'       => $this->input->get('type'),
            'department' => $this->input->get('department'),
            'location'   => $this->input->get('location'),
            'work_mode'  => $this->input->get('work_mode'),
            'search'     => $this->input->get('search'),
        ];

        $per_page = 25;
        $page     = max(1, (int) $this->input->get('page'));

        $data['filters']     = $filters;
        $data['total']       = $this->careers_model->count_jobs($filters);
        $data['jobs']        = $this->careers_model->jobs($filters, $per_page, ($page - 1) * $per_page);
        $data['page']        = $page;
        $data['pages']       = max(1, (int) ceil($data['total'] / $per_page));
        $data['departments'] = $this->careers_model->departments();
        $data['locations']   = $this->careers_model->locations();
        $data['counts']      = $this->job_status_counts();
        $data['title']       = _l('careers_openings');

        $this->load->view('jobs', $data);
    }

    private function job_status_counts()
    {
        $counts = ['all' => 0];
        foreach (array_keys(careers_job_statuses()) as $status) {
            $counts[$status] = 0;
        }

        $rows = $this->db->query('SELECT status, COUNT(*) AS c FROM ' . db_prefix() . 'careers_jobs GROUP BY status')->result();
        foreach ($rows as $row) {
            $counts[$row->status] = (int) $row->c;
            $counts['all'] += (int) $row->c;
        }

        return $counts;
    }

    /**
     * Job editor. POST saves; GET renders the form for a new or existing
     * posting.
     */
    public function job($id = null)
    {
        $id = (int) $id;

        if ($this->input->post()) {
            if (!careers_can($id ? 'edit' : 'create')) {
                access_denied('Careers');
            }

            $post = $this->input->post(null, false); // rich text fields keep their HTML; careers_safe_html() filters it
            $saved = $this->careers_model->save_job($post, $id);

            if (!$saved) {
                set_alert('danger', _l('careers_job_save_failed'));
                redirect(admin_url('careers/job' . ($id ? '/' . $id : '')));
            }

            set_alert('success', $id ? _l('careers_job_updated') : _l('careers_job_created'));
            redirect(admin_url('careers/job/' . $saved));
        }

        if ($id) {
            $data['job'] = $this->careers_model->job($id);
            if (!$data['job']) {
                show_404();
            }
            $data['questions'] = $this->careers_model->questions($id);
            $data['title']     = $data['job']->title;
        } else {
            if (!careers_can('create')) {
                access_denied('Careers');
            }
            $data['job']       = null;
            $data['questions'] = [];
            $data['title']     = _l('careers_new_job');
        }

        $data['departments'] = $this->careers_model->departments();
        $data['locations']   = $this->careers_model->locations();
        $data['staff']       = $this->staff_list();

        $this->load->view('job_form', $data);
    }

    public function job_status($id, $status)
    {
        if (!careers_can('edit')) {
            access_denied('Careers');
        }

        $this->careers_model->set_job_status($id, $status);

        if ($this->input->is_ajax_request()) {
            $this->json(['success' => true]);
        }

        set_alert('success', _l('careers_job_updated'));
        redirect($_SERVER['HTTP_REFERER'] ?? admin_url('careers/jobs'));
    }

    public function duplicate_job($id)
    {
        if (!careers_can('create')) {
            access_denied('Careers');
        }

        $new_id = $this->careers_model->duplicate_job($id);

        if (!$new_id) {
            set_alert('danger', _l('careers_job_save_failed'));
            redirect(admin_url('careers/jobs'));
        }

        set_alert('success', _l('careers_job_duplicated'));
        redirect(admin_url('careers/job/' . $new_id));
    }

    public function delete_job($id)
    {
        if (!careers_can('delete')) {
            access_denied('Careers');
        }

        $this->careers_model->delete_job($id);
        set_alert('success', _l('careers_job_deleted'));

        redirect(admin_url('careers/jobs'));
    }

    /* ═══════════════════════════ Applications ═════════════════════════════ */

    public function applications()
    {
        $filters  = $this->application_filters();
        $per_page = 30;
        $page     = max(1, (int) $this->input->get('page'));

        $data['filters']      = $filters;
        $data['total']        = $this->careers_model->count_applications($filters);
        $data['applications'] = $this->careers_model->applications($filters, $per_page, ($page - 1) * $per_page);
        $data['page']         = $page;
        $data['pages']        = max(1, (int) ceil($data['total'] / $per_page));
        $data['jobs']         = $this->careers_model->jobs(['not_status' => ['archived']]);
        $data['staff']        = $this->staff_list();
        $data['stage_counts'] = $this->careers_model->stage_counts(['job' => $filters['job']]);
        $data['title']        = _l('careers_applications');

        $this->load->view('applications', $data);
    }

    private function application_filters()
    {
        return [
            'job'        => (int) $this->input->get('job'),
            'stage'      => (string) $this->input->get('stage'),
            'type'       => (string) $this->input->get('type'),
            'department' => (int) $this->input->get('department'),
            'rating'     => (int) $this->input->get('rating'),
            'assigned'   => (int) $this->input->get('assigned'),
            'starred'    => (int) $this->input->get('starred'),
            'search'     => (string) $this->input->get('search'),
            'from'       => (string) $this->input->get('from'),
            'to'         => (string) $this->input->get('to'),
            'sort'       => (string) $this->input->get('sort'),
            'direction'  => (string) $this->input->get('direction'),
        ];
    }

    /**
     * Kanban board. Columns are capped per stage — a pipeline with thousands of
     * "new" applications must not try to render them all — and the count in the
     * header always reflects the true total.
     */
    public function pipeline()
    {
        $job_id = (int) $this->input->get('job');
        $limit  = 60;

        $columns = [];
        foreach (careers_board_stages() as $stage) {
            $columns[$stage] = $this->careers_model->applications(['job' => $job_id, 'stage' => $stage], $limit);
        }

        $data['columns'] = $columns;
        $data['counts']  = $this->careers_model->stage_counts(['job' => $job_id]);
        $data['limit']   = $limit;
        $data['job_id']  = $job_id;
        $data['jobs']    = $this->careers_model->jobs(['not_status' => ['archived']]);
        $data['title']   = _l('careers_pipeline');

        $this->load->view('pipeline', $data);
    }

    public function application($id)
    {
        $data['application'] = $this->careers_model->application($id);

        if (!$data['application']) {
            show_404();
        }

        $data['job']        = $this->careers_model->job($data['application']->job_id);
        $data['questions']  = $data['job'] ? $this->careers_model->questions($data['job']->id) : [];
        $data['activity']   = $this->careers_model->activity($id);
        $data['interviews'] = $this->careers_model->interviews(['application' => $id]);
        $data['others']     = $this->careers_model->other_applications($data['application']);
        $data['staff']      = $this->staff_list();
        $data['resume']     = careers_resume_full_path($data['application']) !== '';
        $data['title']      = $data['application']->name;

        $this->load->view('application', $data);
    }

    /**
     * Stage change from the detail page, the table's inline dropdown and the
     * kanban drag-drop alike. Emailing the candidate is opt-in per request so a
     * bulk re-stage never silently mails a hundred people.
     */
    public function set_stage()
    {
        if (!careers_can('edit')) {
            $this->json(['success' => false, 'message' => _l('access_denied')]);
        }

        $id     = (int) $this->input->post('id');
        $stage  = (string) $this->input->post('stage');
        $reason = (string) $this->input->post('reason');
        $notify = (int) $this->input->post('notify') === 1;

        if (!$this->careers_model->set_stage($id, $stage, $reason)) {
            $this->json(['success' => false, 'message' => _l('careers_stage_unchanged')]);
        }

        $emailed = false;
        if ($notify && careers_opt_bool('careers_stage_email_enabled')) {
            $emailed = $this->email_stage_change($id, $stage, $reason);
        }

        $this->json([
            'success' => true,
            'stage'   => $stage,
            'label'   => careers_stage_label($stage),
            'color'   => careers_stage_color($stage),
            'emailed' => $emailed,
            'counts'  => $this->careers_model->stage_counts(['job' => (int) $this->input->post('job_id')]),
        ]);
    }

    private function email_stage_change($application_id, $stage, $reason)
    {
        $application = $this->careers_model->application($application_id);

        if (!$application || empty($application->email)) {
            return false;
        }

        $subjects = [
            'shortlisted' => 'You have been shortlisted for {job}',
            'interview'   => 'Interview stage — your application for {job}',
            'offer'       => 'Good news about your application for {job}',
            'hired'       => 'Welcome aboard — {job}',
            'rejected'    => 'Update on your application for {job}',
        ];

        if (!isset($subjects[$stage])) {
            return false;
        }

        $sent = careers_send_email(
            $application->email,
            str_replace('{job}', $application->job_title, $subjects[$stage]),
            'email_stage_update',
            ['application' => $application, 'stage' => $stage, 'reason' => $reason]
        );

        if ($sent) {
            $this->careers_model->add_activity($application_id, 'email', 'Stage email sent to ' . $application->email . ' (' . careers_stage_label($stage) . ')');
        }

        return $sent;
    }

    /** Star, rate, assign, tag — one endpoint, one capability. */
    public function update_application()
    {
        if (!careers_can('edit')) {
            $this->json(['success' => false, 'message' => _l('access_denied')]);
        }

        $id     = (int) $this->input->post('id');
        $field  = (string) $this->input->post('field');
        $value  = $this->input->post('value');

        $allowed = ['rating', 'is_starred', 'tags', 'assigned_to', 'name', 'email', 'phone', 'current_location',
            'current_company', 'current_designation', 'total_experience', 'current_ctc', 'expected_ctc',
            'notice_period', 'linkedin_url', 'portfolio_url'];

        if (!in_array($field, $allowed, true)) {
            $this->json(['success' => false, 'message' => 'Unknown field']);
        }

        if ($field === 'rating') {
            $value = min(5, max(0, (int) $value));
        }
        if ($field === 'is_starred') {
            $value = (int) (bool) $value;
        }

        $this->careers_model->update_application($id, [$field => $value]);

        $this->json(['success' => true, 'value' => $value]);
    }

    public function add_note()
    {
        if (!careers_can('edit')) {
            access_denied('Careers');
        }

        $id   = (int) $this->input->post('application_id');
        $note = trim((string) $this->input->post('note'));

        if ($id && $note !== '') {
            $this->careers_model->add_activity($id, 'note', $note);
        }

        if ($this->input->is_ajax_request()) {
            $this->json(['success' => true]);
        }

        redirect(admin_url('careers/application/' . $id));
    }

    public function delete_note($id, $application_id)
    {
        if (!careers_can('delete')) {
            access_denied('Careers');
        }

        $this->careers_model->delete_activity($id);
        redirect(admin_url('careers/application/' . (int) $application_id));
    }

    /**
     * Free-text email to a candidate, logged on the timeline. Kept deliberately
     * simple: recruiters compose in their own words, the CRM only wraps it in
     * the branded shell and records that it went out.
     */
    public function email_candidate()
    {
        if (!careers_can('edit')) {
            access_denied('Careers');
        }

        $id      = (int) $this->input->post('application_id');
        $subject = trim((string) $this->input->post('subject'));
        $message = trim((string) $this->input->post('message'));

        $application = $this->careers_model->application($id);

        if (!$application || $subject === '' || $message === '') {
            set_alert('warning', _l('careers_email_incomplete'));
            redirect(admin_url('careers/application/' . $id));
        }

        $sent = careers_send_email($application->email, $subject, 'email_custom', [
            'application' => $application,
            'body'        => nl2br(html_escape($message)),
        ]);

        if ($sent) {
            $this->careers_model->add_activity($id, 'email', 'Email sent — ' . $subject, ['body' => $message]);
            set_alert('success', _l('careers_email_sent'));
        } else {
            set_alert('danger', _l('careers_email_failed'));
        }

        redirect(admin_url('careers/application/' . $id));
    }

    /**
     * Resume download. Streamed through the controller so a stored CV is never
     * reachable by guessing a URL, and only with the module's view capability.
     */
    public function resume($id)
    {
        $application = $this->careers_model->application($id);

        if (!$application) {
            show_404();
        }

        $path = careers_resume_full_path($application);
        if ($path === '') {
            set_alert('warning', _l('careers_resume_missing'));
            redirect(admin_url('careers/application/' . (int) $id));
        }

        $name = $application->resume_name !== ''
            ? $application->resume_name
            : ('resume-' . $application->reference . '.' . pathinfo($path, PATHINFO_EXTENSION));

        // Inline for PDFs (recruiters preview far more often than they save),
        // attachment for everything a browser cannot render.
        $extension   = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $disposition = $extension === 'pdf' ? 'inline' : 'attachment';

        header('Content-Type: ' . ($extension === 'pdf' ? 'application/pdf' : 'application/octet-stream'));
        header('Content-Disposition: ' . $disposition . '; filename="' . str_replace('"', '', $name) . '"');
        header('Content-Length: ' . filesize($path));
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    public function delete_application($id)
    {
        if (!careers_can('delete')) {
            access_denied('Careers');
        }

        $this->careers_model->delete_application($id);
        set_alert('success', _l('careers_application_deleted'));

        redirect(admin_url('careers/applications'));
    }

    /** Bulk stage change / delete from the applications table. */
    public function bulk_action()
    {
        if (!careers_can('edit')) {
            $this->json(['success' => false, 'message' => _l('access_denied')]);
        }

        $ids    = (array) $this->input->post('ids');
        $action = (string) $this->input->post('bulk_action');
        $done   = 0;

        foreach ($ids as $id) {
            $id = (int) $id;
            if (!$id) {
                continue;
            }

            if ($action === 'delete') {
                if (careers_can('delete') && $this->careers_model->delete_application($id)) {
                    $done++;
                }
            } elseif (array_key_exists($action, careers_stages())) {
                if ($this->careers_model->set_stage($id, $action)) {
                    $done++;
                }
            }
        }

        $this->json(['success' => true, 'done' => $done]);
    }

    /**
     * CSV export of the current filter set. Streamed rather than buffered so a
     * large export cannot exhaust memory.
     */
    public function export()
    {
        $filters      = $this->application_filters();
        $applications = $this->careers_model->applications($filters, 5000);

        $filename = 'careers-applications-' . date('Y-m-d-His') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $out = fopen('php://output', 'w');
        fputs($out, "\xEF\xBB\xBF"); // BOM so Excel reads UTF-8 names correctly

        fputcsv($out, ['Reference', 'Applied On', 'Name', 'Email', 'Phone', 'Position', 'Department', 'Stage',
            'Rating', 'Experience (yrs)', 'Current Company', 'Current CTC', 'Expected CTC', 'Notice Period',
            'Location', 'LinkedIn', 'Portfolio', 'Source', 'Assigned To', 'Tags']);

        foreach ($applications as $row) {
            fputcsv($out, [
                $row->reference,
                $row->created_at,
                $row->name,
                $row->email,
                $row->phone,
                $row->job_title,
                $row->department_name,
                careers_stage_label($row->stage),
                $row->rating,
                $row->total_experience,
                $row->current_company,
                $row->current_ctc,
                $row->expected_ctc,
                $row->notice_period,
                $row->current_location,
                $row->linkedin_url,
                $row->portfolio_url,
                $row->source,
                $row->assigned_name,
                $row->tags,
            ]);
        }

        fclose($out);
        exit;
    }

    /* ═══════════════════════════ Interviews ═══════════════════════════════ */

    public function interviews()
    {
        $view = $this->input->get('view') === 'past' ? 'past' : 'upcoming';

        $filters = ['from' => $this->input->get('from'), 'to' => $this->input->get('to')];
        if ($view === 'upcoming' && !$filters['from'] && !$filters['to']) {
            $filters['upcoming'] = true;
        }

        $data['interviews'] = $this->careers_model->interviews($filters);
        $data['view']       = $view;
        $data['filters']    = $filters;
        $data['staff']      = $this->staff_list();
        $data['title']      = _l('careers_interviews');

        $this->load->view('interviews', $data);
    }

    public function save_interview()
    {
        if (!careers_can('edit')) {
            access_denied('Careers');
        }

        $id    = (int) $this->input->post('id');
        $saved = $this->careers_model->save_interview($this->input->post(), $id);

        if (!$saved) {
            set_alert('danger', _l('careers_interview_save_failed'));
            redirect($_SERVER['HTTP_REFERER'] ?? admin_url('careers/interviews'));
        }

        $interview = $this->careers_model->interview($saved);

        if (!$id && $interview->notify_candidate) {
            $application = $this->careers_model->application($interview->application_id);
            if ($application && $application->email) {
                careers_send_email(
                    $application->email,
                    'Interview scheduled — ' . $application->job_title,
                    'email_interview',
                    ['interview' => $interview, 'application' => $application]
                );
            }
        }

        set_alert('success', _l('careers_interview_saved'));
        redirect($_SERVER['HTTP_REFERER'] ?? admin_url('careers/interviews'));
    }

    public function delete_interview($id)
    {
        if (!careers_can('delete')) {
            access_denied('Careers');
        }

        $this->careers_model->delete_interview($id);
        set_alert('success', _l('careers_interview_deleted'));

        redirect($_SERVER['HTTP_REFERER'] ?? admin_url('careers/interviews'));
    }

    /* ══════════════════════ Setup: masters & settings ═════════════════════ */

    public function setup()
    {
        if (!careers_can_settings()) {
            access_denied('Careers');
        }

        $data['departments'] = $this->careers_model->departments();
        $data['locations']   = $this->careers_model->locations();
        $data['subscribers'] = $this->careers_model->subscribers(false);
        $data['title']       = _l('careers_setup');

        $this->load->view('setup', $data);
    }

    public function save_department()
    {
        if (!careers_can_settings()) {
            access_denied('Careers');
        }

        $this->careers_model->save_department($this->input->post(), (int) $this->input->post('id'));
        set_alert('success', _l('careers_saved'));

        redirect(admin_url('careers/setup'));
    }

    public function delete_department($id)
    {
        if (!careers_can_settings()) {
            access_denied('Careers');
        }

        $this->careers_model->delete_department($id);
        set_alert('success', _l('careers_deleted'));

        redirect(admin_url('careers/setup'));
    }

    public function save_location()
    {
        if (!careers_can_settings()) {
            access_denied('Careers');
        }

        $this->careers_model->save_location($this->input->post(), (int) $this->input->post('id'));
        set_alert('success', _l('careers_saved'));

        redirect(admin_url('careers/setup'));
    }

    public function delete_location($id)
    {
        if (!careers_can_settings()) {
            access_denied('Careers');
        }

        $this->careers_model->delete_location($id);
        set_alert('success', _l('careers_deleted'));

        redirect(admin_url('careers/setup'));
    }

    public function settings()
    {
        if (!careers_can_settings()) {
            access_denied('Careers');
        }

        if ($this->input->post()) {
            // Checkboxes only post when checked, so every boolean option is
            // written explicitly rather than looped over the POST body.
            $booleans = ['careers_ack_enabled', 'careers_stage_email_enabled', 'careers_admin_notify',
                'careers_allow_public_apply', 'careers_resume_required', 'careers_auto_close_expired',
                'careers_alerts_enabled', 'careers_embed_enabled'];

            foreach ($booleans as $option) {
                update_option($option, $this->input->post($option) ? 1 : 0);
            }

            $strings = ['careers_company_name', 'careers_site_url', 'careers_notify_emails',
                'careers_allowed_ext', 'careers_default_currency', 'careers_default_country',
                'careers_embed_domains'];

            foreach ($strings as $option) {
                update_option($option, trim((string) $this->input->post($option)));
            }

            update_option('careers_max_resume_mb', max(1, min(25, (int) $this->input->post('careers_max_resume_mb'))));
            update_option('careers_dedupe_days', max(0, (int) $this->input->post('careers_dedupe_days')));
            update_option('careers_retention_days', max(0, (int) $this->input->post('careers_retention_days')));

            set_alert('success', _l('settings_updated'));
            redirect(admin_url('careers/settings'));
        }

        $data['title'] = _l('careers_settings');

        $this->load->view('settings', $data);
    }

    /* ═════════════════════════════ Internals ══════════════════════════════ */

    private function staff_list()
    {
        return $this->db
            ->select('staffid, CONCAT(firstname, " ", lastname) AS full_name, email')
            ->where('active', 1)
            ->order_by('firstname', 'asc')
            ->get(db_prefix() . 'staff')->result();
    }

    private function json($payload)
    {
        $this->output
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($payload))
            ->_display();
        exit;
    }
}
