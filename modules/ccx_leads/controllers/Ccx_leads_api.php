<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * CCX Leads API Controller — Public endpoint for external integrations.
 * Authenticates via API key passed in header or POST param.
 */
class Ccx_leads_api extends ClientsController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('ccx_leads/ccx_leads_model');
        $this->load->model('leads_model');

        // All API responses are JSON
        header('Content-Type: application/json; charset=utf-8');
    }

    /**
     * Validate the API key from header or POST param
     * @return bool
     */
    private function _validate_api_key()
    {
        $api_key = get_option('ccx_leads_api_key');

        if (empty($api_key)) {
            return false;
        }

        // Check header first: X-Api-Key
        $provided = $this->input->get_request_header('X-Api-Key', TRUE);

        // Fallback to POST param
        if (empty($provided)) {
            $provided = $this->input->post('api_key');
        }

        // Fallback to GET param
        if (empty($provided)) {
            $provided = $this->input->get('api_key');
        }

        return ($provided === $api_key);
    }

    /**
     * POST /ccx_leads_api/add_lead
     * Add a new lead via API
     *
     * Required: name, phonenumber
     * Optional: title, email, website, company, address, city, state,
     *           country, zip, description, status, source, assigned, lead_value, is_public
     */
    public function add_lead()
    {
        // Only allow POST
        if ($this->input->method() !== 'post') {
            echo json_encode([
                'success' => false,
                'error' => 'METHOD_NOT_ALLOWED',
                'message' => 'Only POST requests are allowed.',
            ]);
            die;
        }

        // Validate API key
        if (!$this->_validate_api_key()) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'error' => 'UNAUTHORIZED',
                'message' => 'Invalid or missing API key.',
            ]);
            die;
        }

        // Get input data — support both form POST and JSON body
        $data = $this->input->post();
        if (empty($data) || (count($data) == 1 && isset($data['api_key']))) {
            // Try JSON body
            $json = file_get_contents('php://input');
            $decoded = json_decode($json, true);
            if (is_array($decoded) && !empty($decoded)) {
                $data = $decoded;
            }
        }

        // Remove api_key from data before saving
        unset($data['api_key']);

        // Validate required fields: name and phonenumber
        $missing = [];
        if (empty($data['name']))
            $missing[] = 'name';
        if (empty($data['phonenumber']))
            $missing[] = 'phonenumber';

        if (!empty($missing)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'VALIDATION_ERROR',
                'message' => 'Required field(s) missing: ' . implode(', ', $missing),
            ]);
            die;
        }

        // Set defaults
        if (empty($data['status'])) {
            $data['status'] = get_option('leads_default_status');
        }
        if (empty($data['source'])) {
            $data['source'] = get_option('leads_default_source');
        }

        // Add the lead using core model
        $id = $this->leads_model->add($data);

        if ($id) {
            // Roller Coaster — auto-assign the lead
            $rc_result = $this->ccx_leads_model->auto_assign_lead($id, $data);

            http_response_code(201);
            echo json_encode([
                'success' => true,
                'lead_id' => $id,
                'message' => 'Lead created successfully.',
                'assigned_to' => $rc_result['assigned'],
                'rc_action' => $rc_result['action'],
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'CREATE_FAILED',
                'message' => 'Failed to create lead.',
            ]);
        }
        die;
    }

    /**
     * GET /ccx_leads_api/get_leads
     * Get all leads via API (basic list)
     */
    public function get_leads()
    {
        if (!$this->_validate_api_key()) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'error' => 'UNAUTHORIZED',
                'message' => 'Invalid or missing API key.',
            ]);
            die;
        }

        $status = $this->input->get('status');
        $limit = intval($this->input->get('limit') ?: 50);
        $offset = intval($this->input->get('offset') ?: 0);

        if ($limit > 200)
            $limit = 200;

        if ($status) {
            $this->db->where('status', $status);
        }

        $this->db->limit($limit, $offset);
        $this->db->order_by('dateadded', 'desc');
        $leads = $this->db->get(db_prefix() . 'leads')->result_array();

        echo json_encode([
            'success' => true,
            'count' => count($leads),
            'leads' => $leads,
        ]);
        die;
    }

    /**
     * GET /ccx_leads_api/get_lead/{id}
     * Get a single lead by ID
     */
    public function get_lead($id = 0)
    {
        if (!$this->_validate_api_key()) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'error' => 'UNAUTHORIZED',
                'message' => 'Invalid or missing API key.',
            ]);
            die;
        }

        $id = intval($id);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'VALIDATION_ERROR',
                'message' => 'Lead ID is required.',
            ]);
            die;
        }

        $lead = $this->leads_model->get($id);

        if (!$lead) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'NOT_FOUND',
                'message' => 'Lead not found.',
            ]);
            die;
        }

        echo json_encode([
            'success' => true,
            'lead' => $lead,
        ]);
        die;
    }
}
