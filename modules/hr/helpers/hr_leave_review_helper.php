<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Leave review snapshot — everything an approver needs to decide, in one place.
 *
 * Approving a leave blind is the problem this solves: the employee already has
 * work in flight (Todo tasks, support tickets, sales meetings, trainings,
 * interviews) and the department may already be short-staffed on those exact
 * dates. hr_leave_review_data() gathers all of it for ONE leave request.
 *
 * Every external source is optional — the Todo / Pro Sales / Pro Tickets
 * modules may not be installed on a given tenant — so each block is guarded by
 * table_exists() and a try/catch. A broken source must never block an approval.
 */

/**
 * Every calendar date covered by a leave request (inclusive), capped so a
 * mistyped multi-year range can never blow up the popup.
 */
function hr_leave_review_dates($from, $to, $cap = 62)
{
    $out = [];
    $d   = strtotime($from);
    $end = strtotime($to);
    if (!$d || !$end || $end < $d) {
        return $from ? [$from] : [];
    }
    while ($d <= $end && count($out) < $cap) {
        $out[] = date('Y-m-d', $d);
        $d     = strtotime('+1 day', $d);
    }

    return $out;
}

/**
 * Workload + coverage snapshot for a single leave request.
 *
 * @param array $request row from Hr_model::get_leave_request()
 *
 * @return array
 */
