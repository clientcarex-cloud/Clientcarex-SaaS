<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * WhatsApp (Official Cloud API) ↔ Omni Messaging bridge.
 *
 * Omni Messaging (Setup → Omni Messaging → Official WhatsApp) has always sent
 * its WhatsApp traffic through a generic HTTP gateway row in
 * `ccx_msgs_apis` — a third-party BSP URL configured by the provider.
 *
 * As soon as THIS module is active and the tenant has connected their own
 * WhatsApp Business Account, that indirection is pointless and expensive: we
 * already hold a Cloud API token for their number. From then on every
 * WhatsApp send in the platform — hook, campaign, auto-scheduler — is routed
 * through the tenant's own connected number by this bridge, and billed per
 * 24-hour conversation (see whatsapp_credits_helper.php).
 *
 * The bridge deliberately returns the SAME array shape as
 * `_ccx_execute_api_call()` so the existing call sites only need a branch, not
 * a rewrite:
 *
 *     ['status' => 'success'|'failed', 'response_code' => int, 'response_body' => string]
 *
 * Every send is also written to the module's own `whatsapp_api_messages` +
 * `whatsapp_api_contacts` tables, so Omni traffic shows up in the WhatsApp
 * Inbox and its delivery/read receipts arrive over the normal webhook.
 */

if (!function_exists('whatsapp_omni_load')) {
    /** Load the module helpers + model on demand. */
    function whatsapp_omni_load()
    {
        require_once __DIR__ . '/whatsapp_helper.php';
        require_once __DIR__ . '/whatsapp_credits_helper.php';
        require_once __DIR__ . '/whatsapp_shared_helper.php';

        $CI = &get_instance();
        if (!isset($CI->whatsapp_model)) {
            $CI->load->model('whatsapp/whatsapp_model');
        }

        return $CI->whatsapp_model;
    }
}

if (!function_exists('whatsapp_omni_status')) {
    /**
     * Everything the Omni Messaging UI needs to describe the Cloud API link.
     *
     * @return array{module_active:bool,configured:bool,connected:bool,ready:bool,
     *               number:object|null,number_label:string,waba_name:string,
     *               reason:string,billing:string,mode:string,brand:string}
     */
    function whatsapp_omni_status()
    {
        static $status = null;
        if ($status !== null) {
            return $status;
        }

        $status = [
            'module_active' => true, // this file only loads from an active module
            'configured'    => false,
            'connected'     => false,
            'ready'         => false,
            'number'        => null,
            'number_label'  => '',
            'waba_name'     => '',
            'reason'        => '',
            'billing'       => 'conversation',
            // 'own'    — the account's own connected WhatsApp Business Account
            // 'shared' — sending on the provider's number under a grant
            'mode'          => 'own',
            'brand'         => '',
        ];

        try {
            $model = whatsapp_omni_load();

            $status['configured'] = whatsapp_is_configured();
            if (!$status['configured']) {
                $status['reason'] = 'The WhatsApp Cloud API app has not been configured by the provider yet.';

                return $status;
            }

            $connection = $model->registry_get_connection(whatsapp_current_slug());
            if (!$connection || empty($connection->access_token)) {
                // No account of their own — but the provider may have lent them
                // its number. Same Cloud API route, provider's sender, template
                // allowlist enforced at send time.
                if (whatsapp_shared_active()) {
                    $shared = whatsapp_shared_status();

                    $status['mode']      = 'shared';
                    $status['brand']     = $shared['brand'];
                    $status['connected'] = true;
                    $status['waba_name'] = $shared['brand'] . ' (shared number)';
                    $status['billing']   = $shared['billing_mode'] === 'free' ? 'free' : 'conversation';

                    if (!$shared['allow_hooks']) {
                        $status['reason'] = 'Automated WhatsApp messages are not enabled on the shared number for this account.';

                        return $status;
                    }
                    if ($shared['templates'] === 0) {
                        $status['reason'] = 'No approved templates have been shared with this account yet.';

                        return $status;
                    }

                    $status['number']       = $shared['number'];
                    $status['number_label'] = $shared['number_label'];
                    $status['ready']        = true;

                    return $status;
                }

                $status['reason'] = 'No WhatsApp Business Account is connected yet.';

                return $status;
            }

            $status['connected'] = true;
            $status['waba_name'] = (string) ($connection->waba_name ?: '');

            $number = $model->default_number();
            if (!$number) {
                $status['reason'] = 'The account is connected but no phone number has been added to it.';

                return $status;
            }

            $status['number']       = $number;
            $status['number_label'] = (string) ($number->display_phone_number ?: $number->phone_number_id);

            // Only a number Meta has actually reported as unusable blocks the
            // bridge — an unchecked number is "unknown", not "broken".
            if (whatsapp_number_unregistered($number)) {
                $status['reason'] = 'The connected number is not registered on the Cloud API — register it in the WhatsApp module.';

                return $status;
            }

            $status['ready'] = true;
        } catch (Exception $e) {
            $status['reason'] = 'WhatsApp module error: ' . $e->getMessage();
        }

        return $status;
    }
}

