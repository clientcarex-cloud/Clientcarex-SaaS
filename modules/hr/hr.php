<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: HR
Description: Complete HR management for hospitals - employees, attendance, shifts, leave, payroll, trainings, appraisals and exits.
Version: 1.0.0
Requires at least: 2.3.*
*/

define('HR_MODULE_NAME', 'hr');
define('HR_SCHEMA_VERSION', '136');

// SMS / WhatsApp / e-mail notifications for HR events, dispatched through the
// Omni Messaging hooks engine. Required (not CI-loaded) so the fire wrappers
// exist for cron and email-pipe bootstraps too, where no controller runs.
require_once __DIR__ . '/helpers/hr_hooks_helper.php';
// Workload + department-coverage snapshot behind the leave review popup, used
// by both the HR back office and the self-service approvals list.
require_once __DIR__ . '/helpers/hr_leave_review_helper.php';

hooks()->add_action('admin_init', 'hr_module_init_menu_items');
hooks()->add_action('admin_init', 'hr_module_init_approvals_menu');
// "My HR" self-service lives in the top-right profile dropdown (not the sidebar).
hooks()->add_action('admin_navbar_end', 'hr_self_header_menu');
hooks()->add_action('admin_init', 'hr_module_permissions');
hooks()->add_action('admin_init', 'hr_ensure_schema');
hooks()->add_action('after_cron_run', 'hr_birthday_notify_cron');
hooks()->add_action('after_cron_run', 'hr_payroll_reminder_cron');
hooks()->add_action('after_cron_run', 'hr_etime_sync_cron');
// Date-driven Omni Messaging hooks: birthdays, work anniversaries, probation
// endings and document expiries (once per calendar day).
hooks()->add_action('after_cron_run', 'hr_hook_daily_cron');
// Company notices addressed to the current user render on the main dashboard.
hooks()->add_action('after_dashboard', 'hr_notices_dashboard_widget', 4);
// Public birthday wishes render at the TOP of the main CRM dashboard.
hooks()->add_action('before_start_render_dashboard_content', 'hr_birthday_dashboard_widget', 1);

register_language_files(HR_MODULE_NAME, [HR_MODULE_NAME]);

register_activation_hook(HR_MODULE_NAME, 'hr_module_activation_hook');
function hr_module_activation_hook()
{
    require_once(__DIR__ . '/install.php');
}

/**
 * Self-heal schema on live installs where install.php never ran (module
 * updated in place). Guarded by an option so it costs one query per request.
 */
function hr_ensure_schema()
{
    if (get_option('hr_schema_version') !== HR_SCHEMA_VERSION) {
        require_once(__DIR__ . '/install.php');
    }
}

/**
 * Menu-wise permission map. Each HR menu is its own staff-permission feature so
 * access can be granted per section instead of one blanket "view" unlocking the
 * whole module. Keys are feature slugs; values describe the capabilities each
 * feature exposes and the human label shown on the staff-role screen.
 *
 * The `menu` key (when present) drives both the sidebar child and the parent
 * visibility: a user with `view` on any feature that has a menu sees HR.
 *
 * `hr` is the base feature: its `view` gates the Dashboard, its `edit` gates the
 * global Settings screen. `hr_payroll` is kept separate so salary data can be
 * restricted to a smaller group.
 */
function hr_permission_features()
{
    return [
        'hr' => [
            'name' => 'HR Dashboard',
            'caps' => ['view', 'edit'],
            'menu' => ['slug' => 'hr-dashboard', 'lang' => 'hr_dashboard', 'url' => 'hr', 'position' => 5],
        ],
        'hr_employees' => [
            'name' => 'HR Employees',
            'caps' => ['view', 'create', 'edit', 'delete'],
            'menu' => ['slug' => 'hr-employees', 'lang' => 'hr_employees', 'url' => 'hr/employees', 'position' => 10],
        ],
        'hr_attendance' => [
            'name' => 'HR Attendance',
            'caps' => ['view', 'edit'],
            'menu' => ['slug' => 'hr-attendance', 'lang' => 'hr_attendance', 'url' => 'hr/attendance', 'position' => 15],
        ],
        'hr_field_attendance' => [
            // No `create`: punches are created by employees from the self-service
            // portal (hr_self), never from this screen — edit = approve/reject.
            'name' => 'HR Field Attendance',
            'caps' => ['view', 'edit', 'delete'],
            'menu' => ['slug' => 'hr-field-attendance', 'lang' => 'hr_field_attendance', 'url' => 'hr/field_attendance', 'position' => 17],
        ],
        'hr_leaves' => [
            'name' => 'HR Leave',
            'caps' => ['view', 'create', 'edit', 'delete'],
            'menu' => ['slug' => 'hr-leaves', 'lang' => 'hr_leaves', 'url' => 'hr/leaves', 'position' => 20],
        ],
        'hr_shifts' => [
            'name' => 'HR Shifts',
            'caps' => ['view', 'create', 'edit', 'delete'],
            'menu' => ['slug' => 'hr-shifts', 'lang' => 'hr_shifts', 'url' => 'hr/shifts', 'position' => 25],
        ],
        'hr_holidays' => [
            'name' => 'HR Holidays',
            'caps' => ['view', 'create', 'edit', 'delete'],
            'menu' => ['slug' => 'hr-holidays', 'lang' => 'hr_holidays', 'url' => 'hr/holidays', 'position' => 30],
        ],
        'hr_payroll' => [
            'name' => 'HR Payroll',
            'caps' => ['view', 'create', 'edit', 'delete'],
            'menu' => ['slug' => 'hr-payroll', 'lang' => 'hr_payroll', 'url' => 'hr/payroll', 'position' => 35],
        ],
        'hr_trainings' => [
            'name' => 'HR Trainings',
            'caps' => ['view', 'create', 'edit', 'delete'],
            'menu' => ['slug' => 'hr-trainings', 'lang' => 'hr_trainings', 'url' => 'hr/trainings', 'position' => 40],
        ],
        'hr_appraisals' => [
            'name' => 'HR Appraisals',
            'caps' => ['view', 'create', 'edit', 'delete'],
            'menu' => ['slug' => 'hr-appraisals', 'lang' => 'hr_appraisals', 'url' => 'hr/appraisals', 'position' => 45],
        ],
        'hr_benefits' => [
            'name' => 'HR Benefits',
            'caps' => ['view', 'create', 'edit', 'delete'],
            'menu' => ['slug' => 'hr-benefits', 'lang' => 'hr_benefits', 'url' => 'hr/benefits', 'position' => 48],
        ],
        'hr_perks' => [
            'name' => 'HR Perks',
            'caps' => ['view', 'create', 'edit', 'delete'],
            'menu' => ['slug' => 'hr-perks', 'lang' => 'hr_perks', 'url' => 'hr/perks', 'position' => 49],
        ],
        'hr_memos' => [
            'name' => 'HR Memos / Disciplinary',
            'caps' => ['view', 'create', 'edit', 'delete'],
            'menu' => ['slug' => 'hr-memos', 'lang' => 'hr_memos', 'url' => 'hr/memos', 'position' => 46],
        ],
        'hr_onboarding' => [
            'name' => 'HR Onboarding',
            'caps' => ['view', 'create', 'edit', 'delete'],
            'menu' => ['slug' => 'hr-onboarding', 'lang' => 'hr_onboarding', 'url' => 'hr/onboarding', 'position' => 12],
        ],
        'hr_interviews' => [
            'name' => 'HR Interviews',
            'caps' => ['view', 'create', 'edit', 'delete'],
            'menu' => ['slug' => 'hr-interviews', 'lang' => 'hr_interviews', 'url' => 'hr/interviews', 'position' => 47],
        ],
        'hr_exits' => [
            'name' => 'HR Exits',
            'caps' => ['view', 'create', 'edit', 'delete'],
            'menu' => ['slug' => 'hr-exits', 'lang' => 'hr_exits', 'url' => 'hr/exits', 'position' => 50],
        ],
        'hr_notices' => [
            'name' => 'HR Notices',
            'caps' => ['view', 'create', 'edit', 'delete'],
            'menu' => ['slug' => 'hr-notices', 'lang' => 'hr_notices', 'url' => 'hr/notices', 'position' => 52],
        ],
        'hr_feedback' => [
            'name' => 'HR Suggestions / Feedback',
            'caps' => ['view', 'edit', 'delete'],
            'menu' => ['slug' => 'hr-feedback', 'lang' => 'hr_feedback', 'url' => 'hr/feedback', 'position' => 53],
        ],
        'hr_reports' => [
            'name' => 'HR Reports',
            'caps' => ['view'],
            'menu' => ['slug' => 'hr-reports', 'lang' => 'hr_reports', 'url' => 'hr/reports', 'position' => 55],
        ],
    ];
}

/**
 * Register one staff-permission feature per HR menu (see hr_permission_features).
 */
function hr_module_permissions()
{
    $labels = [
        'view'   => _l('permission_view') . '(' . _l('permission_global') . ')',
        'create' => _l('permission_create'),
        'edit'   => _l('permission_edit'),
        'delete' => _l('permission_delete'),
    ];

    foreach (hr_permission_features() as $feature => $meta) {
        $capabilities = ['capabilities' => []];
        foreach ($meta['caps'] as $cap) {
            $capabilities['capabilities'][$cap] = $labels[$cap];
        }
        register_staff_capabilities($feature, $capabilities, $meta['name']);
    }

    // Employee Self-Service is a separate feature so it can be granted to
    // ordinary staff (who should only ever see their OWN data) without giving
    // any of the manager-level HR permissions above.
    //   view   = access the "My HR" portal (own attendance, leave, payslips...)
    //   create = apply for leave (for self only)
    //   edit   = update own profile, limited to superadmin-enabled fields
    register_staff_capabilities('hr_self', ['capabilities' => [
        'view'   => $labels['view'],
        'create' => $labels['create'],
        'edit'   => $labels['edit'],
    ]], 'HR - My Portal (Self-Service)');
}

/**
 * True when the current user can reach any part of HR (admin, or `view` on any
 * HR feature). Used to decide whether the parent sidebar item shows at all.
 */
function hr_can_access_module()
{
    if (is_admin()) {
        return true;
    }
    foreach (array_keys(hr_permission_features()) as $feature) {
        if (has_permission($feature, '', 'view')) {
            return true;
        }
    }

    return false;
}

function hr_module_init_menu_items()
{
    $CI = &get_instance();

    // Build the list of menus the current user may actually see.
    $visible = [];
    foreach (hr_permission_features() as $feature => $meta) {
        if (empty($meta['menu'])) {
            continue;
        }
        if (!is_admin() && !has_permission($feature, '', 'view')) {
            continue;
        }
        // Field attendance is an opt-in feature; hide its menu while it is off.
        if ($feature === 'hr_field_attendance' && !hr_field_enabled()) {
            continue;
        }
        $m = $meta['menu'];
        $visible[] = [
            'slug'     => $m['slug'],
            'name'     => _l($m['lang']),
            'href'     => admin_url($m['url']),
            'position' => $m['position'],
        ];
    }

    // Settings is a child gated by the base feature's `edit` capability.
    if (is_admin() || has_permission('hr', '', 'edit')) {
        $visible[] = [
            'slug'     => 'hr-settings',
            'name'     => _l('settings'),
            'href'     => admin_url('hr/settings'),
            'position' => 60,
        ];
    }

    if (empty($visible)) {
        return;
    }

    // Parent points at the first section the user can open, so clicking the
    // header never lands on an access-denied page (e.g. a user with only
    // Attendance has no Dashboard).
    usort($visible, function ($a, $b) {
        return $a['position'] <=> $b['position'];
    });

    $CI->app_menu->add_sidebar_menu_item('hr', [
        'name'     => _l('hr'),
        'icon'     => 'fa fa-id-card',
        'href'     => $visible[0]['href'],
        'position' => 35,
    ]);

    foreach ($visible as $child) {
        $CI->app_menu->add_sidebar_children_item('hr', $child);
    }
}

