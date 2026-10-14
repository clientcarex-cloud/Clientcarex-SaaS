<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * ePTW — shared vocabulary, access model, options and small utilities.
 *
 * Required from the module bootstrap because the admin screens, the cron and
 * the schema self-heal all need the same definitions.
 */

/* ═══════════════════════════════ Options ══════════════════════════════ */

function eptw_default_options()
{
    return [
        'eptw_company_name'        => '',
        'eptw_number_format'       => '{PROJECT}-{AREA}-{TYPE}-{YEAR}-{SERIAL}',
        'eptw_serial_padding'      => '5',
        'eptw_serial_scope'        => 'year',        // global | year | project_year | type_year | project_type_year
        'eptw_camera_mode'         => 'allowed',     // allowed | restricted | disabled
        'eptw_expiring_hours'      => '4',
        'eptw_auto_activate'       => '1',
        'eptw_email_notifications' => '1',
        'eptw_required_docs'       => 'permit_scan,ra_jsa,method_statement,toolbox_talk',
        'eptw_max_upload_mb'       => '15',
        'eptw_max_extensions'      => '3',
        'eptw_simops_enabled'      => '1',
        'eptw_wah_threshold_m'     => '1.8',
        'eptw_shared_areas'        => '0',         // 1 = areas may be shared by every project
        'eptw_sod_rules'           => '',          // comma list, see eptw_sod_rule_options()
        'eptw_toolbox_mode'        => 'both',      // digital | upload | both
        'eptw_docs_review'         => '0',         // 1 = required documents count once verified
        'eptw_escalate_high_risk'  => '0',         // 1 = high-risk permits also need the Manager
        'eptw_dashboard_refresh'   => '120',       // seconds, 0 = off
        'eptw_overdue_hours'       => '24',        // expired this long → escalated on the dashboard
        'eptw_last_cron'           => '',
        'eptw_schema_version'      => '',
    ];
}

function eptw_opt($name)
{
    $value = get_option($name);
    if ($value === '' || $value === false || $value === null) {
        $defaults = eptw_default_options();

        return $defaults[$name] ?? '';
    }

    return $value;
}

/* ═══════════════════════════════ Roles ════════════════════════════════ */

function eptw_roles()
{
    return [
        'engineer'       => ['label' => 'Engineer / Performing Authority', 'short' => 'Engineer',     'icon' => 'fa-solid fa-helmet-safety'],
        'hse'            => ['label' => 'HSE Officer',                     'short' => 'HSE',          'icon' => 'fa-solid fa-shield-halved'],
        'area_authority' => ['label' => 'Area Authority',                  'short' => 'Area Auth.',   'icon' => 'fa-solid fa-map-location-dot'],
        'coordinator'    => ['label' => 'PTW Coordinator',                 'short' => 'Coordinator',  'icon' => 'fa-solid fa-clipboard-list'],
        'manager'        => ['label' => 'Manager',                         'short' => 'Manager',      'icon' => 'fa-solid fa-chart-line'],
        'admin'          => ['label' => 'ePTW Administrator',              'short' => 'Admin',        'icon' => 'fa-solid fa-user-gear'],
    ];
}

function eptw_role_label($role)
{
    $roles = eptw_roles();

    return $roles[$role]['label'] ?? ucfirst(str_replace('_', ' ', (string) $role));
}

/**
 * The current staff member's ePTW identity: role and project scope.
 * Perfex administrators are always ePTW administrators with full scope.
 *
 * @return array{staff_id:int, role:string, projects:array|null, active:bool}
 */
function eptw_me()
{
    static $me = null;
    if ($me !== null) {
        return $me;
    }

    $staff_id = (int) get_staff_user_id();
    $me       = ['staff_id' => $staff_id, 'role' => '', 'projects' => null, 'active' => false];

    if (!$staff_id) {
        return $me;
    }

    if (is_admin()) {
        $me['role']   = 'admin';
        $me['active'] = true;

        return $me;
    }

    $CI = &get_instance();
    if (!$CI->db->table_exists(db_prefix() . 'eptw_team')) {
        return $me;
    }

    $row = $CI->db->where('staff_id', $staff_id)->where('active', 1)->get(db_prefix() . 'eptw_team')->row();
    if (!$row) {
        return $me;
    }

    $projects = json_decode((string) $row->project_ids, true);
    $projects = is_array($projects) ? array_values(array_filter(array_map('intval', $projects))) : [];

    $me['role']     = $row->role;
    $me['projects'] = count($projects) ? $projects : null;
    $me['active']   = true;

    return $me;
}

function eptw_role()
{
    return eptw_me()['role'];
}

/**
 * May this staff member use ePTW at all? Either they are on the permit team,
 * or an administrator gave them an ePTW menu on their staff profile.
 */
function eptw_can_access()
{
    return eptw_me()['active'] || eptw_has_menu_permission();
}

/* ══════════════════════ Menu permissions (Staff → Permissions) ═════════ */

/**
 * One staff-permission feature per ePTW menu. These decide which menus and
 * pages a staff member reaches; the ePTW team role (above) still decides the
 * workflow steps they may take on a permit (review, issue, suspend …).
 *
 * `roles` lists the team roles that are granted the capability by default —
 * used once when upgrading and whenever a staff member first joins the team.
 */
