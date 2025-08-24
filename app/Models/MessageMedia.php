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
            $data['media_public_id'],
            $data['type'],
            json_encode([
                'file_name' => $data['file_name'] ?? null,
                'file_size' => $data['file_size'] ?? null,
                'mime_type' => $data['mime_type'] ?? null
            ]),
            date('Y-m-d H:i:s')
        ]);
        return $this->db->lastInsertId();
    }
}
