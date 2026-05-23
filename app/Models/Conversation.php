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
                       CASE
                         WHEN m.message_id IS NULL THEN NULL
                         WHEN NULLIF(TRIM(m.content), '') IS NOT NULL THEN m.content
                         WHEN EXISTS (SELECT 1 FROM message_media mm WHERE mm.message_id = m.message_id) THEN '[Ảnh]'
                         ELSE NULL
                       END as last_message,
                       m.sent_at as last_message_time,
                       (SELECT COUNT(*) FROM messages mu
                        WHERE mu.conversation_id = c.conversation_id
                          AND mu.is_read = 0
                          AND mu.deleted_at IS NULL
                          AND mu.sender_id = c.customer_id) as unread_count
                FROM conversations c
                JOIN users u ON u.user_id = c.customer_id
                LEFT JOIN messages m ON m.message_id = (SELECT message_id FROM messages WHERE conversation_id = c.conversation_id ORDER BY sent_at DESC LIMIT 1)
                ORDER BY c.last_updated_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Admin inbox: ẩn thread riêng khách ↔ shipper (label shipper:{id}:order:{id}).
     */
    public function getConversationsWithLastMessageForAdmin(): array
    {
        $sql = "SELECT c.*, u.first_name, u.last_name, u.email, u.avatar_url,
                       CASE
                         WHEN m.message_id IS NULL THEN NULL
                         WHEN NULLIF(TRIM(m.content), '') IS NOT NULL THEN m.content
                         WHEN EXISTS (SELECT 1 FROM message_media mm WHERE mm.message_id = m.message_id) THEN '[Ảnh]'
                         ELSE NULL
                       END as last_message,
                       m.sent_at as last_message_time,
                       (SELECT COUNT(*) FROM messages mu
                        WHERE mu.conversation_id = c.conversation_id
                          AND mu.is_read = 0
                          AND mu.deleted_at IS NULL
                          AND mu.sender_id = c.customer_id) as unread_count,
                       CASE
                         WHEN EXISTS (SELECT 1 FROM shippers s WHERE s.user_id = c.customer_id) THEN 'shipper'
                         WHEN EXISTS (
                           SELECT 1 FROM user_roles ur
                           JOIN roles r ON r.role_id = ur.role_id
                           WHERE ur.user_id = c.customer_id
                             AND r.role_name NOT IN ('admin', 'customer', 'shipper')
                         ) THEN 'staff'
                         ELSE 'customer'
                       END as user_role
                FROM conversations c
                JOIN users u ON u.user_id = c.customer_id
                LEFT JOIN messages m ON m.message_id = (SELECT message_id FROM messages WHERE conversation_id = c.conversation_id ORDER BY sent_at DESC LIMIT 1)
                WHERE c.label IS NULL OR c.label = '' OR c.label NOT REGEXP '^shipper:[0-9]+:order:[0-9]+$'
                ORDER BY c.last_updated_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Shipper app: only threads where label is shipper:{shipperUserId}:order:{n}
     */
    public function getConversationsWithLastMessageForShipper(int $shipperUserId)
    {
        $labelPrefix = sprintf('shipper:%d:order:', $shipperUserId);
        $sql = "SELECT c.*, u.first_name, u.last_name, u.email, u.avatar_url, 
                       CASE
                         WHEN m.message_id IS NULL THEN NULL
                         WHEN NULLIF(TRIM(m.content), '') IS NOT NULL THEN m.content
                         WHEN EXISTS (SELECT 1 FROM message_media mm WHERE mm.message_id = m.message_id) THEN '[Ảnh]'
                         ELSE NULL
                       END as last_message,
                       m.sent_at as last_message_time,
                       (SELECT COUNT(*) FROM messages mu
                        WHERE mu.conversation_id = c.conversation_id
                          AND mu.is_read = 0
                          AND mu.deleted_at IS NULL
                          AND mu.sender_id = c.customer_id) as unread_count
                FROM conversations c
                JOIN users u ON u.user_id = c.customer_id
                LEFT JOIN messages m ON m.message_id = (SELECT message_id FROM messages WHERE conversation_id = c.conversation_id ORDER BY sent_at DESC LIMIT 1)
                WHERE c.label LIKE ?
                ORDER BY c.last_updated_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$labelPrefix . '%']);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getByCustomerId($customerId)
    {
        $sql = "SELECT * FROM conversations WHERE customer_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$customerId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public function getByConversationId(int $conversationId): ?array
    {
        $sql = "SELECT * FROM conversations WHERE conversation_id = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$conversationId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function getByCustomerAndLabel($customerId, $label)
    {
        $sql = "SELECT * FROM conversations WHERE customer_id = ? AND label = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$customerId, $label]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Atomically load or insert one row per (customer_id, label) so web + shipper always share conversation_id.
     * Uses row lock to reduce duplicate rows when two clients create at once; duplicate key (unique index) is retried via SELECT.
     *
     * @return array<string,mixed> conversation row
     */
    public function findOrCreateByCustomerAndLabel(int $customerId, string $label, string $status = 'open'): array
    {
        $pdo = $this->db;
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM conversations WHERE customer_id = ? AND label = ? LIMIT 1 FOR UPDATE');
            $stmt->execute([$customerId, $label]);
            $existing = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($existing) {
                $pdo->commit();

                return $existing;
            }

            $now = date('Y-m-d H:i:s');
            $ins = $pdo->prepare('INSERT INTO conversations (customer_id, label, status, created_at, last_updated_at) VALUES (?, ?, ?, ?, ?)');
            $ins->execute([$customerId, $label, $status, $now, $now]);
            $newId = (int) $pdo->lastInsertId();
            $pdo->commit();

            $stmt2 = $pdo->prepare('SELECT * FROM conversations WHERE conversation_id = ? LIMIT 1');
            $stmt2->execute([$newId]);
            $row = $stmt2->fetch(\PDO::FETCH_ASSOC);
            if ($row) {
                return $row;
            }

            return [
                'conversation_id' => $newId,
                'customer_id' => $customerId,
                'label' => $label,
                'status' => $status,
                'created_at' => $now,
                'last_updated_at' => $now,
            ];
        } catch (\PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (isset($e->errorInfo[1]) && (int) $e->errorInfo[1] === 1062) {
                $again = $this->getByCustomerAndLabel($customerId, $label);
                if ($again) {
                    return $again;
                }
            }
            throw $e;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
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
