<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * ePTW — configuration: projects, areas, contractors, permit templates, the
 * team, SIMOPS rules and settings.
 */
class Eptw_model extends App_Model
{
    /* ═══════════════════════════ Projects ═══════════════════════════ */

    public function projects($only_active = false)
    {
        if ($only_active) {
            $this->db->where('active', 1);
        }

        return $this->db->order_by('name', 'asc')->get(db_prefix() . 'eptw_projects')->result();
    }

    public function project($id)
    {
        return $this->db->where('id', (int) $id)->get(db_prefix() . 'eptw_projects')->row();
    }

    public function save_project(array $in, $id = 0)
    {
        $name = trim((string) ($in['name'] ?? ''));
        if ($name === '') {
            return 'Give the project a name.';
        }
        $code = strtoupper(trim((string) ($in['code'] ?? '')));
        if ($code === '') {
            $code = eptw_code_from_name($name, 6);
        }
        $code = substr(preg_replace('/[^A-Z0-9\-]/', '', $code), 0, 20);

        $dupe = $this->db->where('code', $code)->where('id !=', (int) $id)->get(db_prefix() . 'eptw_projects')->row();
        if ($dupe) {
            return 'Project code "' . $code . '" is already used by ' . $dupe->name . '.';
        }

        $data = [
            'code'        => $code,
            'name'        => substr($name, 0, 150),
            'client_name' => substr(trim((string) ($in['client_name'] ?? '')), 0, 150),
            'description' => trim((string) ($in['description'] ?? '')),
            'camera_mode' => in_array($in['camera_mode'] ?? '', ['inherit', 'allowed', 'restricted', 'disabled'], true) ? $in['camera_mode'] : 'inherit',
            'max_validity_hours' => max(0, min(720, (int) ($in['max_validity_hours'] ?? 0))),
            'active'      => !empty($in['active']) ? 1 : 0,
        ];

        if ($id) {
            $this->db->where('id', (int) $id)->update(db_prefix() . 'eptw_projects', $data);

            return (int) $id;
        }
        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert(db_prefix() . 'eptw_projects', $data);

        return (int) $this->db->insert_id();
    }

    public function delete_project($id)
    {
        $used = $this->db->where('project_id', (int) $id)->count_all_results(db_prefix() . 'eptw_permits');
        if ($used) {
            return 'This project has ' . $used . ' permit(s). Deactivate it instead of deleting.';
        }
        $this->db->where('project_id', (int) $id)->delete(db_prefix() . 'eptw_areas');
        $this->db->where('id', (int) $id)->delete(db_prefix() . 'eptw_projects');

        return true;
    }

    /* ═══════════════════════════ Areas ══════════════════════════════ */

    public function areas($project_id = null)
    {
        $this->db->select('a.*, p.name AS project_name')
            ->from(db_prefix() . 'eptw_areas a')
            ->join(db_prefix() . 'eptw_projects p', 'p.id = a.project_id', 'left');
        if ($project_id !== null) {
            $this->db->where('a.project_id', (int) $project_id);
        }

        return $this->db->order_by('p.name', 'asc')->order_by('a.name', 'asc')->get()->result();
    }

    public function area($id)
    {
        return $this->db->where('id', (int) $id)->get(db_prefix() . 'eptw_areas')->row();
    }

    public function save_area(array $in, $id = 0)
    {
        $name = trim((string) ($in['name'] ?? ''));
        if ($name === '') {
            return 'Give the area a name.';
        }
        $code = strtoupper(trim((string) ($in['code'] ?? '')));
        if ($code === '') {
            $code = eptw_code_from_name($name, 6);
        }
        // Areas belong to a project; a shared area is a deliberate admin choice.
        $shared     = !empty($in['shared']) && eptw_opt('eptw_shared_areas') === '1';
        $project_id = $shared ? 0 : (int) ($in['project_id'] ?? 0);
        if (!$shared && !$project_id) {
            return 'Choose the project this area belongs to.';
        }
        $dupe = $this->db->where('project_id', $project_id)->where('LOWER(name)', mb_strtolower($name))->where('id !=', (int) $id)->get(db_prefix() . 'eptw_areas')->row();
        if ($dupe) {
            return 'This project already has an area called "' . $dupe->name . '".';
        }
        $data = [
            'project_id'  => $project_id,
            'code'        => substr(preg_replace('/[^A-Z0-9\-]/', '', $code), 0, 20),
            'name'        => substr($name, 0, 150),
            'description' => trim((string) ($in['description'] ?? '')),
            'active'      => !empty($in['active']) ? 1 : 0,
        ];
        if ($id) {
            $this->db->where('id', (int) $id)->update(db_prefix() . 'eptw_areas', $data);

            return (int) $id;
        }
        $this->db->insert(db_prefix() . 'eptw_areas', $data);

        return (int) $this->db->insert_id();
    }

    public function delete_area($id)
    {
        $used = $this->db->where('area_id', (int) $id)->count_all_results(db_prefix() . 'eptw_permits');
        if ($used) {
            return 'This area has ' . $used . ' permit(s). Deactivate it instead of deleting.';
        }
        $this->db->where('id', (int) $id)->delete(db_prefix() . 'eptw_areas');

        return true;
    }

    /* ═══════════════════════════ Contractors ════════════════════════ */

    public function contractors()
    {
        return $this->db->order_by('name', 'asc')->get(db_prefix() . 'eptw_contractors')->result();
    }

    public function contractor($id)
    {
        return $this->db->where('id', (int) $id)->get(db_prefix() . 'eptw_contractors')->row();
    }

