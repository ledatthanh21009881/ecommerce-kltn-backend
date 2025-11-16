<?php
/**
 * Test Email OTP Sending
 * 
 * Usage:
 * - Via browser: http://localhost:8000/test-email-otp.php
 * - Via CLI: php public/test-email-otp.php
 */

// Load autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables if .env exists
if (file_exists(__DIR__ . '/../.env')) {
    $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $_ENV[trim($parts[0])] = trim($parts[1]);
        }
    }
}

use App\Support\EmailService;

// Enable debug mode
define('EMAIL_DEBUG', true);

// Configuration
$to = "thanhle02032003@gmail.com"; // Email để test - Thay đổi email này nếu cần
$accountName = "Test User";
$otpCode = "123456";

// Check if running from CLI or browser
$isCLI = php_sapi_name() === 'cli';

if (!$isCLI) {
    header('Content-Type: text/html; charset=utf-8');
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Test Email OTP</title>";
    echo "<style>body{font-family:Arial,sans-serif;max-width:800px;margin:50px auto;padding:20px;}";
    echo ".success{color:green;padding:10px;background:#e8f5e9;border:1px solid #4caf50;border-radius:5px;}";
    echo ".error{color:red;padding:10px;background:#ffebee;border:1px solid #f44336;border-radius:5px;}";
    echo ".info{color:#2196f3;padding:10px;background:#e3f2fd;border:1px solid #2196f3;border-radius:5px;}";
    echo "pre{background:#f5f5f5;padding:15px;border-radius:5px;overflow-x:auto;}</style></head><body>";
    echo "<h1>Test Email OTP Sending</h1>";
}

echo ($isCLI ? "" : "<div class='info'>") . "Testing email OTP sending...\n" . ($isCLI ? "" : "</div>");
echo ($isCLI ? "" : "<p><strong>") . "To: {$to}" . ($isCLI ? "\n" : "</strong></p>");
echo ($isCLI ? "" : "<p><strong>") . "OTP Code: {$otpCode}" . ($isCLI ? "\n" : "</strong></p>");
echo ($isCLI ? "" : "<p><strong>") . "Account Name: {$accountName}" . ($isCLI ? "\n\n" : "</strong></p>");

try {
    $emailService = new EmailService();
    
    // Display SMTP configuration (without password)
    if (!$isCLI) {
        $smtpHost = $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com';
        $smtpPort = $_ENV['SMTP_PORT'] ?? '587';
        $smtpUsername = $_ENV['SMTP_USERNAME'] ?? 'thanhle02032003@gmail.com';
        $fromEmail = $_ENV['FROM_EMAIL'] ?? 'thanhle02032003@gmail.com';
        $smtpPassword = $_ENV['SMTP_PASSWORD'] ?? 'jrwx henr swtq cggr';
        $passwordLength = strlen(str_replace(' ', '', $smtpPassword));
        
        echo "<div class='info'><strong>SMTP Configuration:</strong><br>";
        echo "Host: {$smtpHost}<br>";
        echo "Port: {$smtpPort}<br>";
        echo "Username: {$smtpUsername}<br>";
        echo "From Email: {$fromEmail}<br>";
        echo "Password Length: {$passwordLength} characters (spaces removed)<br>";
        echo "Password (first 4 chars): " . substr(str_replace(' ', '', $smtpPassword), 0, 4) . "***<br>";
        echo "</div>";
        
        if ($passwordLength !== 16) {
            echo "<div class='error'><strong>⚠️ Warning:</strong> Gmail App Password should be exactly 16 characters (without spaces). Current length: {$passwordLength}</div>";
        }
    }
    
    echo ($isCLI ? "" : "<hr>") . "Sending email...\n" . ($isCLI ? "" : "<br>");
    
    $result = $emailService->sendOTPEmail($to, $accountName, $otpCode);
    
    if ($result) {
        $message = "✅ Email sent successfully!\nPlease check your inbox and spam folder.";
        echo ($isCLI ? "" : "<div class='success'>") . $message . ($isCLI ? "\n" : "</div>");
    } else {
        $message = "❌ Failed to send email.\nCheck error logs in: storage/logs/";
        echo ($isCLI ? "" : "<div class='error'>") . $message . ($isCLI ? "\n" : "</div>");
    }
    
} catch (Exception $e) {
    $errorMessage = "❌ Error: " . $e->getMessage();
    echo ($isCLI ? "" : "<div class='error'>") . $errorMessage . ($isCLI ? "\n" : "</div>");
    
    if (!$isCLI) {
        echo "<pre>Stack trace:\n" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    } else {
        echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    }
}

// Check error logs
$logFile = __DIR__ . '/../storage/logs/error.log';
if (file_exists($logFile)) {
    $logContent = file_get_contents($logFile);
    $recentErrors = array_slice(explode("\n", $logContent), -10);
    
    if (!empty(array_filter($recentErrors))) {
        echo ($isCLI ? "\n" : "<hr><h3>Recent Error Logs:</h3><pre>");
        echo implode("\n", $recentErrors);
        echo ($isCLI ? "\n" : "</pre>");
    }
}

if (!$isCLI) {
    echo "<hr><p><a href='test-email-otp.php'>Refresh</a> | <a href='index.php'>Back to Home</a></p>";
    echo "</body></html>";
}

