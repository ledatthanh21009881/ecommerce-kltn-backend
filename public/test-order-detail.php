<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    $orderId = $_GET['order_id'] ?? null;
    
    if (!$orderId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Order ID is required']);
        exit();
    }
    
    // Kết nối database
    $pdo = new PDO(
        'mysql:host=localhost;dbname=shopswiftv2;charset=utf8mb4',
        'root',
        '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Lấy thông tin đơn hàng với customer và shipper
    $orderStmt = $pdo->prepare("
        SELECT 
            o.*,
            u.first_name,
            u.last_name,
            u.email,
            u.phone,
            a.receiver_name,
            a.phone as shipping_phone,
            a.address_line,
            a.ward,
            a.district,
            a.province,
            sm.name as shipping_method_name,
            p.method as payment_method,
            p.status as payment_status,
            p.paid_amount,
            st.shipper_id,
            s.first_name as shipper_first_name,
            s.last_name as shipper_last_name,
            s.phone as shipper_phone,
            s.email as shipper_email,
            sh.rating as shipper_rating,
            sh.on_time_delivery_pct,
            sh.total_delivered
        FROM orders o
        LEFT JOIN customers c ON o.customer_id = c.user_id
        LEFT JOIN users u ON c.user_id = u.user_id
        LEFT JOIN addresses a ON o.address_id = a.address_id
        LEFT JOIN shipping_methods sm ON o.shipping_method_id = sm.shipping_method_id
        LEFT JOIN payments p ON o.order_id = p.order_id
        LEFT JOIN shipping_tracking st ON o.order_id = st.order_id
        LEFT JOIN users s ON st.shipper_id = s.user_id
        LEFT JOIN shippers sh ON st.shipper_id = sh.user_id
        WHERE o.order_id = ?
    ");
    
    $orderStmt->execute([$orderId]);
    $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit();
    }
    
    // Lấy danh sách items
    $itemsStmt = $pdo->prepare("
        SELECT 
            oi.*,
            p.product_name,
            pi.url as product_image
        FROM order_items oi
        LEFT JOIN product_variants pv ON oi.variant_id = pv.variant_id
        LEFT JOIN products p ON pv.product_id = p.product_id
        LEFT JOIN product_images pi ON p.product_id = pi.product_id AND pi.is_main = 1
        WHERE oi.order_id = ?
    ");
    
    $itemsStmt->execute([$orderId]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format dữ liệu
    $formattedOrder = [
        'order_id' => (int)$order['order_id'],
        'invoice_number' => $order['invoice_number'],
        'status' => $order['status'],
        'total_amount' => (float)$order['total_amount'],
        'shipping_fee' => (float)$order['shipping_fee'],
        'discount_amount_applied' => (float)$order['discount_amount_applied'],
        'created_at' => $order['created_at'],
        'updated_at' => $order['updated_at'],
        'note' => $order['note'],
        'internal_note' => $order['internal_note'],
        'estimated_delivery_at' => $order['estimated_delivery_at'],
        
        // Customer info
        'first_name' => $order['first_name'],
        'last_name' => $order['last_name'],
        'email' => $order['email'],
        'phone' => $order['phone'],
        
        // Shipping address
        'shipping_address_snapshot' => $order['shipping_address_snapshot'] ?: json_encode([
            'receiver_name' => $order['receiver_name'],
            'phone' => $order['shipping_phone'],
            'address_line' => $order['address_line'],
            'ward' => $order['ward'],
            'district' => $order['district'],
            'province' => $order['province']
        ]),
        'shipping_method_name_snapshot' => $order['shipping_method_name'],
        
        // Payment info
        'payment' => [
            'method' => $order['payment_method'],
            'status' => $order['payment_status'],
            'paid_amount' => (float)$order['paid_amount']
        ],
        
        // Shipper info
        'shipper' => $order['shipper_id'] ? [
            'user_id' => (int)$order['shipper_id'],
            'first_name' => $order['shipper_first_name'],
            'last_name' => $order['shipper_last_name'],
            'phone' => $order['shipper_phone'],
            'email' => $order['shipper_email'],
            'rating' => (float)$order['shipper_rating'],
            'on_time_delivery_pct' => (float)$order['on_time_delivery_pct'],
            'total_delivered' => (int)$order['total_delivered']
        ] : null,
        
        // Items
        'items' => array_map(function($item) {
            return [
                'item_id' => (int)$item['item_id'],
                'variant_id' => (int)$item['variant_id'],
                'quantity' => (int)$item['quantity'],
                'unit_price' => (float)$item['unit_price'],
                'product_name_snapshot' => $item['product_name_snapshot'] ?: $item['product_name'],
                'product_image' => $item['product_image']
            ];
        }, $items)
    ];
    
    echo json_encode([
        'success' => true,
        'data' => $formattedOrder,
        'message' => 'Order details retrieved successfully'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
