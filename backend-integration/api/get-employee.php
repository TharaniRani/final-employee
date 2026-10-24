<?php
/**
 * Get Employee Data Endpoint
 * Returns employee data for pre-filling the form
 */
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/hrms-repository.php';

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

    $employee = getHrmsEmployeeById($pdo, (int) $employeeId);

    if (!$employee) {
        unset($_SESSION['verified_employee_id'], $_SESSION['verified_mobile']);
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Employee not found']);
        exit;
    }
    $employee['mobile'] = $_SESSION['verified_mobile'] ?? $employee['mobile'];

    $documents = getHrmsEmployeeDocuments($pdo, (int) $employeeId);
    $existingDocuments = getHrmsExistingIdentityDocuments($pdo, (int) $employeeId);
    $legacyDocuments = getHrmsLegacyDocumentNames($pdo, (int) $employeeId);
    $employee['legacy_pg_degree_status'] = $employee['pg_degree_status'] ?? '';
    $employee['legacy_pg_degree_institution'] = $employee['pg_degree_institution'] ?? '';
    unset($employee['pg_degree_status'], $employee['pg_degree_institution']);

    echo json_encode([
        'success' => true,
        'employee' => $employee,
        'documents' => $documents,
        'existing_documents' => $existingDocuments,
        'legacy_documents' => $legacyDocuments
    ]);

} catch (PDOException $e) {
    error_log("Database error in get-employee: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
