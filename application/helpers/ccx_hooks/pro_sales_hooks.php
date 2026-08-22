<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * ──────────────────────────────────────────────────────
 *  PRO SALES MODULE — Hook Definitions
 * ──────────────────────────────────────────────────────
 *
 * Each entry must contain:
 *   hook_key, label, module, description, variables
 *
 * Every entry carries 'requires_module' => 'pro_sales', so the set only appears
 * on the Omni Messaging → Hooks panel once the Pro Sales module is ACTIVATED —
 * nothing can fire them otherwise.
 *
 * Pro Sales schedules demo / sales meetings with LEADS and CUSTOMERS, so the
 * default ("patient") recipient of every hook is the person the meeting is with:
 * {mobile_number} / {email} are theirs. The sales executive hosting the meeting
 * is exposed separately as {host_mobile} / {host_email} — map staff-facing
 * hooks with recipient type Staff / Role, or Custom pointed at those tags.
 *
 * The three links are the point of the whole module:
 *   {meeting_link}     public meeting page — details, join button, add to calendar
 *   {reschedule_link}  self-service reschedule (live slot picker, no login)
 *   {cancel_link}      self-service cancellation
 * They are unguessable per-meeting tokens, safe to send over SMS / WhatsApp.
 */

// Shared by every Pro Sales hook — resolved once per fire in
// pro_sales_omni_payload() (modules/pro_sales/helpers/pro_sales_helper.php).
$pro_sales_common_vars = [
    'meeting_id',
    'meeting_ref',
    'meeting_subject',
    'meeting_type',
    'meeting_date',
    'meeting_time',
    'meeting_end_time',
    'meeting_datetime',
    'meeting_duration',
    'meeting_timezone',
    'meeting_platform',
    'meeting_location',
    'meeting_status',
    'meeting_agenda',
    'join_url',
    'meeting_link',
    'reschedule_link',
    'cancel_link',
    'contact_name',
    'mobile_number',
    'email',
    'company',
    'host_name',
    'host_email',
    'host_mobile',
    'admin_meeting_url',
];

$pro_sales_hook = function ($key, $label, $description, array $extra_vars = []) use ($pro_sales_common_vars) {
    return [
        'hook_key'        => $key,
        'label'           => $label,
        'module'          => 'pro_sales',
        'requires_module' => 'pro_sales',
        'description'     => $description,
        'variables'       => array_merge($pro_sales_common_vars, $extra_vars),
    ];
};

return [

    // ── Meeting Scheduled ──────────────────────────────
    $pro_sales_hook(
        'pro_sales_meeting_scheduled',
        'Pro Sales — Meeting Scheduled',
        'Triggered when a demo / sales meeting is booked — by a sales executive from the admin panel, from a lead or customer profile, or by the prospect themselves on a public booking page. The confirmation message: send {meeting_link} so they can join, reschedule or add it to their calendar.',
        ['booked_by', 'booked_via']
    ),

    // ── Meeting Rescheduled ────────────────────────────
    $pro_sales_hook(
        'pro_sales_meeting_rescheduled',
        'Pro Sales — Meeting Rescheduled',
        'Triggered whenever a meeting moves to a new slot — dragged on the admin calendar, edited by the host, or rescheduled by the prospect on their own reschedule link. {old_datetime} is where it was, {meeting_datetime} where it now is.',
        ['old_datetime', 'rescheduled_by', 'reschedule_reason']
    ),

    // ── Meeting Cancelled ──────────────────────────────
    $pro_sales_hook(
        'pro_sales_meeting_cancelled',
        'Pro Sales — Meeting Cancelled',
        'Triggered when a meeting is cancelled from either side. {cancelled_by} says who dropped it and {cancel_reason} why. The conferencing room (Google Meet / Zoom) is deleted at the same time.',
        ['cancelled_by', 'cancel_reason']
    ),

    // ── Reminder: day before ───────────────────────────
    $pro_sales_hook(
        'pro_sales_meeting_reminder_day',
        'Pro Sales — Reminder (First / Day Before)',
        'The first automatic reminder, fired by cron at the lead time set in Pro Sales → Settings (24 hours before by default). {reminder_window} reads "in 24 hours" so one template covers any configured offset.',
        ['reminder_window']
    ),

    // ── Reminder: shortly before ───────────────────────
    $pro_sales_hook(
        'pro_sales_meeting_reminder_hour',
        'Pro Sales — Reminder (Final / Starting Soon)',
        'The final automatic reminder before the meeting starts (60 minutes before by default). This is the one that should carry {join_url} — it is the message people actually click to join.',
        ['reminder_window']
    ),

    // ── Meeting Completed ──────────────────────────────
    $pro_sales_hook(
        'pro_sales_meeting_completed',
        'Pro Sales — Meeting Completed',
        'Triggered when the host closes out a meeting with an outcome. Good for a thank-you plus next step; {outcome} and {outcome_notes} carry what was decided.',
        ['outcome', 'outcome_notes', 'completed_by', 'deal_value']
    ),

    // ── No Show ────────────────────────────────────────
    $pro_sales_hook(
        'pro_sales_meeting_no_show',
        'Pro Sales — Marked No Show',
        'Triggered when the host marks that the prospect did not turn up. Usually mapped to a polite "sorry we missed you, rebook here" message carrying {reschedule_link}.',
        ['marked_by']
    ),

    // ── Assigned to Host ───────────────────────────────
    $pro_sales_hook(
        'pro_sales_meeting_assigned',
        'Pro Sales — Assigned to Executive',
        'Triggered when a meeting lands on a sales executive — booked for them, reassigned, or handed out by round-robin on a public booking page. Map it to the executive (recipient type Staff/Role, or Custom → {host_mobile}) so they see it before it starts.',
        ['assigned_by']
    ),

    // ── Self-booked on a public page ───────────────────
    $pro_sales_hook(
        'pro_sales_booking_received',
        'Pro Sales — Booked on Public Page (Internal Alert)',
        'Triggered when someone books a slot themselves on a public booking page. Meant as the internal alert to the sales desk — the prospect already gets the confirmation from "Meeting Scheduled".',
        ['booking_page', 'booking_answers', 'lead_created']
    ),
];
