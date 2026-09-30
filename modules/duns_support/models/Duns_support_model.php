<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Duns_support_model extends App_Model
{
    /** Certificates of Incorporation arrive as a scan or a PDF. */
    const ALLOWED_EXT = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];
    const MAX_BYTES   = 10485760; // 10 MB

    private $t;

    public function __construct()
    {
        parent::__construct();
        $p       = db_prefix();
        $this->t = [
            'plans'  => $p . 'duns_plans',
            'orders' => $p . 'duns_orders',
            'files'  => $p . 'duns_order_files',
            'events' => $p . 'duns_order_events',
        ];
    }

    /* ═══════════════════════════════ Plans ═══════════════════════════════ */

    public function get_plans($active_only = false)
    {
        if ($active_only) {
            $this->db->where('active', 1);
        }

        return $this->db->order_by('sort_order', 'ASC')->order_by('delivery_hours', 'ASC')->get($this->t['plans'])->result();
    }

    public function get_plan($id)
    {
        return $this->db->where('id', (int) $id)->get($this->t['plans'])->row();
    }

    /** @return int|string plan id or an error message */
    public function save_plan(array $in, $id = null)
    {
        $data = [
            'name'           => trim((string) ($in['name'] ?? '')),
            'delivery_hours' => max(1, (int) ($in['delivery_hours'] ?? 12)),
            'price'          => round((float) str_replace(',', '', (string) ($in['price'] ?? 0)), 2),
            'compare_price'  => ($in['compare_price'] ?? '') !== '' ? round((float) str_replace(',', '', (string) $in['compare_price']), 2) : null,
            'badge'          => trim((string) ($in['badge'] ?? '')) ?: null,
            'features'       => trim(str_replace("\r", '', (string) ($in['features'] ?? ''))),
            'is_featured'    => !empty($in['is_featured']) ? 1 : 0,
            'active'         => !empty($in['active']) ? 1 : 0,
            'sort_order'     => (int) ($in['sort_order'] ?? 0),
        ];
        if ($data['name'] === '') {
            return 'Please give the plan a name.';
        }
        if ($data['price'] <= 0) {
            return 'The price must be more than zero.';
        }

        // Only one plan carries the "featured" highlight on the landing page
        if ($data['is_featured']) {
            $this->db->update($this->t['plans'], ['is_featured' => 0]);
        }

        if ($id) {
            $this->db->where('id', (int) $id)->update($this->t['plans'], $data);

            return (int) $id;
        }
        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert($this->t['plans'], $data);

        return (int) $this->db->insert_id();
    }

    public function delete_plan($id)
    {
        // Orders keep a snapshot of the plan name/price/hours, so history survives
        return $this->db->where('id', (int) $id)->delete($this->t['plans']);
    }

    /* ═══════════════════════════════ Orders ══════════════════════════════ */

    public function get($id)
    {
        return $this->db->where('id', (int) $id)->get($this->t['orders'])->row();
    }

    public function get_by_ref($ref)
    {
        if (!preg_match('/^[a-f0-9]{16,40}$/', (string) $ref)) {
            return null;
        }

        return $this->db->where('ref', $ref)->get($this->t['orders'])->row();
    }

    private function apply_filters(array $f)
    {
        if (!empty($f['status'])) {
            if ($f['status'] === 'open') {
                $this->db->where_in('status', ['paid', 'processing']);
            } elseif ($f['status'] === 'overdue') {
                $this->db->where_in('status', ['paid', 'processing'])->where('due_at <', date('Y-m-d H:i:s'));
            } else {
                $this->db->where('status', $f['status']);
            }
        }
        if (!empty($f['plan_id'])) {
            $this->db->where('plan_id', (int) $f['plan_id']);
        }
        if (!empty($f['from'])) {
            $this->db->where('created_at >=', date('Y-m-d 00:00:00', strtotime($f['from'])));
        }
        if (!empty($f['to'])) {
            $this->db->where('created_at <=', date('Y-m-d 23:59:59', strtotime($f['to'])));
        }
        if (!empty($f['q'])) {
            $q = trim($f['q']);
            $this->db->group_start()
                ->like('order_no', $q)->or_like('company_name', $q)
                ->or_like('director_name', $q)->or_like('director_email', $q)
                ->or_like('director_mobile', $q)->or_like('work_email', $q)
                ->or_like('duns_number', $q)->or_like('cin', $q)
                ->group_end();
        }
    }

    public function list_orders(array $f, $limit = 25, $offset = 0)
    {
        $this->apply_filters($f);

        // Open orders first by deadline, then everything else newest first
        return $this->db->order_by("FIELD(status,'paid','processing') DESC", '', false)
            ->order_by('due_at', 'ASC')->order_by('id', 'DESC')
            ->limit((int) $limit, (int) $offset)->get($this->t['orders'])->result();
    }

    public function count_orders(array $f)
    {
        $this->apply_filters($f);

        return (int) $this->db->count_all_results($this->t['orders']);
    }

    public function stats()
    {
        $now = $this->db->escape(date('Y-m-d H:i:s'));
        $m0  = $this->db->escape(date('Y-m-01 00:00:00'));
        $row = $this->db->query('SELECT
                COUNT(*) AS total,
                SUM(status = \'pending_payment\') AS pending_payment,
                SUM(status IN (\'paid\',\'processing\')) AS open,
                SUM(status IN (\'paid\',\'processing\') AND due_at < ' . $now . ') AS overdue,
                SUM(status = \'completed\') AS completed,
                SUM(CASE WHEN status IN (\'paid\',\'processing\',\'completed\') THEN amount_paid ELSE 0 END) AS revenue,
                SUM(CASE WHEN status IN (\'paid\',\'processing\',\'completed\') AND paid_at >= ' . $m0 . ' THEN amount_paid ELSE 0 END) AS revenue_month
            FROM ' . $this->t['orders'])->row();

        return [
            'total'           => (int) $row->total,
            'pending_payment' => (int) $row->pending_payment,
            'open'            => (int) $row->open,
            'overdue'         => (int) $row->overdue,
            'completed'       => (int) $row->completed,
            'revenue'         => (float) $row->revenue,
            'revenue_month'   => (float) $row->revenue_month,
        ];
    }

    /**
     * Validate the public order form. Returns a list of messages (empty = OK).
     * $file is the $_FILES entry for the certificate (or null).
     */
    public function validate_order(array $in, $file, $require_file = true)
    {
        $e = [];
        $plan = $this->get_plan((int) ($in['plan_id'] ?? 0));
        if (!$plan || !$plan->active) {
            $e['plan_id'] = 'Please choose how fast you need your DUNS number.';
        }
        if (mb_strlen(trim((string) ($in['company_name'] ?? ''))) < 2) {
            $e['company_name'] = 'Please enter your company\'s registered name.';
        }
        if (mb_strlen(trim((string) ($in['director_name'] ?? ''))) < 2) {
            $e['director_name'] = 'Please enter the director\'s full name.';
        }
        if (!filter_var(trim((string) ($in['director_email'] ?? '')), FILTER_VALIDATE_EMAIL)) {
            $e['director_email'] = 'Please enter a valid email for the director.';
        }
        $mobile = preg_replace('/\D+/', '', (string) ($in['director_mobile'] ?? ''));
        if (strlen($mobile) < 10 || strlen($mobile) > 15) {
            $e['director_mobile'] = 'Please enter a valid mobile number.';
        }
        if (!filter_var(trim((string) ($in['work_email'] ?? '')), FILTER_VALIDATE_EMAIL)) {
            $e['work_email'] = 'Please enter a valid work email.';
        }
        if (empty($in['agree'])) {
            $e['agree'] = 'Please confirm the details are correct and accept the terms.';
        }

        $has_file = $file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        if ($require_file && !$has_file) {
            $e['coi'] = 'Please attach your Certificate of Incorporation (PDF or image).';
        } elseif ($has_file) {
            $err = $this->check_upload($file);
            if ($err) {
                $e['coi'] = $err;
            }
        }

        return $e;
    }

    private function check_upload($file)
    {
        if (($file['error'] ?? 0) === UPLOAD_ERR_INI_SIZE || ($file['error'] ?? 0) === UPLOAD_ERR_FORM_SIZE || ($file['size'] ?? 0) > self::MAX_BYTES) {
            return 'That file is too large — the limit is 10 MB.';
        }
        if (($file['error'] ?? 0) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            return 'The file did not upload. Please try again.';
        }
        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXT, true)) {
            return 'Please upload a PDF, JPG or PNG file.';
        }
        // Trust the bytes, not the browser's claim
        if (function_exists('finfo_open')) {
            $fi   = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($fi, $file['tmp_name']);
            finfo_close($fi);
            if (!in_array($mime, ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'], true)) {
                return 'That file does not look like a PDF or an image.';
            }
        }

        return null;
    }

    /** Move an uploaded file into the order's private folder. */
    public function store_file($order_id, array $file, $doc_type = 'coi', $staff_id = null)
    {
        $err = $this->check_upload($file);
        if ($err) {
            return $err;
        }
        $ext  = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        $name = $doc_type . '-' . bin2hex(random_bytes(8)) . '.' . $ext;
        $dir  = duns_upload_dir($order_id);
        if (!@move_uploaded_file($file['tmp_name'], $dir . $name)) {
            return 'The file could not be saved. Please try again.';
        }

        $mime = null;
        if (function_exists('finfo_open')) {
            $fi   = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($fi, $dir . $name);
            finfo_close($fi);
        }

        $this->db->insert($this->t['files'], [
            'order_id'      => (int) $order_id,
            'doc_type'      => $doc_type,
            'file_name'     => $name,
            'original_name' => mb_substr(preg_replace('/[^\w .()\-]+/u', '_', basename((string) $file['name'])), 0, 190),
            'mime'          => $mime,
            'size'          => (int) $file['size'],
            'uploaded_by'   => $staff_id,
            'created_at'    => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insert_id();
    }

    /**
     * Create an order from the public form (already validated).
     *
     * @return object|string The order, or an error message
     */
    public function create_order(array $in, array $file, array $meta = [])
    {
        $plan = $this->get_plan((int) $in['plan_id']);
        $now  = date('Y-m-d H:i:s');

        $this->db->insert($this->t['orders'], [
            'ref'             => bin2hex(random_bytes(16)),
            'plan_id'         => (int) $plan->id,
            'plan_name'       => $plan->name,
            'delivery_hours'  => (int) $plan->delivery_hours,
            'amount'          => (float) $plan->price,
            'company_name'    => mb_substr(trim($in['company_name']), 0, 191),
            'cin'             => mb_substr(strtoupper(preg_replace('/\s+/', '', (string) ($in['cin'] ?? ''))), 0, 40) ?: null,
            'director_name'   => mb_substr(trim($in['director_name']), 0, 191),
            'director_email'  => mb_substr(strtolower(trim($in['director_email'])), 0, 191),
            'director_mobile' => mb_substr(trim($in['director_mobile']), 0, 30),
            'work_email'      => mb_substr(strtolower(trim($in['work_email'])), 0, 191),
            'notes'           => trim((string) ($in['notes'] ?? '')) ?: null,
            'status'          => 'pending_payment',
            'utm_source'      => mb_substr((string) ($meta['utm_source'] ?? ''), 0, 100) ?: null,
            'utm_medium'      => mb_substr((string) ($meta['utm_medium'] ?? ''), 0, 100) ?: null,
            'utm_campaign'    => mb_substr((string) ($meta['utm_campaign'] ?? ''), 0, 191) ?: null,
            'fbclid'          => mb_substr((string) ($meta['fbclid'] ?? ''), 0, 255) ?: null,
            'ip_address'      => mb_substr((string) ($meta['ip'] ?? ''), 0, 45) ?: null,
            'created_at'      => $now,
        ]);
        $id = (int) $this->db->insert_id();
        if (!$id) {
            return 'We could not save your order. Please try again.';
        }
        $this->db->where('id', $id)->update($this->t['orders'], ['order_no' => 'DUNS-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT)]);

        $stored = $this->store_file($id, $file, 'coi');
        if (!is_int($stored)) {
            // The order exists; staff can still collect the certificate by email
            $this->add_event($id, 'system', 'Certificate upload failed: ' . $stored);
        }
        $this->add_event($id, 'system', 'Order placed from the landing page — ' . $plan->name . ' (' . duns_hours_label($plan->delivery_hours) . ', ' . duns_money($plan->price) . ').');

        return $this->get($id);
    }

    public function update_details($id, array $in)
    {
        $data = [];
        foreach (['company_name', 'director_name', 'director_email', 'director_mobile', 'work_email', 'cin', 'notes'] as $k) {
            if (array_key_exists($k, $in)) {
                $data[$k] = trim((string) $in[$k]) !== '' ? trim((string) $in[$k]) : null;
            }
        }
        foreach (['company_name', 'director_name', 'director_email', 'director_mobile', 'work_email'] as $req) {
            if (array_key_exists($req, $data) && $data[$req] === null) {
                return ucfirst(str_replace('_', ' ', $req)) . ' cannot be empty.';
            }
        }
        if (array_key_exists('assigned_to', $in)) {
            $data['assigned_to'] = (int) $in['assigned_to'] ?: null;
        }
        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->db->where('id', (int) $id)->update($this->t['orders'], $data);
        $this->add_event($id, 'system', 'Order details edited.', get_staff_user_id());

        return true;
    }

    public function delete_order($id)
    {
        $order = $this->get($id);
        if (!$order) {
            return false;
        }
        foreach ($this->get_files($id) as $f) {
            @unlink(duns_upload_dir($id) . $f->file_name);
        }
        @unlink(duns_upload_dir($id) . 'index.html');
        @rmdir(duns_upload_dir($id));
        $this->db->where('order_id', (int) $id)->delete($this->t['files']);
        $this->db->where('order_id', (int) $id)->delete($this->t['events']);
        $this->db->where('id', (int) $id)->delete($this->t['orders']);
        log_activity('DUNS order deleted: ' . $order->order_no . ' (' . $order->company_name . ')');

        return true;
    }

    /* ═════════════════════════ Files & timeline ═════════════════════════ */

    public function get_files($order_id)
    {
        return $this->db->where('order_id', (int) $order_id)->order_by('id', 'ASC')->get($this->t['files'])->result();
    }

    public function get_file($id)
    {
        return $this->db->where('id', (int) $id)->get($this->t['files'])->row();
    }

    public function delete_file($id)
    {
        $f = $this->get_file($id);
        if (!$f) {
            return false;
        }
        @unlink(duns_upload_dir($f->order_id) . $f->file_name);
        $this->db->where('id', (int) $id)->delete($this->t['files']);
        $this->add_event($f->order_id, 'system', 'Document removed: ' . $f->original_name, get_staff_user_id());

        return true;
    }

    public function add_event($order_id, $type, $message, $staff_id = null)
    {
        $this->db->insert($this->t['events'], [
            'order_id'   => (int) $order_id,
            'type'       => $type,
            'message'    => $message,
            'staff_id'   => $staff_id ?: null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function get_events($order_id)
    {
        return $this->db->where('order_id', (int) $order_id)->order_by('id', 'DESC')->get($this->t['events'])->result();
    }

    /* ═══════════════════════════ Status changes ═════════════════════════ */

    /** @return true|string */
    public function set_status($id, $status, $note = '', $staff_id = null)
    {
        $order = $this->get($id);
        if (!$order || !isset(duns_statuses()[$status])) {
            return 'Unknown order or status.';
        }
        if ($status === 'completed') {
            return 'Use "Deliver DUNS number" to complete an order.';
        }
        if ($order->status === $status) {
            return true;
        }

        $data = ['status' => $status, 'updated_at' => date('Y-m-d H:i:s')];
        // Marking an unpaid order as paid by hand (money received outside the gateway)
        if (in_array($status, ['paid', 'processing'], true) && !$order->paid_at) {
            $data['paid_at']     = date('Y-m-d H:i:s');
            $data['due_at']      = date('Y-m-d H:i:s', time() + (int) $order->delivery_hours * 3600);
            $data['amount_paid'] = max((float) $order->amount_paid, (float) $order->amount);
            $data['gateway']     = $order->gateway ?: 'offline';
        }
        $this->db->where('id', (int) $id)->update($this->t['orders'], $data);

        $from = duns_statuses()[$order->status]['label'] ?? $order->status;
        $to   = duns_statuses()[$status]['label'];
        $this->add_event($id, 'status', $from . ' → ' . $to . ($note !== '' ? ' — ' . $note : ''), $staff_id);

        return true;
    }

    /** Hand over the DUNS number: store it, complete the order, email the customer. */
    public function deliver($id, $duns_number, $note = '', $send_email = true, $staff_id = null)
    {
        $order = $this->get($id);
        if (!$order) {
            return 'Unknown order.';
        }
        $duns = preg_replace('/\D+/', '', (string) $duns_number);
        if (strlen($duns) !== 9) {
            return 'A DUNS number has exactly 9 digits.';
        }

        $this->db->where('id', (int) $id)->update($this->t['orders'], [
            'duns_number'  => $duns,
            'status'       => 'completed',
            // A resend keeps the original delivery time — that is what the deadline is measured against
            'completed_at' => $order->completed_at ?: date('Y-m-d H:i:s'),
            'updated_at'   => date('Y-m-d H:i:s'),
        ]);

        $late = $order->due_at && strtotime($order->completed_at ?: 'now') > strtotime($order->due_at);
        $this->add_event($id, 'status', 'DUNS number ' . duns_format_number($duns) . ' delivered' . ($late ? ' (after the deadline)' : ' on time') . ($note !== '' ? ' — ' . $note : ''), $staff_id);

        if ($send_email) {
            $order = $this->get($id);
            $sent  = $this->email_delivered($order, $note);
            $this->add_event($id, 'email', $sent ? 'DUNS number emailed to ' . $order->director_email . ($order->work_email !== $order->director_email ? ' and ' . $order->work_email : '') . '.' : 'The delivery email could not be sent — check the email settings.', $staff_id);
        }

        return true;
    }

    /* ════════════════════════ Checkout & payment ════════════════════════ */

    /**
     * Raise (or re-use) the invoice this order is paid through.
     *
     * @return object|string invoice or an error message
     */
    public function prepare_invoice($order, $gateway = '')
    {
        $this->load->model('invoices_model');

        if ($order->invoice_id) {
            $inv = $this->invoices_model->get((int) $order->invoice_id);
            if ($inv && in_array((int) $inv->status, [1, 3, 4], true)) {
                if ($gateway !== '') {
                    $this->db->where('id', $order->id)->update($this->t['orders'], ['gateway' => substr($gateway, 0, 40)]);
                }

                return $inv;
            }
        }

        $client_id = $order->client_id ?: $this->ensure_client($order);
        if (!$client_id) {
            return 'We could not open your account. Please contact us and we will help you pay.';
        }

        $client = $this->db->where('userid', $client_id)->get(db_prefix() . 'clients')->row();
        $invoice_id = $this->invoices_model->add([
            'clientid'                 => $client_id,
            'number'                   => get_option('next_invoice_number'),
            'date'                     => date('Y-m-d'),
            'duedate'                  => date('Y-m-d'),
            'currency'                 => get_base_currency()->id,
            'subtotal'                 => (float) $order->amount,
            'total'                    => (float) $order->amount,
            'discount_percent'         => 0,
            'discount_total'           => 0,
            'discount_type'            => '',
            'status'                   => 1,
            'adminnote'                => 'DUNS Support · ' . $order->order_no . ' · ' . $order->company_name,
            'clientnote'               => '',
            'terms'                    => '100% money-back guarantee if the DUNS number is not delivered.',
            'show_quantity_as'         => 1,
            'newitems'                 => [[
                'order'            => 1,
                'description'      => 'DUNS Number registration — ' . $order->plan_name,
                'long_description' => 'Delivery within ' . duns_hours_label($order->delivery_hours) . ' · ' . $order->company_name . ' · Order ' . $order->order_no,
                'qty'              => 1,
                'unit'             => '',
                'rate'             => (float) $order->amount,
                'taxname'          => [],
            ]],
            'billing_street'           => (string) ($client->billing_street ?? ''),
            'billing_city'             => (string) ($client->billing_city ?? ''),
            'billing_state'            => (string) ($client->billing_state ?? ''),
            'billing_zip'              => (string) ($client->billing_zip ?? ''),
            'billing_country'          => (int) ($client->billing_country ?? 0),
            'include_shipping'         => 0,
            'show_shipping_on_invoice' => 0,
            'allowed_payment_modes'    => duns_pay_selected_ids(),
        ]);
        if (!$invoice_id) {
            return 'We could not start the payment. Please try again in a minute.';
        }

        $this->db->where('id', $order->id)->update($this->t['orders'], [
            'client_id'  => $client_id,
            'invoice_id' => $invoice_id,
            'gateway'    => $gateway !== '' ? substr($gateway, 0, 40) : null,
        ]);
        $this->add_event($order->id, 'payment', 'Invoice ' . format_invoice_number($invoice_id) . ' raised for checkout.');

        return $this->invoices_model->get($invoice_id);
    }

    /** Find (by email) or create the CRM customer the invoice belongs to. */
    private function ensure_client($order)
    {
        $c = $this->db->select('userid')->where('email', $order->director_email)->limit(1)->get(db_prefix() . 'contacts')->row();
        if ($c) {
            return (int) $c->userid;
        }

        $this->load->model('clients_model');
        $parts = preg_split('/\s+/', trim($order->director_name), 2);

        $client_id = $this->clients_model->add([
            'company'               => $order->company_name,
            'phonenumber'           => $order->director_mobile,
            'firstname'             => $parts[0],
            'lastname'              => $parts[1] ?? '-',
            'email'                 => $order->director_email,
            'contact_phonenumber'   => $order->director_mobile,
            'password'              => bin2hex(random_bytes(8)),
            'donotsendwelcomeemail' => true,
            'is_primary'            => 1,
        ], true);

        return (int) $client_id;
    }

    /**
     * Money landed on an invoice. Runs for the browser callback AND the gateway
     * webhook, so it must be idempotent.
     */
    public function on_invoice_paid($invoice_id)
    {
        $order = $this->db->where('invoice_id', (int) $invoice_id)->get($this->t['orders'])->row();
        if (!$order) {
            return null;
        }

        $paid = (float) $this->db->select_sum('amount')->where('invoiceid', (int) $invoice_id)
            ->get(db_prefix() . 'invoicepaymentrecords')->row()->amount;
        $last = $this->db->select('paymentmode')->where('invoiceid', (int) $invoice_id)->order_by('id', 'DESC')->limit(1)
            ->get(db_prefix() . 'invoicepaymentrecords')->row();

        $this->db->where('id', $order->id)->update($this->t['orders'], ['amount_paid' => round($paid, 2)]);

        if ($paid + 0.009 < (float) $order->amount) {
            $this->add_event($order->id, 'payment', 'Part payment received: ' . duns_money($paid) . ' of ' . duns_money($order->amount) . '.');

            return $order->id;
        }
        if (!in_array($order->status, ['pending_payment', 'cancelled'], true)) {
            return $order->id; // already queued (webhook + callback both fired)
        }

        // Online gateways record their id ("cashfree"); offline modes recorded by staff record a numeric id
        $via = $last ? (string) $last->paymentmode : '';
        if ($via !== '' && is_numeric($via)) {
            $mode = $this->db->select('name')->where('id', (int) $via)->get(db_prefix() . 'payment_modes')->row();
            $via  = $mode ? $mode->name : 'offline';
        }

        $now = time();
        $this->db->where('id', $order->id)->update($this->t['orders'], [
            'status'     => 'paid',
            'paid_at'    => date('Y-m-d H:i:s', $now),
            'due_at'     => date('Y-m-d H:i:s', $now + (int) $order->delivery_hours * 3600),
            'gateway'    => $via !== '' ? substr($via, 0, 40) : $order->gateway,
            'updated_at' => date('Y-m-d H:i:s', $now),
        ]);
        $this->add_event($order->id, 'payment', 'Payment of ' . duns_money($paid) . ' received' . ($via !== '' ? ' via ' . $via : '') . '. Delivery clock started: ' . duns_hours_label($order->delivery_hours) . '.');

        $order = $this->get($order->id);
        $this->notify_staff_paid($order);
        if (get_option('duns_email_customer') == '1') {
            $this->email_paid($order);
        }

        return $order->id;
    }

    public function mark_purchase_tracked($id)
    {
        $this->db->where('id', (int) $id)->where('purchase_tracked', 0)->update($this->t['orders'], ['purchase_tracked' => 1]);

        return $this->db->affected_rows() > 0;
    }

    /* ═════════════════════════════ Messaging ════════════════════════════ */

    private function notify_staff_paid($order)
    {
        $link = 'duns_support/order/' . $order->id;
        $msg  = 'New paid DUNS order ' . $order->order_no . ' — ' . $order->company_name . ' (' . $order->plan_name . ', due in ' . duns_hours_label($order->delivery_hours) . ')';

        $staff = $this->db->select('staffid, admin')->where('active', 1)->get(db_prefix() . 'staff')->result();
        $ids   = [];
        foreach ($staff as $s) {
            if ((int) $s->admin === 1 || staff_can('view', 'duns_support', (int) $s->staffid)) {
                if (add_notification([
                    'description'     => $msg,
                    'touserid'        => (int) $s->staffid,
                    'fromcompany'     => 1,
                    'fromuserid'      => 0,
                    'link'            => $link,
                ])) {
                    $ids[] = (int) $s->staffid;
                }
            }
        }
        if (count($ids) && function_exists('pusher_trigger_notification')) {
            pusher_trigger_notification($ids);
        }

        $html = duns_email_html('New paid DUNS order', 'A customer has paid. The delivery deadline is <b>' . duns_datetime($order->due_at) . '</b>.', [
            'Order'     => html_escape($order->order_no),
            'Plan'      => html_escape($order->plan_name) . ' · ' . duns_hours_label($order->delivery_hours),
            'Paid'      => duns_money($order->amount_paid),
            'Company'   => html_escape($order->company_name),
            'Director'  => html_escape($order->director_name) . '<br>' . html_escape($order->director_email) . '<br>' . html_escape($order->director_mobile),
            'Work email' => html_escape($order->work_email),
        ], ['Open the order', admin_url($link)]);
        foreach (duns_notify_emails() as $to) {
            duns_send_email($to, '[' . $order->order_no . '] Paid — deliver within ' . duns_hours_label($order->delivery_hours), $html);
        }
    }

    private function email_paid($order)
    {
        $brand = duns_landing()['brand'];
        $html  = duns_email_html('Payment received — we are on it', 'Hi ' . html_escape($order->director_name) . ', thank you for your order. Our team has started on your DUNS number and will email it to you by <b>' . duns_datetime($order->due_at) . '</b>.', [
            'Order'    => html_escape($order->order_no),
            'Company'  => html_escape($order->company_name),
            'Plan'     => html_escape($order->plan_name) . ' · within ' . duns_hours_label($order->delivery_hours),
            'Amount'   => duns_money($order->amount_paid),
        ], ['Track your order', duns_public_url('status/' . $order->ref)],
            'We may send a one-time verification code to your work email (' . html_escape($order->work_email) . ') — please keep it handy. 100% money-back guarantee if we cannot deliver.');
        $ok = duns_send_email($order->director_email, 'Order ' . $order->order_no . ' confirmed — ' . $brand, $html);
        $this->add_event($order->id, 'email', $ok ? 'Payment confirmation emailed to ' . $order->director_email . '.' : 'Payment confirmation email failed.');
    }

    private function email_delivered($order, $note = '')
    {
        $brand = duns_landing()['brand'];
        $html  = duns_email_html('Your DUNS number is ready', 'Hi ' . html_escape($order->director_name) . ', here is the D-U-N-S number for <b>' . html_escape($order->company_name) . '</b>. You can use it for Google Play Console, the Apple Developer Program and anywhere else a DUNS number is required.'
            . ($note !== '' ? '<br><br>' . nl2br(html_escape($note)) : ''), [
            'DUNS number' => '<span style="font-size:20px;letter-spacing:1px">' . duns_format_number($order->duns_number) . '</span>',
            'Company'     => html_escape($order->company_name),
            'Order'       => html_escape($order->order_no),
        ], ['View your order', duns_public_url('status/' . $order->ref)], 'Thank you for choosing ' . html_escape($brand) . '.');

        $ok = duns_send_email($order->director_email, 'Your DUNS number — ' . $order->company_name, $html);
        if ($order->work_email && $order->work_email !== $order->director_email) {
            duns_send_email($order->work_email, 'Your DUNS number — ' . $order->company_name, $html);
        }

        return $ok;
    }
}
