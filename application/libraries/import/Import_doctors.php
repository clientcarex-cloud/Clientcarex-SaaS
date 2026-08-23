<?php

defined('BASEPATH') or exit('No direct script access allowed');
require_once(APPPATH . 'libraries/import/App_import.php');

class Import_doctors extends App_import
{
    protected $notImportableFields = ['id', 'role', 'doctor_profile_type', 'active', 'is_not_staff', 'admin', 'password'];

    // We only require phonenumber from the CSV (full_name or firstname can also satisfy)
    protected $requiredFields = ['phonenumber'];

    // Additional data passed from controller (Role, Profile, Password)
    private $extraData = [];

    // Column mapping: CSV column index => system field name
    private $columnMapping = [];

    // All importable system fields with labels
    private $systemFields = [
        'full_name'         => 'Full Name (splits into First/Last)',
        'firstname'         => 'First Name',
        'lastname'          => 'Last Name',
        'email'             => 'Email',
        'phonenumber'       => 'Phone Number',
        'sex'               => 'Gender',
        'date_of_birth'     => 'Date of Birth',
        'area'              => 'Area',
        'address'           => 'Address',
        'qualification'     => 'Qualification',
        'specialization'    => 'Specialization',
        'designation'       => 'Designation',
        'experience'        => 'Experience (years)',
        'associated_clinic' => 'Associated Clinic/Hospital',
        'professional_id'   => 'Professional ID',
    ];

    public $failedRows = []; // Store failures

    // Temp file path for AJAX-uploaded files
    private $tempFilePath = '';

    public function __construct()
    {
        $this->addDoctorsGuidelines();
        parent::__construct();

        // Define available database fields that we map to
        $this->setDatabaseFields(['firstname', 'lastname', 'email', 'phonenumber', 'password', 'role', 'doctor_profile_type', 'address', 'associated_clinic', 'sex', 'date_of_birth', 'area', 'qualification', 'specialization', 'designation', 'experience', 'professional_id']);
    }

    /**
     * Get all system fields available for mapping
     */
    public function getSystemFields()
    {
        return $this->systemFields;
    }

    public function setExtraData($data)
    {
        $this->extraData = $data;
    }

    /**
     * Set column mapping from controller
     * @param array $mapping  [csv_col_index => system_field_name]
     */
    public function setColumnMapping($mapping)
    {
        $this->columnMapping = $mapping;
    }

    /**
     * Get column mapping
     */
    public function getColumnMapping()
    {
        return $this->columnMapping;
    }

    /**
     * Set temp file path for pre-uploaded file
     */
    public function setTempFilePath($path)
    {
        $this->tempFilePath = $path;
    }

    /**
     * Parse CSV headers and first N preview rows
     * @param string $filePath  path to the CSV file
     * @param int $previewRows  number of preview rows to return
     * @return array  ['headers' => [...], 'preview_rows' => [[...], ...], 'total_rows' => int]
     */
    public function parseCsvHeaders($filePath, $previewRows = 3)
    {
        $result = [
            'headers'      => [],
            'preview_rows' => [],
            'total_rows'   => 0,
        ];

        if (!file_exists($filePath)) {
            return $result;
        }

        $fd = fopen($filePath, 'r');
        if (!$fd) {
            return $result;
        }

        $rowIndex = 0;
        while (($row = fgetcsv($fd)) !== false) {
            if ($rowIndex === 0) {
                // First row = headers
                $result['headers'] = array_map('trim', $row);
            } else {
                if ($rowIndex <= $previewRows) {
                    $result['preview_rows'][] = array_map('trim', $row);
                }
            }
            $rowIndex++;
        }

        fclose($fd);

        // total_rows excludes header
        $result['total_rows'] = max(0, $rowIndex - 1);

        return $result;
    }

