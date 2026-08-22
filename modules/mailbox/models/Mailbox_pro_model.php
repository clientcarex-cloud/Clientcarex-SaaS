<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Mailbox — data access for the corporate layer.
 *
 * Labels, internal notes, canned responses, automation rules, the audit trail,
 * shared-inbox assignment/status writes, recipient autocomplete and every
 * aggregate the analytics dashboard draws.
 *
 * Kept apart from Mailbox_model so the original webmail data path stays small
 * and reviewable.
 */
class Mailbox_pro_model extends App_Model
{
    public function __construct()
    {
        parent::__construct();
        mailbox_ensure_schema();
    }

    /* ═══════════════════════════ Labels ═══════════════════════════════════ */

    /**
     * Labels usable on the given accounts — global ones (account_id 0) plus
     * the account-specific ones, each with its current message count.
     */
    public function get_labels(array $account_ids = [])
    {
        $p     = db_prefix();
        $where = 'l.account_id = 0';

        if (count($account_ids)) {
            $where .= ' OR l.account_id IN (' . implode(',', array_map('intval', $account_ids)) . ')';
        }

        return $this->db->query(
            "SELECT l.*, (SELECT COUNT(*) FROM `{$p}mailbox_message_labels` ml WHERE ml.label_id = l.id) AS message_count
             FROM `{$p}mailbox_labels` l
             WHERE {$where}
             ORDER BY l.sort_order ASC, l.name ASC"
        )->result();
    }

    public function get_label($id)
    {
        return $this->db->where('id', (int) $id)->get(db_prefix() . 'mailbox_labels')->row();
    }

