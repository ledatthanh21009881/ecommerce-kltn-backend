<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Controller, Request, Response, Container, Database};
use App\Domain\Orders\{Order, OrderItem};
use App\Domain\Payments\Payment;
use App\Services\Payment\{MockQRPaymentService, VNPayPaymentService, VietQRPaymentService, CODPaymentService, PayOSPaymentService};
use App\Support\GeocodingService;
use App\Support\ResponseHelper;
use App\Support\ShipperCapacity;
use App\Core\Validator;
use App\Services\AdminNotificationService;
use App\Services\NotificationService;
use App\Services\ShipperDeliveryPhotoService;
use Exception;
use PDO;

class OrderController extends Controller 
{
    private const COUNTER_WALKIN_ACCOUNT = '__counter_walkin__';
    private const COUNTER_WALKIN_EMAIL = 'counter.walkin@internal.local';

    private Order $orderModel;
    private OrderItem $orderItemModel;
    private Payment $paymentModel;
    private array $shippingTransitions;
    private array $config;
    
    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->orderModel = new Order($container->get('database'));
        $this->orderItemModel = new OrderItem($container->get('database'));
        $this->paymentModel = new Payment($container->get('database'));
        $this->shippingTransitions = Order::SHIPPING_TRANSITIONS;
        // Get config from container, fallback to loading from app.php if not found
        try {
            $config = $container->get('config');
            $this->config = is_array($config) ? $config : [];
        } catch (\Exception $e) {
            // Fallback: load config from app.php
            $configPath = __DIR__ . '/../config/app.php';
            $this->config = file_exists($configPath) ? require $configPath : [];
        }
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
            $data = $req->json(); // Use json() instead of body() for JSON requests

            $isCounterOrder = !empty($data['counter_order']);
            if ($isCounterOrder && !$this->canCreateCounterOrder($req)) {
                return $res->json(ResponseHelper::validationError([
                    'counter_order' => 'Chỉ nhân viên cửa hàng mới được tạo đơn tại quầy không chọn khách.',
                ]), 403);
            }

            if ($isCounterOrder) {
                $customerId = isset($data['customer_id']) ? (int) $data['customer_id'] : 0;
                $addressIdHint = isset($data['address_id']) ? (int) $data['address_id'] : 0;
                if ($customerId <= 0 || $addressIdHint <= 0) {
                    $defaults = $this->resolveCounterOrderDefaults();
                    if ($customerId <= 0) {
                        $data['customer_id'] = $defaults['customer_id'];
                    }
                    if ($addressIdHint <= 0) {
                        $data['address_id'] = $defaults['address_id'];
                    }
                }
            }
            
            // Validate required fields
            $validator = Validator::make($data, [
                'customer_id' => 'required|integer',
                'shipping_method_id' => 'required|integer',
                'items' => 'required|array'
            ]);
            
            // Handle address_id and address data
            $addressId = isset($data['address_id']) ? (int)$data['address_id'] : null;
            $addressJustCreated = false;
            
            error_log("=== OrderController: Address Processing Start ===");
            error_log("OrderController: Initial address_id = " . var_export($addressId, true));
            error_log("OrderController: Has address data = " . (!empty($data['address']) ? 'yes' : 'no'));
            error_log("OrderController: customer_id = " . ($data['customer_id'] ?? 'null'));
            
