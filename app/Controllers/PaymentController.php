<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Controller, Request, Response, Container};
use App\Domain\Payments\Payment;
use App\Services\Payment\{MockQRPaymentService, VNPayPaymentService, VietQRPaymentService, CODPaymentService, CassoPaymentService};
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
     * POST /api/v1/payments/casso-webhook - Handle Casso webhook callback
     */
    public function handleCassoWebhook(Request $req, Response $res)
    {
        // Start timing to ensure response within 5 seconds
        $startTime = microtime(true);
        
        try {
            // Get raw request body for signature verification (Webhook V2)
            $rawBody = file_get_contents('php://input');
            
            // Parse JSON data
            $data = json_decode($rawBody, true);
            if (empty($data)) {
                error_log('Casso webhook: Invalid or empty request body');
                return $res->text("OK"); // Return OK even if invalid to prevent retries
            }
            
            // Get headers from $_SERVER
            $headers = $this->getAllHeaders();
            
            // Get Casso service
            $db = $this->container->database()->getConnection();
            $cassoConfig = $this->config['casso'] ?? [];
            $cassoService = new CassoPaymentService($db, $this->paymentModel, $cassoConfig);

            // Check error code
            $error = $data['error'] ?? -1;
            if ($error != 0) {
                // Return OK even if error code != 0 (as per Casso requirement)
                error_log("Casso webhook: Error code {$error}");
                return $res->text("OK");
            }

            // Verify webhook (Webhook V2 or Webhook v1)
            $isWebhookV2 = isset($headers['X-Casso-Signature']) || isset($headers['x-casso-signature']);
            
            if ($isWebhookV2) {
                // Webhook V2: Verify signature using raw body
                $signature = $headers['X-Casso-Signature'] ?? $headers['x-casso-signature'] ?? '';
                
                // Extract timestamp from signature
                $timestamp = time();
                if (preg_match('/t=(\d+)/', $signature, $matches)) {
                    $timestamp = (int)$matches[1];
                }
                
                // Verify signature with raw body
                if (!$cassoService->verifyWebhookSignature($signature, $rawBody, $timestamp)) {
                    error_log("Casso webhook: Invalid signature");
                    return $res->json([
                        'success' => false,
                        'message' => 'Invalid webhook signature'
                    ], 401);
                }
                
                // Process single transaction (Webhook V2 - data is object)
                $transaction = $data['data'] ?? [];
                if (!empty($transaction) && is_array($transaction)) {
                    $this->processCassoTransaction($transaction, $db);
                }
            } else {
                // Webhook v1: Verify secure token
                $secureToken = $headers['secure-token'] ?? $headers['Secure-Token'] ?? '';
                if (!$cassoService->verifySecureToken($secureToken)) {
                    error_log("Casso webhook: Invalid secure token");
                    return $res->json([
                        'success' => false,
                        'message' => 'Invalid secure token'
                    ], 401);
                }
                
                // Process transactions (Webhook v1 - data is array of transactions)
                $transactions = $data['data'] ?? [];
                if (is_array($transactions)) {
                    // Check if it's a single transaction object or array of transactions
                    if (isset($transactions['id']) || isset($transactions['tid'])) {
                        // Single transaction object
                        $this->processCassoTransaction($transactions, $db);
                    } else {
                        // Array of transactions
                        foreach ($transactions as $transaction) {
                            if (is_array($transaction)) {
                                $this->processCassoTransaction($transaction, $db);
                            }
                        }
                    }
                }
            }

            $processingTime = microtime(true) - $startTime;
            error_log("Casso webhook processed in " . round($processingTime * 1000, 2) . "ms");

            // Return "OK" as per Casso example (within 5 seconds requirement)
            return $res->text("OK");

        } catch (Exception $e) {
            $processingTime = microtime(true) - $startTime;
            error_log('Casso webhook error after ' . round($processingTime * 1000, 2) . 'ms: ' . $e->getMessage());
            error_log('Casso webhook stack trace: ' . $e->getTraceAsString());
            
            // Return OK even on error to prevent infinite retries
            return $res->text("OK");
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
     * Process a single Casso transaction with idempotency check
     */
    private function processCassoTransaction(array $transaction, $db): void
    {
        error_log("=== processCassoTransaction START ===");
        error_log("Transaction data: " . json_encode($transaction));
        
        // Get transaction ID (unique identifier from Casso - required for idempotency)
        $transactionId = $transaction['id'] ?? $transaction['tid'] ?? null;
        // Convert to string if not null (updateStatus requires ?string)
        $transactionId = $transactionId !== null ? (string)$transactionId : null;
        error_log("Transaction ID: " . ($transactionId ?? 'NULL'));
        
        if (empty($transactionId)) {
            error_log('Casso: Transaction ID is missing - cannot process without unique identifier');
            return;
        }
        
        // Check if this transaction has already been processed (idempotency - prevent replay attack)
        try {
            $stmt = $db->prepare("SELECT id, payment_id, order_id FROM casso_transactions WHERE transaction_id = ?");
            $stmt->execute([$transactionId]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            error_log("Idempotency check result: " . ($existing ? "FOUND" : "NOT FOUND"));
        } catch (\Exception $e) {
            error_log("Casso: Error checking casso_transactions table: " . $e->getMessage());
            error_log("Casso: Table might not exist. Skipping idempotency check.");
            $existing = false;
        }
        
        if ($existing) {
            error_log("Casso: Transaction {$transactionId} already processed (id: {$existing['id']}, payment_id: {$existing['payment_id']}, order_id: {$existing['order_id']}) - skipping (idempotency check)");
            return; // Already processed, skip to prevent replay attack
        }
        
        // Extract amount and description
        $amount = (float)($transaction['amount'] ?? 0);
        $description = $transaction['description'] ?? '';
        
        // Extract order_id from description
        // Try patterns: "ORDER_123", "DH123", "123", etc.
        $orderId = null;
        if (preg_match('/ORDER[_\s]*(\d+)/i', $description, $matches)) {
            $orderId = (int)$matches[1];
        } elseif (preg_match('/DH[_\s]*(\d+)/i', $description, $matches)) {
            $orderId = (int)$matches[1];
        } elseif (preg_match('/(\d{4,})/', $description, $matches)) {
            // Try to match with order_id if it's a 4+ digit number
            $orderId = (int)$matches[1];
        }
        
        if (!$orderId) {
            error_log('Casso: Could not extract order_id from description: ' . $description . ', transaction_id: ' . $transactionId);
            return;
        }
        
        // Find pending payment for this order
        $payment = $this->paymentModel->getByOrderId($orderId);
        
        if (!$payment) {
            error_log('Casso: Payment not found for order_id: ' . $orderId . ', transaction_id: ' . $transactionId);
            return;
        }
        
        if ($payment['status'] !== 'pending' || $payment['method'] !== 'casso') {
            error_log('Casso: Payment is not pending or not casso method. Order: ' . $orderId . ', Status: ' . ($payment['status'] ?? 'N/A') . ', Method: ' . ($payment['method'] ?? 'N/A'));
            return;
        }
        
        // Check if amount matches (allow 1000 VND difference for bank fees)
        $expectedAmount = (float)$payment['paid_amount'];
        $difference = abs($amount - $expectedAmount);
        
        if ($difference > 1000) {
            error_log("Casso: Amount mismatch. Expected: {$expectedAmount}, Received: {$amount}, Order: {$orderId}, Transaction: {$transactionId}");
            return;
        }
        
        // Start database transaction to ensure atomicity
        $db->beginTransaction();
        
        try {
            // Mark transaction as processed FIRST (before updating payment) - prevents race conditions
            $stmt = $db->prepare("
                INSERT INTO casso_transactions (transaction_id, payment_id, order_id, amount, description, processed_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $transactionId,
                $payment['payment_id'],
                $orderId,
                $amount,
                $description
            ]);
            
            // Update payment status
            $this->paymentModel->updateStatus(
                $payment['payment_id'], 
                'confirmed', 
                $transactionId
            );
            
            // Store callback payload
            $stmt = $db->prepare("
                UPDATE payments 
                SET callback_payload = ?, 
                    bank_code = ?, 
                    paid_amount = ? 
                WHERE payment_id = ?
            ");
            $stmt->execute([
                json_encode($transaction),
                $transaction['bankAbbreviation'] ?? $transaction['bankName'] ?? null,
                $amount,
                $payment['payment_id']
            ]);

            // Update order status
            $stmt = $db->prepare("
                UPDATE orders 
                SET status = 'processing' 
                WHERE order_id = ? AND status = 'pending'
            ");
            $stmt->execute([$orderId]);
            
            // Commit all changes
            $db->commit();
            
            error_log("Casso: Payment confirmed successfully - order_id: {$orderId}, payment_id: {$payment['payment_id']}, transaction_id: {$transactionId}, amount: {$amount}");
            
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
                        'method' => 'casso'
                    ]
                );
                error_log("Casso: Payment update broadcasted via WebSocket");
            } catch (\Exception $e) {
                error_log("Casso: Failed to broadcast payment update: " . $e->getMessage());
                // Don't fail payment processing if broadcast fails
            }
        } catch (\Exception $e) {
            // Rollback on error
            $db->rollBack();
            error_log("Casso: Error processing transaction {$transactionId}: " . $e->getMessage());
            error_log("Casso: Stack trace: " . $e->getTraceAsString());
            throw $e; // Re-throw to be caught by handleCassoWebhook
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
        
        // Check casso_transactions
        $stmt = $db->prepare("SELECT * FROM casso_transactions ORDER BY id DESC LIMIT 10");
        $stmt->execute();
        $cassoTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $res->json([
            'payment_raw' => $payment,
            'payment_by_order' => $paymentByOrder,
            'casso_transactions' => $cassoTransactions
        ]);
    }

    /**
     * Get payment service based on method
     */
    private function getPaymentService(string $method, $db): MockQRPaymentService|VNPayPaymentService|VietQRPaymentService|CODPaymentService|CassoPaymentService
    {
        $paymentModel = new Payment($this->container->get('database'));  // Payment Model needs Database object
        $pdo = $db;  // PaymentService needs PDO

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
            case 'casso':
                $cassoConfig = $this->config['casso'] ?? [];
                return new CassoPaymentService($pdo, $paymentModel, $cassoConfig);
            default:
                throw new Exception("Unknown payment method: {$method}");
        }
    }
}

