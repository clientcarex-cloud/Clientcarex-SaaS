<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * ═══════════════════════════════════════════════════════════════
 *  HR → Omni Messaging (CCX hooks) bridge
 * ═══════════════════════════════════════════════════════════════
 *
 * The hook DEFINITIONS live in application/helpers/ccx_hooks/hr_hooks.php.
 * This file is the other half: it builds the payload every HR hook publishes
 * and exposes the wrappers the controllers/model call.
 *
 * Two rules everything here follows:
 *
 *   1. MESSAGING IS NEVER LOAD-BEARING. Every fire is wrapped — a missing
 *      Omni Messaging module, an unreachable gateway or a malformed row can
 *      never break the HR action that triggered it. Failures are logged by the
 *      hook engine itself (Omni Messaging → Hooks → trigger log).
 *   2. EVERY EMPLOYEE HOOK SPEAKS THE SAME TAGS. hr_hook_employee_payload()
 *      is the single source of {employee_name}, {mobile_number}, {email} and
 *      friends, so a template written for one hook reads correctly on another.
 *      {mobile_number} / {email} are what the default recipient type resolves,
 *      which is how an employee gets reached with no extra configuration.
 *
 * Daily reminders (birthday, work anniversary, probation, document expiry)
 * ride on hr_hook_daily_cron(), guarded to one dispatch per calendar day.
 */

/**
 * Contact + identity tags shared by every employee-scoped HR hook.
 *
 * Returns an empty array when the staff member does not exist, which makes
 * every caller a silent no-op — an orphaned staff_id must not send anything.
 *
 * @param  int $staff_id
 * @return array
 */
