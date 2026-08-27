<?php

defined('BASEPATH') or exit('No direct script access allowed');

use app\services\ValidatesContact;

/**
 * Modern client-portal support tickets for the Pro Tickets module.
 *
 * This mirrors the behaviour of the core Clients ticket actions (list / open /
 * reply / status change) but renders a modern, Pro-Tickets-styled interface.
 * It intentionally reuses the CORE tickets_model so every ticket created here
 * shows up in the admin-side Pro Tickets helpdesk (same tbltickets table).
 */
class Clients_pro_tickets extends ClientsController
{
    /**
     * Redirects unauthenticated visitors to the login page (preserving the
     * return URL) exactly like the core Clients controller. Invoked
     * automatically by ClientsController::__construct(). Without this, an
     * unauthenticated hit falls through to redirect(site_url()) → site root,
     * which broke the magic-auth bridge "back" navigation.
     */
    use ValidatesContact;

    public function __construct()
    {
        parent::__construct();
        $this->load->model('tickets_model');
        $this->load->model('projects_model');
        $this->load->model('pro_tickets/pro_tickets_model', 'pro_model');
    }

    /**
     * Ticket list + summary. Optional numeric $status filters by ticket status.
     */
    public function index($status = '')
    {
        if (!has_contact_permission('support')) {
            set_alert('warning', _l('access_denied'));
            redirect(site_url());
        }

        // Landing: a compact row of the four department cards. Each links to the
        // department ticket-list page.
        $data['dept_cards'] = $this->department_summary_cards();
        // Closed tickets still waiting for a satisfaction rating — powers the
        // "how did we do?" notification stack on the portal.
        $data['feedback_pending'] = $this->get_feedback_pending();
        $data['bodyclass']        = 'tickets pro-tickets-portal';
        $data['title']            = _l('pro_tickets_customer_success');
        $this->data($data);
        $this->view('clients/list');
        $this->layout();
    }

    /**
     * Ticket list for a single department card. $key is one of the fixed bucket
     * keys (onboarding|technical|general|others); an optional numeric $status
     * filters within that department.
     */
    public function department($key = '', $status = '')
    {
        if (!has_contact_permission('support')) {
            set_alert('warning', _l('access_denied'));
            redirect(site_url());
        }

        $buckets = $this->department_buckets();
        if (!isset($buckets[$key])) {
            show_404();
        }

        $where = db_prefix() . 'tickets.userid=' . get_client_user_id();
        if (!can_logged_in_contact_view_all_tickets()) {
            $where .= ' AND ' . db_prefix() . 'tickets.contactid=' . get_contact_user_id();
        }
        $where .= $this->bucket_department_sql($key);

        $defaultStatuses = hooks()->apply_filters('customers_area_list_default_ticket_statuses', [1, 2, 3, 4]);
        if (!is_numeric($status)) {
            $where .= ' AND status IN (' . implode(', ', $defaultStatuses) . ')';
        } else {
            $where .= ' AND status=' . $this->db->escape_str($status);
        }

        $data['bucket_key']    = $key;
        $data['bucket_label']  = $buckets[$key]['label'];
        $data['bucket_icon']   = $buckets[$key]['icon'];
        $data['list_statuses'] = is_numeric($status) ? [$status] : $defaultStatuses;
        $data['active_status'] = is_numeric($status) ? (int) $status : 0;
        $data['show_submitter_on_table'] = show_ticket_submitter_on_clients_area_table();
        $data['tickets']       = $this->tickets_model->get('', $where);
        $data['reply_counts']  = $this->get_reply_counts(array_column($data['tickets'], 'ticketid'));

        // Per-status counters for this department (full totals, independent of
        // the active status filter).
        $cards            = $this->build_department_cards([]);
        $data['statuses'] = $cards[$key]['statuses'];

        $data['feedback_pending'] = $this->get_feedback_pending();
        $data['bodyclass']        = 'tickets pro-tickets-portal';
        $data['title']            = $buckets[$key]['label'];
        $this->data($data);
        $this->view('clients/department');
        $this->layout();
    }

    /**
     * Reply totals per ticket, one query, keyed by ticket id.
     */
    private function get_reply_counts(array $ticket_ids)
    {
        $counts = [];
        $ticket_ids = array_filter(array_map('intval', $ticket_ids));
        if (empty($ticket_ids)) {
            return $counts;
        }

        $rows = $this->db->query(
            'SELECT ticketid, COUNT(*) AS cnt FROM `' . db_prefix() . 'ticket_replies`
             WHERE ticketid IN (' . implode(',', $ticket_ids) . ')
             GROUP BY ticketid'
        )->result();
        foreach ($rows as $row) {
            $counts[(int) $row->ticketid] = (int) $row->cnt;
        }

        return $counts;
    }

