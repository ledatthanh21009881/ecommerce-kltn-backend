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
    $password = $input['password'] ?? null;
    $confirmPassword = $input['confirm_password'] ?? null;
    
    if (!$token || !$password || !$confirmPassword) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Token, password and confirm_password are required']);
        exit();
    }
    
    if ($password !== $confirmPassword) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
        exit();
    }
    
    if (strlen($password) < 6) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters long']);
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
            throw new Exception('Invalid reset token');
        }
        
        // Kiểm tra thời gian hết hạn bằng PHP
        $currentTime = new DateTime();
        $expiresAt = new DateTime($account['reset_token_expires_at']);
        
        if ($currentTime > $expiresAt) {
            throw new Exception('Reset token has expired');
        }
        
        // Hash password mới
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Cập nhật password và xóa reset token
        $updateStmt = $pdo->prepare("
            UPDATE accounts 
            SET password = ?, password_reset_token = NULL, reset_token_expires_at = NULL, password_changed_at = NOW()
            WHERE account_id = ?
        ");
        $updateResult = $updateStmt->execute([$hashedPassword, $account['account_id']]);
        
        if (!$updateResult) {
            throw new Exception('Failed to update password');
        }
        
        // Commit transaction
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Password reset successfully',
            'data' => [
                'email' => $account['email'],
                'password_changed_at' => date('Y-m-d H:i:s')
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
