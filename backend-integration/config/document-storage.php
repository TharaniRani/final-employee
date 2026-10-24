<?php
/**
 * Shared path rules for employee-uploaded documents.
 */

function submissionDocumentCategory(string $documentType): string
{
    $categories = [
        'aadhaar' => 'aadhar',
        'aadhar' => 'aadhar',
        'pan' => 'pan',
        'resume' => 'resume',
        'photo' => 'photo',
        'signature' => 'signature',
        'bed' => 'bed',
        'driving-license' => 'driving-license',
        'police-verification' => 'police-verification',
        'education_certificate' => 'education-certificates',
        'experience_letter' => 'experience-letters',
        'experience_payslip' => 'experience-payslips',
        'other_document' => 'other-documents'
    ];

    return $categories[strtolower(trim($documentType))] ?? 'other-documents';
}

function submissionEmployeeDocumentFolder(string $documentType): string
{
    $folders = [
        'aadhaar' => 'aadhar',
        'aadhar' => 'aadhar',
        'pan' => 'pan',
        'photo' => 'photo',
        'signature' => 'sign',
        'staff_sign' => 'sign',
        'resume' => 'resume',
        'education_certificate' => 'certificate_path',
        'bed' => 'pg_degree_doc',
        'driving' => 'driving',
        'driving-license' => 'driving',
        'police-verification' => 'others',
        'experience_letter' => 'certificate_path',
        'experience_payslip' => 'others',
        'experience_pay_slip' => 'others',
        'other_document' => 'others',
        'ccs' => 'ccs'
    ];

    $type = strtolower(trim($documentType));
    if (!isset($folders[$type])) {
        throw new DomainException('This document type is not supported.');
    }

    return $folders[$type];
}

function submissionDocumentCategories(): array
{
    return array_values(array_unique([
        'aadhar',
        'pan',
        'resume',
        'photo',
        'signature',
        'bed',
        'driving-license',
        'police-verification',
        'education-certificates',
        'experience-letters',
        'experience-payslips',
        'other-documents'
    ]));
}

function submissionTemporaryDocumentRelativePath(
    int $employeeId,
    string $documentType,
    string $storedName
): string {
    if ($employeeId < 1 || $storedName === '' || basename($storedName) !== $storedName ||
        str_contains($storedName, '\\') || str_contains($storedName, "\0")) {
        throw new DomainException('A stored document path is invalid. Please contact HR.');
    }

    return 'employees_temp_documents/' . submissionEmployeeDocumentFolder($documentType) . '/' .
        $employeeId . '/' . $storedName;
}

function submissionPermanentDocumentRelativePath(
    int $employeeId,
    string $documentType,
    string $storedName
): string {
    if ($employeeId < 1 || $storedName === '' || basename($storedName) !== $storedName ||
        str_contains($storedName, '\\') || str_contains($storedName, "\0")) {
        throw new DomainException('A stored document path is invalid. Please contact HR.');
    }

    return 'assets/requests/employees/' .
        submissionEmployeeDocumentFolder($documentType) . '/' . $storedName;
}

