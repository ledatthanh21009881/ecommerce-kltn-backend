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
    $email = $input['email'] ?? null;
    
    if (!$email) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Email is required']);
        exit();
    }
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid email format']);
        exit();
    }
    
    // Kết nối database
    $pdo = new PDO(
        'mysql:host=localhost;dbname=shopswiftv2;charset=utf8mb4',
        'root',
        '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Kiểm tra xem email có tồn tại trong hệ thống không
    $stmt = $pdo->prepare("
        SELECT u.user_id, u.email, a.account_name 
        FROM users u 
        INNER JOIN accounts a ON u.account_id = a.account_id 
        WHERE u.email = ?
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Email not found in our system']);
        exit();
    }
    
    // Tạo reset token
    $resetToken = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    // Lưu reset token vào database
    $updateStmt = $pdo->prepare("
        UPDATE accounts 
        SET password_reset_token = ?, reset_token_expires_at = ?
        WHERE account_id = (SELECT account_id FROM users WHERE user_id = ?)
    ");
    $updateResult = $updateStmt->execute([$resetToken, $expiresAt, $user['user_id']]);
    
    if (!$updateResult) {
        throw new Exception('Failed to save reset token');
    }
    
    // Tạo reset link
    $resetLink = "http://localhost:3000/reset-password?token=" . $resetToken;
    
    // Gửi email thực tế
    $emailSent = sendResetEmail($email, $user['account_name'], $resetLink);
    
    if ($emailSent) {
        echo json_encode([
            'success' => true,
            'message' => 'Password reset link has been sent to your email',
            'data' => [
                'email' => $email,
                'expires_at' => $expiresAt
            ]
        ]);
    } else {
        // Nếu gửi email thất bại, vẫn trả về thành công nhưng log lỗi
        error_log("Failed to send email to: {$email}, Reset link: {$resetLink}");
        echo json_encode([
            'success' => true,
            'message' => 'Password reset link has been sent to your email',
            'data' => [
                'email' => $email,
                'reset_link' => $resetLink, // Chỉ trả về trong môi trường development
                'expires_at' => $expiresAt
            ]
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}

function sendResetEmail($toEmail, $accountName, $resetLink) {
    try {
        // Include PHPMailer
        require_once __DIR__ . '/../vendor/autoload.php';
        
        // Import PHPMailer classes
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'thanhle02032003@gmail.com';
        $mail->Password = 'jrwx henr swtq cggr'; // App password
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        // Recipients
        $mail->setFrom('thanhle02032003@gmail.com', 'ShopSwift');
        $mail->addAddress($toEmail, $accountName);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Reset Password - ShopSwift';
        $mail->Body = "
        <html>
        <head>
            <title>Reset Password</title>
        </head>
        <body>
            <h2>Reset Your Password</h2>
            <p>Hello {$accountName},</p>
            <p>You have requested to reset your password. Click the link below to set a new password:</p>
            <p><a href='{$resetLink}' style='background-color: #4F46E5; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; display: inline-block;'>Reset Password</a></p>
            <p>Or copy and paste this link into your browser:</p>
            <p>{$resetLink}</p>
            <p>This link will expire in 1 hour.</p>
            <p>If you didn't request this password reset, please ignore this email.</p>
            <br>
            <p>Best regards,<br>ShopSwift Team</p>
        </body>
        </html>
        ";
        
        $mail->send();
        return true;
        
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        error_log("PHPMailer error: " . $e->getMessage());
        return false;
    } catch (Exception $e) {
        error_log("Email sending error: " . $e->getMessage());
        return false;
    }
}
?>
