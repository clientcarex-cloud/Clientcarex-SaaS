<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Campaigns extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        sms_wa_email_require('campaigns');
        $this->load->model('campaigns_model');
    }

    /**
     * Block a campaign the staff member is not allowed to see at all.
     * Campaign rows carry the UI channel id ("official_whatsapp"), which
     * sms_wa_email_can_channel() folds onto the canonical one.
     */
    private function _guard_campaign_channel($channel)
    {
        if (!sms_wa_email_can_channel($channel)) {
            access_denied('sms_wa_email');
        }
    }

    public function index()
    {
        if ($this->input->is_ajax_request()) {
            $this->app->get_table_data(module_views_path('sms_wa_email', 'campaigns/table'));
        }
        $data['title'] = 'Campaigns';
        $this->load->view('campaigns/manage', $data);
    }

    public function create()
    {
        if ($this->input->post()) {
            $data = $this->input->post();

            // A campaign really sends — never let a channel through that the
            // staff member has no permission for, whatever the form posted.
            $this->_guard_campaign_channel(isset($data['channel']) ? $data['channel'] : '');

            $is_send_now = (isset($data['send_mode']) && $data['send_mode'] === 'now');
            $id = $this->campaigns_model->add($data);
            if ($id) {
                // If Send Now, process the campaign immediately — don't wait for cron
                if ($is_send_now) {
                    sms_wa_email_process_campaigns();
                }
                set_alert('success', $is_send_now ? 'Campaign sent successfully' : 'Campaign scheduled successfully');
                redirect(admin_url('sms_wa_email/campaigns'));
            } else {
                set_alert('danger', 'Failed to schedule campaign');
            }
        }

        $data['title'] = 'New Campaign';

        // Define all system channels with their labels, allocation col prefix, and template DB type
        $all_channels = [
            'sms'               => ['label' => 'SMS',             'col_prefix' => 'sms',      'tpl_type' => 'sms'],
            'official_whatsapp' => ['label' => 'WhatsApp',        'col_prefix' => 'whatsapp', 'tpl_type' => 'whatsapp'],
            'email'             => ['label' => 'Email',           'col_prefix' => 'email',    'tpl_type' => 'email'],
            'ai_call_agent'     => ['label' => 'AI Call Agent',   'col_prefix' => 'aicall',   'tpl_type' => 'ai_call_agent'],
        ];

        // Determine which channels are active based on allocations
        $this->load->helper('ccx_hooks_registry');
        $allocations = _ccx_get_allocation();

        $active_channels = [];
        foreach ($all_channels as $ch_key => $ch_info) {
            $prefix = $ch_info['col_prefix'];
            $is_active = true; // default active if no allocations
            if ($allocations) {
                $promo_col = $prefix . '_promo_active';
                $trans_col = $prefix . '_trans_active';
                $p = isset($allocations->$promo_col) ? (int) $allocations->$promo_col : 1;
                $t = isset($allocations->$trans_col) ? (int) $allocations->$trans_col : 1;
                if ($p === 0 && $t === 0) {
                    $is_active = false;
                }
            }
            // Channel-wise permission is applied on top of the allocation
            // state, so the picker only ever offers channels this staff
            // member may actually send on.
            if ($is_active && sms_wa_email_can_channel($ch_key)) {
                $active_channels[$ch_key] = $ch_info['label'];
            }
        }
        $data['active_channels'] = $active_channels;

        // Load templates grouped by channel — use the tpl_type mapping for DB query
        $channel_templates = [];
        foreach (array_keys($active_channels) as $ch_key) {
            $tpl_type = $all_channels[$ch_key]['tpl_type'];
            $channel_templates[$ch_key] = $this->db->where('type', $tpl_type)->where('active', 1)->get(db_prefix() . 'sms_wa_email_templates')->result_array();
        }
        $data['channel_templates'] = $channel_templates;

        // Load lookup data for advanced filters
        // Items grouped by item group
        $items_raw = $this->db->select('i.id, i.description, ig.name as group_name')
            ->from(db_prefix() . 'items i')
            ->join(db_prefix() . 'items_groups ig', 'ig.id = i.group_id', 'left')
            ->order_by('ig.name', 'asc')
            ->order_by('i.description', 'asc')
            ->get()->result_array();
        $data['items_list'] = $items_raw;

        // Item statuses
        $data['item_statuses'] = $this->db->order_by('name', 'asc')->get(db_prefix() . 'item_statuses')->result_array();

        $this->load->view('campaigns/form', $data);
    }

    public function view($id = '')
    {
        if (!$id || !is_numeric($id)) {
            redirect(admin_url('sms_wa_email/campaigns'));
        }

        $campaign = $this->campaigns_model->get($id);
        if (!$campaign) {
            set_alert('warning', 'Campaign not found');
            redirect(admin_url('sms_wa_email/campaigns'));
        }

        $this->_guard_campaign_channel($campaign->channel);

        $data['campaign'] = $campaign;
        $data['title'] = 'Campaign: ' . $campaign->name;

        // Get template info
        $data['template'] = $this->db->where('id', $campaign->template_id)->get(db_prefix() . 'sms_wa_email_templates')->row();

        // Get campaign logs
        $data['logs'] = $this->db->where('campaign_id', $id)->order_by('id', 'asc')->get(db_prefix() . 'sms_wa_email_campaign_logs')->result_array();

        // Get target patients from saved filters
        $filters = json_decode($campaign->filters_json, true);
        if (!is_array($filters)) $filters = [];
        $data['target_patients'] = $this->campaigns_model->get_filtered_patients_details($filters);
        $data['filters'] = $filters;

        // Load lookup data for resolving filter IDs to names
        $data['items_lookup'] = [];
        $items_raw = $this->db->select('id, description')->get(db_prefix() . 'items')->result_array();
        foreach ($items_raw as $item) {
            $data['items_lookup'][$item['id']] = $item['description'];
        }

        // Stats
        $data['stats'] = [
            'total'     => $campaign->total_targets,
            'processed' => $campaign->processed_count,
            'success'   => $campaign->success_count,
            'failed'    => $campaign->failed_count,
            'remaining' => max(0, $campaign->total_targets - $campaign->processed_count),
        ];

        $this->load->view('campaigns/view', $data);
    }

    /**
     * AJAX method to get template content for preview
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

    public function delete($id)
    {
        if (!$id) {
            redirect(admin_url('sms_wa_email/campaigns'));
        }

        $campaign = $this->campaigns_model->get($id);
        if ($campaign) {
            $this->_guard_campaign_channel($campaign->channel);
        }

        $response = $this->campaigns_model->delete($id);
        if ($response == true) {
            set_alert('success', 'Campaign deleted successfully');
        } else {
            set_alert('warning', 'Problem deleting campaign');
        }
        redirect(admin_url('sms_wa_email/campaigns'));
    }

    /**
     * Dedicated endpoint for real-time campaign processing.
     * Can be called by a server cron job running every minute:
     *   * * * * * curl -s https://yourdomain.com/admin/sms_wa_email/campaigns/process > /dev/null 2>&1
     * Bypasses Perfex's 5-minute cron cooldown entirely.
     */
    public function process()
    {
        // Lightweight lock: prevent overlapping execution (60-second window)
        $last_run = get_option('sms_wa_email_campaign_process_lock');
        $now = time();
        if ($last_run !== '' && ($now - (int)$last_run) < 15) {
            if ($this->input->is_ajax_request()) {
                echo json_encode(['status' => 'skipped', 'reason' => 'lock']);
            }
            return;
        }
        update_option('sms_wa_email_campaign_process_lock', $now);

        // Call the existing campaign processing function
        sms_wa_email_process_campaigns();

        if ($this->input->is_ajax_request()) {
            echo json_encode(['status' => 'done']);
        }
    }

    /**
     * AJAX method to get filtered patients details for the campaign form
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
     * AJAX method to get parameters for selected tests
     */
    public function get_test_parameters()
    {
        if ($this->input->post()) {
            $item_ids = $this->input->post('items');
            if (empty($item_ids) || !is_array($item_ids)) {
                echo json_encode([]);
                return;
            }

            // Secure the item IDs
            $item_ids = array_map('intval', $item_ids);

            $this->db->select('tp.parameter_name');
            $this->db->from(db_prefix() . 'tests_params tp');
            $this->db->join(db_prefix() . 'tests_fixed_templates tft', 'tft.id = tp.fixed_template_id');
            $this->db->where_in('tft.test_id', $item_ids);
            $this->db->where('tft.is_default', 1);
            $this->db->group_by('tp.parameter_name');
            $this->db->order_by('tp.parameter_name', 'ASC');
            
            $result = $this->db->get()->result_array();
            echo json_encode($result);
        }
    }
}
