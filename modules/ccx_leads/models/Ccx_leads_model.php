<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Ccx_leads_model extends App_Model
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('leads_model');
        $this->_ensure_field_settings_table();
    }

    /**
     * Self-healing: ensure the field settings table exists and is seeded
     */
    private function _ensure_field_settings_table()
    {
        if (!$this->db->table_exists(db_prefix() . 'ccx_lead_field_settings')) {
            $CI = &get_instance();
            require_once(module_dir_path('ccx_leads') . 'install.php');
        } elseif ($this->db->count_all(db_prefix() . 'ccx_lead_field_settings') == 0) {
            $this->seed_default_fields();
        }
    }

    /**
     * Get all field settings ordered by field_order
     */
    public function get_field_settings()
    {
        $this->db->order_by('field_order', 'asc');
        return $this->db->get(db_prefix() . 'ccx_lead_field_settings')->result_array();
    }

    /**
     * Get field settings as slug => settings map for quick lookup
     */
    public function get_field_settings_map()
    {
        $fields = $this->get_field_settings();
        $map = [];
        foreach ($fields as $f) {
            $map[$f['slug']] = $f;
        }
        return $map;
    }

    /**
     * Update a single field setting
     */
    public function update_field_setting($id, $data)
    {
        $this->db->where('id', $id);
        return $this->db->update(db_prefix() . 'ccx_lead_field_settings', $data);
    }

    /**
     * Seed default fields into the table
     */
    public function seed_default_fields()
    {
        $defaults = [
            ['slug' => 'name', 'label' => 'Name', 'active' => 1, 'required' => 1, 'field_order' => 1],
            ['slug' => 'title', 'label' => 'Title', 'active' => 1, 'required' => 0, 'field_order' => 2],
            ['slug' => 'email', 'label' => 'Email', 'active' => 1, 'required' => 0, 'field_order' => 3],
            ['slug' => 'phonenumber', 'label' => 'Phone', 'active' => 1, 'required' => 0, 'field_order' => 4],
            ['slug' => 'website', 'label' => 'Website', 'active' => 1, 'required' => 0, 'field_order' => 5],
            ['slug' => 'lead_value', 'label' => 'Lead Value', 'active' => 1, 'required' => 0, 'field_order' => 6],
            ['slug' => 'company', 'label' => 'Company', 'active' => 1, 'required' => 0, 'field_order' => 7],
            ['slug' => 'address', 'label' => 'Address', 'active' => 1, 'required' => 0, 'field_order' => 8],
            ['slug' => 'city', 'label' => 'City', 'active' => 1, 'required' => 0, 'field_order' => 9],
            ['slug' => 'state', 'label' => 'State', 'active' => 1, 'required' => 0, 'field_order' => 10],
            ['slug' => 'country', 'label' => 'Country', 'active' => 1, 'required' => 0, 'field_order' => 11],
            ['slug' => 'zip', 'label' => 'Zip Code', 'active' => 1, 'required' => 0, 'field_order' => 12],
            ['slug' => 'description', 'label' => 'Description', 'active' => 1, 'required' => 0, 'field_order' => 13],
            ['slug' => 'status', 'label' => 'Status', 'active' => 1, 'required' => 1, 'field_order' => 14],
            ['slug' => 'source', 'label' => 'Source', 'active' => 1, 'required' => 0, 'field_order' => 15],
            ['slug' => 'assigned', 'label' => 'Assigned', 'active' => 1, 'required' => 0, 'field_order' => 16],
            ['slug' => 'tags', 'label' => 'Tags', 'active' => 1, 'required' => 0, 'field_order' => 17],
            ['slug' => 'is_public', 'label' => 'Public', 'active' => 1, 'required' => 0, 'field_order' => 18],
        ];

        foreach ($defaults as $field) {
            // Only insert if slug not already present
            if ($this->db->where('slug', $field['slug'])->count_all_results(db_prefix() . 'ccx_lead_field_settings') == 0) {
                $this->db->insert(db_prefix() . 'ccx_lead_field_settings', $field);
            }
        }
    }

    /**
     * Get the 3-column field layout.
     * Returns ['1' => [...fields], '2' => [...], '3' => [...]]
     */
    public function get_field_layout()
    {
        $saved = get_option('ccx_leads_field_layout');
        if ($saved) {
            $layout = json_decode($saved, true);
            if (is_array($layout) && !empty($layout)) {
                return $layout;
            }
        }

        // Build default layout from current fields
        return $this->build_default_layout();
    }

    /**
     * Build default 3-column layout based on slug conventions
     */
    private function build_default_layout()
    {
        $col1_slugs = ['status', 'source', 'assigned'];
        $col2_slugs = ['name', 'title', 'email', 'website', 'phonenumber', 'lead_value', 'company'];
        $col3_slugs = ['address', 'city', 'state', 'country', 'zip', 'description', 'is_public', 'tags'];

        $layout = ['1' => [], '2' => [], '3' => []];

        // Standard fields
        $this->db->order_by('field_order', 'asc');
        $standard = $this->db->get(db_prefix() . 'ccx_lead_field_settings')->result_array();
        foreach ($standard as $f) {
            $item = [
                'type' => 'standard',
                'id' => $f['id'],
                'slug' => $f['slug'],
                'label' => $f['label'],
                'active' => $f['active'],
            ];
            if (in_array($f['slug'], $col1_slugs)) {
                $layout['1'][] = $item;
            } elseif (in_array($f['slug'], $col2_slugs)) {
                $layout['2'][] = $item;
            } else {
                $layout['3'][] = $item;
            }
        }

        // Custom fields go to column 3 by default
        $this->db->where('fieldto', 'leads');
        $this->db->order_by('field_order', 'asc');
        $custom = $this->db->get(db_prefix() . 'customfields')->result_array();
        foreach ($custom as $f) {
            $layout['3'][] = [
                'type' => 'custom',
                'id' => $f['id'],
                'slug' => $f['slug'],
                'label' => $f['name'],
                'active' => $f['active'],
            ];
        }

        return $layout;
    }

    /**
     * Save the 3-column field layout + field_order values
     * @param array $columns ['1' => [{type, id}, ...], '2' => [...], '3' => [...]]
     */
    public function save_field_layout($columns)
    {
        // Build layout JSON for storage and update field_order in DB
        $layout = ['1' => [], '2' => [], '3' => []];
        $global_order = 1;

        foreach (['1', '2', '3'] as $col) {
            if (!isset($columns[$col]) || !is_array($columns[$col]))
                continue;
            foreach ($columns[$col] as $item) {
                $type = $item['type'];
                $id = intval($item['id']);

                // Get field info for storage
                if ($type === 'standard') {
                    $row = $this->db->where('id', $id)->get(db_prefix() . 'ccx_lead_field_settings')->row_array();
                    if ($row) {
                        $layout[$col][] = [
                            'type' => 'standard',
                            'id' => $id,
                            'slug' => $row['slug'],
                            'label' => $row['label'],
                            'active' => $row['active'],
                        ];
                        $this->db->where('id', $id);
                        $this->db->update(db_prefix() . 'ccx_lead_field_settings', ['field_order' => $global_order]);
                    }
                } elseif ($type === 'custom') {
                    $row = $this->db->where('id', $id)->where('fieldto', 'leads')->get(db_prefix() . 'customfields')->row_array();
                    if ($row) {
                        $layout[$col][] = [
                            'type' => 'custom',
                            'id' => $id,
                            'slug' => $row['slug'],
                            'label' => $row['name'],
                            'active' => $row['active'],
                        ];
                        $this->db->where('id', $id);
                        $this->db->where('fieldto', 'leads');
                        $this->db->update(db_prefix() . 'customfields', ['field_order' => $global_order]);
                    }
                }
                $global_order++;
            }
        }

        // Save layout JSON as option
        if (get_option('ccx_leads_field_layout') === false) {
            add_option('ccx_leads_field_layout', json_encode($layout));
        } else {
            update_option('ccx_leads_field_layout', json_encode($layout));
        }

        return true;
    }

    public function do_kanban_query($status, $search = '', $page = 1, $sort = [], $count = false)
    {
        // Wrapper for core kanban query but allows for future optimization
        // For now, we reuse core logic or replicate if we need custom fields in list

        $limit = 10; // Load 10 at a time for infinite scroll
        $start = ($page - 1) * $limit;

        $this->db->select('*');
        $this->db->from(db_prefix() . 'leads');
        $this->db->where('status', $status);

        if (!is_admin()) {
            $this->db->group_start();
            $this->db->where('assigned', get_staff_user_id());
            $this->db->or_where('addedfrom', get_staff_user_id());
            $this->db->or_where('is_public', 1);
            $this->db->group_end();
        }

        if ($search != '') {
            $this->db->group_start();
            $this->db->like('name', $search);
            $this->db->or_like('company', $search);
            $this->db->or_like('phonenumber', $search);
            $this->db->group_end();
        }

        if (!$count) {
            $this->db->limit($limit, $start);
        }

        $this->db->order_by('leadorder', 'asc');
        $this->db->order_by('dateadded', 'desc');

        if ($count) {
            return $this->db->count_all_results();
        }

        return $this->db->get()->result_array();
    }

    public function get_status_summary()
    {
        $this->db->select('status, junk, lost, count(*) as total');
        $this->db->from(db_prefix() . 'leads');

        if (!is_admin()) {
            $this->db->group_start();
            $this->db->where('assigned', get_staff_user_id());
            $this->db->or_where('addedfrom', get_staff_user_id());
            $this->db->or_where('is_public', 1);
            $this->db->group_end();
        }

        $this->db->group_by('status, junk, lost');
        $results = $this->db->get()->result_array();

        // Process results to separate Junk and Lost
        $summary = [];
        $junk_count = 0;
        $lost_count = 0;

        foreach ($results as $row) {
            if ($row['junk'] == 1) {
                $junk_count += $row['total'];
            } elseif ($row['lost'] == 1) {
                $lost_count += $row['total'];
            } else {
                // Regular status
                $found = false;
                foreach ($summary as &$s) {
                    if ($s['id'] == $row['status']) {
                        $s['total'] += $row['total'];
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $summary[] = [
                        'id' => $row['status'],
                        'total' => $row['total']
                    ];
                }
            }
        }

        // Add Junk and Lost to summary with special IDs
        $summary[] = ['id' => 'junk', 'total' => $junk_count, 'name' => _l('leads_junk')];
        $summary[] = ['id' => 'lost', 'total' => $lost_count, 'name' => _l('leads_lost')];

        return $summary;
    }

    // ==================== ROLLER COASTER — AUTO ASSIGNMENT ====================

    /**
     * Auto-assign a newly created lead based on Roller Coaster settings.
     * Called from the API controller after lead creation.
     *
     * @param int   $lead_id  The new lead's ID
     * @param array $data     The lead data that was submitted
     * @return array ['assigned' => staffid|0, 'action' => string]
     */
    public function auto_assign_lead($lead_id, $data = [])
    {
        $result = ['assigned' => 0, 'action' => 'none'];

        // Load RC settings
        $rc_raw = get_option('ccx_leads_roller_coaster');
        if (empty($rc_raw))
            return $result;
        $rc = json_decode($rc_raw, true);
        if (empty($rc) || empty($rc['active']))
            return $result;

        // 1. Check "avoid empty leads"
        if (!empty($rc['avoid_empty'])) {
            if (empty($data['name']) || empty($data['phonenumber'])) {
                $result['action'] = 'skipped_empty';
                return $result;
            }
        }

        // 2. Check "auto junk" — short phone numbers
        if (!empty($rc['junk_enabled']) && !empty($data['phonenumber'])) {
            $digits_only = preg_replace('/\D/', '', $data['phonenumber']);
            $min_digits = isset($rc['junk_min_digits']) ? intval($rc['junk_min_digits']) : 10;
            if (strlen($digits_only) < $min_digits && !empty($rc['junk_status_id'])) {
                // Mark as junk — update status
                $this->db->where('id', $lead_id);
                $this->db->update(db_prefix() . 'leads', ['status' => intval($rc['junk_status_id'])]);
                $result['action'] = 'junk';
                return $result;
            }
        }

        // 3. Check source filter
        if (!empty($rc['sources']) && is_array($rc['sources'])) {
            $lead = $this->db->where('id', $lead_id)->get(db_prefix() . 'leads')->row();
            if ($lead && !in_array($lead->source, $rc['sources'])) {
                $result['action'] = 'source_filtered';
                return $result;
            }
        }

        // 4. Check working hours
        if (!empty($rc['working_hours_enabled'])) {
            $now_time = date('H:i');
            $wh_start = isset($rc['working_hours_start']) ? $rc['working_hours_start'] : '09:00';
            $wh_end = isset($rc['working_hours_end']) ? $rc['working_hours_end'] : '18:00';
            if ($now_time < $wh_start || $now_time > $wh_end) {
                // Outside working hours — apply fallback
                return $this->_rc_apply_fallback($lead_id, $rc, 'outside_hours');
            }
        }

        // 5. Build eligible agent list
        $agents = $this->_rc_get_eligible_agents($rc);

        if (empty($agents)) {
            return $this->_rc_apply_fallback($lead_id, $rc, 'no_agents');
        }

        // 6. Pick agent based on strategy
        $strategy = isset($rc['strategy']) ? $rc['strategy'] : 'round_robin_online';
        $staff_id = 0;

        switch ($strategy) {
            case 'weighted_round_robin':
                $weights = isset($rc['weights']) ? $rc['weights'] : [];
                $staff_id = $this->_rc_weighted_round_robin($agents, $weights);
                break;
            case 'least_leads':
                $staff_id = $this->_rc_least_leads_agent($agents, 'today');
                break;
            case 'least_leads_week':
                $staff_id = $this->_rc_least_leads_agent($agents, 'week');
                break;
            case 'least_leads_month':
                $staff_id = $this->_rc_least_leads_agent($agents, 'month');
                break;
            case 'random':
                $staff_id = $this->_rc_random_agent($agents);
                break;
            case 'skill_based':
                $skill_map = isset($rc['skill_map']) ? $rc['skill_map'] : [];
                $lead = $this->db->where('id', $lead_id)->get(db_prefix() . 'leads')->row();
                $staff_id = $this->_rc_skill_based_agent($agents, $lead, $skill_map);
                break;
            default:
                // round_robin_online / round_robin_all
                $staff_id = $this->_rc_round_robin_agent($agents);
                break;
        }

        if ($staff_id > 0) {
            $this->db->where('id', $lead_id);
            $this->db->update(db_prefix() . 'leads', ['assigned' => $staff_id]);
            $result['assigned'] = $staff_id;
            $result['action'] = 'assigned';
        } else {
            return $this->_rc_apply_fallback($lead_id, $rc, 'no_eligible');
        }

        return $result;
    }

    /**
     * Get eligible agents based on RC settings (roles, active, online, daily cap).
     */
    private function _rc_get_eligible_agents($rc)
    {
        $roles = isset($rc['roles']) ? $rc['roles'] : [];
        $strategy = isset($rc['strategy']) ? $rc['strategy'] : 'round_robin_online';

        // Start with active staff
        $this->db->select('staffid, firstname, lastname, last_activity, role');
        $this->db->where('active', 1);

        // Filter by roles
        if (!empty($roles)) {
            $this->db->where_in('role', $roles);
        }

        // Exclude admins from round-robin (they're the fallback)
        $this->db->where('admin', 0);

        $staff = $this->db->get(db_prefix() . 'staff')->result_array();

        if (empty($staff))
            return [];

        // Filter by online status if strategy requires it
        if ($strategy === 'round_robin_online') {
            $threshold = date('Y-m-d H:i:s', strtotime('-15 minutes'));
            $staff = array_filter($staff, function ($s) use ($threshold) {
                return !empty($s['last_activity']) && $s['last_activity'] >= $threshold;
            });
            $staff = array_values($staff);
        }

        // Filter by daily cap
        $daily_cap = isset($rc['daily_cap']) ? intval($rc['daily_cap']) : 0;
        if ($daily_cap > 0 && !empty($staff)) {
            $today = date('Y-m-d');
            $staff_ids = array_column($staff, 'staffid');

            // Count leads assigned today per agent
            $this->db->select('assigned, COUNT(*) as cnt');
            $this->db->where_in('assigned', $staff_ids);
            $this->db->where('DATE(dateadded)', $today);
            $this->db->group_by('assigned');
            $counts = $this->db->get(db_prefix() . 'leads')->result_array();

            $count_map = [];
            foreach ($counts as $c) {
                $count_map[$c['assigned']] = intval($c['cnt']);
            }

            $staff = array_filter($staff, function ($s) use ($count_map, $daily_cap) {
                $cnt = isset($count_map[$s['staffid']]) ? $count_map[$s['staffid']] : 0;
                return $cnt < $daily_cap;
            });
            $staff = array_values($staff);
        }

        return $staff;
    }

    /**
     * Round-robin: pick next agent from the list using a persisted index.
     */
    private function _rc_round_robin_agent($agents)
    {
        if (empty($agents))
            return 0;

        $idx = intval(get_option('ccx_leads_rr_index'));
        $count = count($agents);

        // Wrap around
        if ($idx >= $count)
            $idx = 0;

        $chosen = $agents[$idx];
        $next_idx = $idx + 1;
        update_option('ccx_leads_rr_index', $next_idx);

        return intval($chosen['staffid']);
    }

    /**
     * Least-leads: pick the agent with fewest leads in the given period.
     * @param string $period 'today', 'week', or 'month'
     */
    private function _rc_least_leads_agent($agents, $period = 'today')
    {
        if (empty($agents))
            return 0;

        $staff_ids = array_column($agents, 'staffid');

        $this->db->select('assigned, COUNT(*) as cnt');
        $this->db->where_in('assigned', $staff_ids);

        switch ($period) {
            case 'week':
                $this->db->where('dateadded >=', date('Y-m-d', strtotime('monday this week')));
                break;
            case 'month':
                $this->db->where('dateadded >=', date('Y-m-01'));
                break;
            default: // today
                $this->db->where('DATE(dateadded)', date('Y-m-d'));
                break;
        }

        $this->db->group_by('assigned');
        $counts = $this->db->get(db_prefix() . 'leads')->result_array();

        $count_map = [];
        foreach ($counts as $c) {
            $count_map[$c['assigned']] = intval($c['cnt']);
        }

        // Find the agent with the minimum count
        $min_count = PHP_INT_MAX;
        $chosen_id = 0;
        foreach ($agents as $a) {
            $cnt = isset($count_map[$a['staffid']]) ? $count_map[$a['staffid']] : 0;
            if ($cnt < $min_count) {
                $min_count = $cnt;
                $chosen_id = intval($a['staffid']);
            }
        }

        return $chosen_id;
    }

    /**
     * Weighted Round Robin: agents with higher weight get proportionally more leads.
     * Weight 3 means the agent appears 3× in the virtual queue.
     */
    private function _rc_weighted_round_robin($agents, $weights = [])
    {
        if (empty($agents))
            return 0;

        // Build expanded queue: agent appears N times where N = weight
        $queue = [];
        foreach ($agents as $a) {
            $w = isset($weights[$a['staffid']]) ? intval($weights[$a['staffid']]) : 1;
            if ($w <= 0)
                continue; // weight 0 = excluded
            for ($i = 0; $i < $w; $i++) {
                $queue[] = intval($a['staffid']);
            }
        }

        if (empty($queue))
            return 0;

        $idx = intval(get_option('ccx_leads_rr_index'));
        $count = count($queue);
        if ($idx >= $count)
            $idx = 0;

        $chosen = $queue[$idx];
        update_option('ccx_leads_rr_index', $idx + 1);

        return $chosen;
    }

    /**
     * Random: pick a random agent from the eligible list.
     */
    private function _rc_random_agent($agents)
    {
        if (empty($agents))
            return 0;
        $pick = $agents[array_rand($agents)];
        return intval($pick['staffid']);
    }

    /**
     * Skill-Based: route leads to agents based on source → role mapping.
     * If the lead's source is mapped to a role, only agents of that role are eligible.
     * Falls back to round-robin among the filtered agents.
     */
    private function _rc_skill_based_agent($agents, $lead, $skill_map = [])
    {
        if (empty($agents))
            return 0;

        // Check if lead source has a role mapping
        if (!empty($skill_map) && $lead && isset($skill_map[$lead->source])) {
            $target_role = $skill_map[$lead->source];
            // Filter agents to only those with the target role
            $filtered = array_filter($agents, function ($a) use ($target_role) {
                return $a['role'] == $target_role;
            });
            $filtered = array_values($filtered);

            if (!empty($filtered)) {
                // Round-robin among filtered agents
                return $this->_rc_round_robin_agent($filtered);
            }
        }

        // No mapping or no agents for mapped role — fallback to round-robin
        return $this->_rc_round_robin_agent($agents);
    }

    /**
     * Apply fallback when no eligible agents are available.
     */
    private function _rc_apply_fallback($lead_id, $rc, $reason)
    {
        $fallback = isset($rc['no_active_fallback']) ? $rc['no_active_fallback'] : 'skip';
        $result = ['assigned' => 0, 'action' => 'fallback_' . $reason];

        if ($fallback === 'admin') {
            // Find the first admin user
            $admin = $this->db
                ->select('staffid')
                ->where('admin', 1)
                ->where('active', 1)
                ->limit(1)
                ->get(db_prefix() . 'staff')
                ->row();

            if ($admin) {
                $this->db->where('id', $lead_id);
                $this->db->update(db_prefix() . 'leads', ['assigned' => $admin->staffid]);
                $result['assigned'] = $admin->staffid;
                $result['action'] = 'assigned_admin';
            }
        } elseif ($fallback === 'queue') {
            // Leave unassigned with a marker for queue processing
            // The next agent that comes online will pick it up
            $result['action'] = 'queued';
        }
        // 'skip' = leave unassigned, no action

        return $result;
    }
}
