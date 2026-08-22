<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: SMS/WA/Email
Description: Module for SMS/WA/Email.
Version: 1.0.0
Requires at least: 2.3.*
*/

define('SMS_WA_EMAIL_MODULE_NAME', 'sms_wa_email');

// Invoice system hooks. Invoices are a CORE entity with no owning module, so the
// listeners that turn core invoice/payment events into Omni Messaging hooks live
// here. Required unconditionally — payments are recorded from the customer area,
// gateway callbacks and cron, none of which load module helpers.
require_once __DIR__ . '/helpers/sms_wa_email_invoices_helper.php';

// Default hook-wise EMAIL templates (seeds/hook_email_templates.php) — the
// "Load Hook Templates" button on the Email tab, and install.php, both call in
// here, so the functions must exist before any controller runs.
require_once __DIR__ . '/helpers/sms_wa_email_email_seeder_helper.php';

// CRM e-mail routing. Required unconditionally: core App_Email::send() asks for
// sms_wa_email_crm_email_dispatch() on every outgoing e-mail, and those come
// from cron, the customer area and gateway callbacks just as much as from an
// admin page — none of which load module helpers on demand.
require_once __DIR__ . '/helpers/sms_wa_email_crm_email_helper.php';

// Name the core mail template behind each routed CRM e-mail, for the send log.
hooks()->add_filter('before_email_template_send', 'sms_wa_email_crm_capture_template_source');

// Permissions register (and the legacy grant migrates) before the sidebar is
// built, so the menu check sees the final capability set.
hooks()->add_action('admin_init', 'sms_wa_email_module_permissions');
hooks()->add_action('admin_init', 'sms_wa_email_module_init_menu_items');
hooks()->add_action('after_cron_run', 'sms_wa_email_cron_purge_old_hook_logs');
hooks()->add_action('after_cron_run', 'sms_wa_email_process_campaigns');
hooks()->add_action('after_cron_run', 'sms_wa_email_process_auto_schedulers');
hooks()->add_action('admin_init', 'sms_wa_email_realtime_campaign_check');
hooks()->add_action('admin_init', 'sms_wa_email_realtime_auto_scheduler_check');

/* ── Invoices: one hook per e-mail template in the Invoices section of
      Setup → Email Templates. See helpers/sms_wa_email_invoices_helper.php
      and application/helpers/ccx_hooks/invoices_hooks.php. ── */

// Read-only snapshot of $invoice->sent, so invoice_sent below can tell a first
// send from a re-send exactly as core tells the two e-mail templates apart.
hooks()->add_filter('invoice_object_before_send_to_client', 'sms_wa_email_invoice_snapshot_sent');

hooks()->add_action('invoice_sent', 'sms_wa_email_invoice_on_sent');
hooks()->add_action('after_payment_added', 'sms_wa_email_invoice_on_payment_added');
hooks()->add_action('invoice_overdue_reminder_sent', 'sms_wa_email_invoice_on_overdue_reminder');
hooks()->add_action('invoice_due_reminder_sent', 'sms_wa_email_invoice_on_due_reminder');

// Override Perfex cron throttle from 300s (5min) to 60s (1min)
hooks()->add_filter('cron_functions_execute_seconds', function () {
    return 60;
});

/**
 * Replace {tag} placeholders in a short string (an e-mail subject, a sender
 * name) from a hook/campaign data array. Same substitution rule the message
 * body goes through, so a subject like "Invoice {invoice_number}" resolves.
 *
 * @param  string $text
 * @param  array  $data  tag => value
 * @return string
 */
function sms_wa_email_render_tags($text, $data)
{
    $text = (string) $text;

    if ($text === '' || empty($data)) {
        return $text;
    }

    foreach ($data as $key => $value) {
        if (!is_string($value) && !is_numeric($value)) {
            continue;
        }
        $text = str_replace('{' . $key . '}', $value, $text);
    }

    return $text;
}

/**
 * Cron job: delete hook trigger logs older than 48 hours
 */
function sms_wa_email_cron_purge_old_hook_logs()
{
    $CI = &get_instance();
    $table = db_prefix() . 'ccx_hook_trigger_logs';
    if ($CI->db->table_exists($table)) {
        $CI->db->where('created_at <', date('Y-m-d H:i:s', strtotime('-48 hours')));
        $CI->db->delete($table);
    }
}

/**
 * Real-time campaign check: runs on every admin page load.
 * Uses a lightweight 30-second lock to avoid hammering the DB.
 * If a pending campaign is due, it immediately triggers processing.
 */
