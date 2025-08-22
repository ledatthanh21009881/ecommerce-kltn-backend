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

try {
    // Kết nối database
    $pdo = new PDO(
        'mysql:host=localhost;dbname=shopswiftv2;charset=utf8mb4',
        'root',
        '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Lấy danh sách shipper từ bảng users JOIN với user_roles và roles
    $stmt = $pdo->query("
        SELECT u.user_id, u.first_name, u.last_name, u.phone, u.email, s.rating, s.on_time_delivery_pct, s.is_available
        FROM users u
        INNER JOIN user_roles ur ON u.user_id = ur.user_id
        INNER JOIN roles r ON ur.role_id = r.role_id
        LEFT JOIN shippers s ON u.user_id = s.user_id
        WHERE r.role_name = 'shipper' AND u.user_id IS NOT NULL
        ORDER BY u.first_name, u.last_name
    ");
    $shippers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format dữ liệu theo interface Shipper
    $formattedShippers = [];
    foreach ($shippers as $shipper) {
        $formattedShippers[] = [
            'user_id' => (int)$shipper['user_id'],
            'first_name' => $shipper['first_name'],
            'last_name' => $shipper['last_name'],
            'phone' => $shipper['phone'],
            'email' => $shipper['email'],
            'rating' => $shipper['rating'] ? (float)$shipper['rating'] : 4.5, // Default rating if null
            'on_time_delivery_pct' => $shipper['on_time_delivery_pct'] ? (float)$shipper['on_time_delivery_pct'] : 95, // Default percentage if null
            'is_available' => $shipper['is_available'] ? (bool)$shipper['is_available'] : true
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => $formattedShippers,
        'message' => 'Shippers retrieved successfully',
        'count' => count($formattedShippers)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
