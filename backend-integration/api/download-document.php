<?php
/**
 * Download Document Endpoint
 * Serves uploaded documents for preview/download
 */
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config/secure-session.php';
require_once __DIR__ . '/../config/document-storage.php';

startSecureSession();

// Verify CSRF token
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!$csrfToken || $csrfToken !== ($_SESSION['csrf_token'] ?? '')) {
    http_response_code(419);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

// Get file ID from query string
$fileId = $_GET['file_id'] ?? '';
$employeeId = (int) ($_SESSION['verified_employee_id'] ?? 0);
if ($employeeId < 1) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

if (empty($fileId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'File ID is required']);
    exit;
}

try {
    $document = submissionResolveUploadReference(
        (string) $fileId,
        $employeeId,
        dirname(__DIR__, 2)
    );
    if ($document === null) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Document not found']);
        exit;
    }

    $filePath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR .
        str_replace('/', DIRECTORY_SEPARATOR, $document['file_path']);
    $detectedMimeType = (new finfo(FILEINFO_MIME_TYPE))->file($filePath);
    $inlineTypes = ['application/pdf', 'image/jpeg', 'image/png'];
    $isPreviewable = in_array($detectedMimeType, $inlineTypes, true);
    $mimeType = $isPreviewable ? $detectedMimeType : 'application/octet-stream';
    $downloadName = $document['stored_name'];
    $disposition = $isPreviewable ? 'inline' : 'attachment';
    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . (string) filesize($filePath));
    header(
        'Content-Disposition: ' . $disposition . '; filename="document"; filename*=UTF-8\'\'' .
        rawurlencode($downloadName)
    );
    header('Cache-Control: no-store, private');
    header('X-Content-Type-Options: nosniff');

    readfile($filePath);
    exit;

} catch (Throwable $e) {
    error_log("Error in download-document: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to serve document']);
}