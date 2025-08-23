<?php

// Test xóa inventory trực tiếp
$variantId = 238; // ID cần xóa (variant còn lại)
$url = "http://localhost:8000/api/v1/inventory/{$variantId}";

// Get admin token first
$loginUrl = 'http://localhost:8000/api/v1/auth/admin/login';
$loginData = [
    'account_name' => 'admin',
    'password' => 'admin123'
];

$loginCh = curl_init();
curl_setopt($loginCh, CURLOPT_URL, $loginUrl);
curl_setopt($loginCh, CURLOPT_POST, true);
curl_setopt($loginCh, CURLOPT_POSTFIELDS, json_encode($loginData));
curl_setopt($loginCh, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($loginCh, CURLOPT_RETURNTRANSFER, true);

$loginResponse = curl_exec($loginCh);
$loginResult = json_decode($loginResponse, true);

echo "Login response: " . $loginResponse . "\n\n";

if (!$loginResult['success']) {
    echo "Login failed: " . $loginResponse . "\n";
    exit;
}

$token = $loginResult['data']['token'] ?? $loginResult['data']['access_token'] ?? '';
echo "Token: " . $token . "\n\n";

echo "Testing DELETE inventory variant ID: {$variantId}\n\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $token
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

echo "HTTP Code: " . $httpCode . "\n";
echo "Response: " . $response . "\n";

curl_close($ch);
curl_close($loginCh);

?>
