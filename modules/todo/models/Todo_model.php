<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Todo_model extends App_Model
{
    public function __construct()
    {
        parent::__construct();

        // Self-healing: Seed default categories if table is empty
        if ($this->db->table_exists(db_prefix() . 'todo_categories')) {
            $count = $this->db->count_all_results(db_prefix() . 'todo_categories');
            if ($count == 0) {
                $defaults = [
                    ['name' => 'General',    'color' => '#6366f1', 'icon' => 'fa-folder',       'sort_order' => 1],
                    ['name' => 'Work',       'color' => '#3b82f6', 'icon' => 'fa-briefcase',    'sort_order' => 2],
                    ['name' => 'Personal',   'color' => '#8b5cf6', 'icon' => 'fa-user',         'sort_order' => 3],
                    ['name' => 'Meetings',   'color' => '#f59e0b', 'icon' => 'fa-users',        'sort_order' => 4],
                    ['name' => 'Follow Up',  'color' => '#10b981', 'icon' => 'fa-phone',        'sort_order' => 5],
                    ['name' => 'Urgent',     'color' => '#ef4444', 'icon' => 'fa-bolt',         'sort_order' => 6],
                    ['name' => 'Ideas',      'color' => '#f97316', 'icon' => 'fa-lightbulb',    'sort_order' => 7],
                    ['name' => 'Admin',      'color' => '#64748b', 'icon' => 'fa-cog',          'sort_order' => 8],
                ];
                $staff_id = get_staff_user_id() ?: 1;
                foreach ($defaults as $cat) {
                    $cat['staff_id']    = $staff_id;
                    $cat['datecreated'] = date('Y-m-d H:i:s');
                    $this->db->insert(db_prefix() . 'todo_categories', $cat);
                }
            }
        }

        // Self-healing: Create todo_remarks table if missing
        if (!$this->db->table_exists(db_prefix() . 'todo_remarks')) {
            $this->db->query('CREATE TABLE `' . db_prefix() . 'todo_remarks` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `task_id` int(11) NOT NULL,
              `staff_id` int(11) NOT NULL,
              `remark` text NOT NULL,
              `datecreated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `task_id` (`task_id`),
              KEY `staff_id` (`staff_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=' . $this->db->char_set . ';');
        }

        // Self-healing: Create assignees table if missing
        if (!$this->db->table_exists(db_prefix() . 'todo_task_assignees')) {
            $this->db->query('CREATE TABLE `' . db_prefix() . 'todo_task_assignees` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `task_id` int(11) NOT NULL,
              `staff_id` int(11) NOT NULL,
              `datecreated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `task_staff` (`task_id`, `staff_id`),
              KEY `task_id` (`task_id`),
              KEY `staff_id` (`staff_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=' . $this->db->char_set . ';');
        }

        // Self-healing: Create task roles table if missing
        if (!$this->db->table_exists(db_prefix() . 'todo_task_roles')) {
            $this->db->query('CREATE TABLE `' . db_prefix() . 'todo_task_roles` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `task_id` int(11) NOT NULL,
              `role_id` int(11) NOT NULL,
              `datecreated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `task_role` (`task_id`, `role_id`),
              KEY `task_id` (`task_id`),
              KEY `role_id` (`role_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=' . $this->db->char_set . ';');
        }

        // Self-healing: Create templates table if missing
        if (!$this->db->table_exists(db_prefix() . 'todo_templates')) {
            $this->db->query('CREATE TABLE `' . db_prefix() . 'todo_templates` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `name` varchar(500) NOT NULL,
              `description` text DEFAULT NULL,
              `category_id` int(11) DEFAULT NULL,
              `priority` tinyint(1) DEFAULT 1,
              `due_days` int(11) DEFAULT NULL,
              `checklist_json` text DEFAULT NULL,
              `assignee_ids_json` text DEFAULT NULL,
              `role_ids_json` text DEFAULT NULL,
              `is_recurring` tinyint(1) DEFAULT 0,
              `recurring_type` varchar(20) DEFAULT NULL,
              `repeat_every` int(11) DEFAULT 1,
              `last_recurring_date` date DEFAULT NULL,
              `next_run_date` date DEFAULT NULL,
              `is_active` tinyint(1) DEFAULT 1,
              `created_by` int(11) NOT NULL,
              `datecreated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `is_recurring` (`is_recurring`),
              KEY `is_active` (`is_active`),
              KEY `next_run_date` (`next_run_date`)
            ) ENGINE=InnoDB DEFAULT CHARSET=' . $this->db->char_set . ';');
        }

        // Self-healing: Create score_log table if missing (BNI incentive scoring)
        if (!$this->db->table_exists(db_prefix() . 'todo_score_log')) {
            $this->db->query('CREATE TABLE `' . db_prefix() . 'todo_score_log` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `staff_id` int(11) NOT NULL,
              `task_id` int(11) DEFAULT NULL,
              `score_type` varchar(50) NOT NULL,
              `points` decimal(6,1) NOT NULL DEFAULT 0,
              `reason` varchar(500) DEFAULT NULL,
              `score_date` date NOT NULL,
              `datecreated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `staff_id` (`staff_id`),
              KEY `task_id` (`task_id`),
              KEY `score_date` (`score_date`),
              KEY `score_type` (`score_type`)
            ) ENGINE=InnoDB DEFAULT CHARSET=' . $this->db->char_set . ';');
        }

        // Self-healing: Create attendance table if missing
        if (!$this->db->table_exists(db_prefix() . 'todo_attendance')) {
            $this->db->query('CREATE TABLE `' . db_prefix() . 'todo_attendance` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `staff_id` int(11) NOT NULL,
              `attend_date` date NOT NULL,
              `status` tinyint(1) DEFAULT 1,
              `points` decimal(6,1) DEFAULT 5,
              `marked_by` int(11) NOT NULL,
              `datecreated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `staff_date` (`staff_id`, `attend_date`),
              KEY `attend_date` (`attend_date`)
            ) ENGINE=InnoDB DEFAULT CHARSET=' . $this->db->char_set . ';');
        }

        // Self-healing: Add date_completed column to tasks if missing
        if ($this->db->table_exists(db_prefix() . 'todo_tasks')) {
            if (!$this->db->field_exists('date_completed', db_prefix() . 'todo_tasks')) {
                $this->db->query('ALTER TABLE `' . db_prefix() . 'todo_tasks` ADD `date_completed` datetime DEFAULT NULL AFTER `dateupdated`');
            }
        }
    }

    // ═══════════════════════════════════════════
    //  CATEGORIES
    // ═══════════════════════════════════════════

    /**
     * Get all categories (optionally filtered by staff)
     */
    public function get_categories($staff_id = null)
    {
        // Show all categories to all staff — categories are shared resources
        $this->db->order_by('sort_order', 'asc');
        return $this->db->get(db_prefix() . 'todo_categories')->result_array();
    }

    /**
     * Get single category
     */
    public function get_category($id)
    {
        $this->db->where('id', $id);
        return $this->db->get(db_prefix() . 'todo_categories')->row_array();
    }

    /**
     * Add category
     */
    public function add_category($data)
    {
        $data['datecreated'] = date('Y-m-d H:i:s');
        $this->db->insert(db_prefix() . 'todo_categories', $data);
        $id = $this->db->insert_id();
        if ($id) {
            log_activity('Todo Category Created [ID: ' . $id . ', Name: ' . $data['name'] . ']');
        }
        return $id;
    }

    /**
     * Update category
     */
    public function update_category($id, $data)
    {
        $this->db->where('id', $id);
        $this->db->update(db_prefix() . 'todo_categories', $data);
        if ($this->db->affected_rows() > 0) {
            log_activity('Todo Category Updated [ID: ' . $id . ']');
            return true;
        }
        return false;
    }

    /**
     * Delete category
     */
    public function delete_category($id)
    {
        // Unlink tasks from this category first
        $this->db->where('category_id', $id);
        $this->db->update(db_prefix() . 'todo_tasks', ['category_id' => null]);

        $this->db->where('id', $id);
        $this->db->delete(db_prefix() . 'todo_categories');
        if ($this->db->affected_rows() > 0) {
            log_activity('Todo Category Deleted [ID: ' . $id . ']');
            return true;
        }
        return false;
    }

    // ═══════════════════════════════════════════
    //  TASKS
    // ═══════════════════════════════════════════

    /**
     * Get tasks with optional filters
     */
    public function get_tasks($filters = [])
    {
        // IMPORTANT: Fetch the staff role BEFORE starting the query builder chain.
        // Running a separate ->get() mid-chain corrupts CI3's shared query builder state,
        // destroying the pending SELECT/FROM/JOIN clauses and causing a crash for non-admin users.
        $user_role = 0;
        if (!empty($filters['staff_id']) && !is_admin()) {
            $sid = (int)$filters['staff_id'];
            $staff_row = $this->db->select('role')->where('staffid', $sid)->get(db_prefix() . 'staff')->row();
            $user_role = $staff_row ? (int)$staff_row->role : 0;
        }

        $this->db->select(
            db_prefix() . 'todo_tasks.*, ' .
            db_prefix() . 'todo_categories.name as category_name, ' .
            db_prefix() . 'todo_categories.color as category_color, ' .
            db_prefix() . 'todo_categories.icon as category_icon, ' .
            'CONCAT(' . db_prefix() . 'staff.firstname, " ", ' . db_prefix() . 'staff.lastname) as staff_name'
        );
        $this->db->from(db_prefix() . 'todo_tasks');
        $this->db->join(db_prefix() . 'todo_categories', db_prefix() . 'todo_categories.id = ' . db_prefix() . 'todo_tasks.category_id', 'left');
        $this->db->join(db_prefix() . 'staff', db_prefix() . 'staff.staffid = ' . db_prefix() . 'todo_tasks.staff_id', 'left');

        // Visibility: show tasks where user is creator, assignee, or has the assigned role
        if (!empty($filters['staff_id']) && !is_admin()) {
            $prefix = db_prefix();
            $this->db->where(
                "({$prefix}todo_tasks.staff_id = {$sid}
                  OR {$prefix}todo_tasks.id IN (SELECT task_id FROM {$prefix}todo_task_assignees WHERE staff_id = {$sid})
                  " . ($user_role ? "OR {$prefix}todo_tasks.id IN (SELECT task_id FROM {$prefix}todo_task_roles WHERE role_id = {$user_role})" : '') . "
                )", null, false
            );
        }

        if (isset($filters['status']) && $filters['status'] !== '' && $filters['status'] !== 'all') {
            if ($filters['status'] === 'my_tasks') {
                $sid = (int)$filters['staff_id'];
                $prefix = db_prefix();
                $this->db->where("({$prefix}todo_tasks.staff_id = {$sid} OR {$prefix}todo_tasks.id IN (SELECT task_id FROM {$prefix}todo_task_assignees WHERE staff_id = {$sid}))", null, false);
            } else {
                $this->db->where(db_prefix() . 'todo_tasks.status', $filters['status']);
            }
        }
        if (!empty($filters['category_id'])) {
            $this->db->where(db_prefix() . 'todo_tasks.category_id', $filters['category_id']);
        }
        if (!empty($filters['priority'])) {
            $this->db->where(db_prefix() . 'todo_tasks.priority', $filters['priority']);
        }
        if (!empty($filters['date_from'])) {
            $this->db->where(db_prefix() . 'todo_tasks.due_date >=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $this->db->where(db_prefix() . 'todo_tasks.due_date <=', $filters['date_to']);
        }
        if (!empty($filters['search'])) {
            $this->db->group_start();
            $this->db->like(db_prefix() . 'todo_tasks.title', $filters['search']);
            $this->db->or_like(db_prefix() . 'todo_tasks.description', $filters['search']);
            $this->db->group_end();
        }
        if (!empty($filters['special'])) {
            if ($filters['special'] === 'overdue') {
                $this->db->where(db_prefix() . 'todo_tasks.status !=', 2);
                $this->db->where(db_prefix() . 'todo_tasks.due_date <', date('Y-m-d'));
                $this->db->where(db_prefix() . 'todo_tasks.due_date IS NOT NULL');
            } elseif ($filters['special'] === 'due_today') {
                $this->db->where(db_prefix() . 'todo_tasks.status !=', 2);
                $this->db->where(db_prefix() . 'todo_tasks.due_date', date('Y-m-d'));
            }
        }

        $this->db->order_by(db_prefix() . 'todo_tasks.status', 'asc');
        $this->db->order_by(db_prefix() . 'todo_tasks.priority', 'desc');
        $this->db->order_by(db_prefix() . 'todo_tasks.sort_order', 'asc');
        $this->db->order_by(db_prefix() . 'todo_tasks.datecreated', 'desc');

        $tasks = $this->db->get()->result_array();

        // Attach checklist items and remarks to each task
        foreach ($tasks as &$task) {
            $task['checklist'] = $this->get_checklist($task['id']);
            $total = count($task['checklist']);
            $done = 0;
            foreach ($task['checklist'] as $item) {
                if ($item['is_checked']) $done++;
            }
            $task['checklist_total'] = $total;
            $task['checklist_done'] = $done;
            $task['remarks_list'] = $this->get_remarks($task['id']);
            $task['assignees'] = $this->get_task_assignees($task['id']);
            $task['assigned_roles'] = $this->get_task_roles($task['id']);
        }

        return $tasks;
    }

    /**
     * Get single task
     */
    public function get_task($id)
    {
        $this->db->where('id', $id);
        $task = $this->db->get(db_prefix() . 'todo_tasks')->row_array();
        if ($task) {
            $task['checklist'] = $this->get_checklist($id);
        }
        return $task;
    }

    /**
     * Add task
     */
    public function add_task($data)
    {
        $checklist = [];
        if (isset($data['checklist'])) {
            $checklist = $data['checklist'];
            unset($data['checklist']);
        }

        $data['datecreated'] = date('Y-m-d H:i:s');

        if (empty($data['due_date'])) {
            $data['due_date'] = null;
        }
        if (empty($data['category_id'])) {
            $data['category_id'] = null;
        }

        $this->db->insert(db_prefix() . 'todo_tasks', $data);
        $id = $this->db->insert_id();

        if ($id && !empty($checklist)) {
            $order = 0;
            foreach ($checklist as $item) {
                if (trim($item) !== '') {
                    $this->db->insert(db_prefix() . 'todo_checklist', [
                        'task_id'     => $id,
                        'title'       => trim($item),
                        'sort_order'  => $order++,
                        'datecreated' => date('Y-m-d H:i:s'),
                    ]);
                }
            }
        }

        if ($id) {
            log_activity('Todo Task Created [ID: ' . $id . ', Title: ' . $data['title'] . ']');
        }
        return $id;
    }

    /**
     * Update task
     */
    public function update_task($id, $data)
    {
        $checklist = null;
        if (isset($data['checklist'])) {
            $checklist = $data['checklist'];
            unset($data['checklist']);
        }

        if (isset($data['due_date']) && empty($data['due_date'])) {
            $data['due_date'] = null;
        }
        if (isset($data['category_id']) && empty($data['category_id'])) {
            $data['category_id'] = null;
        }

        $data['dateupdated'] = date('Y-m-d H:i:s');

        $this->db->where('id', $id);
        $this->db->update(db_prefix() . 'todo_tasks', $data);

        // Rebuild checklist if provided
        if ($checklist !== null) {
            // Remove old checklist items
            $this->db->where('task_id', $id);
            $this->db->delete(db_prefix() . 'todo_checklist');

            $order = 0;
            foreach ($checklist as $item) {
                $title = is_array($item) ? $item['title'] : $item;
                $checked = is_array($item) ? (isset($item['is_checked']) ? $item['is_checked'] : 0) : 0;
                if (trim($title) !== '') {
                    $this->db->insert(db_prefix() . 'todo_checklist', [
                        'task_id'     => $id,
                        'title'       => trim($title),
                        'is_checked'  => $checked,
                        'sort_order'  => $order++,
                        'datecreated' => date('Y-m-d H:i:s'),
                    ]);
                }
            }
        }

        log_activity('Todo Task Updated [ID: ' . $id . ']');
        return true;
    }

    /**
     * Delete task
     */
    public function delete_task($id)
    {
        // Delete checklist items first
        $this->db->where('task_id', $id);
        $this->db->delete(db_prefix() . 'todo_checklist');

        $this->db->where('id', $id);
        $this->db->delete(db_prefix() . 'todo_tasks');
        if ($this->db->affected_rows() > 0) {
            log_activity('Todo Task Deleted [ID: ' . $id . ']');
            return true;
        }
        return false;
    }

    /**
     * Toggle task status — triggers scoring when completing
     */
    public function toggle_task_status($id, $status)
    {
        $update = [
            'status'      => $status,
            'dateupdated' => date('Y-m-d H:i:s'),
        ];

        // Set date_completed when marking as completed
        if ((int)$status === 2) {
            $update['date_completed'] = date('Y-m-d H:i:s');
        } else {
            // Clearing completion — remove date_completed
            $update['date_completed'] = null;
        }

        $this->db->where('id', $id);
        $this->db->update(db_prefix() . 'todo_tasks', $update);

        // Trigger scoring engine
        if ((int)$status === 2) {
            $this->calculate_task_score($id);
        } else {
            // Un-completing: remove any scores previously awarded for this task
            $this->db->where('task_id', $id);
            $this->db->delete(db_prefix() . 'todo_score_log');
        }

        return $this->db->affected_rows() >= 0;
    }

    // ═══════════════════════════════════════════
    //  CHECKLIST
    // ═══════════════════════════════════════════

    /**
     * Get checklist items for a task
     */
    public function get_checklist($task_id)
    {
        $this->db->where('task_id', $task_id);
        $this->db->order_by('sort_order', 'asc');
        return $this->db->get(db_prefix() . 'todo_checklist')->result_array();
    }

    /**
     * Toggle checklist item
     */
    public function toggle_checklist_item($id, $is_checked)
    {
        $this->db->where('id', $id);
        $this->db->update(db_prefix() . 'todo_checklist', [
            'is_checked' => $is_checked,
        ]);
        return $this->db->affected_rows() > 0;
    }

    /**
     * Add checklist item
     */
    public function add_checklist_item($task_id, $title)
    {
        $max_order = $this->db->select_max('sort_order')
            ->where('task_id', $task_id)
            ->get(db_prefix() . 'todo_checklist')
            ->row()->sort_order;

        $this->db->insert(db_prefix() . 'todo_checklist', [
            'task_id'     => $task_id,
            'title'       => $title,
            'sort_order'  => ($max_order ? $max_order + 1 : 0),
            'datecreated' => date('Y-m-d H:i:s'),
        ]);
        return $this->db->insert_id();
    }

    /**
     * Delete checklist item
     */
    public function delete_checklist_item($id)
    {
        $this->db->where('id', $id);
        $this->db->delete(db_prefix() . 'todo_checklist');
        return $this->db->affected_rows() > 0;
    }

    /**
     * Reorder checklist items (drag & drop)
     * @param array $order Array of checklist IDs in new order
     */
    public function reorder_checklist($order)
    {
        foreach ($order as $sort_order => $id) {
            $this->db->where('id', (int)$id);
            $this->db->update(db_prefix() . 'todo_checklist', [
                'sort_order' => (int)$sort_order,
            ]);
        }
        return true;
    }

    /**
     * Update checklist item title (inline edit)
     */
    public function update_checklist_title($id, $title)
    {
        $this->db->where('id', $id);
        $this->db->update(db_prefix() . 'todo_checklist', [
            'title' => trim($title),
        ]);
        return $this->db->affected_rows() >= 0;
    }

    // ═══════════════════════════════════════════
    //  REMARKS
    // ═══════════════════════════════════════════

    /**
     * Get all remarks for a task
     */
    public function get_remarks($task_id)
    {
        $this->db->select(
            db_prefix() . 'todo_remarks.*, ' .
            'CONCAT(' . db_prefix() . 'staff.firstname, " ", ' . db_prefix() . 'staff.lastname) as staff_name'
        );
        $this->db->from(db_prefix() . 'todo_remarks');
        $this->db->join(db_prefix() . 'staff', db_prefix() . 'staff.staffid = ' . db_prefix() . 'todo_remarks.staff_id', 'left');
        $this->db->where('task_id', $task_id);
        $this->db->order_by('datecreated', 'desc');
        return $this->db->get()->result_array();
    }

    /**
     * Add a remark to a task
     */
    public function add_remark($task_id, $staff_id, $remark)
    {
        $this->db->insert(db_prefix() . 'todo_remarks', [
            'task_id'     => $task_id,
            'staff_id'    => $staff_id,
            'remark'      => $remark,
            'datecreated' => date('Y-m-d H:i:s'),
        ]);
        $id = $this->db->insert_id();
        if ($id) {
            // Return the full remark with staff name
            $this->db->select(
                db_prefix() . 'todo_remarks.*, ' .
                'CONCAT(' . db_prefix() . 'staff.firstname, " ", ' . db_prefix() . 'staff.lastname) as staff_name'
            );
            $this->db->from(db_prefix() . 'todo_remarks');
            $this->db->join(db_prefix() . 'staff', db_prefix() . 'staff.staffid = ' . db_prefix() . 'todo_remarks.staff_id', 'left');
            $this->db->where(db_prefix() . 'todo_remarks.id', $id);
            return $this->db->get()->row_array();
        }
        return null;
    }

    /**
     * Delete a remark
     */
    public function delete_remark($id)
    {
        $this->db->where('id', $id);
        $this->db->delete(db_prefix() . 'todo_remarks');
        return $this->db->affected_rows() > 0;
    }

    // ═══════════════════════════════════════════
    //  TASK ASSIGNMENT
    // ═══════════════════════════════════════════

    /**
     * Get assignees for a task
     */
    public function get_task_assignees($task_id)
    {
        $this->db->select(
            db_prefix() . 'todo_task_assignees.staff_id, ' .
            'CONCAT(' . db_prefix() . 'staff.firstname, " ", ' . db_prefix() . 'staff.lastname) as name'
        );
        $this->db->from(db_prefix() . 'todo_task_assignees');
        $this->db->join(db_prefix() . 'staff', db_prefix() . 'staff.staffid = ' . db_prefix() . 'todo_task_assignees.staff_id', 'left');
        $this->db->where('task_id', $task_id);
        return $this->db->get()->result_array();
    }

    /**
     * Get assigned roles for a task
     */
    public function get_task_roles($task_id)
    {
        $this->db->select(
            db_prefix() . 'todo_task_roles.role_id, ' .
            db_prefix() . 'roles.name'
        );
        $this->db->from(db_prefix() . 'todo_task_roles');
        $this->db->join(db_prefix() . 'roles', db_prefix() . 'roles.roleid = ' . db_prefix() . 'todo_task_roles.role_id', 'left');
        $this->db->where('task_id', $task_id);
        return $this->db->get()->result_array();
    }

    /**
     * Sync assignees for a task (replace all)
     */
    public function sync_assignees($task_id, $staff_ids = [])
    {
        $this->db->where('task_id', $task_id);
        $this->db->delete(db_prefix() . 'todo_task_assignees');

        if (!empty($staff_ids)) {
            foreach ($staff_ids as $sid) {
                if ($sid) {
                    $this->db->insert(db_prefix() . 'todo_task_assignees', [
                        'task_id'  => $task_id,
                        'staff_id' => $sid,
                    ]);
                }
            }
        }
    }

    /**
     * Sync roles for a task (replace all)
     */
    public function sync_roles($task_id, $role_ids = [])
    {
        $this->db->where('task_id', $task_id);
        $this->db->delete(db_prefix() . 'todo_task_roles');

        if (!empty($role_ids)) {
            foreach ($role_ids as $rid) {
                if ($rid) {
                    $this->db->insert(db_prefix() . 'todo_task_roles', [
                        'task_id' => $task_id,
                        'role_id' => $rid,
                    ]);
                }
            }
        }
    }

    /**
     * Get all roles for dropdowns
     */
    public function get_all_roles()
    {
        $this->db->select('roleid, name');
        $this->db->order_by('name', 'asc');
        return $this->db->get(db_prefix() . 'roles')->result_array();
    }

    // ═══════════════════════════════════════════
    //  STATS
    // ═══════════════════════════════════════════

    /**
     * Get stats for current staff
     */
    public function get_stats($staff_id = null)
    {
        $prefix = db_prefix();
        $visibility = '';

        if ($staff_id && !is_admin()) {
            $staff_id = (int)$staff_id;
            $staff_row = $this->db->query("SELECT role FROM {$prefix}staff WHERE staffid = {$staff_id} LIMIT 1")->row();
            $user_role = $staff_row ? (int)$staff_row->role : 0;

            $visibility = " AND (t.staff_id = {$staff_id}
                OR t.id IN (SELECT task_id FROM {$prefix}todo_task_assignees WHERE staff_id = {$staff_id})
                " . ($user_role ? "OR t.id IN (SELECT task_id FROM {$prefix}todo_task_roles WHERE role_id = {$user_role})" : '') . "
            )";
        }

        $sql = "
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN t.status = 0 THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN t.status = 1 THEN 1 ELSE 0 END) as in_progress,
                SUM(CASE WHEN t.status = 2 THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN t.status != 2 AND t.due_date < CURDATE() AND t.due_date IS NOT NULL THEN 1 ELSE 0 END) as overdue,
                SUM(CASE WHEN t.status != 2 AND t.due_date = CURDATE() THEN 1 ELSE 0 END) as due_today,
                SUM(CASE WHEN t.priority = 4 THEN 1 ELSE 0 END) as priority_urgent,
                SUM(CASE WHEN t.priority = 3 THEN 1 ELSE 0 END) as priority_high,
                SUM(CASE WHEN t.priority = 2 THEN 1 ELSE 0 END) as priority_medium,
                SUM(CASE WHEN t.priority = 1 THEN 1 ELSE 0 END) as priority_low
            FROM {$prefix}todo_tasks t
            WHERE 1=1 {$visibility}
        ";

        $row = $this->db->query($sql)->row_array();

        $my_tasks = 0;
        if ($staff_id) {
            $sid = (int)$staff_id;
            $sql_my = "SELECT COUNT(*) as cnt FROM {$prefix}todo_tasks t WHERE 1=1 {$visibility} AND (t.staff_id = {$sid} OR t.id IN (SELECT task_id FROM {$prefix}todo_task_assignees WHERE staff_id = {$sid}))";
            $row_my = $this->db->query($sql_my)->row_array();
            $my_tasks = (int)($row_my['cnt'] ?? 0);
        }

        // Category counts
        $cat_sql = "
            SELECT t.category_id, COUNT(*) as cnt
            FROM {$prefix}todo_tasks t
            WHERE t.category_id IS NOT NULL AND t.category_id > 0 {$visibility}
            GROUP BY t.category_id
        ";
        $cat_rows = $this->db->query($cat_sql)->result_array();
        $cat_counts = [];
        foreach ($cat_rows as $cr) {
            $cat_counts[(int)$cr['category_id']] = (int)$cr['cnt'];
        }

        return [
            'total'           => (int)($row['total'] ?? 0),
            'my_tasks'        => $my_tasks,
            'pending'         => (int)($row['pending'] ?? 0),
            'in_progress'     => (int)($row['in_progress'] ?? 0),
            'completed'       => (int)($row['completed'] ?? 0),
            'overdue'         => (int)($row['overdue'] ?? 0),
            'due_today'       => (int)($row['due_today'] ?? 0),
            'priority_urgent' => (int)($row['priority_urgent'] ?? 0),
            'priority_high'   => (int)($row['priority_high'] ?? 0),
            'priority_medium' => (int)($row['priority_medium'] ?? 0),
            'priority_low'    => (int)($row['priority_low'] ?? 0),
            'cat_counts'      => $cat_counts,
        ];
    }

    // ═══════════════════════════════════════════
    //  REPORTS
    // ═══════════════════════════════════════════

    /**
     * Get all categories (all staff, no filter) for report dropdowns
     */
    public function get_all_categories()
    {
        $this->db->order_by('name', 'asc');
        $this->db->group_by('name');
        return $this->db->get(db_prefix() . 'todo_categories')->result_array();
    }

    /**
     * Get all staff members for report dropdowns
     */
    public function get_all_staff()
    {
        $this->db->select('staffid, firstname, lastname');
        $this->db->where('active', 1);
        $this->db->order_by('firstname', 'asc');
        return $this->db->get(db_prefix() . 'staff')->result_array();
    }

    /**
     * Get comprehensive report data — per-staff performance with Plan-Do analysis
     */
    public function get_report_data($filters = [])
    {
        $prefix = db_prefix();

        // Build WHERE clauses for tasks
        $where_sql = ' WHERE 1=1';
        $binds = [];

        if (!empty($filters['staff_id'])) {
            $where_sql .= ' AND t.staff_id = ?';
            $binds[] = $filters['staff_id'];
        }
        if (!empty($filters['category_id'])) {
            $where_sql .= ' AND t.category_id = ?';
            $binds[] = $filters['category_id'];
        }
        if (!empty($filters['date_from'])) {
            $where_sql .= ' AND t.due_date >= ?';
            $binds[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where_sql .= ' AND t.due_date <= ?';
            $binds[] = $filters['date_to'];
        }

        // ── Overall summary stats ──
        $sql_summary = "
            SELECT
                COUNT(*) as total_tasks,
                SUM(CASE WHEN t.status = 0 THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN t.status = 1 THEN 1 ELSE 0 END) as in_progress,
                SUM(CASE WHEN t.status = 2 THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN t.status != 2 AND t.due_date < CURDATE() AND t.due_date IS NOT NULL THEN 1 ELSE 0 END) as overdue,
                SUM(CASE WHEN t.priority = 4 THEN 1 ELSE 0 END) as urgent,
                SUM(CASE WHEN t.priority = 3 THEN 1 ELSE 0 END) as high,
                SUM(CASE WHEN t.priority = 2 THEN 1 ELSE 0 END) as medium,
                SUM(CASE WHEN t.priority = 1 THEN 1 ELSE 0 END) as low
            FROM {$prefix}todo_tasks t
            {$where_sql}
        ";
        $summary = $this->db->query($sql_summary, $binds)->row_array();

        // ── Per-staff breakdown ──
        $sql_staff = "
            SELECT
                s.staffid,
                CONCAT(s.firstname, ' ', s.lastname) as staff_name,
                COUNT(t.id) as total_tasks,
                SUM(CASE WHEN t.status = 0 THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN t.status = 1 THEN 1 ELSE 0 END) as in_progress,
                SUM(CASE WHEN t.status = 2 THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN t.status != 2 AND t.due_date < CURDATE() AND t.due_date IS NOT NULL THEN 1 ELSE 0 END) as overdue,
                SUM(CASE WHEN t.priority = 4 THEN 1 ELSE 0 END) as urgent_count,
                SUM(CASE WHEN t.priority = 3 THEN 1 ELSE 0 END) as high_count,
                COALESCE(
                    (SELECT COUNT(*) FROM {$prefix}todo_checklist cl 
                     INNER JOIN {$prefix}todo_tasks t2 ON cl.task_id = t2.id 
                     WHERE t2.staff_id = s.staffid),
                0) as total_checklist,
                COALESCE(
                    (SELECT SUM(cl.is_checked) FROM {$prefix}todo_checklist cl 
                     INNER JOIN {$prefix}todo_tasks t2 ON cl.task_id = t2.id 
                     WHERE t2.staff_id = s.staffid),
                0) as done_checklist
            FROM {$prefix}staff s
            INNER JOIN {$prefix}todo_tasks t ON t.staff_id = s.staffid
            {$where_sql}
            AND s.active = 1
            GROUP BY s.staffid
            ORDER BY completed DESC, total_tasks DESC
        ";
        $staff_data = $this->db->query($sql_staff, $binds)->result_array();

        // Calculate completion rates
        foreach ($staff_data as &$row) {
            $row['completion_rate'] = $row['total_tasks'] > 0 
                ? round(($row['completed'] / $row['total_tasks']) * 100, 1) 
                : 0;
            $row['checklist_rate'] = $row['total_checklist'] > 0 
                ? round(($row['done_checklist'] / $row['total_checklist']) * 100, 1) 
                : 0;
            $row['plan_score'] = $row['total_tasks']; // Plan = assigned tasks
            $row['do_score'] = $row['completed'] + $row['in_progress']; // Do = acted-upon tasks
        }

        // ── Per-category breakdown ──
        $sql_cat = "
            SELECT
                COALESCE(c.name, 'Uncategorized') as category_name,
                COALESCE(c.color, '#94a3b8') as category_color,
                COALESCE(c.icon, 'fa-folder') as category_icon,
                COUNT(t.id) as total_tasks,
                SUM(CASE WHEN t.status = 2 THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN t.status != 2 AND t.due_date < CURDATE() AND t.due_date IS NOT NULL THEN 1 ELSE 0 END) as overdue
            FROM {$prefix}todo_tasks t
            LEFT JOIN {$prefix}todo_categories c ON c.id = t.category_id
            {$where_sql}
            GROUP BY COALESCE(c.id, 0)
            ORDER BY total_tasks DESC
        ";
        $category_data = $this->db->query($sql_cat, $binds)->result_array();

        foreach ($category_data as &$cat) {
            $cat['completion_rate'] = $cat['total_tasks'] > 0 
                ? round(($cat['completed'] / $cat['total_tasks']) * 100, 1) 
                : 0;
        }

        // ── Timeline: last 30 days task creation/completion ──
        $sql_timeline = "
            SELECT
                DATE(t.datecreated) as date_val,
                COUNT(*) as created,
                SUM(CASE WHEN t.status = 2 THEN 1 ELSE 0 END) as completed
            FROM {$prefix}todo_tasks t
            {$where_sql}
            AND t.datecreated >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY DATE(t.datecreated)
            ORDER BY date_val ASC
        ";
        $timeline = $this->db->query($sql_timeline, $binds)->result_array();

        return [
            'summary'       => $summary,
            'staff'         => $staff_data,
            'categories'    => $category_data,
            'timeline'      => $timeline,
        ];
    }

    // ═══════════════════════════════════════════
    //  TASK TEMPLATES
    // ═══════════════════════════════════════════

    /**
     * Get all templates
     */
    public function get_templates()
    {
        $this->db->select(
            db_prefix() . 'todo_templates.*, ' .
            db_prefix() . 'todo_categories.name as category_name, ' .
            db_prefix() . 'todo_categories.color as category_color, ' .
            'CONCAT(' . db_prefix() . 'staff.firstname, " ", ' . db_prefix() . 'staff.lastname) as created_by_name'
        );
        $this->db->from(db_prefix() . 'todo_templates');
        $this->db->join(db_prefix() . 'todo_categories', db_prefix() . 'todo_categories.id = ' . db_prefix() . 'todo_templates.category_id', 'left');
        $this->db->join(db_prefix() . 'staff', db_prefix() . 'staff.staffid = ' . db_prefix() . 'todo_templates.created_by', 'left');
        $this->db->order_by('datecreated', 'desc');
        return $this->db->get()->result_array();
    }

    /**
     * Get single template
     */
    public function get_template($id)
    {
        $this->db->where('id', $id);
        return $this->db->get(db_prefix() . 'todo_templates')->row_array();
    }

    /**
     * Add template
     */
    public function add_template($data)
    {
        $data['datecreated'] = date('Y-m-d H:i:s');

        // Calculate next_run_date for recurring templates
        if (!empty($data['is_recurring']) && $data['is_recurring'] == 1) {
            $data['next_run_date'] = $this->calculate_next_run_date(
                date('Y-m-d'),
                $data['recurring_type'] ?? 'day',
                $data['repeat_every'] ?? 1
            );
        }

        $this->db->insert(db_prefix() . 'todo_templates', $data);
        return $this->db->insert_id();
    }

    /**
     * Update template
     */
    public function update_template($id, $data)
    {
        // Recalculate next_run_date if recurring settings changed
        if (isset($data['is_recurring']) && $data['is_recurring'] == 1) {
            $current = $this->get_template($id);
            $base = ($current && $current['last_recurring_date'])
                ? $current['last_recurring_date']
                : date('Y-m-d');
            $data['next_run_date'] = $this->calculate_next_run_date(
                $base,
                $data['recurring_type'] ?? 'day',
                $data['repeat_every'] ?? 1
            );
        } elseif (isset($data['is_recurring']) && $data['is_recurring'] == 0) {
            $data['next_run_date'] = null;
        }

        $this->db->where('id', $id);
        $this->db->update(db_prefix() . 'todo_templates', $data);
        return $this->db->affected_rows() >= 0;
    }

    /**
     * Delete template
     */
    public function delete_template($id)
    {
        $this->db->where('id', $id);
        $this->db->delete(db_prefix() . 'todo_templates');
        return $this->db->affected_rows() > 0;
    }

    /**
     * Create a live task from a template
     */
    public function create_task_from_template($template_id, $staff_id = null)
    {
        $tpl = $this->get_template($template_id);
        if (!$tpl) return false;

        if (!$staff_id) $staff_id = get_staff_user_id();

        // Build task data
        $task_data = [
            'title'       => $tpl['name'],
            'description' => $tpl['description'] ?? '',
            'category_id' => $tpl['category_id'],
            'priority'    => $tpl['priority'] ?? 1,
            'status'      => 0, // Pending
            'staff_id'    => $staff_id,
            'due_date'    => $tpl['due_days'] ? date('Y-m-d', strtotime('+' . (int)$tpl['due_days'] . ' days')) : null,
        ];

        $task_id = $this->add_task($task_data);
        if (!$task_id) return false;

        // Add checklist items
        $checklist = !empty($tpl['checklist_json']) ? json_decode($tpl['checklist_json'], true) : [];
        if (is_array($checklist)) {
            foreach ($checklist as $item) {
                if (is_string($item) && trim($item) !== '') {
                    $this->add_checklist_item($task_id, trim($item));
                }
            }
        }

        // Assign staff
        $assignee_ids = !empty($tpl['assignee_ids_json']) ? json_decode($tpl['assignee_ids_json'], true) : [];
        if (is_array($assignee_ids) && !empty($assignee_ids)) {
            $this->sync_assignees($task_id, $assignee_ids);
        }

        // Assign roles
        $role_ids = !empty($tpl['role_ids_json']) ? json_decode($tpl['role_ids_json'], true) : [];
        if (is_array($role_ids) && !empty($role_ids)) {
            $this->sync_roles($task_id, $role_ids);
        }

        return $task_id;
    }

    /**
     * Process recurring templates — called by cron hook
     * Follows the Perfex CRM cron recurring pattern.
     */
    public function process_recurring_templates()
    {
        $prefix = db_prefix();
        $today = date('Y-m-d');

        // Get all active recurring templates that are due
        $this->db->where('is_recurring', 1);
        $this->db->where('is_active', 1);
        $this->db->group_start();
            $this->db->where('next_run_date <=', $today);
            $this->db->or_where('next_run_date IS NULL');
        $this->db->group_end();
        $templates = $this->db->get($prefix . 'todo_templates')->result_array();

        foreach ($templates as $tpl) {
            // Create the task from template
            $task_id = $this->create_task_from_template($tpl['id'], $tpl['created_by']);

            if ($task_id) {
                // Calculate next run date
                $next = $this->calculate_next_run_date(
                    $today,
                    $tpl['recurring_type'],
                    $tpl['repeat_every']
                );

                // Update template with last run and next run
                $this->db->where('id', $tpl['id']);
                $this->db->update($prefix . 'todo_templates', [
                    'last_recurring_date' => $today,
                    'next_run_date'       => $next,
                ]);

                log_activity('Todo Recurring Task Created [Template: ' . $tpl['name'] . ', Task ID: ' . $task_id . ']');
            }
        }
    }

    /**
     * Calculate the next run date based on recurring type
     */
    private function calculate_next_run_date($from_date, $type, $every)
    {
        $every = max(1, (int)$every);
        $type = strtolower($type);

        $map = [
            'day'   => 'days',
            'week'  => 'weeks',
            'month' => 'months',
        ];

        $unit = isset($map[$type]) ? $map[$type] : 'days';
        return date('Y-m-d', strtotime('+' . $every . ' ' . $unit, strtotime($from_date)));
    }

    // ═══════════════════════════════════════════
    //  BNI-STYLE INCENTIVE SCORING ENGINE
    // ═══════════════════════════════════════════

    /**
     * Core scoring engine — calculates all points for a completed task
     * Called automatically when toggle_task_status sets status=2
     */
    public function calculate_task_score($task_id)
    {
        $prefix = db_prefix();
        $task = $this->db->query("SELECT * FROM {$prefix}todo_tasks WHERE id = ?", [$task_id])->row_array();
        if (!$task) return;

        $staff_id = (int)$task['staff_id'];
        $due_date = $task['due_date'];
        $date_completed = $task['date_completed'] ?: date('Y-m-d H:i:s');
        $today = date('Y-m-d');

        // Remove any existing scores for this task (idempotent re-scoring)
        $this->db->where('task_id', $task_id);
        $this->db->delete($prefix . 'todo_score_log');

        // Also score assignees (not just creator)
        $staff_ids = [$staff_id];
        $assignees = $this->db->query("SELECT staff_id FROM {$prefix}todo_task_assignees WHERE task_id = ?", [$task_id])->result_array();
        foreach ($assignees as $a) {
            $sid = (int)$a['staff_id'];
            if (!in_array($sid, $staff_ids)) {
                $staff_ids[] = $sid;
            }
        }

        foreach ($staff_ids as $sid) {
            $this->_score_task_for_staff($sid, $task_id, $task, $due_date, $date_completed, $today);
        }
    }

    /**
     * Score a single task for a single staff member
     */
    private function _score_task_for_staff($staff_id, $task_id, $task, $due_date, $date_completed, $today)
    {
        $prefix = db_prefix();
        $completed_date = date('Y-m-d', strtotime($date_completed));
        $priority = (int)($task['priority'] ?: 1);

        // 1. Base: Task Completed = +10 always
        $this->_insert_score($staff_id, $task_id, 'task_completed', 10, 'Task completed', $completed_date);

        if ($due_date) {
            $due_ts = strtotime($due_date);
            $comp_ts = strtotime($completed_date);
            $diff_days = (int)floor(($due_ts - $comp_ts) / 86400); // positive = early, negative = late

            if ($diff_days >= 0) {
                // ON TIME or EARLY
                $this->_insert_score($staff_id, $task_id, 'on_time', 0, 'Completed on time', $completed_date);

                // Early bonus: 1+ days early = +5
                if ($diff_days >= 1) {
                    $this->_insert_score($staff_id, $task_id, 'early_1day', 5, 'Completed ' . $diff_days . ' day(s) before due date', $completed_date);
                }

                // Extra early bonus: 2+ days early = +3
                if ($diff_days >= 2) {
                    $this->_insert_score($staff_id, $task_id, 'early_2day', 3, 'Completed 2+ days early — extra bonus', $completed_date);
                }
            } else {
                // LATE
                $late_days = abs($diff_days);
                if ($late_days <= 3) {
                    // -1 per day for 1-3 days late
                    $penalty = -1 * $late_days;
                    $this->_insert_score($staff_id, $task_id, 'late_daily', $penalty, 'Completed ' . $late_days . ' day(s) late (-1/day)', $completed_date);
                } else {
                    // -5 flat for 3+ days late
                    $this->_insert_score($staff_id, $task_id, 'late_3plus', -5, 'Completed ' . $late_days . ' days late (>3 days penalty)', $completed_date);
                }
            }
        }

        // High/Urgent priority bonus
        if ($priority >= 3 && $due_date) {
            $due_ts = strtotime($due_date);
            $comp_ts = strtotime($completed_date);
            if ($comp_ts <= $due_ts) {
                $this->_insert_score($staff_id, $task_id, 'high_priority', 5, 'High/Urgent priority completed on time', $completed_date);
            }
        }

        // Checklist completion bonus
        $cl_total = $this->db->query("SELECT COUNT(*) as cnt FROM {$prefix}todo_checklist WHERE task_id = ?", [$task_id])->row()->cnt;
        $cl_done = $this->db->query("SELECT COUNT(*) as cnt FROM {$prefix}todo_checklist WHERE task_id = ? AND is_checked = 1", [$task_id])->row()->cnt;
        if ($cl_total > 0 && $cl_done == $cl_total) {
            $this->_insert_score($staff_id, $task_id, 'checklist_complete', 3, 'All ' . $cl_total . ' checklist items completed', $completed_date);
        }
    }

    /**
     * Insert a score log entry
     */
    private function _insert_score($staff_id, $task_id, $type, $points, $reason, $score_date)
    {
        $this->db->insert(db_prefix() . 'todo_score_log', [
            'staff_id'    => $staff_id,
            'task_id'     => $task_id,
            'score_type'  => $type,
            'points'      => $points,
            'reason'      => $reason,
            'score_date'  => $score_date,
            'datecreated' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Calculate overdue penalties for tasks still overdue (cron-callable)
     * Should be called once per day — adds -2 per overdue task per day, capped at -20
     */
    public function calculate_overdue_penalties()
    {
        $prefix = db_prefix();
        $today = date('Y-m-d');

        // Find all overdue, non-completed tasks
        $overdue_tasks = $this->db->query("
            SELECT t.id, t.staff_id, t.due_date, t.title
            FROM {$prefix}todo_tasks t
            WHERE t.status != 2
              AND t.due_date IS NOT NULL
              AND t.due_date < '{$today}'
        ")->result_array();

        foreach ($overdue_tasks as $task) {
            $staff_ids = [(int)$task['staff_id']];
            $assignees = $this->db->query("SELECT staff_id FROM {$prefix}todo_task_assignees WHERE task_id = ?", [$task['id']])->result_array();
            foreach ($assignees as $a) {
                $sid = (int)$a['staff_id'];
                if (!in_array($sid, $staff_ids)) $staff_ids[] = $sid;
            }

            foreach ($staff_ids as $sid) {
                // Check total overdue penalties already given for this task
                $existing = $this->db->query("
                    SELECT COALESCE(SUM(points), 0) as total_penalty
                    FROM {$prefix}todo_score_log
                    WHERE task_id = ? AND staff_id = ? AND score_type = 'overdue_running'
                ", [$task['id'], $sid])->row()->total_penalty;

                // Cap at -20
                if ((float)$existing > -20) {
                    // Check if already penalised today
                    $today_check = $this->db->query("
                        SELECT id FROM {$prefix}todo_score_log
                        WHERE task_id = ? AND staff_id = ? AND score_type = 'overdue_running' AND score_date = ?
                    ", [$task['id'], $sid, $today])->row();

                    if (!$today_check) {
                        $this->_insert_score($sid, $task['id'], 'overdue_running', -2, 'Task overdue: ' . $task['title'], $today);
                    }
                }
            }
        }
    }

    // ═══════════════════════════════════════════
    //  ATTENDANCE MANAGEMENT
    // ═══════════════════════════════════════════

    /**
     * Mark attendance for a staff member
     */
    public function add_attendance($staff_id, $date, $status, $marked_by)
    {
        $prefix = db_prefix();
        $points_map = [1 => 5, 2 => 3, 0 => 0]; // present=5, late=3, absent=0
        $points = isset($points_map[$status]) ? $points_map[$status] : 0;
        $status_labels = [1 => 'Present', 2 => 'Late', 0 => 'Absent'];
        $label = isset($status_labels[$status]) ? $status_labels[$status] : 'Unknown';

        // Upsert attendance record
        $existing = $this->db->query("SELECT id FROM {$prefix}todo_attendance WHERE staff_id = ? AND attend_date = ?", [$staff_id, $date])->row();
        if ($existing) {
            $this->db->where('id', $existing->id);
            $this->db->update($prefix . 'todo_attendance', [
                'status'    => $status,
                'points'    => $points,
                'marked_by' => $marked_by,
            ]);

            // Update score log
            $this->db->where('staff_id', $staff_id);
            $this->db->where('score_type', 'attendance');
            $this->db->where('score_date', $date);
            $this->db->delete($prefix . 'todo_score_log');
        } else {
            $this->db->insert($prefix . 'todo_attendance', [
                'staff_id'    => $staff_id,
                'attend_date' => $date,
                'status'      => $status,
                'points'      => $points,
                'marked_by'   => $marked_by,
                'datecreated' => date('Y-m-d H:i:s'),
            ]);
        }

        // Add score log entry for attendance
        if ($points != 0) {
            $this->_insert_score($staff_id, null, 'attendance', $points, 'Attendance: ' . $label, $date);
        }

        return true;
    }

    /**
     * Get attendance records for a staff member in a period
     */
    public function get_attendance($staff_id = null, $period_from = null, $period_to = null)
    {
        $prefix = db_prefix();
        $this->db->select("{$prefix}todo_attendance.*, CONCAT({$prefix}staff.firstname, ' ', {$prefix}staff.lastname) as staff_name");
        $this->db->from($prefix . 'todo_attendance');
        $this->db->join($prefix . 'staff', "{$prefix}staff.staffid = {$prefix}todo_attendance.staff_id", 'left');

        if ($staff_id) $this->db->where('staff_id', $staff_id);
        if ($period_from) $this->db->where('attend_date >=', $period_from);
        if ($period_to) $this->db->where('attend_date <=', $period_to);

        $this->db->order_by('attend_date', 'desc');
        return $this->db->get()->result_array();
    }

    /**
     * Admin manual score adjustment
     */
    public function add_manual_score($staff_id, $points, $reason)
    {
        $this->_insert_score($staff_id, null, 'manual_adjustment', $points, $reason, date('Y-m-d'));
        log_activity('Todo Manual Score: ' . $points . ' points for staff #' . $staff_id . ' — ' . $reason);
        return true;
    }

    // ═══════════════════════════════════════════
    //  SCORECARD & TRAFFIC LIGHT
    // ═══════════════════════════════════════════

    /**
     * Get scorecard for all staff members in a period
     */
    public function get_all_scorecards($period_from = null, $period_to = null)
    {
        $prefix = db_prefix();

        if (!$period_from) $period_from = date('Y-m-01');
        if (!$period_to) $period_to = date('Y-m-t');

        // Get all active staff
        $staff_list = $this->db->query("
            SELECT staffid, CONCAT(firstname, ' ', lastname) as staff_name
            FROM {$prefix}staff WHERE active = 1 ORDER BY firstname ASC
        ")->result_array();

        $scorecards = [];
        foreach ($staff_list as $s) {
            $scorecards[] = $this->get_staff_scorecard($s['staffid'], $period_from, $period_to);
        }

        // Sort by total score descending
        usort($scorecards, function($a, $b) {
            return $b['total_score'] - $a['total_score'];
        });

        return $scorecards;
    }

    /**
     * Get detailed scorecard for one staff member
     */
    public function get_staff_scorecard($staff_id, $period_from = null, $period_to = null)
    {
        $prefix = db_prefix();

        if (!$period_from) $period_from = date('Y-m-01');
        if (!$period_to) $period_to = date('Y-m-t');

        $staff_id = (int)$staff_id;

        // Staff info
        $staff = $this->db->query("SELECT staffid, CONCAT(firstname, ' ', lastname) as staff_name FROM {$prefix}staff WHERE staffid = ?", [$staff_id])->row_array();
        if (!$staff) return null;

        // Total score in period
        $score_row = $this->db->query("
            SELECT COALESCE(SUM(points), 0) as total_score
            FROM {$prefix}todo_score_log
            WHERE staff_id = ? AND score_date >= ? AND score_date <= ?
        ", [$staff_id, $period_from, $period_to])->row_array();

        // Score breakdown by type
        $breakdown = $this->db->query("
            SELECT score_type, SUM(points) as type_total, COUNT(*) as type_count
            FROM {$prefix}todo_score_log
            WHERE staff_id = ? AND score_date >= ? AND score_date <= ?
            GROUP BY score_type
            ORDER BY type_total DESC
        ", [$staff_id, $period_from, $period_to])->result_array();

        // Task stats in period
        $task_stats = $this->db->query("
            SELECT
                COUNT(*) as total_tasks,
                SUM(CASE WHEN status = 2 THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 2 AND date_completed IS NOT NULL AND due_date IS NOT NULL
                    AND DATE(date_completed) <= due_date THEN 1 ELSE 0 END) as on_time,
                SUM(CASE WHEN status = 2 AND date_completed IS NOT NULL AND due_date IS NOT NULL
                    AND DATE(date_completed) < due_date THEN 1 ELSE 0 END) as early,
                SUM(CASE WHEN status = 2 AND date_completed IS NOT NULL AND due_date IS NOT NULL
                    AND DATE(date_completed) > due_date THEN 1 ELSE 0 END) as late,
                SUM(CASE WHEN status != 2 AND due_date IS NOT NULL
                    AND due_date < CURDATE() THEN 1 ELSE 0 END) as still_overdue
            FROM {$prefix}todo_tasks
            WHERE (staff_id = {$staff_id}
                OR id IN (SELECT task_id FROM {$prefix}todo_task_assignees WHERE staff_id = {$staff_id}))
              AND datecreated >= '{$period_from}'
              AND datecreated <= '{$period_to} 23:59:59'
        ")->row_array();

        // Attendance in period
        $attendance = $this->db->query("
            SELECT
                COUNT(*) as total_days,
                SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as present,
                SUM(CASE WHEN status = 2 THEN 1 ELSE 0 END) as late,
                SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) as absent,
                COALESCE(SUM(points), 0) as attendance_points
            FROM {$prefix}todo_attendance
            WHERE staff_id = ? AND attend_date >= ? AND attend_date <= ?
        ", [$staff_id, $period_from, $period_to])->row_array();

        // Calculate max possible score
        $total_tasks = (int)($task_stats['total_tasks'] ?? 0);
        $max_task_score = $total_tasks * 10; // +10 per task if all completed on time
        $working_days = $this->_count_working_days($period_from, $period_to);
        $max_attendance = $working_days * 5;
        $max_possible = max(1, $max_task_score + $max_attendance);

        $total_score = (float)($score_row['total_score'] ?? 0);
        $power_score = min(100, max(0, round(($total_score / $max_possible) * 100, 1)));

        return [
            'staff_id'          => $staff_id,
            'staff_name'        => $staff['staff_name'],
            'total_score'       => $total_score,
            'max_possible'      => $max_possible,
            'power_score'       => $power_score,
            'traffic_light'     => $this->get_traffic_light($power_score),
            'breakdown'         => $breakdown,
            'task_stats'        => $task_stats,
            'attendance'        => $attendance,
            'working_days'      => $working_days,
        ];
    }

    /**
     * Get traffic light zone from Power of One score (0-100)
     */
    public function get_traffic_light($power_score)
    {
        if ($power_score >= 80) return 'green';
        if ($power_score >= 40) return 'yellow';
        return 'red';
    }

    /**
     * Get detailed score log for a staff member
     */
    public function get_score_log($staff_id = null, $period_from = null, $period_to = null)
    {
        $prefix = db_prefix();
        $this->db->select("{$prefix}todo_score_log.*, {$prefix}todo_tasks.title as task_title, CONCAT({$prefix}staff.firstname, ' ', {$prefix}staff.lastname) as staff_name");
        $this->db->from($prefix . 'todo_score_log');
        $this->db->join($prefix . 'todo_tasks', "{$prefix}todo_tasks.id = {$prefix}todo_score_log.task_id", 'left');
        $this->db->join($prefix . 'staff', "{$prefix}staff.staffid = {$prefix}todo_score_log.staff_id", 'left');

        if ($staff_id) $this->db->where("{$prefix}todo_score_log.staff_id", $staff_id);
        if ($period_from) $this->db->where('score_date >=', $period_from);
        if ($period_to) $this->db->where('score_date <=', $period_to);

        $this->db->order_by('score_date', 'desc');
        $this->db->order_by("{$prefix}todo_score_log.datecreated", 'desc');
        $this->db->limit(500);
        return $this->db->get()->result_array();
    }

    /**
     * Count working days (Mon-Sat) between two dates
     */
    private function _count_working_days($from, $to)
    {
        $start = new DateTime($from);
        $end = new DateTime($to);
        $count = 0;
        while ($start <= $end) {
            $day = (int)$start->format('N'); // 1=Mon ... 7=Sun
            if ($day <= 6) $count++; // Mon-Sat
            $start->modify('+1 day');
        }
        return $count;
    }
}
