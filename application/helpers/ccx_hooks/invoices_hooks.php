<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * ──────────────────────────────────────────────────────
 *  INVOICES — Hook Definitions
 * ──────────────────────────────────────────────────────
 *
 * One hook per e-mail template in the "Invoices" section of
 * Setup → Email Templates (admin/emails), so anything the CRM already
 * e-mails about an invoice can also go out over SMS, Official WhatsApp,
 * Email or the AI Call Agent:
 *
 *   invoice-send-to-client               → invoice_send_to_client
 *   invoice-already-send                 → invoice_already_send
 *   invoice-payment-recorded             → invoice_payment_recorded
 *   invoice-payment-recorded-to-staff    → invoice_payment_recorded_to_staff
 *   invoice-overdue-notice               → invoice_overdue_notice
 *   invoice-due-notice                   → invoice_due_notice
 *   invoices-batch-payments              → invoices_batch_payments
 *
 * The variable names deliberately mirror the merge fields of those e-mail
 * templates ({invoice_number}, {invoice_amount_due}, {payment_total}, …) so the
 * text of an e-mail body can be pasted into an Omni Messaging template with no
 * renaming.
 *
 * These hooks fire on the underlying invoice/payment EVENT, not on the e-mail
 * leaving the building — so they still work on installations that keep the
 * invoice e-mail templates switched off and message over WhatsApp only.
 *
 * Invoices are a CORE entity, so there is no 'requires_module' gate. The
 * listeners live in the Omni Messaging module itself
 * (modules/sms_wa_email/helpers/sms_wa_email_invoices_helper.php) and ride the
 * core invoice/payment action hooks — nothing in core is patched.
 *
 * Recipient resolution: {mobile_number} / {email} are the CUSTOMER's, which is
 * what the default ("patient") recipient type reads. The staff-facing hook
 * (invoice_payment_recorded_to_staff) is meant to be mapped with recipient type
 * Staff / Role, or Custom pointed at {invoice_sale_agent_mobile}.
 */

// Who the message is about — carried by every hook here, including the batch
// one, which is per-customer rather than per-invoice.
$invoice_customer_vars = [
    'client_id',
    'client_company',
    'patient_name',
    'mobile_number',
    'email',
    'patient_email',
    'contact_firstname',
    'contact_lastname',
    'contact_email',
    'contact_phonenumber',
];

// The invoice itself — every hook except the batch one, which spans several
// invoices at once.
$invoice_document_vars = [
    'invoice_id',
    'invoice_number',
    'invoice_reference',
    'invoice_date',
    'invoice_duedate',
    'invoice_status',
    'invoice_total',
    'invoice_subtotal',
    'invoice_amount_paid',
    'invoice_amount_due',
    'invoice_currency',
    'invoice_link',
    'invoice_short_url',
    'invoice_admin_url',
    'invoice_sale_agent',
    'invoice_sale_agent_email',
    'invoice_sale_agent_mobile',
    'total_days_overdue',
];

// The hooks that carry a single payment record.
$invoice_payment_vars = [
    'payment_id',
    'payment_total',
    'payment_amount',
    'payment_date',
    'payment_mode',
    'payment_transaction_id',
    'payment_note',
];

// All of them are produced once per fire in sms_wa_email_invoice_payload() /
// sms_wa_email_invoice_customer_payload() — anything added to a list above must
// be produced there too, or the tag survives unreplaced in the message.
$invoice_hook = function ($key, $label, $description, array $extra_vars = []) use ($invoice_customer_vars, $invoice_document_vars) {
    return [
        'hook_key'    => $key,
        'label'       => $label,
        'module'      => 'invoices',
        'description' => $description,
        'variables'   => array_merge($invoice_customer_vars, $invoice_document_vars, $extra_vars),
    ];
};

return [

    // ── Invoice Sent to Customer (invoice-send-to-client) ──────────
    $invoice_hook(
        'invoice_send_to_client',
        'Invoice — Sent to Customer',
        'Triggered the FIRST time an invoice is sent to the customer — from the invoice Send button, Save & Send, a scheduled e-mail or the cron. Mirrors the "Send Invoice to Customer" e-mail template.'
    ),

    // ── Invoice Re-sent to Customer (invoice-already-send) ─────────
    $invoice_hook(
        'invoice_already_send',
        'Invoice — Re-sent to Customer',
        'Triggered when an invoice that had already been sent once is sent to the customer again. Mirrors the "Invoice Already Sent to Customer" e-mail template, so a repeat send can read differently from the first one.'
    ),

    // ── Payment Recorded → Customer (invoice-payment-recorded) ─────
    $invoice_hook(
        'invoice_payment_recorded',
        'Invoice — Payment Recorded (Customer)',
        'Triggered when a payment is recorded against an invoice — by staff, from the customer area, by an online payment gateway or inside a batch. The usual "we have received your payment" receipt.',
        $invoice_payment_vars
    ),

    // ── Payment Recorded → Staff (invoice-payment-recorded-to-staff) ──
    $invoice_hook(
        'invoice_payment_recorded_to_staff',
        'Invoice — Payment Recorded (Staff)',
        'Same moment as the customer receipt, for the internal alert. Map it with recipient type Staff / Role, or Custom → {invoice_sale_agent_mobile} to reach the invoice\'s sale agent.',
        $invoice_payment_vars
    ),

    // ── Overdue Notice (invoice-overdue-notice) ────────────────────
    $invoice_hook(
        'invoice_overdue_notice',
        'Invoice — Overdue Notice',
        'Triggered when the overdue reminder goes out for an unpaid invoice past its due date — from the cron or the manual "Send overdue notice" action. {total_days_overdue} carries how late it is.',
        ['sent_to']
    ),

    // ── Due Notice (invoice-due-notice) ────────────────────────────
    $invoice_hook(
        'invoice_due_notice',
        'Invoice — Due Notice',
        'Triggered when the before-due reminder goes out, on the schedule set in Setup → Settings → Invoices. A gentle "your invoice is due on {invoice_duedate}" nudge.',
        ['sent_to']
    ),

    // ── Batch Payments (invoices-batch-payments) ───────────────────
    [
        'hook_key'    => 'invoices_batch_payments',
        'label'       => 'Invoice — Payments Recorded in Batch (Customer)',
        'module'      => 'invoices',
        'description' => 'Triggered once per customer when two or more payments are recorded together through Batch Payments, exactly where the batch e-mail goes out. {batch_payments_list} is the plain-text list of invoice numbers, amounts and dates. Note that "Payment Recorded (Customer)" also fires for each payment in the batch — map one or the other, not both, or the customer hears twice.',
        'variables'   => array_merge($invoice_customer_vars, [
            'batch_payments_list',
            'batch_payments_count',
            'batch_payments_total',
            'batch_invoice_numbers',
        ]),
    ],
];
