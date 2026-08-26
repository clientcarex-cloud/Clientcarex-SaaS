<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Shra extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('shra/shra_model');
        $this->load->helper('shra/shra');

        if (!shra_can_access()) {
            access_denied('shra');
        }
    }

    private function need($cap)
    {
        if (!shra_can($cap)) {
            access_denied('shra');
        }
    }

    private function json($data)
    {
        header('Content-Type: application/json');
        echo json_encode($data);
    }

    /** Offline counter payment modes (cash / UPI / card). Self-heals Cash + UPI when the list is empty. */
    private function payment_modes()
    {
        $modes = $this->db->where('active', 1)->where('expenses_only', 0)->order_by('id', 'ASC')->get(db_prefix() . 'payment_modes')->result();
        if (!count($modes)) {
            foreach (['Cash', 'UPI'] as $n) {
                $this->db->insert(db_prefix() . 'payment_modes', ['name' => $n, 'description' => '', 'active' => 1, 'expenses_only' => 0, 'invoices_only' => 0, 'show_on_pdf' => 0, 'selected_by_default' => $n === 'Cash' ? 1 : 0]);
            }
            $modes = $this->db->where('active', 1)->where('expenses_only', 0)->order_by('id', 'ASC')->get(db_prefix() . 'payment_modes')->result();
        }

        return $modes;
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

    /* ═══════════════════════ Dashboard ═══════════════════════ */

    public function index()
    {
        if (!shra_can('view')) {
            redirect(shra_home_url());
        }

        $data['title']      = _l('shra');
        $data['leads']      = null;
        if (shra_leads_can('all') && $this->db->table_exists(db_prefix() . 'shra_lead_ext')) {
            $this->load->model('shra/shra_leads_model');
            $data['leads'] = $this->shra_leads_model->summary();
        }
        $data['summary']    = $this->shra_model->get_summary();
        $data['spark']      = $this->shra_model->sessions_by_day(14);
        $data['recent']     = $this->shra_model->get_enrollments([], 8);
        $data['today']      = $this->shra_model->get_attendance(['date' => date('Y-m-d')], 10);
        $data['new_riders'] = $this->shra_model->get_riders([]);
        $data['new_riders'] = array_slice($data['new_riders'], 0, 6);
        $data['offer']      = shra_offer();

        $this->load->view('dashboard', $data);
    }

    /* ═══════════════════════ Riders ═══════════════════════ */

    public function riders()
    {
        $this->need('view');

        $filters = [
            'q'      => $this->input->get('q'),
            'type'   => $this->input->get('type'),
            'status' => $this->input->get('status'),
            'level'  => $this->input->get('level'),
            'view'   => $this->input->get('view'),
        ];
        // Default landing: today's riders + amount due. Any search / filter switches to the full list.
        $has_filter = $filters['q'] !== null && $filters['q'] !== '' || $filters['type'] || $filters['status'] || $filters['level'];
        if (!$filters['view']) {
            $filters['view'] = $has_filter ? 'all' : 'home';
        }

        $data['title']         = _l('shra_riders');
        $data['filters']       = $filters;
        $data['levels']        = shra_riding_levels();
        $data['payment_modes'] = $this->payment_modes();
        if ($filters['view'] === 'home') {
            $data['today'] = $this->shra_model->get_riders(['view' => 'today']);
            $data['due']   = $this->shra_model->get_riders(['view' => 'due']);
        } else {
            $f = $filters;
            if ($f['view'] === 'all') {
                unset($f['view']);
            }
            $data['riders'] = $this->shra_model->get_riders($f);
        }

        $this->load->view('riders', $data);
    }

    /** Membership: every learner (membership holder) with the plan they chose, what they bought and where they stand. */
    public function membership()
    {
        $this->need('view');

        $filters = [
            'q'    => $this->input->get('q'),
            'show' => $this->input->get('show') ?: 'all',   // all | active | pending | none
        ];
        $rows = $this->shra_model->get_riders(['q' => $filters['q'], 'type' => 'learner']);
        $p    = db_prefix();
        $ids  = array_map(function ($r) { return (int) $r->id; }, $rows);
        $enr  = [];
        if (count($ids)) {
            foreach ($this->shra_model->get_enrollments(['rider_ids' => $ids], 2000) as $e) {
                $enr[$e->rider_id][] = $e;
            }
        }
        foreach ($rows as $r) {
            $r->enrollments = $enr[$r->id] ?? [];
            $r->current    = null;
            foreach ($r->enrollments as $e) {
                if ($e->status === 'active') { $r->current = $e; break; }
            }
            $r->last = $r->enrollments[0] ?? null;
            $r->stage = $r->current ? 'active' : (count($r->enrollments) ? 'none' : 'pending'); // pending = registered, nothing bought yet
        }
        if ($filters['show'] !== 'all') {
            $rows = array_values(array_filter($rows, function ($r) use ($filters) { return $r->stage === $filters['show']; }));
        }

        $data['title']         = 'Membership';
        $data['filters']       = $filters;
        $data['rows']          = $rows;
        $data['payment_modes'] = $this->payment_modes();

        $this->load->view('membership', $data);
    }

    /** @deprecated kept for old links */
    public function package_riders()
    {
        redirect(admin_url('shra/membership'));
    }

    public function rider_form($id = '')
    {
        $this->need($id ? 'edit' : 'create');

        if ($this->input->post()) {
            $post = $this->input->post(null, true);
            $post['terms_accepted'] = !empty($post['terms_accepted']) ? 1 : 0;

            if (empty($post['full_name']) || empty($post['mobile'])) {
                set_alert('danger', 'Rider name and mobile number are required.');
                redirect($_SERVER['HTTP_REFERER'] ?? admin_url('shra/riders'));
            }

            if ($id) {
                $this->shra_model->update_rider($id, $post);
                set_alert('success', _l('shra_rider_saved'));
                redirect(admin_url('shra/rider/' . $id));
            }

            $new_id = $this->shra_model->add_rider($post, 'staff');
            if ($new_id) {
                set_alert('success', _l('shra_rider_saved'));
                redirect(admin_url(($this->input->post('then') === 'bill' ? 'shra/billing?rider=' : 'shra/rider/') . $new_id));
            }
            set_alert('danger', 'Could not save the rider.');
        }

        $data['title']  = $id ? 'Edit rider' : 'New rider';
        $data['rider']  = $id ? $this->shra_model->get_rider($id) : null;
        if ($id && !$data['rider']) {
            show_404();
        }
        $data['levels'] = shra_riding_levels();
        $data['terms']  = get_option('shra_terms');

        $this->load->view('rider_form', $data);
    }

    public function rider($id)
    {
        $this->need('view');

        $rider = $this->shra_model->get_rider($id);
        if (!$rider) {
            show_404();
        }

        $data['title']       = $rider->full_name;
        $data['rider']       = $rider;
        $data['enrollments'] = $this->shra_model->get_enrollments(['rider_id' => $id]);
        $data['attendance']  = $this->shra_model->get_attendance(['rider_id' => $id], 100);
        $data['due']         = $this->shra_model->rider_due($id);
        $data['payment_modes'] = $this->payment_modes();
        $data['attended_today'] = false;
        foreach ($data['attendance'] as $a) {
            if ($a->session_date === date('Y-m-d')) {
                $data['attended_today'] = true;
            }
        }

        $this->load->view('rider', $data);
    }

    public function delete_rider($id)
    {
        $this->need('delete');
        if ($this->shra_model->delete_rider($id)) {
            set_alert('success', _l('shra_deleted'));
        }
        redirect(admin_url('shra/riders'));
    }

    /** Bulk delete from the riders list. Superadmin only — deliberately not a staff permission. */
    public function bulk_delete_riders()
    {
        if (!is_admin() || $this->input->method() !== 'post') {
            $this->json(['success' => false, 'message' => 'Only a superadmin can delete riders.']);

            return;
        }
        $ids = $this->input->post('ids');
        $ids = is_array($ids) ? array_unique(array_filter(array_map('intval', $ids))) : [];
        if (!count($ids)) {
            $this->json(['success' => false, 'message' => 'Nothing selected.']);

            return;
        }
        $done = 0;
        foreach ($ids as $id) {
            if ($this->shra_model->delete_rider($id)) {
                $done++;
            }
        }
        $this->json([
            'success' => $done > 0,
            'message' => $done > 0
                ? 'Deleted ' . $done . ' rider' . ($done === 1 ? '' : 's') . ($done < count($ids) ? ' — ' . (count($ids) - $done) . ' could not be deleted.' : '.')
                : 'Nothing was deleted.',
        ]);
    }

    /**
     * AJAX quick-add from the billing screen: name + mobile is enough.
     * Reuses an existing rider when the same mobile + name is already on file.
     */
    public function quick_rider()
    {
        if (!shra_can_billing() && !shra_can('create')) {
            $this->json(['success' => false, 'message' => 'Not allowed.']);

            return;
        }

        $name   = trim((string) $this->input->post('full_name'));
        $mobile = trim((string) $this->input->post('mobile'));
        $type   = $this->input->post('rider_type') === 'learner' ? 'learner' : 'guest';
        $dob    = (string) $this->input->post('dob');

        if ($name === '' || strlen(preg_replace('/\D+/', '', $mobile)) !== 10) {
            $this->json(['success' => false, 'message' => 'Name and a 10-digit mobile number are required.']);

            return;
        }

        $existing = $this->shra_model->find_rider_by_mobile($mobile);
        if ($existing && mb_strtolower(trim($existing->full_name)) === mb_strtolower($name)) {
            $this->json(['success' => true, 'rider' => $this->shra_model->search_riders($existing->rider_no, 1)[0] ?? $existing, 'existing' => true]);

            return;
        }

        $id = $this->shra_model->add_rider([
            'rider_type'   => $type,
            'full_name'    => $name,
            'mobile'       => $mobile,
            'dob'          => $dob,
            'riding_level' => shra_riding_levels()[0],
            'status'       => 'active',
        ], 'staff');

        if (!$id) {
            $this->json(['success' => false, 'message' => 'Could not create the rider.']);

            return;
        }

        $rider = $this->shra_model->get_rider($id);
        $this->json(['success' => true, 'rider' => $this->shra_model->search_riders($rider->rider_no, 1)[0] ?? $rider, 'existing' => false]);
    }

    /** AJAX rider search (billing + attendance pickers). */
    public function search()
    {
        $riders = $this->shra_model->search_riders((string) $this->input->get('q'));
        $this->json(['riders' => $riders]);
    }

    public function membership_pdf($rider_id)
    {
        $this->need('view');
        $rider = $this->shra_model->get_rider($rider_id);
        if (!$rider || $rider->rider_type !== 'learner') {
            show_404();
        }
        $this->output_membership($rider);
    }

    private function output_membership($rider)
    {
        require_once(module_dir_path(SHRA_MODULE_NAME, 'libraries/Shra_pdf.php'));
        $pdf = new Shra_pdf($this->brand(), 'P');
        $arr = (array) $rider;
        $arr['qr_text'] = shra_verify_url($rider->rider_no);
        $pdf->membership($arr);
        $pdf->Output('Membership-' . ($rider->membership_no ?: $rider->rider_no) . '.pdf', 'I');
    }

    /* ═══════════════════════ Billing ═══════════════════════ */

    public function billing()
    {
        if (!shra_can_billing()) {
            access_denied('shra');
        }
        $packages = $this->shra_model->get_packages(true);
        $map      = [];
        foreach ($packages as $p) {
            $map[$p->id] = $p;
        }

        $data['title']         = _l('shra_billing');
        $data['packages']      = $packages;
        $data['packages_map']  = $map;
        $data['offer']         = shra_offer();
        // Offline modes only (cash / UPI / card at the counter)
        $data['payment_modes'] = $this->payment_modes();
        $data['trainers']      = $this->shra_model->get_trainers();
        $data['preselect']     = null;
        if ($this->input->get('rider')) {
            $pre = $this->shra_model->get_rider((int) $this->input->get('rider'));
            if ($pre) {
                $data['preselect'] = $this->shra_model->search_riders($pre->rider_no, 1)[0] ?? $pre;
            }
        }
        $data['bill_token']    = bin2hex(random_bytes(12));
        $data['currency']      = get_base_currency();
        $data['lead_id']       = (int) $this->input->get('lead');
        $data['lead']          = null;
        if ($data['lead_id']) {
            $this->load->model('shra/shra_leads_model');
            $data['lead'] = $this->shra_leads_model->get($data['lead_id']);
        }

        $this->load->view('billing', $data);
    }

    /** AJAX: create the bill. */
    public function bill()
    {
        if (!shra_can_billing()) {
            $this->json(['success' => false, 'message' => 'Not allowed.']);

            return;
        }

        $rider_id   = (int) $this->input->post('rider_id');
        $package_id = (int) $this->input->post('package_id');
        $opts       = [
            'discount_percent' => $this->input->post('discount_percent') !== null && $this->input->post('discount_percent') !== '' ? (float) $this->input->post('discount_percent') : null,
            'paid_amount'      => $this->input->post('paid_amount'),
            'payment_mode'     => (string) $this->input->post('payment_mode'),
            'reference'        => (string) $this->input->post('reference'),
            'notes'            => (string) $this->input->post('notes'),
            'mark_now'         => (int) $this->input->post('mark_now') === 1,
            'trainer_id'       => (int) $this->input->post('trainer_id') ?: null,
            'start_date'       => (string) $this->input->post('start_date'),
            'batch'            => (string) $this->input->post('batch'),
            'bill_token'       => (string) $this->input->post('bill_token'),
            'force'            => (int) $this->input->post('force') === 1,
            'lead_id'          => (int) $this->input->post('lead_id'),
            'credit_lead'      => $this->input->post('credit_lead') !== null ? (string) $this->input->post('credit_lead') : '1',
        ];

        $res = $this->shra_model->create_bill($rider_id, $package_id, $opts);
        if (is_string($res)) {
            $this->json(['success' => false, 'message' => $res]);

            return;
        }
        if (!empty($res['needs_confirm'])) {
            $this->json(['success' => false, 'needs_confirm' => true, 'reason' => $res['reason'], 'message' => $res['message']]);

            return;
        }

        $e    = $this->shra_model->get_enrollment($res['enrollment_id']);
        $html = $this->load->view('partials/bill_done', ['e' => $e, 'duplicate' => !empty($res['duplicate'])], true);

        $this->json(['success' => true, 'html' => $html, 'invoice_id' => $res['invoice_id'], 'duplicate' => !empty($res['duplicate'])]);
    }

    /** AJAX: collect a balance payment on an existing bill. */
    public function collect()
    {
        if (!shra_can_billing()) {
            $this->json(['success' => false, 'message' => 'Not allowed.']);

            return;
        }
        $id  = (int) $this->input->post('enrollment_id');
        $res = $this->shra_model->collect_payment($id, $this->input->post('amount'), (string) $this->input->post('payment_mode'), (string) $this->input->post('reference'), (string) $this->input->post('note'));
        if (is_string($res)) {
            $this->json(['success' => false, 'message' => $res]);

            return;
        }
        $e = $this->shra_model->get_enrollment($id);
        $this->json([
            'success'    => true,
            'message'    => 'Payment recorded. ' . ($e->due > 0 ? shra_money($e->due) . ' still due.' : 'Bill fully paid.'),
            'due'        => $e->due,
            'receipt'    => admin_url('shra/receipt_pdf/' . $e->id),
            'pay_status' => $e->pay_status,
        ]);
    }

    /** Premium bill receipt (PDF). */
    public function receipt_pdf($enrollment_id)
    {
        if (!shra_can_billing() && !shra_can('view')) {
            access_denied('shra');
        }
        $e = $this->shra_model->get_enrollment($enrollment_id);
        if (!$e) {
            show_404();
        }
        require_once(module_dir_path(SHRA_MODULE_NAME, 'libraries/Shra_pdf.php'));
        $pdf = new Shra_pdf($this->brand(), 'P');
        $arr = (array) $e;
        $arr['payments']       = $this->shra_model->get_payments($e->id);
        $arr['invoice_label']  = $e->invoice_id ? format_invoice_number($e->invoice_id) : '';
        $arr['qr_text']        = shra_verify_url($e->rider_no);
        $arr['issued_by']      = get_staff_full_name(get_staff_user_id());
        $arr['offer_label']    = get_option('shra_offer_label');
        $arr['currency_symbol'] = get_base_currency()->symbol;
        $pdf->receipt($arr);
        $pdf->Output('Receipt-' . $e->enrollment_no . '.pdf', 'I');
    }

    /* ═══════════════════════ Payments desk ═══════════════════════
     * Superadmin-only ledger of every invoice, every receipt and every advance
     * taken on a call, with the power to remove a wrong entry. Guarded by
     * is_admin() on the list and again on each delete.
     */

    private function admin_only()
    {
        if (!is_admin()) {
            access_denied('shra');
        }
    }

    /**
     * Resolve the ?range / ?from / ?to query into [range, from, to].
     * Shared by Reports and the Payments desk.
     */
    private function date_range($default = 'month')
    {
        $range   = $this->input->get('range') ?: $default;
        $from    = $this->input->get('from');
        $to      = $this->input->get('to');
        $today   = date('Y-m-d');
        $presets = [
            'today'      => [$today, $today],
            'yesterday'  => [date('Y-m-d', strtotime('-1 day')), date('Y-m-d', strtotime('-1 day'))],
            'week'       => [date('Y-m-d', strtotime('monday this week')), $today],
            'month'      => [date('Y-m-01'), $today],
            'last_month' => [date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('last day of last month'))],
            'quarter'    => [date('Y-m-d', strtotime('-3 months')), $today],
            'year'       => [date('Y-01-01'), $today],
            'all'        => ['2000-01-01', $today],
        ];
        if ($range === 'custom' && $from && $to) {
            $from = date('Y-m-d', strtotime($from));
            $to   = date('Y-m-d', strtotime($to));
            if ($from > $to) {
                [$from, $to] = [$to, $from];
            }
        } else {
            if (!isset($presets[$range])) {
                $range = $default;
            }
            [$from, $to] = $presets[$range];
        }

        return [$range, $from, $to];
    }

    /**
     * Where a delete sends the user back to — the same tab, range and search they were on.
     * The posted query is rebuilt from a whitelist, never echoed back as given.
     */
    private function payments_referrer()
    {
        parse_str(ltrim((string) $this->input->post('back'), '?'), $in);
        $args = [];
        foreach (['view', 'range', 'from', 'to', 'q', 'scope', 'status'] as $k) {
            if (isset($in[$k]) && is_string($in[$k]) && $in[$k] !== '') {
                $args[$k] = $in[$k];
            }
        }

        return admin_url('shra/payments' . (count($args) ? '?' . http_build_query($args) : ''));
    }

    public function payments()
    {
        $this->admin_only();

        [$range, $from, $to] = $this->date_range('month');

        $view = $this->input->get('view');
        if (!in_array($view, ['invoices', 'receipts', 'advances'], true)) {
            $view = 'invoices';
        }
        $scope = $this->input->get('scope');
        if (!in_array($scope, ['all', 'shra', 'other'], true)) {
            $scope = 'all';
        }

        $filters = [
            'from'   => $from,
            'to'     => $to,
            'q'      => (string) $this->input->get('q'),
            'scope'  => $scope,
            'status' => $this->input->get('status'),
        ];

        $data['title']    = 'Payments';
        $data['range']    = $range;
        $data['from']     = $from;
        $data['to']       = $to;
        $data['view']     = $view;
        $data['filters']  = $filters;
        $data['summary']  = $this->shra_model->money_summary($from, $to);
        $data['invoices'] = $view === 'invoices' ? $this->shra_model->all_invoices($filters) : [];
        $data['receipts'] = $view === 'receipts' ? $this->shra_model->all_receipts($filters) : [];
        $data['advances'] = [];
        if ($view === 'advances' && $this->db->table_exists(db_prefix() . 'shra_lead_payments')) {
            $this->load->model('shra/shra_leads_model');
            $data['advances'] = $this->shra_leads_model->all_payments($filters);
        }

        $this->load->view('payments', $data);
    }

    /** Delete an invoice and its receipts (POST only — a link must never destroy money). */
    public function delete_invoice($id = '')
    {
        $this->admin_only();
        if (!$this->input->post()) {
            show_404();
        }
        $res = $this->shra_model->delete_invoice($id);
        if ($res === true) {
            set_alert('success', 'Invoice deleted along with its payment receipts.');
        } else {
            set_alert('danger', is_string($res) ? $res : 'The invoice could not be deleted.');
        }
        redirect($this->payments_referrer());
    }

    /** Delete a single payment receipt off an invoice. */
    public function delete_receipt($id = '')
    {
        $this->admin_only();
        if (!$this->input->post()) {
            show_404();
        }
        $res = $this->shra_model->delete_receipt($id);
        if ($res === true) {
            set_alert('success', 'Payment receipt deleted. The invoice balance was recalculated.');
        } else {
            set_alert('danger', is_string($res) ? $res : 'The receipt could not be deleted.');
        }
        redirect($this->payments_referrer());
    }

    /** Delete an advance taken on a call (tblshra_lead_payments + its screenshot). */
    public function delete_advance($id = '')
    {
        $this->admin_only();
        if (!$this->input->post()) {
            show_404();
        }
        $this->load->model('shra/shra_leads_model');
        $res = $this->shra_leads_model->delete_payment($id);
        if ($res === true) {
            set_alert('success', 'Advance payment deleted and taken off the lead total.');
        } else {
            set_alert('danger', is_string($res) ? $res : 'The advance could not be deleted.');
        }
        redirect($this->payments_referrer());
    }

    /* ═══════════════════════ Enrollments ═══════════════════════ */

    public function complete($id)
    {
        $this->need('edit');
        $this->shra_model->complete_enrollment($id, true);
        set_alert('success', _l('shra_course_completed'));
        redirect($_SERVER['HTTP_REFERER'] ?? admin_url('shra/membership'));
    }

    public function cancel_enrollment($id)
    {
        $this->need('delete');
        $this->shra_model->cancel_enrollment($id);
        set_alert('success', 'Enrollment cancelled. The invoice was left untouched — cancel it from Sales if needed.');
        redirect($_SERVER['HTTP_REFERER'] ?? admin_url('shra/membership'));
    }

    public function certificate($id)
    {
        $this->need('edit');
        $no = $this->shra_model->issue_certificate($id);
        if (!$no) {
            set_alert('danger', 'Certificates are only issued for learner packages.');
            redirect($_SERVER['HTTP_REFERER'] ?? admin_url('shra/membership'));
        }
        redirect(admin_url('shra/certificate_pdf/' . $id));
    }

    public function certificate_pdf($id)
    {
        $this->need('view');
        $e = $this->shra_model->get_enrollment($id);
        if (!$e || empty($e->certificate_no)) {
            show_404();
        }

        require_once(module_dir_path(SHRA_MODULE_NAME, 'libraries/Shra_pdf.php'));
        $pdf = new Shra_pdf($this->brand(), 'L');
        $arr = (array) $e;
        $arr['issued_at'] = $e->certificate_issued_at;
        $arr['qr_text']   = shra_verify_url($e->rider_no, $e->certificate_no);
        $pdf->certificate($arr);
        $pdf->Output('Certificate-' . $e->certificate_no . '.pdf', 'I');
    }

    /* ═══════════════════════ Attendance ═══════════════════════ */

    public function attendance()
    {
        if (!shra_can_attendance()) {
            access_denied('shra');
        }

        $date = $this->input->get('date') ?: date('Y-m-d');

        $data['title']     = _l('shra_attendance');
        $data['date']      = $date;
        $data['today']     = $this->shra_model->get_attendance(['date' => $date], 200);
        $data['trainers']  = $this->shra_model->get_trainers();
        $data['preselect'] = $this->input->get('rider') ? $this->shra_model->get_rider((int) $this->input->get('rider')) : null;

        $this->load->view('attendance', $data);
    }

    /** AJAX: active enrollments for a rider. */
    public function rider_enrollments()
    {
        $rows = $this->shra_model->active_enrollments((int) $this->input->get('rider_id'));
        foreach ($rows as $r) {
            $r->expires_at_f = $r->expires_at ? _d($r->expires_at) : '';
        }
        $this->json(['enrollments' => $rows]);
    }

    /** AJAX: mark one session. */
    public function mark()
    {
        if (!shra_can_attendance()) {
            $this->json(['success' => false, 'message' => 'Not allowed.']);

            return;
        }

        $enrollment_id = (int) $this->input->post('enrollment_id');
        $res           = $this->shra_model->mark_attendance($enrollment_id, [
            'trainer_id'   => (int) $this->input->post('trainer_id') ?: null,
            'horse_name'   => (string) $this->input->post('horse_name'),
            'notes'        => (string) $this->input->post('notes'),
            'session_date' => (string) $this->input->post('session_date'),
            'force'        => (int) $this->input->post('force') === 1,
        ]);

        if (is_string($res)) {
            $this->json(['success' => false, 'message' => $res]);

            return;
        }
        if (is_array($res) && !empty($res['needs_confirm'])) {
            $this->json(['success' => false, 'needs_confirm' => true, 'message' => $res['message']]);

            return;
        }

        $e         = $this->shra_model->get_enrollment($enrollment_id);
        $completed = $e && $e->status === 'completed';
        $date      = $this->input->post('session_date') ?: date('Y-m-d');

        $this->json([
            'success'    => true,
            'message'    => 'Session ' . $e->sessions_used . ' of ' . $e->sessions_total . ' marked for ' . $e->full_name . '.',
            'completed'  => $completed,
            'html'       => $completed ? $this->load->view('partials/course_done', ['e' => $e], true) : '',
            'today_html' => $this->load->view('partials/today_list', ['today' => $this->shra_model->get_attendance(['date' => $date], 200), 'date' => $date], true),
        ]);
    }

    public function undo()
    {
        if (!shra_can_attendance()) {
            $this->json(['success' => false, 'message' => 'Not allowed.']);

            return;
        }
        $a   = $this->db->where('id', (int) $this->input->post('id'))->get(db_prefix() . 'shra_attendance')->row();
        $res = $this->shra_model->undo_attendance((int) $this->input->post('id'));
        $date = $a ? $a->session_date : date('Y-m-d');

        $this->json([
            'success'    => $res === true,
            'message'    => is_string($res) ? $res : '',
            'today_html' => $this->load->view('partials/today_list', ['today' => $this->shra_model->get_attendance(['date' => $date], 200), 'date' => $date], true),
        ]);
    }

    public function attendance_log()
    {
        $this->need('view');

        $filters = [
            'from'       => $this->input->get('from') ?: date('Y-m-01'),
            'to'         => $this->input->get('to') ?: date('Y-m-d'),
            'trainer_id' => $this->input->get('trainer_id'),
        ];
        $data['title']    = 'Attendance log';
        $data['filters']  = $filters;
        $data['rows']     = $this->shra_model->get_attendance($filters, 1000);
        $data['trainers'] = $this->shra_model->get_trainers();

        $this->load->view('attendance_log', $data);
    }

    /* ═══════════════════════ Packages ═══════════════════════ */

    public function packages()
    {
        if (!is_admin()) {
            access_denied('shra');
        }

        if ($this->input->post()) {
            $id = (int) $this->input->post('id');
            if ($this->shra_model->save_package($this->input->post(null, true), $id ?: null)) {
                set_alert('success', _l('shra_package_saved'));
            } else {
                set_alert('danger', 'Package name is required.');
            }
            redirect(admin_url('shra/packages'));
        }

        $data['title']    = _l('shra_packages');
        $data['packages'] = $this->shra_model->get_packages(false);
        $data['offer']    = shra_offer();

        $this->load->view('packages', $data);
    }

    public function delete_package($id)
    {
        if (!is_admin()) {
            access_denied('shra');
        }
        $this->shra_model->delete_package($id);
        set_alert('success', _l('shra_deleted'));
        redirect(admin_url('shra/packages'));
    }

    /* ═══════════════════════ Reports ═══════════════════════ */

    public function reports()
    {
        if (!is_admin() && !shra_can('view')) {
            access_denied('shra');
        }

        [$range, $from, $to] = $this->date_range('month');

        $data['title']  = 'Reports';
        $data['range']  = $range;
        $data['from']   = $from;
        $data['to']     = $to;
        $data['report'] = $this->shra_model->report($from, $to);

        $data['lead_agents']  = [];
        $data['lead_sources'] = [];
        if (shra_leads_can('reports') && $this->db->table_exists(db_prefix() . 'shra_lead_attribution')) {
            $this->load->model('shra/shra_leads_model');
            $data['lead_agents']  = $this->shra_leads_model->team_stats($from, $to);
            $data['lead_sources'] = $this->shra_leads_model->source_stats($from, $to);
        }
        $this->load->view('reports', $data);
    }

    /* ═══════════════════════ Trainers ═══════════════════════ */

    public function trainers()
    {
        if (!is_admin()) {
            access_denied('shra');
        }

        if ($this->input->post()) {
            $id = (int) $this->input->post('id');
            if ($this->shra_model->save_trainer($this->input->post(null, true), $id ?: null)) {
                set_alert('success', 'Trainer saved.');
            } else {
                set_alert('danger', 'Trainer name is required.');
            }
            redirect(admin_url('shra/trainers'));
        }

        $data['title']    = 'Trainers';
        $data['trainers'] = $this->shra_model->get_trainers(false);
        $data['staff']    = $this->db->select('staffid, firstname, lastname')->where('active', 1)->order_by('firstname', 'ASC')->get(db_prefix() . 'staff')->result();

        $this->load->view('trainers', $data);
    }

    public function delete_trainer($id)
    {
        if (!is_admin()) {
            access_denied('shra');
        }
        $what = $this->shra_model->delete_trainer($id);
        set_alert('success', $what === 'deactivated' ? 'Trainer has past sessions, so it was deactivated instead of deleted.' : _l('shra_deleted'));
        redirect(admin_url('shra/trainers'));
    }

    /* ═══════════════════════ Settings / QR ═══════════════════════ */

    public function settings()
    {
        if (!is_admin()) {
            access_denied('shra');
        }

        if ($this->input->post()) {
            $post = $this->input->post(null, false);
            $keys = ['shra_academy_name', 'shra_tagline', 'shra_contact_line', 'shra_offer_percent', 'shra_offer_label', 'shra_offer_ends',
                'shra_minor_age', 'shra_riding_levels', 'shra_chief_instructor', 'shra_director', 'shra_terms',
                'shra_pay_min_value', 'shra_pay_note',
                'shra_batch_morning_start', 'shra_batch_morning_end', 'shra_batch_evening_start', 'shra_batch_evening_end', 'shra_batch_fcfs_note'];
            // Batch timings are stored as a 24-hour "HH:MM" — drop anything else rather
            // than saving a value shra_batches() would silently fall back on.
            $bad_time = false;
            foreach (['shra_batch_morning_start', 'shra_batch_morning_end', 'shra_batch_evening_start', 'shra_batch_evening_end'] as $k) {
                if (isset($post[$k]) && !preg_match('/^([01]?\d|2[0-3]):[0-5]\d$/', trim((string) $post[$k]))) {
                    unset($post[$k]);
                    $bad_time = true;
                }
            }
            if ($bad_time) {
                set_alert('warning', 'Batch timings must be a 24-hour time like 06:00 — the old timing was kept.');
            }
            foreach ($keys as $k) {
                if (isset($post[$k])) {
                    update_option($k, is_string($post[$k]) ? trim($post[$k]) : $post[$k]);
                }
            }
            update_option('shra_offer_active', !empty($post['shra_offer_active']) ? '1' : '0');
            update_option('shra_auto_certificate', !empty($post['shra_auto_certificate']) ? '1' : '0');

            // ── Online payments on /join ──
            update_option('shra_pay_enabled', !empty($post['shra_pay_enabled']) ? '1' : '0');
            update_option('shra_pay_use_master', !empty($post['shra_pay_use_master']) ? '1' : '0');
            update_option('shra_pay_allow_skip', !empty($post['shra_pay_allow_skip']) ? '1' : '0');
            update_option('shra_pay_mode', ($post['shra_pay_mode'] ?? '') === 'full_only' ? 'full_only' : 'partial');
            update_option('shra_pay_min_type', ($post['shra_pay_min_type'] ?? '') === 'fixed' ? 'fixed' : 'percent');
            // Only gateways this installation actually registers may be stored
            $known = array_keys(shra_master_gateways());
            $picked = array_values(array_intersect($known, (array) ($post['shra_pay_gateways'] ?? [])));
            update_option('shra_pay_gateways', json_encode($picked));

            if (!empty($_FILES['logo']['name'])) {
                $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['png', 'jpg', 'jpeg']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK && $_FILES['logo']['size'] <= 3 * 1024 * 1024) {
                    $dir = FCPATH . 'uploads/shra/';
                    if (!is_dir($dir)) {
                        mkdir($dir, 0755, true);
                    }
                    $name = 'logo_' . time() . '.' . $ext;
                    if (move_uploaded_file($_FILES['logo']['tmp_name'], $dir . $name)) {
                        $old = get_option('shra_logo');
                        if ($old && is_file($dir . $old)) {
                            @unlink($dir . $old);
                        }
                        update_option('shra_logo', $name);
                    }
                } else {
                    set_alert('warning', 'Logo must be a PNG/JPG under 3 MB.');
                }
            }
            if (!empty($post['remove_logo'])) {
                $old = get_option('shra_logo');
                if ($old && is_file(FCPATH . 'uploads/shra/' . $old)) {
                    @unlink(FCPATH . 'uploads/shra/' . $old);
                }
                update_option('shra_logo', '');
            }

            set_alert('success', _l('shra_settings_saved'));
            redirect(admin_url('shra/settings'));
        }

        $data['title'] = _l('shra_settings');
        $this->load->view('settings', $data);
    }

    /** Printable QR poster for the reception desk. */
    public function qr()
    {
        $this->need('view');
        $data['title'] = 'Self registration QR';
        $data['url']   = shra_join_url();
        $data['svg']   = shra_qr_svg($data['url'], 6);
        $this->load->view('qr_poster', $data);
    }
}
