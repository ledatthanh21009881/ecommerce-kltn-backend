<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Domain\Payments\Payment;
use PDO;

class PayOSPaymentService implements PaymentServiceInterface
{
    private Payment $paymentModel;
    private PDO $db;
    private string $clientId;
    private string $apiKey;
    private string $checksumKey;
    private string $apiUrl;
    private array $config;

    public function __construct(PDO $db, Payment $paymentModel, array $config)
    {
        $this->db = $db;
        $this->paymentModel = $paymentModel;
        $this->clientId = $config['client_id'] ?? '';
        $this->apiKey = $config['api_key'] ?? '';
        $this->checksumKey = $config['checksum_key'] ?? '';
        $this->apiUrl = rtrim($config['api_url'] ?? 'https://api-merchant.payos.vn', '/');
        $this->config = $config;
    }

    /**
     * Create signature for PayOS request: amount, cancelUrl, description, orderCode, returnUrl (alphabet order)
     */
    private function createSignature(int $orderCode, int $amount, string $description, string $cancelUrl, string $returnUrl): string
    {
        $data = "amount={$amount}&cancelUrl={$cancelUrl}&description={$description}&orderCode={$orderCode}&returnUrl={$returnUrl}";
        return hash_hmac('sha256', $data, $this->checksumKey);
    }

    /**
     * Verify webhook signature: sort payload by key, build key=value&..., HMAC-SHA256 with checksumKey
     */
    public function verifyWebhookSignature(array $data, string $signature): bool
    {
        if (empty($this->checksumKey)) {
            return true;
        }
        $sorted = $data;
        ksort($sorted);
        $parts = [];
        foreach ($sorted as $key => $value) {
            if ($key === 'signature') {
                continue;
            }
            $parts[] = $key . '=' . (is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value);
        }
        $dataStr = implode('&', $parts);
        $expected = hash_hmac('sha256', $dataStr, $this->checksumKey);
        return hash_equals($expected, $signature);
    }

    public function createPayment(array $data): array
    {
        $orderId = (int)($data['order_id'] ?? 0);
        $amount = (float)($data['amount'] ?? 0);
        $amountVnd = (int) round($amount);

        $paymentData = [
            'order_id' => $orderId,
            'method' => 'payos',
            'paid_amount' => $amount,
            'status' => 'pending',
        ];
        $paymentId = $this->paymentModel->create($paymentData);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
        $this->paymentModel->updateExpiresAt($paymentId, $expiresAt);

        $baseUrl = $this->config['base_url'] ?? ($_ENV['APP_URL'] ?? 'http://localhost:3000');
        $returnUrl = $baseUrl . '/checkout/payment/success?order_id=' . $orderId . '&payment_id=' . $paymentId;
        $cancelUrl = $baseUrl . '/checkout/payment/' . $paymentId;

        $description = 'ORDER_' . $orderId;
        if (mb_strlen($description) > 9) {
            $description = (string) $orderId;
        }
        $signature = $this->createSignature($orderId, $amountVnd, $description, $cancelUrl, $returnUrl);

        // Body theo tài liệu PayOS: orderCode, amount, description, items, cancelUrl, returnUrl, signature
        $body = [
            'orderCode' => $orderId,
            'amount' => $amountVnd,
            'description' => $description,
            'items' => [
                ['name' => 'Đơn hàng #' . $orderId, 'quantity' => 1, 'price' => $amountVnd],
            ],
            'cancelUrl' => $cancelUrl,
            'returnUrl' => $returnUrl,
            'signature' => $signature,
        ];
        $expiredAtTs = time() + 24 * 3600;
        $body['expiredAt'] = $expiredAtTs;

        $endpoint = $this->apiUrl . '/v2/payment-requests';
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($body),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-client-id: ' . $this->clientId,
                'x-api-key: ' . $this->apiKey,
            ],
            CURLOPT_TIMEOUT => 15,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $paymentUrl = null;
        $qrCode = null;
        $bankAccount = null;
        if ($httpCode === 200 && $response) {
            $json = json_decode($response, true);
            if (is_array($json) && isset($json['code']) && $json['code'] === '00' && !empty($json['data'])) {
                $d = $json['data'];
                $paymentUrl = $d['checkoutUrl'] ?? $d['paymentLink'] ?? null;
                $qrCode = $d['qrCode'] ?? null;
                if ($paymentUrl) {
                    $this->paymentModel->updatePaymentUrl($paymentId, $paymentUrl);
                }
                $bankAccount = [
                    'account_number' => $d['accountNumber'] ?? '',
                    'account_name' => $d['accountName'] ?? '',
                    'bank_name' => $d['bin'] ?? '',
                ];
            }
        }

        return [
            'payment_id' => $paymentId,
            'payment_url' => $paymentUrl,
            'qr_code' => $qrCode,
            'expires_at' => $expiresAt,
            'status' => 'pending',
            'method' => 'payos',
            'message' => 'Quét mã QR hoặc mở link để thanh toán. Hệ thống sẽ tự động xác nhận khi thanh toán thành công.',
            'bank_account' => $bankAccount,
        ];
    }

    public function processPayment(int $paymentId, array $data = []): bool
    {
        return true;
    }

    public function verifyPayment(array $data): bool
    {
        $signature = $data['signature'] ?? '';
        unset($data['signature']);
        return $this->verifyWebhookSignature($data, $signature);
    }
}
