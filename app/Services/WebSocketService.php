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

    public function __construct()
    {
        $this->clients = new \SplObjectStorage;
        $this->userConnections = [];
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        $data = json_decode($msg, true);
        
        if (!$data) return;

        switch ($data['type']) {
            case 'auth':
                $this->authenticateUser($from, $data['token']);
                break;
            case 'join_conversation':
                $this->joinConversation($from, $data['conversation_id']);
                break;
            case 'leave_conversation':
                $this->leaveConversation($from, $data['conversation_id']);
                break;
        }
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
        if (!isset($conn->userId)) return;
        
        if (!isset($conn->conversations)) {
            $conn->conversations = [];
        }
        
        // Convert to array if it's not already
        if (!is_array($conn->conversations)) {
            $conn->conversations = [];
        }
        
        // Add conversation if not already present
        if (!in_array($conversationId, $conn->conversations)) {
            $conn->conversations[] = $conversationId;
        }
        
        echo "User {$conn->userId} joined conversation {$conversationId}\n";
    }

    protected function leaveConversation($conn, $conversationId)
    {
        if (!isset($conn->conversations)) return;
        
        $conn->conversations = array_filter($conn->conversations, function($id) use ($conversationId) {
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
        $message = json_encode($data);
        
        foreach ($this->clients as $client) {
            if (isset($client->conversations) && in_array($data['conversation_id'], $client->conversations)) {
                $client->send($message);
            }
        }
    }

    public function sendToUser($userId, $data)
    {
        $message = json_encode($data);
        
        if (isset($this->userConnections[$userId])) {
            $this->userConnections[$userId]->send($message);
        }
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
