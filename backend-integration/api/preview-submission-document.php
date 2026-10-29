<?php
/**
 * Serves a document attached to a specific employee submission for HR preview.
 */
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, private');
header('X-Frame-Options: SAMEORIGIN');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/secure-session.php';
require_once __DIR__ . '/../config/submission-staging.php';
require_once __DIR__ . '/../config/document-storage.php';
require_once __DIR__ . '/../config/admin-auth.php';
startSecureSession();

$admin = currentAdminUser();
if ($admin === null) {
    http_response_code(403);
    exit('Admin authorization is required.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    exit('Method not allowed.');
}

$submissionId = filter_var($_GET['submission_id'] ?? null, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);
$field = $_GET['field'] ?? '';
$document = $_GET['document'] ?? '';
$side = $_GET['side'] ?? '';
$rowIndex = filter_var($_GET['row'] ?? 0, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 0, 'max_range' => 100]
]);
$column = $_GET['column'] ?? '';

if ($submissionId === false || !is_string($field) || !is_string($document) ||
    ($document === '' && !in_array($side, ['original', 'requested'], true)) ||
    ($document !== '' && !in_array($document, [
        'resume', 'aadhaar', 'pan', 'photo', 'signature', 'bed', 'driving',
        'police_verification', 'other_document', 'ccs', 'education_certificate',
        'experience_letter', 'experience_payslip'
    ], true)) || $rowIndex === false || !is_string($column)) {
    http_response_code(400);
    exit('Invalid document preview request.');
}

