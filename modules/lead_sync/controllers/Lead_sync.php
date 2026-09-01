<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Lead Sync — admin area.
 *
 * Screens: connections list, the connection editor (which doubles as the
 * column-mapping screen), run history and settings. Every mutating endpoint
 * re-checks its capability; hiding a button is never the access control.
 */
class Lead_sync extends AdminController
{
    public function __construct()
    {
        parent::__construct();

        $this->load->model('lead_sync/lead_sync_model');

        require_once module_dir_path(LEAD_SYNC_MODULE_NAME, 'libraries/Lead_sync_sheet.php');
        require_once module_dir_path(LEAD_SYNC_MODULE_NAME, 'libraries/Lead_sync_google.php');

        if (!lead_sync_can_access()) {
            access_denied('Lead Sync');
        }
    }

    /* ═══════════════════════════ Connections ═══════════════════════════ */

    public function index()
    {
        $data['connections'] = $this->lead_sync_model->connections();
        $data['summary']     = $this->lead_sync_model->summary();
        $data['recent']      = $this->lead_sync_model->runs(0, 8);
        $data['title']       = _l('lead_sync');

        $this->load->view('index', $data);
    }

    /**
     * Create (no id) or edit a connection. The same screen carries the column
     * mapping, which is filled in over AJAX once the sheet has been read.
     */
    public function connection($id = 0)
    {
        $id = (int) $id;

        if ($this->input->post()) {
            if (!lead_sync_can($id ? 'edit' : 'create')) {
                access_denied('Lead Sync');
            }

            $post   = $this->input->post(null, false);
            $result = $this->lead_sync_model->save_connection($post, $id);

            if (is_string($result)) {
                set_alert('warning', $result);

                return redirect(admin_url('lead_sync/connection' . ($id ? '/' . $id : '')));
            }

            set_alert('success', $id ? _l('lead_sync_connection_updated') : _l('lead_sync_connection_created'));

            return redirect(admin_url('lead_sync/connection/' . $result));
        }

        $connection = $id ? $this->lead_sync_model->connection($id) : null;
        if ($id && !$connection) {
            show_404();
        }

        $data['connection']    = $connection;
        $data['saved_map']     = $connection ? $this->lead_sync_model->saved_map($connection) : [];
        $data['targets']       = Lead_sync_sheet::targets();
        $data['custom_fields'] = lead_sync_custom_fields();
        $data['statuses']      = lead_sync_statuses();
        $data['sources']       = lead_sync_sources();
        $data['staff']         = lead_sync_staff();
        $data['leads']         = $connection ? $this->lead_sync_model->imported_leads($connection->id, 25) : [];
        $data['title']         = $connection ? $connection->name : _l('lead_sync_new_connection');

        $this->load->view('connection', $data);
    }

    /**
     * Read the sheet and answer with its columns, the suggested mapping and a
     * few sample rows. Drives both the "Test connection" button and the
     * mapping table.
     */
    public function preview($id = 0)
    {
        $connection = $this->connection_or_draft((int) $id);

        if (is_string($connection)) {
            return $this->json(['ok' => false, 'error' => $connection]);
        }

        $preview = $this->lead_sync_model->preview($connection, 8);

        if (!$preview['ok']) {
            return $this->json($preview);
        }

        // Mapping targets, custom fields appended, ready for the <select>s.
        $targets = Lead_sync_sheet::targets();
        foreach (lead_sync_custom_fields() as $field_id => $label) {
            $targets['cf_' . $field_id] = 'Custom field: ' . $label;
        }
        $preview['targets'] = $targets;

        return $this->json($preview);
    }