function eptw_menu_permissions()
{
    $crud    = ['view' => 'View', 'create' => 'Create', 'edit' => 'Edit', 'delete' => 'Delete'];
    $all     = array_keys(eptw_roles());
    $authors = ['engineer', 'coordinator', 'area_authority', 'hse', 'admin'];
    $readers = ['coordinator', 'manager', 'hse', 'area_authority', 'admin'];

    return [
        'eptw_dashboard'         => ['name' => 'Dashboard',                    'url' => 'eptw',                         'caps' => ['view' => 'View'],                                          'roles' => ['view' => $all]],
        'eptw_corporate'         => ['name' => 'Corporate dashboard',          'url' => 'eptw/corporate',               'caps' => ['view' => 'View'],                                          'roles' => ['view' => ['manager', 'admin']]],
        'eptw_register'          => ['name' => 'Permit register',              'url' => 'eptw/register',                'caps' => ['view' => 'View', 'export' => 'Export to Excel'],           'roles' => ['view' => $all, 'export' => $all]],
        'eptw_new_permit'        => ['name' => 'New permit',                   'url' => 'eptw/permit',                  'caps' => ['create' => 'Create'],                                      'roles' => ['create' => $authors]],
        'eptw_approvals'         => ['name' => 'Pending approvals',            'url' => 'eptw/register?view=pending',   'caps' => ['view' => 'View'],                                          'roles' => ['view' => ['area_authority', 'hse', 'coordinator', 'admin']]],
        'eptw_reports'           => ['name' => 'Reports',                      'url' => 'eptw/reports',                 'caps' => ['view' => 'View', 'export' => 'Export to Excel'],           'roles' => ['view' => $readers, 'export' => $readers]],
        'eptw_setup_settings'    => ['name' => 'Setup — General & numbering',  'url' => 'eptw/eptw_setup',              'caps' => ['view' => 'View', 'edit' => 'Edit'],                        'roles' => ['view' => ['admin'], 'edit' => ['admin']]],
        'eptw_setup_projects'    => ['name' => 'Setup — Projects & areas',     'url' => 'eptw/eptw_setup/projects',     'caps' => $crud,                                                       'roles' => array_fill_keys(array_keys($crud), ['admin'])],
        'eptw_setup_contractors' => ['name' => 'Setup — Contractors',          'url' => 'eptw/eptw_setup/contractors',  'caps' => $crud,                                                       'roles' => array_fill_keys(array_keys($crud), ['admin'])],
        'eptw_setup_types'       => ['name' => 'Setup — Permit types',         'url' => 'eptw/eptw_setup/types',        'caps' => $crud,                                                       'roles' => array_fill_keys(array_keys($crud), ['admin'])],
        'eptw_setup_people'      => ['name' => 'Setup — People (site personnel)', 'url' => 'eptw/eptw_setup/people',     'caps' => $crud,                                                       'roles' => array_fill_keys(array_keys($crud), ['coordinator', 'admin'])],
        'eptw_setup_team'        => ['name' => 'Setup — Team & roles',         'url' => 'eptw/eptw_setup/team',         'caps' => $crud,                                                       'roles' => array_fill_keys(array_keys($crud), ['admin'])],
        'eptw_setup_simops'      => ['name' => 'Setup — SIMOPS rules',         'url' => 'eptw/eptw_setup/simops',       'caps' => $crud,                                                       'roles' => array_fill_keys(array_keys($crud), ['admin'])],
        'eptw_setup_import'      => ['name' => 'Setup — Import Excel register', 'url' => 'eptw/eptw_setup/import',      'caps' => ['view' => 'View', 'create' => 'Run import'],                'roles' => ['view' => ['coordinator', 'admin'], 'create' => ['coordinator', 'admin']]],
    ];
}

/** Perfex administrators hold every menu permission. */
function eptw_perm($feature, $capability = 'view')
{
    return is_admin() || staff_can($capability, $feature);
}

/** Setup screens the current staff member may open, in menu order. */
function eptw_setup_features()
{
    return array_values(array_filter(array_keys(eptw_menu_permissions()), function ($f) {
        return strpos($f, 'eptw_setup_') === 0 && eptw_perm($f, 'view');
    }));
}

/**
 * Where a staff member should land in the module: the first ePTW menu they
 * may open, or null when they have none.
 */
function eptw_home_url()
{
    foreach (eptw_menu_permissions() as $feature => $menu) {
        if (eptw_perm($feature, $feature === 'eptw_new_permit' ? 'create' : 'view')) {
            return admin_url($menu['url']);
        }
    }

    return null;
}

/**
 * Does this staff member hold any ePTW menu at all?
 *
 * @param bool $recheck drop the cached answer (permissions just changed)
 */
function eptw_has_menu_permission($recheck = false)
{
    static $any = null;
    if ($recheck) {
        $any = null;
    }
    if ($any !== null) {
        return $any;
    }

    $any = false;
    foreach (eptw_menu_permissions() as $feature => $menu) {
        foreach (array_keys($menu['caps']) as $capability) {
            if (eptw_perm($feature, $capability)) {
                return $any = true;
            }
        }
    }

    return $any;
}

/**
 * Give a staff member the default menu permissions for their team role —
 * only when they have no ePTW menu permission yet, so an administrator's
 * choices on the staff profile are never overwritten.
 */
