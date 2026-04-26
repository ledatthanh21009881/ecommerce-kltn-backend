<?php

namespace App\Services;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

class WebSocketService implements MessageComponentInterface
{
    private const ADMIN_QUEUE_FILE = 'admin_notification_broadcast_queue.json';

    protected $clients;
    protected $userConnections;
    protected $clientConversations; // Add this to store conversations for each client
    protected $clientPayments; // Store payment rooms for each client
    /** @var \SplObjectStorage<ConnectionInterface, int> Kết nối admin đã join kênh thông báo */
    protected $adminNotifSubscribers;

    public function __construct()
    {
        $this->clients = new \SplObjectStorage;
        $this->userConnections = [];
        $this->clientConversations = []; // Initialize conversations storage
        $this->clientPayments = []; // Initialize payments storage
        $this->adminNotifSubscribers = new \SplObjectStorage;
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $this->clients->attach($conn);
        error_log("New connection! ({$conn->resourceId})");
        $this->drainAdminNotificationQueue();
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        error_log("Received message: {$msg}");
        $data = json_decode($msg, true);
        
        if (!$data) {
            error_log("Failed to decode JSON message");
            return;
        }

        if (!isset($data['type'])) {
            error_log("Message missing type field");
            return;
        }

        error_log("Message type: {$data['type']}");

        switch ($data['type']) {
            case 'auth':
                $token = $data['token'] ?? null;
                $this->authenticateUser($from, $token);
                break;
            case 'join_conversation':
                error_log("Joining conversation: {$data['conversation_id']}");
                $this->joinConversation($from, $data['conversation_id']);
                break;
            case 'leave_conversation':
                error_log("Leaving conversation: {$data['conversation_id']}");
                $this->leaveConversation($from, $data['conversation_id']);
                break;
            case 'typing_start':
                error_log("Typing start for conversation: {$data['conversation_id']}");
                $this->broadcastTyping($from, $data['conversation_id'], 'typing_start');
                break;
            case 'typing_stop':
                error_log("Typing stop for conversation: {$data['conversation_id']}");
                $this->broadcastTyping($from, $data['conversation_id'], 'typing_stop');
                break;
            case 'new_message':
                $conversationId = (int)($data['conversation_id'] ?? 0);
                $message = $data['message'] ?? null;
                if ($conversationId > 0 && $message) {
                    $realtimeData = [
                        'event' => 'new_message',
                        'payload' => [
                            'conversation_id' => $conversationId,
                            'message' => $message
                        ],
                        'type' => 'new_message',
                        'conversation_id' => $conversationId,
                        'message' => $message
                    ];
                    $sentCount = 0;
                    foreach ($this->clients as $client) {
                        $clientId = $client->resourceId;
                        if ($client !== $from && $this->isClientInConversation($clientId, $conversationId)) {
                            $client->send(json_encode($realtimeData));
                            $sentCount++;
                        }
                    }
                    error_log("WS relay new_message to {$sentCount} clients in conversation {$conversationId}");
                }
                break;
            case 'join_payment':
                $paymentId = $data['payment_id'] ?? null;
                if ($paymentId) {
                    error_log("Joining payment room: {$paymentId}");
                    $this->joinPaymentRoom($from, $paymentId);
                }
                break;
            case 'leave_payment':
                $paymentId = $data['payment_id'] ?? null;
                if ($paymentId) {
                    error_log("Leaving payment room: {$paymentId}");
                    $this->leavePaymentRoom($from, $paymentId);
                }
                break;
            case 'join_admin_notifications':
                $this->joinAdminNotifications($from);
                break;
            case 'leave_admin_notifications':
                $this->leaveAdminNotifications($from);
                break;
        }
        $this->drainAdminNotificationQueue();
    }

