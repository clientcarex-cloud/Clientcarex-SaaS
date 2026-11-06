<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Tenant-side "Support" unread badge + real-time notifications.
 *
 * When the SaaS provider's staff replies to a tenant's support ticket in the
 * master Pro Tickets helpdesk, the tenant super admin should see:
 *   - a live count badge on the profile menu button and the Support menu item,
 *   - a real-time notification when a new reply arrives.
 *
 * Support tickets live in the MASTER database (core tbltickets). A staff reply
 * flips the ticket's `clientread` flag to 0; opening the ticket sets it back to
 * 1. So the unread count = tickets for this tenant's master client with
 * clientread = 0. We read that cross-DB with the SaaS raw-query helpers.
 */

/**
 * Count unread support replies for the current tenant (from the master DB).
 * Cached per-request (static) and briefly in the session to avoid a cross-DB
 * hit on every admin page load.
 *
 * @param  bool $force Bypass the session cache (used by the polling endpoint).
 * @return int
 */
function perfex_saas_support_unread_count($force = false)
{
    static $cached = null;

    if ($cached !== null && !$force) {
        return $cached;
    }

    if (!function_exists('perfex_saas_is_tenant') || !perfex_saas_is_tenant() || !is_admin()) {
        return $cached = 0;
    }

    $CI  = &get_instance();
    $ttl = 15; // seconds

    if (!$force) {
        $cache = $CI->session->userdata('saas_support_unread_cache');
        if (is_array($cache) && isset($cache['t'], $cache['v']) && (time() - $cache['t']) < $ttl) {
            return $cached = (int) $cache['v'];
        }
    }

    $count = 0;
    try {
        $tenant   = perfex_saas_tenant();
        $clientid = (int) ($tenant->clientid ?? 0);

        if ($clientid) {
            $prefix = perfex_saas_master_db_prefix();

            // Count unread STAFF REPLIES (not just unread conversations), so
            // multiple replies on the same ticket all add to the badge. A reply
            // is "unread" when it is newer than the client's last reply on that
            // ticket (or the ticket creation, if the client hasn't replied) and
            // the ticket is still flagged unread for the client (clientread=0).
            $sql = "SELECT COUNT(*) AS c
                      FROM {$prefix}ticket_replies r
                      JOIN {$prefix}tickets t ON t.ticketid = r.ticketid
                     WHERE t.userid = ? AND t.clientread = 0 AND r.admin IS NOT NULL
                       AND r.date > COALESCE(
                             (SELECT MAX(r2.date) FROM {$prefix}ticket_replies r2 WHERE r2.ticketid = t.ticketid AND r2.admin IS NULL),
                             t.date
                           )";
            $row   = perfex_saas_raw_query_row($sql, [], true, false, [$clientid]);
            $count = $row ? (int) $row->c : 0;
        }
    } catch (\Throwable $e) {
        log_message('error', 'SaaS support unread count failed: ' . $e->getMessage());
        $count = 0;
    }

    // The polling endpoint releases the session lock before it gets here, so
    // there is nothing left to write to — the per-request static cache above
    // still applies.
    if (!function_exists('ccx_session_is_writable') || ccx_session_is_writable()) {
        $CI->session->set_userdata('saas_support_unread_cache', ['t' => time(), 'v' => $count]);
    }

    return $cached = $count;
}

/**
 * Return the tenant's unread support tickets (with a short reply snippet) for
 * the real-time toast notifications. Read cross-DB from the master helpdesk.
 *
 * @param  int $limit
 * @return array<int,array{id:int,subject:string,snippet:string,stamp:string,time:string}>
 */
function perfex_saas_support_unread_items($limit = 6)
{
    if (!function_exists('perfex_saas_is_tenant') || !perfex_saas_is_tenant() || !is_admin()) {
        return [];
    }

    $items = [];
    try {
        $tenant   = perfex_saas_tenant();
        $clientid = (int) ($tenant->clientid ?? 0);
        if (!$clientid) {
            return [];
        }

        $p     = perfex_saas_master_db_prefix();
        $limit = max(1, (int) $limit);

        // Newest unread first; snippet = latest reply, falling back to the body.
        $sql = "SELECT t.ticketid AS id, t.subject AS subject, t.lastreply AS lastreply, t.date AS date,
                    COALESCE((SELECT r.message FROM {$p}ticket_replies r WHERE r.ticketid = t.ticketid ORDER BY r.id DESC LIMIT 1), t.message) AS last_message
                FROM {$p}tickets t
                WHERE t.userid = ? AND t.clientread = 0
                ORDER BY (t.lastreply IS NULL) ASC, t.lastreply DESC, t.ticketid DESC
                LIMIT {$limit}";

        $rows = perfex_saas_raw_query($sql, [], true, false, null, false, true, [$clientid]);

        if (is_array($rows)) {
            foreach ($rows as $r) {
                $msg = html_entity_decode(strip_tags((string) $r->last_message), ENT_QUOTES, 'UTF-8');
                // Drop any invalid UTF-8 so json_encode() never fails downstream.
                $msg = @mb_convert_encoding($msg, 'UTF-8', 'UTF-8');
                $msg = trim(preg_replace('/\s+/', ' ', $msg));
                if (function_exists('mb_strlen') && mb_strlen($msg) > 90) {
                    $msg = mb_substr($msg, 0, 90) . '…';
                }
                $subject = @mb_convert_encoding((string) $r->subject, 'UTF-8', 'UTF-8');

                $stamp = $r->lastreply ?: $r->date;
                $items[] = [
                    'id'      => (int) $r->id,
                    'subject' => $subject,
                    'snippet' => $msg,
                    'stamp'   => (string) $stamp,
                    'time'    => ($stamp && function_exists('_dt')) ? _dt($stamp) : (string) $stamp,
                ];
            }
        }
    } catch (\Throwable $e) {
        log_message('error', 'SaaS support unread items failed: ' . $e->getMessage());
    }

    return $items;
}