function eptw_grant_role_permissions($staff_id, $role)
{
    $CI       = &get_instance();
    $staff_id = (int) $staff_id;
    $table    = db_prefix() . 'staff_permissions';

    if (!$staff_id || !$CI->db->table_exists($table)) {
        return;
    }
    if ($CI->db->where('staff_id', $staff_id)->like('feature', 'eptw_', 'after')->count_all_results($table) > 0) {
        return;
    }

    $granted = [];
    foreach (eptw_menu_permissions() as $feature => $menu) {
        foreach ($menu['roles'] as $capability => $roles) {
            if (in_array($role, $roles, true)) {
                $row = ['staff_id' => $staff_id, 'feature' => $feature, 'capability' => $capability];
                $CI->db->insert($table, $row);
                $granted[] = $row;
            }
        }
    }

    // The logged-in user's permissions were read at the start of the request.
    // Without this they would see the new menus only after one more page load.
    if ($staff_id === (int) get_staff_user_id() && isset($GLOBALS['current_user']) && count($granted)) {
        $GLOBALS['current_user']->permissions = array_merge((array) $GLOBALS['current_user']->permissions, $granted);
        eptw_has_menu_permission(true);
    }
}

/**
 * Role → what it may do. Ownership (an engineer editing their own draft) is
 * checked by the model on top of this. The menu-level actions come from the
 * staff permissions instead (see eptw_menu_permissions).
 */
function eptw_can($action)
{
    switch ($action) {
        case 'create':
            return eptw_perm('eptw_new_permit', 'create');
        case 'reports':
            return eptw_perm('eptw_reports');
        case 'register':
            return eptw_perm('eptw_register', 'export');
        case 'import':
            return eptw_perm('eptw_setup_import', 'create');
        case 'setup':
            return count(eptw_setup_features()) > 0;
    }

    $role = eptw_role();
    if ($role === '') {
        // Menus but no team role: they read what those menus show — the
        // register is granted globally — and take no step on a permit.
        return $action === 'view_all' && (eptw_perm('eptw_register') || eptw_perm('eptw_dashboard'));
    }
    if ($role === 'admin') {
        return true;
    }

    $matrix = [
        'edit'           => ['engineer', 'coordinator', 'area_authority', 'hse'],
        'request_number' => ['engineer', 'coordinator', 'area_authority', 'hse'],
        'review'         => ['area_authority', 'hse', 'coordinator'],
        'approve_aa'     => ['area_authority'],
        'approve_hse'    => ['hse'],
        'approve_mgr'    => ['manager'],
        'issue'          => ['coordinator'],
        'status'         => ['coordinator'],          // activate / suspend / hold / resume / close / cancel
        'extend_request' => ['engineer', 'coordinator', 'area_authority', 'hse'],
        'extend_approve' => ['coordinator'],
        'upload'         => ['engineer', 'coordinator', 'hse', 'area_authority'],
        'gas_test'       => ['engineer', 'coordinator', 'hse', 'area_authority'],
        'remark'         => ['engineer', 'coordinator', 'hse', 'area_authority', 'manager'],
        'view_all'       => ['coordinator', 'manager', 'hse', 'area_authority'],
        'delete'         => [],
    ];

    return in_array($role, $matrix[$action] ?? [], true);
}

/** Project ids the current user may see, or null for "all". */
function eptw_scope()
{
    return eptw_me()['projects'];
}

function eptw_in_scope($project_id)
{
    $scope = eptw_scope();

    return $scope === null || in_array((int) $project_id, $scope, true);
}

/* ═══════════════════════════════ Statuses ═════════════════════════════ */

function eptw_statuses()
{
    return [
        'draft'               => ['label' => 'Draft',                        'badge' => 'draft', 'icon' => 'fa-regular fa-file'],
        'number_requested'    => ['label' => 'Permit number requested',      'badge' => 'info',  'icon' => 'fa-solid fa-paper-plane'],
        'under_review'        => ['label' => 'Under review',                 'badge' => 'info',  'icon' => 'fa-solid fa-magnifying-glass'],
        'returned'            => ['label' => 'Returned for correction',      'badge' => 'warn',  'icon' => 'fa-solid fa-rotate-left'],
        'issued'              => ['label' => 'Issued',                       'badge' => 'ok',    'icon' => 'fa-solid fa-stamp'],
        'active'              => ['label' => 'Active',                       'badge' => 'live',  'icon' => 'fa-solid fa-circle-play'],
        'active_extended'     => ['label' => 'Active – Extended',            'badge' => 'live',  'icon' => 'fa-solid fa-clock-rotate-left'],
        'expired'             => ['label' => 'Expired – work not authorized', 'badge' => 'bad',  'icon' => 'fa-solid fa-hourglass-end'],
        'suspended'           => ['label' => 'Suspended',                    'badge' => 'bad',   'icon' => 'fa-solid fa-circle-pause'],
        'on_hold'             => ['label' => 'On hold',                      'badge' => 'warn',  'icon' => 'fa-solid fa-hand'],
        'on_hold_simops'      => ['label' => 'On hold – SIMOPS conflict',    'badge' => 'bad',   'icon' => 'fa-solid fa-triangle-exclamation'],
        'closed'              => ['label' => 'Closed – complete',            'badge' => 'muted', 'icon' => 'fa-solid fa-circle-check'],
        'closed_docs_pending' => ['label' => 'Closed – documents pending upload', 'badge' => 'warn', 'icon' => 'fa-solid fa-folder-open'],
        'cancelled'           => ['label' => 'Cancelled',                    'badge' => 'muted', 'icon' => 'fa-solid fa-ban'],
        'archived'            => ['label' => 'Archived',                     'badge' => 'muted', 'icon' => 'fa-solid fa-box-archive'],
    ];
}

