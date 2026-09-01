<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * SHRA Leads — My Day (agent queue), pipeline, visits board, lead page,
 * team leaderboard, settings & import.
 */
class Shra_leads extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('shra/shra_model');
        $this->load->model('shra/shra_leads_model', 'leads');
        $this->load->helper('shra/shra');

        if (!shra_leads_can('own')) {
            access_denied('shra leads');
        }
    }

    private function json($data)
    {
        header('Content-Type: application/json');
        echo json_encode($data);
    }

    private function need($what)
    {
        if (!shra_leads_can($what)) {
            if ($this->input->method() === 'post' || $this->input->is_ajax_request()) {
                $this->json(['success' => false, 'message' => 'You do not have permission for this action.']);
                exit;
            }
            access_denied('shra leads');
        }
    }

    /** Load a lead the current user may access, or fail (JSON or 404). */
    private function lead_or_fail($id, $json = false)
    {
        $l = $this->leads->get((int) $id);
        if (!$l || !$this->leads->can_access($l)) {
            if ($json) {
                $this->json(['success' => false, 'message' => 'Lead not found or not yours.']);
                exit;
            }
            show_404();
        }

        return $l;
    }

    private function result($res, $ok_message = 'Saved.', $extra = [])
    {
        if ($res === true || is_int($res)) {
            $this->json(array_merge(['success' => true, 'message' => $ok_message], $extra));
        } else {
            $this->json(['success' => false, 'message' => is_string($res) ? $res : 'Could not save.']);
        }
    }

    /** The lead ledger stores the mode by name; the invoice stores it by id. */
    private function payment_mode_name($mode)
    {
        $mode = trim((string) $mode);
        if ($mode === '' || !is_numeric($mode)) {
            return $mode;
        }
        $m = $this->db->where('id', (int) $mode)->get(db_prefix() . 'payment_modes')->row();

        return $m ? $m->name : '';
    }

    /**
     * The backdate picked in the Arrived & confirm dialog ("the entry was missed
     * yesterday"). Only a valid past date counts — today and anything else mean
     * "stamp it now", so the normal timestamps stay untouched.
     */
    private function entry_date()
    {
        $d = trim((string) $this->input->post('entry_date'));
        if ($d === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) || !strtotime($d)) {
            return '';
        }

        return $d < date('Y-m-d') ? $d : '';
    }

    private function common()
    {
        return [
            'agents'     => shra_lead_agents(),
            'sources'    => $this->leads->sources(),
            'packages'   => $this->shra_model->get_packages(true),
            'slots'      => shra_lead_visit_slots(),
            'reasons'    => shra_lead_lost_reasons(),
            'methods'    => shra_lead_payment_methods(),
            'templates'  => shra_lead_wa_templates(),
            'weekend'    => shra_lead_weekend_dates(),
            'can_all'    => shra_leads_can('all'),
            'can_manage' => shra_leads_can('manage'),
        ];
    }

    /* ═══════════════════════ Leads (work list + pipeline in one) ═══════════════════════ */

    /**
     * The one leads screen. `scope=mine` (default) loads the agent's open queue — what to
     * call today; `scope=all` loads every lead the user may see, optionally narrowed by
     * agent and date added. Stage / source / text filtering happens in the browser over
     * whatever was loaded, so it stays instant either way.
     */
    public function index()
    {
        $me    = get_staff_user_id();
        // agent 0 = "All staff". Admins land there; agents land on their own queue.
        $agent_param = $this->input->get('agent');
        $agent       = $agent_param === null ? (is_admin() ? 0 : (int) $me) : (int) $agent_param;
        if ($agent !== (int) $me && !shra_leads_can('all')) {
            $agent = $me;
        }
        $scope = $this->input->get('scope') === 'all' ? 'all' : 'mine';
        $from  = (string) $this->input->get('from');
        $to    = (string) $this->input->get('to');

        $data          = $this->common();
        $data['title'] = 'Leads';
        $data['agent'] = $agent;
        $data['scope'] = $scope;
        // `stage=open|closed` and `overdue=1` come from dashboard / team links: they pick a
        // tab rather than a stage.
        $stage  = (string) $this->input->get('stage');
        $bucket = '';
        if (in_array($stage, ['open', 'closed'], true)) {
            $bucket = $stage === 'open' ? 'open' : 'closed';
            $stage  = '';
        } elseif ($this->input->get('overdue')) {
            $bucket = 'overdue';
        }
        $data['filters'] = [
            'from'   => $from,
            'to'     => $to,
            'stage'  => $stage,
            'bucket' => $bucket,
            'source' => (int) $this->input->get('source'),
            'q'      => trim((string) $this->input->get('q')),
        ];

        if ($scope === 'all') {
            $data['rows']     = $this->leads->get_list(array_filter([
                'agent' => $agent && $this->input->get('agent') ? $agent : 0,
                'from'  => $from,
                'to'    => $to,
                'order' => 'l.dateadded DESC',
            ]), 1500);
            $data['no_shows'] = [];
        } else {
            $queues = $this->leads->queues_for($agent, $agent === (int) $me && shra_leads_can('all'));
            $rows   = [];
            foreach (['unset', 'overdue', 'today', 'upcoming', 'later', 'joining'] as $k) {
                foreach ($queues[$k] as $l) {
                    $rows[] = $l;
                }
            }
            // Newest enquiry first — flattening the buckets above regrouped them.
            usort($rows, function ($a, $b) {
                return strcmp((string) $b->dateadded, (string) $a->dateadded);
            });
            $data['rows']     = $rows;
            $data['no_shows'] = shra_leads_can('all') ? $this->leads->no_shows(20) : [];
        }

        $data['month']  = $this->leads->my_month($agent);
        $data['funnel'] = $this->leads->funnel_counts(!shra_leads_can('all'));
        $this->load->view('leads/index', $data);
    }

    /**
     * End-of-day report for one agent, as a WhatsApp-ready message. Agents may
     * pull their own day; managers (leads_all) may pull anyone's.
     */
    public function eod()
    {
        $me    = (int) get_staff_user_id();
        $agent = (int) $this->input->get('agent') ?: $me;
        if ($agent !== $me && !shra_leads_can('all')) {
            $agent = $me;
        }
        $date  = (string) $this->input->get('date');
        $date  = $date && strtotime($date) ? date('Y-m-d', strtotime($date)) : date('Y-m-d');
        if ($date > date('Y-m-d')) {
            $date = date('Y-m-d');
        }

        $report = $this->leads->day_report($agent, $date);
        $this->json([
            'success' => true,
            'agent'   => $report['agent'],
            'date'    => $date,
            'label'   => date('D, d M Y', strtotime($date)),
            'text'    => shra_lead_eod_message($report),
        ]);
    }

    /** Pipeline used to be its own page; it is the same screen now. */
    public function pipeline()
    {
        $q = $this->input->get();
        unset($q['view']);
        $q['scope'] = 'all';
        redirect(admin_url('shra/shra_leads?' . http_build_query($q)));
    }

    public function export()
    {
        $this->need('manage');
        $list = $this->leads->get_list(array_filter(['stage' => (string) $this->input->get('stage'), 'agent' => (int) $this->input->get('agent'), 'source' => (int) $this->input->get('source'),
            'from' => (string) $this->input->get('from'), 'to' => (string) $this->input->get('to')]), 5000);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="shra-leads-' . date('Ymd') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID', 'Name', 'Phone', 'Email', 'City', 'Source', 'Agent', 'Stage', 'Rider for', 'Age', 'Interest', 'Start date', 'Batch', 'Expected', 'Next action', 'Visit', 'Calls', 'Lost reason', 'Added', 'Won at']);
        foreach ($list as $l) {
            fputcsv($out, [$l->id, $l->name, $l->phonenumber, $l->email, $l->city, $l->source_name, $l->agent_name, shra_lead_stage_label($l->stage), $l->rider_for, $l->rider_age, $l->package_name, $l->preferred_start_date, $l->batch_label,
                $l->expected_value, $l->next_action_at, trim($l->visit_date . ' ' . $l->visit_slot), $l->call_attempts, $l->lost_reason, $l->dateadded, $l->won_at]);
        }
        fclose($out);
    }

    /* ═══════════════════════ Visits board ═══════════════════════ */

    public function visits()
    {
        $wk   = shra_lead_weekend_dates();
        $date = (string) $this->input->get('date');
        if (!$date || !strtotime($date)) {
            $date = date('Y-m-d');
        }
        $date = date('Y-m-d', strtotime($date));
        $data              = $this->common();
        $data['title']     = 'Leads · Visits';
        $data['date']      = $date;
        $data['groups']    = $this->leads->visits_for($date);
        $data['no_shows']  = $this->leads->no_shows(50);
        $data['counts']    = [];
        foreach (array_unique([date('Y-m-d'), $wk['sat'], $wk['sun'], $date]) as $d) {
            $n = 0;
            foreach ($this->leads->visits_for($d) as $g) {
                $n += count($g);
            }
            $data['counts'][$d] = $n;
        }
        $this->load->view('leads/visits', $data);
    }

    /* ═══════════════════════ Lead page ═══════════════════════ */

    public function view($id)
    {
        $l = $this->lead_or_fail($id);
        $data                = $this->common();
        $data['title']       = 'Lead · ' . $l->name;
        $data['lead']        = $l;
        $data['events']      = $this->leads->events($l->id);
        $data['attribution'] = $this->leads->attribution_for_lead($l->id);
        $data['payments']    = $this->leads->payments($l->id);
        $data['notes']       = $this->db->where('rel_type', 'lead')->where('rel_id', $l->id)->order_by('dateadded', 'DESC')->get(db_prefix() . 'notes')->result();
        $data['rider']       = $l->rider_id ? $this->shra_model->get_rider($l->rider_id) : null;
        $data['enrollments'] = $l->rider_id ? $this->shra_model->get_enrollments(['rider_id' => $l->rider_id], 20) : [];
        // Landed here straight from "Collect & complete" — show the bill that was just raised.
        $data['billed'] = null;
        $billed         = (int) $this->input->get('billed');
        if ($billed && $l->rider_id) {
            $e = $this->shra_model->get_enrollment($billed);
            if ($e && (int) $e->rider_id === (int) $l->rider_id) {
                $data['billed'] = $e;
            }
        }
        $this->load->view('leads/view', $data);
    }

    /* ═══════════════════════ AJAX actions ═══════════════════════ */

    public function check_phone()
    {
        $l = $this->leads->find_by_phone((string) $this->input->get('phone'));
        if (!$l) {
            $this->json(['exists' => false]);

            return;
        }
        // The money/stage context lets the visits board open the Arrived & confirm
        // dialog on a search hit with the same knowledge a lead card carries.
        $money = shra_lead_money($l);
        $this->json(['exists' => true, 'id' => $l->id, 'name' => $l->name, 'agent' => $l->agent_name ?: 'Unassigned', 'stage' => shra_lead_stage_label($l->stage),
            'url' => shra_lead_url($l->id), 'mine' => $this->leads->can_access($l),
            'stage_key' => $l->stage, 'phone' => $l->phonenumber, 'pkg' => (int) $l->interest_package_id,
            'paid' => $money['paid'] > 0 ? shra_money($money['paid']) : '', 'due' => $money['due'] > 0 ? shra_money($money['due']) : '',
            'paid_num' => $money['paid'] + 0]);
    }

    public function add()
    {
        $in = [
            'name'                => $this->input->post('name'),
            'phone'               => $this->input->post('phone'),
            'email'               => $this->input->post('email'),
            'city'                => $this->input->post('city'),
            'source'              => (int) $this->input->post('source'),
            'assigned'            => shra_leads_can('all') ? (int) $this->input->post('assigned') : get_staff_user_id(),
            'rider_for'           => $this->input->post('rider_for'),
            'rider_age'           => $this->input->post('rider_age'),
            'interest_package_id' => (int) $this->input->post('interest_package_id'),
            'preferred_start_date' => $this->input->post('preferred_start_date'),
            'preferred_batch'     => $this->input->post('preferred_batch'),
            'expected_value'      => $this->input->post('expected_value'),
            'description'         => $this->input->post('description'),
            'next_action_at'      => $this->input->post('next_action_at'),
            'campaign'            => $this->input->post('campaign'),
        ];
        if (!shra_leads_can('all') && !$in['assigned']) {
            $in['assigned'] = get_staff_user_id();
        }
        $res = $this->leads->capture($in, 'manual');
        if (is_string($res)) {
            $this->json(['success' => false, 'message' => $res]);

            return;
        }
        if (!empty($res['duplicate'])) {
            $d = $res['lead'];
            $this->json(['success' => false, 'duplicate' => true, 'message' => 'This number already belongs to "' . $d->name . '" (' . ($d->agent_name ?: 'unassigned') . ', ' . shra_lead_stage_label($d->stage) . '). Your attempt was logged on that lead.',
                'url' => shra_lead_url($d->id), 'mine' => $this->leads->can_access($d)]);

            return;
        }
        if ((int) $this->input->post('mark_visited') === 1 && shra_leads_can('all')) {
            $this->leads->mark_visited($res['lead_id'], 'Walk-in');
        }
        $this->json(['success' => true, 'message' => 'Lead added.', 'id' => $res['lead_id'], 'url' => shra_lead_url($res['lead_id'])]);
    }

    public function log_call()
    {
        $l     = $this->lead_or_fail($this->input->post('lead_id'), true);
        $note  = trim((string) $this->input->post('note'));
        $stage = (string) $this->input->post('stage');
        $res   = $this->leads->log_call($l->id, $stage, (string) $this->input->post('next_action_at'), $note, (string) $this->input->post('channel') ?: 'call');
        if ($res !== true) {
            $this->result($res);

            return;
        }

        // The agent may also have taken an advance on the same call ("pay 50% today").
        [$paid, $pay_warning] = $this->collect_payment($l->id);

        // The status picked in the dialog is applied here.
        if ($stage !== '' && in_array($stage, shra_lead_quick_stages())) {
            $fresh = $this->leads->get($l->id);
            if ($fresh && $fresh->stage !== $stage) {
                $moved = $this->leads->set_stage($l->id, $stage, ['silent_next' => true, 'note' => $note]);
                if ($moved !== true) {
                    $this->json(['success' => true, 'warning' => true, 'card' => $this->card($l->id),
                        'message' => 'Call logged' . $paid . ' — status not changed: ' . (is_string($moved) ? $moved : 'not allowed from here.')]);

                    return;
                }
                $this->json(['success' => true, 'warning' => (bool) $pay_warning, 'card' => $this->card($l->id),
                    'message' => 'Call logged' . $paid . ' · status set to ' . shra_lead_stage_label($stage) . '.' . $pay_warning]);

                return;
            }
        }

        $this->json(['success' => true, 'warning' => (bool) $pay_warning, 'card' => $this->card($l->id),
            'message' => 'Call logged' . $paid . '.' . $pay_warning]);
    }

    /** Every call logged on this lead — rendered at the foot of the Log call dialog. */
    public function call_log()
    {
        $l = $this->lead_or_fail($this->input->get('lead_id'), true);
        $this->json(['success' => true, 'html' => $this->load->view('leads/partials/call_log', [
            'log'  => $this->leads->call_log($l->id),
            'lead' => $l,
        ], true)]);
    }

    /**
     * Advance / part payment entered in the Log call or Confirm dialog, with the screenshot
     * the customer sent. Nothing to do when the amount is blank. Returns
     * [" · ₹500 collected" | "", " warning text" | ""] — the call itself is already logged,
     * so a payment problem is reported next to it rather than failing the whole action.
     */
    private function collect_payment($lead_id, $method = null, $when = '')
    {
        // "4,500" / "4 500" must not become ₹4 — strip the grouping before casting.
        $raw = str_replace([',', ' ', "\xc2\xa0"], '', trim((string) $this->input->post('paid_amount')));
        if ($raw === '') {
            return ['', ''];
        }
        if (!is_numeric($raw) || (float) $raw <= 0) {
            return ['', ' The amount collected was not a number, so no payment was recorded.'];
        }

        [$file, $file_name, $file_err] = $this->store_payment_proof();
        $pay = $this->leads->add_payment(
            $lead_id,
            $raw,
            $method !== null ? (string) $method : (string) $this->input->post('paid_method'),
            trim((string) $this->input->post('paid_reference')),
            trim((string) $this->input->post('paid_note')),
            $file,
            $file_name,
            $when
        );
        if (!is_int($pay)) {
            if ($file !== '') {
                @unlink(FCPATH . 'uploads/shra/lead_payments/' . $file);
            }

            return ['', ' Payment not saved: ' . (is_string($pay) ? $pay : 'could not save it.')];
        }

        return [' · ' . shra_money($raw) . ' collected', $file_err !== '' ? ' ' . $file_err . ' Attach it from the lead page.' : ''];
    }

    /**
     * Move the uploaded screenshot into uploads/shra/lead_payments/ under an unguessable
     * name (it is only ever served back through payment_file()).
     * Returns [stored name, original name, reason it was refused].
     */
    private function store_payment_proof()
    {
        if (empty($_FILES['payment_proof']['name'])) {
            return ['', '', ''];
        }
        $f = $_FILES['payment_proof'];
        if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
            return ['', '', 'The screenshot did not upload.'];
        }
        if ($f['size'] > 5 * 1024 * 1024) {
            return ['', '', 'The screenshot must be under 5 MB.'];
        }
        $ext   = strtolower(pathinfo((string) $f['name'], PATHINFO_EXTENSION));
        $types = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'pdf' => 'application/pdf'];
        if (!isset($types[$ext])) {
            return ['', '', 'Only JPG, PNG, WEBP or PDF can be attached.'];
        }
        if ($ext !== 'pdf' && @getimagesize($f['tmp_name']) === false) {
            return ['', '', 'That file is not a readable image.'];
        }
        $dir = FCPATH . 'uploads/shra/lead_payments/';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
            @file_put_contents($dir . 'index.html', '');
        }
        $name = date('Ymd') . '_' . bin2hex(function_exists('random_bytes') ? random_bytes(12) : openssl_random_pseudo_bytes(12)) . '.' . $ext;
        if (!@move_uploaded_file($f['tmp_name'], $dir . $name)) {
            return ['', '', 'The screenshot could not be stored.'];
        }
        @chmod($dir . $name, 0644);

        return [$name, (string) $f['name'], ''];
    }

    /** Serve a payment screenshot to staff who may see the lead — never a public URL. */
    public function payment_file($id)
    {
        $pay = $this->leads->payment($id);
        if (!$pay || !$pay->file) {
            show_404();
        }
        $this->lead_or_fail($pay->lead_id);
        $abs = FCPATH . 'uploads/shra/lead_payments/' . basename($pay->file);
        if (!is_file($abs)) {
            show_404();
        }
        $ext   = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
        $types = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'pdf' => 'application/pdf'];
        header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
        header('Content-Length: ' . filesize($abs));
        header('Content-Disposition: inline; filename="payment-' . (int) $pay->id . '.' . $ext . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=600');
        readfile($abs);
        exit;
    }

    /** Screenshot arrived after the call was logged — attach it to the payment from the lead page. */
    public function attach_proof()
    {
        $pay = $this->leads->payment($this->input->post('payment_id'));
        if (!$pay) {
            $this->json(['success' => false, 'message' => 'Payment not found.']);

            return;
        }
        $l = $this->lead_or_fail($pay->lead_id, true);
        if (empty($_FILES['payment_proof']['name'])) {
            $this->json(['success' => false, 'message' => 'Pick the screenshot first.']);

            return;
        }
        [$file, $file_name, $err] = $this->store_payment_proof();
        if ($file === '') {
            $this->json(['success' => false, 'message' => trim($err) ?: 'The screenshot could not be attached.']);

            return;
        }
        $res = $this->leads->attach_payment_proof($pay->id, $file, $file_name);
        if ($res !== true) {
            @unlink(FCPATH . 'uploads/shra/lead_payments/' . $file);
        }
        $this->result($res, 'Screenshot attached.', ['card' => $this->card($l->id)]);
    }

    /** Wrong amount typed in? A manager can drop the entry; the audit trail keeps the removal. */
    public function delete_payment()
    {
        $this->need('manage');
        $pay = $this->leads->payment($this->input->post('payment_id'));
        if (!$pay) {
            $this->json(['success' => false, 'message' => 'Payment not found.']);

            return;
        }
        $l   = $this->lead_or_fail($pay->lead_id, true);
        $res = $this->leads->delete_payment($pay->id);
        $this->result($res, 'Payment entry removed.', ['card' => $this->card($l->id)]);
    }

    /** Bulk delete from the work list. Superadmin only — deliberately not a staff permission. */
    public function bulk_delete()
    {
        if (!is_admin() || $this->input->method() !== 'post') {
            $this->json(['success' => false, 'message' => 'Only a superadmin can delete leads.']);

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
            if ($this->leads->delete_lead($id)) {
                $done++;
            }
        }
        $this->json([
            'success' => $done > 0,
            'message' => $done > 0
                ? 'Deleted ' . $done . ' lead' . ($done === 1 ? '' : 's') . ($done < count($ids) ? ' — ' . (count($ids) - $done) . ' could not be deleted.' : '.')
                : 'Nothing was deleted.',
        ]);
    }

    public function schedule_visit()
    {
        $l   = $this->lead_or_fail($this->input->post('lead_id'), true);
        $res = $this->leads->schedule_visit($l->id, (string) $this->input->post('visit_date'), (string) $this->input->post('visit_slot'), trim((string) $this->input->post('note')));
        $this->result($res, 'Visit scheduled.', ['card' => $this->card($l->id)]);
    }

    public function visited()
    {
        $this->need('all');
        $l    = $this->lead_or_fail($this->input->post('lead_id'), true);
        $when = $this->entry_date();
        $res  = $this->leads->mark_visited($l->id, trim((string) $this->input->post('note')), $when);
        if ($res !== true) {
            $this->result($res);

            return;
        }

        // The Arrived & confirm dialog collects money in the same breath — record it too.
        [$paid, $pay_warning] = $this->collect_payment($l->id, $this->payment_mode_name($this->input->post('payment_mode')), $when);
        $this->json(['success' => true, 'warning' => (bool) $pay_warning, 'card' => $this->card($l->id),
            'message' => 'Marked as arrived' . ($when !== '' ? ' on ' . date('D d M', strtotime($when)) : '') . $paid . '.' . $pay_warning]);
    }

    public function no_show()
    {
        $l   = $this->lead_or_fail($this->input->post('lead_id'), true);
        $res = $this->leads->mark_no_show($l->id, trim((string) $this->input->post('note')));
        $this->result($res, 'No-show recorded — follow up today.', ['card' => $this->card($l->id)]);
    }

    /**
     * Confirm the lead, and — when the dialog asked for it — take the balance and close the
     * sale in the same step: the money is written to the lead ledger first (so it survives a
     * failed bill), then the rider is created and the real invoice raised, which credits the
     * agent and moves the lead to Joined.
     */
    public function confirm()
    {
        $this->need('all');
        $l = $this->lead_or_fail($this->input->post('lead_id'), true);
        // A forced retry (the counter asked "bill anyway?" and the agent said yes) re-posts the
        // same dialog — the lead is already confirmed, so don't log it or ping the agent twice.
        $retry = (int) $this->input->post('force') === 1 && $l->stage === 'confirmed';
        $when  = $this->entry_date();
        $res   = $retry ? true : $this->leads->confirm($l->id, (int) $this->input->post('package_id'), $this->input->post('expected_value'), trim((string) $this->input->post('note')), $when);
        if ($res !== true) {
            $this->result($res);

            return;
        }

        // Money handed over in the dialog. Recorded even when the bill below cannot be
        // raised, so nothing collected is ever lost.
        $mode = $this->payment_mode_name($this->input->post('payment_mode'));
        [$paid, $pay_warning] = $this->collect_payment($l->id, $mode, $when);

        $fresh = $this->leads->get($l->id);
        $state = [
            'paid_recorded' => $paid !== '',
            'paid_num'      => round((float) $fresh->paid_amount, 2),
            'lead_url'      => shra_lead_url($l->id),
        ];

        if ((int) $this->input->post('complete') !== 1) {
            $this->json(array_merge($state, ['success' => true, 'warning' => (bool) $pay_warning, 'card' => $this->card($l->id),
                'message'  => 'Confirmed' . $paid . '. Bill now to convert.' . $pay_warning,
                'bill_url' => admin_url('shra/shra_leads/bill_now/' . $l->id)]));

            return;
        }

        // ── Collect & complete ──
        // The lead stays confirmed whatever happens here; the reason the bill did not go
        // through is reported next to it rather than rolling the confirmation back.
        $stop = function ($why) use ($l, $paid, $pay_warning, $state) {
            $this->json(array_merge($state, ['success' => true, 'warning' => true, 'card' => $this->card($l->id),
                'message'  => 'Confirmed' . $paid . '. The bill was not raised: ' . $why . $pay_warning,
                'bill_url' => admin_url('shra/shra_leads/bill_now/' . $l->id)]));
        };
        if (!shra_can_billing()) {
            $stop('you do not have the billing permission — ask the counter to bill this rider.');

            return;
        }
        $package_id = (int) $fresh->interest_package_id;
        if (!$package_id) {
            $stop('pick the package first — the invoice is raised for it.');

            return;
        }
        $rider_id = $this->leads->convert_to_rider($l->id);
        if (!is_int($rider_id)) {
            $stop(is_string($rider_id) ? lcfirst($rider_id) : 'the rider could not be created.');

            return;
        }

        $bill = $this->shra_model->create_bill($rider_id, $package_id, [
            'paid_amount'  => round((float) $fresh->paid_amount, 2),
            'payment_mode' => (string) $this->input->post('payment_mode'),
            'reference'    => trim((string) $this->input->post('paid_reference')),
            'notes'        => 'Leads desk · confirmed & billed on lead #' . $l->id,
            'lead_id'      => $l->id,
            'credit_lead'  => '1',
            'bill_token'   => (string) $this->input->post('bill_token'),
            'force'        => (int) $this->input->post('force') === 1,
        ]);
        if (is_string($bill)) {
            $stop(lcfirst($bill));

            return;
        }
        if (!empty($bill['needs_confirm'])) {
            // The counter guards (recent bill / unused sessions / older balance) — the
            // dialog asks and posts again with force=1.
            $this->json(array_merge($state, ['success' => false, 'needs_confirm' => true, 'message' => $bill['message']]));

            return;
        }

        $e = $this->shra_model->get_enrollment($bill['enrollment_id']);
        // The advances on this lead went onto the invoice as money received — say so on the
        // timeline, so nobody chases the rider for cash that is already in the drawer.
        if ((float) $fresh->paid_amount > 0.009) {
            $this->leads->event($l->id, 'note', [
                'note' => shra_money(min((float) $fresh->paid_amount, (float) $e->total)) . ' already collected on this lead was recorded as received on invoice '
                    . ($e->invoice_id ? format_invoice_number($e->invoice_id) : '#' . $e->enrollment_no) . ' — do not collect it again.',
                'log'  => 'Advances settled into ' . $e->enrollment_no,
            ]);
        }
        $this->json(array_merge($state, [
            'success'  => true,
            'message'  => 'Confirmed' . $paid . ' · ' . $e->enrollment_no . ' billed'
                . ($e->due > 0.009 ? ' · ' . shra_money($e->due) . ' still due.' : ' · fully paid.') . $pay_warning,
            'redirect' => shra_lead_url($l->id) . '?billed=' . (int) $e->id,
        ]));
    }

    public function lost()
    {
        $l   = $this->lead_or_fail($this->input->post('lead_id'), true);
        $res = $this->leads->mark_lost($l->id, (string) $this->input->post('reason'), trim((string) $this->input->post('note')));
        $this->result($res, 'Marked lost.', ['card' => $this->card($l->id)]);
    }

    public function junk()
    {
        $l   = $this->lead_or_fail($this->input->post('lead_id'), true);
        $res = $this->leads->mark_junk($l->id, trim((string) $this->input->post('note')));
        $this->result($res, 'Marked junk.', ['card' => $this->card($l->id)]);
    }

    public function reopen()
    {
        $this->need('manage');
        $l   = $this->lead_or_fail($this->input->post('lead_id'), true);
        $res = $this->leads->reopen($l->id, trim((string) $this->input->post('note')));
        $this->result($res, 'Reopened.', ['card' => $this->card($l->id)]);
    }

    public function stage()
    {
        $l  = $this->lead_or_fail($this->input->post('lead_id'), true);
        $to = (string) $this->input->post('to');
        if (in_array($to, ['visited', 'confirmed']) && !shra_leads_can('all')) {
            $this->json(['success' => false, 'message' => 'Only the front desk / manager can mark visits.']);

            return;
        }
        if (in_array($to, ['followup']) && !$l->is_open && !shra_leads_can('manage')) {
            $this->json(['success' => false, 'message' => 'Only a manager can reopen a closed lead.']);

            return;
        }
        $res = $this->leads->set_stage($l->id, $to, [
            'next_action_at' => (string) $this->input->post('next_action_at'),
            'visit_date'     => (string) $this->input->post('visit_date'),
            'visit_slot'     => (string) $this->input->post('visit_slot'),
            'package_id'     => (int) $this->input->post('package_id'),
            'expected_value' => $this->input->post('expected_value'),
            'reason'         => (string) $this->input->post('reason'),
            'note'           => trim((string) $this->input->post('note')),
        ]);
        $this->result($res, 'Moved.', ['card' => $this->card($l->id)]);
    }

    public function reassign()
    {
        $this->need('manage');
        $l   = $this->lead_or_fail($this->input->post('lead_id'), true);
        $res = $this->leads->assign($l->id, (int) $this->input->post('staff_id'), trim((string) $this->input->post('note')));
        $this->result($res, 'Reassigned.', ['card' => $this->card($l->id)]);
    }

    public function update_details()
    {
        $l   = $this->lead_or_fail($this->input->post('lead_id'), true);
        $res = $this->leads->update_details($l->id, $this->input->post());
        $this->result($res, 'Details saved.');
    }

    public function note()
    {
        $l   = $this->lead_or_fail($this->input->post('lead_id'), true);
        $res = $this->leads->add_note($l->id, (string) $this->input->post('text'));
        $this->result($res, 'Note added.');
    }

    /** Create/link the rider and open the counter pre-filled. */
    public function bill_now($id)
    {
        if (!shra_can_billing() && !shra_leads_can('all')) {
            access_denied('shra billing');
        }
        $l   = $this->lead_or_fail($id);
        $res = $this->leads->convert_to_rider($l->id);
        if (is_string($res)) {
            set_alert('danger', $res);
            redirect(shra_lead_url($l->id));
        }
        if (!shra_can_billing()) {
            set_alert('success', 'Rider created. Ask the counter to bill rider #' . $res . '.');
            redirect(shra_lead_url($l->id));
        }
        redirect(admin_url('shra/billing?rider=' . (int) $res . '&lead=' . (int) $l->id));
    }

    /** Lead matched to a phone for the billing screen banner. */
    public function match()
    {
        $l = $this->leads->find_creditable_by_phone((string) $this->input->get('phone'));
        if (!$l || !$l->is_open && $l->stage !== 'won') {
            $this->json(['match' => false]);

            return;
        }
        $this->json(['match' => true, 'id' => $l->id, 'name' => $l->name, 'agent' => $l->agent_name ?: 'Unassigned', 'stage' => shra_lead_stage_label($l->stage), 'url' => shra_lead_url($l->id), 'won' => $l->stage === 'won']);
    }

    /**
     * Re-rendered lead HTML after an action, so the page updates in place.
     * The caller posts fmt=row from the dense work list and fmt=card from the board.
     */
    private function card($lead_id)
    {
        $l    = $this->leads->get($lead_id);
        $view = $this->input->post('fmt') === 'row' ? 'leads/partials/lead_row' : 'leads/partials/lead_card';

        return $this->load->view($view, ['l' => $l, 'can_all' => shra_leads_can('all')], true);
    }

    /* ═══════════════════════ Team & reports ═══════════════════════ */

    public function team()
    {
        $this->need('reports');
        [$from, $to, $preset] = $this->range();
        $data             = $this->common();
        $data['title']    = 'Leads · Team';
        $data['from']     = $from;
        $data['to']       = $to;
        $data['preset']   = $preset;
        $data['rows']     = $this->leads->team_stats($from, $to);
        $data['sources_stats'] = $this->leads->source_stats($from, $to);
        $data['lost']     = $this->leads->lost_reasons($from, $to);
        $data['funnel']   = $this->leads->funnel_counts(false);
        $this->load->view('leads/team', $data);
    }

    private function range()
    {
        $preset = (string) ($this->input->get('range') ?: 'month');
        $from   = (string) $this->input->get('from');
        $to     = (string) $this->input->get('to');
        switch ($preset) {
            case 'today':      $from = $to = date('Y-m-d'); break;
            case 'week':       $from = date('Y-m-d', strtotime('monday this week')); $to = date('Y-m-d'); break;
            case 'last_month': $from = date('Y-m-01', strtotime('first day of last month')); $to = date('Y-m-t', strtotime('last day of last month')); break;
            case 'quarter':    $from = date('Y-m-d', strtotime('-3 months')); $to = date('Y-m-d'); break;
            case 'year':       $from = date('Y-01-01'); $to = date('Y-m-d'); break;
            case 'all':        $from = '2000-01-01'; $to = date('Y-m-d'); break;
            case 'custom':
                $from = $from && strtotime($from) ? date('Y-m-d', strtotime($from)) : date('Y-m-01');
                $to   = $to && strtotime($to) ? date('Y-m-d', strtotime($to)) : date('Y-m-d');
                break;
            default:           $preset = 'month'; $from = date('Y-m-01'); $to = date('Y-m-t');
        }

        return [$from, $to, $preset];
    }

    /* ═══════════════════════ Settings / import ═══════════════════════ */

    public function settings()
    {
        $this->need('manage');
        if ($this->input->post()) {
            $post = $this->input->post(null, false);
            foreach (['shra_lead_sla_minutes', 'shra_lead_stale_days', 'shra_lead_phone_country', 'shra_lead_repeat_credit_months', 'shra_lead_visit_slots', 'shra_lead_lost_reasons', 'shra_lead_payment_methods', 'shra_lead_wa_templates', 'shra_lead_wa_copy_msg', 'shra_lead_wa_master_msg', 'shra_lead_wa_offer_msg', 'shra_lead_wa_links',
                'shra_lead_landing_phone', 'shra_lead_landing_location', 'shra_lead_landing_maps_url', 'shra_lead_landing_instagram', 'shra_lead_landing_reels', 'shra_lead_landing_min_age', 'shra_lead_rate_limit', 'shra_join_reclaim_minutes', 'shra_lead_meta_pixel_id', 'shra_lead_gads_id', 'shra_lead_gads_label', 'shra_lead_ga4_id', 'shra_lead_landing_map_query', 'shra_lead_landing_map_embed'] as $k) {
                if (isset($post[$k])) {
                    update_option($k, trim((string) $post[$k]));
                }
            }
            update_option('shra_lead_auto_assign', !empty($post['shra_lead_auto_assign']) ? '1' : '0');
            update_option('shra_lead_manager_digest', !empty($post['shra_lead_manager_digest']) ? '1' : '0');
            update_option('shra_lead_public_enabled', !empty($post['shra_lead_public_enabled']) ? '1' : '0');
            update_option('shra_lead_agent_pool', json_encode(array_map('intval', (array) ($post['pool'] ?? []))));
            if (!empty($post['new_source'])) {
                $n = trim($post['new_source']);
                if ($n !== '' && !$this->db->where('name', $n)->get(db_prefix() . 'leads_sources')->row()) {
                    $this->db->insert(db_prefix() . 'leads_sources', ['name' => $n]);
                }
            }
            foreach ((array) ($post['source_cost'] ?? []) as $sid => $cost) {
                $this->leads->save_source_cost((int) $sid, $cost);
            }
            if (!empty($post['targets_month']) && isset($post['t'])) {
                $this->leads->save_targets(substr($post['targets_month'], 0, 7), (array) $post['t']);
            }
            set_alert('success', 'Lead settings saved.');
            redirect(admin_url('shra/shra_leads/settings?month=' . ($post['targets_month'] ?? date('Y-m'))));
        }
        $month              = preg_match('/^\d{4}-\d{2}$/', (string) $this->input->get('month')) ? $this->input->get('month') : date('Y-m');
        $data               = $this->common();
        $data['title']      = 'Leads · Settings';
        $data['pool']       = array_map('intval', json_decode((string) get_option('shra_lead_agent_pool'), true) ?: []);
        $data['all_agents'] = shra_lead_agents(false);
        $data['month']      = $month;
        $data['targets']    = $this->leads->get_targets($month);
        $data['inquire_url'] = site_url('inquire');
        $data['inquire_qr']  = shra_qr_svg(site_url('inquire'), 5);
        $this->load->view('leads/settings', $data);
    }

    /**
     * Import leads from any spreadsheet a manager has: upload, check the
     * columns the importer detected (change any of them), preview, commit.
     * The uploaded sheet is parked in the temp folder between the steps so a
     * large file is not carried back and forth through the browser.
     */
    public function import()
    {
        $this->need('manage');
        require_once module_dir_path(SHRA_MODULE_NAME, 'libraries/Shra_import.php');
        $data             = $this->common();
        $data['title']    = 'Leads · Import';
        $data['targets']  = Shra_import::targets();
        $data['sheet']    = null;
        $data['result']   = null;
        $data['commit']   = false;
        $data['token']    = '';
        $data['filename'] = '';
        $data['encoding'] = '';
        $data['opts']     = ['default_source' => 0, 'default_agent' => 0, 'create_sources' => 1, 'remember' => 1, 'has_header' => ''];

        if ($this->input->post()) {
            $post = $this->input->post(null, false);
            $opts = [
                'default_source' => (int) ($post['default_source'] ?? 0),
                'default_agent'  => (int) ($post['default_agent'] ?? 0),
                'create_sources' => !empty($post['create_sources']) ? 1 : 0,
                'remember'       => !empty($post['remember']) ? 1 : 0,
                'has_header'     => isset($post['has_header']) && in_array($post['has_header'], ['0', '1'], true) ? $post['has_header'] : '',
            ];
            $data['opts']     = $opts;
            $data['filename'] = substr(preg_replace('/[^\w \.\-]/u', '', (string) ($post['filename'] ?? '')), 0, 120);
            $data['encoding'] = substr(preg_replace('/[^\w\-]/', '', (string) ($post['encoding'] ?? '')), 0, 20);

            $text     = null;
            $uploaded = false;
            if (!empty($_FILES['file']['tmp_name']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
                if ($_FILES['file']['size'] > 12 * 1024 * 1024) {
                    set_alert('warning', 'That file is larger than 12 MB — split it and import in parts.');
                } else {
                    $dec              = Shra_import::decode((string) file_get_contents($_FILES['file']['tmp_name']));
                    $text             = $dec['text'];
                    $data['filename'] = substr(preg_replace('/[^\w \.\-]/u', '', $_FILES['file']['name']), 0, 120);
                    $data['token']    = $this->import_stash($text);
                    $data['encoding'] = $dec['encoding'];
                    $uploaded         = true;
                }
            } elseif (preg_match('/^[a-f0-9]{32}$/', (string) ($post['token'] ?? ''))) {
                $text          = $this->import_stash_read($post['token']);
                $data['token'] = $post['token'];
                if ($text === null) {
                    set_alert('warning', 'The uploaded file has expired — please upload it again.');
                }
            }

            if ($text !== null && trim($text) !== '') {
                $commit = (int) ($post['commit'] ?? 0) === 1;
                $sheet  = $this->leads->import_read(
                    $text,
                    // A freshly uploaded file gets a fresh guess — the posted map belongs to the previous sheet
                    !$uploaded && isset($post['map']) && is_array($post['map']) ? $post['map'] : null,
                    $opts['has_header'] === '' ? null : (bool) (int) $opts['has_header']
                );
                $data['sheet']  = $sheet;
                $data['result'] = $this->leads->import_run($sheet, $opts, $commit);
                $data['commit'] = $commit;
                if ($commit) {
                    if ($opts['remember']) {
                        $this->leads->import_learn($sheet['headers'], $sheet['map']);
                    }
                    $this->import_stash_drop($data['token']);
                    $data['token'] = '';
                    log_activity('SHRA leads imported [' . (int) $data['result']['counts']['new'] . ' from ' . ($data['filename'] ?: 'sheet') . ']');
                }
            } elseif ($text !== null) {
                set_alert('warning', 'That file had no readable rows.');
            }
        }
        $this->load->view('leads/import', $data);
    }

    /** Park a decoded sheet in the temp folder between the upload and the import. */
    private function import_stash($text)
    {
        $token = bin2hex(random_bytes(16));
        $dir   = app_temp_dir();
        foreach ((array) glob($dir . 'shra-import-*.csv') as $old) {
            if (is_file($old) && filemtime($old) < time() - 6 * 3600) {
                @unlink($old);
            }
        }
        file_put_contents($dir . 'shra-import-' . $token . '.csv', $text);

        return $token;
    }

    private function import_stash_read($token)
    {
        $file = app_temp_dir() . 'shra-import-' . $token . '.csv';

        return is_file($file) ? (string) file_get_contents($file) : null;
    }

    private function import_stash_drop($token)
    {
        if (preg_match('/^[a-f0-9]{32}$/', (string) $token)) {
            @unlink(app_temp_dir() . 'shra-import-' . $token . '.csv');
        }
    }
}
