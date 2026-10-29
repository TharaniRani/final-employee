<?php
/**
 * Save Draft Endpoint
 * Saves form data as draft
 */
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config/database.php';
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

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$formData = $input['form_data'] ?? [];
$currentStep = $input['current_step'] ?? '';

if (empty($formData)) {
    echo json_encode(['success' => false, 'message' => 'No form data provided']);
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

    $mobile = $formData['personal_basic']['mobile_number']['original_value'] ?? $_SESSION['verified_mobile'] ?? '';
    $encodedFormData = json_encode($formData, JSON_THROW_ON_ERROR);

    $pdo->beginTransaction();

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
            SET mobile = ?, form_data = ?, current_step = ?, updated_at = NOW()
            WHERE id = ? AND employee_id = ? AND status = 'draft'
        ");
        $stmt->execute([$mobile, $encodedFormData, $currentStep, $draftId, $employeeId]);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO employee_form_submissions (employee_id, mobile, form_data, status, current_step, updated_at)
            VALUES (?, ?, ?, 'draft', ?, NOW())
        ");
        $stmt->execute([$employeeId, $mobile, $encodedFormData, $currentStep]);
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Draft saved successfully'
    ]);

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Database error in save-draft: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
