<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * ──────────────────────────────────────────────────────
 *  HR MODULE — Hook Definitions
 * ──────────────────────────────────────────────────────
 *
 * Each entry must contain:
 *   hook_key, label, module, description, variables
 *
 * Every entry also carries 'requires_module' => 'hr', so the whole set stays
 * out of the Hooks panel on installations where HR is not activated — nothing
 * could ever fire them there.
 *
 * RECIPIENTS. Employee-facing hooks publish the employee's own contact details
 * as {mobile_number} and {email}, which is exactly what the default "patient"
 * recipient type reads — so the employee is reachable without configuring
 * anything. Hooks whose natural audience is a manager (leave applied, field
 * punch submitted, memo acknowledged) publish the same tags for context but are
 * meant to be mapped to a staff member or a role on the Hooks panel.
 *
 * Firing happens through hr_fire_hook() / hr_fire_employee_hook() in
 * modules/hr/helpers/hr_hooks_helper.php — never call ccx_fire_hook() with a
 * raw HR payload, or the base employee tags go missing.
 */

/**
 * Contact + identity tags published for every employee-scoped HR hook.
 * Built by hr_hook_employee_payload().
 */
$base = [
    'employee_name',
    'employee_code',
    'mobile_number',
    'email',
    'department',
    'designation',
    'date_of_joining',
];

/**
 * Compose one hook definition.
 *
 * @param  string $key
 * @param  string $label
 * @param  string $description
 * @param  array  $variables
 * @return array
 */
$hook = function ($key, $label, $description, array $variables) {
    return [
        'hook_key'        => $key,
        'label'           => $label,
        'module'          => 'hr',
        'requires_module' => 'hr',
        'description'     => $description,
        'variables'       => $variables,
    ];
};

