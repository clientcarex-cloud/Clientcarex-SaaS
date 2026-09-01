<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Lead Sync — connections, the import pipeline and its history.
 *
 * The pipeline is deliberately one method (import_rows) shared by all three
 * triggers — cron, the "Sync now" button and the webhook — so a lead created
 * from a push is identical in every way to one created from a poll.
 *
 * Leads are written straight into `tblleads` and the core `lead_created` hook
 * is fired afterwards. That is what makes the module portable: anything that
 * already listens for a new CRM lead (the SHRA leads desk, notifications, an
 * automation module) picks these up with no knowledge of this module at all.
 */
class Lead_sync_model extends App_Model
{
    public function __construct()
    {
        parent::__construct();

        // Both are static, CRM-free classes rather than CI libraries, so they are
        // required rather than instantiated — the same shape the other modules
        // here use for their import helpers.
        require_once module_dir_path(LEAD_SYNC_MODULE_NAME, 'libraries/Lead_sync_sheet.php');
        require_once module_dir_path(LEAD_SYNC_MODULE_NAME, 'libraries/Lead_sync_google.php');
    }

    /* ═══════════════════════════ Connections ═══════════════════════════ */

    public function connections($only_active = false)
    {
        $this->db->order_by('active', 'desc')->order_by('name', 'asc');
        if ($only_active) {
            $this->db->where('active', 1);
        }

        return $this->db->get(db_prefix() . 'lead_sync_connections')->result();
    }

    public function connection($id)
    {
        return $this->db->where('id', (int) $id)->get(db_prefix() . 'lead_sync_connections')->row();
    }

    public function connection_by_token($token)
    {
        $token = (string) $token;
        if (!preg_match('/^[a-f0-9]{40}$/', $token)) {
            return null;
        }

        return $this->db->where('webhook_token', $token)->get(db_prefix() . 'lead_sync_connections')->row();
    }

    /**
     * Create or update a connection from the form.
     *
     * @return int|string new/updated id, or an error message
     */
    public function save_connection(array $in, $id = 0)
    {
        $id   = (int) $id;
        $name = trim((string) ($in['name'] ?? ''));

        if ($name === '') {
            return 'Give this connection a name.';
        }

        $parts = Lead_sync_google::parse_sheet_url($in['sheet_url'] ?? '');
        if ($parts['id'] === '') {
            return 'That does not look like a Google Sheet link. Paste the address bar URL of the sheet.';
        }

        $mode = in_array($in['auth_mode'] ?? '', ['public', 'api_key', 'service_account'], true)
            ? $in['auth_mode'] : 'public';

        $data = [
            'name'             => substr($name, 0, 150),
            'active'           => !empty($in['active']) ? 1 : 0,
            'auth_mode'        => $mode,
            'sheet_url'        => trim((string) ($in['sheet_url'] ?? '')),
            'spreadsheet_id'   => $parts['id'],
            'gid'              => trim((string) ($in['gid'] ?? '')) !== '' ? trim((string) $in['gid']) : $parts['gid'],
            'tab_name'         => substr(trim((string) ($in['tab_name'] ?? '')), 0, 150),
            'has_header'       => isset($in['has_header']) ? (int) (bool) $in['has_header'] : 1,
            'default_status'   => (int) ($in['default_status'] ?? 0),
            'default_source'   => (int) ($in['default_source'] ?? 0),
            'assign_mode'      => in_array($in['assign_mode'] ?? '', ['unassigned', 'fixed', 'round_robin', 'column'], true) ? $in['assign_mode'] : 'unassigned',
            'assign_to'        => (int) ($in['assign_to'] ?? 0),
            'tags'             => substr(trim((string) ($in['tags'] ?? '')), 0, 255),
            'dedupe_by'        => in_array($in['dedupe_by'] ?? '', ['phone', 'email', 'phone_email', 'none'], true) ? $in['dedupe_by'] : 'phone',
            'skip_before'      => trim((string) ($in['skip_before'] ?? '')) !== '' ? date('Y-m-d', strtotime($in['skip_before'])) : null,
            'interval_minutes' => max(5, (int) ($in['interval_minutes'] ?? 15)),
            'updated_at'       => date('Y-m-d H:i:s'),
        ];

        // A blank credential box on an edit means "leave what is saved alone",
        // so a manager can change the mapping without re-pasting a private key.
        $credentials = trim((string) ($in['credentials'] ?? ''));
        if ($credentials !== '') {
            $data['credentials'] = lead_sync_encrypt($credentials);
        } elseif (!$id || $mode === 'public') {
            $data['credentials'] = '';
        }

        // Only touched when the form actually carried it: the round-robin pool is
        // a disabled (and therefore unposted) field under the other assign modes,
        // and saving in one of those must not empty a pool set up earlier.
        if (isset($in['assign_pool'])) {
            $data['assign_pool'] = json_encode(array_values(array_filter(array_map('intval', (array) $in['assign_pool']))));
        }

        if (isset($in['column_map']) && is_array($in['column_map'])) {
            $data['column_map'] = json_encode($this->clean_map($in['column_map']));
        }

        if ($id) {
            $this->db->where('id', $id)->update(db_prefix() . 'lead_sync_connections', $data);

            return $id;
        }

        $data['created_at']    = date('Y-m-d H:i:s');
        $data['webhook_token'] = lead_sync_new_token();
        $data['last_status']   = 'idle';

        $this->db->insert(db_prefix() . 'lead_sync_connections', $data);

        return (int) $this->db->insert_id();
    }