    public function onClose(ConnectionInterface $conn)
    {
        $this->clients->detach($conn);
        $this->removeUserConnection($conn);
        if ($this->adminNotifSubscribers->contains($conn)) {
            $this->adminNotifSubscribers->detach($conn);
        }
        
        // Clean up conversations and payments for this client
        $clientId = $conn->resourceId;
        if (isset($this->clientConversations[$clientId])) {
            unset($this->clientConversations[$clientId]);
        }
        if (isset($this->clientPayments[$clientId])) {
            unset($this->clientPayments[$clientId]);
        }
        
        error_log("Connection {$conn->resourceId} has disconnected");
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        error_log("An error has occurred: {$e->getMessage()}");
        $conn->close();
    }

    protected function authenticateUser($conn, $token)
    {
        try {
            // Load config the same way as Container
            // WebSocketService lives at: app/Services/ -> config is at: app/config/
            // Using the wrong relative path breaks JWT signature verification.
            $configPath = __DIR__ . '/../config';
            $config = [];
            foreach (glob($configPath . '/*.php') as $file) {
                $key = basename($file, '.php');
                $config[$key] = require $file;
            }
            $jwtSecret = $config['app']['jwt']['secret'] ?? 'your-secret-key-here';
            
            $decoded = \Firebase\JWT\JWT::decode($token, new \Firebase\JWT\Key($jwtSecret, 'HS256'));
            $userId = $decoded->account_id ?? $decoded->user_id ?? null;
            $roles = isset($decoded->roles) ? (array) $decoded->roles : [];
            $isAdmin = !empty($decoded->is_admin) || in_array('admin', $roles, true);
            
            if ($userId !== null) {
                $this->userConnections[$userId] = $conn;
            }
            $conn->userId = $userId;
            $conn->isAdmin = $isAdmin;
            
            error_log("User {$userId} authenticated (admin=" . ($isAdmin ? '1' : '0') . ')');
        } catch (\Exception $e) {
            error_log("Authentication failed: {$e->getMessage()}");
        }
    }

    protected function joinAdminNotifications(ConnectionInterface $conn): void
    {
        if (empty($conn->userId) || empty($conn->isAdmin)) {
            error_log('join_admin_notifications: denied (not admin or not authenticated)');
            $conn->send(json_encode([
                'type' => 'error',
                'event' => 'admin_notifications',
                'message' => 'Admin only',
            ]));
            return;
        }
        if (!$this->adminNotifSubscribers->contains($conn)) {
            $this->adminNotifSubscribers->attach($conn, 1);
        }
        error_log("Client {$conn->resourceId} joined admin notification channel");
        $conn->send(json_encode([
            'type' => 'subscribed',
            'event' => 'admin_notifications',
        ]));
    }

    protected function leaveAdminNotifications(ConnectionInterface $conn): void
    {
        if ($this->adminNotifSubscribers->contains($conn)) {
            $this->adminNotifSubscribers->detach($conn);
        }
    }

    /**
     * Gọi từ PHP HTTP (FPM) — đẩy job vào file; process Ratchet sẽ gửi tới client.
     */
    public static function getAdminNotificationQueuePath(): string
    {
        return dirname(__DIR__, 2) . '/' . self::ADMIN_QUEUE_FILE;
    }

    /**
     * Gửi payload cho mọi client đã join kênh admin.
     */
    public function broadcastAdminNotification(array $notification): int
    {
        $envelope = [
            'type' => 'admin_notification',
            'event' => 'admin_notification',
            'payload' => $notification,
        ];
        $json = json_encode($envelope, JSON_UNESCAPED_UNICODE);
        $sent = 0;
        foreach ($this->adminNotifSubscribers as $client) {
            $client->send($json);
            $sent++;
        }
        if ($sent > 0) {
            error_log("Broadcast admin_notification to {$sent} admin WS client(s)");
        }
        return $sent;
    }

