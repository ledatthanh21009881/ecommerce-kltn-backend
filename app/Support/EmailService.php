<?php
declare(strict_types=1);

namespace App\Support;

use Exception;

class EmailService
{
    private $smtpHost;
    private $smtpPort;
    private $smtpUsername;
    private $smtpPassword;
    private $fromEmail;
    private $fromName;

    public function __construct()
    {
        $this->smtpHost = $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com';
        $this->smtpPort = $_ENV['SMTP_PORT'] ?? 587;
        $this->smtpUsername = $_ENV['SMTP_USERNAME'] ?? 'thanhle02032003@gmail.com';
        $this->smtpPassword = $_ENV['SMTP_PASSWORD'] ?? 'tobg ofxi kkcs tscv';
        $this->fromEmail = $_ENV['FROM_EMAIL'] ?? 'thanhle02032003@gmail.com';
        $this->fromName = $_ENV['FROM_NAME'] ?? 'ShopSwift';
    }

    /**
     * Gửi email với file đính kèm
     */
    public function sendWithAttachment(string $to, string $subject, string $body, string $attachmentPath, string $attachmentName): bool
    {
        try {
            // Sử dụng PHP mail() function với headers
            $headers = [
                'MIME-Version: 1.0',
                'Content-Type: multipart/mixed; boundary="boundary"',
                'From: ' . $this->fromName . ' <' . $this->fromEmail . '>',
                'Reply-To: ' . $this->fromEmail,
                'X-Mailer: PHP/' . phpversion()
            ];

            $boundary = 'boundary';
            
            // Email body
            $message = "--{$boundary}\r\n";
            $message .= "Content-Type: text/html; charset=UTF-8\r\n";
            $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
            $message .= $body . "\r\n\r\n";

            // File đính kèm
            if (file_exists($attachmentPath)) {
                $attachment = file_get_contents($attachmentPath);
                $attachment = base64_encode($attachment);
                
                $message .= "--{$boundary}\r\n";
                $message .= "Content-Type: application/pdf; name=\"{$attachmentName}\"\r\n";
                $message .= "Content-Disposition: attachment; filename=\"{$attachmentName}\"\r\n";
                $message .= "Content-Transfer-Encoding: base64\r\n\r\n";
                $message .= chunk_split($attachment) . "\r\n";
            }

            // Sử dụng PHPMailer thay vì mail()
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            
            // Enable debug mode if in development
            if (($_ENV['APP_ENV'] ?? 'production') === 'development' || defined('EMAIL_DEBUG')) {
                $mail->SMTPDebug = \PHPMailer\PHPMailer\SMTP::DEBUG_SERVER;
                $mail->Debugoutput = function($str, $level) {
                    error_log("PHPMailer Debug [Level {$level}]: {$str}");
                };
            }
            
            // Cấu hình SMTP
            $mail->isSMTP();
            $mail->Host = $this->smtpHost;
            $mail->SMTPAuth = true;
            $mail->Username = $this->smtpUsername;
            // Remove spaces from password (Gmail App Password should not have spaces)
            $mail->Password = str_replace(' ', '', $this->smtpPassword);
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $this->smtpPort;
            
            // Cấu hình charset UTF-8
            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';
            
            // Cấu hình người gửi và người nhận
            $mail->setFrom($this->fromEmail, $this->fromName);
            $mail->addAddress($to);
            
            // Nội dung email
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;
            
            // Thêm file đính kèm
            if (file_exists($attachmentPath)) {
                $mail->addAttachment($attachmentPath, basename($attachmentPath));
            }
            
            // Gửi email
            $result = $mail->send();
            
            if (!$result) {
                error_log("Failed to send email to: {$to}");
                return false;
            }

            return true;

        } catch (Exception $e) {
            error_log("Email sending error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Gửi email đơn giản (không có file đính kèm)
     */
    public function send(string $to, string $subject, string $body): bool
    {
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            
            // Enable debug mode if in development
            if (($_ENV['APP_ENV'] ?? 'production') === 'development' || defined('EMAIL_DEBUG')) {
                $mail->SMTPDebug = \PHPMailer\PHPMailer\SMTP::DEBUG_SERVER;
                $mail->Debugoutput = function($str, $level) {
                    error_log("PHPMailer Debug [Level {$level}]: {$str}");
                };
            }
            
            // Cấu hình SMTP
            $mail->isSMTP();
            $mail->Host = $this->smtpHost;
            $mail->SMTPAuth = true;
            $mail->Username = $this->smtpUsername;
            // Remove spaces from password (Gmail App Password should not have spaces)
            $mail->Password = str_replace(' ', '', $this->smtpPassword);
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $this->smtpPort;
            
            // Cấu hình charset UTF-8
            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';
            
            // Cấu hình người gửi và người nhận
            $mail->setFrom($this->fromEmail, $this->fromName);
            $mail->addAddress($to);
            
            // Nội dung email
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;
            
            // Gửi email
            $result = $mail->send();
            
            if (!$result) {
                error_log("Failed to send email to: {$to}. Error: " . $mail->ErrorInfo);
                return false;
            }

            return true;

        } catch (Exception $e) {
            error_log("Email sending error: " . $e->getMessage());
            if (isset($mail)) {
                error_log("PHPMailer Error Info: " . $mail->ErrorInfo);
            }
            return false;
        }
    }

    /**
     * Gửi email thông báo đơn hàng
     */
    public function sendOrderNotification(string $to, array $order): bool
    {
        $subject = 'Xác nhận đơn hàng #' . $order['invoice_number'];
        $body = $this->getOrderNotificationTemplate($order);
        
        return $this->send($to, $subject, $body);
    }

    /**
     * Gửi email hóa đơn với file PDF đính kèm
     */
    public function sendInvoiceEmail(string $to, array $order): bool
    {
        try {
            // Tạo hóa đơn PDF
            $invoiceService = new \App\Services\InvoiceService();
            $pdfContent = $invoiceService->generateInvoicePDF($order);
            
            // Lưu PDF tạm thời
            $tempDir = sys_get_temp_dir();
            $filename = 'invoice_' . $order['invoice_number'] . '.pdf';
            $filepath = $tempDir . '/' . $filename;
            file_put_contents($filepath, $pdfContent);
            
            // Gửi email với file đính kèm
            $subject = 'Hóa đơn đơn hàng #' . $order['invoice_number'] . ' - ShopSwift';
            $body = $this->getInvoiceEmailTemplate($order);
            
            $result = $this->sendWithAttachment($to, $subject, $body, $filepath, $filename);
            
            // Xóa file tạm
            if (file_exists($filepath)) {
                unlink($filepath);
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Error sending invoice email: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Template email thông báo đơn hàng
     */
    private function getOrderNotificationTemplate(array $order): string
    {
        return "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <h2 style='color: #333;'>Cảm ơn bạn đã đặt hàng tại ShopSwift!</h2>
            
            <div style='background: #f9f9f9; padding: 20px; border-radius: 5px; margin: 20px 0;'>
                <h3 style='color: #666; margin-top: 0;'>Thông tin đơn hàng</h3>
                <p><strong>Mã đơn hàng:</strong> {$order['invoice_number']}</p>
                <p><strong>Ngày đặt:</strong> " . date('d/m/Y H:i', strtotime($order['created_at'])) . "</p>
                <p><strong>Tổng thanh toán:</strong> " . number_format((float)$order['total_amount'], 0, ',', '.') . " ₫</p>
                <p><strong>Trạng thái:</strong> " . ucfirst($order['status']) . "</p>
            </div>
            
            <p>Chúng tôi sẽ xử lý đơn hàng của bạn trong thời gian sớm nhất.</p>
            <p>Bạn sẽ nhận được email cập nhật khi đơn hàng được xử lý.</p>
            
            <div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;'>
                <p style='color: #666; font-size: 14px;'>
                    Trân trọng,<br>
                    <strong>ShopSwift Team</strong>
                </p>
            </div>
        </div>
        ";
    }

    /**
     * Gửi email reset password với link
     */
    public function sendPasswordResetLinkEmail(string $to, string $accountName, string $resetLink): bool
    {
        $subject = 'Reset Password - ShopSwift';
        $body = $this->getPasswordResetLinkTemplate($accountName, $resetLink);
        
        return $this->send($to, $subject, $body);
    }

    /**
     * Template email reset password với link
     */
    private function getPasswordResetLinkTemplate(string $accountName, string $resetLink): string
    {
        return "
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
    }

    /**
     * Gửi email reset password với mật khẩu mới (legacy)
     */
    public function sendPasswordResetEmail(string $to, string $newPassword): bool
    {
        $subject = 'Mật khẩu mới - ShopSwift';
        $body = $this->getPasswordResetTemplate($newPassword);
        
        return $this->send($to, $subject, $body);
    }

    /**
     * Template email hóa đơn
     */
    private function getInvoiceEmailTemplate(array $order): string
    {
        $customer = $order['customer'] ?? [];
        $customerName = ($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? '');
        
        return "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <h2 style='color: #333;'>Hóa đơn đơn hàng #{$order['invoice_number']}</h2>
            
            <p>Xin chào {$customerName},</p>
            
            <p>Cảm ơn bạn đã đặt hàng tại ShopSwift! Dưới đây là hóa đơn chi tiết cho đơn hàng của bạn.</p>
            
            <div style='background: #f9f9f9; padding: 20px; border-radius: 5px; margin: 20px 0;'>
                <h3 style='color: #666; margin-top: 0;'>Thông tin đơn hàng</h3>
                <p><strong>Mã đơn hàng:</strong> {$order['invoice_number']}</p>
                <p><strong>Ngày đặt:</strong> " . date('d/m/Y H:i', strtotime($order['created_at'])) . "</p>
                <p><strong>Tổng thanh toán:</strong> " . number_format((float)$order['total_amount'], 0, ',', '.') . " ₫</p>
                <p><strong>Trạng thái:</strong> " . ucfirst($order['status']) . "</p>
            </div>
            
            <p>Hóa đơn chi tiết được đính kèm trong file PDF. Vui lòng kiểm tra file đính kèm.</p>
            
            <p>Nếu bạn có bất kỳ câu hỏi nào về đơn hàng, vui lòng liên hệ với chúng tôi.</p>
            
            <div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;'>
                <p style='color: #666; font-size: 14px;'>
                    Trân trọng,<br>
                    <strong>ShopSwift Team</strong>
                </p>
            </div>
        </div>
        ";
    }

    /**
     * Template email reset password với mật khẩu mới (legacy)
     */
    private function getPasswordResetTemplate(string $newPassword): string
    {
        return "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <h2 style='color: #333;'>Mật khẩu mới của bạn</h2>
            
            <div style='background: #f9f9f9; padding: 20px; border-radius: 5px; margin: 20px 0;'>
                <p><strong>Mật khẩu mới:</strong> {$newPassword}</p>
            </div>
            
            <p>Vui lòng đăng nhập với mật khẩu mới và thay đổi mật khẩu trong phần cài đặt tài khoản.</p>
            
            <div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;'>
                <p style='color: #666; font-size: 14px;'>
                    Trân trọng,<br>
                    <strong>ShopSwift Team</strong>
                </p>
            </div>
        </div>
        ";
    }

    /**
     * Gửi email OTP code cho mobile app
     */
    public function sendOTPEmail(string $to, string $accountName, string $otpCode): bool
    {
        $subject = 'Mã xác nhận đặt lại mật khẩu - ShopSwift';
        $body = $this->getOTPEmailTemplate($accountName, $otpCode);
        
        return $this->send($to, $subject, $body);
    }

    /**
     * Template email OTP code
     */
    private function getOTPEmailTemplate(string $accountName, string $otpCode): string
    {
        return "
        <html>
        <head>
            <title>Mã xác nhận đặt lại mật khẩu</title>
        </head>
        <body style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
            <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; border-radius: 10px 10px 0 0; text-align: center;'>
                <h1 style='color: white; margin: 0;'>ShopSwift</h1>
            </div>
            
            <div style='background: #ffffff; padding: 30px; border: 1px solid #e0e0e0; border-top: none; border-radius: 0 0 10px 10px;'>
                <h2 style='color: #333; margin-top: 0;'>Mã xác nhận đặt lại mật khẩu</h2>
                
                <p>Xin chào <strong>{$accountName}</strong>,</p>
                
                <p>Bạn đã yêu cầu đặt lại mật khẩu cho tài khoản của mình. Vui lòng sử dụng mã xác nhận sau:</p>
                
                <div style='background: #f5f5f5; border: 2px dashed #667eea; padding: 20px; border-radius: 8px; margin: 30px 0; text-align: center;'>
                    <p style='margin: 0; color: #666; font-size: 14px;'>Mã xác nhận của bạn</p>
                    <h1 style='margin: 10px 0; color: #667eea; font-size: 36px; letter-spacing: 8px; font-family: monospace;'>{$otpCode}</h1>
                </div>
                
                <p style='color: #e74c3c; font-weight: bold;'>⚠️ Lưu ý quan trọng:</p>
                <ul style='color: #666; line-height: 1.8;'>
                    <li>Mã xác nhận này chỉ có hiệu lực trong <strong>10 phút</strong></li>
                    <li>Không chia sẻ mã này với bất kỳ ai</li>
                    <li>Nếu bạn không yêu cầu đặt lại mật khẩu, vui lòng bỏ qua email này</li>
                </ul>
                
                <p style='color: #666; margin-top: 30px;'>Nếu bạn gặp vấn đề, vui lòng liên hệ với bộ phận hỗ trợ.</p>
            </div>
            
            <div style='text-align: center; margin-top: 20px; padding-top: 20px; border-top: 1px solid #e0e0e0;'>
                <p style='color: #999; font-size: 12px; margin: 0;'>
                    Trân trọng,<br>
                    <strong style='color: #667eea;'>ShopSwift Team</strong>
                </p>
                <p style='color: #999; font-size: 11px; margin-top: 10px;'>
                    Email này được gửi tự động, vui lòng không trả lời.
                </p>
            </div>
        </body>
        </html>
        ";
    }
}
