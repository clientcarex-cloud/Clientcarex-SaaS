<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Mailbox — data access.
 */
class Mailbox_model extends App_Model
{
    public function __construct()
    {
        parent::__construct();
        mailbox_ensure_schema();
    }

    /* ─────────────────────────── Accounts ───────────────────────────────── */

    public function get_account($id, $with_creds = false)
    {
        $row = $this->db->where('id', (int) $id)->get(db_prefix() . 'mailbox_accounts')->row();

        if ($row && !$with_creds) {
            unset($row->smtp_password, $row->imap_password);
        }

        return $row;
    }

    /**
     * All accounts with assignment + unread info for the admin manager.
     */
    public function get_accounts_admin()
    {
        $p = db_prefix();

        $accounts = $this->db->query(
            "SELECT a.*,
                    (SELECT COUNT(*) FROM `{$p}mailbox_account_staff` s WHERE s.account_id = a.id) AS assigned_count,
                    (SELECT COUNT(*) FROM `{$p}mailbox_messages` m WHERE m.account_id = a.id AND m.folder = 'inbox' AND m.is_read = 0) AS unread_count,
                    (SELECT COUNT(*) FROM `{$p}mailbox_messages` m WHERE m.account_id = a.id) AS message_count
             FROM `{$p}mailbox_accounts` a ORDER BY a.name ASC"
        )->result();

        foreach ($accounts as $account) {
            unset($account->smtp_password, $account->imap_password);
            $account->assigned = $this->get_assignments($account->id);
        }

        return $accounts;
    }