    /** Form map (header → target) with unknown targets dropped. */
    private function clean_map(array $map)
    {
        $valid = array_keys(Lead_sync_sheet::targets());
        foreach (array_keys(lead_sync_custom_fields()) as $field_id) {
            $valid[] = 'cf_' . $field_id;
        }

        $out = [];
        foreach ($map as $header => $target) {
            $header = Lead_sync_sheet::norm_header($header);
            if ($header !== '' && in_array($target, $valid, true)) {
                $out[$header] = $target;
            }
        }

        return $out;
    }

    public function saved_map($connection)
    {
        $map = json_decode((string) ($connection->column_map ?? ''), true);

        return is_array($map) ? $map : [];
    }

    public function delete_connection($id)
    {
        $id = (int) $id;

        $this->db->where('id', $id)->delete(db_prefix() . 'lead_sync_connections');
        $this->db->where('connection_id', $id)->delete(db_prefix() . 'lead_sync_runs');
        // The imported-row fingerprints go too: a connection re-created against
        // the same sheet is meant to start clean, and the lead-level dedupe
        // still stops anyone already in the CRM from coming back in twice.
        $this->db->where('connection_id', $id)->delete(db_prefix() . 'lead_sync_rows');

        return true;
    }

    public function regenerate_token($id)
    {
        $token = lead_sync_new_token();
        $this->db->where('id', (int) $id)->update(db_prefix() . 'lead_sync_connections', ['webhook_token' => $token]);

        return $token;
    }

    /* ═══════════════════════════ Preview ═══════════════════════════════ */

    /**
     * Read the sheet without importing anything: what the mapping screen and
     * the "Test connection" button both run on.
     */
    public function preview($connection, $sample = 8)
    {
        $fetch = Lead_sync_google::fetch($connection);

        if (!$fetch['ok']) {
            return ['ok' => false, 'error' => $fetch['error']];
        }

        $map  = Lead_sync_sheet::guess_map($fetch['headers'], $fetch['rows'], $this->saved_map($connection), (bool) $connection->has_header);
        $seen = $this->seen_hashes($connection->id);

        $fresh = 0;
        foreach ($fetch['rows'] as $row) {
            if (!isset($seen[Lead_sync_sheet::row_hash($fetch['headers'], $row)])) {
                $fresh++;
            }
        }

        return [
            'ok'        => true,
            'error'     => '',
            'headers'   => $fetch['headers'],
            'labels'    => array_map([Lead_sync_sheet::class, 'label'], $fetch['headers']),
            'map'       => $map,
            'rows'      => array_slice($fetch['rows'], 0, $sample),
            'total'     => count($fetch['rows']),
            'new_rows'  => $fresh,
            'source'    => $fetch['source'],
        ];
    }

    /* ═══════════════════════════ Running ═══════════════════════════════ */