if (!function_exists('whatsapp_omni_active')) {
    /**
     * True when WhatsApp sends should go out over the tenant's own Cloud API
     * number instead of the generic gateway API row.
     */
    function whatsapp_omni_active()
    {
        $s = whatsapp_omni_status();

        return $s['ready'];
    }
}

if (!function_exists('whatsapp_omni_is_channel')) {
    /** Both spellings of the WhatsApp channel used across the codebase. */
    function whatsapp_omni_is_channel($channel)
    {
        return in_array($channel, ['whatsapp', 'official_whatsapp'], true);
    }
}

if (!function_exists('whatsapp_omni_template_for')) {
    /**
     * Resolve the approved Cloud API template mapped to an Omni Messaging
     * template row.
     *
     * `wa_template_name` is the explicit mapping. Installs that predate it
     * often carry the provider template name in `msg_template_id` (a mandatory
     * field in the template modal), so that is accepted as a fallback — but
     * only when it actually matches a synced, approved template, never blindly.
     *
     * @return array{name:string,language:string,variables:int,row:object|null}
     */
    function whatsapp_omni_template_for($template)
    {
        $out = ['name' => '', 'language' => 'en', 'variables' => 0, 'row' => null];
        if (!$template) {
            return $out;
        }

        $CI       = &get_instance();
        $tpl_tbl  = db_prefix() . 'whatsapp_api_templates';
        $name     = trim((string) ($template->wa_template_name ?? ''));
        $language = trim((string) ($template->wa_template_language ?? ''));

        if ($name === '' && !empty($template->msg_template_id) && $CI->db->table_exists($tpl_tbl)) {
            $candidate = trim((string) $template->msg_template_id);
            $exists    = $CI->db->where('name', $candidate)->limit(1)->get($tpl_tbl)->row();
            if ($exists) {
                $name = $candidate;
            }
        }

        if ($name === '') {
            return $out;
        }

        $out['name']     = $name;
        $out['language'] = $language !== '' ? $language : 'en';

        if ($CI->db->table_exists($tpl_tbl)) {
            $CI->db->where('name', $name);
            if ($language !== '') {
                $CI->db->where('language', $language);
            }
            $row = $CI->db->limit(1)->get($tpl_tbl)->row();
            if ($row) {
                $out['row']       = $row;
                $out['language']  = (string) $row->language;
                $out['variables'] = (int) $row->variables_count;
            }
        }

        return $out;
    }
}

if (!function_exists('whatsapp_omni_template_params')) {
    /**
     * Build the {{1}}..{{n}} body parameters for an approved template.
     *
     * Order of preference:
     *   1. The template row's `wa_params` mapping — a comma-separated list of
     *      Omni tags, e.g. "{patient_name}, {visit_date}".
     *   2. The rendered message split on "|" when the approved template expects
     *      more than one variable.
     *   3. The whole rendered message as {{1}} — the classic single-variable
     *      pass-through template every BSP ships with.
     *
     * @param  object $template Omni Messaging template row
     * @param  string $message  fully rendered message text
     * @param  array  $data     hook/campaign variable values
     * @param  int    $expected number of variables the approved template has
     * @return array{params:array,error:string}
     */
    function whatsapp_omni_template_params($template, $message, $data, $expected)
    {
        if ($expected <= 0) {
            return ['params' => [], 'error' => ''];
        }

        $mapping = trim((string) ($template->wa_params ?? ''));
        $params  = [];

        if ($mapping !== '') {
            foreach (explode(',', $mapping) as $piece) {
                $piece = trim($piece);
                if ($piece === '') {
                    continue;
                }
                if (preg_match('/^\{(.+)\}$/', $piece, $m)) {
                    $key   = $m[1];
                    $value = isset($data[$key]) ? $data[$key] : '';
                } else {
                    $value = $piece;
                }
                // Meta rejects newlines, tabs and 4+ consecutive spaces in a
                // parameter (error 132007), so flatten before sending.
                $params[] = trim(preg_replace('/\s{2,}/', ' ', str_replace(["\r", "\n", "\t"], ' ', (string) $value)));
            }
        } elseif ($expected === 1) {
            $params[] = trim(preg_replace('/\s{2,}/', ' ', str_replace(["\r", "\n", "\t"], ' ', $message)));
        } else {
            foreach (explode('|', $message) as $piece) {
                $params[] = trim(preg_replace('/\s{2,}/', ' ', str_replace(["\r", "\n", "\t"], ' ', $piece)));
            }
        }

        if (count($params) !== $expected) {
            return [
                'params' => $params,
                'error'  => 'Template "' . ($template->wa_template_name ?? '') . '" expects ' . $expected
                    . ' variable(s) but ' . count($params) . ' were resolved. Map them in the template\'s '
                    . '"Template variables" field, or separate the values with "|" in the content.',
            ];
        }

        foreach ($params as $p) {
            if ($p === '') {
                return ['params' => $params, 'error' => 'A template variable resolved to an empty value — Meta rejects blank parameters.'];
            }
        }

        return ['params' => $params, 'error' => ''];
    }
}

