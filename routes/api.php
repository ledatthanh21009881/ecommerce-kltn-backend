<?php
declare(strict_types=1);

use App\Controllers\{AddressController, AuthController, ProductController, ProductImageController, OrderController, AdminController, CategoryController, CustomerController, ShipperController, UserController, RoleController, InventoryController, InvoiceController, VoucherController, MessageController, ShippingController, CartController, SupplierController, PurchaseReceiptController, StockAdjustmentController, TrackingController, NotificationController, ContentController, ContentCategoryController, PaymentController, CollectionController, UserOrderController, SiteSettingsController};
use App\Middlewares\{AuthMiddleware, AdminMiddleware};

// Debug test route
$router->get('/api/v1/test', function($req, $res) {
    return $res->json(['success' => true, 'message' => 'API router is working!']);
});

// ========================================
// AUTHENTICATION ROUTES
// ========================================
$router->post('/api/v1/auth/login', [AuthController::class, 'login']);
$router->post('/api/v1/auth/admin/login', [AuthController::class, 'adminLogin']);
$router->put('/api/v1/auth/admin/locale', [AuthController::class, 'updateAdminLocale'], [new AdminMiddleware($container)]);
$router->post('/api/v1/auth/register', [AuthController::class, 'register']);
$router->post('/api/v1/auth/logout', [AuthController::class, 'logout'], [new AuthMiddleware($container)]);
$router->post('/api/v1/auth/refresh', [AuthController::class, 'refresh'], [new AuthMiddleware($container)]);
$router->post('/api/v1/auth/refresh-advanced', [AuthController::class, 'refreshTokenAdvanced']);
$router->post('/api/v1/auth/logout-advanced', [AuthController::class, 'logoutAdvanced'], [new AuthMiddleware($container)]);
$router->post('/api/v1/auth/forgot-password', [AuthController::class, 'forgotPassword']);
$router->post('/api/v1/auth/validate-reset-token', [AuthController::class, 'validateResetToken']);
$router->post('/api/v1/auth/reset-password', [AuthController::class, 'resetPassword']);
$router->get('/api/v1/auth/me', [AuthController::class, 'profile'], [new AuthMiddleware($container)]);
$router->get('/api/v1/auth/check-admin', [AuthController::class, 'checkAdminRole'], [new AuthMiddleware($container)]);
$router->put('/api/v1/auth/profile', [AuthController::class, 'updateProfile'], [new AuthMiddleware($container)]);
$router->post('/api/v1/auth/change-password', [AuthController::class, 'changePassword'], [new AuthMiddleware($container)]);

// ========================================
// MOBILE APP AUTHENTICATION ROUTES (SHIPPER ONLY)
// ========================================
$router->get('/api/mobile/v1/config', function($req, $res) {
    // Get server IP from SERVER_ADDR
    $serverIP = $_SERVER['SERVER_ADDR'] ?? '127.0.0.1';
    
    // Try to get real IP from network interfaces (Windows)
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $output = [];
        exec('ipconfig', $output);
        $currentAdapter = '';
        $foundIPs = [];
        
        foreach ($output as $line) {
            // Track current adapter name
            if (preg_match('/^([^:]+):$/', $line, $matches)) {
                $currentAdapter = trim($matches[1]);
            }
            // Extract IPv4 addresses
            if (preg_match('/IPv4 Address[^:]*:\s*(\d+\.\d+\.\d+\.\d+)/i', $line, $matches)) {
                $ip = $matches[1];
                // Skip localhost and auto-config IPs
                if (strpos($ip, '127.') !== 0 && strpos($ip, '169.254.') !== 0) {
                    $foundIPs[] = [
                        'ip' => $ip,
                        'adapter' => $currentAdapter ?: 'Network Adapter'
                    ];
                }
            }
        }
        
        // Prefer WiFi IP (192.168.x.x or 10.x.x.x) over VPN IP (172.x.x.x)
        $wifiIP = null;
        $vpnIP = null;
        
        foreach ($foundIPs as $ipInfo) {
            $ip = $ipInfo['ip'];
            // Check if it's WiFi IP (192.168.x.x or 10.x.x.x)
            if (strpos($ip, '192.168.') === 0 || strpos($ip, '10.') === 0) {
                $wifiIP = $ip;
                break;
            }
            // Check if it's VPN IP (172.16-31.x.x)
            if (strpos($ip, '172.') === 0 && !$vpnIP) {
                $parts = explode('.', $ip);
                if (count($parts) >= 2) {
                    $secondOctet = (int)$parts[1];
                    if ($secondOctet >= 16 && $secondOctet <= 31) {
                        $vpnIP = $ip;
                    }
                }
            }
        }
        
        // Use WiFi IP if available, otherwise use VPN IP, otherwise use first found IP
        if ($wifiIP) {
            $serverIP = $wifiIP;
        } elseif ($vpnIP) {
            $serverIP = $vpnIP;
        } elseif (!empty($foundIPs)) {
            $serverIP = $foundIPs[0]['ip'];
        }
    }
    
    return $res->json([
        'success' => true,
        'data' => [
            'api_url' => "http://{$serverIP}:8000",
            'server_ip' => $serverIP,
            'port' => 8000
        ]
    ]);
});

$router->post('/api/mobile/v1/auth/login', [AuthController::class, 'shipperLogin']);
$router->post('/api/mobile/v1/auth/refresh', [AuthController::class, 'shipperRefreshToken']);
$router->post('/api/mobile/v1/auth/forgot-password', [AuthController::class, 'shipperForgotPassword']);
$router->post('/api/mobile/v1/auth/verify-otp', [AuthController::class, 'shipperVerifyOTP']);
$router->post('/api/mobile/v1/auth/reset-password', [AuthController::class, 'shipperResetPassword']);

// ========================================
// ORDER MANAGEMENT ROUTES (MAIN API)
// ========================================
$router->get('/api/v1/orders', [OrderController::class, 'index'], [new AuthMiddleware($container)]);
$router->get('/api/v1/orders/statistics', [OrderController::class, 'statistics'], [new AuthMiddleware($container)]);
$router->get('/api/v1/orders/{id}', [OrderController::class, 'show'], [new AuthMiddleware($container)]);

// FRONTEND COMPATIBILITY ROUTES (NO VERSION PREFIX)
// ========================================
$router->get('/api/orders', [OrderController::class, 'index'], [new AuthMiddleware($container)]);
$router->get('/api/orders/statistics', [OrderController::class, 'statistics'], [new AuthMiddleware($container)]);
$router->get('/api/orders/{id}', [OrderController::class, 'show'], [new AuthMiddleware($container)]);