    /**
     * Đọc hàng đợi từ file (do AdminNotificationService ghi từ HTTP) và push tới client.
     */
    public function drainAdminNotificationQueue(): void
    {
        $path = self::getAdminNotificationQueuePath();
        if (!is_file($path) || !is_readable($path)) {
            return;
        }
        if ($this->adminNotifSubscribers->count() === 0) {
            return;
        }
        $fp = fopen($path, 'c+');
        if (!$fp) {
            return;
        }
        if (!flock($fp, LOCK_EX | LOCK_NB)) {
            fclose($fp);
            return;
        }
        $raw = stream_get_contents($fp);
        if ($raw === false || $raw === '') {
            flock($fp, LOCK_UN);
            fclose($fp);
            return;
        }
        $queue = json_decode($raw, true);
        if (!is_array($queue) || $queue === []) {
            ftruncate($fp, 0);
            fflush($fp);
            flock($fp, LOCK_UN);
            fclose($fp);
            return;
        }
        foreach ($queue as $item) {
            if (!is_array($item) || !isset($item['payload']) || !is_array($item['payload'])) {
                continue;
            }
            $this->broadcastAdminNotification($item['payload']);
        }
        ftruncate($fp, 0);
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
    }

    protected function joinConversation($conn, $conversationId)
    {
        if (!isset($conn->userId)) {
            error_log("Cannot join conversation: no user ID");
            return;
        }
        
        // Store conversations in our own array using resourceId as key
        $clientId = $conn->resourceId;
        
        if (!isset($this->clientConversations[$clientId])) {
            $this->clientConversations[$clientId] = [];
        }
        
        // Add conversation if not already present
        if (!in_array($conversationId, $this->clientConversations[$clientId])) {
            $this->clientConversations[$clientId][] = $conversationId;
        }
        
        error_log("User {$conn->userId} joined conversation {$conversationId}");
        error_log("User conversations: " . implode(', ', $this->clientConversations[$clientId]));
        error_log("Debug - Conversations after join: " . json_encode(array_values($this->clientConversations[$clientId])));
    }

    protected function leaveConversation($conn, $conversationId)
    {
        $clientId = $conn->resourceId;
        
        if (!isset($this->clientConversations[$clientId])) return;
        
        $this->clientConversations[$clientId] = array_filter($this->clientConversations[$clientId], function($id) use ($conversationId) {
            return $id != $conversationId;
        });
        
        error_log("User {$conn->userId} left conversation {$conversationId}");
    }

    protected function removeUserConnection($conn)
    {
        if (isset($conn->userId)) {
            unset($this->userConnections[$conn->userId]);
        }
    }

    public function broadcastMessage($data)
    {
        $conversationId = (int)($data['conversation_id'] ?? 0);
        $message = $data['message'] ?? null;
        if ($conversationId <= 0 || !$message) {
            return;
        }

        // Send directly to connected WebSocket clients in this conversation.
        // Keep both formats to stay backward-compatible with existing clients.
        $realtimeData = [
            'event' => 'new_message',
            'payload' => [
                'conversation_id' => $conversationId,
                'message' => $message
            ],
            'type' => 'new_message',
            'conversation_id' => $conversationId,
            'message' => $message
        ];

        $sentCount = 0;
        foreach ($this->clients as $client) {
            $clientId = $client->resourceId;
            if ($this->isClientInConversation($clientId, $conversationId)) {
                $client->send(json_encode($realtimeData));
                $sentCount++;
            }
        }
        error_log("Broadcasted new_message to {$sentCount} clients in conversation {$conversationId}");

        // Keep file queue for compatibility with the secondary Socket.IO server flow.
        $broadcastData = [
            'type' => 'backend_message',
            'conversation_id' => $conversationId,
            'message' => $message,
            'timestamp' => time()
        ];

        $broadcastFile = __DIR__ . '/../../broadcast_queue.json';
        $queue = [];

        if (file_exists($broadcastFile)) {
            $queue = json_decode(file_get_contents($broadcastFile), true) ?: [];
        }

        $queue[] = $broadcastData;
        file_put_contents($broadcastFile, json_encode($queue));
    }

