<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Domain\Payments\Payment;
use PDO;

class CODPaymentService implements PaymentServiceInterface
{
    private Payment $paymentModel;
    private PDO $db;

    public function __construct(PDO $db, Payment $paymentModel)
    {
        $this->db = $db;
        $this->paymentModel = $paymentModel;
    }

    public function createPayment(array $data): array
    {
        // COD: chờ thu khi giao — xác nhận sau khi shipper hoàn tất (OrderController).
        $paymentData = [
            'order_id' => $data['order_id'],
            'method' => 'cod',
            'paid_amount' => 0.00,
            'status' => 'pending',
        ];

        $paymentId = $this->paymentModel->create($paymentData);

        return [
            'payment_id' => $paymentId,
            'status' => 'pending',
            'method' => 'cod',
        ];
    }

    public function processPayment(int $paymentId, array $data = []): bool
    {
        return true;
    }

    public function verifyPayment(array $data): bool
    {
        // COD doesn't need verification
        return true;
    }
}

