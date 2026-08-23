<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Public rider self-registration (QR) — no authentication.
 *
 *   /join                                 membership form (learners) — static, printed on the QR poster
 *   /join/done/{rider_no}/{sig}           success + membership PDF
 *   /join/pdf/{rider_no}/{sig}            membership PDF download
 *   /join/verify/{rider_no}[/{certificate_no}]   QR verification
 *
 * The private option shra_public_token is only used to sign links.
 */
class Shra_public extends App_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('shra/shra_model');
        $this->load->helper('shra/shra');
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
            'powered_by_logo'  => shra_powered_by_logo_path(),
        ];
    }

    public function register($action = '', $a = '', $b = '')
    {
        if ($action === 'done') {
            return $this->done($a, $b);
        }
        if ($action === 'pdf') {
            return $this->pdf($a, $b);
        }
        if ($action === 'verify') {
            return $this->verify($a, $b);
        }

        $packages = $this->shra_model->get_packages(true);
        $plans    = [];
        foreach ($packages as $pk) {
            $q       = $this->shra_model->quote($pk);
            $plans[] = [
                'id'           => (int) $pk->id,
                'name'         => $pk->name,
                'audience'     => $pk->audience,
                'sessions'     => (int) $pk->sessions,
                'duration_min' => (int) $pk->duration_min,
                'is_guest'     => (int) $pk->is_guest,
                'is_featured'  => (int) $pk->is_featured,
                'per_session'  => shra_money($pk->per_session),
                'price'        => shra_money($pk->price),
                'total'        => shra_money($q['total']),
                'discount'     => $q['discount_percent'] + 0,
            ];
        }

        $data = [
            'title'     => 'Join — ' . get_option('shra_academy_name'),
            'levels'    => shra_riding_levels(),
            'terms'     => get_option('shra_terms'),
            'plans'     => $plans,
            'offer'     => shra_offer(),
            'minor_age' => (int) get_option('shra_minor_age'),
            'errors'    => [],
            'old'       => [],
        ];

        if ($this->input->post()) {
            $post   = $this->input->post(null, true);
            $type   = ($post['rider_type'] ?? '') === 'guest' ? 'guest' : 'learner';
            $errors = $this->validate($post, $type);

            if (!count($errors)) {
                $post['rider_type']     = $type;
                $post['terms_accepted'] = 1;
                $post['status']         = 'active';
                unset($post['csrf_token_name']);

                // Plan chosen on the form — must be an active package of the right kind
                $pkg = !empty($post['package_id']) ? $this->shra_model->get_package((int) $post['package_id']) : null;
                $post['preferred_package_id'] = ($pkg && $pkg->active && (int) $pkg->is_guest === ($type === 'guest' ? 1 : 0)) ? $pkg->id : null;

                $id = $this->shra_model->add_rider($post, 'self');
                if ($id) {
                    $rider = $this->shra_model->get_rider($id);
                    redirect(site_url('join/done/' . $rider->rider_no . '/' . shra_sign($rider->rider_no)));
                }
                $errors[] = 'We could not save your registration. Please try again.';
            }

            $data['errors'] = $errors;
            $data['old']    = $post;
        }

        $this->load->view('public_register', $data);
    }

    private function validate(array $p, $type = 'learner')
    {
        $e = [];
        $learner = $type === 'learner';
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
        if ($learner && trim((string) ($p['address'] ?? '')) === '') {
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

        // Duplicate guard — same name + mobile already on file (guests may return; learners should not re-register)
        $existing = $this->shra_model->find_rider_by_mobile($mobile);
        if ($existing && $learner && mb_strtolower(trim($existing->full_name)) === mb_strtolower(trim((string) ($p['full_name'] ?? '')))) {
            $e[] = 'A rider with this name and mobile number is already registered (' . $existing->rider_no . '). Please ask the reception desk.';
        }

        return $e;
    }

    private function done($rider_no, $sig)
    {
        $rider = $this->shra_model->get_rider_by_no($rider_no);
        if (!$rider || !shra_verify_sign($rider_no, $sig)) {
            return $this->error('Not found', 'We could not find this registration.');
        }

        $plan = $rider->preferred_package ? $this->shra_model->quote($rider->preferred_package) : null;

        $this->load->view('public_success', [
            'title'   => 'Welcome to the academy',
            'rider'   => $rider,
            'plan'    => $plan,
            'pdf_url' => $rider->rider_type === 'learner' ? site_url('join/pdf/' . $rider->rider_no . '/' . $sig) : null,
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
        $arr['qr_text'] = shra_verify_url($rider->rider_no);
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
