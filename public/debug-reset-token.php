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
    
    // Kiểm tra cấu trúc bảng accounts
    $stmt = $pdo->query("DESCRIBE accounts");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "=== Cấu trúc bảng accounts ===\n";
    foreach ($columns as $column) {
        echo "Column: {$column['Field']}, Type: {$column['Type']}, Null: {$column['Null']}, Key: {$column['Key']}\n";
    }
    
    // Kiểm tra dữ liệu reset token
    $stmt = $pdo->query("
        SELECT a.account_id, a.password_reset_token, a.reset_token_expires_at, u.email
        FROM accounts a
        INNER JOIN users u ON a.account_id = u.account_id
        WHERE a.password_reset_token IS NOT NULL
    ");
    $tokens = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\n=== Reset tokens trong database ===\n";
    if (empty($tokens)) {
        echo "Không có reset token nào trong database\n";
    } else {
        foreach ($tokens as $token) {
            echo "Account ID: {$token['account_id']}\n";
            echo "Email: {$token['email']}\n";
            echo "Token: {$token['password_reset_token']}\n";
            echo "Expires: {$token['reset_token_expires_at']}\n";
            echo "---\n";
        }
    }
    
    // Kiểm tra tất cả accounts
    $stmt = $pdo->query("
        SELECT a.account_id, a.account_name, a.password_reset_token, a.reset_token_expires_at, u.email
        FROM accounts a
        LEFT JOIN users u ON a.account_id = u.account_id
        ORDER BY a.account_id
    ");
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\n=== Tất cả accounts ===\n";
    foreach ($accounts as $account) {
        echo "Account ID: {$account['account_id']}\n";
        echo "Account Name: {$account['account_name']}\n";
        echo "Email: {$account['email']}\n";
        echo "Reset Token: " . ($account['password_reset_token'] ?: 'NULL') . "\n";
        echo "Expires: " . ($account['reset_token_expires_at'] ?: 'NULL') . "\n";
        echo "---\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>

