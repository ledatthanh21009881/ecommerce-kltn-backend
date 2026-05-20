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
 * ShipperController - Quản lý Shipper cho Order Tracking System
 * 
 * Chức năng chính:
 * - Lấy danh sách tất cả shipper
 * - Lấy shipper available (có thể nhận đơn)
 * - Thống kê performance của shipper
 * - Cập nhật vị trí GPS từ mobile app
 * - Gán đơn hàng cho shipper
 * 
 * Áp dụng error prevention từ Loi_thuong_gap.md:
 * - Không redeclare inherited properties
 * - Không return void methods
 * - Type hints đầy đủ
 * - Prepared statements cho SQL injection prevention
 */
class ShipperController extends Controller
{
    private PDO $pdo;
    private const VIETNAM_MIN_LAT = 8.0;
    private const VIETNAM_MAX_LAT = 24.0;
    private const VIETNAM_MIN_LNG = 102.0;
    private const VIETNAM_MAX_LNG = 110.0;

    public function __construct($container)
    {
        parent::__construct($container);
        $this->pdo = $container->database()->getConnection();
    }

    /**
     * Lấy danh sách tất cả shipper
     * 
     * @param Request $req - Query params: status, search, page, limit
     * @param Response $res
     * @return void
     */
    public function getAll(Request $req, Response $res): void
    {
        try {
            $params = $req->getQueryParams();
            $status = $params['status'] ?? 'all';
            $search = $params['search'] ?? null;
            $page = (int)($params['page'] ?? 1);
            $limit = (int)($params['limit'] ?? 20);
            $offset = ($page - 1) * $limit;

            // Build WHERE conditions
            $whereConditions = [];
            $params_array = [];

            // Status filter
            if ($status !== 'all') {
                $whereConditions[] = "s.status = :status";
                $params_array[':status'] = $status;
            }

            // Search filter
            if ($search) {
                $whereConditions[] = "(u.first_name LIKE :search OR u.last_name LIKE :search OR u.phone LIKE :search OR s.vehicle_info LIKE :search)";
                $params_array[':search'] = "%{$search}%";
            }

            $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

            // Main query
            $sql = "
                SELECT 
                    s.user_id,
                    CONCAT(u.first_name, ' ', u.last_name) as shipper_name,
                    u.phone,
                    u.email,
                    s.vehicle_info,
                    s.rating,
                    s.on_time_delivery_pct,
                    s.total_delivered,
                    s.last_delivery_at,
                    s.is_available,
                    s.status,
                    s.created_at,
                    s.updated_at,
                    
                    -- Current location
                    sl.lat as current_lat,
                    sl.lng as current_lng,
                    sl.captured_at as location_updated_at,
                    
                    -- Active orders count
                    (SELECT COUNT(*) FROM shipping_tracking st WHERE st.shipper_id = s.user_id AND st.order_id IN (
                        SELECT order_id FROM orders WHERE status IN ('processing', 'shipping')
                    )) as active_orders_count
                    
                FROM shippers s
                JOIN users u ON s.user_id = u.user_id
                LEFT JOIN shipper_locations sl
                    ON sl.shipper_id = s.user_id
                    AND sl.captured_at = (
                        SELECT MAX(sl2.captured_at) FROM shipper_locations sl2 WHERE sl2.shipper_id = s.user_id
                    )
                {$whereClause}
                ORDER BY s.created_at DESC
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
            $shippers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Count total records
            $countSql = "
                SELECT COUNT(*) as total
                FROM shippers s
                JOIN users u ON s.user_id = u.user_id
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
                'shippers' => $shippers,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => (int)$total,
                    'total_pages' => ceil($total / $limit)
                ]
            ];

