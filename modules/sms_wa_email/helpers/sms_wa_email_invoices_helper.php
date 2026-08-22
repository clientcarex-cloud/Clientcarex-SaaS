<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * ═══════════════════════════════════════════════════════════════
 *  OMNI MESSAGING — Invoice system hooks
 * ═══════════════════════════════════════════════════════════════
 *
 * Hook DEFINITIONS live in application/helpers/ccx_hooks/invoices_hooks.php —
 * one per e-mail template in the "Invoices" section of Setup → Email Templates.
 * This file builds their variable payload and carries the listeners that fire
 * them.
 *
 * Invoices are a CORE entity with no owning module, so the listeners live here,
 * in Omni Messaging itself: the hooks exist exactly when there is a messaging
 * stack to fire them into. They ride the core action hooks —
 *
 *   invoice_object_before_send_to_client  (filter, snapshot only)
 *   invoice_sent                          → invoice_send_to_client
 *                                         / invoice_already_send
 *   after_payment_added                   → invoice_payment_recorded
 *                                         + invoice_payment_recorded_to_staff
 *                                         + invoices_batch_payments (batches)
 *   invoice_overdue_reminder_sent         → invoice_overdue_notice
 *   invoice_due_reminder_sent             → invoice_due_notice
 *
 * — so nothing in application/ is patched and a CRM upgrade cannot undo any of
 * it.
 *
 * Unlike the e-mail templates, these fire on the invoice EVENT rather than on an
 * e-mail leaving the building. A tenant that keeps the invoice e-mail templates
 * switched off and messages over WhatsApp only still gets every hook.
 */

/* ═══════════════════════════════════════════════════════════════
   PAYLOAD
   ═══════════════════════════════════════════════════════════════ */

/**
 * Is any of these hook keys actually mapped to a live template?
 *
 * ccx_fire_hook() already returns early without a mapping, but only after the
 * caller has built the payload and only after writing a "no_mapping" row into
 * the trigger log. Invoice payments are a hot path (gateway callbacks, batches
 * of dozens), so a single COUNT up front keeps an installation that mapped
 * nothing at one query instead of a payload plus a log write per event.
 *
 * A hook the tenant HAS mapped always fires and always logs — that is the row
 * the Hook Debugger's "Recent logs" is read for.
 *
 * Memoized per request: a batch of fifty payments asks the same question fifty
 * times, and nothing that records a payment also edits the mappings.
 *
 * @param  string|string[] $hook_keys
 * @return bool
 */
function sms_wa_email_invoice_hook_mapped($hook_keys)
{
    static $memo = [];

    $keys = (array) $hook_keys;
    sort($keys);
    $memo_key = implode('|', $keys);

    if (isset($memo[$memo_key])) {
        return $memo[$memo_key];
    }

    $CI    = &get_instance();
    $table = db_prefix() . 'ccx_hook_template_map';

    if (!$CI->db->table_exists($table)) {
        return $memo[$memo_key] = false;
    }

    return $memo[$memo_key] = (bool) $CI->db
        ->where_in('hook_key', $keys)
        ->where('active', 1)
        ->count_all_results($table);
}

/**
 * The customer half of the payload: who we are messaging.
 *
 * {mobile_number} / {email} are what the default ("patient") recipient type
 * reads. The phone comes off the customer record — that is where this product
 * keeps the patient's own mobile — and falls back to the contact. The e-mail
 * comes off the contact that receives invoice e-mails, which is the same
 * selection core makes in get_contacts_for_invoice_emails().
 *
 * @param  int $client_id
 * @return array
 */
function sms_wa_email_invoice_customer_payload($client_id)
{
    $CI        = &get_instance();
    $client_id = (int) $client_id;

    $client = $CI->db->select('userid, company, phonenumber')
        ->where('userid', $client_id)
        ->get(db_prefix() . 'clients')->row();

    // Same contact core would e-mail, primary first (get_contacts() orders on
    // is_primary DESC); any active contact rather than none at all.
    $contact = $CI->db->select('firstname, lastname, email, phonenumber')
        ->where('userid', $client_id)
        ->where('active', 1)
        ->where('invoice_emails', 1)
        ->order_by('is_primary', 'DESC')
        ->limit(1)
        ->get(db_prefix() . 'contacts')->row();

    if (!$contact) {
        $contact = $CI->db->select('firstname, lastname, email, phonenumber')
            ->where('userid', $client_id)
            ->where('active', 1)
            ->order_by('is_primary', 'DESC')
            ->limit(1)
            ->get(db_prefix() . 'contacts')->row();
    }

    $company = $client ? (string) $client->company : '';
    $mobile  = $client ? (string) $client->phonenumber : '';
    if ($mobile === '' && $contact) {
        $mobile = (string) $contact->phonenumber;
    }
    $email = $contact ? (string) $contact->email : '';

    return [
        'client_id'           => $client_id,
        'client_company'      => $company,
        // The resolver reads {patient_name} for the recipient's name, which is
        // what lands in {recipient_name}.
        'patient_name'        => $company,
        'mobile_number'       => $mobile,
        'email'               => $email,
        'patient_email'       => $email,
        'contact_firstname'   => $contact ? (string) $contact->firstname : '',
        'contact_lastname'    => $contact ? (string) $contact->lastname : '',
        'contact_email'       => $email,
        'contact_phonenumber' => $contact ? (string) $contact->phonenumber : '',
    ];
}