function eptw_status_label($status)
{
    $all = eptw_statuses();

    return $all[$status]['label'] ?? ucfirst(str_replace('_', ' ', (string) $status));
}

function eptw_status_badge($status, $extra_class = '')
{
    $all  = eptw_statuses();
    $meta = $all[$status] ?? ['label' => ucfirst((string) $status), 'badge' => 'muted', 'icon' => ''];

    return '<span class="eptw-badge ' . $meta['badge'] . ' ' . $extra_class . '"><i class="' . $meta['icon'] . '"></i> ' . html_escape($meta['label']) . '</span>';
}

/**
 * "Open on site": the permit has a number and is not closed yet — it counts
 * for SIMOPS, area load and the high-risk panel. Only the working statuses
 * below authorize work; every other open status means WORK NOT AUTHORIZED.
 */
function eptw_live_statuses()
{
    return ['issued', 'active', 'active_extended', 'suspended', 'on_hold', 'on_hold_simops', 'expired'];
}

/** The only statuses under which work is authorized. */
function eptw_working_statuses()
{
    return ['active', 'active_extended'];
}

/** Open statuses whose validity is swept to "expired" once the end time passes. */
function eptw_expirable_statuses()
{
    return ['issued', 'active', 'active_extended', 'suspended', 'on_hold', 'on_hold_simops'];
}

/**
 * SQL condition: end time inside the expiry warning window of the permit's
 * own type (or the global window). Needs the permits table aliased "x".
 */
function eptw_expiring_sql($now = null)
{
    $CI  = &get_instance();
    $now = $CI->db->escape($now ?: date('Y-m-d H:i:s'));
    $p   = db_prefix();

    return "x.end_at <= DATE_ADD($now, INTERVAL COALESCE((SELECT NULLIF(tt.expiry_warning_hours, 0) FROM {$p}eptw_permit_types tt WHERE tt.id = x.permit_type_id), "
        . (int) eptw_opt('eptw_expiring_hours') . ') HOUR)';
}

function eptw_hold_statuses()
{
    return ['on_hold', 'on_hold_simops'];
}

/**
 * Work-authorization state, kept apart from the administrative status so the
 * site never has to infer it: ['ok' => bool, 'title' => …, 'detail' => …].
 */
function eptw_work_authorization($permit)
{
    $status = (string) $permit->status;
    $end    = strtotime((string) $permit->end_at);

    if (in_array($status, eptw_working_statuses(), true) && $end && $end > time()) {
        return ['ok' => true, 'title' => 'Work authorized', 'detail' => 'Valid until ' . eptw_dt($permit->end_at) . ' (' . eptw_time_until($permit->end_at) . ').'];
    }
    $reasons = [
        'draft'            => 'Draft — no permit number yet.',
        'number_requested' => 'Waiting for reviews and the permit number.',
        'under_review'     => 'Under review — no permit number yet.',
        'returned'         => 'Returned for correction — no permit number yet.',
        'issued'           => 'Permit number issued, but the start of work has not been confirmed.',
        'suspended'        => 'Suspended' . (!empty($permit->suspend_reason) ? ': ' . $permit->suspend_reason : '') . '. Work must stop until the permit is resumed.',
        'on_hold'          => 'On hold. Work must stop until the permit is resumed.',
        'on_hold_simops'   => 'On hold for a SIMOPS conflict. Work must not start until the conflict is resolved.',
        'expired'          => 'Validity ended ' . eptw_dt($permit->end_at) . '. Work may resume only after an approved extension; otherwise close the permit.',
        'closed'           => 'Closed.',
        'closed_docs_pending' => 'Closed — closure documents are still pending upload.',
        'cancelled'        => 'Cancelled.',
        'archived'         => 'Archived.',
    ];
    if (in_array($status, eptw_working_statuses(), true)) {
        return ['ok' => false, 'title' => 'Work not authorized', 'detail' => 'Validity ended ' . eptw_dt($permit->end_at) . '.'];
    }

    return ['ok' => false, 'title' => 'Work not authorized', 'detail' => $reasons[$status] ?? eptw_status_label($status)];
}

function eptw_pending_statuses()
{
    return ['number_requested', 'under_review'];
}

function eptw_closed_statuses()
{
    return ['closed', 'closed_docs_pending', 'cancelled', 'archived'];
}

function eptw_shifts()
{
    return ['day' => 'Day', 'night' => 'Night', 'both' => 'Day & Night'];
}

function eptw_risk_levels()
{
    return ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High'];
}

function eptw_camera_modes()
{
    return [
        'allowed'    => 'Camera allowed — photos, scans and site evidence can be captured',
        'restricted' => 'Camera restricted — upload from device storage only',
        'disabled'   => 'Camera disabled — camera controls hidden, file upload only',
    ];
}

