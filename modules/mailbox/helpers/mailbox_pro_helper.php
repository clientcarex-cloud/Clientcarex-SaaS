<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Mailbox — corporate engine.
 *
 * Everything that turns the plain webmail into a governed team mailbox lives
 * here: the extended schema, the shared-inbox primitives (assignment, status,
 * internal notes, presence), the incoming-mail rules engine, the out-of-office
 * responder, first-response SLA timers, the scheduled/undo send dispatcher,
 * outbound DLP screening, the compliance archive BCC, the audit trail and the
 * retention purge.
 *
 * Loaded unconditionally next to mailbox_helper.php — cron, the admin area and
 * the sync engine all reach into it.
 */

/* ═══════════════════════════ Options access ══════════════════════════════ */

/**
 * Option reader with a real default.
 *
 * Core get_option() returns '' (never false) for a missing option, so a plain
 * `?:` on a legitimately-zero value would silently fall back to the default.
 * This one only substitutes the default when the option is genuinely absent.
 */
function mailbox_opt($key, $default = '')
{
    $value = get_option($key);

    return ($value === '' || $value === null) ? $default : $value;
}

/**
 * Every option this module owns, with its default. Used by the installer and
 * by the settings screen so a fresh tenant and an upgraded one behave alike.
 */
function mailbox_pro_option_defaults()
{
    return [
        // Shared inbox
        'mailbox_status_enabled'      => '1',
        'mailbox_presence_enabled'    => '1',
        // SLA
        'mailbox_sla_enabled'         => '0',
        'mailbox_sla_hours'           => '4',
        'mailbox_sla_business_only'   => '0',
        'mailbox_sla_notify_admins'   => '1',
        // Sending
        'mailbox_undo_send_seconds'   => '10',
        'mailbox_schedule_enabled'    => '1',
        // Compliance
        'mailbox_compliance_bcc'      => '',
        'mailbox_dlp_enabled'         => '0',
        'mailbox_dlp_mode'            => 'warn',      // warn | block
        'mailbox_dlp_keywords'        => '',
        'mailbox_dlp_detect_cards'    => '0',
        'mailbox_audit_enabled'       => '1',
        'mailbox_audit_retention_days' => '365',
        'mailbox_retention_days'      => '0',         // 0 = keep forever
        'mailbox_retention_folders'   => 'trash',
        // Automation
        'mailbox_rules_enabled'       => '1',
        'mailbox_autoreply_enabled'   => '1',
    ];
}

/* ═════════════════════════════ Schema ════════════════════════════════════ */

/**
 * Add a column only when it is missing — safe to call on every request.
 */
