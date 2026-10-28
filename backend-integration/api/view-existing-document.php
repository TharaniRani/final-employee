<?php
/**
 * Serves an existing employee document after employee verification or authorized HR access.
 */
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, private');
header('X-Frame-Options: SAMEORIGIN');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/secure-session.php';
require_once __DIR__ . '/../config/document-storage.php';
require_once __DIR__ . '/../config/admin-auth.php';
startSecureSession();

$admin = currentAdminUser();
if ($admin !== null) {
    $employeeId = filter_var($_GET['employee_id'] ?? null, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1]
    ]);
    if ($employeeId === false) {
        http_response_code(400);
        exit('A valid employee ID is required.');
    }
    try {
        if (!employeeIsInAdminScope(getDatabaseConnection(), (int) $employeeId, $admin)) {
            http_response_code(404);
            exit('Employee not found in your authorized unit.');
        }
    } catch (Throwable $e) {
        error_log('Admin document authorization failed: ' . $e->getMessage());
        http_response_code(500);
        exit('Unable to authorize this document.');
    }
} else {
    $employeeId = (int) ($_SESSION['verified_employee_id'] ?? 0);
    if ($employeeId < 1) {
        http_response_code(401);
        exit('Employee authentication is required.');
    }
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
    'aadhaar' => ['aadhar_copy', 'aadhaar', ['aadhaar', 'aadhar']],
    'pan' => ['pan_copy', 'pan', ['pan']],
    'photo' => ['photo', 'photo', ['photo']],
    'resume' => ['resume', 'resume', ['resume']],
    'signature' => ['staff_sign', 'signature', ['signature', 'staff_sign']],
    'bed' => ['pg_degree_doc', 'bed', ['bed']],
    'driving' => ['driving', 'driving', ['driving', 'driving_license']],
    'police_verification' => ['others', 'police-verification', ['police_verification']],
    'other_document' => ['others', 'other_document', ['other_document', 'others']],
    'ccs' => ['ccs', 'ccs', ['ccs']]
];

try {
    $pdo = getDatabaseConnection();
    if (in_array($documentType, ['education_certificate', 'experience_letter', 'experience_payslip'], true)) {
        $rowId = filter_var($_GET['row_id'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1]
        ]);
        if ($rowId === false) {
            http_response_code(400);
            exit('A valid document record ID is required.');
        }
        if ($documentType === 'education_certificate') {
            $stmt = $pdo->prepare("
                SELECT certificate_path
                FROM educational_qualifications
                WHERE id = ? AND employee_id = ?
            ");
            $folderType = 'education_certificate';
        } else {
            $column = $documentType === 'experience_letter' ? 'experience_letter_path' : 'pay_slip';
            $stmt = $pdo->prepare("
                SELECT `$column`
                FROM employee_previous_experience
                WHERE id = ? AND employee_id = ?
            ");
            $folderType = $documentType === 'experience_letter' ? 'experience_letter' : 'experience_payslip';
        }
        $stmt->execute([$rowId, $employeeId]);
        $storedPath = $stmt->fetchColumn();
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
        $aliases = $identityColumns[$documentType][2];
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
            $storageType = [
                'aadhar' => 'aadhaar',
                'staff_sign' => 'signature',
                'driving_license' => 'driving',
                'police_verification' => 'police-verification',
                'others' => 'other_document'
            ][$documentTypeAlias] ?? $documentTypeAlias;
            $filePath = resolveExistingEmployeeDocument(
                dirname(__DIR__, 2),
                $storageType,
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
