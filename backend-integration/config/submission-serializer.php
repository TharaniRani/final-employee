<?php
/**
 * Converts the wizard's working state into the compact submission JSON format.
 */
require_once __DIR__ . '/document-storage.php';

function submissionValue(array $data, string $path, $default = '')
{
    $value = $data;
    foreach (explode('.', $path) as $key) {
        if (!is_array($value) || !array_key_exists($key, $value)) {
            return $default;
        }
        $value = $value[$key];
    }

    if (is_array($value) && array_key_exists('original_value', $value)) {
        if (
            ($value['verification'] ?? '') === 'wrong'
            && array_key_exists('corrected_value', $value)
            && $value['corrected_value'] !== null
            && $value['corrected_value'] !== ''
        ) {
            return $value['corrected_value'];
        }
        return $value['original_value'];
    }

    return $value;
}

function employmentSubmissionValue(array $data, string $field, $default = '')
{
    $employmentField = $data['employment_details'][$field] ?? null;
    if (!is_array($employmentField)) {
        return $default;
    }

    if (($employmentField['change'] ?? '') === 'Yes') {
        return $employmentField['updated_value'] ?? $default;
    }

    return $employmentField['original_value'] ?? $default;
}

function submissionVerification(array $data, string $path): string
{
    $value = $data;
    foreach (explode('.', $path) as $key) {
        if (!is_array($value) || !array_key_exists($key, $value)) {
            return 'pending';
        }
        $value = $value[$key];
    }

    return is_array($value) ? (string) ($value['verification'] ?? 'pending') : 'pending';
}

function submissionBoolean($value): bool
{
    return in_array($value, [true, 1, '1', 'Yes', 'yes', 'true'], true);
}

function collectSubmissionFileIds($value, array &$ids, string $key = ''): void
{
    if (is_array($value)) {
        foreach ($value as $childKey => $childValue) {
            collectSubmissionFileIds($childValue, $ids, (string) $childKey);
        }
        return;
    }

    if (!preg_match('/(?:^|_)file_id$/', $key) || $value === null || $value === '') {
        return;
    }

    if (!is_scalar($value) || !ctype_digit((string) $value) || (int) $value < 1) {
        throw new DomainException('A document reference is invalid. Please upload the document again.');
    }

    $ids[(string) (int) $value] = (int) $value;
}

function loadSubmissionFilePaths(PDO $pdo, int $employeeId, array $formData): array
{
    $ids = [];
    collectSubmissionFileIds($formData, $ids);
    if (!$ids) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("
        SELECT id, document_type, stored_name, file_path
        FROM employee_documents
        WHERE employee_id = ? AND id IN ($placeholders)
    ");
    $stmt->execute(array_merge([$employeeId], array_values($ids)));

    $paths = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $document) {
        $relativePath = submissionDocumentRelativePath(
            $employeeId,
            (string) $document['document_type'],
            (string) $document['stored_name']
        );
        $recordedPath = str_replace('\\', '/', (string) ($document['file_path'] ?? ''));
        if (str_starts_with($recordedPath, 'employees_temp_documents/')) {
            $expectedTempPath = submissionTemporaryDocumentRelativePath(
                $employeeId,
                (string) $document['document_type'],
                (string) $document['stored_name']
            );
            if ($recordedPath !== $expectedTempPath) {
                throw new DomainException('A staged document path is invalid. Please upload it again.');
            }
            $temporaryAbsolutePath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR .
                str_replace('/', DIRECTORY_SEPARATOR, $expectedTempPath);
            if (!is_file($temporaryAbsolutePath)) {
                throw new DomainException('A staged document is unavailable. Please upload it again.');
            }
            $paths[(string) $document['id']] = $expectedTempPath;
            continue;
        }
        $legacyPath = submissionLegacyDocumentRelativePath($employeeId, (string) $document['stored_name']);
        $projectRoot = dirname(__DIR__, 2);
        if (!is_file(submissionDocumentAbsolutePath(
            $projectRoot,
            $employeeId,
            (string) $document['document_type'],
            (string) $document['stored_name']
        )) && is_file($projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $legacyPath))) {
            $relativePath = $legacyPath;
        }
        $paths[(string) $document['id']] = $relativePath;
    }

    if (count($paths) !== count($ids)) {
        throw new DomainException('A document is missing or does not belong to this employee. Please upload it again.');
    }

    return $paths;
}

