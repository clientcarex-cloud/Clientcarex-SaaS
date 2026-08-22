<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * ═══════════════════════════════════════════════════════════════
 *  CAREERS — plain-function core
 * ═══════════════════════════════════════════════════════════════
 *
 * Loaded unconditionally by careers.php: the admin pages, the public website
 * API controller and cron all need the same lookups, options and schema, and
 * only the admin area ever runs $CI->load->helper().
 *
 * Sections:
 *   1. Language & permissions
 *   2. Lookups            job types, work modes, statuses, pipeline stages
 *   3. Options            defaults + typed accessors
 *   4. Schema             self-healing CREATE TABLE / ADD COLUMN
 *   5. Formatting         slugs, references, salary, relative time
 *   6. Files              resume upload + safe retrieval
 *   7. Public shape       the exact rows the marketing website receives
 *   8. Mail               templated notifications through the CRM mailer
 *
 * TIME: every stored datetime is the CRM's local time (Y-m-d H:i:s), matching
 * the rest of the app. The public API additionally emits ISO-8601 dates for
 * Google's JobPosting structured data.
 */

define('CAREERS_SCHEMA_VERSION', '1.1.0');

/* ═════════════════════ 1. Language & permissions ═════════════════════════ */

/**
 * Make this module's language available outside the admin panel.
 *
 * register_language_files() only hooks the admin/client language loaders —
 * neither fires on the public API controller or under cron, and _l() would
 * echo the raw key. Loading twice is a no-op in CI, so every entry point that
 * can run outside the admin area calls this.
 */
function careers_load_lang()
{
    static $tried = false;

    if ($tried) {
        return;
    }
    $tried = true;

    $dir   = FCPATH . 'modules/careers/language/';
    $idiom = get_option('active_language') ?: 'english';
    if (!is_file($dir . $idiom . '/careers_lang.php')) {
        $idiom = 'english';
    }
    if (is_file($dir . $idiom . '/careers_lang.php')) {
        get_instance()->lang->load('careers/careers', $idiom);
    }
}

function careers_can($capability = 'view')
{
    return is_admin() || has_permission('careers', '', $capability);
}

function careers_can_access()
{
    return is_admin()
        || has_permission('careers', '', 'view')
        || has_permission('careers', '', 'create')
        || has_permission('careers', '', 'edit');
}

function careers_can_settings()
{
    return is_admin() || has_permission('careers', '', 'settings');
}

/* ═════════════════════════════ 2. Lookups ════════════════════════════════ */

/**
 * Every kind of opening the company can publish.
 *
 * `schema` is Google's employmentType vocabulary — it goes straight into the
 * JobPosting structured data on the website, which is what gets a listing into
 * Google Jobs. `family` groups the long tail for the website filter chips.
 */
function careers_job_types()
{
    return [
        'full_time'      => ['label' => 'Full-time',      'schema' => 'FULL_TIME',  'family' => 'job',      'color' => '#0d9488', 'icon' => 'fa-solid fa-briefcase'],
        'part_time'      => ['label' => 'Part-time',      'schema' => 'PART_TIME',  'family' => 'job',      'color' => '#0284c7', 'icon' => 'fa-solid fa-clock'],
        'contract'       => ['label' => 'Contract',       'schema' => 'CONTRACTOR', 'family' => 'job',      'color' => '#7c3aed', 'icon' => 'fa-solid fa-file-signature'],
        'temporary'      => ['label' => 'Temporary',      'schema' => 'TEMPORARY',  'family' => 'job',      'color' => '#a16207', 'icon' => 'fa-solid fa-hourglass-half'],
        'freelance'      => ['label' => 'Freelance',      'schema' => 'CONTRACTOR', 'family' => 'job',      'color' => '#db2777', 'icon' => 'fa-solid fa-laptop-code'],
        'internship'     => ['label' => 'Internship',     'schema' => 'INTERN',     'family' => 'early',    'color' => '#2563eb', 'icon' => 'fa-solid fa-graduation-cap'],
        'apprenticeship' => ['label' => 'Apprenticeship', 'schema' => 'INTERN',     'family' => 'early',    'color' => '#ea580c', 'icon' => 'fa-solid fa-hammer'],
        'traineeship'    => ['label' => 'Traineeship',    'schema' => 'INTERN',     'family' => 'early',    'color' => '#0891b2', 'icon' => 'fa-solid fa-user-graduate'],
        'fellowship'     => ['label' => 'Fellowship',     'schema' => 'OTHER',      'family' => 'early',    'color' => '#4f46e5', 'icon' => 'fa-solid fa-award'],
        'volunteer'      => ['label' => 'Volunteer',      'schema' => 'VOLUNTEER',  'family' => 'other',    'color' => '#16a34a', 'icon' => 'fa-solid fa-hand-holding-heart'],
    ];
}

function careers_job_type_label($key)
{
    $types = careers_job_types();

    return isset($types[$key]) ? $types[$key]['label'] : ucfirst(str_replace('_', ' ', (string) $key));
}

function careers_job_type_meta($key)
{
    $types = careers_job_types();

    return isset($types[$key])
        ? $types[$key]
        : ['label' => careers_job_type_label($key), 'schema' => 'OTHER', 'family' => 'other', 'color' => '#64748b', 'icon' => 'fa-solid fa-briefcase'];
}

function careers_work_modes()
{
    return [
        'onsite' => ['label' => 'On-site', 'icon' => 'fa-solid fa-building'],
        'hybrid' => ['label' => 'Hybrid',  'icon' => 'fa-solid fa-shuffle'],
        'remote' => ['label' => 'Remote',  'icon' => 'fa-solid fa-house-laptop'],
    ];
}

function careers_work_mode_label($key)
{
    $m = careers_work_modes();

    return isset($m[$key]) ? $m[$key]['label'] : ucfirst((string) $key);
}

/**
 * Publishing lifecycle of a posting. Only `published` reaches the website.
 */
function careers_job_statuses()
{
    return [
        'draft'     => ['label' => 'Draft',     'color' => '#64748b', 'public' => false],
        'published' => ['label' => 'Published', 'color' => '#0d9488', 'public' => true],
        'paused'    => ['label' => 'Paused',    'color' => '#d97706', 'public' => false],
        'closed'    => ['label' => 'Closed',    'color' => '#dc2626', 'public' => false],
        'archived'  => ['label' => 'Archived',  'color' => '#94a3b8', 'public' => false],
    ];
}

