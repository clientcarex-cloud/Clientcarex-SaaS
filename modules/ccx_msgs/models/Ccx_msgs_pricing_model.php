<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Ccx_msgs_pricing_model extends App_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    public function get($id = '')
    {
        if (is_numeric($id)) {
            $this->db->where('id', $id);
            return $this->db->get(db_prefix() . 'ccx_msgs_pricing')->row();
        }
        $this->db->order_by('message_type', 'asc');
        $this->db->order_by('price', 'asc');
        return $this->db->get(db_prefix() . 'ccx_msgs_pricing')->result_array();
    }

    public function add($data)
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        if (isset($data['active'])) {
            $data['active'] = 1;
        } else {
            $data['active'] = 0;
        }

        $this->db->insert(db_prefix() . 'ccx_msgs_pricing', $data);
        $insert_id = $this->db->insert_id();

        if ($insert_id) {
            log_activity('New CCX Msgs Pricing Plan Added [ID: ' . $insert_id . ']');
            return $insert_id;
        }

        return false;
    }

    public function update($data, $id)
    {
        if (isset($data['active'])) {
            $data['active'] = 1;
        } else {
            $data['active'] = 0;
        }

        $this->db->where('id', $id);
        $this->db->update(db_prefix() . 'ccx_msgs_pricing', $data);

        if ($this->db->affected_rows() > 0) {
            log_activity('CCX Msgs Pricing Plan Updated [ID: ' . $id . ']');
            return true;
        }

        return false;
    }

    public function delete($id)
    {
        $this->db->where('id', $id);
        $this->db->delete(db_prefix() . 'ccx_msgs_pricing');

        if ($this->db->affected_rows() > 0) {
            log_activity('CCX Msgs Pricing Plan Deleted [ID: ' . $id . ']');
            return true;
        }

        return false;
    }
}