    /** Connections whose interval has elapsed. */
    public function due_connections()
    {
        $rows = $this->db->where('active', 1)->get(db_prefix() . 'lead_sync_connections')->result();
        $now  = time();
        $due  = [];

        foreach ($rows as $connection) {
            // PHP time, never SQL NOW() — the app timezone and the database
            // server's timezone are not the same clock here.
            if (!$connection->last_run_at
                || $now - strtotime($connection->last_run_at) >= max(5, (int) $connection->interval_minutes) * 60) {
                $due[] = $connection;
            }
        }

        return $due;
    }

    /** Fetch the sheet and import whatever is new. */
    public function run($connection, $trigger = 'cron')
    {
        $run_id = $this->start_run($connection->id, $trigger);
        $fetch  = Lead_sync_google::fetch($connection);

        if (!$fetch['ok']) {
            $this->finish_run($run_id, $connection, ['status' => 'error', 'message' => $fetch['error']]);

            return ['ok' => false, 'error' => $fetch['error'], 'run_id' => $run_id];
        }

        $result = $this->import_rows($connection, $fetch['headers'], $fetch['rows']);
        $this->finish_run($run_id, $connection, $result);

        return ['ok' => true, 'error' => '', 'run_id' => $run_id] + $result;
    }

    /**
     * The pipeline. Rows already handled are recognised by their fingerprint,
     * so this is safe to call repeatedly on the same sheet, in any order, from
     * any trigger.
     */
    public function import_rows($connection, array $headers, array $rows)
    {
        $map       = Lead_sync_sheet::guess_map($headers, $rows, $this->saved_map($connection), (bool) $connection->has_header);
        $seen      = $this->seen_hashes($connection->id);
        $budget    = max(20, (int) lead_sync_opt('lead_sync_max_rows_per_run'));
        $floor     = $connection->skip_before ? strtotime($connection->skip_before . ' 00:00:00') : 0;

        $index     = $this->dedupe_index($connection);
        $counts    = ['rows_read' => count($rows), 'created' => 0, 'duplicates' => 0, 'skipped' => 0, 'failed' => 0];
        $worked    = 0;
        $remaining = 0;
        $errors    = [];

        foreach ($rows as $row) {
            $hash = Lead_sync_sheet::row_hash($headers, $row);

            if (isset($seen[$hash])) {
                continue; // handled by an earlier run
            }
            if ($worked >= $budget) {
                $remaining++;
                continue;
            }
            $worked++;

            $fields = $this->extract($map, $headers, $row);

            if ($floor && $fields['dateadded'] && strtotime($fields['dateadded']) < $floor) {
                $counts['skipped']++;
                $this->remember_row($connection->id, $hash, 0, 'too_old');
                continue;
            }

            if ($fields['name'] === '' && $fields['phonenumber'] === '' && $fields['email'] === '') {
                $counts['skipped']++;
                $this->remember_row($connection->id, $hash, 0, 'empty');
                continue;
            }

            $duplicate = $this->find_duplicate($connection, $fields, $index);
            if ($duplicate) {
                $counts['duplicates']++;
                $this->remember_row($connection->id, $hash, $duplicate, 'duplicate');
                $this->note_duplicate($duplicate, $connection);
                continue;
            }

            try {
                $lead_id = $this->create_lead($connection, $fields);
            } catch (Throwable $e) {
                $lead_id = 0;
                $errors[] = $e->getMessage();
            }

            if ($lead_id) {
                $counts['created']++;
                $this->index_lead($index, $lead_id, $fields);
                $this->remember_row($connection->id, $hash, $lead_id, 'created');
            } else {
                $counts['failed']++;
                // Not remembered: a row that failed on a transient error should
                // be retried on the next run rather than silently dropped.
            }
        }

        $message = [];
        if ($counts['created']) {
            $message[] = $counts['created'] . ' new lead' . ($counts['created'] === 1 ? '' : 's');
        }
        if ($counts['duplicates']) {
            $message[] = $counts['duplicates'] . ' already in the CRM';
        }
        if ($counts['skipped']) {
            $message[] = $counts['skipped'] . ' skipped';
        }
        if ($counts['failed']) {
            $message[] = $counts['failed'] . ' failed';
        }
        if ($remaining) {
            $message[] = $remaining . ' row' . ($remaining === 1 ? '' : 's') . ' left for the next run';
        }
        if (!count($message)) {
            $message[] = 'Nothing new in the sheet';
        }
        if (count($errors)) {
            $message[] = 'Last error: ' . $errors[count($errors) - 1];
        }

        $counts['status']  = $counts['failed'] ? 'partial' : 'ok';
        $counts['message'] = implode(' · ', $message);

        return $counts;
    }

