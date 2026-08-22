<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Ccx_msgs extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        if (!has_permission('ccx_msgs', '', 'view') && !is_admin()) {
            access_denied('ccx_msgs');
        }
        $this->load->model('ccx_msgs_model');
    }

    public function index()
    {
        // The allocations table lists CLIENTS (tenants). This installation is
        // not a client of itself, so its own balance — the one every message
        // sent from THIS account draws on — is kept on the reserved row and
        // surfaced separately above the table.
        $data['self_client_id']  = CCX_MSGS_SELF_CLIENT_ID;
        $data['self_allocation'] = $this->ccx_msgs_model->get_allocation(CCX_MSGS_SELF_CLIENT_ID);

        $data['title'] = _l('ccx_msgs_manage');
        $this->load->view('manage', $data);
    }

    public function table()
    {
        if (!has_permission('ccx_msgs', '', 'view') && !is_admin()) {
            ajax_access_denied();
        }
        $this->app->get_table_data(module_views_path('ccx_msgs', 'tables/ccx_msgs'));
    }

    public function get_allocation_modal($client_id)
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }
        $data['client_id'] = $client_id;
        $data['allocation'] = $this->ccx_msgs_model->get_allocation($client_id);

        if ((int) $client_id === CCX_MSGS_SELF_CLIENT_ID) {
            // This installation's own balance — no client record behind it.
            $data['client'] = (object) ['company' => _l('ccx_msgs_self_account')];
        } else {
            $this->load->model('clients_model');
            $data['client'] = $this->clients_model->get($client_id);
        }

        // "Our WhatsApp" — lending the provider's own WhatsApp number to this
        // tenant is a commercial decision about the same account these credits
        // belong to, so it is configured here rather than in a separate module.
        $data['wa_shared'] = $this->_shared_whatsapp_data($client_id, $this->input->get('slug', true));

        $this->load->view('modals/allocation_modal', $data);
    }

    /**
     * Re-render just the shared-WhatsApp panel for another instance of the same
     * client. Only reachable when a client owns more than one tenant instance.
     */
    public function shared_whatsapp_panel($client_id)
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $shared = $this->_shared_whatsapp_data($client_id, $this->input->get('slug', true));
        if (!$shared) {
            echo '';
            return;
        }

        $this->load->view('modals/_shared_whatsapp', ['wa_shared' => $shared, 'client_id' => $client_id]);
    }

    /**
     * Everything the shared-WhatsApp panel needs, or null when it does not
     * apply: the WhatsApp module is inactive (its bootstrap publishes these
     * functions), this is not a SaaS client, or it owns no tenant instance.
     *
     * @param  int         $client_id
     * @param  string|null $slug      instance to configure (defaults to the first)
     * @return array|null
     */
    private function _shared_whatsapp_data($client_id, $slug = null)
    {
        if (!function_exists('whatsapp_shared_settings') || (int) $client_id <= 0) {
            return null;
        }
        if (!function_exists('perfex_saas_table')) {
            return null;
        }

        $companies_table = perfex_saas_table('companies');
        if (!$this->db->table_exists($companies_table)) {
            return null;
        }

        $companies = $this->db->select('slug, name')
            ->where('clientid', (int) $client_id)
            ->order_by('name', 'ASC')
            ->get($companies_table)->result();

        if (empty($companies)) {
            return null;
        }

        // A slug from the picker is only honoured when it really belongs to
        // this client — otherwise one client could edit another's grant.
        $slugs    = array_map(function ($c) { return $c->slug; }, $companies);
        $slug     = ($slug !== null && in_array($slug, $slugs, true)) ? $slug : $companies[0]->slug;

        // Loading the model also self-heals the WhatsApp schema, so the grant
        // tables exist the first time this modal is opened after the upgrade.
        $this->load->model('whatsapp/whatsapp_model');

        $allowed = [];
        foreach ($this->whatsapp_model->shared_grant_templates($slug) as $t) {
            $allowed[$t->template_name . '|' . $t->language] = true;
        }

        return [
            'settings'  => whatsapp_shared_settings(),
            'companies' => $companies,
            'slug'      => $slug,
            'grant'     => $this->whatsapp_model->shared_grant($slug),
            'allowed'   => $allowed,
            'numbers'   => $this->whatsapp_model->registry_numbers_for_tenant(whatsapp_shared_provider_slug()),
            'templates' => $this->whatsapp_model->shared_provider_templates(),
            'usage'     => $this->whatsapp_model->shared_usage_summary($slug),
            'console'   => admin_url('whatsapp'),
        ];
    }

    /**
     * Persist the shared-WhatsApp grant posted alongside an allocation.
     *
     * A grant row is only created when the switch is actually on — editing a
     * tenant's credits must not litter the provider console with a disabled
     * grant for every client that was ever opened.
     */
    private function _save_shared_whatsapp()
    {
        $slug = trim((string) $this->input->post('wa_shared_slug', true));
        if ($slug === '' || !function_exists('whatsapp_shared_settings')) {
            return;
        }
        if (!is_admin() && !has_permission('ccx_msgs', '', 'edit')) {
            return;
        }

        // Re-resolve the slug against the posted client, never trusting the
        // field on its own.
        $shared = $this->_shared_whatsapp_data($this->input->post('client_id'), $slug);
        if (!$shared || $shared['slug'] !== $slug) {
            return;
        }

        $enabled = (int) $this->input->post('wa_shared_enabled') === 1;
        if (!$enabled && !$shared['grant']) {
            return;
        }

        // A pinned sender must be one of OUR numbers, never a tenant's.
        $number = trim((string) $this->input->post('wa_shared_number', true));
        if ($number !== '') {
            $row = $this->whatsapp_model->registry_get_number($number);
            if (!whatsapp_shared_is_provider_number($row)) {
                $number = '';
            }
        }

        $company = null;
        foreach ($shared['companies'] as $c) {
            if ($c->slug === $slug) {
                $company = $c;
            }
        }

        $this->whatsapp_model->shared_grant_save($slug, [
            'client_id'       => (int) $this->input->post('client_id'),
            'tenant_name'     => $company ? $company->name : $slug,
            'enabled'         => $enabled ? 1 : 0,
            'phone_number_id' => $number,
            'billing_mode'    => $this->input->post('wa_shared_billing', true),
            'daily_limit'     => $this->input->post('wa_shared_daily', true),
            'monthly_limit'   => $this->input->post('wa_shared_monthly', true),
            'allow_send'      => (int) $this->input->post('wa_shared_allow_send'),
            'allow_bulk'      => (int) $this->input->post('wa_shared_allow_bulk'),
            'allow_hooks'     => (int) $this->input->post('wa_shared_allow_hooks'),
            'template_mode'   => $this->input->post('wa_shared_template_mode', true),
            'notes'           => $this->input->post('wa_shared_notes', true),
        ]);

        $this->whatsapp_model->shared_grant_set_templates($slug, (array) $this->input->post('wa_shared_templates'));
    }

    public function save_allocation()
    {
        if ($this->input->post()) {
            $data = $this->input->post();

            // save_allocation() writes this array straight into the allocations
            // table, so anything that is not a column has to come out first.
            // The shared-WhatsApp fields belong to the WhatsApp module's master
            // registry and are persisted separately, below.
            foreach (array_keys($data) as $key) {
                if (strpos($key, 'wa_shared_') === 0) {
                    unset($data[$key]);
                }
            }

            $data['_process_active_fields'] = true; // Sentinel: process active checkboxes
            $success = $this->ccx_msgs_model->save_allocation($data);

            $this->_save_shared_whatsapp();

            if ($this->input->is_ajax_request()) {
                if ($success) {
                    echo json_encode(['success' => true, 'message' => _l('ccx_msgs_allocation_updated')]);
                } else {
                    echo json_encode(['success' => false, 'message' => _l('ccx_msgs_allocation_failed')]);
                }
                die;
            }

            if ($success) {
                set_alert('success', _l('ccx_msgs_allocation_updated'));
            } else {
                set_alert('warning', _l('ccx_msgs_allocation_failed'));
            }
            redirect(admin_url('ccx_msgs'));
        }
    }

    /* Pricing Methods */
    public function pricing()
    {
        $data['title'] = _l('ccx_msgs_pricing');
        $this->load->view('pricing', $data);
    }

    public function pricing_table()
    {
        if (!has_permission('ccx_msgs', '', 'view') && !is_admin()) {
            ajax_access_denied();
        }
        $this->app->get_table_data(module_views_path('ccx_msgs', 'tables/pricing'));
    }

    public function get_plan_modal($id = '')
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $this->load->model('ccx_msgs_pricing_model');
        if (is_numeric($id)) {
            $data['plan'] = $this->ccx_msgs_pricing_model->get($id);
        }

        $this->load->view('modals/plan_modal', $data ?? []);
    }

    public function save_plan()
    {
        if ($this->input->post()) {
            if (!has_permission('ccx_msgs', '', 'edit') && !is_admin() && !has_permission('ccx_msgs', '', 'create')) {
                access_denied('ccx_msgs');
            }

            $data = $this->input->post();
            $id = isset($data['id']) ? $data['id'] : '';
            unset($data['id']);

            $this->load->model('ccx_msgs_pricing_model');
            if ($id == '') {
                $success = $this->ccx_msgs_pricing_model->add($data);
                if ($success) {
                    set_alert('success', _l('ccx_msgs_plan_added'));
                } else {
                    set_alert('warning', _l('ccx_msgs_plan_failed'));
                }
            } else {
                $success = $this->ccx_msgs_pricing_model->update($data, $id);
                if ($success) {
                    set_alert('success', _l('ccx_msgs_plan_updated'));
                }
            }
            redirect(admin_url('ccx_msgs/pricing'));
        }
    }

    public function delete_plan($id)
    {
        if (!has_permission('ccx_msgs', '', 'delete') && !is_admin()) {
            access_denied('ccx_msgs');
        }

        if (!$id) {
            redirect(admin_url('ccx_msgs/pricing'));
        }

        $this->load->model('ccx_msgs_pricing_model');
        $response = $this->ccx_msgs_pricing_model->delete($id);

        if ($response == true) {
            set_alert('success', _l('deleted', _l('pricing_plan')));
        } else {
            set_alert('warning', _l('problem_deleting', _l('pricing_plan')));
        }

        redirect(admin_url('ccx_msgs/pricing'));
    }

    /* ================ APIs Methods ================ */

    public function apis()
    {
        // Self-heal: add use_crm_smtp column without requiring reactivation
        $apis_table = db_prefix() . 'ccx_msgs_apis';
        if ($this->db->table_exists($apis_table) && !$this->db->field_exists('use_crm_smtp', $apis_table)) {
            $this->db->query('ALTER TABLE `' . $apis_table . '` ADD `use_crm_smtp` tinyint(1) NOT NULL DEFAULT 0 COMMENT "1 = use Perfex CRM Setup>Settings>Email SMTP config instead of the fields below" AFTER `client_id`;');
        }

        $data['title'] = _l('ccx_msgs_apis');
        $this->load->view('apis', $data);
    }

    public function apis_table()
    {
        if (!has_permission('ccx_msgs', '', 'view') && !is_admin()) {
            ajax_access_denied();
        }
        $this->app->get_table_data(module_views_path('ccx_msgs', 'tables/apis'));
    }

    public function get_api_modal($id = '')
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $this->load->model('ccx_msgs_api_model');
        if (is_numeric($id)) {
            $data['api'] = $this->ccx_msgs_api_model->get($id);
        }

        // Load clients for the scope dropdown
        $this->db->select('userid, company');
        $this->db->order_by('company', 'asc');
        $data['clients'] = $this->db->get(db_prefix() . 'clients')->result();

        $this->load->view('modals/api_modal', $data ?? []);
    }

    public function save_api()
    {
        if ($this->input->post()) {
            if (!has_permission('ccx_msgs', '', 'edit') && !is_admin() && !has_permission('ccx_msgs', '', 'create')) {
                access_denied('ccx_msgs');
            }

            $data = $this->input->post();
            $id = isset($data['id']) ? $data['id'] : '';
            unset($data['id']);

            // CRITICAL: Re-fetch HTML email fields WITHOUT XSS filtering.
            // global_xss_filtering encodes <html> → &lt;html&gt; which breaks
            // email rendering (recipients see raw HTML tags as text).
            $html_fields = ['email_body_tpl', 'email_header', 'email_footer', 'email_signature'];
            foreach ($html_fields as $hf) {
                $raw_val = $this->input->post($hf, FALSE);
                if ($raw_val !== null) {
                    $data[$hf] = $raw_val;
                }
            }

            // Encrypt SMTP password before saving (same pattern as Perfex CRM core)
            if (isset($data['smtp_password']) && !empty($data['smtp_password'])) {
                $data['smtp_password'] = $this->encryption->encrypt($data['smtp_password']);
            }

            $this->load->model('ccx_msgs_api_model');
            if ($id == '') {
                $success = $this->ccx_msgs_api_model->add($data);
                if ($success) {
                    set_alert('success', _l('ccx_msgs_api_added'));
                } else {
                    set_alert('warning', _l('ccx_msgs_api_failed'));
                }
            } else {
                $success = $this->ccx_msgs_api_model->update($data, $id);
                if ($success) {
                    set_alert('success', _l('ccx_msgs_api_updated'));
                }
            }
            redirect(admin_url('ccx_msgs/apis'));
        }
    }

    public function delete_api($id)
    {
        if (!has_permission('ccx_msgs', '', 'delete') && !is_admin()) {
            access_denied('ccx_msgs');
        }

        if (!$id) {
            redirect(admin_url('ccx_msgs/apis'));
        }

        $this->load->model('ccx_msgs_api_model');
        $response = $this->ccx_msgs_api_model->delete($id);

        if ($response == true) {
            set_alert('success', _l('ccx_msgs_api_deleted'));
        } else {
            set_alert('warning', _l('ccx_msgs_api_delete_failed'));
        }

        redirect(admin_url('ccx_msgs/apis'));
    }

    public function test_api($id)
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $this->load->model('ccx_msgs_api_model');
        $result = $this->ccx_msgs_api_model->trigger_api($id);

        echo json_encode($result);
        die;
    }

    public function test_smtp()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $test_email = $this->input->post('test_email');
        $host       = $this->input->post('smtp_host');
        $port       = $this->input->post('smtp_port') ?: 587;
        $username   = $this->input->post('smtp_username');
        $password   = $this->input->post('smtp_password');
        $encryption = $this->input->post('smtp_encryption');
        $from_email = $this->input->post('smtp_from_email');

        // "Use CRM Email Settings" mode: test with this installation's
        // Perfex email config instead of the manual fields
        $use_crm      = $this->input->post('use_crm_smtp') === '1';
        $crm_protocol = 'smtp';
        if ($use_crm) {
            // Same resolution as the real send: this installation's Setup →
            // Settings → Email, with master fallback on SaaS tenants
            $crm        = ccx_crm_smtp_settings();
            $host       = $crm['host'];
            $port       = $crm['port'];
            $username   = $crm['username'];
            $decrypted  = $this->encryption->decrypt($crm['password']);
            $password   = ($decrypted !== false) ? $decrypted : $crm['password'];
            $encryption = $crm['encryption'];
            $from_email = $crm['from_email'];

            // Respect how the CRM actually sends mail. 'mail'/'sendmail'
            // don't need an SMTP host; 'google'/'microsoft' authenticate
            // via OAuth (configured on the email singleton at boot)
            $crm_protocol = $crm['protocol'];

            if (empty($from_email)) {
                echo json_encode(['success' => false, 'message' => 'CRM Email Settings are not configured (Setup → Settings → Email is missing the email/from address).']);
                die;
            }
            if (empty($host) && in_array($crm_protocol, ['smtp', 'google', 'microsoft'], true)) {
                echo json_encode(['success' => false, 'message' => 'CRM Email Settings are not configured (Setup → Settings → Email has no SMTP host, and no master fallback is available).']);
                die;
            }
        }

        if (empty($test_email) || empty($from_email)) {
            echo json_encode(['success' => false, 'message' => 'Test Email and From Email are required.']);
            die;
        }

        // A hung SMTP handshake must not exceed PHP's execution limit —
        // that kills the request with no JSON reply and the UI looks "stuck"
        @set_time_limit(120);

        $this->load->config('email');
        $this->email->initialize();

        // CRITICAL: Force PHPMailer engine regardless of global Perfex settings.
        // Without this, initialize() may set engine to 'codeigniter' which uses
        // PHP mail() instead of SMTP — silently succeeding but never delivering.
        $this->email->set_useragent('phpmailer');

        // Override with API-specific SMTP values
        $this->email->set_smtp_host($host);
        $this->email->set_smtp_port((int)$port);

        // Username: if blank, use from_email (same as Perfex core email.php config)
        if (empty($username)) {
            $this->email->set_smtp_user($from_email);
        } else {
            $this->email->set_smtp_user($username);
        }

        $this->email->set_smtp_pass($password);

        // The port disambiguates the TLS mode: 465 is implicit TLS (SMTPS) —
        // 'tls' (STARTTLS) there hangs waiting for a plaintext banner that
        // never comes; and STARTTLS ports can't do an implicit-TLS handshake
        $enc_lower = strtolower((string) $encryption);
        if ((int) $port === 465 && $enc_lower !== 'ssl') {
            $encryption = 'ssl';
        } elseif (in_array((int) $port, [587, 25], true) && $enc_lower === 'ssl') {
            $encryption = 'tls';
        }

        $this->email->set_smtp_crypto($encryption);
        // CRM mode follows the CRM's real protocol (mail/sendmail/google/
        // microsoft/smtp); manual credentials are always plain SMTP
        $this->email->set_protocol($use_crm ? $crm_protocol : 'smtp');
        // If this installation's CRM email uses Gmail/Microsoft OAuth, the
        // shared email singleton has PHPMailer AuthType locked to XOAUTH2 —
        // reset it when we authenticate with a plain username/password,
        // otherwise the login is attempted with the wrong mechanism
        $plain_smtp = !$use_crm || $crm_protocol === 'smtp';
        if ($plain_smtp && is_object($this->email->phpmailer) && $this->email->phpmailer->AuthType === 'XOAUTH2') {
            $this->email->phpmailer->AuthType = '';
        }
        // Fail fast instead of hanging for minutes on an unreachable host
        $this->email->set_smtp_timeout(15);
        $this->email->set_mailtype('html');
        $this->email->set_charset('UTF-8');
        $this->email->set_smtp_auto_tls(false);
        $this->email->set_smtp_conn_options([
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ],
        ]);

        // Enable SMTP debug output capture (always, since we forced phpmailer)
        $this->email->set_debug_output(function ($err) {
            if (!isset($GLOBALS['smtp_debug'])) {
                $GLOBALS['smtp_debug'] = '';
            }
            $GLOBALS['smtp_debug'] .= $err . "\n";
            return $err;
        });
        $this->email->set_smtp_debug(3);

        $this->email->set_newline(config_item('newline'));
        $this->email->set_crlf(config_item('crlf'));

        $fromname = get_option('companyname') != '' ? get_option('companyname') : 'CCX Msgs';

        $this->email->from($from_email, $fromname);
        $this->email->to($test_email);
        $this->email->subject('CCX Msgs - SMTP Setup Testing');
        $this->email->message('<html><body><h2>SMTP Test Email</h2><p>This is a test SMTP email from <strong>CCX Msgs</strong>.</p><p>If you received this message, your SMTP settings are configured correctly.</p><br /><p style="color:#888;font-size:12px;">Sent from ' . htmlspecialchars($from_email) . '</p></body></html>');

        try {
            $sent = $this->email->send(true);
        } catch (Exception $e) {
            $sent = false;
            $GLOBALS['smtp_debug'] = (isset($GLOBALS['smtp_debug']) ? $GLOBALS['smtp_debug'] : '') . "\nException: " . $e->getMessage();
        }

        if ($sent) {
            $debugLog = isset($GLOBALS['smtp_debug']) ? $GLOBALS['smtp_debug'] : '';
            echo json_encode([
                'success' => true,
                'message' => 'SMTP connected & test email sent to ' . $test_email,
                'debug'   => trim(strip_tags($debugLog)),
            ]);
        } else {
            $debugLog = isset($GLOBALS['smtp_debug']) ? $GLOBALS['smtp_debug'] : '';
            $debugLog .= $this->email->print_debugger();
            // Clean for JSON display
            $debugLog = strip_tags($debugLog);
            $debugLog = preg_replace('/\n{2,}/', "\n", $debugLog);
            $debugLog = trim($debugLog);
            echo json_encode(['success' => false, 'message' => $debugLog]);
        }
        die;
    }

    public function toggle_default($id)
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        if (!has_permission('ccx_msgs', '', 'edit') && !is_admin()) {
            echo json_encode(['success' => false, 'message' => 'Access denied.']);
            die;
        }

        $this->load->model('ccx_msgs_api_model');
        $result = $this->ccx_msgs_api_model->set_default($id);

        echo json_encode([
            'success' => $result,
            'message' => $result ? _l('ccx_msgs_default_updated') : 'Failed to update default.',
        ]);
        die;
    }

    public function api_logs($api_id = '')
    {
        $data['title'] = _l('ccx_msgs_api_logs');
        $data['api_id'] = $api_id;

        if (is_numeric($api_id)) {
            $this->load->model('ccx_msgs_api_model');
            $data['api'] = $this->ccx_msgs_api_model->get($api_id);
        }

        $this->load->view('api_logs', $data);
    }

    public function api_logs_table()
    {
        if (!has_permission('ccx_msgs', '', 'view') && !is_admin()) {
            ajax_access_denied();
        }
        $this->app->get_table_data(module_views_path('ccx_msgs', 'tables/api_logs'));
    }

    public function get_log_detail($id)
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $this->load->model('ccx_msgs_api_model');
        $log = $this->ccx_msgs_api_model->get_log($id);

        if (!$log) {
            echo json_encode(['success' => false, 'message' => 'Log not found.']);
            die;
        }

        echo json_encode([
            'success' => true,
            'api_name' => $log->api_name,
            'request_url' => $log->request_url,
            'request_payload' => $log->request_payload,
            'response_code' => $log->response_code,
            'response_body' => mb_substr($log->response_body ?? '', 0, 5000),
            'status' => $log->status,
            'execution_time_ms' => $log->execution_time_ms,
            'created_at' => _dt($log->created_at),
        ]);
        die;
    }

    public function delete_api_log($id)
    {
        if (!has_permission('ccx_msgs', '', 'delete') && !is_admin()) {
            access_denied('ccx_msgs');
        }

        if (!$id) {
            redirect(admin_url('ccx_msgs/api_logs'));
        }

        $this->load->model('ccx_msgs_api_model');

        // Get the log's api_id before deleting, so we can redirect back
        $log = $this->ccx_msgs_api_model->get_log($id);
        $api_id = $log ? $log->api_id : '';

        $response = $this->ccx_msgs_api_model->delete_log($id);

        if ($response) {
            set_alert('success', _l('ccx_msgs_log_deleted'));
        } else {
            set_alert('warning', _l('ccx_msgs_log_delete_failed'));
        }

        redirect(admin_url('ccx_msgs/api_logs/' . $api_id));
    }

    public function bulk_delete_api_logs()
    {
        if (!$this->input->is_ajax_request() || !$this->input->post()) {
            show_404();
        }

        if (!has_permission('ccx_msgs', '', 'delete') && !is_admin()) {
            echo json_encode(['success' => false, 'message' => 'Access denied.']);
            die;
        }

        $ids = $this->input->post('ids');
        if (empty($ids) || !is_array($ids)) {
            echo json_encode(['success' => false, 'message' => 'No logs selected.']);
            die;
        }

        $this->load->model('ccx_msgs_api_model');
        $deleted = $this->ccx_msgs_api_model->bulk_delete_logs($ids);

        echo json_encode([
            'success' => true,
            'message' => $deleted . ' log(s) deleted successfully.',
        ]);
        die;
    }

    public function recharge_logs()
    {
        $data['title'] = _l('ccx_msgs_recharge_logs');
        $this->load->view('recharge_logs', $data);
    }

    public function recharge_logs_table()
    {
        if (!has_permission('ccx_msgs', '', 'view') && !is_admin()) {
            ajax_access_denied();
        }
        $this->app->get_table_data(module_views_path('ccx_msgs', 'tables/recharge_logs'));
    }

    // ═══ Promo & Referral Codes ═══

    public function promo_codes()
    {
        if (!has_permission('ccx_msgs', '', 'view') && !is_admin()) {
            access_denied('ccx_msgs');
        }
        $data['title'] = _l('ccx_msgs_promo_codes');
        $this->load->view('promo_codes', $data);
    }

    public function promo_codes_table()
    {
        if (!has_permission('ccx_msgs', '', 'view') && !is_admin()) {
            ajax_access_denied();
        }
        $this->app->get_table_data(module_views_path('ccx_msgs', 'tables/promo_codes'));
    }

    public function promo_code_save()
    {
        if (!has_permission('ccx_msgs', '', 'create') && !is_admin()) {
            access_denied('ccx_msgs');
        }

        $data = $this->input->post();
        $id   = isset($data['id']) ? (int) $data['id'] : 0;
        unset($data['id']);

        // Sanitize
        $data['code'] = strtoupper(trim($data['code']));
        $data['discount_value'] = (float) ($data['discount_value'] ?? 0);
        $data['min_order_amount'] = (float) ($data['min_order_amount'] ?? 0);
        $data['max_discount_amount'] = (float) ($data['max_discount_amount'] ?? 0);
        $data['usage_limit'] = (int) ($data['usage_limit'] ?? 0);
        $data['per_client_limit'] = (int) ($data['per_client_limit'] ?? 0);
        $data['referrer_reward_credits'] = (int) ($data['referrer_reward_credits'] ?? 0);
        $data['active'] = isset($data['active']) ? 1 : 0;

        // Channels
        if (isset($data['applicable_channels']) && is_array($data['applicable_channels'])) {
            $data['applicable_channels'] = json_encode($data['applicable_channels']);
        } else {
            $data['applicable_channels'] = json_encode(['all']);
        }

        // Referrer
        if ($data['code_type'] !== 'referral') {
            $data['referrer_type'] = 'client';
            $data['referrer_client_id'] = null;
            $data['referrer_staff_id'] = null;
            $data['referrer_reward_credits'] = 0;
            $data['referrer_reward_channel'] = null;
        } else {
            $referrer_type = isset($data['referrer_type']) ? $data['referrer_type'] : 'client';
            $data['referrer_type'] = $referrer_type;
            if ($referrer_type === 'staff') {
                $data['referrer_client_id'] = null;
            } else {
                $data['referrer_staff_id'] = null;
            }
        }

        // Empty dates → NULL
        if (empty($data['valid_from'])) $data['valid_from'] = null;
        if (empty($data['valid_until'])) $data['valid_until'] = null;
        if (empty($data['referrer_client_id'])) $data['referrer_client_id'] = null;
        if (empty($data['referrer_staff_id'])) $data['referrer_staff_id'] = null;
        if (empty($data['referrer_reward_channel'])) $data['referrer_reward_channel'] = null;

        $table = db_prefix() . 'ccx_msgs_promo_codes';

        if ($id > 0) {
            $this->db->where('id', $id);
            $this->db->update($table, $data);
            set_alert('success', 'Promo code updated successfully.');
        } else {
            // Check duplicate
            $existing = $this->db->where('code', $data['code'])->get($table)->row();
            if ($existing) {
                set_alert('danger', 'A promo code with this code already exists.');
                redirect(admin_url('ccx_msgs/promo_codes'));
                return;
            }
            $data['created_at'] = date('Y-m-d H:i:s');
            $this->db->insert($table, $data);
            set_alert('success', 'Promo code created successfully.');
        }

        redirect(admin_url('ccx_msgs/promo_codes'));
    }

    public function promo_code_delete($id)
    {
        if (!has_permission('ccx_msgs', '', 'delete') && !is_admin()) {
            access_denied('ccx_msgs');
        }
        $this->db->where('id', $id)->delete(db_prefix() . 'ccx_msgs_promo_codes');
        set_alert('success', 'Promo code deleted.');
        redirect(admin_url('ccx_msgs/promo_codes'));
    }

    public function promo_code_get($id)
    {
        if (!has_permission('ccx_msgs', '', 'view') && !is_admin()) {
            ajax_access_denied();
        }
        header('Content-Type: application/json');
        $code = $this->db->where('id', $id)->get(db_prefix() . 'ccx_msgs_promo_codes')->row();
        echo json_encode($code ? $code : []);
        die;
    }

    // ═══ Coupons (Free Credits) ═══

    public function coupons()
    {
        if (!has_permission('ccx_msgs', '', 'view') && !is_admin()) {
            access_denied('ccx_msgs');
        }
        $data['title'] = _l('ccx_msgs_coupons');
        $this->load->view('coupons', $data);
    }

    public function coupons_table()
    {
        if (!has_permission('ccx_msgs', '', 'view') && !is_admin()) {
            ajax_access_denied();
        }
        $this->app->get_table_data(module_views_path('ccx_msgs', 'tables/coupons'));
    }

    public function coupon_save()
    {
        if (!has_permission('ccx_msgs', '', 'create') && !is_admin()) {
            access_denied('ccx_msgs');
        }

        $data = $this->input->post();
        $id   = isset($data['id']) ? (int) $data['id'] : 0;
        unset($data['id']);

        // Sanitize
        $data['code'] = strtoupper(trim($data['code']));
        $data['description'] = trim($data['description'] ?? '');
        $data['expiry_days'] = (int) ($data['expiry_days'] ?? 0);
        $data['usage_limit'] = (int) ($data['usage_limit'] ?? 0);
        $data['per_client_limit'] = (int) ($data['per_client_limit'] ?? 1);
        $data['active'] = isset($data['active']) ? 1 : 0;

        // Build credits JSON from individual channel inputs
        $channels = ['sms', 'whatsapp', 'email', 'aicall'];
        $credits = [];
        foreach ($channels as $ch) {
            $val = (int) ($data['credit_' . $ch] ?? 0);
            if ($val > 0) {
                $credits[$ch] = $val;
            }
            unset($data['credit_' . $ch]);
        }
        $data['credits'] = json_encode($credits);

        // Empty dates → NULL
        if (empty($data['valid_from'])) $data['valid_from'] = null;
        if (empty($data['valid_until'])) $data['valid_until'] = null;

        $table = db_prefix() . 'ccx_msgs_coupons';

        if ($id > 0) {
            $this->db->where('id', $id);
            $this->db->update($table, $data);
            set_alert('success', 'Coupon updated successfully.');
        } else {
            // Check duplicate
            $existing = $this->db->where('code', $data['code'])->get($table)->row();
            if ($existing) {
                set_alert('danger', 'A coupon with this code already exists.');
                redirect(admin_url('ccx_msgs/coupons'));
                return;
            }
            $data['created_at'] = date('Y-m-d H:i:s');
            $this->db->insert($table, $data);
            set_alert('success', 'Coupon created successfully.');
        }

        redirect(admin_url('ccx_msgs/coupons'));
    }

    public function coupon_get($id)
    {
        if (!has_permission('ccx_msgs', '', 'view') && !is_admin()) {
            ajax_access_denied();
        }
        header('Content-Type: application/json');
        $coupon = $this->db->where('id', $id)->get(db_prefix() . 'ccx_msgs_coupons')->row();
        echo json_encode($coupon ? $coupon : []);
        die;
    }

    public function coupon_delete($id)
    {
        if (!has_permission('ccx_msgs', '', 'delete') && !is_admin()) {
            access_denied('ccx_msgs');
        }
        $this->db->where('id', $id)->delete(db_prefix() . 'ccx_msgs_coupons');
        // Also delete claims
        $this->db->where('coupon_id', $id)->delete(db_prefix() . 'ccx_msgs_coupon_claims');
        set_alert('success', 'Coupon deleted.');
        redirect(admin_url('ccx_msgs/coupons'));
    }

    public function coupon_claims($coupon_id)
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }
        if (!has_permission('ccx_msgs', '', 'view') && !is_admin()) {
            ajax_access_denied();
        }
        header('Content-Type: application/json');

        $this->db->select('cl.*, c.company as client_name');
        $this->db->from(db_prefix() . 'ccx_msgs_coupon_claims cl');
        $this->db->join(db_prefix() . 'clients c', 'c.userid = cl.client_id', 'left');
        $this->db->where('cl.coupon_id', $coupon_id);
        $this->db->order_by('cl.claimed_at', 'DESC');
        $claims = $this->db->get()->result();

        echo json_encode($claims);
        die;
    }
}
