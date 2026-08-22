<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Verification extends ClientsController
{
    /**
     * Helper: send JSON response with fresh CSRF token
     */
    private function json_response($data)
    {
        $data['csrf_token'] = $this->security->get_csrf_hash();
        header('Content-Type: application/json');
        echo json_encode($data);
        die;
    }

    public function index()
    {
        if (is_contact_email_verified()) {
            redirect(site_url('clients'));
        }

        $data['title'] = _l('email_verification_required');

        $this->view('verification_required');
        $this->data($data);
        $this->layout();
    }

    /**
     * Legacy link-based verification — kept for backward compatibility
     * Old emails with verification links will still work
     */
    public function verify($id, $key)
    {
        $contact = $this->clients_model->get_contact($id);

        if (!$contact) {
            show_404();
        }

        if (!is_null($contact->email_verified_at)) {
            set_alert('info', _l('email_already_verified'));
            redirect(site_url('clients'));
        }

        if ($contact->email_verification_key !== $key) {
            show_error(_l('invalid_verification_key'));
        }

        $timestamp_now_minus_2_days = time() - (2 * 86400);
        $contact_registered         = strtotime($contact->email_verification_sent_at);

        if ($timestamp_now_minus_2_days > $contact_registered) {
            show_error(_l('verification_key_expired'));
        }

        $this->clients_model->mark_email_as_verified($contact->id);

        // Clear OTP session flag if set
        $this->session->unset_userdata('otp_verification_pending');
        $this->session->unset_userdata('otp_user_email');

        // User not yet confirmed
        if (total_rows(db_prefix() . 'clients', ['userid' => $contact->userid, 'registration_confirmed' => 0]) > 0) {
            set_alert('info', _l('email_successfully_verified_but_required_admin_confirmation'));
            hooks()->do_action('contact_email_verified_but_requires_admin_confirmation', $contact);
        } else {
            set_alert('success', _l('email_successfully_verified'));
            hooks()->do_action('contact_email_verified', $contact);
        }

        $redUri = is_client_logged_in() ? 'clients' : 'authentication';
        redirect(site_url($redUri));
    }

    /**
     * AJAX: Verify OTP code
     * POST { otp: "123456" }
     */
    public function verify_otp()
    {
        if (!$this->input->is_ajax_request() || !is_client_logged_in()) {
            $this->json_response(['success' => false, 'message' => 'Unauthorized']);
        }

        $otp = trim($this->input->post('otp', true));
        $contact_id = get_contact_user_id();
        $contact = $this->clients_model->get_contact($contact_id);

        if (!$contact) {
            $this->json_response(['success' => false, 'message' => 'Contact not found.']);
        }

        if (!is_null($contact->email_verified_at)) {
            $this->json_response(['success' => true, 'message' => 'Email already verified.', 'redirect' => site_url('clients')]);
        }

        // Check OTP expiry (10 minutes)
        if (!empty($contact->email_verification_sent_at)) {
            $sent_at = strtotime($contact->email_verification_sent_at);
            if ((time() - $sent_at) > 600) {
                $this->json_response(['success' => false, 'message' => 'OTP has expired. Please request a new one.', 'expired' => true]);
            }
        }

        // Check OTP match
        if (empty($contact->email_verification_key) || $contact->email_verification_key !== $otp) {
            $this->json_response(['success' => false, 'message' => 'Invalid OTP code. Please try again.']);
        }

        // OTP is correct — mark as verified
        $this->clients_model->mark_email_as_verified($contact_id);

        // Clear OTP session flags
        $this->session->unset_userdata('otp_verification_pending');
        $this->session->unset_userdata('otp_user_email');

        // Check if admin confirmation is also required
        $client_id = get_user_id_by_contact_id($contact_id);
        if (total_rows(db_prefix() . 'clients', ['userid' => $client_id, 'registration_confirmed' => 0]) > 0) {
            hooks()->do_action('contact_email_verified_but_requires_admin_confirmation', $contact);
            $this->json_response([
                'success'  => true,
                'message'  => 'Email verified! Your account is pending admin approval.',
                'redirect' => site_url('clients'),
            ]);
        } else {
            hooks()->do_action('contact_email_verified', $contact);
            $this->json_response([
                'success'  => true,
                'message'  => 'Email verified successfully!',
                'redirect' => site_url('clients'),
            ]);
        }
    }

    /**
     * AJAX: Resend OTP code
     */
    public function resend_otp()
    {
        if (!$this->input->is_ajax_request() || !is_client_logged_in()) {
            $this->json_response(['success' => false, 'message' => 'Unauthorized']);
        }

        $contact_id = get_contact_user_id();
        $contact = $this->clients_model->get_contact($contact_id);

        if (!$contact) {
            $this->json_response(['success' => false, 'message' => 'Contact not found.']);
        }

        if (!is_null($contact->email_verified_at)) {
            $this->json_response(['success' => true, 'message' => 'Email already verified.', 'redirect' => site_url('clients')]);
        }

        // Check 60-second cooldown
        if (!empty($contact->email_verification_sent_at)) {
            $elapsed = time() - strtotime($contact->email_verification_sent_at);
            if ($elapsed < 60) {
                $remaining = 60 - $elapsed;
                $this->json_response([
                    'success'  => false,
                    'message'  => 'Please wait ' . $remaining . ' seconds before requesting a new OTP.',
                    'cooldown' => $remaining,
                ]);
            }
        }

        // Send new OTP
        $success = $this->clients_model->send_verification_email($contact_id);

        if ($success) {
            $this->json_response([
                'success'  => true,
                'message'  => 'A new OTP has been sent to your email.',
                'cooldown' => 60,
            ]);
        } else {
            $this->json_response([
                'success' => false,
                'message' => 'Failed to send OTP. Please try again.',
            ]);
        }
    }

    /**
     * Legacy resend (non-AJAX) — kept for backward compatibility
     */
    public function resend()
    {
        if (is_contact_email_verified() || !is_client_logged_in()) {
            redirect(site_url('clients'));
        }

        if ($this->clients_model->send_verification_email(get_contact_user_id())) {
            set_alert('success', _l('email_verification_mail_sent_successully'));
        } else {
            set_alert('danger', 'Failed to sent email verification mail, contact webmaster for more information.');
        }

        redirect(site_url('verification'));
    }
}
