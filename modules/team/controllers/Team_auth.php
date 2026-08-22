<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Handles the one-time auto-login token consumption for "Login as Staff" feature.
 * Extends App_Controller (not AdminController) so it can work from an unauthenticated
 * session (i.e., an incognito browser tab).
 */
class Team_auth extends App_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('staff_model');
    }

    /**
     * Consume a one-time login token and log in as the target staff member.
     * URL: admin/team/team_auth/staff_auto_login/{staff_id}/{token}
     *
     * Security:
     * - Token must match staff's stored new_pass_key
     * - Token expires after 5 minutes
     * - Token is cleared immediately after use (one-time)
     * - Activity is logged
     */
    public function staff_auto_login($id = '', $token = '')
    {
        if (empty($id) || empty($token)) {
            set_alert('danger', 'Invalid login link.');
            redirect(admin_url('authentication'));
        }

        // If someone is already logged in as staff, log them out first
        if (is_staff_logged_in()) {
            $this->authentication_model->logout();
        }

        // Validate the token
        $this->db->where('staffid', $id);
        $this->db->where('new_pass_key', $token);
        $staff = $this->db->get(db_prefix() . 'staff')->row();

        if (!$staff) {
            set_alert('danger', 'Invalid or expired login link.');
            redirect(admin_url('authentication'));
        }

        // Check if token is still valid (5-minute window)
        $token_age_seconds = time() - strtotime($staff->new_pass_key_requested);
        if ($token_age_seconds > 300) { // 5 minutes
            // Expired – clear the token
            $this->db->where('staffid', $id);
            $this->db->update(db_prefix() . 'staff', [
                'new_pass_key'           => null,
                'new_pass_key_requested' => null,
            ]);
            set_alert('danger', 'Login link has expired. Please generate a new one.');
            redirect(admin_url('authentication'));
        }

        // Check if staff is active
        if ($staff->active == 0) {
            set_alert('danger', 'This staff account is inactive.');
            redirect(admin_url('authentication'));
        }

        // Clear the token immediately (one-time use)
        $this->db->where('staffid', $id);
        $this->db->update(db_prefix() . 'staff', [
            'new_pass_key'           => null,
            'new_pass_key_requested' => null,
        ]);

        // Log in as the staff member by setting session data
        $this->session->set_userdata([
            'staff_user_id'   => $staff->staffid,
            'staff_logged_in' => true,
        ]);

        // Update last login info
        $this->db->where('staffid', $staff->staffid);
        $this->db->update(db_prefix() . 'staff', [
            'last_ip'    => $this->input->ip_address(),
            'last_login' => date('Y-m-d H:i:s'),
        ]);

        log_activity('Staff Auto-Login Used [Staff: ' . $staff->firstname . ' ' . $staff->lastname . ', ID: ' . $id . ', IP: ' . $this->input->ip_address() . ']');

        // Redirect to the admin dashboard
        redirect(admin_url());
    }
}
