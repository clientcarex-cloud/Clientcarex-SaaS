<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * ──────────────────────────────────────────────────────
 *  PRO SERVICES MODULE — Hook Definitions
 * ──────────────────────────────────────────────────────
 *
 * Each entry must contain:
 *   hook_key, label, module, description, variables
 *
 * Every entry carries 'requires_module' => 'pro_services', so the set only
 * appears on the Omni Messaging → Hooks panel once the Pro Services module is
 * ACTIVATED — nothing can fire them otherwise.
 *
 * Pro Services bills CUSTOMERS on a recurring schedule, so the default
 * ("patient") recipient of every hook is the customer's primary contact:
 * {mobile_number} / {email} are theirs. The staff member the subscription is
 * assigned to is exposed separately as {assigned_mobile} / {assigned_email} —
 * map staff-facing hooks with recipient type Staff / Role, or Custom pointed
 * at those tags.
 *
 * The money links are the point of the whole module:
 *   {payment_link}   the CRM invoice's own payment page — every payment
 *                    gateway the installation has switched on (Stripe, PayPal,
 *                    Razorpay, PayU, offline modes) is offered there, and a
 *                    successful payment is recorded against the invoice by
 *                    core, with no extra step.
 *   {portal_link}    the customer's subscription portal — plan, schedule, full
 *                    billing history and a Pay button on every open invoice.
 * Both are unguessable tokens / hashes, safe to send over SMS and WhatsApp.
 */

// Shared by every Pro Services hook — resolved once per fire in
// pro_services_omni_payload() (modules/pro_services/helpers/pro_services_helper.php).
$pro_services_common_vars = [
    'subscription_id',
    'subscription_ref',
    'service_name',
    'plan_name',
    'customer_name',
    'contact_name',
    'company',
    'mobile_number',
    'email',
    'subscription_amount',
    'subscription_status',
    'billing_cycle',
    'quantity',
    'start_date',
    'next_billing_date',
    'end_date',
    'cycles_billed',
    'total_cycles',
    'portal_link',
    'assigned_name',
    'assigned_email',
    'assigned_mobile',
    'admin_subscription_url',
];

// Added on top of the common set by every hook that is about one invoice /
// one payment. Kept in one place so a template written for "payment overdue"
// works unchanged on "payment reminder".
$pro_services_invoice_vars = [
    'invoice_number',
    'invoice_date',
    'invoice_due_date',
    'invoice_amount',
    'amount_due',
    'amount_paid',
    'invoice_status',
    'payment_link',
    'billing_period',
    'cycle_no',
];

$pro_services_hook = function ($key, $label, $description, array $extra_vars = []) use ($pro_services_common_vars) {
    return [
        'hook_key'        => $key,
        'label'           => $label,
        'module'          => 'pro_services',
        'requires_module' => 'pro_services',
        'description'     => $description,
        'variables'       => array_merge($pro_services_common_vars, $extra_vars),
    ];
};

