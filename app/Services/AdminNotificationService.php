<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use Throwable;

/**
 * Ghi thông báo vào admin_notifications (in-app chuông admin, không dùng bảng notifications của shipper).
 */
final class AdminNotificationService
{
    public const TYPE_NEW_ORDER = 'new_order';
    public const TYPE_PAYMENT_UPDATE = 'payment_update';
    public const TYPE_ORDER_ASSIGNED = 'order_assigned';
    public const TYPE_ORDER_REJECTED = 'order_rejected';
    public const TYPE_ORDER_REASSIGNED = 'order_reassigned';
    public const TYPE_ORDER_REASSIGN_FAILED = 'order_reassign_failed';
    public const TYPE_ORDER_ACCEPT_TIMEOUT = 'order_accept_timeout';

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @param int|null $relatedOrderId
     * @param int|null $relatedShipperId user_id thuộc bảng shippers
     */
    public function create(
        string $type,
        string $title,
        string $message,
        ?int $relatedOrderId = null,
        ?int $relatedShipperId = null
    ): void {
        try {
            $sql = 'INSERT INTO admin_notifications (type, title, message, related_order_id, related_shipper_id, is_read, created_at)
                    VALUES (:type, :title, :message, :oid, :sid, 0, NOW())';
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':type', $type);
            $stmt->bindValue(':title', $title);
            $stmt->bindValue(':message', $message);
            if ($relatedOrderId === null) {
                $stmt->bindValue(':oid', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':oid', $relatedOrderId, PDO::PARAM_INT);
            }
            if ($relatedShipperId === null) {
                $stmt->bindValue(':sid', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':sid', $relatedShipperId, PDO::PARAM_INT);
            }
            $stmt->execute();
            $id = (int) $this->pdo->lastInsertId();
            if ($id > 0) {
                self::queueRealtimePayload([
                    'notification_id' => $id,
                    'type' => $type,
                    'title' => $title,
                    'message' => $message,
                    'related_order_id' => $relatedOrderId,
                    'related_shipper_id' => $relatedShipperId,
                    'is_read' => 0,
                    'created_at' => (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
                ]);
            }
        } catch (Throwable $e) {
            error_log('[AdminNotificationService] ' . $e->getMessage());
        }
    }

    /**
     * Ghi hàng đợi cho WebSocket (Ratchet) — process ở tiến trình `php websocket_server.php`.
     * @param array<string, mixed> $payload
     */
    public static function queueRealtimePayload(array $payload): void
    {
        $path = \App\Services\WebSocketService::getAdminNotificationQueuePath();
        $dir = \dirname($path);
        if (!is_dir($dir)) {
            return;
        }
        $fp = fopen($path, 'c+');
        if ($fp === false) {
            return;
        }
        if (!flock($fp, LOCK_EX)) {
            fclose($fp);
            return;
        }
        $raw = stream_get_contents($fp);
        $queue = $raw ? json_decode($raw, true) : [];
        if (!is_array($queue)) {
            $queue = [];
        }
        $queue[] = ['payload' => $payload, 'ts' => time()];
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($queue, JSON_UNESCAPED_UNICODE));
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
    }

    public function notifyNewOrder(int $orderId, float $totalAmount): void
    {
        $this->create(
            self::TYPE_NEW_ORDER,
            'Đơn hàng mới',
            'Có đơn mới #' . $orderId . ' — tổng ' . number_format((float)$totalAmount, 0, ',', '.') . ' đ.',
            $orderId,
            null
        );
    }

    public function notifyPaymentPending(int $orderId, string $methodLabel = 'online'): void
    {
        $this->create(
            self::TYPE_PAYMENT_UPDATE,
            'Chờ thanh toán',
            'Đơn #' . $orderId . ' đang chờ thanh toán (' . $methodLabel . ').',
            $orderId,
            null
        );
    }

    public function notifyPaymentConfirmed(int $orderId, int $paymentId): void
    {
        $this->create(
            self::TYPE_PAYMENT_UPDATE,
            'Thanh toán đã xác nhận',
            'Đơn #' . $orderId . ' — thanh toán #' . $paymentId . ' đã được xác nhận.',
            $orderId,
            null
        );
    }

    public function notifyOrderAssigned(int $orderId, int $shipperId): void
    {
        $this->create(
            self::TYPE_ORDER_ASSIGNED,
            'Cập nhật giao hàng',
            'Đơn #' . $orderId . ' đã gán tài xế (shipper #' . $shipperId . ').',
            $orderId,
            $shipperId
        );
    }

    public function notifyOrderRejected(int $orderId, int $shipperId, string $shipperName, string $note): void
    {
        $reason = trim($note) !== '' ? trim($note) : '(không ghi lý do)';
        $this->create(
            self::TYPE_ORDER_REJECTED,
            'Shipper từ chối đơn',
            'Đơn #' . $orderId . ' — ' . $shipperName . ': "' . $reason . '"',
            $orderId,
            $shipperId
        );
    }

    public function notifyOrderReassigned(int $orderId, int $newShipperId, string $shipperName): void
    {
        $this->create(
            self::TYPE_ORDER_REASSIGNED,
            'Đã gán lại shipper',
            'Đơn #' . $orderId . ' đã gán lại cho ' . $shipperName . ' (shipper #' . $newShipperId . ').',
            $orderId,
            $newShipperId
        );
    }

    public function notifyOrderReassignFailed(int $orderId): void
    {
        $this->create(
            self::TYPE_ORDER_REASSIGN_FAILED,
            'Cần gán shipper thủ công',
            'Đơn #' . $orderId . ' bị từ chối nhưng không còn shipper rảnh để tự gán lại.',
            $orderId,
            null
        );
    }

    public function notifyAcceptTimeoutNoShipper(int $orderId, int $timeoutMinutes): void
    {
        $this->create(
            self::TYPE_ORDER_ACCEPT_TIMEOUT,
            'Không có shipper nhận đơn',
            'Đơn #' . $orderId . ' quá ' . $timeoutMinutes . ' phút không được chấp nhận và không còn shipper rảnh để tự gán.',
            $orderId,
            null
        );
    }

    public function notifyAcceptTimeoutExhausted(int $orderId, int $maxAttempts): void
    {
        $this->create(
            self::TYPE_ORDER_ACCEPT_TIMEOUT,
            'Cần xử lý đơn thủ công',
            'Đơn #' . $orderId . ' đã quá ' . $maxAttempts . ' lần tự gán lại do hết thời gian chấp nhận. Vui lòng gán shipper thủ công.',
            $orderId,
            null
        );
    }
}