try {
    $pdo = getDatabaseConnection();
    $stmt = $pdo->prepare("
        SELECT employee_id, status, form_data, approved_fields
        FROM employee_form_submissions
        WHERE id = ? AND status IN ('pending', 'approved')
    ");
    $stmt->execute([$submissionId]);
    $submission = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$submission) {
        http_response_code(404);
        exit('Submission not found.');
    }
    if (!submissionIsInAdminScope($pdo, (int) $submissionId, $admin)) {
        http_response_code(403);
        exit('You are not authorized to view this employee document.');
    }

    $employeeId = (int) $submission['employee_id'];
    $formData = json_decode($submission['form_data'], true, 512, JSON_THROW_ON_ERROR);

    $scalarDocuments = [
        'employee_identification.aadhar_copy' => ['temp_employee_identification', 'employee_identification', 'id', 'id', 'aadhar_copy'],
        'employee_identification.pan_copy' => ['temp_employee_identification', 'employee_identification', 'id', 'id', 'pan_copy'],
        'employee_identification.photo' => ['temp_employee_identification', 'employee_identification', 'id', 'id', 'photo'],
        'employee_identification.resume' => ['temp_employee_identification', 'employee_identification', 'id', 'id', 'resume'],
        'employee_identification.staff_sign' => ['temp_employee_identification', 'employee_identification', 'id', 'id', 'staff_sign'],
        'employee_identification.driving' => ['temp_employee_identification', 'employee_identification', 'id', 'id', 'driving']
    ];
    $collectionDocuments = [
        'educational_qualifications.all' => [
            'temp_educational_qualifications', 'educational_qualifications', 'id',
            ['certificate_path']
        ],
        'employee_previous_experience.all' => [
            'temp_employee_previous_experience', 'employee_previous_experience', 'id',
            ['pay_slip', 'experience_letter_path']
        ]
    ];
    $documentPath = null;

    if ($document !== '') {
        $documentPath = submissionDocumentPathFromFormData(
            $formData,
            $document,
            (int) $rowIndex,
            $column
        );
    } else {
        $changedFields = previewSubmissionChangedFields(
            $pdo,
            $formData,
            $employeeId,
            (int) $submissionId,
            (string) $submission['status'],
            $submission['approved_fields']
        );
        if (!in_array($field, $changedFields, true)) {
            http_response_code(403);
            exit('This document is not part of the reviewed changes.');
        }
    }

    if ($document === '' && isset($scalarDocuments[$field])) {
        if ($column !== '') {
            http_response_code(400);
            exit('Invalid document field.');
        }
        [$stagingTable, $originalTable, $stagingKey, $originalKey, $documentColumn] = $scalarDocuments[$field];
        if ($side === 'requested') {
            $stmt = $pdo->prepare(sprintf(
                'SELECT `%s` FROM `%s` WHERE employee_id = ? AND submission_id = ? ORDER BY `%s` DESC LIMIT 1',
                $documentColumn,
                $stagingTable,
                $stagingKey
            ));
            $stmt->execute([$employeeId, $submissionId]);
            $documentPath = $stmt->fetchColumn();
        } else {
            $snapshots = $formData['submission']['original_values'] ?? [];
            if (is_array($snapshots) && array_key_exists($field, $snapshots)) {
                $documentPath = $snapshots[$field];
            } elseif ($submission['status'] !== 'approved') {
                $stmt = $pdo->prepare(sprintf(
                    'SELECT `%s` FROM `%s` WHERE employee_id = ? ORDER BY `%s` DESC LIMIT 1',
                    $documentColumn,
                    $originalTable,
                    $originalKey
                ));
                $stmt->execute([$employeeId]);
                $documentPath = $stmt->fetchColumn();
            }
        }
    } elseif ($document === '' && isset($collectionDocuments[$field])) {
        [$stagingTable, $originalTable, $primaryKey, $documentColumns] = $collectionDocuments[$field];
        if (!in_array($column, $documentColumns, true)) {
            http_response_code(400);
            exit('Invalid document field.');
        }

        if ($side === 'original') {
            $snapshots = $formData['submission']['original_values'] ?? [];
            $originalRows = is_array($snapshots) ? ($snapshots[$field] ?? null) : null;
            if (is_array($originalRows)) {
                $documentPath = $originalRows[$rowIndex][$column] ?? null;
            } elseif ($submission['status'] !== 'approved') {
                $stmt = $pdo->prepare(sprintf(
                    'SELECT `%s` FROM `%s` WHERE employee_id = ? ORDER BY `%s`',
                    $column,
                    $originalTable,
                    $primaryKey
                ));
                $stmt->execute([$employeeId]);
                $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
                $documentPath = $rows[$rowIndex] ?? null;
            }
        } else {
            $stmt = $pdo->prepare(sprintf(
                'SELECT `%s` FROM `%s` WHERE employee_id = ? AND submission_id = ? ORDER BY `%s`',
                $column,
                $stagingTable,
                $primaryKey
            ));
            $stmt->execute([$employeeId, $submissionId]);
            $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $documentPath = $rows[$rowIndex] ?? null;
        }
    } elseif ($document === '') {
        http_response_code(400);
        exit('This field is not a document.');
    }

    if (!is_string($documentPath) || trim($documentPath) === '') {
        http_response_code(404);
        exit('Document not found for this submission.');
    }

    $documentStorageType = null;
    if ($document === '' && $side === 'requested' && isset($scalarDocuments[$field])) {
        $documentStorageType = [
            'employee_identification.aadhar_copy' => 'aadhaar',
            'employee_identification.pan_copy' => 'pan',
            'employee_identification.photo' => 'photo',
            'employee_identification.resume' => 'resume',
            'employee_identification.staff_sign' => 'signature',
            'employee_identification.driving' => 'driving'
        ][$field] ?? null;
    } elseif ($document === '' && $side === 'requested' && $field === 'educational_qualifications.all') {
        $documentStorageType = 'education_certificate';
    } elseif ($document === '' && $side === 'requested' && $field === 'employee_previous_experience.all') {
        $documentStorageType = $column === 'pay_slip' ? 'experience_payslip' : 'experience_letter';
    }

    $filePath = resolveSubmissionDocumentPath($pdo, $employeeId, $documentPath, $documentStorageType);
    if ($filePath === null) {
        http_response_code(404);
        exit('Document file is unavailable.');
    }

    $fileInfo = new finfo(FILEINFO_MIME_TYPE);
    $detectedMimeType = $fileInfo->file($filePath);
    $inlineTypes = ['application/pdf', 'image/jpeg', 'image/png'];
    $isPreviewable = in_array($detectedMimeType, $inlineTypes, true);
    $mimeType = $isPreviewable ? $detectedMimeType : 'application/octet-stream';
    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . (string) filesize($filePath));
    header(
        'Content-Disposition: ' . ($isPreviewable ? 'inline' : 'attachment') .
        '; filename="document"; filename*=UTF-8\'\'' . rawurlencode(basename($documentPath))
    );
    readfile($filePath);
} catch (Throwable $e) {
    error_log('HR document preview failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Unable to preview this document.');
}

