<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Controller, Request, Response, Container};
use App\Domain\Orders\Order;
use App\Support\GeocodingService;
use App\Support\ResponseHelper;
use PDO;
use Exception;

/**
 * User-scoped order APIs for customer account.
 * Resolves customer from authenticated user via customers table.
 * orders.customer_id = customers.user_id in this schema.
 */
class UserOrderController extends Controller
{
    private PDO $pdo;
    private Order $orderModel;

    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->pdo = $container->database()->getConnection();
        $this->orderModel = new Order($container->get('database'));
    }

    /**
     * Resolve customer_id for order filtering from authenticated user.
     * customers.user_id = users.user_id; orders.customer_id = customers.user_id.
     * Returns user_id as the value to filter orders, or null if not a customer.
     */
    private function resolveCustomerId(Request $req): ?int
    {
        $user = $req->getAttribute('user');
        if (!$user || empty($user['user_id'])) {
            return null;
        }
        $userId = (int) $user['user_id'];
        $stmt = $this->pdo->prepare('SELECT user_id FROM customers WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int) $row['user_id'] : null;
    }

    /**
     * GET /api/user/orders
     * Return only orders belonging to the authenticated customer.
     */
    public function index(Request $req, Response $res): void
    {
        try {
            $customerId = $this->resolveCustomerId($req);
            if ($customerId === null) {
                $res->json(ResponseHelper::forbidden('Customer access required'));
                return;
            }

            $page = (int) ($req->query('page') ?? 1);
            $limit = (int) ($req->query('limit') ?? 20);
            $offset = ($page - 1) * $limit;

            $filters = ['customer_id' => $customerId];
            if ($req->query('status')) {
                $filters['status'] = $req->query('status');
            }
            if ($req->query('date_from')) {
                $filters['date_from'] = $req->query('date_from');
            }
            if ($req->query('date_to')) {
                $filters['date_to'] = $req->query('date_to');
            }

            $orders = $this->orderModel->getAll($filters, $limit, $offset);
            $total = $this->orderModel->getCount($filters);

            foreach ($orders as &$order) {
                $order['items'] = $this->orderModel->getOrderItems((int) $order['order_id']);
            }
            unset($order);

            $res->json(ResponseHelper::paginated($orders, $total, $limit, $page));
        } catch (Exception $e) {
            $res->json(ResponseHelper::serverError('Failed to fetch orders: ' . $e->getMessage()));
        }
    }

    /**
     * GET /api/user/orders/{id}
     * Full order detail; ensure order belongs to customer.
     */
    public function show(Request $req, Response $res): void
    {
        try {
            $customerId = $this->resolveCustomerId($req);
            if ($customerId === null) {
                $res->json(ResponseHelper::forbidden('Customer access required'));
                return;
            }

            $id = (int) $req->getAttribute('id');
            $order = $this->orderModel->getByIdWithDetails($id);
            if (!$order) {
                $res->json(ResponseHelper::notFound('Order not found'));
                return;
            }
            if ((int) $order['customer_id'] !== $customerId) {
                $res->json(ResponseHelper::notFound('Order not found'));
                return;
            }

            $res->json(ResponseHelper::success($order));
        } catch (Exception $e) {
            $res->json(ResponseHelper::serverError('Failed to fetch order: ' . $e->getMessage()));
        }
    }

    /**
     * GET /api/user/orders/{id}/tracking
     * Return { shipper, orders } in the shape MapboxShipperDetailMapDemo expects.
     * Reuse TrackingController SQL; do not call Mapbox/Directions on backend.
     */
    public function tracking(Request $req, Response $res): void
    {
        try {
            $customerId = $this->resolveCustomerId($req);
            if ($customerId === null) {
                $res->json(ResponseHelper::forbidden('Customer access required'));
                return;
            }

            $orderId = (int) $req->getAttribute('id');
            $order = $this->orderModel->findById($orderId);
            if (!$order) {
                $res->json(ResponseHelper::notFound('Order not found'));
                return;
            }
            if ((int) $order['customer_id'] !== $customerId) {
                $res->json(ResponseHelper::notFound('Order not found'));
                return;
            }

            // Khớp admin TrackingController: shipper hiện tại + vị trí từ shipper_locations hoặc shipping_tracking
            $sql = "
                SELECT
                    o.order_id,
                    o.status,
                    o.total_amount,
                    o.created_at,
                    o.estimated_delivery_at,
                    o.shipping_address_snapshot,
                    CONCAT(cu.first_name, ' ', cu.last_name) AS customer_name,
                    cu.phone AS customer_phone,
                    JSON_UNQUOTE(JSON_EXTRACT(o.shipping_address_snapshot, '$.address_line')) AS customer_address,
                    st_current.shipper_id,
                    CONCAT(u.first_name, ' ', u.last_name) AS shipper_name,
                    u.phone AS shipper_phone,
                    s.vehicle_info,
                    s.rating,
                    a.lat AS addr_dest_lat,
                    a.lng AS addr_dest_lng,
                    COALESCE(sl.lat, st_latest.current_lat) AS current_lat,
                    COALESCE(sl.lng, st_latest.current_lng) AS current_lng,
                    sl.captured_at AS location_updated_at,
                    (
                        SELECT COUNT(*) FROM order_tracking_events ote WHERE ote.order_id = o.order_id
                    ) AS event_count,
                    (
                        SELECT ote.status FROM order_tracking_events ote
                        WHERE ote.order_id = o.order_id ORDER BY ote.created_at DESC LIMIT 1
                    ) AS last_status,
                    (
                        SELECT ote.created_at FROM order_tracking_events ote
                        WHERE ote.order_id = o.order_id ORDER BY ote.created_at DESC LIMIT 1
                    ) AS last_event_at
                FROM orders o
                LEFT JOIN (
                    SELECT stx.order_id, MAX(stx.shipper_id) AS shipper_id
                    FROM shipping_tracking stx
                    INNER JOIN (
                        SELECT order_id, MAX(last_updated) AS max_last_updated
                        FROM shipping_tracking
                        GROUP BY order_id
                    ) latest_st
                        ON latest_st.order_id = stx.order_id
                        AND latest_st.max_last_updated = stx.last_updated
                    GROUP BY stx.order_id
                ) st_current ON o.order_id = st_current.order_id
                LEFT JOIN shipping_tracking st_latest
                    ON st_latest.order_id = o.order_id
                    AND st_latest.shipper_id = st_current.shipper_id
                    AND st_latest.last_updated = (
                        SELECT MAX(st2.last_updated)
                        FROM shipping_tracking st2
                        WHERE st2.order_id = o.order_id
                          AND st2.shipper_id = st_current.shipper_id
                    )
                LEFT JOIN shippers s ON st_current.shipper_id = s.user_id
                LEFT JOIN users u ON s.user_id = u.user_id
                LEFT JOIN customers c ON o.customer_id = c.user_id
                LEFT JOIN users cu ON c.user_id = cu.user_id
                LEFT JOIN addresses a ON o.address_id = a.address_id
                LEFT JOIN shipper_locations sl
                    ON sl.shipper_id = st_current.shipper_id
                    AND sl.order_id = o.order_id
                    AND sl.captured_at = (
                        SELECT MAX(sl2.captured_at)
                        FROM shipper_locations sl2
                        WHERE sl2.shipper_id = st_current.shipper_id
                          AND sl2.order_id = o.order_id
                    )
                WHERE o.order_id = ?
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$orderId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                $res->json(ResponseHelper::success(['shipper' => null, 'orders' => []]));
                return;
            }

            $destination = $this->resolveCustomerOrderDestinationForTracking($row);

            $shipper = null;
            if (!empty($row['shipper_id']) && $destination['lat'] !== null && $destination['lng'] !== null) {
                $shipper = [
                    'user_id' => (int) $row['shipper_id'],
                    'shipper_name' => $row['shipper_name'] ?? '',
                    'phone' => $row['shipper_phone'] ?? '',
                    'vehicle_info' => $row['vehicle_info'] ?? '',
                    'rating' => $row['rating'] !== null ? (float) $row['rating'] : null,
                    'on_time_delivery_pct' => null,
                    'total_delivered' => 0,
                    'is_available' => false,
                    'status' => 'active',
                    'created_at' => $row['created_at'] ?? date('c'),
                    'current_lat' => $this->normalizeCoordScalar($row['current_lat']),
                    'current_lng' => $this->normalizeCoordScalar($row['current_lng']),
                    'location_updated_at' => $row['location_updated_at'] ?? null,
                    'active_orders_count' => 1,
                ];
            }

            $orderTracking = [
                'order_id' => (int) $row['order_id'],
                'status' => $row['status'],
                'total_amount' => (float) $row['total_amount'],
                'created_at' => $row['created_at'],
                'estimated_delivery_at' => $row['estimated_delivery_at'] ?? null,
                'customer_name' => $row['customer_name'] ?? '',
                'customer_phone' => $row['customer_phone'] ?? '',
                'customer_address' => $row['customer_address'] ?? '',
                'shipper_id' => $row['shipper_id'] ? (int) $row['shipper_id'] : null,
                'shipper_name' => $row['shipper_name'] ?? null,
                'shipper_phone' => $row['shipper_phone'] ?? null,
                'destination_lat' => $destination['lat'],
                'destination_lng' => $destination['lng'],
                'event_count' => (int) ($row['event_count'] ?? 0),
                'last_status' => $row['last_status'] ?? null,
                'last_event_at' => $row['last_event_at'] ?? null,
            ];

            $res->json(ResponseHelper::success([
                'shipper' => $shipper,
                'orders' => [$orderTracking],
            ]));
        } catch (Exception $e) {
            $res->json(ResponseHelper::serverError('Failed to fetch tracking: ' . $e->getMessage()));
        }
    }

    /** @param array<string,mixed> $trackingRow FROM orders + joins (addr_dest_lat, addr_dest_lng, shipping_address_snapshot) */
    /** @return array{lat: ?float, lng: ?float} */
    private function resolveCustomerOrderDestinationForTracking(array $trackingRow): array
    {
        $lat = $this->normalizeCoordScalar($trackingRow['addr_dest_lat'] ?? null);
        $lng = $this->normalizeCoordScalar($trackingRow['addr_dest_lng'] ?? null);
        if ($lat !== null && $lng !== null) {
            return ['lat' => $lat, 'lng' => $lng];
        }

        $raw = $trackingRow['shipping_address_snapshot'] ?? null;
        if ($raw !== null && $raw !== '') {
            $decoded = json_decode((string) $raw, true);
            if (is_array($decoded)) {
                $pairs = [['lat', 'lng'], ['latitude', 'longitude'], ['lat', 'lon']];
                foreach ($pairs as [$lk, $gnk]) {
                    $tryLat = $this->normalizeCoordScalar($decoded[$lk] ?? null);
                    $tryLng = $this->normalizeCoordScalar($decoded[$gnk] ?? null);
                    if ($tryLat !== null && $tryLng !== null) {
                        return ['lat' => $tryLat, 'lng' => $tryLng];
                    }
                }

                $geo = GeocodingService::resolveFromParts(
                    (string) ($decoded['address_line'] ?? ''),
                    (string) ($decoded['ward'] ?? ''),
                    (string) ($decoded['district'] ?? ''),
                    (string) ($decoded['province'] ?? ''),
                );
                if ($geo !== null) {
                    return ['lat' => $geo['lat'], 'lng' => $geo['lng']];
                }
            }
        }

        return ['lat' => null, 'lng' => null];
    }

    private function normalizeCoordScalar(mixed $v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (!is_numeric($v)) {
            return null;
        }
        $n = (float) $v;

        return is_finite($n) ? $n : null;
    }
}
