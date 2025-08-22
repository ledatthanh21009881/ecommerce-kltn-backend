<?php
declare(strict_types=1);

namespace App\Domain\Orders;

use App\Core\Model;
use PDO;
use Exception;

class OrderItem extends Model
{
    protected string $table = 'order_items';
    protected string $primaryKey = 'item_id';
    
    protected array $fillable = [
        'order_id',
        'variant_id',
        'quantity',
        'unit_price',
        'product_name_snapshot'
    ];

    public function getByOrderId(int $orderId): array
    {
        $sql = "
            SELECT 
                oi.*,
                p.product_name,
                p.slug as product_slug,
                pv.sku,
                s.size_name,
                (SELECT pi.url FROM product_images pi WHERE pi.product_id = p.product_id AND pi.is_main = 1 LIMIT 1) as product_image
            FROM {$this->table} oi
            LEFT JOIN product_variants pv ON oi.variant_id = pv.variant_id
            LEFT JOIN products p ON pv.product_id = p.product_id
            LEFT JOIN sizes s ON pv.size_id = s.size_id
            WHERE oi.order_id = ?
            ORDER BY oi.created_at ASC
        ";
        
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute([$orderId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data): int
    {
        $fields = array_keys($data);
        $placeholders = str_repeat('?,', count($fields) - 1) . '?';
        
        $sql = "INSERT INTO {$this->table} (" . implode(',', $fields) . ") VALUES ($placeholders)";
        
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute(array_values($data));
        
        return (int) $this->getConnection()->lastInsertId();
    }

    public function createMultiple(array $items): bool
    {
        $this->getConnection()->beginTransaction();
        
        try {
            foreach ($items as $item) {
                $this->create($item);
            }
            
            $this->getConnection()->commit();
            return true;
        } catch (Exception $e) {
            $this->getConnection()->rollBack();
            throw $e;
        }
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        foreach (array_keys($data) as $field) {
            $fields[] = "$field = ?";
        }
        
        $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE {$this->primaryKey} = ?";
        
        $values = array_values($data);
        $values[] = $id;
        
        $stmt = $this->getConnection()->prepare($sql);
        return $stmt->execute($values);
    }

    public function deleteByOrderId(int $orderId): bool
    {
        $stmt = $this->getConnection()->prepare("DELETE FROM {$this->table} WHERE order_id = ?");
        return $stmt->execute([$orderId]);
    }

    public function calculateOrderTotal(int $orderId): float
    {
        $sql = "SELECT SUM(quantity * unit_price) as total FROM {$this->table} WHERE order_id = ?";
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute([$orderId]);
        return (float) $stmt->fetchColumn();
    }

    public function validateStock(int $variantId, int $quantity): bool
    {
        $sql = "SELECT stock_quantity FROM product_variants WHERE variant_id = ?";
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute([$variantId]);
        $availableStock = (int) $stmt->fetchColumn();
        
        return $availableStock >= $quantity;
    }

    public function updateStock(int $variantId, int $quantity, bool $isDecrease = true): bool
    {
        $operator = $isDecrease ? '-' : '+';
        $sql = "UPDATE product_variants SET stock_quantity = stock_quantity {$operator} ? WHERE variant_id = ?";
        $stmt = $this->getConnection()->prepare($sql);
        return $stmt->execute([$quantity, $variantId]);
    }
}
