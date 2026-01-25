<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Domain\Payments\Payment;
use PDO;

class VietQRPaymentService implements PaymentServiceInterface
{
    private Payment $paymentModel;
    private PDO $db;
    private array $config;

    public function __construct(PDO $db, Payment $paymentModel, array $config = [])
    {
        $this->db = $db;
        $this->paymentModel = $paymentModel;
        $this->config = $config;
    }

    public function createPayment(array $data): array
    {
        // Create payment record
        $paymentData = [
            'order_id' => $data['order_id'],
            'method' => 'vietqr',
            'paid_amount' => $data['amount'],
            'status' => 'pending',
        ];

        $paymentId = $this->paymentModel->create($paymentData);

        // Generate VietQR URL with bank account info
        $qrData = $this->generateVietQRCode($data['order_id'], $data['amount']);
        $paymentUrl = $qrData['qr_url'] ?? null;
        
        // Set expiration (24 hours from now)
        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

        // Update payment with URL and expiration
        if ($paymentUrl) {
            $this->paymentModel->updatePaymentUrl($paymentId, $paymentUrl);
        }
        $this->paymentModel->updateExpiresAt($paymentId, $expiresAt);

        return [
            'payment_id' => $paymentId,
            'payment_url' => $paymentUrl,
            'expires_at' => $expiresAt,
            'status' => 'pending',
            'method' => 'vietqr',
            'bank_account' => $qrData['bank_account'] ?? null,
            'message' => 'Quét mã QR để thanh toán. Thông tin chuyển khoản sẽ hiện lên app ngân hàng.',
        ];
    }

    /**
     * Generate VietQR code URL with bank account info
     */
    private function generateVietQRCode(int $orderId, float $amount): array
    {
        // Get bank account info from config or env
        $accountNumber = $this->config['bank_account']['account_number'] ?? $_ENV['CASSO_BANK_ACCOUNT'] ?? '';
        $bankCode = $this->config['bank_account']['bank_code'] ?? $_ENV['CASSO_BANK_CODE'] ?? '';
        $accountName = $this->config['bank_account']['account_name'] ?? $_ENV['CASSO_BANK_ACCOUNT_NAME'] ?? '';
        $bankName = $this->config['bank_account']['bank_name'] ?? $_ENV['CASSO_BANK_NAME'] ?? '';
        
        // Strip quotes from env values (if any)
        $accountNumber = trim($accountNumber, '"\'');
        $bankCode = trim($bankCode, '"\'');
        $accountName = trim($accountName, '"\'');
        $bankName = trim($bankName, '"\'');
        
        // Fallback to hardcoded values if config is empty
        if (empty($accountNumber) || empty($bankCode)) {
            $accountNumber = '9353126350';
            $bankCode = 'VCB';
            $accountName = 'LE DAT THANH';
            $bankName = 'Vietcombank';
        }
        
        // Ensure accountName is not empty
        if (empty($accountName)) {
            $accountName = $bankName ?: 'THANH TOAN';
        }
        
        $content = "ORDER_{$orderId}";
        $amountVND = (int)$amount;
        
        // Generate VietQR URL
        // Format: https://img.vietqr.io/image/[BANK_CODE]-[ACCOUNT_NUMBER]-[TEMPLATE].jpg?amount=[AMOUNT]&addInfo=[CONTENT]&accountName=[ACCOUNT_NAME]
        $qrUrl = sprintf(
            'https://img.vietqr.io/image/%s-%s-compact2.jpg?amount=%d&addInfo=%s&accountName=%s',
            $bankCode,
            $accountNumber,
            $amountVND,
            urlencode($content),
            urlencode($accountName)
        );
        
        return [
            'qr_url' => $qrUrl,
            'bank_account' => [
                'account_number' => $accountNumber,
                'account_name' => $accountName,
                'bank_name' => $bankName,
                'bank_code' => $bankCode,
            ]
        ];
    }

    public function processPayment(int $paymentId, array $data = []): bool
    {
        return true;
    }

    public function verifyPayment(array $data): bool
    {
        return true;
    }
}

