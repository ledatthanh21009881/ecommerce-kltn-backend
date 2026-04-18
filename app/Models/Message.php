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
                       DATE_FORMAT(m.sent_at, '%Y-%m-%d %H:%i:%s') as sent_at,
                       rp.content AS reply_parent_content,
                       rp.sender_id AS reply_parent_sender_id,
                       rp.is_link AS reply_parent_is_link,
                       ru.first_name AS reply_parent_first_name,
                       ru.last_name AS reply_parent_last_name
                FROM messages m
                JOIN users u ON u.user_id = m.sender_id
                LEFT JOIN messages rp ON rp.message_id = m.reply_to_message_id AND rp.deleted_at IS NULL
                LEFT JOIN users ru ON ru.user_id = rp.sender_id
                WHERE m.conversation_id = ? AND m.deleted_at IS NULL
                ORDER BY m.sent_at ASC
                LIMIT ? OFFSET ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$conversationId, $limit, $offset]);
        $messages = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($messages as &$message) {
            $message['media'] = $this->getMessageMedia($message['message_id']);
            $message = $this->attachReplyToPayload($message);
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
        $replyTo = $data['reply_to_message_id'] ?? null;
        if ($replyTo === '' || $replyTo === false) {
            $replyTo = null;
        }
        $replyTo = $replyTo !== null ? (int) $replyTo : null;

        $sql = "INSERT INTO messages (conversation_id, sender_id, content, sent_at, is_link, reply_to_message_id) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['conversation_id'],
            $data['sender_id'],
            $data['content'],
            $data['sent_at'],
            $data['is_link'] ?? false,
            $replyTo,
        ]);
        return $this->db->lastInsertId();
    }

    public function getById($messageId)
    {
        $sql = "SELECT m.*, u.first_name, u.last_name, u.email, u.avatar_url,
                       DATE_FORMAT(m.sent_at, '%Y-%m-%d %H:%i:%s') as sent_at,
                       rp.content AS reply_parent_content,
                       rp.sender_id AS reply_parent_sender_id,
                       rp.is_link AS reply_parent_is_link,
                       ru.first_name AS reply_parent_first_name,
                       ru.last_name AS reply_parent_last_name
                FROM messages m
                JOIN users u ON u.user_id = m.sender_id
                LEFT JOIN messages rp ON rp.message_id = m.reply_to_message_id AND rp.deleted_at IS NULL
                LEFT JOIN users ru ON ru.user_id = rp.sender_id
                WHERE m.message_id = ? AND m.deleted_at IS NULL";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$messageId]);
        $message = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($message) {
            $message['media'] = $this->getMessageMedia($message['message_id']);
            $message = $this->attachReplyToPayload($message);
        }

        return $message;
    }

    /**
     * Gắn object reply_to cho API và bỏ cột join tạm.
     */
    private function attachReplyToPayload(array $message)
    {
        $rid = isset($message['reply_to_message_id']) ? (int) $message['reply_to_message_id'] : 0;
        if ($rid <= 0) {
            $message['reply_to'] = null;
            unset(
                $message['reply_parent_content'],
                $message['reply_parent_sender_id'],
                $message['reply_parent_is_link'],
                $message['reply_parent_first_name'],
                $message['reply_parent_last_name']
            );

            return $message;
        }

        $message['reply_to'] = [
            'message_id' => $rid,
            'content' => $message['reply_parent_content'] ?? '',
            'sender_id' => (int) ($message['reply_parent_sender_id'] ?? 0),
            'is_link' => (int) ($message['reply_parent_is_link'] ?? 0),
            'first_name' => $message['reply_parent_first_name'] ?? '',
            'last_name' => $message['reply_parent_last_name'] ?? '',
            'media' => $this->getMessageMedia($rid),
        ];

        unset(
            $message['reply_parent_content'],
            $message['reply_parent_sender_id'],
            $message['reply_parent_is_link'],
            $message['reply_parent_first_name'],
            $message['reply_parent_last_name']
        );

        return $message;
    }

    public function findById($messageId)
    {
        $sql = "SELECT * FROM messages WHERE message_id = ? AND deleted_at IS NULL";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$messageId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public function delete($messageId)
    {
        // Soft delete - chỉ set deleted_at
        $sql = "UPDATE messages SET deleted_at = NOW() WHERE message_id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$messageId]);
    }
}
