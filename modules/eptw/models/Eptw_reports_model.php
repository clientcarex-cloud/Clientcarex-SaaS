<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * ePTW — everything read-only and aggregated: dashboard cards, chart data,
 * the report tables and the register export.
 */
class Eptw_reports_model extends App_Model
{
    /** Scope every aggregate to the projects the viewer may see. */
    private function scoped($alias = 'x')
    {
        $scope = eptw_scope();
        if ($scope !== null) {
            $this->db->where_in($alias . '.project_id', count($scope) ? $scope : [0]);
        }
        if (!eptw_can('view_all')) {
            $me = eptw_me()['staff_id'];
            $this->db->group_start()
                ->where($alias . '.engineer_id', $me)->or_where($alias . '.created_by', $me)
                ->or_where($alias . '.area_authority_id', $me)->or_where($alias . '.hse_officer_id', $me)->or_where($alias . '.coordinator_id', $me)
                ->group_end();
        }
    }

    private function count_where(callable $apply)
    {
        $this->scoped();
        $apply($this->db);

        return (int) $this->db->count_all_results(db_prefix() . 'eptw_permits x');
    }

    public function cards()
    {
        $now     = date('Y-m-d H:i:s');
        $today   = date('Y-m-d');
        $horizon = date('Y-m-d H:i:s', time() + (int) eptw_opt('eptw_expiring_hours') * 3600);

        return [
            'today' => $this->count_where(function ($db) use ($today) {
                $db->group_start()->where('DATE(x.created_at)', $today)->or_where('DATE(x.issued_at)', $today)
                    ->or_group_start()->where('x.start_at <=', $today . ' 23:59:59')->where('x.end_at >=', $today . ' 00:00:00')->where_in('x.status', eptw_live_statuses())->group_end()
                    ->group_end();
            }),
            'active'       => $this->count_where(function ($db) { $db->where_in('x.status', eptw_working_statuses()); }),
            'issued'       => $this->count_where(function ($db) { $db->where('x.status', 'issued'); }),
            'pending'      => $this->count_where(function ($db) { $db->where_in('x.status', eptw_pending_statuses()); }),
            'suspended'    => $this->count_where(function ($db) { $db->where_in('x.status', ['suspended', 'on_hold', 'on_hold_simops']); }),
            'expiring'     => $this->count_where(function ($db) use ($now, $horizon) { $db->where_in('x.status', eptw_working_statuses())->where('x.end_at >=', $now)->where('x.end_at <=', $horizon); }),
            'expired'      => $this->count_where(function ($db) use ($now) { $db->where_in('x.status', eptw_working_statuses())->where('x.end_at <', $now); }),
            'docs_pending' => $this->count_where(function ($db) { $db->where('x.status', 'closed_docs_pending'); }),
            'extensions'   => $this->count_where(function ($db) { $db->where('(SELECT COUNT(*) FROM ' . db_prefix() . 'eptw_extensions ex WHERE ex.permit_id = x.id AND ex.status = \'pending\') >', 0, false); }),
            'drafts'       => $this->count_where(function ($db) { $db->where_in('x.status', ['draft', 'returned']); }),
            'high_risk'    => $this->count_where(function ($db) { $db->where('x.high_risk', 1)->where_in('x.status', eptw_live_statuses()); }),
            'simops'       => $this->count_where(function ($db) { $db->where('x.simops_flag', 1)->where_in('x.status', eptw_live_statuses()); }),
            'month'        => $this->count_where(function ($db) { $db->where('x.issued_at >=', date('Y-m-01 00:00:00')); }),
            'closed_month' => $this->count_where(function ($db) { $db->where('x.closed_at >=', date('Y-m-01 00:00:00')); }),
        ];
    }

