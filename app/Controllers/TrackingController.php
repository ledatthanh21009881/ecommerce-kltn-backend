<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Support\ResponseHelper;
use PDO;
use Exception;

/**
 * TrackingController - Quản lý Order Tracking System
 * 
 * Chức năng chính:
 * - Lấy danh sách đơn hàng đang giao (real-time)
 * - Chi tiết đơn hàng với thông tin shipper, customer, tracking
 * - Cập nhật trạng thái đơn hàng
 * - Lấy vị trí shipper hiện tại
 * - Lịch sử tracking events
 * - Thống kê dashboard
 * 
 * Áp dụng error prevention từ Loi_thuong_gap.md:
 * - Không redeclare inherited properties
 * - Không return void methods
 * - Type hints đầy đủ
 * - Prepared statements cho SQL injection prevention
 */
class TrackingController extends Controller
{
    private PDO $pdo;

    public function __construct($container)
    {
        parent::__construct($container);
        $this->pdo = $container->database()->getConnection();
    }

    /**
     * Lấy danh sách đơn hàng đang giao (active orders)
     * 
     * @param Request $req - Query params: status, shipper_id, date_from, date_to, search
     * @param Response $res
     * @return void
     */
    public function getActiveOrders(Request $req, Response $res): void
    {
        try {
            $params = $req->getQueryParams();
            $status = $params['status'] ?? 'all';
            $shipperId = $params['shipper_id'] ?? null;
            $dateFrom = $params['date_from'] ?? null;
            $dateTo = $params['date_to'] ?? null;
            $search = $params['search'] ?? null;
            $page = (int)($params['page'] ?? 1);
            $limit = (int)($params['limit'] ?? 20);
            $offset = ($page - 1) * $limit;

            // Build WHERE conditions
            $whereConditions = [];
            $params_array = [];

            // Status filter
            if ($status !== 'all') {
                $whereConditions[] = "o.status = :status";
                $params_array[':status'] = $status;
            }

            // Shipper filter
            if ($shipperId) {
                $whereConditions[] = "st.shipper_id = :shipper_id";
                $params_array[':shipper_id'] = $shipperId;
            }

            // Date range filter
            if ($dateFrom) {
                $whereConditions[] = "o.created_at >= :date_from";
                $params_array[':date_from'] = $dateFrom;
            }
            if ($dateTo) {
                $whereConditions[] = "o.created_at <= :date_to";
                $params_array[':date_to'] = $dateTo . ' 23:59:59';
            }

            // Search filter
            if ($search) {
                $whereConditions[] = "(o.order_id LIKE :search OR c.first_name LIKE :search OR c.last_name LIKE :search OR u.first_name LIKE :search OR u.last_name LIKE :search)";
                $params_array[':search'] = "%{$search}%";
            }

            $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

            // Main query - lấy orders với thông tin shipper và customer
            $sql = "
                SELECT 
                    o.order_id,
                    o.status,
                    o.total_amount,
                    o.created_at,
                    o.estimated_delivery_at,
                    
                    -- Customer info
                    CONCAT(c.first_name, ' ', c.last_name) as customer_name,
                    c.phone as customer_phone,
                    JSON_UNQUOTE(JSON_EXTRACT(o.shipping_address_snapshot, '$.address_line')) as customer_address,
                    
                    -- Shipper info
                    st.shipper_id,
                    CONCAT(u.first_name, ' ', u.last_name) as shipper_name,
                    u.phone as shipper_phone,
                    s.vehicle_info,
                    s.rating,
                    
                    -- Current location
                    sl.lat as current_lat,
                    sl.lng as current_lng,
                    sl.captured_at as location_updated_at,
                    
                    -- Destination coordinates
                    a.lat as destination_lat,
                    a.lng as destination_lng,
                    
                    -- Tracking info
                    (SELECT COUNT(*) FROM order_tracking_events ote WHERE ote.order_id = o.order_id) as event_count,
                    (SELECT ote.status FROM order_tracking_events ote WHERE ote.order_id = o.order_id ORDER BY ote.created_at DESC LIMIT 1) as last_status,
                    (SELECT ote.created_at FROM order_tracking_events ote WHERE ote.order_id = o.order_id ORDER BY ote.created_at DESC LIMIT 1) as last_event_at
                    
                FROM orders o
                LEFT JOIN shipping_tracking st ON o.order_id = st.order_id
                LEFT JOIN shippers s ON st.shipper_id = s.user_id
                LEFT JOIN users u ON s.user_id = u.user_id
                LEFT JOIN customers c ON o.customer_id = c.user_id
                LEFT JOIN addresses a ON o.address_id = a.address_id
                LEFT JOIN shipper_locations sl
                    ON sl.shipper_id = st.shipper_id
                    AND sl.order_id = o.order_id
                    AND sl.captured_at = (
                        SELECT MAX(sl2.captured_at)
                        FROM shipper_locations sl2
                        WHERE sl2.shipper_id = st.shipper_id AND sl2.order_id = o.order_id
                    )
                {$whereClause}
                ORDER BY o.created_at DESC
                LIMIT :limit OFFSET :offset
            ";

            $stmt = $this->pdo->prepare($sql);
            
            // Bind parameters
            foreach ($params_array as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            
            $stmt->execute();
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Count total records
            $countSql = "
                SELECT COUNT(*) as total
                FROM orders o
                LEFT JOIN shipping_tracking st ON o.order_id = st.order_id
                LEFT JOIN shippers s ON st.shipper_id = s.user_id
                LEFT JOIN users u ON s.user_id = u.user_id
                LEFT JOIN customers c ON o.customer_id = c.user_id
                {$whereClause}
            ";

            $countStmt = $this->pdo->prepare($countSql);
            foreach ($params_array as $key => $value) {
                $countStmt->bindValue($key, $value);
            }
            $countStmt->execute();
            $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

            // Format response
            $response = [
                'orders' => $orders,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => (int)$total,
                    'total_pages' => ceil($total / $limit)
                ]
            ];

            $res->json(ResponseHelper::success($response, 'Active orders retrieved successfully'));

        } catch (Exception $e) {
            error_log('[getActiveOrders] '.$e->getMessage());
            // Trả về dữ liệu rỗng an toàn thay vì 500 để FE không bị gián đoạn
            $response = [
                'orders' => [],
                'pagination' => [
                    'page' => 1,
                    'limit' => 20,
                    'total' => 0,
                    'total_pages' => 0
                ]
            ];
            $res->json(ResponseHelper::success($response, 'Active orders empty (fallback)'));
        }
    }

