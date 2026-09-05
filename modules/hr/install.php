<?php

defined('BASEPATH') or exit('No direct script access allowed');

$CI = &get_instance();
$charset = $CI->db->char_set;

// ---------------------------------------------------------------- employees
if (!$CI->db->table_exists(db_prefix() . 'hr_employees')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'hr_employees` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `staff_id` INT(11) NOT NULL,
        `employee_code` VARCHAR(50) DEFAULT NULL,
        `department_id` INT(11) DEFAULT NULL,
        `designation_id` INT(11) DEFAULT NULL,
        `employment_type` VARCHAR(30) DEFAULT "permanent",
        `date_of_joining` DATE DEFAULT NULL,
        `probation_end` DATE DEFAULT NULL,
        `work_location` VARCHAR(150) DEFAULT NULL,
        `reporting_to` INT(11) DEFAULT NULL,
        `date_of_birth` DATE DEFAULT NULL,
        `gender` VARCHAR(15) DEFAULT NULL,
        `blood_group` VARCHAR(10) DEFAULT NULL,
        `marital_status` VARCHAR(15) DEFAULT NULL,
        `father_name` VARCHAR(150) DEFAULT NULL,
        `personal_email` VARCHAR(150) DEFAULT NULL,
        `alt_phone` VARCHAR(30) DEFAULT NULL,
        `present_address` TEXT DEFAULT NULL,
        `permanent_address` TEXT DEFAULT NULL,
        `emergency_contact_name` VARCHAR(150) DEFAULT NULL,
        `emergency_contact_relation` VARCHAR(50) DEFAULT NULL,
        `emergency_contact_phone` VARCHAR(30) DEFAULT NULL,
        `national_id` VARCHAR(50) DEFAULT NULL,
        `pan_number` VARCHAR(30) DEFAULT NULL,
        `bank_name` VARCHAR(100) DEFAULT NULL,
        `bank_branch` VARCHAR(100) DEFAULT NULL,
        `bank_account_no` VARCHAR(50) DEFAULT NULL,
        `bank_ifsc` VARCHAR(30) DEFAULT NULL,
        `pf_uan` VARCHAR(50) DEFAULT NULL,
        `esi_number` VARCHAR(50) DEFAULT NULL,
        `basic_salary` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `qualifications` TEXT DEFAULT NULL,
        `notes` TEXT DEFAULT NULL,
        `status` VARCHAR(20) NOT NULL DEFAULT "active",
        `created_at` DATETIME DEFAULT NULL,
        `updated_at` DATETIME DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_hr_emp_staff` (`staff_id`),
        KEY `idx_hr_emp_dept` (`department_id`),
        KEY `idx_hr_emp_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $charset . ';');
}

// ------------------------------------------------------------- designations
if (!$CI->db->table_exists(db_prefix() . 'hr_designations')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'hr_designations` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(150) NOT NULL,
        `department_id` INT(11) DEFAULT NULL,
        `role_id` INT(11) DEFAULT NULL,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $charset . ';');
}

// ------------------------------------------------------------------- shifts
if (!$CI->db->table_exists(db_prefix() . 'hr_shifts')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'hr_shifts` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(100) NOT NULL,
        `start_time` TIME NOT NULL DEFAULT "09:00:00",
        `end_time` TIME NOT NULL DEFAULT "17:00:00",
        `break_minutes` INT(11) NOT NULL DEFAULT 0,
        `grace_minutes` INT(11) NOT NULL DEFAULT 10,
        `week_off_days` VARCHAR(20) NOT NULL DEFAULT "0",
        `is_default` TINYINT(1) NOT NULL DEFAULT 0,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $charset . ';');
}

if (!$CI->db->table_exists(db_prefix() . 'hr_shift_assignments')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'hr_shift_assignments` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `staff_id` INT(11) NOT NULL,
        `shift_id` INT(11) NOT NULL,
        `effective_from` DATE NOT NULL,
        `created_at` DATETIME DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_hr_shift_asg_staff` (`staff_id`, `effective_from`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $charset . ';');
}

// --------------------------------------------------------------- attendance
if (!$CI->db->table_exists(db_prefix() . 'hr_attendance')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'hr_attendance` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `staff_id` INT(11) NOT NULL,
        `att_date` DATE NOT NULL,
        `status` VARCHAR(15) NOT NULL DEFAULT "present",
        `check_in` TIME DEFAULT NULL,
        `check_out` TIME DEFAULT NULL,
        `work_minutes` INT(11) NOT NULL DEFAULT 0,
        `is_late` TINYINT(1) NOT NULL DEFAULT 0,
        `note` VARCHAR(255) DEFAULT NULL,
        `marked_by` INT(11) DEFAULT NULL,
        `created_at` DATETIME DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_hr_att` (`staff_id`, `att_date`),
        KEY `idx_hr_att_date` (`att_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $charset . ';');
}

// ----------------------------------------------------------------- holidays
if (!$CI->db->table_exists(db_prefix() . 'hr_holidays')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'hr_holidays` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(150) NOT NULL,
        `holiday_date` DATE NOT NULL,
        `is_optional` TINYINT(1) NOT NULL DEFAULT 0,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (`id`),
        KEY `idx_hr_holiday_date` (`holiday_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $charset . ';');
}

// -------------------------------------------------------------------- leave
if (!$CI->db->table_exists(db_prefix() . 'hr_leave_types')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'hr_leave_types` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(100) NOT NULL,
        `code` VARCHAR(10) NOT NULL,
        `days_per_year` DECIMAL(5,1) NOT NULL DEFAULT 0,
        `is_paid` TINYINT(1) NOT NULL DEFAULT 1,
        `carry_forward` TINYINT(1) NOT NULL DEFAULT 0,
        `color` VARCHAR(10) NOT NULL DEFAULT "#7c3aed",
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $charset . ';');
}

if (!$CI->db->table_exists(db_prefix() . 'hr_leave_allocations')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'hr_leave_allocations` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `staff_id` INT(11) NOT NULL,
        `leave_type_id` INT(11) NOT NULL,
        `year` INT(11) NOT NULL,
        `allocated` DECIMAL(5,1) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_hr_leave_alloc` (`staff_id`, `leave_type_id`, `year`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $charset . ';');
}

if (!$CI->db->table_exists(db_prefix() . 'hr_leave_requests')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'hr_leave_requests` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `staff_id` INT(11) NOT NULL,
        `leave_type_id` INT(11) NOT NULL,
        `from_date` DATE NOT NULL,
        `to_date` DATE NOT NULL,
        `days` DECIMAL(5,1) NOT NULL DEFAULT 1,
        `is_half_day` TINYINT(1) NOT NULL DEFAULT 0,
        `reason` TEXT DEFAULT NULL,
        `status` VARCHAR(15) NOT NULL DEFAULT "pending",
        `approved_by` INT(11) DEFAULT NULL,
        `action_note` VARCHAR(255) DEFAULT NULL,
        `action_at` DATETIME DEFAULT NULL,
        `created_at` DATETIME DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_hr_leave_req_staff` (`staff_id`, `status`),
        KEY `idx_hr_leave_req_dates` (`from_date`, `to_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $charset . ';');
}

// ------------------------------------------------------------------ payroll
if (!$CI->db->table_exists(db_prefix() . 'hr_salary_components')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'hr_salary_components` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(100) NOT NULL,
        `type` VARCHAR(15) NOT NULL DEFAULT "earning",
        `calc_type` VARCHAR(20) NOT NULL DEFAULT "fixed",
        `default_value` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `sort_order` INT(11) NOT NULL DEFAULT 0,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $charset . ';');
}

if (!$CI->db->table_exists(db_prefix() . 'hr_salary_structures')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'hr_salary_structures` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `staff_id` INT(11) NOT NULL,
        `component_id` INT(11) NOT NULL,
        `value` DECIMAL(15,2) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_hr_sal_struct` (`staff_id`, `component_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $charset . ';');
}

if (!$CI->db->table_exists(db_prefix() . 'hr_payroll_runs')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'hr_payroll_runs` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `month` INT(11) NOT NULL,
        `year` INT(11) NOT NULL,
        `status` VARCHAR(15) NOT NULL DEFAULT "draft",
        `remarks` VARCHAR(255) DEFAULT NULL,
        `generated_by` INT(11) DEFAULT NULL,
        `created_at` DATETIME DEFAULT NULL,
        `finalized_at` DATETIME DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_hr_payroll_period` (`month`, `year`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $charset . ';');
}

if (!$CI->db->table_exists(db_prefix() . 'hr_payslips')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'hr_payslips` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `run_id` INT(11) NOT NULL,
        `staff_id` INT(11) NOT NULL,
        `employee_code` VARCHAR(50) DEFAULT NULL,
        `basic` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `earnings` TEXT DEFAULT NULL,
        `deductions` TEXT DEFAULT NULL,
        `gross` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `total_deductions` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `payable_days` DECIMAL(5,1) NOT NULL DEFAULT 0,
        `lop_days` DECIMAL(5,1) NOT NULL DEFAULT 0,
        `lop_amount` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `net_pay` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `status` VARCHAR(15) NOT NULL DEFAULT "unpaid",
        `paid_date` DATE DEFAULT NULL,
        `payment_mode` VARCHAR(50) DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_hr_payslip` (`run_id`, `staff_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $charset . ';');
}