if (!function_exists('whatsapp_omni_dispatch')) {
    /**
     * Send one Omni Messaging WhatsApp message over the tenant's Cloud API
     * number, charging the 24-hour conversation ledger.
     *
     * Delivery mode:
     *   • An approved template is mapped → send the template (works whether or
     *     not the customer's window is open).
     *   • No template, customer window open → free-form session text, free.
     *   • No template, window closed → refused with the reason, because Meta
     *     itself would reject it (error 131047).
     *
     * @param array $args {
     *     @type string $to        recipient phone (any local format)
     *     @type string $message   fully rendered message text
     *     @type object $template  Omni Messaging template row (optional)
     *     @type string $subtype   'promo' | 'trans'
     *     @type array  $data      variable values for parameter mapping
     *     @type string $source    hook | campaign | auto_scheduler
     * }
     * @return array{status:string,response_code:int,response_body:string,charged:bool,
     *               state:string,category:string,mode:string}
     */
    function whatsapp_omni_dispatch($args)
    {
        $to       = isset($args['to']) ? (string) $args['to'] : '';
        $message  = isset($args['message']) ? (string) $args['message'] : '';
        $template = isset($args['template']) ? $args['template'] : null;
        $subtype  = (isset($args['subtype']) && $args['subtype'] === 'promo') ? 'promo' : 'trans';
        $data     = isset($args['data']) && is_array($args['data']) ? $args['data'] : [];
        $source   = isset($args['source']) ? (string) $args['source'] : 'hook';

        $fail = function ($status, $error) {
            return [
                'status'        => $status,
                'response_code' => 0,
                'response_body' => $error,
                'charged'       => false,
                'state'         => 'none',
                'category'      => '',
                'mode'          => '',
            ];
        };

        $model  = whatsapp_omni_load();
        $status = whatsapp_omni_status();

        if (!$status['ready']) {
            return $fail('failed', $status['reason'] ?: 'WhatsApp Cloud API is not ready.');
        }

        $phone = whatsapp_normalize_phone($to);
        if ($phone === '') {
            return $fail('failed', 'Recipient has no usable phone number.');
        }

        $phone_number_id = $status['number']->phone_number_id;

        // Honour opt-outs recorded in the WhatsApp module.
        $contact = $model->get_contact($phone);
        if ($contact && (int) $contact->opted_out === 1) {
            return $fail('failed', 'Contact has opted out of WhatsApp messages.');
        }

        // ── Delivery mode ──
        $tpl      = whatsapp_omni_template_for($template);
        $use_tpl  = $tpl['name'] !== '';
        $params   = [];

        if ($use_tpl) {
            $resolved = whatsapp_omni_template_params($template, $message, $data, $tpl['variables']);
            if ($resolved['error'] !== '') {
                return $fail('failed', $resolved['error']);
            }
            $params = $resolved['params'];
        } elseif ($status['mode'] === 'shared') {
            // The shared number is template-only — there is no customer window
            // to reply inside, because inbound goes to the provider's inbox.
            // 'no_template' lets the call sites fall back to a gateway API row
            // if the tenant has one, exactly as for an unmapped own-number send.
            return $fail(
                'no_template',
                'Only approved templates can be sent on ' . $status['brand']
                . "'s shared WhatsApp number, and this Omni template is not mapped to one."
            );
        } elseif (!whatsapp_service_window_open($phone)) {
            return $fail(
                'no_template',
                'The 24-hour customer window is closed and this template is not mapped to an approved '
                . 'WhatsApp template. Map one under Omni Messaging → Official WhatsApp → Templates.'
            );
        }

        $category = $use_tpl
            ? whatsapp_conversation_category_for($tpl['name'], $tpl['language'], $subtype)
            : 'service';

        // ── Credit gate (conversation-aware) ──
        $gate = whatsapp_credits_precheck($phone, $category);
        if ($gate !== true) {
            return $fail($gate['status'], $gate['error']);
        }

        // ── Send ──
        if ($use_tpl) {
            $res = $model->send_template($phone_number_id, $phone, $tpl['name'], $tpl['language'], $params);
        } else {
            $res = $model->send_text($phone_number_id, $phone, $message);
        }

        $now = date('Y-m-d H:i:s');

        if (isset($res['error'])) {
            $model->log_message([
                'phone_number_id' => $phone_number_id,
                'direction'       => 'outgoing',
                'phone'           => $phone,
                'contact_name'    => isset($data['patient_name']) ? $data['patient_name'] : null,
                'type'            => $use_tpl ? 'template' : 'text',
                'body'            => $message,
                'template_name'   => $use_tpl ? $tpl['name'] : null,
                'status'          => 'failed',
                'error_message'   => substr($res['error'], 0, 480),
                'error_code'      => $res['error_code'] ?? null,
            ]);

            return [
                'status'        => 'failed',
                'response_code' => 0,
                'response_body' => $res['error'],
                'charged'       => false,
                'state'         => 'none',
                'category'      => $category,
                'mode'          => $use_tpl ? 'template' : 'session',
            ];
        }

        // ── Booked: log, then charge the conversation ──
        $model->log_message([
            'phone_number_id' => $phone_number_id,
            'direction'       => 'outgoing',
            'phone'           => $phone,
            'contact_name'    => isset($data['patient_name']) ? $data['patient_name'] : null,
            'type'            => $use_tpl ? 'template' : 'text',
            'body'            => $message,
            'template_name'   => $use_tpl ? $tpl['name'] : null,
            'wamid'           => $res['wamid'],
            'status'          => 'accepted',
        ]);

        $model->upsert_contact($phone, [
            'name'             => isset($data['patient_name']) ? $data['patient_name'] : null,
            'last_outgoing_at' => $now,
        ]);

        $charge = whatsapp_credits_commit($phone, $category, ['source' => $source]);

        return [
            'status'        => 'success',
            'response_code' => 200,
            'response_body' => json_encode([
                'wamid'        => $res['wamid'],
                'mode'         => $use_tpl ? 'template' : 'session',
                'template'     => $use_tpl ? $tpl['name'] : null,
                'category'     => $category,
                'conversation' => $charge['state'],
                'credit'       => $charge['charged'] ? 1 : 0,
            ]),
            'charged'  => $charge['charged'],
            'state'    => $charge['state'],
            'category' => $category,
            'mode'     => $use_tpl ? 'template' : 'session',
        ];
    }
}

