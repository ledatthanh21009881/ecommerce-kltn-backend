<?php

namespace App\Controllers;

use App\Models\Message;
use App\Models\Conversation;
use App\Models\MessageMedia;
use App\Domain\Users\User;
use App\Services\CloudinaryService;
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
    protected $webSocketService;

    public function __construct($container = null)
    {
        parent::__construct($container);
        $this->messageModel = new Message($container);
        $this->conversationModel = new Conversation($container);
        $this->messageMediaModel = new MessageMedia($container);
        $this->userModel = new User($container);
        $this->cloudinaryService = new CloudinaryService();
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

            if (!$conversationId) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Conversation ID is required',
                    'status_code' => 400
                ]);
                return;
            }

            // Tạo tin nhắn
            $messageData = [
                'conversation_id' => $conversationId,
                'sender_id' => $user['user_id'],
                'content' => $content,
                'sent_at' => date('Y-m-d H:i:s')
            ];

            $messageId = $this->messageModel->create($messageData);

            // Upload media nếu có
            $mediaUrls = [];
            if (!empty($mediaFiles)) {
                foreach ($mediaFiles as $file) {
                    $uploadResult = $this->cloudinaryService->uploadMedia($file);
                    if ($uploadResult['success']) {
                        $mediaData = [
                            'message_id' => $messageId,
                            'url' => $uploadResult['url'],
                            'media_public_id' => $uploadResult['public_id'],
                            'type' => $uploadResult['type'],
                            'file_name' => $file['name'] ?? null,
                            'file_size' => $file['size'] ?? null,
                            'mime_type' => $file['type'] ?? null
                        ];
                        $this->messageMediaModel->create($mediaData);
                        $mediaUrls[] = $uploadResult['url'];
                    }
                }
            }

            // Cập nhật thời gian cuối của conversation
            $this->conversationModel->updateLastUpdated($conversationId);

            // Gửi thông báo realtime
            $this->webSocketService->broadcastMessage([
                'type' => 'new_message',
                'conversation_id' => $conversationId,
                'message' => [
                    'message_id' => $messageId,
                    'sender_id' => $user['user_id'],
                    'content' => $content,
                    'media_urls' => $mediaUrls,
                    'sent_at' => $messageData['sent_at']
                ]
            ]);

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Message sent successfully',
                'status_code' => 200,
                'data' => [
                    'message_id' => $messageId,
                    'media_urls' => $mediaUrls
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

            if (!$customerId) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Customer ID is required',
                    'status_code' => 400
                ]);
                return;
            }

            // Kiểm tra xem đã có conversation chưa
            $existingConversation = $this->conversationModel->getByCustomerId($customerId);
            if ($existingConversation) {
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'Conversation already exists',
                    'status_code' => 200,
                    'data' => $existingConversation
                ]);
                return;
            }

            // Tạo conversation mới
            $conversationData = [
                'customer_id' => $customerId,
                'label' => 'new',
                'status' => 'open',
                'created_at' => date('Y-m-d H:i:s')
            ];

            $conversationId = $this->conversationModel->create($conversationData);

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Conversation created successfully',
                'status_code' => 200,
                'data' => [
                    'conversation_id' => $conversationId
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

            $file = $this->request->getFile('media');
            if (!$file || !$file->isValid()) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid file',
                    'status_code' => 400
                ]);
                return;
            }

            $uploadResult = $this->cloudinaryService->uploadMedia([
                'tmp_name' => $file->getTempName(),
                'name' => $file->getName(),
                'size' => $file->getSize(),
                'type' => $file->getMimeType()
            ]);

            if (!$uploadResult['success']) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Upload failed',
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
                    'type' => $uploadResult['type']
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
            $jwtSecret = getenv('JWT_SECRET') ?: 'your-secret-key-here';
            $decoded = JWT::decode($token, new Key($jwtSecret, 'HS256'));
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
