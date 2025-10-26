<?php

require_once __DIR__ . '/app/config/app.php';
require_once __DIR__ . '/app/Support/JWT.php';

use App\Support\JWT;

// Load JWT secret from config
$config = require __DIR__ . '/app/config/app.php';
$jwt = new JWT($config['jwt']['secret']);

// Generate customer token (not admin)
$payload = [
    'user_id' => 1, // Customer ID
    'account_id' => 1,
    'account_name' => 'customer1',
    'roles' => ['customer'],
    'iat' => time(),
    'exp' => time() + (4 * 60 * 60) // 4 hours
];

$token = $jwt->encode($payload);
echo "🔑 Generated Customer Token: " . $token . "\n\n";

// Test cart API
$baseUrl = 'http://localhost:3000/api/backend/v1/cart';
$headers = [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $token
];

echo "🧪 Testing Cart API\n";
echo "==================\n\n";

// Test 1: GET cart
echo "1. GET /api/backend/v1/cart\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Status: $httpCode\n";
echo "Response: " . $response . "\n\n";

// Test 2: Add item to cart
echo "2. POST /api/backend/v1/cart/add\n";
$addItem = [
    'variant_id' => 2, // BEIGE WOOL BLEND CARGO PANTS - Size M
    'quantity' => 1
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl . '/add');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($addItem));
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Status: $httpCode\n";
echo "Response: " . $response . "\n\n";

echo "🎉 Cart API tests completed!\n";