function sms_wa_email_realtime_campaign_check()
{
    $CI = &get_instance();

    // Lightweight lock: only check once every 30 seconds per app instance
    $last_check = get_option('sms_wa_email_last_campaign_check');
    $now = time();
    if ($last_check !== '' && ($now - (int)$last_check) < 30) {
        return;
    }
    update_option('sms_wa_email_last_campaign_check', $now);

    // Quick check: any pending campaigns due right now?
    if (!$CI->db->table_exists(db_prefix() . 'sms_wa_email_campaigns')) {
        return;
    }

    $due_count = $CI->db
        ->where_in('status', ['pending', 'processing'])
        ->where('schedule_date <=', date('Y-m-d H:i:s'))
        ->count_all_results(db_prefix() . 'sms_wa_email_campaigns');

    if ($due_count > 0) {
        sms_wa_email_process_campaigns();
    }
}

/**
 * Cron job: Process pending campaigns
 */
function sms_wa_email_process_campaigns()
{
    $CI = &get_instance();
    
    // Check if table exists
    if (!$CI->db->table_exists(db_prefix() . 'sms_wa_email_campaigns')) {
        return;
    }

    $CI->load->model('sms_wa_email/campaigns_model');
    $CI->load->model('clients_model');
    $CI->load->helper('ccx_hooks_registry');

    // Get campaigns due to run today
    $now = date('Y-m-d H:i:s');
    
    // We select pending campaigns whose schedule_date has passed, and campaigns currently processing
    $CI->db->where_in('status', ['pending', 'processing']);
    $CI->db->where('schedule_date <=', $now);
    $campaigns = $CI->db->get(db_prefix() . 'sms_wa_email_campaigns')->result_array();

    foreach ($campaigns as $campaign) {
        $c_id = $campaign['id'];
        
        // Mark as processing if pending
        if ($campaign['status'] == 'pending') {
            $CI->db->where('id', $c_id)->update(db_prefix() . 'sms_wa_email_campaigns', ['status' => 'processing']);
        }

        $filters = json_decode($campaign['filters_json'], true);
        if(!is_array($filters)) $filters = [];
        
        $target_patients = $CI->campaigns_model->get_filtered_patients($filters);

        // Fetch the template details 
        $template = $CI->db->where('id', $campaign['template_id'])->get(db_prefix() . 'sms_wa_email_templates')->row();
        if (!$template) {
            $CI->db->where('id', $c_id)->update(db_prefix() . 'sms_wa_email_campaigns', [
                'status' => 'failed'
            ]);
            continue;
        }

        $channel = $campaign['channel'];
        // Normalize channel for API lookup: official_whatsapp -> whatsapp
        $api_channel = $channel;
        if ($api_channel === 'official_whatsapp') {
            $api_channel = 'whatsapp';
        }
        $subtype = (!empty($template->message_subtype) && $template->message_subtype === 'promotional') ? 'promo' : 'trans';
        $api_subtype = (!empty($template->message_subtype)) ? $template->message_subtype : 'transactional';
        
        // WhatsApp goes out over the tenant's own connected Cloud API number
        // when the WhatsApp module is live — no gateway API row is involved,
        // and the campaign is billed per 24-hour conversation, not per message.
        // The gateway API is still resolved as a fallback for recipients the
        // Cloud API cannot legally reach (closed window, no approved template).
        $wa_bridge = _ccx_whatsapp_bridge_active($channel);

        // Require API config upfront (unless the Cloud API can carry the send)
        $api = _ccx_get_default_api($api_channel, $api_subtype);
        if (!$api && !$wa_bridge) {
            $CI->db->where('id', $c_id)->update(db_prefix() . 'sms_wa_email_campaigns', [
                'status' => 'failed'
            ]);
            continue;
        }

        $processed = 0;
        $success = 0;
        $failed = 0;

        foreach ($target_patients as $patient_id) {
            // Check if patient was already processed in a previous cron batch for THIS campaign
            $log_exists = $CI->db->where('campaign_id', $c_id)->where('patient_id', $patient_id)->get(db_prefix() . 'sms_wa_email_campaign_logs')->row();
            if ($log_exists) {
                continue; // Already processed
            }

            // Important: we process batches of 50 per cron loop to prevent timeout
            if ($processed >= 50) {
                break; 
            }

            // Re-fetch client data
            $client = $CI->clients_model->get($patient_id);
            if (!$client) {
                $failed++; $processed++;
                $CI->db->insert(db_prefix() . 'sms_wa_email_campaign_logs', [
                    'campaign_id' => $c_id, 'patient_id' => $patient_id, 'status' => 'failed', 'error_message' => 'Patient deleted', 'created_at' => date('Y-m-d H:i:s')
                ]);
                continue;
            }

            // Determine Recipient
            $recipient = ($channel === 'email') ? '' : $client->phonenumber;
            
            if ($channel === 'email') {
                $contacts = $CI->clients_model->get_contacts($patient_id, ['is_primary' => 1]);
                if (!empty($contacts) && isset($contacts[0]['email'])) {
                    $recipient = $contacts[0]['email'];
                }
            }

            if (empty($recipient)) {
                $failed++; $processed++;
                $CI->db->insert(db_prefix() . 'sms_wa_email_campaign_logs', [
                    'campaign_id' => $c_id, 'patient_id' => $patient_id, 'status' => 'failed', 'error_message' => 'Valid contact missing', 'created_at' => date('Y-m-d H:i:s')
                ]);
                continue;
            }

            // Check Channel Balance dynamically for EACH request. The Cloud API
            // bridge runs its own conversation-aware gate instead — a recipient
            // already inside an open 24-hour window costs nothing and must not
            // be blocked by a zero per-message balance.
            if (!$wa_bridge) {
                $balance_check = _ccx_check_channel_balance($api_channel, $subtype);
                if ($balance_check !== true) {
                     $failed++; $processed++;
                     $CI->db->insert(db_prefix() . 'sms_wa_email_campaign_logs', [
                        'campaign_id' => $c_id, 'patient_id' => $patient_id, 'recipient' => $recipient, 'status' => 'failed', 'error_message' => $balance_check['error'], 'created_at' => date('Y-m-d H:i:s')
                     ]);
                     continue;
                }
            }

            // Prepare Data for template replacement
            $data = [
                'patient_name' => $client->company,
                'mobile_number' => $client->phonenumber,
                'patient_email' => ($channel === 'email' ? $recipient : ''),
                'email' => ($channel === 'email' ? $recipient : ''),
                'company' => get_option('companyname'),
                'companyname' => get_option('companyname')
            ];

            $message = $template->content;
            foreach ($data as $key => $value) {
                $message = str_replace('{' . $key . '}', $value, $message);
            }

            $api_status = 'failed';
            $api_error  = null;
            $wa_handled = false;

            if ($wa_bridge) {
                // ── WhatsApp Cloud API flow (own number, conversation billing) ──
                $wa_result = whatsapp_omni_dispatch([
                    'to'       => $recipient,
                    'message'  => $message,
                    'template' => $template,
                    'subtype'  => $subtype,
                    'data'     => $data,
                    'source'   => 'campaign',
                ]);

                // Nothing the Cloud API can deliver to this recipient (closed
                // 24-hour window, no approved template) → let the gateway API
                // take it, when one is configured.
                $wa_handled = !($wa_result['status'] === 'no_template' && $api);

                if ($wa_handled) {
                    $api_status = ($wa_result['status'] === 'success') ? 'success' : 'failed';
                    if ($api_status !== 'success') {
                        $api_error = mb_substr($wa_result['response_body'], 0, 500);
                    }
                } else {
                    // The gateway bills per message, so re-apply the standard gate.
                    $balance_check = _ccx_check_channel_balance($api_channel, $subtype);
                    if ($balance_check !== true) {
                        $failed++; $processed++;
                        $CI->db->insert(db_prefix() . 'sms_wa_email_campaign_logs', [
                            'campaign_id' => $c_id, 'patient_id' => $patient_id, 'recipient' => $recipient, 'status' => 'failed', 'error_message' => $balance_check['error'], 'created_at' => date('Y-m-d H:i:s')
                        ]);
                        continue;
                    }
                }
            }

            if (!$wa_handled) {
                // ── Standard API channel flow ──
                $message_parts = array_map('trim', explode(',', $message));
                $message_with_quotes = implode(',', array_map(function($p) { return '"' . $p . '"'; }, $message_parts));

                $payload = [
                    'to' => $recipient,
                    'message' => $message,
                    'message_with_quotes' => $message_with_quotes,
                    // The e-mail template's own subject / sender name, tags
                    // resolved — same contract as ccx_fire_hook()
                    'subject' => sms_wa_email_render_tags(isset($template->subject) ? $template->subject : '', $data),
                    'from_name' => sms_wa_email_render_tags(isset($template->from_name) ? $template->from_name : '', $data),
                    'msg_template_id' => isset($template->msg_template_id) ? $template->msg_template_id : '',
                    'header_id' => isset($template->header_id) ? $template->header_id : '',
                    // Common SMS gateway field aliases
                    'numbers' => $recipient,
                    'number'  => $recipient,
                    'all_recipients' => $recipient,
                    'recipient_name' => $client->company,
                    // AI Call Agent template fields
                    'voice_type'      => isset($template->voice_type) ? $template->voice_type : '',
                    'tts_text'        => isset($template->content) && isset($template->voice_type) && $template->voice_type === 'tts' ? $template->content : '',
                    'voice_note_id'   => isset($template->voice_note_id) ? $template->voice_note_id : '',
                    'voice_note_file' => isset($template->voice_note_file) ? $template->voice_note_file : '',
                    'voice_type_id'   => isset($template->voice_type_id) ? $template->voice_type_id : '',
                    'retry_count'     => isset($template->retry_count) ? $template->retry_count : '0',
                    'retry_interval'  => isset($template->retry_interval) ? $template->retry_interval : '0',
                    'language'        => isset($template->language) ? $template->language : '',
                ];
                $payload = array_merge($payload, $data);

                // Execute API Call — MUST clone $api each iteration because
                // _ccx_execute_api_call() mutates the object's url/body/headers in-place
                $api_clone = clone $api;
                $result = _ccx_execute_api_call($api_clone, $payload);

                $api_status = ($result['status'] === 'success') ? 'success' : 'failed';
                if ($result['status'] !== 'success') {
                    $api_error = 'API ' . $result['status'] . ': HTTP ' . $result['response_code'];
                    if (!empty($result['response_body'])) {
                        $api_error .= ' | ' . mb_substr($result['response_body'], 0, 500);
                    }
                } else {
                    _ccx_decrement_channel_balance($api_channel, $subtype);

                    if (function_exists('_ccx_log_to_master_api_logs')) {
                        _ccx_log_to_master_api_logs($api, $result, $recipient, $campaign['created_by']);
                    }
                }
            }

            if ($api_status === 'success') $success++; else $failed++;
            
            $processed++;
            $CI->db->insert(db_prefix() . 'sms_wa_email_campaign_logs', [
                'campaign_id' => $c_id, 
                'patient_id' => $patient_id, 
                'recipient' => $recipient, 
                'status' => $api_status, 
                'error_message' => $api_error,
                'created_at' => date('Y-m-d H:i:s')
            ]);

            // Also log to unified channel logs
            _ccx_log_hook_trigger('campaign_send', $channel, $template->id, $recipient, mb_substr($message, 0, 500), $api_status, $api_error, $campaign['created_by'], 'campaign');
        }

        // Update campaign totals
        $new_processed = $campaign['processed_count'] + $processed;
        $new_success = $campaign['success_count'] + $success;
        $new_failed = $campaign['failed_count'] + $failed;

        $update_data = [
            'processed_count' => $new_processed,
            'success_count' => $new_success,
            'failed_count' => $new_failed
        ];

        // Is it completely done?
        if ($new_processed >= count($target_patients) || $target_patients == 0) {
            $update_data['status'] = 'completed';
        }

        $CI->db->where('id', $c_id)->update(db_prefix() . 'sms_wa_email_campaigns', $update_data);
    }
}

