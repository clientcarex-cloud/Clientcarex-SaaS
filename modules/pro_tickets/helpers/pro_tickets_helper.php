<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Pro Tickets — plain-function helpers: access control, extension schema,
 * SLA engine, activity log and the cron automation pipeline.
 *
 * Everything here operates on the CORE ticket tables (tbltickets & friends)
 * plus the module's pro_tickets_* extension tables.
 */

/* ─────────────────────────── Access control ─────────────────────────────── */

function pro_tickets_staff_can_access()
{
    return is_admin()
        || staff_can('view', PRO_TICKETS_MODULE_NAME)
        || staff_can('create', PRO_TICKETS_MODULE_NAME)
        || staff_can('edit', PRO_TICKETS_MODULE_NAME);
}

function pro_tickets_staff_can_create()
{
    return is_admin() || staff_can('create', PRO_TICKETS_MODULE_NAME);
}

function pro_tickets_staff_can_edit()
{
    return is_admin() || staff_can('edit', PRO_TICKETS_MODULE_NAME);
}

function pro_tickets_staff_can_delete()
{
    return is_admin() || staff_can('delete', PRO_TICKETS_MODULE_NAME);
}

function pro_tickets_staff_can_settings()
{
    return is_admin() || staff_can('settings', PRO_TICKETS_MODULE_NAME);
}

/* ────────── Unread customer-reply notifications (provider/staff side) ─────── */

/**
 * Count customer replies that staff have not yet read. A reply flips the
 * ticket's `adminread` flag to 0; opening the ticket sets it back to 1. So an
 * unread customer reply = a client reply (admin IS NULL) newer than the last
 * staff reply, on a ticket still flagged unread for staff.
 *
 * Cached per-request + briefly in the session to avoid a query on every page.
 *
 * @param  bool $force Bypass the session cache (used by the polling endpoint).
 * @return int
 */
function pro_tickets_staff_unread_reply_count($force = false)
{
    static $cached = null;

    if ($cached !== null && !$force) {
        return $cached;
    }
    if (!pro_tickets_staff_can_access()) {
        return $cached = 0;
    }

    $CI  = &get_instance();
    $ttl = 15;

    if (!$force) {
        $c = $CI->session->userdata('pro_tickets_unread_reply_cache');
        if (is_array($c) && isset($c['t'], $c['v']) && (time() - $c['t']) < $ttl) {
            return $cached = (int) $c['v'];
        }
    }

    $p     = db_prefix();
    $count = 0;
    try {
        $sql = "SELECT COUNT(*) AS c
                  FROM {$p}ticket_replies r
                  JOIN {$p}tickets t ON t.ticketid = r.ticketid
                 WHERE t.adminread = 0 AND r.admin IS NULL
                   AND r.date > COALESCE(
                         (SELECT MAX(r2.date) FROM {$p}ticket_replies r2 WHERE r2.ticketid = t.ticketid AND r2.admin IS NOT NULL),
                         t.date
                       )";
        $row   = $CI->db->query($sql)->row();
        $count = $row ? (int) $row->c : 0;
    } catch (\Throwable $e) {
        log_message('error', 'pro_tickets unread reply count failed: ' . $e->getMessage());
    }

    // The polling endpoint releases the session lock before it gets here, so
    // there is nothing left to write to — the per-request static cache above
    // still applies.
    if (!function_exists('ccx_session_is_writable') || ccx_session_is_writable()) {
        $CI->session->set_userdata('pro_tickets_unread_reply_cache', ['t' => time(), 'v' => $count]);
    }

    return $cached = $count;
}

/**
 * Tickets with unread customer replies (subject + reply snippet + who replied)
 * for the real-time toast notifications.
 *
 * @param  int $limit
 * @return array<int,array{id:int,subject:string,from:string,snippet:string,stamp:string,time:string}>
 */
function pro_tickets_staff_unread_reply_items($limit = 6)
{
    if (!pro_tickets_staff_can_access()) {
        return [];
    }

    $CI    = &get_instance();
    $p     = db_prefix();
    $limit = max(1, (int) $limit);
    $items = [];

    try {
        $sql = "SELECT t.ticketid AS id, t.subject AS subject, t.lastreply AS lastreply, t.date AS date,
                    COALESCE((SELECT r.message FROM {$p}ticket_replies r WHERE r.ticketid = t.ticketid AND r.admin IS NULL ORDER BY r.id DESC LIMIT 1), t.message) AS last_message,
                    TRIM(CONCAT(COALESCE(c.firstname, ''), ' ', COALESCE(c.lastname, ''))) AS contact_name
                FROM {$p}tickets t
                LEFT JOIN {$p}contacts c ON c.id = t.contactid
                WHERE t.adminread = 0
                  AND EXISTS (
                        SELECT 1 FROM {$p}ticket_replies r
                         WHERE r.ticketid = t.ticketid AND r.admin IS NULL
                           AND r.date > COALESCE((SELECT MAX(r2.date) FROM {$p}ticket_replies r2 WHERE r2.ticketid = t.ticketid AND r2.admin IS NOT NULL), t.date)
                      )
                ORDER BY (t.lastreply IS NULL) ASC, t.lastreply DESC, t.ticketid DESC
                LIMIT {$limit}";

        $rows = $CI->db->query($sql)->result();
        foreach ($rows as $r) {
            $msg = html_entity_decode(strip_tags((string) $r->last_message), ENT_QUOTES, 'UTF-8');
            $msg = @mb_convert_encoding($msg, 'UTF-8', 'UTF-8');
            $msg = trim(preg_replace('/\s+/', ' ', $msg));
            if (function_exists('mb_strlen') && mb_strlen($msg) > 90) {
                $msg = mb_substr($msg, 0, 90) . '…';
            }
            $stamp = $r->lastreply ?: $r->date;
            $items[] = [
                'id'      => (int) $r->id,
                'subject' => @mb_convert_encoding((string) $r->subject, 'UTF-8', 'UTF-8'),
                'from'    => @mb_convert_encoding(trim((string) $r->contact_name), 'UTF-8', 'UTF-8'),
                'snippet' => $msg,
                'stamp'   => (string) $stamp,
                'time'    => ($stamp && function_exists('_dt')) ? _dt($stamp) : (string) $stamp,
            ];
        }
    } catch (\Throwable $e) {
        log_message('error', 'pro_tickets unread reply items failed: ' . $e->getMessage());
    }

    return $items;
}

/* ─────────────────────────── Schema (self-healing) ──────────────────────── */

/**
 * Create the extension tables when missing. Cheap enough to call from the
 * activation hook, the model constructor and the cron entry point, so a
 * tenant restored from an older backup self-heals.
 */