/**
 * Employee Self-Service ("My HR"). Instead of a sidebar item, it is injected as
 * a submenu into the top-right profile dropdown (next to My Profile / My
 * Timesheets), so the personal portal lives with the other "my" links. Every
 * page it links to scopes strictly to the logged-in staff member (Myhr).
 */
function hr_self_header_menu()
{
    if (!is_admin() && !has_permission('hr_self', '', 'view')) {
        return;
    }

    $items = [
        ['name' => _l('hr_my_dashboard'),  'href' => admin_url('hr/myhr'),             'icon' => 'fa fa-gauge-high'],
        ['name' => _l('hr_my_attendance'), 'href' => admin_url('hr/myhr/attendance'), 'icon' => 'fa fa-calendar-check'],
        ['name' => _l('hr_field_punch'),   'href' => admin_url('hr/myhr/field_punch'), 'icon' => 'fa fa-location-dot', 'only_if' => 'field'],
        ['name' => _l('hr_my_leaves'),     'href' => admin_url('hr/myhr/leaves'),     'icon' => 'fa fa-plane'],
        ['name' => _l('hr_my_profile'),    'href' => admin_url('hr/myhr/profile'),    'icon' => 'fa fa-user'],
        ['name' => _l('hr_holidays'),      'href' => admin_url('hr/myhr/holidays'),   'icon' => 'fa fa-umbrella-beach'],
        ['name' => _l('hr_my_trainings'),  'href' => admin_url('hr/myhr/trainings'),  'icon' => 'fa fa-graduation-cap'],
        ['name' => _l('hr_my_appraisals'), 'href' => admin_url('hr/myhr/appraisals'), 'icon' => 'fa fa-chart-line'],
        ['name' => _l('hr_my_onboarding'), 'href' => admin_url('hr/myhr/onboarding'), 'icon' => 'fa fa-clipboard-check'],
        ['name' => _l('hr_my_memos'),      'href' => admin_url('hr/myhr/memos'),      'icon' => 'fa fa-file-signature'],
        ['name' => _l('hr_my_payslips'),   'href' => admin_url('hr/myhr/payslips'),   'icon' => 'fa fa-money-bill'],
        ['name' => _l('hr_feedback'),      'href' => admin_url('hr/myhr/feedback'),   'icon' => 'fa fa-bullhorn'],
    ];

    $sub = '';
    foreach ($items as $it) {
        // Field punch only shows when the feature is switched on in HR Settings.
        if (($it['only_if'] ?? '') === 'field' && !hr_field_enabled()) {
            continue;
        }
        $sub .= '<li><a href="' . $it['href'] . '"><i class="' . $it['icon'] . ' tw-mr-2 tw-text-neutral-400"></i>' . html_escape($it['name']) . '</a></li>';
    }
    $li = '<li class="dropdown-submenu pull-left header-my-hr">'
        . '<a href="' . admin_url('hr/myhr') . '" tabindex="-1"><i class="fa fa-id-badge tw-mr-2 tw-text-neutral-400"></i>' . html_escape(_l('hr_self')) . '</a>'
        . '<ul class="dropdown-menu">' . $sub . '</ul></li>';

    // Inject the item into the profile dropdown right after "My Timesheets"
    // (fallback: after "My Profile", else at the end, before Logout).
    echo '<script>(function(){try{'
        . 'var m=document.querySelector(".topbar-profile-menu");'
        . 'if(!m||m.querySelector(".header-my-hr"))return;'
        . 'var tmp=document.createElement("ul");tmp.innerHTML=' . json_encode($li) . ';var node=tmp.firstElementChild;'
        . 'var a=m.querySelector(".header-my-timesheets")||m.querySelector(".header-my-profile");'
        . 'if(a){a.parentNode.insertBefore(node,a.nextSibling);}else{var lo=m.querySelector(".header-logout");lo?m.insertBefore(node,lo):m.appendChild(node);}'
        . '}catch(e){}})();</script>';
}

/**
 * Curated set of employee-profile fields that a superadmin MAY allow employees
 * to edit themselves (Settings ▸ Employee Self-Service). Deliberately excludes
 * sensitive/authoritative fields (salary, employee code, department, joining
 * date, status, statutory IDs) which stay HR-controlled. slug => label.
 */
function hr_self_field_options()
{
    return [
        'personal_email'             => 'Personal Email',
        'alt_phone'                  => 'Alternate Phone',
        'present_address'            => 'Present Address',
        'permanent_address'          => 'Permanent Address',
        'marital_status'             => 'Marital Status',
        'blood_group'                => 'Blood Group',
        'emergency_contact_name'     => 'Emergency Contact Name',
        'emergency_contact_relation' => 'Emergency Contact Relation',
        'emergency_contact_phone'    => 'Emergency Contact Phone',
        'qualifications'             => 'Qualifications',
        'bank_name'                  => 'Bank Name',
        'bank_branch'                => 'Bank Branch',
        'bank_account_no'            => 'Bank Account No',
        'bank_ifsc'                  => 'Bank IFSC',
    ];
}

/**
 * Parse a CSV of field slugs, intersected with the curated whitelist so a
 * stale/hand-edited value can never expose a field outside hr_self_field_options().
 */
function hr_ess_parse_fields($csv)
{
    $enabled = array_filter(explode(',', (string) $csv), 'strlen');

    return array_values(array_intersect($enabled, array_keys(hr_self_field_options())));
}

/**
 * Parse a newline-separated document list into structured rows. A line that
 * starts with "*" is MANDATORY (HR's way of selecting must-upload documents,
 * shown in red in ESS). Returns [['name'=>string, 'mandatory'=>bool], ...],
 * trimmed, de-duped by name, order preserved.
 */
function hr_ess_parse_doc_lines($text)
{
    $out  = [];
    $seen = [];
    foreach (preg_split('/\r\n|\r|\n/', (string) $text) as $l) {
        $l = trim($l);
        if ($l === '') {
            continue;
        }
        $mandatory = ($l[0] === '*');
        if ($mandatory) {
            $l = trim(ltrim($l, '*'));
        }
        if ($l === '' || in_array($l, $seen, true)) {
            continue;
        }
        $seen[]  = $l;
        $out[]   = ['name' => $l, 'mandatory' => $mandatory];
    }

    return $out;
}

/**
 * Same list as plain names (no mandatory info). Kept for callers that only need
 * the document names.
 */
function hr_ess_parse_docs($text)
{
    return array_column(hr_ess_parse_doc_lines($text), 'name');
}

/**
 * GLOBAL self-editable profile fields (default when no role/employee override
 * applies). Kept for the settings screen; runtime should prefer the resolved
 * per-staff config via hr_ess_config_for_staff().
 */
function hr_self_editable_fields()
{
    return hr_ess_parse_fields(get_option('hr_self_editable_fields'));
}

/**
 * GLOBAL required-documents checklist (default). Runtime should prefer
 * hr_ess_config_for_staff().
 */
function hr_required_documents()
{
    return hr_ess_parse_docs(get_option('hr_required_documents'));
}

/**
 * Decoded role / employee ESS override maps. Each is keyed by id and holds an
 * override config: ['override'=>1, 'editable_fields'=>csv,
 * 'required_documents'=>text, 'checklist_enabled'=>'1'|'0'].
 */
function hr_ess_role_config()
{
    $map = json_decode((string) get_option('hr_ess_role_config'), true);

    return is_array($map) ? $map : [];
}

function hr_ess_employee_config()
{
    $map = json_decode((string) get_option('hr_ess_employee_config'), true);

    return is_array($map) ? $map : [];
}

/**
 * Resolve the effective ESS configuration for one employee.
 *
 * Precedence (most specific wins): employee override → role override → global.
 * Only a level whose `override` flag is set participates; otherwise we fall
 * through to the next. Returns:
 *   ['editable_fields' => [slugs], 'required_documents' => [['name'=>,'mandatory'=>], ...], 'level' => 'employee'|'role'|'global']
 *
 * @param int $staff_id
 * @param int|null $role_id  optional (saves a query if the caller already has it)
 */
function hr_ess_config_for_staff($staff_id, $role_id = null)
{
    // Build from an override config array, honouring its checklist toggle.
    $from_override = function ($cfg, $level) {
        $enabled = !isset($cfg['checklist_enabled']) || $cfg['checklist_enabled'] === '1' || $cfg['checklist_enabled'] === 1;

        return [
            'editable_fields'    => hr_ess_parse_fields($cfg['editable_fields'] ?? ''),
            'required_documents' => $enabled ? hr_ess_parse_doc_lines($cfg['required_documents'] ?? '') : [],
            'level'              => $level,
        ];
    };

    // 1) employee-specific
    $emp = hr_ess_employee_config();
    if (!empty($emp[(string) $staff_id]['override'])) {
        return $from_override($emp[(string) $staff_id], 'employee');
    }

    // 2) role-specific
    if ($role_id === null) {
        $CI  = &get_instance();
        $row = $CI->db->select('role')->where('staffid', $staff_id)->get(db_prefix() . 'staff')->row();
        $role_id = $row ? $row->role : null;
    }
    $roles = hr_ess_role_config();
    if ($role_id !== null && !empty($roles[(string) $role_id]['override'])) {
        return $from_override($roles[(string) $role_id], 'role');
    }

    // 3) global
    $global_enabled = get_option('hr_required_documents_enabled') !== '0';

    return [
        'editable_fields'    => hr_self_editable_fields(),
        'required_documents' => $global_enabled ? hr_ess_parse_doc_lines(get_option('hr_required_documents')) : [],
        'level'              => 'global',
    ];
}

/**
 * Extensions allowed for employee document uploads, and whether a file is a
 * previewable image. Shared by the controller (validation) and views (preview).
 */
function hr_document_allowed_extensions()
{
    return ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx'];
}

