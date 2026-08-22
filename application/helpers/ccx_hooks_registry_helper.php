<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * ═══════════════════════════════════════════════════════════════
 *  CCX Hooks Registry — Centralized System Hook Engine
 * ═══════════════════════════════════════════════════════════════
 *
 * Hook DEFINITIONS live in per-module files under:
 *   application/helpers/ccx_hooks/{module}_hooks.php
 *
 * This file provides:
 *   - ccx_get_registered_hooks()  — auto-loads all definitions
 *   - ccx_fire_hook()             — fires a hook by key
 *   - Balance, API, logging helpers
 *
 * To add hooks for a new module, create a new file:
 *   ccx_hooks/{module}_hooks.php   (returns an array)
 *
 * Usage:
 *   $hooks = ccx_get_registered_hooks();
 *
 *   ccx_fire_hook('visit_created', [
 *       'patient_name'  => 'John Doe',
 *       'mobile_number' => '9876543210',
 *   ]);
 */

/**
 * Returns all registered system hooks.
 *
 * Hook definitions are auto-loaded from per-module files in:
 *   application/helpers/ccx_hooks/{module}_hooks.php
 *
 * Each file must return an array of hook entries. To add hooks
 * for a new module, simply create a new file in that directory.
 *
 * Each hook entry must contain:
 *   - hook_key    (string)  Unique identifier, snake_case
 *   - label       (string)  Human-readable name shown in UI
 *   - module      (string)  Module slug that owns this hook
 *   - description (string)  Explains when this hook fires
 *   - variables   (array)   Placeholder variables available for templates
 *
 * Optionally:
 *   - requires_module (string)  Module slug that must be ACTIVATED for this
 *                               hook to be offered. Hooks belonging to an
 *                               optional module (e.g. Pro Tickets) would
 *                               otherwise be mappable on installations where
 *                               nothing can ever fire them.
 *
 * @return array
 */
function ccx_get_registered_hooks()
{
    $hooks = [];

    // ──────────────────────────────────────────────────────
    //  Auto-load hook definitions from ccx_hooks/ directory
    //  One file per module: {module}_hooks.php
    // ──────────────────────────────────────────────────────
    $hooks_dir = APPPATH . 'helpers/ccx_hooks/';
    if (is_dir($hooks_dir)) {
        foreach (glob($hooks_dir . '*_hooks.php') as $file) {
            $module_hooks = include $file;
            if (!is_array($module_hooks)) {
                continue;
            }
            foreach ($module_hooks as $hook) {
                // Gated hooks stay out of the registry until their owning
                // module is activated on this installation.
                if (!empty($hook['requires_module']) && !ccx_hook_module_active($hook['requires_module'])) {
                    continue;
                }
                $hooks[] = $hook;
            }
        }
    }

    // Inject system-level variables available to ALL hooks
    $system_vars = ['all_recipients', 'recipient_name'];
    foreach ($hooks as &$h) {
        foreach ($system_vars as $sv) {
            if (!in_array($sv, $h['variables'])) {
                $h['variables'][] = $sv;
            }
        }
    }

    return $hooks;
}

/**
 * Whether a module is installed AND activated on this installation.
 *
 * Used to gate hook definitions that carry a 'requires_module' key. The
 * App_modules library is what loads modules in the first place, so it is
 * available wherever a module hook can fire — the tblmodules fallback covers
 * the odd bootstrap (cron / email pipe) where the library is not attached yet.
 *
 * @param  string $module module slug (directory name under /modules)
 * @return bool
 */
function ccx_hook_module_active($module)
{
    static $cache = [];

    if (isset($cache[$module])) {
        return $cache[$module];
    }

    $CI     = &get_instance();
    $active = false;

    if (isset($CI->app_modules) && method_exists($CI->app_modules, 'is_active')) {
        $active = (bool) $CI->app_modules->is_active($module);
    } elseif (isset($CI->db)) {
        $table = db_prefix() . 'modules';
        if ($CI->db->table_exists($table)) {
            $active = (bool) $CI->db
                ->where('module_name', $module)
                ->where('active', 1)
                ->count_all_results($table);
        }
    }

    return $cache[$module] = $active;
}

/**
 * Fire a system hook.
 *
 * For each active channel mapping:
 *   1. Validate transactional balance (count > 0, not expired)
 *   2. Prepare message from template + variable data
 *   3. Log the trigger attempt
 *   4. Decrement balance on success
 *
 * @param string $hook_key      The hook identifier (e.g. 'visit_created')
 * @param array  $data          Key-value pairs of variable values
 * @param string $only_channel  Optional: restrict firing to one channel
 *                              (used by the Hook Debugger's scoped test fire)
 * @return void
 */
