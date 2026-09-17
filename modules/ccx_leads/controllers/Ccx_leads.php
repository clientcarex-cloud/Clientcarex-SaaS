<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Ccx_leads extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('ccx_leads_model');
        $this->load->model('leads_model');
        $this->load->model('staff_model');
        $this->load->model('misc_model');
        $this->load->model('custom_fields_model');
    }

    /* List all leads */
    public function index($id = '')
    {
        close_setup_menu();

        if (!has_permission('leads', '', 'view')) {
            access_denied('leads');
        }

        // If ID is passed, we might want to open the slide-over directly (logic in JS)
        $data['leadid'] = $id;

        $data['statuses'] = $this->leads_model->get_status();
        $data['sources'] = $this->leads_model->get_source();
        $data['members'] = $this->staff_model->get('', ['active' => 1]);
        $data['summary'] = $this->ccx_leads_model->get_status_summary();
        $data['field_settings_map'] = $this->ccx_leads_model->get_field_settings_map();
        $data['title'] = 'CCX Leads';

        // Kanban logic
        $data['kanban_content'] = $this->kanban();

        $this->load->view('ccx_leads/main', $data);
    }

    public function kanban()
    {
        $data['statuses'] = $this->leads_model->get_status();

        return $this->load->view('ccx_leads/kanban', $data, true);
    }

    public function kanban_load_more()
    {
        $status = $this->input->get('status');
        $page = $this->input->get('page');

        $this->load->model('ccx_leads_model');
        $leads = $this->ccx_leads_model->do_kanban_query($status, '', $page);

        foreach ($leads as $lead) {
            $this->load->view('ccx_leads/_kan_ban_card', ['lead' => $lead, 'status' => $status]);
        }
    }

    /* Table view data */
    public function table()
    {
        $this->load->model('ccx_leads_model');
        $this->load->model('leads_model');
        $this->load->model('staff_model');
        $this->load->model('misc_model');

        if (!has_permission('leads', '', 'view')) {
            ajax_access_denied();
        }

        $this->app->get_table_data(module_views_path('ccx_leads', 'table'));
    }

    /* Get lead data for slide-over */
    public function get_lead_data($id)
    {
        $lead = $this->leads_model->get($id);

        if (!$lead) {
            header('HTTP/1.0 404 Not Found');
            echo 'Lead not found';
            die;
        }

        $data['lead'] = $lead;
        $data['check_permission'] = true; // For activity log
        $data['activity_log'] = $this->leads_model->get_lead_activity_log($id);
        $data['notes'] = $this->misc_model->get_notes($id, 'lead');
        $data['attachments'] = $this->leads_model->get_lead_attachments($id);

        $this->load->view('ccx_leads/lead_panel', $data);
    }

    /* Module's own lead modal — independent from core */
    public function lead_modal($id)
    {
        if (!has_permission('leads', '', 'view')) {
            ajax_access_denied();
        }

        $lead = $this->leads_model->get($id);
        if (!$lead) {
            show_404();
        }

        $this->load->model('currencies_model');

        $data['lead'] = $lead;
        $data['activity_log'] = $this->leads_model->get_lead_activity_log($id);
        $data['notes'] = $this->misc_model->get_notes($id, 'lead');
        $data['mail_activity'] = $this->leads_model->get_mail_activity($id);
        $data['statuses'] = $this->leads_model->get_status();
        $data['sources'] = $this->leads_model->get_source();
        $data['members'] = $this->staff_model->get('', ['active' => 1]);
        $data['total_notes'] = count($data['notes']);
        $data['total_reminders'] = total_rows(db_prefix() . 'reminders', ['rel_id' => $id, 'rel_type' => 'lead']);
        $data['total_attachments'] = count($lead->attachments);
        $data['openEdit'] = false;
        $data['lead_locked'] = false;
        $data['base_currency'] = $this->currencies_model->get_base_currency();
        $data['field_settings'] = $this->ccx_leads_model->get_field_settings_map();
        $data['field_layout'] = $this->ccx_leads_model->get_field_layout();

        $this->load->view('ccx_leads/lead_modal', $data);
    }

    /* AJAX: Serve independent New Lead form */
    public function new_lead()
    {
        if (!has_permission('leads', '', 'view')) {
            ajax_access_denied();
        }

        $this->load->model('currencies_model');

        $data['statuses'] = $this->leads_model->get_status();
        $data['sources'] = $this->leads_model->get_source();
        $data['members'] = $this->staff_model->get('', ['active' => 1]);
        $data['base_currency'] = $this->currencies_model->get_base_currency();
        $data['field_settings'] = $this->ccx_leads_model->get_field_settings_map();
        $data['field_layout'] = $this->ccx_leads_model->get_field_layout();

        $this->load->view('ccx_leads/new_lead_form', $data);
    }

    /* AJAX: Handle new lead form submission */
    public function save_lead()
    {
        if (!has_permission('leads', '', 'view')) {
            ajax_access_denied();
        }

        if (!$this->input->post()) {
            echo json_encode(['success' => false, 'message' => 'No data received']);
            die;
        }

        $post_data = $this->input->post();

        // Handle phone country code — prepend to phonenumber
        $phone_country_code = isset($post_data['phone_country_code']) ? trim($post_data['phone_country_code']) : '';
        if (!empty($phone_country_code) && !empty($post_data['phonenumber'])) {
            $post_data['phonenumber'] = $phone_country_code . ' ' . ltrim($post_data['phonenumber']);
        }
        unset($post_data['phone_country_code']);

        // Extract call log fields before passing to leads_model
        $add_call_log = isset($post_data['add_call_log']) ? $post_data['add_call_log'] : '';
        $call_log_description = isset($post_data['call_log_description']) ? trim($post_data['call_log_description']) : '';
        $call_log_contact_date = isset($post_data['call_log_contact_date']) ? $post_data['call_log_contact_date'] : '';
        $call_log_contacted = isset($post_data['call_log_contacted']) ? $post_data['call_log_contacted'] : 'no';

        // Remove call log fields so they don't interfere with lead creation
        unset($post_data['add_call_log']);
        unset($post_data['call_log_description']);
        unset($post_data['call_log_contact_date']);
        unset($post_data['call_log_contacted']);

        $id = $this->leads_model->add($post_data);

        // If lead was created and call log checkbox was checked with notes
        if ($id && $add_call_log == '1' && $call_log_description !== '') {
            $note_data = [
                'description' => $call_log_description,
            ];

            $contacted_date = null;
            if ($call_log_contacted === 'yes' && !empty($call_log_contact_date)) {
                $contacted_date = to_sql_date($call_log_contact_date, true);
                $note_data['date_contacted'] = $contacted_date;
            }

            $note_id = $this->misc_model->add_note($note_data, 'lead', $id);

            // Update lead's lastcontact if contacted
            if ($note_id && $contacted_date) {
                $this->db->where('id', $id);
                $this->db->update(db_prefix() . 'leads', [
                    'lastcontact' => $contacted_date,
                ]);
                if ($this->db->affected_rows() > 0) {
                    $this->leads_model->log_lead_activity($id, 'not_lead_activity_contacted', false, serialize([
                        get_staff_full_name(get_staff_user_id()),
                        _dt($contacted_date),
                    ]));
                }
            }
        }

        echo json_encode([
            'success' => $id ? true : false,
            'id' => $id,
            'message' => $id ? _l('added_successfully', _l('lead')) : '',
        ]);
        die;
    }

    /* Reports page */
    public function reports()
    {
        if (!has_permission('leads', '', 'view')) {
            access_denied('leads');
        }

        $data['title'] = 'CCX Leads - Reports';
        $data['members'] = $this->staff_model->get('', ['active' => 1]);

        $this->load->view('ccx_leads/reports', $data);
    }

    /* Individual report view */
    public function report_view($report_id = 1)
    {
        // TEMPORARY DEBUG — remove after fixing
        ini_set('display_errors', 1);
        error_reporting(E_ALL);

        if (!has_permission('leads', '', 'view')) {
            access_denied('leads');
        }

        $this->load->model('ccx_leads_reports_model');
        $report_id = intval($report_id);

        // Report metadata: name, model method, view partial
        $reports_meta = [
            1 => ['name' => 'Overall Leads Assigned', 'method' => 'report_overall_leads_assigned', 'view' => 'report_1_leads_assigned'],
            2 => ['name' => 'Overall Staff New Leads & Calls', 'method' => 'report_staff_new_leads_calls', 'view' => 'report_2_staff_leads_calls'],
            3 => ['name' => 'Overall Missed Leads Calls', 'method' => 'report_missed_leads_calls', 'view' => 'report_3_missed_calls'],
            4 => ['name' => 'Staff Wise Leads Call TAT Report', 'method' => 'report_call_tat', 'view' => 'report_4_call_tat'],
            5 => ['name' => 'Leads Follow-ups', 'method' => 'report_leads_followups', 'view' => 'report_5_followups'],
            6 => ['name' => 'Overview of Leads by Staff', 'method' => 'report_leads_by_staff', 'view' => 'report_6_leads_by_staff'],
            7 => ['name' => 'Complete Overview of Whole Leads', 'method' => 'report_complete_overview', 'view' => 'report_7_complete_overview'],
            8 => ['name' => 'Last One Week Leads & Conversion', 'method' => 'report_last_week_leads', 'view' => 'report_8_9_conversion'],
            9 => ['name' => 'Last 2 Week Leads & Conversion', 'method' => 'report_last_2week_leads', 'view' => 'report_8_9_conversion'],
            10 => ['name' => 'Overall Leads Statuses', 'method' => 'report_leads_statuses', 'view' => 'report_10_statuses'],
        ];

        if (!isset($reports_meta[$report_id])) {
            show_404();
        }

        $meta = $reports_meta[$report_id];

        // Collect filters from GET
        $date_from = $this->input->get('date_from') ?: '';
        $date_to = $this->input->get('date_to') ?: '';
        $staff_id = $this->input->get('staff_id') ?: '';

        // Call the dedicated reports model
        $method = $meta['method'];
        try {
            $data['report_data'] = $this->ccx_leads_reports_model->$method($date_from, $date_to, $staff_id);
        } catch (\Throwable $e) {
            log_message('error', 'CCX Report Error [ID: ' . $report_id . ']: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            // Reset query builder to flush any leftover SELECT/FROM/JOIN state
            $this->db->reset_query();
            $data['report_data'] = [];
        }
        // Guarantee report_data is always an array
        if (!is_array($data['report_data'])) {
            $data['report_data'] = [];
        }

        $data['report_id'] = $report_id;
        $data['report_name'] = $meta['name'];
        $data['date_from'] = $date_from;
        $data['date_to'] = $date_to;
        $data['staff_id'] = $staff_id;
        $data['members'] = $this->staff_model->get('', ['active' => 1]);
        $data['title'] = 'CCX Leads - ' . $meta['name'];

        // Render the individual report table into a string
        $data['report_content'] = $this->load->view('ccx_leads/reports/' . $meta['view'], $data, true);

        // Render the shared layout with the report content inside
        $this->load->view('ccx_leads/reports/_layout', $data);
    }

    /* Settings page */
    public function settings()
    {
        if (!has_permission('leads', '', 'view')) {
            access_denied('leads');
        }

        $data['title'] = _l('ccx_leads_settings');
        $this->load->model('leads_model');
        $data['statuses'] = $this->leads_model->get_status();
        $data['sources'] = $this->leads_model->get_source();
        $data['field_settings'] = $this->ccx_leads_model->get_field_settings();

        // Custom fields for leads
        $this->db->where('fieldto', 'leads');
        $this->db->order_by('field_order', 'asc');
        $data['custom_fields'] = $this->db->get(db_prefix() . 'customfields')->result_array();

        // Merged fields list for ordering tab (3 columns)
        $data['field_layout'] = $this->ccx_leads_model->get_field_layout();

        // API tab data
        $data['api_key'] = get_option('ccx_leads_api_key');
        $data['api_base_url'] = site_url('ccx_leads_api/');

        // Roller Coaster tab data
        $this->load->model('roles_model');
        $data['roles'] = $this->roles_model->get();
        $rc_raw = get_option('ccx_leads_roller_coaster');
        $data['rc'] = $rc_raw ? json_decode($rc_raw, true) : [];

        $this->load->view('ccx_leads/settings', $data);
    }

    /* AJAX: Save field settings */
    public function save_field_settings()
    {
        if (!has_permission('leads', '', 'view')) {
            ajax_access_denied();
        }

        $fields = $this->input->post('fields');
        if (!$fields || !is_array($fields)) {
            echo json_encode(['success' => false, 'message' => 'No data received']);
            die;
        }

        foreach ($fields as $field) {
            $id = intval($field['id']);
            if ($id <= 0)
                continue;

            $update = [
                'label' => trim($field['label']),
                'active' => isset($field['active']) ? 1 : 0,
                'required' => isset($field['required']) ? 1 : 0,
            ];

            $this->ccx_leads_model->update_field_setting($id, $update);
        }

        echo json_encode(['success' => true, 'message' => 'Field settings saved successfully']);
        die;
    }

    /* AJAX: Save field layout (3-column ordering) */
    public function save_field_order()
    {
        if (!is_admin()) {
            ajax_access_denied();
        }

        $columns = $this->input->post('columns');
        if (!$columns || !is_array($columns)) {
            echo json_encode(['success' => false, 'message' => 'No data received']);
            die;
        }

        $this->ccx_leads_model->save_field_layout($columns);
        echo json_encode(['success' => true, 'message' => 'Field order saved successfully']);
        die;
    }

    /* AJAX: Save (add/edit) a custom field for leads */
    public function save_custom_field()
    {
        if (!is_admin()) {
            ajax_access_denied();
        }

        $data = $this->input->post();
        if (!$data) {
            echo json_encode(['success' => false, 'message' => 'No data received']);
            die;
        }

        // Force fieldto = leads
        $data['fieldto'] = 'leads';

        $id = isset($data['id']) && $data['id'] != '' ? $data['id'] : '';
        unset($data['id']);

        if ($id == '') {
            $insert_id = $this->custom_fields_model->add($data);
            if ($insert_id) {
                echo json_encode(['success' => true, 'message' => _l('added_successfully', _l('custom_field')), 'id' => $insert_id]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to add custom field']);
            }
        } else {
            // Verify this custom field belongs to leads
            $existing = $this->custom_fields_model->get($id);
            if (!$existing || $existing->fieldto != 'leads') {
                echo json_encode(['success' => false, 'message' => 'Invalid custom field']);
                die;
            }
            $result = $this->custom_fields_model->update($data, $id);
            echo json_encode(['success' => true, 'message' => _l('updated_successfully', _l('custom_field')), 'id' => $id]);
        }
        die;
    }

    /* AJAX: Delete a custom field (leads only) */
    public function delete_custom_field($id)
    {
        if (!is_admin()) {
            ajax_access_denied();
        }

        // Verify this custom field belongs to leads
        $existing = $this->custom_fields_model->get($id);
        if (!$existing || $existing->fieldto != 'leads') {
            echo json_encode(['success' => false, 'message' => 'Invalid custom field']);
            die;
        }

        $result = $this->custom_fields_model->delete($id);
        echo json_encode(['success' => $result ? true : false, 'message' => $result ? _l('deleted', _l('custom_field')) : 'Failed to delete']);
        die;
    }

    /* AJAX: Toggle custom field active/inactive */
    public function toggle_custom_field($id, $status)
    {
        if (!is_admin()) {
            ajax_access_denied();
        }

        // Verify this custom field belongs to leads
        $existing = $this->custom_fields_model->get($id);
        if (!$existing || $existing->fieldto != 'leads') {
            echo json_encode(['success' => false, 'message' => 'Invalid custom field']);
            die;
        }

        $this->custom_fields_model->change_custom_field_status($id, $status);
        echo json_encode(['success' => true]);
        die;
    }

    /* AJAX: Generate or regenerate API key */
    public function generate_api_key()
    {
        if (!is_admin()) {
            ajax_access_denied();
        }

        $key = bin2hex(random_bytes(32)); // 64-char hex key
        update_option('ccx_leads_api_key', $key);

        echo json_encode(['success' => true, 'api_key' => $key]);
        die;
    }

    /* AJAX: Save Roller Coaster settings */
    public function save_roller_coaster()
    {
        if (!is_admin()) {
            ajax_access_denied();
        }

        $settings = [
            'active' => $this->input->post('active') ? 1 : 0,
            'roles' => $this->input->post('roles') ?: [],
            'strategy' => $this->input->post('strategy') ?: 'round_robin_online',
            'no_active_fallback' => $this->input->post('no_active_fallback') ?: 'queue',
            'sources' => $this->input->post('sources') ?: [],
            'daily_cap' => intval($this->input->post('daily_cap')),
            'working_hours_enabled' => $this->input->post('working_hours_enabled') ? 1 : 0,
            'working_hours_start' => $this->input->post('working_hours_start') ?: '09:00',
            'working_hours_end' => $this->input->post('working_hours_end') ?: '18:00',
            'avoid_empty' => $this->input->post('avoid_empty') ? 1 : 0,
            'junk_enabled' => $this->input->post('junk_enabled') ? 1 : 0,
            'junk_min_digits' => intval($this->input->post('junk_min_digits') ?: 10),
            'junk_status_id' => intval($this->input->post('junk_status_id')),
            'weights' => $this->input->post('weights') ?: [],
            'skill_map' => $this->input->post('skill_map') ?: [],
        ];

        update_option('ccx_leads_roller_coaster', json_encode($settings));

        echo json_encode(['success' => true, 'message' => 'Roller Coaster settings saved']);
        die;
    }
}
