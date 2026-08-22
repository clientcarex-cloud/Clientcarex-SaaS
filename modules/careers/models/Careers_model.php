<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Careers — data layer.
 *
 * Sections:
 *   1. Masters            departments & locations
 *   2. Jobs               CRUD, publishing, screening questions
 *   3. Public queries     what the website API is allowed to read
 *   4. Applications       intake, pipeline, activity timeline
 *   5. Interviews
 *   6. Subscribers        job alerts
 *   7. Analytics
 *   8. Automation         cron housekeeping
 *
 * Every write that a candidate can trigger goes through this model, so the
 * public API controller never builds SQL of its own.
 */
class Careers_model extends App_Model
{
    private $t_jobs;
    private $t_apps;
    private $t_dept;
    private $t_loc;
    private $t_quest;
    private $t_act;
    private $t_int;
    private $t_subs;
    private $t_stats;

    public function __construct()
    {
        parent::__construct();

        $p             = db_prefix();
        $this->t_jobs  = $p . 'careers_jobs';
        $this->t_apps  = $p . 'careers_applications';
        $this->t_dept  = $p . 'careers_departments';
        $this->t_loc   = $p . 'careers_locations';
        $this->t_quest = $p . 'careers_questions';
        $this->t_act   = $p . 'careers_activity';
        $this->t_int   = $p . 'careers_interviews';
        $this->t_subs  = $p . 'careers_subscribers';
        $this->t_stats = $p . 'careers_job_stats';
    }

    /* ═══════════════════════════ 1. Masters ═══════════════════════════════ */

    public function departments($only_active = false)
    {
        if ($only_active) {
            $this->db->where('active', 1);
        }

        return $this->db->order_by('sort_order', 'asc')->order_by('name', 'asc')->get($this->t_dept)->result();
    }

    public function department($id)
    {
        return $this->db->where('id', (int) $id)->get($this->t_dept)->row();
    }