// ========================================
// USER ORDER ROUTES (customer-scoped)
// ========================================
$router->get('/api/user/orders', [UserOrderController::class, 'index'], [new AuthMiddleware($container)]);
$router->get('/api/user/orders/{id}', [UserOrderController::class, 'show'], [new AuthMiddleware($container)]);
$router->get('/api/user/orders/{id}/tracking', [UserOrderController::class, 'tracking'], [new AuthMiddleware($container)]);

// User addresses (customer-scoped CRUD + default)
$router->get('/api/v1/user/addresses', [AddressController::class, 'index'], [new AuthMiddleware($container)]);
$router->post('/api/v1/user/addresses', [AddressController::class, 'store'], [new AuthMiddleware($container)]);
$router->put('/api/v1/user/addresses/{id}', [AddressController::class, 'update'], [new AuthMiddleware($container)]);
$router->delete('/api/v1/user/addresses/{id}', [AddressController::class, 'destroy'], [new AuthMiddleware($container)]);
$router->put('/api/v1/user/addresses/{id}/default', [AddressController::class, 'setDefault'], [new AuthMiddleware($container)]);

// TEST ROUTES (NO AUTHENTICATION REQUIRED)
// ========================================
$router->get('/api/test/orders/{id}', [OrderController::class, 'show']);
$router->post('/api/v1/orders', [OrderController::class, 'store'], [new AuthMiddleware($container)]);
$router->put('/api/v1/orders/{id}', [OrderController::class, 'update'], [new AuthMiddleware($container)]);
$router->put('/api/v1/orders/{id}/status', [OrderController::class, 'updateStatus'], [new AuthMiddleware($container)]);
$router->post('/api/v1/orders/{id}/assign-shipper', [OrderController::class, 'assignShipper'], [new AuthMiddleware($container)]);
$router->post('/api/v1/orders/{id}/send-invoice', [OrderController::class, 'sendInvoice'], [new AuthMiddleware($container)]);
$router->get('/api/v1/orders/available-shippers', [OrderController::class, 'getAvailableShippers'], [new AuthMiddleware($container)]);
$router->delete('/api/v1/orders/{id}', [OrderController::class, 'destroy'], [new AuthMiddleware($container)]);
$router->get('/api/v1/orders/{id}/invoice', [OrderController::class, 'generateInvoice'], [new AuthMiddleware($container)]);
$router->get('/api/v1/orders/export', [OrderController::class, 'export'], [new AuthMiddleware($container)]);

// ========================================
// BACKEND API ROUTES (FOR ADMIN FRONTEND)
// ========================================
$router->get('/api/backend/v1/orders', [OrderController::class, 'index'], [new AuthMiddleware($container)]);
$router->get('/api/backend/v1/orders/statistics', [OrderController::class, 'statistics'], [new AuthMiddleware($container)]);
$router->get('/api/backend/v1/orders/available-shippers', [OrderController::class, 'getAvailableShippers'], [new AuthMiddleware($container)]);
$router->get('/api/backend/v1/orders/export', [OrderController::class, 'export'], [new AuthMiddleware($container)]);
$router->get('/api/backend/v1/orders/{id}', [OrderController::class, 'show'], [new AuthMiddleware($container)]);
$router->put('/api/backend/v1/orders/{id}/status', [OrderController::class, 'updateStatus'], [new AuthMiddleware($container)]);
$router->post('/api/backend/v1/orders/{id}/status', [OrderController::class, 'updateStatus'], [new AuthMiddleware($container)]);
$router->post('/api/backend/v1/orders/{id}/assign-shipper', [OrderController::class, 'assignShipper'], [new AuthMiddleware($container)]);
$router->post('/api/backend/v1/orders/{id}/send-invoice', [OrderController::class, 'sendInvoice'], [new AuthMiddleware($container)]);
$router->delete('/api/backend/v1/orders/{id}', [OrderController::class, 'destroy'], [new AuthMiddleware($container)]);
$router->get('/api/backend/v1/admin/dashboard', [AdminController::class, 'dashboard'], [new AuthMiddleware($container)]);
$router->get('/api/v1/public/site-settings', [SiteSettingsController::class, 'publicSettings']);
$router->get('/api/backend/v1/settings', [SiteSettingsController::class, 'adminGet'], [new AdminMiddleware($container)]);
$router->put('/api/backend/v1/settings', [SiteSettingsController::class, 'adminUpdate'], [new AdminMiddleware($container)]);
$router->post('/api/backend/v1/settings', [SiteSettingsController::class, 'adminUpdate'], [new AdminMiddleware($container)]);
$router->post('/api/backend/v1/settings/favicon', [SiteSettingsController::class, 'uploadFavicon'], [new AdminMiddleware($container)]);
$router->get('/api/test/available-shippers', [OrderController::class, 'getAvailableShippers']);

// ========================================
// BACKEND INVENTORY API ROUTES (FOR ADMIN FRONTEND)
// ========================================
$router->get('/api/backend/v1/inventory', [InventoryController::class, 'index'], [new AuthMiddleware($container)]);
$router->get('/api/backend/v1/inventory/{id}', [InventoryController::class, 'show'], [new AuthMiddleware($container)]);
$router->post('/api/backend/v1/inventory', [InventoryController::class, 'store'], [new AuthMiddleware($container)]);
$router->put('/api/backend/v1/inventory/{id}', [InventoryController::class, 'update'], [new AuthMiddleware($container)]);
$router->delete('/api/backend/v1/inventory/{id}', [InventoryController::class, 'destroy'], [new AuthMiddleware($container)]);

// ========================================
// BACKEND USER MANAGEMENT API ROUTES (FOR ADMIN FRONTEND)
// ========================================
$router->get('/api/backend/v1/users', [AdminController::class, 'getUsers'], [new AuthMiddleware($container)]);
$router->get('/api/backend/v1/users/{id}/addresses', [AdminController::class, 'getUserAddresses'], [new AuthMiddleware($container)]);
$router->get('/api/backend/v1/users/stats', [AdminController::class, 'getUserStats'], [new AuthMiddleware($container)]);
$router->post('/api/backend/v1/users', [AdminController::class, 'createUser'], [new AuthMiddleware($container)]);
$router->put('/api/backend/v1/users/{id}', [AdminController::class, 'updateUser'], [new AuthMiddleware($container)]);
$router->delete('/api/backend/v1/users/{id}', [AdminController::class, 'deleteUser'], [new AuthMiddleware($container)]);
$router->get('/api/backend/v1/roles/all', [RoleController::class, 'getAllRoles'], [new AuthMiddleware($container)]);