    /* ═══════════════════════ Row → lead fields ═════════════════════════ */

    /** One sheet row, read through the mapping, as CRM-shaped values. */
    private function extract(array $map, array $headers, array $row)
    {
        $out = [
            'name' => '', 'first_name' => '', 'last_name' => '', 'phonenumber' => '', 'email' => '',
            'company' => '', 'title' => '', 'city' => '', 'state' => '', 'zip' => '', 'address' => '',
            'website' => '', 'country_name' => '', 'source_name' => '', 'status_name' => '',
            'assigned_name' => '', 'tags' => '', 'lead_value' => '', 'dateadded' => null,
            'description' => '', 'notes' => [], 'custom' => [],
        ];

        $simple = [
            'name' => 'name', 'first_name' => 'first_name', 'last_name' => 'last_name',
            'phone' => 'phonenumber', 'email' => 'email', 'company' => 'company', 'title' => 'title',
            'city' => 'city', 'state' => 'state', 'zip' => 'zip', 'address' => 'address',
            'website' => 'website', 'country' => 'country_name', 'source' => 'source_name',
            'status' => 'status_name', 'assigned' => 'assigned_name', 'tags' => 'tags',
            'lead_value' => 'lead_value',
        ];

        foreach ($row as $i => $value) {
            $target = $map[$i] ?? 'extra';
            $value  = trim((string) $value);

            if ($target === 'ignore' || $value === '') {
                continue;
            }

            if (isset($simple[$target])) {
                $out[$simple[$target]] = $value;
            } elseif ($target === 'created_at') {
                $out['dateadded'] = Lead_sync_sheet::parse_date($value);
            } elseif ($target === 'description') {
                $out['description'] = trim($out['description'] . "\n" . Lead_sync_sheet::humanize($value));
            } elseif (strncmp($target, 'cf_', 3) === 0) {
                $out['custom'][(int) substr($target, 3)] = Lead_sync_sheet::humanize($value);
            } else {
                // 'extra' — keep it, but say which question it answered, so the
                // agent opening the lead sees the form as the person filled it.
                $label = Lead_sync_sheet::label($headers[$i] ?? '');
                $out['notes'][] = ($label !== '' ? $label . ': ' : '') . Lead_sync_sheet::humanize($value);
            }
        }

        if ($out['name'] === '') {
            $out['name'] = trim($out['first_name'] . ' ' . $out['last_name']);
        }

        return $out;
    }

    /* ═══════════════════════════ Dedupe ════════════════════════════════ */

    /**
     * Every lead the CRM already holds, indexed by phone digits and by e-mail.
     *
     * Built once per run rather than queried per row: a sheet block of 500 rows
     * would otherwise be 500 full scans of tblleads, and the index also lets a
     * lead created earlier in this same run block a repeat further down the
     * sheet — two rows for one person on one day is the commonest way a
     * duplicate slips through.
     */
    private function dedupe_index($connection)
    {
        $index = ['phone' => [], 'email' => []];

        if ((string) $connection->dedupe_by === 'none') {
            return $index;
        }

        foreach ($this->db->select('id, phonenumber, email')->get(db_prefix() . 'leads')->result() as $lead) {
            $phone = lead_sync_phone_norm($lead->phonenumber);
            if ($phone !== '' && !isset($index['phone'][$phone])) {
                $index['phone'][$phone] = (int) $lead->id;
            }
            $email = strtolower(trim((string) $lead->email));
            if ($email !== '' && !isset($index['email'][$email])) {
                $index['email'][$email] = (int) $lead->id;
            }
        }

        return $index;
    }

    /**
     * Is this person already a lead? Phone is the identity by default — it is
     * the one field a Meta form always asks for and a human retypes identically.
     * Comparison is on digits only, so "+91 98765 43210" matches "09876543210".
     *
     * @return int lead id, or 0
     */
    private function find_duplicate($connection, array $fields, array $index)
    {
        $by = (string) $connection->dedupe_by;
        if ($by === 'none') {
            return 0;
        }

        if ($by === 'phone' || $by === 'phone_email') {
            $phone = lead_sync_phone_norm($fields['phonenumber']);
            if ($phone !== '' && isset($index['phone'][$phone])) {
                return $index['phone'][$phone];
            }
        }

        if ($by === 'email' || $by === 'phone_email') {
            $email = strtolower(trim($fields['email']));
            if ($email !== '' && isset($index['email'][$email])) {
                return $index['email'][$email];
            }
        }

        return 0;
    }