    public function save_department($data, $id = 0)
    {
        $row = [
            'name'        => substr(trim((string) $data['name']), 0, 150),
            'description' => substr(trim((string) ($data['description'] ?? '')), 0, 500),
            'color'       => substr(trim((string) ($data['color'] ?? '#0d9488')), 0, 20),
            'active'      => isset($data['active']) ? 1 : 0,
            'sort_order'  => (int) ($data['sort_order'] ?? 0),
        ];

        if ($row['name'] === '') {
            return false;
        }

        $row['slug'] = careers_slugify($row['name'], 'careers_departments', $id);

        if ($id) {
            $this->db->where('id', (int) $id)->update($this->t_dept, $row);

            return (int) $id;
        }

        $row['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert($this->t_dept, $row);

        return (int) $this->db->insert_id();
    }

    public function delete_department($id)
    {
        // Postings keep working with department_id 0 ("Other") rather than
        // disappearing from the website because a master row was tidied up.
        $this->db->where('department_id', (int) $id)->update($this->t_jobs, ['department_id' => 0]);
        $this->db->where('id', (int) $id)->delete($this->t_dept);

        return $this->db->affected_rows() > 0;
    }

    public function locations($only_active = false)
    {
        if ($only_active) {
            $this->db->where('active', 1);
        }

        return $this->db->order_by('sort_order', 'asc')->order_by('name', 'asc')->get($this->t_loc)->result();
    }

    public function location($id)
    {
        return $this->db->where('id', (int) $id)->get($this->t_loc)->row();
    }

    public function save_location($data, $id = 0)
    {
        $row = [
            'name'        => substr(trim((string) $data['name']), 0, 150),
            'city'        => substr(trim((string) ($data['city'] ?? '')), 0, 120),
            'state'       => substr(trim((string) ($data['state'] ?? '')), 0, 120),
            'country'     => substr(trim((string) ($data['country'] ?? '')), 0, 120),
            'address'     => substr(trim((string) ($data['address'] ?? '')), 0, 500),
            'postal_code' => substr(trim((string) ($data['postal_code'] ?? '')), 0, 30),
            'active'      => isset($data['active']) ? 1 : 0,
            'sort_order'  => (int) ($data['sort_order'] ?? 0),
        ];

        if ($row['name'] === '') {
            return false;
        }

        if ($id) {
            $this->db->where('id', (int) $id)->update($this->t_loc, $row);

            return (int) $id;
        }

        $row['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert($this->t_loc, $row);

        return (int) $this->db->insert_id();
    }

    public function delete_location($id)
    {
        $this->db->where('location_id', (int) $id)->update($this->t_jobs, ['location_id' => 0]);
        $this->db->where('id', (int) $id)->delete($this->t_loc);

        return $this->db->affected_rows() > 0;
    }

    /* ═════════════════════════════ 2. Jobs ════════════════════════════════ */

    /**
     * Base select for every job read: the department and location names, plus
     * the live application count, come along so no view has to N+1 for them.
     */
    private function job_select()
    {
        $this->db->select(
            $this->t_jobs . '.*,'
            . $this->t_dept . '.name AS department_name,'
            . $this->t_dept . '.color AS department_color,'
            . $this->t_loc . '.name AS location_name,'
            . $this->t_loc . '.city AS loc_city,'
            . $this->t_loc . '.state AS loc_state,'
            . $this->t_loc . '.country AS loc_country,'
            . $this->t_loc . '.address AS loc_address,'
            . $this->t_loc . '.postal_code AS loc_postal,'
            . '(SELECT COUNT(*) FROM ' . $this->t_apps . ' a WHERE a.job_id = ' . $this->t_jobs . '.id) AS applications_count,'
            . '(SELECT COUNT(*) FROM ' . $this->t_apps . ' a WHERE a.job_id = ' . $this->t_jobs . '.id AND a.stage = "new") AS new_count'
        );
        $this->db->join($this->t_dept, $this->t_dept . '.id = ' . $this->t_jobs . '.department_id', 'left');
        $this->db->join($this->t_loc, $this->t_loc . '.id = ' . $this->t_jobs . '.location_id', 'left');
    }

    /**
     * @param array $filters status|type|department|location|search|featured
     */
    public function jobs($filters = [], $limit = 0, $offset = 0)
    {
        $this->job_select();
        $this->apply_job_filters($filters);

        $this->db->order_by($this->t_jobs . '.is_featured', 'desc');
        $this->db->order_by($this->t_jobs . '.sort_order', 'asc');
        $this->db->order_by('COALESCE(' . $this->t_jobs . '.published_at, ' . $this->t_jobs . '.created_at)', 'desc', false);

        if ($limit) {
            $this->db->limit((int) $limit, (int) $offset);
        }

        return $this->db->get($this->t_jobs)->result();
    }

    public function count_jobs($filters = [])
    {
        $this->db->join($this->t_dept, $this->t_dept . '.id = ' . $this->t_jobs . '.department_id', 'left');
        $this->db->join($this->t_loc, $this->t_loc . '.id = ' . $this->t_jobs . '.location_id', 'left');
        $this->apply_job_filters($filters);

        return (int) $this->db->count_all_results($this->t_jobs);
    }

    private function apply_job_filters($filters)
    {
        if (!empty($filters['status'])) {
            $this->db->where($this->t_jobs . '.status', $filters['status']);
        }
        if (!empty($filters['type'])) {
            $this->db->where($this->t_jobs . '.job_type', $filters['type']);
        }
        if (!empty($filters['work_mode'])) {
            $this->db->where($this->t_jobs . '.work_mode', $filters['work_mode']);
        }
        if (!empty($filters['department'])) {
            $this->db->where($this->t_jobs . '.department_id', (int) $filters['department']);
        }
        if (!empty($filters['location'])) {
            $this->db->where($this->t_jobs . '.location_id', (int) $filters['location']);
        }
        if (isset($filters['featured']) && $filters['featured'] !== '') {
            $this->db->where($this->t_jobs . '.is_featured', (int) $filters['featured']);
        }
        if (!empty($filters['not_status'])) {
            $this->db->where_not_in($this->t_jobs . '.status', (array) $filters['not_status']);
        }
        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $this->db->group_start()
                ->like($this->t_jobs . '.title', $search)
                ->or_like($this->t_jobs . '.reference', $search)
                ->or_like($this->t_jobs . '.skills', $search)
                ->or_like($this->t_jobs . '.location_text', $search)
                ->or_like($this->t_dept . '.name', $search)
                ->group_end();
        }
    }

    public function job($id)
    {
        $this->job_select();
        $this->db->where($this->t_jobs . '.id', (int) $id);

        return $this->db->get($this->t_jobs)->row();
    }

    public function job_by_slug($slug)
    {
        $this->job_select();
        $this->db->where($this->t_jobs . '.slug', (string) $slug);

        return $this->db->get($this->t_jobs)->row();
    }

    /**
     * Create or update a posting from the job editor.
     *
     * Publishing is a side effect of the status: the first time a posting goes
     * `published` it stamps published_at, which is what the website sorts on
     * and what Google reads as datePosted.
     */
    public function save_job($data, $id = 0)
    {
        $questions = isset($data['questions']) && is_array($data['questions']) ? $data['questions'] : [];
        $fields    = isset($data['form_fields']) && is_array($data['form_fields']) ? $data['form_fields'] : [];
        unset($data['questions'], $data['form_fields']);

        $existing = $id ? $this->job($id) : null;

        $row = [
            'title'            => substr(trim((string) ($data['title'] ?? '')), 0, 191),
            'job_type'         => array_key_exists((string) ($data['job_type'] ?? ''), careers_job_types()) ? $data['job_type'] : 'full_time',
            'department_id'    => (int) ($data['department_id'] ?? 0),
            'location_id'      => (int) ($data['location_id'] ?? 0),
            'location_text'    => substr(trim((string) ($data['location_text'] ?? '')), 0, 191),
            'work_mode'        => array_key_exists((string) ($data['work_mode'] ?? ''), careers_work_modes()) ? $data['work_mode'] : 'onsite',
            'summary'          => substr(trim(strip_tags((string) ($data['summary'] ?? ''))), 0, 500),
            'description'      => careers_safe_html($data['description'] ?? ''),
            'responsibilities' => careers_safe_html($data['responsibilities'] ?? ''),
            'requirements'     => careers_safe_html($data['requirements'] ?? ''),
            'benefits'         => careers_safe_html($data['benefits'] ?? ''),
            'skills'           => substr(implode(', ', careers_split_list($data['skills'] ?? '')), 0, 1000),
            'education'        => substr(trim((string) ($data['education'] ?? '')), 0, 255),
            'experience_min'   => $this->nullable_number($data['experience_min'] ?? null),
            'experience_max'   => $this->nullable_number($data['experience_max'] ?? null),
            'duration_months'  => $this->nullable_number($data['duration_months'] ?? null, true),
            'stipend'          => substr(trim((string) ($data['stipend'] ?? '')), 0, 120),
            'salary_min'       => $this->nullable_number($data['salary_min'] ?? null),
            'salary_max'       => $this->nullable_number($data['salary_max'] ?? null),
            'salary_currency'  => substr(trim((string) ($data['salary_currency'] ?? careers_opt('careers_default_currency'))), 0, 10),
            'salary_period'    => array_key_exists((string) ($data['salary_period'] ?? ''), careers_salary_periods()) ? $data['salary_period'] : 'year',
            'salary_hidden'    => !empty($data['salary_hidden']) ? 1 : 0,
            'openings'         => max(1, (int) ($data['openings'] ?? 1)),
            'status'           => array_key_exists((string) ($data['status'] ?? ''), careers_job_statuses()) ? $data['status'] : 'draft',
            'is_featured'      => !empty($data['is_featured']) ? 1 : 0,
            'is_urgent'        => !empty($data['is_urgent']) ? 1 : 0,
            'apply_mode'       => ($data['apply_mode'] ?? 'internal') === 'external' ? 'external' : 'internal',
            'external_url'     => substr(trim((string) ($data['external_url'] ?? '')), 0, 500),
            'seo_title'        => substr(trim(strip_tags((string) ($data['seo_title'] ?? ''))), 0, 191),
            'seo_description'  => substr(trim(strip_tags((string) ($data['seo_description'] ?? ''))), 0, 500),
            'hiring_manager'   => (int) ($data['hiring_manager'] ?? 0),
            // to_sql_date() hands back whatever it was given when it cannot
            // parse it, and MySQL turns that into 0000-00-00 — a posting that
            // then reads as "deadline in the past" and disappears from the
            // website. Anything that is not a real date is stored as no
            // deadline at all.
            'deadline'         => careers_sql_date_or_null($data['deadline'] ?? null),
            'sort_order'       => (int) ($data['sort_order'] ?? 0),
            'form_fields'      => json_encode($this->normalize_form_fields($fields)),
        ];

        if ($row['title'] === '') {
            return false;
        }

        $row['slug'] = careers_slugify(
            !empty($data['slug']) ? $data['slug'] : $row['title'],
            'careers_jobs',
            $id
        );

        if ($row['status'] === 'published' && (!$existing || empty($existing->published_at))) {
            $row['published_at'] = date('Y-m-d H:i:s');
        }
        if ($row['status'] === 'closed' && (!$existing || $existing->status !== 'closed')) {
            $row['closed_at'] = date('Y-m-d H:i:s');
        }

        if ($id) {
            $row['updated_at'] = date('Y-m-d H:i:s');
            $this->db->where('id', (int) $id)->update($this->t_jobs, $row);
        } else {
            $row['reference']  = careers_next_reference('JOB', 'careers_jobs');
            $row['created_by'] = get_staff_user_id();
            $row['created_at'] = date('Y-m-d H:i:s');
            $this->db->insert($this->t_jobs, $row);
            $id = (int) $this->db->insert_id();
        }

        if (!$id) {
            return false;
        }

        $this->save_questions($id, $questions);

        log_activity('Careers: job ' . ($existing ? 'updated' : 'created') . ' [' . $row['title'] . ', ID: ' . $id . ']');

        return (int) $id;
    }

    private function normalize_form_fields($submitted)
    {
        $out = [];
        foreach (careers_optional_form_fields() as $key => $meta) {
            $out[$key] = !empty($submitted[$key]) ? 1 : 0;
        }

        return $out;
    }

    private function nullable_number($value, $integer = false)
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }

