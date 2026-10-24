<?php
/**
 * Download Document Endpoint
 * Serves uploaded documents for preview/download
 */
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config/database.php';
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
    // Connect to database
    $pdo = getDatabaseConnection();

    // Get document record
    $stmt = $pdo->prepare("
        SELECT document_type, stored_name, file_path, original_name, file_size
        FROM employee_documents
        WHERE id = ? AND employee_id = ?
    ");
    $stmt->execute([$fileId, $employeeId]);
    $document = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$document) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Document not found']);
        exit;
    }

    $filePath = resolveEmployeeDocumentFile(
        dirname(__DIR__, 2),
        $employeeId,
        (string) $document['document_type'],
        (string) $document['stored_name'],
        (string) $document['file_path']
    );
    if ($filePath === null) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'File not found on server']);
        exit;
    }

    $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($filePath);
    $extensions = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png'
    ];
    if (!isset($extensions[$mimeType])) {
        http_response_code(415);
        echo json_encode(['success' => false, 'message' => 'This document type cannot be previewed']);
        exit;
    }

    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . (string) filesize($filePath));
    header('Content-Disposition: inline; filename="employee-document.' . $extensions[$mimeType] . '"');
    header('Cache-Control: no-store, private');
    header('X-Content-Type-Options: nosniff');

    readfile($filePath);
    exit;

} catch (PDOException $e) {
    error_log("Database error in download-document: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
} catch (Throwable $e) {
    error_log("Error in download-document: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to serve document']);
}

function resolveEmployeeDocumentFile(
    string $projectRoot,
    int $employeeId,
    string $documentType,
    string $storedName,
    string $recordedPath
): ?string {
    $recordedPath = str_replace('\\', '/', trim($recordedPath));
    $expectedTemporaryPath = submissionTemporaryDocumentRelativePath($employeeId, $documentType, $storedName);
    $expectedCategorizedPath = submissionDocumentRelativePath($employeeId, $documentType, $storedName);
    $expectedLegacyPath = submissionLegacyDocumentRelativePath($employeeId, $storedName);
    $allowedPaths = [
        $expectedTemporaryPath,
        $expectedCategorizedPath,
        $expectedLegacyPath,
        submissionPermanentDocumentRelativePath($employeeId, $documentType, $storedName)
    ];
    if (in_array($recordedPath, $allowedPaths, true)) {
        $candidatePath = $projectRoot . DIRECTORY_SEPARATOR .
            str_replace('/', DIRECTORY_SEPARATOR, $recordedPath);
        $resolved = resolveWithinEmployeeDocumentRoots($projectRoot, $employeeId, $candidatePath);
        if ($resolved !== null) {
            return $resolved;
        }
    }

    if (preg_match('/^[A-Za-z]:[\/\\\\]/', $recordedPath) || str_starts_with($recordedPath, '/')) {
        $resolved = resolveWithinEmployeeDocumentRoots($projectRoot, $employeeId, $recordedPath);
        if ($resolved !== null) {
            return $resolved;
        }
    }

    foreach ($allowedPaths as $allowedPath) {
        $candidatePath = $projectRoot . DIRECTORY_SEPARATOR .
            str_replace('/', DIRECTORY_SEPARATOR, $allowedPath);
        $resolved = resolveWithinEmployeeDocumentRoots($projectRoot, $employeeId, $candidatePath);
        if ($resolved !== null) {
            return $resolved;
        }
    }

    return null;
}

function resolveWithinEmployeeDocumentRoots(
    string $projectRoot,
    int $employeeId,
    string $candidatePath
): ?string {
    $candidate = realpath($candidatePath);
    if ($candidate === false || !is_file($candidate)) {
        return null;
    }

    $allowedRoots = [
        $projectRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'documents' .
            DIRECTORY_SEPARATOR . $employeeId,
        $projectRoot . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'requests' .
            DIRECTORY_SEPARATOR . 'employees'
    ];
    foreach (submissionEmployeeDocumentFolderNames() as $folder) {
        $allowedRoots[] = $projectRoot . DIRECTORY_SEPARATOR . 'employees_temp_documents' .
            DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR . $employeeId;
    }
    foreach (submissionDocumentCategories() as $category) {
        $allowedRoots[] = $projectRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR .
            'documents' . DIRECTORY_SEPARATOR . $category . DIRECTORY_SEPARATOR . $employeeId;
    }
    foreach ($allowedRoots as $allowedRoot) {
        $resolvedRoot = realpath($allowedRoot);
        if ($resolvedRoot !== false &&
            str_starts_with(strtolower($candidate), strtolower($resolvedRoot . DIRECTORY_SEPARATOR))) {
            return $candidate;
        }
    }

    return null;
}

function submissionEmployeeDocumentFolderNames(): array
{
    return ['aadhar', 'pan', 'photo', 'sign', 'resume', 'certificate_path',
        'pg_degree_doc', 'driving', 'others', 'ccs'];
}