/**
 * Full payload for one invoice: the customer half plus the invoice itself.
 *
 * The tag names mirror the merge fields of the invoice e-mail templates
 * ({invoice_number}, {invoice_amount_due}, {total_days_overdue}, …) so an e-mail
 * body can be pasted into an Omni Messaging template unchanged. Values are NOT
 * html-escaped the way merge fields are — these go into an SMS or a WhatsApp
 * bubble, where "&amp;" would be read literally.
 *
 * @param  int         $invoice_id
 * @param  object|null $currency   out: the invoice's currency, for callers that
 *                                 need to format further amounts against it
 * @return array|null  null when the invoice no longer exists
 */
function sms_wa_email_invoice_payload($invoice_id, &$currency = null)
{
    $CI = &get_instance();
    $p  = db_prefix();

    // number / prefix / number_format / date / status are what
    // format_invoice_number() needs — selecting them lets it take the object and
    // skip a second query per fire.
    $inv = $CI->db->query(
        "SELECT i.id, i.clientid, i.number, i.prefix, i.number_format, i.reference_no,
                i.date, i.duedate, i.status, i.total, i.subtotal, i.currency,
                i.hash, i.short_link, i.sale_agent,
                s.firstname AS s_first, s.lastname AS s_last,
                s.email AS s_email, s.phonenumber AS s_phone
         FROM `{$p}invoices` i
         LEFT JOIN `{$p}staff` s ON s.staffid = i.sale_agent
         WHERE i.id = ? LIMIT 1",
        [(int) $invoice_id]
    )->row();

    if (!$inv) {
        return null;
    }

    // Pass the currency OBJECT to app_format_money() — a bare string is read as
    // a currency NAME, and an unknown one silently loses the tenant's symbol
    // placement and separators.
    $currency = get_currency($inv->currency);

    $due  = get_invoice_total_left_to_pay($inv->id, $inv->total);
    $paid = (float) $inv->total - (float) $due;

    // get_invoice_shortlink() would mint a Bitly link — an outbound HTTP call on
    // a hot path — so reuse one only if the invoice already has it.
    $long_link  = site_url('invoice/' . $inv->id . '/' . $inv->hash);
    $short_link = !empty($inv->short_link) ? $inv->short_link : $long_link;

    return array_merge(sms_wa_email_invoice_customer_payload($inv->clientid), [
        'invoice_id'                => (int) $inv->id,
        'invoice_number'            => format_invoice_number($inv),
        'invoice_reference'         => (string) $inv->reference_no,
        'invoice_date'              => $inv->date ? _d($inv->date) : '',
        'invoice_duedate'           => $inv->duedate ? _d($inv->duedate) : '',
        // format_invoice_status() html-escapes its label; undo that, since a
        // locale with an apostrophe would otherwise arrive as "&#039;".
        'invoice_status'            => html_entity_decode(
            format_invoice_status($inv->status, '', false),
            ENT_QUOTES,
            'UTF-8'
        ),
        'invoice_total'             => app_format_money($inv->total, $currency),
        'invoice_subtotal'          => app_format_money($inv->subtotal, $currency),
        'invoice_amount_paid'       => app_format_money($paid, $currency),
        'invoice_amount_due'        => app_format_money($due, $currency),
        'invoice_currency'          => $currency ? (string) $currency->name : '',
        'invoice_link'              => $long_link,
        'invoice_short_url'         => $short_link,
        'invoice_admin_url'         => admin_url('invoices/list_invoices/' . (int) $inv->id),
        'invoice_sale_agent'        => trim($inv->s_first . ' ' . $inv->s_last),
        'invoice_sale_agent_email'  => (string) $inv->s_email,
        'invoice_sale_agent_mobile' => (string) $inv->s_phone,
        'total_days_overdue'        => $inv->duedate ? get_total_days_overdue($inv->duedate) : 0,
    ]);
}