function careers_job_status_label($key)
{
    $s = careers_job_statuses();

    return isset($s[$key]) ? $s[$key]['label'] : ucfirst((string) $key);
}

function careers_job_status_color($key)
{
    $s = careers_job_statuses();

    return isset($s[$key]) ? $s[$key]['color'] : '#64748b';
}

/**
 * The ATS pipeline. `open` stages sit on the kanban board; `terminal` stages
 * stop time-to-hire counting. `notify` marks the stages whose change sends the
 * candidate an email by default (still gated by the settings toggle).
 */
function careers_stages()
{
    return [
        'new'         => ['label' => 'New',          'color' => '#0284c7', 'open' => true,  'terminal' => false, 'notify' => false, 'order' => 1],
        'screening'   => ['label' => 'Screening',    'color' => '#7c3aed', 'open' => true,  'terminal' => false, 'notify' => false, 'order' => 2],
        'shortlisted' => ['label' => 'Shortlisted',  'color' => '#2563eb', 'open' => true,  'terminal' => false, 'notify' => true,  'order' => 3],
        'interview'   => ['label' => 'Interview',    'color' => '#d97706', 'open' => true,  'terminal' => false, 'notify' => true,  'order' => 4],
        'offer'       => ['label' => 'Offer',        'color' => '#0d9488', 'open' => true,  'terminal' => false, 'notify' => true,  'order' => 5],
        'hired'       => ['label' => 'Hired',        'color' => '#15803d', 'open' => false, 'terminal' => true,  'notify' => true,  'order' => 6],
        'on_hold'     => ['label' => 'On Hold',      'color' => '#a16207', 'open' => false, 'terminal' => false, 'notify' => false, 'order' => 7],
        'rejected'    => ['label' => 'Not Selected', 'color' => '#dc2626', 'open' => false, 'terminal' => true,  'notify' => true,  'order' => 8],
        'withdrawn'   => ['label' => 'Withdrawn',    'color' => '#94a3b8', 'open' => false, 'terminal' => true,  'notify' => false, 'order' => 9],
    ];
}

function careers_stage_label($key)
{
    $s = careers_stages();

    return isset($s[$key]) ? $s[$key]['label'] : ucfirst(str_replace('_', ' ', (string) $key));
}

function careers_stage_color($key)
{
    $s = careers_stages();

    return isset($s[$key]) ? $s[$key]['color'] : '#64748b';
}

/** Stages shown as kanban columns, in board order. */
function careers_board_stages()
{
    return ['new', 'screening', 'shortlisted', 'interview', 'offer', 'hired'];
}

function careers_interview_modes()
{
    return [
        'video'     => ['label' => 'Video Call', 'icon' => 'fa-solid fa-video'],
        'phone'     => ['label' => 'Phone',      'icon' => 'fa-solid fa-phone'],
        'in_person' => ['label' => 'In Person',  'icon' => 'fa-solid fa-building-user'],
    ];
}

function careers_interview_statuses()
{
    return [
        'scheduled' => ['label' => 'Scheduled', 'color' => '#0284c7'],
        'completed' => ['label' => 'Completed', 'color' => '#0d9488'],
        'no_show'   => ['label' => 'No Show',   'color' => '#d97706'],
        'cancelled' => ['label' => 'Cancelled', 'color' => '#dc2626'],
    ];
}

/** Screening-question field types the job editor can add to the apply form. */
function careers_question_types()
{
    return [
        'text'     => 'Short text',
        'textarea' => 'Paragraph',
        'number'   => 'Number',
        'select'   => 'Dropdown',
        'radio'    => 'Single choice',
        'checkbox' => 'Multiple choice',
        'yesno'    => 'Yes / No',
        'date'     => 'Date',
        'url'      => 'Link',
    ];
}

function careers_salary_periods()
{
    return ['year' => 'per year', 'month' => 'per month', 'week' => 'per week', 'day' => 'per day', 'hour' => 'per hour'];
}

/** Google's unitText vocabulary for baseSalary. */
function careers_salary_period_schema($period)
{
    $map = ['year' => 'YEAR', 'month' => 'MONTH', 'week' => 'WEEK', 'day' => 'DAY', 'hour' => 'HOUR'];

    return isset($map[$period]) ? $map[$period] : 'MONTH';
}

/** Optional fields the job editor can switch on/off per posting. */
function careers_optional_form_fields()
{
    return [
        'phone'              => ['label' => 'Phone number',        'default' => 1],
        'current_location'   => ['label' => 'Current location',    'default' => 1],
        'total_experience'   => ['label' => 'Total experience',    'default' => 1],
        'current_company'    => ['label' => 'Current company',     'default' => 1],
        'current_ctc'        => ['label' => 'Current CTC',         'default' => 0],
        'expected_ctc'       => ['label' => 'Expected CTC',        'default' => 1],
        'notice_period'      => ['label' => 'Notice period',       'default' => 1],
        'linkedin_url'       => ['label' => 'LinkedIn profile',    'default' => 0],
        'portfolio_url'      => ['label' => 'Portfolio / GitHub',  'default' => 0],
        'cover_letter'       => ['label' => 'Cover letter',        'default' => 1],
        'resume'             => ['label' => 'Resume / CV upload',  'default' => 1],
    ];
}

/* ═════════════════════════════ 3. Options ════════════════════════════════ */

function careers_default_options()
{
    return [
        'careers_schema_version'      => CAREERS_SCHEMA_VERSION,
        'careers_company_name'        => '',
        'careers_site_url'            => '',   // public careers page, used in emails & alerts
        'careers_notify_emails'       => '',   // comma separated internal recipients
        'careers_ack_enabled'         => 1,    // auto acknowledgement to the candidate
        'careers_stage_email_enabled' => 1,    // email the candidate on shortlist/offer/reject
        'careers_admin_notify'        => 1,    // email the team on every new application
        'careers_allow_public_apply'  => 1,
        'careers_resume_required'     => 1,
        'careers_max_resume_mb'       => 5,
        'careers_allowed_ext'         => 'pdf,doc,docx,rtf,odt',
        'careers_default_currency'    => 'INR',
        'careers_default_country'     => 'India',
        'careers_auto_close_expired'  => 1,    // close postings past their deadline on cron
        'careers_dedupe_days'         => 30,   // block a repeat apply to the same job within N days
        'careers_retention_days'      => 0,    // 0 = keep forever; else purge rejected applicants
        'careers_alerts_enabled'      => 1,    // job-alert subscriptions from the website
        'careers_perks_json'          => '',   // optional perks shown on the website
        'careers_embed_enabled'       => 1,    // the paste-anywhere widget
        'careers_embed_domains'       => '',   // blank = any site may embed it
        'careers_last_cron'           => '',
    ];
}

