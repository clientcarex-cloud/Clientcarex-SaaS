<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * ePTW — configuration screens, each gated by its own menu permission
 * (Staff → Permissions → "ePTW — Setup — …").
 * Reached at admin/eptw/eptw_setup/<method>.
 */
class Eptw_setup extends AdminController
{
    public function __construct()
    {
        parent::__construct();

        $this->load->model('eptw/eptw_model', 'setup');
        $this->load->model('eptw/eptw_permits_model', 'permits');

        [$feature, $capability] = $this->required_permission($this->router->fetch_method());
        if (!eptw_perm($feature, $capability)) {
            // Landing on Setup without the settings screen → the first setup screen they may open.
            if ($this->router->fetch_method() === 'index' && !$this->input->post() && eptw_can('setup')) {
                redirect(admin_url(eptw_setup_url()));
            }
            access_denied('ePTW ' . strtolower(eptw_menu_permissions()[$feature]['name']));
        }
    }

    /**
     * Method → [menu permission feature, capability]. A save with an id is an
     * edit, without one a create. Unknown methods need the settings edit right.
     */
    private function required_permission($method)
    {
        $segs = $this->uri->segment_array();
        $at   = array_search($method, $segs, true);
        $id   = $at !== false ? (int) ($segs[$at + 1] ?? 0) : 0;
        $post = (bool) $this->input->post();
        $save = $id ? 'edit' : 'create';

        switch ($method) {
            case 'index':
                return ['eptw_setup_settings', $post ? 'edit' : 'view'];
            case 'projects':
            case 'contractors':
            case 'types':
            case 'team':
            case 'simops':
                return ['eptw_setup_' . $method, 'view'];
            case 'project_save':
            case 'area_save':
                return ['eptw_setup_projects', $save];
            case 'project_delete':
            case 'area_delete':
                return ['eptw_setup_projects', 'delete'];
            case 'contractor_save':
                return ['eptw_setup_contractors', $save];
            case 'contractor_delete':
                return ['eptw_setup_contractors', 'delete'];
            case 'type':
                return ['eptw_setup_types', $post ? $save : ($id ? 'view' : 'create')];
            case 'type_reset':
                return ['eptw_setup_types', 'edit'];
            case 'type_delete':
                return ['eptw_setup_types', 'delete'];
            case 'team_save':
                $staff_id = (int) $this->input->post('staff_id');
                $exists   = $staff_id && $this->db->where('staff_id', $staff_id)->count_all_results(db_prefix() . 'eptw_team') > 0;

                return ['eptw_setup_team', $exists ? 'edit' : 'create'];
            case 'team_delete':
                return ['eptw_setup_team', 'delete'];
            case 'rule_save':
                return ['eptw_setup_simops', $save];
            case 'rule_delete':
                return ['eptw_setup_simops', 'delete'];
            case 'import':
                return ['eptw_setup_import', empty($_FILES['register']['name']) ? 'view' : 'create'];
            case 'import_commit':
            case 'import_rollback':
                return ['eptw_setup_import', 'create'];
            case 'people':
                return ['eptw_setup_people', 'view'];
            case 'person_save':
                return ['eptw_setup_people', $save];
            case 'person_delete':
                return ['eptw_setup_people', 'delete'];
            case 'matrix':
                return ['eptw_setup_types', $post ? 'edit' : 'view'];
        }

        return ['eptw_setup_settings', 'edit'];
    }

    private function back($to, $result, $ok_message)
    {
        set_alert(is_string($result) ? 'warning' : 'success', is_string($result) ? $result : $ok_message);

        return redirect(admin_url('eptw/eptw_setup/' . $to));
    }

    /* ═══════════════════════════ Settings ═══════════════════════════ */

    public function index()
    {
        if ($this->input->post()) {
            return $this->back('', $this->setup->save_settings($this->input->post(null, false)), 'Settings saved.');
        }
        $data['title']  = 'ePTW setup';
        $data['sample'] = $this->sample_number();
        $this->load->view('setup/settings', $data);
    }