    public function save_contractor(array $in, $id = 0)
    {
        $name = trim((string) ($in['name'] ?? ''));
        if ($name === '') {
            return 'Give the contractor a name.';
        }
        $data = [
            'code'         => substr(strtoupper(trim((string) ($in['code'] ?? ''))), 0, 20),
            'name'         => substr($name, 0, 150),
            'contact_name' => substr(trim((string) ($in['contact_name'] ?? '')), 0, 150),
            'phone'        => substr(trim((string) ($in['phone'] ?? '')), 0, 40),
            'email'        => substr(trim((string) ($in['email'] ?? '')), 0, 150),
            'active'       => !empty($in['active']) ? 1 : 0,
        ];
        if ($id) {
            $this->db->where('id', (int) $id)->update(db_prefix() . 'eptw_contractors', $data);

            return (int) $id;
        }
        $this->db->insert(db_prefix() . 'eptw_contractors', $data);

        return (int) $this->db->insert_id();
    }

    public function delete_contractor($id)
    {
        $used = $this->db->where('contractor_id', (int) $id)->count_all_results(db_prefix() . 'eptw_permits');
        if ($used) {
            return 'This contractor has ' . $used . ' permit(s). Deactivate it instead of deleting.';
        }
        $this->db->where('id', (int) $id)->delete(db_prefix() . 'eptw_contractors');

        return true;
    }

    /** Find-or-create by name — used by the register importer. */
    public function contractor_id_by_name($name)
    {
        $name = trim((string) $name);
        if ($name === '' || strtoupper($name) === 'N/A') {
            return 0;
        }
        $row = $this->db->where('LOWER(name)', mb_strtolower($name))->get(db_prefix() . 'eptw_contractors')->row();
        if ($row) {
            return (int) $row->id;
        }
        $this->db->insert(db_prefix() . 'eptw_contractors', ['name' => substr($name, 0, 150), 'code' => eptw_code_from_name($name, 8), 'active' => 1]);
        $cache = &eptw_cache();
        unset($cache['contractors_1'], $cache['contractors_0']);

        return (int) $this->db->insert_id();
    }

    public function project_id_by_name($name)
    {
        $name = trim((string) $name);
        if ($name === '') {
            return 0;
        }
        $row = $this->db->group_start()->where('LOWER(name)', mb_strtolower($name))->or_where('code', strtoupper($name))->group_end()
            ->get(db_prefix() . 'eptw_projects')->row();
        if ($row) {
            return (int) $row->id;
        }
        $code = eptw_code_from_name($name, 6);
        while ($this->db->where('code', $code)->count_all_results(db_prefix() . 'eptw_projects')) {
            $code = substr($code, 0, 5) . rand(1, 9);
        }
        $this->db->insert(db_prefix() . 'eptw_projects', ['code' => $code, 'name' => substr($name, 0, 150), 'active' => 1, 'created_at' => date('Y-m-d H:i:s')]);
        $cache = &eptw_cache();
        unset($cache['projects_1'], $cache['projects_0']);

        return (int) $this->db->insert_id();
    }

    public function area_id_by_name($name, $project_id)
    {
        $name = trim((string) $name);
        if ($name === '') {
            return 0;
        }
        $this->db->where('LOWER(name)', mb_strtolower($name));
        if (eptw_opt('eptw_shared_areas') === '1') {
            $this->db->group_start()->where('project_id', (int) $project_id)->or_where('project_id', 0)->group_end();
        } else {
            $this->db->where('project_id', (int) $project_id);
        }
        $row = $this->db->order_by('project_id', 'desc')->get(db_prefix() . 'eptw_areas')->row();
        if ($row) {
            return (int) $row->id;
        }
        $this->db->insert(db_prefix() . 'eptw_areas', ['project_id' => (int) $project_id, 'code' => eptw_code_from_name($name, 6), 'name' => substr($name, 0, 150), 'active' => 1]);

        return (int) $this->db->insert_id();
    }

    public function type_id_by_name($name)
    {
        $name = mb_strtolower(trim((string) $name));
        if ($name === '') {
            return 0;
        }
        $aliases = [
            'hydrotest' => 'HT', 'hydro test' => 'HT', 'hydrostatic' => 'HT', 'confined space' => 'CSE', 'confined space entry' => 'CSE',
            'height' => 'WAH', 'work at height' => 'WAH', 'working at height' => 'WAH', 'electrical' => 'EL', 'electrical work' => 'EL',
            'hot work' => 'HW', 'cold work' => 'CW', 'lifting' => 'LF', 'excavation' => 'EX', 'loto' => 'LOTO', 'isolation' => 'LOTO',
            'radiography' => 'RT', 'manlift' => 'MEWP', 'mewp' => 'MEWP', 'scissor lift' => 'SL', 'piling' => 'PL',
            'pressure test' => 'PT', 'pressure testing' => 'PT', 'simops' => 'SIM', 'night work' => 'NW', 'heat stress' => 'HS',
        ];
        foreach (eptw_permit_types(false) as $type) {
            if (mb_strtolower($type->name) === $name || mb_strtolower($type->code) === $name) {
                return (int) $type->id;
            }
        }
        foreach ($aliases as $alias => $code) {
            if (strpos($name, $alias) !== false) {
                foreach (eptw_permit_types(false) as $type) {
                    if ($type->code === $code) {
                        return (int) $type->id;
                    }
                }
            }
        }
        foreach (eptw_permit_types(false) as $type) {
            if (strpos(mb_strtolower($type->name), $name) !== false || strpos($name, mb_strtolower(str_replace(' Permit', '', $type->name))) !== false) {
                return (int) $type->id;
            }
        }

        return 0;
    }

