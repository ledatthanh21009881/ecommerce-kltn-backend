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
    $input = json_decode(file_get_contents('php://input'), true);
    $token = $input['token'] ?? null;
    
    if (!$token) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Token is required']);
        exit();
    }
    
    // Kết nối database
    $pdo = new PDO(
        'mysql:host=localhost;dbname=shopswiftv2;charset=utf8mb4',
        'root',
        '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Kiểm tra token có hợp lệ và chưa hết hạn không
    $stmt = $pdo->prepare("
        SELECT a.account_id, a.password_reset_token, a.reset_token_expires_at, u.email
        FROM accounts a
        INNER JOIN users u ON a.account_id = u.account_id
        WHERE a.password_reset_token = ?
    ");
    $stmt->execute([$token]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$account) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid reset token']);
        exit();
    }
    
    // Kiểm tra thời gian hết hạn bằng PHP
    $currentTime = new DateTime();
    $expiresAt = new DateTime($account['reset_token_expires_at']);
    
    if ($currentTime > $expiresAt) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Reset token has expired']);
        exit();
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Token is valid',
        'data' => [
            'account_id' => $account['account_id'],
            'email' => $account['email'],
            'expires_at' => $account['reset_token_expires_at']
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
