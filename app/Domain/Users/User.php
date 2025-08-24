<?php

namespace App\Domain\Users;

use PDO;

class User
{
    protected $table = 'users';
    protected $db;

    public function __construct($container = null)
    {
        if ($container) {
            $this->db = $container->database()->getConnection();
        }
    }

    /**
     * Lấy user theo account_id
     */
    public function getUserByAccountId($accountId)
    {
        $sql = "SELECT * FROM {$this->table} WHERE account_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$accountId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Lấy user theo ID
     */
    public function getUserById($userId)
    {
        $sql = "SELECT * FROM {$this->table} WHERE user_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Lấy tất cả users
     */
    public function getAllUsers()
    {
        $sql = "SELECT * FROM {$this->table} ORDER BY user_id DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
