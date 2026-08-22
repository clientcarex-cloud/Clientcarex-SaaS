<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * ──────────────────────────────────────────────────────
 *  PRO LEADS MODULE — Hook Definitions
 * ──────────────────────────────────────────────────────
 *
 * Each entry must contain:
 *   hook_key, label, module, description, variables
 *
 * Every entry also carries 'requires_module' => 'pro_leads', so the set only
 * appears on the Omni Messaging → Hooks panel once the Pro Leads module is
 * ACTIVATED — nothing fires them otherwise.
 *
 * Leads live in the shared core table, so these fire for a lead entering the
 * system through ANY channel (Pro Leads modal, core Leads, the module's public
 * API, web-to-lead forms, Meta Lead Ads, e-mail integration).
 *
 * The recipient resolver reads a hook's {mobile_number} / {email} for its
 * default ("patient") recipient — for a lead that is the lead's own phone and
 * e-mail. Staff-facing alerts (assigned, unrouted, junk) are meant to be mapped
 * with recipient type Staff / Role, or Custom pointed at {assigned_mobile} /
 * {assigned_email}.
 */

// Shared by every lead hook — resolved once per fire in
// pro_leads_omni_payload() (modules/pro_leads/helpers/pro_leads_helper.php).
$pro_leads_common_vars = [
    'lead_id',
    'lead_name',
    'lead_title',
    'lead_company',
    'mobile_number',
    'email',
    'lead_status',
    'lead_source',
    'lead_value',
    'lead_city',
    'lead_website',
    'lead_date',
    'lead_last_contact',
    'assigned_name',
    'assigned_email',
    'assigned_mobile',
    'lead_url',
];

$pro_leads_hook = function ($key, $label, $description, array $extra_vars = []) use ($pro_leads_common_vars) {
    return [
        'hook_key'        => $key,
        'label'           => $label,
        'module'          => 'pro_leads',
        'requires_module' => 'pro_leads',
        'description'     => $description,
        'variables'       => array_merge($pro_leads_common_vars, $extra_vars),
    ];
};

return [

    // ── Lead Created ───────────────────────────────────
    $pro_leads_hook(
        'pro_lead_created',
        'Pro Leads — Lead Created',
        'Triggered when a lead enters the system through any channel — Pro Leads, core Leads, the module API, a web-to-lead form, Meta Lead Ads or e-mail integration. The usual "thanks, we have your enquiry" auto-reply.',
        ['lead_description', 'created_via']
    ),

    // ── Assigned to Agent ──────────────────────────────
    $pro_leads_hook(
        'pro_lead_assigned',
        'Pro Leads — Assigned to Agent',
        'Triggered when a lead gets an agent — manual assignment or Roller Coaster auto-distribution. Map it to the agent (recipient type Staff/Role, or Custom → {assigned_mobile}) so they call the lead while it is hot.',
        ['assigned_by', 'assign_reason']
    ),

    // ── Status Changed ─────────────────────────────────
    $pro_leads_hook(
        'pro_lead_status_changed',
        'Pro Leads — Status Changed',
        'Triggered on every pipeline status change, including a Kanban drag. {lead_status} is the new status, {old_status} the previous one.',
        ['old_status', 'changed_by']
    ),

    // ── Lead Updated ───────────────────────────────────
    $pro_leads_hook(
        'pro_lead_updated',
        'Pro Leads — Lead Updated',
        'Triggered whenever a lead\'s details are saved. Fires on every edit, so keep this one for internal alerts rather than customer-facing messages.',
        ['changed_by']
    ),

    // ── Note / Call Log Added ──────────────────────────
    $pro_leads_hook(
        'pro_lead_note_added',
        'Pro Leads — Note / Call Log Added',
        'Triggered when a note or call log is saved on a lead from the Pro Leads modal (including the call log entered while creating or editing a lead).',
        ['note_excerpt', 'note_by']
    ),

    // ── Marked Contacted ───────────────────────────────
    $pro_leads_hook(
        'pro_lead_contacted',
        'Pro Leads — Marked Contacted',
        'Triggered when a call log marks the lead as contacted and stamps its last-contact date — a good moment for a follow-up message to the lead.',
        ['contacted_at', 'contacted_by']
    ),

    // ── Marked Lost ────────────────────────────────────
    $pro_leads_hook(
        'pro_lead_marked_lost',
        'Pro Leads — Marked Lost',
        'Triggered when a lead is marked as lost.',
        ['marked_by']
    ),

    // ── Marked Junk ────────────────────────────────────
    $pro_leads_hook(
        'pro_lead_marked_junk',
        'Pro Leads — Marked Junk',
        'Triggered when a lead is marked as junk by a staff member.',
        ['marked_by']
    ),

    // ── Converted to Customer ──────────────────────────
    $pro_leads_hook(
        'pro_lead_converted',
        'Pro Leads — Converted to Customer',
        'Triggered when a lead is converted into a customer. Use it to welcome the new customer or to alert the account team.',
        ['customer_id', 'converted_by']
    ),

    // ── Roller Coaster: Auto-Junked ────────────────────
    $pro_leads_hook(
        'pro_lead_auto_junk',
        'Pro Leads — Auto-Junked (Roller Coaster)',
        'Triggered when Roller Coaster junks an incoming lead because its phone number is too short to be real. Meant as an internal quality alert.',
        ['junk_reason', 'phone_digits']
    ),

    // ── Roller Coaster: Not Routed ─────────────────────
    $pro_leads_hook(
        'pro_lead_assign_failed',
        'Pro Leads — Not Routed (Roller Coaster)',
        'Triggered when Roller Coaster cannot hand a lead to any agent — outside working hours, no eligible agent, or every agent at their daily cap. Map it to a supervisor so the lead is not left sitting.',
        ['fail_reason']
    ),
];