// ---------------------------------------------------------------- documents
if (!$CI->db->table_exists(db_prefix() . 'hr_documents')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'hr_documents` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `staff_id` INT(11) NOT NULL,
        `doc_type` VARCHAR(50) DEFAULT NULL,
        `title` VARCHAR(191) NOT NULL,
        `file_name` VARCHAR(191) NOT NULL,
        `issue_date` DATE DEFAULT NULL,
        `expiry_date` DATE DEFAULT NULL,
        `uploaded_by` INT(11) DEFAULT NULL,
        `created_at` DATETIME DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_hr_doc_staff` (`staff_id`),
        KEY `idx_hr_doc_expiry` (`expiry_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $charset . ';');
}

// ---------------------------------------------------------------- trainings
if (!$CI->db->table_exists(db_prefix() . 'hr_trainings')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'hr_trainings` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `title` VARCHAR(191) NOT NULL,
        `trainer` VARCHAR(150) DEFAULT NULL,
        `category` VARCHAR(100) DEFAULT NULL,
        `start_date` DATE DEFAULT NULL,
        `end_date` DATE DEFAULT NULL,
        `department_id` INT(11) DEFAULT NULL,
        `venue` VARCHAR(191) DEFAULT NULL,
        `description` TEXT DEFAULT NULL,
        `status` VARCHAR(15) NOT NULL DEFAULT "planned",
        `created_by` INT(11) DEFAULT NULL,
        `created_at` DATETIME DEFAULT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $charset . ';');
}

if (!$CI->db->table_exists(db_prefix() . 'hr_training_attendees')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'hr_training_attendees` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `training_id` INT(11) NOT NULL,
        `staff_id` INT(11) NOT NULL,
        `attendee_status` VARCHAR(15) NOT NULL DEFAULT "enrolled",
        `score` VARCHAR(20) DEFAULT NULL,
        `remarks` VARCHAR(255) DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_hr_training_att` (`training_id`, `staff_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $charset . ';');
}

// --------------------------------------------------------------- appraisals
if (!$CI->db->table_exists(db_prefix() . 'hr_appraisals')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'hr_appraisals` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `staff_id` INT(11) NOT NULL,
        `period_from` DATE DEFAULT NULL,
        `period_to` DATE DEFAULT NULL,
        `reviewer_id` INT(11) DEFAULT NULL,
        `ratings` TEXT DEFAULT NULL,
        `overall_rating` DECIMAL(3,1) NOT NULL DEFAULT 0,
        `strengths` TEXT DEFAULT NULL,
        `improvements` TEXT DEFAULT NULL,
        `goals` TEXT DEFAULT NULL,
        `status` VARCHAR(15) NOT NULL DEFAULT "draft",
        `created_at` DATETIME DEFAULT NULL,
        `completed_at` DATETIME DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_hr_appraisal_staff` (`staff_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $charset . ';');
}

// -------------------------------------------------------------------- exits
if (!$CI->db->table_exists(db_prefix() . 'hr_exits')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'hr_exits` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `staff_id` INT(11) NOT NULL,
        `exit_type` VARCHAR(30) NOT NULL DEFAULT "resignation",
        `notice_date` DATE DEFAULT NULL,
        `last_working_day` DATE DEFAULT NULL,
        `reason` TEXT DEFAULT NULL,
        `clearance` TEXT DEFAULT NULL,
        `settlement_amount` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `settlement_note` VARCHAR(255) DEFAULT NULL,
        `status` VARCHAR(15) NOT NULL DEFAULT "pending",
        `created_by` INT(11) DEFAULT NULL,
        `created_at` DATETIME DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_hr_exit_staff` (`staff_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $charset . ';');
}

// -------------------------------------------------- suggestions / feedback
if (!$CI->db->table_exists(db_prefix() . 'hr_feedback')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'hr_feedback` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `staff_id` INT(11) NOT NULL,
        `category` VARCHAR(20) NOT NULL DEFAULT "suggestion",
        `subject` VARCHAR(191) NOT NULL,
        `message` TEXT NOT NULL,
        `is_anonymous` TINYINT(1) NOT NULL DEFAULT 0,
        `status` VARCHAR(15) NOT NULL DEFAULT "new",
        `admin_reply` TEXT DEFAULT NULL,
        `replied_by` INT(11) DEFAULT NULL,
        `replied_at` DATETIME DEFAULT NULL,
        `created_at` DATETIME DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_hr_feedback_staff` (`staff_id`),
        KEY `idx_hr_feedback_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $charset . ';');
}

// ----------------------------------------------------------------- notices
if (!$CI->db->table_exists(db_prefix() . 'hr_notices')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'hr_notices` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `title` VARCHAR(191) NOT NULL,
        `message` TEXT NOT NULL,
        `audience_type` VARCHAR(20) NOT NULL DEFAULT "all",
        `role_ids` VARCHAR(255) DEFAULT NULL,
        `staff_ids` TEXT DEFAULT NULL,
        `priority` VARCHAR(10) NOT NULL DEFAULT "normal",
        `start_date` DATE DEFAULT NULL,
        `end_date` DATE DEFAULT NULL,
        `active` TINYINT(1) NOT NULL DEFAULT 1,
        `created_by` INT(11) DEFAULT NULL,
        `date_created` DATETIME DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_hr_notice_active` (`active`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $charset . ';');
}

// -------------------------------------------------------------------- seeds
if ($CI->db->count_all(db_prefix() . 'hr_leave_types') == 0) {
    $CI->db->insert_batch(db_prefix() . 'hr_leave_types', [
        ['name' => 'Casual Leave', 'code' => 'CL', 'days_per_year' => 12, 'is_paid' => 1, 'carry_forward' => 0, 'color' => '#7c3aed'],
        ['name' => 'Sick Leave', 'code' => 'SL', 'days_per_year' => 12, 'is_paid' => 1, 'carry_forward' => 0, 'color' => '#dc2626'],
        ['name' => 'Earned Leave', 'code' => 'EL', 'days_per_year' => 15, 'is_paid' => 1, 'carry_forward' => 1, 'color' => '#0891b2'],
        ['name' => 'Maternity Leave', 'code' => 'ML', 'days_per_year' => 182, 'is_paid' => 1, 'carry_forward' => 0, 'color' => '#db2777'],
        ['name' => 'Paternity Leave', 'code' => 'PL', 'days_per_year' => 15, 'is_paid' => 1, 'carry_forward' => 0, 'color' => '#2563eb'],
        ['name' => 'Leave Without Pay', 'code' => 'LWP', 'days_per_year' => 0, 'is_paid' => 0, 'carry_forward' => 0, 'color' => '#64748b'],
    ]);
}

if ($CI->db->count_all(db_prefix() . 'hr_salary_components') == 0) {
    $CI->db->insert_batch(db_prefix() . 'hr_salary_components', [
        ['name' => 'House Rent Allowance', 'type' => 'earning', 'calc_type' => 'percent_basic', 'default_value' => 40, 'sort_order' => 1],
        ['name' => 'Conveyance Allowance', 'type' => 'earning', 'calc_type' => 'fixed', 'default_value' => 1600, 'sort_order' => 2],
        ['name' => 'Medical Allowance', 'type' => 'earning', 'calc_type' => 'fixed', 'default_value' => 1250, 'sort_order' => 3],
        ['name' => 'Special Allowance', 'type' => 'earning', 'calc_type' => 'fixed', 'default_value' => 0, 'sort_order' => 4],
        ['name' => 'Provident Fund', 'type' => 'deduction', 'calc_type' => 'percent_basic', 'default_value' => 12, 'sort_order' => 5],
        ['name' => 'ESI', 'type' => 'deduction', 'calc_type' => 'percent_basic', 'default_value' => 0.75, 'sort_order' => 6],
        ['name' => 'Professional Tax', 'type' => 'deduction', 'calc_type' => 'fixed', 'default_value' => 200, 'sort_order' => 7],
    ]);
}

if ($CI->db->count_all(db_prefix() . 'hr_shifts') == 0) {
    $CI->db->insert_batch(db_prefix() . 'hr_shifts', [
        ['name' => 'General Shift', 'start_time' => '09:00:00', 'end_time' => '17:00:00', 'break_minutes' => 30, 'grace_minutes' => 10, 'week_off_days' => '0', 'is_default' => 1],
        ['name' => 'Morning Shift', 'start_time' => '07:00:00', 'end_time' => '15:00:00', 'break_minutes' => 30, 'grace_minutes' => 10, 'week_off_days' => '0', 'is_default' => 0],
        ['name' => 'Evening Shift', 'start_time' => '15:00:00', 'end_time' => '23:00:00', 'break_minutes' => 30, 'grace_minutes' => 10, 'week_off_days' => '0', 'is_default' => 0],
        ['name' => 'Night Shift', 'start_time' => '23:00:00', 'end_time' => '07:00:00', 'break_minutes' => 30, 'grace_minutes' => 15, 'week_off_days' => '0', 'is_default' => 0],
    ]);
}

if ($CI->db->count_all(db_prefix() . 'hr_designations') == 0) {
    $CI->db->insert_batch(db_prefix() . 'hr_designations', [
        ['name' => 'Medical Superintendent'], ['name' => 'Senior Consultant'], ['name' => 'Consultant'],
        ['name' => 'Resident Doctor'], ['name' => 'Nursing Superintendent'], ['name' => 'Head Nurse'],
        ['name' => 'Staff Nurse'], ['name' => 'Lab Technician'], ['name' => 'Radiographer'],
        ['name' => 'Pharmacist'], ['name' => 'Physiotherapist'], ['name' => 'Front Desk Executive'],
        ['name' => 'Billing Executive'], ['name' => 'HR Manager'], ['name' => 'HR Executive'],
        ['name' => 'Accountant'], ['name' => 'Housekeeping Supervisor'], ['name' => 'Ward Boy'],
        ['name' => 'Security Guard'], ['name' => 'Ambulance Driver'],
    ]);
}

// ------------------------------------------------------------- benefits
if (!$CI->db->table_exists(db_prefix() . 'hr_benefits')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'hr_benefits` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(191) NOT NULL,
        `description` TEXT DEFAULT NULL,
        `benefit_type` VARCHAR(20) NOT NULL DEFAULT "standard",
        `vesting_years` DECIMAL(4,1) NOT NULL DEFAULT 0,
        `value_label` VARCHAR(191) DEFAULT NULL,
        `icon` VARCHAR(40) DEFAULT "fa-gift",
        `color` VARCHAR(20) DEFAULT "#4f46e5",
        `applies_to` VARCHAR(20) NOT NULL DEFAULT "all",
        `role_ids` TEXT DEFAULT NULL,
        `staff_ids` TEXT DEFAULT NULL,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `sort_order` INT(11) NOT NULL DEFAULT 0,
        `created_at` DATETIME DEFAULT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $charset . ';');
}