    private function sample_number()
    {
        $projects = eptw_projects();
        $types    = eptw_permit_types();

        return strtr(eptw_opt('eptw_number_format'), [
            '{PROJECT}' => count($projects) ? $projects[0]->code : 'ALPHA',
            '{AREA}'    => 'Z2',
            '{TYPE}'    => count($types) ? $types[0]->code : 'HW',
            '{YEAR}'    => date('Y'),
            '{YY}'      => date('y'),
            '{MONTH}'   => date('m'),
            '{SERIAL}'  => str_pad('21', (int) eptw_opt('eptw_serial_padding'), '0', STR_PAD_LEFT),
        ]);
    }

    /* ═══════════════════════════ Projects & areas ═══════════════════ */

    public function projects()
    {
        $data['projects'] = $this->setup->projects();
        $data['areas']    = $this->setup->areas();
        $data['counts']   = $this->permit_counts('project_id');
        $data['title']    = 'Projects & areas';
        $this->load->view('setup/projects', $data);
    }

    public function project_save($id = 0)
    {
        return $this->back('projects', $this->setup->save_project($this->input->post(null, false) ?: [], (int) $id), 'Project saved.');
    }

    public function project_delete($id)
    {
        return $this->back('projects', $this->setup->delete_project((int) $id), 'Project deleted.');
    }

    public function area_save($id = 0)
    {
        return $this->back('projects', $this->setup->save_area($this->input->post(null, false) ?: [], (int) $id), 'Area saved.');
    }

    public function area_delete($id)
    {
        return $this->back('projects', $this->setup->delete_area((int) $id), 'Area deleted.');
    }

    /* ═══════════════════════════ Contractors ════════════════════════ */

    public function contractors()
    {
        $data['contractors'] = $this->setup->contractors();
        $data['counts']      = $this->permit_counts('contractor_id');
        $data['title']       = 'Contractors';
        $this->load->view('setup/contractors', $data);
    }

    public function contractor_save($id = 0)
    {
        return $this->back('contractors', $this->setup->save_contractor($this->input->post(null, false) ?: [], (int) $id), 'Contractor saved.');
    }

    public function contractor_delete($id)
    {
        return $this->back('contractors', $this->setup->delete_contractor((int) $id), 'Contractor deleted.');
    }

    /* ═══════════════════════════ Permit types ═══════════════════════ */

    public function types()
    {
        $data['types']  = $this->setup->types();
        $data['counts'] = $this->permit_counts('permit_type_id');
        $data['title']  = 'Permit types';
        $this->load->view('setup/types', $data);
    }

    public function type($id = 0)
    {
        $id = (int) $id;
        if ($this->input->post()) {
            $result = $this->setup->save_type($this->input->post(null, false), $id);
            if (is_string($result)) {
                set_alert('warning', $result);

                return redirect(admin_url('eptw/eptw_setup/type' . ($id ? '/' . $id : '')));
            }
            $cache = &eptw_cache();
            unset($cache['types_1'], $cache['types_0']);
            set_alert('success', 'Permit type saved.');

            return redirect(admin_url('eptw/eptw_setup/types'));
        }
        $type = $id ? $this->setup->type($id) : null;
        if ($id && !$type) {
            show_404();
        }
        $data['type']  = $type;
        $data['title'] = $type ? $type->name : 'New permit type';
        $this->load->view('setup/type_form', $data);
    }

    public function type_delete($id)
    {
        return $this->back('types', $this->setup->delete_type((int) $id), 'Permit type deleted.');
    }

    public function type_reset($id)
    {
        return $this->back('types', $this->setup->reset_type_from_seed((int) $id) ? true : 'This type is not one of the shipped standard templates.', 'Hazards and controls restored from the standard template.');
    }

    /* ═══════════════════════════ Approval matrix ═══════════════════ */