        return $integer ? (int) $value : (float) $value;
    }

    /**
     * Screening questions are replaced wholesale on save. They are always
     * re-read from this table when an application is rendered, so answers keep
     * their question text through the snapshot stored on the application.
     */
    private function save_questions($job_id, $questions)
    {
        $this->db->where('job_id', (int) $job_id)->delete($this->t_quest);

        $order = 0;
        foreach ($questions as $question) {
            $text = trim((string) ($question['question'] ?? ''));
            if ($text === '') {
                continue;
            }
            $order++;

            $this->db->insert($this->t_quest, [
                'job_id'          => (int) $job_id,
                'question'        => substr($text, 0, 500),
                'type'            => array_key_exists((string) ($question['type'] ?? ''), careers_question_types()) ? $question['type'] : 'text',
                'options'         => substr(trim((string) ($question['options'] ?? '')), 0, 2000),
                'required'        => !empty($question['required']) ? 1 : 0,
                'knockout_answer' => substr(trim((string) ($question['knockout_answer'] ?? '')), 0, 191),
                'sort_order'      => $order,
            ]);
        }
    }

    public function questions($job_id)
    {
        return $this->db->where('job_id', (int) $job_id)
            ->order_by('sort_order', 'asc')
            ->get($this->t_quest)->result();
    }

    public function update_job($id, $data)
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->db->where('id', (int) $id)->update($this->t_jobs, $data);

        return $this->db->affected_rows() > 0;
    }

    /** Publish / pause / close from the list without opening the editor. */
    public function set_job_status($id, $status)
    {
        if (!array_key_exists($status, careers_job_statuses())) {
            return false;
        }

        $job = $this->job($id);
        if (!$job) {
            return false;
        }

        $update = ['status' => $status, 'updated_at' => date('Y-m-d H:i:s')];

        if ($status === 'published' && empty($job->published_at)) {
            $update['published_at'] = date('Y-m-d H:i:s');
        }
        if ($status === 'closed') {
            $update['closed_at'] = date('Y-m-d H:i:s');
        }

        $this->db->where('id', (int) $id)->update($this->t_jobs, $update);
        log_activity('Careers: job status → ' . $status . ' [' . $job->title . ', ID: ' . $id . ']');

        return true;
    }

    /**
     * Copy a posting, questions included. The copy always lands as a draft with
     * its own slug and reference so nothing is published by accident.
     */
    public function duplicate_job($id)
    {
        $job = $this->db->where('id', (int) $id)->get($this->t_jobs)->row_array();
        if (!$job) {
            return false;
        }

        unset($job['id']);
        $job['title']        = mb_substr($job['title'] . ' (copy)', 0, 191);
        $job['slug']         = careers_slugify($job['title'], 'careers_jobs');
        $job['reference']    = careers_next_reference('JOB', 'careers_jobs');
        $job['status']       = 'draft';
        $job['published_at'] = null;
        $job['closed_at']    = null;
        $job['views']        = 0;
        $job['created_by']   = get_staff_user_id();
        $job['created_at']   = date('Y-m-d H:i:s');
        $job['updated_at']   = null;

        $this->db->insert($this->t_jobs, $job);
        $new_id = (int) $this->db->insert_id();

        foreach ($this->questions($id) as $question) {
            $this->db->insert($this->t_quest, [
                'job_id'          => $new_id,
                'question'        => $question->question,
                'type'            => $question->type,
                'options'         => $question->options,
                'required'        => $question->required,
                'knockout_answer' => $question->knockout_answer,
                'sort_order'      => $question->sort_order,
            ]);
        }

        return $new_id;
    }

    /**
     * Deleting a posting takes its applications, their timelines, interviews
     * and stored resumes with it — leaving orphaned CVs on disk would be the
     * one thing a retention policy cannot clean up later.
     */
    public function delete_job($id)
    {
        $id = (int) $id;

        foreach ($this->db->where('job_id', $id)->get($this->t_apps)->result() as $application) {
            $this->delete_application($application->id, false);
        }

        $this->db->where('job_id', $id)->delete($this->t_quest);
        $this->db->where('job_id', $id)->delete($this->t_stats);
        $this->db->where('id', $id)->delete($this->t_jobs);

        $directory = careers_upload_path($id);
        if (is_dir($directory)) {
            @array_map('unlink', glob($directory . '*') ?: []);
            @rmdir($directory);
        }

        log_activity('Careers: job deleted [ID: ' . $id . ']');

        return true;
    }

    /* ═══════════════════════ 3. Public queries ════════════════════════════ */

    /**
     * Postings the website may show: published, and not past their deadline.
     * The deadline is compared in SQL rather than filtered in PHP so a paginated
     * response is never short.
     */
    public function public_jobs($filters = [])
    {
        $this->job_select();
        $this->db->where($this->t_jobs . '.status', 'published');
        // A zero date is "no deadline", exactly as it is read everywhere else.
        // Without this arm a posting whose deadline never saved cleanly is
        // published in the CRM and invisible on the website.
        $this->db->group_start()
            ->where($this->t_jobs . '.deadline IS NULL')
            ->or_where($this->t_jobs . ".deadline = '0000-00-00'", null, false)
            ->or_where($this->t_jobs . '.deadline >=', date('Y-m-d'))
            ->group_end();

        if (!empty($filters['type'])) {
            $this->db->where($this->t_jobs . '.job_type', $filters['type']);
        }
        if (!empty($filters['department'])) {
            $this->db->where($this->t_dept . '.slug', $filters['department']);
        }

        $this->db->order_by($this->t_jobs . '.is_featured', 'desc');
        $this->db->order_by($this->t_jobs . '.sort_order', 'asc');
        $this->db->order_by('COALESCE(' . $this->t_jobs . '.published_at, ' . $this->t_jobs . '.created_at)', 'desc', false);

        if (!empty($filters['limit'])) {
            $this->db->limit((int) $filters['limit']);
        }

        return $this->db->get($this->t_jobs)->result();
    }

    /** A single published posting by slug — the website's detail page. */
    public function public_job($slug)
    {
        $this->job_select();
        $this->db->where($this->t_jobs . '.slug', (string) $slug);
        $this->db->where($this->t_jobs . '.status', 'published');

        return $this->db->get($this->t_jobs)->row();
    }

    /**
     * The other openings shown beside a posting, so a detail page a candidate
     * is not right for is not a dead end. Same department first — that is the
     * neighbouring role they are most likely to qualify for — then whatever
     * else is live, on the same "published and not past its deadline" terms as
     * the listing itself.
     */
    public function related_jobs($job, $limit = 4)
    {
        $job_id     = (int) (is_object($job) ? $job->id : $job);
        $department = (is_object($job) && !empty($job->department_id)) ? (int) $job->department_id : 0;

        $this->job_select();
        $this->db->where($this->t_jobs . '.status', 'published');
        $this->db->where($this->t_jobs . '.id !=', $job_id);
        $this->db->group_start()
            ->where($this->t_jobs . '.deadline IS NULL')
            ->or_where($this->t_jobs . ".deadline = '0000-00-00'", null, false)
            ->or_where($this->t_jobs . '.deadline >=', date('Y-m-d'))
            ->group_end();

        if ($department) {
            $this->db->order_by('CASE WHEN ' . $this->t_jobs . '.department_id = ' . $department . ' THEN 0 ELSE 1 END', '', false);
        }

        $this->db->order_by($this->t_jobs . '.is_featured', 'desc');
        $this->db->order_by('COALESCE(' . $this->t_jobs . '.published_at, ' . $this->t_jobs . '.created_at)', 'desc', false);
        $this->db->limit((int) $limit);

        return $this->db->get($this->t_jobs)->result();
    }

    /**
     * Page-view counter. Written as two cheap statements (a counter bump plus a
     * daily rollup upsert) so the website can call it on every detail view.
     */
    public function track_view($job_id)
    {
        $job_id = (int) $job_id;
        if (!$job_id) {
            return;
        }

        $this->db->set('views', 'views + 1', false)->where('id', $job_id)->update($this->t_jobs);

        $this->db->query(
            'INSERT INTO ' . $this->t_stats . ' (job_id, stat_date, views, applications) VALUES (?, ?, 1, 0)
             ON DUPLICATE KEY UPDATE views = views + 1',
            [$job_id, date('Y-m-d')]
        );
    }

    /* ═════════════════════════ 4. Applications ════════════════════════════ */

    private function application_select()
    {
        $this->db->select(
            $this->t_apps . '.*,'
            . $this->t_jobs . '.title AS job_title,'
            . $this->t_jobs . '.slug AS job_slug,'
            . $this->t_jobs . '.reference AS job_reference,'
            . $this->t_jobs . '.job_type AS job_type,'
            . $this->t_dept . '.name AS department_name,'
            . 'CONCAT(' . db_prefix() . 'staff.firstname, " ", ' . db_prefix() . 'staff.lastname) AS assigned_name'
        );
        $this->db->join($this->t_jobs, $this->t_jobs . '.id = ' . $this->t_apps . '.job_id', 'left');
        $this->db->join($this->t_dept, $this->t_dept . '.id = ' . $this->t_jobs . '.department_id', 'left');
        $this->db->join(db_prefix() . 'staff', db_prefix() . 'staff.staffid = ' . $this->t_apps . '.assigned_to', 'left');
    }

    /**
     * @param array $filters job|stage|rating|assigned|search|from|to|starred|type
     */
    public function applications($filters = [], $limit = 0, $offset = 0)
    {
        $this->application_select();
        $this->apply_application_filters($filters);

        $sort = in_array($filters['sort'] ?? '', ['created_at', 'rating', 'name', 'last_activity_at'], true)
            ? $filters['sort'] : 'created_at';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $this->db->order_by($this->t_apps . '.' . $sort, $direction);

        if ($limit) {
            $this->db->limit((int) $limit, (int) $offset);
        }

        return $this->db->get($this->t_apps)->result();
    }

    public function count_applications($filters = [])
    {
        $this->db->join($this->t_jobs, $this->t_jobs . '.id = ' . $this->t_apps . '.job_id', 'left');
        $this->db->join($this->t_dept, $this->t_dept . '.id = ' . $this->t_jobs . '.department_id', 'left');
        $this->apply_application_filters($filters);

        return (int) $this->db->count_all_results($this->t_apps);
    }

    private function apply_application_filters($filters)
    {
        if (!empty($filters['job'])) {
            $this->db->where($this->t_apps . '.job_id', (int) $filters['job']);
        }
        if (!empty($filters['stage'])) {
            $this->db->where($this->t_apps . '.stage', $filters['stage']);
        }
        if (!empty($filters['type'])) {
            $this->db->where($this->t_jobs . '.job_type', $filters['type']);
        }
        if (!empty($filters['department'])) {
            $this->db->where($this->t_jobs . '.department_id', (int) $filters['department']);
        }
        if (!empty($filters['rating'])) {
            $this->db->where($this->t_apps . '.rating >=', (int) $filters['rating']);
        }
        if (!empty($filters['assigned'])) {
            $this->db->where($this->t_apps . '.assigned_to', (int) $filters['assigned']);
        }
        if (!empty($filters['starred'])) {
            $this->db->where($this->t_apps . '.is_starred', 1);
        }
        if (!empty($filters['from'])) {
            $this->db->where('DATE(' . $this->t_apps . '.created_at) >=', to_sql_date($filters['from']));
        }
        if (!empty($filters['to'])) {
            $this->db->where('DATE(' . $this->t_apps . '.created_at) <=', to_sql_date($filters['to']));
        }
        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $this->db->group_start()
                ->like($this->t_apps . '.name', $search)
                ->or_like($this->t_apps . '.email', $search)
                ->or_like($this->t_apps . '.phone', $search)
                ->or_like($this->t_apps . '.reference', $search)
                ->or_like($this->t_apps . '.current_company', $search)
                ->or_like($this->t_apps . '.tags', $search)
                ->or_like($this->t_jobs . '.title', $search)
                ->group_end();
        }
    }

    public function application($id)
    {
        $this->application_select();
        $this->db->where($this->t_apps . '.id', (int) $id);

        return $this->db->get($this->t_apps)->row();
    }

    /**
     * Intake from the public API.
     *
     * Returns ['ok' => bool, 'id' => int, 'reference' => string, 'error' => string].
     * A duplicate inside the configured window is reported as a soft failure so
     * the website can say something useful instead of silently double-storing.
     */
    public function create_application($job, array $data, array $answers = [], array $resume = [])
    {
        $name  = substr(trim(strip_tags((string) ($data['name'] ?? ''))), 0, 191);
        $email = strtolower(trim((string) ($data['email'] ?? '')));

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'A name and a valid email address are required.'];
        }

        $window = (int) careers_opt('careers_dedupe_days');
        if ($window > 0) {
            $duplicate = $this->db
                ->where('job_id', (int) $job->id)
                ->where('email', $email)
                ->where('created_at >=', date('Y-m-d H:i:s', strtotime('-' . $window . ' days')))
                ->count_all_results($this->t_apps);

            if ($duplicate > 0) {
                return ['ok' => false, 'error' => 'You have already applied for this position. Our team is reviewing your application.', 'duplicate' => true];
            }
        }

        $row = [
            'reference'           => careers_next_reference('APP', 'careers_applications'),
            'job_id'              => (int) $job->id,
            'name'                => $name,
            'email'               => substr($email, 0, 191),
            'phone'               => substr(trim(strip_tags((string) ($data['phone'] ?? ''))), 0, 60),
            'current_location'    => substr(trim(strip_tags((string) ($data['current_location'] ?? ''))), 0, 191),
            'current_company'     => substr(trim(strip_tags((string) ($data['current_company'] ?? ''))), 0, 191),
            'current_designation' => substr(trim(strip_tags((string) ($data['current_designation'] ?? ''))), 0, 191),
            'total_experience'    => $this->nullable_number($data['total_experience'] ?? null),
            'current_ctc'         => substr(trim(strip_tags((string) ($data['current_ctc'] ?? ''))), 0, 60),
            'expected_ctc'        => substr(trim(strip_tags((string) ($data['expected_ctc'] ?? ''))), 0, 60),
            'notice_period'       => substr(trim(strip_tags((string) ($data['notice_period'] ?? ''))), 0, 60),
            'linkedin_url'        => substr($this->clean_url($data['linkedin_url'] ?? ''), 0, 500),
            'portfolio_url'       => substr($this->clean_url($data['portfolio_url'] ?? ''), 0, 500),
            'cover_letter'        => substr(trim(strip_tags((string) ($data['cover_letter'] ?? ''))), 0, 5000),
            'resume_file'         => isset($resume['file']) ? $resume['file'] : '',
            'resume_name'         => isset($resume['name']) ? $resume['name'] : '',
            'answers'             => !empty($answers) ? json_encode($answers) : null,
            'stage'               => 'new',
            'source'              => substr(trim(strip_tags((string) ($data['source'] ?? 'website'))), 0, 60),
            'utm'                 => substr(trim(strip_tags((string) ($data['utm'] ?? ''))), 0, 500),
            'ip_address'          => substr((string) ($data['ip_address'] ?? ''), 0, 45),
            'user_agent'          => substr((string) ($data['user_agent'] ?? ''), 0, 255),
            'token'               => bin2hex(random_bytes(16)),
            'stage_changed_at'    => date('Y-m-d H:i:s'),
            'last_activity_at'    => date('Y-m-d H:i:s'),
            'created_at'          => date('Y-m-d H:i:s'),
        ];

        $this->db->insert($this->t_apps, $row);
        $id = (int) $this->db->insert_id();

        if (!$id) {
            return ['ok' => false, 'error' => 'We could not record your application. Please try again.'];
        }

        $this->db->query(
            'INSERT INTO ' . $this->t_stats . ' (job_id, stat_date, views, applications) VALUES (?, ?, 0, 1)
             ON DUPLICATE KEY UPDATE applications = applications + 1',
            [(int) $job->id, date('Y-m-d')]
        );

        $this->add_activity($id, 'system', 'Application received from ' . $row['source'], [], 0);

        return ['ok' => true, 'id' => $id, 'reference' => $row['reference']];
    }

    /**
     * The complete public intake pipeline — honeypot, throttle, required-field
     * pass over the posting's own form configuration, screening questions with
     * knockout answers, resume upload, storage and notifications.
     *
     * Lives here rather than in a controller because two public surfaces run it:
     * the website proxy (Careers_api) and the embeddable widget (Careers_embed).
     * Neither may validate a candidate's submission on its own.
     *
     * @param  object $job     a published posting
     * @param  array  $post    the submitted fields
     * @param  array  $files   $_FILES
     * @param  array  $context ip_address, user_agent, source
     * @return array  ['ok' => bool, 'status' => int, 'error' => string, 'reference' => string]
     */
    public function process_public_application($job, array $post, array $files, array $context = [])
    {
        if (!careers_opt_bool('careers_allow_public_apply')) {
            return ['ok' => false, 'status' => 403, 'error' => 'Applications are currently closed.'];
        }

        if (!empty($job->deadline) && $job->deadline !== '0000-00-00' && $job->deadline < date('Y-m-d')) {
            return ['ok' => false, 'status' => 410, 'error' => 'The deadline for this position has passed.'];
        }

        if ($job->apply_mode === 'external') {
            return ['ok' => false, 'status' => 400, 'error' => 'This position is handled on an external site.'];
        }

        // Honeypot: a hidden field only a bot fills in. Answered with a success
        // shape on purpose, so the bot has nothing to learn from the response.
        if (trim((string) ($post['company_website'] ?? '')) !== '') {
            return ['ok' => true, 'status' => 200, 'reference' => 'ACK', 'error' => ''];
        }

        $ip = (string) ($context['ip_address'] ?? '');
        if ($ip !== '' && $this->throttled($ip)) {
            return ['ok' => false, 'status' => 429, 'error' => 'Too many applications from this address. Please try again later.'];
        }

        $fields  = careers_job_form_fields($job);
        $missing = [];

        foreach (['name' => 'Full name', 'email' => 'Email address'] as $key => $label) {
            if (trim((string) ($post[$key] ?? '')) === '') {
                $missing[] = $label;
            }
        }
        if (!empty($fields['phone']) && trim((string) ($post['phone'] ?? '')) === '') {
            $missing[] = 'Phone number';
        }

        if (!empty($missing)) {
            return ['ok' => false, 'status' => 422, 'error' => 'Please provide: ' . implode(', ', $missing) . '.'];
        }

        if (!filter_var(trim((string) $post['email']), FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'status' => 422, 'error' => 'Please enter a valid email address.'];
        }

        // Screening questions. A knockout match is still stored — recruiters want
        // to see who was filtered out and why — but lands as Not Selected.
        $answers  = [];
        $knockout = false;

        foreach ($this->questions($job->id) as $question) {
            $value = $post['q_' . $question->id] ?? '';

            if (is_array($value)) {
                $value = implode(', ', array_map(static function ($v) {
                    return substr(trim(strip_tags((string) $v)), 0, 191);
                }, $value));
            } else {
                $value = substr(trim(strip_tags((string) $value)), 0, 2000);
            }

            if ($question->required && $value === '') {
                return ['ok' => false, 'status' => 422, 'error' => 'Please answer: ' . $question->question];
            }

            $answers[] = ['q' => $question->question, 'a' => $value];

            if ($question->knockout_answer !== '' && strcasecmp($value, $question->knockout_answer) === 0) {
                $knockout = true;
            }
        }

        $resume = [];
        if (!empty($files['resume']['name'])) {
            $stored = careers_store_resume($files['resume'], $job->id);

            if (!$stored['ok']) {
                return ['ok' => false, 'status' => 422, 'error' => $stored['error']];
            }
            $resume = $stored;
        } elseif (careers_opt_bool('careers_resume_required') && !empty($fields['resume'])) {
            return ['ok' => false, 'status' => 422, 'error' => 'Please attach your resume.'];
        }

        $result = $this->create_application($job, [
            'name'                => $post['name'] ?? '',
            'email'               => $post['email'] ?? '',
            'phone'               => $post['phone'] ?? '',
            'current_location'    => $post['current_location'] ?? '',
            'current_company'     => $post['current_company'] ?? '',
            'current_designation' => $post['current_designation'] ?? '',
            'total_experience'    => $post['total_experience'] ?? null,
            'current_ctc'         => $post['current_ctc'] ?? '',
            'expected_ctc'        => $post['expected_ctc'] ?? '',
            'notice_period'       => $post['notice_period'] ?? '',
            'linkedin_url'        => $post['linkedin_url'] ?? '',
            'portfolio_url'       => $post['portfolio_url'] ?? '',
            'cover_letter'        => $post['cover_letter'] ?? '',
            'source'              => $post['source'] ?? ($context['source'] ?? 'website'),
            'utm'                 => $post['utm'] ?? '',
            'ip_address'          => $ip,
            'user_agent'          => (string) ($context['user_agent'] ?? ''),
        ], $answers, $resume);

        if (empty($result['ok'])) {
            return [
                'ok'        => false,
                'status'    => !empty($result['duplicate']) ? 409 : 422,
                'error'     => $result['error'],
                'duplicate' => !empty($result['duplicate']),
            ];
        }

        if ($knockout) {
            $this->set_stage($result['id'], 'rejected', 'Screening question knockout');
        }

        $this->notify_new_application($job, $result['id']);

        return ['ok' => true, 'status' => 200, 'error' => '', 'id' => $result['id'], 'reference' => $result['reference']];
    }

    /**
     * Candidate acknowledgement + internal alert. A failure here never fails the
     * application itself — the row is already stored.
     */
    public function notify_new_application($job, $application_id)
    {
        $application = $this->application($application_id);

        if (!$application) {
            return;
        }

        try {
            if (careers_opt_bool('careers_ack_enabled')) {
                careers_send_email(
                    $application->email,
                    'We received your application for ' . $job->title,
                    'email_application_received',
                    ['application' => $application, 'job' => $job]
                );
            }

            if (careers_opt_bool('careers_admin_notify')) {
                $recipients = careers_notification_recipients($job);

                if (!empty($recipients)) {
                    careers_send_email(
                        $recipients,
                        'New application: ' . $job->title . ' — ' . $application->name,
                        'email_new_application',
                        ['application' => $application, 'job' => $job],
                        careers_resume_full_path($application)
                    );
                }
            }
        } catch (Throwable $e) {
            log_activity('Careers: notification failed for application ' . $application_id . ' — ' . $e->getMessage());
        }
    }

    /**
     * Intake throttle: no more than 8 applications an hour from one address.
     * Runs on the stored rows, so it needs no extra table and survives a restart.
     */
    public function throttled($ip)
    {
        $recent = $this->db
            ->where('ip_address', $ip)
            ->where('created_at >=', date('Y-m-d H:i:s', strtotime('-1 hour')))
            ->count_all_results($this->t_apps);

        return $recent >= 8;
    }

    private function clean_url($url)
    {
        $url = trim((string) $url);

        if ($url === '') {
            return '';
        }
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . ltrim($url, '/');
        }

        return filter_var($url, FILTER_VALIDATE_URL) ? $url : '';
    }

    /**
     * Move a candidate through the pipeline. Every move writes a timeline entry
     * so the history survives even when the stage is later changed back.
     */
    public function set_stage($id, $stage, $reason = '')
    {
        if (!array_key_exists($stage, careers_stages())) {
            return false;
        }

        $application = $this->application($id);
        if (!$application || $application->stage === $stage) {
            return false;
        }

        $update = [
            'stage'            => $stage,
            'stage_changed_at' => date('Y-m-d H:i:s'),
            'last_activity_at' => date('Y-m-d H:i:s'),
            'updated_at'       => date('Y-m-d H:i:s'),
        ];

        if ($stage === 'hired') {
            $update['hired_at'] = date('Y-m-d H:i:s');
        }
        if ($stage === 'rejected' && $reason !== '') {
            $update['rejection_reason'] = substr(trim($reason), 0, 500);
        }

        $this->db->where('id', (int) $id)->update($this->t_apps, $update);

        $this->add_activity($id, 'stage', careers_stage_label($application->stage) . ' → ' . careers_stage_label($stage)
            . ($reason !== '' ? ' — ' . $reason : ''), ['from' => $application->stage, 'to' => $stage]);

        return true;
    }

    public function update_application($id, $data)
    {
        $allowed = ['rating', 'is_starred', 'tags', 'assigned_to', 'rejection_reason', 'name', 'email', 'phone',
            'current_location', 'current_company', 'current_designation', 'total_experience', 'current_ctc',
            'expected_ctc', 'notice_period', 'linkedin_url', 'portfolio_url'];

        $update = array_intersect_key($data, array_flip($allowed));
        if (empty($update)) {
            return false;
        }

        $update['last_activity_at'] = date('Y-m-d H:i:s');
        $update['updated_at']       = date('Y-m-d H:i:s');

        $this->db->where('id', (int) $id)->update($this->t_apps, $update);

        return true;
    }

    public function delete_application($id, $log = true)
    {
        $id          = (int) $id;
        $application = $this->db->where('id', $id)->get($this->t_apps)->row();

        if (!$application) {
            return false;
        }

        $file = careers_resume_full_path($application);
        if ($file !== '') {
            @unlink($file);
        }

        $this->db->where('application_id', $id)->delete($this->t_act);
        $this->db->where('application_id', $id)->delete($this->t_int);
        $this->db->where('id', $id)->delete($this->t_apps);

        if ($log) {
            log_activity('Careers: application deleted [' . $application->reference . ']');
        }

        return true;
    }

    /* ── Timeline ── */

    public function add_activity($application_id, $type, $content, $meta = [], $staff_id = null)
    {
        $this->db->insert($this->t_act, [
            'application_id' => (int) $application_id,
            'staff_id'       => $staff_id === null ? (int) get_staff_user_id() : (int) $staff_id,
            'type'           => $type,
            'content'        => $content,
            'meta'           => !empty($meta) ? json_encode($meta) : null,
            'created_at'     => date('Y-m-d H:i:s'),
        ]);

        $this->db->where('id', (int) $application_id)->update($this->t_apps, ['last_activity_at' => date('Y-m-d H:i:s')]);

        return (int) $this->db->insert_id();
    }

    public function activity($application_id)
    {
        $this->db->select($this->t_act . '.*, CONCAT(' . db_prefix() . 'staff.firstname, " ", ' . db_prefix() . 'staff.lastname) AS staff_name');
        $this->db->join(db_prefix() . 'staff', db_prefix() . 'staff.staffid = ' . $this->t_act . '.staff_id', 'left');
        $this->db->where('application_id', (int) $application_id);
        $this->db->order_by($this->t_act . '.id', 'desc');

        return $this->db->get($this->t_act)->result();
    }

    public function delete_activity($id)
    {
        $this->db->where('id', (int) $id)->where('type', 'note')->delete($this->t_act);

        return $this->db->affected_rows() > 0;
    }

    /**
     * Every other application from the same person — the one signal a recruiter
     * always wants and no single-record view can show.
     */
    public function other_applications($application)
    {
        $this->application_select();
        $this->db->where($this->t_apps . '.email', $application->email);
        $this->db->where($this->t_apps . '.id !=', (int) $application->id);
        $this->db->order_by($this->t_apps . '.created_at', 'desc');
        $this->db->limit(10);

        return $this->db->get($this->t_apps)->result();
    }

    /* ═══════════════════════════ 5. Interviews ════════════════════════════ */

    public function interviews($filters = [])
    {
        $this->db->select(
            $this->t_int . '.*,'
            . $this->t_apps . '.name AS candidate_name,'
            . $this->t_apps . '.email AS candidate_email,'
            . $this->t_apps . '.phone AS candidate_phone,'
            . $this->t_apps . '.stage AS candidate_stage,'
            . $this->t_jobs . '.title AS job_title'
        );
        $this->db->join($this->t_apps, $this->t_apps . '.id = ' . $this->t_int . '.application_id', 'left');
        $this->db->join($this->t_jobs, $this->t_jobs . '.id = ' . $this->t_int . '.job_id', 'left');

        if (!empty($filters['application'])) {
            $this->db->where($this->t_int . '.application_id', (int) $filters['application']);
        }
        if (!empty($filters['status'])) {
            $this->db->where($this->t_int . '.status', $filters['status']);
        }
        if (!empty($filters['upcoming'])) {
            $this->db->where($this->t_int . '.scheduled_at >=', date('Y-m-d 00:00:00'));
            $this->db->where($this->t_int . '.status', 'scheduled');
        }
        if (!empty($filters['from'])) {
            $this->db->where('DATE(' . $this->t_int . '.scheduled_at) >=', to_sql_date($filters['from']));
        }
        if (!empty($filters['to'])) {
            $this->db->where('DATE(' . $this->t_int . '.scheduled_at) <=', to_sql_date($filters['to']));
        }
        if (!empty($filters['interviewer'])) {
            $this->db->like($this->t_int . '.interviewers', ',' . (int) $filters['interviewer'] . ',');
        }

        $this->db->order_by($this->t_int . '.scheduled_at', !empty($filters['upcoming']) ? 'asc' : 'desc');

        return $this->db->get($this->t_int)->result();
    }

    public function interview($id)
    {
        return $this->db->where('id', (int) $id)->get($this->t_int)->row();
    }

    public function save_interview($data, $id = 0)
    {
        $application_id = (int) ($data['application_id'] ?? 0);
        $application    = $this->application($application_id);

        if (!$application || empty($data['scheduled_at'])) {
            return false;
        }

        // Interviewers are stored comma-wrapped (",4,7,") so a LIKE ',7,' filter
        // can never match staff 17 or 71.
        $interviewers = array_filter(array_map('intval', (array) ($data['interviewers'] ?? [])));

        $row = [
            'application_id'   => $application_id,
            'job_id'           => (int) $application->job_id,
            'title'            => substr(trim(strip_tags((string) ($data['title'] ?? 'Interview'))), 0, 191) ?: 'Interview',
            'round'            => max(1, (int) ($data['round'] ?? 1)),
            'mode'             => array_key_exists((string) ($data['mode'] ?? ''), careers_interview_modes()) ? $data['mode'] : 'video',
            'location'         => substr(trim(strip_tags((string) ($data['location'] ?? ''))), 0, 500),
            'meeting_link'     => substr($this->clean_url($data['meeting_link'] ?? ''), 0, 500),
            'scheduled_at'     => to_sql_date($data['scheduled_at'], true),
            'duration'         => max(5, (int) ($data['duration'] ?? 45)),
            'interviewers'     => $interviewers ? ',' . implode(',', $interviewers) . ',' : '',
            'status'           => array_key_exists((string) ($data['status'] ?? ''), careers_interview_statuses()) ? $data['status'] : 'scheduled',
            'feedback'         => substr(trim((string) ($data['feedback'] ?? '')), 0, 5000),
            'rating'           => min(5, max(0, (int) ($data['rating'] ?? 0))),
            'notify_candidate' => !empty($data['notify_candidate']) ? 1 : 0,
        ];

        if ($id) {
            $this->db->where('id', (int) $id)->update($this->t_int, $row);
        } else {
            $row['created_by'] = get_staff_user_id();
            $row['created_at'] = date('Y-m-d H:i:s');
            $this->db->insert($this->t_int, $row);
            $id = (int) $this->db->insert_id();
        }

        $this->add_activity(
            $application_id,
            'interview',
            $row['title'] . ' — ' . _dt($row['scheduled_at']) . ' (' . careers_interview_modes()[$row['mode']]['label'] . ')',
            ['interview_id' => $id, 'status' => $row['status']]
        );

        return (int) $id;
    }

    public function delete_interview($id)
    {
        $interview = $this->interview($id);
        if (!$interview) {
            return false;
        }

        $this->db->where('id', (int) $id)->delete($this->t_int);
        $this->add_activity($interview->application_id, 'system', 'Interview cancelled — ' . _dt($interview->scheduled_at));

        return true;
    }

    /* ══════════════════════════ 6. Subscribers ════════════════════════════ */

    public function subscribe($email, $name = '', $departments = '', $job_types = '')
    {
        $email = strtolower(trim((string) $email));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $existing = $this->db->where('email', $email)->get($this->t_subs)->row();

        $row = [
            'name'        => substr(trim(strip_tags((string) $name)), 0, 191),
            'departments' => substr(trim((string) $departments), 0, 255),
            'job_types'   => substr(trim((string) $job_types), 0, 255),
            'active'      => 1,
        ];

        if ($existing) {
            $this->db->where('id', $existing->id)->update($this->t_subs, $row);

            return (int) $existing->id;
        }

        $row['email']      = $email;
        $row['token']      = bin2hex(random_bytes(16));
        $row['created_at'] = date('Y-m-d H:i:s');

        $this->db->insert($this->t_subs, $row);

        return (int) $this->db->insert_id();
    }

    public function unsubscribe($token)
    {
        $this->db->where('token', (string) $token)->update($this->t_subs, ['active' => 0]);

        return $this->db->affected_rows() > 0;
    }

    public function subscribers($only_active = true)
    {
        if ($only_active) {
            $this->db->where('active', 1);
        }

        return $this->db->order_by('created_at', 'desc')->get($this->t_subs)->result();
    }

    /* ═══════════════════════════ 7. Analytics ═════════════════════════════ */

    /**
     * Everything the dashboard needs, in a handful of aggregate queries rather
     * than one row-scan per card.
     */
    public function dashboard($days = 30)
    {
        $days = in_array((int) $days, [7, 30, 90, 365], true) ? (int) $days : 30;
        $from = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));

        $kpi = [
            'published'   => (int) $this->db->where('status', 'published')->count_all_results($this->t_jobs),
            'draft'       => (int) $this->db->where('status', 'draft')->count_all_results($this->t_jobs),
            'total_jobs'  => (int) $this->db->count_all($this->t_jobs),
            'total_apps'  => (int) $this->db->count_all($this->t_apps),
            'new_apps'    => (int) $this->db->where('stage', 'new')->count_all_results($this->t_apps),
            'period_apps' => (int) $this->db->where('DATE(created_at) >=', $from)->count_all_results($this->t_apps),
            'interviews'  => (int) $this->db->where('stage', 'interview')->count_all_results($this->t_apps),
            'offers'      => (int) $this->db->where('stage', 'offer')->count_all_results($this->t_apps),
            'hired'       => (int) $this->db->where('stage', 'hired')->count_all_results($this->t_apps),
            'upcoming_interviews' => (int) $this->db
                ->where('status', 'scheduled')
                ->where('scheduled_at >=', date('Y-m-d H:i:s'))
                ->count_all_results($this->t_int),
        ];

        $views_row    = $this->db->select_sum('views')->where('stat_date >=', $from)->get($this->t_stats)->row();
        $kpi['views'] = (int) ($views_row && $views_row->views !== null ? $views_row->views : 0);

        // Applications ÷ views over the same window: the number that tells a
        // recruiter whether the posting or the page is the problem.
        $kpi['conversion'] = $kpi['views'] > 0 ? round(($kpi['period_apps'] / $kpi['views']) * 100, 1) : null;

        // Average days from application to hire, over hires in the window.
        $hire = $this->db->query(
            'SELECT AVG(DATEDIFF(hired_at, created_at)) AS days FROM ' . $this->t_apps . '
             WHERE stage = "hired" AND hired_at IS NOT NULL AND DATE(hired_at) >= ?',
            [$from]
        )->row();
        $kpi['time_to_hire'] = ($hire && $hire->days !== null) ? round((float) $hire->days) : null;

        // Daily series — zero-filled in PHP so the chart never has gaps.
        $series = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date          = date('Y-m-d', strtotime('-' . $i . ' days'));
            $series[$date] = ['date' => $date, 'applications' => 0, 'views' => 0];
        }

        $rows = $this->db->query(
            'SELECT stat_date, SUM(views) AS views, SUM(applications) AS applications
             FROM ' . $this->t_stats . ' WHERE stat_date >= ? GROUP BY stat_date',
            [$from]
        )->result();

        foreach ($rows as $row) {
            if (isset($series[$row->stat_date])) {
                $series[$row->stat_date]['views']        = (int) $row->views;
                $series[$row->stat_date]['applications'] = (int) $row->applications;
            }
        }

        // The rollup only knows about applications that arrived after the module
        // started counting; the applications table is the source of truth, so a
        // second pass overwrites it for the same window.
        $by_day = $this->db->query(
            'SELECT DATE(created_at) AS d, COUNT(*) AS c FROM ' . $this->t_apps . '
             WHERE DATE(created_at) >= ? GROUP BY DATE(created_at)',
            [$from]
        )->result();

        foreach ($by_day as $row) {
            if (isset($series[$row->d])) {
                $series[$row->d]['applications'] = (int) $row->c;
            }
        }

        $stages = [];
        foreach ($this->db->query('SELECT stage, COUNT(*) AS c FROM ' . $this->t_apps . ' GROUP BY stage')->result() as $row) {
            $stages[$row->stage] = (int) $row->c;
        }

        $sources = $this->db->query(
            'SELECT source, COUNT(*) AS c FROM ' . $this->t_apps . '
             WHERE DATE(created_at) >= ? GROUP BY source ORDER BY c DESC LIMIT 6',
            [$from]
        )->result();

        $this->job_select();
        $this->db->where($this->t_jobs . '.status', 'published');
        $this->db->order_by('applications_count', 'desc');
        $this->db->limit(6);
        $top_jobs = $this->db->get($this->t_jobs)->result();

        return [
            'days'      => $days,
            'kpi'       => $kpi,
            'series'    => array_values($series),
            'stages'    => $stages,
            'sources'   => $sources,
            'top_jobs'  => $top_jobs,
            'recent'    => $this->applications([], 8),
            'upcoming'  => $this->interviews(['upcoming' => true]),
        ];
    }

    /** Counts per stage for the kanban column headers. */
    public function stage_counts($filters = [])
    {
        $counts = [];
        foreach (array_keys(careers_stages()) as $stage) {
            $counts[$stage] = 0;
        }

        $this->db->select('stage, COUNT(*) AS c');
        if (!empty($filters['job'])) {
            $this->db->where('job_id', (int) $filters['job']);
        }
        if (!empty($filters['from'])) {
            $this->db->where('DATE(created_at) >=', to_sql_date($filters['from']));
        }
        $this->db->group_by('stage');

        foreach ($this->db->get($this->t_apps)->result() as $row) {
            $counts[$row->stage] = (int) $row->c;
        }

        return $counts;
    }

    /** Newer-than-$since count, for the live "new applications" poller. */
    public function applications_since($since)
    {
        $since = $since ? date('Y-m-d H:i:s', strtotime($since)) : date('Y-m-d H:i:s', strtotime('-5 minutes'));

        $this->application_select();
        $this->db->where($this->t_apps . '.created_at >', $since);
        $this->db->order_by($this->t_apps . '.created_at', 'desc');
        $this->db->limit(20);

        return $this->db->get($this->t_apps)->result();
    }

    /* ══════════════════════════ 8. Automation ═════════════════════════════ */

    /**
     * Postings past their deadline stop showing on the website immediately
     * (public_jobs() filters on the date); this is what makes the CRM agree.
     */
    public function close_expired_jobs()
    {
        $expired = $this->db
            ->where('status', 'published')
            ->where('deadline IS NOT NULL')
            ->where("deadline != '0000-00-00'")
            ->where('deadline <', date('Y-m-d'))
            ->get($this->t_jobs)->result();

        foreach ($expired as $job) {
            $this->db->where('id', $job->id)->update($this->t_jobs, [
                'status'     => 'closed',
                'closed_at'  => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            log_activity('Careers: job auto-closed past deadline [' . $job->title . ', ID: ' . $job->id . ']');
        }

        return count($expired);
    }

    /**
     * One reminder per interview, 24 hours ahead, to the candidate and to every
     * interviewer. reminder_sent is the idempotency guard — cron runs far more
     * often than once a day.
     */
    public function send_interview_reminders()
    {
        $due = $this->db
            ->select($this->t_int . '.*, ' . $this->t_apps . '.name AS candidate_name, ' . $this->t_apps . '.email AS candidate_email, ' . $this->t_jobs . '.title AS job_title')
            ->join($this->t_apps, $this->t_apps . '.id = ' . $this->t_int . '.application_id', 'left')
            ->join($this->t_jobs, $this->t_jobs . '.id = ' . $this->t_int . '.job_id', 'left')
            ->where($this->t_int . '.status', 'scheduled')
            ->where($this->t_int . '.reminder_sent', 0)
            ->where($this->t_int . '.scheduled_at >', date('Y-m-d H:i:s'))
            ->where($this->t_int . '.scheduled_at <=', date('Y-m-d H:i:s', strtotime('+24 hours')))
            ->get($this->t_int)->result();

        foreach ($due as $interview) {
            $this->db->where('id', $interview->id)->update($this->t_int, ['reminder_sent' => 1]);

            if ($interview->notify_candidate && !empty($interview->candidate_email)) {
                careers_send_email(
                    $interview->candidate_email,
                    'Reminder: your interview for ' . $interview->job_title . ' is tomorrow',
                    'email_interview',
                    ['interview' => $interview, 'reminder' => true]
                );
            }

            $staff_ids = array_filter(array_map('intval', explode(',', (string) $interview->interviewers)));
            if (!empty($staff_ids)) {
                $emails = $this->db->select('email')->where_in('staffid', $staff_ids)->get(db_prefix() . 'staff')->result();
                careers_send_email(
                    array_column($emails, 'email'),
                    'Interview tomorrow: ' . $interview->candidate_name . ' — ' . $interview->job_title,
                    'email_interview',
                    ['interview' => $interview, 'reminder' => true, 'internal' => true]
                );
            }
        }

        return count($due);
    }

    /**
     * Retention: candidates who were not selected and have had no activity for
     * the configured window are removed with their resumes. Hired candidates
     * and anyone still in the pipeline are never touched.
     */
    public function purge_old_applications($days)
    {
        $cutoff = date('Y-m-d H:i:s', strtotime('-' . (int) $days . ' days'));

        // Raw predicate (third argument false): CI would otherwise try to
        // escape COALESCE(...) as if it were a column name.
        $stale = $this->db
            ->where_in('stage', ['rejected', 'withdrawn'])
            ->where('COALESCE(last_activity_at, created_at) < ' . $this->db->escape($cutoff), null, false)
            ->limit(200)
            ->get($this->t_apps)->result();

        foreach ($stale as $application) {
            $this->delete_application($application->id, false);
        }

        if (!empty($stale)) {
            log_activity('Careers: retention purge removed ' . count($stale) . ' application(s) older than ' . (int) $days . ' days');
        }

        return count($stale);
    }
}
