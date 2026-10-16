<?php
/**
 * PIN Code Lookup Endpoint
 * Proxies requests to India Post API: https://api.postalpincode.in/pincode/{PINCODE}
 * Returns city (district), state, and country for a given Indian PIN code
 */
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get PIN code from query string
$pincode = $_GET['pincode'] ?? '';

if (empty($pincode)) {
    echo json_encode(['success' => false, 'message' => 'PIN code is required']);
    exit;
}

// Validate PIN code format (6 digits)
if (!preg_match('/^\d{6}$/', $pincode)) {
    echo json_encode(['success' => false, 'message' => 'Invalid PIN code format. Must be 6 digits.']);
    exit;
}

try {
    // Call India Post API
    $url = "https://api.postalpincode.in/pincode/{$pincode}";
    $context = stream_context_create([
        'http' => [
            'timeout' => 8,
            'user_agent' => 'HRMS-Form/1.0'
        ]
    ]);
    
    $response = @file_get_contents($url, false, $context);
    
    if ($response === false) {
        throw new Exception('Failed to connect to India Post API');
    }
    
    $data = json_decode($response, true);
    
    if (!$data || !isset($data[0]['Status'])) {
        throw new Exception('Invalid API response format');
    }
    
    if ($data[0]['Status'] !== 'Success' || empty($data[0]['PostOffice'])) {
        echo json_encode([
            'success' => false,
            'message' => $data[0]['Message'] ?? 'PIN code not found'
        ]);
        exit;
    }
    
    // Get the first post office (most relevant)
    // API returns array of post offices - use the first one
    $postOffice = $data[0]['PostOffice'][0];
    
    // Extract location data
    // District is the closest to "city" level in India Post API
    $district = $postOffice['District'] ?? '';
    $state = $postOffice['State'] ?? '';
    $country = 'India';
    
    if (empty($district) || empty($state)) {
        echo json_encode([
            'success' => false,
            'message' => 'Incomplete location data from API'
        ]);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'city' => $district,
            'state' => $state,
            'country' => $country,
            'pincode' => $pincode,
            'post_office' => $postOffice['Name'] ?? '',
            'division' => $postOffice['Division'] ?? '',
            'region' => $postOffice['Region'] ?? '',
            'circle' => $postOffice['Circle'] ?? ''
        ]
    ]);
    
} catch (Exception $e) {
    error_log("PIN code lookup error for {$pincode}: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Lookup failed. Please enter manually.']);
}