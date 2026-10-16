<?php
/**
 * Verify Mobile Number Endpoint
 * Checks the registered mobile number and creates a local-only OTP
 */
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/hrms-repository.php';
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

// Validate mobile format (Indian mobile: 10 digits starting with 6-9)
if (!is_string($mobile) || !preg_match('/^[6-9]\d{9}$/', $mobile)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid mobile number format']);
    exit;
}

try {
    if (!isLocalOtpRequest()) {
        http_response_code(503);
        echo json_encode([
            'success' => false,
            'message' => 'SMS delivery is not configured. Local OTP verification is available only from this machine.'
        ]);
        exit;
    }

    // Connect to database
    $pdo = getDatabaseConnection();
    $otpExpiryMinutes = filter_var(getenv('OTP_EXPIRY_MINUTES') ?: '5', FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 60]
    ]);
    if ($otpExpiryMinutes === false) {
        throw new RuntimeException('OTP_EXPIRY_MINUTES must be between 1 and 60');
    }

    $employeeId = findHrmsEmployeeIdByMobile($pdo, $mobile);

    if ($employeeId === null) {
        echo json_encode(['success' => false, 'message' => 'This mobile number is not registered. Please contact HR.']);
        exit;
    }

    // Generate OTP (6 digits)
    $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("
        SELECT
            TIMESTAMPDIFF(SECOND, last_requested_at, NOW()) AS seconds_since_request,
            TIMESTAMPDIFF(SECOND, request_window_started_at, NOW()) AS seconds_in_window,
            requests_in_window
        FROM otp_verifications
        WHERE mobile = ?
        FOR UPDATE
    ");
    $stmt->execute([$mobile]);
    $requestState = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($requestState && $requestState['seconds_since_request'] !== null && (int) $requestState['seconds_since_request'] < 60) {
        $pdo->rollBack();
        http_response_code(429);
        echo json_encode(['success' => false, 'message' => 'Please wait before requesting another OTP.']);
        exit;
    }

    $windowExpired = !$requestState
        || $requestState['seconds_in_window'] === null
        || (int) $requestState['seconds_in_window'] >= 900;
    if (!$windowExpired && (int) $requestState['requests_in_window'] >= 3) {
        $pdo->rollBack();
        http_response_code(429);
        echo json_encode(['success' => false, 'message' => 'Too many OTP requests. Please try again in 15 minutes.']);
        exit;
    }

    if ($requestState) {
        $requestCount = $windowExpired ? 1 : ((int) $requestState['requests_in_window'] + 1);
        $stmt = $pdo->prepare("
            UPDATE otp_verifications
            SET otp = ?, otp_hash = NULL, expires_at = DATE_ADD(NOW(), INTERVAL ? MINUTE),
                employee_id = ?, attempts = 0, status = 'pending', used_at = NULL,
                created_at = NOW(), last_requested_at = NOW(),
                request_window_started_at = IF(?, NOW(), request_window_started_at),
                requests_in_window = ?
            WHERE mobile = ?
        ");
        $stmt->execute([$otp, $otpExpiryMinutes, $employeeId, $windowExpired ? 1 : 0, $requestCount, $mobile]);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO otp_verifications
                (mobile, otp, otp_hash, expires_at, employee_id, attempts, status, created_at,
                 last_requested_at, request_window_started_at, requests_in_window)
            VALUES (?, ?, NULL, DATE_ADD(NOW(), INTERVAL ? MINUTE), ?, 0, 'pending', NOW(), NOW(), NOW(), 1)
        ");
        $stmt->execute([$mobile, $otp, $otpExpiryMinutes, $employeeId]);
    }
    $pdo->commit();

    // Store employee ID in session for later verification
    $_SESSION['pending_mobile'] = $mobile;
    $_SESSION['pending_employee_id'] = $employeeId;

    echo json_encode([
        'success' => true,
        'message' => 'Local development OTP generated.',
        'otp' => $otp
    ]);

} catch (RuntimeException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Employee lookup error in verify-mobile: " . $e->getMessage());
    if ($e->getMessage() === 'The mobile number is associated with multiple employee records') {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'This mobile number matches multiple employee records. Please contact HR.']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Unable to generate a verification code. Please try again.']);
    }
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Database error in verify-mobile: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error in verify-mobile: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
