<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * SHRA Leads — calling agents, follow-ups, weekend visits, revenue attribution.
 *
 * Leads live in the core tblleads table (so native Perfex keeps working);
 * everything academy-specific is in tblshra_lead_ext, every action is written
 * to the append-only tblshra_lead_events, and revenue credit is frozen into
 * tblshra_lead_attribution the moment a bill is created.
 */
class Shra_leads_model extends App_Model
{
    public function __construct()
    {
        parent::__construct();
        $this->load->helper('shra/shra');
    }

    /* ═══════════════════════ Read ═══════════════════════ */

    private function base_select()
    {
        $p = db_prefix();

        return "SELECT l.id, l.name, l.phonenumber, l.email, l.city, l.address, l.source, l.status, l.assigned, l.addedfrom, l.dateadded,
                   l.lastcontact, l.dateassigned, l.last_status_change, l.lost, l.junk, l.date_converted, l.lead_value, l.description, l.client_id,
                   x.*, s.name AS source_name,
                   CONCAT(st.firstname,' ',st.lastname) AS agent_name, st.email AS agent_email,
                   CONCAT(ad.firstname,' ',ad.lastname) AS added_by_name,
                   pk.name AS package_name, pk.price AS package_price, pk.audience AS package_audience,
                   r.rider_no, r.full_name AS rider_name
                FROM {$p}leads l
                JOIN {$p}shra_lead_ext x ON x.lead_id = l.id
                LEFT JOIN {$p}leads_sources s ON s.id = l.source
                LEFT JOIN {$p}staff st ON st.staffid = l.assigned
                LEFT JOIN {$p}staff ad ON ad.staffid = l.addedfrom
                LEFT JOIN {$p}shra_packages pk ON pk.id = x.interest_package_id
                LEFT JOIN {$p}shra_riders r ON r.id = x.rider_id ";
    }

    private function decorate($l)
    {
        if (!$l) {
            return $l;
        }
        $l->stage = $l->junk ? 'junk' : ($l->lost ? 'lost' : $l->stage_key);
        $l->is_open = in_array($l->stage, shra_lead_open_stages());
        $l->is_overdue = $l->is_open && !empty($l->next_action_at) && strtotime($l->next_action_at) < time();
        $l->is_today   = $l->is_open && !empty($l->next_action_at) && date('Y-m-d', strtotime($l->next_action_at)) === date('Y-m-d');
        $l->age_days   = (int) floor((time() - strtotime($l->dateadded)) / 86400);
        $l->wa_link    = shra_wa_link($l->phonenumber);
        $l->tel_link   = 'tel:' . preg_replace('/[^\d+]/', '', (string) $l->phonenumber);
        $l->batch_label = shra_batch_label($l->preferred_batch ?? null);
        $l->schedule    = shra_schedule_line($l->preferred_start_date ?? null, $l->preferred_batch ?? null);

        return $l;
    }

    public function get($id)
    {
        $row = $this->db->query($this->base_select() . ' WHERE l.id = ?', [(int) $id])->row();

        return $this->decorate($row);
    }

    /** Can the logged-in staff see / act on this lead? */
    public function can_access($lead, $staff_id = null)
    {
        $staff_id = $staff_id ?: get_staff_user_id();
        if (shra_leads_can('all')) {
            return true;
        }
        if (!shra_leads_can('own')) {
            return false;
        }

        return (int) $lead->assigned === (int) $staff_id || (int) $lead->addedfrom === (int) $staff_id;
    }

    private function scope_sql()
    {
        if (shra_leads_can('all')) {
            return '1=1';
        }
        $me = (int) get_staff_user_id();

        return "(l.assigned = {$me} OR l.addedfrom = {$me})";
    }

    /**
     * Generic list. Filters: stage (key or 'open'|'closed'), agent, source, q, from, to,
     * visit_date, overdue(1), stale(1), audience, limit, order.
     */
    public function get_list(array $f = [], $limit = 500)
    {
        $now = date('Y-m-d H:i:s');
        $w = [$this->scope_sql()];
        $b = [];
        if (!empty($f['stage'])) {
            if ($f['stage'] === 'open') {
                $w[] = "l.lost = 0 AND l.junk = 0 AND x.stage_key IN ('" . implode("','", shra_lead_open_stages()) . "')";
            } elseif ($f['stage'] === 'closed') {
                $w[] = "(l.lost = 1 OR l.junk = 1 OR x.stage_key = 'won')";
            } elseif ($f['stage'] === 'lost') {
                $w[] = 'l.lost = 1';
            } elseif ($f['stage'] === 'junk') {
                $w[] = 'l.junk = 1';
            } else {
                $w[] = 'l.lost = 0 AND l.junk = 0 AND x.stage_key = ?';
                $b[] = $f['stage'];
            }
        }
        if (!empty($f['agent'])) {
            $w[] = 'l.assigned = ?';
            $b[] = (int) $f['agent'];
        }
        if (!empty($f['source'])) {
            $w[] = 'l.source = ?';
            $b[] = (int) $f['source'];
        }
        if (!empty($f['audience'])) {
            $w[] = 'x.audience = ?';
            $b[] = $f['audience'];
        }
        if (!empty($f['q'])) {
            $q   = '%' . $f['q'] . '%';
            $w[] = '(l.name LIKE ? OR l.phonenumber LIKE ? OR x.phone_norm LIKE ? OR l.email LIKE ? OR l.city LIKE ?)';
            array_push($b, $q, $q, '%' . shra_phone_norm($f['q']) . '%', $q, $q);
        }
        if (!empty($f['from'])) {
            $w[] = 'l.dateadded >= ?';
            $b[] = $f['from'] . ' 00:00:00';
        }
        if (!empty($f['to'])) {
            $w[] = 'l.dateadded <= ?';
            $b[] = $f['to'] . ' 23:59:59';
        }
        if (!empty($f['visit_date'])) {
            $w[] = 'x.visit_date = ?';
            $b[] = $f['visit_date'];
        }
        if (!empty($f['overdue'])) {
            $w[] = "l.lost = 0 AND l.junk = 0 AND x.stage_key <> 'won' AND x.next_action_at < '{$now}'";
        }
        if (!empty($f['stale'])) {
            $w[] = 'x.is_stale = 1';
        }
        $order = !empty($f['order']) ? $f['order'] : 'x.next_action_at IS NULL, x.next_action_at ASC, l.dateadded DESC';
        $sql   = $this->base_select() . ' WHERE ' . implode(' AND ', $w) . ' ORDER BY ' . $order . ' LIMIT ' . (int) $limit;
        $rows  = $this->db->query($sql, $b)->result();
        foreach ($rows as $r) {
            $this->decorate($r);
        }

        return $rows;
    }

