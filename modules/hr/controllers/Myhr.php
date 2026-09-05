<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Employee Self-Service ("My HR").
 *
 * Every action here is scoped to the LOGGED-IN staff member only. The employee
 * id is always taken from get_staff_user_id() — never from the request — so a
 * user can only ever read/write their own records. Record-by-id actions
 * (payslip, appraisal, document) additionally verify ownership before showing
 * anything.
 *
 * Access is gated by the `hr_self` staff-permission feature:
 *   view   → open the portal
 *   create → apply for leave
 *   edit   → update own profile (only superadmin-enabled fields)
 */
class Myhr extends AdminController
{
    protected $staff_id;

    public function __construct()
    {
        parent::__construct();
        $this->load->model('hr_model');
        $this->staff_id = get_staff_user_id();
    }

    protected function guard($capability = 'view')
    {
        if (!has_permission('hr_self', '', $capability) && !is_admin()) {
            access_denied('hr_self');
        }
    }

    /* --------------------------------------------------------- My Dashboard */

    public function index()
    {
        $this->guard();

        // Make sure the profile row exists so widgets have data to read.
        $this->hr_model->ensure_employee($this->staff_id);

        $month = (int) date('n');
        $year  = (int) date('Y');

        $employee = $this->hr_model->get_employee($this->staff_id);
        $counts   = $this->hr_model->get_staff_attendance_counts($this->staff_id, $month, $year);
        $leaves   = $this->hr_model->get_leave_requests(['r.staff_id' => $this->staff_id]);

        $data['title']      = _l('hr_my_dashboard');
        $data['employee']   = $employee;
        $data['counts']     = $counts;
        $data['statuses']   = hr_attendance_statuses();
        $data['leaves']     = $leaves;
        $data['balances']   = $this->leave_balances($year);
        $data['payslips']   = $this->hr_model->get_employee_payslips($this->staff_id);
        $data['trainings']  = $this->hr_model->get_employee_trainings($this->staff_id);
        $data['shift']      = $this->hr_model->get_staff_shift($this->staff_id);
        $data['alerts']     = $this->build_alerts($employee, $leaves, $year);

        // Benefits that apply to this employee, enriched with vesting progress
        // (gratuity, long-service award, ...) from their joining date.
        $benefits = $this->hr_model->get_employee_benefits($this->staff_id, $employee['role'] ?? null);
        foreach ($benefits as &$b) {
            $b['progress'] = $b['benefit_type'] === 'vesting'
                ? hr_benefit_progress($b['vesting_years'], $employee['date_of_joining'] ?? null)
                : null;
        }
        unset($b);
        $data['benefits'] = $benefits;

        $this->load->view('hr/self_dashboard', $data);
    }

    /* -------------------------------------------------------- My Attendance */

    public function attendance()
    {
        $this->guard();

        $month = (int) ($this->input->get('month') ?: date('n'));
        $year  = (int) ($this->input->get('year') ?: date('Y'));
        $month = max(1, min(12, $month));

        $data['title']      = _l('hr_my_attendance');
        $data['month']      = $month;
        $data['year']       = $year;
        $data['days']       = $this->hr_model->get_staff_month_attendance($this->staff_id, $month, $year);
        $data['counts']     = $this->hr_model->get_staff_attendance_counts($this->staff_id, $month, $year);
        $data['statuses']   = hr_attendance_statuses();
        $data['shift']      = $this->hr_model->get_staff_shift($this->staff_id);
        $data['holidays']   = [];
        foreach ($this->hr_model->get_holidays($year, true) as $h) {
            if ((int) date('n', strtotime($h['holiday_date'])) === $month) {
                $data['holidays'][(int) date('j', strtotime($h['holiday_date']))] = $h['name'];
            }
        }

        $this->load->view('hr/self_attendance', $data);
    }

    /* ------------------------------------------------- Field Punch (remote) */

    /**
     * Punch in / out from outside the office. The screen captures the device
     * location and a live selfie (or an uploaded photo, per HR Settings); the
     * server is what decides the punch type, the geofence result and whether the
     * punch needs review — the browser only supplies raw evidence.
     */
    public function field_punch()
    {
        $this->guard();

        if (!hr_field_enabled()) {
            set_alert('warning', 'Field attendance is turned off. Please contact HR.');
            redirect(admin_url('hr/myhr'));
        }

        $today = date('Y-m-d');
        $last  = $this->hr_model->get_last_field_punch($this->staff_id);

        $data['title']        = _l('hr_field_punch');
        $data['cfg']          = hr_field_settings();
        $data['sites']        = $this->hr_model->get_field_sites(true);
        $data['purposes']     = hr_field_purposes();
        $data['statuses']     = hr_field_statuses();
        $data['types']        = hr_field_punch_types();
        $data['last']         = $last;
        // Next punch alternates: after an IN the button offers OUT.
        $data['next_type']    = ($last && $last['punch_type'] === 'in' && $last['punch_date'] === $today) ? 'out' : 'in';
        $data['today_punches'] = $this->hr_model->get_staff_field_punches($this->staff_id, $today);
        $data['recent']       = $this->hr_model->get_field_punches([
            'staff_id' => $this->staff_id,
            'from'     => date('Y-m-d', strtotime('-30 days')),
            'to'       => $today,
        ], 100);

        $this->load->view('hr/self_field_punch', $data);
    }

