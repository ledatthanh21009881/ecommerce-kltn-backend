<?php

namespace App\Services;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

class WebSocketService implements MessageComponentInterface
{
    protected $clients;
    protected $userConnections;
    protected $clientConversations; // Add this to store conversations for each client
    protected $clientPayments; // Store payment rooms for each client

    public function __construct()
    {
        $this->clients = new \SplObjectStorage;
        $this->userConnections = [];
        $this->clientConversations = []; // Initialize conversations storage
        $this->clientPayments = []; // Initialize payments storage
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        echo "Received message: {$msg}\n";
        $data = json_decode($msg, true);
        
        if (!$data) {
            echo "Failed to decode JSON message\n";
            return;
        }

        if (!isset($data['type'])) {
            echo "Message missing type field\n";
            return;
        }

        echo "Message type: {$data['type']}\n";

        switch ($data['type']) {
            case 'auth':
                $token = $data['token'] ?? null;
                $this->authenticateUser($from, $token);
                break;
            case 'join_conversation':
                echo "Joining conversation: {$data['conversation_id']}\n";
                $this->joinConversation($from, $data['conversation_id']);
                break;
            case 'leave_conversation':
                echo "Leaving conversation: {$data['conversation_id']}\n";
                $this->leaveConversation($from, $data['conversation_id']);
                break;
            case 'typing_start':
                echo "Typing start for conversation: {$data['conversation_id']}\n";
                $this->broadcastTyping($from, $data['conversation_id'], 'typing_start');
                break;
            case 'typing_stop':
                echo "Typing stop for conversation: {$data['conversation_id']}\n";
                $this->broadcastTyping($from, $data['conversation_id'], 'typing_stop');
                break;
            case 'join_payment':
                $paymentId = $data['payment_id'] ?? null;
                if ($paymentId) {
                    echo "Joining payment room: {$paymentId}\n";
                    $this->joinPaymentRoom($from, $paymentId);
                }
                break;
            case 'leave_payment':
                $paymentId = $data['payment_id'] ?? null;
                if ($paymentId) {
                    echo "Leaving payment room: {$paymentId}\n";
                    $this->leavePaymentRoom($from, $paymentId);
                }
                break;
        }
    }

    public function onClose(ConnectionInterface $conn)
    {
        $this->clients->detach($conn);
        $this->removeUserConnection($conn);
        
        // Clean up conversations and payments for this client
        $clientId = $conn->resourceId;
        if (isset($this->clientConversations[$clientId])) {
            unset($this->clientConversations[$clientId]);
        }
        if (isset($this->clientPayments[$clientId])) {
            unset($this->clientPayments[$clientId]);
        }
        
        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }

    protected function authenticateUser($conn, $token)
    {
        try {
            // Load config the same way as Container
            $configPath = __DIR__ . '/../../config';
            $config = [];
            foreach (glob($configPath . '/*.php') as $file) {
                $key = basename($file, '.php');
                $config[$key] = require $file;
            }
            $jwtSecret = $config['app']['jwt']['secret'] ?? 'your-secret-key-here';
            
            $decoded = \Firebase\JWT\JWT::decode($token, new \Firebase\JWT\Key($jwtSecret, 'HS256'));
            $userId = $decoded->account_id;
            
            $this->userConnections[$userId] = $conn;
            $conn->userId = $userId;
            
            echo "User {$userId} authenticated\n";
        } catch (\Exception $e) {
            echo "Authentication failed: {$e->getMessage()}\n";
        }
    }

    protected function joinConversation($conn, $conversationId)
    {
        if (!isset($conn->userId)) {
            echo "Cannot join conversation: no user ID\n";
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
        
        echo "User {$conn->userId} joined conversation {$conversationId}\n";
        echo "User conversations: " . implode(', ', $this->clientConversations[$clientId]) . "\n";
        
        // Debug: Check if conversation was actually added
        echo "Debug - Conversations after join: ";
        var_dump($this->clientConversations[$clientId]);
    }

    protected function leaveConversation($conn, $conversationId)
    {
        $clientId = $conn->resourceId;
        
        if (!isset($this->clientConversations[$clientId])) return;
        
        $this->clientConversations[$clientId] = array_filter($this->clientConversations[$clientId], function($id) use ($conversationId) {
            return $id != $conversationId;
        });
        
        echo "User {$conn->userId} left conversation {$conversationId}\n";
    }

    protected function removeUserConnection($conn)
    {
        if (isset($conn->userId)) {
            unset($this->userConnections[$conn->userId]);
        }
    }

    public function broadcastMessage($data)
    {
        // This method is called by backend when a new message is saved
        // We need to broadcast to the Socket.IO server on port 3001
        // Since we can't directly communicate between servers, we'll use a file-based approach
        
        $broadcastData = [
            'type' => 'backend_message',
            'conversation_id' => $data['conversation_id'],
            'message' => $data['message'],
            'timestamp' => time()
        ];
        
        // Write to a temporary file that Socket.IO server can read
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
        echo "Broadcasting typing: {$typingType} for conversation: {$conversationId}\n";
        echo "Total clients: " . count($this->clients) . "\n";
        echo "From client ID: {$from->resourceId}\n";
        
        $typingData = [
            'type' => $typingType,
            'conversation_id' => $conversationId
        ];

        $sentCount = 0;
        foreach ($this->clients as $client) {
            $clientId = $client->resourceId;
            echo "Checking client {$clientId}: ";
            echo "Is not from: " . ($client !== $from ? 'yes' : 'no') . ", ";
            echo "Has conversations: " . (isset($this->clientConversations[$clientId]) ? 'yes' : 'no') . ", ";
            if (isset($this->clientConversations[$clientId])) {
                echo "Conversations: " . implode(', ', $this->clientConversations[$clientId]) . ", ";
                echo "In conversation: " . (in_array($conversationId, $this->clientConversations[$clientId]) ? 'yes' : 'no');
            }
            echo "\n";
            
            if ($client !== $from && isset($this->clientConversations[$clientId]) && in_array($conversationId, $this->clientConversations[$clientId])) {
                $client->send(json_encode($typingData));
                $sentCount++;
                echo "Sent typing message to client {$clientId}\n";
            }
        }
        echo "Sent typing message to {$sentCount} clients\n";
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
            echo "Client {$clientId} joined payment room: {$paymentId}\n";
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
            echo "Client {$clientId} left payment room: {$paymentId}\n";
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
                echo "Sent payment update to client {$clientId}\n";
            }
        }
        
        echo "Broadcasted payment update to {$sentCount} clients: payment_id={$paymentId}, order_id={$orderId}, status={$status}\n";
        
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

        echo "WebSocket server started on port {$port}\n";
        $server->run();
    }
}