// ========================================
// BACKEND VOUCHER MANAGEMENT API ROUTES (FOR ADMIN FRONTEND)
// ========================================
$router->get('/api/backend/v1/vouchers', [VoucherController::class, 'index'], [new AuthMiddleware($container)]);
$router->get('/api/backend/v1/vouchers/stats', [VoucherController::class, 'stats'], [new AuthMiddleware($container)]);
$router->get('/api/backend/v1/vouchers/{id}', [VoucherController::class, 'show'], [new AuthMiddleware($container)]);
$router->post('/api/backend/v1/vouchers', [VoucherController::class, 'store'], [new AuthMiddleware($container)]);
$router->put('/api/backend/v1/vouchers/{id}', [VoucherController::class, 'update'], [new AuthMiddleware($container)]);
$router->delete('/api/backend/v1/vouchers/{id}', [VoucherController::class, 'destroy'], [new AuthMiddleware($container)]);
$router->get('/api/backend/v1/vouchers/{id}/usage-stats', [VoucherController::class, 'usageStats'], [new AuthMiddleware($container)]);
$router->put('/api/backend/v1/vouchers/{id}/toggle-status', [VoucherController::class, 'toggleStatus'], [new AuthMiddleware($container)]);

// ========================================
// SHIPPING MANAGEMENT ROUTES
// ========================================
$router->get('/api/backend/v1/shipping', [ShippingController::class, 'index'], [new AuthMiddleware($container)]);
$router->get('/api/backend/v1/shipping/{id}', [ShippingController::class, 'show'], [new AuthMiddleware($container)]);
$router->post('/api/backend/v1/shipping', [ShippingController::class, 'store'], [new AuthMiddleware($container)]);
$router->put('/api/backend/v1/shipping/{id}', [ShippingController::class, 'update'], [new AuthMiddleware($container)]);
$router->delete('/api/backend/v1/shipping/{id}', [ShippingController::class, 'destroy'], [new AuthMiddleware($container)]);
$router->patch('/api/backend/v1/shipping/{id}/toggle', [ShippingController::class, 'toggleStatus'], [new AuthMiddleware($container)]);

// ========================================
// COLLECTIONS ROUTES (public, no auth)
// ========================================
$router->get('/api/collections', [CollectionController::class, 'index']);
$router->get('/api/collections/{collection_id}/images', [CollectionController::class, 'getImages']);
$router->get('/api/collections/{slug}', [CollectionController::class, 'showBySlug']);

// ========================================
// COLLECTIONS ROUTES (admin, auth required)
// ========================================
$router->get('/api/backend/v1/collections', [CollectionController::class, 'adminIndex'], [new AuthMiddleware($container)]);
$router->get('/api/backend/v1/collections/{id}', [CollectionController::class, 'adminShow'], [new AuthMiddleware($container)]);
$router->post('/api/backend/v1/collections', [CollectionController::class, 'adminStore'], [new AuthMiddleware($container)]);
$router->put('/api/backend/v1/collections/reorder', [CollectionController::class, 'adminReorder'], [new AuthMiddleware($container)]);
$router->put('/api/backend/v1/collections/{id}', [CollectionController::class, 'adminUpdate'], [new AuthMiddleware($container)]);
$router->delete('/api/backend/v1/collections/{id}', [CollectionController::class, 'adminDestroy'], [new AuthMiddleware($container)]);

// ========================================
// CART MANAGEMENT ROUTES
// ========================================
// Cart routes - allow guest users (no auth required, but middleware will check if token exists)
$router->get('/api/backend/v1/cart', [CartController::class, 'index']);
$router->post('/api/backend/v1/cart/add', [CartController::class, 'addItem']);
$router->put('/api/backend/v1/cart/update/{item_id}', [CartController::class, 'updateItem']);
$router->delete('/api/backend/v1/cart/remove/{item_id}', [CartController::class, 'removeItem']);
$router->delete('/api/backend/v1/cart/clear', [CartController::class, 'clearCart']);
$router->post('/api/backend/v1/cart/sync', [CartController::class, 'syncCart']);

// ========================================
// BACKEND MESSAGING API ROUTES (FOR ADMIN FRONTEND)
// ========================================
$router->get('/api/backend/v1/conversations', [MessageController::class, 'getConversations'], [new AuthMiddleware($container)]);
$router->get('/api/backend/v1/conversations/{id}/messages', [MessageController::class, 'getMessages'], [new AuthMiddleware($container)]);
$router->post('/api/backend/v1/conversations', [MessageController::class, 'createConversation'], [new AuthMiddleware($container)]);
$router->post('/api/backend/v1/messages', [MessageController::class, 'sendMessage'], [new AuthMiddleware($container)]);
$router->post('/api/backend/v1/messages/upload-media', [MessageController::class, 'uploadMedia'], [new AuthMiddleware($container)]);
$router->delete('/api/backend/v1/messages/{id}', [MessageController::class, 'deleteMessage'], [new AuthMiddleware($container)]);
$router->put('/api/backend/v1/conversations/{id}/mark-read', [MessageController::class, 'markAsRead'], [new AuthMiddleware($container)]);
// ========================================
// TEST ROUTES (NO AUTHENTICATION REQUIRED)
// ========================================
$router->get('/api/v1/orders/debug', function($req, $res) use ($container) {
    try {
        $pdo = $container->database()->getConnection();
        
        // Test basic query
        $stmt = $pdo->query('SELECT COUNT(*) as count FROM orders');
        $count = $stmt->fetch()['count'];
        
        // Test JOIN query with correct table structure
        $sql = "SELECT 
                    o.order_id,
                    o.invoice_number,
                    o.total_amount,
                    o.status,
                    o.created_at,
                    u.first_name,
                    u.last_name,
                    u.email,
                    u.phone
                FROM orders o
                INNER JOIN customers c ON o.customer_id = c.user_id
                INNER JOIN users u ON c.user_id = u.user_id
                LIMIT 5";
        
        $stmt = $pdo->query($sql);
        $orders = $stmt->fetchAll();
        
        return $res->json([
            'success' => true, 
            'message' => 'Orders debug',
            'order_count' => $count,
            'sample_orders' => $orders
        ]);
    } catch(Exception $e) {
        return $res->json(['success' => false, 'error' => $e->getMessage()]);
    }
});

