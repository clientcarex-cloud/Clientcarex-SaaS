<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<?php
$clientid = get_client_user_id();
if ($clientid)
    $invoice =  get_instance()->perfex_saas_model->get_company_invoice($clientid);

$alternative_host = perfex_saas_get_saas_alternative_host();
$perfex_saas_default_host = empty($alternative_host) ? perfex_saas_get_saas_default_host() : $alternative_host;
?>

<script>
"use strict";

const SAAS_MODULE_NAME = '<?= PERFEX_SAAS_MODULE_WHITELABEL_NAME; ?>';
const SAAS_MAGIC_AUTH_BASE_URL = '<?= base_url('clients/ps_magic/'); ?>';
const SAAS_DEFAULT_HOST = '<?= $perfex_saas_default_host; ?>';
const SAAS_ACTIVE_SEGMENT = (window.location
        .search.startsWith("?subscription") || window.location
        .search.startsWith("?companies")) ? window.location.search.split('&')[0] :
    '<?= empty($invoice) ? "?subscription" : "?companies"; ?>';

const SAAS_CONTROL_CLIENT_MENU = <?= (int)get_option('perfex_saas_control_client_menu'); ?>;
const SAAS_MAX_SLUG_LENGTH = <?= PERFEX_SAAS_MAX_SLUG_LENGTH; ?>;
</script>

<!-- Load client panel script and style -->
<script src="<?= perfex_saas_asset_url('js/client.js'); ?>"></script>
<script>
const appFormatMoney = new AppFormatMoney({
    removeDecimalsOnZero: <?= (int)get_option('remove_decimals_on_zero'); ?>,
    decimalPlaces: <?= (int)get_decimal_places(); ?>,
    currency: <?= json_encode(get_base_currency()); ?>,
});
</script>
<link rel="stylesheet" type="text/css" href="<?= perfex_saas_asset_url('css/client.css'); ?>" />

<!-- HealthO Pro AI theme for all client portal pages (matches Pro AI Chat "Ask AI") -->
<style>
:root {
    --hp-primary: #1E3D7B;
    --hp-primary-2: #00B4D8;
    --hp-grad: linear-gradient(135deg, #1E3D7B 0%, #122B5C 45%, #00B4D8 125%);
    --hp-ink: #14213D;
    --hp-muted: #6b7280;
    --hp-border: #E3E9F3;
    --hp-bg: #F6F9FC;
    --hp-soft: #EAF4FB;
}

/* ── Global client portal overrides ── */
body {
    background: var(--hp-bg) !important;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    color: var(--hp-ink);
}

/* Content area */
#wrapper > #content {
    background: var(--hp-bg);
}

/* Panel cards */
.panel_s,
.panel-table {
    border-radius: 14px !important;
    border: 1px solid var(--hp-border) !important;
    box-shadow: 0 1px 4px rgba(15,27,51,0.05) !important;
}
.panel_s .panel-body {
    padding: 18px 22px;
}

/* Tables */
.table > thead > tr > th {
    background: var(--hp-bg) !important;
    border-bottom: 2px solid var(--hp-border) !important;
    color: #4b5563 !important;
    font-weight: 600;
    font-size: 11.5px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 10px 14px !important;
}
.table > tbody > tr > td {
    border-bottom: 1px solid #eef2f8 !important;
    border-top: none !important;
    padding: 9px 14px !important;
    color: #374151;
    font-size: 13px;
    vertical-align: middle !important;
}
.table > tbody > tr:hover {
    background: #f4f9fd !important;
}

/* Buttons */
.btn-default {
    border-radius: 10px;
    border-color: var(--hp-border);
    font-size: 13px;
}
.btn-default:hover,
.btn-default:focus {
    background: var(--hp-soft);
    border-color: #a9cfe5;
    color: var(--hp-primary);
}
.btn-info {
    background: var(--hp-grad) !important;
    border: none !important;
    border-radius: 10px;
    box-shadow: 0 3px 8px rgba(18,43,92,0.25);
}
.btn-info:hover {
    background: var(--hp-grad) !important;
    filter: brightness(1.1);
    box-shadow: 0 5px 14px rgba(18,43,92,0.35);
}

