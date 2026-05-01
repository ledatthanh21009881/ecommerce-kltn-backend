<?php

namespace App\Controllers;

use App\Models\Message;
use App\Models\Conversation;
use App\Models\MessageMedia;
use App\Domain\Users\User;
use App\Support\CloudinaryService;
use App\Support\MessengerCloudinaryService;
use App\Services\WebSocketService;
use App\Services\NotificationService;
use App\Core\Request;
use App\Core\Response;

class MessageController extends BaseController
{
    protected $messageModel;
    protected $conversationModel;
    protected $messageMediaModel;
    protected $userModel;
    protected $cloudinaryService;
    protected $messengerCloudinaryService;
    protected $webSocketService;

    public function __construct($container = null)
    {
        parent::__construct($container);
        $this->messageModel = new Message($container);
        $this->conversationModel = new Conversation($container);
        $this->messageMediaModel = new MessageMedia($container);
        $this->userModel = new User($container);
        $this->cloudinaryService = new CloudinaryService();
        $this->messengerCloudinaryService = new MessengerCloudinaryService();
        $this->webSocketService = new WebSocketService();
    }

    // Lấy danh sách cuộc hội thoại
    public function getConversations(Request $req, Response $res): void
    {
        try {
            $user = $this->getCurrentUser($req);
            if (!$user) {
                http_response_code(401);
                echo json_encode([
                    'success' => false,
                    'message' => 'Unauthorized',
                    'status_code' => 401
                ]);
                return;
            }

            // Check if customer_id is provided in query params
            $customerId = $_GET['customer_id'] ?? null;
            
            if ($customerId) {
                $label = $this->resolveConversationLabel([
                    'label' => $_GET['label'] ?? null,
                    'shipper_id' => $_GET['shipper_id'] ?? null,
                    'order_id' => $_GET['order_id'] ?? null,
                ]);

                // Do not resolve a shipper thread with order_id missing/zero (avoids shipper:X:order:0 drift vs real order)
                if (preg_match('/^shipper:\d+:order:0$/', $label)) {
                    http_response_code(200);
                    echo json_encode([
                        'success' => true,
                        'message' => 'No conversation found',
                        'status_code' => 200,
                        'data' => [
                            'items' => [],
                            'pagination' => [
                                'total' => 0,
                                'per_page' => 50,
                                'current_page' => 1,
                                'last_page' => 1,
                            ],
                        ],
                    ]);

                    return;
                }

                $customerIdInt = (int) $customerId;
                if (!$this->userMayAccessShipperThread($user, ['label' => $label, 'customer_id' => $customerIdInt])) {
                    http_response_code(403);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Forbidden — private shipper conversation',
                        'status_code' => 403,
                    ]);

                    return;
                }

                // Get conversation for specific customer/context
                $conversation = $this->conversationModel->getByCustomerAndLabel($customerId, $label);
                if ($conversation) {
                    http_response_code(200);
                    echo json_encode([
                        'success' => true,
                        'message' => 'Success',
                        'status_code' => 200,
                        'data' => [
                            'items' => [$conversation],
                            'pagination' => [
                                'total' => 1,
                                'per_page' => 50,
                                'current_page' => 1,
                                'last_page' => 1
                            ]
                        ]
                    ]);
                    return;
                } else {
                    // No conversation found for this customer
                    http_response_code(200);
                    echo json_encode([
                        'success' => true,
                        'message' => 'No conversation found',
                        'status_code' => 200,
                        'data' => [
                            'items' => [],
                            'pagination' => [
                                'total' => 0,
                                'per_page' => 50,
                                'current_page' => 1,
                                'last_page' => 1
                            ]
                        ]
                    ]);
                    return;
                }
            }

