<?php

defined('BASEPATH') || exit('No direct script access allowed');

/**
 * Banner — Tenant Banner (master control panel).
 *
 * MASTER-ONLY. Never exposed on tenant instances. Lets the provider operator
 * publish a banner to the dashboards of all tenants, or a selected subset. The
 * banner row lives only in the master DB; tenants read it cross-DB at render
 * time (see get_tenant_banner_details() in the banner helper).
 */
class Banner_tenant extends AdminController {
    public function __construct() {
        parent::__construct();

        $this->app_modules->is_inactive('banner') ? access_denied() : '';

        // Master + admin only. Tenants must never reach this controller.
        if (!is_admin()
            || (function_exists('perfex_saas_is_tenant') && perfex_saas_is_tenant())) {
            access_denied('Tenant Banner');
        }

        $this->load->helper('banner');
        $this->load->model('banner/banner_tenant_model', 'tenant_banner');
    }

    /**
     * List + manage tenant banners.
     */
    public function index() {
        $companies = $this->tenant_banner->get_companies();

        $viewData['title']     = _l('tenant_banner');
        $viewData['banners']   = $this->tenant_banner->get_all();
        $viewData['companies'] = array_map(function ($c) {
            return ['slug' => $c->slug, 'name' => $c->name ?? $c->slug];
        }, $companies);

        $this->load->view('tenant_banner', $viewData);
    }

    /**
     * Create or update a tenant banner (multipart, optional image).
     */
    public function save() {
        $id = (int) $this->input->post('id');

        $target_type  = ('selected' === $this->input->post('target_type')) ? 'selected' : 'all';
        $target_slugs = $this->input->post('target_slugs');
        $target_slugs = is_array($target_slugs) ? array_values(array_filter($target_slugs)) : [];

        $has_action = $this->input->post('has_action') ? 1 : 0;

        $data = [
            'title'         => $this->input->post('title', true),
            'start_date'    => to_sql_date($this->input->post('start_date')),
            'end_date'      => to_sql_date($this->input->post('end_date')),
            'admin_area'    => $this->input->post('admin_area') ? 1 : 0,
            'clients_area'  => $this->input->post('clients_area') ? 1 : 0,
            'has_action'    => $has_action,
            'action_label'  => $has_action ? $this->input->post('action_label', true) : '',
            'action_url'    => $has_action ? $this->input->post('action_url') : '',
            'label_color'   => $this->input->post('label_color'),
            'action_target' => $this->input->post('action_target') ? 1 : 0,
            'target_type'   => $target_type,
            'target_slugs'  => ('selected' === $target_type) ? serialize($target_slugs) : '',
        ];

        // If the operator picked "selected" but no tenant, don't publish anywhere.
        if ('selected' === $target_type && empty($target_slugs)) {
            set_alert('warning', _l('tenant_banner_select_tenant'));
            redirect(admin_url('banner/banner_tenant'));
        }

        // Never leave a banner invisible: default to the admin area.
        if (!$data['admin_area'] && !$data['clients_area']) {
            $data['admin_area'] = 1;
        }

        // Handle image upload (required on create, optional on edit).
        $uploaded = $this->_handle_image_upload();
        if ($uploaded) {
            $data['detail']    = $uploaded['filename'];
            $data['image_url'] = $uploaded['url'];
        }

        if (!$id && !$uploaded) {
            set_alert('danger', _l('please_select_image'));
            redirect(admin_url('banner/banner_tenant'));
        }

        $this->tenant_banner->save($data, $id);

        set_alert('success', $id ? _l('banner_update_success') : _l('banner_add_success'));
        redirect(admin_url('banner/banner_tenant'));
    }

    /**
     * Move an uploaded banner image into the shared banner uploads folder and
     * return its filename + absolute (master-domain) URL.
     *
     * @return array{filename:string,url:string}|false
     */
    private function _handle_image_upload() {
        if (empty($_FILES['banner_image']['name'])) {
            return false;
        }

        $extension          = strtolower(pathinfo($_FILES['banner_image']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'bmp', 'webp'];

        if (_perfex_upload_error($_FILES['banner_image']['error'])
            || !in_array($extension, $allowed_extensions)) {
            set_alert('danger', _l('image_extenstion_not_allowed'));
            return false;
        }

        $path = get_upload_path_by_type('banner') . '/';
        _maybe_create_upload_path($path);

        $filename    = unique_filename($path, $_FILES['banner_image']['name']);
        $newFilePath = $path . $filename;

        if (!move_uploaded_file($_FILES['banner_image']['tmp_name'], $newFilePath)) {
            return false;
        }

        return [
            'filename' => $filename,
            'url'      => base_url('uploads/banner/' . $filename),
        ];
    }

    public function delete($id) {
        $this->tenant_banner->delete($id);
        set_alert('danger', _l('banner_deleted'));
        redirect(admin_url('banner/banner_tenant'));
    }

    public function change_status($id, $status) {
        if (!$this->input->is_ajax_request()) {
            ajax_access_denied();
        }

        $this->tenant_banner->change_status($id, $status);
        echo json_encode(['success' => true]);
    }
}
