<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Ccx_login extends AdminController
{
    public function __construct()
    {
        parent::__construct();

        if (!has_permission('settings', '', 'view')) {
            access_denied('CCX Login Settings');
        }
    }

    public function settings()
    {
        $data['title'] = 'CCX Login Settings';

        $posters = get_option('ccx_login_posters');
        $data['posters'] = $posters ? json_decode($posters, true) : [];

        $this->load->view('ccx_login/settings', $data);
    }

    public function upload_poster()
    {
        if (!has_permission('settings', '', 'create') && !has_permission('settings', '', 'edit')) {
            access_denied('CCX Login Settings');
        }

        if (isset($_FILES['poster']) && !empty($_FILES['poster']['name'])) {
            $path = FCPATH . 'modules/ccx_login/uploads/';

            if (!file_exists($path)) {
                mkdir($path, 0755, true);
                $index_file = fopen($path . 'index.html', 'w');
                fclose($index_file);
            }

            $config['upload_path'] = $path;
            $config['allowed_types'] = 'jpg|jpeg|png|gif';
            $config['max_size'] = '5120'; // 5MB
            $config['encrypt_name'] = true;

            $this->load->library('upload', $config);

            if ($this->upload->do_upload('poster')) {
                $upload_data = $this->upload->data();
                $filename = $upload_data['file_name'];

                $posters = get_option('ccx_login_posters');
                $posters = $posters ? json_decode($posters, true) : [];

                // Add new poster
                $posters[] = [
                    'filename' => $filename,
                    'original_name' => $_FILES['poster']['name'],
                    'date_added' => date('Y-m-d H:i:s'),
                    'visible' => true
                ];

                update_option('ccx_login_posters', json_encode($posters));
                set_alert('success', 'Poster uploaded successfully');
            } else {
                set_alert('danger', $this->upload->display_errors('', ''));
            }
        } else {
            set_alert('warning', 'Please select a file to upload');
        }

        redirect(admin_url('ccx_login/settings'));
    }

    public function delete_poster($filename)
    {
        if (!has_permission('settings', '', 'delete')) {
            access_denied('CCX Login Settings');
        }

        $posters = get_option('ccx_login_posters');
        $posters = $posters ? json_decode($posters, true) : [];

        $updated_posters = [];
        $found = false;

        foreach ($posters as $poster) {
            if ($poster['filename'] === $filename) {
                $found = true;
                $path = FCPATH . 'modules/ccx_login/uploads/' . $filename;
                if (file_exists($path)) {
                    unlink($path);
                }
            } else {
                $updated_posters[] = $poster;
            }
        }

        if ($found) {
            update_option('ccx_login_posters', json_encode($updated_posters));
            set_alert('success', 'Poster deleted successfully');
        } else {
            set_alert('warning', 'Poster not found');
        }

        redirect(admin_url('ccx_login/settings'));
    }

    public function save_general_settings()
    {
        if (!has_permission('settings', '', 'edit')) {
            access_denied('CCX Login Settings');
        }

        $split_ratio = $this->input->post('ccx_login_split_ratio');
        $background_size = $this->input->post('ccx_login_background_size');
        $disable_customer_login = $this->input->post('ccx_disable_customer_login');
        $poster_padding = $this->input->post('ccx_login_poster_padding');
        $poster_bg = $this->input->post('ccx_login_poster_bg');

        update_option('ccx_login_split_ratio', $split_ratio);
        update_option('ccx_login_background_size', $background_size);
        update_option('ccx_disable_customer_login', $disable_customer_login);
        update_option('ccx_login_poster_padding', $poster_padding);
        update_option('ccx_login_poster_bg', $poster_bg);

        set_alert('success', 'Settings saved successfully');
        redirect(admin_url('ccx_login/settings'));
    }

    public function toggle_poster_visibility($filename)
    {
        if (!has_permission('settings', '', 'edit')) {
            access_denied('CCX Login Settings');
        }

        $posters = get_option('ccx_login_posters');
        $posters = $posters ? json_decode($posters, true) : [];
        $updated_posters = [];

        foreach ($posters as $poster) {
            if ($poster['filename'] === $filename) {
                // Toggle visibility, default to true if not set
                $current = isset($poster['visible']) ? $poster['visible'] : true;
                $poster['visible'] = !$current;
            }
            $updated_posters[] = $poster;
        }

        update_option('ccx_login_posters', json_encode($updated_posters));
        // No alert needed for toggle usually, or flash message
        redirect(admin_url('ccx_login/settings'));
    }
}
