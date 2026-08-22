<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Team extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('staff_model');
        $this->load->model('roles_model');
    }

    /**
     * Get staff quota information from the SaaS module.
     * Returns an array with quota details or a "no limit" fallback.
     */
    private function _get_staff_quota_info()
    {
        $info = [
            'is_saas'       => false,
            'quota'         => -1,
            'usage'         => 0,
            'is_unlimited'  => true,
            'limit_reached' => false,
            'upgrade_url'   => '#',
        ];

        // Check if SaaS module functions are available
        if (!function_exists('perfex_saas_is_tenant') || !perfex_saas_is_tenant()) {
            return $info;
        }

        $tenant = perfex_saas_tenant();
        if (!$tenant) {
            return $info;
        }

        $info['is_saas'] = true;

        // Get staff quota from the package
        $quota = perfex_saas_tenant_resources_quota($tenant, 'staff');
        $info['quota'] = $quota;
        $info['is_unlimited'] = ($quota === -1);

        // Get current staff usage
        $usage_list = perfex_saas_get_tenant_quota_usage($tenant, ['staff']);
        $info['usage'] = isset($usage_list['staff']) ? (int)$usage_list['staff'] : 0;

        // Check if limit is reached
        if (!$info['is_unlimited']) {
            $info['limit_reached'] = ($info['usage'] >= $info['quota']);
        }

        // Build upgrade URL via the client bridge
        if (function_exists('perfex_saas_tenant_is_enabled') && perfex_saas_tenant_is_enabled('client_bridge')) {
            $info['upgrade_url'] = admin_url('billing/my_account?redirect=clients/?companies');
        } else {
            // Fallback to direct SaaS client portal
            $info['upgrade_url'] = function_exists('perfex_saas_default_base_url')
                ? perfex_saas_default_base_url('clients/my_account')
                : '#';
        }

        return $info;
    }

    public function index()
    {
        if (!has_permission('team', '', 'view')) {
            access_denied('team');
        }

        if ($this->input->is_ajax_request()) {
            $this->load->view('table_data');
            return;
        }

        $data['staff_members'] = $this->staff_model->get('', ['active' => 1]);
        $data['title'] = 'Team Management';
        $data['quota_info'] = $this->_get_staff_quota_info();
        $this->load->view('manage', $data);
    }

    public function member($id = '')
    {
        if (!has_permission('team', '', 'view') && !has_permission('team', '', 'create') && !has_permission('team', '', 'edit')) {
            access_denied('team');
        }
        // Note: above uses && intentionally - deny only if user has NONE of the three

        $this->load->model('departments_model');

        if ($this->input->post()) {
            $data = $this->input->post();
            if (isset($data['isedit'])) {
                unset($data['isedit']);
            }
            if (isset($data['memberid'])) {
                unset($data['memberid']);
            }
            // Guard: strip max_discount if the column doesn't exist yet
            // (e.g. on the master SaaS account where the patients module may not be active)
            if (isset($data['max_discount'])) {
                if (!$this->db->field_exists('max_discount', db_prefix() . 'staff')) {
                    unset($data['max_discount']);
                } elseif ($data['max_discount'] === '') {
                    // Convert empty max_discount to NULL (no limit = 100%)
                    $data['max_discount'] = NULL;
                }
            }
            if ($id == '') {
                if (!has_permission('team', '', 'create')) {
                    access_denied('team');
                }
                // Check quota before creating
                $quota_info = $this->_get_staff_quota_info();
                if ($quota_info['limit_reached']) {
                    set_alert('danger', 'Users limit reached. Please upgrade your plan to add more team members.');
                    redirect(admin_url('team'));
                }
                $id = $this->staff_model->add($data);
                if ($id) {
                    handle_staff_profile_image_upload($id);
                    set_alert('success', _l('added_successfully', _l('staff_member')));
                    redirect(admin_url('team/member/' . $id));
                }
            } else {
                if (!has_permission('team', '', 'edit')) {
                    access_denied('team');
                }
                handle_staff_profile_image_upload($id);
                $response = $this->staff_model->update($data, $id);
                if (is_array($response)) {
                    // Staff_model::update() returns array on admin-protection errors
                    if (isset($response['cant_remove_main_admin'])) {
                        set_alert('warning', _l('staff_cant_remove_main_admin'));
                    } elseif (isset($response['cant_remove_yourself_from_admin'])) {
                        set_alert('warning', _l('staff_cant_remove_yourself_from_admin'));
                    }
                } elseif ($response == true) {
                    set_alert('success', _l('updated_successfully', _l('staff_member')));
                }
                redirect(admin_url('team/member/' . $id));
            }
        }

        // Pass quota info to the member view
        $data['quota_info'] = $this->_get_staff_quota_info();

        if ($id == '') {
            $title = _l('add_new', _l('staff_member'));
        } else {
            $member = $this->staff_model->get($id);
            if (!$member) {
                blank_page('Staff Member Not Found', 'danger');
            }
            $data['member'] = $member;
            $title = $member->firstname . ' ' . $member->lastname;
            $data['staff_departments'] = $this->departments_model->get_staff_departments($member->staffid);
        }

        $this->load->model('currencies_model');
        $this->load->model('misc_model');
        $data['base_currency'] = $this->currencies_model->get_base_currency();
        $data['roles'] = $this->roles_model->get();
        $data['user_notes'] = ($id != '') ? $this->misc_model->get_notes($id, 'staff') : [];
        $data['departments'] = $this->departments_model->get();
        $data['title'] = $title;

        // Check for preset role from query string (e.g. ?role=MR from MRS module)
        $preset_role_name = $this->input->get('role');
        if ($preset_role_name && $id == '') {
            $this->db->where('name', $preset_role_name);
            $preset_role = $this->db->get(db_prefix() . 'roles')->row();
            if ($preset_role) {
                $data['preset_role'] = $preset_role->roleid;
            }
        }

        $this->load->view('member', $data);
    }

    public function delete($id)
    {
        if (!has_permission('team', '', 'delete')) {
            access_denied('Delete Staff');
        }

        // Check if we are trying to delete the current user or admin
        if ($id == get_staff_user_id() || $id == 1) { // Assuming ID 1 is main admin, or check is_admin($id) logic from staff controller
            set_alert('warning', _l('staff_cant_remove_main_admin'));
            redirect(admin_url('team'));
        }

        $success = $this->staff_model->delete($id, $this->input->post('transfer_data_to'));
        if ($success) {
            set_alert('success', _l('deleted', _l('staff_member')));
        }
        redirect(admin_url('team'));
    }
    public function roles()
    {
        if (!has_permission('team_roles', '', 'view')) {
            access_denied('team_roles');
        }
        if ($this->input->is_ajax_request()) {
            $this->load->view('role_table_data');
            return;
        }
        $data['title'] = _l('all_roles');
        $this->load->view('roles', $data);
    }

    public function role($id = '')
    {
        if (!has_permission('team_roles', '', 'view') && !has_permission('team_roles', '', 'create') && !has_permission('team_roles', '', 'edit')) {
            access_denied('team_roles');
        }
        if ($this->input->post()) {
            if ($id == '') {
                if (!has_permission('team_roles', '', 'create')) {
                    access_denied('team_roles');
                }
                $id = $this->roles_model->add($this->input->post());
                if ($id) {
                    set_alert('success', _l('added_successfully', _l('role')));
                    redirect(admin_url('team/role/' . $id));
                }
            } else {
                if (!has_permission('team_roles', '', 'edit')) {
                    access_denied('team_roles');
                }
                $success = $this->roles_model->update($this->input->post(), $id);
                if ($success) {
                    set_alert('success', _l('updated_successfully', _l('role')));
                }
                redirect(admin_url('team/role/' . $id));
            }
        }
        if ($id == '') {
            $title = _l('add_new', _l('role'));
        } else {
            $data['role_staff'] = $this->roles_model->get_role_staff($id);
            $role = $this->roles_model->get($id);
            $data['role'] = $role;
            $title = _l('edit', _l('role')) . ' ' . $role->name;
        }
        $data['title'] = $title;
        $this->load->view('role', $data);
    }

    public function delete_role($id)
    {
        if (!has_permission('team_roles', '', 'delete')) {
            access_denied('team_roles');
        }
        if (!$id) {
            redirect(admin_url('team/roles'));
        }
        $response = $this->roles_model->delete($id);
        if (is_array($response) && isset($response['referenced'])) {
            set_alert('warning', _l('is_referenced', _l('role_lowercase')));
        } elseif ($response == true) {
            set_alert('success', _l('deleted', _l('role')));
        } else {
            set_alert('warning', _l('problem_deleting', _l('role_lowercase')));
        }
        redirect(admin_url('team/roles'));
    }

    public function visibility_settings()
    {
        if (!has_permission('team', '', 'view')) {
            access_denied('team');
        }

        if ($this->input->post()) {
            $hidden_roles = $this->input->post('hidden_roles');
            if (!is_array($hidden_roles)) {
                $hidden_roles = [];
            }
            update_option('team_module_hidden_roles', json_encode($hidden_roles));
            set_alert('success', _l('updated_successfully', 'Visibility Settings'));
            redirect(admin_url('team/visibility_settings'));
        }

        $this->load->model('roles_model');
        $data['roles'] = $this->roles_model->get();
        $this->load->model('misc_model'); // Ensure misc_model is loaded for get_option
        $data['hidden_roles'] = json_decode(get_option('team_module_hidden_roles'), true);
        if (!is_array($data['hidden_roles'])) {
            $data['hidden_roles'] = [];
        }

        $data['title'] = 'Team Visibility Settings';
        $this->load->view('visibility_settings', $data);
    }

    /* Change status to staff active or inactive / ajax */
    public function change_staff_status($id, $status)
    {
        if (has_permission('team', '', 'edit')) {
            if ($this->input->is_ajax_request()) {
                $this->staff_model->change_staff_status($id, $status);
            }
        }
    }

    /**
     * Generate a one-time login token for a staff member (admin only, AJAX).
     * Returns JSON with the auto-login URL to open in an incognito tab.
     */
    public function login_as_staff($id)
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        // Only admins can impersonate other staff
        if (!is_admin()) {
            header('HTTP/1.1 403 Forbidden');
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            die;
        }

        // Cannot login as yourself
        if ($id == get_staff_user_id()) {
            echo json_encode(['success' => false, 'message' => 'You are already logged in as this user.']);
            die;
        }

        $staff = $this->staff_model->get($id);
        if (!$staff || $staff->active == 0) {
            echo json_encode(['success' => false, 'message' => 'Staff member not found or inactive.']);
            die;
        }

        // Generate a secure one-time token (must be ≤32 chars to fit new_pass_key VARCHAR(32))
        $token = app_generate_hash();

        // Store token in new_pass_key columns (existing columns, no schema change)
        $this->db->where('staffid', $id);
        $this->db->update(db_prefix() . 'staff', [
            'new_pass_key'           => $token,
            'new_pass_key_requested' => date('Y-m-d H:i:s'),
        ]);

        // Build the auto-login URL (HMVC routes sub-controllers as admin/{module}/{controller}/{method})
        $login_url = admin_url('team/team_auth/staff_auto_login/' . $id . '/' . $token);

        log_activity('Login As Staff Token Generated [Admin: ' . get_staff_full_name() . ', Target Staff: ' . $staff->firstname . ' ' . $staff->lastname . ', ID: ' . $id . ']');

        echo json_encode([
            'success'   => true,
            'login_url' => $login_url,
            'staff_name' => $staff->firstname . ' ' . $staff->lastname,
        ]);
        die;
    }
}
