<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Mailbox
Description: Corporate team webmail — the superadmin connects multiple professional email accounts (SMTP + IMAP) with an interactive setup guide and assigns them to staff. On top of full two-way mail (send, receive, reply, forward, drafts, starring, archive, trash, attachments, live sync) it adds a shared inbox (assignment, open/pending/closed status, internal notes, @mentions, collision detection), automation (incoming-mail rules, out-of-office, first-response SLA with breach alerts), productivity (labels, canned responses, advanced search, keyboard shortcuts, recipient autocomplete, lead/ticket conversion, scheduled & undo send) and governance (audit trail, compliance archive Bcc, outbound DLP screening, legal hold, retention policy and team analytics).
Version: 2.0.0
Requires at least: 2.3.*
*/

define('MAILBOX_MODULE_NAME', 'mailbox');
define('MAILBOX_MODULE_VERSION', '2.0.0');

// Plain-function helpers (crypto, IMAP sync engine, SMTP send engine) are
// needed from admin pages and cron alike, so load them unconditionally.
require_once __DIR__ . '/helpers/mailbox_helper.php';
// Corporate engine — shared inbox, rules, SLA, DLP, audit, retention. Loaded
// straight after so mailbox_ensure_schema() can heal the extended schema and
// the sync engine can call mailbox_after_import().
require_once __DIR__ . '/helpers/mailbox_pro_helper.php';

/**
 * Activation — create the module tables and seed defaults.
 */
register_activation_hook(MAILBOX_MODULE_NAME, 'mailbox_activation_hook');
function mailbox_activation_hook()
{
    require_once __DIR__ . '/install.php';
}

register_uninstall_hook(MAILBOX_MODULE_NAME, 'mailbox_uninstall_hook');
function mailbox_uninstall_hook()
{
    require_once __DIR__ . '/uninstall.php';
}

register_language_files(MAILBOX_MODULE_NAME, [MAILBOX_MODULE_NAME]);

/* ─────────────────────────── Admin UI hooks ─────────────────────────────── */

hooks()->add_action('admin_init', 'mailbox_module_init_menu_items');
hooks()->add_action('admin_init', 'mailbox_module_permissions');
hooks()->add_action('app_admin_head', 'mailbox_add_head_components');
hooks()->add_action('app_admin_footer', 'mailbox_add_footer_components');

/* ────────────────────────── Background sync ─────────────────────────────── */
// Piggy-back the core cron: every tick pulls new IMAP mail for all active
// accounts (internally throttled per account).
hooks()->add_action('after_cron_run', 'mailbox_run_cron_sync');
// Governance tick — scheduled sends, SLA breach flags, retention purge. Kept
// separate from the IMAP pull so an unreachable mail server never stops it.
hooks()->add_action('after_cron_run', 'mailbox_run_cron_maintenance');

/**
 * Staff capabilities. Account management (add/edit/delete/assign) is
 * superadmin-only by design; the "view" capability merely lets a staff
 * member open the webmail — they still only ever see accounts assigned
 * to them.
 */
function mailbox_module_permissions()
{
    $capabilities = [];
    $capabilities['capabilities'] = [
        'view' => _l('permission_view') . '(' . _l('permission_global') . ')',
        // Shared assets: labels, canned responses, automation rules.
        'manage' => _l('mailbox_permission_manage'),
        // Governance screens: team analytics + the audit trail.
        'analytics' => _l('mailbox_permission_analytics'),
    ];
    register_staff_capabilities(MAILBOX_MODULE_NAME, $capabilities, _l('mailbox'));
}

/**
 * Sidebar menu — visible to the superadmin and to staff with at least one
 * assigned account.
 */
function mailbox_module_init_menu_items()
{
    if (!mailbox_staff_can_access()) {
        return;
    }

    $CI = &get_instance();
    $CI->app_menu->add_sidebar_menu_item('mailbox-module', [
        'name'     => _l('mailbox'),
        'icon'     => 'fa-solid fa-envelope-open-text',
        'href'     => admin_url('mailbox'),
        'position' => 26,
    ]);
}

/**
 * Detect whether the current request targets this module's admin controller.
 * Prefix-agnostic — the master admin panel can be served under a custom
 * security prefix (same approach as pro_tickets / pro_analytics).
 */
function mailbox_is_module_page()
{
    $CI = &get_instance();

    if (isset($CI->router) && method_exists($CI->router, 'fetch_class')
        && $CI->router->fetch_class() === MAILBOX_MODULE_NAME) {
        return true;
    }

    return (bool) preg_match('#(^|/)admin/' . MAILBOX_MODULE_NAME . '(/|$)#', $CI->uri->uri_string());
}

/**
 * CSS in <head> on module pages.
 */
