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
 * NotificationController - Quản lý Admin Notifications cho Order Tracking System
 * 
 * Chức năng chính:
 * - Lấy danh sách tất cả notifications
 * - Đánh dấu notification đã đọc
 * - Đánh dấu tất cả notifications đã đọc
 * - Tạo notification mới
 * - Thống kê notifications
 * 
 * Áp dụng error prevention từ Loi_thuong_gap.md:
 * - Không redeclare inherited properties
 * - Không return void methods
 * - Type hints đầy đủ
 * - Prepared statements cho SQL injection prevention
 */
class NotificationController extends Controller
{
    private PDO $pdo;

    public function __construct($container)
    {
        parent::__construct($container);
        $this->pdo = $container->database()->getConnection();
    }

    /**
     * Lấy danh sách tất cả notifications
     * 
     * @param Request $req - Query params: type, is_read, page, limit, search
     * @param Response $res
     * @return void
     */
    public function getAll(Request $req, Response $res): void
    {
        try {
            $params = $req->getQueryParams();
            $type = $params['type'] ?? 'all';
            $isRead = $params['is_read'] ?? 'all';
            $search = $params['search'] ?? null;
            $page = (int)($params['page'] ?? 1);
            $limit = (int)($params['limit'] ?? 20);
            $offset = ($page - 1) * $limit;

            // Build WHERE conditions
            $whereConditions = [];
            $params_array = [];

            // Type filter
            if ($type !== 'all') {
                $whereConditions[] = "an.type = :type";
                $params_array[':type'] = $type;
            }

            // Read status filter
            if ($isRead !== 'all') {
                $whereConditions[] = "an.is_read = :is_read";
                $params_array[':is_read'] = (int)$isRead;
            }

            // Search filter
            if ($search) {
                $whereConditions[] = "(an.title LIKE :search OR an.message LIKE :search)";
                $params_array[':search'] = "%{$search}%";
            }

            $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

            // Main query
            $sql = "
                SELECT 
                    an.notification_id,
                    an.type,
                    an.title,
                    an.message,
                    an.related_order_id,
                    an.related_shipper_id,
                    an.is_read,
                    an.created_at,
                    
                    -- Order info
                    o.order_id,
                    o.status as order_status,
                    o.total_amount,
                    
                    -- Shipper info
                    CONCAT(u.first_name, ' ', u.last_name) as shipper_name,
                    u.phone as shipper_phone,
                    
                    -- Customer (name/phone live on `users`, not `customers` row)
                    CONCAT(cu.first_name, ' ', cu.last_name) as customer_name,
                    cu.phone as customer_phone
                    
                FROM admin_notifications an
                LEFT JOIN orders o ON an.related_order_id = o.order_id
                LEFT JOIN shippers s ON an.related_shipper_id = s.user_id
                LEFT JOIN users u ON s.user_id = u.user_id
                LEFT JOIN users cu ON o.customer_id = cu.user_id
                {$whereClause}
                ORDER BY an.created_at DESC
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
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Count total records
            $countSql = "
                SELECT COUNT(*) as total
                FROM admin_notifications an
                {$whereClause}
            ";

            $countStmt = $this->pdo->prepare($countSql);
            foreach ($params_array as $key => $value) {
                $countStmt->bindValue($key, $value);
            }
            $countStmt->execute();
            $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

            // Count unread notifications
            $unreadCountSql = "SELECT COUNT(*) as unread_count FROM admin_notifications WHERE is_read = 0";
            $unreadStmt = $this->pdo->prepare($unreadCountSql);
            $unreadStmt->execute();
            $unreadCount = $unreadStmt->fetch(PDO::FETCH_ASSOC)['unread_count'];

            // Format response
            $response = [
                'notifications' => $notifications,
                'unread_count' => (int)$unreadCount,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => (int)$total,
                    'total_pages' => ceil($total / $limit)
                ]
            ];

            $res->json(ResponseHelper::success($response, 'Notifications retrieved successfully'));

        } catch (Exception $e) {
            $res->json(ResponseHelper::serverError('Failed to fetch notifications: ' . $e->getMessage()));
        }
    }

    /**
     * Đánh dấu notification đã đọc
     * 
     * @param Request $req - Path param: notification_id
     * @param Response $res
     * @return void
     */
    public function markAsRead(Request $req, Response $res): void
    {
        try {
            $notificationId = $req->getPathParam('id');
            
            if (!$notificationId) {
                $res->json(ResponseHelper::badRequest('Notification ID is required'));
                return;
            }

            $sql = "UPDATE admin_notifications SET is_read = 1 WHERE notification_id = :notification_id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':notification_id', $notificationId, PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->rowCount() === 0) {
                $res->json(ResponseHelper::notFound('Notification not found'));
                return;
            }

            $res->json(ResponseHelper::success(null, 'Notification marked as read'));

        } catch (Exception $e) {
            $res->json(ResponseHelper::serverError('Failed to mark notification as read: ' . $e->getMessage()));
        }
    }

    /**
     * Đánh dấu tất cả notifications đã đọc
     * 
     * @param Request $req
     * @param Response $res
     * @return void
     */
    public function markAllAsRead(Request $req, Response $res): void
    {
        try {
            $sql = "UPDATE admin_notifications SET is_read = 1 WHERE is_read = 0";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();

            $updatedCount = $stmt->rowCount();

            $res->json(ResponseHelper::success([
                'updated_count' => $updatedCount
            ], "Marked {$updatedCount} notifications as read"));

        } catch (Exception $e) {
            $res->json(ResponseHelper::serverError('Failed to mark all notifications as read: ' . $e->getMessage()));
        }
    }

    /**
     * Tạo notification mới
     * 
     * @param Request $req - Body: type, title, message, related_order_id, related_shipper_id
     * @param Response $res
     * @return void
     */
    public function create(Request $req, Response $res): void
    {
        try {
            $data = $req->getBody();
            
            $type = $data['type'] ?? null;
            $title = $data['title'] ?? null;
            $message = $data['message'] ?? null;
            $relatedOrderId = $data['related_order_id'] ?? null;
            $relatedShipperId = $data['related_shipper_id'] ?? null;

            if (!$type || !$title || !$message) {
                $res->json(ResponseHelper::badRequest('Type, title, and message are required'));
                return;
            }

            // Validate type
            $validTypes = [
                'order_assigned', 'order_picked_up', 'order_delivered', 'order_cancelled',
                'shipper_issue', 'order_delayed', 'shipper_offline', 'system_alert',
                'new_order', 'payment_update',
            ];
            if (!in_array($type, $validTypes)) {
                $res->json(ResponseHelper::badRequest('Invalid notification type'));
                return;
            }

            $sql = "
                INSERT INTO admin_notifications (type, title, message, related_order_id, related_shipper_id, is_read, created_at)
                VALUES (:type, :title, :message, :related_order_id, :related_shipper_id, 0, NOW())
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':type', $type);
            $stmt->bindValue(':title', $title);
            $stmt->bindValue(':message', $message);
            $stmt->bindValue(':related_order_id', $relatedOrderId, PDO::PARAM_INT);
            $stmt->bindValue(':related_shipper_id', $relatedShipperId, PDO::PARAM_INT);
            $stmt->execute();

            $notificationId = $this->pdo->lastInsertId();

            $res->json(ResponseHelper::success([
                'notification_id' => (int)$notificationId
            ], 'Notification created successfully'));

        } catch (Exception $e) {
            $res->json(ResponseHelper::serverError('Failed to create notification: ' . $e->getMessage()));
        }
    }

    /**
     * Lấy thống kê notifications
     * 
     * @param Request $req - Query params: date_from, date_to
     * @param Response $res
     * @return void
     */
    public function getStats(Request $req, Response $res): void
    {
        try {
            $params = $req->getQueryParams();
            $dateFrom = $params['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
            $dateTo = $params['date_to'] ?? date('Y-m-d');

            // Total notifications
            $totalSql = "
                SELECT COUNT(*) as total_notifications
                FROM admin_notifications 
                WHERE DATE(created_at) BETWEEN :date_from AND :date_to
            ";

            // Unread notifications
            $unreadSql = "
                SELECT COUNT(*) as unread_notifications
                FROM admin_notifications 
                WHERE is_read = 0 AND DATE(created_at) BETWEEN :date_from AND :date_to
            ";

            // Notifications by type
            $byTypeSql = "
                SELECT 
                    type,
                    COUNT(*) as count
                FROM admin_notifications 
                WHERE DATE(created_at) BETWEEN :date_from AND :date_to
                GROUP BY type
                ORDER BY count DESC
            ";

            // Recent notifications (last 10)
            $recentSql = "
                SELECT 
                    notification_id,
                    type,
                    title,
                    created_at,
                    is_read
                FROM admin_notifications 
                WHERE DATE(created_at) BETWEEN :date_from AND :date_to
                ORDER BY created_at DESC
                LIMIT 10
            ";

            $stats = [];

            // Execute queries
            $queries = [
                'total_notifications' => $totalSql,
                'unread_notifications' => $unreadSql
            ];

            foreach ($queries as $key => $sql) {
                $stmt = $this->pdo->prepare($sql);
                $stmt->bindValue(':date_from', $dateFrom);
                $stmt->bindValue(':date_to', $dateTo);
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $stats[$key] = (int)$result[array_key_first($result)];
            }

            // By type
            $typeStmt = $this->pdo->prepare($byTypeSql);
            $typeStmt->bindValue(':date_from', $dateFrom);
            $typeStmt->bindValue(':date_to', $dateTo);
            $typeStmt->execute();
            $byType = $typeStmt->fetchAll(PDO::FETCH_ASSOC);

            // Recent
            $recentStmt = $this->pdo->prepare($recentSql);
            $recentStmt->bindValue(':date_from', $dateFrom);
            $recentStmt->bindValue(':date_to', $dateTo);
            $recentStmt->execute();
            $recent = $recentStmt->fetchAll(PDO::FETCH_ASSOC);

            // Format response
            $response = [
                'total_notifications' => $stats['total_notifications'],
                'unread_notifications' => $stats['unread_notifications'],
                'read_rate' => $stats['total_notifications'] > 0 ? 
                    round((($stats['total_notifications'] - $stats['unread_notifications']) / $stats['total_notifications']) * 100, 2) : 0,
                'by_type' => $byType,
                'recent_notifications' => $recent,
                'date_range' => [
                    'from' => $dateFrom,
                    'to' => $dateTo
                ]
            ];

            $res->json(ResponseHelper::success($response, 'Notification stats retrieved successfully'));

        } catch (Exception $e) {
            $res->json(ResponseHelper::serverError('Failed to fetch notification stats: ' . $e->getMessage()));
        }
    }

    /**
     * Xóa notification
     * 
     * @param Request $req - Path param: notification_id
     * @param Response $res
     * @return void
     */
    public function delete(Request $req, Response $res): void
    {
        try {
            $notificationId = $req->getPathParam('id');
            
            if (!$notificationId) {
                $res->json(ResponseHelper::badRequest('Notification ID is required'));
                return;
            }

            $sql = "DELETE FROM admin_notifications WHERE notification_id = :notification_id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':notification_id', $notificationId, PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->rowCount() === 0) {
                $res->json(ResponseHelper::notFound('Notification not found'));
                return;
            }

            $res->json(ResponseHelper::success(null, 'Notification deleted successfully'));

        } catch (Exception $e) {
            $res->json(ResponseHelper::serverError('Failed to delete notification: ' . $e->getMessage()));
        }
    }
}