function hr_document_is_image($file_name)
{
    return in_array(strtolower(pathinfo((string) $file_name, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp']);
}

/**
 * Map legacy Font Awesome 4 icon names to their Font Awesome 6 equivalents.
 * This install ships FA6 (Free), where several FA4 names either were renamed or
 * now alias Pro-only glyphs that render as an empty box (e.g. fa-money). Passing
 * every benefit icon through this keeps old and hand-entered names rendering.
 */
function hr_normalize_icon($icon)
{
    $map = [
        'fa-money'         => 'fa-money-bill',
        'fa-line-chart'    => 'fa-chart-line',
        'fa-cutlery'       => 'fa-utensils',
        'fa-male'          => 'fa-person',
        'fa-female'        => 'fa-person-dress',
        'fa-user-md'       => 'fa-user-doctor',
        'fa-mobile'        => 'fa-mobile-screen-button',
        'fa-bank'          => 'fa-building-columns',
        'fa-home'          => 'fa-house',
        'fa-shield'        => 'fa-shield-halved',
        'fa-clock-o'       => 'fa-clock',
        'fa-plus-square'   => 'fa-square-plus',
        'fa-birthday-cake' => 'fa-cake-candles',
        'fa-coffee'        => 'fa-mug-hot',
        'fa-taxi'          => 'fa-taxi',
    ];

    return $map[$icon] ?? ($icon ?: 'fa-gift');
}

/**
 * Progress of a time-based (vesting) benefit for an employee, from their
 * joining date toward the vesting period (e.g. gratuity at 5 years). Returns
 * null when it doesn't apply (no joining date or a non-vesting benefit).
 *
 * @return array|null ['tenure_years','progress'(0-100),'remaining_years','eligible_date','vested']
 */
function hr_benefit_progress($vesting_years, $date_of_joining)
{
    $vesting_years = (float) $vesting_years;
    if ($vesting_years <= 0 || empty($date_of_joining) || $date_of_joining === '0000-00-00') {
        return null;
    }
    $start  = strtotime($date_of_joining);
    if (!$start) {
        return null;
    }
    $tenure_years  = max(0, (time() - $start) / (365.25 * 86400));
    $progress      = min(100, ($tenure_years / $vesting_years) * 100);
    $eligible_date = date('Y-m-d', strtotime($date_of_joining . ' +' . (int) round($vesting_years * 12) . ' months'));

    return [
        'tenure_years'    => $tenure_years,
        'progress'        => $progress,
        'remaining_years' => max(0, $vesting_years - $tenure_years),
        'eligible_date'   => $eligible_date,
        'vested'          => $tenure_years >= $vesting_years,
    ];
}

/**
 * Tenant-aware base dir for HR document uploads.
 */
function hr_upload_dir($staff_id)
{
    $base = defined('PERFEX_SAAS_TENANT_UPLOAD_BASE_FOLDER')
        ? PERFEX_SAAS_TENANT_UPLOAD_BASE_FOLDER
        : FCPATH . 'uploads/';

    return $base . 'hr_documents/' . (int) $staff_id . '/';
}

/**
 * File types accepted as a leave "proof of reason".
 */
function hr_leave_proof_allowed()
{
    return ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'gif', 'doc', 'docx'];
}

/**
 * Handle the optional "proof of reason" upload from a leave-apply form. Stores
 * the file under the employee's HR uploads folder with a leave_ prefix.
 * Returns ['file' => filename|null, 'error' => message|null]. No file uploaded
 * is not an error (proof is optional).
 */
function hr_store_leave_proof($staff_id, $field = 'proof')
{
    if (empty($_FILES[$field]['name']) || !empty($_FILES[$field]['error'])) {
        return ['file' => null, 'error' => null];
    }

    $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, hr_leave_proof_allowed(), true)) {
        return ['file' => null, 'error' => 'Proof file type not allowed. Use PDF, image or Word.'];
    }

    $dir = hr_upload_dir($staff_id);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $filename = uniqid('leave_') . '.' . $ext;
    if (!move_uploaded_file($_FILES[$field]['tmp_name'], $dir . $filename)) {
        return ['file' => null, 'error' => 'Proof upload failed. Please try again.'];
    }

    return ['file' => $filename, 'error' => null];
}

/**
 * Stream a leave proof file for the given request row. Images/PDFs render inline
 * (for preview); other types are sent as a download. Callers must authorise
 * access before invoking. Terminates the request.
 */
function hr_output_leave_proof($request)
{
    if (empty($request['proof_file'])) {
        show_404();
    }
    $path = hr_upload_dir($request['staff_id']) . $request['proof_file'];
    if (!file_exists($path)) {
        show_404();
    }

    $ext   = strtolower(pathinfo($request['proof_file'], PATHINFO_EXTENSION));
    $mimes = [
        'pdf'  => 'application/pdf',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'webp' => 'image/webp',
        'gif'  => 'image/gif',
    ];

    while (ob_get_level()) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header_remove('Content-Security-Policy');
        header_remove('Content-Security-Policy-Report-Only');
        header_remove('X-Frame-Options');
        if (isset($mimes[$ext])) {
            header('Content-Type: ' . $mimes[$ext]);
            header('Content-Disposition: inline; filename="leave-proof.' . $ext . '"');
        } else {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="leave-proof.' . $ext . '"');
        }
        header('Content-Length: ' . filesize($path));
        header('X-Content-Type-Options: nosniff');
    }
    readfile($path);
    exit;
}

/**
 * Weekly off days for a role, as array of day numbers (0 = Sunday).
 * Roles with an override in the hr_role_week_off option map use it;
 * everyone else falls back to the global hr_default_week_off.
 */
function hr_week_off_days($role_id = null)
{
    if ($role_id) {
        $map = json_decode(get_option('hr_role_week_off'), true);
        if (is_array($map) && array_key_exists((string) $role_id, $map)) {
            return array_values(array_filter(explode(',', $map[(string) $role_id]), 'strlen'));
        }
    }

    return array_values(array_filter(explode(',', get_option('hr_default_week_off')), 'strlen'));
}

/**
 * Curated list of Indian public / gazetted holidays for a given year, used by
 * the "Add Indian Holidays" importer on the Holidays screen.
 *
 * Two parts:
 *   - Fixed-date national holidays (Republic Day, Independence Day, Gandhi
 *     Jayanti, Christmas, ...) computed for ANY year.
 *   - Variable-date festivals (Holi, Diwali, the Eids, Dussehra, ...) which
 *     shift every year with the lunar/religious calendars — seeded with
 *     accurate dates for 2025, 2026 and 2027. For other years only the fixed
 *     national holidays are returned.
 *
 * `is_optional` marks restricted / regional festivals (shown as "Optional");
 * everything else is a full public holiday. Imported rows are fully editable
 * and deletable afterwards, so an admin can localise dates to their state.
 *
 * @param  int $year
 * @return array [['name'=>string, 'holiday_date'=>'Y-m-d', 'is_optional'=>0|1], ...] sorted by date
 */
function hr_indian_holidays($year)
{
    $year = (int) $year;
    $d    = function ($md) use ($year) {
        return sprintf('%04d-%s', $year, $md);
    };

    // Fixed Gregorian dates — apply every year.
    $fixed = [
        ['New Year\'s Day',        $d('01-01'), 1],
        ['Makar Sankranti / Pongal', $d('01-14'), 1],
        ['Republic Day',           $d('01-26'), 0],
        ['Dr. Ambedkar Jayanti',   $d('04-14'), 0],
        ['May Day (Labour Day)',   $d('05-01'), 1],
        ['Independence Day',       $d('08-15'), 0],
        ['Gandhi Jayanti',         $d('10-02'), 0],
        ['Christmas',              $d('12-25'), 0],
    ];

    // Variable-date festivals, per year. [name, MM-DD, is_optional]
    $variable = [
        2025 => [
            ['Maha Shivaratri',            '02-26', 1],
            ['Holi',                       '03-14', 0],
            ['Ugadi / Gudi Padwa',         '03-30', 1],
            ['Id-ul-Fitr (Ramzan Id)',     '03-31', 0],
            ['Ram Navami',                 '04-06', 0],
            ['Mahavir Jayanti',            '04-10', 0],
            ['Good Friday',                '04-18', 0],
            ['Buddha Purnima',             '05-12', 0],
            ['Bakrid (Id-ul-Zuha)',        '06-07', 0],
            ['Rath Yatra',                 '06-27', 1],
            ['Muharram',                   '07-06', 0],
            ['Raksha Bandhan',             '08-09', 1],
            ['Janmashtami',                '08-16', 0],
            ['Ganesh Chaturthi',           '08-27', 1],
            ['Onam',                       '09-04', 1],
            ['Milad-un-Nabi',              '09-05', 0],
            ['Dussehra (Vijaya Dashami)',  '10-02', 0],
            ['Diwali (Deepavali)',         '10-20', 0],
            ['Guru Nanak Jayanti',         '11-05', 0],
        ],
        2026 => [
            ['Maha Shivaratri',            '02-15', 1],
            ['Holi',                       '03-04', 0],
            ['Ugadi / Gudi Padwa',         '03-19', 1],
            ['Id-ul-Fitr (Ramzan Id)',     '03-21', 0],
            ['Ram Navami',                 '03-26', 0],
            ['Mahavir Jayanti',            '03-31', 0],
            ['Good Friday',                '04-03', 0],
            ['Buddha Purnima',             '05-01', 0],
            ['Bakrid (Id-ul-Zuha)',        '05-27', 0],
            ['Muharram',                   '06-26', 0],
            ['Rath Yatra',                 '07-16', 1],
            ['Onam',                       '08-26', 1],
            ['Milad-un-Nabi',              '08-26', 0],
            ['Raksha Bandhan',             '08-28', 1],
            ['Janmashtami',                '09-04', 0],
            ['Ganesh Chaturthi',           '09-14', 1],
            ['Dussehra (Vijaya Dashami)',  '10-20', 0],
            ['Diwali (Deepavali)',         '11-08', 0],
            ['Guru Nanak Jayanti',         '11-24', 0],
        ],
        2027 => [
            ['Maha Shivaratri',            '03-06', 1],
            ['Id-ul-Fitr (Ramzan Id)',     '03-10', 0],
            ['Holi',                       '03-22', 0],
            ['Good Friday',                '03-26', 0],
            ['Ugadi / Gudi Padwa',         '04-08', 1],
            ['Ram Navami',                 '04-15', 0],
            ['Bakrid (Id-ul-Zuha)',        '05-17', 0],
            ['Buddha Purnima',             '05-20', 0],
            ['Muharram',                   '06-15', 0],
            ['Rath Yatra',                 '07-05', 1],
            ['Milad-un-Nabi',              '08-15', 0],
            ['Raksha Bandhan',             '08-17', 1],
            ['Janmashtami',                '08-25', 0],
            ['Ganesh Chaturthi',           '09-04', 1],
            ['Onam',                       '09-11', 1],
            ['Dussehra (Vijaya Dashami)',  '10-10', 0],
            ['Diwali (Deepavali)',         '10-29', 0],
            ['Guru Nanak Jayanti',         '11-14', 0],
        ],
    ];

    $rows = $fixed;
    if (isset($variable[$year])) {
        foreach ($variable[$year] as $v) {
            $rows[] = [$v[0], $d($v[1]), $v[2]];
        }
    }

    // Normalise to assoc rows and sort by date.
    $out = [];
    foreach ($rows as $r) {
        $out[] = ['name' => $r[0], 'holiday_date' => $r[1], 'is_optional' => (int) $r[2]];
    }
    usort($out, function ($a, $b) {
        return strcmp($a['holiday_date'], $b['holiday_date']);
    });

    return $out;
}

/**
 * Years for which hr_indian_holidays() has an accurate variable-date festival
 * seed (outside this range only the fixed national holidays are available).
 */
function hr_indian_holidays_seeded_years()
{
    return [2025, 2026, 2027];
}

/**
 * Preset categories for the Perks / office-supplies checklist. Used to group the
 * list and to populate the category picker (which also allows a custom value via
 * a datalist, so this is a convenience list, not a hard whitelist).
 */
function hr_perk_categories()
{
    return [
        'Pantry & Snacks',
        'Beverages',
        'Pantry Equipment',
        'Office Maintenance',
        'Cleaning & Housekeeping',
        'Stationery',
        'Electrical & Appliances',
        'IT & Peripherals',
        'Furniture & Fixtures',
        'Health & Safety',
        'Employee Welfare',
        'Décor & Plants',
        'Other',
    ];
}

/**
 * Perks checklist status workflow: pending (to order) → ordered → received.
 * Keys are stored; label/colour/icon drive the UI.
 */
function hr_perk_statuses()
{
    return [
        'pending'  => ['label' => 'To Order', 'color' => '#d97706', 'icon' => 'fa-clock'],
        'ordered'  => ['label' => 'Ordered',  'color' => '#2563eb', 'icon' => 'fa-truck'],
        'received' => ['label' => 'Received', 'color' => '#16a34a', 'icon' => 'fa-check'],
    ];
}

/**
 * Priority → label/colour for a perk checklist item.
 */
function hr_perk_priorities()
{
    return [
        'low'    => ['label' => 'Low',    'color' => '#64748b'],
        'medium' => ['label' => 'Medium', 'color' => '#0891b2'],
        'high'   => ['label' => 'High',   'color' => '#dc2626'],
    ];
}

