<?php
declare(strict_types=1);

return [
    'name' => $_ENV['APP_NAME'] ?? 'VIVIENNE Ecommerce',
    'env' => $_ENV['APP_ENV'] ?? 'development',
    'debug' => filter_var($_ENV['APP_DEBUG'] ?? true, FILTER_VALIDATE_BOOLEAN),
    'url' => $_ENV['APP_URL'] ?? 'http://localhost:8000',
    'timezone' => 'Asia/Ho_Chi_Minh',
    
    'jwt' => [
        'secret' => $_ENV['JWT_SECRET'] ?? 'your-secret-key-here',
        'algorithm' => $_ENV['JWT_ALGORITHM'] ?? 'HS256',
        'expire_time' => (int)($_ENV['JWT_EXPIRE_TIME'] ?? 3600),
    ],
    
    'upload' => [
        'path' => $_ENV['UPLOAD_PATH'] ?? 'storage/uploads',
        'max_size' => (int)($_ENV['MAX_FILE_SIZE'] ?? 5242880), // 5MB
        'allowed_types' => ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'],
    ],
    
    'pagination' => [
        'per_page' => 20,
        'max_per_page' => 100,
    ],
    
    'mail' => [
        'host' => $_ENV['MAIL_HOST'] ?? 'smtp.gmail.com',
        'port' => (int)($_ENV['MAIL_PORT'] ?? 587),
        'username' => $_ENV['MAIL_USERNAME'] ?? '',
        'password' => $_ENV['MAIL_PASSWORD'] ?? '',
        'encryption' => $_ENV['MAIL_ENCRYPTION'] ?? 'tls',
        'from_address' => $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@vivienne.com',
        'from_name' => $_ENV['MAIL_FROM_NAME'] ?? 'VIVIENNE',
    ],
    
    'payment' => [
        'gateway_url' => $_ENV['PAYMENT_GATEWAY_URL'] ?? '',
        'api_key' => $_ENV['PAYMENT_API_KEY'] ?? '',
        'secret_key' => $_ENV['PAYMENT_SECRET_KEY'] ?? '',
    ],
    
    'vnpay' => [
        'tmn_code' => $_ENV['VNPAY_TMN_CODE'] ?? '',
        'hash_secret' => $_ENV['VNPAY_HASH_SECRET'] ?? '',
        'url' => $_ENV['VNPAY_URL'] ?? 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
        'return_url' => $_ENV['VNPAY_RETURN_URL'] ?? '',
        'ipn_url' => $_ENV['VNPAY_IPN_URL'] ?? '',
    ],
    
    'payos' => [
        'client_id' => $_ENV['PAYOS_CLIENT_ID'] ?? '',
        'api_key' => $_ENV['PAYOS_API_KEY'] ?? '',
        'checksum_key' => $_ENV['PAYOS_CHECKSUM_KEY'] ?? '',
        'api_url' => $_ENV['PAYOS_API_URL'] ?? 'https://api-merchant.payos.vn',
        'base_url' => $_ENV['PAYOS_BASE_URL'] ?? $_ENV['APP_URL'] ?? 'http://localhost:3000',
    ],
    
    'shipping' => [
        'api_url' => $_ENV['SHIPPING_API_URL'] ?? '',
        'api_key' => $_ENV['SHIPPING_API_KEY'] ?? '',
    ],
];
