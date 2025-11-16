<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Controller, Request, Response, Container};
use App\Domain\Orders\{Order, OrderItem};
use App\Support\ResponseHelper;
use App\Core\Validator;
use App\Services\NotificationService;
use Exception;
use PDO;

class OrderController extends Controller 
{
    private Order $orderModel;
    private OrderItem $orderItemModel;
    private array $shippingTransitions;
    
    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->orderModel = new Order($container->get('database'));
        $this->orderItemModel = new OrderItem($container->get('database'));
        $this->shippingTransitions = Order::SHIPPING_TRANSITIONS;
    }
    
    /**
     * GET /api/orders - Lấy danh sách đơn hàng với filter và pagination
     */
    public function index(Request $req, Response $res)
    {
        try {
            $page = (int)($req->query('page') ?? 1);
            $limit = (int)($req->query('limit') ?? 20);
            $offset = ($page - 1) * $limit;
            
            $filters = [];
            if ($req->query('status')) {
                $filters['status'] = $req->query('status');
            }
            if ($req->query('shipping_status')) {
                $filters['shipping_status'] = $req->query('shipping_status');
            }
            if ($req->query('customer_id')) {
                $filters['customer_id'] = $req->query('customer_id');
            }
            if ($req->query('search')) {
                $filters['search'] = $req->query('search');
            }
            if ($req->query('date_from')) {
                $filters['date_from'] = $req->query('date_from');
            }
            if ($req->query('date_to')) {
                $filters['date_to'] = $req->query('date_to');
            }
            
            $orders = $this->orderModel->getAll($filters, $limit, $offset);
            $total = $this->orderModel->getCount($filters);
            
            return $res->json(ResponseHelper::paginated($orders, $total, $limit, $page));
            
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to fetch orders: ' . $e->getMessage()));
        }
    }
    
    /**
     * GET /api/orders/statistics - Lấy thống kê đơn hàng
     */
    public function statistics(Request $req, Response $res)
    {
        try {
            $stats = $this->orderModel->getStatistics();
            return $res->json(ResponseHelper::success($stats));
            
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to fetch statistics: ' . $e->getMessage()));
        }
    }
    
    /**
     * GET /api/orders/{id} - Xem chi tiết đơn hàng
     */
    public function show(Request $req, Response $res)
    {
        try {
            $id = (int) $req->getAttribute('id');
            
            $order = $this->orderModel->getByIdWithDetails($id);
            if (!$order) {
                return $res->json(ResponseHelper::notFound('Order not found'));
            }
            
            return $res->json(ResponseHelper::success($order));
            
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to fetch order: ' . $e->getMessage()));
        }
    }
    
    /**
     * POST /api/orders - Tạo đơn hàng mới
     */
    public function store(Request $req, Response $res)
    {
        try {
            $data = $req->body();
            
            // Validate required fields
            $validator = new Validator($data);
            $validator->required(['customer_id', 'address_id', 'shipping_method_id', 'items'])
                     ->integer(['customer_id', 'address_id', 'shipping_method_id'])
                     ->array('items');
            
            if (!$validator->validate()) {
                return $res->json(ResponseHelper::validationError($validator->getErrors()));
            }
            
            // Validate items
            if (empty($data['items'])) {
                return $res->json(ResponseHelper::validationError(['items' => 'Order must have at least one item']));
            }
            
            // Validate stock for each item
            foreach ($data['items'] as $item) {
                if (!isset($item['variant_id']) || !isset($item['quantity'])) {
                    return $res->json(ResponseHelper::validationError(['items' => 'Each item must have variant_id and quantity']));
                }
                
                if (!$this->orderItemModel->validateStock($item['variant_id'], $item['quantity'])) {
                    return $res->json(ResponseHelper::validationError(['items' => 'Insufficient stock for some items']));
                }
            }
            
            // Calculate totals
            $subtotal = 0;
            foreach ($data['items'] as $item) {
                // Get product variant price
                $stmt = $this->getConnection()->prepare("SELECT list_price FROM products p JOIN product_variants pv ON p.product_id = pv.product_id WHERE pv.variant_id = ?");
                $stmt->execute([$item['variant_id']]);
                $price = $stmt->fetchColumn();
                $subtotal += $price * $item['quantity'];
            }
            
            // Get shipping fee
            $stmt = $this->getConnection()->prepare("SELECT fee FROM shipping_methods WHERE shipping_method_id = ?");
            $stmt->execute([$data['shipping_method_id']]);
            $shippingFee = $stmt->fetchColumn() ?? 0;
            
            // Calculate discount if voucher is applied
            $discountAmount = 0;
            if (!empty($data['voucher_id'])) {
                $stmt = $this->getConnection()->prepare("SELECT discount_amount, discount_type FROM vouchers WHERE voucher_id = ? AND status = 'active'");
                $stmt->execute([$data['voucher_id']]);
                $voucher = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($voucher) {
                    if ($voucher['discount_type'] === 'percent') {
                        $discountAmount = ($subtotal * $voucher['discount_amount']) / 100;
                    } else {
                        $discountAmount = $voucher['discount_amount'];
                    }
                }
            }
            
            $totalAmount = $subtotal + $shippingFee - $discountAmount;
            
            // Create order
            $orderData = [
                'customer_id' => $data['customer_id'],
                'address_id' => $data['address_id'],
                'shipping_method_id' => $data['shipping_method_id'],
                'voucher_id' => $data['voucher_id'] ?? null,
                'voucher_code_applied' => $data['voucher_code'] ?? null,
                'discount_amount_applied' => $discountAmount,
                'total_amount' => $totalAmount,
                'shipping_fee' => $shippingFee,
                'cod_amount' => $data['cod_amount'] ?? 0,
                'status' => 'pending',
                'shipping_status' => 'new_request',
                'note' => $data['note'] ?? '',
                'internal_note' => $data['internal_note'] ?? '',
                'estimated_delivery_at' => $data['estimated_delivery_at'] ?? null
            ];
            
            $orderId = $this->orderModel->create($orderData);
            
            // Create order items
            foreach ($data['items'] as $item) {
                $stmt = $this->getConnection()->prepare("SELECT p.list_price, p.product_name FROM products p JOIN product_variants pv ON p.product_id = pv.product_id WHERE pv.variant_id = ?");
                $stmt->execute([$item['variant_id']]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $itemData = [
                    'order_id' => $orderId,
                    'variant_id' => $item['variant_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $product['list_price'],
                    'product_name_snapshot' => $product['product_name']
                ];
                
                $this->orderItemModel->create($itemData);
                
                // Update stock
                $this->orderItemModel->updateStock($item['variant_id'], $item['quantity'], true);
            }
            
            $order = $this->orderModel->getByIdWithDetails($orderId);
            
            // Gửi email hóa đơn cho khách hàng
            try {
                $emailService = new \App\Support\EmailService();
                $customerEmail = $order['customer']['email'] ?? null;
                
                if ($customerEmail) {
                    $emailSent = $emailService->sendInvoiceEmail($customerEmail, $order);
                    if ($emailSent) {
                        error_log("Invoice email sent successfully to: {$customerEmail}");
                    } else {
                        error_log("Failed to send invoice email to: {$customerEmail}");
                    }
                }
            } catch (Exception $e) {
                error_log("Error sending invoice email: " . $e->getMessage());
                // Không throw exception để không ảnh hưởng đến việc tạo đơn hàng
            }
            
            return $res->json(ResponseHelper::success($order, 'Order created successfully'));
            
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to create order: ' . $e->getMessage()));
        }
    }
    
    /**
     * PUT /api/orders/{id} - Cập nhật đơn hàng
     */
    public function update(Request $req, Response $res)
    {
        try {
            $id = (int) $req->getAttribute('id');
            $data = $req->body();
            
            // Check if order can be edited
            if (!$this->orderModel->canEdit($id)) {
                return $res->json(ResponseHelper::forbidden('Order cannot be edited in current status'));
            }
            
            // Validate data
            $validator = new Validator($data);
            $validator->optional(['note', 'internal_note', 'estimated_delivery_at']);
            
            if (!$validator->validate()) {
                return $res->json(ResponseHelper::validationError($validator->getErrors()));
            }
            
            $result = $this->orderModel->update($id, $data);
            if (!$result) {
                return $res->json(ResponseHelper::serverError('Failed to update order'));
            }
            
            $order = $this->orderModel->getByIdWithDetails($id);
            return $res->json(ResponseHelper::success($order, 'Order updated successfully'));
            
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to update order: ' . $e->getMessage()));
        }
    }
    
    /**
     * PUT /api/orders/{id}/status - Cập nhật trạng thái đơn hàng
     */
    public function updateStatus(Request $req, Response $res)
    {
        try {
            // Debug logging
            error_log("OrderController::updateStatus called");
            error_log("Request method: " . $req->method());
            error_log("Request path: " . $req->path());
            error_log("Request headers: " . json_encode($_SERVER));
            
            $id = (int) $req->getAttribute('id');
            error_log("Order ID: " . $id);
            
            $data = $req->json();
            error_log("Request data: " . json_encode($data));
            
            // Validate required fields
            $rules = [
                'status' => 'required|in:pending,processing,shipping,completed,cancelled,returned'
            ];
            
            $validator = new Validator($data, $rules);
            
            if (!$validator->validate()) {
                error_log("Validation failed: " . json_encode($validator->getErrors()));
                return $res->json(ResponseHelper::validationError($validator->getErrors()));
            }
            
            $user = $req->getAttribute('user');
            $changedBy = $user['user_id'] ?? 1; // Default to admin user
            error_log("Changed by user ID: " . $changedBy);
            
            $reason = $data['reason'] ?? '';
            error_log("Reason: " . $reason);
            
            $result = $this->orderModel->updateStatus($id, $data['status'], $changedBy, $reason);
            if (!$result) {
                error_log("Failed to update order status in database");
                return $res->json(ResponseHelper::serverError('Failed to update order status'));
            }
            
            error_log("Order status updated successfully");
            $order = $this->orderModel->getByIdWithDetails($id);
            return $res->json(ResponseHelper::success($order, 'Order status updated successfully'));
            
        } catch (Exception $e) {
            error_log("Exception in updateStatus: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return $res->json(ResponseHelper::serverError('Failed to update order status: ' . $e->getMessage()));
        }
    }
    
    /**
     * POST /api/orders/{id}/send-invoice - Gửi lại hóa đơn qua email
     */
    public function sendInvoice(Request $req, Response $res)
    {
        try {
            $id = (int) $req->getAttribute('id');
            
            $order = $this->orderModel->getByIdWithDetails($id);
            if (!$order) {
                return $res->json(ResponseHelper::notFound('Order not found'));
            }
            
            $customerEmail = $order['email'] ?? null;
            if (!$customerEmail) {
                return $res->json(ResponseHelper::error('Customer email not found'));
            }
            
            // Gửi email hóa đơn
            $emailService = new \App\Support\EmailService();
            $emailSent = $emailService->sendInvoiceEmail($customerEmail, $order);
            
            if ($emailSent) {
                return $res->json(ResponseHelper::success(null, 'Invoice sent successfully to ' . $customerEmail));
            } else {
                return $res->json(ResponseHelper::serverError('Failed to send invoice email'));
            }
            
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to send invoice: ' . $e->getMessage()));
        }
    }

    /**
     * POST /api/orders/{id}/assign-shipper - Gán shipper cho đơn hàng
     */
    public function assignShipper(Request $req, Response $res)
    {
        try {
            $id = (int) $req->getAttribute('id');
            $data = $req->json();
            
            // Validate required fields
            $rules = [
                'shipper_id' => 'required|integer'
            ];
            $validator = new Validator($data, $rules);
            
            if (!$validator->validate()) {
                return $res->json(ResponseHelper::validationError($validator->getErrors()));
            }
            
            $pdo = $this->container->database()->getConnection();
            $user = $req->getAttribute('user');
            $assignedBy = $user['user_id'] ?? 1;
            
            // 1. Kiểm tra đơn hàng tồn tại
            $order = $this->orderModel->find($id);
            if (!$order) {
                return $res->json(ResponseHelper::notFound('Order not found'));
            }
            
            // 2. Kiểm tra status đơn hàng - chỉ cho phép gán khi pending hoặc processing
            $allowedStatuses = ['pending', 'processing'];
            if (!in_array($order['status'], $allowedStatuses)) {
                return $res->json(ResponseHelper::forbidden(
                    "Cannot assign shipper. Order status must be 'pending' or 'processing'. Current status: {$order['status']}"
                ));
            }
            
            // 3. Kiểm tra đơn đã được gán chưa
            $trackingSql = "SELECT shipper_id FROM shipping_tracking WHERE order_id = ?";
            $trackingStmt = $pdo->prepare($trackingSql);
            $trackingStmt->execute([$id]);
            $existingAssignment = $trackingStmt->fetch(PDO::FETCH_ASSOC);
            $shouldResetShippingStatus = false;
            
            if ($existingAssignment) {
                $currentShipperId = (int)$existingAssignment['shipper_id'];
                
                // Nếu đang gán lại cho cùng shipper → OK
                if ($currentShipperId === (int)$data['shipper_id']) {
                    $updatedOrder = $this->orderModel->getByIdWithDetails($id);
                    return $res->json(ResponseHelper::success($updatedOrder, 'Shipper already assigned to this order'));
                }
                
                // Nếu đơn đang ở status 'shipping' → không cho phép thay đổi shipper
                if ($order['status'] === 'shipping') {
                    return $res->json(ResponseHelper::forbidden(
                        'Cannot reassign shipper. Order is already being delivered. Please cancel the order first if you need to change shipper.'
                    ));
                }
                
                // Nếu đơn ở 'processing' → cho phép thay đổi shipper
                $oldShipperId = $currentShipperId;
                $shouldResetShippingStatus = true;
            }
            
            // 4. Kiểm tra shipper tồn tại và available
            $shipperSql = "SELECT user_id, is_available, status FROM shippers WHERE user_id = ?";
            $shipperStmt = $pdo->prepare($shipperSql);
            $shipperStmt->execute([$data['shipper_id']]);
            $shipper = $shipperStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$shipper) {
                return $res->json(ResponseHelper::notFound('Shipper not found'));
            }
            
            if (!$shipper['is_available'] || $shipper['status'] !== 'active') {
                return $res->json(ResponseHelper::forbidden('Shipper is not available or inactive'));
            }
            
            if (!$existingAssignment) {
                $shouldResetShippingStatus = true;
            }

            // 5. Gán shipper
            $oldShipperId = $existingAssignment['shipper_id'] ?? null;
            $result = $this->orderModel->assignShipper($id, $data['shipper_id'], $assignedBy);
            
            if (!$result) {
                return $res->json(ResponseHelper::serverError('Failed to assign shipper'));
            }
            
            // 6. Cập nhật status đơn hàng thành 'processing' nếu đang là 'pending'
            if ($order['status'] === 'pending') {
                $this->orderModel->updateStatus($id, 'processing', $assignedBy, 'Order assigned to shipper');
            }
            
            // 7. Log thay đổi shipper nếu có
            if ($existingAssignment && isset($oldShipperId) && $oldShipperId !== (int)$data['shipper_id']) {
                $this->orderModel->logActivity(
                    $id, 
                    'reassign_shipper', 
                    $assignedBy, 
                    ['shipper_id' => $oldShipperId], 
                    ['shipper_id' => $data['shipper_id']]
                );
            }

            if ($shouldResetShippingStatus) {
                try {
                    $this->orderModel->forceShippingStatus(
                        $id,
                        'new_request',
                        (int)$data['shipper_id'],
                        ['note' => 'Order assigned to shipper']
                    );
                } catch (Exception $e) {
                    error_log('[Assign Shipper] Failed to reset shipping status: ' . $e->getMessage());
                }
            }
            
            // 8. Lấy thông tin đơn hàng đã cập nhật
            $updatedOrder = $this->orderModel->getByIdWithDetails($id);
            
            // 9. Send push notification to shipper
            try {
                $pdo = $this->container->database()->getConnection();
                $notificationService = new NotificationService($pdo);
                
                $shipperId = (int)$data['shipper_id'];
                $orderId = $id;
                $title = "Đơn hàng mới được gán";
                $message = "Bạn có đơn hàng #{$orderId} mới cần xử lý";
                $dataPayload = [
                    'order_id' => $orderId,
                    'type' => 'new_order_assigned'
                ];
                
                $notificationService->sendPushNotification(
                    $shipperId,
                    $title,
                    $message,
                    $dataPayload,
                    'new_order_assigned'
                );
                
                error_log("[OrderController] Push notification sent to shipper: {$shipperId} for order: {$orderId}");
            } catch (Exception $e) {
                // Log error but don't fail the assignment
                error_log('[OrderController] Error sending push notification: ' . $e->getMessage());
            }
            
            $message = $existingAssignment && isset($oldShipperId) && $oldShipperId !== (int)$data['shipper_id']
                ? 'Shipper reassigned successfully' 
                : 'Shipper assigned successfully';
                
            return $res->json(ResponseHelper::success($updatedOrder, $message));
            
        } catch (Exception $e) {
            error_log('[Assign Shipper] Error: ' . $e->getMessage());
            return $res->json(ResponseHelper::serverError('Failed to assign shipper: ' . $e->getMessage()));
        }
    }
    
    /**
     * GET /api/orders/available-shippers - Lấy danh sách shipper có sẵn
     */
    public function getAvailableShippers(Request $req, Response $res)
    {
        try {
            $shippers = $this->orderModel->getAvailableShippers();
            return $res->json(ResponseHelper::success($shippers));
            
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to fetch available shippers: ' . $e->getMessage()));
        }
    }
    
    /**
     * GET /api/v1/shipper/orders - Lấy danh sách đơn hàng của shipper
     */
    public function shipperOrders(Request $req, Response $res)
    {
        try {
            // Lấy shipper_id từ JWT token
            $user = $req->getAttribute('user');
            $shipperId = (int) ($user['user_id'] ?? 0);
            
            if (!$shipperId) {
                return $res->json(ResponseHelper::unauthorized('Shipper ID not found in token'));
            }
            
            // Kiểm tra user có phải shipper không
            $pdo = $this->container->database()->getConnection();
            $stmt = $pdo->prepare("SELECT user_id FROM shippers WHERE user_id = ?");
            $stmt->execute([$shipperId]);
            $shipper = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$shipper) {
                return $res->json(ResponseHelper::forbidden('User is not a shipper'));
            }
            
            // Lấy pagination params
            $page = (int)($req->query('page') ?? 1);
            $limit = (int)($req->query('limit') ?? 20);
            $offset = ($page - 1) * $limit;
            
            // Lấy filter params
            $filters = [];
            if ($req->query('status')) {
                $filters['status'] = $req->query('status');
            }
            if ($req->query('shipping_status')) {
                $filters['shipping_status'] = $req->query('shipping_status');
            }
            
            // Lấy đơn hàng
            $orders = $this->orderModel->getOrdersByShipper($shipperId, $filters, $limit, $offset);
            $total = $this->orderModel->getOrdersByShipperCount($shipperId, $filters);
            
            return $res->json(ResponseHelper::paginated($orders, $total, $limit, $page));
            
        } catch (Exception $e) {
            error_log('[Shipper Orders] Error: ' . $e->getMessage());
            return $res->json(ResponseHelper::serverError('Failed to fetch shipper orders: ' . $e->getMessage()));
        }
    }

    public function shipperOrderDetail(Request $req, Response $res)
    {
        try {
            $orderId = (int)$req->getAttribute('id');
            $user = $req->getAttribute('user');
            $shipperId = (int)($user['user_id'] ?? 0);

            if (!$shipperId) {
                return $res->json(ResponseHelper::unauthorized('Shipper ID not found in token'));
            }

            $this->getShipperOrderContext($orderId, $shipperId);
            $order = $this->orderModel->getByIdWithDetails($orderId);

            if (!$order) {
                return $res->json(ResponseHelper::notFound('Order not found'));
            }

            return $res->json(ResponseHelper::success($order));
        } catch (Exception $e) {
            return $this->shippingErrorResponse($res, $e);
        }
    }
    
    /**
     * POST /api/v1/shipper/orders/{id}/accept - Shipper nhận đơn hàng
     */
    public function acceptOrder(Request $req, Response $res)
    {
        try {
            $orderId = (int) $req->getAttribute('id');
            $user = $req->getAttribute('user');
            $shipperId = (int) ($user['user_id'] ?? 0);
            
            if (!$shipperId) {
                return $res->json(ResponseHelper::unauthorized('Shipper ID not found in token'));
            }
            
            $payload = $this->parseShippingPayload($req);
            try {
                $updatedOrder = $this->performShippingTransition($orderId, $shipperId, 'accepted', $payload);
                return $res->json(ResponseHelper::success($updatedOrder, 'Order accepted successfully'));
            } catch (Exception $workflowException) {
                return $this->shippingErrorResponse($res, $workflowException);
            }
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to accept order: ' . $e->getMessage()));
        }
    }
    
    /**
     * POST /api/v1/shipper/orders/{id}/pickup - Shipper lấy hàng
     */
    public function pickupOrder(Request $req, Response $res)
    {
        try {
            $orderId = (int) $req->getAttribute('id');
            $user = $req->getAttribute('user');
            $shipperId = (int) ($user['user_id'] ?? 0);
            
            if (!$shipperId) {
                return $res->json(ResponseHelper::unauthorized('Shipper ID not found in token'));
            }
            
            $payload = $this->parseShippingPayload($req);
            try {
                $updatedOrder = $this->performShippingTransition($orderId, $shipperId, 'picked_up', $payload, [
                    'require_photo' => true,
                    'require_location' => true,
                    'require_proof' => true,
                    'proof_type' => 'pickup_photo'
                ]);
                return $res->json(ResponseHelper::success($updatedOrder, 'Order picked up successfully'));
            } catch (Exception $workflowException) {
                return $this->shippingErrorResponse($res, $workflowException);
            }
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to pickup order: ' . $e->getMessage()));
        }
    }
    
    /**
     * POST /api/v1/shipper/orders/{id}/deliver - Shipper giao hàng
     */
    public function deliverOrder(Request $req, Response $res)
    {
        try {
            $orderId = (int) $req->getAttribute('id');
            $user = $req->getAttribute('user');
            $shipperId = (int) ($user['user_id'] ?? 0);
            $payload = $this->parseShippingPayload($req);
            
            if (!$shipperId) {
                return $res->json(ResponseHelper::unauthorized('Shipper ID not found in token'));
            }
            
            try {
                $updatedOrder = $this->performShippingTransition($orderId, $shipperId, 'delivered', $payload, [
                    'require_photo' => true,
                    'require_location' => true,
                    'require_proof' => true,
                    'proof_type' => 'delivery_photo'
                ]);
                return $res->json(ResponseHelper::success($updatedOrder, 'Order delivered successfully'));
            } catch (Exception $workflowException) {
                return $this->shippingErrorResponse($res, $workflowException);
            }
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to deliver order: ' . $e->getMessage()));
        }
    }

    public function startDelivery(Request $req, Response $res)
    {
        try {
            $orderId = (int) $req->getAttribute('id');
            $user = $req->getAttribute('user');
            $shipperId = (int) ($user['user_id'] ?? 0);

            if (!$shipperId) {
                return $res->json(ResponseHelper::unauthorized('Shipper ID not found in token'));
            }

            $payload = $this->parseShippingPayload($req);

            try {
                $updatedOrder = $this->performShippingTransition($orderId, $shipperId, 'delivering', $payload);
                return $res->json(ResponseHelper::success($updatedOrder, 'Delivery started'));
            } catch (Exception $workflowException) {
                return $this->shippingErrorResponse($res, $workflowException);
            }
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to start delivery: ' . $e->getMessage()));
        }
    }

    public function arriveOrder(Request $req, Response $res)
    {
        try {
            $orderId = (int) $req->getAttribute('id');
            $user = $req->getAttribute('user');
            $shipperId = (int) ($user['user_id'] ?? 0);

            if (!$shipperId) {
                return $res->json(ResponseHelper::unauthorized('Shipper ID not found in token'));
            }

            $payload = $this->parseShippingPayload($req);

            try {
                $updatedOrder = $this->performShippingTransition($orderId, $shipperId, 'arrived', $payload, [
                    'require_location' => true
                ]);
                return $res->json(ResponseHelper::success($updatedOrder, 'Arrival confirmed'));
            } catch (Exception $workflowException) {
                return $this->shippingErrorResponse($res, $workflowException);
            }
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to confirm arrival: ' . $e->getMessage()));
        }
    }

    public function completeOrder(Request $req, Response $res)
    {
        try {
            $orderId = (int) $req->getAttribute('id');
            $user = $req->getAttribute('user');
            $shipperId = (int) ($user['user_id'] ?? 0);

            if (!$shipperId) {
                return $res->json(ResponseHelper::unauthorized('Shipper ID not found in token'));
            }

            $payload = $this->parseShippingPayload($req);

            try {
                $updatedOrder = $this->performShippingTransition($orderId, $shipperId, 'completed', $payload, [
                    'update_order_status' => 'completed'
                ]);
                return $res->json(ResponseHelper::success($updatedOrder, 'Order completed successfully'));
            } catch (Exception $workflowException) {
                return $this->shippingErrorResponse($res, $workflowException);
            }
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to complete order: ' . $e->getMessage()));
        }
    }

    public function rejectOrder(Request $req, Response $res)
    {
        try {
            $orderId = (int) $req->getAttribute('id');
            $user = $req->getAttribute('user');
            $shipperId = (int) ($user['user_id'] ?? 0);

            if (!$shipperId) {
                return $res->json(ResponseHelper::unauthorized('Shipper ID not found in token'));
            }

            $payload = $this->parseShippingPayload($req);
            if (empty($payload['note'])) {
                return $res->json(ResponseHelper::validationError(['note' => 'Reject reason is required']));
            }

            try {
                $updatedOrder = $this->performShippingTransition($orderId, $shipperId, 'rejected', $payload);
                return $res->json(ResponseHelper::success($updatedOrder, 'Order rejected'));
            } catch (Exception $workflowException) {
                return $this->shippingErrorResponse($res, $workflowException);
            }
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to reject order: ' . $e->getMessage()));
        }
    }
    
    /**
     * DELETE /api/orders/{id} - Hủy đơn hàng
     */
    public function destroy(Request $req, Response $res)
    {
        try {
            $id = (int) $req->getAttribute('id');
            
            // Check if order can be cancelled
            if (!$this->orderModel->canCancel($id)) {
                return $res->json(ResponseHelper::forbidden('Order cannot be cancelled in current status'));
            }
            
            $user = $req->getAttribute('user');
            $changedBy = $user['user_id'] ?? 1; // Default to admin user
            $reason = $req->body('reason') ?? 'Order cancelled by admin';
            
            $result = $this->orderModel->updateStatus($id, 'cancelled', $changedBy, $reason);
            if (!$result) {
                return $res->json(ResponseHelper::serverError('Failed to cancel order'));
            }
            
            // Restore stock for cancelled order
            $items = $this->orderItemModel->getByOrderId($id);
            foreach ($items as $item) {
                $this->orderItemModel->updateStock($item['variant_id'], $item['quantity'], false);
            }
            
            return $res->json(ResponseHelper::success(null, 'Order cancelled successfully'));
            
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to cancel order: ' . $e->getMessage()));
        }
    }
    
    /**
     * GET /api/orders/{id}/invoice - Xuất hóa đơn
     */
    public function generateInvoice(Request $req, Response $res)
    {
        try {
            $id = (int) $req->getAttribute('id');
            
            $order = $this->orderModel->getByIdWithDetails($id);
            if (!$order) {
                return $res->json(ResponseHelper::notFound('Order not found'));
            }
            
            // Generate invoice data
            $invoiceData = [
                'invoice_number' => $order['invoice_number'],
                'order_date' => $order['created_at'],
                'customer' => [
                    'name' => $order['first_name'] . ' ' . $order['last_name'],
                    'email' => $order['email'],
                    'phone' => $order['phone']
                ],
                'shipping_address' => json_decode($order['shipping_address_snapshot'], true),
                'items' => $order['items'],
                'subtotal' => array_sum(array_map(fn($item) => $item['quantity'] * $item['unit_price'], $order['items'])),
                'shipping_fee' => $order['shipping_fee'],
                'discount_amount' => $order['discount_amount_applied'],
                'total_amount' => $order['total_amount']
            ];
            
            return $res->json(ResponseHelper::success($invoiceData, 'Invoice generated successfully'));
            
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to generate invoice: ' . $e->getMessage()));
        }
    }
    
    /**
     * GET /api/orders/export - Xuất danh sách đơn hàng
     */
    public function export(Request $req, Response $res)
    {
        try {
            $filters = [];
            if ($req->query('status')) {
                $filters['status'] = $req->query('status');
            }
            if ($req->query('date_from')) {
                $filters['date_from'] = $req->query('date_from');
            }
            if ($req->query('date_to')) {
                $filters['date_to'] = $req->query('date_to');
            }
            
            $orders = $this->orderModel->getAll($filters, 1000, 0); // Get up to 1000 orders
            
            $exportData = [];
            foreach ($orders as $order) {
                $exportData[] = [
                    'Invoice Number' => $order['invoice_number'],
                    'Customer' => $order['first_name'] . ' ' . $order['last_name'],
                    'Email' => $order['email'],
                    'Phone' => $order['phone'],
                    'Status' => $order['status'],
                    'Total Amount' => number_format($order['total_amount'], 0, ',', '.') . ' VND',
                    'Shipping Fee' => number_format($order['shipping_fee'], 0, ',', '.') . ' VND',
                    'Discount' => number_format($order['discount_amount_applied'], 0, ',', '.') . ' VND',
                    'Created Date' => $order['created_at'],
                    'Estimated Delivery' => $order['estimated_delivery_at']
                ];
            }
            
            return $res->json(ResponseHelper::success($exportData, 'Orders exported successfully'));
            
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to export orders: ' . $e->getMessage()));
        }
    }

    private function parseShippingPayload(Request $req): array
    {
        $data = $req->json();
        if (!is_array($data)) {
            $data = [];
        }

        $body = $req->body();
        if (is_array($body)) {
            $data = array_merge($data, $body);
        }

        return $data;
    }

    /**
     * @return array{order: array, tracking: array}
     * @throws Exception
     */
    private function getShipperOrderContext(int $orderId, int $shipperId): array
    {
        $order = $this->orderModel->find($orderId);
        if (!$order) {
            throw new Exception('Order not found', 404);
        }

        $pdo = $this->container->database()->getConnection();
        $stmt = $pdo->prepare("SELECT shipper_id FROM shipping_tracking WHERE order_id = ?");
        $stmt->execute([$orderId]);
        $tracking = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$tracking || (int)$tracking['shipper_id'] !== $shipperId) {
            throw new Exception('Order is not assigned to this shipper', 403);
        }

        return ['order' => $order, 'tracking' => $tracking];
    }

    /**
     * @throws Exception
     */
    private function performShippingTransition(
        int $orderId,
        int $shipperId,
        string $targetStatus,
        array $payload,
        array $options = []
    ): array {
        $this->getShipperOrderContext($orderId, $shipperId);

        $photoUrl = $payload['photo_url']
            ?? $payload['confirmation_photo']
            ?? $payload['photo']
            ?? null;

        if (($options['require_photo'] ?? false) && !$photoUrl) {
            throw new Exception('Photo proof is required for this action', 422);
        }

        $latValue = $payload['latitude'] ?? $payload['lat'] ?? null;
        $lngValue = $payload['longitude'] ?? $payload['lng'] ?? null;

        if (($options['require_location'] ?? false) && ($latValue === null || $lngValue === null)) {
            throw new Exception('GPS location is required for this action', 422);
        }

        $latitude = $latValue !== null ? (float)$latValue : null;
        $longitude = $lngValue !== null ? (float)$lngValue : null;

        $metadata = $payload['metadata'] ?? [];
        if (!is_array($metadata)) {
            $metadata = ['raw' => $metadata];
        }

        $eventData = [
            'note' => $payload['note'] ?? null,
            'photo_url' => $photoUrl,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'metadata' => $metadata,
        ];

        $this->orderModel->updateShippingStatus(
            $orderId,
            $targetStatus,
            $shipperId,
            $eventData,
            $options['force'] ?? false
        );

        if (($options['require_proof'] ?? false) && $photoUrl) {
            $this->orderModel->addDeliveryProof(
                $orderId,
                $shipperId,
                $targetStatus,
                $photoUrl,
                $options['proof_type'] ?? 'delivery_photo',
                $latitude,
                $longitude,
                $metadata
            );
        }

        if (!empty($options['update_order_status'])) {
            $this->orderModel->updateStatus(
                $orderId,
                $options['update_order_status'],
                $shipperId,
                'Synced from shipping workflow'
            );
        }

        return $this->orderModel->getByIdWithDetails($orderId);
    }

    private function shippingErrorResponse(Response $res, Exception $e)
    {
        $code = $e->getCode();
        if ($code === 404) {
            return $res->json(ResponseHelper::notFound($e->getMessage()));
        }
        if ($code === 403) {
            return $res->json(ResponseHelper::forbidden($e->getMessage()));
        }
        if ($code === 422) {
            return $res->json(ResponseHelper::validationError(['shipping_status' => $e->getMessage()]));
        }

        error_log('[Shipping Workflow] ' . $e->getMessage());
        return $res->json(ResponseHelper::serverError('Shipping workflow failed: ' . $e->getMessage()));
    }
}
