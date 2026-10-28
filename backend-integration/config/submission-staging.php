<?php
/**
 * Append only changed employee data to the existing temp_* tables.
 */

function insertStagingRow(PDO $pdo, string $table, array $row, array $excludedColumns = []): void
{
    $definitions = getStagingTableDefinitions($pdo, $table);
    $columns = array_fill_keys(array_keys($definitions), true);
    $row = array_intersect_key($row, $columns);
    foreach ($excludedColumns as $column) {
        unset($row[$column]);
    }

    if (!$row) {
        throw new RuntimeException("No staging columns were available for {$table}");
    }

    $columnNames = array_keys($row);
    $quotedColumns = array_map(static fn(string $column): string => "`{$column}`", $columnNames);
    $placeholders = implode(', ', array_fill(0, count($columnNames), '?'));
    $sql = sprintf(
        'INSERT INTO `%s` (%s) VALUES (%s)',
        $table,
        implode(', ', $quotedColumns),
        $placeholders
    );
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_values($row));
}

function getStagingTableDefinitions(PDO $pdo, string $table): array
{
    $stmt = $pdo->prepare("
        SELECT COLUMN_NAME, IS_NULLABLE, COLUMN_DEFAULT, EXTRA, DATA_TYPE
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
    ");
    $stmt->execute([$table]);
    $definitions = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $column) {
        $definitions[$column['COLUMN_NAME']] = [
            'is_nullable' => $column['IS_NULLABLE'],
            'column_default' => $column['COLUMN_DEFAULT'],
            'extra' => $column['EXTRA'],
            'data_type' => strtolower($column['DATA_TYPE'])
        ];
    }

    return $definitions;
}

function getSubmissionTableChanges(
    PDO $pdo,
    string $sourceTable,
    string $sourcePrimaryKey,
    int $employeeId,
    array $values,
    array $fields,
    array $specialComparisons = []
): array {
    $stmt = $pdo->prepare(
        "SELECT * FROM `{$sourceTable}` WHERE employee_id = ? ORDER BY `{$sourcePrimaryKey}` DESC LIMIT 1"
    );
    $stmt->execute([$employeeId]);
    $original = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $changes = [];

    foreach ($values as $column => $value) {
        if (!in_array($column, $fields, true)) {
            continue;
        }
        $originalValue = $original[$column] ?? null;
        if (!submissionValuesEqual($originalValue, $value, $specialComparisons[$column] ?? null)) {
            $changes[$column] = $value;
        }
    }

    if ($changes && !$original) {
        throw new DomainException("No original {$sourceTable} record exists for the submitted changes.");
    }

    return $changes;
}

function submissionValuesEqual($original, $submitted, ?string $comparison = null): bool
{
    if ($comparison === 'alumni') {
        $normalize = static function ($value): string {
            $value = strtolower(trim((string) ($value ?? '')));
            if (in_array($value, ['yes', 'y', 'true', '1', 'alumni'], true)) {
                return 'yes';
            }
            if (in_array($value, ['no', 'n', 'false', '0'], true)) {
                return 'no';
            }
            return $value;
        };

        return $normalize($original) === $normalize($submitted);
    }

    $normalize = static function ($value): string {
        $value = (string) ($value ?? '');
        if ($value === '' || $value === '0000-00-00') {
            return '';
        }
        return $value;
    };

    return $normalize($original) === $normalize($submitted);
}