    /**
     * Closed tickets of this customer that were closed by a staff member and
     * have no feedback yet — newest first, capped so the notification stack
     * stays sane.
     */
    private function get_feedback_pending()
    {
        pro_tickets_ensure_schema();
        $p = db_prefix();

        $where = 't.userid = ' . (int) get_client_user_id();
        if (!can_logged_in_contact_view_all_tickets()) {
            $where .= ' AND t.contactid = ' . (int) get_contact_user_id();
        }

        return $this->db->query(
            "SELECT t.ticketid, t.subject, m.closed_by_staff, m.resolved_at,
                    CONCAT(s.firstname, ' ', s.lastname) AS agent_name
             FROM `{$p}tickets` t
             JOIN `{$p}pro_tickets_meta` m ON m.ticket_id = t.ticketid
             LEFT JOIN `{$p}pro_tickets_feedback` f ON f.ticket_id = t.ticketid
             LEFT JOIN `{$p}staff` s ON s.staffid = t.assigned
             WHERE {$where} AND t.status = 5 AND t.merged_ticket_id IS NULL
               AND f.id IS NULL AND m.closed_by_staff IS NOT NULL AND m.closed_by_staff > 0
             ORDER BY m.resolved_at DESC
             LIMIT 3"
        )->result();
    }

    /**
     * The fixed set of department cards shown on the portal, in display order.
     * Every real department is folded into one of these buckets by matching its
     * name; anything that matches none lands in "Others".
     *
     * @return array<string,array{label:string,match:string[]}>
     */
    private function department_buckets()
    {
        return [
            'onboarding' => ['label' => _l('pro_tickets_dept_onboarding'), 'icon' => 'fa-graduation-cap',      'match' => ['onboard']],
            'technical'  => ['label' => _l('pro_tickets_dept_technical'),  'icon' => 'fa-screwdriver-wrench',  'match' => ['technical']],
            'general'    => ['label' => _l('pro_tickets_dept_general'),    'icon' => 'fa-headset',             'match' => ['general']],
            'others'     => ['label' => _l('pro_tickets_dept_others'),     'icon' => 'fa-ellipsis',            'match' => []],
        ];
    }

    /**
     * Resolve a department name to one of the fixed bucket keys.
     */
    private function department_bucket_key($department_name)
    {
        $name = strtolower((string) $department_name);
        foreach ($this->department_buckets() as $key => $bucket) {
            foreach ($bucket['match'] as $needle) {
                if ($needle !== '' && strpos($name, $needle) !== false) {
                    return $key;
                }
            }
        }

        return 'others';
    }

    /**
     * Build the department-card view model: the four fixed cards, each carrying
     * its own status counters and the visible tickets that belong to it.
     *
     * @param array $tickets The tickets already loaded for the current view.
     * @return array
     */
    private function build_department_cards(array $tickets)
    {
        $p = db_prefix();

        // Full per-department / per-status counts for this customer, regardless
        // of the active status filter, so the counters always show real totals.
        $where = db_prefix() . 'tickets.userid = ' . (int) get_client_user_id();
        if (!can_logged_in_contact_view_all_tickets()) {
            $where .= ' AND ' . db_prefix() . 'tickets.contactid = ' . (int) get_contact_user_id();
        }

        $count_rows = $this->db->query(
            "SELECT department, status, COUNT(*) AS cnt
             FROM `{$p}tickets`
             WHERE {$where} AND merged_ticket_id IS NULL
             GROUP BY department, status"
        )->result();

        // department id → bucket key (via the department name).
        $dept_bucket = [];
        foreach ($this->db->select('departmentid, name')->get($p . 'departments')->result() as $dept) {
            $dept_bucket[(int) $dept->departmentid] = $this->department_bucket_key($dept->name);
        }

        $statuses = $this->tickets_model->get_ticket_status();

        // Seed every card with a zeroed row per status.
        $cards = [];
        foreach ($this->department_buckets() as $key => $bucket) {
            $status_counts = [];
            foreach ($statuses as $st) {
                $status_counts[(int) $st['ticketstatusid']] = [
                    'ticketstatusid'  => (int) $st['ticketstatusid'],
                    'translated_name' => ticket_status_translate($st['ticketstatusid']),
                    'statuscolor'     => $st['statuscolor'],
                    'total_tickets'   => 0,
                ];
            }
            $cards[$key] = [
                'label'         => $bucket['label'],
                'statuses'      => $status_counts,
                'tickets'       => [],
                'total_tickets' => 0,
            ];
        }

        foreach ($count_rows as $row) {
            $bucket = $dept_bucket[(int) $row->department] ?? 'others';
            $status = (int) $row->status;
            if (isset($cards[$bucket]['statuses'][$status])) {
                $cards[$bucket]['statuses'][$status]['total_tickets'] += (int) $row->cnt;
                $cards[$bucket]['total_tickets'] += (int) $row->cnt;
            }
        }

        // Drop the visible tickets into their cards.
        foreach ($tickets as $ticket) {
            $bucket = $this->department_bucket_key($ticket['department_name'] ?? '');
            $cards[$bucket]['tickets'][] = $ticket;
        }

        return $cards;
    }

