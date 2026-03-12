<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Controller, Request, Response, Container};
use App\Domain\Payments\Payment;
use App\Services\Payment\{MockQRPaymentService, VNPayPaymentService, VietQRPaymentService, CODPaymentService, PayOSPaymentService};
use App\Support\ResponseHelper;
use App\Core\Validator;
use Exception;
use PDO;

class PaymentController extends Controller
{
    private Payment $paymentModel;
    private array $config;

    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->paymentModel = new Payment($container->get('database'));
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
     * POST /api/v1/payments/create - Create payment
     */
    public function create(Request $req, Response $res)
    {
        try {
            $data = $req->body();

            $validator = new Validator($data);
            $validator->required(['order_id', 'method', 'amount'])
                     ->integer(['order_id'])
                     ->numeric('amount');

            if (!$validator->validate()) {
                return $res->json(ResponseHelper::validationError($validator->getErrors()));
            }

            $method = $data['method'];
            $db = $this->container->database()->getConnection();

            // Get appropriate service
            $service = $this->getPaymentService($method, $db);

            // Create payment
            $paymentData = $service->createPayment([
                'order_id' => $data['order_id'],
                'amount' => $data['amount'],
            ]);

            return $res->json(ResponseHelper::success($paymentData));

        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to create payment: ' . $e->getMessage()));
        }
    }

    /**
     * POST /api/v1/payments/{id}/approve - Approve mock/vietqr payment
     */
    public function approve(Request $req, Response $res)
    {
        try {
            $paymentId = (int) $req->getAttribute('id');

            $payment = $this->paymentModel->getByPaymentId($paymentId);
            if (!$payment) {
                return $res->json(ResponseHelper::notFound('Payment not found'));
            }

            if ($payment['status'] !== 'pending') {
                return $res->json(ResponseHelper::validationError(['payment' => 'Payment is not pending']));
            }

            // Check expiration
            if ($payment['expires_at'] && strtotime($payment['expires_at']) < time()) {
                return $res->json(ResponseHelper::validationError(['payment' => 'Payment has expired']));
            }

            // Update payment status
            $this->paymentModel->updateStatus($paymentId, 'confirmed');

            // Update order status if needed
            $orderId = $payment['order_id'];
            $stmt = $this->container->database()->getConnection()->prepare("UPDATE orders SET status = 'processing' WHERE order_id = ? AND status = 'pending'");
            $stmt->execute([$orderId]);

            return $res->json(ResponseHelper::success([
                'payment_id' => $paymentId,
                'status' => 'confirmed',
                'order_id' => $orderId
            ]));

        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to approve payment: ' . $e->getMessage()));
        }
    }