    /** Review steps per project × permit type (overrides the permit type default). */
    public function matrix()
    {
        $projects   = eptw_projects(false);
        $project_id = (int) ($this->input->post('project_id') ?: $this->input->get('project') ?: (count($projects) ? $projects[0]->id : 0));
        if ($this->input->post()) {
            $result = $this->setup->save_matrix($project_id, $this->input->post(null, false));
            set_alert(is_string($result) ? 'warning' : 'success', is_string($result) ? $result : 'Approval matrix saved.');

            return redirect(admin_url('eptw/eptw_setup/matrix?project=' . $project_id));
        }
        $data['projects']   = $projects;
        $data['project_id'] = $project_id;
        $data['matrix']     = $this->setup->matrix($project_id);
        $data['types']      = eptw_permit_types(false);
        $data['title']      = 'Approval matrix';
        $this->load->view('setup/matrix', $data);
    }

    /* ═══════════════════════════ People ═════════════════════════════ */

    public function people()
    {
        $data['people']      = $this->setup->people();
        $data['contractors'] = eptw_contractors(false);
        $data['projects']    = eptw_projects(false);
        $data['staff']       = eptw_staff();
        $data['title']       = 'People';
        $this->load->view('setup/people', $data);
    }

    public function person_save($id = 0)
    {
        return $this->back('people', $this->setup->save_person($this->input->post(null, false) ?: [], (int) $id), 'Person saved.');
    }

    public function person_delete($id)
    {
        return $this->back('people', $this->setup->delete_person((int) $id), 'Person removed.');
    }

    /* ═══════════════════════════ Team ═══════════════════════════════ */

    public function team()
    {
        $data['team']     = $this->setup->team();
        $data['staff']    = eptw_staff();
        $data['projects'] = eptw_projects(false);
        $data['title']    = 'Team & roles';
        $this->load->view('setup/team', $data);
    }

    public function team_save()
    {
        return $this->back('team', $this->setup->save_member($this->input->post(null, false) ?: []), 'Team member saved.');
    }

    public function team_delete($id)
    {
        $this->setup->remove_member((int) $id);

        return $this->back('team', true, 'Removed from the ePTW team.');
    }

    /* ═══════════════════════════ SIMOPS rules ═══════════════════════ */

    public function simops()
    {
        $data['rules'] = $this->setup->simops_rules();
        $data['types'] = eptw_permit_types(false);
        $data['title'] = 'SIMOPS rules';
        $this->load->view('setup/simops', $data);
    }

    public function rule_save($id = 0)
    {
        return $this->back('simops', $this->setup->save_rule($this->input->post(null, false) ?: [], (int) $id), 'Rule saved.');
    }

    public function rule_delete($id)
    {
        $this->setup->delete_rule((int) $id);

        return $this->back('simops', true, 'Rule deleted.');
    }

    /* ═══════════════════════════ Register import ════════════════════ */

    /**
     * Step 1: upload the Excel/CSV register → dry run: every row validated,
     * duplicates and errors listed, nothing written.
     * Step 2 (import_commit): import the valid rows as one batch.
     * Each batch is logged and can be reversed (import_rollback).
     */
    public function import()
    {
        require_once module_dir_path(EPTW_MODULE_NAME, 'libraries/Eptw_import.php');

        $data['title']   = 'Import the Excel register';
        $data['preview'] = null;
        $data['imports'] = $this->setup->imports();

        if (!empty($_FILES['register']['name'])) {
            if (($_FILES['register']['error'] ?? 1) !== UPLOAD_ERR_OK) {
                set_alert('warning', 'The upload failed — try again.');

                return redirect(admin_url('eptw/eptw_setup/import'));
            }
            $table = Eptw_import::read($_FILES['register']['tmp_name'], $_FILES['register']['name']);
            if (is_string($table)) {
                set_alert('warning', $table);

                return redirect(admin_url('eptw/eptw_setup/import'));
            }
            $table['file_name'] = substr((string) $_FILES['register']['name'], 0, 191);
            $token = bin2hex(random_bytes(8));
            $dir   = FCPATH . 'uploads/eptw/import/';
            _maybe_create_upload_path($dir);
            file_put_contents($dir . $token . '.json', json_encode($table));

            $map             = Eptw_import::map_headers($table['headers']);
            $default_project = (int) ($this->input->post('default_project') ?: (count(eptw_projects()) ? eptw_projects()[0]->id : 0));
            $data['preview'] = [
                'token'           => $token,
                'file_name'       => $table['file_name'],
                'headers'         => $table['headers'],
                'map'             => $map,
                'rows'            => array_slice($table['rows'], 0, 8),
                'count'           => count($table['rows']),
                'unmapped'        => array_values(array_diff_key($table['headers'], $map)),
                'default_project' => $default_project,
                'dry'             => isset(array_flip($map)['type']) ? $this->setup->import_rows($table, $map, $default_project, true) : null,
            ];
        }

        $this->load->view('setup/import', $data);
    }

