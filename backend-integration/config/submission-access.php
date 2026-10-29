<?php

function getEmployeeSubmissionLockout(PDO $pdo, int $employeeId): ?array
{
    $stmt = $pdo->prepare("
        SELECT status, reference_number
        FROM employee_form_submissions
        WHERE employee_id = ? AND status IN ('submitted', 'pending', 'approved')
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$employeeId]);
    $submission = $stmt->fetch(PDO::FETCH_ASSOC);

    return $submission ?: null;
}

function employeeSubmissionLockoutMessage(array $submission): string
{
    return $submission['status'] === 'approved'
        ? 'You have already submitted this form, and it has been approved. You cannot edit it.'
        : 'You have already submitted this form. It is waiting for HR approval, so you cannot edit it.';
}
