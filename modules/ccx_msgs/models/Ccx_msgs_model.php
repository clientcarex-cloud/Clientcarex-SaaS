<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Ccx_msgs_model extends App_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    public function get_allocation($client_id)
    {
        $this->db->where('client_id', $client_id);
        return $this->db->get(db_prefix() . 'ccx_msgs_allocations')->row();
    }

    public function save_allocation($data)
    {
        $client_id = $data['client_id'];
        unset($data['client_id']);

        $date_fields = [
            'sms_promo_expiry',
            'sms_trans_expiry',
            'whatsapp_promo_expiry',
            'whatsapp_trans_expiry',
            'email_promo_expiry',
            'email_trans_expiry',
            'aicall_promo_expiry',
            'aicall_trans_expiry'
        ];

        foreach ($date_fields as $field) {
            if (isset($data[$field])) {
                if ($data[$field] != '') {
                    $data[$field] = to_sql_date($data[$field]);
                } else {
                    $data[$field] = null;
                }
            }
            // If not set at all, don't touch it — leave DB value as-is
        }

        $this->db->where('client_id', $client_id);
        $exists = $this->db->get(db_prefix() . 'ccx_msgs_allocations')->row();

        // Separate fields that are count additions from the main data array
        $additions = [];
        $deductions = [];
        $count_fields = [
            'sms_promo_count',
            'sms_trans_count',
            'whatsapp_promo_count',
            'whatsapp_trans_count',
            'email_promo_count',
            'email_trans_count',
            'aicall_promo_count',
            'aicall_trans_count'
        ];

        foreach ($count_fields as $field) {
            // Handle additions
            $add_field = $field . '_add';
            if (isset($data[$add_field])) {
                $additions[$field] = (int) $data[$add_field];
                unset($data[$add_field]);
            } else {
                $additions[$field] = 0;
            }

            // Handle deductions
            $deduct_field = $field . '_deduct';
            if (isset($data[$deduct_field])) {
                $deductions[$field] = (int) $data[$deduct_field];
                unset($data[$deduct_field]);
            } else {
                $deductions[$field] = 0;
            }

            // we remove the direct count fields if they exist to prevent direct overwrite
            if (isset($data[$field])) {
                unset($data[$field]);
            }
        }

        // Handle active fields (checkbox: present=1, absent=0)
        // Only process when explicitly requested (admin form saves pass this flag)
        // Payment callbacks do NOT pass this, so active flags are preserved
        $active_fields = [
            'sms_promo_active',
            'sms_trans_active',
            'whatsapp_promo_active',
            'whatsapp_trans_active',
            'email_promo_active',
            'email_trans_active',
            'aicall_promo_active',
            'aicall_trans_active',
        ];
        if (isset($data['_process_active_fields'])) {
            unset($data['_process_active_fields']);
            foreach ($active_fields as $af) {
                $data[$af] = isset($data[$af]) ? 1 : 0;
            }
        } else {
            // Remove any active fields that were not explicitly passed
            foreach ($active_fields as $af) {
                if (!isset($data[$af])) {
                    // Don't touch this field — leave DB value as-is
                } else {
                    $data[$af] = (int) $data[$af];
                }
            }
        }

        if ($exists) {
            // Apply additions
            foreach ($additions as $field => $val) {
                if ($val > 0) {
                    $this->db->set($field, $field . ' + ' . $val, false);
                }
            }
            // Apply deductions (never go below 0)
            foreach ($deductions as $field => $val) {
                if ($val > 0) {
                    $this->db->set($field, 'GREATEST(' . $field . ' - ' . $val . ', 0)', false);
                }
            }
            // Update other data (expiries, headers, active flags)
            if (!empty($data)) {
                $this->db->set($data);
            }

            $this->db->where('client_id', $client_id);
            $this->db->update(db_prefix() . 'ccx_msgs_allocations');
            return true;
        } else {
            // New record, set initial values
            foreach ($additions as $field => $val) {
                $deduct_val = isset($deductions[$field]) ? $deductions[$field] : 0;
                $data[$field] = max($val - $deduct_val, 0);
            }
            $data['client_id'] = $client_id;
            $data['created_at'] = date('Y-m-d H:i:s');
            $this->db->insert(db_prefix() . 'ccx_msgs_allocations', $data);
            return $this->db->insert_id() > 0;
        }
    }
}
