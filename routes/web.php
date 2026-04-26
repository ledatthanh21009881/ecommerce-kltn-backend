<?php
declare(strict_types=1);

// Web Routes for Basic Web Interface
$router->get('/', function($req, $res) {
    $res->json([
        'name' => 'VIVIENNE Ecommerce API',
        'version' => '1.0.0',
        'status' => 'running',
        'endpoints' => [
            'api_base' => '/api/v1',
            'documentation' => '/docs',
            'health' => '/health'
        ]
    ]);
});

// Health Check Route
$router->get('/health', function($req, $res) {
    $res->json([
        'status' => 'ok',
        'timestamp' => date('Y-m-d H:i:s'),
        'environment' => $_ENV['APP_ENV'] ?? 'development',
        'database' => 'connected'
    ]);
});

// API Documentation Route
$router->get('/docs', function($req, $res) {
    $res->json([
        'message' => 'API Documentation',
        'endpoints' => [
            'Authentication' => [
                'POST /api/v1/auth/login' => 'User login',
                'POST /api/v1/auth/register' => 'User registration',
                'POST /api/v1/auth/logout' => 'User logout',
                'GET /api/v1/auth/me' => 'Get user profile'
            ],
            'Products' => [
                'GET /api/v1/products' => 'List all products',
                'GET /api/v1/products/{id}' => 'Get product by ID',
                'POST /api/v1/products' => 'Create new product (Admin)',
                'PUT /api/v1/products/{id}' => 'Update product (Admin)'
            ],
            'Orders' => [
                'GET /api/v1/orders' => 'List user orders',
                'POST /api/v1/orders' => 'Create new order',
                'GET /api/v1/orders/{id}' => 'Get order details',
                'PUT /api/v1/orders/{id}/status' => 'Update order status'
            ],
            'Cart' => [
                'GET /api/v1/cart' => 'Get user cart',
                'POST /api/v1/cart/add' => 'Add item to cart',
                'PUT /api/v1/cart/{id}' => 'Update cart item',
                'DELETE /api/v1/cart/{id}' => 'Remove cart item'
            ]
        ]
    ]);
});
