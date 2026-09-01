<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Instant delivery endpoint — no authentication beyond the connection's token.
 *
 *   POST /lead_sync/push/{token}
 *
 * Body is JSON: {"headers": ["Full name", "Phone", …], "rows": [[…], [… ]]}
 * A single row may also be posted as an object keyed by column name, which is
 * what most no-code tools (Zapier, Make, n8n) send by default.
 *
 * This runs the exact same pipeline as the cron poll, including the row
 * fingerprints — so a tool that retries a delivery, or an Apps Script that
 * re-sends a block after an error, cannot create the same lead twice.
 *
 * The token is the credential, so it is compared in constant time and a bad
 * one gets the same flat 404-ish answer as an unknown connection: nothing here
 * should help someone probe for valid tokens.
 */
class Lead_sync_push extends App_Controller
{
    public function __construct()
    {
        parent::__construct();

        $this->load->model('lead_sync/lead_sync_model');

        require_once module_dir_path(LEAD_SYNC_MODULE_NAME, 'libraries/Lead_sync_sheet.php');

        if (function_exists('lead_sync_maybe_upgrade_schema')) {
            lead_sync_maybe_upgrade_schema();
        }
    }

    public function index($token = '')
    {
        if (strtoupper((string) $this->input->server('REQUEST_METHOD')) !== 'POST') {
            return $this->reply(405, ['ok' => false, 'error' => 'POST a JSON body to this URL.']);
        }

        if (lead_sync_opt('lead_sync_enabled') !== '1') {
            return $this->reply(503, ['ok' => false, 'error' => 'Lead Sync is switched off.']);
        }

        $connection = $this->lead_sync_model->connection_by_token($token);

        if (!$connection || !hash_equals((string) $connection->webhook_token, (string) $token)) {
            return $this->reply(404, ['ok' => false, 'error' => 'Unknown endpoint.']);
        }
        if (!$connection->active) {
            return $this->reply(409, ['ok' => false, 'error' => 'This connection is paused.']);
        }

        $payload = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            return $this->reply(400, ['ok' => false, 'error' => 'Body must be JSON.']);
        }

        [$headers, $rows] = $this->normalize($payload);

        if (!count($rows)) {
            return $this->reply(200, ['ok' => true, 'created' => 0, 'message' => 'Nothing to import.']);
        }

        $sheet  = Lead_sync_sheet::from_values($headers, $rows);

        try {
            $result = $this->lead_sync_model->import_rows($connection, $sheet['headers'], $sheet['rows']);
        } catch (Throwable $e) {
            log_activity('Lead Sync push error on "' . $connection->name . '": ' . $e->getMessage());

            return $this->reply(500, ['ok' => false, 'error' => 'The import failed. See the CRM activity log.']);
        }

        $this->record_push($connection, $result);

        return $this->reply(200, [
            'ok'         => true,
            'created'    => (int) $result['created'],
            'duplicates' => (int) $result['duplicates'],
            'skipped'    => (int) $result['skipped'],
            'message'    => $result['message'],
        ]);
    }

    /**
     * Accept the three shapes real senders use:
     *   {headers: [...], rows: [[...], ...]}   the Apps Script in the settings screen
     *   {rows: [{col: val}, ...]}              Make / n8n batches
     *   {col: val, ...}                        a single Zapier row
     */
    private function normalize(array $payload)
    {
        $headers = [];
        $rows    = [];

        if (isset($payload['headers']) && is_array($payload['headers'])) {
            $headers = array_values($payload['headers']);
        }

        $incoming = [];
        if (isset($payload['rows']) && is_array($payload['rows'])) {
            $incoming = $payload['rows'];
        } elseif (isset($payload['row']) && is_array($payload['row'])) {
            $incoming = [$payload['row']];
        } else {
            $incoming = [$payload]; // a bare object: one row keyed by column name
        }

        foreach ($incoming as $row) {
            if (!is_array($row)) {
                continue;
            }

            // Keyed row: the keys are the column names.
            if (array_keys($row) !== range(0, count($row) - 1)) {
                if (!count($headers)) {
                    $headers = array_keys($row);
                }
                $ordered = [];
                foreach ($headers as $header) {
                    $ordered[] = $row[$header] ?? '';
                }
                $rows[] = $ordered;
                continue;
            }

            $rows[] = array_values($row);
        }

        return [$headers, $rows];
    }

    /** A push is a run like any other, so it shows up in the history screen. */
    private function record_push($connection, array $result)
    {
        $now = date('Y-m-d H:i:s');

        $this->db->insert(db_prefix() . 'lead_sync_runs', [
            'connection_id' => (int) $connection->id,
            'trigger_type'  => 'webhook',
            'started_at'    => $now,
            'finished_at'   => $now,
            'rows_read'     => (int) $result['rows_read'],
            'created'       => (int) $result['created'],
            'duplicates'    => (int) $result['duplicates'],
            'skipped'       => (int) $result['skipped'],
            'failed'        => (int) $result['failed'],
            'status'        => (string) $result['status'],
            'message'       => (string) $result['message'],
        ]);

        $this->db->where('id', (int) $connection->id)->update(db_prefix() . 'lead_sync_connections', [
            'last_run_at'    => $now,
            'last_status'    => (string) $result['status'],
            'last_message'   => substr((string) $result['message'], 0, 500),
            'total_imported' => (int) $connection->total_imported + (int) $result['created'],
        ]);
    }

    private function reply($status, array $body)
    {
        return $this->output
            ->set_status_header($status)
            ->set_content_type('application/json')
            ->set_output(json_encode($body));
    }
}