function mailbox_add_head_components()
{
    if (mailbox_is_module_page()) {
        $css = __DIR__ . '/assets/css/mailbox.css';
        $v   = 'v1.' . (file_exists($css) ? filemtime($css) : time());
        echo '<link href="' . module_dir_url(MAILBOX_MODULE_NAME, 'assets/css/mailbox.css') . '?' . $v . '" rel="stylesheet" type="text/css" />';
    }
}

/**
 * JS in footer on module pages.
 */
function mailbox_add_footer_components()
{
    if (!mailbox_is_module_page()) {
        return;
    }

    foreach (['mailbox.js', 'mailbox_pro.js'] as $file) {
        $path = __DIR__ . '/assets/js/' . $file;
        $v    = 'v1.' . (file_exists($path) ? filemtime($path) : time());
        echo '<script src="' . module_dir_url(MAILBOX_MODULE_NAME, 'assets/js/' . $file) . '?' . $v . '"></script>';
    }
}

/* ─────────────────────── Admin dashboard widget ─────────────────────────── */

hooks()->add_action('after_dashboard', 'mailbox_dashboard_widget', 7);

/**
 * Team-mail widget on the main admin dashboard: unread counters per assigned
 * account, 7-day received-vs-sent volume, latest unread messages and sync
 * failure alerts. Scoped to the accounts assigned to the logged-in staff
 * member (superadmins see every account).
 */
function mailbox_dashboard_widget()
{
    if (!mailbox_staff_can_access()) {
        return;
    }

    $CI = &get_instance();

    // Never let a widget failure blank the whole admin dashboard.
    try {
        $accounts = mailbox_accounts_for_staff(get_staff_user_id());
        if (empty($accounts)) {
            return;
        }

        $p   = db_prefix();
        $ids = implode(',', array_map(function ($a) {
            return (int) $a->id;
        }, $accounts));

        $totals = $CI->db->query(
            "SELECT
                SUM(folder = 'inbox' AND is_read = 0) AS unread,
                SUM(folder = 'inbox' AND DATE(message_date) = CURDATE()) AS received_today,
                SUM(folder = 'sent' AND DATE(message_date) = CURDATE()) AS sent_today,
                SUM(is_starred = 1 AND folder NOT IN ('trash')) AS starred
             FROM `{$p}mailbox_messages`
             WHERE account_id IN ({$ids})"
        )->row();

        $unread_by_account = [];
        foreach ($CI->db->query(
            "SELECT account_id, COUNT(*) AS cnt FROM `{$p}mailbox_messages`
             WHERE account_id IN ({$ids}) AND folder = 'inbox' AND is_read = 0
             GROUP BY account_id"
        )->result() as $r) {
            $unread_by_account[(int) $r->account_id] = (int) $r->cnt;
        }

        // 7-day received vs sent volume.
        $vol_map = [];
        foreach ($CI->db->query(
            "SELECT DATE(message_date) AS d,
                    SUM(folder = 'inbox') AS received,
                    SUM(folder = 'sent') AS sent
             FROM `{$p}mailbox_messages`
             WHERE account_id IN ({$ids})
               AND message_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
             GROUP BY DATE(message_date)"
        )->result() as $r) {
            $vol_map[$r->d] = ['received' => (int) $r->received, 'sent' => (int) $r->sent];
        }
        $vol_labels = $vol_received = $vol_sent = [];
        for ($i = 6; $i >= 0; $i--) {
            $d            = date('Y-m-d', strtotime("-{$i} days"));
            $vol_labels[] = $i === 0 ? 'Today' : date('D', strtotime($d));
            $vol_received[] = $vol_map[$d]['received'] ?? 0;
            $vol_sent[]     = $vol_map[$d]['sent'] ?? 0;
        }

        // Latest unread messages across the visible accounts.
        $unread_messages = $CI->db->query(
            "SELECT m.id, m.account_id, m.subject, m.from_name, m.from_email, m.message_date, a.name AS account_name
             FROM `{$p}mailbox_messages` m
             JOIN `{$p}mailbox_accounts` a ON a.id = m.account_id
             WHERE m.account_id IN ({$ids}) AND m.folder = 'inbox' AND m.is_read = 0
             ORDER BY m.message_date DESC
             LIMIT 6"
        )->result();

        $sync_errors = array_values(array_filter($accounts, function ($a) {
            return trim((string) ($a->last_sync_error ?? '')) !== '';
        }));

        $CI->load->view('mailbox/dashboard_widget', [
            'accounts'          => $accounts,
            'totals'            => $totals,
            'unread_by_account' => $unread_by_account,
            'vol_labels'        => $vol_labels,
            'vol_received'      => $vol_received,
            'vol_sent'          => $vol_sent,
            'unread_messages'   => $unread_messages,
            'sync_errors'       => $sync_errors,
        ]);
    } catch (Throwable $e) {
        log_activity('Mailbox dashboard widget failed to render [' . $e->getMessage() . ']');
    }
}
