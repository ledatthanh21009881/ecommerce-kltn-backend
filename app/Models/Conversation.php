<?php

namespace App\Models;

// Custom Model class for PDO

class Conversation
{
    protected $table = 'conversations';
    protected $primaryKey = 'conversation_id';
    protected $db;

    public function __construct($container = null)
    {
        if ($container) {
            $this->db = $container->database()->getConnection();
        }
    }

    public function getConversationsWithLastMessage()
    {
        $sql = "SELECT c.*, u.first_name, u.last_name, u.email, u.avatar_url, 
                       m.content as last_message, m.sent_at as last_message_time,
                       (SELECT COUNT(*) FROM messages WHERE conversation_id = c.conversation_id AND is_read = 0) as unread_count
                FROM conversations c
                JOIN users u ON u.user_id = c.customer_id
                LEFT JOIN messages m ON m.message_id = (SELECT message_id FROM messages WHERE conversation_id = c.conversation_id ORDER BY sent_at DESC LIMIT 1)
                ORDER BY c.last_updated_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getByCustomerId($customerId)
    {
        $sql = "SELECT * FROM conversations WHERE customer_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$customerId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public function updateLastUpdated($conversationId)
    {
        $sql = "UPDATE conversations SET last_updated_at = ? WHERE conversation_id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([date('Y-m-d H:i:s'), $conversationId]);
    }

    public function create($data)
    {
        $sql = "INSERT INTO conversations (customer_id, label, status, created_at, last_updated_at) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['customer_id'],
            $data['label'],
            $data['status'],
            $data['created_at'],
            $data['created_at'] // last_updated_at same as created_at initially
        ]);
        return $this->db->lastInsertId();
    }
}
