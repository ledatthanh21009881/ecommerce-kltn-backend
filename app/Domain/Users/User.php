<?php
declare(strict_types=1);

namespace App\Domain\Users;

use App\Core\Model;
use PDO;
use Exception;

class User extends Model
{
    protected string $table = 'users';
    protected string $primaryKey = 'user_id';
    
    protected array $fillable = [
        'account_id', 'first_name', 'last_name', 'email', 'phone', 
        'gender', 'birthdate', 'avatar_url'
    ];

    /**
     * Lấy tất cả users với thông tin chi tiết
     */
    public function getAllWithDetails(array $filters = [], int $limit = 20, int $offset = 0): array
    {
        $whereConditions = [];
        $params = [];

        // Xử lý filters
        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $whereConditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR a.account_name LIKE ?)";
            $params = array_merge($params, [$search, $search, $search, $search]);
        }

        if (!empty($filters['role'])) {
            $whereConditions[] = "r.role_name = ?";
            $params[] = $filters['role'];
        }

        if (isset($filters['is_active'])) {
            $whereConditions[] = "a.is_active = ?";
            $params[] = $filters['is_active'] ? 1 : 0;
        }

        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        $sql = "
            SELECT 
                u.*,
                a.account_name,
                a.last_login_at,
                a.failed_attempts,
                a.locked_until,
                a.two_fa_enabled,
                a.is_active,
                GROUP_CONCAT(r.role_name) as roles
            FROM users u
            LEFT JOIN accounts a ON u.account_id = a.account_id
            LEFT JOIN user_roles ur ON u.user_id = ur.user_id
            LEFT JOIN roles r ON ur.role_id = r.role_id
            {$whereClause}
            GROUP BY u.user_id
            ORDER BY u.user_id DESC
            LIMIT ? OFFSET ?
        ";

        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lấy user theo ID
     */
    public function findById(int $userId): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE user_id = ?";
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Lấy user theo ID với thông tin chi tiết
     */
    public function findByIdWithDetails(int $userId): ?array
    {
        $sql = "
            SELECT 
                u.*,
                a.account_name,
                a.last_login_at,
                a.failed_attempts,
                a.locked_until,
                a.two_fa_enabled,
                a.is_active,
                GROUP_CONCAT(r.role_name) as roles
            FROM users u
            LEFT JOIN accounts a ON u.account_id = a.account_id
            LEFT JOIN user_roles ur ON u.user_id = ur.user_id
            LEFT JOIN roles r ON ur.role_id = r.role_id
            WHERE u.user_id = ?
            GROUP BY u.user_id
        ";

        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    /**
     * Tạo user mới
     */
    public function create(array $data): int
    {
        $this->getConnection()->beginTransaction();
        try {
            // Tạo account
            $accountData = [
                'account_name' => $data['account_name'],
                'password' => password_hash($data['password'], PASSWORD_DEFAULT),
                'account_type' => 'local',
                'is_active' => $data['is_active'] ?? 1
            ];
            $accountId = $this->createAccount($accountData);

            // Tạo user
            $userData = [
                'account_id' => $accountId,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'gender' => $data['gender'] ?? 'other',
                'birthdate' => $data['birthdate'] ?? null,
                'avatar_url' => $data['avatar_url'] ?? null
            ];

            $fields = array_keys($userData);
            $placeholders = str_repeat('?,', count($fields) - 1) . '?';
            $sql = "INSERT INTO {$this->table} (" . implode(',', $fields) . ") VALUES ($placeholders)";
            $stmt = $this->getConnection()->prepare($sql);
            $stmt->execute(array_values($userData));
            $userId = (int) $this->getConnection()->lastInsertId();

            // Gán roles
            if (!empty($data['role_ids'])) {
                foreach ($data['role_ids'] as $roleId) {
                    $this->assignRole($userId, (int) $roleId);
                }
            }

            $this->getConnection()->commit();
            return $userId;
        } catch (Exception $e) {
            $this->getConnection()->rollBack();
            throw $e;
        }
    }

    /**
     * Cập nhật user
     */
    public function update(int $userId, array $data): bool
    {
        $this->getConnection()->beginTransaction();
        try {
            // Cập nhật user
            $userData = array_intersect_key($data, array_flip($this->fillable));
            if (!empty($userData)) {
                $fields = array_keys($userData);
                $setClause = implode('=?,', $fields) . '=?';
                $sql = "UPDATE {$this->table} SET {$setClause} WHERE user_id = ?";
                $params = array_values($userData);
                $params[] = $userId;
                $stmt = $this->getConnection()->prepare($sql);
                $stmt->execute($params);
            }

            // Cập nhật account nếu có
            if (isset($data['is_active'])) {
                $user = $this->findById($userId);
                if ($user) {
                    $sql = "UPDATE accounts SET is_active = ? WHERE account_id = ?";
                    $stmt = $this->getConnection()->prepare($sql);
                    $stmt->execute([$data['is_active'] ? 1 : 0, $user['account_id']]);
                }
            }

            // Cập nhật roles nếu có
            if (isset($data['role_ids'])) {
                // Xóa roles cũ
                $sql = "DELETE FROM user_roles WHERE user_id = ?";
                $stmt = $this->getConnection()->prepare($sql);
                $stmt->execute([$userId]);

                // Gán roles mới
                foreach ($data['role_ids'] as $roleId) {
                    $this->assignRole($userId, $roleId);
                }
            }

            $this->getConnection()->commit();
            return true;
        } catch (Exception $e) {
            $this->getConnection()->rollBack();
            throw $e;
        }
    }

    /**
     * Xóa user
     */
    public function delete(int $userId): bool
    {
        $this->getConnection()->beginTransaction();
        try {
            $user = $this->findById($userId);
            if (!$user) {
                throw new Exception('User not found');
            }

            // Xóa user_roles
            $sql = "DELETE FROM user_roles WHERE user_id = ?";
            $stmt = $this->getConnection()->prepare($sql);
            $stmt->execute([$userId]);

            // Xóa user
            $sql = "DELETE FROM {$this->table} WHERE user_id = ?";
            $stmt = $this->getConnection()->prepare($sql);
            $stmt->execute([$userId]);

            // Xóa account
            $sql = "DELETE FROM accounts WHERE account_id = ?";
            $stmt = $this->getConnection()->prepare($sql);
            $stmt->execute([$user['account_id']]);

            $this->getConnection()->commit();
            return true;
        } catch (Exception $e) {
            $this->getConnection()->rollBack();
            throw $e;
        }
    }

    /**
     * Khóa/Mở khóa user
     */
    public function toggleLock(int $userId, ?string $lockedUntil = null): bool
    {
        $user = $this->findById($userId);
        if (!$user) {
            throw new Exception('User not found');
        }

        $sql = "UPDATE accounts SET locked_until = ? WHERE account_id = ?";
        $stmt = $this->getConnection()->prepare($sql);
        return $stmt->execute([$lockedUntil, $user['account_id']]);
    }

    /**
     * Bật/Tắt 2FA
     */
    public function toggle2FA(int $userId, bool $enabled, ?string $secret = null): bool
    {
        $user = $this->findById($userId);
        if (!$user) {
            throw new Exception('User not found');
        }

        $sql = "UPDATE accounts SET two_fa_enabled = ?, two_fa_secret = ? WHERE account_id = ?";
        $stmt = $this->getConnection()->prepare($sql);
        return $stmt->execute([$enabled ? 1 : 0, $secret, $user['account_id']]);
    }

    /**
     * Lấy roles của user
     */
    public function getUserRoles(int $userId): array
    {
        $sql = "
            SELECT r.* 
            FROM roles r
            JOIN user_roles ur ON r.role_id = ur.role_id
            WHERE ur.user_id = ?
        ";
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Gán roles cho user
     */
    public function assignRoles(int $userId, array $roleIds): bool
    {
        $this->getConnection()->beginTransaction();
        try {
            // Xóa roles cũ
            $sql = "DELETE FROM user_roles WHERE user_id = ?";
            $stmt = $this->getConnection()->prepare($sql);
            $stmt->execute([$userId]);

            // Gán roles mới
            foreach ($roleIds as $roleId) {
                $this->assignRole($userId, $roleId);
            }

            $this->getConnection()->commit();
            return true;
        } catch (Exception $e) {
            $this->getConnection()->rollBack();
            throw $e;
        }
    }

    /**
     * Lấy thống kê users
     */
    public function getStats(): array
    {
        $sql = "
            SELECT 
                COUNT(DISTINCT u.user_id) as total_users,
                COUNT(DISTINCT CASE WHEN a.is_active = 1 THEN u.user_id END) as active_users,
                COUNT(DISTINCT CASE WHEN r.role_name = 'admin' THEN u.user_id END) as admins,
                COUNT(DISTINCT CASE WHEN r.role_name = 'manager' THEN u.user_id END) as managers,
                COUNT(DISTINCT CASE WHEN r.role_name = 'staff' THEN u.user_id END) as staff,
                COUNT(DISTINCT CASE WHEN r.role_name = 'customer' THEN u.user_id END) as customers
            FROM users u
            LEFT JOIN accounts a ON u.account_id = a.account_id
            LEFT JOIN user_roles ur ON u.user_id = ur.user_id
            LEFT JOIN roles r ON ur.role_id = r.role_id
        ";

        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Đếm tổng số users
     */
    public function getCount(array $filters = []): int
    {
        $whereConditions = [];
        $params = [];

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $whereConditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR a.account_name LIKE ?)";
            $params = array_merge($params, [$search, $search, $search, $search]);
        }

        if (!empty($filters['role'])) {
            $whereConditions[] = "r.role_name = ?";
            $params[] = $filters['role'];
        }

        if (isset($filters['is_active'])) {
            $whereConditions[] = "a.is_active = ?";
            $params[] = $filters['is_active'] ? 1 : 0;
        }

        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        $sql = "
            SELECT COUNT(DISTINCT u.user_id) as count
            FROM users u
            LEFT JOIN accounts a ON u.account_id = a.account_id
            LEFT JOIN user_roles ur ON u.user_id = ur.user_id
            LEFT JOIN roles r ON ur.role_id = r.role_id
            {$whereClause}
        ";

        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) $result['count'];
    }

    // Helper methods
    private function createAccount(array $data): int
    {
        $fields = array_keys($data);
        $placeholders = str_repeat('?,', count($fields) - 1) . '?';
        $sql = "INSERT INTO accounts (" . implode(',', $fields) . ") VALUES ($placeholders)";
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute(array_values($data));
        return (int) $this->getConnection()->lastInsertId();
    }

    private function assignRole(int $userId, int $roleId): void
    {
        $sql = "INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)";
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute([$userId, $roleId]);
    }
}