function mailbox_add_column($table, $column, $definition)
{
    $CI = &get_instance();

    if (!$CI->db->field_exists($column, $table)) {
        $CI->db->query("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
    }
}

/**
 * Corporate schema — new tables plus the columns bolted onto the original
 * mailbox_messages / mailbox_accounts tables. Called from
 * mailbox_ensure_schema() so every entry point heals itself.
 */
function mailbox_pro_ensure_schema()
{
    static $ensured = null;
    if ($ensured !== null) {
        return $ensured;
    }

    $CI = &get_instance();
    $p  = db_prefix();

    try {
        /* ── Labels ── */
        if (!$CI->db->table_exists($p . 'mailbox_labels')) {
            $CI->db->query("CREATE TABLE `{$p}mailbox_labels` (
                `id` INT NOT NULL AUTO_INCREMENT,
                `account_id` INT NOT NULL DEFAULT 0,
                `name` VARCHAR(80) NOT NULL,
                `color` VARCHAR(9) DEFAULT '#4f46e5',
                `sort_order` INT DEFAULT 0,
                `created_by` INT DEFAULT 0,
                `created_at` DATETIME NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `account_name` (`account_id`, `name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        }

        if (!$CI->db->table_exists($p . 'mailbox_message_labels')) {
            $CI->db->query("CREATE TABLE `{$p}mailbox_message_labels` (
                `id` INT NOT NULL AUTO_INCREMENT,
                `message_id` INT NOT NULL,
                `label_id` INT NOT NULL,
                `created_at` DATETIME NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `message_label` (`message_id`, `label_id`),
                KEY `label_id` (`label_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        }

        /* ── Internal notes (never leave the CRM) ── */
        if (!$CI->db->table_exists($p . 'mailbox_notes')) {
            $CI->db->query("CREATE TABLE `{$p}mailbox_notes` (
                `id` INT NOT NULL AUTO_INCREMENT,
                `account_id` INT NOT NULL,
                `message_id` INT NOT NULL,
                `thread_id` INT NOT NULL DEFAULT 0,
                `staff_id` INT NOT NULL,
                `note` TEXT NULL,
                `mentions` VARCHAR(255) DEFAULT '',
                `created_at` DATETIME NULL,
                PRIMARY KEY (`id`),
                KEY `message_id` (`message_id`),
                KEY `thread_id` (`thread_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        }

        /* ── Canned response templates ── */
        if (!$CI->db->table_exists($p . 'mailbox_templates')) {
            $CI->db->query("CREATE TABLE `{$p}mailbox_templates` (
                `id` INT NOT NULL AUTO_INCREMENT,
                `account_id` INT NOT NULL DEFAULT 0,
                `name` VARCHAR(150) NOT NULL,
                `subject` VARCHAR(300) DEFAULT '',
                `body_html` LONGTEXT NULL,
                `is_shared` TINYINT(1) DEFAULT 1,
                `usage_count` INT DEFAULT 0,
                `created_by` INT DEFAULT 0,
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL,
                PRIMARY KEY (`id`),
                KEY `account_id` (`account_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        }

        /* ── Incoming-mail rules ── */
        if (!$CI->db->table_exists($p . 'mailbox_rules')) {
            $CI->db->query("CREATE TABLE `{$p}mailbox_rules` (
                `id` INT NOT NULL AUTO_INCREMENT,
                `account_id` INT NOT NULL DEFAULT 0,
                `name` VARCHAR(150) NOT NULL,
                `match_type` VARCHAR(5) DEFAULT 'all',
                `conditions` LONGTEXT NULL,
                `actions` LONGTEXT NULL,
                `active` TINYINT(1) DEFAULT 1,
                `stop_processing` TINYINT(1) DEFAULT 0,
                `sort_order` INT DEFAULT 0,
                `hits` INT DEFAULT 0,
                `last_hit_at` DATETIME NULL,
                `created_by` INT DEFAULT 0,
                `created_at` DATETIME NULL,
                PRIMARY KEY (`id`),
                KEY `account_active` (`account_id`, `active`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        }

        /* ── Audit trail ── */
        if (!$CI->db->table_exists($p . 'mailbox_audit')) {
            $CI->db->query("CREATE TABLE `{$p}mailbox_audit` (
                `id` INT NOT NULL AUTO_INCREMENT,
                `account_id` INT NOT NULL DEFAULT 0,
                `message_id` INT NOT NULL DEFAULT 0,
                `staff_id` INT NOT NULL DEFAULT 0,
                `action` VARCHAR(40) NOT NULL,
                `subject` VARCHAR(300) DEFAULT '',
                `details` TEXT NULL,
                `ip` VARCHAR(45) DEFAULT '',
                `created_at` DATETIME NULL,
                PRIMARY KEY (`id`),
                KEY `created_at` (`created_at`),
                KEY `account_action` (`account_id`, `action`),
                KEY `staff_id` (`staff_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        }

        /* ── Collision detection ── */
        if (!$CI->db->table_exists($p . 'mailbox_presence')) {
            $CI->db->query("CREATE TABLE `{$p}mailbox_presence` (
                `id` INT NOT NULL AUTO_INCREMENT,
                `thread_key` VARCHAR(60) NOT NULL,
                `staff_id` INT NOT NULL,
                `state` VARCHAR(12) DEFAULT 'viewing',
                `updated_at` DATETIME NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `thread_staff` (`thread_key`, `staff_id`),
                KEY `updated_at` (`updated_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        }

        /* ── Auto-responder loop guard ── */
        if (!$CI->db->table_exists($p . 'mailbox_autoreply_log')) {
            $CI->db->query("CREATE TABLE `{$p}mailbox_autoreply_log` (
                `id` INT NOT NULL AUTO_INCREMENT,
                `account_id` INT NOT NULL,
                `to_email` VARCHAR(191) NOT NULL,
                `sent_on` DATE NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `account_to_day` (`account_id`, `to_email`, `sent_on`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        }

        /* ── Columns on the original tables ── */
        $messages = $p . 'mailbox_messages';
        mailbox_add_column($messages, 'assigned_to', 'INT NOT NULL DEFAULT 0');
        mailbox_add_column($messages, 'assigned_at', 'DATETIME NULL');
        mailbox_add_column($messages, 'assigned_by', 'INT NOT NULL DEFAULT 0');
        mailbox_add_column($messages, 'conv_status', "VARCHAR(12) NOT NULL DEFAULT ''");
        mailbox_add_column($messages, 'status_changed_at', 'DATETIME NULL');
        mailbox_add_column($messages, 'status_changed_by', 'INT NOT NULL DEFAULT 0');
        mailbox_add_column($messages, 'first_reply_at', 'DATETIME NULL');
        mailbox_add_column($messages, 'first_reply_by', 'INT NOT NULL DEFAULT 0');
        mailbox_add_column($messages, 'sla_due_at', 'DATETIME NULL');
        mailbox_add_column($messages, 'sla_breached', 'TINYINT(1) NOT NULL DEFAULT 0');
        mailbox_add_column($messages, 'scheduled_at', 'DATETIME NULL');
        mailbox_add_column($messages, 'send_attempts', 'INT NOT NULL DEFAULT 0');
        mailbox_add_column($messages, 'send_error', 'TEXT NULL');
        mailbox_add_column($messages, 'legal_hold', 'TINYINT(1) NOT NULL DEFAULT 0');
        mailbox_add_column($messages, 'rel_type', "VARCHAR(20) NOT NULL DEFAULT ''");
        mailbox_add_column($messages, 'rel_id', 'INT NOT NULL DEFAULT 0');

        $accounts = $p . 'mailbox_accounts';
        mailbox_add_column($accounts, 'oo_enabled', 'TINYINT(1) NOT NULL DEFAULT 0');
        mailbox_add_column($accounts, 'oo_subject', "VARCHAR(255) NOT NULL DEFAULT ''");
        mailbox_add_column($accounts, 'oo_body', 'TEXT NULL');
        mailbox_add_column($accounts, 'oo_from', 'DATE NULL');
        mailbox_add_column($accounts, 'oo_to', 'DATE NULL');
        mailbox_add_column($accounts, 'sla_hours', 'INT NOT NULL DEFAULT 0');
        mailbox_add_column($accounts, 'default_assignee', 'INT NOT NULL DEFAULT 0');
        mailbox_add_column($accounts, 'shared_inbox', 'TINYINT(1) NOT NULL DEFAULT 1');

        // Indexes that only matter once the columns above exist.
        mailbox_ensure_index($messages, 'assigned_status', ['account_id', 'assigned_to', 'conv_status']);
        mailbox_ensure_index($messages, 'scheduled_at', ['scheduled_at']);

        // Seed the options an already-activated install never got from
        // install.php. add_option() is a no-op when the row exists.
        foreach (mailbox_pro_option_defaults() as $key => $value) {
            add_option($key, $value);
        }

        return $ensured = true;
    } catch (Exception $e) {
        log_activity('Mailbox pro schema error: ' . $e->getMessage());

        return $ensured = false;
    }
}

/**
 * Create an index when it is not already present.
 */
function mailbox_ensure_index($table, $name, array $columns)
{
    $CI = &get_instance();

    $existing = $CI->db->query("SHOW INDEX FROM `{$table}` WHERE Key_name = " . $CI->db->escape($name))->row();
    if ($existing) {
        return;
    }

    $cols = implode('`, `', $columns);
    $CI->db->query("ALTER TABLE `{$table}` ADD INDEX `{$name}` (`{$cols}`)");
}

/* ═════════════════════════════ Audit trail ═══════════════════════════════ */

/**
 * Record one auditable event. Never throws — an audit failure must not break
 * the action it is describing.
 *
 * @param string $action  short verb: read, sent, deleted, exported, assigned…
 */
function mailbox_audit($action, array $context = [])
{
    if ((int) mailbox_opt('mailbox_audit_enabled', '1') !== 1) {
        return;
    }

    try {
        $CI = &get_instance();

        $CI->db->insert(db_prefix() . 'mailbox_audit', [
            'account_id' => (int) ($context['account_id'] ?? 0),
            'message_id' => (int) ($context['message_id'] ?? 0),
            'staff_id'   => (int) ($context['staff_id'] ?? (is_staff_logged_in() ? get_staff_user_id() : 0)),
            'action'     => mb_substr((string) $action, 0, 40),
            'subject'    => mb_substr((string) ($context['subject'] ?? ''), 0, 300),
            'details'    => (string) ($context['details'] ?? ''),
            'ip'         => mb_substr((string) ($context['ip'] ?? $CI->input->ip_address()), 0, 45),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    } catch (Exception $e) {
        // Deliberately silent.
    }
}

/**
 * The audit verbs the UI knows how to label and filter on.
 */
function mailbox_audit_actions()
{
    return ['read', 'sent', 'scheduled', 'unscheduled', 'draft_saved', 'assigned', 'status_changed',
        'labelled', 'note_added', 'archived', 'trashed', 'restored', 'deleted', 'exported',
        'downloaded', 'rule_applied', 'autoreplied', 'dlp_blocked', 'sla_breached', 'converted',
        'settings_changed', 'account_changed', 'purged'];
}

/* ══════════════════════ Shared-inbox: presence ═══════════════════════════ */

function mailbox_presence_key($account_id, $thread_id)
{
    return (int) $account_id . ':' . (int) $thread_id;
}

/**
 * Announce that this user is looking at (or replying to) a conversation and
 * return everyone else currently on it. Rows older than the window are pruned
 * lazily so no cron is needed.
 *
 * @return array of ['staff_id', 'full_name', 'state']
 */
function mailbox_presence_touch($account_id, $thread_id, $state = 'viewing')
{
    if ((int) mailbox_opt('mailbox_presence_enabled', '1') !== 1) {
        return [];
    }

    $CI    = &get_instance();
    $p     = db_prefix();
    $key   = mailbox_presence_key($account_id, $thread_id);
    $staff = (int) get_staff_user_id();
    $state = in_array($state, ['viewing', 'replying', 'gone'], true) ? $state : 'viewing';
    $now   = date('Y-m-d H:i:s');

    // 90 s of slack: the client heartbeats every 20 s.
    $CI->db->query(
        "DELETE FROM `{$p}mailbox_presence` WHERE updated_at < DATE_SUB(NOW(), INTERVAL 90 SECOND)"
    );

    if ($state === 'gone') {
        $CI->db->where('thread_key', $key)->where('staff_id', $staff)->delete($p . 'mailbox_presence');
    } else {
        $CI->db->query(
            "INSERT INTO `{$p}mailbox_presence` (thread_key, staff_id, state, updated_at)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE state = VALUES(state), updated_at = VALUES(updated_at)",
            [$key, $staff, $state, $now]
        );
    }

    return $CI->db->query(
        "SELECT pr.staff_id, pr.state, CONCAT(st.firstname, ' ', st.lastname) AS full_name
         FROM `{$p}mailbox_presence` pr
         JOIN `{$p}staff` st ON st.staffid = pr.staff_id
         WHERE pr.thread_key = ? AND pr.staff_id != ?
         ORDER BY pr.updated_at DESC",
        [$key, $staff]
    )->result();
}

/* ═══════════════════════════ SLA timers ══════════════════════════════════ */

/**
 * Effective first-response SLA for an account, in hours. A per-account value
 * wins over the global one; 0 anywhere means "no SLA".
 */
function mailbox_sla_hours($account)
{
    if ((int) mailbox_opt('mailbox_sla_enabled', '0') !== 1) {
        return 0;
    }

    $account_hours = (int) ($account->sla_hours ?? 0);

    return $account_hours > 0 ? $account_hours : (int) mailbox_opt('mailbox_sla_hours', '4');
}

/**
 * Stamp the response deadline on a freshly imported inbound message.
 */
function mailbox_sla_start($account, $message_id, $message_date)
{
    $hours = mailbox_sla_hours($account);
    if ($hours <= 0) {
        return;
    }

    $CI  = &get_instance();
    $due = date('Y-m-d H:i:s', strtotime($message_date . ' +' . $hours . ' hours'));

    $CI->db->where('id', (int) $message_id)
        ->update(db_prefix() . 'mailbox_messages', ['sla_due_at' => $due]);
}

/**
 * A reply went out — stop the clock on the message it answers and on every
 * earlier unanswered message of the same conversation.
 */
function mailbox_sla_stop($account_id, $source_message, $staff_id = null)
{
    if (!$source_message) {
        return;
    }

    $CI  = &get_instance();
    $p   = db_prefix();
    $now = date('Y-m-d H:i:s');

    // The dispatcher runs from cron, where there is no session to read the
    // replier from — callers pass the stored sender instead.
    if ($staff_id === null) {
        $staff_id = is_staff_logged_in() ? get_staff_user_id() : 0;
    }

    $thread = (int) ($source_message->thread_id ?: $source_message->id);

    $CI->db->query(
        "UPDATE `{$p}mailbox_messages`
         SET first_reply_at = ?, first_reply_by = ?, sla_due_at = NULL
         WHERE account_id = ? AND thread_id = ? AND folder = 'inbox' AND first_reply_at IS NULL",
        [$now, (int) $staff_id, (int) $account_id, $thread]
    );
}

/**
 * Cron pass: flag everything that blew its deadline and tell the people who
 * can still do something about it.
 */
function mailbox_sla_scan()
{
    if ((int) mailbox_opt('mailbox_sla_enabled', '0') !== 1) {
        return 0;
    }

    $CI = &get_instance();
    $p  = db_prefix();

    $breached = $CI->db->query(
        "SELECT m.id, m.account_id, m.subject, m.from_email, m.assigned_to, a.name AS account_name
         FROM `{$p}mailbox_messages` m
         JOIN `{$p}mailbox_accounts` a ON a.id = m.account_id
         WHERE m.folder = 'inbox' AND m.sla_breached = 0 AND m.first_reply_at IS NULL
           AND m.sla_due_at IS NOT NULL AND m.sla_due_at < NOW()
           AND m.conv_status != 'closed'
         LIMIT 100"
    )->result();

    if (!count($breached)) {
        return 0;
    }

    $notify_admins = (int) mailbox_opt('mailbox_sla_notify_admins', '1') === 1;

    foreach ($breached as $row) {
        $CI->db->where('id', $row->id)->update($p . 'mailbox_messages', ['sla_breached' => 1]);

        mailbox_audit('sla_breached', [
            'account_id' => $row->account_id,
            'message_id' => $row->id,
            'staff_id'   => 0,
            'subject'    => $row->subject,
            'details'    => 'No reply within the SLA window (from ' . $row->from_email . ')',
        ]);

        $targets = [];
        if ((int) $row->assigned_to > 0) {
            $targets[] = (int) $row->assigned_to;
        } elseif ($notify_admins) {
            foreach ($CI->db->query("SELECT staffid FROM `{$p}staff` WHERE admin = 1 AND active = 1")->result() as $s) {
                $targets[] = (int) $s->staffid;
            }
        }

        foreach (array_unique($targets) as $staff_id) {
            add_notification([
                'description'     => 'mailbox_notification_sla_breached',
                'touserid'        => $staff_id,
                'fromcompany'     => 1,
                'fromuserid'      => 0,
                'additional_data' => serialize([
                    ($row->subject !== '' ? $row->subject : '(no subject)'),
                    $row->account_name,
                ]),
                'link' => 'mailbox?open=' . (int) $row->id,
            ]);
        }
    }

    return count($breached);
}

/* ═══════════════════════════ Rules engine ════════════════════════════════ */

/**
 * Fields a rule can test, in the order the settings UI lists them.
 */
function mailbox_rule_fields()
{
    return ['from_email', 'from_name', 'to', 'cc', 'subject', 'body', 'has_attachment', 'any_recipient'];
}

function mailbox_rule_operators()
{
    return ['contains', 'not_contains', 'equals', 'starts_with', 'ends_with', 'regex', 'is_true', 'is_false'];
}

/**
 * Pull the comparable text for one rule field out of a stored message row.
 */
function mailbox_rule_field_value($field, array $row)
{
    switch ($field) {
        case 'from_email':     return (string) $row['from_email'];
        case 'from_name':      return (string) $row['from_name'];
        case 'subject':        return (string) $row['subject'];
        case 'body':
            // body_plain is NULL for HTML-only mail — fall back to the
            // stripped HTML so a body rule still sees the text.
            $plain = trim((string) ($row['body_plain'] ?? ''));

            return $plain !== '' ? $plain : strip_tags((string) ($row['body_html'] ?? ''));
        case 'has_attachment': return !empty($row['has_attachments']) ? '1' : '';
        case 'to':             return mailbox_json_emails($row['to_emails'] ?? '[]');
        case 'cc':             return mailbox_json_emails($row['cc_emails'] ?? '[]');
        case 'any_recipient':  return mailbox_json_emails($row['to_emails'] ?? '[]') . ' ' . mailbox_json_emails($row['cc_emails'] ?? '[]');
    }

    return '';
}

/**
 * JSON address blob → space separated "name email" haystack.
 */
function mailbox_json_emails($json)
{
    $out = [];
    foreach ((array) json_decode((string) $json, true) as $entry) {
        if (is_array($entry)) {
            $out[] = trim(($entry['name'] ?? '') . ' ' . ($entry['email'] ?? ''));
        }
    }

    return implode(' ', $out);
}

/**
 * Evaluate one condition against a message row.
 */
function mailbox_rule_condition_matches(array $condition, array $row)
{
    $haystack = mb_strtolower(mailbox_rule_field_value($condition['field'] ?? '', $row));
    $needle   = mb_strtolower(trim((string) ($condition['value'] ?? '')));
    $op       = $condition['op'] ?? 'contains';

    switch ($op) {
        case 'is_true':      return $haystack !== '';
        case 'is_false':     return $haystack === '';
        case 'equals':       return $haystack === $needle;
        case 'not_contains': return $needle === '' || mb_strpos($haystack, $needle) === false;
        case 'starts_with':  return $needle !== '' && mb_strpos($haystack, $needle) === 0;
        case 'ends_with':    return $needle !== '' && mb_substr($haystack, -mb_strlen($needle)) === $needle;
        case 'regex':
            if ($needle === '') {
                return false;
            }
            // User-authored pattern: a bad one must not fatal the sync.
            $result = @preg_match('#' . str_replace('#', '\#', $condition['value']) . '#i', $haystack);

            return $result === 1;
        case 'contains':
        default:
            return $needle !== '' && mb_strpos($haystack, $needle) !== false;
    }
}

/**
 * Does this rule fire for this message?
 */
function mailbox_rule_matches($rule, array $row)
{
    $conditions = (array) json_decode((string) $rule->conditions, true);
    $conditions = array_values(array_filter($conditions, function ($c) {
        return is_array($c) && !empty($c['field']);
    }));

    if (!count($conditions)) {
        return false;
    }

    $all = ($rule->match_type ?? 'all') === 'all';

    foreach ($conditions as $condition) {
        $hit = mailbox_rule_condition_matches($condition, $row);
        if ($all && !$hit) {
            return false;
        }
        if (!$all && $hit) {
            return true;
        }
    }

    return $all;
}

/**
 * Run the account's active rules over a freshly imported message.
 *
 * Actions understood: labels[], assign_to, status, mark_read, star, archive,
 * trash, forward_to, auto_reply (template id), notify (staff id).
 *
 * @return array the actions that were actually applied (for the rule tester)
 */
function mailbox_apply_rules($account, $message_id, array $row)
{
    if ((int) mailbox_opt('mailbox_rules_enabled', '1') !== 1) {
        return [];
    }

    $CI = &get_instance();
    $p  = db_prefix();

    $rules = $CI->db->query(
        "SELECT * FROM `{$p}mailbox_rules`
         WHERE active = 1 AND (account_id = 0 OR account_id = ?)
         ORDER BY sort_order ASC, id ASC",
        [(int) $account->id]
    )->result();

    $applied = [];

    foreach ($rules as $rule) {
        if (!mailbox_rule_matches($rule, $row)) {
            continue;
        }

        $actions = (array) json_decode((string) $rule->actions, true);
        $applied = array_merge($applied, mailbox_run_rule_actions($account, $message_id, $row, $actions, $rule));

        $CI->db->where('id', $rule->id)->update($p . 'mailbox_rules', [
            'hits'        => (int) $rule->hits + 1,
            'last_hit_at' => date('Y-m-d H:i:s'),
        ]);

        mailbox_audit('rule_applied', [
            'account_id' => $account->id,
            'message_id' => $message_id,
            'staff_id'   => 0,
            'subject'    => $row['subject'] ?? '',
            'details'    => 'Rule "' . $rule->name . '" matched',
        ]);

        if (!empty($rule->stop_processing)) {
            break;
        }
    }

    return $applied;
}

/**
 * Execute one rule's action set. Kept separate from matching so the settings
 * screen can dry-run a rule against recent mail without side effects.
 */
function mailbox_run_rule_actions($account, $message_id, array $row, array $actions, $rule = null)
{
    $CI      = &get_instance();
    $p       = db_prefix();
    $applied = [];
    $update  = [];

    foreach ((array) ($actions['labels'] ?? []) as $label_id) {
        $label_id = (int) $label_id;
        if ($label_id > 0) {
            $CI->db->query(
                "INSERT IGNORE INTO `{$p}mailbox_message_labels` (message_id, label_id, created_at) VALUES (?, ?, ?)",
                [(int) $message_id, $label_id, date('Y-m-d H:i:s')]
            );
            $applied[] = 'label:' . $label_id;
        }
    }

    if (!empty($actions['assign_to'])) {
        $update['assigned_to'] = (int) $actions['assign_to'];
        $update['assigned_at'] = date('Y-m-d H:i:s');
        $update['assigned_by'] = 0;
        $applied[]             = 'assign:' . (int) $actions['assign_to'];
    }

    if (!empty($actions['status']) && in_array($actions['status'], ['open', 'pending', 'closed'], true)) {
        $update['conv_status']       = $actions['status'];
        $update['status_changed_at'] = date('Y-m-d H:i:s');
        $applied[]                   = 'status:' . $actions['status'];
    }

    if (!empty($actions['mark_read'])) {
        $update['is_read'] = 1;
        $applied[]         = 'mark_read';
    }
    if (!empty($actions['star'])) {
        $update['is_starred'] = 1;
        $applied[]            = 'star';
    }
    if (!empty($actions['archive'])) {
        $update['folder'] = 'archive';
        $applied[]        = 'archive';
    }
    if (!empty($actions['trash'])) {
        $update['folder']      = 'trash';
        $update['orig_folder'] = 'inbox';
        $applied[]             = 'trash';
    }

    if (count($update)) {
        $CI->db->where('id', (int) $message_id)->update($p . 'mailbox_messages', $update);
    }

    // Auto-forward — best effort, never blocks the import.
    $forward_to = trim((string) ($actions['forward_to'] ?? ''));
    if ($forward_to !== '' && filter_var($forward_to, FILTER_VALIDATE_EMAIL)) {
        $full = $CI->db->query("SELECT * FROM `{$p}mailbox_accounts` WHERE id = ?", [(int) $account->id])->row();
        if ($full) {
            mailbox_send_mail($full, [
                'to'        => [$forward_to],
                'subject'   => 'Fwd: ' . ($row['subject'] ?? ''),
                'body_html' => '<p><em>Automatically forwarded by rule'
                    . ($rule ? ' &ldquo;' . html_escape($rule->name) . '&rdquo;' : '') . '</em></p><hr>'
                    . ($row['body_html'] !== '' && $row['body_html'] !== null
                        ? $row['body_html']
                        : nl2br(html_escape((string) $row['body_plain']))),
            ]);
            $applied[] = 'forward:' . $forward_to;
        }
    }

    // Notify a staff member in the CRM notification bell.
    if (!empty($actions['notify'])) {
        add_notification([
            'description'     => 'mailbox_notification_rule_match',
            'touserid'        => (int) $actions['notify'],
            'fromcompany'     => 1,
            'fromuserid'      => 0,
            'additional_data' => serialize([
                ($row['subject'] ?? '') !== '' ? $row['subject'] : '(no subject)',
                $rule ? $rule->name : '',
            ]),
            'link' => 'mailbox?open=' . (int) $message_id,
        ]);
        $applied[] = 'notify:' . (int) $actions['notify'];
    }

    return $applied;
}

/* ═════════════════════════ Out-of-office ═════════════════════════════════ */

/**
 * Is the account's auto-responder live right now? Empty dates mean "always"
 * on the open side of the range.
 */
function mailbox_oo_active($account)
{
    if ((int) mailbox_opt('mailbox_autoreply_enabled', '1') !== 1 || empty($account->oo_enabled)) {
        return false;
    }

    $today = date('Y-m-d');
    if (!empty($account->oo_from) && $account->oo_from > $today) {
        return false;
    }
    if (!empty($account->oo_to) && $account->oo_to < $today) {
        return false;
    }

    return true;
}

/**
 * Send the out-of-office reply for one inbound message, at most once per
 * sender per day, and never to bulk/automated mail.
 */
function mailbox_maybe_autoreply($account, array $row)
{
    if (!mailbox_oo_active($account)) {
        return false;
    }

    $to = trim((string) $row['from_email']);
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    // Never answer ourselves, mailing lists, or anything machine-generated —
    // that is how auto-responder loops start.
    if (mb_strtolower($to) === mb_strtolower((string) $account->email)) {
        return false;
    }
    foreach (['noreply', 'no-reply', 'donotreply', 'do-not-reply', 'mailer-daemon', 'postmaster', 'bounce'] as $needle) {
        if (mb_strpos(mb_strtolower($to), $needle) !== false) {
            return false;
        }
    }

    $CI  = &get_instance();
    $p   = db_prefix();
    $day = date('Y-m-d');

    $already = $CI->db->query(
        "SELECT id FROM `{$p}mailbox_autoreply_log` WHERE account_id = ? AND to_email = ? AND sent_on = ? LIMIT 1",
        [(int) $account->id, $to, $day]
    )->row();
    if ($already) {
        return false;
    }

    // Claim the slot before sending so a parallel sync cannot double-reply.
    $CI->db->query(
        "INSERT IGNORE INTO `{$p}mailbox_autoreply_log` (account_id, to_email, sent_on) VALUES (?, ?, ?)",
        [(int) $account->id, $to, $day]
    );
    if ($CI->db->affected_rows() < 1) {
        return false;
    }

    $subject = trim((string) $account->oo_subject);
    if ($subject === '') {
        $subject = 'Auto-reply: ' . ($row['subject'] ?? '');
    } elseif (mb_strpos($subject, '{subject}') !== false) {
        $subject = str_replace('{subject}', (string) ($row['subject'] ?? ''), $subject);
    }

    $result = mailbox_send_mail($account, [
        'to'          => [$to],
        'subject'     => mb_substr($subject, 0, 250),
        'body_html'   => (string) $account->oo_body,
        'in_reply_to' => (string) ($row['message_id'] ?? ''),
        'auto_submitted' => true,
    ]);

    mailbox_audit('autoreplied', [
        'account_id' => $account->id,
        'staff_id'   => 0,
        'subject'    => $subject,
        'details'    => $result['success'] ? 'Auto-reply sent to ' . $to : 'Auto-reply FAILED: ' . $result['error'],
    ]);

    return $result['success'];
}

/* ════════════════════ Post-import automation entry point ═════════════════ */

/**
 * Called by the sync engine right after a message row lands. Runs the rules,
 * applies the account's default assignee, starts the SLA clock and fires the
 * out-of-office reply — in that order, because a rule may already have
 * assigned or closed the conversation.
 */
function mailbox_after_import($account, $message_id, array $row)
{
    try {
        $applied = mailbox_apply_rules($account, $message_id, $row);

        $assigned = false;
        foreach ($applied as $action) {
            if (mb_strpos($action, 'assign:') === 0) {
                $assigned = true;
            }
        }

        $CI     = &get_instance();
        $p      = db_prefix();
        $update = [];

        if (!$assigned && (int) ($account->default_assignee ?? 0) > 0) {
            $update['assigned_to'] = (int) $account->default_assignee;
            $update['assigned_at'] = date('Y-m-d H:i:s');
        }
        if (!empty($account->shared_inbox) && (int) mailbox_opt('mailbox_status_enabled', '1') === 1) {
            $current = $CI->db->query(
                "SELECT conv_status, folder FROM `{$p}mailbox_messages` WHERE id = ?",
                [(int) $message_id]
            )->row();
            if ($current && $current->conv_status === '' && $current->folder === 'inbox') {
                $update['conv_status']       = 'open';
                $update['status_changed_at'] = date('Y-m-d H:i:s');
            }
        }
        if (count($update)) {
            $CI->db->where('id', (int) $message_id)->update($p . 'mailbox_messages', $update);
        }

        // The SLA clock only makes sense for mail that is still in the inbox
        // and still open.
        $state = $CI->db->query(
            "SELECT folder, conv_status FROM `{$p}mailbox_messages` WHERE id = ?",
            [(int) $message_id]
        )->row();
        if ($state && $state->folder === 'inbox' && $state->conv_status !== 'closed') {
            mailbox_sla_start($account, $message_id, $row['message_date']);
            mailbox_maybe_autoreply($account, $row);
        }
    } catch (Exception $e) {
        log_activity('Mailbox automation error (message ' . $message_id . '): ' . $e->getMessage());
    }
}

/* ══════════════════════════════ DLP ══════════════════════════════════════ */

/**
 * Screen an outgoing message for data the company does not want leaving.
 *
 * @return array ['hits' => string[], 'block' => bool]
 */
function mailbox_dlp_check($subject, $body_html, array $recipients = [])
{
    if ((int) mailbox_opt('mailbox_dlp_enabled', '0') !== 1) {
        return ['hits' => [], 'block' => false];
    }

    $haystack = mb_strtolower($subject . ' ' . strip_tags((string) $body_html) . ' ' . implode(' ', $recipients));
    $hits     = [];

    foreach (preg_split('/[,\n]+/', (string) mailbox_opt('mailbox_dlp_keywords', ''), -1, PREG_SPLIT_NO_EMPTY) as $keyword) {
        $keyword = trim($keyword);
        if ($keyword !== '' && mb_strpos($haystack, mb_strtolower($keyword)) !== false) {
            $hits[] = $keyword;
        }
    }

    if ((int) mailbox_opt('mailbox_dlp_detect_cards', '0') === 1) {
        // 13–19 digits, optionally grouped — then Luhn, so order numbers and
        // phone numbers do not trip the check.
        if (preg_match_all('/\b(?:\d[ -]?){12,18}\d\b/', strip_tags((string) $body_html), $matches)) {
            foreach ($matches[0] as $candidate) {
                if (mailbox_luhn_valid(preg_replace('/\D/', '', $candidate))) {
                    $hits[] = 'card number';
                    break;
                }
            }
        }
    }

    $hits = array_values(array_unique($hits));

    return [
        'hits'  => $hits,
        'block' => count($hits) > 0 && mailbox_opt('mailbox_dlp_mode', 'warn') === 'block',
    ];
}

function mailbox_luhn_valid($digits)
{
    $length = strlen($digits);
    if ($length < 13 || $length > 19) {
        return false;
    }

    $sum = 0;
    for ($i = 0; $i < $length; $i++) {
        $digit = (int) $digits[$length - 1 - $i];
        if ($i % 2 === 1) {
            $digit *= 2;
            if ($digit > 9) {
                $digit -= 9;
            }
        }
        $sum += $digit;
    }

    return $sum % 10 === 0;
}

/**
 * The silent archive copy every outgoing mail gets, when configured.
 */
function mailbox_compliance_bcc()
{
    $address = trim((string) mailbox_opt('mailbox_compliance_bcc', ''));

    return filter_var($address, FILTER_VALIDATE_EMAIL) ? $address : '';
}

/* ═══════════════════ Scheduled send / undo send ══════════════════════════ */

/**
 * Dispatch every outgoing message whose time has come.
 *
 * Runs from three places so a scheduled mail is never late: the cron tick, the
 * webmail's sync poll, and a direct client ping the moment an undo-send window
 * expires.
 *
 * @return array ['sent' => int, 'failed' => int]
 */
function mailbox_dispatch_scheduled($account_ids = null)
{
    $CI = &get_instance();
    $p  = db_prefix();

    $where = "m.folder = 'scheduled' AND m.scheduled_at IS NOT NULL AND m.scheduled_at <= NOW() AND m.send_attempts < 5";
    $binds = [];

    if (is_array($account_ids) && count($account_ids)) {
        $where .= ' AND m.account_id IN (' . implode(',', array_map('intval', $account_ids)) . ')';
    }

    $due = $CI->db->query(
        "SELECT m.* FROM `{$p}mailbox_messages` m WHERE {$where} ORDER BY m.scheduled_at ASC LIMIT 25",
        $binds
    )->result();

    $sent = $failed = 0;

    foreach ($due as $message) {
        // Claim it first — the cron tick, the webmail poll and an undo-window
        // ping can all arrive together. Matching on the attempt count we just
        // read makes this a compare-and-swap: only one caller's UPDATE can
        // match, so a message is never delivered twice.
        $CI->db->query(
            "UPDATE `{$p}mailbox_messages`
             SET send_attempts = send_attempts + 1
             WHERE id = ? AND folder = 'scheduled' AND send_attempts = ?",
            [(int) $message->id, (int) $message->send_attempts]
        );
        if ($CI->db->affected_rows() < 1) {
            continue;
        }

        $account = $CI->db->query("SELECT * FROM `{$p}mailbox_accounts` WHERE id = ?", [(int) $message->account_id])->row();
        if (!$account || !$account->active) {
            $CI->db->where('id', $message->id)->update($p . 'mailbox_messages', [
                'send_error' => 'Account inactive or removed',
            ]);
            $failed++;
            continue;
        }

        $attachments = [];
        foreach ($CI->db->query("SELECT * FROM `{$p}mailbox_attachments` WHERE message_id = ?", [(int) $message->id])->result() as $attachment) {
            $path = FCPATH . 'uploads' . DIRECTORY_SEPARATOR . 'mailbox' . DIRECTORY_SEPARATOR
                . (int) $message->account_id . DIRECTORY_SEPARATOR . (int) $message->id
                . DIRECTORY_SEPARATOR . $attachment->stored_name;
            if (is_file($path)) {
                $attachments[] = ['path' => $path, 'name' => $attachment->file_name];
            }
        }

        $result = mailbox_send_mail($account, [
            'to'          => mailbox_emails_from_json($message->to_emails),
            'cc'          => mailbox_emails_from_json($message->cc_emails),
            'bcc'         => mailbox_emails_from_json($message->bcc_emails),
            'subject'     => (string) $message->subject,
            'body_html'   => (string) $message->body_html,
            'in_reply_to' => (string) $message->in_reply_to,
            'references'  => (string) $message->refs,
            'attachments' => $attachments,
        ]);

        if ($result['success']) {
            $CI->db->where('id', $message->id)->update($p . 'mailbox_messages', [
                'folder'       => 'sent',
                'scheduled_at' => null,
                'send_error'   => null,
                'message_id'   => mb_substr($result['message_id'], 0, 255),
                'message_date' => date('Y-m-d H:i:s'),
            ]);
            mailbox_append_to_sent($account, $result['raw']);
            mailbox_sla_stop_for_reply($account, $message);

            mailbox_audit('sent', [
                'account_id' => $account->id,
                'message_id' => $message->id,
                'staff_id'   => (int) $message->sent_by,
                'subject'    => $message->subject,
                'details'    => 'Scheduled send delivered to ' . implode(', ', mailbox_emails_from_json($message->to_emails)),
            ]);
            $sent++;
        } else {
            $CI->db->where('id', $message->id)->update($p . 'mailbox_messages', [
                'send_error' => $result['error'],
            ]);
            $failed++;
        }
    }

    return ['sent' => $sent, 'failed' => $failed];
}

/**
 * Stop the SLA clock for a delivered reply, resolving the source message from
 * the stored in_reply_to header.
 */
function mailbox_sla_stop_for_reply($account, $message)
{
    if (trim((string) $message->in_reply_to) === '') {
        return;
    }

    $CI     = &get_instance();
    $source = $CI->db->query(
        'SELECT id, thread_id FROM `' . db_prefix() . 'mailbox_messages` WHERE account_id = ? AND message_id = ? LIMIT 1',
        [(int) $account->id, (string) $message->in_reply_to]
    )->row();

    if ($source) {
        mailbox_sla_stop($account->id, $source, (int) $message->sent_by);
    }
}

/**
 * Stored JSON address blob → plain email array.
 */
function mailbox_emails_from_json($json)
{
    $out = [];
    foreach ((array) json_decode((string) $json, true) as $entry) {
        if (is_array($entry) && !empty($entry['email'])) {
            $out[] = $entry['email'];
        }
    }

    return $out;
}

/* ═══════════════════════════ Retention ═══════════════════════════════════ */

/**
 * Delete mail past the retention horizon, skipping anything under legal hold.
 * Purges the audit trail on its own (longer) clock.
 *
 * @return int messages removed
 */
function mailbox_run_retention()
{
    $CI   = &get_instance();
    $p    = db_prefix();
    $days = (int) mailbox_opt('mailbox_retention_days', '0');
    $gone = 0;

    if ($days > 0) {
        $folders = array_values(array_filter(array_map('trim', explode(',', (string) mailbox_opt('mailbox_retention_folders', 'trash')))));
        $folders = array_values(array_intersect($folders, ['inbox', 'sent', 'drafts', 'archive', 'trash']));

        if (count($folders)) {
            $in  = "'" . implode("','", array_map(function ($f) use ($CI) {
                return $CI->db->escape_str($f);
            }, $folders)) . "'";

            $rows = $CI->db->query(
                "SELECT id, account_id, subject FROM `{$p}mailbox_messages`
                 WHERE legal_hold = 0 AND folder IN ({$in})
                   AND message_date < DATE_SUB(NOW(), INTERVAL {$days} DAY)
                 LIMIT 500"
            )->result();

            foreach ($rows as $row) {
                $CI->db->where('message_id', (int) $row->id)->delete($p . 'mailbox_attachments');
                $CI->db->where('id', (int) $row->id)->delete($p . 'mailbox_messages');

                $dir = FCPATH . 'uploads' . DIRECTORY_SEPARATOR . 'mailbox' . DIRECTORY_SEPARATOR
                    . (int) $row->account_id . DIRECTORY_SEPARATOR . (int) $row->id;
                if (is_dir($dir)) {
                    mailbox_rrmdir($dir);
                }
                $gone++;
            }

            if ($gone > 0) {
                mailbox_audit('purged', [
                    'staff_id' => 0,
                    'details'  => $gone . ' message(s) removed by the ' . $days . '-day retention policy',
                ]);
            }
        }
    }

    $audit_days = (int) mailbox_opt('mailbox_audit_retention_days', '365');
    if ($audit_days > 0) {
        $CI->db->query("DELETE FROM `{$p}mailbox_audit` WHERE created_at < DATE_SUB(NOW(), INTERVAL {$audit_days} DAY)");
    }

    // Yesterday's loop-guard rows are dead weight.
    $CI->db->query("DELETE FROM `{$p}mailbox_autoreply_log` WHERE sent_on < DATE_SUB(CURDATE(), INTERVAL 7 DAY)");

    return $gone;
}

function mailbox_rrmdir($dir)
{
    foreach ((array) scandir($dir) as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        is_dir($path) ? mailbox_rrmdir($path) : @unlink($path);
    }
    @rmdir($dir);
}

/* ═══════════════════════════ Cron entry ══════════════════════════════════ */

/**
 * Maintenance tick: dispatch scheduled mail, flag SLA breaches, enforce
 * retention. Separate from the IMAP sync so a dead mail server never stops
 * governance work.
 */
function mailbox_run_cron_maintenance()
{
    if (!mailbox_ensure_schema()) {
        return;
    }

    try {
        mailbox_dispatch_scheduled();
        mailbox_sla_scan();

        // Retention is expensive — once a day is plenty.
        if (date('Y-m-d') !== (string) get_option('mailbox_retention_last_run')) {
            update_option('mailbox_retention_last_run', date('Y-m-d'));
            mailbox_run_retention();
        }
    } catch (Exception $e) {
        log_activity('Mailbox maintenance error: ' . $e->getMessage());
    }
}

/* ═════════════════════════ Template rendering ════════════════════════════ */

/**
 * Merge placeholders into a canned response. Anything unknown is left alone
 * so a typo is visible rather than silently blanked.
 */
function mailbox_render_template($html, array $context = [])
{
    $staff_id = get_staff_user_id();

    $map = [
        '{my_name}'      => $staff_id ? get_staff_full_name($staff_id) : '',
        '{my_email}'     => $context['account_email'] ?? '',
        '{company}'      => get_option('companyname'),
        '{date}'         => _d(date('Y-m-d')),
        '{contact_name}' => $context['contact_name'] ?? '',
        '{contact_email}' => $context['contact_email'] ?? '',
        '{subject}'      => $context['subject'] ?? '',
    ];

    return str_replace(array_keys($map), array_values($map), (string) $html);
}

function mailbox_template_placeholders()
{
    return ['{my_name}', '{my_email}', '{company}', '{date}', '{contact_name}', '{contact_email}', '{subject}'];
}

/* ═══════════════════════ Advanced search syntax ══════════════════════════ */

/**
 * Parse a Gmail-style query into structured filters.
 *
 * Understood: from: to: subject: label: has:attachment is:unread is:read
 * is:starred is:open is:pending is:closed is:overdue assigned:me
 * assigned:none after:YYYY-MM-DD before:YYYY-MM-DD — quoted values allowed.
 * Everything left over is free text.
 *
 * @return array structured filter set
 */
function mailbox_parse_search($input)
{
    $filters = [
        'text' => '', 'from' => '', 'to' => '', 'subject' => '', 'label' => '',
        'has_attachment' => false, 'is_unread' => false, 'is_read' => false,
        'is_starred' => false, 'status' => '', 'overdue' => false,
        'assigned' => '', 'after' => '', 'before' => '',
    ];

    $input = trim((string) $input);
    if ($input === '') {
        return $filters;
    }

    $free = $input;

    // token:value  or  token:"value with spaces"
    if (preg_match_all('/(\w+):("([^"]*)"|\S+)/', $input, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $key = mb_strtolower($match[1]);
            // $match[3] is only populated for the quoted alternative.
            $raw   = $match[2];
            $value = (isset($raw[0]) && $raw[0] === '"') ? ($match[3] ?? '') : $raw;
            $value = trim($value);

            $consumed = true;
            switch ($key) {
                case 'from':    $filters['from'] = $value; break;
                case 'to':      $filters['to'] = $value; break;
                case 'subject': $filters['subject'] = $value; break;
                case 'label':   $filters['label'] = $value; break;
                case 'after':   $filters['after'] = $value; break;
                case 'before':  $filters['before'] = $value; break;
                case 'has':
                    $filters['has_attachment'] = in_array(mb_strtolower($value), ['attachment', 'attachments', 'file'], true);
                    break;
                case 'assigned':
                    $filters['assigned'] = mb_strtolower($value);
                    break;
                case 'is':
                    switch (mb_strtolower($value)) {
                        case 'unread':  $filters['is_unread'] = true; break;
                        case 'read':    $filters['is_read'] = true; break;
                        case 'starred': $filters['is_starred'] = true; break;
                        case 'open':    $filters['status'] = 'open'; break;
                        case 'pending': $filters['status'] = 'pending'; break;
                        case 'closed':  $filters['status'] = 'closed'; break;
                        case 'overdue': $filters['overdue'] = true; break;
                        default: $consumed = false;
                    }
                    break;
                default:
                    $consumed = false;
            }

            if ($consumed) {
                $free = str_replace($match[0], ' ', $free);
            }
        }
    }

    $filters['text'] = trim(preg_replace('/\s+/', ' ', $free));

    return $filters;
}

/**
 * The operators the search help popover documents.
 */
function mailbox_search_operators()
{
    return [
        'from:name@company.com', 'to:someone@company.com', 'subject:"quarterly report"',
        'label:Escalations', 'has:attachment', 'is:unread', 'is:starred',
        'is:open', 'is:pending', 'is:closed', 'is:overdue',
        'assigned:me', 'assigned:none', 'after:2026-01-01', 'before:2026-03-31',
    ];
}

/* ═════════════════════════ Capability gates ══════════════════════════════ */

/**
 * May the current user see the governance screens (analytics + audit)?
 * Superadmins always; other staff need the explicit capability.
 */
function mailbox_can_view_reports()
{
    return mailbox_staff_is_super() || staff_can('analytics', MAILBOX_MODULE_NAME);
}

/**
 * May the current user manage shared assets — labels, templates, rules?
 */
function mailbox_can_manage()
{
    return mailbox_staff_is_super() || staff_can('manage', MAILBOX_MODULE_NAME);
}

/**
 * Conversation statuses in display order.
 */
function mailbox_statuses()
{
    return ['open', 'pending', 'closed'];
}
