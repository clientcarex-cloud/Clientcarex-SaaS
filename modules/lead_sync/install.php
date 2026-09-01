<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Lead Sync — schema and options.
 *
 * Re-running this file is safe and expected: it is the activation hook, and it
 * runs again by itself whenever the module version moves on (the self-heal in
 * lead_sync.php), which is how a SaaS tenant picks up a new column without
 * anyone touching their database.
 */

lead_sync_ensure_schema();

foreach (lead_sync_default_options() as $lead_sync_option => $lead_sync_value) {
    add_option($lead_sync_option, $lead_sync_value);
}

// Columns added after the first release. Each one is guarded, so an install
// that already has them is untouched.
$CI = &get_instance();
$p  = db_prefix();

foreach ([
    'lead_sync_connections' => [
        'rr_index'    => "ALTER TABLE `{$p}lead_sync_connections` ADD COLUMN `rr_index` INT(11) NOT NULL DEFAULT 0",
        'skip_before' => "ALTER TABLE `{$p}lead_sync_connections` ADD COLUMN `skip_before` DATE DEFAULT NULL",
        'tab_name'    => "ALTER TABLE `{$p}lead_sync_connections` ADD COLUMN `tab_name` VARCHAR(150) NOT NULL DEFAULT ''",
    ],
    'lead_sync_rows' => [
        'outcome' => "ALTER TABLE `{$p}lead_sync_rows` ADD COLUMN `outcome` VARCHAR(20) NOT NULL DEFAULT 'created'",
    ],
] as $lead_sync_table => $lead_sync_columns) {
    if (!$CI->db->table_exists($p . $lead_sync_table)) {
        continue;
    }
    $lead_sync_existing = $CI->db->list_fields($p . $lead_sync_table);
    foreach ($lead_sync_columns as $lead_sync_column => $lead_sync_sql) {
        if (!in_array($lead_sync_column, $lead_sync_existing, true)) {
            $CI->db->query($lead_sync_sql);
        }
    }
}

// A connection minted before webhook pushes existed has no token; give it one
// so the "instant delivery" panel works without the manager re-saving the form.
if ($CI->db->table_exists($p . 'lead_sync_connections')) {
    foreach ($CI->db->select('id')->where('webhook_token', '')->get($p . 'lead_sync_connections')->result() as $lead_sync_row) {
        $CI->db->where('id', $lead_sync_row->id)
            ->update($p . 'lead_sync_connections', ['webhook_token' => lead_sync_new_token()]);
    }
}
