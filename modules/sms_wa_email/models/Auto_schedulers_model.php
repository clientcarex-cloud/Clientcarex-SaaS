<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Auto_schedulers_model extends App_Model
{
    private $table;
    private $logs_table;

    public function __construct()
    {
        parent::__construct();
        $this->table      = db_prefix() . 'sms_wa_email_auto_schedulers';
        $this->logs_table = db_prefix() . 'sms_wa_email_auto_scheduler_logs';
    }

    /**
     * Get a single scheduler or all schedulers.
     */
    public function get($id = '', $where = [])
    {
        $this->db->where($where);
        if (is_numeric($id)) {
            $this->db->where('id', $id);
            return $this->db->get($this->table)->row();
        }
        $this->db->order_by('created_at', 'desc');
        return $this->db->get($this->table)->result_array();
    }

    /**
     * Add a new auto-scheduler.
     */
    public function add($data)
    {
        $data['created_by'] = get_staff_user_id();
        $data['created_at'] = date('Y-m-d H:i:s');

        // Encode time_slots as JSON if it's an array
        if (isset($data['time_slots']) && is_array($data['time_slots'])) {
            // Filter out empty slots
            $data['time_slots'] = json_encode(array_values(array_filter($data['time_slots'])));
        }

        // Encode filters
        $filters = [];
        if (isset($data['filters'])) {
            $filters = $data['filters'];
            unset($data['filters']);
        }
        $data['filters_json'] = json_encode($filters);

        // Handle is_active
        $data['is_active'] = isset($data['is_active']) ? (int)$data['is_active'] : 1;

        // Remove template_id_* fields that don't exist in the DB table
        foreach (array_keys($data) as $key) {
            if (strpos($key, 'template_id_') === 0) {
                unset($data[$key]);
            }
        }

        $this->db->insert($this->table, $data);
        $insert_id = $this->db->insert_id();

        if ($insert_id) {
            log_activity('New Auto Scheduler Created [ID:' . $insert_id . ', Name: ' . $data['name'] . ']');
            return $insert_id;
        }

        return false;
    }

    /**
     * Update an existing auto-scheduler.
     */
    public function update($id, $data)
    {
        if (isset($data['time_slots']) && is_array($data['time_slots'])) {
            $data['time_slots'] = json_encode(array_values(array_filter($data['time_slots'])));
        }

        if (isset($data['filters'])) {
            $data['filters_json'] = json_encode($data['filters']);
            unset($data['filters']);
        }

        $data['is_active'] = isset($data['is_active']) ? (int)$data['is_active'] : 1;

        // Remove template_id_* fields
        foreach (array_keys($data) as $key) {
            if (strpos($key, 'template_id_') === 0) {
                unset($data[$key]);
            }
        }

        $this->db->where('id', $id);
        $this->db->update($this->table, $data);

        if ($this->db->affected_rows() > 0) {
            log_activity('Auto Scheduler Updated [ID:' . $id . ']');
            return true;
        }

        return false;
    }

    /**
     * Delete a scheduler and its logs.
     */
    public function delete($id)
    {
        $this->db->where('id', $id);
        $this->db->delete($this->table);
        if ($this->db->affected_rows() > 0) {
            $this->db->where('scheduler_id', $id);
            $this->db->delete($this->logs_table);
            log_activity('Auto Scheduler Deleted [ID:' . $id . ']');
            return true;
        }
        return false;
    }

    /**
     * Get patient IDs already messaged today for a given scheduler.
     */
    public function get_todays_sent_patient_ids($scheduler_id)
    {
        $today = date('Y-m-d');
        $this->db->select('patient_id');
        $this->db->from($this->logs_table);
        $this->db->where('scheduler_id', $scheduler_id);
        $this->db->where('run_date', $today);
        $result = $this->db->get()->result_array();
        $ids = [];
        foreach ($result as $r) {
            $ids[] = $r['patient_id'];
        }
        return $ids;
    }

    /**
     * Get logs for a scheduler, optionally filtered by date.
     */
    public function get_logs($scheduler_id, $date = '')
    {
        $this->db->where('scheduler_id', $scheduler_id);
        if ($date != '') {
            $this->db->where('run_date', $date);
        }
        $this->db->order_by('id', 'desc');
        return $this->db->get($this->logs_table)->result_array();
    }

    /**
     * Get today's stats for a scheduler.
     */
    public function get_today_stats($scheduler_id)
    {
        $today = date('Y-m-d');
        $this->db->select('
            COUNT(*) as total,
            SUM(CASE WHEN status = "success" THEN 1 ELSE 0 END) as success_count,
            SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed_count
        ');
        $this->db->from($this->logs_table);
        $this->db->where('scheduler_id', $scheduler_id);
        $this->db->where('run_date', $today);
        $row = $this->db->get()->row();
        return [
            'total'   => (int)$row->total,
            'success' => (int)$row->success_count,
            'failed'  => (int)$row->failed_count,
        ];
    }

    /**
     * Get distinct run dates for a scheduler (for date picker).
     */
    public function get_run_dates($scheduler_id)
    {
        $this->db->select('DISTINCT(run_date) as run_date');
        $this->db->from($this->logs_table);
        $this->db->where('scheduler_id', $scheduler_id);
        $this->db->order_by('run_date', 'desc');
        $this->db->limit(30);
        return $this->db->get()->result_array();
    }
}