/* Tabs / Navigation */
.nav-tabs {
    border-bottom: 2px solid var(--hp-border);
}
.nav-tabs > li > a {
    border: none !important;
    border-radius: 10px 10px 0 0;
    color: var(--hp-muted);
    font-weight: 500;
    font-size: 13px;
    padding: 9px 16px;
    transition: all 0.15s ease;
}
.nav-tabs > li > a:hover {
    background: var(--hp-soft);
    color: var(--hp-primary);
}
.nav-tabs > li.active > a,
.nav-tabs > li.active > a:hover,
.nav-tabs > li.active > a:focus {
    border: none !important;
    border-bottom: 2px solid var(--hp-primary-2) !important;
    color: var(--hp-primary) !important;
    font-weight: 600;
    background: transparent;
}

/* Page titles */
.page-title {
    font-size: 18px !important;
    font-weight: 700;
    color: var(--hp-ink);
}

/* Section headings (invoices, estimates, …) */
h4.section-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 8px;
    font-size: 17px;
    font-weight: 700;
    color: var(--hp-ink);
    margin-bottom: 14px;
}
h4.section-heading .view-account-statement {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border: 1px solid #d3e5f2;
    background: var(--hp-soft);
    color: var(--hp-primary);
    border-radius: 999px;
    padding: 5px 14px;
    font-size: 12px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.15s ease;
}
h4.section-heading .view-account-statement:hover {
    background: var(--hp-primary);
    border-color: var(--hp-primary);
    color: #fff;
    box-shadow: 0 3px 8px rgba(18,43,92,0.3);
}

