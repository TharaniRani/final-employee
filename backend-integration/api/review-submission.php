<?php
/**
 * HR-only queue and decision endpoint for employee form submissions.
 */
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/secure-session.php';
require_once __DIR__ . '/../config/submission-staging.php';
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
        $statusFilter = trim((string) ($_GET['status'] ?? 'pending'));
        if (!in_array($statusFilter, ['all', 'pending', 'approved', 'rejected'], true)) {
            throw new DomainException('The requested status filter is invalid.');
        }
        $unitFilter = trim((string) ($_GET['unit'] ?? ''));
        $search = trim((string) ($_GET['search'] ?? ''));
        if (strlen($search) > 120 || strlen($unitFilter) > 255) {
            throw new DomainException('The search or unit filter is too long.');
        }

        $statusCounts = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
        $counts = $pdo->query("
            SELECT status, COUNT(*) AS total
            FROM employee_form_submissions
            WHERE status IN ('pending', 'approved', 'rejected')
            GROUP BY status
        ");
        foreach ($counts->fetchAll(PDO::FETCH_ASSOC) as $count) {
            $statusCounts[$count['status']] = (int) $count['total'];
        }

        $latestEmploymentJoin = "
            LEFT JOIN employee_employment em
                ON em.employment_id = (
                    SELECT MAX(em2.employment_id)
                    FROM employee_employment em2
                    WHERE em2.employee_id = s.employee_id
                )
        ";
        $unitsStmt = $pdo->query("
            SELECT DISTINCT NULLIF(TRIM(em.unit), '') AS unit
            FROM employee_form_submissions s
            {$latestEmploymentJoin}
            WHERE s.status IN ('pending', 'approved', 'rejected')
              AND NULLIF(TRIM(em.unit), '') IS NOT NULL
            ORDER BY unit
        ");
        $units = $unitsStmt->fetchAll(PDO::FETCH_COLUMN);

        $where = ["s.status IN ('pending', 'approved', 'rejected')"];
        $parameters = [];
        if ($statusFilter !== 'all') {
            $where[] = 's.status = ?';
            $parameters[] = $statusFilter;
        }
        if ($unitFilter !== '') {
            $where[] = 'em.unit = ?';
            $parameters[] = $unitFilter;
        }
        if ($search !== '') {
            $where[] = "CONCAT_WS(' ', s.id, s.reference_number, s.employee_id, e.full_name, e.mss_employee_id, s.mobile) LIKE ?";
            $parameters[] = '%' . $search . '%';
        }

        $stmt = $pdo->prepare("
            SELECT s.id, s.employee_id, s.mobile, s.status, s.form_data, s.approved_fields,
                   s.reference_number, s.submitted_at,
                   e.full_name, e.mss_employee_id, em.unit
            FROM employee_form_submissions s
            LEFT JOIN employees e ON e.employee_id = s.employee_id
            {$latestEmploymentJoin}
            WHERE " . implode(' AND ', $where) . "
            ORDER BY s.submitted_at DESC, s.id DESC
            LIMIT 100
        ");
        $stmt->execute($parameters);
        $submissions = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $formData = json_decode($row['form_data'], true, 512, JSON_THROW_ON_ERROR);
            $fieldDecisions = $formData['submission']['field_decisions'] ?? [];
            $fieldDecisions = is_array($fieldDecisions) ? $fieldDecisions : [];
            if (
                $row['status'] === 'approved' &&
                !is_array($formData['submission']['changed_fields'] ?? null) &&
                is_string($row['approved_fields'])
            ) {
                $previouslyApproved = json_decode($row['approved_fields'], true, 512, JSON_THROW_ON_ERROR);
                $allowedFields = array_merge(
                    array_keys(submissionApprovalFieldMap()),
                    array_keys(submissionApprovalCollectionMap())
                );
                $changedFields = is_array($previouslyApproved)
                    ? array_values(array_intersect($previouslyApproved, $allowedFields))
                    : [];
            } else {
                $changedFields = submissionChangedFields(
                    $formData,
                    $pdo,
                    (int) $row['employee_id'],
                    (int) $row['id']
                );
            }
            $allowedFields = array_merge(
                array_keys(submissionApprovalFieldMap()),
                array_keys(submissionApprovalCollectionMap())
            );
            $changedFields = array_values(array_unique(array_merge(
                $changedFields,
                array_intersect(array_keys($fieldDecisions), $allowedFields)
            )));
            $originalSnapshots = $formData['submission']['original_values'] ?? [];
            $submissions[] = [
                'id' => (int) $row['id'],
                'employee_id' => (int) $row['employee_id'],
                'employee_name' => $row['full_name'] ?? '',
                'employee_number' => $row['mss_employee_id'] ?? '',
                'unit' => $row['unit'] ?? '',
                'mobile' => $row['mobile'],
                'status' => $row['status'],
                'reference_number' => $row['reference_number'],
                'submitted_at' => $row['submitted_at'],
                'changed_fields' => $changedFields,
                'field_decisions' => $fieldDecisions,
                'changed_field_details' => submissionChangedFieldDetails(
                    $pdo,
                    (int) $row['employee_id'],
                    (int) $row['id'],
                    $changedFields,
                    $row['status'],
                    is_array($originalSnapshots) ? $originalSnapshots : [],
                    $fieldDecisions
                )
            ];
        }
        echo json_encode([
            'success' => true,
            'submissions' => $submissions,
            'status_counts' => $statusCounts,
            'units' => $units
        ]);
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
$rejectedFields = $input['rejected_fields'] ?? [];
$partialDecision = in_array($decision, ['approve_fields', 'reject_fields'], true);

if ($submissionId === false || !in_array($decision, ['approve', 'reject', 'approve_fields', 'reject_fields'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'A valid submission ID and decision are required']);
    exit;
}
if (in_array($decision, ['reject', 'reject_fields'], true) && $rejectionReason === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'A rejection reason is required']);
    exit;
}
if (($decision === 'approve' || $decision === 'approve_fields') && (!is_array($approvedFields) || !$approvedFields)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Select at least one field or section to approve']);
    exit;
}
if ($decision === 'reject_fields' && (!is_array($rejectedFields) || !$rejectedFields)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Select at least one field or section to reject']);
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
        SELECT id, employee_id, reference_number, status, form_data, approved_fields
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

    $formData = json_decode($submission['form_data'], true, 512, JSON_THROW_ON_ERROR);
    $fieldDecisions = $formData['submission']['field_decisions'] ?? [];
    if (!is_array($fieldDecisions)) {
        $fieldDecisions = [];
    }
    $changedFields = submissionChangedFields(
        $formData,
        $pdo,
        (int) $submission['employee_id'],
        (int) $submissionId
    );
    $allowedFields = array_merge(
        array_keys(submissionApprovalFieldMap()),
        array_keys(submissionApprovalCollectionMap())
    );
    $changedFields = array_values(array_unique(array_merge(
        $changedFields,
        array_intersect(array_keys($fieldDecisions), $allowedFields)
    )));
    if ($decision === 'approve' && $fieldDecisions) {
        throw new DomainException('This request already has field-by-field decisions. Continue reviewing only the remaining fields.');
    }
    $requestedFields = $decision === 'reject_fields' ? $rejectedFields : $approvedFields;
    if ($decision === 'approve' || $partialDecision) {
        foreach ($requestedFields as $field) {
            if (!is_string($field) || !in_array($field, $changedFields, true)) {
                throw new DomainException('Only changed fields in this submission can be reviewed.');
            }
            if ($partialDecision && isset($fieldDecisions[$field])) {
                throw new DomainException('One or more selected fields have already been reviewed.');
            }
        }
    }

    $employeeId = (int) $submission['employee_id'];
    if ($partialDecision) {
        if (!is_array($formData['submission']['original_values'] ?? null)) {
            $formData['submission']['original_values'] = [];
        }
        $missingSnapshots = array_values(array_diff(
            $changedFields,
            array_keys($formData['submission']['original_values'])
        ));
        if ($missingSnapshots) {
            $formData['submission']['original_values'] += submissionOriginalValueSnapshots(
                $pdo,
                $employeeId,
                $missingSnapshots
            );
        }
        $formData['submission']['changed_fields'] = $changedFields;
    }
    if ($decision === 'approve' || $decision === 'approve_fields') {
        applyApprovedSubmissionFields($pdo, $employeeId, (int) $submissionId, $approvedFields, (int) $reviewerId);
    }

    if ($partialDecision) {
        foreach ($requestedFields as $field) {
            $fieldDecisions[$field] = [
                'decision' => $decision === 'approve_fields' ? 'approved' : 'rejected',
                'reason' => $decision === 'reject_fields' ? $rejectionReason : null,
                'reviewer_id' => (int) $reviewerId,
                'reviewer_name' => $reviewerName,
                'reviewed_at' => date(DATE_ATOM)
            ];
        }
        $formData['submission']['field_decisions'] = $fieldDecisions;
        $approvedBefore = json_decode((string) ($submission['approved_fields'] ?? '[]'), true, 512, JSON_THROW_ON_ERROR);
        $approvedBefore = is_array($approvedBefore) ? $approvedBefore : [];
        $allApprovedFields = $decision === 'approve_fields'
            ? array_values(array_unique(array_merge($approvedBefore, $approvedFields)))
            : array_values(array_unique($approvedBefore));
        $resolvedCount = count(array_intersect($changedFields, array_keys($fieldDecisions)));
        $allResolved = $resolvedCount === count($changedFields);
        $status = !$allResolved
            ? 'pending'
            : ($allApprovedFields ? 'approved' : 'rejected');
        $approvedJson = json_encode($allApprovedFields, JSON_THROW_ON_ERROR);
        $formDataJson = json_encode($formData, JSON_THROW_ON_ERROR);
    } else {
        $approvedBefore = json_decode((string) ($submission['approved_fields'] ?? '[]'), true, 512, JSON_THROW_ON_ERROR);
        if ($decision === 'reject' && is_array($approvedBefore) && $approvedBefore) {
            throw new DomainException('Some fields have already been approved and applied. Reject the remaining fields individually.');
        }
        $status = $decision === 'approve' ? 'approved' : 'rejected';
        $allApprovedFields = $decision === 'approve' ? array_values(array_unique($approvedFields)) : [];
        $approvedJson = json_encode($allApprovedFields, JSON_THROW_ON_ERROR);
        if ($decision === 'reject') {
            foreach ($changedFields as $field) {
                if (!isset($fieldDecisions[$field])) {
                    $fieldDecisions[$field] = [
                        'decision' => 'rejected',
                        'reason' => $rejectionReason,
                        'reviewer_id' => (int) $reviewerId,
                        'reviewer_name' => $reviewerName,
                        'reviewed_at' => date(DATE_ATOM)
                    ];
                }
            }
            $formData['submission']['field_decisions'] = $fieldDecisions;
            $formDataJson = json_encode($formData, JSON_THROW_ON_ERROR);
        } else {
            $formDataJson = $submission['form_data'];
        }
    }
    $stmt = $pdo->prepare("
        UPDATE employee_form_submissions
        SET status = ?, reviewed_by = ?, reviewer_name = ?, reviewed_at = NOW(),
            approved_at = CASE WHEN ? = 'approved' THEN COALESCE(approved_at, NOW()) ELSE approved_at END,
            approved_fields = ?, form_data = ?, review_notes = ?,
            rejection_reason = CASE WHEN ? = 'rejected' AND ? = 'reject' THEN ? ELSE rejection_reason END,
            updated_at = NOW()
        WHERE id = ? AND status = 'pending'
    ");
    $stmt->execute([
        $status,
        (string) $reviewerId,
        $reviewerName,
        $status,
        $approvedJson,
        $formDataJson,
        $reviewNotes === '' ? null : $reviewNotes,
        $status,
        $decision,
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
        $partialDecision
            ? 'Fields: ' . implode(', ', $requestedFields) . (
                $decision === 'reject_fields'
                    ? '. Reason: ' . $rejectionReason
                    : ($reviewNotes === '' ? '' : '. ' . $reviewNotes)
            )
            : ($decision === 'reject' ? $rejectionReason : ($reviewNotes === '' ? null : $reviewNotes)),
        substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
        substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512)
    ]);

    $pdo->commit();
    echo json_encode([
        'success' => true,
        'status' => $status,
        'field_decisions' => $fieldDecisions,
        'approved_fields' => $allApprovedFields,
        'message' => $partialDecision
            ? ($decision === 'approve_fields'
                ? 'Selected changes were approved.'
                : 'Selected changes were rejected; original employee records were not changed.')
            : ($decision === 'approve'
                ? 'Approved fields were applied to the employee record.'
                : 'Submission rejected. Original employee records were not changed.')
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

function submissionChangedFields(
    array $formData,
    ?PDO $pdo = null,
    ?int $employeeId = null,
    ?int $submissionId = null
): array
{
    $changedFields = $formData['submission']['changed_fields'] ?? null;
    if (!is_array($changedFields)) {
        if ($pdo instanceof PDO && $employeeId !== null && $submissionId !== null) {
            return stageSubmissionRows($pdo, $employeeId, $submissionId, $formData, false);
        }
        return [];
    }

    $allowedFields = array_merge(
        array_keys(submissionApprovalFieldMap()),
        array_keys(submissionApprovalCollectionMap())
    );

    return array_values(array_unique(array_filter(
        $changedFields,
        static fn($field): bool => is_string($field) && in_array($field, $allowedFields, true)
    )));
}

function submissionChangedFieldDetails(
    PDO $pdo,
    int $employeeId,
    int $submissionId,
    array $changedFields,
    string $status,
    array $originalSnapshots,
    array $fieldDecisions = []
): array
{
    $fieldMap = submissionApprovalFieldMap();
    $collectionMap = submissionApprovalCollectionMap();
    $changes = [];

    foreach ($changedFields as $field) {
        if (isset($fieldMap[$field])) {
            $mapping = $fieldMap[$field];
            $staged = $pdo->prepare(sprintf(
                'SELECT `%s` FROM `%s` WHERE employee_id = ? AND submission_id = ? ORDER BY `%s` DESC LIMIT 1',
                $mapping['column'],
                $mapping['staging_table'],
                $mapping['staging_primary_key']
            ));
            $staged->execute([$employeeId, $submissionId]);
            $requested = $staged->fetchColumn();
            if ($requested === false) {
                throw new RuntimeException("Staged value for {$field} was not found.");
            }

            $originalValue = $originalSnapshots[$field] ?? null;
            $originalAvailable = array_key_exists($field, $originalSnapshots);
            if (!$originalAvailable && $status !== 'approved') {
                $original = $pdo->prepare(sprintf(
                    'SELECT `%s` FROM `%s` WHERE employee_id = ? ORDER BY `%s` DESC LIMIT 1',
                    $mapping['column'],
                    $mapping['original_table'],
                    $mapping['original_primary_key']
                ));
                $original->execute([$employeeId]);
                $originalValue = $original->fetchColumn();
                $originalAvailable = $originalValue !== false;
            }
            $changes[] = [
                'field' => $field,
                'original' => $originalValue === false ? null : $originalValue,
                'original_available' => $originalAvailable,
                'requested' => $requested,
                'decision' => $fieldDecisions[$field]['decision'] ?? null,
                'decision_reason' => $fieldDecisions[$field]['reason'] ?? null
            ];
            continue;
        }

        if (isset($collectionMap[$field])) {
            $mapping = $collectionMap[$field];
            $columns = array_values(array_diff(
                $mapping['columns'],
                ['employee_id', 'created_at', 'created_by', 'updated_by', 'updated_at']
            ));
            $originalAvailable = array_key_exists($field, $originalSnapshots);
            $original = $originalAvailable
                ? $originalSnapshots[$field]
                : ($status === 'approved' ? [] : submissionCollectionValues(
                    $pdo,
                    $mapping['original_table'],
                    $mapping['primary_key'],
                    $columns,
                    $employeeId
                ));
            $requested = submissionCollectionValues(
                $pdo,
                $mapping['staging_table'],
                $mapping['primary_key'],
                $columns,
                $employeeId,
                $submissionId
            );
            $changes[] = [
                'field' => $field,
                'original' => $original,
                'original_available' => $originalAvailable || $status !== 'approved',
                'requested' => $requested,
                'decision' => $fieldDecisions[$field]['decision'] ?? null,
                'decision_reason' => $fieldDecisions[$field]['reason'] ?? null
            ];
        }
    }

    return $changes;
}

function submissionCollectionValues(
    PDO $pdo,
    string $table,
    string $primaryKey,
    array $columns,
    int $employeeId,
    ?int $submissionId = null
): array {
    $quotedColumns = array_map(static fn(string $column): string => "`{$column}`", $columns);
    $sql = sprintf(
        'SELECT %s FROM `%s` WHERE employee_id = ?%s ORDER BY `%s`',
        implode(', ', $quotedColumns),
        $table,
        $submissionId === null ? '' : ' AND submission_id = ?',
        $primaryKey
    );
    $stmt = $pdo->prepare($sql);
    $parameters = [$employeeId];
    if ($submissionId !== null) {
        $parameters[] = $submissionId;
    }
    $stmt->execute($parameters);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