function hr_leave_review_data(array $request)
{
    $CI = &get_instance();
    $p  = db_prefix();

    $staff_id = (int) $request['staff_id'];
    $from     = $request['from_date'];
    $to       = $request['to_date'];
    $from_dt  = $from . ' 00:00:00';
    $to_dt    = $to . ' 23:59:59';
    $today    = date('Y-m-d');
    $dates    = hr_leave_review_dates($from, $to);

    $data = [
        'dates'      => $dates,
        'todo'       => null,
        'tickets'    => null,
        'meetings'   => null,
        'trainings'  => [],
        'interviews' => [],
        'holidays'   => [],
        'shift'      => null,
        'balance'    => null,
        'coverage'   => null,
        'alerts'     => [],
    ];

    /* ------------------------------------------------------------- Todo */
    if ($CI->db->table_exists($p . 'todo_tasks')) {
        try {
            $rows = $CI->db->query(
                "SELECT t.id, t.title, t.priority, t.status, t.due_date, c.name AS category
                   FROM `{$p}todo_tasks` t
              LEFT JOIN `{$p}todo_categories` c ON c.id = t.category_id
                  WHERE t.staff_id = ? AND t.status < 2
               ORDER BY (t.due_date IS NULL) ASC, t.due_date ASC, t.priority DESC
                  LIMIT 200",
                [$staff_id]
            )->result_array();

            $todo = ['total' => 0, 'pending' => 0, 'in_progress' => 0, 'overdue' => 0, 'due_in_window' => 0, 'urgent' => 0, 'items' => []];
            foreach ($rows as $r) {
                $todo['total']++;
                (int) $r['status'] === 1 ? $todo['in_progress']++ : $todo['pending']++;
                $r['is_overdue']   = !empty($r['due_date']) && $r['due_date'] < $today;
                $r['is_in_window'] = !empty($r['due_date']) && $r['due_date'] >= $from && $r['due_date'] <= $to;
                if ($r['is_overdue']) {
                    $todo['overdue']++;
                }
                if ($r['is_in_window']) {
                    $todo['due_in_window']++;
                }
                if ((int) $r['priority'] >= 3) {
                    $todo['urgent']++;
                }
                if (count($todo['items']) < 12 && ($r['is_overdue'] || $r['is_in_window'] || (int) $r['priority'] >= 3)) {
                    $todo['items'][] = $r;
                }
            }
            // Nothing critical? Still show the next few so the load is visible.
            if (!count($todo['items'])) {
                $todo['items'] = array_slice($rows, 0, 6);
            }
            $data['todo'] = $todo;
        } catch (\Throwable $e) {
            log_message('error', 'hr leave review (todo) failed: ' . $e->getMessage());
        }
    }

    /* ---------------------------------------------------------- Tickets */
    if ($CI->db->table_exists($p . 'tickets')) {
        try {
            $has_meta = $CI->db->table_exists($p . 'pro_tickets_meta');
            $sql      = "SELECT t.ticketid, t.subject, t.status, t.priority, t.date, t.lastreply,
                               st.name AS status_name, pr.name AS priority_name"
                      . ($has_meta ? ", m.res_due, m.res_breached, m.frt_breached" : ", NULL AS res_due, 0 AS res_breached, 0 AS frt_breached") . "
                          FROM `{$p}tickets` t
                     LEFT JOIN `{$p}tickets_status` st ON st.ticketstatusid = t.status
                     LEFT JOIN `{$p}tickets_priorities` pr ON pr.priorityid = t.priority"
                      . ($has_meta ? " LEFT JOIN `{$p}pro_tickets_meta` m ON m.ticket_id = t.ticketid" : '') . "
                         WHERE t.assigned = ? AND t.status <> 5
                      ORDER BY t.priority DESC, t.lastreply DESC
                         LIMIT 200";
            $rows = $CI->db->query($sql, [$staff_id])->result_array();

            $tickets = ['total' => 0, 'breached' => 0, 'due_in_window' => 0, 'high' => 0, 'items' => []];
            foreach ($rows as $r) {
                $tickets['total']++;
                $r['status_label'] = $r['status_name'] ?: (function_exists('ticket_status_translate') ? ticket_status_translate($r['status']) : ('#' . $r['status']));
                $r['is_breached']  = !empty($r['res_breached']) || !empty($r['frt_breached']);
                $r['is_in_window'] = !empty($r['res_due']) && $r['res_due'] >= $from_dt && $r['res_due'] <= $to_dt;
                if ($r['is_breached']) {
                    $tickets['breached']++;
                }
                if ($r['is_in_window']) {
                    $tickets['due_in_window']++;
                }
                if ((int) $r['priority'] >= 3) {
                    $tickets['high']++;
                }
                if (count($tickets['items']) < 12 && ($r['is_breached'] || $r['is_in_window'] || (int) $r['priority'] >= 3)) {
                    $tickets['items'][] = $r;
                }
            }
            if (!count($tickets['items'])) {
                $tickets['items'] = array_slice($rows, 0, 6);
            }
            $data['tickets'] = $tickets;
        } catch (\Throwable $e) {
            log_message('error', 'hr leave review (tickets) failed: ' . $e->getMessage());
        }
    }

    /* --------------------------------------------------- Pro Sales meetings */
    if ($CI->db->table_exists($p . 'pro_sales_meetings')) {
        try {
            $clash = $CI->db->query(
                "SELECT id, reference, subject, contact_name, company, start_time, end_time, status, platform
                   FROM `{$p}pro_sales_meetings`
                  WHERE host_staff_id = ? AND status IN ('scheduled','rescheduled')
                    AND start_time BETWEEN ? AND ?
               ORDER BY start_time ASC
                  LIMIT 25",
                [$staff_id, $from_dt, $to_dt]
            )->result_array();

            $upcoming = (int) $CI->db->query(
                "SELECT COUNT(*) AS c FROM `{$p}pro_sales_meetings`
                  WHERE host_staff_id = ? AND status IN ('scheduled','rescheduled') AND start_time >= ?",
                [$staff_id, date('Y-m-d H:i:s')]
            )->row()->c;

            $data['meetings'] = ['in_window' => $clash, 'upcoming' => $upcoming];
        } catch (\Throwable $e) {
            log_message('error', 'hr leave review (pro_sales) failed: ' . $e->getMessage());
        }
    }

    /* ------------------------------------------- HR commitments in the window */
    try {
        if ($CI->db->table_exists($p . 'hr_trainings') && $CI->db->table_exists($p . 'hr_training_attendees')) {
            $data['trainings'] = $CI->db->query(
                "SELECT t.id, t.title, t.start_date, t.end_date, t.venue, t.status
                   FROM `{$p}hr_training_attendees` a
                   JOIN `{$p}hr_trainings` t ON t.id = a.training_id
                  WHERE a.staff_id = ? AND t.status <> 'cancelled'
                    AND COALESCE(t.start_date, t.end_date) <= ?
                    AND COALESCE(t.end_date, t.start_date) >= ?
               ORDER BY t.start_date ASC LIMIT 10",
                [$staff_id, $to, $from]
            )->result_array();
        }

        if ($CI->db->table_exists($p . 'hr_interviews')) {
            $data['interviews'] = $CI->db->query(
                "SELECT id, candidate_name, position, round_name, scheduled_at, status, interviewer_ids
                   FROM `{$p}hr_interviews`
                  WHERE status = 'scheduled' AND scheduled_at BETWEEN ? AND ?
                    AND CONCAT(',', REPLACE(interviewer_ids, ' ', ''), ',') LIKE ?
               ORDER BY scheduled_at ASC LIMIT 10",
                [$from_dt, $to_dt, '%,' . $staff_id . ',%']
            )->result_array();
        }

        if ($CI->db->table_exists($p . 'hr_holidays')) {
            $data['holidays'] = $CI->db->query(
                "SELECT name, holiday_date, is_optional FROM `{$p}hr_holidays`
                  WHERE is_active = 1 AND holiday_date BETWEEN ? AND ? ORDER BY holiday_date ASC",
                [$from, $to]
            )->result_array();
        }

        if ($CI->db->table_exists($p . 'hr_shift_assignments')) {
            $data['shift'] = $CI->db->query(
                "SELECT s.name, s.start_time, s.end_time, s.week_off_days
                   FROM `{$p}hr_shift_assignments` a
                   JOIN `{$p}hr_shifts` s ON s.id = a.shift_id
                  WHERE a.staff_id = ? AND a.effective_from <= ?
               ORDER BY a.effective_from DESC, a.id DESC LIMIT 1",
                [$staff_id, $to]
            )->row_array();
        }
    } catch (\Throwable $e) {
        log_message('error', 'hr leave review (hr commitments) failed: ' . $e->getMessage());
    }

    /* ----------------------------------------------------- Leave balance */
    try {
        $year    = (int) date('Y', strtotime($from));
        $type_id = (int) $request['leave_type_id'];
        $alloc   = (float) ($CI->hr_model->get_leave_allocations($year)[$staff_id][$type_id] ?? 0);
        $carried = (float) ($CI->hr_model->get_leave_carried($year)[$staff_id][$type_id] ?? 0);
        $used    = (float) ($CI->hr_model->get_leave_used($year)[$staff_id][$type_id] ?? 0);

        $data['balance'] = [
            'year'       => $year,
            'allocated'  => $alloc,
            'carried'    => $carried,
            'used'       => $used,
            'remaining'  => $alloc + $carried - $used,
            'requested'  => (float) $request['days'],
            // `used` already counts approved leave only, so a pending request
            // is measured against what is left today.
            'after'      => $alloc + $carried - $used - (float) $request['days'],
        ];
    } catch (\Throwable $e) {
        log_message('error', 'hr leave review (balance) failed: ' . $e->getMessage());
    }

    /* -------------------------------------------- Department coverage */
    try {
        $data['coverage'] = hr_leave_review_coverage($staff_id, $dates, (int) $request['id']);
    } catch (\Throwable $e) {
        log_message('error', 'hr leave review (coverage) failed: ' . $e->getMessage());
    }

    $data['alerts'] = hr_leave_review_alerts($data, $request);

    return $data;
}