/**
 * Real-time auto-scheduler check: runs on every admin page load.
 * Uses a lightweight 30-second lock.
 */
function sms_wa_email_realtime_auto_scheduler_check()
{
    $CI = &get_instance();

    $last_check = get_option('sms_wa_email_last_auto_sched_check');
    $now = time();
    if ($last_check !== '' && ($now - (int)$last_check) < 15) {
        return;
    }
    update_option('sms_wa_email_last_auto_sched_check', $now);

    if (!$CI->db->table_exists(db_prefix() . 'sms_wa_email_auto_schedulers')) {
        return;
    }

    $active_count = $CI->db->where('is_active', 1)->count_all_results(db_prefix() . 'sms_wa_email_auto_schedulers');
    if ($active_count > 0) {
        sms_wa_email_process_auto_schedulers();
    }
}

/**
 * Process active auto-schedulers.
 * For each scheduler, checks if the current time matches any configured time slot
 * (within ±5 minutes). If YES, sends messages to patients who haven't been
 * messaged TODAY by this scheduler (daily deduplication).
 */
function sms_wa_email_process_auto_schedulers()
{
    $CI = &get_instance();

    if (!$CI->db->table_exists(db_prefix() . 'sms_wa_email_auto_schedulers')) {
        return;
    }

    // Ensure correct timezone is set (critical for cron runs outside controller)
    $app_tz = get_option('default_timezone');
    if (!empty($app_tz)) {
        date_default_timezone_set($app_tz);
    }

    $CI->load->model('sms_wa_email/campaigns_model');
    $CI->load->model('sms_wa_email/auto_schedulers_model');
    $CI->load->model('clients_model');
    $CI->load->helper('ccx_hooks_registry');

    $now_ts   = time();
    $today    = date('Y-m-d');
    $now_mins = (int)date('G') * 60 + (int)date('i'); // minutes since midnight

    // Get all active schedulers
    $schedulers = $CI->db->where('is_active', 1)->get(db_prefix() . 'sms_wa_email_auto_schedulers')->result_array();

    foreach ($schedulers as $sched) {
        $sched_id = $sched['id'];

        // Reset daily counter if new day
        if ($sched['last_run_date'] !== $today) {
            $CI->db->where('id', $sched_id)->update(db_prefix() . 'sms_wa_email_auto_schedulers', [
                'total_sent_today' => 0,
                'last_run_date'    => $today
            ]);
            $sched['total_sent_today'] = 0;
        }

        // Parse time slots
        $time_slots = json_decode($sched['time_slots'], true);
        if (!is_array($time_slots) || empty($time_slots)) {
            continue;
        }

        // Find ALL slots that should have fired: slot time is in the past but within 30 minutes
        // Process the first one that HASN'T already run today
        $current_slot = null;
        foreach ($time_slots as $slot) {
            $parts = explode(':', $slot);
            if (count($parts) < 2) continue;
            $slot_mins = (int)$parts[0] * 60 + (int)$parts[1];
            $diff = $now_mins - $slot_mins;
            // Slot is eligible if current time is 0–30 minutes after the slot time
            if ($diff >= 0 && $diff <= 30) {
                // Check if this specific slot already ran today
                $slot_ran = $CI->db
                    ->where('scheduler_id', $sched_id)
                    ->where('run_date', $today)
                    ->where('time_slot', $slot)
                    ->count_all_results(db_prefix() . 'sms_wa_email_auto_scheduler_logs');
                if ($slot_ran == 0) {
                    $current_slot = $slot;
                    break; // Found an unprocessed slot — use this one
                }
                // Otherwise, skip this already-processed slot and check the next one
            }
        }

        if ($current_slot === null) {
            continue; // No unprocessed slots to fire right now
        }

        // Get filtered patients
        $filters = json_decode($sched['filters_json'], true);
        if (!is_array($filters)) $filters = [];
        $target_patients = $CI->campaigns_model->get_filtered_patients($filters);

        if (empty($target_patients)) {
            continue;
        }

        // Get patients already sent to TODAY (across ALL slots)
        $already_sent = $CI->auto_schedulers_model->get_todays_sent_patient_ids($sched_id);

        // Fetch template
        $template = $CI->db->where('id', $sched['template_id'])->get(db_prefix() . 'sms_wa_email_templates')->row();
        if (!$template) {
            continue;
        }

        // Resolve channel & API
        $channel = $sched['channel'];
        $api_channel = ($channel === 'official_whatsapp') ? 'whatsapp' : $channel;
        $subtype = (!empty($template->message_subtype) && $template->message_subtype === 'promotional') ? 'promo' : 'trans';
        $api_subtype = (!empty($template->message_subtype)) ? $template->message_subtype : 'transactional';

        // WhatsApp rides the tenant's own Cloud API number when the WhatsApp
        // module is connected — no gateway API row, conversation billing.
        $wa_bridge = _ccx_whatsapp_bridge_active($channel);

        // Require API config upfront (unless the Cloud API can carry the send)
        $api = _ccx_get_default_api($api_channel, $api_subtype);
        if (!$api && !$wa_bridge) {
            continue;
        }

        $processed = 0;
        $success   = 0;
        $failed    = 0;

        foreach ($target_patients as $patient_id) {
            // Skip if already sent today
            if (in_array($patient_id, $already_sent)) {
                continue;
            }

            // Batch limit
            if ($processed >= 50) {
                break;
            }

            // Get patient data
            $client = $CI->clients_model->get($patient_id);
            if (!$client) {
                $failed++;
                $processed++;
                $CI->db->insert(db_prefix() . 'sms_wa_email_auto_scheduler_logs', [
                    'scheduler_id'  => $sched_id,
                    'patient_id'    => $patient_id,
                    'time_slot'     => $current_slot,
                    'run_date'      => $today,
                    'status'        => 'failed',
                    'error_message' => 'Patient deleted',
                    'created_at'    => date('Y-m-d H:i:s')
                ]);
                continue;
            }

            // Determine recipient
            $recipient = ($channel === 'email') ? '' : $client->phonenumber;
            if ($channel === 'email') {
                $contacts = $CI->clients_model->get_contacts($patient_id, ['is_primary' => 1]);
                if (!empty($contacts) && isset($contacts[0]['email'])) {
                    $recipient = $contacts[0]['email'];
                }
            }

            if (empty($recipient)) {
                $failed++;
                $processed++;
                $CI->db->insert(db_prefix() . 'sms_wa_email_auto_scheduler_logs', [
                    'scheduler_id'  => $sched_id,
                    'patient_id'    => $patient_id,
                    'time_slot'     => $current_slot,
                    'run_date'      => $today,
                    'status'        => 'failed',
                    'error_message' => 'Valid contact missing',
                    'created_at'    => date('Y-m-d H:i:s')
                ]);
                continue;
            }

            // Check channel balance (the Cloud API bridge gates per conversation)
            $balance_check = $wa_bridge ? true : _ccx_check_channel_balance($api_channel, $subtype);
            if ($balance_check !== true) {
                $failed++;
                $processed++;
                $CI->db->insert(db_prefix() . 'sms_wa_email_auto_scheduler_logs', [
                    'scheduler_id'  => $sched_id,
                    'patient_id'    => $patient_id,
                    'recipient'     => $recipient,
                    'time_slot'     => $current_slot,
                    'run_date'      => $today,
                    'status'        => 'failed',
                    'error_message' => $balance_check['error'],
                    'created_at'    => date('Y-m-d H:i:s')
                ]);
                continue;
            }

            // Prepare template data
            $tpl_data = [
                'patient_name'  => $client->company,
                'mobile_number' => $client->phonenumber,
                'patient_email' => ($channel === 'email' ? $recipient : ''),
                'email'         => ($channel === 'email' ? $recipient : ''),
                'company'       => get_option('companyname'),
                'companyname'   => get_option('companyname')
            ];

            $message = $template->content;
            foreach ($tpl_data as $key => $value) {
                $message = str_replace('{' . $key . '}', $value, $message);
            }


            $api_status = 'failed';
            $api_error  = null;
            $wa_handled = false;

            if ($wa_bridge) {
                // ── WhatsApp Cloud API flow (own number, conversation billing) ──
                $wa_result = whatsapp_omni_dispatch([
                    'to'       => $recipient,
                    'message'  => $message,
                    'template' => $template,
                    'subtype'  => $subtype,
                    'data'     => $tpl_data,
                    'source'   => 'auto_scheduler',
                ]);

                // Closed 24-hour window with no approved template mapped → hand
                // the send to the gateway API when one is configured.
                $wa_handled = !($wa_result['status'] === 'no_template' && $api);

                if ($wa_handled) {
                    $api_status = ($wa_result['status'] === 'success') ? 'success' : 'failed';
                    if ($api_status !== 'success') {
                        $api_error = mb_substr($wa_result['response_body'], 0, 500);
                    }
                } else {
                    // The gateway bills per message, so re-apply the standard gate.
                    $gateway_balance = _ccx_check_channel_balance($api_channel, $subtype);
                    if ($gateway_balance !== true) {
                        $failed++;
                        $processed++;
                        $CI->db->insert(db_prefix() . 'sms_wa_email_auto_scheduler_logs', [
                            'scheduler_id'  => $sched_id,
                            'patient_id'    => $patient_id,
                            'recipient'     => $recipient,
                            'time_slot'     => $current_slot,
                            'run_date'      => $today,
                            'status'        => 'failed',
                            'error_message' => $gateway_balance['error'],
                            'created_at'    => date('Y-m-d H:i:s')
                        ]);
                        continue;
                    }
                }
            }

            if (!$wa_handled) {
                // ── Standard API channel flow ──
                $message_parts = array_map('trim', explode(',', $message));
                $message_with_quotes = implode(',', array_map(function ($p) {
                    return '"' . $p . '"';
                }, $message_parts));

                $payload = [
                    'to'                 => $recipient,
                    'message'            => $message,
                    'message_with_quotes'=> $message_with_quotes,
                    // The e-mail template's own subject / sender name, tags
                    // resolved — same contract as ccx_fire_hook()
                    'subject'            => sms_wa_email_render_tags(isset($template->subject) ? $template->subject : '', $tpl_data),
                    'from_name'          => sms_wa_email_render_tags(isset($template->from_name) ? $template->from_name : '', $tpl_data),
                    'msg_template_id'    => isset($template->msg_template_id) ? $template->msg_template_id : '',
                    'header_id'          => isset($template->header_id) ? $template->header_id : '',
                    // Common SMS gateway field aliases
                    'numbers'            => $recipient,
                    'number'             => $recipient,
                    'all_recipients'     => $recipient,
                    'recipient_name'     => $client->company,
                    // AI Call Agent template fields
                    'voice_type'         => isset($template->voice_type) ? $template->voice_type : '',
                    'tts_text'           => isset($template->content) && isset($template->voice_type) && $template->voice_type === 'tts' ? $template->content : '',
                    'voice_note_id'      => isset($template->voice_note_id) ? $template->voice_note_id : '',
                    'voice_note_file'    => isset($template->voice_note_file) ? $template->voice_note_file : '',
                    'voice_type_id'      => isset($template->voice_type_id) ? $template->voice_type_id : '',
                    'retry_count'        => isset($template->retry_count) ? $template->retry_count : '0',
                    'retry_interval'     => isset($template->retry_interval) ? $template->retry_interval : '0',
                    'language'           => isset($template->language) ? $template->language : '',
                ];
                $payload = array_merge($payload, $tpl_data);

                // Execute API call
                $api_clone = clone $api;
                $result = _ccx_execute_api_call($api_clone, $payload);

                $api_status = ($result['status'] === 'success') ? 'success' : 'failed';
                if ($result['status'] !== 'success') {
                    $api_error = 'API ' . $result['status'] . ': HTTP ' . $result['response_code'];
                    if (!empty($result['response_body'])) {
                        $api_error .= ' | ' . mb_substr($result['response_body'], 0, 500);
                    }
                } else {
                    _ccx_decrement_channel_balance($api_channel, $subtype);
                    if (function_exists('_ccx_log_to_master_api_logs')) {
                        _ccx_log_to_master_api_logs($api, $result, $recipient, $sched['created_by']);
                    }
                }
            }

            if ($api_status === 'success') $success++; else $failed++;
            $processed++;

            $CI->db->insert(db_prefix() . 'sms_wa_email_auto_scheduler_logs', [
                'scheduler_id'  => $sched_id,
                'patient_id'    => $patient_id,
                'recipient'     => $recipient,
                'time_slot'     => $current_slot,
                'run_date'      => $today,
                'status'        => $api_status,
                'error_message' => $api_error,
                'created_at'    => date('Y-m-d H:i:s')
            ]);

            // Also log to unified channel logs
            _ccx_log_hook_trigger('auto_scheduler_send', $channel, $sched['template_id'], $recipient, mb_substr($message, 0, 500), $api_status, $api_error, $sched['created_by'], 'auto_scheduler');
        }

        // Update scheduler counters
        $new_sent = $sched['total_sent_today'] + $processed;
        $CI->db->where('id', $sched_id)->update(db_prefix() . 'sms_wa_email_auto_schedulers', [
            'total_sent_today' => $new_sent,
            'last_run_at'      => date('Y-m-d H:i:s'),
            'last_run_date'    => $today
        ]);
    }
}

