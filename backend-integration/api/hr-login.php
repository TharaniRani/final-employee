<?php
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, private');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/secure-session.php';
require_once __DIR__ . '/../config/admin-auth.php';
startSecureSession();

function adminAuthRespond(int $status, array $body): void
{
    http_response_code($status);
    echo json_encode($body);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    adminAuthRespond(405, ['success' => false, 'message' => 'Method not allowed']);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input) || !hash_equals($_SESSION['csrf_token'] ?? '', (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))) {
    adminAuthRespond(419, ['success' => false, 'message' => 'Refresh the page and try again.']);
}

if (($input['action'] ?? '') === 'logout') {
    unset(
        $_SESSION['admin_id'],
        $_SESSION['admin_name'],
        $_SESSION['admin_email'],
        $_SESSION['admin_role'],
        $_SESSION['admin_unit_id'],
        $_SESSION['admin_unit_name'],
        $_SESSION['admin_login_attempts'],
        $_SESSION['admin_login_blocked_until']
    );
    session_regenerate_id(true);
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    adminAuthRespond(200, ['success' => true, 'csrf_token' => $_SESSION['csrf_token']]);
}

if (($input['action'] ?? '') !== 'login') {
    adminAuthRespond(422, ['success' => false, 'message' => 'Invalid sign-in request.']);
}

if (($_SESSION['admin_login_blocked_until'] ?? 0) > time()) {
    adminAuthRespond(429, ['success' => false, 'message' => 'Too many sign-in attempts. Please wait 15 minutes and try again.']);
}

$email = strtolower(trim((string) ($input['email'] ?? '')));
$password = (string) ($input['password'] ?? '');
if ($email === '' || strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL) ||
    $password === '' || strlen($password) > 1024) {
    adminAuthFail();
}

try {
    $pdo = getAdminDatabaseConnection();
    $stmt = $pdo->prepare("
        SELECT a.admin_id, a.role_id, a.unit_id, TRIM(a.email) AS email, a.password,
               r.role_name, u.unit_name
        FROM admins a
        INNER JOIN user_roles r ON r.role_id = a.role_id
        LEFT JOIN units u ON u.id = a.unit_id
        WHERE LOWER(TRIM(a.email)) = ? AND a.is_active = 1
        ORDER BY a.admin_id
        LIMIT 2
    ");
    $stmt->execute([$email]);
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($accounts) !== 1) {
        adminAuthFail();
    }
    $account = $accounts[0];
    if (!in_array($account['role_name'], ADMIN_ALLOWED_ROLES, true)) adminAuthFail();

    $storedPassword = (string) $account['password'];
    $passwordInfo = password_get_info($storedPassword);
    $isPasswordHash = ($passwordInfo['algoName'] ?? 'unknown') !== 'unknown';
    $passwordMatches = $isPasswordHash
        ? password_verify($password, $storedPassword)
        : hash_equals($storedPassword, $password);
    if (!$passwordMatches) {
        adminAuthFail();
    }

    if (!$isPasswordHash || password_needs_rehash($storedPassword, PASSWORD_DEFAULT)) {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $update = $pdo->prepare('UPDATE admins SET password = ?, updated_at = NOW() WHERE admin_id = ?');
        $update->execute([$passwordHash, $account['admin_id']]);
        if ($update->rowCount() !== 1) {
            throw new RuntimeException('Unable to securely update the administrator password.');
        }
    }

    if ($account['role_name'] === 'school_admin' &&
        ($account['unit_id'] === null || $account['unit_name'] === null || trim((string) $account['unit_name']) === '')) {
        error_log('Active School Admin account has no valid assigned unit: admin_id ' . (int) $account['admin_id']);
        adminAuthRespond(403, ['success' => false, 'message' => 'This School Admin account has no assigned unit. Contact HR.']);
    }
    if ($account['role_name'] === 'school_admin' &&
        !adminUnitEmploymentNames([
            'role' => $account['role_name'],
            'unit_name' => $account['unit_name']
        ])) {
        error_log('Active School Admin account has no employee-unit mapping: admin_id ' . (int) $account['admin_id']);
        adminAuthRespond(403, ['success' => false, 'message' => 'This School Admin account has no employee-unit access mapping. Contact HR.']);
    }

    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int) $account['admin_id'];
    $_SESSION['admin_name'] = (string) $account['email'];
    $_SESSION['admin_email'] = strtolower(trim((string) $account['email']));
    $_SESSION['admin_role'] = (string) $account['role_name'];
    $_SESSION['admin_unit_id'] = $account['unit_id'] === null ? null : (int) $account['unit_id'];
    $_SESSION['admin_unit_name'] = $account['unit_name'] === null ? '' : trim((string) $account['unit_name']);
    $_SESSION['admin_login_attempts'] = 0;
    unset($_SESSION['admin_login_blocked_until']);
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    adminAuthRespond(200, [
        'success' => true,
        'role' => $_SESSION['admin_role'],
        'csrf_token' => $_SESSION['csrf_token']
    ]);
} catch (Throwable $e) {
    error_log('Admin sign-in failed: ' . $e->getMessage());
    adminAuthRespond(503, ['success' => false, 'message' => 'Admin sign-in is temporarily unavailable. Please try again.']);
}

function adminAuthFail(): void
{
    $attempts = (int) ($_SESSION['admin_login_attempts'] ?? 0) + 1;
    $_SESSION['admin_login_attempts'] = $attempts;
    if ($attempts >= 5) {
        $_SESSION['admin_login_blocked_until'] = time() + 900;
        $_SESSION['admin_login_attempts'] = 0;
    }
    adminAuthRespond(401, ['success' => false, 'message' => 'Email or password is incorrect.']);
}