$hr_now = date('Y-m-d H:i:s');
$hr_default_benefits = [
    ['name' => 'Gratuity', 'description' => 'Lump-sum reward for long service, payable after 5 years of continuous service (15 days of last-drawn salary per completed year).', 'benefit_type' => 'vesting', 'vesting_years' => 5, 'value_label' => '15 days salary / year', 'icon' => 'fa-gift', 'color' => '#7c3aed', 'applies_to' => 'all', 'sort_order' => 10],
    ['name' => 'Provident Fund (PF)', 'description' => 'Retirement savings — 12% of basic contributed by both employee and employer to your EPF account.', 'benefit_type' => 'standard', 'vesting_years' => 0, 'value_label' => '12% of basic', 'icon' => 'fa-university', 'color' => '#0891b2', 'applies_to' => 'all', 'sort_order' => 20],
    ['name' => 'Health Insurance', 'description' => 'Family floater medical insurance covering hospitalisation for you and dependents.', 'benefit_type' => 'standard', 'vesting_years' => 0, 'value_label' => 'Up to 5,00,000 cover', 'icon' => 'fa-heartbeat', 'color' => '#dc2626', 'applies_to' => 'all', 'sort_order' => 30],
    ['name' => 'Employee State Insurance (ESI)', 'description' => 'Medical and cash benefits under the ESI scheme for eligible employees.', 'benefit_type' => 'standard', 'vesting_years' => 0, 'value_label' => 'As per ESIC', 'icon' => 'fa-medkit', 'color' => '#16a34a', 'applies_to' => 'all', 'sort_order' => 40],
    ['name' => 'Annual Performance Bonus', 'description' => 'Yearly bonus linked to individual and hospital performance.', 'benefit_type' => 'standard', 'vesting_years' => 0, 'value_label' => 'Performance based', 'icon' => 'fa-trophy', 'color' => '#d97706', 'applies_to' => 'all', 'sort_order' => 50],
    ['name' => 'Long Service Award', 'description' => 'Recognition award on completing 10 years of dedicated service.', 'benefit_type' => 'vesting', 'vesting_years' => 10, 'value_label' => '10-year milestone', 'icon' => 'fa-star', 'color' => '#ca8a04', 'applies_to' => 'all', 'sort_order' => 60],
    ['name' => 'Professional Development Allowance', 'description' => 'Annual allowance for courses, conferences and certifications.', 'benefit_type' => 'standard', 'vesting_years' => 0, 'value_label' => 'Annual allowance', 'icon' => 'fa-graduation-cap', 'color' => '#2563eb', 'applies_to' => 'all', 'sort_order' => 70],
    // --- big-corporate perks ---
    ['name' => 'Employee Stock Options (ESOP)', 'description' => 'Company equity granted to you, vesting over 4 years so you share in the organisation\'s growth.', 'benefit_type' => 'vesting', 'vesting_years' => 4, 'value_label' => 'Equity ownership', 'icon' => 'fa-line-chart', 'color' => '#4f46e5', 'applies_to' => 'all', 'sort_order' => 80],
    ['name' => 'Term Life Insurance', 'description' => 'Group term life cover that protects your family, typically up to 3× your annual CTC.', 'benefit_type' => 'standard', 'vesting_years' => 0, 'value_label' => 'Up to 3× annual CTC', 'icon' => 'fa-shield', 'color' => '#334155', 'applies_to' => 'all', 'sort_order' => 90],
    ['name' => 'Personal Accident Cover', 'description' => '24×7 accidental death & disability insurance, on and off duty.', 'benefit_type' => 'standard', 'vesting_years' => 0, 'value_label' => '24×7 coverage', 'icon' => 'fa-umbrella', 'color' => '#0ea5e9', 'applies_to' => 'all', 'sort_order' => 100],
    ['name' => 'Meal Card / Food Coupons', 'description' => 'Tax-saving meal allowance loaded monthly onto your food card.', 'benefit_type' => 'standard', 'vesting_years' => 0, 'value_label' => 'Tax-saving meals', 'icon' => 'fa-cutlery', 'color' => '#f59e0b', 'applies_to' => 'all', 'sort_order' => 110],
    ['name' => 'Transport / Cab Facility', 'description' => 'Doorstep pick-up and drop or a transport allowance for your commute.', 'benefit_type' => 'standard', 'vesting_years' => 0, 'value_label' => 'Pick & drop', 'icon' => 'fa-bus', 'color' => '#0891b2', 'applies_to' => 'all', 'sort_order' => 120],
    ['name' => 'Work From Home / Hybrid', 'description' => 'Flexible working with the option to work remotely on eligible days.', 'benefit_type' => 'standard', 'vesting_years' => 0, 'value_label' => 'Flexible working', 'icon' => 'fa-laptop', 'color' => '#6366f1', 'applies_to' => 'all', 'sort_order' => 130],
    ['name' => 'Maternity Leave', 'description' => 'Up to 26 weeks of fully-paid maternity leave with a smooth return-to-work.', 'benefit_type' => 'standard', 'vesting_years' => 0, 'value_label' => '26 weeks paid', 'icon' => 'fa-child', 'color' => '#ec4899', 'applies_to' => 'all', 'sort_order' => 140],
    ['name' => 'Paternity Leave', 'description' => 'Paid paternity leave to support you and your family after childbirth.', 'benefit_type' => 'standard', 'vesting_years' => 0, 'value_label' => 'Paid leave', 'icon' => 'fa-male', 'color' => '#3b82f6', 'applies_to' => 'all', 'sort_order' => 150],
    ['name' => 'Annual Health Check-up', 'description' => 'Complimentary preventive health check-up for you every year.', 'benefit_type' => 'standard', 'vesting_years' => 0, 'value_label' => 'Free yearly checkup', 'icon' => 'fa-stethoscope', 'color' => '#16a34a', 'applies_to' => 'all', 'sort_order' => 160],
    ['name' => 'Wellness & Gym Membership', 'description' => 'Subsidised gym / fitness membership and wellness activities.', 'benefit_type' => 'standard', 'vesting_years' => 0, 'value_label' => 'Fitness & wellness', 'icon' => 'fa-bicycle', 'color' => '#ef4444', 'applies_to' => 'all', 'sort_order' => 170],
    ['name' => 'Employee Assistance Program (EAP)', 'description' => 'Confidential counselling and mental-health support for you and your family.', 'benefit_type' => 'standard', 'vesting_years' => 0, 'value_label' => 'Counselling support', 'icon' => 'fa-user-md', 'color' => '#14b8a6', 'applies_to' => 'all', 'sort_order' => 180],
    ['name' => 'Mobile & Internet Reimbursement', 'description' => 'Monthly reimbursement of your mobile and broadband bills.', 'benefit_type' => 'standard', 'vesting_years' => 0, 'value_label' => 'Monthly reimbursement', 'icon' => 'fa-mobile', 'color' => '#64748b', 'applies_to' => 'all', 'sort_order' => 190],
    ['name' => 'Education / Tuition Reimbursement', 'description' => 'Financial support for approved higher-education and upskilling programmes.', 'benefit_type' => 'standard', 'vesting_years' => 0, 'value_label' => 'Higher-education support', 'icon' => 'fa-book', 'color' => '#2563eb', 'applies_to' => 'all', 'sort_order' => 200],
    ['name' => 'Sabbatical Leave', 'description' => 'Extended paid break available after 7 years of service for study, travel or family.', 'benefit_type' => 'vesting', 'vesting_years' => 7, 'value_label' => 'Extended paid break', 'icon' => 'fa-suitcase', 'color' => '#a855f7', 'applies_to' => 'all', 'sort_order' => 210],
    ['name' => 'Retention Bonus', 'description' => 'Loyalty bonus payable on completing 3 years of continuous service.', 'benefit_type' => 'vesting', 'vesting_years' => 3, 'value_label' => 'Stay & earn', 'icon' => 'fa-money', 'color' => '#22c55e', 'applies_to' => 'all', 'sort_order' => 220],
    ['name' => 'Employee Referral Bonus', 'description' => 'Cash reward when you refer a candidate who is hired and confirmed.', 'benefit_type' => 'standard', 'vesting_years' => 0, 'value_label' => 'Refer & earn', 'icon' => 'fa-users', 'color' => '#f97316', 'applies_to' => 'all', 'sort_order' => 230],
    ['name' => 'National Pension System (NPS)', 'description' => 'Voluntary NPS contribution with additional tax benefits toward your retirement.', 'benefit_type' => 'standard', 'vesting_years' => 0, 'value_label' => 'Retirement pension', 'icon' => 'fa-bank', 'color' => '#0d9488', 'applies_to' => 'all', 'sort_order' => 240],
    ['name' => 'Relocation Assistance', 'description' => 'Support with moving and settling-in costs when you relocate for work.', 'benefit_type' => 'standard', 'vesting_years' => 0, 'value_label' => 'Moving support', 'icon' => 'fa-home', 'color' => '#8b5cf6', 'applies_to' => 'all', 'sort_order' => 250],
];