    /* ═══════════════════════════ Register import ════════════════════ */

    private function import_yes($v)
    {
        return in_array(mb_strtolower(trim((string) $v)), ['yes', 'y', 'true', '1'], true);
    }

    /** Does a project / contractor / area of that name exist (no creation)? */
    private function import_lookup($kind, $name, $project_id = 0)
    {
        $name = trim((string) $name);
        if ($name === '' || strtoupper($name) === 'N/A') {
            return 0;
        }
        $p = db_prefix();
        switch ($kind) {
            case 'project':
                $row = $this->db->group_start()->where('LOWER(name)', mb_strtolower($name))->or_where('code', strtoupper($name))->group_end()->get($p . 'eptw_projects')->row();
                break;
            case 'contractor':
                $row = $this->db->where('LOWER(name)', mb_strtolower($name))->get($p . 'eptw_contractors')->row();
                break;
            default:
                $this->db->where('LOWER(name)', mb_strtolower($name));
                if (eptw_opt('eptw_shared_areas') === '1') {
                    $this->db->group_start()->where('project_id', (int) $project_id)->or_where('project_id', 0)->group_end();
                } else {
                    $this->db->where('project_id', (int) $project_id);
                }
                $row = $this->db->get($p . 'eptw_areas')->row();
        }

        return $row ? (int) $row->id : 0;
    }