/* ═══════════════════════════════════════════════════════════════════
   ACCESS CONTROL

   Omni Messaging is gated on two independent axes so a staff member can
   be given, say, "SMS + WhatsApp, templates only" without ever reaching
   Email, the campaign engine or the diagnostics:

     sms_wa_email           — WHAT they may do   (feature-wise)
     sms_wa_email_channels  — WHERE they may do it (channel-wise)

   Both must pass. Admins bypass everything (staff_can() short-circuits).
   ═══════════════════════════════════════════════════════════════════ */

/**
 * Every channel this module sends on.
 * Key = canonical channel id, which doubles as the staff capability name.
 */
function sms_wa_email_channel_map()
{
    return [
        'sms'           => 'SMS',
        'whatsapp'      => 'Official WhatsApp',
        'email'         => 'Email',
        'ai_call_agent' => 'AI Call Agent',
    ];
}

/**
 * Feature capabilities — what a staff member may DO, independent of the
 * channels they may do it on.
 */
function sms_wa_email_feature_map()
{
    return [
        'view'            => 'Access Omni Messaging',
        'templates'       => 'Create / edit / delete templates',
        'hooks'           => 'Configure system hooks (automation)',
        'logs'            => 'View & clear send logs',
        'campaigns'       => 'Campaigns (mass sends)',
        'auto_schedulers' => 'Auto Schedulers (recurring sends)',
        'settings'        => 'Advanced settings, limits & diagnostics',
    ];
}