// Fresh install: seed the whole catalogue.
if ($CI->db->count_all(db_prefix() . 'hr_benefits') == 0) {
    foreach ($hr_default_benefits as $b) {
        $b['created_at'] = $hr_now;
        $CI->db->insert(db_prefix() . 'hr_benefits', $b);
    }
}
// Existing installs: one-time top-up of any catalogue benefits not present yet
// (matched by name). Runs once; deletions the admin makes later are respected.
if (get_option('hr_benefits_seed_v2') != '1') {
    $existing = array_map('strtolower', array_column($CI->db->get(db_prefix() . 'hr_benefits')->result_array(), 'name'));
    foreach ($hr_default_benefits as $b) {
        if (!in_array(strtolower($b['name']), $existing, true)) {
            $b['created_at'] = $hr_now;
            $CI->db->insert(db_prefix() . 'hr_benefits', $b);
        }
    }
    update_option('hr_benefits_seed_v2', '1');
}

// ------------------------------------------------------------------ options
// Comprehensive default checklist for hospital / healthcare employees. A line
// starting with "*" is MANDATORY (shown in red / compulsory in ESS).
$hr_required_docs_default = "*Passport-size Photograph\n"
    . "*ID Proof (Aadhaar Card)\n"
    . "*PAN Card\n"
    . "*Address Proof (Utility Bill / Rental Agreement)\n"
    . "*Educational Certificates (10th & 12th)\n"
    . "*Degree / Professional Qualification Certificate\n"
    . "*Medical / Nursing Council Registration\n"
    . "Internship / Experience Certificate\n"
    . "Relieving Letter (Previous Employer)\n"
    . "Previous Salary Slips\n"
    . "Resume / CV\n"
    . "*Bank Passbook / Cancelled Cheque\n"
    . "PF / UAN Details\n"
    . "*Vaccination Certificate (Hepatitis B / COVID-19)\n"
    . "*Medical Fitness Certificate\n"
    . "*Police Verification Certificate";

// Prior shipped defaults, used to safely auto-upgrade installs that never
// customised the list. Customised lists are always left untouched.
//   v1 = original 6-item list; v2 = the 16-item list before mandatory markers
//   (derived from the current default by stripping the "*" prefixes).
$hr_required_docs_v1 = "ID Proof\nAddress Proof\nPAN Card\nEducational Certificate\nResume / CV\nBank Passbook / Cheque";
$hr_required_docs_v2 = implode("\n", array_map(function ($l) {
    return ltrim($l, '*');
}, explode("\n", $hr_required_docs_default)));

$hr_options = [
    'hr_employee_code_prefix'   => 'EMP-',
    'hr_leave_year_start_month' => '1',
    'hr_default_week_off'       => '0',
    'hr_role_week_off'          => '{}',
    'hr_payroll_lop_basis'      => 'calendar',
    'hr_doc_expiry_alert_days'  => '30',
    // Lead time for the "Probation Ending Soon" Omni Messaging hook.
    'hr_probation_alert_days'   => '7',
    'hr_self_editable_fields'   => '',
    'hr_required_documents'     => $hr_required_docs_default,
    'hr_required_documents_enabled' => '1',
    'hr_birthday_wishes_enabled' => '1',
    'hr_salary_payment_day'     => '1',
    'hr_leave_multilevel_enabled' => '0',
    'hr_leave_approval_chain'   => '[]',
    'hr_leave_min_notice_days'  => '1',
    'hr_leave_allow_backdated'  => '0',
    'hr_payslip_show_logo'      => '1',
    'hr_ess_role_config'        => '{}',
    'hr_ess_employee_config'    => '{}',
];
foreach ($hr_options as $name => $value) {
    if (get_option($name) === '') {
        add_option($name, $value);
    }
}

// Upgrade the checklist for installs still on a prior default (never edited in
// Settings). Comparison is LINE-ENDING AGNOSTIC — saving the settings textarea
// stores CRLF, which never matched the LF constants before. Customised lists
// are left untouched.
$hr_norm_docs = function ($s) {
    return implode("\n", array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $s)), 'strlen'));
};
$hr_cur_docs = $hr_norm_docs(get_option('hr_required_documents'));
if (in_array($hr_cur_docs, [$hr_norm_docs($hr_required_docs_v1), $hr_norm_docs($hr_required_docs_v2)], true)) {
    update_option('hr_required_documents', $hr_required_docs_default);
}

// ---------------------------------------------- hr_documents: submission cols
// Employee self-service adds a light verification workflow on top of the
// existing documents table. Guarded by field_exists so it runs once.
if ($CI->db->table_exists(db_prefix() . 'hr_documents')
    && !$CI->db->field_exists('status', db_prefix() . 'hr_documents')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'hr_documents`
        ADD COLUMN `status` VARCHAR(20) DEFAULT "submitted",
        ADD COLUMN `source` VARCHAR(20) DEFAULT "hr",
        ADD COLUMN `verified_by` INT(11) DEFAULT NULL,
        ADD COLUMN `verified_at` DATETIME DEFAULT NULL,
        ADD COLUMN `review_note` VARCHAR(255) DEFAULT NULL');
    // Everything already in the table was uploaded by HR — treat as verified.
    $CI->db->query('UPDATE `' . db_prefix() . 'hr_documents` SET `status` = "verified", `source` = "hr"');
}

// ------------------------------------------ hr_designations: mapped staff role
// A designation can carry a default staff (permission) role so that assigning
// the designation to an employee pre-fills their Role. Guarded by field_exists.
if ($CI->db->table_exists(db_prefix() . 'hr_designations')
    && !$CI->db->field_exists('role_id', db_prefix() . 'hr_designations')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'hr_designations`
        ADD COLUMN `role_id` INT(11) DEFAULT NULL');
}

// ------------------------------- hr_leave_requests: proof-of-reason upload
if ($CI->db->table_exists(db_prefix() . 'hr_leave_requests')
    && !$CI->db->field_exists('proof_file', db_prefix() . 'hr_leave_requests')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'hr_leave_requests`
        ADD COLUMN `proof_file` VARCHAR(191) DEFAULT NULL');
}

// ------------------------- hr_leave_requests: multi-level approval progress
if ($CI->db->table_exists(db_prefix() . 'hr_leave_requests')
    && !$CI->db->field_exists('current_level', db_prefix() . 'hr_leave_requests')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'hr_leave_requests`
        ADD COLUMN `current_level` TINYINT(4) NOT NULL DEFAULT 0');
}

// -------------------------------------- hr_leave_approvals: per-level log
if (!$CI->db->table_exists(db_prefix() . 'hr_leave_approvals')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'hr_leave_approvals` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `request_id` INT(11) NOT NULL,
        `level` TINYINT(4) NOT NULL DEFAULT 1,
        `role_id` INT(11) NOT NULL DEFAULT 0,
        `action` VARCHAR(15) NOT NULL,
        `staff_id` INT(11) NOT NULL,
        `note` VARCHAR(255) DEFAULT NULL,
        `created_at` DATETIME DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_hr_leave_appr_req` (`request_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $charset . ';');
}