/* Alerts */
.alert {
    border-radius: 12px;
    border: none;
    font-size: 13px;
    padding: 12px 18px;
}
.alert-warning {
    background: linear-gradient(180deg,#fffaf1,#fff5e4) !important;
    color: #92400e !important;
    border: 1px solid #ffe3b3 !important;
    border-left: 4px solid #f59e0b !important;
}
.alert-danger {
    background: #fef2f2 !important;
    color: #991b1b !important;
    border: 1px solid #fecaca !important;
    border-left: 4px solid #ef4444 !important;
}

/* Form inputs */
.form-control {
    border: 1.5px solid #dde6f0 !important;
    border-radius: 10px !important;
    padding: 8px 12px;
    font-size: 13px;
    background: #F7FAFC;
    transition: border-color 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
}
.form-control:focus {
    border-color: var(--hp-primary-2) !important;
    background: #fff;
    box-shadow: 0 0 0 3px rgba(0,180,216,0.16) !important;
}

/* Status labels / Badges */
.label {
    border-radius: 999px;
    padding: 4px 11px;
    font-weight: 600;
    font-size: 11px;
}
.label-success {
    background: linear-gradient(135deg, #27AE60, #2ECC71) !important;
}
.label-danger {
    background: linear-gradient(135deg, #ef4444, #f87171) !important;
}
.label-warning {
    background: linear-gradient(135deg, #f59e0b, #fbbf24) !important;
    color: #fff !important;
}
.label-info {
    background: linear-gradient(135deg, #1E3D7B, #00B4D8) !important;
    color: #fff !important;
}

<?php if (get_option('clients_default_theme') !== 'healthO') : ?>
/* Top navbar (skipped for the HealthO theme, which ships its own gradient navbar) */
nav.navbar.header {
    background: #fff;
    border: none;
    border-bottom: 1px solid var(--hp-border);
    box-shadow: 0 1px 4px rgba(15,27,51,0.04);
}
nav.navbar.header .navbar-nav > li > a {
    color: #4b5563;
    font-size: 13px;
    font-weight: 500;
    transition: color 0.15s ease, background 0.15s ease;
}
nav.navbar.header .navbar-nav > li > a:hover,
nav.navbar.header .navbar-nav > li > a:focus {
    color: var(--hp-primary);
    background: var(--hp-soft);
}
nav.navbar.header .navbar-nav > li.active > a {
    color: var(--hp-primary);
    font-weight: 600;
    box-shadow: inset 0 -2px 0 var(--hp-primary-2);
    background: transparent;
}
<?php endif; ?>

/* Client sidebar nav */
.customer-top-submenu li a {
    font-size: 13px;
    border-radius: 999px;
    transition: color 0.15s ease, background 0.15s ease;
}
.customer-top-submenu li a:hover {
    color: var(--hp-primary) !important;
    background: var(--hp-soft);
}
.customer-top-submenu li.active a {
    color: var(--hp-primary) !important;
    background: var(--hp-soft);
    font-weight: 600;
}

/* Pagination */
.pagination > li > a {
    border-radius: 8px !important;
    margin: 0 2px;
    border-color: var(--hp-border);
    color: var(--hp-muted);
    font-size: 13px;
}
.pagination > .active > a,
.pagination > .active > a:hover,
.pagination > .active > a:focus,
.pagination > li.paginate_button.active > a {
    background: var(--hp-grad) !important;
    border-color: transparent !important;
    color: #fff !important;
    font-weight: 600;
    box-shadow: 0 3px 8px rgba(18, 43, 92, 0.3);
}

/* DataTables chrome (invoices list & co.) — compact */
div.dataTables_wrapper .dataTables_filter input,
div.dataTables_wrapper .dataTables_length select {
    border: 1.5px solid #dde6f0 !important;
    border-radius: 10px !important;
    background: #fff;
    font-size: 13px;
    padding: 6px 11px;
}
div.dataTables_wrapper .dataTables_filter input:focus {
    border-color: var(--hp-primary-2) !important;
    box-shadow: 0 0 0 3px rgba(0,180,216,0.16);
    outline: none;
}
/* Search field renders as input-group (magnifier addon + input) — merge into one pill */
div.dataTables_wrapper div.dataTables_filter .input-group {
    display: flex;
    align-items: stretch;
}
div.dataTables_wrapper div.dataTables_filter .input-group .input-group-addon {
    display: flex;
    align-items: center;
    border: 1.5px solid #dde6f0;
    border-right: 0;
    background: #fff;
    color: #8aa0b8;
    border-radius: 10px 0 0 10px;
    padding: 6px 4px 6px 13px;
    transition: border-color 0.15s ease, color 0.15s ease;
}
div.dataTables_wrapper div.dataTables_filter .input-group input {
    flex: 1 1 auto;
    width: auto;
    min-width: 0;
    border-radius: 0 10px 10px 0 !important;
    border-left: 0 !important;
    background: #fff;
    box-shadow: none;
}
div.dataTables_wrapper div.dataTables_filter .input-group input:focus {
    border-color: #dde6f0 !important;
    box-shadow: none;
}
div.dataTables_wrapper div.dataTables_filter .input-group:focus-within .input-group-addon,
div.dataTables_wrapper div.dataTables_filter .input-group:focus-within input {
    border-color: var(--hp-primary-2) !important;
}
div.dataTables_wrapper div.dataTables_filter .input-group:focus-within .input-group-addon {
    color: var(--hp-primary-2);
}
div.dataTables_wrapper .dataTables_info {
    color: #8aa0b8;
    font-size: 12px;
}
table.dataTable thead .sorting:before, table.dataTable thead .sorting:after,
table.dataTable thead .sorting_asc:before, table.dataTable thead .sorting_asc:after,
table.dataTable thead .sorting_desc:before, table.dataTable thead .sorting_desc:after {
    color: #a9cfe5;
}
.table-invoices .invoice-number {
    font-weight: 600;
    color: var(--hp-primary);
}
.table-invoices .invoice-number:hover {
    color: #0096B7;
}

/* ── Invoice quick stats — compact tiles ── */
.invoices-stats {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin: 0 0 4px;
}
.invoices-stats > [class*="col-md-3"] {
    flex: 1 1 190px;
    width: auto;
    padding: 0;
    float: none;
}
.invoices-stats > [class*="col-md-3"] > .row {
    margin: 0;
    height: 100%;
    background: #fff;
    border: 1px solid var(--hp-border);
    border-radius: 12px;
    padding: 10px 14px 12px;
    box-shadow: 0 1px 4px rgba(15,27,51,0.05);
    transition: border-color 0.15s ease, box-shadow 0.15s ease, transform 0.15s ease;
}
.invoices-stats > [class*="col-md-3"] > .row:hover {
    border-color: #a9cfe5;
    box-shadow: 0 5px 14px rgba(0,150,183,0.16);
    transform: translateY(-1px);
}
.invoices-stats .stats-status,
.invoices-stats .stats-numbers {
    width: auto;
    padding: 0;
    float: none;
    display: inline-block;
}
.invoices-stats > [class*="col-md-3"] > .row > .col-md-12 {
    width: 100%;
    padding: 0;
    float: none;
}
.invoices-stats .stats-status a {
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.3px;
    text-transform: uppercase;
    color: #8aa0b8 !important;
    text-decoration: none;
}
.invoices-stats .stats-status a:hover {
    color: var(--hp-primary) !important;
}
.invoices-stats .stats-numbers {
    float: right;
    font-size: 13px;
    font-weight: 700;
    color: var(--hp-ink);
}
.invoices-stats .progress {
    height: 6px;
    margin: 0;
    border-radius: 99px;
    background: #edf2f8;
    box-shadow: none;
}
.invoices-stats .progress .progress-bar {
    border-radius: 99px;
    box-shadow: none;
    font-size: 0;
    color: transparent;
}
.invoices-stats .progress .progress-bar-success {
    background: linear-gradient(90deg, #27AE60, #2ECC71);
}
.invoices-stats .progress .progress-bar-danger {
    background: linear-gradient(90deg, #ef4444, #f87171);
}
.invoices-stats .progress .progress-bar-warning {
    background: linear-gradient(90deg, #f59e0b, #fbbf24);
}
.invoices-stats + hr {
    border-color: var(--hp-border);
    margin: 14px 0;
}

/* Ticket list */
.ticket-single {
    border-radius: 12px !important;
    border: 1px solid var(--hp-border) !important;
    transition: box-shadow 0.2s ease, border-color 0.2s ease;
}
.ticket-single:hover {
    border-color: #a9cfe5 !important;
    box-shadow: 0 5px 14px rgba(0,150,183,0.16);
}

/* ── Companies page (single theme) — compact detail card ── */
.single-theme .company-info .panel_s .panel-body {
    padding: 18px 22px;
}
.single-theme .company-info .form-group {
    margin-bottom: 0;
    padding: 9px 2px;
    border-bottom: 1px dashed #e7edf5;
}
.single-theme .company-info .form-group:last-child {
    border-bottom: none;
}
.single-theme .company-info .form-group label {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.6px;
    text-transform: uppercase;
    color: #8aa0b8;
    margin-bottom: 0;
    align-self: center;
}
.single-theme .company-info .form-group .form-control-static {
    font-size: 13px;
    color: var(--hp-ink);
    font-weight: 500;
    min-height: 0;
    padding-bottom: 0;
}
.single-theme .company-info .form-group .form-control-static a {
    color: var(--hp-primary);
    font-size: 12.5px;
}
.single-theme .company-info .form-group .form-control-static a:hover {
    color: #0096B7;
}
.single-theme .company-info .form-group .form-control-static em {
    color: #8aa0b8;
    font-size: 11.5px;
    font-style: normal;
}
</style>

<!-- style control for client menu visibility -->
<?php if ((int)get_option('perfex_saas_control_client_menu')) : ?>
<style>
.section-client-dashboard>dl:first-of-type,
.projects-summary-heading,
.submenu.customer-top-submenu {
    display: none;
}
</style>
<?php endif; ?>

<?php $CI = &get_instance(); ?>

<!-- load client widgets -->
<?php require_once(__DIR__ . '/widgets/index.php'); ?>


<?php
$portal_message = $CI->input->get('portal-message', true);
$portal_message_value = $CI->input->get('portal-message-value');
if (!empty($portal_message_value))
    $portal_message_value = urldecode(base64_decode($portal_message_value));
$has_magic_auth = $CI->session->has_userdata('magic_auth');
$portal_origin = $CI->session->userdata('magic_auth')['source_url'] ?? '';

// Sett custom message
if (isset($GLOBALS['has_outstanding']) && $GLOBALS['has_outstanding'] && empty($portal_message)) {

    $portal_message = 'closedBridge';
}

if (!empty($portal_message)) {

    // Remove any magic auth before redirecting away through message
    $CI->session->unset_userdata('magic_auth');
}
?>


<?php if ($has_magic_auth || !empty($portal_message)) : ?>
<style>
#wrapper>#content {
    margin-top: 30px
}
</style>

<script>
const SAAS_IS_MAGIC_AUTH = true;
const SINGLE_PORTAL_PARENT = window?.self !== window?.top ? window.parent : null;
const SINGLE_PORTAL_TARGET_ORIGIN = "<?= $portal_origin ?>";
const SINGLE_PORTAL_MESSAGE = "<?= $portal_message; ?>";
const SINGLE_PORTAL_MESSAGE_VALUE = "<?= $portal_message_value; ?>";
const SAAS_SINGLE_PORTAL_ORIGIN = "<?= parse_url(perfex_saas_default_base_url())['host'] ?? ''; ?>";

const SAAS_SINGLE_PORTAL_TARGET_IS_CUSTOM_DOMAIN = !SINGLE_PORTAL_TARGET_ORIGIN.includes(SAAS_SINGLE_PORTAL_ORIGIN);
const SAAS_CUSTOM_DOMAIN_CAN_USE_SINGLE_PORTAL = "<?= get_option('perfex_saas_enable_cross_domain_bridge'); ?>" == "1";
</script>

<script>
if (SINGLE_PORTAL_PARENT && SINGLE_PORTAL_PARENT.postMessage && SINGLE_PORTAL_MESSAGE.length) {

    SINGLE_PORTAL_PARENT.postMessage({
        message: SINGLE_PORTAL_MESSAGE,
        value: SINGLE_PORTAL_MESSAGE_VALUE
    }, SINGLE_PORTAL_TARGET_ORIGIN);
}

// Handle orphaned client window
if (SAAS_IS_MAGIC_AUTH && !SINGLE_PORTAL_PARENT && !SINGLE_PORTAL_MESSAGE.length && SINGLE_PORTAL_TARGET_ORIGIN) {

    // Get redirect count
    const singlePortalRedStorageKey = "sprc";
    const SAAS_SINGLE_PORTAL_REDIRECTION_COUNT = parseInt(sessionStorage.getItem(singlePortalRedStorageKey) || 1);

    let saasShouldNotRedirectOrphanedPage = (SAAS_SINGLE_PORTAL_TARGET_IS_CUSTOM_DOMAIN && !
            SAAS_CUSTOM_DOMAIN_CAN_USE_SINGLE_PORTAL) ||
        SAAS_SINGLE_PORTAL_REDIRECTION_COUNT > 3;

    if (saasShouldNotRedirectOrphanedPage) {
        // reset the redirect counter
        sessionStorage.setItem(singlePortalRedStorageKey, 0);

    } else {

        // track redirection for limit
        sessionStorage.setItem(singlePortalRedStorageKey, SAAS_SINGLE_PORTAL_REDIRECTION_COUNT + 1);

        // redirect
        window.location.href = SINGLE_PORTAL_TARGET_ORIGIN + '?redirect=' + window.location.pathname + window.location
            .search;
    }
}


if (SINGLE_PORTAL_PARENT) {
    // Function to handle navigation attempts
    function SaaSHandleCrossOriginNavigation(event, url = '') {

        if (!url.length)
            url = event.target.href || event.target.src || window.location.href;

        // Check if the URL is a third-party URL (like Stripe)
        if (!url.includes(SAAS_SINGLE_PORTAL_ORIGIN)) {
            event.preventDefault(); // Prevent the default navigation
            SINGLE_PORTAL_PARENT.postMessage({
                message: 'openInParent',
                value: url
            }, SINGLE_PORTAL_TARGET_ORIGIN); // Send a message to the parent
            return;
        }
    }

    // Attach the event listener for links and forms
    document.addEventListener('click', (event) => {
        if (event.target.tagName === 'A' || event.target.tagName === 'FORM') {
            SaaSHandleCrossOriginNavigation(event);
        }
    });

    // Alternatively, for window.location changes
    window.addEventListener('beforeunload', (event) => {
        SaaSShowLoadingIndicator(); // Show the spinner when leaving the page
        SaaSHandleCrossOriginNavigation(event);
    });
}
</script>
<?php endif; ?>