function submissionDocumentPathFromFormData(array $formData, string $document, int $rowIndex, string $column): ?string
{
    $scalarPaths = [
        'resume' => $formData['documents']['resume']['file_path'] ?? null,
        'aadhaar' => $formData['documents']['aadhaar']['file_path'] ?? null,
        'pan' => $formData['documents']['pan']['file_path'] ?? null,
        'photo' => $formData['personal_details']['photo_file_path'] ?? null,
        'signature' => $formData['personal_details']['signature_file_path'] ?? null,
        'bed' => $formData['teacher_details']['document_file_path'] ?? null
    ];
    if (array_key_exists($document, $scalarPaths)) {
        if ($column !== '') {
            http_response_code(400);
            exit('Invalid document field.');
        }
        $path = $scalarPaths[$document];
        return is_string($path) ? $path : null;
    }

    if ($document === 'education_certificate') {
        $allowedColumn = 'certificate_file_path';
        $records = $formData['education'] ?? [];
    } elseif (in_array($document, ['experience_letter', 'experience_payslip'], true)) {
        $allowedColumn = $document === 'experience_letter'
            ? 'experience_letter_file_path'
            : 'pay_slip_file_path';
        $records = $formData['work_experience'] ?? [];
    } else {
        $allowedColumn = 'file_path';
        $records = $formData['other_documents'] ?? [];
    }
    if ($column !== $allowedColumn || !is_array($records) ||
        !isset($records[$rowIndex]) || !is_array($records[$rowIndex])) {
        http_response_code(400);
        exit('Invalid document field.');
    }

    $record = $records[$rowIndex];
    if ($document === 'driving' && ($record['type'] ?? '') !== 'driving_license') {
        http_response_code(404);
        exit('Document not found for this submission.');
    }
    if ($document === 'police_verification' && ($record['type'] ?? '') !== 'police_verification') {
        http_response_code(404);
        exit('Document not found for this submission.');
    }
    if ($document === 'ccs' && ($record['type'] ?? '') !== 'ccs') {
        http_response_code(404);
        exit('Document not found for this submission.');
    }
    if ($document === 'other_document' && isset($record['type'])) {
        http_response_code(404);
        exit('Document not found for this submission.');
    }

    $path = $record[$allowedColumn] ?? null;
    return is_string($path) ? $path : null;
}