// ------------------------------- hr_leave_types: icon + carry-forward cap
if ($CI->db->table_exists(db_prefix() . 'hr_leave_types')
    && !$CI->db->field_exists('icon', db_prefix() . 'hr_leave_types')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'hr_leave_types`
        ADD COLUMN `icon` VARCHAR(40) DEFAULT NULL,
        ADD COLUMN `carry_forward_max` DECIMAL(5,1) NOT NULL DEFAULT 0');
}

// ------------------------ hr_leave_types: emergency flag (same-day allowed)
if ($CI->db->table_exists(db_prefix() . 'hr_leave_types')
    && !$CI->db->field_exists('is_emergency', db_prefix() . 'hr_leave_types')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'hr_leave_types`
        ADD COLUMN `is_emergency` TINYINT(1) NOT NULL DEFAULT 0');
    // Sensible default: treat Sick / Medical / Emergency types as emergency so
    // urgent same-day leave works out of the box.
    $CI->db->query('UPDATE `' . db_prefix() . 'hr_leave_types`
        SET `is_emergency` = 1
        WHERE `code` = "SL" OR `name` LIKE "%Sick%" OR `name` LIKE "%Medical%" OR `name` LIKE "%Emergency%"');
}

// --------------------------- hr_leave_allocations: carried-forward balance
if ($CI->db->table_exists(db_prefix() . 'hr_leave_allocations')
    && !$CI->db->field_exists('carried', db_prefix() . 'hr_leave_allocations')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'hr_leave_allocations`
        ADD COLUMN `carried` DECIMAL(5,1) NOT NULL DEFAULT 0');
}

// ------------------------------------- hr_employees: per-employee pay day
// Optional per-employee override of the global Salary Payment Day. NULL means
// "use the global setting"; 0 means "last day of month"; 1-31 is a fixed day.
if ($CI->db->table_exists(db_prefix() . 'hr_employees')
    && !$CI->db->field_exists('salary_payment_day', db_prefix() . 'hr_employees')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'hr_employees`
        ADD COLUMN `salary_payment_day` TINYINT(4) DEFAULT NULL');
}

// -------------------------------------------- hr_holidays: show/hide toggle
// Lets an admin hide a holiday (e.g. an imported Indian holiday that doesn't
// apply at this workplace) without deleting it. Inactive holidays are excluded
// from attendance, working-day and payroll calculations. Guarded by field_exists.
if ($CI->db->table_exists(db_prefix() . 'hr_holidays')
    && !$CI->db->field_exists('is_active', db_prefix() . 'hr_holidays')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'hr_holidays`
        ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1');
}

// -------------------------------------------------------------------- perks
// Office perks / supplies procurement checklist — HR lists things to order
// (pantry snacks, beverages, office-maintenance items, ...), tracks each from
// "to order" → "ordered" → "received", and can assign a person to procure it.
if (!$CI->db->table_exists(db_prefix() . 'hr_perk_items')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'hr_perk_items` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `title` VARCHAR(191) NOT NULL,
        `category` VARCHAR(100) NOT NULL DEFAULT "Pantry & Snacks",
        `quantity` VARCHAR(60) DEFAULT NULL,
        `priority` VARCHAR(10) NOT NULL DEFAULT "medium",
        `status` VARCHAR(15) NOT NULL DEFAULT "pending",
        `assigned_to` INT(11) DEFAULT NULL,
        `needed_by` DATE DEFAULT NULL,
        `notes` TEXT DEFAULT NULL,
        `sort_order` INT(11) NOT NULL DEFAULT 0,
        `ordered_at` DATETIME DEFAULT NULL,
        `received_at` DATETIME DEFAULT NULL,
        `created_by` INT(11) DEFAULT NULL,
        `created_at` DATETIME DEFAULT NULL,
        `updated_at` DATETIME DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_hr_perk_status` (`status`),
        KEY `idx_hr_perk_category` (`category`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $charset . ';');
}

// Big-corporate starter checklist across every category. Items are examples HR
// can edit or delete — they simply make the screen useful out of the box. Titles
// are the de-dupe key for the one-time top-up below, so keep them stable.
$hr_default_perks = [
    // --- Pantry & Snacks ---
    ['title' => 'Biscuits (assorted)',            'category' => 'Pantry & Snacks',         'quantity' => '4 packs',    'priority' => 'medium'],
    ['title' => 'Namkeen / savoury snacks',       'category' => 'Pantry & Snacks',         'quantity' => '3 packs',    'priority' => 'low'],
    ['title' => 'Cookies & rusks',                'category' => 'Pantry & Snacks',         'quantity' => '3 packs',    'priority' => 'low'],
    ['title' => 'Sugar',                          'category' => 'Pantry & Snacks',         'quantity' => '2 kg',       'priority' => 'medium'],
    ['title' => 'Dry fruits & nuts',              'category' => 'Pantry & Snacks',         'quantity' => '2 kg',       'priority' => 'low'],
    ['title' => 'Chocolates / candy jar',         'category' => 'Pantry & Snacks',         'quantity' => '1 box',      'priority' => 'low'],
    ['title' => 'Fresh fruits (weekly)',          'category' => 'Pantry & Snacks',         'quantity' => 'As needed',  'priority' => 'medium'],
    ['title' => 'Breakfast cereal / oats',        'category' => 'Pantry & Snacks',         'quantity' => '2 packs',    'priority' => 'low'],
    ['title' => 'Instant noodles / cup meals',    'category' => 'Pantry & Snacks',         'quantity' => '2 boxes',    'priority' => 'low'],

    // --- Beverages ---
    ['title' => 'Tea & Coffee sachets',           'category' => 'Beverages',               'quantity' => '2 boxes',    'priority' => 'high'],
    ['title' => 'Green tea / herbal tea',         'category' => 'Beverages',               'quantity' => '2 boxes',    'priority' => 'low'],
    ['title' => 'Coffee beans (for machine)',     'category' => 'Beverages',               'quantity' => '2 kg',       'priority' => 'medium'],
    ['title' => 'Drinking water cans',            'category' => 'Beverages',               'quantity' => '5 cans',     'priority' => 'high'],
    ['title' => 'Milk (daily)',                   'category' => 'Beverages',               'quantity' => 'As needed',  'priority' => 'high'],
    ['title' => 'Packaged juice / tetra packs',   'category' => 'Beverages',               'quantity' => '2 boxes',    'priority' => 'low'],
    ['title' => 'Soft drinks / soda',             'category' => 'Beverages',               'quantity' => '2 cases',    'priority' => 'low'],

    // --- Pantry Equipment ---
    ['title' => 'Disposable cups & plates',       'category' => 'Pantry Equipment',        'quantity' => '5 sleeves',  'priority' => 'medium'],
    ['title' => 'Tissue papers / napkins',        'category' => 'Pantry Equipment',        'quantity' => '6 packs',    'priority' => 'medium'],
    ['title' => 'Stirrers & straws',              'category' => 'Pantry Equipment',        'quantity' => '2 packs',    'priority' => 'low'],
    ['title' => 'Water dispenser servicing',      'category' => 'Pantry Equipment',        'quantity' => '1 service',  'priority' => 'medium'],
    ['title' => 'Coffee machine descaling',       'category' => 'Pantry Equipment',        'quantity' => '1 service',  'priority' => 'low'],

    // --- Office Maintenance ---
    ['title' => 'Tube light replacement',         'category' => 'Office Maintenance',      'quantity' => '3 nos',      'priority' => 'low'],
    ['title' => 'AC servicing',                   'category' => 'Office Maintenance',      'quantity' => '1 service',  'priority' => 'medium'],
    ['title' => 'Pest control',                   'category' => 'Office Maintenance',      'quantity' => '1 service',  'priority' => 'medium'],
    ['title' => 'Plumbing check (washrooms)',     'category' => 'Office Maintenance',      'quantity' => '1 service',  'priority' => 'low'],
    ['title' => 'Fire extinguisher refill / check','category' => 'Office Maintenance',     'quantity' => 'Annual',     'priority' => 'high'],
    ['title' => 'Door / cabinet lock repair',     'category' => 'Office Maintenance',      'quantity' => 'As needed',  'priority' => 'low'],

    // --- Cleaning & Housekeeping ---
    ['title' => 'Hand wash / sanitiser refill',   'category' => 'Cleaning & Housekeeping', 'quantity' => '3 bottles',  'priority' => 'medium'],
    ['title' => 'Floor cleaner (phenyl)',         'category' => 'Cleaning & Housekeeping', 'quantity' => '2 litres',   'priority' => 'low'],
    ['title' => 'Toilet cleaner',                 'category' => 'Cleaning & Housekeeping', 'quantity' => '4 bottles',  'priority' => 'medium'],
    ['title' => 'Garbage bags (assorted)',        'category' => 'Cleaning & Housekeeping', 'quantity' => '4 rolls',    'priority' => 'medium'],
    ['title' => 'Air freshener / room spray',     'category' => 'Cleaning & Housekeeping', 'quantity' => '3 cans',     'priority' => 'low'],
    ['title' => 'Glass cleaner',                  'category' => 'Cleaning & Housekeeping', 'quantity' => '2 bottles',  'priority' => 'low'],
    ['title' => 'Dishwashing liquid',             'category' => 'Cleaning & Housekeeping', 'quantity' => '3 bottles',  'priority' => 'low'],
    ['title' => 'Mop, broom & dusters',           'category' => 'Cleaning & Housekeeping', 'quantity' => '2 sets',     'priority' => 'low'],

    // --- Stationery ---
    ['title' => 'A4 printer paper',               'category' => 'Stationery',              'quantity' => '2 boxes',    'priority' => 'medium'],
    ['title' => 'Printer toner / ink cartridge',  'category' => 'Stationery',              'quantity' => '2 nos',      'priority' => 'high'],
    ['title' => 'Pens & notepads',                'category' => 'Stationery',              'quantity' => '2 boxes',    'priority' => 'low'],
    ['title' => 'Whiteboard markers & duster',    'category' => 'Stationery',              'quantity' => '1 set',      'priority' => 'low'],
    ['title' => 'Sticky notes & flags',           'category' => 'Stationery',              'quantity' => '2 packs',    'priority' => 'low'],
    ['title' => 'Stapler, pins & clips',          'category' => 'Stationery',              'quantity' => '1 set',      'priority' => 'low'],
    ['title' => 'File folders & envelopes',       'category' => 'Stationery',              'quantity' => '1 box',      'priority' => 'low'],

    // --- Electrical & Appliances ---
    ['title' => 'Extension boards / power strips','category' => 'Electrical & Appliances', 'quantity' => '3 nos',      'priority' => 'low'],
    ['title' => 'LED bulbs (spare)',              'category' => 'Electrical & Appliances', 'quantity' => '5 nos',      'priority' => 'low'],
    ['title' => 'Batteries (AA/AAA)',             'category' => 'Electrical & Appliances', 'quantity' => '1 pack',     'priority' => 'low'],
    ['title' => 'UPS / inverter battery check',   'category' => 'Electrical & Appliances', 'quantity' => '1 service',  'priority' => 'medium'],

    // --- IT & Peripherals ---
    ['title' => 'Wireless mouse & keyboard',      'category' => 'IT & Peripherals',        'quantity' => '3 sets',     'priority' => 'low'],
    ['title' => 'Laptop chargers (spare)',        'category' => 'IT & Peripherals',        'quantity' => '2 nos',      'priority' => 'medium'],
    ['title' => 'HDMI / USB-C cables',            'category' => 'IT & Peripherals',        'quantity' => '5 nos',      'priority' => 'low'],
    ['title' => 'Webcams / headsets',             'category' => 'IT & Peripherals',        'quantity' => '3 nos',      'priority' => 'low'],

    // --- Furniture & Fixtures ---
    ['title' => 'Office chairs (replacement)',    'category' => 'Furniture & Fixtures',    'quantity' => '2 nos',      'priority' => 'low'],
    ['title' => 'Desk organisers',                'category' => 'Furniture & Fixtures',    'quantity' => '5 nos',      'priority' => 'low'],

    // --- Health & Safety ---
    ['title' => 'First-aid kit refill',           'category' => 'Health & Safety',         'quantity' => '2 kits',     'priority' => 'high'],
    ['title' => 'Face masks & gloves',            'category' => 'Health & Safety',         'quantity' => '2 boxes',    'priority' => 'medium'],
    ['title' => 'Sanitiser dispenser refills',    'category' => 'Health & Safety',         'quantity' => '4 nos',      'priority' => 'medium'],

    // --- Employee Welfare ---
    ['title' => 'Birthday cake & celebration kit','category' => 'Employee Welfare',        'quantity' => 'Monthly',    'priority' => 'low'],
    ['title' => 'Friday team snacks',             'category' => 'Employee Welfare',        'quantity' => 'Weekly',     'priority' => 'low'],

    // --- Décor & Plants ---
    ['title' => 'Indoor plants & pots',           'category' => 'Décor & Plants',          'quantity' => '4 nos',      'priority' => 'low'],
    ['title' => 'Plant maintenance service',      'category' => 'Décor & Plants',          'quantity' => 'Monthly',    'priority' => 'low'],
];

$hr_perk_now = date('Y-m-d H:i:s');

// Fresh install: seed the whole catalogue so the screen is useful out of the box.
if ($CI->db->count_all(db_prefix() . 'hr_perk_items') == 0) {
    $hr_perk_order = 10;
    foreach ($hr_default_perks as $p) {
        $p['status']     = 'pending';
        $p['sort_order'] = $hr_perk_order;
        $p['created_at'] = $hr_perk_now;
        $CI->db->insert(db_prefix() . 'hr_perk_items', $p);
        $hr_perk_order += 10;
    }
}
// Existing installs: one-time top-up of any catalogue items not present yet
// (matched by title). Runs once; items the admin deleted later are respected.
if (get_option('hr_perks_seed_v2') != '1') {
    $hr_existing_perks = array_map('strtolower', array_column($CI->db->get(db_prefix() . 'hr_perk_items')->result_array(), 'title'));
    $hr_perk_order = 10;
    foreach ($hr_default_perks as $p) {
        if (!in_array(strtolower($p['title']), $hr_existing_perks, true)) {
            $p['status']     = 'pending';
            $p['sort_order'] = $hr_perk_order;
            $p['created_at'] = $hr_perk_now;
            $CI->db->insert(db_prefix() . 'hr_perk_items', $p);
        }
        $hr_perk_order += 10;
    }
    update_option('hr_perks_seed_v2', '1');
}

// -------------------------------------------------------------- memos
// Disciplinary / memo records: misconduct, disciplinary action, late coming,
// property damage, misuse, temporary suspension, warnings, etc. Each memo is
// issued to an employee who then acknowledges it (acknowledgement receipt).
if (!$CI->db->table_exists(db_prefix() . 'hr_memos')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'hr_memos` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `staff_id` INT(11) NOT NULL,
        `memo_type` VARCHAR(40) NOT NULL DEFAULT "misconduct",
        `severity` VARCHAR(10) NOT NULL DEFAULT "medium",
        `subject` VARCHAR(191) NOT NULL,
        `description` TEXT DEFAULT NULL,
        `incident_date` DATE DEFAULT NULL,
        `action_taken` TEXT DEFAULT NULL,
        `penalty_amount` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `suspension_from` DATE DEFAULT NULL,
        `suspension_to` DATE DEFAULT NULL,
        `attachment` VARCHAR(191) DEFAULT NULL,
        `status` VARCHAR(20) NOT NULL DEFAULT "issued",
        `acknowledged_at` DATETIME DEFAULT NULL,
        `ack_signature` VARCHAR(191) DEFAULT NULL,
        `ack_note` TEXT DEFAULT NULL,
        `ack_agree` TINYINT(1) NOT NULL DEFAULT 1,
        `issued_by` INT(11) DEFAULT NULL,
        `created_at` DATETIME DEFAULT NULL,
        `updated_at` DATETIME DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_hr_memo_staff` (`staff_id`),
        KEY `idx_hr_memo_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $charset . ';');
}