function careers_opt($key, $default = null)
{
    $value    = get_option($key);
    $defaults = careers_default_options();

    if ($value === '' || $value === null) {
        if ($default !== null) {
            return $default;
        }

        return isset($defaults[$key]) ? $defaults[$key] : '';
    }

    return $value;
}

function careers_opt_bool($key)
{
    return (int) careers_opt($key) === 1;
}

function careers_company_name()
{
    return careers_opt('careers_company_name') ?: get_option('companyname');
}

/* ═════════════════════════════ 4. Schema ═════════════════════════════════ */

/**
 * Create-if-missing schema. Safe to run on every activation and cheap enough
 * to self-heal from the module bootstrap when the stored version lags, which
 * is what keeps installations upgraded without a manual re-activation.
 */
function careers_ensure_schema()
{
    $CI = &get_instance();
    $p  = db_prefix();
    $cs = $CI->db->char_set ?: 'utf8mb4';

    if (!$CI->db->table_exists($p . 'careers_departments')) {
        $CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}careers_departments` (
            `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(150) NOT NULL,
            `slug` VARCHAR(160) NOT NULL,
            `description` VARCHAR(500) DEFAULT NULL,
            `color` VARCHAR(20) NOT NULL DEFAULT '#0d9488',
            `active` TINYINT(1) NOT NULL DEFAULT 1,
            `sort_order` INT(11) NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `slug` (`slug`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$cs};");
    }

    if (!$CI->db->table_exists($p . 'careers_locations')) {
        $CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}careers_locations` (
            `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(150) NOT NULL,
            `city` VARCHAR(120) NOT NULL DEFAULT '',
            `state` VARCHAR(120) NOT NULL DEFAULT '',
            `country` VARCHAR(120) NOT NULL DEFAULT '',
            `address` VARCHAR(500) DEFAULT NULL,
            `postal_code` VARCHAR(30) NOT NULL DEFAULT '',
            `active` TINYINT(1) NOT NULL DEFAULT 1,
            `sort_order` INT(11) NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$cs};");
    }

    if (!$CI->db->table_exists($p . 'careers_jobs')) {
        $CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}careers_jobs` (
            `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `reference` VARCHAR(30) NOT NULL DEFAULT '',
            `title` VARCHAR(191) NOT NULL,
            `slug` VARCHAR(191) NOT NULL,
            `job_type` VARCHAR(30) NOT NULL DEFAULT 'full_time',
            `department_id` INT(11) NOT NULL DEFAULT 0,
            `location_id` INT(11) NOT NULL DEFAULT 0,
            `location_text` VARCHAR(191) NOT NULL DEFAULT '',
            `work_mode` VARCHAR(20) NOT NULL DEFAULT 'onsite',
            `summary` VARCHAR(500) NOT NULL DEFAULT '',
            `description` LONGTEXT DEFAULT NULL,
            `responsibilities` LONGTEXT DEFAULT NULL,
            `requirements` LONGTEXT DEFAULT NULL,
            `benefits` LONGTEXT DEFAULT NULL,
            `skills` VARCHAR(1000) NOT NULL DEFAULT '',
            `education` VARCHAR(255) NOT NULL DEFAULT '',
            `experience_min` DECIMAL(4,1) DEFAULT NULL,
            `experience_max` DECIMAL(4,1) DEFAULT NULL,
            `duration_months` INT(11) DEFAULT NULL,
            `stipend` VARCHAR(120) NOT NULL DEFAULT '',
            `salary_min` DECIMAL(15,2) DEFAULT NULL,
            `salary_max` DECIMAL(15,2) DEFAULT NULL,
            `salary_currency` VARCHAR(10) NOT NULL DEFAULT 'INR',
            `salary_period` VARCHAR(10) NOT NULL DEFAULT 'year',
            `salary_hidden` TINYINT(1) NOT NULL DEFAULT 0,
            `openings` INT(11) NOT NULL DEFAULT 1,
            `status` VARCHAR(20) NOT NULL DEFAULT 'draft',
            `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
            `is_urgent` TINYINT(1) NOT NULL DEFAULT 0,
            `apply_mode` VARCHAR(20) NOT NULL DEFAULT 'internal',
            `external_url` VARCHAR(500) NOT NULL DEFAULT '',
            `form_fields` TEXT DEFAULT NULL,
            `seo_title` VARCHAR(191) NOT NULL DEFAULT '',
            `seo_description` VARCHAR(500) NOT NULL DEFAULT '',
            `hiring_manager` INT(11) NOT NULL DEFAULT 0,
            `published_at` DATETIME DEFAULT NULL,
            `deadline` DATE DEFAULT NULL,
            `closed_at` DATETIME DEFAULT NULL,
            `views` INT(11) NOT NULL DEFAULT 0,
            `sort_order` INT(11) NOT NULL DEFAULT 0,
            `created_by` INT(11) NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `slug` (`slug`),
            KEY `status_type` (`status`,`job_type`),
            KEY `department_id` (`department_id`),
            KEY `location_id` (`location_id`),
            KEY `published_at` (`published_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$cs};");
    }

    if (!$CI->db->table_exists($p . 'careers_questions')) {
        $CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}careers_questions` (
            `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `job_id` INT(11) UNSIGNED NOT NULL,
            `question` VARCHAR(500) NOT NULL,
            `type` VARCHAR(20) NOT NULL DEFAULT 'text',
            `options` TEXT DEFAULT NULL,
            `required` TINYINT(1) NOT NULL DEFAULT 0,
            `knockout_answer` VARCHAR(191) NOT NULL DEFAULT '',
            `sort_order` INT(11) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            KEY `job_id` (`job_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$cs};");
    }

    if (!$CI->db->table_exists($p . 'careers_applications')) {
        $CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}careers_applications` (
            `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `reference` VARCHAR(30) NOT NULL DEFAULT '',
            `job_id` INT(11) UNSIGNED NOT NULL DEFAULT 0,
            `name` VARCHAR(191) NOT NULL,
            `email` VARCHAR(191) NOT NULL,
            `phone` VARCHAR(60) NOT NULL DEFAULT '',
            `current_location` VARCHAR(191) NOT NULL DEFAULT '',
            `current_company` VARCHAR(191) NOT NULL DEFAULT '',
            `current_designation` VARCHAR(191) NOT NULL DEFAULT '',
            `total_experience` DECIMAL(4,1) DEFAULT NULL,
            `current_ctc` VARCHAR(60) NOT NULL DEFAULT '',
            `expected_ctc` VARCHAR(60) NOT NULL DEFAULT '',
            `notice_period` VARCHAR(60) NOT NULL DEFAULT '',
            `linkedin_url` VARCHAR(500) NOT NULL DEFAULT '',
            `portfolio_url` VARCHAR(500) NOT NULL DEFAULT '',
            `cover_letter` TEXT DEFAULT NULL,
            `resume_file` VARCHAR(255) NOT NULL DEFAULT '',
            `resume_name` VARCHAR(255) NOT NULL DEFAULT '',
            `answers` TEXT DEFAULT NULL,
            `stage` VARCHAR(20) NOT NULL DEFAULT 'new',
            `rating` TINYINT(1) NOT NULL DEFAULT 0,
            `is_starred` TINYINT(1) NOT NULL DEFAULT 0,
            `tags` VARCHAR(500) NOT NULL DEFAULT '',
            `assigned_to` INT(11) NOT NULL DEFAULT 0,
            `source` VARCHAR(60) NOT NULL DEFAULT 'website',
            `utm` VARCHAR(500) NOT NULL DEFAULT '',
            `ip_address` VARCHAR(45) NOT NULL DEFAULT '',
            `user_agent` VARCHAR(255) NOT NULL DEFAULT '',
            `token` VARCHAR(48) NOT NULL DEFAULT '',
            `rejection_reason` VARCHAR(500) NOT NULL DEFAULT '',
            `stage_changed_at` DATETIME DEFAULT NULL,
            `hired_at` DATETIME DEFAULT NULL,
            `last_activity_at` DATETIME DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `job_stage` (`job_id`,`stage`),
            KEY `email` (`email`),
            KEY `created_at` (`created_at`),
            KEY `assigned_to` (`assigned_to`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$cs};");
    }

    if (!$CI->db->table_exists($p . 'careers_activity')) {
        $CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}careers_activity` (
            `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `application_id` INT(11) UNSIGNED NOT NULL,
            `staff_id` INT(11) NOT NULL DEFAULT 0,
            `type` VARCHAR(20) NOT NULL DEFAULT 'note',
            `content` TEXT DEFAULT NULL,
            `meta` TEXT DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `application_id` (`application_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$cs};");
    }

    if (!$CI->db->table_exists($p . 'careers_interviews')) {
        $CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}careers_interviews` (
            `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `application_id` INT(11) UNSIGNED NOT NULL,
            `job_id` INT(11) UNSIGNED NOT NULL DEFAULT 0,
            `title` VARCHAR(191) NOT NULL DEFAULT 'Interview',
            `round` INT(11) NOT NULL DEFAULT 1,
            `mode` VARCHAR(20) NOT NULL DEFAULT 'video',
            `location` VARCHAR(500) NOT NULL DEFAULT '',
            `meeting_link` VARCHAR(500) NOT NULL DEFAULT '',
            `scheduled_at` DATETIME NOT NULL,
            `duration` INT(11) NOT NULL DEFAULT 45,
            `interviewers` VARCHAR(255) NOT NULL DEFAULT '',
            `status` VARCHAR(20) NOT NULL DEFAULT 'scheduled',
            `feedback` TEXT DEFAULT NULL,
            `rating` TINYINT(1) NOT NULL DEFAULT 0,
            `notify_candidate` TINYINT(1) NOT NULL DEFAULT 1,
            `reminder_sent` TINYINT(1) NOT NULL DEFAULT 0,
            `created_by` INT(11) NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `application_id` (`application_id`),
            KEY `scheduled_at` (`scheduled_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$cs};");
    }

    if (!$CI->db->table_exists($p . 'careers_subscribers')) {
        $CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}careers_subscribers` (
            `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `email` VARCHAR(191) NOT NULL,
            `name` VARCHAR(191) NOT NULL DEFAULT '',
            `departments` VARCHAR(255) NOT NULL DEFAULT '',
            `job_types` VARCHAR(255) NOT NULL DEFAULT '',
            `token` VARCHAR(48) NOT NULL DEFAULT '',
            `active` TINYINT(1) NOT NULL DEFAULT 1,
            `last_sent_at` DATETIME DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `email` (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$cs};");
    }

    // Daily rollup — one row per job per day, so the dashboard charts never
    // scan the applications table and view counts stay cheap to write.
    if (!$CI->db->table_exists($p . 'careers_job_stats')) {
        $CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}careers_job_stats` (
            `job_id` INT(11) UNSIGNED NOT NULL,
            `stat_date` DATE NOT NULL,
            `views` INT(11) NOT NULL DEFAULT 0,
            `applications` INT(11) NOT NULL DEFAULT 0,
            PRIMARY KEY (`job_id`,`stat_date`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$cs};");
    }

    careers_ensure_columns();
    careers_ensure_indexes();

    update_option('careers_schema_version', CAREERS_SCHEMA_VERSION);
}

/**
 * Indexes that only matter once a table grows.
 *
 * `stage` alone is the hot filter — every admin page counts the untouched
 * applications for the sidebar badge, and the composite (job_id, stage) index
 * cannot serve a query that does not name a job. Existence is checked with
 * SHOW INDEX rather than CREATE INDEX IF NOT EXISTS, which MySQL does not have.
 */
function careers_ensure_indexes()
{
    $CI = &get_instance();
    $p  = db_prefix();

    $indexes = [
        'careers_applications' => ['careers_stage_idx' => '`stage`'],
        'careers_jobs'         => ['careers_slug_status_idx' => '`status`,`is_featured`'],
    ];

    foreach ($indexes as $table => $definitions) {
        if (!$CI->db->table_exists($p . $table)) {
            continue;
        }

        foreach ($definitions as $name => $columns) {
            $exists = $CI->db->query("SHOW INDEX FROM `{$p}{$table}` WHERE Key_name = ?", [$name])->num_rows();

            if (!$exists) {
                $CI->db->query("ALTER TABLE `{$p}{$table}` ADD INDEX `{$name}` ({$columns})");
            }
        }
    }
}

/**
 * Columns introduced after the first release. Adding them here (rather than in
 * the CREATE above only) is what upgrades an installation that already has the
 * tables — the CREATE never runs a second time.
 */
function careers_ensure_columns()
{
    $CI = &get_instance();
    $p  = db_prefix();

    $additions = [
        'careers_jobs' => [
            'duration_months' => "INT(11) DEFAULT NULL AFTER `experience_max`",
            'stipend'         => "VARCHAR(120) NOT NULL DEFAULT '' AFTER `duration_months`",
            'is_urgent'       => "TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_featured`",
            'apply_mode'      => "VARCHAR(20) NOT NULL DEFAULT 'internal' AFTER `is_urgent`",
            'external_url'    => "VARCHAR(500) NOT NULL DEFAULT '' AFTER `apply_mode`",
            'form_fields'     => "TEXT DEFAULT NULL AFTER `external_url`",
            'closed_at'       => "DATETIME DEFAULT NULL AFTER `deadline`",
        ],
        'careers_applications' => [
            'rejection_reason' => "VARCHAR(500) NOT NULL DEFAULT '' AFTER `token`",
            'hired_at'         => "DATETIME DEFAULT NULL AFTER `stage_changed_at`",
            'utm'              => "VARCHAR(500) NOT NULL DEFAULT '' AFTER `source`",
        ],
        'careers_interviews' => [
            'reminder_sent' => "TINYINT(1) NOT NULL DEFAULT 0 AFTER `notify_candidate`",
        ],
    ];

    foreach ($additions as $table => $columns) {
        if (!$CI->db->table_exists($p . $table)) {
            continue;
        }
        foreach ($columns as $column => $definition) {
            if (!$CI->db->field_exists($column, $p . $table)) {
                $CI->db->query("ALTER TABLE `{$p}{$table}` ADD COLUMN `{$column}` {$definition}");
            }
        }
    }
}

/**
 * Cheap guard called from the module bootstrap: only touches the database when
 * the stored schema version is behind the code.
 */
function careers_maybe_upgrade_schema()
{
    if (get_option('careers_schema_version') === CAREERS_SCHEMA_VERSION) {
        return;
    }

    careers_ensure_schema();

    foreach (careers_default_options() as $option => $value) {
        add_option($option, $value);
    }
}

/* ═══════════════════════════ 5. Formatting ══════════════════════════════ */

/**
 * URL-safe slug, made unique against a table so the public /careers/{slug}
 * link of an existing posting is never stolen by a new one with the same title.
 */
function careers_slugify($text, $table = null, $ignore_id = 0, $column = 'slug')
{
    $slug = strtolower(trim((string) $text));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim(preg_replace('/-+/', '-', $slug), '-');

    if ($slug === '') {
        $slug = 'item-' . substr(md5(microtime(true) . mt_rand()), 0, 6);
    }
    $slug = substr($slug, 0, 150);

    if ($table === null) {
        return $slug;
    }

    $CI     = &get_instance();
    $base   = $slug;
    $suffix = 1;

    while (true) {
        $CI->db->where($column, $slug);
        if ($ignore_id) {
            $CI->db->where('id !=', (int) $ignore_id);
        }
        if ((int) $CI->db->count_all_results(db_prefix() . $table) === 0) {
            return $slug;
        }
        $suffix++;
        $slug = $base . '-' . $suffix;
    }
}

/** Human reference numbers: JOB-2026-0007 / APP-2026-000123. */
function careers_next_reference($prefix, $table)
{
    $CI    = &get_instance();
    $year  = date('Y');
    $count = (int) $CI->db
        ->like('reference', $prefix . '-' . $year . '-', 'after')
        ->count_all_results(db_prefix() . $table);

    $pad = $prefix === 'APP' ? 5 : 4;

    return $prefix . '-' . $year . '-' . str_pad((string) ($count + 1), $pad, '0', STR_PAD_LEFT);
}

/**
 * Compact money for a listing card: 12,00,000 stays readable as 12L only in
 * INR, so anything else falls back to thousands/millions.
 */
function careers_format_amount($amount, $currency = 'INR')
{
    $amount = (float) $amount;

    if ($currency === 'INR') {
        if ($amount >= 10000000) {
            return rtrim(rtrim(number_format($amount / 10000000, 2, '.', ''), '0'), '.') . ' Cr';
        }
        if ($amount >= 100000) {
            return rtrim(rtrim(number_format($amount / 100000, 2, '.', ''), '0'), '.') . ' L';
        }
        if ($amount >= 1000) {
            return rtrim(rtrim(number_format($amount / 1000, 1, '.', ''), '0'), '.') . 'K';
        }

        return number_format($amount, 0);
    }

    if ($amount >= 1000000) {
        return rtrim(rtrim(number_format($amount / 1000000, 2, '.', ''), '0'), '.') . 'M';
    }
    if ($amount >= 1000) {
        return rtrim(rtrim(number_format($amount / 1000, 1, '.', ''), '0'), '.') . 'K';
    }

    return number_format($amount, 0);
}

function careers_currency_symbol($code)
{
    $symbols = ['INR' => '₹', 'USD' => '$', 'AED' => 'AED ', 'SAR' => 'SAR ', 'EUR' => '€', 'GBP' => '£', 'QAR' => 'QAR ', 'OMR' => 'OMR ', 'KWD' => 'KWD ', 'BHD' => 'BHD '];

    return isset($symbols[$code]) ? $symbols[$code] : ($code . ' ');
}

/**
 * "₹8L – ₹14L per year", "Stipend ₹15,000/month", or '' when the posting hides
 * compensation. Used identically by the admin list and the public API.
 */
function careers_salary_text($job)
{
    if (!empty($job->salary_hidden)) {
        return '';
    }

    $currency = !empty($job->salary_currency) ? $job->salary_currency : 'INR';
    $symbol   = careers_currency_symbol($currency);
    $period   = careers_salary_periods();
    $suffix   = isset($period[$job->salary_period]) ? ' ' . $period[$job->salary_period] : '';

    $min = isset($job->salary_min) && $job->salary_min !== null && (float) $job->salary_min > 0 ? (float) $job->salary_min : null;
    $max = isset($job->salary_max) && $job->salary_max !== null && (float) $job->salary_max > 0 ? (float) $job->salary_max : null;

    if ($min === null && $max === null) {
        return !empty($job->stipend) ? 'Stipend ' . $job->stipend : '';
    }
    if ($min !== null && $max !== null) {
        return $symbol . careers_format_amount($min, $currency) . ' – ' . $symbol . careers_format_amount($max, $currency) . $suffix;
    }

    $one = $min !== null ? $min : $max;

    return ($min !== null ? 'From ' : 'Up to ') . $symbol . careers_format_amount($one, $currency) . $suffix;
}

/** "2 – 5 yrs", "Fresher", "5+ yrs". */
function careers_experience_text($job)
{
    $min = isset($job->experience_min) && $job->experience_min !== null ? (float) $job->experience_min : null;
    $max = isset($job->experience_max) && $job->experience_max !== null ? (float) $job->experience_max : null;

    if ($min === null && $max === null) {
        return '';
    }
    if (($min === null || $min == 0) && ($max === null || $max == 0)) {
        return 'Fresher';
    }
    if ($min !== null && $max !== null && $max > 0) {
        return careers_trim_number($min) . ' – ' . careers_trim_number($max) . ' yrs';
    }
    if ($min !== null && $min > 0) {
        return careers_trim_number($min) . '+ yrs';
    }

    return 'Up to ' . careers_trim_number($max) . ' yrs';
}

function careers_trim_number($number)
{
    return rtrim(rtrim(number_format((float) $number, 1, '.', ''), '0'), '.');
}

/** Where the job is, as one line. */
function careers_location_text($job)
{
    if (!empty($job->location_text)) {
        return $job->location_text;
    }

    $parts = array_filter([
        isset($job->loc_city) ? $job->loc_city : null,
        isset($job->loc_state) ? $job->loc_state : null,
        isset($job->loc_country) ? $job->loc_country : null,
    ], static function ($v) {
        return $v !== null && $v !== '';
    });

    if (empty($parts) && !empty($job->location_name)) {
        return $job->location_name;
    }

    return implode(', ', $parts);
}

/** "3 days ago" — used on cards and the pipeline board. */
function careers_time_ago($datetime)
{
    if (empty($datetime) || $datetime === '0000-00-00 00:00:00') {
        return '';
    }

    $seconds = time() - strtotime($datetime);
    if ($seconds < 0) {
        return 'just now';
    }
    if ($seconds < 60) {
        return 'just now';
    }
    if ($seconds < 3600) {
        $m = (int) ($seconds / 60);

        return $m . ' min' . ($m === 1 ? '' : 's') . ' ago';
    }
    if ($seconds < 86400) {
        $h = (int) ($seconds / 3600);

        return $h . ' hour' . ($h === 1 ? '' : 's') . ' ago';
    }
    $d = (int) ($seconds / 86400);
    if ($d < 31) {
        return $d . ' day' . ($d === 1 ? '' : 's') . ' ago';
    }
    if ($d < 365) {
        $mo = (int) ($d / 30);

        return $mo . ' month' . ($mo === 1 ? '' : 's') . ' ago';
    }
    $y = (int) ($d / 365);

    return $y . ' year' . ($y === 1 ? '' : 's') . ' ago';
}

/** Comma / newline separated skills → clean array. */
function careers_split_list($value, $limit = 40)
{
    $parts = preg_split('/[\r\n,;]+/', (string) $value);
    $out   = [];

    foreach ($parts as $part) {
        $part = trim($part);
        if ($part !== '' && !in_array($part, $out, true)) {
            $out[] = $part;
        }
        if (count($out) >= $limit) {
            break;
        }
    }

    return $out;
}

/**
 * Admin-entered rich text is rendered on a public website, so it is filtered
 * down to the tags a job description actually needs — anything else (script,
 * iframe, style, event attributes) never leaves the CRM.
 */
function careers_safe_html($html)
{
    $html = (string) $html;

    if (trim($html) === '') {
        return '';
    }

    $html = preg_replace('#<(script|style|iframe|object|embed|form|link|meta)\b[^>]*>.*?</\1>#is', '', $html);
    $html = preg_replace('#<(script|style|iframe|object|embed|form|link|meta)\b[^>]*/?>#is', '', $html);
    $html = strip_tags($html, '<p><br><b><strong><i><em><u><ul><ol><li><h2><h3><h4><h5><blockquote><a><span><div><table><thead><tbody><tr><th><td><hr><code><pre>');
    // Strip inline event handlers and javascript: URLs left on allowed tags.
    $html = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
    $html = preg_replace('/(href|src)\s*=\s*("|\')\s*javascript:[^"\']*\2/i', '$1="#"', $html);

    return trim($html);
}

/** Rich text → plain text, for meta descriptions and email previews. */
function careers_excerpt($html, $length = 180)
{
    $text = trim(preg_replace('/\s+/', ' ', strip_tags((string) $html)));

    if (mb_strlen($text) <= $length) {
        return $text;
    }

    return rtrim(mb_substr($text, 0, $length - 1), " ,.;:-") . '…';
}

/* ══════════════════════════════ 6. Files ════════════════════════════════ */

/**
 * Where resumes live. Kept outside the module directory (which a module update
 * would overwrite) and served only through the permission-checked
 * careers/resume/{id} controller — never by a direct URL.
 */
function careers_upload_path($job_id = 0)
{
    // perfex_saas redefines the upload base per tenant; honouring it keeps every
    // instance writing into its own folder instead of the master's.
    $base = defined('PERFEX_SAAS_UPLOAD_BASE_DIR') ? FCPATH . PERFEX_SAAS_UPLOAD_BASE_DIR : FCPATH . 'uploads/';
    $path = rtrim($base, '/') . '/careers/';

    if ($job_id) {
        $path .= (int) $job_id . '/';
    }

    return $path;
}

function careers_allowed_extensions()
{
    $raw = strtolower((string) careers_opt('careers_allowed_ext'));
    $out = [];

    foreach (careers_split_list($raw, 20) as $ext) {
        $ext = ltrim(trim($ext), '.');
        if (preg_match('/^[a-z0-9]{1,6}$/', $ext)) {
            $out[] = $ext;
        }
    }

    return $out ?: ['pdf', 'doc', 'docx'];
}

/**
 * Store an uploaded resume.
 *
 * @param  array $file $_FILES entry
 * @param  int   $job_id
 * @return array ['ok' => bool, 'error' => string, 'file' => stored name, 'name' => original name]
 */
function careers_store_resume(array $file, $job_id)
{
    if (empty($file['name']) || !isset($file['tmp_name']) || $file['tmp_name'] === '') {
        return ['ok' => false, 'error' => 'No file received.'];
    }

    if (!empty($file['error'])) {
        return ['ok' => false, 'error' => 'Upload failed, please try again.'];
    }

    $max_bytes = max(1, (int) careers_opt('careers_max_resume_mb')) * 1024 * 1024;
    if ((int) $file['size'] > $max_bytes) {
        return ['ok' => false, 'error' => 'File is too large. Maximum ' . (int) careers_opt('careers_max_resume_mb') . ' MB.'];
    }

    $original  = (string) $file['name'];
    $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    $allowed   = careers_allowed_extensions();

    if (!in_array($extension, $allowed, true)) {
        return ['ok' => false, 'error' => 'Unsupported file type. Allowed: ' . strtoupper(implode(', ', $allowed)) . '.'];
    }

    // is_uploaded_file() would reject the proxied upload the API controller
    // re-materialises, so the caller is trusted for the move but never for the
    // name: the stored filename is generated here and carries a safe extension.
    $directory = careers_upload_path($job_id);
    if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
        return ['ok' => false, 'error' => 'Storage unavailable, please email your CV instead.'];
    }

    if (!is_file($directory . 'index.html')) {
        @file_put_contents($directory . 'index.html', '');
    }

    $stored = date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $extension;

    $moved = @move_uploaded_file($file['tmp_name'], $directory . $stored)
        || @rename($file['tmp_name'], $directory . $stored)
        || @copy($file['tmp_name'], $directory . $stored);

    if (!$moved) {
        return ['ok' => false, 'error' => 'Could not save the file, please try again.'];
    }

    @chmod($directory . $stored, 0644);

    return [
        'ok'    => true,
        'error' => '',
        'file'  => $stored,
        'name'  => substr(preg_replace('/[^\w \-\.\(\)]+/u', '', $original), 0, 200) ?: ('resume.' . $extension),
    ];
}

function careers_resume_full_path($application)
{
    if (empty($application->resume_file)) {
        return '';
    }

    // Stored names are generated by careers_store_resume(); basename() keeps a
    // hand-edited row from ever reaching outside the uploads directory.
    $path = careers_upload_path($application->job_id) . basename($application->resume_file);

    return is_file($path) ? $path : '';
}

/* ═══════════════════════════ 7. Public shape ════════════════════════════ */

/**
 * The exact job row the marketing website receives. One function so the list
 * endpoint, the detail endpoint and the alert emails can never drift apart —
 * and so nothing internal (hiring manager, internal notes, counts) leaks.
 */
function careers_public_job($job, $detailed = false)
{
    $type = careers_job_type_meta($job->job_type);

    $row = [
        'id'              => (int) $job->id,
        'reference'       => (string) $job->reference,
        'title'           => (string) $job->title,
        'slug'            => (string) $job->slug,
        'type'            => (string) $job->job_type,
        'type_label'      => $type['label'],
        'type_family'     => $type['family'],
        'type_schema'     => $type['schema'],
        'department'      => isset($job->department_name) ? (string) $job->department_name : '',
        'work_mode'       => (string) $job->work_mode,
        'work_mode_label' => careers_work_mode_label($job->work_mode),
        'location'        => careers_location_text($job),
        'city'            => isset($job->loc_city) ? (string) $job->loc_city : '',
        'state'           => isset($job->loc_state) ? (string) $job->loc_state : '',
        'country'         => isset($job->loc_country) ? (string) $job->loc_country : (string) careers_opt('careers_default_country'),
        'experience'      => careers_experience_text($job),
        'experience_min'  => $job->experience_min !== null ? (float) $job->experience_min : null,
        'experience_max'  => $job->experience_max !== null ? (float) $job->experience_max : null,
        'education'       => (string) $job->education,
        'salary'          => careers_salary_text($job),
        'salary_min'      => (!$job->salary_hidden && $job->salary_min !== null) ? (float) $job->salary_min : null,
        'salary_max'      => (!$job->salary_hidden && $job->salary_max !== null) ? (float) $job->salary_max : null,
        'salary_currency' => (string) $job->salary_currency,
        'salary_period'   => (string) $job->salary_period,
        'salary_unit'     => careers_salary_period_schema($job->salary_period),
        'stipend'         => (string) $job->stipend,
        'duration_months' => $job->duration_months !== null ? (int) $job->duration_months : null,
        'openings'        => (int) $job->openings,
        'skills'          => careers_split_list($job->skills),
        'summary'         => $job->summary !== '' ? (string) $job->summary : careers_excerpt($job->description),
        'featured'        => (int) $job->is_featured === 1,
        'urgent'          => (int) $job->is_urgent === 1,
        'apply_mode'      => (string) $job->apply_mode,
        'external_url'    => (string) $job->external_url,
        'posted_at'       => $job->published_at ?: $job->created_at,
        'posted_iso'      => careers_iso_date($job->published_at ?: $job->created_at),
        'posted_ago'      => careers_time_ago($job->published_at ?: $job->created_at),
        'deadline'        => (!empty($job->deadline) && $job->deadline !== '0000-00-00') ? $job->deadline : null,
        'deadline_iso'    => (!empty($job->deadline) && $job->deadline !== '0000-00-00') ? careers_iso_date($job->deadline . ' 23:59:59') : null,
    ];

    if ($detailed) {
        $row['description']      = careers_safe_html($job->description);
        $row['responsibilities'] = careers_safe_html($job->responsibilities);
        $row['requirements']     = careers_safe_html($job->requirements);
        $row['benefits']         = careers_safe_html($job->benefits);
        $row['seo_title']        = $job->seo_title !== '' ? (string) $job->seo_title : ($job->title . ' — Careers');
        $row['seo_description']  = $job->seo_description !== '' ? (string) $job->seo_description : careers_excerpt($job->summary ?: $job->description, 155);
        $row['address']          = isset($job->loc_address) ? (string) $job->loc_address : '';
        $row['postal_code']      = isset($job->loc_postal) ? (string) $job->loc_postal : '';
        $row['form_fields']      = careers_job_form_fields($job);
    }

    return $row;
}

/**
 * A date the database can safely store, or null.
 *
 * to_sql_date() hands back whatever it was given when the value does not match
 * the CRM's date format, and MySQL turns that into 0000-00-00. Every reader in
 * this module treats a zero date as "no deadline", but a SQL date comparison
 * reads it as long past — so a posting saved that way is published in the CRM
 * and missing from the website. Only a real calendar date survives this.
 */
function careers_sql_date_or_null($value)
{
    $value = trim((string) $value);

    if ($value === '' || $value === '0000-00-00') {
        return null;
    }

    $sql = (string) to_sql_date($value);

    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $sql, $parts)) {
        return null;
    }

    return checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]) ? $sql : null;
}