$router->get('/api/v1/orders-test', function($req, $res) use ($container) {
    try {
        $pdo = $container->database()->getConnection();
        
        // Get query parameters
        $status = $_GET['status'] ?? null;
        $search = $_GET['search'] ?? null;
        $page = (int)($_GET['page'] ?? 1);
        $limit = (int)($_GET['limit'] ?? 20);
        $offset = ($page - 1) * $limit;
        
        // Build WHERE clause
        $whereConditions = [];
        $params = [];
        
        if ($status) {
            $whereConditions[] = "o.status = ?";
            $params[] = $status;
        }
        
        if ($search) {
            $whereConditions[] = "(o.invoice_number LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
            $searchTerm = "%$search%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";
        
        // Count total
        $countSql = "SELECT COUNT(*) as total FROM orders o 
                     INNER JOIN customers c ON o.customer_id = c.user_id 
                     INNER JOIN users u ON c.user_id = u.user_id 
                     $whereClause";
        $stmt = $pdo->prepare($countSql);
        $stmt->execute($params);
        $total = $stmt->fetch()['total'];
        
        // Get orders
        $sql = "SELECT 
                    o.order_id,
                    o.invoice_number,
                    o.total_amount,
                    o.status,
                    o.created_at,
                    o.updated_at,
                    u.first_name,
                    u.last_name,
                    u.email,
                    u.phone,
                    (SELECT COUNT(*) FROM order_items WHERE order_id = o.order_id) as item_count
                FROM orders o
                INNER JOIN customers c ON o.customer_id = c.user_id
                INNER JOIN users u ON c.user_id = u.user_id
                $whereClause
                ORDER BY o.created_at DESC
                LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $orders = $stmt->fetchAll();
        
        return $res->json([
            'success' => true,
            'data' => [
                'items' => $orders,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total' => $total,
                    'total_pages' => ceil($total / $limit)
                ]
            ]
        ]);
        
    } catch(Exception $e) {
        return $res->json([
            'success' => false,
            'message' => 'Failed to fetch orders: ' . $e->getMessage()
        ]);
    }
});

$router->get('/api/v1/orders-test/statistics', function($req, $res) use ($container) {
    try {
        $pdo = $container->database()->getConnection();
        
        // Get statistics
        $sql = "SELECT 
                    COUNT(*) as total_orders,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
                    SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing_orders,
                    SUM(CASE WHEN status = 'shipping' THEN 1 ELSE 0 END) as shipping_orders,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
                    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders,
                    SUM(CASE WHEN status = 'returned' THEN 1 ELSE 0 END) as returned_orders,
                    SUM(CASE WHEN status = 'completed' THEN total_amount ELSE 0 END) as total_revenue
                FROM orders";
        
        $stmt = $pdo->query($sql);
        $stats = $stmt->fetch();
        
        return $res->json([
            'success' => true,
            'data' => [
                'total_orders' => (int)$stats['total_orders'],
                'pending_orders' => (int)$stats['pending_orders'],
                'processing_orders' => (int)$stats['processing_orders'],
                'shipping_orders' => (int)$stats['shipping_orders'],
                'completed_orders' => (int)$stats['completed_orders'],
                'cancelled_orders' => (int)$stats['cancelled_orders'],
                'returned_orders' => (int)$stats['returned_orders'],
                'total_revenue' => (float)$stats['total_revenue']
            ]
        ]);
        
    } catch(Exception $e) {
        return $res->json([
            'success' => false,
            'message' => 'Failed to fetch statistics: ' . $e->getMessage()
        ]);
    }
});

// ========================================
// SHIPPER API ROUTES
// ========================================
$router->get('/api/v1/shipper/orders', [OrderController::class, 'shipperOrders'], [new AuthMiddleware($container)]);
$router->get('/api/v1/shipper/orders/{id}', [OrderController::class, 'shipperOrderDetail'], [new AuthMiddleware($container)]);
$router->post('/api/v1/shipper/orders/{id}/accept', [OrderController::class, 'acceptOrder'], [new AuthMiddleware($container)]);
$router->post('/api/v1/shipper/orders/{id}/pickup', [OrderController::class, 'pickupOrder'], [new AuthMiddleware($container)]);
$router->post('/api/v1/shipper/orders/{id}/start-delivery', [OrderController::class, 'startDelivery'], [new AuthMiddleware($container)]);
$router->post('/api/v1/shipper/orders/{id}/arrive', [OrderController::class, 'arriveOrder'], [new AuthMiddleware($container)]);
$router->post('/api/v1/shipper/orders/{id}/deliver', [OrderController::class, 'deliverOrder'], [new AuthMiddleware($container)]);
$router->post('/api/v1/shipper/orders/{id}/complete', [OrderController::class, 'completeOrder'], [new AuthMiddleware($container)]);
$router->post('/api/v1/shipper/orders/{id}/reject', [OrderController::class, 'rejectOrder'], [new AuthMiddleware($container)]);
$router->post('/api/v1/shipper/fcm-token', [ShipperController::class, 'registerFCMToken'], [new AuthMiddleware($container)]);
$router->get('/api/v1/shipper/notifications', [ShipperController::class, 'getNotifications'], [new AuthMiddleware($container)]);
$router->get('/api/v1/shipper/notifications/unread-count', [ShipperController::class, 'getUnreadCount'], [new AuthMiddleware($container)]);
$router->post('/api/v1/shipper/notifications/{id}/read', [ShipperController::class, 'markNotificationAsRead'], [new AuthMiddleware($container)]);
$router->post('/api/v1/shipper/notifications/mark-all-read', [ShipperController::class, 'markAllNotificationsAsRead'], [new AuthMiddleware($container)]);

// ========================================
// TRACKING API ROUTES
// ========================================
$router->get('/api/v1/orders/{id}/tracking', [OrderController::class, 'tracking'], [new AuthMiddleware($container)]);
$router->post('/api/v1/orders/{id}/tracking', [OrderController::class, 'updateTracking'], [new AuthMiddleware($container)]);