    protected function broadcastTyping($from, $conversationId, $typingType)
    {
        error_log("Broadcasting typing: {$typingType} for conversation: {$conversationId}");
        error_log("Total clients: " . count($this->clients));
        error_log("From client ID: {$from->resourceId}");
        
        $conversationId = (int)$conversationId;
        $typingData = [
            'event' => 'typing_indicator',
            'payload' => [
                'type' => $typingType,
                'conversation_id' => $conversationId
            ],
            'type' => $typingType,
            'conversation_id' => $conversationId
        ];

        $sentCount = 0;
        foreach ($this->clients as $client) {
            $clientId = $client->resourceId;
            $clientLog = "Checking client {$clientId}: ";
            $clientLog .= "Is not from: " . ($client !== $from ? 'yes' : 'no') . ", ";
            $clientLog .= "Has conversations: " . (isset($this->clientConversations[$clientId]) ? 'yes' : 'no') . ", ";
            if (isset($this->clientConversations[$clientId])) {
                $clientLog .= "Conversations: " . implode(', ', $this->clientConversations[$clientId]) . ", ";
                $clientLog .= "In conversation: " . (in_array($conversationId, $this->clientConversations[$clientId]) ? 'yes' : 'no');
            }
            error_log($clientLog);
            
            if ($client !== $from && $this->isClientInConversation($clientId, $conversationId)) {
                $client->send(json_encode($typingData));
                $sentCount++;
                error_log("Sent typing message to client {$clientId}");
            }
        }
        error_log("Sent typing message to {$sentCount} clients");
    }

    protected function isClientInConversation($clientId, $conversationId)
    {
        if (!isset($this->clientConversations[$clientId])) {
            return false;
        }

        $targetConversationId = (int)$conversationId;
        foreach ($this->clientConversations[$clientId] as $id) {
            if ((int)$id === $targetConversationId) {
                return true;
            }
        }

        return false;
    }

    public function sendToUser($userId, $data)
    {
        $message = json_encode($data);
        
        if (isset($this->userConnections[$userId])) {
            $this->userConnections[$userId]->send($message);
        }
    }

    /**
     * Join a payment room to receive real-time updates
     */
    protected function joinPaymentRoom($conn, $paymentId)
    {
        $clientId = $conn->resourceId;
        if (!isset($this->clientPayments[$clientId])) {
            $this->clientPayments[$clientId] = [];
        }
        if (!in_array($paymentId, $this->clientPayments[$clientId])) {
            $this->clientPayments[$clientId][] = $paymentId;
            error_log("Client {$clientId} joined payment room: {$paymentId}");
        }
    }

    /**
     * Leave a payment room
     */
    protected function leavePaymentRoom($conn, $paymentId)
    {
        $clientId = $conn->resourceId;
        if (isset($this->clientPayments[$clientId])) {
            $this->clientPayments[$clientId] = array_filter(
                $this->clientPayments[$clientId],
                fn($id) => $id != $paymentId
            );
            error_log("Client {$clientId} left payment room: {$paymentId}");
        }
    }

    /**
     * Broadcast payment status update to all clients watching this payment
     */
    public function broadcastPaymentUpdate($paymentId, $orderId, $status, $data = [])
    {
        $updateData = [
            'type' => 'payment_update',
            'payment_id' => $paymentId,
            'order_id' => $orderId,
            'status' => $status,
            'data' => $data,
            'timestamp' => time()
        ];
        
        // Send to all clients watching this payment
        $sentCount = 0;
        foreach ($this->clients as $client) {
            $clientId = $client->resourceId;
            if (isset($this->clientPayments[$clientId]) && in_array($paymentId, $this->clientPayments[$clientId])) {
                $client->send(json_encode($updateData));
                $sentCount++;
                error_log("Sent payment update to client {$clientId}");
            }
        }
        
        error_log("Broadcasted payment update to {$sentCount} clients: payment_id={$paymentId}, order_id={$orderId}, status={$status}");
        
        // Also write to file queue for Socket.IO server (if using)
        $broadcastFile = __DIR__ . '/../../broadcast_queue.json';
        $queue = [];
        if (file_exists($broadcastFile)) {
            $queue = json_decode(file_get_contents($broadcastFile), true) ?: [];
        }
        $queue[] = $updateData;
        file_put_contents($broadcastFile, json_encode($queue));
    }

    public function startServer($port = 8080)
    {
        $server = IoServer::factory(
            new HttpServer(
                new WsServer($this)
            ),
            $port
        );

        error_log("WebSocket server started on port {$port}");
        $server->run();
    }
}
