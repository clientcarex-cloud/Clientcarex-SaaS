<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Core_crm extends AdminController
{
    public function __construct()
    {
        parent::__construct();

        // Ensure shared helpers are available to this controller and its views,
        // regardless of whether the module bootstrap ran first on this tenant.
        require_once __DIR__ . '/../helpers/core_crm_helper.php';

        if (!is_admin()) {
            access_denied('Core CRM Settings');
        }
    }

    public function index()
    {
        if (!$this->session->has_userdata('core_crm_authenticated')) {
            if ($this->input->post('password')) {
                if ($this->input->post('password') === '&*73hgh%g') {
                    $this->session->set_userdata('core_crm_authenticated', true);
                    redirect(admin_url('core_crm'));
                } else {
                    set_alert('danger', 'Invalid Password');
                    redirect(admin_url('core_crm'));
                }
            }

            $data['title'] = 'Authentication Required';
            $this->load->view('core_crm/auth', $data);
            return;
        }

        $data['title'] = 'Core CRM Settings';

        if ($this->input->post()) {
            $menus = array_keys(core_crm_managed_menus());

            // Normal Save
            foreach ($menus as $menu) {
                $val_hide = $this->input->post('hide_' . $menu) ? '1' : '0';
                $hide_key = 'core_crm_hide_' . $menu;
                if (get_option($hide_key) === false) {
                    add_option($hide_key, $val_hide);
                } else {
                    update_option($hide_key, $val_hide);
                }

                $val_restrict = $this->input->post('restrict_' . $menu) ? '1' : '0';
                $restrict_key = 'core_crm_restrict_' . $menu;
                if (get_option($restrict_key) === false) {
                    add_option($restrict_key, $val_restrict);
                } else {
                    update_option($restrict_key, $val_restrict);
                }
            }

            set_alert('success', _l('updated_successfully', 'Settings'));
            redirect(admin_url('core_crm'));
        }

        $this->load->view('core_crm/settings', $data);
    }
}
