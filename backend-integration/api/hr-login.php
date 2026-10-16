<?php
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, private');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/secure-session.php';
startSecureSession();

function hrAuthRespond(int $status, array $body): void
{
    http_response_code($status);
    echo json_encode($body);
    exit;
}

if (!isLocalOtpRequest() && (getenv('HR_AUTH_LOCAL_ONLY') ?: 'true') !== 'false') {
    hrAuthRespond(403, ['success' => false, 'message' => 'HR sign-in is available only on this local development server.']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    hrAuthRespond(405, ['success' => false, 'message' => 'Method not allowed']);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input) || !hash_equals($_SESSION['csrf_token'] ?? '', (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))) {
    hrAuthRespond(419, ['success' => false, 'message' => 'Refresh the page and try again.']);
}

$action = $input['action'] ?? '';
if ($action === 'logout') {
    unset($_SESSION['user_role'], $_SESSION['hr_user_id'], $_SESSION['hr_user_name'], $_SESSION['hr_login_attempts'], $_SESSION['hr_login_blocked_until']);
    session_regenerate_id(true);
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    hrAuthRespond(200, ['success' => true, 'csrf_token' => $_SESSION['csrf_token']]);
}

if ($action !== 'login') {
    hrAuthRespond(422, ['success' => false, 'message' => 'Invalid sign-in request.']);
}

if (($_SESSION['hr_login_blocked_until'] ?? 0) > time()) {
    hrAuthRespond(429, ['success' => false, 'message' => 'Too many sign-in attempts. Please wait 15 minutes and try again.']);
}

$expectedUsername = trim((string) (getenv('HR_LOGIN_USERNAME') ?: ''));
$passwordHash = trim((string) (getenv('HR_LOGIN_PASSWORD_HASH') ?: ''));
$reviewerId = trim((string) (getenv('HR_REVIEWER_ID') ?: ''));
$reviewerName = trim((string) (getenv('HR_REVIEWER_NAME') ?: ''));
if ($expectedUsername === '' || $passwordHash === '' || $reviewerName === '' ||
    !ctype_digit($reviewerId) || (int) $reviewerId < 1) {
    error_log('HR login is unavailable because the required HR_LOGIN_USERNAME, HR_LOGIN_PASSWORD_HASH, HR_REVIEWER_ID, or HR_REVIEWER_NAME configuration is missing.');
    hrAuthRespond(503, ['success' => false, 'message' => 'HR sign-in is not configured. See the HR setup instructions in README.md.']);
}

$username = trim((string) ($input['username'] ?? ''));
$password = (string) ($input['password'] ?? '');
if (strlen($username) > 100 || strlen($password) > 1024 ||
    !hash_equals($expectedUsername, $username) || !password_verify($password, $passwordHash)) {
    $attempts = (int) ($_SESSION['hr_login_attempts'] ?? 0) + 1;
    $_SESSION['hr_login_attempts'] = $attempts;
    if ($attempts >= 5) {
        $_SESSION['hr_login_blocked_until'] = time() + 900;
        $_SESSION['hr_login_attempts'] = 0;
    }
    hrAuthRespond(401, ['success' => false, 'message' => 'Username or password is incorrect.']);
}

session_regenerate_id(true);
$_SESSION['user_role'] = 'HR';
$_SESSION['hr_user_id'] = (int) $reviewerId;
$_SESSION['hr_user_name'] = $reviewerName;
$_SESSION['hr_login_attempts'] = 0;
unset($_SESSION['hr_login_blocked_until']);
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

hrAuthRespond(200, [
    'success' => true,
    'reviewer_name' => $reviewerName,
    'csrf_token' => $_SESSION['csrf_token']
]);
