<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Ccx_theme extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->helper('ccx_theme');
        if (!has_permission('settings', '', 'view')) {
            access_denied('CCX Theme');
        }
    }

    public function index()
    {
        $data['title'] = 'CCX Theme Options';

        if ($this->input->post()) {
            $data = $this->input->post();
            // Loop through all post data and save as options
            foreach ($data as $key => $val) {
                // Determine if it is a color option, maybe need sanitization?
                // For now, trusting admin input as standard Perfex settings do.
                update_option($key, $val);
            }
            set_alert('success', _l('settings_updated'));
            redirect(admin_url('ccx_theme'));
        }

        $this->load->view('theme_options', $data);
    }
}