function ccx_fire_hook($hook_key, $data = [], $only_channel = null)
{
    $CI = &get_instance();

    // Verify this hook exists in registry
    $hook_def = ccx_get_hook_by_key($hook_key);
    if (!$hook_def) {
        log_activity('CCX Hook Warning: Unknown hook key fired: ' . $hook_key);
        return;
    }

    // Inject {companyname} alias so both {company} and {companyname} work in templates
    if (!isset($data['companyname'])) {
        $data['companyname'] = isset($data['company']) ? $data['company'] : get_option('companyname');
    }

    // Find active template mappings for this hook
    $map_table = db_prefix() . 'ccx_hook_template_map';
    if (!$CI->db->table_exists($map_table)) {
        return;
    }

    $CI->db->where('hook_key', $hook_key)->where('active', 1);
    if (!empty($only_channel)) {
        $CI->db->where('channel', $only_channel);
    }
    $mappings = $CI->db->get($map_table)->result();

    if (empty($mappings)) {
        // Log diagnostic entry so the user can see the hook DID fire
        $recipient = isset($data['mobile_number']) ? $data['mobile_number'] : (isset($data['email']) ? $data['email'] : '');
        $staff_id = function_exists('get_staff_user_id') ? get_staff_user_id() : 0;
        _ccx_log_hook_trigger($hook_key, 'n/a', 0, $recipient, '', 'no_mapping', 'Hook fired but no active template mapping found. Configure one on the Hooks panel.', $staff_id);
        return;
    }

    $staff_id = function_exists('get_staff_user_id') ? get_staff_user_id() : 0;

    // ── Pre-pass: collect ALL recipient numbers across all mappings into {all_recipients} ──
    $all_numbers = [];
    foreach ($mappings as $m) {
        $ch = $m->channel;
        $rt = isset($m->recipient_type) ? $m->recipient_type : 'patient';
        $rv = isset($m->recipient_value) ? $m->recipient_value : null;
        $resolved = _ccx_resolve_recipients($rt, $rv, $data, $ch);
        foreach ($resolved as $r) {
            if (!empty($r['number']) && !in_array($r['number'], $all_numbers)) {
                $all_numbers[] = $r['number'];
            }
        }
    }
    $data['all_recipients'] = implode(',', $all_numbers);

    foreach ($mappings as $mapping) {
        $channel = $mapping->channel;
        $recipient_type = isset($mapping->recipient_type) ? $mapping->recipient_type : 'patient';
        $recipient_value = isset($mapping->recipient_value) ? $mapping->recipient_value : null;

        // ── 1. Get template ──
        $template = $CI->db
            ->where('id', $mapping->template_id)
            ->where('active', 1)
            ->get(db_prefix() . 'sms_wa_email_templates')
            ->row();

        if (!$template) {
            $fallback_recipient = isset($data['mobile_number']) ? $data['mobile_number'] : '';
            _ccx_log_hook_trigger($hook_key, $channel, $mapping->template_id, $fallback_recipient, '', 'no_template', 'Template not found or inactive', $staff_id);
            continue;
        }

        // Determine subtype from template (promotional or transactional)
        $subtype = (!empty($template->message_subtype) && $template->message_subtype === 'promotional') ? 'promo' : 'trans';

        // ── 2. Resolve recipients based on recipient_type ──
        $recipients = _ccx_resolve_recipients($recipient_type, $recipient_value, $data, $channel);

        if (empty($recipients)) {
            _ccx_log_hook_trigger($hook_key, $channel, $template->id, '', '', 'no_recipient', 'No recipient resolved for type: ' . $recipient_type . ($recipient_value ? ' (value: ' . $recipient_value . ')' : ''), $staff_id);
            continue;
        }

        // ── 3. Send to each resolved recipient ──
        foreach ($recipients as $recipient_info) {
            $recipient = $recipient_info['number'];
            $recipient_name = isset($recipient_info['name']) ? $recipient_info['name'] : '';

            if (empty($recipient)) {
                $missing = ($channel === 'email') ? 'email address' : 'phone number';
                _ccx_log_hook_trigger($hook_key, $channel, $template->id, '', '', 'no_recipient', 'Recipient has no ' . $missing . ($recipient_name ? ' (' . $recipient_name . ')' : ''), $staff_id);
                continue;
            }

            // When the WhatsApp module is connected, WhatsApp leaves the generic
            // gateway path entirely: it sends over the tenant's own Cloud API
            // number and is billed per 24-hour conversation, so the per-message
            // balance gate below must not run for it (a message riding inside an
            // open window is free). whatsapp_omni_dispatch() applies its own,
            // conversation-aware gate.
            $wa_bridge = _ccx_whatsapp_bridge_active($channel);

            // ── 3a. Check balance ──
            if (!$wa_bridge) {
                $balance_check = _ccx_check_channel_balance($channel, $subtype);
                if ($balance_check !== true) {
                    $message = $template->content;
                    foreach ($data as $key => $value) {
                        $message = str_replace('{' . $key . '}', $value, $message);
                    }
                    $preview = mb_substr($message, 0, 500);
                    _ccx_log_hook_trigger($hook_key, $channel, $template->id, $recipient, $preview, $balance_check['status'], $balance_check['error'], $staff_id);
                    continue;
                }
            }

            // ── 3b. Prepare message ──
            $message = $template->content;
            // Merge hook data + recipient-specific overrides for template substitution
            $merge_data = $data;
            // Set {recipient_name} — use resolved name (staff/role name, or patient name)
            $merge_data['recipient_name'] = $recipient_name;

            foreach ($merge_data as $key => $value) {
                if (!is_string($value) && !is_numeric($value)) {
                    continue;
                }
                $message = str_replace('{' . $key . '}', $value, $message);
            }

            $preview = mb_substr($message, 0, 500);

            // ── 3b-bis. WhatsApp Cloud API bridge ──
            if ($wa_bridge) {
                $wa_result = whatsapp_omni_dispatch([
                    'to'       => $recipient,
                    'message'  => $message,
                    'template' => $template,
                    'subtype'  => $subtype,
                    'data'     => $merge_data,
                    'source'   => 'hook',
                ]);

                // The Cloud API has nothing it can legally deliver right now —
                // no approved template mapped AND the customer's 24-hour window
                // is closed. Rather than drop a message that used to go out, fall
                // back to the configured gateway API when one exists (per-message
                // billing, so the standard balance gate applies again).
                $wa_fallback_api = null;
                if ($wa_result['status'] === 'no_template') {
                    $wa_fallback_api = _ccx_get_default_api($channel, (!empty($template->message_subtype)) ? $template->message_subtype : 'transactional');
                }

                if (!$wa_fallback_api) {
                    $wa_ok = ($wa_result['status'] === 'success');
                    _ccx_log_hook_trigger(
                        $hook_key,
                        $channel,
                        $template->id,
                        $recipient,
                        $preview,
                        $wa_ok ? 'success' : ($wa_result['status'] === 'failed' ? 'api_failed' : $wa_result['status']),
                        $wa_ok ? null : $wa_result['response_body'],
                        $staff_id
                    );

                    log_activity('CCX Hook Fired: ' . $hook_key . ' → ' . $channel . ' → WhatsApp Cloud API ('
                        . $wa_result['mode'] . '/' . $wa_result['category'] . ', conversation: ' . $wa_result['state']
                        . ', credit: ' . ($wa_result['charged'] ? 1 : 0) . ') → ' . $wa_result['status']);

                    continue;
                }

                $balance_check = _ccx_check_channel_balance($channel, $subtype);
                if ($balance_check !== true) {
                    _ccx_log_hook_trigger($hook_key, $channel, $template->id, $recipient, $preview, $balance_check['status'], $balance_check['error'], $staff_id);
                    continue;
                }
            }

            // ── 3c. Find default API for this channel ──
            $api_subtype = (!empty($template->message_subtype)) ? $template->message_subtype : 'transactional';
            $api = _ccx_get_default_api($channel, $api_subtype);
            if (!$api) {
                _ccx_log_hook_trigger($hook_key, $channel, $template->id, $recipient, $preview, 'no_api', 'No default API configured for channel: ' . $channel . ' (' . $api_subtype . ')', $staff_id);
                continue;
            }

            // ── 3d. Trigger API with message payload ──
            // Build {message_with_quotes}: wrap each comma-separated part in double quotes
            $message_parts = array_map('trim', explode(',', $message));
            $message_with_quotes = implode(',', array_map(function($p) { return '"' . $p . '"'; }, $message_parts));

            // E-mail templates carry their own subject and sender name (both
            // mandatory in the template modal), and those may contain tags of
            // their own — resolve them exactly like the body before handing
            // them over, so each hook's e-mail lands with ITS subject line
            // rather than the API's one-size-fits-all default.
            $subject   = isset($template->subject) ? (string) $template->subject : '';
            $from_name = isset($template->from_name) ? (string) $template->from_name : '';
            foreach ($merge_data as $key => $value) {
                if (!is_string($value) && !is_numeric($value)) {
                    continue;
                }
                $subject   = str_replace('{' . $key . '}', $value, $subject);
                $from_name = str_replace('{' . $key . '}', $value, $from_name);
            }

            $payload = [
                'to' => $recipient,
                'message' => $message,
                'message_with_quotes' => $message_with_quotes,
                'subject' => $subject,
                'from_name' => $from_name,
                'msg_template_id' => isset($template->msg_template_id) ? $template->msg_template_id : '',
                'header_id' => isset($template->header_id) ? $template->header_id : '',
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
            $payload = array_merge($payload, $merge_data);

            $result = _ccx_execute_api_call($api, $payload);

            // ── 3e. Log result + decrement only on success ──
            $api_status = ($result['status'] === 'success') ? 'success' : 'api_failed';
            $api_error = null;
            if ($result['status'] !== 'success') {
                $api_error = 'API ' . $result['status'] . ': HTTP ' . $result['response_code'];
                if (!empty($result['response_body'])) {
                    // Keep enough to include a full SMTP conversation log
                    $api_error .= ' | Response: ' . mb_substr($result['response_body'], 0, 8000);
                }
            }

            _ccx_log_hook_trigger($hook_key, $channel, $template->id, $recipient, $preview, $api_status, $api_error, $staff_id);

            // Also log to master's ccx_msgs_api_logs
            _ccx_log_to_master_api_logs($api, $result, $recipient, $staff_id);

            if ($result['status'] === 'success') {
                _ccx_decrement_channel_balance($channel, $subtype);
            }

            $recipient_label = $recipient_type !== 'patient' ? ' [' . $recipient_type . ': ' . ($recipient_name ?: $recipient_value) . ']' : '';
            log_activity('CCX Hook Fired: ' . $hook_key . ' → ' . $channel . ' → API: ' . $api->api_name . ' → ' . $result['status'] . $recipient_label);
        }
    }
}

/**
 * Resolve recipients based on recipient_type.
 *
 * @param string $recipient_type   'patient', 'staff', or 'role'
 * @param string $recipient_value  staff_id or role_id (null for patient)
 * @param array  $data             Hook data (contains mobile_number, email for patient)
 * @param string $channel          Channel key for context
 * @return array                   Array of ['number' => ..., 'name' => ...]
 */
function _ccx_resolve_recipients($recipient_type, $recipient_value, $data, $channel)
{
    $CI = &get_instance();
    $recipients = [];

    switch ($recipient_type) {
        case 'staff':
            // Send to a specific staff member
            if (!empty($recipient_value)) {
                $staff = $CI->db
                    ->select('staffid, firstname, lastname, phonenumber, email')
                    ->where('staffid', $recipient_value)
                    ->where('active', 1)
                    ->get(db_prefix() . 'staff')
                    ->row();

                if ($staff) {
                    $number = ($channel === 'email') ? $staff->email : $staff->phonenumber;
                    $recipients[] = [
                        'number' => $number,
                        'name' => $staff->firstname . ' ' . $staff->lastname,
                    ];
                }
            }
            break;

        case 'role':
            // Send to all active staff members with this role
            if (!empty($recipient_value)) {
                $staff_list = $CI->db
                    ->select('staffid, firstname, lastname, phonenumber, email')
                    ->where('role', $recipient_value)
                    ->where('active', 1)
                    ->get(db_prefix() . 'staff')
                    ->result();

                foreach ($staff_list as $s) {
                    $number = ($channel === 'email') ? $s->email : $s->phonenumber;
                    if (!empty($number)) {
                        $recipients[] = [
                            'number' => $number,
                            'name' => $s->firstname . ' ' . $s->lastname,
                        ];
                    }
                }
            }
            break;

        case 'custom':
            // Send to a custom number or a variable tag from hook data
            if (!empty($recipient_value)) {
                $number = $recipient_value;
                // Check if it's a variable tag like {mobile_number}
                if (preg_match('/^\{(.+)\}$/', $recipient_value, $matches)) {
                    $var_key = $matches[1];
                    $number = isset($data[$var_key]) ? $data[$var_key] : '';
                }
                $recipients[] = [
                    'number' => $number,
                    'name' => 'Custom: ' . $recipient_value,
                ];
            }
            break;

        case 'patient':
        default:
            // Original behavior — send to patient's number/email from hook data
            $number = '';
            if ($channel === 'email') {
                // Hooks pass the patient email as 'email' and/or 'patient_email'
                $number = !empty($data['email']) ? $data['email'] : (isset($data['patient_email']) ? $data['patient_email'] : '');
            } else {
                $number = isset($data['mobile_number']) ? $data['mobile_number'] : '';
            }
            // {recipient_name}: not every hook is about a patient — HR hooks
            // address an employee, recruitment hooks a candidate. Fall through
            // the known "who is this about" tags rather than sending a blank.
            $name = '';
            foreach (['patient_name', 'employee_name', 'candidate_name', 'customer_name'] as $name_key) {
                if (!empty($data[$name_key])) {
                    $name = $data[$name_key];
                    break;
                }
            }
            $recipients[] = [
                'number' => $number,
                'name' => $name,
            ];
            break;
    }

    return $recipients;
}

/**
 * Helper: Get a single hook definition by its key.
 *
 * @param string $hook_key
 * @return array|null
 */
function ccx_get_hook_by_key($hook_key)
{
    $hooks = ccx_get_registered_hooks();
    foreach ($hooks as $hook) {
        if ($hook['hook_key'] === $hook_key) {
            return $hook;
        }
    }
    return null;
}

/**
 * Reserved client_id for the installation's OWN message allocation.
 *
 * Allocations are keyed by client_id, i.e. by tenant — but the master account
 * (and any non-SaaS single install) sends messages too, and it is not a client
 * of itself. Its balance therefore lives on a row nothing else can collide
 * with: tblclients.userid is AUTO_INCREMENT from 1, so 0 is free forever.
 *
 * Before this existed the master read (and decremented!) whichever allocation
 * row happened to come back first — in practice some tenant's balance.
 */
if (!defined('CCX_MSGS_SELF_CLIENT_ID')) {
    define('CCX_MSGS_SELF_CLIENT_ID', 0);
}

// ══════════════════════════════════════════════════════════
//  WHATSAPP CLOUD API BRIDGE
// ══════════════════════════════════════════════════════════

/**
 * True when this channel's traffic must go out over the tenant's own
 * connected WhatsApp Cloud API number instead of a generic gateway API row.
 *
 * The WhatsApp module publishes whatsapp_omni_*() from its bootstrap, so the
 * function simply does not exist when the module is inactive — every caller
 * then keeps the original per-message gateway behaviour untouched.
 *
 * @param  string $channel  'whatsapp' | 'official_whatsapp' | anything else
 * @return bool
 */
function _ccx_whatsapp_bridge_active($channel)
{
    return function_exists('whatsapp_omni_active')
        && whatsapp_omni_is_channel($channel)
        && whatsapp_omni_active();
}

// ══════════════════════════════════════════════════════════
//  BALANCE HELPERS
// ══════════════════════════════════════════════════════════

/**
 * Map channel key to allocation column prefix.
 * Channel 'ai_call_agent' → column prefix 'aicall'
 */
function _ccx_channel_to_col_prefix($channel)
{
    $map = [
        'sms' => 'sms',
        'whatsapp' => 'whatsapp',
        // The Omni Messaging UI/campaigns store the channel as
        // 'official_whatsapp'; it bills against the same whatsapp_* columns.
        'official_whatsapp' => 'whatsapp',
        'email' => 'email',
        'ai_call_agent' => 'aicall',
    ];
    return isset($map[$channel]) ? $map[$channel] : $channel;
}

/**
 * Option name behind the superadmin's per-channel sending switch.
 *
 * @param  string $prefix   allocation column prefix (sms|whatsapp|email|aicall)
 * @param  string $subtype  'promo' | 'trans'
 * @return string
 */
function ccx_channel_send_option($prefix, $subtype)
{
    return 'ccx_channel_send_' . $prefix . '_' . ($subtype === 'promo' ? 'promo' : 'trans');
}

/**
 * The master switch a superadmin flips from the balance cards on Omni
 * Messaging — "may this installation send on this channel at all".
 *
 * Deliberately separate from the allocation's own {prefix}_{subtype}_active
 * columns: those live on the master and say what the PROVIDER has activated,
 * this one is the account's own on/off and is stored as a local option.
 *
 * A channel that was never switched off stays on — get_option() returns ''
 * for an option that has never been written, so only a literal '0' disables.
 *
 * @param  string      $channel  any channel spelling (official_whatsapp, aicall, …)
 * @param  string|null $subtype  'promo' | 'trans', or null for "is this channel
 *                               usable at all" (true while either half is on)
 * @return bool
 */
function ccx_channel_send_enabled($channel, $subtype = null)
{
    $prefix = _ccx_channel_to_col_prefix($channel);

    if ($subtype === null) {
        return (string) get_option(ccx_channel_send_option($prefix, 'promo')) !== '0'
            || (string) get_option(ccx_channel_send_option($prefix, 'trans')) !== '0';
    }

    return (string) get_option(ccx_channel_send_option($prefix, $subtype)) !== '0';
}

/**
 * Check if the current tenant/instance has sufficient balance
 * for the given channel and subtype.
 *
 * @param string $channel
 * @param string $subtype  'trans' or 'promo'
 * @return true|array  Returns true if OK, or ['status'=>..., 'error'=>...] on failure
 */
function _ccx_check_channel_balance($channel, $subtype = 'trans')
{
    $prefix = _ccx_channel_to_col_prefix($channel);
    $count_col = $prefix . '_' . $subtype . '_count';
    $expiry_col = $prefix . '_' . $subtype . '_expiry';
    $active_col = $prefix . '_' . $subtype . '_active';
    $label = ucfirst($prefix) . ' ' . ($subtype === 'promo' ? 'promotional' : 'transactional');

    // The account's own switch is checked before anything else — an
    // installation with no allocation row at all (master / non-SaaS) must
    // still obey a channel its superadmin has turned off.
    if (!ccx_channel_send_enabled($channel, $subtype)) {
        return ['status' => 'inactive', 'error' => $label . ' sending is switched off for this account'];
    }

    $allocation = _ccx_get_allocation();
    if (!$allocation) {
        return ['status' => 'insufficient_balance', 'error' => 'No message allocation found for this tenant'];
    }

    // Check if this channel+subtype is active
    if (isset($allocation->$active_col) && (int) $allocation->$active_col === 0) {
        return ['status' => 'inactive', 'error' => $label . ' messaging is disabled by admin'];
    }

    // Check expiry
    if (!empty($allocation->$expiry_col)) {
        if (strtotime($allocation->$expiry_col) < time()) {
            return ['status' => 'expired', 'error' => $label . ' balance expired on ' . $allocation->$expiry_col];
        }
    }

    // Check count
    if (!isset($allocation->$count_col) || (int) $allocation->$count_col <= 0) {
        return ['status' => 'insufficient_balance', 'error' => $label . ' balance is 0'];
    }

    return true;
}

/**
 * Decrement the balance count by 1 for the given channel and subtype.
 *
 * @param string $channel
 * @param string $subtype  'trans' or 'promo'
 * @return void
 */
function _ccx_decrement_channel_balance($channel, $subtype = 'trans')
{
    $prefix = _ccx_channel_to_col_prefix($channel);
    $count_col = $prefix . '_' . $subtype . '_count';

    if (function_exists('perfex_saas_is_tenant') && perfex_saas_is_tenant()) {
        // Tenant: update master DB
        $tenant = perfex_saas_tenant();
        if ($tenant && isset($tenant->clientid)) {
            $master_prefix = perfex_saas_master_db_prefix();
            $sql = "UPDATE `{$master_prefix}ccx_msgs_allocations` SET `{$count_col}` = GREATEST(`{$count_col}` - 1, 0) WHERE `client_id` = :client_id";
            perfex_saas_raw_query_row($sql, [], false, false, [':client_id' => $tenant->clientid]);
        }
    } else {
        // Master / non-SaaS: this installation's OWN allocation row
        $CI = &get_instance();
        $table = db_prefix() . 'ccx_msgs_allocations';
        if ($CI->db->table_exists($table)) {
            $CI->db->query(
                "UPDATE `{$table}` SET `{$count_col}` = GREATEST(`{$count_col}` - 1, 0) WHERE `client_id` = ?",
                [CCX_MSGS_SELF_CLIENT_ID]
            );
        }
    }
}

/**
 * Get the current allocation row (SaaS-aware).
 *
 * @return object|null
 */
function _ccx_get_allocation()
{
    $CI = &get_instance();

    if (function_exists('perfex_saas_is_tenant') && perfex_saas_is_tenant()) {
        $tenant = perfex_saas_tenant();
        if ($tenant && isset($tenant->clientid)) {
            $query = "SELECT * FROM `" . perfex_saas_master_db_prefix() . "ccx_msgs_allocations` WHERE `client_id` = :client_id";
            return perfex_saas_raw_query_row($query, [], true, true, [':client_id' => $tenant->clientid]);
        }
        return null;
    }

    // Master / non-SaaS: this installation's own allocation row. Never "the
    // first row in the table" — on a master that is some tenant's balance.
    $table = db_prefix() . 'ccx_msgs_allocations';
    if ($CI->db->table_exists($table)) {
        return $CI->db->where('client_id', CCX_MSGS_SELF_CLIENT_ID)->get($table)->row();
    }
    return null;
}

// ══════════════════════════════════════════════════════════
//  API LOOKUP HELPER
// ══════════════════════════════════════════════════════════

/**
 * Get the default active API configuration for a given channel.
 * Priority: client-specific API first, then global default.
 *
 * @param string $channel          Channel key (sms, whatsapp, email, etc.)
 * @param string $message_subtype  'transactional' or 'promotional'
 * @return object|null             API row or null if none configured
 */
function _ccx_get_default_api($channel, $message_subtype = 'transactional')
{
    $CI = &get_instance();

    // Map channel to API message_type
    $type_map = [
        'sms' => 'sms',
        'whatsapp' => 'whatsapp',
        'official_whatsapp' => 'whatsapp',
        'email' => 'email',
        'ai_call_agent' => 'aicall',
    ];
    $message_type = isset($type_map[$channel]) ? $type_map[$channel] : $channel;

    // SaaS tenant: APIs are stored in the master database
    if (function_exists('perfex_saas_is_tenant') && perfex_saas_is_tenant()) {
        $master_prefix = perfex_saas_master_db_prefix();

        // Determine this tenant's client_id
        $tenant_client_id = null;
        if (function_exists('perfex_saas_tenant')) {
            $tenant = perfex_saas_tenant();
            if ($tenant && !empty($tenant->clientid)) {
                $tenant_client_id = $tenant->clientid;
            }
        }

        // 1. Try client-specific API
        if ($tenant_client_id) {
            $q_client = "SELECT * FROM `{$master_prefix}ccx_msgs_apis` WHERE `message_type` = :message_type AND `message_subtype` = :message_subtype AND `is_default` = 1 AND `active` = 1 AND `api_scope` = 'client' AND `client_id` = :client_id LIMIT 1";
            $client_api = perfex_saas_raw_query_row($q_client, [], true, true, [
                ':message_type' => $message_type,
                ':message_subtype' => $message_subtype,
                ':client_id' => $tenant_client_id,
            ]);
            if ($client_api) {
                return $client_api;
            }
        }

        // 2. Fall back to global API
        $q_global = "SELECT * FROM `{$master_prefix}ccx_msgs_apis` WHERE `message_type` = :message_type AND `message_subtype` = :message_subtype AND `is_default` = 1 AND `active` = 1 AND (`api_scope` = 'global' OR `api_scope` IS NULL OR `api_scope` = '') LIMIT 1";
        return perfex_saas_raw_query_row($q_global, [], true, true, [
            ':message_type' => $message_type,
            ':message_subtype' => $message_subtype,
        ]);
    }

    // Non-SaaS fallback: query local table
    $table = db_prefix() . 'ccx_msgs_apis';
    if (!$CI->db->table_exists($table)) {
        return null;
    }

    return $CI->db
        ->where('message_type', $message_type)
        ->where('message_subtype', $message_subtype)
        ->where('is_default', 1)
        ->where('active', 1)
        ->get($table)
        ->row();
}

/**
 * Resolve the effective settings for "Use CRM Email Settings" mode.
 *
 * Reads this installation's own Setup → Settings → Email options first.
 * On a SaaS tenant whose own SMTP is not configured (empty host), falls
 * back to the MASTER installation's email settings — global email APIs
 * must keep working for tenants that never set up their own mail.
 *
 * The password may still be encrypted (Perfex core encrypts the option);
 * callers run it through the usual decrypt-with-fallback path. Master and
 * tenants share the same codebase/encryption key, so a master-sourced
 * password decrypts on tenants too.
 *
 * @return array{host:string,port:int,username:string,password:string,encryption:string,from_email:string,protocol:string,source:string}
 */
function ccx_crm_smtp_settings()
{
    $settings = [
        'host'       => trim((string) get_option('smtp_host')),
        'port'       => (int) (get_option('smtp_port') ?: 587),
        'username'   => trim((string) get_option('smtp_username')),
        'password'   => (string) get_option('smtp_password'),
        'encryption' => (string) get_option('smtp_encryption'),
        'from_email' => trim((string) get_option('smtp_email')),
        'protocol'   => get_option('email_protocol') ?: 'smtp',
        'source'     => 'local',
        'note'       => '',
    ];

    // Own settings usable: host configured, or a protocol that needs none
    if ($settings['host'] !== '' || in_array($settings['protocol'], ['mail', 'sendmail'], true)) {
        return $settings;
    }

    // Master fallback is only possible from a SaaS tenant context
    if (!function_exists('perfex_saas_is_tenant') || !perfex_saas_is_tenant()) {
        $settings['note'] = 'no master fallback: not a SaaS tenant context';
        return $settings;
    }
    if (!function_exists('perfex_saas_raw_query_row') || !function_exists('perfex_saas_master_db_prefix')) {
        $settings['note'] = 'no master fallback: perfex_saas helpers unavailable';
        return $settings;
    }

    $prefix = perfex_saas_master_db_prefix();
    // Empty DSN => the helper targets the master DSN (see perfex_saas_raw_query())
    $row = perfex_saas_raw_query_row(
        "SELECT
            MAX(CASE WHEN name = 'smtp_host' THEN value END) AS smtp_host,
            MAX(CASE WHEN name = 'smtp_port' THEN value END) AS smtp_port,
            MAX(CASE WHEN name = 'smtp_username' THEN value END) AS smtp_username,
            MAX(CASE WHEN name = 'smtp_password' THEN value END) AS smtp_password,
            MAX(CASE WHEN name = 'smtp_encryption' THEN value END) AS smtp_encryption,
            MAX(CASE WHEN name = 'smtp_email' THEN value END) AS smtp_email,
            MAX(CASE WHEN name = 'email_protocol' THEN value END) AS email_protocol
         FROM `{$prefix}options`
         WHERE name IN ('smtp_host','smtp_port','smtp_username','smtp_password','smtp_encryption','smtp_email','email_protocol')",
        [],
        true,
        true,
        []
    );

    if (!$row) {
        $settings['note'] = 'no master fallback: master options query returned nothing (prefix: ' . $prefix . ')';
        return $settings;
    }
    if (empty($row->smtp_host)) {
        $settings['note'] = 'no master fallback: master smtp_host option is empty';
        return $settings;
    }

    // The master's OAuth (google/microsoft) wiring cannot be reused from a
    // tenant — but any other protocol means plain SMTP credentials exist
    // (the module always dispatches over SMTP, even if the master's own
    // CRM mail goes out via PHP mail/sendmail)
    $master_protocol = !empty($row->email_protocol) ? $row->email_protocol : 'smtp';
    if (in_array($master_protocol, ['google', 'microsoft'], true)) {
        $settings['note'] = 'no master fallback: master email uses ' . $master_protocol . ' OAuth, which tenants cannot reuse';
        return $settings;
    }

    return [
        'host'       => trim((string) $row->smtp_host),
        'port'       => (int) (!empty($row->smtp_port) ? $row->smtp_port : 587),
        'username'   => isset($row->smtp_username) ? trim((string) $row->smtp_username) : '',
        'password'   => isset($row->smtp_password) ? (string) $row->smtp_password : '',
        'encryption' => isset($row->smtp_encryption) ? (string) $row->smtp_encryption : '',
        'from_email' => isset($row->smtp_email) ? trim((string) $row->smtp_email) : '',
        'protocol'   => 'smtp',
        'source'     => 'master',
        'note'       => '',
    ];
}

/**
 * Resolve the email header/footer wrap for "Use CRM Email Settings" mode.
 *
 * The wrap follows the same SOURCE as the SMTP settings that will do the
 * send: pass $prefer_master = true when ccx_crm_smtp_settings() resolved
 * to the master installation, so the email is branded by the settings
 * that actually send it. Perfex seeds every tenant DB with a default
 * email_header/email_footer, so "tenant options empty" can never be the
 * fallback trigger — the SMTP source is.
 *
 * Falls back to the local pair when the master's is entirely empty (and
 * vice versa). The pair always travels together so master and tenant
 * branding never mix.
 *
 * @param  bool $prefer_master  true when the send's SMTP settings came from the master
 * @return array{header:string,footer:string,source:string}
 */
function ccx_crm_email_wrap_settings($prefer_master = false)
{
    $wrap = [
        'header' => (string) get_option('email_header'),
        'footer' => (string) get_option('email_footer'),
        'source' => 'local',
    ];
    $local_empty = trim($wrap['header']) === '' && trim($wrap['footer']) === '';

    // Local wrap wins unless the send itself runs on master settings
    // (then the master's branding must match) or local has nothing
    if (!$prefer_master && !$local_empty) {
        return $wrap;
    }

    if (!function_exists('perfex_saas_is_tenant') || !perfex_saas_is_tenant()) {
        return $wrap;
    }
    if (!function_exists('perfex_saas_raw_query_row') || !function_exists('perfex_saas_master_db_prefix')) {
        return $wrap;
    }

    $prefix = perfex_saas_master_db_prefix();
    $row = perfex_saas_raw_query_row(
        "SELECT
            MAX(CASE WHEN name = 'email_header' THEN value END) AS email_header,
            MAX(CASE WHEN name = 'email_footer' THEN value END) AS email_footer
         FROM `{$prefix}options`
         WHERE name IN ('email_header','email_footer')",
        [],
        true,
        true,
        []
    );

    if ($row && (trim((string) $row->email_header) !== '' || trim((string) $row->email_footer) !== '')) {
        return [
            'header' => (string) $row->email_header,
            'footer' => (string) $row->email_footer,
            'source' => 'master',
        ];
    }

    return $wrap;
}

// ══════════════════════════════════════════════════════════
//  API EXECUTION HELPER
// ══════════════════════════════════════════════════════════

/**
 * Execute an API call using the already-fetched API object.
 * This avoids re-fetching from the tenant DB in SaaS context.
 *
 * @param object $api           API row object (from master or local DB)
 * @param array  $payload_data  Dynamic payload data (to, message, hook vars)
 * @return array                Result with status, response_code, response_body
 */
function _ccx_execute_api_call($api, $payload_data = [])
{
    // ── Replace {tag} placeholders in all API text fields ──
    // This allows admins to use tags like {patient_name}, {mobile_number} etc.
    // in the API URL, overall URL, headers, and body template.
    // URL fields get URL-encoded values; headers/body get raw values.
    if (!empty($payload_data)) {
        foreach ($payload_data as $key => $value) {
            if (!is_string($value) && !is_numeric($value)) {
                continue;
            }
            $tag = '{' . $key . '}';
            $encoded_value = urlencode($value);

            // URLs: use encoded values so query-string params are valid
            $api->api_url = str_replace($tag, $encoded_value, $api->api_url);
            if (!empty($api->overall_url)) {
                $api->overall_url = str_replace($tag, $encoded_value, $api->overall_url);
            }
            // Headers & body: use raw values (JSON / plain text)
            if (!empty($api->headers)) {
                $api->headers = str_replace($tag, $value, $api->headers);
            }
            if (!empty($api->body_template)) {
                $api->body_template = str_replace($tag, $value, $api->body_template);
            }
            // Email template fields: use raw values
            if (!empty($api->email_to_tpl)) {
                $api->email_to_tpl = str_replace($tag, $value, $api->email_to_tpl);
            }
            if (!empty($api->email_subject_tpl)) {
                $api->email_subject_tpl = str_replace($tag, $value, $api->email_subject_tpl);
            }
            if (!empty($api->email_from_name_tpl)) {
                $api->email_from_name_tpl = str_replace($tag, $value, $api->email_from_name_tpl);
            }
            if (!empty($api->email_body_tpl)) {
                $api->email_body_tpl = str_replace($tag, $value, $api->email_body_tpl);
            }
        }
    }

    // ── Email type: route through model's SMTP sending ──
    if (isset($api->message_type) && $api->message_type === 'email') {
        $CI = &get_instance();

        // Load the mailer model WITHOUT going through $CI->load->model():
        // if the ccx_msgs module isn't activated on this tenant the loader
        // show_error()s and exit()s, killing the request before any trigger
        // log is written — the send then fails with zero trace
        if (!class_exists('Ccx_msgs_api_model', false)) {
            $model_file = FCPATH . 'modules/ccx_msgs/models/Ccx_msgs_api_model.php';
            if (is_file($model_file)) {
                if (!class_exists('CI_Model', false)) {
                    load_class('Model', 'core');
                }
                require_once($model_file);
            }
        }
        if (!class_exists('Ccx_msgs_api_model', false)) {
            return [
                'status'            => 'failed',
                'response_code'     => 0,
                'response_body'     => 'Email send failed: Ccx_msgs_api_model not found (modules/ccx_msgs is missing on this installation).',
                'execution_time_ms' => 0,
                'request_url'       => 'SMTP',
                'request_payload'   => '',
            ];
        }
        if (!isset($CI->Ccx_msgs_api_model)) {
            $CI->Ccx_msgs_api_model = new Ccx_msgs_api_model();
        }

        // Resolve "to" from tag-replaced email_to_tpl, fallback to payload 'to'
        $to = '';
        if (!empty($api->email_to_tpl)) {
            $to = $api->email_to_tpl;
        }
        if (empty($to) && isset($payload_data['to'])) {
            $to = $payload_data['to'];
        }

        // Resolve subject, body, from_name from tag-replaced templates.
        // Subject and sender name belong to the MESSAGE when it brings its own
        // (every Omni Messaging e-mail template does): the API-level templates
        // are a fallback for callers that send only a body, otherwise every
        // hook would arrive under the same generic subject line.
        $subject   = !empty($payload_data['subject']) ? $payload_data['subject'] : (!empty($api->email_subject_tpl) ? $api->email_subject_tpl : 'No Subject');
        $body      = !empty($api->email_body_tpl) ? $api->email_body_tpl : (isset($payload_data['message']) ? $payload_data['message'] : '');
        $from_name = !empty($payload_data['from_name']) ? $payload_data['from_name'] : (!empty($api->email_from_name_tpl) ? $api->email_from_name_tpl : '');

        // Call send_smtp_email directly with already tag-replaced values —
        // never let a mailer error kill the request before logging
        try {
            $result = $CI->Ccx_msgs_api_model->send_smtp_email($api, $to, $subject, $body, $from_name);
        } catch (\Throwable $e) {
            $result = [
                'status'        => 'failed',
                'response_code' => 0,
                'response_body' => 'PHP error during SMTP send: ' . $e->getMessage() . ' in ' . basename($e->getFile()) . ':' . $e->getLine(),
            ];
        }

        // Normalize result format
        return [
            'status'           => isset($result['status']) ? $result['status'] : 'failed',
            'response_code'    => isset($result['response_code']) ? $result['response_code'] : 0,
            'response_body'    => isset($result['response_body']) ? $result['response_body'] : '',
            'execution_time_ms'=> 0,
            'request_url'      => 'SMTP → ' . $to,
            'request_payload'  => json_encode(['to' => $to, 'subject' => $subject]),
        ];
    }

    $url = $api->api_url;
    $method = strtoupper($api->request_method);
    $headers = [];

    // GET with Overall URL mode — use the full URL directly
    if ($method === 'GET' && !empty($api->get_mode) && $api->get_mode === 'overall_url' && !empty($api->overall_url)) {
        $url = $api->overall_url;

        // Sanitize: parse and re-encode query-string values so special chars are safe
        $parts = parse_url($url);
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $qs);
            $encoded_query = http_build_query($qs, '', '&', PHP_QUERY_RFC3986);
            $base = $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '') . (isset($parts['path']) ? $parts['path'] : '/');
            $url = $base . '?' . $encoded_query;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $start_time = microtime(true);
        $response_body = curl_exec($ch);
        $end_time = microtime(true);
        $execution_ms = round(($end_time - $start_time) * 1000);

        $response_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($curl_error) {
            $status = 'failed';
            $response_body = $curl_error;
        } elseif ($response_code >= 200 && $response_code < 300) {
            $status = 'success';
        } elseif ($response_code == 0) {
            $status = 'timeout';
        } else {
            $status = 'failed';
        }

        return [
            'status' => $status,
            'response_code' => $response_code,
            'response_body' => $response_body,
            'execution_time_ms' => $execution_ms,
            'request_url' => $url,
            'request_payload' => '',
        ];
    }

    // Parse headers
    if (!empty($api->headers)) {
        $h = json_decode($api->headers, true);
        if (is_array($h)) {
            foreach ($h as $key => $val) {
                $headers[] = $key . ': ' . $val;
            }
        }
    }

    // Build body or query string
    $body = '';
    $query_params = [];

    if (!empty($api->body_template)) {
        $template = json_decode($api->body_template, true);
        if (is_array($template)) {
            $merged = $template;
            if ($method === 'GET') {
                $query_params = $merged;
            } else {
                $body = json_encode($merged);
                if (!in_array('Content-Type: application/json', $headers)) {
                    $headers[] = 'Content-Type: application/json';
                }
            }
        } else {
            $body = $api->body_template;
        }
    } elseif (!empty($payload_data)) {
        if ($method === 'GET') {
            $query_params = $payload_data;
        } else {
            $body = json_encode($payload_data);
            if (!in_array('Content-Type: application/json', $headers)) {
                $headers[] = 'Content-Type: application/json';
            }
        }
    }

    // For GET: append params as query string to URL
    if ($method === 'GET' && !empty($query_params)) {
        $separator = (strpos($url, '?') !== false) ? '&' : '?';
        $url .= $separator . http_build_query($query_params);
    }

    // Auth
    switch ($api->auth_type) {
        case 'bearer':
            $headers[] = 'Authorization: Bearer ' . $api->auth_credentials;
            break;
        case 'api_key':
            $headers[] = 'X-API-Key: ' . $api->auth_credentials;
            break;
        case 'basic':
            $headers[] = 'Authorization: Basic ' . $api->auth_credentials;
            break;
    }

    // cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    if (in_array($method, ['POST', 'PUT', 'PATCH']) && $body !== '') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }

    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $start_time = microtime(true);
    $response_body = curl_exec($ch);
    $end_time = microtime(true);
    $execution_ms = round(($end_time - $start_time) * 1000);

    $response_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    // Determine status
    if ($curl_error) {
        $status = 'failed';
        $response_body = $curl_error;
    } elseif ($response_code >= 200 && $response_code < 300) {
        $status = 'success';
    } elseif ($response_code == 0) {
        $status = 'timeout';
    } else {
        $status = 'failed';
    }

    return [
        'status' => $status,
        'response_code' => $response_code,
        'response_body' => $response_body,
        'execution_time_ms' => $execution_ms,
        'request_url' => $url,
        'request_payload' => $body,
    ];
}

