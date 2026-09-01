<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Lead Sync — shared lookups, schema and small utilities.
 *
 * Required from the module bootstrap (not loaded per controller) because the
 * admin screens, the public webhook and the cron all need the same helpers,
 * and the schema self-heal runs on admin_init.
 */

/* ═══════════════════════════════ Access ═══════════════════════════════ */

function lead_sync_can($capability = 'view')
{
    return is_admin() || has_permission('lead_sync', '', $capability);
}

function lead_sync_can_access()
{
    return is_admin() || has_permission('lead_sync', '', 'view');
}

/* ═══════════════════════════════ Options ══════════════════════════════ */

function lead_sync_default_options()
{
    return [
        'lead_sync_enabled'             => '1',
        'lead_sync_log_retention_days'  => '60',
        'lead_sync_last_cron'           => '',
        // Rows are read in blocks so a first sync of a 20k-row sheet cannot
        // time the cron out; the next run picks up where this one stopped.
        'lead_sync_max_rows_per_run'    => '500',
        'lead_sync_http_timeout'        => '30',
    ];
}

function lead_sync_opt($name)
{
    $value = get_option($name);

    if ($value === '' || $value === false || $value === null) {
        $defaults = lead_sync_default_options();

        return $defaults[$name] ?? '';
    }

    return $value;
}

/* ═══════════════════════════ Credential storage ═══════════════════════ */

/**
 * API keys and service-account private keys are the one genuinely secret thing
 * this module holds, so they never sit in the table as plain text. CI's
 * Encryption library keys off the app's encryption_key, the same mechanism
 * core uses for SMTP and gateway credentials.
 */
function lead_sync_encrypt($value)
{
    $value = (string) $value;
    if ($value === '') {
        return '';
    }

    $CI = &get_instance();
    $CI->load->library('encryption');

    return 'enc:' . $CI->encryption->encrypt($value);
}

function lead_sync_decrypt($value)
{
    $value = (string) $value;
    if (strncmp($value, 'enc:', 4) !== 0) {
        return $value; // written before encryption, or empty
    }

    $CI = &get_instance();
    $CI->load->library('encryption');

    return (string) $CI->encryption->decrypt(substr($value, 4));
}

/* ═══════════════════════════ Phone identity ═══════════════════════════ */

/**
 * Digits-only comparison key. Meta writes numbers as "p:+91 98765 43210",
 * a manager types "098765 43210" and the CRM may hold "+919876543210" —
 * all three are the same person, so the last 10 digits are what we match on
 * (kept longer for numbers that are genuinely shorter).
 */
function lead_sync_phone_norm($value)
{
    $digits = preg_replace('/\D+/', '', (string) $value);

    if ($digits === '') {
        return '';
    }

    return strlen($digits) > 10 ? substr($digits, -10) : $digits;
}

function lead_sync_phone_valid($value)
{
    return strlen(lead_sync_phone_norm($value)) >= 7;
}

/* ═══════════════════════════ Webhook plumbing ═════════════════════════ */

function lead_sync_webhook_url($token)
{
    // Routed by the module's own config/routes.php and exempted from CSRF by its
    // config/csrf_exclude_uris.php — no entry in any core config file.
    return site_url('lead_sync/push/' . $token);
}

function lead_sync_new_token()
{
    return bin2hex(random_bytes(20));
}

/**
 * The Apps Script a tenant pastes into their sheet for instant delivery.
 * Kept here (not in the view) so the settings screen and the connection screen
 * cannot drift apart.
 */
function lead_sync_apps_script($token, $tab_name = '')
{
    $url = lead_sync_webhook_url($token);
    $tab = $tab_name !== '' ? $tab_name : 'Sheet1';

    return <<<JS
/**
 * Lead Sync — push new rows to the CRM the moment they land.
 *
 * 1. In your Google Sheet: Extensions → Apps Script, paste this, Save.
 * 2. Run installTrigger() once and approve the permission prompt.
 * Nothing else to do — new rows reach the CRM within seconds.
 */
const LEAD_SYNC_URL = '{$url}';
const LEAD_SYNC_TAB = '{$tab}';

function installTrigger() {
  ScriptApp.getProjectTriggers().forEach(t => ScriptApp.deleteTrigger(t));
  ScriptApp.newTrigger('leadSyncPush')
    .forSpreadsheet(SpreadsheetApp.getActive())
    .onChange()
    .create();
  leadSyncPush();
}

function leadSyncPush() {
  const sheet = SpreadsheetApp.getActive().getSheetByName(LEAD_SYNC_TAB)
    || SpreadsheetApp.getActive().getSheets()[0];
  const values = sheet.getDataRange().getDisplayValues();
  if (values.length < 2) return;

  const props = PropertiesService.getScriptProperties();
  const sent  = Number(props.getProperty('leadSyncSentRows') || 1); // row 1 = headers
  if (values.length <= sent) return;

  const payload = { headers: values[0], rows: values.slice(sent) };
  const res = UrlFetchApp.fetch(LEAD_SYNC_URL, {
    method: 'post',
    contentType: 'application/json',
    payload: JSON.stringify(payload),
    muteHttpExceptions: true
  });
  if (res.getResponseCode() === 200) {
    props.setProperty('leadSyncSentRows', String(values.length));
  } else {
    console.error('Lead Sync push failed: ' + res.getContentText());
  }
}
JS;
}