/**
 * Who else from the same department is already off (or was marked absent) on
 * the requested dates, and what that does to the department's headcount.
 */
function hr_leave_review_coverage($staff_id, array $dates, $exclude_request_id = 0)
{
    $CI = &get_instance();
    $p  = db_prefix();

    if (!count($dates) || !$CI->db->table_exists($p . 'hr_employees')) {
        return null;
    }

    $me = $CI->db->query(
        "SELECT e.department_id, d.name AS department_name
           FROM `{$p}hr_employees` e
      LEFT JOIN `{$p}departments` d ON d.departmentid = e.department_id
          WHERE e.staff_id = ? LIMIT 1",
        [(int) $staff_id]
    )->row_array();

    $dept_id = (int) ($me['department_id'] ?? 0);
    if (!$dept_id) {
        return null;
    }

    $from = $dates[0];
    $to   = $dates[count($dates) - 1];

    $strength = (int) $CI->db->query(
        "SELECT COUNT(*) AS c
           FROM `{$p}hr_employees` e
           JOIN `{$p}staff` s ON s.staffid = e.staff_id
          WHERE e.department_id = ? AND s.active = 1",
        [$dept_id]
    )->row()->c;

    // Peers with an overlapping leave request (approved counts for certain,
    // pending is a heads-up that the clash is still avoidable).
    $peers = $CI->db->query(
        "SELECT r.id, r.staff_id, r.from_date, r.to_date, r.status, r.days, r.is_half_day,
                t.name AS type_name, t.code AS type_code, t.color AS type_color,
                s.firstname, s.lastname
           FROM `{$p}hr_leave_requests` r
           JOIN `{$p}hr_employees` e ON e.staff_id = r.staff_id
           JOIN `{$p}staff` s ON s.staffid = r.staff_id
           JOIN `{$p}hr_leave_types` t ON t.id = r.leave_type_id
          WHERE e.department_id = ? AND s.active = 1
            AND r.staff_id <> ? AND r.id <> ?
            AND r.status IN ('approved','pending')
            AND r.from_date <= ? AND r.to_date >= ?
       ORDER BY r.from_date ASC",
        [$dept_id, (int) $staff_id, (int) $exclude_request_id, $to, $from]
    )->result_array();

    // Attendance already marked absent/leave for peers (only meaningful for
    // dates that have passed or are today).
    $absent = [];
    if ($CI->db->table_exists($p . 'hr_attendance')) {
        $rows = $CI->db->query(
            "SELECT a.staff_id, a.att_date, a.status, s.firstname, s.lastname
               FROM `{$p}hr_attendance` a
               JOIN `{$p}hr_employees` e ON e.staff_id = a.staff_id
               JOIN `{$p}staff` s ON s.staffid = a.staff_id
              WHERE e.department_id = ? AND a.staff_id <> ?
                AND a.att_date BETWEEN ? AND ?
                AND a.status IN ('absent','leave','half_day')",
            [$dept_id, (int) $staff_id, $from, $to]
        )->result_array();
        foreach ($rows as $r) {
            $absent[$r['att_date']][] = $r;
        }
    }

    // Per-date roll-up: who is off, and what share of the department that is.
    $per_date  = [];
    $worst     = 0;
    $clash_ids = [];
    foreach ($dates as $d) {
        $off = [];
        foreach ($peers as $pr) {
            if ($pr['from_date'] <= $d && $pr['to_date'] >= $d) {
                $off[$pr['staff_id']] = [
                    'staff_id' => $pr['staff_id'],
                    'name'     => trim($pr['firstname'] . ' ' . $pr['lastname']),
                    'status'   => $pr['status'],
                    'type'     => $pr['type_name'],
                    'source'   => 'leave',
                ];
                $clash_ids[$pr['id']] = true;
            }
        }
        foreach ($absent[$d] ?? [] as $a) {
            if (!isset($off[$a['staff_id']])) {
                $off[$a['staff_id']] = [
                    'staff_id' => $a['staff_id'],
                    'name'     => trim($a['firstname'] . ' ' . $a['lastname']),
                    'status'   => $a['status'],
                    'type'     => ucfirst(str_replace('_', ' ', $a['status'])),
                    'source'   => 'attendance',
                ];
            }
        }
        $out_count = count($off) + 1; // + the applicant
        $per_date[$d] = [
            'off'     => array_values($off),
            'out'     => $out_count,
            'present' => max(0, $strength - $out_count),
            'percent' => $strength > 0 ? round($out_count * 100 / $strength) : 0,
        ];
        $worst = max($worst, count($off));
    }

    return [
        'department_id'   => $dept_id,
        'department_name' => $me['department_name'] ?: ('Department #' . $dept_id),
        'strength'        => $strength,
        'peers'           => $peers,
        'per_date'        => $per_date,
        'max_peers_off'   => $worst,
        'clashing'        => count($clash_ids),
    ];
}

