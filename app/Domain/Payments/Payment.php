<?php

declare(strict_types=1);

namespace App\Domain\Payments;

use App\Core\Database;

class Payment extends Database
{
    protected string $table = 'payments';

    public function getByOrderId(int $orderId): ?array
    {
        $sql = "
            SELECT * FROM {$this->table} 
            WHERE order_id = ? 
            ORDER BY created_at DESC 
            LIMIT 1
        ";
        
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute([$orderId]);
        
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function getByOrderIdWithDetails(int $orderId): ?array
    {
        $sql = "
            SELECT 
                p.*,
                o.invoice_number,
                o.total_amount,
                o.status as order_status
            FROM {$this->table} p
            LEFT JOIN orders o ON p.order_id = o.order_id
            WHERE p.order_id = ?
            ORDER BY p.created_at DESC
        ";
        
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute([$orderId]);
        
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function create(array $data): bool
    {
        $sql = "
            INSERT INTO {$this->table} (
                order_id, payment_method, amount, status, 
                transaction_id, payment_details, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
        ";
        
        $stmt = $this->getConnection()->prepare($sql);
        return $stmt->execute([
            $data['order_id'],
            $data['payment_method'],
            $data['amount'],
            $data['status'],
            $data['transaction_id'] ?? null,
            $data['payment_details'] ? json_encode($data['payment_details']) : null
        ]);
    }

    public function updateStatus(int $paymentId, string $status, ?string $transactionId = null): bool
    {
        $sql = "
            UPDATE {$this->table} 
            SET status = ?, transaction_id = ?, updated_at = NOW()
            WHERE payment_id = ?
        ";
        
        $stmt = $this->getConnection()->prepare($sql);
        return $stmt->execute([$status, $transactionId, $paymentId]);
    }

    public function isConfirmed(int $orderId): bool
    {
        $payment = $this->getByOrderId($orderId);
        return $payment && $payment['status'] === 'confirmed';
    }

    public function getPaymentHistory(int $orderId): array
    {
        $sql = "
            SELECT * FROM {$this->table} 
            WHERE order_id = ? 
            ORDER BY created_at DESC
        ";
        
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute([$orderId]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