if (!function_exists('whatsapp_omni_approved_templates')) {
    /** Approved Cloud API templates, for the Omni template mapping picker. */
    function whatsapp_omni_approved_templates()
    {
        $CI    = &get_instance();
        $table = db_prefix() . 'whatsapp_api_templates';

        if (!$CI->db->table_exists($table)) {
            return [];
        }

        $rows = $CI->db->select('name, language, category, status, body_text, variables_count, has_media_header')
            ->where('status', 'APPROVED')
            ->order_by('name', 'ASC')
            ->get($table)->result();

        // Carry the auto-map engine's reading of the template along, so the
        // mapping modal can offer the same suggestion the engine would apply.
        foreach ($rows as $row) {
            $built                   = whatsapp_omni_build_from_approved($row);
            $row->suggested_params   = $built['params'];
            $row->suggested_content  = $built['content'];
            $row->omni_supported     = whatsapp_omni_supported_template($row) ? 1 : 0;
        }

        return $rows;
    }
}

/* ═══════════════════════════════════════════════════════════════════════════
 *  AUTO-MAPPING
 *
 *  Everything below turns the tenant's APPROVED Meta template library into
 *  ready-to-use Omni Messaging templates, so that the moment the WhatsApp
 *  module is connected the Official WhatsApp channel can be wired to hooks,
 *  campaigns and auto-schedulers without anyone hand-mapping a single row.
 *
 *  It runs on three passes:
 *    A. LINK    — an existing Omni template whose title / Msg Template ID
 *                 already names an approved template gets mapped to it.
 *    B. IMPORT  — every approved template nothing maps to becomes a new Omni
 *                 template, its {{1}}..{{n}} placeholders translated into hook
 *                 tags so it reads like a hand-written template.
 *    C. REFRESH — rows this engine created follow their source: body changes
 *                 are pulled in, and a template that is withdrawn or falls out
 *                 of APPROVED is deactivated (never deleted — hook mappings
 *                 point at the row id).
 *
 *  Rows the user has since edited are left alone: saving a template through
 *  the modal clears its `wa_auto_synced` flag, which takes it out of pass C.
 * ═══════════════════════════════════════════════════════════════════════════ */

if (!function_exists('whatsapp_omni_templates_table')) {
    /** The Omni Messaging templates table this engine writes into. */
    function whatsapp_omni_templates_table()
    {
        return db_prefix() . 'sms_wa_email_templates';
    }
}

