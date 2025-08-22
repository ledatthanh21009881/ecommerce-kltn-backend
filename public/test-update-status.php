<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    $orderId = $_GET['order_id'] ?? null;
    $input = json_decode(file_get_contents('php://input'), true);
    $newStatus = $input['status'] ?? null;
    $reason = $input['reason'] ?? '';
    
    if (!$orderId || !$newStatus) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Order ID and status are required']);
        exit();
    }
    
    // Kết nối database
    $pdo = new PDO(
        'mysql:host=localhost;dbname=shopswiftv2;charset=utf8mb4',
        'root',
        '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Bắt đầu transaction
    $pdo->beginTransaction();
    
    try {
        // Update order status
        $updateStmt = $pdo->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE order_id = ?");
        $updateResult = $updateStmt->execute([$newStatus, $orderId]);
        
        if (!$updateResult) {
            throw new Exception('Failed to update order status');
        }
        
        // Log status change
        $logStmt = $pdo->prepare("
            INSERT INTO order_status_logs (order_id, status, changed_by, reason, changed_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        $logResult = $logStmt->execute([$orderId, $newStatus, 1, $reason]);
        
        if (!$logResult) {
            throw new Exception('Failed to log status change');
        }
        
        // Log activity
        $activityStmt = $pdo->prepare("
            INSERT INTO activity_logs (entity_type, entity_id, action, changed_by, data_before, data_after, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $activityData = json_encode([
            'order_id' => $orderId,
            'status' => $newStatus,
            'reason' => $reason
        ]);
        
        $activityResult = $activityStmt->execute(['order', $orderId, 'status_change', 1, null, $activityData]);
        
        if (!$activityResult) {
            throw new Exception('Failed to log activity');
        }
        
        // Commit transaction
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Order status updated successfully',
            'data' => [
                'order_id' => $orderId,
                'status' => $newStatus,
                'updated_at' => date('Y-m-d H:i:s')
            ]
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction
        $pdo->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
