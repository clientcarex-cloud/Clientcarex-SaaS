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
        $data = [
            'project_id'  => (int) ($in['project_id'] ?? 0),
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
        $row = $this->db->where('LOWER(name)', mb_strtolower($name))
            ->group_start()->where('project_id', (int) $project_id)->or_where('project_id', 0)->group_end()
            ->get(db_prefix() . 'eptw_areas')->row();
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
            'isolation_required'     => !empty($in['isolation_required']) ? 1 : 0,
            'default_validity_hours' => max(1, (int) ($in['default_validity_hours'] ?? 12)),
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

        return true;
    }
}