// -------------------------------------------------------------- onboarding
// Reusable onboarding checklist templates (a named set of tasks HR can apply to
// any new joiner), plus per-employee onboarding instances with their own copy
// of the tasks so progress is tracked individually.
if (!$CI->db->table_exists(db_prefix() . 'hr_onboarding_templates')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'hr_onboarding_templates` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(150) NOT NULL,
        `description` VARCHAR(255) DEFAULT NULL,
        `is_default` TINYINT(1) NOT NULL DEFAULT 0,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `created_at` DATETIME DEFAULT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $charset . ';');
}

if (!$CI->db->table_exists(db_prefix() . 'hr_onboarding_template_items')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'hr_onboarding_template_items` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `template_id` INT(11) NOT NULL,
        `title` VARCHAR(191) NOT NULL,
        `description` VARCHAR(255) DEFAULT NULL,
        `phase` VARCHAR(30) NOT NULL DEFAULT "first_day",
        `assigned_role_id` INT(11) DEFAULT NULL,
        `due_offset_days` INT(11) NOT NULL DEFAULT 0,
        `sort_order` INT(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        KEY `idx_hr_onb_tpl_item` (`template_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $charset . ';');
}

if (!$CI->db->table_exists(db_prefix() . 'hr_onboarding')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'hr_onboarding` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `staff_id` INT(11) NOT NULL,
        `template_id` INT(11) DEFAULT NULL,
        `status` VARCHAR(15) NOT NULL DEFAULT "in_progress",
        `start_date` DATE DEFAULT NULL,
        `target_date` DATE DEFAULT NULL,
        `completed_at` DATETIME DEFAULT NULL,
        `notes` TEXT DEFAULT NULL,
        `created_by` INT(11) DEFAULT NULL,
        `created_at` DATETIME DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_hr_onb_staff` (`staff_id`),
        KEY `idx_hr_onb_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $charset . ';');
}

if (!$CI->db->table_exists(db_prefix() . 'hr_onboarding_items')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'hr_onboarding_items` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `onboarding_id` INT(11) NOT NULL,
        `title` VARCHAR(191) NOT NULL,
        `description` VARCHAR(255) DEFAULT NULL,
        `phase` VARCHAR(30) NOT NULL DEFAULT "first_day",
        `assigned_to` INT(11) DEFAULT NULL,
        `due_date` DATE DEFAULT NULL,
        `status` VARCHAR(15) NOT NULL DEFAULT "pending",
        `completed_by` INT(11) DEFAULT NULL,
        `completed_at` DATETIME DEFAULT NULL,
        `notes` VARCHAR(255) DEFAULT NULL,
        `sort_order` INT(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        KEY `idx_hr_onb_item` (`onboarding_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $charset . ';');
}

// Seed one default onboarding template (only on a fresh templates table).
if ($CI->db->count_all(db_prefix() . 'hr_onboarding_templates') == 0) {
    $CI->db->insert(db_prefix() . 'hr_onboarding_templates', [
        'name'        => 'Standard Employee Onboarding',
        'description' => 'Default onboarding checklist for new joiners.',
        'is_default'  => 1,
        'is_active'   => 1,
        'created_at'  => date('Y-m-d H:i:s'),
    ]);
    $hr_onb_tpl_id = (int) $CI->db->insert_id();
    // phase => [ [title, description, due_offset_days], ... ]
    $hr_onb_seed = [
        'pre_boarding' => [
            ['Collect signed offer / appointment letter', 'Ensure the candidate has accepted and returned the signed letter.', -3],
            ['Collect joining documents', 'ID proof, certificates, photos, bank details (see Documents checklist).', -2],
            ['Create employee record in HR', 'Add the employee, employee code and department.', -1],
        ],
        'first_day' => [
            ['Welcome & office / ward tour', 'Introduce the team and show around the facility.', 0],
            ['Issue ID card & access', 'Biometric / access-card enrolment and photo ID.', 0],
            ['IT setup (email, systems, logins)', 'Create email, HMIS/HIS logins and required software access.', 0],
            ['Assign workstation / locker', 'Desk, locker, uniform and basic supplies.', 0],
        ],
        'first_week' => [
            ['Policy & code-of-conduct briefing', 'HR policies, leave, attendance and disciplinary policy.', 3],
            ['Safety & infection-control training', 'Mandatory hospital safety, fire and infection-control orientation.', 5],
            ['Assign reporting manager & buddy', 'Confirm reporting line and assign an onboarding buddy.', 2],
            ['Enrol in statutory benefits (PF/ESI)', 'Collect PF/UAN, ESI and insurance nomination forms.', 5],
        ],
        'first_month' => [
            ['Role-specific / department training', 'Department SOPs and job-specific training.', 20],
            ['30-day check-in', 'First feedback conversation with the reporting manager.', 30],
            ['Confirm probation goals', 'Agree objectives to be reviewed at end of probation.', 15],
        ],
    ];
    $hr_onb_sort = 10;
    foreach ($hr_onb_seed as $hr_onb_phase => $hr_onb_items) {
        foreach ($hr_onb_items as $hr_onb_it) {
            $CI->db->insert(db_prefix() . 'hr_onboarding_template_items', [
                'template_id'     => $hr_onb_tpl_id,
                'title'           => $hr_onb_it[0],
                'description'     => $hr_onb_it[1],
                'phase'           => $hr_onb_phase,
                'due_offset_days' => $hr_onb_it[2],
                'sort_order'      => $hr_onb_sort,
            ]);
            $hr_onb_sort += 10;
        }
    }
}

// -------------------------------------------------------------- interviews
// Online / in-person interview scheduling for standalone candidates. When Zoom
// or Google Meet is configured, a real meeting is auto-created and its join URL
// stored; otherwise HR can paste a link manually.
if (!$CI->db->table_exists(db_prefix() . 'hr_interviews')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'hr_interviews` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `candidate_name` VARCHAR(150) NOT NULL,
        `candidate_email` VARCHAR(150) DEFAULT NULL,
        `candidate_phone` VARCHAR(30) DEFAULT NULL,
        `position` VARCHAR(150) DEFAULT NULL,
        `designation_id` INT(11) DEFAULT NULL,
        `department_id` INT(11) DEFAULT NULL,
        `resume_file` VARCHAR(191) DEFAULT NULL,
        `round_no` INT(11) NOT NULL DEFAULT 1,
        `round_name` VARCHAR(100) DEFAULT NULL,
        `platform` VARCHAR(20) NOT NULL DEFAULT "google_meet",
        `scheduled_at` DATETIME DEFAULT NULL,
        `duration_minutes` INT(11) NOT NULL DEFAULT 30,
        `timezone` VARCHAR(64) DEFAULT NULL,
        `location` VARCHAR(191) DEFAULT NULL,
        `meeting_url` TEXT DEFAULT NULL,
        `meeting_id` VARCHAR(100) DEFAULT NULL,
        `meeting_password` VARCHAR(100) DEFAULT NULL,
        `provider_meeting_id` VARCHAR(100) DEFAULT NULL,
        `provider_host_url` TEXT DEFAULT NULL,
        `interviewer_ids` TEXT DEFAULT NULL,
        `status` VARCHAR(15) NOT NULL DEFAULT "scheduled",
        `rating` DECIMAL(3,1) NOT NULL DEFAULT 0,
        `recommendation` VARCHAR(20) DEFAULT NULL,
        `feedback` TEXT DEFAULT NULL,
        `notes` TEXT DEFAULT NULL,
        `invite_sent` TINYINT(1) NOT NULL DEFAULT 0,
        `created_by` INT(11) DEFAULT NULL,
        `created_at` DATETIME DEFAULT NULL,
        `updated_at` DATETIME DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_hr_interview_status` (`status`),
        KEY `idx_hr_interview_date` (`scheduled_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $charset . ';');
}

// ------------------------------ hr_employees: biometric machine employee code
// Optional per-employee code as registered on the attendance machine. When
// blank the punch import falls back to matching on employee_code.
if ($CI->db->table_exists(db_prefix() . 'hr_employees')
    && !$CI->db->field_exists('device_empcode', db_prefix() . 'hr_employees')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'hr_employees`
        ADD COLUMN `device_empcode` VARCHAR(150) DEFAULT NULL AFTER `employee_code`');
}

// Widen device_empcode so several comma-separated machine codes fit — the
// one-time mapping tool on the punch log appends codes for employees who are
// registered more than once on the machine.
if ($CI->db->table_exists(db_prefix() . 'hr_employees')
    && $CI->db->field_exists('device_empcode', db_prefix() . 'hr_employees')) {
    $col = $CI->db->query('SHOW COLUMNS FROM `' . db_prefix() . 'hr_employees` LIKE "device_empcode"')->row_array();
    if ($col && stripos($col['Type'], 'varchar(150)') === false) {
        $CI->db->query('ALTER TABLE `' . db_prefix() . 'hr_employees`
            MODIFY `device_empcode` VARCHAR(150) DEFAULT NULL');
    }
}

// ---------------------------------------------- hr_attendance: record source
// Distinguishes machine-imported rows from human-marked ones so an auto-sync
// never silently overwrites attendance an HR user set by hand (unless the admin
// opts in). Existing rows are treated as manual.
if ($CI->db->table_exists(db_prefix() . 'hr_attendance')
    && !$CI->db->field_exists('source', db_prefix() . 'hr_attendance')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'hr_attendance`
        ADD COLUMN `source` VARCHAR(15) NOT NULL DEFAULT "manual"');
}

// ------------------------------------- hr_attendance_punches: raw punch feed
// Every punch pulled from the machine is stored here (deduped) so the daily
// attendance can be rebuilt idempotently and a full punch log is available.
if (!$CI->db->table_exists(db_prefix() . 'hr_attendance_punches')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'hr_attendance_punches` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `staff_id` INT(11) DEFAULT NULL,
        `empcode` VARCHAR(50) NOT NULL,
        `name` VARCHAR(150) DEFAULT NULL,
        `punch_at` DATETIME NOT NULL,
        `mcid` VARCHAR(20) DEFAULT NULL,
        `source` VARCHAR(20) NOT NULL DEFAULT "etime",
        `created_at` DATETIME DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_hr_punch` (`source`, `empcode`, `punch_at`),
        KEY `idx_hr_punch_staff` (`staff_id`, `punch_at`),
        KEY `idx_hr_punch_date` (`punch_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $charset . ';');
}

// ----------------------------------------- hr_employees: Aadhaar (India UIDAI)
// Kept separate from national_id so installs that use national_id for a
// passport / SSN / other national identifier are not overloaded. Existing
// national_id values that are plainly a 12-digit Aadhaar are copied over once.
if ($CI->db->table_exists(db_prefix() . 'hr_employees')
    && !$CI->db->field_exists('aadhaar_number', db_prefix() . 'hr_employees')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'hr_employees`
        ADD COLUMN `aadhaar_number` VARCHAR(20) DEFAULT NULL AFTER `national_id`');
    $CI->db->query('UPDATE `' . db_prefix() . 'hr_employees`
        SET `aadhaar_number` = REPLACE(REPLACE(national_id, " ", ""), "-", "")
        WHERE national_id IS NOT NULL
          AND REPLACE(REPLACE(national_id, " ", ""), "-", "") REGEXP "^[0-9]{12}$"');
}

// --------------------------------------------------------- family members
// Repeatable family / dependent records per employee (insurance, ESI
// dependants, emergency reach-out, gratuity records).
if (!$CI->db->table_exists(db_prefix() . 'hr_family_members')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'hr_family_members` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `staff_id` INT(11) NOT NULL,
        `name` VARCHAR(150) NOT NULL,
        `relation` VARCHAR(50) DEFAULT NULL,
        `date_of_birth` DATE DEFAULT NULL,
        `gender` VARCHAR(15) DEFAULT NULL,
        `blood_group` VARCHAR(10) DEFAULT NULL,
        `occupation` VARCHAR(150) DEFAULT NULL,
        `phone` VARCHAR(30) DEFAULT NULL,
        `aadhaar_number` VARCHAR(20) DEFAULT NULL,
        `is_dependent` TINYINT(1) NOT NULL DEFAULT 0,
        `is_emergency_contact` TINYINT(1) NOT NULL DEFAULT 0,
        `notes` TEXT DEFAULT NULL,
        `created_at` DATETIME DEFAULT NULL,
        `updated_at` DATETIME DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_hr_family_staff` (`staff_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $charset . ';');
}

// --------------------------------------------------------------- nominees
// Nomination records (PF / ESI / Gratuity / Insurance / general). Share is a
// percentage per scheme; a guardian is captured when the nominee is a minor.
if (!$CI->db->table_exists(db_prefix() . 'hr_nominees')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'hr_nominees` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `staff_id` INT(11) NOT NULL,
        `name` VARCHAR(150) NOT NULL,
        `relation` VARCHAR(50) DEFAULT NULL,
        `date_of_birth` DATE DEFAULT NULL,
        `nominee_for` VARCHAR(30) NOT NULL DEFAULT "general",
        `share_percent` DECIMAL(5,2) NOT NULL DEFAULT 100.00,
        `phone` VARCHAR(30) DEFAULT NULL,
        `aadhaar_number` VARCHAR(20) DEFAULT NULL,
        `address` TEXT DEFAULT NULL,
        `guardian_name` VARCHAR(150) DEFAULT NULL,
        `guardian_relation` VARCHAR(50) DEFAULT NULL,
        `notes` TEXT DEFAULT NULL,
        `created_at` DATETIME DEFAULT NULL,
        `updated_at` DATETIME DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_hr_nominee_staff` (`staff_id`, `nominee_for`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $charset . ';');
}

// ------------------------------------- field attendance: remote punch log
// Punch in / punch out taken OUTSIDE the office (field staff, client visits,
// work from home) from the employee's own phone or laptop: GPS coordinates +
// a selfie/photo, optionally matched against a saved office/site location.
if (!$CI->db->table_exists(db_prefix() . 'hr_field_punches')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'hr_field_punches` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `staff_id` INT(11) NOT NULL,
        `punch_type` VARCHAR(10) NOT NULL DEFAULT "in",
        `punch_at` DATETIME NOT NULL,
        `punch_date` DATE NOT NULL,
        `latitude` DECIMAL(10,7) DEFAULT NULL,
        `longitude` DECIMAL(10,7) DEFAULT NULL,
        `accuracy_m` INT(11) DEFAULT NULL,
        `address` VARCHAR(255) DEFAULT NULL,
        `site_id` INT(11) DEFAULT NULL,
        `site_name` VARCHAR(150) DEFAULT NULL,
        `distance_m` INT(11) DEFAULT NULL,
        `within_geofence` TINYINT(1) NOT NULL DEFAULT 0,
        `photo` VARCHAR(191) DEFAULT NULL,
        `photo_source` VARCHAR(10) DEFAULT NULL,
        `purpose` VARCHAR(30) DEFAULT NULL,
        `note` VARCHAR(255) DEFAULT NULL,
        `device_info` VARCHAR(255) DEFAULT NULL,
        `ip_address` VARCHAR(45) DEFAULT NULL,
        `status` VARCHAR(15) NOT NULL DEFAULT "pending",
        `reviewed_by` INT(11) DEFAULT NULL,
        `reviewed_at` DATETIME DEFAULT NULL,
        `review_note` VARCHAR(255) DEFAULT NULL,
        `created_at` DATETIME DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_hr_fp_staff_date` (`staff_id`, `punch_date`),
        KEY `idx_hr_fp_date` (`punch_date`),
        KEY `idx_hr_fp_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $charset . ';');
}

// ------------------------------------------------------------- KRA & KPI
// Key Result Areas and the Key Performance Indicators that measure them, per
// employee. One table for both (entry_type), so a KPI can point at the KRA it
// measures through parent_id. `description` and `review_remarks` hold TinyMCE
// HTML (purified on save).
if (!$CI->db->table_exists(db_prefix() . 'hr_kra_kpi')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'hr_kra_kpi` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `staff_id` INT(11) NOT NULL,
        `entry_type` VARCHAR(10) NOT NULL DEFAULT "kra",
        `parent_id` INT(11) DEFAULT NULL,
        `title` VARCHAR(255) NOT NULL,
        `description` LONGTEXT DEFAULT NULL,
        `metric` VARCHAR(150) DEFAULT NULL,
        `target_value` VARCHAR(100) DEFAULT NULL,
        `actual_value` VARCHAR(100) DEFAULT NULL,
        `weightage` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
        `frequency` VARCHAR(20) NOT NULL DEFAULT "annual",
        `period_from` DATE DEFAULT NULL,
        `period_to` DATE DEFAULT NULL,
        `status` VARCHAR(20) NOT NULL DEFAULT "not_started",
        `rating` DECIMAL(4,2) DEFAULT NULL,
        `review_remarks` LONGTEXT DEFAULT NULL,
        `sort_order` INT(11) NOT NULL DEFAULT 0,
        `created_by` INT(11) DEFAULT NULL,
        `updated_by` INT(11) DEFAULT NULL,
        `created_at` DATETIME DEFAULT NULL,
        `updated_at` DATETIME DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_hr_krakpi_staff` (`staff_id`, `entry_type`),
        KEY `idx_hr_krakpi_parent` (`parent_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $charset . ';');
}