function eptw_document_types()
{
    return [
        'permit_scan'      => 'Scanned / signed permit',
        'ra_jsa'           => 'Risk Assessment / JSA',
        'method_statement' => 'Method Statement',
        'toolbox_talk'     => 'Toolbox Talk record',
        'gas_test'         => 'Gas test records',
        'isolation_cert'   => 'Isolation certificate',
        'lift_plan'        => 'Lift plan / drawings',
        'photo'            => 'Site photo / evidence',
        'other'            => 'Other',
    ];
}

function eptw_required_doc_types()
{
    $list = array_filter(array_map('trim', explode(',', (string) eptw_opt('eptw_required_docs'))));
    $all  = eptw_document_types();

    return array_values(array_filter($list, function ($k) use ($all) {
        return isset($all[$k]);
    }));
}

/**
 * Segregation-of-duties rules a client may switch on (Setup → General).
 * Off by default: the guideline defines separate roles but does not forbid
 * one person holding two of them.
 */
function eptw_sod_rule_options()
{
    return [
        'own_permit'      => 'The initiator may not sign a review step on their own permit',
        'two_steps'       => 'One person may not sign two review steps on the same permit',
        'issuer_initiator'=> 'The permit issuer may not issue a permit they initiated',
        'own_extension'   => 'Whoever requests an extension may not approve it',
        'reval_distinct'  => 'Shift revalidation needs different people as Area Authority, Issuer and HSE',
    ];
}

function eptw_sod($rule)
{
    return in_array($rule, array_map('trim', explode(',', (string) eptw_opt('eptw_sod_rules'))), true);
}

function eptw_toolbox_modes()
{
    return [
        'both'    => 'Digital attendance or an uploaded signed sheet — either is accepted',
        'upload'  => 'Signed paper sheet uploaded after the talk (hybrid site)',
        'digital' => 'Digital attendance list with badge / ID and acknowledgement',
    ];
}

/** Isolation / LOTO applicability rule per permit type. */
function eptw_isolation_rules()
{
    return [
        'conditional' => 'Ask on each permit (Yes / No / N/A)',
        'always'      => 'Always required for this permit type',
        'never'       => 'Not applicable to this permit type',
    ];
}

/** Closure checklist items: key => [label, default when not applicable]. */
function eptw_closure_items()
{
    return [
        'work_completed'      => 'Work completed',
        'area_clean'          => 'Area clean & housekeeping done',
        'no_residual_hazards' => 'No residual hazards',
        'isolation_removed'   => 'Isolation removed / de-isolated',
        'area_restored'       => 'Area restored / handed back',
        'inspection_done'     => 'Final inspection done',
    ];
}

/** A closure item as yes | no | na | '' — understands the pre-1.2 0/1 format too. */
function eptw_closure_value($closure, $key)
{
    $v = $closure[$key] ?? null;
    if (is_array($v)) {
        return (string) ($v['v'] ?? '');
    }
    if ($v === null || $v === '') {
        return '';
    }

    return (int) $v ? 'yes' : 'no';
}

/** Type descriptions may carry {HEIGHT} — the configured work-at-height threshold. */
function eptw_type_text($text)
{
    $threshold = rtrim(rtrim(number_format((float) eptw_opt('eptw_wah_threshold_m'), 2, '.', ''), '0'), '.');

    return str_replace('{HEIGHT}', $threshold, (string) $text);
}

/** Validity limits for a permit type on a project: [default hours, max hours or 0]. */
function eptw_validity_limits($type, $project = null)
{
    $default = max(1, (int) ($type->default_validity_hours ?? 12));
    $caps    = array_filter([(int) ($type->max_validity_hours ?? 0), (int) ($project->max_validity_hours ?? 0)]);
    $max     = count($caps) ? min($caps) : 0;
    if ($max && $default > $max) {
        $default = $max;
    }

    return [$default, $max];
}

/** Expiry warning window for a permit type, in hours (type override, else global). */
function eptw_expiry_hours($type = null)
{
    $own = (int) ($type->expiry_warning_hours ?? 0);

    return $own > 0 ? $own : (int) eptw_opt('eptw_expiring_hours');
}

function eptw_suspension_reasons()
{
    return ['Weather conditions', 'Gas detection', 'SIMOPS conflict', 'Unsafe condition', 'Emergency', 'Other'];
}

/* ═══════════════════════════════ Lookups ══════════════════════════════ */

function &eptw_cache()
{
    static $cache = [];

    return $cache;
}

function eptw_projects($only_active = true)
{
    $cache = &eptw_cache();
    $key   = 'projects_' . ($only_active ? 1 : 0);
    if (!isset($cache[$key])) {
        $CI = &get_instance();
        if ($only_active) {
            $CI->db->where('active', 1);
        }
        $cache[$key] = $CI->db->order_by('name', 'asc')->get(db_prefix() . 'eptw_projects')->result();
    }

    return $cache[$key];
}

function eptw_areas($project_id = 0, $only_active = true)
{
    $CI = &get_instance();
    if ($only_active) {
        $CI->db->where('active', 1);
    }
    if ((int) $project_id > 0) {
        if (eptw_opt('eptw_shared_areas') === '1') {
            $CI->db->group_start()->where('project_id', (int) $project_id)->or_where('project_id', 0)->group_end();
        } else {
            $CI->db->where('project_id', (int) $project_id);
        }
    }

    return $CI->db->order_by('name', 'asc')->get(db_prefix() . 'eptw_areas')->result();
}