    /**
     * Record one field punch for the logged-in employee. Everything is validated
     * server-side against the HR Settings rules; the staff id always comes from
     * the session, never from the request.
     */
    public function save_field_punch()
    {
        $this->guard();
        if (!$this->input->post()) {
            show_404();
        }

        $back = admin_url('hr/myhr/field_punch');
        $cfg  = hr_field_settings();
        if (!$cfg['enabled']) {
            set_alert('warning', 'Field attendance is turned off. Please contact HR.');
            redirect($back);
        }

        $staff_id = (int) $this->staff_id;
        $this->hr_model->ensure_employee($staff_id);

        $type = $this->input->post('punch_type');
        if (!array_key_exists($type, hr_field_punch_types())) {
            $type = 'in';
        }

        $now   = date('Y-m-d H:i:s');
        $today = date('Y-m-d');
        $last  = $this->hr_model->get_last_field_punch($staff_id);

        // Throttle accidental double taps and enforce alternating in/out within
        // the same day (an OUT the morning after an overnight IN is allowed).
        if ($last) {
            $gap_min = (strtotime($now) - strtotime($last['punch_at'])) / 60;
            if ($cfg['min_gap_minutes'] > 0 && $gap_min < $cfg['min_gap_minutes']) {
                set_alert('warning', 'Please wait ' . $cfg['min_gap_minutes'] . ' minute(s) between punches.');
                redirect($back);
            }
            if ($last['punch_date'] === $today && $last['punch_type'] === $type) {
                set_alert('warning', 'You have already punched ' . strtoupper($type) . ' today. Please punch '
                    . ($type === 'in' ? 'OUT' : 'IN') . ' next.');
                redirect($back);
            }
        }

        // ------------------------------------------------------------ location
        $lat = $this->input->post('latitude');
        $lng = $this->input->post('longitude');
        $lat = ($lat === '' || $lat === null) ? null : (float) $lat;
        $lng = ($lng === '' || $lng === null) ? null : (float) $lng;
        if ($lat !== null && ($lat < -90 || $lat > 90)) {
            $lat = null;
        }
        if ($lng !== null && ($lng < -180 || $lng > 180)) {
            $lng = null;
        }
        $accuracy = (int) $this->input->post('accuracy_m') ?: null;

        if ($cfg['require_location'] && ($lat === null || $lng === null)) {
            set_alert('danger', 'Location is required. Allow location access in your browser and try again.');
            redirect($back);
        }
        if ($cfg['max_accuracy_m'] > 0 && $accuracy && $accuracy > $cfg['max_accuracy_m']) {
            set_alert('danger', 'Your location accuracy is ±' . $accuracy . ' m, which is above the allowed '
                . $cfg['max_accuracy_m'] . ' m. Move to an open area and try again.');
            redirect($back);
        }

        // ------------------------------------------------------------ geofence
        $nearest = hr_field_nearest_site($lat, $lng, $this->hr_model->get_field_sites(true));
        $within  = $nearest ? (bool) $nearest['within'] : false;
        if ($cfg['geofence_mode'] === 'block' && $nearest && !$within) {
            set_alert('danger', 'You are ' . $nearest['distance_m'] . ' m away from ' . $nearest['site']['name']
                . '. Punching is only allowed within an approved location.');
            redirect($back);
        }

        // --------------------------------------------------------------- photo
        $photo        = null;
        $photo_source = null;
        $photo_res    = $this->store_field_photo($staff_id, $cfg);
        if (!empty($photo_res['error'])) {
            set_alert('danger', $photo_res['error']);
            redirect($back);
        }
        if (!empty($photo_res['file'])) {
            $photo        = $photo_res['file'];
            $photo_source = $photo_res['source'];
        }
        if ($cfg['require_photo'] && !$photo) {
            set_alert('danger', 'A live photo is required for a field punch. Start the camera and capture a selfie.');
            redirect($back);
        }

        $purpose = $this->input->post('purpose');
        if (!array_key_exists((string) $purpose, hr_field_purposes())) {
            $purpose = 'field_duty';
        }

        $status = $cfg['auto_approve'] ? 'approved' : 'pending';

        $id = $this->hr_model->add_field_punch([
            'staff_id'        => $staff_id,
            'punch_type'      => $type,
            'punch_at'        => $now,
            'punch_date'      => $today,
            'latitude'        => $lat,
            'longitude'       => $lng,
            'accuracy_m'      => $accuracy,
            'address'         => substr(trim((string) $this->input->post('address')), 0, 255) ?: null,
            'site_id'         => $nearest ? (int) $nearest['site']['id'] : null,
            'site_name'       => $nearest ? $nearest['site']['name'] : null,
            'distance_m'      => $nearest ? (int) $nearest['distance_m'] : null,
            'within_geofence' => $within ? 1 : 0,
            'photo'           => $photo,
            'photo_source'    => $photo_source,
            'purpose'         => $purpose,
            'note'            => substr(trim((string) $this->input->post('note')), 0, 255) ?: null,
            'device_info'     => substr((string) $this->input->user_agent(), 0, 255) ?: null,
            'ip_address'      => $this->input->ip_address(),
            'status'          => $status,
            'reviewed_by'     => null,
            'reviewed_at'     => null,
        ]);

        if ($id && $status === 'approved') {
            $this->hr_model->rebuild_field_attendance($staff_id, $today);
        }

        // Pending punches need a human: ping the reviewers once per punch.
        if ($id && $status === 'pending' && $cfg['notify_reviewers']) {
            $recipients = [];
            foreach (hr_field_reviewer_ids() as $sid) {
                if ($sid === $staff_id) {
                    continue;
                }
                add_notification([
                    'description'     => 'hr_field_punch_notification',
                    'touserid'        => $sid,
                    'fromcompany'     => 0,
                    'fromuserid'      => $staff_id,
                    'link'            => 'hr/field_attendance?status=pending',
                    'additional_data' => serialize([
                        get_staff_full_name($staff_id),
                        hr_field_punch_types()[$type]['label'],
                        _dt($now),
                    ]),
                ]);
                $recipients[] = $sid;
            }
            if ($recipients) {
                pusher_trigger_notification($recipients);
            }
        }

        // A punch waiting on a human is worth a message to the reviewers; an
        // auto-approved one needs nobody's attention.
        if ($id && $status === 'pending') {
            $punch = $this->hr_model->get_field_punch((int) $id);
            hr_fire_employee_hook('hr_field_punch_submitted', $staff_id, [
                'punch_type'      => hr_field_punch_types()[$type]['label'],
                'punch_date'      => _d($today),
                'punch_time'      => _dt($now),
                'punch_purpose'   => hr_field_purposes()[$purpose] ?? $purpose,
                'punch_site'      => (string) ($punch['site_name'] ?? ''),
                'punch_address'   => (string) ($punch['address'] ?? ''),
                'punch_distance'  => isset($punch['distance_m']) && $punch['distance_m'] !== null ? $punch['distance_m'] . ' m' : '',
                'geofence_status' => $within ? 'Inside geofence' : 'Outside geofence',
            ]);
        }

        if (!$id) {
            set_alert('danger', 'Could not record your punch. Please try again.');
        } elseif ($status === 'approved') {
            set_alert('success', hr_field_punch_types()[$type]['label'] . ' recorded at ' . date('g:i A') . '.');
        } else {
            set_alert('success', hr_field_punch_types()[$type]['label'] . ' submitted at ' . date('g:i A')
                . ' — awaiting HR approval.');
        }

        redirect($back);
    }