return [

    // ── Subscription created ───────────────────────────
    $pro_services_hook(
        'pro_services_subscription_created',
        'Pro Services — Subscription Started',
        'Triggered when a recurring service is set up for a customer. The welcome message: confirm what they signed up for, what it costs, when the first bill lands, and send {portal_link} so they can see the schedule and pay from one place.',
        ['created_by', 'first_billing_date', 'trial_end_date', 'setup_fee']
    ),

    // ── Invoice generated for a cycle ──────────────────
    $pro_services_hook(
        'pro_services_invoice_generated',
        'Pro Services — Invoice Raised (Payment Request)',
        'Triggered by cron each time a billing cycle raises its CRM invoice — the request for money. This is the hook that must carry {payment_link}: it opens the invoice\'s own payment page, where every gateway switched on in Setup → Payment Gateways is offered and the payment is recorded automatically.',
        array_merge($pro_services_invoice_vars, ['days_until_due', 'is_first_invoice'])
    ),

    // ── Reminder before due date ───────────────────────
    $pro_services_hook(
        'pro_services_payment_reminder',
        'Pro Services — Payment Reminder (Before Due)',
        'The polite nudge before the money is due, fired by cron on each offset set in Pro Services → Settings (7, 3 and 1 days before by default). One template covers every offset — {days_until_due} says how many days are left.',
        array_merge($pro_services_invoice_vars, ['days_until_due', 'reminder_window'])
    ),

    // ── Due today ──────────────────────────────────────
    $pro_services_hook(
        'pro_services_payment_due_today',
        'Pro Services — Payment Due Today',
        'Fired on the morning of the due date, once per invoice. Short and actionable: the amount and {payment_link}.',
        $pro_services_invoice_vars
    ),

    // ── Overdue ────────────────────────────────────────
    $pro_services_hook(
        'pro_services_payment_overdue',
        'Pro Services — Payment Overdue',
        'The dunning message, fired on each overdue offset set in Settings (1, 3, 7 and 15 days past due by default). {days_overdue} carries how late the payment is, so one template can escalate its own wording.',
        array_merge($pro_services_invoice_vars, ['days_overdue', 'overdue_stage'])
    ),

    // ── Payment received ───────────────────────────────
    $pro_services_hook(
        'pro_services_payment_received',
        'Pro Services — Payment Received',
        'Triggered the moment a payment is recorded against a subscription invoice — online through a gateway or entered by staff. The receipt: what was paid, how, and what is still open ({amount_due} is 0 when the invoice is fully settled).',
        array_merge($pro_services_invoice_vars, ['payment_amount', 'payment_mode', 'payment_date', 'transaction_id'])
    ),

    // ── Renewal coming up ──────────────────────────────
    $pro_services_hook(
        'pro_services_renewal_upcoming',
        'Pro Services — Renewal Coming Up',
        'Fired ahead of the next billing date (7 days before by default) on subscriptions that keep running. Use it to warn before a card is charged, or to ask a customer on a fixed term whether they want to continue.',
        ['renewal_date', 'renewal_amount', 'days_until_renewal']
    ),

    // ── Paused ─────────────────────────────────────────
    $pro_services_hook(
        'pro_services_subscription_paused',
        'Pro Services — Subscription Paused',
        'Triggered when billing is put on hold — by staff, or automatically after the number of days past due set in Settings. No further invoices are raised until it is resumed. {pause_reason} says why.',
        ['pause_reason', 'paused_by']
    ),

    // ── Resumed ────────────────────────────────────────
    $pro_services_hook(
        'pro_services_subscription_resumed',
        'Pro Services — Subscription Resumed',
        'Triggered when a paused subscription starts billing again. {next_billing_date} is when the next invoice will be raised.',
        ['resumed_by']
    ),

    // ── Cancelled ──────────────────────────────────────
    $pro_services_hook(
        'pro_services_subscription_cancelled',
        'Pro Services — Subscription Cancelled',
        'Triggered when a recurring service is stopped for good. {cancel_reason} carries why, {cancelled_by} who. Any invoice already raised stays open and payable — cancelling stops future billing, it does not write off what is owed.',
        ['cancel_reason', 'cancelled_by', 'outstanding_amount']
    ),

    // ── Term completed ─────────────────────────────────
    $pro_services_hook(
        'pro_services_subscription_completed',
        'Pro Services — Term Completed',
        'Triggered when a fixed-term subscription bills its last cycle, or an end date is reached. The natural moment to thank the customer and offer a renewal.',
        ['completed_cycles']
    ),

    // ── KPI target alert (internal) ────────────────────
    $pro_services_hook(
        'pro_services_kpi_alert',
        'Pro Services — KPI Off Target (Internal Alert)',
        'Triggered by cron when a KPI tracked on the Pro Services KPI console breaches its target for the current period. Meant for the owner or the account manager — map it to recipient type Staff / Role, or Custom pointed at {assigned_mobile}. It never goes to a customer.',
        ['kpi_name', 'kpi_metric', 'kpi_value', 'kpi_target', 'kpi_period', 'kpi_gap', 'kpi_direction']
    ),
];
