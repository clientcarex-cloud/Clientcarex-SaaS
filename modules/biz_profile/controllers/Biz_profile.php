<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Biz_profile extends AdminController
{
    public function __construct()
    {
        parent::__construct();

        // Business Profile is superadmin-only
        if (!is_admin()) {
            access_denied('biz_profile');
        }

        $this->load->model('settings_model');
    }

    public function index()
    {
        if ($this->input->post()) {
            $post_data = $this->input->post();

            // Handle file uploads
            $logo_uploaded       = (handle_company_logo_upload() ? true : false);
            $favicon_uploaded    = (handle_favicon_upload() ? true : false);
            $letterhead_uploaded = (handle_company_letterhead_upload() ? true : false);

            $success = $this->settings_model->update($post_data);

            if ($success > 0 || $logo_uploaded || $favicon_uploaded || $letterhead_uploaded) {
                set_alert('success', _l('settings_updated'));
            }

            if ($logo_uploaded || $favicon_uploaded) {
                set_debug_alert(_l('logo_favicon_changed_notice'));
            }

            redirect(admin_url('biz_profile'));
        }

        $data['title'] = 'Business Profile';

        // Contacts panel — tenant manages the contacts on its own master client
        // record (cross-DB read of the master tblcontacts). Only available on a
        // deployed tenant instance where we can resolve the master client id.
        $data['biz_client_id'] = $this->biz_master_client_id();
        $data['biz_contacts']  = $data['biz_client_id'] ? $this->biz_get_contacts($data['biz_client_id']) : [];

        $this->load->view('manage', $data);
    }

    /**
     * Add a contact to this tenant's master client record.
     *
     * Contacts are informational only (name/email/phone/title) — no portal
     * password, welcome email or permissions are set from the tenant side. The
     * row is written cross-DB into the master tblcontacts for the tenant's own
     * client id and is always added as non-primary so the billing primary
     * contact set by the provider is never disturbed.
     */
    public function add_contact()
    {
        if (!$this->input->post()) {
            redirect(admin_url('biz_profile'));
        }

        $client_id = $this->biz_master_client_id();
        if (!$client_id) {
            set_alert('danger', _l('access_denied'));
            redirect(admin_url('biz_profile'));
        }

        $firstname = trim($this->input->post('firstname', true));
        $lastname  = trim($this->input->post('lastname', true));
        $email     = trim($this->input->post('email', true));
        $phone     = trim($this->input->post('phonenumber', true));
        $title     = trim($this->input->post('title', true));

        // Validation
        if ($firstname === '' || $email === '') {
            set_alert('warning', _l('client_firstname') . ' & ' . _l('client_email') . ' are required.');
            redirect(admin_url('biz_profile'));
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            set_alert('warning', 'Please enter a valid email address.');
            redirect(admin_url('biz_profile'));
        }

        // Email must be unique across master contacts (Perfex requirement)
        if ($this->biz_contact_email_exists($email)) {
            set_alert('warning', _l('email_exists'));
            redirect(admin_url('biz_profile'));
        }

        $table = perfex_saas_master_db_prefix() . 'contacts';
        $now   = date('Y-m-d H:i:s');

        $result = perfex_saas_raw_query(
            "INSERT INTO `{$table}`
                (userid, is_primary, firstname, lastname, email, phonenumber, title, datecreated, email_verified_at, active,
                 invoice_emails, estimate_emails, credit_note_emails, contract_emails, task_emails, project_emails, ticket_emails)
             VALUES
                (:uid, 0, :fn, :ln, :em, :ph, :ti, :dc, :ev, 1, 0, 0, 0, 0, 0, 0, 0)",
            [],
            false,
            true,
            null,
            false,
            true,
            [
                ':uid' => $client_id,
                ':fn'  => $firstname,
                ':ln'  => $lastname,
                ':em'  => $email,
                ':ph'  => $phone,
                ':ti'  => ($title === '' ? null : $title),
                ':dc'  => $now,
                ':ev'  => $now,
            ]
        );

        if (is_array($result) && !empty($result[0])) {
            set_alert('success', _l('added_successfully', _l('contact')));
        } else {
            set_alert('danger', _l('something_went_wrong'));
        }

        redirect(admin_url('biz_profile'));
    }

    /**
     * Resolve the master client id for the current tenant instance.
     *
     * @return int  Master client id, or 0 when not a resolvable tenant (e.g. on the master install).
     */
    private function biz_master_client_id()
    {
        if (!function_exists('perfex_saas_tenant') || !function_exists('perfex_saas_raw_query')) {
            return 0;
        }

        $tenant = perfex_saas_tenant();

        return ($tenant && !empty($tenant->clientid)) ? (int) $tenant->clientid : 0;
    }

    /**
     * Fetch the contacts on the tenant's master client record (cross-DB).
     *
     * @param int $client_id
     * @return array
     */
    private function biz_get_contacts($client_id)
    {
        $table = perfex_saas_master_db_prefix() . 'contacts';

        $rows = perfex_saas_raw_query(
            "SELECT id, firstname, lastname, email, phonenumber, title, is_primary, active, datecreated
             FROM `{$table}`
             WHERE userid = :uid
             ORDER BY is_primary DESC, id ASC",
            [],
            true,
            true,
            null,
            false,
            true,
            [':uid' => (int) $client_id]
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * Check whether an email already exists on any master contact.
     *
     * @param string $email
     * @return bool
     */
    private function biz_contact_email_exists($email)
    {
        $table = perfex_saas_master_db_prefix() . 'contacts';

        $row = perfex_saas_raw_query_row(
            "SELECT id FROM `{$table}` WHERE email = :em LIMIT 1",
            [],
            true,
            true,
            [':em' => $email]
        );

        return !empty($row);
    }

    public function remove_company_logo($type = '')
    {
        hooks()->do_action('before_remove_company_logo');

        $logoName = get_option('company_logo');
        if ($type == 'dark') {
            $logoName = get_option('company_logo_dark');
        }

        $path = get_upload_path_by_type('company') . '/' . $logoName;
        if ($logoName != '' && file_exists($path)) {
            unlink($path);
        }

        update_option('company_logo' . ($type == 'dark' ? '_dark' : ''), '');
        redirect(admin_url('biz_profile'));
    }

    public function remove_fv()
    {
        hooks()->do_action('before_remove_favicon');

        $favicon = get_option('favicon');
        if ($favicon != '' && file_exists(get_upload_path_by_type('company') . '/' . $favicon)) {
            unlink(get_upload_path_by_type('company') . '/' . $favicon);
        }

        update_option('favicon', '');
        redirect(admin_url('biz_profile'));
    }

    public function remove_letterhead()
    {
        $letterhead = get_option('company_letterhead');
        if ($letterhead != '') {
            $path = get_upload_path_by_type('company') . '/' . $letterhead;
            if (file_exists($path)) {
                unlink($path);
            }
        }

        update_option('company_letterhead', '');
        redirect(admin_url('biz_profile'));
    }
}
