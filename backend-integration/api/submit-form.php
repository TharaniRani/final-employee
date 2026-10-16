<?php
/**
 * Submit Form Endpoint
 * Final submission of the employee form
 */
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/submission-serializer.php';
require_once __DIR__ . '/../config/submission-staging.php';

session_start();

// Verify CSRF token
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!$csrfToken || $csrfToken !== ($_SESSION['csrf_token'] ?? '')) {
    http_response_code(419);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

// Verify employee is authenticated
$employeeId = $_SESSION['verified_employee_id'] ?? '';
if (empty($employeeId)) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$formData = $input['form_data'] ?? [];

if (empty($formData)) {
    echo json_encode(['success' => false, 'message' => 'No form data provided']);
    exit;
}

// Server-side validation
$validationErrors = validateFormData($formData);
if (!empty($validationErrors)) {
    echo json_encode([
        'success' => false,
        'message' => 'Validation failed',
        'errors' => $validationErrors
    ]);
    exit;
}

try {
    $pdo = getDatabaseConnection();

    $employeeId = (int) $employeeId;
    $mobile = (string) ($_SESSION['verified_mobile'] ?? '');
    $submittedAt = date('c');
    $pdo->beginTransaction();

    $employeeLock = $pdo->prepare('SELECT employee_id FROM employees WHERE employee_id = ? FOR UPDATE');
    $employeeLock->execute([$employeeId]);
    if ($employeeLock->fetchColumn() === false) {
        throw new DomainException('Your employee record could not be found. Please contact HR.');
    }

    $pending = $pdo->prepare("
        SELECT id
        FROM employee_form_submissions
        WHERE employee_id = ? AND status IN ('submitted', 'pending')
        ORDER BY id DESC
        LIMIT 1
        FOR UPDATE
    ");
    $pending->execute([$employeeId]);
    if ($pending->fetchColumn() !== false) {
        throw new DomainException('Your form is already waiting for HR approval.');
    }

    $rejected = $pdo->prepare("
        SELECT id
        FROM employee_form_submissions
        WHERE employee_id = ? AND status = 'rejected'
        ORDER BY id DESC
        LIMIT 1
        FOR UPDATE
    ");
    $rejected->execute([$employeeId]);
    $resubmissionOf = $rejected->fetchColumn();
    $resubmissionOf = $resubmissionOf === false ? null : (int) $resubmissionOf;

    $referenceNumber = 'EMP-' . date('Ymd') . '-' . str_pad((string) $employeeId, 6, '0', STR_PAD_LEFT) . '-' . strtoupper(bin2hex(random_bytes(3)));
    $submissionData = buildSubmissionJson($pdo, $employeeId, $formData, $mobile, $referenceNumber, $submittedAt);
    $encodedFormData = json_encode($submissionData, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $stmt = $pdo->prepare("
        SELECT id
        FROM employee_form_submissions
        WHERE employee_id = ? AND status = 'draft'
        ORDER BY updated_at DESC, id DESC
        LIMIT 1
        FOR UPDATE
    ");
    $stmt->execute([$employeeId]);
    $draftId = $stmt->fetchColumn();

    if ($draftId !== false) {
        $stmt = $pdo->prepare("
            UPDATE employee_form_submissions
            SET mobile = ?, form_data = ?, status = 'pending',
                reference_number = ?, resubmission_of = ?, submitted_at = NOW(), updated_at = NOW()
            WHERE id = ? AND employee_id = ? AND status = 'draft'
        ");
        $stmt->execute([$mobile, $encodedFormData, $referenceNumber, $resubmissionOf, $draftId, $employeeId]);
        $submissionId = (int) $draftId;
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO employee_form_submissions
                (employee_id, mobile, form_data, status, reference_number, resubmission_of, submitted_at, created_at, updated_at)
            VALUES (?, ?, ?, 'pending', ?, ?, NOW(), NOW(), NOW())
        ");
        $stmt->execute([$employeeId, $mobile, $encodedFormData, $referenceNumber, $resubmissionOf]);
        $submissionId = (int) $pdo->lastInsertId();
    }

    stageSubmissionRows($pdo, $employeeId, $submissionId, $submissionData);

    $stmt = $pdo->prepare("
        INSERT INTO form_submission_logs
            (submission_id, employee_id, reference_number, action, actor_type, actor_id, actor_name, ip_address, user_agent, created_at)
        VALUES (?, ?, ?, 'submit', 'employee', ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $submissionId,
        $employeeId,
        $referenceNumber,
        (string) $employeeId,
        (string) ($submissionData['personal_basic']['full_name']['value'] ?? ''),
        $_SERVER['REMOTE_ADDR'] ?? '',
        $_SERVER['HTTP_USER_AGENT'] ?? ''
    ]);

    $pdo->commit();

    // Clear draft status
    unset($_SESSION['verified_employee_id']);
    unset($_SESSION['verified_mobile']);

    echo json_encode([
        'success' => true,
        'message' => 'Your submission is waiting for HR approval.',
        'status' => 'pending',
        'reference_number' => $referenceNumber,
        'submitted_at' => $submittedAt
    ]);

} catch (DomainException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log("Database error in submit-form: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log("Error in submit-form: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Submission failed']);
}

function validateFormData($data) {
    $errors = [];

    // Personal Basic
    if (empty($data['personal_basic']['full_name']['original_value'])) {
        $errors[] = ['field' => 'personal_basic.full_name', 'message' => 'Full name is required'];
    }
    if (empty($data['personal_basic']['email']['original_value']) || !filter_var($data['personal_basic']['email']['original_value'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = ['field' => 'personal_basic.email', 'message' => 'Valid email is required'];
    }
    if (empty($data['personal_basic']['mobile_number']['original_value']) || !preg_match('/^[6-9]\d{9}$/', $data['personal_basic']['mobile_number']['original_value'])) {
        $errors[] = ['field' => 'personal_basic.mobile_number', 'message' => 'Valid mobile number is required'];
    }

    // Address
    if (empty($data['address']['current']['address_line1']['original_value'])) {
        $errors[] = ['field' => 'address.current.address_line1', 'message' => 'Current address line 1 is required'];
    }
    if (empty($data['address']['current']['city']['original_value'])) {
        $errors[] = ['field' => 'address.current.city', 'message' => 'Current city is required'];
    }
    if (empty($data['address']['current']['pincode']['original_value']) || !preg_match('/^\d{6}$/', $data['address']['current']['pincode']['original_value'])) {
        $errors[] = ['field' => 'address.current.pincode', 'message' => 'Valid PIN code is required'];
    }
    if (empty($data['address']['current']['state']['original_value'])) {
        $errors[] = ['field' => 'address.current.state', 'message' => 'Current state is required'];
    }

    // Permanent address if different
    if (($data['address']['same_as_permanent']['original_value'] ?? '') === 'No') {
        if (empty($data['address']['permanent']['address_line1']['original_value'])) {
            $errors[] = ['field' => 'address.permanent.address_line1', 'message' => 'Permanent address line 1 is required'];
        }
        if (empty($data['address']['permanent']['city']['original_value'])) {
            $errors[] = ['field' => 'address.permanent.city', 'message' => 'Permanent city is required'];
        }
        if (empty($data['address']['permanent']['pincode']['original_value']) || !preg_match('/^\d{6}$/', $data['address']['permanent']['pincode']['original_value'])) {
            $errors[] = ['field' => 'address.permanent.pincode', 'message' => 'Valid permanent PIN code is required'];
        }
        if (empty($data['address']['permanent']['state']['original_value'])) {
            $errors[] = ['field' => 'address.permanent.state', 'message' => 'Permanent state is required'];
        }
    }

    // Aadhaar & PAN
    if (empty($data['aadhaar_pan']['aadhaar']['name']['original_value'])) {
        $errors[] = ['field' => 'aadhaar_pan.aadhaar.name', 'message' => 'Aadhaar name is required'];
    }
    if (empty($data['aadhaar_pan']['aadhaar']['number']['original_value']) || !preg_match('/^\d{12}$/', $data['aadhaar_pan']['aadhaar']['number']['original_value'])) {
        $errors[] = ['field' => 'aadhaar_pan.aadhaar.number', 'message' => 'Valid Aadhaar number is required'];
    }
    if (empty($data['aadhaar_pan']['aadhaar']['file_id'])) {
        $errors[] = ['field' => 'aadhaar_pan.aadhaar.file_id', 'message' => 'Aadhaar document is required'];
    }
    if (empty($data['aadhaar_pan']['pan']['number']['original_value']) || !preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/', $data['aadhaar_pan']['pan']['number']['original_value'])) {
        $errors[] = ['field' => 'aadhaar_pan.pan.number', 'message' => 'Valid PAN is required'];
    }
    if (empty($data['aadhaar_pan']['pan']['file_id'])) {
        $errors[] = ['field' => 'aadhaar_pan.pan.file_id', 'message' => 'PAN document is required'];
    }
    if (empty($data['aadhaar_pan']['aadhaar_pan_linked']['original_value'])) {
        $errors[] = ['field' => 'aadhaar_pan.aadhaar_pan_linked', 'message' => 'Aadhaar-PAN link status is required'];
    }

    // Personal Details
    if (empty($data['personal_details']['photo_file_id'])) {
        $errors[] = ['field' => 'personal_details.photo_file_id', 'message' => 'Photograph is required'];
    }
    if (empty($data['personal_details']['signature_file_id'])) {
        $errors[] = ['field' => 'personal_details.signature_file_id', 'message' => 'Signature is required'];
    }
    if (empty($data['personal_details']['date_of_birth']['original_value'])) {
        $errors[] = ['field' => 'personal_details.date_of_birth', 'message' => 'Date of birth is required'];
    }
    if (empty($data['personal_details']['gender']['original_value'])) {
        $errors[] = ['field' => 'personal_details.gender', 'message' => 'Gender is required'];
    }
    if (empty($data['personal_details']['marital_status']['original_value'])) {
        $errors[] = ['field' => 'personal_details.marital_status', 'message' => 'Marital status is required'];
    }
    if (($data['personal_details']['marital_status']['original_value'] ?? '') === 'Married' && empty($data['personal_details']['spouse_name']['original_value'])) {
        $errors[] = ['field' => 'personal_details.spouse_name', 'message' => 'Spouse name is required for married employees'];
    }
    if (empty($data['personal_details']['father_name']['original_value'])) {
        $errors[] = ['field' => 'personal_details.father_name', 'message' => "Father's name is required"];
    }
    if (empty($data['personal_details']['mother_name']['original_value'])) {
        $errors[] = ['field' => 'personal_details.mother_name', 'message' => "Mother's name is required"];
    }
    if (empty($data['personal_details']['blood_group']['original_value'])) {
        $errors[] = ['field' => 'personal_details.blood_group', 'message' => 'Blood group is required'];
    }

    // Next of Kin
    if (empty($data['next_of_kin']['name_source']['original_value'])) {
        $errors[] = ['field' => 'next_of_kin.name_source', 'message' => 'NOK name source is required'];
    }
    if (($data['next_of_kin']['name_source']['original_value'] ?? '') === 'select' && empty($data['next_of_kin']['selected_name']['original_value'])) {
        $errors[] = ['field' => 'next_of_kin.selected_name', 'message' => 'Please select NOK'];
    }
    if (($data['next_of_kin']['name_source']['original_value'] ?? '') === 'manual' && empty($data['next_of_kin']['manual_name']['original_value'])) {
        $errors[] = ['field' => 'next_of_kin.manual_name', 'message' => 'NOK name is required'];
    }
    if (empty($data['next_of_kin']['contact']['original_value']) || !preg_match('/^[6-9]\d{9}$/', $data['next_of_kin']['contact']['original_value'])) {
        $errors[] = ['field' => 'next_of_kin.contact', 'message' => 'Valid NOK contact number is required'];
    }
    if (empty($data['next_of_kin']['is_emergency']['original_value'])) {
        $errors[] = ['field' => 'next_of_kin.is_emergency', 'message' => 'Emergency contact confirmation is required'];
    }
    if (empty($data['next_of_kin']['address']['address_line1']['original_value'])) {
        $errors[] = ['field' => 'next_of_kin.address.address_line1', 'message' => 'NOK address line 1 is required'];
    }
    if (empty($data['next_of_kin']['address']['city']['original_value'])) {
        $errors[] = ['field' => 'next_of_kin.address.city', 'message' => 'NOK city is required'];
    }
    if (empty($data['next_of_kin']['address']['pincode']['original_value']) || !preg_match('/^\d{6}$/', $data['next_of_kin']['address']['pincode']['original_value'])) {
        $errors[] = ['field' => 'next_of_kin.address.pincode', 'message' => 'Valid NOK PIN code is required'];
    }
    if (empty($data['next_of_kin']['address']['state']['original_value'])) {
        $errors[] = ['field' => 'next_of_kin.address.state', 'message' => 'NOK state is required'];
    }

    // Referral
    $familyWorkingStatus = submissionValue($data, 'referral.family_working');
    if (empty($familyWorkingStatus)) {
        $errors[] = ['field' => 'referral.family_working', 'message' => 'Family working status is required'];
    }
    if ($familyWorkingStatus === 'Yes') {
        $familyWorkingDetails = $data['referral']['family_working_details'] ?? [];
        if (!is_array($familyWorkingDetails)) {
            $familyWorkingDetails = [];
        }
        if (count($familyWorkingDetails) === 0) {
            $errors[] = ['field' => 'referral.family_working', 'message' => 'Add at least one family member working in the organisation'];
        }
        foreach ($familyWorkingDetails as $index => $member) {
            $member = is_array($member) ? $member : [];
            if (empty($member['name']['original_value'])) {
                $errors[] = ['field' => "referral.family_working_details[$index].name", 'message' => "Family member $index name is required"];
            }
            if (empty($member['unit']['original_value'])) {
                $errors[] = ['field' => "referral.family_working_details[$index].unit", 'message' => "Family member $index unit is required"];
            }
            if (empty($member['designation']['original_value'])) {
                $errors[] = ['field' => "referral.family_working_details[$index].designation", 'message' => "Family member $index designation is required"];
            }
        }
    }
    $familyStudyingStatus = submissionValue($data, 'referral.family_studying');
    if (empty($familyStudyingStatus)) {
        $errors[] = ['field' => 'referral.family_studying', 'message' => 'Family studying status is required'];
    }
    if ($familyStudyingStatus === 'Yes') {
        $familyStudyingDetails = $data['referral']['family_studying_details'] ?? [];
        if (!is_array($familyStudyingDetails)) {
            $familyStudyingDetails = [];
        }
        if (count($familyStudyingDetails) === 0) {
            $errors[] = ['field' => 'referral.family_studying', 'message' => 'Add at least one family member studying in the institution'];
        }
        foreach ($familyStudyingDetails as $index => $student) {
            $student = is_array($student) ? $student : [];
            if (empty($student['name']['original_value'])) {
                $errors[] = ['field' => "referral.family_studying_details[$index].name", 'message' => "Family student $index name is required"];
            }
            if (empty($student['school']['original_value'])) {
                $errors[] = ['field' => "referral.family_studying_details[$index].school", 'message' => "Family student $index school is required"];
            }
            if (empty($student['class']['original_value'])) {
                $errors[] = ['field' => "referral.family_studying_details[$index].class", 'message' => "Family student $index class is required"];
            }
        }
    }

    // Education
    if (empty($data['education']) || !is_array($data['education']) || count($data['education']) === 0) {
        $errors[] = ['field' => 'education', 'message' => 'At least one qualification is required'];
    } else {
        foreach ($data['education'] as $index => $edu) {
            if (empty($edu['qualification_type']['original_value'])) {
                $errors[] = ['field' => "education[$index].qualification_type", 'message' => "Qualification $index type is required"];
            }
            if (empty($edu['qualification_name']['original_value'])) {
                $errors[] = ['field' => "education[$index].qualification_name", 'message' => "Qualification $index name is required"];
            }
            if (empty($edu['institution']['original_value'])) {
                $errors[] = ['field' => "education[$index].institution", 'message' => "Qualification $index institution is required"];
            }
            if (empty($edu['year_of_completion']['original_value'])) {
                $errors[] = ['field' => "education[$index].year_of_completion", 'message' => "Qualification $index year is required"];
            }
            if (empty($edu['certificate_file_id'])) {
                $errors[] = ['field' => "education[$index].certificate_file_id", 'message' => "Qualification $index certificate is required"];
            }
            if (($edu['qualification_type']['original_value'] ?? '') === 'Others' && empty($edu['skilled_in']['original_value'])) {
                $errors[] = ['field' => "education[$index].skilled_in", 'message' => "Skills required for 'Others' qualification type"];
            }
        }
    }

    // Teacher Documents
    if (empty($data['teacher_documents']['is_teacher']['original_value'])) {
        $errors[] = ['field' => 'teacher_documents.is_teacher', 'message' => 'Teacher status is required'];
    }
    if (($data['teacher_documents']['is_teacher']['original_value'] ?? '') === 'Yes') {
        if (empty($data['teacher_documents']['bed_status']['original_value'])) {
            $errors[] = ['field' => 'teacher_documents.bed_status', 'message' => 'B.Ed status is required'];
        }
        if (empty($data['teacher_documents']['bed_institution']['original_value'])) {
            $errors[] = ['field' => 'teacher_documents.bed_institution', 'message' => 'B.Ed institution is required'];
        }
        if (empty($data['teacher_documents']['bed_file_id'])) {
            $errors[] = ['field' => 'teacher_documents.bed_file_id', 'message' => 'B.Ed document is required'];
        }
        if (($data['teacher_documents']['bed_status']['original_value'] ?? '') === 'Joined' && empty($data['teacher_documents']['bed_proposed_year']['original_value'])) {
            $errors[] = ['field' => 'teacher_documents.bed_proposed_year', 'message' => 'Proposed graduation year is required'];
        }
        if (($data['teacher_documents']['bed_status']['original_value'] ?? '') === 'Completed' && empty($data['teacher_documents']['bed_graduation_year']['original_value'])) {
            $errors[] = ['field' => 'teacher_documents.bed_graduation_year', 'message' => 'Graduation year is required'];
        }
    }

    // Work Experience
    if (empty($data['work_experience']['not_applicable']) && (!isset($data['work_experience']['entries']) || count($data['work_experience']['entries']) === 0)) {
        $errors[] = ['field' => 'work_experience', 'message' => 'At least one work experience entry is required (or select Not Applicable)'];
    }
    if (empty($data['work_experience']['not_applicable'])) {
        foreach ($data['work_experience']['entries'] as $index => $exp) {
            if (empty($exp['company']['original_value'])) {
                $errors[] = ['field' => "work_experience.entries[$index].company", 'message' => "Experience $index company is required"];
            }
            if (empty($exp['designation']['original_value'])) {
                $errors[] = ['field' => "work_experience.entries[$index].designation", 'message' => "Experience $index designation is required"];
            }
            if (empty($exp['start_month']['original_value'])) {
                $errors[] = ['field' => "work_experience.entries[$index].start_month", 'message' => "Experience $index start month is required"];
            }
            if (empty($exp['end_month']['original_value'])) {
                $errors[] = ['field' => "work_experience.entries[$index].end_month", 'message' => "Experience $index end month is required"];
            }
            // Validate that end_month is not before start_month
            if (!empty($exp['start_month']['original_value']) && !empty($exp['end_month']['original_value'])) {
                $startMonth = $exp['start_month']['original_value'];
                $endMonth = $exp['end_month']['original_value'];
                if ($endMonth < $startMonth) {
                    $errors[] = ['field' => "work_experience.entries[$index].end_month", 'message' => "Experience $index leaving month cannot be before starting month"];
                }
            }
            if (empty($exp['details']['original_value'])) {
                $errors[] = ['field' => "work_experience.entries[$index].details", 'message' => "Experience $index details are required"];
            }
        }
    }

    $employmentFields = [
        'unit' => 'Unit',
        'campus' => 'Campus',
        'designation' => 'Designation',
        'employee_type' => 'Employee type',
        'job_type' => 'Job type',
        'reporting_to' => 'Reporting To',
        'date_of_joining' => 'Date of joining',
        'probation_period' => 'Probation period',
        'probation_start_date' => 'Probation start date',
        'probation_end_date' => 'Probation end date'
    ];
    foreach ($employmentFields as $field => $label) {
        $change = $data['employment_details'][$field]['change'] ?? '';
        if (!in_array($change, ['Yes', 'No'], true)) {
            $errors[] = [
                'field' => 'employment_details.' . $field . '.change',
                'message' => 'Choose whether to change ' . $label
            ];
            continue;
        }

        $value = employmentSubmissionValue($data, $field, '');
        if (!is_string($value) || trim($value) === '') {
            $errors[] = [
                'field' => 'employment_details.' . $field,
                'message' => $label . ' is required'
            ];
        } elseif (in_array($field, ['date_of_joining', 'probation_start_date', 'probation_end_date'], true) &&
            !isValidFormDate($value)) {
            $errors[] = [
                'field' => 'employment_details.' . $field,
                'message' => $label . ' must be a valid date'
            ];
        }
    }
    $probationStart = employmentSubmissionValue($data, 'probation_start_date', '');
    $probationEnd = employmentSubmissionValue($data, 'probation_end_date', '');
    if (isValidFormDate($probationStart) && isValidFormDate($probationEnd) && $probationEnd < $probationStart) {
        $errors[] = [
            'field' => 'employment_details.probation_end_date',
            'message' => 'Probation end date cannot be before the probation start date'
        ];
    }

    // Declaration
    if (empty($data['submission']['declaration'])) {
        $errors[] = ['field' => 'submission.declaration', 'message' => 'Declaration must be accepted'];
    }
    // Terms and Conditions
    if (empty($data['submission']['terms_accepted'])) {
        $errors[] = ['field' => 'submission.terms_accepted', 'message' => 'Terms and Conditions must be accepted'];
    }

    return $errors;
}

function isValidFormDate($value): bool
{
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return false;
    }

    $date = DateTime::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
}
