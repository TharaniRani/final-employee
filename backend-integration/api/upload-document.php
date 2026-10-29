<?php
/**
 * Upload Document Endpoint
 * Handles file uploads for employee documents
 */
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

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
if (!is_string($documentType) || strlen($documentType) > 100) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid document category']);
    exit;
}

$maxSize = 5 * 1024 * 1024; // 5MB

if ($file['size'] < 1 || $file['size'] > $maxSize) {
    http_response_code($file['size'] > $maxSize ? 413 : 422);
    echo json_encode(['success' => false, 'message' => 'The file must be non-empty and no larger than 5MB.']);
    exit;
}

$documentType = strtolower(trim($documentType));
$projectRoot = dirname(__DIR__, 2);
try {
    $storedName = submissionNormalizeUploadFilename((string) $file['name']);
    $relativePath = submissionTemporaryDocumentRelativePath((int) $employeeId, $documentType, $storedName);
    $permanentPath = $projectRoot . DIRECTORY_SEPARATOR .
        str_replace(
            '/',
            DIRECTORY_SEPARATOR,
            submissionPermanentDocumentRelativePath((int) $employeeId, $documentType, $storedName)
        );
} catch (DomainException $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
$filePath = $projectRoot . DIRECTORY_SEPARATOR .
    str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
$uploadDir = dirname($filePath);

try {
    if (is_file($permanentPath)) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'A file with this name already exists in this document category. Rename the file and upload it again.'
        ]);
        exit;
    }

    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('Unable to create the temporary document folder.');
    }

    $destination = @fopen($filePath, 'xb');
    if ($destination === false) {
        if (file_exists($filePath)) {
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'message' => 'A file with this name already exists in this document category. Rename the file and upload it again.'
            ]);
            exit;
        }
        throw new RuntimeException('Unable to reserve a unique document filename.');
    }
    fclose($destination);
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        @unlink($filePath);
        throw new Exception('Failed to move uploaded file');
    }

    $uploadReference = submissionCreateUploadReference(
        (int) $employeeId,
        $documentType,
        $storedName
    );

    echo json_encode([
        'success' => true,
        'message' => 'Document uploaded successfully',
        'file_id' => $uploadReference,
        'document_id' => $uploadReference,
        'original_name' => $storedName,
        'stored_name' => $storedName
    ]);

} catch (Throwable $e) {
    if (isset($filePath) && is_file($filePath)) {
        @unlink($filePath);
    }
    error_log("Error in upload-document: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Upload failed. Please try again.']);
}