function eptw_contractors($only_active = true)
{
    $cache = &eptw_cache();
    $key   = 'contractors_' . ($only_active ? 1 : 0);
    if (!isset($cache[$key])) {
        $CI = &get_instance();
        if ($only_active) {
            $CI->db->where('active', 1);
        }
        $cache[$key] = $CI->db->order_by('name', 'asc')->get(db_prefix() . 'eptw_contractors')->result();
    }

    return $cache[$key];
}

function eptw_permit_types($only_active = true)
{
    $cache = &eptw_cache();
    $key   = 'types_' . ($only_active ? 1 : 0);
    if (!isset($cache[$key])) {
        $CI = &get_instance();
        if ($only_active) {
            $CI->db->where('active', 1);
        }
        $rows = $CI->db->order_by('sort_order', 'asc')->order_by('name', 'asc')->get(db_prefix() . 'eptw_permit_types')->result();
        foreach ($rows as $row) {
            eptw_hydrate_type($row);
        }
        $cache[$key] = $rows;
    }

    return $cache[$key];
}

function eptw_hydrate_type($row)
{
    foreach (['hazards', 'controls', 'extra_fields', 'approvals', 'keywords', 'ppe'] as $json) {
        $decoded   = json_decode((string) ($row->$json ?? ''), true);
        $row->$json = is_array($decoded) ? $decoded : [];
    }

    return $row;
}

function eptw_permit_type($id)
{
    foreach (eptw_permit_types(false) as $type) {
        if ((int) $type->id === (int) $id) {
            return $type;
        }
    }

    return null;
}

/** Active staff, id => name. */
function eptw_staff()
{
    $cache = &eptw_cache();
    if (!isset($cache['staff'])) {
        $cache['staff'] = [];
        $rows = get_instance()->db->select('staffid, firstname, lastname')
            ->where('active', 1)->order_by('firstname', 'asc')->get(db_prefix() . 'staff')->result();
        foreach ($rows as $row) {
            $cache['staff'][(int) $row->staffid] = trim($row->firstname . ' ' . $row->lastname);
        }
    }

    return $cache['staff'];
}

function eptw_staff_name($id)
{
    $id = (int) $id;
    if (!$id) {
        return '';
    }
    $staff = eptw_staff();
    if (isset($staff[$id])) {
        return $staff[$id];
    }
    // Inactive staff still need a name on old permits.
    $row = get_instance()->db->select('firstname, lastname')->where('staffid', $id)->get(db_prefix() . 'staff')->row();

    return $row ? trim($row->firstname . ' ' . $row->lastname) : '#' . $id;
}

/** Team members holding a role, optionally limited to those covering a project. id => name. */
function eptw_team_by_role($role, $project_id = 0)
{
    $CI   = &get_instance();
    $rows = $CI->db->where('role', $role)->where('active', 1)->get(db_prefix() . 'eptw_team')->result();
    $out  = [];
    foreach ($rows as $row) {
        $projects = json_decode((string) $row->project_ids, true);
        $projects = is_array($projects) ? array_map('intval', $projects) : [];
        if ($project_id && count($projects) && !in_array((int) $project_id, $projects, true)) {
            continue;
        }
        $out[(int) $row->staff_id] = eptw_staff_name($row->staff_id);
    }
    // Perfex admins can act in any role.
    foreach ($CI->db->select('staffid, firstname, lastname')->where('admin', 1)->where('active', 1)->get(db_prefix() . 'staff')->result() as $row) {
        if (!isset($out[(int) $row->staffid])) {
            $out[(int) $row->staffid] = trim($row->firstname . ' ' . $row->lastname);
        }
    }
    asort($out);

    return $out;
}

/* ═══════════════════════════════ Formatting ═══════════════════════════ */

function eptw_dt($datetime, $with_time = true)
{
    $datetime = (string) $datetime;
    if ($datetime === '' || $datetime === '0000-00-00 00:00:00' || $datetime === '0000-00-00') {
        return '—';
    }
    $ts = strtotime($datetime);
    if (!$ts) {
        return '—';
    }

    return $with_time ? date('d M Y, h:i A', $ts) : date('d M Y', $ts);
}

function eptw_time_ago($datetime)
{
    $datetime = (string) $datetime;
    if ($datetime === '' || $datetime === '0000-00-00 00:00:00') {
        return 'never';
    }
    $seconds = time() - strtotime($datetime);
    if ($seconds < 0) {
        return eptw_time_until($datetime);
    }
    if ($seconds < 60) {
        return 'just now';
    }
    if ($seconds < 3600) {
        return floor($seconds / 60) . ' min ago';
    }
    if ($seconds < 86400) {
        return floor($seconds / 3600) . ' h ago';
    }

    return floor($seconds / 86400) . ' d ago';
}

function eptw_time_until($datetime)
{
    $seconds = strtotime((string) $datetime) - time();
    if ($seconds <= 0) {
        return 'expired';
    }
    if ($seconds < 3600) {
        return 'in ' . max(1, floor($seconds / 60)) . ' min';
    }
    if ($seconds < 86400) {
        return 'in ' . floor($seconds / 3600) . ' h ' . floor(($seconds % 3600) / 60) . ' min';
    }

    return 'in ' . floor($seconds / 86400) . ' d';
}

function eptw_hours_between($from, $to)
{
    $a = strtotime((string) $from);
    $b = strtotime((string) $to);
    if (!$a || !$b) {
        return 0;
    }

    return round(($b - $a) / 3600, 1);
}

