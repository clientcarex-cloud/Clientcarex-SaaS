<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Pro Tickets — admin controller.
 *
 * URL map (admin/pro_tickets/...):
 *   index            dashboard (KPIs, charts, automation feed)
 *   tickets          filterable inbox list
 *   kanban           drag & drop board by status
 *   ticket/{id}      conversation + properties + timeline
 *   new_ticket       create form
 *   settings         automation + SLA policies
 *   smart_create     Smart Ticket capture endpoint (shortcut reporter, AJAX)
 */
class Pro_tickets extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('pro_tickets/pro_tickets_model', 'pro_model');

        // Reporting an issue with the Smart Ticket shortcut is self-service:
        // ANY logged-in staff member may file one from any admin page, so it is
        // exempt from the helpdesk access gate below (it enforces its own).
        if ($this->router->fetch_method() === 'smart_create') {
            return;
        }

        if (!pro_tickets_staff_can_access()) {
            access_denied('Pro Tickets');
        }
    }

    /* ─────────────────────────── Dashboard ──────────────────────────────── */

    public function index()
    {
        $data['dashboard'] = $this->pro_model->get_dashboard();
        $data['statuses']  = $this->pro_model->get_statuses();
        $data['title']     = _l('pro_tickets');
        $this->load->view('dashboard', $data);
    }

    /**
     * JSON endpoint polled by the admin UI to keep the unread customer-reply
     * toast notifications live. Returns pending customer replies (subject +
     * snippet + who replied) not yet read by staff. Access is enforced by the
     * constructor.
     *
     * @return void
     */
    public function unread_replies()
    {
        // Read-only poll: hand the MySQL session row lock back straight away so
        // this doesn't serialise the user's other in-flight AJAX calls.
        ccx_release_session_lock();

        header('Content-Type: application/json');
        header('Cache-Control: no-store, no-cache, must-revalidate');

        $count = (int) pro_tickets_staff_unread_reply_count(true);
        $items = pro_tickets_staff_unread_reply_items(6);

        $payload = json_encode(['count' => $count, 'items' => $items], JSON_INVALID_UTF8_SUBSTITUTE);
        echo $payload === false ? json_encode(['count' => $count, 'items' => []]) : $payload;
    }

    /* ─────────────────────────── Ticket list ────────────────────────────── */

    public function tickets()
    {
        // Default view hides closed tickets: when no status is in the URL,
        // filter to non-closed ("not_closed"), except on feedback drill-downs
        // where ratings only exist on closed tickets. An explicit empty
        // status (the "All statuses" option) still shows everything.
        $status = $this->input->get('status');
        if ($status === null && !$this->input->get('feedback')) {
            $status = 'not_closed';
        }

        $filters = [
            'status'     => $status,
            'department' => $this->input->get('department'),
            'priority'   => $this->input->get('priority'),
            'assigned'   => $this->input->get('assigned'),
            'sla'        => $this->input->get('sla'),
            'feedback'   => $this->input->get('feedback'),
            'search'     => trim((string) $this->input->get('search')),
            'period'     => $this->input->get('period'),
        ];
        $page     = max(1, (int) $this->input->get('page'));
        $per_page = 25;

        $result = $this->pro_model->get_tickets($filters, $page, $per_page);

        $data['tickets']     = $result['rows'];
        $data['total']       = $result['total'];
        $data['page']        = $page;
        $data['per_page']    = $per_page;
        $data['total_pages'] = max(1, (int) ceil($result['total'] / $per_page));
        $data['filters']     = $filters;
        $data['statuses']    = $this->pro_model->get_statuses();
        $data['priorities']  = $this->pro_model->get_priorities();
        $data['departments'] = $this->pro_model->get_departments();
        $data['staff']       = $this->pro_model->get_staff();
        $data['title']       = _l('pro_tickets_all_tickets');
        $this->load->view('list', $data);
    }

    /* ───────────────────────────── Kanban ───────────────────────────────── */

    public function kanban()
    {
        $data['columns'] = $this->pro_model->get_kanban();
        $data['title']   = _l('pro_tickets_kanban');
        $this->load->view('kanban', $data);
    }

    public function kanban_move()
    {
        if (!$this->input->post() || !pro_tickets_staff_can_edit()) {
            ajax_access_denied();
        }

        $ticket_id = (int) $this->input->post('ticket_id');
        $status    = (int) $this->input->post('status');

        $this->load->model('tickets_model');
        $result = $this->tickets_model->change_ticket_status($ticket_id, $status);

        echo json_encode(['success' => isset($result['alert']) && $result['alert'] === 'success']);
    }

    /* ─────────────────────────── Single ticket ──────────────────────────── */

    public function ticket($id = '')
    {
        $ticket = $this->pro_model->get_ticket((int) $id);
        if (!$ticket) {
            show_404();
        }

        // Mark as read for the admin area.
        $this->db->where('ticketid', $ticket->ticketid)->update(db_prefix() . 'tickets', ['adminread' => 1]);

        // Heal Smart Ticket screenshots that older code saved into a tenant's
        // own upload dir instead of the master ticket uploads dir.
        pro_tickets_rescue_stray_attachments($ticket->ticketid);

        $data['ticket']      = $ticket;
        $data['sla_state']   = pro_tickets_sla_state($ticket->meta, $ticket->status);
        $data['statuses']    = $this->pro_model->get_statuses();
        $data['priorities']  = $this->pro_model->get_priorities();
        $data['departments'] = $this->pro_model->get_departments();
        $data['services']    = $this->pro_model->get_services();
        $data['staff']       = $this->pro_model->get_staff();
        $data['canned']      = $this->pro_model->get_predefined_replies();
        $data['is_watching'] = in_array(get_staff_user_id(), pro_tickets_watcher_ids($ticket->ticketid));
        $data['ticket_feedback'] = pro_tickets_get_feedback($ticket->ticketid);

        // Department → member map for the Transfer dialog (active staff only,
        // from the core staff↔department assignments).
        $dept_staff = [];
        $ds_rows = $this->db->query(
            'SELECT sd.departmentid, s.staffid, CONCAT(s.firstname, " ", s.lastname) AS name
             FROM `' . db_prefix() . 'staff_departments` sd
             JOIN `' . db_prefix() . 'staff` s ON s.staffid = sd.staffid AND s.active = 1
             ORDER BY s.firstname ASC'
        )->result();
        foreach ($ds_rows as $ds) {
            $dept_staff[(int) $ds->departmentid][] = ['id' => (int) $ds->staffid, 'name' => $ds->name];
        }
        $data['dept_staff'] = $dept_staff;
        $data['company_contacts'] = $ticket->userid != 0 ? $this->pro_model->get_company_contacts($ticket->userid) : [];

        // The number a hook-triggered SMS/WhatsApp for this ticket is sent to
        // ({mobile_number} in the Omni Messaging payload). Resolved here rather
        // than read off $ticket, whose SELECT * makes `phonenumber` ambiguous
        // between the contacts and clients joins.
        $data['requester_mobile'] = $this->pro_model->get_requester_mobile(
            $ticket->contactid,
            $ticket->userid,
            $ticket->contactid != 0 ? $ticket->email : $ticket->ticket_email
        );

        // Accounts a still-unlinked ticket can be attached to (Requester card).
        $data['link_tenants']   = $ticket->userid == 0 ? $this->pro_model->get_tenants() : [];
        $data['link_customers'] = $ticket->userid == 0 ? $this->pro_model->get_customers() : [];

        // Who the ticket belongs to, shown in the header on every tab: the
        // customer company plus the SaaS instance when that customer is one.
        $data['ticket_company'] = $ticket->userid != 0 ? get_company_name($ticket->userid) : '';
        $data['ticket_tenant']  = $ticket->userid != 0 ? $this->pro_model->get_client_tenant($ticket->userid) : null;

        // Pro Tickets' own to-do checklist for this ticket (+ progress bar).
        $data['ticket_todos']    = $this->pro_model->get_ticket_todos($ticket->ticketid);
        $data['todo_progress']   = $this->pro_model->get_ticket_todo_progress($ticket->ticketid);
        $data['todo_templates']  = $this->pro_model->get_todo_templates();

        // Smart Forms integration — forms assigned to the requester (contact
        // or company/patient record). Read-only, only shown when any exist.
        $data['smart_forms_active'] = pro_tickets_module_active('smart_forms');
        $data['ticket_forms']       = $data['smart_forms_active']
            ? $this->pro_model->get_ticket_smart_forms($ticket->contactid, $ticket->userid)
            : [];

        // Caller module integration — call history of the requester + CC contacts.
        $data['caller_active'] = pro_tickets_module_active('caller');
        $data['ticket_calls']  = [];
        if ($data['caller_active']) {
            $phones   = [];
            $cc_lc    = array_map('strtolower', array_filter(array_map('trim', explode(',', (string) ($ticket->cc ?? '')))));
            foreach ($data['company_contacts'] as $contact) {
                if ($contact->id == $ticket->contactid || in_array(strtolower(trim($contact->email)), $cc_lc, true)) {
                    $phones[] = $contact->phonenumber;
                }
            }
            $data['ticket_calls'] = $this->pro_model->get_calls_for_phones($phones);
        }

        // Mailbox module integration — latest emails exchanged with the
        // requester (+ CC recipients), limited to the mailbox accounts the
        // current staff member is allowed to see.
        $data['mailbox_active'] = pro_tickets_module_active('mailbox') && function_exists('mailbox_accounts_for_staff');
        $data['ticket_emails']  = [];
        if ($data['mailbox_active']) {
            $mailbox_accounts = mailbox_accounts_for_staff(get_staff_user_id());
            if (empty($mailbox_accounts)) {
                $data['mailbox_active'] = false;
            } else {
                $emails   = [$ticket->contactid != 0 ? $ticket->email : $ticket->ticket_email];
                $emails   = array_merge($emails, explode(',', (string) ($ticket->cc ?? '')));
                $data['ticket_emails'] = $this->pro_model->get_emails_for_addresses(
                    array_column($mailbox_accounts, 'id'),
                    $emails
                );
            }
        }

        // Smart PDF module integration — generate documents from the ticket.
        $data['smart_pdf_active'] = pro_tickets_module_active('smart_pdf');
        $data['pdf_templates']    = $data['smart_pdf_active'] ? $this->pro_model->get_pdf_templates() : [];

        // Prefill the reply composer with the same greeting/signature template
        // the new-ticket screen uses, so an agent starts from "Dear {Name} ji,"
        // and the signature instead of a blank box. {Name}/{Subject} are known
        // here (unlike on the new-ticket screen), so they are resolved from the
        // ticket; {Agent Name}/{Role}/{Company} come from the composing staff.
        pro_tickets_seed_default_templates();
        $data['reply_prefill'] = '';
        $reply_tpl_id = (int) get_option('pro_tickets_new_ticket_template');
        if ($reply_tpl_id) {
            $reply_tpl = $this->pro_model->get_predefined_reply($reply_tpl_id);
            if ($reply_tpl) {
                $data['reply_prefill'] = pro_tickets_apply_template_tags(
                    $reply_tpl->message,
                    pro_tickets_ticket_tag_values($ticket->ticketid)
                );
            }
        }

        $data['title']       = _l('pro_tickets_ticket') . ' #' . $ticket->ticketid;
        $this->load->view('ticket', $data);
    }

    /* ───────────────────── Smart PDF integration ────────────────────────── */

    /**
     * GET — field definitions of one Smart PDF template for the ticket
     * "Documents" card, prefilled from the ticket context (patient_* tags
     * via the requester's client record + common ticket/contact tags).
     */
    public function ticket_pdf_fields($template_id)
    {
        if (!pro_tickets_module_active('smart_pdf')) {
            echo json_encode(['success' => false]);

            return;
        }

        $this->load->model('smart_pdf/smart_pdf_model');
        $template = $this->smart_pdf_model->get((int) $template_id);
        $ticket   = $this->db->where('ticketid', (int) $this->input->get('ticket_id'))
            ->get(db_prefix() . 'tickets')->row();
        if (!$template || !$template->active || !$ticket) {
            echo json_encode(['success' => false]);

            return;
        }

        $prefill = $this->ticket_pdf_prefill($ticket);
        $fields  = [];
        foreach ($this->smart_pdf_model->get_fields($template) as $field) {
            if (!empty($field['ignored'])) {
                continue;
            }
            $value = $prefill[$field['tag']] ?? '';
            $field['value'] = $value !== '' ? $value : $field['default'];
            $fields[] = $field;
        }

        echo json_encode([
            'success' => true,
            'name'    => $template->name,
            'fields'  => $fields,
        ]);
    }

    /**
     * POST — merge submitted values, log the generation (audit history +
     * ticket activity) and serve the printable document. mode=preview
     * skips logging and the auto-print. Mirrors Smart_pdf::generate() but
     * gated by Pro Tickets access instead of the smart_pdf permission.
     */
    public function ticket_pdf_generate()
    {
        if (!$this->input->post() || !pro_tickets_module_active('smart_pdf')) {
            show_404();
        }

        $this->load->model('smart_pdf/smart_pdf_model');
        $template = $this->smart_pdf_model->get((int) $this->input->post('template_id'));
        $ticket   = $this->db->where('ticketid', (int) $this->input->post('ticket_id'))
            ->get(db_prefix() . 'tickets')->row();
        if (!$template || !$template->active || !$ticket) {
            show_404();
        }

        $values = $this->input->post('tags');
        if (!is_array($values)) {
            $values = [];
        }

        if ($this->input->post('mode') === 'preview') {
            $merged = $this->smart_pdf_model->merge($template, $values, _l('smart_pdf_preview_document_no'));
            $this->ticket_pdf_output($template, $merged, false);

            return;
        }

        $generation_id = $this->smart_pdf_model->add_generation($template->id, $values, 'print', $ticket->userid ?: null);
        $document_no   = 'DOC-' . str_pad($generation_id, 5, '0', STR_PAD_LEFT);

        pro_tickets_log($ticket->ticketid, 'pdf_generated', $template->name . ' · ' . $document_no, get_staff_user_id());

        $merged = $this->smart_pdf_model->merge($template, $values, $document_no);
        $this->ticket_pdf_output($template, $merged, true);
    }

    /**
     * Prefill values for a template from the ticket: patient_* tags resolve
     * through the requester's client record (patients are clients here),
     * plus common ticket/contact tag names. Empty values never overwrite.
     */
    private function ticket_pdf_prefill($ticket)
    {
        $values = [];
        if ($ticket->userid) {
            $values = $this->smart_pdf_model->get_patient_tag_values($ticket->userid);
        }

        $contact = $ticket->contactid ? $this->db->where('id', (int) $ticket->contactid)
            ->get(db_prefix() . 'contacts')->row() : null;
        $name    = $contact ? trim($contact->firstname . ' ' . $contact->lastname) : (string) $ticket->name;
        $email   = $contact ? (string) $contact->email : (string) $ticket->email;
        $phone   = $contact ? (string) $contact->phonenumber : '';
        $company = $ticket->userid ? get_company_name($ticket->userid) : '';

        $map = [
            'ticket_id'      => '#' . $ticket->ticketid,
            'ticket_no'      => '#' . $ticket->ticketid,
            'ticket_subject' => (string) $ticket->subject,
            'subject'        => (string) $ticket->subject,
            'ticket_date'    => _d($ticket->date),
            'name'           => $name,
            'full_name'      => $name,
            'customer_name'  => $name,
            'client_name'    => $name,
            'contact_name'   => $name,
            'email'          => $email,
            'customer_email' => $email,
            'phone'          => $phone,
            'mobile'         => $phone,
            'phone_number'   => $phone,
            'company'        => $company,
            'company_name'   => $company,
        ];
        foreach ($map as $tag => $value) {
            if ($value !== '' && (!isset($values[$tag]) || $values[$tag] === '')) {
                $values[$tag] = $value;
            }
        }

        return $values;
    }

    /**
     * Serve the merged document standalone (same handling as the Smart PDF
     * module: wrap bare content, optional auto-print, no CSP so imported
     * template assets keep working).
     */
    private function ticket_pdf_output($template, $merged, $auto_print)
    {
        if (stripos($merged, '<html') === false) {
            $orientation = $template->orientation == 'L' ? 'landscape' : 'portrait';
            $merged = '<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>' . html_escape($template->name) . '</title>
<style>
@page { size: ' . $template->paper_size . ' ' . $orientation . '; margin: 12mm; }
body { font-family: Arial, Helvetica, sans-serif; }
</style>
</head>
<body>' . $merged . '</body>
</html>';
        }

        if ($auto_print) {
            $snippet = '<script>window.addEventListener("load", function () { window.print(); });</script>';
            $merged  = stripos($merged, '</body>') !== false
                ? str_ireplace('</body>', $snippet . '</body>', $merged)
                : $merged . $snippet;
        }

        if (!headers_sent()) {
            header_remove('Content-Security-Policy');
            header_remove('Content-Security-Policy-Report-Only');
        }

        echo $merged;
    }

    /**
     * POST — staff reply (message, optional status change, attachments).
     */
    public function reply($id)
    {
        if (!$this->input->post() || !pro_tickets_staff_can_edit()) {
            access_denied('Pro Tickets');
        }

        $message = $this->input->post('message', false);
        if (trim(strip_tags((string) $message)) === '' && empty($_FILES['attachments']['name'][0])) {
            set_alert('warning', _l('pro_tickets_reply_empty'));
            redirect(admin_url('pro_tickets/ticket/' . (int) $id));
        }

        // Priority picked in the send-split — apply before the reply so the SLA
        // re-match below runs against the new priority.
        $priority = (int) $this->input->post('priority');
        if ($priority) {
            $current = $this->db->select('priority')->where('ticketid', (int) $id)->get(db_prefix() . 'tickets')->row();
            if ($current && (int) $current->priority !== $priority) {
                $this->db->where('ticketid', (int) $id)->update(db_prefix() . 'tickets', ['priority' => $priority]);
                pro_tickets_log((int) $id, 'priority_changed', (string) $priority, get_staff_user_id());
                pro_tickets_reapply_sla((int) $id);
            }
        }

        // Fill any merge tag left in the composed reply ({Name}, {Agent Name},
        // …) so a predefined message never goes out with raw tags in it.
        $message = pro_tickets_apply_template_tags($message, pro_tickets_ticket_tag_values((int) $id));

        $this->load->model('tickets_model');
        $replyid = $this->tickets_model->add_reply([
            'message' => $message,
            'status'  => (int) $this->input->post('status'),
        ], (int) $id, get_staff_user_id());

        set_alert($replyid ? 'success' : 'danger', $replyid ? _l('pro_tickets_reply_added') : _l('pro_tickets_something_wrong'));
        redirect(admin_url('pro_tickets/ticket/' . (int) $id));
    }

    /**
     * POST — inline property updates (assigned / priority / department /
     * service / status) from the detail page or the list.
     */
    public function update_ticket($id)
    {
        if (!$this->input->post() || !pro_tickets_staff_can_edit()) {
            ajax_access_denied();
        }

        $id     = (int) $id;
        $ticket = $this->db->where('ticketid', $id)->get(db_prefix() . 'tickets')->row();
        if (!$ticket) {
            echo json_encode(['success' => false]);

            return;
        }

        $field = $this->input->post('field');
        $value = (int) $this->input->post('value');

        $allowed = ['assigned', 'priority', 'department', 'service', 'status'];
        if (!in_array($field, $allowed, true)) {
            echo json_encode(['success' => false]);

            return;
        }

        if ($field === 'status') {
            $this->load->model('tickets_model');
            $this->tickets_model->change_ticket_status($id, $value);
        } else {
            $this->db->where('ticketid', $id)->update(db_prefix() . 'tickets', [$field => $value]);

            if ($field === 'assigned') {
                pro_tickets_log($id, 'assigned', $value ? get_staff_full_name($value) : '', get_staff_user_id());
                if ($value && $value != get_staff_user_id()) {
                    pro_tickets_notify($value, 'pro_tickets_not_assigned_to_you', $id, $ticket->subject);
                }
                if ($value && $value !== (int) $ticket->assigned) {
                    pro_tickets_fire_omni('pro_ticket_assigned', $id, [
                        'assigned_by'   => get_staff_full_name(get_staff_user_id()),
                        'assign_reason' => pro_tickets_omni_l('pro_tickets_hook_assign_manual'),
                    ]);
                }
            } else {
                pro_tickets_log($id, $field . '_changed', (string) $value, get_staff_user_id());
            }

            // Department/priority changes can re-match a different SLA policy.
            if ($field === 'priority' || $field === 'department') {
                pro_tickets_reapply_sla($id);
            }
        }

        echo json_encode(['success' => true]);
    }

    /**
     * POST — attach a ticket that belongs to no account yet to a tenant (its
     * master client record) or a customer.
     *
     * Tickets raised for a tenant staff member before the requester flow linked
     * them carry only a name + e-mail, so they sit outside the tenant's account
     * everywhere in the CRM. This puts them where they belong without touching
     * who the requester is.
     */
    public function link_client($id)
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!$this->input->post() || !pro_tickets_staff_can_edit()) {
            echo json_encode(['success' => false, 'message' => _l('access_denied')]);

            return;
        }

        $id     = (int) $id;
        $key    = trim((string) $this->input->post('key'));
        $ticket = $this->db->select('ticketid, email, contactid, userid')
            ->where('ticketid', $id)->get(db_prefix() . 'tickets')->row();

        if (!$ticket || $key === '') {
            echo json_encode(['success' => false, 'message' => _l('pro_tickets_something_wrong')]);

            return;
        }

        $update = [];
        if (strpos($key, 't:') === 0) {
            $link = $this->pro_model->get_tenant_client_link(substr($key, 2), (string) $ticket->email);
            if (!$link) {
                echo json_encode(['success' => false, 'message' => _l('pro_tickets_link_client_failed')]);

                return;
            }
            $update['userid'] = $link['userid'];
            // Only adopt the contact when it really is this requester —
            // otherwise the ticket keeps its own name + e-mail.
            if (!$link['stamp']) {
                $update['contactid'] = $link['contactid'];
            }
        } elseif (strpos($key, 'c:') === 0) {
            $userid = (int) substr($key, 2);
            $client = $this->db->select('userid')->where('userid', $userid)->get(db_prefix() . 'clients')->row();
            if (!$client) {
                echo json_encode(['success' => false, 'message' => _l('pro_tickets_link_client_failed')]);

                return;
            }
            $update['userid'] = $userid;

            $contact = $this->db->query(
                'SELECT id FROM `' . db_prefix() . 'contacts` WHERE userid = ? AND LOWER(email) = ? LIMIT 1',
                [$userid, strtolower((string) $ticket->email)]
            )->row();
            if ($contact) {
                $update['contactid'] = (int) $contact->id;
            }
        } else {
            echo json_encode(['success' => false, 'message' => _l('pro_tickets_something_wrong')]);

            return;
        }

        $this->db->where('ticketid', $id)->update(db_prefix() . 'tickets', $update);
        pro_tickets_log($id, 'client_linked', get_company_name($update['userid']), get_staff_user_id());

        echo json_encode(['success' => true]);
    }

    /**
     * POST — internal note (never visible to the customer).
     */
    public function add_note($id)
    {
        if (!$this->input->post()) {
            access_denied('Pro Tickets');
        }

        $note = trim((string) $this->input->post('note'));
        if ($note !== '') {
            $this->pro_model->add_note((int) $id, get_staff_user_id(), $note);
            pro_tickets_notify_mentions((int) $id, $note, 'note');
            set_alert('success', _l('pro_tickets_note_added'));
        }
        redirect(admin_url('pro_tickets/ticket/' . (int) $id));
    }

    /**
     * POST — watch/unwatch a ticket for the current staff member, or manage
     * another watcher when staff_id is passed (edit permission required).
     */
    public function toggle_watch($id)
    {
        if (!$this->input->post()) {
            ajax_access_denied();
        }

        $staff_id = (int) ($this->input->post('staff_id') ?: get_staff_user_id());
        if ($staff_id !== (int) get_staff_user_id() && !pro_tickets_staff_can_edit()) {
            ajax_access_denied();
        }

        $watching = $this->pro_model->toggle_watcher((int) $id, $staff_id);
        pro_tickets_log((int) $id, $watching ? 'watcher_added' : 'watcher_removed', get_staff_full_name($staff_id), get_staff_user_id());

        echo json_encode(['success' => true, 'watching' => $watching]);
    }

    /**
     * POST — add/remove a CC recipient on a ticket. Additions must be an
     * active contact of the requester's company (same-company rule).
     */
    public function toggle_cc($id)
    {
        if (!$this->input->post() || !pro_tickets_staff_can_edit()) {
            ajax_access_denied();
        }

        $id     = (int) $id;
        $ticket = $this->db->where('ticketid', $id)->get(db_prefix() . 'tickets')->row();
        $email  = trim((string) $this->input->post('email'));
        $action = $this->input->post('cc_action') === 'remove' ? 'remove' : 'add';

        if (!$ticket || $email === '') {
            echo json_encode(['success' => false]);

            return;
        }

        $cc = array_values(array_filter(array_map('trim', explode(',', (string) $ticket->cc))));
        $lc = array_map('strtolower', $cc);

        if ($action === 'add') {
            $contact = $ticket->userid ? $this->db->where('userid', $ticket->userid)
                ->where('email', $email)
                ->where('active', 1)
                ->get(db_prefix() . 'contacts')->row() : null;
            $requester = $ticket->contactid ? $this->db->select('email')->where('id', $ticket->contactid)
                ->get(db_prefix() . 'contacts')->row() : null;
            if (!$contact || ($requester && strtolower($email) === strtolower(trim((string) $requester->email)))) {
                echo json_encode(['success' => false]);

                return;
            }
            if (!in_array(strtolower($email), $lc, true)) {
                $cc[] = $email;
            }
        } else {
            $cc = array_values(array_filter($cc, function ($existing) use ($email) {
                return strtolower($existing) !== strtolower($email);
            }));
        }

        $this->db->where('ticketid', $id)->update(db_prefix() . 'tickets', ['cc' => implode(',', $cc)]);
        pro_tickets_log($id, $action === 'add' ? 'cc_added' : 'cc_removed', $email, get_staff_user_id());

        echo json_encode(['success' => true]);
    }

    /**
     * POST — create a to-do task on the ticket's own checklist.
     */
    public function add_todo($id)
    {
        if (!$this->input->post() || !pro_tickets_staff_can_edit()) {
            access_denied('Pro Tickets');
        }

        $id    = (int) $id;
        $title = trim((string) $this->input->post('title'));

        if ($title !== '' && total_rows(db_prefix() . 'tickets', ['ticketid' => $id]) > 0) {
            $this->pro_model->add_ticket_todo(
                $id,
                $title,
                $this->input->post('due_date'),
                (int) $this->input->post('priority'),
                (int) $this->input->post('assignee'),
                get_staff_user_id(),
                trim((string) $this->input->post('description'))
            );
            pro_tickets_log($id, 'todo_added', $title, get_staff_user_id());
            set_alert('success', _l('pro_tickets_todo_added'));
        }

        redirect(admin_url('pro_tickets/ticket/' . $id));
    }

    /**
     * POST — toggle a ticket to-do between pending and completed.
     */
    public function toggle_todo($task_id)
    {
        if (!$this->input->post() || !pro_tickets_staff_can_edit()) {
            ajax_access_denied();
        }

        $task = $this->pro_model->get_ticket_todo((int) $task_id);
        if (!$task) {
            echo json_encode(['success' => false]);

            return;
        }

        $completed = $this->pro_model->toggle_ticket_todo($task->id);
        pro_tickets_log((int) $task->ticket_id, $completed ? 'todo_completed' : 'todo_reopened', $task->title, get_staff_user_id());

        echo json_encode(['success' => true, 'completed' => $completed]);
    }

    /**
     * POST — delete a ticket to-do task.
     */
    public function delete_todo($task_id)
    {
        if (!$this->input->post() || !pro_tickets_staff_can_edit()) {
            ajax_access_denied();
        }

        $task = $this->pro_model->delete_ticket_todo((int) $task_id);
        if (!$task) {
            echo json_encode(['success' => false]);

            return;
        }

        pro_tickets_log((int) $task->ticket_id, 'todo_deleted', $task->title, get_staff_user_id());

        echo json_encode(['success' => true]);
    }

    /**
     * POST — apply a to-do checklist template to a ticket (adds every item).
     */
    public function apply_todo_template($id)
    {
        if (!$this->input->post() || !pro_tickets_staff_can_edit()) {
            access_denied('Pro Tickets');
        }

        $id          = (int) $id;
        $template_id = (int) $this->input->post('template_id');

        if ($template_id && total_rows(db_prefix() . 'tickets', ['ticketid' => $id]) > 0) {
            $template = $this->pro_model->get_todo_template($template_id);
            $added    = $template
                ? $this->pro_model->apply_todo_template($template_id, $id, get_staff_user_id(), $this->input->post('due_date'))
                : 0;

            if ($added > 0) {
                pro_tickets_log($id, 'todo_added', $template->name . ' (×' . $added . ')', get_staff_user_id());
                set_alert('success', _l('pro_tickets_todo_template_applied', $added));
            }
        }

        redirect(admin_url('pro_tickets/ticket/' . $id));
    }

    /**
     * To-do checklist templates — management page (list + editor).
     */
    public function todo_templates($id = '')
    {
        if (!pro_tickets_staff_can_edit()) {
            access_denied('Pro Tickets');
        }

        pro_tickets_ensure_schema();

        $data['title']     = _l('pro_tickets_todo_templates');
        $data['templates'] = $this->pro_model->get_todo_templates();
        $data['editing']   = $id !== '' ? $this->pro_model->get_todo_template((int) $id) : null;
        if ($id !== '' && !$data['editing']) {
            show_404();
        }

        $this->load->view('todo_templates', $data);
    }

    /**
     * POST — create/update a to-do checklist template. Items arrive as a JSON
     * array [{title, description, priority}] serialized by the editor.
     */
    public function save_todo_template()
    {
        if (!$this->input->post() || !pro_tickets_staff_can_edit()) {
            access_denied('Pro Tickets');
        }

        $items = json_decode((string) $this->input->post('items', false), true);
        $saved = $this->pro_model->save_todo_template(
            (int) $this->input->post('id'),
            (string) $this->input->post('name'),
            (string) $this->input->post('description'),
            is_array($items) ? $items : []
        );

        set_alert($saved ? 'success' : 'warning', $saved
            ? _l('pro_tickets_todo_template_saved')
            : _l('pro_tickets_todo_template_invalid'));
        redirect(admin_url('pro_tickets/todo_templates'));
    }

    /**
     * POST — delete a to-do checklist template.
     */
    public function delete_todo_template($id)
    {
        if (!$this->input->post() || !pro_tickets_staff_can_edit()) {
            access_denied('Pro Tickets');
        }

        if ($this->pro_model->delete_todo_template((int) $id)) {
            set_alert('success', _l('pro_tickets_todo_template_deleted'));
        }

        redirect(admin_url('pro_tickets/todo_templates'));
    }

    /**
     * GET — Caller-module call history for one contact (new-ticket form).
     */
    public function contact_calls()
    {
        if (!pro_tickets_staff_can_create() || !pro_tickets_module_active('caller')) {
            echo json_encode([]);

            return;
        }

        $contact = $this->db->where('id', (int) $this->input->get('contactid'))
            ->get(db_prefix() . 'contacts')->row();
        if (!$contact || trim((string) $contact->phonenumber) === '') {
            echo json_encode([]);

            return;
        }

        $calls = $this->pro_model->get_calls_for_phones([$contact->phonenumber], 5);
        echo json_encode(array_map(function ($call) {
            return [
                'call_type' => $call->call_type,
                'call_date' => _dt($call->call_date),
                'duration'  => (int) $call->duration_seconds,
                'staff'     => trim((string) $call->staff_name),
                'phone'     => $call->phone_number,
            ];
        }, $calls));
    }

    /**
     * GET — Mailbox-module recent emails for one contact (new-ticket form).
     * Limited to the mailbox accounts visible to the current staff member.
     */
    public function contact_emails()
    {
        if (!pro_tickets_staff_can_create() || !pro_tickets_module_active('mailbox')
            || !function_exists('mailbox_accounts_for_staff')) {
            echo json_encode([]);

            return;
        }

        $accounts = mailbox_accounts_for_staff(get_staff_user_id());
        $contact  = $this->db->where('id', (int) $this->input->get('contactid'))
            ->get(db_prefix() . 'contacts')->row();
        if (empty($accounts) || !$contact || trim((string) $contact->email) === '') {
            echo json_encode([]);

            return;
        }

        $emails = $this->pro_model->get_emails_for_addresses(array_column($accounts, 'id'), [$contact->email], 5);
        echo json_encode(array_map(function ($m) {
            $outgoing = strtolower(trim((string) $m->from_email)) === strtolower(trim((string) $m->account_email));

            return [
                'subject'  => (string) $m->subject,
                'snippet'  => (string) $m->snippet,
                'from'     => $outgoing ? $m->account_email : ($m->from_name !== '' ? $m->from_name : $m->from_email),
                'date'     => _dt($m->message_date),
                'account'  => $m->account_name,
                'outgoing' => $outgoing,
                'attach'   => (int) $m->has_attachments,
            ];
        }, $emails));
    }

    /**
     * POST — delete a ticket (delete permission required).
     */
    public function delete($id)
    {
        if (!pro_tickets_staff_can_delete()) {
            access_denied('Pro Tickets');
        }

        $this->load->model('tickets_model');
        $success = $this->tickets_model->delete((int) $id);

        set_alert($success ? 'success' : 'danger', $success ? _l('pro_tickets_deleted') : _l('pro_tickets_something_wrong'));
        redirect(admin_url('pro_tickets/tickets'));
    }

    /* ─────────────────────────── New ticket ─────────────────────────────── */

    public function new_ticket()
    {
        if (!pro_tickets_staff_can_create()) {
            access_denied('Pro Tickets');
        }

        if ($this->input->post()) {
            if (trim((string) $this->input->post('subject')) === ''
                || trim(strip_tags((string) $this->input->post('message', false))) === ''
                || !(int) $this->input->post('department')) {
                set_alert('warning', _l('pro_tickets_something_wrong'));
                redirect(admin_url('pro_tickets/new_ticket'));
            }

            $post = [
                'subject'    => $this->input->post('subject'),
                'message'    => $this->input->post('message', false),
                'department' => (int) $this->input->post('department'),
                'priority'   => (int) $this->input->post('priority'),
                'assigned'   => (int) $this->input->post('assigned'),
            ];

            $service = (int) $this->input->post('service');
            if ($service) {
                $post['service'] = $service;
            }

            // Resolve the requester. The primary pick is either a tenant staff
            // member (tenant_slug + tenant_staff_id, resolved cross-DB and
            // stored as name+email), a client contact (contactid), an internal
            // staff member (staff_id → also name+email, since tickets have no
            // native staff requester), or a manually typed name & email.
            $primary_email = '';
            $primary_name  = '';
            $stamp_author  = null; // requester to write back when core can't take them
            if ($this->input->post('tenant_slug') && $this->input->post('tenant_staff_id')) {
                $slug = trim((string) $this->input->post('tenant_slug'));
                $ts   = $this->pro_model->get_tenant_staff_member(
                    $slug,
                    (int) $this->input->post('tenant_staff_id')
                );
                if (!$ts) {
                    // Tenant unreachable or staff no longer active — never
                    // create a ticket with an empty requester.
                    set_alert('warning', _l('pro_tickets_something_wrong'));
                    redirect(admin_url('pro_tickets/new_ticket'));
                }
                $post['name']  = $ts['name'];
                $post['email'] = $ts['email'];
                $primary_email = (string) $ts['email'];
                $primary_name  = (string) $ts['name'];

                // The ticket belongs to the TENANT's account, not to the person
                // who asked for it: link it to that client so it shows up under
                // the tenant everywhere (list, client profile, CC roster).
                $link = $this->pro_model->get_tenant_client_link($slug, $ts['email']);
                if ($link) {
                    unset($post['name'], $post['email']);
                    $post['userid']    = $link['userid'];
                    $post['contactid'] = $link['contactid'];

                    if ($link['stamp']) {
                        $stamp_author = ['name' => $ts['name'], 'email' => $ts['email']];
                    }
                }
            } elseif ($this->input->post('contactid')) {
                $contact = $this->db->where('id', (int) $this->input->post('contactid'))
                    ->get(db_prefix() . 'contacts')->row();
                if ($contact) {
                    $post['contactid'] = $contact->id;
                    $post['userid']    = $contact->userid;
                    $primary_email     = (string) $contact->email;
                    $primary_name      = trim($contact->firstname . ' ' . $contact->lastname);
                }
            } elseif ($this->input->post('staff_id')) {
                $staff = $this->db->where('staffid', (int) $this->input->post('staff_id'))
                    ->where('active', 1)
                    ->get(db_prefix() . 'staff')->row();
                if ($staff) {
                    $post['name']  = trim($staff->firstname . ' ' . $staff->lastname);
                    $post['email'] = $staff->email;
                    $primary_email = (string) $staff->email;
                    $primary_name  = $post['name'];
                }
            }
            if (!isset($post['contactid']) && !isset($post['email'])) {
                // Manual entry (or an unresolved pick) — fall back to name & email.
                $post['name']  = $this->input->post('name');
                $post['email'] = $this->input->post('email');
                $primary_email = (string) $this->input->post('email');
                $primary_name  = (string) $this->input->post('name');
            }

            // Backstop for the composer's merge tags: whatever the agent left
            // unresolved in the message ({Name}, {Agent Name}, …) is filled in
            // here, where the requester is finally known — so a template never
            // reaches the customer with raw tags in it.
            $post['message'] = pro_tickets_apply_template_tags($post['message'], [
                'name'    => $primary_name,
                'subject' => (string) $post['subject'],
            ]);

            // Additional picked people ride along as CC — carried as e-mail
            // addresses so contacts and staff work the same way. The core stores
            // these on the ticket's `cc` column (comma-separated).
            $cc = array_map(function ($e) { return trim((string) $e); }, (array) $this->input->post('cc_emails'));
            $cc = array_values(array_unique(array_filter($cc, function ($e) use ($primary_email) {
                return $e !== '' && strcasecmp($e, $primary_email) !== 0;
            })));

            // A requester with no contact record here is only put on the ticket
            // after it exists (see below), so core addresses the "ticket opened"
            // e-mail to the account's primary contact — ride along as CC so the
            // person it was actually raised for gets it too. They are dropped
            // from the CC list again once they own the ticket's e-mail column.
            if ($stamp_author && $stamp_author['email'] !== '') {
                $cc[] = $stamp_author['email'];
            }

            if (!empty($cc)) {
                $post['cc'] = $cc;
            }

            $this->load->model('tickets_model');
            $ticket_id = $this->tickets_model->add($post, get_staff_user_id());

            if ($ticket_id) {
                // Requester with no contact record here: the row was written
                // with the account's primary contact (core insists on one), so
                // put the real person back on it — the ticket keeps belonging
                // to the tenant's account either way. From here on core mails
                // every reply to tickets.email, so the CC copy added above is
                // removed to avoid delivering each reply twice.
                if ($stamp_author) {
                    $rest = array_values(array_filter($cc, function ($e) use ($stamp_author) {
                        return strcasecmp($e, $stamp_author['email']) !== 0;
                    }));

                    $this->db->where('ticketid', (int) $ticket_id)->update(db_prefix() . 'tickets', [
                        'contactid' => 0,
                        'name'      => $stamp_author['name'],
                        'email'     => $stamp_author['email'],
                        'cc'        => implode(',', $rest),
                    ]);
                }

                // Optional follow-up task on the ticket's own to-do checklist.
                if ($this->input->post('create_todo')) {
                    $todo_title = trim((string) $this->input->post('todo_title')) ?: _l('pro_tickets_todo_default_title', $post['subject']);
                    $this->pro_model->add_ticket_todo(
                        $ticket_id,
                        $todo_title,
                        $this->input->post('todo_due_date'),
                        2,
                        (int) $this->input->post('assigned'),
                        get_staff_user_id()
                    );
                    pro_tickets_log($ticket_id, 'todo_added', $todo_title, get_staff_user_id());
                }

                set_alert('success', _l('pro_tickets_created'));
                redirect(admin_url('pro_tickets/ticket/' . $ticket_id));
            }

            set_alert('danger', _l('pro_tickets_something_wrong'));
        }

        // Predefined messages for the composer + the greeting/signature
        // skeleton the message box starts from (when one is configured).
        pro_tickets_seed_default_templates();
        $data['canned']  = $this->pro_model->get_predefined_replies();
        $data['prefill'] = '';

        $prefill_id = (int) get_option('pro_tickets_new_ticket_template');
        if ($prefill_id) {
            $tpl = $this->pro_model->get_predefined_reply($prefill_id);
            if ($tpl) {
                $data['prefill'] = pro_tickets_apply_template_tags($tpl->message);
            }
        }

        $data['departments']   = $this->pro_model->get_departments();
        $data['priorities']    = $this->pro_model->get_priorities();
        $data['services']      = $this->pro_model->get_services();
        $data['staff']         = $this->pro_model->get_staff();
        $data['tenants']       = $this->pro_model->get_tenants();
        $data['customers']     = $this->pro_model->get_customers();
        $data['caller_active'] = pro_tickets_module_active('caller');
        $data['mailbox_active'] = pro_tickets_module_active('mailbox')
            && function_exists('mailbox_accounts_for_staff')
            && count(mailbox_accounts_for_staff(get_staff_user_id())) > 0;
        $data['title']         = _l('pro_tickets_new');
        $this->load->view('new_ticket', $data);
    }

    /**
     * Smart Ticket capture endpoint for the MASTER admin panel (AJAX).
     *
     * Powers the Ctrl+Alt+N (⌥⇧N on Mac) reporter injected on every admin page, so the
     * provider's own staff can report an issue in any CRM module from the page
     * it happened on. The report is filed as an internal ticket in this
     * helpdesk, attributed to the staff member who raised it.
     *
     * Unlike the tenant path (which writes cross-DB and therefore bypasses the
     * pipeline), this goes through the core tickets_model so SLA, auto-assign,
     * the activity timeline and the screenshot attachment all behave exactly
     * like a hand-typed ticket.
     *
     * Access is deliberately wider than the rest of the controller — any
     * logged-in staff member may report — so the gate is enforced here rather
     * than in the constructor.
     *
     * Always answers JSON; never redirects.
     */
    public function smart_create()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        header('Content-Type: application/json; charset=utf-8');

        $fail = function ($message) {
            echo json_encode(['success' => false, 'message' => $message]);
            exit;
        };

        // Same gate as the injector, so an endpoint is never reachable when the
        // widget that feeds it is switched off (or when this is a tenant, where
        // perfex_saas owns the reporting path).
        if (!pro_tickets_smart_admin_available()) {
            $fail(_l('access_denied'));
        }

        $message    = trim((string) $this->input->post('message', false));
        $subject    = trim((string) $this->input->post('subject'));
        $department = (int) $this->input->post('department');
        $priority   = (int) $this->input->post('priority');
        $source_url = trim((string) $this->input->post('source_url'));

        // "Smart" fallback: derive a subject from the first line of the issue
        // description when the reporter didn't type one.
        if ($subject === '' && $message !== '') {
            $first   = trim(strtok(strip_tags($message), "\n"));
            $subject = mb_substr($first, 0, 60) . (mb_strlen($first) > 60 ? '…' : '');
        }

        $errors = [];
        if ($message === '') {
            $errors[] = _l('pro_tickets_smart_issue');
        }
        if (!$department) {
            $errors[] = _l('clients_ticket_open_departments');
        }
        if ($subject === '') {
            $errors[] = _l('customer_ticket_subject');
        }

        if (!empty($errors)) {
            $fail(_l('pro_tickets_smart_missing_fields') . ' ' . implode(', ', $errors));
        }

        // Tickets have no native staff requester, so an internal report carries
        // the reporter's own name + e-mail with no client attached — the same
        // representation the new-ticket form uses for a staff requester. Staff
        // can link it to a customer later from the ticket header if it turns
        // out to be customer-facing.
        $staff = $this->db->select('firstname, lastname, email')
            ->where('staffid', get_staff_user_id())
            ->get(db_prefix() . 'staff')->row();

        if (!$staff) {
            $fail(_l('access_denied'));
        }

        $reporter_name = trim($staff->firstname . ' ' . $staff->lastname);

        // Context header: who reported it, which module, and the exact page —
        // the reason an internal report beats a verbal one.
        $area = pro_tickets_smart_admin_area($source_url);

        $body = '<p style="color:#64748b;font-size:12px;margin:0 0 6px;">'
            . html_escape(_l('pro_tickets_smart_internal_report')) . '</p>';
        $body .= '<p style="font-size:12px;margin:0 0 2px;"><strong>'
            . html_escape(_l('pro_tickets_smart_reported_by')) . ':</strong> '
            . html_escape($reporter_name . ' <' . $staff->email . '>') . '</p>';
        if ($area !== '') {
            $body .= '<p style="font-size:12px;margin:0 0 2px;"><strong>'
                . html_escape(_l('pro_tickets_smart_area')) . ':</strong> '
                . html_escape($area) . '</p>';
        }
        if ($source_url !== '') {
            // Anchored here rather than only at display time, so the page the
            // report is about is one click away from the notification e-mail
            // as well as from the ticket thread.
            $body .= '<p style="font-size:12px;margin:0 0 10px;"><strong>'
                . html_escape(_l('pro_tickets_smart_page')) . ':</strong> '
                . pro_tickets_link_html(html_escape($source_url), html_escape($source_url)) . '</p>';
        }
        $body .= '<div>' . nl2br(html_escape($message)) . '</div>';

        // The annotated screenshot rides along as attachments[0] and is picked
        // up by the core handle_ticket_attachments() inside add().
        $this->load->model('tickets_model');
        $ticket_id = (int) $this->tickets_model->add([
            'subject'    => mb_substr($subject, 0, 191),
            'message'    => $body,
            'department' => $department,
            'priority'   => $priority ?: hooks()->apply_filters('new_ticket_priority_selected', 2),
            'name'       => $reporter_name,
            'email'      => $staff->email,
        ], get_staff_user_id());

        if (!$ticket_id) {
            $fail(_l('something_went_wrong'));
        }

        $this->smart_store_screenshot($ticket_id);

        pro_tickets_log(
            $ticket_id,
            'smart_reported',
            $area !== '' ? $area : _l('pro_tickets_smart_area_unknown'),
            get_staff_user_id()
        );

        // Land the reporter on the helpdesk view of their report; staff without
        // Pro Tickets access go to the core ticket screen instead, which is open
        // to every staff member (subject to core's own department filtering).
        $url = pro_tickets_staff_can_access()
            ? admin_url('pro_tickets/ticket/' . $ticket_id)
            : admin_url('tickets/ticket/' . $ticket_id);

        echo json_encode([
            'success'   => true,
            'ticket_id' => $ticket_id,
            'message'   => _l('pro_tickets_smart_internal_created', $ticket_id),
            'url'       => $url,
        ]);
        exit;
    }

    /**
     * Make sure a Smart Ticket report keeps its annotated screenshot.
     *
     * The core add() already ran handle_ticket_attachments(), which honours the
     * admin-configurable "allowed ticket attachment extensions" list — a report
     * whose .jpg isn't on that list would silently lose its image, gutting the
     * whole point of the capture. So when nothing was stored, the screenshot is
     * written here instead. That allowlist guards arbitrary user uploads; this
     * file is generated by our own widget and is verified to be a real image
     * before it is written.
     *
     * No-op when core already stored the file (the common case).
     *
     * @param  int $ticket_id
     * @return void
     */
    private function smart_store_screenshot($ticket_id)
    {
        if (empty($_FILES['attachments']['tmp_name'][0])) {
            return;
        }

        $stored = (int) $this->db->where('ticketid', $ticket_id)
            ->count_all_results(db_prefix() . 'ticket_attachments');
        if ($stored > 0) {
            return; // core accepted it
        }

        $tmp = $_FILES['attachments']['tmp_name'][0];
        if (!is_uploaded_file($tmp)) {
            return;
        }

        $info = @getimagesize($tmp);
        if (!$info || empty($info['mime']) || strpos($info['mime'], 'image/') !== 0) {
            return; // not an image — never write it
        }

        $extensions = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
        if (!isset($extensions[$info['mime']])) {
            return;
        }

        $path     = get_upload_path_by_type('ticket') . $ticket_id . '/';
        $filename = 'smart-' . date('YmdHis') . '-' . mt_rand(1000, 9999) . '.' . $extensions[$info['mime']];

        _maybe_create_upload_path($path);
        if (!is_dir($path) || !is_writable($path) || !@move_uploaded_file($tmp, $path . $filename)) {
            log_activity('Pro Tickets: could not store Smart Ticket screenshot for ticket #' . $ticket_id);

            return;
        }

        @chmod($path . $filename, 0644);
        $this->tickets_model->insert_ticket_attachments_to_database(
            [['file_name' => $filename, 'filetype' => $info['mime']]],
            $ticket_id
        );
    }

    /* ───────────────────────────── AJAX utils ───────────────────────────── */

    public function contacts_search()
    {
        $q = trim((string) $this->input->get('q'));
        echo json_encode($q === '' ? [] : $this->pro_model->search_contacts($q));
    }

    /**
     * AJAX — the unified requester search for the new-ticket form: client
     * contacts and internal staff in one typed result list.
     */
    public function requester_search()
    {
        $q = trim((string) $this->input->get('q'));
        echo json_encode($q === '' ? [] : $this->pro_model->search_requesters($q));
    }

    /**
     * AJAX — active staff of one SaaS tenant (cross-DB), for the tenant-first
     * requester flow on the new-ticket form. Returns a plain array of staff
     * on success, or {error: "…"} when the tenant can't be reached.
     */
    public function tenant_staff()
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!pro_tickets_staff_can_create()) {
            echo json_encode(['error' => _l('access_denied')]);

            return;
        }

        $slug = trim((string) $this->input->get('slug'));
        echo json_encode($slug === '' ? [] : $this->pro_model->get_tenant_staff($slug));
    }

    /**
     * AJAX — active contacts of one customer (master CRM client), the
     * customer branch of the tenant/customer requester flow.
     */
    public function customer_contacts()
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!pro_tickets_staff_can_create()) {
            echo json_encode(['error' => _l('access_denied')]);

            return;
        }

        $userid = (int) $this->input->get('userid');
        echo json_encode($userid ? $this->pro_model->get_customer_contacts($userid) : []);
    }

    /**
     * JSON — a predefined message with its merge tags filled in. Agent, role,
     * company and date always resolve; {Name} / {Subject} resolve too when a
     * ticket is given (the reply composer), and are otherwise left in place
     * for the browser to fill as the new-ticket form is completed.
     */
    public function canned($id)
    {
        $reply = $this->pro_model->get_predefined_reply((int) $id);

        if (!$reply) {
            echo json_encode(['message' => '']);

            return;
        }

        $ticket_id = (int) $this->input->get('ticket_id');
        $extra     = $ticket_id ? pro_tickets_ticket_tag_values($ticket_id) : [];

        echo json_encode(['message' => pro_tickets_apply_template_tags($reply->message, $extra)]);
    }

    /**
     * POST — save the current composer content as a predefined (canned) reply
     * so it can be reused on any ticket. Writes the same core table the
     * composer dropdown reads (tbltickets_predefined_replies).
     */
    public function save_canned()
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!$this->input->post() || !pro_tickets_staff_can_edit()) {
            echo json_encode(['success' => false, 'message' => _l('access_denied')]);

            return;
        }

        $name    = trim((string) $this->input->post('name'));
        $message = trim((string) $this->input->post('message', false));

        if ($name === '' || $message === '' || $message === '<p></p>') {
            echo json_encode(['success' => false, 'message' => _l('pro_tickets_canned_missing')]);

            return;
        }

        $this->db->insert(db_prefix() . 'tickets_predefined_replies', [
            'name'    => $name,
            'message' => $message,
        ]);
        $id = (int) $this->db->insert_id();
        log_activity('New Predefined Reply Added [' . $name . ']');

        echo json_encode(['success' => (bool) $id, 'id' => $id, 'name' => $name]);
    }

    /**
     * POST — transfer a ticket to another department and (optionally) assign
     * it to one of that department's members in the same action. The member
     * must actually belong to the target department (tblstaff_departments) —
     * that is the whole point of the combined control.
     */
    public function transfer($id)
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!$this->input->post() || !pro_tickets_staff_can_edit()) {
            echo json_encode(['success' => false, 'message' => _l('access_denied')]);

            return;
        }

        $id     = (int) $id;
        $ticket = $this->db->where('ticketid', $id)->get(db_prefix() . 'tickets')->row();
        if (!$ticket) {
            echo json_encode(['success' => false, 'message' => _l('pro_tickets_something_wrong')]);

            return;
        }

        $department = (int) $this->input->post('department');
        $assigned   = (int) $this->input->post('assigned');

        $dept = $this->db->where('departmentid', $department)->get(db_prefix() . 'departments')->row();
        if (!$dept) {
            echo json_encode(['success' => false, 'message' => _l('pro_tickets_something_wrong')]);

            return;
        }

        if ($assigned) {
            $is_member = (int) ($this->db->query(
                'SELECT COUNT(*) AS c FROM `' . db_prefix() . 'staff_departments`
                 WHERE staffid = ? AND departmentid = ?',
                [$assigned, $department]
            )->row()->c ?? 0);
            if (!$is_member) {
                echo json_encode(['success' => false, 'message' => _l('pro_tickets_transfer_not_member')]);

                return;
            }
        }

        $this->db->where('ticketid', $id)->update(db_prefix() . 'tickets', [
            'department' => $department,
            'assigned'   => $assigned,
        ]);

        pro_tickets_log(
            $id,
            'transferred',
            $dept->name . ($assigned ? ' · ' . get_staff_full_name($assigned) : ''),
            get_staff_user_id()
        );

        // A department change can re-match a different SLA policy.
        pro_tickets_reapply_sla($id);

        if ($assigned && $assigned != get_staff_user_id()) {
            pro_tickets_notify($assigned, 'pro_tickets_not_assigned_to_you', $id, $ticket->subject);
        }
        pro_tickets_notify_watchers($id, 'pro_tickets_not_transferred', $ticket->subject, [get_staff_user_id(), $assigned]);

        if ($assigned && (int) $assigned !== (int) $ticket->assigned) {
            pro_tickets_fire_omni('pro_ticket_assigned', $id, [
                'assigned_by'   => get_staff_full_name(get_staff_user_id()),
                'assign_reason' => pro_tickets_omni_l('pro_tickets_hook_assign_transfer') . ' — ' . $dept->name,
            ]);
        }

        echo json_encode(['success' => true]);
    }

    /* ─────────────────────────── Settings ───────────────────────────────── */

    public function settings()
    {
        if (!pro_tickets_staff_can_settings()) {
            access_denied('Pro Tickets');
        }

        if ($this->input->post()) {
            update_option('pro_tickets_auto_assign', in_array($this->input->post('auto_assign'), ['off', 'round_robin', 'least_busy']) ? $this->input->post('auto_assign') : 'off');
            update_option('pro_tickets_auto_assign_role', max(0, (int) $this->input->post('auto_assign_role')));
            update_option('pro_tickets_auto_close_enabled', $this->input->post('auto_close_enabled') ? '1' : '0');
            update_option('pro_tickets_auto_close_days', max(1, (int) $this->input->post('auto_close_days')));
            update_option('pro_tickets_sla_warning_pct', min(99, max(1, (int) $this->input->post('sla_warning_pct'))));
            update_option('pro_tickets_bump_priority_on_breach', $this->input->post('bump_priority_on_breach') ? '1' : '0');
            update_option('pro_tickets_notify_watchers', $this->input->post('notify_watchers') ? '1' : '0');
            update_option('pro_tickets_smart_admin_enabled', $this->input->post('smart_admin_enabled') ? '1' : '0');
            update_option('pro_tickets_new_ticket_template', max(0, (int) $this->input->post('new_ticket_template')));

            set_alert('success', _l('settings_updated'));
            redirect(admin_url('pro_tickets/settings'));
        }

        pro_tickets_seed_default_templates();
        $data['canned']      = $this->pro_model->get_predefined_replies();
        $data['slas']        = $this->pro_model->get_slas();
        $data['departments'] = $this->pro_model->get_departments();
        $data['priorities']  = $this->pro_model->get_priorities();
        $data['staff']       = $this->pro_model->get_staff();
        $data['roles']       = $this->pro_model->get_roles();
        $data['dept_agents'] = $this->pro_model->get_dept_agents();
        $data['title']       = _l('pro_tickets_settings');
        $this->load->view('settings', $data);
    }

    /**
     * POST — save the per-department agent roster (Department agents card).
     */
    public function save_dept_agents()
    {
        if (!$this->input->post() || !pro_tickets_staff_can_settings()) {
            access_denied('Pro Tickets');
        }

        $map = $this->input->post('dept_agents');
        $this->pro_model->save_dept_agents(is_array($map) ? $map : []);

        set_alert('success', _l('pro_tickets_dept_agents_saved'));
        redirect(admin_url('pro_tickets/settings'));
    }

    public function sla_save()
    {
        if (!$this->input->post() || !pro_tickets_staff_can_settings()) {
            access_denied('Pro Tickets');
        }

        $id   = (int) $this->input->post('id');
        $name = trim((string) $this->input->post('name'));

        if ($name === '') {
            set_alert('warning', _l('pro_tickets_sla_name_required'));
        } else {
            $this->pro_model->save_sla([
                'name'          => $name,
                'department_id' => $this->input->post('department_id'),
                'priority_id'   => $this->input->post('priority_id'),
                'frt_minutes'   => (int) round((float) $this->input->post('frt_hours') * 60),
                'res_minutes'   => (int) round((float) $this->input->post('res_hours') * 60),
                'escalate_to'   => $this->input->post('escalate_to'),
                'active'        => $this->input->post('active'),
                'sort_order'    => $this->input->post('sort_order'),
            ], $id ?: null);
            set_alert('success', _l('pro_tickets_sla_saved'));
        }

        redirect(admin_url('pro_tickets/settings'));
    }

    public function sla_delete($id)
    {
        if (!pro_tickets_staff_can_settings()) {
            access_denied('Pro Tickets');
        }

        $this->pro_model->delete_sla((int) $id);
        set_alert('success', _l('pro_tickets_sla_deleted'));
        redirect(admin_url('pro_tickets/settings'));
    }

    /**
     * POST — run the automation pipeline immediately (settings page button).
     */
    public function run_automation()
    {
        if (!$this->input->post() || !pro_tickets_staff_can_settings()) {
            ajax_access_denied();
        }

        pro_tickets_run_automation(true);
        echo json_encode(['success' => true]);
    }
}
