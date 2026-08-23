<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Public rider self-registration (QR) — no authentication.
 *
 *   /join/{token}                         membership form (learners)
 *   /join/{token}/done/{rider_no}/{sig}   success + membership PDF
 *   /join/{token}/pdf/{rider_no}/{sig}    membership PDF download
 *   /join/{token}/verify/{rider_no}[/{certificate_no}]   QR verification
 */
class Shra_public extends App_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('shra/shra_model');
        $this->load->helper('shra/shra');
    }

    private function check_token($token)
    {
        $real = (string) get_option('shra_public_token');
        if ($real === '' || !hash_equals($real, (string) $token)) {
            $this->error('Link not valid', 'This registration link is not valid any more. Please scan the QR code at the academy again.');

            return false;
        }

        return true;
    }

    private function error($title, $message)
    {
        $this->load->view('public_error', ['title' => $title, 'message' => $message]);
    }

    private function brand()
    {
        return [
            'name'             => get_option('shra_academy_name') ?: 'Stallion Horse Riding Academy',
            'tagline'          => get_option('shra_tagline'),
            'logo_path'        => shra_logo_pdf_path(),
            'contact'          => get_option('shra_contact_line'),
            'chief_instructor' => get_option('shra_chief_instructor') ?: 'Chief Instructor',
            'director'         => get_option('shra_director') ?: 'Director',
        ];
    }

    public function register($token = '', $action = '', $a = '', $b = '')
    {
        if (!$this->check_token($token)) {
            return;
        }

        if ($action === 'done') {
            return $this->done($token, $a, $b);
        }
        if ($action === 'pdf') {
            return $this->pdf($a, $b);
        }
        if ($action === 'verify') {
            return $this->verify($a, $b);
        }

        $data = [
            'title'  => 'Rider membership — ' . get_option('shra_academy_name'),
            'token'  => $token,
            'levels' => shra_riding_levels(),
            'terms'  => get_option('shra_terms'),
            'errors' => [],
            'old'    => [],
        ];

        if ($this->input->post()) {
            $post = $this->input->post(null, true);
            $errors = $this->validate($post);

            if (!count($errors)) {
                $post['rider_type']     = 'learner';
                $post['terms_accepted'] = 1;
                $post['status']         = 'active';
                unset($post['csrf_token_name']);

                $id = $this->shra_model->add_rider($post, 'self');
                if ($id) {
                    $rider = $this->shra_model->get_rider($id);
                    redirect(site_url('join/' . $token . '/done/' . $rider->rider_no . '/' . shra_sign($rider->rider_no)));
                }
                $errors[] = 'We could not save your registration. Please try again.';
            }

            $data['errors'] = $errors;
            $data['old']    = $post;
        }

        $this->load->view('public_register', $data);
    }

    private function validate(array $p)
    {
        $e = [];
        if (trim((string) ($p['full_name'] ?? '')) === '') {
            $e[] = 'Please enter the rider\'s full name.';
        }
        $mobile = preg_replace('/\D+/', '', (string) ($p['mobile'] ?? ''));
        if (strlen($mobile) < 8) {
            $e[] = 'Please enter a valid mobile number.';
        }
        if (empty($p['dob'])) {
            $e[] = 'Please enter the date of birth.';
        } elseif (strtotime($p['dob']) === false || strtotime($p['dob']) > time()) {
            $e[] = 'The date of birth is not valid.';
        }
        if (!empty($p['email']) && !filter_var($p['email'], FILTER_VALIDATE_EMAIL)) {
            $e[] = 'The email address is not valid.';
        }
        if (empty($p['gender'])) {
            $e[] = 'Please select a gender.';
        }
        if (trim((string) ($p['address'] ?? '')) === '') {
            $e[] = 'Please enter the full address.';
        }
        $minor = !empty($p['dob']) && shra_is_minor($p['dob']);
        if ($minor && trim((string) ($p['guardian_name'] ?? '')) === '') {
            $e[] = 'The rider is under ' . get_option('shra_minor_age') . ' — a parent / guardian name is required.';
        }
        if (empty($p['terms_accepted'])) {
            $e[] = $minor ? 'The parent / guardian must accept the terms & conditions on the rider\'s behalf.' : 'Please accept the terms & conditions.';
        }
        if ($minor && trim((string) ($p['terms_accepted_by'] ?? '')) === '') {
            $e[] = 'Please type the guardian\'s name to accept the terms on the rider\'s behalf.';
        }

        // Simple duplicate guard — same mobile already registered as a learner
        $existing = $this->shra_model->find_rider_by_mobile($mobile);
        if ($existing && mb_strtolower(trim($existing->full_name)) === mb_strtolower(trim((string) ($p['full_name'] ?? '')))) {
            $e[] = 'A rider with this name and mobile number is already registered (' . $existing->rider_no . '). Please ask the reception desk.';
        }

        return $e;
    }

    private function done($token, $rider_no, $sig)
    {
        $rider = $this->shra_model->get_rider_by_no($rider_no);
        if (!$rider || !shra_verify_sign($rider_no, $sig)) {
            return $this->error('Not found', 'We could not find this registration.');
        }

        $this->load->view('public_success', [
            'title'   => 'Welcome to the academy',
            'rider'   => $rider,
            'pdf_url' => site_url('join/' . $token . '/pdf/' . $rider->rider_no . '/' . $sig),
        ]);
    }

    private function pdf($rider_no, $sig)
    {
        $rider = $this->shra_model->get_rider_by_no($rider_no);
        if (!$rider || !shra_verify_sign($rider_no, $sig) || $rider->rider_type !== 'learner') {
            return $this->error('Not found', 'We could not find this membership.');
        }

        require_once(module_dir_path(SHRA_MODULE_NAME, 'libraries/Shra_pdf.php'));
        $pdf = new Shra_pdf($this->brand(), 'P');
        $arr = (array) $rider;
        $arr['qr_text'] = site_url('join/' . get_option('shra_public_token') . '/verify/' . $rider->rider_no);
        $pdf->membership($arr);
        $pdf->Output('Membership-' . ($rider->membership_no ?: $rider->rider_no) . '.pdf', 'D');
    }

    /** QR verification — shows that a membership / certificate is genuine. */
    private function verify($rider_no, $certificate_no = '')
    {
        $rider = $this->shra_model->get_rider_by_no($rider_no);
        $cert  = null;
        if ($rider && $certificate_no !== '') {
            $cert = $this->db->where('rider_id', $rider->id)->where('certificate_no', $certificate_no)
                ->get(db_prefix() . 'shra_enrollments')->row();
        }

        $this->load->view('public_verify', [
            'title'          => 'Verification',
            'rider'          => $rider,
            'cert'           => $cert,
            'certificate_no' => $certificate_no,
        ]);
    }
}