    /**
     * Compact landing cards: one per department bucket with its total / open /
     * unread counters and the URL of its ticket-list page.
     *
     * @return array
     */
    private function department_summary_cards()
    {
        $p = db_prefix();

        $where = $p . 'tickets.userid = ' . (int) get_client_user_id();
        if (!can_logged_in_contact_view_all_tickets()) {
            $where .= ' AND ' . $p . 'tickets.contactid = ' . (int) get_contact_user_id();
        }

        $rows = $this->db->query(
            "SELECT department, status,
                    COUNT(*) AS cnt,
                    SUM(CASE WHEN clientread = 0 THEN 1 ELSE 0 END) AS unread
             FROM `{$p}tickets`
             WHERE {$where} AND merged_ticket_id IS NULL
             GROUP BY department, status"
        )->result();

        $dept_bucket = [];
        foreach ($this->db->select('departmentid, name')->get($p . 'departments')->result() as $dept) {
            $dept_bucket[(int) $dept->departmentid] = $this->department_bucket_key($dept->name);
        }

        $statuses = $this->tickets_model->get_ticket_status();

        $cards = [];
        foreach ($this->department_buckets() as $key => $bucket) {
            // Seed a zeroed counter per status; the view renders only the
            // non-zero ones as chips.
            $status_counts = [];
            foreach ($statuses as $st) {
                $status_counts[(int) $st['ticketstatusid']] = [
                    'ticketstatusid'  => (int) $st['ticketstatusid'],
                    'translated_name' => ticket_status_translate($st['ticketstatusid']),
                    'statuscolor'     => $st['statuscolor'],
                    'count'           => 0,
                ];
            }
            $cards[$key] = [
                'key'      => $key,
                'label'    => $bucket['label'],
                'icon'     => $bucket['icon'],
                'total'    => 0,
                'unread'   => 0,
                'statuses' => $status_counts,
                'url'      => site_url('clients/pro_tickets/department/' . $key),
            ];
        }

        foreach ($rows as $row) {
            $bucket = $dept_bucket[(int) $row->department] ?? 'others';
            $status = (int) $row->status;
            $cards[$bucket]['total']  += (int) $row->cnt;
            $cards[$bucket]['unread'] += (int) $row->unread;
            if (isset($cards[$bucket]['statuses'][$status])) {
                $cards[$bucket]['statuses'][$status]['count'] += (int) $row->cnt;
            }
        }

        return $cards;
    }

    /**
     * department id → bucket key map, then inverted to bucket key → [dept ids].
     *
     * @return array<string,int[]>
     */
    private function department_ids_by_bucket()
    {
        $map = array_fill_keys(array_keys($this->department_buckets()), []);
        foreach ($this->db->select('departmentid, name')->get(db_prefix() . 'departments')->result() as $dept) {
            $map[$this->department_bucket_key($dept->name)][] = (int) $dept->departmentid;
        }

        return $map;
    }

    /**
     * SQL fragment constraining a ticket query to one department bucket.
     * "Others" is expressed as "not in the three named buckets" so it also
     * catches tickets on departments that were later renamed or removed.
     */
    private function bucket_department_sql($key)
    {
        $p   = db_prefix();
        $ids = $this->department_ids_by_bucket();

        if ($key === 'others') {
            $named = array_map('intval', array_merge($ids['onboarding'], $ids['technical'], $ids['general']));

            return empty($named) ? '' : ' AND ' . $p . 'tickets.department NOT IN (' . implode(',', $named) . ')';
        }

        $bucket_ids = array_map('intval', $ids[$key] ?? []);

        return empty($bucket_ids)
            ? ' AND 1=0'
            : ' AND ' . $p . 'tickets.department IN (' . implode(',', $bucket_ids) . ')';
    }

