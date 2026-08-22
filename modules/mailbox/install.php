<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Mailbox — schema + defaults.
 *
 * Everything lives in mailbox_* tables; no core table is touched.
 * The schema function is self-healing and safe to re-run.
 */

mailbox_ensure_schema();

// ── Sync options ──
add_option('mailbox_sync_interval', '120');   // min seconds between cron syncs per account
add_option('mailbox_initial_days', '7');      // first sync pulls mail from the last N days
add_option('mailbox_sync_batch', '50');       // max messages imported per account per tick
add_option('mailbox_cron_last_run', '0');
add_option('mailbox_retention_last_run', '');

// ── Corporate options (shared inbox, SLA, compliance, automation) ──
// add_option() is called unconditionally: core get_option() returns '' for a
// missing option, so a `=== false` guard would never fire.
foreach (mailbox_pro_option_defaults() as $key => $value) {
    add_option($key, $value);
}