    /**
     * Save the punch photo. LIVE CAMERA CAPTURE ONLY — the photo must arrive as
     * a base64 data URL produced by the punch screen's camera. Gallery / file
     * uploads are deliberately not accepted (an old or borrowed photo would
     * defeat the point of the proof), so any posted file is ignored.
     * Returns ['file' => name|null, 'source' => 'camera'|null, 'error' => msg|null].
     */
    protected function store_field_photo($staff_id, $cfg)
    {
        $data_url = (string) $this->input->post('photo_data', false);
        if ($data_url === '') {
            return ['file' => null, 'source' => null, 'error' => null];
        }

        if (!preg_match('#^data:image/(jpeg|jpg|png|webp);base64,#i', $data_url, $m)) {
            return ['file' => null, 'source' => null, 'error' => 'The captured photo could not be read. Please retake it.'];
        }
        $ext    = strtolower($m[1]) === 'jpg' ? 'jpeg' : strtolower($m[1]);
        $binary = base64_decode(substr($data_url, strpos($data_url, ',') + 1), true);
        if ($binary === false || strlen($binary) < 1024) {
            return ['file' => null, 'source' => null, 'error' => 'The captured photo is invalid. Please retake it.'];
        }
        if (strlen($binary) > 8 * 1024 * 1024) {
            return ['file' => null, 'source' => null, 'error' => 'The captured photo is too large (max 8 MB).'];
        }

        $dir = hr_field_photo_dir($staff_id);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $name = 'fp_' . uniqid() . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);
        if (file_put_contents($dir . $name, $binary) === false) {
            return ['file' => null, 'source' => null, 'error' => 'Could not save the photo. Please try again.'];
        }

