<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Todo
Description: A modern, single-page task management system with checklists, categories, priorities, and more.
Version: 1.0.0
Requires at least: 2.3.*
*/

define('TODO_MODULE_NAME', 'todo');

hooks()->add_action('admin_init', 'todo_module_init_menu_items');
hooks()->add_action('admin_init', 'todo_permissions');
hooks()->add_action('after_cron_run', 'todo_process_recurring_tasks');

/**
 * Cron hook — process recurring todo templates
 */
function todo_process_recurring_tasks()
{
    $CI = &get_instance();
    $CI->load->model('todo/todo_model');
    $CI->todo_model->process_recurring_templates();
}

/**
 * Register staff permissions for the Todo module
 */
function todo_permissions()
{
    $capabilities = [];

    $capabilities['capabilities'] = [
        'view'   => _l('permission_view') . '(' . _l('permission_global') . ')',
        'create' => _l('permission_create'),
        'edit'   => _l('permission_edit'),
        'delete' => _l('permission_delete'),
    ];

    register_staff_capabilities('todo', $capabilities, 'Todo');
}

/**
 * Register activation module hook
 */
register_activation_hook(TODO_MODULE_NAME, 'todo_module_activation_hook');

function todo_module_activation_hook()
{
    $CI = &get_instance();
    require_once(__DIR__ . '/install.php');
}

/**
 * Register language files
 */
register_language_files(TODO_MODULE_NAME, [TODO_MODULE_NAME]);

/**
 * Init todo module menu items in setup in admin_init hook
 */
function todo_module_init_menu_items()
{
    $CI = &get_instance();

    if (staff_can('view', 'todo') || staff_can('create', 'todo') || staff_can('edit', 'todo') || is_admin()) {
        $CI->app_menu->add_sidebar_menu_item('todo', [
            'name'     => 'Todo',
            'href'     => admin_url('todo'),
            'position' => 26,
            'icon'     => 'fa-solid fa-list-check',
        ]);
    }
}

/* ─────────────────────── Admin dashboard widget ─────────────────────────── */

hooks()->add_action('after_dashboard', 'todo_dashboard_widget', 5);

/**
 * Action-oriented Todo widget on the main admin dashboard: overdue / due-today
 * alerts, status + due-load charts, and a "needs attention" task list. Data is
 * scoped with the same visibility rules as the module itself (creator,
 * assignee or assigned role; admins see everything).
 */
function todo_dashboard_widget()
{
    if (!staff_can('view', 'todo') && !staff_can('create', 'todo') && !staff_can('edit', 'todo') && !is_admin()) {
        return;
    }

    $CI = &get_instance();

    // Never let a widget failure blank the whole admin dashboard.
    try {
        $p = db_prefix();
        if (!$CI->db->table_exists($p . 'todo_tasks')) {
            return;
        }

        $CI->load->model('todo/todo_model');
        $staff_id = get_staff_user_id();
        $stats    = $CI->todo_model->get_stats($staff_id);

        // Visibility clause (matches Todo_model::get_stats()).
        $vis = '';
        if (!is_admin()) {
            $sid  = (int) $staff_id;
            $row  = $CI->db->select('role')->where('staffid', $sid)->get($p . 'staff')->row();
            $role = $row ? (int) $row->role : 0;
            $vis  = " AND (t.staff_id = {$sid}
                OR t.id IN (SELECT task_id FROM {$p}todo_task_assignees WHERE staff_id = {$sid})
                " . ($role ? "OR t.id IN (SELECT task_id FROM {$p}todo_task_roles WHERE role_id = {$role})" : '') . ')';
        }

        // Open tasks that need action now: overdue, due today, or urgent.
        $attention = $CI->db->query(
            "SELECT t.id, t.title, t.due_date, t.priority, t.status,
                    c.name AS category_name, c.color AS category_color
             FROM {$p}todo_tasks t
             LEFT JOIN {$p}todo_categories c ON c.id = t.category_id
             WHERE t.status != 2 {$vis}
               AND ((t.due_date IS NOT NULL AND t.due_date <= CURDATE()) OR t.priority = 4)
             ORDER BY (t.due_date IS NULL) ASC, t.due_date ASC, t.priority DESC
             LIMIT 7"
        )->result();

        // Due-load for the next 7 days (open tasks only).
        $due_rows = $CI->db->query(
            "SELECT t.due_date AS d, COUNT(*) AS cnt
             FROM {$p}todo_tasks t
             WHERE t.status != 2 {$vis}
               AND t.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 6 DAY)
             GROUP BY t.due_date"
        )->result();

        $due_map = [];
        foreach ($due_rows as $r) {
            $due_map[$r->d] = (int) $r->cnt;
        }
        $due_labels = ['Overdue'];
        $due_counts = [(int) $stats['overdue']];
        for ($i = 0; $i <= 6; $i++) {
            $d            = date('Y-m-d', strtotime("+{$i} days"));
            $due_labels[] = $i === 0 ? 'Today' : date('D', strtotime($d));
            $due_counts[] = $due_map[$d] ?? 0;
        }

        $CI->load->view('todo/dashboard_widget', [
            'stats'      => $stats,
            'attention'  => $attention,
            'due_labels' => $due_labels,
            'due_counts' => $due_counts,
        ]);
    } catch (Throwable $e) {
        log_activity('Todo dashboard widget failed to render [' . $e->getMessage() . ']');
    }
}