/**
 * Shared status → label/colour map for attendance.
 */
function hr_attendance_statuses()
{
    return [
        'present'  => ['label' => _l('hr_att_present'), 'short' => 'P', 'color' => '#16a34a'],
        'absent'   => ['label' => _l('hr_att_absent'), 'short' => 'A', 'color' => '#dc2626'],
        'half_day' => ['label' => _l('hr_att_half_day'), 'short' => 'HD', 'color' => '#d97706'],
        'leave'    => ['label' => _l('hr_att_leave'), 'short' => 'L', 'color' => '#7c3aed'],
        'holiday'  => ['label' => _l('hr_att_holiday'), 'short' => 'H', 'color' => '#0891b2'],
        'week_off' => ['label' => _l('hr_att_week_off'), 'short' => 'WO', 'color' => '#64748b'],
    ];
}

/* ------------------------------------------------------ Field attendance */

/**
 * Is remote / field punch-in enabled? (HR Settings toggle; default on.)
 */
function hr_field_enabled()
{
    return get_option('hr_field_enabled') !== '0';
}

/**
 * Resolved field-attendance rules, read once and passed to the punch screen and
 * the save handler so the browser UI and the server validation never drift.
 *
 * The punch photo is ALWAYS a live camera capture — file/gallery uploads are
 * not offered anywhere and are rejected server side, so there is no setting
 * for it.
 *
 *   geofence_mode   off | warn | block  — what happens outside every site
 *   max_accuracy_m  0 = accept any GPS accuracy, otherwise the worst accepted
 *   min_gap_minutes throttle between two punches by the same employee
 */
function hr_field_settings()
{
    $geofence = get_option('hr_field_geofence_mode');
    if (!in_array($geofence, ['off', 'warn', 'block'], true)) {
        $geofence = 'off';
    }

    return [
        'enabled'            => hr_field_enabled(),
        'require_location'   => get_option('hr_field_require_location') !== '0',
        'require_photo'      => get_option('hr_field_require_photo') !== '0',
        'max_accuracy_m'     => max(0, (int) get_option('hr_field_max_accuracy_m')),
        'geofence_mode'      => $geofence,
        'auto_approve'       => get_option('hr_field_auto_approve') !== '0',
        'update_attendance'  => get_option('hr_field_update_attendance') !== '0',
        'overwrite_manual'   => get_option('hr_field_overwrite_manual') === '1',
        'halfday_minutes'    => max(0, (int) get_option('hr_field_halfday_minutes')),
        'min_gap_minutes'    => max(0, (int) get_option('hr_field_min_gap_minutes')),
        'reverse_geocode'    => get_option('hr_field_reverse_geocode') === '1',
        'notify_reviewers'   => get_option('hr_field_notify_reviewers') !== '0',
    ];
}

/**
 * Where a field-punch photo lives. Kept out of hr_documents/ so the employee
 * document checklist never lists selfies. Tenant-aware (SaaS installs redefine
 * the upload base per tenant).
 */
function hr_field_photo_dir($staff_id)
{
    $base = defined('PERFEX_SAAS_TENANT_UPLOAD_BASE_FOLDER')
        ? PERFEX_SAAS_TENANT_UPLOAD_BASE_FOLDER
        : FCPATH . 'uploads/';

    return $base . 'hr_field_attendance/' . (int) $staff_id . '/';
}

/**
 * Stream a punch photo inline. Callers MUST have done the access check first
 * (HR permission, or ownership for the self-service portal).
 */
function hr_field_stream_photo($punch)
{
    $path = hr_field_photo_dir($punch['staff_id']) . $punch['photo'];
    if (!is_file($path)) {
        show_404();
    }
    $mimes = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
    $ext   = strtolower(pathinfo($punch['photo'], PATHINFO_EXTENSION));

    header('Content-Type: ' . ($mimes[$ext] ?? 'application/octet-stream'));
    header('Content-Disposition: inline; filename="' . basename($punch['photo']) . '"');
    header('Content-Length: ' . filesize($path));
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit;
}

/**
 * Why the employee is punching from outside. Free-form enough for hospitals
 * (home visits, camps) without becoming a configurable list of its own.
 */
function hr_field_purposes()
{
    return [
        'field_duty'    => 'Field Duty / On Site',
        'client_visit'  => 'Client / Patient Visit',
        'home_visit'    => 'Home Visit',
        'camp'          => 'Camp / Outreach',
        'travel'        => 'Official Travel',
        'work_from_home' => 'Work From Home',
        'other'         => 'Other',
    ];
}

function hr_field_punch_types()
{
    return [
        'in'  => ['label' => 'Punch In',  'color' => '#16a34a', 'icon' => 'fa fa-right-to-bracket'],
        'out' => ['label' => 'Punch Out', 'color' => '#dc2626', 'icon' => 'fa fa-right-from-bracket'],
    ];
}

function hr_field_statuses()
{
    return [
        'pending'  => ['label' => 'Pending Review', 'color' => '#d97706'],
        'approved' => ['label' => 'Approved',       'color' => '#16a34a'],
        'rejected' => ['label' => 'Rejected',       'color' => '#dc2626'],
    ];
}

/**
 * Great-circle distance between two coordinates, in metres (haversine).
 * Returns null when either point is missing.
 */
function hr_field_distance_m($lat1, $lng1, $lat2, $lng2)
{
    if ($lat1 === null || $lng1 === null || $lat2 === null || $lng2 === null
        || $lat1 === '' || $lng1 === '' || $lat2 === '' || $lng2 === '') {
        return null;
    }

    $r    = 6371000; // earth radius, metres
    $p1   = deg2rad((float) $lat1);
    $p2   = deg2rad((float) $lat2);
    $dLat = deg2rad((float) $lat2 - (float) $lat1);
    $dLng = deg2rad((float) $lng2 - (float) $lng1);

    $a = sin($dLat / 2) * sin($dLat / 2)
        + cos($p1) * cos($p2) * sin($dLng / 2) * sin($dLng / 2);

    return (int) round($r * 2 * atan2(sqrt($a), sqrt(1 - $a)));
}

/**
 * Nearest active work site to a coordinate. Returns
 * ['site' => row, 'distance_m' => int, 'within' => bool] or null when no site
 * is configured (in which case geofencing simply does not apply).
 */
function hr_field_nearest_site($lat, $lng, $sites = null)
{
    if ($lat === null || $lng === null || $lat === '' || $lng === '') {
        return null;
    }
    if ($sites === null) {
        $CI = &get_instance();
        $CI->load->model(HR_MODULE_NAME . '/hr_model');
        $sites = $CI->hr_model->get_field_sites(true);
    }
    if (empty($sites)) {
        return null;
    }

    $best = null;
    foreach ($sites as $site) {
        $d = hr_field_distance_m($lat, $lng, $site['latitude'], $site['longitude']);
        if ($d === null) {
            continue;
        }
        if ($best === null || $d < $best['distance_m']) {
            $best = [
                'site'       => $site,
                'distance_m' => $d,
                'within'     => $d <= (int) $site['radius_m'],
            ];
        }
    }

    return $best;
}

/**
 * External map link for a captured coordinate (opened in a new tab; no API key
 * and no map script is embedded in the admin).
 */
function hr_field_map_url($lat, $lng)
{
    return 'https://www.google.com/maps/search/?api=1&query=' . urlencode($lat . ',' . $lng);
}

/**
 * Staff who review field punches: admins + anyone holding hr_field_attendance.
 * Mirrors hr_feedback_manager_ids().
 */