    /**
     * The connection to preview: the saved one, or an unsaved draft built from
     * whatever is currently typed into the form (so "Test connection" works
     * before anything has been saved).
     */
    private function connection_or_draft($id)
    {
        $post = $this->input->post(null, false) ?: [];

        if ($id) {
            $connection = $this->lead_sync_model->connection($id);
            if (!$connection) {
                return 'That connection no longer exists.';
            }
        } else {
            $connection = (object) [
                'id' => 0, 'name' => 'Draft', 'column_map' => '', 'has_header' => 1,
                'credentials' => '', 'spreadsheet_id' => '', 'gid' => '', 'tab_name' => '',
                'sheet_url' => '', 'auth_mode' => 'public',
            ];
        }

        // Anything typed into the form wins over what is stored, so a manager
        // can test a changed link or a new key before committing to it.
        foreach (['sheet_url', 'gid', 'tab_name', 'auth_mode'] as $field) {
            if (isset($post[$field])) {
                $connection->$field = (string) $post[$field];
            }
        }
        if (isset($post['has_header'])) {
            $connection->has_header = (int) (bool) $post['has_header'];
        }
        if (!empty($post['credentials'])) {
            $connection->credentials = lead_sync_encrypt((string) $post['credentials']);
        }
        if (trim((string) $connection->sheet_url) === '') {
            return 'Paste the Google Sheet link first.';
        }

        return $connection;
    }

    /** Run one connection now. */
    public function sync($id)
    {
        if (!lead_sync_can('edit')) {
            access_denied('Lead Sync');
        }

        $connection = $this->lead_sync_model->connection((int) $id);
        if (!$connection) {
            show_404();
        }

        $result = $this->lead_sync_model->run($connection, 'manual');

        if ($this->input->is_ajax_request()) {
            return $this->json($result);
        }

        set_alert($result['ok'] ? 'success' : 'danger', $result['ok'] ? $result['message'] : $result['error']);

        return redirect(admin_url('lead_sync/connection/' . (int) $id));
    }

    public function toggle($id)
    {
        if (!lead_sync_can('edit')) {
            access_denied('Lead Sync');
        }

        $connection = $this->lead_sync_model->connection((int) $id);
        if (!$connection) {
            show_404();
        }

        $this->db->where('id', (int) $id)->update(db_prefix() . 'lead_sync_connections', [
            'active' => $connection->active ? 0 : 1,
        ]);
        set_alert('success', $connection->active ? _l('lead_sync_paused') : _l('lead_sync_resumed'));

        return redirect(admin_url('lead_sync'));
    }

    public function regenerate_token($id)
    {
        if (!lead_sync_can('edit')) {
            access_denied('Lead Sync');
        }

        $this->lead_sync_model->regenerate_token((int) $id);
        set_alert('success', _l('lead_sync_token_regenerated'));

        return redirect(admin_url('lead_sync/connection/' . (int) $id));
    }

    public function delete($id)
    {
        if (!lead_sync_can('delete')) {
            access_denied('Lead Sync');
        }

        $this->lead_sync_model->delete_connection((int) $id);
        set_alert('success', _l('lead_sync_connection_deleted'));

        return redirect(admin_url('lead_sync'));
    }

    /* ═══════════════════════════ History ═══════════════════════════════ */

    public function logs($connection_id = 0)
    {
        $data['runs']        = $this->lead_sync_model->runs((int) $connection_id, 150);
        $data['connections'] = $this->lead_sync_model->connections();
        $data['filter']      = (int) $connection_id;
        $data['title']       = _l('lead_sync_history');

        $this->load->view('logs', $data);
    }

    /* ═══════════════════════════ Settings ══════════════════════════════ */

    public function settings()
    {
        if ($this->input->post()) {
            if (!lead_sync_can('edit')) {
                access_denied('Lead Sync');
            }

            foreach (array_keys(lead_sync_default_options()) as $option) {
                if ($option === 'lead_sync_last_cron') {
                    continue;
                }
                update_option($option, (string) $this->input->post($option));
            }
            update_option('lead_sync_enabled', $this->input->post('lead_sync_enabled') ? '1' : '0');

            set_alert('success', _l('settings_updated'));

            return redirect(admin_url('lead_sync/settings'));
        }

        $data['title'] = _l('lead_sync_settings');

        $this->load->view('settings', $data);
    }

    /* ═══════════════════════════ Utilities ════════════════════════════ */

    private function json($payload)
    {
        return $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($payload));
    }
}
