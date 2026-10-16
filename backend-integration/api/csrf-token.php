<?php
/**
 * CSRF Token Endpoint
 * Returns a fresh CSRF token for AJAX requests
 */
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config/secure-session.php';
startSecureSession();

// Generate new CSRF token
$csrfToken = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrfToken;

echo json_encode([
    'success' => true,
    'csrf_token' => $csrfToken
]);