function submissionOriginalValueSnapshots(PDO $pdo, int $employeeId, array $changedFields): array
{
    $scalarTables = [
        'employees' => [
            'employee_id',
            [
                'date_of_birth',
                'gender',
                'marital_status',
                'spouse_name',
                'father_name',
                'mother_name',
                'blood_group',
                'alumni_detail',
                'alumni_unit'
            ]
        ],
        'employee_contact' => [
            'contact_id',
            [
                'personal_email',
                'local_address',
                'local_address1',
                'local_address2',
                'pincode',
                'city',
                'state',
                'country',
                'permanent_address',
                'permanent_address1',
                'permanent_address2',
                'perm_pincode',
                'perm_city',
                'perm_state',
                'perm_country',
                'nok_name',
                'nok_contact_no',
                'nok_address',
                'nok_emergency_no'
            ]
        ],
        'employee_identification' => [
            'id',
            [
                'aadhar_no',
                'aadhar_copy',
                'aadharlinked',
                'pan_no',
                'pan_copy',
                'photo',
                'resume',
                'staff_sign',
                'driving',
                'pg_degree_status',
                'pg_degree_institution',
                'pg_degree_doc'
            ]
        ],
        'employee_employment' => [
            'employment_id',
            [
                'unit',
                'branch',
                'designation',
                'employee_type',
                'job_type',
                'reporting_to',
                'date_of_joining'
            ]
        ]
    ];
    $collectionTables = [
        'employee_referrals.all' => [
            'employee_referrals',
            'referral_id',
            [
                'referred_by',
                'family_members_working',
                'working_name',
                'working_unit',
                'working_designation',
                'family_members_studying',
                'student_name',
                'student_unit',
                'student_class'
            ]
        ],
        'educational_qualifications.all' => [
            'educational_qualifications',
            'id',
            [
                'qualification_type',
                'qualification_details',
                'institution',
                'completion_year',
                'certificate_path'
            ]
        ],
        'employee_previous_experience.all' => [
            'employee_previous_experience',
            'id',
            [
                'company_name',
                'role',
                'start_date',
                'end_date',
                'experience_details',
                'pay_slip',
                'experience_letter_path'
            ]
        ]
    ];
    $snapshots = [];

    foreach ($changedFields as $field) {
        if (!is_string($field)) {
            continue;
        }
        if (isset($collectionTables[$field])) {
            [$table, $primaryKey, $columns] = $collectionTables[$field];
            $quotedColumns = array_map(static fn(string $column): string => "`{$column}`", $columns);
            $stmt = $pdo->prepare(sprintf(
                'SELECT %s FROM `%s` WHERE employee_id = ? ORDER BY `%s`',
                implode(', ', $quotedColumns),
                $table,
                $primaryKey
            ));
            $stmt->execute([$employeeId]);
            $snapshots[$field] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            continue;
        }
        if (str_contains($field, '.')) {
            [$table, $column] = explode('.', $field, 2);
            if (!isset($scalarTables[$table]) || !in_array($column, $scalarTables[$table][1], true)) {
                continue;
            }
            [$primaryKey] = $scalarTables[$table];
            $stmt = $pdo->prepare(sprintf(
                'SELECT `%s` FROM `%s` WHERE employee_id = ? ORDER BY `%s` DESC LIMIT 1',
                $column,
                $table,
                $primaryKey
            ));
            $stmt->execute([$employeeId]);
            $value = $stmt->fetchColumn();
            $snapshots[$field] = $value === false ? null : $value;
        }
    }

    return $snapshots;
}

function submissionCollectionChanged(
    PDO $pdo,
    string $table,
    string $primaryKey,
    int $employeeId,
    array $submittedRows,
    array $columns,
    array $ignoreNullColumns = []
): bool {
    $stmt = $pdo->prepare(
        "SELECT * FROM `{$table}` WHERE employee_id = ? ORDER BY `{$primaryKey}`"
    );
    $stmt->execute([$employeeId]);
    $originalRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($originalRows) !== count($submittedRows)) {
        return true;
    }

    foreach ($submittedRows as $index => $submittedRow) {
        foreach ($columns as $column) {
            $value = $submittedRow[$column] ?? null;
            if (in_array($column, $ignoreNullColumns, true) && ($value === null || $value === '')) {
                continue;
            }
            if (!submissionValuesEqual($originalRows[$index][$column] ?? null, $value)) {
                return true;
            }
        }
    }

    return false;
}

function getBaselineStagingRow(PDO $pdo, string $table, string $primaryKey, int $employeeId): array
{
    $stmt = $pdo->prepare(
        "SELECT * FROM `{$table}` WHERE employee_id = ? AND submission_id IS NULL ORDER BY `{$primaryKey}` DESC LIMIT 1"
    );
    $stmt->execute([$employeeId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new RuntimeException("No baseline staging row was found in {$table}");
    }

    return $row;
}

function submissionDatabaseDate($value, bool $required = false): ?string
{
    $value = trim((string) ($value ?? ''));
    if ($value === '') {
        if ($required) {
            throw new DomainException('A required date is missing from the submitted form.');
        }
        return null;
    }

    if (preg_match('/^\d{4}-(0[1-9]|1[0-2])-\d{2}$/', $value)) {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($date && $date->format('Y-m-d') === $value) {
            return $value;
        }
        throw new DomainException('A submitted employment date is invalid.');
    }

    if (!preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $value, $matches)) {
        throw new DomainException('A submitted employment date has an invalid format.');
    }

    return $matches[1] . '-' . $matches[2] . '-01';
}

