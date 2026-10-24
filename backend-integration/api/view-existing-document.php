<?php
/**
 * Serves an employee's existing HRMS document after OTP verification.
 */
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, private');
header('X-Frame-Options: SAMEORIGIN');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/secure-session.php';
require_once __DIR__ . '/../config/document-storage.php';
startSecureSession();

$employeeId = (int) ($_SESSION['verified_employee_id'] ?? 0);
if ($employeeId < 1) {
    http_response_code(401);
    exit('Employee authentication is required.');
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    exit('Method not allowed.');
}

$documentType = $_GET['type'] ?? '';
if (!is_string($documentType)) {
    http_response_code(400);
    exit('Invalid document type.');
}
$identityColumns = [
    'aadhaar' => ['aadhar_copy', 'aadhaar'],
    'pan' => ['pan_copy', 'pan'],
    'photo' => ['photo', 'photo'],
    'resume' => ['resume', 'resume'],
    'signature' => ['staff_sign', 'signature']
];

try {
    $pdo = getDatabaseConnection();
    if ($documentType === 'education_certificate') {
        $rowId = filter_var($_GET['row_id'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1]
        ]);
        if ($rowId === false) {
            http_response_code(400);
            exit('A valid qualification ID is required.');
        }
        $stmt = $pdo->prepare("
            SELECT certificate_path
            FROM educational_qualifications
            WHERE id = ? AND employee_id = ?
        ");
        $stmt->execute([$rowId, $employeeId]);
        $storedPath = $stmt->fetchColumn();
        $folderType = 'education_certificate';
    } elseif (isset($identityColumns[$documentType])) {
        [$column, $folderType] = $identityColumns[$documentType];
        $stmt = $pdo->prepare("
            SELECT `$column`
            FROM employee_identification
            WHERE employee_id = ?
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([$employeeId]);
        $storedPath = $stmt->fetchColumn();
    } else {
        http_response_code(400);
        exit('Unsupported document type.');
    }

    if (!is_string($storedPath)) {
        $storedPath = '';
    }
    if (trim($storedPath) === '' && !isset($identityColumns[$documentType])) {
        http_response_code(404);
        exit('No existing document is recorded.');
    }

    $filePath = trim($storedPath) === ''
        ? null
        : resolveExistingEmployeeDocument(dirname(__DIR__, 2), $folderType, $storedPath);
    if ($filePath === null && isset($identityColumns[$documentType])) {
        $documentTypes = [
            'aadhaar' => ['aadhaar', 'aadhar'],
            'pan' => ['pan'],
            'photo' => ['photo'],
            'resume' => ['resume'],
            'signature' => ['signature', 'staff_sign']
        ];
        $aliases = $documentTypes[$documentType];
        $placeholders = implode(', ', array_fill(0, count($aliases), '?'));
        $documentStmt = $pdo->prepare("
            SELECT document_type, file_path
            FROM employee_documents
            WHERE employee_id = ? AND LOWER(REPLACE(document_type, '-', '_')) IN ($placeholders)
            ORDER BY id DESC
        ");
        $documentStmt->execute([$employeeId, ...$aliases]);
        foreach ($documentStmt->fetchAll(PDO::FETCH_ASSOC) as $document) {
            $documentTypeAlias = strtolower(str_replace('-', '_', (string) $document['document_type']));
            $filePath = resolveExistingEmployeeDocument(
                dirname(__DIR__, 2),
                $documentTypeAlias,
                (string) ($document['file_path'] ?? '')
            );
            if ($filePath !== null) {
                break;
            }
        }
    }
    if ($filePath === null) {
        http_response_code(404);
        exit('The existing document is unavailable.');
    }

    $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($filePath);
    $allowedMimeTypes = ['application/pdf', 'image/jpeg', 'image/png'];
    if (!in_array($mimeType, $allowedMimeTypes, true)) {
        http_response_code(415);
        exit('This document type cannot be previewed.');
    }

    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . (string) filesize($filePath));
    header('Content-Disposition: inline; filename="employee-document.' . [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png'
    ][$mimeType] . '"');
    readfile($filePath);
} catch (Throwable $e) {
    error_log('Existing employee document preview failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Unable to preview the document.');
}
