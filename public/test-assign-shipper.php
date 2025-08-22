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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    // Get request body
    $input = json_decode(file_get_contents('php://input'), true);
    $orderId = $_GET['order_id'] ?? null;
    $shipperId = $input['shipper_id'] ?? null;
    
    if (!$orderId || !$shipperId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Order ID and shipper ID are required']);
        exit();
    }
    
    // Kết nối database
    $pdo = new PDO(
        'mysql:host=localhost;dbname=shopswiftv2;charset=utf8mb4',
        'root',
        '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Kiểm tra xem đã có tracking record chưa
    $checkStmt = $pdo->prepare("SELECT tracking_id FROM shipping_tracking WHERE order_id = ?");
    $checkStmt->execute([$orderId]);
    $existingTracking = $checkStmt->fetch();
    
    if ($existingTracking) {
        // Update existing tracking record
        $stmt = $pdo->prepare("UPDATE shipping_tracking SET shipper_id = ?, last_updated = NOW() WHERE order_id = ?");
        $result = $stmt->execute([$shipperId, $orderId]);
    } else {
        // Insert new tracking record
        $stmt = $pdo->prepare("INSERT INTO shipping_tracking (order_id, shipper_id, last_updated) VALUES (?, ?, NOW())");
        $result = $stmt->execute([$orderId, $shipperId]);
    }
    
    if ($result) {
        // Log activity
        $activityStmt = $pdo->prepare("INSERT INTO activity_logs (entity_type, entity_id, action, changed_by, data_after, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $activityData = json_encode([
            'order_id' => $orderId,
            'shipper_id' => $shipperId
        ]);
        $activityStmt->execute(['order', $orderId, 'assign', 1, $activityData]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Shipper assigned successfully',
            'data' => [
                'order_id' => $orderId,
                'shipper_id' => $shipperId
            ]
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to assign shipper']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