function submissionSchoolText($value, $fallback): string
{
    $value = trim((string) ($value ?? ''));
    if ($value === '') {
        $value = trim((string) ($fallback ?? ''));
    }
    if ($value === '') {
        throw new DomainException('A family member school is required.');
    }

    return $value;
}

function submissionUnitText($value, $fallback): string
{
    $value = trim((string) ($value ?? ''));
    if ($value === '') {
        $value = trim((string) ($fallback ?? ''));
    }
    if ($value === '') {
        throw new DomainException('A family member unit is required.');
    }

    return $value;
}

function stageSubmissionRepeaterRow(
    PDO $pdo,
    string $stagingTable,
    string $primaryKey,
    int $employeeId,
    int $submissionId,
    array $overrides
): void {
    $row = getBaselineStagingRow($pdo, $stagingTable, $primaryKey, $employeeId);
    $row = array_replace($row, $overrides, [
        'employee_id' => $employeeId,
        'submission_id' => $submissionId
    ]);
    $row['created_by'] = $employeeId;
    if (array_key_exists('updated_by', $row)) {
        $row['updated_by'] = $employeeId;
    }
    insertStagingRow($pdo, $stagingTable, $row, ['id', 'referral_id']);
}

function buildSubmissionReferralRows(PDO $pdo, int $employeeId, array $referral): array
{
    $baseline = getBaselineStagingRow($pdo, 'temp_employee_referrals', 'referral_id', $employeeId);
    $working = is_array($referral['family_members'] ?? null) ? $referral['family_members'] : [];
    $students = is_array($referral['students'] ?? null) ? $referral['students'] : [];
    $rowCount = max(1, count($working), count($students));
    $rows = [];

    for ($index = 0; $index < $rowCount; $index++) {
        $workingMember = is_array($working[$index] ?? null) ? $working[$index] : [];
        $student = is_array($students[$index] ?? null) ? $students[$index] : [];
        $workingStatus = (string) ($referral['family_members_working'] ?? 'No');
        $studyingStatus = (string) ($referral['family_members_studying'] ?? 'No');
        $rows[] = array_replace($baseline, [
            // 'referral_source' => (string) ($referral['referred_by']['source'] ?? ''),
            'referred_by' => (string) ($referral['referred_by']['name'] ?? ''),
            'family_members_working' => $workingStatus,
            'working_name' => (string) ($workingMember['name'] ?? ''),
            'working_unit' => $workingStatus === 'Yes'
                ? ($workingMember
                    ? submissionUnitText($workingMember['unit'] ?? '', $baseline['working_unit'] ?? '')
                    : '')
                : (string) ($baseline['working_unit'] ?? ''),
            'working_designation' => (string) ($workingMember['designation'] ?? ''),
            'family_members_studying' => $studyingStatus,
            'student_name' => (string) ($student['name'] ?? ''),
            'student_unit' => $studyingStatus === 'Yes'
                ? ($student ? submissionSchoolText($student['school'] ?? '', '') : '')
                : (string) ($baseline['student_unit'] ?? ''),
            'student_class' => (string) ($student['class'] ?? '')
        ]);
    }

    return $rows;
}

function stageSubmissionReferralRows(PDO $pdo, int $employeeId, int $submissionId, array $rows): void
{
    foreach ($rows as $row) {
        $row['employee_id'] = $employeeId;
        $row['submission_id'] = $submissionId;
        insertStagingRow($pdo, 'temp_employee_referrals', $row, ['referral_id']);
    }
}

function stageSparseSubmissionRow(
    PDO $pdo,
    string $table,
    int $employeeId,
    int $submissionId,
    array $changes,
    array $metadata = []
): void {
    $row = array_replace($changes, $metadata, [
        'employee_id' => $employeeId,
        'submission_id' => $submissionId
    ]);
    insertStagingRow($pdo, $table, $row, ['id', 'contact_id', 'employment_id', 'referral_id']);
}

