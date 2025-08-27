<?php
require_once __DIR__ . '/vendor/autoload.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class SocketIOServer implements MessageComponentInterface
{
    protected $clients;
    protected $conversations;
    protected $userConnections;
    protected $jwtSecret;

    public function __construct()
    {
        $this->clients = new \SplObjectStorage;
        $this->conversations = [];
        $this->userConnections = [];
        
        // Load config
        $config = require __DIR__ . '/app/config/app.php';
        $this->jwtSecret = $config['jwt']['secret'];
        
        echo "WebSocket Socket.IO Server started on port 3001\n";
        echo "Using JWT secret: " . substr($this->jwtSecret, 0, 10) . "...\n";
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        try {
            $data = json_decode($msg, true);
            if (!$data) {
                echo "Invalid JSON received\n";
                return;
            }

            $event = $data['event'] ?? '';
            $payload = $data['payload'] ?? [];

            echo "Received event: {$event} from {$from->resourceId}\n";

            switch ($event) {
                case 'auth':
                    $this->handleAuth($from, $payload);
                    break;
                case 'join_conversation':
                    $this->handleJoinConversation($from, $payload);
                    break;
                case 'leave_conversation':
                    $this->handleLeaveConversation($from, $payload);
                    break;
                case 'typing_start':
                    $this->handleTypingStart($from, $payload);
                    break;
                case 'typing_stop':
                    $this->handleTypingStop($from, $payload);
                    break;
                case 'new_message':
                    $this->handleNewMessage($from, $payload);
                    break;
                default:
                    echo "Unknown event: {$event}\n";
            }
        } catch (Exception $e) {
            echo "Error processing message: " . $e->getMessage() . "\n";
        }
        
        // Check for backend messages in queue
        $this->processBackendMessageQueue();
    }

    public function onClose(ConnectionInterface $conn)
    {
        $this->clients->detach($conn);
        $this->removeUserConnection($conn);
        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }

    protected function handleAuth($conn, $payload)
    {
        $token = $payload['token'] ?? '';
        if (!$token) {
            $this->sendError($conn, 'Token required');
            return;
        }

        try {
            $decoded = JWT::decode($token, new Key($this->jwtSecret, 'HS256'));
            
            // Kiểm tra các trường có thể có user_id
            $userId = $decoded->user_id ?? $decoded->account_id ?? $decoded->id ?? $decoded->userid ?? null;
            
            if (!$userId) {
                echo "Token decoded but no user_id found. Available fields: " . implode(', ', array_keys((array)$decoded)) . "\n";
                $this->sendError($conn, 'Token missing user_id');
                return;
            }
            
            $conn->userId = $userId;
            $this->userConnections[$userId] = $conn;
            
            $this->sendToClient($conn, 'auth_success', [
                'user_id' => $userId,
                'message' => 'Authentication successful'
            ]);
            
            echo "User {$userId} authenticated\n";
        } catch (Exception $e) {
            $this->sendError($conn, 'Invalid token');
            echo "Authentication failed: " . $e->getMessage() . "\n";
        }
    }

    protected function handleJoinConversation($conn, $payload)
    {
        if (!isset($conn->userId)) {
            $this->sendError($conn, 'Authentication required');
            return;
        }

        $conversationId = $payload['conversation_id'] ?? null;
        if (!$conversationId) {
            $this->sendError($conn, 'Conversation ID required');
            return;
        }

        // Luôn tạo conversation mới nếu chưa tồn tại
        if (!isset($this->conversations[$conversationId])) {
            $this->conversations[$conversationId] = [];
            echo "Created new conversation {$conversationId}\n";
        }

        $this->conversations[$conversationId][$conn->resourceId] = $conn;
        $conn->conversationId = $conversationId;

        $this->sendToClient($conn, 'joined_conversation', [
            'conversation_id' => $conversationId,
            'message' => 'Joined conversation successfully'
        ]);

        echo "User {$conn->userId} joined conversation {$conversationId}\n";
        echo "Current conversations: " . implode(', ', array_keys($this->conversations)) . "\n";
    }

    protected function handleLeaveConversation($conn, $payload)
    {
        $conversationId = $payload['conversation_id'] ?? null;
        if ($conversationId && isset($this->conversations[$conversationId])) {
            unset($this->conversations[$conversationId][$conn->resourceId]);
            unset($conn->conversationId);
            
            if (empty($this->conversations[$conversationId])) {
                unset($this->conversations[$conversationId]);
            }
        }

        $this->sendToClient($conn, 'left_conversation', [
            'conversation_id' => $conversationId,
            'message' => 'Left conversation'
        ]);
    }

    protected function handleTypingStart($conn, $payload)
    {
        $conversationId = $payload['conversation_id'] ?? null;
        if (!$conversationId) {
            echo "Typing start failed: no conversation_id provided\n";
            return;
        }
        
        // Tự động join conversation nếu chưa join
        if (!isset($conn->conversationId) || $conn->conversationId !== $conversationId) {
            if (!isset($this->conversations[$conversationId])) {
                $this->conversations[$conversationId] = [];
                echo "Created conversation {$conversationId} for typing\n";
            }
            
            $this->conversations[$conversationId][$conn->resourceId] = $conn;
            $conn->conversationId = $conversationId;
            echo "Auto-joined conversation {$conversationId} for typing\n";
        }

        $typingData = [
            'type' => 'typing_start',
            'conversation_id' => $conversationId,
            'user_id' => $conn->userId ?? null
        ];

        echo "Broadcasting typing_start to conversation {$conversationId}\n";
        $this->broadcastToConversation($conversationId, 'typing_indicator', $typingData, $conn);
    }

    protected function handleTypingStop($conn, $payload)
    {
        $conversationId = $payload['conversation_id'] ?? null;
        if (!$conversationId) {
            echo "Typing stop failed: no conversation_id provided\n";
            return;
        }
        
        // Tự động join conversation nếu chưa join
        if (!isset($conn->conversationId) || $conn->conversationId !== $conversationId) {
            if (!isset($this->conversations[$conversationId])) {
                $this->conversations[$conversationId] = [];
                echo "Created conversation {$conversationId} for typing stop\n";
            }
            
            $this->conversations[$conversationId][$conn->resourceId] = $conn;
            $conn->conversationId = $conversationId;
            echo "Auto-joined conversation {$conversationId} for typing stop\n";
        }

        $typingData = [
            'type' => 'typing_stop',
            'conversation_id' => $conversationId,
            'user_id' => $conn->userId ?? null
        ];

        echo "Broadcasting typing_stop to conversation {$conversationId}\n";
        $this->broadcastToConversation($conversationId, 'typing_indicator', $typingData, $conn);
    }

    protected function handleNewMessage($conn, $payload)
    {
        $conversationId = $payload['conversation_id'] ?? null;
        if (!$conversationId || !isset($this->conversations[$conversationId])) {
            return;
        }

        $messageData = [
            'type' => 'new_message',
            'conversation_id' => $conversationId,
            'message' => $payload['message'] ?? null
        ];

        $this->broadcastToConversation($conversationId, 'new_message', $messageData, $conn);
    }

    // Broadcast message to all clients in a conversation except sender
    protected function broadcastToConversation($conversationId, $event, $data, $excludeConn = null)
    {
        if (!isset($this->conversations[$conversationId])) {
            echo "Broadcast failed: conversation {$conversationId} not found\n";
            return;
        }

        $clientCount = count($this->conversations[$conversationId]);
        echo "Broadcasting {$event} to {$clientCount} clients in conversation {$conversationId}\n";

        foreach ($this->conversations[$conversationId] as $conn) {
            if ($conn !== $excludeConn) {
                echo "Sending to client {$conn->resourceId}\n";
                $this->sendToClient($conn, $event, $data);
            }
        }
    }

    // Send message to specific client
    protected function sendToClient($conn, $event, $data)
    {
        $message = json_encode([
            'event' => $event,
            'payload' => $data
        ]);
        $conn->send($message);
    }

    // Send error message to client
    protected function sendError($conn, $message)
    {
        $this->sendToClient($conn, 'error', [
            'message' => $message
        ]);
    }

    // Remove user connection when they disconnect
    protected function removeUserConnection($conn)
    {
        if (isset($conn->userId)) {
            unset($this->userConnections[$conn->userId]);
        }

        if (isset($conn->conversationId)) {
            $conversationId = $conn->conversationId;
            if (isset($this->conversations[$conversationId])) {
                unset($this->conversations[$conversationId][$conn->resourceId]);
                
                if (empty($this->conversations[$conversationId])) {
                    unset($this->conversations[$conversationId]);
                }
            }
        }
    }

    // Public method to broadcast message from backend (e.g., when new message is saved to database)
    public function broadcastNewMessage($conversationId, $messageData)
    {
        $data = [
            'type' => 'new_message',
            'conversation_id' => $conversationId,
            'message' => $messageData
        ];

        $this->broadcastToConversation($conversationId, 'new_message', $data);
    }

    // Process backend message queue
    protected function processBackendMessageQueue()
    {
        $broadcastFile = __DIR__ . '/broadcast_queue.json';
        
        if (!file_exists($broadcastFile)) {
            return;
        }
        
        $queue = json_decode(file_get_contents($broadcastFile), true) ?: [];
        
        if (empty($queue)) {
            return;
        }
        
        // Process all messages in queue
        foreach ($queue as $item) {
            if ($item['type'] === 'backend_message') {
                echo "Processing backend message for conversation: {$item['conversation_id']}\n";
                
                $this->broadcastToConversation($item['conversation_id'], 'new_message', [
                    'message' => $item['message']
                ], null);
            }
        }
        
        // Clear the queue
        file_put_contents($broadcastFile, json_encode([]));
    }
}

// Start the server
$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new SocketIOServer()
        )
    ),
    3001
);

echo "Socket.IO WebSocket Server is running on port 3001\n";
$server->run();