function submissionPromoteDocumentPath(
    string $projectRoot,
    int $employeeId,
    string $documentType,
    string $relativePath
): array {
    $relativePath = str_replace('\\', '/', trim($relativePath));
    $folder = submissionEmployeeDocumentFolder($documentType);
    if (!str_starts_with($relativePath, 'employees_temp_documents/')) {
        return ['path' => $relativePath, 'temporary_path' => null, 'permanent_path' => null];
    }
    $expectedPrefix = 'employees_temp_documents/' . $folder . '/' . $employeeId . '/';
    if (!str_starts_with($relativePath, $expectedPrefix)) {
        throw new DomainException('The staged document does not match its approved document field.');
    }

    $storedName = substr($relativePath, strlen($expectedPrefix));
    if ($storedName === '' || basename($storedName) !== $storedName ||
        !preg_match('/^' . preg_quote((string) $employeeId, '/') . '_[a-z0-9-]+_[a-f0-9]{32}\.(pdf|jpg|png)$/', $storedName)) {
        throw new DomainException('The staged document name is invalid.');
    }

    $temporaryRelativePath = submissionTemporaryDocumentRelativePath($employeeId, $documentType, $storedName);
    $permanentRelativePath = submissionPermanentDocumentRelativePath($employeeId, $documentType, $storedName);
    $temporaryAbsolutePath = $projectRoot . DIRECTORY_SEPARATOR .
        str_replace('/', DIRECTORY_SEPARATOR, $temporaryRelativePath);
    $permanentAbsolutePath = $projectRoot . DIRECTORY_SEPARATOR .
        str_replace('/', DIRECTORY_SEPARATOR, $permanentRelativePath);
    $temporaryStorageRoot = realpath($projectRoot . DIRECTORY_SEPARATOR . 'employees_temp_documents');
    $temporaryRoot = realpath(dirname($temporaryAbsolutePath));
    $candidate = realpath($temporaryAbsolutePath);
    if ($temporaryStorageRoot === false || $temporaryRoot === false || $candidate === false ||
        !str_starts_with(strtolower($temporaryRoot), strtolower($temporaryStorageRoot . DIRECTORY_SEPARATOR)) ||
        !is_file($candidate) ||
        !str_starts_with(strtolower($candidate), strtolower($temporaryRoot . DIRECTORY_SEPARATOR))) {
        throw new DomainException('The staged document is unavailable.');
    }

    $permanentDirectory = dirname($permanentAbsolutePath);
    if (!is_dir($permanentDirectory) &&
        !mkdir($permanentDirectory, 0755, true) && !is_dir($permanentDirectory)) {
        throw new RuntimeException('Unable to create the permanent document folder.');
    }
    $permanentRoot = realpath(
        $projectRoot . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR .
        'requests' . DIRECTORY_SEPARATOR . 'employees'
    );
    $resolvedPermanentDirectory = realpath($permanentDirectory);
    if ($permanentRoot === false || $resolvedPermanentDirectory === false ||
        !str_starts_with(strtolower($resolvedPermanentDirectory), strtolower($permanentRoot . DIRECTORY_SEPARATOR))) {
        throw new DomainException('The permanent document folder is unavailable.');
    }
    $permanentAbsolutePath = $resolvedPermanentDirectory . DIRECTORY_SEPARATOR . $storedName;

    $destination = @fopen($permanentAbsolutePath, 'xb');
    if ($destination === false) {
        throw new DomainException('A document with this unique filename already exists.');
    }
    $source = fopen($candidate, 'rb');
    $copied = $source === false ? false : stream_copy_to_stream($source, $destination);
    if (is_resource($source)) {
        fclose($source);
    }
    fclose($destination);
    if ($copied === false || $copied !== filesize($candidate)) {
        @unlink($permanentAbsolutePath);
        throw new RuntimeException('Unable to move the approved document.');
    }

    return [
        'path' => $permanentRelativePath,
        'temporary_path' => $temporaryRelativePath,
        'permanent_path' => $permanentRelativePath
    ];
}

function submissionDocumentRelativePath(int $employeeId, string $documentType, string $storedName): string
{
    if ($employeeId < 1 || $storedName === '' || basename($storedName) !== $storedName ||
        str_contains($storedName, '\\') || str_contains($storedName, "\0")) {
        throw new DomainException('A stored document path is invalid. Please contact HR.');
    }

    return 'uploads/documents/' . submissionDocumentCategory($documentType) . '/' .
        $employeeId . '/' . $storedName;
}

function submissionLegacyDocumentRelativePath(int $employeeId, string $storedName): string
{
    if ($employeeId < 1 || $storedName === '' || basename($storedName) !== $storedName ||
        str_contains($storedName, '\\') || str_contains($storedName, "\0")) {
        throw new DomainException('A stored document path is invalid. Please contact HR.');
    }

    return 'uploads/documents/' . $employeeId . '/' . $storedName;
}

