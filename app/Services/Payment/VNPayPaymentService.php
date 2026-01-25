<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Domain\Payments\Payment;
use PDO;

class VNPayPaymentService implements PaymentServiceInterface
{
    private Payment $paymentModel;
    private PDO $db;
    private array $config;

    public function __construct(PDO $db, Payment $paymentModel, array $config)
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
            'method' => 'vnpay',
            'paid_amount' => $data['amount'],
            'status' => 'pending',
        ];

        $paymentId = $this->paymentModel->create($paymentData);

        // Generate VNPay payment URL
        $paymentUrl = $this->generatePaymentUrl($paymentId, $data);

        // Update payment with URL
        $this->paymentModel->updatePaymentUrl($paymentId, $paymentUrl);

        return [
            'payment_id' => $paymentId,
            'payment_url' => $paymentUrl,
            'status' => 'pending',
            'method' => 'vnpay'
        ];
    }

    private function generatePaymentUrl(int $paymentId, array $data): string
    {
        $vnp_TmnCode = $this->config['tmn_code'];
        $vnp_HashSecret = $this->config['hash_secret'];
        $vnp_Url = $this->config['url'];
        $vnp_ReturnUrl = $this->config['return_url'];

        $vnp_TxnRef = 'ORDER_' . $data['order_id'] . '_' . $paymentId . '_' . time();
        $vnp_OrderInfo = 'Thanh toan don hang #' . $data['order_id'];
        $vnp_OrderType = 'other';
        $vnp_Amount = (int)($data['amount'] * 100); // VNPay uses cents
        $vnp_Locale = 'vn';
        $vnp_IpAddr = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $vnp_CreateDate = date('YmdHis');

        $inputData = [
            "vnp_Version" => "2.1.0",
            "vnp_TmnCode" => $vnp_TmnCode,
            "vnp_Amount" => $vnp_Amount,
            "vnp_Command" => "pay",
            "vnp_CreateDate" => $vnp_CreateDate,
            "vnp_CurrCode" => "VND",
            "vnp_IpAddr" => $vnp_IpAddr,
            "vnp_Locale" => $vnp_Locale,
            "vnp_OrderInfo" => $vnp_OrderInfo,
            "vnp_OrderType" => $vnp_OrderType,
            "vnp_ReturnUrl" => $vnp_ReturnUrl,
            "vnp_TxnRef" => $vnp_TxnRef,
        ];

        ksort($inputData);
        $query = http_build_query($inputData);
        $vnp_SecureHash = hash_hmac('sha512', $query, $vnp_HashSecret);

        // Store transaction_id and secure hash
        $this->paymentModel->updateStatus($paymentId, 'pending', $vnp_TxnRef);
        $stmt = $this->db->prepare("UPDATE payments SET vnp_secure_hash = ? WHERE payment_id = ?");
        $stmt->execute([$vnp_SecureHash, $paymentId]);

        return $vnp_Url . '?' . $query . '&vnp_SecureHash=' . $vnp_SecureHash;
    }

    public function processPayment(int $paymentId, array $data = []): bool
    {
        // VNPay processes via IPN callback
        return true;
    }

    public function verifyPayment(array $data): bool
    {
        $vnp_SecureHash = $data['vnp_SecureHash'] ?? '';
        unset($data['vnp_SecureHash']);

        ksort($data);
        $query = http_build_query($data);
        $vnp_HashSecret = $this->config['hash_secret'];
        $secureHash = hash_hmac('sha512', $query, $vnp_HashSecret);

        return $secureHash === $vnp_SecureHash;
    }
}

