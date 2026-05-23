<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Orders\Order;
use Exception;
use PDO;
use Throwable;

/**
 * Đơn ở shipping_status new_request quá N giây không accept → gán shipper khác (loại trừ shipper cũ).
 * Không còn shipper rảnh → thông báo admin.
 */
final class ShipperAcceptTimeoutService
{
    public const ACTIVITY_ACCEPT_TIMEOUT = 'accept_timeout_reassign';
    public const ACTIVITY_ACCEPT_TIMEOUT_EXHAUSTED = 'accept_timeout_exhausted';

    public function __construct(
        private PDO $pdo,
        private Order $orderModel,
        private int $timeoutSeconds = 120,
        private int $maxReassignAttempts = 5,
    ) {
    }

    /**
     * @return array{scanned: int, reassigned: int, no_shipper: int, max_attempts: int, skipped: int, errors: int}
     */
    public function processStaleAssignments(): array
    {
        $stats = [
            'scanned' => 0,
            'reassigned' => 0,
            'no_shipper' => 0,
            'max_attempts' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];

        $rows = $this->findStaleOrders();
        $stats['scanned'] = count($rows);
        $timeoutMinutes = (int) max(1, round($this->timeoutSeconds / 60));

        foreach ($rows as $row) {
            $orderId = (int) $row['order_id'];
            $oldShipperId = (int) $row['shipper_id'];
            if ($orderId <= 0 || $oldShipperId <= 0) {
                $stats['skipped']++;
                continue;
            }

            try {
                $result = $this->processOneOrder($orderId, $oldShipperId, $timeoutMinutes);
                if (isset($stats[$result])) {
                    $stats[$result]++;
                } else {
                    $stats['skipped']++;
                }
            } catch (Throwable $e) {
                $stats['errors']++;
                error_log('[ShipperAcceptTimeout] order #' . $orderId . ': ' . $e->getMessage());
            }
        }

        return $stats;
    }

    /**
     * @return list<array{order_id: int, shipper_id: int, status: string}>
     */
    private function findStaleOrders(): array
    {
        $sql = "
            SELECT o.order_id, o.status, st.shipper_id
            FROM orders o
            INNER JOIN shipping_tracking st ON st.order_id = o.order_id
            WHERE o.shipping_status = 'new_request'
              AND o.shipping_status_updated_at IS NOT NULL
              AND o.shipping_status_updated_at <= DATE_SUB(NOW(), INTERVAL :seconds SECOND)
              AND st.shipper_id IS NOT NULL
              AND o.status NOT IN ('cancelled', 'completed', 'returned', 'failed')
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':seconds', $this->timeoutSeconds, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    }

    private function countTimeoutReassigns(int $orderId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM activity_logs
             WHERE entity_type = 'order' AND entity_id = ? AND action = ?"
        );
        $stmt->execute([$orderId, self::ACTIVITY_ACCEPT_TIMEOUT]);

