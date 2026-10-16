<?php
/**
 * HR-only queue and decision endpoint for employee form submissions.
 */
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/secure-session.php';
startSecureSession();

$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!$csrfToken || !hash_equals($_SESSION['csrf_token'] ?? '', $csrfToken)) {
    http_response_code(419);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$reviewerId = $_SESSION['hr_user_id'] ?? null;
$reviewerName = trim((string) ($_SESSION['hr_user_name'] ?? ''));
if (($_SESSION['user_role'] ?? '') !== 'HR' || !is_scalar($reviewerId) ||
    trim((string) $reviewerId) === '' || $reviewerName === '') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'HR reviewer authorization is required']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $pdo = getDatabaseConnection();
        $limit = filter_var($_GET['limit'] ?? 50, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 100]
        ]);
        if ($limit === false) {
            throw new DomainException('The queue limit must be between 1 and 100.');
        }

        $stmt = $pdo->prepare("
            SELECT id, employee_id, mobile, form_data, reference_number, submitted_at, resubmission_of
            FROM employee_form_submissions
            WHERE status = 'pending'
            ORDER BY submitted_at ASC, id ASC
            LIMIT ?
        ");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        $submissions = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $formData = json_decode($row['form_data'], true, 512, JSON_THROW_ON_ERROR);
            $submissions[] = [
                'id' => (int) $row['id'],
                'employee_id' => (int) $row['employee_id'],
                'mobile' => $row['mobile'],
                'reference_number' => $row['reference_number'],
                'submitted_at' => $row['submitted_at'],
                'resubmission_of' => $row['resubmission_of'] === null ? null : (int) $row['resubmission_of'],
                'form_data' => $formData
            ];
        }
        echo json_encode(['success' => true, 'submissions' => $submissions]);
    } catch (DomainException $e) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    } catch (Throwable $e) {
        error_log('Unable to load HR review queue: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Unable to load review queue']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: GET, POST');
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'A JSON request body is required']);
    exit;
}

$submissionId = filter_var($input['submission_id'] ?? null, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);
$decision = $input['decision'] ?? '';
$reviewNotes = trim((string) ($input['review_notes'] ?? ''));
$rejectionReason = trim((string) ($input['rejection_reason'] ?? ''));
$approvedFields = $input['approved_fields'] ?? [];

if ($submissionId === false || !in_array($decision, ['approve', 'reject'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'A valid submission ID and decision are required']);
    exit;
}
if ($decision === 'reject' && $rejectionReason === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'A rejection reason is required']);
    exit;
}
if ($decision === 'approve' && (!is_array($approvedFields) || !$approvedFields)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Select at least one field or section to approve']);
    exit;
}
if (!ctype_digit((string) $reviewerId) || (int) $reviewerId < 1) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'The HR reviewer session has an invalid user ID']);
    exit;
}

try {
    $pdo = getDatabaseConnection();
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT id, employee_id, reference_number, status
        FROM employee_form_submissions
        WHERE id = ?
        FOR UPDATE
    ");
    $stmt->execute([$submissionId]);
    $submission = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$submission) {
        throw new DomainException('Submission not found.');
    }
    if ($submission['status'] !== 'pending') {
        throw new DomainException('This submission is no longer pending HR review.');
    }

    $employeeId = (int) $submission['employee_id'];
    if ($decision === 'approve') {
        applyApprovedSubmissionFields($pdo, $employeeId, (int) $submissionId, $approvedFields, (int) $reviewerId);
    }

    $status = $decision === 'approve' ? 'approved' : 'rejected';
    $approvedJson = $decision === 'approve'
        ? json_encode(array_values(array_unique($approvedFields)), JSON_THROW_ON_ERROR)
        : null;
    $stmt = $pdo->prepare("
        UPDATE employee_form_submissions
        SET status = ?, reviewed_by = ?, reviewer_name = ?, reviewed_at = NOW(),
            approved_at = CASE WHEN ? = 'approved' THEN NOW() ELSE NULL END,
            approved_fields = ?, review_notes = ?, rejection_reason = ?, updated_at = NOW()
        WHERE id = ? AND status = 'pending'
    ");
    $stmt->execute([
        $status,
        (string) $reviewerId,
        $reviewerName,
        $status,
        $approvedJson,
        $reviewNotes === '' ? null : $reviewNotes,
        $decision === 'reject' ? $rejectionReason : null,
        $submissionId
    ]);

    $stmt = $pdo->prepare("
        INSERT INTO form_submission_logs
            (submission_id, employee_id, reference_number, action, actor_type, actor_id, actor_name, notes, ip_address, user_agent, created_at)
        VALUES (?, ?, ?, ?, 'hr', ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $submissionId,
        $employeeId,
        $submission['reference_number'],
        $decision,
        (string) $reviewerId,
        $reviewerName,
        $decision === 'reject' ? $rejectionReason : ($reviewNotes === '' ? null : $reviewNotes),
        substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
        substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512)
    ]);

    $pdo->commit();
    echo json_encode([
        'success' => true,
        'status' => $status,
        'message' => $decision === 'approve'
            ? 'Approved fields were applied to the employee record.'
            : 'Submission rejected. Original employee records were not changed.'
    ]);
} catch (DomainException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('HR submission review failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Review could not be completed']);
}