/**
 * Channel ids are not spelled the same everywhere — the UI tab and the
 * campaign/scheduler rows use "official_whatsapp", templates use
 * "whatsapp", allocations use "aicall". Fold them onto the canonical id.
 */
function sms_wa_email_normalize_channel($channel)
{
    switch ((string) $channel) {
        case 'official_whatsapp':
        case 'wa':
            return 'whatsapp';
        case 'aicall':
        case 'ai_call':
            return 'ai_call_agent';
        default:
            return (string) $channel;
    }
}

/**
 * Feature-wise check, e.g. sms_wa_email_can('campaigns').
 */
function sms_wa_email_can($capability)
{
    return has_permission('sms_wa_email', '', $capability);
}

/**
 * Channel-wise check, e.g. sms_wa_email_can_channel('official_whatsapp').
 */
function sms_wa_email_can_channel($channel)
{
    $channel = sms_wa_email_normalize_channel($channel);

    if (!array_key_exists($channel, sms_wa_email_channel_map())) {
        return false;
    }

    return has_permission('sms_wa_email_channels', '', $channel);
}

/**
 * Canonical ids => labels for the channels this staff member may use.
 */
function sms_wa_email_allowed_channels()
{
    $allowed = [];
    foreach (sms_wa_email_channel_map() as $key => $label) {
        if (sms_wa_email_can_channel($key)) {
            $allowed[$key] = $label;
        }
    }

    return $allowed;
}

