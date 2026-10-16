<?php
/**
 * Append-only copies of a submitted employee profile in the existing temp_* tables.
 */

function getStagingTableColumns(PDO $pdo, string $table): array
{
    $stmt = $pdo->prepare("
        SELECT COLUMN_NAME
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
    ");
    $stmt->execute([$table]);

    return array_fill_keys($stmt->fetchAll(PDO::FETCH_COLUMN), true);
}

function insertStagingRow(PDO $pdo, string $table, array $row, array $excludedColumns = []): void
{
    $columns = getStagingTableColumns($pdo, $table);
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

function getStagingPrimaryKey(PDO $pdo, string $table): string
{
    $stmt = $pdo->prepare("
        SELECT COLUMN_NAME
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = 'PRIMARY'
        ORDER BY ORDINAL_POSITION
        LIMIT 1
    ");
    $stmt->execute([$table]);
    $primaryKey = $stmt->fetchColumn();
    if (!$primaryKey) {
        throw new RuntimeException("No primary key was found for {$table}");
    }

    return (string) $primaryKey;
}

function copyOriginalToStaging(
    PDO $pdo,
    string $sourceTable,
    string $sourcePrimaryKey,
    string $stagingTable,
    int $employeeId,
    int $submissionId,
    array $overrides
): void {
    $stmt = $pdo->prepare(
        "SELECT * FROM `{$sourceTable}` WHERE employee_id = ? ORDER BY `{$sourcePrimaryKey}` DESC LIMIT 1"
    );
    $stmt->execute([$employeeId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        $row = getBaselineStagingRow(
            $pdo,
            $stagingTable,
            getStagingPrimaryKey($pdo, $stagingTable),
            $employeeId
        );
    }
    $row = array_replace($row, $overrides, [
        'employee_id' => $employeeId,
        'submission_id' => $submissionId
    ]);
    insertStagingRow($pdo, $stagingTable, $row, ['id', 'contact_id', 'employment_id', 'referral_id']);
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

function submissionUnitId($value, $fallback): int
{
    $value = trim((string) ($value ?? ''));
    if ($value === '') {
        $value = trim((string) ($fallback ?? ''));
    }
    if ($value === '' || !ctype_digit($value)) {
        throw new DomainException('A family member unit or school selection is invalid.');
    }

    return (int) $value;
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

function stageSubmissionReferralRows(
    PDO $pdo,
    int $employeeId,
    int $submissionId,
    array $referral
): void {
    $baseline = getBaselineStagingRow($pdo, 'temp_employee_referrals', 'referral_id', $employeeId);
    $working = is_array($referral['family_members'] ?? null) ? $referral['family_members'] : [];
    $students = is_array($referral['students'] ?? null) ? $referral['students'] : [];
    $rowCount = max(1, count($working), count($students));

    for ($index = 0; $index < $rowCount; $index++) {
        $workingMember = $working[$index] ?? [];
        $student = $students[$index] ?? [];
        $row = array_replace($baseline, [
            'employee_id' => $employeeId,
            'submission_id' => $submissionId,
            'referral_source' => (string) ($referral['referred_by']['source'] ?? ''),
            'referred_by' => (string) ($referral['referred_by']['name'] ?? ''),
            'family_members_working' => (string) ($referral['family_members_working'] ?? 'No'),
            'working_name' => (string) ($workingMember['name'] ?? ''),
            'working_unit' => submissionUnitId($workingMember['unit'] ?? '', $baseline['working_unit'] ?? ''),
            'working_designation' => (string) ($workingMember['designation'] ?? ''),
            'family_members_studying' => (string) ($referral['family_members_studying'] ?? 'No'),
            'student_name' => (string) ($student['name'] ?? ''),
            'student_unit' => submissionUnitId($student['school'] ?? '', $baseline['student_unit'] ?? ''),
            'student_class' => (string) ($student['class'] ?? '')
        ]);
        insertStagingRow($pdo, 'temp_employee_referrals', $row, ['referral_id']);
    }
}

function stageSubmissionRows(PDO $pdo, int $employeeId, int $submissionId, array $data): void
{
    $basic = $data['personal_basic'] ?? [];
    $address = $data['address'] ?? [];
    $documents = $data['documents'] ?? [];
    $personal = $data['personal_details'] ?? [];
    $nok = $data['nok'] ?? [];
    $employment = $data['employment_details'] ?? [];

    copyOriginalToStaging($pdo, 'employees', 'employee_id', 'temp_employees', $employeeId, $submissionId, [
        'date_of_birth' => $personal['dob'] ?? null,
        'gender' => $personal['gender'] ?? null,
        'marital_status' => $personal['marital_status'] ?? null,
        'spouse_name' => $personal['spouse_name'] ?? null,
        'father_name' => $personal['father_name'] ?? null,
        'mother_name' => $personal['mother_name'] ?? null,
        'blood_group' => $personal['blood_group'] ?? null,
        'alumni_detail' => (string) ($basic['is_alumni'] ?? ''),
        'alumni_unit' => (string) ($basic['alumni_school'] ?? ''),
        'updated_by' => $employeeId,
        'updated_on' => date('Y-m-d H:i:s')
    ]);

    $current = $address['current'] ?? [];
    $permanent = $address['permanent'] ?? [];
    $nokAddress = $nok['address'] ?? [];
    copyOriginalToStaging($pdo, 'employee_contact', 'contact_id', 'temp_employee_contact', $employeeId, $submissionId, [
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
        'nok_emergency_no' => (string) ($nok['emergency_contact_no'] ?? ''),
        'mobile_no' => (string) ($data['verification']['mobile'] ?? '')
    ]);

    copyOriginalToStaging($pdo, 'employee_employment', 'employment_id', 'temp_employee_employment', $employeeId, $submissionId, [
        'unit' => $employment['unit'] ?? null,
        'branch' => $employment['campus'] ?? null,
        'designation' => $employment['designation'] ?? null,
        'employee_type' => $employment['employee_type'] ?? null,
        'job_type' => $employment['job_type'] ?? null,
        'reporting_to' => $employment['reporting_to'] ?? null,
        'date_of_joining' => $employment['date_of_joining'] ?: null,
        'probation_period' => (string) ($employment['probation_period'] ?? ''),
        'probation_start_date' => $employment['probation_start_date'] ?: null,
        'probation_end_date' => $employment['probation_end_date'] ?: null,
        'updated_by' => $employeeId,
        'updated_on' => date('Y-m-d H:i:s')
    ]);

    $personalDocumentPaths = [
        'aadhar_no' => $documents['aadhaar']['number'] ?? '',
        'aadhar_copy' => $documents['aadhaar']['file_path'] ?? null,
        'aadharlinked' => (string) ($documents['aadhaar_pan_linked'] ?? ''),
        'pan_no' => $documents['pan']['number'] ?? '',
        'pan_copy' => $documents['pan']['file_path'] ?? null,
        'photo' => $personal['photo_file_path'] ?? null,
        'resume' => $documents['resume']['file_path'] ?? null,
        'staff_sign' => $personal['signature_file_path'] ?? null,
        'driving' => null
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
    copyOriginalToStaging($pdo, 'employee_identification', 'id', 'temp_employee_identification', $employeeId, $submissionId, array_replace(
        $personalDocumentPaths,
        [
            'updated_by' => $employeeId,
            'updated_at' => date('Y-m-d H:i:s')
        ]
    ));

    stageSubmissionReferralRows($pdo, $employeeId, $submissionId, $data['referral'] ?? []);

    foreach (($data['education'] ?? []) as $entry) {
        if (!is_array($entry)) {
            throw new DomainException('An education entry is invalid.');
        }
        stageSubmissionRepeaterRow($pdo, 'temp_educational_qualifications', 'id', $employeeId, $submissionId, [
            'qualification_type' => (string) ($entry['qualification_type'] ?? ''),
            'qualification_details' => (string) ($entry['qualification_name'] ?? ''),
            'institution' => (string) ($entry['institution'] ?? ''),
            'completion_year' => (string) ($entry['year_of_completion'] ?? ''),
            'certificate_path' => $entry['certificate_file_path'] ?? null,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }

    foreach (($data['work_experience'] ?? []) as $entry) {
        if (!is_array($entry)) {
            throw new DomainException('A work experience entry is invalid.');
        }
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