/* ═══════════════════════════ CRM lookups ══════════════════════════════ */

/**
 * Per-request cache for the small lookup tables below. Kept in one place (not
 * in function statics) so importing a sheet that invents a new lead source can
 * drop the stale copy mid-run.
 */
function &lead_sync_cache()
{
    static $cache = [];

    return $cache;
}

function lead_sync_sources_refresh()
{
    $cache = &lead_sync_cache();
    unset($cache['sources']);
}

/** Lead statuses, id => name. */
function lead_sync_statuses()
{
    $cache = &lead_sync_cache();

    if (!isset($cache['statuses'])) {
        $cache['statuses'] = [];
        foreach (get_instance()->db->order_by('statusorder', 'asc')->get(db_prefix() . 'leads_status')->result() as $row) {
            $cache['statuses'][(int) $row->id] = $row->name;
        }
    }

    return $cache['statuses'];
}

/** Lead sources, id => name. */
function lead_sync_sources()
{
    $cache = &lead_sync_cache();

    if (!isset($cache['sources'])) {
        $cache['sources'] = [];
        foreach (get_instance()->db->order_by('name', 'asc')->get(db_prefix() . 'leads_sources')->result() as $row) {
            $cache['sources'][(int) $row->id] = $row->name;
        }
    }

    return $cache['sources'];
}

/** Active staff, id => full name — the assignment pool. */
function lead_sync_staff()
{
    $cache = &lead_sync_cache();

    if (!isset($cache['staff'])) {
        $cache['staff'] = [];
        $rows = get_instance()->db->select('staffid, firstname, lastname')
            ->where('active', 1)->order_by('firstname', 'asc')
            ->get(db_prefix() . 'staff')->result();
        foreach ($rows as $row) {
            $cache['staff'][(int) $row->staffid] = trim($row->firstname . ' ' . $row->lastname);
        }
    }

    return $cache['staff'];
}

/** Lead custom fields, id => label — offered as mapping targets. */
function lead_sync_custom_fields()
{
    $cache = &lead_sync_cache();

    if (!isset($cache['custom_fields'])) {
        $cache['custom_fields'] = [];
        $rows = get_instance()->db->where('fieldto', 'leads')->where('active', 1)
            ->order_by('field_order', 'asc')->get(db_prefix() . 'customfields')->result();
        foreach ($rows as $row) {
            $cache['custom_fields'][(int) $row->id] = $row->name;
        }
    }

    return $cache['custom_fields'];
}

/**
 * Sheet wording for a lead source → the name a human would recognise.
 *
 * Meta's own export writes its `platform` column as "ig" and "fb", so without
 * this a campaign quietly creates lead sources called "Ig" and "Fb" alongside
 * the "Instagram" and "Facebook" the CRM already has, and the source report
 * splits in two.
 */
function lead_sync_normalize_source($name)
{
    $name = trim((string) $name);
    if ($name === '') {
        return '';
    }

    $key = preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower($name));
    $key = trim(preg_replace('/\s+/', ' ', (string) $key));

    $known = [
        'ig'                 => 'Instagram',
        'instagram'          => 'Instagram',
        'ig profile'         => 'Instagram',
        'instagram lead ad'  => 'Instagram',
        'fb'                 => 'Facebook',
        'facebook'           => 'Facebook',
        'facebook lead ad'   => 'Facebook',
        'facebook lead ads'  => 'Facebook',
        'meta'               => 'Facebook',
        'msg'                => 'Messenger',
        'messenger'          => 'Messenger',
        'an'                 => 'Audience Network',
        'audience network'   => 'Audience Network',
        'wa'                 => 'WhatsApp',
        'whatsapp'           => 'WhatsApp',
        'google'             => 'Google',
        'google ads'         => 'Google',
        'adwords'            => 'Google',
        'tiktok'             => 'TikTok',
        'linkedin'           => 'LinkedIn',
        'youtube'            => 'YouTube',
        'website'            => 'Website',
        'web'                => 'Website',
        'organic'            => 'Organic',
        'referral'           => 'Referral',
        'walkin'             => 'Walk-in',
        'walk in'            => 'Walk-in',
    ];

    return $known[$key] ?? $name;
}

