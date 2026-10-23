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
