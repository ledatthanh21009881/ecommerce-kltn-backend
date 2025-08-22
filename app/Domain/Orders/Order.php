<?php
declare(strict_types=1);

namespace App\Domain\Orders;

use App\Core\Model;
use PDO;
use Exception;

class Order extends Model
{
    protected string $table = 'orders';
    protected string $primaryKey = 'order_id';
    
    protected array $fillable = [
        'customer_id',
        'address_id',
        'shipping_method_id',
        'voucher_id',
        'voucher_code_applied',
        'discount_amount_applied',
        'total_amount',
        'shipping_fee',
        'cod_amount',
        'status',
        'note',
        'internal_note',
        'estimated_delivery_at',
        'invoice_number',
        'shipping_address_snapshot',
        'shipping_method_name_snapshot',
        'voucher_summary_snapshot'
    ];

    public function getAll(array $filters = [], int $limit = 20, int $offset = 0): array
    {
        $sql = "
            SELECT 
                o.*,
                c.loyalty_points,
                c.total_orders,
                u.first_name,
                u.last_name,
                u.email,
                u.phone,
                sm.name as shipping_method_name,
                v.code as voucher_code,
                v.discount_type as voucher_discount_type,
                v.discount_amount as voucher_discount_amount,
                (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.order_id) as item_count
            FROM {$this->table} o
            LEFT JOIN customers c ON o.customer_id = c.user_id
            LEFT JOIN users u ON c.user_id = u.user_id
            LEFT JOIN shipping_methods sm ON o.shipping_method_id = sm.shipping_method_id
            LEFT JOIN vouchers v ON o.voucher_id = v.voucher_id
        ";
        
        $whereConditions = [];
        $params = [];
        
        if (!empty($filters['status'])) {
            $whereConditions[] = "o.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['customer_id'])) {
            $whereConditions[] = "o.customer_id = ?";
            $params[] = $filters['customer_id'];
        }
        
        if (!empty($filters['search'])) {
            $whereConditions[] = "(o.invoice_number LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
            $searchTerm = "%{$filters['search']}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if (!empty($filters['date_from'])) {
            $whereConditions[] = "DATE(o.created_at) >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $whereConditions[] = "DATE(o.created_at) <= ?";
            $params[] = $filters['date_to'];
        }
        
        if (!empty($whereConditions)) {
            $sql .= " WHERE " . implode(" AND ", $whereConditions);
        }
        
        $sql .= " ORDER BY o.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCount(array $filters = []): int
    {
        $sql = "
            SELECT COUNT(*) 
            FROM {$this->table} o
            LEFT JOIN customers c ON o.customer_id = c.user_id
            LEFT JOIN users u ON c.user_id = u.user_id
        ";
        
        $whereConditions = [];
        $params = [];
        
        if (!empty($filters['status'])) {
            $whereConditions[] = "o.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['customer_id'])) {
            $whereConditions[] = "o.customer_id = ?";
            $params[] = $filters['customer_id'];
        }
        
        if (!empty($filters['search'])) {
            $whereConditions[] = "(o.invoice_number LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
            $searchTerm = "%{$filters['search']}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if (!empty($filters['date_from'])) {
            $whereConditions[] = "DATE(o.created_at) >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $whereConditions[] = "DATE(o.created_at) <= ?";
            $params[] = $filters['date_to'];
        }
        
        if (!empty($whereConditions)) {
            $sql .= " WHERE " . implode(" AND ", $whereConditions);
        }
        
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function findById(int $id): ?array
    {
        $sql = "
            SELECT 
                o.*,
                c.loyalty_points,
                c.total_orders,
                u.first_name,
                u.last_name,
                u.email,
                u.phone,
                sm.name as shipping_method_name,
                v.code as voucher_code,
                v.discount_type as voucher_discount_type,
                v.discount_amount as voucher_discount_amount
            FROM {$this->table} o
            LEFT JOIN customers c ON o.customer_id = c.user_id
            LEFT JOIN users u ON c.user_id = u.user_id
            LEFT JOIN shipping_methods sm ON o.shipping_method_id = sm.shipping_method_id
            LEFT JOIN vouchers v ON o.voucher_id = v.voucher_id
            WHERE o.order_id = ?
        ";
        
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function getByIdWithDetails(int $id): ?array
    {
        $order = $this->findById($id);
        if (!$order) {
            return null;
        }
        
        // Get order items
        $order['items'] = $this->getOrderItems($id);
        
        // Get order status logs
        $order['status_logs'] = $this->getOrderStatusLogs($id);
        
        // Get shipping tracking
        $order['tracking'] = $this->getShippingTracking($id);
        
        // Get payment info
        $order['payment'] = $this->getPaymentInfo($id);
        
        return $order;
    }

    public function getOrderItems(int $orderId): array
    {
        $sql = "
            SELECT 
                oi.*,
                p.product_name,
                p.slug as product_slug,
                pv.sku,
                s.size_name,
                (SELECT pi.url FROM product_images pi WHERE pi.product_id = p.product_id AND pi.is_main = 1 LIMIT 1) as product_image
            FROM order_items oi
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

    public function getOrderStatusLogs(int $orderId): array
    {
        $sql = "
            SELECT 
                osl.*,
                u.first_name,
                u.last_name
            FROM order_status_logs osl
            LEFT JOIN users u ON osl.changed_by = u.user_id
            WHERE osl.order_id = ?
            ORDER BY osl.changed_at DESC
        ";
        
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute([$orderId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getShippingTracking(int $orderId): ?array
    {
        $sql = "
            SELECT 
                st.*,
                s.vehicle_info,
                s.rating,
                u.first_name,
                u.last_name,
                u.phone
            FROM shipping_tracking st
            LEFT JOIN shippers s ON st.shipper_id = s.user_id
            LEFT JOIN users u ON s.user_id = u.user_id
            WHERE st.order_id = ?
        ";
        
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute([$orderId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function getPaymentInfo(int $orderId): ?array
    {
        $sql = "
            SELECT * FROM payments 
            WHERE order_id = ?
            ORDER BY created_at DESC
            LIMIT 1
        ";
        
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute([$orderId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function create(array $data): int
    {
        $this->getConnection()->beginTransaction();
        
        try {
            // Generate invoice number
            $data['invoice_number'] = $this->generateInvoiceNumber();
            
            $fields = array_keys($data);
            $placeholders = str_repeat('?,', count($fields) - 1) . '?';
            
            $sql = "INSERT INTO {$this->table} (" . implode(',', $fields) . ") VALUES ($placeholders)";
            
            $stmt = $this->getConnection()->prepare($sql);
            $stmt->execute(array_values($data));
            
            $orderId = (int) $this->getConnection()->lastInsertId();
            
            // Log initial status
            $this->logStatusChange($orderId, $data['status'], $data['customer_id'], 'Đơn hàng được tạo');
            
            $this->getConnection()->commit();
            return $orderId;
        } catch (Exception $e) {
            $this->getConnection()->rollBack();
            throw $e;
        }
    }

    public function update(int $id, array $data): bool
    {
        $this->getConnection()->beginTransaction();
        
        try {
            $fields = [];
            foreach (array_keys($data) as $field) {
                $fields[] = "$field = ?";
            }
            
            $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE {$this->primaryKey} = ?";
            
            $values = array_values($data);
            $values[] = $id;
            
            $stmt = $this->getConnection()->prepare($sql);
            $result = $stmt->execute($values);
            
            // Log status change if status was updated
            if (isset($data['status'])) {
                $this->logStatusChange($id, $data['status'], $data['customer_id'] ?? null, $data['note'] ?? 'Cập nhật trạng thái');
            }
            
            $this->getConnection()->commit();
            return $result;
        } catch (Exception $e) {
            $this->getConnection()->rollBack();
            throw $e;
        }
    }

    public function updateStatus(int $id, string $status, int $changedBy, string $reason = ''): bool
    {
        $this->getConnection()->beginTransaction();
        
        try {
            $sql = "UPDATE {$this->table} SET status = ?, updated_at = NOW() WHERE {$this->primaryKey} = ?";
            $stmt = $this->getConnection()->prepare($sql);
            $result = $stmt->execute([$status, $id]);
            
            if ($result) {
                $this->logStatusChange($id, $status, $changedBy, $reason);
            }
            
            $this->getConnection()->commit();
            return $result;
        } catch (Exception $e) {
            $this->getConnection()->rollBack();
            throw $e;
        }
    }

    public function assignShipper(int $orderId, int $shipperId, int $assignedBy): bool
    {
        $this->getConnection()->beginTransaction();
        
        try {
            // Check if tracking record exists
            $stmt = $this->getConnection()->prepare("SELECT tracking_id FROM shipping_tracking WHERE order_id = ?");
            $stmt->execute([$orderId]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                // Update existing tracking
                $sql = "UPDATE shipping_tracking SET shipper_id = ?, last_updated = NOW() WHERE order_id = ?";
                $stmt = $this->getConnection()->prepare($sql);
                $stmt->execute([$shipperId, $orderId]);
            } else {
                // Create new tracking record
                $sql = "INSERT INTO shipping_tracking (order_id, shipper_id, last_updated) VALUES (?, ?, NOW())";
                $stmt = $this->getConnection()->prepare($sql);
                $stmt->execute([$orderId, $shipperId]);
            }
            
            // Log the assignment
            $this->logActivity($orderId, 'assign', $assignedBy, null, ['shipper_id' => $shipperId]);
            
            $this->getConnection()->commit();
            return true;
        } catch (Exception $e) {
            $this->getConnection()->rollBack();
            throw $e;
        }
    }

    public function getStatistics(): array
    {
        $sql = "
            SELECT 
                COUNT(*) as total_orders,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
                SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing_orders,
                SUM(CASE WHEN status = 'shipping' THEN 1 ELSE 0 END) as shipping_orders,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders,
                SUM(CASE WHEN status = 'returned' THEN 1 ELSE 0 END) as returned_orders,
                SUM(total_amount) as total_revenue
            FROM {$this->table}
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ";
        
        $stmt = $this->getConnection()->query($sql);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getAvailableShippers(): array
    {
        $sql = "
            SELECT 
                s.user_id,
                s.vehicle_info,
                s.rating,
                s.on_time_delivery_pct,
                s.total_delivered,
                s.is_available,
                u.first_name,
                u.last_name,
                u.phone
            FROM shippers s
            LEFT JOIN users u ON s.user_id = u.user_id
            WHERE s.is_available = 1 AND s.status = 'active'
            ORDER BY s.rating DESC, s.on_time_delivery_pct DESC
        ";
        
        $stmt = $this->getConnection()->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function generateInvoiceNumber(): string
    {
        $year = date('Y');
        $month = date('m');
        
        $sql = "
            SELECT COUNT(*) as count 
            FROM {$this->table} 
            WHERE YEAR(created_at) = ? AND MONTH(created_at) = ?
        ";
        
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute([$year, $month]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $count = $result['count'] + 1;
        return "INV-{$year}-" . str_pad($count, 3, '0', STR_PAD_LEFT);
    }

    private function logStatusChange(int $orderId, string $status, int $changedBy, string $reason = ''): void
    {
        $sql = "
            INSERT INTO order_status_logs (order_id, status, changed_by, reason, changed_at)
            VALUES (?, ?, ?, ?, NOW())
        ";
        
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute([$orderId, $status, $changedBy, $reason]);
    }



    public function canEdit(int $orderId): bool
    {
        $order = $this->findById($orderId);
        if (!$order) {
            return false;
        }
        
        // Only allow editing if order is pending or processing
        return in_array($order['status'], ['pending', 'processing']);
    }

    public function canCancel(int $orderId): bool
    {
        $order = $this->findById($orderId);
        if (!$order) {
            return false;
        }
        
        // Allow cancellation if order is pending, processing, or shipping
        return in_array($order['status'], ['pending', 'processing', 'shipping']);
    }

    public function updateInvoiceUrl(int $orderId, string $invoiceUrl): bool
    {
        $sql = "
            UPDATE {$this->table} 
            SET invoice_url = ?, updated_at = NOW()
            WHERE order_id = ?
        ";
        
        $stmt = $this->getConnection()->prepare($sql);
        return $stmt->execute([$invoiceUrl, $orderId]);
    }

    public function logActivity(int $orderId, string $action, int $changedBy, ?array $dataBefore = null, ?array $dataAfter = null): void
    {
        $sql = "
            INSERT INTO activity_logs (entity_type, entity_id, action, changed_by, data_before, data_after, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ";
        
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute([
            'order',
            $orderId,
            $action,
            $changedBy,
            $dataBefore ? json_encode($dataBefore) : null,
            $dataAfter ? json_encode($dataAfter) : null
        ]);
    }
}