function hr_hook_employee_payload($staff_id)
{
    static $cache = [];

    $staff_id = (int) $staff_id;
    if ($staff_id <= 0) {
        return [];
    }
    if (isset($cache[$staff_id])) {
        return $cache[$staff_id];
    }

    $CI  = &get_instance();
    $row = $CI->db
        ->select('s.staffid, s.firstname, s.lastname, s.email, s.phonenumber,
            e.employee_code, e.date_of_joining, e.employment_type, e.work_location,
            dep.name AS department, des.name AS designation,
            m.firstname AS mgr_first, m.lastname AS mgr_last')
        ->from(db_prefix() . 'staff s')
        ->join(db_prefix() . 'hr_employees e', 'e.staff_id = s.staffid', 'left')
        ->join(db_prefix() . 'departments dep', 'dep.departmentid = e.department_id', 'left')
        ->join(db_prefix() . 'hr_designations des', 'des.id = e.designation_id', 'left')
        ->join(db_prefix() . 'staff m', 'm.staffid = e.reporting_to', 'left')
        ->where('s.staffid', $staff_id)
        ->get()->row_array();

    if (!$row) {
        return $cache[$staff_id] = [];
    }

    return $cache[$staff_id] = [
        'employee_name'   => trim($row['firstname'] . ' ' . $row['lastname']),
        'employee_code'   => (string) ($row['employee_code'] ?: ''),
        'mobile_number'   => (string) ($row['phonenumber'] ?: ''),
        'email'           => (string) ($row['email'] ?: ''),
        'department'      => (string) ($row['department'] ?: ''),
        'designation'     => (string) ($row['designation'] ?: ''),
        'date_of_joining' => $row['date_of_joining'] ? _d($row['date_of_joining']) : '',
        'employment_type' => (string) ($row['employment_type'] ?: ''),
        'work_location'   => (string) ($row['work_location'] ?: ''),
        'reporting_to'    => trim((string) $row['mgr_first'] . ' ' . (string) $row['mgr_last']),
    ];
}

/**
 * Fire an employee-scoped HR hook.
 *
 * @param  string $hook_key
 * @param  int    $staff_id  employee the event is about
 * @param  array  $extra     hook-specific tags
 * @return void
 */
function hr_fire_employee_hook($hook_key, $staff_id, $extra = [])
{
    $base = hr_hook_employee_payload($staff_id);
    if (empty($base)) {
        return;
    }

    hr_fire_hook($hook_key, array_merge($base, $extra));
}

/**
 * Fire an HR hook with an already-complete payload. Used by the hooks that are
 * not about an employee (a job candidate, say).
 *
 * Scalars only reach the template engine, so array/object values are dropped
 * here rather than tripping str_replace() downstream.
 *
 * @param  string $hook_key
 * @param  array  $payload
 * @return void
 */
function hr_fire_hook($hook_key, $payload = [])
{
    if (!function_exists('ccx_fire_hook')) {
        return; // Omni Messaging / hooks engine not present on this install
    }

    $clean = [];
    foreach ($payload as $key => $value) {
        if (is_bool($value)) {
            $value = $value ? 'Yes' : 'No';
        }
        if ($value === null) {
            $value = '';
        }
        if (!is_string($value) && !is_numeric($value)) {
            continue;
        }
        $clean[$key] = (string) $value;
    }

    try {
        ccx_fire_hook($hook_key, $clean);
    } catch (\Throwable $e) {
        // A messaging problem must never take an HR save down with it.
        log_activity('HR Hook Error (' . $hook_key . '): ' . $e->getMessage());
    }
}

/**
 * Money formatted for a message body — no HTML, unlike app_format_money().
 *
 * @param  mixed $amount
 * @return string
 */
function hr_hook_money($amount)
{
    $symbol = '';
    if (function_exists('get_base_currency')) {
        $currency = get_base_currency();
        $symbol   = $currency && !empty($currency->symbol) ? $currency->symbol : '';
    }

    return trim($symbol . ' ' . number_format((float) $amount, 2));
}

/**
 * "March 2026" — the salary period as an employee would say it.
 *
 * @param  int $month 1-12
 * @param  int $year
 * @return string
 */
function hr_hook_pay_period($month, $year)
{
    $ts = mktime(0, 0, 0, (int) $month, 1, (int) $year);

    return $ts ? date('F Y', $ts) : trim($month . ' ' . $year);
}

/**
 * Leave tags shared by the applied / approved / rejected hooks.
 *
 * @param  array|null $request row from Hr_model::get_leave_request()
 * @return array
 */
function hr_hook_leave_tags($request)
{
    if (empty($request)) {
        return [];
    }

    return [
        'leave_type'    => (string) ($request['type_name'] ?? ''),
        'from_date'     => !empty($request['from_date']) ? _d($request['from_date']) : '',
        'to_date'       => !empty($request['to_date']) ? _d($request['to_date']) : '',
        'days'          => (string) ($request['days'] ?? ''),
        'half_day'      => !empty($request['is_half_day']) ? 'Yes' : 'No',
        'reason'        => (string) ($request['reason'] ?? ''),
        'action_note'   => (string) ($request['action_note'] ?? ''),
        'approver_name' => !empty($request['approved_by']) ? get_staff_full_name($request['approved_by']) : '',
    ];
}

/**
 * Staff ids an HR notice is addressed to. Mirrors the audience rules the
 * self-service notice board applies (all / roles / employees).
 *
 * @param  array $notice
 * @return int[]
 */
function hr_hook_notice_audience($notice)
{
    $CI = &get_instance();

    $CI->db->select('s.staffid')
        ->from(db_prefix() . 'staff s')
        ->join(db_prefix() . 'hr_employees e', 'e.staff_id = s.staffid', 'left')
        ->where('s.active', 1)
        ->where('s.is_not_staff', 0)
        ->group_start()
            ->where('e.status IS NULL', null, false)
            ->or_where('e.status !=', 'exited')
        ->group_end();

    if ($notice['audience_type'] === 'roles') {
        $ids = array_filter(array_map('intval', explode(',', (string) $notice['role_ids'])));
        if (empty($ids)) {
            return [];
        }
        $CI->db->where_in('s.role', $ids);
    } elseif ($notice['audience_type'] === 'employees') {
        $ids = array_filter(array_map('intval', explode(',', (string) $notice['staff_ids'])));
        if (empty($ids)) {
            return [];
        }
        $CI->db->where_in('s.staffid', $ids);
    }

    return array_map('intval', array_column($CI->db->get()->result_array(), 'staffid'));
}

// ══════════════════════════════════════════════════════════
//  DAILY REMINDERS
// ══════════════════════════════════════════════════════════

/**
 * How many days ahead the probation-ending hook fires. 0 disables it.
 */
function hr_hook_probation_alert_days()
{
    $days = get_option('hr_probation_alert_days');

    return ($days === '' || $days === null) ? 7 : (int) $days;
}

/**
 * How many days ahead the document-expiry hook fires. 0 disables it.
 * Shares the lead time the HR dashboard's "expiring documents" card uses.
 */
function hr_hook_document_alert_days()
{
    $days = get_option('hr_doc_expiry_alert_days');

    return ($days === '' || $days === null) ? 30 : (int) $days;
}

/**
 * Date-driven HR hooks, dispatched once per calendar day from the cron.
 *
 * The day is CLAIMED before any hook fires (same pattern as the birthday
 * notifier) so two overlapping cron runs cannot double-message anyone. A hook
 * with no active mapping still costs one cheap registry lookup, so there is no
 * point gating this on configuration.
 *
 * @return void
 */
function hr_hook_daily_cron()
{
    if (!function_exists('ccx_fire_hook')) {
        return;
    }

    $today = date('Y-m-d');
    if (get_option('hr_hooks_reminder_date') === $today) {
        return;
    }
    update_option('hr_hooks_reminder_date', $today);

    hr_hook_dispatch_birthdays();
    hr_hook_dispatch_anniversaries();
    hr_hook_dispatch_probation_endings();
    hr_hook_dispatch_document_expiries();
}

/**
 * All active, non-exited employees whose {$column} falls on today's month+day.
 *
 * @param  string $column hr_employees column holding the date
 * @return array
 */
function hr_hook_employees_on_date_anniversary($column)
{
    $CI = &get_instance();

    return $CI->db->query('SELECT s.staffid, e.' . $column . ' AS the_date
        FROM ' . db_prefix() . 'hr_employees e
        JOIN ' . db_prefix() . 'staff s ON s.staffid = e.staff_id AND s.active = 1 AND s.is_not_staff = 0
        WHERE e.' . $column . ' IS NOT NULL
          AND MONTH(e.' . $column . ') = MONTH(CURDATE())
          AND DAY(e.' . $column . ') = DAY(CURDATE())
          AND (e.status IS NULL OR e.status != "exited")')->result_array();
}

function hr_hook_dispatch_birthdays()
{
    foreach (hr_hook_employees_on_date_anniversary('date_of_birth') as $row) {
        hr_fire_employee_hook('hr_employee_birthday', $row['staffid'], [
            'date_of_birth' => _d($row['the_date']),
        ]);
    }
}

function hr_hook_dispatch_anniversaries()
{
    foreach (hr_hook_employees_on_date_anniversary('date_of_joining') as $row) {
        $years = (int) date('Y') - (int) date('Y', strtotime($row['the_date']));
        if ($years < 1) {
            continue; // the joining day itself is not an anniversary
        }
        hr_fire_employee_hook('hr_work_anniversary', $row['staffid'], [
            'years_completed'  => $years,
            'anniversary_date' => _d(date('Y-m-d')),
        ]);
    }
}

function hr_hook_dispatch_probation_endings()
{
    $days = hr_hook_probation_alert_days();
    if ($days <= 0) {
        return;
    }

    $CI   = &get_instance();
    $rows = $CI->db->query('SELECT s.staffid, e.probation_end
        FROM ' . db_prefix() . 'hr_employees e
        JOIN ' . db_prefix() . 'staff s ON s.staffid = e.staff_id AND s.active = 1 AND s.is_not_staff = 0
        WHERE e.probation_end = DATE_ADD(CURDATE(), INTERVAL ? DAY)
          AND (e.status IS NULL OR e.status != "exited")', [$days])->result_array();

    foreach ($rows as $row) {
        hr_fire_employee_hook('hr_probation_ending', $row['staffid'], [
            'probation_end' => _d($row['probation_end']),
            'days_left'     => $days,
        ]);
    }
}

function hr_hook_dispatch_document_expiries()
{
    $days = hr_hook_document_alert_days();
    if ($days <= 0) {
        return;
    }

    $CI   = &get_instance();
    $rows = $CI->db->query('SELECT d.staff_id, d.title, d.doc_type, d.expiry_date
        FROM ' . db_prefix() . 'hr_documents d
        JOIN ' . db_prefix() . 'staff s ON s.staffid = d.staff_id AND s.active = 1
        WHERE d.expiry_date = DATE_ADD(CURDATE(), INTERVAL ? DAY)', [$days])->result_array();

    foreach ($rows as $row) {
        hr_fire_employee_hook('hr_document_expiring', $row['staff_id'], [
            'document_title' => (string) $row['title'],
            'document_type'  => (string) ($row['doc_type'] ?: ''),
            'expiry_date'    => _d($row['expiry_date']),
            'days_left'      => $days,
        ]);
    }
}
