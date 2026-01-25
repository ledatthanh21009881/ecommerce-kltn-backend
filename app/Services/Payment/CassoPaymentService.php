<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Domain\Payments\Payment;
use PDO;

class CassoPaymentService implements PaymentServiceInterface
{
    private Payment $paymentModel;
    private PDO $db;
    private string $apiKey;
    private string $webhookSecret;
    private array $config;

    public function __construct(PDO $db, Payment $paymentModel, array $config)
    {
        $this->db = $db;
        $this->paymentModel = $paymentModel;
        $this->apiKey = $config['api_key'] ?? '';
        $this->webhookSecret = $config['webhook_secret'] ?? '';
        $this->config = $config; // Store full config
    }

    public function createPayment(array $data): array
    {
        error_log("=== CassoPaymentService::createPayment START ===");
        error_log("Order ID: " . ($data['order_id'] ?? 'N/A'));
        error_log("Amount: " . ($data['amount'] ?? 'N/A'));
        error_log("API Key configured: " . (empty($this->apiKey) ? 'NO' : 'YES'));
        error_log("Config: " . json_encode($this->config));
        
        // Create payment record
        $paymentData = [
            'order_id' => $data['order_id'],
            'method' => 'casso',
            'paid_amount' => $data['amount'],
            'status' => 'pending',
        ];

        $paymentId = $this->paymentModel->create($paymentData);
        error_log("Payment created with ID: {$paymentId}");

        // Set expiration (24 hours from now)
        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
        $this->paymentModel->updateExpiresAt($paymentId, $expiresAt);

        // Generate QR code from bank account info (using VietQR)
        error_log("Calling createCassoQRCode...");
        $qrCodeData = $this->createCassoQRCode($data['order_id'], $data['amount'], $paymentId);
        error_log("QR Code Data returned: " . json_encode($qrCodeData));
        
        // Save QR code URL to database
        if (!empty($qrCodeData['qr_url'])) {
            $this->paymentModel->updatePaymentUrl($paymentId, $qrCodeData['qr_url']);
            error_log("Saved payment_url to database: {$qrCodeData['qr_url']}");
        } else {
            error_log("WARNING: No QR code URL generated! qrCodeData: " . json_encode($qrCodeData));
        }

        $result = [
            'payment_id' => $paymentId,
            'payment_url' => $qrCodeData['qr_url'] ?? null, // QR code image URL
            'qr_code' => $qrCodeData['qr_code'] ?? null, // QR code data for display
            'expires_at' => $expiresAt,
            'status' => 'pending',
            'method' => 'casso',
            'message' => 'Quét mã QR để thanh toán. Hệ thống sẽ tự động xác nhận khi nhận được giao dịch.',
            'bank_account' => $qrCodeData['bank_account'] ?? null,
        ];
        
        error_log("Returning result: " . json_encode($result));
        error_log("=== CassoPaymentService::createPayment END ===");
        
        return $result;
    }

    /**
     * Get bank account information from Casso API
     */
    private function getBankAccountInfo(): ?array
    {
        error_log("=== getBankAccountInfo START ===");
        
        if (empty($this->apiKey)) {
            error_log("getBankAccountInfo: API key is empty!");
            return null;
        }

        try {
            $apiUrl = $this->config['api_url'] ?? 'https://oauth.casso.vn/v2';
            $endpoint = rtrim($apiUrl, '/') . '/accounts';
            error_log("getBankAccountInfo: Calling endpoint: {$endpoint}");
            
            $ch = curl_init($endpoint);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Apikey ' . $this->apiKey,
                ],
                CURLOPT_TIMEOUT => 10,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                error_log("getBankAccountInfo: cURL error: {$curlError}");
                return null;
            }

            error_log("getBankAccountInfo: HTTP Code: {$httpCode}");
            error_log("getBankAccountInfo: Response: {$response}");