    /**
     * POST /api/v1/payments/vnpay-ipn - Handle VNPay IPN callback
     */
    public function handleVNPayIPN(Request $req, Response $res)
    {
        try {
            $data = $req->body();
            
            // Get VNPay service
            $db = $this->container->database()->getConnection();
            $vnpayService = new VNPayPaymentService($db, $this->paymentModel, $this->config['vnpay']);

            // Verify payment
            if (!$vnpayService->verifyPayment($data)) {
                return $res->json([
                    'RspCode' => '97',
                    'Message' => 'Checksum failed'
                ]);
            }

            $vnp_ResponseCode = $data['vnp_ResponseCode'] ?? '';
            $vnp_TxnRef = $data['vnp_TxnRef'] ?? '';

            // Extract payment_id from transaction ref (format: ORDER_{order_id}_{payment_id}_{timestamp})
            $parts = explode('_', $vnp_TxnRef);
            if (count($parts) < 3) {
                return $res->json([
                    'RspCode' => '01',
                    'Message' => 'Invalid transaction reference'
                ]);
            }

            $paymentId = (int)$parts[2];

            $payment = $this->paymentModel->getByPaymentId($paymentId);
            if (!$payment) {
                return $res->json([
                    'RspCode' => '01',
                    'Message' => 'Payment not found'
                ]);
            }

            // Update payment based on response code
            if ($vnp_ResponseCode === '00') {
                // Success
                $this->paymentModel->updateStatus($paymentId, 'confirmed', $vnp_TxnRef);
                
                // Store callback payload
                $stmt = $this->container->database()->getConnection()->prepare("UPDATE payments SET callback_payload = ?, bank_code = ?, paid_amount = ? WHERE payment_id = ?");
                $stmt->execute([
                    json_encode($data),
                    $data['vnp_BankCode'] ?? null,
                    (float)($data['vnp_Amount'] ?? 0) / 100, // Convert from cents
                    $paymentId
                ]);

                // Update order status
                $orderId = $payment['order_id'];
                $stmt = $this->container->database()->getConnection()->prepare("UPDATE orders SET status = 'processing' WHERE order_id = ? AND status = 'pending'");
                $stmt->execute([$orderId]);

                return $res->json([
                    'RspCode' => '00',
                    'Message' => 'Success'
                ]);
            } else {
                // Failed
                $this->paymentModel->updateStatus($paymentId, 'failed', $vnp_TxnRef);
                
                $stmt = $this->container->database()->getConnection()->prepare("UPDATE payments SET failure_reason = ?, callback_payload = ? WHERE payment_id = ?");
                $stmt->execute([
                    $data['vnp_ResponseCode'] ?? 'Unknown error',
                    json_encode($data),
                    $paymentId
                ]);

                return $res->json([
                    'RspCode' => $vnp_ResponseCode,
                    'Message' => 'Payment failed'
                ]);
            }

        } catch (Exception $e) {
            return $res->json([
                'RspCode' => '99',
                'Message' => 'Internal error: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * GET /api/v1/payments/{id}/status - Get payment status
     */
    public function getStatus(Request $req, Response $res)
    {
        try {
            $paymentId = (int) $req->getAttribute('id');

            $payment = $this->paymentModel->getByPaymentId($paymentId);
            // #region agent log
            try {
                $logPath = __DIR__ . '/../../.cursor/debug-e7f3bb.log';
                @mkdir(dirname($logPath), 0777, true);
                @file_put_contents($logPath, json_encode([
                    'sessionId' => 'e7f3bb',
                    'runId' => 'pre-fix',
                    'hypothesisId' => 'H5',
                    'location' => 'ecommerce/app/Controllers/PaymentController.php:getStatus',
                    'message' => 'Fetched payment record for status',
                    'data' => [
                        'paymentId' => $paymentId,
                        'payment_exists' => (bool)$payment,
                        'method' => is_array($payment) ? ($payment['method'] ?? null) : null,
                        'status' => is_array($payment) ? ($payment['status'] ?? null) : null,
                        'order_id' => is_array($payment) ? ($payment['order_id'] ?? null) : null,
                    ],
                    'timestamp' => round(microtime(true) * 1000),
                ]) . "\n", FILE_APPEND);
            } catch (\Throwable $e) {
                // ignore logging errors
            }
            // #endregion
            if (!$payment) {
                return $res->json(ResponseHelper::notFound('Payment not found'));
            }

            // Get order info for display (public endpoint, no auth required)
            $orderId = $payment['order_id'];
            $db = $this->container->database()->getConnection();
            
            // Get order basic info
            $orderStmt = $db->prepare("SELECT order_id, total_amount, status FROM orders WHERE order_id = ?");
            $orderStmt->execute([$orderId]);
            $order = $orderStmt->fetch(\PDO::FETCH_ASSOC);
            
            // Get order items
            $itemsStmt = $db->prepare("
                SELECT product_name_snapshot, quantity, unit_price 
                FROM order_items 
                WHERE order_id = ?
            ");
            $itemsStmt->execute([$orderId]);
            $items = $itemsStmt->fetchAll(\PDO::FETCH_ASSOC);

            // Get bank account info for Casso payment
            $bankAccount = null;
            if ($payment['method'] === 'casso') {
                $cassoConfig = $this->config['casso'] ?? [];
                // Ensure API key is loaded from $_ENV if not in config
                if (empty($cassoConfig['api_key'])) {
                    $cassoConfig['api_key'] = $_ENV['CASSO_API_KEY'] ?? '';
                }
                if (empty($cassoConfig['webhook_secret'])) {
                    $cassoConfig['webhook_secret'] = $_ENV['CASSO_WEBHOOK_SECRET'] ?? '';
                }
                if (empty($cassoConfig['api_url'])) {
                    $cassoConfig['api_url'] = $_ENV['CASSO_API_URL'] ?? 'https://oauth.casso.vn/v2';
                }
                $cassoService = new CassoPaymentService($db, $this->paymentModel, $cassoConfig);
                
                // 🔄 AUTO-CHECK: If payment is still pending, automatically check Casso API
                if ($payment['status'] === 'pending') {
                    // #region agent log (commented out)
                    // $debugLogPath = __DIR__ . '/../../.cursor/debug.log';
                    // @mkdir(dirname($debugLogPath), 0777, true);
                    // file_put_contents($debugLogPath, json_encode(['hypothesisId'=>'H1','location'=>'PaymentController:getStatus','message'=>'AUTO-CHECK CALLED','data'=>['orderId'=>$orderId,'paymentId'=>$paymentId,'cassoConfig_keys'=>array_keys($cassoConfig),'api_key_set'=>!empty($cassoConfig['api_key']),'api_key_length'=>strlen($cassoConfig['api_key'] ?? ''),'env_api_key_set'=>!empty($_ENV['CASSO_API_KEY'])],'timestamp'=>round(microtime(true)*1000),'sessionId'=>'debug-session'])."\n", FILE_APPEND);
                    // #endregion
                    error_log("🔄 [Payment Status] Payment is pending, auto-checking Casso API for order {$orderId}...");
                    
                    try {
                        $confirmed = $cassoService->autoCheckPaymentStatus($orderId, $paymentId);
                        if ($confirmed) {
                            error_log("✅ [Payment Status] Payment confirmed via auto-check!");
                            // Refresh payment data
                            $payment = $this->paymentModel->getByPaymentId($paymentId);
                        }
                    } catch (\Exception $e) {
                        error_log("⚠️ [Payment Status] Auto-check error: " . $e->getMessage());
                        // Don't fail the request if auto-check fails
                    }
                }
                
                // Try to get from callback_payload first
                if (!empty($payment['callback_payload'])) {
                    $callbackData = json_decode($payment['callback_payload'], true);
                    if (!empty($callbackData)) {
                        $bankAccount = [
                            'account_number' => $callbackData['accountNumber'] ?? null,
                            'account_name' => $callbackData['counterAccountName'] ?? null,
                            'bank_name' => $callbackData['bankName'] ?? $callbackData['bankAbbreviation'] ?? null,
                        ];
                    }
                }
                
                // If not found, get from config or API
                if (!$bankAccount && !empty($cassoConfig['bank_account'])) {
                    $bankAccount = $cassoConfig['bank_account'];
                }
            }

            return $res->json(ResponseHelper::success([
                'payment_id' => $payment['payment_id'],
                'order_id' => $payment['order_id'],
                'method' => $payment['method'],
                'status' => $payment['status'],
                'payment_url' => $payment['payment_url'] ?? null,
                'expires_at' => $payment['expires_at'] ?? null,
                'transaction_id' => $payment['transaction_id'] ?? null,
                'bank_account' => $bankAccount,
                // Include order info for display
                'order' => $order ? [
                    'order_id' => $order['order_id'],
                    'total_amount' => $order['total_amount'],
                    'status' => $order['status'],
                    'items' => $items
                ] : null
            ]));

        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to get payment status: ' . $e->getMessage()));
        }
    }

    /**
     * GET /api/v1/payments/order/{order_id} - Get payment by order_id
     */
    public function getByOrderId(Request $req, Response $res)
    {
        try {
            $orderId = (int) $req->getAttribute('order_id');

            $payment = $this->paymentModel->getByOrderId($orderId);
            if (!$payment) {
                return $res->json(ResponseHelper::notFound('Payment not found'));
            }

            return $res->json(ResponseHelper::success($payment));

        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to get payment: ' . $e->getMessage()));
        }
    }

    /**
     * POST /api/v1/payments/payos-webhook - Handle PayOS webhook callback
     */
    public function handlePayOSWebhook(Request $req, Response $res)
    {
        $startTime = microtime(true);
        try {
            $rawBody = file_get_contents('php://input');
            $data = json_decode($rawBody, true);
            if (empty($data)) {
                error_log('PayOS webhook: Invalid or empty request body');
                return $res->json(['success' => true], 200);
            }
            $db = $this->container->database()->getConnection();
            $payosConfig = $this->config['payos'] ?? [];
            $payosService = new PayOSPaymentService($db, $this->paymentModel, $payosConfig);

            if (empty($data['success']) || ($data['code'] ?? '') !== '00') {
                error_log('PayOS webhook: Unsuccessful or non-00 code');
                return $res->json(['success' => true], 200);
            }
            $signature = $data['signature'] ?? '';
            if (!$payosService->verifyWebhookSignature($data, $signature)) {
                error_log('PayOS webhook: Invalid signature');
                return $res->json(['success' => false, 'message' => 'Invalid signature'], 401);
            }
            $payload = $data['data'] ?? [];
            if (!empty($payload) && is_array($payload)) {
                $this->processPayOSTransaction($payload, $db);
            }
            $processingTime = microtime(true) - $startTime;
            error_log("PayOS webhook processed in " . round($processingTime * 1000, 2) . "ms");
            return $res->json(['success' => true], 200);
        } catch (Exception $e) {
            error_log('PayOS webhook error: ' . $e->getMessage());
            return $res->json(['success' => true], 200);
        }
    }

    /**
     * Get all HTTP headers
     */
    private function getAllHeaders(): array
    {
        $headers = [];
        
        // Get headers from $_SERVER (prefixed with HTTP_)
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headerName = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))));
                $headers[$headerName] = $value;
                // Also add lowercase version for case-insensitive lookup
                $headers[strtolower($headerName)] = $value;
            }
        }
        