    /** Live high-risk permits, grouped by permit type, for the dashboard panel. */
    public function high_risk_panel()
    {
        $p = db_prefix();
        $this->db->select('t.id, t.name, t.code, t.icon, t.color, COUNT(x.id) AS n,
                SUM(CASE WHEN x.status IN ("active","active_extended") THEN 1 ELSE 0 END) AS working')
            ->from($p . 'eptw_permits x')->join($p . 'eptw_permit_types t', 't.id = x.permit_type_id')
            ->where('x.high_risk', 1)->where_in('x.status', eptw_live_statuses());
        $this->scoped();

        return $this->db->group_by('t.id')->order_by('n', 'desc')->get()->result();
    }

    public function simops_panel()
    {
        $p = db_prefix();
        $this->db->select('x.id, x.permit_no, x.work_title, x.status, x.simops_notes, x.start_at, x.end_at, t.name AS type_name, t.color AS type_color, a.name AS area_name, pr.name AS project_name')
            ->from($p . 'eptw_permits x')
            ->join($p . 'eptw_permit_types t', 't.id = x.permit_type_id', 'left')
            ->join($p . 'eptw_areas a', 'a.id = x.area_id', 'left')
            ->join($p . 'eptw_projects pr', 'pr.id = x.project_id', 'left')
            ->where('x.simops_flag', 1)->where_in('x.status', eptw_live_statuses());
        $this->scoped();

        return $this->db->order_by('x.status', 'desc')->limit(12)->get()->result();
    }

    /** Chart series for the dashboard: by type, contractor, area, status. */
    public function charts($days = 30)
    {
        $p     = db_prefix();
        $since = date('Y-m-d 00:00:00', strtotime('-' . (int) $days . ' days'));
        $out   = [];

        $this->db->select('t.name AS label, t.color, COUNT(x.id) AS n')->from($p . 'eptw_permits x')
            ->join($p . 'eptw_permit_types t', 't.id = x.permit_type_id', 'left')->where('x.created_at >=', $since)->where('x.status !=', 'cancelled');
        $this->scoped();
        $out['by_type'] = $this->db->group_by('t.id')->order_by('n', 'desc')->limit(12)->get()->result();

        $this->db->select('COALESCE(c.name, "— no contractor —") AS label, COUNT(x.id) AS n')->from($p . 'eptw_permits x')
            ->join($p . 'eptw_contractors c', 'c.id = x.contractor_id', 'left')->where('x.created_at >=', $since)->where('x.status !=', 'cancelled');
        $this->scoped();
        $out['by_contractor'] = $this->db->group_by('x.contractor_id')->order_by('n', 'desc')->limit(10)->get()->result();

        $this->db->select('CONCAT(COALESCE(pr.code, "?"), " · ", COALESCE(a.name, "— no area —")) AS label, COUNT(x.id) AS n')->from($p . 'eptw_permits x')
            ->join($p . 'eptw_areas a', 'a.id = x.area_id', 'left')->join($p . 'eptw_projects pr', 'pr.id = x.project_id', 'left')
            ->where('x.created_at >=', $since)->where('x.status !=', 'cancelled');
        $this->scoped();
        $out['by_area'] = $this->db->group_by('x.project_id, x.area_id')->order_by('n', 'desc')->limit(10)->get()->result();

        $this->db->select('x.status AS label, COUNT(x.id) AS n')->from($p . 'eptw_permits x')->where_not_in('x.status', ['archived']);
        $this->scoped();
        $out['by_status'] = $this->db->group_by('x.status')->order_by('n', 'desc')->get()->result();

        // Daily issued vs closed for the trend line.
        $this->db->select('DATE(x.issued_at) AS d, COUNT(x.id) AS n')->from($p . 'eptw_permits x')->where('x.issued_at >=', $since);
        $this->scoped();
        $issued = $this->db->group_by('DATE(x.issued_at)')->get()->result();
        $this->db->select('DATE(x.closed_at) AS d, COUNT(x.id) AS n')->from($p . 'eptw_permits x')->where('x.closed_at >=', $since);
        $this->scoped();
        $closed = $this->db->group_by('DATE(x.closed_at)')->get()->result();

        $trend = [];
        for ($i = (int) $days; $i >= 0; $i--) {
            $trend[date('Y-m-d', strtotime('-' . $i . ' days'))] = ['issued' => 0, 'closed' => 0];
        }
        foreach ($issued as $row) {
            if (isset($trend[$row->d])) {
                $trend[$row->d]['issued'] = (int) $row->n;
            }
        }
        foreach ($closed as $row) {
            if (isset($trend[$row->d])) {
                $trend[$row->d]['closed'] = (int) $row->n;
            }
        }
        $out['trend'] = $trend;

        return $out;
    }

    /* ═══════════════════════════ Reports ════════════════════════════ */

    /**
     * Report tables. $from/$to bound on start_at for list reports and on
     * issued_at for the monthly statistics.
     */
    public function report($name, $from, $to)
    {
        $p    = db_prefix();
        $from = date('Y-m-d 00:00:00', strtotime($from));
        $to   = date('Y-m-d 23:59:59', strtotime($to));
        $now  = date('Y-m-d H:i:s');

        $list = function (callable $where) use ($p, $from, $to) {
            $this->db->select('x.*, pr.name AS project_name, pr.code AS project_code, a.name AS area_name, t.name AS type_name, t.color AS type_color, c.name AS contractor_name, CONCAT(e.firstname, " ", e.lastname) AS engineer_name')
                ->from($p . 'eptw_permits x')
                ->join($p . 'eptw_projects pr', 'pr.id = x.project_id', 'left')
                ->join($p . 'eptw_areas a', 'a.id = x.area_id', 'left')
                ->join($p . 'eptw_permit_types t', 't.id = x.permit_type_id', 'left')
                ->join($p . 'eptw_contractors c', 'c.id = x.contractor_id', 'left')
                ->join($p . 'staff e', 'e.staffid = x.engineer_id', 'left');
            $this->scoped();
            $where($this->db, $from, $to);

            return $this->db->order_by('x.start_at', 'desc')->limit(1000)->get()->result();
        };

        switch ($name) {
            case 'active':
                return ['kind' => 'list', 'rows' => $list(function ($db) { $db->where_in('x.status', eptw_working_statuses()); })];
            case 'suspended':
                return ['kind' => 'list', 'rows' => $list(function ($db) { $db->where_in('x.status', ['suspended', 'on_hold', 'on_hold_simops']); })];
            case 'expired':
                return ['kind' => 'list', 'rows' => $list(function ($db) use ($now) { $db->where_in('x.status', eptw_working_statuses())->where('x.end_at <', $now); })];
            case 'docs_pending':
                return ['kind' => 'list', 'rows' => $list(function ($db) { $db->where('x.status', 'closed_docs_pending'); })];
            case 'high_risk':
                return ['kind' => 'list', 'rows' => $list(function ($db, $from, $to) { $db->where('x.high_risk', 1)->where('x.start_at >=', $from)->where('x.start_at <=', $to)->where('x.status !=', 'cancelled'); })];
            case 'simops':
                return ['kind' => 'list', 'rows' => $list(function ($db, $from, $to) { $db->where('x.simops_flag', 1)->where('x.start_at >=', $from)->where('x.start_at <=', $to); })];
            case 'extensions':
                return ['kind' => 'list', 'rows' => $list(function ($db, $from, $to) { $db->where('x.extension_count >', 0)->where('x.start_at >=', $from)->where('x.start_at <=', $to); })];
            case 'all':
                return ['kind' => 'list', 'rows' => $list(function ($db, $from, $to) { $db->where('x.start_at >=', $from)->where('x.start_at <=', $to); })];

            case 'contractor':
                $this->db->select('COALESCE(c.name, "— no contractor —") AS label, COUNT(x.id) AS total,
                        SUM(CASE WHEN x.status IN ("active","active_extended") THEN 1 ELSE 0 END) AS active,
                        SUM(CASE WHEN x.status IN ("suspended","on_hold","on_hold_simops") THEN 1 ELSE 0 END) AS suspended,
                        SUM(CASE WHEN x.status IN ("closed","closed_docs_pending","archived") THEN 1 ELSE 0 END) AS closed,
                        SUM(x.high_risk) AS high_risk, SUM(x.extension_count) AS extensions, SUM(x.simops_flag) AS simops')
                    ->from($p . 'eptw_permits x')->join($p . 'eptw_contractors c', 'c.id = x.contractor_id', 'left')
                    ->where('x.start_at >=', $from)->where('x.start_at <=', $to)->where('x.status !=', 'cancelled');
                $this->scoped();

                return ['kind' => 'summary', 'rows' => $this->db->group_by('x.contractor_id')->order_by('total', 'desc')->get()->result()];

            case 'type':
                $this->db->select('t.name AS label, COUNT(x.id) AS total,
                        SUM(CASE WHEN x.status IN ("active","active_extended") THEN 1 ELSE 0 END) AS active,
                        SUM(CASE WHEN x.status IN ("suspended","on_hold","on_hold_simops") THEN 1 ELSE 0 END) AS suspended,
                        SUM(CASE WHEN x.status IN ("closed","closed_docs_pending","archived") THEN 1 ELSE 0 END) AS closed,
                        SUM(x.high_risk) AS high_risk, SUM(x.extension_count) AS extensions, SUM(x.simops_flag) AS simops')
                    ->from($p . 'eptw_permits x')->join($p . 'eptw_permit_types t', 't.id = x.permit_type_id', 'left')
                    ->where('x.start_at >=', $from)->where('x.start_at <=', $to)->where('x.status !=', 'cancelled');
                $this->scoped();

                return ['kind' => 'summary', 'rows' => $this->db->group_by('x.permit_type_id')->order_by('total', 'desc')->get()->result()];

            case 'area':
                $this->db->select('CONCAT(COALESCE(pr.name, "?"), " · ", COALESCE(a.name, "— no area —")) AS label, COUNT(x.id) AS total,
                        SUM(CASE WHEN x.status IN ("active","active_extended") THEN 1 ELSE 0 END) AS active,
                        SUM(CASE WHEN x.status IN ("suspended","on_hold","on_hold_simops") THEN 1 ELSE 0 END) AS suspended,
                        SUM(CASE WHEN x.status IN ("closed","closed_docs_pending","archived") THEN 1 ELSE 0 END) AS closed,
                        SUM(x.high_risk) AS high_risk, SUM(x.extension_count) AS extensions, SUM(x.simops_flag) AS simops')
                    ->from($p . 'eptw_permits x')->join($p . 'eptw_areas a', 'a.id = x.area_id', 'left')->join($p . 'eptw_projects pr', 'pr.id = x.project_id', 'left')
                    ->where('x.start_at >=', $from)->where('x.start_at <=', $to)->where('x.status !=', 'cancelled');
                $this->scoped();

                return ['kind' => 'summary', 'rows' => $this->db->group_by('x.project_id, x.area_id')->order_by('total', 'desc')->get()->result()];

            case 'monthly':
            default:
                $this->db->select('DATE_FORMAT(x.created_at, "%Y-%m") AS label, COUNT(x.id) AS total,
                        SUM(CASE WHEN x.issued_at IS NOT NULL THEN 1 ELSE 0 END) AS issued,
                        SUM(CASE WHEN x.status IN ("closed","closed_docs_pending","archived") THEN 1 ELSE 0 END) AS closed,
                        SUM(CASE WHEN x.status = "cancelled" THEN 1 ELSE 0 END) AS cancelled,
                        SUM(x.high_risk) AS high_risk, SUM(x.extension_count) AS extensions, SUM(x.simops_flag) AS simops,
                        SUM(CASE WHEN x.suspended_at IS NOT NULL THEN 1 ELSE 0 END) AS suspended')
                    ->from($p . 'eptw_permits x')->where('x.created_at >=', date('Y-m-01 00:00:00', strtotime('-11 months')));
                $this->scoped();

                return ['kind' => 'monthly', 'rows' => $this->db->group_by('DATE_FORMAT(x.created_at, "%Y-%m")')->order_by('label', 'desc')->get()->result()];
        }
    }

    public function report_names()
    {
        return [
            'monthly'      => 'Monthly permit statistics',
            'active'       => 'Active permits',
            'suspended'    => 'Suspended / on-hold permits',
            'expired'      => 'Expired permits (still active)',
            'docs_pending' => 'Documents pending upload',
            'high_risk'    => 'High-risk permit list',
            'simops'       => 'SIMOPS-flagged permits',
            'extensions'   => 'Extended permits',
            'contractor'   => 'Contractor permit summary',
            'type'         => 'Permit type summary',
            'area'         => 'Area summary',
            'all'          => 'All permits in period',
        ];
    }

    /* ═══════════════════════════ Export ═════════════════════════════ */

    /** The register as rows for CSV — the same columns as the Excel register, plus the system's own. */
    public function export_rows(array $rows)
    {
        $out   = [[
            'Permit ID', 'Permit Type', 'Work Order No', 'Project', 'Area', 'Company / Contractor', 'Subcontractor', 'Work Title', 'Work Description', 'Location', 'Equipment Tag No',
            'Start Date', 'End Date', 'Shift', 'Workers', 'Initiator', 'Area Authority', 'Permit Issuer', 'Permit Holder', 'Supervisor', 'HSE Officer', 'RA/JSA Ref No',
            'Hazards', 'Control Measures', 'PPE Required', 'Isolation Required', 'Isolation Type', 'Isolation Certificate No', 'LOTO Applied', 'Gas Test Required', 'Gas Tests Recorded',
            'Weather Condition', 'SIMOPS Conflict', 'Remarks', 'Attachments', 'Status', 'Extension Count', 'Risk Level', 'High Risk', 'Issued At', 'Closed By', 'Closure Date', 'Work Completed', 'Area Restored', 'Isolation Removed', 'Auto Flags', 'Source',
        ]];
        foreach ($rows as $r) {
            $hazards = [];
            foreach ((array) $r->hazards as $name => $v) {
                if ($v === 'yes') {
                    $hazards[] = $name;
                }
            }
            $hazards  = array_merge($hazards, (array) $r->extra_hazards);
            $controls = [];
            foreach ((array) $r->controls as $name => $c) {
                if (($c['v'] ?? '') === 'yes') {
                    $controls[] = $name;
                }
            }
            $flags = array_map(function ($f) { return $f[1]; }, (array) ($r->auto_flags ?? []));
            $out[] = [
                $r->permit_no ?: '(draft #' . $r->id . ')', $r->type_name, $r->work_order, $r->project_name, $r->area_name, $r->contractor_name, $r->subcontractor, $r->work_title, $r->work_description, $r->location, $r->equipment_tag,
                $r->start_at, $r->end_at, ucfirst($r->shift), $r->workers_count, $r->engineer_name, $r->area_authority_name, $r->coordinator_name, $r->permit_holder, $r->supervisor, $r->hse_officer_name, $r->ra_ref,
                implode('; ', $hazards), implode('; ', $controls), implode('; ', (array) $r->ppe), $r->isolation_required ? 'Yes' : 'No', $r->isolation_type, $r->isolation_cert_no, $r->loto_applied ? 'Yes' : 'No', $r->gas_test_required ? 'Yes' : 'No', $r->gas_test_count,
                $r->weather, $r->simops_flag ? 'Yes' : 'No', $r->remarks, $r->doc_count, eptw_status_label($r->status), $r->extension_count, ucfirst($r->risk_level), $r->high_risk ? 'Yes' : 'No', $r->issued_at, eptw_staff_name($r->closed_by), $r->closed_at,
                !empty($r->closure['work_completed']) ? 'Yes' : '', !empty($r->closure['area_restored']) || !empty($r->closure['area_clean']) ? 'Yes' : '', !empty($r->closure['isolation_removed']) ? 'Yes' : '', implode('; ', $flags), $r->source,
            ];
        }

        return $out;
    }
}
