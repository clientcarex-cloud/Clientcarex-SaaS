<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
/**
 * Smart Ticket — global asset + config loader (every client-portal page).
 *
 * Injected via the `app_customers_footer` hook so the keyboard shortcut and the
 * floating "Report an issue" launcher are available anywhere in the customer
 * area, not just on the Pro Tickets pages. Expects (from the hook):
 *   $CI                    CodeIgniter instance (for CSRF)
 *   $pst_departments[]     [{id,name}]
 *   $pst_priorities[]      [{id,name}]
 *   $pst_default_priority
 * Assets are cache-busted with filemtime so edits go live without a manual bump.
 */
// Load once per request. This partial is emitted both inline from the Pro
// Tickets views (so it survives the SaaS magic-auth bridge, which calls
// disableFooter()) and from the app_customers_footer hook (for every other
// customer page) — the guard prevents a double injection when both fire.
if (!empty($GLOBALS['__pst_smart_loaded'])) {
    return;
}
$GLOBALS['__pst_smart_loaded'] = true;

// Work whether invoked from the footer hook (vars pre-set) or included straight
// from a client view (fall back to the globally-shared client-area view vars).
$CI = isset($CI) ? $CI : get_instance();

if (!isset($pst_departments)) {
    $pst_departments = [];
    foreach ((isset($departments) ? $departments : []) as $d) {
        $pst_departments[] = ['id' => $d['departmentid'], 'name' => $d['name']];
    }
}
if (!isset($pst_priorities)) {
    $pst_priorities = [];
    foreach ((isset($priorities) ? $priorities : []) as $p) {
        $pst_priorities[] = ['id' => $p['priorityid'], 'name' => ticket_priority_translate($p['priorityid'])];
    }
}
if (!isset($pst_default_priority)) {
    $pst_default_priority = hooks()->apply_filters('new_ticket_priority_selected', 2);
}
// Most smart tickets are product problems, so preselect the technical desk when
// a department by that name exists; otherwise leave the browser's first-option
// pick alone.
if (!isset($pst_default_department)) {
    $pst_default_department = '';
    foreach ($pst_departments as $d) {
        if (stripos($d['name'], 'technical support') !== false) {
            $pst_default_department = $d['id'];
            break;
        }
    }
    $pst_default_department = hooks()->apply_filters('pro_tickets_smart_default_department', $pst_default_department, $pst_departments);
}

$pst_asset   = module_dir_url('pro_tickets', 'assets');
$pst_base    = dirname(__DIR__, 2) . '/assets';   // modules/pro_tickets/assets
$pst_ver_css = file_exists($pst_base . '/css/smart_ticket.css') ? filemtime($pst_base . '/css/smart_ticket.css') : time();
$pst_ver_js  = file_exists($pst_base . '/js/smart_ticket.js') ? filemtime($pst_base . '/js/smart_ticket.js') : time();

// Caller-overridable: submit endpoint + screenshot format. Customer portal
// uploads a PNG file; the tenant admin path embeds a lighter JPEG inline.
$pst_url           = isset($pst_url) ? $pst_url : site_url('clients/pro_tickets/smart_create');
$pst_image_format  = isset($pst_image_format) ? $pst_image_format : 'image/png';
$pst_image_quality = isset($pst_image_quality) ? $pst_image_quality : 0.92;
// Launcher corner: 'right' (customer portal) or 'left' (tenant admin, so it
// clears the SaaS support chat button that lives bottom-right).
$pst_fab_position  = isset($pst_fab_position) ? $pst_fab_position : 'right';
?>
<link rel="stylesheet" href="<?= $pst_asset; ?>/css/smart_ticket.css?v=<?= $pst_ver_css; ?>">
<script>
window.PST_SMART_TICKET = {
    url: '<?= $pst_url; ?>',
    // 194 KB screenshot engine — fetched on first capture, not on every page
    // load, since this widget is injected app-wide but used rarely.
    html2canvasUrl: '<?= $pst_asset; ?>/vendor/html2canvas.min.js?v=1.4.1',
    csrfName: '<?= $CI->security->get_csrf_token_name(); ?>',
    csrfHash: '<?= $CI->security->get_csrf_hash(); ?>',
    imageFormat: '<?= $pst_image_format; ?>',
    imageQuality: <?= (float) $pst_image_quality; ?>,
    fabPosition: '<?= $pst_fab_position; ?>',
    departments: <?= json_encode($pst_departments); ?>,
    priorities: <?= json_encode($pst_priorities); ?>,
    defaultPriority: '<?= e($pst_default_priority); ?>',
    defaultDepartment: '<?= e($pst_default_department); ?>',
    i18n: {
        report: <?= json_encode(_l('pro_tickets_smart_report')); ?>,
        title: <?= json_encode(_l('pro_tickets_smart_title')); ?>,
        hint: <?= json_encode(_l('pro_tickets_smart_hint')); ?>,
        capturing: <?= json_encode(_l('pro_tickets_smart_capturing')); ?>,
        capture: <?= json_encode(_l('pro_tickets_smart_capture')); ?>,
        capture_failed: <?= json_encode(_l('pro_tickets_smart_capture_failed')); ?>,
        cancel: <?= json_encode(_l('cancel')); ?>,
        undo: <?= json_encode(_l('pro_tickets_smart_undo')); ?>,
        clear: <?= json_encode(_l('pro_tickets_smart_clear')); ?>,
        t_box: <?= json_encode(_l('pro_tickets_smart_tool_box')); ?>,
        t_arrow: <?= json_encode(_l('pro_tickets_smart_tool_arrow')); ?>,
        t_pen: <?= json_encode(_l('pro_tickets_smart_tool_pen')); ?>,
        t_highlight: <?= json_encode(_l('pro_tickets_smart_tool_highlight')); ?>,
        screenshot: <?= json_encode(_l('pro_tickets_smart_screenshot')); ?>,
        retake: <?= json_encode(_l('pro_tickets_smart_retake')); ?>,
        edit_marks: <?= json_encode(_l('pro_tickets_smart_edit_marks')); ?>,
        subject: <?= json_encode(_l('customer_ticket_subject')); ?>,
        subject_ph: <?= json_encode(_l('pro_tickets_smart_subject_ph')); ?>,
        issue: <?= json_encode(_l('pro_tickets_smart_issue')); ?>,
        issue_ph: <?= json_encode(_l('pro_tickets_smart_issue_ph')); ?>,
        department: <?= json_encode(_l('clients_ticket_open_departments')); ?>,
        priority: <?= json_encode(_l('clients_ticket_open_priority')); ?>,
        submit: <?= json_encode(_l('pro_tickets_smart_send')); ?>,
        sending: <?= json_encode(_l('wait_text')); ?>,
        sent: <?= json_encode(_l('pro_tickets_smart_sent')); ?>,
        err_issue: <?= json_encode(_l('pro_tickets_smart_err_issue')); ?>,
        err_department: <?= json_encode(_l('pro_tickets_smart_err_department')); ?>,
        err_generic: <?= json_encode(_l('something_went_wrong')); ?>
    }
};
</script>
<script src="<?= $pst_asset; ?>/js/smart_ticket.js?v=<?= $pst_ver_js; ?>"></script>