function eptw_risk_badge($level)
{
    $map = ['high' => 'bad', 'medium' => 'warn', 'low' => 'ok'];

    return '<span class="eptw-badge ' . ($map[$level] ?? 'muted') . '">' . html_escape(ucfirst((string) $level)) . ' risk</span>';
}

function eptw_initials($name)
{
    $parts = preg_split('/\s+/', trim((string) $name));
    $out   = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        $out .= mb_strtoupper(mb_substr($part, 0, 1));
    }

    return $out ?: '?';
}

function eptw_human_size($bytes)
{
    $bytes = (int) $bytes;
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 1) . ' MB';
    }
    if ($bytes >= 1024) {
        return round($bytes / 1024) . ' KB';
    }

    return $bytes . ' B';
}

/**
 * Short code from a name: "Zone A / North" → "ZONEAN". Letters and digits
 * only — the code is a segment of the hyphen-separated permit number, so a
 * hyphen inside it would make "ALPHA-Z-A-HW-2026-00021" ambiguous.
 */
function eptw_code_from_name($name, $max = 8)
{
    $code = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '', trim((string) $name)));
    if ($code === '') {
        $code = 'X';
    }

    return substr($code, 0, $max);
}

function eptw_upload_dir($permit_id)
{
    $dir = FCPATH . 'uploads/eptw/permits/' . (int) $permit_id . '/';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
        @file_put_contents(FCPATH . 'uploads/eptw/index.html', '');
        @file_put_contents(FCPATH . 'uploads/eptw/permits/index.html', '');
        @file_put_contents($dir . 'index.html', '');
    }

    return $dir;
}

/**
 * Effective camera mode for a project: the project's own setting, else the
 * client-wide default.
 */
function eptw_camera_mode($project = null)
{
    if ($project && !empty($project->camera_mode) && $project->camera_mode !== 'inherit') {
        return $project->camera_mode;
    }

    return eptw_opt('eptw_camera_mode');
}

/* ═══════════════════════════════ Hazard intelligence ══════════════════ */

/**
 * Rule-based hazard / control / PPE suggestions from the permit type and the
 * words in the work description. The permit type template supplies the
 * baseline; the keyword rules add what the description reveals ("trench",
 * "weld", "crane", ...). Returns lists of strings for the form to pre-tick.
 */
