<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\FirebaseService;
use PDO;
use Exception;

/**
 * NotificationService - Handles notification creation and sending
 */
class NotificationService
{
    private PDO $pdo;
    private ?FirebaseService $firebaseService = null;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        try {
            $this->firebaseService = new FirebaseService();
        } catch (Exception $e) {
            error_log('[NotificationService] Failed to initialize FirebaseService: ' . $e->getMessage());
            // Continue without Firebase - notifications will still be saved to database
            $this->firebaseService = null;
        }
    }

    /**
     * Create a notification record in the database
     * 
     * @param int $userId The user ID (shipper_id)
     * @param string $type Notification type (e.g., 'new_order_assigned')
     * @param string $title Notification title
     * @param string $message Notification message
     * @param array $payload Additional data as JSON
     * @return int|false Notification ID on success, false on failure
     */
    public function createNotification(int $userId, string $type, string $title, string $message, array $payload = [])
    {
        try {
            $sql = "INSERT INTO notifications (user_id, type, title, message, payload, is_read, created_at) 
                    VALUES (?, ?, ?, ?, ?, 0, NOW())";
            
            $stmt = $this->pdo->prepare($sql);
            $payloadJson = !empty($payload) ? json_encode($payload) : null;
            
            $result = $stmt->execute([$userId, $type, $title, $message, $payloadJson]);
            
            if ($result) {
                return (int) $this->pdo->lastInsertId();
            }
            
            return false;
        } catch (Exception $e) {
            error_log('[NotificationService] Error creating notification: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send push notification to a shipper
     * 
     * @param int $shipperId The shipper's user_id
     * @param string $title Notification title
     * @param string $message Notification message
     * @param array $data Additional data for FCM payload
     * @param string $type Notification type (default: 'new_order_assigned')
     * @return bool True if notification was sent successfully (or saved to DB if FCM fails)
     */
    public function sendPushNotification(int $shipperId, string $title, string $message, array $data = [], string $type = 'new_order_assigned'): bool
    {
        try {
            // 1. Get FCM token from shippers table
            $sql = "SELECT fcm_token FROM shippers WHERE user_id = ? AND fcm_token IS NOT NULL AND fcm_token != ''";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$shipperId]);
            $shipper = $stmt->fetch(PDO::FETCH_ASSOC);

            // 2. Create notification record in database
            $notificationId = $this->createNotification($shipperId, $type, $title, $message, $data);
            
            if (!$notificationId) {
                error_log('[NotificationService] Failed to create notification record for shipper: ' . $shipperId);
                return false;
            }

            // 3. Send FCM push notification if token exists and FirebaseService is available
            if ($shipper && !empty($shipper['fcm_token']) && $this->firebaseService !== null) {
                $fcmSent = $this->firebaseService->sendNotification(
                    $shipper['fcm_token'],
                    $title,
                    $message,
                    $data
                );

                if ($fcmSent) {
                    error_log("[NotificationService] FCM notification sent successfully to shipper: {$shipperId}");
                } else {
                    error_log("[NotificationService] FCM notification failed for shipper: {$shipperId}, but notification saved to database");
                }
            } else {
                if (!$shipper || empty($shipper['fcm_token'])) {
                    error_log("[NotificationService] No FCM token found for shipper: {$shipperId}. Notification saved to database only.");
                }
            }

            return true;

        } catch (Exception $e) {
            error_log('[NotificationService] Error sending push notification: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get unread notification count for a user
     * 
     * @param int $userId The user ID
     * @return int Unread count
     */
    public function getUnreadCount(int $userId): int
    {
        try {
            $sql = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int) ($result['count'] ?? 0);
        } catch (Exception $e) {
            error_log('[NotificationService] Error getting unread count: ' . $e->getMessage());
            return 0;
        }
    }
}