function careers_iso_date($datetime)
{
    if (empty($datetime) || $datetime === '0000-00-00 00:00:00' || $datetime === '0000-00-00') {
        return null;
    }

    $timestamp = strtotime($datetime);

    return $timestamp ? date('c', $timestamp) : null;
}

/**
 * Which optional fields this posting's apply form shows. Stored as JSON on the
 * job; a posting saved before a new field existed falls back to its default.
 */
function careers_job_form_fields($job)
{
    $defaults = careers_optional_form_fields();
    $saved    = [];

    if (!empty($job->form_fields)) {
        $decoded = json_decode((string) $job->form_fields, true);
        if (is_array($decoded)) {
            $saved = $decoded;
        }
    }

    $out = [];
    foreach ($defaults as $key => $meta) {
        $out[$key] = isset($saved[$key]) ? (int) (bool) $saved[$key] : (int) $meta['default'];
    }

    // A resume is either required by settings or optional, but the field itself
    // must exist whenever the posting accepts it.
    if (careers_opt_bool('careers_resume_required')) {
        $out['resume'] = 1;
    }

    return $out;
}

/** Screening questions as the website needs them (no knockout answers). */
function careers_public_question($question)
{
    return [
        'id'       => (int) $question->id,
        'question' => (string) $question->question,
        'type'     => (string) $question->type,
        'options'  => careers_split_list($question->options, 30),
        'required' => (int) $question->required === 1,
    ];
}