/**
 * The payment extras for the two "Payment Recorded" hooks.
 *
 * {payment_total} is the money-formatted amount, matching the e-mail merge field
 * of the same name; {payment_amount} is the same value under the name the visit
 * hooks already use.
 *
 * @param  int         $payment_id
 * @param  object|null $currency   the parent invoice's currency
 * @return array
 */
function sms_wa_email_invoice_payment_extras($payment_id, $currency)
{
    $CI = &get_instance();
    $p  = db_prefix();

    $pay = $CI->db->query(
        "SELECT r.id, r.amount, r.date, r.paymentmode, r.transactionid, r.note,
                m.name AS mode_name
         FROM `{$p}invoicepaymentrecords` r
         LEFT JOIN `{$p}payment_modes` m ON m.id = r.paymentmode
         WHERE r.id = ? LIMIT 1",
        [(int) $payment_id]
    )->row();

    if (!$pay) {
        return [];
    }

    // Online gateways store their slug in paymentmode instead of a mode id, so
    // there is no row to join — resolve the gateway's display name instead.
    $mode = (string) $pay->mode_name;
    if ($mode === '' && (string) $pay->paymentmode !== '' && !is_numeric($pay->paymentmode)) {
        $mode = sms_wa_email_invoice_gateway_name($pay->paymentmode);
    }

    $amount = app_format_money($pay->amount, $currency);

    return [
        'payment_id'             => (int) $pay->id,
        'payment_total'          => $amount,
        'payment_amount'         => $amount,
        'payment_date'           => $pay->date ? _d($pay->date) : '',
        'payment_mode'           => $mode,
        'payment_transaction_id' => (string) $pay->transactionid,
        'payment_note'           => sms_wa_email_invoice_excerpt($pay->note),
    ];
}

/**
 * Display name of an online payment gateway by its slug, e.g. "paypal_checkout".
 */
function sms_wa_email_invoice_gateway_name($slug)
{
    $CI = &get_instance();

    if (!class_exists('Payment_modes_model', false)) {
        $CI->load->model('payment_modes_model');
    }

    foreach ($CI->payment_modes_model->get_payment_gateways(true) as $gateway) {
        if ($gateway['id'] === $slug) {
            return $gateway['name'];
        }
    }

    return (string) $slug;
}

/**
 * Flatten an admin note into a one-line excerpt safe for an SMS or a WhatsApp
 * bubble — notes are stored with nl2br() applied.
 */
function sms_wa_email_invoice_excerpt($html, $length = 160)
{
    $text = trim(preg_replace('/\s+/', ' ', strip_tags((string) $html)));

    return mb_strlen($text) > $length ? mb_substr($text, 0, $length - 1) . '…' : $text;
}

/**
 * Fire one invoice hook. Everything is best-effort: a messaging failure must
 * never break the invoice action that triggered it.
 *
 * @param string $hook_key key from application/helpers/ccx_hooks/invoices_hooks.php
 * @param array  $data
 */
function sms_wa_email_invoice_fire($hook_key, array $data)
{
    if (!function_exists('ccx_fire_hook')) {
        return;
    }

    try {
        ccx_fire_hook($hook_key, $data);
    } catch (Throwable $e) {
        log_activity('Omni Messaging invoice hook failed [' . $hook_key . ': ' . $e->getMessage() . ']');
    }
}

/* ═══════════════════════════════════════════════════════════════
   LISTENERS — invoice sent
   ═══════════════════════════════════════════════════════════════ */

/**
 * Backing store for the "was it already sent?" snapshot, returned by reference
 * so the filter and the action share one array.
 *
 * @return array
 */
function &sms_wa_email_invoice_sent_store()
{
    static $store = [];

    return $store;
}

/**
 * Snapshot whether the invoice had already been sent, BEFORE core sends it.
 *
 * Core picks between the "Send Invoice to Customer" and "Invoice Already Sent to
 * Customer" templates on $invoice->sent, then flips that flag — so by the time
 * invoice_sent fires the answer is gone. This filter runs inside
 * send_invoice_to_client() before any of that, and only reads.
 *
 * @param  object $invoice
 * @return object the invoice, untouched
 */