    /**
     * The real author behind this portal session.
     *
     * The SaaS bridge signs EVERY tenant staff member into this portal as the
     * customer's primary contact, so get_contact_user_id() on its own stamps
     * every ticket and reply with the account owner's details no matter who
     * wrote it. When the bridge handed us the real person, prefer them: their
     * own contact record on this install when one exists (matched by e-mail),
     * otherwise contactid 0 + their name / e-mail — exactly how core represents
     * a submitter who has no portal account of their own.
     *
     * @return array{contactid:int,name:string,email:string}|null
     *         null when the logged-in contact IS the author (nothing to change).
     */
    private function portal_author()
    {
        $actor = $this->session->userdata('perfex_saas_portal_actor');
        if (!is_array($actor) || empty($actor['email'])
            || (int) ($actor['clientid'] ?? 0) !== (int) get_client_user_id()) {
            return null;
        }

        $contact = $this->db->query(
            'SELECT id FROM `' . db_prefix() . 'contacts` WHERE userid = ? AND LOWER(email) = ? LIMIT 1',
            [(int) get_client_user_id(), strtolower($actor['email'])]
        )->row();

        if ($contact && (int) $contact->id === (int) get_contact_user_id()) {
            return null;
        }

        return [
            'contactid' => $contact ? (int) $contact->id : 0,
            'name'      => $actor['name'] !== '' ? $actor['name'] : $actor['email'],
            'email'     => $actor['email'],
        ];
    }

    /**
     * contactid to hand to the core model when creating a ticket / reply.
     * A contact-less author cannot be passed in — core dereferences the contact
     * unconditionally while the row belongs to a customer — so those are written
     * with the session contact and corrected by stamp_portal_author() below.
     */
    private function author_contact_id($author)
    {
        return $author && $author['contactid'] !== 0 ? $author['contactid'] : get_contact_user_id();
    }

    /**
     * Rewrite a freshly created ticket / reply so it carries its real author.
     * No-op unless the author has no contact record here.
     */
    private function stamp_portal_author($table, $key, $id, $author)
    {
        if (!$author || $author['contactid'] !== 0) {
            return;
        }

        $this->db->where($key, (int) $id)->update(db_prefix() . $table, [
            'contactid' => 0,
            'name'      => $author['name'],
            'email'     => $author['email'],
        ]);
    }

    /**
     * Open a new ticket. Mirrors core Clients::open_ticket().
     */
    public function open()
    {
        if (!has_contact_permission('support')) {
            set_alert('warning', _l('access_denied'));
            redirect(site_url());
        }

        if ($this->input->post()) {
            $this->form_validation->set_rules('subject', _l('customer_ticket_subject'), 'required');
            $this->form_validation->set_rules('department', _l('clients_ticket_open_departments'), 'required');
            $this->form_validation->set_rules('priority', _l('priority'), 'required');

            $custom_fields = get_custom_fields('tickets', [
                'show_on_client_portal' => 1,
                'required'              => 1,
            ]);
            foreach ($custom_fields as $field) {
                $field_name = 'custom_fields[' . $field['fieldto'] . '][' . $field['id'] . ']';
                if ($field['type'] == 'checkbox' || $field['type'] == 'multiselect') {
                    $field_name .= '[]';
                }
                $this->form_validation->set_rules($field_name, $field['name'], 'required');
            }

            if ($this->form_validation->run() !== false) {
                $data   = $this->input->post();
                $author = $this->portal_author();

                $id = $this->tickets_model->add([
                    'subject'    => $data['subject'],
                    'department' => $data['department'],
                    'priority'   => $data['priority'],
                    'service'    => isset($data['service']) && is_numeric($data['service'])
                        ? $data['service']
                        : null,
                    'project_id' => isset($data['project_id']) && is_numeric($data['project_id'])
                        ? $data['project_id']
                        : 0,
                    'custom_fields' => isset($data['custom_fields']) && is_array($data['custom_fields'])
                        ? $data['custom_fields']
                        : [],
                    'message'   => $this->input->post('message', false),
                    'contactid' => $this->author_contact_id($author),
                    'userid'    => get_client_user_id(),
                ]);

                if ($id) {
                    $this->stamp_portal_author('tickets', 'ticketid', $id, $author);
                    set_alert('success', _l('new_ticket_added_successfully', $id));
                    redirect(site_url('clients/pro_tickets/ticket/' . $id));
                }
            }
        }

        $data             = [];
        $data['projects'] = $this->projects_model->get_projects_for_ticket(get_client_user_id());
        $data['title']    = _l('new_ticket');
        $data['bodyclass'] = 'pro-tickets-portal';
        $this->data($data);
        $this->view('clients/open');
        $this->layout();
    }