            $res->json(ResponseHelper::success($response, 'Shippers retrieved successfully'));

        } catch (Exception $e) {
            error_log('[getAllShippers] '.$e->getMessage());
            $response = [
                'shippers' => [],
                'pagination' => [
                    'page' => 1,
                    'limit' => 20,
                    'total' => 0,
                    'total_pages' => 0
                ]
            ];
            $res->json(ResponseHelper::success($response, 'Shippers empty (fallback)'));
        }
    }

    /**
     * Lấy danh sách shipper available (có thể nhận đơn)
     * 
     * @param Request $req - Query params: lat, lng, radius (optional)
     * @param Response $res
     * @return void
     */
    public function getAvailable(Request $req, Response $res): void
    {
        try {
            $params = $req->getQueryParams();
            $lat = $params['lat'] ?? null;
            $lng = $params['lng'] ?? null;
            $radius = (float)($params['radius'] ?? 10); // km

            $sql = "
                SELECT 
                    s.user_id,
                    CONCAT(u.first_name, ' ', u.last_name) as shipper_name,
                    u.phone,
                    s.vehicle_info,
                    s.rating,
                    s.on_time_delivery_pct,
                    s.total_delivered,
                    s.is_available,
                    
                    -- Current location
                    sl.lat as current_lat,
                    sl.lng as current_lng,
                    sl.captured_at as location_updated_at,
                    
                    -- Distance calculation (if lat/lng provided)
                    " . ($lat && $lng ? "
                    (6371 * acos(cos(radians(:lat)) * cos(radians(sl.lat)) * 
                     cos(radians(sl.lng) - radians(:lng)) + sin(radians(:lat)) * 
                     sin(radians(sl.lat)))) as distance_km
                    " : "NULL as distance_km") . "
                    
                FROM shippers s
                JOIN users u ON s.user_id = u.user_id
                LEFT JOIN shipper_locations sl
                    ON sl.shipper_id = s.user_id
                    AND sl.captured_at = (
                        SELECT MAX(sl2.captured_at) FROM shipper_locations sl2 WHERE sl2.shipper_id = s.user_id
                    )
                WHERE s.is_available = 1 
                  AND s.status = 'active'
                  " . ($lat && $lng ? "AND sl.lat IS NOT NULL AND sl.lng IS NOT NULL" : "") . "
                ORDER BY " . ($lat && $lng ? "distance_km ASC" : "s.rating DESC") . "
                LIMIT 50
            ";

            $stmt = $this->pdo->prepare($sql);
            
            if ($lat && $lng) {
                $stmt->bindValue(':lat', $lat);
                $stmt->bindValue(':lng', $lng);
            }
            
            $stmt->execute();
            $shippers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Filter by radius if coordinates provided
            if ($lat && $lng) {
                $shippers = array_filter($shippers, function($shipper) use ($radius) {
                    return $shipper['distance_km'] <= $radius;
                });
            }

            $res->json(ResponseHelper::success($shippers, 'Available shippers retrieved successfully'));

        } catch (Exception $e) {
            error_log('[getAvailableShippers] '.$e->getMessage());
            $res->json(ResponseHelper::success([], 'Available shippers empty (fallback)'));
        }
    }

    /**
     * Lấy thống kê performance của shipper
     * 
     * @param Request $req - Path param: shipper_id, Query params: date_from, date_to
     * @param Response $res
     * @return void
     */
    public function getPerformance(Request $req, Response $res): void
    {
        try {
            $shipperId = $req->getPathParam('id');
            $params = $req->getQueryParams();
            $dateFrom = $params['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
            $dateTo = $params['date_to'] ?? date('Y-m-d');
            
            if (!$shipperId) {
                $res->json(ResponseHelper::badRequest('Shipper ID is required'));
                return;
            }

            // Basic shipper info
            $shipperSql = "
                SELECT 
                    s.user_id,
                    CONCAT(u.first_name, ' ', u.last_name) as shipper_name,
                    u.phone,
                    s.vehicle_info,
                    s.rating,
                    s.on_time_delivery_pct,
                    s.total_delivered,
                    s.last_delivery_at,
                    s.created_at
                FROM shippers s
                JOIN users u ON s.user_id = u.user_id
                WHERE s.user_id = :shipper_id
            ";

            $stmt = $this->pdo->prepare($shipperSql);
            $stmt->bindValue(':shipper_id', $shipperId, PDO::PARAM_INT);
            $stmt->execute();
            $shipper = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$shipper) {
                $res->json(ResponseHelper::notFound('Shipper not found'));
                return;
            }

            // Performance stats for date range
            $statsSql = "
                SELECT 
                    COUNT(*) as total_orders,
                    SUM(CASE WHEN o.status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
                    SUM(CASE WHEN o.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders,
                    AVG(CASE WHEN o.status = 'completed' THEN TIMESTAMPDIFF(MINUTE, o.created_at, o.updated_at) END) as avg_delivery_time,
                    SUM(CASE WHEN o.status = 'completed' THEN o.total_amount ELSE 0 END) as total_revenue
                FROM shipping_tracking st
                JOIN orders o ON st.order_id = o.order_id
                WHERE st.shipper_id = :shipper_id
                  AND DATE(o.created_at) BETWEEN :date_from AND :date_to
            ";

            $statsStmt = $this->pdo->prepare($statsSql);
            $statsStmt->bindValue(':shipper_id', $shipperId, PDO::PARAM_INT);
            $statsStmt->bindValue(':date_from', $dateFrom);
            $statsStmt->bindValue(':date_to', $dateTo);
            $statsStmt->execute();
            $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

            // Recent orders
            $recentOrdersSql = "
                SELECT 
                    o.order_id,
                    o.status,
                    o.total_amount,
                    o.created_at,
                    o.updated_at,
                    CONCAT(c.first_name, ' ', c.last_name) as customer_name
                FROM shipping_tracking st
                JOIN orders o ON st.order_id = o.order_id
                JOIN customers c ON o.customer_id = c.user_id
                WHERE st.shipper_id = :shipper_id
                ORDER BY o.created_at DESC
                LIMIT 10
            ";

            $recentStmt = $this->pdo->prepare($recentOrdersSql);
            $recentStmt->bindValue(':shipper_id', $shipperId, PDO::PARAM_INT);
            $recentStmt->execute();
            $recentOrders = $recentStmt->fetchAll(PDO::FETCH_ASSOC);

            // Format response
            $response = [
                'shipper' => $shipper,
                'performance' => [
                    'total_orders' => (int)$stats['total_orders'],
                    'completed_orders' => (int)$stats['completed_orders'],
                    'cancelled_orders' => (int)$stats['cancelled_orders'],
                    'success_rate' => $stats['total_orders'] > 0 ? round(($stats['completed_orders'] / $stats['total_orders']) * 100, 2) : 0,
                    'avg_delivery_time' => round((float)$stats['avg_delivery_time'], 2),
                    'total_revenue' => (float)$stats['total_revenue']
                ],
                'recent_orders' => $recentOrders,
                'date_range' => [
                    'from' => $dateFrom,
                    'to' => $dateTo
                ]
            ];

            $res->json(ResponseHelper::success($response, 'Shipper performance retrieved successfully'));

        } catch (Exception $e) {
            $res->json(ResponseHelper::serverError('Failed to fetch shipper performance: ' . $e->getMessage()));
        }
    }

    /**
     * Cập nhật vị trí GPS từ mobile app
     * 
     * @param Request $req - Path param: shipper_id, Body: lat, lng, speed, heading, accuracy, battery_level, order_id
     * @param Response $res
     * @return void
     */
    public function updateLocation(Request $req, Response $res): void
    {
        try {
            $shipperId = $req->getPathParam('id');
            $data = $req->getBody();
            
            if (!$shipperId) {
                $res->json(ResponseHelper::badRequest('Shipper ID is required'));
                return;
            }

            $lat = $data['lat'] ?? ($data['latitude'] ?? null);
            $lng = $data['lng'] ?? ($data['longitude'] ?? null);
            $speed = $data['speed'] ?? 0;
            $heading = $data['heading'] ?? 0;
            $accuracy = $data['accuracy'] ?? 0;
            $batteryLevel = $data['battery_level'] ?? 100;
            $orderId = $data['order_id'] ?? null;

            if (!is_numeric($lat) || !is_numeric($lng)) {
                $res->json(ResponseHelper::badRequest('Latitude and longitude are required'));
                return;
            }

            $lat = (float) $lat;
            $lng = (float) $lng;

            // Validate coordinates
            if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
                $res->json(ResponseHelper::badRequest('Invalid coordinates'));
                return;
            }

            $isInVietnam =
                $lat >= self::VIETNAM_MIN_LAT &&
                $lat <= self::VIETNAM_MAX_LAT &&
                $lng >= self::VIETNAM_MIN_LNG &&
                $lng <= self::VIETNAM_MAX_LNG;
            if (!$isInVietnam) {
                error_log(sprintf(
                    '[updateLocation] Reject out-of-vietnam coordinates shipper_id=%s order_id=%s lat=%s lng=%s',
                    (string)$shipperId,
                    (string)($orderId ?? 'null'),
                    (string)$lat,
                    (string)$lng
                ));
                $res->json(ResponseHelper::badRequest('GPS vị trí ngoài phạm vi Việt Nam, vui lòng kiểm tra lại thiết bị'));
                return;
            }

            // Insert location record
            $sql = "
                INSERT INTO shipper_locations (shipper_id, order_id, lat, lng, speed, heading, accuracy, battery_level, captured_at)
                VALUES (:shipper_id, :order_id, :lat, :lng, :speed, :heading, :accuracy, :battery_level, NOW())
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':shipper_id', $shipperId, PDO::PARAM_INT);
            $stmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
            $stmt->bindValue(':lat', $lat);
            $stmt->bindValue(':lng', $lng);
            $stmt->bindValue(':speed', $speed);
            $stmt->bindValue(':heading', $heading, PDO::PARAM_INT);
            $stmt->bindValue(':accuracy', $accuracy);
            $stmt->bindValue(':battery_level', $batteryLevel, PDO::PARAM_INT);
            $stmt->execute();

            // Update shipper's last activity
            $updateSql = "UPDATE shippers SET updated_at = NOW() WHERE user_id = :shipper_id";
            $updateStmt = $this->pdo->prepare($updateSql);
            $updateStmt->bindValue(':shipper_id', $shipperId, PDO::PARAM_INT);
            $updateStmt->execute();

            $res->json(ResponseHelper::success(null, 'Location updated successfully'));

        } catch (Exception $e) {
            $res->json(ResponseHelper::serverError('Failed to update location: ' . $e->getMessage()));
        }
    }

    /**
     * Cập nhật vị trí GPS cho shipper đăng nhập hiện tại (mobile token).
     *
     * @param Request $req
     * @param Response $res
     * @return void
     */
    public function updateMyLocation(Request $req, Response $res): void
    {
        try {
            $user = $req->getAttribute('user');
            $shipperId = (int) ($user['user_id'] ?? 0);
            if ($shipperId <= 0) {
                $res->json(ResponseHelper::unauthorized('Shipper ID not found in token'));
                return;
            }

            // Reuse existing logic by injecting shipper id into route param.
            $req->setAttribute('id', (string) $shipperId);
            $this->updateLocation($req, $res);
        } catch (Exception $e) {
            $res->json(ResponseHelper::serverError('Failed to update my location: ' . $e->getMessage()));
        }
    }

    /**
     * Gán đơn hàng cho shipper
     * 
     * @param Request $req - Body: order_id, shipper_id, note
     * @param Response $res
     * @return void
     */
    public function assignOrder(Request $req, Response $res): void
    {
        try {
            $data = $req->getBody();
            
            $orderId = $data['order_id'] ?? null;
            $shipperId = $data['shipper_id'] ?? null;
            $note = $data['note'] ?? null;

            if (!$orderId || !$shipperId) {
                $res->json(ResponseHelper::badRequest('Order ID and Shipper ID are required'));
                return;
            }

            $this->pdo->beginTransaction();

            try {
                // Check if order exists and is assignable
                $orderSql = "SELECT order_id, status FROM orders WHERE order_id = :order_id";
                $orderStmt = $this->pdo->prepare($orderSql);
                $orderStmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
                $orderStmt->execute();
                $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

                if (!$order) {
                    throw new Exception('Order not found');
                }

                if (!in_array($order['status'], ['pending', 'processing'])) {
                    throw new Exception('Order cannot be assigned in current status');
                }

                // Check if shipper is available
                $shipperSql = "SELECT user_id, is_available, status FROM shippers WHERE user_id = :shipper_id";
                $shipperStmt = $this->pdo->prepare($shipperSql);
                $shipperStmt->bindValue(':shipper_id', $shipperId, PDO::PARAM_INT);
                $shipperStmt->execute();
                $shipper = $shipperStmt->fetch(PDO::FETCH_ASSOC);

                if (!$shipper) {
                    throw new Exception('Shipper not found');
                }

                if (!$shipper['is_available'] || $shipper['status'] !== 'active') {
                    throw new Exception('Shipper is not available');
                }

                // Check if order is already assigned
                $existingSql = "SELECT tracking_id FROM shipping_tracking WHERE order_id = :order_id";
                $existingStmt = $this->pdo->prepare($existingSql);
                $existingStmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
                $existingStmt->execute();
                $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);

                if ($existing) {
                    // Update existing assignment
                    $updateSql = "UPDATE shipping_tracking SET shipper_id = :shipper_id, last_updated = NOW() WHERE order_id = :order_id";
                    $updateStmt = $this->pdo->prepare($updateSql);
                    $updateStmt->bindValue(':shipper_id', $shipperId, PDO::PARAM_INT);
                    $updateStmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
                    $updateStmt->execute();
                } else {
                    // Create new assignment
                    $insertSql = "INSERT INTO shipping_tracking (order_id, shipper_id, last_updated) VALUES (:order_id, :shipper_id, NOW())";
                    $insertStmt = $this->pdo->prepare($insertSql);
                    $insertStmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
                    $insertStmt->bindValue(':shipper_id', $shipperId, PDO::PARAM_INT);
                    $insertStmt->execute();
                }

                // Update order status to shipping once shipper is assigned
                $orderUpdateSql = "UPDATE orders SET status = 'shipping', updated_at = NOW() WHERE order_id = :order_id";
                $orderUpdateStmt = $this->pdo->prepare($orderUpdateSql);
                $orderUpdateStmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
                $orderUpdateStmt->execute();

                // Add tracking event
                $eventSql = "
                    INSERT INTO order_tracking_events (order_id, status, note, created_by, created_at)
                    VALUES (:order_id, 'assigned', :note, 1, NOW())
                ";
                $eventStmt = $this->pdo->prepare($eventSql);
                $eventStmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
                $eventStmt->bindValue(':note', $note ?: "Order assigned to shipper #{$shipperId}");
                $eventStmt->execute();

                $this->pdo->commit();

                $res->json(ResponseHelper::success(null, 'Order assigned successfully'));

            } catch (Exception $e) {
                $this->pdo->rollBack();
                throw $e;
            }

        } catch (Exception $e) {
            $res->json(ResponseHelper::serverError('Failed to assign order: ' . $e->getMessage()));
        }
    }

    /**
     * PATCH/PUT /api/v1/shipper/availability — Shipper bật/tắt trạng thái nhận đơn (mobile app).
     */
    public function updateMyAvailability(Request $req, Response $res): void
    {
        try {
            $user = $req->getAttribute('user');
            $shipperId = (int) ($user['user_id'] ?? 0);

            if ($shipperId <= 0) {
                $res->json(ResponseHelper::unauthorized('Shipper ID not found in token'));
                return;
            }

            $stmt = $this->pdo->prepare('SELECT user_id, status FROM shippers WHERE user_id = ?');
            $stmt->execute([$shipperId]);
            $shipper = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$shipper) {
                $res->json(ResponseHelper::forbidden('User is not a shipper'));
                return;
            }

            if (($shipper['status'] ?? '') !== 'active') {
                $res->json(ResponseHelper::forbidden('Shipper account is not active'));
                return;
            }

            $data = $req->json();
            if (!array_key_exists('is_available', $data)) {
                $res->json(ResponseHelper::validationError(['is_available' => 'is_available is required']));
                return;
            }

            $isAvailable = filter_var($data['is_available'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($isAvailable === null) {
                $res->json(ResponseHelper::validationError(['is_available' => 'is_available must be a boolean']));
                return;
            }

            $updateStmt = $this->pdo->prepare('UPDATE shippers SET is_available = ? WHERE user_id = ?');
            $updateStmt->execute([$isAvailable ? 1 : 0, $shipperId]);

            $res->json(ResponseHelper::success(
                ['is_available' => $isAvailable],
                $isAvailable ? 'You are now available for new orders' : 'You are now offline'
            ));
        } catch (Exception $e) {
            error_log('[ShipperController] updateMyAvailability: ' . $e->getMessage());
            $res->json(ResponseHelper::serverError('Failed to update availability: ' . $e->getMessage()));
        }
    }

    /**
     * POST /api/v1/shipper/fcm-token - Register FCM token for push notifications
     * 
     * @param Request $req
     * @param Response $res
     * @return void
     */
    public function registerFCMToken(Request $req, Response $res): void
    {
        try {
            $user = $req->getAttribute('user');
            $shipperId = (int) ($user['user_id'] ?? 0);

            if (!$shipperId) {
                $res->json(ResponseHelper::unauthorized('Shipper ID not found in token'));
                return;
            }

            // Verify user is a shipper
            $stmt = $this->pdo->prepare("SELECT user_id FROM shippers WHERE user_id = ?");
            $stmt->execute([$shipperId]);
            $shipper = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$shipper) {
                $res->json(ResponseHelper::forbidden('User is not a shipper'));
                return;
            }

            $data = $req->json();
            $fcmToken = $data['fcm_token'] ?? null;

            if (empty($fcmToken)) {
                $res->json(ResponseHelper::validationError(['fcm_token' => 'FCM token is required']));
                return;
            }

            // Update FCM token in shippers table
            $updateSql = "UPDATE shippers SET fcm_token = ?, fcm_token_updated_at = NOW() WHERE user_id = ?";
            $updateStmt = $this->pdo->prepare($updateSql);
            $result = $updateStmt->execute([$fcmToken, $shipperId]);

            if ($result) {
                error_log("[ShipperController] FCM token registered for shipper: {$shipperId}");
                $res->json(ResponseHelper::success(null, 'FCM token registered successfully'));
            } else {
                $res->json(ResponseHelper::serverError('Failed to register FCM token'));
            }

        } catch (Exception $e) {
            error_log('[ShipperController] Error registering FCM token: ' . $e->getMessage());
            $res->json(ResponseHelper::serverError('Failed to register FCM token: ' . $e->getMessage()));
        }
    }

    /**
     * GET /api/v1/shipper/notifications - Get notifications list
     * 
     * @param Request $req
     * @param Response $res
     * @return void
     */
    public function getNotifications(Request $req, Response $res): void
    {
        try {
            $user = $req->getAttribute('user');
            $shipperId = (int) ($user['user_id'] ?? 0);

            if (!$shipperId) {
                $res->json(ResponseHelper::unauthorized('Shipper ID not found in token'));
                return;
            }

            $page = (int)($req->query('page') ?? 1);
            $limit = (int)($req->query('limit') ?? 50);
            $offset = ($page - 1) * $limit;
            $isRead = $req->query('is_read');

            $sql = "SELECT * FROM notifications WHERE user_id = ?";
            $params = [$shipperId];

            if ($isRead !== null) {
                $sql .= " AND is_read = ?";
                $params[] = $isRead === '1' || $isRead === 'true' ? 1 : 0;
            }

            $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Parse payload JSON
            foreach ($notifications as &$notification) {
                if (!empty($notification['payload'])) {
                    $notification['payload'] = json_decode($notification['payload'], true);
                }
            }

            $res->json(ResponseHelper::success($notifications));

        } catch (Exception $e) {
            error_log('[ShipperController] Error getting notifications: ' . $e->getMessage());
            $res->json(ResponseHelper::serverError('Failed to get notifications: ' . $e->getMessage()));
        }
    }

    /**
     * GET /api/v1/shipper/notifications/unread-count - Get unread notification count
     * 
     * @param Request $req
     * @param Response $res
     * @return void
     */
    public function getUnreadCount(Request $req, Response $res): void
    {
        try {
            $user = $req->getAttribute('user');
            $shipperId = (int) ($user['user_id'] ?? 0);

            if (!$shipperId) {
                $res->json(ResponseHelper::unauthorized('Shipper ID not found in token'));
                return;
            }

            $sql = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$shipperId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            $count = (int) ($result['count'] ?? 0);

            $res->json(ResponseHelper::success(['unread_count' => $count]));

        } catch (Exception $e) {
            error_log('[ShipperController] Error getting unread count: ' . $e->getMessage());
            $res->json(ResponseHelper::serverError('Failed to get unread count: ' . $e->getMessage()));
        }
    }

    /**
     * POST /api/v1/shipper/notifications/{id}/read - Mark notification as read
     * 
     * @param Request $req
     * @param Response $res
     * @return void
     */
    public function markNotificationAsRead(Request $req, Response $res): void
    {
        try {
            $user = $req->getAttribute('user');
            $shipperId = (int) ($user['user_id'] ?? 0);
            $notificationId = (int) $req->getAttribute('id');

            if (!$shipperId) {
                $res->json(ResponseHelper::unauthorized('Shipper ID not found in token'));
                return;
            }

            // Verify notification belongs to shipper
            $checkSql = "SELECT notification_id FROM notifications WHERE notification_id = ? AND user_id = ?";
            $checkStmt = $this->pdo->prepare($checkSql);
            $checkStmt->execute([$notificationId, $shipperId]);
            $notification = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if (!$notification) {
                $res->json(ResponseHelper::notFound('Notification not found'));
                return;
            }

            // Mark as read
            $updateSql = "UPDATE notifications SET is_read = 1, read_at = NOW() WHERE notification_id = ? AND user_id = ?";
            $updateStmt = $this->pdo->prepare($updateSql);
            $result = $updateStmt->execute([$notificationId, $shipperId]);

            if ($result) {
                $res->json(ResponseHelper::success(null, 'Notification marked as read'));
            } else {
                $res->json(ResponseHelper::serverError('Failed to mark notification as read'));
            }

        } catch (Exception $e) {
            error_log('[ShipperController] Error marking notification as read: ' . $e->getMessage());
            $res->json(ResponseHelper::serverError('Failed to mark notification as read: ' . $e->getMessage()));
        }
    }

    /**
     * POST /api/v1/shipper/notifications/mark-all-read - Mark all notifications as read
     * 
     * @param Request $req
     * @param Response $res
     * @return void
     */
    public function markAllNotificationsAsRead(Request $req, Response $res): void
    {
        try {
            $user = $req->getAttribute('user');
            $shipperId = (int) ($user['user_id'] ?? 0);

            if (!$shipperId) {
                $res->json(ResponseHelper::unauthorized('Shipper ID not found in token'));
                return;
            }

            // Mark all as read
            $updateSql = "UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0";
            $updateStmt = $this->pdo->prepare($updateSql);
            $result = $updateStmt->execute([$shipperId]);

            if ($result) {
                $res->json(ResponseHelper::success(null, 'All notifications marked as read'));
            } else {
                $res->json(ResponseHelper::serverError('Failed to mark all notifications as read'));
            }

        } catch (Exception $e) {
            error_log('[ShipperController] Error marking all notifications as read: ' . $e->getMessage());
            $res->json(ResponseHelper::serverError('Failed to mark all notifications as read: ' . $e->getMessage()));
        }
    }
}