    /**
     * Auto-match CSV headers to system fields
     * @param array $csvHeaders  array of CSV column header strings
     * @return array  [csv_col_index => system_field_name]  only matched fields
     */
    public function autoMatchHeaders($csvHeaders)
    {
        $mapping = [];

        // Define fuzzy match patterns
        $patterns = [
            'full_name'         => ['full name', 'full_name', 'fullname', 'name', 'doctor name', 'dr name', 'doctor_name'],
            'firstname'         => ['first name', 'first_name', 'firstname', 'first', 'given name'],
            'lastname'          => ['last name', 'last_name', 'lastname', 'last', 'surname', 'family name'],
            'email'             => ['email', 'e-mail', 'email address', 'mail'],
            'phonenumber'       => ['phone', 'phone number', 'phonenumber', 'phone_number', 'mobile', 'mobile number', 'contact', 'contact number', 'cell', 'telephone', 'tel'],
            'sex'               => ['sex', 'gender'],
            'date_of_birth'     => ['date of birth', 'date_of_birth', 'dob', 'birth date', 'birthdate', 'birthday'],
            'area'              => ['area', 'locality', 'region', 'zone'],
            'address'           => ['address', 'addr', 'location', 'street'],
            'qualification'     => ['qualification', 'qualifications', 'degree', 'education'],
            'specialization'    => ['specialization', 'specialisation', 'specialty', 'speciality', 'specialist'],
            'designation'       => ['designation', 'title', 'position', 'job title'],
            'experience'        => ['experience', 'exp', 'years of experience', 'years experience', 'yrs'],
            'associated_clinic' => ['associated clinic', 'associated_clinic', 'clinic', 'hospital', 'clinic/hospital', 'clinic hospital', 'associated clinic/hospital', 'associated hospital'],
            'professional_id'   => ['professional id', 'professional_id', 'license', 'license number', 'registration number', 'reg no', 'license no', 'medical license', 'nmc', 'pmc'],
        ];

        $usedFields = [];

        foreach ($csvHeaders as $index => $header) {
            $headerLower = strtolower(trim($header));

            foreach ($patterns as $fieldName => $matchPatterns) {
                if (in_array($fieldName, $usedFields)) {
                    continue; // Don't map same system field twice
                }

                foreach ($matchPatterns as $pattern) {
                    if ($headerLower === $pattern || strpos($headerLower, $pattern) !== false) {
                        $mapping[$index] = $fieldName;
                        $usedFields[] = $fieldName;
                        break 2; // Break both foreach loops
                    }
                }
            }
        }

        return $mapping;
    }

    /**
     * Initialize from a temp file that was already saved (AJAX flow)
     */
    public function initializeFromTempFile()
    {
        if (empty($this->tempFilePath) || !file_exists($this->tempFilePath)) {
            set_alert('warning', _l('import_upload_failed'));
            redirect($this->failureRedirectURL());
        }

        // Read the file
        $fd = fopen($this->tempFilePath, 'r');
        $rows = [];
        while (($row = fgetcsv($fd)) !== false) {
            $rows[] = $row;
        }
        fclose($fd);

        $this->totalRows = count($rows);

        if ($this->totalRows <= 1) {
            set_alert('warning', 'Not enough rows for importing');
            redirect($this->failureRedirectURL());
        }

        // Remove header row
        unset($rows[0]);
        $this->setRows($rows);

        return $this;
    }