    /**
     * Smart Ticket capture endpoint (AJAX).
     *
     * Powers the keyboard-shortcut "annotate → describe → submit" widget on the
     * client portal. Receives the annotated screenshot as a normal multipart
     * file field (`attachments[0]`) so the CORE tickets_model->add() picks it up
     * via handle_ticket_attachments() exactly like the regular ticket form.
     *
     * Always answers JSON — never redirects — so the front-end can react inline.
     */
    public function smart_create()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        header('Content-Type: application/json; charset=utf-8');

        if (!has_contact_permission('support')) {
            echo json_encode([
                'success' => false,
                'message' => _l('access_denied'),
            ]);
            die;
        }

        $message    = trim((string) $this->input->post('message', false));
        $department = $this->input->post('department');
        $priority   = $this->input->post('priority');
        $subject    = trim((string) $this->input->post('subject'));

        // "Smart" fallback: derive a subject from the first line of the issue
        // description when the customer didn't type one explicitly.
        if ($subject === '' && $message !== '') {
            $first   = trim(strtok(strip_tags($message), "\n"));
            $subject = mb_substr($first, 0, 60) . (mb_strlen($first) > 60 ? '…' : '');
        }

        $errors = [];
        if ($subject === '') {
            $errors[] = _l('customer_ticket_subject');
        }
        if (empty($department)) {
            $errors[] = _l('clients_ticket_open_departments');
        }
        if ($message === '') {
            $errors[] = _l('clients_ticket_open_body');
        }

        if (!empty($errors)) {
            echo json_encode([
                'success' => false,
                'message' => _l('pro_tickets_smart_missing_fields') . ' ' . implode(', ', $errors),
            ]);
            die;
        }

        $author = $this->portal_author();

        $id = $this->tickets_model->add([
            'subject'    => $subject,
            'department' => $department,
            'priority'   => is_numeric($priority) ? $priority : hooks()->apply_filters('new_ticket_priority_selected', 2),
            'message'    => $message,
            'contactid'  => $this->author_contact_id($author),
            'userid'     => get_client_user_id(),
        ]);

        if ($id) {
            $this->stamp_portal_author('tickets', 'ticketid', $id, $author);
            echo json_encode([
                'success'   => true,
                'ticket_id' => $id,
                'message'   => _l('new_ticket_added_successfully', $id),
                'url'       => site_url('clients/pro_tickets/ticket/' . $id),
            ]);
            die;
        }