// ----------------------------------- field attendance: geofenced work sites
// Optional named locations (head office, branch, client site) with a radius.
// A field punch is matched to the nearest active site; the geofence mode in
// HR Settings decides whether an out-of-range punch is flagged or blocked.
if (!$CI->db->table_exists(db_prefix() . 'hr_field_sites')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'hr_field_sites` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(150) NOT NULL,
        `address` VARCHAR(255) DEFAULT NULL,
        `latitude` DECIMAL(10,7) NOT NULL,
        `longitude` DECIMAL(10,7) NOT NULL,
        `radius_m` INT(11) NOT NULL DEFAULT 200,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `created_at` DATETIME DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_hr_field_site_active` (`is_active`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $charset . ';');
}

// ------------------------------------------------ new-feature default options
$hr_new_options = [
    // Interview integrations (Zoom Server-to-Server OAuth + Google Meet SA).
    'hr_zoom_enabled'              => '0',
    'hr_zoom_account_id'           => '',
    'hr_zoom_client_id'            => '',
    'hr_zoom_client_secret'        => '',
    'hr_zoom_host_email'           => '',
    'hr_google_meet_enabled'       => '0',
    'hr_google_sa_json'            => '',
    'hr_google_impersonate_email'  => '',
    'hr_interview_send_invites'    => '1',
    // Biometric attendance machine (e-timeoffice).
    'hr_etime_enabled'             => '0',
    'hr_etime_base_url'            => 'https://api.etimeoffice.com/api/',
    'hr_etime_corporate_id'        => '',
    'hr_etime_username'            => '',
    'hr_etime_password'            => '',
    'hr_etime_auto_sync'           => '0',
    'hr_etime_sync_window_days'    => '2',
    'hr_etime_halfday_minutes'     => '240',
    'hr_etime_overwrite_manual'    => '0',
    'hr_etime_last_record'         => '',
    'hr_etime_last_sync'           => '',
    // Field attendance (punch in/out from outside the office).
    'hr_field_enabled'             => '1',
    'hr_field_require_location'    => '1',
    'hr_field_require_photo'       => '1',
    'hr_field_max_accuracy_m'      => '0',
    'hr_field_geofence_mode'       => 'off',
    'hr_field_auto_approve'        => '1',
    'hr_field_update_attendance'   => '1',
    'hr_field_overwrite_manual'    => '0',
    'hr_field_halfday_minutes'     => '240',
    'hr_field_min_gap_minutes'     => '2',
    'hr_field_reverse_geocode'     => '0',
    'hr_field_notify_reviewers'    => '1',
];
foreach ($hr_new_options as $name => $value) {
    if (get_option($name) === '') {
        add_option($name, $value);
    }
}

update_option('hr_schema_version', defined('HR_SCHEMA_VERSION') ? HR_SCHEMA_VERSION : '100');
