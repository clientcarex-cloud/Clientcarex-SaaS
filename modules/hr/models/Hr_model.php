<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Hr_model extends App_Model
{
    /* ---------------------------------------------------------- Employees */

    /**
     * Active staff joined with their HR profile (staff without a profile
     * are included so they can be synced/completed).
     */
    public function get_employees($include_inactive = false)
    {
        $this->db->select(db_prefix() . 'staff.staffid, firstname, lastname, ' . db_prefix() . 'staff.email, phonenumber, ' . db_prefix() . 'staff.active, e.*');
        $this->db->from(db_prefix() . 'staff');
        $this->db->join(db_prefix() . 'hr_employees e', 'e.staff_id = ' . db_prefix() . 'staff.staffid', 'left');
        $this->db->where('is_not_staff', 0);
        if (!$include_inactive) {
            $this->db->where(db_prefix() . 'staff.active', 1);
        }
        $this->db->order_by('firstname', 'ASC');

        return $this->db->get()->result_array();
    }

    /**
     * Active employees eligible for HR operations (attendance, payroll...).
     */
    public function get_active_employees()
    {
        $this->db->select(db_prefix() . 'staff.staffid, firstname, lastname, ' . db_prefix() . 'staff.email, ' . db_prefix() . 'staff.role, e.*');
        $this->db->from(db_prefix() . 'staff');
        $this->db->join(db_prefix() . 'hr_employees e', 'e.staff_id = ' . db_prefix() . 'staff.staffid');
        $this->db->where('is_not_staff', 0);
        $this->db->where(db_prefix() . 'staff.active', 1);
        $this->db->where('e.status !=', 'exited');
        $this->db->order_by('firstname', 'ASC');

        return $this->db->get()->result_array();
    }

    public function get_employee($staff_id)
    {
        $this->db->select(db_prefix() . 'staff.staffid, firstname, lastname, ' . db_prefix() . 'staff.email, phonenumber, ' . db_prefix() . 'staff.active, ' . db_prefix() . 'staff.role, e.*');
        $this->db->from(db_prefix() . 'staff');
        $this->db->join(db_prefix() . 'hr_employees e', 'e.staff_id = ' . db_prefix() . 'staff.staffid', 'left');
        $this->db->where('staffid', $staff_id);

        return $this->db->get()->row_array();
    }

    /**
     * Create HR profile rows for any active staff missing one.
     * Returns number created.
     */
    public function sync_employees()
    {
        $rows = $this->db->query('SELECT staffid FROM ' . db_prefix() . 'staff s
            WHERE s.is_not_staff = 0
            AND NOT EXISTS (SELECT 1 FROM ' . db_prefix() . 'hr_employees e WHERE e.staff_id = s.staffid)')->result_array();

        foreach ($rows as $r) {
            $this->ensure_employee($r['staffid']);
        }

        return count($rows);
    }

    public function ensure_employee($staff_id)
    {
        $exists = $this->db->get_where(db_prefix() . 'hr_employees', ['staff_id' => $staff_id])->row_array();
        if ($exists) {
            return $exists['id'];
        }
        $this->db->insert(db_prefix() . 'hr_employees', [
            'staff_id'      => $staff_id,
            'employee_code' => get_option('hr_employee_code_prefix') . str_pad($staff_id, 4, '0', STR_PAD_LEFT),
            'created_at'    => date('Y-m-d H:i:s'),
        ]);
        $insert_id = $this->db->insert_id();

        // Welcome message. This is the single creation point for an HR profile,
        // so it covers "Sync from Staff", the importer and first-open alike.
        hr_fire_employee_hook('hr_employee_added', $staff_id);

        return $insert_id;
    }

    public function save_employee($staff_id, $data)
    {
        $this->ensure_employee($staff_id);
        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->db->where('staff_id', $staff_id);
        $this->db->update(db_prefix() . 'hr_employees', $data);

        return $this->db->affected_rows() >= 0;
    }

    /* ------------------------------------------------- Employees import/export */

    /**
     * Column order shared by export, the sample template and the importer so
     * the exported file can be edited and re-imported as-is. HR profile fields
     * mirror the whitelist in Hr::save_employee(). Department / designation /
     * reporting-to travel as human-editable NAMES / email (not ids).
     */
    public function employee_export_columns()
    {
        return [
            'staff_id', 'first_name', 'last_name', 'email', 'phone', 'staff_active',
            'employee_code', 'department', 'designation', 'employment_type',
            'date_of_joining', 'probation_end', 'work_location', 'reporting_to_email',
            'date_of_birth', 'gender', 'blood_group', 'marital_status', 'father_name',
            'personal_email', 'alt_phone', 'present_address', 'permanent_address',
            'emergency_contact_name', 'emergency_contact_relation', 'emergency_contact_phone',
            'national_id', 'aadhaar_number', 'pan_number', 'bank_name', 'bank_branch', 'bank_account_no',
            'bank_ifsc', 'pf_uan', 'esi_number', 'basic_salary', 'qualifications',
            'notes', 'status',
        ];
    }

    /**
     * Every employee (active + inactive staff) as flat rows keyed by the
     * export columns, ready to stream to CSV.
     */
    public function export_employee_rows()
    {
        $dept_names = [];
        foreach ($this->db->get(db_prefix() . 'departments')->result_array() as $d) {
            $dept_names[(int) $d['departmentid']] = $d['name'];
        }
        $desg_names = [];
        foreach ($this->get_designations() as $dg) {
            $desg_names[(int) $dg['id']] = $dg['name'];
        }
        $staff_emails = [];
        foreach ($this->db->select('staffid, email')->get(db_prefix() . 'staff')->result_array() as $s) {
            $staff_emails[(int) $s['staffid']] = $s['email'];
        }

        $rows = [];
        foreach ($this->get_employees(true) as $e) {
            $rows[] = [
                'staff_id'                   => $e['staffid'],
                'first_name'                 => $e['firstname'],
                'last_name'                  => $e['lastname'],
                'email'                      => $e['email'],
                'phone'                      => $e['phonenumber'],
                'staff_active'               => $e['active'],
                'employee_code'              => $e['employee_code'] ?? '',
                'department'                 => !empty($e['department_id']) && isset($dept_names[(int) $e['department_id']]) ? $dept_names[(int) $e['department_id']] : '',
                'designation'                => !empty($e['designation_id']) && isset($desg_names[(int) $e['designation_id']]) ? $desg_names[(int) $e['designation_id']] : '',
                'employment_type'            => $e['employment_type'] ?? '',
                'date_of_joining'            => $e['date_of_joining'] ?? '',
                'probation_end'              => $e['probation_end'] ?? '',
                'work_location'              => $e['work_location'] ?? '',
                'reporting_to_email'         => !empty($e['reporting_to']) && isset($staff_emails[(int) $e['reporting_to']]) ? $staff_emails[(int) $e['reporting_to']] : '',
                'date_of_birth'              => $e['date_of_birth'] ?? '',
                'gender'                     => $e['gender'] ?? '',
                'blood_group'                => $e['blood_group'] ?? '',
                'marital_status'             => $e['marital_status'] ?? '',
                'father_name'                => $e['father_name'] ?? '',
                'personal_email'             => $e['personal_email'] ?? '',
                'alt_phone'                  => $e['alt_phone'] ?? '',
                'present_address'            => $e['present_address'] ?? '',
                'permanent_address'          => $e['permanent_address'] ?? '',
                'emergency_contact_name'     => $e['emergency_contact_name'] ?? '',
                'emergency_contact_relation' => $e['emergency_contact_relation'] ?? '',
                'emergency_contact_phone'    => $e['emergency_contact_phone'] ?? '',
                'national_id'                => $e['national_id'] ?? '',
                'aadhaar_number'             => $e['aadhaar_number'] ?? '',
                'pan_number'                 => $e['pan_number'] ?? '',
                'bank_name'                  => $e['bank_name'] ?? '',
                'bank_branch'                => $e['bank_branch'] ?? '',
                'bank_account_no'            => $e['bank_account_no'] ?? '',
                'bank_ifsc'                  => $e['bank_ifsc'] ?? '',
                'pf_uan'                     => $e['pf_uan'] ?? '',
                'esi_number'                 => $e['esi_number'] ?? '',
                'basic_salary'               => $e['basic_salary'] ?? '',
                'qualifications'             => $e['qualifications'] ?? '',
                'notes'                      => $e['notes'] ?? '',
                'status'                     => $e['status'] ?? '',
            ];
        }

        return $rows;
    }

    /**
     * Editor hints for the import preview grid: how each column should be
     * rendered (text / date / number / select …), its human label and, for
     * enum columns, the exact accepted values. The grid builds its inputs
     * from this, so a column can never offer a value the importer rejects.
     */
    public function import_column_meta()
    {
        $labels = [
            'staff_id' => 'Staff ID', 'first_name' => 'First Name', 'last_name' => 'Last Name',
            'email' => 'Email', 'phone' => 'Phone', 'staff_active' => 'Login Active',
            'employee_code' => 'Employee Code', 'department' => 'Department', 'designation' => 'Designation',
            'employment_type' => 'Employment Type', 'date_of_joining' => 'Date of Joining',
            'probation_end' => 'Probation End', 'work_location' => 'Work Location',
            'reporting_to_email' => 'Reporting To (email)', 'date_of_birth' => 'Date of Birth',
            'gender' => 'Gender', 'blood_group' => 'Blood Group', 'marital_status' => 'Marital Status',
            'father_name' => "Father's Name", 'personal_email' => 'Personal Email', 'alt_phone' => 'Alt Phone',
            'present_address' => 'Present Address', 'permanent_address' => 'Permanent Address',
            'emergency_contact_name' => 'Emergency Contact', 'emergency_contact_relation' => 'Emergency Relation',
            'emergency_contact_phone' => 'Emergency Phone', 'national_id' => 'National ID',
            'aadhaar_number' => 'Aadhaar Number', 'pan_number' => 'PAN Number', 'bank_name' => 'Bank Name',
            'bank_branch' => 'Bank Branch', 'bank_account_no' => 'Bank A/C No', 'bank_ifsc' => 'IFSC',
            'pf_uan' => 'PF UAN', 'esi_number' => 'ESI Number', 'basic_salary' => 'Basic Salary',
            'qualifications' => 'Qualifications', 'notes' => 'Notes', 'status' => 'HR Status',
        ];

        $opts = function (array $map) {
            $list = [];
            foreach ($map as $v => $l) {
                $list[] = ['v' => (string) $v, 'l' => $l];
            }

            return $list;
        };

        $types = [
            'staff_id'        => ['type' => 'text', 'width' => 80],
            'date_of_joining' => ['type' => 'date'],
            'probation_end'   => ['type' => 'date'],
            'date_of_birth'   => ['type' => 'date'],
            'basic_salary'    => ['type' => 'number'],
            'email'           => ['type' => 'email', 'width' => 210],
            'personal_email'  => ['type' => 'email', 'width' => 210],
            'reporting_to_email' => ['type' => 'email', 'width' => 210, 'list' => 'managers'],
            'department'      => ['type' => 'text', 'list' => 'departments'],
            'designation'     => ['type' => 'text', 'list' => 'designations'],
            'present_address' => ['width' => 220],
            'permanent_address' => ['width' => 220],
            'notes'           => ['width' => 220],
            'qualifications'  => ['width' => 180],
            // Options travel as an ordered LIST of {v,l} pairs, not a map: a
            // map with numeric-looking keys ("1"/"0") gets silently reordered
            // by the browser when the grid iterates it.
            'staff_active'    => ['type' => 'select', 'options' => $opts(['1' => 'Yes (can log in)', '0' => 'No'])],
            'employment_type' => ['type' => 'select', 'options' => $opts([
                'permanent' => 'Permanent', 'contract' => 'Contract', 'probation' => 'Probation',
                'consultant' => 'Consultant', 'part_time' => 'Part Time', 'intern' => 'Intern',
            ])],
            'status' => ['type' => 'select', 'options' => $opts([
                'active' => 'Active', 'on_notice' => 'On Notice', 'exited' => 'Exited',
                'suspended' => 'Suspended', 'long_leave' => 'Long Leave',
            ])],
            'gender' => ['type' => 'select', 'options' => $opts(['female' => 'Female', 'male' => 'Male', 'other' => 'Other'])],
            'marital_status' => ['type' => 'select', 'options' => $opts(['single' => 'Single', 'married' => 'Married', 'other' => 'Other'])],
            'blood_group' => ['type' => 'select', 'options' => $opts(array_combine(
                ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'],
                ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-']
            ))],
        ];

        // Columns worth showing when the grid is in "essentials" mode.
        $essential = [
            'staff_id', 'first_name', 'last_name', 'email', 'phone', 'employee_code',
            'department', 'designation', 'employment_type', 'date_of_joining', 'status',
        ];

        $meta = [];
        foreach ($this->employee_export_columns() as $c) {
            $meta[$c] = array_merge(
                ['type' => 'text', 'label' => $labels[$c] ?? $c, 'width' => 150, 'essential' => in_array($c, $essential, true)],
                $types[$c] ?? []
            );
            $meta[$c]['label'] = $labels[$c] ?? $c;
        }

        return $meta;
    }

    /**
     * Value lists the preview grid offers as autocomplete: existing department
     * and designation names (typing a new one is still allowed — the importer
     * creates it) and every staff email that can be used as a manager.
     */
    public function import_datalists()
    {
        $departments = [];
        foreach ($this->db->get(db_prefix() . 'departments')->result_array() as $d) {
            $departments[] = $d['name'];
        }
        $designations = [];
        foreach ($this->get_designations() as $dg) {
            $designations[] = $dg['name'];
        }
        $managers = [];
        foreach ($this->db->select('email')->order_by('email', 'ASC')->get(db_prefix() . 'staff')->result_array() as $s) {
            if ($s['email'] != '') {
                $managers[] = $s['email'];
            }
        }

        return ['departments' => $departments, 'designations' => $designations, 'managers' => $managers];
    }

    /**
     * Lookup maps + validation vocabulary shared by the live preview grid and
     * the actual writer, so what the grid shows can never disagree with what
     * the import ends up doing.
     */
    public function import_context()
    {
        $ctx = [
            'can_salary'     => has_permission('hr_payroll', '', 'edit') || is_admin(),
            'departments'    => [], // lower(name) => id
            'designations'   => [], // lower(name) => id
            'staff_ids'      => [], // staffid => true
            'staff_by_email' => [], // lower(email) => staffid (negative = created earlier in this same file)
            'staff_by_code'  => [], // lower(employee_code) => staffid
            'pending_email'  => [], // lower(email) => row number that creates it
            'pending_code'   => [], // lower(code)  => row number that creates it
            'valid_types'    => ['permanent', 'contract', 'probation', 'consultant', 'part_time', 'intern'],
            'valid_statuses' => ['active', 'on_notice', 'exited', 'suspended', 'long_leave'],
            'valid_genders'  => ['female', 'male', 'other'],
            'valid_marital'  => ['single', 'married', 'other'],
            'date_fields'    => ['date_of_joining', 'probation_end', 'date_of_birth'],
            'hr_fields'      => [
                'employee_code', 'employment_type', 'date_of_joining', 'probation_end', 'work_location',
                'date_of_birth', 'gender', 'blood_group', 'marital_status', 'father_name', 'personal_email',
                'alt_phone', 'present_address', 'permanent_address', 'emergency_contact_name',
                'emergency_contact_relation', 'emergency_contact_phone', 'national_id', 'aadhaar_number', 'pan_number',
                'bank_name', 'bank_branch', 'bank_account_no', 'bank_ifsc', 'pf_uan', 'esi_number',
                'qualifications', 'notes', 'status',
            ],
        ];

        foreach ($this->db->get(db_prefix() . 'departments')->result_array() as $d) {
            $ctx['departments'][mb_strtolower(trim($d['name']))] = (int) $d['departmentid'];
        }
        foreach ($this->get_designations() as $dg) {
            $ctx['designations'][mb_strtolower(trim($dg['name']))] = (int) $dg['id'];
        }
        foreach ($this->db->select('staffid, email')->get(db_prefix() . 'staff')->result_array() as $s) {
            $ctx['staff_ids'][(int) $s['staffid']] = true;
            $ctx['staff_by_email'][mb_strtolower(trim($s['email']))] = (int) $s['staffid'];
        }
        foreach ($this->db->select('staff_id, employee_code')->get(db_prefix() . 'hr_employees')->result_array() as $e) {
            if ($e['employee_code'] != '') {
                $ctx['staff_by_code'][mb_strtolower(trim($e['employee_code']))] = (int) $e['staff_id'];
            }
        }

        return $ctx;
    }

    /**
     * Normalise + validate ONE import row against the running context. This is
     * the single source of truth for "what will happen to this row": both the
     * live grid (read-only) and import_employees() (writer) call it.
     *
     * @param array $row   raw cells keyed by employee_export_columns()
     * @param array $ctx   import_context(), mutated as rows claim emails/codes
     * @param int   $line  row number shown to the user
     * @param bool  $write true when the caller will actually persist (lets new
     *                     departments/designations be created for real)
     * @return array ['empty','row','staff_id','duplicate_of','action','messages','issues','dept_id','desg_id','reporting_to']
     */
    protected function prepare_import_row(array $row, array &$ctx, $line, $write = false)
    {
        // Rebuild strictly from the canonical columns: callers may hand us
        // bookkeeping keys (_row/_uid) that must never count as cell content.
        $clean = [];
        foreach ($this->employee_export_columns() as $c) {
            $clean[$c] = isset($row[$c]) && is_scalar($row[$c]) ? (string) $row[$c] : '';
        }
        $row = $clean;

        $messages = [];
        $issues   = [];
        // A rejected cell is blanked in the row that gets WRITTEN, but the
        // original text is kept here so the preview grid can still show it and
        // the user can correct it in place instead of retyping from the file.
        $kept = [];
        // Note a problem once: it goes to the row's message list and, when the
        // offending column is known, tints that single cell in the grid.
        $flag = function ($field, $message, $level = 'warning') use (&$messages, &$issues) {
            $messages[] = ['level' => $level, 'text' => $message];
            if ($field !== null && (!isset($issues[$field]) || ($level === 'error' && $issues[$field]['level'] !== 'error'))) {
                $issues[$field] = ['level' => $level, 'message' => $message];
            }
        };

        $blank = [
            'empty' => true, 'row' => $row, 'preview_row' => $row, 'staff_id' => 0, 'duplicate_of' => 0,
            'action' => 'empty', 'messages' => [], 'issues' => [], 'dept_id' => null, 'desg_id' => null,
            'reporting_to' => null,
        ];
        if (implode('', array_map('trim', $row)) === '') {
            return $blank;
        }

        // ---------------------------------------------------------- smart fill
        // Excel hands big numeric cells (phones, Aadhaar, accounts) over in
        // scientific notation — expand them back to plain digits.
        foreach (['phone', 'alt_phone', 'emergency_contact_phone', 'aadhaar_number', 'bank_account_no', 'pf_uan', 'esi_number'] as $nf) {
            $v = trim($row[$nf]);
            if ($v !== '' && preg_match('/^\d+(\.\d+)?[eE]\+?\d+$/', $v)) {
                $row[$nf] = sprintf('%.0f', (float) $v);
            }
        }

        // Both names in one cell — "Asha Nair" with a blank last_name — split
        // on the final space so the pair lands in the right columns.
        if (trim($row['last_name']) === '' && preg_match('/\s/', trim($row['first_name']))) {
            $parts             = preg_split('/\s+/', trim($row['first_name']));
            $row['last_name']  = array_pop($parts);
            $row['first_name'] = implode(' ', $parts);
            $flag('last_name', 'last_name "' . $row['last_name'] . '" auto-split from first_name', 'info');
        }

        $email = mb_strtolower(trim($row['email']));

        // ------------------------------------------------------ match employee
        $staff_id = 0;
        if (trim($row['staff_id']) !== '') {
            if (!ctype_digit(trim($row['staff_id']))) {
                $flag('staff_id', 'staff_id "' . trim($row['staff_id']) . '" is not a number — matched by email/code instead');
            } else {
                $sid = (int) trim($row['staff_id']);
                if (isset($ctx['staff_ids'][$sid])) {
                    $staff_id = $sid;
                } else {
                    $flag('staff_id', 'staff_id ' . $sid . ' not found — matched by email/code instead');
                }
            }
        }
        if (!$staff_id && $email !== '' && isset($ctx['staff_by_email'][$email])) {
            $staff_id = $ctx['staff_by_email'][$email];
        }
        $code_key = mb_strtolower(trim($row['employee_code']));
        if (!$staff_id && $code_key !== '' && isset($ctx['staff_by_code'][$code_key])) {
            $staff_id = $ctx['staff_by_code'][$code_key];
        }

        // No email and no match — derive a placeholder address from the mobile
        // number so the row can still be imported. Generated AFTER matching (a
        // blank email on a matched row must never overwrite a real one), then
        // re-checked against the staff map so re-importing the same sheet
        // updates the previously created record instead of duplicating it.
        if (!$staff_id && $email === '' && trim($row['phone']) !== '') {
            $digits = preg_replace('/\D+/', '', $row['phone']);
            if ($digits !== '') {
                $row['email'] = $digits . '@healtho.pro.com';
                $email        = $row['email'];
                if (isset($ctx['staff_by_email'][$email])) {
                    $staff_id   = $ctx['staff_by_email'][$email];
                    $flag('email', 'matched an existing employee by the auto-generated email ' . $row['email'], 'info');
                } else {
                    $flag('email', 'email auto-generated from the mobile number: ' . $row['email'], 'info');
                }
            }
        }

        // A negative id means an earlier row in THIS file creates that person;
        // the real import will match it for real and update it.
        $duplicate_of = 0;
        if ($staff_id < 0) {
            $duplicate_of = (int) ($ctx['pending_email'][$email] ?? ($ctx['pending_code'][$code_key] ?? 0));
            $staff_id     = 0;
            $flag('email', 'the same employee is already created by ' . ($duplicate_of ? 'row ' . $duplicate_of : 'an earlier row')
                . ' in this file — this row will update that record instead of creating a second one');
        }

        // -------------------------------------------------- validate/normalise
        foreach ($ctx['date_fields'] as $df) {
            $v = trim($row[$df]);
            if ($v === '') {
                continue;
            }
            $norm = $this->normalize_import_date($v);
            if ($norm === false) {
                $flag($df, 'the date "' . $v . '" in ' . $df . ' was not understood (use YYYY-MM-DD) — it will not be saved');
                $kept[$df] = $v;
                $row[$df]  = '';
            } else {
                $row[$df] = $norm;
            }
        }

        $etype = mb_strtolower(trim($row['employment_type']));
        if ($etype !== '' && !in_array($etype, $ctx['valid_types'], true)) {
            $flag('employment_type', 'employment_type "' . trim($row['employment_type']) . '" is not one of ' . implode(' / ', $ctx['valid_types']) . ' — ignored');
            $kept['employment_type'] = trim($row['employment_type']);
            $etype                   = '';
        }
        $row['employment_type'] = $etype;

        $estatus = mb_strtolower(trim($row['status']));
        if ($estatus !== '' && !in_array($estatus, $ctx['valid_statuses'], true)) {
            $flag('status', 'status "' . trim($row['status']) . '" is not one of ' . implode(' / ', $ctx['valid_statuses']) . ' — ignored');
            $kept['status'] = trim($row['status']);
            $estatus        = '';
        }
        $row['status'] = $estatus;

        $gender = mb_strtolower(trim($row['gender']));
        if ($gender !== '') {
            // Accept the common single-letter / long spellings people type.
            $aliases = ['f' => 'female', 'm' => 'male', 'o' => 'other', 'w' => 'female', 'woman' => 'female', 'man' => 'male'];
            $gender  = $aliases[$gender] ?? $gender;
            if (!in_array($gender, $ctx['valid_genders'], true)) {
                $flag('gender', 'gender "' . trim($row['gender']) . '" is not female / male / other — ignored');
                $kept['gender'] = trim($row['gender']);
                $gender         = '';
            }
        }
        $row['gender'] = $gender;

        $marital = mb_strtolower(trim($row['marital_status']));
        if ($marital !== '' && !in_array($marital, $ctx['valid_marital'], true)) {
            $flag('marital_status', 'marital_status "' . trim($row['marital_status']) . '" is not single / married / other — ignored');
            $kept['marital_status'] = trim($row['marital_status']);
            $marital                = '';
        }
        $row['marital_status'] = $marital;

        $bg = strtoupper(str_replace(' ', '', trim($row['blood_group'])));
        if ($bg !== '' && !in_array($bg, ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'], true)) {
            $flag('blood_group', 'blood_group "' . trim($row['blood_group']) . '" is not a known group — ignored');
            $kept['blood_group'] = trim($row['blood_group']);
            $bg                  = '';
        }
        $row['blood_group'] = $bg;

        $sa = trim($row['staff_active']);
        if ($sa !== '' && !in_array($sa, ['0', '1'], true)) {
            $flag('staff_active', 'staff_active "' . $sa . '" must be 1 or 0 — ignored');
            $kept['staff_active'] = $sa;
            $row['staff_active']  = '';
        }

        if (trim($row['personal_email']) !== '' && !filter_var(trim($row['personal_email']), FILTER_VALIDATE_EMAIL)) {
            $flag('personal_email', 'personal_email "' . trim($row['personal_email']) . '" is not a valid address — ignored');
            $kept['personal_email'] = trim($row['personal_email']);
            $row['personal_email']  = '';
        }

        $salary = trim($row['basic_salary']);
        if ($salary !== '') {
            if (!is_numeric(str_replace(',', '', $salary))) {
                $flag('basic_salary', 'basic_salary "' . $salary . '" is not a number — ignored');
                $kept['basic_salary'] = $salary;
                $row['basic_salary']  = '';
            } else {
                $row['basic_salary'] = (string) (float) str_replace(',', '', $salary);
                if (!$ctx['can_salary']) {
                    $flag('basic_salary', 'basic_salary will be ignored — you do not hold payroll edit permission');
                }
            }
        }

        // Aadhaar may travel with spaces / hyphens — store digits only. A value
        // that is not a 12-digit UIDAI number is still saved (some installs
        // record another identifier) but the cell is flagged.
        $aadhaar = trim($row['aadhaar_number']);
        if ($aadhaar !== '') {
            $row['aadhaar_number'] = hr_normalize_aadhaar($aadhaar);
            if (!hr_aadhaar_is_valid($row['aadhaar_number'])) {
                $flag('aadhaar_number', 'aadhaar_number "' . $aadhaar . '" is not a 12-digit Aadhaar — saved as given');
            }
        }

        // Department / designation resolved by name, created when new
        $dept_id = null;
        $dept    = trim($row['department']);
        if ($dept !== '') {
            $key = mb_strtolower($dept);
            if (isset($ctx['departments'][$key])) {
                $dept_id = $ctx['departments'][$key];
            } else {
                if ($write) {
                    $this->db->insert(db_prefix() . 'departments', ['name' => $dept]);
                    $dept_id = $this->db->insert_id();
                } else {
                    $dept_id = -1; // placeholder so the preview can report intent
                }
                $ctx['departments'][$key] = $dept_id;
                $flag('department', 'department "' . $dept . '" ' . ($write ? 'created' : 'does not exist yet and will be created'), 'info');
            }
        }
        $desg_id = null;
        $desg    = trim($row['designation']);
        if ($desg !== '') {
            $key = mb_strtolower($desg);
            if (isset($ctx['designations'][$key])) {
                $desg_id = $ctx['designations'][$key];
            } else {
                if ($write) {
                    $desg_id = $this->save_designation([
                        'name'          => $desg,
                        'department_id' => ($dept_id && $dept_id > 0) ? $dept_id : null,
                        'is_active'     => 1,
                    ]);
                } else {
                    $desg_id = -1;
                }
                $ctx['designations'][$key] = $desg_id;
                $flag('designation', 'designation "' . $desg . '" ' . ($write ? 'created' : 'does not exist yet and will be created'), 'info');
            }
        }

        $reporting_to = null;
        $mgr_email    = mb_strtolower(trim($row['reporting_to_email']));
        if ($mgr_email !== '') {
            if (isset($ctx['staff_by_email'][$mgr_email]) && $ctx['staff_by_email'][$mgr_email] > 0) {
                $reporting_to = $ctx['staff_by_email'][$mgr_email];
            } else {
                $flag('reporting_to_email', 'reporting_to_email "' . $mgr_email . '" does not match any staff member — ignored');
            }
        }

        // ------------------------------------------------------------- verdict
        if ($staff_id || $duplicate_of) {
            $action = 'updated';
            if ($staff_id && $email !== '' && isset($ctx['staff_by_email'][$email])
                && $ctx['staff_by_email'][$email] > 0 && $ctx['staff_by_email'][$email] !== $staff_id) {
                $flag('email', 'this email already belongs to another staff member — the email will not be changed');
            }
        } else {
            $ok = true;
            if (trim($row['first_name']) === '') {
                $flag('first_name', 'a new employee needs a first_name — row will be skipped', 'error');
                $ok = false;
            }
            if ($email === '') {
                $flag('email', 'a new employee needs an email, or a mobile number to generate one from — row will be skipped', 'error');
                $ok = false;
            } elseif (!filter_var(trim($row['email']), FILTER_VALIDATE_EMAIL)) {
                $flag('email', 'email "' . trim($row['email']) . '" is not a valid address — row will be skipped', 'error');
                $ok = false;
            }
            $action = $ok ? 'created' : 'skipped';
        }

        // Claim the email / code for the rest of the file so a repeated row is
        // reported as an update of the row above instead of a second create.
        if ($action === 'created' && !$write) {
            $ctx['staff_by_email'][$email] = -1;
            $ctx['pending_email'][$email]  = $line;
            if ($code_key !== '') {
                $ctx['staff_by_code'][$code_key] = -1;
                $ctx['pending_code'][$code_key]  = $line;
            }
        }

        return [
            'empty'        => false,
            'row'          => $row,
            'preview_row'  => array_merge($row, $kept),
            'staff_id'     => $staff_id,
            'duplicate_of' => $duplicate_of,
            'action'       => $action,
            'messages'     => $messages,
            'issues'       => $issues,
            'dept_id'      => $dept_id,
            'desg_id'      => $desg_id,
            'reporting_to' => $reporting_to,
        ];
    }

    /**
     * Dry-run the whole sheet and hand back one entry per input row — the
     * normalised values (so the grid shows what would actually be saved), the
     * verdict, the row notes and the per-cell problems. Writes nothing.
     *
     * Order is preserved 1:1 with $rows (blank rows come back as action
     * "empty") so the browser can map results onto its grid by index.
     */
    public function analyze_import_rows($rows)
    {
        $ctx = $this->import_context();
        $out = [];

        foreach ($rows as $i => $row) {
            $line = isset($row['_row']) && (int) $row['_row'] > 0 ? (int) $row['_row'] : ($i + 2);
            $prep = $this->prepare_import_row(is_array($row) ? $row : [], $ctx, $line, false);
            $name = trim($prep['row']['first_name'] . ' ' . $prep['row']['last_name']);

            $out[] = [
                'row'          => $line,
                'uid'          => isset($row['_uid']) ? (string) $row['_uid'] : ('r' . $i),
                'empty'        => $prep['empty'],
                'action'       => $prep['action'],
                'staff_id'     => $prep['staff_id'],
                'duplicate_of' => $prep['duplicate_of'],
                'name'         => $name,
                'values'       => $prep['preview_row'],
                'messages'     => $prep['messages'],
                'issues'       => $prep['issues'],
            ];
        }

        return $out;
    }

    /**
     * Bulk import: each row either UPDATES an existing employee (matched by
     * staff_id, then staff email, then employee_code) or CREATES a brand-new
     * staff member + HR profile. Designed to round-trip the export file.
     *
     * Update semantics: blank cells are left UNCHANGED (so a partial sheet
     * can't wipe data). basic_salary is only applied when the importing user
     * holds hr_payroll edit (mirrors Hr::save_employee).
     *
     * @param array $rows     assoc rows keyed by employee_export_columns()
     * @param bool  $simulate dry-run — validate + report, write nothing
     * @return array ['created'=>int,'updated'=>int,'skipped'=>int,'results'=>[['row'=>n,'name'=>..,'action'=>created|updated|skipped,'messages'=>[]]]]
     */
    public function import_employees($rows, $simulate = false)
    {
        $ctx     = $this->import_context();
        $summary = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'results' => []];

        foreach ($rows as $i => $raw) {
            $line = isset($raw['_row']) && (int) $raw['_row'] > 0 ? (int) $raw['_row'] : ($i + 2);
            $prep = $this->prepare_import_row(is_array($raw) ? $raw : [], $ctx, $line, !$simulate);
            if ($prep['empty']) {
                continue;
            }

            $row      = $prep['row'];
            $messages = array_map(function ($m) { return $m['text']; }, $prep['messages']);
            $name     = trim($row['first_name'] . ' ' . $row['last_name']);
            $staff_id = $prep['staff_id'];
            $email    = mb_strtolower(trim($row['email']));

            if ($prep['action'] === 'skipped') {
                $summary['skipped']++;
                $summary['results'][] = ['row' => $line, 'name' => $name !== '' ? $name : '(blank)', 'action' => 'skipped', 'messages' => $messages];
                continue;
            }

            // ------------------------------------------------------- UPDATE
            if ($staff_id) {
                $staff_update = [];
                foreach (['first_name' => 'firstname', 'last_name' => 'lastname', 'phone' => 'phonenumber'] as $col => $field) {
                    if (trim($row[$col]) !== '') {
                        $staff_update[$field] = trim($row[$col]);
                    }
                }
                if ($email !== '' && (!isset($ctx['staff_by_email'][$email]) || $ctx['staff_by_email'][$email] === $staff_id)) {
                    $staff_update['email'] = trim($row['email']);
                }
                $sa = trim($row['staff_active']);
                if ($sa !== '' && in_array($sa, ['0', '1'], true)) {
                    $staff_update['active'] = (int) $sa;
                }
                if (!$simulate && !empty($staff_update)) {
                    $this->db->where('staffid', $staff_id)->update(db_prefix() . 'staff', $staff_update);
                    if (isset($staff_update['email'])) {
                        $ctx['staff_by_email'][$email] = $staff_id;
                    }
                }

                if (!$simulate) {
                    $this->save_employee($staff_id, $this->import_profile_payload($row, $prep, $ctx));
                }

                $summary['updated']++;
                $summary['results'][] = ['row' => $line, 'name' => $name !== '' ? $name : ('Staff #' . $staff_id), 'action' => 'updated', 'messages' => $messages];
                continue;
            }

            // Dry run only: an earlier row in this same file creates this
            // person, so this row updates that record rather than adding a
            // second one. (During a real run the earlier row has already been
            // written, so it is matched by id above.)
            if ($prep['action'] === 'updated') {
                $summary['updated']++;
                $summary['results'][] = ['row' => $line, 'name' => $name, 'action' => 'updated', 'messages' => $messages];
                continue;
            }

            // ------------------------------------------------------- CREATE
            if ($simulate) {
                $summary['created']++;
                $summary['results'][] = ['row' => $line, 'name' => $name, 'action' => 'created', 'messages' => $messages];
                continue;
            }

            $this->load->model('staff_model');
            $sa       = trim($row['staff_active']);
            $staff_id = $this->staff_model->add([
                'firstname'   => trim($row['first_name']),
                'lastname'    => trim($row['last_name']),
                'email'       => trim($row['email']),
                'phonenumber' => trim($row['phone']),
                // random password — staff resets via "forgot password" (no
                // welcome email is sent from an import)
                'password'    => bin2hex(random_bytes(12)),
                'active'      => ($sa === '0') ? 0 : 1,
                'is_not_staff' => 0,
            ]);
            if (!$staff_id) {
                $messages[]           = 'staff record could not be created — row skipped';
                $summary['skipped']++;
                $summary['results'][] = ['row' => $line, 'name' => $name, 'action' => 'skipped', 'messages' => $messages];
                continue;
            }

            $ctx['staff_ids'][(int) $staff_id]      = true;
            $ctx['staff_by_email'][$email]          = (int) $staff_id;
            if (trim($row['employee_code']) !== '') {
                $ctx['staff_by_code'][mb_strtolower(trim($row['employee_code']))] = (int) $staff_id;
            }

            $this->ensure_employee($staff_id);
            $profile = $this->import_profile_payload($row, $prep, $ctx);
            if (!empty($profile)) {
                $this->save_employee($staff_id, $profile);
            }

            $summary['created']++;
            $summary['results'][] = ['row' => $line, 'name' => $name, 'action' => 'created', 'messages' => $messages];
        }

        return $summary;
    }

    /**
     * HR-profile half of an import row: every non-blank whitelisted field plus
     * the resolved department / designation / manager ids. Blank cells are
     * left out on purpose so a partial sheet never wipes existing data.
     */
    protected function import_profile_payload(array $row, array $prep, array $ctx)
    {
        $profile = [];
        foreach ($ctx['hr_fields'] as $f) {
            if (trim((string) ($row[$f] ?? '')) !== '') {
                $profile[$f] = trim((string) $row[$f]);
            }
        }
        if ($prep['dept_id'] && $prep['dept_id'] > 0) {
            $profile['department_id'] = $prep['dept_id'];
        }
        if ($prep['desg_id'] && $prep['desg_id'] > 0) {
            $profile['designation_id'] = $prep['desg_id'];
        }
        if ($prep['reporting_to']) {
            $profile['reporting_to'] = $prep['reporting_to'];
        }
        if (trim((string) ($row['basic_salary'] ?? '')) !== '' && $ctx['can_salary']) {
            $profile['basic_salary'] = (float) $row['basic_salary'];
        }

        return $profile;
    }

    /**
     * Accept the common ways Excel mangles dates: Y-m-d (canonical), d-m-Y,
     * d/m/Y, Y/m/d, plus raw .xlsx date cells, which arrive as a serial day
     * count (e.g. 34505 = 1994-06-20). Returns Y-m-d or false.
     */
    protected function normalize_import_date($value)
    {
        $value = trim($value);

        if (preg_match('/^\d{4,5}(\.0+)?$/', $value)) {
            $serial = (int) $value;
            // 10000 ≈ 1927, 80000 ≈ 2119; 25569 = serial of the Unix epoch
            if ($serial >= 10000 && $serial <= 80000) {
                return gmdate('Y-m-d', ($serial - 25569) * 86400);
            }
        }

        foreach (['Y-m-d', 'd-m-Y', 'd/m/Y', 'Y/m/d'] as $fmt) {
            $dt = DateTime::createFromFormat($fmt, $value);
            if ($dt && $dt->format($fmt) === $value) {
                return $dt->format('Y-m-d');
            }
        }

        return false;
    }

    /* ------------------------------------------------------- Designations */

    public function get_designations($only_active = false)
    {
        if ($only_active) {
            $this->db->where('is_active', 1);
        }
        $this->db->order_by('name', 'ASC');

        return $this->db->get(db_prefix() . 'hr_designations')->result_array();
    }

    public function save_designation($data, $id = null)
    {
        if ($id) {
            $this->db->where('id', $id);
            $this->db->update(db_prefix() . 'hr_designations', $data);

            return $id;
        }
        $this->db->insert(db_prefix() . 'hr_designations', $data);

        return $this->db->insert_id();
    }

    public function delete_designation($id)
    {
        $this->db->where('designation_id', $id)->update(db_prefix() . 'hr_employees', ['designation_id' => null]);
        $this->db->where('id', $id)->delete(db_prefix() . 'hr_designations');

        return $this->db->affected_rows() > 0;
    }

    /* ------------------------------------------------------------- Shifts */

    public function get_shifts($only_active = false)
    {
        if ($only_active) {
            $this->db->where('is_active', 1);
        }
        $this->db->order_by('start_time', 'ASC');

        return $this->db->get(db_prefix() . 'hr_shifts')->result_array();
    }

    public function get_shift($id)
    {
        return $this->db->get_where(db_prefix() . 'hr_shifts', ['id' => $id])->row_array();
    }

    public function save_shift($data, $id = null)
    {
        if (!empty($data['is_default'])) {
            $this->db->update(db_prefix() . 'hr_shifts', ['is_default' => 0]);
        }
        if ($id) {
            $this->db->where('id', $id);
            $this->db->update(db_prefix() . 'hr_shifts', $data);

            return $id;
        }
        $this->db->insert(db_prefix() . 'hr_shifts', $data);

        return $this->db->insert_id();
    }

    public function delete_shift($id)
    {
        $this->db->where('shift_id', $id)->delete(db_prefix() . 'hr_shift_assignments');
        $this->db->where('id', $id)->delete(db_prefix() . 'hr_shifts');

        return $this->db->affected_rows() > 0;
    }

    public function assign_shift($staff_id, $shift_id, $effective_from)
    {
        // One assignment per staff per effective date - latest wins
        $this->db->where(['staff_id' => $staff_id, 'effective_from' => $effective_from])
            ->delete(db_prefix() . 'hr_shift_assignments');
        $this->db->insert(db_prefix() . 'hr_shift_assignments', [
            'staff_id'       => $staff_id,
            'shift_id'       => $shift_id,
            'effective_from' => $effective_from,
            'created_at'     => date('Y-m-d H:i:s'),
        ]);

        return $this->db->insert_id();
    }

    /**
     * Shift effective for a staff member on a given date (falls back to the
     * default shift).
     */
    public function get_staff_shift($staff_id, $date = null)
    {
        $date = $date ?: date('Y-m-d');
        $row  = $this->db->query('SELECT s.* FROM ' . db_prefix() . 'hr_shift_assignments a
            JOIN ' . db_prefix() . 'hr_shifts s ON s.id = a.shift_id
            WHERE a.staff_id = ? AND a.effective_from <= ?
            ORDER BY a.effective_from DESC, a.id DESC LIMIT 1', [$staff_id, $date])->row_array();
        if ($row) {
            return $row;
        }

        return $this->db->get_where(db_prefix() . 'hr_shifts', ['is_default' => 1])->row_array();
    }

    /**
     * Latest shift assignment per staff (for the roster listing).
     */
    public function get_current_shift_map()
    {
        $rows = $this->db->query('SELECT a.staff_id, a.effective_from, s.id as shift_id, s.name,
                s.start_time, s.end_time, s.break_minutes, s.grace_minutes, s.week_off_days
            FROM ' . db_prefix() . 'hr_shift_assignments a
            JOIN ' . db_prefix() . 'hr_shifts s ON s.id = a.shift_id
            WHERE a.effective_from <= CURDATE()
            ORDER BY a.effective_from ASC, a.id ASC')->result_array();
        $map = [];
        foreach ($rows as $r) {
            $map[$r['staff_id']] = $r; // later (newer) rows overwrite older
        }

        return $map;
    }

    /* --------------------------------------------------------- Attendance */

    public function get_day_attendance($date)
    {
        $rows = $this->db->get_where(db_prefix() . 'hr_attendance', ['att_date' => $date])->result_array();
        $map  = [];
        foreach ($rows as $r) {
            $map[$r['staff_id']] = $r;
        }

        return $map;
    }

    public function save_attendance_row($staff_id, $date, $data)
    {
        $data['staff_id'] = $staff_id;
        $data['att_date'] = $date;
        // A hand-marked row is always tagged "manual" so an automatic machine
        // sync will not overwrite it (unless the admin enables that).
        $data['source'] = 'manual';

        $existing = $this->db->get_where(db_prefix() . 'hr_attendance', ['staff_id' => $staff_id, 'att_date' => $date])->row_array();

        // work minutes + late flag from shift + times
        $data['work_minutes'] = 0;
        $data['is_late']      = 0;
        if (!empty($data['check_in']) && !empty($data['check_out'])) {
            $in  = strtotime($date . ' ' . $data['check_in']);
            $out = strtotime($date . ' ' . $data['check_out']);
            if ($out < $in) {
                $out += 86400; // overnight shift
            }
            $data['work_minutes'] = max(0, (int) round(($out - $in) / 60));
        }
        if (!empty($data['check_in']) && in_array($data['status'], ['present', 'half_day'])) {
            $shift = $this->get_staff_shift($staff_id, $date);
            if ($shift) {
                $limit = strtotime($date . ' ' . $shift['start_time']) + ((int) $shift['grace_minutes'] * 60);
                if (strtotime($date . ' ' . $data['check_in']) > $limit) {
                    $data['is_late'] = 1;
                }
            }
        }

        // Only a CHANGE to absent is worth a message — re-saving a day that was
        // already marked absent must not send the employee a second alert.
        $newly_absent = $data['status'] === 'absent'
            && (!$existing || $existing['status'] !== 'absent');

        if ($existing) {
            $this->db->where('id', $existing['id']);
            $this->db->update(db_prefix() . 'hr_attendance', $data);
            $row_id = $existing['id'];
        } else {
            $data['created_at'] = date('Y-m-d H:i:s');
            $this->db->insert(db_prefix() . 'hr_attendance', $data);
            $row_id = $this->db->insert_id();
        }

        if ($newly_absent) {
            hr_fire_employee_hook('hr_attendance_absent', $staff_id, [
                'attendance_date' => _d($date),
                'attendance_note' => (string) ($data['note'] ?? ''),
            ]);
        }

        return $row_id;
    }

    /**
     * Month matrix: [staff_id][day] => row.
     */
    public function get_month_attendance($month, $year)
    {
        $from = sprintf('%04d-%02d-01', $year, $month);
        $to   = date('Y-m-t', strtotime($from));
        $rows = $this->db->query('SELECT * FROM ' . db_prefix() . 'hr_attendance
            WHERE att_date BETWEEN ? AND ?', [$from, $to])->result_array();
        $map = [];
        foreach ($rows as $r) {
            $map[$r['staff_id']][(int) date('j', strtotime($r['att_date']))] = $r;
        }

        return $map;
    }

    public function get_attendance_summary($month, $year)
    {
        $from = sprintf('%04d-%02d-01', $year, $month);
        $to   = date('Y-m-t', strtotime($from));

        return $this->db->query('SELECT staff_id, status, COUNT(*) as cnt, SUM(is_late) as late_cnt, SUM(work_minutes) as minutes
            FROM ' . db_prefix() . 'hr_attendance
            WHERE att_date BETWEEN ? AND ?
            GROUP BY staff_id, status', [$from, $to])->result_array();
    }

    /* ------------------------------------ Biometric attendance machine feed */

    /**
     * Normalised lookup keys for one machine/employee code. Always the trimmed
     * lower-case form, plus (for purely numeric codes) a leading-zero-stripped
     * variant so machine code "0001" resolves to employee_code "1".
     *
     * @return string[]
     */
    private function hr_empcode_keys($code)
    {
        $code = mb_strtolower(trim((string) $code));
        if ($code === '') {
            return [];
        }
        $keys = [$code];
        if (ctype_digit($code)) {
            $stripped = ltrim($code, '0');
            $keys[]   = ($stripped === '') ? '0' : $stripped;
        }

        return array_values(array_unique($keys));
    }

    /**
     * Map of every machine/employee code (and its numeric variants) to a
     * staff_id. device_empcode takes priority over employee_code and may hold
     * several comma-separated codes (an employee registered more than once on
     * the machine).
     */
    public function get_device_empcode_map()
    {
        $map  = [];
        $rows = $this->db->select('staff_id, employee_code, device_empcode')
            ->get(db_prefix() . 'hr_employees')->result_array();
        foreach ($rows as $e) {
            $sid = (int) $e['staff_id'];
            // device_empcode first so it wins when both resolve to the same key
            foreach ([$e['device_empcode'] ?? '', $e['employee_code'] ?? ''] as $code) {
                foreach (explode(',', (string) $code) as $part) {
                    foreach ($this->hr_empcode_keys($part) as $k) {
                        if (!isset($map[$k])) {
                            $map[$k] = $sid;
                        }
                    }
                }
            }
        }

        return $map;
    }

    private function hr_lookup_staff($empcode, $map)
    {
        foreach ($this->hr_empcode_keys($empcode) as $k) {
            if (isset($map[$k])) {
                return $map[$k];
            }
        }

        return null;
    }

    /**
     * Store raw punch rows pulled from the machine, de-duplicated on
     * (source, empcode, punch_at). Unknown codes are still stored (staff_id
     * NULL) so they can be mapped later, and their codes are returned so the
     * caller can warn the admin.
     *
     * @param array $rows e-timeoffice punch rows (Empcode, PunchDate, mcid, Name)
     * @return array ['added'=>int,'unmapped'=>string[]]
     */
    public function import_etime_punches($rows)
    {
        if (empty($rows) || !is_array($rows)) {
            return ['added' => 0, 'unmapped' => []];
        }

        $map        = $this->get_device_empcode_map();
        $now        = date('Y-m-d H:i:s');
        $unmapped   = [];
        $candidates = [];
        foreach ($rows as $r) {
            $emp = trim((string) ($r['Empcode'] ?? ''));
            $ts  = hr_etime_parse_dt($r['PunchDate'] ?? '');
            if ($emp === '' || !$ts) {
                continue;
            }
            $staff_id = $this->hr_lookup_staff($emp, $map);
            if (!$staff_id) {
                $unmapped[$emp] = true;
            }
            $mcid = $r['mcid'] ?? ($r['MCID'] ?? null);
            $candidates[] = [
                'staff_id'   => $staff_id,
                'empcode'    => $emp,
                'name'       => isset($r['Name']) ? substr((string) $r['Name'], 0, 150) : null,
                'punch_at'   => date('Y-m-d H:i:s', $ts),
                'mcid'       => ($mcid === null || $mcid === '') ? null : substr((string) $mcid, 0, 20),
                'source'     => 'etime',
                'created_at' => $now,
            ];
        }
        if (empty($candidates)) {
            return ['added' => 0, 'unmapped' => array_keys($unmapped)];
        }

        // One query resolves which of these already exist, so the batch insert
        // never trips the UNIQUE key.
        $times = array_column($candidates, 'punch_at');
        $exist = [];
        foreach ($this->db->query('SELECT empcode, punch_at FROM ' . db_prefix() . 'hr_attendance_punches
            WHERE source = "etime" AND punch_at BETWEEN ? AND ?', [min($times), max($times)])->result_array() as $e) {
            $exist[$e['empcode'] . '|' . $e['punch_at']] = true;
        }

        $batch = [];
        foreach ($candidates as $c) {
            $key = $c['empcode'] . '|' . $c['punch_at'];
            if (isset($exist[$key])) {
                continue;
            }
            $exist[$key] = true; // guard against duplicates within this payload too
            $batch[]     = $c;
        }
        if (!empty($batch)) {
            $this->db->insert_batch(db_prefix() . 'hr_attendance_punches', $batch);
        }

        return ['added' => count($batch), 'unmapped' => array_keys($unmapped)];
    }

    /**
     * Derive daily attendance from stored punches for a date range: first punch
     * of a day = check-in, last = check-out, short days flagged half-day, late
     * computed against the employee's shift. Only machine-owned or empty rows
     * are touched unless the admin enabled "overwrite manual". Absences are NOT
     * inferred (no punch simply means no machine row for that day).
     *
     * @return int number of attendance days inserted/updated
     */
    public function rebuild_attendance_from_punches($from_date, $to_date)
    {
        $from = date('Y-m-d', strtotime($from_date));
        $to   = date('Y-m-d', strtotime($to_date));
        $rows = $this->db->query('SELECT staff_id, punch_at FROM ' . db_prefix() . 'hr_attendance_punches
            WHERE staff_id IS NOT NULL AND source = "etime" AND DATE(punch_at) BETWEEN ? AND ?
            ORDER BY staff_id ASC, punch_at ASC', [$from, $to])->result_array();
        if (empty($rows)) {
            return 0;
        }

        $groups = []; // [staff_id][Y-m-d] => [datetime, ...]
        foreach ($rows as $r) {
            $groups[(int) $r['staff_id']][substr($r['punch_at'], 0, 10)][] = $r['punch_at'];
        }

        $halfday_minutes = max(0, (int) get_option('hr_etime_halfday_minutes'));
        $overwrite       = get_option('hr_etime_overwrite_manual') === '1';
        $count           = 0;

        foreach ($groups as $staff_id => $days) {
            foreach ($days as $date => $times) {
                sort($times);
                $first     = $times[0];
                $last      = end($times);
                $has_out   = ($last !== $first);
                $check_in  = substr($first, 11, 8);
                $check_out = $has_out ? substr($last, 11, 8) : null;

                $work_minutes = $has_out ? max(0, (int) round((strtotime($last) - strtotime($first)) / 60)) : 0;

                $status = 'present';
                if ($halfday_minutes > 0 && $has_out && $work_minutes > 0 && $work_minutes < $halfday_minutes) {
                    $status = 'half_day';
                }

                $is_late = 0;
                $shift   = $this->get_staff_shift($staff_id, $date);
                if ($shift) {
                    $limit = strtotime($date . ' ' . $shift['start_time']) + ((int) $shift['grace_minutes'] * 60);
                    if (strtotime($date . ' ' . $check_in) > $limit) {
                        $is_late = 1;
                    }
                }

                $existing = $this->db->get_where(db_prefix() . 'hr_attendance', ['staff_id' => $staff_id, 'att_date' => $date])->row_array();
                if ($existing) {
                    $existing_source = $existing['source'] ?? 'manual';
                    if ($existing_source === 'manual' && !$overwrite) {
                        continue; // never silently clobber a human-marked day
                    }
                    $this->db->where('id', $existing['id'])->update(db_prefix() . 'hr_attendance', [
                        'status'       => $status,
                        'check_in'     => $check_in,
                        'check_out'    => $check_out,
                        'work_minutes' => $work_minutes,
                        'is_late'      => $is_late,
                        'source'       => 'machine',
                    ]);
                } else {
                    $this->db->insert(db_prefix() . 'hr_attendance', [
                        'staff_id'     => $staff_id,
                        'att_date'     => $date,
                        'status'       => $status,
                        'check_in'     => $check_in,
                        'check_out'    => $check_out,
                        'work_minutes' => $work_minutes,
                        'is_late'      => $is_late,
                        'source'       => 'machine',
                        'marked_by'    => null,
                        'created_at'   => date('Y-m-d H:i:s'),
                    ]);
                }
                $count++;
            }
        }

        return $count;
    }

    /**
     * Punch log for the machine viewer: joined to staff names, newest first.
     */
    public function get_punches($from, $to, $staff_id = null, $limit = 1000)
    {
        $this->db->select('p.*, s.firstname, s.lastname');
        $this->db->from(db_prefix() . 'hr_attendance_punches p');
        $this->db->join(db_prefix() . 'staff s', 's.staffid = p.staff_id', 'left');
        $this->db->where('DATE(p.punch_at) >=', date('Y-m-d', strtotime($from)));
        $this->db->where('DATE(p.punch_at) <=', date('Y-m-d', strtotime($to)));
        if ($staff_id) {
            $this->db->where('p.staff_id', (int) $staff_id);
        }
        $this->db->order_by('p.punch_at', 'DESC');
        $this->db->limit((int) $limit);

        return $this->db->get()->result_array();
    }

    /**
     * Distinct machine codes that have punches not linked to any employee,
     * with enough context (machine name, punch count, last seen) for the
     * one-time mapping tool.
     */
    public function get_unmapped_punch_codes()
    {
        return $this->db->query('SELECT empcode, MAX(name) AS name, COUNT(*) AS punches,
            MIN(punch_at) AS first_at, MAX(punch_at) AS last_at
            FROM ' . db_prefix() . 'hr_attendance_punches
            WHERE staff_id IS NULL
            GROUP BY empcode
            ORDER BY MAX(punch_at) DESC')->result_array();
    }

    /**
     * One-time mapping of machine codes to employees. For each code => staff_id
     * pair: persist the code on the employee (device_empcode, comma-appended if
     * one already exists) so future syncs resolve automatically, attach every
     * stored orphan punch with that code, then rebuild attendance for the whole
     * date span the attached punches cover.
     *
     * @param array $pairs [empcode => staff_id]
     * @return array ['mapped'=>int,'attached'=>int,'attendance_updated'=>int,'skipped'=>string[]]
     */
    public function map_punch_codes($pairs)
    {
        $summary = ['mapped' => 0, 'attached' => 0, 'attendance_updated' => 0, 'skipped' => []];
        $min     = null;
        $max     = null;

        foreach ($pairs as $code => $staff_id) {
            $code     = trim((string) $code);
            $staff_id = (int) $staff_id;
            if ($code === '' || $staff_id <= 0) {
                continue;
            }
            $emp = $this->db->get_where(db_prefix() . 'hr_employees', ['staff_id' => $staff_id])->row_array();
            if (!$emp) {
                $summary['skipped'][] = $code;
                continue;
            }

            // Persist the code unless it already resolves to this employee.
            $covered = [];
            foreach ([$emp['device_empcode'] ?? '', $emp['employee_code'] ?? ''] as $existing) {
                foreach (explode(',', (string) $existing) as $part) {
                    foreach ($this->hr_empcode_keys($part) as $k) {
                        $covered[$k] = true;
                    }
                }
            }
            $already = false;
            foreach ($this->hr_empcode_keys($code) as $k) {
                if (isset($covered[$k])) {
                    $already = true;
                    break;
                }
            }
            if (!$already) {
                $device = trim((string) ($emp['device_empcode'] ?? ''));
                $device = ($device === '') ? $code : $device . ',' . $code;
                $this->db->where('staff_id', $staff_id)
                    ->update(db_prefix() . 'hr_employees', ['device_empcode' => substr($device, 0, 150)]);
            }

            // Attach every orphan punch carrying this exact code.
            $this->db->where('staff_id IS NULL', null, false)
                ->where('empcode', $code)
                ->update(db_prefix() . 'hr_attendance_punches', ['staff_id' => $staff_id]);
            $attached = $this->db->affected_rows();
            $summary['attached'] += $attached;
            $summary['mapped']++;

            if ($attached > 0) {
                $r = $this->db->query('SELECT MIN(punch_at) AS mn, MAX(punch_at) AS mx
                    FROM ' . db_prefix() . 'hr_attendance_punches
                    WHERE staff_id = ? AND empcode = ?', [$staff_id, $code])->row_array();
                if ($r && $r['mn']) {
                    $min = ($min === null || $r['mn'] < $min) ? $r['mn'] : $min;
                    $max = ($max === null || $r['mx'] > $max) ? $r['mx'] : $max;
                }
            }
        }

        // "Proceed for attendance": rebuild the days the newly attached punches touch.
        if ($min !== null && $max !== null) {
            $summary['attendance_updated'] = $this->rebuild_attendance_from_punches(substr($min, 0, 10), substr($max, 0, 10));
        }

        return $summary;
    }

    /* -------------------------------------------------- Field attendance */

    /**
     * Punch log for the manager screen. $filters accepts from / to / staff_id /
     * status / punch_type; everything is optional.
     */
    public function get_field_punches($filters = [], $limit = 500)
    {
        $this->db->select('p.*, s.firstname, s.lastname, e.employee_code, e.designation_id');
        $this->db->from(db_prefix() . 'hr_field_punches p');
        $this->db->join(db_prefix() . 'staff s', 's.staffid = p.staff_id', 'left');
        $this->db->join(db_prefix() . 'hr_employees e', 'e.staff_id = p.staff_id', 'left');

        if (!empty($filters['from'])) {
            $this->db->where('p.punch_date >=', date('Y-m-d', strtotime($filters['from'])));
        }
        if (!empty($filters['to'])) {
            $this->db->where('p.punch_date <=', date('Y-m-d', strtotime($filters['to'])));
        }
        if (!empty($filters['staff_id'])) {
            $this->db->where('p.staff_id', (int) $filters['staff_id']);
        }
        if (!empty($filters['status'])) {
            $this->db->where('p.status', $filters['status']);
        }
        if (!empty($filters['punch_type'])) {
            $this->db->where('p.punch_type', $filters['punch_type']);
        }
        $this->db->order_by('p.punch_at', 'DESC');
        $this->db->limit((int) $limit);

        return $this->db->get()->result_array();
    }

    public function get_field_punch($id)
    {
        $this->db->select('p.*, s.firstname, s.lastname, e.employee_code');
        $this->db->from(db_prefix() . 'hr_field_punches p');
        $this->db->join(db_prefix() . 'staff s', 's.staffid = p.staff_id', 'left');
        $this->db->join(db_prefix() . 'hr_employees e', 'e.staff_id = p.staff_id', 'left');
        $this->db->where('p.id', (int) $id);

        return $this->db->get()->row_array();
    }

    public function add_field_punch($data)
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert(db_prefix() . 'hr_field_punches', $data);

        return $this->db->insert_id();
    }

    public function update_field_punch($id, $data)
    {
        $this->db->where('id', (int) $id);
        $this->db->update(db_prefix() . 'hr_field_punches', $data);

        return $this->db->affected_rows() >= 0;
    }

    public function delete_field_punch($id)
    {
        $punch = $this->db->get_where(db_prefix() . 'hr_field_punches', ['id' => (int) $id])->row_array();
        if (!$punch) {
            return false;
        }
        if (!empty($punch['photo'])) {
            $path = hr_field_photo_dir($punch['staff_id']) . $punch['photo'];
            if (is_file($path)) {
                @unlink($path);
            }
        }
        $this->db->where('id', (int) $id)->delete(db_prefix() . 'hr_field_punches');

        return true;
    }

    /**
     * One employee's punches for a single day, oldest first.
     */
    public function get_staff_field_punches($staff_id, $date, $only_approved = false)
    {
        $this->db->where('staff_id', (int) $staff_id);
        $this->db->where('punch_date', date('Y-m-d', strtotime($date)));
        if ($only_approved) {
            $this->db->where('status', 'approved');
        }
        $this->db->order_by('punch_at', 'ASC');

        return $this->db->get(db_prefix() . 'hr_field_punches')->result_array();
    }

    /**
     * The employee's most recent punch overall (used to decide whether the next
     * one should default to IN or OUT, and to throttle double taps).
     */
    public function get_last_field_punch($staff_id)
    {
        $this->db->where('staff_id', (int) $staff_id);
        $this->db->order_by('punch_at', 'DESC');
        $this->db->limit(1);

        return $this->db->get(db_prefix() . 'hr_field_punches')->row_array();
    }

    /**
     * Headline counts for the manager screen, over the filtered date range.
     */
    public function get_field_punch_stats($from, $to)
    {
        $row = $this->db->query('SELECT
                COUNT(*) AS total,
                SUM(status = "pending")  AS pending,
                SUM(status = "approved") AS approved,
                SUM(status = "rejected") AS rejected,
                SUM(within_geofence = 0 AND site_id IS NOT NULL) AS outside,
                COUNT(DISTINCT staff_id) AS employees
            FROM ' . db_prefix() . 'hr_field_punches
            WHERE punch_date BETWEEN ? AND ?', [
                date('Y-m-d', strtotime($from)), date('Y-m-d', strtotime($to)),
            ])->row_array();

        return [
            'total'     => (int) ($row['total'] ?? 0),
            'pending'   => (int) ($row['pending'] ?? 0),
            'approved'  => (int) ($row['approved'] ?? 0),
            'rejected'  => (int) ($row['rejected'] ?? 0),
            'outside'   => (int) ($row['outside'] ?? 0),
            'employees' => (int) ($row['employees'] ?? 0),
        ];
    }

    public function count_pending_field_punches()
    {
        return (int) $this->db->where('status', 'pending')
            ->count_all_results(db_prefix() . 'hr_field_punches');
    }

    /**
     * Turn one employee's APPROVED field punches for a day into their daily
     * attendance row: first punch = check-in, last = check-out, short days fall
     * to half_day. Written with source = "field" and — exactly like the machine
     * sync — a hand-marked ("manual") day is never clobbered unless the admin
     * opted in. Days already covered by the biometric machine are left alone
     * too, so an office punch always beats a remote one.
     *
     * Returns true when an attendance row was written.
     */
    public function rebuild_field_attendance($staff_id, $date)
    {
        $cfg = hr_field_settings();
        if (!$cfg['update_attendance']) {
            return false;
        }

        $date    = date('Y-m-d', strtotime($date));
        $punches = $this->get_staff_field_punches($staff_id, $date, true);

        // Nothing approved left for that day (all rejected / deleted): drop the
        // row this feature created, but never touch a manual or machine day.
        if (empty($punches)) {
            $this->db->where('staff_id', $staff_id)
                ->where('att_date', $date)
                ->where('source', 'field')
                ->delete(db_prefix() . 'hr_attendance');

            return false;
        }

        $times = array_map(function ($p) { return $p['punch_at']; }, $punches);
        sort($times);
        $first     = $times[0];
        $last      = end($times);
        $has_out   = ($last !== $first);
        $check_in  = substr($first, 11, 8);
        $check_out = $has_out ? substr($last, 11, 8) : null;

        $work_minutes = $has_out ? max(0, (int) round((strtotime($last) - strtotime($first)) / 60)) : 0;

        $status = 'present';
        if ($cfg['halfday_minutes'] > 0 && $has_out && $work_minutes > 0 && $work_minutes < $cfg['halfday_minutes']) {
            $status = 'half_day';
        }

        $is_late = 0;
        $shift   = $this->get_staff_shift($staff_id, $date);
        if ($shift) {
            $limit = strtotime($date . ' ' . $shift['start_time']) + ((int) $shift['grace_minutes'] * 60);
            if (strtotime($date . ' ' . $check_in) > $limit) {
                $is_late = 1;
            }
        }

        $existing = $this->db->get_where(db_prefix() . 'hr_attendance', [
            'staff_id' => $staff_id, 'att_date' => $date,
        ])->row_array();

        if ($existing) {
            $source = $existing['source'] ?? 'manual';
            if (in_array($source, ['manual', 'machine'], true) && !$cfg['overwrite_manual']) {
                return false;
            }
            $this->db->where('id', $existing['id'])->update(db_prefix() . 'hr_attendance', [
                'status'       => $status,
                'check_in'     => $check_in,
                'check_out'    => $check_out,
                'work_minutes' => $work_minutes,
                'is_late'      => $is_late,
                'source'       => 'field',
            ]);

            return true;
        }

        $this->db->insert(db_prefix() . 'hr_attendance', [
            'staff_id'     => $staff_id,
            'att_date'     => $date,
            'status'       => $status,
            'check_in'     => $check_in,
            'check_out'    => $check_out,
            'work_minutes' => $work_minutes,
            'is_late'      => $is_late,
            'source'       => 'field',
            'marked_by'    => null,
            'created_at'   => date('Y-m-d H:i:s'),
        ]);

        return true;
    }

    /* ------------------------------------------- Field attendance: sites */

    public function get_field_sites($only_active = false)
    {
        if ($only_active) {
            $this->db->where('is_active', 1);
        }
        $this->db->order_by('name', 'ASC');

        return $this->db->get(db_prefix() . 'hr_field_sites')->result_array();
    }

    public function get_field_site($id)
    {
        return $this->db->get_where(db_prefix() . 'hr_field_sites', ['id' => (int) $id])->row_array();
    }

    public function save_field_site($data, $id = null)
    {
        if ($id) {
            $this->db->where('id', (int) $id);
            $this->db->update(db_prefix() . 'hr_field_sites', $data);

            return (int) $id;
        }
        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert(db_prefix() . 'hr_field_sites', $data);

        return $this->db->insert_id();
    }

    public function delete_field_site($id)
    {
        $this->db->where('id', (int) $id)->delete(db_prefix() . 'hr_field_sites');

        return $this->db->affected_rows() > 0;
    }

    /* ----------------------------------------------------------- Holidays */

    public function get_holidays($year = null, $only_active = false)
    {
        if ($year) {
            $this->db->where('YEAR(holiday_date)', $year);
        }
        if ($only_active) {
            $this->db->where('is_active', 1);
        }
        $this->db->order_by('holiday_date', 'ASC');

        return $this->db->get(db_prefix() . 'hr_holidays')->result_array();
    }

    /**
     * Import the curated Indian public holidays (see hr_indian_holidays) for a
     * year. Skips any holiday already present for the same date + name so it is
     * safe to run more than once. Returns the number of rows actually added.
     */
    public function import_indian_holidays($year)
    {
        $added = 0;
        foreach (hr_indian_holidays($year) as $h) {
            $exists = $this->db
                ->where('holiday_date', $h['holiday_date'])
                ->where('name', $h['name'])
                ->count_all_results(db_prefix() . 'hr_holidays');
            if ($exists) {
                continue;
            }
            $this->db->insert(db_prefix() . 'hr_holidays', [
                'name'         => $h['name'],
                'holiday_date' => $h['holiday_date'],
                'is_optional'  => $h['is_optional'],
                'is_active'    => 1,
            ]);
            $added++;
        }

        return $added;
    }

    /**
     * Show/hide a holiday. Inactive holidays stay in the list but are excluded
     * from attendance, working-day and payroll calculations.
     */
    public function toggle_holiday($id, $is_active)
    {
        $this->db->where('id', $id)->update(db_prefix() . 'hr_holidays', [
            'is_active' => $is_active ? 1 : 0,
        ]);

        return $this->db->affected_rows() >= 0;
    }

    public function save_holiday($data, $id = null)
    {
        if ($id) {
            $this->db->where('id', $id);
            $this->db->update(db_prefix() . 'hr_holidays', $data);

            return $id;
        }
        $this->db->insert(db_prefix() . 'hr_holidays', $data);

        return $this->db->insert_id();
    }

    public function delete_holiday($id)
    {
        $this->db->where('id', $id)->delete(db_prefix() . 'hr_holidays');

        return $this->db->affected_rows() > 0;
    }

    /* -------------------------------------------------------------- Leave */

    public function get_leave_types($only_active = false)
    {
        if ($only_active) {
            $this->db->where('is_active', 1);
        }
        $this->db->order_by('id', 'ASC');

        return $this->db->get(db_prefix() . 'hr_leave_types')->result_array();
    }

    public function get_leave_type($id)
    {
        return $this->db->where('id', (int) $id)
            ->get(db_prefix() . 'hr_leave_types')->row_array();
    }

    public function save_leave_type($data, $id = null)
    {
        if ($id) {
            $this->db->where('id', $id);
            $this->db->update(db_prefix() . 'hr_leave_types', $data);

            return $id;
        }
        $this->db->insert(db_prefix() . 'hr_leave_types', $data);

        return $this->db->insert_id();
    }

    public function delete_leave_type($id)
    {
        $used = $this->db->where('leave_type_id', $id)->count_all_results(db_prefix() . 'hr_leave_requests');
        if ($used > 0) {
            // keep history intact - deactivate instead
            $this->db->where('id', $id)->update(db_prefix() . 'hr_leave_types', ['is_active' => 0]);

            return false;
        }
        $this->db->where('leave_type_id', $id)->delete(db_prefix() . 'hr_leave_allocations');
        $this->db->where('id', $id)->delete(db_prefix() . 'hr_leave_types');

        return true;
    }

    public function get_leave_requests($where = [])
    {
        $this->db->select('r.*, t.name as type_name, t.code as type_code, t.color as type_color, t.is_paid, s.firstname, s.lastname');
        $this->db->from(db_prefix() . 'hr_leave_requests r');
        $this->db->join(db_prefix() . 'hr_leave_types t', 't.id = r.leave_type_id');
        $this->db->join(db_prefix() . 'staff s', 's.staffid = r.staff_id');
        foreach ($where as $k => $v) {
            $this->db->where($k, $v);
        }
        $this->db->order_by('r.id', 'DESC');

        return $this->db->get()->result_array();
    }

    public function get_leave_request($id)
    {
        $this->db->select('r.*, t.name as type_name, t.code as type_code, t.color as type_color, t.is_paid, s.firstname, s.lastname');
        $this->db->from(db_prefix() . 'hr_leave_requests r');
        $this->db->join(db_prefix() . 'hr_leave_types t', 't.id = r.leave_type_id');
        $this->db->join(db_prefix() . 'staff s', 's.staffid = r.staff_id');
        $this->db->where('r.id', $id);

        return $this->db->get()->row_array();
    }

    public function add_leave_request($data)
    {
        $data['days'] = !empty($data['is_half_day'])
            ? 0.5
            : ((strtotime($data['to_date']) - strtotime($data['from_date'])) / 86400) + 1;
        if ($data['days'] < 0.5) {
            return false;
        }
        $data['status']     = 'pending';
        $data['created_at'] = date('Y-m-d H:i:s');
        // Start at level 1 when a multi-level approval chain is configured.
        $data['current_level'] = count(hr_leave_chain()) > 0 ? 1 : 0;
        $this->db->insert(db_prefix() . 'hr_leave_requests', $data);
        $insert_id = $this->db->insert_id();

        // Alert the approvers. Fired here rather than in the controllers so a
        // request reaches them whether the employee filed it or HR did.
        hr_fire_employee_hook('hr_leave_applied', $data['staff_id'], hr_hook_leave_tags($this->get_leave_request($insert_id)));

        return $insert_id;
    }

    /**
     * Approve / reject / cancel. Approval writes leave rows into attendance;
     * un-approving (cancel after approve) removes them.
     */
    public function action_leave_request($id, $status, $note = '')
    {
        $request = $this->get_leave_request($id);
        if (!$request) {
            return false;
        }

        $this->db->where('id', $id)->update(db_prefix() . 'hr_leave_requests', [
            'status'      => $status,
            'approved_by' => get_staff_user_id(),
            'action_note' => $note,
            'action_at'   => date('Y-m-d H:i:s'),
        ]);

        if ($status === 'approved') {
            $date = $request['from_date'];
            while (strtotime($date) <= strtotime($request['to_date'])) {
                $this->save_attendance_row($request['staff_id'], $date, [
                    'status'    => $request['is_half_day'] ? 'half_day' : 'leave',
                    'note'      => $request['type_name'],
                    'marked_by' => get_staff_user_id(),
                ]);
                $date = date('Y-m-d', strtotime($date . ' +1 day'));
            }
        } elseif ($request['status'] === 'approved' && in_array($status, ['cancelled', 'rejected'])) {
            $this->db->where('staff_id', $request['staff_id'])
                ->where('att_date >=', $request['from_date'])
                ->where('att_date <=', $request['to_date'])
                ->where_in('status', ['leave', 'half_day'])
                ->delete(db_prefix() . 'hr_attendance');
        }

        return true;
    }

    /**
     * Act on a leave request within the multi-level approval chain. Approving a
     * non-final level advances the request to the next level; approving the last
     * level finalises it (writes attendance). Rejecting at any level rejects the
     * whole request. Each approval clears exactly ONE level — there is no
     * clear-all shortcut, so the chain is always followed level by level (an
     * admin approving simply stands in for that one level's approver). Falls back
     * to the classic single-step behaviour when no chain is configured.
     * Returns 'approved' | 'advanced' | 'rejected' | false.
     */
    public function leave_stage_action($id, $action, $acting_staff_id, $note = '')
    {
        $request = $this->get_leave_request($id);
        if (!$request || $request['status'] !== 'pending') {
            return false;
        }
        $chain = hr_leave_chain();

        // No chain, or a legacy row created before multi-level was enabled →
        // classic behaviour.
        if (empty($chain) || (int) $request['current_level'] < 1) {
            $this->action_leave_request($id, $action, $note);
            $this->fire_leave_outcome_hook($id, $action);

            return $action;
        }

        $level   = (int) $request['current_level'];
        $role_id = isset($chain[$level - 1]) ? (int) $chain[$level - 1]['role'] : 0;

        if ($action === 'rejected') {
            $this->db->where('id', $id)->update(db_prefix() . 'hr_leave_requests', [
                'status'      => 'rejected',
                'approved_by' => (int) $acting_staff_id,
                'action_note' => $note,
                'action_at'   => date('Y-m-d H:i:s'),
            ]);
            $this->log_leave_approval($id, $level, $role_id, 'rejected', $acting_staff_id, $note);
            $this->fire_leave_outcome_hook($id, 'rejected');

            return 'rejected';
        }

        // Approved for this level.
        $this->log_leave_approval($id, $level, $role_id, 'approved', $acting_staff_id, $note);

        if ($level >= count($chain)) {
            // Final level approved — write attendance via the shared path.
            $this->action_leave_request($id, 'approved', $note);
            $this->db->where('id', $id)->update(db_prefix() . 'hr_leave_requests', [
                'current_level' => count($chain),
            ]);
            $this->fire_leave_outcome_hook($id, 'approved');

            return 'approved';
        }

        // Advance to the next level.
        $this->db->where('id', $id)->update(db_prefix() . 'hr_leave_requests', [
            'current_level' => $level + 1,
        ]);

        return 'advanced';
    }

    /**
     * Message the employee once a leave request reaches a FINAL outcome.
     * Clearing an intermediate approval level is deliberately silent — only the
     * decision the employee is waiting on is worth an SMS.
     *
     * @param  int    $id      leave request id (re-read so the tags carry the
     *                         approver and note just written)
     * @param  string $outcome 'approved' | 'rejected'
     * @return void
     */
    protected function fire_leave_outcome_hook($id, $outcome)
    {
        if (!in_array($outcome, ['approved', 'rejected'], true)) {
            return;
        }
        $request = $this->get_leave_request($id);
        if (!$request) {
            return;
        }

        hr_fire_employee_hook('hr_leave_' . $outcome, $request['staff_id'], hr_hook_leave_tags($request));
    }

    /**
     * Permanently delete a leave request. If it was approved, the attendance
     * rows it created are removed; its approval log and any uploaded proof file
     * are cleaned up too.
     */
    public function delete_leave_request($id)
    {
        $request = $this->get_leave_request($id);
        if (!$request) {
            return false;
        }

        if ($request['status'] === 'approved') {
            $this->db->where('staff_id', $request['staff_id'])
                ->where('att_date >=', $request['from_date'])
                ->where('att_date <=', $request['to_date'])
                ->where_in('status', ['leave', 'half_day'])
                ->delete(db_prefix() . 'hr_attendance');
        }

        if (!empty($request['proof_file'])) {
            $path = hr_upload_dir($request['staff_id']) . $request['proof_file'];
            if (is_file($path)) {
                @unlink($path);
            }
        }

        $this->db->where('request_id', (int) $id)->delete(db_prefix() . 'hr_leave_approvals');
        $this->db->where('id', (int) $id)->delete(db_prefix() . 'hr_leave_requests');

        return $this->db->affected_rows() > 0;
    }

    public function log_leave_approval($request_id, $level, $role_id, $action, $staff_id, $note = '')
    {
        $this->db->insert(db_prefix() . 'hr_leave_approvals', [
            'request_id' => (int) $request_id,
            'level'      => (int) $level,
            'role_id'    => (int) $role_id,
            'action'     => $action,
            'staff_id'   => (int) $staff_id,
            'note'       => $note !== '' ? mb_substr($note, 0, 255) : null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function get_leave_approvals($request_id)
    {
        return $this->db->where('request_id', (int) $request_id)
            ->order_by('id', 'ASC')
            ->get(db_prefix() . 'hr_leave_approvals')->result_array();
    }

    /**
     * Pending leave requests that the given staff member may act on right now:
     * the request's current level is bound to their role (or an "Anyone" level
     * they can approve), excluding their own requests. Admins see all pending.
     */
    public function get_pending_approvals_for_staff($staff_id)
    {
        $chain = hr_leave_chain();
        if (empty($chain)) {
            return [];
        }
        $staff_id  = (int) $staff_id;
        $my_role   = hr_staff_role_id($staff_id);
        $admin     = is_admin();
        $can_any   = has_permission('hr_leaves', '', 'edit') || $admin;

        $out = [];
        foreach ($this->get_leave_requests(['r.status' => 'pending']) as $r) {
            if ((int) $r['staff_id'] === $staff_id) {
                continue;
            }
            $level = (int) $r['current_level'];
            if ($level < 1 || $level > count($chain)) {
                continue;
            }
            $lvl      = $chain[$level - 1];
            $eligible = $admin
                || ($lvl['type'] === 'user' && (int) $lvl['user'] === $staff_id)
                || ($lvl['type'] === 'role' && $my_role === (int) $lvl['role'])
                || ($lvl['type'] === 'any' && $can_any);
            if ($eligible) {
                $out[] = $r;
            }
        }

        return $out;
    }

    public function get_leave_allocations($year)
    {
        $rows = $this->db->get_where(db_prefix() . 'hr_leave_allocations', ['year' => $year])->result_array();
        $map  = [];
        foreach ($rows as $r) {
            $map[$r['staff_id']][$r['leave_type_id']] = $r['allocated'];
        }

        return $map;
    }

    public function set_leave_allocation($staff_id, $type_id, $year, $days)
    {
        $existing = $this->db->get_where(db_prefix() . 'hr_leave_allocations', [
            'staff_id' => $staff_id, 'leave_type_id' => $type_id, 'year' => $year,
        ])->row_array();
        if ($existing) {
            $this->db->where('id', $existing['id'])->update(db_prefix() . 'hr_leave_allocations', ['allocated' => $days]);
        } else {
            $this->db->insert(db_prefix() . 'hr_leave_allocations', [
                'staff_id' => $staff_id, 'leave_type_id' => $type_id, 'year' => $year, 'allocated' => $days,
            ]);
        }
    }

    /**
     * Approved leave days used per staff per type in a year.
     */
    public function get_leave_used($year)
    {
        $rows = $this->db->query('SELECT staff_id, leave_type_id, SUM(days) as used
            FROM ' . db_prefix() . 'hr_leave_requests
            WHERE status = "approved" AND YEAR(from_date) = ?
            GROUP BY staff_id, leave_type_id', [$year])->result_array();
        $map = [];
        foreach ($rows as $r) {
            $map[$r['staff_id']][$r['leave_type_id']] = (float) $r['used'];
        }

        return $map;
    }

    /**
     * Carried-forward days per staff per type for a year.
     */
    public function get_leave_carried($year)
    {
        $rows = $this->db->get_where(db_prefix() . 'hr_leave_allocations', ['year' => $year])->result_array();
        $map  = [];
        foreach ($rows as $r) {
            $map[$r['staff_id']][$r['leave_type_id']] = (float) ($r['carried'] ?? 0);
        }

        return $map;
    }

    /**
     * Store a carried-forward value on an employee's allocation row. Only
     * materialises a row when there is carry to store, so the grid stays clean.
     */
    public function set_leave_carried($staff_id, $type_id, $year, $carried, $default_base = 0)
    {
        $existing = $this->db->get_where(db_prefix() . 'hr_leave_allocations', [
            'staff_id' => $staff_id, 'leave_type_id' => $type_id, 'year' => $year,
        ])->row_array();

        if ($existing) {
            $this->db->where('id', $existing['id'])->update(db_prefix() . 'hr_leave_allocations', ['carried' => $carried]);
        } elseif ($carried > 0) {
            $this->db->insert(db_prefix() . 'hr_leave_allocations', [
                'staff_id'  => $staff_id, 'leave_type_id' => $type_id, 'year' => $year,
                'allocated' => $default_base, 'carried' => $carried,
            ]);
        }
    }

    /**
     * Carry unused balance from the previous year into $year for every active
     * employee and every carry-forward-enabled leave type. Idempotent — re-running
     * recomputes the carried value from source rather than stacking on top.
     * Returns the number of (employee × type) cells processed.
     */
    public function carry_forward_leaves($year)
    {
        $year = (int) $year;
        $prev = $year - 1;

        $cf_types = array_filter($this->get_leave_types(true), function ($t) {
            return !empty($t['carry_forward']);
        });
        if (empty($cf_types)) {
            return 0;
        }

        $employees    = $this->get_active_employees();
        $prev_alloc   = $this->get_leave_allocations($prev);
        $prev_carried = $this->get_leave_carried($prev);
        $prev_used    = $this->get_leave_used($prev);

        $applied = 0;
        foreach ($employees as $emp) {
            $sid = $emp['staffid'];
            foreach ($cf_types as $t) {
                $tid       = $t['id'];
                $base      = isset($prev_alloc[$sid][$tid]) ? (float) $prev_alloc[$sid][$tid] : (float) $t['days_per_year'];
                $carried   = (float) ($prev_carried[$sid][$tid] ?? 0);
                $used      = (float) ($prev_used[$sid][$tid] ?? 0);
                $remaining = max(0, $base + $carried - $used);
                $cap       = (float) ($t['carry_forward_max'] ?? 0);
                if ($cap > 0) {
                    $remaining = min($remaining, $cap);
                }
                $this->set_leave_carried($sid, $tid, $year, $remaining, (float) $t['days_per_year']);
                $applied++;
            }
        }

        return $applied;
    }

    /* ------------------------------------------------------------ Payroll */

    public function get_salary_components($only_active = false)
    {
        if ($only_active) {
            $this->db->where('is_active', 1);
        }
        $this->db->order_by('sort_order', 'ASC');

        return $this->db->get(db_prefix() . 'hr_salary_components')->result_array();
    }

    public function save_salary_component($data, $id = null)
    {
        if ($id) {
            $this->db->where('id', $id);
            $this->db->update(db_prefix() . 'hr_salary_components', $data);

            return $id;
        }
        $this->db->insert(db_prefix() . 'hr_salary_components', $data);

        return $this->db->insert_id();
    }

    public function delete_salary_component($id)
    {
        $this->db->where('component_id', $id)->delete(db_prefix() . 'hr_salary_structures');
        $this->db->where('id', $id)->delete(db_prefix() . 'hr_salary_components');

        return $this->db->affected_rows() > 0;
    }

    public function get_salary_structure($staff_id)
    {
        $rows = $this->db->get_where(db_prefix() . 'hr_salary_structures', ['staff_id' => $staff_id])->result_array();
        $map  = [];
        foreach ($rows as $r) {
            $map[$r['component_id']] = $r['value'];
        }

        return $map;
    }

    public function save_salary_structure($staff_id, $values)
    {
        $this->db->where('staff_id', $staff_id)->delete(db_prefix() . 'hr_salary_structures');

        // Sentinel row (component_id 0) records that this employee has an
        // explicit structure, so "all components disabled" is honoured instead
        // of silently falling back to every component's default.
        $this->db->insert(db_prefix() . 'hr_salary_structures', [
            'staff_id'     => $staff_id,
            'component_id' => 0,
            'value'        => 0,
        ]);

        foreach ($values as $component_id => $value) {
            if ((int) $component_id <= 0 || $value === '' || $value === null) {
                continue;
            }
            $this->db->insert(db_prefix() . 'hr_salary_structures', [
                'staff_id'     => $staff_id,
                'component_id' => (int) $component_id,
                'value'        => (float) $value,
            ]);
        }
    }

    /**
     * Resolve the component lines for an employee: assigned structure rows,
     * or component defaults when the employee has no structure yet.
     */
    public function resolve_components($staff_id, $basic)
    {
        $components = $this->get_salary_components(true);
        $structure  = $this->get_salary_structure($staff_id);
        $earnings   = [];
        $deductions = [];

        foreach ($components as $c) {
            $value = array_key_exists($c['id'], $structure) ? (float) $structure[$c['id']] : (float) $c['default_value'];
            if (count($structure) > 0 && !array_key_exists($c['id'], $structure)) {
                continue; // employee has an explicit structure; skip unassigned components
            }
            $amount = $c['calc_type'] === 'percent_basic' ? round($basic * $value / 100, 2) : round($value, 2);
            if ($amount <= 0) {
                continue;
            }
            $line = ['name' => $c['name'], 'amount' => $amount];
            if ($c['type'] === 'earning') {
                $earnings[] = $line;
            } else {
                $deductions[] = $line;
            }
        }

        return [$earnings, $deductions];
    }

    /**
     * Live salary calculation for one employee for a given month (defaults to the
     * current month). Returns the full-month projection (what lands on payday)
     * plus an "earned so far" estimate prorated by the days present up to today.
     * Deductions are returned both prorated and in full so the UI can offer the
     * two approaches.
     */
    public function salary_preview($staff_id, $month = null, $year = null)
    {
        $staff_id = (int) $staff_id;
        $month    = $month ?: (int) date('n');
        $year     = $year ?: (int) date('Y');

        $emp   = $this->get_employee($staff_id);
        $basic = (float) ($emp['basic_salary'] ?? 0);

        [$earnings, $deductions] = $this->resolve_components($staff_id, $basic);
        $earn_sum = array_sum(array_column($earnings, 'amount'));
        $ded_sum  = array_sum(array_column($deductions, 'amount'));
        $gross    = round($basic + $earn_sum, 2);
        $net_full = round($gross - $ded_sum, 2);

        $basis_days = $this->payroll_basis_days($month, $year, hr_week_off_days($emp['role'] ?? null));

        $from      = sprintf('%04d-%02d-01', $year, $month);
        $today     = date('Y-m-d');
        $month_end = date('Y-m-t', strtotime($from));
        $upto      = $today < $from ? $from : ($today > $month_end ? $month_end : $today);
        $elapsed   = $today < $from ? 0 : (int) date('j', strtotime($upto));

        $att = $this->db->query('SELECT
                SUM(status = "absent") absents, SUM(status = "half_day") half_days
            FROM ' . db_prefix() . 'hr_attendance
            WHERE staff_id = ? AND att_date BETWEEN ? AND ?', [$staff_id, $from, $upto])->row_array();
        $lop_so_far = (float) ($att['absents'] ?? 0) + 0.5 * (float) ($att['half_days'] ?? 0);

        $unpaid = $this->db->query('SELECT r.from_date, r.to_date, r.is_half_day
            FROM ' . db_prefix() . 'hr_leave_requests r
            JOIN ' . db_prefix() . 'hr_leave_types t ON t.id = r.leave_type_id
            WHERE r.staff_id = ? AND r.status = "approved" AND t.is_paid = 0
              AND r.from_date <= ? AND r.to_date >= ?', [$staff_id, $upto, $from])->result_array();
        foreach ($unpaid as $u) {
            $s = max(strtotime($u['from_date']), strtotime($from));
            $e = min(strtotime($u['to_date']), strtotime($upto));
            $lop_so_far += $u['is_half_day'] ? 0.5 : max(0, (($e - $s) / 86400) + 1);
        }

        $payable_so_far = min(max(0, $elapsed - $lop_so_far), $basis_days);
        $per_day        = $basis_days > 0 ? $gross / $basis_days : 0;
        $earned_gross   = round($per_day * $payable_so_far, 2);
        $ded_prorated   = round($basis_days > 0 ? $ded_sum * ($payable_so_far / $basis_days) : 0, 2);

        $pay_day    = hr_effective_pay_day($emp['salary_payment_day'] ?? null);
        $pay_date   = hr_pay_date_for_month($pay_day, $month, $year);
        $days_until = (int) floor((strtotime($pay_date) - strtotime($today)) / 86400);

        return [
            'basic'                => $basic,
            'earnings'             => $earnings,
            'deductions'           => $deductions,
            'earn_sum'             => round($earn_sum, 2),
            'ded_sum'              => round($ded_sum, 2),
            'gross'                => $gross,
            'net_full'             => max(0, $net_full),
            'basis_days'           => $basis_days,
            'elapsed'              => $elapsed,
            'lop_so_far'           => $lop_so_far,
            'payable_so_far'       => $payable_so_far,
            'per_day'              => round($per_day, 2),
            'earned_gross'         => $earned_gross,
            'ded_prorated'         => $ded_prorated,
            'current_net_prorated' => max(0, round($earned_gross - $ded_prorated, 2)),
            'current_net_fullded'  => max(0, round($earned_gross - $ded_sum, 2)),
            'emp_pay_day'          => isset($emp['salary_payment_day']) ? $emp['salary_payment_day'] : null,
            'pay_day'              => $pay_day,
            'pay_date'             => $pay_date,
            'days_until'           => $days_until,
        ];
    }

    /**
     * Projected full-month payroll totals across active employees, for the
     * pre-payday dashboard reminder. Not prorated (this is the expected payout).
     */
    public function payroll_reminder_summary($month = null, $year = null)
    {
        $month = $month ?: (int) date('n');
        $year  = $year ?: (int) date('Y');

        $employees   = $this->get_active_employees();
        $total_gross = 0;
        $total_ded   = 0;
        $with_salary = 0;

        foreach ($employees as $emp) {
            $basic = (float) $emp['basic_salary'];
            [$earnings, $deductions] = $this->resolve_components($emp['staff_id'], $basic);
            $gross = $basic + array_sum(array_column($earnings, 'amount'));
            if ($gross <= 0) {
                continue;
            }
            $total_gross += $gross;
            $total_ded   += array_sum(array_column($deductions, 'amount'));
            $with_salary++;
        }

        return [
            'headcount'        => count($employees),
            'with_salary'      => $with_salary,
            'total_gross'      => round($total_gross, 2),
            'total_deductions' => round($total_ded, 2),
            'total_net'        => round($total_gross - $total_ded, 2),
            'month'            => $month,
            'year'             => $year,
        ];
    }

    public function get_payroll_runs()
    {
        return $this->db->query('SELECT r.*,
            (SELECT COUNT(*) FROM ' . db_prefix() . 'hr_payslips p WHERE p.run_id = r.id) as slip_count,
            (SELECT COALESCE(SUM(net_pay),0) FROM ' . db_prefix() . 'hr_payslips p WHERE p.run_id = r.id) as total_net
            FROM ' . db_prefix() . 'hr_payroll_runs r ORDER BY r.year DESC, r.month DESC')->result_array();
    }

    public function get_payroll_run($id)
    {
        return $this->db->get_where(db_prefix() . 'hr_payroll_runs', ['id' => $id])->row_array();
    }

    public function get_payslips($run_id)
    {
        $this->db->select('p.*, s.firstname, s.lastname, e.designation_id, d.name as designation');
        $this->db->from(db_prefix() . 'hr_payslips p');
        $this->db->join(db_prefix() . 'staff s', 's.staffid = p.staff_id');
        $this->db->join(db_prefix() . 'hr_employees e', 'e.staff_id = p.staff_id', 'left');
        $this->db->join(db_prefix() . 'hr_designations d', 'd.id = e.designation_id', 'left');
        $this->db->where('p.run_id', $run_id);
        $this->db->order_by('s.firstname', 'ASC');

        return $this->db->get()->result_array();
    }

    public function get_payslip($id)
    {
        $this->db->select('p.*, s.firstname, s.lastname, s.email, e.department_id, e.designation_id, e.date_of_joining, e.bank_name, e.bank_account_no, e.pf_uan, e.esi_number, e.pan_number, d.name as designation, r.month, r.year, r.status as run_status');
        $this->db->from(db_prefix() . 'hr_payslips p');
        $this->db->join(db_prefix() . 'staff s', 's.staffid = p.staff_id');
        $this->db->join(db_prefix() . 'hr_payroll_runs r', 'r.id = p.run_id');
        $this->db->join(db_prefix() . 'hr_employees e', 'e.staff_id = p.staff_id', 'left');
        $this->db->join(db_prefix() . 'hr_designations d', 'd.id = e.designation_id', 'left');
        $this->db->where('p.id', $id);

        return $this->db->get()->row_array();
    }

    /**
     * Days basis for salary proration per settings. Pass $week_offs (array of
     * day numbers) to use role-specific weekly offs for the "working" basis.
     */
    public function payroll_basis_days($month, $year, $week_offs = null)
    {
        $basis         = get_option('hr_payroll_lop_basis');
        $days_in_month = (int) date('t', strtotime(sprintf('%04d-%02d-01', $year, $month)));
        if ($basis === 'fixed30') {
            return 30;
        }
        if ($basis === 'working') {
            $off_days = $week_offs !== null ? $week_offs : hr_week_off_days();
            $count    = 0;
            for ($d = 1; $d <= $days_in_month; $d++) {
                $w = date('w', strtotime(sprintf('%04d-%02d-%02d', $year, $month, $d)));
                if (in_array($w, $off_days)) {
                    $count++;
                }
            }
            $holidays = $this->db->query('SELECT COUNT(*) c FROM ' . db_prefix() . 'hr_holidays
                WHERE MONTH(holiday_date) = ? AND YEAR(holiday_date) = ? AND is_active = 1', [$month, $year])->row()->c;

            return max(1, $days_in_month - $count - (int) $holidays);
        }

        return $days_in_month;
    }

    /**
     * Generate (or regenerate draft) payroll for a month.
     * Returns [run_id, generated_count] or a string error.
     */
    public function generate_payroll($month, $year)
    {
        $run = $this->db->get_where(db_prefix() . 'hr_payroll_runs', ['month' => $month, 'year' => $year])->row_array();
        if ($run && $run['status'] === 'finalized') {
            return 'finalized';
        }
        if (!$run) {
            $this->db->insert(db_prefix() . 'hr_payroll_runs', [
                'month' => $month, 'year' => $year, 'status' => 'draft',
                'generated_by' => get_staff_user_id(), 'created_at' => date('Y-m-d H:i:s'),
            ]);
            $run_id = $this->db->insert_id();
        } else {
            $run_id = $run['id'];
            $this->db->where('run_id', $run_id)->delete(db_prefix() . 'hr_payslips');
        }

        $from      = sprintf('%04d-%02d-01', $year, $month);
        $to        = date('Y-m-t', strtotime($from));
        $employees = $this->get_active_employees();

        // "working" basis varies by role (role-wise weekly offs) - cache per role
        $basis_by_role = [];

        // absent / half-day counts for the month
        $att = $this->db->query('SELECT staff_id,
                SUM(status = "absent") as absents,
                SUM(status = "half_day") as half_days
            FROM ' . db_prefix() . 'hr_attendance
            WHERE att_date BETWEEN ? AND ? GROUP BY staff_id', [$from, $to])->result_array();
        $att_map = [];
        foreach ($att as $a) {
            $att_map[$a['staff_id']] = $a;
        }

        // unpaid approved leave days overlapping the month
        $unpaid = $this->db->query('SELECT r.staff_id, r.from_date, r.to_date, r.days, r.is_half_day
            FROM ' . db_prefix() . 'hr_leave_requests r
            JOIN ' . db_prefix() . 'hr_leave_types t ON t.id = r.leave_type_id
            WHERE r.status = "approved" AND t.is_paid = 0
            AND r.from_date <= ? AND r.to_date >= ?', [$to, $from])->result_array();
        $unpaid_map = [];
        foreach ($unpaid as $u) {
            $start = max(strtotime($u['from_date']), strtotime($from));
            $end   = min(strtotime($u['to_date']), strtotime($to));
            $days  = $u['is_half_day'] ? 0.5 : (($end - $start) / 86400) + 1;
            $unpaid_map[$u['staff_id']] = ($unpaid_map[$u['staff_id']] ?? 0) + max(0, $days);
        }

        $generated = 0;
        foreach ($employees as $emp) {
            $role_key = (string) ($emp['role'] ?: 0);
            if (!isset($basis_by_role[$role_key])) {
                $basis_by_role[$role_key] = $this->payroll_basis_days($month, $year, hr_week_off_days($emp['role']));
            }
            $basis_days = $basis_by_role[$role_key];

            $basic = (float) $emp['basic_salary'];
            [$earnings, $deductions] = $this->resolve_components($emp['staff_id'], $basic);
            if ($basic <= 0 && !count($earnings)) {
                continue;
            }

            $gross      = $basic + array_sum(array_column($earnings, 'amount'));
            $total_ded  = array_sum(array_column($deductions, 'amount'));
            $a          = $att_map[$emp['staff_id']] ?? ['absents' => 0, 'half_days' => 0];
            $lop_days   = (float) $a['absents'] + 0.5 * (float) $a['half_days'] + ($unpaid_map[$emp['staff_id']] ?? 0);
            $lop_days   = min($lop_days, $basis_days);
            $lop_amount = round(($gross / $basis_days) * $lop_days, 2);
            $net        = round($gross - $lop_amount - $total_ded, 2);

            $this->db->insert(db_prefix() . 'hr_payslips', [
                'run_id'           => $run_id,
                'staff_id'         => $emp['staff_id'],
                'employee_code'    => $emp['employee_code'],
                'basic'            => $basic,
                'earnings'         => json_encode($earnings),
                'deductions'       => json_encode($deductions),
                'gross'            => $gross,
                'total_deductions' => $total_ded,
                'payable_days'     => $basis_days - $lop_days,
                'lop_days'         => $lop_days,
                'lop_amount'       => $lop_amount,
                'net_pay'          => max(0, $net),
            ]);
            $generated++;
        }

        return [$run_id, $generated];
    }

    public function finalize_payroll($run_id)
    {
        $this->db->where('id', $run_id)->where('status', 'draft')->update(db_prefix() . 'hr_payroll_runs', [
            'status' => 'finalized', 'finalized_at' => date('Y-m-d H:i:s'),
        ]);
        if ($this->db->affected_rows() < 1) {
            return false;
        }

        // Finalizing is what makes payslips visible in self-service, so this is
        // the moment to tell everyone in the run.
        $run = $this->get_payroll_run($run_id);
        foreach ($this->get_payslips($run_id) as $slip) {
            hr_fire_employee_hook('hr_payslip_published', $slip['staff_id'], [
                'pay_period'       => hr_hook_pay_period($run['month'], $run['year']),
                'pay_month'        => (string) $run['month'],
                'pay_year'         => (string) $run['year'],
                'gross_pay'        => hr_hook_money($slip['gross']),
                'total_deductions' => hr_hook_money($slip['total_deductions']),
                'net_pay'          => hr_hook_money($slip['net_pay']),
                'payable_days'     => (string) $slip['payable_days'],
                'lop_days'         => (string) $slip['lop_days'],
            ]);
        }

        return true;
    }

    public function delete_payroll_run($run_id, $allow_finalized = false)
    {
        $run = $this->get_payroll_run($run_id);
        if (!$run) {
            return false;
        }
        // Finalized runs are locked for ordinary delete-permission holders; only
        // an explicit override (superadmin) may remove them.
        if ($run['status'] === 'finalized' && !$allow_finalized) {
            return false;
        }
        // Remove all payslips (and their paid/log data) belonging to the run.
        $this->db->where('run_id', $run_id)->delete(db_prefix() . 'hr_payslips');
        $this->db->where('id', $run_id)->delete(db_prefix() . 'hr_payroll_runs');

        return true;
    }

    public function mark_payslip_paid($id, $mode = '', $paid_date = null)
    {
        // Allow backdating (e.g. old payslips paid earlier). Fall back to today.
        if (!$paid_date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $paid_date)) {
            $paid_date = date('Y-m-d');
        }
        $this->db->where('id', $id)->update(db_prefix() . 'hr_payslips', [
            'status' => 'paid', 'paid_date' => $paid_date, 'payment_mode' => $mode,
        ]);
        if ($this->db->affected_rows() < 1) {
            return false;
        }

        $slip = $this->get_payslip($id);
        if ($slip) {
            hr_fire_employee_hook('hr_salary_paid', $slip['staff_id'], [
                'pay_period'   => hr_hook_pay_period($slip['month'], $slip['year']),
                'pay_month'    => (string) $slip['month'],
                'pay_year'     => (string) $slip['year'],
                'net_pay'      => hr_hook_money($slip['net_pay']),
                'payment_mode' => (string) ($slip['payment_mode'] ?: ''),
                'paid_date'    => _d($slip['paid_date']),
            ]);
        }

        return true;
    }

    public function delete_payslip($id)
    {
        $this->db->where('id', (int) $id)->delete(db_prefix() . 'hr_payslips');

        return $this->db->affected_rows() > 0;
    }

    /* ----------------------------------------------------- Family members
     * Family / dependent records of one employee. Used for insurance and ESI
     * dependants, emergency reach-out and printed HR forms.
     * ---------------------------------------------------------------------- */

    public function get_family_members($staff_id)
    {
        $this->db->where('staff_id', (int) $staff_id);
        $this->db->order_by('is_dependent', 'DESC');
        $this->db->order_by('id', 'ASC');

        return $this->db->get(db_prefix() . 'hr_family_members')->result_array();
    }

    public function get_family_member($id)
    {
        return $this->db->get_where(db_prefix() . 'hr_family_members', ['id' => (int) $id])->row_array();
    }

    /**
     * Insert or update one family member. $id = 0 inserts.
     *
     * @return int the family member id
     */
    public function save_family_member($staff_id, $data, $id = 0)
    {
        $id                 = (int) $id;
        $data['staff_id']   = (int) $staff_id;
        $data['updated_at'] = date('Y-m-d H:i:s');

        if ($id) {
            unset($data['staff_id']);
            $this->db->where('id', $id)->update(db_prefix() . 'hr_family_members', $data);

            return $id;
        }

        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert(db_prefix() . 'hr_family_members', $data);

        return $this->db->insert_id();
    }

    public function delete_family_member($id)
    {
        $this->db->where('id', (int) $id)->delete(db_prefix() . 'hr_family_members');

        return $this->db->affected_rows() > 0;
    }

    /* ---------------------------------------------------------- KRA & KPI
     * Key Result Areas and the Key Performance Indicators measuring them.
     * Both live in one table (entry_type); a KPI may reference its KRA by
     * parent_id. Rich-text fields are purified by the controller before save.
     * ---------------------------------------------------------------------- */

    /**
     * All KRA/KPI rows of one employee, optionally limited to one entry type.
     *
     * @param int         $staff_id
     * @param string|null $type 'kra' | 'kpi' | null for both
     */
    public function get_kra_kpi($staff_id, $type = null)
    {
        $this->db->where('staff_id', (int) $staff_id);
        if ($type !== null) {
            $this->db->where('entry_type', $type);
        }
        $this->db->order_by('entry_type', 'ASC');
        $this->db->order_by('sort_order', 'ASC');
        $this->db->order_by('id', 'ASC');

        return $this->db->get(db_prefix() . 'hr_kra_kpi')->result_array();
    }

    public function get_kra_kpi_item($id)
    {
        return $this->db->get_where(db_prefix() . 'hr_kra_kpi', ['id' => (int) $id])->row_array();
    }

    /**
     * Insert or update one KRA / KPI. $id = 0 inserts.
     *
     * @return int the row id
     */
    public function save_kra_kpi($staff_id, $data, $id = 0)
    {
        $id                 = (int) $id;
        $data['updated_at'] = date('Y-m-d H:i:s');
        $data['updated_by'] = get_staff_user_id();

        if ($id) {
            unset($data['staff_id'], $data['entry_type']);
            $this->db->where('id', $id)->update(db_prefix() . 'hr_kra_kpi', $data);

            return $id;
        }

        $data['staff_id']   = (int) $staff_id;
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['created_by'] = get_staff_user_id();
        $this->db->insert(db_prefix() . 'hr_kra_kpi', $data);

        return $this->db->insert_id();
    }

    /**
     * Delete one KRA / KPI. Deleting a KRA detaches (never deletes) the KPIs
     * that were measuring it, so no performance record is silently lost.
     */
    public function delete_kra_kpi($id)
    {
        $id = (int) $id;
        $this->db->where('parent_id', $id)->update(db_prefix() . 'hr_kra_kpi', ['parent_id' => null]);
        $this->db->where('id', $id)->delete(db_prefix() . 'hr_kra_kpi');

        return $this->db->affected_rows() > 0;
    }

    /**
     * Total weightage per entry type for one employee, e.g.
     * ['kra' => 100.00, 'kpi' => 80.00]. Weightage of each type should add up
     * to 100%, which the profile flags but never enforces (entry is gradual).
     */
    public function kra_kpi_weightage_totals($staff_id)
    {
        $this->db->select('entry_type, SUM(weightage) AS total, COUNT(*) AS items, AVG(rating) AS avg_rating');
        $this->db->where('staff_id', (int) $staff_id);
        $this->db->where('status !=', 'dropped');
        $this->db->group_by('entry_type');
        $rows = $this->db->get(db_prefix() . 'hr_kra_kpi')->result_array();

        $out = [];
        foreach ($rows as $r) {
            $out[$r['entry_type']] = [
                'total'      => (float) $r['total'],
                'items'      => (int) $r['items'],
                'avg_rating' => $r['avg_rating'] === null ? null : (float) $r['avg_rating'],
            ];
        }

        return $out;
    }

    /* ----------------------------------------------------------- Nominees
     * Nomination records per scheme (PF / ESI / Gratuity / Insurance...).
     * Shares are percentages that should add up to 100 per scheme.
     * ---------------------------------------------------------------------- */

    public function get_nominees($staff_id)
    {
        $this->db->where('staff_id', (int) $staff_id);
        $this->db->order_by('nominee_for', 'ASC');
        $this->db->order_by('share_percent', 'DESC');
        $this->db->order_by('id', 'ASC');

        return $this->db->get(db_prefix() . 'hr_nominees')->result_array();
    }

    public function get_nominee($id)
    {
        return $this->db->get_where(db_prefix() . 'hr_nominees', ['id' => (int) $id])->row_array();
    }

    /**
     * Insert or update one nominee. $id = 0 inserts.
     *
     * @return int the nominee id
     */
    public function save_nominee($staff_id, $data, $id = 0)
    {
        $id                 = (int) $id;
        $data['staff_id']   = (int) $staff_id;
        $data['updated_at'] = date('Y-m-d H:i:s');

        if ($id) {
            unset($data['staff_id']);
            $this->db->where('id', $id)->update(db_prefix() . 'hr_nominees', $data);

            return $id;
        }

        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert(db_prefix() . 'hr_nominees', $data);

        return $this->db->insert_id();
    }

    public function delete_nominee($id)
    {
        $this->db->where('id', (int) $id)->delete(db_prefix() . 'hr_nominees');

        return $this->db->affected_rows() > 0;
    }

    /**
     * Total nominated share per scheme for one employee, e.g. ['pf' => 100.00].
     * Drives the "shares do not add up to 100%" warning on the profile.
     *
     * @return array scheme => float
     */
    public function nominee_share_totals($staff_id)
    {
        $rows = $this->db->query('SELECT nominee_for, SUM(share_percent) AS total
            FROM ' . db_prefix() . 'hr_nominees WHERE staff_id = ?
            GROUP BY nominee_for', [(int) $staff_id])->result_array();

        $totals = [];
        foreach ($rows as $r) {
            $totals[$r['nominee_for']] = (float) $r['total'];
        }

        return $totals;
    }

    /* ---------------------------------------------------------- Documents */

    public function get_documents($staff_id)
    {
        $this->db->where('staff_id', $staff_id);
        $this->db->order_by('id', 'DESC');

        return $this->db->get(db_prefix() . 'hr_documents')->result_array();
    }

    public function get_document($id)
    {
        return $this->db->get_where(db_prefix() . 'hr_documents', ['id' => $id])->row_array();
    }

    public function add_document($data)
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert(db_prefix() . 'hr_documents', $data);

        return $this->db->insert_id();
    }

    public function delete_document($id)
    {
        $doc = $this->get_document($id);
        if (!$doc) {
            return false;
        }
        $path = hr_upload_dir($doc['staff_id']) . $doc['file_name'];
        if (file_exists($path)) {
            @unlink($path);
        }
        $this->db->where('id', $id)->delete(db_prefix() . 'hr_documents');

        return true;
    }

    /**
     * HR verifies / rejects an employee-submitted document.
     */
    public function verify_document($id, $status, $note = '')
    {
        if (!in_array($status, ['submitted', 'verified', 'rejected'], true)) {
            return false;
        }
        $doc = $this->get_document($id);
        $this->db->where('id', $id)->update(db_prefix() . 'hr_documents', [
            'status'      => $status,
            'review_note' => $note !== '' ? $note : null,
            'verified_by' => get_staff_user_id(),
            'verified_at' => date('Y-m-d H:i:s'),
        ]);

        // "submitted" is HR putting a document back into the queue — nothing to
        // announce; only a verdict is worth telling the employee about.
        if ($doc && in_array($status, ['verified', 'rejected'], true)) {
            hr_fire_employee_hook('hr_document_' . $status, $doc['staff_id'], [
                'document_title' => (string) $doc['title'],
                'document_type'  => (string) ($doc['doc_type'] ?: ''),
                'reviewer_name'  => get_staff_full_name(get_staff_user_id()),
                'review_note'    => $note,
            ]);
        }

        return true;
    }

    public function get_expiring_documents($days = 30)
    {
        return $this->db->query('SELECT d.*, s.firstname, s.lastname FROM ' . db_prefix() . 'hr_documents d
            JOIN ' . db_prefix() . 'staff s ON s.staffid = d.staff_id
            WHERE d.expiry_date IS NOT NULL
            AND d.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ' . (int) $days . ' DAY)
            ORDER BY d.expiry_date ASC')->result_array();
    }

    /* ---------------------------------------------------------- Trainings */

    public function get_trainings()
    {
        return $this->db->query('SELECT t.*, d.name as department_name,
            (SELECT COUNT(*) FROM ' . db_prefix() . 'hr_training_attendees a WHERE a.training_id = t.id) as attendee_count
            FROM ' . db_prefix() . 'hr_trainings t
            LEFT JOIN ' . db_prefix() . 'departments d ON d.departmentid = t.department_id
            ORDER BY t.id DESC')->result_array();
    }

    public function get_training($id)
    {
        return $this->db->get_where(db_prefix() . 'hr_trainings', ['id' => $id])->row_array();
    }

    public function save_training($data, $id = null)
    {
        if ($id) {
            $this->db->where('id', $id);
            $this->db->update(db_prefix() . 'hr_trainings', $data);

            return $id;
        }
        $data['created_by'] = get_staff_user_id();
        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert(db_prefix() . 'hr_trainings', $data);

        return $this->db->insert_id();
    }

    public function delete_training($id)
    {
        $this->db->where('training_id', $id)->delete(db_prefix() . 'hr_training_attendees');
        $this->db->where('id', $id)->delete(db_prefix() . 'hr_trainings');

        return $this->db->affected_rows() > 0;
    }

    public function get_training_attendees($training_id)
    {
        return $this->db->query('SELECT a.*, s.firstname, s.lastname FROM ' . db_prefix() . 'hr_training_attendees a
            JOIN ' . db_prefix() . 'staff s ON s.staffid = a.staff_id
            WHERE a.training_id = ? ORDER BY s.firstname ASC', [$training_id])->result_array();
    }

    public function set_training_attendees($training_id, $staff_ids)
    {
        $existing = array_column($this->get_training_attendees($training_id), 'staff_id');
        $added    = [];
        foreach ($staff_ids as $sid) {
            if (!in_array($sid, $existing)) {
                $this->db->insert(db_prefix() . 'hr_training_attendees', [
                    'training_id' => $training_id, 'staff_id' => (int) $sid,
                ]);
                $added[] = (int) $sid;
            }
        }

        // Invite only the people who were just nominated — the attendee list is
        // re-posted in full on every save, so everyone else already knows.
        if ($added) {
            $training = $this->get_training($training_id);
            foreach ($added as $sid) {
                hr_fire_employee_hook('hr_training_scheduled', $sid, [
                    'training_title'    => (string) $training['title'],
                    'trainer'           => (string) ($training['trainer'] ?: ''),
                    'training_category' => (string) ($training['category'] ?: ''),
                    'training_start'    => $training['start_date'] ? _d($training['start_date']) : '',
                    'training_end'      => $training['end_date'] ? _d($training['end_date']) : '',
                    'training_venue'    => (string) ($training['venue'] ?: ''),
                ]);
            }
        }
        // remove unchecked
        if (count($staff_ids)) {
            $this->db->where('training_id', $training_id)->where_not_in('staff_id', $staff_ids)
                ->delete(db_prefix() . 'hr_training_attendees');
        } else {
            $this->db->where('training_id', $training_id)->delete(db_prefix() . 'hr_training_attendees');
        }
    }

    public function update_training_attendee($id, $data)
    {
        $this->db->where('id', $id)->update(db_prefix() . 'hr_training_attendees', $data);

        return $this->db->affected_rows() >= 0;
    }

    /* --------------------------------------------------------- Appraisals */

    public function get_appraisals($staff_id = null)
    {
        $this->db->select('a.*, s.firstname, s.lastname, r.firstname as rev_first, r.lastname as rev_last');
        $this->db->from(db_prefix() . 'hr_appraisals a');
        $this->db->join(db_prefix() . 'staff s', 's.staffid = a.staff_id');
        $this->db->join(db_prefix() . 'staff r', 'r.staffid = a.reviewer_id', 'left');
        if ($staff_id) {
            $this->db->where('a.staff_id', $staff_id);
        }
        $this->db->order_by('a.id', 'DESC');

        return $this->db->get()->result_array();
    }

    public function get_appraisal($id)
    {
        return $this->db->get_where(db_prefix() . 'hr_appraisals', ['id' => $id])->row_array();
    }

    public function save_appraisal($data, $id = null)
    {
        $was_completed = false;
        if ($id) {
            $before        = $this->get_appraisal($id);
            $was_completed = $before && $before['status'] === 'completed';
            if (($data['status'] ?? '') === 'completed') {
                $data['completed_at'] = date('Y-m-d H:i:s');
            }
            $this->db->where('id', $id);
            $this->db->update(db_prefix() . 'hr_appraisals', $data);
        } else {
            $data['created_at'] = date('Y-m-d H:i:s');
            $this->db->insert(db_prefix() . 'hr_appraisals', $data);
            $id = $this->db->insert_id();
        }

        // Completing an appraisal is what publishes it to the employee's
        // self-service portal — re-saving a completed one must not re-announce.
        if (($data['status'] ?? '') === 'completed' && !$was_completed) {
            $appraisal = $this->get_appraisal($id);
            if ($appraisal) {
                hr_fire_employee_hook('hr_appraisal_shared', $appraisal['staff_id'], [
                    'period_from'    => $appraisal['period_from'] ? _d($appraisal['period_from']) : '',
                    'period_to'      => $appraisal['period_to'] ? _d($appraisal['period_to']) : '',
                    'overall_rating' => (string) $appraisal['overall_rating'],
                    'reviewer_name'  => $appraisal['reviewer_id'] ? get_staff_full_name($appraisal['reviewer_id']) : '',
                    'strengths'      => (string) ($appraisal['strengths'] ?: ''),
                    'improvements'   => (string) ($appraisal['improvements'] ?: ''),
                    'goals'          => (string) ($appraisal['goals'] ?: ''),
                ]);
            }
        }

        return $id;
    }

    public function delete_appraisal($id)
    {
        $this->db->where('id', $id)->delete(db_prefix() . 'hr_appraisals');

        return $this->db->affected_rows() > 0;
    }

    /* -------------------------------------------------------------- Exits */

    public function get_exits()
    {
        return $this->db->query('SELECT x.*, s.firstname, s.lastname, e.employee_code
            FROM ' . db_prefix() . 'hr_exits x
            JOIN ' . db_prefix() . 'staff s ON s.staffid = x.staff_id
            LEFT JOIN ' . db_prefix() . 'hr_employees e ON e.staff_id = x.staff_id
            ORDER BY x.id DESC')->result_array();
    }

    public function get_exit($id)
    {
        return $this->db->get_where(db_prefix() . 'hr_exits', ['id' => $id])->row_array();
    }

    public function save_exit($data, $id = null)
    {
        $before = $id ? $this->get_exit($id) : null;

        if ($id) {
            $this->db->where('id', $id);
            $this->db->update(db_prefix() . 'hr_exits', $data);
        } else {
            $data['created_by'] = get_staff_user_id();
            $data['created_at'] = date('Y-m-d H:i:s');
            $this->db->insert(db_prefix() . 'hr_exits', $data);
            $id = $this->db->insert_id();
        }

        $exit = $this->get_exit($id);
        // keep the employee profile status in sync
        if ($exit) {
            $emp_status = $exit['status'] === 'settled' ? 'exited' : 'on_notice';
            $this->save_employee($exit['staff_id'], ['status' => $emp_status]);
            if ($exit['status'] === 'settled') {
                // deactivate the CRM login
                $this->db->where('staffid', $exit['staff_id'])->update(db_prefix() . 'staff', ['active' => 0]);
            }

            // Two distinct moments worth a message: the exit being recorded, and
            // the settlement closing it. Editing an exit in place fires neither.
            if (!$before) {
                hr_fire_employee_hook('hr_exit_initiated', $exit['staff_id'], [
                    'exit_type'        => (string) ($exit['exit_type'] ?: ''),
                    'notice_date'      => $exit['notice_date'] ? _d($exit['notice_date']) : '',
                    'last_working_day' => $exit['last_working_day'] ? _d($exit['last_working_day']) : '',
                    'exit_reason'      => (string) ($exit['reason'] ?: ''),
                ]);
            }
            if ($exit['status'] === 'settled' && (!$before || $before['status'] !== 'settled')) {
                hr_fire_employee_hook('hr_exit_settled', $exit['staff_id'], [
                    'exit_type'         => (string) ($exit['exit_type'] ?: ''),
                    'last_working_day'  => $exit['last_working_day'] ? _d($exit['last_working_day']) : '',
                    'settlement_amount' => hr_hook_money($exit['settlement_amount']),
                    'settlement_note'   => (string) ($exit['settlement_note'] ?: ''),
                ]);
            }
        }

        return $id;
    }

    public function delete_exit($id)
    {
        $exit = $this->get_exit($id);
        if ($exit) {
            $this->save_employee($exit['staff_id'], ['status' => 'active']);
        }
        $this->db->where('id', $id)->delete(db_prefix() . 'hr_exits');

        return $this->db->affected_rows() > 0;
    }

    /* ---------------------------------------------------------- Dashboard */

    public function dashboard_stats()
    {
        $today = date('Y-m-d');

        $stats = [];
        $stats['total_employees'] = $this->db->query('SELECT COUNT(*) c FROM ' . db_prefix() . 'staff s
            JOIN ' . db_prefix() . 'hr_employees e ON e.staff_id = s.staffid
            WHERE s.active = 1 AND s.is_not_staff = 0 AND e.status != "exited"')->row()->c;

        $att = $this->db->query('SELECT status, COUNT(*) c FROM ' . db_prefix() . 'hr_attendance
            WHERE att_date = ? GROUP BY status', [$today])->result_array();
        $att_counts = array_column($att, 'c', 'status');
        $stats['present_today'] = (int) ($att_counts['present'] ?? 0) + (int) ($att_counts['half_day'] ?? 0);
        $stats['absent_today']  = (int) ($att_counts['absent'] ?? 0);
        $stats['on_leave_today'] = (int) ($att_counts['leave'] ?? 0);

        $stats['pending_leaves'] = $this->db->where('status', 'pending')->count_all_results(db_prefix() . 'hr_leave_requests');
        $stats['pending_exits']  = $this->db->where_in('status', ['pending', 'cleared'])->count_all_results(db_prefix() . 'hr_exits');

        $stats['on_probation'] = $this->db->query('SELECT COUNT(*) c FROM ' . db_prefix() . 'hr_employees e
            JOIN ' . db_prefix() . 'staff s ON s.staffid = e.staff_id AND s.active = 1
            WHERE e.probation_end >= CURDATE()')->row()->c;

        $stats['new_joiners'] = $this->db->query('SELECT COUNT(*) c FROM ' . db_prefix() . 'hr_employees e
            JOIN ' . db_prefix() . 'staff s ON s.staffid = e.staff_id AND s.active = 1
            WHERE e.date_of_joining >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)')->row()->c;

        return $stats;
    }

    public function department_headcount()
    {
        return $this->db->query('SELECT COALESCE(d.name, "Unassigned") as name, COUNT(*) as cnt
            FROM ' . db_prefix() . 'hr_employees e
            JOIN ' . db_prefix() . 'staff s ON s.staffid = e.staff_id AND s.active = 1 AND s.is_not_staff = 0
            LEFT JOIN ' . db_prefix() . 'departments d ON d.departmentid = e.department_id
            WHERE e.status != "exited"
            GROUP BY e.department_id, d.name ORDER BY cnt DESC')->result_array();
    }

    public function birthdays_today()
    {
        return $this->db->query('SELECT e.date_of_birth, s.staffid, s.firstname, s.lastname, e.employee_code,
                COALESCE(d.name, "") as department
            FROM ' . db_prefix() . 'hr_employees e
            JOIN ' . db_prefix() . 'staff s ON s.staffid = e.staff_id AND s.active = 1 AND s.is_not_staff = 0
            LEFT JOIN ' . db_prefix() . 'departments d ON d.departmentid = e.department_id
            WHERE e.date_of_birth IS NOT NULL
              AND MONTH(e.date_of_birth) = MONTH(CURDATE())
              AND DAY(e.date_of_birth) = DAY(CURDATE())
              AND e.status != "exited"
            ORDER BY s.firstname ASC')->result_array();
    }

    public function birthdays_this_month()
    {
        return $this->db->query('SELECT e.date_of_birth, s.staffid, s.firstname, s.lastname
            FROM ' . db_prefix() . 'hr_employees e
            JOIN ' . db_prefix() . 'staff s ON s.staffid = e.staff_id AND s.active = 1
            WHERE e.date_of_birth IS NOT NULL AND MONTH(e.date_of_birth) = MONTH(CURDATE()) AND e.status != "exited"
            ORDER BY DAY(e.date_of_birth) ASC')->result_array();
    }

    public function recent_joiners($limit = 8)
    {
        return $this->db->query('SELECT e.date_of_joining, e.employee_code, s.staffid, s.firstname, s.lastname
            FROM ' . db_prefix() . 'hr_employees e
            JOIN ' . db_prefix() . 'staff s ON s.staffid = e.staff_id AND s.active = 1
            WHERE e.date_of_joining IS NOT NULL
            ORDER BY e.date_of_joining DESC LIMIT ' . (int) $limit)->result_array();
    }

    /* ------------------------------------------------------------ Notices */

    public function get_notices()
    {
        return $this->db->order_by('id', 'desc')
            ->get(db_prefix() . 'hr_notices')->result_array();
    }

    public function get_notice($id)
    {
        return $this->db->where('id', (int) $id)
            ->get(db_prefix() . 'hr_notices')->row_array();
    }

    public function save_notice($data, $id = null)
    {
        if ($id) {
            $this->db->where('id', (int) $id)->update(db_prefix() . 'hr_notices', $data);

            return (int) $id;
        }
        $data['created_by']   = get_staff_user_id();
        $data['date_created'] = date('Y-m-d H:i:s');
        $this->db->insert(db_prefix() . 'hr_notices', $data);
        $insert_id = (int) $this->db->insert_id();

        // Broadcast on CREATE only, and only for a notice that is live. Editing
        // one is usually a typo fix — nobody wants the whole company messaged
        // twice for that.
        if (!empty($data['active'])) {
            $notice = $this->get_notice($insert_id);
            foreach (hr_hook_notice_audience($notice) as $sid) {
                hr_fire_employee_hook('hr_notice_published', $sid, [
                    'notice_title'    => (string) $notice['title'],
                    'notice_message'  => trim(strip_tags((string) $notice['message'])),
                    'notice_priority' => (string) $notice['priority'],
                    'notice_start'    => $notice['start_date'] ? _d($notice['start_date']) : '',
                    'notice_end'      => $notice['end_date'] ? _d($notice['end_date']) : '',
                ]);
            }
        }

        return $insert_id;
    }

    public function delete_notice($id)
    {
        $this->db->where('id', (int) $id)->delete(db_prefix() . 'hr_notices');

        return $this->db->affected_rows() > 0;
    }

    /**
     * Active notices addressed to one staff member: targeted to everyone, to
     * their role, or to them by name, and inside the optional publish window.
     */
    public function get_notices_for_staff($staff_id)
    {
        $staff_id = (int) $staff_id;
        $row      = $this->db->select('role')->where('staffid', $staff_id)
            ->get(db_prefix() . 'staff')->row();
        $role_id  = $row ? (int) $row->role : 0;

        $notices = $this->db->where('active', 1)
            ->group_start()
                ->where('start_date IS NULL', null, false)
                ->or_where('start_date <=', date('Y-m-d'))
            ->group_end()
            ->group_start()
                ->where('end_date IS NULL', null, false)
                ->or_where('end_date >=', date('Y-m-d'))
            ->group_end()
            ->order_by('priority = "high"', 'desc', false)
            ->order_by('id', 'desc')
            ->get(db_prefix() . 'hr_notices')->result_array();

        $out = [];
        foreach ($notices as $n) {
            if ($n['audience_type'] === 'all') {
                $out[] = $n;
            } elseif ($n['audience_type'] === 'roles') {
                $ids = array_filter(array_map('intval', explode(',', (string) $n['role_ids'])));
                if ($role_id && in_array($role_id, $ids, true)) {
                    $out[] = $n;
                }
            } elseif ($n['audience_type'] === 'employees') {
                $ids = array_filter(array_map('intval', explode(',', (string) $n['staff_ids'])));
                if (in_array($staff_id, $ids, true)) {
                    $out[] = $n;
                }
            }
        }

        return $out;
    }

    /* -------------------------------------------------- Suggestions / Feedback */

    public function add_feedback($data)
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert(db_prefix() . 'hr_feedback', $data);

        return (int) $this->db->insert_id();
    }

    /**
     * Feedback rows for management, joined with the submitter's name (the view
     * decides whether to reveal it for anonymous entries).
     */
    public function get_feedback($where = [])
    {
        $this->db->select('f.*, s.firstname, s.lastname');
        $this->db->from(db_prefix() . 'hr_feedback f');
        $this->db->join(db_prefix() . 'staff s', 's.staffid = f.staff_id', 'left');
        foreach ($where as $k => $v) {
            $this->db->where($k, $v);
        }
        $this->db->order_by('f.id', 'DESC');

        return $this->db->get()->result_array();
    }

    public function get_feedback_row($id)
    {
        $this->db->select('f.*, s.firstname, s.lastname');
        $this->db->from(db_prefix() . 'hr_feedback f');
        $this->db->join(db_prefix() . 'staff s', 's.staffid = f.staff_id', 'left');
        $this->db->where('f.id', (int) $id);

        return $this->db->get()->row_array();
    }

    public function get_staff_feedback($staff_id)
    {
        return $this->db->where('staff_id', (int) $staff_id)
            ->order_by('id', 'DESC')
            ->get(db_prefix() . 'hr_feedback')->result_array();
    }

    public function update_feedback($id, $data)
    {
        $this->db->where('id', (int) $id)->update(db_prefix() . 'hr_feedback', $data);

        return $this->db->affected_rows() >= 0;
    }

    public function delete_feedback($id)
    {
        $this->db->where('id', (int) $id)->delete(db_prefix() . 'hr_feedback');

        return $this->db->affected_rows() > 0;
    }

    public function feedback_status_counts()
    {
        $rows = $this->db->query('SELECT status, COUNT(*) c FROM ' . db_prefix() . 'hr_feedback GROUP BY status')->result_array();

        return array_column($rows, 'c', 'status');
    }

    /* ------------------------------------------------------------ Reports */

    public function headcount_by_type()
    {
        return $this->db->query('SELECT COALESCE(e.employment_type, "unspecified") as type, COUNT(*) as cnt
            FROM ' . db_prefix() . 'hr_employees e
            JOIN ' . db_prefix() . 'staff s ON s.staffid = e.staff_id AND s.active = 1 AND s.is_not_staff = 0
            WHERE e.status != "exited"
            GROUP BY e.employment_type ORDER BY cnt DESC')->result_array();
    }

    public function joins_exits_by_month($months = 12)
    {
        $out = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $m     = date('Y-m', strtotime("-$i months"));
            $joins = $this->db->query('SELECT COUNT(*) c FROM ' . db_prefix() . 'hr_employees
                WHERE DATE_FORMAT(date_of_joining, "%Y-%m") = ?', [$m])->row()->c;
            $exits = $this->db->query('SELECT COUNT(*) c FROM ' . db_prefix() . 'hr_exits
                WHERE DATE_FORMAT(last_working_day, "%Y-%m") = ?', [$m])->row()->c;
            $out[] = ['month' => $m, 'joins' => (int) $joins, 'exits' => (int) $exits];
        }

        return $out;
    }

    /* -------------------------------------------------- Employee self-service
     * Every method here is scoped by staff_id: callers pass the logged-in
     * user's own id (never a value from the request) so an employee can only
     * ever read their own records.
     * ---------------------------------------------------------------------- */

    /**
     * One month of a single employee's attendance, keyed by day-of-month.
     */
    public function get_staff_month_attendance($staff_id, $month, $year)
    {
        $from = sprintf('%04d-%02d-01', $year, $month);
        $to   = date('Y-m-t', strtotime($from));
        $rows = $this->db->query('SELECT * FROM ' . db_prefix() . 'hr_attendance
            WHERE staff_id = ? AND att_date BETWEEN ? AND ?', [$staff_id, $from, $to])->result_array();
        $map = [];
        foreach ($rows as $r) {
            $map[(int) date('j', strtotime($r['att_date']))] = $r;
        }

        return $map;
    }

    /**
     * status => count for one employee over a month (present, absent, ...).
     */
    public function get_staff_attendance_counts($staff_id, $month, $year)
    {
        $from = sprintf('%04d-%02d-01', $year, $month);
        $to   = date('Y-m-t', strtotime($from));
        $rows = $this->db->query('SELECT status, COUNT(*) as cnt FROM ' . db_prefix() . 'hr_attendance
            WHERE staff_id = ? AND att_date BETWEEN ? AND ?
            GROUP BY status', [$staff_id, $from, $to])->result_array();

        return array_column($rows, 'cnt', 'status');
    }

    /**
     * Trainings a given employee is enrolled in (attendee rows joined to the
     * training), newest first.
     */
    public function get_employee_trainings($staff_id)
    {
        return $this->db->query('SELECT t.*, a.attendee_status, a.score, a.remarks, d.name as department_name
            FROM ' . db_prefix() . 'hr_training_attendees a
            JOIN ' . db_prefix() . 'hr_trainings t ON t.id = a.training_id
            LEFT JOIN ' . db_prefix() . 'departments d ON d.departmentid = t.department_id
            WHERE a.staff_id = ?
            ORDER BY t.start_date DESC, t.id DESC', [$staff_id])->result_array();
    }

    /**
     * An employee's payslips, but ONLY from finalized runs (drafts are not
     * shown to employees), newest period first.
     */
    public function get_employee_payslips($staff_id)
    {
        return $this->db->query('SELECT p.id, p.net_pay, p.gross, p.total_deductions, p.lop_days,
                r.month, r.year, r.status as run_status
            FROM ' . db_prefix() . 'hr_payslips p
            JOIN ' . db_prefix() . 'hr_payroll_runs r ON r.id = p.run_id
            WHERE p.staff_id = ? AND r.status = "finalized"
            ORDER BY r.year DESC, r.month DESC', [$staff_id])->result_array();
    }

    /**
     * Documents belonging to one employee that expire within $days.
     */
    public function get_staff_expiring_documents($staff_id, $days = 30)
    {
        return $this->db->query('SELECT * FROM ' . db_prefix() . 'hr_documents
            WHERE staff_id = ? AND expiry_date IS NOT NULL
            AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ' . (int) $days . ' DAY)
            ORDER BY expiry_date ASC', [$staff_id])->result_array();
    }

    /* ------------------------------------------------------ Employee benefits
     * Catalogue of benefits the organisation offers, each scoped to all staff,
     * selected roles, or selected employees.
     * ---------------------------------------------------------------------- */

    public function get_benefits($only_active = false)
    {
        if ($only_active) {
            $this->db->where('is_active', 1);
        }
        $this->db->order_by('sort_order', 'ASC');
        $this->db->order_by('id', 'ASC');

        return $this->db->get(db_prefix() . 'hr_benefits')->result_array();
    }

    public function get_benefit($id)
    {
        return $this->db->get_where(db_prefix() . 'hr_benefits', ['id' => $id])->row_array();
    }

    public function save_benefit($data, $id = null)
    {
        if ($id) {
            $this->db->where('id', $id);
            $this->db->update(db_prefix() . 'hr_benefits', $data);

            return $id;
        }
        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert(db_prefix() . 'hr_benefits', $data);

        return $this->db->insert_id();
    }

    public function delete_benefit($id)
    {
        $this->db->where('id', $id)->delete(db_prefix() . 'hr_benefits');

        return $this->db->affected_rows() > 0;
    }

    /**
     * Active benefits that apply to one employee, by scope:
     *   all       → everyone
     *   roles     → employee's role is in role_ids
     *   employees → employee's staff id is in staff_ids
     */
    public function get_employee_benefits($staff_id, $role_id)
    {
        $out = [];
        foreach ($this->get_benefits(true) as $b) {
            $applies = false;
            if ($b['applies_to'] === 'all') {
                $applies = true;
            } elseif ($b['applies_to'] === 'roles') {
                $rids    = json_decode((string) $b['role_ids'], true) ?: [];
                $applies = in_array((string) $role_id, array_map('strval', $rids), true);
            } elseif ($b['applies_to'] === 'employees') {
                $sids    = json_decode((string) $b['staff_ids'], true) ?: [];
                $applies = in_array((string) $staff_id, array_map('strval', $sids), true);
            }
            if ($applies) {
                $out[] = $b;
            }
        }

        return $out;
    }

    /* ------------------------------------------------------------- Perks */

    /**
     * Perks / office-supplies checklist items, joined with the assignee's name.
     * Optional filters: 'status', 'category'. Ordered so open items (to-order,
     * then ordered) surface above received ones, then by priority and sort order.
     */
    public function get_perk_items($filters = [])
    {
        $this->db->select('p.*, ' . db_prefix() . 'staff.firstname AS assignee_firstname, ' . db_prefix() . 'staff.lastname AS assignee_lastname');
        $this->db->from(db_prefix() . 'hr_perk_items p');
        $this->db->join(db_prefix() . 'staff', db_prefix() . 'staff.staffid = p.assigned_to', 'left');

        if (!empty($filters['status']) && array_key_exists($filters['status'], hr_perk_statuses())) {
            $this->db->where('p.status', $filters['status']);
        }
        if (!empty($filters['category'])) {
            $this->db->where('p.category', $filters['category']);
        }

        // pending (0) and ordered (1) before received (2).
        $this->db->order_by("FIELD(p.status, 'pending', 'ordered', 'received')", '', false);
        $this->db->order_by("FIELD(p.priority, 'high', 'medium', 'low')", '', false);
        $this->db->order_by('p.sort_order', 'ASC');
        $this->db->order_by('p.id', 'ASC');

        return $this->db->get()->result_array();
    }

    public function get_perk_item($id)
    {
        return $this->db->get_where(db_prefix() . 'hr_perk_items', ['id' => (int) $id])->row_array();
    }

    public function save_perk_item($data, $id = null)
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        if ($id) {
            $this->db->where('id', (int) $id);
            $this->db->update(db_prefix() . 'hr_perk_items', $data);

            return (int) $id;
        }
        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert(db_prefix() . 'hr_perk_items', $data);

        return $this->db->insert_id();
    }

    public function delete_perk_item($id)
    {
        $this->db->where('id', (int) $id)->delete(db_prefix() . 'hr_perk_items');

        return $this->db->affected_rows() > 0;
    }

    /**
     * Move a perk item to a new status, stamping ordered_at / received_at the
     * first time it reaches that stage. Returns the saved status or null if the
     * status is not a valid workflow value.
     */
    public function set_perk_status($id, $status)
    {
        if (!array_key_exists($status, hr_perk_statuses())) {
            return null;
        }
        $item = $this->get_perk_item($id);
        if (!$item) {
            return null;
        }

        $update = ['status' => $status, 'updated_at' => date('Y-m-d H:i:s')];
        if ($status === 'ordered' && empty($item['ordered_at'])) {
            $update['ordered_at'] = date('Y-m-d H:i:s');
        }
        if ($status === 'received') {
            if (empty($item['ordered_at'])) {
                $update['ordered_at'] = date('Y-m-d H:i:s');
            }
            if (empty($item['received_at'])) {
                $update['received_at'] = date('Y-m-d H:i:s');
            }
        }

        $this->db->where('id', (int) $id)->update(db_prefix() . 'hr_perk_items', $update);

        return $status;
    }

    /**
     * Count of perk items per workflow status (for the header summary chips).
     */
    public function perk_status_counts()
    {
        $counts = ['pending' => 0, 'ordered' => 0, 'received' => 0];
        $rows   = $this->db->select('status, COUNT(*) AS c')
            ->group_by('status')
            ->get(db_prefix() . 'hr_perk_items')
            ->result_array();
        foreach ($rows as $r) {
            if (isset($counts[$r['status']])) {
                $counts[$r['status']] = (int) $r['c'];
            }
        }

        return $counts;
    }

    /**
     * Remove all received items in one go ("Clear received"). Returns rows deleted.
     */
    public function clear_received_perks()
    {
        $this->db->where('status', 'received')->delete(db_prefix() . 'hr_perk_items');

        return $this->db->affected_rows();
    }

    /* ------------------------------------------------------ Employee Memos */

    /**
     * List memos with employee + issuer names. Optional filters (assoc where).
     */
    public function get_memos($where = [])
    {
        $this->db->select('m.*, s.firstname, s.lastname, e.employee_code,
            i.firstname AS issuer_first, i.lastname AS issuer_last');
        $this->db->from(db_prefix() . 'hr_memos m');
        $this->db->join(db_prefix() . 'staff s', 's.staffid = m.staff_id');
        $this->db->join(db_prefix() . 'hr_employees e', 'e.staff_id = m.staff_id', 'left');
        $this->db->join(db_prefix() . 'staff i', 'i.staffid = m.issued_by', 'left');
        if (!empty($where)) {
            $this->db->where($where);
        }
        $this->db->order_by('m.id', 'DESC');

        return $this->db->get()->result_array();
    }

    public function get_memo($id)
    {
        $this->db->select('m.*, s.firstname, s.lastname, e.employee_code, e.designation_id,
            i.firstname AS issuer_first, i.lastname AS issuer_last');
        $this->db->from(db_prefix() . 'hr_memos m');
        $this->db->join(db_prefix() . 'staff s', 's.staffid = m.staff_id');
        $this->db->join(db_prefix() . 'hr_employees e', 'e.staff_id = m.staff_id', 'left');
        $this->db->join(db_prefix() . 'staff i', 'i.staffid = m.issued_by', 'left');
        $this->db->where('m.id', (int) $id);

        return $this->db->get()->row_array();
    }

    public function get_staff_memos($staff_id)
    {
        return $this->get_memos(['m.staff_id' => (int) $staff_id]);
    }

    public function save_memo($data, $id = null)
    {
        if ($id) {
            $data['updated_at'] = date('Y-m-d H:i:s');
            $this->db->where('id', (int) $id)->update(db_prefix() . 'hr_memos', $data);

            return (int) $id;
        }
        $data['issued_by']  = get_staff_user_id();
        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert(db_prefix() . 'hr_memos', $data);
        $insert_id = (int) $this->db->insert_id();

        $types      = hr_memo_types();
        $severities = hr_memo_severities();
        hr_fire_employee_hook('hr_memo_issued', $data['staff_id'], [
            'memo_type'      => $types[$data['memo_type']]['label'] ?? (string) $data['memo_type'],
            'memo_severity'  => $severities[$data['severity']]['label'] ?? (string) $data['severity'],
            'memo_subject'   => (string) $data['subject'],
            'incident_date'  => !empty($data['incident_date']) ? _d($data['incident_date']) : '',
            'action_taken'   => trim(strip_tags((string) ($data['action_taken'] ?? ''))),
            'penalty_amount' => hr_hook_money($data['penalty_amount'] ?? 0),
            'issuer_name'    => get_staff_full_name($data['issued_by']),
        ]);

        return $insert_id;
    }

    /**
     * Record an employee's acknowledgement receipt for a memo. $agree=false
     * marks it acknowledged-but-disputed. Only touches a memo still "issued".
     */
    public function acknowledge_memo($id, $staff_id, $signature, $note, $agree = true)
    {
        $memo = $this->db->where('id', (int) $id)->where('staff_id', (int) $staff_id)
            ->get(db_prefix() . 'hr_memos')->row_array();
        if (!$memo || $memo['status'] !== 'issued') {
            return false;
        }
        $this->db->where('id', (int) $id)->update(db_prefix() . 'hr_memos', [
            'status'          => $agree ? 'acknowledged' : 'disputed',
            'acknowledged_at' => date('Y-m-d H:i:s'),
            'ack_signature'   => $signature,
            'ack_note'        => $note,
            'ack_agree'       => $agree ? 1 : 0,
        ]);

        // The issuing manager is the audience here; the employee tags describe
        // who signed, so the hook is mapped to a staff member on the panel.
        $statuses = hr_memo_statuses();
        $status   = $agree ? 'acknowledged' : 'disputed';
        hr_fire_employee_hook('hr_memo_acknowledged', $staff_id, [
            'memo_subject'  => (string) $memo['subject'],
            'memo_status'   => $statuses[$status]['label'] ?? $status,
            'ack_signature' => (string) $signature,
            'ack_note'      => trim(strip_tags((string) $note)),
            'issuer_name'   => !empty($memo['issued_by']) ? get_staff_full_name($memo['issued_by']) : '',
        ]);

        return true;
    }

    public function delete_memo($id)
    {
        $memo = $this->db->where('id', (int) $id)->get(db_prefix() . 'hr_memos')->row_array();
        if (!$memo) {
            return false;
        }
        // Best-effort attachment cleanup.
        if (!empty($memo['attachment'])) {
            $path = hr_memo_upload_dir($memo['staff_id']) . $memo['attachment'];
            if (is_file($path)) {
                @unlink($path);
            }
        }
        $this->db->where('id', (int) $id)->delete(db_prefix() . 'hr_memos');

        return $this->db->affected_rows() > 0;
    }

    /**
     * Count of memos per status (for header chips).
     */
    public function memo_status_counts()
    {
        $counts = ['issued' => 0, 'acknowledged' => 0, 'disputed' => 0];
        foreach ($this->db->select('status, COUNT(*) AS c')->group_by('status')
            ->get(db_prefix() . 'hr_memos')->result_array() as $r) {
            if (isset($counts[$r['status']])) {
                $counts[$r['status']] = (int) $r['c'];
            }
        }

        return $counts;
    }

    /* --------------------------------------------------------- Onboarding */

    public function get_onboarding_templates($only_active = false)
    {
        $this->db->select('t.*, (SELECT COUNT(*) FROM ' . db_prefix() . 'hr_onboarding_template_items ti WHERE ti.template_id = t.id) AS item_count');
        $this->db->from(db_prefix() . 'hr_onboarding_templates t');
        if ($only_active) {
            $this->db->where('t.is_active', 1);
        }
        $this->db->order_by('t.is_default', 'DESC');
        $this->db->order_by('t.name', 'ASC');

        return $this->db->get()->result_array();
    }

    public function get_onboarding_template($id)
    {
        return $this->db->where('id', (int) $id)->get(db_prefix() . 'hr_onboarding_templates')->row_array();
    }

    public function get_default_onboarding_template()
    {
        $row = $this->db->where('is_active', 1)->order_by('is_default', 'DESC')->order_by('id', 'ASC')
            ->limit(1)->get(db_prefix() . 'hr_onboarding_templates')->row_array();

        return $row ?: null;
    }

    public function get_template_items($template_id)
    {
        return $this->db->where('template_id', (int) $template_id)
            ->order_by('sort_order', 'ASC')->order_by('id', 'ASC')
            ->get(db_prefix() . 'hr_onboarding_template_items')->result_array();
    }

    public function save_onboarding_template($data, $id = null)
    {
        if ($id) {
            $this->db->where('id', (int) $id)->update(db_prefix() . 'hr_onboarding_templates', $data);

            return (int) $id;
        }
        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert(db_prefix() . 'hr_onboarding_templates', $data);

        return (int) $this->db->insert_id();
    }

    public function delete_onboarding_template($id)
    {
        $this->db->where('template_id', (int) $id)->delete(db_prefix() . 'hr_onboarding_template_items');
        $this->db->where('id', (int) $id)->delete(db_prefix() . 'hr_onboarding_templates');

        return $this->db->affected_rows() > 0;
    }

    /**
     * Replace a template's items with the supplied ordered list. Each row:
     * ['title','description','phase','due_offset_days'].
     */
    public function set_template_items($template_id, $items)
    {
        $this->db->where('template_id', (int) $template_id)->delete(db_prefix() . 'hr_onboarding_template_items');
        $sort = 10;
        foreach ($items as $it) {
            $title = trim((string) ($it['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            $this->db->insert(db_prefix() . 'hr_onboarding_template_items', [
                'template_id'     => (int) $template_id,
                'title'           => mb_substr($title, 0, 191),
                'description'     => mb_substr(trim((string) ($it['description'] ?? '')), 0, 255),
                'phase'           => array_key_exists($it['phase'] ?? '', hr_onboarding_phases()) ? $it['phase'] : 'first_day',
                'due_offset_days' => (int) ($it['due_offset_days'] ?? 0),
                'sort_order'      => $sort,
            ]);
            $sort += 10;
        }
    }

    public function get_onboardings($where = [])
    {
        $this->db->select('o.*, s.firstname, s.lastname, e.employee_code, e.date_of_joining,
            t.name AS template_name,
            (SELECT COUNT(*) FROM ' . db_prefix() . 'hr_onboarding_items oi WHERE oi.onboarding_id = o.id) AS total_items,
            (SELECT COUNT(*) FROM ' . db_prefix() . 'hr_onboarding_items oi WHERE oi.onboarding_id = o.id AND oi.status != "pending") AS done_items');
        $this->db->from(db_prefix() . 'hr_onboarding o');
        $this->db->join(db_prefix() . 'staff s', 's.staffid = o.staff_id');
        $this->db->join(db_prefix() . 'hr_employees e', 'e.staff_id = o.staff_id', 'left');
        $this->db->join(db_prefix() . 'hr_onboarding_templates t', 't.id = o.template_id', 'left');
        if (!empty($where)) {
            $this->db->where($where);
        }
        $this->db->order_by('o.id', 'DESC');

        return $this->db->get()->result_array();
    }

    public function get_onboarding($id)
    {
        $this->db->select('o.*, s.firstname, s.lastname, e.employee_code, e.date_of_joining, t.name AS template_name');
        $this->db->from(db_prefix() . 'hr_onboarding o');
        $this->db->join(db_prefix() . 'staff s', 's.staffid = o.staff_id');
        $this->db->join(db_prefix() . 'hr_employees e', 'e.staff_id = o.staff_id', 'left');
        $this->db->join(db_prefix() . 'hr_onboarding_templates t', 't.id = o.template_id', 'left');
        $this->db->where('o.id', (int) $id);

        return $this->db->get()->row_array();
    }

    public function get_active_onboarding_for_staff($staff_id)
    {
        return $this->db->where('staff_id', (int) $staff_id)
            ->order_by('id', 'DESC')->limit(1)
            ->get(db_prefix() . 'hr_onboarding')->row_array();
    }

    public function get_onboarding_items($onboarding_id)
    {
        return $this->db->select('oi.*, s.firstname AS asg_first, s.lastname AS asg_last')
            ->from(db_prefix() . 'hr_onboarding_items oi')
            ->join(db_prefix() . 'staff s', 's.staffid = oi.assigned_to', 'left')
            ->where('oi.onboarding_id', (int) $onboarding_id)
            ->order_by('oi.sort_order', 'ASC')->order_by('oi.id', 'ASC')
            ->get()->result_array();
    }

    /**
     * Start an onboarding process for an employee from a template. Copies the
     * template items into per-employee task rows, resolving due dates from the
     * employee's joining date (or start_date) + the item offset.
     * Returns the new onboarding id, or an existing one if already present.
     */
    public function start_onboarding($staff_id, $template_id, $start_date = null, $target_date = null)
    {
        $staff_id = (int) $staff_id;
        $tpl      = $this->get_onboarding_template((int) $template_id);
        if (!$tpl) {
            return null;
        }
        $start_date = $start_date ?: date('Y-m-d');

        $this->db->insert(db_prefix() . 'hr_onboarding', [
            'staff_id'    => $staff_id,
            'template_id' => (int) $template_id,
            'status'      => 'in_progress',
            'start_date'  => $start_date,
            'target_date' => $target_date ?: null,
            'created_by'  => get_staff_user_id(),
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
        $onboarding_id = (int) $this->db->insert_id();

        $sort = 10;
        foreach ($this->get_template_items($template_id) as $ti) {
            $due = null;
            if ($ti['due_offset_days'] !== null) {
                $due = date('Y-m-d', strtotime($start_date . ' ' . (int) $ti['due_offset_days'] . ' days'));
            }
            $this->db->insert(db_prefix() . 'hr_onboarding_items', [
                'onboarding_id' => $onboarding_id,
                'title'         => $ti['title'],
                'description'   => $ti['description'],
                'phase'         => $ti['phase'],
                'due_date'      => $due,
                'status'        => 'pending',
                'sort_order'    => $sort,
            ]);
            $sort += 10;
        }

        return $onboarding_id;
    }

    public function update_onboarding($id, $data)
    {
        $this->db->where('id', (int) $id)->update(db_prefix() . 'hr_onboarding', $data);

        return $this->db->affected_rows() >= 0;
    }

    public function delete_onboarding($id)
    {
        $this->db->where('onboarding_id', (int) $id)->delete(db_prefix() . 'hr_onboarding_items');
        $this->db->where('id', (int) $id)->delete(db_prefix() . 'hr_onboarding');

        return $this->db->affected_rows() > 0;
    }

    public function get_onboarding_item($id)
    {
        return $this->db->where('id', (int) $id)->get(db_prefix() . 'hr_onboarding_items')->row_array();
    }

    public function add_onboarding_item($onboarding_id, $data)
    {
        $data['onboarding_id'] = (int) $onboarding_id;
        $max = (int) $this->db->select_max('sort_order', 'm')->where('onboarding_id', (int) $onboarding_id)
            ->get(db_prefix() . 'hr_onboarding_items')->row()->m;
        $data['sort_order'] = $max + 10;
        $this->db->insert(db_prefix() . 'hr_onboarding_items', $data);

        return (int) $this->db->insert_id();
    }

    public function update_onboarding_item($id, $data)
    {
        $this->db->where('id', (int) $id)->update(db_prefix() . 'hr_onboarding_items', $data);

        return $this->db->affected_rows() >= 0;
    }

    public function delete_onboarding_item($id)
    {
        $this->db->where('id', (int) $id)->delete(db_prefix() . 'hr_onboarding_items');

        return $this->db->affected_rows() > 0;
    }

    /**
     * Set a checklist item's status; stamps completed_by/at when done and, when
     * all items are resolved, auto-completes the parent onboarding.
     */
    public function set_onboarding_item_status($id, $status)
    {
        if (!array_key_exists($status, hr_onboarding_item_statuses())) {
            return null;
        }
        $item = $this->get_onboarding_item($id);
        if (!$item) {
            return null;
        }
        $update = ['status' => $status];
        if ($status === 'pending') {
            $update['completed_by'] = null;
            $update['completed_at'] = null;
        } else {
            $update['completed_by'] = get_staff_user_id();
            $update['completed_at'] = date('Y-m-d H:i:s');
        }
        $this->db->where('id', (int) $id)->update(db_prefix() . 'hr_onboarding_items', $update);
        $this->refresh_onboarding_completion((int) $item['onboarding_id']);

        return $status;
    }

    /**
     * Auto-mark an onboarding complete when no item is left pending (and revert
     * to in_progress if a completed one is reopened).
     */
    public function refresh_onboarding_completion($onboarding_id)
    {
        $pending = (int) $this->db->where('onboarding_id', (int) $onboarding_id)
            ->where('status', 'pending')->count_all_results(db_prefix() . 'hr_onboarding_items');
        $total   = (int) $this->db->where('onboarding_id', (int) $onboarding_id)
            ->count_all_results(db_prefix() . 'hr_onboarding_items');
        $ob      = $this->get_onboarding($onboarding_id);
        if (!$ob || $ob['status'] === 'cancelled') {
            return;
        }
        if ($total > 0 && $pending === 0 && $ob['status'] !== 'completed') {
            $this->update_onboarding($onboarding_id, ['status' => 'completed', 'completed_at' => date('Y-m-d H:i:s')]);
        } elseif ($pending > 0 && $ob['status'] === 'completed') {
            $this->update_onboarding($onboarding_id, ['status' => 'in_progress', 'completed_at' => null]);
        }
    }

    /* --------------------------------------------------------- Interviews */

    public function get_interviews($where = [])
    {
        $this->db->select('iv.*, d.name AS designation_name, dep.name AS department_name');
        $this->db->from(db_prefix() . 'hr_interviews iv');
        $this->db->join(db_prefix() . 'hr_designations d', 'd.id = iv.designation_id', 'left');
        $this->db->join(db_prefix() . 'departments dep', 'dep.departmentid = iv.department_id', 'left');
        if (!empty($where)) {
            $this->db->where($where);
        }
        $this->db->order_by('iv.scheduled_at', 'DESC');

        return $this->db->get()->result_array();
    }

    public function get_interview($id)
    {
        $this->db->select('iv.*, d.name AS designation_name, dep.name AS department_name');
        $this->db->from(db_prefix() . 'hr_interviews iv');
        $this->db->join(db_prefix() . 'hr_designations d', 'd.id = iv.designation_id', 'left');
        $this->db->join(db_prefix() . 'departments dep', 'dep.departmentid = iv.department_id', 'left');
        $this->db->where('iv.id', (int) $id);

        return $this->db->get()->row_array();
    }

    public function save_interview($data, $id = null)
    {
        if ($id) {
            $data['updated_at'] = date('Y-m-d H:i:s');
            $this->db->where('id', (int) $id)->update(db_prefix() . 'hr_interviews', $data);

            return (int) $id;
        }
        $data['created_by']  = get_staff_user_id();
        $data['created_at']  = date('Y-m-d H:i:s');
        $this->db->insert(db_prefix() . 'hr_interviews', $data);

        return (int) $this->db->insert_id();
    }

    public function delete_interview($id)
    {
        $iv = $this->db->where('id', (int) $id)->get(db_prefix() . 'hr_interviews')->row_array();
        if ($iv && !empty($iv['resume_file'])) {
            $path = hr_interview_upload_dir() . $iv['resume_file'];
            if (is_file($path)) {
                @unlink($path);
            }
        }
        $this->db->where('id', (int) $id)->delete(db_prefix() . 'hr_interviews');

        return $this->db->affected_rows() > 0;
    }

    /**
     * Count of interviews per status (for header chips).
     */
    public function interview_status_counts()
    {
        $counts = [];
        foreach (array_keys(hr_interview_statuses()) as $s) {
            $counts[$s] = 0;
        }
        foreach ($this->db->select('status, COUNT(*) AS c')->group_by('status')
            ->get(db_prefix() . 'hr_interviews')->result_array() as $r) {
            if (isset($counts[$r['status']])) {
                $counts[$r['status']] = (int) $r['c'];
            }
        }

        return $counts;
    }

    /**
     * Upcoming scheduled interviews (for the HR dashboard / interviewer view).
     */
    public function get_upcoming_interviews($limit = 10)
    {
        return $this->db->where('status', 'scheduled')
            ->where('scheduled_at >=', date('Y-m-d H:i:s'))
            ->order_by('scheduled_at', 'ASC')->limit((int) $limit)
            ->get(db_prefix() . 'hr_interviews')->result_array();
    }
}
