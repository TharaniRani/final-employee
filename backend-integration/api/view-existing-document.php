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
    'aadhaar' => ['aadhar_copy', 'aadhaar'],
    'pan' => ['pan_copy', 'pan'],
    'photo' => ['photo', 'photo'],
    'resume' => ['resume', 'resume'],
    'signature' => ['staff_sign', 'signature'],
    'bed' => ['pg_degree_doc', 'bed'],
    'driving' => ['driving', 'driving'],
    'police_verification' => ['others', 'police-verification'],
    'other_document' => ['others', 'other_document'],
    'ccs' => ['ccs', 'ccs']
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
    if ($filePath === null) {
        http_response_code(404);
        exit('The existing document is unavailable.');
    }

    $detectedMimeType = (new finfo(FILEINFO_MIME_TYPE))->file($filePath);
    $inlineTypes = ['application/pdf', 'image/jpeg', 'image/png'];
    $isPreviewable = in_array($detectedMimeType, $inlineTypes, true);
    $mimeType = $isPreviewable ? $detectedMimeType : 'application/octet-stream';
    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . (string) filesize($filePath));
    $downloadName = basename(str_replace('\\', '/', $storedPath));
    header(
        'Content-Disposition: ' . ($isPreviewable ? 'inline' : 'attachment') .
        '; filename="document"; filename*=UTF-8\'\'' . rawurlencode($downloadName)
    );
    readfile($filePath);
} catch (Throwable $e) {
    error_log('Existing employee document preview failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Unable to preview the document.');
}