    /**
     * Validate — and unless $dry, import — every row of a parsed register.
     * One pass for both, so the dry-run preview says exactly what the import
     * will do: per row ok / warning / duplicate / error, with the reasons.
     *
     * @return array{rows:array, created:int, skipped:int, failed:int, warnings:int}
     */
    public function import_rows(array $table, array $map, $default_project, $dry = true, $batch_id = 0)
    {
        require_once module_dir_path(EPTW_MODULE_NAME, 'libraries/Eptw_import.php');
        $p      = db_prefix();
        $me     = (int) eptw_me()['staff_id'];
        $seen   = [];
        $report = [];
        $tally  = ['created' => 0, 'skipped' => 0, 'failed' => 0, 'warnings' => 0];

        foreach ($table['rows'] as $n => $row) {
            $line = $n + 1;
            $r    = [];
            foreach ($map as $i => $field) {
                $r[$field] = trim((string) ($row[$i] ?? ''));
            }
            if (!count(array_filter($r, function ($v) { return $v !== ''; }))) {
                continue; // blank line
            }
            $msgs      = [];
            $warn      = [];
            $permit_no = (string) ($r['permit_no'] ?? '');

            if ($permit_no !== '' && isset($seen[mb_strtolower($permit_no)])) {
                $report[] = ['row' => $line, 'permit_no' => $permit_no, 'result' => 'duplicate', 'messages' => ['Same permit number as row ' . $seen[mb_strtolower($permit_no)] . ' in this file — skipped']];
                $tally['skipped']++;
                continue;
            }
            if ($permit_no !== '') {
                $seen[mb_strtolower($permit_no)] = $line;
            }
            if ($permit_no !== '' && $this->db->where('permit_no', $permit_no)->count_all_results($p . 'eptw_permits')) {
                $report[] = ['row' => $line, 'permit_no' => $permit_no, 'result' => 'duplicate', 'messages' => ['Permit number already in the register — skipped']];
                $tally['skipped']++;
                continue;
            }

            $type_id = $this->type_id_by_name($r['type'] ?? '');
            if (!$type_id) {
                $msgs[] = 'Unknown permit type "' . ($r['type'] ?? '') . '"';
            }
            $project_name = (string) ($r['project'] ?? '');
            $project_id   = $project_name !== '' ? $this->import_lookup('project', $project_name) : (int) $default_project;
            if ($project_name !== '' && !$project_id) {
                $warn[] = 'New project "' . $project_name . '" will be created';
            }
            if ($project_name === '' && !$project_id) {
                $msgs[] = 'No project, and no default project chosen';
            }
            $start_raw = (string) ($r['start_at'] ?? '');
            $start     = Eptw_import::to_datetime($start_raw);
            if ($start_raw !== '' && !$start) {
                $msgs[] = 'Start date "' . $start_raw . '" is not a date';
            } elseif ($start_raw === '') {
                $warn[] = 'No start date — the import date is used';
            }
            $end_raw = (string) ($r['end_at'] ?? '');
            $end     = Eptw_import::to_datetime($end_raw);
            if ($end_raw !== '' && !$end) {
                $msgs[] = 'End date "' . $end_raw . '" is not a date';
            }
            if ($start && $end && strtotime($end) <= strtotime($start)) {
                $msgs[] = 'End date is before the start date';
            }
            $status_raw = (string) ($r['status'] ?? '');
            $status     = Eptw_import::to_status($status_raw);
            if ($status_raw !== '' && $status === '') {
                $warn[] = 'Status "' . $status_raw . '" not recognised — imported as ' . ($permit_no !== '' ? 'Closed' : 'Draft');
            }
            if (($r['company'] ?? '') !== '' && strtoupper($r['company']) !== 'N/A' && !$this->import_lookup('contractor', $r['company'])) {
                $warn[] = 'New contractor "' . $r['company'] . '" will be created';
            }
            if (($r['location'] ?? '') !== '' && $project_id && !$this->import_lookup('area', $r['location'], $project_id)) {
                $warn[] = 'New area "' . $r['location'] . '" will be created';
            }

            if (count($msgs)) {
                $report[] = ['row' => $line, 'permit_no' => $permit_no, 'result' => 'error', 'messages' => array_merge($msgs, $warn)];
                $tally['failed']++;
                continue;
            }
            if (count($warn)) {
                $tally['warnings']++;
            }
            if ($dry) {
                $report[] = ['row' => $line, 'permit_no' => $permit_no, 'result' => count($warn) ? 'warning' : 'ok', 'messages' => $warn];
                $tally['created']++;
                continue;
            }

            // ── Write ──
            $project_id    = $project_name !== '' ? $this->project_id_by_name($project_name) : (int) $default_project;
            $area_id       = $this->area_id_by_name($r['location'] ?? '', $project_id);
            $contractor_id = $this->contractor_id_by_name($r['company'] ?? '');
            $type          = eptw_permit_type($type_id);
            $start         = $start ?: date('Y-m-d H:i:00');
            $end           = $end ?: date('Y-m-d H:i:00', strtotime($start) + (int) $type->default_validity_hours * 3600);
            $status        = $status ?: ($permit_no !== '' ? 'closed' : 'draft');
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
            $assess  = eptw_risk_assess($type, $hazards, 'day', 0);
            if (!isset(eptw_risk_levels()[$risk])) {
                $risk = $assess['level'];
            }
            $iso = $this->import_yes($r['isolation_required'] ?? '');

            $data = [
                'permit_no'            => $permit_no !== '' ? substr($permit_no, 0, 60) : null,
                'serial_key'           => 'import',
                'source'               => 'import',
                'import_batch'         => (int) $batch_id,
                'project_id'           => $project_id,
                'area_id'              => $area_id,
                'permit_type_id'       => $type_id,
                'contractor_id'        => $contractor_id,
                'subcontractor'        => substr(strtoupper((string) ($r['subcontractor'] ?? '')) === 'N/A' ? '' : (string) ($r['subcontractor'] ?? ''), 0, 150),
                'work_order'           => substr(strtoupper((string) ($r['work_order'] ?? '')) === 'N/A' ? '' : (string) ($r['work_order'] ?? ''), 0, 60),
                'work_title'           => substr((string) ($r['work_description'] ?? ($type->name . ' — imported')), 0, 191),
                'work_description'     => (string) ($r['work_description'] ?? ''),
                'location'             => substr((string) ($r['location'] ?? ''), 0, 191),
                'equipment_tag'        => substr((string) ($r['equipment_tag'] ?? ''), 0, 120),
                'shift'                => stripos((string) ($r['shift'] ?? ''), 'night') !== false ? 'night' : 'day',
                'start_at'             => $start,
                'end_at'               => $end,
                'original_end_at'      => $end,
                'engineer_id'          => $me,
                'permit_holder'        => substr((string) ($r['permit_holder'] ?? ''), 0, 150),
                'supervisor'           => substr((string) ($r['initiator'] ?? ''), 0, 150),
                'status'               => $status,
                'risk_level'           => $risk,
                'high_risk'            => ($risk === 'high' || $type->high_risk) ? 1 : 0,
                'risk_reasons'         => json_encode($assess['reasons']),
                'simops_flag'          => $this->import_yes($r['simops'] ?? '') ? 1 : 0,
                'extension_count'      => (int) ($r['extension_count'] ?? 0),
                'hazards'              => json_encode([]),
                'extra_hazards'        => json_encode($hazards),
                'controls'             => json_encode([]),
                'extra'                => json_encode(array_filter([
                    'imported_initiator' => $r['initiator'] ?? '', 'imported_area_authority' => $r['area_authority'] ?? '', 'imported_issuer' => $r['issuer'] ?? '',
                    'imported_hse' => $r['hse'] ?? '', 'imported_controls' => $r['controls'] ?? '',
                ])),
                'ppe'                  => json_encode(array_values(array_filter(array_map('trim', preg_split('/[;,\n]+/', (string) ($r['ppe'] ?? '')))))),
                'ra_ref'               => substr((string) ($r['ra_ref'] ?? ''), 0, 80),
                'isolation_required'   => $iso ? 1 : 0,
                'isolation_applicable' => $iso ? 'yes' : '',
                'isolation_type'       => substr((string) ($r['isolation_type'] ?? ''), 0, 80),
                'isolation_cert_no'    => substr((string) ($r['isolation_cert_no'] ?? ''), 0, 80),
                'loto_applied'         => $this->import_yes($r['loto'] ?? '') ? 1 : 0,
                'gas_test_required'    => $this->import_yes($r['gas_test'] ?? '') ? 1 : (int) $type->gas_test_required,
                'weather'              => substr((string) ($r['weather'] ?? ''), 0, 80),
                'remarks'              => $remarks,
                'issued_at'            => $permit_no !== '' ? $start : null,
                'issued_by'            => $permit_no !== '' ? $me : 0,
                'activated_at'         => in_array($status, array_merge(eptw_working_statuses(), eptw_closed_statuses()), true) ? $start : null,
                'closed_at'            => in_array($status, ['closed', 'closed_docs_pending', 'archived'], true) ? ($closed_at ?: $end) : null,
                'closure'              => json_encode([
                    'work_completed'    => ['v' => $this->import_yes($r['work_completed'] ?? '') ? 'yes' : '', 'by' => (string) ($r['closed_by'] ?? ''), 'at' => '', 'r' => ''],
                    'area_restored'     => ['v' => $this->import_yes($r['area_restored'] ?? '') ? 'yes' : '', 'by' => (string) ($r['closed_by'] ?? ''), 'at' => '', 'r' => ''],
                    'isolation_removed' => ['v' => $this->import_yes($r['isolation_removed'] ?? '') ? 'yes' : '', 'by' => (string) ($r['closed_by'] ?? ''), 'at' => '', 'r' => ''],
                    'closed_by_name'    => (string) ($r['closed_by'] ?? ''),
                ]),
                'docs_complete'        => 1,
                'expiry_notified'      => 1,
                'created_by'           => $me,
                'created_at'           => $start,
                'updated_at'           => date('Y-m-d H:i:s'),
            ];
            $this->db->insert($p . 'eptw_permits', $data);
            $id = (int) $this->db->insert_id();
            $this->db->insert($p . 'eptw_events', [
                'permit_id' => $id, 'staff_id' => $me, 'event' => 'imported', 'from_status' => '', 'to_status' => $status,
                'note' => 'Imported from the Excel register (batch #' . (int) $batch_id . ', row ' . $line . ')' . ($permit_no !== '' ? ' as ' . $permit_no : ''), 'created_at' => date('Y-m-d H:i:s'),
            ]);
            $report[] = ['row' => $line, 'permit_no' => $permit_no, 'result' => count($warn) ? 'warning' : 'ok', 'messages' => $warn, 'permit_id' => $id];
            $tally['created']++;
        }

        return ['rows' => $report] + $tally;
    }

