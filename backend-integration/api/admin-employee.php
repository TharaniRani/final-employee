<?php
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, private');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/document-storage.php';
require_once __DIR__ . '/../config/secure-session.php';
require_once __DIR__ . '/../config/admin-auth.php';
require_once __DIR__ . '/../config/hrms-repository.php';
require_once __DIR__ . '/../config/submission-staging.php';
startSecureSession();

function adminEmployeeRespond(int $status, array $body): void
{
    http_response_code($status);
    echo json_encode($body, JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function adminEmployeeEffectiveValue($value)
{
    if (!is_array($value)) {
        return $value;
    }
    if (array_key_exists('original_value', $value)) {
        if (($value['verification'] ?? '') === 'wrong' &&
            isset($value['corrected_value']) && $value['corrected_value'] !== '') {
            return $value['corrected_value'];
        }
        return $value['original_value'];
    }
    foreach ($value as $key => $item) {
        $value[$key] = adminEmployeeEffectiveValue($item);
    }
    return $value;
}

function adminEmployeeValidateCollection(array $rows, array $requiredFields, string $label): void
{
    if (!$rows) {
        throw new DomainException("Add at least one {$label} entry.");
    }
    foreach ($rows as $index => $row) {
        foreach ($requiredFields as $field => $fieldLabel) {
            if (trim((string) ($row[$field] ?? '')) === '') {
                throw new DomainException("Enter {$fieldLabel} for {$label} entry " . ($index + 1) . '.');
            }
        }
    }
}

function adminEmployeeValidateConditionalSubmissionEdits(array $data, array $changes): void
{
    $referralFields = [
        'submission.referral.family_working',
        'submission.referral.family_working_details'
    ];
    if (array_intersect($referralFields, array_keys($changes))) {
        if (($data['referral']['family_members_working'] ?? '') === 'Yes') {
            adminEmployeeValidateCollection(
                $data['referral']['family_members'] ?? [],
                [
                    'tmss_id' => 'the MSS Employee ID',
                    'name' => 'the name',
                    'unit' => 'the unit',
                    'designation' => 'the designation'
                ],
                'family member working'
            );
        }
    }
    $studyFields = [
        'submission.referral.family_studying',
        'submission.referral.family_studying_details'
    ];
    if (array_intersect($studyFields, array_keys($changes)) &&
        ($data['referral']['family_members_studying'] ?? '') === 'Yes') {
        adminEmployeeValidateCollection(
            $data['referral']['students'] ?? [],
            ['name' => 'the name', 'school' => 'the school', 'class' => 'the class'],
            'family member studying'
        );
    }
    $teacherFields = array_filter(
        array_keys($changes),
        static fn(string $field): bool => str_starts_with($field, 'submission.teacher_documents.')
    );
    $teacher = $data['teacher_details'] ?? [];
    $isTeacher = in_array($teacher['is_teacher'] ?? false, [true, 1, '1', 'Yes', 'yes'], true);
    if ($teacherFields && $isTeacher) {
        $status = (string) ($teacher['bed_status'] ?? '');
        if (!in_array($status, ['Joined', 'Completed'], true) ||
            trim((string) ($teacher['institution'] ?? '')) === '') {
            throw new DomainException('Enter the B.Ed status and institution when the employee is a teacher.');
        }
        $yearField = $status === 'Joined' ? 'proposed_graduation_year' : 'graduation_year';
        if (!preg_match('/^[0-9]{4}$/', (string) ($teacher[$yearField] ?? ''))) {
            throw new DomainException('Enter the required four-digit B.Ed year.');
        }
    }
    if (isset($changes['educational_qualifications.all'])) {
        $education = $data['education'] ?? [];
        adminEmployeeValidateCollection(
            $education,
            [
                'qualification_type' => 'the qualification type',
                'qualification_name' => 'the qualification name',
                'institution' => 'the institution',
                'year_of_completion' => 'the completion year'
            ],
            'qualification'
        );
        foreach ($education as $index => $entry) {
            if (($entry['qualification_type'] ?? '') === 'Others' &&
                trim((string) ($entry['skilled_in'] ?? '')) === '') {
                throw new DomainException('Enter the skills for qualification entry ' . ($index + 1) . '.');
            }
            if (!preg_match('/^[0-9]{4}$/', (string) ($entry['year_of_completion'] ?? ''))) {
                throw new DomainException('Enter a valid completion year for qualification entry ' . ($index + 1) . '.');
            }
        }
    }
    if (isset($changes['submission.work_experience.not_applicable']) ||
        isset($changes['employee_previous_experience.all'])) {
        if (empty($data['work_experience_not_applicable'])) {
            $experiences = $data['work_experience'] ?? [];
            adminEmployeeValidateCollection(
                $experiences,
                [
                    'company' => 'the company',
                    'designation' => 'the designation',
                    'start_month' => 'the starting month',
                    'end_month' => 'the leaving month',
                    'details' => 'the experience details'
                ],
                'work experience'
            );
        }
    }
}

function adminEmployeePromoteDocument(
    PDO $pdo,
    int $employeeId,
    string $column,
    string $value,
    array &$promotedDocuments
): string {
    $documentTypes = [
        'aadhar_copy' => 'aadhaar',
        'pan_copy' => 'pan',
        'photo' => 'photo',
        'resume' => 'resume',
        'staff_sign' => 'signature',
        'driving' => 'driving',
        'pg_degree_doc' => 'bed',
        'certificate_path' => 'education_certificate',
        'pay_slip' => 'experience_payslip',
        'experience_letter_path' => 'experience_letter'
    ];
    if (!str_starts_with(str_replace('\\', '/', $value), 'employees_temp_documents/')) {
        if (basename(str_replace('\\', '/', $value)) !== $value ||
            submissionResolveStoredDocumentPath(
                dirname(__DIR__, 2),
                $employeeId,
                $documentTypes[$column] ?? '',
                $value
            ) === null) {
            return $value;
        }
        $value = submissionTemporaryDocumentRelativePath(
            $employeeId,
            $documentTypes[$column],
            $value
        );
    }
    if (isset($promotedDocuments[$value])) {
        return $promotedDocuments[$value];
    }
    if (!isset($documentTypes[$column])) {
        throw new DomainException('A replacement document is assigned to an unsupported employee field.');
    }
    $result = submissionPromoteDocumentPath(
        dirname(__DIR__, 2),
        $employeeId,
        $documentTypes[$column],
        $value
    );
    if ($result['temporary_path'] !== null) {
        $promotedDocuments[$value] = $result['path'];
    }
    return $result['path'];
}

function adminEmployeeSetPath(array &$data, array $path, $value): void
{
    $target = &$data;
    foreach (array_slice($path, 0, -1) as $part) {
        if (!isset($target[$part]) || !is_array($target[$part])) {
            $target[$part] = [];
        }
        $target = &$target[$part];
    }
    $target[$path[count($path) - 1]] = $value;
    unset($target);
}

function adminEmployeeSubmissionPathForField(string $field): ?array
{
    $paths = [
        'employees.full_name' => ['personal_basic', 'full_name', 'value'],
        'employees.date_of_birth' => ['personal_details', 'dob'],
        'employees.gender' => ['personal_details', 'gender'],
        'employees.marital_status' => ['personal_details', 'marital_status'],
        'employees.spouse_name' => ['personal_details', 'spouse_name'],
        'employees.father_name' => ['personal_details', 'father_name'],
        'employees.mother_name' => ['personal_details', 'mother_name'],
        'employees.blood_group' => ['personal_details', 'blood_group'],
        'employees.alumni_detail' => ['personal_basic', 'is_alumni'],
        'employees.alumni_unit' => ['personal_basic', 'alumni_school'],
        'employee_contact.personal_email' => ['personal_basic', 'email', 'value'],
        'employee_contact.local_address' => ['address', 'current', 'address1'],
        'employee_contact.local_address1' => ['address', 'current', 'address2'],
        'employee_contact.local_address2' => ['address', 'current', 'address3'],
        'employee_contact.pincode' => ['address', 'current', 'pincode'],
        'employee_contact.city' => ['address', 'current', 'city'],
        'employee_contact.state' => ['address', 'current', 'state'],
        'employee_contact.country' => ['address', 'current', 'country'],
        'employee_contact.permanent_address' => ['address', 'permanent', 'address1'],
        'employee_contact.permanent_address1' => ['address', 'permanent', 'address2'],
        'employee_contact.permanent_address2' => ['address', 'permanent', 'address3'],
        'employee_contact.perm_pincode' => ['address', 'permanent', 'pincode'],
        'employee_contact.perm_city' => ['address', 'permanent', 'city'],
        'employee_contact.perm_state' => ['address', 'permanent', 'state'],
        'employee_contact.perm_country' => ['address', 'permanent', 'country'],
        'employee_contact.nok_name' => ['nok', 'name'],
        'employee_contact.nok_contact_no' => ['nok', 'contact_no'],
        'employee_contact.nok_address' => ['nok', 'address', 'address1'],
        'employee_contact.nok_emergency_no' => ['nok', 'emergency_contact_no'],
        'employee_identification.aadhar_no' => ['documents', 'aadhaar', 'number'],
        'employee_identification.pan_no' => ['documents', 'pan', 'number'],
        'employee_identification.aadharlinked' => ['documents', 'aadhaar_pan_linked'],
        'employee_employment.unit' => ['employment_details', 'unit'],
        'employee_employment.branch' => ['employment_details', 'campus'],
        'employee_employment.designation' => ['employment_details', 'designation'],
        'employee_employment.employee_type' => ['employment_details', 'employee_type'],
        'employee_employment.job_type' => ['employment_details', 'job_type'],
        'employee_employment.reporting_to' => ['employment_details', 'reporting_to'],
        'employee_employment.date_of_joining' => ['employment_details', 'date_of_joining']
    ];
    return $paths[$field] ?? null;
}

function adminEmployeeApplyStagedChanges(
    PDO $pdo,
    int $employeeId,
    int $submissionId,
    int $adminId,
    array $changedFields,
    array &$formData
): void {
    $scalarMaps = [
        'employees' => ['temp_employees', 'employees', 'employee_id'],
        'employee_contact' => ['temp_employee_contact', 'employee_contact', 'contact_id'],
        'employee_identification' => ['temp_employee_identification', 'employee_identification', 'id'],
        'employee_employment' => ['temp_employee_employment', 'employee_employment', 'employment_id']
    ];
    $approvedColumns = [
        'employees' => [
            'date_of_birth', 'gender', 'marital_status', 'spouse_name', 'father_name',
            'mother_name', 'blood_group', 'alumni_detail', 'alumni_unit'
        ],
        'employee_contact' => [
            'personal_email', 'local_address', 'local_address1', 'local_address2',
            'pincode', 'city', 'state', 'country', 'permanent_address',
            'permanent_address1', 'permanent_address2', 'perm_pincode', 'perm_city',
            'perm_state', 'perm_country', 'nok_name', 'nok_contact_no', 'nok_address',
            'nok_emergency_no'
        ],
        'employee_identification' => [
            'aadhar_no', 'aadhar_copy', 'aadharlinked', 'pan_no', 'pan_copy',
            'photo', 'resume', 'staff_sign', 'driving', 'pg_degree_status',
            'pg_degree_institution', 'pg_degree_doc'
        ],
        'employee_employment' => [
            'unit', 'branch', 'designation', 'employee_type', 'job_type',
            'reporting_to', 'date_of_joining'
        ]
    ];
    $collectionMaps = [
        'employee_referrals.all' => ['temp_employee_referrals', 'employee_referrals', 'referral_id'],
        'educational_qualifications.all' => [
            'temp_educational_qualifications', 'educational_qualifications', 'id'
        ],
        'employee_previous_experience.all' => [
            'temp_employee_previous_experience', 'employee_previous_experience', 'id'
        ]
    ];
    $promotedDocuments = [];
    foreach ($changedFields as $field) {
        if (isset($collectionMaps[$field])) {
            [$stagingTable, $originalTable, $primaryKey] = $collectionMaps[$field];
            $selectRows = $pdo->prepare(
                "SELECT * FROM `{$stagingTable}` WHERE employee_id = ? AND submission_id = ? ORDER BY `{$primaryKey}`"
            );
            $selectRows->execute([$employeeId, $submissionId]);
            $rows = $selectRows->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as &$row) {
                foreach (['certificate_path', 'pay_slip', 'experience_letter_path'] as $documentColumn) {
                    if (isset($row[$documentColumn]) && is_string($row[$documentColumn])) {
                        $row[$documentColumn] = adminEmployeePromoteDocument(
                            $pdo,
                            $employeeId,
                            $documentColumn,
                            $row[$documentColumn],
                            $promotedDocuments
                        );
                    }
                }
            }
            unset($row);
            $deleteOriginal = $pdo->prepare("DELETE FROM `{$originalTable}` WHERE employee_id = ?");
            $deleteOriginal->execute([$employeeId]);
            foreach ($rows as $row) {
                insertStagingRow($pdo, $originalTable, $row, [$primaryKey, 'submission_id']);
            }
            if ($field === 'educational_qualifications.all') {
                foreach ($formData['education'] ?? [] as $index => &$entry) {
                    if (isset($rows[$index]['certificate_path'])) {
                        $entry['certificate_file_path'] = $rows[$index]['certificate_path'];
                    }
                }
                unset($entry);
            } elseif ($field === 'employee_previous_experience.all') {
                foreach ($formData['work_experience'] ?? [] as $index => &$entry) {
                    if (isset($rows[$index]['experience_letter_path'])) {
                        $entry['experience_letter_file_path'] = $rows[$index]['experience_letter_path'];
                    }
                    if (isset($rows[$index]['pay_slip'])) {
                        $entry['pay_slip_file_path'] = $rows[$index]['pay_slip'];
                    }
                }
                unset($entry);
            }
            continue;
        }
        if (!str_contains($field, '.')) {
            continue;
        }
        [$table, $column] = explode('.', $field, 2);
        if (!isset($scalarMaps[$table]) ||
            !in_array($column, $approvedColumns[$table] ?? [], true)) {
            continue;
        }
        [$stagingTable, $originalTable, $primaryKey] = $scalarMaps[$table];
        $selectValue = $pdo->prepare(
            "SELECT `{$column}` FROM `{$stagingTable}` WHERE employee_id = ? AND submission_id = ? ORDER BY `{$primaryKey}` DESC LIMIT 1"
        );
        $selectValue->execute([$employeeId, $submissionId]);
        $value = $selectValue->fetchColumn();
        if ($value === false) {
            continue;
        }
        if (is_string($value)) {
            $value = adminEmployeePromoteDocument(
                $pdo,
                $employeeId,
                $column,
                $value,
                $promotedDocuments
            );
        }
        $selectId = $pdo->prepare(
            "SELECT `{$primaryKey}` FROM `{$originalTable}` WHERE employee_id = ? ORDER BY `{$primaryKey}` DESC LIMIT 1 FOR UPDATE"
        );
        $selectId->execute([$employeeId]);
        $rowId = $selectId->fetchColumn();
        if ($rowId === false) {
            throw new DomainException("No original row exists to update for {$field}.");
        }
        $update = $pdo->prepare(
            "UPDATE `{$originalTable}` SET `{$column}` = ? WHERE `{$primaryKey}` = ? AND employee_id = ?"
        );
        $update->execute([$value, $rowId, $employeeId]);
        $documentPaths = [
            'employee_identification.aadhar_copy' => ['documents', 'aadhaar', 'file_path'],
            'employee_identification.pan_copy' => ['documents', 'pan', 'file_path'],
            'employee_identification.photo' => ['personal_details', 'photo_file_path'],
            'employee_identification.resume' => ['documents', 'resume', 'file_path'],
            'employee_identification.staff_sign' => ['personal_details', 'signature_file_path'],
            'employee_identification.pg_degree_doc' => ['teacher_details', 'document_file_path']
        ];
        if (isset($documentPaths[$field])) {
            adminEmployeeSetPath($formData, $documentPaths[$field], $value);
        }
    }

    foreach ($formData['other_documents'] ?? [] as &$document) {
        if (!is_array($document) || !isset($document['file_path']) || !is_string($document['file_path'])) {
            continue;
        }
        $type = (string) ($document['type'] ?? 'other_document');
        $type = [
            'driving_license' => 'driving-license',
            'police_verification' => 'police-verification'
        ][$type] ?? $type;
        if (!in_array($type, ['driving-license', 'police-verification', 'other_document', 'ccs'], true)) {
            $type = 'other_document';
        }
        if (str_starts_with(str_replace('\\', '/', $document['file_path']), 'employees_temp_documents/')) {
            $oldPath = $document['file_path'];
            if (isset($promotedDocuments[$oldPath])) {
                $document['file_path'] = $promotedDocuments[$oldPath];
            } else {
                $result = submissionPromoteDocumentPath(
                    dirname(__DIR__, 2),
                    $employeeId,
                    $type,
                    $oldPath
                );
                $document['file_path'] = $result['path'];
                $promotedDocuments[$oldPath] = $result['path'];
            }
        }
    }
    unset($document);
}

$admin = currentAdminUser();
if ($admin === null) {
    adminEmployeeRespond(403, ['success' => false, 'message' => 'Admin authorization is required.']);
}

if (!hash_equals($_SESSION['csrf_token'] ?? '', (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))) {
    adminEmployeeRespond(419, ['success' => false, 'message' => 'Refresh the page and try again.']);
}

function adminEditableEmployeeFields(): array
{
    $fields = [];
    $groups = [
        'employees' => [
            'full_name' => ['Full name', 'text', 255, true],
            'date_of_birth' => ['Date of birth', 'date', 10, false],
            'gender' => ['Gender', 'select', 6, false],
            'marital_status' => ['Marital status', 'select', 8, false],
            'spouse_name' => ['Spouse name', 'text', 255, false],
            'father_name' => ['Father name', 'text', 255, false],
            'mother_name' => ['Mother name', 'text', 255, false],
            'blood_group' => ['Blood group', 'select', 4, false],
            'alumni_detail' => ['Alumni status', 'select', 5, false],
            'alumni_unit' => ['Alumni school', 'school', 255, false]
        ],
        'employee_contact' => [
            'personal_email' => ['Email', 'email', 255, false],
            'local_address' => ['Current address line 1', 'text', 65535, false],
            'local_address1' => ['Current address line 2', 'text', 65535, false],
            'local_address2' => ['Current address line 3', 'text', 65535, false],
            'pincode' => ['Current PIN code', 'pincode', 6, true],
            'city' => ['Current city', 'text', 100, true],
            'state' => ['Current state', 'state', 100, true],
            'country' => ['Current country', 'country', 100, true],
            'permanent_address' => ['Permanent address line 1', 'text', 65535, false],
            'permanent_address1' => ['Permanent address line 2', 'text', 65535, false],
            'permanent_address2' => ['Permanent address line 3', 'text', 65535, false],
            'perm_pincode' => ['Permanent PIN code', 'pincode', 6, true],
            'perm_city' => ['Permanent city', 'text', 100, true],
            'perm_state' => ['Permanent state', 'state', 100, true],
            'perm_country' => ['Permanent country', 'country', 100, true],
            'nok_name' => ['Emergency contact name', 'text', 255, false],
            'nok_contact_no' => ['Emergency contact number', 'mobile', 10, true],
            'nok_address' => ['Emergency contact address', 'text', 65535, false],
            'nok_emergency_no' => ['Emergency contact alternate number', 'mobile', 10, false]
        ],
        'employee_identification' => [
            'aadhar_no' => ['Aadhaar number', 'aadhaar', 12, false],
            'pan_no' => ['PAN number', 'pan', 10, false],
            'aadharlinked' => ['Aadhaar & PAN linked', 'select', 5, false]
        ],
        'employee_employment' => [
            'unit' => ['Unit', 'unit', 255, false],
            'branch' => ['Campus', 'text', 255, false],
            'designation' => ['Designation', 'text', 255, false],
            'employee_type' => ['Employee type', 'text', 250, false],
            'job_type' => ['Job type', 'text', 250, false],
            'reporting_to' => ['Reporting to', 'text', 255, false],
            'date_of_joining' => ['Date of joining', 'date', 10, false]
        ]
    ];
    $fields['submission.documents.aadhaar.name'] = [
        'storage' => 'submission',
        'path' => ['documents', 'aadhaar', 'name'],
        'label' => 'Name as per Aadhaar',
        'type' => 'text',
        'max_length' => 255,
        'required' => false
    ];
    foreach ([
        'submission.documents.resume.file_path' => [
            'path' => ['documents', 'resume', 'file_path'],
            'label' => 'Resume document',
            'document_type' => 'resume'
        ],
        'submission.documents.aadhaar.file_path' => [
            'path' => ['documents', 'aadhaar', 'file_path'],
            'label' => 'Aadhaar document',
            'document_type' => 'aadhaar'
        ],
        'submission.documents.pan.file_path' => [
            'path' => ['documents', 'pan', 'file_path'],
            'label' => 'PAN document',
            'document_type' => 'pan'
        ],
        'submission.personal_details.photo_file_path' => [
            'path' => ['personal_details', 'photo_file_path'],
            'label' => 'Photograph',
            'document_type' => 'photo'
        ],
        'submission.personal_details.signature_file_path' => [
            'path' => ['personal_details', 'signature_file_path'],
            'label' => 'Signature',
            'document_type' => 'signature'
        ],
        'submission.teacher_details.document_file_path' => [
            'path' => ['teacher_details', 'document_file_path'],
            'label' => 'B.Ed document',
            'document_type' => 'bed'
        ]
    ] as $field => $definition) {
        $fields[$field] = [
            'storage' => 'submission',
            'type' => 'document_path',
            'max_length' => 500,
            'required' => false
        ] + $definition;
    }
    $submissionFields = [
        'submission.address.same_as_permanent' => [
            'path' => ['address', 'same_as_permanent'],
            'label' => 'Same as permanent address',
            'type' => 'select',
            'max_length' => 3,
            'required' => true
        ],
        'submission.referral.referred_by_name' => [
            'path' => ['referral', 'referred_by', 'name'],
            'label' => 'Referred by',
            'type' => 'text',
            'max_length' => 255,
            'required' => false
        ],
        'submission.referral.family_working' => [
            'path' => ['referral', 'family_members_working'],
            'label' => 'Family members working',
            'type' => 'select',
            'max_length' => 3,
            'required' => true
        ],
        'submission.referral.family_working_details' => [
            'path' => ['referral', 'family_members'],
            'label' => 'Family working details',
            'type' => 'collection',
            'max_length' => 0,
            'required' => false
        ],
        'submission.referral.family_studying' => [
            'path' => ['referral', 'family_members_studying'],
            'label' => 'Family members studying',
            'type' => 'select',
            'max_length' => 3,
            'required' => true
        ],
        'submission.referral.family_studying_details' => [
            'path' => ['referral', 'students'],
            'label' => 'Family studying details',
            'type' => 'collection',
            'max_length' => 0,
            'required' => false
        ],
        'educational_qualifications.all' => [
            'storage' => 'submission',
            'path' => ['education'],
            'label' => 'Qualifications',
            'type' => 'collection',
            'max_length' => 0,
            'required' => false
        ],
        'submission.teacher_documents.is_teacher' => [
            'path' => ['teacher_details', 'is_teacher'],
            'label' => 'Are you a teacher?',
            'type' => 'select',
            'max_length' => 3,
            'required' => true
        ],
        'submission.teacher_documents.bed_status' => [
            'path' => ['teacher_details', 'bed_status'],
            'label' => 'B.Ed status',
            'type' => 'select',
            'max_length' => 20,
            'required' => false
        ],
        'submission.teacher_documents.bed_institution' => [
            'path' => ['teacher_details', 'institution'],
            'label' => 'B.Ed institution',
            'type' => 'text',
            'max_length' => 255,
            'required' => false
        ],
        'submission.teacher_documents.bed_proposed_year' => [
            'path' => ['teacher_details', 'proposed_graduation_year'],
            'label' => 'Proposed graduation year',
            'type' => 'year',
            'max_length' => 4,
            'required' => false
        ],
        'submission.teacher_documents.bed_graduation_year' => [
            'path' => ['teacher_details', 'graduation_year'],
            'label' => 'Graduation year',
            'type' => 'year',
            'max_length' => 4,
            'required' => false
        ],
        'submission.other_documents' => [
            'path' => ['other_documents'],
            'label' => 'Other documents',
            'type' => 'collection',
            'max_length' => 0,
            'required' => false
        ],
        'submission.work_experience.not_applicable' => [
            'path' => ['work_experience_not_applicable'],
            'label' => 'Not applicable',
            'type' => 'select',
            'max_length' => 5,
            'required' => true
        ],
        'employee_previous_experience.all' => [
            'storage' => 'submission',
            'path' => ['work_experience'],
            'label' => 'Experience entries',
            'type' => 'collection',
            'max_length' => 0,
            'required' => false
        ]
    ];
    foreach ($submissionFields as $field => $definition) {
        $fields[$field] = array_merge([
            'storage' => 'submission',
            'max_length' => 255,
            'required' => false
        ], $definition);
    }

    foreach ($groups as $table => $columns) {
        foreach ($columns as $column => [$label, $type, $maxLength, $required]) {
            $fields["{$table}.{$column}"] = [
                'table' => $table,
                'column' => $column,
                'primary_key' => $table === 'employees'
                    ? 'employee_id'
                    : ($table === 'employee_contact'
                        ? 'contact_id'
                        : ($table === 'employee_identification' ? 'id' : 'employment_id')),
                'label' => $label,
                'type' => $type,
                'max_length' => $maxLength,
                'required' => $required
            ];
        }
    }

    $fields['submission.nok.is_emergency_contact'] = [
        'storage' => 'submission',
        'path' => ['nok', 'is_emergency_contact'],
        'label' => 'Is emergency contact',
        'type' => 'select',
        'max_length' => 3,
        'required' => true
    ];
    $fields['submission.nok.address.city'] = [
        'storage' => 'submission',
        'path' => ['nok', 'address', 'city'],
        'label' => 'NOK city',
        'type' => 'text',
        'max_length' => 100,
        'required' => true
    ];
    $fields['submission.nok.address.address2'] = [
        'storage' => 'submission',
        'path' => ['nok', 'address', 'address2'],
        'label' => 'NOK address line 2',
        'type' => 'text',
        'max_length' => 65535,
        'required' => false
    ];
    $fields['submission.nok.address.address3'] = [
        'storage' => 'submission',
        'path' => ['nok', 'address', 'address3'],
        'label' => 'NOK address line 3',
        'type' => 'text',
        'max_length' => 65535,
        'required' => false
    ];
    $fields['submission.nok.address.state'] = [
        'storage' => 'submission',
        'path' => ['nok', 'address', 'state'],
        'label' => 'NOK state',
        'type' => 'state',
        'max_length' => 100,
        'required' => true
    ];
    $fields['submission.nok.address.country'] = [
        'storage' => 'submission',
        'path' => ['nok', 'address', 'country'],
        'label' => 'NOK country',
        'type' => 'country',
        'max_length' => 100,
        'required' => true
    ];
    $fields['submission.nok.address.pincode'] = [
        'storage' => 'submission',
        'path' => ['nok', 'address', 'pincode'],
        'label' => 'NOK PIN code',
        'type' => 'pincode',
        'max_length' => 6,
        'required' => true
    ];

    return $fields;
}

function adminEmployeeFixedOptions(string $field): array
{
    $options = [
        'employees.gender' => ['Male', 'Female', 'Other'],
        'employees.marital_status' => ['Single', 'Married', 'Divorced', 'Widowed'],
        'employees.blood_group' => [
            'A+', 'A-', 'A1+', 'A1-', 'A1B+', 'A2B+', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'
        ],
        'employees.alumni_detail' => ['Yes', 'No'],
        'employee_identification.aadharlinked' => ['Yes', 'No'],
        'submission.address.same_as_permanent' => ['Yes', 'No'],
        'submission.referral.family_working' => ['Yes', 'No'],
        'submission.referral.family_studying' => ['Yes', 'No'],
        'submission.teacher_documents.is_teacher' => ['Yes', 'No'],
        'submission.teacher_documents.bed_status' => ['Joined', 'Completed'],
        'submission.work_experience.not_applicable' => ['Yes', 'No'],
        'submission.nok.is_emergency_contact' => ['Yes', 'No'],
        'employee_contact.state' => adminEmployeeIndiaStates(),
        'employee_contact.perm_state' => adminEmployeeIndiaStates(),
        'employee_contact.country' => ['India'],
        'employee_contact.perm_country' => ['India'],
        'submission.nok.address.state' => adminEmployeeIndiaStates(),
        'submission.nok.address.country' => ['India'],
        'employees.alumni_unit' => [
            'Lady Andal School - IB',
            'Sir Mutha School - CBSE',
            'Sir & Lady M. Venkatasubba Rao Hr., Sec., School, Tambaram',
            'Lady Andal Open School',
            'Lady Andal House of Children - NIOS',
            'The Madras Seva Sadan Hr., Sec., School Chetpet',
            'The Madras Seva Sadan Hr., Sec., School Tambaram',
            'Lady Andal Venkatasubba Rao Mat., Hr., Sec., School',
            'Sir M. Venkatasubba Rao Hr., Sec., School, T. Nagar'
        ]
    ];

    return $options[$field] ?? [];
}

function adminEmployeeIndiaStates(): array
{
    return [
        'Andhra Pradesh', 'Arunachal Pradesh', 'Assam', 'Bihar', 'Chhattisgarh',
        'Goa', 'Gujarat', 'Haryana', 'Himachal Pradesh', 'Jharkhand', 'Karnataka',
        'Kerala', 'Madhya Pradesh', 'Maharashtra', 'Manipur', 'Meghalaya', 'Mizoram',
        'Nagaland', 'Odisha', 'Punjab', 'Rajasthan', 'Sikkim', 'Tamil Nadu',
        'Telangana', 'Tripura', 'Uttar Pradesh', 'Uttarakhand', 'West Bengal',
        'Andaman and Nicobar Islands', 'Chandigarh', 'Dadra and Nagar Haveli',
        'Daman and Diu', 'Delhi', 'Lakshadweep', 'Puducherry'
    ];
}

function adminEmployeeEnumOptions(PDO $pdo, string $table, string $column): array
{
    $fixedOptions = adminEmployeeFixedOptions("{$table}.{$column}");
    if ($fixedOptions) {
        return $fixedOptions;
    }

    $stmt = $pdo->prepare("
        SELECT COLUMN_TYPE
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
    ");
    $stmt->execute([$table, $column]);
    $definition = (string) $stmt->fetchColumn();
    preg_match_all("/'((?:\\\\\\\\.|[^'\\\\\\\\])*)'/", $definition, $matches);

    return array_map('stripcslashes', $matches[1] ?? []);
}

$fields = adminEditableEmployeeFields();
$isAdminWrite = $_SERVER['REQUEST_METHOD'] === 'POST';
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    adminEmployeeRespond(405, ['success' => false, 'message' => 'Method not allowed.']);
}

$input = $isAdminWrite ? json_decode(file_get_contents('php://input'), true) : [];
if ($isAdminWrite && (!is_array($input) || ($input['action'] ?? '') !== 'update')) {
    adminEmployeeRespond(400, ['success' => false, 'message' => 'A valid employee update is required.']);
}

$employeeId = filter_var(
    $isAdminWrite ? ($input['employee_id'] ?? null) : ($_GET['employee_id'] ?? null),
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);
if ($employeeId === false) {
    adminEmployeeRespond(422, ['success' => false, 'message' => 'A valid employee ID is required.']);
}
$submissionId = filter_var(
    $isAdminWrite ? ($input['submission_id'] ?? null) : null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);
$profileSubmissionId = $isAdminWrite
    ? null
    : filter_var($_GET['submission_id'] ?? null, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1]
    ]);
