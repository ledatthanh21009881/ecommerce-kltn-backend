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
        // #region agent log (commented out)
        // $debugLogPath = __DIR__ . '/../../.cursor/debug.log';
        // @mkdir(dirname($debugLogPath), 0777, true);
        // file_put_contents($debugLogPath, json_encode(['hypothesisId'=>'H1','location'=>'Payment:updateStatus:START','message'=>'updateStatus called','data'=>['paymentId'=>$paymentId,'status'=>$status,'transactionId'=>$transactionId],'timestamp'=>round(microtime(true)*1000),'sessionId'=>'debug-session'])."\n", FILE_APPEND);
        // #endregion
        
        $sql = "
            UPDATE {$this->table} 
            SET status = ?, transaction_id = ?, confirmed_at = CASE WHEN ? = 'confirmed' THEN NOW() ELSE confirmed_at END, updated_at = NOW()
            WHERE payment_id = ?
        ";
        
        $stmt = $this->getConnection()->prepare($sql);
        $result = $stmt->execute([$status, $transactionId, $status, $paymentId]);
        $rowsAffected = $stmt->rowCount();
        
        // #region agent log (commented out)
        // file_put_contents($debugLogPath, json_encode(['hypothesisId'=>'H1','location'=>'Payment:updateStatus:RESULT','message'=>'updateStatus result','data'=>['paymentId'=>$paymentId,'status'=>$status,'result'=>$result,'rowsAffected'=>$rowsAffected],'timestamp'=>round(microtime(true)*1000),'sessionId'=>'debug-session'])."\n", FILE_APPEND);
        // #endregion
        
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
