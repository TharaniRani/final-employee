<?php
/**
 * Upload Document Endpoint
 * Handles file uploads for employee documents
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

// Check if file was uploaded
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
    exit;
}

$file = $_FILES['file'];
$documentType = $_POST['document_type'] ?? 'document';
$metadata = json_decode($_POST['metadata'] ?? '{}', true);

// Validate file
$allowedTypes = ['application/pdf', 'image/jpeg', 'image/png'];
$maxSize = 5 * 1024 * 1024; // 5MB

if (!in_array($file['type'], $allowedTypes)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Allowed: PDF, JPG, PNG']);
    exit;
}

if ($file['size'] > $maxSize) {
    echo json_encode(['success' => false, 'message' => 'File size exceeds 5MB limit']);
    exit;
}

// Additional validation: check actual MIME type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$actualType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($actualType, $allowedTypes)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file content type']);
    exit;
}

try {
    $pdo = getDatabaseConnection();

    // Generate secure filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $secureName = bin2hex(random_bytes(16)) . '.' . strtolower($extension);

    // Store outside web root if possible, otherwise in protected uploads folder
    $uploadDir = __DIR__ . '/../../uploads/documents/' . $employeeId . '/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filePath = $uploadDir . $secureName;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        throw new Exception('Failed to move uploaded file');
    }

    // Save document record to database
    $stmt = $pdo->prepare("
        INSERT INTO employee_documents (employee_id, document_type, original_name, stored_name, file_path, file_size, mime_type, metadata, uploaded_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $employeeId,
        $documentType,
        $file['name'],
        $secureName,
        $filePath,
        $file['size'],
        $actualType,
        json_encode($metadata)
    ]);

    $documentId = $pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'message' => 'Document uploaded successfully',
        'file_id' => $documentId,
        'document_id' => $documentId,
        'original_name' => $file['name'],
        'stored_name' => $secureName
    ]);

} catch (PDOException $e) {
    error_log("Database error in upload-document: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
} catch (Exception $e) {
    error_log("Error in upload-document: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Upload failed: ' . $e->getMessage()]);
}