    public function imports($limit = 20)
    {
        return $this->db->select('i.*, CONCAT(s.firstname, " ", s.lastname) AS staff_name, (SELECT COUNT(*) FROM ' . db_prefix() . 'eptw_permits x WHERE x.import_batch = i.id) AS live_rows')
            ->from(db_prefix() . 'eptw_imports i')->join(db_prefix() . 'staff s', 's.staffid = i.created_by', 'left')
            ->order_by('i.id', 'desc')->limit((int) $limit)->get()->result();
    }

    public function import_batch($id)
    {
        return $this->db->where('id', (int) $id)->get(db_prefix() . 'eptw_imports')->row();
    }

    /**
     * Reverse an import: removes the permits of the batch that nobody has
     * touched since (no event besides the import, no document). Touched
     * permits are kept and reported.
     *
     * @return array{removed:int, kept:int}|string
     */
    public function rollback_import($id)
    {
        $batch = $this->import_batch($id);
        if (!$batch) {
            return 'Import not found.';
        }
        if ($batch->rolled_back_at) {
            return 'This import was already reversed.';
        }
        $p       = db_prefix();
        $removed = 0;
        $kept    = 0;
        foreach ($this->db->select('id')->where('import_batch', (int) $id)->get($p . 'eptw_permits')->result() as $row) {
            $events = $this->db->where('permit_id', (int) $row->id)->where('event !=', 'imported')->count_all_results($p . 'eptw_events');
            $docs   = $this->db->where('permit_id', (int) $row->id)->count_all_results($p . 'eptw_documents');
            if ($events || $docs) {
                $kept++;
                continue;
            }
            foreach (['eptw_events', 'eptw_approvals', 'eptw_gas_tests', 'eptw_extensions', 'eptw_revalidations'] as $table) {
                $this->db->where('permit_id', (int) $row->id)->delete($p . $table);
            }
            $this->db->where('id', (int) $row->id)->delete($p . 'eptw_permits');
            $removed++;
        }
        $this->db->where('id', (int) $id)->update($p . 'eptw_imports', ['rolled_back_at' => date('Y-m-d H:i:s'), 'rolled_back_by' => (int) eptw_me()['staff_id']]);
        log_activity('ePTW import #' . (int) $id . ' reversed: ' . $removed . ' permit(s) removed, ' . $kept . ' kept (changed since import)');

        return ['removed' => $removed, 'kept' => $kept];
    }

    /* ═══════════════════════════ Permit types ═══════════════════════ */

    public function types()
    {
        $rows = $this->db->order_by('sort_order', 'asc')->get(db_prefix() . 'eptw_permit_types')->result();
        foreach ($rows as $row) {
            eptw_hydrate_type($row);
        }

        return $rows;
    }

    public function type($id)
    {
        $row = $this->db->where('id', (int) $id)->get(db_prefix() . 'eptw_permit_types')->row();

        return $row ? eptw_hydrate_type($row) : null;
    }

