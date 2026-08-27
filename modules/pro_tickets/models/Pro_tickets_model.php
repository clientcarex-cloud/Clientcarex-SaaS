<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Pro Tickets — data layer.
 *
 * Reads/writes the CORE ticket tables plus the module's pro_tickets_*
 * extension tables. Anything that must fire core e-mails/hooks (create,
 * reply, status change, delete) is delegated to the core Tickets_model.
 */
class Pro_tickets_model extends App_Model
{
    public function __construct()
    {
        parent::__construct();
        pro_tickets_ensure_schema();
    }

    /* ───────────────────────── Reference data ───────────────────────────── */

    public function get_statuses()
    {
        return $this->db->order_by('statusorder', 'asc')->get(db_prefix() . 'tickets_status')->result();
    }

    public function get_priorities()
    {
        return $this->db->order_by('priorityid', 'asc')->get(db_prefix() . 'tickets_priorities')->result();
    }

    public function get_departments()
    {
        return $this->db->order_by('name', 'asc')->get(db_prefix() . 'departments')->result();
    }

    public function get_services()
    {
        return $this->db->order_by('name', 'asc')->get(db_prefix() . 'services')->result();
    }

    public function get_staff()
    {
        // `email` is the staff login username — shown next to the assignee on
        // the ticket header, so the agent handling it is identified by login.
        return $this->db->select('staffid, firstname, lastname, email')
            ->where('active', 1)->order_by('firstname', 'asc')
            ->get(db_prefix() . 'staff')->result();
    }

    public function get_roles()
    {
        return $this->db->select('roleid, name')->order_by('name', 'asc')
            ->get(db_prefix() . 'roles')->result();
    }

    /**
     * Per-department agent roster as [department_id => [staff_id, ...]].
     * Powers the "Department agents" settings card and the auto-assignment
     * candidate pool.
     *
     * @return array<int,int[]>
     */
    public function get_dept_agents()
    {
        $map = [];
        $rows = $this->db->select('department_id, staff_id')
            ->get(db_prefix() . 'pro_tickets_dept_agents')->result();
        foreach ($rows as $row) {
            $map[(int) $row->department_id][] = (int) $row->staff_id;
        }

        return $map;
    }

