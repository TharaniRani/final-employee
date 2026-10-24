<?php
/**
 * Looks up current employment details for a family member by MSS employee ID.
 */
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/secure-session.php';
require_once __DIR__ . '/../config/hrms-repository.php';
startSecureSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!$csrfToken || !hash_equals($_SESSION['csrf_token'] ?? '', $csrfToken)) {
    http_response_code(419);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

if (empty($_SESSION['verified_employee_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Employee authentication is required']);
    exit;
}

$request = json_decode(file_get_contents('php://input'), true);
$mssEmployeeId = is_array($request) && is_string($request['mss_employee_id'] ?? null)
    ? trim($request['mss_employee_id'])
    : '';
if (!preg_match('/^[A-Za-z0-9-]{1,20}$/', $mssEmployeeId)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Enter a valid MSS Employee ID']);
    exit;
}

try {
    $employee = findHrmsFamilyMemberEmploymentByMssId(getDatabaseConnection(), $mssEmployeeId);
    if ($employee === null) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'No current employee was found for that MSS Employee ID']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'employee' => [
            'full_name' => trim((string) ($employee['full_name'] ?? '')),
            'unit' => trim((string) ($employee['unit'] ?? '')),
            'designation' => trim((string) ($employee['designation'] ?? ''))
        ]
    ]);
} catch (PDOException $e) {
    error_log('Database error in family member MSS ID lookup: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to look up this MSS Employee ID']);
} catch (RuntimeException $e) {
    error_log('Family member MSS ID lookup failed: ' . $e->getMessage());
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'This MSS Employee ID matches multiple employee records']);
}