    /**
     * The template editor posts hazards and control sections as plain text
     * (one item per line) — far friendlier than JSON for a safety manager.
     */
    public function save_type(array $in, $id = 0)
    {
        $name = trim((string) ($in['name'] ?? ''));
        $code = strtoupper(preg_replace('/[^A-Z0-9]/', '', strtoupper(trim((string) ($in['code'] ?? '')))));
        if ($name === '' || $code === '') {
            return 'A permit type needs a name and a short code.';
        }
        $dupe = $this->db->where('code', $code)->where('id !=', (int) $id)->get(db_prefix() . 'eptw_permit_types')->row();
        if ($dupe) {
            return 'Code "' . $code . '" is already used by ' . $dupe->name . '.';
        }
        if ((int) ($in['max_validity_hours'] ?? 0) > 0 && (int) ($in['default_validity_hours'] ?? 12) > (int) $in['max_validity_hours']) {
            return 'The default validity cannot be longer than the maximum validity.';
        }

        $lines = function ($text) {
            $out = [];
            foreach (preg_split('/\r\n|\r|\n/', (string) $text) as $line) {
                $line = trim($line);
                if ($line !== '') {
                    $out[] = substr($line, 0, 150);
                }
            }

            return array_values(array_unique($out));
        };

        $controls = [];
        $titles   = (array) ($in['control_title'] ?? []);
        $items    = (array) ($in['control_items'] ?? []);
        foreach ($titles as $i => $title) {
            $title = trim((string) $title);
            $list  = $lines($items[$i] ?? '');
            if ($title === '' && !count($list)) {
                continue;
            }
            $controls[] = ['title' => $title !== '' ? $title : 'Control measures', 'items' => $list];
        }

        // Extra fields: key|label|type|options (options comma separated)
        $extra = [];
        foreach ($lines($in['extra_fields_text'] ?? '') as $line) {
            $parts = array_map('trim', explode('|', $line));
            $label = $parts[0] ?? '';
            $ftype = strtolower($parts[1] ?? 'text');
            if ($label === '') {
                continue;
            }
            if (!in_array($ftype, ['text', 'number', 'yesno', 'select', 'checkboxes', 'textarea', 'detect', 'personnel'], true)) {
                $ftype = 'text';
            }
            $field = ['key' => substr(strtolower(preg_replace('/[^a-z0-9]+/i', '_', $label)), 0, 40), 'label' => $label, 'type' => $ftype === 'personnel' ? 'text' : $ftype];
            if ($ftype === 'personnel') {
                $field['group'] = 'personnel';
                $field['key']   = 'p_' . $field['key'];
            }
            if (in_array($ftype, ['select', 'checkboxes', 'detect'], true)) {
                $field['options'] = array_values(array_filter(array_map('trim', explode(',', $parts[2] ?? ''))));
            }
            $extra[] = $field;
        }

        $approvals = array_values(array_intersect((array) ($in['approvals'] ?? []), ['area_authority', 'hse', 'manager', 'coordinator']));
        if (!in_array('coordinator', $approvals, true)) {
            $approvals[] = 'coordinator';
        }

        $data = [
            'code'                   => substr($code, 0, 10),
            'name'                   => substr($name, 0, 120),
            'description'            => substr(trim((string) ($in['description'] ?? '')), 0, 255),
            'icon'                   => substr(trim((string) ($in['icon'] ?? 'fa-solid fa-file-shield')), 0, 60) ?: 'fa-solid fa-file-shield',
            'color'                  => preg_match('/^#[0-9a-f]{6}$/i', (string) ($in['color'] ?? '')) ? $in['color'] : '#2563eb',
            'high_risk'              => !empty($in['high_risk']) ? 1 : 0,
            'gas_test_required'      => !empty($in['gas_test_required']) ? 1 : 0,
            'isolation_required'     => ($in['isolation_rule'] ?? '') === 'always' ? 1 : 0,
            'default_validity_hours' => max(1, (int) ($in['default_validity_hours'] ?? 12)),
            'max_validity_hours'     => max(0, min(720, (int) ($in['max_validity_hours'] ?? 0))),
            'expiry_warning_hours'   => max(0, min(72, (int) ($in['expiry_warning_hours'] ?? 0))),
            'isolation_rule'         => isset(eptw_isolation_rules()[$in['isolation_rule'] ?? '']) ? $in['isolation_rule'] : 'conditional',
            'hazards'                => json_encode($lines($in['hazards_text'] ?? '')),
            'controls'               => json_encode($controls),
            'extra_fields'           => json_encode($extra),
            'ppe'                    => json_encode($lines($in['ppe_text'] ?? '')),
            'approvals'              => json_encode($approvals),
            'keywords'               => json_encode(array_values(array_filter(array_map('trim', explode(',', (string) ($in['keywords_text'] ?? '')))))),
            'sort_order'             => (int) ($in['sort_order'] ?? 0),
            'active'                 => !empty($in['active']) ? 1 : 0,
        ];

        if ($id) {
            $this->db->where('id', (int) $id)->update(db_prefix() . 'eptw_permit_types', $data);

            return (int) $id;
        }
        $this->db->insert(db_prefix() . 'eptw_permit_types', $data);

        return (int) $this->db->insert_id();
    }

    public function delete_type($id)
    {
        $used = $this->db->where('permit_type_id', (int) $id)->count_all_results(db_prefix() . 'eptw_permits');
        if ($used) {
            return 'This permit type is used by ' . $used . ' permit(s). Deactivate it instead.';
        }
        $this->db->where('id', (int) $id)->delete(db_prefix() . 'eptw_permit_types');

        return true;
    }

    /** Re-seed a template from the shipped V3 form (keeps the id). */
    public function reset_type_from_seed($id)
    {
        $type = $this->type($id);
        if (!$type) {
            return false;
        }
        require_once module_dir_path(EPTW_MODULE_NAME, 'data/permit_types_seed.php');
        foreach (eptw_permit_types_seed() as $seed) {
            if ($seed['code'] === $type->code) {
                $this->db->where('id', (int) $id)->update(db_prefix() . 'eptw_permit_types', [
                    'hazards'      => json_encode($seed['hazards']),
                    'controls'     => json_encode($seed['controls']),
                    'extra_fields' => json_encode($seed['extra_fields']),
                    'ppe'          => json_encode($seed['ppe']),
                    'approvals'    => json_encode($seed['approvals']),
                    'keywords'     => json_encode($seed['keywords']),
                ]);

                return true;
            }
        }

        return false;
    }