        return (int) $stmt->fetchColumn();
    }

    private function hasExhaustedAdminNotified(int $orderId): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM activity_logs
             WHERE entity_type = 'order' AND entity_id = ? AND action = ?"
        );
        $stmt->execute([$orderId, self::ACTIVITY_ACCEPT_TIMEOUT_EXHAUSTED]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * @return 'reassigned'|'no_shipper'|'max_attempts'|'skipped'
     */
    private function processOneOrder(
        int $orderId,
        int $oldShipperId,
        int $timeoutMinutes,
    ): string {
        $fresh = $this->orderModel->find($orderId);
        if (!$fresh || ($fresh['shipping_status'] ?? '') !== 'new_request') {
            return 'skipped';
        }

        $orderStatus = (string) ($fresh['status'] ?? '');

        $trackingStmt = $this->pdo->prepare(
            'SELECT shipper_id FROM shipping_tracking WHERE order_id = ? LIMIT 1'
        );
        $trackingStmt->execute([$orderId]);
        $tracking = $trackingStmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($tracking) || (int) ($tracking['shipper_id'] ?? 0) !== $oldShipperId) {
            return 'skipped';
        }

        if ($this->countTimeoutReassigns($orderId) >= $this->maxReassignAttempts) {
            if (!$this->hasExhaustedAdminNotified($orderId)) {
                $this->notifyAdminMaxAttempts($orderId);
                $this->orderModel->logActivity($orderId, self::ACTIVITY_ACCEPT_TIMEOUT_EXHAUSTED, 1, null, null);
            }
            return 'max_attempts';
        }

        $timeoutNote = sprintf(
            'Shipper did not accept within %d minutes (auto-reassign)',
            $timeoutMinutes
        );

        try {
            $this->orderModel->forceShippingStatus($orderId, 'rejected', $oldShipperId, [
                'note' => $timeoutNote,
                'metadata' => ['reason' => 'accept_timeout'],
            ]);
        } catch (Exception $e) {
            error_log('[ShipperAcceptTimeout] forceShippingStatus rejected #' . $orderId . ': ' . $e->getMessage());
        }

        if (!in_array($orderStatus, ['pending', 'processing'], true)) {
            $this->orderModel->updateStatus(
                $orderId,
                'processing',
                1,
                'Reset to processing after accept timeout'
            );
        }

        $this->orderModel->clearShipperAssignment($orderId);
        $this->orderModel->logActivity(
            $orderId,
            self::ACTIVITY_ACCEPT_TIMEOUT,
            1,
            ['shipper_id' => $oldShipperId],
            null
        );

        $newShipperId = $this->orderModel->autoAssignBestAvailableShipper(
            $orderId,
            1,
            'Reassigned after accept timeout',
            $oldShipperId
        );

        $adminNotif = new AdminNotificationService($this->pdo);

        if ($newShipperId !== null) {
            $shipperName = $this->resolveShipperDisplayName($newShipperId);
            try {
                $adminNotif->notifyOrderReassigned($orderId, $newShipperId, $shipperName);
            } catch (Throwable $e) {
                error_log('[ShipperAcceptTimeout] admin reassign notify: ' . $e->getMessage());
            }
            $this->notifyShipperNewAssignment($orderId, $newShipperId);
            $this->notifyShipperTimeoutReleased($orderId, $oldShipperId);

            return 'reassigned';
        }

        try {
            $adminNotif->notifyAcceptTimeoutNoShipper($orderId, $timeoutMinutes);
        } catch (Throwable $e) {
            error_log('[ShipperAcceptTimeout] admin no-shipper notify: ' . $e->getMessage());
        }

        return 'no_shipper';
    }

    private function notifyAdminMaxAttempts(int $orderId): void
    {
        try {
            (new AdminNotificationService($this->pdo))->notifyAcceptTimeoutExhausted(
                $orderId,
                $this->maxReassignAttempts
            );
        } catch (Throwable $e) {
            error_log('[ShipperAcceptTimeout] admin max attempts notify: ' . $e->getMessage());
        }
    }

    private function notifyShipperNewAssignment(int $orderId, int $shipperId): void
    {
        try {
            (new NotificationService($this->pdo))->sendPushNotification(
                $shipperId,
                'Đơn hàng mới được gán',
                "Bạn có đơn hàng #{$orderId} mới cần xử lý",
                ['order_id' => $orderId, 'type' => 'new_order_assigned'],
                'new_order_assigned'
            );
        } catch (Throwable $e) {
            error_log('[ShipperAcceptTimeout] push new shipper: ' . $e->getMessage());
        }
    }

    private function notifyShipperTimeoutReleased(int $orderId, int $shipperId): void
    {
        try {
            (new NotificationService($this->pdo))->sendPushNotification(
                $shipperId,
                'Đơn đã được chuyển',
                "Đơn #{$orderId} đã được chuyển cho shipper khác do quá thời gian chấp nhận.",
                ['order_id' => $orderId, 'type' => 'order_accept_timeout'],
                'order_accept_timeout'
            );
        } catch (Throwable $e) {
            error_log('[ShipperAcceptTimeout] push old shipper: ' . $e->getMessage());
        }
    }

    private function resolveShipperDisplayName(int $shipperUserId): string
    {
        $stmt = $this->pdo->prepare(
            'SELECT first_name, last_name FROM users WHERE user_id = ? LIMIT 1'
        );
        $stmt->execute([$shipperUserId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return 'Shipper #' . $shipperUserId;
        }
        $name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
        return $name !== '' ? $name : 'Shipper #' . $shipperUserId;
    }
}