function resolveSubmissionDocumentPath(
    PDO $pdo,
    int $employeeId,
    string $storedPath,
    ?string $documentType = null
): ?string
{
    $projectRoot = dirname(__DIR__, 2);
    $relativePath = str_replace('\\', '/', trim($storedPath));
    if (str_contains($relativePath, "\0") || preg_match('/^[A-Za-z]:/', $relativePath)) {
        return resolveAbsoluteEmployeeUpload($projectRoot, $employeeId, $storedPath);
    }

    if (preg_match(
        '#^uploads/documents/([a-z0-9-]+)/' . preg_quote((string) $employeeId, '#') . '/([^/]+)$#',
        $relativePath,
        $matches
    ) && in_array($matches[1], submissionDocumentCategories(), true) &&
        basename($matches[2]) === $matches[2]) {
        $candidate = realpath($projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath));
        $employeeUploadRoot = realpath(
            $projectRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'documents' .
            DIRECTORY_SEPARATOR . $matches[1] . DIRECTORY_SEPARATOR . $employeeId
        );
        if ($candidate !== false && $employeeUploadRoot !== false &&
            str_starts_with(strtolower($candidate), strtolower($employeeUploadRoot . DIRECTORY_SEPARATOR)) &&
            is_file($candidate)) {
            return $candidate;
        }
        return null;
    }

    if (preg_match(
        '#^uploads/documents/' . preg_quote((string) $employeeId, '#') . '/([^/]+)$#',
        $relativePath,
        $matches
    ) && basename($matches[1]) === $matches[1]) {
        $candidate = realpath($projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath));
        $employeeUploadRoot = realpath(
            $projectRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'documents' .
            DIRECTORY_SEPARATOR . $employeeId
        );
        if ($candidate !== false && $employeeUploadRoot !== false &&
            str_starts_with(strtolower($candidate), strtolower($employeeUploadRoot . DIRECTORY_SEPARATOR)) &&
            is_file($candidate)) {
            return $candidate;
        }
        return null;
    }

    if (str_starts_with($relativePath, 'assets/requests/employees/')) {
        $legacyRoot = realpath($projectRoot . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'requests' . DIRECTORY_SEPARATOR . 'employees');
        $candidate = realpath($projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath));
        if ($candidate !== false && $legacyRoot !== false &&
            str_starts_with(strtolower($candidate), strtolower($legacyRoot . DIRECTORY_SEPARATOR)) &&
            is_file($candidate)) {
            return $candidate;
        }
        return null;
    }

    if (preg_match(
        '#^employees_temp_documents/([a-z0-9_-]+)/' . preg_quote((string) $employeeId, '#') . '/([^/]+)$#',
        $relativePath,
        $matches
    ) && in_array($matches[1], [
        'aadhar', 'pan', 'photo', 'sign', 'resume', 'certificate_path',
        'pg_degree_doc', 'driving', 'others', 'ccs'
    ], true) && basename($matches[2]) === $matches[2]) {
        $candidate = realpath($projectRoot . DIRECTORY_SEPARATOR .
            str_replace('/', DIRECTORY_SEPARATOR, $relativePath));
        $temporaryRoot = realpath(
            $projectRoot . DIRECTORY_SEPARATOR . 'employees_temp_documents' .
            DIRECTORY_SEPARATOR . $matches[1] . DIRECTORY_SEPARATOR . $employeeId
        );
        if ($candidate !== false && $temporaryRoot !== false &&
            str_starts_with(strtolower($candidate), strtolower($temporaryRoot . DIRECTORY_SEPARATOR)) &&
            is_file($candidate)) {
            return $candidate;
        }

        return null;
    }

    if (preg_match(
        '#^employees_temp_documents/([a-z0-9_-]+)/([^/]+)$#',
        $relativePath,
        $matches
    ) && in_array($matches[1], [
        'aadhar', 'pan', 'photo', 'sign', 'resume', 'certificate_path',
        'pg_degree_doc', 'driving', 'others', 'ccs'
    ], true) && basename($matches[2]) === $matches[2]) {
        $candidate = realpath($projectRoot . DIRECTORY_SEPARATOR .
            str_replace('/', DIRECTORY_SEPARATOR, $relativePath));
        $temporaryRoot = realpath(
            $projectRoot . DIRECTORY_SEPARATOR . 'employees_temp_documents' .
            DIRECTORY_SEPARATOR . $matches[1]
        );
        if ($candidate !== false && $temporaryRoot !== false &&
            str_starts_with(strtolower($candidate), strtolower($temporaryRoot . DIRECTORY_SEPARATOR)) &&
            is_file($candidate)) {
            return $candidate;
        }
        return null;
    }

    if (basename($relativePath) !== $relativePath) {
        return null;
    }

    if ($documentType !== null) {
        $temporaryRelativePath = submissionTemporaryDocumentRelativePath(
            $employeeId,
            $documentType,
            $relativePath
        );
        $candidate = realpath($projectRoot . DIRECTORY_SEPARATOR .
            str_replace('/', DIRECTORY_SEPARATOR, $temporaryRelativePath));
        $temporaryRoot = realpath(
            $projectRoot . DIRECTORY_SEPARATOR . 'employees_temp_documents' .
            DIRECTORY_SEPARATOR . submissionEmployeeDocumentFolder($documentType)
        );
        if ($candidate !== false && $temporaryRoot !== false &&
            str_starts_with(strtolower($candidate), strtolower($temporaryRoot . DIRECTORY_SEPARATOR)) &&
            is_file($candidate)) {
            return $candidate;
        }
    }

    $legacyDocumentPath = resolveLegacyEmployeeDocumentPath($pdo, $employeeId, $projectRoot, $relativePath);
    if ($legacyDocumentPath !== null) {
        return $legacyDocumentPath;
    }
    return null;
}