    /**
     * Replace the whole department→agents roster in one shot.
     *
     * @param array<int,int[]> $map department_id => [staff_id, ...]
     */
    public function save_dept_agents(array $map)
    {
        $p = db_prefix();
        $this->db->truncate($p . 'pro_tickets_dept_agents');

        $now  = date('Y-m-d H:i:s');
        $seen = [];
        foreach ($map as $department_id => $staff_ids) {
            $department_id = (int) $department_id;
            if ($department_id <= 0 || !is_array($staff_ids)) {
                continue;
            }
            foreach ($staff_ids as $staff_id) {
                $staff_id = (int) $staff_id;
                $key      = $department_id . ':' . $staff_id;
                if ($staff_id <= 0 || isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $this->db->insert($p . 'pro_tickets_dept_agents', [
                    'department_id' => $department_id,
                    'staff_id'      => $staff_id,
                    'created_at'    => $now,
                ]);
            }
        }
    }

    public function get_predefined_replies()
    {
        return $this->db->select('id, name')->order_by('name', 'asc')
            ->get(db_prefix() . 'tickets_predefined_replies')->result();
    }

    public function get_predefined_reply($id)
    {
        return $this->db->where('id', (int) $id)->get(db_prefix() . 'tickets_predefined_replies')->row();
    }

    /**
     * Contact search for the new-ticket form (name/email/company).
     */
    public function search_contacts($q, $limit = 15)
    {
        $p = db_prefix();
        $q = '%' . $this->db->escape_like_str($q) . '%';

        return $this->db->query(
            "SELECT c.id AS contactid, c.userid, c.firstname, c.lastname, c.email, c.phonenumber, cl.company
             FROM `{$p}contacts` c
             JOIN `{$p}clients` cl ON cl.userid = c.userid
             WHERE c.active = 1 AND (
                CONCAT(c.firstname, ' ', c.lastname) LIKE ? ESCAPE '!'
                OR c.email LIKE ? ESCAPE '!'
                OR cl.company LIKE ? ESCAPE '!'
             )
             ORDER BY c.firstname ASC LIMIT " . (int) $limit,
            [$q, $q, $q]
        )->result();
    }

    /**
     * Internal staff (tblstaff) search for the "Staff member" requester flow —
     * lets an agent raise a ticket on behalf of one of the tenant's own team
     * members (stored on the ticket as name + email, since staff are not
     * client contacts).
     */
    public function search_staff($q, $limit = 15)
    {
        $p = db_prefix();
        $like = '%' . $this->db->escape_like_str($q) . '%';

        return $this->db->query(
            "SELECT staffid, firstname, lastname, email
             FROM `{$p}staff`
             WHERE active = 1 AND (
                CONCAT(firstname, ' ', lastname) LIKE ? ESCAPE '!'
                OR email LIKE ? ESCAPE '!'
             )
             ORDER BY firstname ASC LIMIT " . (int) $limit,
            [$like, $like]
        )->result();
    }

    /**
     * ONE smart requester search powering the new-ticket "who is this for?"
     * box: client contacts (matched by name, email or their company) AND the
     * tenant's own staff members, merged into a single typed result list so the
     * agent never has to guess which tab a person lives under.
     *
     * Each row: type (contact|staff), id, userid, name, email, phone, company.
     */
    public function search_requesters($q, $limit = 8)
    {
        $out = [];

        // Tenant staff first: the front-end shows them as their own labelled
        // group, and keeping them ahead of a potentially long contact list
        // guarantees the tenant's own team is always visible in the picker.
        foreach ($this->search_staff($q, $limit) as $s) {
            $out[] = [
                'type'    => 'staff',
                'id'      => (int) $s->staffid,
                'userid'  => 0,
                'name'    => trim($s->firstname . ' ' . $s->lastname),
                'email'   => (string) $s->email,
                'phone'   => '',
                'company' => '',
            ];
        }

        foreach ($this->search_contacts($q, $limit) as $c) {
            $out[] = [
                'type'    => 'contact',
                'id'      => (int) $c->contactid,
                'userid'  => (int) $c->userid,
                'name'    => trim($c->firstname . ' ' . $c->lastname),
                'email'   => (string) $c->email,
                'phone'   => (string) $c->phonenumber,
                'company' => (string) $c->company,
            ];
        }

        return $out;
    }

    /**
     * Active contacts of one company — used for the same-company CC picker
     * on the ticket detail page.
     */
    public function get_company_contacts($userid)
    {
        return $this->db->select('id, userid, firstname, lastname, email, phonenumber')
            ->where('userid', (int) $userid)
            ->where('active', 1)
            ->order_by('firstname', 'ASC')
            ->get(db_prefix() . 'contacts')->result();
    }

    /**
     * The requester's mobile number — the contact's own, falling back to the
     * company record's. Same rule the Omni Messaging payload uses for
     * {mobile_number}, so the ticket page shows exactly the number a
     * hook-triggered SMS/WhatsApp would reach.
     */
    public function get_requester_mobile($contactid, $userid, $email = '')
    {
        $contact_phone = '';
        if ((int) $contactid !== 0) {
            $contact = $this->db->select('phonenumber')
                ->where('id', (int) $contactid)
                ->get(db_prefix() . 'contacts')->row();
            $contact_phone = $contact ? (string) $contact->phonenumber : '';
        }

        $client_phone = '';
        if ((int) $userid !== 0) {
            $client = $this->db->select('phonenumber')
                ->where('userid', (int) $userid)
                ->get(db_prefix() . 'clients')->row();
            $client_phone = $client ? (string) $client->phonenumber : '';
        }

        $mobile = pro_tickets_pick_mobile($contact_phone, $client_phone);

        // Tenant-raised tickets carry no contact record here (contactid is
        // reset to 0 and the person is stamped onto tickets.name/email), so
        // the only number they have is the one on their staff profile inside
        // the tenant's own database.
        if ($mobile === '') {
            $mobile = $this->get_tenant_staff_phone($userid, $email);
        }

        return $mobile;
    }

    /**
     * Phone number of a tenant staff member, looked up cross-DB by the login
     * e-mail the ticket was stamped with. Silent on any failure — an
     * unreachable tenant must never break a ticket page or a hook.
     */
    public function get_tenant_staff_phone($userid, $email)
    {
        $email = trim((string) $email);
        if ($email === '' || (int) $userid === 0) {
            return '';
        }

        $tenant = $this->get_client_tenant((int) $userid);
        if (!$tenant) {
            return '';
        }

        $conn = $this->open_tenant_db($tenant['slug']);
        if (isset($conn['error'])) {
            return '';
        }

        $db     = $conn['db'];
        $prefix = $conn['prefix'];

        if (!$db->table_exists($prefix . 'staff')) {
            $db->close();

            return '';
        }

        $row = $db->query(
            'SELECT phonenumber FROM `' . $prefix . 'staff` WHERE LOWER(email) = ? LIMIT 1',
            [strtolower($email)]
        )->row();
        $db->close();

        return $row ? trim((string) $row->phonenumber) : '';
    }

    /**
     * Active customers (master CRM clients) — the second source of the
     * tenant/customer requester flow on the new-ticket form.
     *
     * @return array<int,array{userid:int,company:string}>
     */
    public function get_customers()
    {
        $rows = $this->db->select('userid, company')
            ->where('active', 1)
            ->order_by('company', 'asc')
            ->get(db_prefix() . 'clients')->result();

        $out = [];
        foreach ($rows as $c) {
            $out[] = [
                'userid'  => (int) $c->userid,
                'company' => trim((string) $c->company) !== '' ? (string) $c->company : ('Customer #' . (int) $c->userid),
            ];
        }

        return $out;
    }

    /**
     * Active contacts of one customer, in the same row shape the tenant
     * staff endpoint uses so the front-end picker treats both alike.
     *
     * @return array<int,array{id:int,userid:int,name:string,email:string,phone:string}>
     */
    public function get_customer_contacts($userid)
    {
        $out = [];
        foreach ($this->get_company_contacts((int) $userid) as $c) {
            $out[] = [
                'id'     => (int) $c->id,
                'userid' => (int) $c->userid,
                'name'   => trim($c->firstname . ' ' . $c->lastname),
                'email'  => (string) $c->email,
                'phone'  => (string) $c->phonenumber,
            ];
        }

        return $out;
    }

    /* ─────────────────── SaaS tenants (master instance) ─────────────────── */

    /**
     * Active SaaS tenant companies of this (master) install — powers the
     * tenant-first requester flow on the new-ticket form. Returns [] on a
     * tenant instance or when perfex_saas is absent, which makes the form
     * fall back to the classic contact/staff smart search.
     *
     * @return array<int,array{slug:string,name:string}>
     */
    public function get_tenants()
    {
        if ((function_exists('perfex_saas_is_tenant') && perfex_saas_is_tenant())
            || !function_exists('perfex_saas_table')
            || !$this->db->table_exists(perfex_saas_table('companies'))) {
            return [];
        }

        $this->load->model('perfex_saas/perfex_saas_model');

        $out = [];
        foreach ((array) $this->perfex_saas_model->companies() as $c) {
            if (empty($c->slug) || (isset($c->status) && $c->status !== 'active')) {
                continue;
            }
            $out[] = ['slug' => (string) $c->slug, 'name' => (string) $c->name];
        }

        usort($out, function ($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });

        return $out;
    }

    /**
     * The SaaS tenant behind a client account, when the ticket's customer is a
     * provisioned instance rather than an ordinary customer. Null on a tenant
     * instance, without perfex_saas, or for a plain customer.
     *
     * @return array{id:int,slug:string,name:string,status:string}|null
     */
    public function get_client_tenant($userid)
    {
        $userid = (int) $userid;
        if (!$userid
            || (function_exists('perfex_saas_is_tenant') && perfex_saas_is_tenant())
            || !function_exists('perfex_saas_table')
            || !$this->db->table_exists(perfex_saas_table('companies'))) {
            return null;
        }

        $row = $this->db->select('id, slug, name, status')
            ->where('clientid', $userid)
            ->order_by('id', 'desc')
            ->limit(1)
            ->get(perfex_saas_table('companies'))->row();

        if (!$row) {
            return null;
        }

        return [
            'id'     => (int) $row->id,
            'slug'   => (string) $row->slug,
            'name'   => (string) $row->name,
            'status' => (string) $row->status,
        ];
    }

    /**
     * Open a CI DB handle + resolved prefix for one tenant, mirroring the
     * DSN/prefix resolution used by the other master-side consoles.
     *
     * @return array{db:object,prefix:string}|array{error:string}
     */
    private function open_tenant_db($slug)
    {
        if (!function_exists('perfex_saas_get_company_dsn')
            || !function_exists('perfex_saas_load_ci_db_from_dsn')
            || !function_exists('perfex_saas_tenant_db_prefix')) {
            return ['error' => 'SaaS helpers unavailable'];
        }

        $this->load->model('perfex_saas/perfex_saas_model');
        $company = $this->perfex_saas_model->get_company_by_slug($slug);
        if (empty($company) || empty($company->slug)) {
            return ['error' => 'Tenant not found'];
        }

        try {
            $dsn        = perfex_saas_get_company_dsn($company);
            $clean_slug = str_replace('healtho_ps_', '', $company->slug);
            $prefix     = perfex_saas_tenant_db_prefix($clean_slug);

            $db = perfex_saas_load_ci_db_from_dsn($dsn, ['dbprefix' => $prefix]);
            if ($db && $db->conn_id) {
                return ['db' => $db, 'prefix' => $prefix];
            }

            return ['error' => 'Connection failed'];
        } catch (\Throwable $e) {
            return ['error' => 'Connection exception: ' . $e->getMessage()];
        }
    }

    /**
     * Active staff of one tenant (cross-DB read of the tenant's tblstaff),
     * as the requester candidates for that tenant.
     *
     * @return array<int,array{id:int,name:string,email:string,phone:string}>|array{error:string}
     */
    public function get_tenant_staff($slug)
    {
        $conn = $this->open_tenant_db($slug);
        if (isset($conn['error'])) {
            return ['error' => $conn['error']];
        }

        $db     = $conn['db'];
        $prefix = $conn['prefix'];

        if (!$db->table_exists($prefix . 'staff')) {
            $db->close();

            return ['error' => 'Tenant database has no staff table'];
        }

        $rows = $db->select('staffid, firstname, lastname, email, phonenumber')
            ->where('active', 1)
            ->order_by('firstname', 'asc')
            ->get($prefix . 'staff')->result();
        $db->close();

        $out = [];
        foreach ($rows as $s) {
            $out[] = [
                'id'    => (int) $s->staffid,
                'name'  => trim($s->firstname . ' ' . $s->lastname),
                'email' => (string) $s->email,
                'phone' => (string) $s->phonenumber,
            ];
        }

        return $out;
    }

    /**
     * One active tenant staff member — server-side resolution of the picked
     * requester on POST (the form only submits slug + staff id).
     *
     * @return array{id:int,name:string,email:string,phone:string}|null
     */
    public function get_tenant_staff_member($slug, $staff_id)
    {
        $staff = $this->get_tenant_staff($slug);
        if (isset($staff['error'])) {
            return null;
        }

        foreach ($staff as $s) {
            if ($s['id'] === (int) $staff_id) {
                return $s;
            }
        }

        return null;
    }

    /**
     * How a ticket raised for one of a tenant's staff members is linked to the
     * tenant itself: every SaaS company owns a client record on the master CRM
     * (companies.clientid), and that is the account the ticket belongs to — not
     * the individual who asked for it.
     *
     * Returns the client + the contact to hand to the core ticket model. When
     * the person has no contact record here, `stamp` is true: core cannot be
     * given a contact-less ticket that belongs to a customer (it dereferences
     * the contact for the notification e-mail), so the row is written with the
     * account's primary contact and corrected afterwards — the same dance the
     * client portal does for bridged tenant staff.
     *
     * @return array{userid:int,contactid:int,stamp:bool}|null
     */
    public function get_tenant_client_link($slug, $email)
    {
        if ((function_exists('perfex_saas_is_tenant') && perfex_saas_is_tenant())
            || !function_exists('perfex_saas_table')) {
            return null;
        }

        $this->load->model('perfex_saas/perfex_saas_model');
        $company = $this->perfex_saas_model->get_company_by_slug($slug);

        $clientid = (int) ($company->clientid ?? 0);
        if (!$clientid) {
            return null;
        }

        $email = trim((string) $email);
        if ($email !== '') {
            $contact = $this->db->query(
                'SELECT id FROM `' . db_prefix() . 'contacts` WHERE userid = ? AND LOWER(email) = ? LIMIT 1',
                [$clientid, strtolower($email)]
            )->row();

            if ($contact) {
                return ['userid' => $clientid, 'contactid' => (int) $contact->id, 'stamp' => false];
            }
        }

        $fallback = $this->db->query(
            'SELECT id FROM `' . db_prefix() . 'contacts` WHERE userid = ? ORDER BY is_primary DESC, id ASC LIMIT 1',
            [$clientid]
        )->row();

        return $fallback
            ? ['userid' => $clientid, 'contactid' => (int) $fallback->id, 'stamp' => true]
            : null;
    }

    /* ────────────────────── Todo / Caller integration ───────────────────── */

    /**
     * Pro Tickets' own to-do checklist for a ticket, with creator and
     * assignee names. Pending tasks first, then by due date, newest last.
     */
    public function get_ticket_todos($ticket_id)
    {
        pro_tickets_ensure_schema();
        $p = db_prefix();

        return $this->db->query(
            "SELECT t.id, t.title, t.description, t.priority, t.status, t.due_date, t.datecreated, t.date_completed,
                    CONCAT(s.firstname, ' ', s.lastname) AS creator_name,
                    TRIM(CONCAT(COALESCE(sa.firstname, ''), ' ', COALESCE(sa.lastname, ''))) AS assignee_names
             FROM `{$p}pro_tickets_todos` t
             LEFT JOIN `{$p}staff` s ON s.staffid = t.staff_id
             LEFT JOIN `{$p}staff` sa ON sa.staffid = t.assignee_id
             WHERE t.ticket_id = ?
             ORDER BY t.status ASC, t.due_date IS NULL, t.due_date ASC, t.id DESC",
            [(int) $ticket_id]
        )->result();
    }

    /**
     * Completion progress for a ticket's to-do checklist.
     *
     * @return array ['total' => int, 'done' => int, 'pct' => int]
     */
    public function get_ticket_todo_progress($ticket_id)
    {
        $row = $this->db->query(
            "SELECT COUNT(*) AS total, SUM(status = 2) AS done
             FROM `" . db_prefix() . "pro_tickets_todos` WHERE ticket_id = ?",
            [(int) $ticket_id]
        )->row();

        $total = (int) ($row->total ?? 0);
        $done  = (int) ($row->done ?? 0);

        return [
            'total' => $total,
            'done'  => $done,
            'pct'   => $total > 0 ? (int) round(($done / $total) * 100) : 0,
        ];
    }

    /**
     * Create a to-do task linked to a ticket. Returns the task id.
     */
    public function add_ticket_todo($ticket_id, $title, $due_date, $priority, $assignee_id, $staff_id, $description = '')
    {
        pro_tickets_ensure_schema();
        $this->db->insert(db_prefix() . 'pro_tickets_todos', [
            'ticket_id'   => (int) $ticket_id,
            'title'       => $title,
            'description' => trim((string) $description) !== '' ? $description : null,
            'priority'    => max(1, min(4, (int) $priority)),
            'status'      => 0,
            'due_date'    => $due_date ?: null,
            'assignee_id' => (int) $assignee_id,
            'staff_id'    => (int) $staff_id,
            'datecreated' => date('Y-m-d H:i:s'),
        ]);

        return $this->db->insert_id();
    }

    /**
     * One to-do row scoped to its ticket (used by toggle/delete).
     */
    public function get_ticket_todo($task_id)
    {
        return $this->db->where('id', (int) $task_id)->get(db_prefix() . 'pro_tickets_todos')->row();
    }

    /**
     * Flip a to-do between pending (0) and done (2). Returns the new done
     * state (bool) or null when the task does not exist.
     */
    public function toggle_ticket_todo($task_id)
    {
        $task = $this->get_ticket_todo($task_id);
        if (!$task) {
            return null;
        }

        $completed = (int) $task->status !== 2;
        $this->db->where('id', $task->id)->update(db_prefix() . 'pro_tickets_todos', [
            'status'         => $completed ? 2 : 0,
            'date_completed' => $completed ? date('Y-m-d H:i:s') : null,
        ]);

        return $completed;
    }

    /**
     * Remove a to-do task. Returns the deleted row (for logging) or null.
     */
    public function delete_ticket_todo($task_id)
    {
        $task = $this->get_ticket_todo($task_id);
        if (!$task) {
            return null;
        }

        $this->db->where('id', $task->id)->delete(db_prefix() . 'pro_tickets_todos');

        return $task;
    }

    /* ─────────────────────── To-do checklist templates ──────────────────── */

    /**
     * All checklist templates with their item counts, alphabetical.
     */
    public function get_todo_templates()
    {
        pro_tickets_ensure_schema();
        $p = db_prefix();

        return $this->db->query(
            "SELECT t.*, CONCAT(s.firstname, ' ', s.lastname) AS creator_name,
                    (SELECT COUNT(*) FROM `{$p}pro_tickets_todo_template_items` i WHERE i.template_id = t.id) AS items_count
             FROM `{$p}pro_tickets_todo_templates` t
             LEFT JOIN `{$p}staff` s ON s.staffid = t.staff_id
             ORDER BY t.name ASC"
        )->result();
    }

    /**
     * One template with its ->items (ordered), or null.
     */
    public function get_todo_template($id)
    {
        pro_tickets_ensure_schema();
        $template = $this->db->where('id', (int) $id)->get(db_prefix() . 'pro_tickets_todo_templates')->row();
        if (!$template) {
            return null;
        }

        $template->items = $this->db->where('template_id', $template->id)
            ->order_by('position', 'ASC')->order_by('id', 'ASC')
            ->get(db_prefix() . 'pro_tickets_todo_template_items')->result();

        return $template;
    }

    /**
     * Create or update a template and replace its items.
     *
     * @param  int    $id    0 to create
     * @param  string $name
     * @param  string $description
     * @param  array  $items [['title' => ..., 'description' => ..., 'priority' => 1-4], ...]
     * @return int template id, 0 on invalid input
     */
    public function save_todo_template($id, $name, $description, array $items)
    {
        pro_tickets_ensure_schema();

        $name = mb_substr(trim((string) $name), 0, 191);
        if ($name === '') {
            return 0;
        }

        $clean = [];
        foreach ($items as $item) {
            $title = mb_substr(trim((string) ($item['title'] ?? '')), 0, 500);
            if ($title === '') {
                continue;
            }
            $clean[] = [
                'title'       => $title,
                'description' => trim((string) ($item['description'] ?? '')) !== '' ? trim((string) $item['description']) : null,
                'priority'    => max(1, min(4, (int) ($item['priority'] ?? 2))),
            ];
            if (count($clean) >= 50) {
                break;
            }
        }
        if (empty($clean)) {
            return 0;
        }

        $p   = db_prefix();
        $row = [
            'name'        => $name,
            'description' => mb_substr(trim((string) $description), 0, 500) ?: null,
        ];

        $id = (int) $id;
        if ($id && $this->db->where('id', $id)->count_all_results($p . 'pro_tickets_todo_templates') > 0) {
            $this->db->where('id', $id)->update($p . 'pro_tickets_todo_templates', $row);
        } else {
            $row['staff_id']    = (int) get_staff_user_id();
            $row['datecreated'] = date('Y-m-d H:i:s');
            $this->db->insert($p . 'pro_tickets_todo_templates', $row);
            $id = (int) $this->db->insert_id();
        }

        $this->db->where('template_id', $id)->delete($p . 'pro_tickets_todo_template_items');
        foreach ($clean as $position => $item) {
            $this->db->insert($p . 'pro_tickets_todo_template_items', $item + [
                'template_id' => $id,
                'position'    => $position,
            ]);
        }

        return $id;
    }

    /**
     * Remove a template and its items. Returns the deleted row or null.
     */
    public function delete_todo_template($id)
    {
        $template = $this->db->where('id', (int) $id)->get(db_prefix() . 'pro_tickets_todo_templates')->row();
        if (!$template) {
            return null;
        }

        $this->db->where('template_id', $template->id)->delete(db_prefix() . 'pro_tickets_todo_template_items');
        $this->db->where('id', $template->id)->delete(db_prefix() . 'pro_tickets_todo_templates');

        return $template;
    }

    /**
     * Add every item of a template to a ticket's checklist.
     *
     * @return int number of to-dos created (0 when the template is empty/missing)
     */
    public function apply_todo_template($template_id, $ticket_id, $staff_id, $due_date = null, $assignee_id = 0)
    {
        $template = $this->get_todo_template($template_id);
        if (!$template) {
            return 0;
        }

        $added = 0;
        foreach ($template->items as $item) {
            $this->add_ticket_todo(
                $ticket_id,
                $item->title,
                $due_date,
                (int) $item->priority,
                (int) $assignee_id,
                $staff_id,
                (string) ($item->description ?? '')
            );
            $added++;
        }

        return $added;
    }

    /**
     * Smart Forms assignments linked to a ticket's requester — either the
     * contact (target_type = contact) or the company/patient record
     * (target_type = patient). Newest first. Empty when the module's tables
     * are absent.
     */
    public function get_ticket_smart_forms($contactid, $userid)
    {
        $p = db_prefix();
        if (!$this->db->table_exists($p . 'smart_forms_assignments') || !$this->db->table_exists($p . 'smart_forms')) {
            return [];
        }

        $contactid = (int) $contactid;
        $userid    = (int) $userid;
        if ($contactid === 0 && $userid === 0) {
            return [];
        }

        $where  = [];
        $params = [];
        if ($contactid !== 0) {
            $where[]  = "(a.target_type = 'contact' AND a.target_id = ?)";
            $params[] = $contactid;
        }
        if ($userid !== 0) {
            $where[]  = "(a.target_type = 'patient' AND a.target_id = ?)";
            $params[] = $userid;
        }

        return $this->db->query(
            "SELECT a.id, a.form_id, a.status, a.draft_progress, a.due_date, a.completed_at,
                    a.submission_id, a.target_type, f.title AS form_title, f.status AS form_status
             FROM `{$p}smart_forms_assignments` a
             JOIN `{$p}smart_forms` f ON f.id = a.form_id
             WHERE " . implode(' OR ', $where) . "
             ORDER BY a.created_at DESC, a.id DESC
             LIMIT 20",
            $params
        )->result();
    }

    /**
     * Caller-module call logs whose number matches any of the given phones
     * (last-10-digit match), newest first.
     */
    public function get_calls_for_phones(array $phones, $limit = 8)
    {
        $p = db_prefix();
        if (!$this->db->table_exists($p . 'caller_call_logs')) {
            return [];
        }

        $last10s = array_values(array_unique(array_filter(array_map('pro_tickets_phone_last10', $phones))));
        if (empty($last10s)) {
            return [];
        }

        $expr         = "RIGHT(REPLACE(REPLACE(REPLACE(l.phone_number, '+', ''), ' ', ''), '-', ''), 10)";
        $placeholders = implode(',', array_fill(0, count($last10s), '?'));

        return $this->db->query(
            "SELECT l.phone_number, l.contact_name, l.call_type, l.duration_seconds,
                    l.call_date, l.has_recording, l.recording_id,
                    CONCAT(s.firstname, ' ', s.lastname) AS staff_name
             FROM `{$p}caller_call_logs` l
             LEFT JOIN `{$p}staff` s ON s.staffid = l.staff_id
             WHERE l.phone_number IS NOT NULL AND {$expr} IN ({$placeholders})
             ORDER BY l.call_date DESC
             LIMIT " . (int) $limit,
            $last10s
        )->result();
    }

    /**
     * Mailbox-module messages exchanged with any of the given email
     * addresses, newest first. Only searches the given accounts (the ones
     * visible to the current staff member) and skips drafts/trash.
     *
     * to/cc are stored as JSON [{"name":..,"email":..}] so the recipient
     * match is a quoted-value LIKE.
     */
    public function get_emails_for_addresses(array $account_ids, array $emails, $limit = 6)
    {
        $p = db_prefix();
        if (!$this->db->table_exists($p . 'mailbox_messages')) {
            return [];
        }

        $account_ids = array_values(array_unique(array_filter(array_map('intval', $account_ids))));
        $emails      = array_values(array_unique(array_filter(array_map(function ($email) {
            return mb_strtolower(trim((string) $email));
        }, $emails), function ($email) {
            return filter_var($email, FILTER_VALIDATE_EMAIL);
        })));
        if (empty($account_ids) || empty($emails)) {
            return [];
        }

        $accounts_in = implode(',', $account_ids);
        $match       = [];
        $binds       = [];
        foreach ($emails as $email) {
            $like    = '%"' . $this->db->escape_like_str($email) . '"%';
            $match[] = "(LOWER(m.from_email) = ? OR LOWER(m.to_emails) LIKE ? ESCAPE '!' OR LOWER(m.cc_emails) LIKE ? ESCAPE '!')";
            array_push($binds, $email, $like, $like);
        }

        return $this->db->query(
            "SELECT m.id, m.account_id, m.folder, m.subject, m.from_name, m.from_email,
                    m.snippet, m.has_attachments, m.message_date, a.name AS account_name, a.email AS account_email
             FROM `{$p}mailbox_messages` m
             JOIN `{$p}mailbox_accounts` a ON a.id = m.account_id
             WHERE m.account_id IN ({$accounts_in})
               AND m.folder NOT IN ('drafts', 'trash')
               AND (" . implode(' OR ', $match) . ")
             ORDER BY m.message_date DESC, m.id DESC
             LIMIT " . (int) $limit,
            $binds
        )->result();
    }

    /**
     * Smart PDF module — active templates for the ticket "Documents" card.
     */
    public function get_pdf_templates()
    {
        $p = db_prefix();
        if (!$this->db->table_exists($p . 'smart_pdf_templates')) {
            return [];
        }

        return $this->db->select('id, name')
            ->where('active', 1)
            ->order_by('name', 'ASC')
            ->get($p . 'smart_pdf_templates')->result();
    }

    /* ─────────────────────────── Ticket lists ───────────────────────────── */

    /**
     * Filterable, paginated ticket list joined with SLA meta.
     *
     * $filters: status, department, priority, assigned (-1 = unassigned),
     *           sla (breached|at_risk|ok), search, period (days)
     *
     * @return array ['rows' => [], 'total' => int]
     */
    public function get_tickets($filters = [], $page = 1, $per_page = 25)
    {
        $p = db_prefix();

        $where  = ["t.merged_ticket_id IS NULL"];
        $params = [];

        if (($filters['status'] ?? '') === 'not_closed') {
            $where[] = 't.status != 5';
        } elseif (!empty($filters['status'])) {
            $where[]  = 't.status = ?';
            $params[] = (int) $filters['status'];
        }
        if (!empty($filters['department'])) {
            $where[]  = 't.department = ?';
            $params[] = (int) $filters['department'];
        }
        if (!empty($filters['priority'])) {
            $where[]  = 't.priority = ?';
            $params[] = (int) $filters['priority'];
        }
        if (isset($filters['assigned']) && $filters['assigned'] !== '' && $filters['assigned'] !== null) {
            if ((int) $filters['assigned'] === -1) {
                $where[] = 't.assigned = 0';
            } else {
                $where[]  = 't.assigned = ?';
                $params[] = (int) $filters['assigned'];
            }
        }
        if (!empty($filters['sla'])) {
            if ($filters['sla'] === 'breached') {
                $where[] = '(m.frt_breached = 1 OR m.res_breached = 1)';
            } elseif ($filters['sla'] === 'at_risk') {
                $where[] = 'm.sla_warned = 1 AND m.frt_breached = 0 AND m.res_breached = 0 AND t.status != 5';
            } elseif ($filters['sla'] === 'ok') {
                $where[] = 'COALESCE(m.sla_warned, 0) = 0 AND COALESCE(m.frt_breached, 0) = 0 AND COALESCE(m.res_breached, 0) = 0';
            }
        }
        if (!empty($filters['period'])) {
            $where[]  = 't.date >= DATE_SUB(NOW(), INTERVAL ' . (int) $filters['period'] . ' DAY)';
        }
        if (!empty($filters['feedback'])) {
            if ($filters['feedback'] === 'positive') {
                $where[] = 'fb.rating >= 4';
            } elseif ($filters['feedback'] === 'neutral') {
                $where[] = 'fb.rating = 3';
            } elseif ($filters['feedback'] === 'negative') {
                $where[] = 'fb.rating <= 2 AND fb.rating > 0';
            } elseif ($filters['feedback'] === 'rated') {
                $where[] = 'fb.id IS NOT NULL';
            } elseif ($filters['feedback'] === 'unrated') {
                // Only closed tickets can be rated, so "awaiting" means closed + no row.
                $where[] = 'fb.id IS NULL AND t.status = 5';
            }
        }
        if (!empty($filters['search'])) {
            $s        = '%' . $this->db->escape_like_str($filters['search']) . '%';
            $where[]  = "(t.subject LIKE ? ESCAPE '!' OR t.ticketid LIKE ? ESCAPE '!' OR t.name LIKE ? ESCAPE '!' OR t.email LIKE ? ESCAPE '!'
                          OR CONCAT(c.firstname, ' ', c.lastname) LIKE ? ESCAPE '!')";
            array_push($params, $s, $s, $s, $s, $s);
        }

        $whereSql = implode(' AND ', $where);

        $joins = "FROM `{$p}tickets` t
            LEFT JOIN `{$p}pro_tickets_meta` m ON m.ticket_id = t.ticketid
            LEFT JOIN `{$p}tickets_status` st ON st.ticketstatusid = t.status
            LEFT JOIN `{$p}tickets_priorities` pr ON pr.priorityid = t.priority
            LEFT JOIN `{$p}departments` d ON d.departmentid = t.department
            LEFT JOIN `{$p}staff` s ON s.staffid = t.assigned
            LEFT JOIN `{$p}contacts` c ON c.id = t.contactid
            LEFT JOIN `{$p}clients` cl ON cl.userid = t.userid
            LEFT JOIN `{$p}pro_tickets_feedback` fb ON fb.ticket_id = t.ticketid";

        $total = (int) $this->db->query("SELECT COUNT(*) AS cnt $joins WHERE $whereSql", $params)->row()->cnt;

        $offset = max(0, ((int) $page - 1) * (int) $per_page);
        $rows   = $this->db->query(
            "SELECT t.ticketid, t.subject, t.status, t.priority, t.department, t.assigned,
                    t.date, t.lastreply, t.adminread, t.userid, t.contactid,
                    t.name AS from_name, t.email AS ticket_email,
                    st.name AS status_name, st.statuscolor,
                    pr.name AS priority_name,
                    d.name AS department_name,
                    s.firstname AS staff_firstname, s.lastname AS staff_lastname,
                    c.firstname AS contact_firstname, c.lastname AS contact_lastname,
                    cl.company,
                    m.frt_due, m.res_due, m.first_replied_at, m.resolved_at,
                    m.frt_breached, m.res_breached, m.sla_warned, m.created_at AS meta_created_at,
                    fb.rating AS feedback_rating, fb.comment AS feedback_comment,
                    fb.created_at AS feedback_at
             $joins WHERE $whereSql
             ORDER BY COALESCE(t.lastreply, t.date) DESC
             LIMIT " . (int) $per_page . ' OFFSET ' . $offset,
            $params
        )->result();

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * Tickets grouped by status for the Kanban board.
     */
    public function get_kanban($limit_per_column = 40)
    {
        $columns = [];
        foreach ($this->get_statuses() as $status) {
            $result = $this->get_tickets(['status' => $status->ticketstatusid], 1, $limit_per_column);

            $columns[] = [
                'status' => $status,
                'total'  => $result['total'],
                'rows'   => $result['rows'],
            ];
        }

        return $columns;
    }

    /* ─────────────────────────── Single ticket ──────────────────────────── */

    /**
     * Ticket + meta + everything the detail page needs.
     */
    public function get_ticket($id)
    {
        $this->load->model('tickets_model');

        $ticket = $this->tickets_model->get_ticket_by_id($id);
        if (!$ticket) {
            return null;
        }

        $ticket->meta     = pro_tickets_ensure_meta($ticket->ticketid);
        $ticket->replies  = $this->tickets_model->get_ticket_replies($ticket->ticketid);
        $ticket->watchers = $this->get_watchers($ticket->ticketid);
        $ticket->notes    = $this->get_notes($ticket->ticketid);
        $ticket->activity = $this->get_activity($ticket->ticketid);
        $ticket->tags     = get_tags_in($ticket->ticketid, 'ticket');

        return $ticket;
    }

    /* ───────────────────────── Watchers / notes ─────────────────────────── */

    public function get_watchers($ticket_id)
    {
        $p = db_prefix();

        return $this->db->query(
            "SELECT w.staff_id, s.firstname, s.lastname
             FROM `{$p}pro_tickets_watchers` w
             JOIN `{$p}staff` s ON s.staffid = w.staff_id
             WHERE w.ticket_id = ? ORDER BY s.firstname",
            [(int) $ticket_id]
        )->result();
    }

    /**
     * @return bool true when now watching, false when unwatched
     */
    public function toggle_watcher($ticket_id, $staff_id)
    {
        $p        = db_prefix();
        $existing = $this->db->where(['ticket_id' => (int) $ticket_id, 'staff_id' => (int) $staff_id])
            ->get($p . 'pro_tickets_watchers')->row();

        if ($existing) {
            $this->db->where('id', $existing->id)->delete($p . 'pro_tickets_watchers');

            return false;
        }

        $this->db->insert($p . 'pro_tickets_watchers', [
            'ticket_id'  => (int) $ticket_id,
            'staff_id'   => (int) $staff_id,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return true;
    }

    public function get_notes($ticket_id)
    {
        $p = db_prefix();

        return $this->db->query(
            "SELECT n.*, s.firstname, s.lastname
             FROM `{$p}pro_tickets_notes` n
             LEFT JOIN `{$p}staff` s ON s.staffid = n.staff_id
             WHERE n.ticket_id = ? ORDER BY n.created_at DESC",
            [(int) $ticket_id]
        )->result();
    }

    public function add_note($ticket_id, $staff_id, $note)
    {
        $this->db->insert(db_prefix() . 'pro_tickets_notes', [
            'ticket_id'  => (int) $ticket_id,
            'staff_id'   => (int) $staff_id,
            'note'       => $note,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        pro_tickets_log($ticket_id, 'note_added', '', (int) $staff_id);

        return $this->db->insert_id();
    }

    public function get_activity($ticket_id, $limit = 100)
    {
        $p = db_prefix();

        return $this->db->query(
            "SELECT a.*, s.firstname, s.lastname
             FROM `{$p}pro_tickets_activity` a
             LEFT JOIN `{$p}staff` s ON s.staffid = a.staff_id
             WHERE a.ticket_id = ? ORDER BY a.created_at DESC, a.id DESC LIMIT " . (int) $limit,
            [(int) $ticket_id]
        )->result();
    }

    /* ─────────────────────────── SLA policies ───────────────────────────── */

    public function get_slas($active_only = false)
    {
        if ($active_only) {
            $this->db->where('active', 1);
        }

        return $this->db->order_by('sort_order', 'asc')->order_by('id', 'asc')
            ->get(db_prefix() . 'pro_tickets_sla')->result();
    }

    public function save_sla($data, $id = null)
    {
        $row = [
            'name'          => $data['name'],
            'department_id' => (int) ($data['department_id'] ?? 0),
            'priority_id'   => (int) ($data['priority_id'] ?? 0),
            'frt_minutes'   => max(1, (int) ($data['frt_minutes'] ?? 240)),
            'res_minutes'   => max(1, (int) ($data['res_minutes'] ?? 1440)),
            'escalate_to'   => (int) ($data['escalate_to'] ?? 0),
            'active'        => !empty($data['active']) ? 1 : 0,
            'sort_order'    => (int) ($data['sort_order'] ?? 0),
        ];

        if ($id) {
            $this->db->where('id', (int) $id)->update(db_prefix() . 'pro_tickets_sla', $row);

            return (int) $id;
        }

        $row['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert(db_prefix() . 'pro_tickets_sla', $row);

        return $this->db->insert_id();
    }

    public function delete_sla($id)
    {
        $this->db->where('id', (int) $id)->delete(db_prefix() . 'pro_tickets_sla');
        $this->db->where('sla_id', (int) $id)->update(db_prefix() . 'pro_tickets_meta', ['sla_id' => null]);

        return $this->db->affected_rows() >= 0;
    }

    /* ──────────────────────── Dashboard analytics ───────────────────────── */

    public function get_dashboard()
    {
        $p = db_prefix();

        $kpi = $this->db->query(
            "SELECT
                SUM(t.status != 5) AS open_total,
                SUM(t.status != 5 AND t.assigned = 0) AS unassigned,
                SUM(t.status != 5 AND (m.frt_breached = 1 OR m.res_breached = 1)) AS overdue,
                SUM(t.status != 5 AND m.sla_warned = 1 AND COALESCE(m.frt_breached,0) = 0 AND COALESCE(m.res_breached,0) = 0) AS at_risk,
                SUM(t.status = 5 AND m.resolved_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) AS solved_7d,
                SUM(t.date >= DATE_SUB(NOW(), INTERVAL 7 DAY)) AS new_7d
             FROM `{$p}tickets` t
             LEFT JOIN `{$p}pro_tickets_meta` m ON m.ticket_id = t.ticketid
             WHERE t.merged_ticket_id IS NULL"
        )->row();

        // Response/resolution averages + SLA compliance over the last 30 days.
        $perf = $this->db->query(
            "SELECT
                AVG(CASE WHEN m.first_replied_at IS NOT NULL
                    THEN TIMESTAMPDIFF(MINUTE, t.date, m.first_replied_at) END) AS avg_frt_mins,
                AVG(CASE WHEN m.resolved_at IS NOT NULL
                    THEN TIMESTAMPDIFF(MINUTE, t.date, m.resolved_at) END) AS avg_res_mins,
                SUM(m.resolved_at IS NOT NULL) AS resolved_cnt,
                SUM(m.resolved_at IS NOT NULL AND m.frt_breached = 0 AND m.res_breached = 0) AS resolved_in_sla
             FROM `{$p}pro_tickets_meta` m
             JOIN `{$p}tickets` t ON t.ticketid = m.ticket_id
             WHERE t.date >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        )->row();

        // Customer satisfaction over the last 30 days (one rating per ticket).
        $csat = $this->db->query(
            "SELECT COUNT(*) AS responses,
                AVG(f.rating) AS avg_rating,
                SUM(f.rating >= 4) AS positive,
                SUM(f.rating = 3) AS neutral,
                SUM(f.rating <= 2) AS negative,
                SUM(f.rating = 1) AS r1, SUM(f.rating = 2) AS r2, SUM(f.rating = 3) AS r3,
                SUM(f.rating = 4) AS r4, SUM(f.rating = 5) AS r5
             FROM `{$p}pro_tickets_feedback` f
             WHERE f.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        )->row();

        // Response rate: of the tickets staff closed in the window, how many
        // came back with a rating.
        $csatResp = $this->db->query(
            "SELECT COUNT(*) AS closed_cnt, SUM(f.id IS NOT NULL) AS rated_cnt
             FROM `{$p}pro_tickets_meta` m
             JOIN `{$p}tickets` t ON t.ticketid = m.ticket_id
             LEFT JOIN `{$p}pro_tickets_feedback` f ON f.ticket_id = m.ticket_id
             WHERE m.closed_by_staff > 0
               AND m.resolved_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
               AND t.merged_ticket_id IS NULL"
        )->row();

        // Latest ratings, newest first.
        $csatRecent = $this->db->query(
            "SELECT f.ticket_id, f.rating, f.comment, f.created_at, t.subject,
                    CONCAT(s.firstname, ' ', s.lastname) AS agent_name
             FROM `{$p}pro_tickets_feedback` f
             JOIN `{$p}tickets` t ON t.ticketid = f.ticket_id
             LEFT JOIN `{$p}staff` s ON s.staffid = f.agent_id
             ORDER BY f.id DESC LIMIT 6"
        )->result();

        // 14-day opened vs solved trend.
        $days   = [];
        $opened = [];
        $solved = [];
        for ($i = 13; $i >= 0; $i--) {
            $days[date('Y-m-d', strtotime("-$i days"))] = ['opened' => 0, 'solved' => 0];
        }
        $trendOpen = $this->db->query(
            "SELECT DATE(date) AS d, COUNT(*) AS cnt FROM `{$p}tickets`
             WHERE date >= DATE_SUB(CURDATE(), INTERVAL 13 DAY) AND merged_ticket_id IS NULL
             GROUP BY DATE(date)"
        )->result();
        foreach ($trendOpen as $r) {
            if (isset($days[$r->d])) {
                $days[$r->d]['opened'] = (int) $r->cnt;
            }
        }
        $trendSolved = $this->db->query(
            "SELECT DATE(resolved_at) AS d, COUNT(*) AS cnt FROM `{$p}pro_tickets_meta`
             WHERE resolved_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
             GROUP BY DATE(resolved_at)"
        )->result();
        foreach ($trendSolved as $r) {
            if (isset($days[$r->d])) {
                $days[$r->d]['solved'] = (int) $r->cnt;
            }
        }
        foreach ($days as $d => $v) {
            $opened[] = ['label' => date('d M', strtotime($d)), 'value' => $v['opened']];
            $solved[] = $v['solved'];
        }

        // Distribution: by status, priority, department (open tickets only for dept/priority).
        $byStatus = $this->db->query(
            "SELECT st.name, st.statuscolor, COUNT(*) AS cnt
             FROM `{$p}tickets` t JOIN `{$p}tickets_status` st ON st.ticketstatusid = t.status
             WHERE t.merged_ticket_id IS NULL GROUP BY t.status ORDER BY st.statusorder"
        )->result();

        $byPriority = $this->db->query(
            "SELECT COALESCE(pr.name, '—') AS name, COUNT(*) AS cnt
             FROM `{$p}tickets` t LEFT JOIN `{$p}tickets_priorities` pr ON pr.priorityid = t.priority
             WHERE t.status != 5 AND t.merged_ticket_id IS NULL GROUP BY t.priority ORDER BY t.priority"
        )->result();

        $byDepartment = $this->db->query(
            "SELECT COALESCE(d.name, '—') AS name, COUNT(*) AS cnt
             FROM `{$p}tickets` t LEFT JOIN `{$p}departments` d ON d.departmentid = t.department
             WHERE t.status != 5 AND t.merged_ticket_id IS NULL
             GROUP BY t.department ORDER BY cnt DESC LIMIT 8"
        )->result();

        // Agent leaderboard (open now + solved in the last 30 days).
        $agents = $this->db->query(
            "SELECT s.staffid, s.firstname, s.lastname,
                SUM(t.status != 5) AS open_cnt,
                SUM(t.status = 5 AND m.resolved_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS solved_30d,
                AVG(CASE WHEN m.first_replied_at IS NOT NULL AND t.date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    THEN TIMESTAMPDIFF(MINUTE, t.date, m.first_replied_at) END) AS avg_frt,
                AVG(fb.rating) AS csat_avg,
                COUNT(fb.id) AS csat_cnt
             FROM `{$p}tickets` t
             JOIN `{$p}staff` s ON s.staffid = t.assigned
             LEFT JOIN `{$p}pro_tickets_meta` m ON m.ticket_id = t.ticketid
             LEFT JOIN `{$p}pro_tickets_feedback` fb ON fb.ticket_id = t.ticketid
             WHERE t.assigned != 0 AND t.merged_ticket_id IS NULL
             GROUP BY t.assigned ORDER BY open_cnt DESC LIMIT 6"
        )->result();

        // Recent automation/activity feed.
        $feed = $this->db->query(
            "SELECT a.*, t.subject, s.firstname, s.lastname
             FROM `{$p}pro_tickets_activity` a
             JOIN `{$p}tickets` t ON t.ticketid = a.ticket_id
             LEFT JOIN `{$p}staff` s ON s.staffid = a.staff_id
             ORDER BY a.id DESC LIMIT 12"
        )->result();

        $resolvedCnt = (int) ($perf->resolved_cnt ?? 0);

        $csatCnt    = (int) ($csat->responses ?? 0);
        $csatClosed = (int) ($csatResp->closed_cnt ?? 0);

        return [
            'kpi' => [
                'open'        => (int) ($kpi->open_total ?? 0),
                'unassigned'  => (int) ($kpi->unassigned ?? 0),
                'overdue'     => (int) ($kpi->overdue ?? 0),
                'at_risk'     => (int) ($kpi->at_risk ?? 0),
                'solved_7d'   => (int) ($kpi->solved_7d ?? 0),
                'new_7d'      => (int) ($kpi->new_7d ?? 0),
                'avg_frt'     => $perf->avg_frt_mins !== null ? pro_tickets_human_duration($perf->avg_frt_mins) : '—',
                'avg_res'     => $perf->avg_res_mins !== null ? pro_tickets_human_duration($perf->avg_res_mins) : '—',
                'sla_pct'     => $resolvedCnt ? round(((int) $perf->resolved_in_sla / $resolvedCnt) * 100) : null,
                // CSAT mirrors of the 'csat' block below, for the dashboard widget.
                'csat_avg'       => $csatCnt ? round((float) $csat->avg_rating, 1) : null,
                'csat_pct'       => $csatCnt ? round(((int) $csat->positive / $csatCnt) * 100) : null,
                'csat_responses' => $csatCnt,
                'csat_resp_rate' => $csatClosed ? round(((int) $csatResp->rated_cnt / $csatClosed) * 100) : null,
            ],
            'trend'         => ['labels' => array_column($opened, 'label'), 'opened' => array_column($opened, 'value'), 'solved' => $solved],
            'by_status'     => $byStatus,
            'by_priority'   => $byPriority,
            'by_department' => $byDepartment,
            'csat' => [
                'responses'  => $csatCnt,
                'avg'        => $csatCnt ? round((float) $csat->avg_rating, 1) : null,
                'pct'        => $csatCnt ? round(((int) $csat->positive / $csatCnt) * 100) : null,
                'positive'   => (int) ($csat->positive ?? 0),
                'neutral'    => (int) ($csat->neutral ?? 0),
                'negative'   => (int) ($csat->negative ?? 0),
                'resp_rate'  => $csatClosed ? round(((int) $csatResp->rated_cnt / $csatClosed) * 100) : null,
                'closed_cnt' => $csatClosed,
                'dist'       => [
                    1 => (int) ($csat->r1 ?? 0),
                    2 => (int) ($csat->r2 ?? 0),
                    3 => (int) ($csat->r3 ?? 0),
                    4 => (int) ($csat->r4 ?? 0),
                    5 => (int) ($csat->r5 ?? 0),
                ],
                'recent'     => $csatRecent,
            ],
            'agents'        => $agents,
            'feed'          => $feed,
        ];
    }
}
