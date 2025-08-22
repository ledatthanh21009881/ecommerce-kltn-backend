<?php
require_once __DIR__ . '/vendor/autoload.php';

use App\Core\Container;
use App\Support\JWT;

try {
    $container = new Container(__DIR__ . '/app/config');
    $container->bootEnv(__DIR__ . '/');
    
    $jwt = $container->jwt();
    
    // Create test token
    $payload = [
        'account_id' => 1,
        'account_name' => 'admin',
        'account_type' => 'local',
        'roles' => ['admin'],
        'is_admin' => true
    ];
    
    $token = $jwt->encode($payload);
    
    echo "Test token created successfully!\n";
    echo "Token: " . $token . "\n";
    echo "Payload: " . json_encode($payload) . "\n";
    
    // Test decode
    $decoded = $jwt->decode($token);
    echo "Decoded payload: " . json_encode($decoded) . "\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

