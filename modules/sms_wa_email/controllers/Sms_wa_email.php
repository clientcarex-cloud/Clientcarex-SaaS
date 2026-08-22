<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Sms_wa_email extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        if (!sms_wa_email_can_access()) {
            access_denied('sms_wa_email');
        }
        $needs_install = !$this->db->table_exists(db_prefix() . 'sms_wa_email_templates')
            || !$this->db->table_exists(db_prefix() . 'sms_wa_email_company_limits')
            || !$this->db->table_exists(db_prefix() . 'ccx_hook_template_map')
            || !$this->db->table_exists(db_prefix() . 'ccx_hook_trigger_logs')
            || !$this->db->table_exists(db_prefix() . 'sms_wa_email_auto_schedulers')
            || !$this->db->table_exists(db_prefix() . 'sms_wa_email_auto_scheduler_logs')
            // Official WhatsApp Cloud API mapping columns (v1.1)
            || !$this->db->field_exists('wa_template_name', db_prefix() . 'sms_wa_email_templates')
            // Auto-map bookkeeping columns (v1.2)
            || !$this->db->field_exists('wa_auto_synced', db_prefix() . 'sms_wa_email_templates');

        if ($needs_install) {
            $CI = &get_instance();
            require_once(module_dir_path('sms_wa_email', 'install.php'));
        }
    }

    public function index()
    {
        // Balance the sends on this installation actually draw on: a tenant's
        // own row in the master allocations table, or — on the master / a
        // non-SaaS install — its reserved self row (CCX Msgs → This Account).
        $data['allocations'] = function_exists('_ccx_get_allocation') ? _ccx_get_allocation() : null;

        // Compute per-channel sent stats from hook trigger logs
        $channel_stats = [
            'sms'            => ['sent_total' => 0, 'sent_today' => 0, 'sent_success' => 0, 'sent_failed' => 0],
            'whatsapp'       => ['sent_total' => 0, 'sent_today' => 0, 'sent_success' => 0, 'sent_failed' => 0],
            'email'          => ['sent_total' => 0, 'sent_today' => 0, 'sent_success' => 0, 'sent_failed' => 0],
            'ai_call_agent'  => ['sent_total' => 0, 'sent_today' => 0, 'sent_success' => 0, 'sent_failed' => 0],
        ];

        $logs_table = db_prefix() . 'ccx_hook_trigger_logs';
        if ($this->db->table_exists($logs_table)) {
            // Total sent per channel
            $totals = $this->db->select("channel, COUNT(*) as total, SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success_count, SUM(CASE WHEN status != 'success' THEN 1 ELSE 0 END) as failed_count")
                ->group_by('channel')
                ->get($logs_table)->result();
            foreach ($totals as $row) {
                if (isset($channel_stats[$row->channel])) {
                    $channel_stats[$row->channel]['sent_total'] = (int) $row->total;
                    $channel_stats[$row->channel]['sent_success'] = (int) $row->success_count;
                    $channel_stats[$row->channel]['sent_failed'] = (int) $row->failed_count;
                }
            }

            // Sent today per channel
            $today = $this->db->select("channel, COUNT(*) as today_count")
                ->where('DATE(created_at)', date('Y-m-d'))
                ->group_by('channel')
                ->get($logs_table)->result();
            foreach ($today as $row) {
                if (isset($channel_stats[$row->channel])) {
                    $channel_stats[$row->channel]['sent_today'] = (int) $row->today_count;
                }
            }
        }
        $data['channel_stats'] = $channel_stats;

        // Sender address shown (read-only) in the Email template modal —
        // resolved from the default Email API's SMTP from-address per subtype
        $data['email_sender'] = ['transactional' => '', 'promotional' => ''];
        if (function_exists('_ccx_get_default_api')) {
            foreach (['transactional', 'promotional'] as $subtype) {
                $api = _ccx_get_default_api('email', $subtype);
                if ($api) {
                    $data['email_sender'][$subtype] = !empty($api->smtp_from_email) ? $api->smtp_from_email : (isset($api->smtp_username) ? $api->smtp_username : '');
                }
            }
        }

        // CRM e-mail routing — state of the switch that puts every system
        // e-mail (invoices, tickets, password resets, the queue) through this
        // module's Email channel. See helpers/sms_wa_email_crm_email_helper.php
        $data['crm_routing'] = $this->_crm_routing_data();

        // Official WhatsApp — Cloud API link state + 24-hour conversation ledger
        $data['wa_cloud'] = $this->_wa_cloud_data();

        $data['title'] = 'SMS/WA/Email';
        $this->load->view('manage', $data);
    }

    /**
     * Everything the "CRM Routing" panel on the Email tab renders: the five
     * switches, the sender that will carry the mail, and how much has gone
     * out through it. Degrades to "off" when the helper is unavailable.
     *
     * @return array
     */
    private function _crm_routing_data()
    {
        $available = function_exists('sms_wa_email_crm_option');

        $out = [
            'available'         => $available,
            'enabled'           => $available && sms_wa_email_crm_email_enabled(),
            'use_api_smtp'      => $available && sms_wa_email_crm_option('sms_wa_email_crm_email_use_api_smtp', '1') === '1',
            'block_no_balance'  => $available && sms_wa_email_crm_option('sms_wa_email_crm_email_block_no_balance', '1') === '1',
            'fallback_smtp'     => $available && sms_wa_email_crm_option('sms_wa_email_crm_email_fallback_smtp', '1') === '1',
            'reply_to_original' => $available && sms_wa_email_crm_option('sms_wa_email_crm_email_reply_to_original', '1') === '1',
            'stats'             => ['today' => 0, 'today_failed' => 0, 'total' => 0, 'blocked' => 0],
            'sender'            => '',
            'sender_mode'       => 'crm',
            'api_name'          => '',
        ];

        if (!$available) {
            return $out;
        }

        $out['stats'] = sms_wa_email_crm_email_stats();

        $api = function_exists('_ccx_get_default_api') ? _ccx_get_default_api('email', 'transactional') : null;
        if ($api) {
            $out['api_name'] = isset($api->api_name) ? $api->api_name : '';
            $out['sender']   = !empty($api->smtp_from_email)
                ? $api->smtp_from_email
                : (isset($api->smtp_username) ? $api->smtp_username : '');

            // "Use CRM Email Settings" APIs already send on this installation's
            // own SMTP, so there is no transport to swap in — only billing.
            $out['sender_mode'] = !empty($api->use_crm_smtp) ? 'crm' : 'api';
        }

        if ($out['sender'] === '') {
            $out['sender'] = trim((string) get_option('smtp_email'));
        }

        return $out;
    }

    /**
     * Save the CRM routing switches. Every switch is posted on every save,
     * so an unchecked box is stored as '0' rather than left at its default.
     */
    public function save_crm_email_settings()
    {
        sms_wa_email_require_ajax('settings', 'email');

        if (!$this->input->post()) {
            echo json_encode(['success' => false, 'message' => 'No data received']);

            return;
        }

        $flags = [
            'sms_wa_email_route_crm_email',
            'sms_wa_email_crm_email_use_api_smtp',
            'sms_wa_email_crm_email_block_no_balance',
            'sms_wa_email_crm_email_fallback_smtp',
            'sms_wa_email_crm_email_reply_to_original',
        ];

        foreach ($flags as $flag) {
            update_option($flag, $this->input->post($flag) == '1' ? '1' : '0');
        }

        echo json_encode([
            'success' => true,
            'message' => 'CRM email routing settings saved',
        ]);
    }

    /* ───────────── Official WhatsApp (Cloud API bridge) ───────────── */

    /**
     * Everything the Official WhatsApp tab needs to describe the Cloud API
     * link. Degrades to a plain "module not installed" shape when the WhatsApp
     * module is inactive, so the tab renders either way.
     */
    private function _wa_cloud_data()
    {
        $out = [
            'installed'    => sms_wa_email_can_channel('whatsapp') && function_exists('whatsapp_omni_status'),
            'status'       => null,
            'stats'        => null,
            'recent'       => [],
            'templates'    => [],
            'autosync'     => null,
            'needs_params' => 0,
            'module_url'   => admin_url('whatsapp'),
        ];

        if (!$out['installed']) {
            return $out;
        }

        $out['status'] = whatsapp_omni_status();

        if ($out['status']['ready']) {
            // Keep this channel's templates in step with the tenant's approved
            // Meta library before the tab renders — throttled, so opening the
            // page repeatedly costs nothing.
            $out['autosync']     = whatsapp_omni_autosync_templates();
            $out['needs_params'] = whatsapp_omni_needs_params_count();
            $out['stats']        = whatsapp_credits_stats(30);
            $out['recent']       = whatsapp_credits_recent(10);
            $out['templates']    = whatsapp_omni_approved_templates();
        }

        return $out;
    }

    /**
     * Manual "Sync templates" — pull the approved library from Meta, then
     * re-map it onto this channel's templates.
     */
    public function wa_sync_templates()
    {
        sms_wa_email_require_ajax('templates', 'whatsapp');

        if (!function_exists('whatsapp_omni_autosync_templates')) {
            echo json_encode(['success' => false, 'message' => 'The WhatsApp module is not active on this account.']);
            return;
        }

        $status = whatsapp_omni_status();
        if (!$status['ready']) {
            echo json_encode(['success' => false, 'message' => $status['reason'] ?: 'WhatsApp Cloud API is not connected.']);
            return;
        }

        // Pull first so a template approved minutes ago is already in the cache.
        $pulled = null;
        $this->load->model('whatsapp/whatsapp_model');
        $pull = $this->whatsapp_model->sync_templates();
        if (isset($pull['error'])) {
            // A failed pull is not fatal — map whatever was cached last time.
            $pulled = $pull['error'];
        }

        $result = whatsapp_omni_autosync_templates(true);

        echo json_encode([
            'success'      => true,
            'pull_error'   => $pulled,
            'result'       => $result,
            'needs_params' => whatsapp_omni_needs_params_count(),
            'templates'    => whatsapp_omni_approved_templates(),
        ]);
    }

    /**
     * Approved Cloud API templates for the template-mapping picker.
     */
    public function wa_cloud_templates()
    {
        sms_wa_email_require_ajax('templates', 'whatsapp');

        if (!function_exists('whatsapp_omni_approved_templates')) {
            echo json_encode(['ready' => false, 'templates' => []]);
            return;
        }

        $status = whatsapp_omni_status();

        echo json_encode([
            'ready'     => $status['ready'],
            'reason'    => $status['reason'],
            'templates' => whatsapp_omni_approved_templates(),
        ]);
    }

    /**
     * Live 24-hour conversation ledger for the Official WhatsApp tab.
     */
    public function wa_conversations()
    {
        sms_wa_email_require_ajax('view', 'whatsapp');

        if (!function_exists('whatsapp_credits_stats')) {
            echo json_encode(['ready' => false, 'stats' => null, 'recent' => []]);
            return;
        }

        $days = (int) $this->input->get('days');
        $days = $days > 0 ? min($days, 365) : 30;

        echo json_encode([
            'ready'  => whatsapp_omni_active(),
            'stats'  => whatsapp_credits_stats($days),
            'recent' => whatsapp_credits_recent(15),
        ]);
    }

    public function settings()
    {
        sms_wa_email_require('settings');

        if ($this->input->post()) {
            $post_data = $this->input->post();
            $settings_group = $this->input->post('settings_group');
            unset($post_data['settings_group']);

            foreach ($post_data as $key => $val) {
                update_option($key, $val);
            }
            set_alert('success', _l('settings_updated'));
            redirect(admin_url('sms_wa_email#' . $settings_group));
        }

        $data['title'] = 'SMS/WA/Email Settings';
        $this->load->view('settings', $data);
    }

    /* AJAX METHODS FOR TEMPLATES */

    public function get_templates($type)
    {
        // Read-only: the hooks panel needs this list too, so `view` on the
        // channel is enough — editing is gated separately below.
        sms_wa_email_require_ajax('view', $type);

        $templates = $this->db->where('type', $type)->get(db_prefix() . 'sms_wa_email_templates')->result_array();
        echo json_encode($templates);
    }

    public function save_template()
    {
        if ($this->input->post()) {
            $data = $this->input->post();

            // The channel being written to is the template's own type — never
            // trust the tab the request claims to have come from.
            sms_wa_email_require_ajax('templates', isset($data['type']) ? $data['type'] : '');

            $id = isset($data['id']) && $data['id'] != '' ? $data['id'] : false;

            // Editing an existing row must also be allowed on its CURRENT
            // channel, so a template cannot be moved out of a channel the
            // staff member has no access to.
            if ($id) {
                $existing_row = $this->db->where('id', $id)->get(db_prefix() . 'sms_wa_email_templates')->row();
                if ($existing_row && !sms_wa_email_can_channel($existing_row->type)) {
                    ajax_access_denied();
                }
            }

            $data['is_attachment'] = isset($data['is_attachment']) && $data['is_attachment'] == '1' ? 1 : 0;
            $data['active'] = isset($data['active']) && $data['active'] == '1' ? 1 : 0;
            $data['is_default'] = isset($data['is_default']) && $data['is_default'] == '1' ? 1 : 0;

            // A WhatsApp template the user has edited becomes theirs: the Cloud
            // API auto-map engine stops refreshing it from Meta.
            if (isset($data['type']) && $data['type'] === 'whatsapp'
                && $this->db->field_exists('wa_auto_synced', db_prefix() . 'sms_wa_email_templates')) {
                $data['wa_auto_synced'] = 0;
            }

            // AI Call Agent numeric fields
            if (isset($data['retry_count'])) {
                $data['retry_count'] = (int) $data['retry_count'];
            }
            if (isset($data['retry_interval'])) {
                $data['retry_interval'] = (int) $data['retry_interval'];
            }

            if ($data['is_default'] == 1) {
                // Remove default from other templates of this type
                $this->db->where('type', $data['type']);
                if ($id) {
                    $this->db->where('id !=', $id);
                }
                $this->db->update(db_prefix() . 'sms_wa_email_templates', ['is_default' => 0]);
            }

            if ($id) {
                unset($data['id']);
                $this->db->where('id', $id);
                $this->db->update(db_prefix() . 'sms_wa_email_templates', $data);
                $insert_id = $id;
            } else {
                // If it's the first template added for this type, make it default automatically
                $existing = $this->db->where('type', $data['type'])->get(db_prefix() . 'sms_wa_email_templates')->row();
                if (!$existing) {
                    $data['is_default'] = 1;
                }
                $this->db->insert(db_prefix() . 'sms_wa_email_templates', $data);
                $insert_id = $this->db->insert_id();
            }

            echo json_encode(['success' => true, 'id' => $insert_id]);
        }
    }

    /**
     * Upload voice note audio file for AI Call Agent templates.
     * Stores files in uploads/ai_call_voice_notes/ directory.
     */
    public function upload_voice_note()
    {
        sms_wa_email_require_ajax('templates', 'ai_call_agent');

        $upload_dir = FCPATH . 'uploads/ai_call_voice_notes/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $config['upload_path']   = $upload_dir;
        $config['allowed_types'] = 'wav';
        $config['max_size']      = 10240; // 10MB
        $config['encrypt_name']  = true;

        $this->load->library('upload', $config);

        if (!$this->upload->do_upload('voice_note_file')) {
            echo json_encode(['success' => false, 'error' => $this->upload->display_errors('', '')]);
        } else {
            $file_data = $this->upload->data();
            $file_path = 'uploads/ai_call_voice_notes/' . $file_data['file_name'];
            echo json_encode(['success' => true, 'file_path' => $file_path, 'file_name' => $file_data['orig_name']]);
        }
    }

    /* ───────────── Bulk template import (Excel / CSV) ───────────── */

    /**
     * Only these channels can be bulk-imported — the sheet format is the DLT
     * approval export, which has no meaning for e-mail or the call agent.
     */
    private function _import_type()
    {
        $type = (string) $this->input->post('type');

        return in_array($type, ['sms', 'whatsapp'], true) ? $type : 'sms';
    }

    /**
     * Step 1 — parse the uploaded workbook and hand back a reviewable preview:
     * mapped columns, generated titles, derived message type and a per-row
     * duplicate verdict. Nothing is written here.
     */
    public function import_preview()
    {
        $type = $this->_import_type();
        sms_wa_email_require_ajax('templates', $type);

        require_once module_dir_path('sms_wa_email', 'helpers/sms_wa_email_import_helper.php');

        if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            $msg = 'Please choose a file to import.';
            if (isset($_FILES['import_file']) && in_array($_FILES['import_file']['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
                $msg = 'That file is larger than this server accepts. Split it or upload it as CSV.';
            }
            echo json_encode(['success' => false, 'message' => $msg]);
            return;
        }

        $name = $_FILES['import_file']['name'];
        $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if (!in_array($ext, ['xlsx', 'csv', 'txt', 'tsv'], true)) {
            echo json_encode([
                'success' => false,
                'message' => $ext === 'xls'
                    ? 'The old .xls format cannot be read. Open it in Excel and save as .xlsx or CSV.'
                    : 'Unsupported file type. Upload an .xlsx workbook or a .csv file.',
            ]);
            return;
        }

        if ($_FILES['import_file']['size'] > 8 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'That file is over 8 MB. Please split it into smaller sheets.']);
            return;
        }

        try {
            $matrix = sms_wa_email_import_read_file($_FILES['import_file']['tmp_name'], $ext);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            return;
        }

        if (empty($matrix)) {
            echo json_encode(['success' => false, 'message' => 'That file has no readable rows.']);
            return;
        }

        $result         = sms_wa_email_import_prepare($matrix, $type);
        $result['file'] = $name;
        $result['type'] = $type;

        echo json_encode($result);
    }

    /**
     * Step 2 — write the rows the user kept. Every row is re-validated against
     * what is stored right now, so a sheet imported twice (or two admins
     * importing together) can never create a duplicate.
     */
    public function import_commit()
    {
        $type = $this->_import_type();
        sms_wa_email_require_ajax('templates', $type);

        require_once module_dir_path('sms_wa_email', 'helpers/sms_wa_email_import_helper.php');

        $rows = json_decode((string) $this->input->post('rows'), true);
        if (!is_array($rows) || empty($rows)) {
            echo json_encode(['success' => false, 'message' => 'No templates were selected for import.']);
            return;
        }

        if (count($rows) > SMS_WA_EMAIL_IMPORT_MAX_ROWS) {
            $rows = array_slice($rows, 0, SMS_WA_EMAIL_IMPORT_MAX_ROWS);
        }

        $existing = sms_wa_email_import_existing_index($type);
        $used     = $existing['titles'];
        $table    = db_prefix() . 'sms_wa_email_templates';
        $has_any  = $existing['count'] > 0;

        $imported = 0;
        $skipped  = 0;
        $failed   = 0;
        $details  = [];

        foreach ($rows as $row) {
            $msgId   = isset($row['msg_template_id']) ? trim((string) $row['msg_template_id']) : '';
            $header  = isset($row['header_id']) ? trim((string) $row['header_id']) : '';
            $content = isset($row['content']) ? trim((string) $row['content']) : '';
            $rowNo   = isset($row['row']) ? (int) $row['row'] : 0;

            if ($content === '' || $msgId === '') {
                $failed++;
                $details[] = ['row' => $rowNo, 'result' => 'skipped', 'reason' => 'Missing Msg Template ID or content'];
                continue;
            }

            $idKey      = strtolower($msgId);
            $contentKey = sms_wa_email_import_content_key($content);

            if (isset($existing['ids'][$idKey])
                || ($contentKey !== '' && isset($existing['contents'][$contentKey]))) {
                $skipped++;
                $details[] = ['row' => $rowNo, 'result' => 'skipped', 'reason' => 'Already exists'];
                continue;
            }

            // Titles typed in the preview are honoured; blank ones fall back to
            // the generator. Either way uniqueness is settled here.
            $title = isset($row['title']) ? trim((string) $row['title']) : '';
            if ($title === '' || isset($used[strtolower($title)])) {
                $title = sms_wa_email_import_title($content, $used);
            } else {
                $title = sms_wa_email_import_trim_title($title, 240);
                $used[strtolower($title)] = true;
            }

            $subtype = isset($row['message_subtype']) && $row['message_subtype'] === 'promotional'
                ? 'promotional'
                : (isset($row['message_subtype']) && $row['message_subtype'] === 'transactional'
                    ? 'transactional'
                    : sms_wa_email_import_subtype($header));

            $data = [
                'type'            => $type,
                'message_subtype' => $subtype,
                'title'           => $title,
                'content'         => $content,
                'msg_template_id' => $msgId,
                'header_id'       => $header,
                'sample_content'  => $content,
                'is_attachment'   => 0,
                'active'          => 1,
                // Mirrors save_template(): the very first template of a channel
                // becomes its default.
                'is_default'      => $has_any ? 0 : 1,
            ];

            $inserted = $this->db->insert($table, $data);
            if (!$inserted || !$this->db->affected_rows()) {
                $failed++;
                $details[] = ['row' => $rowNo, 'result' => 'failed', 'reason' => 'Database rejected the row'];
                continue;
            }

            $has_any  = true;
            $imported++;
            $existing['ids'][$idKey] = true;
            if ($contentKey !== '') {
                $existing['contents'][$contentKey] = true;
            }
            $details[] = ['row' => $rowNo, 'result' => 'imported', 'title' => $title];
        }

        if ($imported > 0) {
            log_activity('Omni Messaging: imported ' . $imported . ' ' . $type . ' template(s) from a spreadsheet ('
                . $skipped . ' duplicate(s) skipped, ' . $failed . ' rejected)');
        }

        echo json_encode([
            'success'  => true,
            'imported' => $imported,
            'skipped'  => $skipped,
            'failed'   => $failed,
            'details'  => $details,
        ]);
    }

    /**
     * A ready-made sheet in exactly the shape the importer expects.
     */
    public function import_sample()
    {
        sms_wa_email_require('templates', 'sms');

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=sms_templates_import_sample.csv');

        $out = fopen('php://output', 'w');
        fprintf($out, "\xEF\xBB\xBF");
        fputcsv($out, ['message', 'sender_id', 'approved_message']);
        fputcsv($out, ['1107161234567890', 'HLTHO', 'Dear {#var#}, your appointment with {#var#} is confirmed for {#var#}. - HealthO Pro']);
        fputcsv($out, ['1107161234567891', 'HLTHO', 'Dear {#var#}, your lab report is ready. Download it here: {#var#} - HealthO Pro']);
        fputcsv($out, ['1107161234567892', '123456', 'Dear {#var#}, get 20% off on our full body check-up package this month. Call {#var#} to book. - HealthO Pro']);
        fclose($out);
        exit;
    }

    public function delete_template($id)
    {
        $template = $this->db->where('id', $id)->get(db_prefix() . 'sms_wa_email_templates')->row();

        sms_wa_email_require_ajax('templates', $template ? $template->type : '');

        if ($template) {
            $this->db->where('id', $id);
            $this->db->delete(db_prefix() . 'sms_wa_email_templates');

            // If deleted was default, make the oldest default
            if ($template->is_default == 1) {
                $first = $this->db->where('type', $template->type)->order_by('id', 'asc')->get(db_prefix() . 'sms_wa_email_templates')->row();
                if ($first) {
                    $this->db->where('id', $first->id);
                    $this->db->update(db_prefix() . 'sms_wa_email_templates', ['is_default' => 1]);
                }
            }
        }
        echo json_encode(['success' => true]);
    }

    public function toggle_active($id)
    {
        $template = $this->db->where('id', $id)->get(db_prefix() . 'sms_wa_email_templates')->row();

        sms_wa_email_require_ajax('templates', $template ? $template->type : '');

        if ($this->input->post()) {
            $status = $this->input->post('status') == '1' ? 1 : 0;
            $this->db->where('id', $id);
            $this->db->update(db_prefix() . 'sms_wa_email_templates', ['active' => $status]);
            echo json_encode(['success' => true]);
        }
    }

    /**
     * One-click: give every registered hook a ready-to-use EMAIL template.
     *
     * Idempotent and non-destructive — a hook that already has a mapping, or a
     * template that already carries the seed title, is left exactly as it is.
     * Mappings are created switched OFF, so pressing this never starts sending
     * anything on its own. See helpers/sms_wa_email_email_seeder_helper.php.
     */
    public function seed_email_templates()
    {
        sms_wa_email_require_ajax('templates', 'email');

        if (!function_exists('sms_wa_email_seed_hook_email_templates')) {
            echo json_encode(['success' => false, 'message' => 'The template seeder helper is missing from this installation.']);
            return;
        }

        // Writing hook mappings is the `hooks` capability, not `templates` —
        // someone who may only manage templates still gets the templates.
        $report = sms_wa_email_seed_hook_email_templates([
            'create_mappings' => sms_wa_email_can('hooks'),
        ]);

        if (!empty($report['error'])) {
            echo json_encode(['success' => false, 'message' => $report['error']]);
            return;
        }

        // Once the admin has done this by hand, install.php must not quietly
        // re-seed rows they may go on to delete.
        update_option('sms_wa_email_hook_email_templates_seeded', '1');

        // A renamed hook variable would ship a literal {tag} — say so loudly
        // rather than letting it reach a patient's inbox.
        $report['pack_warnings'] = function_exists('sms_wa_email_validate_hook_email_seed_pack')
            ? sms_wa_email_validate_hook_email_seed_pack()
            : [];

        log_activity('Omni Messaging: seeded hook e-mail templates ('
            . (int) $report['created'] . ' created, '
            . (int) $report['imported'] . ' imported from Email Templates, '
            . (int) $report['reused'] . ' already in place, '
            . (int) $report['mapped'] . ' mapped inactive)');

        $report['success'] = true;
        echo json_encode($report);
    }

    /**
     * Flip one channel's sending switch on or off (the toggle that sits on the
     * balance cards). Superadmin only — this decides whether the whole account
     * may send on a channel, so it sits deliberately ABOVE the module's own
     * feature/channel capabilities rather than inside them.
     *
     * Writes a local option; the master's own {prefix}_{subtype}_active
     * allocation columns are never touched from here. The gate itself lives in
     * _ccx_check_channel_balance(), so hooks, campaigns, auto-schedulers, CRM
     * e-mail routing and the WhatsApp Cloud bridge all obey it.
     */
    public function toggle_channel_send()
    {
        if (!is_admin()) {
            ajax_access_denied();
        }

        $channel = sms_wa_email_normalize_channel($this->input->post('channel'));
        $subtype = $this->input->post('subtype') === 'promo' ? 'promo' : 'trans';
        $enabled = $this->input->post('status') == '1';

        $channels = sms_wa_email_channel_map();
        if (!isset($channels[$channel])) {
            echo json_encode(['success' => false, 'message' => 'Unknown channel']);

            return;
        }

        $prefix = _ccx_channel_to_col_prefix($channel);
        update_option(ccx_channel_send_option($prefix, $subtype), $enabled ? '1' : '0');

        $label = $channels[$channel] . ' ' . ($subtype === 'promo' ? 'promotional' : 'transactional');

        log_activity('Omni Messaging: ' . $label . ' sending switched ' . ($enabled ? 'ON' : 'OFF'));

        echo json_encode([
            'success' => true,
            'enabled' => $enabled,
            'message' => $label . ' sending is now ' . ($enabled ? 'ON' : 'OFF'),
        ]);
    }

    public function run_install_schema()
    {
        if (is_admin()) {
            $CI = &get_instance();
            require_once(module_dir_path('sms_wa_email', 'install.php'));
            echo "Schema installed/updated.";
        }
    }

    public function get_company_limits()
    {
        sms_wa_email_require_ajax('settings');

        $this->db->select('c.userid as client_id, c.company, l.sms_limit, l.sms_count, l.wa_limit, l.wa_count, l.email_limit, l.email_count, l.ai_call_limit, l.ai_call_count');
        $this->db->from(db_prefix() . 'clients c');
        $this->db->join(db_prefix() . 'sms_wa_email_company_limits l', 'c.userid = l.client_id', 'left');
        $this->db->where('c.active', 1);
        $companies = $this->db->get()->result_array();

        // Ensure defaults if null
        foreach ($companies as &$c) {
            foreach (['sms_limit', 'sms_count', 'wa_limit', 'wa_count', 'email_limit', 'email_count', 'ai_call_limit', 'ai_call_count'] as $f) {
                if ($c[$f] === null)
                    $c[$f] = 0;
            }
        }
        echo json_encode($companies);
    }

    public function save_company_limits()
    {
        sms_wa_email_require_ajax('settings');

        if ($this->input->post()) {
            $data = $this->input->post();
            $client_id = $data['client_id'];
            unset($data['client_id']);

            // Format numeric values
            foreach (['sms_limit', 'sms_count', 'wa_limit', 'wa_count', 'email_limit', 'email_count', 'ai_call_limit', 'ai_call_count'] as $f) {
                $data[$f] = isset($data[$f]) && is_numeric($data[$f]) ? (int) $data[$f] : 0;
            }

            $exists = $this->db->where('client_id', $client_id)->get(db_prefix() . 'sms_wa_email_company_limits')->row();
            if ($exists) {
                $this->db->where('client_id', $client_id);
                $this->db->update(db_prefix() . 'sms_wa_email_company_limits', $data);
            } else {
                $data['client_id'] = $client_id;
                $this->db->insert(db_prefix() . 'sms_wa_email_company_limits', $data);
            }
            echo json_encode(['success' => true]);
        }
    }

    /* AJAX METHODS FOR HOOKS */

    /**
     * Get all registered system hooks with their current template mappings.
     * Returns JSON array — each hook has a 'mappings' array with all recipient mappings.
     *
     * @param string $channel  The channel to get mappings for (sms, whatsapp, etc.)
     */
    public function get_hooks($channel = '')
    {
        sms_wa_email_require_ajax('hooks', $channel);

        $hooks = function_exists('ccx_get_registered_hooks') ? ccx_get_registered_hooks() : [];

        // Get existing mappings for this channel
        $mappings_by_hook = [];
        $table = db_prefix() . 'ccx_hook_template_map';
        if ($this->db->table_exists($table)) {
            if ($channel) {
                $this->db->where('channel', $channel);
            }
            $query = $this->db->get($table);
            foreach ($query->result() as $row) {
                $mappings_by_hook[$row->hook_key][] = [
                    'mapping_id' => $row->id,
                    'template_id' => $row->template_id,
                    'active' => (int) $row->active,
                    'recipient_type' => isset($row->recipient_type) ? $row->recipient_type : 'patient',
                    'recipient_value' => isset($row->recipient_value) ? $row->recipient_value : null,
                ];
            }
        }

        // Merge mapping data into hooks
        foreach ($hooks as &$hook) {
            if (isset($mappings_by_hook[$hook['hook_key']])) {
                $hook['mappings'] = $mappings_by_hook[$hook['hook_key']];
            } else {
                $hook['mappings'] = [];
            }
        }

        echo json_encode($hooks);
    }

    /**
     * Save or update a hook → template mapping.
     * Uses mapping_id for updates; inserts new row if no mapping_id.
     */
    public function save_hook_mapping()
    {
        if ($this->input->post()) {
            $hook_key = $this->input->post('hook_key');
            $channel = $this->input->post('channel');
            $template_id = $this->input->post('template_id');
            $active = $this->input->post('active') == '1' ? 1 : 0;
            $mapping_id = $this->input->post('mapping_id');
            $recipient_type = $this->input->post('recipient_type') ?: 'patient';
            $recipient_value = $this->input->post('recipient_value') ?: null;

            sms_wa_email_require_ajax('hooks', $channel);

            // An inline toggle posts no channel of its own — authorise against
            // the channel the stored mapping actually belongs to.
            if ($mapping_id) {
                $existing_map = $this->db->where('id', $mapping_id)
                    ->get(db_prefix() . 'ccx_hook_template_map')->row();
                if ($existing_map && !sms_wa_email_can_channel($existing_map->channel)) {
                    ajax_access_denied();
                }
            }

            // Validate recipient_type
            if (!in_array($recipient_type, ['patient', 'staff', 'role', 'custom'])) {
                $recipient_type = 'patient';
            }
            // Patient type doesn't need recipient_value
            if ($recipient_type === 'patient') {
                $recipient_value = null;
            }

            $table = db_prefix() . 'ccx_hook_template_map';

            // Ensure table exists
            if (!$this->db->table_exists($table)) {
                $CI = &get_instance();
                require_once(module_dir_path('sms_wa_email', 'install.php'));
            }

            $save_data = [
                'template_id' => $template_id,
                'active' => $active,
                'recipient_type' => $recipient_type,
                'recipient_value' => $recipient_value,
            ];

            if ($mapping_id) {
                // Update existing mapping
                // If template_id is 0, this is an inline active toggle — only update active
                if (empty($template_id) || $template_id == '0') {
                    $this->db->where('id', $mapping_id);
                    $this->db->update($table, ['active' => $active]);
                } else {
                    $this->db->where('id', $mapping_id);
                    $this->db->update($table, $save_data);
                }
                $id = $mapping_id;
            } else {
                // Check for duplicate mapping before inserting
                $this->db->where('hook_key', $hook_key);
                $this->db->where('channel', $channel);
                $this->db->where('recipient_type', $recipient_type);
                if ($recipient_value === null) {
                    $this->db->where('recipient_value IS NULL', null, false);
                } else {
                    $this->db->where('recipient_value', $recipient_value);
                }
                $existing = $this->db->get($table)->row();
                if ($existing) {
                    echo json_encode(['success' => false, 'error' => 'duplicate', 'message' => 'This recipient mapping already exists for this hook']);
                    return;
                }

                // Insert new mapping
                $save_data['hook_key'] = $hook_key;
                $save_data['channel'] = $channel;
                $this->db->insert($table, $save_data);
                $id = $this->db->insert_id();
            }

            echo json_encode(['success' => true, 'id' => $id]);
        }
    }

    /**
     * Delete a specific hook mapping row.
     *
     * @param int $id  Mapping row ID
     */
    public function delete_hook_mapping($id)
    {
        $table = db_prefix() . 'ccx_hook_template_map';

        $mapping = $this->db->table_exists($table)
            ? $this->db->where('id', $id)->get($table)->row()
            : null;

        sms_wa_email_require_ajax('hooks', $mapping ? $mapping->channel : '');

        if ($mapping) {
            $this->db->where('id', $id);
            $this->db->delete($table);
        }

        echo json_encode(['success' => true]);
    }

    /**
     * Get list of active staff members for the recipient picker.
     * Returns JSON array of [{staffid, name, phonenumber}].
     */
    public function get_staff_list()
    {
        sms_wa_email_require_ajax('hooks');

        $this->db->select('staffid, CONCAT(firstname, " ", lastname) as name, phonenumber');
        $this->db->from(db_prefix() . 'staff');
        $this->db->where('active', 1);
        $this->db->order_by('firstname', 'asc');
        $staff = $this->db->get()->result_array();

        echo json_encode($staff);
    }

    /**
     * Get list of all roles for the recipient picker.
     * Returns JSON array of [{roleid, name}].
     */
    public function get_roles_list()
    {
        sms_wa_email_require_ajax('hooks');

        $this->db->select('roleid, name');
        $this->db->from(db_prefix() . 'roles');
        $this->db->order_by('name', 'asc');
        $roles = $this->db->get()->result_array();

        echo json_encode($roles);
    }

    /**
     * Get hook trigger logs, optionally filtered by channel.
     *
     * @param string $channel  Optional channel filter
     */
    public function get_hook_logs($channel = '')
    {
        // A blank/"all" channel must not become a back door to channels the
        // staff member cannot see — it collapses to their allowed set.
        if ($channel === '' || $channel === 'all') {
            sms_wa_email_require_ajax('logs');
        } else {
            sms_wa_email_require_ajax('logs', $channel);
        }

        $table = db_prefix() . 'ccx_hook_trigger_logs';
        if (!$this->db->table_exists($table)) {
            echo json_encode([]);
            return;
        }

        $this->db->select($table . '.*, ' . db_prefix() . 'sms_wa_email_templates.title as template_title, ' . db_prefix() . 'sms_wa_email_templates.message_subtype as message_subtype, CONCAT(' . db_prefix() . 'staff.firstname, " ", ' . db_prefix() . 'staff.lastname) as staff_name');
        $this->db->from($table);
        $this->db->join(db_prefix() . 'sms_wa_email_templates', db_prefix() . 'sms_wa_email_templates.id = ' . $table . '.template_id', 'left');
        $this->db->join(db_prefix() . 'staff', db_prefix() . 'staff.staffid = ' . $table . '.staff_id', 'left');

        if ($channel && $channel !== 'all') {
            $this->db->where($table . '.channel', $channel);
        } else {
            // Logs are written with both spellings ("whatsapp" from hooks,
            // "official_whatsapp" from campaigns), so match on either.
            $visible = [];
            foreach (array_keys(sms_wa_email_allowed_channels()) as $ch) {
                $visible[] = $ch;
                if ($ch === 'whatsapp') {
                    $visible[] = 'official_whatsapp';
                }
            }
            $this->db->where_in($table . '.channel', $visible);
        }

        $this->db->order_by($table . '.created_at', 'DESC');
        $this->db->limit(200);

        $logs = $this->db->get()->result_array();
        echo json_encode($logs);
    }

    /**
     * Delete a single hook trigger log entry.
     *
     * @param int $id  Log entry ID
     */
    public function delete_hook_log($id)
    {
        $table = db_prefix() . 'ccx_hook_trigger_logs';

        $log = $this->db->table_exists($table)
            ? $this->db->where('id', $id)->get($table)->row()
            : null;

        sms_wa_email_require_ajax('logs', $log ? $log->channel : '');

        if ($log) {
            $this->db->where('id', $id);
            $this->db->delete($table);
        }

        echo json_encode(['success' => true]);
    }

    /* ══════════════════════════════════════════════════════
       HOOK DEBUGGER — traces the full send pipeline for a hook
       ══════════════════════════════════════════════════════ */

    /**
     * Debug page: pick a hook and trace mapping → template →
     * balance → API → recipient, with an optional live test fire.
     */
    public function debug()
    {
        sms_wa_email_require('settings');

        // Self-heal: widen error_message (was varchar(500)) so the full
        // SMTP conversation fits in trigger logs instead of being cut off
        $logs_table = db_prefix() . 'ccx_hook_trigger_logs';
        if ($this->db->table_exists($logs_table)) {
            $err_col = $this->db->query('SHOW COLUMNS FROM `' . $logs_table . '` LIKE "error_message"')->row();
            if ($err_col && stripos($err_col->Type, 'varchar') !== false) {
                $this->db->query('ALTER TABLE `' . $logs_table . '` MODIFY `error_message` TEXT NULL');
            }
        }

        $data['hooks'] = function_exists('ccx_get_registered_hooks') ? ccx_get_registered_hooks() : [];
        $data['title'] = 'Omni Messaging — Hook Debugger';
        $this->load->view('debug', $data);
    }

    /**
     * AJAX: dry-run diagnostic for a hook. Walks every check
     * ccx_fire_hook() performs and reports where it would stop.
     * Nothing is sent and no balance is used.
     */
    public function run_hook_debug()
    {
        sms_wa_email_require_ajax('settings');

        $hook_key    = $this->input->post('hook_key');
        $test_email  = $this->input->post('test_email');
        $test_mobile = $this->input->post('test_mobile');
        $only_channel = $this->input->post('channel'); // '' or 'all' = every channel

        if (!empty($only_channel) && $only_channel !== 'all' && !sms_wa_email_can_channel($only_channel)) {
            ajax_access_denied();
        }

        $steps = [];
        $add = function ($status, $label, $details = '') use (&$steps) {
            $steps[] = ['status' => $status, 'label' => $label, 'details' => $details];
        };

        // ── 1. Hook registered? ──
        $hook_def = function_exists('ccx_get_hook_by_key') ? ccx_get_hook_by_key($hook_key) : null;
        if (!$hook_def) {
            $add('fail', 'Hook registry', 'Hook key "' . $hook_key . '" is NOT registered. Nothing will ever fire for it.');
            echo json_encode(['steps' => $steps, 'mappings' => [], 'logs' => []]);
            return;
        }
        $add('ok', 'Hook registry', 'Registered as "' . $hook_def['label'] . '" (module: ' . $hook_def['module'] . '). Variables: {' . implode('}, {', $hook_def['variables']) . '}');

        // ── 2. Mapping table ──
        $map_table = db_prefix() . 'ccx_hook_template_map';
        if (!$this->db->table_exists($map_table)) {
            $add('fail', 'Mapping table', 'Table ' . $map_table . ' does not exist — ccx_fire_hook() returns silently. Re-run the module installer.');
            echo json_encode(['steps' => $steps, 'mappings' => [], 'logs' => []]);
            return;
        }
        $add('ok', 'Mapping table', $map_table . ' exists.');

        // ── 3. Mappings for this hook (active or not), optionally one channel ──
        $this->db->where('hook_key', $hook_key);
        if (!empty($only_channel) && $only_channel !== 'all') {
            $this->db->where('channel', $only_channel);
        } else {
            // "All channels" means all the channels THIS staff member has
            $this->db->where_in('channel', array_keys(sms_wa_email_allowed_channels()));
        }
        $rows = $this->db->get($map_table)->result();
        $channel_note = (!empty($only_channel) && $only_channel !== 'all') ? ' for channel "' . $only_channel . '"' : '';
        if (empty($rows)) {
            $add('fail', 'Template mappings', 'No mapping rows' . $channel_note . ' for this hook. When it fires, nothing is sent on ' . ($channel_note ? 'that channel' : 'any channel') . '. Attach a template on the Hooks panel of the channel tab you want.');
        } else {
            $active_count = 0;
            foreach ($rows as $r) {
                if ((int) $r->active === 1) {
                    $active_count++;
                }
            }
            $add($active_count > 0 ? 'ok' : 'fail', 'Template mappings',
                count($rows) . ' mapping(s) found' . $channel_note . ', ' . $active_count . ' active. Only ACTIVE mappings send — each is traced below.');
        }

        // Sample data used for recipient resolution (mirrors what a real fire would pass)
        $sample_data = [
            'patient_name'  => 'Debug Patient',
            'mobile_number' => $test_mobile !== '' ? $test_mobile : '',
            'patient_email' => $test_email !== '' ? $test_email : '',
            'email'         => $test_email !== '' ? $test_email : '',
        ];

        // ── 4. Per-mapping pipeline trace ──
        $mappings_out = [];
        foreach ($rows as $r) {
            $m = [
                'mapping_id'      => (int) $r->id,
                'channel'         => $r->channel,
                'active'          => (int) $r->active,
                'recipient_type'  => isset($r->recipient_type) ? $r->recipient_type : 'patient',
                'recipient_value' => isset($r->recipient_value) ? $r->recipient_value : null,
                'checks'          => [],
                'verdict'         => '',
                'verdict_status'  => 'ok',
            ];
            $mc = function ($status, $label, $details = '') use (&$m) {
                $m['checks'][] = ['status' => $status, 'label' => $label, 'details' => $details];
            };
            $blocked = false;

            // 4a. Mapping active?
            if ((int) $r->active !== 1) {
                $mc('fail', 'Mapping active', 'This mapping is INACTIVE — ccx_fire_hook() skips it entirely. Toggle it on in the Hooks panel.');
                $blocked = true;
            } else {
                $mc('ok', 'Mapping active', 'Active.');
            }

            // 4b. Template exists + active?
            $template = $this->db->where('id', $r->template_id)->get(db_prefix() . 'sms_wa_email_templates')->row();
            $subtype = 'trans';
            $api_subtype = 'transactional';
            if (!$template) {
                $mc('fail', 'Template', 'Template #' . $r->template_id . ' not found — logs "no_template". Re-map to an existing template.');
                $blocked = true;
            } elseif ((int) $template->active !== 1) {
                $mc('fail', 'Template', '"' . $template->title . '" (#' . $template->id . ') exists but is INACTIVE — logs "no_template". Activate it on the Templates sub-tab.');
                $blocked = true;
            } else {
                $subtype = (!empty($template->message_subtype) && $template->message_subtype === 'promotional') ? 'promo' : 'trans';
                $api_subtype = !empty($template->message_subtype) ? $template->message_subtype : 'transactional';
                $tpl_info = '"' . $template->title . '" (#' . $template->id . '), subtype: ' . $api_subtype . '.';
                if ($r->channel === 'email') {
                    $tpl_info .= ' Subject: ' . ($template->subject !== '' && $template->subject !== null ? '"' . $template->subject . '"' : '(empty)') . '.';
                }
                $mc('ok', 'Template', $tpl_info);
            }

            // 4c. Recipient resolution (uses your test values)
            if (function_exists('_ccx_resolve_recipients')) {
                $resolved = _ccx_resolve_recipients($m['recipient_type'], $m['recipient_value'], $sample_data, $r->channel);
                $resolved_list = [];
                foreach ($resolved as $ri) {
                    if (!empty($ri['number'])) {
                        $resolved_list[] = $ri['number'] . (!empty($ri['name']) ? ' (' . $ri['name'] . ')' : '');
                    }
                }
                if (empty($resolved_list)) {
                    $what = ($r->channel === 'email') ? 'email address' : 'phone number';
                    $hint = ($m['recipient_type'] === 'patient')
                        ? 'Recipient type is "patient" — the ' . $what . ' comes from the hook data at fire time. Enter a test value above to simulate it, and make sure the real patient has a ' . $what . ' (email = primary contact email).'
                        : 'Recipient type "' . $m['recipient_type'] . '"' . ($m['recipient_value'] ? ' (value: ' . $m['recipient_value'] . ')' : '') . ' resolved to nobody — check that the staff/role exists, is active and has a ' . $what . '.';
                    $mc('fail', 'Recipient', 'Resolved to EMPTY → logs "no_recipient". ' . $hint);
                    $blocked = true;
                } else {
                    $mc('ok', 'Recipient', 'Type "' . $m['recipient_type'] . '" resolves to: ' . implode(', ', $resolved_list));
                }
            }

            // 4c-bis. WhatsApp Cloud API route — when the WhatsApp module is
            // connected this channel bypasses the gateway API entirely, so the
            // generic balance + default-API checks below do not apply to it.
            $wa_bridge_map = function_exists('_ccx_whatsapp_bridge_active') && _ccx_whatsapp_bridge_active($r->channel);

            if ($wa_bridge_map) {
                $wa_st = whatsapp_omni_status();
                $mc('ok', 'Route', 'WhatsApp Cloud API — sending from your own number ' . $wa_st['number_label']
                    . '. The gateway API row is not used, and billing is per 24-hour conversation.');

                $wa_map = $template ? whatsapp_omni_template_for($template) : ['name' => '', 'variables' => 0, 'language' => ''];
                if ($wa_map['name'] !== '') {
                    $mc('ok', 'Cloud template', 'Mapped to approved template "' . $wa_map['name'] . '" (' . $wa_map['language']
                        . ', ' . $wa_map['variables'] . ' variable(s)) — deliverable whether or not the customer window is open.');
                } else {
                    $mc('warn', 'Cloud template', 'No approved template mapped — this sends as free-form text, which Meta only accepts '
                        . 'while the contact has messaged you in the last 24 hours. Map one in the template editor to reach closed windows.');
                }

                $wa_cat = $wa_map['name'] !== ''
                    ? whatsapp_conversation_category_for($wa_map['name'], $wa_map['language'], $subtype)
                    : 'service';
                $wa_bucket = whatsapp_conversation_bucket($wa_cat);

                if ($wa_bucket === null) {
                    $mc('ok', 'Credits', 'Category "' . $wa_cat . '" — free, Meta does not bill conversations the customer opened.');
                } else {
                    $wa_bal = _ccx_check_channel_balance('whatsapp', $wa_bucket);
                    if ($wa_bal === true) {
                        $mc('ok', 'Credits', 'Category "' . $wa_cat . '" — one ' . ($wa_bucket === 'promo' ? 'promotional' : 'transactional')
                            . ' credit per new 24-hour conversation. Balance available; messages inside an open window cost nothing.');
                    } else {
                        $mc('fail', 'Credits', 'BLOCKED (' . $wa_bal['status'] . '): ' . $wa_bal['error']
                            . ' — only recipients already inside an open 24-hour window would still receive this.');
                        $blocked = true;
                    }
                }
            }

            // 4d. Balance
            if (!$wa_bridge_map && function_exists('_ccx_check_channel_balance')) {
                $bal = _ccx_check_channel_balance($r->channel, $subtype);
                if ($bal !== true) {
                    $mc('fail', 'Balance', 'BLOCKED (' . $bal['status'] . '): ' . $bal['error']);
                    $blocked = true;
                } else {
                    $mc('ok', 'Balance', ucfirst($subtype === 'promo' ? 'promotional' : 'transactional') . ' balance available.');
                }
            }

            // 4e. Default API
            if (!$wa_bridge_map && function_exists('_ccx_get_default_api')) {
                $api = _ccx_get_default_api($r->channel, $api_subtype);
                if (!$api) {
                    $mc('fail', 'Default API', 'No default ACTIVE API for channel "' . $r->channel . '" + subtype "' . $api_subtype . '" → logs "no_api". In CCX Msgs → APIs there must be one row with this message type + subtype marked Default AND Active. Note: the subtype must match the TEMPLATE\'s message type.');
                    $blocked = true;
                } else {
                    $api_info = '"' . $api->api_name . '" (scope: ' . (!empty($api->api_scope) ? $api->api_scope : 'global') . ')';
                    if ($r->channel === 'email') {
                        $api = $this->_apply_crm_smtp($api);
                        $from = !empty($api->smtp_from_email) ? $api->smtp_from_email : (!empty($api->smtp_username) ? $api->smtp_username : '(none)');
                        // Mirror send_smtp_email(): empty username falls back to from_email
                        $auth_user = !empty($api->smtp_username) ? $api->smtp_username : (!empty($api->smtp_from_email) ? $api->smtp_from_email . ' (fallback from From Email — username is empty)' : '(none!)');
                        $crm_protocol = 'smtp';
                        if (!empty($api->use_crm_smtp)) {
                            $crm_protocol = isset($api->crm_smtp_protocol) ? $api->crm_smtp_protocol : (get_option('email_protocol') ?: 'smtp');
                            $crm_source   = isset($api->crm_smtp_source) ? $api->crm_smtp_source : 'local';
                            $api_info .= ' — [CRM Email Settings mode: values below come from ' . ($crm_source === 'master' ? 'the MASTER installation\'s Setup → Settings → Email (this tenant has no SMTP host of its own)' : 'this tenant\'s Setup → Settings → Email') . ', protocol: ' . $crm_protocol . ']';
                            if (!empty($api->crm_smtp_note)) {
                                $api_info .= ' [' . $api->crm_smtp_note . ']';
                            }
                        }
                        $api_info .= ' — SMTP ' . (isset($api->smtp_host) ? $api->smtp_host : '?') . ':' . (isset($api->smtp_port) ? $api->smtp_port : '?') . ', from: ' . $from . ', auth user: ' . $auth_user;
                        // Mirror send_smtp_email(): CRM mode with mail/sendmail protocol sends without an SMTP host
                        $host_required = empty($api->use_crm_smtp) || in_array($crm_protocol, ['smtp', 'google', 'microsoft'], true);
                        if ($host_required && empty($api->smtp_host)) {
                            $mc('fail', 'Default API', $api_info . ' — SMTP HOST IS EMPTY, the send will fail.');
                            $blocked = true;
                            $api_info = null;
                        }
                    }
                    if ($api_info !== null) {
                        $mc('ok', 'Default API', $api_info);
                    }

                    // Email wrap diagnostics: exactly which header/footer the send will use
                    if ($r->channel === 'email') {
                        if (!empty($api->use_crm_smtp)) {
                            if (!function_exists('ccx_crm_email_wrap_settings')) {
                                $mc('warn', 'Email wrap', 'ccx_crm_email_wrap_settings() does NOT exist on this installation — the deployed code (or opcache) predates the CRM header/footer parity update. Emails still go out with the per-API header/footer. Deploy the latest build / reset PHP opcache.');
                            } else {
                                // Same preference as the real send: wrap follows the SMTP source
                                $wrap  = ccx_crm_email_wrap_settings(isset($api->crm_smtp_source) && $api->crm_smtp_source === 'master');
                                $own_h = strlen(trim((string) get_option('email_header')));
                                $own_f = strlen(trim((string) get_option('email_footer')));
                                $desc  = 'CRM Email Settings mode wraps as header + body + footer, same as core CRM mail. '
                                    . 'This installation\'s own options: email_header ' . ($own_h ? $own_h . ' chars' : 'EMPTY') . ', email_footer ' . ($own_f ? $own_f . ' chars' : 'EMPTY') . '. '
                                    . 'Resolved wrap → source: ' . strtoupper($wrap['source']) . ', header: ' . (trim($wrap['header']) !== '' ? strlen($wrap['header']) . ' chars' : 'EMPTY') . ', footer: ' . (trim($wrap['footer']) !== '' ? strlen($wrap['footer']) . ' chars' : 'EMPTY') . '.';
                                if (trim($wrap['header']) === '' && trim($wrap['footer']) === '') {
                                    $mc('warn', 'Email wrap', $desc . ' BOTH resolve EMPTY → emails send unwrapped. Fill Email Header / Email Footer under Setup → Settings → Email (on this installation, or on the MASTER for the tenant fallback).');
                                } else {
                                    $mc('ok', 'Email wrap', $desc);
                                }
                            }
                        } else {
                            $own_h = isset($api->email_header) ? strlen(trim((string) $api->email_header)) : 0;
                            $own_f = isset($api->email_footer) ? strlen(trim((string) $api->email_footer)) : 0;
                            $mc('info', 'Email wrap', 'Manual credentials mode — the per-API fields wrap the email: header ' . ($own_h ? $own_h . ' chars' : 'EMPTY') . ', footer ' . ($own_f ? $own_f . ' chars' : 'EMPTY') . ', signature ' . (!empty($api->email_signature) ? strlen(trim((string) $api->email_signature)) . ' chars' : 'EMPTY') . '.');
                        }
                    }
                }
            }

            // 4f. Email only: mailer model must be loadable on THIS tenant
            if ($r->channel === 'email') {
                $mailer_ok = class_exists('Ccx_msgs_api_model', false)
                    || is_file(FCPATH . 'modules/ccx_msgs/models/Ccx_msgs_api_model.php');
                if ($mailer_ok) {
                    $mc('ok', 'Mailer', 'Ccx_msgs_api_model available (modules/ccx_msgs).');
                } else {
                    $mc('fail', 'Mailer', 'Ccx_msgs_api_model NOT available — modules/ccx_msgs is missing on this installation, so SMTP sends cannot run.');
                    $blocked = true;
                }
            }

            if ($blocked) {
                $m['verdict'] = 'WOULD NOT SEND — fix the failed step(s) above.';
                $m['verdict_status'] = 'fail';
            } else {
                $m['verdict'] = 'All checks passed — this mapping WOULD send when the hook fires.';
                $m['verdict_status'] = 'ok';
            }
            $mappings_out[] = $m;
        }

        // ── 5. Recent trigger logs for this hook ──
        $logs = [];
        $logs_table = db_prefix() . 'ccx_hook_trigger_logs';
        if ($this->db->table_exists($logs_table)) {
            $logs = $this->db->where('hook_key', $hook_key)
                ->order_by('created_at', 'DESC')
                ->limit(15)
                ->get($logs_table)
                ->result_array();
        }
        if (empty($logs)) {
            $add('warn', 'Recent trigger logs', 'No log rows for this hook (logs auto-purge after 48h). If you clicked the action and NOTHING appears here afterwards, the hook itself is not firing — check the browser console / network tab for the fire_action_hook AJAX call.');
        } else {
            $add('info', 'Recent trigger logs', count($logs) . ' recent row(s) shown below — the status column tells you exactly where each attempt stopped.');
        }

        echo json_encode(['steps' => $steps, 'mappings' => $mappings_out, 'logs' => $logs]);
    }

    /**
     * Apply "Use CRM Email Settings" overrides to an email API object —
     * same behavior as Ccx_msgs_api_model::apply_crm_smtp_overrides(),
     * duplicated here because that model may not be loadable on tenants.
     */
    private function _apply_crm_smtp($api)
    {
        if (empty($api->use_crm_smtp)) {
            return $api;
        }
        $api = clone $api;
        if (function_exists('ccx_crm_smtp_settings')) {
            // Same resolution as the real send: tenant options first, then
            // automatic fallback to the master installation's settings
            $crm = ccx_crm_smtp_settings();
            $api->smtp_host        = $crm['host'];
            $api->smtp_port        = $crm['port'];
            $api->smtp_encryption  = $crm['encryption'];
            $api->smtp_username    = $crm['username'];
            $api->smtp_from_email  = $crm['from_email'];
            $api->smtp_password    = $crm['password'];
            $api->crm_smtp_protocol = $crm['protocol'];
            $api->crm_smtp_source   = $crm['source'];
            $api->crm_smtp_note     = isset($crm['note']) ? $crm['note'] : '';
        } else {
            $api->smtp_host       = trim(get_option('smtp_host'));
            $api->smtp_port       = (int) (get_option('smtp_port') ?: 587);
            $api->smtp_encryption = get_option('smtp_encryption');
            $api->smtp_username   = trim(get_option('smtp_username'));
            $api->smtp_from_email = trim(get_option('smtp_email'));
            $api->smtp_password   = get_option('smtp_password');
            $api->crm_smtp_protocol = get_option('email_protocol') ?: 'smtp';
            $api->crm_smtp_source   = 'local';
            $api->crm_smtp_note     = 'ccx_crm_smtp_settings() helper not loaded — legacy path';
        }
        return $api;
    }

    /**
     * AJAX: SMTP credential probe. Connects to the default email API's
     * SMTP server and attempts AUTH LOGIN with the exact credentials the
     * sender would use. No email is sent. Also reports whether the stored
     * password decrypts with this app's encryption key — a mismatch means
     * raw ciphertext is sent as the password (guaranteed 535).
     */
    public function debug_smtp_check()
    {
        sms_wa_email_require_ajax('settings', 'email');

        $subtype = $this->input->post('subtype') ?: 'transactional';
        $api = function_exists('_ccx_get_default_api') ? _ccx_get_default_api('email', $subtype) : null;
        if (!$api) {
            echo json_encode(['success' => false, 'message' => 'No default active email API found for subtype "' . $subtype . '".']);
            return;
        }

        $lines = [];
        $crm_mode = !empty($api->use_crm_smtp);
        $api = $this->_apply_crm_smtp($api);
        $host = isset($api->smtp_host) ? $api->smtp_host : '';
        $port = isset($api->smtp_port) ? (int) $api->smtp_port : 587;
        $enc  = isset($api->smtp_encryption) ? strtolower((string) $api->smtp_encryption) : '';

        // Resolve auth user exactly like send_smtp_email()
        $user = !empty($api->smtp_username) ? $api->smtp_username : (isset($api->smtp_from_email) ? $api->smtp_from_email : '');
        $lines[] = 'API        : "' . $api->api_name . '" (' . $subtype . ')';
        if ($crm_mode) {
            $crm_protocol = isset($api->crm_smtp_protocol) ? $api->crm_smtp_protocol : (get_option('email_protocol') ?: 'smtp');
            $crm_source   = isset($api->crm_smtp_source) ? $api->crm_smtp_source : 'local';
            $lines[] = 'Mode       : Using CRM Email Settings — source: ' . ($crm_source === 'master' ? 'MASTER installation (this tenant has no SMTP host in Setup → Settings → Email)' : 'THIS installation (Setup → Settings → Email)');
            $lines[] = 'Protocol   : ' . $crm_protocol . ($crm_protocol !== 'smtp' ? '  ← sends follow this protocol; the AUTH LOGIN probe below may not reflect the real send' : '');
            if (!empty($api->crm_smtp_note)) {
                $lines[] = 'Fallback   : ' . $api->crm_smtp_note;
            }
            if (function_exists('ccx_crm_email_wrap_settings')) {
                $wrap = ccx_crm_email_wrap_settings(isset($api->crm_smtp_source) && $api->crm_smtp_source === 'master');
                $lines[] = 'Wrap       : header/footer from ' . ($wrap['source'] === 'master' ? 'the MASTER installation' : 'THIS installation') . ' — header ' . (trim($wrap['header']) !== '' ? strlen($wrap['header']) . ' chars' : 'EMPTY') . ', footer ' . (trim($wrap['footer']) !== '' ? strlen($wrap['footer']) . ' chars' : 'EMPTY');
            } else {
                $lines[] = 'Wrap       : ⚠ ccx_crm_email_wrap_settings() MISSING — deployed code/opcache predates the header/footer parity fix';
            }
        }
        $lines[] = 'Server     : ' . $host . ':' . $port . ' (encryption: ' . ($enc !== '' ? $enc : 'none') . ')';
        $lines[] = 'Auth user  : ' . ($user !== '' ? $user : '(EMPTY!)') . (empty($api->smtp_username) ? '  ← fallback from From Email because SMTP Username is empty' : '');

        // Resolve password exactly like send_smtp_email()
        $pass_raw = isset($api->smtp_password) ? (string) $api->smtp_password : '';
        $pass = $pass_raw;
        if ($pass_raw === '') {
            $lines[] = 'Password   : EMPTY — no password stored on the API config!';
        } else {
            $decrypted = $this->encryption->decrypt($pass_raw);
            if ($decrypted !== false) {
                $pass = $decrypted;
                $lines[] = 'Password   : decrypts OK with this app\'s encryption key (plaintext length ' . strlen($pass) . ')';
            } elseif (strlen($pass_raw) > 40) {
                $lines[] = 'Password   : ⚠ DOES NOT DECRYPT — the stored value (length ' . strlen($pass_raw) . ') looks encrypted but this app\'s encryption key cannot decrypt it. The raw ciphertext is being sent as the password, which guarantees "535 Incorrect authentication data". Fix: re-save the API password from an admin running with the SAME encryption key as this tenant.';
            } else {
                $lines[] = 'Password   : stored as plaintext (length ' . strlen($pass_raw) . '), used as-is';
            }
        }

        // ── Live AUTH probe (no email is sent) ──
        $transcript = [];
        $auth_ok = false;
        if ($host === '') {
            $lines[] = 'RESULT     : cannot probe — SMTP host is empty.';
        } else {
            $remote = (($port === 465 || $enc === 'ssl') ? 'ssl://' : '') . $host;
            $errno = 0;
            $errstr = '';
            $fp = @stream_socket_client($remote . ':' . $port, $errno, $errstr, 10, STREAM_CLIENT_CONNECT, stream_context_create([
                'ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true],
            ]));

            if (!$fp) {
                $lines[] = 'RESULT     : CONNECT FAILED — ' . $errstr . ' (errno ' . $errno . ')';
            } else {
                stream_set_timeout($fp, 10);
                $read = function () use ($fp) {
                    $out = '';
                    while (($line = fgets($fp, 515)) !== false) {
                        $out .= $line;
                        if (isset($line[3]) && $line[3] === ' ') {
                            break;
                        }
                    }
                    return trim($out);
                };
                $send = function ($cmd) use ($fp) {
                    fwrite($fp, $cmd . "\r\n");
                };

                $transcript[] = 'S: ' . $read();
                $ehlo_host = parse_url(site_url(), PHP_URL_HOST) ?: 'localhost';
                $send('EHLO ' . $ehlo_host);
                $transcript[] = 'C: EHLO ' . $ehlo_host;
                $ehlo = $read();
                $transcript[] = 'S: ' . $ehlo;

                if ($enc === 'tls' && stripos($ehlo, 'STARTTLS') !== false) {
                    $send('STARTTLS');
                    $transcript[] = 'C: STARTTLS';
                    $transcript[] = 'S: ' . $read();
                    if (@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                        $send('EHLO ' . $ehlo_host);
                        $transcript[] = 'C: EHLO ' . $ehlo_host . ' (after STARTTLS)';
                        $transcript[] = 'S: ' . $read();
                    } else {
                        $transcript[] = '!: STARTTLS negotiation failed';
                    }
                }

                $send('AUTH LOGIN');
                $transcript[] = 'C: AUTH LOGIN';
                $transcript[] = 'S: ' . $read();
                $send(base64_encode($user));
                $transcript[] = 'C: <username, base64>';
                $transcript[] = 'S: ' . $read();
                $send(base64_encode($pass));
                $transcript[] = 'C: <password, base64>';
                $auth_resp = $read();
                $transcript[] = 'S: ' . $auth_resp;

                $auth_ok = strpos($auth_resp, '235') === 0;
                $send('QUIT');
                fclose($fp);

                $lines[] = $auth_ok
                    ? 'RESULT     : ✔ AUTH SUCCESS (235) — these credentials are CORRECT. Sends should work now.'
                    : 'RESULT     : ✘ AUTH FAILED — server said: "' . $auth_resp . '". The username/password pair above is what the server is rejecting.';
            }
        }

        echo json_encode(['success' => true, 'auth_ok' => $auth_ok, 'lines' => $lines, 'transcript' => $transcript]);
    }

    /**
     * AJAX: live test fire. Actually calls ccx_fire_hook() with sample
     * data (your test email/mobile), then returns the log rows it produced.
     * NOTE: this sends real messages and uses balance.
     */
    public function debug_test_fire()
    {
        sms_wa_email_require_ajax('settings');

        // A slow SMTP conversation must not exceed PHP's execution limit —
        // that kills the request with no JSON reply and the UI looks stuck
        @set_time_limit(300);

        $hook_key    = $this->input->post('hook_key');
        $test_email  = $this->input->post('test_email');
        $test_mobile = $this->input->post('test_mobile');
        // This really sends, so resolve exactly which channels may fire.
        // "All" fans out over the staff member's allowed channels one at a
        // time rather than passing null, which would fire every channel.
        $only_channel = $this->input->post('channel');
        if ($only_channel === 'all' || $only_channel === '') {
            $fire_channels = array_keys(sms_wa_email_allowed_channels());
        } elseif (!sms_wa_email_can_channel($only_channel)) {
            ajax_access_denied();
        } else {
            $fire_channels = [$only_channel];
        }

        $hook_def = function_exists('ccx_get_hook_by_key') ? ccx_get_hook_by_key($hook_key) : null;
        if (!$hook_def) {
            echo json_encode(['success' => false, 'message' => 'Unknown hook key.']);
            return;
        }

        // Build sample values for every registered variable so the
        // template renders fully, then overlay the real test contacts
        $data = [];
        foreach ($hook_def['variables'] as $var) {
            $data[$var] = '[' . $var . ']';
        }
        $data['patient_name']  = 'Debug Test Patient';
        $data['mobile_number'] = $test_mobile;
        $data['patient_email'] = $test_email;
        $data['email']         = $test_email;
        $data['company']       = get_option('companyname');
        if (in_array('otp_code', $hook_def['variables'])) {
            $data['otp_code'] = '000000';
        }
        if (in_array('report_link', $hook_def['variables'])) {
            $data['report_link'] = site_url('patients/report/DEBUG-TEST');
        }

        // A real fire can hit slow SMTP/API endpoints (30s curl timeouts per
        // mapping) — don't let PHP's default limit 500 the request mid-send
        @set_time_limit(300);

        $fired_at = date('Y-m-d H:i:s', time() - 2);
        try {
            foreach ($fire_channels as $fire_channel) {
                ccx_fire_hook($hook_key, $data, $fire_channel);
            }
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => 'PHP error during fire: ' . $e->getMessage() . ' in ' . basename($e->getFile()) . ':' . $e->getLine(),
            ]);
            return;
        }

        // Return the log rows this fire just produced
        $logs = [];
        $logs_table = db_prefix() . 'ccx_hook_trigger_logs';
        if ($this->db->table_exists($logs_table)) {
            $logs = $this->db->where('hook_key', $hook_key)
                ->where('created_at >=', $fired_at)
                ->order_by('id', 'DESC')
                ->limit(20)
                ->get($logs_table)
                ->result_array();
        }

        echo json_encode(['success' => true, 'logs' => $logs]);
    }
}