    /** Keep the index honest as the run creates leads. */
    private function index_lead(array &$index, $lead_id, array $fields)
    {
        $phone = lead_sync_phone_norm($fields['phonenumber']);
        if ($phone !== '') {
            $index['phone'][$phone] = (int) $lead_id;
        }
        $email = strtolower(trim($fields['email']));
        if ($email !== '') {
            $index['email'][$email] = (int) $lead_id;
        }
    }

    /** Leave a breadcrumb on the lead the sheet row collided with. */
    private function note_duplicate($lead_id, $connection)
    {
        $this->load->model('leads_model');
        $this->leads_model->log_lead_activity(
            (int) $lead_id,
            'Lead Sync: the same person appeared again in "' . $connection->name . '" — no duplicate lead created.',
            true
        );
    }

    /* ═══════════════════════════ Creating ══════════════════════════════ */

    private function create_lead($connection, array $fields)
    {
        $prefix   = db_prefix();
        $now      = date('Y-m-d H:i:s');
        $added    = $fields['dateadded'] ? min($now, $fields['dateadded']) : $now;
        $assigned = $this->resolve_assignee($connection, $fields['assigned_name']);

        $description = trim($fields['description']);
        if (count($fields['notes'])) {
            $description = trim($description . "\n" . implode("\n", $fields['notes']));
        }

        $data = [
            'hash'               => app_generate_hash(),
            'name'               => substr($fields['name'] !== '' ? $fields['name'] : ($fields['email'] ?: $fields['phonenumber']), 0, 191),
            'title'              => substr($fields['title'], 0, 100),
            'company'            => substr($fields['company'], 0, 191),
            'description'        => nl2br($description),
            'country'            => $this->resolve_country($fields['country_name']),
            'zip'                => substr($fields['zip'], 0, 15),
            'city'               => substr($fields['city'], 0, 100),
            'state'              => substr($fields['state'], 0, 50),
            'address'            => substr($fields['address'], 0, 100),
            'assigned'           => $assigned,
            'dateadded'          => $added,
            'from_form_id'       => 0,
            'status'             => $this->resolve_status($connection, $fields['status_name']),
            'source'             => $this->resolve_source($connection, $fields['source_name']),
            'lastcontact'        => null,
            'dateassigned'       => $assigned ? date('Y-m-d') : null,
            'last_status_change' => $added,
            'addedfrom'          => 0,
            'email'              => substr(strtolower($fields['email']), 0, 100),
            'website'            => substr($fields['website'], 0, 150),
            'leadorder'          => 1,
            'phonenumber'        => substr($fields['phonenumber'], 0, 50),
            'lost'               => 0,
            'junk'               => 0,
            'is_public'          => 0,
            'default_language'   => '',
            'client_id'          => 0,
            'lead_value'         => $this->resolve_value($fields['lead_value']),
        ];

        $data = hooks()->apply_filters('lead_sync_before_lead_added', $data, ['connection' => $connection, 'fields' => $fields]);

        $this->db->insert($prefix . 'leads', $data);
        $lead_id = (int) $this->db->insert_id();

        if (!$lead_id) {
            return 0;
        }

        $tags = trim(implode(',', array_filter([$connection->tags, $fields['tags']])), ',');
        if ($tags !== '') {
            handle_tags_save($tags, $lead_id, 'lead');
        }

        if (count($fields['custom'])) {
            handle_custom_fields_post($lead_id, ['leads' => $fields['custom']]);
        }

        $this->load->model('leads_model');
        $this->leads_model->log_lead_activity($lead_id, 'Imported by Lead Sync from "' . $connection->name . '".', true);

        if ($assigned) {
            // integration = true: the notification must fire even though there
            // is no logged-in staff member behind a cron run.
            $this->leads_model->lead_assigned_member_notification($lead_id, $assigned, true);
        }

        // The core signal. Everything else in the CRM — the SHRA leads desk,
        // automations, other modules — reacts to this and needs to know nothing
        // about Lead Sync.
        hooks()->do_action('lead_created', $lead_id);
        hooks()->do_action('lead_sync_lead_imported', ['lead_id' => $lead_id, 'connection_id' => (int) $connection->id]);

        return $lead_id;
    }