function hr_field_reviewer_ids()
{
    $CI  = &get_instance();
    $p   = db_prefix();
    $ids = $CI->db->query('SELECT staffid FROM ' . $p . 'staff
        WHERE active = 1 AND (admin = 1 OR staffid IN (
            SELECT staff_id FROM ' . $p . 'staff_permissions WHERE feature = "hr_field_attendance"
        ))')->result_array();

    return array_map(function ($r) { return (int) $r['staffid']; }, $ids);
}

/* ------------------------------------------------------- Birthday wishes */

/**
 * Is the birthday-wishes feature turned on? (HR Settings toggle; default on.)
 */
function hr_birthday_wishes_enabled()
{
    return get_option('hr_birthday_wishes_enabled') !== '0';
}

/**
 * Daily cron entry point — dispatch the "wish your colleague" notifications.
 */
function hr_birthday_notify_cron()
{
    hr_dispatch_birthday_notifications();
}

/**
 * Notify every other active staff member about today's birthday(s), asking
 * them to send their wishes. Idempotent per day: guarded by the
 * hr_birthday_notified_date option so it fires once regardless of how many
 * times the cron or an HR dashboard load triggers it. Safe to call anywhere.
 */
function hr_dispatch_birthday_notifications()
{
    if (!hr_birthday_wishes_enabled()) {
        return;
    }

    $today = date('Y-m-d');
    if (get_option('hr_birthday_notified_date') === $today) {
        return; // already dispatched for today
    }

    $CI = &get_instance();
    $CI->load->model(HR_MODULE_NAME . '/hr_model');
    $birthdays = $CI->hr_model->birthdays_today();

    // Claim the day first so concurrent requests don't double-send.
    update_option('hr_birthday_notified_date', $today);

    if (empty($birthdays)) {
        return;
    }

    // All active staff = potential well-wishers.
    $staff = $CI->db->select('staffid')
        ->where('active', 1)
        ->where('is_not_staff', 0)
        ->get(db_prefix() . 'staff')->result_array();

    foreach ($birthdays as $b) {
        $celebrant_id = (int) $b['staffid'];
        $name         = trim($b['firstname'] . ' ' . $b['lastname']);
        $dept         = trim($b['department'] ?? '');
        $label        = $name . ($dept !== '' ? ' (' . $dept . ')' : '');
        $recipients   = [];

        foreach ($staff as $s) {
            $sid = (int) $s['staffid'];
            if ($sid === $celebrant_id) {
                continue; // don't ask the birthday person to wish themselves
            }
            $notified = add_notification([
                'description'     => 'hr_birthday_notification',
                'touserid'        => $sid,
                'fromcompany'     => 1,
                'fromuserid'      => 0,
                'link'            => 'hr/employee/' . $celebrant_id,
                'additional_data' => serialize([$label]),
            ]);
            if ($notified) {
                $recipients[] = $sid;
            }
        }

        if (!empty($recipients)) {
            pusher_trigger_notification($recipients);
        }
    }
}

/**
 * Public birthday greeting on the MAIN CRM dashboard (every staff member sees
 * it). Only renders when "wish employees on their birthday" is ON — when the
 * toggle is OFF, birthdays stay private to the HR dashboard, so nothing shows
 * here. Wrapped so a failure never blanks the dashboard.
 */
function hr_birthday_dashboard_widget()
{
    if (!hr_birthday_wishes_enabled()) {
        return; // public wishing off → not shown on the main dashboard
    }

    $CI = &get_instance();
    try {
        if (!$CI->db->table_exists(db_prefix() . 'hr_employees')) {
            return;
        }
        $CI->load->model(HR_MODULE_NAME . '/hr_model');
        $birthdays = $CI->hr_model->birthdays_today();
        if (empty($birthdays)) {
            return;
        }
        // Make sure the "wish your colleague" notifications go out even if no
        // one opens the HR dashboard today (idempotent per day).
        hr_dispatch_birthday_notifications();

        $CI->load->view('hr/dashboard_birthday_widget', [
            'birthdays' => $birthdays,
            'me'        => (int) get_staff_user_id(),
        ]);
    } catch (Throwable $e) {
        log_activity('HR birthday dashboard widget failed to render [' . $e->getMessage() . ']');
    }
}

/* ------------------------------------------------------- Salary pay day */

/**
 * Resolve the effective salary payment day for an employee. The per-employee
 * override wins; NULL/'' falls back to the global setting. 0 = last day of the
 * month, 1-31 = a fixed day.
 */
function hr_effective_pay_day($emp_day)
{
    if ($emp_day !== null && $emp_day !== '') {
        return max(0, min(31, (int) $emp_day));
    }
    $g = get_option('hr_salary_payment_day');

    return $g === '' ? 1 : max(0, min(31, (int) $g));
}

/**
 * The real calendar pay date for a month. Day 0 (or a day past the month's
 * length, e.g. 31 in February) resolves to the last day of that month.
 */
function hr_pay_date_for_month($day, $month, $year)
{
    $dim = (int) date('t', strtotime(sprintf('%04d-%02d-01', $year, $month)));
    $day = (int) $day;
    if ($day <= 0 || $day > $dim) {
        $day = $dim;
    }

    return sprintf('%04d-%02d-%02d', $year, $month, $day);
}

/**
 * Human label for a pay day setting, e.g. "7th" or "Last day of month".
 */
function hr_pay_day_label($day)
{
    $day = (int) $day;
    if ($day <= 0) {
        return 'Last day of month';
    }
    $mod100 = $day % 100;
    $suffix = ($mod100 >= 11 && $mod100 <= 13) ? 'th'
        : (['th', 'st', 'nd', 'rd'][$day % 10] ?? 'th');

    return $day . $suffix;
}

/**
 * Active staff who should receive payroll reminders: admins plus anyone holding
 * an hr_payroll capability. Returns a list of staff ids.
 */
function hr_payroll_staff_ids()
{
    $CI  = &get_instance();
    $p   = db_prefix();
    $ids = $CI->db->query('SELECT staffid FROM ' . $p . 'staff
        WHERE active = 1 AND (admin = 1 OR staffid IN (
            SELECT staff_id FROM ' . $p . 'staff_permissions WHERE feature = "hr_payroll"
        ))')->result_array();

    return array_map(function ($r) { return (int) $r['staffid']; }, $ids);
}

/**
 * FontAwesome icon for a leave type. Uses the type's own `icon` column when set,
 * otherwise a sensible default inferred from the code/name (so existing types
 * get nice icons without any configuration).
 */
function hr_leave_type_icon($lt)
{
    if (!empty($lt['icon'])) {
        return $lt['icon'];
    }
    $key = strtoupper(trim(($lt['code'] ?? '') . ' ' . ($lt['name'] ?? '')));
    $map = [
        'CASUAL'       => 'fa-coffee',
        'SICK'         => 'fa-medkit',
        'MEDICAL'      => 'fa-medkit',
        'EARNED'       => 'fa-sun-o',
        'ANNUAL'       => 'fa-sun-o',
        'PRIVILEGE'    => 'fa-sun-o',
        'MATERNITY'    => 'fa-child',
        'PATERNITY'    => 'fa-male',
        'MARRIAGE'     => 'fa-heart',
        'BEREAVEMENT'  => 'fa-pagelines',
        'COMP'         => 'fa-exchange',
        'COMPENSATORY' => 'fa-exchange',
        'WITHOUT PAY'  => 'fa-ban',
        'LWP'          => 'fa-ban',
        'UNPAID'       => 'fa-ban',
    ];
    foreach ($map as $needle => $icon) {
        if (strpos($key, $needle) !== false) {
            return $icon;
        }
    }

    return 'fa-calendar-o';
}

/**
 * Cron entry point for the pre-payday reminder.
 */
function hr_payroll_reminder_cron()
{
    hr_dispatch_payroll_reminder();
}

/**
 * Notify payroll staff 3 days before the (global) salary payment date, with the
 * projected month total. Idempotent per calendar month via the
 * hr_payroll_reminded_period option. Safe to call from cron or a dashboard load.
 */
function hr_dispatch_payroll_reminder()
{
    $CI = &get_instance();
    if (!$CI->db->table_exists(db_prefix() . 'hr_employees')) {
        return;
    }

    $month      = (int) date('n');
    $year       = (int) date('Y');
    $pay_date   = hr_pay_date_for_month(hr_effective_pay_day(null), $month, $year);
    $days_until = (int) floor((strtotime($pay_date) - strtotime(date('Y-m-d'))) / 86400);
    if ($days_until < 0 || $days_until > 3) {
        return; // only within the 3-day pre-payday window
    }

    $period = date('Y-m');
    if (get_option('hr_payroll_reminded_period') === $period) {
        return; // already reminded for this month
    }
    update_option('hr_payroll_reminded_period', $period);

    $CI->load->model(HR_MODULE_NAME . '/hr_model');
    $sum        = $CI->hr_model->payroll_reminder_summary($month, $year);
    $recipients = hr_payroll_staff_ids();
    if (empty($recipients)) {
        return;
    }

    foreach ($recipients as $sid) {
        add_notification([
            'description'     => 'hr_payroll_reminder_notification',
            'touserid'        => $sid,
            'fromcompany'     => 1,
            'fromuserid'      => 0,
            'link'            => 'hr/payroll',
            'additional_data' => serialize([_d($pay_date), app_format_money($sum['total_net'], get_base_currency())]),
        ]);
    }
    pusher_trigger_notification($recipients);
}

/* --------------------------------------------------------- Leave rules */

/**
 * Minimum advance-notice days an employee must give when applying for leave.
 * 1 (default) blocks same-day leave; 0 allows it. Emergency leave types are
 * always exempt from this rule.
 */
function hr_leave_min_notice_days()
{
    $v = get_option('hr_leave_min_notice_days');

    return $v === '' ? 1 : max(0, (int) $v);
}

/**
 * Validate an employee's leave application against the configured rules.
 * Returns an error message when it is not allowed, or '' when it passes.
 * HR/management applying on an employee's behalf are not subject to this.
 *
 * @param array  $leave_type  The leave type row (uses its is_emergency flag).
 * @param string $from_date   Leave start date (Y-m-d).
 */
function hr_leave_apply_violation($leave_type, $from_date)
{
    $ts = strtotime((string) $from_date);
    if (!$ts) {
        return 'Please choose a valid start date.';
    }
    $today = strtotime(date('Y-m-d'));
    $from  = strtotime(date('Y-m-d', $ts));

    if ($from < $today && get_option('hr_leave_allow_backdated') !== '1') {
        return 'You cannot apply for a past date.';
    }

    if (empty($leave_type['is_emergency'])) {
        $notice   = hr_leave_min_notice_days();
        $earliest = strtotime('+' . $notice . ' day', $today);
        if ($from < $earliest) {
            return $notice <= 1
                ? 'Same-day leave is not allowed for this leave type. For an urgent/medical emergency, choose an emergency leave type.'
                : 'This leave must be applied at least ' . $notice . ' day(s) in advance. For an urgent/medical emergency, choose an emergency leave type.';
        }
    }

    return '';
}

/* -------------------------------------------- Multi-level leave approval */

/**
 * Parsed approval chain (ordered levels). Empty array = single-step approval
 * (the classic behaviour). Each level: ['role' => int, 'label' => string],
 * where role 0 means "Anyone" (any staff with the leave-approve capability).
 */
function hr_leave_chain()
{
    if (get_option('hr_leave_multilevel_enabled') !== '1') {
        return [];
    }
    $raw = json_decode((string) get_option('hr_leave_approval_chain'), true);
    if (!is_array($raw)) {
        return [];
    }
    $out = [];
    foreach ($raw as $lvl) {
        if (!is_array($lvl)) {
            continue;
        }
        $label = trim((string) ($lvl['label'] ?? ''));

        // Newer format carries an explicit approver type.
        if (isset($lvl['type'])) {
            if ($lvl['type'] === 'user') {
                $uid = (int) ($lvl['user'] ?? 0);
                if ($uid <= 0) {
                    continue;
                }
                $out[] = ['type' => 'user', 'user' => $uid, 'role' => 0, 'label' => $label];
            } elseif ($lvl['type'] === 'role' && (int) ($lvl['role'] ?? 0) > 0) {
                $out[] = ['type' => 'role', 'user' => 0, 'role' => (int) $lvl['role'], 'label' => $label];
            } else {
                $out[] = ['type' => 'any', 'user' => 0, 'role' => 0, 'label' => $label];
            }
            continue;
        }

        // Legacy format: {role: X} (0 = anyone).
        $rid = (int) ($lvl['role'] ?? 0);
        $out[] = $rid > 0
            ? ['type' => 'role', 'user' => 0, 'role' => $rid, 'label' => $label]
            : ['type' => 'any', 'user' => 0, 'role' => 0, 'label' => $label];
    }

    return $out;
}

/**
 * Human label for a chain level (uses the custom label if set).
 */
function hr_leave_level_label($lvl, $role_names = [])
{
    if (!is_array($lvl)) {
        return '';
    }
    if (!empty($lvl['label'])) {
        return $lvl['label'];
    }
    if ($lvl['type'] === 'user') {
        return get_staff_full_name((int) $lvl['user']);
    }
    if ($lvl['type'] === 'role') {
        return $role_names[(int) $lvl['role']] ?? ('Role #' . (int) $lvl['role']);
    }

    return 'Anyone';
}

/**
 * Staff (permission) role id for a staff member.
 */
function hr_staff_role_id($staff_id)
{
    $CI  = &get_instance();
    $row = $CI->db->select('role')->where('staffid', (int) $staff_id)
        ->get(db_prefix() . 'staff')->row();

    return $row ? (int) $row->role : 0;
}

/**
 * Can the CURRENT user act on the level a leave request is currently pending at?
 * Admins can act on any level (override). For a role-bound level, the user must
 * hold that role. For an "Anyone" (role 0) level, the leave-approve capability
 * is enough. With no chain configured, the classic hr_leaves-edit rule applies.
 */
function hr_can_action_leave_level($request, $staff_id)
{
    $chain = hr_leave_chain();
    if (empty($chain)) {
        return has_permission('hr_leaves', '', 'edit') || is_admin();
    }
    if (is_admin()) {
        return true;
    }
    // Never approve your own request.
    if ((int) $request['staff_id'] === (int) $staff_id) {
        return false;
    }
    $level = (int) $request['current_level'];
    // Legacy rows created before the chain was enabled (level 0) fall back to
    // the classic "any leave approver" rule.
    if ($level < 1) {
        return has_permission('hr_leaves', '', 'edit');
    }
    if ($level > count($chain)) {
        return false;
    }
    $lvl = $chain[$level - 1];
    if ($lvl['type'] === 'user') {
        return (int) $lvl['user'] === (int) $staff_id;
    }
    if ($lvl['type'] === 'role') {
        return hr_staff_role_id($staff_id) === (int) $lvl['role'];
    }

    // "Anyone" — any staff with the leave-approve capability.
    return has_permission('hr_leaves', '', 'edit');
}

/**
 * Sidebar entry so chain approvers (e.g. a Team Lead who has no HR-management
 * access) can reach their pending leave approvals. Only shown when multi-level
 * approval is on and the user is actually an approver somewhere in the chain.
 */
function hr_module_init_approvals_menu()
{
    $chain = hr_leave_chain();
    if (empty($chain)) {
        return;
    }

    $is_approver = is_admin();
    if (!$is_approver) {
        $me      = (int) get_staff_user_id();
        $my_role = hr_staff_role_id($me);
        $can_any = has_permission('hr_leaves', '', 'edit');
        foreach ($chain as $lvl) {
            if (($lvl['type'] === 'user' && (int) $lvl['user'] === $me)
                || ($lvl['type'] === 'role' && (int) $lvl['role'] === $my_role)
                || ($lvl['type'] === 'any' && $can_any)) {
                $is_approver = true;
                break;
            }
        }
    }
    if (!$is_approver) {
        return;
    }

    $CI = &get_instance();
    $CI->app_menu->add_sidebar_menu_item('hr_approvals', [
        'name'     => _l('hr_leave_approvals'),
        'icon'     => 'fa fa-check-square-o',
        'href'     => admin_url('hr/myhr/approvals'),
        'position' => 37,
    ]);
}

/* ---------------------------------------------------- Suggestions / Feedback */

/**
 * Category slug => label/icon for staff suggestions & feedback.
 */
function hr_feedback_categories()
{
    return [
        'suggestion'   => ['label' => 'Suggestion', 'icon' => 'fa-lightbulb-o', 'color' => '#ca8a04'],
        'feedback'     => ['label' => 'Feedback', 'icon' => 'fa-comment-o', 'color' => '#2563eb'],
        'complaint'    => ['label' => 'Complaint / Concern', 'icon' => 'fa-exclamation-triangle', 'color' => '#dc2626'],
        'appreciation' => ['label' => 'Appreciation', 'icon' => 'fa-thumbs-o-up', 'color' => '#16a34a'],
        'other'        => ['label' => 'Other', 'icon' => 'fa-ellipsis-h', 'color' => '#64748b'],
    ];
}

/**
 * Status slug => label/colour for the management review workflow.
 */
function hr_feedback_statuses()
{
    return [
        'new'       => ['label' => 'New', 'class' => 'info'],
        'in_review' => ['label' => 'In Review', 'class' => 'warning'],
        'actioned'  => ['label' => 'Actioned', 'class' => 'success'],
        'closed'    => ['label' => 'Closed', 'class' => 'default'],
    ];
}

/**
 * Active staff who manage feedback: admins plus anyone with an hr_feedback
 * capability. Used to notify management when new feedback arrives.
 */
function hr_feedback_manager_ids()
{
    $CI  = &get_instance();
    $p   = db_prefix();
    $ids = $CI->db->query('SELECT staffid FROM ' . $p . 'staff
        WHERE active = 1 AND (admin = 1 OR staffid IN (
            SELECT staff_id FROM ' . $p . 'staff_permissions WHERE feature = "hr_feedback"
        ))')->result_array();

    return array_map(function ($r) { return (int) $r['staffid']; }, $ids);
}

/* -------------------------------------------------------------- HR Notices */

/**
 * Render the notices addressed to the current staff member on the main admin
 * dashboard. A notice reaches a user when it targets everyone, their role, or
 * them specifically, is active, and falls inside its optional publish window.
 * Wrapped so a widget failure can never blank the whole dashboard.
 */
function hr_notices_dashboard_widget()
{
    $CI = &get_instance();

    try {
        if (!$CI->db->table_exists(db_prefix() . 'hr_notices')) {
            return;
        }

        $CI->load->model(HR_MODULE_NAME . '/hr_model');
        $notices = $CI->hr_model->get_notices_for_staff(get_staff_user_id());
        if (empty($notices)) {
            return;
        }

        $CI->load->view('hr/dashboard_notices_widget', ['notices' => $notices]);
    } catch (Throwable $e) {
        log_activity('HR notices dashboard widget failed to render [' . $e->getMessage() . ']');
    }
}

/* --------------------------------------------------------- Employee Memos */

/**
 * Memo / disciplinary categories. slug => label + icon + colour. Covers the
 * common hospital HR cases (misconduct, disciplinary action, late coming,
 * property damage, misuse, temporary suspension, warnings, appreciation).
 */
function hr_memo_types()
{
    return [
        'misconduct'        => ['label' => 'Misconduct',            'icon' => 'fa-triangle-exclamation', 'color' => '#dc2626'],
        'disciplinary'      => ['label' => 'Disciplinary Action',   'icon' => 'fa-gavel',                'color' => '#b91c1c'],
        'warning'           => ['label' => 'Warning Letter',        'icon' => 'fa-circle-exclamation',   'color' => '#d97706'],
        'late_coming'       => ['label' => 'Late Coming',           'icon' => 'fa-clock',                'color' => '#ca8a04'],
        'absenteeism'       => ['label' => 'Absenteeism',           'icon' => 'fa-user-slash',           'color' => '#ea580c'],
        'property_damage'   => ['label' => 'Property Damage',       'icon' => 'fa-house-crack',          'color' => '#9333ea'],
        'misuse'            => ['label' => 'Misuse of Resources',   'icon' => 'fa-ban',                  'color' => '#7c3aed'],
        'policy_violation'  => ['label' => 'Policy Violation',      'icon' => 'fa-book',                 'color' => '#2563eb'],
        'suspension'        => ['label' => 'Temporary Suspension',  'icon' => 'fa-user-lock',            'color' => '#111827'],
        'performance'       => ['label' => 'Performance Concern',   'icon' => 'fa-chart-line',           'color' => '#0891b2'],
        'appreciation'      => ['label' => 'Appreciation',          'icon' => 'fa-thumbs-up',            'color' => '#16a34a'],
        'other'             => ['label' => 'Other',                 'icon' => 'fa-file-lines',           'color' => '#64748b'],
    ];
}

/**
 * Severity level of a memo. slug => label + colour + bootstrap label class.
 */
function hr_memo_severities()
{
    return [
        'low'      => ['label' => 'Low',      'color' => '#16a34a', 'class' => 'success'],
        'medium'   => ['label' => 'Medium',   'color' => '#d97706', 'class' => 'warning'],
        'high'     => ['label' => 'High',     'color' => '#dc2626', 'class' => 'danger'],
        'critical' => ['label' => 'Critical', 'color' => '#111827', 'class' => 'default'],
    ];
}

/**
 * Memo lifecycle. issued → acknowledged (employee signed receipt), or disputed
 * (employee acknowledged but recorded disagreement).
 */
function hr_memo_statuses()
{
    return [
        'issued'       => ['label' => 'Awaiting Acknowledgement', 'class' => 'warning'],
        'acknowledged' => ['label' => 'Acknowledged',            'class' => 'success'],
        'disputed'     => ['label' => 'Acknowledged (Disputed)', 'class' => 'danger'],
    ];
}

/**
 * File types accepted as a memo attachment (evidence / signed letter).
 */
function hr_memo_attachment_allowed()
{
    return ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx'];
}

/* --------------------------------------------------------- KRA & KPI */

/**
 * How often a KRA / KPI is measured. Stored as the slug so exports and printed
 * appraisal forms stay readable.
 */
function hr_kra_kpi_frequencies()
{
    return [
        'monthly'     => 'Monthly',
        'quarterly'   => 'Quarterly',
        'half_yearly' => 'Half-Yearly',
        'annual'      => 'Annual',
        'one_time'    => 'One Time',
    ];
}

/**
 * Progress of one KRA / KPI. slug => label + bootstrap label class.
 */
function hr_kra_kpi_statuses()
{
    return [
        'not_started'  => ['label' => 'Not Started',          'class' => 'default'],
        'in_progress'  => ['label' => 'In Progress',          'class' => 'info'],
        'achieved'     => ['label' => 'Achieved',             'class' => 'success'],
        'partial'      => ['label' => 'Partially Achieved',   'class' => 'warning'],
        'not_achieved' => ['label' => 'Not Achieved',         'class' => 'danger'],
        'dropped'      => ['label' => 'Dropped',              'class' => 'default'],
    ];
}

/* --------------------------------------------------- Family & Nominees */

/**
 * Relations offered for family members and nominees. Kept as a flat list so
 * the stored value stays human-readable in exports and printed forms.
 */
function hr_relation_options()
{
    return [
        'Spouse', 'Father', 'Mother', 'Son', 'Daughter', 'Brother', 'Sister',
        'Father-in-law', 'Mother-in-law', 'Grandfather', 'Grandmother',
        'Grandson', 'Granddaughter', 'Guardian', 'Other',
    ];
}

/**
 * Schemes a nomination can be made against. Shares are validated per scheme
 * (each scheme should total 100%), which is how PF/ESI/Gratuity forms work.
 */
function hr_nominee_purposes()
{
    return [
        'general'   => ['label' => 'General',        'class' => 'default'],
        'pf'        => ['label' => 'Provident Fund', 'class' => 'info'],
        'esi'       => ['label' => 'ESI',            'class' => 'primary'],
        'gratuity'  => ['label' => 'Gratuity',       'class' => 'success'],
        'insurance' => ['label' => 'Insurance',      'class' => 'warning'],
        'pension'   => ['label' => 'Pension',        'class' => 'danger'],
    ];
}

/**
 * Strip formatting from an Aadhaar number (spaces / hyphens). Returns the
 * cleaned value; the caller decides what to do when it is not 12 digits —
 * nothing is rejected outright so non-Indian installs are not blocked.
 */
function hr_normalize_aadhaar($value)
{
    $clean = preg_replace('/[\s\-]/', '', (string) $value);

    return trim($clean);
}

/**
 * True when the value looks like a valid 12-digit Aadhaar (UIDAI numbers never
 * start with 0 or 1). Empty values are treated as valid (field is optional).
 */
function hr_aadhaar_is_valid($value)
{
    $clean = hr_normalize_aadhaar($value);

    return $clean === '' || (bool) preg_match('/^[2-9][0-9]{11}$/', $clean);
}

/**
 * Display form of an Aadhaar number: "1234 5678 9012".
 */
function hr_format_aadhaar($value)
{
    $clean = hr_normalize_aadhaar($value);

    return strlen($clean) === 12 ? implode(' ', str_split($clean, 4)) : $clean;
}

/* ------------------------------------------------------------ Onboarding */

/**
 * Onboarding phases (ordered). slug => label + icon. due_offset_days on a task
 * is measured relative to the employee's joining date.
 */
function hr_onboarding_phases()
{
    return [
        'pre_boarding' => ['label' => 'Pre-boarding', 'icon' => 'fa-inbox',          'color' => '#6366f1'],
        'first_day'    => ['label' => 'First Day',    'icon' => 'fa-door-open',      'color' => '#0891b2'],
        'first_week'   => ['label' => 'First Week',   'icon' => 'fa-calendar-week',  'color' => '#16a34a'],
        'first_month'  => ['label' => 'First Month',  'icon' => 'fa-calendar-days',  'color' => '#d97706'],
        'wrap_up'      => ['label' => 'Wrap-up',      'icon' => 'fa-flag-checkered', 'color' => '#7c3aed'],
    ];
}

/**
 * Status of a single onboarding checklist item.
 */
function hr_onboarding_item_statuses()
{
    return [
        'pending' => ['label' => 'Pending', 'color' => '#94a3b8', 'icon' => 'fa-circle'],
        'done'    => ['label' => 'Done',    'color' => '#16a34a', 'icon' => 'fa-circle-check'],
        'na'      => ['label' => 'N/A',     'color' => '#64748b', 'icon' => 'fa-circle-minus'],
    ];
}

/* -------------------------------------------------------------- Interviews */

/**
 * Interview meeting platforms. slug => label + icon. google_meet / zoom can be
 * auto-generated when configured; in_person / phone are manual.
 */
function hr_interview_platforms()
{
    return [
        'google_meet' => ['label' => 'Google Meet', 'icon' => 'fa-video',   'online' => true],
        'zoom'        => ['label' => 'Zoom',        'icon' => 'fa-video',   'online' => true],
        'in_person'   => ['label' => 'In Person',   'icon' => 'fa-building', 'online' => false],
        'phone'       => ['label' => 'Phone Call',  'icon' => 'fa-phone',   'online' => false],
    ];
}

/**
 * Interview pipeline statuses. slug => label + bootstrap label class.
 */
function hr_interview_statuses()
{
    return [
        'scheduled' => ['label' => 'Scheduled', 'class' => 'info'],
        'completed' => ['label' => 'Completed', 'class' => 'primary'],
        'selected'  => ['label' => 'Selected',  'class' => 'success'],
        'rejected'  => ['label' => 'Rejected',  'class' => 'danger'],
        'on_hold'   => ['label' => 'On Hold',   'class' => 'warning'],
        'no_show'   => ['label' => 'No Show',    'class' => 'default'],
        'cancelled' => ['label' => 'Cancelled', 'class' => 'default'],
    ];
}

/**
 * Interviewer recommendation after a round.
 */
function hr_interview_recommendations()
{
    return [
        'strong_yes' => ['label' => 'Strong Hire',  'class' => 'success'],
        'yes'        => ['label' => 'Hire',          'class' => 'success'],
        'maybe'      => ['label' => 'Maybe',         'class' => 'warning'],
        'no'         => ['label' => 'No Hire',        'class' => 'danger'],
        'strong_no'  => ['label' => 'Strong No Hire', 'class' => 'danger'],
    ];
}

/**
 * Is the Zoom Server-to-Server OAuth integration fully configured?
 */
function hr_zoom_configured()
{
    return get_option('hr_zoom_enabled') === '1'
        && get_option('hr_zoom_account_id') !== ''
        && get_option('hr_zoom_client_id') !== ''
        && get_option('hr_zoom_client_secret') !== '';
}

/**
 * Is the Google Meet (service-account) integration fully configured?
 */
function hr_google_meet_configured()
{
    return get_option('hr_google_meet_enabled') === '1'
        && get_option('hr_google_sa_json') !== ''
        && get_option('hr_google_impersonate_email') !== '';
}

/**
 * Tenant-aware upload dir for HR interview resumes.
 */
function hr_interview_upload_dir()
{
    $base = defined('PERFEX_SAAS_TENANT_UPLOAD_BASE_FOLDER')
        ? PERFEX_SAAS_TENANT_UPLOAD_BASE_FOLDER
        : FCPATH . 'uploads/';

    return $base . 'hr_interviews/';
}

/**
 * Tenant-aware upload dir for memo attachments.
 */
function hr_memo_upload_dir($staff_id)
{
    return hr_upload_dir($staff_id) . 'memos/';
}

/**
 * Minimal cURL JSON request helper. Returns
 * ['ok'=>bool, 'code'=>int, 'body'=>array|null, 'error'=>string].
 *
 * @param string $method GET|POST|PATCH
 * @param string $url
 * @param array|string|null $payload  encoded as JSON when array, sent raw when string
 * @param array $headers  extra headers (Authorization, Content-Type handled by caller)
 */
function hr_http_json($method, $url, $payload = null, $headers = [])
{
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'code' => 0, 'body' => null, 'error' => 'PHP cURL extension is not available on this server.'];
    }
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 25);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 12);
    if ($payload !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($payload) ? json_encode($payload) : $payload);
    }
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    $raw  = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        return ['ok' => false, 'code' => $code, 'body' => null, 'error' => $err ?: 'Request failed.'];
    }
    $body = json_decode($raw, true);

    return [
        'ok'    => $code >= 200 && $code < 300,
        'code'  => $code,
        'body'  => is_array($body) ? $body : null,
        'error' => ($code >= 200 && $code < 300) ? '' : (is_array($body) && isset($body['message']) ? $body['message'] : ('HTTP ' . $code . ': ' . mb_strimwidth((string) $raw, 0, 200))),
    ];
}