    /** Agent home: overdue / today / upcoming (7 days) / no next action (safety net). */
    public function queues_for($staff_id, $include_unassigned = false)
    {
        $p    = db_prefix();
        // Managers also see unassigned leads (public form with an empty agent pool) so nothing is invisible.
        // $staff_id 0 = the whole team's queue ("All staff"), unassigned included.
        if ((int) $staff_id === 0) {
            $who  = '1=1';
            $bind = [];
        } else {
            $who  = $include_unassigned ? '(l.assigned = ? OR l.assigned = 0 OR l.assigned IS NULL)' : 'l.assigned = ?';
            $bind = [(int) $staff_id];
        }
        $rows = $this->db->query($this->base_select() . " WHERE $who AND l.lost = 0 AND l.junk = 0 AND x.stage_key <> 'won'
            ORDER BY l.dateadded DESC LIMIT 800", $bind)->result();
        $out  = ['overdue' => [], 'today' => [], 'upcoming' => [], 'later' => [], 'unset' => []];
        $now  = time();
        foreach ($rows as $r) {
            $this->decorate($r);
            if (empty($r->next_action_at)) {
                $out['unset'][] = $r;
            } elseif (strtotime($r->next_action_at) < $now) {
                $out['overdue'][] = $r;
            } elseif (date('Y-m-d', strtotime($r->next_action_at)) === date('Y-m-d')) {
                $out['today'][] = $r;
            } elseif (strtotime($r->next_action_at) < strtotime('+7 days')) {
                $out['upcoming'][] = $r;
            } else {
                $out['later'][] = $r;
            }
        }

        return $out;
    }

    /** Visits board: scheduled for a date, grouped by slot; plus no-shows (past, never visited). */
    public function visits_for($date)
    {
        $rows = $this->db->query($this->base_select() . " WHERE l.lost = 0 AND l.junk = 0 AND x.visit_date = ?
            AND x.stage_key IN ('visit_scheduled','visited','confirmed','won') ORDER BY x.visit_slot ASC, l.name ASC", [$date])->result();
        $groups = [];
        foreach ($rows as $r) {
            $this->decorate($r);
            $groups[$r->visit_slot ?: 'Any time'][] = $r;
        }

        return $groups;
    }

    public function no_shows($limit = 100)
    {
        $today = date('Y-m-d');
        $rows = $this->db->query($this->base_select() . " WHERE l.lost = 0 AND l.junk = 0 AND x.stage_key = 'visit_scheduled' AND x.visit_date < '{$today}'
            ORDER BY x.visit_date DESC LIMIT " . (int) $limit)->result();
        foreach ($rows as $r) {
            $this->decorate($r);
        }

        return $rows;
    }

    public function find_by_phone($phone)
    {
        $n = shra_phone_norm($phone);
        if ($n === '') {
            return null;
        }

        return $this->decorate($this->db->query($this->base_select() . ' WHERE x.phone_norm = ?', [$n])->row());
    }

    /** Open (or recently won) lead matching a phone — used by billing to credit walk-ins. */
    public function find_creditable_by_phone($phone)
    {
        $l = $this->find_by_phone($phone);
        if (!$l || $l->junk) {
            return null;
        }

        return $l;
    }

    public function events($lead_id, $limit = 200)
    {
        $p = db_prefix();

        return $this->db->query("SELECT e.*, CONCAT(s.firstname,' ',s.lastname) AS staff_name FROM {$p}shra_lead_events e
            LEFT JOIN {$p}staff s ON s.staffid = e.staff_id WHERE e.lead_id = ? ORDER BY e.id DESC LIMIT " . (int) $limit, [(int) $lead_id])->result();
    }

    public function attribution_for_lead($lead_id)
    {
        $p = db_prefix();

        return $this->db->query("SELECT a.*, e.enrollment_no, e.package_name, CONCAT(s.firstname,' ',s.lastname) AS agent_name
            FROM {$p}shra_lead_attribution a LEFT JOIN {$p}shra_enrollments e ON e.id = a.enrollment_id
            LEFT JOIN {$p}staff s ON s.staffid = a.agent_id WHERE a.lead_id = ? ORDER BY a.id ASC", [(int) $lead_id])->result();
    }

    /** Advances collected on this lead, newest first. */
    public function payments($lead_id)
    {
        $p = db_prefix();
        // The table is created by the module install run — never break the lead page before it.
        if (!$this->db->table_exists($p . 'shra_lead_payments')) {
            return [];
        }

        return $this->db->query("SELECT pm.*, CONCAT(s.firstname,' ',s.lastname) AS staff_name FROM {$p}shra_lead_payments pm
            LEFT JOIN {$p}staff s ON s.staffid = pm.staff_id WHERE pm.lead_id = ? ORDER BY pm.id DESC", [(int) $lead_id])->result();
    }

    /**
     * Every advance taken on a call, across all leads — the Payments desk lists these
     * next to the invoices. $filters: from, to (dates on created_at), q.
     */
    public function all_payments(array $filters = [], $limit = 500)
    {
        $p = db_prefix();
        if (!$this->db->table_exists($p . 'shra_lead_payments')) {
            return [];
        }

        $this->db->select("pm.*, l.name AS lead_name, l.phonenumber, l.email, x.stage_key,
            CONCAT(s.firstname,' ',s.lastname) AS staff_name", false)
            ->from($p . 'shra_lead_payments pm')
            ->join($p . 'leads l', 'l.id = pm.lead_id', 'left')
            ->join($p . 'shra_lead_ext x', 'x.lead_id = pm.lead_id', 'left')
            ->join($p . 'staff s', 's.staffid = pm.staff_id', 'left');

        if (!empty($filters['from'])) {
            $this->db->where('pm.created_at >=', $filters['from'] . ' 00:00:00');
        }
        if (!empty($filters['to'])) {
            $this->db->where('pm.created_at <=', $filters['to'] . ' 23:59:59');
        }
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $like = $this->db->escape_like_str($q);
            $cols = ['l.name', 'l.phonenumber', 'l.email', 'pm.reference', 'pm.note', 'pm.method', "CONCAT(s.firstname,' ',s.lastname)"];
            $or   = [];
            foreach ($cols as $c) {
                $or[] = $c . " LIKE '%{$like}%' ESCAPE '!'";
            }
            $this->db->where('(' . implode(' OR ', $or) . ')');
        }

        return $this->db->order_by('pm.created_at DESC, pm.id DESC')->limit((int) $limit)->get()->result();
    }

    public function payment($id)
    {
        return $this->db->where('id', (int) $id)->get(db_prefix() . 'shra_lead_payments')->row();
    }

    /* ═══════════════════════ Write helpers ═══════════════════════ */

    private function staff_id()
    {
        return is_staff_logged_in() ? (int) get_staff_user_id() : null;
    }

    public function event($lead_id, $type, array $o = [])
    {
        $this->db->insert(db_prefix() . 'shra_lead_events', [
            'lead_id'    => (int) $lead_id,
            'staff_id'   => array_key_exists('staff_id', $o) ? $o['staff_id'] : $this->staff_id(),
            'event_type' => $type,
            'outcome'    => $o['outcome'] ?? null,
            'from_value' => isset($o['from']) ? substr((string) $o['from'], 0, 60) : null,
            'to_value'   => isset($o['to']) ? substr((string) $o['to'], 0, 60) : null,
            'note'       => isset($o['note']) && $o['note'] !== '' ? $o['note'] : null,
            'meta'       => isset($o['meta']) ? json_encode($o['meta']) : null,
            'ip'         => $this->input->ip_address(),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Mirror to the native Perfex lead timeline
        if (!empty($o['log'])) {
            $this->db->insert(db_prefix() . 'lead_activity_log', [
                'leadid'          => (int) $lead_id,
                'description'     => $o['log'],
                'additional_data' => '',
                'date'            => date('Y-m-d H:i:s'),
                'staffid'         => (int) $this->staff_id(),
                'full_name'       => $this->staff_id() ? get_staff_full_name($this->staff_id()) : 'System',
                'custom_activity' => 1,
            ]);
        }

        return $this->db->insert_id();
    }

    private function update_lead($lead_id, array $core = [], array $ext = [])
    {
        $p = db_prefix();
        if (count($core)) {
            $this->db->where('id', (int) $lead_id)->update($p . 'leads', $core);
        }
        if (count($ext)) {
            $this->db->where('lead_id', (int) $lead_id)->update($p . 'shra_lead_ext', $ext);
        }
    }

    private function notify($staff_id, $lang_key, array $args, $lead_id)
    {
        if (!$staff_id || (int) $staff_id === (int) $this->staff_id()) {
            return;
        }
        $ok = add_notification([
            'description'     => $lang_key,
            'touserid'        => (int) $staff_id,
            'link'            => 'shra/shra_leads/view/' . (int) $lead_id,
            'additional_data' => serialize($args),
        ]);
        if ($ok) {
            pusher_trigger_notification([(int) $staff_id]);
        }
    }

    /* ═══════════════════════ Capture ═══════════════════════ */

    /**
     * Create a lead. Returns ['lead_id'=>…] | ['duplicate'=>true,'lead'=>…] | string error.
     * $in: name, phone, email, city, source (id), assigned, rider_for, rider_age, audience,
     *      interest_package_id, expected_value, description, campaign, referrer_rider_id, next_action_at
     */
    public function capture(array $in, $ctx = 'manual')
    {
        $p     = db_prefix();
        $name  = trim((string) ($in['name'] ?? ''));
        $phone = trim((string) ($in['phone'] ?? ''));
        if ($name === '') {
            return 'Name is required.';
        }
        if (!shra_phone_valid($phone)) {
            return 'Enter a valid mobile number.';
        }
        $norm = shra_phone_norm($phone);

        $dup = $this->find_by_phone($norm);
        if ($dup) {
            $this->event($dup->id, 'duplicate_attempt', [
                'note' => 'Duplicate lead attempt for ' . $phone . ' (' . $name . ') via ' . $ctx,
                'meta' => ['name' => $name, 'ctx' => $ctx, 'by' => $this->staff_id()],
                'log'  => 'Duplicate lead attempt (' . $name . ') — attached to this lead',
            ]);
            // Re-activate a lost lead automatically when the same person reaches out again
            if ($dup->lost && $ctx !== 'import') {
                $this->reopen($dup->id, 'Re-inquired via ' . $ctx);
                $dup = $this->get($dup->id);
            }

            return ['duplicate' => true, 'lead' => $dup, 'lead_id' => $dup->id];
        }

        $assigned = (int) ($in['assigned'] ?? 0);
        $auto     = false;
        if (!$assigned && get_option('shra_lead_auto_assign') == '1') {
            $assigned = $this->next_agent_round_robin();
            $auto     = (bool) $assigned;
        }
        if (!$assigned && $this->staff_id() && shra_leads_can('own')) {
            $assigned = $this->staff_id();
        }

        $sla    = max(5, (int) get_option('shra_lead_sla_minutes'));
        // Imports may carry the date the lead really came in; never in the future
        $added  = !empty($in['dateadded']) ? min(date('Y-m-d H:i:s'), date('Y-m-d H:i:s', strtotime($in['dateadded']))) : date('Y-m-d H:i:s');
        $next   = !empty($in['next_action_at']) ? date('Y-m-d H:i:s', strtotime($in['next_action_at'])) : date('Y-m-d H:i:s', time() + $sla * 60);
        $expect = round((float) ($in['expected_value'] ?? 0), 2);
        $pkg_id = (int) ($in['interest_package_id'] ?? 0) ?: null;
        if ($pkg_id && $expect <= 0) {
            $pk = $this->db->where('id', $pkg_id)->get($p . 'shra_packages')->row();
            if ($pk) {
                $this->load->model('shra/shra_model');
                $expect = (float) $this->shra_model->quote($pk)['total'];
            }
        }

        $core = [
            'hash'             => app_generate_hash(),
            'name'             => substr($name, 0, 191),
            'title'            => '',
            'company'          => '',
            'description'      => (string) ($in['description'] ?? ''),
            'country'          => 0,
            'zip'              => '',
            'city'             => substr(trim((string) ($in['city'] ?? '')), 0, 100),
            'state'            => '',
            'address'          => substr(trim((string) ($in['address'] ?? '')), 0, 100),
            'assigned'         => $assigned,
            'dateadded'        => $added,
            'status'           => shra_lead_stage_id('new'),
            'source'           => (int) ($in['source'] ?? 0),
            'lastcontact'      => null,
            'dateassigned'     => $assigned ? date('Y-m-d') : null,
            'last_status_change' => $added,
            'addedfrom'        => (int) $this->staff_id(),
            'email'            => substr(trim((string) ($in['email'] ?? '')), 0, 100),
            'website'          => '',
            'leadorder'        => 1,
            'phonenumber'      => substr($phone, 0, 50),
            'lost'             => 0,
            'junk'             => 0,
            'is_public'        => 0,
            'default_language' => '',
            'client_id'        => 0,
            'lead_value'       => $expect,
        ];
        $this->db->insert($p . 'leads', $core);
        $lead_id = $this->db->insert_id();
        if (!$lead_id) {
            return 'Could not save the lead.';
        }

        $rider_for = in_array($in['rider_for'] ?? '', ['self', 'child', 'both']) ? $in['rider_for'] : 'self';
        $age       = isset($in['rider_age']) && $in['rider_age'] !== '' ? (int) $in['rider_age'] : null;
        $audience  = in_array($in['audience'] ?? '', ['children', 'adults']) ? $in['audience'] : ($age !== null ? ($age < (int) get_option('shra_minor_age') ? 'children' : 'adults') : ($rider_for === 'child' ? 'children' : null));

        $this->db->insert($p . 'shra_lead_ext', [
            'lead_id'             => $lead_id,
            'phone_norm'          => $norm,
            'stage_key'           => 'new',
            'rider_for'           => $rider_for,
            'rider_age'           => $age,
            'audience'            => $audience,
            'interest_package_id' => $pkg_id,
            'preferred_start_date' => shra_start_date($in['preferred_start_date'] ?? ''),
            'preferred_batch'     => shra_batch_key($in['preferred_batch'] ?? ''),
            'expected_value'      => $expect,
            'next_action_at'      => $next,
            'next_action_type'    => 'call',
            'campaign'            => substr(trim((string) ($in['campaign'] ?? '')), 0, 80) ?: null,
            'referrer_rider_id'   => (int) ($in['referrer_rider_id'] ?? 0) ?: null,
            'ip_address'          => $this->input->ip_address(),
            'created_at'          => $added,
        ]);

        $this->event($lead_id, 'created', ['note' => 'Captured via ' . $ctx, 'meta' => ['ctx' => $ctx], 'log' => 'Lead captured via ' . $ctx]);
        if ($assigned) {
            $this->event($lead_id, 'assigned', ['to' => $assigned, 'note' => $auto ? 'Auto-assigned (round robin)' : null, 'log' => 'Assigned to ' . get_staff_full_name($assigned)]);
            $this->notify($assigned, 'shra_not_lead_assigned', [$name], $lead_id);
        }
        hooks()->do_action('shra_lead_captured', ['lead_id' => $lead_id, 'ctx' => $ctx]);

        return ['lead_id' => $lead_id];
    }

    /** Hook: a lead created by native Perfex (web-to-lead, email integration, manual). */
    public function adopt_core_lead($lead_id)
    {
        $p = db_prefix();
        if ($this->db->where('lead_id', (int) $lead_id)->get($p . 'shra_lead_ext')->row()) {
            return;
        }
        $l = $this->db->where('id', (int) $lead_id)->get($p . 'leads')->row();
        if (!$l) {
            return;
        }
        $norm = shra_phone_norm($l->phonenumber);
        if ($norm === '') {
            $norm = 'noph-' . $lead_id; // cannot dedupe without a phone; keep it but isolated
        } elseif ($this->db->where('phone_norm', $norm)->get($p . 'shra_lead_ext')->row()) {
            $norm = 'dup-' . $lead_id . '-' . $norm; // native duplicate; surfaced in the pipeline as a duplicate
        }
        $assigned = (int) $l->assigned;
        if (!$assigned && get_option('shra_lead_auto_assign') == '1') {
            $assigned = $this->next_agent_round_robin();
            if ($assigned) {
                $this->db->where('id', $l->id)->update($p . 'leads', ['assigned' => $assigned, 'dateassigned' => date('Y-m-d')]);
            }
        }
        $stage = shra_lead_stage_key_from_status($l->status);
        if ($l->status != shra_lead_stage_id($stage)) {
            $this->db->where('id', $l->id)->update($p . 'leads', ['status' => shra_lead_stage_id('new')]);
            $stage = 'new';
        }
        $this->db->insert($p . 'shra_lead_ext', [
            'lead_id'        => $l->id,
            'phone_norm'     => $norm,
            'stage_key'      => $stage,
            'next_action_at' => date('Y-m-d H:i:s', time() + max(5, (int) get_option('shra_lead_sla_minutes')) * 60),
            'created_at'     => date('Y-m-d H:i:s'),
        ]);
        $this->event($l->id, 'created', ['note' => 'Adopted from core CRM lead', 'staff_id' => $this->staff_id()]);
        if ($assigned) {
            $this->event($l->id, 'assigned', ['to' => $assigned, 'note' => 'Auto-assigned (round robin)']);
            $this->notify($assigned, 'shra_not_lead_assigned', [$l->name], $l->id);
        }
    }

    /** Least-recently-assigned active agent from the pool (falls back to all agents). */
    public function next_agent_round_robin()
    {
        $p    = db_prefix();
        $pool = array_filter(array_map('intval', json_decode((string) get_option('shra_lead_agent_pool'), true) ?: []));
        if (!count($pool)) {
            // Default pool: everyone holding the "work own leads" capability (calling agents), not desk/managers
            $rows = $this->db->query("SELECT DISTINCT sp.staff_id FROM {$p}staff_permissions sp JOIN {$p}staff s ON s.staffid = sp.staff_id
                WHERE sp.feature = 'shra' AND sp.capability = 'leads_own' AND s.active = 1 AND s.admin = 0")->result();
            $pool = array_map(function ($r) { return (int) $r->staff_id; }, $rows);
        }
        if (!count($pool)) {
            return 0;
        }
        $active = $this->db->select('staffid')->where_in('staffid', $pool)->where('active', 1)->get($p . 'staff')->result();
        $pool   = array_map(function ($r) { return (int) $r->staffid; }, $active);
        if (!count($pool)) {
            return 0;
        }
        $rows = $this->db->query("SELECT assigned, MAX(dateadded) AS last FROM {$p}leads WHERE assigned IN (" . implode(',', $pool) . ') GROUP BY assigned')->result();
        $last = [];
        foreach ($rows as $r) {
            $last[(int) $r->assigned] = strtotime($r->last);
        }
        $best = 0;
        $min  = PHP_INT_MAX;
        foreach ($pool as $sid) {
            $t = $last[$sid] ?? 0;
            if ($t < $min) {
                $min  = $t;
                $best = $sid;
            }
        }

        return $best;
    }

    /* ═══════════════════════ Actions ═══════════════════════ */

    public function assign($lead_id, $staff_id, $note = '', $auto = false)
    {
        $l = $this->get($lead_id);
        if (!$l) {
            return 'Lead not found.';
        }
        $staff_id = (int) $staff_id;
        if ($staff_id === (int) $l->assigned) {
            return true;
        }
        $this->update_lead($lead_id, ['assigned' => $staff_id, 'dateassigned' => date('Y-m-d')], ['is_stale' => 0]);
        $this->event($lead_id, $l->assigned ? 'reassigned' : 'assigned', ['from' => $l->assigned, 'to' => $staff_id, 'note' => $note ?: ($auto ? 'Auto-reassigned' : null),
            'log' => ($l->assigned ? 'Reassigned to ' : 'Assigned to ') . get_staff_full_name($staff_id)]);
        $this->notify($staff_id, 'shra_not_lead_assigned', [$l->name], $lead_id);

        return true;
    }

    /**
     * Log a call / WhatsApp attempt. The agent picks the lead status in the dialog — the
     * outcome stored on the event is derived from it (see shra_lead_stage_outcome), so the
     * leaderboard and the EOD report keep reading the same vocabulary as before.
     * $stage is the status the agent picked, '' to keep the current one. The move itself is
     * applied by the controller. Returns true | error string.
     */
    public function log_call($lead_id, $stage = '', $next_at = null, $note = '', $channel = 'call')
    {
        $l = $this->get($lead_id);
        if (!$l) {
            return 'Lead not found.';
        }
        if (!$l->is_open) {
            return 'This lead is closed (' . shra_lead_stage_label($l->stage) . '). Reopen it first.';
        }
        $stage = (string) $stage;
        if ($stage !== '' && !in_array($stage, shra_lead_quick_stages(), true)) {
            return 'Pick a status from the list — a visit, a confirmation or a loss has its own step.';
        }
        $target = $stage !== '' ? $stage : $l->stage;
        // A WhatsApp that changed nothing is exactly that — don't read the standing status into it.
        $outcome = ($channel === 'whatsapp' && $stage === '') ? 'whatsapp_sent' : shra_lead_stage_outcome($target, $channel);
        $defs    = shra_lead_outcomes();
        [, $is_contact, $needs_next] = $defs[$outcome];

        if ($needs_next) {
            if (empty($next_at)) {
                return 'Set the next follow-up date & time — every open lead must have one.';
            }
            $next_at = date('Y-m-d H:i:s', strtotime($next_at));
            if (strtotime($next_at) < time() - 300) {
                return 'Next follow-up must be in the future.';
            }
        }

        $ext = [
            'call_attempts'    => (int) $l->call_attempts + 1,
            'last_outcome'     => $outcome,
            'next_action_at'   => $next_at,
            'next_action_type' => $channel === 'whatsapp' ? 'whatsapp' : 'call',
            'is_stale'         => 0,
        ];
        $core = ['lastcontact' => date('Y-m-d H:i:s')];
        if ($is_contact && empty($l->first_contact_at)) {
            $ext['first_contact_at'] = date('Y-m-d H:i:s');
        }
        $this->update_lead($lead_id, $core, $ext);
        $this->event($lead_id, $channel === 'whatsapp' ? 'whatsapp' : 'call', ['outcome' => $outcome, 'note' => $note, 'to' => $next_at,
            'meta' => ['stage' => $stage],   // '' = the agent kept the status
            'log'  => ucfirst($channel) . ': ' . shra_lead_stage_label($target) . ($note ? ' — ' . $note : '') . ($next_at ? ' · next ' . shra_datetime($next_at, false) : '')]);

        return true;
    }

    /** Every call / WhatsApp attempt on a lead, newest first — the log at the foot of the dialog. */
    public function call_log($lead_id, $limit = 40)
    {
        $p = db_prefix();

        return $this->db->query("SELECT e.*, CONCAT(s.firstname,' ',s.lastname) AS staff_name FROM {$p}shra_lead_events e
            LEFT JOIN {$p}staff s ON s.staffid = e.staff_id
            WHERE e.lead_id = ? AND e.event_type IN ('call','whatsapp') ORDER BY e.id DESC LIMIT " . (int) $limit, [(int) $lead_id])->result();
    }

    /**
     * Record an advance the agent collected on the call ("pay 50% now" offers), with the
     * payment screenshot the customer sent back. Money is not a bill yet — it is proof the
     * lead has committed; the counter still raises the real invoice at billing time.
     * $file is the stored name inside uploads/shra/lead_payments/. Returns the payment id | error string.
     */
    public function add_payment($lead_id, $amount, $method = '', $reference = '', $note = '', $file = '', $file_name = '')
    {
        $l = $this->get($lead_id);
        if (!$l) {
            return 'Lead not found.';
        }
        $amount = round((float) $amount, 2);
        if ($amount <= 0) {
            return 'Enter the amount collected.';
        }
        if ($amount > 99999999) {
            return 'That amount looks wrong — check it and try again.';
        }
        $p = db_prefix();
        if (!$this->db->table_exists($p . 'shra_lead_payments')) {
            return 'Payments are not set up yet — reactivate the SHRA module under Setup → Modules.';
        }
        $this->db->insert($p . 'shra_lead_payments', [
            'lead_id'    => (int) $lead_id,
            'staff_id'   => $this->staff_id(),
            'amount'     => $amount,
            'method'     => $method !== '' ? substr($method, 0, 40) : null,
            'reference'  => $reference !== '' ? substr($reference, 0, 80) : null,
            'note'       => $note !== '' ? substr($note, 0, 255) : null,
            'file'       => $file !== '' ? $file : null,
            'file_name'  => $file_name !== '' ? substr($file_name, 0, 160) : null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $id = (int) $this->db->insert_id();
        if (!$id) {
            return 'Could not save the payment.';
        }
        $this->update_lead($lead_id, [], ['paid_amount' => (float) $l->paid_amount + $amount, 'last_payment_at' => date('Y-m-d H:i:s')]);
        $this->event($lead_id, 'payment', [
            'to'   => $amount,
            'note' => $note,
            'meta' => ['payment_id' => $id, 'method' => $method, 'reference' => $reference, 'file' => $file !== '' ? 1 : 0],
            'log'  => 'Payment collected: ' . shra_money($amount) . ($method ? ' · ' . $method : '') . ($reference ? ' · ref ' . $reference : '') . ($file !== '' ? ' · screenshot attached' : ''),
        ]);

        return $id;
    }

    /** Attach (or replace) the screenshot on a payment already recorded. */
    public function attach_payment_proof($id, $file, $file_name = '')
    {
        $pay = $this->payment($id);
        if (!$pay) {
            return 'Payment not found.';
        }
        $old = $pay->file;
        $this->db->where('id', (int) $pay->id)->update(db_prefix() . 'shra_lead_payments', [
            'file'      => $file,
            'file_name' => $file_name !== '' ? substr($file_name, 0, 160) : null,
        ]);
        if ($old && $old !== $file) {
            $abs = FCPATH . 'uploads/shra/lead_payments/' . basename($old);
            if (is_file($abs)) {
                @unlink($abs);
            }
        }
        $this->event($pay->lead_id, 'payment_proof', [
            'to'   => $pay->amount,
            'meta' => ['payment_id' => (int) $pay->id],
            'log'  => ($old ? 'Payment screenshot replaced on ' : 'Payment screenshot attached to ') . shra_money($pay->amount),
        ]);

        return true;
    }

    /** Remove a wrongly entered payment (managers only — the controller gates it). */
    public function delete_payment($id)
    {
        $pay = $this->payment($id);
        if (!$pay) {
            return 'Payment not found.';
        }
        $l = $this->get($pay->lead_id);
        $this->db->where('id', (int) $pay->id)->delete(db_prefix() . 'shra_lead_payments');
        if ($pay->file) {
            $abs = FCPATH . 'uploads/shra/lead_payments/' . basename($pay->file);
            if (is_file($abs)) {
                @unlink($abs);
            }
        }
        if ($l) {
            $this->update_lead($pay->lead_id, [], ['paid_amount' => max(0, (float) $l->paid_amount - (float) $pay->amount)]);
        }
        $this->event($pay->lead_id, 'payment_removed', [
            'to'  => $pay->amount,
            'log' => 'Payment entry removed: ' . shra_money($pay->amount),
        ]);

        return true;
    }

    /**
     * Move to a stage with validation. $ctx may carry: next_action_at, visit_date, visit_slot,
     * package_id, expected_value, reason, note, silent_next (skip the next-action requirement).
     */
    public function set_stage($lead_id, $to, array $ctx = [])
    {
        $l = $this->get($lead_id);
        if (!$l) {
            return 'Lead not found.';
        }
        $from = $l->stage;
        if ($from === $to) {
            return true;
        }
        if (!in_array($to, shra_lead_transitions($from))) {
            return 'Cannot move from "' . shra_lead_stage_label($from) . '" to "' . shra_lead_stage_label($to) . '".';
        }
        switch ($to) {
            case 'visit_scheduled':
                return $this->schedule_visit($lead_id, $ctx['visit_date'] ?? '', $ctx['visit_slot'] ?? '', $ctx['note'] ?? '');
            case 'visited':
                return $this->mark_visited($lead_id, $ctx['note'] ?? '');
            case 'confirmed':
                return $this->confirm($lead_id, $ctx['package_id'] ?? 0, $ctx['expected_value'] ?? null, $ctx['note'] ?? '');
            case 'lost':
                return $this->mark_lost($lead_id, $ctx['reason'] ?? '', $ctx['note'] ?? '');
            case 'junk':
                return $this->mark_junk($lead_id, $ctx['note'] ?? '');
            case 'won':
                return 'Leads become customers automatically when a bill is created — use "Bill now".';
        }

        // contacted / followup (or reopen)
        $ext  = ['stage_key' => $to, 'is_stale' => 0];
        $core = ['status' => shra_lead_stage_id($to), 'last_status_change' => date('Y-m-d H:i:s'), 'lost' => 0, 'junk' => 0];
        if (!empty($ctx['next_action_at'])) {
            $ext['next_action_at'] = date('Y-m-d H:i:s', strtotime($ctx['next_action_at']));
        } elseif (empty($l->next_action_at) || strtotime($l->next_action_at) < time()) {
            if (empty($ctx['silent_next'])) {
                return 'Set the next follow-up date before moving this lead.';
            }
            $ext['next_action_at'] = date('Y-m-d H:i:s', strtotime('tomorrow 10:00'));
        }
        $this->update_lead($lead_id, $core, $ext);
        $this->event($lead_id, 'stage', ['from' => $from, 'to' => $to, 'note' => $ctx['note'] ?? '', 'log' => 'Stage: ' . shra_lead_stage_label($from) . ' → ' . shra_lead_stage_label($to)]);
        hooks()->do_action('lead_status_changed', ['lead_id' => $lead_id, 'old_status' => shra_lead_stage_id($from), 'new_status' => shra_lead_stage_id($to)]);

        return true;
    }

    public function schedule_visit($lead_id, $date, $slot, $note = '')
    {
        $l = $this->get($lead_id);
        if (!$l) {
            return 'Lead not found.';
        }
        if (!$l->is_open) {
            return 'This lead is closed. Reopen it first.';
        }
        if (empty($date) || !strtotime($date)) {
            return 'Pick the visit date.';
        }
        $date = date('Y-m-d', strtotime($date));
        if ($date < date('Y-m-d')) {
            return 'Visit date cannot be in the past.';
        }
        $slot = substr(trim((string) $slot), 0, 40);
        if ($slot === '') {
            return 'Pick a visit slot.';
        }
        $time = '09:00';
        if (preg_match('/(\d{1,2}):(\d{2})/', $slot, $m)) {
            $time = str_pad($m[1], 2, '0', STR_PAD_LEFT) . ':' . $m[2];
        }
        $p        = db_prefix();
        $reminder = $l->visit_reminder_id;
        if ($l->assigned) {
            $rdata = [
                'description'     => 'Visit today: ' . $l->name . ' (' . $l->phonenumber . ') at ' . $slot . ' — SHRA lead',
                'date'            => $date . ' ' . $time . ':00',
                'isnotified'      => 0,
                'rel_id'          => (int) $lead_id,
                'staff'           => (int) $l->assigned,
                'rel_type'        => 'lead',
                'notify_by_email' => 1,
                'creator'         => (int) $this->staff_id(),
            ];
            $rdata['date'] = date('Y-m-d H:i:s', strtotime($rdata['date']) - 3600); // one hour before
            if ($reminder && $this->db->where('id', $reminder)->get($p . 'reminders')->row()) {
                $this->db->where('id', $reminder)->update($p . 'reminders', $rdata);
            } else {
                $this->db->insert($p . 'reminders', $rdata);
                $reminder = $this->db->insert_id();
            }
        }
        $resched = $l->stage === 'visit_scheduled';
        $this->update_lead($lead_id,
            ['status' => shra_lead_stage_id('visit_scheduled'), 'last_status_change' => date('Y-m-d H:i:s'), 'lost' => 0, 'junk' => 0],
            ['stage_key' => 'visit_scheduled', 'visit_date' => $date, 'visit_slot' => $slot, 'visit_reminder_id' => $reminder,
             'next_action_at' => $date . ' ' . $time . ':00', 'next_action_type' => 'visit', 'is_stale' => 0]);
        $this->event($lead_id, $resched ? 'visit_rescheduled' : 'visit_scheduled', ['from' => $resched ? $l->visit_date . ' ' . $l->visit_slot : $l->stage, 'to' => $date . ' ' . $slot, 'note' => $note,
            'log' => ($resched ? 'Visit rescheduled to ' : 'Visit scheduled for ') . date('D d M', strtotime($date)) . ' · ' . $slot]);
        if ($l->assigned) {
            $this->notify($l->assigned, 'shra_not_lead_visit', [$l->name, date('D d M', strtotime($date)) . ' ' . $slot], $lead_id);
        }

        return true;
    }

    public function mark_visited($lead_id, $note = '')
    {
        $l = $this->get($lead_id);
        if (!$l) {
            return 'Lead not found.';
        }
        if (!$l->is_open) {
            return 'This lead is closed. Reopen it first.';
        }
        $today = date('Y-m-d');
        $this->update_lead($lead_id,
            ['status' => shra_lead_stage_id('visited'), 'last_status_change' => date('Y-m-d H:i:s'), 'lastcontact' => date('Y-m-d H:i:s')],
            ['stage_key' => 'visited', 'visited_at' => date('Y-m-d H:i:s'), 'visited_by' => $this->staff_id(),
             'visit_date' => $l->visit_date ?: $today, 'visit_slot' => $l->visit_slot ?: 'Walk-in',
             'next_action_at' => date('Y-m-d H:i:s', strtotime('tomorrow 10:00')), 'next_action_type' => 'call', 'is_stale' => 0]);
        $this->event($lead_id, 'visited', ['from' => $l->stage, 'note' => $note, 'log' => 'Visited the academy']);
        if ($l->assigned) {
            $this->notify($l->assigned, 'shra_not_lead_visited', [$l->name], $lead_id);
        }

        return true;
    }

    public function mark_no_show($lead_id, $note = '')
    {
        $l = $this->get($lead_id);
        if (!$l) {
            return 'Lead not found.';
        }
        if ($l->stage !== 'visit_scheduled') {
            return 'Only a scheduled visit can be a no-show.';
        }
        $this->update_lead($lead_id,
            ['status' => shra_lead_stage_id('followup'), 'last_status_change' => date('Y-m-d H:i:s')],
            ['stage_key' => 'followup', 'no_show_count' => (int) $l->no_show_count + 1, 'last_outcome' => 'no_show',
             'next_action_at' => date('Y-m-d H:i:s'), 'next_action_type' => 'call', 'visit_date' => null, 'visit_slot' => null]);
        $this->event($lead_id, 'no_show', ['from' => $l->visit_date . ' ' . $l->visit_slot, 'note' => $note, 'log' => 'No-show for visit on ' . $l->visit_date]);
        if ($l->assigned) {
            $this->notify($l->assigned, 'shra_not_lead_noshow', [$l->name], $lead_id);
        }

        return true;
    }

    public function confirm($lead_id, $package_id = 0, $expected_value = null, $note = '')
    {
        $l = $this->get($lead_id);
        if (!$l) {
            return 'Lead not found.';
        }
        if (!in_array($l->stage, ['visited', 'visit_scheduled', 'confirmed'])) {
            return 'Mark the lead as visited first.';
        }
        $p      = db_prefix();
        $pkg_id = (int) $package_id ?: $l->interest_package_id;
        $expect = $expected_value !== null && $expected_value !== '' ? round((float) $expected_value, 2) : (float) $l->expected_value;
        if ($pkg_id && ($expected_value === null || $expected_value === '')) {
            $pk = $this->db->where('id', $pkg_id)->get($p . 'shra_packages')->row();
            if ($pk) {
                $this->load->model('shra/shra_model');
                $expect = (float) $this->shra_model->quote($pk)['total'];
            }
        }
        $ext = ['stage_key' => 'confirmed', 'confirmed_at' => date('Y-m-d H:i:s'), 'interest_package_id' => $pkg_id ?: null, 'expected_value' => $expect,
            'next_action_at' => date('Y-m-d H:i:s', strtotime('+1 hour')), 'next_action_type' => 'other', 'is_stale' => 0];
        if ($l->stage !== 'visited' && empty($l->visited_at)) {
            $ext['visited_at'] = date('Y-m-d H:i:s');
            $ext['visited_by'] = $this->staff_id();
            $ext['visit_date'] = $l->visit_date ?: date('Y-m-d');
        }
        $this->update_lead($lead_id, ['status' => shra_lead_stage_id('confirmed'), 'last_status_change' => date('Y-m-d H:i:s'), 'lead_value' => $expect], $ext);
        $this->event($lead_id, 'confirmed', ['from' => $l->stage, 'to' => $expect, 'note' => $note, 'log' => 'Visited & confirmed · expected ' . shra_money($expect)]);
        if ($l->assigned) {
            $this->notify($l->assigned, 'shra_not_lead_confirmed', [$l->name], $lead_id);
        }

        return true;
    }

    public function mark_lost($lead_id, $reason, $note = '')
    {
        $l = $this->get($lead_id);
        if (!$l) {
            return 'Lead not found.';
        }
        if ($l->stage === 'won') {
            return 'A converted customer cannot be marked lost.';
        }
        $reason = substr(trim((string) $reason), 0, 40);
        if ($reason === '') {
            return 'A reason is required to mark a lead lost.';
        }
        $this->update_lead($lead_id, ['lost' => 1, 'junk' => 0, 'last_status_change' => date('Y-m-d H:i:s')],
            ['lost_reason' => $reason, 'lost_note' => substr(trim((string) $note), 0, 255) ?: null, 'next_action_at' => null, 'is_stale' => 0]);
        $this->event($lead_id, 'lost', ['from' => $l->stage, 'to' => $reason, 'note' => $note, 'log' => 'Marked lost: ' . $reason . ($note ? ' — ' . $note : '')]);
        hooks()->do_action('lead_marked_as_lost', ['lead_id' => $lead_id]);

        return true;
    }

    public function mark_junk($lead_id, $note = '')
    {
        $l = $this->get($lead_id);
        if (!$l) {
            return 'Lead not found.';
        }
        if ($l->stage === 'won') {
            return 'A converted customer cannot be marked junk.';
        }
        $this->update_lead($lead_id, ['junk' => 1, 'last_status_change' => date('Y-m-d H:i:s')], ['next_action_at' => null, 'lost_note' => substr(trim((string) $note), 0, 255) ?: null, 'is_stale' => 0]);
        $this->event($lead_id, 'junk', ['from' => $l->stage, 'note' => $note, 'log' => 'Marked junk' . ($note ? ': ' . $note : '')]);
        hooks()->do_action('lead_marked_as_junk', ['lead_id' => $lead_id]);

        return true;
    }

    public function reopen($lead_id, $note = '')
    {
        $l = $this->get($lead_id);
        if (!$l) {
            return 'Lead not found.';
        }
        if ($l->is_open) {
            return true;
        }
        if ($l->stage === 'won') {
            return 'Customers cannot be reopened as leads.';
        }
        $this->update_lead($lead_id, ['lost' => 0, 'junk' => 0, 'status' => shra_lead_stage_id('followup'), 'last_status_change' => date('Y-m-d H:i:s')],
            ['stage_key' => 'followup', 'next_action_at' => date('Y-m-d H:i:s', strtotime('+1 hour')), 'next_action_type' => 'call', 'lost_reason' => null, 'lost_note' => null, 'is_stale' => 0]);
        $this->event($lead_id, 'reopen', ['from' => $l->stage, 'to' => 'followup', 'note' => $note, 'log' => 'Reopened' . ($note ? ': ' . $note : '')]);
        if ($l->assigned) {
            $this->notify($l->assigned, 'shra_not_lead_reopened', [$l->name], $lead_id);
        }

        return true;
    }

    /** Update editable details (name, email, city, source, rider_for, age, package, expected, description, next action). */
    public function update_details($lead_id, array $in)
    {
        $l = $this->get($lead_id);
        if (!$l) {
            return 'Lead not found.';
        }
        $p    = db_prefix();
        $core = [];
        $ext  = [];
        if (isset($in['name']) && trim($in['name']) !== '') {
            $core['name'] = substr(trim($in['name']), 0, 191);
        }
        if (isset($in['phone']) && trim($in['phone']) !== '' && shra_phone_norm($in['phone']) !== $l->phone_norm) {
            if (!shra_phone_valid($in['phone'])) {
                return 'Enter a valid mobile number.';
            }
            $other = $this->find_by_phone($in['phone']);
            if ($other && (int) $other->id !== (int) $lead_id) {
                return 'That number already belongs to lead "' . $other->name . '" (' . ($other->agent_name ?: 'unassigned') . ').';
            }
            $core['phonenumber'] = substr(trim($in['phone']), 0, 50);
            $ext['phone_norm']   = shra_phone_norm($in['phone']);
            $this->event($lead_id, 'note', ['from' => $l->phonenumber, 'to' => $core['phonenumber'], 'note' => 'Phone changed', 'log' => 'Phone changed to ' . $core['phonenumber']]);
        }
        foreach (['email' => 100, 'city' => 100, 'address' => 100] as $k => $len) {
            if (array_key_exists($k, $in)) {
                $core[$k] = substr(trim((string) $in[$k]), 0, $len);
            }
        }
        if (array_key_exists('description', $in)) {
            $core['description'] = (string) $in['description'];
        }
        if (isset($in['source'])) {
            $core['source'] = (int) $in['source'];
        }
        if (isset($in['rider_for']) && in_array($in['rider_for'], ['self', 'child', 'both'])) {
            $ext['rider_for'] = $in['rider_for'];
        }
        if (array_key_exists('rider_age', $in)) {
            $ext['rider_age'] = $in['rider_age'] !== '' ? (int) $in['rider_age'] : null;
            if ($ext['rider_age'] !== null) {
                $ext['audience'] = $ext['rider_age'] < (int) get_option('shra_minor_age') ? 'children' : 'adults';
            }
        }
        if (isset($in['audience']) && in_array($in['audience'], ['children', 'adults', ''])) {
            $ext['audience'] = $in['audience'] ?: null;
        }
        if (array_key_exists('interest_package_id', $in)) {
            $ext['interest_package_id'] = (int) $in['interest_package_id'] ?: null;
        }
        if (array_key_exists('preferred_start_date', $in)) {
            $ext['preferred_start_date'] = shra_start_date($in['preferred_start_date'], true);
        }
        if (array_key_exists('preferred_batch', $in)) {
            $ext['preferred_batch'] = shra_batch_key($in['preferred_batch']);
        }
        if (array_key_exists('expected_value', $in) && $in['expected_value'] !== '') {
            $ext['expected_value'] = round((float) $in['expected_value'], 2);
            $core['lead_value']    = $ext['expected_value'];
        } elseif (!empty($ext['interest_package_id']) && (float) $l->expected_value <= 0) {
            $pk = $this->db->where('id', $ext['interest_package_id'])->get($p . 'shra_packages')->row();
            if ($pk) {
                $this->load->model('shra/shra_model');
                $ext['expected_value'] = (float) $this->shra_model->quote($pk)['total'];
                $core['lead_value']    = $ext['expected_value'];
            }
        }
        if (!empty($in['next_action_at']) && $l->is_open) {
            $ext['next_action_at'] = date('Y-m-d H:i:s', strtotime($in['next_action_at']));
        }
        if (array_key_exists('campaign', $in)) {
            $ext['campaign'] = substr(trim((string) $in['campaign']), 0, 80) ?: null;
        }
        $this->update_lead($lead_id, $core, $ext);
        $this->event($lead_id, 'note', ['note' => 'Details updated', 'meta' => array_keys($core + $ext)]);

        return true;
    }

    public function add_note($lead_id, $text)
    {
        $text = trim((string) $text);
        if ($text === '') {
            return 'Write something first.';
        }
        $this->db->insert(db_prefix() . 'notes', [
            'rel_id' => (int) $lead_id, 'rel_type' => 'lead', 'description' => $text,
            'date_contacted' => null, 'addedfrom' => (int) $this->staff_id(), 'dateadded' => date('Y-m-d H:i:s'),
        ]);
        $this->event($lead_id, 'note', ['note' => $text]);

        return true;
    }

    /* ═══════════════════════ Convert & revenue ═══════════════════════ */

    /** Create (or link) the rider for a lead so the counter can bill. Returns rider_id | error. */
    /**
     * @param array $opts rider_type ('learner' | 'guest'), package_id, source and
     *                    promote — all set by the public /inquire booking, where the
     *                    plan the visitor is paying for may differ from the one stored
     *                    on the lead. With no opts this behaves exactly as before: a
     *                    learner carrying the lead's own plan, and an already-known
     *                    rider returned untouched.
     */
    /**
     * Tie a lead to a rider created outside the pipeline (the /join page captures
     * both at once). Never steals a lead that already points at another rider —
     * a returning customer's won lead keeps its history.
     */
    public function link_rider($lead_id, $rider)
    {
        $l = $this->get($lead_id);
        if (!$l || $l->rider_id) {
            return false;
        }
        $this->update_lead($lead_id, ['client_id' => (int) $rider->client_id], ['rider_id' => (int) $rider->id]);
        $this->event($lead_id, 'note', ['note' => 'Rider ' . $rider->rider_no . ' registered on the join page', 'log' => 'Registered on the join page as rider ' . $rider->rider_no]);

        return true;
    }

    public function convert_to_rider($lead_id, array $opts = [])
    {
        $l = $this->get($lead_id);
        if (!$l) {
            return 'Lead not found.';
        }
        $this->load->model('shra/shra_model');

        $type       = ($opts['rider_type'] ?? '') === 'guest' ? 'guest' : 'learner';
        $package_id = (int) ($opts['package_id'] ?? 0) ?: null;
        // Upgrading an existing guest is only ever driven by a course purchase
        $promote    = !empty($opts['promote']);

        if ($l->rider_id) {
            $r = $this->shra_model->get_rider($l->rider_id);
            if ($r) {
                $this->align_rider($lead_id, $r, $type, $package_id, $promote);

                return (int) $r->id;
            }
        }
        $existing = $this->shra_model->find_rider_by_mobile($l->phonenumber);
        if ($existing) {
            $this->update_lead($lead_id, ['client_id' => (int) $existing->client_id], ['rider_id' => (int) $existing->id]);
            $this->event($lead_id, 'note', ['note' => 'Linked to existing rider ' . $existing->rider_no, 'log' => 'Linked to rider ' . $existing->rider_no]);
            $this->align_rider($lead_id, $existing, $type, $package_id, $promote);

            return (int) $existing->id;
        }
        $is_child = $l->rider_for === 'child' || $l->audience === 'children';
        $data = [
            'rider_type'           => $type,
            'full_name'            => $l->name,
            'mobile'               => $l->phonenumber,
            'email'                => $l->email,
            'address'              => $l->address ?: $l->city,
            'riding_level'         => shra_riding_levels()[0],
            'status'               => 'active',
            'preferred_package_id' => $package_id ?: $l->interest_package_id,
            'preferred_start_date' => $l->preferred_start_date ?? null,
            'preferred_batch'      => $l->preferred_batch ?? null,
            'notes'                => 'From lead #' . $l->id . ($l->source_name ? ' · ' . $l->source_name : '') . ($l->agent_name ? ' · agent ' . $l->agent_name : ''),
        ];
        if ($is_child) {
            $data['guardian_name']         = $l->name;
            $data['guardian_relationship'] = 'Guardian';
            if ($l->rider_age) {
                $data['dob'] = date('Y-m-d', strtotime('-' . (int) $l->rider_age . ' years'));
            }
        }
        $rider_id = $this->shra_model->add_rider($data, ($opts['source'] ?? '') === 'self' ? 'self' : 'staff');
        if (!$rider_id) {
            return 'Could not create the rider.';
        }
        $r = $this->shra_model->get_rider($rider_id);
        $this->update_lead($lead_id, ['client_id' => (int) $r->client_id], ['rider_id' => (int) $rider_id]);
        $this->event($lead_id, 'note', ['note' => 'Rider ' . $r->rider_no . ' created from lead', 'log' => 'Rider ' . $r->rider_no . ' created']);

        return (int) $rider_id;
    }

    /**
     * Point an already-known rider at the plan they are about to buy, and promote a
     * former guest to learner when that plan is a course (the counter does the same).
     * Only ever narrows to what the visitor just chose — never clears anything.
     */
    private function align_rider($lead_id, $rider, $type, $package_id, $promote = false)
    {
        $upd = [];
        if ($package_id && (int) $rider->preferred_package_id !== $package_id) {
            $upd['preferred_package_id'] = $package_id;
        }
        // The start date / batch asked for on this inquiry wins over an older one
        $l = $this->get($lead_id);
        $start = $l ? shra_start_date($l->preferred_start_date ?? '', true) : null;
        $batch = $l ? shra_batch_key($l->preferred_batch ?? '') : null;
        if ($start && $start !== ($rider->preferred_start_date ?? null)) {
            $upd['preferred_start_date'] = $start;
        }
        if ($batch && $batch !== ($rider->preferred_batch ?? null)) {
            $upd['preferred_batch'] = $batch;
        }
        if (count($upd)) {
            $this->db->where('id', (int) $rider->id)->update(db_prefix() . 'shra_riders', $upd);
        }
        if ($promote && $type === 'learner' && $rider->rider_type === 'guest'
            && $this->shra_model->set_rider_type($rider->id, 'learner')) {
            $this->event($lead_id, 'note', [
                'note' => 'Guest rider ' . $rider->rider_no . ' upgraded to a member (bought a course)',
                'log'  => 'Rider ' . $rider->rider_no . ' upgraded to a member',
            ]);
        }
    }

    /**
     * Called from Shra_model::create_bill(). Freezes revenue credit and marks the lead won.
     * Never throws — billing must not fail because of attribution.
     */
    public function credit_revenue($enrollment_id, $invoice_id, $rider, array $opts = [])
    {
        $p = db_prefix();
        try {
            $lead = null;
            if (!empty($opts['lead_id'])) {
                $lead = $this->get((int) $opts['lead_id']);
            }
            if (!$lead) {
                $row = $this->db->where('rider_id', (int) $rider->id)->get($p . 'shra_lead_ext')->row();
                if ($row) {
                    $lead = $this->get($row->lead_id);
                }
            }
            if (!$lead && empty($opts['no_phone_match'])) {
                $lead = $this->find_creditable_by_phone($rider->mobile);
            }
            if (!$lead || $lead->junk) {
                return null;
            }
            if (!empty($opts['credit_lead']) && (string) $opts['credit_lead'] === '0') {
                $this->event($lead->id, 'note', ['note' => 'Bill #' . $invoice_id . ' created WITHOUT crediting this lead (declined at counter)']);

                return null;
            }
            if ($this->db->where('enrollment_id', (int) $enrollment_id)->get($p . 'shra_lead_attribution')->row()) {
                return null;
            }
            $e = $this->db->where('id', (int) $enrollment_id)->get($p . 'shra_enrollments')->row();
            if (!$e) {
                return null;
            }
            $agent = (int) $lead->assigned ?: (int) $lead->addedfrom;
            $prev  = $this->db->where('lead_id', $lead->id)->order_by('id', 'ASC')->get($p . 'shra_lead_attribution')->row();
            $kind  = $prev ? 'repeat' : 'first';
            if ($kind === 'repeat') {
                $months = (int) get_option('shra_lead_repeat_credit_months');
                if ($months <= 0 || strtotime($prev->credited_at) < strtotime("-{$months} months")) {
                    return null; // renewal credit window closed
                }
            }
            $this->db->insert($p . 'shra_lead_attribution', [
                'lead_id'       => $lead->id,
                'agent_id'      => $agent,
                'rider_id'      => (int) $rider->id,
                'enrollment_id' => (int) $enrollment_id,
                'invoice_id'    => (int) $invoice_id,
                'kind'          => $kind,
                'amount_billed' => (float) $e->total,
                'amount_paid'   => (float) $e->paid_amount,
                'source_id'     => (int) $lead->source,
                'credited_by'   => $this->staff_id(),
                'credited_at'   => date('Y-m-d H:i:s'),
                'locked'        => 1,
            ]);

            $ext  = ['rider_id' => (int) $rider->id];
            $core = [];
            if ($lead->stage !== 'won') {
                $ext  += ['stage_key' => 'won', 'won_at' => date('Y-m-d H:i:s'), 'first_enrollment_id' => (int) $enrollment_id, 'next_action_at' => null, 'is_stale' => 0];
                $core  = ['status' => shra_lead_stage_id('won'), 'lost' => 0, 'junk' => 0, 'date_converted' => date('Y-m-d H:i:s'), 'client_id' => (int) $rider->client_id, 'last_status_change' => date('Y-m-d H:i:s')];
                if (empty($lead->visited_at)) {
                    $ext['visited_at'] = date('Y-m-d H:i:s');
                    $ext['visited_by'] = $this->staff_id();
                    $ext['visit_date'] = $lead->visit_date ?: date('Y-m-d');
                    $ext['visit_slot'] = $lead->visit_slot ?: 'Walk-in';
                }
            }
            $this->update_lead($lead->id, $core, $ext);
            $this->event($lead->id, 'won', ['to' => $e->total, 'note' => ($kind === 'repeat' ? 'Renewal' : 'First bill') . ' · ' . $e->package_name . ' · invoice #' . $invoice_id,
                'meta' => ['enrollment_id' => $enrollment_id, 'invoice_id' => $invoice_id, 'agent_id' => $agent, 'kind' => $kind],
                'log'  => ($kind === 'repeat' ? 'Renewal billed ' : 'Converted — billed ') . shra_money($e->total) . ' (' . $e->package_name . ')']);
            if ($agent) {
                $this->notify($agent, 'shra_not_lead_won', [$lead->name, shra_money($e->total)], $lead->id);
            }
            hooks()->do_action('lead_converted_to_customer', ['lead_id' => $lead->id, 'customer_id' => (int) $rider->client_id]);

            return $lead->id;
        } catch (Throwable $t) {
            log_activity('SHRA lead attribution failed: ' . $t->getMessage());

            return null;
        }
    }

    /** Keep attribution.amount_paid in step with the invoice payments. */
    public function sync_attribution_paid($enrollment_id)
    {
        $p = db_prefix();
        $e = $this->db->where('id', (int) $enrollment_id)->get($p . 'shra_enrollments')->row();
        if ($e) {
            $this->db->where('enrollment_id', $e->id)->update($p . 'shra_lead_attribution', ['amount_paid' => (float) $e->paid_amount, 'amount_billed' => (float) $e->total]);
        }
    }

    /* ═══════════════════════ Stats ═══════════════════════ */

    public function team_stats($from, $to)
    {
        $p   = db_prefix();
        $now = date('Y-m-d H:i:s');
        $f   = $from . ' 00:00:00';
        $t   = $to . ' 23:59:59';
        $sql = "SELECT s.staffid, CONCAT(s.firstname,' ',s.lastname) AS name, s.active,
            (SELECT COUNT(*) FROM {$p}leads l WHERE l.assigned = s.staffid AND l.dateadded BETWEEN ? AND ?) AS assigned,
            (SELECT COUNT(*) FROM {$p}shra_lead_events e WHERE e.staff_id = s.staffid AND e.event_type IN ('call','whatsapp') AND e.created_at BETWEEN ? AND ?) AS calls,
            (SELECT COUNT(DISTINCT e.lead_id) FROM {$p}shra_lead_events e WHERE e.staff_id = s.staffid AND e.event_type IN ('call','whatsapp') AND e.outcome IN ('interested','callback_requested','not_interested') AND e.created_at BETWEEN ? AND ?) AS contacted,
            (SELECT COUNT(DISTINCT e.lead_id) FROM {$p}shra_lead_events e JOIN {$p}leads l ON l.id = e.lead_id WHERE l.assigned = s.staffid AND e.event_type IN ('visit_scheduled','visit_rescheduled') AND e.created_at BETWEEN ? AND ?) AS visits_booked,
            (SELECT COUNT(DISTINCT e.lead_id) FROM {$p}shra_lead_events e JOIN {$p}leads l ON l.id = e.lead_id WHERE l.assigned = s.staffid AND e.event_type = 'visited' AND e.created_at BETWEEN ? AND ?) AS visited,
            (SELECT COUNT(DISTINCT e.lead_id) FROM {$p}shra_lead_events e JOIN {$p}leads l ON l.id = e.lead_id WHERE l.assigned = s.staffid AND e.event_type = 'confirmed' AND e.created_at BETWEEN ? AND ?) AS confirmed,
            (SELECT COUNT(*) FROM {$p}shra_lead_attribution a WHERE a.agent_id = s.staffid AND a.kind = 'first' AND a.credited_at BETWEEN ? AND ?) AS won,
            (SELECT COUNT(*) FROM {$p}shra_lead_attribution a WHERE a.agent_id = s.staffid AND a.kind = 'repeat' AND a.credited_at BETWEEN ? AND ?) AS renewals,
            (SELECT COALESCE(SUM(a.amount_billed),0) FROM {$p}shra_lead_attribution a WHERE a.agent_id = s.staffid AND a.credited_at BETWEEN ? AND ?) AS revenue,
            (SELECT COALESCE(SUM(a.amount_paid),0) FROM {$p}shra_lead_attribution a WHERE a.agent_id = s.staffid AND a.credited_at BETWEEN ? AND ?) AS collected,
            (SELECT COUNT(*) FROM {$p}leads l JOIN {$p}shra_lead_ext x ON x.lead_id = l.id WHERE l.assigned = s.staffid AND l.lost = 0 AND l.junk = 0 AND x.stage_key <> 'won') AS open_now,
            (SELECT COUNT(*) FROM {$p}leads l JOIN {$p}shra_lead_ext x ON x.lead_id = l.id WHERE l.assigned = s.staffid AND l.lost = 0 AND l.junk = 0 AND x.stage_key <> 'won' AND x.next_action_at < '{$now}') AS overdue_now,
            (SELECT COUNT(*) FROM {$p}leads l JOIN {$p}shra_lead_ext x ON x.lead_id = l.id WHERE l.assigned = s.staffid AND x.is_stale = 1 AND l.lost = 0 AND l.junk = 0 AND x.stage_key <> 'won') AS stale_now,
            (SELECT COUNT(*) FROM {$p}leads l WHERE l.assigned = s.staffid AND l.lost = 1 AND l.last_status_change BETWEEN ? AND ?) AS lost,
            (SELECT AVG(TIMESTAMPDIFF(HOUR, l.dateadded, x.won_at)) FROM {$p}leads l JOIN {$p}shra_lead_ext x ON x.lead_id = l.id WHERE l.assigned = s.staffid AND x.won_at BETWEEN ? AND ?) AS avg_hours_to_win,
            tg.calls_target, tg.visits_target, tg.revenue_target
            FROM {$p}staff s
            LEFT JOIN {$p}shra_lead_targets tg ON tg.staff_id = s.staffid AND tg.month = ?
            WHERE s.admin = 1 OR EXISTS (SELECT 1 FROM {$p}staff_permissions sp WHERE sp.staff_id = s.staffid AND sp.feature = 'shra' AND sp.capability IN ('leads_own','leads_all','leads_manage'))
               OR EXISTS (SELECT 1 FROM {$p}shra_lead_attribution a2 WHERE a2.agent_id = s.staffid)
            ORDER BY revenue DESC, won DESC, calls DESC";
        $b = [];
        for ($i = 0; $i < 12; $i++) {
            array_push($b, $f, $t);
        }
        $b[] = substr($from, 0, 7);
        $rows = $this->db->query($sql, $b)->result();
        foreach ($rows as $r) {
            $r->contact_rate = $r->assigned > 0 ? round($r->contacted / $r->assigned * 100) : 0;
            $r->show_rate    = $r->visits_booked > 0 ? round($r->visited / $r->visits_booked * 100) : 0;
            $r->win_rate     = $r->assigned > 0 ? round($r->won / $r->assigned * 100) : 0;
            $r->avg_days     = $r->avg_hours_to_win !== null ? round($r->avg_hours_to_win / 24, 1) : null;
        }

        return $rows;
    }

    public function source_stats($from, $to)
    {
        $p   = db_prefix();
        $f   = $from . ' 00:00:00';
        $t   = $to . ' 23:59:59';
        $sql = "SELECT s.id, s.name, COALESCE(m.monthly_cost,0) AS monthly_cost,
            (SELECT COUNT(*) FROM {$p}leads l WHERE l.source = s.id AND l.dateadded BETWEEN ? AND ?) AS leads,
            (SELECT COUNT(*) FROM {$p}leads l JOIN {$p}shra_lead_ext x ON x.lead_id = l.id WHERE l.source = s.id AND x.visited_at BETWEEN ? AND ?) AS visited,
            (SELECT COUNT(*) FROM {$p}shra_lead_attribution a WHERE a.source_id = s.id AND a.kind = 'first' AND a.credited_at BETWEEN ? AND ?) AS won,
            (SELECT COALESCE(SUM(a.amount_billed),0) FROM {$p}shra_lead_attribution a WHERE a.source_id = s.id AND a.credited_at BETWEEN ? AND ?) AS revenue,
            (SELECT COUNT(*) FROM {$p}leads l WHERE l.source = s.id AND l.lost = 1 AND l.last_status_change BETWEEN ? AND ?) AS lost
            FROM {$p}leads_sources s LEFT JOIN {$p}shra_lead_sources_meta m ON m.source_id = s.id
            ORDER BY leads DESC, revenue DESC";
        $rows   = $this->db->query($sql, [$f, $t, $f, $t, $f, $t, $f, $t, $f, $t])->result();
        $months = max(1, round((strtotime($to) - strtotime($from)) / (86400 * 30)));
        foreach ($rows as $r) {
            $r->cost = (float) $r->monthly_cost * $months;
            $r->cpl  = $r->leads > 0 && $r->cost > 0 ? round($r->cost / $r->leads) : null;
            $r->roi  = $r->cost > 0 ? round($r->revenue / $r->cost, 1) : null;
            $r->conv = $r->leads > 0 ? round($r->won / $r->leads * 100) : 0;
        }

        return $rows;
    }

    public function funnel_counts($scope_me = false)
    {
        $p   = db_prefix();
        $w   = $scope_me ? 'AND l.assigned = ' . (int) get_staff_user_id() : '';
        $out = array_fill_keys(array_keys(shra_lead_stage_defs()), 0);
        $rows = $this->db->query("SELECT CASE WHEN l.junk = 1 THEN 'junk' WHEN l.lost = 1 THEN 'lost' ELSE x.stage_key END AS k, COUNT(*) AS c
            FROM {$p}leads l JOIN {$p}shra_lead_ext x ON x.lead_id = l.id WHERE 1=1 {$w} GROUP BY k")->result();
        foreach ($rows as $r) {
            $out[$r->k] = (int) $r->c;
        }

        return $out;
    }

    public function lost_reasons($from, $to)
    {
        $p = db_prefix();

        return $this->db->query("SELECT x.lost_reason AS reason, COUNT(*) AS c FROM {$p}leads l JOIN {$p}shra_lead_ext x ON x.lead_id = l.id
            WHERE l.lost = 1 AND l.last_status_change BETWEEN ? AND ? GROUP BY x.lost_reason ORDER BY c DESC", [$from . ' 00:00:00', $to . ' 23:59:59'])->result();
    }

    /** Agent-level KPIs for My Day header (this month, or the month $in falls in). $staff_id 0 = whole team. */
    public function my_month($staff_id, $in = null)
    {
        $ts   = $in && strtotime($in) ? strtotime($in) : time();
        $rows = $this->team_stats(date('Y-m-01', $ts), date('Y-m-t', $ts));
        if ((int) $staff_id === 0) {
            return $this->team_totals($rows);
        }
        foreach ($rows as $r) {
            if ((int) $r->staffid === (int) $staff_id) {
                return $r;
            }
        }

        return null;
    }

    /** Every agent's month rolled into one row, for the All-staff header. */
    private function team_totals(array $rows)
    {
        $keys = ['assigned', 'calls', 'contacted', 'visits_booked', 'visited', 'confirmed', 'won', 'renewals',
            'revenue', 'collected', 'open_now', 'overdue_now', 'stale_now', 'lost', 'calls_target', 'visits_target', 'revenue_target'];
        $t = (object) array_fill_keys($keys, 0);
        foreach ($rows as $r) {
            foreach ($keys as $k) {
                $t->$k += (float) ($r->$k ?? 0);
            }
        }
        $t->staffid      = 0;
        $t->name         = 'All staff';
        $t->contact_rate = $t->assigned > 0 ? round($t->contacted / $t->assigned * 100) : 0;
        $t->show_rate    = $t->visits_booked > 0 ? round($t->visited / $t->visits_booked * 100) : 0;
        $t->win_rate     = $t->assigned > 0 ? round($t->won / $t->assigned * 100) : 0;
        $t->avg_days     = null;

        return $t;
    }

    /**
     * One agent's day, for the EOD report: what they did between 00:00 and 23:59 on
     * $date, where the month stands against target, and what is waiting tomorrow.
     */
    public function day_report($staff_id, $date)
    {
        $p   = db_prefix();
        $id  = (int) $staff_id;
        $f   = $date . ' 00:00:00';
        $t   = $date . ' 23:59:59';
        $tm  = date('Y-m-d', strtotime($date . ' +1 day'));
        $now = date('Y-m-d H:i:s');
        // Advances only exist once the module install run has created the table.
        $has_pay = $this->db->table_exists($p . 'shra_lead_payments');
        $pay_sql = $has_pay ? "(SELECT COALESCE(SUM(pm.amount),0) FROM {$p}shra_lead_payments pm WHERE pm.staff_id = ? AND pm.created_at BETWEEN ? AND ?)" : '0';

        // Calls / WhatsApp are credited to whoever logged them; everything else to
        // whoever owns the lead right now — same rule the leaderboard uses.
        $ev = "SELECT COUNT(DISTINCT e.lead_id) FROM {$p}shra_lead_events e JOIN {$p}leads l ON l.id = e.lead_id
               WHERE l.assigned = ? AND e.created_at BETWEEN ? AND ? AND e.event_type";
        $sql = "SELECT
            (SELECT COUNT(*) FROM {$p}shra_lead_events e WHERE e.staff_id = ? AND e.created_at BETWEEN ? AND ? AND e.event_type = 'call') AS calls,
            (SELECT COUNT(*) FROM {$p}shra_lead_events e WHERE e.staff_id = ? AND e.created_at BETWEEN ? AND ? AND e.event_type = 'whatsapp') AS whatsapp,
            (SELECT COUNT(DISTINCT e.lead_id) FROM {$p}shra_lead_events e WHERE e.staff_id = ? AND e.created_at BETWEEN ? AND ? AND e.event_type IN ('call','whatsapp')) AS touched,
            (SELECT COUNT(DISTINCT e.lead_id) FROM {$p}shra_lead_events e WHERE e.staff_id = ? AND e.created_at BETWEEN ? AND ? AND e.event_type IN ('call','whatsapp') AND e.outcome IN ('interested','callback_requested','not_interested')) AS connected,
            (SELECT COUNT(DISTINCT e.lead_id) FROM {$p}shra_lead_events e WHERE e.staff_id = ? AND e.created_at BETWEEN ? AND ? AND e.event_type IN ('call','whatsapp') AND e.outcome = 'interested') AS interested,
            (SELECT COUNT(*) FROM {$p}leads l WHERE l.assigned = ? AND l.dateadded BETWEEN ? AND ?) AS new_leads,
            ({$ev} IN ('visit_scheduled','visit_rescheduled')) AS booked,
            ({$ev} = 'visited') AS visited,
            ({$ev} = 'confirmed') AS confirmed,
            ({$ev} = 'no_show') AS no_show,
            (SELECT COUNT(*) FROM {$p}shra_lead_attribution a WHERE a.agent_id = ? AND a.credited_at BETWEEN ? AND ? AND a.kind = 'first') AS won,
            (SELECT COUNT(*) FROM {$p}shra_lead_attribution a WHERE a.agent_id = ? AND a.credited_at BETWEEN ? AND ? AND a.kind = 'repeat') AS renewals,
            (SELECT COALESCE(SUM(a.amount_billed),0) FROM {$p}shra_lead_attribution a WHERE a.agent_id = ? AND a.credited_at BETWEEN ? AND ?) AS revenue,
            (SELECT COALESCE(SUM(a.amount_paid),0) FROM {$p}shra_lead_attribution a WHERE a.agent_id = ? AND a.credited_at BETWEEN ? AND ?) AS collected,
            {$pay_sql} AS advance,
            (SELECT COUNT(*) FROM {$p}leads l WHERE l.assigned = ? AND l.last_status_change BETWEEN ? AND ? AND l.lost = 1) AS lost,
            (SELECT COUNT(*) FROM {$p}leads l JOIN {$p}shra_lead_ext x ON x.lead_id = l.id WHERE l.assigned = ? AND l.lost = 0 AND l.junk = 0 AND x.stage_key <> 'won') AS open_now,
            (SELECT COUNT(*) FROM {$p}leads l JOIN {$p}shra_lead_ext x ON x.lead_id = l.id WHERE l.assigned = ? AND l.lost = 0 AND l.junk = 0 AND x.stage_key <> 'won' AND x.next_action_at < ?) AS overdue_now,
            (SELECT COUNT(*) FROM {$p}leads l JOIN {$p}shra_lead_ext x ON x.lead_id = l.id WHERE l.assigned = ? AND l.lost = 0 AND l.junk = 0 AND x.stage_key <> 'won' AND DATE(x.next_action_at) = ?) AS due_tomorrow";

        $b = [];
        for ($i = 0, $triples = $has_pay ? 16 : 15; $i < $triples; $i++) {
            array_push($b, $id, $f, $t);
        }
        array_push($b, $id, $id, $now, $id, $tm);
        $today = $this->db->query($sql, $b)->row();

        // Names make the report worth reading — who joined, who is coming tomorrow.
        $wins = $this->db->query("SELECT l.name, e.package_name, a.amount_billed, a.kind FROM {$p}shra_lead_attribution a
            LEFT JOIN {$p}leads l ON l.id = a.lead_id LEFT JOIN {$p}shra_enrollments e ON e.id = a.enrollment_id
            WHERE a.agent_id = ? AND a.credited_at BETWEEN ? AND ? ORDER BY a.id ASC LIMIT 10", [$id, $f, $t])->result();
        $visits = $this->db->query("SELECT l.name, l.phonenumber, x.visit_slot FROM {$p}leads l JOIN {$p}shra_lead_ext x ON x.lead_id = l.id
            WHERE l.assigned = ? AND l.lost = 0 AND l.junk = 0 AND x.stage_key = 'visit_scheduled' AND x.visit_date = ?
            ORDER BY x.visit_slot ASC LIMIT 10", [$id, $tm])->result();

        return [
            'agent_id' => $id,
            'agent'    => get_staff_full_name($id),
            'date'     => $date,
            'tomorrow' => $tm,
            'today'    => $today,
            'month'    => $this->my_month($id, $date),
            'wins'     => $wins,
            'visits'   => $visits,
        ];
    }

    /** Dashboard tiles. */
    public function summary()
    {
        $p  = db_prefix();
        $now = date('Y-m-d H:i:s');
        $wk = shra_lead_weekend_dates();
        $r  = (array) $this->db->query("SELECT
            (SELECT COUNT(*) FROM {$p}leads l JOIN {$p}shra_lead_ext x ON x.lead_id = l.id WHERE l.lost = 0 AND l.junk = 0 AND x.stage_key <> 'won') AS open_leads,
            (SELECT COUNT(*) FROM {$p}leads l JOIN {$p}shra_lead_ext x ON x.lead_id = l.id WHERE l.lost = 0 AND l.junk = 0 AND x.stage_key <> 'won' AND x.next_action_at < '{$now}') AS overdue,
            (SELECT COUNT(*) FROM {$p}leads l JOIN {$p}shra_lead_ext x ON x.lead_id = l.id WHERE l.lost = 0 AND l.junk = 0 AND x.stage_key = 'visit_scheduled' AND x.visit_date IN (?, ?)) AS weekend_visits,
            (SELECT COUNT(*) FROM {$p}leads l WHERE l.dateadded >= ?) AS new_month,
            (SELECT COALESCE(SUM(a.amount_billed),0) FROM {$p}shra_lead_attribution a WHERE a.credited_at >= ?) AS revenue_month,
            (SELECT COUNT(*) FROM {$p}shra_lead_attribution a WHERE a.kind = 'first' AND a.credited_at >= ?) AS won_month",
            [$wk['sat'], $wk['sun'], date('Y-m-01 00:00:00'), date('Y-m-01 00:00:00'), date('Y-m-01 00:00:00')])->row();
        $top = $this->db->query("SELECT a.agent_id, CONCAT(s.firstname,' ',s.lastname) AS name, SUM(a.amount_billed) AS revenue FROM {$p}shra_lead_attribution a
            LEFT JOIN {$p}staff s ON s.staffid = a.agent_id WHERE a.credited_at >= ? GROUP BY a.agent_id ORDER BY revenue DESC LIMIT 1", [date('Y-m-01 00:00:00')])->row();
        $r['top_agent'] = $top;

        return $r;
    }

    /* ═══════════════════════ Targets / sources meta ═══════════════════════ */

    public function save_targets($month, array $rows)
    {
        $p = db_prefix();
        foreach ($rows as $staff_id => $t) {
            $data = [
                'staff_id'       => (int) $staff_id,
                'month'          => $month,
                'calls_target'   => (int) ($t['calls'] ?? 0),
                'visits_target'  => (int) ($t['visits'] ?? 0),
                'revenue_target' => round((float) ($t['revenue'] ?? 0), 2),
            ];
            $ex = $this->db->where('staff_id', (int) $staff_id)->where('month', $month)->get($p . 'shra_lead_targets')->row();
            if ($ex) {
                $this->db->where('id', $ex->id)->update($p . 'shra_lead_targets', $data);
            } else {
                $this->db->insert($p . 'shra_lead_targets', $data);
            }
        }
    }

    public function get_targets($month)
    {
        $out = [];
        foreach ($this->db->where('month', $month)->get(db_prefix() . 'shra_lead_targets')->result() as $r) {
            $out[(int) $r->staff_id] = $r;
        }

        return $out;
    }

    public function save_source_cost($source_id, $cost)
    {
        $p  = db_prefix();
        $ex = $this->db->where('source_id', (int) $source_id)->get($p . 'shra_lead_sources_meta')->row();
        if ($ex) {
            $this->db->where('id', $ex->id)->update($p . 'shra_lead_sources_meta', ['monthly_cost' => round((float) $cost, 2)]);
        } else {
            $this->db->insert($p . 'shra_lead_sources_meta', ['source_id' => (int) $source_id, 'monthly_cost' => round((float) $cost, 2), 'active' => 1]);
        }
    }

    public function sources()
    {
        $p = db_prefix();

        return $this->db->query("SELECT s.*, COALESCE(m.monthly_cost,0) AS monthly_cost FROM {$p}leads_sources s LEFT JOIN {$p}shra_lead_sources_meta m ON m.source_id = s.id ORDER BY s.name ASC")->result();
    }

    /* ═══════════════════════ Import ═══════════════════════ */

    /**
     * Read an uploaded sheet (any encoding, any separator, any column order)
     * and decide what every column is. Returns the parsed sheet plus 'map'
     * (column index → target) and 'auto' (what the guesser proposed, so the
     * screen can show which columns were changed by hand).
     *
     * @param string     $text       raw file contents
     * @param array|null $override   column index → target chosen on screen
     * @param bool|null  $has_header null = decide from the first row
     */
    public function import_read($text, $override = null, $has_header = null)
    {
        require_once module_dir_path(SHRA_MODULE_NAME, 'libraries/Shra_import.php');
        $sheet = Shra_import::parse($text, $has_header);
        $auto  = Shra_import::guess_map($sheet['headers'], $sheet['rows'], $this->import_learned(), $sheet['has_header']);
        $map   = $auto;
        if (is_array($override)) {
            $valid = array_keys(Shra_import::targets());
            foreach ($sheet['headers'] as $i => $h) {
                if (isset($override[$i]) && in_array($override[$i], $valid, true)) {
                    $map[$i] = $override[$i];
                }
            }
        }
        $sheet['map']  = $map;
        $sheet['auto'] = $auto;

        return $sheet;
    }

    /** Header wording this academy has already mapped by hand: normalised header → target. */
    public function import_learned()
    {
        $m = json_decode((string) get_option('shra_lead_import_map'), true);

        return is_array($m) ? $m : [];
    }

    /** Remember a confirmed mapping so the same sheet shape maps itself next time. */
    public function import_learn(array $headers, array $map)
    {
        require_once module_dir_path(SHRA_MODULE_NAME, 'libraries/Shra_import.php');
        $learned = $this->import_learned();
        foreach ($headers as $i => $h) {
            $n = Shra_import::norm_header($h);
            if ($n !== '' && !preg_match('/^column \d+$/', $n) && isset($map[$i])) {
                $learned[$n] = $map[$i];
            }
        }
        if (count($learned) > 400) {
            $learned = array_slice($learned, -400, null, true);
        }
        update_option('shra_lead_import_map', json_encode($learned));
    }

    /**
     * Preview — or run, when $commit — an import of a sheet read by import_read().
     * $opts: default_source, default_agent, create_sources.
     * Returns ['rows' => [… status new|duplicate|invalid …], 'counts' => […]].
     */
    public function import_run(array $sheet, array $opts = [], $commit = false)
    {
        require_once module_dir_path(SHRA_MODULE_NAME, 'libraries/Shra_import.php');
        $headers = $sheet['headers'];
        $map     = $sheet['map'];
        $def_src = (int) ($opts['default_source'] ?? 0);
        $def_agt = (int) ($opts['default_agent'] ?? 0);
        $make    = !empty($opts['create_sources']);

        $sources = $source_names = [];
        foreach ($this->sources() as $s) {
            $sources[mb_strtolower($s->name)] = (int) $s->id;
            $source_names[(int) $s->id]       = $s->name;
        }
        $agents = $agent_names = [];
        foreach (shra_lead_agents(false) as $a) {
            $agents[mb_strtolower($a->email)]     = (int) $a->staffid;
            $agents[mb_strtolower($a->full_name)] = (int) $a->staffid;
            $agent_names[(int) $a->staffid]       = $a->full_name;
        }
        $this->load->model('shra/shra_model');
        $packages = $this->shra_model->get_packages(false);
        $auto_rr  = get_option('shra_lead_auto_assign') == '1';

        $counts = ['new' => 0, 'duplicate' => 0, 'invalid' => 0];
        $seen   = [];
        $out    = [];
        $offset = $sheet['has_header'] ? 2 : 1;

        foreach ($sheet['rows'] as $n => $r) {
            $f     = array_fill_keys(Shra_import::single_targets(), '');
            $notes = [];
            foreach ($map as $i => $t) {
                $v = trim((string) ($r[$i] ?? ''));
                if ($v === '' || $t === 'ignore') {
                    continue;
                }
                if ($t === 'extra') {
                    $label   = Shra_import::label($headers[$i]);
                    $notes[] = ($label !== '' ? $label . ': ' : '') . Shra_import::humanize($v);
                } elseif ($t === 'notes') {
                    $notes[] = Shra_import::humanize($v);
                } elseif ($f[$t] === '') {
                    $f[$t] = $v;
                }
            }

            $name = $f['name'] !== '' ? $f['name'] : trim($f['first_name'] . ' ' . $f['last_name']);
            if ($name === '' && $f['email'] !== '') {
                $name = Shra_import::humanize(str_replace(['.', '_', '-'], ' ', explode('@', $f['email'])[0]));
            }
            if ($f['interest'] !== '') {
                array_unshift($notes, 'Interested in: ' . Shra_import::humanize($f['interest']));
            }
            [$src_id, $src_label, $src_new] = $this->import_source($f['source'], $sources, $source_names, $def_src, $make, $commit);
            $agent_id = $agents[mb_strtolower($f['agent'])] ?? $def_agt;
            $created  = Shra_import::parse_date($f['created_at']);

            $row = [
                'line'    => $n + $offset,
                'name'    => $name,
                'phone'   => $f['phone'],
                'email'   => $f['email'],
                'city'    => $f['city'],
                'source'  => $src_label . ($src_new ? ' (new)' : ''),
                'agent'   => $agent_id ? ($agent_names[$agent_id] ?? get_staff_full_name($agent_id)) : ($auto_rr ? 'Auto' : '—'),
                'created' => $created,
                'notes'   => implode("\n", $notes),
                'status'  => 'new',
                'message' => '',
            ];

            $norm = shra_phone_norm($f['phone']);
            if ($name === '' || !shra_phone_valid($f['phone'])) {
                $row['status']  = 'invalid';
                $row['message'] = $name === '' ? 'No name in this row' : 'Phone missing or invalid';
            } elseif (isset($seen[$norm])) {
                $row['status']  = 'duplicate';
                $row['message'] = 'Repeated in this file (line ' . $seen[$norm] . ')';
            } elseif ($dup = $this->find_by_phone($norm)) {
                $row['status']  = 'duplicate';
                $row['message'] = 'Exists: lead #' . $dup->id . ' (' . ($dup->agent_name ?: 'unassigned') . ', ' . shra_lead_stage_label($dup->stage) . ')';
            } else {
                $seen[$norm] = $n + $offset;
            }

            if ($row['status'] === 'new' && $commit) {
                $res = $this->capture([
                    'name'                => $name,
                    'phone'               => $f['phone'],
                    'email'               => $f['email'],
                    'city'                => $f['city'],
                    'address'             => $f['address'],
                    'source'              => $src_id,
                    'assigned'            => $agent_id,
                    'campaign'            => $f['campaign'],
                    'rider_for'           => $this->import_rider_for($f['rider_for']),
                    'rider_age'           => is_numeric($f['age']) ? (int) $f['age'] : '',
                    'interest_package_id' => $this->import_match_package($f['interest'], $packages),
                    'description'         => $row['notes'],
                    'dateadded'           => $created,
                ], 'import');
                if (is_string($res)) {
                    $row['status']  = 'invalid';
                    $row['message'] = $res;
                } elseif (!empty($res['duplicate'])) {
                    $row['status']  = 'duplicate';
                    $row['message'] = 'Exists: lead #' . $res['lead_id'];
                } else {
                    $row['message'] = 'Imported as #' . $res['lead_id'];
                }
            }
            $counts[$row['status']]++;
            $out[] = $row;
        }

        return ['rows' => $out, 'counts' => $counts];
    }

    /**
     * Sheet value → lead source id. Known shorthands (ig, fb, gads…) are spelled
     * out, an unknown name is created when the manager asked for it.
     * Returns [id, label, was_created].
     */
    private function import_source($raw, array &$sources, array &$names, $default, $create, $commit)
    {
        $fallback = [(int) $default, $names[(int) $default] ?? '—', false];
        $raw      = trim((string) $raw);
        if ($raw === '') {
            return $fallback;
        }
        $aliases = [
            'ig' => 'Instagram', 'insta' => 'Instagram', 'instagram' => 'Instagram',
            'fb' => 'Facebook', 'facebook' => 'Facebook', 'messenger' => 'Facebook',
            'meta' => 'Meta Ads', 'an' => 'Meta Ads', 'audience network' => 'Meta Ads',
            'google' => 'Google', 'adwords' => 'Google', 'gads' => 'Google', 'google ads' => 'Google',
            'wa' => 'WhatsApp', 'whatsapp' => 'WhatsApp', 'walk in' => 'Walk-in', 'walkin' => 'Walk-in',
            'web' => 'Website', 'website' => 'Website', 'referral' => 'Referral', 'ref' => 'Referral',
        ];
        $key   = mb_strtolower($raw);
        $label = $aliases[$key] ?? $raw;
        foreach ([$key, mb_strtolower($label)] as $k) {
            if (isset($sources[$k])) {
                return [$sources[$k], $names[$sources[$k]], false];
            }
        }
        if (!$create) {
            return $fallback;
        }
        if (!$commit) {
            return [(int) $default, $label, true];
        }
        $label = substr($label, 0, 100);
        $this->db->insert(db_prefix() . 'leads_sources', ['name' => $label]);
        $id = (int) $this->db->insert_id();
        if (!$id) {
            return $fallback;
        }
        $sources[$key] = $sources[mb_strtolower($label)] = $id;
        $names[$id]    = $label;

        return [$id, $label, true];
    }

    /** "my_child" / "both myself & my child" → the rider_for value the lead page uses. */
    private function import_rider_for($raw)
    {
        $v = mb_strtolower(str_replace('_', ' ', trim((string) $raw)));
        if ($v === '') {
            return 'self';
        }
        $child = preg_match('/child|kid|son|daughter|ward/', $v);
        $self  = preg_match('/myself|my self|self|adult/', $v);
        if (strpos($v, 'both') !== false || ($child && $self)) {
            return 'both';
        }

        return $child ? 'child' : 'self';
    }

    /** Free-text interest → package id, only when the wording clearly points at one. */
    private function import_match_package($raw, array $packages)
    {
        $v = $this->import_slug($raw);
        if ($v === '' || !count($packages)) {
            return 0;
        }
        $best = [0, 0];
        foreach ($packages as $p) {
            $n = $this->import_slug($p->name);
            if ($n === '') {
                continue;
            }
            if ($n === $v) {
                return (int) $p->id;
            }
            similar_text($n, $v, $pct);
            if ($pct > $best[1]) {
                $best = [(int) $p->id, $pct];
            }
        }

        return $best[1] >= 85 ? $best[0] : 0;
    }

    private function import_slug($v)
    {
        return trim(mb_strtolower(preg_replace('/[^\p{L}\p{N}]+/u', ' ', str_replace('_', ' ', (string) $v))));
    }

    /* ═══════════════════════ Cron ═══════════════════════ */

    public function run_cron()
    {
        $last = (int) get_option('shra_lead_last_cron');
        if (time() - $last < 600) {
            return;
        }
        update_option('shra_lead_last_cron', time());
        $p      = db_prefix();
        $now    = date('Y-m-d H:i:s');
        $days   = max(1, (int) get_option('shra_lead_stale_days'));
        $cutoff = date('Y-m-d H:i:s', time() - $days * 86400);

        // 1. SLA breach — new leads never called within the SLA
        $breached = $this->db->query("SELECT l.id, l.name, l.assigned FROM {$p}leads l JOIN {$p}shra_lead_ext x ON x.lead_id = l.id
            WHERE l.lost = 0 AND l.junk = 0 AND x.stage_key = 'new' AND x.call_attempts = 0 AND x.sla_notified = 0 AND x.next_action_at < '{$now}'")->result();
        $managers = shra_lead_manager_ids();
        foreach ($breached as $b) {
            $this->db->where('lead_id', $b->id)->update($p . 'shra_lead_ext', ['sla_notified' => 1]);
            $this->event($b->id, 'sla_breach', ['staff_id' => null, 'note' => 'Not called within SLA']);
            $targets = array_unique(array_merge($b->assigned ? [(int) $b->assigned] : [], $managers));
            foreach ($targets as $sid) {
                add_notification(['description' => 'shra_not_lead_sla', 'touserid' => $sid, 'fromcompany' => 1, 'link' => 'shra/shra_leads/view/' . $b->id, 'additional_data' => serialize([$b->name])]);
            }
            if (count($targets)) {
                pusher_trigger_notification($targets);
            }
        }

        // 2. Stale — open leads with no activity for N days
        $this->db->query("UPDATE {$p}leads l JOIN {$p}shra_lead_ext x ON x.lead_id = l.id SET x.is_stale = 1
            WHERE l.lost = 0 AND l.junk = 0 AND x.stage_key <> 'won' AND x.is_stale = 0
              AND COALESCE(l.lastcontact, l.dateadded) < '{$cutoff}'
              AND NOT EXISTS (SELECT 1 FROM {$p}shra_lead_events e WHERE e.lead_id = l.id AND e.created_at > '{$cutoff}')");

        // 3. Daily manager digest at/after 08:00
        if (get_option('shra_lead_manager_digest') == '1' && date('H') >= 8 && get_option('shra_lead_last_digest') !== date('Y-m-d')) {
            update_option('shra_lead_last_digest', date('Y-m-d'));
            $wk   = shra_lead_weekend_dates();
            $sum  = $this->summary();
            $over = $this->db->query("SELECT CONCAT(s.firstname,' ',s.lastname) AS name, COUNT(*) AS c FROM {$p}leads l JOIN {$p}shra_lead_ext x ON x.lead_id = l.id
                LEFT JOIN {$p}staff s ON s.staffid = l.assigned WHERE l.lost = 0 AND l.junk = 0 AND x.stage_key <> 'won' AND x.next_action_at < '{$now}' GROUP BY l.assigned ORDER BY c DESC LIMIT 5")->result();
            $parts = [];
            foreach ($over as $o) {
                $parts[] = ($o->name ?: 'Unassigned') . ' ' . $o->c;
            }
            $yday = $this->db->query("SELECT COALESCE(SUM(amount_billed),0) AS v FROM {$p}shra_lead_attribution WHERE credited_at >= ? AND credited_at < ?", [date('Y-m-d 00:00:00', strtotime('-1 day')), date('Y-m-d 00:00:00')])->row()->v;
            $text = 'Open ' . $sum['open_leads'] . ' · Overdue ' . $sum['overdue'] . (count($parts) ? ' (' . implode(', ', $parts) . ')' : '') . ' · Weekend visits ' . $sum['weekend_visits'] . ' · Yesterday revenue from leads ' . shra_money($yday);
            foreach ($managers as $sid) {
                add_notification(['description' => 'shra_not_lead_digest', 'touserid' => $sid, 'fromcompany' => 1, 'link' => 'shra/shra_leads/team', 'additional_data' => serialize([$text])]);
            }
            if (count($managers)) {
                pusher_trigger_notification($managers);
            }
        }

        // 4. Join-page registrations that never turned into money — reclaim the
        //    rider, keep the lead. A self-registered rider with no enrollment
        //    after the grace window paid neither online nor at the desk, so the
        //    rider row goes and the linked lead stays with its agent, who is
        //    nudged to follow up. Blank option = 2880 min (48 h); 0 switches the
        //    reclaim off.
        $mins = get_option('shra_join_reclaim_minutes');
        $mins = ($mins === '' || $mins === false || $mins === null) ? 2880 : (int) $mins;
        if ($mins > 0) {
            $rcut      = date('Y-m-d H:i:s', time() - $mins * 60);
            $abandoned = $this->db->query("SELECT r.id, r.rider_no, r.full_name, x.lead_id, l.assigned
                FROM {$p}shra_riders r
                JOIN {$p}shra_lead_ext x ON x.rider_id = r.id
                JOIN {$p}leads l ON l.id = x.lead_id
                WHERE r.source = 'self' AND r.created_at < '{$rcut}'
                  AND NOT EXISTS (SELECT 1 FROM {$p}shra_enrollments e WHERE e.rider_id = r.id)
                LIMIT 50")->result();
            if (count($abandoned)) {
                $this->load->model('shra/shra_model');
                $this->load->model('invoices_model');
            }
            foreach ($abandoned as $a) {
                // Void the open checkout and its unpaid invoice first, so a late
                // gateway webhook cannot pay for a rider that no longer exists.
                $open = $this->db->where('rider_id', $a->id)->where('status', 'pending')->get($p . 'shra_join_checkouts')->result();
                foreach ($open as $c) {
                    $this->db->where('id', $c->id)->update($p . 'shra_join_checkouts', ['status' => 'abandoned']);
                    $this->invoices_model->mark_as_cancelled((int) $c->invoice_id);
                }
                $this->db->where('lead_id', $a->lead_id)->update($p . 'shra_lead_ext', ['rider_id' => null]);
                $this->shra_model->delete_rider($a->id);
                $this->event($a->lead_id, 'note', ['staff_id' => null,
                    'note' => 'Join-page registration (rider ' . $a->rider_no . ') was never paid — the rider entry was removed; the lead stays open for follow-up.',
                    'log'  => 'Join not completed — back to lead']);
                if ($a->assigned) {
                    $this->notify($a->assigned, 'shra_not_lead_join_unpaid', [$a->full_name], $a->lead_id);
                }
            }
        }
    }
}
