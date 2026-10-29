<?php

require_once __DIR__ . '/secure-session.php';

function getOtpDeliveryMode(): string
{
    $configuredMode = strtolower(trim((string) (getenv('OTP_DELIVERY_MODE') ?: '')));

    if ($configuredMode === '') {
        return isLocalOtpRequest() ? 'local' : 'disabled';
    }

    if (!in_array($configuredMode, ['local', 'whatsapp'], true)) {
        throw new RuntimeException('OTP_DELIVERY_MODE must be local or whatsapp');
    }

    if ($configuredMode === 'local' && !isLocalOtpRequest()) {
        return 'disabled';
    }

    return $configuredMode;
}

function sendWhatsappOtp(string $mobile, string $otp): int
{
    $apiKey = trim((string) (getenv('OMNI_PORTAL_API_KEY') ?: ''));
    if ($apiKey === '') {
        throw new RuntimeException('OMNI_PORTAL_API_KEY is not configured');
    }

    if (!function_exists('curl_init')) {
        throw new RuntimeException('The PHP cURL extension is required for WhatsApp OTP delivery');
    }

    $apiUrl = trim((string) (getenv('OMNI_WHATSAPP_API_URL') ?: 'https://wb.omni.tatatelebusiness.com/whatsapp-cloud/messages'));
    if (filter_var($apiUrl, FILTER_VALIDATE_URL) === false || strtolower((string) parse_url($apiUrl, PHP_URL_SCHEME)) !== 'https') {
        throw new RuntimeException('OMNI_WHATSAPP_API_URL must be a valid HTTPS URL');
    }

    $templateName = trim((string) (getenv('OMNI_WHATSAPP_OTP_TEMPLATE') ?: 'otp_service'));
    $language = trim((string) (getenv('OMNI_WHATSAPP_OTP_LANGUAGE') ?: 'en'));
    if ($templateName === '' || $language === '') {
        throw new RuntimeException('WhatsApp OTP template settings cannot be empty');
    }

    $payload = [
        'to' => '+91' . $mobile,
        'type' => 'template',
        'template' => [
            'name' => $templateName,
            'language' => ['code' => $language],
            'components' => [
                [
                    'type' => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => $otp]
                    ]
                ],
                [
                    'type' => 'button',
                    'sub_type' => 'url',
                    'index' => 0,
                    'parameters' => [
                        ['type' => 'text', 'text' => $otp]
                    ]
                ]
            ]
        ]
    ];

    $requestBody = json_encode($payload);
    if ($requestBody === false) {
        throw new RuntimeException('Unable to encode the WhatsApp OTP request');
    }

    $curl = curl_init($apiUrl);
    if ($curl === false) {
        throw new RuntimeException('Unable to initialize the WhatsApp OTP request');
    }

    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $requestBody,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10
    ]);

    $responseBody = curl_exec($curl);
    $curlError = curl_error($curl);
    $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($responseBody === false) {
        throw new RuntimeException('WhatsApp OTP request failed: ' . $curlError);
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        throw new RuntimeException('WhatsApp OTP provider returned HTTP ' . $httpCode);
    }

    return $httpCode;
}
