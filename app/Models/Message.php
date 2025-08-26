<?php

namespace App\Models;

// Custom Model class for PDO

class Message
{
    protected $table = 'messages';
    protected $primaryKey = 'message_id';
    protected $db;

    public function __construct($container = null)
    {
        if ($container) {
            $this->db = $container->database()->getConnection();
        }
    }

    public function getMessagesByConversation($conversationId, $limit = 50, $offset = 0)
    {
        $sql = "SELECT m.*, u.first_name, u.last_name, u.email, u.avatar_url,
                       DATE_FORMAT(m.sent_at, '%Y-%m-%d %H:%i:%s') as sent_at
                FROM messages m
                JOIN users u ON u.user_id = m.sender_id
                WHERE m.conversation_id = ? AND m.deleted_at IS NULL
                ORDER BY m.sent_at ASC
                LIMIT ? OFFSET ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$conversationId, $limit, $offset]);
        $messages = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Lấy media cho từng tin nhắn
        foreach ($messages as &$message) {
            $message['media'] = $this->getMessageMedia($message['message_id']);
        }

        return $messages;
    }

    public function getMessageMedia($messageId)
    {
        $sql = "SELECT * FROM message_media WHERE message_id = ? ORDER BY created_at ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$messageId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function markAsRead($conversationId, $userId)
    {
        $sql = "UPDATE messages SET is_read = 1 
                WHERE conversation_id = ? AND sender_id != ? AND is_read = 0";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$conversationId, $userId]);
    }

    public function getUnreadCount($conversationId, $userId)
    {
        $sql = "SELECT COUNT(*) FROM messages 
                WHERE conversation_id = ? AND sender_id != ? AND is_read = 0 AND deleted_at IS NULL";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$conversationId, $userId]);
        return $stmt->fetchColumn();
    }

    public function create($data)
    {
        $sql = "INSERT INTO messages (conversation_id, sender_id, content, sent_at) 
                VALUES (?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['conversation_id'],
            $data['sender_id'],
            $data['content'],
            $data['sent_at']
        ]);
        return $this->db->lastInsertId();
    }

    public function getById($messageId)
    {
        $sql = "SELECT m.*, u.first_name, u.last_name, u.email, u.avatar_url,
                       DATE_FORMAT(m.sent_at, '%Y-%m-%d %H:%i:%s') as sent_at
                FROM messages m
                JOIN users u ON u.user_id = m.sender_id
                WHERE m.message_id = ? AND m.deleted_at IS NULL";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$messageId]);
        $message = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($message) {
            $message['media'] = $this->getMessageMedia($message['message_id']);
        }

        return $message;
    }
}