/* ============================================================================
 *  e-timeoffice biometric attendance machine integration
 *  --------------------------------------------------------------------------
 *  Pulls employee punch data from the e-timeoffice cloud API
 *  (https://api.etimeoffice.com/api/) and turns it into daily HR attendance.
 *
 *  Auth is HTTP Basic with a Base64 of "Corporateid:Username:Password:True".
 *  Endpoints used:
 *    - DownloadPunchDataMCID   raw punches + machine id (range sync + punch log)
 *    - DownloadInOutPunchData  per-day IN/OUT summary   (Test Connection)
 *    - DownloadLastPunchData   new-only punches by MaxRecord (incremental pull)
 *    - DownloadPunchData       raw punches (exposed for completeness)
 * ========================================================================== */

/** True when the admin has switched the machine integration on. */
function hr_etime_enabled()
{
    return get_option('hr_etime_enabled') === '1';
}

/** Effective connection settings (option-backed). */
function hr_etime_config()
{
    return [
        'base_url'     => (string) (get_option('hr_etime_base_url') ?: 'https://api.etimeoffice.com/api/'),
        'corporate_id' => trim((string) get_option('hr_etime_corporate_id')),
        'username'     => trim((string) get_option('hr_etime_username')),
        'password'     => (string) get_option('hr_etime_password'),
    ];
}