/**
 * Support tickets of this tenant that were CLOSED BY PROVIDER STAFF and have
 * no satisfaction rating yet (read cross-DB from the master Pro Tickets
 * helpdesk). Powers the real-time "your ticket was closed — how did we do?"
 * notification on every tenant admin page; clicking it lands on the client
 * portal single-ticket view, which auto-opens the star-rating modal.
 *
 * closed_by_staff semantics (pro_tickets_meta): NULL = not closed (or closed
 * before the feedback feature existed), 0 = the customer closed it themselves,
 * >0 = staff id — only the latter should notify.
 *
 * @param  int $limit
 * @return array<int,array{id:int,subject:string,stamp:string,time:string}>
 */
function perfex_saas_support_feedback_pending($limit = 3)
{
    if (!function_exists('perfex_saas_is_tenant') || !perfex_saas_is_tenant() || !is_admin()) {
        return [];
    }

    $items = [];
    try {
        $tenant   = perfex_saas_tenant();
        $clientid = (int) ($tenant->clientid ?? 0);
        if (!$clientid) {
            return [];
        }

        $p     = perfex_saas_master_db_prefix();
        $limit = max(1, (int) $limit);

        $sql = "SELECT t.ticketid AS id, t.subject AS subject, m.resolved_at AS resolved_at
                FROM {$p}tickets t
                JOIN {$p}pro_tickets_meta m ON m.ticket_id = t.ticketid
                LEFT JOIN {$p}pro_tickets_feedback f ON f.ticket_id = t.ticketid
                WHERE t.userid = ? AND t.status = 5 AND t.merged_ticket_id IS NULL
                  AND f.id IS NULL AND m.closed_by_staff IS NOT NULL AND m.closed_by_staff > 0
                ORDER BY m.resolved_at DESC
                LIMIT {$limit}";

        $rows = perfex_saas_raw_query($sql, [], true, false, null, false, true, [$clientid]);

        if (is_array($rows)) {
            foreach ($rows as $r) {
                $subject = @mb_convert_encoding((string) $r->subject, 'UTF-8', 'UTF-8');
                $stamp   = (string) $r->resolved_at;
                $items[] = [
                    'id'      => (int) $r->id,
                    'subject' => $subject,
                    'stamp'   => $stamp,
                    'time'    => ($stamp && function_exists('_dt')) ? _dt($stamp) : $stamp,
                ];
            }
        }
    } catch (\Throwable $e) {
        log_message('error', 'SaaS support feedback pending failed: ' . $e->getMessage());
    }

    return $items;
}

if (perfex_saas_is_tenant()) {

    // Inject the unread count as a badge on the "Support" account menu item so
    // it renders server-side on first paint (JS keeps it live afterwards).
    hooks()->add_filter('perfex_saas_tenant_account_menu', function ($items) {
        if (!is_array($items)) {
            return $items;
        }

        $count = perfex_saas_support_unread_count();
        if ($count <= 0) {
            return $items;
        }

        foreach ($items as &$item) {
            if (($item['slug'] ?? '') === 'saas_client_tickets') {
                $item['badge'] = [
                    'value' => $count > 99 ? '99+' : $count,
                    'type'  => 'danger',
                ];
            }
        }

        return $items;
    });

    // Inject the polling + notification script into the tenant admin footer.
    hooks()->add_action('app_admin_footer', function () {
        if (!is_admin()) {
            return;
        }

        $initial          = perfex_saas_support_unread_count();
        $initial_items    = perfex_saas_support_unread_items(6);
        $initial_feedback = perfex_saas_support_feedback_pending(3);
        $endpoint         = admin_url('billing/support_unread');
        $myaccount        = admin_url('billing/my_account');

        include __DIR__ . '/../views/tenant_support_badge_script.php';
    });
}
