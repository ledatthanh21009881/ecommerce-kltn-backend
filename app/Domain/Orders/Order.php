<?php
declare(strict_types=1);

namespace App\Domain\Orders;

use App\Core\Model;
use App\Support\ShipperCapacity;
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
        'shipping_status',
        'shipping_status_updated_at',
        'note',
        'internal_note',
        'estimated_delivery_at',
        'invoice_number',
        'shipping_address_snapshot',
        'shipping_method_name_snapshot',
        'voucher_summary_snapshot'
    ];

    public const SHIPPING_STATUSES = [
        'new_request',
        'accepted',
        'picked_up',
        'delivering',
        'arrived',
        'delivered',
        'completed',
        'rejected'
    ];

    public const SHIPPING_TRANSITIONS = [
        'new_request' => ['accepted', 'rejected'],
        'accepted' => ['picked_up', 'rejected'],
        'picked_up' => ['delivering'],
        'delivering' => ['arrived'],
        'arrived' => ['delivered'],
        'delivered' => ['completed'],
        'completed' => [],
        'rejected' => []
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
        
        if (!empty($filters['shipping_status'])) {
            $whereConditions[] = "o.shipping_status = ?";
            $params[] = $filters['shipping_status'];
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
        
        if (!empty($filters['shipping_status'])) {
            $whereConditions[] = "o.shipping_status = ?";
            $params[] = $filters['shipping_status'];
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

        // Shipping workflow timeline
        $order['delivery_events'] = $this->getDeliveryEvents($id);
        $order['delivery_proofs'] = $this->getDeliveryProofs($id);
        
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
            // Ensure every new order stores immutable shipping snapshot.
            if (empty($data['shipping_address_snapshot']) && !empty($data['address_id'])) {
                $snapshot = $this->buildShippingAddressSnapshot((int) $data['address_id']);
                if ($snapshot !== null) {
                    $data['shipping_address_snapshot'] = json_encode($snapshot, JSON_UNESCAPED_UNICODE);
                }
            }

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
            $this->logShippingEvent(
                $orderId,
                null,  // statusFrom
                $data['shipping_status'] ?? 'new_request',  // statusTo
                null,  // shipperId = null (chưa có shipper khi tạo order mới)
                ['note' => 'Shipping workflow initialized']
            );
            
            $this->getConnection()->commit();
            return $orderId;
        } catch (Exception $e) {
            $this->getConnection()->rollBack();
            throw $e;
        }
    }

    /**
     * Build order shipping snapshot from addresses table.
     */
    private function buildShippingAddressSnapshot(int $addressId): ?array
    {
        if ($addressId <= 0) {
            return null;
        }

        $sql = "
            SELECT
                receiver_name,
                phone,
                address_line,
                ward,
                district,
                province,
                lat,
                lng
            FROM addresses
            WHERE address_id = ?
            LIMIT 1
        ";

        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute([$addressId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return [
            'receiver_name' => $row['receiver_name'] ?? '',
            'phone' => $row['phone'] ?? '',
            'address_line' => $row['address_line'] ?? '',
            'ward' => $row['ward'] ?? '',
            'district' => $row['district'] ?? '',
            'province' => $row['province'] ?? '',
            'lat' => isset($row['lat']) ? (float)$row['lat'] : null,
            'lng' => isset($row['lng']) ? (float)$row['lng'] : null,
        ];
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

    /**
     * Gỡ shipper khỏi đơn (sau từ chối) để auto-gán lại shipper khác.
     */
    public function clearShipperAssignment(int $orderId): void
    {
        $stmt = $this->getConnection()->prepare('DELETE FROM shipping_tracking WHERE order_id = ?');
        $stmt->execute([$orderId]);
    }

    /**
     * Tự gán shipper rảnh nhất (is_available + active). Giữ đơn ở pending/processing — không chuyển shipping.
     * Trả về shipper_id khi gán thành công, null nếu bỏ qua hoặc thất bại.
     */
    public function autoAssignBestAvailableShipper(
        int $orderId,
        int $assignedBy,
        string $note = 'Auto-assigned',
        ?int $excludeShipperId = null
    ): ?int
    {
        $order = $this->find($orderId);
        if (!$order) {
            error_log("[Order] Auto-assign #{$orderId}: order not found");
            return null;
        }

        if (!in_array($order['status'], ['pending', 'processing'], true)) {
            error_log("[Order] Auto-assign #{$orderId}: skip, status={$order['status']}");
            return null;
        }

        $trackingStmt = $this->getConnection()->prepare(
            'SELECT shipper_id FROM shipping_tracking WHERE order_id = ? LIMIT 1'
        );
        $trackingStmt->execute([$orderId]);
        $existing = $trackingStmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($existing) && !empty($existing['shipper_id'])) {
            error_log("[Order] Auto-assign #{$orderId}: already has shipper_id={$existing['shipper_id']}");
            return (int) $existing['shipper_id'];
        }

        $shipperSql = "
            SELECT
                s.user_id,
                COUNT(CASE WHEN o.status IN ('processing', 'shipping') THEN 1 END) AS active_orders
            FROM shippers s
            LEFT JOIN shipping_tracking st ON st.shipper_id = s.user_id
            LEFT JOIN orders o ON o.order_id = st.order_id
            WHERE s.is_available = 1 AND s.status = 'active'
        ";
        $params = [];
        if ($excludeShipperId !== null && $excludeShipperId > 0) {
            $shipperSql .= ' AND s.user_id != ?';
            $params[] = $excludeShipperId;
        }
        $maxActive = ShipperCapacity::maxActiveOrders();
        $shipperSql .= '
            GROUP BY s.user_id, s.rating, s.on_time_delivery_pct
            HAVING active_orders < ?
            ORDER BY active_orders ASC, s.rating DESC, s.on_time_delivery_pct DESC
            LIMIT 1
        ';
        $params[] = $maxActive;
        $shipperStmt = $this->getConnection()->prepare($shipperSql);
        $shipperStmt->execute($params);
        $shipper = $shipperStmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($shipper) || empty($shipper['user_id'])) {
            error_log(
                "[Order] Auto-assign #{$orderId}: no available active shipper under capacity (max="
                . $maxActive
                . ')'
            );
            return null;
        }

        $shipperId = (int) $shipper['user_id'];
        if (!$this->assignShipper($orderId, $shipperId, $assignedBy)) {
            error_log("[Order] Auto-assign #{$orderId}: assignShipper failed for shipper_id={$shipperId}");
            return null;
        }

        if ($order['status'] === 'pending') {
            $this->updateStatus($orderId, 'processing', $assignedBy, $note);
        }

        try {
            $this->forceShippingStatus($orderId, 'new_request', $shipperId, ['note' => $note]);
        } catch (Exception $e) {
            error_log("[Order] Auto-assign #{$orderId}: forceShippingStatus failed: " . $e->getMessage());
        }

        error_log("[Order] Auto-assign #{$orderId}: success shipper_id={$shipperId}");
        return $shipperId;
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
                COALESCE(SUM(CASE WHEN status = 'completed' THEN total_amount ELSE 0 END), 0) as total_revenue
            FROM {$this->table}
        ";
        
        $stmt = $this->getConnection()->query($sql);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'total_orders' => (int) ($row['total_orders'] ?? 0),
            'pending_orders' => (int) ($row['pending_orders'] ?? 0),
            'processing_orders' => (int) ($row['processing_orders'] ?? 0),
            'shipping_orders' => (int) ($row['shipping_orders'] ?? 0),
            'completed_orders' => (int) ($row['completed_orders'] ?? 0),
            'cancelled_orders' => (int) ($row['cancelled_orders'] ?? 0),
            'returned_orders' => (int) ($row['returned_orders'] ?? 0),
            'total_revenue' => (float) ($row['total_revenue'] ?? 0),
        ];
    }

    public function getAvailableShippers(): array
    {
        $maxActive = ShipperCapacity::maxActiveOrders();
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
                u.phone,
                COUNT(CASE WHEN o.status IN ('processing', 'shipping') THEN 1 END) AS active_orders,
                ? AS max_active_orders
            FROM shippers s
            LEFT JOIN users u ON s.user_id = u.user_id
            LEFT JOIN shipping_tracking st ON st.shipper_id = s.user_id
            LEFT JOIN orders o ON o.order_id = st.order_id
            WHERE s.is_available = 1 AND s.status = 'active'
            GROUP BY s.user_id, s.vehicle_info, s.rating, s.on_time_delivery_pct, s.total_delivered,
                     s.is_available, u.first_name, u.last_name, u.phone
            ORDER BY active_orders ASC, s.rating DESC, s.on_time_delivery_pct DESC
        ";

        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute([$maxActive]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $active = (int) ($row['active_orders'] ?? 0);
            $row['has_capacity'] = $active < $maxActive;
        }

        return $rows;
    }

    private function generateInvoiceNumber(): string
    {
        $year = date('Y');
        $month = date('m');
        $conn = $this->getConnection();
        
        // Use lock to prevent race conditions (already in transaction from create method)
        // Find the maximum invoice number for this month with lock
        $sql = "
            SELECT invoice_number 
            FROM {$this->table} 
            WHERE invoice_number LIKE ? 
            AND YEAR(created_at) = ? 
            AND MONTH(created_at) = ?
            ORDER BY invoice_number DESC 
            LIMIT 1
            FOR UPDATE
        ";
        
        $pattern = "INV-{$year}-%";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$pattern, $year, $month]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $count = 1;
        if ($result && !empty($result['invoice_number'])) {
            // Extract number from existing invoice (e.g., "INV-2025-001" -> 1)
            $existingInvoice = $result['invoice_number'];
            if (preg_match('/INV-\d{4}-(\d+)/', $existingInvoice, $matches)) {
                $count = (int)$matches[1] + 1;
            }
        }
        
        // Retry logic to avoid duplicates (handle race conditions)
        $maxRetries = 100;
        $retry = 0;
        while ($retry < $maxRetries) {
            $invoiceNumber = "INV-{$year}-" . str_pad((string)$count, 3, '0', STR_PAD_LEFT);
            
            // Check if this invoice number already exists (with lock)
            $checkSql = "SELECT COUNT(*) FROM {$this->table} WHERE invoice_number = ? FOR UPDATE";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->execute([$invoiceNumber]);
            $exists = $checkStmt->fetchColumn() > 0;
            
            if (!$exists) {
                return $invoiceNumber;
            }
            
            // If exists, try next number
            $count++;
            $retry++;
        }
        
        // Fallback: use timestamp suffix if all retries failed
        $timestamp = time();
        return "INV-{$year}-" . str_pad((string)$count, 3, '0', STR_PAD_LEFT) . "-" . substr((string)$timestamp, -4);
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

    public function getOrdersByShipper(int $shipperId, array $filters = [], int $limit = 20, int $offset = 0): array
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
                st.confirmed_delivery_at,
                st.current_lat,
                st.current_lng,
                st.photo_proof_url,
                (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.order_id) as item_count
            FROM shipping_tracking st
            INNER JOIN {$this->table} o ON st.order_id = o.order_id
            LEFT JOIN customers c ON o.customer_id = c.user_id
            LEFT JOIN users u ON c.user_id = u.user_id
            LEFT JOIN shipping_methods sm ON o.shipping_method_id = sm.shipping_method_id
            LEFT JOIN vouchers v ON o.voucher_id = v.voucher_id
            WHERE st.shipper_id = ?
        ";
        
        $params = [$shipperId];
        
        // Filter by shipping status first (fallback to order status for backward compatibility)
        $shippingStatusFilter = $filters['shipping_status'] ?? $filters['status'] ?? null;
        if ($shippingStatusFilter) {
            $sql .= " AND o.shipping_status = ?";
            $params[] = $shippingStatusFilter;
        } else {
            $sql .= " AND o.shipping_status IN ('new_request','accepted','picked_up','delivering','arrived','delivered')";
        }
        
        $sql .= " ORDER BY o.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute($params);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get order items for each order
        foreach ($orders as &$order) {
            $order['items'] = $this->getOrderItems((int)$order['order_id']);
            
            // Parse shipping address snapshot if exists
            if (!empty($order['shipping_address_snapshot'])) {
                $addressData = json_decode($order['shipping_address_snapshot'], true);
                if ($addressData) {
                    $order['shipping_address'] = $addressData;
                }
            }

            $order['delivery_events'] = $this->getDeliveryEvents((int)$order['order_id']);
            $order['delivery_proofs'] = $this->getDeliveryProofs((int)$order['order_id']);
        }
        
        return $orders;
    }

    public function getOrdersByShipperCount(int $shipperId, array $filters = []): int
    {
        $sql = "
            SELECT COUNT(*) 
            FROM shipping_tracking st
            INNER JOIN {$this->table} o ON st.order_id = o.order_id
            WHERE st.shipper_id = ?
        ";
        
        $params = [$shipperId];
        
        $shippingStatusFilter = $filters['shipping_status'] ?? $filters['status'] ?? null;
        if ($shippingStatusFilter) {
            $sql .= " AND o.shipping_status = ?";
            $params[] = $shippingStatusFilter;
        } else {
            $sql .= " AND o.shipping_status IN ('new_request','accepted','picked_up','delivering','arrived','delivered')";
        }
        
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function updateShippingStatus(int $orderId, string $statusTo, int $shipperId, array $eventData = [], bool $force = false): array
    {
        $this->getConnection()->beginTransaction();

        try {
            $stmt = $this->getConnection()->prepare("SELECT shipping_status FROM {$this->table} WHERE {$this->primaryKey} = ? FOR UPDATE");
            $stmt->execute([$orderId]);
            $currentStatus = $stmt->fetchColumn();

            if ($currentStatus === false) {
                throw new Exception('Order not found', 404);
            }

            $currentStatus = $currentStatus ?: 'new_request';

            if (!$force) {
                $allowedTransitions = self::SHIPPING_TRANSITIONS[$currentStatus] ?? [];
                if (!in_array($statusTo, $allowedTransitions, true)) {
                    throw new Exception("Invalid shipping status transition from {$currentStatus} to {$statusTo}", 422);
                }
            }

            $updateSql = "UPDATE {$this->table} SET shipping_status = ?, shipping_status_updated_at = NOW() WHERE {$this->primaryKey} = ?";
            $updateStmt = $this->getConnection()->prepare($updateSql);
            $updateStmt->execute([$statusTo, $orderId]);

            $eventId = $this->logShippingEvent($orderId, $currentStatus ?: null, $statusTo, $shipperId, $eventData);

            $this->getConnection()->commit();
            return [
                'previous_status' => $currentStatus,
                'event_id' => $eventId
            ];
        } catch (Exception $e) {
            $this->getConnection()->rollBack();
            throw $e;
        }
    }

    public function logShippingEvent(
        int $orderId,
        ?string $statusFrom,
        string $statusTo,
        ?int $shipperId = null,
        array $eventData = []
    ): int {
        $sql = "
            INSERT INTO order_delivery_events (
                order_id,
                shipper_id,
                status_from,
                status_to,
                note,
                photo_url,
                latitude,
                longitude,
                metadata,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ";

        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute([
            $orderId,
            $shipperId,
            $statusFrom,
            $statusTo,
            $eventData['note'] ?? null,
            $eventData['photo_url'] ?? null,
            $eventData['latitude'] ?? null,
            $eventData['longitude'] ?? null,
            isset($eventData['metadata']) ? json_encode($eventData['metadata']) : null,
        ]);

        return (int)$this->getConnection()->lastInsertId();
    }

    public function addDeliveryProof(
        int $orderId,
        int $shipperId,
        string $status,
        string $photoUrl,
        string $proofType = 'delivery_photo',
        ?float $latitude = null,
        ?float $longitude = null,
        array $metadata = []
    ): int {
        $sql = "
            INSERT INTO order_delivery_proofs (
                order_id,
                shipper_id,
                status,
                photo_url,
                proof_type,
                latitude,
                longitude,
                metadata,
                captured_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ";

        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute([
            $orderId,
            $shipperId,
            $status,
            $photoUrl,
            $proofType,
            $latitude,
            $longitude,
            $metadata ? json_encode($metadata) : null
        ]);

        return (int)$this->getConnection()->lastInsertId();
    }

    public function getDeliveryEvents(int $orderId): array
    {
        $sql = "
            SELECT *
            FROM order_delivery_events
            WHERE order_id = ?
            ORDER BY created_at DESC
        ";

        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute([$orderId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getDeliveryProofs(int $orderId): array
    {
        $sql = "
            SELECT *
            FROM order_delivery_proofs
            WHERE order_id = ?
            ORDER BY captured_at DESC
        ";

        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute([$orderId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function forceShippingStatus(int $orderId, string $status, int $shipperId, array $eventData = []): array
    {
        return $this->updateShippingStatus($orderId, $status, $shipperId, $eventData, true);
    }

    /**
     * Lịch sử đơn theo sự kiện shipper (completed / rejected) — không phụ thuộc shipping_tracking.
     */
    public function getShipperOrderHistory(int $shipperId, string $eventStatusTo, int $limit = 20, int $offset = 0): array
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
                e.created_at AS history_event_at,
                e.status_to AS history_status_to,
                (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.order_id) as item_count
            FROM order_delivery_events e
            INNER JOIN {$this->table} o ON o.order_id = e.order_id
            INNER JOIN (
                SELECT order_id, MAX(created_at) AS max_created
                FROM order_delivery_events
                WHERE shipper_id = ? AND status_to = ?
                GROUP BY order_id
            ) latest ON latest.order_id = e.order_id
                AND latest.max_created = e.created_at
            LEFT JOIN customers c ON o.customer_id = c.user_id
            LEFT JOIN users u ON c.user_id = u.user_id
            LEFT JOIN shipping_methods sm ON o.shipping_method_id = sm.shipping_method_id
            LEFT JOIN vouchers v ON o.voucher_id = v.voucher_id
            WHERE e.shipper_id = ? AND e.status_to = ?
            ORDER BY e.created_at DESC
            LIMIT ? OFFSET ?
        ";

        $params = [$shipperId, $eventStatusTo, $shipperId, $eventStatusTo, $limit, $offset];
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute($params);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($orders as &$order) {
            $order['items'] = $this->getOrderItems((int)$order['order_id']);

            if (!empty($order['shipping_address_snapshot'])) {
                $addressData = json_decode($order['shipping_address_snapshot'], true);
                if ($addressData) {
                    $order['shipping_address'] = $addressData;
                }
            }

            $order['delivery_events'] = $this->getDeliveryEvents((int)$order['order_id']);
            $order['delivery_proofs'] = $this->getDeliveryProofs((int)$order['order_id']);
        }

        return $orders;
    }

    public function getShipperOrderHistoryCount(int $shipperId, string $eventStatusTo): int
    {
        $sql = "
            SELECT COUNT(DISTINCT order_id)
            FROM order_delivery_events
            WHERE shipper_id = ? AND status_to = ?
        ";
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute([$shipperId, $eventStatusTo]);
        return (int) $stmt->fetchColumn();
    }
}