    /**
     * @return array ['success' => bool, 'id' => int, 'error' => string]
     */
    public function save_label(array $data, $id = 0)
    {
        $p    = db_prefix();
        $id   = (int) $id;
        $name = trim((string) ($data['name'] ?? ''));

        if ($name === '') {
            return ['success' => false, 'id' => 0, 'error' => _l('mailbox_label_name_required')];
        }

        $row = [
            'account_id' => (int) ($data['account_id'] ?? 0),
            'name'       => mb_substr($name, 0, 80),
            'color'      => preg_match('/^#[0-9a-f]{6}$/i', (string) ($data['color'] ?? '')) ? $data['color'] : '#4f46e5',
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];

        // The (account_id, name) unique key is what keeps the sidebar clean.
        $clash = $this->db->query(
            "SELECT id FROM `{$p}mailbox_labels` WHERE account_id = ? AND name = ? AND id != ? LIMIT 1",
            [$row['account_id'], $row['name'], $id]
        )->row();
        if ($clash) {
            return ['success' => false, 'id' => 0, 'error' => _l('mailbox_label_exists')];
        }

        if ($id > 0) {
            $this->db->where('id', $id)->update($p . 'mailbox_labels', $row);

            return ['success' => true, 'id' => $id, 'error' => ''];
        }

        $row['created_by'] = get_staff_user_id();
        $row['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert($p . 'mailbox_labels', $row);

        return ['success' => true, 'id' => (int) $this->db->insert_id(), 'error' => ''];
    }

    public function delete_label($id)
    {
        $p = db_prefix();
        $this->db->where('label_id', (int) $id)->delete($p . 'mailbox_message_labels');
        $this->db->where('id', (int) $id)->delete($p . 'mailbox_labels');

        return true;
    }

    /**
     * Attach / detach one label across a set of messages.
     *
     * @return int messages touched
     */
    public function apply_label(array $message_ids, $label_id, $on)
    {
        $p        = db_prefix();
        $label_id = (int) $label_id;
        $done     = 0;

        foreach ($message_ids as $message_id) {
            $message_id = (int) $message_id;
            if ($message_id <= 0) {
                continue;
            }

            if ($on) {
                $this->db->query(
                    "INSERT IGNORE INTO `{$p}mailbox_message_labels` (message_id, label_id, created_at) VALUES (?, ?, ?)",
                    [$message_id, $label_id, date('Y-m-d H:i:s')]
                );
            } else {
                $this->db->where('message_id', $message_id)->where('label_id', $label_id)
                    ->delete($p . 'mailbox_message_labels');
            }
            $done++;
        }

        return $done;
    }

    /**
     * Labels for a batch of messages, keyed by message id — one query for a
     * whole list page.
     *
     * @return array [message_id => [{id, name, color}, ...]]
     */
    public function labels_for_messages(array $message_ids)
    {
        if (!count($message_ids)) {
            return [];
        }

        $p   = db_prefix();
        $in  = implode(',', array_map('intval', $message_ids));
        $out = [];

        foreach ($this->db->query(
            "SELECT ml.message_id, l.id, l.name, l.color
             FROM `{$p}mailbox_message_labels` ml
             JOIN `{$p}mailbox_labels` l ON l.id = ml.label_id
             WHERE ml.message_id IN ({$in})
             ORDER BY l.sort_order ASC, l.name ASC"
        )->result() as $row) {
            $out[(int) $row->message_id][] = [
                'id'    => (int) $row->id,
                'name'  => $row->name,
                'color' => $row->color,
            ];
        }

        return $out;
    }

    /* ═══════════════════════ Internal notes ═══════════════════════════════ */

    /**
     * Notes on a whole conversation, so a note written on one reply is visible
     * from every message in the thread.
     */
    public function get_notes($account_id, $thread_id, $message_id)
    {
        $p = db_prefix();

        return $this->db->query(
            "SELECT n.*, CONCAT(st.firstname, ' ', st.lastname) AS staff_name, st.profile_image
             FROM `{$p}mailbox_notes` n
             LEFT JOIN `{$p}staff` st ON st.staffid = n.staff_id
             WHERE n.account_id = ? AND (n.thread_id = ? OR n.message_id = ?)
             ORDER BY n.created_at ASC, n.id ASC",
            [(int) $account_id, (int) $thread_id, (int) $message_id]
        )->result();
    }

    /**
     * Store a note and notify everyone @mentioned in it.
     *
     * @param  array $mentions staff ids extracted client side
     * @return int   note id
     */
    public function add_note($message, $note, array $mentions = [])
    {
        $p        = db_prefix();
        $mentions = array_values(array_unique(array_filter(array_map('intval', $mentions))));

        $this->db->insert($p . 'mailbox_notes', [
            'account_id' => (int) $message->account_id,
            'message_id' => (int) $message->id,
            'thread_id'  => (int) ($message->thread_id ?: $message->id),
            'staff_id'   => get_staff_user_id(),
            'note'       => (string) $note,
            'mentions'   => implode(',', $mentions),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $id = (int) $this->db->insert_id();

        foreach ($mentions as $staff_id) {
            if ($staff_id === (int) get_staff_user_id()) {
                continue;
            }
            add_notification([
                'description'     => 'mailbox_notification_mentioned',
                'touserid'        => $staff_id,
                'fromuserid'      => get_staff_user_id(),
                'additional_data' => serialize([
                    $message->subject !== '' ? $message->subject : '(no subject)',
                ]),
                'link' => 'mailbox?open=' . (int) $message->id,
            ]);
        }

        return $id;
    }

    public function get_note($id)
    {
        return $this->db->where('id', (int) $id)->get(db_prefix() . 'mailbox_notes')->row();
    }

    public function delete_note($id)
    {
        $this->db->where('id', (int) $id)->delete(db_prefix() . 'mailbox_notes');

        return true;
    }

    /**
     * Note counts for a list page, keyed by message id.
     */
    public function note_counts_for_messages(array $message_ids)
    {
        if (!count($message_ids)) {
            return [];
        }

        $p   = db_prefix();
        $in  = implode(',', array_map('intval', $message_ids));
        $out = [];

        foreach ($this->db->query(
            "SELECT m.id, (SELECT COUNT(*) FROM `{$p}mailbox_notes` n
                            WHERE n.thread_id = m.thread_id AND n.account_id = m.account_id) AS cnt
             FROM `{$p}mailbox_messages` m WHERE m.id IN ({$in})"
        )->result() as $row) {
            $out[(int) $row->id] = (int) $row->cnt;
        }

        return $out;
    }

    /* ═════════════════════════ Templates ══════════════════════════════════ */

    public function get_templates(array $account_ids = [])
    {
        $p     = db_prefix();
        $where = 't.account_id = 0';

        if (count($account_ids)) {
            $where .= ' OR t.account_id IN (' . implode(',', array_map('intval', $account_ids)) . ')';
        }

        // A private template belongs to whoever created it.
        $where = "({$where}) AND (t.is_shared = 1 OR t.created_by = " . (int) get_staff_user_id() . ')';

        return $this->db->query(
            "SELECT t.*, CONCAT(st.firstname, ' ', st.lastname) AS created_by_name
             FROM `{$p}mailbox_templates` t
             LEFT JOIN `{$p}staff` st ON st.staffid = t.created_by
             WHERE {$where}
             ORDER BY t.name ASC"
        )->result();
    }

    public function get_template($id)
    {
        return $this->db->where('id', (int) $id)->get(db_prefix() . 'mailbox_templates')->row();
    }

    public function save_template(array $data, $id = 0)
    {
        $p    = db_prefix();
        $id   = (int) $id;
        $name = trim((string) ($data['name'] ?? ''));

        if ($name === '') {
            return ['success' => false, 'id' => 0, 'error' => _l('mailbox_template_name_required')];
        }

        $row = [
            'account_id' => (int) ($data['account_id'] ?? 0),
            'name'       => mb_substr($name, 0, 150),
            'subject'    => mb_substr(trim((string) ($data['subject'] ?? '')), 0, 300),
            'body_html'  => (string) ($data['body_html'] ?? ''),
            'is_shared'  => !empty($data['is_shared']) ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($id > 0) {
            $this->db->where('id', $id)->update($p . 'mailbox_templates', $row);

            return ['success' => true, 'id' => $id, 'error' => ''];
        }

        $row['created_by'] = get_staff_user_id();
        $row['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert($p . 'mailbox_templates', $row);

        return ['success' => true, 'id' => (int) $this->db->insert_id(), 'error' => ''];
    }

    public function delete_template($id)
    {
        $this->db->where('id', (int) $id)->delete(db_prefix() . 'mailbox_templates');

        return true;
    }

    public function bump_template_usage($id)
    {
        $p = db_prefix();
        $this->db->query("UPDATE `{$p}mailbox_templates` SET usage_count = usage_count + 1 WHERE id = ?", [(int) $id]);
    }

    /* ═══════════════════════════ Rules ════════════════════════════════════ */

    public function get_rules($account_id = null)
    {
        $p     = db_prefix();
        $where = $account_id === null ? '1 = 1' : '(r.account_id = 0 OR r.account_id = ' . (int) $account_id . ')';

        return $this->db->query(
            "SELECT r.*, a.name AS account_name
             FROM `{$p}mailbox_rules` r
             LEFT JOIN `{$p}mailbox_accounts` a ON a.id = r.account_id
             WHERE {$where}
             ORDER BY r.sort_order ASC, r.id ASC"
        )->result();
    }

    public function get_rule($id)
    {
        return $this->db->where('id', (int) $id)->get(db_prefix() . 'mailbox_rules')->row();
    }

    /**
     * Conditions and actions arrive as arrays from the settings screen and are
     * stored as JSON. Unknown keys are dropped so a stale UI cannot smuggle
     * anything into the engine.
     */
    public function save_rule(array $data, $id = 0)
    {
        $p    = db_prefix();
        $id   = (int) $id;
        $name = trim((string) ($data['name'] ?? ''));

        if ($name === '') {
            return ['success' => false, 'id' => 0, 'error' => _l('mailbox_rule_name_required')];
        }

        $conditions = [];
        foreach ((array) ($data['conditions'] ?? []) as $condition) {
            if (!is_array($condition) || empty($condition['field'])) {
                continue;
            }
            if (!in_array($condition['field'], mailbox_rule_fields(), true)) {
                continue;
            }
            $conditions[] = [
                'field' => $condition['field'],
                'op'    => in_array($condition['op'] ?? '', mailbox_rule_operators(), true) ? $condition['op'] : 'contains',
                'value' => mb_substr((string) ($condition['value'] ?? ''), 0, 500),
            ];
        }

        if (!count($conditions)) {
            return ['success' => false, 'id' => 0, 'error' => _l('mailbox_rule_conditions_required')];
        }

        $raw     = (array) ($data['actions'] ?? []);
        $actions = [
            'labels'     => array_values(array_filter(array_map('intval', (array) ($raw['labels'] ?? [])))),
            'assign_to'  => (int) ($raw['assign_to'] ?? 0),
            'status'     => in_array($raw['status'] ?? '', mailbox_statuses(), true) ? $raw['status'] : '',
            'mark_read'  => !empty($raw['mark_read']) ? 1 : 0,
            'star'       => !empty($raw['star']) ? 1 : 0,
            'archive'    => !empty($raw['archive']) ? 1 : 0,
            'trash'      => !empty($raw['trash']) ? 1 : 0,
            'forward_to' => filter_var(trim((string) ($raw['forward_to'] ?? '')), FILTER_VALIDATE_EMAIL) ?: '',
            'notify'     => (int) ($raw['notify'] ?? 0),
        ];

        $row = [
            'account_id'      => (int) ($data['account_id'] ?? 0),
            'name'            => mb_substr($name, 0, 150),
            'match_type'      => ($data['match_type'] ?? 'all') === 'any' ? 'any' : 'all',
            'conditions'      => json_encode($conditions, JSON_UNESCAPED_UNICODE),
            'actions'         => json_encode($actions, JSON_UNESCAPED_UNICODE),
            'active'          => !empty($data['active']) ? 1 : 0,
            'stop_processing' => !empty($data['stop_processing']) ? 1 : 0,
            'sort_order'      => (int) ($data['sort_order'] ?? 0),
        ];

        if ($id > 0) {
            $this->db->where('id', $id)->update($p . 'mailbox_rules', $row);

            return ['success' => true, 'id' => $id, 'error' => ''];
        }

        $row['created_by'] = get_staff_user_id();
        $row['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert($p . 'mailbox_rules', $row);

        return ['success' => true, 'id' => (int) $this->db->insert_id(), 'error' => ''];
    }

    public function delete_rule($id)
    {
        $this->db->where('id', (int) $id)->delete(db_prefix() . 'mailbox_rules');

        return true;
    }

    /**
     * Dry-run a rule against the newest mail on its account — no side effects,
     * just "these N of the last 200 messages would have matched".
     */
    public function test_rule($rule, $limit = 200)
    {
        $p     = db_prefix();
        $where = 'folder != \'drafts\'';
        if ((int) $rule->account_id > 0) {
            $where .= ' AND account_id = ' . (int) $rule->account_id;
        }

        $rows = $this->db->query(
            "SELECT id, account_id, subject, from_name, from_email, to_emails, cc_emails,
                    body_plain, body_html, has_attachments, message_date
             FROM `{$p}mailbox_messages`
             WHERE {$where}
             ORDER BY message_date DESC
             LIMIT " . (int) $limit
        )->result_array();

        $matches = [];
        foreach ($rows as $row) {
            if (mailbox_rule_matches($rule, $row)) {
                $matches[] = [
                    'id'         => (int) $row['id'],
                    'subject'    => $row['subject'],
                    'from_email' => $row['from_email'],
                    'date'       => $row['message_date'],
                ];
            }
        }

        return ['scanned' => count($rows), 'matches' => $matches];
    }

    /* ═══════════════════ Assignment / conversation status ═════════════════ */

    /**
     * Assign a conversation. Applied thread-wide so a later reply inherits the
     * owner instead of falling back to unassigned.
     *
     * @return int rows updated
     */
    public function assign(array $message_ids, $staff_id)
    {
        $p        = db_prefix();
        $staff_id = (int) $staff_id;
        $now      = date('Y-m-d H:i:s');
        $updated  = 0;

        foreach ($message_ids as $message_id) {
            $message = $this->db->query(
                "SELECT id, account_id, thread_id, subject FROM `{$p}mailbox_messages` WHERE id = ?",
                [(int) $message_id]
            )->row();
            if (!$message) {
                continue;
            }

            $this->db->query(
                "UPDATE `{$p}mailbox_messages`
                 SET assigned_to = ?, assigned_at = ?, assigned_by = ?
                 WHERE account_id = ? AND thread_id = ?",
                [$staff_id, $staff_id > 0 ? $now : null, get_staff_user_id(), (int) $message->account_id, (int) ($message->thread_id ?: $message->id)]
            );
            $updated++;

            mailbox_audit('assigned', [
                'account_id' => $message->account_id,
                'message_id' => $message->id,
                'subject'    => $message->subject,
                'details'    => $staff_id > 0
                    ? 'Assigned to ' . get_staff_full_name($staff_id)
                    : 'Assignment cleared',
            ]);

            if ($staff_id > 0 && $staff_id !== (int) get_staff_user_id()) {
                add_notification([
                    'description'     => 'mailbox_notification_assigned',
                    'touserid'        => $staff_id,
                    'fromuserid'      => get_staff_user_id(),
                    'additional_data' => serialize([
                        $message->subject !== '' ? $message->subject : '(no subject)',
                    ]),
                    'link' => 'mailbox?open=' . (int) $message->id,
                ]);
            }
        }

        return $updated;
    }

    /**
     * Move a conversation through open → pending → closed. Closing stops the
     * SLA clock; reopening restarts nothing (the first response already
     * happened or the deadline already passed).
     */
    public function set_status(array $message_ids, $status)
    {
        if (!in_array($status, mailbox_statuses(), true)) {
            return 0;
        }

        $p       = db_prefix();
        $now     = date('Y-m-d H:i:s');
        $updated = 0;

        foreach ($message_ids as $message_id) {
            $message = $this->db->query(
                "SELECT id, account_id, thread_id, subject FROM `{$p}mailbox_messages` WHERE id = ?",
                [(int) $message_id]
            )->row();
            if (!$message) {
                continue;
            }

            $extra = $status === 'closed' ? ', sla_due_at = NULL' : '';

            $this->db->query(
                "UPDATE `{$p}mailbox_messages`
                 SET conv_status = ?, status_changed_at = ?, status_changed_by = ?{$extra}
                 WHERE account_id = ? AND thread_id = ?",
                [$status, $now, get_staff_user_id(), (int) $message->account_id, (int) ($message->thread_id ?: $message->id)]
            );
            $updated++;

            mailbox_audit('status_changed', [
                'account_id' => $message->account_id,
                'message_id' => $message->id,
                'subject'    => $message->subject,
                'details'    => 'Conversation marked ' . $status,
            ]);
        }

        return $updated;
    }

    public function set_legal_hold(array $message_ids, $on)
    {
        $p = db_prefix();
        if (!count($message_ids)) {
            return 0;
        }

        $this->db->where_in('id', array_map('intval', $message_ids))
            ->update($p . 'mailbox_messages', ['legal_hold' => $on ? 1 : 0]);

        return $this->db->affected_rows();
    }

    /* ═══════════════════════ Recipient autocomplete ═══════════════════════ */

    /**
     * Address book: people this team has actually corresponded with, plus CRM
     * contacts and leads. Deduplicated on the email.
     */
    public function search_contacts($query, array $account_ids, $limit = 8)
    {
        $query = trim((string) $query);
        if (mb_strlen($query) < 2) {
            return [];
        }

        $p    = db_prefix();
        $like = '%' . $this->db->escape_like_str($query) . '%';
        $out  = [];

        $push = function ($email, $name, $source) use (&$out) {
            $email = trim((string) $email);
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return;
            }
            $key = mb_strtolower($email);
            if (!isset($out[$key])) {
                $out[$key] = ['email' => $email, 'name' => trim((string) $name), 'source' => $source];
            }
        };

        if (count($account_ids)) {
            $in = implode(',', array_map('intval', $account_ids));
            foreach ($this->db->query(
                "SELECT from_email AS email, from_name AS name, MAX(message_date) AS last_seen
                 FROM `{$p}mailbox_messages`
                 WHERE account_id IN ({$in}) AND folder = 'inbox'
                   AND (from_email LIKE ? OR from_name LIKE ?)
                 GROUP BY from_email, from_name
                 ORDER BY last_seen DESC
                 LIMIT " . (int) $limit,
                [$like, $like]
            )->result() as $row) {
                $push($row->email, $row->name, 'mailbox');
            }
        }

        foreach ($this->db->query(
            "SELECT email, CONCAT(firstname, ' ', lastname) AS name
             FROM `{$p}contacts`
             WHERE active = 1 AND email != '' AND (email LIKE ? OR firstname LIKE ? OR lastname LIKE ?)
             LIMIT " . (int) $limit,
            [$like, $like, $like]
        )->result() as $row) {
            $push($row->email, $row->name, 'contact');
        }

        foreach ($this->db->query(
            "SELECT email, name FROM `{$p}leads`
             WHERE email != '' AND (email LIKE ? OR name LIKE ?)
             LIMIT " . (int) $limit,
            [$like, $like]
        )->result() as $row) {
            $push($row->email, $row->name, 'lead');
        }

        return array_slice(array_values($out), 0, $limit * 2);
    }

    /**
     * Does this sender already exist in the CRM? Powers the context chip in
     * the reading pane.
     */
    public function crm_match($email)
    {
        $email = trim((string) $email);
        if ($email === '') {
            return null;
        }

        $p = db_prefix();

        $contact = $this->db->query(
            "SELECT c.id, c.userid, CONCAT(c.firstname, ' ', c.lastname) AS name, cl.company
             FROM `{$p}contacts` c
             LEFT JOIN `{$p}clients` cl ON cl.userid = c.userid
             WHERE c.email = ? LIMIT 1",
            [$email]
        )->row();
        if ($contact) {
            return [
                'type'  => 'customer',
                'id'    => (int) $contact->userid,
                'label' => $contact->company !== '' ? $contact->company : $contact->name,
                'url'   => admin_url('clients/client/' . (int) $contact->userid),
            ];
        }

        $lead = $this->db->query("SELECT id, name, company FROM `{$p}leads` WHERE email = ? LIMIT 1", [$email])->row();
        if ($lead) {
            return [
                'type'  => 'lead',
                'id'    => (int) $lead->id,
                'label' => $lead->company !== '' ? $lead->company : $lead->name,
                'url'   => admin_url('leads/index/' . (int) $lead->id),
            ];
        }

        return null;
    }

    /* ═════════════════════════ Audit trail ════════════════════════════════ */

    /**
     * Paged audit query with the filters the audit screen exposes.
     */
    public function get_audit(array $filters, $page = 1, $per_page = 50)
    {
        $p     = db_prefix();
        $where = '1 = 1';
        $binds = [];

        if (!empty($filters['account_id'])) {
            $where .= ' AND al.account_id = ?';
            $binds[] = (int) $filters['account_id'];
        }
        if (!empty($filters['staff_id'])) {
            $where .= ' AND al.staff_id = ?';
            $binds[] = (int) $filters['staff_id'];
        }
        if (!empty($filters['action'])) {
            $where .= ' AND al.action = ?';
            $binds[] = (string) $filters['action'];
        }
        if (!empty($filters['from'])) {
            $where .= ' AND al.created_at >= ?';
            $binds[] = $filters['from'] . ' 00:00:00';
        }
        if (!empty($filters['to'])) {
            $where .= ' AND al.created_at <= ?';
            $binds[] = $filters['to'] . ' 23:59:59';
        }
        if (!empty($filters['search'])) {
            $where .= ' AND (al.subject LIKE ? OR al.details LIKE ? OR al.ip LIKE ?)';
            $like  = '%' . $this->db->escape_like_str($filters['search']) . '%';
            array_push($binds, $like, $like, $like);
        }

        $total  = (int) $this->db->query("SELECT COUNT(*) AS c FROM `{$p}mailbox_audit` al WHERE {$where}", $binds)->row()->c;
        $offset = max(0, ((int) $page - 1) * (int) $per_page);

        $rows = $this->db->query(
            "SELECT al.*, CONCAT(st.firstname, ' ', st.lastname) AS staff_name, a.name AS account_name
             FROM `{$p}mailbox_audit` al
             LEFT JOIN `{$p}staff` st ON st.staffid = al.staff_id
             LEFT JOIN `{$p}mailbox_accounts` a ON a.id = al.account_id
             WHERE {$where}
             ORDER BY al.id DESC
             LIMIT " . (int) $per_page . ' OFFSET ' . $offset,
            $binds
        )->result();

        return ['rows' => $rows, 'total' => $total, 'total_pages' => max(1, (int) ceil($total / $per_page))];
    }

    /* ═════════════════════════ Analytics ══════════════════════════════════ */

    /**
     * Everything the dashboard needs, in one call.
     *
     * @param array  $account_ids accounts in scope (empty → nothing)
     * @param string $from        Y-m-d
     * @param string $to          Y-m-d
     */
    public function analytics(array $account_ids, $from, $to)
    {
        if (!count($account_ids)) {
            return ['totals' => null, 'daily' => [], 'by_account' => [], 'by_staff' => [], 'top_senders' => [], 'by_hour' => []];
        }

        $p     = db_prefix();
        $in    = implode(',', array_map('intval', $account_ids));
        $start = $from . ' 00:00:00';
        $end   = $to . ' 23:59:59';

        $totals = $this->db->query(
            "SELECT
                SUM(folder = 'inbox') AS received,
                SUM(folder = 'sent') AS sent,
                SUM(folder = 'inbox' AND is_read = 0) AS unread,
                SUM(folder = 'inbox' AND conv_status = 'open') AS open_conv,
                SUM(folder = 'inbox' AND conv_status = 'pending') AS pending_conv,
                SUM(folder = 'inbox' AND conv_status = 'closed') AS closed_conv,
                SUM(folder = 'inbox' AND assigned_to = 0 AND conv_status != 'closed') AS unassigned,
                SUM(folder = 'inbox' AND sla_breached = 1) AS sla_breached,
                SUM(folder = 'inbox' AND first_reply_at IS NOT NULL) AS answered,
                AVG(CASE WHEN folder = 'inbox' AND first_reply_at IS NOT NULL
                         THEN TIMESTAMPDIFF(MINUTE, message_date, first_reply_at) END) AS avg_response_minutes,
                SUM(folder = 'scheduled') AS scheduled
             FROM `{$p}mailbox_messages`
             WHERE account_id IN ({$in}) AND message_date BETWEEN ? AND ?",
            [$start, $end]
        )->row();

        $daily = $this->db->query(
            "SELECT DATE(message_date) AS d,
                    SUM(folder = 'inbox') AS received,
                    SUM(folder = 'sent') AS sent
             FROM `{$p}mailbox_messages`
             WHERE account_id IN ({$in}) AND message_date BETWEEN ? AND ?
             GROUP BY DATE(message_date)
             ORDER BY d ASC",
            [$start, $end]
        )->result();

        $by_account = $this->db->query(
            "SELECT a.id, a.name, a.email,
                    SUM(m.folder = 'inbox') AS received,
                    SUM(m.folder = 'sent') AS sent,
                    SUM(m.folder = 'inbox' AND m.sla_breached = 1) AS breached,
                    SUM(m.folder = 'inbox' AND m.first_reply_at IS NOT NULL) AS answered,
                    AVG(CASE WHEN m.folder = 'inbox' AND m.first_reply_at IS NOT NULL
                             THEN TIMESTAMPDIFF(MINUTE, m.message_date, m.first_reply_at) END) AS avg_response_minutes
             FROM `{$p}mailbox_accounts` a
             LEFT JOIN `{$p}mailbox_messages` m
                    ON m.account_id = a.id AND m.message_date BETWEEN ? AND ?
             WHERE a.id IN ({$in})
             GROUP BY a.id, a.name, a.email
             ORDER BY received DESC",
            [$start, $end]
        )->result();

        $by_staff = $this->db->query(
            "SELECT st.staffid, CONCAT(st.firstname, ' ', st.lastname) AS full_name,
                    SUM(m.folder = 'sent' AND m.sent_by = st.staffid) AS sent,
                    (SELECT COUNT(*) FROM `{$p}mailbox_messages` x
                      WHERE x.account_id IN ({$in}) AND x.assigned_to = st.staffid
                        AND x.folder = 'inbox' AND x.message_date BETWEEN ? AND ?) AS assigned,
                    (SELECT COUNT(*) FROM `{$p}mailbox_messages` x
                      WHERE x.account_id IN ({$in}) AND x.status_changed_by = st.staffid
                        AND x.conv_status = 'closed' AND x.folder = 'inbox'
                        AND x.status_changed_at BETWEEN ? AND ?) AS closed,
                    (SELECT AVG(TIMESTAMPDIFF(MINUTE, x.message_date, x.first_reply_at))
                       FROM `{$p}mailbox_messages` x
                      WHERE x.account_id IN ({$in}) AND x.first_reply_by = st.staffid
                        AND x.first_reply_at IS NOT NULL AND x.message_date BETWEEN ? AND ?) AS avg_response_minutes
             FROM `{$p}staff` st
             LEFT JOIN `{$p}mailbox_messages` m
                    ON m.account_id IN ({$in}) AND m.sent_by = st.staffid AND m.message_date BETWEEN ? AND ?
             WHERE st.active = 1
             GROUP BY st.staffid, st.firstname, st.lastname
             HAVING sent > 0 OR assigned > 0 OR closed > 0
             ORDER BY sent DESC",
            [$start, $end, $start, $end, $start, $end, $start, $end]
        )->result();

        $top_senders = $this->db->query(
            "SELECT from_email, MAX(from_name) AS from_name, COUNT(*) AS cnt
             FROM `{$p}mailbox_messages`
             WHERE account_id IN ({$in}) AND folder = 'inbox' AND message_date BETWEEN ? AND ?
             GROUP BY from_email
             ORDER BY cnt DESC
             LIMIT 10",
            [$start, $end]
        )->result();

        $by_hour = $this->db->query(
            "SELECT HOUR(message_date) AS h, COUNT(*) AS cnt
             FROM `{$p}mailbox_messages`
             WHERE account_id IN ({$in}) AND folder = 'inbox' AND message_date BETWEEN ? AND ?
             GROUP BY HOUR(message_date)",
            [$start, $end]
        )->result();

        return [
            'totals'      => $totals,
            'daily'       => $daily,
            'by_account'  => $by_account,
            'by_staff'    => $by_staff,
            'top_senders' => $top_senders,
            'by_hour'     => $by_hour,
        ];
    }

    /* ═════════════════════════ Misc lookups ═══════════════════════════════ */

    /**
     * Staff who can actually be assigned mail on the given accounts —
     * superadmins plus everyone the account is shared with.
     */
    public function assignable_staff(array $account_ids)
    {
        $p = db_prefix();

        if (!count($account_ids)) {
            return [];
        }
        $in = implode(',', array_map('intval', $account_ids));

        return $this->db->query(
            "SELECT DISTINCT st.staffid, CONCAT(st.firstname, ' ', st.lastname) AS full_name, st.email
             FROM `{$p}staff` st
             WHERE st.active = 1
               AND (st.admin = 1 OR st.staffid IN (
                    SELECT s.staff_id FROM `{$p}mailbox_account_staff` s WHERE s.account_id IN ({$in})
               ))
             ORDER BY st.firstname ASC"
        )->result();
    }
}
