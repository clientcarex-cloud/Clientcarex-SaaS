<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Auto_schedulers extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        sms_wa_email_require('auto_schedulers');
        $this->load->model('auto_schedulers_model');
        $this->load->model('campaigns_model');
    }

    /**
     * Block a scheduler whose channel this staff member has no access to.
     */
    private function _guard_channel($channel)
    {
        if (!sms_wa_email_can_channel($channel)) {
            access_denied('sms_wa_email');
        }
    }

    /**
     * List all auto-schedulers (+ DataTable AJAX).
     */
    public function index()
    {
        if ($this->input->is_ajax_request()) {
            $this->app->get_table_data(module_views_path('sms_wa_email', 'auto_schedulers/table'));
        }
        $data['title'] = 'Auto Schedulers';
        $this->load->view('auto_schedulers/manage', $data);
    }

    /**
     * Create a new auto-scheduler.
     */
    public function create()
    {
        if ($this->input->post()) {
            $data = $this->input->post();

            // A scheduler really sends, on a repeating cycle — validate the
            // channel server-side rather than trusting the submitted form.
            $this->_guard_channel(isset($data['channel']) ? $data['channel'] : '');

            $id = $this->auto_schedulers_model->add($data);
            if ($id) {
                set_alert('success', 'Auto Scheduler created successfully');
                redirect(admin_url('sms_wa_email/auto_schedulers'));
            } else {
                set_alert('danger', 'Failed to create Auto Scheduler');
            }
        }

        $this->_load_form_data($data);
        $data['title'] = 'New Auto Scheduler';
        $data['scheduler'] = null;
        $this->load->view('auto_schedulers/form', $data);
    }

    /**
     * Edit an existing auto-scheduler.
     */
    public function edit($id = '')
    {
        if (!$id || !is_numeric($id)) {
            redirect(admin_url('sms_wa_email/auto_schedulers'));
        }

        $scheduler = $this->auto_schedulers_model->get($id);
        if (!$scheduler) {
            set_alert('warning', 'Auto Scheduler not found');
            redirect(admin_url('sms_wa_email/auto_schedulers'));
        }

        // Both the scheduler's current channel and any channel it is being
        // moved to must be permitted.
        $this->_guard_channel($scheduler->channel);

        if ($this->input->post()) {
            $data = $this->input->post();
            $this->_guard_channel(isset($data['channel']) ? $data['channel'] : $scheduler->channel);
            $this->auto_schedulers_model->update($id, $data);
            set_alert('success', 'Auto Scheduler updated successfully');
            redirect(admin_url('sms_wa_email/auto_schedulers/view/' . $id));
        }

        $this->_load_form_data($data);
        $data['title'] = 'Edit Auto Scheduler';
        $data['scheduler'] = $scheduler;
        $this->load->view('auto_schedulers/form', $data);
    }

    /**
     * View scheduler detail page with today's logs.
     */
    public function view($id = '')
    {
        if (!$id || !is_numeric($id)) {
            redirect(admin_url('sms_wa_email/auto_schedulers'));
        }

        $scheduler = $this->auto_schedulers_model->get($id);
        if (!$scheduler) {
            set_alert('warning', 'Auto Scheduler not found');
            redirect(admin_url('sms_wa_email/auto_schedulers'));
        }

        $this->_guard_channel($scheduler->channel);

        $data['scheduler'] = $scheduler;
        $data['title'] = 'Auto Scheduler: ' . $scheduler->name;

        // Template info
        $data['template'] = $this->db->where('id', $scheduler->template_id)->get(db_prefix() . 'sms_wa_email_templates')->row();

        // Time slots
        $data['time_slots'] = json_decode($scheduler->time_slots, true);
        if (!is_array($data['time_slots'])) $data['time_slots'] = [];

        // Filters
        $filters = json_decode($scheduler->filters_json, true);
        if (!is_array($filters)) $filters = [];
        $data['filters'] = $filters;

        // Date for logs (default today)
        $view_date = $this->input->get('date') ? $this->input->get('date') : date('Y-m-d');
        $data['view_date'] = $view_date;

        // Logs for selected date
        $data['logs'] = $this->auto_schedulers_model->get_logs($id, $view_date);

        // Today's stats
        $data['stats'] = $this->auto_schedulers_model->get_today_stats($id);

        // Target patients count (current filter set)
        $target_patients = $this->campaigns_model->get_filtered_patients($filters);
        $data['total_targets'] = count($target_patients);

        // Already sent today
        $sent_today_ids = $this->auto_schedulers_model->get_todays_sent_patient_ids($id);
        $data['sent_today'] = count($sent_today_ids);
        $data['remaining'] = max(0, $data['total_targets'] - count($sent_today_ids));

        // ── Upcoming Send: next slot + eligible patients with details ──
        $now_mins = (int)date('G') * 60 + (int)date('i');
        $next_slot = null;
        $next_slot_diff = PHP_INT_MAX;
        foreach ($data['time_slots'] as $slot) {
            $parts = explode(':', $slot);
            if (count($parts) < 2) continue;
            $slot_mins = (int)$parts[0] * 60 + (int)$parts[1];
            $diff = $slot_mins - $now_mins;
            if ($diff > 0 && $diff < $next_slot_diff) {
                $next_slot = $slot;
                $next_slot_diff = $diff;
            }
        }
        $data['next_slot'] = $next_slot;
        $data['next_slot_diff_mins'] = $next_slot ? $next_slot_diff : 0;

        // Upcoming patients = filtered patients minus already sent today
        $upcoming_patients = [];
        if (!empty($target_patients)) {
            $upcoming_ids = array_diff($target_patients, $sent_today_ids);
            if (!empty($upcoming_ids)) {
                $this->db->select('userid as id, company as name, phonenumber as phone');
                $this->db->from(db_prefix() . 'clients');
                $this->db->where_in('userid', array_values($upcoming_ids));
                $this->db->order_by('company', 'asc');
                $upcoming_patients = $this->db->get()->result_array();
            }
        }
        $data['upcoming_patients'] = $upcoming_patients;

        // Run dates for date picker
        $data['run_dates'] = $this->auto_schedulers_model->get_run_dates($id);

        // Items lookup for filter display
        $data['items_lookup'] = [];
        $items_raw = $this->db->select('id, description')->get(db_prefix() . 'items')->result_array();
        foreach ($items_raw as $item) {
            $data['items_lookup'][$item['id']] = $item['description'];
        }

        $this->load->view('auto_schedulers/view', $data);
    }

    /**
     * Delete a scheduler.
     */
    public function delete($id)
    {
        if (!$id) {
            redirect(admin_url('sms_wa_email/auto_schedulers'));
        }

        $scheduler = $this->auto_schedulers_model->get($id);
        if ($scheduler) {
            $this->_guard_channel($scheduler->channel);
        }

        $response = $this->auto_schedulers_model->delete($id);
        if ($response == true) {
            set_alert('success', 'Auto Scheduler deleted successfully');
        } else {
            set_alert('warning', 'Problem deleting Auto Scheduler');
        }
        redirect(admin_url('sms_wa_email/auto_schedulers'));
    }

    /**
     * AJAX: Toggle active status.
     */
    public function toggle_active($id)
    {
        $scheduler = $this->auto_schedulers_model->get($id);
        if (!$scheduler || !sms_wa_email_can_channel($scheduler->channel)) {
            ajax_access_denied();
        }

        if ($this->input->post()) {
            $status = $this->input->post('is_active') == '1' ? 1 : 0;
            $this->db->where('id', $id)->update(db_prefix() . 'sms_wa_email_auto_schedulers', ['is_active' => $status]);
            echo json_encode(['success' => true]);
        }
    }

    /**
     * Manual trigger endpoint for processing auto-schedulers.
     * Can be called by curl cron: * * * * * curl -s https://domain/admin/sms_wa_email/auto_schedulers/process
     */
    public function process()
    {
        $last_run = get_option('sms_wa_email_auto_sched_process_lock');
        $now = time();
        if ($last_run !== '' && ($now - (int)$last_run) < 15) {
            if ($this->input->is_ajax_request()) {
                echo json_encode(['status' => 'skipped', 'reason' => 'lock']);
            }
            return;
        }
        update_option('sms_wa_email_auto_sched_process_lock', $now);

        sms_wa_email_process_auto_schedulers();

        if ($this->input->is_ajax_request()) {
            echo json_encode(['status' => 'done']);
        }
    }

    /**
     * AJAX: Get filtered patients (reuses campaigns model).
     */
    public function fetch_filtered_patients()
    {
        if ($this->input->post()) {
            $filters = $this->input->post('filters');
            if (!is_array($filters)) {
                $filters = [];
            }
            $patients = $this->campaigns_model->get_filtered_patients_details($filters);
            echo json_encode(['success' => true, 'count' => count($patients), 'patients' => $patients]);
        }
    }

    /**
     * AJAX: Get template content for preview.
     */
    public function get_template_content()
    {
        if ($this->input->post()) {
            $template_id = $this->input->post('template_id');
            if (!$template_id) {
                echo json_encode(['success' => false]);
                return;
            }
            $template = $this->db->where('id', $template_id)->get(db_prefix() . 'sms_wa_email_templates')->row();
            if ($template && sms_wa_email_can_channel($template->type)) {
                echo json_encode([
                    'success' => true,
                    'content' => $template->content,
                    'title'   => $template->title,
                    'type'    => $template->type
                ]);
            } else {
                echo json_encode(['success' => false]);
            }
        }
    }

    /**
     * Load form data shared between create and edit views.
     */
    private function _load_form_data(&$data)
    {
        // Active channels (same logic as campaigns)
        $all_channels = [
            'sms'               => ['label' => 'SMS',             'col_prefix' => 'sms',      'tpl_type' => 'sms'],
            'official_whatsapp' => ['label' => 'WhatsApp',        'col_prefix' => 'whatsapp', 'tpl_type' => 'whatsapp'],
            'email'             => ['label' => 'Email',           'col_prefix' => 'email',    'tpl_type' => 'email'],
            'ai_call_agent'     => ['label' => 'AI Call Agent',   'col_prefix' => 'aicall',   'tpl_type' => 'ai_call_agent'],
        ];

        $this->load->helper('ccx_hooks_registry');
        $allocations = _ccx_get_allocation();

        $active_channels = [];
        foreach ($all_channels as $ch_key => $ch_info) {
            $prefix = $ch_info['col_prefix'];
            $is_active = true;
            if ($allocations) {
                $promo_col = $prefix . '_promo_active';
                $trans_col = $prefix . '_trans_active';
                $p = isset($allocations->$promo_col) ? (int)$allocations->$promo_col : 1;
                $t = isset($allocations->$trans_col) ? (int)$allocations->$trans_col : 1;
                if ($p === 0 && $t === 0) {
                    $is_active = false;
                }
            }
            // Channel-wise permission narrows the allocation-derived list
            if ($is_active && sms_wa_email_can_channel($ch_key)) {
                $active_channels[$ch_key] = $ch_info['label'];
            }
        }
        $data['active_channels'] = $active_channels;

        // Templates per channel
        $channel_templates = [];
        foreach (array_keys($active_channels) as $ch_key) {
            $tpl_type = $all_channels[$ch_key]['tpl_type'];
            $channel_templates[$ch_key] = $this->db->where('type', $tpl_type)->where('active', 1)->get(db_prefix() . 'sms_wa_email_templates')->result_array();
        }
        $data['channel_templates'] = $channel_templates;

        // Items list
        $items_raw = $this->db->select('i.id, i.description, ig.name as group_name')
            ->from(db_prefix() . 'items i')
            ->join(db_prefix() . 'items_groups ig', 'ig.id = i.group_id', 'left')
            ->order_by('ig.name', 'asc')
            ->order_by('i.description', 'asc')
            ->get()->result_array();
        $data['items_list'] = $items_raw;

        // Item statuses
        $data['item_statuses'] = $this->db->order_by('name', 'asc')->get(db_prefix() . 'item_statuses')->result_array();
    }
}