if (!function_exists('whatsapp_omni_ensure_schema')) {
    /**
     * Self-heal the Cloud API columns on the Omni Messaging templates table.
     *
     * The columns belong to the sms_wa_email module, but this engine can run
     * on a tenant whose Omni module has not been opened since the upgrade, so
     * it must never assume its installer has run.
     *
     * @return bool false when Omni Messaging itself is not installed
     */
    function whatsapp_omni_ensure_schema()
    {
        static $ok = null;
        if ($ok !== null) {
            return $ok;
        }

        $CI    = &get_instance();
        $table = whatsapp_omni_templates_table();

        if (!$CI->db->table_exists($table)) {
            return $ok = false;
        }

        $columns = [
            'wa_template_name'     => 'ADD `wa_template_name` varchar(191) DEFAULT NULL COMMENT "Approved WhatsApp Cloud API template name"',
            'wa_template_language' => 'ADD `wa_template_language` varchar(20) DEFAULT NULL COMMENT "Approved template language code, e.g. en"',
            'wa_params'            => 'ADD `wa_params` varchar(500) DEFAULT NULL COMMENT "Comma-separated tags mapped to {{1}}..{{n}}"',
            'wa_auto_synced'       => 'ADD `wa_auto_synced` tinyint(1) NOT NULL DEFAULT 0 COMMENT "1 = created/maintained by the Cloud API auto-map engine"',
            'wa_synced_at'         => 'ADD `wa_synced_at` datetime DEFAULT NULL COMMENT "Last time the auto-map engine touched this row"',
        ];

        foreach ($columns as $column => $sql) {
            if (!$CI->db->field_exists($column, $table)) {
                $CI->db->query('ALTER TABLE `' . $table . '` ' . $sql . ';');
            }
        }

        return $ok = true;
    }
}

if (!function_exists('whatsapp_omni_slug')) {
    /** Normalise a title / template name for comparison. */
    function whatsapp_omni_slug($value)
    {
        return trim(preg_replace('/_+/', '_', preg_replace('/[^a-z0-9]+/', '_', strtolower((string) $value))), '_');
    }
}

if (!function_exists('whatsapp_omni_humanize')) {
    /** "visit_created_v2" → "Visit Created V2" — a readable Omni title. */
    function whatsapp_omni_humanize($name)
    {
        $words = trim(preg_replace('/[^a-z0-9]+/i', ' ', (string) $name));

        return $words === '' ? (string) $name : ucwords($words);
    }
}

if (!function_exists('whatsapp_omni_supported_template')) {
    /**
     * Whether this engine can wire an approved template to a hook.
     *
     * Authentication templates carry their code in a button component and
     * media-header templates need an uploaded asset per send — neither is
     * something `whatsapp_omni_dispatch()` can supply from a hook payload, so
     * they are reported as skipped instead of being imported broken.
     */
    function whatsapp_omni_supported_template($tpl)
    {
        if (!$tpl || strtoupper((string) $tpl->status) !== 'APPROVED') {
            return false;
        }
        if (strtoupper((string) $tpl->category) === 'AUTHENTICATION') {
            return false;
        }

        return (int) ($tpl->has_media_header ?? 0) === 0;
    }
}