function applyApprovedSubmissionFields(PDO $pdo, int $employeeId, int $submissionId, array $approvedFields, int $reviewerId): void
{
    $fieldMap = submissionApprovalFieldMap();
    $collectionMap = submissionApprovalCollectionMap();
    $allAllowed = array_merge(array_keys($fieldMap), array_keys($collectionMap));
    foreach ($approvedFields as $field) {
        if (!is_string($field) || !in_array($field, $allAllowed, true)) {
            throw new DomainException('An approval field is invalid or not editable.');
        }
    }
    $approvedFields = array_values(array_unique($approvedFields));

    foreach ($approvedFields as $field) {
        if (isset($collectionMap[$field])) {
            replaceApprovedSubmissionCollection(
                $pdo,
                $employeeId,
                $submissionId,
                $reviewerId,
                $collectionMap[$field]
            );
            continue;
        }

        $mapping = $fieldMap[$field];
        $staged = $pdo->prepare(sprintf(
            'SELECT `%s` FROM `%s` WHERE employee_id = ? AND submission_id = ? ORDER BY `%s` DESC LIMIT 1',
            $mapping['column'],
            $mapping['staging_table'],
            $mapping['staging_primary_key']
        ));
        $staged->execute([$employeeId, $submissionId]);
        $value = $staged->fetchColumn();
        if ($value === false) {
            throw new DomainException("Staged value for {$field} was not found.");
        }

        $original = $pdo->prepare(sprintf(
            'SELECT `%s` FROM `%s` WHERE employee_id = ? ORDER BY `%s` DESC LIMIT 1 FOR UPDATE',
            $mapping['original_primary_key'],
            $mapping['original_table'],
            $mapping['original_primary_key']
        ));
        $original->execute([$employeeId]);
        $originalId = $original->fetchColumn();
        if ($originalId === false) {
            throw new DomainException("No original row exists to update for {$field}.");
        }

        $updateColumns = [$mapping['column'] . ' = ?'];
        $parameters = [$value];
        if ($mapping['original_table'] === 'employees') {
            $updateColumns[] = 'updated_by = ?';
            $updateColumns[] = 'updated_on = NOW()';
            $parameters[] = $reviewerId;
        } elseif (in_array($mapping['original_table'], ['employee_identification', 'employee_employment'], true)) {
            $updateColumns[] = 'updated_by = ?';
            $updateColumns[] = $mapping['original_table'] === 'employee_identification'
                ? 'updated_at = NOW()'
                : 'updated_on = NOW()';
            $parameters[] = $reviewerId;
        }
        $parameters[] = $originalId;
        $parameters[] = $employeeId;
        $update = $pdo->prepare(sprintf(
            'UPDATE `%s` SET %s WHERE `%s` = ? AND employee_id = ?',
            $mapping['original_table'],
            implode(', ', $updateColumns),
            $mapping['original_primary_key']
        ));
        $update->execute($parameters);
    }
}

