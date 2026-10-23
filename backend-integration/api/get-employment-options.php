<?php
/**
 * Returns values already used for employment fields in the HRMS.
 */
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config/database.php';

session_start();

$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!$csrfToken || $csrfToken !== ($_SESSION['csrf_token'] ?? '')) {
    http_response_code(419);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

if (empty($_SESSION['verified_employee_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

try {
    $pdo = getDatabaseConnection();
    $fields = [
        'campus' => 'branch',
        'employee_type' => 'employee_type',
        'job_type' => 'job_type',
        'reporting_to' => 'reporting_to'
    ];
    $options = [];

    foreach ($fields as $key => $column) {
        $stmt = $pdo->query("
            SELECT DISTINCT TRIM($column) AS value
            FROM employee_employment
            WHERE $column IS NOT NULL
              AND TRIM($column) != ''
              AND TRIM($column) != '-'
              AND LOWER(TRIM($column)) != 'no'
            ORDER BY value
        ");
        $options[$key] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    echo json_encode(['success' => true, 'options' => $options]);
} catch (PDOException $e) {
    error_log('Database error in get-employment-options: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to load employment options']);
}
