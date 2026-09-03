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

function eptw_can_access()
{
    return eptw_me()['active'];
}

/**
 * Role → what it may do. Ownership (an engineer editing their own draft) is
 * checked by the model on top of this.
 */
function eptw_can($action)
{
    $role = eptw_role();
    if ($role === '') {
        return false;
    }
    if ($role === 'admin') {
        return true;
    }

    $matrix = [
        'create'         => ['engineer', 'coordinator', 'area_authority', 'hse'],
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
        'reports'        => ['coordinator', 'manager', 'hse', 'area_authority'],
        'register'       => ['coordinator', 'manager', 'hse', 'area_authority', 'engineer'],
        'import'         => ['coordinator'],
        'delete'         => [],
        'setup'          => [],
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
        'suspended'           => ['label' => 'Suspended',                    'badge' => 'bad',   'icon' => 'fa-solid fa-circle-pause'],
        'on_hold'             => ['label' => 'On hold',                      'badge' => 'warn',  'icon' => 'fa-solid fa-hand'],
        'on_hold_simops'      => ['label' => 'On hold – SIMOPS conflict',    'badge' => 'bad',   'icon' => 'fa-solid fa-triangle-exclamation'],
        'closed'              => ['label' => 'Closed',                       'badge' => 'muted', 'icon' => 'fa-solid fa-circle-check'],
        'closed_docs_pending' => ['label' => 'Closed – documents pending',   'badge' => 'warn',  'icon' => 'fa-solid fa-folder-open'],
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

/** Statuses that count as "the permit exists on site" for SIMOPS and area load. */
function eptw_live_statuses()
{
    return ['issued', 'active', 'active_extended', 'suspended', 'on_hold', 'on_hold_simops'];
}

function eptw_working_statuses()
{
    return ['active', 'active_extended'];
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
        $CI->db->group_start()->where('project_id', (int) $project_id)->or_where('project_id', 0)->group_end();
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
 * Risk level from the template flag, the hazards ticked and a few modifiers.
 */
function eptw_risk_level($type, array $hazards_yes, $shift = 'day', $workers = 0)
{
    $score = !empty($type->high_risk) ? 6 : 2;
    $score += min(6, count($hazards_yes));
    if ($shift === 'night' || $shift === 'both') {
        $score += 1;
    }
    if ((int) $workers >= 10) {
        $score += 1;
    }
    if (!empty($type->gas_test_required)) {
        $score += 1;
    }

    if ($score >= 9) {
        return 'high';
    }
    if ($score >= 5) {
        return 'medium';
    }

    return 'low';
}
