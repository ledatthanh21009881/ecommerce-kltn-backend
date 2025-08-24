<?php
declare(strict_types=1);

namespace App\Domain\Vouchers;

use PDO;
use Exception;

class VoucherRepository
{
    public function __construct(private PDO $pdo) {}

    public function findAll(int $page = 1, int $limit = 10, string $search = ''): array
    {
        $offset = ($page - 1) * $limit;
        
        $whereConditions = ['1=1'];
        $params = [];
        
        if (!empty($search)) {
            $whereConditions[] = "(code LIKE ? OR discount_type LIKE ?)";
            $searchTerm = "%{$search}%";
            $params = [$searchTerm, $searchTerm];
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM vouchers WHERE {$whereClause}";
        $stmt = $this->pdo->prepare($countSql);
        $stmt->execute($params);
        $total = $stmt->fetch()['total'];
        
        // Get vouchers
        $sql = "
            SELECT * FROM vouchers 
            WHERE {$whereClause}
            ORDER BY created_at DESC 
            LIMIT ? OFFSET ?
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge($params, [$limit, $offset]));
        $vouchers = $stmt->fetchAll();
        
        return [
            'items' => array_map(fn($v) => Voucher::fromArray($v), $vouchers),
            'total' => $total,
            'page' => $page,
            'limit' => $limit
        ];
    }

    public function findById(int $id): ?Voucher
    {
        $stmt = $this->pdo->prepare("SELECT * FROM vouchers WHERE voucher_id = ?");
        $stmt->execute([$id]);
        $data = $stmt->fetch();
        
        return $data ? Voucher::fromArray($data) : null;
    }

    public function findByCode(string $code): ?Voucher
    {
        $stmt = $this->pdo->prepare("SELECT * FROM vouchers WHERE code = ?");
        $stmt->execute([$code]);
        $data = $stmt->fetch();
        
        return $data ? Voucher::fromArray($data) : null;
    }

    public function create(Voucher $voucher): int
    {
        $sql = "
            INSERT INTO vouchers (
                code, discount_amount, discount_type, max_usage, version,
                usage_per_user, min_order_total, start_date, end_date, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $voucher->code,
            $voucher->discount_amount,
            $voucher->discount_type,
            $voucher->max_usage,
            $voucher->version,
            $voucher->usage_per_user,
            $voucher->min_order_total,
            $voucher->start_date,
            $voucher->end_date,
            $voucher->status
        ]);
        
        return (int) $this->pdo->lastInsertId();
    }

    public function update(Voucher $voucher): bool
    {
        $sql = "
            UPDATE vouchers SET 
                code = ?, discount_amount = ?, discount_type = ?, 
                max_usage = ?, version = ?, usage_per_user = ?, 
                min_order_total = ?, start_date = ?, end_date = ?, 
                status = ?, updated_at = NOW()
            WHERE voucher_id = ?
        ";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $voucher->code,
            $voucher->discount_amount,
            $voucher->discount_type,
            $voucher->max_usage,
            $voucher->version,
            $voucher->usage_per_user,
            $voucher->min_order_total,
            $voucher->start_date,
            $voucher->end_date,
            $voucher->status,
            $voucher->voucher_id
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM vouchers WHERE voucher_id = ?");
        return $stmt->execute([$id]);
    }

    public function getStats(): array
    {
        // Total vouchers
        $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM vouchers");
        $total = $stmt->fetch()['total'];
        
        // Active vouchers
        $stmt = $this->pdo->query("
            SELECT COUNT(*) as active 
            FROM vouchers 
            WHERE status = 'active' 
            AND start_date <= CURDATE() 
            AND end_date >= CURDATE()
        ");
        $active = $stmt->fetch()['active'];
        
        // Expired vouchers
        $stmt = $this->pdo->query("
            SELECT COUNT(*) as expired 
            FROM vouchers 
            WHERE end_date < CURDATE()
        ");
        $expired = $stmt->fetch()['expired'];
        
        // Upcoming vouchers
        $stmt = $this->pdo->query("
            SELECT COUNT(*) as upcoming 
            FROM vouchers 
            WHERE start_date > CURDATE()
        ");
        $upcoming = $stmt->fetch()['upcoming'];
        
        // Vouchers by type
        $stmt = $this->pdo->query("
            SELECT discount_type, COUNT(*) as count
            FROM vouchers 
            GROUP BY discount_type
        ");
        $byType = $stmt->fetchAll();
        
        return [
            'total' => $total,
            'active' => $active,
            'expired' => $expired,
            'upcoming' => $upcoming,
            'by_type' => $byType
        ];
    }

    public function getUsageStats(int $voucherId): array
    {
        // Get usage summary
        $stmt = $this->pdo->prepare("
            SELECT total_used_count, last_used_at
            FROM voucher_usage_summary 
            WHERE voucher_id = ?
        ");
        $stmt->execute([$voucherId]);
        $summary = $stmt->fetch();
        
        // Get recent usages
        $stmt = $this->pdo->prepare("
            SELECT vu.*, u.first_name, u.last_name, u.email
            FROM voucher_usages vu
            JOIN customers c ON vu.customer_id = c.user_id
            JOIN users u ON c.user_id = u.user_id
            WHERE vu.voucher_id = ?
            ORDER BY vu.used_at DESC
            LIMIT 10
        ");
        $stmt->execute([$voucherId]);
        $recentUsages = $stmt->fetchAll();
        
        return [
            'summary' => $summary ?: ['total_used_count' => 0, 'last_used_at' => null],
            'recent_usages' => $recentUsages
        ];
    }
}
