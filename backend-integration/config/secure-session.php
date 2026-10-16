<?php
function startSecureSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}

function isLocalOtpRequest(): bool
{
    $remoteAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $hostHeader = (string) ($_SERVER['HTTP_HOST'] ?? '');
    $host = strtolower((string) parse_url('http://' . $hostHeader, PHP_URL_HOST));

    $isLoopbackAddress = $remoteAddress === '::1'
        || str_starts_with($remoteAddress, '127.')
        || $remoteAddress === '0:0:0:0:0:0:0:1';
    $isLocalHost = $host === 'localhost'
        || $host === '127.0.0.1'
        || $host === '::1'
        || $host === '[::1]'
        || str_ends_with($host, '.localhost');

    return $isLoopbackAddress && $isLocalHost;
}