// ══════════════════════════════════════════════════════════
//  LOGGING HELPER
// ══════════════════════════════════════════════════════════

/**
 * Insert a trigger log row.
 */
function _ccx_log_hook_trigger($hook_key, $channel, $template_id, $recipient, $message_preview, $status, $error_message, $staff_id, $send_type = 'hook')
{
    $CI = &get_instance();
    $table = db_prefix() . 'ccx_hook_trigger_logs';

    if (!$CI->db->table_exists($table)) {
        return;
    }

    $insert_data = [
        'hook_key' => $hook_key,
        'channel' => $channel,
        'template_id' => $template_id,
        'recipient' => $recipient,
        'message_preview' => $message_preview,
        'status' => $status,
        'error_message' => $error_message,
        'staff_id' => $staff_id,
        'created_at' => date('Y-m-d H:i:s'),
    ];

    // Add send_type if the column exists (upgrade-safe)
    if ($CI->db->field_exists('send_type', $table)) {
        $insert_data['send_type'] = $send_type;
    }

    $CI->db->insert($table, $insert_data);
}

// ══════════════════════════════════════════════════════════
//  MASTER API LOG HELPER
// ══════════════════════════════════════════════════════════

/**
 * Log a hook-triggered API call to the master's ccx_msgs_api_logs table.
 * This makes hook-fired API calls visible on the master CCX Msgs → API Logs page.
 *
 * @param object $api      API config object
 * @param array  $result   Result from _ccx_execute_api_call
 * @param string $recipient  Recipient (phone/email)
 * @param int    $staff_id   Staff who triggered the action
 * @return void
 */