            // If address data is provided, always create a new address (ignore address_id from request)
            if (!empty($data['address'])) {
                error_log("OrderController: Address data provided, will create new address");
                $addressData = $data['address'];
                $addressLine = trim((string)($addressData['address_line'] ?? ''));
                $ward = trim((string)($addressData['ward'] ?? ''));
                $district = trim((string)($addressData['district'] ?? ''));
                $province = trim((string)($addressData['province'] ?? ''));
                $coordinates = GeocodingService::resolveFromParts($addressLine, $ward, $district, $province);
                if (!$coordinates) {
                    return $res->json(ResponseHelper::validationError([
                        'address' => 'Không thể xác định tọa độ cho địa chỉ giao hàng mới. Vui lòng kiểm tra lại địa chỉ.',
                    ]), 422);
                }
                error_log("OrderController: Attempting to create address for customer_id = " . $data['customer_id']);
                error_log("OrderController: Address data = " . json_encode($addressData));
                
                $db = $this->container->database()->getConnection();
                error_log("OrderController: Database connection obtained");
                
                // Create address with is_default = 0 (not default, just for this order)
                $sql = "INSERT INTO addresses (user_id, receiver_name, phone, address_line, ward, district, province, is_default, lat, lng) VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?)";
                error_log("OrderController: SQL = " . $sql);
                
                try {
                    $stmt = $db->prepare($sql);
                    $params = [
                        $data['customer_id'],
                        $addressData['receiver_name'] ?? '',
                        $addressData['phone'] ?? '',
                        $addressLine,
                        $ward,
                        $district,
                        $province,
                        $coordinates['lat'],
                        $coordinates['lng'],
                    ];
                    error_log("OrderController: INSERT params = " . json_encode($params));
                    
                    $result = $stmt->execute($params);
                    
                    error_log("OrderController: INSERT execute result = " . var_export($result, true));
                    if (!$result) {
                        $errorInfo = $stmt->errorInfo();
                        error_log("OrderController: PDO error = " . json_encode($errorInfo));
                        throw new \Exception("INSERT failed: " . json_encode($errorInfo));
                    }
                    
                    // Always get the new address_id after successful INSERT
                    $newAddressId = $db->lastInsertId();
                    error_log("OrderController: lastInsertId() = " . var_export($newAddressId, true));
                    error_log("OrderController: lastInsertId() type = " . gettype($newAddressId));
                    
                    if ($newAddressId && $newAddressId > 0) {
                        $addressId = (int)$newAddressId;
                        $addressJustCreated = true;
                        error_log("OrderController: ✓ Created new address with ID = " . $addressId);
                    } else {
                        // If lastInsertId failed, try to find the address we just created
                        error_log("OrderController: lastInsertId returned 0 or false, trying to find address");
                        $findSql = "SELECT address_id FROM addresses WHERE user_id = ? ORDER BY address_id DESC LIMIT 1";
                        $findStmt = $db->prepare($findSql);
                        $findStmt->execute([$data['customer_id']]);
                        $found = $findStmt->fetch(PDO::FETCH_ASSOC);
                        error_log("OrderController: Latest address for user = " . var_export($found, true));
                        
                        if ($found && isset($found['address_id'])) {
                            $addressId = (int)$found['address_id'];
                            $addressJustCreated = true;
                            error_log("OrderController: ✓ Using latest address ID = " . $addressId);
                        } else {
                            throw new \Exception("Failed to get address_id after INSERT");
                        }
                    }
                } catch (\Exception $e) {
                    error_log("OrderController: ✗ Error creating address: " . $e->getMessage());
                    error_log("OrderController: Error stack trace: " . $e->getTraceAsString());
                    // Return detailed error with debug info
                    return $res->json([
                        'success' => false,
                        'message' => 'Failed to create address: ' . $e->getMessage(),
                        'status_code' => 500,
                        'errors' => ['address' => 'Failed to create address'],
                        'debug' => [
                            'exception_message' => $e->getMessage(),
                            'customer_id' => $data['customer_id'] ?? null,
                            'address_data' => $addressData ?? null,
                            'initial_address_id' => isset($data['address_id']) ? (int)$data['address_id'] : null
                        ]
                    ], 500);
                }
            } else if ($addressId && $addressId > 1) {
                // If no address data but address_id > 1 provided, verify it exists
                error_log("OrderController: No address data, verifying existing address_id = " . $addressId);
                $stmt = $this->container->database()->getConnection()->prepare("SELECT address_id FROM addresses WHERE address_id = ? AND user_id = ?");
                $stmt->execute([$addressId, $data['customer_id']]);
                $existing = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$existing) {
                    error_log("OrderController: ✗ address_id " . $addressId . " not found or doesn't belong to customer");
                    $addressId = null;
                } else {
                    error_log("OrderController: ✓ Using existing address_id = " . $addressId);
                }
            }
            // If still no address, use default address for customer
            if ((!$addressId || $addressId === 0) && empty($data['address'])) {
                $stmt = $this->container->database()->getConnection()->prepare("SELECT address_id FROM addresses WHERE user_id = ? AND is_default = 1 LIMIT 1");
                $stmt->execute([$data['customer_id']]);
                $defaultRow = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($defaultRow && !empty($defaultRow['address_id'])) {
                    $addressId = (int) $defaultRow['address_id'];
                    error_log("OrderController: ✓ Using default address_id = " . $addressId);
                }
            }
            
            error_log("OrderController: Final address_id = " . var_export($addressId, true));
            error_log("OrderController: addressJustCreated = " . var_export($addressJustCreated, true));
            
            // Now validate address_id exists
            if (!$addressId || $addressId === 0) {
                error_log("OrderController: ✗✗✗ Validation failed - address_id is invalid");
                error_log("OrderController: address_id value = " . var_export($addressId, true));
                error_log("OrderController: address_id type = " . gettype($addressId));
                error_log("OrderController: address_id === 0 = " . var_export($addressId === 0, true));
                error_log("OrderController: !addressId = " . var_export(!$addressId, true));
                
                // Return detailed debug info
                $debugInfo = [
                    'initial_address_id' => isset($data['address_id']) ? (int)$data['address_id'] : null,
                    'final_address_id' => $addressId,
                    'final_address_id_type' => gettype($addressId),
                    'has_address_data' => !empty($data['address']),
                    'address_just_created' => $addressJustCreated,
                    'customer_id' => $data['customer_id'] ?? null,
                    'address_data_provided' => !empty($data['address']) ? 'yes' : 'no'
                ];
                error_log("OrderController: Debug info = " . json_encode($debugInfo));
                
                return $res->json([
                    'success' => false,
                    'message' => 'Validation Error',
                    'status_code' => 422,
                    'errors' => ['address_id' => 'Vui lòng chọn địa chỉ giao hàng hoặc thêm địa chỉ mặc định trong Tài khoản'],
                    'debug' => $debugInfo
                ], 422);
            }
            
            error_log("OrderController: ✓✓✓ Address validation passed, address_id = " . $addressId);
            
            // Verify address exists (skip if we just created it)
            if (!$addressJustCreated) {
                $stmt = $this->container->database()->getConnection()->prepare("SELECT address_id FROM addresses WHERE address_id = ? AND user_id = ?");
                $stmt->execute([$addressId, $data['customer_id']]);
                if (!$stmt->fetch()) {
                    return $res->json(ResponseHelper::validationError(['address_id' => 'Address not found or does not belong to customer']));
                }
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
                $stmt = $this->container->database()->getConnection()->prepare("SELECT list_price FROM products p JOIN product_variants pv ON p.product_id = pv.product_id WHERE pv.variant_id = ?");
                $stmt->execute([$item['variant_id']]);
                $price = $stmt->fetchColumn();
                $subtotal += $price * $item['quantity'];
            }
            
            // Get shipping fee
            $stmt = $this->container->database()->getConnection()->prepare("SELECT fee FROM shipping_methods WHERE shipping_method_id = ?");
            $stmt->execute([$data['shipping_method_id']]);
            $shippingFee = $stmt->fetchColumn() ?? 0;
            
            // Calculate discount if voucher is applied
            $discountAmount = 0;
            if (!empty($data['voucher_id'])) {
                $stmt = $this->container->database()->getConnection()->prepare("SELECT discount_amount, discount_type FROM vouchers WHERE voucher_id = ? AND status = 'active'");
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
            
            // Create order (use newly created address_id if address was created)
            $orderData = [
                'customer_id' => $data['customer_id'],
                'address_id' => $addressId, // Use the address_id (newly created or existing)
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
                $stmt = $this->container->database()->getConnection()->prepare("SELECT p.list_price, p.product_name FROM products p JOIN product_variants pv ON p.product_id = pv.product_id WHERE pv.variant_id = ?");
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
            
            // Create payment if payment_method is provided
            $paymentData = null;
            $paymentMethod = !empty($data['payment_method']) ? strtolower((string)$data['payment_method']) : null;
            if (!empty($data['payment_method'])) {
                try {
                    $database = $this->container->database();
                    $service = $this->getPaymentService($data['payment_method'], $database);
                    
                    $paymentData = $service->createPayment([
                        'order_id' => $orderId,
                        'amount' => $totalAmount,
                    ]);
                    
                    error_log("OrderController: Payment created - payment_id: " . ($paymentData['payment_id'] ?? 'N/A') . ", payment_url: " . ($paymentData['payment_url'] ?? 'NULL'));
                    
                    // Add payment_id to order response
                    $order['payment_id'] = $paymentData['payment_id'] ?? null;
                    $order['payment_url'] = $paymentData['payment_url'] ?? null;
                    $order['payment_qr_code'] = $paymentData['qr_code'] ?? null;
                    $order['payment_method'] = $paymentData['method'] ?? $paymentMethod;
                } catch (Exception $e) {
                    error_log("Error creating payment: " . $e->getMessage());
                    error_log("Error stack trace: " . $e->getTraceAsString());
                    // Don't fail order creation if payment creation fails
                }
            }

            // Auto-assign shipper for non-transfer orders.
            // Transfer methods (payos/vnpay/vietqr/mock_qr/...) will be auto-assigned
            // after payment is confirmed in PaymentController callbacks.
            if (!$this->shouldWaitForPaymentConfirmationBeforeAutoAssign($paymentMethod)) {
                try {
                    $this->autoAssignOrderToBestAvailableShipper($orderId, 1, 'Auto-assigned on order creation');
                    $order = $this->orderModel->getByIdWithDetails($orderId);
                } catch (Exception $e) {
                    error_log('[OrderController] Auto-assign on creation failed: ' . $e->getMessage());
                }
            }
            
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

            try {
                $adminNotify = new AdminNotificationService($this->container->database()->getConnection());
                $adminNotify->notifyNewOrder($orderId, (float) $totalAmount);
                if (!empty($paymentData) && ($paymentData['status'] ?? '') === 'pending') {
                    $pm = strtolower((string) ($paymentData['method'] ?? ''));
                    if ($pm !== 'cod') {
                        $methodLabel = (string) ($paymentData['method'] ?? 'online');
                        $adminNotify->notifyPaymentPending($orderId, $methodLabel);
                    }
                }
            } catch (Exception $e) {
                error_log('[OrderController] Admin notification: ' . $e->getMessage());
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
            $data = $req->json(); // Use json() instead of body() for JSON requests
            
            // Check if order can be edited
            if (!$this->orderModel->canEdit($id)) {
                return $res->json(ResponseHelper::forbidden('Order cannot be edited in current status'));
            }
            
            // Validate data (note, internal_note, estimated_delivery_at are optional)
            // No validation needed for optional fields
            
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
            $hasExistingAssignment = is_array($existingAssignment);
            $shouldResetShippingStatus = false;
            $oldShipperId = null;

            if ($hasExistingAssignment) {
                $currentShipperId = (int) ($existingAssignment['shipper_id'] ?? 0);

                // Nếu đang gán lại cho cùng shipper → OK (kể cả đơn processing sau thanh toán)
                if ($currentShipperId === (int) $data['shipper_id']) {
                    $updatedOrder = $this->orderModel->getByIdWithDetails($id);
                    return $res->json(ResponseHelper::success($updatedOrder, 'Shipper already assigned to this order'));
                }

                // Đơn processing/pending: cho phép đổi shipper
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

            $newShipperId = (int) $data['shipper_id'];
            $excludeOrderId = null;
            if ($hasExistingAssignment && isset($currentShipperId) && $currentShipperId === $newShipperId) {
                $excludeOrderId = $id;
            }
            if (!ShipperCapacity::hasCapacity($pdo, $newShipperId, $excludeOrderId)) {
                return $res->json(ResponseHelper::forbidden(
                    ShipperCapacity::forbiddenMessage($pdo, $newShipperId, $excludeOrderId)
                ));
            }
            
            if (!$hasExistingAssignment) {
                $shouldResetShippingStatus = true;
            }

            // 5. Gán shipper (lần đầu hoặc đổi shipper khi pending/processing)
            $result = $this->orderModel->assignShipper($id, $data['shipper_id'], $assignedBy);
            
            if (!$result) {
                return $res->json(ResponseHelper::serverError('Failed to assign shipper'));
            }
            
            // 6. Đồng bộ trạng thái đơn sang 'shipping' khi đã gán shipper
            if (in_array($order['status'], ['pending', 'processing'], true)) {
                $this->orderModel->updateStatus($id, 'shipping', $assignedBy, 'Order assigned to shipper');
            }
            
            // 7. Log thay đổi shipper nếu có
            if ($hasExistingAssignment && $oldShipperId !== null && $oldShipperId !== (int) $data['shipper_id']) {
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

            try {
                (new AdminNotificationService($this->container->database()->getConnection()))->notifyOrderAssigned(
                    $id,
                    (int) $data['shipper_id']
                );
            } catch (Exception $e) {
                error_log('[OrderController] Admin shipper notification: ' . $e->getMessage());
            }
            
            $message = $hasExistingAssignment && $oldShipperId !== null && $oldShipperId !== (int) $data['shipper_id']
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

    /**
     * GET /api/v1/shipper/orders/history/{type} — type: completed | rejected
     * Lịch sử từ order_delivery_events (không phụ thuộc shipping_tracking).
     */
    public function shipperOrderHistory(Request $req, Response $res)
    {
        try {
            $user = $req->getAttribute('user');
            $shipperId = (int) ($user['user_id'] ?? 0);

            if (!$shipperId) {
                return $res->json(ResponseHelper::unauthorized('Shipper ID not found in token'));
            }

            $pdo = $this->container->database()->getConnection();
            $stmt = $pdo->prepare('SELECT user_id FROM shippers WHERE user_id = ?');
            $stmt->execute([$shipperId]);
            if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
                return $res->json(ResponseHelper::forbidden('User is not a shipper'));
            }

            $type = strtolower(trim((string) ($req->getAttribute('type') ?? '')));
            if (!in_array($type, ['completed', 'rejected'], true)) {
                return $res->json(ResponseHelper::badRequest('Invalid history type. Use completed or rejected.'));
            }

            $page = max(1, (int) ($req->query('page') ?? 1));
            $limit = max(1, min(100, (int) ($req->query('limit') ?? 20)));
            $offset = ($page - 1) * $limit;

            $orders = $this->orderModel->getShipperOrderHistory($shipperId, $type, $limit, $offset);
            $total = $this->orderModel->getShipperOrderHistoryCount($shipperId, $type);

            return $res->json(ResponseHelper::paginated($orders, $total, $limit, $page));
        } catch (Exception $e) {
            error_log('[Shipper Order History] Error: ' . $e->getMessage());
            return $res->json(ResponseHelper::serverError('Failed to fetch shipper order history: ' . $e->getMessage()));
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
                $updatedOrder = $this->performShippingTransition($orderId, $shipperId, 'delivering', $payload, [
                    'update_order_status' => 'shipping',
                ]);
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
                    'require_location' => true,
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
                $this->performShippingTransition($orderId, $shipperId, 'rejected', $payload);

                $pdo = $this->container->database()->getConnection();
                $rejectNote = trim((string) ($payload['note'] ?? ''));
                $shipperName = $this->resolveShipperDisplayName($pdo, $shipperId);

                try {
                    (new AdminNotificationService($pdo))->notifyOrderRejected(
                        $orderId,
                        $shipperId,
                        $shipperName,
                        $rejectNote
                    );
                } catch (\Throwable $notifyEx) {
                    error_log('[OrderController] rejectOrder admin notification: ' . $notifyEx->getMessage());
                }

                $this->orderModel->updateStatus(
                    $orderId,
                    'processing',
                    $shipperId,
                    'Shipper rejected order: ' . ($rejectNote !== '' ? $rejectNote : 'no reason')
                );
                $this->orderModel->clearShipperAssignment($orderId);

                $newShipperId = $this->orderModel->autoAssignBestAvailableShipper(
                    $orderId,
                    1,
                    'Reassigned after rejection',
                    $shipperId
                );

                $adminNotif = new AdminNotificationService($pdo);
                if ($newShipperId !== null) {
                    $newShipperName = $this->resolveShipperDisplayName($pdo, $newShipperId);
                    try {
                        $adminNotif->notifyOrderReassigned($orderId, $newShipperId, $newShipperName);
                    } catch (\Throwable $notifyEx) {
                        error_log('[OrderController] rejectOrder reassign notify: ' . $notifyEx->getMessage());
                    }
                    $this->notifyShipperAssigned($pdo, $orderId, $newShipperId);
                } else {
                    try {
                        $adminNotif->notifyOrderReassignFailed($orderId);
                    } catch (\Throwable $notifyEx) {
                        error_log('[OrderController] rejectOrder reassign failed notify: ' . $notifyEx->getMessage());
                    }
                }

                try {
                    $notificationService = new NotificationService($pdo);
                    $notificationService->sendPushNotification(
                        $shipperId,
                        'Đã từ chối đơn hàng',
                        sprintf('Bạn đã từ chối thành công đơn #%d.', $orderId),
                        [
                            'order_id' => $orderId,
                            'type' => 'order_rejected_by_shipper',
                        ],
                        'order_rejected_by_shipper'
                    );
                } catch (\Throwable $notifyEx) {
                    error_log('[OrderController] rejectOrder shipper push: ' . $notifyEx->getMessage());
                }

                $updatedOrder = $this->orderModel->getByIdWithDetails($orderId);
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
                    'Total Amount' => number_format((float)($order['total_amount'] ?? 0), 0, ',', '.') . ' VND',
                    'Shipping Fee' => number_format((float)($order['shipping_fee'] ?? 0), 0, ',', '.') . ' VND',
                    'Discount' => number_format((float)($order['discount_amount_applied'] ?? 0), 0, ',', '.') . ' VND',
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

        $photoUrl = null;
        $needsPhoto = ($options['require_photo'] ?? false) || ($options['require_proof'] ?? false);

        if ($needsPhoto) {
            $photoService = new ShipperDeliveryPhotoService();
            $photoUrl = $photoService->resolveFromPayload($orderId, $payload);
        } else {
            $rawPhoto = $payload['photo_url']
                ?? $payload['photoUrl']
                ?? $payload['confirmation_photo']
                ?? $payload['photo']
                ?? null;
            if (is_string($rawPhoto) && trim($rawPhoto) !== '') {
                try {
                    $photoService = new ShipperDeliveryPhotoService();
                    $photoUrl = $photoService->resolveUrlString($orderId, trim($rawPhoto));
                } catch (Exception $e) {
                    if ((int) $e->getCode() === 422) {
                        throw $e;
                    }
                    $photoUrl = trim($rawPhoto);
                }
            }
        }

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

        if ($targetStatus === 'completed') {
            $this->finalizeCodPaymentAfterDelivery($orderId);
        }

        return $this->orderModel->getByIdWithDetails($orderId);
    }

    /**
     * Khi shipper hoàn tất giao hàng: chốt thanh toán COD (pending → confirmed, ghi paid_amount).
     */
    private function finalizeCodPaymentAfterDelivery(int $orderId): void
    {
        try {
            $payment = $this->paymentModel->getByOrderId($orderId);
            if (!$payment || strtolower((string) ($payment['method'] ?? '')) !== 'cod') {
                return;
            }
            if (($payment['status'] ?? '') !== 'pending') {
                return;
            }
            $order = $this->orderModel->find($orderId);
            if (!$order) {
                return;
            }
            $cod = (float) ($order['cod_amount'] ?? 0);
            $total = (float) ($order['total_amount'] ?? 0);
            $amount = $cod > 0 ? $cod : $total;
            $pid = (int) ($payment['payment_id'] ?? 0);
            if ($pid <= 0) {
                return;
            }
            $this->paymentModel->confirmCodCollection($pid, $amount);
        } catch (\Throwable $e) {
            error_log('[OrderController] finalizeCodPaymentAfterDelivery: ' . $e->getMessage());
        }
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

    private function shouldWaitForPaymentConfirmationBeforeAutoAssign(?string $paymentMethod): bool
    {
        if (!$paymentMethod) {
            return false;
        }

        return $paymentMethod !== 'cod';
    }

    private function autoAssignOrderToBestAvailableShipper(int $orderId, int $assignedBy, string $note = 'Auto-assigned by system'): bool
    {
        $shipperId = $this->orderModel->autoAssignBestAvailableShipper($orderId, $assignedBy, $note);
        if ($shipperId === null) {
            return false;
        }

        $pdo = $this->container->database()->getConnection();
        $this->notifyShipperAssigned($pdo, $orderId, $shipperId);

        return true;
    }

    private function resolveShipperDisplayName(\PDO $pdo, int $shipperUserId): string
    {
        $stmt = $pdo->prepare(
            'SELECT first_name, last_name FROM users WHERE user_id = ? LIMIT 1'
        );
        $stmt->execute([$shipperUserId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return 'Shipper #' . $shipperUserId;
        }
        $name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
        return $name !== '' ? $name : 'Shipper #' . $shipperUserId;
    }

    private function notifyShipperAssigned(\PDO $pdo, int $orderId, int $shipperId): void
    {
        try {
            (new NotificationService($pdo))->sendPushNotification(
                $shipperId,
                'Đơn hàng mới được gán',
                "Bạn có đơn hàng #{$orderId} mới cần xử lý",
                ['order_id' => $orderId, 'type' => 'new_order_assigned'],
                'new_order_assigned'
            );
        } catch (Exception $e) {
            error_log('[OrderController] Shipper push notification failed: ' . $e->getMessage());
        }

        try {
            (new AdminNotificationService($pdo))->notifyOrderAssigned($orderId, $shipperId);
        } catch (Exception $e) {
            error_log('[OrderController] Admin shipper notification: ' . $e->getMessage());
        }
    }

    /**
     * Get payment service based on method
     */
    private function getPaymentService(string $method, Database $database): MockQRPaymentService|VNPayPaymentService|VietQRPaymentService|CODPaymentService|PayOSPaymentService
    {
        $paymentModel = new Payment($database);  // Payment Model needs Database object
        $db = $database->getConnection();  // PaymentService needs PDO

        switch ($method) {
            case 'mock_qr':
                return new MockQRPaymentService($db, $paymentModel);
            case 'vnpay':
                $vnpayConfig = $this->config['payment']['vnpay'] ?? $this->config['vnpay'] ?? [];
                return new VNPayPaymentService($db, $paymentModel, $vnpayConfig);
            case 'vietqr':
                return new VietQRPaymentService($db, $paymentModel);
            case 'cod':
                return new CODPaymentService($db, $paymentModel);
            case 'bank_transfer':
            case 'payos':
                $payosConfig = $this->config['payos'] ?? [];
                // Fallback to $_ENV if config was loaded before .env (e.g. PAYOS_CLIENT_ID)
                if (empty($payosConfig['client_id']) && !empty($_ENV['PAYOS_CLIENT_ID'])) {
                    $payosConfig['client_id'] = $_ENV['PAYOS_CLIENT_ID'];
                    $payosConfig['api_key'] = $payosConfig['api_key'] ?? $_ENV['PAYOS_API_KEY'] ?? '';
                    $payosConfig['checksum_key'] = $payosConfig['checksum_key'] ?? $_ENV['PAYOS_CHECKSUM_KEY'] ?? '';
                    $payosConfig['api_url'] = $payosConfig['api_url'] ?? $_ENV['PAYOS_API_URL'] ?? 'https://api-merchant.payos.vn';
                    $payosConfig['base_url'] = $payosConfig['base_url'] ?? $_ENV['PAYOS_BASE_URL'] ?? $_ENV['APP_URL'] ?? 'http://localhost:3000';
                }
                return new PayOSPaymentService($db, $paymentModel, $payosConfig);
            default:
                throw new Exception("Unknown payment method: {$method}");
        }
    }

    /**
     * Counter / walk-in orders from admin panel (customer & address optional).
     */
    private function canCreateCounterOrder(Request $req): bool
    {
        $user = $req->getAttribute('user');
        if (!is_array($user)) {
            return false;
        }

        if (!empty($user['is_admin'])) {
            return true;
        }

        if (($user['account_type'] ?? '') === 'admin') {
            return true;
        }

        $roles = array_map('strtolower', $user['roles'] ?? []);
        if (in_array('admin', $roles, true)) {
            return true;
        }

        // Staff accounts (any role other than customer-only)
        if (empty($roles)) {
            return false;
        }

        return count($roles) > 1 || !in_array('customer', $roles, true);
    }

    /**
     * Default user + store pickup address for walk-in counter sales.
     *
     * @return array{customer_id: int, address_id: int}
     */
    private function resolveCounterOrderDefaults(): array
    {
        $pdo = $this->container->database()->getConnection();

        $envUserId = (int) ($_ENV['COUNTER_ORDER_USER_ID'] ?? 0);
        $envAddressId = (int) ($_ENV['COUNTER_ORDER_ADDRESS_ID'] ?? 0);
        if ($envUserId > 0 && $envAddressId > 0) {
            $this->ensureCustomerRow($pdo, $envUserId);
            return ['customer_id' => $envUserId, 'address_id' => $envAddressId];
        }

        $stmt = $pdo->prepare("
            SELECT u.user_id
            FROM users u
            INNER JOIN accounts a ON a.account_id = u.account_id
            WHERE a.account_name = ?
            LIMIT 1
        ");
        $stmt->execute([self::COUNTER_WALKIN_ACCOUNT]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $userId = $row ? (int) $row['user_id'] : 0;

        if ($userId <= 0) {
            $userId = $this->createCounterWalkInUser($pdo);
        }

        $addrStmt = $pdo->prepare("
            SELECT address_id FROM addresses
            WHERE user_id = ? AND is_default = 1
            ORDER BY address_id DESC
            LIMIT 1
        ");
        $addrStmt->execute([$userId]);
        $addrRow = $addrStmt->fetch(PDO::FETCH_ASSOC);
        $addressId = $addrRow ? (int) $addrRow['address_id'] : 0;

        if ($addressId <= 0) {
            $addressId = $this->createCounterStoreAddress($pdo, $userId);
        }

        $this->ensureCustomerRow($pdo, $userId);

        return ['customer_id' => $userId, 'address_id' => $addressId];
    }

    /**
     * orders.customer_id FK references customers.user_id — ensure row exists.
     */
    private function ensureCustomerRow(PDO $pdo, int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        $exists = $pdo->prepare('SELECT user_id FROM customers WHERE user_id = ? LIMIT 1');
        $exists->execute([$userId]);
        if ($exists->fetchColumn()) {
            return;
        }

        $u = $pdo->prepare('SELECT user_id FROM users WHERE user_id = ? LIMIT 1');
        $u->execute([$userId]);
        if (!$u->fetchColumn()) {
            throw new \InvalidArgumentException('User not found for customer enrollment');
        }

        try {
            $ins = $pdo->prepare(
                'INSERT INTO customers (user_id, loyalty_points, total_orders, created_at, updated_at) VALUES (?, 0, 0, NOW(), NOW())'
            );
            $ins->execute([$userId]);
        } catch (\PDOException $e) {
            if (!isset($e->errorInfo[1]) || (int) $e->errorInfo[1] !== 1062) {
                throw $e;
            }
        }
    }

    private function createCounterWalkInUser(PDO $pdo): int
    {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("
                INSERT INTO accounts (account_name, password, account_type, is_active, created_at)
                VALUES (?, ?, 'local', 1, NOW())
            ");
            $stmt->execute([
                self::COUNTER_WALKIN_ACCOUNT,
                password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
            ]);
            $accountId = (int) $pdo->lastInsertId();

            $stmt = $pdo->prepare("
                INSERT INTO users (account_id, first_name, last_name, email, phone)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $accountId,
                'Khách',
                'vãng lai',
                self::COUNTER_WALKIN_EMAIL,
                '0000000000',
            ]);
            $userId = (int) $pdo->lastInsertId();

            $roleStmt = $pdo->query("SELECT role_id FROM roles WHERE role_name = 'customer' LIMIT 1");
            $roleRow = $roleStmt ? $roleStmt->fetch(PDO::FETCH_ASSOC) : false;
            if ($roleRow && !empty($roleRow['role_id'])) {
                $link = $pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)');
                $link->execute([$userId, (int) $roleRow['role_id']]);
            }

            $this->ensureCustomerRow($pdo, $userId);

            $pdo->commit();
            return $userId;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    private function createCounterStoreAddress(PDO $pdo, int $userId): int
    {
        $lat = (float) ($_ENV['COUNTER_STORE_LAT'] ?? 10.776889);
        $lng = (float) ($_ENV['COUNTER_STORE_LNG'] ?? 106.700806);

        $stmt = $pdo->prepare("
            INSERT INTO addresses (
                user_id, receiver_name, phone, address_line, ward, district, province, is_default, lat, lng
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?)
        ");
        $stmt->execute([
            $userId,
            'Khách vãng lai',
            '0000000000',
            'Nhận tại cửa hàng',
            '',
            '',
            'Cửa hàng',
            $lat,
            $lng,
        ]);

        return (int) $pdo->lastInsertId();
    }
}
