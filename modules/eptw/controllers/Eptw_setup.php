<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * ePTW — configuration screens. Administrators only.
 * Reached at admin/eptw/eptw_setup/<method>.
 */
class Eptw_setup extends AdminController
{
    public function __construct()
    {
        parent::__construct();

        $this->load->model('eptw/eptw_model', 'setup');
        $this->load->model('eptw/eptw_permits_model', 'permits');

        if (!eptw_can_access()) {
            access_denied('ePTW');
        }
        // The register importer is also a coordinator's job; everything else is admin-only.
        if (!eptw_can('setup') && !(eptw_can('import') && in_array($this->router->fetch_method(), ['import', 'import_commit'], true))) {
            access_denied('ePTW setup');
        }
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
        return $this->back('types', $this->setup->reset_type_from_seed((int) $id) ? true : 'This type is not one of the shipped V3 templates.', 'Template restored from the V3 form.');
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
     * Step 1: upload the Excel/CSV register → show what was recognised.
     * Step 2 (import_commit): create the permits.
     */
    public function import()
    {
        require_once module_dir_path(EPTW_MODULE_NAME, 'libraries/Eptw_import.php');

        $data['title']   = 'Import the Excel register';
        $data['preview'] = null;

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
            $token = bin2hex(random_bytes(8));
            $dir   = FCPATH . 'uploads/eptw/import/';
            _maybe_create_upload_path($dir);
            file_put_contents($dir . $token . '.json', json_encode($table));

            $map      = Eptw_import::map_headers($table['headers']);
            $data['preview'] = [
                'token'    => $token,
                'headers'  => $table['headers'],
                'map'      => $map,
                'rows'     => array_slice($table['rows'], 0, 8),
                'count'    => count($table['rows']),
                'unmapped' => array_values(array_diff_key($table['headers'], $map)),
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

        $map      = Eptw_import::map_headers($table['headers']);
        $default_project = (int) $this->input->post('default_project');
        $me       = (int) eptw_me()['staff_id'];
        $created  = 0;
        $skipped  = 0;
        $errors   = [];
        $p        = db_prefix();

        foreach ($table['rows'] as $n => $row) {
            $r = [];
            foreach ($map as $i => $field) {
                $r[$field] = trim((string) ($row[$i] ?? ''));
            }
            $permit_no = (string) ($r['permit_no'] ?? '');
            if ($permit_no !== '' && $this->db->where('permit_no', $permit_no)->count_all_results($p . 'eptw_permits')) {
                $skipped++;
                continue;
            }
            $type_id = $this->setup->type_id_by_name($r['type'] ?? '');
            if (!$type_id) {
                $errors[] = 'Row ' . ($n + 1) . ': unknown permit type "' . ($r['type'] ?? '') . '"';
                continue;
            }
            $project_id = ($r['project'] ?? '') !== '' ? $this->setup->project_id_by_name($r['project']) : $default_project;
            if (!$project_id) {
                $errors[] = 'Row ' . ($n + 1) . ': no project';
                continue;
            }
            $area_id       = $this->setup->area_id_by_name($r['location'] ?? '', $project_id);
            $contractor_id = $this->setup->contractor_id_by_name($r['company'] ?? '');
            $type          = eptw_permit_type($type_id);
            $start         = Eptw_import::to_datetime($r['start_at'] ?? '') ?: date('Y-m-d H:i:00');
            $end           = Eptw_import::to_datetime($r['end_at'] ?? '') ?: date('Y-m-d H:i:00', strtotime($start) + (int) $type->default_validity_hours * 3600);
            $status        = Eptw_import::to_status($r['status'] ?? '') ?: ($permit_no !== '' ? 'closed' : 'draft');
            $closed_at     = Eptw_import::to_datetime($r['closed_at'] ?? '');
            if (in_array($status, eptw_working_statuses(), true) && strtotime($end) < time() && $closed_at) {
                $status = 'closed';
            }

            $hazards = array_values(array_filter(array_map('trim', preg_split('/[;,\n]+/', (string) ($r['hazards'] ?? '')))));
            $extra_notes = [];
            foreach (['gas_tester', 'o2', 'lel', 'h2s', 'approver', 'delay_reason', 'risk_level'] as $k) {
                if (($r[$k] ?? '') !== '') {
                    $extra_notes[] = ucfirst(str_replace('_', ' ', $k)) . ': ' . $r[$k];
                }
            }
            $remarks = trim((string) ($r['remarks'] ?? '') . (count($extra_notes) ? "\n" . implode("\n", $extra_notes) : ''));
            $risk    = mb_strtolower((string) ($r['risk_level'] ?? ''));
            if (!isset(eptw_risk_levels()[$risk])) {
                $risk = eptw_risk_level($type, $hazards, 'day', 0);
            }

            $data = [
                'permit_no'          => $permit_no !== '' ? substr($permit_no, 0, 60) : null,
                'serial_key'         => 'import',
                'source'             => 'import',
                'project_id'         => $project_id,
                'area_id'            => $area_id,
                'permit_type_id'     => $type_id,
                'contractor_id'      => $contractor_id,
                'subcontractor'      => substr(strtoupper((string) ($r['subcontractor'] ?? '')) === 'N/A' ? '' : (string) ($r['subcontractor'] ?? ''), 0, 150),
                'work_order'         => substr(strtoupper((string) ($r['work_order'] ?? '')) === 'N/A' ? '' : (string) ($r['work_order'] ?? ''), 0, 60),
                'work_title'         => substr((string) ($r['work_description'] ?? ($type->name . ' — imported')), 0, 191),
                'work_description'   => (string) ($r['work_description'] ?? ''),
                'location'           => substr((string) ($r['location'] ?? ''), 0, 191),
                'equipment_tag'      => substr((string) ($r['equipment_tag'] ?? ''), 0, 120),
                'shift'              => stripos((string) ($r['shift'] ?? ''), 'night') !== false ? 'night' : 'day',
                'start_at'           => $start,
                'end_at'             => $end,
                'original_end_at'    => $end,
                'engineer_id'        => $me,
                'permit_holder'      => substr((string) ($r['permit_holder'] ?? ''), 0, 150),
                'supervisor'         => substr((string) ($r['initiator'] ?? ''), 0, 150),
                'status'             => $status,
                'risk_level'         => $risk,
                'high_risk'          => ($risk === 'high' || $type->high_risk) ? 1 : 0,
                'simops_flag'        => in_array(mb_strtolower((string) ($r['simops'] ?? '')), ['yes', 'y', 'true', '1'], true) ? 1 : 0,
                'extension_count'    => (int) ($r['extension_count'] ?? 0),
                'hazards'            => json_encode([]),
                'extra_hazards'      => json_encode($hazards),
                'controls'           => json_encode([]),
                'extra'              => json_encode(array_filter([
                    'imported_initiator' => $r['initiator'] ?? '', 'imported_area_authority' => $r['area_authority'] ?? '', 'imported_issuer' => $r['issuer'] ?? '',
                    'imported_hse' => $r['hse'] ?? '', 'imported_controls' => $r['controls'] ?? '',
                ])),
                'ppe'                => json_encode(array_values(array_filter(array_map('trim', preg_split('/[;,\n]+/', (string) ($r['ppe'] ?? '')))))),
                'ra_ref'             => substr((string) ($r['ra_ref'] ?? ''), 0, 80),
                'isolation_required' => in_array(mb_strtolower((string) ($r['isolation_required'] ?? '')), ['yes', 'y', 'true', '1'], true) ? 1 : 0,
                'isolation_type'     => substr((string) ($r['isolation_type'] ?? ''), 0, 80),
                'isolation_cert_no'  => substr((string) ($r['isolation_cert_no'] ?? ''), 0, 80),
                'loto_applied'       => in_array(mb_strtolower((string) ($r['loto'] ?? '')), ['yes', 'y', 'true', '1'], true) ? 1 : 0,
                'gas_test_required'  => in_array(mb_strtolower((string) ($r['gas_test'] ?? '')), ['yes', 'y', 'true', '1'], true) ? 1 : (int) $type->gas_test_required,
                'weather'            => substr((string) ($r['weather'] ?? ''), 0, 80),
                'remarks'            => $remarks,
                'issued_at'          => $permit_no !== '' ? $start : null,
                'issued_by'          => $permit_no !== '' ? $me : 0,
                'activated_at'       => in_array($status, array_merge(eptw_working_statuses(), eptw_closed_statuses()), true) ? $start : null,
                'closed_at'          => in_array($status, ['closed', 'closed_docs_pending', 'archived'], true) ? ($closed_at ?: $end) : null,
                'closure'            => json_encode([
                    'work_completed' => in_array(mb_strtolower((string) ($r['work_completed'] ?? '')), ['yes', 'y', 'true', '1'], true) ? 1 : 0,
                    'area_restored' => in_array(mb_strtolower((string) ($r['area_restored'] ?? '')), ['yes', 'y', 'true', '1'], true) ? 1 : 0,
                    'isolation_removed' => in_array(mb_strtolower((string) ($r['isolation_removed'] ?? '')), ['yes', 'y', 'true', '1'], true) ? 1 : 0,
                    'closed_by_name' => (string) ($r['closed_by'] ?? ''),
                ]),
                'docs_complete'      => 1,
                'expiry_notified'    => 1,
                'created_by'         => $me,
                'created_at'         => $start,
                'updated_at'         => date('Y-m-d H:i:s'),
            ];
            $this->db->insert($p . 'eptw_permits', $data);
            $id = (int) $this->db->insert_id();
            $this->permits->event($id, 'imported', '', $status, 'Imported from the Excel register' . ($permit_no !== '' ? ' as ' . $permit_no : ''));
            $created++;
        }

        $msg = $created . ' permit(s) imported' . ($skipped ? ', ' . $skipped . ' skipped (permit number already exists)' : '') . '.';
        if (count($errors)) {
            $msg .= ' ' . count($errors) . ' row(s) could not be read: ' . implode('; ', array_slice($errors, 0, 5)) . (count($errors) > 5 ? '…' : '');
        }
        set_alert($created ? 'success' : 'warning', $msg);

        return redirect(admin_url('eptw/register?view=&q='));
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
