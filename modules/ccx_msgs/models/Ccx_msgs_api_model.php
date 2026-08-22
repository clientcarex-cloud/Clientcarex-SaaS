<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Ccx_msgs_api_model extends App_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Get API(s)
     * @param mixed $id  numeric = single row, empty = all
     */
    public function get($id = '')
    {
        if (is_numeric($id)) {
            $this->db->where('id', $id);
            return $this->db->get(db_prefix() . 'ccx_msgs_apis')->row();
        }
        $this->db->order_by('message_type', 'asc');
        $this->db->order_by('message_subtype', 'asc');
        return $this->db->get(db_prefix() . 'ccx_msgs_apis')->result_array();
    }

    /**
     * Add new API configuration
     */
    public function add($data)
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['active'] = isset($data['active']) ? 1 : 0;
        $data['is_default'] = isset($data['is_default']) ? 1 : 0;
        $data['use_crm_smtp'] = isset($data['use_crm_smtp']) ? 1 : 0;
        unset($data['get_mode_radio']);

        // Email type: set defaults for API fields (NOT NULL columns)
        if (isset($data['message_type']) && $data['message_type'] === 'email') {
            if (empty($data['api_url'])) $data['api_url'] = 'SMTP';
            if (empty($data['request_method'])) $data['request_method'] = 'POST';
        }

        // If setting as default, clear others in the same type+subtype
        if ($data['is_default'] == 1) {
            $this->clear_defaults($data['message_type'], $data['message_subtype']);
        }

        $this->db->insert(db_prefix() . 'ccx_msgs_apis', $data);
        $insert_id = $this->db->insert_id();

        if ($insert_id) {
            log_activity('New CCX Msgs API Added [ID: ' . $insert_id . ', Name: ' . $data['api_name'] . ']');
            return $insert_id;
        }
        return false;
    }

    /**
     * Update API configuration
     */
    public function update($data, $id)
    {
        $data['active'] = isset($data['active']) ? 1 : 0;
        $data['is_default'] = isset($data['is_default']) ? 1 : 0;
        $data['use_crm_smtp'] = isset($data['use_crm_smtp']) ? 1 : 0;
        unset($data['get_mode_radio']);

        // Email type: set defaults for API fields (NOT NULL columns)
        if (isset($data['message_type']) && $data['message_type'] === 'email') {
            if (empty($data['api_url'])) $data['api_url'] = 'SMTP';
            if (empty($data['request_method'])) $data['request_method'] = 'POST';
        }

        // If setting as default, clear others in the same type+subtype
        if ($data['is_default'] == 1) {
            $this->clear_defaults($data['message_type'], $data['message_subtype'], $id);
        }

        $this->db->where('id', $id);
        $this->db->update(db_prefix() . 'ccx_msgs_apis', $data);

        if ($this->db->affected_rows() > 0) {
            log_activity('CCX Msgs API Updated [ID: ' . $id . ']');
            return true;
        }
        return false;
    }

    /**
     * Set a specific API as the default for its type+subtype, clearing others
     */
    public function set_default($id)
    {
        $api = $this->get($id);
        if (!$api) {
            return false;
        }

        // Clear all defaults in the same type+subtype
        $this->clear_defaults($api->message_type, $api->message_subtype);

        // Set this one as default
        $this->db->where('id', $id);
        $this->db->update(db_prefix() . 'ccx_msgs_apis', ['is_default' => 1]);

        log_activity('CCX Msgs API Set as Default [ID: ' . $id . ']');
        return true;
    }

    /**
     * Clear all defaults for a given message_type + message_subtype
     * @param int $exclude_id  Optional ID to exclude from clearing
     */
    private function clear_defaults($message_type, $message_subtype, $exclude_id = null)
    {
        $this->db->where('message_type', $message_type);
        $this->db->where('message_subtype', $message_subtype);
        $this->db->where('is_default', 1);
        if ($exclude_id) {
            $this->db->where('id !=', $exclude_id);
        }
        $this->db->update(db_prefix() . 'ccx_msgs_apis', ['is_default' => 0]);
    }

    /**
     * Delete API configuration and its logs
     */
    public function delete($id)
    {
        // Delete associated logs first
        $this->db->where('api_id', $id);
        $this->db->delete(db_prefix() . 'ccx_msgs_api_logs');

        $this->db->where('id', $id);
        $this->db->delete(db_prefix() . 'ccx_msgs_apis');

        if ($this->db->affected_rows() > 0) {
            log_activity('CCX Msgs API Deleted [ID: ' . $id . ']');
            return true;
        }
        return false;
    }

    /**
     * Trigger an API call via cURL and log the result
     * @param int   $id            API config ID
     * @param array $payload_data  Optional dynamic payload data to merge into body template
     * @return array               Result with status, response_code, response_body
     */
    public function trigger_api($id, $payload_data = [])
    {
        $api = $this->get($id);
        if (!$api) {
            return ['status' => 'failed', 'response_code' => 0, 'response_body' => 'API configuration not found.'];
        }

        // ── Email type: send via SMTP instead of cURL ──
        if ($api->message_type === 'email') {
            // Resolve recipient: use email_to_tpl (with tag replacement), fallback to payload 'to'
            $to = '';
            if (!empty($api->email_to_tpl)) {
                $to = $api->email_to_tpl;
                // Replace {tags} in the to-template with payload values
                if (!empty($payload_data)) {
                    foreach ($payload_data as $key => $value) {
                        if (is_string($value) || is_numeric($value)) {
                            $to = str_replace('{' . $key . '}', $value, $to);
                        }
                    }
                }
            }
            if (empty($to) && isset($payload_data['to'])) {
                $to = $payload_data['to'];
            }
            // Use stored templates as fallback
            $subject    = isset($payload_data['subject']) ? $payload_data['subject']
                          : (!empty($api->email_subject_tpl) ? $api->email_subject_tpl : 'No Subject');
            $body       = isset($payload_data['message']) ? $payload_data['message']
                          : (!empty($api->email_body_tpl) ? $api->email_body_tpl : '');
            $from_name  = isset($payload_data['from_name']) ? $payload_data['from_name']
                          : (!empty($api->email_from_name_tpl) ? $api->email_from_name_tpl : '');
            return $this->send_smtp_email($api, $to, $subject, $body, $from_name);
        }

        $url = $api->api_url;
        $method = strtoupper($api->request_method);
        $headers = [];

        // GET with Overall URL mode — use the full URL directly, skip headers/body
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

            // cURL — simple direct request
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

            // Log
            $this->db->insert(db_prefix() . 'ccx_msgs_api_logs', [
                'api_id' => $id,
                'triggered_by' => get_staff_user_id(),
                'request_url' => $url,
                'request_payload' => '',
                'response_code' => $response_code,
                'response_body' => $response_body ? mb_substr($response_body, 0, 65000) : '',
                'status' => $status,
                'execution_time_ms' => $execution_ms,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            return [
                'status' => $status,
                'response_code' => $response_code,
                'response_body' => $response_body,
                'execution_time_ms' => $execution_ms,
                'log_id' => $this->db->insert_id(),
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

        // For GET: also merge headers that should be query params (e.g. authorization for Fast2SMS)
        // and append all params to the URL
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

        // Log the trigger
        $log_data = [
            'api_id' => $id,
            'triggered_by' => get_staff_user_id(),
            'request_url' => $url,
            'request_payload' => $body,
            'response_code' => $response_code,
            'response_body' => $response_body ? mb_substr($response_body, 0, 65000) : '',
            'status' => $status,
            'execution_time_ms' => $execution_ms,
            'created_at' => date('Y-m-d H:i:s'),
        ];
        $this->db->insert(db_prefix() . 'ccx_msgs_api_logs', $log_data);

        return [
            'status' => $status,
            'response_code' => $response_code,
            'response_body' => $response_body,
            'execution_time_ms' => $execution_ms,
            'log_id' => $this->db->insert_id(),
        ];
    }

    /**
     * Send email via SMTP using per-API credentials
     * @param object $api        API config row
     * @param string $to         Recipient email
     * @param string $subject    Email subject
     * @param string $body       HTML body content
     * @param string $from_name  Sender display name
     * @return array
     */
    /**
     * When the API is set to "Use CRM Email Settings", override its SMTP
     * fields with the current installation's Perfex CRM email config
     * (Setup → Settings → Email). Runs at send time, so on a SaaS tenant
     * it uses the TENANT's own working mail settings.
     *
     * @param  object $api
     * @return object  Clone with overrides applied (original untouched)
     */
    public function apply_crm_smtp_overrides($api)
    {
        if (empty($api->use_crm_smtp)) {
            return $api;
        }

        $api = clone $api;

        if (function_exists('ccx_crm_smtp_settings')) {
            // Tenant's own Setup → Settings → Email, with automatic fallback
            // to the MASTER installation's settings when the tenant has none
            $crm = ccx_crm_smtp_settings();
            $api->smtp_host        = $crm['host'];
            $api->smtp_port        = $crm['port'];
            $api->smtp_encryption  = $crm['encryption'];
            $api->smtp_username    = $crm['username'];
            $api->smtp_from_email  = $crm['from_email'];
            // May be stored encrypted — the existing decrypt path handles it
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
            // Stored encrypted by Perfex core — the existing decrypt path handles it
            $api->smtp_password   = get_option('smtp_password');
            $api->crm_smtp_protocol = get_option('email_protocol') ?: 'smtp';
            $api->crm_smtp_source   = 'local';
        }

        $charset = trim(get_option('smtp_email_charset'));
        if ($charset !== '') {
            $api->smtp_email_charset = $charset;
        }

        // 100% parity with core CRM emails (App_mail_template::send() wraps
        // as email_header . message . email_footer): use Setup → Settings →
        // Email header/footer instead of the per-API fields, which the UI
        // disables in this mode. The wrap follows the SAME SOURCE as the
        // SMTP settings resolved above — a send riding on the master's
        // SMTP carries the master's header/footer, a send on the tenant's
        // own SMTP carries the tenant's. Core never auto-appends a
        // signature ({email_signature} is only a template merge field),
        // so the per-API signature is ignored too.
        if (function_exists('ccx_crm_email_wrap_settings')) {
            $wrap = ccx_crm_email_wrap_settings(isset($api->crm_smtp_source) && $api->crm_smtp_source === 'master');
            $api->email_header = $wrap['header'];
            $api->email_footer = $wrap['footer'];
        } else {
            $api->email_header = (string) get_option('email_header');
            $api->email_footer = (string) get_option('email_footer');
        }
        $api->email_signature = '';

        return $api;
    }

    public function send_smtp_email($api, $to, $subject, $body, $from_name = '')
    {
        $start_time = microtime(true);

        // "Use CRM Email Settings" mode: ride on the installation's
        // working Perfex SMTP config instead of API-specific credentials
        $crm_mode = !empty($api->use_crm_smtp);
        $api      = $this->apply_crm_smtp_overrides($api);
        // CRM mode follows the CRM's real protocol (mail/sendmail/google/
        // microsoft/smtp); manual credentials are always plain SMTP
        $crm_protocol = $crm_mode
            ? (isset($api->crm_smtp_protocol) ? $api->crm_smtp_protocol : (get_option('email_protocol') ?: 'smtp'))
            : 'smtp';

        if (empty($to)) {
            $result = [
                'status'           => 'failed',
                'response_code'    => 0,
                'response_body'    => 'Recipient email address is required.',
                'execution_time_ms' => 0,
            ];
            $this->log_smtp_result($api->id, $to, $subject, $result);
            return $result;
        }

        // Fail with a clear reason instead of letting PHPMailer connect to
        // an empty host ('mail'/'sendmail' protocols don't need one)
        $host_required = !$crm_mode || in_array($crm_protocol, ['smtp', 'google', 'microsoft'], true);
        if ($host_required && empty($api->smtp_host)) {
            $result = [
                'status'            => 'failed',
                'response_code'     => 0,
                'response_body'     => $crm_mode
                    ? ('CRM Email Settings mode is enabled but no usable SMTP config was found: this installation\'s Setup → Settings → Email has no SMTP host.'
                        . (!empty($api->crm_smtp_note) ? ' [' . $api->crm_smtp_note . ']' : ''))
                    : 'SMTP host is empty — configure the SMTP fields of this email API.',
                'execution_time_ms' => 0,
            ];
            $this->log_smtp_result($api->id, $to, $subject, $result);
            return $result;
        }

        // Use Perfex's App_mailer with SMTP overrides
        $smtp_encryption = isset($api->smtp_encryption) ? $api->smtp_encryption : '';

        $CI = &get_instance();
        $CI->load->config('email');
        $CI->email->initialize();

        // CRITICAL: Force PHPMailer engine regardless of global Perfex settings.
        // Without this, initialize() may set engine to 'codeigniter' which uses
        // PHP mail() instead of SMTP — silently succeeding but never delivering.
        $CI->email->set_useragent('phpmailer');

        // Override with API-specific SMTP values
        $CI->email->set_smtp_host(isset($api->smtp_host) ? $api->smtp_host : '');
        $CI->email->set_smtp_port(isset($api->smtp_port) ? (int)$api->smtp_port : 587);

        // Username: if blank, use from_email (same as Perfex core email.php config)
        $smtp_user = isset($api->smtp_username) ? $api->smtp_username : '';
        if (empty($smtp_user)) {
            $smtp_user = isset($api->smtp_from_email) ? $api->smtp_from_email : '';
        }
        $CI->email->set_smtp_user($smtp_user);

        // Decrypt stored password (same pattern as Perfex CRM core email.php config)
        $password = isset($api->smtp_password) ? $api->smtp_password : '';
        if (!empty($password)) {
            $decrypted = $CI->encryption->decrypt($password);
            if ($decrypted !== false) {
                $password = $decrypted;
            }
        }
        $CI->email->set_smtp_pass($password);

        // The port disambiguates the TLS mode: 465 is implicit TLS (SMTPS) —
        // 'tls' (STARTTLS) there hangs waiting for a plaintext banner that
        // never comes; and STARTTLS ports can't do an implicit-TLS handshake
        $smtp_port_int = isset($api->smtp_port) ? (int) $api->smtp_port : 0;
        $enc_lower     = strtolower((string) $smtp_encryption);
        if ($smtp_port_int === 465 && $enc_lower !== 'ssl') {
            $smtp_encryption = 'ssl';
        } elseif (in_array($smtp_port_int, [587, 25], true) && $enc_lower === 'ssl') {
            $smtp_encryption = 'tls';
        }

        $CI->email->set_smtp_crypto($smtp_encryption); // empty string = none (Perfex pattern)
        $CI->email->set_protocol($crm_mode ? $crm_protocol : 'smtp');
        // If this installation's CRM email uses Gmail/Microsoft OAuth, the
        // shared email singleton has PHPMailer AuthType locked to XOAUTH2 —
        // reset it when we authenticate with a plain username/password,
        // otherwise the login is attempted with the wrong mechanism
        $plain_smtp = !$crm_mode || $crm_protocol === 'smtp';
        if ($plain_smtp && is_object($CI->email->phpmailer) && $CI->email->phpmailer->AuthType === 'XOAUTH2') {
            $CI->email->phpmailer->AuthType = '';
        }
        // Fail fast instead of hanging for minutes on an unreachable host
        $CI->email->set_smtp_timeout(15);
        $CI->email->set_smtp_auto_tls(false);
        $CI->email->set_smtp_conn_options([
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ],
        ]);
        $CI->email->set_newline(config_item('newline'));
        $CI->email->set_crlf(config_item('crlf'));
        $CI->email->set_mailtype('html');

        // Enable SMTP debug capture for permanent diagnostics in API logs
        $CI->email->set_debug_output(function ($err) {
            if (!isset($GLOBALS['ccx_smtp_debug'])) {
                $GLOBALS['ccx_smtp_debug'] = '';
            }
            $GLOBALS['ccx_smtp_debug'] .= $err . "\n";
            return $err;
        });
        $CI->email->set_smtp_debug(2); // Level 2: commands + data (not as verbose as 3)

        // Per-API charset (default: utf-8)
        $charset = isset($api->smtp_email_charset) && !empty($api->smtp_email_charset) ? $api->smtp_email_charset : 'utf-8';
        $CI->email->set_charset($charset);

        $from_email = isset($api->smtp_from_email) ? $api->smtp_from_email : (isset($api->smtp_username) ? $api->smtp_username : '');
        if (empty($from_name)) {
            $from_name = get_option('companyname');
        }

        $CI->email->from($from_email, $from_name);
        $CI->email->to($to);

        // Per-API BCC
        if (isset($api->bcc_emails) && !empty($api->bcc_emails)) {
            $CI->email->bcc($api->bcc_emails);
        }

        // Wrap body with per-API header, footer, and signature
        $email_header    = isset($api->email_header) && !empty($api->email_header) ? $api->email_header : '';
        $email_footer    = isset($api->email_footer) && !empty($api->email_footer) ? $api->email_footer : '';
        $email_signature = isset($api->email_signature) && !empty($api->email_signature) ? '<br />' . $api->email_signature : '';

        $full_body = $email_header . $body . $email_signature . $email_footer;

        // CRITICAL: Decode HTML entities that may have been stored by XSS filtering.
        // global_xss_filtering encodes <html> → &lt;html&gt; on save, breaking
        // email rendering. Decode them back so PHPMailer sends proper HTML.
        $full_body = html_entity_decode($full_body, ENT_QUOTES, 'UTF-8');
        $subject   = html_entity_decode($subject, ENT_QUOTES, 'UTF-8');

        // Resolve core CRM merge fields ({companyname}, {logo_image_with_url},
        // {crm_url}, …) that email header/footer templates rely on — core
        // parses them per email, so module sends must too. Values come from
        // THIS installation's options: on SaaS the TENANT's company name and
        // logo, even when the wrap HTML fell back to the master's.
        $merge     = $this->crm_other_merge_fields();
        $full_body = str_replace(array_keys($merge), array_values($merge), $full_body);
        $subject   = str_replace(array_keys($merge), array_values($merge), $subject);

        $CI->email->subject($subject);
        $CI->email->message($full_body);

        // Every send that reaches this method is already accounted for by its
        // own caller — a hook, a campaign, an auto-scheduler, an OTP, an API
        // test. The flag tells Omni Messaging's CRM e-mail router (which sits
        // on App_Email::send()) to stay out of the way, so the message is
        // billed and logged once instead of twice.
        $GLOBALS['ccx_omni_email_inflight'] = true;

        try {
            $sent = $CI->email->send(true);
        } catch (Exception $e) {
            $sent = false;
            $GLOBALS['ccx_smtp_debug'] = (isset($GLOBALS['ccx_smtp_debug']) ? $GLOBALS['ccx_smtp_debug'] : '') . "\nException: " . $e->getMessage();
        } finally {
            unset($GLOBALS['ccx_omni_email_inflight']);
        }

        $end_time = microtime(true);
        $execution_ms = round(($end_time - $start_time) * 1000);

        // Capture SMTP debug log for permanent diagnostics
        $smtp_log = isset($GLOBALS['ccx_smtp_debug']) ? trim(strip_tags($GLOBALS['ccx_smtp_debug'])) : '';
        unset($GLOBALS['ccx_smtp_debug']);

        if ($sent) {
            $result = [
                'status'            => 'success',
                'response_code'     => 200,
                'response_body'     => 'Email sent successfully to ' . $to . ($smtp_log ? "\n\n--- SMTP Log ---\n" . $smtp_log : ''),
                'execution_time_ms' => $execution_ms,
            ];
        } else {
            $debugger = $CI->email->print_debugger(['headers', 'subject', 'body']);
            $result = [
                'status'            => 'failed',
                'response_code'     => 0,
                'response_body'     => 'Email sending failed.' . ($smtp_log ? "\n\n--- SMTP Log ---\n" . $smtp_log : '') . "\n\n--- CI Debugger ---\n" . strip_tags($debugger),
                'execution_time_ms' => $execution_ms,
            ];
        }

        $this->log_smtp_result($api->id, $to, $subject, $result);
        return $result;
    }

    /**
     * Mirror of core Other_merge_fields::format() for module email sends.
     * Resolves from the sending installation's options — on SaaS that is
     * the TENANT's company name, logo and URLs, regardless of where the
     * header/footer wrap HTML came from. Unlike core, an empty logo
     * renders as nothing instead of a broken <img>.
     *
     * @return array  '{tag}' => replacement
     */
    private function crm_other_merge_fields()
    {
        $logo_file  = (string) get_option('company_logo');
        $logo_url   = $logo_file !== '' ? base_url('uploads/company/' . $logo_file) : '';
        $logo_width = hooks()->apply_filters('merge_field_logo_img_width', '');
        $width_attr = $logo_width != '' ? ' width="' . e($logo_width) . '"' : '';

        $fields = [
            '{logo_url}'                 => $logo_url,
            '{logo_image_with_url}'      => $logo_url !== '' ? '<a href="' . site_url() . '" target="_blank"><img src="' . $logo_url . '"' . $width_attr . '></a>' : '',
            '{dark_logo_image_with_url}' => '',
            '{crm_url}'                  => rtrim(site_url(), '/'),
            '{admin_url}'                => admin_url(),
            '{main_domain}'              => e((string) get_option('main_domain')),
            '{companyname}'              => e((string) get_option('companyname')),
            '{email_signature}'          => (string) get_option('email_signature'),
            '{terms_and_conditions_url}' => function_exists('terms_url') ? terms_url() : '',
            '{privacy_policy_url}'       => function_exists('privacy_policy_url') ? privacy_policy_url() : '',
        ];

        $dark_logo = (string) get_option('company_logo_dark');
        if ($dark_logo !== '') {
            $fields['{dark_logo_image_with_url}'] = '<a href="' . site_url() . '" target="_blank"><img src="' . base_url('uploads/company/' . $dark_logo) . '"' . $width_attr . '></a>';
        }

        if (!is_html($fields['{email_signature}'])) {
            $fields['{email_signature}'] = nl2br($fields['{email_signature}']);
        }

        return $fields;
    }

    /**
     * Log SMTP email result to api_logs
     */
    private function log_smtp_result($api_id, $to, $subject, $result)
    {
        // Tenant DBs may not have this table (ccx_msgs is master-side) —
        // logging must never kill the send with a DB fatal
        $table = db_prefix() . 'ccx_msgs_api_logs';
        if (!$this->db->table_exists($table)) {
            return;
        }
        try {
            $this->db->insert($table, [
                'api_id'           => $api_id,
                'triggered_by'     => get_staff_user_id(),
                'request_url'      => 'SMTP → ' . $to,
                'request_payload'  => json_encode(['to' => $to, 'subject' => $subject]),
                'response_code'    => $result['response_code'],
                'response_body'    => isset($result['response_body']) ? mb_substr($result['response_body'], 0, 65000) : '',
                'status'           => $result['status'],
                'execution_time_ms' => $result['execution_time_ms'],
                'created_at'       => date('Y-m-d H:i:s'),
            ]);
            $result['log_id'] = $this->db->insert_id();
        } catch (\Throwable $e) {
            // swallow — diagnostics only
        }
    }

    /**
     * Get logs — all or for a specific API
     */
    public function get_logs($api_id = '')
    {
        $this->db->select(db_prefix() . 'ccx_msgs_api_logs.*, ' . db_prefix() . 'ccx_msgs_apis.api_name, ' . db_prefix() . 'ccx_msgs_apis.message_type, ' . db_prefix() . 'ccx_msgs_apis.message_subtype');
        $this->db->join(db_prefix() . 'ccx_msgs_apis', db_prefix() . 'ccx_msgs_apis.id = ' . db_prefix() . 'ccx_msgs_api_logs.api_id', 'left');

        if (is_numeric($api_id)) {
            $this->db->where(db_prefix() . 'ccx_msgs_api_logs.api_id', $api_id);
        }

        $this->db->order_by(db_prefix() . 'ccx_msgs_api_logs.created_at', 'desc');
        return $this->db->get(db_prefix() . 'ccx_msgs_api_logs')->result_array();
    }

    /**
     * Get a single log entry
     */
    public function get_log($id)
    {
        $this->db->select(db_prefix() . 'ccx_msgs_api_logs.*, ' . db_prefix() . 'ccx_msgs_apis.api_name');
        $this->db->join(db_prefix() . 'ccx_msgs_apis', db_prefix() . 'ccx_msgs_apis.id = ' . db_prefix() . 'ccx_msgs_api_logs.api_id', 'left');
        $this->db->where(db_prefix() . 'ccx_msgs_api_logs.id', $id);
        return $this->db->get(db_prefix() . 'ccx_msgs_api_logs')->row();
    }

    /**
     * Delete a single log entry
     */
    public function delete_log($id)
    {
        $this->db->where('id', $id);
        $this->db->delete(db_prefix() . 'ccx_msgs_api_logs');

        if ($this->db->affected_rows() > 0) {
            log_activity('CCX Msgs API Log Deleted [ID: ' . $id . ']');
            return true;
        }
        return false;
    }

    /**
     * Bulk delete log entries
     * @param array $ids Array of log IDs
     * @return int Number of deleted rows
     */
    public function bulk_delete_logs($ids)
    {
        $ids = array_map('intval', $ids);
        $this->db->where_in('id', $ids);
        $this->db->delete(db_prefix() . 'ccx_msgs_api_logs');
        $deleted = $this->db->affected_rows();

        if ($deleted > 0) {
            log_activity('CCX Msgs API Logs Bulk Deleted [Count: ' . $deleted . ']');
        }
        return $deleted;
    }
}
