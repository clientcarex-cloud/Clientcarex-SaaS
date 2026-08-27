<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Pro Tickets — uninstall.
 * Drops only the module's own extension tables and options. Core ticket data
 * (tbltickets and friends) is left completely untouched.
 */

$CI = &get_instance();
$p  = db_prefix();

foreach (['pro_tickets_meta', 'pro_tickets_sla', 'pro_tickets_activity', 'pro_tickets_watchers', 'pro_tickets_notes'] as $table) {
    $CI->db->query('DROP TABLE IF EXISTS `' . $p . $table . '`;');
}

foreach ([
    'pro_tickets_auto_assign',
    'pro_tickets_auto_close_enabled',
    'pro_tickets_auto_close_days',
    'pro_tickets_sla_warning_pct',
    'pro_tickets_bump_priority_on_breach',
    'pro_tickets_notify_watchers',
    'pro_tickets_smart_admin_enabled',
    'pro_tickets_cron_last_run',
] as $option) {
    delete_option($option);
}