/**
 * True when the staff member may open the module at all — the `view`
 * capability AND at least one channel. Someone with `view` but no channel
 * would only ever see an empty page, so the module stays hidden instead.
 */
function sms_wa_email_can_access()
{
    return sms_wa_email_can('view') && !empty(sms_wa_email_allowed_channels());
}

/**
 * Guard for a whole page: feature capability + (optionally) one channel.
 * Redirects to the access-denied screen when the check fails.
 */
function sms_wa_email_require($capability, $channel = null)
{
    $ok = sms_wa_email_can_access() && sms_wa_email_can($capability);

    if ($ok && $channel !== null) {
        $ok = sms_wa_email_can_channel($channel);
    }

    if (!$ok) {
        access_denied('sms_wa_email');
    }
}

/**
 * Same rule as sms_wa_email_require(), for AJAX endpoints.
 */
function sms_wa_email_require_ajax($capability, $channel = null)
{
    $ok = sms_wa_email_can_access() && sms_wa_email_can($capability);

    if ($ok && $channel !== null) {
        $ok = sms_wa_email_can_channel($channel);
    }

    if (!$ok) {
        ajax_access_denied();
    }
}

/**
 * One-time upgrade: Omni Messaging used to expose a single
 * `sms_wa_email / view` permission. Anyone who already had it is granted the
 * full new capability set, so this upgrade never silently strips working
 * staff of the module — the administrator narrows it down afterwards.
 *
 * Runs on admin_init (guarded by an option) so it self-heals on installs
 * where every table already exists and install.php is no longer re-entered.
 */
