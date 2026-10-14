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
    /** Events written by the expiry sweep and the cron belong to the system, not to whoever loaded the page. */
    private $system_actor = false;

    /* ═══════════════════════════ Reading ════════════════════════════ */

    private function base_select()
    {
        $p   = db_prefix();
        $req = eptw_required_doc_types();
        $req_sql = count($req)
            ? "(SELECT COUNT(DISTINCT d2.doc_type) FROM {$p}eptw_documents d2 WHERE d2.permit_id = x.id AND d2.doc_type IN ("
                . implode(',', array_map([$this->db, 'escape'], $req)) . ')'
                . (eptw_opt('eptw_docs_review') === '1' ? " AND d2.review_status = 'verified'" : '') . ')'
            : '0';

        return $this->db->select("x.*, pr.name AS project_name, pr.code AS project_code, pr.camera_mode AS project_camera_mode,
                a.name AS area_name, a.code AS area_code, a.project_id AS area_project_id,
                t.name AS type_name, t.code AS type_code, t.icon AS type_icon, t.color AS type_color, t.high_risk AS type_high_risk,
                t.expiry_warning_hours AS type_expiry_hours, t.isolation_rule AS type_isolation_rule,
                c.name AS contractor_name,
                CONCAT(e.firstname, ' ', e.lastname) AS engineer_name,
                CONCAT(aa.firstname, ' ', aa.lastname) AS area_authority_name,
                CONCAT(h.firstname, ' ', h.lastname) AS hse_officer_name,
                CONCAT(co.firstname, ' ', co.lastname) AS coordinator_name,
                iso.permit_no AS isolation_permit_no, iso.status AS isolation_permit_status,
                (SELECT COUNT(*) FROM {$p}eptw_documents d WHERE d.permit_id = x.id) AS doc_count,
                {$req_sql} AS req_docs_have,
                (SELECT COUNT(*) FROM {$p}eptw_extensions ex WHERE ex.permit_id = x.id AND ex.status = 'pending') AS pending_extensions,
                (SELECT COUNT(*) FROM {$p}eptw_gas_tests g WHERE g.permit_id = x.id) AS gas_test_count", false)
            ->from($p . 'eptw_permits x')
            ->join($p . 'eptw_projects pr', 'pr.id = x.project_id', 'left')
            ->join($p . 'eptw_areas a', 'a.id = x.area_id', 'left')
            ->join($p . 'eptw_permit_types t', 't.id = x.permit_type_id', 'left')
            ->join($p . 'eptw_contractors c', 'c.id = x.contractor_id', 'left')
            ->join($p . 'staff e', 'e.staffid = x.engineer_id', 'left')
            ->join($p . 'staff aa', 'aa.staffid = x.area_authority_id', 'left')
            ->join($p . 'staff h', 'h.staffid = x.hse_officer_id', 'left')
            ->join($p . 'staff co', 'co.staffid = x.coordinator_id', 'left')
            ->join($p . 'eptw_permits iso', 'iso.id = x.isolation_permit_id', 'left');
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
        $working   = in_array($row->status, eptw_working_statuses(), true);
        $end       = $row->end_at ? strtotime($row->end_at) : 0;
        $warn_secs = ((int) ($row->type_expiry_hours ?? 0) > 0 ? (int) $row->type_expiry_hours : (int) eptw_opt('eptw_expiring_hours')) * 3600;

        $row->is_expired     = $row->status === 'expired' || ($working && $end && $end < time());
        $row->expiring_soon  = $working && $end && !$row->is_expired && ($end - time()) <= $warn_secs;
        $row->req_docs_total = count(eptw_required_doc_types());
        $row->work_auth      = eptw_work_authorization($row);
        $row->auto_flags     = $this->auto_flags($row);

        return $row;
    }

    /** Things a coordinator would want pointed out without asking. */
    private function auto_flags($row)
    {
        $flags = [];
        if ($row->is_expired) {
            $flags[] = ['bad', 'Expired — work not authorized'];
        } elseif ($row->expiring_soon) {
            $flags[] = ['warn', 'Expires ' . eptw_time_until($row->end_at)];
        }
        if ($row->simops_flag) {
            $flags[] = ['bad', 'SIMOPS conflict' . ($row->simops_ack_at ? ' (acknowledged)' : '')];
        }
        if ($row->gas_test_required && in_array($row->status, eptw_live_statuses(), true) && (int) $row->gas_test_count === 0) {
            $flags[] = ['warn', 'Gas test required — none recorded'];
        }
        if ((int) $row->pending_extensions > 0) {
            $flags[] = ['info', 'Extension awaiting approval'];
        }
        if ($row->status === 'closed_docs_pending') {
            $flags[] = ['warn', 'Closure documents pending upload'];
        }
        if ((int) $row->extension_count >= (int) eptw_opt('eptw_max_extensions') && (int) eptw_opt('eptw_max_extensions') > 0) {
            $flags[] = ['warn', 'Extension limit reached'];
        }
        if ((int) $row->isolation_permit_id && $row->isolation_permit_no) {
            $flags[] = ['info', 'Isolation under ' . $row->isolation_permit_no];
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

    /**
     * Only a draft or a returned permit is editable. Once a number is issued
     * the approved record is never overwritten: validity changes go through
     * the extension workflow and everything else through remarks and actions.
     */
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

    /** Register filter keys, shared by the register, its export and the dashboard drill-down. */
    public static function filter_keys()
    {
        return ['q', 'view', 'status', 'project', 'area', 'type', 'contractor', 'engineer', 'shift', 'from', 'to', 'risk'];
    }

    /**
     * Register query.
     *
     * @param array $f filters: see filter_keys(); status may be a comma list
     */
    public function register(array $f, $limit = 100, $offset = 0, &$total = null)
    {
        $p = db_prefix();
        $this->apply_filters($f);
        $total = (int) $this->db->count_all_results($p . 'eptw_permits x', false);
        $this->db->reset_query();

        $this->base_select();
        $this->apply_filters($f);
        $this->db->order_by(in_array((string) ($f['view'] ?? ''), ['expired', 'expiring'], true) ? 'x.end_at' : 'x.created_at', ($f['view'] ?? '') === 'expiring' || ($f['view'] ?? '') === 'expired' ? 'asc' : 'desc')
            ->limit((int) $limit, (int) $offset);

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
            $list = array_values(array_intersect(array_map('trim', explode(',', (string) $f['status'])), array_keys(eptw_statuses())));
            $this->db->where_in('x.status', count($list) ? $list : ['—']);
        }
        if (!empty($f['shift']) && isset(eptw_shifts()[$f['shift']])) {
            $this->db->where('x.shift', (string) $f['shift']);
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
            case 'ready':
                $p = db_prefix();
                $this->db->where_in('x.status', eptw_pending_statuses())
                    ->where("NOT EXISTS (SELECT 1 FROM {$p}eptw_approvals ap WHERE ap.permit_id = x.id AND ap.step != 'coordinator' AND ap.decision != 'approved')", null, false);
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
                $this->db->where('x.status', 'suspended');
                break;
            case 'on_hold':
                $this->db->where_in('x.status', eptw_hold_statuses());
                break;
            case 'extensions':
                $this->db->where('(SELECT COUNT(*) FROM ' . db_prefix() . 'eptw_extensions ex WHERE ex.permit_id = x.id AND ex.status = \'pending\') >', 0, false);
                break;
            case 'expiring':
                $this->db->where_in('x.status', eptw_working_statuses())->where('x.end_at >=', $now)->where(eptw_expiring_sql($now), null, false);
                break;
            case 'expired':
                $this->db->group_start()->where('x.status', 'expired')
                    ->or_group_start()->where_in('x.status', eptw_working_statuses())->where('x.end_at <', $now)->group_end()
                    ->group_end();
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
            case 'latest':
                $this->db->where('x.created_at >=', date('Y-m-d H:i:s', strtotime('-7 days')));
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
            // Nothing to approve — drop the half-built query, or the next one
            // on this page inherits its "x" alias and dies.
            $this->db->reset_query();

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

    /**
     * Role-based work queue for the dashboard: what this person must act on,
     * grouped by the action. Least privilege — every list goes through the
     * register's own visibility rules.
     *
     * @return array<string, array{label:string, hint:string, view:string, rows:array}>
     */
    public function action_queue(array $scope = [])
    {
        $role = eptw_role();
        $me   = (int) eptw_me()['staff_id'];
        $out  = [];
        $add  = function ($key, $label, $hint, array $filters, $limit = 6) use (&$out, $scope) {
            $total = 0;
            $rows  = $this->register(array_merge($scope, $filters), $limit, 0, $total);
            if ($total) {
                $out[$key] = ['label' => $label, 'hint' => $hint, 'filters' => array_merge($scope, $filters), 'rows' => $rows, 'total' => $total];
            }
        };

        if (in_array($role, ['area_authority', 'hse', 'manager'], true)) {
            $rows = $this->my_queue();
            if (count($rows)) {
                $out['sign'] = ['label' => 'Awaiting your signature', 'hint' => 'Review and sign as ' . eptw_role_label($role), 'filters' => ['view' => 'pending'], 'rows' => array_slice($rows, 0, 6), 'total' => count($rows)];
            }
        }
        if (in_array($role, ['coordinator', 'admin'], true)) {
            $add('ready', 'Ready to issue', 'All reviews signed — issue the permit number', ['view' => 'ready']);
            $add('extensions', 'Extension requests awaiting approval', 'Revalidate conditions, then approve or reject', ['view' => 'extensions']);
            $add('simops', 'SIMOPS holds', 'Resolve the conflict before work starts', ['status' => 'on_hold_simops']);
        }
        if (in_array($role, ['coordinator', 'admin', 'area_authority', 'hse'], true)) {
            $add('expired', 'Expired — action required', 'Close the permit or request an extension', ['view' => 'expired']);
            $add('issued', 'Issued — start of work not confirmed', 'Confirm the start of work on site', ['view' => 'issued']);
        }
        if (in_array($role, ['engineer', 'coordinator', 'area_authority', 'hse'], true)) {
            $add('my_drafts', 'Your drafts and returned permits', 'Complete and submit for a permit number', ['view' => 'drafts', 'engineer' => $me]);
        }
        if ($role === 'engineer') {
            $add('my_expiring', 'Your permits expiring soon', 'Request an extension if work continues', ['view' => 'expiring', 'engineer' => $me]);
            $add('my_expired', 'Your expired permits', 'Work is not authorized — request an extension or ask for closure', ['view' => 'expired', 'engineer' => $me]);
        }

        return $out;
    }

    /* ═══════════════════════════ Saving the form ════════════════════ */

    /** Fields whose before/after values go into the audit trail when a permit is edited. */
    private function audit_fields()
    {
        return [
            'project_id' => 'Project', 'area_id' => 'Area', 'contractor_id' => 'Contractor', 'subcontractor' => 'Subcontractor', 'work_order' => 'Work order',
            'work_title' => 'Work title', 'work_description' => 'Work description', 'location' => 'Location', 'equipment_tag' => 'Equipment tag',
            'shift' => 'Shift', 'workers_count' => 'Workers', 'start_at' => 'Start', 'end_at' => 'End', 'engineer_id' => 'Initiator',
            'permit_holder' => 'Permit holder', 'supervisor' => 'Supervisor', 'contact_no' => 'Contact no.', 'area_authority_id' => 'Area Authority',
            'hse_officer_id' => 'HSE Officer', 'risk_level' => 'Risk level', 'hazards' => 'Hazards', 'extra_hazards' => 'Additional hazards',
            'controls' => 'Control measures', 'extra' => 'Type-specific details', 'ppe' => 'PPE', 'ra_ref' => 'RA / JSA ref',
            'isolation_applicable' => 'Isolation applicable', 'isolation_permit_id' => 'Linked isolation permit', 'isolation_type' => 'Isolation type',
            'isolation_cert_no' => 'Isolation certificate', 'loto_applied' => 'LOTO applied', 'zero_energy_verified' => 'Zero energy verified',
            'isolation_authority' => 'Isolation authority', 'lock_tag_numbers' => 'Lock / tag numbers', 'gas_test_required' => 'Gas test required',
            'weather' => 'Weather', 'remarks' => 'Remarks',
        ];
    }

    /** Readable value for the audit trail. */
    private function audit_value($field, $value)
    {
        $value = (string) $value;
        switch ($field) {
            case 'project_id':
                $row = $this->db->select('name')->where('id', (int) $value)->get(db_prefix() . 'eptw_projects')->row();

                return $row ? $row->name : '—';
            case 'area_id':
                $row = $this->db->select('name')->where('id', (int) $value)->get(db_prefix() . 'eptw_areas')->row();

                return $row ? $row->name : '—';
            case 'contractor_id':
                $row = $this->db->select('name')->where('id', (int) $value)->get(db_prefix() . 'eptw_contractors')->row();

                return $row ? $row->name : '—';
            case 'isolation_permit_id':
                $row = (int) $value ? $this->db->select('permit_no')->where('id', (int) $value)->get(db_prefix() . 'eptw_permits')->row() : null;

                return $row ? (string) $row->permit_no : '—';
            case 'engineer_id':
            case 'area_authority_id':
            case 'hse_officer_id':
                return (int) $value ? eptw_staff_name($value) : '—';
            case 'hazards':
                $list = json_decode($value, true);

                return is_array($list) ? implode(', ', array_keys(array_filter($list, function ($v) { return $v === 'yes'; }))) ?: 'none' : '';
            case 'controls':
                $list = json_decode($value, true);
                $out  = [];
                foreach ((array) $list as $k => $c) {
                    if (($c['v'] ?? '') !== '') {
                        $out[] = $k . ': ' . strtoupper($c['v']);
                    }
                }

                return implode('; ', $out);
            case 'extra_hazards':
            case 'ppe':
                $list = json_decode($value, true);

                return is_array($list) ? implode(', ', $list) : '';
            case 'extra':
                $list = json_decode($value, true);
                $out  = [];
                foreach ((array) $list as $k => $v) {
                    $out[] = $k . ': ' . (is_array($v) ? json_encode($v) : $v);
                }

                return implode('; ', $out);
            case 'loto_applied':
            case 'zero_energy_verified':
            case 'gas_test_required':
                return $value === '1' ? 'Yes' : 'No';
        }

        return $value;
    }

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
        $project = $this->db->where('id', $project_id)->get(db_prefix() . 'eptw_projects')->row();

        // The area must belong to the chosen project (or be a deliberately shared area).
        $area_id = (int) ($in['area_id'] ?? 0);
        if ($area_id) {
            $area = $this->db->where('id', $area_id)->get(db_prefix() . 'eptw_areas')->row();
            $ok   = $area && ((int) $area->project_id === $project_id || ((int) $area->project_id === 0 && eptw_opt('eptw_shared_areas') === '1'));
            if (!$ok) {
                return 'The selected area does not belong to ' . ($project ? $project->name : 'this project') . '. Choose an area of that project.';
            }
        }

        $title = trim((string) ($in['work_title'] ?? ''));
        $desc  = trim((string) ($in['work_description'] ?? ''));
        if ($title === '' && $desc === '') {
            return 'Describe the work.';
        }
        if ($title === '') {
            $title = mb_substr($desc, 0, 120);
        }

        [$default_hours, $max_hours] = eptw_validity_limits($type, $project);
        $start = $this->parse_dt($in['start_at'] ?? '');
        $end   = $this->parse_dt($in['end_at'] ?? '');
        if (!$start) {
            return 'Set the planned start date and time.';
        }
        if (!$end) {
            $end = date('Y-m-d H:i:s', strtotime($start) + $default_hours * 3600);
        }
        if (strtotime($end) <= strtotime($start)) {
            return 'The end time must be after the start time.';
        }
        if ($max_hours && (strtotime($end) - strtotime($start)) > $max_hours * 3600) {
            return 'The maximum validity for a ' . $type->name . ' is ' . $max_hours . ' h. Shorten the window — longer work continues through an approved extension.';
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
                $val         = is_array($val) ? $val : [];
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

        // Isolation / LOTO: applicability per the type's rule, not a blanket requirement.
        $rule = $type->isolation_rule ?? 'conditional';
        $applicable = (string) ($in['isolation_applicable'] ?? '');
        if ($rule === 'always') {
            $applicable = 'yes';
        } elseif ($rule === 'never') {
            $applicable = 'na';
        } elseif (!in_array($applicable, ['yes', 'no', 'na'], true)) {
            $applicable = '';
        }
        $iso_on = $applicable === 'yes';
        $iso_permit_id = $iso_on ? (int) ($in['isolation_permit_id'] ?? 0) : 0;
        if ($iso_permit_id) {
            $iso = $this->db->select('x.id, x.project_id, x.status, t.code')->from(db_prefix() . 'eptw_permits x')
                ->join(db_prefix() . 'eptw_permit_types t', 't.id = x.permit_type_id', 'left')->where('x.id', $iso_permit_id)->get()->row();
            if (!$iso || (int) $iso->project_id !== $project_id || (int) $iso_permit_id === $id) {
                return 'The linked isolation permit must be a permit of the same project.';
            }
        }

        $ppe    = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n|,/', (string) ($in['ppe'] ?? '')))));
        $shift  = isset(eptw_shifts()[$in['shift'] ?? '']) ? $in['shift'] : 'day';
        $yes    = array_keys(array_filter($hazards, function ($v) { return $v === 'yes'; }));
        $assess = eptw_risk_assess($type, array_merge($yes, $extra_hazards), $shift, (int) ($in['workers_count'] ?? 0));

        $data = [
            'project_id'           => $project_id,
            'area_id'              => $area_id,
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
            'risk_level'           => $assess['level'],
            'high_risk'            => $assess['high_risk'] ? 1 : 0,
            'risk_reasons'         => json_encode($assess['reasons']),
            'hazards'              => json_encode($hazards),
            'extra_hazards'        => json_encode($extra_hazards),
            'controls'             => json_encode($controls),
            'extra'                => json_encode($extra),
            'ppe'                  => json_encode($ppe),
            'ra_ref'               => substr(trim((string) ($in['ra_ref'] ?? '')), 0, 80),
            'isolation_applicable' => $applicable,
            'isolation_required'   => $iso_on ? 1 : 0,
            'isolation_permit_id'  => $iso_permit_id,
            'isolation_type'       => $iso_on ? substr(trim((string) ($in['isolation_type'] ?? '')), 0, 80) : '',
            'isolation_cert_no'    => $iso_on ? substr(trim((string) ($in['isolation_cert_no'] ?? '')), 0, 80) : '',
            'loto_applied'         => $iso_on && !empty($in['loto_applied']) ? 1 : 0,
            'zero_energy_verified' => $iso_on && !empty($in['zero_energy_verified']) ? 1 : 0,
            'isolation_authority'  => $iso_on ? substr(trim((string) ($in['isolation_authority'] ?? '')), 0, 150) : '',
            'lock_tag_numbers'     => $iso_on ? substr(trim((string) ($in['lock_tag_numbers'] ?? '')), 0, 255) : '',
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
            $raw = $this->db->where('id', $id)->get(db_prefix() . 'eptw_permits')->row_array();
            $this->db->where('id', $id)->update(db_prefix() . 'eptw_permits', $data);

            // Who changed what, from what to what — never overwritten.
            $changes = [];
            foreach ($this->audit_fields() as $field => $label) {
                if (!array_key_exists($field, $data)) {
                    continue;
                }
                $old = (string) ($raw[$field] ?? '');
                $new = (string) $data[$field];
                if ($old !== $new) {
                    $changes[] = ['field' => $label, 'from' => mb_substr($this->audit_value($field, $old), 0, 300), 'to' => mb_substr($this->audit_value($field, $new), 0, 300)];
                }
            }
            $reason = trim((string) ($in['change_reason'] ?? ''));
            if (count($changes)) {
                $this->event($id, 'edited', $permit->status, $permit->status, trim(count($changes) . ' field(s) changed. ' . $reason), ['changes' => $changes]);
            }

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

    /**
     * What must be in place before a permit can be submitted or issued.
     * The draft itself may be saved incomplete.
     *
     * @return string[] problems, empty when ready
     */
    public function readiness_errors($permit)
    {
        $type   = eptw_permit_type($permit->permit_type_id);
        $errors = [];
        if (!$permit->area_id) {
            $errors[] = 'Choose the area / zone (it is part of the permit number and of SIMOPS detection).';
        }
        $rule = $type->isolation_rule ?? 'conditional';
        if ($rule === 'conditional' && !in_array($permit->isolation_applicable, ['yes', 'no', 'na'], true)) {
            $errors[] = 'Answer "Is isolation / LOTO applicable to this task?" (Yes / No / N/A).';
        }
        if ($permit->isolation_applicable === 'yes' && trim((string) $permit->isolation_cert_no) === '' && !(int) $permit->isolation_permit_id) {
            $errors[] = 'Isolation applies: give the isolation certificate number or link the LOTO / isolation permit.';
        }

        return $errors;
    }

    /** Live LOTO / isolation permits of a project that work permits can be linked to. */
    public function isolation_permits($project_id, $exclude_id = 0)
    {
        $p = db_prefix();

        return $this->db->select('x.id, x.permit_no, x.work_title, x.status, x.equipment_tag')
            ->from($p . 'eptw_permits x')->join($p . 'eptw_permit_types t', 't.id = x.permit_type_id', 'left')
            ->where('x.project_id', (int) $project_id)->where('x.id !=', (int) $exclude_id)
            ->group_start()->where('t.code', 'LOTO')->or_where('t.isolation_rule', 'always')->group_end()
            ->where_in('x.status', array_merge(eptw_live_statuses(), eptw_pending_statuses()))
            ->order_by('x.id', 'desc')->limit(100)->get()->result();
    }

    /** Open work permits that rely on an isolation permit. */
    public function dependent_permits($isolation_permit_id)
    {
        return $this->db->select('id, permit_no, work_title, status')->where('isolation_permit_id', (int) $isolation_permit_id)
            ->where_in('status', array_merge(eptw_live_statuses(), eptw_pending_statuses(), ['draft', 'returned']))
            ->get(db_prefix() . 'eptw_permits')->result();
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

    /**
     * Hard delete — only for a permit that never received a number. An issued
     * permit is a control record: it is cancelled or closed, never erased.
     */
    public function delete($id)
    {
        $permit = $this->get($id);
        if (!$permit || (string) $permit->permit_no !== '') {
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
            'staff_id'    => $this->system_actor ? 0 : (int) eptw_me()['staff_id'],
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
            'remark' => 'Remark', 'expired' => 'Expired — work not authorized', 'auto_activated' => 'Marked active at planned start (monitoring mode)', 'imported' => 'Imported from register',
            'toolbox' => 'Toolbox talk recorded', 'sod_override' => 'Segregation-of-duties override', 'simops_ack' => 'SIMOPS conflict acknowledged',
            'simops_flagged' => 'SIMOPS conflict with a newly issued permit', 'doc_verified' => 'Document verified', 'doc_rejected' => 'Document rejected',
            'activated' => 'Start of work confirmed — work authorized', 'closure_blocked' => 'Closure blocked',
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
        $problems = $this->readiness_errors($permit);
        if (count($problems)) {
            return 'Before submitting: ' . implode(' ', $problems);
        }
        $type      = eptw_permit_type($permit->permit_type_id);
        $resubmit  = $permit->status === 'returned';
        $this->build_approvals($permit, $type);
        if ($resubmit) {
            $note = trim('Resubmitted after correction — every review step must sign again. ' . $note);
        }

        $this->set_status($permit, 'number_requested', 'number_requested', $note, ['number_requested_at' => date('Y-m-d H:i:s'), 'return_reason' => '']);
        $fresh = $this->get($permit->id);

        $this->notify_roles($fresh, ['coordinator', 'area_authority', 'hse'], 'eptw_not_requested', [$fresh->work_title, eptw_staff_name($fresh->engineer_id)],
            'Permit number requested: ' . $fresh->work_title,
            eptw_staff_name($fresh->engineer_id) . ' has submitted a ' . $fresh->type_name . ' for ' . $fresh->project_name . ' / ' . $fresh->area_name . ' and is waiting for a permit number.');

        return true;
    }

    /**
     * Review steps for a permit: the project × type approval matrix when one
     * is configured, else the permit type's own steps; high-risk permits pick
     * up the Manager when risk-based escalation is switched on.
     */
    public function approval_steps($permit, $type)
    {
        $steps = (array) ($type->approvals ?? ['area_authority', 'hse', 'coordinator']);
        $row   = $this->db->where('project_id', (int) $permit->project_id)->where('permit_type_id', (int) $permit->permit_type_id)
            ->get(db_prefix() . 'eptw_approval_matrix')->row();
        if ($row) {
            $custom = json_decode((string) $row->steps, true);
            if (is_array($custom)) {
                $steps = $custom;
            }
        }
        if (!empty($permit->high_risk) && eptw_opt('eptw_escalate_high_risk') === '1' && !in_array('manager', $steps, true)) {
            $steps[] = 'manager';
        }
        $steps = array_values(array_intersect(['area_authority', 'hse', 'manager', 'coordinator'], $steps));
        if (!in_array('coordinator', $steps, true)) {
            $steps[] = 'coordinator';
        }

        return $steps;
    }

    private function build_approvals($permit, $type)
    {
        $this->db->where('permit_id', (int) $permit->id)->delete(db_prefix() . 'eptw_approvals');
        $steps = array_values(array_unique(array_merge(['initiator'], $this->approval_steps($permit, $type))));
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
        if ($decision === 'approved') {
            $me_id = (int) eptw_me()['staff_id'];
            if (in_array($me_id, [(int) $permit->engineer_id, (int) $permit->created_by], true)
                && ($err = $this->sod_check($permit, 'own_permit', $remarks)) !== null) {
                return $err;
            }
            $other = $this->db->where('permit_id', (int) $permit->id)->where('staff_id', $me_id)->where('decision', 'approved')
                ->where_not_in('step', ['initiator', $step])->count_all_results(db_prefix() . 'eptw_approvals');
            if ($other && ($err = $this->sod_check($permit, 'two_steps', $remarks)) !== null) {
                return $err;
            }
        }
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

    /**
     * Segregation of duties. Returns an error when an enabled rule forbids the
     * step; an ePTW administrator may override with a written reason, which is
     * logged as its own audit event. Returns null when the step may go ahead.
     */
    private function sod_check($permit, $rule, $note)
    {
        if (!eptw_sod($rule)) {
            return null;
        }
        $text = eptw_sod_rule_options()[$rule] ?? $rule;
        if (eptw_role() !== 'admin') {
            return 'Segregation of duties: ' . lcfirst($text) . '. Ask another authorized person to do this step.';
        }
        if (trim((string) $note) === '') {
            return 'Segregation of duties: ' . lcfirst($text) . '. As administrator you may override it — write the reason in the remarks / note.';
        }
        $this->event($permit->id, 'sod_override', $permit->status, $permit->status, $text . ' — overridden: ' . trim((string) $note));

        return null;
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
        $problems = $this->readiness_errors($permit);
        if (count($problems)) {
            return 'Before issuing: ' . implode(' ', $problems);
        }
        if (in_array((int) eptw_me()['staff_id'], [(int) $permit->engineer_id, (int) $permit->created_by], true)
            && ($err = $this->sod_check($permit, 'issuer_initiator', $override_note)) !== null) {
            return $err;
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
        // The permits already on site are in conflict too — flag them so their pages and the dashboard say so.
        foreach ($conflicts as $c) {
            $other = $this->db->select('id, status, simops_notes')->where('id', (int) $c['permit_id'])->get(db_prefix() . 'eptw_permits')->row();
            if (!$other) {
                continue;
            }
            $line = strtoupper($c['severity']) . ': ' . $c['description'] . ' — ' . $number['no'] . ' (' . eptw_dt($permit->start_at) . ' → ' . eptw_dt($permit->end_at) . ')';
            $this->db->where('id', (int) $other->id)->update(db_prefix() . 'eptw_permits', [
                'simops_flag' => 1, 'simops_ack_by' => 0, 'simops_ack_at' => null,
                'simops_notes' => trim((string) $other->simops_notes . "\n" . $line),
            ]);
            $this->event($other->id, 'simops_flagged', $other->status, $other->status, $line);
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

    /**
     * Start of work: the deliberate, authorized confirmation that turns an
     * issued permit into authorization to work. Where isolation applies the
     * LOTO and zero-energy check are confirmed here; where a gas test is
     * required its result is confirmed here (digital record or paper sheet).
     * $auto is the optional clock-driven monitoring mode (Setup → General).
     */
    public function activate($permit, $note = '', $auto = false, array $in = [])
    {
        if (!$auto && !eptw_can('status') && !(eptw_role() === 'area_authority')) {
            return 'Only the PTW Coordinator or Area Authority can confirm the start of work.';
        }
        if ($permit->status !== 'issued') {
            return 'Only an issued permit can be started.';
        }
        if (strtotime((string) $permit->end_at) <= time()) {
            return 'The validity of this permit has ended. Request an extension, or close it.';
        }
        $extra = ['activated_at' => date('Y-m-d H:i:s')];
        $notes = [];
        if (!$auto) {
            if ($permit->isolation_applicable === 'yes') {
                if (empty($in['loto_applied']) || empty($in['zero_energy_verified'])) {
                    return 'Isolation applies to this permit: confirm that LOTO is applied and zero energy is verified before work starts.';
                }
                $extra['loto_applied']         = 1;
                $extra['zero_energy_verified'] = 1;
                if (trim((string) ($in['lock_tag_numbers'] ?? '')) !== '') {
                    $extra['lock_tag_numbers'] = substr(trim((string) $in['lock_tag_numbers']), 0, 255);
                }
                $notes[] = 'LOTO applied and zero energy verified';
            }
            if ($permit->gas_test_required) {
                $gas = (string) ($in['gas_check'] ?? '');
                if ($gas !== 'recorded' && $gas !== 'paper') {
                    return 'A gas test is required: confirm the pre-work gas test (recorded here or on the paper sheet).';
                }
                if ($gas === 'recorded') {
                    $last = $this->db->where('permit_id', (int) $permit->id)->order_by('tested_at', 'desc')->get(db_prefix() . 'eptw_gas_tests')->row();
                    if (!$last) {
                        return 'No gas test is recorded on this permit yet. Record it under Gas tests, or confirm the paper gas-test sheet.';
                    }
                    if ($last->result !== 'safe') {
                        return 'The latest gas test is UNSAFE. Work cannot start until a safe test is recorded.';
                    }
                }
                $notes[] = $gas === 'paper' ? 'Pre-work gas test done on the paper sheet' : 'Pre-work gas test recorded — safe';
            }
        }
        $this->set_status($permit, 'active', $auto ? 'auto_activated' : 'activated', trim(implode('. ', $notes) . ($notes ? '. ' : '') . $note), $extra);

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
        if (strtotime((string) $permit->end_at) <= time()) {
            return 'The validity of this permit has ended, so it cannot resume. Request an extension, or close it.';
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

    /**
     * Request → authorized review → revalidation of conditions (and the gas
     * test where one applies) → approval → new expiry. The original expiry
     * and every decision stay on record; an expired permit resumes only here.
     */
    public function request_extension($permit, $new_end, $reason, array $checks = [])
    {
        if (!eptw_can('extend_request') || !$this->can_view($permit)) {
            return 'You cannot request an extension on this permit.';
        }
        if (!in_array($permit->status, ['issued', 'active', 'active_extended', 'expired'], true)) {
            return 'Only an issued, active or expired permit can be extended.';
        }
        $max = (int) eptw_opt('eptw_max_extensions');
        if ($max > 0 && (int) $permit->extension_count >= $max) {
            return 'This permit has reached the maximum of ' . $max . ' extensions. Raise a new permit instead.';
        }
        $new_end = $this->parse_dt($new_end);
        if (!$new_end || strtotime($new_end) <= strtotime($permit->end_at) || strtotime($new_end) <= time()) {
            return 'The new end time must be later than the current end time (' . eptw_dt($permit->end_at) . ') and in the future.';
        }
        if (($err = $this->extension_window_error($permit, $new_end)) !== null) {
            return $err;
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

        // The issuer may approve their own request in the same step, but only
        // with the conditions revalidated, and only where segregation allows.
        if (eptw_can('extend_approve') && !empty($checks['approve_now']) && !eptw_sod('own_extension')) {
            $fresh = $this->get($permit->id);

            return $this->decide_extension($fresh, $ext_id, 'approved', (string) ($checks['note'] ?? ''), $checks);
        }

        $this->notify_roles($permit, ['coordinator'], 'eptw_not_extension_requested', [$permit->permit_no], 'Extension requested: ' . $permit->permit_no,
            eptw_staff_name(eptw_me()['staff_id']) . ' asks to extend ' . $permit->permit_no . ' until ' . eptw_dt($new_end) . '. Reason: ' . $reason);

        return true;
    }

    /** An extension may not stretch one window beyond the type / project maximum validity. */
    private function extension_window_error($permit, $new_end)
    {
        $type    = eptw_permit_type($permit->permit_type_id);
        $project = $this->db->where('id', (int) $permit->project_id)->get(db_prefix() . 'eptw_projects')->row();
        [, $max] = $type ? eptw_validity_limits($type, $project) : [0, 0];
        $from    = max(time(), strtotime((string) $permit->end_at));
        if ($max && (strtotime($new_end) - $from) > $max * 3600) {
            return 'One extension may add at most ' . $max . ' h (the maximum validity for this permit type).';
        }

        return null;
    }

    public function extensions($permit_id)
    {
        $rows = $this->db->select('ex.*, CONCAT(r.firstname, " ", r.lastname) AS requested_name, CONCAT(d.firstname, " ", d.lastname) AS decided_name')
            ->from(db_prefix() . 'eptw_extensions ex')
            ->join(db_prefix() . 'staff r', 'r.staffid = ex.requested_by', 'left')
            ->join(db_prefix() . 'staff d', 'd.staffid = ex.decided_by', 'left')
            ->where('ex.permit_id', (int) $permit_id)->order_by('ex.id', 'desc')->get()->result();
        foreach ($rows as $row) {
            $decoded     = json_decode((string) ($row->checks ?? ''), true);
            $row->checks = is_array($decoded) ? $decoded : [];
        }

        return $rows;
    }

    /**
     * Approve or reject. Approval needs the conditions revalidated: site
     * conditions unchanged, controls still in place, and — where the permit
     * needs one — a repeated gas test.
     */
    public function decide_extension($permit, $extension_id, $decision, $note = '', array $checks = [])
    {
        if (!eptw_can('extend_approve')) {
            return 'Only the PTW Coordinator can decide extensions.';
        }
        $ext = $this->db->where('id', (int) $extension_id)->where('permit_id', (int) $permit->id)->get(db_prefix() . 'eptw_extensions')->row();
        if (!$ext || $ext->status !== 'pending') {
            return 'That extension request has already been decided.';
        }
        $decision = $decision === 'approved' ? 'approved' : 'rejected';
        $me       = (int) eptw_me()['staff_id'];

        $record = [];
        if ($decision === 'approved') {
            if ((int) $ext->requested_by === $me && ($err = $this->sod_check($permit, 'own_extension', $note)) !== null) {
                return $err;
            }
            if (strtotime((string) $ext->new_end_at) <= time()) {
                return 'The requested end time has already passed. Reject this request and ask for a new one.';
            }
            if (empty($checks['conditions_ok']) || empty($checks['controls_ok'])) {
                return 'Revalidate before approving: confirm the site conditions are unchanged and the controls are still in place.';
            }
            $gas = (string) ($checks['gas'] ?? '');
            if ($permit->gas_test_required && !in_array($gas, ['recorded', 'paper'], true)) {
                return 'This permit requires gas testing: confirm the repeated gas test (recorded here or on the paper sheet) before approving.';
            }
            if ($gas === 'recorded') {
                $last = $this->db->where('permit_id', (int) $permit->id)->order_by('tested_at', 'desc')->get(db_prefix() . 'eptw_gas_tests')->row();
                if (!$last || $last->result !== 'safe') {
                    return 'The latest recorded gas test is missing or UNSAFE. Record a safe test first, or reject the extension.';
                }
            }
            $record = [
                'conditions_ok' => 1, 'controls_ok' => 1,
                'gas'           => $gas !== '' ? $gas : 'not_required',
                'gas_ref'       => substr(trim((string) ($checks['gas_ref'] ?? '')), 0, 120),
                'by'            => eptw_staff_name($me),
                'at'            => date('Y-m-d H:i:s'),
            ];
        }
        $this->db->where('id', $ext->id)->update(db_prefix() . 'eptw_extensions', [
            'status' => $decision, 'decided_by' => $me, 'decided_at' => date('Y-m-d H:i:s'), 'decision_note' => substr((string) $note, 0, 255),
            'checks' => count($record) ? json_encode($record) : null,
        ]);

        if ($decision === 'rejected') {
            $this->event($permit->id, 'extension_rejected', $permit->status, $permit->status, $note);
            $this->notify_staff($permit, [(int) $ext->requested_by], 'eptw_not_extension_rejected', [$permit->permit_no], 'Extension rejected: ' . $permit->permit_no, 'The extension of ' . $permit->permit_no . ' was rejected. ' . $note);

            return true;
        }

        if ($permit->status === 'expired') {
            $status = $permit->activated_at ? 'active_extended' : 'issued';
        } else {
            $status = in_array($permit->status, ['active', 'active_extended'], true) ? 'active_extended' : $permit->status;
        }
        $checks_text = 'Conditions revalidated by ' . $record['by'] . ' (conditions unchanged, controls in place'
            . ($record['gas'] === 'recorded' ? ', gas test recorded — safe' : ($record['gas'] === 'paper' ? ', gas test on paper sheet' . ($record['gas_ref'] !== '' ? ' ' . $record['gas_ref'] : '') : '')) . ').';
        $this->set_status($permit, $status, 'extended', 'Until ' . eptw_dt($ext->new_end_at) . ' (was ' . eptw_dt($ext->old_end_at) . ') — ' . $ext->reason . '. ' . $checks_text . ($note !== '' ? ' ' . $note : ''), [
            'end_at' => $ext->new_end_at, 'extension_count' => (int) $permit->extension_count + 1, 'expiry_notified' => 0,
        ], ['old_end_at' => $ext->old_end_at, 'new_end_at' => $ext->new_end_at, 'original_end_at' => $permit->original_end_at]);

        // The longer window may now overlap other work.
        $fresh     = $this->get($permit->id);
        $conflicts = $this->simops_check($fresh);
        if (count($conflicts)) {
            $this->db->where('id', (int) $permit->id)->update(db_prefix() . 'eptw_permits', ['simops_flag' => 1, 'simops_notes' => $this->simops_text($conflicts)]);
            $this->notify_roles($fresh, ['coordinator', 'hse'], 'eptw_not_simops', [$fresh->permit_no], 'SIMOPS conflict after extension: ' . $fresh->permit_no, $this->simops_text($conflicts));
        }
        $this->notify_staff($permit, [(int) $ext->requested_by, (int) $permit->engineer_id, (int) $permit->area_authority_id, (int) $permit->hse_officer_id], 'eptw_not_extended', [$permit->permit_no, eptw_dt($ext->new_end_at)],
            'Permit extended: ' . $permit->permit_no, 'Permit ' . $permit->permit_no . ' now runs until ' . eptw_dt($ext->new_end_at) . '.');

        return true;
    }

    /* ── Closure ── */

    /**
     * Close-out checklist: Yes / No / N/A per item, with who confirmed it and
     * when. "No" needs a remark; where isolation applied, de-isolation must be
     * confirmed Yes by a named person before the permit can close; an
     * isolation permit cannot close while work permits still rely on it.
     */
    public function close($permit, array $closure, $note = '')
    {
        if (!eptw_can('status')) {
            return 'Only the PTW Coordinator can close a permit.';
        }
        if (!in_array($permit->status, eptw_live_statuses(), true)) {
            return 'This permit is not open.';
        }
        $dependents = $this->dependent_permits($permit->id);
        if (count($dependents)) {
            return 'Work permits still rely on this isolation: ' . implode(', ', array_map(function ($d) { return $d->permit_no ?: ('draft #' . $d->id); }, $dependents))
                . '. Close them before removing the isolation.';
        }

        $me_name = eptw_staff_name(eptw_me()['staff_id']);
        $now     = date('Y-m-d H:i:s');
        $iso_on  = $permit->isolation_applicable === 'yes';
        $checks  = [];
        $errors  = [];
        foreach (eptw_closure_items() as $key => $label) {
            $v = (string) ($closure[$key] ?? '');
            if (!in_array($v, ['yes', 'no', 'na'], true)) {
                $errors[] = 'Answer "' . $label . '".';
                continue;
            }
            $by = trim((string) ($closure[$key . '_by'] ?? ''));
            $r  = trim((string) ($closure[$key . '_remark'] ?? ''));
            if ($v === 'no' && $r === '') {
                $errors[] = '"' . $label . '" is No — say why in its remark.';
            }
            $checks[$key] = ['v' => $v, 'by' => substr($by !== '' ? $by : $me_name, 0, 150), 'at' => $now, 'r' => substr($r, 0, 255)];
        }
        if (($checks['work_completed']['v'] ?? '') === 'na') {
            $errors[] = '"Work completed" cannot be N/A — answer Yes, or No with the reason.';
        }
        if ($iso_on) {
            if (($checks['isolation_removed']['v'] ?? '') !== 'yes') {
                $errors[] = 'Isolation applied on this permit: de-isolation must be confirmed (Yes) before closure.';
            } elseif (trim((string) ($closure['isolation_removed_by'] ?? '')) === '') {
                $errors[] = 'Name the person who removed the isolation (isolation authority).';
            }
        }
        if (count($errors)) {
            return implode(' ', $errors);
        }
        $checks['final_remarks']  = substr(trim((string) ($closure['final_remarks'] ?? '')), 0, 1000);
        $checks['closed_by_name'] = $me_name;

        $docs_ok = $this->docs_complete($permit->id);
        $status  = $docs_ok ? 'closed' : 'closed_docs_pending';
        $summary = [];
        foreach (eptw_closure_items() as $key => $label) {
            $summary[] = $label . ': ' . strtoupper($checks[$key]['v']) . ($checks[$key]['r'] !== '' ? ' (' . $checks[$key]['r'] . ')' : '');
        }
        $this->set_status($permit, $status, 'closed', trim($note . ' ' . $checks['final_remarks']), [
            'closed_at' => $now, 'closed_by' => (int) eptw_me()['staff_id'], 'closure' => json_encode($checks), 'docs_complete' => $docs_ok ? 1 : 0, 'simops_flag' => 0,
        ], ['closure' => $summary]);
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

    /** A coordinator / HSE records that a SIMOPS warning was reviewed and the work may run side by side. */
    public function simops_acknowledge($permit, $note)
    {
        if (!eptw_can('status') && eptw_role() !== 'hse') {
            return 'Only the PTW Coordinator or HSE can acknowledge a SIMOPS conflict.';
        }
        if (!$permit->simops_flag) {
            return 'This permit has no SIMOPS conflict.';
        }
        if ($permit->status === 'on_hold_simops') {
            return 'A blocking conflict is resolved with "Resolve SIMOPS & resume", not acknowledged.';
        }
        $note = trim((string) $note);
        if ($note === '') {
            return 'Record how the simultaneous work is controlled (e.g. exclusion zone, sequencing, supervisor).';
        }
        $this->db->where('id', (int) $permit->id)->update(db_prefix() . 'eptw_permits', ['simops_ack_by' => (int) eptw_me()['staff_id'], 'simops_ack_at' => date('Y-m-d H:i:s')]);
        $this->event($permit->id, 'simops_ack', $permit->status, $permit->status, $note);

        return true;
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

    /**
     * Required closure documents still missing. With document review on,
     * a document counts only once it has been verified.
     */
    public function missing_docs($permit_id)
    {
        $have = [];
        $this->db->select('doc_type')->where('permit_id', (int) $permit_id);
        if (eptw_opt('eptw_docs_review') === '1') {
            $this->db->where('review_status', 'verified');
        }
        foreach ($this->db->get(db_prefix() . 'eptw_documents')->result() as $row) {
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

        $this->sync_docs_complete($permit, 'All closure documents uploaded');

        return true;
    }

    /** Closing the documents gap flips the permit to fully closed by itself (and back, if one is removed or rejected). */
    private function sync_docs_complete($permit, $why)
    {
        $complete = $this->docs_complete($permit->id);
        if ($permit->status === 'closed_docs_pending' && $complete) {
            $this->db->where('id', (int) $permit->id)->update(db_prefix() . 'eptw_permits', ['status' => 'closed', 'docs_complete' => 1, 'updated_at' => date('Y-m-d H:i:s')]);
            $this->event($permit->id, 'closed', 'closed_docs_pending', 'closed', $why);
        } elseif ($permit->status === 'closed' && !$complete) {
            $this->db->where('id', (int) $permit->id)->update(db_prefix() . 'eptw_permits', ['status' => 'closed_docs_pending', 'docs_complete' => 0, 'updated_at' => date('Y-m-d H:i:s')]);
            $this->event($permit->id, 'document', 'closed', 'closed_docs_pending', 'A required closure document is missing again');
        } else {
            $this->db->where('id', (int) $permit->id)->update(db_prefix() . 'eptw_permits', ['docs_complete' => $complete ? 1 : 0]);
        }
    }

    public function can_review_documents()
    {
        return eptw_can('status') || eptw_role() === 'hse';
    }

    /** Verify or reject an uploaded document (document review, Setup → General). */
    public function review_document($permit, $doc_id, $decision, $note = '')
    {
        if (!$this->can_review_documents()) {
            return 'Only the PTW Coordinator or HSE can review documents.';
        }
        $doc = $this->document($doc_id);
        if (!$doc || (int) $doc->permit_id !== (int) $permit->id) {
            return 'Document not found.';
        }
        $decision = $decision === 'verified' ? 'verified' : 'rejected';
        if ($decision === 'rejected' && trim((string) $note) === '') {
            return 'Say why the document is rejected.';
        }
        $this->db->where('id', (int) $doc->id)->update(db_prefix() . 'eptw_documents', [
            'review_status' => $decision, 'reviewed_by' => (int) eptw_me()['staff_id'], 'reviewed_at' => date('Y-m-d H:i:s'), 'review_note' => substr(trim((string) $note), 0, 255),
        ]);
        $this->event($permit->id, $decision === 'verified' ? 'doc_verified' : 'doc_rejected', $permit->status, $permit->status,
            (eptw_document_types()[$doc->doc_type] ?? $doc->doc_type) . ': ' . $doc->original_name . (trim((string) $note) !== '' ? ' — ' . trim((string) $note) : ''));
        $this->sync_docs_complete($permit, 'All closure documents uploaded and verified');

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
        $this->sync_docs_complete($permit, 'All closure documents present');

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

    /**
     * Shift revalidation. "Conditions unchanged" and "gas test" are separate
     * answers; where the permit needs gas testing the repeated test is
     * confirmed (recorded here, or on the paper sheet that may be uploaded
     * with it). An expired permit is not revalidated — it is extended.
     */
    public function add_revalidation($permit, array $in)
    {
        if (!eptw_can('status') && !in_array(eptw_role(), ['area_authority', 'hse'], true)) {
            return 'Only the Coordinator, Area Authority or HSE can revalidate a shift.';
        }
        if (!in_array($permit->status, eptw_live_statuses(), true) || $permit->status === 'expired') {
            return $permit->status === 'expired' ? 'This permit has expired — extend it instead of revalidating.' : 'Only a live permit can be revalidated.';
        }
        if (($in['conditions_ok'] ?? '') !== 'yes') {
            return ($in['conditions_ok'] ?? '') === 'no'
                ? 'Conditions have changed: the shift cannot be revalidated. Suspend the permit, or raise a new one for the changed scope.'
                : 'Confirm whether the site conditions are unchanged.';
        }
        $gas = (string) ($in['gas_test'] ?? '');
        if ($permit->gas_test_required && !in_array($gas, ['done', 'paper'], true)) {
            return 'This permit requires gas testing: confirm the repeated gas test for this shift.';
        }
        if (!in_array($gas, ['done', 'paper', 'not_required'], true)) {
            $gas = 'not_required';
        }
        $names = [
            'area_authority' => substr(trim((string) ($in['area_authority'] ?? '')), 0, 150),
            'issuer'         => substr(trim((string) ($in['issuer'] ?? '')), 0, 150),
            'hse'            => substr(trim((string) ($in['hse'] ?? '')), 0, 150),
        ];
        if (eptw_sod('reval_distinct')) {
            $filled = array_filter(array_map('mb_strtolower', $names));
            if (count($filled) !== count(array_unique($filled))) {
                if (($err = $this->sod_check($permit, 'reval_distinct', (string) ($in['notes'] ?? ''))) !== null) {
                    return $err;
                }
            }
        }
        $this->db->insert(db_prefix() . 'eptw_revalidations', [
            'permit_id'      => (int) $permit->id,
            'shift'          => isset(eptw_shifts()[$in['shift'] ?? '']) ? $in['shift'] : 'day',
            'from_at'        => $this->parse_dt($in['from_at'] ?? ''),
            'to_at'          => $this->parse_dt($in['to_at'] ?? ''),
            'area_authority' => $names['area_authority'],
            'issuer'         => $names['issuer'],
            'hse'            => $names['hse'],
            'conditions_ok'  => 1,
            'gas_test'       => $gas,
            'gas_test_ok'    => in_array($gas, ['done', 'paper'], true) ? 1 : 0,
            'notes'          => substr(trim((string) ($in['notes'] ?? '')), 0, 255),
            'created_by'     => (int) eptw_me()['staff_id'],
            'created_at'     => date('Y-m-d H:i:s'),
        ]);
        $gas_text = ['done' => 'gas test repeated', 'paper' => 'gas test on paper sheet', 'not_required' => 'gas test not required'][$gas];
        $this->event($permit->id, 'revalidated', $permit->status, $permit->status, ucfirst($in['shift'] ?? 'day') . ' shift ' . eptw_dt($this->parse_dt($in['from_at'] ?? '')) . ' → ' . eptw_dt($this->parse_dt($in['to_at'] ?? '')) . ' — conditions unchanged, ' . $gas_text);

        return true;
    }

    public function save_toolbox($permit, array $in)
    {
        if (!eptw_can('upload') || !$this->can_view($permit)) {
            return 'You cannot record the toolbox talk on this permit.';
        }
        $names = (array) ($in['worker_name'] ?? []);
        $ids   = (array) ($in['worker_id'] ?? []);
        $acks  = (array) ($in['worker_ack'] ?? []);
        $rows  = [];
        foreach ($names as $i => $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }
            $rows[] = ['name' => substr($name, 0, 100), 'id' => substr(trim((string) ($ids[$i] ?? '')), 0, 40), 'ack' => !empty($acks[$i]) ? 1 : 0];
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

    /* ═══════════════════════════ Expiry & cron ══════════════════════ */

    /**
     * Validity ended → EXPIRED / work not authorized, at once and for every
     * open status. Runs on every ePTW page load as well as on the cron, so a
     * permit never shows as Active after its end time. It resumes only
     * through an approved extension.
     */
    public function expire_due()
    {
        $now  = date('Y-m-d H:i:s');
        $rows = $this->db->select('id')->where_in('status', eptw_expirable_statuses())->where('end_at <', $now)->where('end_at IS NOT NULL', null, false)
            ->limit(200)->get(db_prefix() . 'eptw_permits')->result();
        if (!count($rows)) {
            return 0;
        }
        $this->system_actor = true;
        foreach ($rows as $row) {
            $permit = $this->get($row->id);
            if (!$permit || !in_array($permit->status, eptw_expirable_statuses(), true)) {
                continue;
            }
            $this->set_status($permit, 'expired', 'expired', 'Validity ended ' . eptw_dt($permit->end_at) . ' while ' . eptw_status_label($permit->status) . '. Work is not authorized until an extension is approved.', [
                'expired_at' => $now, 'expiry_notified' => 1,
            ]);
            $this->notify_staff($permit, [(int) $permit->engineer_id, (int) $permit->coordinator_id, (int) $permit->area_authority_id, (int) $permit->hse_officer_id], 'eptw_not_expired', [$permit->permit_no],
                'Permit expired — work not authorized: ' . $permit->permit_no, 'Permit ' . $permit->permit_no . ' passed its end time (' . eptw_dt($permit->end_at) . '). Work under it is no longer authorized. Request an extension or close the permit.');
        }
        $this->system_actor = false;

        return count($rows);
    }

    public function cron_pass()
    {
        $now = date('Y-m-d H:i:s');
        $p   = db_prefix();

        $this->expire_due();

        // Optional monitoring mode: issued → active when the planned start arrives.
        if (eptw_opt('eptw_auto_activate') === '1') {
            $this->system_actor = true;
            foreach ($this->db->where('status', 'issued')->where('start_at <=', $now)->where('end_at >', $now)->get($p . 'eptw_permits')->result() as $row) {
                $permit = $this->get($row->id);
                $this->activate($permit, 'Planned start time reached', true);
            }
            $this->system_actor = false;
        }

        // Expiring soon — once per permit (reset when extended), in the permit type's own warning window.
        $this->db->select('x.id')->from($p . 'eptw_permits x')->where_in('x.status', eptw_working_statuses())->where('x.expiry_notified', 0)
            ->where('x.end_at >=', $now)->where(eptw_expiring_sql($now), null, false);
        foreach ($this->db->get()->result() as $row) {
            $permit = $this->get($row->id);
            $this->db->where('id', $row->id)->update($p . 'eptw_permits', ['expiry_notified' => 1]);
            $this->notify_staff($permit, [(int) $permit->engineer_id, (int) $permit->coordinator_id, (int) $permit->area_authority_id], 'eptw_not_expiring', [$permit->permit_no, eptw_time_until($permit->end_at)],
                'Permit expiring soon: ' . $permit->permit_no, 'Permit ' . $permit->permit_no . ' expires ' . eptw_time_until($permit->end_at) . ' (' . eptw_dt($permit->end_at) . '). Request an extension if work continues.');
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
