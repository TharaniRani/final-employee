<?php
/**
 * Upload Document Endpoint
 * Handles file uploads for employee documents
 */
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/document-storage.php';
require_once __DIR__ . '/../config/secure-session.php';

startSecureSession();

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
    http_response_code(401);
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
if (!is_string($documentType) || strlen($documentType) > 100) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid document category']);
    exit;
}
if (!is_array($metadata)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid document metadata']);
    exit;
}

// Validate file
$allowedTypes = ['application/pdf', 'image/jpeg', 'image/png'];
$maxSize = 5 * 1024 * 1024; // 5MB

if ($file['size'] > $maxSize) {
    echo json_encode(['success' => false, 'message' => 'File size exceeds 5MB limit']);
    exit;
}

// Additional validation: check actual MIME type
$finfo = new finfo(FILEINFO_MIME_TYPE);
$actualType = $finfo->file($file['tmp_name']);

if (!in_array($actualType, $allowedTypes)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid file content type']);
    exit;
}

$extensions = [
    'application/pdf' => 'pdf',
    'image/jpeg' => 'jpg',
    'image/png' => 'png'
];
$documentType = strtolower(trim($documentType));
$fileType = str_replace('_', '-', $documentType);
$typeToken = preg_replace('/[^a-z0-9-]/', '', $fileType);
$storedName = (int) $employeeId . '_' . $typeToken . '_' . bin2hex(random_bytes(16)) .
    '.' . $extensions[$actualType];
$projectRoot = dirname(__DIR__, 2);
try {
    $relativePath = submissionTemporaryDocumentRelativePath((int) $employeeId, $documentType, $storedName);
} catch (DomainException $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
$filePath = $projectRoot . DIRECTORY_SEPARATOR .
    str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
$uploadDir = dirname($filePath);

try {
    $pdo = getDatabaseConnection();
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('Unable to create the temporary document folder.');
    }

    $destination = @fopen($filePath, 'xb');
    if ($destination === false) {
        throw new RuntimeException('Unable to reserve a unique document filename.');
    }
    fclose($destination);
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        @unlink($filePath);
        throw new Exception('Failed to move uploaded file');
    }

    $stmt = $pdo->prepare("
        INSERT INTO employee_documents (employee_id, document_type, original_name, stored_name, file_path, file_size, mime_type, metadata, uploaded_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $employeeId,
        $documentType,
        $file['name'],
        $storedName,
        $relativePath,
        $file['size'],
        $actualType,
        json_encode($metadata, JSON_THROW_ON_ERROR)
    ]);

    $documentId = $pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'message' => 'Document uploaded successfully',
        'file_id' => $documentId,
        'document_id' => $documentId,
        'original_name' => $file['name'],
        'stored_name' => $storedName
    ]);

} catch (PDOException $e) {
    if (isset($filePath) && is_file($filePath)) {
        @unlink($filePath);
    }
    error_log("Database error in upload-document: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
} catch (Throwable $e) {
    if (isset($filePath) && is_file($filePath)) {
        @unlink($filePath);
    }
    error_log("Error in upload-document: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Upload failed. Please try again.']);
}
