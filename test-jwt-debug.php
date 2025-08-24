<?php
require_once 'vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$token = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJ1c2VyX2lkIjoxLCJhY2NvdW50X2lkIjoxLCJhY2NvdW50X25hbWUiOiJhZG1pbiIsInJvbGVzIjpbImFkbWluIl0sImlhdCI6MTc1NjAzOTEyOCwiZXhwIjoxNzU2MDQyNzI4fQ.QjgxjXXfiCNlI2Jr7WvQO2q5HiMZgqkT4pm2pOS2X1s';

echo "Testing JWT decode...\n";

// Test với secret cứng
$jwtSecret = 'your-super-secret-jwt-key-here-2024';

try {
    $decoded = JWT::decode($token, new Key($jwtSecret, 'HS256'));
    echo "JWT decoded successfully!\n";
    echo "Decoded data: " . json_encode($decoded, JSON_PRETTY_PRINT) . "\n";
} catch (Exception $e) {
    echo "JWT decode error: " . $e->getMessage() . "\n";
}

// Test với secret khác
$jwtSecret2 = 'your-secret-key';

try {
    $decoded2 = JWT::decode($token, new Key($jwtSecret2, 'HS256'));
    echo "JWT decoded with secret2 successfully!\n";
} catch (Exception $e) {
    echo "JWT decode with secret2 error: " . $e->getMessage() . "\n";
}
