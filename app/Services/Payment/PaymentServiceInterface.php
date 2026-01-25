<?php

declare(strict_types=1);

namespace App\Services\Payment;

interface PaymentServiceInterface
{
    /**
     * Create payment and return payment data
     * 
     * @param array $data Payment data (order_id, amount, etc.)
     * @return array Payment data with payment_id, payment_url (if applicable), etc.
     */
    public function createPayment(array $data): array;

    /**
     * Process payment (for services that need async processing)
     * 
     * @param int $paymentId
     * @param array $data Additional data
     * @return bool Success
     */
    public function processPayment(int $paymentId, array $data = []): bool;

    /**
     * Verify payment (for IPN/callback verification)
     * 
     * @param array $data Callback data
     * @return bool Is valid
     */
    public function verifyPayment(array $data): bool;
}

