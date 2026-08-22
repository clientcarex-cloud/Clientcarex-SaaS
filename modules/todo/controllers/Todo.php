<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Todo extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('todo_model');
    }

    /**
     * Main single-page view
     */
    public function index()
    {
        if (!staff_can('view', 'todo') && !staff_can('create', 'todo') && !staff_can('edit', 'todo') && !is_admin()) {
            access_denied('todo');
        }

        $staff_id = get_staff_user_id();

        $data['title']      = 'Todo';
        $data['categories'] = $this->todo_model->get_categories($staff_id);
        $data['stats']      = $this->todo_model->get_stats($staff_id);
        $data['tasks']      = $this->todo_model->get_tasks(['staff_id' => $staff_id]);
        $data['staff_id']   = $staff_id;
        $data['staff_list'] = $this->todo_model->get_all_staff();
        $data['roles_list'] = $this->todo_model->get_all_roles();

        $this->load->view('manage', $data);
    }

    /**
     * Reports page — Advanced staff task/checklist performance analytics
     */
    public function reports()
    {
        if (!is_admin()) {
            access_denied('todo');
        }

        $data['title']      = 'Todo Reports';
        $data['categories'] = $this->todo_model->get_all_categories();
        $data['staff_list'] = $this->todo_model->get_all_staff();
        $data['report']     = $this->todo_model->get_report_data();
        $data['scorecards'] = $this->todo_model->get_all_scorecards();

        $this->load->view('reports', $data);
    }

    // ═══════════════════════════════════════════
    //  AJAX ENDPOINTS — TASKS
    // ═══════════════════════════════════════════

    /**
     * Get tasks via AJAX (with filters)
     */
    public function ajax_get_tasks()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $staff_id = get_staff_user_id();
        $filters = [
            'staff_id'    => $staff_id,
            'status'      => $this->input->post('status'),
            'category_id' => $this->input->post('category_id'),
            'priority'    => $this->input->post('priority'),
            'search'      => $this->input->post('search'),
        ];

        $tasks = $this->todo_model->get_tasks($filters);
        $stats = $this->todo_model->get_stats($staff_id);

        echo json_encode(['success' => true, 'tasks' => $tasks, 'stats' => $stats]);
    }

    /**
     * AJAX endpoint for reports data with filters
     */
    public function ajax_report_data()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }
        if (!is_admin()) {
            echo json_encode(['success' => false, 'message' => 'Permission denied']);
            return;
        }

        $filters = [
            'staff_id'    => $this->input->post('staff_id'),
            'category_id' => $this->input->post('category_id'),
            'date_from'   => $this->input->post('date_from'),
            'date_to'     => $this->input->post('date_to'),
        ];

        $report = $this->todo_model->get_report_data($filters);
        echo json_encode(['success' => true, 'report' => $report]);
    }

    // ═══════════════════════════════════════════
    //  AJAX ENDPOINTS — SCORECARDS & INCENTIVES
    // ═══════════════════════════════════════════

    /**
     * Get all staff scorecards for a period
     */
    public function ajax_get_scorecards()
    {
        if (!$this->input->is_ajax_request()) { show_404(); }
        if (!is_admin()) {
            echo json_encode(['success' => false, 'message' => 'Permission denied']);
            return;
        }

        $period_from = $this->input->post('period_from');
        $period_to   = $this->input->post('period_to');

        $scorecards = $this->todo_model->get_all_scorecards($period_from, $period_to);

        // Traffic light summary counts
        $tl_summary = ['green' => 0, 'yellow' => 0, 'red' => 0];
        foreach ($scorecards as $sc) {
            $tl = $sc['traffic_light'];
            if (isset($tl_summary[$tl])) $tl_summary[$tl]++;
        }

        echo json_encode([
            'success'    => true,
            'scorecards' => $scorecards,
            'tl_summary' => $tl_summary,
        ]);
    }

    /**
     * Get detailed score log for a staff member
     */
    public function ajax_get_staff_score_detail()
    {
        if (!$this->input->is_ajax_request()) { show_404(); }
        if (!is_admin()) {
            echo json_encode(['success' => false, 'message' => 'Permission denied']);
            return;
        }

        $staff_id    = $this->input->post('staff_id');
        $period_from = $this->input->post('period_from');
        $period_to   = $this->input->post('period_to');

        $scorecard = $this->todo_model->get_staff_scorecard($staff_id, $period_from, $period_to);
        $score_log = $this->todo_model->get_score_log($staff_id, $period_from, $period_to);

        echo json_encode([
            'success'   => true,
            'scorecard' => $scorecard,
            'score_log' => $score_log,
        ]);
    }

    /**
     * Mark attendance for a staff member
     */
    public function ajax_mark_attendance()
    {
        if (!$this->input->is_ajax_request()) { show_404(); }
        if (!is_admin()) {
            echo json_encode(['success' => false, 'message' => 'Permission denied']);
            return;
        }

        $staff_id = $this->input->post('staff_id');
        $date     = $this->input->post('date') ?: date('Y-m-d');
        $status   = (int)$this->input->post('status'); // 1=present, 0=absent, 2=late

        $result = $this->todo_model->add_attendance($staff_id, $date, $status, get_staff_user_id());
        echo json_encode(['success' => $result]);
    }

    /**
     * Admin manual score adjustment
     */
    public function ajax_add_manual_score()
    {
        if (!$this->input->is_ajax_request()) { show_404(); }
        if (!is_admin()) {
            echo json_encode(['success' => false, 'message' => 'Permission denied']);
            return;
        }

        $staff_id = $this->input->post('staff_id');
        $points   = (float)$this->input->post('points');
        $reason   = $this->input->post('reason');

        if (empty($reason)) {
            echo json_encode(['success' => false, 'message' => 'Reason is required']);
            return;
        }

        $result = $this->todo_model->add_manual_score($staff_id, $points, $reason);
        echo json_encode(['success' => $result]);
    }


    /**
     * Add task via AJAX
     */
    public function ajax_add_task()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }
        if (!staff_can('create', 'todo') && !is_admin()) {
            echo json_encode(['success' => false, 'message' => 'Permission denied']);
            return;
        }

        $data = [
            'title'       => $this->input->post('title'),
            'description' => $this->input->post('description'),
            'category_id' => $this->input->post('category_id'),
            'priority'    => $this->input->post('priority') ?: 1,
            'due_date'    => $this->input->post('due_date'),
            'staff_id'    => get_staff_user_id(),
            'status'      => 0,
            'remarks'     => $this->input->post('remarks'),
        ];

        $checklist = $this->input->post('checklist');
        if ($checklist) {
            $data['checklist'] = $checklist;
        }

        $id = $this->todo_model->add_task($data);

        if ($id) {
            // Sync assignees
            $assignee_ids = $this->input->post('assignee_ids');
            if ($assignee_ids) {
                $this->todo_model->sync_assignees($id, $assignee_ids);
            }
            // Sync roles
            $role_ids = $this->input->post('role_ids');
            if ($role_ids) {
                $this->todo_model->sync_roles($id, $role_ids);
            }

            echo json_encode(['success' => true, 'id' => $id, 'message' => 'Task created successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to create task']);
        }
    }

    /**
     * Update task via AJAX
     */
    public function ajax_update_task()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }
        if (!staff_can('edit', 'todo') && !is_admin()) {
            echo json_encode(['success' => false, 'message' => 'Permission denied']);
            return;
        }

        $id = $this->input->post('id');
        $data = [];

        if ($this->input->post('title') !== null) {
            $data['title'] = $this->input->post('title');
        }
        if ($this->input->post('description') !== null) {
            $data['description'] = $this->input->post('description');
        }
        if ($this->input->post('category_id') !== null) {
            $data['category_id'] = $this->input->post('category_id');
        }
        if ($this->input->post('priority') !== null) {
            $data['priority'] = $this->input->post('priority');
        }
        if ($this->input->post('due_date') !== null) {
            $data['due_date'] = $this->input->post('due_date');
        }
        if ($this->input->post('status') !== null) {
            $data['status'] = $this->input->post('status');
        }

        $checklist = $this->input->post('checklist');
        if ($checklist !== null) {
            $data['checklist'] = $checklist;
        }
        if ($this->input->post('remarks') !== null) {
            $data['remarks'] = $this->input->post('remarks');
        }

        $this->todo_model->update_task($id, $data);

        // Sync assignees if provided
        $assignee_ids = $this->input->post('assignee_ids');
        if ($assignee_ids !== null) {
            $this->todo_model->sync_assignees($id, is_array($assignee_ids) ? $assignee_ids : []);
        }
        // Sync roles if provided
        $role_ids = $this->input->post('role_ids');
        if ($role_ids !== null) {
            $this->todo_model->sync_roles($id, is_array($role_ids) ? $role_ids : []);
        }

        echo json_encode(['success' => true, 'message' => 'Task updated']);
    }

    /**
     * Toggle task status (complete/incomplete)
     */
    public function ajax_toggle_task()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $id     = $this->input->post('id');
        $status = $this->input->post('status');

        $this->todo_model->toggle_task_status($id, $status);
        echo json_encode(['success' => true]);
    }

    /**
     * Delete task via AJAX
     */
    public function ajax_delete_task()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }
        if (!staff_can('delete', 'todo') && !is_admin()) {
            echo json_encode(['success' => false, 'message' => 'Permission denied']);
            return;
        }

        $id = $this->input->post('id');
        $result = $this->todo_model->delete_task($id);
        echo json_encode(['success' => $result]);
    }

    // ═══════════════════════════════════════════
    //  AJAX ENDPOINTS — CHECKLIST
    // ═══════════════════════════════════════════

    /**
     * Toggle checklist item
     */
    public function ajax_toggle_checklist()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $id         = $this->input->post('id');
        $is_checked = $this->input->post('is_checked');

        $this->todo_model->toggle_checklist_item($id, $is_checked);
        echo json_encode(['success' => true]);
    }

    /**
     * Add checklist item
     */
    public function ajax_add_checklist_item()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $task_id = $this->input->post('task_id');
        $title   = $this->input->post('title');

        $id = $this->todo_model->add_checklist_item($task_id, $title);
        echo json_encode(['success' => !!$id, 'id' => $id]);
    }

    /**
     * Delete checklist item
     */
    public function ajax_delete_checklist_item()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $id = $this->input->post('id');
        $result = $this->todo_model->delete_checklist_item($id);
        echo json_encode(['success' => $result]);
    }

    /**
     * Reorder checklist items (drag & drop)
     */
    public function ajax_reorder_checklist()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $order = $this->input->post('order');
        if (is_array($order)) {
            $this->todo_model->reorder_checklist($order);
        }
        echo json_encode(['success' => true]);
    }

    /**
     * Update checklist item title (inline edit)
     */
    public function ajax_update_checklist_title()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $id    = $this->input->post('id');
        $title = $this->input->post('title');

        if (empty($title)) {
            echo json_encode(['success' => false, 'message' => 'Title cannot be empty']);
            return;
        }

        $result = $this->todo_model->update_checklist_title($id, $title);
        echo json_encode(['success' => $result]);
    }

    // ═══════════════════════════════════════════
    //  AJAX ENDPOINTS — REMARKS
    // ═══════════════════════════════════════════

    /**
     * Add a remark to a task
     */
    public function ajax_add_remark()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $task_id = $this->input->post('task_id');
        $remark  = $this->input->post('remark');

        if (empty($remark)) {
            echo json_encode(['success' => false, 'message' => 'Remark cannot be empty']);
            return;
        }

        $result = $this->todo_model->add_remark($task_id, get_staff_user_id(), $remark);
        if ($result) {
            echo json_encode(['success' => true, 'remark' => $result]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add remark']);
        }
    }

    /**
     * Delete a remark
     */
    public function ajax_delete_remark()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $id = $this->input->post('id');
        $result = $this->todo_model->delete_remark($id);
        echo json_encode(['success' => $result]);
    }

    // ═══════════════════════════════════════════
    //  AJAX ENDPOINTS — CATEGORIES
    // ═══════════════════════════════════════════

    /**
     * Add category
     */
    public function ajax_add_category()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $data = [
            'name'     => $this->input->post('name'),
            'color'    => $this->input->post('color') ?: '#6366f1',
            'icon'     => $this->input->post('icon') ?: 'fa-folder',
            'staff_id' => get_staff_user_id(),
        ];

        $id = $this->todo_model->add_category($data);
        if ($id) {
            $category = $this->todo_model->get_category($id);
            echo json_encode(['success' => true, 'category' => $category]);
        } else {
            echo json_encode(['success' => false]);
        }
    }

    /**
     * Update category
     */
    public function ajax_update_category()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $id   = $this->input->post('id');
        $data = [
            'name'  => $this->input->post('name'),
            'color' => $this->input->post('color'),
        ];

        $this->todo_model->update_category($id, $data);
        echo json_encode(['success' => true]);
    }

    /**
     * Delete category
     */
    public function ajax_delete_category()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $id = $this->input->post('id');
        $result = $this->todo_model->delete_category($id);
        echo json_encode(['success' => $result]);
    }

    // ═══════════════════════════════════════════
    //  AJAX ENDPOINTS — TEMPLATES
    // ═══════════════════════════════════════════

    /**
     * Get all templates
     */
    public function ajax_get_templates()
    {
        if (!$this->input->is_ajax_request()) { show_404(); }
        $templates = $this->todo_model->get_templates();
        echo json_encode(['success' => true, 'templates' => $templates]);
    }

    /**
     * Add template
     */
    public function ajax_add_template()
    {
        if (!$this->input->is_ajax_request()) { show_404(); }

        $data = [
            'name'             => $this->input->post('name'),
            'description'      => $this->input->post('description'),
            'category_id'      => $this->input->post('category_id') ?: null,
            'priority'         => $this->input->post('priority') ?: 1,
            'due_days'         => $this->input->post('due_days') ?: null,
            'checklist_json'   => $this->input->post('checklist_json'),
            'assignee_ids_json'=> $this->input->post('assignee_ids_json'),
            'role_ids_json'    => $this->input->post('role_ids_json'),
            'is_recurring'     => $this->input->post('is_recurring') ?: 0,
            'recurring_type'   => $this->input->post('recurring_type'),
            'repeat_every'     => $this->input->post('repeat_every') ?: 1,
            'is_active'        => 1,
            'created_by'       => get_staff_user_id(),
        ];

        $id = $this->todo_model->add_template($data);
        echo json_encode(['success' => (bool)$id, 'id' => $id]);
    }

    /**
     * Update template
     */
    public function ajax_update_template()
    {
        if (!$this->input->is_ajax_request()) { show_404(); }

        $id = $this->input->post('id');
        $data = [
            'name'             => $this->input->post('name'),
            'description'      => $this->input->post('description'),
            'category_id'      => $this->input->post('category_id') ?: null,
            'priority'         => $this->input->post('priority') ?: 1,
            'due_days'         => $this->input->post('due_days') ?: null,
            'checklist_json'   => $this->input->post('checklist_json'),
            'assignee_ids_json'=> $this->input->post('assignee_ids_json'),
            'role_ids_json'    => $this->input->post('role_ids_json'),
            'is_recurring'     => $this->input->post('is_recurring') ?: 0,
            'recurring_type'   => $this->input->post('recurring_type'),
            'repeat_every'     => $this->input->post('repeat_every') ?: 1,
            'is_active'        => $this->input->post('is_active') !== null ? $this->input->post('is_active') : 1,
        ];

        $result = $this->todo_model->update_template($id, $data);
        echo json_encode(['success' => $result]);
    }

    /**
     * Delete template
     */
    public function ajax_delete_template()
    {
        if (!$this->input->is_ajax_request()) { show_404(); }
        $id = $this->input->post('id');
        $result = $this->todo_model->delete_template($id);
        echo json_encode(['success' => $result]);
    }

    /**
     * Use template — create task instantly from a template
     */
    public function ajax_use_template()
    {
        if (!$this->input->is_ajax_request()) { show_404(); }
        $id = $this->input->post('template_id');
        $task_id = $this->todo_model->create_task_from_template($id);
        echo json_encode(['success' => (bool)$task_id, 'task_id' => $task_id]);
    }
}
