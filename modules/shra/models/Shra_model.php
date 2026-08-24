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
        $r->preferred_package = null;
        if (!empty($r->preferred_package_id)) {
            $r->preferred_package = $this->get_package($r->preferred_package_id);
        }
    }

    public function get_riders($filters = [])
    {
        $p = db_prefix();
        $today = date('Y-m-d');
        $this->db->select("r.*,
            (SELECT COUNT(*) FROM {$p}shra_enrollments e WHERE e.rider_id = r.id AND e.status = 'active') AS active_enrollments,
            (SELECT COALESCE(SUM(e.sessions_total - e.sessions_used),0) FROM {$p}shra_enrollments e WHERE e.rider_id = r.id AND e.status = 'active') AS sessions_left,
            (SELECT MAX(a.session_date) FROM {$p}shra_attendance a WHERE a.rider_id = r.id) AS last_session,
            (SELECT COUNT(*) FROM {$p}shra_attendance a WHERE a.rider_id = r.id AND a.session_date = '{$today}') AS attended_today,
            (SELECT COUNT(*) FROM {$p}shra_enrollments e WHERE e.rider_id = r.id AND DATE(e.created_at) = '{$today}' AND e.status <> 'cancelled') AS billed_today,
            (SELECT COUNT(*) FROM {$p}shra_enrollments e WHERE e.rider_id = r.id AND e.status <> 'cancelled') AS bills,
            (SELECT e.id FROM {$p}shra_enrollments e WHERE e.rider_id = r.id AND e.status <> 'cancelled' AND e.invoice_id IS NOT NULL
                AND e.total - COALESCE((SELECT SUM(pr.amount) FROM {$p}invoicepaymentrecords pr WHERE pr.invoiceid = e.invoice_id), e.paid_amount) > 0.009 ORDER BY e.id ASC LIMIT 1) AS due_enrollment_id,
            " . $this->due_sql('r.id') . " AS total_due")
            ->from($p . 'shra_riders r');

        if (!empty($filters['view']) && $filters['view'] === 'today') {
            $this->db->where("(EXISTS (SELECT 1 FROM {$p}shra_attendance a WHERE a.rider_id = r.id AND a.session_date = '{$today}')
                OR EXISTS (SELECT 1 FROM {$p}shra_enrollments e WHERE e.rider_id = r.id AND DATE(e.created_at) = '{$today}' AND e.status <> 'cancelled'))", null, false);
        }
        if (!empty($filters['view']) && $filters['view'] === 'due') {
            $this->db->where($this->due_sql('r.id') . ' > 0.009', null, false);
        }

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

    /**
     * SQL expression: outstanding balance for a rider across all non-cancelled
     * enrollments. The invoice's payment records are the source of truth (so
     * payments recorded from Sales count too); enrollments without an invoice
     * fall back to their own paid_amount.
     */
    private function due_sql($rider_col)
    {
        $p = db_prefix();

        return "(SELECT COALESCE(SUM(GREATEST(0, e.total - COALESCE((SELECT SUM(pr.amount) FROM {$p}invoicepaymentrecords pr WHERE pr.invoiceid = e.invoice_id), e.paid_amount))),0)
            FROM {$p}shra_enrollments e WHERE e.rider_id = {$rider_col} AND e.status <> 'cancelled')";
    }

    /** Lightweight search for billing / attendance pickers. */
    public function search_riders($q, $limit = 12)
    {
        $p = db_prefix();
        $q = $this->db->escape_like_str(trim((string) $q));

        $today = date('Y-m-d');
        $this->db->select("r.id, r.rider_no, r.full_name, r.mobile, r.dob, r.rider_type, r.riding_level, r.membership_no, r.status, r.preferred_package_id,
            (SELECT COALESCE(SUM(e.sessions_total - e.sessions_used),0) FROM {$p}shra_enrollments e WHERE e.rider_id = r.id AND e.status = 'active') AS sessions_left,
            (SELECT COUNT(*) FROM {$p}shra_attendance a WHERE a.rider_id = r.id AND a.session_date = '{$today}') AS attended_today,
            " . $this->due_sql('r.id') . " AS total_due")
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

    /**
     * Switch a rider between guest and learner, issuing the membership number the
     * first time they become a learner. Used when a returning guest buys a course.
     */
    public function set_rider_type($id, $type)
    {
        $rider = $this->get_rider($id);
        $type  = $type === 'guest' ? 'guest' : 'learner';
        if (!$rider || $rider->rider_type === $type) {
            return false;
        }

        $upd = ['rider_type' => $type];
        if ($type === 'learner' && empty($rider->membership_no)) {
            $upd['membership_no']        = $this->next_number('shra_next_membership_no', 'SHRA-M-', 4);
            $upd['membership_issued_at'] = date('Y-m-d H:i:s');
        }
        $this->db->where('id', (int) $id)->update(db_prefix() . 'shra_riders', $upd);

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
            'place_of_birth', 'address', 'marital_status', 'riding_level', 'preferred_package_id', 'terms_accepted', 'terms_accepted_by', 'status', 'notes'];
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
        if (isset($out['preferred_package_id'])) {
            $out['preferred_package_id'] = (int) $out['preferred_package_id'] ?: null;
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

        // ── Duplicate guards ──
        $token = isset($opts['bill_token']) ? substr(preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $opts['bill_token']), 0, 40) : '';
        if ($token !== '') {
            $dup = $this->db->where('bill_token', $token)->get(db_prefix() . 'shra_enrollments')->row();
            if ($dup) {
                // Same form submitted twice (double click / refresh) — return the first result instead of billing again.
                return ['enrollment_id' => $dup->id, 'invoice_id' => $dup->invoice_id, 'duplicate' => true];
            }
        }
        if (empty($opts['force'])) {
            $recent = $this->db->where('rider_id', $rider->id)->where('package_id', $package->id)->where('status <>', 'cancelled')
                ->where('created_at >=', date('Y-m-d H:i:s', time() - 180))->get(db_prefix() . 'shra_enrollments')->row();
            if ($recent) {
                return ['needs_confirm' => true, 'reason' => 'recent', 'message' => $rider->full_name . ' was billed for "' . $package->name . '" less than 3 minutes ago (' . $recent->enrollment_no . '). Bill again?'];
            }
            $open = $this->active_enrollments($rider->id);
            if (count($open)) {
                $left = 0;
                foreach ($open as $o) {
                    $left += $o->sessions_total - $o->sessions_used;
                }
                return ['needs_confirm' => true, 'reason' => 'open', 'message' => $rider->full_name . ' already has ' . $left . ' unused session' . ($left == 1 ? '' : 's') . ' on an active package. Add another package anyway?'];
            }
            $due = $this->rider_due($rider->id);
            if ($due > 0.009) {
                return ['needs_confirm' => true, 'reason' => 'due', 'message' => $rider->full_name . ' still owes ' . shra_money($due) . ' on a previous bill. Collect that first, or continue with a new bill?'];
            }
        }

        $quote        = $this->quote($package, $opts['discount_percent'] ?? null);
        $payment_mode = (string) ($opts['payment_mode'] ?? '');
        if (!isset($opts['paid_amount']) || trim((string) $opts['paid_amount']) === '' || !is_numeric($opts['paid_amount'])) {
            return 'Enter the amount received (0 is allowed for an unpaid bill).';
        }
        $paid_amount  = (float) $opts['paid_amount'];
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

        $client = $this->db->where('userid', $rider->client_id)->get(db_prefix() . 'clients')->row();

        $mode_name = $payment_mode;
        if (is_numeric($payment_mode)) {
            $m = $this->payment_modes_model->get((int) $payment_mode);
            $mode_name = $m ? $m->name : $payment_mode;
        }

        $status = $paid_amount >= $quote['total'] ? 2 : ($paid_amount > 0 ? 3 : 1); // paid | partial | unpaid

        $invoice_data = $this->invoice_payload($rider, $package, $quote, $client, $status, [
            'source'                => 'counter billing',
            'allowed_payment_modes' => is_numeric($payment_mode) ? [$payment_mode] : [],
        ]);

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

        $enrolled = $this->add_enrollment($rider, $package, $quote, $invoice_id, $paid_amount, $mode_name, array_merge($opts, ['bill_token' => $token]));

        return $enrolled + ['invoice_id' => $invoice_id, 'quote' => $quote];
    }

    /**
     * Insert the sessions wallet for a bill and freeze the lead revenue credit.
     * Shared by the counter (create_bill) and by the online /join checkout, which
     * only reaches here once the gateway has confirmed the money.
     *
     * @return array ['enrollment_id', 'lead_id']
     */
    private function add_enrollment($rider, $package, array $quote, $invoice_id, $paid_amount, $mode_name, array $opts = [])
    {
        $start   = date('Y-m-d');
        $expires = $package->validity_days ? date('Y-m-d', strtotime("+{$package->validity_days} days")) : null;
        $token   = (string) ($opts['bill_token'] ?? '');

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
            'bill_token'       => $token !== '' ? $token : null,
            'start_date'       => $start,
            'expires_at'       => $expires,
            'status'           => 'active',
            'notes'            => isset($opts['notes']) ? substr(trim((string) $opts['notes']), 0, 500) : null,
            // No staff session on an online /join payment
            'created_by'       => get_staff_user_id() ?: null,
            'created_at'       => date('Y-m-d H:i:s'),
        ];
        $this->db->insert(db_prefix() . 'shra_enrollments', $enr);
        $enrollment_id = $this->db->insert_id();

        // A guest ride purchased by a guest rider: mark attended right away when asked
        if (!empty($opts['mark_now']) && $enrollment_id) {
            $this->mark_attendance($enrollment_id, ['trainer_id' => $opts['trainer_id'] ?? null]);
        }

        log_activity('SHRA bill created [Invoice #' . $invoice_id . ', ' . $rider->rider_no . ', ' . $package->name . ', ' . shra_money($quote['total']) . ']');

        // Leads desk: freeze revenue credit to the agent who brought this rider (never blocks billing)
        $lead_id = null;
        if ($enrollment_id && $this->db->table_exists(db_prefix() . 'shra_lead_attribution')) {
            $this->load->model('shra/shra_leads_model');
            $lead_id = $this->shra_leads_model->credit_revenue($enrollment_id, $invoice_id, $rider, [
                'lead_id'     => (int) ($opts['lead_id'] ?? 0),
                'credit_lead' => isset($opts['credit_lead']) ? (string) $opts['credit_lead'] : '1',
            ]);
        }

        return ['enrollment_id' => $enrollment_id, 'lead_id' => $lead_id];
    }

    /**
     * The Perfex invoice for one package sold to one rider. Shared by the counter
     * and by the online /join checkout so both raise an identical-looking bill.
     */
    private function invoice_payload($rider, $package, array $quote, $client, $status, array $opts = [])
    {
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

        return [
            'clientid'                 => $rider->client_id,
            'number'                   => get_option('next_invoice_number'),
            'date'                     => date('Y-m-d'),
            'duedate'                  => date('Y-m-d'),
            'currency'                 => get_base_currency()->id,
            'subtotal'                 => $quote['list_price'],
            'total'                    => $quote['total'],
            'discount_percent'         => $quote['discount_percent'],
            'discount_total'           => $quote['discount_amount'],
            'discount_type'            => $quote['discount_percent'] > 0 ? 'before_tax' : '',
            'status'                   => $status,
            'adminnote'                => 'SHRA ' . ($opts['source'] ?? 'billing') . ' · ' . $rider->rider_no . ' · ' . $package->name,
            'clientnote'               => $quote['discount_percent'] > 0 ? ($quote['discount_percent'] + 0) . '% ' . (get_option('shra_offer_label') ?: 'offer') . ' applied.' : '',
            'terms'                    => 'Sessions are first-come, first-served with no fixed time slots. Packages are non-transferable.',
            'show_quantity_as'         => 1,
            'newitems'                 => $items,
            'billing_street'           => (string) (($client->billing_street ?? '') ?: $rider->address),
            'billing_city'             => (string) ($client->billing_city ?? ''),
            'billing_state'            => (string) ($client->billing_state ?? ''),
            'billing_zip'              => (string) ($client->billing_zip ?? ''),
            'billing_country'          => (int) ($client->billing_country ?? 0),
            'include_shipping'         => 0,
            'show_shipping_on_invoice' => 0,
            'allowed_payment_modes'    => $opts['allowed_payment_modes'] ?? [],
        ];
    }

    /* ═══════════ Online checkout from the public /join page (v1.4) ═══════════
     * The invoice is raised when the rider starts the checkout so the gateway has
     * something to attach the payment to; the enrollment waits for the money.
     */

    /** The open (or paid) checkout attached to an invoice. */
    public function join_checkout_by_invoice($invoice_id)
    {
        if (!$this->db->table_exists(db_prefix() . 'shra_join_checkouts')) {
            return null;
        }

        return $this->db->where('invoice_id', (int) $invoice_id)->get(db_prefix() . 'shra_join_checkouts')->row();
    }

    /** The latest checkout a rider started, whatever its state. */
    public function join_checkout_for_rider($rider_id)
    {
        if (!$this->db->table_exists(db_prefix() . 'shra_join_checkouts')) {
            return null;
        }

        return $this->db->where('rider_id', (int) $rider_id)->order_by('id', 'DESC')->limit(1)
            ->get(db_prefix() . 'shra_join_checkouts')->row();
    }

    /**
     * Raise (or re-use) the invoice a rider is about to pay online.
     *
     * A rider who abandons the gateway and comes back gets the SAME unpaid
     * invoice rather than a new one each time — only the amount they intend to
     * pay is updated.
     *
     * @param  float  $amount  What the rider chose to pay now
     * @return array|string    ['checkout_id','invoice_id','hash','amount','total'] or an error message
     */
    public function create_join_invoice($rider_id, $package_id, $amount, $gateway = '')
    {
        $rider   = $this->get_rider($rider_id);
        $package = $this->get_package($package_id);

        if (!$rider || !$package || !$package->active) {
            return 'That plan is no longer available. Please pick another one.';
        }
        // A guest is a walk-in visitor with no membership, so a multi-session course
        // needs a full registration first. The other direction is fine — a member may
        // buy a one-off guest ride.
        if ($rider->rider_type === 'guest' && !(int) $package->is_guest) {
            return 'That plan needs a full membership registration. Please register at ' . shra_join_url() . ' or ask the reception desk.';
        }

        $quote = $this->quote($package);
        $total = (float) $quote['total'];
        $min   = shra_pay_min_amount($total);
        $amount = round((float) $amount, 2);

        if ($amount < $min - 0.009) {
            return 'The smallest amount you can pay now is ' . shra_money($min) . '.';
        }
        $amount = min($amount, $total);

        // Ensure a core customer exists (riders registered on the public form have none yet)
        if (!$rider->client_id) {
            $link = $this->ensure_client((array) $rider);
            if (empty($link['client_id'])) {
                return 'We could not open your account. Please pay at the reception desk.';
            }
            $this->db->where('id', $rider->id)->update(db_prefix() . 'shra_riders', $link);
            $rider->client_id  = $link['client_id'];
            $rider->contact_id = $link['contact_id'];
        }

        $this->load->model('invoices_model');

        // Reuse an unpaid checkout for the same plan instead of stacking invoices
        $open = $this->db->where('rider_id', $rider->id)->where('package_id', $package->id)
            ->where('status', 'pending')->order_by('id', 'DESC')->limit(1)
            ->get(db_prefix() . 'shra_join_checkouts')->row();

        if ($open) {
            $invoice = $this->invoices_model->get((int) $open->invoice_id);
            if ($invoice && (int) $invoice->status === 1) {
                $this->db->where('id', $open->id)->update(db_prefix() . 'shra_join_checkouts', [
                    'amount_intended' => $amount,
                    'kind'            => $amount >= $total - 0.009 ? 'full' : 'partial',
                    'gateway'         => $gateway !== '' ? substr($gateway, 0, 40) : $open->gateway,
                ]);

                return ['checkout_id' => (int) $open->id, 'invoice_id' => (int) $invoice->id,
                    'hash' => $invoice->hash, 'amount' => $amount, 'total' => $total];
            }
        }

        $client     = $this->db->where('userid', $rider->client_id)->get(db_prefix() . 'clients')->row();
        $invoice_id = $this->invoices_model->add(
            $this->invoice_payload($rider, $package, $quote, $client, 1, ['source' => 'online join'])
        );

        if (!$invoice_id) {
            return 'We could not start the payment. Please pay at the reception desk.';
        }

        $this->db->insert(db_prefix() . 'shra_join_checkouts', [
            'rider_id'        => $rider->id,
            'package_id'      => $package->id,
            'invoice_id'      => $invoice_id,
            'total'           => $total,
            'amount_intended' => $amount,
            'kind'            => $amount >= $total - 0.009 ? 'full' : 'partial',
            'gateway'         => substr($gateway, 0, 40),
            'status'          => 'pending',
            'created_at'      => date('Y-m-d H:i:s'),
        ]);
        $checkout_id = (int) $this->db->insert_id();

        $invoice = $this->invoices_model->get($invoice_id);

        return ['checkout_id' => $checkout_id, 'invoice_id' => (int) $invoice_id,
            'hash' => $invoice->hash, 'amount' => $amount, 'total' => $total];
    }

    /**
     * The gateway confirmed money against an invoice — create the sessions wallet.
     * Called from the `after_payment_added` hook, so it runs for the browser
     * callback AND for the webhook and must be safe to run more than once.
     *
     * @return int|null The enrollment id, or null when the invoice is not a /join checkout
     */
    public function fulfil_join_checkout($invoice_id)
    {
        $checkout = $this->join_checkout_by_invoice($invoice_id);
        if (!$checkout) {
            return null;
        }
        if ($checkout->enrollment_id) {
            // The rider is topping up a part payment — the wallet already exists,
            // it just has to catch up with what the invoice has now been paid.
            $this->sync_paid((int) $checkout->enrollment_id);

            return (int) $checkout->enrollment_id;
        }

        $rider   = $this->get_rider($checkout->rider_id);
        $package = $this->get_package($checkout->package_id);
        if (!$rider || !$package) {
            log_activity('SHRA join checkout #' . $checkout->id . ' paid but the rider or plan is gone.');

            return null;
        }

        $paid = (float) $this->db->select_sum('amount')->where('invoiceid', (int) $invoice_id)
            ->get(db_prefix() . 'invoicepaymentrecords')->row()->amount;

        $quote = [
            'list_price'       => (float) $checkout->total,
            'discount_percent' => 0,
            'discount_amount'  => 0,
            'total'            => (float) $checkout->total,
        ];
        // Re-derive the discount the same way the invoice did, so the wallet matches the bill
        $fresh = $this->quote($package);
        if (abs($fresh['total'] - (float) $checkout->total) < 0.01) {
            $quote = $fresh;
        }

        $enrolled = $this->add_enrollment($rider, $package, $quote, (int) $invoice_id,
            min($paid, (float) $checkout->total), 'Online · ' . ($checkout->gateway ?: 'gateway'), [
                'notes' => 'Paid online from the join page.',
            ]);

        $this->db->where('id', $checkout->id)->update(db_prefix() . 'shra_join_checkouts', [
            'enrollment_id' => $enrolled['enrollment_id'],
            'status'        => 'paid',
            'paid_at'       => date('Y-m-d H:i:s'),
        ]);

        // The plan is now bought, not just preferred
        $this->db->where('id', $rider->id)->update(db_prefix() . 'shra_riders', ['preferred_package_id' => $package->id]);

        return (int) $enrolled['enrollment_id'];
    }

    /* ═══════════════════════ Payments ═══════════════════════ */

    /** Outstanding balance for a rider (all non-cancelled enrollments). */
    public function rider_due($rider_id)
    {
        $row = $this->db->query('SELECT ' . $this->due_sql((int) $rider_id) . ' AS due')->row();

        return round((float) ($row ? $row->due : 0), 2);
    }

    /** Decorate an enrollment row with paid_real / due / pay_status. */
    private function decorate_enrollment($e)
    {
        if (!$e) {
            return $e;
        }
        $paid = $e->invoice_id && isset($e->invoice_paid) ? (float) $e->invoice_paid : (float) $e->paid_amount;
        $e->paid_real  = round($paid, 2);
        $e->due        = round(max(0, (float) $e->total - $paid), 2);
        $e->pay_status = $e->status === 'cancelled' ? 'cancelled' : ($e->due <= 0.009 ? 'paid' : ($paid > 0.009 ? 'partial' : 'unpaid'));

        return $e;
    }

    /** Payment records behind an enrollment's invoice. */
    public function get_payments($enrollment_id)
    {
        $p = db_prefix();
        $e = $this->db->select('invoice_id')->where('id', (int) $enrollment_id)->get($p . 'shra_enrollments')->row();
        if (!$e || !$e->invoice_id) {
            return [];
        }

        return $this->db->select('pr.*, pm.name AS mode_name')
            ->from($p . 'invoicepaymentrecords pr')
            ->join($p . 'payment_modes pm', 'pm.id = pr.paymentmode', 'left')
            ->where('pr.invoiceid', (int) $e->invoice_id)
            ->order_by('pr.id', 'ASC')->get()->result();
    }

    /**
     * Collect a further payment against an enrollment (partial bills).
     * Never exceeds the outstanding balance. Returns the payment id or a string error.
     */
    public function collect_payment($enrollment_id, $amount, $payment_mode = '', $reference = '', $note = '')
    {
        $e = $this->get_enrollment($enrollment_id);
        if (!$e) {
            return 'Enrollment not found.';
        }
        if ($e->status === 'cancelled') {
            return 'This enrollment is cancelled.';
        }
        if (!$e->invoice_id) {
            return 'This enrollment has no invoice to record the payment against.';
        }
        $amount = round((float) $amount, 2);
        if ($amount <= 0) {
            return 'Enter an amount greater than zero.';
        }
        if ($e->due <= 0.009) {
            return 'Nothing is due on this bill — it is already fully paid.';
        }
        if ($amount > $e->due + 0.009) {
            return 'Amount exceeds the balance due (' . shra_money($e->due) . ').';
        }
        // Guard: identical payment recorded in the last 2 minutes = accidental double submit
        $dup = $this->db->where('invoiceid', $e->invoice_id)->where('amount', $amount)
            ->where('daterecorded >=', date('Y-m-d H:i:s', time() - 120))->get(db_prefix() . 'invoicepaymentrecords')->row();
        if ($dup) {
            return 'An identical payment of ' . shra_money($amount) . ' was recorded moments ago. Refresh to see it.';
        }

        $this->db->insert(db_prefix() . 'invoicepaymentrecords', [
            'invoiceid'     => $e->invoice_id,
            'amount'        => $amount,
            'paymentmode'   => (string) $payment_mode,
            'date'          => date('Y-m-d'),
            'daterecorded'  => date('Y-m-d H:i:s'),
            'transactionid' => substr(trim((string) $reference), 0, 100),
            'note'          => 'SHRA counter · balance payment · ' . $e->package_name . ($note !== '' ? ' · ' . substr(trim((string) $note), 0, 200) : ''),
        ]);
        $pid = $this->db->insert_id();
        update_invoice_status($e->invoice_id, true);
        $this->load->model('invoices_model');
        $this->invoices_model->save_formatted_number($e->invoice_id);
        $this->sync_paid($e->id, $payment_mode);

        log_activity('SHRA payment collected [' . shra_money($amount) . ' on ' . $e->enrollment_no . ' / Invoice #' . $e->invoice_id . ']');

        return $pid;
    }

    /** Keep enrollment.paid_amount equal to the invoice's recorded payments. */
    public function sync_paid($enrollment_id, $payment_mode = null)
    {
        $p = db_prefix();
        $e = $this->db->where('id', (int) $enrollment_id)->get($p . 'shra_enrollments')->row();
        if (!$e || !$e->invoice_id) {
            return;
        }
        $sum = $this->db->select_sum('amount')->where('invoiceid', $e->invoice_id)->get($p . 'invoicepaymentrecords')->row()->amount;
        $upd = ['paid_amount' => round((float) $sum, 2)];
        if ($payment_mode !== null && $payment_mode !== '') {
            $name = $payment_mode;
            if (is_numeric($payment_mode)) {
                $m    = $this->db->where('id', (int) $payment_mode)->get($p . 'payment_modes')->row();
                $name = $m ? $m->name : $payment_mode;
            }
            $upd['payment_mode'] = $e->payment_mode && stripos($e->payment_mode, $name) === false ? $e->payment_mode . ', ' . $name : $name;
        }
        $this->db->where('id', $e->id)->update($p . 'shra_enrollments', $upd);

        if ($this->db->table_exists($p . 'shra_lead_attribution')) {
            $this->load->model('shra/shra_leads_model');
            $this->shra_leads_model->sync_attribution_paid($e->id);
        }
    }

    public function get_enrollment($id)
    {
        $p = db_prefix();

        return $this->decorate_enrollment($this->db->select("e.*, r.full_name, r.rider_no, r.mobile, r.email, r.address, r.dob, r.rider_type, r.riding_level, r.membership_no, r.guardian_name, r.is_minor, i.number AS invoice_number, i.formatted_number AS invoice_formatted, i.hash AS invoice_hash, i.status AS invoice_status,
            (SELECT COALESCE(SUM(pr.amount),0) FROM {$p}invoicepaymentrecords pr WHERE pr.invoiceid = e.invoice_id) AS invoice_paid")
            ->from($p . 'shra_enrollments e')
            ->join($p . 'shra_riders r', 'r.id = e.rider_id', 'left')
            ->join($p . 'invoices i', 'i.id = e.invoice_id', 'left')
            ->where('e.id', (int) $id)->get()->row());
    }

    public function get_enrollments($filters = [], $limit = 300)
    {
        $p = db_prefix();
        $this->db->select("e.*, r.full_name, r.rider_no, r.mobile, r.rider_type, r.membership_no, i.status AS invoice_status, i.hash AS invoice_hash, i.formatted_number AS invoice_formatted,
            (SELECT COALESCE(SUM(pr.amount),0) FROM {$p}invoicepaymentrecords pr WHERE pr.invoiceid = e.invoice_id) AS invoice_paid")
            ->from($p . 'shra_enrollments e')
            ->join($p . 'shra_riders r', 'r.id = e.rider_id', 'left')
            ->join($p . 'invoices i', 'i.id = e.invoice_id', 'left');

        if (!empty($filters['rider_id'])) {
            $this->db->where('e.rider_id', (int) $filters['rider_id']);
        }
        if (!empty($filters['rider_ids'])) {
            $this->db->where_in('e.rider_id', array_map('intval', $filters['rider_ids']));
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

        if (!empty($filters['due'])) {
            $this->db->where("e.status <> 'cancelled' AND e.total - COALESCE((SELECT SUM(pr.amount) FROM {$p}invoicepaymentrecords pr WHERE pr.invoiceid = e.invoice_id), e.paid_amount) > 0.009", null, false);
        }

        $rows = $this->db->order_by('e.id', 'DESC')->limit($limit)->get()->result();
        foreach ($rows as $r) {
            $this->decorate_enrollment($r);
        }

        return $rows;
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

        if (empty($data['force'])) {
            $same_day = $this->db->where('rider_id', $e->rider_id)->where('session_date', $date)->get(db_prefix() . 'shra_attendance')->row();
            if ($same_day) {
                return ['needs_confirm' => true, 'message' => 'This rider already has a session marked on ' . _d($date) . ' (session ' . $same_day->session_no . '). Mark a second session on the same day?'];
            }
        }

        $trainer_id = !empty($data['trainer_id']) ? (int) $data['trainer_id'] : $this->default_trainer_id();

        $this->db->insert(db_prefix() . 'shra_attendance', [
            'enrollment_id' => $e->id,
            'rider_id'      => $e->rider_id,
            'session_no'    => $session_no,
            'session_date'  => $date,
            'session_time'  => !empty($data['session_time']) ? $data['session_time'] : date('H:i:s'),
            'trainer_id'    => $trainer_id,
            'horse_name'    => isset($data['horse_name']) ? substr(trim((string) $data['horse_name']), 0, 100) : null,
            'notes'         => isset($data['notes']) ? substr(trim((string) $data['notes']), 0, 500) : null,
            'forced'        => !empty($data['force']) ? 1 : 0,
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
            t.name AS trainer_name, CONCAT(m.firstname, " ", m.lastname) AS marked_by_name')
            ->from($p . 'shra_attendance a')
            ->join($p . 'shra_riders r', 'r.id = a.rider_id', 'left')
            ->join($p . 'shra_enrollments e', 'e.id = a.enrollment_id', 'left')
            ->join($p . 'shra_trainers t', 't.id = a.trainer_id', 'left')
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

    /* ═══════════════════════ Trainers ═══════════════════════ */

    public function get_trainers($active_only = true)
    {
        $p = db_prefix();
        $this->db->select("t.*, (SELECT COUNT(*) FROM {$p}shra_attendance a WHERE a.trainer_id = t.id) AS sessions,
            (SELECT COUNT(*) FROM {$p}shra_attendance a WHERE a.trainer_id = t.id AND a.session_date = '" . date('Y-m-d') . "') AS sessions_today")
            ->from($p . 'shra_trainers t');
        if ($active_only) {
            $this->db->where('t.active', 1);
        }

        return $this->db->order_by('t.sort_order', 'ASC')->order_by('t.name', 'ASC')->get()->result();
    }

    public function get_trainer($id)
    {
        return $this->db->where('id', (int) $id)->get(db_prefix() . 'shra_trainers')->row();
    }

    public function save_trainer(array $in, $id = null)
    {
        $name = trim((string) ($in['name'] ?? ''));
        if ($name === '') {
            return false;
        }
        $data = [
            'name'       => substr($name, 0, 191),
            'mobile'     => substr(trim((string) ($in['mobile'] ?? '')), 0, 30) ?: null,
            'specialty'  => substr(trim((string) ($in['specialty'] ?? '')), 0, 191) ?: null,
            'staff_id'   => !empty($in['staff_id']) ? (int) $in['staff_id'] : null,
            'active'     => !empty($in['active']) ? 1 : 0,
            'sort_order' => (int) ($in['sort_order'] ?? 0),
        ];
        if ($id) {
            $this->db->where('id', (int) $id)->update(db_prefix() . 'shra_trainers', $data);

            return (int) $id;
        }
        $this->db->insert(db_prefix() . 'shra_trainers', $data);

        return $this->db->insert_id();
    }

    /** Deactivate when sessions reference the trainer, delete otherwise. */
    public function delete_trainer($id)
    {
        $used = $this->db->where('trainer_id', (int) $id)->count_all_results(db_prefix() . 'shra_attendance');
        if ($used > 0) {
            $this->db->where('id', (int) $id)->update(db_prefix() . 'shra_trainers', ['active' => 0]);

            return 'deactivated';
        }
        $this->db->where('id', (int) $id)->delete(db_prefix() . 'shra_trainers');

        return 'deleted';
    }

    /** Trainer linked to the logged-in staff member, if any. */
    public function default_trainer_id()
    {
        if (!is_staff_logged_in()) {
            return null;
        }
        $t = $this->db->select('id')->where('staff_id', get_staff_user_id())->where('active', 1)->get(db_prefix() . 'shra_trainers')->row();

        return $t ? (int) $t->id : null;
    }

    /* ═══════════════════════ Reports ═══════════════════════ */

    /**
     * Finance + academy analytics for a date range (inclusive, Y-m-d).
     * Collections are taken from the invoice payment records of SHRA bills,
     * so balance payments recorded later land on the day they were received.
     */
    public function report($from, $to)
    {
        $p  = db_prefix();
        $f  = $this->db->escape($from);
        $t  = $this->db->escape($to);
        $q  = function ($sql) { return $this->db->query($sql)->result(); };
        $r1 = function ($sql) { return (array) $this->db->query($sql)->row(); };

        // Payments in range that belong to SHRA enrollments
        $pay_join = "FROM {$p}invoicepaymentrecords pr
            JOIN {$p}shra_enrollments e ON e.invoice_id = pr.invoiceid AND e.status <> 'cancelled'
            LEFT JOIN {$p}payment_modes pm ON pm.id = pr.paymentmode
            LEFT JOIN {$p}shra_riders r ON r.id = e.rider_id
            WHERE DATE(pr.date) BETWEEN {$f} AND {$t}";

        $out = [];
        $out['kpi'] = $r1("SELECT
            (SELECT COALESCE(SUM(pr.amount),0) {$pay_join}) AS collected,
            (SELECT COUNT(*) {$pay_join}) AS payments,
            (SELECT COALESCE(SUM(e.total),0) FROM {$p}shra_enrollments e WHERE DATE(e.created_at) BETWEEN {$f} AND {$t} AND e.status <> 'cancelled') AS billed,
            (SELECT COALESCE(SUM(e.list_price),0) FROM {$p}shra_enrollments e WHERE DATE(e.created_at) BETWEEN {$f} AND {$t} AND e.status <> 'cancelled') AS list_value,
            (SELECT COALESCE(SUM(e.discount_amount),0) FROM {$p}shra_enrollments e WHERE DATE(e.created_at) BETWEEN {$f} AND {$t} AND e.status <> 'cancelled') AS discounts,
            (SELECT COUNT(*) FROM {$p}shra_enrollments e WHERE DATE(e.created_at) BETWEEN {$f} AND {$t} AND e.status <> 'cancelled') AS packages_sold,
            (SELECT COALESCE(SUM(e.sessions_total),0) FROM {$p}shra_enrollments e WHERE DATE(e.created_at) BETWEEN {$f} AND {$t} AND e.status <> 'cancelled') AS sessions_sold,
            (SELECT COALESCE(SUM(GREATEST(0, e.total - COALESCE((SELECT SUM(x.amount) FROM {$p}invoicepaymentrecords x WHERE x.invoiceid = e.invoice_id), e.paid_amount))),0)
                FROM {$p}shra_enrollments e WHERE DATE(e.created_at) BETWEEN {$f} AND {$t} AND e.status <> 'cancelled') AS due_in_range,
            (SELECT COALESCE(SUM(GREATEST(0, e.total - COALESCE((SELECT SUM(x.amount) FROM {$p}invoicepaymentrecords x WHERE x.invoiceid = e.invoice_id), e.paid_amount))),0)
                FROM {$p}shra_enrollments e WHERE e.status <> 'cancelled') AS due_total,
            (SELECT COUNT(*) FROM {$p}shra_attendance a WHERE a.session_date BETWEEN {$f} AND {$t}) AS sessions_held,
            (SELECT COUNT(DISTINCT a.rider_id) FROM {$p}shra_attendance a WHERE a.session_date BETWEEN {$f} AND {$t}) AS riders_rode,
            (SELECT COUNT(DISTINCT a.session_date) FROM {$p}shra_attendance a WHERE a.session_date BETWEEN {$f} AND {$t}) AS riding_days,
            (SELECT COUNT(*) FROM {$p}shra_riders r WHERE DATE(r.created_at) BETWEEN {$f} AND {$t}) AS new_riders,
            (SELECT COUNT(*) FROM {$p}shra_riders r WHERE DATE(r.created_at) BETWEEN {$f} AND {$t} AND r.rider_type = 'learner') AS new_learners,
            (SELECT COUNT(*) FROM {$p}shra_riders r WHERE DATE(r.created_at) BETWEEN {$f} AND {$t} AND r.source = 'self') AS new_self,
            (SELECT COUNT(*) FROM {$p}shra_enrollments e WHERE DATE(e.certificate_issued_at) BETWEEN {$f} AND {$t}) AS certificates,
            (SELECT COUNT(*) FROM {$p}shra_enrollments e WHERE DATE(e.completed_at) BETWEEN {$f} AND {$t} AND e.status = 'completed') AS completed,
            (SELECT COUNT(*) FROM {$p}shra_enrollments e WHERE e.status = 'active') AS active_packages,
            (SELECT COALESCE(SUM(e.sessions_total - e.sessions_used),0) FROM {$p}shra_enrollments e WHERE e.status = 'active') AS sessions_outstanding,
            (SELECT COUNT(*) FROM {$p}shra_riders) AS riders_total
        ");

        $out['by_mode'] = $q("SELECT COALESCE(pm.name, NULLIF(pr.paymentmode,''), 'Unspecified') AS mode, COUNT(*) AS n, SUM(pr.amount) AS amount {$pay_join} GROUP BY mode ORDER BY amount DESC");
        $out['by_day']  = $q("SELECT DATE(pr.date) AS d, SUM(pr.amount) AS amount, COUNT(*) AS n {$pay_join} GROUP BY DATE(pr.date) ORDER BY d");
        $out['sessions_by_day'] = $q("SELECT a.session_date AS d, COUNT(*) AS n FROM {$p}shra_attendance a WHERE a.session_date BETWEEN {$f} AND {$t} GROUP BY a.session_date ORDER BY d");
        $out['payments'] = $q("SELECT pr.id, pr.date, pr.amount, pr.transactionid, COALESCE(pm.name, pr.paymentmode) AS mode, e.enrollment_no, e.package_name, e.rider_id, r.full_name, r.rider_no {$pay_join} ORDER BY pr.date DESC, pr.id DESC LIMIT 500");

        $enr_where = "FROM {$p}shra_enrollments e WHERE DATE(e.created_at) BETWEEN {$f} AND {$t} AND e.status <> 'cancelled'";
        $out['by_package']  = $q("SELECT e.package_name, e.audience, e.is_guest, COUNT(*) AS n, SUM(e.sessions_total) AS sessions, SUM(e.list_price) AS list_value, SUM(e.discount_amount) AS discounts, SUM(e.total) AS billed,
            SUM(COALESCE((SELECT SUM(x.amount) FROM {$p}invoicepaymentrecords x WHERE x.invoiceid = e.invoice_id), e.paid_amount)) AS collected {$enr_where} GROUP BY e.package_name, e.audience, e.is_guest ORDER BY billed DESC");
        $out['by_audience'] = $q("SELECT e.audience AS k, COUNT(*) AS n, SUM(e.total) AS billed {$enr_where} GROUP BY e.audience");
        $out['by_type']     = $q("SELECT IF(e.is_guest = 1, 'guest', 'learner') AS k, COUNT(*) AS n, SUM(e.total) AS billed {$enr_where} GROUP BY k");

        $out['by_trainer'] = $q("SELECT COALESCE(t.name, 'Unassigned') AS trainer, COUNT(*) AS n, COUNT(DISTINCT a.rider_id) AS riders, COUNT(DISTINCT a.session_date) AS days
            FROM {$p}shra_attendance a LEFT JOIN {$p}shra_trainers t ON t.id = a.trainer_id WHERE a.session_date BETWEEN {$f} AND {$t} GROUP BY trainer ORDER BY n DESC");
        $out['by_weekday'] = $q("SELECT DAYOFWEEK(a.session_date) AS dow, COUNT(*) AS n FROM {$p}shra_attendance a WHERE a.session_date BETWEEN {$f} AND {$t} GROUP BY dow");
        $out['by_hour']    = $q("SELECT HOUR(a.session_time) AS h, COUNT(*) AS n FROM {$p}shra_attendance a WHERE a.session_date BETWEEN {$f} AND {$t} AND a.session_time IS NOT NULL GROUP BY h ORDER BY h");
        $out['top_riders'] = $q("SELECT r.id, r.full_name, r.rider_no, r.rider_type, COUNT(*) AS n, MAX(a.session_date) AS last_session
            FROM {$p}shra_attendance a JOIN {$p}shra_riders r ON r.id = a.rider_id WHERE a.session_date BETWEEN {$f} AND {$t} GROUP BY r.id ORDER BY n DESC, r.full_name ASC LIMIT 10");
        $out['top_horses'] = $q("SELECT a.horse_name, COUNT(*) AS n FROM {$p}shra_attendance a WHERE a.session_date BETWEEN {$f} AND {$t} AND a.horse_name IS NOT NULL AND a.horse_name <> '' GROUP BY a.horse_name ORDER BY n DESC LIMIT 8");
        $out['riders_by_source'] = $q("SELECT r.source AS k, r.rider_type, COUNT(*) AS n FROM {$p}shra_riders r WHERE DATE(r.created_at) BETWEEN {$f} AND {$t} GROUP BY r.source, r.rider_type");
        $out['riders_by_level']  = $q("SELECT r.riding_level AS k, COUNT(*) AS n FROM {$p}shra_riders r WHERE r.rider_type = 'learner' GROUP BY r.riding_level ORDER BY n DESC");
        $out['age_bands'] = $q("SELECT CASE WHEN r.dob IS NULL THEN 'Unknown' WHEN TIMESTAMPDIFF(YEAR, r.dob, CURDATE()) < 8 THEN 'Under 8' WHEN TIMESTAMPDIFF(YEAR, r.dob, CURDATE()) < 13 THEN '8–12'
            WHEN TIMESTAMPDIFF(YEAR, r.dob, CURDATE()) < 18 THEN '13–17' WHEN TIMESTAMPDIFF(YEAR, r.dob, CURDATE()) < 30 THEN '18–29' WHEN TIMESTAMPDIFF(YEAR, r.dob, CURDATE()) < 45 THEN '30–44' ELSE '45+' END AS k, COUNT(*) AS n
            FROM {$p}shra_riders r GROUP BY k ORDER BY FIELD(k,'Under 8','8–12','13–17','18–29','30–44','45+','Unknown')");
        $out['dues'] = $q("SELECT e.id, e.enrollment_no, e.package_name, e.created_at, e.total, e.rider_id, r.full_name, r.rider_no, r.mobile,
            GREATEST(0, e.total - COALESCE((SELECT SUM(x.amount) FROM {$p}invoicepaymentrecords x WHERE x.invoiceid = e.invoice_id), e.paid_amount)) AS due
            FROM {$p}shra_enrollments e JOIN {$p}shra_riders r ON r.id = e.rider_id WHERE e.status <> 'cancelled' HAVING due > 0.009 ORDER BY due DESC LIMIT 100");
        $out['expiring'] = $q("SELECT e.id, e.package_name, e.expires_at, e.sessions_total - e.sessions_used AS left_n, r.full_name, r.rider_no, r.id AS rider_id
            FROM {$p}shra_enrollments e JOIN {$p}shra_riders r ON r.id = e.rider_id WHERE e.status = 'active' AND e.expires_at IS NOT NULL AND e.expires_at <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) ORDER BY e.expires_at ASC LIMIT 50");

        return $out;
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
            (SELECT COUNT(*) FROM {$p}shra_enrollments WHERE status = 'active' AND sessions_total - sessions_used <= 2 AND is_guest = 0) AS ending_soon,
            (SELECT COALESCE(SUM(GREATEST(0, e.total - COALESCE((SELECT SUM(pr.amount) FROM {$p}invoicepaymentrecords pr WHERE pr.invoiceid = e.invoice_id), e.paid_amount))),0) FROM {$p}shra_enrollments e WHERE e.status <> 'cancelled') AS total_due
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

    /* ═══════════════════════ Payments desk (admin only) ═══════════════════════
     * One screen over every invoice and every receipt the academy has issued — the
     * counter bills raised here plus anything created from Sales — so the owner can
     * audit the money and remove a wrong entry. The controller gates all of it
     * behind is_admin(); nothing below re-checks.
     */

    /** Shared range + search WHERE for the money lists. */
    private function money_filters(array $f, $date_col, array $search_cols)
    {
        if (!empty($f['from'])) {
            $this->db->where($date_col . ' >=', $f['from']);
        }
        if (!empty($f['to'])) {
            $this->db->where($date_col . ' <=', $f['to']);
        }
        $q = trim((string) ($f['q'] ?? ''));
        if ($q !== '') {
            $like = $this->db->escape_like_str($q);
            $or   = [];
            foreach ($search_cols as $c) {
                $or[] = $c . " LIKE '%{$like}%' ESCAPE '!'";
            }
            $this->db->where('(' . implode(' OR ', $or) . ')');
        }
        $scope = $f['scope'] ?? 'all';
        if ($scope === 'shra') {
            $this->db->where('e.id IS NOT NULL');
        } elseif ($scope === 'other') {
            $this->db->where('e.id IS NULL');
        }
    }

    /**
     * Every invoice, newest first, carrying the rider and enrollment behind it when the
     * bill came from the SHRA counter. $filters: from, to, q, scope (all|shra|other), status.
     */
    public function all_invoices(array $filters = [], $limit = 500)
    {
        $p = db_prefix();
        $this->db->select("i.id, i.number, i.prefix, i.number_format, i.formatted_number, i.date, i.duedate, i.total, i.status, i.hash, i.clientid, i.adminnote,
            c.company,
            e.id AS enrollment_id, e.enrollment_no, e.package_name, e.status AS enrollment_status,
            r.id AS rider_id, r.full_name AS rider_name, r.rider_no, r.mobile,
            (SELECT COALESCE(SUM(pr.amount),0) FROM {$p}invoicepaymentrecords pr WHERE pr.invoiceid = i.id) AS paid,
            (SELECT COUNT(*) FROM {$p}invoicepaymentrecords pr WHERE pr.invoiceid = i.id) AS receipts", false)
            ->from($p . 'invoices i')
            ->join($p . 'shra_enrollments e', 'e.invoice_id = i.id', 'left')
            ->join($p . 'shra_riders r', 'r.id = e.rider_id', 'left')
            ->join($p . 'clients c', 'c.userid = i.clientid', 'left');

        $this->money_filters($filters, 'i.date', ['i.formatted_number', 'i.number', 'r.full_name', 'r.rider_no', 'r.mobile', 'c.company', 'e.enrollment_no', 'e.package_name']);

        if (isset($filters['status']) && $filters['status'] !== '' && is_numeric($filters['status'])) {
            $this->db->where('i.status', (int) $filters['status']);
        }

        $rows = $this->db->order_by('i.date DESC, i.id DESC')->limit((int) $limit)->get()->result();
        foreach ($rows as $row) {
            $row->paid = round((float) $row->paid, 2);
            $row->due  = round(max(0, (float) $row->total - $row->paid), 2);
        }

        return $rows;
    }

    /** Every receipt recorded against an invoice, newest first. Same filters as all_invoices(). */
    public function all_receipts(array $filters = [], $limit = 500)
    {
        $p = db_prefix();
        $this->db->select("pr.id, pr.invoiceid, pr.amount, pr.paymentmode, pr.date, pr.daterecorded, pr.transactionid, pr.note,
            pm.name AS mode_name,
            i.id AS inv_id, i.number, i.prefix, i.number_format, i.formatted_number, i.date AS invoice_date, i.status AS invoice_status, i.total AS invoice_total, i.hash AS invoice_hash, i.clientid,
            c.company,
            e.id AS enrollment_id, e.enrollment_no, e.package_name,
            r.id AS rider_id, r.full_name AS rider_name, r.rider_no, r.mobile", false)
            ->from($p . 'invoicepaymentrecords pr')
            ->join($p . 'payment_modes pm', 'pm.id = pr.paymentmode', 'left')
            ->join($p . 'invoices i', 'i.id = pr.invoiceid', 'left')
            ->join($p . 'shra_enrollments e', 'e.invoice_id = i.id', 'left')
            ->join($p . 'shra_riders r', 'r.id = e.rider_id', 'left')
            ->join($p . 'clients c', 'c.userid = i.clientid', 'left');

        $this->money_filters($filters, 'pr.date', ['i.formatted_number', 'i.number', 'pr.transactionid', 'pr.note', 'r.full_name', 'r.rider_no', 'r.mobile', 'c.company', 'e.enrollment_no']);

        return $this->db->order_by('pr.date DESC, pr.id DESC')->limit((int) $limit)->get()->result();
    }

    /** Headline numbers for the range the lists are showing. */
    public function money_summary($from, $to)
    {
        $p    = db_prefix();
        $from = date('Y-m-d', strtotime($from));
        $to   = date('Y-m-d', strtotime($to));
        $adv  = '0';
        if ($this->db->table_exists($p . 'shra_lead_payments')) {
            $adv = "(SELECT COALESCE(SUM(lp.amount),0) FROM {$p}shra_lead_payments lp WHERE DATE(lp.created_at) BETWEEN '{$from}' AND '{$to}')";
        }

        $row = $this->db->query("SELECT
            (SELECT COUNT(*) FROM {$p}invoices i WHERE i.date BETWEEN '{$from}' AND '{$to}') AS invoices,
            (SELECT COALESCE(SUM(i.total),0) FROM {$p}invoices i WHERE i.date BETWEEN '{$from}' AND '{$to}' AND i.status <> 5) AS billed,
            (SELECT COUNT(*) FROM {$p}invoicepaymentrecords pr WHERE pr.date BETWEEN '{$from}' AND '{$to}') AS receipts,
            (SELECT COALESCE(SUM(pr.amount),0) FROM {$p}invoicepaymentrecords pr WHERE pr.date BETWEEN '{$from}' AND '{$to}') AS collected,
            {$adv} AS advances,
            (SELECT COALESCE(SUM(GREATEST(0, i.total - COALESCE((SELECT SUM(pr.amount) FROM {$p}invoicepaymentrecords pr WHERE pr.invoiceid = i.id),0))),0)
                FROM {$p}invoices i WHERE i.date BETWEEN '{$from}' AND '{$to}' AND i.status <> 5) AS due
        ")->row();

        return (array) $row;
    }

    /**
     * Remove an invoice for good, together with its receipts. Perfex normally refuses
     * anything but the newest invoice (the delete_only_on_last_invoice setting); the
     * academy owner asked to be able to remove any of them, so we unlink what a "simple"
     * delete would leave dangling and let the core model do the rest.
     *
     * The enrollment billed on it keeps its row but loses the bill — detached and
     * cancelled, so nobody is chased for a balance on an invoice that no longer exists.
     * Returns true or a string error.
     */
    public function delete_invoice($id)
    {
        $p       = db_prefix();
        $id      = (int) $id;
        $invoice = $this->db->where('id', $id)->get($p . 'invoices')->row();
        if (!$invoice) {
            return 'Invoice not found.';
        }
        $label       = format_invoice_number($invoice);
        $enrollments = $this->db->where('invoice_id', $id)->get($p . 'shra_enrollments')->result();

        // Links the core model only clears on a full delete — cut them here so the simple
        // delete below cannot strand a record on a missing invoice.
        $this->db->where('invoiceid', $id)->update($p . 'expenses', ['invoiceid' => null]);
        $this->db->where('invoice_id', $id)->update($p . 'proposals', ['invoice_id' => null, 'date_converted' => null]);
        $this->db->where('invoiceid', $id)->update($p . 'estimates', ['invoiceid' => null]);

        $this->load->model('invoices_model');
        if (!$this->invoices_model->delete($id, true)) {
            return 'The invoice could not be deleted.';
        }

        foreach ($enrollments as $e) {
            $this->db->where('id', $e->id)->update($p . 'shra_enrollments', [
                'invoice_id'  => null,
                'paid_amount' => 0,
                'status'      => 'cancelled',
                'notes'       => substr(trim((string) $e->notes . ' [Invoice ' . $label . ' deleted ' . date('d M Y') . ']'), 0, 500),
            ]);
            if ($this->db->table_exists($p . 'shra_lead_attribution')) {
                $this->db->where('enrollment_id', $e->id)->update($p . 'shra_lead_attribution', ['amount_paid' => 0, 'amount_billed' => 0, 'invoice_id' => null]);
            }
        }

        log_activity('SHRA invoice deleted [' . $label . ' · ' . shra_money($invoice->total)
            . (count($enrollments) ? ' · ' . count($enrollments) . ' enrollment(s) cancelled' : '') . ']');

        return true;
    }

    /**
     * Remove one payment receipt. The invoice falls back to partial / unpaid and the
     * enrollment's paid_amount follows it. Returns true or a string error.
     */
    public function delete_receipt($id)
    {
        $p   = db_prefix();
        $pay = $this->db->where('id', (int) $id)->get($p . 'invoicepaymentrecords')->row();
        if (!$pay) {
            return 'Payment receipt not found.';
        }
        $invoice_id = (int) $pay->invoiceid;
        $label      = format_invoice_number($invoice_id);

        $this->load->model('payments_model');
        if (!$this->payments_model->delete($pay->id)) {
            return 'The receipt could not be deleted.';
        }

        foreach ($this->db->where('invoice_id', $invoice_id)->get($p . 'shra_enrollments')->result() as $e) {
            $this->sync_paid($e->id);
        }

        log_activity('SHRA payment receipt deleted [' . shra_money($pay->amount) . ' on invoice ' . $label . ']');

        return true;
    }
}
