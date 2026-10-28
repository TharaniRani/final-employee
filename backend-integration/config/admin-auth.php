<?php
const ADMIN_HR_ROLES = ['hr_head', 'hr_processor', 'hr_executive'];
const ADMIN_ALLOWED_ROLES = ['hr_head', 'hr_processor', 'hr_executive', 'school_admin'];

function currentAdminUser(): ?array
{
    $role = $_SESSION['admin_role'] ?? '';
    $adminId = $_SESSION['admin_id'] ?? null;
    $name = trim((string) ($_SESSION['admin_name'] ?? ''));
    if (!is_string($role) || !in_array($role, ADMIN_ALLOWED_ROLES, true) ||
        !is_scalar($adminId) || !ctype_digit((string) $adminId) || (int) $adminId < 1 ||
        $name === '') {
        return null;
    }

    $unitId = $_SESSION['admin_unit_id'] ?? null;
    $unitName = trim((string) ($_SESSION['admin_unit_name'] ?? ''));
    if ($role === 'school_admin' &&
        (!is_scalar($unitId) || !ctype_digit((string) $unitId) || (int) $unitId < 1 || $unitName === '')) {
        return null;
    }

    return [
        'id' => (int) $adminId,
        'name' => $name,
        'role' => $role,
        'unit_id' => $unitId === null ? null : (int) $unitId,
        'unit_name' => $unitName
    ];
}

function adminCanAccessAllUnits(array $admin): bool
{
    return in_array($admin['role'], ADMIN_HR_ROLES, true);
}

function adminUnitEmploymentNames(array $admin): array
{
    if ($admin['role'] !== 'school_admin') {
        return [];
    }

    $unitMappings = [
        'Lady Andal School - IB, Chetpet' => ['Lady Andal IB'],
        'Lady Andal House of Children, Chetpet' => ['Lady Andal House of Children Chetpet'],
        'Lady Andal Open School - NIOS, T. Nagar' => ['LAOS - NIOS'],
        'Lady Andal Open School - OS, T. Nagar' => ['LAOS - OS'],
        'Prem Vihar, Chetpet' => ['Prem Vihar'],
        'School of Sound and Music, Chetpet' => ['School of Sound and Music, Chetpet'],
        'Shanthi Sadan, Chetpet' => ['Shanthi Sadan'],
        'Sir & Lady M. Venkatasubba Rao Hr., Sec., School, Tambaram' => ['Sir & LADY M Tambaram'],
        'Sir Mutha School - CBSE, Chetpet' => ['Sir Mutha School'],
        'Sir Mutha Venkatasubba Rao Concert Hall, Chetpet' => ['SMVRCH'],
        'The Madras Seva Sadan - Head Office, Chetpet' => ['MSS Central'],
        'The Madras Seva Sadan Hr., Sec., School, Chetpet' => ['MSS Chetpet'],
        'The Madras Seva Sadan Hr., Sec., School, Tambaram' => ['MSS Tambaram'],
        'The Madras Seva Sadan Nursery and Primary School, Tambaram' => ['MSS Tambaram Primary School']
    ];
    $unitName = trim((string) ($admin['unit_name'] ?? ''));

    return $unitMappings[$unitName] ?? [];
}

function submissionIsInAdminScope(PDO $pdo, int $submissionId, array $admin): bool
{
    if (adminCanAccessAllUnits($admin)) {
        return true;
    }

    $unitNames = adminUnitEmploymentNames($admin);
    if (!$unitNames) {
        return false;
    }
    $unitPlaceholders = implode(', ', array_fill(0, count($unitNames), '?'));
    $stmt = $pdo->prepare("
        SELECT 1
        FROM employee_form_submissions s
        INNER JOIN employee_employment em
            ON em.employment_id = (
                SELECT latest.employment_id
                FROM employee_employment latest
                WHERE latest.employee_id = s.employee_id
                ORDER BY latest.employment_id DESC
                LIMIT 1
            )
        WHERE s.id = ? AND TRIM(em.unit) IN ({$unitPlaceholders})
        LIMIT 1
    ");
    $stmt->execute([$submissionId, ...$unitNames]);

    return $stmt->fetchColumn() !== false;
}

function employeeIsInAdminScope(PDO $pdo, int $employeeId, array $admin): bool
{
    if (adminCanAccessAllUnits($admin)) {
        return true;
    }

    $unitNames = adminUnitEmploymentNames($admin);
    if (!$unitNames) {
        return false;
    }
    $unitPlaceholders = implode(', ', array_fill(0, count($unitNames), '?'));
    $stmt = $pdo->prepare("
        SELECT 1
        FROM employee_employment latest
        WHERE latest.employee_id = ?
          AND TRIM(latest.unit) IN ({$unitPlaceholders})
          AND latest.employment_id = (
              SELECT MAX(current_employment.employment_id)
              FROM employee_employment current_employment
              WHERE current_employment.employee_id = latest.employee_id
          )
        LIMIT 1
    ");
    $stmt->execute([$employeeId, ...$unitNames]);

    return $stmt->fetchColumn() !== false;
}
