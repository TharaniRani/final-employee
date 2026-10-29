<?php
/**
 * Get Employee Data Endpoint
 * Returns employee data for pre-filling the form
 */
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/hrms-repository.php';
require_once __DIR__ . '/../config/submission-access.php';

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

try {
    $pdo = getDatabaseConnection();
    $existingSubmission = getEmployeeSubmissionLockout($pdo, (int) $employeeId);
    if ($existingSubmission !== null) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => employeeSubmissionLockoutMessage($existingSubmission)
        ]);
        exit;
    }

    $employee = getHrmsEmployeeById($pdo, (int) $employeeId);

    if (!$employee) {
        unset($_SESSION['verified_employee_id'], $_SESSION['verified_mobile']);
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Employee not found']);
        exit;
    }
    $employee['mobile'] = (string) ($_SESSION['verified_mobile'] ?? '');

    $existingDocuments = getHrmsExistingIdentityDocuments($pdo, (int) $employeeId);
    $employee['legacy_pg_degree_status'] = $employee['pg_degree_status'] ?? '';
    $employee['legacy_pg_degree_institution'] = $employee['pg_degree_institution'] ?? '';
    unset($employee['pg_degree_status'], $employee['pg_degree_institution']);

    echo json_encode([
        'success' => true,
        'employee' => $employee,
        'documents' => [],
        'existing_documents' => $existingDocuments,
        'legacy_documents' => []
    ]);

} catch (PDOException $e) {
    error_log("Database error in get-employee: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
