<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * CCX Leads Reports Model
 *
 * Dedicated model for all lead report queries.
 * Each public method corresponds to one report, accepting common filters:
 *   $date_from  (Y-m-d)
 *   $date_to    (Y-m-d)
 *   $staff_id   (int)
 */
class Ccx_leads_reports_model extends App_Model
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('leads_model');
    }

    // ─── Report 1: Overall Leads Assigned ───────────────────────────────

    public function report_overall_leads_assigned($date_from = '', $date_to = '', $staff_id = '')
    {
        $this->db->select('
            s.staffid,
            CONCAT(s.firstname, " ", s.lastname) as staff_name,
            COUNT(l.id) as total_assigned
        ');
        $this->db->from(db_prefix() . 'leads as l');
        $this->db->join(db_prefix() . 'staff as s', 's.staffid = l.assigned', 'inner');
        $this->db->where('l.assigned >', 0);
        $this->_date_filter('l.dateadded', $date_from, $date_to);
        if (!empty($staff_id))
            $this->db->where('l.assigned', $staff_id);
        $this->db->group_by('s.staffid');
        $this->db->order_by('total_assigned', 'desc');
        return $this->db->get()->result_array();
    }

    // ─── Report 2: Overall Staff New Leads & Calls ──────────────────────

    public function report_staff_new_leads_calls($date_from = '', $date_to = '', $staff_id = '')
    {
        $this->db->select('staffid, CONCAT(firstname, " ", lastname) as staff_name');
        $this->db->where('active', 1);
        if (!empty($staff_id))
            $this->db->where('staffid', $staff_id);
        $staff = $this->db->get(db_prefix() . 'staff')->result_array();

        $result = [];
        foreach ($staff as $s) {
            $sid = $s['staffid'];

            $this->db->where('assigned', $sid);
            $this->_date_filter('dateadded', $date_from, $date_to);
            $new_leads = $this->db->count_all_results(db_prefix() . 'leads');

            $this->db->where('addedfrom', $sid);
            $this->db->where('rel_type', 'lead');
            $this->_date_filter('dateadded', $date_from, $date_to);
            $call_logs = $this->db->count_all_results(db_prefix() . 'notes');

            if ($new_leads > 0 || $call_logs > 0) {
                $result[] = [
                    'staffid' => $sid,
                    'staff_name' => $s['staff_name'],
                    'new_leads' => $new_leads,
                    'call_logs' => $call_logs,
                    'total' => $new_leads + $call_logs,
                ];
            }
        }
        return $result;
    }

    // ─── Report 3: Overall Missed Leads Calls ───────────────────────────

    public function report_missed_leads_calls($date_from = '', $date_to = '', $staff_id = '')
    {
        $this->db->select('
            l.id, l.name, l.phonenumber, l.email,
            l.dateadded, l.assigned,
            CONCAT(s.firstname, " ", s.lastname) as staff_name,
            ls.name as status_name,
            ls.color as status_color
        ');
        $this->db->from(db_prefix() . 'leads as l');
        $this->db->join(db_prefix() . 'staff as s', 's.staffid = l.assigned', 'left');
        $this->db->join(db_prefix() . 'leads_status as ls', 'ls.id = l.status', 'left');
        $this->db->where('(l.lastcontact IS NULL OR l.lastcontact = "0000-00-00 00:00:00")', null, false);
        $this->db->where('l.junk', 0);
        $this->db->where('l.lost', 0);
        $this->_date_filter('l.dateadded', $date_from, $date_to);
        if (!empty($staff_id))
            $this->db->where('l.assigned', $staff_id);
        $this->db->order_by('l.dateadded', 'desc');
        return $this->db->get()->result_array();
    }

    // ─── Report 4: Staff Wise Leads Call TAT Report ─────────────────────

    public function report_call_tat($date_from = '', $date_to = '', $staff_id = '')
    {
        $sub = '(SELECT rel_id, MIN(dateadded) as first_call FROM ' . db_prefix() . 'notes WHERE rel_type = "lead" GROUP BY rel_id)';

        $this->db->select('
            l.id, l.name, l.phonenumber,
            l.dateadded as lead_created,
            l.assigned,
            CONCAT(s.firstname, " ", s.lastname) as staff_name,
            fc.first_call,
            TIMESTAMPDIFF(MINUTE, l.dateadded, fc.first_call) as tat_minutes
        ');
        $this->db->from(db_prefix() . 'leads as l');
        $this->db->join(db_prefix() . 'staff as s', 's.staffid = l.assigned', 'left');
        $this->db->join($sub . ' as fc', 'fc.rel_id = l.id', 'left');
        $this->_date_filter('l.dateadded', $date_from, $date_to);
        if (!empty($staff_id))
            $this->db->where('l.assigned', $staff_id);
        $this->db->order_by('l.assigned', 'asc');
        $this->db->order_by('l.dateadded', 'desc');
        return $this->db->get()->result_array();
    }

    // ─── Report 5: Leads Follow-ups ─────────────────────────────────────

    public function report_leads_followups($date_from = '', $date_to = '', $staff_id = '')
    {
        $this->db->select('
            r.id as reminder_id,
            r.description as reminder_desc,
            r.date as reminder_date,
            r.isnotified,
            r.rel_id as lead_id,
            l.name as lead_name,
            l.phonenumber,
            CONCAT(s.firstname, " ", s.lastname) as staff_name,
            CONCAT(cr.firstname, " ", cr.lastname) as created_by,
            ls.name as lead_status
        ');
        $this->db->from(db_prefix() . 'reminders as r');
        $this->db->join(db_prefix() . 'leads as l', 'l.id = r.rel_id', 'inner');
        $this->db->join(db_prefix() . 'staff as s', 's.staffid = r.staff', 'left');
        $this->db->join(db_prefix() . 'staff as cr', 'cr.staffid = r.creator', 'left');
        $this->db->join(db_prefix() . 'leads_status as ls', 'ls.id = l.status', 'left');
        $this->db->where('r.rel_type', 'lead');
        $this->_date_filter('r.date', $date_from, $date_to);
        if (!empty($staff_id))
            $this->db->where('r.staff', $staff_id);
        $this->db->order_by('r.date', 'desc');
        return $this->db->get()->result_array();
    }

    // ─── Report 6: Overview of Leads by Staff ───────────────────────────

    public function report_leads_by_staff($date_from = '', $date_to = '', $staff_id = '')
    {
        // Get all statuses — guard against non-iterable return
        $statuses = [];
        if (isset($this->leads_model) && method_exists($this->leads_model, 'get_status')) {
            $raw = $this->leads_model->get_status();
            if (is_array($raw)) {
                $statuses = $raw;
            }
        }

        $this->db->select('staffid, CONCAT(firstname, " ", lastname) as staff_name');
        $this->db->where('active', 1);
        if (!empty($staff_id))
            $this->db->where('staffid', $staff_id);
        $all_staff = $this->db->get(db_prefix() . 'staff')->result_array();

        if (!is_array($all_staff)) {
            return [];
        }

        $result = [];
        foreach ($all_staff as $s) {
            $sid = $s['staffid'];
            $row = [
                'staffid' => $sid,
                'staff_name' => $s['staff_name'],
                'total_leads' => 0,
                'contacted' => 0,
                'not_contacted' => 0,
                'junk' => 0,
                'lost' => 0,
                'statuses' => [],
            ];

            $this->db->where('assigned', $sid);
            $this->_date_filter('dateadded', $date_from, $date_to);
            $row['total_leads'] = (int) $this->db->count_all_results(db_prefix() . 'leads');
            if ($row['total_leads'] == 0)
                continue;

            $this->db->where('assigned', $sid);
            $this->db->where('lastcontact IS NOT NULL', null, false);
            $this->db->where('lastcontact !=', '0000-00-00 00:00:00');
            $this->_date_filter('dateadded', $date_from, $date_to);
            $row['contacted'] = (int) $this->db->count_all_results(db_prefix() . 'leads');
            $row['not_contacted'] = $row['total_leads'] - $row['contacted'];

            $this->db->where('assigned', $sid);
            $this->db->where('junk', 1);
            $this->_date_filter('dateadded', $date_from, $date_to);
            $row['junk'] = (int) $this->db->count_all_results(db_prefix() . 'leads');

            $this->db->where('assigned', $sid);
            $this->db->where('lost', 1);
            $this->_date_filter('dateadded', $date_from, $date_to);
            $row['lost'] = (int) $this->db->count_all_results(db_prefix() . 'leads');

            foreach ($statuses as $status) {
                if (!isset($status['id']))
                    continue;
                $this->db->where('assigned', $sid);
                $this->db->where('status', $status['id']);
                $this->db->where('junk', 0);
                $this->db->where('lost', 0);
                $this->_date_filter('dateadded', $date_from, $date_to);
                $cnt = (int) $this->db->count_all_results(db_prefix() . 'leads');
                if ($cnt > 0) {
                    $row['statuses'][] = [
                        'name' => isset($status['name']) ? $status['name'] : 'Unknown',
                        'count' => $cnt,
                        'color' => isset($status['color']) ? $status['color'] : '#6b7280',
                    ];
                }
            }
            $result[] = $row;
        }

        usort($result, function ($a, $b) {
            return $b['total_leads'] - $a['total_leads'];
        });
        return $result;
    }

    // ─── Report 7: Complete Overview of Whole Leads ─────────────────────

    public function report_complete_overview($date_from = '', $date_to = '', $staff_id = '')
    {
        $this->db->select('
            l.id, l.name, l.email, l.phonenumber, l.company,
            l.lead_value, l.dateadded, l.lastcontact, l.last_status_change,
            l.junk, l.lost, l.assigned,
            CONCAT(s.firstname, " ", s.lastname) as staff_name,
            ls.name as status_name, ls.color as status_color,
            lso.name as source_name
        ');
        $this->db->from(db_prefix() . 'leads as l');
        $this->db->join(db_prefix() . 'staff as s', 's.staffid = l.assigned', 'left');
        $this->db->join(db_prefix() . 'leads_status as ls', 'ls.id = l.status', 'left');
        $this->db->join(db_prefix() . 'leads_sources as lso', 'lso.id = l.source', 'left');
        $this->_date_filter('l.dateadded', $date_from, $date_to);
        if (!empty($staff_id))
            $this->db->where('l.assigned', $staff_id);
        $this->db->order_by('l.dateadded', 'desc');
        return $this->db->get()->result_array();
    }

    // ─── Report 8: Last One Week Leads & Conversion ─────────────────────

    public function report_last_week_leads($date_from = '', $date_to = '', $staff_id = '')
    {
        if (empty($date_from) && empty($date_to)) {
            $date_from = date('Y-m-d', strtotime('-7 days'));
            $date_to = date('Y-m-d');
        }
        return $this->_report_leads_conversion($date_from, $date_to, $staff_id);
    }

    // ─── Report 9: Last 2 Week Leads & Conversion ───────────────────────

    public function report_last_2week_leads($date_from = '', $date_to = '', $staff_id = '')
    {
        if (empty($date_from) && empty($date_to)) {
            $date_from = date('Y-m-d', strtotime('-14 days'));
            $date_to = date('Y-m-d');
        }
        return $this->_report_leads_conversion($date_from, $date_to, $staff_id);
    }

    // ─── Report 10: Overall Leads Statuses ──────────────────────────────

    public function report_leads_statuses($date_from = '', $date_to = '', $staff_id = '')
    {
        $this->db->select('ls.name as status_name, ls.color, COUNT(l.id) as total');
        $this->db->from(db_prefix() . 'leads as l');
        $this->db->join(db_prefix() . 'leads_status as ls', 'ls.id = l.status', 'inner');
        $this->db->where('l.junk', 0);
        $this->db->where('l.lost', 0);
        $this->_date_filter('l.dateadded', $date_from, $date_to);
        if (!empty($staff_id))
            $this->db->where('l.assigned', $staff_id);
        $this->db->group_by('l.status');
        $this->db->order_by('total', 'desc');
        $statuses = $this->db->get()->result_array();

        // Junk
        $this->db->where('junk', 1);
        $this->_date_filter('dateadded', $date_from, $date_to);
        if (!empty($staff_id))
            $this->db->where('assigned', $staff_id);
        $junk = $this->db->count_all_results(db_prefix() . 'leads');
        if ($junk > 0)
            $statuses[] = ['status_name' => 'Junk', 'color' => '#9CA3AF', 'total' => $junk];

        // Lost
        $this->db->where('lost', 1);
        $this->_date_filter('dateadded', $date_from, $date_to);
        if (!empty($staff_id))
            $this->db->where('assigned', $staff_id);
        $lost = $this->db->count_all_results(db_prefix() . 'leads');
        if ($lost > 0)
            $statuses[] = ['status_name' => 'Lost', 'color' => '#EF4444', 'total' => $lost];

        return $statuses;
    }

    // ─── Private Helpers ────────────────────────────────────────────────

    private function _date_filter($col, $date_from, $date_to)
    {
        if (!empty($date_from))
            $this->db->where($col . ' >=', $date_from . ' 00:00:00');
        if (!empty($date_to))
            $this->db->where($col . ' <=', $date_to . ' 23:59:59');
    }

    private function _report_leads_conversion($date_from, $date_to, $staff_id = '')
    {
        $this->db->select('
            l.id, l.name, l.email, l.phonenumber, l.company,
            l.dateadded, l.lastcontact, l.assigned,
            l.junk, l.lost,
            CONCAT(s.firstname, " ", s.lastname) as staff_name,
            ls.name as status_name, ls.color as status_color,
            lso.name as source_name,
            c.userid as client_id
        ');
        $this->db->from(db_prefix() . 'leads as l');
        $this->db->join(db_prefix() . 'staff as s', 's.staffid = l.assigned', 'left');
        $this->db->join(db_prefix() . 'leads_status as ls', 'ls.id = l.status', 'left');
        $this->db->join(db_prefix() . 'leads_sources as lso', 'lso.id = l.source', 'left');
        $this->db->join(db_prefix() . 'clients as c', 'c.leadid = l.id', 'left');
        $this->_date_filter('l.dateadded', $date_from, $date_to);
        if (!empty($staff_id))
            $this->db->where('l.assigned', $staff_id);
        $this->db->order_by('l.dateadded', 'desc');
        return $this->db->get()->result_array();
    }
}