    /**
     * Insert / update an account. Passwords are only re-encrypted when a new
     * value is posted — blank means "keep the current one".
     *
     * @return int account id
     */
    public function save_account(array $data, $id = 0)
    {
        $p  = db_prefix();
        $id = (int) $id;

        $row = [
            'name'               => trim($data['name']),
            'email'              => trim($data['email']),
            'from_name'          => trim($data['from_name'] ?? ''),
            'signature'          => $data['signature'] ?? '',
            'provider'           => $data['provider'] ?? 'custom',
            'smtp_host'          => trim($data['smtp_host'] ?? ''),
            'smtp_port'          => (int) ($data['smtp_port'] ?? 465),
            'smtp_encryption'    => in_array($data['smtp_encryption'] ?? '', ['ssl', 'tls', 'none']) ? $data['smtp_encryption'] : 'ssl',
            'smtp_username'      => trim($data['smtp_username'] ?? ''),
            'imap_host'          => trim($data['imap_host'] ?? ''),
            'imap_port'          => (int) ($data['imap_port'] ?? 993),
            'imap_encryption'    => in_array($data['imap_encryption'] ?? '', ['ssl', 'tls', 'starttls', 'none']) ? $data['imap_encryption'] : 'ssl',
            'imap_username'      => trim($data['imap_username'] ?? ''),
            'imap_folder'        => trim($data['imap_folder'] ?? '') !== '' ? trim($data['imap_folder']) : 'INBOX',
            'imap_sent_folder'   => trim($data['imap_sent_folder'] ?? ''),
            'imap_validate_cert' => !empty($data['imap_validate_cert']) ? 1 : 0,
            'active'             => !empty($data['active']) ? 1 : 0,
        ];

        if (($data['smtp_password'] ?? '') !== '') {
            $row['smtp_password'] = mailbox_encrypt($data['smtp_password']);
        }
        if (($data['imap_password'] ?? '') !== '') {
            $row['imap_password'] = mailbox_encrypt($data['imap_password']);
        } elseif (($data['smtp_password'] ?? '') !== '' && $id === 0) {
            // New account with a single password typed: reuse it for IMAP.
            $row['imap_password'] = mailbox_encrypt($data['smtp_password']);
        }

        if ($id > 0) {
            // Server / credential changes invalidate previous test results
            // and restart the incremental sync when the source changed.
            $current = $this->get_account($id);
            if ($current && ($current->imap_host !== $row['imap_host'] || $current->imap_folder !== $row['imap_folder']
                || ($current->imap_username !== $row['imap_username']))) {
                $row['last_uid']       = 0;
                $row['initial_synced'] = 0;
            }

            $this->db->where('id', $id)->update($p . 'mailbox_accounts', $row);

            return $id;
        }

        $row['created_by'] = get_staff_user_id();
        $row['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert($p . 'mailbox_accounts', $row);

        return (int) $this->db->insert_id();
    }

    /**
     * Delete an account and everything under it — messages, attachment rows
     * and the files on disk.
     */
    public function delete_account($id)
    {
        $p  = db_prefix();
        $id = (int) $id;

        $message_ids = array_column(
            $this->db->query("SELECT id FROM `{$p}mailbox_messages` WHERE account_id = ?", [$id])->result_array(),
            'id'
        );

        if (count($message_ids)) {
            $this->db->where_in('message_id', $message_ids)->delete($p . 'mailbox_attachments');
        }
        $this->db->where('account_id', $id)->delete($p . 'mailbox_messages');
        $this->db->where('account_id', $id)->delete($p . 'mailbox_account_staff');
        $this->db->where('id', $id)->delete($p . 'mailbox_accounts');

        $dir = FCPATH . 'uploads' . DIRECTORY_SEPARATOR . 'mailbox' . DIRECTORY_SEPARATOR . $id;
        if (is_dir($dir)) {
            $this->rrmdir($dir);
        }

        return true;
    }

    private function rrmdir($dir)
    {
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            is_dir($path) ? $this->rrmdir($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    /* ────────────────────────── Assignments ─────────────────────────────── */

    public function get_assignments($account_id)
    {
        $p = db_prefix();

        return $this->db->query(
            "SELECT s.staff_id, CONCAT(st.firstname, ' ', st.lastname) AS full_name, st.email
             FROM `{$p}mailbox_account_staff` s
             JOIN `{$p}staff` st ON st.staffid = s.staff_id
             WHERE s.account_id = ?
             ORDER BY st.firstname",
            [(int) $account_id]
        )->result();
    }

    public function set_assignments($account_id, array $staff_ids)
    {
        $p          = db_prefix();
        $account_id = (int) $account_id;
        $staff_ids  = array_values(array_unique(array_map('intval', $staff_ids)));

        $this->db->where('account_id', $account_id)->delete($p . 'mailbox_account_staff');

        foreach ($staff_ids as $staff_id) {
            if ($staff_id > 0) {
                $this->db->insert($p . 'mailbox_account_staff', [
                    'account_id' => $account_id,
                    'staff_id'   => $staff_id,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    public function get_staff()
    {
        return $this->db->query(
            'SELECT staffid, CONCAT(firstname, " ", lastname) AS full_name, email, admin
             FROM `' . db_prefix() . 'staff` WHERE active = 1 ORDER BY firstname'
        )->result();
    }

    /* ─────────────────────────── Messages ───────────────────────────────── */

    /**
     * Folder counters for the accounts visible to the current user.
     *
     * @return array [account_id => ['inbox_unread' => n, 'drafts' => n]]
     */
    public function get_counts(array $account_ids)
    {
        if (!count($account_ids)) {
            return [];
        }
        $p     = db_prefix();
        $in    = implode(',', array_map('intval', $account_ids));
        $me    = (int) get_staff_user_id();
        $out   = [];

        $rows = $this->db->query(
            "SELECT account_id,
                    SUM(CASE WHEN folder = 'inbox' AND is_read = 0 THEN 1 ELSE 0 END) AS inbox_unread,
                    SUM(CASE WHEN folder = 'drafts' THEN 1 ELSE 0 END) AS drafts,
                    SUM(CASE WHEN folder = 'scheduled' THEN 1 ELSE 0 END) AS scheduled,
                    SUM(CASE WHEN folder = 'inbox' AND assigned_to = {$me} AND conv_status != 'closed' THEN 1 ELSE 0 END) AS mine,
                    SUM(CASE WHEN folder = 'inbox' AND assigned_to = 0 AND conv_status != 'closed' THEN 1 ELSE 0 END) AS unassigned,
                    SUM(CASE WHEN folder = 'inbox' AND sla_breached = 1 AND conv_status != 'closed' THEN 1 ELSE 0 END) AS overdue
             FROM `{$p}mailbox_messages`
             WHERE account_id IN ($in)
             GROUP BY account_id"
        )->result();

        foreach ($rows as $row) {
            $out[(int) $row->account_id] = [
                'inbox_unread' => (int) $row->inbox_unread,
                'drafts'       => (int) $row->drafts,
                'scheduled'    => (int) $row->scheduled,
                'mine'         => (int) $row->mine,
                'unassigned'   => (int) $row->unassigned,
                'overdue'      => (int) $row->overdue,
            ];
        }

        return $out;
    }

    /**
     * Paged message list for one folder across one or more accounts.
     *
     * $filters keys (all optional): folder, search, label_id, assigned
     * ('me' | 'none' | staff id), status, overdue. The free-text search string
     * is run through mailbox_parse_search() first, so Gmail-style operators
     * (from: subject: has:attachment is:unread …) narrow the query server side.
     */
    public function get_messages(array $account_ids, array $filters, $page, $per_page)
    {
        if (!count($account_ids)) {
            return ['rows' => [], 'total' => 0];
        }

        $p      = db_prefix();
        $in     = implode(',', array_map('intval', $account_ids));
        $folder = (string) ($filters['folder'] ?? 'inbox');
        $where  = "m.account_id IN ($in)";
        $binds  = [];
        $join   = '';

        $like = function ($value) {
            return '%' . $this->db->escape_like_str($value) . '%';
        };

        /* ── Folder / virtual folder ── */
        if ($folder === 'starred') {
            $where .= " AND m.is_starred = 1 AND m.folder != 'trash'";
        } elseif ($folder === 'mine') {
            $where .= ' AND m.folder = \'inbox\' AND m.assigned_to = ' . (int) get_staff_user_id() . " AND m.conv_status != 'closed'";
        } elseif ($folder === 'unassigned') {
            $where .= " AND m.folder = 'inbox' AND m.assigned_to = 0 AND m.conv_status != 'closed'";
        } elseif ($folder === 'overdue') {
            $where .= " AND m.folder = 'inbox' AND m.sla_breached = 1 AND m.conv_status != 'closed'";
        } else {
            $where .= ' AND m.folder = ?';
            $binds[] = $folder;
        }

        /* ── Label ── */
        if (!empty($filters['label_id'])) {
            $join .= " JOIN `{$p}mailbox_message_labels` fml ON fml.message_id = m.id AND fml.label_id = " . (int) $filters['label_id'];
        }

        /* ── Shared-inbox facets ── */
        $assigned = (string) ($filters['assigned'] ?? '');
        if ($assigned === 'me') {
            $where .= ' AND m.assigned_to = ' . (int) get_staff_user_id();
        } elseif ($assigned === 'none') {
            $where .= ' AND m.assigned_to = 0';
        } elseif ($assigned !== '' && ctype_digit($assigned)) {
            $where .= ' AND m.assigned_to = ' . (int) $assigned;
        }

        if (!empty($filters['status'])) {
            $where .= ' AND m.conv_status = ?';
            $binds[] = (string) $filters['status'];
        }
        if (!empty($filters['overdue'])) {
            $where .= ' AND m.sla_breached = 1';
        }

        /* ── Free text + operators ── */
        $search = mailbox_parse_search((string) ($filters['search'] ?? ''));

        if ($search['text'] !== '') {
            $where .= ' AND (m.subject LIKE ? OR m.from_email LIKE ? OR m.from_name LIKE ? OR m.snippet LIKE ? OR m.to_emails LIKE ? OR m.body_plain LIKE ?)';
            $needle = $like($search['text']);
            array_push($binds, $needle, $needle, $needle, $needle, $needle, $needle);
        }
        if ($search['from'] !== '') {
            $where .= ' AND (m.from_email LIKE ? OR m.from_name LIKE ?)';
            array_push($binds, $like($search['from']), $like($search['from']));
        }
        if ($search['to'] !== '') {
            $where .= ' AND (m.to_emails LIKE ? OR m.cc_emails LIKE ?)';
            array_push($binds, $like($search['to']), $like($search['to']));
        }
        if ($search['subject'] !== '') {
            $where .= ' AND m.subject LIKE ?';
            $binds[] = $like($search['subject']);
        }
        if ($search['label'] !== '') {
            $join .= " JOIN `{$p}mailbox_message_labels` sml ON sml.message_id = m.id"
                . " JOIN `{$p}mailbox_labels` sl ON sl.id = sml.label_id AND sl.name LIKE ?";
            // Join binds come before the WHERE binds in the final statement.
            array_unshift($binds, $like($search['label']));
        }
        if ($search['has_attachment']) {
            $where .= ' AND m.has_attachments = 1';
        }
        if ($search['is_unread']) {
            $where .= ' AND m.is_read = 0';
        }
        if ($search['is_read']) {
            $where .= ' AND m.is_read = 1';
        }
        if ($search['is_starred']) {
            $where .= ' AND m.is_starred = 1';
        }
        if ($search['status'] !== '') {
            $where .= ' AND m.conv_status = ?';
            $binds[] = $search['status'];
        }
        if ($search['overdue']) {
            $where .= ' AND m.sla_breached = 1';
        }
        if ($search['assigned'] === 'me') {
            $where .= ' AND m.assigned_to = ' . (int) get_staff_user_id();
        } elseif (in_array($search['assigned'], ['none', 'nobody', 'unassigned'], true)) {
            $where .= ' AND m.assigned_to = 0';
        }
        if ($search['after'] !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $search['after'])) {
            $where .= ' AND m.message_date >= ?';
            $binds[] = $search['after'] . ' 00:00:00';
        }
        if ($search['before'] !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $search['before'])) {
            $where .= ' AND m.message_date <= ?';
            $binds[] = $search['before'] . ' 23:59:59';
        }

        $total = (int) $this->db->query(
            "SELECT COUNT(DISTINCT m.id) AS c FROM `{$p}mailbox_messages` m {$join} WHERE $where",
            $binds
        )->row()->c;

        $offset = max(0, ($page - 1) * $per_page);
        // Scheduled mail is ordered by when it will go out, not when it was written.
        $order  = $folder === 'scheduled' ? 'm.scheduled_at ASC, m.id ASC' : 'm.message_date DESC, m.id DESC';

        $rows = $this->db->query(
            "SELECT DISTINCT m.id, m.account_id, m.folder, m.subject, m.from_name, m.from_email, m.to_emails,
                    m.snippet, m.has_attachments, m.is_read, m.is_starred, m.message_date, m.sent_by, m.thread_id,
                    m.assigned_to, m.conv_status, m.sla_due_at, m.sla_breached, m.first_reply_at,
                    m.scheduled_at, m.legal_hold, m.send_error,
                    CONCAT(st.firstname, ' ', st.lastname) AS assigned_name
             FROM `{$p}mailbox_messages` m
             {$join}
             LEFT JOIN `{$p}staff` st ON st.staffid = m.assigned_to
             WHERE $where
             ORDER BY {$order}
             LIMIT $per_page OFFSET $offset",
            $binds
        )->result();

        return ['rows' => $rows, 'total' => $total];
    }

    public function get_message($id)
    {
        $p   = db_prefix();
        $row = $this->db->query(
            "SELECT m.*, CONCAT(st.firstname, ' ', st.lastname) AS sent_by_name,
                    CONCAT(asg.firstname, ' ', asg.lastname) AS assigned_name
             FROM `{$p}mailbox_messages` m
             LEFT JOIN `{$p}staff` st ON st.staffid = m.sent_by
             LEFT JOIN `{$p}staff` asg ON asg.staffid = m.assigned_to
             WHERE m.id = ? LIMIT 1",
            [(int) $id]
        )->row();

        if ($row) {
            $row->attachments = $this->get_attachments($row->id);
        }

        return $row;
    }

    /**
     * Other messages of the same conversation (oldest first), excluding the
     * one being viewed.
     */
    public function get_thread_siblings($message)
    {
        if (!$message->thread_id) {
            return [];
        }
        $p = db_prefix();

        return $this->db->query(
            "SELECT id, folder, subject, from_name, from_email, snippet, message_date, sent_by, is_read
             FROM `{$p}mailbox_messages`
             WHERE account_id = ? AND thread_id = ? AND id != ? AND folder != 'trash'
             ORDER BY message_date ASC, id ASC",
            [(int) $message->account_id, (int) $message->thread_id, (int) $message->id]
        )->result();
    }

    public function get_attachments($message_id)
    {
        return $this->db->where('message_id', (int) $message_id)
            ->get(db_prefix() . 'mailbox_attachments')->result();
    }

    public function get_attachment($id)
    {
        return $this->db->where('id', (int) $id)
            ->get(db_prefix() . 'mailbox_attachments')->row();
    }

    /* ────────────────────────── Message actions ─────────────────────────── */

    public function set_read($id, $read)
    {
        $this->db->where('id', (int) $id)
            ->update(db_prefix() . 'mailbox_messages', ['is_read' => $read ? 1 : 0]);
    }

    public function set_starred($id, $starred)
    {
        $this->db->where('id', (int) $id)
            ->update(db_prefix() . 'mailbox_messages', ['is_starred' => $starred ? 1 : 0]);
    }

    /**
     * Move between local folders. Trash remembers where the message came
     * from so restore puts it back.
     */
    public function move_message($message, $target)
    {
        $update = ['folder' => $target];

        if ($target === 'trash') {
            $update['orig_folder'] = $message->folder;
        } elseif ($message->folder === 'trash' && $target === 'restore') {
            $update['folder']      = $message->orig_folder ?: 'inbox';
            $update['orig_folder'] = null;
        }

        $this->db->where('id', (int) $message->id)
            ->update(db_prefix() . 'mailbox_messages', $update);
    }

    public function delete_message_forever($message)
    {
        $p = db_prefix();

        $this->db->where('message_id', (int) $message->id)->delete($p . 'mailbox_attachments');
        $this->db->where('id', (int) $message->id)->delete($p . 'mailbox_messages');

        $dir = FCPATH . 'uploads' . DIRECTORY_SEPARATOR . 'mailbox' . DIRECTORY_SEPARATOR
            . (int) $message->account_id . DIRECTORY_SEPARATOR . (int) $message->id;
        if (is_dir($dir)) {
            $this->rrmdir($dir);
        }
    }

    /* ─────────────────────── Outgoing / drafts ──────────────────────────── */

    /**
     * Store an outgoing (or draft) message locally.
     *
     * @return int local message id
     */
    public function store_outgoing($account, array $data, $folder, $message_id = '', $draft_id = 0)
    {
        $p = db_prefix();

        $to_json = json_encode(array_map(function ($e) {
            return ['name' => '', 'email' => $e];
        }, $data['to']), JSON_UNESCAPED_UNICODE);
        $cc_json = json_encode(array_map(function ($e) {
            return ['name' => '', 'email' => $e];
        }, $data['cc'] ?? []), JSON_UNESCAPED_UNICODE);
        $bcc_json = json_encode(array_map(function ($e) {
            return ['name' => '', 'email' => $e];
        }, $data['bcc'] ?? []), JSON_UNESCAPED_UNICODE);

        $row = [
            'account_id'   => $account->id,
            'folder'       => $folder,
            'uid'          => 0,
            'message_id'   => mb_substr($message_id, 0, 255),
            'in_reply_to'  => mb_substr((string) ($data['in_reply_to'] ?? ''), 0, 255),
            'refs'         => (string) ($data['references'] ?? ''),
            'subject'      => mb_substr((string) $data['subject'], 0, 500),
            'from_name'    => $account->from_name !== '' ? $account->from_name : $account->name,
            'from_email'   => $account->email,
            'to_emails'    => $to_json,
            'cc_emails'    => $cc_json,
            'bcc_emails'   => $bcc_json,
            'reply_to'     => '',
            'body_html'    => $data['body_html'],
            'body_plain'   => trim(strip_tags(preg_replace('#<br\s*/?>#i', "\n", $data['body_html']))),
            'snippet'      => mailbox_snippet($data['body_html']),
            'is_read'      => 1,
            'is_starred'   => 0,
            'sent_by'      => get_staff_user_id(),
            'message_date' => date('Y-m-d H:i:s'),
            'created_at'   => date('Y-m-d H:i:s'),
        ];

        $row['thread_id'] = mailbox_resolve_thread_id($account->id, $row['in_reply_to'], $row['refs'], $row['subject']);

        // Queued mail (send later / undo-send) parks in the 'scheduled' folder
        // until the dispatcher picks it up.
        if ($folder === 'scheduled') {
            $row['scheduled_at']  = $data['scheduled_at'] ?? date('Y-m-d H:i:s');
            $row['send_attempts'] = 0;
            $row['send_error']    = null;
        } elseif ($folder === 'sent') {
            $row['scheduled_at'] = null;
            $row['send_error']   = null;
        }

        if ($draft_id > 0) {
            // Draft being sent / re-saved: reuse the row (attachments stay linked).
            $this->db->where('id', $draft_id)->where('account_id', $account->id)
                ->update($p . 'mailbox_messages', $row);
            $id = $draft_id;
        } else {
            $this->db->insert($p . 'mailbox_messages', $row);
            $id = (int) $this->db->insert_id();
        }

        if (!$row['thread_id']) {
            $this->db->where('id', $id)->update($p . 'mailbox_messages', ['thread_id' => $id]);
        }

        return $id;
    }
}