/* ══════════════════════════════ 8. Mail ═════════════════════════════════ */

/**
 * Send one of the module's emails.
 *
 * The body comes from a module view and is wrapped in the CRM's own Email
 * Header / Footer exactly the way core does it, so these carry the same
 * branding as every other email the installation sends — and, where the Omni
 * Messaging module is active, the same delivery pipeline.
 *
 * @param  string|array $to
 * @param  string       $subject
 * @param  string       $view    view name under modules/careers/views/
 * @param  array        $data    view data
 * @param  string       $attachment absolute path, optional
 * @return bool
 */
function careers_send_email($to, $subject, $view, array $data = [], $attachment = '')
{
    $recipients = is_array($to) ? $to : careers_split_list($to, 20);
    $recipients = array_values(array_filter($recipients, static function ($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }));

    if (empty($recipients)) {
        return false;
    }

    $CI = &get_instance();

    $data['company']      = careers_company_name();
    $data['careers_url']  = careers_opt('careers_site_url');
    $body                 = $CI->load->view('careers/' . $view, $data, true);

    $template           = new stdClass();
    $template->message  = get_option('email_header') . $body . get_option('email_footer');
    // A stray newline in an admin-entered subject is header injection.
    $template->subject  = trim(preg_replace('/\s+/', ' ', (string) $subject));
    $template->fromname = careers_company_name();

    if (function_exists('parse_email_template')) {
        $template = parse_email_template($template);
    }

    try {
        $CI->load->library('email');
        $CI->load->config('email');
        $CI->email->clear(true);
        $CI->email->initialize();
        $CI->email->set_newline(config_item('newline'));
        $CI->email->from(get_option('smtp_email'), $template->fromname);
        $CI->email->to($recipients);
        $CI->email->subject($template->subject);
        $CI->email->set_mailtype('html');
        $CI->email->message($template->message);
        $CI->email->set_alt_message(strip_html_tags($template->message, '<br/>, <br>, <br />'));

        if ($attachment !== '' && is_file($attachment)) {
            $CI->email->attach($attachment);
        }

        if ($CI->email->send()) {
            return true;
        }

        log_activity('Careers: email send failed to ' . implode(',', $recipients) . ' — ' . strip_tags($CI->email->print_debugger(['headers'])));
    } catch (Exception $e) {
        log_activity('Careers: email exception — ' . $e->getMessage());
    }

    return false;
}

/** Internal recipients for new-application alerts. */
function careers_notification_recipients($job = null)
{
    $emails = careers_split_list(careers_opt('careers_notify_emails'), 20);

    if ($job && !empty($job->hiring_manager)) {
        $CI    = &get_instance();
        $staff = $CI->db->select('email')->where('staffid', (int) $job->hiring_manager)->get(db_prefix() . 'staff')->row();
        if ($staff && !empty($staff->email)) {
            $emails[] = $staff->email;
        }
    }

    return array_values(array_unique(array_filter($emails)));
}

/**
 * Public URL of a posting on the marketing website. Falls back to the CRM's
 * own site so a link is never dead while the website URL is unconfigured.
 */
function careers_public_job_url($job)
{
    $base = rtrim((string) careers_opt('careers_site_url'), '/');

    if ($base === '') {
        return site_url('careers');
    }

    return $base . '/' . (is_object($job) ? $job->slug : $job);
}
