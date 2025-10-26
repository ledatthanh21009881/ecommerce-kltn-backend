<?php
require_once 'vendor/autoload.php';

use App\Support\JWT;

// Load config
$config = require 'app/config/app.php';

// Create JWT instance
$jwt = new JWT($config['jwt']['secret']);

// Customer data
$customerData = [
    'user_id' => 1,
    'account_id' => 1,
    'account_name' => 'customer1',
    'roles' => ['customer']
];

// Generate token (expires in 1 hour)
$token = $jwt->encode($customerData, 3600);

echo "Customer Token: " . $token . "\n";
echo "Expires in: 1 hour\n";
?>