/** True when Corporate ID + Username + Password are all present. */
function hr_etime_configured($cfg = null)
{
    $c = $cfg ?: hr_etime_config();

    return $c['corporate_id'] !== '' && $c['username'] !== '' && $c['password'] !== '';
}

/**
 * Perform an authenticated GET against an e-timeoffice endpoint.
 *
 * @param string     $endpoint e.g. "DownloadInOutPunchData"
 * @param array      $params   query parameters (dates are sent raw, per the API)
 * @param array|null $cfg      optional credentials override (used by Test Connection
 *                             so admins can verify before saving)
 * @return array ['ok'=>bool,'code'=>int,'body'=>array|null,'error'=>string]
 */
function hr_etime_get($endpoint, $params = [], $cfg = null)
{
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'code' => 0, 'body' => null, 'error' => 'PHP cURL extension is not available on this server.'];
    }
    $c = $cfg ?: hr_etime_config();
    if (!hr_etime_configured($c)) {
        return ['ok' => false, 'code' => 0, 'body' => null, 'error' => 'e-timeoffice credentials are not configured. Fill Corporate ID, Username and Password in HR Settings.'];
    }

    $base = rtrim($c['base_url'] ?: 'https://api.etimeoffice.com/api/', '/') . '/';
    $url  = $base . ltrim($endpoint, '/');
    if (!empty($params)) {
        // The API expects date values raw (dd/MM/yyyy_HH:mm) — only spaces are
        // ever unsafe in our values, so encode just those.
        $pairs = [];
        foreach ($params as $k => $v) {
            $pairs[] = $k . '=' . str_replace(' ', '%20', (string) $v);
        }
        $url .= '?' . implode('&', $pairs);
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPGET, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 45);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Basic ' . base64_encode($c['corporate_id'] . ':' . $c['username'] . ':' . $c['password'] . ':True'),
        'Content-Type: application/json',
    ]);
    $raw  = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        return ['ok' => false, 'code' => $code, 'body' => null, 'error' => $err ?: 'Request failed.'];
    }
    $body = json_decode($raw, true);

    // Object responses carry an explicit Error/Msg flag; the raw-punch (API 1)
    // response is a plain array with no such envelope.
    $api_err = (is_array($body) && !empty($body['Error']))
        ? (isset($body['Msg']) ? (string) $body['Msg'] : 'API returned an error.')
        : '';
    $ok = ($code >= 200 && $code < 300) && $api_err === '';

    return [
        'ok'    => $ok,
        'code'  => $code,
        'body'  => is_array($body) ? $body : null,
        'error' => $ok ? '' : ($api_err ?: ('HTTP ' . $code . ': ' . mb_strimwidth((string) $raw, 0, 200))),
    ];
}

/** Parse an e-timeoffice date string (d/m/Y [H:i[:s]]) to a unix timestamp, 0 on failure. */
function hr_etime_parse_dt($str)
{
    $str = trim((string) $str);
    if ($str === '') {
        return 0;
    }
    foreach (['d/m/Y H:i:s', 'd/m/Y H:i', 'd/m/Y'] as $fmt) {
        $dt = DateTime::createFromFormat($fmt, $str);
        if ($dt instanceof DateTime) {
            return $dt->getTimestamp();
        }
    }
    $t = strtotime($str);

    return $t ?: 0;
}

/** API 2 — raw punches incl. machine id, for a date range. */
function hr_etime_fetch_punches($from_date, $to_date, $empcode = 'ALL', $cfg = null)
{
    return hr_etime_get('DownloadPunchDataMCID', [
        'Empcode'  => $empcode,
        'FromDate' => date('d/m/Y', strtotime($from_date)) . '_00:00',
        'ToDate'   => date('d/m/Y', strtotime($to_date)) . '_23:59',
    ], $cfg);
}

/** API 3 — per-day IN/OUT summary (used by Test Connection). */
function hr_etime_fetch_inout($from_date, $to_date, $empcode = 'ALL', $cfg = null)
{
    return hr_etime_get('DownloadInOutPunchData', [
        'Empcode'  => $empcode,
        'FromDate' => date('d/m/Y', strtotime($from_date)),
        'ToDate'   => date('d/m/Y', strtotime($to_date)),
    ], $cfg);
}

/** API 4 — only-new punches after $last_record (format MMyyyy$ID). */
function hr_etime_fetch_last($last_record, $empcode = 'ALL', $cfg = null)
{
    return hr_etime_get('DownloadLastPunchData', [
        'Empcode'    => $empcode,
        'LastRecord' => $last_record,
    ], $cfg);
}

/**
 * Range sync: import raw punches for [$from,$to] and rebuild the daily
 * attendance derived from them. Idempotent — safe to run repeatedly.
 *
 * @return array ['ok','punches_added','attendance_updated','rows','unmapped','error']
 */
function hr_etime_run_sync($from_date, $to_date)
{
    $CI = &get_instance();
    $CI->load->model('hr/hr_model');

    $summary = ['ok' => false, 'punches_added' => 0, 'attendance_updated' => 0, 'rows' => 0, 'unmapped' => [], 'error' => ''];

    $res = hr_etime_fetch_punches($from_date, $to_date);
    if (!$res['ok']) {
        $summary['error'] = $res['error'];

        return $summary;
    }
    $punch_data          = isset($res['body']['PunchData']) && is_array($res['body']['PunchData']) ? $res['body']['PunchData'] : [];
    $summary['rows']     = count($punch_data);
    $imp                 = $CI->hr_model->import_etime_punches($punch_data);
    $summary['punches_added'] = $imp['added'];
    $summary['unmapped']      = $imp['unmapped'];
    $summary['attendance_updated'] = $CI->hr_model->rebuild_attendance_from_punches($from_date, $to_date);
    $summary['ok']            = true;

    update_option('hr_etime_last_sync', json_encode([
        'at'                 => date('Y-m-d H:i:s'),
        'from'               => date('Y-m-d', strtotime($from_date)),
        'to'                 => date('Y-m-d', strtotime($to_date)),
        'punches_added'      => $summary['punches_added'],
        'attendance_updated' => $summary['attendance_updated'],
        'unmapped'           => count($summary['unmapped']),
        'mode'               => 'range',
    ]));

    return $summary;
}

/**
 * Incremental pull (API 4): fetch only punches newer than the stored MaxRecord,
 * persist the new MaxRecord, then rebuild attendance for the affected dates.
 *
 * @return array ['ok','added','attendance_updated','max','error']
 */