function stageSubmissionRows(
    PDO $pdo,
    int $employeeId,
    int $submissionId,
    array $data,
    bool $persist = true
): array {
    $basic = $data['personal_basic'] ?? [];
    $address = $data['address'] ?? [];
    $documents = $data['documents'] ?? [];
    $personal = $data['personal_details'] ?? [];
    $nok = $data['nok'] ?? [];
    $employment = $data['employment_details'] ?? [];

    $employeeValues = [
        'date_of_birth' => $personal['dob'] ?? null,
        'gender' => $personal['gender'] ?? null,
        'marital_status' => $personal['marital_status'] ?? null,
        'spouse_name' => $personal['spouse_name'] ?? null,
        'father_name' => $personal['father_name'] ?? null,
        'mother_name' => $personal['mother_name'] ?? null,
        'blood_group' => $personal['blood_group'] ?? null,
        'alumni_detail' => (string) ($basic['is_alumni'] ?? ''),
        'alumni_unit' => (string) ($basic['alumni_school'] ?? '')
    ];
    $employeeFields = [
        'date_of_birth',
        'gender',
        'marital_status',
        'spouse_name',
        'father_name',
        'mother_name',
        'blood_group',
        'alumni_detail',
        'alumni_unit'
    ];
    $employeeChanges = getSubmissionTableChanges(
        $pdo,
        'employees',
        'employee_id',
        $employeeId,
        $employeeValues,
        $employeeFields,
        ['alumni_detail' => 'alumni']
    );

    $current = $address['current'] ?? [];
    $permanent = $address['permanent'] ?? [];
    $nokAddress = $nok['address'] ?? [];
    $contactValues = [
        'personal_email' => $basic['email']['value'] ?? null,
        'local_address' => (string) ($current['address1'] ?? ''),
        'local_address1' => (string) ($current['address2'] ?? ''),
        'local_address2' => (string) ($current['address3'] ?? ''),
        'pincode' => (string) ($current['pincode'] ?? ''),
        'city' => (string) ($current['city'] ?? ''),
        'state' => (string) ($current['state'] ?? ''),
        'country' => (string) ($current['country'] ?? ''),
        'permanent_address' => (string) ($permanent['address1'] ?? ''),
        'permanent_address1' => (string) ($permanent['address2'] ?? ''),
        'permanent_address2' => (string) ($permanent['address3'] ?? ''),
        'perm_pincode' => (string) ($permanent['pincode'] ?? ''),
        'perm_city' => (string) ($permanent['city'] ?? ''),
        'perm_state' => (string) ($permanent['state'] ?? ''),
        'perm_country' => (string) ($permanent['country'] ?? ''),
        'nok_name' => (string) ($nok['name'] ?? ''),
        'nok_contact_no' => (string) ($nok['contact_no'] ?? ''),
        'nok_address' => (string) ($nokAddress['address1'] ?? ''),
        'nok_emergency_no' => (string) ($nok['emergency_contact_no'] ?? '')
    ];
    $contactFields = [
        'personal_email',
        'local_address',
        'local_address1',
        'local_address2',
        'pincode',
        'city',
        'state',
        'country',
        'permanent_address',
        'permanent_address1',
        'permanent_address2',
        'perm_pincode',
        'perm_city',
        'perm_state',
        'perm_country',
        'nok_name',
        'nok_contact_no',
        'nok_address',
        'nok_emergency_no'
    ];
    $contactChanges = getSubmissionTableChanges(
        $pdo,
        'employee_contact',
        'contact_id',
        $employeeId,
        $contactValues,
        $contactFields
    );

    $employmentValues = [
        'unit' => $employment['unit'] ?? null,
        'branch' => $employment['campus'] ?? null,
        'designation' => $employment['designation'] ?? null,
        'employee_type' => $employment['employee_type'] ?? null,
        'job_type' => $employment['job_type'] ?? null,
        'reporting_to' => $employment['reporting_to'] ?? null,
        'date_of_joining' => submissionDatabaseDate($employment['date_of_joining'] ?? '')
    ];
    $employmentFields = [
        'unit',
        'branch',
        'designation',
        'employee_type',
        'job_type',
        'reporting_to',
        'date_of_joining'
    ];
    $employmentChanges = getSubmissionTableChanges(
        $pdo,
        'employee_employment',
        'employment_id',
        $employeeId,
        $employmentValues,
        $employmentFields
    );

    $personalDocumentPaths = [
        'aadhar_no' => $documents['aadhaar']['number'] ?? '',
        'aadhar_copy' => $documents['aadhaar']['file_path'] ?? null,
        'aadharlinked' => (string) ($documents['aadhaar_pan_linked'] ?? ''),
        'pan_no' => $documents['pan']['number'] ?? '',
        'pan_copy' => $documents['pan']['file_path'] ?? null,
        'photo' => $personal['photo_file_path'] ?? null,
        'resume' => $documents['resume']['file_path'] ?? null,
        'staff_sign' => $personal['signature_file_path'] ?? null,
        'driving' => null,
        'pg_degree_status' => $data['teacher_details']['bed_status'] ?? null,
        'pg_degree_institution' => $data['teacher_details']['institution'] ?? null,
        'pg_degree_doc' => in_array(
            $data['teacher_details']['is_teacher'] ?? false,
            [false, 0, '0', 'No', 'no'],
            true
        ) ? '' : ($data['teacher_details']['document_file_path'] ?? null)
    ];
    foreach (($data['other_documents'] ?? []) as $document) {
        if (($document['type'] ?? '') === 'driving_license') {
            $personalDocumentPaths['driving'] = $document['file_path'] ?? null;
        }
    }
    foreach (['aadhar_copy', 'pan_copy', 'photo', 'resume', 'staff_sign', 'driving'] as $fileColumn) {
        if ($personalDocumentPaths[$fileColumn] === null || $personalDocumentPaths[$fileColumn] === '') {
            unset($personalDocumentPaths[$fileColumn]);
        }
    }
    $identificationFields = [
        'aadhar_no',
        'aadhar_copy',
        'aadharlinked',
        'pan_no',
        'pan_copy',
        'photo',
        'resume',
        'staff_sign',
        'driving',
        'pg_degree_status',
        'pg_degree_institution',
        'pg_degree_doc'
    ];
    $identificationChanges = getSubmissionTableChanges(
        $pdo,
        'employee_identification',
        'id',
        $employeeId,
        $personalDocumentPaths,
        $identificationFields
    );

    $referralRows = buildSubmissionReferralRows($pdo, $employeeId, $data['referral'] ?? []);
    $referralFields = [
        'referred_by',
        'family_members_working',
        'working_name',
        'working_unit',
        'working_designation',
        'family_members_studying',
        'student_name',
        'student_unit',
        'student_class'
    ];
    $referralsChanged = submissionCollectionChanged(
        $pdo,
        'employee_referrals',
        'referral_id',
        $employeeId,
        $referralRows,
        $referralFields
    );

    $education = $data['education'] ?? [];
    foreach ($education as $entry) {
        if (!is_array($entry)) {
            throw new DomainException('An education entry is invalid.');
        }
    }
    $educationRows = array_map(static fn(array $entry): array => [
        'qualification_type' => (string) ($entry['qualification_type'] ?? ''),
        'qualification_details' => (string) ($entry['qualification_name'] ?? ''),
        'institution' => (string) ($entry['institution'] ?? ''),
        'completion_year' => (string) ($entry['year_of_completion'] ?? ''),
        'certificate_path' => $entry['certificate_file_path'] ?? null
    ], $education);
    $educationChanged = submissionCollectionChanged(
        $pdo,
        'educational_qualifications',
        'id',
        $employeeId,
        $educationRows,
        ['qualification_type', 'qualification_details', 'institution', 'completion_year', 'certificate_path'],
        ['certificate_path']
    );

    $workExperienceNotApplicable = !empty($data['work_experience_not_applicable']);
    $workExperience = $workExperienceNotApplicable
        ? []
        : ($data['work_experience'] ?? []);
    foreach ($workExperience as $entry) {
        if (!is_array($entry)) {
            throw new DomainException('A work experience entry is invalid.');
        }
    }
    $experienceRows = array_map(static fn(array $entry): array => [
        'company_name' => (string) ($entry['company'] ?? ''),
        'role' => (string) ($entry['designation'] ?? ''),
        'start_date' => submissionDatabaseDate($entry['start_month'] ?? '', true),
        'end_date' => submissionDatabaseDate($entry['end_month'] ?? ''),
        'experience_details' => (string) ($entry['details'] ?? ''),
        'pay_slip' => (string) ($entry['pay_slip_file_path'] ?? ''),
        'experience_letter_path' => $entry['experience_letter_file_path'] ?? null
    ], $workExperience);
    $experienceChanged = submissionCollectionChanged(
        $pdo,
        'employee_previous_experience',
        'id',
        $employeeId,
        $experienceRows,
        [
            'company_name',
            'role',
            'start_date',
            'end_date',
            'experience_details',
            'pay_slip',
            'experience_letter_path'
        ],
        ['experience_letter_path']
    );

    $changedFields = [];
    foreach ([
        'employees' => $employeeChanges,
        'employee_contact' => $contactChanges,
        'employee_employment' => $employmentChanges,
        'employee_identification' => $identificationChanges
    ] as $table => $changes) {
        foreach (array_keys($changes) as $column) {
            $changedFields[] = "{$table}.{$column}";
        }
    }
    if ($referralsChanged) {
        $changedFields[] = 'employee_referrals.all';
    }
    if ($educationChanged) {
        $changedFields[] = 'educational_qualifications.all';
    }
    if ($experienceChanged) {
        $changedFields[] = 'employee_previous_experience.all';
    }

    if (!$changedFields) {
        return [];
    }
    if (!$persist) {
        return $changedFields;
    }

    stageSparseSubmissionRow(
        $pdo,
        'temp_employees',
        $employeeId,
        $submissionId,
        $employeeChanges,
        $employeeChanges ? ['updated_by' => $employeeId, 'updated_on' => date('Y-m-d H:i:s')] : []
    );
    if ($contactChanges) {
        stageSparseSubmissionRow($pdo, 'temp_employee_contact', $employeeId, $submissionId, $contactChanges);
    }
    if ($employmentChanges) {
        stageSparseSubmissionRow(
            $pdo,
            'temp_employee_employment',
            $employeeId,
            $submissionId,
            $employmentChanges,
            ['updated_by' => $employeeId, 'updated_on' => date('Y-m-d H:i:s')]
        );
    }
    if ($identificationChanges) {
        stageSparseSubmissionRow(
            $pdo,
            'temp_employee_identification',
            $employeeId,
            $submissionId,
            $identificationChanges,
            ['updated_by' => $employeeId, 'updated_at' => date('Y-m-d H:i:s')]
        );
    }
    if ($referralsChanged) {
        stageSubmissionReferralRows($pdo, $employeeId, $submissionId, $referralRows);
    }
    if ($educationChanged) {
        foreach ($education as $entry) {
            stageSubmissionRepeaterRow($pdo, 'temp_educational_qualifications', 'id', $employeeId, $submissionId, [
                'qualification_type' => (string) ($entry['qualification_type'] ?? ''),
                'qualification_details' => (string) ($entry['qualification_name'] ?? ''),
                'institution' => (string) ($entry['institution'] ?? ''),
                'completion_year' => (string) ($entry['year_of_completion'] ?? ''),
                'certificate_path' => $entry['certificate_file_path'] ?? null,
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        }
    }
    if ($experienceChanged) {
        foreach ($workExperience as $entry) {
            stageSubmissionRepeaterRow($pdo, 'temp_employee_previous_experience', 'id', $employeeId, $submissionId, [
                'company_name' => (string) ($entry['company'] ?? ''),
                'role' => (string) ($entry['designation'] ?? ''),
                'start_date' => submissionDatabaseDate($entry['start_month'] ?? '', true),
                'end_date' => submissionDatabaseDate($entry['end_month'] ?? ''),
                'experience_details' => (string) ($entry['details'] ?? ''),
                'pay_slip' => (string) ($entry['pay_slip_file_path'] ?? ''),
                'experience_letter_path' => $entry['experience_letter_file_path'] ?? null
            ]);
        }
    }

    return $changedFields;
}