function resolveLegacyEmployeeDocumentPath(
    PDO $pdo,
    int $employeeId,
    string $projectRoot,
    string $storedName
): ?string {
    $identity = $pdo->prepare("
        SELECT aadhar_copy, pan_copy, photo, staff_sign, resume, driving
        FROM employee_identification
        WHERE employee_id = ?
        ORDER BY id DESC
        LIMIT 1
    ");
    $identity->execute([$employeeId]);
    $identityRow = $identity->fetch(PDO::FETCH_ASSOC) ?: [];
    $documents = [];
    foreach ([
        'aadhar_copy' => 'aadhaar',
        'pan_copy' => 'pan',
        'photo' => 'photo',
        'staff_sign' => 'signature',
        'resume' => 'resume',
        'driving' => 'driving'
    ] as $column => $type) {
        if (!empty($identityRow[$column])) {
            $documents[] = [$identityRow[$column], $type];
        }
    }

    $education = $pdo->prepare("
        SELECT certificate_path
        FROM educational_qualifications
        WHERE employee_id = ? AND certificate_path IS NOT NULL AND TRIM(certificate_path) <> ''
    ");
    $education->execute([$employeeId]);
    foreach ($education->fetchAll(PDO::FETCH_COLUMN) as $path) {
        $documents[] = [$path, 'education_certificate'];
    }

    foreach ($documents as [$storedPath, $type]) {
        $normalizedPath = str_replace('\\', '/', trim((string) $storedPath));
        if (basename($normalizedPath) !== $storedName) {
            continue;
        }
        $candidatePath = str_starts_with($normalizedPath, 'assets/requests/employees/')
            ? $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalizedPath)
            : $projectRoot . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR .
                'requests' . DIRECTORY_SEPARATOR . 'employees' . DIRECTORY_SEPARATOR .
                submissionEmployeeDocumentFolder($type) . DIRECTORY_SEPARATOR . $storedName;
        $candidate = realpath($candidatePath);
        $legacyRoot = realpath(
            $projectRoot . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR .
            'requests' . DIRECTORY_SEPARATOR . 'employees'
        );
        if ($candidate !== false && $legacyRoot !== false && is_file($candidate) &&
            str_starts_with(strtolower($candidate), strtolower($legacyRoot . DIRECTORY_SEPARATOR))) {
            return $candidate;
        }
    }

    return null;
}

function resolveAbsoluteEmployeeUpload(string $projectRoot, int $employeeId, string $storedPath): ?string
{
    $candidate = realpath($storedPath);
    $legacyUploadRoot = realpath(
        $projectRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'documents' .
        DIRECTORY_SEPARATOR . $employeeId
    );
    if ($candidate === false || !is_file($candidate)) {
        return null;
    }

    $allowedRoots = [
        $legacyUploadRoot,
        realpath($projectRoot . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR .
            'requests' . DIRECTORY_SEPARATOR . 'employees'),
        realpath($projectRoot . DIRECTORY_SEPARATOR . 'employees_temp_documents')
    ];
    foreach (submissionDocumentCategories() as $category) {
        $allowedRoots[] = realpath(
            $projectRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'documents' .
            DIRECTORY_SEPARATOR . $category . DIRECTORY_SEPARATOR . $employeeId
        );
    }
    foreach ($allowedRoots as $allowedRoot) {
        if ($allowedRoot !== false &&
            str_starts_with(strtolower($candidate), strtolower($allowedRoot . DIRECTORY_SEPARATOR))) {
            return $candidate;
        }
    }

    return null;
}

function previewDocumentExtension(string $mimeType): string
{
    return [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png'
    ][$mimeType];
}

function previewSubmissionChangedFields(
    PDO $pdo,
    array $formData,
    int $employeeId,
    int $submissionId,
    string $status,
    $approvedFieldsJson
): array {
    $changedFields = $formData['submission']['changed_fields'] ?? null;
    if (is_array($changedFields)) {
        return array_values(array_unique(array_filter($changedFields, 'is_string')));
    }

    if ($status === 'approved' && is_string($approvedFieldsJson)) {
        $approvedFields = json_decode($approvedFieldsJson, true, 512, JSON_THROW_ON_ERROR);
        return is_array($approvedFields)
            ? array_values(array_unique(array_filter($approvedFields, 'is_string')))
            : [];
    }

    return stageSubmissionRows($pdo, $employeeId, $submissionId, $formData, false);
}