function hr_etime_pull_incremental()
{
    $CI = &get_instance();
    $CI->load->model('hr/hr_model');

    $last = trim((string) get_option('hr_etime_last_record'));
    if ($last === '') {
        // Bootstrap: start of the current month (MMyyyy$0) so the first pull
        // only brings this month's punches rather than the whole history.
        $last = date('mY') . '$0';
    }

    $res = hr_etime_fetch_last($last);
    if (!$res['ok']) {
        return ['ok' => false, 'added' => 0, 'attendance_updated' => 0, 'max' => '', 'error' => $res['error']];
    }

    $punch_data = isset($res['body']['PunchData']) && is_array($res['body']['PunchData']) ? $res['body']['PunchData'] : [];
    $imp        = $CI->hr_model->import_etime_punches($punch_data);
    $max        = isset($res['body']['MaxRecord']) ? (string) $res['body']['MaxRecord'] : '';
    if ($max !== '') {
        update_option('hr_etime_last_record', $max);
    }

    $updated = 0;
    if (!empty($punch_data)) {
        $dates = [];
        foreach ($punch_data as $p) {
            $ts = hr_etime_parse_dt($p['PunchDate'] ?? '');
            if ($ts) {
                $dates[date('Y-m-d', $ts)] = true;
            }
        }
        if ($dates) {
            $keys    = array_keys($dates);
            $updated = $CI->hr_model->rebuild_attendance_from_punches(min($keys), max($keys));
        }
    }

    update_option('hr_etime_last_sync', json_encode([
        'at'                 => date('Y-m-d H:i:s'),
        'punches_added'      => $imp['added'],
        'attendance_updated' => $updated,
        'unmapped'           => count($imp['unmapped']),
        'max_record'         => $max,
        'mode'               => 'incremental',
    ]));

    return ['ok' => true, 'added' => $imp['added'], 'attendance_updated' => $updated, 'max' => $max, 'error' => ''];
}

/**
 * Cron entry point — auto-pull recent punches on a rolling window. Guarded by
 * both the master enable and the auto-sync toggle, and throttled to ~hourly so
 * it survives high-frequency cron schedules. Safe to call anywhere.
 */
function hr_etime_sync_cron()
{
    if (get_option('hr_etime_enabled') !== '1' || get_option('hr_etime_auto_sync') !== '1') {
        return;
    }
    if (!hr_etime_configured()) {
        return;
    }
    $last = (int) get_option('hr_etime_cron_last_ts');
    if ($last && (time() - $last) < 3300) {
        return; // ran within the last 55 minutes
    }
    update_option('hr_etime_cron_last_ts', (string) time());

    $window = (int) get_option('hr_etime_sync_window_days');
    $window = $window > 0 ? min(31, $window) : 2;
    $to     = date('Y-m-d');
    $from   = date('Y-m-d', strtotime('-' . ($window - 1) . ' days'));

    hr_etime_run_sync($from, $to);
}

/**
 * Fetch a Zoom Server-to-Server OAuth access token (account_credentials grant).
 * Returns [token, error].
 */
function hr_zoom_access_token()
{
    $account_id = get_option('hr_zoom_account_id');
    $client_id  = get_option('hr_zoom_client_id');
    $secret     = get_option('hr_zoom_client_secret');
    if ($account_id === '' || $client_id === '' || $secret === '') {
        return ['token' => '', 'error' => 'Zoom credentials are incomplete.'];
    }

    $res = hr_http_json(
        'POST',
        'https://zoom.us/oauth/token?grant_type=account_credentials&account_id=' . rawurlencode($account_id),
        '',
        [
            'Authorization: Basic ' . base64_encode($client_id . ':' . $secret),
            'Content-Type: application/x-www-form-urlencoded',
        ]
    );
    if (!$res['ok'] || empty($res['body']['access_token'])) {
        return ['token' => '', 'error' => 'Zoom auth failed: ' . ($res['error'] ?: 'no token returned')];
    }

    return ['token' => $res['body']['access_token'], 'error' => ''];
}

/**
 * Create a Zoom meeting. $args: topic, start_time (Y-m-d H:i:s local),
 * duration (min), timezone, agenda. Returns a normalised
 * ['ok','url','id','password','host_url','error'].
 */
function hr_zoom_create_meeting($args)
{
    $auth = hr_zoom_access_token();
    if ($auth['error']) {
        return ['ok' => false, 'url' => '', 'id' => '', 'password' => '', 'host_url' => '', 'error' => $auth['error']];
    }
    $host = get_option('hr_zoom_host_email');
    $host = $host !== '' ? rawurlencode($host) : 'me';

    // Zoom wants start_time in ISO 8601. When a timezone is supplied we send the
    // local wall-clock time plus the timezone; otherwise UTC.
    $tz         = $args['timezone'] ?: 'UTC';
    $start_iso  = date('Y-m-d\TH:i:s', strtotime($args['start_time']));

    $res = hr_http_json(
        'POST',
        'https://api.zoom.us/v2/users/' . $host . '/meetings',
        [
            'topic'      => mb_substr((string) $args['topic'], 0, 200),
            'type'       => 2, // scheduled
            'start_time' => $start_iso,
            'duration'   => max(1, (int) $args['duration']),
            'timezone'   => $tz,
            'agenda'     => mb_substr((string) ($args['agenda'] ?? ''), 0, 2000),
            'settings'   => [
                'join_before_host' => true,
                'waiting_room'     => true,
                'approval_type'    => 2,
            ],
        ],
        [
            'Authorization: Bearer ' . $auth['token'],
            'Content-Type: application/json',
        ]
    );
    if (!$res['ok'] || empty($res['body']['join_url'])) {
        return ['ok' => false, 'url' => '', 'id' => '', 'password' => '', 'host_url' => '', 'error' => 'Zoom meeting create failed: ' . $res['error']];
    }
    $b = $res['body'];

    return [
        'ok'       => true,
        'url'      => $b['join_url'],
        'id'       => (string) ($b['id'] ?? ''),
        'password' => (string) ($b['password'] ?? ''),
        'host_url' => (string) ($b['start_url'] ?? ''),
        'error'    => '',
    ];
}

/**
 * Base64url encode (JWT).
 */
function hr_base64url($data)
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/**
 * Obtain a Google OAuth access token via a service-account JWT (signed JWT
 * bearer flow), impersonating the configured Workspace user. Returns [token, error].
 */
function hr_google_access_token()
{
    $json = json_decode((string) get_option('hr_google_sa_json'), true);
    $sub  = get_option('hr_google_impersonate_email');
    if (!is_array($json) || empty($json['client_email']) || empty($json['private_key'])) {
        return ['token' => '', 'error' => 'Google service-account JSON is invalid.'];
    }
    if ($sub === '') {
        return ['token' => '', 'error' => 'Google impersonation email is not set.'];
    }
    if (!function_exists('openssl_sign')) {
        return ['token' => '', 'error' => 'PHP OpenSSL extension is required for Google Meet.'];
    }

    $now    = time();
    $header = hr_base64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $claim  = hr_base64url(json_encode([
        'iss'   => $json['client_email'],
        'sub'   => $sub,
        'scope' => 'https://www.googleapis.com/auth/calendar.events',
        'aud'   => 'https://oauth2.googleapis.com/token',
        'iat'   => $now,
        'exp'   => $now + 3600,
    ]));
    $signature = '';
    if (!openssl_sign($header . '.' . $claim, $signature, $json['private_key'], 'sha256WithRSAEncryption')) {
        return ['token' => '', 'error' => 'Failed to sign Google JWT (check the private key).'];
    }
    $jwt = $header . '.' . $claim . '.' . hr_base64url($signature);

    $res = hr_http_json(
        'POST',
        'https://oauth2.googleapis.com/token',
        http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $jwt,
        ]),
        ['Content-Type: application/x-www-form-urlencoded']
    );
    if (!$res['ok'] || empty($res['body']['access_token'])) {
        return ['token' => '', 'error' => 'Google auth failed: ' . ($res['error'] ?: 'no token returned')];
    }

    return ['token' => $res['body']['access_token'], 'error' => ''];
}

/**
 * Create a Google Calendar event with a Meet conference link. $args: topic,
 * start_time (Y-m-d H:i:s), duration (min), timezone, agenda, attendees (emails).
 * Returns normalised ['ok','url','id','password','host_url','error'].
 */
function hr_google_meet_create($args)
{
    $auth = hr_google_access_token();
    if ($auth['error']) {
        return ['ok' => false, 'url' => '', 'id' => '', 'password' => '', 'host_url' => '', 'error' => $auth['error']];
    }
    $calendar = rawurlencode(get_option('hr_google_impersonate_email'));
    $tz       = $args['timezone'] ?: date_default_timezone_get();
    $start_ts = strtotime($args['start_time']);
    $end_ts   = $start_ts + max(1, (int) $args['duration']) * 60;

    $attendees = [];
    foreach (($args['attendees'] ?? []) as $email) {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $attendees[] = ['email' => $email];
        }
    }

    // A stable-enough unique request id (Date::now unavailable in workflows but
    // this is normal runtime, uniqid is fine here).
    $request_id = 'hrint-' . uniqid();

    $body = [
        'summary'     => mb_substr((string) $args['topic'], 0, 200),
        'description' => (string) ($args['agenda'] ?? ''),
        'start'       => ['dateTime' => date('c', $start_ts), 'timeZone' => $tz],
        'end'         => ['dateTime' => date('c', $end_ts), 'timeZone' => $tz],
        'conferenceData' => [
            'createRequest' => [
                'requestId'             => $request_id,
                'conferenceSolutionKey' => ['type' => 'hangoutsMeet'],
            ],
        ],
    ];
    if (!empty($attendees)) {
        $body['attendees'] = $attendees;
    }

    $res = hr_http_json(
        'POST',
        'https://www.googleapis.com/calendar/v3/calendars/' . $calendar . '/events?conferenceDataVersion=1&sendUpdates=all',
        $body,
        [
            'Authorization: Bearer ' . $auth['token'],
            'Content-Type: application/json',
        ]
    );
    if (!$res['ok']) {
        return ['ok' => false, 'url' => '', 'id' => '', 'password' => '', 'host_url' => '', 'error' => 'Google Meet create failed: ' . $res['error']];
    }
    $b   = $res['body'];
    $url = $b['hangoutLink'] ?? '';
    if ($url === '' && !empty($b['conferenceData']['entryPoints'])) {
        foreach ($b['conferenceData']['entryPoints'] as $ep) {
            if (($ep['entryPointType'] ?? '') === 'video' && !empty($ep['uri'])) {
                $url = $ep['uri'];
                break;
            }
        }
    }
    if ($url === '') {
        return ['ok' => false, 'url' => '', 'id' => '', 'password' => '', 'host_url' => '', 'error' => 'Google event created but no Meet link was returned (is Meet enabled for the Workspace?).'];
    }

    return [
        'ok'       => true,
        'url'      => $url,
        'id'       => (string) ($b['id'] ?? ''),
        'password' => '',
        'host_url' => (string) ($b['htmlLink'] ?? ''),
        'error'    => '',
    ];
}

/**
 * Dispatch meeting creation for the chosen platform. Returns the normalised
 * result array; for non-online platforms returns ok with empty url.
 */
function hr_interview_create_meeting($platform, $args)
{
    if ($platform === 'zoom') {
        return hr_zoom_create_meeting($args);
    }
    if ($platform === 'google_meet') {
        return hr_google_meet_create($args);
    }

    return ['ok' => true, 'url' => '', 'id' => '', 'password' => '', 'host_url' => '', 'error' => ''];
}

/**
 * Send a simple HTML email via the app's configured SMTP. Returns bool.
 * Failures are swallowed (logged) so a mail hiccup never blocks scheduling.
 */
function hr_send_html_email($to, $subject, $html)
{
    if (!$to || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $CI = &get_instance();
    try {
        $CI->load->library('email');
        $CI->email->clear(true);
        $CI->email->initialize([
            'mailtype' => 'html',
            'charset'  => 'utf-8',
            'newline'  => "\r\n",
        ]);
        $CI->email->from(get_option('smtp_email') ?: ('noreply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost')), get_option('companyname'));
        $CI->email->to($to);
        $CI->email->subject($subject);
        $CI->email->message($html);

        return (bool) $CI->email->send(false);
    } catch (Throwable $e) {
        log_activity('HR email send failed [' . $e->getMessage() . ']');

        return false;
    }
}
