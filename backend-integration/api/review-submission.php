<?php
/**
 * Admin/HR queue and decision endpoint for employee form submissions.
 */
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/secure-session.php';
require_once __DIR__ . '/../config/submission-staging.php';
require_once __DIR__ . '/../config/document-storage.php';
require_once __DIR__ . '/../config/admin-auth.php';
startSecureSession();

$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!$csrfToken || !hash_equals($_SESSION['csrf_token'] ?? '', $csrfToken)) {
    http_response_code(419);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$admin = currentAdminUser();
if ($admin === null) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin authorization is required']);
    exit;
}
$reviewerId = $admin['id'];
$reviewerName = $admin['name'];
$actorType = $admin['role'] === 'school_admin' ? 'school_admin' : 'hr';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $pdo = getDatabaseConnection();
        $statusFilter = trim((string) ($_GET['status'] ?? 'pending'));
        if (!in_array($statusFilter, ['all', 'pending', 'approved'], true)) {
            throw new DomainException('The requested status filter is invalid.');
        }
        $unitFilter = trim((string) ($_GET['unit'] ?? ''));
        $scopeUnitNames = [];
        if (!adminCanAccessAllUnits($admin)) {
            $scopeUnitNames = adminUnitEmploymentNames($admin);
            if (!$scopeUnitNames) {
                throw new DomainException('This School Admin account has no employee-unit access mapping.');
            }
            if ($unitFilter !== '' &&
                $unitFilter !== $admin['unit_name'] &&
                !in_array($unitFilter, $scopeUnitNames, true)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'You are not authorized to view this unit.']);
                exit;
            }
            if ($unitFilter === $admin['unit_name']) {
                $unitFilter = '';
            }
        }
        $search = trim((string) ($_GET['search'] ?? ''));
        $submissionIdFilter = filter_var($_GET['submission_id'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1]
        ]);
        $page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 50000]
        ]);
        if ($page === false || ($submissionIdFilter === false && isset($_GET['submission_id'])) ||
            strlen($search) > 120 || strlen($unitFilter) > 255) {
            throw new DomainException('The search, unit, or page filter is invalid.');
        }

        $statusCounts = ['pending' => 0, 'approved' => 0];
        if (adminCanAccessAllUnits($admin)) {
            $counts = $pdo->query("
                SELECT status, COUNT(*) AS total
                FROM employee_form_submissions
                WHERE status IN ('pending', 'approved')
                GROUP BY status
            ");
        } else {
            $scopeUnitPlaceholders = implode(', ', array_fill(0, count($scopeUnitNames), '?'));
            $counts = $pdo->prepare("
                SELECT s.status, COUNT(*) AS total
                FROM employee_form_submissions s
                LEFT JOIN employee_employment em
                    ON em.employment_id = (
                        SELECT em2.employment_id
                        FROM employee_employment em2
                        WHERE em2.employee_id = s.employee_id
                        ORDER BY em2.employment_id DESC
                        LIMIT 1
                    )
                WHERE s.status IN ('pending', 'approved')
                  AND TRIM(COALESCE(em.unit, '')) IN ({$scopeUnitPlaceholders})
                GROUP BY s.status
            ");
            $counts->execute($scopeUnitNames);
        }
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
        if (adminCanAccessAllUnits($admin)) {
            $unitsStmt = $pdo->query("
                SELECT DISTINCT TRIM(unit) AS unit
                FROM employee_employment
                WHERE unit IS NOT NULL AND TRIM(unit) <> ''
                ORDER BY unit
            ");
            $units = array_values($unitsStmt->fetchAll(PDO::FETCH_COLUMN));
        } else {
            $units = $scopeUnitNames;
        }

        $where = ["s.status IN ('pending', 'approved')"];
        $parameters = [];
        if (!adminCanAccessAllUnits($admin)) {
            $scopeUnitPlaceholders = implode(', ', array_fill(0, count($scopeUnitNames), '?'));
            $where[] = "TRIM(COALESCE(em.unit, '')) IN ({$scopeUnitPlaceholders})";
            array_push($parameters, ...$scopeUnitNames);
        }
        if ($statusFilter !== 'all') {
            $where[] = 's.status = ?';
            $parameters[] = $statusFilter;
        }
        if ($unitFilter !== '') {
            $where[] = 'TRIM(em.unit) = ?';
            $parameters[] = $unitFilter;
        }
        if ($submissionIdFilter !== null && $submissionIdFilter !== false) {
            $where[] = 's.id = ?';
            $parameters[] = $submissionIdFilter;
        }
        if ($search !== '') {
            $where[] = "CONCAT_WS(' ', s.id, s.reference_number, s.employee_id, e.full_name, e.mss_employee_id, s.mobile, contact.personal_email) LIKE ?";
            $parameters[] = '%' . $search . '%';
        }

        $latestContactJoin = "
            LEFT JOIN employee_contact contact
                ON contact.contact_id = (
                    SELECT c2.contact_id
                    FROM employee_contact c2
                    WHERE c2.employee_id = s.employee_id
                    ORDER BY c2.contact_id DESC
                    LIMIT 1
                )
        ";
        $whereSql = implode(' AND ', $where);
        $totalStmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM employee_form_submissions s
            LEFT JOIN employees e ON e.employee_id = s.employee_id
            {$latestEmploymentJoin}
            {$latestContactJoin}
            WHERE {$whereSql}
        ");
        $totalStmt->execute($parameters);
        $totalResults = (int) $totalStmt->fetchColumn();
        $pageSize = 10;
        $offset = ($page - 1) * $pageSize;
        $stmt = $pdo->prepare("
            SELECT s.id, s.employee_id, s.mobile, s.status, s.form_data, s.approved_fields,
                   s.reference_number, s.submitted_at, s.updated_at,
                   e.full_name, e.mss_employee_id, em.unit, em.designation,
                   contact.personal_email
            FROM employee_form_submissions s
            LEFT JOIN employees e ON e.employee_id = s.employee_id
            {$latestEmploymentJoin}
            {$latestContactJoin}
            WHERE {$whereSql}
            ORDER BY CASE s.status WHEN 'pending' THEN 0 WHEN 'approved' THEN 1 ELSE 2 END,
                     s.submitted_at DESC, s.id DESC
            LIMIT {$pageSize} OFFSET {$offset}
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
            $changedFieldDetails = submissionChangedFieldDetails(
                $pdo,
                (int) $row['employee_id'],
                (int) $row['id'],
                $changedFields,
                $row['status'],
                is_array($originalSnapshots) ? $originalSnapshots : [],
                is_array($fieldDecisions) ? $fieldDecisions : []
            );
            if ($row['status'] === 'approved') {
                $changedFieldDetails = array_values(array_filter(
                    $changedFieldDetails,
                    static function (array $detail): bool {
                        if (empty($detail['original_available'])) {
                            return true;
                        }
                        if (is_array($detail['original']) || is_array($detail['requested'])) {
                            return json_encode($detail['original']) !== json_encode($detail['requested']);
                        }
                        return !submissionValuesEqual($detail['original'], $detail['requested']);
                    }
                ));
                $changedFields = array_column($changedFieldDetails, 'field');
            }
            $contactEmail = trim((string) ($row['personal_email'] ?? ''));
            $submittedEmail = $formData['personal_basic']['email'] ?? '';
            if (is_array($submittedEmail)) {
                $submittedEmail = $submittedEmail['value'] ??
                    $submittedEmail['corrected_value'] ??
                    $submittedEmail['original_value'] ??
                    '';
            }
            $submittedEmail = is_scalar($submittedEmail) ? trim((string) $submittedEmail) : '';
            $submissions[] = [
                'id' => (int) $row['id'],
                'employee_id' => (int) $row['employee_id'],
                'employee_name' => $row['full_name'] ?? '',
                'employee_number' => $row['mss_employee_id'] ?? '',
                'unit' => $row['unit'] ?? '',
                'mobile' => $row['mobile'],
                'email' => $contactEmail !== '' ? $contactEmail : $submittedEmail,
                'designation' => $row['designation'] ?? '',
                'status' => $row['status'],
                'reference_number' => $row['reference_number'],
                'submitted_at' => $row['submitted_at'],
                'declaration' => !empty($formData['submission']['declaration']),
                'changed_fields' => $changedFields,
                'changed_field_details' => $changedFieldDetails
            ];
        }
        echo json_encode([
            'success' => true,
            'submissions' => $submissions,
            'status_counts' => $statusCounts,
            'total_count' => array_sum($statusCounts),
            'units' => $units,
            'pagination' => [
                'page' => $page,
                'page_size' => $pageSize,
                'total_results' => $totalResults,
                'total_pages' => max(1, (int) ceil($totalResults / $pageSize))
            ]
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

if ($submissionId === false || !in_array($decision, ['approve', 'delete'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'A valid submission ID and review action are required']);
    exit;
}
try {
    $pdo = getDatabaseConnection();
    if ($decision === 'delete') {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("
            SELECT id, employee_id, COALESCE(reference_number, CONCAT('SUBMISSION-', id)) AS reference_number, status
            FROM employee_form_submissions
            WHERE id = ?
            FOR UPDATE
        ");
        $stmt->execute([$submissionId]);
        $submission = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$submission) {
            throw new DomainException('Submission not found.');
        }
        if (!submissionIsInAdminScope($pdo, (int) $submissionId, $admin)) {
            throw new DomainException('You are not authorized to delete this employee submission.');
        }
        if ($submission['status'] !== 'pending') {
            throw new DomainException('Only pending submissions can be deleted.');
        }

        $stmt = $pdo->prepare("
            INSERT INTO form_submission_logs
                (submission_id, employee_id, reference_number, action, actor_type, actor_id, actor_name, ip_address, user_agent, created_at)
            VALUES (?, ?, ?, 'delete', ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $submissionId,
            (int) $submission['employee_id'],
            $submission['reference_number'],
            $actorType,
            (string) $reviewerId,
            $reviewerName,
            substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
            substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512)
        ]);

        $stmt = $pdo->prepare('UPDATE admin_employee_change_history SET submission_id = NULL WHERE submission_id = ?');
        $stmt->execute([$submissionId]);

        foreach ([
            'temp_educational_qualifications',
            'temp_employee_contact',
            'temp_employee_employment',
            'temp_employee_identification',
            'temp_employee_previous_experience',
            'temp_employee_referrals'
        ] as $table) {
            $stmt = $pdo->prepare("DELETE FROM {$table} WHERE employee_id = ? AND submission_id = ?");
            $stmt->execute([(int) $submission['employee_id'], $submissionId]);
        }
        $stmt = $pdo->prepare('DELETE FROM temp_employees WHERE employee_id = ? AND submission_id = ?');
        $stmt->execute([(int) $submission['employee_id'], $submissionId]);

        $stmt = $pdo->prepare("DELETE FROM employee_form_submissions WHERE id = ? AND status = 'pending'");
        $stmt->execute([$submissionId]);
        if ($stmt->rowCount() !== 1) {
            throw new DomainException('This submission is no longer pending and could not be deleted.');
        }

        $pdo->commit();
        echo json_encode([
            'success' => true,
            'message' => 'Employee submission deleted successfully.'
        ]);
        exit;
    }

    $promotedDocuments = [];
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
    if (!submissionIsInAdminScope($pdo, (int) $submissionId, $admin)) {
        throw new DomainException('You are not authorized to review this employee submission.');
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
    $employeeId = (int) $submission['employee_id'];
    $approvedBefore = json_decode((string) ($submission['approved_fields'] ?? '[]'), true, 512, JSON_THROW_ON_ERROR);
    $approvedBefore = is_array($approvedBefore)
        ? array_values(array_intersect($approvedBefore, $allowedFields))
        : [];
    $changedFields = array_values(array_unique(array_merge(
        $changedFields,
        $approvedBefore
    )));
    if (!is_array($formData['submission']['original_values'] ?? null)) {
        $formData['submission']['original_values'] = [];
    }
    $fieldsToApply = array_values(array_diff($changedFields, $approvedBefore));
    $missingSnapshots = array_values(array_diff(
        $fieldsToApply,
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
    $allApprovedFields = $changedFields;
    $approvedJson = json_encode($allApprovedFields, JSON_THROW_ON_ERROR);
    applyApprovedSubmissionFields(
        $pdo,
        $employeeId,
        (int) $submissionId,
        $fieldsToApply,
        (int) $reviewerId,
        $promotedDocuments
    );
    promoteSubmittedOtherDocuments(
        $pdo,
        $employeeId,
        $formData,
        $promotedDocuments
    );
    replacePromotedDocumentPaths($formData, $promotedDocuments);
    $formDataJson = json_encode($formData, JSON_THROW_ON_ERROR);
    $stmt = $pdo->prepare("
        UPDATE employee_form_submissions
        SET status = ?, reviewed_by = ?, reviewer_name = ?, reviewed_at = NOW(),
            approved_at = COALESCE(approved_at, NOW()),
            approved_fields = ?, form_data = ?, review_notes = ?,
            updated_at = NOW()
        WHERE id = ? AND status = 'pending'
    ");
    $stmt->execute([
        'approved',
        (string) $reviewerId,
        $reviewerName,
        $approvedJson,
        $formDataJson,
        $reviewNotes === '' ? null : $reviewNotes,
        $submissionId
    ]);

    $stmt = $pdo->prepare("
        INSERT INTO form_submission_logs
            (submission_id, employee_id, reference_number, action, actor_type, actor_id, actor_name, notes, ip_address, user_agent, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $submissionId,
        $employeeId,
        $submission['reference_number'],
        $decision,
        $actorType,
        (string) $reviewerId,
        $reviewerName,
        $reviewNotes === '' ? null : $reviewNotes,
        substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
        substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512)
    ]);

    $pdo->commit();
    foreach ($promotedDocuments as $document) {
        $temporaryAbsolutePath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR .
            str_replace('/', DIRECTORY_SEPARATOR, $document['temporary_path']);
        if (is_file($temporaryAbsolutePath) && !unlink($temporaryAbsolutePath)) {
            error_log('Approved document remains in temporary storage: ' . $document['temporary_path']);
        }
    }
    echo json_encode([
        'success' => true,
        'status' => 'approved',
        'approved_fields' => $allApprovedFields,
        'message' => 'Employee submission approved and changes saved.'
    ]);
} catch (DomainException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    removeUncommittedPromotedDocuments($promotedDocuments ?? []);
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    removeUncommittedPromotedDocuments($promotedDocuments ?? []);
    error_log('HR submission review failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Review could not be completed']);
}

function applyApprovedSubmissionFields(
    PDO $pdo,
    int $employeeId,
    int $submissionId,
    array $approvedFields,
    int $reviewerId,
    array &$promotedDocuments = []
): void
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
                $collectionMap[$field],
                $promotedDocuments
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
        if (is_string($value)) {
            $promotedValue = promoteApprovedDocumentValue(
                $pdo,
                $employeeId,
                $mapping['column'],
                $value,
                $promotedDocuments
            );
            if ($promotedValue !== $value) {
                $updateStaged = $pdo->prepare(sprintf(
                    'UPDATE `%s` SET `%s` = ? WHERE employee_id = ? AND submission_id = ?',
                    $mapping['staging_table'],
                    $mapping['column']
                ));
                $updateStaged->execute([$promotedValue, $employeeId, $submissionId]);
            }
            $value = $promotedValue;
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
            if ($status === 'approved') {
                $requestedStmt = $pdo->prepare(sprintf(
                    'SELECT `%s` FROM `%s` WHERE employee_id = ? ORDER BY `%s` DESC LIMIT 1',
                    $mapping['column'],
                    $mapping['original_table'],
                    $mapping['original_primary_key']
                ));
                $requestedStmt->execute([$employeeId]);
                $requested = $requestedStmt->fetchColumn();
            } else {
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
                $status === 'approved' ? $mapping['original_table'] : $mapping['staging_table'],
                $mapping['primary_key'],
                $columns,
                $employeeId,
                $status === 'approved' ? null : $submissionId
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
            'photo', 'resume', 'staff_sign', 'driving', 'pg_degree_status',
            'pg_degree_institution', 'pg_degree_doc'
        ],
        'employee_employment' => [
            'unit', 'branch', 'designation', 'employee_type', 'job_type', 'reporting_to',
            'date_of_joining'
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
                'employee_id', 'referred_by', 'family_members_working',
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
    array $mapping,
    array &$promotedDocuments = []
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
        foreach (['certificate_path', 'pay_slip', 'experience_letter_path'] as $documentColumn) {
            if (isset($row[$documentColumn]) && is_string($row[$documentColumn])) {
                $promotedValue = promoteApprovedDocumentValue(
                    $pdo,
                    $employeeId,
                    $documentColumn,
                    $row[$documentColumn],
                    $promotedDocuments
                );
                if ($promotedValue !== $row[$documentColumn]) {
                    $updateStaged = $pdo->prepare(sprintf(
                        'UPDATE `%s` SET `%s` = ? WHERE employee_id = ? AND submission_id = ? AND `%s` = ?',
                        $mapping['staging_table'],
                        $documentColumn,
                        $mapping['primary_key']
                    ));
                    $updateStaged->execute([
                        $promotedValue,
                        $employeeId,
                        $submissionId,
                        $row[$mapping['primary_key']]
                    ]);
                }
                $row[$documentColumn] = $promotedValue;
            }
        }
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

function promoteApprovedDocumentValue(
    PDO $pdo,
    int $employeeId,
    string $column,
    string $value,
    array &$promotedDocuments
): string {
    $types = [
        'aadhar_copy' => 'aadhaar',
        'pan_copy' => 'pan',
        'photo' => 'photo',
        'resume' => 'resume',
        'staff_sign' => 'signature',
        'pg_degree_doc' => 'bed',
        'driving' => 'driving',
        'certificate_path' => 'education_certificate',
        'pay_slip' => 'experience_payslip',
        'experience_letter_path' => 'experience_letter'
    ];
    $normalizedPath = str_replace('\\', '/', $value);
    if (!isset($types[$column])) {
        if (str_starts_with($normalizedPath, 'employees_temp_documents/')) {
            throw new DomainException('A staged document is assigned to an unsupported employee field.');
        }
        return $value;
    }

    if (!str_starts_with($normalizedPath, 'employees_temp_documents/')) {
        if (basename($normalizedPath) !== $normalizedPath) {
            return $value;
        }

        $candidatePath = submissionTemporaryDocumentRelativePath(
            $employeeId,
            $types[$column],
            $normalizedPath
        );
        if (submissionResolveStoredDocumentPath(
            dirname(__DIR__, 2),
            $employeeId,
            $types[$column],
            $candidatePath
        ) === null) {
            return $value;
        }
        $normalizedPath = $candidatePath;
    }

    if (isset($promotedDocuments[$normalizedPath])) {
        return $promotedDocuments[$normalizedPath]['path'];
    }

    $result = submissionPromoteDocumentPath(
        dirname(__DIR__, 2),
        $employeeId,
        $types[$column],
        $normalizedPath
    );
    if ($result['temporary_path'] !== null) {
        $promotedDocuments[$normalizedPath] = $result;
    }

    return $result['path'];
}

function promoteSubmittedOtherDocuments(
    PDO $pdo,
    int $employeeId,
    array &$formData,
    array &$promotedDocuments
): void {
    foreach ($formData['other_documents'] ?? [] as &$document) {
        if (!is_array($document) || !is_string($document['file_path'] ?? null)) {
            continue;
        }
        $temporaryPath = str_replace('\\', '/', $document['file_path']);
        if (!str_starts_with($temporaryPath, 'employees_temp_documents/')) {
            continue;
        }
        if (isset($promotedDocuments[$temporaryPath])) {
            $document['file_path'] = $promotedDocuments[$temporaryPath]['path'];
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
        $result = submissionPromoteDocumentPath(
            dirname(__DIR__, 2),
            $employeeId,
            $type,
            $temporaryPath
        );
        if ($result['temporary_path'] !== null) {
            $promotedDocuments[$temporaryPath] = $result;
        }
        $document['file_path'] = $result['path'];
    }
    unset($document);
}

function replacePromotedDocumentPaths(array &$value, array $promotedDocuments): void
{
    if (is_array($value)) {
        foreach ($value as &$item) {
            replacePromotedDocumentPaths($item, $promotedDocuments);
        }
        unset($item);
        return;
    }

    if (!is_string($value)) {
        return;
    }
    $normalizedPath = str_replace('\\', '/', $value);
    if (isset($promotedDocuments[$normalizedPath])) {
        $value = $promotedDocuments[$normalizedPath]['path'];
    }
}

function removeUncommittedPromotedDocuments(array $promotedDocuments): void
{
    $projectRoot = dirname(__DIR__, 2);
    foreach ($promotedDocuments as $document) {
        if (empty($document['permanent_path'])) {
            continue;
        }
        $permanentAbsolutePath = $projectRoot . DIRECTORY_SEPARATOR .
            str_replace('/', DIRECTORY_SEPARATOR, $document['permanent_path']);
        if (is_file($permanentAbsolutePath) && !unlink($permanentAbsolutePath)) {
            error_log('Unable to remove an uncommitted approved document: ' . $document['permanent_path']);
        }
    }
}
