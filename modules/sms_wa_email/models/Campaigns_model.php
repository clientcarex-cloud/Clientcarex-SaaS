<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Campaigns_model extends App_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    public function get($id = '', $where = [])
    {
        $this->db->where($where);
        if (is_numeric($id)) {
            $this->db->where('id', $id);
            return $this->db->get(db_prefix() . 'sms_wa_email_campaigns')->row();
        }
        $this->db->order_by('created_at', 'desc');
        return $this->db->get(db_prefix() . 'sms_wa_email_campaigns')->result_array();
    }

    public function add($data)
    {
        $data['created_by'] = get_staff_user_id();
        $data['created_at'] = date('Y-m-d H:i:s');

        // Handle send mode: "now" means set schedule_date to current time
        $send_mode = isset($data['send_mode']) ? $data['send_mode'] : 'now';
        unset($data['send_mode']);
        
        // Format the schedule date based on system format or keep direct if already Y-m-d H:i:s
        if ($send_mode === 'schedule' && isset($data['schedule_date']) && $data['schedule_date'] !== '') {
            $data['schedule_date'] = to_sql_date($data['schedule_date'], true);
        } else {
            $data['schedule_date'] = date('Y-m-d H:i:s'); // Send now
        }

        // We receive filters as an array from the form. We need to evaluate total patients now.
        $filters = [];
        if (isset($data['filters'])) {
            $filters = $data['filters'];
            unset($data['filters']);
        }
        
        $data['filters_json'] = json_encode($filters);
        
        // Pre-compute the number of targets based on filters
        $data['total_targets'] = $this->get_filtered_patients_count($filters);
        $data['status'] = 'pending';

        // Remove all template_id_* form fields that don't exist in the DB table
        foreach (array_keys($data) as $key) {
            if (strpos($key, 'template_id_') === 0) {
                unset($data[$key]);
            }
        }

        $this->db->insert(db_prefix() . 'sms_wa_email_campaigns', $data);
        $insert_id = $this->db->insert_id();
        
        if ($insert_id) {
            log_activity('New SMS/WA/Email Campaign Scheduled [ID:' . $insert_id . ', Name: ' . $data['name'] . ']');
            return $insert_id;
        }
        
        return false;
    }

    public function delete($id)
    {
        $this->db->where('id', $id);
        $this->db->delete(db_prefix() . 'sms_wa_email_campaigns');
        if ($this->db->affected_rows() > 0) {
            // Also delete logs
            $this->db->where('campaign_id', $id);
            $this->db->delete(db_prefix() . 'sms_wa_email_campaign_logs');
            log_activity('SMS/WA/Email Campaign Deleted [ID:' . $id . ']');
            return true;
        }
        return false;
    }

    public function get_filtered_patients_count($filters = [])
    {
        return count($this->get_filtered_patients($filters));
    }

    public function get_filtered_patients($filters = [])
    {
        $this->db->select('userid as id');
        $this->db->from(db_prefix() . 'clients');
        
        $this->_apply_common_filters($filters);
        
        if (isset($filters['excluded_patients']) && is_array($filters['excluded_patients']) && count($filters['excluded_patients']) > 0) {
            $this->db->where_not_in('userid', $filters['excluded_patients']);
        }
        
        $result = $this->db->get()->result_array();
        $ids = [];
        foreach($result as $r) {
            $ids[] = $r['id'];
        }
        return $ids;
    }

    public function get_filtered_patients_details($filters = [])
    {
        $prefix = db_prefix();
        $this->db->select('userid as id, company as name, phonenumber as phone, datecreated, (SELECT mr_number FROM ' . $prefix . 'patients_extra WHERE patient_id=' . $prefix . 'clients.userid LIMIT 1) as mr_no, (SELECT email FROM ' . $prefix . 'contacts WHERE userid=' . $prefix . 'clients.userid AND is_primary=1 LIMIT 1) as email, (SELECT MAX(created_at) FROM ' . $prefix . 'visits WHERE patient_id=' . $prefix . 'clients.userid) as last_visit_date, (SELECT COUNT(*) FROM ' . $prefix . 'visits WHERE patient_id=' . $prefix . 'clients.userid) as total_visits', false);
        $this->db->from($prefix . 'clients');
        
        $this->_apply_common_filters($filters);
        
        $result = $this->db->get()->result_array();
        return $result;
    }

    /**
     * Centralized filter logic used by both get_filtered_patients and get_filtered_patients_details
     */
    private function _apply_common_filters($filters = [])
    {
        $prefix = db_prefix();

        // Patient Status
        if (isset($filters['status']) && $filters['status'] !== '') {
            $this->db->where('active', $filters['status']);
        } else {
            $this->db->where('active', 1);
        }

        // Registration Date Range
        if (isset($filters['registered_from']) && $filters['registered_from'] != '') {
            $this->db->where('datecreated >=', to_sql_date($filters['registered_from']));
        }
        if (isset($filters['registered_to']) && $filters['registered_to'] != '') {
            $this->db->where('datecreated <=', to_sql_date($filters['registered_to']) . ' 23:59:59');
        }

        // Gender
        if (isset($filters['gender']) && $filters['gender'] != '') {
            $this->db->where('EXISTS (SELECT 1 FROM ' . $prefix . 'patients_extra pe WHERE pe.patient_id = ' . $prefix . 'clients.userid AND pe.gender = ' . $this->db->escape($filters['gender']) . ')', null, false);
        }

        // Age Range
        if (isset($filters['age_from']) && $filters['age_from'] !== '') {
            $this->db->where('EXISTS (SELECT 1 FROM ' . $prefix . 'patients_extra pe2 WHERE pe2.patient_id = ' . $prefix . 'clients.userid AND pe2.age >= ' . (int)$filters['age_from'] . ')', null, false);
        }
        if (isset($filters['age_to']) && $filters['age_to'] !== '') {
            $this->db->where('EXISTS (SELECT 1 FROM ' . $prefix . 'patients_extra pe3 WHERE pe3.patient_id = ' . $prefix . 'clients.userid AND pe3.age <= ' . (int)$filters['age_to'] . ')', null, false);
        }

        // Visit Date Range
        if (isset($filters['visit_date_from']) && $filters['visit_date_from'] != '') {
            $vdf = to_sql_date($filters['visit_date_from']);
            $this->db->where('EXISTS (SELECT 1 FROM ' . $prefix . 'visits v WHERE v.patient_id = ' . $prefix . 'clients.userid AND v.created_at >= ' . $this->db->escape($vdf) . ')', null, false);
        }
        if (isset($filters['visit_date_to']) && $filters['visit_date_to'] != '') {
            $vdt = to_sql_date($filters['visit_date_to']) . ' 23:59:59';
            $this->db->where('EXISTS (SELECT 1 FROM ' . $prefix . 'visits v2 WHERE v2.patient_id = ' . $prefix . 'clients.userid AND v2.created_at <= ' . $this->db->escape($vdt) . ')', null, false);
        }

        // Visit Count (Min / Max)
        if (isset($filters['visits_min']) && $filters['visits_min'] !== '') {
            $min = (int)$filters['visits_min'];
            $this->db->where('(SELECT COUNT(*) FROM ' . $prefix . 'visits vc WHERE vc.patient_id = ' . $prefix . 'clients.userid) >= ' . $min, null, false);
        }
        if (isset($filters['visits_max']) && $filters['visits_max'] !== '') {
            $max = (int)$filters['visits_max'];
            $this->db->where('(SELECT COUNT(*) FROM ' . $prefix . 'visits vc2 WHERE vc2.patient_id = ' . $prefix . 'clients.userid) <= ' . $max, null, false);
        }

        // Items / Tests + Item Status — combined on the same patient_tests row
        $has_items   = isset($filters['items']) && is_array($filters['items']) && count($filters['items']) > 0;
        $has_statuses = isset($filters['item_statuses']) && is_array($filters['item_statuses']) && count($filters['item_statuses']) > 0;

        if ($has_items && $has_statuses) {
            // BOTH set → single EXISTS requiring same row to have matching item_id AND status
            $item_ids = array_map('intval', $filters['items']);
            $item_ids_str = implode(',', $item_ids);
            $escaped = [];
            foreach ($filters['item_statuses'] as $s) {
                $escaped[] = $this->db->escape($s);
            }
            $statuses_str = implode(',', $escaped);
            $this->db->where('EXISTS (SELECT 1 FROM ' . $prefix . 'patient_tests pt WHERE pt.patient_id = ' . $prefix . 'clients.userid AND pt.item_id IN (' . $item_ids_str . ') AND pt.status IN (' . $statuses_str . '))', null, false);
        } elseif ($has_items) {
            // Only items set
            $item_ids = array_map('intval', $filters['items']);
            $item_ids_str = implode(',', $item_ids);
            $this->db->where('EXISTS (SELECT 1 FROM ' . $prefix . 'patient_tests pt WHERE pt.patient_id = ' . $prefix . 'clients.userid AND pt.item_id IN (' . $item_ids_str . '))', null, false);
        } elseif ($has_statuses) {
            // Only item statuses set
            $escaped = [];
            foreach ($filters['item_statuses'] as $s) {
                $escaped[] = $this->db->escape($s);
            }
            $statuses_str = implode(',', $escaped);
            $this->db->where('EXISTS (SELECT 1 FROM ' . $prefix . 'patient_tests pt2 WHERE pt2.patient_id = ' . $prefix . 'clients.userid AND pt2.status IN (' . $statuses_str . '))', null, false);
        }

        // Payment Status (multi-select, invoice statuses: 1=Unpaid, 2=Paid, 3=Partially Paid, 4=Overdue, 5=Cancelled, 6=Draft)
        if (isset($filters['payment_statuses']) && is_array($filters['payment_statuses']) && count($filters['payment_statuses']) > 0) {
            $pst_ids = array_map('intval', $filters['payment_statuses']);
            $pst_str = implode(',', $pst_ids);
            $this->db->where('EXISTS (SELECT 1 FROM ' . $prefix . 'invoices inv WHERE inv.clientid = ' . $prefix . 'clients.userid AND inv.status IN (' . $pst_str . '))', null, false);
        }

        // Parameter Value Filter
        $enable_param_filter = isset($filters['enable_parameter_filter']) && $filters['enable_parameter_filter'] == '1';
        $param_name = isset($filters['parameter_name']) ? $filters['parameter_name'] : '';
        $param_operator = isset($filters['parameter_operator']) ? $filters['parameter_operator'] : '';
        $param_value = isset($filters['parameter_value']) ? $filters['parameter_value'] : '';

        if ($enable_param_filter && $param_name !== '' && $param_operator !== '' && $param_value !== '') {
            $safe_op = '=';
            $valid_ops = ['=', '<', '>', '<=', '>=', '!='];
            if (in_array($param_operator, $valid_ops)) {
                $safe_op = $param_operator;
            }

            // Numeric comparison safe-cast
            $cast_str = "tp_sub.result_value";
            $val_str = $this->db->escape($param_value);
            if (in_array($safe_op, ['<', '>', '<=', '>='])) {
                // MySQL cast for numeric evaluations where result_value could contain strings
                // Uses 0 for non-numeric gracefully in most cases, but simple CAST works for purely numeric inputs
                $cast_str = "CAST(tp_sub.result_value AS DECIMAL(10,4))";
                $val_str = (float) $param_value;
            }

            $param_query = "EXISTS (
                SELECT 1 FROM {$prefix}patient_tests pt_sub 
                JOIN {$prefix}transcriptor t_sub ON t_sub.patient_test_id = pt_sub.id 
                JOIN {$prefix}transcriptor_params tp_sub ON tp_sub.transcription_id = t_sub.id 
                WHERE pt_sub.patient_id = {$prefix}clients.userid 
                  AND tp_sub.parameter_name = " . $this->db->escape($param_name) . "
                  AND {$cast_str} {$safe_op} {$val_str}";

            if ($has_items) {
                // Ensure the parameter check targets the selected items
                $item_ids = array_map('intval', $filters['items']);
                $item_ids_str = implode(',', $item_ids);
                $param_query .= " AND pt_sub.item_id IN ({$item_ids_str})";
            }
            
            $param_query .= ")";
            $this->db->where($param_query, null, false);
        }

    }
}