            if ($httpCode === 200) {
                $data = json_decode($response, true);
                error_log("getBankAccountInfo: Parsed data: " . json_encode($data));
                
                if (isset($data['error']) && $data['error'] == 0 && !empty($data['data'])) {
                    error_log("getBankAccountInfo: Found " . count($data['data']) . " accounts");
                    // Get first active account (connectStatus = 1)
                    foreach ($data['data'] as $index => $account) {
                        error_log("getBankAccountInfo: Account #{$index}: " . json_encode($account));
                        // Check connectStatus = 1 (connected)
                        $connectStatus = $account['connectStatus'] ?? $account['connect_status'] ?? 0;
                        if ($connectStatus == 1) {
                            $accountNumber = $account['accountNumber'] ?? $account['account_number'] ?? '';
                            error_log("getBankAccountInfo: Found active bank account: {$accountNumber}");
                            return $account;
                        }
                    }
                    error_log("getBankAccountInfo: No active account found (connectStatus = 1), trying first available account");
                    // Fallback: use first account if no active account found
                    if (!empty($data['data'][0])) {
                        $firstAccount = $data['data'][0];
                        $accountNumber = $firstAccount['accountNumber'] ?? $firstAccount['account_number'] ?? '';
                        error_log("getBankAccountInfo: Using first available account: {$accountNumber}");
                        return $firstAccount;
                    }
                } else {
                    error_log("getBankAccountInfo: API returned error or no data. Error: " . ($data['error'] ?? 'N/A') . ", Message: " . ($data['message'] ?? 'N/A'));
                }
            } else {
                error_log("getBankAccountInfo: API error - HTTP {$httpCode}, Response: {$response}");
            }
        } catch (\Exception $e) {
            error_log("getBankAccountInfo: Exception: " . $e->getMessage());
            error_log("getBankAccountInfo: Stack trace: " . $e->getTraceAsString());
        }
        
        error_log("=== getBankAccountInfo END (returning null) ===");
        return null;
    }

    /**
     * Convert Casso bank code name to VietQR bank code
     */
    private function convertBankCodeNameToVietQR(string $bankCodeName): string
    {
        // Map Casso bank code names to VietQR codes
        $mapping = [
            'vietcombank' => 'VCB',
            'acb_digi' => 'ACB',
            'timoplus' => 'TCB',
            'techcombank' => 'TCB',
            'vietinbank' => 'CTG',
            'bidv' => 'BID',
            'vpbank' => 'VPB',
            'mbbank' => 'MSB',
            'sacombank' => 'STB',
            'tpbank' => 'TPB',
            'shb' => 'SHB',
            'eximbank' => 'EIB',
            'vib' => 'VIB',
            'hdbank' => 'HDB',
            // Add more mappings as needed
        ];
        
        return $mapping[strtolower($bankCodeName)] ?? strtoupper($bankCodeName);
    }

    /**
     * Generate VietQR code from bank account info
     */
    private function generateVietQRCode(int $orderId, float $amount, ?array $bankAccount = null): array
    {
        error_log("=== generateVietQRCode START ===");
        error_log("Order ID: {$orderId}, Amount: {$amount}");
        error_log("Bank account provided: " . ($bankAccount ? 'YES' : 'NO'));
        
        // Try to get bank account from Casso API first
        if (!$bankAccount) {
            error_log("generateVietQRCode: Attempting to get bank account from API...");
            $bankAccount = $this->getBankAccountInfo();
        }
        
        // Fallback to config if API fails
        if (!$bankAccount) {
            error_log("generateVietQRCode: API failed, trying config fallback...");
            $accountNumber = $this->config['bank_account']['account_number'] ?? '';
            $bankCode = $this->config['bank_account']['bank_code'] ?? '';
            $accountName = $this->config['bank_account']['account_name'] ?? '';
            $bankName = $this->config['bank_account']['bank_name'] ?? '';
            
            // Strip quotes from config values (if any from .env)
            $accountNumber = trim($accountNumber, '"\'');
            $bankCode = trim($bankCode, '"\'');
            $accountName = trim($accountName, '"\'');
            $bankName = trim($bankName, '"\'');
            
            error_log("generateVietQRCode: Config values - account_number: '{$accountNumber}', bank_code: '{$bankCode}', accountName: '{$accountName}'");
            
            // Ultimate fallback - hardcoded values if config is also empty
            if (empty($accountNumber) || empty($bankCode)) {
                error_log("generateVietQRCode: Config empty, using hardcoded fallback...");
                $accountNumber = '9353126350';
                $bankCode = 'VCB';
                $accountName = 'LE DAT THANH';
                $bankName = 'Vietcombank';
                error_log("generateVietQRCode: Using hardcoded values - account: {$accountNumber}, bank: {$bankCode}");
            }
        } else {
            $accountNumber = $bankAccount['accountNumber'] ?? '';
            $accountName = $bankAccount['accountName'] ?? '';
            $bankName = $bankAccount['bankName'] ?? '';
            // Convert bank code name to VietQR format
            $bankCodeName = $bankAccount['bankCodeName'] ?? '';
            $bankCode = $this->convertBankCodeNameToVietQR($bankCodeName);
            
            error_log("generateVietQRCode: Using bank account from API - Number: '{$accountNumber}', Code: '{$bankCode}', Name: '{$bankName}'");
        }
        
        if (empty($bankCode) || empty($accountNumber)) {
            error_log("generateVietQRCode: ERROR - Missing bank code or account number!");
            error_log("generateVietQRCode: bankCode: '{$bankCode}', accountNumber: '{$accountNumber}'");
            return [];
        }
        
        // Đảm bảo accountName không rỗng
        if (empty($accountName)) {
            $accountName = $bankName ?? 'THANH TOAN';
            error_log("generateVietQRCode: accountName was empty, using fallback: '{$accountName}'");
        }
        
        $content = "ORDER_{$orderId}";
        $amountVND = (int)$amount; // VND doesn't have cents
        
        error_log("generateVietQRCode: Generating QR URL with - bankCode: {$bankCode}, accountNumber: {$accountNumber}, amount: {$amountVND}, content: {$content}, accountName: {$accountName}");
        
        // Format VietQR URL: https://img.vietqr.io/image/[BANK_CODE]-[ACCOUNT_NUMBER]-[TEMPLATE].jpg?amount=[AMOUNT]&addInfo=[CONTENT]&accountName=[ACCOUNT_NAME]
        // Note: Use .jpg instead of .png for better compatibility, and include accountName parameter
        $qrUrl = sprintf(
            'https://img.vietqr.io/image/%s-%s-compact2.jpg?amount=%d&addInfo=%s&accountName=%s',
            $bankCode,
            $accountNumber,
            $amountVND,
            urlencode($content),
            urlencode($accountName)
        );
        
        error_log("generateVietQRCode: Generated QR URL: {$qrUrl}");
        
        $result = [
            'qr_url' => $qrUrl,
            'bank_account' => [
                'account_number' => $accountNumber,
                'account_name' => $accountName,
                'bank_name' => $bankName,
                'bank_code' => $bankCode,
            ]
        ];
        
        error_log("generateVietQRCode: Returning: " . json_encode($result));
        error_log("=== generateVietQRCode END ===");
        
        return $result;
    }

    /**
     * Create QR code from bank account info (using VietQR)
     */
    private function createCassoQRCode(int $orderId, float $amount, int $paymentId): array
    {
        error_log("=== createCassoQRCode START ===");
        error_log("Order ID: {$orderId}, Amount: {$amount}, Payment ID: {$paymentId}");
        
        // Generate VietQR code from bank account info
        $result = $this->generateVietQRCode($orderId, $amount);
        
        error_log("createCassoQRCode: Result: " . json_encode($result));
        error_log("=== createCassoQRCode END ===");
        
        return $result;
    }

    public function processPayment(int $paymentId, array $data = []): bool
    {
        return true;
    }

    public function verifyPayment(array $data): bool
    {
        return true;
    }

    /**
     * Verify webhook signature (Webhook V2)
     * Signature format: t=timestamp,v1=hash
     * Hash = HMAC-SHA256(timestamp + '.' + raw_body, webhook_secret)
     */
    public function verifyWebhookSignature(string $signature, string $rawBody, int $timestamp): bool
    {
        if (empty($this->webhookSecret)) {
            return true; // Skip verification if secret not set
        }

        // Extract signature parts: t=timestamp,v1=signature
        if (!preg_match('/t=(\d+),v1=([a-f0-9]+)/', $signature, $matches)) {
            error_log("CassoPaymentService: Invalid signature format: {$signature}");
            return false;
        }

        $sigTimestamp = (int)$matches[1];
        $sigHash = $matches[2];

        // Check timestamp (should be within 5 minutes)
        $currentTime = time();
        if (abs($currentTime - $sigTimestamp) > 300) {
            error_log("CassoPaymentService: Signature timestamp expired. Current: {$currentTime}, Signature: {$sigTimestamp}");
            return false;
        }

        // Create expected signature using timestamp from signature + raw body
        $signedPayload = $sigTimestamp . '.' . $rawBody;
        $expectedHash = hash_hmac('sha256', $signedPayload, $this->webhookSecret);

        $isValid = hash_equals($expectedHash, $sigHash);
        
        if (!$isValid) {
            error_log("CassoPaymentService: Signature mismatch");
        }
        
        return $isValid;
    }

    /**
     * Verify secure token (Webhook v1)
     */
    public function verifySecureToken(string $token): bool
    {
        if (empty($this->webhookSecret)) {
            return true; // Skip verification if secret not set
        }
        return hash_equals($this->webhookSecret, $token);
    }

    /**
     * Get transactions from Casso API
     * @param array $options Query options (page, pageSize, fromDate, toDate)
     * @return array|null
     */
    public function getTransactions(array $options = []): ?array
    {
        // #region agent log (commented out)
        // $debugLogPath = __DIR__ . '/../../../.cursor/debug.log';
        // @mkdir(dirname($debugLogPath), 0777, true);
        // file_put_contents($debugLogPath, json_encode(['hypothesisId'=>'H1,H2,H3','location'=>'CassoPaymentService:getTransactions:START','message'=>'getTransactions CALLED','data'=>['apiKey_set'=>!empty($this->apiKey),'apiKey_length'=>strlen($this->apiKey),'apiKey_first20'=>substr($this->apiKey,0,20),'config_keys'=>array_keys($this->config),'config_api_key_set'=>isset($this->config['api_key'])],'timestamp'=>round(microtime(true)*1000),'sessionId'=>'debug-session'])."\n", FILE_APPEND);
        // #endregion
        error_log("=== getTransactions START ===");
        
        if (empty($this->apiKey)) {
            // #region agent log (commented out)
            // file_put_contents($debugLogPath, json_encode(['hypothesisId'=>'H1','location'=>'CassoPaymentService:getTransactions:EMPTY_KEY','message'=>'API KEY IS EMPTY','data'=>['config_keys'=>array_keys($this->config),'config_dump'=>$this->config],'timestamp'=>round(microtime(true)*1000),'sessionId'=>'debug-session'])."\n", FILE_APPEND);
            // #endregion
            error_log("getTransactions: API key is empty!");
            return null;
        }

        try {
            $apiUrl = $this->config['api_url'] ?? 'https://oauth.casso.vn/v2';
            $endpoint = rtrim($apiUrl, '/') . '/transactions';
            
            $params = [
                'page' => $options['page'] ?? 1,
                'pageSize' => $options['pageSize'] ?? 20,
                'sort' => 'DESC',
            ];
            
            if (!empty($options['fromDate'])) {
                $params['fromDate'] = $options['fromDate'];
            }
            if (!empty($options['toDate'])) {
                $params['toDate'] = $options['toDate'];
            }
            
            $url = $endpoint . '?' . http_build_query($params);
            error_log("getTransactions: Calling endpoint: {$url}");
            
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Apikey ' . $this->apiKey,
                    'Content-Type: application/json',
                ],
                CURLOPT_TIMEOUT => 10,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                error_log("getTransactions: cURL error: {$curlError}");
                return null;
            }

            // #region agent log (commented out)
            // file_put_contents($debugLogPath, json_encode(['hypothesisId'=>'H4,H5','location'=>'CassoPaymentService:getTransactions:RESPONSE','message'=>'API Response received','data'=>['httpCode'=>$httpCode,'url'=>$url,'curlError'=>$curlError,'response_length'=>strlen($response),'response_preview'=>substr($response,0,300)],'timestamp'=>round(microtime(true)*1000),'sessionId'=>'debug-session'])."\n", FILE_APPEND);
            // #endregion
            
            if ($httpCode === 200) {
                $data = json_decode($response, true);
                // #region agent log (commented out)
                // file_put_contents($debugLogPath, json_encode(['hypothesisId'=>'H4','location'=>'CassoPaymentService:getTransactions:PARSED','message'=>'API Response parsed','data'=>['api_error_code'=>$data['error'] ?? 'N/A','has_data'=>!empty($data['data']),'record_count'=>count($data['data']['records'] ?? [])],'timestamp'=>round(microtime(true)*1000),'sessionId'=>'debug-session'])."\n", FILE_APPEND);
                // #endregion
                
                if (isset($data['error']) && $data['error'] == 0 && !empty($data['data'])) {
                    $recordCount = count($data['data']['records'] ?? []);
                    error_log("getTransactions: Found {$recordCount} transactions");
                    return $data['data'];
                } else {
                    error_log("getTransactions: API returned error or no data");
                }
            } else {
                error_log("getTransactions: API error - HTTP {$httpCode}");
            }
        } catch (\Exception $e) {
            error_log("getTransactions: Exception: " . $e->getMessage());
        }
        
        error_log("=== getTransactions END ===");
        return null;
    }

    /**
     * Extract order ID from transaction description
     * @param string $description Transaction description
     * @return int|null
     */
    public function extractOrderId(string $description): ?int
    {
        // Try patterns: "ORDER_123", "DH123", "123", etc.
        if (preg_match('/ORDER[_\s]*(\d+)/i', $description, $matches)) {
            return (int)$matches[1];
        } elseif (preg_match('/DH[_\s]*(\d+)/i', $description, $matches)) {
            return (int)$matches[1];
        } elseif (preg_match('/(\d{4,})/', $description, $matches)) {
            // Try to match with order_id if it's a 4+ digit number
            return (int)$matches[1];
        }
        
        return null;
    }

    /**
     * Match transaction with payment
     * @param array $transaction Casso transaction
     * @param array $payment Payment record
     * @return bool
     */
    public function matchTransaction(array $transaction, array $payment): bool
    {
        // Check amount (allow 1000 VND difference for rounding)
        $amount = (float)($transaction['amount'] ?? 0);
        $expectedAmount = (float)$payment['paid_amount'];
        $difference = abs($amount - $expectedAmount);
        
        if ($difference > 1000) {
            error_log("CassoPaymentService: Amount mismatch. Expected: {$expectedAmount}, Received: {$amount}");
            return false;
        }
        
        return true;
    }

    /**
     * Auto-check payment status by querying Casso API
     * @param int $orderId Order ID
     * @param int $paymentId Payment ID
     * @return bool True if payment was confirmed
     */
    public function autoCheckPaymentStatus(int $orderId, int $paymentId): bool
    {
        // #region agent log (commented out)
        // $debugLogPath = __DIR__ . '/../../../.cursor/debug.log';
        // @mkdir(dirname($debugLogPath), 0777, true);
        // file_put_contents($debugLogPath, json_encode(['hypothesisId'=>'H1','location'=>'CassoPaymentService:autoCheckPaymentStatus:START','message'=>'autoCheckPaymentStatus CALLED','data'=>['orderId'=>$orderId,'paymentId'=>$paymentId,'apiKey_set'=>!empty($this->apiKey),'apiKey_length'=>strlen($this->apiKey)],'timestamp'=>round(microtime(true)*1000),'sessionId'=>'debug-session'])."\n", FILE_APPEND);
        // #endregion
        error_log("=== autoCheckPaymentStatus START ===");
        error_log("Order ID: {$orderId}, Payment ID: {$paymentId}");
        
        // Get payment to check current status
        $payment = $this->paymentModel->getByPaymentId($paymentId);
        if (!$payment || $payment['status'] !== 'pending' || $payment['method'] !== 'casso') {
            // #region agent log (commented out)
            // file_put_contents($debugLogPath, json_encode(['hypothesisId'=>'H1','location'=>'CassoPaymentService:autoCheckPaymentStatus:SKIP','message'=>'Payment not pending or not casso','data'=>['payment_exists'=>!!$payment,'status'=>$payment['status'] ?? 'N/A','method'=>$payment['method'] ?? 'N/A'],'timestamp'=>round(microtime(true)*1000),'sessionId'=>'debug-session'])."\n", FILE_APPEND);
            // #endregion
            error_log("autoCheckPaymentStatus: Payment not pending or not casso method");
            return false;
        }
        
        // Get transactions from Casso
        $transactionsResult = $this->getTransactions([
            'page' => 1,
            'pageSize' => 10
        ]);
        
        // #region agent log (commented out)
        // file_put_contents($debugLogPath, json_encode(['hypothesisId'=>'H1,H4','location'=>'CassoPaymentService:autoCheckPaymentStatus:TRANSACTIONS','message'=>'getTransactions result','data'=>['has_result'=>!!$transactionsResult,'has_records'=>!empty($transactionsResult['records']),'record_count'=>count($transactionsResult['records'] ?? [])],'timestamp'=>round(microtime(true)*1000),'sessionId'=>'debug-session'])."\n", FILE_APPEND);
        // #endregion
        
        if (!$transactionsResult || empty($transactionsResult['records'])) {
            error_log("autoCheckPaymentStatus: No transactions found from Casso");
            return false;
        }
        
        $transactions = $transactionsResult['records'];
        error_log("autoCheckPaymentStatus: Found " . count($transactions) . " transactions from Casso");
        
        // Find matching transaction
        foreach ($transactions as $index => $transaction) {
            $description = $transaction['description'] ?? '';
            $amount = $transaction['amount'] ?? 0;
            $extractedOrderId = $this->extractOrderId($description);
            
            // #region agent log (commented out)
            // file_put_contents($debugLogPath, json_encode(['hypothesisId'=>'H1','location'=>'CassoPaymentService:autoCheckPaymentStatus:LOOP','message'=>"Processing transaction #{$index}",'data'=>['index'=>$index,'description'=>$description,'amount'=>$amount,'extractedOrderId'=>$extractedOrderId,'expectedOrderId'=>$orderId,'match'=>($extractedOrderId === $orderId)],'timestamp'=>round(microtime(true)*1000),'sessionId'=>'debug-session'])."\n", FILE_APPEND);
            // #endregion
            error_log("autoCheckPaymentStatus: Processing transaction - Description: '{$description}', Extracted Order ID: " . ($extractedOrderId ?? 'NULL'));
            
            if ($extractedOrderId === $orderId) {
                error_log("autoCheckPaymentStatus: Found matching transaction for order {$orderId}");
                
                // Check if transaction matches payment
                $paymentAmount = $payment['paid_amount'] ?? 0;
                // #region agent log (commented out)
                // file_put_contents($debugLogPath, json_encode(['hypothesisId'=>'H1','location'=>'CassoPaymentService:autoCheckPaymentStatus:AMOUNT_CHECK','message'=>'Checking amount match','data'=>['transactionAmount'=>$amount,'paymentAmount'=>$paymentAmount,'difference'=>abs($amount - $paymentAmount)],'timestamp'=>round(microtime(true)*1000),'sessionId'=>'debug-session'])."\n", FILE_APPEND);
                // #endregion
                
                if ($this->matchTransaction($transaction, $payment)) {
                    // #region agent log (commented out)
                    // file_put_contents($debugLogPath, json_encode(['hypothesisId'=>'H1','location'=>'CassoPaymentService:autoCheckPaymentStatus:MATCH','message'=>'MATCH FOUND - Confirming payment','data'=>['orderId'=>$orderId,'paymentId'=>$paymentId,'amount'=>$amount],'timestamp'=>round(microtime(true)*1000),'sessionId'=>'debug-session'])."\n", FILE_APPEND);
                    // #endregion
                    error_log("autoCheckPaymentStatus: Transaction matches payment, confirming...");
                    
                    // Check if transaction already processed (idempotency)
                    $transactionId = $transaction['id'] ?? $transaction['tid'] ?? null;
                    // Convert to string if not null (updateStatus requires ?string)
                    $transactionId = $transactionId !== null ? (string)$transactionId : null;
                    if ($transactionId) {
                        try {
                            $stmt = $this->db->prepare("SELECT id FROM casso_transactions WHERE transaction_id = ?");
                            $stmt->execute([$transactionId]);
                            $existing = $stmt->fetch(\PDO::FETCH_ASSOC);
                            
                            if ($existing) {
                                error_log("autoCheckPaymentStatus: Transaction already processed, skipping");
                                return false;
                            }
                        } catch (\Exception $e) {
                            error_log("autoCheckPaymentStatus: Error checking casso_transactions: " . $e->getMessage());
                        }
                    }
                    
                    // Start database transaction
                    $this->db->beginTransaction();
                    
                    try {
                        // Mark transaction as processed
                        if ($transactionId) {
                            $stmt = $this->db->prepare("
                                INSERT INTO casso_transactions (transaction_id, payment_id, order_id, amount, description, processed_at)
                                VALUES (?, ?, ?, ?, ?, NOW())
                            ");
                            $stmt->execute([
                                $transactionId,
                                $paymentId,
                                $orderId,
                                $transaction['amount'] ?? 0,
                                $description
                            ]);
                            // #region agent log (commented out)
                            // file_put_contents($debugLogPath, json_encode(['hypothesisId'=>'H1','location'=>'CassoPaymentService:autoCheckPaymentStatus:DB_INSERT','message'=>'Inserted into casso_transactions','data'=>['transactionId'=>$transactionId,'paymentId'=>$paymentId,'orderId'=>$orderId],'timestamp'=>round(microtime(true)*1000),'sessionId'=>'debug-session'])."\n", FILE_APPEND);
                            // #endregion
                        }
                        
                        // Update payment status
                        $this->paymentModel->updateStatus($paymentId, 'confirmed', $transactionId);
                        // #region agent log (commented out)
                        // file_put_contents($debugLogPath, json_encode(['hypothesisId'=>'H1','location'=>'CassoPaymentService:autoCheckPaymentStatus:DB_UPDATE_STATUS','message'=>'Called updateStatus','data'=>['paymentId'=>$paymentId,'newStatus'=>'confirmed','transactionId'=>$transactionId],'timestamp'=>round(microtime(true)*1000),'sessionId'=>'debug-session'])."\n", FILE_APPEND);
                        // #endregion
                        
                        // Store callback payload
                        $stmt = $this->db->prepare("
                            UPDATE payments 
                            SET callback_payload = ?, 
                                bank_code = ?, 
                                paid_amount = ? 
                            WHERE payment_id = ?
                        ");
                        $stmt->execute([
                            json_encode($transaction),
                            $transaction['bankAbbreviation'] ?? $transaction['bankName'] ?? null,
                            $transaction['amount'] ?? 0,
                            $paymentId
                        ]);
                        // #region agent log (commented out)
                        // file_put_contents($debugLogPath, json_encode(['hypothesisId'=>'H1','location'=>'CassoPaymentService:autoCheckPaymentStatus:DB_UPDATE_PAYMENT','message'=>'Updated payments table','data'=>['paymentId'=>$paymentId,'rowsAffected'=>$stmt->rowCount()],'timestamp'=>round(microtime(true)*1000),'sessionId'=>'debug-session'])."\n", FILE_APPEND);
                        // #endregion

                        // Update order status
                        $stmt = $this->db->prepare("
                            UPDATE orders 
                            SET status = 'processing' 
                            WHERE order_id = ? AND status = 'pending'
                        ");
                        $stmt->execute([$orderId]);
                        // #region agent log (commented out)
                        // file_put_contents($debugLogPath, json_encode(['hypothesisId'=>'H1','location'=>'CassoPaymentService:autoCheckPaymentStatus:DB_UPDATE_ORDER','message'=>'Updated orders table','data'=>['orderId'=>$orderId,'rowsAffected'=>$stmt->rowCount()],'timestamp'=>round(microtime(true)*1000),'sessionId'=>'debug-session'])."\n", FILE_APPEND);
                        // #endregion
                        
                        $this->db->commit();
                        // #region agent log (commented out)
                        // file_put_contents($debugLogPath, json_encode(['hypothesisId'=>'H1','location'=>'CassoPaymentService:autoCheckPaymentStatus:DB_COMMIT','message'=>'Transaction committed successfully','data'=>['paymentId'=>$paymentId,'orderId'=>$orderId],'timestamp'=>round(microtime(true)*1000),'sessionId'=>'debug-session'])."\n", FILE_APPEND);
                        // #endregion
                        
                        // Verify payment status was actually updated
                        $verifyPayment = $this->paymentModel->getByPaymentId($paymentId);
                        // #region agent log (commented out)
                        // file_put_contents($debugLogPath, json_encode(['hypothesisId'=>'H1','location'=>'CassoPaymentService:autoCheckPaymentStatus:VERIFY','message'=>'Verifying payment status after update','data'=>['paymentId'=>$paymentId,'status'=>$verifyPayment['status'] ?? 'NOT_FOUND','expected'=>'confirmed'],'timestamp'=>round(microtime(true)*1000),'sessionId'=>'debug-session'])."\n", FILE_APPEND);
                        // #endregion
                        
                        error_log("autoCheckPaymentStatus: Payment confirmed successfully!");
                        return true;
                    } catch (\Exception $e) {
                        $this->db->rollBack();
                        // #region agent log (commented out)
                        // file_put_contents($debugLogPath, json_encode(['hypothesisId'=>'H1','location'=>'CassoPaymentService:autoCheckPaymentStatus:EXCEPTION','message'=>'Exception during DB update','data'=>['paymentId'=>$paymentId,'error'=>$e->getMessage(),'trace'=>substr($e->getTraceAsString(),0,500)],'timestamp'=>round(microtime(true)*1000),'sessionId'=>'debug-session'])."\n", FILE_APPEND);
                        // #endregion
                        error_log("autoCheckPaymentStatus: Error processing transaction: " . $e->getMessage());
                        return false;
                    }
                } else {
                    // #region agent log (commented out)
                    // file_put_contents($debugLogPath, json_encode(['hypothesisId'=>'H1','location'=>'CassoPaymentService:autoCheckPaymentStatus:AMOUNT_MISMATCH','message'=>'AMOUNT MISMATCH','data'=>['transactionAmount'=>$amount,'paymentAmount'=>$paymentAmount,'difference'=>abs($amount - $paymentAmount)],'timestamp'=>round(microtime(true)*1000),'sessionId'=>'debug-session'])."\n", FILE_APPEND);
                    // #endregion
                    error_log("autoCheckPaymentStatus: Transaction does not match payment (amount mismatch)");
                }
            }
        }
        
        // #region agent log (commented out)
        // file_put_contents($debugLogPath, json_encode(['hypothesisId'=>'H1','location'=>'CassoPaymentService:autoCheckPaymentStatus:END','message'=>'No matching transaction found','data'=>['orderId'=>$orderId,'totalChecked'=>count($transactions)],'timestamp'=>round(microtime(true)*1000),'sessionId'=>'debug-session'])."\n", FILE_APPEND);
        // #endregion
        error_log("autoCheckPaymentStatus: No matching transaction found");
        error_log("=== autoCheckPaymentStatus END ===");
        return false;
    }
}