    public function perform()
    {
        // If we have a temp file path, use that; otherwise do the standard initialize
        if (!empty($this->tempFilePath)) {
            $this->initializeFromTempFile();
        } else {
            $this->initialize();
        }

        $rows = $this->getRows();
        $mapping = $this->columnMapping;

        foreach ($rows as $rowNumber => $row) {
            $mappedData = [];

            // Apply column mapping to extract data
            foreach ($mapping as $csvIndex => $fieldName) {
                if ($fieldName === '' || $fieldName === 'skip') {
                    continue;
                }
                $csvIndex = intval($csvIndex);
                $mappedData[$fieldName] = isset($row[$csvIndex]) ? trim($row[$csvIndex]) : '';
            }

            // Handle full_name splitting
            $firstName = '';
            $lastName = '';

            if (isset($mappedData['full_name']) && !empty($mappedData['full_name'])) {
                $parts = explode(' ', $mappedData['full_name'], 2);
                $firstName = isset($parts[0]) ? trim($parts[0]) : '';
                $lastName = isset($parts[1]) ? trim($parts[1]) : '';
                unset($mappedData['full_name']);
            }

            // Direct firstname/lastname override
            if (isset($mappedData['firstname']) && !empty($mappedData['firstname'])) {
                $firstName = $mappedData['firstname'];
            }
            if (isset($mappedData['lastname']) && !empty($mappedData['lastname'])) {
                $lastName = $mappedData['lastname'];
            }

            if ($lastName == '') {
                $lastName = '.';
            }

            $phoneNumber = isset($mappedData['phonenumber']) ? $mappedData['phonenumber'] : '';

            // Generate Email if not provided
            $email = '';
            if (isset($mappedData['email']) && !empty($mappedData['email'])) {
                $email = $mappedData['email'];
            } else {
                $cleanPhone = preg_replace('/[^0-9]/', '', $phoneNumber);
                if (empty($cleanPhone)) {
                    $cleanPhone = time() . rand(10, 99);
                }
                $email = $cleanPhone . '@clientcarex.com';
            }

            // Ensure Email Uniqueness
            $counter = 1;
            $baseEmail = $email;
            while (true) {
                $this->ci->db->where('email', $email);
                $count = $this->ci->db->count_all_results(db_prefix() . 'staff');
                if ($count == 0) {
                    break;
                }
                $emailParts = explode('@', $baseEmail);
                $email = $emailParts[0] . '_' . $counter . '@' . (isset($emailParts[1]) ? $emailParts[1] : 'clientcarex.com');
                $counter++;
            }

            // Additional Data from controller
            $roleId = isset($this->extraData['role']) ? $this->extraData['role'] : 0;
            $profileType = isset($this->extraData['profile_type']) ? $this->extraData['profile_type'] : '';
            $password = isset($this->extraData['password']) ? $this->extraData['password'] : '123456';

            $insert = [
                'firstname'           => $firstName,
                'lastname'            => $lastName,
                'email'               => $email,
                'phonenumber'         => $phoneNumber,
                'password'            => $password,
                'role'                => $roleId,
                'doctor_profile_type' => $profileType,
                'datecreated'         => date('Y-m-d H:i:s'),
                'active'              => 1,
                'is_not_staff'        => 0,
            ];

            // Add any extra mapped fields
            $extraFields = ['address', 'associated_clinic', 'sex', 'date_of_birth', 'area', 'qualification', 'specialization', 'designation', 'experience', 'professional_id'];
            foreach ($extraFields as $field) {
                if (isset($mappedData[$field]) && $mappedData[$field] !== '') {
                    $insert[$field] = $mappedData[$field];
                }
            }

            // Build display name for error reporting
            $displayName = $firstName . ' ' . $lastName;

            if (count($insert) > 0) {
                if (!$this->isSimulation()) {
                    $id = $this->ci->staff_model->add($insert);

                    if ($id) {
                        $this->incrementImported();
                    } else {
                        $db_error = $this->ci->db->error();
                        $reason = isset($db_error['message']) && !empty($db_error['message']) ? $db_error['message'] : 'Database Insert Failed (Check if Password is too short or fields missing)';
                        $this->failedRows[] = [
                            'line'   => $rowNumber + 1,
                            'name'   => $displayName,
                            'reason' => $reason,
                        ];
                    }
                } else {
                    $this->simulationData[$rowNumber] = $this->formatValuesForSimulation($insert, $displayName);
                }
            }
        }
    }

    private function formatValuesForSimulation($values, $displayName)
    {
        // Get Role Name
        $roleName = '';
        if (!empty($values['role'])) {
            $role = $this->ci->roles_model->get($values['role']);
            if ($role) {
                $roleName = $role->name;
            }
        }

        $result = [
            'full_name'     => $displayName,
            'firstname'     => $values['firstname'],
            'lastname'      => $values['lastname'],
            'phonenumber'   => $values['phonenumber'],
            'email'         => $values['email'],
            'role'          => $roleName,
            'profile_type'  => $values['doctor_profile_type'],
            'password'      => '******',
        ];

        // Add extra fields that were mapped
        $extraFields = ['address', 'associated_clinic', 'sex', 'date_of_birth', 'area', 'qualification', 'specialization', 'designation', 'experience', 'professional_id'];
        foreach ($extraFields as $field) {
            if (isset($values[$field])) {
                $result[$field] = $values[$field];
            }
        }

        return $result;
    }