// ========================================
// PAYMENT API ROUTES
// ========================================
$router->get('/api/v1/payments', [PaymentController::class, 'index'], [new AuthMiddleware($container)]);
$router->get('/api/backend/v1/payments', [PaymentController::class, 'index'], [new AuthMiddleware($container)]);
$router->post('/api/v1/payments/create', [PaymentController::class, 'create'], [new AuthMiddleware($container)]);
$router->post('/api/v1/payments/{id}/approve', [PaymentController::class, 'approve']); // Public endpoint for QR code approval
$router->post('/api/v1/payments/vnpay-ipn', [PaymentController::class, 'handleVNPayIPN']);
$router->post('/api/v1/payments/payos-webhook', [PaymentController::class, 'handlePayOSWebhook']); // Public endpoint for PayOS webhook
$router->get('/api/v1/payments/{id}/status', [PaymentController::class, 'getStatus']); // Public endpoint for QR code
$router->get('/api/v1/payments/{id}/debug', [PaymentController::class, 'debugPayment']); // Debug endpoint
$router->get('/api/v1/payments/order/{order_id}', [PaymentController::class, 'getByOrderId'], [new AuthMiddleware($container)]);

// ========================================
// INVOICE API ROUTES
// ========================================
$router->get('/api/v1/invoice/generate', [InvoiceController::class, 'generate'], [new AuthMiddleware($container)]);
$router->post('/api/v1/invoice/generate', [InvoiceController::class, 'generateAndSend'], [new AuthMiddleware($container)]);

