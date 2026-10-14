<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * ePTW — everything read-only and aggregated: dashboard cards, chart data,
 * the report tables and the register export.
 */
class Eptw_reports_model extends App_Model
{
    /** Dashboard scope: project, area, contractor, type, shift (+ status for charts and lists). */
    private $filters = [];

    public function set_filters(array $f)
    {
        $this->filters = $f;

        return $this;
    }

    /** Scope every aggregate to the projects the viewer may see, and to the dashboard filters. */
    private function scoped($alias = 'x', $with_status = false)
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
        foreach (['project' => 'project_id', 'area' => 'area_id', 'contractor' => 'contractor_id', 'type' => 'permit_type_id'] as $key => $col) {
            if (!empty($this->filters[$key])) {
                $this->db->where($alias . '.' . $col, (int) $this->filters[$key]);
            }
        }
        if (!empty($this->filters['shift']) && isset(eptw_shifts()[$this->filters['shift']])) {
            $this->db->where($alias . '.shift', (string) $this->filters['shift']);
        }
        if ($with_status && !empty($this->filters['status'])) {
            $this->db->where_in($alias . '.status', array_map('trim', explode(',', (string) $this->filters['status'])));
        }
    }

    private function count_where(callable $apply)
    {
        $this->scoped();
        $apply($this->db);

        return (int) $this->db->count_all_results(db_prefix() . 'eptw_permits x');
    }

    /**
     * KPI cards — live state "as of now". Each key is one precisely defined
     * set (see kpi_definitions()); the register view of the same name lists
     * exactly the permits counted.
     */
    public function cards()
    {
        $now   = date('Y-m-d H:i:s');
        $today = date('Y-m-d');

        return [
            'today' => $this->count_where(function ($db) use ($today) {
                $db->group_start()->where('DATE(x.created_at)', $today)->or_where('DATE(x.issued_at)', $today)
                    ->or_group_start()->where('x.start_at <=', $today . ' 23:59:59')->where('x.end_at >=', $today . ' 00:00:00')->where_in('x.status', eptw_live_statuses())->group_end()
                    ->group_end();
            }),
            'live'         => $this->count_where(function ($db) { $db->where_in('x.status', eptw_live_statuses()); }),
            'active'       => $this->count_where(function ($db) use ($now) { $db->where_in('x.status', eptw_working_statuses())->where('x.end_at >=', $now); }),
            'issued'       => $this->count_where(function ($db) { $db->where('x.status', 'issued'); }),
            'pending'      => $this->count_where(function ($db) { $db->where_in('x.status', eptw_pending_statuses()); }),
            'suspended'    => $this->count_where(function ($db) { $db->where('x.status', 'suspended'); }),
            'on_hold'      => $this->count_where(function ($db) { $db->where_in('x.status', eptw_hold_statuses()); }),
            'simops_hold'  => $this->count_where(function ($db) { $db->where('x.status', 'on_hold_simops'); }),
            'expiring'     => $this->count_where(function ($db) use ($now) { $db->where_in('x.status', eptw_working_statuses())->where('x.end_at >=', $now)->where(eptw_expiring_sql($now), null, false); }),
            'expired'      => $this->count_where(function ($db) use ($now) {
                $db->group_start()->where('x.status', 'expired')->or_group_start()->where_in('x.status', eptw_working_statuses())->where('x.end_at <', $now)->group_end()->group_end();
            }),
            'overdue'      => $this->count_where(function ($db) {
                $db->where('x.status', 'expired')->where('x.end_at <', date('Y-m-d H:i:s', time() - max(1, (int) eptw_opt('eptw_overdue_hours')) * 3600));
            }),
            'docs_pending' => $this->count_where(function ($db) { $db->where('x.status', 'closed_docs_pending'); }),
            'extensions'   => $this->count_where(function ($db) { $db->where('(SELECT COUNT(*) FROM ' . db_prefix() . 'eptw_extensions ex WHERE ex.permit_id = x.id AND ex.status = \'pending\') >', 0, false); }),
            'drafts'       => $this->count_where(function ($db) { $db->where_in('x.status', ['draft', 'returned']); }),
            'high_risk'    => $this->count_where(function ($db) { $db->where('x.high_risk', 1)->where_in('x.status', eptw_live_statuses()); }),
            'simops'       => $this->count_where(function ($db) { $db->where('x.simops_flag', 1)->where_in('x.status', eptw_live_statuses()); }),
            'month'        => $this->count_where(function ($db) { $db->where('x.issued_at >=', date('Y-m-01 00:00:00')); }),
            'closed_month' => $this->count_where(function ($db) { $db->where('x.closed_at >=', date('Y-m-01 00:00:00')); }),
        ];
    }

    /** What each KPI counts — printed under the card so nobody has to infer it. */
    public function kpi_definitions()
    {
        $docs = eptw_opt('eptw_docs_review') === '1' ? 'Closed, a mandatory document is missing or not yet verified' : 'Closed, a mandatory document is not uploaded yet';

        return [
            'live'         => 'Has a permit number and is not closed: issued, active, on hold, suspended or expired',
            'active'       => 'Active or Active – Extended, inside validity — the only permits under which work is authorized',
            'issued'       => 'Number issued, start of work not yet confirmed — work not authorized',
            'on_hold'      => 'On hold, including holds for a SIMOPS conflict — work not authorized',
            'suspended'    => 'Stopped for a safety reason (weather, gas, unsafe condition…) — work not authorized',
            'expired'      => 'Validity ended without an approved extension — work not authorized',
            'expiring'     => 'Active permits ending within their warning window',
            'pending'      => 'Submitted, waiting for reviews or the permit number',
            'extensions'   => 'Extension requested, waiting for the issuer to revalidate and approve',
            'docs_pending' => $docs,
            'drafts'       => 'Drafts and permits returned for correction',
        ];
    }

    /**
     * Open high-risk permits grouped by type, with the status breakdown spelt
     * out and the reason they are high risk (type classification vs. assessed).
     */
    public function high_risk_panel()
    {
        $p = db_prefix();
        $this->db->select('t.id, t.name, t.code, t.icon, t.color, t.high_risk AS type_high_risk, COUNT(x.id) AS n,
                SUM(CASE WHEN x.status IN ("active","active_extended") THEN 1 ELSE 0 END) AS s_active,
                SUM(CASE WHEN x.status = "issued" THEN 1 ELSE 0 END) AS s_issued,
                SUM(CASE WHEN x.status IN ("on_hold","on_hold_simops") THEN 1 ELSE 0 END) AS s_on_hold,
                SUM(CASE WHEN x.status = "suspended" THEN 1 ELSE 0 END) AS s_suspended,
                SUM(CASE WHEN x.status = "expired" THEN 1 ELSE 0 END) AS s_expired', false)
            ->from($p . 'eptw_permits x')->join($p . 'eptw_permit_types t', 't.id = x.permit_type_id')
            ->where('x.high_risk', 1)->where_in('x.status', eptw_live_statuses());
        $this->scoped();

        return $this->db->group_by('t.id')->order_by('n', 'desc')->get()->result();
    }

    public function simops_panel()
    {
        $p = db_prefix();
        $this->db->select('x.id, x.permit_no, x.work_title, x.status, x.simops_notes, x.simops_ack_at, x.start_at, x.end_at, t.name AS type_name, t.color AS type_color, a.name AS area_name, pr.name AS project_name')
            ->from($p . 'eptw_permits x')
            ->join($p . 'eptw_permit_types t', 't.id = x.permit_type_id', 'left')
            ->join($p . 'eptw_areas a', 'a.id = x.area_id', 'left')
            ->join($p . 'eptw_projects pr', 'pr.id = x.project_id', 'left')
            ->where('x.simops_flag', 1)->where_in('x.status', eptw_live_statuses());
        $this->scoped();

        return $this->db->order_by('x.status', 'desc')->limit(12)->get()->result();
    }

    /**
     * Chart series for the dashboard. Created-in-period charts use the
     * date range ($from/$to, default last 30 days); the status pipeline is
     * the current state of every permit in scope.
     */
    public function charts($from = '', $to = '')
    {
        $p     = db_prefix();
        $since = $from !== '' ? date('Y-m-d 00:00:00', strtotime($from)) : date('Y-m-d 00:00:00', strtotime('-30 days'));
        $until = $to !== '' ? date('Y-m-d 23:59:59', strtotime($to)) : date('Y-m-d 23:59:59');
        $out   = [];

        $this->db->select('t.id AS k, t.name AS label, t.color, COUNT(x.id) AS n')->from($p . 'eptw_permits x')
            ->join($p . 'eptw_permit_types t', 't.id = x.permit_type_id', 'left')->where('x.created_at >=', $since)->where('x.created_at <=', $until)->where('x.status !=', 'cancelled');
        $this->scoped('x', true);
        $out['by_type'] = $this->db->group_by('t.id')->order_by('n', 'desc')->limit(12)->get()->result();

        $this->db->select('x.contractor_id AS k, COALESCE(c.name, "— no contractor —") AS label, COUNT(x.id) AS n', false)->from($p . 'eptw_permits x')
            ->join($p . 'eptw_contractors c', 'c.id = x.contractor_id', 'left')->where('x.created_at >=', $since)->where('x.created_at <=', $until)->where('x.status !=', 'cancelled');
        $this->scoped('x', true);
        $out['by_contractor'] = $this->db->group_by('x.contractor_id')->order_by('n', 'desc')->limit(12)->get()->result();

        $this->db->select('x.area_id AS k, x.project_id AS pk, CONCAT(COALESCE(pr.code, "?"), " · ", COALESCE(a.name, "— no area —")) AS label, COUNT(x.id) AS n', false)->from($p . 'eptw_permits x')
            ->join($p . 'eptw_areas a', 'a.id = x.area_id', 'left')->join($p . 'eptw_projects pr', 'pr.id = x.project_id', 'left')
            ->where('x.created_at >=', $since)->where('x.created_at <=', $until)->where('x.status !=', 'cancelled');
        $this->scoped('x', true);
        $out['by_area'] = $this->db->group_by('x.project_id, x.area_id')->order_by('n', 'desc')->limit(12)->get()->result();

        // Full pipeline in workflow order, zeros included, so nothing hides.
        $this->db->select('x.status AS label, COUNT(x.id) AS n')->from($p . 'eptw_permits x');
        $this->scoped();
        $counts = [];
        foreach ($this->db->group_by('x.status')->get()->result() as $row) {
            $counts[$row->label] = (int) $row->n;
        }
        $this->db->where('(SELECT COUNT(*) FROM ' . $p . 'eptw_extensions ex WHERE ex.permit_id = x.id AND ex.status = \'pending\') >', 0, false);
        $ext = $this->count_where(function () {});
        $pipeline = [];
        foreach (['draft', 'returned', 'number_requested', 'under_review', 'issued', 'active', 'active_extended', 'on_hold', 'on_hold_simops', 'suspended', 'expired', 'extension_requested', 'closed_docs_pending', 'closed', 'cancelled', 'archived'] as $key) {
            $pipeline[] = (object) [
                'key'   => $key,
                'label' => $key === 'extension_requested' ? 'Extension requested' : eptw_status_label($key),
                'n'     => $key === 'extension_requested' ? $ext : ($counts[$key] ?? 0),
            ];
        }
        $out['by_status'] = $pipeline;

        // Daily issued vs closed for the trend line.
        $this->db->select('DATE(x.issued_at) AS d, COUNT(x.id) AS n')->from($p . 'eptw_permits x')->where('x.issued_at >=', $since)->where('x.issued_at <=', $until);
        $this->scoped();
        $issued = $this->db->group_by('DATE(x.issued_at)')->get()->result();
        $this->db->select('DATE(x.closed_at) AS d, COUNT(x.id) AS n')->from($p . 'eptw_permits x')->where('x.closed_at >=', $since)->where('x.closed_at <=', $until);
        $this->scoped();
        $closed = $this->db->group_by('DATE(x.closed_at)')->get()->result();

        $trend = [];
        $days  = min(366, max(1, (int) round((strtotime($until) - strtotime($since)) / 86400)));
        for ($i = $days - 1; $i >= 0; $i--) {
            $trend[date('Y-m-d', strtotime($until) - $i * 86400)] = ['issued' => 0, 'closed' => 0];
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
        $out['since'] = $since;
        $out['until'] = $until;

        return $out;
    }

    /**
     * Corporate roll-up: one row per project in the viewer's scope, live
     * state as of now plus this month's throughput.
     */
    public function corporate()
    {
        $p     = db_prefix();
        $now   = $this->db->escape(date('Y-m-d H:i:s'));
        $month = $this->db->escape(date('Y-m-01 00:00:00'));
        $live  = "'" . implode("','", eptw_live_statuses()) . "'";
        $this->db->select("pr.id, pr.code, pr.name, pr.client_name, pr.active,
                SUM(CASE WHEN x.status IN ($live) THEN 1 ELSE 0 END) AS live,
                SUM(CASE WHEN x.status IN ('active','active_extended') AND x.end_at >= $now THEN 1 ELSE 0 END) AS active,
                SUM(CASE WHEN x.status = 'issued' THEN 1 ELSE 0 END) AS issued,
                SUM(CASE WHEN x.status IN ('on_hold','on_hold_simops') THEN 1 ELSE 0 END) AS on_hold,
                SUM(CASE WHEN x.status = 'suspended' THEN 1 ELSE 0 END) AS suspended,
                SUM(CASE WHEN x.status = 'expired' THEN 1 ELSE 0 END) AS expired,
                SUM(CASE WHEN x.status IN ('number_requested','under_review') THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN x.high_risk = 1 AND x.status IN ($live) THEN 1 ELSE 0 END) AS high_risk,
                SUM(CASE WHEN x.simops_flag = 1 AND x.status IN ($live) THEN 1 ELSE 0 END) AS simops,
                SUM(CASE WHEN x.status = 'closed_docs_pending' THEN 1 ELSE 0 END) AS docs_pending,
                SUM(CASE WHEN x.issued_at >= $month THEN 1 ELSE 0 END) AS issued_month,
                SUM(CASE WHEN x.closed_at >= $month THEN 1 ELSE 0 END) AS closed_month,
                COUNT(x.id) AS total", false)
            ->from($p . 'eptw_projects pr')
            ->join($p . 'eptw_permits x', 'x.project_id = pr.id', 'left');
        $scope = eptw_scope();
        if ($scope !== null) {
            $this->db->where_in('pr.id', count($scope) ? $scope : [0]);
        }

        return $this->db->group_by('pr.id')->order_by('pr.active', 'desc')->order_by('pr.name', 'asc')->get()->result();
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
                return ['kind' => 'list', 'rows' => $list(function ($db) use ($now) {
                    $db->group_start()->where('x.status', 'expired')->or_group_start()->where_in('x.status', eptw_working_statuses())->where('x.end_at <', $now)->group_end()->group_end();
                })];
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
            'expired'      => 'Expired permits — work not authorized',
            'docs_pending' => 'Closed – documents pending upload',
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

    /** The register as rows for CSV — the agreed register columns first, then the system's own. */
    public function export_rows(array $rows)
    {
        $out   = [[
            'Permit Number', 'Permit Type', 'Project', 'Area', 'Contractor', 'Engineer / Initiator', 'Area Authority', 'HSE Officer', 'Start Date', 'End Date', 'Status',
            'Work Authorization', 'Extension Count', 'High Risk Flag', 'High Risk Reason', 'SIMOPS Conflict Flag', 'Closure Date', 'Attachment Status',
            'Work Order No', 'Subcontractor', 'Work Title', 'Work Description', 'Location', 'Equipment Tag No', 'Shift', 'Workers', 'Permit Issuer', 'Permit Holder', 'Supervisor', 'RA/JSA Ref No',
            'Hazards', 'Control Measures', 'PPE Required', 'Isolation Applicable', 'Linked Isolation Permit', 'Isolation Type', 'Isolation Certificate No', 'LOTO Applied', 'Gas Test Required', 'Gas Tests Recorded',
            'Weather Condition', 'Remarks', 'Original End Date', 'Risk Level', 'Issued At', 'Closed By', 'Work Completed', 'Area Restored', 'Isolation Removed', 'Auto Flags', 'Source',
        ]];
        $yn = function ($v) { return ['yes' => 'Yes', 'no' => 'No', 'na' => 'N/A'][$v] ?? ''; };
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
            $flags   = array_map(function ($f) { return $f[1]; }, (array) ($r->auto_flags ?? []));
            $attach  = (int) $r->doc_count . ' file(s)' . ($r->req_docs_total ? '; required ' . (int) $r->req_docs_have . '/' . (int) $r->req_docs_total . ((int) $r->req_docs_have >= (int) $r->req_docs_total ? ' complete' : ' — missing') : '');
            $out[] = [
                $r->permit_no ?: '(draft #' . $r->id . ')', $r->type_name, $r->project_name, $r->area_name, $r->contractor_name, $r->engineer_name, $r->area_authority_name, $r->hse_officer_name, $r->start_at, $r->end_at, eptw_status_label($r->status),
                $r->work_auth['ok'] ? 'Authorized' : 'NOT authorized', $r->extension_count, $r->high_risk ? 'Yes' : 'No', $r->high_risk ? eptw_risk_reason_text($r) : '', $r->simops_flag ? 'Yes' : 'No', $r->closed_at, $attach,
                $r->work_order, $r->subcontractor, $r->work_title, $r->work_description, $r->location, $r->equipment_tag, ucfirst($r->shift), $r->workers_count, $r->coordinator_name, $r->permit_holder, $r->supervisor, $r->ra_ref,
                implode('; ', $hazards), implode('; ', $controls), implode('; ', (array) $r->ppe), $yn($r->isolation_applicable) ?: ($r->isolation_required ? 'Yes' : ''), $r->isolation_permit_no, $r->isolation_type, $r->isolation_cert_no, $r->loto_applied ? 'Yes' : 'No', $r->gas_test_required ? 'Yes' : 'No', $r->gas_test_count,
                $r->weather, $r->remarks, $r->original_end_at, ucfirst($r->risk_level), $r->issued_at, eptw_staff_name($r->closed_by), $yn(eptw_closure_value($r->closure, 'work_completed')),
                $yn(eptw_closure_value($r->closure, 'area_restored')), $yn(eptw_closure_value($r->closure, 'isolation_removed')), implode('; ', $flags), $r->source,
            ];
        }

        return $out;
    }
}