/**
 * Turn the raw snapshot into the banner list at the top of the popup.
 * Order matters — the most decision-changing facts come first.
 */
function hr_leave_review_alerts(array $d, array $request)
{
    $alerts = [];
    $today  = date('Y-m-d');

    // -- coverage
    if (!empty($d['coverage'])) {
        $c = $d['coverage'];
        if ($c['max_peers_off'] > 0) {
            $bad_dates = [];
            foreach ($c['per_date'] as $date => $info) {
                if (count($info['off'])) {
                    $bad_dates[] = _d($date) . ' (' . $info['out'] . '/' . max(1, $c['strength']) . ' out)';
                }
            }
            $worst_pct = 0;
            foreach ($c['per_date'] as $info) {
                $worst_pct = max($worst_pct, $info['percent']);
            }
            $alerts[] = [
                'level' => $worst_pct >= 50 ? 'danger' : 'warning',
                'icon'  => 'fa-users',
                'text'  => $c['max_peers_off'] . ' other ' . ($c['max_peers_off'] > 1 ? 'employees' : 'employee')
                           . ' from <strong>' . html_escape($c['department_name']) . '</strong> already off on these dates — '
                           . implode(', ', array_slice($bad_dates, 0, 6))
                           . (count($bad_dates) > 6 ? ' …' : '')
                           . ($worst_pct >= 50 ? ' <strong>Half or more of the department would be away.</strong>' : ''),
            ];
        } elseif ($c['strength'] > 0) {
            $alerts[] = [
                'level' => 'success',
                'icon'  => 'fa-users',
                'text'  => 'No one else in <strong>' . html_escape($c['department_name']) . '</strong> is off on these dates ('
                           . $c['strength'] . ' in the department).',
            ];
        }
    }

    // -- balance
    if (!empty($d['balance'])) {
        $b = $d['balance'];
        if ($b['after'] < 0) {
            $alerts[] = [
                'level' => 'danger',
                'icon'  => 'fa-battery-empty',
                'text'  => 'Balance would go negative: ' . (float) $b['remaining'] . ' day(s) left, '
                           . (float) $b['requested'] . ' requested (' . (float) $b['after'] . ' after approval).',
            ];
        }
    }

    // -- SLA / ticket exposure
    if (!empty($d['tickets'])) {
        $t = $d['tickets'];
        if ($t['breached'] > 0 || $t['due_in_window'] > 0) {
            $bits = [];
            if ($t['due_in_window'] > 0) {
                $bits[] = $t['due_in_window'] . ' ticket(s) with an SLA due during the leave';
            }
            if ($t['breached'] > 0) {
                $bits[] = $t['breached'] . ' already SLA-breached';
            }
            $alerts[] = ['level' => 'warning', 'icon' => 'fa-life-ring', 'text' => ucfirst(implode(', ', $bits)) . '.'];
        }
    }

    // -- meetings booked inside the leave
    if (!empty($d['meetings']['in_window'])) {
        $alerts[] = [
            'level' => 'danger',
            'icon'  => 'fa-handshake-o',
            'text'  => count($d['meetings']['in_window']) . ' sales meeting(s) are booked during these dates — they need rescheduling or a new host.',
        ];
    }

    // -- todo pressure
    if (!empty($d['todo'])) {
        $t = $d['todo'];
        if ($t['overdue'] > 0 || $t['due_in_window'] > 0) {
            $bits = [];
            if ($t['overdue'] > 0) {
                $bits[] = $t['overdue'] . ' overdue task(s)';
            }
            if ($t['due_in_window'] > 0) {
                $bits[] = $t['due_in_window'] . ' task(s) due during the leave';
            }
            $alerts[] = ['level' => 'warning', 'icon' => 'fa-check-square-o', 'text' => ucfirst(implode(' and ', $bits)) . ' still open.'];
        }
    }

    // -- other commitments
    if (count($d['trainings'])) {
        $alerts[] = ['level' => 'warning', 'icon' => 'fa-graduation-cap', 'text' => count($d['trainings']) . ' training session(s) overlap this leave.'];
    }
    if (count($d['interviews'])) {
        $alerts[] = ['level' => 'warning', 'icon' => 'fa-user-plus', 'text' => count($d['interviews']) . ' interview(s) scheduled with this employee on the panel.'];
    }
    if (count($d['holidays'])) {
        $names = [];
        foreach ($d['holidays'] as $h) {
            $names[] = $h['name'] . ' (' . _d($h['holiday_date']) . ')';
        }
        $alerts[] = ['level' => 'info', 'icon' => 'fa-calendar-o', 'text' => 'Holiday(s) inside the range: ' . html_escape(implode(', ', $names)) . '.'];
    }

    // -- short notice
    if ($request['from_date'] < $today) {
        $alerts[] = ['level' => 'info', 'icon' => 'fa-history', 'text' => 'Back-dated request — the leave starts on ' . _d($request['from_date']) . '.'];
    } elseif (!empty($request['created_at'])) {
        $notice = (strtotime($request['from_date']) - strtotime(date('Y-m-d', strtotime($request['created_at'])))) / 86400;
        if ($notice <= 1) {
            $alerts[] = ['level' => 'info', 'icon' => 'fa-bolt', 'text' => 'Short notice — applied ' . ($notice < 1 ? 'the same day' : 'a day before') . ' the leave starts.'];
        }
    }

    return $alerts;
}