    /* ═══════════════════════════ Team ═══════════════════════════════ */

    public function team()
    {
        return $this->db->select('t.*, s.firstname, s.lastname, s.email, s.active AS staff_active')
            ->from(db_prefix() . 'eptw_team t')
            ->join(db_prefix() . 'staff s', 's.staffid = t.staff_id', 'left')
            ->order_by('t.role', 'asc')->order_by('s.firstname', 'asc')
            ->get()->result();
    }

    public function save_member(array $in)
    {
        $staff_id = (int) ($in['staff_id'] ?? 0);
        $role     = (string) ($in['role'] ?? '');
        if (!$staff_id || !isset(eptw_roles()[$role])) {
            return 'Pick a staff member and a role.';
        }
        $projects = array_values(array_filter(array_map('intval', (array) ($in['project_ids'] ?? []))));
        $data     = [
            'staff_id'      => $staff_id,
            'role'          => $role,
            'project_ids'   => json_encode($projects),
            'contractor_id' => (int) ($in['contractor_id'] ?? 0),
            'phone'         => substr(trim((string) ($in['phone'] ?? '')), 0, 40),
            'active'        => !empty($in['active']) ? 1 : 0,
        ];
        // First time on the team → the menus their role needs. Never overwrites
        // what an administrator has already set on the staff profile.
        eptw_grant_role_permissions($staff_id, $role);

        $existing = $this->db->where('staff_id', $staff_id)->get(db_prefix() . 'eptw_team')->row();
        if ($existing) {
            $this->db->where('id', $existing->id)->update(db_prefix() . 'eptw_team', $data);

            return (int) $existing->id;
        }
        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert(db_prefix() . 'eptw_team', $data);

        return (int) $this->db->insert_id();
    }

    public function remove_member($id)
    {
        $this->db->where('id', (int) $id)->delete(db_prefix() . 'eptw_team');
    }

    /* ═══════════════════════════ SIMOPS rules ═══════════════════════ */

    public function simops_rules($only_active = false)
    {
        if ($only_active) {
            $this->db->where('active', 1);
        }

        return $this->db->order_by('severity', 'asc')->order_by('type_a', 'asc')->get(db_prefix() . 'eptw_simops_rules')->result();
    }

    public function save_rule(array $in, $id = 0)
    {
        $a = strtoupper(trim((string) ($in['type_a'] ?? '')));
        $b = strtoupper(trim((string) ($in['type_b'] ?? '')));
        if ($a === '' || $b === '') {
            return 'Pick both permit types.';
        }
        $data = [
            'type_a'      => $a,
            'type_b'      => $b,
            'severity'    => ($in['severity'] ?? '') === 'block' ? 'block' : 'warn',
            'description' => substr(trim((string) ($in['description'] ?? '')), 0, 255),
            'active'      => !empty($in['active']) ? 1 : 0,
        ];
        if ($id) {
            $this->db->where('id', (int) $id)->update(db_prefix() . 'eptw_simops_rules', $data);

            return (int) $id;
        }
        $this->db->insert(db_prefix() . 'eptw_simops_rules', $data);

        return (int) $this->db->insert_id();
    }

    public function delete_rule($id)
    {
        $this->db->where('id', (int) $id)->delete(db_prefix() . 'eptw_simops_rules');
    }

    /* ═══════════════════════════ People (site personnel) ══════════ */

    /**
     * The personnel master: everyone who may be named on a permit (holder,
     * supervisor, fire watch, isolation authority, toolbox attendee…) without
     * needing a login. Approvals stay with ePTW team members who log in.
     */
    public function people($only_active = false)
    {
        $this->db->select('pe.*, c.name AS contractor_name')->from(db_prefix() . 'eptw_people pe')
            ->join(db_prefix() . 'eptw_contractors c', 'c.id = pe.contractor_id', 'left');
        if ($only_active) {
            $this->db->where('pe.active', 1);
        }

        return $this->db->order_by('pe.name', 'asc')->get()->result();
    }

    /** Active people covering a project (empty project list = every project). */
    public function people_for($project_id)
    {
        if (!$this->db->table_exists(db_prefix() . 'eptw_people')) {
            return [];
        }
        $out = [];
        foreach ($this->people(true) as $row) {
            $projects = json_decode((string) $row->project_ids, true);
            $projects = is_array($projects) ? array_map('intval', $projects) : [];
            if ((int) $project_id && count($projects) && !in_array((int) $project_id, $projects, true)) {
                continue;
            }
            $out[] = $row;
        }

        return $out;
    }

    public function save_person(array $in, $id = 0)
    {
        $name = trim((string) ($in['name'] ?? ''));
        if ($name === '') {
            return 'Give the person a name.';
        }
        $badge = substr(trim((string) ($in['badge_no'] ?? '')), 0, 60);
        if ($badge !== '') {
            $dupe = $this->db->where('badge_no', $badge)->where('id !=', (int) $id)->get(db_prefix() . 'eptw_people')->row();
            if ($dupe) {
                return 'Badge / ID "' . $badge . '" already belongs to ' . $dupe->name . '.';
            }
        }
        $data = [
            'name'          => substr($name, 0, 150),
            'position'      => substr(trim((string) ($in['position'] ?? '')), 0, 120),
            'contractor_id' => (int) ($in['contractor_id'] ?? 0),
            'company'       => substr(trim((string) ($in['company'] ?? '')), 0, 150),
            'badge_no'      => $badge,
            'phone'         => substr(trim((string) ($in['phone'] ?? '')), 0, 40),
            'staff_id'      => (int) ($in['staff_id'] ?? 0),
            'project_ids'   => json_encode(array_values(array_filter(array_map('intval', (array) ($in['project_ids'] ?? []))))),
            'active'        => !empty($in['active']) ? 1 : 0,
        ];
        if ($id) {
            $this->db->where('id', (int) $id)->update(db_prefix() . 'eptw_people', $data);

            return (int) $id;
        }
        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert(db_prefix() . 'eptw_people', $data);

        return (int) $this->db->insert_id();
    }

