<?php
/**
 * Download Document Endpoint
 * Serves uploaded documents for preview/download
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

// Get file ID from query string
$fileId = $_GET['file_id'] ?? '';

if (empty($fileId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'File ID is required']);
    exit;
}

try {
    // Connect to database
    $pdo = getDatabaseConnection();

    // Get document record
    $stmt = $pdo->prepare("
        SELECT file_path, original_name, mime_type, file_size
        FROM employee_documents
        WHERE id = ? AND employee_id = ?
    ");
    $stmt->execute([$fileId, $_SESSION['verified_employee_id'] ?? 0]);
    $document = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$document) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Document not found']);
        exit;
    }

    // Check if file exists
    if (!file_exists($document['file_path'])) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'File not found on server']);
        exit;
    }

    // Set headers for file preview
    header('Content-Type: ' . $document['mime_type']);
    header('Content-Length: ' . $document['file_size']);
    header('Content-Disposition: inline; filename="' . basename($document['original_name']) . '"');
    header('Cache-Control: private, max-age=3600');
    header('X-Content-Type-Options: nosniff');

    // Output file
    readfile($document['file_path']);
    exit;

} catch (PDOException $e) {
    error_log("Database error in download-document: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
} catch (Exception $e) {
    error_log("Error in download-document: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to serve document']);
}