function submissionApprovalFieldMap(): array
{
    $fields = [];
    $tableMappings = [
        'employees' => ['temp_employees', 'temp_row_id', 'employee_id'],
        'employee_contact' => ['temp_employee_contact', 'contact_id', 'contact_id'],
        'employee_identification' => ['temp_employee_identification', 'id', 'id'],
        'employee_employment' => ['temp_employee_employment', 'employment_id', 'employment_id']
    ];
    $columns = [
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
            'photo', 'resume', 'staff_sign', 'driving'
        ],
        'employee_employment' => [
            'unit', 'branch', 'designation', 'employee_type', 'job_type', 'reporting_to',
            'date_of_joining', 'probation_period', 'probation_start_date', 'probation_end_date'
        ]
    ];

    foreach ($columns as $table => $tableColumns) {
        [$stagingTable, $stagingPrimaryKey, $originalPrimaryKey] = $tableMappings[$table];
        foreach ($tableColumns as $column) {
            $fields["{$table}.{$column}"] = [
                'staging_table' => $stagingTable,
                'staging_primary_key' => $stagingPrimaryKey,
                'original_table' => $table,
                'original_primary_key' => $originalPrimaryKey,
                'column' => $column
            ];
        }
    }

    return $fields;
}

function submissionApprovalCollectionMap(): array
{
    return [
        'employee_referrals.all' => [
            'staging_table' => 'temp_employee_referrals',
            'original_table' => 'employee_referrals',
            'primary_key' => 'referral_id',
            'columns' => [
                'employee_id', 'referral_source', 'referred_by', 'family_members_working',
                'working_name', 'working_unit', 'working_designation', 'family_members_studying',
                'student_name', 'student_unit', 'student_class', 'mobile_no'
            ]
        ],
        'educational_qualifications.all' => [
            'staging_table' => 'temp_educational_qualifications',
            'original_table' => 'educational_qualifications',
            'primary_key' => 'id',
            'columns' => [
                'employee_id', 'qualification_type', 'qualification_details', 'institution',
                'completion_year', 'certificate_path', 'created_at', 'created_by',
                'updated_by', 'updated_at', 'mobile_no'
            ]
        ],
        'employee_previous_experience.all' => [
            'staging_table' => 'temp_employee_previous_experience',
            'original_table' => 'employee_previous_experience',
            'primary_key' => 'id',
            'columns' => [
                'employee_id', 'company_name', 'role', 'start_date', 'end_date',
                'experience_details', 'pay_slip', 'experience_letter_path',
                'created_at', 'created_by', 'mobile_no'
            ]
        ]
    ];
}

function replaceApprovedSubmissionCollection(
    PDO $pdo,
    int $employeeId,
    int $submissionId,
    int $reviewerId,
    array $mapping
): void {
    $select = $pdo->prepare(sprintf(
        'SELECT * FROM `%s` WHERE employee_id = ? AND submission_id = ? ORDER BY `%s`',
        $mapping['staging_table'],
        $mapping['primary_key']
    ));
    $select->execute([$employeeId, $submissionId]);
    $rows = $select->fetchAll(PDO::FETCH_ASSOC);

    $delete = $pdo->prepare(sprintf(
        'DELETE FROM `%s` WHERE employee_id = ?',
        $mapping['original_table']
    ));
    $delete->execute([$employeeId]);

    foreach ($rows as $row) {
        $row['employee_id'] = $employeeId;
        if (in_array('created_by', $mapping['columns'], true)) {
            $row['created_by'] = $reviewerId;
            $row['created_at'] = date('Y-m-d H:i:s');
        }
        if (in_array('updated_by', $mapping['columns'], true)) {
            $row['updated_by'] = $reviewerId;
            $row['updated_at'] = date('Y-m-d H:i:s');
        }
        $values = [];
        foreach ($mapping['columns'] as $column) {
            $values[] = $row[$column] ?? null;
        }
        $quotedColumns = array_map(static fn(string $column): string => "`{$column}`", $mapping['columns']);
        $placeholders = implode(', ', array_fill(0, count($mapping['columns']), '?'));
        $insert = $pdo->prepare(sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $mapping['original_table'],
            implode(', ', $quotedColumns),
            $placeholders
        ));
        $insert->execute($values);
    }
}
