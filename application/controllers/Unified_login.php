<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Unified_login extends ClientsController
{
    public function __construct()
    {
        parent::__construct();
        // Load encryption for secure token generation
        $this->load->library('encryption');
    }

    public function index()
    {
        // Prevent browser caching so the back button doesn't restore the page
        $this->output->set_header('Last-Modified: ' . gmdate("D, d M Y H:i:s") . ' GMT');
        $this->output->set_header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
        $this->output->set_header('Pragma: no-cache');
        $this->output->set_header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");

        if (perfex_saas_is_tenant()) {
            // For tenants, /login should remain standard behavior (e.g., client portal)
            redirect(site_url('clients/login'));
        }

        if (is_staff_logged_in()) {
            redirect(admin_url());
        }

        // Check if there is a remember cookie for unified login
        $this->load->helper('cookie');
        
        if ($this->input->get('logout') == 'true') {
            delete_cookie('unified_tenant_remember');
            redirect(site_url('login'));
        }

        $remember_cookie = get_cookie('unified_tenant_remember');
        if ($remember_cookie) {
            $decrypted = $this->encryption->decrypt($remember_cookie);
            if ($decrypted) {
                $payload = json_decode($decrypted, true);
                if ($payload && !empty($payload['slug']) && !empty($payload['staff_id'])) {
                    // Automatically redirect to the tenant
                    $this->load->model('perfex_saas/perfex_saas_model');
                    $company = $this->perfex_saas_model->get_company_by_slug($payload['slug']);
                    if ($company && $company->status === 'active') {
                        $this->_redirect_to_tenant_auth($company, $payload['staff_id']);
                        return;
                    }
                }
            }
        }

        $data['matches'] = $this->session->userdata('unified_login_matches');
        $data['title'] = 'Unified Staff Portal';
        $this->load->view('unified_login', $data);
    }

    public function clear_selection()
    {
        $this->session->unset_userdata('unified_login_matches');
        redirect(site_url('login'));
    }

    public function authenticate()
    {
        if (perfex_saas_is_tenant()) {
            redirect(site_url('clients/login'));
        }

        if ($this->input->post()) {
            $email = $this->input->post('email', true);
            $password = $this->input->post('password', false);

            if (empty($email) || empty($password)) {
                set_alert('danger', 'Email and password are required.');
                redirect(site_url('login'));
            }

            // Load saas models
            $this->load->model('perfex_saas/perfex_saas_model');
            
            // Get all active companies
            $this->db->where('status', 'active');
            $companies = $this->db->get(perfex_saas_table('companies'))->result();

            $matched_companies = [];
            $hasher = app_hasher();

            foreach ($companies as $company) {
                // Parse company to get proper DSN and logic
                $company = $this->perfex_saas_model->parse_company($company);
                $dsn = $company->dsn;
                
                if (empty($dsn)) continue;

                if (is_string($dsn)) {
                    $dsn = perfex_saas_parse_dsn($dsn);
                }

                $db_prefix = perfex_saas_tenant_db_prefix($company->slug);
                $query = "SELECT * FROM `{$db_prefix}staff` WHERE `email` = " . $this->db->escape($email) . " AND `active` = 1";
                
                try {
                    $staff = perfex_saas_raw_query_row($query, $dsn, true);
                    if ($staff && !empty($staff->password)) {
                        // Check password
                        if ($hasher->CheckPassword($password, $staff->password)) {
                            // Valid match
                            $matched_companies[] = [
                                'company' => $company,
                                'staff_id' => $staff->staffid,
                                'staff_name' => $staff->firstname . ' ' . $staff->lastname
                            ];
                        }
                    }
                } catch (\Exception $e) {
                    // Ignore individual database connection errors to prevent entire process failure
                    continue;
                }
            }

            if (empty($matched_companies)) {
                set_alert('danger', 'Invalid email or password. No matching staff records found.');
                redirect(site_url('login'));
            }

            if (count($matched_companies) === 1) {
                // Only one match, proceed to login directly
                $match = $matched_companies[0];
                $this->_redirect_to_tenant_auth($match['company'], $match['staff_id']);
            } else {
                // Multiple matches, user must select which tenant to log into
                $this->session->set_userdata('unified_login_matches', $matched_companies);
                redirect(site_url('login'));
            }
        }
    }

    public function select()
    {
        // Prevent browser caching
        $this->output->set_header('Last-Modified: ' . gmdate("D, d M Y H:i:s") . ' GMT');
        $this->output->set_header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
        $this->output->set_header('Pragma: no-cache');
        $this->output->set_header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");

        if (perfex_saas_is_tenant()) {
            redirect(site_url('clients/login'));
        }

        $matches = $this->session->userdata('unified_login_matches');
        if (empty($matches)) {
            redirect(site_url('login'));
        }

        $data['matches'] = $matches;
        $data['title'] = 'Select Company - Unified Portal';
        $this->load->view('unified_login_tenant_select', $data);
    }

    public function process_selection($slug)
    {
        if (perfex_saas_is_tenant()) {
            redirect(site_url('clients/login'));
        }

        $matches = $this->session->userdata('unified_login_matches');
        if (empty($matches)) {
            redirect(site_url('login'));
        }

        foreach ($matches as $match) {
            if ($match['company']->slug === $slug) {
                $this->_redirect_to_tenant_auth($match['company'], $match['staff_id']);
                return;
            }
        }

        set_alert('danger', 'Invalid company selection.');
        redirect(site_url('unified_login/select'));
    }

    private function _redirect_to_tenant_auth($company, $staff_id)
    {
        $payload = [
            'staff_id' => $staff_id,
            'slug' => $company->slug,
            'time' => time()
        ];
        
        $token = $this->encryption->encrypt(json_encode($payload));
        $this->session->unset_userdata('unified_login_matches');

        // Set a remember cookie on the master domain to auto-redirect them next time
        $this->load->helper('cookie');
        $remember_payload = $this->encryption->encrypt(json_encode([
            'staff_id' => $staff_id,
            'slug' => $company->slug
        ]));
        set_cookie([
            'name'   => 'unified_tenant_remember',
            'value'  => $remember_payload,
            'expire' => 86400 * 30, // 30 days
            'secure' => true,
            'httponly' => true
        ]);

        $links = perfex_saas_tenant_base_url($company, '', 'all');
        $support_custom_domain = get_option('perfex_saas_enable_cross_domain_bridge') == "1";
        
        $urlmode = 'subdomain';
        if (!empty($links['custom_domain']) && $support_custom_domain) {
            $urlmode = 'custom_domain';
        }
        if (empty($links[$urlmode])) {
            $urlmode = 'path';
        }

        $redirect_base = rtrim($links[$urlmode], '/');
        $redirect_url = $redirect_base . '/unified_login/tenant_auth?token=' . urlencode($token);
        
        redirect($redirect_url);
    }

    public function tenant_auth()
    {
        if (!perfex_saas_is_tenant()) {
            die('Access denied. This endpoint is strictly for tenant instances.');
        }

        $token = $this->input->get('token');
        if (empty($token)) {
            die('Invalid request. No token provided.');
        }

        $decrypted = $this->encryption->decrypt($token);
        if (!$decrypted) {
            die('Invalid or corrupted security token.');
        }

        $payload = json_decode($decrypted, true);
        if (!$payload || empty($payload['time']) || empty($payload['staff_id']) || empty($payload['slug'])) {
            die('Invalid token payload structure.');
        }

        // Enforce 60-second expiration window
        if (time() - $payload['time'] > 60) {
            die('Security token expired. Please log in again from the main portal.');
        }

        // Enforce correct tenant matching
        if ($payload['slug'] !== perfex_saas_tenant_slug()) {
            die('Security token is not valid for this tenant instance.');
        }

        // Automatically log the staff into the tenant via SaaS helper
        try {
            perfex_saas_tenant_admin_autologin($payload['staff_id']);
            redirect(admin_url());
        } catch (\Exception $e) {
            die('Unified Login failed: ' . $e->getMessage());
        }
    }

    public function debug()
    {
        $email = $this->input->get('email', true);
        $password = $this->input->get('password', false);

        if (empty($email) || empty($password)) {
            die("Please provide ?email=...&password=... in the URL to debug.");
        }

        echo "<pre>Starting debug for email: $email\n\n";

        $this->load->model('perfex_saas/perfex_saas_model');
        $this->db->where('status', 'active');
        $companies = $this->db->get(perfex_saas_table('companies'))->result();

        echo "Total active companies: " . count($companies) . "\n\n";

        $hasher = app_hasher();

        foreach ($companies as $company) {
            echo "----------------------------------------\n";
            echo "Checking Company: " . $company->name . " (Slug: " . $company->slug . ")\n";
            
            $company = $this->perfex_saas_model->parse_company($company);
            $dsn = $company->dsn;
            
            if (empty($dsn)) {
                echo "Result: No DSN found for this company.\n";
                continue;
            }

            if (is_string($dsn)) {
                $dsn = perfex_saas_parse_dsn($dsn);
            }

            echo "DSN Host: " . ($dsn['host'] ?? 'N/A') . ", DB: " . ($dsn['dbname'] ?? 'N/A') . "\n";

            $db_prefix = perfex_saas_tenant_db_prefix($company->slug);
            $query = "SELECT * FROM `{$db_prefix}staff` WHERE `email` = " . $this->db->escape($email) . " AND `active` = 1";
            
            echo "Query: $query\n";
            
            try {
                $staff = perfex_saas_raw_query_row($query, $dsn, true);
                if ($staff) {
                    echo "Result: Staff found in database!\n";
                    echo "Staff ID: " . $staff->staffid . "\n";
                    
                    if (!empty($staff->password) && $hasher->CheckPassword($password, $staff->password)) {
                        echo "Password check: SUCCESS - MATCHED\n";
                    } else {
                        echo "Password check: FAILED - MISMATCH OR EMPTY\n";
                    }
                } else {
                    echo "Result: Staff not found or inactive in this tenant.\n";
                }
            } catch (\Exception $e) {
                echo "Error querying tenant DB: " . $e->getMessage() . "\n";
            }
        }
        
        echo "</pre>";
    }
}