    /**
     * Lấy chi tiết đơn hàng với thông tin đầy đủ
     * 
     * @param Request $req - Path param: order_id
     * @param Response $res
     * @return void
     */
    public function getOrderDetail(Request $req, Response $res): void
    {
        try {
            $orderId = $req->getPathParam('id');
            
            if (!$orderId) {
                $res->json(ResponseHelper::badRequest('Order ID is required'));
                return;
            }

            // Get order details with nested structure
            $sql = "
                SELECT 
                    o.order_id,
                    o.status,
                    o.total_amount,
                    o.shipping_fee,
                    o.created_at,
                    o.estimated_delivery_at,
                    o.note as order_note,
                    
                    -- Customer info
                    CONCAT(c.first_name, ' ', c.last_name) as customer_name,
                    c.phone as customer_phone,
                    c.email as customer_email,
                    JSON_UNQUOTE(JSON_EXTRACT(o.shipping_address_snapshot, '$.address_line')) as customer_address,
                    
                    -- Shipper info
                    st.shipper_id,
                    CONCAT(u.first_name, ' ', u.last_name) as shipper_name,
                    u.phone as shipper_phone,
                    s.vehicle_info,
                    s.rating,
                    s.on_time_delivery_pct,
                    
                    -- Current location
                    sl.lat as current_lat,
                    sl.lng as current_lng,
                    sl.speed,
                    sl.heading,
                    sl.captured_at as location_updated_at,
                    
                    -- Destination coordinates
                    a.lat as destination_lat,
                    a.lng as destination_lng
                    
                FROM orders o
                LEFT JOIN shipping_tracking st ON o.order_id = st.order_id
                LEFT JOIN shippers s ON st.shipper_id = s.user_id
                LEFT JOIN users u ON s.user_id = u.user_id
                LEFT JOIN customers c ON o.customer_id = c.user_id
                LEFT JOIN addresses a ON o.address_id = a.address_id
                LEFT JOIN shipper_locations sl
                    ON sl.shipper_id = st.shipper_id
                    AND sl.order_id = o.order_id
                    AND sl.captured_at = (
                        SELECT MAX(sl2.captured_at)
                        FROM shipper_locations sl2
                        WHERE sl2.shipper_id = st.shipper_id AND sl2.order_id = o.order_id
                    )
                WHERE o.order_id = :order_id
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
            $stmt->execute();
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                $res->json(ResponseHelper::notFound('Order not found'));
                return;
            }

            // Get order items
            $itemsSql = "
                SELECT 
                    oi.order_item_id,
                    oi.quantity,
                    oi.price,
                    oi.subtotal,
                    p.product_name,
                    s.size_name,
                    pv.sku
                FROM order_items oi
                JOIN product_variants pv ON oi.variant_id = pv.variant_id
                JOIN products p ON pv.product_id = p.product_id
                JOIN sizes s ON pv.size_id = s.size_id
                WHERE oi.order_id = :order_id
            ";

            $itemsStmt = $this->pdo->prepare($itemsSql);
            $itemsStmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
            $itemsStmt->execute();
            $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

            // Get tracking events
            $eventsSql = "
                SELECT 
                    ote.event_id,
                    ote.status,
                    ote.note,
                    ote.lat,
                    ote.lng,
                    ote.created_at,
                    CONCAT(u.first_name, ' ', u.last_name) as created_by_name
                FROM order_tracking_events ote
                LEFT JOIN users u ON ote.created_by = u.user_id
                WHERE ote.order_id = :order_id
                ORDER BY ote.created_at ASC
            ";

            $eventsStmt = $this->pdo->prepare($eventsSql);
            $eventsStmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
            $eventsStmt->execute();
            $events = $eventsStmt->fetchAll(PDO::FETCH_ASSOC);

            // Format response with nested structure
            $response = [
                'order' => [
                    'order_id' => (int)$order['order_id'],
                    'status' => $order['status'],
                    'total_amount' => (float)$order['total_amount'],
                    'shipping_fee' => (float)$order['shipping_fee'],
                    'created_at' => $order['created_at'],
                    'estimated_delivery_at' => $order['estimated_delivery_at'],
                    'note' => $order['order_note']
                ],
                'customer' => [
                    'name' => $order['customer_name'],
                    'phone' => $order['customer_phone'],
                    'email' => $order['customer_email'],
                    'address' => $order['customer_address'],
                    'destination_lat' => $order['destination_lat'] ? (float)$order['destination_lat'] : null,
                    'destination_lng' => $order['destination_lng'] ? (float)$order['destination_lng'] : null
                ],
                'shipper' => [
                    'user_id' => $order['shipper_id'] ? (int)$order['shipper_id'] : null,
                    'name' => $order['shipper_name'],
                    'phone' => $order['shipper_phone'],
                    'vehicle_info' => $order['vehicle_info'],
                    'rating' => $order['rating'] ? (float)$order['rating'] : null,
                    'on_time_delivery_pct' => $order['on_time_delivery_pct'] ? (float)$order['on_time_delivery_pct'] : null,
                    'current_location' => [
                        'lat' => $order['current_lat'] ? (float)$order['current_lat'] : null,
                        'lng' => $order['current_lng'] ? (float)$order['current_lng'] : null,
                        'speed' => $order['speed'] ? (float)$order['speed'] : null,
                        'heading' => $order['heading'] ? (int)$order['heading'] : null,
                        'updated_at' => $order['location_updated_at']
                    ]
                ],
                'items' => $items,
                'tracking' => [
                    'events' => $events,
                    'event_count' => count($events)
                ]
            ];

            $res->json(ResponseHelper::success($response, 'Order details retrieved successfully'));

        } catch (Exception $e) {
            $res->json(ResponseHelper::serverError('Failed to fetch order details: ' . $e->getMessage()));
        }
    }

    /**
     * Cập nhật trạng thái đơn hàng
     * 
     * @param Request $req - Path param: order_id, Body: status, note, lat, lng
     * @param Response $res
     * @return void
     */
    public function updateOrderStatus(Request $req, Response $res): void
    {
        try {
            $orderId = $req->getPathParam('id');
            $data = $req->getBody();
            
            if (!$orderId) {
                $res->json(ResponseHelper::badRequest('Order ID is required'));
                return;
            }

            $status = $data['status'] ?? null;
            $note = $data['note'] ?? null;
            $lat = $data['lat'] ?? null;
            $lng = $data['lng'] ?? null;
            $createdBy = $data['created_by'] ?? 1; // Default to admin

            if (!$status) {
                $res->json(ResponseHelper::badRequest('Status is required'));
                return;
            }

            // Validate status transition (basic validation)
            $validStatuses = ['pending', 'confirmed', 'assigned', 'picking_up', 'picked_up', 'in_transit', 'arriving', 'delivered', 'failed', 'cancelled'];
            if (!in_array($status, $validStatuses)) {
                $res->json(ResponseHelper::badRequest('Invalid status'));
                return;
            }

            $this->pdo->beginTransaction();

            try {
                // Insert tracking event
                $eventSql = "
                    INSERT INTO order_tracking_events (order_id, status, note, lat, lng, created_by, created_at)
                    VALUES (:order_id, :status, :note, :lat, :lng, :created_by, NOW())
                ";

                $eventStmt = $this->pdo->prepare($eventSql);
                $eventStmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
                $eventStmt->bindValue(':status', $status);
                $eventStmt->bindValue(':note', $note);
                $eventStmt->bindValue(':lat', $lat);
                $eventStmt->bindValue(':lng', $lng);
                $eventStmt->bindValue(':created_by', $createdBy, PDO::PARAM_INT);
                $eventStmt->execute();

                // Update order status if needed
                if (in_array($status, ['delivered', 'failed', 'cancelled'])) {
                    $orderStatus = $status === 'delivered' ? 'completed' : 
                                 ($status === 'failed' ? 'cancelled' : 'cancelled');
                    
                    $updateSql = "UPDATE orders SET status = :status, updated_at = NOW() WHERE order_id = :order_id";
                    $updateStmt = $this->pdo->prepare($updateSql);
                    $updateStmt->bindValue(':status', $orderStatus);
                    $updateStmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
                    $updateStmt->execute();
                }

                $this->pdo->commit();

                $res->json(ResponseHelper::success(null, 'Order status updated successfully'));

            } catch (Exception $e) {
                $this->pdo->rollBack();
                throw $e;
            }

        } catch (Exception $e) {
            $res->json(ResponseHelper::serverError('Failed to update order status: ' . $e->getMessage()));
        }
    }

    /**
     * Lấy vị trí shipper hiện tại
     * 
     * @param Request $req - Path param: shipper_id
     * @param Response $res
     * @return void
     */
    public function getShipperLocation(Request $req, Response $res): void
    {
        try {
            $shipperId = $req->getPathParam('id');
            
            if (!$shipperId) {
                $res->json(ResponseHelper::badRequest('Shipper ID is required'));
                return;
            }

            $sql = "
                SELECT 
                    sl.location_id,
                    sl.shipper_id,
                    sl.order_id,
                    sl.lat,
                    sl.lng,
                    sl.speed,
                    sl.heading,
                    sl.accuracy,
                    sl.battery_level,
                    sl.captured_at,
                    CONCAT(u.first_name, ' ', u.last_name) as shipper_name,
                    s.vehicle_info
                FROM shipper_locations sl
                JOIN shippers s ON sl.shipper_id = s.user_id
                JOIN users u ON s.user_id = u.user_id
                WHERE sl.shipper_id = :shipper_id
                ORDER BY sl.captured_at DESC
                LIMIT 1
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':shipper_id', $shipperId, PDO::PARAM_INT);
            $stmt->execute();
            $location = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$location) {
                $res->json(ResponseHelper::notFound('Shipper location not found'));
                return;
            }

            $res->json(ResponseHelper::success($location, 'Shipper location retrieved successfully'));

        } catch (Exception $e) {
            $res->json(ResponseHelper::serverError('Failed to fetch shipper location: ' . $e->getMessage()));
        }
    }

    /**
     * Lấy lịch sử tracking events của đơn hàng
     * 
     * @param Request $req - Path param: order_id
     * @param Response $res
     * @return void
     */
    public function getOrderHistory(Request $req, Response $res): void
    {
        try {
            $orderId = $req->getPathParam('id');
            
            if (!$orderId) {
                $res->json(ResponseHelper::badRequest('Order ID is required'));
                return;
            }

            $sql = "
                SELECT 
                    ote.event_id,
                    ote.status,
                    ote.note,
                    ote.lat,
                    ote.lng,
                    ote.created_at,
                    CONCAT(u.first_name, ' ', u.last_name) as created_by_name,
                    u.phone as created_by_phone
                FROM order_tracking_events ote
                LEFT JOIN users u ON ote.created_by = u.user_id
                WHERE ote.order_id = :order_id
                ORDER BY ote.created_at ASC
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
            $stmt->execute();
            $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $res->json(ResponseHelper::success($events, 'Order tracking history retrieved successfully'));

        } catch (Exception $e) {
            $res->json(ResponseHelper::serverError('Failed to fetch order history: ' . $e->getMessage()));
        }
    }

    /**
     * Lấy thống kê dashboard
     * 
     * @param Request $req - Query params: date_from, date_to
     * @param Response $res
     * @return void
     */
    public function getDashboardStats(Request $req, Response $res): void
    {
        try {
            $params = $req->getQueryParams();
            $dateFrom = $params['date_from'] ?? date('Y-m-d');
            $dateTo = $params['date_to'] ?? date('Y-m-d');

            // Total Orders Today
            $totalOrdersSql = "
                SELECT COUNT(*) as total_orders_today
                FROM orders 
                WHERE DATE(created_at) = :date_from
            ";

            // Active Deliveries
            $activeDeliveriesSql = "
                SELECT COUNT(*) as active_deliveries
                FROM orders 
                WHERE status IN ('processing', 'shipping')
            ";

            // Completed Today
            $completedTodaySql = "
                SELECT COUNT(*) as completed_today
                FROM orders 
                WHERE status = 'completed' AND DATE(updated_at) = :date_from
            ";

            // Pending Pickup
            $pendingPickupSql = "
                SELECT COUNT(*) as pending_pickup
                FROM orders 
                WHERE status = 'processing'
            ";

            // Failed Deliveries
            $failedDeliveriesSql = "
                SELECT COUNT(*) as failed_deliveries
                FROM orders 
                WHERE status = 'cancelled' AND DATE(updated_at) = :date_from
            ";

            // Average Delivery Time (in minutes)
            $avgDeliveryTimeSql = "
                SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, updated_at)) as avg_delivery_time
                FROM orders 
                WHERE status = 'completed' AND DATE(updated_at) = :date_from
            ";

            // Total Revenue Today
            $totalRevenueSql = "
                SELECT COALESCE(SUM(total_amount), 0) as total_revenue_today
                FROM orders 
                WHERE status = 'completed' AND DATE(updated_at) = :date_from
            ";

            // Active Shippers
            $activeShippersSql = "
                SELECT COUNT(DISTINCT st.shipper_id) as active_shippers
                FROM shipping_tracking st
                JOIN shippers s ON st.shipper_id = s.user_id
                WHERE s.is_available = 1
            ";

            $stats = [];

            // Execute all queries
            $queries = [
                'total_orders_today' => $totalOrdersSql,
                'active_deliveries' => $activeDeliveriesSql,
                'completed_today' => $completedTodaySql,
                'pending_pickup' => $pendingPickupSql,
                'failed_deliveries' => $failedDeliveriesSql,
                'avg_delivery_time' => $avgDeliveryTimeSql,
                'total_revenue_today' => $totalRevenueSql,
                'active_shippers' => $activeShippersSql
            ];

            foreach ($queries as $key => $sql) {
                $stmt = $this->pdo->prepare($sql);
                if (in_array($key, ['total_orders_today', 'completed_today', 'failed_deliveries', 'avg_delivery_time', 'total_revenue_today'])) {
                    $stmt->bindValue(':date_from', $dateFrom);
                }
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $stats[$key] = $result[array_key_first($result)] ?? 0;
            }

            // Format response
            $response = [
                'total_orders_today' => (int)$stats['total_orders_today'],
                'active_deliveries' => (int)$stats['active_deliveries'],
                'completed_today' => (int)$stats['completed_today'],
                'pending_pickup' => (int)$stats['pending_pickup'],
                'failed_deliveries' => (int)$stats['failed_deliveries'],
                'avg_delivery_time' => round((float)$stats['avg_delivery_time'], 2),
                'total_revenue_today' => (float)$stats['total_revenue_today'],
                'active_shippers' => (int)$stats['active_shippers'],
                'date_range' => [
                    'from' => $dateFrom,
                    'to' => $dateTo
                ]
            ];

            $res->json(ResponseHelper::success($response, 'Dashboard stats retrieved successfully'));

        } catch (Exception $e) {
            error_log('[getDashboardStats] '.$e->getMessage());
            $response = [
                'total_orders_today' => 0,
                'active_deliveries' => 0,
                'completed_today' => 0,
                'pending_pickup' => 0,
                'failed_deliveries' => 0,
                'avg_delivery_time' => 0,
                'total_revenue_today' => 0,
                'active_shippers' => 0,
                'date_range' => [
                    'from' => date('Y-m-d'),
                    'to' => date('Y-m-d')
                ]
            ];
            $res->json(ResponseHelper::success($response, 'Dashboard stats empty (fallback)'));
        }
    }
}
