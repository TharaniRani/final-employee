<?php
/**
 * Verify OTP Endpoint
 * Validates OTP and establishes the authenticated employee session
 */
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/hrms-repository.php';
require_once __DIR__ . '/../config/submission-access.php';
require_once __DIR__ . '/../config/secure-session.php';

startSecureSession();

// Verify CSRF token
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!$csrfToken || $csrfToken !== ($_SESSION['csrf_token'] ?? '')) {
    http_response_code(419);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$mobile = is_array($input) ? ($input['mobile'] ?? null) : null;
$otp = is_array($input) ? ($input['otp'] ?? null) : null;

if (!is_string($mobile) || !preg_match('/^[6-9]\d{9}$/', $mobile)
    || !is_string($otp) || !preg_match('/^\d{6}$/', $otp)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Enter a valid mobile number and 6-digit OTP.']);
    exit;
}

// Verify mobile matches pending session
if (($_SESSION['pending_mobile'] ?? '') !== $mobile
    || (int) ($_SESSION['pending_employee_id'] ?? 0) < 1) {
    echo json_encode(['success' => false, 'message' => 'Invalid session. Please start over.']);
    exit;
}

$deliveryMode = $_SESSION['pending_otp_delivery_mode'] ?? '';
if (!in_array($deliveryMode, ['local', 'whatsapp'], true)
    || ($deliveryMode === 'local' && !isLocalOtpRequest())) {
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'message' => 'OTP verification session is unavailable. Please request a new code.'
    ]);
    exit;
}

try {
    $pdo = getDatabaseConnection();
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("
        SELECT employee_id, otp, otp_hash, attempts, status,
               (expires_at > NOW()) AS is_unexpired
        FROM otp_verifications
        WHERE mobile = ?
        FOR UPDATE
    ");
    $stmt->execute([$mobile]);
    $otpRecord = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$otpRecord || (int) $otpRecord['employee_id'] !== (int) $_SESSION['pending_employee_id']) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No OTP request exists for this mobile number. Request a new OTP.']);
        exit;
    }

    if ($otpRecord['status'] === 'verified') {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'This OTP has already been used. Request a new OTP.']);
        exit;
    }

    if ($otpRecord['status'] === 'locked' || (int) $otpRecord['attempts'] >= 5) {
        $pdo->rollBack();
        http_response_code(429);
        echo json_encode(['success' => false, 'message' => 'Maximum OTP attempts exceeded. Request a new OTP.']);
        exit;
    }

    if (!(bool) $otpRecord['is_unexpired']) {
        $stmt = $pdo->prepare("UPDATE otp_verifications SET status = 'expired' WHERE mobile = ? AND status = 'pending'");
        $stmt->execute([$mobile]);
        $pdo->commit();
        http_response_code(410);
        echo json_encode(['success' => false, 'message' => 'This OTP has expired. Request a new OTP.']);
        exit;
    }

    $otpMatches = $otpRecord['otp'] !== null
        ? hash_equals((string) $otpRecord['otp'], $otp)
        : password_verify($otp, (string) $otpRecord['otp_hash']);
    if ($otpRecord['status'] !== 'pending' || !$otpMatches) {
        $attempts = (int) $otpRecord['attempts'] + 1;
        $status = $attempts >= 5 ? 'locked' : 'pending';
        $stmt = $pdo->prepare("UPDATE otp_verifications SET attempts = ?, status = ? WHERE mobile = ?");
        $stmt->execute([$attempts, $status, $mobile]);
        $pdo->commit();

        if ($status === 'locked') {
            http_response_code(429);
            echo json_encode(['success' => false, 'message' => 'Maximum OTP attempts exceeded. Request a new OTP.']);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid OTP. ' . (5 - $attempts) . ' attempts remaining.']);
        }
        exit;
    }

    $employeeId = (int) $otpRecord['employee_id'];
    $existingSubmission = getEmployeeSubmissionLockout($pdo, $employeeId);
    if ($existingSubmission !== null) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => employeeSubmissionLockoutMessage($existingSubmission)
        ]);
        exit;
    }

    $stmt = $pdo->prepare("
        UPDATE otp_verifications
        SET status = 'verified', used_at = NOW()
        WHERE mobile = ? AND employee_id = ? AND status = 'pending' AND expires_at > NOW()
    ");
    $stmt->execute([$mobile, $employeeId]);
    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException('OTP state changed during verification');
    }
    $pdo->commit();

    if (!session_regenerate_id(true)) {
        throw new RuntimeException('Unable to renew the authenticated session');
    }

    unset($_SESSION['pending_mobile']);
    unset($_SESSION['pending_employee_id']);
    unset($_SESSION['pending_otp_delivery_mode']);
    $_SESSION['verified_employee_id'] = $employeeId;
    $_SESSION['verified_mobile'] = $mobile;
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    echo json_encode([
        'success' => true,
        'message' => 'OTP verified successfully'
    ]);

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Database error in verify-otp: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error in verify-otp: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