function eptw_suggest($type, $description, $area_name = '')
{
    $text = mb_strtolower((string) $description . ' ' . $area_name);

    $rules = [
        ['/weld|cutting|grind|torch|braz|spark|hot tap|burn/',                 'hazards' => ['Fire/Explosion', 'Fumes/Smoke', 'Flammable Gas'],            'controls' => ['Fire Watch Assigned', 'Fire Extinguishers (Type/Qty)', 'Combustibles Removed (10m radius)', 'Gas Testing Completed'], 'ppe' => ['Welding shield / face shield', 'Leather gloves', 'FR coveralls']],
        ['/trench|excavat|dig|backfill|underground|cable route/',              'hazards' => ['Trench collapse', 'Underground services', 'Falling Objects'], 'controls' => ['Shoring/Sloping Provided', 'Access Ladder Provided', 'Spoil at Safe Distance', 'Barricading Provided'],                 'ppe' => ['Hi-vis vest', 'Safety boots']],
        ['/crane|lift|hoist|rigging|sling|load|manlift|mewp|boom/',            'hazards' => ['Falling Objects', 'Dropped load', 'Crushing'],                'controls' => ['Lift Plan Approved', 'Taglines Used', 'No Unauthorized Access', 'Wind Speed Checked'],                                 'ppe' => ['Hard hat', 'Gloves']],
        ['/height|scaffold|roof|ladder|elevat|platform|edge/',                 'hazards' => ['Fall Hazard'],                                                'controls' => ['Full Body Harness Used', 'Anchorage Point Verified', 'Guardrails Installed', 'Rescue Plan Available'],                  'ppe' => ['Full body harness', 'Double lanyard']],
        ['/tank|vessel|manhole|silo|pit|confined|duct|entry/',                 'hazards' => ['Oxygen Deficiency', 'Toxic Gas', 'Engulfment'],              'controls' => ['Gas Testing Done', 'Ventilation Provided', 'Standby Personnel Available', 'Rescue Plan Ready'],                       'ppe' => ['Gas detector', 'Escape set / SCBA']],
        ['/electric|cable|panel|switchgear|voltage|mcc|transformer|live|kv/',  'hazards' => ['Electrical Hazard', 'Arc flash'],                            'controls' => ['Power Isolated', 'LOTO Applied', 'Zero Energy Verified', 'Test Before Touch'],                                        'ppe' => ['Insulated gloves', 'Arc-rated clothing']],
        ['/chemical|acid|solvent|paint|coat|caustic|epoxy/',                   'hazards' => ['Chemical Exposure', 'Fumes/Smoke'],                          'controls' => ['Chemical Handling Controls', 'Spill Prevention Measures', 'PPE Verified'],                                            'ppe' => ['Chemical gloves', 'Goggles', 'Respirator']],
        ['/pressure|hydro|pneumatic|test|bar|psi|blind/',                      'hazards' => ['Pressurized Systems', 'Line of fire'],                       'controls' => ['Exclusion Zone Established', 'No Personnel in Line of Fire', 'Pressure Gauge Calibrated'],                            'ppe' => ['Face shield']],
        ['/radiograph|x-ray|gamma|isotope|ndt|source/',                        'hazards' => ['Ionising radiation'],                                        'controls' => ['Controlled Area Established', 'Barricading & Signage Installed', 'Dose Meter Provided'],                              'ppe' => ['Dosimeter']],
        ['/night|dark|after hours|late shift/',                                'hazards' => ['Poor visibility', 'Fatigue'],                                 'controls' => ['Lighting Adequate', 'Extra lighting installed', 'Night supervisor present'],                                          'ppe' => ['Hi-vis vest', 'Head torch']],
        ['/heat|summer|noon|sun|hot weather|humid/',                           'hazards' => ['Heat Stress'],                                                'controls' => ['Drinking water available', 'Rest shelter available', 'Work-rest schedule implemented'],                              'ppe' => ['Cooling vest', 'Sun protection']],
        ['/traffic|road|vehicle|truck|forklift|crossing/',                     'hazards' => ['Traffic Risk', 'Moving Machinery'],                          'controls' => ['Traffic Management in Place', 'Spotter Available', 'Area Barricaded & Signage'],                                     'ppe' => ['Hi-vis vest']],
        ['/noise|jackhammer|compressor|breaker|pil(e|ing)/',                   'hazards' => ['Noise', 'Vibration'],                                         'controls' => ['Noise Control Measures', 'Vibration Monitoring (if required)'],                                                     'ppe' => ['Ear protection']],
        ['/h2s|sour|gas plant|flare|hydrocarbon|process area/',                'hazards' => ['Toxic Gas', 'Flammable Gas'],                                 'controls' => ['Gas Testing Completed', 'Continuous Monitoring Required', 'Emergency Response Ready'],                                'ppe' => ['Personal H2S monitor', 'Escape set']],
    ];

    $hazards  = [];
    $controls = [];
    $ppe      = is_array($type->ppe ?? null) ? $type->ppe : [];
    $why      = [];

    foreach ($rules as $rule) {
        if (preg_match($rule[0], $text, $m)) {
            $hazards  = array_merge($hazards, $rule['hazards']);
            $controls = array_merge($controls, $rule['controls']);
            $ppe      = array_merge($ppe, $rule['ppe']);
            $why[]    = $m[0];
        }
    }

    // Only suggest items the template actually has (others show as "extra hazards").
    $template_hazards  = array_map('strval', $type->hazards ?? []);
    $template_controls = [];
    foreach (($type->controls ?? []) as $section) {
        foreach (($section['items'] ?? []) as $item) {
            $template_controls[] = (string) $item;
        }
    }

    return [
        'hazards'        => array_values(array_unique(array_intersect($hazards, $template_hazards))),
        'extra_hazards'  => array_values(array_unique(array_diff($hazards, $template_hazards))),
        'controls'       => array_values(array_unique(array_intersect($controls, $template_controls))),
        'ppe'            => array_values(array_unique($ppe)),
        'matched'        => array_values(array_unique($why)),
    ];
}

/**
 * Risk level from the template flag, the hazards ticked and a few modifiers,
 * with the reasons spelt out so the dashboard can say why a permit is high
 * risk (a Cold Work permit can be, through its assessment).
 *
 * @return array{level:string, score:int, high_risk:bool, reasons:string[]}
 */
function eptw_risk_assess($type, array $hazards_yes, $shift = 'day', $workers = 0)
{
    $reasons = [];
    $score   = !empty($type->high_risk) ? 6 : 2;
    if (!empty($type->high_risk)) {
        $reasons[] = 'Permit type is classified high-risk';
    }
    $hz     = min(6, count($hazards_yes));
    $score += $hz;
    if ($hz) {
        $reasons[] = count($hazards_yes) . ' hazard(s) identified';
    }
    if ($shift === 'night' || $shift === 'both') {
        $score += 1;
        $reasons[] = 'Night shift';
    }
    if ((int) $workers >= 10) {
        $score += 1;
        $reasons[] = (int) $workers . ' workers';
    }
    if (!empty($type->gas_test_required)) {
        $score += 1;
        $reasons[] = 'Gas testing required';
    }

    $level = $score >= 9 ? 'high' : ($score >= 5 ? 'medium' : 'low');
    $high  = $level === 'high' || !empty($type->high_risk);
    if ($level === 'high' && empty($type->high_risk)) {
        array_unshift($reasons, 'Assessed high risk (score ' . $score . ' of 9+)');
    }

    return ['level' => $level, 'score' => $score, 'high_risk' => $high, 'reasons' => $reasons];
}

function eptw_risk_level($type, array $hazards_yes, $shift = 'day', $workers = 0)
{
    return eptw_risk_assess($type, $hazards_yes, $shift, $workers)['level'];
}

/** One line saying why a permit is (not) high risk. */
function eptw_risk_reason_text($permit)
{
    $reasons = json_decode((string) ($permit->risk_reasons ?? ''), true);
    if (is_array($reasons) && count($reasons)) {
        return implode(' · ', $reasons);
    }
    if (!empty($permit->type_high_risk)) {
        return 'Permit type is classified high-risk';
    }

    return $permit->high_risk ? 'Assessed high risk' : '';
}