    public function delete_person($id)
    {
        $this->db->where('id', (int) $id)->delete(db_prefix() . 'eptw_people');

        return true;
    }

    /* ═══════════════════════════ Approval matrix ═══════════════════ */

    /** permit_type_id => steps for one project (only the overridden types). */
    public function matrix($project_id)
    {
        $out = [];
        foreach ($this->db->where('project_id', (int) $project_id)->get(db_prefix() . 'eptw_approval_matrix')->result() as $row) {
            $steps = json_decode((string) $row->steps, true);
            $out[(int) $row->permit_type_id] = is_array($steps) ? $steps : [];
        }

        return $out;
    }

    /**
     * Save a project's matrix. Types left on "use the permit type default"
     * have no row; the coordinator always signs last by issuing.
     */
    public function save_matrix($project_id, array $in)
    {
        $project_id = (int) $project_id;
        if (!$project_id || !$this->project($project_id)) {
            return 'Choose a project.';
        }
        $table = db_prefix() . 'eptw_approval_matrix';
        foreach (eptw_permit_types(false) as $type) {
            $tid = (int) $type->id;
            $this->db->where('project_id', $project_id)->where('permit_type_id', $tid)->delete($table);
            if (empty($in['custom'][$tid])) {
                continue;
            }
            $steps = array_values(array_intersect(['area_authority', 'hse', 'manager'], (array) ($in['steps'][$tid] ?? [])));
            $steps[] = 'coordinator';
            $this->db->insert($table, ['project_id' => $project_id, 'permit_type_id' => $tid, 'steps' => json_encode($steps)]);
        }

        return true;
    }

    /* ═══════════════════════════ Settings ═══════════════════════════ */

    public function save_settings(array $in)
    {
        $format = trim((string) ($in['eptw_number_format'] ?? ''));
        if ($format === '' || strpos($format, '{SERIAL}') === false) {
            return 'The permit number format must contain {SERIAL}.';
        }
        $docs = array_values(array_intersect((array) ($in['eptw_required_docs'] ?? []), array_keys(eptw_document_types())));

        update_option('eptw_company_name', substr(trim((string) ($in['eptw_company_name'] ?? '')), 0, 150));
        update_option('eptw_number_format', substr($format, 0, 120));
        update_option('eptw_serial_padding', (string) max(2, min(8, (int) ($in['eptw_serial_padding'] ?? 5))));
        update_option('eptw_serial_scope', in_array($in['eptw_serial_scope'] ?? '', ['global', 'year', 'project_year', 'type_year', 'project_type_year'], true) ? $in['eptw_serial_scope'] : 'year');
        update_option('eptw_camera_mode', isset(eptw_camera_modes()[$in['eptw_camera_mode'] ?? '']) ? $in['eptw_camera_mode'] : 'allowed');
        update_option('eptw_expiring_hours', (string) max(1, min(72, (int) ($in['eptw_expiring_hours'] ?? 4))));
        update_option('eptw_auto_activate', !empty($in['eptw_auto_activate']) ? '1' : '0');
        update_option('eptw_email_notifications', !empty($in['eptw_email_notifications']) ? '1' : '0');
        update_option('eptw_simops_enabled', !empty($in['eptw_simops_enabled']) ? '1' : '0');
        update_option('eptw_required_docs', implode(',', $docs));
        update_option('eptw_max_upload_mb', (string) max(1, min(100, (int) ($in['eptw_max_upload_mb'] ?? 15))));
        update_option('eptw_max_extensions', (string) max(0, min(20, (int) ($in['eptw_max_extensions'] ?? 3))));
        $threshold = (float) str_replace(',', '.', (string) ($in['eptw_wah_threshold_m'] ?? '1.8'));
        update_option('eptw_wah_threshold_m', (string) ($threshold > 0 && $threshold <= 50 ? $threshold : 1.8));
        update_option('eptw_shared_areas', !empty($in['eptw_shared_areas']) ? '1' : '0');
        update_option('eptw_sod_rules', implode(',', array_values(array_intersect((array) ($in['eptw_sod_rules'] ?? []), array_keys(eptw_sod_rule_options())))));
        update_option('eptw_toolbox_mode', isset(eptw_toolbox_modes()[$in['eptw_toolbox_mode'] ?? '']) ? $in['eptw_toolbox_mode'] : 'both');
        update_option('eptw_docs_review', !empty($in['eptw_docs_review']) ? '1' : '0');
        update_option('eptw_escalate_high_risk', !empty($in['eptw_escalate_high_risk']) ? '1' : '0');
        update_option('eptw_dashboard_refresh', (string) (in_array((int) ($in['eptw_dashboard_refresh'] ?? 120), [0, 60, 120, 300, 600], true) ? (int) $in['eptw_dashboard_refresh'] : 120));
        update_option('eptw_overdue_hours', (string) max(1, min(720, (int) ($in['eptw_overdue_hours'] ?? 24))));

        return true;
    }
}
