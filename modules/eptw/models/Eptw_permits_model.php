<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * ePTW — the permit itself: register queries, the form, the approval matrix,
 * numbering, the status workflow, extensions, documents, gas tests, SIMOPS
 * detection, notifications and the audit trail.
 *
 * Every state change goes through transition(), so the register, the event
 * log and the notifications can never drift apart.
 */
class Eptw_permits_model extends App_Model
{
    /* ═══════════════════════════ Reading ════════════════════════════ */

    private function base_select()
    {
        $p = db_prefix();

        return $this->db->select("x.*, pr.name AS project_name, pr.code AS project_code, pr.camera_mode AS project_camera_mode,
                a.name AS area_name, a.code AS area_code,
                t.name AS type_name, t.code AS type_code, t.icon AS type_icon, t.color AS type_color, t.high_risk AS type_high_risk,
                c.name AS contractor_name,
                CONCAT(e.firstname, ' ', e.lastname) AS engineer_name,
                CONCAT(aa.firstname, ' ', aa.lastname) AS area_authority_name,
                CONCAT(h.firstname, ' ', h.lastname) AS hse_officer_name,
                CONCAT(co.firstname, ' ', co.lastname) AS coordinator_name,
                (SELECT COUNT(*) FROM {$p}eptw_documents d WHERE d.permit_id = x.id) AS doc_count,
                (SELECT COUNT(*) FROM {$p}eptw_extensions ex WHERE ex.permit_id = x.id AND ex.status = 'pending') AS pending_extensions,
                (SELECT COUNT(*) FROM {$p}eptw_gas_tests g WHERE g.permit_id = x.id) AS gas_test_count")
            ->from($p . 'eptw_permits x')
            ->join($p . 'eptw_projects pr', 'pr.id = x.project_id', 'left')
            ->join($p . 'eptw_areas a', 'a.id = x.area_id', 'left')
            ->join($p . 'eptw_permit_types t', 't.id = x.permit_type_id', 'left')
            ->join($p . 'eptw_contractors c', 'c.id = x.contractor_id', 'left')
            ->join($p . 'staff e', 'e.staffid = x.engineer_id', 'left')
            ->join($p . 'staff aa', 'aa.staffid = x.area_authority_id', 'left')
            ->join($p . 'staff h', 'h.staffid = x.hse_officer_id', 'left')
            ->join($p . 'staff co', 'co.staffid = x.coordinator_id', 'left');
    }

    public function get($id)
    {
        $row = $this->base_select()->where('x.id', (int) $id)->get()->row();

        return $row ? $this->hydrate($row) : null;
    }

    public function get_by_no($permit_no)
    {
        $row = $this->base_select()->where('x.permit_no', (string) $permit_no)->get()->row();

        return $row ? $this->hydrate($row) : null;
    }

    private function hydrate($row)
    {
        foreach (['hazards', 'controls', 'extra', 'closure', 'toolbox'] as $json) {
            $decoded   = json_decode((string) $row->$json, true);
            $row->$json = is_array($decoded) ? $decoded : [];
        }
        foreach (['ppe', 'extra_hazards'] as $json) {
            $decoded   = json_decode((string) $row->$json, true);
            $row->$json = is_array($decoded) ? $decoded : [];
        }
        $row->is_expired     = in_array($row->status, eptw_working_statuses(), true) && $row->end_at && strtotime($row->end_at) < time();
        $row->expiring_soon  = in_array($row->status, eptw_working_statuses(), true) && $row->end_at
            && !$row->is_expired && (strtotime($row->end_at) - time()) <= (int) eptw_opt('eptw_expiring_hours') * 3600;
        $row->auto_flags     = $this->auto_flags($row);

        return $row;
    }

    /** Things a coordinator would want pointed out without asking. */
    private function auto_flags($row)
    {
        $flags = [];
        if ($row->is_expired) {
            $flags[] = ['bad', 'Expired — still marked active'];
        } elseif ($row->expiring_soon) {
            $flags[] = ['warn', 'Expires ' . eptw_time_until($row->end_at)];
        }
        if ($row->simops_flag) {
            $flags[] = ['bad', 'SIMOPS conflict'];
        }
        if ($row->gas_test_required && in_array($row->status, eptw_live_statuses(), true) && (int) $row->gas_test_count === 0) {
            $flags[] = ['warn', 'Gas test required — none recorded'];
        }
        if ((int) $row->pending_extensions > 0) {
            $flags[] = ['info', 'Extension awaiting decision'];
        }
        if ($row->status === 'closed_docs_pending') {
            $flags[] = ['warn', 'Closure documents pending'];
        }
        if ((int) $row->extension_count >= (int) eptw_opt('eptw_max_extensions') && (int) eptw_opt('eptw_max_extensions') > 0) {
            $flags[] = ['warn', 'Extension limit reached'];
        }

        return $flags;
    }

    /**
     * Visibility: managers/coordinators/HSE/AA see their projects; an engineer
     * sees the permits they raised or are named on.
     */
    public function can_view($permit)
    {
        if (!$permit || !eptw_can_access()) {
            return false;
        }
        if (!eptw_in_scope($permit->project_id)) {
            return false;
        }
        if (eptw_can('view_all')) {
            return true;
        }
        $me = eptw_me()['staff_id'];

        return in_array($me, [(int) $permit->engineer_id, (int) $permit->created_by, (int) $permit->area_authority_id, (int) $permit->hse_officer_id, (int) $permit->coordinator_id], true);
    }

    public function can_edit($permit)
    {
        if (!$this->can_view($permit) || !in_array($permit->status, ['draft', 'returned'], true)) {
            return false;
        }
        $role = eptw_role();
        if (in_array($role, ['admin', 'coordinator'], true)) {
            return true;
        }
        $me = eptw_me()['staff_id'];

        return eptw_can('edit') && in_array($me, [(int) $permit->engineer_id, (int) $permit->created_by], true);
    }

    /**
     * Register query.
     *
     * @param array $f filters: q, view, status, project, area, type, contractor, engineer, from, to, risk
     */
    public function register(array $f, $limit = 100, $offset = 0, &$total = null)
    {
        $p = db_prefix();
        $this->apply_filters($f);
        $total = (int) $this->db->count_all_results($p . 'eptw_permits x', false);
        $this->db->reset_query();

        $this->base_select();
        $this->apply_filters($f);
        $this->db->order_by('x.created_at', 'desc')->limit((int) $limit, (int) $offset);

        $rows = $this->db->get()->result();
        foreach ($rows as $row) {
            $this->hydrate($row);
        }

        return $rows;
    }

    private function apply_filters(array $f)
    {
        $now   = date('Y-m-d H:i:s');
        $today = date('Y-m-d');
        $scope = eptw_scope();
        $me    = eptw_me()['staff_id'];

        if ($scope !== null) {
            $this->db->where_in('x.project_id', count($scope) ? $scope : [0]);
        }
        if (!eptw_can('view_all')) {
            $this->db->group_start()
                ->where('x.engineer_id', $me)->or_where('x.created_by', $me)
                ->or_where('x.area_authority_id', $me)->or_where('x.hse_officer_id', $me)->or_where('x.coordinator_id', $me)
                ->group_end();
        }

        $q = trim((string) ($f['q'] ?? ''));
        if ($q !== '') {
            $this->db->group_start()
                ->like('x.permit_no', $q)->or_like('x.work_title', $q)->or_like('x.work_description', $q)
                ->or_like('x.location', $q)->or_like('x.equipment_tag', $q)->or_like('x.work_order', $q)->or_like('x.permit_holder', $q)
                ->group_end();
        }
        foreach (['project' => 'x.project_id', 'area' => 'x.area_id', 'type' => 'x.permit_type_id', 'contractor' => 'x.contractor_id', 'engineer' => 'x.engineer_id'] as $key => $col) {
            if (!empty($f[$key])) {
                $this->db->where($col, (int) $f[$key]);
            }
        }
        if (!empty($f['status'])) {
            $this->db->where('x.status', (string) $f['status']);
        }
        if (!empty($f['risk'])) {
            $this->db->where('x.risk_level', (string) $f['risk']);
        }
        if (!empty($f['from'])) {
            $this->db->where('x.start_at >=', date('Y-m-d 00:00:00', strtotime($f['from'])));
        }
        if (!empty($f['to'])) {
            $this->db->where('x.start_at <=', date('Y-m-d 23:59:59', strtotime($f['to'])));
        }

        switch ((string) ($f['view'] ?? '')) {
            case 'pending':
                $this->db->where_in('x.status', eptw_pending_statuses());
                break;
            case 'active':
                $this->db->where_in('x.status', eptw_working_statuses());
                break;
            case 'live':
                $this->db->where_in('x.status', eptw_live_statuses());
                break;
            case 'issued':
                $this->db->where('x.status', 'issued');
                break;
            case 'suspended':
                $this->db->where_in('x.status', ['suspended', 'on_hold', 'on_hold_simops']);
                break;
            case 'extensions':
                $this->db->where('(SELECT COUNT(*) FROM ' . db_prefix() . 'eptw_extensions ex WHERE ex.permit_id = x.id AND ex.status = \'pending\') >', 0, false);
                break;
            case 'expiring':
                $this->db->where_in('x.status', eptw_working_statuses())
                    ->where('x.end_at >=', $now)
                    ->where('x.end_at <=', date('Y-m-d H:i:s', time() + (int) eptw_opt('eptw_expiring_hours') * 3600));
                break;
            case 'expired':
                $this->db->where_in('x.status', eptw_working_statuses())->where('x.end_at <', $now);
                break;
            case 'closed':
                $this->db->where_in('x.status', ['closed', 'closed_docs_pending', 'archived']);
                break;
            case 'docs_pending':
                $this->db->where('x.status', 'closed_docs_pending');
                break;
            case 'high_risk':
                $this->db->where('x.high_risk', 1)->where_in('x.status', eptw_live_statuses());
                break;
            case 'simops':
                $this->db->where('x.simops_flag', 1)->where_in('x.status', eptw_live_statuses());
                break;
            case 'today':
                $this->db->group_start()
                    ->where('DATE(x.created_at)', $today)->or_where('DATE(x.issued_at)', $today)
                    ->or_group_start()->where('x.start_at <=', $today . ' 23:59:59')->where('x.end_at >=', $today . ' 00:00:00')->where_in('x.status', eptw_live_statuses())->group_end()
                    ->group_end();
                break;
            case 'mine':
                $this->db->group_start()->where('x.engineer_id', $me)->or_where('x.created_by', $me)->group_end();
                break;
            case 'drafts':
                $this->db->where_in('x.status', ['draft', 'returned']);
                break;
        }
    }

    /** Permits waiting on the current user's signature. */
    public function my_queue()
    {
        $role = eptw_role();
        $step = ['area_authority' => 'area_authority', 'hse' => 'hse', 'manager' => 'manager'][$role] ?? '';
        $p    = db_prefix();

        $this->base_select()->where_in('x.status', eptw_pending_statuses());
        if ($step !== '') {
            $this->db->where("EXISTS (SELECT 1 FROM {$p}eptw_approvals ap WHERE ap.permit_id = x.id AND ap.step = " . $this->db->escape($step) . " AND ap.decision = 'pending')", null, false);
        } elseif (in_array($role, ['coordinator', 'admin'], true)) {
            // Anything pending is the coordinator's business.
        } else {
            return [];
        }
        $scope = eptw_scope();
        if ($scope !== null) {
            $this->db->where_in('x.project_id', count($scope) ? $scope : [0]);
        }
        $rows = $this->db->order_by('x.number_requested_at', 'asc')->limit(50)->get()->result();
        foreach ($rows as $row) {
            $this->hydrate($row);
        }

        return $rows;
    }

    /* ═══════════════════════════ Saving the form ════════════════════ */

    /**
     * Create or update from the permit form.
     *
     * @return int|string permit id, or an error message
     */
    public function save(array $in, $id = 0)
    {
        $id   = (int) $id;
        $type = eptw_permit_type((int) ($in['permit_type_id'] ?? 0));
        if (!$type) {
            return 'Choose a permit type.';
        }
        $project_id = (int) ($in['project_id'] ?? 0);
        if (!$project_id || !eptw_in_scope($project_id)) {
            return 'Choose a project.';
        }
        $title = trim((string) ($in['work_title'] ?? ''));
        $desc  = trim((string) ($in['work_description'] ?? ''));
        if ($title === '' && $desc === '') {
            return 'Describe the work.';
        }
        if ($title === '') {
            $title = mb_substr($desc, 0, 120);
        }

        $start = $this->parse_dt($in['start_at'] ?? '');
        $end   = $this->parse_dt($in['end_at'] ?? '');
        if (!$start) {
            return 'Set the planned start date and time.';
        }
        if (!$end) {
            $end = date('Y-m-d H:i:s', strtotime($start) + (int) $type->default_validity_hours * 3600);
        }
        if (strtotime($end) <= strtotime($start)) {
            return 'The end time must be after the start time.';
        }

        // Hazards: name => yes|no
        $hazards = [];
        foreach ((array) ($type->hazards ?? []) as $hazard) {
            $key            = $this->hkey($hazard);
            $hazards[$hazard] = (($in['hazard'][$key] ?? '') === 'yes') ? 'yes' : 'no';
        }
        $extra_hazards = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n|,/', (string) ($in['extra_hazards'] ?? '')))));

        // Controls: item => {v, r}
        $controls = [];
        foreach ((array) ($type->controls ?? []) as $section) {
            foreach ((array) ($section['items'] ?? []) as $item) {
                $key = $this->hkey($item);
                $v   = (string) ($in['control'][$key] ?? '');
                $controls[$item] = [
                    'v' => in_array($v, ['yes', 'no', 'na'], true) ? $v : '',
                    'r' => substr(trim((string) ($in['control_remark'][$key] ?? '')), 0, 255),
                ];
            }
        }

        // Type-specific fields
        $extra = [];
        foreach ((array) ($type->extra_fields ?? []) as $field) {
            $key = $field['key'];
            $val = $in['extra'][$key] ?? '';
            if ($field['type'] === 'checkboxes') {
                $extra[$key] = array_values(array_intersect((array) $val, (array) ($field['options'] ?? [])));
            } elseif ($field['type'] === 'detect') {
                $extra[$key] = [];
                foreach ((array) ($field['options'] ?? []) as $opt) {
                    $extra[$key][$opt] = (($val[$this->hkey($opt)] ?? '') === 'detected') ? 'detected' : 'not_detected';
                }
            } elseif ($field['type'] === 'yesno') {
                $extra[$key] = $val === 'yes' ? 'yes' : ($val === 'no' ? 'no' : '');
            } else {
                $extra[$key] = is_array($val) ? '' : substr(trim((string) $val), 0, 2000);
            }
        }

        $ppe   = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n|,/', (string) ($in['ppe'] ?? '')))));
        $shift = isset(eptw_shifts()[$in['shift'] ?? '']) ? $in['shift'] : 'day';
        $yes   = array_keys(array_filter($hazards, function ($v) { return $v === 'yes'; }));
        $risk  = eptw_risk_level($type, array_merge($yes, $extra_hazards), $shift, (int) ($in['workers_count'] ?? 0));

        $data = [
            'project_id'           => $project_id,
            'area_id'              => (int) ($in['area_id'] ?? 0),
            'permit_type_id'       => (int) $type->id,
            'contractor_id'        => (int) ($in['contractor_id'] ?? 0),
            'subcontractor'        => substr(trim((string) ($in['subcontractor'] ?? '')), 0, 150),
            'work_order'           => substr(trim((string) ($in['work_order'] ?? '')), 0, 60),
            'work_title'           => substr($title, 0, 191),
            'work_description'     => $desc,
            'location'             => substr(trim((string) ($in['location'] ?? '')), 0, 191),
            'equipment_tag'        => substr(trim((string) ($in['equipment_tag'] ?? '')), 0, 120),
            'shift'                => $shift,
            'workers_count'        => max(0, (int) ($in['workers_count'] ?? 0)),
            'start_at'             => $start,
            'end_at'               => $end,
            'permit_holder'        => substr(trim((string) ($in['permit_holder'] ?? '')), 0, 150),
            'supervisor'           => substr(trim((string) ($in['supervisor'] ?? '')), 0, 150),
            'contact_no'           => substr(trim((string) ($in['contact_no'] ?? '')), 0, 40),
            'area_authority_id'    => (int) ($in['area_authority_id'] ?? 0),
            'hse_officer_id'       => (int) ($in['hse_officer_id'] ?? 0),
            'risk_level'           => $risk,
            'high_risk'            => ($risk === 'high' || !empty($type->high_risk)) ? 1 : 0,
            'hazards'              => json_encode($hazards),
            'extra_hazards'        => json_encode($extra_hazards),
            'controls'             => json_encode($controls),
            'extra'                => json_encode($extra),
            'ppe'                  => json_encode($ppe),
            'ra_ref'               => substr(trim((string) ($in['ra_ref'] ?? '')), 0, 80),
            'isolation_required'   => !empty($in['isolation_required']) ? 1 : 0,
            'isolation_type'       => substr(trim((string) ($in['isolation_type'] ?? '')), 0, 80),
            'isolation_cert_no'    => substr(trim((string) ($in['isolation_cert_no'] ?? '')), 0, 80),
            'loto_applied'         => !empty($in['loto_applied']) ? 1 : 0,
            'zero_energy_verified' => !empty($in['zero_energy_verified']) ? 1 : 0,
            'isolation_authority'  => substr(trim((string) ($in['isolation_authority'] ?? '')), 0, 150),
            'lock_tag_numbers'     => substr(trim((string) ($in['lock_tag_numbers'] ?? '')), 0, 255),
            'gas_test_required'    => (!empty($in['gas_test_required']) || !empty($type->gas_test_required)) ? 1 : 0,
            'weather'              => substr(trim((string) ($in['weather'] ?? '')), 0, 80),
            'remarks'              => trim((string) ($in['remarks'] ?? '')),
            'updated_at'           => date('Y-m-d H:i:s'),
        ];

        if ($id) {
            $permit = $this->get($id);
            if (!$permit || !$this->can_edit($permit)) {
                return 'This permit can no longer be edited.';
            }
            // A returned permit keeps its history; only the coordinator may hand a draft to another engineer.
            if (in_array(eptw_role(), ['admin', 'coordinator'], true) && !empty($in['engineer_id'])) {
                $data['engineer_id'] = (int) $in['engineer_id'];
            }
            $this->db->where('id', $id)->update(db_prefix() . 'eptw_permits', $data);
            $this->event($id, 'edited', $permit->status, $permit->status, '');

            return $id;
        }

        $me                     = eptw_me()['staff_id'];
        $data['engineer_id']    = (in_array(eptw_role(), ['admin', 'coordinator'], true) && !empty($in['engineer_id'])) ? (int) $in['engineer_id'] : $me;
        $data['source']         = ($in['source'] ?? '') === 'paper' ? 'paper' : 'digital';
        $data['status']         = 'draft';
        $data['created_by']     = $me;
        $data['created_at']     = date('Y-m-d H:i:s');
        $data['original_end_at'] = $end;

        $this->db->insert(db_prefix() . 'eptw_permits', $data);
        $id = (int) $this->db->insert_id();
        $this->event($id, 'created', '', 'draft', $data['source'] === 'paper' ? 'Recorded from a paper permit' : '');

        return $id;
    }

    public function hkey($label)
    {
        return substr(md5(mb_strtolower(trim((string) $label))), 0, 10);
    }

    private function parse_dt($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $ts = strtotime(str_replace('T', ' ', $value));

        return $ts ? date('Y-m-d H:i:00', $ts) : null;
    }

    public function delete($id)
    {
        $permit = $this->get($id);
        if (!$permit) {
            return false;
        }
        foreach ($this->documents($id) as $doc) {
            @unlink(eptw_upload_dir($id) . $doc->file_name);
        }
        foreach (['eptw_events', 'eptw_approvals', 'eptw_gas_tests', 'eptw_documents', 'eptw_extensions', 'eptw_revalidations'] as $table) {
            $this->db->where('permit_id', (int) $id)->delete(db_prefix() . $table);
        }
        $this->db->where('id', (int) $id)->delete(db_prefix() . 'eptw_permits');

        return true;
    }

    /* ═══════════════════════════ Audit trail ════════════════════════ */

    public function event($permit_id, $event, $from, $to, $note = '', array $data = [])
    {
        $this->db->insert(db_prefix() . 'eptw_events', [
            'permit_id'   => (int) $permit_id,
            'staff_id'    => (int) eptw_me()['staff_id'],
            'event'       => substr((string) $event, 0, 30),
            'from_status' => (string) $from,
            'to_status'   => (string) $to,
            'note'        => (string) $note,
            'data'        => count($data) ? json_encode($data) : null,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    public function events($permit_id)
    {
        $rows = $this->db->select('ev.*, CONCAT(s.firstname, " ", s.lastname) AS staff_name')
            ->from(db_prefix() . 'eptw_events ev')
            ->join(db_prefix() . 'staff s', 's.staffid = ev.staff_id', 'left')
            ->where('ev.permit_id', (int) $permit_id)
            ->order_by('ev.id', 'desc')->get()->result();
        foreach ($rows as $row) {
            $decoded   = json_decode((string) $row->data, true);
            $row->data = is_array($decoded) ? $decoded : [];
        }

        return $rows;
    }

    public function event_label($event)
    {
        $map = [
            'created' => 'Permit created', 'edited' => 'Permit edited', 'number_requested' => 'Permit number requested',
            'review_started' => 'Review started', 'approved' => 'Approved', 'rejected' => 'Rejected', 'returned' => 'Returned for correction',
            'issued' => 'Permit number issued', 'activated' => 'Work started', 'extension_requested' => 'Extension requested',
            'extended' => 'Permit extended', 'extension_rejected' => 'Extension rejected', 'suspended' => 'Suspended', 'hold' => 'Put on hold',
            'simops_hold' => 'SIMOPS conflict — on hold', 'simops_cleared' => 'SIMOPS conflict cleared', 'resumed' => 'Resumed',
            'closed' => 'Closed', 'cancelled' => 'Cancelled', 'archived' => 'Archived', 'document' => 'Document uploaded',
            'document_removed' => 'Document removed', 'gas_test' => 'Gas test recorded', 'revalidated' => 'Shift revalidation',
            'remark' => 'Remark', 'expired' => 'Expired', 'auto_activated' => 'Work window opened', 'imported' => 'Imported from register',
            'toolbox' => 'Toolbox talk recorded',
        ];

        return $map[$event] ?? ucfirst(str_replace('_', ' ', (string) $event));
    }

    /* ═══════════════════════════ Transitions ════════════════════════ */

    private function set_status($permit, $status, $event, $note = '', array $extra = [], array $data = [])
    {
        $extra['status']     = $status;
        $extra['updated_at'] = date('Y-m-d H:i:s');
        $this->db->where('id', (int) $permit->id)->update(db_prefix() . 'eptw_permits', $extra);
        $this->event($permit->id, $event, $permit->status, $status, $note, $data);
    }

    /** Engineer → "please issue a permit number". Builds the approval matrix. */
    public function request_number($permit, $note = '')
    {
        if (!in_array($permit->status, ['draft', 'returned'], true)) {
            return 'Only a draft or a returned permit can be submitted.';
        }
        if (!$this->can_edit($permit)) {
            return 'You cannot submit this permit.';
        }
        $type = eptw_permit_type($permit->permit_type_id);
        $this->build_approvals($permit, $type);

        $this->set_status($permit, 'number_requested', 'number_requested', $note, ['number_requested_at' => date('Y-m-d H:i:s'), 'return_reason' => '']);
        $fresh = $this->get($permit->id);

        $this->notify_roles($fresh, ['coordinator', 'area_authority', 'hse'], 'eptw_not_requested', [$fresh->work_title, eptw_staff_name($fresh->engineer_id)],
            'Permit number requested: ' . $fresh->work_title,
            eptw_staff_name($fresh->engineer_id) . ' has submitted a ' . $fresh->type_name . ' for ' . $fresh->project_name . ' / ' . $fresh->area_name . ' and is waiting for a permit number.');

        return true;
    }

    private function build_approvals($permit, $type)
    {
        $this->db->where('permit_id', (int) $permit->id)->delete(db_prefix() . 'eptw_approvals');
        $steps = array_values(array_unique(array_merge(['initiator'], (array) ($type->approvals ?? ['area_authority', 'hse', 'coordinator']))));
        if (!in_array('coordinator', $steps, true)) {
            $steps[] = 'coordinator';
        }
        $order = 0;
        foreach ($steps as $step) {
            $order += 10;
            $row = ['permit_id' => (int) $permit->id, 'step' => $step, 'sort_order' => $order, 'decision' => 'pending'];
            if ($step === 'initiator') {
                $row['staff_id']   = (int) $permit->engineer_id;
                $row['name']       = eptw_staff_name($permit->engineer_id);
                $row['decision']   = 'approved';
                $row['decided_at'] = date('Y-m-d H:i:s');
            }
            $this->db->insert(db_prefix() . 'eptw_approvals', $row);
        }
    }

    public function approvals($permit_id)
    {
        $rows = $this->db->select('ap.*, CONCAT(s.firstname, " ", s.lastname) AS staff_name')
            ->from(db_prefix() . 'eptw_approvals ap')
            ->join(db_prefix() . 'staff s', 's.staffid = ap.staff_id', 'left')
            ->where('ap.permit_id', (int) $permit_id)->order_by('ap.sort_order', 'asc')->get()->result();

        return $rows;
    }

    public function step_label($step)
    {
        $map = ['initiator' => 'Initiator', 'area_authority' => 'Area Authority', 'hse' => 'HSE Officer', 'manager' => 'Manager', 'coordinator' => 'Permit Issuer (PTW Coordinator)'];

        return $map[$step] ?? ucfirst($step);
    }

    /** Whether the current user may sign a given step. */
    public function can_sign($permit, $step)
    {
        if (!in_array($permit->status, eptw_pending_statuses(), true)) {
            return false;
        }
        $role = eptw_role();
        if ($role === 'admin') {
            return $step !== 'initiator';
        }

        return ($step === 'area_authority' && $role === 'area_authority')
            || ($step === 'hse' && $role === 'hse')
            || ($step === 'manager' && $role === 'manager')
            || ($step === 'coordinator' && $role === 'coordinator' && false); // coordinator signs by issuing
    }

    /**
     * A reviewer signs (or rejects) their step.
     * $signature is the data-URL PNG from the signature pad, optional.
     */
    public function decide($permit, $step, $decision, $remarks = '', $signature = '')
    {
        if (!$this->can_sign($permit, $step)) {
            return 'You cannot sign this step.';
        }
        $row = $this->db->where('permit_id', (int) $permit->id)->where('step', $step)->get(db_prefix() . 'eptw_approvals')->row();
        if (!$row) {
            return 'That approval step does not exist on this permit.';
        }
        $decision = $decision === 'approved' ? 'approved' : 'rejected';
        // Resolved before the builder call: a lookup that runs its own query
        // inside update()'s argument list would consume the queued where().
        $me      = (int) eptw_me()['staff_id'];
        $me_name = eptw_staff_name($me);
        $this->db->where('id', $row->id)->update(db_prefix() . 'eptw_approvals', [
            'staff_id'   => $me,
            'name'       => $me_name,
            'decision'   => $decision,
            'remarks'    => $remarks,
            'signature'  => $this->clean_signature($signature),
            'decided_at' => date('Y-m-d H:i:s'),
        ]);

        if ($decision === 'rejected') {
            $this->set_status($permit, 'returned', 'returned', $this->step_label($step) . ': ' . $remarks, ['return_reason' => substr($this->step_label($step) . ': ' . $remarks, 0, 255)]);
            $this->notify_staff($permit, [(int) $permit->engineer_id], 'eptw_not_returned', [$permit->work_title], 'Permit returned for correction: ' . $permit->work_title, $this->step_label($step) . ' returned the permit: ' . $remarks);

            return true;
        }

        $this->event($permit->id, 'approved', $permit->status, 'under_review', $this->step_label($step) . ($remarks !== '' ? ': ' . $remarks : ''));
        if ($permit->status === 'number_requested') {
            $this->db->where('id', (int) $permit->id)->update(db_prefix() . 'eptw_permits', ['status' => 'under_review', 'updated_at' => date('Y-m-d H:i:s')]);
        }
        if ($this->reviews_complete($permit->id)) {
            $fresh = $this->get($permit->id);
            $this->notify_roles($fresh, ['coordinator'], 'eptw_not_ready_to_issue', [$fresh->work_title], 'Ready to issue: ' . $fresh->work_title, 'All reviews are signed. The permit is waiting for its permit number.');
        }

        return true;
    }

    /** Coordinator records a paper-signed approval (hybrid workflow). */
    public function record_paper_approval($permit, $step, $name, $remarks = '')
    {
        if (!eptw_can('issue')) {
            return 'Only the PTW Coordinator can record paper approvals.';
        }
        $row = $this->db->where('permit_id', (int) $permit->id)->where('step', $step)->get(db_prefix() . 'eptw_approvals')->row();
        if (!$row) {
            return 'That approval step does not exist on this permit.';
        }
        $recorded_by = eptw_staff_name(eptw_me()['staff_id']);
        $this->db->where('id', $row->id)->update(db_prefix() . 'eptw_approvals', [
            'name'       => substr(trim((string) $name), 0, 150),
            'decision'   => 'approved',
            'remarks'    => trim('Recorded from paper by ' . $recorded_by . '. ' . $remarks),
            'decided_at' => date('Y-m-d H:i:s'),
        ]);
        $this->event($permit->id, 'approved', $permit->status, $permit->status, $this->step_label($step) . ' signed on paper: ' . $name);

        return true;
    }

    public function reviews_complete($permit_id)
    {
        $pending = $this->db->where('permit_id', (int) $permit_id)->where('decision !=', 'approved')->where('step !=', 'coordinator')
            ->count_all_results(db_prefix() . 'eptw_approvals');

        return $pending === 0;
    }

    private function clean_signature($signature)
    {
        $signature = (string) $signature;
        if (strncmp($signature, 'data:image/png;base64,', 22) !== 0 || strlen($signature) > 400000) {
            return null;
        }

        return $signature;
    }

    /**
     * The PTW Coordinator issues the permit number. This is the control point
     * of the whole system: nothing is valid on site before this.
     */
    public function issue($permit, $override_note = '', $signature = '')
    {
        if (!eptw_can('issue')) {
            return 'Only the PTW Coordinator can issue a permit number.';
        }
        if (!in_array($permit->status, ['draft', 'returned', 'number_requested', 'under_review'], true)) {
            return 'This permit already has a number or is closed.';
        }
        if (!$permit->area_id) {
            return 'Set the area before issuing — it is part of the permit number.';
        }

        $type = eptw_permit_type($permit->permit_type_id);
        if (in_array($permit->status, ['draft', 'returned'], true)) {
            $this->build_approvals($permit, $type);   // direct issue: paper approvals recorded afterwards
        }
        if (!$this->reviews_complete($permit->id) && trim((string) $override_note) === '') {
            return 'Reviews are still pending. Either wait for the signatures or state why you are issuing anyway (e.g. "approved on paper").';
        }

        $number = $this->next_permit_no($permit);
        $now    = date('Y-m-d H:i:s');
        $me     = (int) eptw_me()['staff_id'];

        $me_name   = eptw_staff_name($me);
        $signature = $this->clean_signature($signature);
        $this->db->where('permit_id', (int) $permit->id)->where('step', 'coordinator')->update(db_prefix() . 'eptw_approvals', [
            'staff_id' => $me, 'name' => $me_name, 'decision' => 'approved', 'remarks' => $override_note, 'signature' => $signature, 'decided_at' => $now,
        ]);

        $conflicts = $this->simops_check($permit);
        $blocking  = array_filter($conflicts, function ($c) { return $c['severity'] === 'block'; });
        $status    = count($blocking) ? 'on_hold_simops' : 'issued';

        $this->set_status($permit, $status, 'issued', trim('Permit number ' . $number['no'] . '. ' . $override_note), [
            'permit_no'      => $number['no'],
            'serial'         => $number['serial'],
            'serial_key'     => $number['key'],
            'issued_at'      => $now,
            'issued_by'      => $me,
            'coordinator_id' => $me,
            'simops_flag'    => count($conflicts) ? 1 : 0,
            'simops_notes'   => count($conflicts) ? $this->simops_text($conflicts) : null,
        ], ['permit_no' => $number['no']]);

        if (count($blocking)) {
            $this->event($permit->id, 'simops_hold', 'issued', 'on_hold_simops', $this->simops_text($blocking));
        }

        $fresh = $this->get($permit->id);
        $this->notify_staff($fresh, [(int) $fresh->engineer_id, (int) $fresh->area_authority_id, (int) $fresh->hse_officer_id], 'eptw_not_issued', [$fresh->permit_no, $fresh->work_title],
            'Permit issued: ' . $fresh->permit_no, 'Permit ' . $fresh->permit_no . ' (' . $fresh->type_name . ') has been issued for ' . $fresh->project_name . ' / ' . $fresh->area_name . '. Work window: ' . eptw_dt($fresh->start_at) . ' → ' . eptw_dt($fresh->end_at) . '.');
        if (count($conflicts)) {
            $this->notify_roles($fresh, ['coordinator', 'hse', 'manager'], 'eptw_not_simops', [$fresh->permit_no], 'SIMOPS conflict: ' . $fresh->permit_no, $this->simops_text($conflicts));
        }

        return true;
    }

    /**
     * Next permit number for the configured format and reset scope.
     * Serialised with a row lock on the last serial in the same scope so two
     * coordinators issuing at once cannot mint the same number.
     */
    public function next_permit_no($permit)
    {
        $type    = eptw_permit_type($permit->permit_type_id);
        $project = $this->db->where('id', (int) $permit->project_id)->get(db_prefix() . 'eptw_projects')->row();
        $area    = $this->db->where('id', (int) $permit->area_id)->get(db_prefix() . 'eptw_areas')->row();
        $year    = date('Y', strtotime($permit->start_at ?: 'now'));

        $scope = eptw_opt('eptw_serial_scope');
        $parts = [];
        if (in_array($scope, ['project_year', 'project_type_year'], true)) {
            $parts[] = $project ? $project->code : 'P' . (int) $permit->project_id;
        }
        if (in_array($scope, ['type_year', 'project_type_year'], true)) {
            $parts[] = $type ? $type->code : 'T' . (int) $permit->permit_type_id;
        }
        if ($scope !== 'global') {
            $parts[] = $year;
        }
        $key = count($parts) ? implode('|', $parts) : '*';

        $this->db->trans_begin();
        $row    = $this->db->query('SELECT MAX(serial) AS s FROM ' . db_prefix() . 'eptw_permits WHERE serial_key = ? FOR UPDATE', [$key])->row();
        $serial = (int) ($row->s ?? 0) + 1;

        $format = eptw_opt('eptw_number_format');
        $pad    = (int) eptw_opt('eptw_serial_padding');
        $build  = function ($serial) use ($format, $pad, $project, $area, $type, $year) {
            return strtr($format, [
                '{PROJECT}' => $project ? $project->code : 'PRJ',
                '{AREA}'    => $area ? $area->code : 'AREA',
                '{TYPE}'    => $type ? $type->code : 'PTW',
                '{YEAR}'    => $year,
                '{YY}'      => substr($year, -2),
                '{MONTH}'   => date('m'),
                '{SERIAL}'  => str_pad((string) $serial, $pad, '0', STR_PAD_LEFT),
            ]);
        };
        $no = $build($serial);
        while ($this->db->where('permit_no', $no)->count_all_results(db_prefix() . 'eptw_permits')) {
            $serial++;
            $no = $build($serial);
        }
        $this->db->trans_commit();

        return ['no' => $no, 'serial' => $serial, 'key' => $key];
    }

    public function activate($permit, $note = '', $auto = false)
    {
        if (!$auto && !eptw_can('status') && !(eptw_role() === 'area_authority')) {
            return 'Only the PTW Coordinator or Area Authority can start work.';
        }
        if ($permit->status !== 'issued') {
            return 'Only an issued permit can be started.';
        }
        $this->set_status($permit, 'active', $auto ? 'auto_activated' : 'activated', $note, ['activated_at' => date('Y-m-d H:i:s')]);

        return true;
    }

    public function suspend($permit, $reason, $note = '')
    {
        if (!eptw_can('status') && !in_array(eptw_role(), ['hse', 'area_authority'], true)) {
            return 'You cannot suspend permits.';
        }
        if (!in_array($permit->status, ['issued', 'active', 'active_extended', 'on_hold'], true)) {
            return 'Only a live permit can be suspended.';
        }
        $reason = substr(trim((string) $reason), 0, 255);
        if ($reason === '') {
            return 'Give a reason for the suspension.';
        }
        $this->set_status($permit, 'suspended', 'suspended', trim($reason . '. ' . $note), ['suspended_at' => date('Y-m-d H:i:s'), 'suspend_reason' => $reason]);
        $this->notify_staff($permit, [(int) $permit->engineer_id, (int) $permit->area_authority_id, (int) $permit->hse_officer_id, (int) $permit->coordinator_id], 'eptw_not_suspended', [$permit->permit_no, $reason],
            'Permit suspended: ' . $permit->permit_no, 'Permit ' . $permit->permit_no . ' was suspended. Reason: ' . $reason . ($note !== '' ? ' — ' . $note : '') . '. Work must stop until it is resumed.');

        return true;
    }

    public function hold($permit, $note = '')
    {
        if (!eptw_can('status')) {
            return 'Only the PTW Coordinator can put a permit on hold.';
        }
        if (!in_array($permit->status, ['issued', 'active', 'active_extended'], true)) {
            return 'Only a live permit can be put on hold.';
        }
        $this->set_status($permit, 'on_hold', 'hold', $note);

        return true;
    }

    public function resume($permit, $note = '')
    {
        if (!eptw_can('status')) {
            return 'Only the PTW Coordinator can resume a permit.';
        }
        if (!in_array($permit->status, ['suspended', 'on_hold', 'on_hold_simops'], true)) {
            return 'This permit is not suspended or on hold.';
        }
        if ($permit->status === 'on_hold_simops') {
            if (trim((string) $note) === '') {
                return 'State how the SIMOPS conflict was resolved (this is the SIMOPS approval).';
            }
            $target = $permit->activated_at ? ((int) $permit->extension_count > 0 ? 'active_extended' : 'active') : 'issued';
            $this->set_status($permit, $target, 'simops_cleared', $note, ['simops_approved_by' => (int) eptw_me()['staff_id']]);

            return true;
        }
        $target = $permit->activated_at ? ((int) $permit->extension_count > 0 ? 'active_extended' : 'active') : 'issued';
        $this->set_status($permit, $target, 'resumed', $note, ['suspend_reason' => '']);
        $this->notify_staff($permit, [(int) $permit->engineer_id, (int) $permit->area_authority_id, (int) $permit->hse_officer_id], 'eptw_not_resumed', [$permit->permit_no], 'Permit resumed: ' . $permit->permit_no, 'Work may continue under permit ' . $permit->permit_no . '. ' . $note);

        return true;
    }

    /* ── Extensions ── */

    public function request_extension($permit, $new_end, $reason)
    {
        if (!eptw_can('extend_request') || !$this->can_view($permit)) {
            return 'You cannot request an extension on this permit.';
        }
        if (!in_array($permit->status, ['issued', 'active', 'active_extended'], true)) {
            return 'Only a live permit can be extended.';
        }
        $max = (int) eptw_opt('eptw_max_extensions');
        if ($max > 0 && (int) $permit->extension_count >= $max) {
            return 'This permit has reached the maximum of ' . $max . ' extensions. Raise a new permit instead.';
        }
        $new_end = $this->parse_dt($new_end);
        if (!$new_end || strtotime($new_end) <= strtotime($permit->end_at)) {
            return 'The new end time must be later than the current end time (' . eptw_dt($permit->end_at) . ').';
        }
        if ((int) $permit->pending_extensions > 0) {
            return 'An extension request is already waiting for a decision.';
        }
        $reason = trim((string) $reason);
        if ($reason === '') {
            return 'Give the reason for the extension.';
        }
        $this->db->insert(db_prefix() . 'eptw_extensions', [
            'permit_id' => (int) $permit->id, 'requested_by' => (int) eptw_me()['staff_id'], 'reason' => $reason,
            'old_end_at' => $permit->end_at, 'new_end_at' => $new_end, 'status' => 'pending', 'created_at' => date('Y-m-d H:i:s'),
        ]);
        $ext_id = (int) $this->db->insert_id();
        $this->event($permit->id, 'extension_requested', $permit->status, $permit->status, 'Until ' . eptw_dt($new_end) . ' — ' . $reason);

        // The coordinator's own request is approved on the spot: they are the deciding authority.
        if (eptw_can('extend_approve')) {
            return $this->decide_extension($permit, $ext_id, 'approved', 'Extended by the PTW Coordinator');
        }

        $this->notify_roles($permit, ['coordinator'], 'eptw_not_extension_requested', [$permit->permit_no], 'Extension requested: ' . $permit->permit_no,
            eptw_staff_name(eptw_me()['staff_id']) . ' asks to extend ' . $permit->permit_no . ' until ' . eptw_dt($new_end) . '. Reason: ' . $reason);

        return true;
    }

    public function extensions($permit_id)
    {
        return $this->db->select('ex.*, CONCAT(r.firstname, " ", r.lastname) AS requested_name, CONCAT(d.firstname, " ", d.lastname) AS decided_name')
            ->from(db_prefix() . 'eptw_extensions ex')
            ->join(db_prefix() . 'staff r', 'r.staffid = ex.requested_by', 'left')
            ->join(db_prefix() . 'staff d', 'd.staffid = ex.decided_by', 'left')
            ->where('ex.permit_id', (int) $permit_id)->order_by('ex.id', 'desc')->get()->result();
    }

    public function decide_extension($permit, $extension_id, $decision, $note = '')
    {
        if (!eptw_can('extend_approve')) {
            return 'Only the PTW Coordinator can decide extensions.';
        }
        $ext = $this->db->where('id', (int) $extension_id)->where('permit_id', (int) $permit->id)->get(db_prefix() . 'eptw_extensions')->row();
        if (!$ext || $ext->status !== 'pending') {
            return 'That extension request has already been decided.';
        }
        $decision = $decision === 'approved' ? 'approved' : 'rejected';
        $this->db->where('id', $ext->id)->update(db_prefix() . 'eptw_extensions', [
            'status' => $decision, 'decided_by' => (int) eptw_me()['staff_id'], 'decided_at' => date('Y-m-d H:i:s'), 'decision_note' => substr((string) $note, 0, 255),
        ]);

        if ($decision === 'rejected') {
            $this->event($permit->id, 'extension_rejected', $permit->status, $permit->status, $note);
            $this->notify_staff($permit, [(int) $ext->requested_by], 'eptw_not_extension_rejected', [$permit->permit_no], 'Extension rejected: ' . $permit->permit_no, 'The extension of ' . $permit->permit_no . ' was rejected. ' . $note);

            return true;
        }

        $status = in_array($permit->status, ['active', 'active_extended'], true) ? 'active_extended' : $permit->status;
        $this->set_status($permit, $status, 'extended', 'Until ' . eptw_dt($ext->new_end_at) . ' — ' . $ext->reason . ($note !== '' ? ' (' . $note . ')' : ''), [
            'end_at' => $ext->new_end_at, 'extension_count' => (int) $permit->extension_count + 1, 'expiry_notified' => 0,
        ]);
        $this->notify_staff($permit, [(int) $ext->requested_by, (int) $permit->engineer_id, (int) $permit->area_authority_id, (int) $permit->hse_officer_id], 'eptw_not_extended', [$permit->permit_no, eptw_dt($ext->new_end_at)],
            'Permit extended: ' . $permit->permit_no, 'Permit ' . $permit->permit_no . ' now runs until ' . eptw_dt($ext->new_end_at) . '.');

        return true;
    }

    /* ── Closure ── */

    public function close($permit, array $closure, $note = '')
    {
        if (!eptw_can('status')) {
            return 'Only the PTW Coordinator can close a permit.';
        }
        if (!in_array($permit->status, eptw_live_statuses(), true)) {
            return 'This permit is not open.';
        }
        $checks = [];
        foreach (['work_completed', 'area_clean', 'no_residual_hazards', 'isolation_removed', 'area_restored', 'inspection_done'] as $key) {
            $checks[$key] = !empty($closure[$key]) ? 1 : 0;
        }
        $checks['final_remarks'] = substr(trim((string) ($closure['final_remarks'] ?? '')), 0, 1000);
        $checks['closed_by_name'] = eptw_staff_name(eptw_me()['staff_id']);

        if (!$checks['work_completed'] && !$checks['area_clean'] && trim((string) $note) === '' && $checks['final_remarks'] === '') {
            return 'Confirm the closure checks or explain why the permit is being closed incomplete.';
        }

        $docs_ok = $this->docs_complete($permit->id);
        $status  = $docs_ok ? 'closed' : 'closed_docs_pending';
        $this->set_status($permit, $status, 'closed', trim($note . ' ' . $checks['final_remarks']), [
            'closed_at' => date('Y-m-d H:i:s'), 'closed_by' => (int) eptw_me()['staff_id'], 'closure' => json_encode($checks), 'docs_complete' => $docs_ok ? 1 : 0, 'simops_flag' => 0,
        ]);
        $this->notify_staff($permit, [(int) $permit->engineer_id, (int) $permit->area_authority_id, (int) $permit->hse_officer_id], 'eptw_not_closed', [$permit->permit_no], 'Permit closed: ' . $permit->permit_no,
            'Permit ' . $permit->permit_no . ' is closed.' . ($docs_ok ? '' : ' Closure documents are still pending upload: ' . implode(', ', $this->missing_docs($permit->id)) . '.'));

        return true;
    }

    public function cancel($permit, $reason)
    {
        if (!eptw_can('status') && !$this->can_edit($permit)) {
            return 'You cannot cancel this permit.';
        }
        if (in_array($permit->status, eptw_closed_statuses(), true)) {
            return 'This permit is already closed.';
        }
        $reason = substr(trim((string) $reason), 0, 255);
        if ($reason === '') {
            return 'Give the reason for cancelling.';
        }
        $this->set_status($permit, 'cancelled', 'cancelled', $reason, ['cancel_reason' => $reason, 'simops_flag' => 0]);

        return true;
    }

    public function archive($permit)
    {
        if (!eptw_can('status')) {
            return 'Only the PTW Coordinator can archive.';
        }
        if (!in_array($permit->status, ['closed', 'cancelled'], true)) {
            return 'Only a closed permit with all documents can be archived.';
        }
        $this->set_status($permit, 'archived', 'archived', '');

        return true;
    }

    public function return_for_correction($permit, $reason)
    {
        if (!eptw_can('review') && !eptw_can('issue')) {
            return 'You cannot return permits.';
        }
        if (!in_array($permit->status, eptw_pending_statuses(), true)) {
            return 'Only a submitted permit can be returned.';
        }
        $reason = substr(trim((string) $reason), 0, 255);
        if ($reason === '') {
            return 'Tell the engineer what to correct.';
        }
        $this->set_status($permit, 'returned', 'returned', $reason, ['return_reason' => $reason]);
        $this->notify_staff($permit, [(int) $permit->engineer_id], 'eptw_not_returned', [$permit->work_title], 'Permit returned for correction: ' . $permit->work_title, $reason);

        return true;
    }

    public function add_remark($permit, $text)
    {
        $text = trim((string) $text);
        if ($text === '' || !eptw_can('remark') || !$this->can_view($permit)) {
            return 'Nothing to add.';
        }
        $this->event($permit->id, 'remark', $permit->status, $permit->status, $text);
        $targets = array_diff([(int) $permit->engineer_id, (int) $permit->coordinator_id, (int) $permit->hse_officer_id, (int) $permit->area_authority_id], [(int) eptw_me()['staff_id']]);
        $this->notify_staff($permit, $targets, 'eptw_not_remark', [$permit->permit_no ?: $permit->work_title, eptw_staff_name(eptw_me()['staff_id'])], null, null);

        return true;
    }

    /* ═══════════════════════════ SIMOPS ═════════════════════════════ */

    /**
     * Conflicting live permits: same project + area, overlapping window, and
     * a rule for the pair of permit types. Returns [] when SIMOPS is off.
     */
    public function simops_check($permit)
    {
        if (eptw_opt('eptw_simops_enabled') !== '1' || !$permit->area_id) {
            return [];
        }
        $type = eptw_permit_type($permit->permit_type_id);
        if (!$type) {
            return [];
        }
        $p     = db_prefix();
        $rules = $this->db->where('active', 1)->get($p . 'eptw_simops_rules')->result();
        $pairs = [];
        foreach ($rules as $rule) {
            if ($rule->type_a === $type->code) {
                $pairs[$rule->type_b] = $rule;
            } elseif ($rule->type_b === $type->code) {
                $pairs[$rule->type_a] = $rule;
            }
        }
        if (!count($pairs)) {
            return [];
        }

        $rows = $this->db->select('x.id, x.permit_no, x.work_title, x.status, x.start_at, x.end_at, t.code AS type_code, t.name AS type_name')
            ->from($p . 'eptw_permits x')->join($p . 'eptw_permit_types t', 't.id = x.permit_type_id', 'left')
            ->where('x.id !=', (int) $permit->id)
            ->where('x.project_id', (int) $permit->project_id)->where('x.area_id', (int) $permit->area_id)
            ->where_in('x.status', eptw_live_statuses())
            ->where('x.start_at <', $permit->end_at)->where('x.end_at >', $permit->start_at)
            ->where_in('t.code', array_keys($pairs))
            ->get()->result();

        $out = [];
        foreach ($rows as $row) {
            $rule  = $pairs[$row->type_code];
            $out[] = [
                'permit_id' => (int) $row->id, 'permit_no' => $row->permit_no, 'work_title' => $row->work_title, 'type_name' => $row->type_name,
                'status' => $row->status, 'start_at' => $row->start_at, 'end_at' => $row->end_at,
                'severity' => $rule->severity, 'description' => $rule->description,
            ];
        }

        return $out;
    }

    private function simops_text(array $conflicts)
    {
        $lines = [];
        foreach ($conflicts as $c) {
            $lines[] = strtoupper($c['severity']) . ': ' . $c['description'] . ' — ' . ($c['permit_no'] ?: 'draft') . ' (' . $c['type_name'] . ', ' . eptw_dt($c['start_at']) . ' → ' . eptw_dt($c['end_at']) . ')';
        }

        return implode("\n", $lines);
    }

    /* ═══════════════════════════ Documents ══════════════════════════ */

    public function documents($permit_id)
    {
        return $this->db->select('d.*, CONCAT(s.firstname, " ", s.lastname) AS uploaded_name')
            ->from(db_prefix() . 'eptw_documents d')
            ->join(db_prefix() . 'staff s', 's.staffid = d.uploaded_by', 'left')
            ->where('d.permit_id', (int) $permit_id)->order_by('d.id', 'desc')->get()->result();
    }

    public function document($id)
    {
        return $this->db->where('id', (int) $id)->get(db_prefix() . 'eptw_documents')->row();
    }

    public function missing_docs($permit_id)
    {
        $have = [];
        foreach ($this->db->select('doc_type')->where('permit_id', (int) $permit_id)->get(db_prefix() . 'eptw_documents')->result() as $row) {
            $have[$row->doc_type] = true;
        }
        $labels  = eptw_document_types();
        $missing = [];
        foreach (eptw_required_doc_types() as $type) {
            if (!isset($have[$type])) {
                $missing[] = $labels[$type];
            }
        }

        return $missing;
    }

    public function docs_complete($permit_id)
    {
        return count($this->missing_docs($permit_id)) === 0;
    }

    /** Store one uploaded file ($_FILES entry) against the permit. */
    public function add_document($permit, array $file, $doc_type, $note = '')
    {
        if (!eptw_can('upload') || !$this->can_view($permit)) {
            return 'You cannot upload to this permit.';
        }
        if (empty($file['name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return 'Choose a file first.';
        }
        $max = (int) eptw_opt('eptw_max_upload_mb') * 1048576;
        if ((int) $file['size'] > $max) {
            return 'The file is larger than ' . eptw_opt('eptw_max_upload_mb') . ' MB.';
        }
        $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'csv'];
        if (!in_array($ext, $allowed, true)) {
            return 'File type ".' . $ext . '" is not allowed. Upload PDF, images or Office documents.';
        }
        if (!isset(eptw_document_types()[$doc_type])) {
            $doc_type = 'other';
        }
        $dir  = eptw_upload_dir($permit->id);
        $name = date('Ymd-His') . '-' . bin2hex(random_bytes(6)) . '.' . $ext;
        if (!@move_uploaded_file($file['tmp_name'], $dir . $name)) {
            return 'The file could not be stored on the server.';
        }
        $this->db->insert(db_prefix() . 'eptw_documents', [
            'permit_id'     => (int) $permit->id,
            'doc_type'      => $doc_type,
            'file_name'     => $name,
            'original_name' => substr((string) $file['name'], 0, 191),
            'mime'          => substr((string) ($file['type'] ?? ''), 0, 100),
            'size'          => (int) $file['size'],
            'note'          => substr(trim((string) $note), 0, 255),
            'uploaded_by'   => (int) eptw_me()['staff_id'],
            'created_at'    => date('Y-m-d H:i:s'),
        ]);
        $this->event($permit->id, 'document', $permit->status, $permit->status, eptw_document_types()[$doc_type] . ': ' . $file['name']);

        // Closing the documents gap flips the permit to fully closed by itself.
        if ($permit->status === 'closed_docs_pending' && $this->docs_complete($permit->id)) {
            $this->db->where('id', (int) $permit->id)->update(db_prefix() . 'eptw_permits', ['status' => 'closed', 'docs_complete' => 1, 'updated_at' => date('Y-m-d H:i:s')]);
            $this->event($permit->id, 'closed', 'closed_docs_pending', 'closed', 'All closure documents uploaded');
        } elseif ($this->docs_complete($permit->id)) {
            $this->db->where('id', (int) $permit->id)->update(db_prefix() . 'eptw_permits', ['docs_complete' => 1]);
        }

        return true;
    }

    public function delete_document($permit, $doc_id)
    {
        $doc = $this->document($doc_id);
        if (!$doc || (int) $doc->permit_id !== (int) $permit->id) {
            return 'Document not found.';
        }
        $me = (int) eptw_me()['staff_id'];
        if (!eptw_can('status') && (int) $doc->uploaded_by !== $me) {
            return 'Only the uploader or the PTW Coordinator can remove a document.';
        }
        @unlink(eptw_upload_dir($permit->id) . $doc->file_name);
        $this->db->where('id', (int) $doc->id)->delete(db_prefix() . 'eptw_documents');
        $this->event($permit->id, 'document_removed', $permit->status, $permit->status, $doc->original_name);
        if (!$this->docs_complete($permit->id)) {
            $update = ['docs_complete' => 0];
            if ($permit->status === 'closed') {
                $update['status'] = 'closed_docs_pending';
            }
            $this->db->where('id', (int) $permit->id)->update(db_prefix() . 'eptw_permits', $update);
        }

        return true;
    }

    /* ═══════════════════════════ Gas tests ══════════════════════════ */

    public function gas_tests($permit_id)
    {
        return $this->db->where('permit_id', (int) $permit_id)->order_by('tested_at', 'desc')->get(db_prefix() . 'eptw_gas_tests')->result();
    }

    public function add_gas_test($permit, array $in)
    {
        if (!eptw_can('gas_test') || !$this->can_view($permit)) {
            return 'You cannot record gas tests on this permit.';
        }
        $num = function ($v) {
            $v = trim((string) $v);

            return $v === '' ? null : (float) $v;
        };
        $o2  = $num($in['o2'] ?? '');
        $lel = $num($in['lel'] ?? '');
        $h2s = $num($in['h2s'] ?? '');
        $co  = $num($in['co'] ?? '');

        // GCC-typical acceptance: O2 19.5–23.5 %, LEL < 10 %, H2S < 10 ppm, CO < 25 ppm.
        $unsafe = ($o2 !== null && ($o2 < 19.5 || $o2 > 23.5)) || ($lel !== null && $lel >= 10) || ($h2s !== null && $h2s >= 10) || ($co !== null && $co >= 25);

        $tester = trim((string) ($in['tester'] ?? ''));
        if ($tester === '') {
            $tester = eptw_staff_name(eptw_me()['staff_id']);
        }
        $this->db->insert(db_prefix() . 'eptw_gas_tests', [
            'permit_id'  => (int) $permit->id,
            'tested_at'  => $this->parse_dt($in['tested_at'] ?? '') ?: date('Y-m-d H:i:s'),
            'o2' => $o2, 'lel' => $lel, 'h2s' => $h2s, 'co' => $co, 'so2' => $num($in['so2'] ?? ''), 'nh3' => $num($in['nh3'] ?? ''),
            'tester'     => substr($tester, 0, 150),
            'result'     => $unsafe ? 'unsafe' : 'safe',
            'remarks'    => substr(trim((string) ($in['remarks'] ?? '')), 0, 255),
            'created_by' => (int) eptw_me()['staff_id'],
        ]);
        $this->event($permit->id, 'gas_test', $permit->status, $permit->status, ($unsafe ? 'UNSAFE — ' : 'Safe — ') . 'O2 ' . ($o2 ?? '–') . '%, LEL ' . ($lel ?? '–') . '%, H2S ' . ($h2s ?? '–') . ' ppm, CO ' . ($co ?? '–') . ' ppm');

        if ($unsafe) {
            $this->notify_staff($permit, [(int) $permit->coordinator_id, (int) $permit->hse_officer_id, (int) $permit->area_authority_id, (int) $permit->engineer_id], 'eptw_not_gas_unsafe', [$permit->permit_no ?: $permit->work_title],
                'UNSAFE gas test: ' . ($permit->permit_no ?: $permit->work_title), 'A gas test outside the acceptance limits was recorded. Consider suspending the permit.');
        }

        return $unsafe ? 'unsafe' : true;
    }

    public function delete_gas_test($permit, $id)
    {
        if (!eptw_can('status')) {
            return 'Only the PTW Coordinator can remove a gas test.';
        }
        $this->db->where('id', (int) $id)->where('permit_id', (int) $permit->id)->delete(db_prefix() . 'eptw_gas_tests');

        return true;
    }

    /* ═══════════════════════════ Revalidation / toolbox ═════════════ */

    public function revalidations($permit_id)
    {
        return $this->db->where('permit_id', (int) $permit_id)->order_by('id', 'desc')->get(db_prefix() . 'eptw_revalidations')->result();
    }

    public function add_revalidation($permit, array $in)
    {
        if (!eptw_can('status') && !in_array(eptw_role(), ['area_authority', 'hse'], true)) {
            return 'Only the Coordinator, Area Authority or HSE can revalidate a shift.';
        }
        if (!in_array($permit->status, eptw_live_statuses(), true)) {
            return 'Only a live permit can be revalidated.';
        }
        $this->db->insert(db_prefix() . 'eptw_revalidations', [
            'permit_id'      => (int) $permit->id,
            'shift'          => isset(eptw_shifts()[$in['shift'] ?? '']) ? $in['shift'] : 'day',
            'from_at'        => $this->parse_dt($in['from_at'] ?? ''),
            'to_at'          => $this->parse_dt($in['to_at'] ?? ''),
            'area_authority' => substr(trim((string) ($in['area_authority'] ?? '')), 0, 150),
            'issuer'         => substr(trim((string) ($in['issuer'] ?? '')), 0, 150),
            'hse'            => substr(trim((string) ($in['hse'] ?? '')), 0, 150),
            'gas_test_ok'    => !empty($in['gas_test_ok']) ? 1 : 0,
            'notes'          => substr(trim((string) ($in['notes'] ?? '')), 0, 255),
            'created_by'     => (int) eptw_me()['staff_id'],
            'created_at'     => date('Y-m-d H:i:s'),
        ]);
        $this->event($permit->id, 'revalidated', $permit->status, $permit->status, ucfirst($in['shift'] ?? 'day') . ' shift ' . eptw_dt($this->parse_dt($in['from_at'] ?? '')) . ' → ' . eptw_dt($this->parse_dt($in['to_at'] ?? '')));

        return true;
    }

    public function save_toolbox($permit, array $in)
    {
        if (!eptw_can('upload') || !$this->can_view($permit)) {
            return 'You cannot record the toolbox talk on this permit.';
        }
        $names = (array) ($in['worker_name'] ?? []);
        $ids   = (array) ($in['worker_id'] ?? []);
        $rows  = [];
        foreach ($names as $i => $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }
            $rows[] = ['name' => substr($name, 0, 100), 'id' => substr(trim((string) ($ids[$i] ?? '')), 0, 40)];
        }
        $talk = [
            'held_at'   => $this->parse_dt($in['held_at'] ?? '') ?: date('Y-m-d H:i:s'),
            'by'        => substr(trim((string) ($in['held_by'] ?? '')) !== '' ? trim((string) $in['held_by']) : eptw_staff_name(eptw_me()['staff_id']), 0, 150),
            'topics'    => array_values(array_intersect((array) ($in['topics'] ?? []), ['hazards', 'controls', 'ppe', 'emergency'])),
            'attendees' => $rows,
        ];
        $this->db->where('id', (int) $permit->id)->update(db_prefix() . 'eptw_permits', ['toolbox' => json_encode($talk), 'updated_at' => date('Y-m-d H:i:s')]);
        $this->event($permit->id, 'toolbox', $permit->status, $permit->status, count($rows) . ' attendee(s)');

        return true;
    }

    /* ═══════════════════════════ Notifications ══════════════════════ */

    private function notify_roles($permit, array $roles, $lang_key, array $args, $subject, $body)
    {
        $targets = [];
        foreach ($roles as $role) {
            foreach (array_keys(eptw_team_by_role($role, (int) $permit->project_id)) as $staff_id) {
                $targets[] = (int) $staff_id;
            }
        }
        $this->notify_staff($permit, $targets, $lang_key, $args, $subject, $body);
    }

    private function notify_staff($permit, array $staff_ids, $lang_key, array $args, $subject, $body)
    {
        $me        = (int) eptw_me()['staff_id'];
        $staff_ids = array_values(array_unique(array_filter(array_map('intval', $staff_ids))));
        $emails    = [];

        foreach ($staff_ids as $staff_id) {
            if ($staff_id === $me) {
                continue;
            }
            add_notification([
                'description'     => $lang_key,
                'touserid'        => $staff_id,
                'fromcompany'     => 1,
                'link'            => 'eptw/view/' . (int) $permit->id,
                'additional_data' => serialize($args),
            ]);
            if ($subject !== null) {
                $emails[] = $staff_id;
            }
        }

        if (!count($emails) || eptw_opt('eptw_email_notifications') !== '1') {
            return;
        }
        $rows = $this->db->select('email')->where_in('staffid', $emails)->where('active', 1)->get(db_prefix() . 'staff')->result();
        $link = admin_url('eptw/view/' . (int) $permit->id);
        $html = '<p>' . nl2br(html_escape($body)) . '</p><p><a href="' . $link . '">Open the permit</a></p>';
        try {
            $this->load->model('emails_model');
            foreach ($rows as $row) {
                if (!empty($row->email)) {
                    $this->emails_model->send_simple_email($row->email, '[ePTW] ' . $subject, $html);
                }
            }
        } catch (Throwable $e) {
            log_activity('ePTW email failed: ' . $e->getMessage());
        }
    }

    /* ═══════════════════════════ Cron ═══════════════════════════════ */

    public function cron_pass()
    {
        $now = date('Y-m-d H:i:s');
        $p   = db_prefix();

        // Work windows that opened: issued → active.
        if (eptw_opt('eptw_auto_activate') === '1') {
            foreach ($this->db->where('status', 'issued')->where('start_at <=', $now)->get($p . 'eptw_permits')->result() as $row) {
                $permit = $this->get($row->id);
                $this->activate($permit, 'Start time reached', true);
            }
        }

        // Expiring soon — once per permit (reset when extended).
        $horizon = date('Y-m-d H:i:s', time() + (int) eptw_opt('eptw_expiring_hours') * 3600);
        $rows    = $this->db->where_in('status', eptw_working_statuses())->where('expiry_notified', 0)
            ->where('end_at <=', $horizon)->get($p . 'eptw_permits')->result();
        foreach ($rows as $row) {
            $permit  = $this->get($row->id);
            $expired = strtotime($permit->end_at) < time();
            $this->db->where('id', $row->id)->update($p . 'eptw_permits', ['expiry_notified' => 1]);
            if ($expired) {
                $this->event($permit->id, 'expired', $permit->status, $permit->status, 'End time passed while active');
                $this->notify_staff($permit, [(int) $permit->engineer_id, (int) $permit->coordinator_id, (int) $permit->area_authority_id, (int) $permit->hse_officer_id], 'eptw_not_expired', [$permit->permit_no],
                    'Permit expired: ' . $permit->permit_no, 'Permit ' . $permit->permit_no . ' passed its end time (' . eptw_dt($permit->end_at) . ') and is still marked active. Extend it or close it.');
            } else {
                $this->notify_staff($permit, [(int) $permit->engineer_id, (int) $permit->coordinator_id, (int) $permit->area_authority_id], 'eptw_not_expiring', [$permit->permit_no, eptw_time_until($permit->end_at)],
                    'Permit expiring soon: ' . $permit->permit_no, 'Permit ' . $permit->permit_no . ' expires ' . eptw_time_until($permit->end_at) . ' (' . eptw_dt($permit->end_at) . '). Request an extension if work continues.');
            }
        }

        // Nudge about closure documents still missing after a day.
        $stale = $this->db->where('status', 'closed_docs_pending')->where('closed_at <', date('Y-m-d H:i:s', time() - 86400))
            ->where('DATE(updated_at) !=', date('Y-m-d'))->limit(30)->get($p . 'eptw_permits')->result();
        foreach ($stale as $row) {
            $permit = $this->get($row->id);
            $this->db->where('id', $row->id)->update($p . 'eptw_permits', ['updated_at' => $now]);
            $this->notify_staff($permit, [(int) $permit->engineer_id, (int) $permit->coordinator_id], 'eptw_not_docs_pending', [$permit->permit_no, implode(', ', $this->missing_docs($permit->id))], null, null);
        }
    }
}