    public function import_commit()
    {
        require_once module_dir_path(EPTW_MODULE_NAME, 'libraries/Eptw_import.php');

        $token = preg_replace('/[^a-f0-9]/', '', (string) $this->input->post('token'));
        $file  = FCPATH . 'uploads/eptw/import/' . $token . '.json';
        if ($token === '' || !is_file($file)) {
            set_alert('warning', 'The uploaded register has expired — upload it again.');

            return redirect(admin_url('eptw/eptw_setup/import'));
        }
        $table = json_decode((string) file_get_contents($file), true);
        @unlink($file);
        if (!is_array($table)) {
            set_alert('warning', 'The uploaded register could not be read.');

            return redirect(admin_url('eptw/eptw_setup/import'));
        }

        $map = Eptw_import::map_headers($table['headers']);
        $p   = db_prefix();
        $this->db->insert($p . 'eptw_imports', [
            'file_name' => (string) ($table['file_name'] ?? ''), 'total_rows' => count($table['rows']),
            'created_by' => (int) eptw_me()['staff_id'], 'created_at' => date('Y-m-d H:i:s'),
        ]);
        $batch  = (int) $this->db->insert_id();
        $result = $this->setup->import_rows($table, $map, (int) $this->input->post('default_project'), false, $batch);
        $this->db->where('id', $batch)->update($p . 'eptw_imports', [
            'created' => $result['created'], 'skipped' => $result['skipped'], 'failed' => $result['failed'],
            'report'  => json_encode(array_values(array_filter($result['rows'], function ($r) { return $r['result'] !== 'ok'; }))),
        ]);
        log_activity('ePTW register import #' . $batch . ': ' . $result['created'] . ' created, ' . $result['skipped'] . ' skipped, ' . $result['failed'] . ' failed');

        $msg = 'Import #' . $batch . ': ' . $result['created'] . ' permit(s) imported'
            . ($result['skipped'] ? ', ' . $result['skipped'] . ' duplicate(s) skipped' : '')
            . ($result['failed'] ? ', ' . $result['failed'] . ' row(s) rejected — see the import log' : '') . '.';
        set_alert($result['created'] ? 'success' : 'warning', $msg);

        return redirect(admin_url('eptw/eptw_setup/import?batch=' . $batch));
    }

    public function import_rollback($id)
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }
        $result = $this->setup->rollback_import((int) $id);
        if (is_string($result)) {
            return $this->back('import', $result, '');
        }

        return $this->back('import', true, 'Import #' . (int) $id . ' reversed: ' . $result['removed'] . ' permit(s) removed'
            . ($result['kept'] ? ', ' . $result['kept'] . ' kept because they were changed after the import' : '') . '.');
    }

    /* ═══════════════════════════ Utilities ══════════════════════════ */

    private function permit_counts($column)
    {
        $out = [];
        foreach ($this->db->select($column . ' AS k, COUNT(*) AS n')->group_by($column)->get(db_prefix() . 'eptw_permits')->result() as $row) {
            $out[(int) $row->k] = (int) $row->n;
        }

        return $out;
    }
}