if (!function_exists('whatsapp_omni_guess_tags')) {
    /**
     * Guess the hook tag behind each {{n}} placeholder of an approved body.
     *
     * Meta templates are numbered, hooks are named, and nothing in the Cloud
     * API carries the link — so it is read out of the words leading up to the
     * placeholder ("Dear {{1}}" → patient_name, "on {{2}}" → visit_date). Every
     * guess is visible and editable in the template modal; a placeholder the
     * rules cannot read is left as {{n}} and the template is reported as
     * needing variables rather than mapped to something wrong.
     *
     * @return array {{n}} index (1-based) => hook tag, missing when unresolved
     */
    function whatsapp_omni_guess_tags($body_text)
    {
        $body = (string) $body_text;
        if (!preg_match_all('/\{\{\s*(\d+)\s*\}\}/', $body, $m, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        // Ordered — the first rule that matches the words before the
        // placeholder wins, so the specific ones come before the generic.
        $rules = [
            '/\b(otp|one[ -]?time|verification|auth(entication)?)\s*(code|password|pin)?\s*[:\-]?\s*$/i' => 'otp_code',
            // "MR No: {{1}}" is an identifier; "Dear Mr. {{1}}" is a salutation,
            // so a bare Mr must fall through to the name rule below.
            '/\b(mrn|uhid)\s*[:\-]?\s*$/i'                                                     => 'mr_number',
            '/\b(mr|patient|registration|reg)\s*(no|number|id|#)\s*[:\-]?\s*$/i'                => 'mr_number',
            '/\b(dr|doctor|consultant|physician|specialist)\.?\s*$/i'                          => 'doctor_name',
            '/\b(visit|bill|invoice|receipt|token|booking|order)\s*(id|no|number|code|#)?\s*[:\-]?\s*$/i' => 'visit_code',
            '/\b(balance|outstanding|pending|due)\s*(amount|amt|of|is|:)?\s*[₹$]?\s*$/i'       => 'balance_due',
            '/\b(paid|payment\s+received|received)\s*(amount|amt|of|is|:)?\s*[₹$]?\s*$/i'      => 'total_paid',
            '/\b(amount|amt|total|bill|fee|charges|payable|rs\.?|inr)\s*(of|is|:)?\s*[₹$]?\s*$/i' => 'total_amount',
            '/[₹$]\s*$/'                                                                       => 'total_amount',
            '/\b(mobile|phone|contact)\s*(no|number)?\s*[:\-]?\s*$/i'                          => 'mobile_number',
            '/\b(e-?mail)\s*(id|address)?\s*[:\-]?\s*$/i'                                      => 'patient_email',
            '/\b(report|result|link|url|download|click|view|here)\s*[:\-]?\s*$/i'              => 'report_link',
            '/\b(mode|method|via|through|by)\s*[:\-]?\s*$/i'                                   => 'payment_mode',
            '/\b(on|at|date|dated|scheduled|appointment|visit)\s*(for|on)?\s*[:\-]?\s*$/i'     => 'visit_date',
            '/\b(dear|hi|hello|thank you|thanks|mr|mrs|ms|miss|patient|name)\.?\s*[,:\-]?\s*$/i' => 'patient_name',
        ];

        // Second tier — the keyword is in the phrase but not right against the
        // placeholder ("your report is ready: {{1}}"). Only consulted when no
        // strict rule matched, and over a short window so it stays defensible.
        $loose = [
            '/\b(otp|verification|passcode)\b/i'                     => 'otp_code',
            '/\b(report|result|link|url|download|click here)\b/i'    => 'report_link',
            '/\b(balance|outstanding|due)\b/i'                       => 'balance_due',
            '/\b(amount|total|fee|charges|payment|bill|rs\.?|inr)\b/i' => 'total_amount',
            '/\b(date|scheduled|appointment)\b/i'                    => 'visit_date',
            '/\b(dr|doctor|consultant)\b/i'                          => 'doctor_name',
            '/\b(dear|hi|hello|thank you|thanks|name)\b/i'           => 'patient_name',
        ];

        $tags = [];
        foreach ($m[1] as $i => $match) {
            $index = (int) $match[0];
            if (isset($tags[$index])) {
                continue;
            }
            // The ~60 characters leading up to this placeholder carry the clue.
            $before = substr($body, 0, $m[0][$i][1]);
            $before = rtrim(substr($before, max(0, strlen($before) - 60)));
            // "Your OTP is {{1}}" reads the same as "Your OTP: {{1}}" — drop the
            // linking verb so one rule covers both phrasings.
            $before = preg_replace('/\s+(is|are|was|were)\s*[:\-]?\s*$/i', '', $before);

            foreach ($rules as $pattern => $tag) {
                if (preg_match($pattern, $before)) {
                    $tags[$index] = $tag;
                    break;
                }
            }

            if (!isset($tags[$index])) {
                $window = ltrim(substr($before, max(0, strlen($before) - 28)));
                foreach ($loose as $pattern => $tag) {
                    if (preg_match($pattern, $window)) {
                        $tags[$index] = $tag;
                        break;
                    }
                }
            }

            // A leading placeholder with nothing before it is the salutation
            // in practice ("{{1}}, your report is ready").
            if (!isset($tags[$index]) && $index === 1 && trim($before) === '') {
                $tags[$index] = 'patient_name';
            }
        }

        // Two placeholders that read as the same tag ("scheduled for {{3}} at
        // {{4}}") would send one value twice. Keep the first, drop the rest —
        // the template then reports as needing variables instead of quietly
        // sending the wrong thing.
        $seen = [];
        foreach ($tags as $index => $tag) {
            if (isset($seen[$tag])) {
                unset($tags[$index]);
                continue;
            }
            $seen[$tag] = true;
        }

        return $tags;
    }
}

if (!function_exists('whatsapp_omni_build_from_approved')) {
    /**
     * Turn an approved template into the Omni Messaging fields that mirror it.
     *
     * @return array{content:string,params:string,resolved:bool,variables:int}
     */
    function whatsapp_omni_build_from_approved($tpl)
    {
        $body      = (string) $tpl->body_text;
        $variables = (int) $tpl->variables_count;
        $tags      = whatsapp_omni_guess_tags($body);

        $content = preg_replace_callback('/\{\{\s*(\d+)\s*\}\}/', function ($m) use ($tags) {
            $index = (int) $m[1];

            return isset($tags[$index]) ? '{' . $tags[$index] . '}' : $m[0];
        }, $body);

        // Only a fully resolved template gets a parameter mapping — a partial
        // one would send the wrong value into {{n}}.
        $ordered  = [];
        $resolved = true;
        for ($i = 1; $i <= $variables; $i++) {
            if (!isset($tags[$i])) {
                $resolved = false;
                break;
            }
            $ordered[] = '{' . $tags[$i] . '}';
        }

        return [
            'content'   => $content,
            'params'    => ($resolved && $ordered) ? implode(', ', $ordered) : '',
            'resolved'  => $variables === 0 ? true : $resolved,
            'variables' => $variables,
        ];
    }
}

if (!function_exists('whatsapp_omni_autosync_summary')) {
    /** The last auto-map result, as stored by the engine. */
    function whatsapp_omni_autosync_summary()
    {
        $raw = get_option('whatsapp_omni_autosync_summary');
        $out = $raw ? json_decode($raw, true) : null;

        return is_array($out) ? $out : null;
    }
}

if (!function_exists('whatsapp_omni_autosync_templates')) {
    /**
     * Map the tenant's approved Meta templates onto the Official WhatsApp
     * channel's templates, so they can be picked in the Hooks tab.
     *
     * Safe to call on every page load: it is throttled unless forced, only
     * ever writes when something actually differs, and never touches a
     * template the user has edited.
     *
     * @param  bool $force ignore the throttle (manual "Sync" button)
     * @return array{ran:bool,reason:string,linked:int,imported:int,updated:int,
     *                retired:int,skipped:int,needs_params:int,approved:int,at:string|null}
     */
    function whatsapp_omni_autosync_templates($force = false)
    {
        $out = [
            'ran'          => false,
            'reason'       => '',
            'linked'       => 0,
            'imported'     => 0,
            'updated'      => 0,
            'retired'      => 0,
            'skipped'      => 0,
            'needs_params' => 0,
            'approved'     => 0,
            'at'           => null,
        ];

        $status = whatsapp_omni_status();
        if (!$status['ready']) {
            $out['reason'] = $status['reason'] ?: 'WhatsApp Cloud API is not connected.';

            return $out;
        }

        if (!whatsapp_omni_ensure_schema()) {
            $out['reason'] = 'Omni Messaging is not installed on this account yet.';

            return $out;
        }

        // Throttle the automatic (page-load) run — the manual Sync button and
        // a fresh pull from Meta both force it.
        $last = (int) get_option('whatsapp_omni_autosync_at');
        if (!$force && $last && (time() - $last) < 300) {
            $previous = whatsapp_omni_autosync_summary();
            if ($previous) {
                $previous['ran']    = false;
                $previous['reason'] = 'throttled';

                return array_merge($out, $previous);
            }
            $out['reason'] = 'throttled';

            return $out;
        }

        $CI    = &get_instance();
        $table = whatsapp_omni_templates_table();
        $now   = date('Y-m-d H:i:s');

        // On the provider's shared number the "approved library" is whatever
        // the provider allowlisted, so refresh the local mirror before mapping
        // — otherwise a template granted a minute ago is invisible to hooks.
        if ($status['mode'] === 'shared') {
            whatsapp_shared_sync_templates($force);
        }

        $approved = whatsapp_omni_approved_templates();
        $out['approved'] = count($approved);

        // Approved library, indexed for lookup by name (+ language).
        $by_key  = [];
        $by_name = [];
        foreach ($approved as $tpl) {
            $by_key[strtolower($tpl->name . '|' . $tpl->language)] = $tpl;
            $slug = whatsapp_omni_slug($tpl->name);
            if (!isset($by_name[$slug])) {
                $by_name[$slug] = $tpl;
            }
            if (!whatsapp_omni_supported_template($tpl)) {
                $out['skipped']++;
            }
        }

        $rows   = $CI->db->where('type', 'whatsapp')->get($table)->result();
        $mapped = [];   // name|language already claimed by an Omni row
        foreach ($rows as $row) {
            if (!empty($row->wa_template_name)) {
                $mapped[strtolower($row->wa_template_name . '|' . ($row->wa_template_language ?: 'en'))] = true;
            }
        }

        /* ── Pass A: link unmapped rows that already name an approved template ── */
        foreach ($rows as $row) {
            if (!empty($row->wa_template_name)) {
                continue;
            }

            $candidates = array_filter([
                whatsapp_omni_slug($row->msg_template_id ?? ''),
                whatsapp_omni_slug($row->title),
            ]);

            foreach ($candidates as $candidate) {
                if (!isset($by_name[$candidate])) {
                    continue;
                }
                $tpl = $by_name[$candidate];
                if (!whatsapp_omni_supported_template($tpl)) {
                    continue;
                }

                $built  = whatsapp_omni_build_from_approved($tpl);
                $update = [
                    'wa_template_name'     => $tpl->name,
                    'wa_template_language' => $tpl->language,
                    'wa_synced_at'         => $now,
                ];
                // The row's own content is the user's — only the mapping is filled in.
                if (trim((string) $row->wa_params) === '' && $built['params'] !== '') {
                    $update['wa_params'] = $built['params'];
                }

                $CI->db->where('id', $row->id)->update($table, $update);

                $row->wa_template_name     = $tpl->name;
                $row->wa_template_language = $tpl->language;
                $mapped[strtolower($tpl->name . '|' . $tpl->language)] = true;
                $out['linked']++;

                if ((int) $tpl->variables_count > 0
                    && trim((string) $row->wa_params) === '' && $built['params'] === '') {
                    $out['needs_params']++;
                }
                break;
            }
        }

        /* ── Pass B: import approved templates nothing maps to ── */
        $titles = [];
        foreach ($rows as $row) {
            $titles[strtolower(trim($row->title))] = true;
        }
        $has_any = count($rows) > 0;

        foreach ($approved as $tpl) {
            $key = strtolower($tpl->name . '|' . $tpl->language);
            if (isset($mapped[$key]) || !whatsapp_omni_supported_template($tpl)) {
                continue;
            }

            $built = whatsapp_omni_build_from_approved($tpl);

            $title = whatsapp_omni_humanize($tpl->name);
            if (isset($titles[strtolower($title)])) {
                $title .= ' (' . $tpl->language . ')';
            }
            if (isset($titles[strtolower($title)])) {
                $title = whatsapp_omni_humanize($tpl->name) . ' — ' . $tpl->name . ' (' . $tpl->language . ')';
            }

            $CI->db->insert($table, [
                'type'                 => 'whatsapp',
                'message_subtype'      => strtoupper((string) $tpl->category) === 'MARKETING' ? 'promotional' : 'transactional',
                'title'                => $title,
                'content'              => $built['content'],
                // Keeps the legacy gateway identifier meaningful and lets a
                // re-run recognise the row even if the mapping is cleared.
                'msg_template_id'      => $tpl->name,
                'sample_content'       => $tpl->body_text,
                'wa_template_name'     => $tpl->name,
                'wa_template_language' => $tpl->language,
                'wa_params'            => $built['params'],
                'wa_auto_synced'       => 1,
                'wa_synced_at'         => $now,
                'is_attachment'        => 0,
                'active'               => 1,
                'is_default'           => $has_any ? 0 : 1,
                'created_at'           => $now,
            ]);

            $has_any                          = true;
            $titles[strtolower($title)]       = true;
            $mapped[$key]                     = true;
            $out['imported']++;

            if (!$built['resolved']) {
                $out['needs_params']++;
            }
        }

        /* ── Pass C: keep engine-owned rows in step with their source ── */
        $owned = $CI->db->where('type', 'whatsapp')
            ->where('wa_auto_synced', 1)
            ->where('wa_template_name IS NOT NULL', null, false)
            ->get($table)->result();

        foreach ($owned as $row) {
            $key = strtolower($row->wa_template_name . '|' . ($row->wa_template_language ?: 'en'));
            $tpl = isset($by_key[$key]) ? $by_key[$key] : null;

            if (!$tpl || !whatsapp_omni_supported_template($tpl)) {
                // Withdrawn, rejected or paused at Meta — park it rather than
                // delete it, because hook mappings point at this row id.
                if ((int) $row->active === 1) {
                    $CI->db->where('id', $row->id)->update($table, ['active' => 0, 'wa_synced_at' => $now]);
                    $out['retired']++;
                }
                continue;
            }

            // Meta's body is mirrored in sample_content — a difference means
            // the template was edited and re-approved upstream.
            if (trim((string) $row->sample_content) === trim((string) $tpl->body_text)) {
                continue;
            }

            $built = whatsapp_omni_build_from_approved($tpl);
            $CI->db->where('id', $row->id)->update($table, [
                'content'         => $built['content'],
                'sample_content'  => $tpl->body_text,
                'wa_params'       => $built['params'],
                'message_subtype' => strtoupper((string) $tpl->category) === 'MARKETING' ? 'promotional' : 'transactional',
                'wa_synced_at'    => $now,
            ]);
            $out['updated']++;

            if (!$built['resolved']) {
                $out['needs_params']++;
            }
        }

        $out['ran'] = true;
        $out['at']  = $now;

        update_option('whatsapp_omni_autosync_at', time());
        update_option('whatsapp_omni_autosync_summary', json_encode($out));

        return $out;
    }
}

if (!function_exists('whatsapp_omni_needs_params_count')) {
    /**
     * Mapped Official WhatsApp templates that still need their {{n}} variables
     * filled in before a hook can deliver them.
     */
    function whatsapp_omni_needs_params_count()
    {
        if (!whatsapp_omni_ensure_schema()) {
            return 0;
        }

        $CI       = &get_instance();
        $approved = [];
        foreach (whatsapp_omni_approved_templates() as $tpl) {
            $approved[strtolower($tpl->name . '|' . $tpl->language)] = (int) $tpl->variables_count;
        }

        $rows = $CI->db->select('wa_template_name, wa_template_language, wa_params')
            ->where('type', 'whatsapp')
            ->where('active', 1)
            ->where("COALESCE(wa_template_name, '') !=", '')
            ->get(whatsapp_omni_templates_table())->result();

        $count = 0;
        foreach ($rows as $row) {
            $key = strtolower($row->wa_template_name . '|' . ($row->wa_template_language ?: 'en'));
            if (!empty($approved[$key]) && trim((string) $row->wa_params) === '') {
                $count++;
            }
        }

        return $count;
    }
}
