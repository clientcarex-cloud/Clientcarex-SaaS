<?php

defined('BASEPATH') or exit('No direct script access allowed');

// ── Categories Table ──
if (!$CI->db->table_exists(db_prefix() . 'todo_categories')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'todo_categories` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `name` varchar(150) NOT NULL,
      `color` varchar(20) DEFAULT "#6366f1",
      `icon` varchar(50) DEFAULT "fa-folder",
      `sort_order` int(11) DEFAULT 0,
      `staff_id` int(11) NOT NULL,
      `datecreated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `staff_id` (`staff_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ';');
}

// ── Tasks Table ──
if (!$CI->db->table_exists(db_prefix() . 'todo_tasks')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'todo_tasks` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `title` varchar(500) NOT NULL,
      `description` text DEFAULT NULL,
      `category_id` int(11) DEFAULT NULL,
      `priority` tinyint(1) DEFAULT 1 COMMENT "1=low,2=medium,3=high,4=urgent",
      `status` tinyint(1) DEFAULT 0 COMMENT "0=pending,1=in_progress,2=completed",
      `due_date` date DEFAULT NULL,
      `sort_order` int(11) DEFAULT 0,
      `staff_id` int(11) NOT NULL,
      `datecreated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `dateupdated` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
      `remarks` text DEFAULT NULL,
      PRIMARY KEY (`id`),
      KEY `staff_id` (`staff_id`),
      KEY `category_id` (`category_id`),
      KEY `status` (`status`),
      KEY `priority` (`priority`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ';');
}

// ── Checklist Items Table ──
if (!$CI->db->table_exists(db_prefix() . 'todo_checklist')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'todo_checklist` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `task_id` int(11) NOT NULL,
      `title` varchar(500) NOT NULL,
      `is_checked` tinyint(1) DEFAULT 0,
      `sort_order` int(11) DEFAULT 0,
      `datecreated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `task_id` (`task_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ';');
}

// ── Task Remarks Table ──
if (!$CI->db->table_exists(db_prefix() . 'todo_remarks')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'todo_remarks` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `task_id` int(11) NOT NULL,
      `staff_id` int(11) NOT NULL,
      `remark` text NOT NULL,
      `datecreated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `task_id` (`task_id`),
      KEY `staff_id` (`staff_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ';');
}

// ── Task Assignees Table (multi-staff) ──
if (!$CI->db->table_exists(db_prefix() . 'todo_task_assignees')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'todo_task_assignees` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `task_id` int(11) NOT NULL,
      `staff_id` int(11) NOT NULL,
      `datecreated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `task_staff` (`task_id`, `staff_id`),
      KEY `task_id` (`task_id`),
      KEY `staff_id` (`staff_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ';');
}

// ── Task Roles Table (role-based assignment) ──
if (!$CI->db->table_exists(db_prefix() . 'todo_task_roles')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'todo_task_roles` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `task_id` int(11) NOT NULL,
      `role_id` int(11) NOT NULL,
      `datecreated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `task_role` (`task_id`, `role_id`),
      KEY `task_id` (`task_id`),
      KEY `role_id` (`role_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ';');
}

// ── Score Log Table (BNI-style incentive scoring) ──
if (!$CI->db->table_exists(db_prefix() . 'todo_score_log')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'todo_score_log` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ';');
}

// ── Attendance Table (daily punctuality tracking) ──
if (!$CI->db->table_exists(db_prefix() . 'todo_attendance')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'todo_attendance` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `staff_id` int(11) NOT NULL,
      `attend_date` date NOT NULL,
      `status` tinyint(1) DEFAULT 1 COMMENT "1=present, 0=absent, 2=late",
      `points` decimal(6,1) DEFAULT 5,
      `marked_by` int(11) NOT NULL,
      `datecreated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `staff_date` (`staff_id`, `attend_date`),
      KEY `attend_date` (`attend_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ';');
}

// ── Add date_completed to tasks table if missing ──
if ($CI->db->table_exists(db_prefix() . 'todo_tasks')) {
    if (!$CI->db->field_exists('date_completed', db_prefix() . 'todo_tasks')) {
        $CI->db->query('ALTER TABLE `' . db_prefix() . 'todo_tasks` ADD `date_completed` datetime DEFAULT NULL AFTER `dateupdated`');
    }
    // Relation link (e.g. Pro Tickets attaches follow-up tasks to tickets)
    if (!$CI->db->field_exists('rel_type', db_prefix() . 'todo_tasks')) {
        $CI->db->query('ALTER TABLE `' . db_prefix() . 'todo_tasks` ADD `rel_type` varchar(30) DEFAULT NULL, ADD `rel_id` int(11) DEFAULT NULL, ADD KEY `rel` (`rel_type`, `rel_id`)');
    }
}

// ── Task Templates Table (predefined + recurring) ──
if (!$CI->db->table_exists(db_prefix() . 'todo_templates')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'todo_templates` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `name` varchar(500) NOT NULL,
      `description` text DEFAULT NULL,
      `category_id` int(11) DEFAULT NULL,
      `priority` tinyint(1) DEFAULT 1,
      `due_days` int(11) DEFAULT NULL COMMENT "Days from creation to due date",
      `checklist_json` text DEFAULT NULL COMMENT "JSON array of checklist titles",
      `assignee_ids_json` text DEFAULT NULL COMMENT "JSON array of staff IDs",
      `role_ids_json` text DEFAULT NULL COMMENT "JSON array of role IDs",
      `is_recurring` tinyint(1) DEFAULT 0,
      `recurring_type` varchar(20) DEFAULT NULL COMMENT "day,week,month",
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
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ';');
}
