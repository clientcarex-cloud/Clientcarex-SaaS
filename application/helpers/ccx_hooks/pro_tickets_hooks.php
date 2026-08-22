<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * ──────────────────────────────────────────────────────
 *  PRO TICKETS MODULE — Hook Definitions
 * ──────────────────────────────────────────────────────
 *
 * Each entry must contain:
 *   hook_key, label, module, description, variables
 *
 * Every entry also carries 'requires_module' => 'pro_tickets', so the whole
 * set only appears on the Omni Messaging → Hooks panel once the Pro Tickets
 * module is ACTIVATED. Without the module nothing can fire them, so offering
 * the mappings would only create dead configuration.
 *
 * The recipient resolver treats a hook's {mobile_number} / {email} as the
 * "patient" contact — for a ticket that is the requester (the customer
 * contact). Staff-facing alerts (SLA breach, customer replied, …) are meant to
 * be mapped with recipient type Staff / Role, or Custom pointed at
 * {assigned_mobile} / {assigned_email}.
 */

// Shared by every ticket hook — resolved once per fire in
// pro_tickets_omni_payload() (modules/pro_tickets/helpers/pro_tickets_helper.php).
$pro_tickets_common_vars = [
    'ticket_id',
    'ticket_number',
    'ticket_subject',
    'ticket_status',
    'ticket_priority',
    'ticket_department',
    'ticket_date',
    'customer_name',
    'customer_company',
    'mobile_number',
    'email',
    'assigned_name',
    'assigned_email',
    'assigned_mobile',
    'ticket_url',
    'admin_ticket_url',
];

$pro_tickets_hook = function ($key, $label, $description, array $extra_vars = []) use ($pro_tickets_common_vars) {
    return [
        'hook_key'        => $key,
        'label'           => $label,
        'module'          => 'pro_tickets',
        'requires_module' => 'pro_tickets',
        'description'     => $description,
        'variables'       => array_merge($pro_tickets_common_vars, $extra_vars),
    ];
};

return [

    // ── Ticket Opened ──────────────────────────────────
    $pro_tickets_hook(
        'pro_ticket_created',
        'Pro Tickets — Ticket Opened',
        'Triggered when a support ticket is opened through any channel (admin area, customer portal, Smart Ticket or e-mail piping). Use it to acknowledge the requester.',
        ['ticket_message', 'sla_first_response_due', 'sla_resolution_due']
    ),

    // ── Staff Replied ──────────────────────────────────
    $pro_tickets_hook(
        'pro_ticket_staff_reply',
        'Pro Tickets — Staff Replied',
        'Triggered when a staff member posts a reply on a ticket — the usual "we have responded, please check" alert to the requester.',
        ['reply_by', 'reply_excerpt']
    ),

    // ── Customer Replied ───────────────────────────────
    $pro_tickets_hook(
        'pro_ticket_customer_reply',
        'Pro Tickets — Customer Replied',
        'Triggered when the customer posts a reply. Map it to the assignee (recipient type Staff/Role, or Custom → {assigned_mobile}) so the agent knows the ball is back in their court.',
        ['reply_by', 'reply_excerpt']
    ),

    // ── Assigned to Agent ──────────────────────────────
    $pro_tickets_hook(
        'pro_ticket_assigned',
        'Pro Tickets — Assigned to Agent',
        'Triggered when a ticket gets an assignee — auto-assignment (round-robin / least-busy), a manual change on the ticket screen, or a department transfer.',
        ['assigned_by', 'assign_reason']
    ),

    // ── Status Changed ─────────────────────────────────
    $pro_tickets_hook(
        'pro_ticket_status_changed',
        'Pro Tickets — Status Changed',
        'Triggered on every ticket status change (Open, In Progress, Answered, On Hold, Closed …). {ticket_status} holds the new status.',
        ['changed_by']
    ),

    // ── Ticket Closed ──────────────────────────────────
    $pro_tickets_hook(
        'pro_ticket_closed',
        'Pro Tickets — Ticket Closed',
        'Triggered when a ticket is closed (status 5), whether by staff or by the customer. Good place for a closing note plus the feedback request.',
        ['closed_by', 'resolution_time', 'sla_met']
    ),

    // ── Ticket Reopened ────────────────────────────────
    $pro_tickets_hook(
        'pro_ticket_reopened',
        'Pro Tickets — Ticket Reopened',
        'Triggered when a previously closed ticket is reopened.',
        ['reopened_count', 'changed_by']
    ),

    // ── SLA Warning ────────────────────────────────────
    $pro_tickets_hook(
        'pro_ticket_sla_warning',
        'Pro Tickets — SLA At Risk',
        'Triggered by the automation cron when a ticket consumes the configured percentage of its SLA window without being answered/resolved. Meant for the agent and the watchers.',
        ['sla_stage', 'sla_due', 'time_left']
    ),

    // ── SLA Breached ───────────────────────────────────
    $pro_tickets_hook(
        'pro_ticket_sla_breached',
        'Pro Tickets — SLA Breached',
        'Triggered by the automation cron the moment a first-response or resolution deadline passes unmet. {sla_type} names which one.',
        ['sla_type', 'sla_due', 'overdue_by']
    ),

    // ── Escalated ──────────────────────────────────────
    $pro_tickets_hook(
        'pro_ticket_escalated',
        'Pro Tickets — Escalated',
        'Triggered when a breached ticket is escalated to the SLA policy\'s escalation contact. Map it to Custom → {escalate_to_mobile} / {escalate_to_email} to reach that contact directly.',
        ['sla_type', 'escalate_to_name', 'escalate_to_email', 'escalate_to_mobile']
    ),

    // ── Auto-Closed ────────────────────────────────────
    $pro_tickets_hook(
        'pro_ticket_auto_closed',
        'Pro Tickets — Auto-Closed (Idle)',
        'Triggered when the automation closes an answered ticket that stayed idle for the configured number of days.',
        ['idle_days']
    ),

    // ── Feedback Received ──────────────────────────────
    $pro_tickets_hook(
        'pro_ticket_feedback_received',
        'Pro Tickets — Feedback Received',
        'Triggered when the customer submits a satisfaction rating on a closed ticket. Useful as a low-rating alert to the agent or a supervisor role.',
        ['rating', 'rating_label', 'feedback_comment', 'agent_name']
    ),
];
