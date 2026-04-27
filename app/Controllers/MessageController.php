<?php

namespace App\Controllers;

use App\Models\Message;
use App\Models\Conversation;
use App\Models\MessageMedia;
use App\Domain\Users\User;
use App\Support\CloudinaryService;
use App\Support\MessengerCloudinaryService;
use App\Services\WebSocketService;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
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
    public function getConversations()
    {
        try {
            $user = $this->getCurrentUser();
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

            // Get all conversations (for admin)
            $conversations = $this->conversationModel->getConversationsWithLastMessage();
            
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
            $user = $this->getCurrentUser();
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
    public function sendMessage()
    {
        try {
            $user = $this->getCurrentUser();
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
    public function createConversation()
    {
        try {
            $user = $this->getCurrentUser();
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
            $user = $this->getCurrentUser();
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
    public function uploadMedia()
    {
        // Disable error display to prevent HTML output
        error_reporting(0);
        ini_set('display_errors', 0);
        
        error_log('Upload media method called');
        
        try {
            $user = $this->getCurrentUser();
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
            
            if (!in_array($file['type'], $allowedTypes)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Unsupported file type: ' . $file['type'],
                    'status_code' => 400
                ]);
                return;
            }

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
            $user = $this->getCurrentUser();
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

    private function getCurrentUser()
    {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? null;
        
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            error_log('No Authorization header found');
            return null;
        }

        $token = substr($authHeader, 7);
        
        try {
            // IMPORTANT: Use the same JWT secret source as the rest of the app (app/config/app.php).
            // On VPS, JWT_SECRET might not exist in shell env (printenv), but PHP may still load it via $_ENV.
            // Loading from config keeps WebSocket auth and REST auth consistent.
            $config = require __DIR__ . '/../config/app.php';
            $jwtSecret = $config['jwt']['secret'] ?? 'your-secret-key-here';
            $jwtAlgorithm = $config['jwt']['algorithm'] ?? 'HS256';
            $decoded = JWT::decode($token, new Key($jwtSecret, $jwtAlgorithm));
            error_log('JWT decoded successfully: ' . json_encode($decoded));
            
            $user = $this->userModel->getUserByAccountId($decoded->account_id);
            error_log('User found: ' . ($user ? 'yes' : 'no'));
            
            return $user;
        } catch (\Exception $e) {
            error_log('JWT decode error: ' . $e->getMessage());
            return null;
        }
    }
}
