<?php
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, private');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/document-storage.php';
require_once __DIR__ . '/../config/secure-session.php';
require_once __DIR__ . '/../config/admin-auth.php';
startSecureSession();

function adminEmployeeDocumentRespond(int $status, array $body): void
{
    http_response_code($status);
    echo json_encode($body, JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

$admin = currentAdminUser();
if ($admin === null) {
    adminEmployeeDocumentRespond(403, ['success' => false, 'message' => 'Admin authorization is required.']);
}
if (!hash_equals($_SESSION['csrf_token'] ?? '', (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))) {
    adminEmployeeDocumentRespond(419, ['success' => false, 'message' => 'Refresh the page and try again.']);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    adminEmployeeDocumentRespond(405, ['success' => false, 'message' => 'Method not allowed.']);
}

$employeeId = filter_var($_POST['employee_id'] ?? null, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);
$submissionId = filter_var($_POST['submission_id'] ?? null, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);
$documentType = strtolower(trim((string) ($_POST['document_type'] ?? '')));
$allowedTypes = [
    'aadhaar', 'pan', 'resume', 'photo', 'signature', 'bed', 'driving',
    'police-verification', 'education_certificate', 'experience_letter',
    'experience_payslip', 'other_document', 'ccs'
];
if ($employeeId === false || $submissionId === false || !in_array($documentType, $allowedTypes, true)) {
    adminEmployeeDocumentRespond(422, ['success' => false, 'message' => 'A valid employee, submission, and document type are required.']);
}
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    adminEmployeeDocumentRespond(422, ['success' => false, 'message' => 'Choose a document to upload.']);
}

$file = $_FILES['file'];
if ($file['size'] < 1 || $file['size'] > 5 * 1024 * 1024) {
    adminEmployeeDocumentRespond(422, ['success' => false, 'message' => 'Documents must be 5MB or smaller.']);
}
$mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
$extensions = [
    'application/pdf' => 'pdf',
    'image/jpeg' => 'jpg',
    'image/png' => 'png'
];
if (!isset($extensions[$mimeType])) {
    adminEmployeeDocumentRespond(422, ['success' => false, 'message' => 'Upload a PDF, JPG, or PNG document.']);
}

$filePath = null;
try {
    $pdo = getDatabaseConnection();
    if (!employeeIsInAdminScope($pdo, (int) $employeeId, $admin) ||
        !submissionIsInAdminScope($pdo, (int) $submissionId, $admin)) {
        adminEmployeeDocumentRespond(404, ['success' => false, 'message' => 'Submission not found in your authorized unit.']);
    }
    $submission = $pdo->prepare("
        SELECT 1
        FROM employee_form_submissions
        WHERE id = ? AND employee_id = ? AND status IN ('pending', 'approved')
        LIMIT 1
    ");
    $submission->execute([$submissionId, $employeeId]);
    if ($submission->fetchColumn() === false) {
        adminEmployeeDocumentRespond(404, ['success' => false, 'message' => 'Submission is no longer available.']);
    }

    $storedName = $employeeId . '_' . str_replace('_', '-', $documentType) . '_' .
        bin2hex(random_bytes(16)) . '.' . $extensions[$mimeType];
    $relativePath = submissionTemporaryDocumentRelativePath(
        (int) $employeeId,
        $documentType,
        $storedName
    );
    $projectRoot = dirname(__DIR__, 2);
    $filePath = $projectRoot . DIRECTORY_SEPARATOR .
        str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    $uploadDirectory = dirname($filePath);
    if (!is_dir($uploadDirectory) &&
        !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
        throw new RuntimeException('Unable to create the temporary document folder.');
    }
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        throw new RuntimeException('Unable to store the uploaded document.');
    }

    $insert = $pdo->prepare("
        INSERT INTO employee_documents
            (employee_id, document_type, original_name, stored_name, file_path, file_size, mime_type, metadata, uploaded_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $insert->execute([
        $employeeId,
        $documentType,
        basename((string) $file['name']),
        $storedName,
        $relativePath,
        $file['size'],
        $mimeType,
        json_encode(['submission_id' => (int) $submissionId, 'uploaded_by' => (int) $admin['id']], JSON_THROW_ON_ERROR)
    ]);

    adminEmployeeDocumentRespond(200, [
        'success' => true,
        'message' => 'Replacement document uploaded.',
        'file_path' => $relativePath,
        'original_name' => basename((string) $file['name'])
    ]);
} catch (DomainException $error) {
    if ($filePath !== null && is_file($filePath)) {
        unlink($filePath);
    }
    adminEmployeeDocumentRespond(422, ['success' => false, 'message' => $error->getMessage()]);
} catch (Throwable $error) {
    if ($filePath !== null && is_file($filePath)) {
        unlink($filePath);
    }
    error_log('Admin employee document upload failed: ' . $error->getMessage());
    adminEmployeeDocumentRespond(500, ['success' => false, 'message' => 'Unable to upload the replacement document.']);
}