function sms_wa_email_migrate_legacy_permissions()
{
    if (get_option('sms_wa_email_permissions_migrated') === '1') {
        return;
    }

    $CI = &get_instance();

    $grant = [
        'sms_wa_email'          => array_keys(sms_wa_email_feature_map()),
        'sms_wa_email_channels' => array_keys(sms_wa_email_channel_map()),
    ];

    // Roles keep their permissions serialized on tblroles
    if ($CI->db->table_exists(db_prefix() . 'roles')) {
        foreach ($CI->db->get(db_prefix() . 'roles')->result_array() as $role) {
            $perms = @unserialize($role['permissions']);
            if (!is_array($perms)
                || empty($perms['sms_wa_email'])
                || !in_array('view', (array) $perms['sms_wa_email'], true)) {
                continue;
            }

            foreach ($grant as $feature => $caps) {
                $existing        = isset($perms[$feature]) ? (array) $perms[$feature] : [];
                $perms[$feature] = array_values(array_unique(array_merge($existing, $caps)));
            }

            $CI->db->where('roleid', $role['roleid'])
                ->update(db_prefix() . 'roles', ['permissions' => serialize($perms)]);
        }
    }

    // Staff members keep one row per feature + capability
    if ($CI->db->table_exists(db_prefix() . 'staff_permissions')) {
        $granted = $CI->db->select('staff_id')
            ->where('feature', 'sms_wa_email')
            ->where('capability', 'view')
            ->get(db_prefix() . 'staff_permissions')
            ->result_array();

        foreach ($granted as $row) {
            foreach ($grant as $feature => $caps) {
                foreach ($caps as $cap) {
                    $exists = $CI->db->where('staff_id', $row['staff_id'])
                        ->where('feature', $feature)
                        ->where('capability', $cap)
                        ->count_all_results(db_prefix() . 'staff_permissions');

                    if (!$exists) {
                        $CI->db->insert(db_prefix() . 'staff_permissions', [
                            'staff_id'   => $row['staff_id'],
                            'feature'    => $feature,
                            'capability' => $cap,
                        ]);
                    }
                }
            }
        }
    }

    update_option('sms_wa_email_permissions_migrated', '1');
}