        // Also check for Content-Type and other non-HTTP_ prefixed headers
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['Content-Type'] = $_SERVER['CONTENT_TYPE'];
            $headers['content-type'] = $_SERVER['CONTENT_TYPE'];
        }
        
        return $headers;
    }

    /**
     * Process a single PayOS webhook transaction with idempotency check
     * PayOS data: orderCode, amount, description, reference, accountNumber, ...
     */
    private function processPayOSTransaction(array $data, $db): void
    {
        $orderId = (int)($data['orderCode'] ?? 0);
        $amount = (float)($data['amount'] ?? 0);
        $description = $data['description'] ?? '';
        $transactionId = $data['reference'] ?? $data['paymentLinkId'] ?? ('payos-' . $orderId . '-' . ($data['transactionDateTime'] ?? ''));
        $transactionId = (string) $transactionId;

        if (!$orderId) {
            error_log('PayOS: Missing orderCode in webhook data');
            return;
        }
        try {
            $stmt = $db->prepare("SELECT id, payment_id, order_id FROM payos_transactions WHERE transaction_id = ?");
            $stmt->execute([$transactionId]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            $existing = false;
        }
        if ($existing) {
            error_log("PayOS: Transaction {$transactionId} already processed");
            return;
        }
        $payment = $this->paymentModel->getByOrderId($orderId);
        if (!$payment) {
            error_log('PayOS: Payment not found for order_id: ' . $orderId);
            return;
        }
        if ($payment['status'] !== 'pending' || $payment['method'] !== 'payos') {
            return;
        }
        $expectedAmount = (float)$payment['paid_amount'];
        if (abs($amount - $expectedAmount) > 1000) {
            error_log("PayOS: Amount mismatch. Expected: {$expectedAmount}, Received: {$amount}");
            return;
        }
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("
                INSERT INTO payos_transactions (transaction_id, payment_id, order_id, amount, description, processed_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$transactionId, $payment['payment_id'], $orderId, $amount, $description]);

            $this->paymentModel->updateStatus($payment['payment_id'], 'confirmed', $transactionId);

            $stmt = $db->prepare("
                UPDATE payments SET callback_payload = ?, bank_code = ?, paid_amount = ? WHERE payment_id = ?
            ");
            $stmt->execute([
                json_encode($data),
                $data['counterAccountBankName'] ?? $data['accountNumber'] ?? null,
                $amount,
                $payment['payment_id']
            ]);

            $stmt = $db->prepare("
                UPDATE orders SET status = 'processing' WHERE order_id = ? AND status = 'pending'
            ");
            $stmt->execute([$orderId]);

            $db->commit();
            error_log("PayOS: Payment confirmed - order_id: {$orderId}, payment_id: {$payment['payment_id']}, reference: {$transactionId}");
            
            // Broadcast payment update via WebSocket
            try {
                $wsService = new \App\Services\WebSocketService();
                $wsService->broadcastPaymentUpdate(
                    $payment['payment_id'],
                    $orderId,
                    'confirmed',
                    [
                        'transaction_id' => $transactionId,
                        'amount' => $amount,
                        'method' => 'payos'
                    ]
                );
                error_log("PayOS: Payment update broadcasted via WebSocket");
            } catch (\Exception $e) {
                error_log("PayOS: Failed to broadcast payment update: " . $e->getMessage());
            }
        } catch (\Exception $e) {
            $db->rollBack();
            error_log("PayOS: Error processing transaction {$transactionId}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Debug endpoint to check payment details
     * GET /api/v1/payments/debug/{id}
     */
    public function debugPayment(Request $req, Response $res)
    {
        $paymentId = (int) $req->getAttribute('id');
        $db = $this->container->database()->getConnection();
        
        // Get payment directly from database
        $stmt = $db->prepare("SELECT * FROM payments WHERE payment_id = ?");
        $stmt->execute([$paymentId]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get by order_id
        $paymentByOrder = null;
        if ($payment) {
            $paymentByOrder = $this->paymentModel->getByOrderId($payment['order_id']);
        }
        
        // Check payos_transactions
        $stmt = $db->prepare("SELECT * FROM payos_transactions ORDER BY id DESC LIMIT 10");
        $stmt->execute();
        $payosTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $res->json([
            'payment_raw' => $payment,
            'payment_by_order' => $paymentByOrder,
            'payos_transactions' => $payosTransactions
        ]);
    }

    /**
     * Get payment service based on method
     */
    private function getPaymentService(string $method, $db): MockQRPaymentService|VNPayPaymentService|VietQRPaymentService|CODPaymentService|PayOSPaymentService
    {
        $paymentModel = new Payment($this->container->get('database'));
        $pdo = $db;

        switch ($method) {
            case 'mock_qr':
                return new MockQRPaymentService($pdo, $paymentModel);
            case 'vnpay':
                $vnpayConfig = $this->config['payment']['vnpay'] ?? $this->config['vnpay'] ?? [];
                return new VNPayPaymentService($pdo, $paymentModel, $vnpayConfig);
            case 'vietqr':
                return new VietQRPaymentService($pdo, $paymentModel);
            case 'cod':
                return new CODPaymentService($pdo, $paymentModel);
            case 'payos':
                $payosConfig = $this->config['payos'] ?? [];
                return new PayOSPaymentService($pdo, $paymentModel, $payosConfig);
            default:
                throw new Exception("Unknown payment method: {$method}");
        }
    }
}

