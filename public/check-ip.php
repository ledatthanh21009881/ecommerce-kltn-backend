<?php
/**
 * Check Computer IP Address
 * 
 * Usage:
 * - Via browser: http://localhost:8000/check-ip.php
 * - Via CLI: php public/check-ip.php
 */

// Check if running from CLI or browser
$isCLI = php_sapi_name() === 'cli';

// Get all network interfaces
function getLocalIPs() {
    $ips = [];
    
    // Get hostname
    $hostname = gethostname();
    
    // Get IP by hostname
    $ip = gethostbyname($hostname);
    if ($ip !== $hostname) {
        $ips[] = [
            'type' => 'Hostname IP',
            'ip' => $ip,
            'hostname' => $hostname
        ];
    }
    
    // Try to get all IPs from $_SERVER
    if (isset($_SERVER['SERVER_ADDR'])) {
        $ips[] = [
            'type' => 'Server IP',
            'ip' => $_SERVER['SERVER_ADDR'],
            'hostname' => null
        ];
    }
    
    // Get IPv4 addresses (Windows)
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $output = [];
        exec('ipconfig', $output);
        $currentAdapter = '';
        foreach ($output as $line) {
            if (preg_match('/^([^:]+):$/', $line, $matches)) {
                $currentAdapter = trim($matches[1]);
            }
            if (preg_match('/IPv4 Address[^:]*:\s*(\d+\.\d+\.\d+\.\d+)/i', $line, $matches)) {
                $ips[] = [
                    'type' => $currentAdapter ?: 'Network Adapter',
                    'ip' => $matches[1],
                    'hostname' => null
                ];
            }
        }
    } else {
        // Linux/Mac
        $output = [];
        exec("hostname -I 2>/dev/null || ifconfig | grep 'inet ' | awk '{print $2}'", $output);
        foreach ($output as $line) {
            $ip = trim($line);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE)) {
                continue; // Skip public IPs
            }
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $ips[] = [
                    'type' => 'Network Interface',
                    'ip' => $ip,
                    'hostname' => null
                ];
            }
        }
    }
    
    // Remove duplicates
    $uniqueIPs = [];
    $seenIPs = [];
    foreach ($ips as $ipInfo) {
        if (!in_array($ipInfo['ip'], $seenIPs) && 
            filter_var($ipInfo['ip'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) &&
            strpos($ipInfo['ip'], '127.') !== 0 && 
            strpos($ipInfo['ip'], '169.254.') !== 0) {
            $uniqueIPs[] = $ipInfo;
            $seenIPs[] = $ipInfo['ip'];
        }
    }
    
    return $uniqueIPs;
}

$localIPs = getLocalIPs();

if (!$isCLI) {
    header('Content-Type: text/html; charset=utf-8');
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Check IP Address</title>";
    echo "<style>
        body{font-family:Arial,sans-serif;max-width:800px;margin:50px auto;padding:20px;background:#f5f5f5;}
        .container{background:white;padding:30px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.1);}
        h1{color:#333;border-bottom:3px solid #4CAF50;padding-bottom:10px;}
        .ip-item{background:#e8f5e9;padding:15px;margin:10px 0;border-radius:5px;border-left:4px solid #4CAF50;}
        .ip-address{font-size:24px;font-weight:bold;color:#2e7d32;margin:10px 0;}
        .ip-type{color:#666;font-size:14px;}
        .copy-btn{background:#4CAF50;color:white;border:none;padding:8px 16px;border-radius:5px;cursor:pointer;margin-top:10px;margin-right:5px;}
        .copy-btn:hover{background:#45a049;}
        .info{background:#e3f2fd;padding:15px;border-radius:5px;margin:20px 0;border-left:4px solid #2196F3;}
        .warning{background:#fff3cd;padding:15px;border-radius:5px;margin:20px 0;border-left:4px solid #ffc107;}
        code{background:#f5f5f5;padding:2px 6px;border-radius:3px;font-family:monospace;}
    </style></head><body>";
    echo "<div class='container'>";
    echo "<h1>🌐 Kiểm tra IP máy tính</h1>";
    
    if (empty($localIPs)) {
        echo "<div class='warning'><strong>⚠️ Không tìm thấy IP!</strong><br>";
        echo "Vui lòng chạy lệnh <code>ipconfig</code> trong Command Prompt để xem IP.</div>";
    } else {
        echo "<div class='info'><strong>ℹ️ Hướng dẫn:</strong><br>";
        echo "1. Chọn IP có dạng <code>192.168.x.x</code> hoặc <code>10.x.x.x</code><br>";
        echo "2. Copy IP và cập nhật vào file <code>authService.ts</code> và <code>apiClient.ts</code><br>";
        echo "3. URL API sẽ là: <code>http://[IP]:8000</code></div>";
        
        echo "<h2>📋 Danh sách IP:</h2>";
        foreach ($localIPs as $index => $ipInfo) {
            $apiUrl = "http://{$ipInfo['ip']}:8000";
            echo "<div class='ip-item'>";
            echo "<div class='ip-type'>{$ipInfo['type']}</div>";
            echo "<div class='ip-address' id='ip-{$index}'>{$ipInfo['ip']}</div>";
            if ($ipInfo['hostname']) {
                echo "<div class='ip-type'>Hostname: {$ipInfo['hostname']}</div>";
            }
            echo "<div style='margin-top:10px;'>";
            echo "<strong>API URL:</strong> <code id='url-{$index}'>{$apiUrl}</code><br>";
            echo "<button class='copy-btn' onclick=\"copyToClipboard('{$ipInfo['ip']}')\">📋 Copy IP</button> ";
            echo "<button class='copy-btn' onclick=\"copyToClipboard('{$apiUrl}')\">📋 Copy URL</button>";
            echo "</div>";
            echo "</div>";
        }
    }
    
    echo "<div class='info' style='margin-top:30px;'>";
    echo "<strong>🔧 Cách cập nhật IP trong code:</strong><br>";
    echo "1. Mở file: <code>mobile-app-delivery-new/app/services/authService.ts</code><br>";
    echo "2. Tìm dòng: <code>const COMPUTER_IP = \"192.168.1.140\"</code><br>";
    echo "3. Thay bằng IP mới: <code>const COMPUTER_IP = \"[IP_MỚI]\"</code><br>";
    echo "4. Làm tương tự với file: <code>mobile-app-delivery-new/app/services/apiClient.ts</code>";
    echo "</div>";
    
    echo "<script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                alert('Đã copy: ' + text);
            }, function(err) {
                var textarea = document.createElement('textarea');
                textarea.value = text;
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                alert('Đã copy: ' + text);
            });
        }
    </script>";
    
    echo "</div></body></html>";
} else {
    // CLI mode
    echo "=== Kiểm tra IP máy tính ===\n\n";
    if (empty($localIPs)) {
        echo "❌ Không tìm thấy IP!\n";
        echo "Vui lòng chạy lệnh: ipconfig\n";
    } else {
        echo "📋 Danh sách IP:\n\n";
        foreach ($localIPs as $ipInfo) {
            echo "Type: {$ipInfo['type']}\n";
            echo "IP: {$ipInfo['ip']}\n";
            if ($ipInfo['hostname']) {
                echo "Hostname: {$ipInfo['hostname']}\n";
            }
            echo "API URL: http://{$ipInfo['ip']}:8000\n";
            echo str_repeat("-", 50) . "\n";
        }
    }
}