        echo json_encode([
            'success' => false,
            'message' => _l('something_went_wrong'),
        ]);
        die;
    }

    /**
     * View a single ticket + thread, and post a reply. Mirrors core Clients::ticket().
     */
    public function ticket($id = '')
    {
        if (!has_contact_permission('support')) {
            set_alert('warning', _l('access_denied'));
            redirect(site_url());
        }

        if (!$id) {
            redirect(site_url('clients/pro_tickets'));
        }

        $data['ticket'] = $this->tickets_model->get_ticket_by_id($id, get_client_user_id());
        if (!$data['ticket'] || $data['ticket']->userid != get_client_user_id()) {
            show_404();
        }

        if ($data['ticket']->merged_ticket_id != null) {
            redirect(site_url('clients/pro_tickets/ticket/' . $data['ticket']->merged_ticket_id));
        }

        // Heal Smart Ticket screenshots that older code saved into a tenant's
        // own upload dir instead of the master ticket uploads dir.
        pro_tickets_rescue_stray_attachments($id);

        if ($this->input->post()) {
            $this->form_validation->set_rules('message', _l('ticket_reply'), 'required');

            if ($this->form_validation->run() !== false) {
                $author  = $this->portal_author();
                $replyid = $this->tickets_model->add_reply([
                    'message'   => $this->input->post('message', false),
                    'contactid' => $this->author_contact_id($author),
                    'userid'    => get_client_user_id(),
                ], $id);

                if ($replyid) {
                    $this->stamp_portal_author('ticket_replies', 'id', $replyid, $author);
                    set_alert('success', _l('replied_to_ticket_successfully', $id));
                }

                redirect(site_url('clients/pro_tickets/ticket/' . $id));
            }
        }

        $data['ticket_replies'] = $this->tickets_model->get_ticket_replies($id);
        $data['ticket_todos']   = $this->pro_model->get_ticket_todos($id);
        $data['todo_progress']  = $this->pro_model->get_ticket_todo_progress($id);

        // Satisfaction feedback — existing rating (read-only display) or, on a
        // closed ticket without one, the prompt state for the feedback modal.
        pro_tickets_ensure_schema();
        $data['ticket_feedback'] = pro_tickets_get_feedback($id);
        $data['feedback_agent']  = '';
        if ((int) $data['ticket']->assigned !== 0) {
            $agent = $this->db->select('firstname, lastname')
                ->where('staffid', (int) $data['ticket']->assigned)
                ->get(db_prefix() . 'staff')->row();
            $data['feedback_agent'] = $agent ? trim($agent->firstname . ' ' . $agent->lastname) : '';
        }

        $data['title']          = $data['ticket']->subject;
        $data['bodyclass']      = 'pro-tickets-portal';
        $this->data($data);
        $this->view('clients/single');
        $this->layout();
    }

    /**
     * Submit satisfaction feedback on a closed ticket (AJAX, JSON response).
     *
     * One rating per ticket, only by the customer the ticket belongs to and
     * only while the ticket is closed. The agent on record is the assignee at
     * submit time.
     */
    public function feedback()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        header('Content-Type: application/json; charset=utf-8');

        if (!has_contact_permission('support')) {
            echo json_encode(['success' => false, 'message' => _l('access_denied')]);
            die;
        }

        $ticket_id = (int) $this->input->post('ticket_id');
        $rating    = (int) $this->input->post('rating');
        $comment   = trim((string) $this->input->post('comment', false));

        $p      = db_prefix();
        $ticket = $this->db->where('ticketid', $ticket_id)->get($p . 'tickets')->row();

        if (!$ticket || (int) $ticket->userid !== (int) get_client_user_id()
            || (!can_logged_in_contact_view_all_tickets() && (int) $ticket->contactid !== (int) get_contact_user_id())) {
            echo json_encode(['success' => false, 'message' => _l('access_denied')]);
            die;
        }

        if ((int) $ticket->status !== 5) {
            echo json_encode(['success' => false, 'message' => _l('pro_tickets_feedback_not_closed')]);
            die;
        }

        if ($rating < 1 || $rating > 5) {
            echo json_encode(['success' => false, 'message' => _l('pro_tickets_feedback_rating_required')]);
            die;
        }

        pro_tickets_ensure_schema();

        if (pro_tickets_get_feedback($ticket_id)) {
            echo json_encode(['success' => false, 'message' => _l('pro_tickets_feedback_already')]);
            die;
        }

        $meta = $this->db->where('ticket_id', $ticket_id)->get($p . 'pro_tickets_meta')->row();

        $this->db->insert($p . 'pro_tickets_feedback', [
            'ticket_id'       => $ticket_id,
            'userid'          => (int) $ticket->userid,
            'contactid'       => (int) get_contact_user_id(),
            'agent_id'        => (int) $ticket->assigned,
            'rating'          => $rating,
            'comment'         => $comment !== '' ? $comment : null,
            'closed_by_staff' => $meta->closed_by_staff ?? null,
            'created_at'      => date('Y-m-d H:i:s'),
        ]);

        pro_tickets_log($ticket_id, 'feedback_received', $rating . '/5');
        if ((int) $ticket->assigned !== 0) {
            pro_tickets_notify((int) $ticket->assigned, 'pro_tickets_not_feedback', $ticket_id, $ticket->subject);
        }

        pro_tickets_fire_omni('pro_ticket_feedback_received', $ticket_id, function ($d) use ($rating, $comment) {
            return [
                'rating'           => $rating . '/5',
                'rating_label'     => pro_tickets_rating_label($rating),
                'feedback_comment' => $comment,
                'agent_name'       => $d['assigned_name'],
            ];
        });

        echo json_encode(['success' => true, 'message' => _l('pro_tickets_feedback_thanks')]);
        die;
    }
}