        return ['file' => $name, 'source' => 'camera', 'error' => null];
    }

    /**
     * Stream one of the employee's OWN punch photos (ownership enforced).
     */
    public function field_punch_photo($id)
    {
        $this->guard();

        $punch = $this->hr_model->get_field_punch((int) $id);
        if (!$punch || (int) $punch['staff_id'] !== (int) $this->staff_id || empty($punch['photo'])) {
            show_404();
        }
        hr_field_stream_photo($punch);
    }

    /* ------------------------------------------------------------- My Leave */

    public function leaves()
    {
        $this->guard();

        $year = (int) date('Y');

        $data['title']       = _l('hr_my_leaves');
        $data['requests']    = $this->hr_model->get_leave_requests(['r.staff_id' => $this->staff_id]);
        $data['leave_types'] = $this->hr_model->get_leave_types(true);
        $data['balances']    = $this->leave_balances($year);
        $data['can_apply']   = has_permission('hr_self', '', 'create') || is_admin();

        $this->load->view('hr/self_leaves', $data);
    }

    public function apply_leave()
    {
        $this->guard('create');
        if (!$this->input->post()) {
            show_404();
        }

        $is_half   = $this->input->post('is_half_day') ? 1 : 0;
        $from      = $this->input->post('from_date');
        $to        = $is_half ? $from : $this->input->post('to_date');
        $type_id   = (int) $this->input->post('leave_type_id');

        // Enforce the leave application rules (min notice / no same-day, unless
        // the chosen leave type is an emergency type). HR is exempt (see Hr).
        $leave_type = $this->hr_model->get_leave_type($type_id);
        $violation  = hr_leave_apply_violation($leave_type, $from);
        if ($violation !== '') {
            set_alert('warning', $violation);
            redirect(admin_url('hr/myhr/leaves'));
        }

        // Optional proof-of-reason upload (stored under the employee's own folder).
        $proof = hr_store_leave_proof($this->staff_id);
        if ($proof['error']) {
            set_alert('danger', $proof['error']);
            redirect(admin_url('hr/myhr/leaves'));
        }

        // staff_id is forced to the logged-in user — an employee can never file
        // leave on behalf of someone else.
        $id = $this->hr_model->add_leave_request([
            'staff_id'      => $this->staff_id,
            'leave_type_id' => (int) $this->input->post('leave_type_id'),
            'from_date'     => $from,
            'to_date'       => $to,
            'is_half_day'   => $is_half,
            'reason'        => $this->input->post('reason'),
            'proof_file'    => $proof['file'],
        ]);

        set_alert($id ? 'success' : 'danger', $id ? _l('added_successfully', _l('hr_leave_request')) : 'Invalid date range');
        redirect(admin_url('hr/myhr/leaves'));
    }

    /**
     * Stream the proof file for one of the employee's OWN leave requests.
     */
    public function leave_proof($id)
    {
        $this->guard();
        $request = $this->hr_model->get_leave_request((int) $id);
        // Own proof, or the proof of a request this user has to action.
        if (!$request || ((int) $request['staff_id'] !== (int) $this->staff_id
            && !hr_can_action_leave_level($request, $this->staff_id))) {
            show_404();
        }
        hr_output_leave_proof($request);
    }

    /* ------------------------------------------ Suggestions / Feedback (staff) */

    public function feedback()
    {
        $this->guard();

        $data['title']    = _l('hr_feedback');
        $data['feedback'] = $this->hr_model->get_staff_feedback($this->staff_id);

        $this->load->view('hr/self_feedback', $data);
    }

    public function submit_feedback()
    {
        $this->guard();
        if (!$this->input->post()) {
            show_404();
        }

        $subject = trim((string) $this->input->post('subject'));
        $message = trim((string) $this->input->post('message'));
        if ($subject === '' || $message === '') {
            set_alert('warning', 'Please enter a subject and your message.');
            redirect(admin_url('hr/myhr/feedback'));
        }

        $category = $this->input->post('category');
        if (!array_key_exists($category, hr_feedback_categories())) {
            $category = 'suggestion';
        }
        $anonymous = $this->input->post('is_anonymous') ? 1 : 0;

        $id = $this->hr_model->add_feedback([
            'staff_id'     => $this->staff_id,
            'category'     => $category,
            'subject'      => $subject,
            'message'      => $message,
            'is_anonymous' => $anonymous,
            'status'       => 'new',
        ]);

        // Notify management (admins + hr_feedback holders) of the new submission.
        if ($id) {
            $from       = $anonymous ? 'A team member (anonymous)' : get_staff_full_name($this->staff_id);
            $recipients = [];
            foreach (hr_feedback_manager_ids() as $sid) {
                if ($sid === (int) $this->staff_id) {
                    continue;
                }
                add_notification([
                    'description'     => 'hr_feedback_new_notification',
                    'touserid'        => $sid,
                    'fromcompany'     => 1,
                    'fromuserid'      => 0,
                    'link'            => 'hr/feedback',
                    'additional_data' => serialize([$from, $subject]),
                ]);
                $recipients[] = $sid;
            }
            if ($recipients) {
                pusher_trigger_notification($recipients);
            }
        }

        set_alert('success', 'Thank you! Your submission has been sent to management.');
        redirect(admin_url('hr/myhr/feedback'));
    }

    /* ---------------------------------------------- Leave Approvals (approver) */

    /**
     * Inbox of leave requests currently awaiting the logged-in user's approval
     * level. Available to any staff who is an approver in the chain — no HR
     * management permission required (a Team Lead need not have hr_leaves).
     */
    public function approvals()
    {
        $data['title']     = _l('hr_leave_approvals');
        $data['pending']   = $this->hr_model->get_pending_approvals_for_staff($this->staff_id);
        $data['chain']     = hr_leave_chain();
        $data['role_names'] = [];
        $this->load->model('roles_model');
        foreach ($this->roles_model->get() as $r) {
            $data['role_names'][(int) $r['roleid']] = $r['name'];
        }

        $this->load->view('hr/self_approvals', $data);
    }

    /**
     * Same review popup as the HR back office, for approvers working from the
     * self-service "Awaiting You" list.
     */
    public function leave_review($id)
    {
        $this->guard();

        $request = $this->hr_model->get_leave_request((int) $id);
        if (!$request) {
            show_404();
        }
        // Only the approver of the current level (or the applicant) may look.
        if (!hr_can_action_leave_level($request, $this->staff_id) && (int) $request['staff_id'] !== (int) $this->staff_id) {
            access_denied('hr_self');
        }

        $data['request']    = $request;
        $data['review']     = hr_leave_review_data($request);
        $data['can_act']    = hr_can_action_leave_level($request, $this->staff_id);
        $data['action_url'] = admin_url('hr/myhr/approve_leave/');
        $data['proof_url']  = admin_url('hr/myhr/leave_proof/' . (int) $id);
        $data['chain']      = hr_leave_chain();
        $data['role_names'] = [];
        $this->load->model('roles_model');
        foreach ($this->roles_model->get() as $r) {
            $data['role_names'][(int) $r['roleid']] = $r['name'];
        }

        $this->load->view('hr/leave_review', $data);
    }

    public function approve_leave($id, $action)
    {
        if (!in_array($action, ['approved', 'rejected'])) {
            show_404();
        }
        $request = $this->hr_model->get_leave_request((int) $id);
        if (!$request || $request['status'] !== 'pending') {
            show_404();
        }
        if (!hr_can_action_leave_level($request, $this->staff_id)) {
            access_denied('hr');
        }
        $this->hr_model->leave_stage_action((int) $id, $action, $this->staff_id, $this->input->post('note') ?: '');
        set_alert('success', _l('hr_leave_request') . ' ' . $action);
        redirect(admin_url('hr/myhr/approvals'));
    }

    /* ----------------------------------------------------------- My Profile */

    public function profile()
    {
        $this->guard();
        $this->hr_model->ensure_employee($this->staff_id);

        $this->load->model('departments_model');

        $data['title']           = _l('hr_my_profile');
        $data['employee']        = $this->hr_model->get_employee($this->staff_id);
        $data['departments']     = $this->departments_model->get();
        $data['designations']    = $this->hr_model->get_designations();
        $ess = hr_ess_config_for_staff($this->staff_id, $data['employee']['role'] ?? null);

        $data['editable_fields'] = (has_permission('hr_self', '', 'edit') || is_admin())
            ? $ess['editable_fields']
            : [];
        $data['field_labels']    = hr_self_field_options();
        $data['documents']       = $this->hr_model->get_documents($this->staff_id);
        $data['required_docs']   = $ess['required_documents'];

        $this->load->view('hr/self_profile', $data);
    }

    public function save_profile()
    {
        $this->guard('edit');
        if (!$this->input->post()) {
            show_404();
        }

        // Only fields enabled for THIS employee (resolved role/employee/global)
        // are ever written. Anything else in the POST body is ignored, so the
        // form cannot be tampered to update salary, department, statutory IDs.
        $ess     = hr_ess_config_for_staff($this->staff_id);
        $allowed = $ess['editable_fields'];
        $update  = [];
        foreach ($allowed as $field) {
            $val = $this->input->post($field);
            if ($val !== null) {
                $update[$field] = ($val === '') ? null : $val;
            }
        }

        if (!empty($update)) {
            $this->hr_model->save_employee($this->staff_id, $update);
            set_alert('success', _l('updated_successfully', _l('hr_my_profile')));
        } else {
            set_alert('warning', 'Nothing to update.');
        }

        redirect(admin_url('hr/myhr/profile'));
    }

    /**
     * Employee updates their own profile picture. Reuses the core staff
     * profile-image pipeline (validation + thumbnails + tblstaff.profile_image)
     * so the new photo shows everywhere in the app, not just here. Forced to the
     * logged-in staff id — an employee can only change their own picture.
     */
    public function save_profile_picture()
    {
        $this->guard();
        $is_ajax = $this->input->is_ajax_request();

        if (empty($_FILES['profile_image']['name'])) {
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Please choose an image first.']);
                die;
            }
            set_alert('warning', 'Please choose an image first.');
            redirect(admin_url('hr/myhr/profile'));
        }

        $ok = handle_staff_profile_image_upload($this->staff_id);

        // staff_profile_image_url() reads the in-memory $GLOBALS['current_user']
        // for the logged-in staff, but that row still holds the OLD profile_image
        // filename loaded at request start. Refresh it so the URL we hand back
        // points to the newly uploaded file instead of the previous one.
        if ($ok && isset($GLOBALS['current_user']) && (string) $this->staff_id === (string) get_staff_user_id()) {
            $fresh = $this->db->select('profile_image')
                ->where('staffid', $this->staff_id)
                ->get(db_prefix() . 'staff')
                ->row();
            if ($fresh) {
                $GLOBALS['current_user']->profile_image = $fresh->profile_image;
            }
        }

        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => (bool) $ok,
                'image'   => staff_profile_image_url($this->staff_id, 'small'),
                'message' => $ok ? _l('updated_successfully', _l('hr_my_profile')) : 'Could not update the photo. Please upload a JPG or PNG image.',
            ]);
            die;
        }

        if ($ok) {
            set_alert('success', _l('updated_successfully', _l('hr_my_profile')));
        } else {
            set_alert('warning', 'Could not update the photo. Please upload a JPG or PNG image.');
        }

        redirect(admin_url('hr/myhr/profile'));
    }

    /* ------------------------------------------------------------ Holidays */

    public function holidays()
    {
        $this->guard();
        $year = (int) ($this->input->get('year') ?: date('Y'));

        $data['title']    = _l('hr_holidays');
        $data['year']     = $year;
        $data['holidays'] = $this->hr_model->get_holidays($year, true);

        $this->load->view('hr/self_holidays', $data);
    }

    /* ---------------------------------------------------------- Trainings */

    public function trainings()
    {
        $this->guard();

        $data['title']     = _l('hr_my_trainings');
        $data['trainings'] = $this->hr_model->get_employee_trainings($this->staff_id);

        $this->load->view('hr/self_trainings', $data);
    }

    /* --------------------------------------------------------- Appraisals */

    public function appraisals()
    {
        $this->guard();

        // Only completed reviews are surfaced to the employee — in-progress
        // drafts stay private to the reviewer.
        $all = $this->hr_model->get_appraisals($this->staff_id);
        $data['appraisals'] = array_values(array_filter($all, function ($a) {
            return $a['status'] === 'completed';
        }));
        $data['title'] = _l('hr_my_appraisals');

        $this->load->view('hr/self_appraisals', $data);
    }

    public function appraisal($id)
    {
        $this->guard();

        $appraisal = $this->hr_model->get_appraisal((int) $id);
        // Ownership + completed check: never expose another employee's review or
        // an unfinished draft.
        if (!$appraisal || (int) $appraisal['staff_id'] !== (int) $this->staff_id || $appraisal['status'] !== 'completed') {
            blank_page(_l('access_denied'), 'danger');
        }

        $data['title']     = 'Appraisal #' . $appraisal['id'];
        $data['appraisal'] = $appraisal;
        $data['reviewer']  = $appraisal['reviewer_id'] ? get_staff_full_name($appraisal['reviewer_id']) : '';

        $this->load->view('hr/self_appraisal', $data);
    }

    /* -------------------------------------------------------------- Memos */

    public function memos()
    {
        $this->guard();

        $data['title'] = _l('hr_my_memos');
        $data['memos'] = $this->hr_model->get_staff_memos($this->staff_id);

        $this->load->view('hr/self_memos', $data);
    }

    /**
     * Employee files an acknowledgement receipt for a memo issued to them. The
     * memo id is validated to belong to the logged-in employee before writing.
     */
    public function acknowledge_memo($id)
    {
        $this->guard();
        if (!$this->input->post()) {
            show_404();
        }
        $signature = trim((string) $this->input->post('ack_signature'));
        if ($signature === '') {
            set_alert('warning', 'Please type your name to sign the acknowledgement.');
            redirect(admin_url('hr/myhr/memos'));
        }
        $agree = $this->input->post('ack_agree') !== 'dispute';
        $ok    = $this->hr_model->acknowledge_memo(
            (int) $id,
            $this->staff_id,
            mb_substr($signature, 0, 191),
            $this->input->post('ack_note', false),
            $agree
        );
        if ($ok) {
            // Notify the issuer that the employee has acknowledged.
            $memo = $this->hr_model->get_memo((int) $id);
            if ($memo && $memo['issued_by']) {
                add_notification([
                    'description'     => 'hr_memo_ack_notification',
                    'touserid'        => (int) $memo['issued_by'],
                    'fromcompany'     => 0,
                    'fromuserid'      => $this->staff_id,
                    'link'            => 'hr/memo/' . (int) $id,
                    'additional_data' => serialize([get_staff_full_name($this->staff_id), $memo['subject']]),
                ]);
                pusher_trigger_notification([(int) $memo['issued_by']]);
            }
            set_alert('success', 'Acknowledgement recorded. Thank you.');
        } else {
            set_alert('warning', 'This memo could not be acknowledged (already acknowledged or not found).');
        }
        redirect(admin_url('hr/myhr/memos'));
    }

    public function memo_attachment($id)
    {
        $this->guard();
        $memo = $this->hr_model->get_memo((int) $id);
        if (!$memo || (int) $memo['staff_id'] !== (int) $this->staff_id) {
            blank_page(_l('access_denied'), 'danger');
        }
        // Delegate the raw stream to the shared manager-side handler, which
        // re-checks that the requester owns the memo.
        redirect(admin_url('hr/memo_attachment/' . (int) $id));
    }

    /* --------------------------------------------------------- Onboarding */

    public function onboarding()
    {
        $this->guard();

        $ob = $this->hr_model->get_active_onboarding_for_staff($this->staff_id);
        $data['title']    = _l('hr_my_onboarding');
        $data['ob']       = $ob;
        $data['items']    = $ob ? $this->hr_model->get_onboarding_items($ob['id']) : [];
        $data['phases']   = hr_onboarding_phases();
        $data['statuses'] = hr_onboarding_item_statuses();

        $this->load->view('hr/self_onboarding', $data);
    }

    /* ----------------------------------------------------------- Payslips */

    public function payslips()
    {
        $this->guard();

        $data['title']    = _l('hr_my_payslips');
        $data['payslips'] = $this->hr_model->get_employee_payslips($this->staff_id);

        $this->load->view('hr/self_payslips', $data);
    }

    public function payslip($id)
    {
        $this->guard();

        $slip = $this->hr_model->get_payslip((int) $id);
        // Own payslip only, and only once its run is finalized.
        if (!$slip || (int) $slip['staff_id'] !== (int) $this->staff_id || $slip['run_status'] !== 'finalized') {
            blank_page(_l('access_denied'), 'danger');
        }

        $this->load->model('departments_model');
        $dept = $slip['department_id'] ? $this->departments_model->get($slip['department_id']) : null;

        $data['title']      = _l('hr_payslip');
        $data['slip']       = $slip;
        $data['department'] = $dept;

        // Reuse the manager payslip template — it is already read-only.
        $this->load->view('hr/payslip', $data);
    }

    /* ----------------------------------------------------------- Documents */

    /**
     * Employee submits a document to HR. Stored against their own staff_id with
     * source=employee / status=submitted so HR can review it. The document type
     * usually matches a required-checklist item, but a free "Other" upload is
     * allowed too.
     */
    public function upload_document()
    {
        $this->guard();
        $staff_id = (int) $this->staff_id;

        if (empty($_FILES['document']['name'])) {
            set_alert('warning', 'Please choose a file to upload.');
            redirect(admin_url('hr/myhr/profile') . '#my-documents');
        }

        $ext = strtolower(pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, hr_document_allowed_extensions(), true)) {
            set_alert('danger', 'File type not allowed. Use PDF, image, Word or Excel files.');
            redirect(admin_url('hr/myhr/profile') . '#my-documents');
        }

        $dir = hr_upload_dir($staff_id);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $filename = uniqid('doc_') . '.' . $ext;
        if (!move_uploaded_file($_FILES['document']['tmp_name'], $dir . $filename)) {
            set_alert('danger', 'Upload failed. Please try again.');
            redirect(admin_url('hr/myhr/profile') . '#my-documents');
        }

        $doc_type = $this->input->post('doc_type') ?: 'Other';
        $this->hr_model->add_document([
            'staff_id'    => $staff_id,
            'doc_type'    => $doc_type,
            'title'       => $this->input->post('title') ?: $doc_type,
            'file_name'   => $filename,
            'issue_date'  => $this->input->post('issue_date') ?: null,
            'expiry_date' => $this->input->post('expiry_date') ?: null,
            'uploaded_by' => $staff_id,
            'source'      => 'employee',
            'status'      => 'submitted',
        ]);

        set_alert('success', 'Document submitted to HR.');
        redirect(admin_url('hr/myhr/profile') . '#my-documents');
    }

    /**
     * Stream one of the employee's own documents inline (for image/PDF preview).
     */
    public function view_document($id)
    {
        $this->guard();
        $this->serve_own_document((int) $id, false);
    }

    public function download_document($id)
    {
        $this->guard();
        $this->serve_own_document((int) $id, true);
    }

    /**
     * Shared file streamer with a strict ownership check. $download toggles
     * attachment vs inline (preview) disposition.
     */
    protected function serve_own_document($id, $download)
    {
        $doc = $this->hr_model->get_document($id);
        if (!$doc || (int) $doc['staff_id'] !== (int) $this->staff_id) {
            show_404();
        }
        $path = hr_upload_dir($doc['staff_id']) . $doc['file_name'];
        if (!file_exists($path)) {
            show_404();
        }

        if ($download) {
            $this->load->helper('download');
            $safe = preg_replace('/[^\w\s\.\-]/u', '_', $doc['title']);
            force_download($safe . '.' . pathinfo($doc['file_name'], PATHINFO_EXTENSION), file_get_contents($path));

            return;
        }

        $mimes = [
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
            'webp' => 'image/webp', 'pdf' => 'application/pdf',
        ];
        $ext  = strtolower(pathinfo($doc['file_name'], PATHINFO_EXTENSION));
        $mime = $mimes[$ext] ?? 'application/octet-stream';
        // Non-inline types fall back to a normal download.
        if (!isset($mimes[$ext])) {
            $this->load->helper('download');
            $safe = preg_replace('/[^\w\s\.\-]/u', '_', $doc['title']);
            force_download($safe . '.' . $ext, file_get_contents($path));

            return;
        }
        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline; filename="' . basename($doc['file_name']) . '"');
        header('Content-Length: ' . filesize($path));
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    /* ------------------------------------------------------------- Helpers */

    /**
     * Per-type leave balance for the current employee: allocated, used and
     * remaining. Only leave types with an allocation OR usage are returned.
     */
    protected function leave_balances($year)
    {
        $types   = $this->hr_model->get_leave_types(true);
        $alloc   = $this->hr_model->get_leave_allocations($year)[$this->staff_id] ?? [];
        $carried = $this->hr_model->get_leave_carried($year)[$this->staff_id] ?? [];
        $used    = $this->hr_model->get_leave_used($year)[$this->staff_id] ?? [];

        $out = [];
        foreach ($types as $t) {
            $a  = (float) ($alloc[$t['id']] ?? 0);
            $cf = (float) ($carried[$t['id']] ?? 0);
            $u  = (float) ($used[$t['id']] ?? 0);
            if ($a == 0 && $cf == 0 && $u == 0) {
                continue;
            }
            $out[] = [
                'name'      => $t['name'],
                'code'      => $t['code'],
                'color'     => $t['color'],
                'icon'      => hr_leave_type_icon($t),
                'allocated' => $a,
                'carried'   => $cf,
                'used'      => $u,
                'remaining' => $a + $cf - $u,
            ];
        }

        return $out;
    }

    /**
     * Smart dashboard alerts derived from the employee's own data. Each entry:
     * ['level' => success|info|warning|danger, 'icon' => .., 'text' => ..].
     */
    protected function build_alerts($employee, $leaves, $year)
    {
        $alerts = [];
        $today  = date('Y-m-d');

        // Pending leave requests
        $pending = 0;
        foreach ($leaves as $lr) {
            if ($lr['status'] === 'pending') {
                $pending++;
            }
        }
        if ($pending > 0) {
            $alerts[] = ['level' => 'info', 'icon' => 'fa-hourglass-half',
                'text' => $pending . ' leave request(s) awaiting approval.'];
        }

        // Low leave balance
        foreach ($this->leave_balances($year) as $b) {
            if ($b['allocated'] > 0 && $b['remaining'] <= 0) {
                $alerts[] = ['level' => 'danger', 'icon' => 'fa-plane',
                    'text' => 'No ' . $b['name'] . ' balance left (' . (float) $b['remaining'] . ' remaining).'];
            } elseif ($b['allocated'] > 0 && $b['remaining'] <= 2) {
                $alerts[] = ['level' => 'warning', 'icon' => 'fa-plane',
                    'text' => 'Only ' . (float) $b['remaining'] . ' ' . $b['name'] . ' day(s) left.'];
            }
        }

        // Own documents expiring soon
        $expiring = $this->hr_model->get_staff_expiring_documents($this->staff_id, (int) get_option('hr_doc_expiry_alert_days'));
        foreach ($expiring as $doc) {
            $alerts[] = ['level' => 'warning', 'icon' => 'fa-exclamation-triangle',
                'text' => 'Document "' . $doc['title'] . '" expires on ' . _d($doc['expiry_date']) . '.'];
        }

        // Probation ending soon
        if (!empty($employee['probation_end']) && $employee['probation_end'] >= $today
            && $employee['probation_end'] <= date('Y-m-d', strtotime('+30 days'))) {
            $alerts[] = ['level' => 'info', 'icon' => 'fa-graduation-cap',
                'text' => 'Your probation ends on ' . _d($employee['probation_end']) . '.'];
        }

        // Upcoming holidays (next 30 days)
        foreach ($this->hr_model->get_holidays((int) date('Y'), true) as $h) {
            if ($h['holiday_date'] >= $today && $h['holiday_date'] <= date('Y-m-d', strtotime('+30 days'))) {
                $alerts[] = ['level' => 'success', 'icon' => 'fa-calendar',
                    'text' => 'Upcoming holiday: ' . $h['name'] . ' on ' . _d($h['holiday_date']) . '.'];
            }
        }

        // Upcoming trainings
        foreach ($this->hr_model->get_employee_trainings($this->staff_id) as $t) {
            if (!empty($t['start_date']) && $t['start_date'] >= $today
                && $t['start_date'] <= date('Y-m-d', strtotime('+30 days'))) {
                $alerts[] = ['level' => 'info', 'icon' => 'fa-book',
                    'text' => 'Training "' . $t['title'] . '" starts ' . _d($t['start_date']) . '.'];
            }
        }

        // Birthday this month
        if (!empty($employee['date_of_birth'])
            && (int) date('n', strtotime($employee['date_of_birth'])) === (int) date('n')) {
            $alerts[] = ['level' => 'success', 'icon' => 'fa-birthday-cake',
                'text' => 'Your birthday is this month — ' . date('d M', strtotime($employee['date_of_birth'])) . '. 🎉'];
        }

        return $alerts;
    }
}