// Debug invoice routes
$router->get('/api/v1/invoice/debug/{orderId}', function($req, $res) use ($container) {
    try {
        $orderId = (int) $req->getAttribute('orderId');
        $_GET['order_id'] = $orderId;
        $controller = new InvoiceController($container);
        return $controller->generate($req, $res);
    } catch (Exception $e) {
        return $res->json([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
});

$router->get('/api/v1/invoice/test/{orderId}', function($req, $res) use ($container) {
    try {
        $orderId = (int) $req->getAttribute('orderId');
        $controller = new InvoiceController($container);
        $database = $container->database();
        $orderModel = new \App\Domain\Orders\Order($database);
        $order = $orderModel->getByIdWithDetails($orderId);
        
        return $res->json([
            'success' => true,
            'message' => 'InvoiceController created successfully',
            'order_exists' => $order ? true : false,
            'order_status' => $order['status'] ?? 'N/A'
        ]);
    } catch (Exception $e) {
        return $res->json([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
});

// ========================================
// CATEGORY ROUTES
// ========================================
$router->get('/api/v1/categories', [CategoryController::class, 'index']);
$router->get('/api/v1/categories/main', [CategoryController::class, 'getMainCategories']);
$router->get('/api/v1/categories/{id}', [CategoryController::class, 'show']);
$router->get('/api/v1/categories/{id}/children', [CategoryController::class, 'getChildren']);
$router->get('/api/v1/categories/slug/{slug}', [CategoryController::class, 'getBySlug']);

// Category Management Routes (Admin only)
$router->post('/api/v1/categories', [CategoryController::class, 'store'], [new AuthMiddleware($container)]);
$router->put('/api/v1/categories/{id}', [CategoryController::class, 'update'], [new AuthMiddleware($container)]);
$router->delete('/api/v1/categories/{id}', [CategoryController::class, 'destroy'], [new AuthMiddleware($container)]);
$router->post('/api/v1/categories/reorder', [CategoryController::class, 'reorder'], [new AuthMiddleware($container)]);
$router->patch('/api/v1/categories/{id}/toggle-status', [CategoryController::class, 'toggleStatus'], [new AuthMiddleware($container)]);

// ========================================
// PRODUCT ROUTES
// ========================================
$router->get('/api/v1/products', [ProductController::class, 'index']);
$router->get('/api/v1/products/featured', [ProductController::class, 'featured']);
$router->get('/api/v1/products/search', [ProductController::class, 'search']);
$router->get('/api/v1/products/variants', [ProductController::class, 'getAllVariants'], [new AuthMiddleware($container)]);
$router->get('/api/v1/products/{id}', [ProductController::class, 'show']);
$router->post('/api/v1/products', [ProductController::class, 'store'], [new AuthMiddleware($container)]);
$router->put('/api/v1/products/{id}', [ProductController::class, 'update'], [new AuthMiddleware($container)]);
$router->delete('/api/v1/products/{id}', [ProductController::class, 'destroy'], [new AuthMiddleware($container)]);
$router->patch('/api/v1/products/{id}/soft-delete', [ProductController::class, 'softDelete'], [new AuthMiddleware($container)]);
$router->patch('/api/v1/products/{id}/restore', [ProductController::class, 'restore'], [new AuthMiddleware($container)]);

// Product Routes (without version for backward compatibility)
$router->get('/api/products', [ProductController::class, 'index']);
$router->get('/api/products/featured', [ProductController::class, 'featured']);
$router->get('/api/products/search', [ProductController::class, 'search']);
$router->get('/api/products/{id}', [ProductController::class, 'show']);
$router->post('/api/products', [ProductController::class, 'store'], [new AuthMiddleware($container)]);
$router->put('/api/products/{id}', [ProductController::class, 'update'], [new AuthMiddleware($container)]);
$router->delete('/api/products/{id}', [ProductController::class, 'destroy'], [new AuthMiddleware($container)]);

// ========================================
// PRODUCT IMAGE ROUTES
// ========================================
$router->get('/api/v1/products/{id}/images', [ProductImageController::class, 'getImages']);
$router->post('/api/v1/products/{id}/images', [ProductImageController::class, 'addImages'], [new AuthMiddleware($container)]);
$router->put('/api/v1/product-images/{id}', [ProductImageController::class, 'updateImage'], [new AuthMiddleware($container)]);
$router->delete('/api/v1/product-images/{id}', [ProductImageController::class, 'deleteImage'], [new AuthMiddleware($container)]);
$router->patch('/api/v1/product-images/{id}/set-main', [ProductImageController::class, 'setMainImage'], [new AuthMiddleware($container)]);

// ========================================
// INVENTORY ROUTES
// ========================================
$router->get('/api/v1/inventory', [InventoryController::class, 'index'], [new AuthMiddleware($container)]);
$router->get('/api/v1/inventory/{id}', [InventoryController::class, 'show'], [new AuthMiddleware($container)]);
$router->post('/api/v1/inventory', [InventoryController::class, 'store'], [new AuthMiddleware($container)]);
$router->put('/api/v1/inventory/{id}', [InventoryController::class, 'update'], [new AuthMiddleware($container)]);
$router->delete('/api/v1/inventory/{id}', [InventoryController::class, 'destroy'], [new AuthMiddleware($container)]);

// ========================================
// CUSTOMER MANAGEMENT ROUTES
// ========================================
$router->get('/api/v1/customers', [CustomerController::class, 'index'], [new AdminMiddleware($container)]);
$router->get('/api/v1/customers/search', [CustomerController::class, 'search'], [new AdminMiddleware($container)]);
$router->get('/api/v1/customers/top', [CustomerController::class, 'topCustomers'], [new AdminMiddleware($container)]);
$router->get('/api/v1/customers/{id}', [CustomerController::class, 'show'], [new AdminMiddleware($container)]);
$router->post('/api/v1/customers', [CustomerController::class, 'store'], [new AdminMiddleware($container)]);
$router->put('/api/v1/customers/{id}', [CustomerController::class, 'update'], [new AdminMiddleware($container)]);
$router->delete('/api/v1/customers/{id}', [CustomerController::class, 'destroy'], [new AdminMiddleware($container)]);
$router->get('/api/v1/customers/{id}/orders', [CustomerController::class, 'orders'], [new AdminMiddleware($container)]);
$router->get('/api/v1/customers/{id}/addresses', [CustomerController::class, 'addresses'], [new AdminMiddleware($container)]);
$router->put('/api/v1/customers/{id}/loyalty-points', [CustomerController::class, 'updateLoyaltyPoints'], [new AdminMiddleware($container)]);

// ========================================
// SHIPPER MANAGEMENT ROUTES
// ========================================
$router->get('/api/v1/shippers', [ShipperController::class, 'index'], [new AdminMiddleware($container)]);
$router->get('/api/v1/shippers/search', [ShipperController::class, 'search'], [new AdminMiddleware($container)]);
$router->get('/api/v1/shippers/available', [ShipperController::class, 'available'], [new AdminMiddleware($container)]);
$router->get('/api/v1/shippers/top', [ShipperController::class, 'topShippers'], [new AdminMiddleware($container)]);
$router->get('/api/v1/shippers/statistics', [ShipperController::class, 'statistics'], [new AdminMiddleware($container)]);
$router->get('/api/v1/shippers/{id}', [ShipperController::class, 'show'], [new AdminMiddleware($container)]);
$router->post('/api/v1/shippers', [ShipperController::class, 'store'], [new AdminMiddleware($container)]);
$router->put('/api/v1/shippers/{id}', [ShipperController::class, 'update'], [new AdminMiddleware($container)]);
$router->delete('/api/v1/shippers/{id}', [ShipperController::class, 'destroy'], [new AdminMiddleware($container)]);
$router->get('/api/v1/shippers/{id}/deliveries', [ShipperController::class, 'deliveries'], [new AdminMiddleware($container)]);
$router->get('/api/v1/shippers/{id}/performance', [ShipperController::class, 'performance'], [new AdminMiddleware($container)]);
$router->put('/api/v1/shippers/{id}/rating', [ShipperController::class, 'updateRating'], [new AdminMiddleware($container)]);
$router->put('/api/v1/shippers/{id}/availability', [ShipperController::class, 'updateAvailability'], [new AdminMiddleware($container)]);
$router->put('/api/v1/shippers/{id}/delivery-stats', [ShipperController::class, 'updateDeliveryStats'], [new AdminMiddleware($container)]);

// ========================================
// ADMIN ROUTES
// ========================================
$router->get('/api/v1/admin/dashboard', [AdminController::class, 'dashboard'], [new AdminMiddleware($container)]);
$router->get('/api/v1/admin/users', [AdminController::class, 'getUsers'], [new AdminMiddleware($container)]);
$router->put('/api/v1/admin/users/{id}/status', [AdminController::class, 'updateUserStatus'], [new AdminMiddleware($container)]);
$router->get('/api/v1/admin/activity-logs', [AdminController::class, 'getActivityLogs'], [new AdminMiddleware($container)]);

// ========================================
// USER MANAGEMENT ROUTES
// ========================================
$router->get('/api/v1/users', [UserController::class, 'index'], [new AdminMiddleware($container)]);
$router->get('/api/v1/users/search', [UserController::class, 'search'], [new AdminMiddleware($container)]);
$router->get('/api/v1/users/stats', [UserController::class, 'getStats'], [new AdminMiddleware($container)]);
$router->get('/api/v1/users/{id}', [UserController::class, 'show'], [new AdminMiddleware($container)]);
$router->post('/api/v1/users', [UserController::class, 'store'], [new AdminMiddleware($container)]);
$router->put('/api/v1/users/{id}', [UserController::class, 'update'], [new AdminMiddleware($container)]);
$router->delete('/api/v1/users/{id}', [UserController::class, 'destroy'], [new AdminMiddleware($container)]);
$router->put('/api/v1/users/{id}/lock', [UserController::class, 'toggleLock'], [new AdminMiddleware($container)]);
$router->put('/api/v1/users/{id}/2fa', [UserController::class, 'toggle2FA'], [new AdminMiddleware($container)]);
$router->get('/api/v1/users/{id}/roles', [UserController::class, 'getUserRoles'], [new AdminMiddleware($container)]);
$router->post('/api/v1/users/{id}/roles', [UserController::class, 'assignRoles'], [new AdminMiddleware($container)]);

// ========================================
// ROLE MANAGEMENT ROUTES
// ========================================
$router->get('/api/v1/roles', [RoleController::class, 'index'], [new AdminMiddleware($container)]);
$router->get('/api/v1/roles/all', [RoleController::class, 'getAll'], [new AdminMiddleware($container)]);
$router->get('/api/v1/roles/search', [RoleController::class, 'search'], [new AdminMiddleware($container)]);
$router->get('/api/v1/roles/{id}', [RoleController::class, 'show'], [new AdminMiddleware($container)]);
$router->post('/api/v1/roles', [RoleController::class, 'store'], [new AdminMiddleware($container)]);
$router->put('/api/v1/roles/{id}', [RoleController::class, 'update'], [new AdminMiddleware($container)]);
$router->delete('/api/v1/roles/{id}', [RoleController::class, 'destroy'], [new AdminMiddleware($container)]);
$router->get('/api/v1/users/{user_id}/roles', [RoleController::class, 'getUserRoles'], [new AdminMiddleware($container)]);
$router->post('/api/v1/users/{user_id}/roles', [RoleController::class, 'assignRolesToUser'], [new AdminMiddleware($container)]);
$router->delete('/api/v1/users/{user_id}/roles/{role_id}', [RoleController::class, 'removeRoleFromUser'], [new AdminMiddleware($container)]);

// ========================================
// DEBUG ROUTES
// ========================================
$router->get('/api/v1/products/debug', function($req, $res) use ($container) {
    try {
        $pdo = $container->database()->getConnection();
        $stmt = $pdo->query('SELECT COUNT(*) as count FROM products');
        $count = $stmt->fetch()['count'];
        
        $stmt = $pdo->query('SELECT * FROM products LIMIT 2');
        $products = $stmt->fetchAll();
        
        return $res->json([
            'success' => true, 
            'message' => 'Products debug',
            'product_count' => $count,
            'sample_products' => $products
        ]);
    } catch(Exception $e) {
        return $res->json(['success' => false, 'error' => $e->getMessage()]);
    }
});

$router->get('/api/v1/inventory/debug', function($req, $res) use ($container) {
    try {
        $pdo = $container->database()->getConnection();
        
        $stmt = $pdo->query('SELECT COUNT(*) as count FROM product_variants WHERE is_active = 1');
        $count = $stmt->fetch()['count'];
        
        $sql = "SELECT 
                    pv.variant_id,
                    pv.product_id,
                    pv.size_id,
                    pv.sku,
                    pv.stock_quantity,
                    pv.status,
                    pv.is_active,
                    p.product_name,
                    s.size_name
                FROM product_variants pv
                INNER JOIN products p ON pv.product_id = p.product_id
                INNER JOIN sizes s ON pv.size_id = s.size_id
                WHERE pv.is_active = 1
                LIMIT 3";
        
        $stmt = $pdo->query($sql);
        $variants = $stmt->fetchAll();
        
        return $res->json([
            'success' => true, 
            'message' => 'Inventory debug',
            'variant_count' => $count,
            'sample_variants' => $variants
        ]);
    } catch(Exception $e) {
        return $res->json(['success' => false, 'error' => $e->getMessage()]);
    }
});

$router->get('/api/test/shippers', function($req, $res) use ($container) {
    try {
        $pdo = $container->database()->getConnection();
        
        $sql = "
            SELECT 
                s.user_id,
                s.vehicle_info,
                s.rating,
                s.on_time_delivery_pct,
                s.total_delivered,
                s.is_available,
                u.first_name,
                u.last_name,
                u.phone,
                u.email
            FROM shippers s
            LEFT JOIN users u ON s.user_id = u.user_id
            WHERE s.is_available = 1 AND s.status = 'active'
            ORDER BY s.rating DESC, s.on_time_delivery_pct DESC
        ";
        
        $stmt = $pdo->query($sql);
        $shippers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $res->json([
            'success' => true,
            'data' => $shippers,
            'count' => count($shippers)
        ]);
        
    } catch (Exception $e) {
        return $res->json([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
});

$router->get('/api/test/order-controller-shippers', function($req, $res) use ($container) {
    try {
        $controller = new OrderController($container);
        return $controller->getAvailableShippers($req, $res);
    } catch (Exception $e) {
        return $res->json([
            'success' => false,
            'message' => 'Controller Error: ' . $e->getMessage()
        ]);
    }
});

// ========================================
// SUPPLIER MANAGEMENT API ROUTES
// ========================================
$router->get('/api/v1/suppliers', [SupplierController::class, 'index'], [new AuthMiddleware($container)]);
$router->get('/api/v1/suppliers/stats', [SupplierController::class, 'getStats'], [new AuthMiddleware($container)]);
$router->post('/api/v1/suppliers', [SupplierController::class, 'store'], [new AuthMiddleware($container)]);
$router->put('/api/v1/suppliers', [SupplierController::class, 'update'], [new AuthMiddleware($container)]);
$router->delete('/api/v1/suppliers', [SupplierController::class, 'delete'], [new AuthMiddleware($container)]);

// Backend API routes (for admin frontend)
$router->get('/api/backend/v1/suppliers', [SupplierController::class, 'index'], [new AuthMiddleware($container)]);
$router->get('/api/backend/v1/suppliers/stats', [SupplierController::class, 'getStats'], [new AuthMiddleware($container)]);
$router->get('/api/backend/v1/suppliers/stats-detail', [SupplierController::class, 'getDetailStats'], [new AuthMiddleware($container)]);
$router->post('/api/backend/v1/suppliers', [SupplierController::class, 'store'], [new AuthMiddleware($container)]);
$router->put('/api/backend/v1/suppliers', [SupplierController::class, 'update'], [new AuthMiddleware($container)]);
$router->delete('/api/backend/v1/suppliers', [SupplierController::class, 'delete'], [new AuthMiddleware($container)]);

// ========================================
// PURCHASE RECEIPT MANAGEMENT API ROUTES
// ========================================
$router->get('/api/v1/purchase-receipts', [PurchaseReceiptController::class, 'index'], [new AuthMiddleware($container)]);
$router->get('/api/v1/purchase-receipts/by-supplier', [PurchaseReceiptController::class, 'getBySupplier'], [new AuthMiddleware($container)]);
$router->post('/api/v1/purchase-receipts', [PurchaseReceiptController::class, 'store'], [new AuthMiddleware($container)]);
$router->post('/api/v1/purchase-receipts/confirm', [PurchaseReceiptController::class, 'confirm'], [new AuthMiddleware($container)]);
$router->put('/api/v1/purchase-receipts', [PurchaseReceiptController::class, 'update'], [new AuthMiddleware($container)]);
$router->delete('/api/v1/purchase-receipts', [PurchaseReceiptController::class, 'delete'], [new AuthMiddleware($container)]);

// Backend API routes (for admin frontend)
$router->get('/api/backend/v1/purchase-receipts', [PurchaseReceiptController::class, 'index'], [new AuthMiddleware($container)]);
$router->get('/api/backend/v1/purchase-receipts/by-supplier', [PurchaseReceiptController::class, 'getBySupplier'], [new AuthMiddleware($container)]);
$router->post('/api/backend/v1/purchase-receipts', [PurchaseReceiptController::class, 'store'], [new AuthMiddleware($container)]);
$router->post('/api/backend/v1/purchase-receipts/confirm', [PurchaseReceiptController::class, 'confirm'], [new AuthMiddleware($container)]);
$router->put('/api/backend/v1/purchase-receipts', [PurchaseReceiptController::class, 'update'], [new AuthMiddleware($container)]);
$router->delete('/api/backend/v1/purchase-receipts', [PurchaseReceiptController::class, 'delete'], [new AuthMiddleware($container)]);

// ========================================
// STOCK ADJUSTMENT MANAGEMENT API ROUTES
// ========================================
$router->get('/api/v1/stock-adjustments', [StockAdjustmentController::class, 'index'], [new AuthMiddleware($container)]);
$router->post('/api/v1/stock-adjustments', [StockAdjustmentController::class, 'store'], [new AuthMiddleware($container)]);
$router->post('/api/v1/stock-adjustments/confirm', [StockAdjustmentController::class, 'confirm'], [new AuthMiddleware($container)]);
$router->post('/api/v1/stock-adjustments/cancel', [StockAdjustmentController::class, 'cancel'], [new AuthMiddleware($container)]);
$router->delete('/api/v1/stock-adjustments', [StockAdjustmentController::class, 'delete'], [new AuthMiddleware($container)]);

// Backend API routes (for admin frontend)
$router->get('/api/backend/v1/stock-adjustments', [StockAdjustmentController::class, 'index'], [new AuthMiddleware($container)]);
$router->post('/api/backend/v1/stock-adjustments', [StockAdjustmentController::class, 'store'], [new AuthMiddleware($container)]);
$router->post('/api/backend/v1/stock-adjustments/confirm', [StockAdjustmentController::class, 'confirm'], [new AuthMiddleware($container)]);
$router->post('/api/backend/v1/stock-adjustments/cancel', [StockAdjustmentController::class, 'cancel'], [new AuthMiddleware($container)]);
$router->delete('/api/backend/v1/stock-adjustments', [StockAdjustmentController::class, 'delete'], [new AuthMiddleware($container)]);

// ========================================
// ORDER TRACKING SYSTEM API ROUTES
// ========================================
// Order Tracking
$router->get('/api/backend/v1/tracking/orders', [TrackingController::class, 'getActiveOrders'], [new AuthMiddleware($container)]);
$router->get('/api/backend/v1/tracking/orders/{id}', [TrackingController::class, 'getOrderDetail'], [new AuthMiddleware($container)]);
$router->post('/api/backend/v1/tracking/orders/{id}/status', [TrackingController::class, 'updateOrderStatus'], [new AuthMiddleware($container)]);
$router->get('/api/backend/v1/tracking/shippers/{id}/location', [TrackingController::class, 'getShipperLocation'], [new AuthMiddleware($container)]);
$router->get('/api/backend/v1/tracking/orders/{id}/history', [TrackingController::class, 'getOrderHistory'], [new AuthMiddleware($container)]);
$router->get('/api/backend/v1/tracking/stats', [TrackingController::class, 'getDashboardStats'], [new AuthMiddleware($container)]);

// Shipper Management (Enhanced for Tracking)
$router->get('/api/backend/v1/shippers', [ShipperController::class, 'getAll'], [new AuthMiddleware($container)]);
$router->get('/api/backend/v1/shippers/available', [ShipperController::class, 'getAvailable'], [new AuthMiddleware($container)]);
$router->get('/api/backend/v1/shippers/{id}/performance', [ShipperController::class, 'getPerformance'], [new AuthMiddleware($container)]);
$router->post('/api/backend/v1/shippers/{id}/location', [ShipperController::class, 'updateLocation'], [new AuthMiddleware($container)]);
$router->post('/api/backend/v1/shippers/assign-order', [ShipperController::class, 'assignOrder'], [new AuthMiddleware($container)]);

// Notifications
$router->get('/api/backend/v1/notifications', [NotificationController::class, 'getAll'], [new AuthMiddleware($container)]);
$router->get('/api/backend/v1/notifications/stats', [NotificationController::class, 'getStats'], [new AuthMiddleware($container)]);
$router->put('/api/backend/v1/notifications/{id}/read', [NotificationController::class, 'markAsRead'], [new AuthMiddleware($container)]);
$router->put('/api/backend/v1/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead'], [new AuthMiddleware($container)]);
$router->post('/api/backend/v1/notifications', [NotificationController::class, 'create'], [new AuthMiddleware($container)]);
$router->delete('/api/backend/v1/notifications/{id}', [NotificationController::class, 'delete'], [new AuthMiddleware($container)]);

// ========================================
// CONTENT MANAGEMENT SYSTEM API ROUTES
// ========================================
// Public content endpoint (no auth)
$router->get('/api/backend/v1/content/public/slug/{slug}', [ContentController::class, 'getPublicBySlug']);

// Content CRUD
$router->get('/api/backend/v1/content', [ContentController::class, 'index'], [new AuthMiddleware($container)]);
$router->get('/api/backend/v1/content/{id}', [ContentController::class, 'show'], [new AuthMiddleware($container)]);
$router->get('/api/backend/v1/content/slug/{slug}', [ContentController::class, 'getBySlug'], [new AuthMiddleware($container)]);
$router->post('/api/backend/v1/content', [ContentController::class, 'store'], [new AuthMiddleware($container)]);
$router->put('/api/backend/v1/content/{id}', [ContentController::class, 'update'], [new AuthMiddleware($container)]);
$router->delete('/api/backend/v1/content/{id}', [ContentController::class, 'destroy'], [new AuthMiddleware($container)]);
$router->post('/api/backend/v1/content/{id}/publish', [ContentController::class, 'publish'], [new AuthMiddleware($container)]);
$router->get('/api/backend/v1/content/stats', [ContentController::class, 'getStats'], [new AuthMiddleware($container)]);

// Content Categories CRUD
$router->get('/api/backend/v1/content/categories', [ContentCategoryController::class, 'index'], [new AuthMiddleware($container)]);
$router->get('/api/backend/v1/content/categories/{id}', [ContentCategoryController::class, 'show'], [new AuthMiddleware($container)]);
$router->post('/api/backend/v1/content/categories', [ContentCategoryController::class, 'store'], [new AuthMiddleware($container)]);
$router->put('/api/backend/v1/content/categories/{id}', [ContentCategoryController::class, 'update'], [new AuthMiddleware($container)]);
$router->delete('/api/backend/v1/content/categories/{id}', [ContentCategoryController::class, 'destroy'], [new AuthMiddleware($container)]);

// ========================================
// PRODUCT VARIANTS API ROUTES (for Purchase Receipts)
// ========================================
$router->get('/api/backend/v1/products/variants', [ProductController::class, 'getAllVariants'], [new AuthMiddleware($container)]);