function sms_wa_email_invoice_snapshot_sent($invoice)
{
    if (is_object($invoice) && isset($invoice->id)) {
        $store                     = &sms_wa_email_invoice_sent_store();
        $store[(int) $invoice->id] = !empty($invoice->sent);
    }

    return $invoice;
}

/**
 * invoice_sent → the first-send or the re-send hook, on the same rule core uses
 * to choose between the two e-mail templates.
 *
 * @param int $invoice_id
 */
function sms_wa_email_invoice_on_sent($invoice_id)
{
    $invoice_id = (int) $invoice_id;
    if (!$invoice_id) {
        return;
    }

    $store = &sms_wa_email_invoice_sent_store();
    // No snapshot means something fired invoice_sent outside
    // send_invoice_to_client() — treat it as a first send, which is also core's
    // default template choice.
    $already = !empty($store[$invoice_id]);
    unset($store[$invoice_id]);

    $hook_key = $already ? 'invoice_already_send' : 'invoice_send_to_client';

    if (!sms_wa_email_invoice_hook_mapped($hook_key)) {
        return;
    }

    $data = sms_wa_email_invoice_payload($invoice_id);
    if ($data === null) {
        return;
    }

    sms_wa_email_invoice_fire($hook_key, $data);
}

/* ═══════════════════════════════════════════════════════════════
   LISTENERS — payment recorded
   ═══════════════════════════════════════════════════════════════ */

/**
 * after_payment_added → the customer receipt and the staff alert.
 *
 * Both fire on the payment itself. The staff one is deliberately NOT gated on
 * the "notification_when_customer_pay_invoice" setting that governs the staff
 * e-mail: this mapping has its own Active toggle on the Hooks panel, and a hook
 * a tenant switched on there should not stay silent because of an unrelated
 * e-mail setting.
 *
 * @param int $payment_id
 */
function sms_wa_email_invoice_on_payment_added($payment_id)
{
    $payment_id = (int) $payment_id;
    if (!$payment_id) {
        return;
    }

    // Buffer batch payments for the per-customer batch hook, whether or not the
    // two per-payment hooks below are mapped.
    sms_wa_email_invoice_collect_batch_payment($payment_id);

    // Per key, not as a group: firing a hook we already know is unmapped only
    // writes a "no_mapping" row into the trigger log, and a batch of fifty
    // payments would write a hundred of them.
    $hook_keys = array_filter(
        ['invoice_payment_recorded', 'invoice_payment_recorded_to_staff'],
        'sms_wa_email_invoice_hook_mapped'
    );

    if (empty($hook_keys)) {
        return;
    }

    $CI  = &get_instance();
    $row = $CI->db->select('invoiceid')
        ->where('id', $payment_id)
        ->get(db_prefix() . 'invoicepaymentrecords')->row();

    if (!$row) {
        return;
    }

    $currency = null;
    $data     = sms_wa_email_invoice_payload($row->invoiceid, $currency);
    if ($data === null) {
        return;
    }

    $data = array_merge($data, sms_wa_email_invoice_payment_extras($payment_id, $currency));

    foreach ($hook_keys as $hook_key) {
        sms_wa_email_invoice_fire($hook_key, $data);
    }
}

/* ═══════════════════════════════════════════════════════════════
   LISTENERS — batch payments
   ═══════════════════════════════════════════════════════════════ */

/**
 * Buffer for the batch hook, returned by reference so the collector and the
 * flush share one array.
 *
 * @return array
 */
function &sms_wa_email_invoice_batch_store()
{
    static $payment_ids = [];

    return $payment_ids;
}

/**
 * Remember a payment that was recorded as part of a batch.
 *
 * Core's batch e-mail is decided only once every payment in the batch exists —
 * one message per customer, listing all of them — and add_batch_payment() offers
 * no hook after its loop. So payments recorded inside it are buffered here and
 * flushed on shutdown, which is after the loop AND after the controller's
 * redirect.
 *
 * @param int $payment_id
 */
function sms_wa_email_invoice_collect_batch_payment($payment_id)
{
    if (!sms_wa_email_invoice_in_batch()) {
        return;
    }

    if (!sms_wa_email_invoice_hook_mapped('invoices_batch_payments')) {
        return;
    }

    $store = &sms_wa_email_invoice_batch_store();

    if (empty($store)) {
        register_shutdown_function('sms_wa_email_invoice_flush_batch_payments');
    }

    $store[] = (int) $payment_id;
}

/**
 * Whether we are inside Payments_model::add_batch_payment().
 *
 * after_payment_added fires from both add() and add_batch_payment(), and only
 * the latter sends the batch e-mail — the call stack is the one thing that says
 * which, without depending on the shape of $_POST.
 *
 * @return bool
 */