function submissionFilePath(array $paths, $fileId): ?string
{
    if ($fileId === null || $fileId === '') {
        return null;
    }

    return $paths[(string) (int) $fileId] ?? null;
}

function buildSubmissionJson(PDO $pdo, int $employeeId, array $data, string $mobile, string $referenceNumber, string $submittedAt): array
{
    $paths = loadSubmissionFilePaths($pdo, $employeeId, $data);
    $sameAsPermanent = submissionBoolean(submissionValue($data, 'address.same_as_permanent'));
    $currentAddress = static function (string $field) use ($data): string {
        return (string) submissionValue($data, 'address.current.' . $field, '');
    };
    $permanentAddress = static function (string $field) use ($data, $sameAsPermanent, $currentAddress): string {
        $currentValue = $currentAddress($field);
        if ($sameAsPermanent && trim($currentValue) !== '') {
            return $currentValue;
        }

        return (string) submissionValue($data, 'address.permanent.' . $field, '');
    };
    $nokAddress = static function (string $field) use ($data): string {
        return (string) submissionValue($data, 'next_of_kin.address.' . $field, '');
    };

    $education = [];
    foreach (($data['education'] ?? []) as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $certificatePath = submissionFilePath($paths, $entry['certificate_file_id'] ?? null);
        $existingEducationId = filter_var($entry['existing_row_id'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1]
        ]);
        if ($certificatePath === null && $existingEducationId !== false && $existingEducationId !== null) {
            $existingCertificate = $pdo->prepare("
                SELECT certificate_path
                FROM educational_qualifications
                WHERE id = ? AND employee_id = ?
            ");
            $existingCertificate->execute([$existingEducationId, $employeeId]);
            $storedCertificatePath = $existingCertificate->fetchColumn();
            if ($storedCertificatePath === false) {
                throw new DomainException('An existing qualification could not be verified. Refresh and try again.');
            }
            $certificatePath = $storedCertificatePath === null || $storedCertificatePath === ''
                ? null
                : (string) $storedCertificatePath;
        }
        $education[] = [
            'qualification_type' => submissionValue($entry, 'qualification_type'),
            'qualification_name' => submissionValue($entry, 'qualification_name'),
            'skilled_in' => submissionValue($entry, 'skilled_in'),
            'special_talents' => submissionValue($entry, 'special_talents'),
            'institution' => submissionValue($entry, 'institution'),
            'year_of_completion' => submissionValue($entry, 'year_of_completion'),
            'certificate_file_path' => $certificatePath,
            'existing_row_id' => $existingEducationId === false ? null : $existingEducationId
        ];
    }

    $familyMembers = [];
    foreach (($data['referral']['family_working_details'] ?? []) as $member) {
        if (!is_array($member)) {
            continue;
        }
        $familyMembers[] = [
            'tmss_id' => submissionValue($member, 'tmss_id'),
            'name' => submissionValue($member, 'name'),
            'unit' => submissionValue($member, 'unit'),
            'designation' => submissionValue($member, 'designation')
        ];
    }

    $students = [];
    foreach (($data['referral']['family_studying_details'] ?? []) as $student) {
        if (!is_array($student)) {
            continue;
        }
        $students[] = [
            'name' => submissionValue($student, 'name'),
            'school' => submissionValue($student, 'school'),
            'class' => submissionValue($student, 'class')
        ];
    }

    $workExperience = [];
    foreach (($data['work_experience']['entries'] ?? []) as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $workExperience[] = [
            'company' => submissionValue($entry, 'company'),
            'designation' => submissionValue($entry, 'designation'),
            'start_month' => submissionValue($entry, 'start_month'),
            'end_month' => submissionValue($entry, 'end_month'),
            'details' => submissionValue($entry, 'details'),
            'experience_letter_file_path' => submissionFilePath($paths, $entry['experience_letter_file_id'] ?? null),
            'pay_slip_file_path' => submissionFilePath($paths, $entry['pay_slip_file_id'] ?? null)
        ];
    }

    $otherDocuments = [];
    foreach (($data['teacher_documents']['other_documents'] ?? []) as $document) {
        if (!is_array($document)) {
            continue;
        }
        $otherDocuments[] = [
            'title' => submissionValue($document, 'title'),
            'file_path' => submissionFilePath($paths, $document['file_id'] ?? null)
        ];
    }

    $verificationStatuses = [
        'full_name' => submissionVerification($data, 'personal_basic.full_name'),
        'email' => submissionVerification($data, 'personal_basic.email')
    ];

    return [
        'employee_id' => $employeeId,
        'verification' => [
            'mobile' => $mobile,
            'otp_verified' => true
        ],
        'personal_basic' => [
            'full_name' => [
                'value' => submissionValue($data, 'personal_basic.full_name'),
                'readonly' => true,
                'verification' => $verificationStatuses['full_name']
            ],
            'email' => [
                'value' => submissionValue($data, 'personal_basic.email'),
                'readonly' => false,
                'verification' => $verificationStatuses['email']
            ],
            'mobile' => [
                'value' => $mobile,
                'readonly' => true,
                'verification' => 'correct'
            ],
            'is_alumni' => submissionValue($data, 'personal_basic.is_alumni'),
            'alumni_school' => submissionValue($data, 'personal_basic.alumni_school')
        ],
        'address' => [
            'current' => [
                'address1' => $currentAddress('address_line1'),
                'address2' => $currentAddress('address_line2'),
                'address3' => $currentAddress('address_line3'),
                'city' => $currentAddress('city'),
                'pincode' => $currentAddress('pincode'),
                'state' => $currentAddress('state'),
                'country' => $currentAddress('country')
            ],
            'same_as_permanent' => $sameAsPermanent,
            'permanent' => [
                'address1' => $permanentAddress('address_line1'),
                'address2' => $permanentAddress('address_line2'),
                'address3' => $permanentAddress('address_line3'),
                'city' => $permanentAddress('city'),
                'pincode' => $permanentAddress('pincode'),
                'state' => $permanentAddress('state'),
                'country' => $permanentAddress('country')
            ]
        ],
        'documents' => [
            'resume' => [
                'file_path' => submissionFilePath($paths, $data['personal_basic']['resume_file_id'] ?? null)
            ],
            'aadhaar' => [
                'name' => submissionValue($data, 'aadhaar_pan.aadhaar.name'),
                'number' => submissionValue($data, 'aadhaar_pan.aadhaar.number'),
                'file_path' => submissionFilePath($paths, $data['aadhaar_pan']['aadhaar']['file_id'] ?? null)
            ],
            'pan' => [
                'number' => submissionValue($data, 'aadhaar_pan.pan.number'),
                'file_path' => submissionFilePath($paths, $data['aadhaar_pan']['pan']['file_id'] ?? null)
            ],
            'aadhaar_pan_linked' => submissionValue($data, 'aadhaar_pan.aadhaar_pan_linked')
        ],
        'personal_details' => [
            'photo_file_path' => submissionFilePath($paths, $data['personal_details']['photo_file_id'] ?? null),
            'signature_file_path' => submissionFilePath($paths, $data['personal_details']['signature_file_id'] ?? null),
            'resume_file_path' => submissionFilePath($paths, $data['personal_basic']['resume_file_id'] ?? null),
            'dob' => submissionValue($data, 'personal_details.date_of_birth'),
            'gender' => submissionValue($data, 'personal_details.gender'),
            'marital_status' => submissionValue($data, 'personal_details.marital_status'),
            'spouse_name' => submissionValue($data, 'personal_details.spouse_name'),
            'father_name' => submissionValue($data, 'personal_details.father_name'),
            'mother_name' => submissionValue($data, 'personal_details.mother_name'),
            'blood_group' => submissionValue($data, 'personal_details.blood_group')
        ],
        'nok' => [
            'name' => submissionValue(
                $data,
                submissionValue($data, 'next_of_kin.name_source') === 'select'
                    ? 'next_of_kin.selected_name'
                    : 'next_of_kin.manual_name'
            ),
            'contact_no' => submissionValue($data, 'next_of_kin.contact'),
            'is_emergency_contact' => submissionValue($data, 'next_of_kin.is_emergency'),
            'emergency_contact_no' => submissionValue($data, 'next_of_kin.is_emergency') === 'No'
                ? submissionValue($data, 'next_of_kin.emergency_contact')
                : '',
            'address' => [
                'address1' => $nokAddress('address_line1'),
                'address2' => $nokAddress('address_line2'),
                'address3' => $nokAddress('address_line3'),
                'city' => $nokAddress('city'),
                'pincode' => $nokAddress('pincode'),
                'state' => $nokAddress('state'),
                'country' => $nokAddress('country')
            ]
        ],
        'referral' => [
            'referred_by' => [
                'name' => submissionValue($data, 'referral.referred_by_name'),
                // 'source' => submissionValue($data, 'referral.referral_source')
            ],
            'family_members_working' => submissionValue($data, 'referral.family_working'),
            'family_members' => $familyMembers,
            'family_members_studying' => submissionValue($data, 'referral.family_studying'),
            'students' => $students
        ],
        'education' => $education,
        'teacher_details' => [
            'is_teacher' => submissionBoolean(submissionValue($data, 'teacher_documents.is_teacher')),
            'bed_status' => submissionValue($data, 'teacher_documents.bed_status'),
            'institution' => submissionValue($data, 'teacher_documents.bed_institution'),
            'document_file_path' => submissionFilePath($paths, $data['teacher_documents']['bed_file_id'] ?? null),
            'proposed_graduation_year' => submissionValue($data, 'teacher_documents.bed_proposed_year'),
            'graduation_year' => submissionValue($data, 'teacher_documents.bed_graduation_year')
        ],
        'other_documents' => array_merge([
            [
                'type' => 'driving_license',
                'file_path' => submissionFilePath($paths, $data['teacher_documents']['driving_license_file_id'] ?? null)
            ],
            [
                'type' => 'police_verification',
                'file_path' => submissionFilePath($paths, $data['teacher_documents']['police_verification_file_id'] ?? null)
            ]
        ], $otherDocuments),
        'work_experience' => $workExperience,
        'work_experience_not_applicable' => (bool) ($data['work_experience']['not_applicable'] ?? false),
        'employment_details' => [
            'unit' => employmentSubmissionValue($data, 'unit'),
            'campus' => employmentSubmissionValue($data, 'campus'),
            'designation' => employmentSubmissionValue($data, 'designation'),
            'employee_type' => employmentSubmissionValue($data, 'employee_type'),
            'job_type' => employmentSubmissionValue($data, 'job_type'),
            'reporting_to' => employmentSubmissionValue($data, 'reporting_to'),
            'date_of_joining' => employmentSubmissionValue($data, 'date_of_joining')
        ],
        'submission' => [
            'status' => 'pending',
            'declaration' => submissionBoolean(submissionValue($data, 'submission.declaration', false)),
            'submitted_at' => $submittedAt,
            'reference_number' => $referenceNumber
        ]
    ];
}
