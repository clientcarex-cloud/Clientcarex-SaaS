<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Shra_model extends App_Model
{
    public function __construct()
    {
        parent::__construct();
        $this->load->helper('shra/shra');
    }

    /* ═══════════════════════ Numbering ═══════════════════════ */

    private function next_number($option, $prefix, $pad = 4)
    {
        $n = (int) get_option($option);
        if ($n < 1) {
            $n = 1;
        }
        update_option($option, $n + 1);

        return $prefix . str_pad($n, $pad, '0', STR_PAD_LEFT);
    }

    /* ═══════════════════════ Riders ═══════════════════════ */

    public function get_rider($id)
    {
        $r = $this->db->where('id', (int) $id)->get(db_prefix() . 'shra_riders')->row();
        if ($r) {
            $this->decorate_rider($r);
        }

        return $r;
    }

    public function get_rider_by_no($rider_no)
    {
        $r = $this->db->where('rider_no', $rider_no)->get(db_prefix() . 'shra_riders')->row();
        if ($r) {
            $this->decorate_rider($r);
        }

        return $r;
    }

    private function decorate_rider($r)
    {
        $r->age      = shra_age($r->dob);
        $r->audience = shra_audience_for($r->dob);
    }

    public function get_riders($filters = [])
    {
        $p = db_prefix();
        $this->db->select("r.*,
            (SELECT COUNT(*) FROM {$p}shra_enrollments e WHERE e.rider_id = r.id AND e.status = 'active') AS active_enrollments,
            (SELECT COALESCE(SUM(e.sessions_total - e.sessions_used),0) FROM {$p}shra_enrollments e WHERE e.rider_id = r.id AND e.status = 'active') AS sessions_left,
            (SELECT MAX(a.session_date) FROM {$p}shra_attendance a WHERE a.rider_id = r.id) AS last_session")
            ->from($p . 'shra_riders r');

        if (!empty($filters['q'])) {
            $q = $this->db->escape_like_str($filters['q']);
            $this->db->where("(r.full_name LIKE '%{$q}%' OR r.mobile LIKE '%{$q}%' OR r.rider_no LIKE '%{$q}%' OR r.email LIKE '%{$q}%' OR r.membership_no LIKE '%{$q}%')");
        }
        if (!empty($filters['type'])) {
            $this->db->where('r.rider_type', $filters['type']);
        }
        if (!empty($filters['status'])) {
            $this->db->where('r.status', $filters['status']);
        }
        if (!empty($filters['level'])) {
            $this->db->where('r.riding_level', $filters['level']);
        }

        $rows = $this->db->order_by('r.id', 'DESC')->limit(500)->get()->result();
        foreach ($rows as $r) {
            $this->decorate_rider($r);
        }

        return $rows;
    }

    /** Lightweight search for billing / attendance pickers. */
    public function search_riders($q, $limit = 12)
    {
        $p = db_prefix();
        $q = $this->db->escape_like_str(trim((string) $q));

        $this->db->select("r.id, r.rider_no, r.full_name, r.mobile, r.dob, r.rider_type, r.riding_level, r.membership_no, r.status,
            (SELECT COALESCE(SUM(e.sessions_total - e.sessions_used),0) FROM {$p}shra_enrollments e WHERE e.rider_id = r.id AND e.status = 'active') AS sessions_left")
            ->from($p . 'shra_riders r');

        if ($q !== '') {
            $this->db->where("(r.full_name LIKE '%{$q}%' OR r.mobile LIKE '%{$q}%' OR r.rider_no LIKE '%{$q}%' OR r.membership_no LIKE '%{$q}%')");
        }

        $rows = $this->db->order_by('r.full_name', 'ASC')->limit($limit)->get()->result();
        foreach ($rows as $r) {
            $this->decorate_rider($r);
        }

        return $rows;
    }

    public function find_rider_by_mobile($mobile)
    {
        $mobile = preg_replace('/\D+/', '', (string) $mobile);
        if ($mobile === '') {
            return null;
        }
        $r = $this->db->where("REPLACE(REPLACE(REPLACE(mobile,' ',''),'-',''),'+','') LIKE '%" . $this->db->escape_like_str($mobile) . "'", null, false)
            ->limit(1)->get(db_prefix() . 'shra_riders')->row();
        if ($r) {
            $this->decorate_rider($r);
        }

        return $r;
    }

    /**
     * Create a rider. $data keys follow the shra_riders columns.
     * Also creates (or links) the core customer + contact so billing can
     * attach invoices to a real Perfex customer.
     */
    public function add_rider(array $data, $source = 'staff')
    {
        $data = $this->clean_rider_data($data);

        $data['rider_no']   = $this->next_number('shra_next_rider_no', 'SHRA-');
        $data['source']     = $source;
        $data['is_minor']   = shra_is_minor($data['dob'] ?? null) ? 1 : 0;
        $data['ip_address'] = $this->input->ip_address();
        $data['created_by'] = is_staff_logged_in() ? get_staff_user_id() : null;
        $data['created_at'] = date('Y-m-d H:i:s');

        if (!empty($data['terms_accepted'])) {
            $data['terms_accepted_at'] = date('Y-m-d H:i:s');
            if (empty($data['terms_accepted_by'])) {
                $data['terms_accepted_by'] = $data['is_minor'] && !empty($data['guardian_name']) ? $data['guardian_name'] : $data['full_name'];
            }
        }

        // Learners receive a membership number straight away
        if (($data['rider_type'] ?? 'learner') === 'learner') {
            $data['membership_no']        = $this->next_number('shra_next_membership_no', 'SHRA-M-', 4);
            $data['membership_issued_at'] = date('Y-m-d H:i:s');
        }

        $client = $this->ensure_client($data);
        $data['client_id']  = $client['client_id'];
        $data['contact_id'] = $client['contact_id'];

        $this->db->insert(db_prefix() . 'shra_riders', $data);
        $id = $this->db->insert_id();

        if ($id) {
            log_activity('SHRA rider registered [' . $data['rider_no'] . ' ' . $data['full_name'] . ', source: ' . $source . ']');
        }

        return $id;
    }

    public function update_rider($id, array $data)
    {
        $data = $this->clean_rider_data($data);
        $data['is_minor'] = shra_is_minor($data['dob'] ?? null) ? 1 : 0;

        $rider = $this->get_rider($id);
        if (!$rider) {
            return false;
        }

        // Learner switched on later → issue a membership number
        if (($data['rider_type'] ?? $rider->rider_type) === 'learner' && empty($rider->membership_no)) {
            $data['membership_no']        = $this->next_number('shra_next_membership_no', 'SHRA-M-', 4);
            $data['membership_issued_at'] = date('Y-m-d H:i:s');
        }

        if (!empty($data['terms_accepted']) && !$rider->terms_accepted) {
            $data['terms_accepted_at'] = date('Y-m-d H:i:s');
            $data['terms_accepted_by'] = $data['terms_accepted_by'] ?? ($data['is_minor'] ? ($data['guardian_name'] ?? '') : $data['full_name']);
        }

        $this->db->where('id', (int) $id)->update(db_prefix() . 'shra_riders', $data);

        // Keep the core contact in sync
        if ($rider->contact_id) {
            [$first, $last] = $this->split_name($data['full_name'] ?? $rider->full_name);
            $this->db->where('id', $rider->contact_id)->update(db_prefix() . 'contacts', [
                'firstname'   => $first,
                'lastname'    => $last,
                'phonenumber' => $data['mobile'] ?? $rider->mobile,
                'email'       => $data['email'] ?? $rider->email,
            ]);
        }
        if ($rider->client_id) {
            $this->db->where('userid', $rider->client_id)->update(db_prefix() . 'clients', [
                'company'        => $data['full_name'] ?? $rider->full_name,
                'phonenumber'    => $data['mobile'] ?? $rider->mobile,
                'address'        => $data['address'] ?? $rider->address,
            ]);
        }

        return true;
    }

    public function delete_rider($id)
    {
        $id = (int) $id;
        $this->db->where('rider_id', $id)->delete(db_prefix() . 'shra_attendance');
        $this->db->where('rider_id', $id)->delete(db_prefix() . 'shra_enrollments');
        $this->db->where('id', $id)->delete(db_prefix() . 'shra_riders');

        return $this->db->affected_rows() > 0;
    }

    private function clean_rider_data(array $in)
    {
        $allowed = ['rider_type', 'full_name', 'guardian_name', 'guardian_relationship', 'mobile', 'email', 'gender', 'dob',
            'place_of_birth', 'address', 'marital_status', 'riding_level', 'terms_accepted', 'terms_accepted_by', 'status', 'notes'];
        $out = [];
        foreach ($allowed as $k) {
            if (array_key_exists($k, $in)) {
                $v = $in[$k];
                $out[$k] = is_string($v) ? trim($v) : $v;
            }
        }
        if (isset($out['dob'])) {
            $out['dob'] = $out['dob'] !== '' ? to_sql_date($out['dob']) : null;
            if ($out['dob'] && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $out['dob'])) {
                $out['dob'] = date('Y-m-d', strtotime($out['dob'])) ?: null;
            }
        }
        if (isset($out['terms_accepted'])) {
            $out['terms_accepted'] = $out['terms_accepted'] ? 1 : 0;
        }
        foreach (['email', 'guardian_name', 'guardian_relationship', 'place_of_birth', 'address', 'marital_status', 'gender', 'notes'] as $k) {
            if (isset($out[$k]) && $out[$k] === '') {
                $out[$k] = null;
            }
        }

        return $out;
    }

    private function split_name($full)
    {
        $parts = preg_split('/\s+/', trim((string) $full), 2);

        return [$parts[0] ?? '', $parts[1] ?? ''];
    }

    /**
     * Find a core customer by contact phone/email, else create one
     * (company = rider name) with a primary contact. No welcome email.
     */
    private function ensure_client(array $rider)
    {
        $p = db_prefix();

        $mobile = preg_replace('/\D+/', '', (string) ($rider['mobile'] ?? ''));
        $email  = (string) ($rider['email'] ?? '');

        if ($mobile !== '' || $email !== '') {
            $this->db->select('id, userid')->from($p . 'contacts');
            $wh = [];
            if ($mobile !== '') {
                $wh[] = "REPLACE(REPLACE(REPLACE(phonenumber,' ',''),'-',''),'+','') LIKE '%" . $this->db->escape_like_str($mobile) . "'";
            }
            if ($email !== '') {
                $wh[] = 'email = ' . $this->db->escape($email);
            }
            $c = $this->db->where('(' . implode(' OR ', $wh) . ')', null, false)->limit(1)->get()->row();
            if ($c) {
                return ['client_id' => (int) $c->userid, 'contact_id' => (int) $c->id];
            }
        }

        $this->load->model('clients_model');
        [$first, $last] = $this->split_name($rider['full_name']);

        $client_data = [
            'company'               => $rider['full_name'],
            'phonenumber'           => $rider['mobile'] ?? '',
            'address'               => $rider['address'] ?? '',
            'firstname'             => $first,
            'lastname'              => $last ?: '-',
            'email'                 => $email !== '' ? $email : '',
            'password'              => bin2hex(random_bytes(8)),
            'donotsendwelcomeemail' => true,
            'is_primary'            => 1,
        ];

        $client_id = $this->clients_model->add($client_data, true);
        $contact   = $client_id ? $this->db->where('userid', $client_id)->order_by('id', 'ASC')->limit(1)->get($p . 'contacts')->row() : null;

        return ['client_id' => (int) $client_id, 'contact_id' => $contact ? (int) $contact->id : null];
    }

    /* ═══════════════════════ Packages ═══════════════════════ */

    public function get_packages($active_only = true, $audience = null)
    {
        if ($active_only) {
            $this->db->where('active', 1);
        }
        if ($audience) {
            $this->db->where('audience', $audience);
        }

        return $this->db->order_by('audience', 'ASC')->order_by('sort_order', 'ASC')->order_by('sessions', 'ASC')
            ->get(db_prefix() . 'shra_packages')->result();
    }

    public function get_package($id)
    {
        return $this->db->where('id', (int) $id)->get(db_prefix() . 'shra_packages')->row();
    }

    public function save_package(array $in, $id = null)
    {
        $data = [
            'name'          => trim((string) ($in['name'] ?? '')),
            'audience'      => in_array($in['audience'] ?? '', ['children', 'adults']) ? $in['audience'] : 'children',
            'sessions'      => max(1, (int) ($in['sessions'] ?? 1)),
            'duration_min'  => max(5, (int) ($in['duration_min'] ?? 30)),
            'per_session'   => (float) ($in['per_session'] ?? 0),
            'is_guest'      => !empty($in['is_guest']) ? 1 : 0,
            'is_featured'   => !empty($in['is_featured']) ? 1 : 0,
            'validity_days' => !empty($in['validity_days']) ? (int) $in['validity_days'] : null,
            'active'        => !empty($in['active']) ? 1 : 0,
            'sort_order'    => (int) ($in['sort_order'] ?? 0),
        ];
        $data['price'] = isset($in['price']) && $in['price'] !== '' ? (float) $in['price'] : $data['per_session'] * $data['sessions'];

        if ($data['name'] === '') {
            return false;
        }

        if ($id) {
            $this->db->where('id', (int) $id)->update(db_prefix() . 'shra_packages', $data);

            return (int) $id;
        }
        $this->db->insert(db_prefix() . 'shra_packages', $data);

        return $this->db->insert_id();
    }

    public function delete_package($id)
    {
        $this->db->where('id', (int) $id)->delete(db_prefix() . 'shra_packages');

        return $this->db->affected_rows() > 0;
    }

    /* ═══════════════════════ Billing / Enrollments ═══════════════════════ */

    /**
     * Price preview for a package (applies the offer unless overridden).
     */
    public function quote($package, $discount_percent = null)
    {
        $offer = shra_offer();
        if ($discount_percent === null) {
            $discount_percent = $offer['active'] ? $offer['percent'] : 0;
        }
        $discount_percent = max(0, min(100, (float) $discount_percent));
        $list             = (float) $package->price;
        $discount         = round($list * $discount_percent / 100, 2);

        return [
            'list_price'       => $list,
            'discount_percent' => $discount_percent,
            'discount_amount'  => $discount,
            'total'            => round($list - $discount, 2),
        ];
    }

    /**
     * One-screen billing: creates a paid Perfex invoice + payment record and
     * the enrollment (sessions wallet). Returns ['enrollment_id', 'invoice_id']
     * or a string error.
     */
    public function create_bill($rider_id, $package_id, array $opts = [])
    {
        $rider   = $this->get_rider($rider_id);
        $package = $this->get_package($package_id);

        if (!$rider || !$package) {
            return 'Rider or package not found.';
        }

        $quote        = $this->quote($package, $opts['discount_percent'] ?? null);
        $payment_mode = (string) ($opts['payment_mode'] ?? '');
        $paid_amount  = isset($opts['paid_amount']) && $opts['paid_amount'] !== '' ? (float) $opts['paid_amount'] : $quote['total'];
        $paid_amount  = max(0, min($quote['total'], $paid_amount));

        // Ensure a core customer exists (riders created before linking)
        if (!$rider->client_id) {
            $link = $this->ensure_client((array) $rider);
            $this->db->where('id', $rider->id)->update(db_prefix() . 'shra_riders', $link);
            $rider->client_id  = $link['client_id'];
            $rider->contact_id = $link['contact_id'];
        }

        $this->load->model('invoices_model');
        $this->load->model('payment_modes_model');

        $currency = get_base_currency();
        $client   = $this->db->where('userid', $rider->client_id)->get(db_prefix() . 'clients')->row();

        $items = [[
            'order'            => 1,
            'description'      => ucfirst($package->audience) . ' — ' . $package->name,
            'long_description' => $package->sessions . ' session' . ($package->sessions > 1 ? 's' : '') . ' × ' . $package->duration_min . ' min'
                . ' · ' . shra_money($package->per_session) . ' per session · Rider ' . $rider->rider_no,
            'qty'              => 1,
            'unit'             => '',
            'rate'             => $quote['list_price'],
            'taxname'          => [],
        ]];

        $mode_name = $payment_mode;
        if (is_numeric($payment_mode)) {
            $m = $this->payment_modes_model->get((int) $payment_mode);
            $mode_name = $m ? $m->name : $payment_mode;
        }

        $status = $paid_amount >= $quote['total'] ? 2 : ($paid_amount > 0 ? 3 : 1); // paid | partial | unpaid

        $invoice_data = [
            'clientid'                 => $rider->client_id,
            'number'                   => get_option('next_invoice_number'),
            'date'                     => date('Y-m-d'),
            'duedate'                  => date('Y-m-d'),
            'currency'                 => $currency->id,
            'subtotal'                 => $quote['list_price'],
            'total'                    => $quote['total'],
            'discount_percent'         => $quote['discount_percent'],
            'discount_total'           => $quote['discount_amount'],
            'discount_type'            => $quote['discount_percent'] > 0 ? 'before_tax' : '',
            'status'                   => $status,
            'adminnote'                => 'SHRA counter billing · ' . $rider->rider_no . ' · ' . $package->name,
            'clientnote'               => $quote['discount_percent'] > 0 ? ($quote['discount_percent'] + 0) . '% ' . (get_option('shra_offer_label') ?: 'offer') . ' applied.' : '',
            'terms'                    => 'Sessions are first-come, first-served with no fixed time slots. Packages are non-transferable.',
            'show_quantity_as'         => 1,
            'newitems'                 => $items,
            'billing_street'           => (string) ($client->billing_street ?: $rider->address),
            'billing_city'             => (string) ($client->billing_city ?? ''),
            'billing_state'            => (string) ($client->billing_state ?? ''),
            'billing_zip'              => (string) ($client->billing_zip ?? ''),
            'billing_country'          => (int) ($client->billing_country ?? 0),
            'include_shipping'         => 0,
            'show_shipping_on_invoice' => 0,
            'allowed_payment_modes'    => is_numeric($payment_mode) ? [$payment_mode] : [],
        ];

        $invoice_id = $this->invoices_model->add($invoice_data);
        if (!$invoice_id) {
            return 'Could not create the invoice.';
        }

        if ($paid_amount > 0) {
            $this->db->insert(db_prefix() . 'invoicepaymentrecords', [
                'invoiceid'     => $invoice_id,
                'amount'        => $paid_amount,
                'paymentmode'   => $payment_mode,
                'date'          => date('Y-m-d'),
                'daterecorded'  => date('Y-m-d H:i:s'),
                'transactionid' => (string) ($opts['reference'] ?? ''),
                'note'          => 'SHRA counter · ' . $package->name,
            ]);
            update_invoice_status($invoice_id, true);
            $this->invoices_model->save_formatted_number($invoice_id);
        }

        $start   = date('Y-m-d');
        $expires = $package->validity_days ? date('Y-m-d', strtotime("+{$package->validity_days} days")) : null;

        $enr = [
            'enrollment_no'    => $this->next_number('shra_next_enrollment_no', 'SHRA-E-'),
            'rider_id'         => $rider->id,
            'package_id'       => $package->id,
            'package_name'     => $package->name,
            'audience'         => $package->audience,
            'is_guest'         => (int) $package->is_guest,
            'sessions_total'   => (int) $package->sessions,
            'sessions_used'    => 0,
            'duration_min'     => (int) $package->duration_min,
            'list_price'       => $quote['list_price'],
            'discount_percent' => $quote['discount_percent'],
            'discount_amount'  => $quote['discount_amount'],
            'total'            => $quote['total'],
            'paid_amount'      => $paid_amount,
            'payment_mode'     => $mode_name,
            'invoice_id'       => $invoice_id,
            'start_date'       => $start,
            'expires_at'       => $expires,
            'status'           => 'active',
            'notes'            => isset($opts['notes']) ? substr(trim((string) $opts['notes']), 0, 500) : null,
            'created_by'       => get_staff_user_id(),
            'created_at'       => date('Y-m-d H:i:s'),
        ];
        $this->db->insert(db_prefix() . 'shra_enrollments', $enr);
        $enrollment_id = $this->db->insert_id();

        // A guest ride purchased by a guest rider: mark attended right away when asked
        if (!empty($opts['mark_now']) && $enrollment_id) {
            $this->mark_attendance($enrollment_id, ['trainer_id' => $opts['trainer_id'] ?? null]);
        }

        log_activity('SHRA bill created [Invoice #' . $invoice_id . ', ' . $rider->rider_no . ', ' . $package->name . ', ' . shra_money($quote['total']) . ']');

        return ['enrollment_id' => $enrollment_id, 'invoice_id' => $invoice_id, 'quote' => $quote];
    }

    public function get_enrollment($id)
    {
        $p = db_prefix();

        return $this->db->select('e.*, r.full_name, r.rider_no, r.mobile, r.dob, r.rider_type, r.riding_level, r.membership_no, r.guardian_name, r.is_minor, i.number AS invoice_number, i.formatted_number AS invoice_formatted, i.hash AS invoice_hash, i.status AS invoice_status')
            ->from($p . 'shra_enrollments e')
            ->join($p . 'shra_riders r', 'r.id = e.rider_id', 'left')
            ->join($p . 'invoices i', 'i.id = e.invoice_id', 'left')
            ->where('e.id', (int) $id)->get()->row();
    }

    public function get_enrollments($filters = [], $limit = 300)
    {
        $p = db_prefix();
        $this->db->select('e.*, r.full_name, r.rider_no, r.mobile, r.rider_type, r.membership_no, i.status AS invoice_status, i.hash AS invoice_hash, i.formatted_number AS invoice_formatted')
            ->from($p . 'shra_enrollments e')
            ->join($p . 'shra_riders r', 'r.id = e.rider_id', 'left')
            ->join($p . 'invoices i', 'i.id = e.invoice_id', 'left');

        if (!empty($filters['rider_id'])) {
            $this->db->where('e.rider_id', (int) $filters['rider_id']);
        }
        if (!empty($filters['status'])) {
            $this->db->where('e.status', $filters['status']);
        }
        if (!empty($filters['q'])) {
            $q = $this->db->escape_like_str($filters['q']);
            $this->db->where("(r.full_name LIKE '%{$q}%' OR r.mobile LIKE '%{$q}%' OR r.rider_no LIKE '%{$q}%' OR e.enrollment_no LIKE '%{$q}%' OR e.certificate_no LIKE '%{$q}%')");
        }
        if (!empty($filters['from'])) {
            $this->db->where('DATE(e.created_at) >=', $filters['from']);
        }
        if (!empty($filters['to'])) {
            $this->db->where('DATE(e.created_at) <=', $filters['to']);
        }

        return $this->db->order_by('e.id', 'DESC')->limit($limit)->get()->result();
    }

    /** Active enrollments with sessions remaining for a rider. */
    public function active_enrollments($rider_id)
    {
        return $this->db->where('rider_id', (int) $rider_id)->where('status', 'active')
            ->where('sessions_used < sessions_total', null, false)
            ->order_by('created_at', 'ASC')->get(db_prefix() . 'shra_enrollments')->result();
    }

    public function complete_enrollment($id, $issue_certificate = true)
    {
        $e = $this->get_enrollment($id);
        if (!$e) {
            return false;
        }
        $upd = ['status' => 'completed', 'completed_at' => $e->completed_at ?: date('Y-m-d H:i:s')];
        $this->db->where('id', $e->id)->update(db_prefix() . 'shra_enrollments', $upd);

        if ($issue_certificate && !$e->is_guest && empty($e->certificate_no)) {
            $this->issue_certificate($e->id);
        }

        return true;
    }

    public function issue_certificate($id)
    {
        $e = $this->get_enrollment($id);
        if (!$e || $e->is_guest) {
            return false;
        }
        if (!empty($e->certificate_no)) {
            return $e->certificate_no;
        }
        $no = $this->next_number('shra_next_certificate_no', 'SHRA-C-', 4);
        $this->db->where('id', $e->id)->update(db_prefix() . 'shra_enrollments', [
            'certificate_no'        => $no,
            'certificate_issued_at' => date('Y-m-d H:i:s'),
            'certificate_issued_by' => is_staff_logged_in() ? get_staff_user_id() : null,
            'status'                => 'completed',
            'completed_at'          => $e->completed_at ?: date('Y-m-d H:i:s'),
        ]);
        log_activity('SHRA certificate issued [' . $no . ' for ' . $e->rider_no . ']');

        return $no;
    }

    public function cancel_enrollment($id)
    {
        $this->db->where('id', (int) $id)->update(db_prefix() . 'shra_enrollments', ['status' => 'cancelled']);

        return $this->db->affected_rows() > 0;
    }

    /* ═══════════════════════ Attendance ═══════════════════════ */

    /**
     * Mark one class session on an enrollment. Returns the attendance id,
     * or a string error.
     */
    public function mark_attendance($enrollment_id, array $data = [])
    {
        $e = $this->db->where('id', (int) $enrollment_id)->get(db_prefix() . 'shra_enrollments')->row();
        if (!$e) {
            return 'Enrollment not found.';
        }
        if ($e->status !== 'active') {
            return 'This enrollment is ' . $e->status . '.';
        }
        if ($e->sessions_used >= $e->sessions_total) {
            return _l('shra_no_sessions_left');
        }
        if ($e->expires_at && strtotime($e->expires_at) < strtotime('today')) {
            $this->db->where('id', $e->id)->update(db_prefix() . 'shra_enrollments', ['status' => 'expired']);

            return 'This package expired on ' . _d($e->expires_at) . '.';
        }

        $session_no = (int) $e->sessions_used + 1;
        $date       = !empty($data['session_date']) ? to_sql_date($data['session_date']) : date('Y-m-d');

        $this->db->insert(db_prefix() . 'shra_attendance', [
            'enrollment_id' => $e->id,
            'rider_id'      => $e->rider_id,
            'session_no'    => $session_no,
            'session_date'  => $date,
            'session_time'  => !empty($data['session_time']) ? $data['session_time'] : date('H:i:s'),
            'trainer_id'    => !empty($data['trainer_id']) ? (int) $data['trainer_id'] : (is_staff_logged_in() ? get_staff_user_id() : null),
            'horse_name'    => isset($data['horse_name']) ? substr(trim((string) $data['horse_name']), 0, 100) : null,
            'notes'         => isset($data['notes']) ? substr(trim((string) $data['notes']), 0, 500) : null,
            'marked_by'     => is_staff_logged_in() ? get_staff_user_id() : null,
            'created_at'    => date('Y-m-d H:i:s'),
        ]);
        $att_id = $this->db->insert_id();

        $upd = ['sessions_used' => $session_no];
        if ($session_no >= $e->sessions_total) {
            $upd['status']       = 'completed';
            $upd['completed_at'] = date('Y-m-d H:i:s');
        }
        $this->db->where('id', $e->id)->update(db_prefix() . 'shra_enrollments', $upd);

        if (isset($upd['status']) && !$e->is_guest && get_option('shra_auto_certificate') == '1') {
            $this->issue_certificate($e->id);
        }

        return $att_id;
    }

    /** Undo the latest session of an enrollment (mistake correction). */
    public function undo_attendance($attendance_id)
    {
        $a = $this->db->where('id', (int) $attendance_id)->get(db_prefix() . 'shra_attendance')->row();
        if (!$a) {
            return false;
        }
        $latest = $this->db->where('enrollment_id', $a->enrollment_id)->order_by('session_no', 'DESC')->limit(1)
            ->get(db_prefix() . 'shra_attendance')->row();
        if (!$latest || $latest->id != $a->id) {
            return 'Only the most recent session of an enrollment can be undone.';
        }
        $this->db->where('id', $a->id)->delete(db_prefix() . 'shra_attendance');
        $e = $this->db->where('id', $a->enrollment_id)->get(db_prefix() . 'shra_enrollments')->row();
        if ($e) {
            $upd = ['sessions_used' => max(0, $e->sessions_used - 1)];
            if ($e->status === 'completed' && empty($e->certificate_no)) {
                $upd['status']       = 'active';
                $upd['completed_at'] = null;
            }
            $this->db->where('id', $e->id)->update(db_prefix() . 'shra_enrollments', $upd);
        }

        return true;
    }

    public function get_attendance($filters = [], $limit = 300)
    {
        $p = db_prefix();
        $this->db->select('a.*, r.full_name, r.rider_no, r.rider_type, e.package_name, e.sessions_total, e.enrollment_no,
            CONCAT(s.firstname, " ", s.lastname) AS trainer_name, CONCAT(m.firstname, " ", m.lastname) AS marked_by_name')
            ->from($p . 'shra_attendance a')
            ->join($p . 'shra_riders r', 'r.id = a.rider_id', 'left')
            ->join($p . 'shra_enrollments e', 'e.id = a.enrollment_id', 'left')
            ->join($p . 'staff s', 's.staffid = a.trainer_id', 'left')
            ->join($p . 'staff m', 'm.staffid = a.marked_by', 'left');

        if (!empty($filters['date'])) {
            $this->db->where('a.session_date', $filters['date']);
        }
        if (!empty($filters['from'])) {
            $this->db->where('a.session_date >=', $filters['from']);
        }
        if (!empty($filters['to'])) {
            $this->db->where('a.session_date <=', $filters['to']);
        }
        if (!empty($filters['rider_id'])) {
            $this->db->where('a.rider_id', (int) $filters['rider_id']);
        }
        if (!empty($filters['enrollment_id'])) {
            $this->db->where('a.enrollment_id', (int) $filters['enrollment_id']);
        }
        if (!empty($filters['trainer_id'])) {
            $this->db->where('a.trainer_id', (int) $filters['trainer_id']);
        }

        return $this->db->order_by('a.session_date', 'DESC')->order_by('a.id', 'DESC')->limit($limit)->get()->result();
    }

    /** Staff list for the trainer picker (active staff). */
    public function get_trainers()
    {
        return $this->db->select('staffid, firstname, lastname')->where('active', 1)
            ->order_by('firstname', 'ASC')->get(db_prefix() . 'staff')->result();
    }

    /* ═══════════════════════ Dashboard ═══════════════════════ */

    public function get_summary()
    {
        $p     = db_prefix();
        $today = date('Y-m-d');
        $month = date('Y-m-01');

        $row = $this->db->query("SELECT
            (SELECT COUNT(*) FROM {$p}shra_riders) AS riders,
            (SELECT COUNT(*) FROM {$p}shra_riders WHERE rider_type = 'learner') AS learners,
            (SELECT COUNT(*) FROM {$p}shra_riders WHERE DATE(created_at) >= '{$month}') AS riders_month,
            (SELECT COUNT(*) FROM {$p}shra_enrollments WHERE status = 'active') AS active_enrollments,
            (SELECT COUNT(*) FROM {$p}shra_attendance WHERE session_date = '{$today}') AS sessions_today,
            (SELECT COUNT(*) FROM {$p}shra_attendance WHERE session_date >= '{$month}') AS sessions_month,
            (SELECT COALESCE(SUM(paid_amount),0) FROM {$p}shra_enrollments WHERE DATE(created_at) = '{$today}' AND status <> 'cancelled') AS revenue_today,
            (SELECT COALESCE(SUM(paid_amount),0) FROM {$p}shra_enrollments WHERE DATE(created_at) >= '{$month}' AND status <> 'cancelled') AS revenue_month,
            (SELECT COUNT(*) FROM {$p}shra_enrollments WHERE certificate_no IS NOT NULL) AS certificates,
            (SELECT COUNT(*) FROM {$p}shra_enrollments WHERE status = 'active' AND sessions_total - sessions_used <= 2 AND is_guest = 0) AS ending_soon
        ")->row();

        return (array) $row;
    }

    /** Last 14 days of sessions for the dashboard sparkline. */
    public function sessions_by_day($days = 14)
    {
        $p    = db_prefix();
        $from = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
        $rows = $this->db->query("SELECT session_date, COUNT(*) AS c FROM {$p}shra_attendance WHERE session_date >= '{$from}' GROUP BY session_date")->result();
        $map  = [];
        foreach ($rows as $r) {
            $map[$r->session_date] = (int) $r->c;
        }
        $out = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $d     = date('Y-m-d', strtotime("-{$i} days"));
            $out[] = ['date' => $d, 'count' => $map[$d] ?? 0];
        }

        return $out;
    }
}
