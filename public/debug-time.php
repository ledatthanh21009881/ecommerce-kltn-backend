<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    // Kết nối database
    $pdo = new PDO(
        'mysql:host=localhost;dbname=shopswiftv2;charset=utf8mb4',
        'root',
        '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    $currentTime = date('Y-m-d H:i:s');
    echo "=== Thời gian hiện tại ===\n";
    echo "PHP time: {$currentTime}\n";
    
    // Lấy thời gian từ database
    $stmt = $pdo->query("SELECT NOW() as db_time");
    $dbTime = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Database time: {$dbTime['db_time']}\n";
    
    // Kiểm tra token cụ thể
    $token = "a2a09e5ae30e078c4616f86a370d80c156973c10896a813a8d088a50a36b72c4";
    
    $stmt = $pdo->prepare("
        SELECT a.account_id, a.password_reset_token, a.reset_token_expires_at, u.email
        FROM accounts a
        INNER JOIN users u ON a.account_id = u.account_id
        WHERE a.password_reset_token = ?
    ");
    $stmt->execute([$token]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "\n=== Kiểm tra token cụ thể ===\n";
    if ($account) {
        echo "Account ID: {$account['account_id']}\n";
        echo "Email: {$account['email']}\n";
        echo "Token: {$account['password_reset_token']}\n";
        echo "Expires: {$account['reset_token_expires_at']}\n";
        
        // So sánh thời gian
        $expiresAt = new DateTime($account['reset_token_expires_at']);
        $now = new DateTime();
        
        echo "Current time: " . $now->format('Y-m-d H:i:s') . "\n";
        echo "Expires at: " . $expiresAt->format('Y-m-d H:i:s') . "\n";
        echo "Is expired: " . ($now > $expiresAt ? 'YES' : 'NO') . "\n";
        echo "Time difference: " . $now->diff($expiresAt)->format('%H:%I:%S') . "\n";
        
        // Test query với điều kiện thời gian
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM accounts a
            INNER JOIN users u ON a.account_id = u.account_id
            WHERE a.password_reset_token = ? AND a.reset_token_expires_at > NOW()
        ");
        $stmt->execute([$token]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "Query result count: {$result['count']}\n";
        
    } else {
        echo "Token không tìm thấy trong database\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>

