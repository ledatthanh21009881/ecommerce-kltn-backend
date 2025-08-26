<?php

namespace App\Models;

// Custom Model class for PDO

class MessageMedia
{
    protected $table = 'message_media';
    protected $primaryKey = 'media_id';
    protected $db;

    public function __construct($container = null)
    {
        if ($container) {
            $this->db = $container->database()->getConnection();
        }
    }

    public function getByMessageId($messageId)
    {
        $sql = "SELECT * FROM message_media WHERE message_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$messageId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function create($data)
    {
        $sql = "INSERT INTO message_media (message_id, url, public_id, type, metadata, created_at) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['message_id'],
            $data['url'],
            $data['public_id'],
            $data['type'],
            $data['metadata'],
            $data['created_at']
        ]);
        return $this->db->lastInsertId();
    }

    public function deleteByMessageId($messageId)
    {
        $sql = "DELETE FROM message_media WHERE message_id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$messageId]);
    }
}