function _ccx_log_to_master_api_logs($api, $result, $recipient = '', $staff_id = 0)
{
    $log_data = [
        'api_id' => $api->id,
        'triggered_by' => (int) $staff_id,
        'tenant_name' => '',
        'request_url' => isset($result['request_url']) ? $result['request_url'] : $api->api_url,
        'request_payload' => isset($result['request_payload']) ? $result['request_payload'] : '',
        'response_code' => $result['response_code'],
        'response_body' => !empty($result['response_body']) ? mb_substr($result['response_body'], 0, 65000) : '',
        'status' => $result['status'],
        'execution_time_ms' => isset($result['execution_time_ms']) ? $result['execution_time_ms'] : 0,
        'created_at' => date('Y-m-d H:i:s'),
    ];

    // Resolve tenant name
    if (function_exists('perfex_saas_is_tenant') && perfex_saas_is_tenant()) {
        $log_data['tenant_name'] = get_option('companyname');
    } else {
        $log_data['tenant_name'] = 'Master / Local';
    }

    if (function_exists('perfex_saas_is_tenant') && perfex_saas_is_tenant()) {
        // Tenant: insert into master DB table
        $master_prefix = perfex_saas_master_db_prefix();
        $columns = implode('`, `', array_keys($log_data));
        $placeholders = implode(', ', array_map(function ($k) {
            return ':' . $k;
        }, array_keys($log_data)));
        $sql = "INSERT INTO `{$master_prefix}ccx_msgs_api_logs` (`{$columns}`) VALUES ({$placeholders})";

        $binds = [];
        foreach ($log_data as $k => $v) {
            $binds[':' . $k] = $v;
        }
        perfex_saas_raw_query_row($sql, [], false, false, $binds);
    } else {
        // Non-SaaS: insert into local table
        $CI = &get_instance();
        $table = db_prefix() . 'ccx_msgs_api_logs';
        if ($CI->db->table_exists($table)) {
            $CI->db->insert($table, $log_data);
        }
    }
}