if ($isAdminWrite && $submissionId === false && isset($input['submission_id'])) {
    adminEmployeeRespond(422, ['success' => false, 'message' => 'The submission ID is invalid.']);
}
if (!$isAdminWrite && $profileSubmissionId === false) {
    adminEmployeeRespond(422, ['success' => false, 'message' => 'A valid submission ID is required.']);
}

try {
    $pdo = getDatabaseConnection();
    if (!employeeIsInAdminScope($pdo, (int) $employeeId, $admin)) {
        adminEmployeeRespond(404, ['success' => false, 'message' => 'Employee not found in your authorized unit.']);
    }

    $submissionData = null;
    if (!$isAdminWrite) {
        $submissionStmt = $pdo->prepare("
            SELECT employee_id, status, form_data
            FROM employee_form_submissions
            WHERE id = ? AND status IN ('pending', 'approved')
            LIMIT 1
        ");
        $submissionStmt->execute([$profileSubmissionId]);
        $profileSubmission = $submissionStmt->fetch(PDO::FETCH_ASSOC);
        if (!$profileSubmission || (int) $profileSubmission['employee_id'] !== (int) $employeeId ||
            !submissionIsInAdminScope($pdo, (int) $profileSubmissionId, $admin)) {
            adminEmployeeRespond(404, ['success' => false, 'message' => 'Submission not found in your authorized unit.']);
        }
        $submissionData = json_decode($profileSubmission['form_data'], true, 512, JSON_THROW_ON_ERROR);
    }

    if ($isAdminWrite) {
        $changes = $input['changes'] ?? null;
        if (!is_array($changes) || !$changes || count($changes) > 40) {
            adminEmployeeRespond(422, ['success' => false, 'message' => 'Provide one or more valid employee fields.']);
        }
        foreach ($changes as $field => $value) {
            if (!is_string($field) || !isset($fields[$field]) ||
                (!is_scalar($value) &&
                    !($fields[$field]['type'] === 'collection' && is_array($value)))) {
                throw new DomainException('One or more employee fields are invalid.');
            }
        }
        if ($submissionId !== false && $submissionId !== null) {
            $submissionStmt = $pdo->prepare("
                SELECT employee_id, status, form_data
                FROM employee_form_submissions
                WHERE id = ?
                LIMIT 1
            ");
            $submissionStmt->execute([$submissionId]);
            $linkedSubmission = $submissionStmt->fetch(PDO::FETCH_ASSOC);
            if (!$linkedSubmission || (int) $linkedSubmission['employee_id'] !== (int) $employeeId ||
                !submissionIsInAdminScope($pdo, (int) $submissionId, $admin)) {
                adminEmployeeRespond(404, ['success' => false, 'message' => 'Submission not found in your authorized unit.']);
            }
        }
        if (isset($changes['submission.nok.is_emergency_contact'])) {
            $emergencyStatus = (string) $changes['submission.nok.is_emergency_contact'];
            if ($emergencyStatus === 'Yes') {
                $changes['employee_contact.nok_emergency_no'] = '';
            } elseif ($emergencyStatus === 'No' &&
                (!isset($changes['employee_contact.nok_emergency_no']) ||
                    !preg_match('/^[6-9][0-9]{9}$/', (string) $changes['employee_contact.nok_emergency_no']))) {
                throw new DomainException('Enter a valid 10-digit emergency contact number when the NOK is not the emergency contact.');
            }
        }
        if (isset($changes['employees.marital_status'])) {
            if ($changes['employees.marital_status'] === 'Married' &&
                (!isset($changes['employees.spouse_name']) ||
                    trim((string) $changes['employees.spouse_name']) === '')) {
                throw new DomainException('Enter the spouse name when marital status is Married.');
            }
            if ($changes['employees.marital_status'] !== 'Married') {
                $changes['employees.spouse_name'] = '';
            }
        }
        if (isset($changes['employees.alumni_detail'])) {
            if ($changes['employees.alumni_detail'] === 'Yes' &&
                (!isset($changes['employees.alumni_unit']) ||
                    trim((string) $changes['employees.alumni_unit']) === '')) {
                throw new DomainException('Select the alumni school when alumni status is Yes.');
            }
            if ($changes['employees.alumni_detail'] !== 'Yes') {
                $changes['employees.alumni_unit'] = '';
            }
        }
        $pendingStmt = $pdo->prepare("
            SELECT id, form_data
            FROM employee_form_submissions
            WHERE employee_id = ? AND status = 'pending'
        ");
        $pendingStmt->execute([$employeeId]);
        $pendingSubmissions = $pendingStmt->fetchAll(PDO::FETCH_ASSOC);
        $stagedTables = [
            'employees' => 'temp_employees',
            'employee_contact' => 'temp_employee_contact',
            'employee_identification' => 'temp_employee_identification',
            'employee_employment' => 'temp_employee_employment'
        ];
        $stagedColumns = [
            'employees' => [
                'date_of_birth', 'gender', 'marital_status', 'spouse_name', 'father_name',
                'mother_name', 'blood_group', 'alumni_detail', 'alumni_unit'
            ],
            'employee_contact' => [
                'personal_email', 'local_address', 'local_address1', 'local_address2',
                'pincode', 'city', 'state', 'country', 'permanent_address',
                'permanent_address1', 'permanent_address2', 'perm_pincode', 'perm_city',
                'perm_state', 'perm_country', 'nok_name', 'nok_contact_no',
                'nok_address', 'nok_emergency_no'
            ],
            'employee_identification' => ['aadhar_no', 'pan_no', 'aadharlinked'],
            'employee_employment' => [
                'unit', 'branch', 'designation', 'employee_type', 'job_type',
                'reporting_to',                 'date_of_joining'
            ]
        ];
        $stagedSubmissionByField = [];
        foreach ($pendingSubmissions as $pendingSubmission) {
            $pendingForm = json_decode($pendingSubmission['form_data'], true, 512, JSON_THROW_ON_ERROR);
            $pendingFields = $pendingForm['submission']['changed_fields'] ?? [];
            $pendingFields = is_array($pendingFields) ? $pendingFields : [];
            foreach (array_keys($changes) as $field) {
                [$table, $column] = explode('.', $field, 2);
                if (!isset($stagedTables[$table]) || !in_array($column, $stagedColumns[$table], true)) {
                    continue;
                }
                $staged = $pdo->prepare(sprintf(
                    'SELECT 1 FROM `%s` WHERE employee_id = ? AND submission_id = ? AND `%s` IS NOT NULL LIMIT 1',
                    $stagedTables[$table],
                    $column
                ));
                $staged->execute([$employeeId, (int) $pendingSubmission['id']]);
                $hasStagedValue = $staged->fetchColumn() !== false;
                if (!in_array($field, $pendingFields, true) && !$hasStagedValue) {
                    continue;
                }
                if (!$hasStagedValue) {
                    throw new DomainException('The pending correction for this field is unavailable. Refresh and try again.');
                }
                if ($submissionId === false || $submissionId === null ||
                    (int) $submissionId !== (int) $pendingSubmission['id']) {
                    throw new DomainException('This field is part of another pending submission. Open that submission to edit it.');
                }
                if (isset($stagedSubmissionByField[$field])) {
                    throw new DomainException('This field is included in more than one pending submission. Resolve the other submission first.');
                }
                $stagedSubmissionByField[$field] = (int) $pendingSubmission['id'];
            }
        }

        $unitNames = [];
        if (adminCanAccessAllUnits($admin)) {
            $unitNames = getAdminDatabaseConnection()->query('SELECT unit_name FROM units')->fetchAll(PDO::FETCH_COLUMN);
        }
        $pdo->beginTransaction();
        $submissionFormData = null;
        if ($submissionId !== false && $submissionId !== null) {
            $lockedSubmission = $pdo->prepare("
                SELECT status, form_data, mobile, reference_number, submitted_at
                FROM employee_form_submissions
                WHERE id = ? AND employee_id = ?
                FOR UPDATE
            ");
            $lockedSubmission->execute([$submissionId, $employeeId]);
            $submissionRecord = $lockedSubmission->fetch(PDO::FETCH_ASSOC);
            if (!$submissionRecord || !in_array($submissionRecord['status'], ['pending', 'approved'], true)) {
                throw new DomainException('The linked submission is no longer available for editing.');
            }
            $submissionFormData = json_decode($submissionRecord['form_data'], true, 512, JSON_THROW_ON_ERROR);
            $emergencyValue = $changes['submission.nok.is_emergency_contact'] ??
                ($submissionFormData['nok']['is_emergency_contact'] ?? null);
            $emergencyIsNo = $emergencyValue === false || $emergencyValue === 0 ||
                $emergencyValue === 'No' || $emergencyValue === '0';
            if ($emergencyIsNo && array_key_exists('employee_contact.nok_emergency_no', $changes) &&
                !preg_match('/^[6-9][0-9]{9}$/', (string) $changes['employee_contact.nok_emergency_no'])) {
                throw new DomainException('Enter a valid 10-digit emergency contact number when the NOK is not the emergency contact.');
            }
        }
        $savedChanges = [];
        $submissionFormDataChanged = false;
        foreach ($changes as $field => $value) {
            $definition = $fields[$field];
            if (($definition['storage'] ?? '') === 'submission' &&
                $definition['type'] === 'collection') {
                if (!is_array($submissionFormData) || !$submissionId ||
                    !is_array($value) || count($value) > 30) {
                    throw new DomainException('This collection can only be updated from its employee submission.');
                }
                $allowedCollectionKeys = [
                    'submission.referral.family_working_details' => ['tmss_id', 'name', 'unit', 'designation'],
                    'submission.referral.family_studying_details' => ['name', 'school', 'class'],
                    'educational_qualifications.all' => [
                        'qualification_type', 'qualification_name', 'institution', 'year_of_completion',
                        'skilled_in', 'special_talents', 'existing_row_id', 'certificate_file_path'
                    ],
                    'employee_previous_experience.all' => [
                        'company', 'designation', 'start_month', 'end_month', 'details',
                        'experience_letter_file_path', 'pay_slip_file_path'
                    ],
                    'submission.other_documents' => ['title', 'type', 'file_path']
                ];
                foreach ($value as $entry) {
                    if (!is_array($entry)) {
                        throw new DomainException($definition['label'] . ' contains an invalid entry.');
                    }
                    foreach ($entry as $entryKey => $entryValue) {
                        if (!in_array($entryKey, $allowedCollectionKeys[$field] ?? [], true)) {
                            throw new DomainException($definition['label'] . ' contains an unsupported field.');
                        }
                        if (!is_scalar($entryValue) && $entryValue !== null) {
                            throw new DomainException($definition['label'] . ' contains an invalid value.');
                        }
                        if (is_string($entryValue) && strlen($entryValue) > 5000) {
                            throw new DomainException($definition['label'] . ' contains a value that is too long.');
                        }
                    }
                }
                $current = $submissionFormData;
                foreach ($definition['path'] as $pathPart) {
                    if (!is_array($current) || !array_key_exists($pathPart, $current)) {
                        $current = [];
                        break;
                    }
                    $current = $current[$pathPart];
                }
                $documentColumns = [
                    'educational_qualifications.all' => ['certificate_file_path'],
                    'employee_previous_experience.all' => [
                        'experience_letter_file_path', 'pay_slip_file_path'
                    ],
                    'submission.other_documents' => ['file_path']
                ];
                foreach ($value as $index => $entry) {
                    foreach ($documentColumns[$field] ?? [] as $documentColumn) {
                        $newPath = trim((string) ($entry[$documentColumn] ?? ''));
                        $oldPath = trim((string) ($current[$index][$documentColumn] ?? ''));
                        if ($newPath === '' || $newPath === $oldPath) {
                            continue;
                        }
                        $columnTypes = [
                            'certificate_file_path' => 'education_certificate',
                            'experience_letter_file_path' => 'experience_letter',
                            'pay_slip_file_path' => 'experience_payslip',
                            'file_path' => (string) ($entry['type'] ?? 'other_document')
                        ];
                        $documentType = $columnTypes[$documentColumn] ?? '';
                        if ($documentType === 'driving_license') {
                            $documentType = 'driving-license';
                        } elseif ($documentType === 'police_verification') {
                            $documentType = 'police-verification';
                        }
                        if (submissionResolveStoredDocumentPath(
                            dirname(__DIR__, 2),
                            $employeeId,
                            $documentType,
                            $newPath
                        ) === null) {
                            throw new DomainException('A replacement document is not available for this employee.');
                        }
                    }
                }
                if (json_encode(adminEmployeeEffectiveValue($current), JSON_THROW_ON_ERROR) ===
                    json_encode($value, JSON_THROW_ON_ERROR)) {
                    continue;
                }
                $target = &$submissionFormData;
                foreach (array_slice($definition['path'], 0, -1) as $pathPart) {
                    if (!isset($target[$pathPart]) || !is_array($target[$pathPart])) {
                        $target[$pathPart] = [];
                    }
                    $target = &$target[$pathPart];
                }
                $lastPathPart = $definition['path'][count($definition['path']) - 1];
                $target[$lastPathPart] = $value;
                unset($target);
                $submissionFormDataChanged = true;
                $savedChanges[] = [
                    'field' => $field,
                    'label' => $definition['label'],
                    'old_value' => json_encode($current, JSON_INVALID_UTF8_SUBSTITUTE),
                    'new_value' => json_encode($value, JSON_INVALID_UTF8_SUBSTITUTE)
                ];
                continue;
            }
            $value = trim((string) $value);
            if ($definition['required'] && $value === '') {
                throw new DomainException($definition['label'] . ' is required.');
            }
            $valueLength = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
            if ($valueLength > $definition['max_length']) {
                throw new DomainException($definition['label'] . ' is too long.');
            }
            if ($definition['type'] === 'pincode' && !preg_match('/^[0-9]{6}$/', $value)) {
                throw new DomainException('Enter a valid 6-digit PIN code.');
            }
            if ($definition['type'] === 'mobile' && $value !== '' && !preg_match('/^[6-9][0-9]{9}$/', $value)) {
                throw new DomainException('Enter a valid 10-digit mobile number.');
            }
            if ($definition['type'] === 'select') {
                $options = isset($definition['storage']) && $definition['storage'] === 'submission'
                    ? adminEmployeeFixedOptions($field)
                    : adminEmployeeEnumOptions($pdo, $definition['table'], $definition['column']);
                if (!in_array($value, $options, true)) {
                    throw new DomainException('Select a valid ' . strtolower($definition['label']) . '.');
                }
            }
            if ($definition['type'] === 'year' && $value !== '' && !preg_match('/^[0-9]{4}$/', $value)) {
                throw new DomainException('Enter a valid four-digit year.');
            }
            if ($definition['type'] === 'school' &&
                !in_array($value, adminEmployeeFixedOptions($field), true)) {
                throw new DomainException('Select a valid alumni school.');
            }
            if (in_array($definition['type'], ['state', 'country'], true) &&
                !in_array($value, adminEmployeeFixedOptions($field), true)) {
                throw new DomainException('Select a valid ' . strtolower($definition['label']) . '.');
            }

            if (($definition['storage'] ?? '') === 'submission') {
                if (!is_array($submissionFormData) || !$submissionId) {
                    throw new DomainException('This value can only be updated from its employee submission.');
                }
                if ($definition['type'] === 'document_path') {
                    if (!is_string($value)) {
                        throw new DomainException('Choose a valid replacement document.');
                    }
                    if ($value !== '') {
                        if (submissionResolveStoredDocumentPath(
                            dirname(__DIR__, 2),
                            $employeeId,
                            $definition['document_type'],
                            $value
                        ) === null) {
                            throw new DomainException('The replacement document is not available for this employee.');
                        }
                    }
                }
                $current = $submissionFormData;
                foreach ($definition['path'] as $pathPart) {
                    if (!is_array($current) || !array_key_exists($pathPart, $current)) {
                        $current = null;
                        break;
                    }
                    $current = $current[$pathPart];
                }
                $previousValue = $current;
                if (is_array($previousValue) && array_key_exists('original_value', $previousValue)) {
                    $previousValue = $previousValue['original_value'];
                }
                $storedValue = $field === 'submission.nok.is_emergency_contact'
                    ? ($value === 'Yes')
                    : $value;
                if ($field === 'submission.work_experience.not_applicable') {
                    $storedValue = $value === 'Yes';
                }
                if ($previousValue === $storedValue || (string) $previousValue === $value) {
                    continue;
                }
                $target = &$submissionFormData;
                foreach (array_slice($definition['path'], 0, -1) as $pathPart) {
                    if (!isset($target[$pathPart]) || !is_array($target[$pathPart])) {
                        $target[$pathPart] = [];
                    }
                    $target = &$target[$pathPart];
                }
                $lastPathPart = $definition['path'][count($definition['path']) - 1];
                $target[$lastPathPart] = $storedValue;
                unset($target);
                $submissionFormDataChanged = true;
                $savedChanges[] = [
                    'field' => $field,
                    'label' => $definition['label'],
                    'old_value' => $field === 'submission.nok.is_emergency_contact'
                        ? ($previousValue === true || $previousValue === 1 || $previousValue === 'Yes' ? 'Yes' : 'No')
                        : (is_scalar($previousValue) ? (string) $previousValue : ''),
                    'new_value' => $value
                ];
                continue;
            }

            if ($definition['table'] === 'employee_employment' && $field === 'employee_employment.unit' &&
                !adminCanAccessAllUnits($admin)) {
                throw new DomainException('School Admins cannot change an employee’s assigned unit.');
            }
            if ($definition['type'] === 'email' && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                throw new DomainException('Enter a valid email address.');
            }
            if ($definition['type'] === 'aadhaar' && $value !== '' && !preg_match('/^[0-9]{12}$/', $value)) {
                throw new DomainException('Enter a valid 12-digit Aadhaar number.');
            }
            if ($definition['type'] === 'pan') {
                $value = strtoupper($value);
                if ($value !== '' && !preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', $value)) {
                    throw new DomainException('Enter a valid PAN number.');
                }
            }
            if ($definition['type'] === 'date') {
                $date = DateTime::createFromFormat('!Y-m-d', $value);
                if (!$date || $date->format('Y-m-d') !== $value) {
                    throw new DomainException('Enter a valid ' . strtolower($definition['label']) . ' date.');
                }
            }
            if ($definition['type'] === 'select') {
                $options = adminEmployeeEnumOptions($pdo, $definition['table'], $definition['column']);
                if ($value !== '' && !in_array($value, $options, true)) {
                    throw new DomainException('Select a valid ' . strtolower($definition['label']) . '.');
                }
            }
            if ($definition['type'] === 'unit' && !in_array($value, $unitNames, true)) {
                throw new DomainException('Select a valid unit.');
            }

            $table = $definition['table'];
            $column = $definition['column'];
            $primaryKey = $definition['primary_key'];
            $select = $pdo->prepare(sprintf(
                'SELECT `%s`, `%s` FROM `%s` WHERE employee_id = ? ORDER BY `%s` DESC LIMIT 1 FOR UPDATE',
                $column,
                $primaryKey,
                $table,
                $primaryKey
            ));
            $select->execute([$employeeId]);
            $record = $select->fetch(PDO::FETCH_NUM);
            if (!$record) {
                throw new DomainException('An employee record needed for this update is unavailable.');
            }
            [$original, $rowId] = $record;
            $originalValue = (string) ($original ?? '');
            if (isset($stagedSubmissionByField[$field])) {
                $targetSubmissionId = $stagedSubmissionByField[$field];
                $stagedTable = $stagedTables[$table];
                $stagedCurrent = $pdo->prepare(sprintf(
                    'SELECT `%s` FROM `%s` WHERE employee_id = ? AND submission_id = ? AND `%s` IS NOT NULL LIMIT 1',
                    $column,
                    $stagedTable,
                    $column
                ));
                $stagedCurrent->execute([$employeeId, $targetSubmissionId]);
                $previousValue = $stagedCurrent->fetchColumn();
                if ($previousValue === false) {
                    throw new DomainException('The pending correction for this field is unavailable. Refresh and try again.');
                }
                $previousValue = (string) $previousValue;
                if ($previousValue === $value) {
                    continue;
                }
                $update = $pdo->prepare(sprintf(
                    'UPDATE `%s` SET `%s` = ? WHERE employee_id = ? AND submission_id = ? AND `%s` IS NOT NULL',
                    $stagedTable,
                    $column,
                    $column
                ));
                $update->execute([$value, $employeeId, $targetSubmissionId]);
                if ($update->rowCount() === 0) {
                    throw new RuntimeException('The pending employee correction could not be updated.');
                }
                $originalValue = $previousValue;
            } else {
                if ($originalValue === $value) {
                    continue;
                }
                $update = $pdo->prepare(sprintf(
                    'UPDATE `%s` SET `%s` = ? WHERE `%s` = ?',
                    $table,
                    $column,
                    $primaryKey
                ));
                $update->execute([$value, (int) $rowId]);
            }
            $savedChanges[] = [
                'field' => $field,
                'label' => $definition['label'],
                'old_value' => $originalValue,
                'new_value' => $value
            ];
            $submissionPath = adminEmployeeSubmissionPathForField($field);
            if ($submissionPath !== null && is_array($submissionFormData)) {
                adminEmployeeSetPath($submissionFormData, $submissionPath, $value);
                $submissionFormDataChanged = true;
            }
        }

        if ($submissionFormDataChanged) {
            adminEmployeeValidateConditionalSubmissionEdits($submissionFormData, $changes);
            foreach ([
                'temp_employees',
                'temp_employee_contact',
                'temp_employee_identification',
                'temp_employee_employment',
                'temp_employee_referrals',
                'temp_educational_qualifications',
                'temp_employee_previous_experience'
            ] as $stagingTable) {
                $deleteStaging = $pdo->prepare(
                    "DELETE FROM `{$stagingTable}` WHERE employee_id = ? AND submission_id = ?"
                );
                $deleteStaging->execute([$employeeId, $submissionId]);
            }
            $changedFields = stageSubmissionRows(
                $pdo,
                (int) $employeeId,
                (int) $submissionId,
                $submissionFormData
            );
            if (($submissionRecord['status'] ?? '') === 'approved') {
                adminEmployeeApplyStagedChanges(
                    $pdo,
                    (int) $employeeId,
                    (int) $submissionId,
                    (int) $admin['id'],
                    $changedFields,
                    $submissionFormData
                );
                foreach ([
                    'temp_employees',
                    'temp_employee_contact',
                    'temp_employee_identification',
                    'temp_employee_employment',
                    'temp_employee_referrals',
                    'temp_educational_qualifications',
                    'temp_employee_previous_experience'
                ] as $stagingTable) {
                    $deleteStaging = $pdo->prepare(
                        "DELETE FROM `{$stagingTable}` WHERE employee_id = ? AND submission_id = ?"
                    );
                    $deleteStaging->execute([$employeeId, $submissionId]);
                }
            }
            if (!isset($submissionFormData['submission']) ||
                !is_array($submissionFormData['submission'])) {
                $submissionFormData['submission'] = [];
            }
            if (($submissionRecord['status'] ?? '') === 'approved') {
                $changedFields = array_values(array_unique(array_merge(
                    is_array($submissionFormData['submission']['changed_fields'] ?? null)
                        ? $submissionFormData['submission']['changed_fields']
                        : [],
                    $changedFields,
                    array_column($savedChanges, 'field')
                )));
            }
            $submissionFormData['submission']['changed_fields'] = $changedFields;
            $updateSubmissionData = $pdo->prepare("
                UPDATE employee_form_submissions
                SET form_data = ?, updated_at = NOW()
                WHERE id = ? AND employee_id = ?
            ");
            $updateSubmissionData->execute([
                json_encode($submissionFormData, JSON_THROW_ON_ERROR),
                $submissionId,
                $employeeId
            ]);
        }

        if ($savedChanges) {
            $history = $pdo->prepare("
                INSERT INTO admin_employee_change_history
                    (employee_id, submission_id, field_name, old_value, new_value, changed_by, changed_by_role, changed_by_name, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            foreach ($savedChanges as $change) {
                $history->execute([
                    $employeeId,
                    $submissionId === false ? null : $submissionId,
                    $change['field'],
                    $change['old_value'],
                    $change['new_value'],
                    $admin['id'],
                    $admin['role'],
                    $admin['name']
                ]);
            }
        }
        $pdo->commit();
        adminEmployeeRespond(200, [
            'success' => true,
            'message' => $savedChanges ? 'Employee information updated and change history recorded.' : 'No values changed.',
            'changes' => $savedChanges
        ]);
    }

    require_once __DIR__ . '/../config/submission-staging.php';
    $employee = getHrmsEmployeeById($pdo, (int) $employeeId);
    if ($employee === null) {
        adminEmployeeRespond(404, ['success' => false, 'message' => 'Employee not found.']);
    }
    $historyStmt = $pdo->prepare("
        SELECT field_name, old_value, new_value, changed_by_name, changed_by_role, created_at
        FROM admin_employee_change_history
        WHERE employee_id = ?
        ORDER BY id DESC
        LIMIT 100
    ");
    $historyStmt->execute([$employeeId]);
    $history = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

    $fieldOptions = [];
    foreach ($fields as $key => $definition) {
        if (in_array($definition['type'], ['select', 'school', 'state', 'country'], true)) {
            $fixedOptions = adminEmployeeFixedOptions($key);
            $fieldOptions[$key] = $fixedOptions ?: adminEmployeeEnumOptions(
                $pdo,
                $definition['table'],
                $definition['column']
            );
        }
    }
    $fieldOptions['educational_qualifications.all'] = [
        'X', 'XII', 'UG', 'PG', 'Doc', 'Diploma', 'Others'
    ];
    $fieldOptions['family_units'] = adminCanAccessAllUnits($admin)
        ? getAdminDatabaseConnection()
            ->query('SELECT unit_name FROM units ORDER BY unit_name')
            ->fetchAll(PDO::FETCH_COLUMN)
        : adminUnitEmploymentNames($admin);
    if (adminCanAccessAllUnits($admin)) {
        $fieldOptions['employee_employment.unit'] = getAdminDatabaseConnection()
            ->query('SELECT unit_name FROM units ORDER BY unit_name')
            ->fetchAll(PDO::FETCH_COLUMN);
    } else {
        unset($fields['employee_employment.unit']);
    }

    echo json_encode([
        'success' => true,
        'employee' => $employee,
        'submission_data' => $submissionData,
        'history' => $history,
        'fields' => $fields,
        'field_options' => $fieldOptions,
        'documents' => [],
        'legacy_documents' => getHrmsLegacyDocumentNames($pdo, (int) $employeeId)
    ], JSON_INVALID_UTF8_SUBSTITUTE);
} catch (DomainException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    adminEmployeeRespond(422, ['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Admin employee profile request failed: ' . $e->getMessage());
    adminEmployeeRespond(500, ['success' => false, 'message' => 'Employee profile could not be loaded or updated. Apply the admin employee history migration and try again.']);
}