    /* ── Resolving values against the CRM ───────────────────────────────── */

    private function resolve_status($connection, $name)
    {
        $name = trim((string) $name);

        if ($name !== '') {
            foreach (lead_sync_statuses() as $id => $label) {
                if (mb_strtolower($label) === mb_strtolower($name)) {
                    return $id;
                }
            }
        }

        if ((int) $connection->default_status) {
            return (int) $connection->default_status;
        }

        $statuses = lead_sync_statuses();

        return count($statuses) ? (int) array_key_first($statuses) : 0;
    }

    /**
     * A source named in the sheet ("Instagram", "Facebook Lead Ad") is created
     * on first sight — the alternative is silently dropping the one field that
     * tells a manager which campaign is working. Meta's shorthand is spelled
     * out first so "ig" lands on the Instagram source that already exists.
     */
    private function resolve_source($connection, $name)
    {
        $name = lead_sync_normalize_source($name);

        if ($name === '') {
            return (int) $connection->default_source;
        }

        foreach (lead_sync_sources() as $id => $label) {
            if (mb_strtolower($label) === mb_strtolower($name)) {
                return $id;
            }
        }

        $this->db->insert(db_prefix() . 'leads_sources', ['name' => substr(Lead_sync_sheet::humanize($name), 0, 100)]);
        $id = (int) $this->db->insert_id();

        if ($id) {
            // The static cache in the helper is now stale for the rest of the run.
            lead_sync_sources_refresh();

            return $id;
        }

        return (int) $connection->default_source;
    }

    private function resolve_country($name)
    {
        $name = trim((string) $name);
        if ($name === '') {
            return 0;
        }

        $row = $this->db->select('country_id')
            ->group_start()
                ->where('short_name', $name)->or_where('long_name', $name)->or_where('iso2', strtoupper($name))
            ->group_end()
            ->limit(1)->get(db_prefix() . 'countries')->row();

        return $row ? (int) $row->country_id : 0;
    }

    private function resolve_value($raw)
    {
        $raw = preg_replace('/[^\d.\-]/', '', (string) $raw);

        return $raw === '' ? null : round((float) $raw, 2);
    }

    /**
     * Who picks this lead up. Round robin walks the pool in order and stores
     * where it stopped, so a quiet campaign still spreads evenly instead of
     * always handing the first lead of the day to the same agent.
     */
    private function resolve_assignee($connection, $name_from_sheet = '')
    {
        $mode = (string) $connection->assign_mode;

        if ($mode === 'column') {
            $name = trim((string) $name_from_sheet);
            if ($name !== '') {
                foreach (lead_sync_staff() as $id => $full_name) {
                    if (mb_strtolower($full_name) === mb_strtolower($name)) {
                        return $id;
                    }
                }
                $row = $this->db->select('staffid')->where('email', $name)->where('active', 1)
                    ->limit(1)->get(db_prefix() . 'staff')->row();
                if ($row) {
                    return (int) $row->staffid;
                }
            }

            return (int) $connection->assign_to;
        }

        if ($mode === 'fixed') {
            return (int) $connection->assign_to;
        }

        if ($mode === 'round_robin') {
            $pool = json_decode((string) $connection->assign_pool, true);
            $pool = is_array($pool) ? array_values(array_filter(array_map('intval', $pool))) : [];

            if (!count($pool)) {
                $pool = array_keys(lead_sync_staff());
            }
            if (!count($pool)) {
                return 0;
            }

            $index = ((int) $connection->rr_index) % count($pool);
            $this->db->where('id', (int) $connection->id)
                ->update(db_prefix() . 'lead_sync_connections', ['rr_index' => $index + 1]);
            $connection->rr_index = $index + 1;

            return (int) $pool[$index];
        }

        return 0;
    }

    /* ═══════════════════════ Row fingerprints ══════════════════════════ */

    private function seen_hashes($connection_id)
    {
        $out  = [];
        $rows = $this->db->select('row_hash')->where('connection_id', (int) $connection_id)
            ->get(db_prefix() . 'lead_sync_rows')->result();

        foreach ($rows as $row) {
            $out[$row->row_hash] = true;
        }

        return $out;
    }

