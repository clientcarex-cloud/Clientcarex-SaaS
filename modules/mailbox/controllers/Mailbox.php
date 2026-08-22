<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Mailbox — admin controller.
 *
 * URL map (admin/mailbox/...):
 *   index                webmail app (accounts assigned to the logged-in user)
 *   bootstrap            JSON: accounts + folder counters
 *   messages             JSON: paged folder listing
 *   message/{id}         JSON: full message + thread, marks read
 *   action               POST: read/unread/star/unstar/archive/trash/restore/delete
 *   send                 POST multipart: send (or reply/forward) through the account SMTP
 *   save_draft           POST: store a draft
 *   sync                 POST: force IMAP pull
 *   attachment/{id}      download (access-checked)
 *
 * Superadmin only:
 *   accounts             account manager + interactive connect wizard
 *   account_save         POST: create / update account
 *   account_get/{id}     JSON: account for editing (no passwords)
 *   account_delete       POST
 *   assignments          POST: replace staff assignments
 *   test_smtp            POST: live SMTP check (posted or stored credentials)
 *   test_imap            POST: live IMAP check + folder list
 */
class Mailbox extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('mailbox/mailbox_model', 'mailbox_model');
        $this->load->model('mailbox/mailbox_pro_model', 'mbx_pro');

        if (!mailbox_staff_can_access()) {
            access_denied('Mailbox');
        }
    }

    /* ─────────────────────────── Webmail ────────────────────────────────── */

    public function index()
    {
        $data['accounts']    = mailbox_accounts_for_staff(get_staff_user_id());
        $data['is_super']    = mailbox_staff_is_super();
        $data['can_manage']  = mailbox_can_manage();
        $data['can_reports'] = mailbox_can_view_reports();
        $data['imap_ext']    = function_exists('imap_open');
        $data['operators']   = mailbox_search_operators();
        $data['title']       = _l('mailbox');
        $this->load->view('app', $data);
    }

    public function bootstrap()
    {
        $accounts = mailbox_accounts_for_staff(get_staff_user_id());
        $ids      = array_map(function ($a) {
            return (int) $a->id;
        }, $accounts);

        // Signatures for the composer (accounts list has no creds anyway).
        $signatures = [];
        foreach ($ids as $id) {
            $account = $this->mailbox_model->get_account($id);
            $signatures[$id] = $account ? (string) $account->signature : '';
        }

        echo json_encode([
            'accounts'   => $accounts,
            'counts'     => $this->mailbox_model->get_counts($ids),
            'signatures' => $signatures,
            'staff_id'   => get_staff_user_id(),
            'labels'     => $this->mbx_pro->get_labels($ids),
            'templates'  => $this->mbx_pro->get_templates($ids),
            'staff'      => $this->mbx_pro->assignable_staff($ids),
            'features'   => $this->feature_flags(),
        ]);
    }

    /**
     * Runtime feature switches the front end needs to know about.
     */
    private function feature_flags()
    {
        return [
            'status'       => (int) mailbox_opt('mailbox_status_enabled', '1') === 1,
            'presence'     => (int) mailbox_opt('mailbox_presence_enabled', '1') === 1,
            'sla'          => (int) mailbox_opt('mailbox_sla_enabled', '0') === 1,
            'schedule'     => (int) mailbox_opt('mailbox_schedule_enabled', '1') === 1,
            'undo_seconds' => max(0, (int) mailbox_opt('mailbox_undo_send_seconds', '10')),
            'can_manage'   => mailbox_can_manage(),
            'statuses'     => mailbox_statuses(),
        ];
    }

    public function messages()
    {
        $account_ids = $this->allowed_account_ids($this->input->get('account'));
        $page        = max(1, (int) $this->input->get('page'));
        $per_page    = 30;

        $filters = [
            'folder'   => $this->clean_folder($this->input->get('folder')),
            'search'   => trim((string) $this->input->get('search')),
            'label_id' => (int) $this->input->get('label'),
            'assigned' => (string) $this->input->get('assigned'),
            'status'   => in_array($this->input->get('status'), mailbox_statuses(), true) ? $this->input->get('status') : '',
        ];

        $result = $this->mailbox_model->get_messages($account_ids, $filters, $page, $per_page);

        $ids = array_map(function ($row) {
            return (int) $row->id;
        }, $result['rows']);

        echo json_encode([
            'rows'        => $result['rows'],
            'labels'      => $this->mbx_pro->labels_for_messages($ids),
            'notes'       => $this->mbx_pro->note_counts_for_messages($ids),
            'total'       => $result['total'],
            'page'        => $page,
            'total_pages' => max(1, (int) ceil($result['total'] / $per_page)),
            'counts'      => $this->mailbox_model->get_counts($account_ids),
            'server_time' => date('Y-m-d H:i:s'),
        ]);
    }

    public function message($id = '')
    {
        $message = $this->mailbox_model->get_message((int) $id);
        if (!$message || !$this->can_touch_account($message->account_id)) {
            ajax_access_denied();
        }

        if (!$message->is_read) {
            $this->mailbox_model->set_read($message->id, true);
            $message->is_read = 1;

            // Who opened which mail is exactly what a compliance review asks
            // for, so the read event is audited once — on the transition.
            mailbox_audit('read', [
                'account_id' => $message->account_id,
                'message_id' => $message->id,
                'subject'    => $message->subject,
                'details'    => 'Opened message from ' . $message->from_email,
            ]);
        }

        $thread_id = (int) ($message->thread_id ?: $message->id);
        $labels    = $this->mbx_pro->labels_for_messages([(int) $message->id]);

        echo json_encode([
            'message'  => $message,
            'thread'   => $this->mailbox_model->get_thread_siblings($message),
            'labels'   => $labels[(int) $message->id] ?? [],
            'notes'    => $this->mbx_pro->get_notes($message->account_id, $thread_id, $message->id),
            'crm'      => $this->mbx_pro->crm_match($message->from_email),
            'presence' => mailbox_presence_touch($message->account_id, $thread_id, 'viewing'),
        ]);
    }

    public function action()
    {
        if (!$this->input->post()) {
            ajax_access_denied();
        }

        $ids     = array_map('intval', (array) $this->input->post('ids'));
        $action  = (string) $this->input->post('do');
        $done    = 0;
        $blocked = 0;

        foreach ($ids as $id) {
            $message = $this->mailbox_model->get_message($id);
            if (!$message || !$this->can_touch_account($message->account_id)) {
                continue;
            }

            // Legal hold outranks every destructive action, including the
            // superadmin's — that is the whole point of a hold.
            if (!empty($message->legal_hold) && in_array($action, ['trash', 'delete'], true)) {
                $blocked++;
                continue;
            }

            switch ($action) {
                case 'read':    $this->mailbox_model->set_read($id, true); break;
                case 'unread':  $this->mailbox_model->set_read($id, false); break;
                case 'star':    $this->mailbox_model->set_starred($id, true); break;
                case 'unstar':  $this->mailbox_model->set_starred($id, false); break;
                case 'archive':
                    $this->mailbox_model->move_message($message, 'archive');
                    $this->audit_message('archived', $message);
                    break;
                case 'inbox':   $this->mailbox_model->move_message($message, 'inbox'); break;
                case 'trash':
                    $this->mailbox_model->move_message($message, 'trash');
                    $this->audit_message('trashed', $message);
                    break;
                case 'restore':
                    $this->mailbox_model->move_message($message, 'restore');
                    $this->audit_message('restored', $message);
                    break;
                case 'delete':
                    // Permanent delete only out of trash, drafts or the
                    // scheduled queue.
                    if (in_array($message->folder, ['trash', 'drafts', 'scheduled'], true)) {
                        $this->audit_message('deleted', $message, 'Permanently deleted from ' . $message->folder);
                        $this->mailbox_model->delete_message_forever($message);
                    }
                    break;
                default:
                    continue 2;
            }
            $done++;
        }

        echo json_encode([
            'success' => $done > 0,
            'done'    => $done,
            'blocked' => $blocked,
            'error'   => $blocked > 0 ? _l('mailbox_legal_hold_blocked') : '',
        ]);
    }

    /**
     * Audit shortcut for message-scoped events.
     */
    private function audit_message($action, $message, $details = '')
    {
        mailbox_audit($action, [
            'account_id' => $message->account_id,
            'message_id' => $message->id,
            'subject'    => $message->subject,
            'details'    => $details !== '' ? $details : ('Folder was ' . $message->folder),
        ]);
    }

    /* ─────────────────────────── Compose ────────────────────────────────── */

    public function send()
    {
        if (!$this->input->post()) {
            ajax_access_denied();
        }

        $account_id = (int) $this->input->post('account_id');
        if (!$this->can_touch_account($account_id)) {
            ajax_access_denied();
        }

        $account = $this->mailbox_model->get_account($account_id, true);
        if (!$account || !$account->active || $account->smtp_host === '') {
            echo json_encode(['success' => false, 'error' => _l('mailbox_no_smtp')]);

            return;
        }

        $data = $this->compose_data_from_post();

        if (!count($data['to'])) {
            echo json_encode(['success' => false, 'error' => _l('mailbox_no_recipient')]);

            return;
        }

        /* ── Outbound DLP screening ──────────────────────────────────────
           In "block" mode nothing leaves, ever. In "warn" mode the composer
           gets the hit list back once and re-posts with dlp_ack when the user
           accepts responsibility — which is itself audited. */
        $recipients = array_merge($data['to'], $data['cc'], $data['bcc']);
        $dlp        = mailbox_dlp_check($data['subject'], $data['body_html'], $recipients);

        if (count($dlp['hits'])) {
            if ($dlp['block'] || !$this->input->post('dlp_ack')) {
                if ($dlp['block']) {
                    mailbox_audit('dlp_blocked', [
                        'account_id' => $account_id,
                        'subject'    => $data['subject'],
                        'details'    => 'Blocked — matched: ' . implode(', ', $dlp['hits'])
                            . ' | to: ' . implode(', ', $recipients),
                    ]);
                }

                echo json_encode([
                    'success' => false,
                    'error'   => $dlp['block'] ? _l('mailbox_dlp_blocked') : '',
                    'dlp'     => ['blocked' => $dlp['block'], 'hits' => $dlp['hits']],
                ]);

                return;
            }

            mailbox_audit('sent', [
                'account_id' => $account_id,
                'subject'    => $data['subject'],
                'details'    => 'DLP warning overridden — matched: ' . implode(', ', $dlp['hits']),
            ]);
        }

        // Reply / forward threading — inherit headers from the source message.
        $reply_to_id = (int) $this->input->post('reply_to_id');
        $source      = null;
        if ($reply_to_id > 0) {
            $source = $this->mailbox_model->get_message($reply_to_id);
            if ($source && (int) $source->account_id === $account_id && $source->message_id !== '') {
                $data['in_reply_to'] = $source->message_id;
                $data['references']  = trim($source->refs . ' <' . $source->message_id . '>');
            }
        }

        $draft_id = (int) $this->input->post('draft_id');

        /* ── Queue instead of sending? ───────────────────────────────────
           Two reasons to park the message: an explicit send-later time, or
           the undo-send window. Both land in the 'scheduled' folder and are
           picked up by mailbox_dispatch_scheduled(). */
        $queue_at = $this->queue_time();

        if ($queue_at !== '') {
            $data['scheduled_at'] = $queue_at;
            $local_id = $this->mailbox_model->store_outgoing($account, $data, 'scheduled', '', $draft_id);
            $this->attach_uploads_to($account, $local_id);

            $explicit = trim((string) $this->input->post('schedule_at')) !== '';
            if ($explicit) {
                mailbox_audit('scheduled', [
                    'account_id' => $account_id,
                    'message_id' => $local_id,
                    'subject'    => $data['subject'],
                    'details'    => 'Queued for ' . $queue_at,
                ]);
            }

            echo json_encode([
                'success'      => true,
                'id'           => $local_id,
                'queued'       => true,
                'scheduled'    => $explicit,
                'scheduled_at' => $queue_at,
                'undo_seconds' => $explicit ? 0 : max(0, strtotime($queue_at) - time()),
            ]);

            return;
        }

        // Uploaded attachments land in a scratch dir first, are attached to
        // the outgoing mail, then moved into permanent storage on success.
        $tmp_attachments = $this->collect_uploads();
        $data['attachments'] = $tmp_attachments;

        $result = mailbox_send_mail($account, $data);

        if (!$result['success']) {
            foreach ($tmp_attachments as $att) {
                @unlink($att['path']);
            }
            echo json_encode(['success' => false, 'error' => $result['error']]);

            return;
        }

        $local_id = $this->mailbox_model->store_outgoing($account, $data, 'sent', $result['message_id'], $draft_id);

        foreach ($tmp_attachments as $att) {
            $content = @file_get_contents($att['path']);
            if ($content !== false) {
                mailbox_store_attachment($account->id, $local_id, $att['name'], $content);
            }
            @unlink($att['path']);
        }
        if (count($tmp_attachments)) {
            $this->db->where('id', $local_id)
                ->update(db_prefix() . 'mailbox_messages', ['has_attachments' => 1]);
        }

        // Mark SMTP verified and mirror to the IMAP sent folder if configured.
        $this->db->where('id', $account->id)
            ->update(db_prefix() . 'mailbox_accounts', ['smtp_ok' => 1]);
        mailbox_append_to_sent($account, $result['raw']);

        // A delivered reply stops the first-response clock for the whole thread.
        if ($source) {
            mailbox_sla_stop($account_id, $source);
        }

        mailbox_audit('sent', [
            'account_id' => $account_id,
            'message_id' => $local_id,
            'subject'    => $data['subject'],
            'details'    => 'To: ' . implode(', ', $data['to'])
                . (count($data['cc']) ? ' | Cc: ' . implode(', ', $data['cc']) : '')
                . (count($data['bcc']) ? ' | Bcc: ' . implode(', ', $data['bcc']) : ''),
        ]);

        echo json_encode(['success' => true, 'id' => $local_id]);
    }

    /**
     * When (if at all) should this send be parked rather than delivered now?
     *
     * @return string 'Y-m-d H:i:s', or '' to send immediately
     */
    private function queue_time()
    {
        $requested = trim((string) $this->input->post('schedule_at'));

        if ($requested !== '' && (int) mailbox_opt('mailbox_schedule_enabled', '1') === 1) {
            $timestamp = strtotime($requested);
            // Anything in the past means "as soon as possible", not "never".
            if ($timestamp !== false) {
                return date('Y-m-d H:i:s', max($timestamp, time()));
            }
        }

        $undo = max(0, (int) mailbox_opt('mailbox_undo_send_seconds', '10'));
        if ($undo > 0 && !$this->input->post('send_now')) {
            return date('Y-m-d H:i:s', time() + $undo);
        }

        return '';
    }

    /**
     * Move composer uploads into permanent storage under a queued message.
     */
    private function attach_uploads_to($account, $local_id)
    {
        $uploads = $this->collect_uploads();
        $saved   = 0;

        foreach ($uploads as $upload) {
            $content = @file_get_contents($upload['path']);
            if ($content !== false && mailbox_store_attachment($account->id, $local_id, $upload['name'], $content)) {
                $saved++;
            }
            @unlink($upload['path']);
        }

        if ($saved > 0) {
            $this->db->where('id', (int) $local_id)
                ->update(db_prefix() . 'mailbox_messages', ['has_attachments' => 1]);
        }

        return $saved;
    }

    public function save_draft()
    {
        if (!$this->input->post()) {
            ajax_access_denied();
        }

        $account_id = (int) $this->input->post('account_id');
        if (!$this->can_touch_account($account_id)) {
            ajax_access_denied();
        }

        $account  = $this->mailbox_model->get_account($account_id);
        $data     = $this->compose_data_from_post();
        $draft_id = (int) $this->input->post('draft_id');

        // Only overwrite a row that really is one of this account's drafts.
        if ($draft_id > 0) {
            $existing = $this->mailbox_model->get_message($draft_id);
            if (!$existing || $existing->folder !== 'drafts' || (int) $existing->account_id !== $account_id) {
                $draft_id = 0;
            }
        }

        $id = $this->mailbox_model->store_outgoing($account, $data, 'drafts', '', $draft_id);

        echo json_encode(['success' => true, 'id' => $id]);
    }

    /* ──────────────────────────── Sync ──────────────────────────────────── */

    public function sync()
    {
        $account_ids = $this->allowed_account_ids($this->input->post('account'));
        $imported    = 0;
        $errors      = [];

        foreach ($account_ids as $id) {
            $account = $this->mailbox_model->get_account($id, true);
            if (!$account) {
                continue;
            }
            $result = mailbox_sync_account($account, (bool) $this->input->post('force'));
            $imported += $result['imported'];
            if ($result['error'] !== '') {
                $errors[] = $account->email . ': ' . $result['error'];
            }
        }

        // Piggy-back the poll: queued mail should not wait for the next cron
        // tick just because nobody opened the CRM's cron URL recently.
        $dispatched = mailbox_dispatch_scheduled($account_ids);

        echo json_encode([
            'success'    => true,
            'imported'   => $imported,
            'errors'     => $errors,
            'dispatched' => $dispatched['sent'],
        ]);
    }

    /**
     * Push queued mail out right now — called by the composer the moment an
     * undo-send window expires, so "sent" really means sent.
     */
    public function dispatch()
    {
        $account_ids = $this->allowed_account_ids($this->input->post('account'));
        $result      = mailbox_dispatch_scheduled($account_ids);

        echo json_encode(['success' => true, 'sent' => $result['sent'], 'failed' => $result['failed']]);
    }

    /**
     * Undo / cancel: pull a queued message back into drafts so it can be
     * edited or thrown away.
     */
    public function cancel_scheduled()
    {
        if (!$this->input->post()) {
            ajax_access_denied();
        }

        $ids  = array_map('intval', (array) $this->input->post('ids'));
        $done = 0;

        foreach ($ids as $id) {
            $message = $this->mailbox_model->get_message($id);
            if (!$message || $message->folder !== 'scheduled' || !$this->can_touch_account($message->account_id)) {
                continue;
            }

            $this->db->where('id', $id)->update(db_prefix() . 'mailbox_messages', [
                'folder'        => 'drafts',
                'scheduled_at'  => null,
                'send_attempts' => 0,
                'send_error'    => null,
            ]);

            mailbox_audit('unscheduled', [
                'account_id' => $message->account_id,
                'message_id' => $message->id,
                'subject'    => $message->subject,
                'details'    => 'Queued send cancelled, returned to drafts',
            ]);
            $done++;
        }

        echo json_encode(['success' => $done > 0, 'done' => $done]);
    }

    /* ═══════════════════ Shared inbox: assign / status ════════════════════ */

    public function assign()
    {
        if (!$this->input->post()) {
            ajax_access_denied();
        }

        $ids = $this->owned_message_ids((array) $this->input->post('ids'));
        if (!count($ids)) {
            ajax_access_denied();
        }

        $staff_id = (int) $this->input->post('staff_id');

        // Only someone who can actually open the mailbox may own its mail.
        if ($staff_id > 0) {
            $allowed = array_map(function ($s) {
                return (int) $s->staffid;
            }, $this->mbx_pro->assignable_staff($this->my_account_ids()));

            if (!in_array($staff_id, $allowed, true)) {
                echo json_encode(['success' => false, 'error' => _l('mailbox_assignee_not_allowed')]);

                return;
            }
        }

        $done = $this->mbx_pro->assign($ids, $staff_id);

        echo json_encode([
            'success'  => $done > 0,
            'done'     => $done,
            'staff_id' => $staff_id,
            'name'     => $staff_id > 0 ? get_staff_full_name($staff_id) : '',
        ]);
    }

    public function set_status()
    {
        if (!$this->input->post()) {
            ajax_access_denied();
        }

        $ids    = $this->owned_message_ids((array) $this->input->post('ids'));
        $status = (string) $this->input->post('status');

        if (!count($ids) || !in_array($status, mailbox_statuses(), true)) {
            echo json_encode(['success' => false, 'error' => _l('mailbox_invalid_status')]);

            return;
        }

        echo json_encode(['success' => true, 'done' => $this->mbx_pro->set_status($ids, $status), 'status' => $status]);
    }

    /**
     * Legal hold — freeze a conversation against deletion and retention.
     * Compliance officers only.
     */
    public function legal_hold()
    {
        $this->require_super();
        if (!$this->input->post()) {
            ajax_access_denied();
        }

        $ids = $this->owned_message_ids((array) $this->input->post('ids'));
        $on  = (bool) $this->input->post('on');

        foreach ($ids as $id) {
            $message = $this->mailbox_model->get_message($id);
            if ($message) {
                mailbox_audit($on ? 'legal_hold_on' : 'legal_hold_off', [
                    'account_id' => $message->account_id,
                    'message_id' => $message->id,
                    'subject'    => $message->subject,
                    'details'    => $on ? 'Legal hold placed' : 'Legal hold released',
                ]);
            }
        }

        echo json_encode(['success' => true, 'done' => $this->mbx_pro->set_legal_hold($ids, $on), 'on' => $on]);
    }

    /* ═════════════════════════ Presence ═══════════════════════════════════ */

    /**
     * Collision detection heartbeat: "I am on this conversation" in, "who else
     * is here" out.
     */
    public function presence()
    {
        if (!$this->input->post()) {
            ajax_access_denied();
        }

        $message = $this->mailbox_model->get_message((int) $this->input->post('message_id'));
        if (!$message || !$this->can_touch_account($message->account_id)) {
            echo json_encode(['success' => false, 'others' => []]);

            return;
        }

        $others = mailbox_presence_touch(
            $message->account_id,
            (int) ($message->thread_id ?: $message->id),
            (string) $this->input->post('state')
        );

        echo json_encode(['success' => true, 'others' => $others]);
    }

    /* ══════════════════════════ Labels ════════════════════════════════════ */

    public function labels()
    {
        echo json_encode(['labels' => $this->mbx_pro->get_labels($this->my_account_ids())]);
    }

    public function label_save()
    {
        if (!$this->input->post()) {
            ajax_access_denied();
        }
        if (!mailbox_can_manage()) {
            ajax_access_denied();
        }

        $id     = (int) $this->input->post('id');
        $result = $this->mbx_pro->save_label([
            'name'       => $this->input->post('name'),
            'color'      => $this->input->post('color'),
            'account_id' => $this->valid_account_or_zero($this->input->post('account_id')),
            'sort_order' => $this->input->post('sort_order'),
        ], $id);

        echo json_encode($result);
    }

    public function label_delete()
    {
        if (!$this->input->post()) {
            ajax_access_denied();
        }
        if (!mailbox_can_manage()) {
            ajax_access_denied();
        }

        $this->mbx_pro->delete_label((int) $this->input->post('id'));
        echo json_encode(['success' => true]);
    }

    public function apply_label()
    {
        if (!$this->input->post()) {
            ajax_access_denied();
        }

        $ids      = $this->owned_message_ids((array) $this->input->post('ids'));
        $label_id = (int) $this->input->post('label_id');
        $label    = $this->mbx_pro->get_label($label_id);

        if (!count($ids) || !$label) {
            echo json_encode(['success' => false]);

            return;
        }

        // An account-scoped label may only land on that account's mail.
        if ((int) $label->account_id > 0) {
            $mine = $this->my_account_ids();
            if (!in_array((int) $label->account_id, $mine, true)) {
                ajax_access_denied();
            }
        }

        $on   = (bool) $this->input->post('on');
        $done = $this->mbx_pro->apply_label($ids, $label_id, $on);

        if ($done > 0) {
            $first = $this->mailbox_model->get_message($ids[0]);
            mailbox_audit('labelled', [
                'account_id' => $first ? $first->account_id : 0,
                'message_id' => $ids[0],
                'subject'    => $first ? $first->subject : '',
                'details'    => ($on ? 'Added' : 'Removed') . ' label "' . $label->name . '" on ' . $done . ' message(s)',
            ]);
        }

        echo json_encode(['success' => true, 'done' => $done, 'on' => $on]);
    }

    /* ═══════════════════════ Internal notes ═══════════════════════════════ */

    public function notes($message_id = '')
    {
        $message = $this->mailbox_model->get_message((int) $message_id);
        if (!$message || !$this->can_touch_account($message->account_id)) {
            ajax_access_denied();
        }

        echo json_encode([
            'notes' => $this->mbx_pro->get_notes(
                $message->account_id,
                (int) ($message->thread_id ?: $message->id),
                $message->id
            ),
        ]);
    }

    public function note_add()
    {
        if (!$this->input->post()) {
            ajax_access_denied();
        }

        $message = $this->mailbox_model->get_message((int) $this->input->post('message_id'));
        if (!$message || !$this->can_touch_account($message->account_id)) {
            ajax_access_denied();
        }

        // Notes are plain text on purpose: they are shown inside the admin DOM,
        // unlike mail bodies which get a sandboxed iframe.
        $note = trim(strip_tags((string) $this->input->post('note', false)));
        if ($note === '') {
            echo json_encode(['success' => false, 'error' => _l('mailbox_note_empty')]);

            return;
        }

        $mentions = array_map('intval', (array) $this->input->post('mentions'));
        $id       = $this->mbx_pro->add_note($message, $note, $mentions);

        mailbox_audit('note_added', [
            'account_id' => $message->account_id,
            'message_id' => $message->id,
            'subject'    => $message->subject,
            'details'    => mb_substr($note, 0, 200),
        ]);

        echo json_encode([
            'success' => true,
            'id'      => $id,
            'notes'   => $this->mbx_pro->get_notes($message->account_id, (int) ($message->thread_id ?: $message->id), $message->id),
        ]);
    }

    public function note_delete()
    {
        if (!$this->input->post()) {
            ajax_access_denied();
        }

        $note = $this->mbx_pro->get_note((int) $this->input->post('id'));
        if (!$note || !$this->can_touch_account($note->account_id)) {
            ajax_access_denied();
        }
        // Your own note, or a superadmin cleaning up.
        if ((int) $note->staff_id !== (int) get_staff_user_id() && !mailbox_staff_is_super()) {
            ajax_access_denied();
        }

        $this->mbx_pro->delete_note($note->id);
        echo json_encode(['success' => true]);
    }

    /* ═════════════════════════ Templates ══════════════════════════════════ */

    public function templates()
    {
        echo json_encode([
            'templates'    => $this->mbx_pro->get_templates($this->my_account_ids()),
            'placeholders' => mailbox_template_placeholders(),
        ]);
    }

    /**
     * Render a template for the composer, merging the placeholders that only
     * make sense in the context of the message being answered.
     */
    public function template_use($id = '')
    {
        $template = $this->mbx_pro->get_template((int) $id);
        if (!$template) {
            show_404();
        }
        if ((int) $template->account_id > 0 && !$this->can_touch_account($template->account_id)) {
            ajax_access_denied();
        }

        $context = [
            'contact_name'  => (string) $this->input->get('contact_name'),
            'contact_email' => (string) $this->input->get('contact_email'),
            'subject'       => (string) $this->input->get('subject'),
            'account_email' => (string) $this->input->get('account_email'),
        ];

        $this->mbx_pro->bump_template_usage($template->id);

        echo json_encode([
            'success' => true,
            'subject' => mailbox_render_template($template->subject, $context),
            'body'    => mailbox_render_template($template->body_html, $context),
        ]);
    }

    public function template_save()
    {
        if (!$this->input->post()) {
            ajax_access_denied();
        }

        $id       = (int) $this->input->post('id');
        $existing = $id > 0 ? $this->mbx_pro->get_template($id) : null;

        // Shared templates are managed material; a private one belongs to its
        // author, who may always edit it.
        $is_owner = $existing && (int) $existing->created_by === (int) get_staff_user_id();
        if (!mailbox_can_manage() && !$is_owner && $id > 0) {
            ajax_access_denied();
        }
        if (!mailbox_can_manage() && $this->input->post('is_shared')) {
            ajax_access_denied();
        }

        echo json_encode($this->mbx_pro->save_template([
            'name'       => $this->input->post('name'),
            'subject'    => $this->input->post('subject'),
            'body_html'  => $this->input->post('body_html', false),
            'is_shared'  => $this->input->post('is_shared'),
            'account_id' => $this->valid_account_or_zero($this->input->post('account_id')),
        ], $id));
    }

    public function template_delete()
    {
        if (!$this->input->post()) {
            ajax_access_denied();
        }

        $template = $this->mbx_pro->get_template((int) $this->input->post('id'));
        if (!$template) {
            echo json_encode(['success' => true]);

            return;
        }
        if (!mailbox_can_manage() && (int) $template->created_by !== (int) get_staff_user_id()) {
            ajax_access_denied();
        }

        $this->mbx_pro->delete_template($template->id);
        echo json_encode(['success' => true]);
    }

    /* ════════════════════ Recipient autocomplete ══════════════════════════ */

    public function contacts()
    {
        echo json_encode([
            'results' => $this->mbx_pro->search_contacts(
                $this->input->get('q'),
                $this->my_account_ids()
            ),
        ]);
    }

    /* ═══════════════════════ CRM conversion ═══════════════════════════════ */

    /**
     * Turn an email into a CRM record — a lead or a support ticket — and
     * remember the link on the message so the reading pane can show it.
     */
    public function convert()
    {
        if (!$this->input->post()) {
            ajax_access_denied();
        }

        $message = $this->mailbox_model->get_message((int) $this->input->post('message_id'));
        if (!$message || !$this->can_touch_account($message->account_id)) {
            ajax_access_denied();
        }
        if ($message->rel_id > 0) {
            echo json_encode(['success' => false, 'error' => _l('mailbox_already_converted')]);

            return;
        }

        $target = (string) $this->input->post('target');
        $body   = trim((string) $message->body_plain) !== ''
            ? (string) $message->body_plain
            : trim(strip_tags((string) $message->body_html));

        if ($target === 'lead') {
            if (!has_permission('leads', '', 'create') && !is_admin()) {
                ajax_access_denied();
            }

            $this->load->model('leads_model');
            $status = $this->db->query('SELECT id FROM `' . db_prefix() . 'leads_status` ORDER BY statusorder ASC LIMIT 1')->row();
            $source = $this->db->query('SELECT id FROM `' . db_prefix() . 'leads_sources` ORDER BY id ASC LIMIT 1')->row();

            $rel_id = $this->leads_model->add([
                'name'        => $message->from_name !== '' ? $message->from_name : $message->from_email,
                'email'       => $message->from_email,
                'title'       => mb_substr((string) $message->subject, 0, 100),
                'description' => mb_substr($body, 0, 5000),
                'status'      => $status ? (int) $status->id : 0,
                'source'      => $source ? (int) $source->id : 0,
                'assigned'    => (int) ($message->assigned_to ?: get_staff_user_id()),
                'address'     => '',
                'lastcontact' => date('Y-m-d H:i:s'),
            ]);
            $url = admin_url('leads/index/' . (int) $rel_id);
        } elseif ($target === 'ticket') {
            if (!has_permission('tickets', '', 'create') && !is_admin()) {
                ajax_access_denied();
            }

            $this->load->model('tickets_model');
            // A ticket without a department lands nowhere in the core UI, so
            // fall back to the first configured one.
            $department = $this->db->query(
                'SELECT departmentid FROM `' . db_prefix() . 'departments` ORDER BY departmentid ASC LIMIT 1'
            )->row();

            $rel_id = $this->tickets_model->add([
                'subject'    => $message->subject !== '' ? $message->subject : '(no subject)',
                'message'    => $body,
                'email'      => $message->from_email,
                'name'       => $message->from_name !== '' ? $message->from_name : $message->from_email,
                'department' => $department ? (int) $department->departmentid : 0,
                'assigned'   => (int) $message->assigned_to,
                'userid'     => 0,
                'contactid'  => 0,
                'priority'   => 2,
            ], get_staff_user_id());
            $url = admin_url('tickets/ticket/' . (int) $rel_id);
        } else {
            echo json_encode(['success' => false, 'error' => _l('mailbox_invalid_convert_target')]);

            return;
        }

        if (!$rel_id) {
            echo json_encode(['success' => false, 'error' => _l('mailbox_convert_failed')]);

            return;
        }

        $this->db->where('id', $message->id)->update(db_prefix() . 'mailbox_messages', [
            'rel_type' => $target,
            'rel_id'   => (int) $rel_id,
        ]);

        mailbox_audit('converted', [
            'account_id' => $message->account_id,
            'message_id' => $message->id,
            'subject'    => $message->subject,
            'details'    => 'Converted to ' . $target . ' #' . (int) $rel_id,
        ]);

        echo json_encode(['success' => true, 'id' => (int) $rel_id, 'url' => $url, 'target' => $target]);
    }

    /* ───────────────────────── Attachments ──────────────────────────────── */

    public function attachment($id = '')
    {
        $attachment = $this->mailbox_model->get_attachment((int) $id);
        if (!$attachment) {
            show_404();
        }

        $message = $this->mailbox_model->get_message($attachment->message_id);
        if (!$message || !$this->can_touch_account($message->account_id)) {
            access_denied('Mailbox');
        }

        $path = FCPATH . 'uploads' . DIRECTORY_SEPARATOR . 'mailbox' . DIRECTORY_SEPARATOR
            . (int) $message->account_id . DIRECTORY_SEPARATOR . (int) $message->id
            . DIRECTORY_SEPARATOR . $attachment->stored_name;

        if (!is_file($path)) {
            show_404();
        }

        mailbox_audit('downloaded', [
            'account_id' => $message->account_id,
            'message_id' => $message->id,
            'subject'    => $message->subject,
            'details'    => 'Attachment "' . $attachment->file_name . '" (' . $attachment->size . ' bytes)',
        ]);

        $this->load->helper('download');
        force_download($attachment->file_name, file_get_contents($path));
    }

    /* ──────────────────── Superadmin: account manager ───────────────────── */

    public function accounts()
    {
        $this->require_super();

        $data['accounts'] = $this->mailbox_model->get_accounts_admin();
        $data['staff']    = $this->mailbox_model->get_staff();
        $data['presets']  = mailbox_provider_presets();
        $data['imap_ext'] = function_exists('imap_open');
        $data['title']    = _l('mailbox_accounts');
        $this->load->view('accounts', $data);
    }

    public function account_save()
    {
        $this->require_super();
        if (!$this->input->post()) {
            ajax_access_denied();
        }

        $post = $this->input->post(null, false);
        $id   = (int) ($post['id'] ?? 0);

        if (trim($post['email'] ?? '') === '' || !filter_var(trim($post['email']), FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'error' => _l('mailbox_invalid_email')]);

            return;
        }
        if (trim($post['name'] ?? '') === '') {
            $post['name'] = trim($post['email']);
        }

        $account_id = $this->mailbox_model->save_account($post, $id);

        if (isset($post['staff_ids'])) {
            $this->mailbox_model->set_assignments($account_id, (array) $post['staff_ids']);
        }

        echo json_encode(['success' => true, 'id' => $account_id]);
    }

    public function account_get($id = '')
    {
        $this->require_super();

        $account = $this->mailbox_model->get_account((int) $id);
        if (!$account) {
            show_404();
        }

        echo json_encode([
            'account'  => $account,
            'assigned' => array_map(function ($r) {
                return (int) $r->staff_id;
            }, $this->mailbox_model->get_assignments($account->id)),
        ]);
    }

    public function account_delete()
    {
        $this->require_super();
        if (!$this->input->post()) {
            ajax_access_denied();
        }

        $this->mailbox_model->delete_account((int) $this->input->post('id'));
        echo json_encode(['success' => true]);
    }

    public function assignments()
    {
        $this->require_super();
        if (!$this->input->post()) {
            ajax_access_denied();
        }

        $account_id = (int) $this->input->post('account_id');
        if (!$this->mailbox_model->get_account($account_id)) {
            show_404();
        }

        $this->mailbox_model->set_assignments($account_id, (array) $this->input->post('staff_ids'));
        echo json_encode(['success' => true]);
    }

    /* ─────────────── Superadmin: live connection tests ──────────────────── */

    public function test_smtp()
    {
        $this->require_super();

        $account = $this->test_account_from_post();
        $result  = mailbox_test_smtp($account);

        if ($account->id > 0) {
            $this->db->where('id', $account->id)->update(db_prefix() . 'mailbox_accounts', [
                'smtp_ok' => $result['success'] ? 1 : 0,
            ]);
        }

        echo json_encode($result);
    }

    public function test_imap()
    {
        $this->require_super();

        $account = $this->test_account_from_post();
        $result  = mailbox_test_imap($account);

        if ($account->id > 0) {
            $this->db->where('id', $account->id)->update(db_prefix() . 'mailbox_accounts', [
                'imap_ok' => $result['success'] ? 1 : 0,
            ]);
        }

        echo json_encode($result);
    }

    /* ═════════════════ Superadmin: governance settings ════════════════════ */

    public function settings()
    {
        $this->require_super_page();

        $data['accounts']     = $this->mailbox_model->get_accounts_admin();
        $data['staff']        = $this->mailbox_model->get_staff();
        $data['labels']       = $this->mbx_pro->get_labels([]);
        $data['templates']    = $this->mbx_pro->get_templates([]);
        $data['rules']        = $this->mbx_pro->get_rules();
        $data['options']      = $this->settings_snapshot();
        $data['rule_fields']  = mailbox_rule_fields();
        $data['rule_ops']     = mailbox_rule_operators();
        $data['placeholders'] = mailbox_template_placeholders();
        $data['title']        = _l('mailbox_settings');
        $this->load->view('settings', $data);
    }

    /**
     * Current value of every governance option, defaults filled in.
     */
    private function settings_snapshot()
    {
        $out = [];
        foreach (mailbox_pro_option_defaults() as $key => $default) {
            $out[$key] = mailbox_opt($key, $default);
        }
        foreach (['mailbox_sync_interval' => '120', 'mailbox_initial_days' => '7', 'mailbox_sync_batch' => '50'] as $key => $default) {
            $out[$key] = mailbox_opt($key, $default);
        }

        return $out;
    }

    public function settings_save()
    {
        $this->require_super();
        if (!$this->input->post()) {
            ajax_access_denied();
        }

        $post    = $this->input->post(null, false);
        $changed = [];

        $allowed = array_merge(
            array_keys(mailbox_pro_option_defaults()),
            ['mailbox_sync_interval', 'mailbox_initial_days', 'mailbox_sync_batch']
        );

        foreach ($allowed as $key) {
            if (!array_key_exists($key, $post)) {
                // Unticked switches and empty checkbox groups never reach
                // $_POST at all — store their off state explicitly instead of
                // silently keeping the previous value.
                if (in_array($key, $this->boolean_option_keys(), true)) {
                    $post[$key] = '0';
                } elseif ($key === 'mailbox_retention_folders') {
                    $post[$key] = '';
                } else {
                    continue;
                }
            }

            $value = is_array($post[$key]) ? implode(',', $post[$key]) : trim((string) $post[$key]);

            if ($key === 'mailbox_compliance_bcc' && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['success' => false, 'error' => _l('mailbox_invalid_email')]);

                return;
            }
            if ($key === 'mailbox_dlp_mode' && !in_array($value, ['warn', 'block'], true)) {
                $value = 'warn';
            }

            if ((string) mailbox_opt($key, '') !== $value) {
                $changed[] = $key;
            }
            update_option($key, $value);
        }

        if (count($changed)) {
            mailbox_audit('settings_changed', [
                'details' => 'Updated: ' . implode(', ', $changed),
            ]);
        }

        echo json_encode(['success' => true, 'changed' => $changed]);
    }

    /**
     * Options rendered as checkboxes — these need an explicit "0" when the box
     * comes back unticked.
     */
    private function boolean_option_keys()
    {
        return ['mailbox_status_enabled', 'mailbox_presence_enabled', 'mailbox_sla_enabled',
            'mailbox_sla_business_only', 'mailbox_sla_notify_admins', 'mailbox_schedule_enabled',
            'mailbox_dlp_enabled', 'mailbox_dlp_detect_cards', 'mailbox_audit_enabled',
            'mailbox_rules_enabled', 'mailbox_autoreply_enabled'];
    }

    /**
     * Per-account out-of-office, SLA override and default assignee — kept
     * apart from the credentials wizard so it can be edited without retyping
     * passwords.
     */
    public function account_policy_save()
    {
        $this->require_super();
        if (!$this->input->post()) {
            ajax_access_denied();
        }

        $account_id = (int) $this->input->post('account_id');
        $account    = $this->mailbox_model->get_account($account_id);
        if (!$account) {
            show_404();
        }

        $from = trim((string) $this->input->post('oo_from'));
        $to   = trim((string) $this->input->post('oo_to'));

        $this->db->where('id', $account_id)->update(db_prefix() . 'mailbox_accounts', [
            'oo_enabled'       => $this->input->post('oo_enabled') ? 1 : 0,
            'oo_subject'       => mb_substr(trim((string) $this->input->post('oo_subject')), 0, 255),
            'oo_body'          => (string) $this->input->post('oo_body', false),
            'oo_from'          => preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) ? $from : null,
            'oo_to'            => preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) ? $to : null,
            'sla_hours'        => max(0, (int) $this->input->post('sla_hours')),
            'default_assignee' => max(0, (int) $this->input->post('default_assignee')),
            'shared_inbox'     => $this->input->post('shared_inbox') ? 1 : 0,
        ]);

        mailbox_audit('account_changed', [
            'account_id' => $account_id,
            'details'    => 'Mailbox policy updated (out-of-office / SLA / default owner)',
        ]);

        echo json_encode(['success' => true]);
    }

    /* ══════════════════════════ Rules CRUD ════════════════════════════════ */

    public function rule_get($id = '')
    {
        $this->require_super();

        $rule = $this->mbx_pro->get_rule((int) $id);
        if (!$rule) {
            show_404();
        }

        $rule->conditions_parsed = json_decode((string) $rule->conditions, true) ?: [];
        $rule->actions_parsed    = json_decode((string) $rule->actions, true) ?: [];

        echo json_encode(['rule' => $rule]);
    }

    public function rule_save()
    {
        $this->require_super();
        if (!$this->input->post()) {
            ajax_access_denied();
        }

        $post   = $this->input->post(null, false);
        $id     = (int) ($post['id'] ?? 0);
        $result = $this->mbx_pro->save_rule($post, $id);

        if ($result['success']) {
            mailbox_audit('settings_changed', [
                'account_id' => (int) ($post['account_id'] ?? 0),
                'details'    => ($id > 0 ? 'Updated' : 'Created') . ' automation rule "' . ($post['name'] ?? '') . '"',
            ]);
        }

        echo json_encode($result);
    }

    public function rule_delete()
    {
        $this->require_super();
        if (!$this->input->post()) {
            ajax_access_denied();
        }

        $rule = $this->mbx_pro->get_rule((int) $this->input->post('id'));
        if ($rule) {
            $this->mbx_pro->delete_rule($rule->id);
            mailbox_audit('settings_changed', ['details' => 'Deleted automation rule "' . $rule->name . '"']);
        }

        echo json_encode(['success' => true]);
    }

    public function rule_toggle()
    {
        $this->require_super();
        if (!$this->input->post()) {
            ajax_access_denied();
        }

        $rule = $this->mbx_pro->get_rule((int) $this->input->post('id'));
        if (!$rule) {
            show_404();
        }

        $active = $rule->active ? 0 : 1;
        $this->db->where('id', $rule->id)->update(db_prefix() . 'mailbox_rules', ['active' => $active]);

        echo json_encode(['success' => true, 'active' => $active]);
    }

    /**
     * Dry-run: how many of the last 200 messages would this rule have caught?
     * Nothing is modified.
     */
    public function rule_test()
    {
        $this->require_super();
        if (!$this->input->post()) {
            ajax_access_denied();
        }

        $post = $this->input->post(null, false);

        // Test the posted draft, not the saved copy — that is the point.
        $rule = (object) [
            'account_id' => (int) ($post['account_id'] ?? 0),
            'match_type' => ($post['match_type'] ?? 'all') === 'any' ? 'any' : 'all',
            'conditions' => json_encode(array_values((array) ($post['conditions'] ?? []))),
        ];

        echo json_encode($this->mbx_pro->test_rule($rule));
    }

    /* ═══════════════════════════ Audit ════════════════════════════════════ */

    public function audit()
    {
        if (!mailbox_can_view_reports()) {
            access_denied('Mailbox');
        }

        $data['accounts'] = $this->mailbox_model->get_accounts_admin();
        $data['staff']    = $this->mailbox_model->get_staff();
        $data['actions']  = mailbox_audit_actions();
        $data['title']    = _l('mailbox_audit_trail');
        $this->load->view('audit', $data);
    }

    public function audit_data()
    {
        if (!mailbox_can_view_reports()) {
            ajax_access_denied();
        }

        $page   = max(1, (int) $this->input->get('page'));
        $result = $this->mbx_pro->get_audit($this->audit_filters(), $page, 50);

        echo json_encode([
            'rows'        => $result['rows'],
            'total'       => $result['total'],
            'page'        => $page,
            'total_pages' => $result['total_pages'],
        ]);
    }

    private function audit_filters()
    {
        return [
            'account_id' => (int) $this->input->get('account_id'),
            'staff_id'   => (int) $this->input->get('staff_id'),
            'action'     => (string) $this->input->get('action'),
            'from'       => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $this->input->get('from')) ? $this->input->get('from') : '',
            'to'         => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $this->input->get('to')) ? $this->input->get('to') : '',
            'search'     => trim((string) $this->input->get('search')),
        ];
    }

    /**
     * The whole filtered trail as CSV — what an auditor actually asks for.
     */
    public function audit_export()
    {
        if (!mailbox_can_view_reports()) {
            access_denied('Mailbox');
        }

        $result = $this->mbx_pro->get_audit($this->audit_filters(), 1, 20000);

        $rows = [['Date', 'Staff', 'Account', 'Action', 'Subject', 'Details', 'IP']];
        foreach ($result['rows'] as $row) {
            $rows[] = [
                $row->created_at,
                $row->staff_name ?: 'System',
                $row->account_name ?: '',
                $row->action,
                $row->subject,
                $row->details,
                $row->ip,
            ];
        }

        $handle = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        mailbox_audit('exported', ['details' => 'Audit trail exported (' . (count($rows) - 1) . ' rows)']);

        $this->load->helper('download');
        force_download('mailbox-audit-' . date('Ymd-His') . '.csv', $csv);
    }

    /* ═══════════════════════════ Analytics ════════════════════════════════ */

    public function analytics()
    {
        if (!mailbox_can_view_reports()) {
            access_denied('Mailbox');
        }

        $data['accounts'] = mailbox_accounts_for_staff(get_staff_user_id());
        $data['title']    = _l('mailbox_analytics');
        $this->load->view('analytics', $data);
    }

    public function analytics_data()
    {
        if (!mailbox_can_view_reports()) {
            ajax_access_denied();
        }

        $account_ids = $this->allowed_account_ids($this->input->get('account'));

        $to   = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $this->input->get('to')) ? $this->input->get('to') : date('Y-m-d');
        $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $this->input->get('from')) ? $this->input->get('from') : date('Y-m-d', strtotime('-29 days'));

        // A backwards range returns nothing at all otherwise.
        if (strtotime($from) > strtotime($to)) {
            [$from, $to] = [$to, $from];
        }

        $data         = $this->mbx_pro->analytics($account_ids, $from, $to);
        $data['from'] = $from;
        $data['to']   = $to;
        $data['sla']  = [
            'enabled' => (int) mailbox_opt('mailbox_sla_enabled', '0') === 1,
            'hours'   => (int) mailbox_opt('mailbox_sla_hours', '4'),
        ];

        echo json_encode($data);
    }

    /* ─────────────────────────── Internals ──────────────────────────────── */

    /**
     * Page-level superadmin gate (redirects instead of returning JSON).
     */
    private function require_super_page()
    {
        if (!mailbox_staff_is_super()) {
            access_denied('Mailbox');
        }
    }

    private function require_super()
    {
        if (!mailbox_staff_is_super()) {
            ajax_access_denied();
        }
    }

    private function can_touch_account($account_id)
    {
        return mailbox_staff_is_super() || mailbox_staff_can_use_account($account_id);
    }

    /**
     * Every account id the logged-in user may see.
     */
    private function my_account_ids()
    {
        return array_map(function ($a) {
            return (int) $a->id;
        }, mailbox_accounts_for_staff(get_staff_user_id()));
    }

    /**
     * Filter a posted id list down to messages this user is actually allowed
     * to act on. Every bulk endpoint runs its input through this.
     */
    private function owned_message_ids(array $ids)
    {
        $out = [];

        foreach (array_map('intval', $ids) as $id) {
            if ($id <= 0) {
                continue;
            }
            $message = $this->mailbox_model->get_message($id);
            if ($message && $this->can_touch_account($message->account_id)) {
                $out[] = $id;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * An account scope the user may use, or 0 meaning "all accounts".
     */
    private function valid_account_or_zero($account_id)
    {
        $account_id = (int) $account_id;

        return ($account_id > 0 && $this->can_touch_account($account_id)) ? $account_id : 0;
    }

    /**
     * Requested account filter → validated list of ids the user may read.
     * 'all' (or empty) expands to every visible account.
     */
    private function allowed_account_ids($requested)
    {
        $mine = array_map(function ($a) {
            return (int) $a->id;
        }, mailbox_accounts_for_staff(get_staff_user_id()));

        if ($requested === null || $requested === '' || $requested === 'all') {
            return $mine;
        }

        $requested = (int) $requested;

        return in_array($requested, $mine) ? [$requested] : [];
    }

    /**
     * Real folders plus the virtual views the sidebar offers (starred, my
     * queue, unassigned, overdue).
     */
    private function clean_folder($folder)
    {
        $allowed = ['inbox', 'sent', 'drafts', 'archive', 'trash', 'starred',
            'scheduled', 'mine', 'unassigned', 'overdue'];

        return in_array($folder, $allowed, true) ? $folder : 'inbox';
    }

    /**
     * Shared composer POST → normalized send/draft payload.
     */
    private function compose_data_from_post()
    {
        return [
            'to'        => mailbox_parse_address_input($this->input->post('to')),
            'cc'        => mailbox_parse_address_input($this->input->post('cc')),
            'bcc'       => mailbox_parse_address_input($this->input->post('bcc')),
            'subject'   => trim((string) $this->input->post('subject')),
            'body_html' => (string) $this->input->post('body', false),
        ];
    }

    /**
     * Build a transient account object for the wizard tests: posted values
     * win, stored values (including passwords when the field is left blank)
     * fill the gaps.
     */
    private function test_account_from_post()
    {
        $id     = (int) $this->input->post('id');
        $stored = $id > 0 ? $this->mailbox_model->get_account($id, true) : null;

        $account = (object) [
            'id'                 => $stored ? (int) $stored->id : 0,
            'email'              => trim((string) $this->input->post('email')) ?: ($stored->email ?? ''),
            'smtp_host'          => trim((string) $this->input->post('smtp_host')) ?: ($stored->smtp_host ?? ''),
            'smtp_port'          => (int) ($this->input->post('smtp_port') ?: ($stored->smtp_port ?? 465)),
            'smtp_encryption'    => $this->input->post('smtp_encryption') ?: ($stored->smtp_encryption ?? 'ssl'),
            'smtp_username'      => trim((string) $this->input->post('smtp_username')) ?: ($stored->smtp_username ?? ''),
            'imap_host'          => trim((string) $this->input->post('imap_host')) ?: ($stored->imap_host ?? ''),
            'imap_port'          => (int) ($this->input->post('imap_port') ?: ($stored->imap_port ?? 993)),
            'imap_encryption'    => $this->input->post('imap_encryption') ?: ($stored->imap_encryption ?? 'ssl'),
            'imap_username'      => trim((string) $this->input->post('imap_username')) ?: ($stored->imap_username ?? ''),
            'imap_validate_cert' => (int) $this->input->post('imap_validate_cert'),
        ];

        $smtp_password = (string) $this->input->post('smtp_password', false);
        $imap_password = (string) $this->input->post('imap_password', false);
        if ($imap_password === '') {
            $imap_password = $smtp_password;
        }

        $account->smtp_password = $smtp_password !== ''
            ? mailbox_encrypt($smtp_password)
            : ($stored->smtp_password ?? '');
        $account->imap_password = $imap_password !== ''
            ? mailbox_encrypt($imap_password)
            : ($stored->imap_password ?? '');

        return $account;
    }

    /**
     * Composer file uploads → scratch files. 15 MB per file cap, dangerous
     * executable extensions refused.
     */
    private function collect_uploads()
    {
        $out = [];
        if (empty($_FILES['attachments']) || !is_array($_FILES['attachments']['name'])) {
            return $out;
        }

        $blocked = ['php', 'phtml', 'php3', 'php4', 'php5', 'phar', 'exe', 'sh', 'bat', 'cmd', 'com', 'cgi', 'pl', 'js', 'jar', 'htaccess'];
        $tmp_dir = FCPATH . 'uploads' . DIRECTORY_SEPARATOR . 'mailbox' . DIRECTORY_SEPARATOR . 'tmp';
        if (!is_dir($tmp_dir)) {
            mkdir($tmp_dir, 0755, true);
        }

        foreach ($_FILES['attachments']['name'] as $i => $name) {
            if ($_FILES['attachments']['error'][$i] !== UPLOAD_ERR_OK) {
                continue;
            }
            if ($_FILES['attachments']['size'][$i] > 15 * 1024 * 1024) {
                continue;
            }
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (in_array($ext, $blocked)) {
                continue;
            }

            $scratch = $tmp_dir . DIRECTORY_SEPARATOR . bin2hex(random_bytes(16)) . ($ext !== '' ? '.' . $ext : '');
            if (move_uploaded_file($_FILES['attachments']['tmp_name'][$i], $scratch)) {
                $out[] = ['path' => $scratch, 'name' => $name];
            }
        }

        return $out;
    }
}
