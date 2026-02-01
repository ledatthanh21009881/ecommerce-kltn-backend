<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Controller, Request, Response, Container};
use App\Domain\Orders\Order;
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

            $sql = "
                SELECT 
                    o.order_id,
                    o.status,
                    o.total_amount,
                    o.created_at,
                    o.estimated_delivery_at,
                    CONCAT(c.first_name, ' ', c.last_name) as customer_name,
                    c.phone as customer_phone,
                    JSON_UNQUOTE(JSON_EXTRACT(o.shipping_address_snapshot, '$.address_line')) as customer_address,
                    st.shipper_id,
                    CONCAT(u.first_name, ' ', u.last_name) as shipper_name,
                    u.phone as shipper_phone,
                    s.vehicle_info,
                    s.rating,
                    sl.lat as current_lat,
                    sl.lng as current_lng,
                    sl.captured_at as location_updated_at,
                    a.lat as destination_lat,
                    a.lng as destination_lng,
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
                WHERE o.order_id = ?
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$orderId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                $res->json(ResponseHelper::success(['shipper' => null, 'orders' => []]));
                return;
            }

            $shipper = null;
            if (!empty($row['shipper_id']) && $row['destination_lat'] !== null && $row['destination_lng'] !== null) {
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
                    'current_lat' => $row['current_lat'] !== null ? (float) $row['current_lat'] : null,
                    'current_lng' => $row['current_lng'] !== null ? (float) $row['current_lng'] : null,
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
                'destination_lat' => $row['destination_lat'] !== null ? (float) $row['destination_lat'] : null,
                'destination_lng' => $row['destination_lng'] !== null ? (float) $row['destination_lng'] : null,
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
}