    private function remember_row($connection_id, $hash, $lead_id, $outcome)
    {
        // Two triggers can race on the same row (cron and a webhook push a
        // second apart); the unique key settles it and the loser is a no-op.
        $this->db->query(
            'INSERT IGNORE INTO `' . db_prefix() . 'lead_sync_rows` (connection_id, row_hash, lead_id, outcome, imported_at) VALUES (?, ?, ?, ?, ?)',
            [(int) $connection_id, $hash, (int) $lead_id, $outcome, date('Y-m-d H:i:s')]
        );
    }

    /* ═══════════════════════════ Run history ═══════════════════════════ */

    private function start_run($connection_id, $trigger)
    {
        $this->db->insert(db_prefix() . 'lead_sync_runs', [
            'connection_id' => (int) $connection_id,
            'trigger_type'  => $trigger,
            'started_at'    => date('Y-m-d H:i:s'),
            'status'        => 'running',
        ]);

        return (int) $this->db->insert_id();
    }

    private function finish_run($run_id, $connection, array $result)
    {
        $now = date('Y-m-d H:i:s');

        $this->db->where('id', (int) $run_id)->update(db_prefix() . 'lead_sync_runs', [
            'finished_at' => $now,
            'rows_read'   => (int) ($result['rows_read'] ?? 0),
            'created'     => (int) ($result['created'] ?? 0),
            'duplicates'  => (int) ($result['duplicates'] ?? 0),
            'skipped'     => (int) ($result['skipped'] ?? 0),
            'failed'      => (int) ($result['failed'] ?? 0),
            'status'      => (string) ($result['status'] ?? 'ok'),
            'message'     => (string) ($result['message'] ?? ''),
        ]);

        $this->db->where('id', (int) $connection->id)->update(db_prefix() . 'lead_sync_connections', [
            'last_run_at'    => $now,
            'last_status'    => (string) ($result['status'] ?? 'ok'),
            'last_message'   => substr((string) ($result['message'] ?? ''), 0, 500),
            'total_imported' => (int) $connection->total_imported + (int) ($result['created'] ?? 0),
        ]);
    }

    public function runs($connection_id = 0, $limit = 100)
    {
        $this->db->select('r.*, c.name as connection_name')
            ->from(db_prefix() . 'lead_sync_runs r')
            ->join(db_prefix() . 'lead_sync_connections c', 'c.id = r.connection_id', 'left')
            ->order_by('r.started_at', 'desc')->limit((int) $limit);

        if ($connection_id) {
            $this->db->where('r.connection_id', (int) $connection_id);
        }

        return $this->db->get()->result();
    }

    /** Leads this connection has produced, newest first. */
    public function imported_leads($connection_id, $limit = 50)
    {
        return $this->db->select('l.id, l.name, l.email, l.phonenumber, l.dateadded, l.assigned, s.row_hash, s.outcome, s.imported_at')
            ->from(db_prefix() . 'lead_sync_rows s')
            ->join(db_prefix() . 'leads l', 'l.id = s.lead_id', 'inner')
            ->where('s.connection_id', (int) $connection_id)
            ->where('s.outcome', 'created')
            ->order_by('s.id', 'desc')->limit((int) $limit)
            ->get()->result();
    }

    public function summary()
    {
        $prefix = db_prefix();
        $today  = date('Y-m-d');

        return [
            'connections' => (int) $this->db->count_all($prefix . 'lead_sync_connections'),
            'active'      => (int) $this->db->where('active', 1)->count_all_results($prefix . 'lead_sync_connections'),
            'imported'    => (int) $this->db->where('outcome', 'created')->count_all_results($prefix . 'lead_sync_rows'),
            'today'       => (int) $this->db->where('outcome', 'created')->where('imported_at >=', $today . ' 00:00:00')
                                ->count_all_results($prefix . 'lead_sync_rows'),
        ];
    }

    public function purge_old_runs($days)
    {
        $days = (int) $days;
        if ($days < 1) {
            return;
        }

        $this->db->where('started_at <', date('Y-m-d H:i:s', time() - $days * 86400))
            ->delete(db_prefix() . 'lead_sync_runs');
    }
}