    public function createSampleTableHtml($simulation = false)
    {
        // Dynamic headers based on what was mapped + always show core fields
        $headers = [
            'full_name'    => 'Full Name',
            'firstname'    => 'First Name',
            'lastname'     => 'Last Name',
            'phonenumber'  => 'Phone',
            'email'        => 'Email',
            'role'         => 'Role',
            'profile_type' => 'Profile Type',
            'password'     => 'Password',
        ];

        // Add headers for extra mapped fields
        $extraLabels = [
            'address'           => 'Address',
            'associated_clinic' => 'Associated Clinic/Hospital',
            'sex'               => 'Gender',
            'date_of_birth'     => 'Date of Birth',
            'area'              => 'Area',
            'qualification'     => 'Qualification',
            'specialization'    => 'Specialization',
            'designation'       => 'Designation',
            'experience'        => 'Experience',
            'professional_id'   => 'Professional ID',
        ];

        // Check simulation data to see which extra fields were actually mapped
        if ($simulation) {
            $simData = $this->getSimulationData();
            if (!empty($simData)) {
                $firstRow = $simData[0];
                foreach ($extraLabels as $key => $label) {
                    if (isset($firstRow[$key])) {
                        $headers[$key] = $label;
                    }
                }
            }
        }

        $table = '<div class="table-responsive no-dt">';
        $table .= '<table class="table table-hover table-bordered no-mtop">';
        $table .= '<thead><tr>';

        foreach ($headers as $key => $label) {
            $table .= '<th class="bold">' . $label . '</th>';
        }

        $table .= '</tr></thead><tbody>';

        if ($simulation) {
            foreach ($this->getSimulationData() as $row) {
                $table .= '<tr>';
                foreach ($headers as $key => $label) {
                    $val = isset($row[$key]) ? $row[$key] : '';
                    $table .= '<td>' . htmlspecialchars($val) . '</td>';
                }
                $table .= '</tr>';
            }
        } else {
            $table .= '<tr>';
            $table .= '<td colspan="' . count($headers) . '">Upload a file to see simulation data...</td>';
            $table .= '</tr>';
        }

        $table .= '</tbody></table></div>';

        return $table;
    }

    private function addDoctorsGuidelines()
    {
        $this->addImportGuidelinesInfo('Upload any CSV file. The system will detect your columns and let you map them to the correct fields.');
        $this->addImportGuidelinesInfo('System will split Full Name into First/Last name if mapped. e.g. "John Doe" => First: John, Last: Doe.');
        $this->addImportGuidelinesInfo('Email will be auto-generated as <b>[PhoneNumber]@clientcarex.com</b> if not mapped from CSV.');
    }

    protected function failureRedirectURL()
    {
        return admin_url('doctors/import');
    }

    public function downloadSample()
    {
        header('Pragma: public');
        header('Expires: 0');
        header('Content-Type: application/csv');
        header('Content-Disposition: attachment; filename="doctors_import_sample.csv";');
        header('Content-Transfer-Encoding: binary');

        echo "Full Name,Phone Number,Email,Gender,Date of Birth,Area,Address,Qualification,Specialization,Designation,Experience,Associated Clinic/Hospital,Professional ID\n";
        echo "John Doe,9876543210,john@example.com,Male,1985-06-15,Downtown,123 Main St,MBBS,Cardiology,Senior Consultant,15,City Hospital,PMC-12345\n";
        echo "Dr. Jane Smith,1234567890,,Female,1990-03-20,Suburbs,456 Oak Ave,MD,Dermatology,Consultant,8,Community Clinic,PMC-67890\n";

        exit;
    }
}
