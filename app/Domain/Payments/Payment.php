<?php

declare(strict_types=1);

namespace App\Domain\Payments;

use App\Core\Model;

class Payment extends Model
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

    public function create(array $data): int
    {
        $sql = "
            INSERT INTO {$this->table} (
                order_id, method, paid_amount, status, 
                transaction_id, bank_code, vnp_secure_hash, callback_payload,
                payment_url, expires_at, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ";
        
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute([
            $data['order_id'],
            $data['method'],
            $data['paid_amount'] ?? $data['amount'] ?? 0.00,
            $data['status'],
            $data['transaction_id'] ?? null,
            $data['bank_code'] ?? null,
            $data['vnp_secure_hash'] ?? null,
            !empty($data['callback_payload']) ? json_encode($data['callback_payload']) : null,
            $data['payment_url'] ?? null,
            $data['expires_at'] ?? null
        ]);
        return (int)$this->getConnection()->lastInsertId();
    }

    public function updateStatus(int $paymentId, string $status, ?string $transactionId = null): bool
    {
        $sql = "
            UPDATE {$this->table} 
            SET status = ?, transaction_id = ?, confirmed_at = CASE WHEN ? = 'confirmed' THEN NOW() ELSE confirmed_at END, updated_at = NOW()
            WHERE payment_id = ?
        ";
        
        $stmt = $this->getConnection()->prepare($sql);
        $result = $stmt->execute([$status, $transactionId, $status, $paymentId]);
        return $result;
    }

    public function getByPaymentId(int $paymentId): ?array
    {
        $sql = "
            SELECT * FROM {$this->table} 
            WHERE payment_id = ? 
            LIMIT 1
        ";
        
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute([$paymentId]);
        
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function updatePaymentUrl(int $paymentId, string $paymentUrl): bool
    {
        $sql = "
            UPDATE {$this->table} 
            SET payment_url = ?, updated_at = NOW()
            WHERE payment_id = ?
        ";
        
        $stmt = $this->getConnection()->prepare($sql);
        return $stmt->execute([$paymentUrl, $paymentId]);
    }

    public function updatePayOsDisplayData(int $paymentId, ?string $paymentUrl, ?string $qrCode): bool
    {
        $sql = "
            UPDATE {$this->table}
            SET payment_url = COALESCE(?, payment_url),
                qr_code = COALESCE(?, qr_code),
                updated_at = NOW()
            WHERE payment_id = ?
        ";

        $stmt = $this->getConnection()->prepare($sql);
        return $stmt->execute([$paymentUrl, $qrCode, $paymentId]);
    }

    /**
     * Resolve stored PayOS QR string from row (column or legacy callback_payload).
     */
    public function extractQrCode(array $payment): ?string
    {
        if (!empty($payment['qr_code']) && is_string($payment['qr_code'])) {
            return $payment['qr_code'];
        }

        if (empty($payment['callback_payload'])) {
            return null;
        }

        $decoded = json_decode((string) $payment['callback_payload'], true);
        if (!is_array($decoded)) {
            return null;
        }

        return $decoded['qrCode'] ?? $decoded['qr_code'] ?? $decoded['payos_qr_code'] ?? null;
    }

    public function updateExpiresAt(int $paymentId, ?string $expiresAt): bool
    {
        $sql = "
            UPDATE {$this->table} 
            SET expires_at = ?, updated_at = NOW()
            WHERE payment_id = ?
        ";
        
        $stmt = $this->getConnection()->prepare($sql);
        return $stmt->execute([$expiresAt, $paymentId]);
    }

    public function isConfirmed(int $orderId): bool
    {
        $payment = $this->getByOrderId($orderId);
        return $payment && $payment['status'] === 'confirmed';
    }

    /**
     * Sau khi shipper giao xong (shipping completed): ghi nhận đã thu COD.
     */
    public function confirmCodCollection(int $paymentId, float $paidAmount): bool
    {
        $sql = "
            UPDATE {$this->table}
            SET status = 'confirmed',
                paid_amount = ?,
                confirmed_at = NOW(),
                updated_at = NOW()
            WHERE payment_id = ?
              AND method = 'cod'
              AND status = 'pending'
        ";
        $stmt = $this->getConnection()->prepare($sql);

        return $stmt->execute([$paidAmount, $paymentId]);
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

    /**
     * Get all payments for admin list with order/customer info.
     * Returns rows with: payment_id, order_id, method, paid_amount, status, transaction_id,
     * created_at, confirmed_at, callback_payload, customer_name (from orders -> users).
     */
    public function getAllForAdmin(array $filters = [], int $limit = 100, int $offset = 0): array
    {
        $sql = "
            SELECT 
                p.payment_id,
                p.order_id,
                p.method,
                p.paid_amount,
                p.status,
                p.transaction_id,
                p.created_at,
                p.confirmed_at,
                p.expires_at,
                p.callback_payload,
                TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) AS customer_name
            FROM {$this->table} p
            LEFT JOIN orders o ON p.order_id = o.order_id
            LEFT JOIN customers c ON o.customer_id = c.user_id
            LEFT JOIN users u ON c.user_id = u.user_id
        ";
        $whereConditions = [];
        $params = [];

        if (!empty($filters['status'])) {
            $whereConditions[] = "p.status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['method'])) {
            $whereConditions[] = "p.method = ?";
            $params[] = $filters['method'];
        }
        if (!empty($filters['search'])) {
            $term = "%{$filters['search']}%";
            $parts = ["p.transaction_id LIKE ?", "u.first_name LIKE ?", "u.last_name LIKE ?", "u.email LIKE ?"];
            if (is_numeric($filters['search'])) {
                $parts[] = "p.order_id = ?";
                $params[] = (int) $filters['search'];
            }
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $whereConditions[] = "(" . implode(" OR ", $parts) . ")";
        }

        if (count($whereConditions) > 0) {
            $sql .= " WHERE " . implode(" AND ", $whereConditions);
        }
        $sql .= " ORDER BY p.created_at DESC LIMIT " . (int) $limit . " OFFSET " . (int) $offset;

        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
