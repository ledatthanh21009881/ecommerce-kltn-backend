<?php

declare(strict_types=1);

namespace App\Support;

use PDO;

/**
 * Giới hạn số đơn đang xử lý (processing + shipping) trên mỗi shipper.
 */
final class ShipperCapacity
{
    public static function maxActiveOrders(): int
    {
        $max = (int) ($_ENV['SHIPPER_MAX_ACTIVE_ORDERS'] ?? 5);

        return max(1, $max);
    }

    /**
     * Đếm đơn đang gánh của shipper (processing + shipping).
     *
     * @param int|null $excludeOrderId Bỏ qua đơn này (gán lại cùng shipper / đổi shipper trên cùng đơn).
     */
    public static function countActiveOrders(PDO $pdo, int $shipperId, ?int $excludeOrderId = null): int
    {
        if ($shipperId <= 0) {
            return 0;
        }

        $sql = "
            SELECT COUNT(*)
            FROM shipping_tracking st
            INNER JOIN orders o ON o.order_id = st.order_id
            WHERE st.shipper_id = ?
              AND o.status IN ('processing', 'shipping')
        ";
        $params = [$shipperId];

        if ($excludeOrderId !== null && $excludeOrderId > 0) {
            $sql .= ' AND o.order_id != ?';
            $params[] = $excludeOrderId;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public static function hasCapacity(PDO $pdo, int $shipperId, ?int $excludeOrderId = null): bool
    {
        return self::countActiveOrders($pdo, $shipperId, $excludeOrderId) < self::maxActiveOrders();
    }

    public static function forbiddenMessage(PDO $pdo, int $shipperId, ?int $excludeOrderId = null): string
    {
        $max = self::maxActiveOrders();
        $current = self::countActiveOrders($pdo, $shipperId, $excludeOrderId);

        return sprintf(
            'Shipper đã đạt giới hạn %d đơn đang xử lý (hiện có %d). Vui lòng chọn shipper khác hoặc chờ shipper hoàn tất bớt đơn.',
            $max,
            $current
        );
    }
}
