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
        // Create payment record and auto-confirm
        $paymentData = [
            'order_id' => $data['order_id'],
            'method' => 'cod',
            'paid_amount' => $data['amount'],
            'status' => 'confirmed', // Auto-confirm for COD
        ];

        $paymentId = $this->paymentModel->create($paymentData);

        // Update confirmed_at
        $this->paymentModel->updateStatus($paymentId, 'confirmed');

        return [
            'payment_id' => $paymentId,
            'status' => 'confirmed',
            'method' => 'cod'
        ];
    }

    public function processPayment(int $paymentId, array $data = []): bool
    {
        // COD is already confirmed
        return true;
    }

    public function verifyPayment(array $data): bool
    {
        // COD doesn't need verification
        return true;
    }
}

