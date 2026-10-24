<?php
/**
 * Get Units Endpoint
 * Returns the configured organizational unit choices.
 */
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

session_start();

// Verify CSRF token
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!$csrfToken || $csrfToken !== ($_SESSION['csrf_token'] ?? '')) {
    http_response_code(419);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$units = [
    'Lady Andal House of Children, Chetpet',
    'Lady Andal Open School - NIOS, T. Nagar',
    'Lady Andal Open School - OS, T. Nagar',
    'Lady Andal School - IB, Chetpet',
    'Prem Vihar, Chetpet',
    'School of Sound and Music, Chetpet',
    'Shanthi Sadan, Chetpet',
    'Sir & Lady M. Venkatasubba Rao Hr., Sec., School, Tambaram',
    'Sir Mutha School - CBSE, Chetpet',
    'Sir Mutha Venkatasubba Rao Concert Hall, Chetpet',
    'The Madras Seva Sadan - Head Office, Chetpet',
    'The Madras Seva Sadan Hr., Sec., School, Chetpet',
    'The Madras Seva Sadan Hr., Sec., School, Tambaram',
    'The Madras Seva Sadan Nursery and Primary School, Tambaram'
];

echo json_encode([
    'success' => true,
    'units' => $units
]);