/**
 * Register both permission features on the Roles / Staff permission screens.
 */
function sms_wa_email_module_permissions()
{
    sms_wa_email_migrate_legacy_permissions();

    $feature_caps = [];
    foreach (sms_wa_email_feature_map() as $cap => $label) {
        $feature_caps[$cap] = $label;
    }
    // Keep the core wording on `view` so it reads like every other module
    $feature_caps['view'] = _l('permission_view') . '(' . _l('permission_global') . ')';

    register_staff_capabilities(
        SMS_WA_EMAIL_MODULE_NAME,
        ['capabilities' => $feature_caps],
        'Omni Messaging'
    );

    register_staff_capabilities(
        'sms_wa_email_channels',
        ['capabilities' => sms_wa_email_channel_map()],
        'Omni Messaging — Channels'
    );
}

/**
 * Register activation module hook
 */
register_activation_hook(SMS_WA_EMAIL_MODULE_NAME, 'sms_wa_email_module_activation_hook');

function sms_wa_email_module_activation_hook()
{
    $CI = &get_instance();
    require_once(__DIR__ . '/install.php');
}

/**
 * Register language files, must be registered if the module is using languages
 */
register_language_files(SMS_WA_EMAIL_MODULE_NAME, [SMS_WA_EMAIL_MODULE_NAME]);

/**
 * Init sms_wa_email module menu items in setup in admin_init hook
 * @return null
 */
function sms_wa_email_module_init_menu_items()
{
    $CI = &get_instance();

    if (sms_wa_email_can_access()) {
        $CI->app_menu->add_sidebar_menu_item('sms_wa_email', [
            'name' => 'Omni Messaging',
            'href' => admin_url('sms_wa_email'),
            'icon' => 'fa fa-paper-plane',
            'position' => 15,
        ]);
    }
}
