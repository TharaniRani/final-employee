<?php
/**
 * Get Draft Endpoint
 * Retrieves saved draft for employee
 */
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config/database.php';

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

    $stmt = $pdo->prepare("
        SELECT form_data, current_step, updated_at
        FROM employee_form_submissions
        WHERE employee_id = ? AND status = 'draft'
        ORDER BY updated_at DESC
        LIMIT 1
    ");
    $stmt->execute([$employeeId]);
    $draft = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$draft) {
        echo json_encode(['success' => true, 'draft' => null]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'draft' => [
            'form_data' => json_decode($draft['form_data'], true),
            'current_step' => $draft['current_step'],
            'updated_at' => $draft['updated_at']
        ]
    ]);

} catch (PDOException $e) {
    error_log("Database error in get-draft: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