            $myShipperFlag = $_GET['my_shipper_conversations'] ?? null;
            if ($myShipperFlag === '1' || $myShipperFlag === 'true') {
                $shipperUserId = (int) ($user['user_id'] ?? 0);
                if ($shipperUserId <= 0) {
                    http_response_code(401);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Unauthorized',
                        'status_code' => 401
                    ]);
                    return;
                }
                $pdo = $this->container->database()->getConnection();
                $chk = $pdo->prepare('SELECT user_id FROM shippers WHERE user_id = ? LIMIT 1');
                $chk->execute([$shipperUserId]);
                if (!$chk->fetch(\PDO::FETCH_ASSOC)) {
                    http_response_code(403);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Shipper access required',
                        'status_code' => 403
                    ]);
                    return;
                }
                $conversations = $this->conversationModel->getConversationsWithLastMessageForShipper($shipperUserId);
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'Success',
                    'status_code' => 200,
                    'data' => [
                        'items' => $conversations,
                        'pagination' => [
                            'total' => count($conversations),
                            'per_page' => 50,
                            'current_page' => 1,
                            'last_page' => 1
                        ]
                    ]
                ]);
                return;
            }

            // Admin inbox: không hiển thị thread shipper:…:order:… (chỉ khách ↔ shipper)
            $conversations = $this->conversationModel->getConversationsWithLastMessageForAdmin();
            
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Success',
                'status_code' => 200,
                'data' => [
                    'items' => $conversations,
                    'pagination' => [
                        'total' => count($conversations),
                        'per_page' => 50,
                        'current_page' => 1,
                        'last_page' => 1
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'status_code' => 500
            ]);
        }
    }

    // Lấy tin nhắn của một cuộc hội thoại
    public function getMessages(Request $req, Response $res)
    {
        try {
            $user = $this->getCurrentUser($req);
            if (!$user) {
                http_response_code(401);
                echo json_encode([
                    'success' => false,
                    'message' => 'Unauthorized',
                    'status_code' => 401
                ]);
                return;
            }

            $id = $req->getAttribute('id');
            $conversation = $this->conversationModel->getByConversationId((int) $id);
            if (!$conversation) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Conversation not found',
                    'status_code' => 404,
                ]);

                return;
            }
            if (!$this->userMayAccessShipperThread($user, $conversation)) {
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'message' => 'Forbidden — private shipper conversation',
                    'status_code' => 403,
                ]);

                return;
            }

            $messages = $this->messageModel->getMessagesByConversation($id);
            
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Success',
                'status_code' => 200,
                'data' => [
                    'items' => $messages,
                    'pagination' => [
                        'total' => count($messages),
                        'per_page' => 50,
                        'current_page' => 1,
                        'last_page' => 1
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'status_code' => 500
            ]);
        }
    }

    // Gửi tin nhắn
    public function sendMessage(Request $req, Response $res): void
    {
        try {
            $user = $this->getCurrentUser($req);
            if (!$user) {
                http_response_code(401);
                echo json_encode([
                    'success' => false,
                    'message' => 'Unauthorized',
                    'status_code' => 401
                ]);
                return;
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $conversationId = $input['conversation_id'] ?? null;
            $content = $input['content'] ?? '';
            $mediaFiles = $input['media'] ?? [];
            // MySQL column `is_link` is integer. Frontend may send `''` (empty string),
            // which causes: "Incorrect integer value: '' for column 'is_link'".
            // Normalize to 0/1.
            $isLinkRaw = $input['is_link'] ?? false;
            if (is_string($isLinkRaw)) {
                $isLinkRaw = trim($isLinkRaw);
            }
            $isLink = ($isLinkRaw === true || $isLinkRaw === 1 || $isLinkRaw === '1' || $isLinkRaw === 'true') ? 1 : 0;

            if (!$conversationId) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Conversation ID is required',
                    'status_code' => 400
                ]);
                return;
            }

            $conversationRow = $this->conversationModel->getByConversationId((int) $conversationId);
            if (!$conversationRow) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Conversation not found',
                    'status_code' => 404,
                ]);

                return;
            }
            if (!$this->userMayAccessShipperThread($user, $conversationRow)) {
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'message' => 'Forbidden — private shipper conversation',
                    'status_code' => 403,
                ]);

                return;
            }

            $replyToMessageId = isset($input['reply_to_message_id']) ? (int) $input['reply_to_message_id'] : 0;
            if ($replyToMessageId > 0) {
                $parent = $this->messageModel->findById($replyToMessageId);
                if (!$parent || (int) $parent['conversation_id'] !== (int) $conversationId) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Invalid reply_to_message_id for this conversation',
                        'status_code' => 400
                    ]);
                    return;
                }
            }

            // Tạo tin nhắn
            $messageData = [
                'conversation_id' => $conversationId,
                'sender_id' => $user['user_id'],
                'content' => $content,
                'sent_at' => date('Y-m-d H:i:s'),
                'is_link' => $isLink,
                'reply_to_message_id' => $replyToMessageId > 0 ? $replyToMessageId : null,
            ];

            $messageId = $this->messageModel->create($messageData);

            // Upload media nếu có
            $mediaUrls = [];
            if (!empty($mediaFiles)) {
                foreach ($mediaFiles as $file) {
                    // Kiểm tra xem file đã được upload chưa (có URL)
                    if (isset($file['url']) && !empty($file['url'])) {
                        // File đã được upload, chỉ cần lưu vào database
                        $mediaData = [
                            'message_id' => $messageId,
                            'url' => $file['url'],
                            'public_id' => $file['public_id'] ?? null,
                            'type' => $file['type'],
                            'metadata' => json_encode([
                                'file_name' => $file['name'] ?? null,
                                'file_size' => $file['size'] ?? null,
                                'width' => null,
                                'height' => null,
                                'format' => null
                            ]),
                            'created_at' => date('Y-m-d H:i:s')
                        ];
                        $this->messageMediaModel->create($mediaData);
                        $mediaUrls[] = $file['url'];
                    } else {
                        // File chưa được upload, cần upload trước
                        if (isset($file['tmp_name']) && file_exists($file['tmp_name'])) {
                            $uploadResult = null;
                            
                            if (strpos($file['type'], 'audio/') === 0) {
                                // Use special method for audio files
                                $uploadResult = $this->messengerCloudinaryService->uploadAudioFile($file, 'messenger');
                            } else {
                                // Use base64 method for images and videos
                                $fileContent = file_get_contents($file['tmp_name']);
                                $base64Data = base64_encode($fileContent);
                                
                                // Determine resource type based on file type
                                $resourceType = 'image';
                                if (strpos($file['type'], 'video/') === 0) {
                                    $resourceType = 'video';
                                }
                                
                                $uploadResult = $this->messengerCloudinaryService->uploadBase64Image($base64Data, 'messenger', $resourceType);
                            }
                            
                            if ($uploadResult['success']) {
                                $mediaData = [
                                    'message_id' => $messageId,
                                    'url' => $uploadResult['url'],
                                    'public_id' => $uploadResult['public_id'],
                                    'type' => $file['type'],
                                    'metadata' => json_encode([
                                        'file_name' => $file['name'] ?? null,
                                        'file_size' => $file['size'] ?? null,
                                        'width' => $uploadResult['width'] ?? null,
                                        'height' => $uploadResult['height'] ?? null,
                                        'format' => $uploadResult['format'] ?? null,
                                        'duration' => $uploadResult['duration'] ?? null
                                    ]),
                                    'created_at' => date('Y-m-d H:i:s')
                                ];
                                $this->messageMediaModel->create($mediaData);
                                $mediaUrls[] = $uploadResult['url'];
                            }
                        }
                    }
                }
            }

            // Cập nhật thời gian cuối của conversation
            $this->conversationModel->updateLastUpdated($conversationId);

            // Lấy thông tin tin nhắn đầy đủ để gửi qua WebSocket
            $fullMessage = $this->messageModel->getById($messageId);
            
            // Gửi thông báo realtime
            $this->webSocketService->broadcastMessage([
                'type' => 'new_message',
                'conversation_id' => $conversationId,
                'message' => $fullMessage
            ]);

            // In-app + FCM cho shipper khi khách gửi tin (label shipper:{id}:order:{id})
            if ($this->container && $fullMessage) {
                try {
                    $cid = (int) $conversationId;
                    $conv = $this->conversationModel->getByConversationId($cid);
                    if ($conv && !empty($conv['label']) && isset($conv['customer_id'])) {
                        $label = (string) $conv['label'];
                        $customerId = (int) $conv['customer_id'];
                        $senderId = (int) ($fullMessage['sender_id'] ?? 0);
                        if ($customerId > 0 && $senderId === $customerId && preg_match('/^shipper:(\d+):order:(\d+)$/', $label, $m)) {
                            $shipperUserId = (int) $m[1];
                            $orderNumericId = (int) $m[2];
                            $pdo = $this->container->database()->getConnection();
                            $notificationService = new NotificationService($pdo);
                            $preview = trim((string) ($fullMessage['content'] ?? ''));
                            if ($preview === '' || preg_match('/^\[(Image|Ảnh|Video)\]$/iu', $preview)) {
                                $mediaList = $fullMessage['media'] ?? [];
                                $preview = !empty($mediaList) ? '[Ảnh]' : 'Tin nhắn mới';
                            }
                            $fn = trim((string) ($fullMessage['first_name'] ?? ''));
                            $ln = trim((string) ($fullMessage['last_name'] ?? ''));
                            $who = trim($fn . ' ' . $ln) ?: 'Khách hàng';
                            $snippet = function_exists('mb_substr')
                                ? mb_substr($preview, 0, 120)
                                : substr($preview, 0, 120);
                            $notificationService->sendPushNotification(
                                $shipperUserId,
                                'Tin nhắn mới',
                                $who . ': ' . $snippet,
                                [
                                    'type' => 'new_chat_message',
                                    'conversation_id' => $cid,
                                    'customer_user_id' => $customerId,
                                    'order_id' => $orderNumericId,
                                    'customer_name' => $who,
                                ],
                                'new_chat_message'
                            );
                        }
                    }
                } catch (\Throwable $e) {
                    error_log('[MessageController] Shipper chat notification: ' . $e->getMessage());
                }
            }

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Message sent successfully',
                'status_code' => 200,
                'data' => $fullMessage
            ]);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'status_code' => 500
            ]);
        }
    }

    // Tạo cuộc hội thoại mới
    public function createConversation(Request $req, Response $res): void
    {
        try {
            $user = $this->getCurrentUser($req);
            if (!$user) {
                http_response_code(401);
                echo json_encode([
                    'success' => false,
                    'message' => 'Unauthorized',
                    'status_code' => 401
                ]);
                return;
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $customerId = $input['customer_id'] ?? null;
            $label = $this->resolveConversationLabel($input);

            if (!$customerId) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Customer ID is required',
                    'status_code' => 400
                ]);
                return;
            }

            $customerId = (int) $customerId;
            $jwtUserId = (int) ($user['user_id'] ?? 0);
            $bodyShipperId = isset($input['shipper_id']) && is_numeric($input['shipper_id']) ? (int) $input['shipper_id'] : 0;
            $bodyOrderId = isset($input['order_id']) && is_numeric($input['order_id']) ? (int) $input['order_id'] : 0;

            if ($jwtUserId !== $customerId) {
                if ($bodyShipperId > 0 && $bodyShipperId === $jwtUserId) {
                    if ($bodyOrderId <= 0 || !$this->assertShipperOwnsCustomerOrder($jwtUserId, $bodyOrderId, $customerId)) {
                        http_response_code(403);
                        echo json_encode([
                            'success' => false,
                            'message' => 'Cannot create conversation for this customer/order',
                            'status_code' => 403
                        ]);
                        return;
                    }
                } else {
                    http_response_code(403);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Forbidden',
                        'status_code' => 403
                    ]);
                    return;
                }
            }

            if ($bodyShipperId > 0 && $bodyOrderId <= 0) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'order_id is required for shipper chat',
                    'status_code' => 400
                ]);
                return;
            }

            if (preg_match('/^shipper:\d+:order:0$/', $label)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid shipper chat context (missing order_id)',
                    'status_code' => 400
                ]);
                return;
            }

            $before = $this->conversationModel->getByCustomerAndLabel($customerId, $label);
            $conversationRow = $this->conversationModel->findOrCreateByCustomerAndLabel($customerId, $label, 'open');
            $wasExisting = $before !== false && $before !== null;

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => $wasExisting ? 'Conversation already exists' : 'Conversation created successfully',
                'status_code' => 200,
                'data' => $conversationRow,
            ]);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'status_code' => 500
            ]);
        }
    }

    private function assertShipperOwnsCustomerOrder(int $shipperUserId, int $orderId, int $customerUserId): bool
    {
        $pdo = $this->container->database()->getConnection();
        $sql = "SELECT o.order_id
                FROM orders o
                INNER JOIN shipping_tracking st ON st.order_id = o.order_id
                WHERE o.order_id = ?
                  AND o.customer_id = ?
                  AND st.shipper_id = ?
                LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$orderId, $customerUserId, $shipperUserId]);
        return (bool) $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    private function resolveConversationLabel(array $data): string
    {
        $shipperIdRaw = $data['shipper_id'] ?? null;
        $orderIdRaw = $data['order_id'] ?? null;
        $explicit = isset($data['label']) ? trim((string)$data['label']) : '';

        $shipperId = is_numeric($shipperIdRaw) ? (int)$shipperIdRaw : 0;
        $orderId = is_numeric($orderIdRaw) ? (int)$orderIdRaw : 0;

        if ($shipperId > 0) {
            return sprintf('shipper:%d:order:%d', $shipperId, max(0, $orderId));
        }

        if ($explicit !== '') {
            return $explicit;
        }

        return 'support';
    }

    // Đánh dấu tin nhắn đã đọc
    public function markAsRead(Request $req, Response $res)
    {
        try {
            $user = $this->getCurrentUser($req);
            if (!$user) {
                http_response_code(401);
                echo json_encode([
                    'success' => false,
                    'message' => 'Unauthorized',
                    'status_code' => 401
                ]);
                return;
            }

            $id = $req->getAttribute('id');
            $conversation = $this->conversationModel->getByConversationId((int) $id);
            if (!$conversation) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Conversation not found',
                    'status_code' => 404,
                ]);

                return;
            }
            if (!$this->userMayAccessShipperThread($user, $conversation)) {
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'message' => 'Forbidden — private shipper conversation',
                    'status_code' => 403,
                ]);

                return;
            }

            $this->messageModel->markAsRead($id, $user['user_id']);

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Messages marked as read',
                'status_code' => 200
            ]);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'status_code' => 500
            ]);
        }
    }

    // Upload media
    public function uploadMedia(Request $req, Response $res): void
    {
        // Disable error display to prevent HTML output
        error_reporting(0);
        ini_set('display_errors', 0);
        
        error_log('Upload media method called');
        
        try {
            $user = $this->getCurrentUser($req);
            if (!$user) {
                http_response_code(401);
                echo json_encode([
                    'success' => false,
                    'message' => 'Unauthorized',
                    'status_code' => 401
                ]);
                return;
            }

            // Get uploaded file from $_FILES
            if (!isset($_FILES['media']) || $_FILES['media']['error'] !== UPLOAD_ERR_OK) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid file upload',
                    'status_code' => 400
                ]);
                return;
            }

            $file = $_FILES['media'];
            error_log('File received: ' . json_encode($file));

            // Validate file type
            $allowedTypes = [
                'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp',
                'video/mp4', 'video/avi', 'video/mov', 'video/wmv', 'video/flv',
                'audio/mp3', 'audio/wav', 'audio/ogg', 'audio/webm', 'audio/m4a'
            ];

            // Client MIME (React Native / Android) is often wrong (e.g. application/octet-stream). Prefer finfo.
            $clientType = strtolower(trim((string)($file['type'] ?? '')));
            $sniffed = null;
            $tmpPath = $file['tmp_name'] ?? '';
            if ($tmpPath !== '' && function_exists('finfo_open')) {
                $fi = @finfo_open(FILEINFO_MIME_TYPE);
                if ($fi) {
                    $sniffed = finfo_file($fi, $tmpPath);
                    finfo_close($fi);
                    if (is_string($sniffed)) {
                        $sniffed = strtolower(trim($sniffed));
                    } else {
                        $sniffed = null;
                    }
                }
            }

            $effectiveType = $clientType;
            if ($sniffed !== null && $sniffed !== '' && in_array($sniffed, $allowedTypes, true)) {
                $effectiveType = $sniffed;
            } elseif ($clientType !== '' && in_array($clientType, $allowedTypes, true)) {
                $effectiveType = $clientType;
            }

            if ($effectiveType === 'image/jpg' || $effectiveType === 'image/pjpeg') {
                $effectiveType = 'image/jpeg';
            }

            if (!in_array($effectiveType, $allowedTypes, true)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Unsupported file type',
                    'status_code' => 400
                ]);
                return;
            }

            $file['type'] = $effectiveType;

            // Validate file size (50MB max)
            $maxSize = 50 * 1024 * 1024;
            if ($file['size'] > $maxSize) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'File too large. Maximum size is 50MB',
                    'status_code' => 400
                ]);
                return;
            }

            error_log('Starting upload for file: ' . $file['name']);
            
            // Convert file to base64
            $fileContent = file_get_contents($file['tmp_name']);
            $base64Data = base64_encode($fileContent);
            
            // Determine resource type based on file type
            $resourceType = 'image';
            if (strpos($file['type'], 'video/') === 0) {
                $resourceType = 'video';
            }
            
            // Upload to Cloudinary
            $uploadResult = null;
            if (strpos($file['type'], 'audio/') === 0) {
                // Use special method for audio files
                $uploadResult = $this->messengerCloudinaryService->uploadAudioFile($file, 'messenger');
            } else {
                // Use base64 method for images and videos
                $uploadResult = $this->messengerCloudinaryService->uploadBase64Image($base64Data, 'messenger', $resourceType);
            }
            error_log('Upload result: ' . json_encode($uploadResult));

            if (!$uploadResult['success']) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Upload failed: ' . ($uploadResult['error'] ?? 'Unknown error'),
                    'status_code' => 500
                ]);
                return;
            }

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Media uploaded successfully',
                'status_code' => 200,
                'data' => [
                    'url' => $uploadResult['url'],
                    'public_id' => $uploadResult['public_id'],
                    'type' => $file['type'], // Use original file type
                    'file_name' => $file['name'],
                    'file_size' => $file['size']
                ]
            ]);

        } catch (\Exception $e) {
            error_log('Upload media error: ' . $e->getMessage());
            error_log('Upload media error trace: ' . $e->getTraceAsString());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'status_code' => 500
            ]);
        }
    }

    // Xóa tin nhắn
    public function deleteMessage(Request $req, Response $res)
    {
        try {
            $user = $this->getCurrentUser($req);
            if (!$user) {
                http_response_code(401);
                echo json_encode([
                    'success' => false,
                    'message' => 'Unauthorized',
                    'status_code' => 401
                ]);
                return;
            }

            $messageId = $req->getAttribute('id');
            
            // Lấy thông tin tin nhắn trước khi xóa
            $message = $this->messageModel->findById($messageId);
            if (!$message) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Message not found',
                    'status_code' => 404
                ]);
                return;
            }

            $conversationOfMessage = $this->conversationModel->getByConversationId((int) $message['conversation_id']);
            if ($conversationOfMessage && !$this->userMayAccessShipperThread($user, $conversationOfMessage)) {
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'message' => 'Forbidden — private shipper conversation',
                    'status_code' => 403,
                ]);

                return;
            }

            // Kiểm tra quyền xóa (chỉ người gửi mới được xóa)
            if ($message['sender_id'] != $user['user_id']) {
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'message' => 'You can only delete your own messages',
                    'status_code' => 403
                ]);
                return;
            }

            // Lấy media của tin nhắn để xóa khỏi Cloudinary
            $mediaFiles = $this->messageMediaModel->getByMessageId($messageId);
            
            // Xóa media từ Cloudinary
            foreach ($mediaFiles as $media) {
                if (!empty($media['public_id'])) {
                    $this->messengerCloudinaryService->deleteImage($media['public_id']);
                }
            }

            // Xóa media từ database
            $this->messageMediaModel->deleteByMessageId($messageId);
            
            // Xóa tin nhắn (soft delete)
            $result = $this->messageModel->delete($messageId);
            
            if (!$result) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to delete message',
                    'status_code' => 500
                ]);
                return;
            }

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Message deleted successfully',
                'status_code' => 200
            ]);

        } catch (\Exception $e) {
            error_log('Delete message error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'status_code' => 500
            ]);
        }
    }

    /**
     * Xóa toàn bộ cuộc hội thoại (tin + media + Cloudinary) — chỉ admin backend, không áp thread shipper:order.
     */
    public function deleteConversation(Request $req, Response $res): void
    {
        try {
            $user = $this->getCurrentUser($req);
            if (!$user) {
                http_response_code(401);
                echo json_encode([
                    'success' => false,
                    'message' => 'Unauthorized',
                    'status_code' => 401,
                ]);

                return;
            }

            if (!$this->isMessengerAdmin($user, $req)) {
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'message' => 'Chỉ tài khoản admin mới xóa được hội thoại.',
                    'status_code' => 403,
                ]);

                return;
            }

            $conversationId = (int) $req->getAttribute('id');
            if ($conversationId <= 0) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid conversation id',
                    'status_code' => 400,
                ]);

                return;
            }

            $conversation = $this->conversationModel->getByConversationId($conversationId);
            if (!$conversation) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Conversation not found',
                    'status_code' => 404,
                ]);

                return;
            }

            $label = (string) ($conversation['label'] ?? '');
            if (preg_match('/^shipper:\d+:order:\d+$/', $label)) {
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'message' => 'Không thể xóa cuộc trò chuyện giữa khách và shipper từ admin.',
                    'status_code' => 403,
                ]);

                return;
            }

            $pdo = $this->container->database()->getConnection();
            $pdo->beginTransaction();

            try {
                $stmt = $pdo->prepare('SELECT message_id FROM messages WHERE conversation_id = ?');
                $stmt->execute([$conversationId]);
                $messageIds = $stmt->fetchAll(\PDO::FETCH_COLUMN);

                foreach ($messageIds as $midRaw) {
                    $mid = (int) $midRaw;
                    if ($mid <= 0) {
                        continue;
                    }

                    $mediaFiles = $this->messageMediaModel->getByMessageId($mid);
                    foreach ($mediaFiles as $media) {
                        if (!empty($media['public_id'])) {
                            try {
                                $this->messengerCloudinaryService->deleteImage($media['public_id']);
                            } catch (\Throwable $e) {
                                error_log('[deleteConversation] Cloudinary: ' . $e->getMessage());
                            }
                        }
                    }
                    $this->messageMediaModel->deleteByMessageId($mid);
                }

                $pdo->prepare('DELETE FROM messages WHERE conversation_id = ?')->execute([$conversationId]);
                $pdo->prepare('DELETE FROM conversations WHERE conversation_id = ?')->execute([$conversationId]);
                $pdo->commit();
            } catch (\Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Conversation deleted',
                'status_code' => 200,
            ]);
        } catch (\Exception $e) {
            error_log('deleteConversation: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'status_code' => 500,
            ]);
        }
    }

    private function isMessengerAdmin(array $user, Request $req): bool
    {
        $payload = $req->getAttribute('token_payload');
        if (is_array($payload) && !empty($payload['is_admin'])) {
            return true;
        }

        $roles = $user['roles'] ?? [];
        if (!is_array($roles)) {
            $roles = ($roles !== null && $roles !== '') ? [(string) $roles] : [];
        }
        foreach ($roles as $r) {
            if (strtolower(trim((string) $r)) === 'admin') {
                return true;
            }
        }

        return strtolower((string) ($user['account_type'] ?? '')) === 'admin';
    }

    /**
     * Thread có label shipper:{shipperUserId}:order:* chỉ khách (customer_id) và shipper đó được truy cập.
     */
    private function userMayAccessShipperThread(array $user, array $conversation): bool
    {
        $label = (string) ($conversation['label'] ?? '');
        if (!preg_match('/^shipper:(\d+):order:\d+$/', $label, $m)) {
            return true;
        }

        $uid = (int) ($user['user_id'] ?? 0);
        $customerId = (int) ($conversation['customer_id'] ?? 0);
        $shipperUid = (int) $m[1];

        return $uid === $customerId || $uid === $shipperUid;
    }

    /**
     * Prefer AuthMiddleware's `user` on the request; fallback: Bearer via $_SERVER/getallheaders + App\Support\JWT.
     */
    private function getCurrentUser(?Request $req = null): ?array
    {
        if ($req !== null) {
            $fromMw = $req->getAttribute('user');
            if (is_array($fromMw) && $fromMw !== []) {
                return $fromMw;
            }
        }

        return $this->getCurrentUserFromBearerToken();
    }

    private function getCurrentUserFromBearerToken(): ?array
    {
        $authHeader = $this->readAuthorizationHeader();
        if ($authHeader === null || $authHeader === '' || !str_starts_with($authHeader, 'Bearer ')) {
            error_log('[MessageController] No Authorization header (fallback path)');
            return null;
        }

        $token = substr($authHeader, 7);
        $payload = $this->container->jwt()->decode($token);
        if (!is_array($payload) || !isset($payload['account_id'])) {
            error_log('[MessageController] JWT decode failed or missing account_id (fallback path)');
            return null;
        }

        $user = $this->userModel->getUserByAccountId($payload['account_id']);
        return is_array($user) && $user !== [] ? $user : null;
    }

    /** Same header resolution as Request::header('Authorization'), plus REDIRECT/nginx cases. */
    private function readAuthorizationHeader(): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', 'Authorization'));
        $auth = $_SERVER[$key] ?? null;
        if (is_string($auth) && $auth !== '') {
            return $auth;
        }
        if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION']) && is_string($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            return $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }
        if (function_exists('getallheaders')) {
            foreach (getallheaders() as $name => $value) {
                if (strcasecmp((string) $name, 'Authorization') === 0 && is_string($value) && $value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }
}