function submissionDocumentAbsolutePath(string $projectRoot, int $employeeId, string $documentType, string $storedName): string
{
    $relativePath = submissionDocumentRelativePath($employeeId, $documentType, $storedName);

    return $projectRoot . DIRECTORY_SEPARATOR .
        str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
}

function resolveExistingEmployeeDocument(
    string $projectRoot,
    string $documentType,
    string $storedPath
): ?string {
    $legacyRoot = realpath(
        $projectRoot . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR .
        'requests' . DIRECTORY_SEPARATOR . 'employees'
    );
    $uploadRoot = realpath(
        $projectRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'documents'
    );
    if ($legacyRoot === false && $uploadRoot === false) {
        return null;
    }

    $normalizedPath = str_replace('\\', '/', trim($storedPath));
    if ($normalizedPath === '' || str_contains($normalizedPath, "\0")) {
        return null;
    }

    $permanentFolder = submissionEmployeeDocumentFolder($documentType);
    $requiresTypeFolder = false;
    $requiresUploadRoot = false;
    if (str_starts_with($normalizedPath, 'assets/requests/employees/')) {
        $candidatePath = $projectRoot . DIRECTORY_SEPARATOR .
            str_replace('/', DIRECTORY_SEPARATOR, $normalizedPath);
        if ($documentType === 'education_certificate' &&
            str_starts_with($normalizedPath, 'assets/requests/employees/certificates/')) {
            $alternatePath = $legacyRoot . DIRECTORY_SEPARATOR . 'certificate_path' . DIRECTORY_SEPARATOR .
                substr($normalizedPath, strlen('assets/requests/employees/certificates/'));
            if (!is_file($candidatePath) && is_file($alternatePath)) {
                $candidatePath = $alternatePath;
            }
        }
    } elseif (str_starts_with($normalizedPath, 'uploads/documents/')) {
        if (in_array('..', explode('/', $normalizedPath), true)) {
            return null;
        }
        $requiresUploadRoot = true;
        $candidatePath = $projectRoot . DIRECTORY_SEPARATOR .
            str_replace('/', DIRECTORY_SEPARATOR, $normalizedPath);
    } elseif (preg_match('/^[A-Za-z]:[\/\\\\]/', $normalizedPath) ||
        str_starts_with($normalizedPath, '/')) {
        $candidatePath = $normalizedPath;
    } elseif (basename($normalizedPath) === $normalizedPath) {
        $relativePath = submissionPermanentDocumentRelativePath(1, $documentType, $normalizedPath);
        $candidatePath = $projectRoot . DIRECTORY_SEPARATOR .
            str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    } elseif (str_starts_with($normalizedPath, $permanentFolder . '/')) {
        if (in_array('..', explode('/', $normalizedPath), true)) {
            return null;
        }
        $requiresTypeFolder = true;
        $candidatePath = $legacyRoot . DIRECTORY_SEPARATOR .
            str_replace('/', DIRECTORY_SEPARATOR, $normalizedPath);
    } else {
        return null;
    }

    $candidate = realpath($candidatePath);
    if ($candidate === false || !is_file($candidate)) {
        return null;
    }
    $allowedRoots = array_filter([$legacyRoot, $uploadRoot]);
    $isWithinAllowedRoot = false;
    foreach ($allowedRoots as $allowedRoot) {
        if (str_starts_with(strtolower($candidate), strtolower($allowedRoot . DIRECTORY_SEPARATOR))) {
            $isWithinAllowedRoot = true;
            break;
        }
    }
    if (!$isWithinAllowedRoot) {
        return null;
    }
    if ($requiresTypeFolder) {
        $typeRoot = realpath($legacyRoot . DIRECTORY_SEPARATOR . $permanentFolder);
        if ($typeRoot === false ||
            !str_starts_with(strtolower($candidate), strtolower($typeRoot . DIRECTORY_SEPARATOR))) {
            return null;
        }
    }
    if ($requiresUploadRoot &&
        ($uploadRoot === false ||
            !str_starts_with(strtolower($candidate), strtolower($uploadRoot . DIRECTORY_SEPARATOR)))) {
        return null;
    }

    return $candidate;
}