function sms_wa_email_invoice_in_batch()
{
    foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 12) as $frame) {
        if (isset($frame['function']) && $frame['function'] === 'add_batch_payment') {
            return true;
        }
    }

    return false;
}

/**
 * Fire invoices_batch_payments once per customer, mirroring core: a customer who
 * got two or more payments in this batch hears about them together, a customer
 * who got exactly one is covered by invoice_payment_recorded instead.
 */
function sms_wa_email_invoice_flush_batch_payments()
{
    $store = &sms_wa_email_invoice_batch_store();

    $payment_ids = array_values(array_unique(array_filter($store)));
    // Empty it first: a fatal inside the loop must not leave ids behind for a
    // second shutdown pass to message about twice.
    $store = [];

    if (empty($payment_ids)) {
        return;
    }

    try {
        $CI     = &get_instance();
        $p      = db_prefix();
        $in_ids = implode(',', array_map('intval', $payment_ids));

        // Every row doubles as the invoice object format_invoice_number() takes,
        // so it never re-queries per payment: id / number / prefix /
        // number_format / date / status are the invoice's, and the payment's own
        // id and date are aliased out of the way.
        $rows = $CI->db->query(
            "SELECT r.id AS payment_id, r.amount, r.date AS paid_on,
                    i.id, i.clientid, i.currency,
                    i.number, i.prefix, i.number_format, i.date, i.status
             FROM `{$p}invoicepaymentrecords` r
             INNER JOIN `{$p}invoices` i ON i.id = r.invoiceid
             WHERE r.id IN ({$in_ids})
             ORDER BY r.id ASC"
        )->result();

        $by_client = [];
        foreach ($rows as $row) {
            $by_client[(int) $row->clientid][] = $row;
        }

        foreach ($by_client as $client_id => $payments) {
            // One payment → core sends the single payment receipt, which
            // invoice_payment_recorded has already covered.
            if (count($payments) < 2) {
                continue;
            }

            $currency = get_currency($payments[0]->currency);

            $lines   = [];
            $numbers = [];
            $total   = 0;

            foreach ($payments as $payment) {
                $number    = format_invoice_number($payment);
                $numbers[] = $number;
                $total += (float) $payment->amount;
                $lines[] = $number . ' — ' . app_format_money($payment->amount, $currency)
                    . ($payment->paid_on ? ' (' . _d($payment->paid_on) . ')' : '');
            }

            sms_wa_email_invoice_fire('invoices_batch_payments', array_merge(
                sms_wa_email_invoice_customer_payload($client_id),
                [
                    'batch_payments_list'   => implode("\n", $lines),
                    'batch_payments_count'  => count($payments),
                    'batch_payments_total'  => app_format_money($total, $currency),
                    'batch_invoice_numbers' => implode(', ', $numbers),
                ]
            ));
        }
    } catch (Throwable $e) {
        log_activity('Omni Messaging batch payment hook failed [' . $e->getMessage() . ']');
    }
}

/* ═══════════════════════════════════════════════════════════════
   LISTENERS — due / overdue reminders
   ═══════════════════════════════════════════════════════════════ */

/**
 * invoice_overdue_reminder_sent / invoice_due_reminder_sent → the matching hook.
 *
 * Core hands both the same payload shape: invoice_id, sent_to (the e-mail
 * addresses it reached) and sms_send.
 *
 * @param array  $args
 * @param string $hook_key
 */
function sms_wa_email_invoice_on_reminder($args, $hook_key)
{
    $invoice_id = is_array($args) && isset($args['invoice_id']) ? (int) $args['invoice_id'] : 0;
    if (!$invoice_id) {
        return;
    }

    if (!sms_wa_email_invoice_hook_mapped($hook_key)) {
        return;
    }

    $data = sms_wa_email_invoice_payload($invoice_id);
    if ($data === null) {
        return;
    }

    $sent_to         = isset($args['sent_to']) ? $args['sent_to'] : [];
    $data['sent_to'] = is_array($sent_to) ? implode(', ', $sent_to) : (string) $sent_to;

    sms_wa_email_invoice_fire($hook_key, $data);
}

function sms_wa_email_invoice_on_overdue_reminder($args)
{
    sms_wa_email_invoice_on_reminder($args, 'invoice_overdue_notice');
}

function sms_wa_email_invoice_on_due_reminder($args)
{
    sms_wa_email_invoice_on_reminder($args, 'invoice_due_notice');
}
