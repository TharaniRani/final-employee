<?php
/**
 * Queries for the finalized HRMS employee schema.
 */
require_once __DIR__ . '/document-storage.php';

function hrmsMobileSql(string $column): string
{
    return "RIGHT(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE($column, ''), ' ', ''), '-', ''), '+', ''), '(', ''), ')', ''), '.', ''), 10)";
}

function findHrmsEmployeeIdByMobile(PDO $pdo, string $mobile): ?int
{
    $employeeMobile = hrmsMobileSql('e.mobile_no');

    $stmt = $pdo->prepare("
        SELECT e.employee_id
        FROM employees e
        WHERE LOWER(TRIM(e.working_status)) IN ('working', 'active')
          AND $employeeMobile = ?
        ORDER BY e.employee_id
        LIMIT 2
    ");
    $stmt->execute([$mobile]);
    $employeeIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (count($employeeIds) > 1) {
        throw new RuntimeException('The mobile number is associated with multiple employee records');
    }
    if (!$employeeIds) {
        return null;
    }

    return (int) $employeeIds[0];
}

function findHrmsFamilyMemberEmploymentByMssId(PDO $pdo, string $mssEmployeeId): ?array
{
    $stmt = $pdo->prepare("
        SELECT e.employee_id, e.mss_employee_id, e.full_name, em.unit, em.designation
        FROM employees e
        INNER JOIN employee_employment em
            ON em.employment_id = (
                SELECT latest_employment.employment_id
                FROM employee_employment latest_employment
                WHERE latest_employment.employee_id = e.employee_id
                ORDER BY latest_employment.employment_id DESC
                LIMIT 1
            )
        WHERE TRIM(e.mss_employee_id) = ?
          AND LOWER(TRIM(e.working_status)) IN ('working', 'active')
        ORDER BY e.employee_id
        LIMIT 2
    ");
    $stmt->execute([$mssEmployeeId]);
    $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($matches) > 1) {
        throw new RuntimeException('The MSS employee ID is associated with multiple employee records');
    }

    return $matches[0] ?? null;
}

function getHrmsEmployeeById(PDO $pdo, int $employeeId): ?array
{
    $stmt = $pdo->prepare("
        SELECT
            e.employee_id,
            e.mss_employee_id,
            e.full_name,
            e.date_of_birth,
            e.gender,
            e.marital_status,
            e.spouse_name,
            e.father_name,
            e.mother_name,
            e.blood_group,
            e.working_status,
            COALESCE(
                NULLIF(TRIM((SELECT c.mobile_no FROM employee_contact c WHERE c.employee_id = e.employee_id ORDER BY c.contact_id DESC LIMIT 1)), ''),
                NULLIF(TRIM(e.mobile_no), ''),
                NULLIF(TRIM((SELECT em.mobile_no FROM employee_employment em WHERE em.employee_id = e.employee_id ORDER BY em.employment_id DESC LIMIT 1)), '')
            ) AS mobile,
            (SELECT c.personal_email FROM employee_contact c WHERE c.employee_id = e.employee_id ORDER BY c.contact_id DESC LIMIT 1) AS email,
            (SELECT c.local_address FROM employee_contact c WHERE c.employee_id = e.employee_id ORDER BY c.contact_id DESC LIMIT 1) AS current_address,
            (SELECT c.local_address1 FROM employee_contact c WHERE c.employee_id = e.employee_id ORDER BY c.contact_id DESC LIMIT 1) AS current_address_line2,
            (SELECT c.local_address2 FROM employee_contact c WHERE c.employee_id = e.employee_id ORDER BY c.contact_id DESC LIMIT 1) AS current_address_line3,
            (SELECT c.pincode FROM employee_contact c WHERE c.employee_id = e.employee_id ORDER BY c.contact_id DESC LIMIT 1) AS current_pincode,
            (SELECT c.city FROM employee_contact c WHERE c.employee_id = e.employee_id ORDER BY c.contact_id DESC LIMIT 1) AS current_city,
            (SELECT c.state FROM employee_contact c WHERE c.employee_id = e.employee_id ORDER BY c.contact_id DESC LIMIT 1) AS current_state,
            (SELECT c.country FROM employee_contact c WHERE c.employee_id = e.employee_id ORDER BY c.contact_id DESC LIMIT 1) AS current_country,
            (SELECT c.permanent_address FROM employee_contact c WHERE c.employee_id = e.employee_id ORDER BY c.contact_id DESC LIMIT 1) AS permanent_address,
            (SELECT c.permanent_address1 FROM employee_contact c WHERE c.employee_id = e.employee_id ORDER BY c.contact_id DESC LIMIT 1) AS permanent_address_line2,
            (SELECT c.permanent_address2 FROM employee_contact c WHERE c.employee_id = e.employee_id ORDER BY c.contact_id DESC LIMIT 1) AS permanent_address_line3,
            (SELECT c.perm_pincode FROM employee_contact c WHERE c.employee_id = e.employee_id ORDER BY c.contact_id DESC LIMIT 1) AS permanent_pincode,
            (SELECT c.perm_city FROM employee_contact c WHERE c.employee_id = e.employee_id ORDER BY c.contact_id DESC LIMIT 1) AS permanent_city,
            (SELECT c.perm_state FROM employee_contact c WHERE c.employee_id = e.employee_id ORDER BY c.contact_id DESC LIMIT 1) AS permanent_state,
            (SELECT c.perm_country FROM employee_contact c WHERE c.employee_id = e.employee_id ORDER BY c.contact_id DESC LIMIT 1) AS permanent_country,
            (SELECT i.aadharlinked FROM employee_identification i WHERE i.employee_id = e.employee_id ORDER BY i.id DESC LIMIT 1) AS aadhaar_pan_linked,
            (SELECT c.nok_name FROM employee_contact c WHERE c.employee_id = e.employee_id ORDER BY c.contact_id DESC LIMIT 1) AS nok_name,
            (SELECT c.nok_contact_no FROM employee_contact c WHERE c.employee_id = e.employee_id ORDER BY c.contact_id DESC LIMIT 1) AS nok_contact,
            (SELECT c.nok_emergency_no FROM employee_contact c WHERE c.employee_id = e.employee_id ORDER BY c.contact_id DESC LIMIT 1) AS nok_emergency_contact,
            (SELECT c.nok_address FROM employee_contact c WHERE c.employee_id = e.employee_id ORDER BY c.contact_id DESC LIMIT 1) AS nok_address,
            NULLIF(TRIM(e.alumni_detail), '') AS alumni_detail,
            NULLIF(TRIM(e.alumni_unit), '') AS alumni_unit,
            (SELECT i.aadhar_no FROM employee_identification i WHERE i.employee_id = e.employee_id ORDER BY i.id DESC LIMIT 1) AS aadhaar_number,
            (SELECT i.pan_no FROM employee_identification i WHERE i.employee_id = e.employee_id ORDER BY i.id DESC LIMIT 1) AS pan_number,
            (SELECT i.pg_degree_status FROM employee_identification i WHERE i.employee_id = e.employee_id ORDER BY i.id DESC LIMIT 1) AS pg_degree_status,
            (SELECT i.pg_degree_institution FROM employee_identification i WHERE i.employee_id = e.employee_id ORDER BY i.id DESC LIMIT 1) AS pg_degree_institution,
            (SELECT r.referred_by FROM employee_referrals r WHERE r.employee_id = e.employee_id ORDER BY r.referral_id DESC LIMIT 1) AS referred_by,
            (SELECT r.family_members_working FROM employee_referrals r WHERE r.employee_id = e.employee_id ORDER BY r.referral_id DESC LIMIT 1) AS family_working,
            (SELECT r.family_members_studying FROM employee_referrals r WHERE r.employee_id = e.employee_id ORDER BY r.referral_id DESC LIMIT 1) AS family_studying,
            NULL AS family_working_tmss_id,
            (SELECT r.working_name FROM employee_referrals r WHERE r.employee_id = e.employee_id AND NULLIF(TRIM(r.working_name), '') IS NOT NULL ORDER BY r.referral_id DESC LIMIT 1) AS family_working_name,
            (SELECT r.working_unit FROM employee_referrals r WHERE r.employee_id = e.employee_id AND NULLIF(r.working_name, '') IS NOT NULL ORDER BY r.referral_id DESC LIMIT 1) AS family_working_unit,
            (SELECT r.working_designation FROM employee_referrals r WHERE r.employee_id = e.employee_id AND NULLIF(TRIM(r.working_name), '') IS NOT NULL ORDER BY r.referral_id DESC LIMIT 1) AS family_working_designation,
            (SELECT r.student_name FROM employee_referrals r WHERE r.employee_id = e.employee_id AND NULLIF(TRIM(r.student_name), '') IS NOT NULL ORDER BY r.referral_id DESC LIMIT 1) AS family_studying_name,
            (SELECT r.student_unit FROM employee_referrals r WHERE r.employee_id = e.employee_id AND NULLIF(r.student_name, '') IS NOT NULL ORDER BY r.referral_id DESC LIMIT 1) AS family_studying_school,
            (SELECT r.student_class FROM employee_referrals r WHERE r.employee_id = e.employee_id AND NULLIF(TRIM(r.student_name), '') IS NOT NULL ORDER BY r.referral_id DESC LIMIT 1) AS family_studying_class,
            (SELECT NULLIF(TRIM(em.unit), '') FROM employee_employment em WHERE em.employee_id = e.employee_id ORDER BY em.employment_id DESC LIMIT 1) AS employment_unit,
            (SELECT NULLIF(TRIM(em.branch), '') FROM employee_employment em WHERE em.employee_id = e.employee_id ORDER BY em.employment_id DESC LIMIT 1) AS employment_campus,
            (SELECT NULLIF(TRIM(em.designation), '') FROM employee_employment em WHERE em.employee_id = e.employee_id ORDER BY em.employment_id DESC LIMIT 1) AS employment_designation,
            (SELECT NULLIF(TRIM(em.employee_type), '') FROM employee_employment em WHERE em.employee_id = e.employee_id ORDER BY em.employment_id DESC LIMIT 1) AS employment_employee_type,
            (SELECT NULLIF(TRIM(em.job_type), '') FROM employee_employment em WHERE em.employee_id = e.employee_id ORDER BY em.employment_id DESC LIMIT 1) AS employment_job_type,
            (SELECT NULLIF(TRIM(em.reporting_to), '') FROM employee_employment em WHERE em.employee_id = e.employee_id ORDER BY em.employment_id DESC LIMIT 1) AS employment_reporting_to,
            (SELECT NULLIF(em.date_of_joining, '0000-00-00') FROM employee_employment em WHERE em.employee_id = e.employee_id ORDER BY em.employment_id DESC LIMIT 1) AS employment_date_of_joining
        FROM employees e
        WHERE e.employee_id = ?
        LIMIT 1
    ");
    $stmt->execute([$employeeId]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$employee) {
        return null;
    }

    $digits = preg_replace('/\D+/', '', (string) ($employee['mobile'] ?? ''));
    $employee['mobile'] = strlen($digits) > 10 ? substr($digits, -10) : $digits;

    $employee['same_as_permanent'] = hrmsAddressesMatch($employee);
    $employee['nok_name_source'] = empty($employee['nok_name'])
        ? ''
        : (hrmsNokNameMatchesFamily($employee) ? 'select' : 'manual');
    $employee['nok_selected_name'] = $employee['nok_name_source'] === 'select' ? (string) ($employee['nok_name'] ?? '') : '';
    $employee['nok_manual_name'] = $employee['nok_name_source'] === 'manual' ? (string) ($employee['nok_name'] ?? '') : '';
    if (!empty($employee['nok_emergency_contact']) && !empty($employee['nok_contact'])) {
        $employee['nok_is_emergency'] = preg_replace('/\D+/', '', $employee['nok_emergency_contact'])
            === preg_replace('/\D+/', '', $employee['nok_contact']) ? 'Yes' : 'No';
    } else {
        $employee['nok_is_emergency'] = '';
    }
    $employee['is_alumni'] = hrmsAlumniStatus($employee['alumni_detail'] ?? null);
    $employee['alumni_school'] = $employee['alumni_unit'] ?? '';

    $employee['education'] = getHrmsEducationRows($pdo, $employeeId);
    $employee['work_experience'] = getHrmsExperienceRows($pdo, $employeeId);
    $employee['referrals'] = getHrmsReferralRows($pdo, $employeeId);

    return $employee;
}

function hrmsAddressesMatch(array $employee): string
{
    $fields = [
        ['current_address', 'permanent_address'],
        ['current_address_line2', 'permanent_address_line2'],
        ['current_address_line3', 'permanent_address_line3'],
        ['current_pincode', 'permanent_pincode'],
        ['current_city', 'permanent_city'],
        ['current_state', 'permanent_state'],
        ['current_country', 'permanent_country']
    ];
    $hasAddress = false;
    foreach ($fields as [$current, $permanent]) {
        $currentValue = trim((string) ($employee[$current] ?? ''));
        $permanentValue = trim((string) ($employee[$permanent] ?? ''));
        if ($currentValue !== '' || $permanentValue !== '') {
            $hasAddress = true;
        }
        if (strcasecmp($currentValue, $permanentValue) !== 0) {
            return false;
        }
    }
    return $hasAddress ? 'Yes' : '';
}

function hrmsNokNameMatchesFamily(array $employee): bool
{
    $nokName = trim((string) ($employee['nok_name'] ?? ''));
    if ($nokName === '') {
        return false;
    }
    foreach (['spouse_name', 'father_name', 'mother_name'] as $field) {
        if ($nokName !== '' && strcasecmp($nokName, trim((string) ($employee[$field] ?? ''))) === 0) {
            return true;
        }
    }
    return false;
}

function hrmsAlumniStatus(?string $value): string
{
    $value = strtolower(trim((string) $value));
    if (in_array($value, ['yes', 'y', 'true', '1', 'alumni'], true)) {
        return 'Yes';
    }
    if (in_array($value, ['no', 'n', 'false', '0'], true)) {
        return 'No';
    }
    return '';
}

function getHrmsEducationRows(PDO $pdo, int $employeeId): array
{
    $stmt = $pdo->prepare("
        SELECT id, qualification_type, qualification_details, institution,
               completion_year, certificate_path
        FROM educational_qualifications
        WHERE employee_id = ?
        ORDER BY id
    ");
    $stmt->execute([$employeeId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        $storedPath = (string) ($row['certificate_path'] ?? '');
        $filePath = $storedPath === ''
            ? null
            : resolveExistingEmployeeDocument(dirname(__DIR__, 2), 'education_certificate', $storedPath);
        $row['certificate_name'] = $filePath === null ? '' : hrmsStoredFileName($storedPath);
        $row['has_certificate'] = $filePath !== null;
        unset($row['certificate_path']);
    }
    unset($row);
    return $rows;
}

function getHrmsExistingIdentityDocuments(PDO $pdo, int $employeeId): array
{
    $stmt = $pdo->prepare("
        SELECT aadhar_copy, pan_copy, photo, resume, staff_sign
        FROM employee_identification
        WHERE employee_id = ?
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$employeeId]);
    $identity = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $documents = [];
    foreach ([
        'aadhaar' => ['aadhar_copy', 'aadhaar'],
        'pan' => ['pan_copy', 'pan'],
        'photo' => ['photo', 'photo'],
        'resume' => ['resume', 'resume'],
        'signature' => ['staff_sign', 'signature']
    ] as $type => [$column, $storageType]) {
        $storedPath = (string) ($identity[$column] ?? '');
        $filePath = $storedPath === ''
            ? null
            : resolveExistingEmployeeDocument(dirname(__DIR__, 2), $storageType, $storedPath);
        $filename = $filePath === null ? '' : hrmsStoredFileName($storedPath);
        $documents[$type] = [
            'filename' => $filename,
            'exists' => $filePath !== null
        ];
    }

    $documentTypes = [
        'aadhaar' => ['aadhaar', 'aadhar'],
        'pan' => ['pan'],
        'photo' => ['photo'],
        'resume' => ['resume'],
        'signature' => ['signature', 'staff_sign']
    ];
    $placeholders = implode(', ', array_fill(0, count(array_unique(array_merge(...array_values($documentTypes)))), '?'));
    $documentStmt = $pdo->prepare("
        SELECT document_type, original_name, file_path
        FROM employee_documents
        WHERE employee_id = ? AND LOWER(REPLACE(document_type, '-', '_')) IN ($placeholders)
        ORDER BY id DESC
    ");
    $documentStmt->execute([
        $employeeId,
        ...array_unique(array_merge(...array_values($documentTypes)))
    ]);
    foreach ($documentStmt->fetchAll(PDO::FETCH_ASSOC) as $document) {
        $type = strtolower(str_replace('-', '_', (string) ($document['document_type'] ?? '')));
        foreach ($documentTypes as $identityType => $aliases) {
            if ($documents[$identityType]['exists'] || !in_array($type, $aliases, true)) {
                continue;
            }

            $filePath = resolveExistingEmployeeDocument(
                dirname(__DIR__, 2),
                $type,
                (string) ($document['file_path'] ?? '')
            );
            if ($filePath === null) {
                continue;
            }

            $documents[$identityType] = [
                'filename' => trim((string) ($document['original_name'] ?? '')) ?: hrmsStoredFileName($filePath),
                'exists' => true
            ];
            break;
        }
    }

    return $documents;
}

function getHrmsExperienceRows(PDO $pdo, int $employeeId): array
{
    $stmt = $pdo->prepare("
        SELECT id, company_name, role,
               NULLIF(start_date, '0000-00-00') AS start_date,
               NULLIF(end_date, '0000-00-00') AS end_date,
               experience_details, pay_slip, experience_letter_path
        FROM employee_previous_experience
        WHERE employee_id = ?
        ORDER BY id
    ");
    $stmt->execute([$employeeId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        $row['pay_slip_name'] = hrmsStoredFileName($row['pay_slip'] ?? null);
        $row['experience_letter_name'] = hrmsStoredFileName($row['experience_letter_path'] ?? null);
    }
    unset($row);
    return $rows;
}

function getHrmsReferralRows(PDO $pdo, int $employeeId): array
{
    $stmt = $pdo->prepare("
        SELECT referral_id, referred_by, family_members_working,
               NULL AS working_tmss_id, working_name, working_unit, working_designation,
               family_members_studying, student_name, student_unit, student_class
        FROM employee_referrals
        WHERE employee_id = ?
        ORDER BY referral_id
    ");
    $stmt->execute([$employeeId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getHrmsEmployeeDocuments(PDO $pdo, int $employeeId): array
{
    $stmt = $pdo->prepare("
        SELECT id, document_type, original_name, file_path
        FROM employee_documents
        WHERE employee_id = ?
        ORDER BY id
    ");
    $stmt->execute([$employeeId]);
    $documents = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $document) {
        $storedPath = str_replace('\\', '/', trim((string) ($document['file_path'] ?? '')));
        $normalizedPath = preg_replace('~^(?:\./|/)+~', '', $storedPath);
        if ($normalizedPath === '' ||
            preg_match('~(?:^|/)employees_temp_documents(?:/|$)~i', $normalizedPath) === 1) {
            continue;
        }

        unset($document['file_path']);
        $documents[] = $document;
    }

    return $documents;
}

function getHrmsLegacyDocumentNames(PDO $pdo, int $employeeId): array
{
    $documents = [];
    $stmt = $pdo->prepare("
        SELECT aadhar_copy, pan_copy, photo, resume, staff_sign, ccs, driving,
               other_docname, others, pg_degree_doc
        FROM employee_identification
        WHERE employee_id = ?
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$employeeId]);
    $identity = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $identityTypes = [
        'aadhar_copy' => 'Aadhaar',
        'pan_copy' => 'PAN',
        'photo' => 'Photo',
        'resume' => 'Resume',
        'staff_sign' => 'Signature',
        'ccs' => 'CCS',
        'driving' => 'Driving document',
        'others' => (string) ($identity['other_docname'] ?? 'Other document'),
        'pg_degree_doc' => 'PG degree document'
    ];
    foreach ($identityTypes as $column => $label) {
        $name = hrmsStoredFileName($identity[$column] ?? null);
        if ($name !== '') {
            $documents[] = ['type' => $label, 'name' => $name];
        }
    }

    $sources = [
        ['educational_qualifications', 'certificate_path', 'Education certificate'],
        ['employee_previous_experience', 'pay_slip', 'Experience pay slip'],
        ['employee_previous_experience', 'experience_letter_path', 'Experience letter']
    ];
    foreach ($sources as [$table, $column, $label]) {
        $query = $pdo->prepare("SELECT $column FROM $table WHERE employee_id = ? AND $column IS NOT NULL AND TRIM($column) <> '' ORDER BY id");
        $query->execute([$employeeId]);
        foreach ($query->fetchAll(PDO::FETCH_COLUMN) as $path) {
            $name = hrmsStoredFileName($path);
            if ($name !== '') {
                $documents[] = ['type' => $label, 'name' => $name];
            }
        }
    }

    return $documents;
}

function hrmsStoredFileName(?string $path): string
{
    $path = trim((string) $path);
    if ($path === '') {
        return '';
    }
    return basename(str_replace('\\', '/', $path));
}