return [

    // ══════════════════════════════════════════════════════
    //  EMPLOYEE LIFECYCLE
    // ══════════════════════════════════════════════════════

    $hook(
        'hr_employee_added',
        'HR — Employee Onboarded',
        'Triggered when an HR profile is created for a staff member — on "Sync from Staff", on employee import, and the first time an employee record is opened. Use it for the welcome message.',
        array_merge($base, ['employment_type', 'work_location', 'reporting_to'])
    ),

    $hook(
        'hr_employee_birthday',
        'HR — Employee Birthday',
        'Triggered once on the morning of an employee\'s birthday by the HR daily cron. Fires independently of the in-app "wish employees" toggle.',
        array_merge($base, ['date_of_birth'])
    ),

    $hook(
        'hr_work_anniversary',
        'HR — Work Anniversary',
        'Triggered once by the HR daily cron on the anniversary of an employee\'s date of joining (from the first completed year onwards).',
        array_merge($base, ['years_completed', 'anniversary_date'])
    ),

    $hook(
        'hr_probation_ending',
        'HR — Probation Ending Soon',
        'Triggered by the HR daily cron exactly N days before an employee\'s probation end date, N being the reminder lead time in HR Settings (default 7). Fires once per employee. Useful as a confirmation-review nudge to the reporting manager.',
        array_merge($base, ['probation_end', 'days_left'])
    ),

    // ══════════════════════════════════════════════════════
    //  DOCUMENTS
    // ══════════════════════════════════════════════════════

    $hook(
        'hr_document_verified',
        'HR — Document Verified',
        'Triggered when HR verifies a document the employee submitted through the self-service portal.',
        array_merge($base, ['document_title', 'document_type', 'reviewer_name', 'review_note'])
    ),

    $hook(
        'hr_document_rejected',
        'HR — Document Rejected',
        'Triggered when HR rejects a submitted document. The rejection reason is published as {review_note} so the employee knows what to re-upload.',
        array_merge($base, ['document_title', 'document_type', 'reviewer_name', 'review_note'])
    ),

    $hook(
        'hr_document_expiring',
        'HR — Document Expiring',
        'Triggered by the HR daily cron exactly N days before a document\'s expiry date, N being the reminder lead time in HR Settings (default 15). Fires once per document, not every day of the window.',
        array_merge($base, ['document_title', 'document_type', 'expiry_date', 'days_left'])
    ),

    // ══════════════════════════════════════════════════════
    //  LEAVE
    // ══════════════════════════════════════════════════════

    $hook(
        'hr_leave_applied',
        'HR — Leave Applied',
        'Triggered when a leave request is filed, from the employee portal or by HR on someone\'s behalf. Map this to the approving role so the request does not wait on an unread in-app notification.',
        array_merge($base, ['leave_type', 'from_date', 'to_date', 'days', 'half_day', 'reason'])
    ),

    $hook(
        'hr_leave_approved',
        'HR — Leave Approved',
        'Triggered when a leave request is fully approved — i.e. the final level of the approval chain clears it. Intermediate level approvals do not fire.',
        array_merge($base, ['leave_type', 'from_date', 'to_date', 'days', 'half_day', 'approver_name', 'action_note'])
    ),

    $hook(
        'hr_leave_rejected',
        'HR — Leave Rejected',
        'Triggered when a leave request is rejected at any level of the approval chain.',
        array_merge($base, ['leave_type', 'from_date', 'to_date', 'days', 'half_day', 'approver_name', 'action_note'])
    ),

    // ══════════════════════════════════════════════════════
    //  ATTENDANCE & SHIFTS
    // ══════════════════════════════════════════════════════

    $hook(
        'hr_attendance_absent',
        'HR — Marked Absent',
        'Triggered when an employee\'s attendance for a day is saved as Absent from the daily attendance sheet. Only a change TO absent fires — re-saving a day that was already absent does not.',
        array_merge($base, ['attendance_date', 'attendance_note'])
    ),

    $hook(
        'hr_shift_assigned',
        'HR — Shift Assigned',
        'Triggered for each employee included in a shift assignment.',
        array_merge($base, ['shift_name', 'shift_start', 'shift_end', 'effective_from'])
    ),

    $hook(
        'hr_field_punch_submitted',
        'HR — Field Punch Submitted',
        'Triggered when an employee records a field (out-of-office) punch that needs approval. Map it to the reviewing staff or role.',
        array_merge($base, ['punch_type', 'punch_date', 'punch_time', 'punch_purpose', 'punch_site', 'punch_address', 'punch_distance', 'geofence_status'])
    ),

    $hook(
        'hr_field_punch_approved',
        'HR — Field Punch Approved',
        'Triggered when a pending field punch is approved, individually or in a bulk action.',
        array_merge($base, ['punch_type', 'punch_date', 'punch_time', 'reviewer_name', 'review_note'])
    ),

    $hook(
        'hr_field_punch_rejected',
        'HR — Field Punch Rejected',
        'Triggered when a pending field punch is rejected, individually or in a bulk action.',
        array_merge($base, ['punch_type', 'punch_date', 'punch_time', 'reviewer_name', 'review_note'])
    ),

    // ══════════════════════════════════════════════════════
    //  PAYROLL
    // ══════════════════════════════════════════════════════

    $hook(
        'hr_payslip_published',
        'HR — Payslip Published',
        'Triggered for every employee in a payroll run at the moment the run is finalized — that is when payslips become visible in the self-service portal.',
        array_merge($base, ['pay_period', 'pay_month', 'pay_year', 'gross_pay', 'total_deductions', 'net_pay', 'payable_days', 'lop_days'])
    ),

    $hook(
        'hr_salary_paid',
        'HR — Salary Paid',
        'Triggered when a payslip is marked as paid.',
        array_merge($base, ['pay_period', 'pay_month', 'pay_year', 'net_pay', 'payment_mode', 'paid_date'])
    ),

    // ══════════════════════════════════════════════════════
    //  DISCIPLINE
    // ══════════════════════════════════════════════════════

    $hook(
        'hr_memo_issued',
        'HR — Memo Issued',
        'Triggered when a new disciplinary memo is issued to an employee. Only new memos fire; editing an existing one does not.',
        array_merge($base, ['memo_type', 'memo_severity', 'memo_subject', 'incident_date', 'action_taken', 'penalty_amount', 'issuer_name'])
    ),

    $hook(
        'hr_memo_acknowledged',
        'HR — Memo Acknowledged',
        'Triggered when the employee signs the acknowledgement receipt for a memo — whether they agreed or disputed it ({memo_status}). Map it to the issuing manager.',
        array_merge($base, ['memo_subject', 'memo_status', 'ack_signature', 'ack_note', 'issuer_name'])
    ),

    // ══════════════════════════════════════════════════════
    //  GROWTH
    // ══════════════════════════════════════════════════════

    $hook(
        'hr_training_scheduled',
        'HR — Training Scheduled',
        'Triggered for each employee newly added to a training\'s attendee list. Employees already on the list are not messaged again.',
        array_merge($base, ['training_title', 'trainer', 'training_category', 'training_start', 'training_end', 'training_venue'])
    ),

    $hook(
        'hr_appraisal_shared',
        'HR — Appraisal Shared',
        'Triggered when an appraisal is saved with the status Completed — the point at which the employee can see it in the self-service portal.',
        array_merge($base, ['period_from', 'period_to', 'overall_rating', 'reviewer_name', 'strengths', 'improvements', 'goals'])
    ),

    // ══════════════════════════════════════════════════════
    //  RECRUITMENT
    // ══════════════════════════════════════════════════════

    $hook(
        'hr_interview_scheduled',
        'HR — Interview Scheduled',
        'Triggered when an interview is scheduled or rescheduled. The recipient is the CANDIDATE (not a staff member), so {mobile_number} and {email} carry the candidate\'s details.',
        [
            'candidate_name',
            'mobile_number',
            'email',
            'position',
            'round_no',
            'round_name',
            'interview_datetime',
            'interview_mode',
            'duration_minutes',
            'meeting_link',
            'meeting_id',
            'meeting_password',
            'interviewers',
        ]
    ),

    // ══════════════════════════════════════════════════════
    //  EXIT
    // ══════════════════════════════════════════════════════

    $hook(
        'hr_exit_initiated',
        'HR — Exit Initiated',
        'Triggered when an exit record is created (resignation, termination, retirement …) while it is still pending or in clearance.',
        array_merge($base, ['exit_type', 'notice_date', 'last_working_day', 'exit_reason'])
    ),

    $hook(
        'hr_exit_settled',
        'HR — Full & Final Settled',
        'Triggered when an exit record moves to the Settled status — full and final settlement done, staff account deactivated.',
        array_merge($base, ['exit_type', 'last_working_day', 'settlement_amount', 'settlement_note'])
    ),

    // ══════════════════════════════════════════════════════
    //  COMMUNICATION
    // ══════════════════════════════════════════════════════

    $hook(
        'hr_notice_published',
        'HR — Notice Published',
        'Triggered for every employee in a notice\'s audience the moment an active HR notice is created. Editing an existing notice does NOT re-broadcast it.',
        array_merge($base, ['notice_title', 'notice_message', 'notice_priority', 'notice_start', 'notice_end'])
    ),
];