function pro_tickets_ensure_schema()
{
    $CI = &get_instance();
    $p  = db_prefix();

    if (!$CI->db->table_exists($p . 'pro_tickets_meta')) {
        $CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}pro_tickets_meta` (
            `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `ticket_id` INT(11) NOT NULL,
            `sla_id` INT(11) DEFAULT NULL,
            `frt_due` DATETIME DEFAULT NULL,
            `res_due` DATETIME DEFAULT NULL,
            `first_replied_at` DATETIME DEFAULT NULL,
            `resolved_at` DATETIME DEFAULT NULL,
            `frt_breached` TINYINT(1) NOT NULL DEFAULT 0,
            `res_breached` TINYINT(1) NOT NULL DEFAULT 0,
            `sla_warned` TINYINT(1) NOT NULL DEFAULT 0,
            `escalated` TINYINT(1) NOT NULL DEFAULT 0,
            `reopened_count` INT(11) NOT NULL DEFAULT 0,
            `auto_closed` TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `ticket_id` (`ticket_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
    }

    if (!$CI->db->table_exists($p . 'pro_tickets_sla')) {
        $CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}pro_tickets_sla` (
            `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(150) NOT NULL,
            `department_id` INT(11) NOT NULL DEFAULT 0,
            `priority_id` INT(11) NOT NULL DEFAULT 0,
            `frt_minutes` INT(11) NOT NULL DEFAULT 240,
            `res_minutes` INT(11) NOT NULL DEFAULT 1440,
            `escalate_to` INT(11) NOT NULL DEFAULT 0,
            `active` TINYINT(1) NOT NULL DEFAULT 1,
            `sort_order` INT(11) NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
    }

    if (!$CI->db->table_exists($p . 'pro_tickets_activity')) {
        $CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}pro_tickets_activity` (
            `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `ticket_id` INT(11) NOT NULL,
            `staff_id` INT(11) DEFAULT NULL,
            `type` VARCHAR(40) NOT NULL DEFAULT '',
            `description` TEXT DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `ticket_id` (`ticket_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
    }

    if (!$CI->db->table_exists($p . 'pro_tickets_watchers')) {
        $CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}pro_tickets_watchers` (
            `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `ticket_id` INT(11) NOT NULL,
            `staff_id` INT(11) NOT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `ticket_staff` (`ticket_id`, `staff_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
    }

    if (!$CI->db->table_exists($p . 'pro_tickets_notes')) {
        $CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}pro_tickets_notes` (
            `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `ticket_id` INT(11) NOT NULL,
            `staff_id` INT(11) NOT NULL,
            `note` TEXT DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `ticket_id` (`ticket_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
    }

    // Pro Tickets' own to-do checklist — self-contained, no dependency on the
    // external Todo module. `status`: 0 = pending, 2 = done (kept compatible
    // with the earlier Todo-module integration so the timeline reads the same).
    if (!$CI->db->table_exists($p . 'pro_tickets_todos')) {
        $CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}pro_tickets_todos` (
            `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `ticket_id` INT(11) NOT NULL,
            `title` VARCHAR(500) NOT NULL,
            `description` TEXT DEFAULT NULL,
            `priority` TINYINT(1) NOT NULL DEFAULT 2,
            `status` TINYINT(1) NOT NULL DEFAULT 0,
            `due_date` DATE DEFAULT NULL,
            `assignee_id` INT(11) NOT NULL DEFAULT 0,
            `staff_id` INT(11) NOT NULL DEFAULT 0,
            `datecreated` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `date_completed` DATETIME DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `ticket_id` (`ticket_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
    } elseif (!$CI->db->field_exists('description', $p . 'pro_tickets_todos')) {
        // Self-heal older installs created before the description column existed.
        $CI->db->query("ALTER TABLE `{$p}pro_tickets_todos` ADD COLUMN `description` TEXT DEFAULT NULL AFTER `title`");
    }

    // Reusable to-do checklist templates: a named set of checklist items an
    // agent can apply to any ticket in one click (e.g. "New tenant onboarding").
    if (!$CI->db->table_exists($p . 'pro_tickets_todo_templates')) {
        $CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}pro_tickets_todo_templates` (
            `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(191) NOT NULL,
            `description` VARCHAR(500) DEFAULT NULL,
            `staff_id` INT(11) NOT NULL DEFAULT 0,
            `datecreated` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
    }
    if (!$CI->db->table_exists($p . 'pro_tickets_todo_template_items')) {
        $CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}pro_tickets_todo_template_items` (
            `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `template_id` INT(11) NOT NULL,
            `title` VARCHAR(500) NOT NULL,
            `description` TEXT DEFAULT NULL,
            `priority` TINYINT(1) NOT NULL DEFAULT 2,
            `position` INT(11) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            KEY `template_id` (`template_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
    }

    // Who closed the ticket last: NULL = not closed, 0 = the customer,
    // otherwise the staff id. Drives the "rate our support" prompt wording
    // on the client portal.
    if (!$CI->db->field_exists('closed_by_staff', $p . 'pro_tickets_meta')) {
        $CI->db->query("ALTER TABLE `{$p}pro_tickets_meta` ADD COLUMN `closed_by_staff` INT(11) DEFAULT NULL AFTER `auto_closed`");
    }

    // Customer satisfaction feedback, one row per closed ticket.
    if (!$CI->db->table_exists($p . 'pro_tickets_feedback')) {
        $CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}pro_tickets_feedback` (
            `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `ticket_id` INT(11) NOT NULL,
            `userid` INT(11) NOT NULL DEFAULT 0,
            `contactid` INT(11) NOT NULL DEFAULT 0,
            `agent_id` INT(11) NOT NULL DEFAULT 0,
            `rating` TINYINT(1) NOT NULL,
            `comment` TEXT DEFAULT NULL,
            `closed_by_staff` INT(11) DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `ticket_id` (`ticket_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
    }

    // Explicit per-department agent roster (this tenant only). When populated
    // for a department it becomes the candidate pool for auto-assignment,
    // ahead of the core staff↔department assignments.
    if (!$CI->db->table_exists($p . 'pro_tickets_dept_agents')) {
        $CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}pro_tickets_dept_agents` (
            `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `department_id` INT(11) NOT NULL,
            `staff_id` INT(11) NOT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `dept_staff` (`department_id`, `staff_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
    }
}

/**
 * Customer feedback row for a ticket (or null).
 */
function pro_tickets_get_feedback($ticket_id)
{
    $CI = &get_instance();
    $p  = db_prefix();

    if (!$CI->db->table_exists($p . 'pro_tickets_feedback')) {
        return null;
    }

    return $CI->db->query(
        "SELECT f.*, CONCAT(s.firstname, ' ', s.lastname) AS agent_name,
                TRIM(CONCAT(c.firstname, ' ', c.lastname)) AS contact_name
         FROM `{$p}pro_tickets_feedback` f
         LEFT JOIN `{$p}staff` s ON s.staffid = f.agent_id
         LEFT JOIN `{$p}contacts` c ON c.id = f.contactid
         WHERE f.ticket_id = ?",
        [(int) $ticket_id]
    )->row();
}

/**
 * Translated label for a 1-5 CSAT rating ('' when out of range).
 */
function pro_tickets_rating_label($rating)
{
    $rating = (int) $rating;

    return $rating >= 1 && $rating <= 5 ? _l('pro_tickets_fb_r' . $rating) : '';
}

/**
 * Tone bucket for a rating: 4-5 good, 3 okay, 1-2 bad. Drives the colourway.
 */
function pro_tickets_rating_tone($rating)
{
    $rating = (int) $rating;

    if ($rating >= 4) {
        return 'good';
    }

    return $rating === 3 ? 'okay' : 'bad';
}

/**
 * Inline 5-star CSAT rating for admin tables. The label and any customer
 * comment ride along in the tooltip; unrated tickets render a dash.
 */
function pro_tickets_rating_stars($rating, $comment = '')
{
    $rating = (int) $rating;

    if ($rating < 1 || $rating > 5) {
        return '<span class="ptk-muted">—</span>';
    }

    $stars = '';
    for ($i = 1; $i <= 5; $i++) {
        $stars .= '<i class="fa-' . ($i <= $rating ? 'solid' : 'regular') . ' fa-star"></i>';
    }

    $comment = trim((string) $comment);
    $tip     = pro_tickets_rating_label($rating) . ' (' . $rating . '/5)';
    if ($comment !== '') {
        $tip .= ' — ' . $comment;
        $stars .= '<i class="fa-regular fa-comment-dots ptk-stars-note"></i>';
    }

    return '<span class="ptk-stars ptk-stars-' . pro_tickets_rating_tone($rating) . '" title="'
        . html_escape($tip) . '">' . $stars . '</span>';
}

/* ─────────────────────────────── SLA engine ─────────────────────────────── */

/**
 * Find the most specific active SLA policy for a department + priority pair.
 * Specificity: exact dept + exact priority > exact dept > exact priority >
 * catch-all. Ties resolved by sort_order.
 */
function pro_tickets_match_sla($department, $priority)
{
    $CI = &get_instance();
    $p  = db_prefix();

    $row = $CI->db->query(
        "SELECT * FROM `{$p}pro_tickets_sla`
         WHERE active = 1
           AND (department_id = 0 OR department_id = ?)
           AND (priority_id = 0 OR priority_id = ?)
         ORDER BY (department_id != 0) DESC, (priority_id != 0) DESC, sort_order ASC, id ASC
         LIMIT 1",
        [(int) $department, (int) $priority]
    )->row();

    return $row ?: null;
}

/**
 * Make sure a meta row exists for a ticket and its SLA targets are set.
 * History is always reconstructed from existing replies/status so tickets
 * that predate the module never trigger a storm of stale SLA notifications
 * (for a brand-new ticket the reconstruction is a no-op). The $silent flag
 * is kept for call-site readability only.
 *
 * @return object|null the meta row
 */
function pro_tickets_ensure_meta($ticket_id, $silent = true)
{
    $CI = &get_instance();
    $p  = db_prefix();

    $ticket = $CI->db->where('ticketid', (int) $ticket_id)->get($p . 'tickets')->row();
    if (!$ticket) {
        return null;
    }

    $meta = $CI->db->where('ticket_id', (int) $ticket_id)->get($p . 'pro_tickets_meta')->row();
    if ($meta) {
        return $meta;
    }

    $sla  = pro_tickets_match_sla($ticket->department, $ticket->priority);
    $base = strtotime($ticket->date) ?: time();

    $data = [
        'ticket_id'  => (int) $ticket_id,
        'sla_id'     => $sla ? $sla->id : null,
        'frt_due'    => $sla ? date('Y-m-d H:i:s', $base + $sla->frt_minutes * 60) : null,
        'res_due'    => $sla ? date('Y-m-d H:i:s', $base + $sla->res_minutes * 60) : null,
        'created_at' => date('Y-m-d H:i:s'),
    ];

    {
        // Reconstruct what already happened so the cron never fires stale
        // warnings/escalations for pre-existing tickets.
        $firstReply = $CI->db->query(
            "SELECT MIN(date) AS d FROM `{$p}ticket_replies` WHERE ticketid = ? AND admin IS NOT NULL",
            [(int) $ticket_id]
        )->row();
        if ($firstReply && $firstReply->d) {
            $data['first_replied_at'] = $firstReply->d;
        }
        if ($ticket->status == 5) {
            $data['resolved_at'] = $ticket->lastreply ?: $ticket->date;
        }
        if ($data['frt_due'] && !isset($data['first_replied_at']) && time() > strtotime($data['frt_due'])) {
            $data['frt_breached'] = 1;
        }
        if (isset($data['first_replied_at']) && $data['frt_due'] && strtotime($data['first_replied_at']) > strtotime($data['frt_due'])) {
            $data['frt_breached'] = 1;
        }
        if ($data['res_due'] && $ticket->status != 5 && time() > strtotime($data['res_due'])) {
            $data['res_breached'] = 1;
        }
        if (isset($data['resolved_at']) && $data['res_due'] && strtotime($data['resolved_at']) > strtotime($data['res_due'])) {
            $data['res_breached'] = 1;
        }
        $data['sla_warned'] = ($data['frt_breached'] ?? 0) || ($data['res_breached'] ?? 0) ? 1 : 0;
        $data['escalated']  = $data['sla_warned'];
    }

    $CI->db->insert($p . 'pro_tickets_meta', $data);

    return $CI->db->where('ticket_id', (int) $ticket_id)->get($p . 'pro_tickets_meta')->row();
}

/**
 * Re-match and re-apply the SLA policy for a ticket (used after a department
 * or priority change). Deadlines are recomputed from the ticket open date;
 * already-met milestones are kept.
 */
function pro_tickets_reapply_sla($ticket_id)
{
    $CI = &get_instance();
    $p  = db_prefix();

    $ticket = $CI->db->where('ticketid', (int) $ticket_id)->get($p . 'tickets')->row();
    $meta   = pro_tickets_ensure_meta($ticket_id);
    if (!$ticket || !$meta) {
        return;
    }

    $sla  = pro_tickets_match_sla($ticket->department, $ticket->priority);
    $base = strtotime($ticket->date) ?: time();

    $CI->db->where('ticket_id', (int) $ticket_id)->update($p . 'pro_tickets_meta', [
        'sla_id'  => $sla ? $sla->id : null,
        'frt_due' => $sla ? date('Y-m-d H:i:s', $base + $sla->frt_minutes * 60) : null,
        'res_due' => $sla ? date('Y-m-d H:i:s', $base + $sla->res_minutes * 60) : null,
    ]);
}

/**
 * Human-readable duration for minute counts (e.g. "1d 4h", "45m").
 */
function pro_tickets_human_duration($minutes)
{
    $minutes = (int) round($minutes);
    if ($minutes < 1) {
        return '0m';
    }
    $d = intdiv($minutes, 1440);
    $h = intdiv($minutes % 1440, 60);
    $m = $minutes % 60;

    $parts = [];
    if ($d) {
        $parts[] = $d . 'd';
    }
    if ($h) {
        $parts[] = $h . 'h';
    }
    if ($m && !$d) {
        $parts[] = $m . 'm';
    }

    return implode(' ', $parts);
}

/**
 * UI state of a ticket's SLA for badges/chips.
 *
 * @return array ['state' => none|met|ok|warn|breach, 'label' => string]
 */
function pro_tickets_sla_state($meta, $ticket_status)
{
    if (!$meta || (!$meta->frt_due && !$meta->res_due)) {
        return ['state' => 'none', 'label' => _l('pro_tickets_sla_none')];
    }

    if ($ticket_status == 5) {
        return ($meta->frt_breached || $meta->res_breached)
            ? ['state' => 'breach', 'label' => _l('pro_tickets_sla_breached')]
            : ['state' => 'met', 'label' => _l('pro_tickets_sla_met')];
    }

    // Active ticket: the next pending milestone drives the chip.
    $due = (!$meta->first_replied_at && $meta->frt_due) ? $meta->frt_due : $meta->res_due;
    if (!$due) {
        return ['state' => 'none', 'label' => _l('pro_tickets_sla_none')];
    }

    $left = strtotime($due) - time();
    if ($meta->frt_breached || $meta->res_breached || $left < 0) {
        return ['state' => 'breach', 'label' => _l('pro_tickets_sla_overdue_by', pro_tickets_human_duration(abs($left) / 60))];
    }

    $warnPct = (int) get_option('pro_tickets_sla_warning_pct') ?: 80;
    $meta_created = strtotime($meta->created_at);
    $total        = strtotime($due) - $meta_created;
    $consumed     = time() - $meta_created;
    $state        = ($total > 0 && ($consumed / $total) * 100 >= $warnPct) ? 'warn' : 'ok';

    return ['state' => $state, 'label' => _l('pro_tickets_sla_due_in', pro_tickets_human_duration($left / 60))];
}

/**
 * Render one activity row (from pro_tickets_activity joined with staff
 * names) into a human sentence. Used by the dashboard feed and the ticket
 * timeline.
 */
function pro_tickets_activity_line($item)
{
    $who   = trim(($item->firstname ?? '') . ' ' . ($item->lastname ?? ''));
    $who   = $who !== '' ? $who : _l('pro_tickets_act_system');
    $type  = $item->type;
    $descr = (string) ($item->description ?? '');

    // Keys whose lang string embeds the description via %s.
    $sprintfTypes = ['assigned', 'auto_assigned', 'watcher_added', 'watcher_removed', 'cc_added', 'cc_removed', 'todo_added', 'todo_completed', 'todo_reopened', 'todo_deleted', 'pdf_generated', 'feedback_received', 'transferred', 'client_linked', 'mentioned', 'smart_reported'];
    $key          = 'pro_tickets_act_' . $type;
    $label        = in_array($type, $sprintfTypes, true) ? _l($key, $descr) : _l($key);

    // System-driven events read as standalone sentences; human actions are
    // prefixed with the actor.
    $systemTypes = ['created', 'client_reply', 'auto_assigned', 'sla_warning', 'sla_breach', 'priority_escalated', 'auto_closed'];
    if (in_array($type, $systemTypes, true) || !$item->staff_id) {
        return $label;
    }

    return $who . ' — ' . $label;
}

/* ─────────────────────── Todo / Caller integration ──────────────────────── */

/**
 * Whether another module is installed AND activated.
 */
function pro_tickets_module_active($module)
{
    $CI = &get_instance();

    return is_dir(FCPATH . 'modules/' . $module) && $CI->app_modules->is_active($module);
}

/* ───────────── Smart Ticket — provider (master) admin panel ─────────────── */

/*
 * Three Smart Ticket paths exist, all sharing the same widget assets:
 *   1. customer portal  → clients/pro_tickets/smart_create      (own DB)
 *   2. tenant admin     → admin/billing/smart_ticket_submit     (cross-DB to master,
 *                          wired in modules/perfex_saas/hooks/tenant_smart_ticket.php)
 *   3. master admin     → admin/pro_tickets/smart_create        (this module, below)
 *
 * Path 3 lets the provider's OWN staff report issues in their CRM modules from
 * anywhere in the master admin panel with the same keyboard shortcut, filed as
 * an internal ticket in this helpdesk.
 */

/**
 * Whether the admin-panel Smart Ticket launcher is enabled on this install.
 *
 * The option is seeded by install.php, but copies of the module installed
 * before that existed have no row at all — and core get_option() answers ''
 * (never false) for a missing option, so an unset value must read as ON, not
 * as "switched off".
 *
 * @return bool
 */
function pro_tickets_smart_admin_enabled()
{
    $value = get_option('pro_tickets_smart_admin_enabled');

    return ($value === '' || $value === null) ? true : ($value == '1');
}

/**
 * Whether the admin Smart Ticket widget should be injected for this request.
 *
 * Provider / standalone side only: on a SaaS tenant the perfex_saas hook
 * already injects the same widget pointed at the provider's helpdesk, and this
 * module is normally inactive there anyway.
 *
 * @return bool
 */
function pro_tickets_smart_admin_available()
{
    if (function_exists('perfex_saas_is_tenant') && perfex_saas_is_tenant()) {
        return false;
    }

    return function_exists('is_staff_logged_in')
        && is_staff_logged_in()
        && pro_tickets_smart_admin_enabled();
}

/**
 * Human label for the CRM area an admin URL belongs to — the whole point of an
 * internal report is knowing WHICH module broke, so the reporter never has to
 * type it.
 *
 * The admin controller segment is matched against modules/<name>: a hit is
 * reported as that module (by its registered module name when available),
 * anything else as a core CRM area. Returns '' when nothing can be derived,
 * in which case the caller simply omits the line.
 *
 * @param  string $url absolute URL submitted by the widget
 * @return string
 */
function pro_tickets_smart_admin_area($url)
{
    $path = (string) parse_url((string) $url, PHP_URL_PATH);
    if ($path === '') {
        return '';
    }

    $segments = array_values(array_filter(explode('/', $path), function ($s) {
        return $s !== '';
    }));

    // The controller sits right after the admin URI, whose length is
    // installation-specific: the master panel is served under a secret
    // multi-segment CUSTOM_ADMIN_URL slug ("<secret>/admin"), tenants under
    // plain "admin". Anchor on the configured slug, and fall back to scanning
    // for an "admin" segment if this URL doesn't carry it.
    $controller_at = false;

    $admin_uri = function_exists('get_admin_uri') ? trim((string) get_admin_uri(), '/') : 'admin';
    $admin_len = count(array_filter(explode('/', $admin_uri), function ($s) {
        return $s !== '';
    }));

    if ($admin_len > 0 && strcasecmp(implode('/', array_slice($segments, 0, $admin_len)), $admin_uri) === 0) {
        $controller_at = $admin_len;
    } else {
        $admin_at = array_search('admin', $segments, true);
        if ($admin_at !== false) {
            $controller_at = $admin_at + 1;
        }
    }

    if ($controller_at === false || !isset($segments[$controller_at])) {
        return '';
    }

    $controller = strtolower(preg_replace('/[^a-zA-Z0-9_\-]/', '', $segments[$controller_at]));
    if ($controller === '') {
        return '';
    }

    if (is_dir(FCPATH . 'modules/' . $controller)) {
        $CI   = &get_instance();
        $name = '';

        // Prefer the module's own declared name ("Pro Tickets") over the
        // directory slug ("pro_tickets").
        if (isset($CI->app_modules) && method_exists($CI->app_modules, 'get')) {
            $module = $CI->app_modules->get($controller);
            $name   = is_array($module) ? trim((string) ($module['headers']['module_name'] ?? '')) : '';
        }

        if ($name === '') {
            $name = ucwords(str_replace('_', ' ', $controller));
        }

        return _l('pro_tickets_smart_area_module', $name);
    }

    return _l('pro_tickets_smart_area_core', ucwords(str_replace('_', ' ', $controller)));
}

/* ─────────────────── Composer templates & merge tags ────────────────────── */

/**
 * The greeting/signature skeleton shipped as the default predefined message.
 * Stored once in the core tbltickets_predefined_replies table, so it is
 * equally usable from the reply composer and the core ticket screens.
 */
function pro_tickets_default_template_html()
{
    return '<p>Dear {Name} ji,</p>'
        . '<p>&nbsp;</p>'
        . '<p>&nbsp;</p>'
        . '<p>Thank You</p>'
        . '<p>Best Regards,<br />{Agent Name}<br />{Role}<br />{Company Name}</p>';
}

/**
 * Seed the default greeting template once and point the new-ticket composer at
 * it. Called lazily from the screens that use templates so already-installed
 * copies of the module pick it up without a re-install.
 */
function pro_tickets_seed_default_templates()
{
    // get_option() returns '' (never false) for a missing option, so the
    // add_option() calls run unconditionally and the guard reads the value.
    add_option('pro_tickets_default_template_seeded', '0');
    add_option('pro_tickets_new_ticket_template', '0');

    if (get_option('pro_tickets_default_template_seeded') == '1') {
        return;
    }

    $CI   = &get_instance();
    $name = _l('pro_tickets_tpl_default_name');

    $existing = $CI->db->select('id')->where('name', $name)
        ->get(db_prefix() . 'tickets_predefined_replies')->row();

    if ($existing) {
        $id = (int) $existing->id;
    } else {
        $CI->db->insert(db_prefix() . 'tickets_predefined_replies', [
            'name'    => $name,
            'message' => pro_tickets_default_template_html(),
        ]);
        $id = (int) $CI->db->insert_id();
    }

    update_option('pro_tickets_default_template_seeded', '1');

    if ($id && (int) get_option('pro_tickets_new_ticket_template') === 0) {
        update_option('pro_tickets_new_ticket_template', $id);
    }
}

/**
 * Values for the merge tags a predefined message may carry. Everything that
 * depends on who is composing (agent, role, company, date) is resolved here;
 * ticket-specific values ({Name}, {Subject}) are passed in by the caller —
 * on the new-ticket screen the requester isn't known server-side, so those
 * tags are left in place and filled by the browser as the form is completed.
 */
function pro_tickets_template_tags(array $extra = [])
{
    $CI       = &get_instance();
    $staff_id = (int) get_staff_user_id();

    $role = $CI->db->query(
        "SELECT r.name FROM `" . db_prefix() . "roles` r
         JOIN `" . db_prefix() . "staff` s ON s.role = r.roleid
         WHERE s.staffid = ?",
        [$staff_id]
    )->row();

    return array_merge([
        'name'    => '',
        'agent'   => $staff_id ? get_staff_full_name($staff_id) : '',
        'role'    => $role ? $role->name : _l('pro_tickets_tpl_role_fallback'),
        'company' => (string) get_option('companyname'),
        'subject' => '',
        'date'    => _d(date('Y-m-d')),
    ], $extra);
}

/**
 * Map a raw tag name to its value slot. Matching ignores case, spaces and
 * punctuation, so {Agent Name}, {agent_name} and {AgentName} are the same tag.
 */
function pro_tickets_template_tag_slot($raw)
{
    $key = preg_replace('/[^a-z]/', '', strtolower((string) $raw));

    $map = [
        'name'          => 'name',
        'clientname'    => 'name',
        'customername'  => 'name',
        'contactname'   => 'name',
        'requestername' => 'name',
        'agentname'     => 'agent',
        'agent'         => 'agent',
        'staffname'     => 'agent',
        'myname'        => 'agent',
        'role'          => 'role',
        'agentrole'     => 'role',
        'designation'   => 'role',
        'companyname'   => 'company',
        'company'       => 'company',
        'subject'       => 'subject',
        'ticketsubject' => 'subject',
        'date'          => 'date',
        'todaysdate'    => 'date',
    ];

    return isset($map[$key]) ? $map[$key] : '';
}

/**
 * Requester name + subject of a ticket, for filling {Name} / {Subject} on the
 * ticket screens. Tickets opened for a client contact carry the name on the
 * contact row rather than on the ticket itself.
 */
function pro_tickets_ticket_tag_values($ticket_id)
{
    $CI     = &get_instance();
    $ticket = $CI->db->select('subject, name, contactid')
        ->where('ticketid', (int) $ticket_id)
        ->get(db_prefix() . 'tickets')->row();

    if (!$ticket) {
        return [];
    }

    $name = trim((string) $ticket->name);
    if ($name === '' && $ticket->contactid) {
        $contact = $CI->db->select('firstname, lastname')
            ->where('id', (int) $ticket->contactid)
            ->get(db_prefix() . 'contacts')->row();
        $name = $contact ? trim($contact->firstname . ' ' . $contact->lastname) : '';
    }

    return ['name' => $name, 'subject' => (string) $ticket->subject];
}

/**
 * Replace the merge tags in a predefined message. Unknown tags — and known
 * ones with nothing to fill them yet — are left untouched so the agent can
 * see (and complete) them instead of getting a silently blanked signature.
 */
function pro_tickets_apply_template_tags($html, array $extra = [])
{
    $values = pro_tickets_template_tags($extra);

    return preg_replace_callback('/\{\s*([A-Za-z][A-Za-z0-9 _\-]{0,30})\s*\}/', function ($m) use ($values) {
        $slot = pro_tickets_template_tag_slot($m[1]);

        if ($slot === '' || empty($values[$slot])) {
            return $m[0];
        }

        return html_escape($values[$slot]);
    }, (string) $html);
}

/**
 * Last-10-digit phone match expression (mirrors the Caller module helper so
 * Pro Tickets works even when caller_helper.php isn't loaded).
 */
function pro_tickets_phone_last10($phone)
{
    $digits = preg_replace('/[^0-9]/', '', (string) $phone);

    return strlen($digits) >= 10 ? substr($digits, -10) : $digits;
}

/**
 * Return ticket replies ordered newest-first for display.
 *
 * Pro Tickets conversation threads always show the latest reply at the top
 * (original message at the bottom), regardless of the core
 * `ticket_replies_order` option — that option keeps steering the core ticket
 * screens and the chronological AI transcript, so normalize here instead of
 * changing the fetch order.
 */
function pro_tickets_replies_newest_first(array $replies)
{
    usort($replies, function ($a, $b) {
        $cmp = strcmp((string) $b['date'], (string) $a['date']);

        return $cmp !== 0 ? $cmp : ((int) $b['id'] <=> (int) $a['id']);
    });

    return $replies;
}

/**
 * Relocate stray Smart Ticket screenshots into the master ticket uploads dir.
 *
 * The tenant-admin Smart Ticket capture used to save its screenshot via
 * TICKET_ATTACHMENTS_FOLDER, which perfex_saas redefines per-tenant — so the
 * file landed in uploads/tenants/<slug>/ticket_attachments/<id>/ while the
 * attachment row lives on the MASTER helpdesk ticket. Those tickets render a
 * dead attachment chip. Called lazily from the ticket views: for any
 * attachment file missing at the master path, look for it under the tenant
 * upload dirs and move it home. Cheap no-op when nothing is missing.
 */
function pro_tickets_rescue_stray_attachments($ticket_id)
{
    $ticket_id = (int) $ticket_id;
    if (!$ticket_id) {
        return;
    }

    $CI   = &get_instance();
    $rows = $CI->db->select('file_name')
        ->from(db_prefix() . 'ticket_attachments')
        ->where('ticketid', $ticket_id)
        ->get()->result();

    if (!$rows) {
        return;
    }

    $base       = defined('PERFEX_SAAS_UPLOAD_BASE_DIR') ? PERFEX_SAAS_UPLOAD_BASE_DIR : 'uploads/';
    $master_dir = FCPATH . $base . 'ticket_attachments/' . $ticket_id . '/';

    foreach ($rows as $row) {
        $fname = basename((string) $row->file_name);
        // Only Smart Ticket cross-DB uploads ever strayed; the strict pattern
        // also keeps the filename glob-safe and avoids grabbing a tenant's own
        // same-numbered local ticket files.
        if (!preg_match('/^smart-\d+-\d+\.[a-z0-9]{3,4}$/i', $fname) || is_file($master_dir . $fname)) {
            continue;
        }

        $matches = glob(FCPATH . $base . 'tenants/*/ticket_attachments/' . $ticket_id . '/' . $fname);
        if (empty($matches)) {
            continue;
        }

        if (!is_dir($master_dir)) {
            @mkdir($master_dir, 0755, true);
            @fopen($master_dir . 'index.html', 'w');
        }
        if (@rename($matches[0], $master_dir . $fname)) {
            @chmod($master_dir . $fname, 0644);
            log_message('error', 'Pro Tickets: rescued stray Smart Ticket attachment ' . $fname . ' for ticket #' . $ticket_id);
        }
    }
}

/* ──────────────────── Omni Messaging (system hooks) ─────────────────────── */

/**
 * _l() for the hook labels below, safe outside the admin panel.
 *
 * register_language_files() loads a module's language on
 * after_load_admin_language / after_load_client_language only. E-mail piping
 * (pipe.php) bootstraps a bare CI_Controller and fires neither — and CI's
 * lang->line() returns FALSE for an unknown key, so _l() would quietly render
 * an EMPTY tag in a piped ticket's messages.
 *
 * @param  string       $key
 * @param  string|array $label sprintf argument(s), as _l() takes them
 * @return string
 */
function pro_tickets_omni_l($key, $label = '')
{
    static $tried = false;

    if (!$tried) {
        // Once per request, whatever the outcome — lang->load() show_error()s on
        // a missing file, which would take the whole request with it.
        $tried = true;

        $dir   = FCPATH . 'modules/pro_tickets/language/';
        $idiom = get_option('active_language') ?: 'english';
        if (!is_file($dir . $idiom . '/pro_tickets_lang.php')) {
            $idiom = 'english';
        }
        if (is_file($dir . $idiom . '/pro_tickets_lang.php')) {
            get_instance()->lang->load('pro_tickets/pro_tickets', $idiom);
        }
    }

    return _l($key, $label);
}

/**
 * The requester mobile a hook-triggered SMS/WhatsApp actually goes to: the
 * contact's own number, falling back to the company record's.
 *
 * Single rule for both the outgoing payload ({mobile_number}) and the number
 * shown on the ticket's Requester card — they must never disagree.
 */
function pro_tickets_pick_mobile($contact_phone, $client_phone)
{
    $contact_phone = trim((string) $contact_phone);

    return $contact_phone !== '' ? $contact_phone : trim((string) $client_phone);
}

/**
 * Mask a phone number for display, leaving the last 4 digits readable and any
 * separators (+, spaces, dashes) in place so the shape stays recognisable.
 */
function pro_tickets_mask_mobile($number)
{
    $number = trim((string) $number);
    if ($number === '') {
        return '';
    }

    $digits = preg_match_all('/\d/', $number);
    $seen   = 0;

    return preg_replace_callback('/\d/', function ($m) use (&$seen, $digits) {
        $seen++;

        return $seen > $digits - 4 ? $m[0] : '•';
    }, $number);
}

/**
 * Build the standard variable payload every Pro Tickets system hook carries.
 *
 * Mirrors application/helpers/ccx_hooks/pro_tickets_hooks.php — anything added
 * to the common variable list there must be produced here, otherwise the tag
 * survives unreplaced in the outgoing message.
 *
 * {mobile_number} / {email} are the requester's, because that is what the
 * recipient resolver reads for the default ("patient") recipient type. The
 * assignee is exposed separately so staff-facing hooks can be mapped with a
 * Custom recipient of {assigned_mobile} / {assigned_email}.
 *
 * @param  int $ticket_id
 * @return array|null  null when the ticket no longer exists
 */
function pro_tickets_omni_payload($ticket_id)
{
    $CI = &get_instance();
    $p  = db_prefix();

    $t = $CI->db->query(
        "SELECT t.ticketid, t.subject, t.message, t.date, t.status, t.priority, t.department,
                t.assigned, t.userid, t.contactid, t.name AS from_name, t.email AS ticket_email,
                st.name AS status_name, pr.name AS priority_name, d.name AS department_name,
                s.firstname AS s_first, s.lastname AS s_last, s.email AS s_email, s.phonenumber AS s_phone,
                c.firstname AS c_first, c.lastname AS c_last, c.email AS c_email, c.phonenumber AS c_phone,
                cl.company, cl.phonenumber AS cl_phone,
                m.frt_due, m.res_due, m.first_replied_at, m.resolved_at,
                m.frt_breached, m.res_breached, m.reopened_count
         FROM `{$p}tickets` t
         LEFT JOIN `{$p}tickets_status` st ON st.ticketstatusid = t.status
         LEFT JOIN `{$p}tickets_priorities` pr ON pr.priorityid = t.priority
         LEFT JOIN `{$p}departments` d ON d.departmentid = t.department
         LEFT JOIN `{$p}staff` s ON s.staffid = t.assigned
         LEFT JOIN `{$p}contacts` c ON c.id = t.contactid
         LEFT JOIN `{$p}clients` cl ON cl.userid = t.userid
         LEFT JOIN `{$p}pro_tickets_meta` m ON m.ticket_id = t.ticketid
         WHERE t.ticketid = ? LIMIT 1",
        [(int) $ticket_id]
    )->row();

    if (!$t) {
        return null;
    }

    $customer_name = trim($t->c_first . ' ' . $t->c_last);
    if ($customer_name === '') {
        $customer_name = (string) $t->from_name;
    }

    $assigned_name = trim($t->s_first . ' ' . $t->s_last);

    $requester_email = (string) ($t->c_email ?: $t->ticket_email);

    // Same resolution the ticket page shows on the Requester card, so what an
    // agent reads there is exactly where this message goes. Never fatal: an
    // unreachable tenant just leaves the number empty.
    $mobile = pro_tickets_pick_mobile($t->c_phone, $t->cl_phone);
    if ($mobile === '') {
        try {
            $CI->load->model('pro_tickets/pro_tickets_model');
            $mobile = $CI->pro_tickets_model->get_tenant_staff_phone($t->userid, $requester_email);
        } catch (Throwable $e) {
            $mobile = '';
        }
    }

    return [
        'ticket_id'         => (int) $t->ticketid,
        'ticket_number'     => '#' . (int) $t->ticketid,
        'ticket_subject'    => (string) $t->subject,
        'ticket_status'     => (string) $t->status_name,
        'ticket_priority'   => (string) $t->priority_name,
        'ticket_department' => (string) $t->department_name,
        'ticket_date'       => $t->date ? _dt($t->date) : '',
        'customer_name'     => $customer_name,
        'customer_company'  => (string) $t->company,
        // The resolver reads {patient_name} for the default recipient's name,
        // which is what lands in {recipient_name}.
        'patient_name'      => $customer_name,
        'mobile_number'     => $mobile,
        'email'             => $requester_email,
        'patient_email'     => $requester_email,
        'assigned_name'     => $assigned_name,
        'assigned_email'    => (string) $t->s_email,
        'assigned_mobile'   => (string) $t->s_phone,
        'ticket_url'        => site_url('clients/pro_tickets/ticket/' . (int) $t->ticketid),
        'admin_ticket_url'  => admin_url('pro_tickets/ticket/' . (int) $t->ticketid),
    ];
}

/**
 * Flatten a ticket/reply body into a one-line excerpt safe for an SMS or a
 * WhatsApp bubble.
 */
function pro_tickets_omni_excerpt($html, $length = 160)
{
    $text = trim(preg_replace('/\s+/', ' ', strip_tags((string) $html)));

    return mb_strlen($text) > $length ? mb_substr($text, 0, $length - 1) . '…' : $text;
}

/**
 * Fire one of this module's Omni Messaging system hooks.
 *
 * Everything is best-effort: a messaging failure must never break the ticket
 * action that triggered it, and the hooks registry simply isn't there on an
 * installation without the Omni Messaging stack.
 *
 * @param string         $hook_key  key from application/helpers/ccx_hooks/pro_tickets_hooks.php
 * @param int            $ticket_id
 * @param array|callable $extra     hook-specific variables, or a callback
 *                                  receiving the resolved payload and
 *                                  returning them (for extras derived from it)
 */
function pro_tickets_fire_omni($hook_key, $ticket_id, $extra = [])
{
    if (!function_exists('ccx_fire_hook') || !$ticket_id) {
        return;
    }

    try {
        $data = pro_tickets_omni_payload((int) $ticket_id);
        if ($data === null) {
            return;
        }

        if (is_callable($extra)) {
            $extra = $extra($data);
        }

        ccx_fire_hook($hook_key, array_merge($data, (array) $extra));
    } catch (Throwable $e) {
        log_activity('Pro Tickets omni hook failed [' . $hook_key . ' #' . (int) $ticket_id . ': ' . $e->getMessage() . ']');
    }
}

/* ─────────────────────────── Activity & notify ──────────────────────────── */

/**
 * Append an entry to the ticket's activity timeline.
 */
function pro_tickets_log($ticket_id, $type, $description, $staff_id = null)
{
    $CI = &get_instance();
    $CI->db->insert(db_prefix() . 'pro_tickets_activity', [
        'ticket_id'   => (int) $ticket_id,
        'staff_id'    => $staff_id,
        'type'        => $type,
        'description' => $description,
        'created_at'  => date('Y-m-d H:i:s'),
    ]);
}

/**
 * In-app notification (+pusher) to one staff member about a ticket.
 * $lang_key must accept the ticket subject via %s.
 */
function pro_tickets_notify($staff_id, $lang_key, $ticket_id, $subject)
{
    if (!$staff_id) {
        return;
    }
    $notified = add_notification([
        'description'     => $lang_key,
        'touserid'        => (int) $staff_id,
        'fromcompany'     => 1,
        'fromuserid'      => 0,
        'link'            => 'pro_tickets/ticket/' . (int) $ticket_id,
        'additional_data' => serialize([$subject]),
    ]);
    if ($notified) {
        pusher_trigger_notification([(int) $staff_id]);
    }
}

/**
 * Staff ids watching a ticket.
 */
function pro_tickets_watcher_ids($ticket_id)
{
    $CI   = &get_instance();
    $rows = $CI->db->select('staff_id')->where('ticket_id', (int) $ticket_id)
        ->get(db_prefix() . 'pro_tickets_watchers')->result_array();

    return array_map('intval', array_column($rows, 'staff_id'));
}

/**
 * Notify every watcher of a ticket (minus excluded staff ids).
 */
function pro_tickets_notify_watchers($ticket_id, $lang_key, $subject, $exclude = [])
{
    if (get_option('pro_tickets_notify_watchers') != '1') {
        return;
    }
    foreach (pro_tickets_watcher_ids($ticket_id) as $staff_id) {
        if (!in_array($staff_id, $exclude)) {
            pro_tickets_notify($staff_id, $lang_key, $ticket_id, $subject);
        }
    }
}

/* ──────────────────────── @mentions (internal staff) ────────────────────── */

/**
 * Active staff members that can be @mentioned, as
 * [['id' => 3, 'name' => 'John Doe', 'email' => '…'], …].
 *
 * Limited to admins and staff holding any Pro Tickets capability — tagging
 * someone who cannot open the ticket would send them to an access-denied
 * page. One query rather than a per-member staff_can() round-trip.
 */
function pro_tickets_mentionable_staff()
{
    static $cache = null;

    if ($cache === null) {
        $CI    = &get_instance();
        $p     = db_prefix();
        $cache = [];

        $rows = $CI->db->query(
            "SELECT s.staffid, s.firstname, s.lastname, s.email
             FROM `{$p}staff` s
             WHERE s.active = 1
               AND (s.admin = 1 OR EXISTS (
                    SELECT 1 FROM `{$p}staff_permissions` sp
                    WHERE sp.staff_id = s.staffid AND sp.feature = ?))
             ORDER BY s.firstname ASC",
            [PRO_TICKETS_MODULE_NAME]
        )->result();

        // Nobody matched (permissions never granted on this install) — fall
        // back to the full roster rather than silently disabling mentions.
        if (!$rows) {
            $rows = $CI->db->select('staffid, firstname, lastname, email')
                ->where('active', 1)->order_by('firstname', 'asc')
                ->get($p . 'staff')->result();
        }

        foreach ($rows as $row) {
            $name = trim(preg_replace('/\s+/', ' ', $row->firstname . ' ' . $row->lastname));
            if ($name === '') {
                continue;
            }
            $cache[] = ['id' => (int) $row->staffid, 'name' => $name, 'email' => (string) $row->email];
        }
    }

    return $cache;
}

/**
 * One alternation regex matching every mentionable "@Full Name" in a body.
 *
 * Names are matched longest-first so "@John Smithson" is never resolved as
 * "John Smith" (PHP alternation takes the first branch that matches), and the
 * trailing look-ahead stops a short name from matching the head of a longer
 * one. Spaces inside a name are relaxed to \s+ because an editor is free to
 * split "John Smith" across markup or a non-breaking space.
 *
 * @param bool $escaped match against html_escape()d text (internal notes)
 *
 * @return string|null null when there is nobody to mention
 */
function pro_tickets_mention_pattern($escaped = false)
{
    $staff = pro_tickets_mentionable_staff();
    if (!$staff) {
        return null;
    }

    $names = array_column($staff, 'name');
    usort($names, function ($a, $b) {
        return mb_strlen($b) - mb_strlen($a);
    });

    $alts = [];
    foreach ($names as $name) {
        $alts[] = str_replace(' ', '\s+', preg_quote($escaped ? html_escape($name) : $name, '/'));
    }

    // The leading look-behind keeps e-mail addresses out of it ("care@ali.com"
    // must not tag Ali); the trailing one stops a short name from matching the
    // head of a longer one.
    return '/(?<![\p{L}\p{N}_.@-])@(' . implode('|', $alts) . ')(?![\p{L}\p{N}_])/iu';
}

/**
 * Staff ids @mentioned inside a reply or note body.
 *
 * Two passes, because the composer chip is not guaranteed to survive: the
 * picker stamps data-mention-id on the inserted span, and any "@Full Name"
 * left as plain text (typed by hand, or stripped of its markup by the editor)
 * is resolved against the staff roster.
 */
function pro_tickets_extract_mentions($body)
{
    $body = (string) $body;
    if (strpos($body, '@') === false) {
        return [];
    }

    $ids = [];
    if (preg_match_all('/data-mention-id=["\']?(\d+)/i', $body, $matches)) {
        $ids = array_map('intval', $matches[1]);
    }

    $pattern = pro_tickets_mention_pattern();
    if ($pattern) {
        // Plain-text view of the body: tags out, entities decoded, so a name
        // matches whether it came from TinyMCE or from a plain textarea.
        $text = preg_replace('/<[^>]*>/', ' ', $body);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\xC2\xA0", ' ', $text);

        if (preg_match_all($pattern, $text, $matches)) {
            $found = array_map(function ($name) {
                return mb_strtolower(trim(preg_replace('/\s+/', ' ', $name)));
            }, $matches[1]);

            foreach (pro_tickets_mentionable_staff() as $member) {
                if (in_array(mb_strtolower($member['name']), $found, true)) {
                    $ids[] = $member['id'];
                }
            }
        }
    }

    return array_values(array_unique(array_filter($ids)));
}

/**
 * In-app notification to every staff member @mentioned in a reply or note.
 *
 * @return array staff ids actually notified
 */
function pro_tickets_notify_mentions($ticket_id, $body, $context = 'reply', $author_id = null)
{
    $ids = pro_tickets_extract_mentions($body);
    if (!$ids) {
        return [];
    }

    $CI     = &get_instance();
    $ticket = $CI->db->select('subject')->where('ticketid', (int) $ticket_id)
        ->get(db_prefix() . 'tickets')->row();
    if (!$ticket) {
        return [];
    }

    $author = $author_id !== null ? (int) $author_id : (int) get_staff_user_id();
    $roster = [];
    foreach (pro_tickets_mentionable_staff() as $member) {
        $roster[$member['id']] = $member['name'];
    }

    $key      = $context === 'note' ? 'pro_tickets_not_mentioned_note' : 'pro_tickets_not_mentioned';
    $notified = [];
    foreach ($ids as $staff_id) {
        // Mentioning yourself is a no-op — the notification would be noise.
        if ($staff_id === $author || !isset($roster[$staff_id])) {
            continue;
        }
        pro_tickets_notify($staff_id, $key, $ticket_id, $ticket->subject);
        $notified[] = $staff_id;
    }

    if ($notified) {
        $names = [];
        foreach ($notified as $staff_id) {
            $names[] = $roster[$staff_id];
        }
        pro_tickets_log($ticket_id, 'mentioned', implode(', ', $names), $author ?: null);
    }

    return $notified;
}

/**
 * Wrap "@Full Name" mentions in a chip for display. Runs on already-escaped
 * text (internal notes), so nothing but the wrapper is introduced.
 */
function pro_tickets_highlight_mentions($escaped_text)
{
    if (strpos((string) $escaped_text, '@') === false) {
        return $escaped_text;
    }

    $pattern = pro_tickets_mention_pattern(true);
    if (!$pattern) {
        return $escaped_text;
    }

    // Single pass over the whole string — re-running per name would nest a
    // shorter match inside a chip that was already written.
    return preg_replace($pattern, '<span class="ptk-mention">$0</span>', $escaped_text);
}

/* ─────────────────────────── Auto-assignment ────────────────────────────── */

/**
 * Pick a staff member for a ticket according to the configured strategy.
 * Candidates are active staff assigned to the ticket's department
 * (tblstaff_departments); when the department has none, any active staff
 * member qualifies.
 *
 * round_robin — the candidate whose latest assigned ticket is oldest.
 * least_busy  — the candidate with the fewest open assigned tickets.
 *
 * @return int staff id or 0 when no candidate / strategy off
 */
function pro_tickets_pick_assignee($ticket)
{
    $strategy = get_option('pro_tickets_auto_assign');
    if ($strategy !== 'round_robin' && $strategy !== 'least_busy') {
        return 0;
    }

    $CI   = &get_instance();
    $p    = db_prefix();
    $dept = (int) $ticket->department;

    // 1) Explicit Pro Tickets department roster (this tenant's own config).
    $candidates = [];
    if ($CI->db->table_exists($p . 'pro_tickets_dept_agents')) {
        $candidates = $CI->db->query(
            "SELECT da.staff_id AS staffid FROM `{$p}pro_tickets_dept_agents` da
             JOIN `{$p}staff` s ON s.staffid = da.staff_id AND s.active = 1
             WHERE da.department_id = ?",
            [$dept]
        )->result_array();
    }

    // 2) Core staff↔department assignments.
    if (empty($candidates)) {
        $candidates = $CI->db->query(
            "SELECT s.staffid FROM `{$p}staff` s
             JOIN `{$p}staff_departments` sd ON sd.staffid = s.staffid
             WHERE s.active = 1 AND sd.departmentid = ?",
            [$dept]
        )->result_array();
    }

    // 3) Everyone active (non-admins first, then anyone).
    if (empty($candidates)) {
        $candidates = $CI->db->query("SELECT staffid FROM `{$p}staff` WHERE active = 1 AND admin != 1")->result_array();
    }
    if (empty($candidates)) {
        $candidates = $CI->db->query("SELECT staffid FROM `{$p}staff` WHERE active = 1")->result_array();
    }
    if (empty($candidates)) {
        return 0;
    }

    // Role-wise constraint: only auto-assign within the configured role. Applied
    // as an intersection with the pool above; if that empties the pool, fall
    // back to any active staff holding the role so a ticket still gets routed.
    $role = (int) get_option('pro_tickets_auto_assign_role');
    if ($role > 0) {
        $pool_ids = implode(',', array_map('intval', array_column($candidates, 'staffid')));
        $roled    = $CI->db->query(
            "SELECT staffid FROM `{$p}staff` WHERE active = 1 AND role = ? AND staffid IN ($pool_ids)",
            [$role]
        )->result_array();
        if (empty($roled)) {
            $roled = $CI->db->query(
                "SELECT staffid FROM `{$p}staff` WHERE active = 1 AND role = ?",
                [$role]
            )->result_array();
        }
        if (!empty($roled)) {
            $candidates = $roled;
        }
    }

    $ids = implode(',', array_map('intval', array_column($candidates, 'staffid')));

    if ($strategy === 'least_busy') {
        $row = $CI->db->query(
            "SELECT s.staffid, COALESCE(t.cnt, 0) AS cnt
             FROM `{$p}staff` s
             LEFT JOIN (
                SELECT assigned, COUNT(*) AS cnt FROM `{$p}tickets`
                WHERE status != 5 GROUP BY assigned
             ) t ON t.assigned = s.staffid
             WHERE s.staffid IN ($ids)
             ORDER BY cnt ASC, s.staffid ASC LIMIT 1"
        )->row();
    } else {
        $row = $CI->db->query(
            "SELECT s.staffid, COALESCE(MAX(t.date), '1970-01-01') AS last_assigned
             FROM `{$p}staff` s
             LEFT JOIN `{$p}tickets` t ON t.assigned = s.staffid
             WHERE s.staffid IN ($ids)
             GROUP BY s.staffid
             ORDER BY last_assigned ASC, s.staffid ASC LIMIT 1"
        )->row();
    }

    return $row ? (int) $row->staffid : 0;
}

/**
 * Assign an unassigned ticket via the configured strategy, notify the staff
 * member and log the action.
 *
 * @return int the chosen staff id (0 = nothing assigned)
 */
function pro_tickets_auto_assign_ticket($ticket)
{
    $staff_id = pro_tickets_pick_assignee($ticket);
    if (!$staff_id) {
        return 0;
    }

    $CI = &get_instance();
    $CI->db->where('ticketid', $ticket->ticketid)->update(db_prefix() . 'tickets', ['assigned' => $staff_id]);

    pro_tickets_log($ticket->ticketid, 'auto_assigned', get_staff_full_name($staff_id));
    pro_tickets_notify($staff_id, 'pro_tickets_not_auto_assigned', $ticket->ticketid, $ticket->subject);

    pro_tickets_fire_omni('pro_ticket_assigned', $ticket->ticketid, [
        'assigned_by'   => pro_tickets_omni_l('pro_tickets_hook_assign_auto'),
        'assign_reason' => get_option('pro_tickets_auto_assign') === 'least_busy'
            ? pro_tickets_omni_l('pro_tickets_set_auto_assign_lb')
            : pro_tickets_omni_l('pro_tickets_set_auto_assign_rr'),
    ]);

    return $staff_id;
}

/* ───────────────────────── Core-hook handlers ───────────────────────────── */

/**
 * ticket_created — runs for every channel (admin, portal, email pipe).
 * Creates the SLA meta row and auto-assigns when unassigned.
 */
function pro_tickets_on_ticket_created($ticket_id)
{
    pro_tickets_ensure_schema();
    $meta = pro_tickets_ensure_meta($ticket_id);
    pro_tickets_log($ticket_id, 'created', '', function_exists('get_staff_user_id') && is_staff_logged_in() ? get_staff_user_id() : null);

    $CI     = &get_instance();
    $ticket = $CI->db->where('ticketid', (int) $ticket_id)->get(db_prefix() . 'tickets')->row();
    if ($ticket && (int) $ticket->assigned === 0 && $ticket->status != 5) {
        pro_tickets_auto_assign_ticket($ticket);
    }

    // Fired last so the acknowledgement can name the agent auto-assignment
    // just picked.
    pro_tickets_fire_omni('pro_ticket_created', $ticket_id, [
        'ticket_message'         => pro_tickets_omni_excerpt($ticket->message ?? ''),
        'sla_first_response_due' => ($meta && $meta->frt_due) ? _dt($meta->frt_due) : '',
        'sla_resolution_due'     => ($meta && $meta->res_due) ? _dt($meta->res_due) : '',
    ]);
}

/**
 * after_ticket_reply_added — stamps the first staff response for SLA FRT
 * tracking, logs the reply and keeps watchers in the loop on client replies.
 */
function pro_tickets_on_reply_added($params)
{
    $ticket_id = $params['id'] ?? 0;
    if (!$ticket_id) {
        return;
    }

    pro_tickets_ensure_schema();
    $meta = pro_tickets_ensure_meta($ticket_id);
    if (!$meta) {
        return;
    }

    $CI    = &get_instance();
    $p     = db_prefix();
    $admin = $params['admin'] ?? null;

    $reply_excerpt = pro_tickets_omni_excerpt($params['data']['message'] ?? '');

    if ($admin !== null) {
        pro_tickets_log($ticket_id, 'staff_reply', '', (int) $admin);

        // @mentions live in the reply body — handled here (rather than in the
        // module controller) so replies posted from the core ticket screens
        // tag colleagues just the same.
        pro_tickets_notify_mentions($ticket_id, $params['data']['message'] ?? '', 'reply', (int) $admin);

        if (!$meta->first_replied_at) {
            $now    = date('Y-m-d H:i:s');
            $update = ['first_replied_at' => $now];
            if ($meta->frt_due && strtotime($now) > strtotime($meta->frt_due)) {
                $update['frt_breached'] = 1;
            }
            $CI->db->where('ticket_id', (int) $ticket_id)->update($p . 'pro_tickets_meta', $update);
        }

        pro_tickets_fire_omni('pro_ticket_staff_reply', $ticket_id, [
            'reply_by'      => get_staff_full_name((int) $admin),
            'reply_excerpt' => $reply_excerpt,
        ]);
    } else {
        pro_tickets_log($ticket_id, 'client_reply', '');

        $ticket = $CI->db->where('ticketid', (int) $ticket_id)->get($p . 'tickets')->row();
        if ($ticket) {
            pro_tickets_notify_watchers($ticket_id, 'pro_tickets_not_client_reply', $ticket->subject, [(int) $ticket->assigned]);
        }

        pro_tickets_fire_omni('pro_ticket_customer_reply', $ticket_id, function ($d) use ($reply_excerpt) {
            return [
                'reply_by'      => $d['customer_name'],
                'reply_excerpt' => $reply_excerpt,
            ];
        });
    }
}

/**
 * after_ticket_status_changed — tracks resolution and reopens.
 */
function pro_tickets_on_status_changed($params)
{
    $ticket_id = $params['id'] ?? 0;
    $status    = (int) ($params['status'] ?? 0);
    if (!$ticket_id) {
        return;
    }

    pro_tickets_ensure_schema();
    $meta = pro_tickets_ensure_meta($ticket_id);
    if (!$meta) {
        return;
    }

    $CI = &get_instance();
    $p  = db_prefix();

    $staff_id = (function_exists('is_staff_logged_in') && is_staff_logged_in()) ? get_staff_user_id() : null;
    pro_tickets_log($ticket_id, 'status_changed', (string) $status, $staff_id);

    $changed_by = $staff_id !== null ? get_staff_full_name((int) $staff_id) : '';
    $closed     = false;
    $reopened   = false;

    if ($status === 5) {
        $now    = date('Y-m-d H:i:s');
        $update = [
            'resolved_at'     => $now,
            // 0 = the customer closed it themselves, otherwise the staff id —
            // the client portal words the feedback prompt accordingly.
            'closed_by_staff' => $staff_id !== null ? (int) $staff_id : 0,
        ];
        if ($meta->res_due && strtotime($now) > strtotime($meta->res_due)) {
            $update['res_breached'] = 1;
        }
        $CI->db->where('ticket_id', (int) $ticket_id)->update($p . 'pro_tickets_meta', $update);
        $closed = true;
    } elseif ($meta->resolved_at) {
        // Reopened after being closed.
        $CI->db->where('ticket_id', (int) $ticket_id)->update($p . 'pro_tickets_meta', [
            'resolved_at'     => null,
            'auto_closed'     => 0,
            'closed_by_staff' => null,
            'reopened_count'  => $meta->reopened_count + 1,
        ]);
        pro_tickets_log($ticket_id, 'reopened', '', $staff_id);
        $reopened = true;
    }

    $ticket = $CI->db->where('ticketid', (int) $ticket_id)->get($p . 'tickets')->row();
    if ($ticket) {
        pro_tickets_notify_watchers($ticket_id, 'pro_tickets_not_status_changed', $ticket->subject, $staff_id ? [$staff_id] : []);
    }

    pro_tickets_fire_omni('pro_ticket_status_changed', $ticket_id, ['changed_by' => $changed_by]);

    if ($closed) {
        $minutes  = $ticket ? max(0, (int) round((time() - strtotime($ticket->date)) / 60)) : 0;
        $breached = $meta->res_breached || isset($update['res_breached']);

        pro_tickets_fire_omni('pro_ticket_closed', $ticket_id, function ($d) use ($changed_by, $minutes, $breached) {
            return [
                // No staff context = the customer closed their own ticket.
                'closed_by'       => $changed_by !== '' ? $changed_by : $d['customer_name'],
                'resolution_time' => pro_tickets_human_duration($minutes),
                'sla_met'         => $breached ? pro_tickets_omni_l('pro_tickets_hook_sla_met_no') : pro_tickets_omni_l('pro_tickets_hook_sla_met_yes'),
            ];
        });
    } elseif ($reopened) {
        pro_tickets_fire_omni('pro_ticket_reopened', $ticket_id, [
            'reopened_count' => (int) $meta->reopened_count + 1,
            'changed_by'     => $changed_by,
        ]);
    }
}

/**
 * after_ticket_deleted — purge extension rows.
 */
function pro_tickets_on_ticket_deleted($ticket_id)
{
    $CI = &get_instance();
    $p  = db_prefix();
    foreach (['pro_tickets_meta', 'pro_tickets_activity', 'pro_tickets_watchers', 'pro_tickets_notes'] as $table) {
        if ($CI->db->table_exists($p . $table)) {
            $CI->db->where('ticket_id', (int) $ticket_id)->delete($p . $table);
        }
    }
}

/* ─────────────────────────── Cron automation ────────────────────────────── */

/**
 * The automation pipeline, piggy-backed on the core cron (after_cron_run) and
 * runnable manually from Settings. Throttled to once every 2 minutes.
 *
 * 1. Backfill meta rows for tickets that predate the module (silent).
 * 2. Auto-assign any unassigned open tickets (safety net for piped tickets
 *    created while the strategy was off).
 * 3. SLA warnings when N% of the window is consumed.
 * 4. SLA breach detection + escalation (notify, optional priority bump,
 *    optional escalation contact per policy).
 * 5. Auto-close answered tickets idle for N days.
 */
function pro_tickets_run_automation($manual = false)
{
    if (!$manual) {
        $last = (int) get_option('pro_tickets_cron_last_run');
        if (time() - $last < 120) {
            return;
        }
    }
    update_option('pro_tickets_cron_last_run', time());

    pro_tickets_ensure_schema();

    $CI = &get_instance();
    $p  = db_prefix();

    // ── 1. Backfill meta for tickets created before activation ──
    $missing = $CI->db->query(
        "SELECT t.ticketid FROM `{$p}tickets` t
         LEFT JOIN `{$p}pro_tickets_meta` m ON m.ticket_id = t.ticketid
         WHERE m.id IS NULL LIMIT 200"
    )->result_array();
    foreach ($missing as $row) {
        pro_tickets_ensure_meta($row['ticketid'], true);
    }

    // ── 2. Auto-assign unassigned open tickets ──
    if (in_array(get_option('pro_tickets_auto_assign'), ['round_robin', 'least_busy'])) {
        $unassigned = $CI->db->query(
            "SELECT * FROM `{$p}tickets` WHERE assigned = 0 AND status != 5 AND merged_ticket_id IS NULL LIMIT 50"
        )->result();
        foreach ($unassigned as $ticket) {
            pro_tickets_auto_assign_ticket($ticket);
        }
    }

    $now = time();

    // ── 3. SLA warnings ──
    $warnPct = (int) get_option('pro_tickets_sla_warning_pct');
    if ($warnPct > 0 && $warnPct < 100) {
        $candidates = $CI->db->query(
            "SELECT t.ticketid, t.subject, t.assigned, t.date, m.*
             FROM `{$p}pro_tickets_meta` m
             JOIN `{$p}tickets` t ON t.ticketid = m.ticket_id
             WHERE t.status != 5 AND m.sla_warned = 0
               AND (m.frt_due IS NOT NULL OR m.res_due IS NOT NULL) LIMIT 200"
        )->result();

        foreach ($candidates as $c) {
            $due = (!$c->first_replied_at && $c->frt_due) ? $c->frt_due : $c->res_due;
            if (!$due) {
                continue;
            }
            $start = strtotime($c->date);
            $end   = strtotime($due);
            if ($end <= $start || $now > $end) {
                continue; // invalid window or already past due (breach handles it)
            }
            if ((($now - $start) / ($end - $start)) * 100 >= $warnPct) {
                $CI->db->where('ticket_id', $c->ticket_id)->update($p . 'pro_tickets_meta', ['sla_warned' => 1]);
                pro_tickets_log($c->ticket_id, 'sla_warning', '');
                pro_tickets_notify((int) $c->assigned, 'pro_tickets_not_sla_warning', $c->ticket_id, $c->subject);
                pro_tickets_notify_watchers($c->ticket_id, 'pro_tickets_not_sla_warning', $c->subject, [(int) $c->assigned]);

                pro_tickets_fire_omni('pro_ticket_sla_warning', $c->ticket_id, [
                    'sla_stage' => (!$c->first_replied_at && $c->frt_due)
                        ? pro_tickets_omni_l('pro_tickets_hook_sla_frt')
                        : pro_tickets_omni_l('pro_tickets_hook_sla_res'),
                    'sla_due'   => _dt($due),
                    'time_left' => pro_tickets_human_duration((int) round(($end - $now) / 60)),
                ]);
            }
        }
    }

    // ── 4. Breaches + escalation ──
    $breaches = $CI->db->query(
        "SELECT t.ticketid, t.subject, t.assigned, t.priority, m.*
         FROM `{$p}pro_tickets_meta` m
         JOIN `{$p}tickets` t ON t.ticketid = m.ticket_id
         WHERE t.status != 5 AND (
            (m.frt_breached = 0 AND m.first_replied_at IS NULL AND m.frt_due IS NOT NULL AND m.frt_due < NOW())
            OR
            (m.res_breached = 0 AND m.res_due IS NOT NULL AND m.res_due < NOW())
         ) LIMIT 200"
    )->result();

    $bumpPriority = get_option('pro_tickets_bump_priority_on_breach') == '1';
    $maxPriority  = (int) ($CI->db->query("SELECT MAX(priorityid) AS mx FROM `{$p}tickets_priorities`")->row()->mx ?? 3);

    foreach ($breaches as $b) {
        $update = [];
        $type   = '';
        if (!$b->frt_breached && !$b->first_replied_at && $b->frt_due && strtotime($b->frt_due) < $now) {
            $update['frt_breached'] = 1;
            $type = 'frt';
        }
        if (!$b->res_breached && $b->res_due && strtotime($b->res_due) < $now) {
            $update['res_breached'] = 1;
            $type = $type ? 'both' : 'res';
        }
        if (!$update) {
            continue;
        }
        $CI->db->where('ticket_id', $b->ticket_id)->update($p . 'pro_tickets_meta', $update);
        pro_tickets_log($b->ticket_id, 'sla_breach', $type);

        pro_tickets_notify((int) $b->assigned, 'pro_tickets_not_sla_breach', $b->ticket_id, $b->subject);
        pro_tickets_notify_watchers($b->ticket_id, 'pro_tickets_not_sla_breach', $b->subject, [(int) $b->assigned]);

        $breach_due = ($type === 'frt') ? $b->frt_due : $b->res_due;
        pro_tickets_fire_omni('pro_ticket_sla_breached', $b->ticket_id, [
            'sla_type'   => pro_tickets_omni_l('pro_tickets_hook_sla_' . $type),
            'sla_due'    => $breach_due ? _dt($breach_due) : '',
            'overdue_by' => $breach_due
                ? pro_tickets_human_duration((int) round(($now - strtotime($breach_due)) / 60))
                : '',
        ]);

        if (!$b->escalated) {
            $CI->db->where('ticket_id', $b->ticket_id)->update($p . 'pro_tickets_meta', ['escalated' => 1]);

            // Escalation contact from the matched SLA policy.
            if ($b->sla_id) {
                $sla = $CI->db->where('id', $b->sla_id)->get($p . 'pro_tickets_sla')->row();
                if ($sla && $sla->escalate_to && (int) $sla->escalate_to !== (int) $b->assigned) {
                    pro_tickets_notify((int) $sla->escalate_to, 'pro_tickets_not_escalated', $b->ticket_id, $b->subject);

                    $esc = $CI->db->select('firstname, lastname, email, phonenumber')
                        ->where('staffid', (int) $sla->escalate_to)
                        ->get($p . 'staff')->row();

                    pro_tickets_fire_omni('pro_ticket_escalated', $b->ticket_id, [
                        'sla_type'          => pro_tickets_omni_l('pro_tickets_hook_sla_' . $type),
                        'escalate_to_name'  => $esc ? trim($esc->firstname . ' ' . $esc->lastname) : '',
                        'escalate_to_email' => $esc ? (string) $esc->email : '',
                        'escalate_to_mobile'=> $esc ? (string) $esc->phonenumber : '',
                    ]);
                }
            }

            if ($bumpPriority && (int) $b->priority < $maxPriority) {
                $CI->db->where('ticketid', $b->ticket_id)->update($p . 'tickets', ['priority' => $maxPriority]);
                pro_tickets_log($b->ticket_id, 'priority_escalated', (string) $maxPriority);
            }
        }
    }

    // ── 5. Auto-close answered tickets idle for N days ──
    if (get_option('pro_tickets_auto_close_enabled') == '1') {
        $days = (int) get_option('pro_tickets_auto_close_days');
        if ($days > 0) {
            $idle = $CI->db->query(
                "SELECT ticketid, subject, assigned FROM `{$p}tickets`
                 WHERE status = 3 AND merged_ticket_id IS NULL
                   AND COALESCE(lastreply, date) < DATE_SUB(NOW(), INTERVAL {$days} DAY)
                 LIMIT 50"
            )->result();

            if ($idle) {
                $CI->load->model('tickets_model');
                foreach ($idle as $t) {
                    // change_ticket_status fires after_ticket_status_changed →
                    // resolved_at is stamped by our own handler above.
                    $CI->tickets_model->change_ticket_status($t->ticketid, 5);
                    $CI->db->where('ticket_id', $t->ticketid)->update($p . 'pro_tickets_meta', ['auto_closed' => 1]);
                    pro_tickets_log($t->ticketid, 'auto_closed', $days . 'd');
                    pro_tickets_notify((int) $t->assigned, 'pro_tickets_not_auto_closed', $t->ticketid, $t->subject);

                    pro_tickets_fire_omni('pro_ticket_auto_closed', $t->ticketid, ['idle_days' => $days]);
                }
            }
        }
    }
}

/* ─────────────────────────── Clickable links ────────────────────────────── */

/**
 * Turn bare URLs / e-mail addresses inside a message body into real links.
 *
 * Ticket bodies reach the admin thread as raw HTML, and the parts that were
 * typed rather than composed in the editor (Smart Ticket reports, plain-text
 * e-mail replies, pasted page URLs) arrive html-escaped — so a URL shows as
 * dead text the agent has to select and copy. The client portal already runs
 * every message through core's linkifier; this is the same idea for the
 * helpdesk side, but tag-aware.
 *
 * The markup is walked tag by tag rather than regex-replaced whole, so:
 *   - anything inside an existing <a> is left alone (no nested anchors),
 *   - attribute values are never touched (a tag is copied through verbatim),
 *   - <code>/<pre>/<script>/<style> blocks keep their text as text.
 *
 * Input is assumed to be display-ready HTML — nothing is escaped or stripped
 * here, only anchors are introduced.
 *
 * @param  string $html message body
 * @return string
 */
function pro_tickets_linkify($html)
{
    $html = (string) $html;

    if ($html === ''
        || (stripos($html, 'http') === false
            && stripos($html, 'www.') === false
            && strpos($html, '@') === false)) {
        return $html;
    }

    $parts = preg_split('/(<[^>]*>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    if ($parts === false) {
        return $html;
    }

    $skip = 0;
    $out  = '';

    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }

        if ($part[0] === '<') {
            if (preg_match('~^<\s*/\s*(a|code|pre|script|style|textarea)\b~i', $part)) {
                $skip = max(0, $skip - 1);
            } elseif (preg_match('~^<\s*(a|code|pre|script|style|textarea)\b~i', $part)
                && substr(rtrim($part), -2) !== '/>') {
                $skip++;
            }

            $out .= $part;
            continue;
        }

        $out .= $skip > 0 ? $part : pro_tickets_linkify_text($part);
    }

    return $out;
}

/**
 * Linkify one plain-text run of an already-escaped body. Kept separate from
 * pro_tickets_linkify() only so the tag walker above stays readable — call
 * that one, not this.
 *
 * @param  string $text
 * @return string
 */
function pro_tickets_linkify_text($text)
{
    // Web addresses: http(s):// or a bare www. host. The URL stops at
    // whitespace and at the characters that can only be markup or prose
    // around it; trailing punctuation is handed back to the sentence.
    $text = preg_replace_callback(
        '~(^|[\s(\[<])((?:https?://|www\.)[^\s<>"\'\]]+)~i',
        function ($m) {
            $lead = $m[1];
            $url  = $m[2];

            // Text nodes carry entities: a following &nbsp; would otherwise be
            // swallowed into the URL, and a trailing &amp;/&hellip; belongs to
            // the prose, not the address.
            $nbsp = stripos($url, '&nbsp;');
            if ($nbsp !== false) {
                $url = substr($url, 0, $nbsp);
            }
            $url = preg_replace('~(&(?:[a-z]+|#\d+);)+$~i', '', $url);

            // Unbalanced closing bracket = the one that wrapped the URL.
            if (substr($url, -1) === ')' && substr_count($url, '(') < substr_count($url, ')')) {
                $url = substr($url, 0, -1);
            }

            $url  = rtrim($url, '.,;:!?');
            $tail = substr($m[2], strlen($url));

            if ($url === '' || preg_match('~^(?:https?://|www\.)[^a-z0-9]*$~i', $url)) {
                return $m[0];
            }

            $href = (stripos($url, 'www.') === 0) ? 'https://' . $url : $url;

            return $lead . pro_tickets_link_html($href, $url) . $tail;
        },
        $text
    );

    // E-mail addresses. "@Full Name" mentions have no local part, so the
    // required character before the @ keeps them out of this.
    return preg_replace_callback(
        '~(^|[\s(\[<])([A-Z0-9._%+-]+@(?:[A-Z0-9-]+\.)+[A-Z]{2,})~i',
        function ($m) {
            $email = rtrim($m[2], '.');

            return $m[1] . pro_tickets_link_html('mailto:' . $email, $email);
        },
        $text
    );
}

/**
 * One anchor, written the same way everywhere: opens in a new tab, never
 * leaks the helpdesk URL as a referrer, and keeps the full address as its
 * text so it stays copy-pasteable.
 *
 * @param  string $href  already-escaped href
 * @param  string $label already-escaped link text
 * @return string
 */
function pro_tickets_link_html($href, $label)
{
    return '<a href="' . $href . '" class="ptk-link" target="_blank" rel="noopener noreferrer nofollow">'
        . $label . '</a>';
}
