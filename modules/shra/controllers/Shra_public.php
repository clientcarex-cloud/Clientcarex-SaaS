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

    /* ═══════════════════════ Public inquiry (leads) ═══════════════════════ */

    /**
     * Ad landing page (Meta Ads / Google Ads) + public inquiry form.
     *
     *   /inquire            landing page → lead (source from utm_source, round-robin assigned)
     *   /inquire/done       thank-you page — fires the Meta Pixel "Lead" + Google Ads conversion
     *
     * Query params carried through the form: c (campaign short code), utm_source, utm_medium,
     * utm_campaign, utm_content, gclid, fbclid, pkg (pre-select a package id).
     */
    public function inquire($action = '')
    {
        if (get_option('shra_lead_public_enabled') !== '1') {
            return $this->error('Inquiries closed', 'Please call the academy directly.');
        }
        if ($action === 'done') {
            return $this->load->view('public_inquire_success', [
                'title'   => 'Thank you — ' . get_option('shra_academy_name'),
                'landing' => $this->landing(),
            ]);
        }

        $this->load->model('shra/shra_leads_model');
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
                'per_session_now' => shra_money($pk->sessions > 0 ? $q['total'] / $pk->sessions : $q['total']),
                'price'        => shra_money($pk->price),
                'total'        => shra_money($q['total']),
                'total_raw'    => (float) $q['total'],
                'discount'     => $q['discount_percent'] + 0,
            ];
        }

        $get  = $this->input->get(null, true) ?: [];
        $data = [
            'title'    => 'Learn Horse Riding in Hyderabad — ' . get_option('shra_academy_name'),
            'packages' => $packages,
            'plans'    => $plans,
            'offer'    => shra_offer(),
            'landing'  => $this->landing(),
            'errors'   => [],
            'old'      => ['package_id' => (int) ($get['pkg'] ?? 0)],
            'ts'       => time(),
            'track'    => $this->tracking_from($get),
        ];
        $data['sig'] = shra_sign('inquire|' . $data['ts']);

        if ($this->input->post()) {
            $post   = $this->input->post(null, true);
            $errors = [];
            // Anti-spam: honeypot, signed timestamp (3 s .. 3 h), per-IP rate limit
            if (!empty($post['website'])) {
                $errors[] = 'Submission rejected.';
            }
            $ts = (int) ($post['ts'] ?? 0);
            if (!shra_verify_sign('inquire|' . $ts, (string) ($post['sig'] ?? '')) || time() - $ts < 3 || time() - $ts > 10800) {
                $errors[] = 'The form expired — please try again.';
            }
            $ip    = $this->input->ip_address();
            $count = $this->db->where('ip', $ip)->where('event_type', 'created')->where('created_at >=', date('Y-m-d H:i:s', time() - 3600))->count_all_results(db_prefix() . 'shra_lead_events');
            if ($count >= 5) {
                $errors[] = 'Too many inquiries from this connection. Please call us instead.';
            }
            if (trim((string) ($post['name'] ?? '')) === '') {
                $errors[] = 'Please enter your name.';
            }
            if (!shra_phone_valid((string) ($post['phone'] ?? ''))) {
                $errors[] = 'Please enter a valid mobile number.';
            }

            if (!count($errors)) {
                $track = $this->tracking_from($post);
                $desc  = trim((string) ($post['message'] ?? ''));
                if ($track['line'] !== '') {
                    $desc .= ($desc !== '' ? "\n\n" : '') . 'Ad tracking: ' . $track['line'];
                }
                $res = $this->shra_leads_model->capture([
                    'name'                => $post['name'],
                    'phone'               => $post['phone'],
                    'rider_for'           => $post['rider_for'] ?? 'self',
                    'rider_age'           => $post['rider_age'] ?? '',
                    'interest_package_id' => (int) ($post['package_id'] ?? 0),
                    'city'                => $post['city'] ?? '',
                    'source'              => $this->lead_source_id($track),
                    'description'         => $desc,
                    'campaign'            => $track['campaign'],
                ], 'public_form');
                // Duplicates are attached to the original lead silently — the visitor still sees a thank-you
                if (!is_string($res)) {
                    redirect(site_url('inquire/done'));
                }
                $errors[] = $res;
            }
            $data['errors'] = $errors;
            $data['old']    = $post;
            $data['track']  = $this->tracking_from($post);
        }

        $this->load->view('public_inquire', $data);
    }

    /** Landing-page settings (phone, reels, pixels) with sensible fallbacks. */
    private function landing()
    {
        $phone = trim((string) get_option('shra_lead_landing_phone'));
        if ($phone === '' && preg_match('/(\+?\d[\d\s-]{7,}\d)/', (string) get_option('shra_contact_line'), $m)) {
            $phone = $m[1];
        }
        $reels = array_values(array_filter(array_map(function ($l) {
            $l = trim($l);
            if (preg_match('~instagram\.com/(?:[^/]+/)?(?:reel|p)/([A-Za-z0-9_-]+)~', $l, $m)) {
                return $m[1];
            }

            return preg_match('/^[A-Za-z0-9_-]{5,}$/', $l) ? $l : null;
        }, preg_split('/[\r\n,]+/', (string) get_option('shra_lead_landing_reels')))));

        return [
            'phone'       => $phone,
            'phone_digits' => preg_replace('/\D+/', '', $phone),
            'wa_link'     => $phone !== '' ? shra_wa_link($phone, 'Hi! I saw your ad and I\'m interested in horse riding lessons at ' . get_option('shra_academy_name') . '. Please share the packages and visit timings.') : '',
            'location'    => trim((string) get_option('shra_lead_landing_location')),
            'maps_url'    => trim((string) get_option('shra_lead_landing_maps_url')),
            'instagram'   => trim((string) get_option('shra_lead_landing_instagram')),
            'reels'       => $reels,
            'meta_pixel'  => preg_replace('/\D+/', '', (string) get_option('shra_lead_meta_pixel_id')),
            'gads_id'     => trim((string) get_option('shra_lead_gads_id')),
            'gads_label'  => trim((string) get_option('shra_lead_gads_label')),
            'ga4_id'      => trim((string) get_option('shra_lead_ga4_id')),
            'min_age'     => (int) (get_option('shra_lead_landing_min_age') ?: 4),
        ];
    }

    /** Normalise the ad-tracking params from GET (landing) or POST (hidden fields). */
    private function tracking_from(array $in)
    {
        $t = [];
        foreach (['c', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'gclid', 'fbclid'] as $k) {
            $t[$k] = substr(trim((string) ($in[$k] ?? '')), 0, 120);
        }
        $t['campaign'] = substr($t['c'] !== '' ? $t['c'] : $t['utm_campaign'], 0, 80);

        $parts = [];
        foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'] as $k) {
            if ($t[$k] !== '') {
                $parts[] = substr($k, 4) . '=' . $t[$k];
            }
        }
        if ($t['gclid'] !== '') {
            $parts[] = 'gclid=' . $t['gclid'];
        }
        if ($t['fbclid'] !== '') {
            $parts[] = 'fbclid';
        }
        $t['line'] = implode(' · ', $parts);

        return $t;
    }

    /** Map utm_source / click ids onto the Perfex lead sources seeded by the module. */
    private function lead_source_id(array $t)
    {
        $src  = strtolower($t['utm_source'] . ' ' . $t['utm_medium']);
        $name = 'Website QR';
        if ($t['gclid'] !== '' || strpos($src, 'google') !== false || strpos($src, 'adwords') !== false) {
            $name = 'Google';
        } elseif (strpos($src, 'ig') === 0 || strpos($src, 'instagram') !== false) {
            $name = 'Instagram';
        } elseif ($t['fbclid'] !== '' || strpos($src, 'fb') !== false || strpos($src, 'facebook') !== false || strpos($src, 'meta') !== false) {
            $name = 'Facebook';
        }
        $row = $this->db->where('name', $name)->get(db_prefix() . 'leads_sources')->row();
        if (!$row && $name !== 'Website QR') {
            $row = $this->db->where('name', 'Website QR')->get(db_prefix() . 'leads_sources')->row();
        }

        return $row ? (int) $row->id : 0;
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