/** Human label for a stored run status. */
function lead_sync_status_label($status)
{
    $map = [
        'ok'      => 'Completed',
        'partial' => 'Completed with errors',
        'error'   => 'Failed',
        'running' => 'Running',
        'idle'    => 'Never run',
    ];

    return $map[$status] ?? ucfirst((string) $status);
}

function lead_sync_time_ago($datetime)
{
    $datetime = (string) $datetime;
    if ($datetime === '' || $datetime === '0000-00-00 00:00:00') {
        return 'never';
    }

    $seconds = time() - strtotime($datetime);
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

/* ═══════════════════════════════ Schema ═══════════════════════════════ */

/**
 * Create-if-missing schema. Safe to run on every activation and on the
 * version-bump self-heal; nothing here drops or rewrites existing data.
 */
function lead_sync_ensure_schema()
{
    $CI = &get_instance();
    $p  = db_prefix();
    $cs = $CI->db->char_set ?: 'utf8mb4';

    $CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}lead_sync_connections` (
        `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(150) NOT NULL,
        `active` TINYINT(1) NOT NULL DEFAULT 1,
        `auth_mode` VARCHAR(20) NOT NULL DEFAULT 'public',
        `sheet_url` TEXT DEFAULT NULL,
        `spreadsheet_id` VARCHAR(120) NOT NULL DEFAULT '',
        `gid` VARCHAR(40) NOT NULL DEFAULT '',
        `tab_name` VARCHAR(150) NOT NULL DEFAULT '',
        `credentials` MEDIUMTEXT DEFAULT NULL,
        `has_header` TINYINT(1) NOT NULL DEFAULT 1,
        `column_map` MEDIUMTEXT DEFAULT NULL,
        `default_status` INT(11) NOT NULL DEFAULT 0,
        `default_source` INT(11) NOT NULL DEFAULT 0,
        `assign_mode` VARCHAR(20) NOT NULL DEFAULT 'unassigned',
        `assign_to` INT(11) NOT NULL DEFAULT 0,
        `assign_pool` TEXT DEFAULT NULL,
        `rr_index` INT(11) NOT NULL DEFAULT 0,
        `tags` VARCHAR(255) NOT NULL DEFAULT '',
        `dedupe_by` VARCHAR(20) NOT NULL DEFAULT 'phone',
        `skip_before` DATE DEFAULT NULL,
        `interval_minutes` INT(11) NOT NULL DEFAULT 15,
        `webhook_token` VARCHAR(64) NOT NULL DEFAULT '',
        `last_run_at` DATETIME DEFAULT NULL,
        `last_status` VARCHAR(20) NOT NULL DEFAULT 'idle',
        `last_message` VARCHAR(500) NOT NULL DEFAULT '',
        `total_imported` INT(11) NOT NULL DEFAULT 0,
        `created_at` DATETIME DEFAULT NULL,
        `updated_at` DATETIME DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `webhook_token` (`webhook_token`),
        KEY `active` (`active`)
    ) ENGINE=InnoDB DEFAULT CHARSET={$cs};");

    $CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}lead_sync_runs` (
        `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `connection_id` INT(11) UNSIGNED NOT NULL,
        `trigger_type` VARCHAR(20) NOT NULL DEFAULT 'cron',
        `started_at` DATETIME NOT NULL,
        `finished_at` DATETIME DEFAULT NULL,
        `rows_read` INT(11) NOT NULL DEFAULT 0,
        `created` INT(11) NOT NULL DEFAULT 0,
        `duplicates` INT(11) NOT NULL DEFAULT 0,
        `skipped` INT(11) NOT NULL DEFAULT 0,
        `failed` INT(11) NOT NULL DEFAULT 0,
        `status` VARCHAR(20) NOT NULL DEFAULT 'running',
        `message` TEXT DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `connection_id` (`connection_id`),
        KEY `started_at` (`started_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET={$cs};");

    // One row per sheet line we have already handled. This is what makes a
    // re-run — cron, manual or a webhook replay — idempotent no matter how the
    // sheet is sorted or how many blank rows appear in the middle of it.
    $CI->db->query("CREATE TABLE IF NOT EXISTS `{$p}lead_sync_rows` (
        `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `connection_id` INT(11) UNSIGNED NOT NULL,
        `row_hash` CHAR(40) NOT NULL,
        `lead_id` INT(11) NOT NULL DEFAULT 0,
        `outcome` VARCHAR(20) NOT NULL DEFAULT 'created',
        `imported_at` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `connection_row` (`connection_id`, `row_hash`),
        KEY `lead_id` (`lead_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET={$cs};");

    // table_exists() caches the table list on its first call and never refreshes
    // it, so anything created above would stay invisible for the rest of this
    // request (install.php seeds against it).
    unset($CI->db->data_cache['table_names']);
}
