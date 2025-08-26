<?php
require_once __DIR__ . '/../vendor/autoload.php';

// Lấy admin token
$token = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJhY2NvdW50X2lkIjoxLCJhY2NvdW50X25hbWUiOiJhZG1pbiIsImlhdCI6MTczNDU5NzI5MCwiZXhwIjoxNzM0NjgzNjkwfQ.Ej8Ej8Ej8Ej8Ej8Ej8Ej8Ej8Ej8Ej8Ej8Ej8Ej8Ej8';

$url = 'http://localhost:8000/api/backend/v1/messages';

$data = [
    'conversation_id' => 1,
    'content' => 'https://www.google.com',
    'is_link' => true
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $token
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n";

// Kiểm tra tin nhắn đã được lưu
$url2 = 'http://localhost:8000/api/backend/v1/conversations/1/messages';
$ch2 = curl_init();
curl_setopt($ch2, CURLOPT_URL, $url2);
curl_setopt($ch2, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token
]);
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);

$response2 = curl_exec($ch2);
curl_close($ch2);

echo "\nMessages in conversation:\n";
echo $response2 . "\n";
?>
