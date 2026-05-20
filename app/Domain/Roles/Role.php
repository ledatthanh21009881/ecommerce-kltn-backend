<?php
declare(strict_types=1);

namespace App\Domain\Roles;

use App\Core\Model;
use App\Support\PanelRole;
use PDO;
use Exception;

class Role extends Model
{
    protected string $table = 'roles';
    protected string $primaryKey = 'role_id';
    
    protected array $fillable = [
        'role_name'
    ];

    /**
     * Lấy tất cả roles với phân trang
     */
    public function getAllWithPagination(array $filters = [], int $limit = 20, int $offset = 0): array
    {
        $whereConditions = [];
        $params = [];

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $whereConditions[] = "role_name LIKE ?";
            $params[] = $search;
        }

        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        $sql = "
            SELECT 
                r.*,
                COUNT(ur.user_id) as user_count
            FROM roles r
            LEFT JOIN user_roles ur ON r.role_id = ur.role_id
            {$whereClause}
            GROUP BY r.role_id
            ORDER BY r.role_id DESC
            LIMIT ? OFFSET ?
        ";

        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lấy role theo ID với thông tin chi tiết
     */
    public function findByIdWithDetails(int $roleId): ?array
    {
        $sql = "
            SELECT 
                r.*,
                COUNT(ur.user_id) as user_count
            FROM roles r
            LEFT JOIN user_roles ur ON r.role_id = ur.role_id
            WHERE r.role_id = ?
            GROUP BY r.role_id
        ";

        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute([$roleId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    /**
     * Tạo role mới
     */
    public function create(array $data): int
    {
        // Kiểm tra role_name unique
        if ($this->isRoleNameExists($data['role_name'])) {
            throw new Exception('Role name already exists');
        }

        $fields = array_keys($data);
        $placeholders = str_repeat('?,', count($fields) - 1) . '?';
        $sql = "INSERT INTO {$this->table} (" . implode(',', $fields) . ") VALUES ($placeholders)";
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute(array_values($data));
        return (int) $this->getConnection()->lastInsertId();
    }

    /**
     * Cập nhật role
     */
    public function update(int $roleId, array $data): bool
    {
        // Kiểm tra role_name unique (trừ role hiện tại)
        if (isset($data['role_name']) && $this->isRoleNameExists($data['role_name'], $roleId)) {
            throw new Exception('Role name already exists');
        }

        $fields = array_keys($data);
        $setClause = implode('=?,', $fields) . '=?';
        $sql = "UPDATE {$this->table} SET {$setClause} WHERE role_id = ?";
        $params = array_values($data);
        $params[] = $roleId;
        $stmt = $this->getConnection()->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Xóa role
     */
    public function delete(int $roleId): bool
    {
        // Kiểm tra xem role có được gán cho user nào không
        if ($this->hasAssignedUsers($roleId)) {
            throw new Exception('Cannot delete role that has assigned users');
        }

        $sql = "DELETE FROM {$this->table} WHERE role_id = ?";
        $stmt = $this->getConnection()->prepare($sql);
        return $stmt->execute([$roleId]);
    }

    /**
     * Lấy tất cả roles (cho dropdown)
     */
    public function getAll(): array
    {
        $sql = "SELECT role_id, role_name FROM {$this->table} ORDER BY role_name";
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Roles panel (không gồm customer/shipper) có phân trang.
     */
    public function getPanelRolesWithPagination(?string $search, int $limit, int $offset): array
    {
        $externalPh = PanelRole::externalPlaceholders();
        $where = "WHERE LOWER(r.role_name) NOT IN ({$externalPh})";
        $params = PanelRole::EXTERNAL;

        if ($search !== null && $search !== '') {
            $where .= ' AND r.role_name LIKE ?';
            $params[] = '%' . $search . '%';
        }

        $sql = "
            SELECT r.role_id, r.role_name, COUNT(ur.user_id) AS user_count
            FROM {$this->table} r
            LEFT JOIN user_roles ur ON r.role_id = ur.role_id
            {$where}
            GROUP BY r.role_id, r.role_name
            ORDER BY r.role_name ASC
            LIMIT ? OFFSET ?
        ";
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPanelRoleCount(?string $search): int
    {
        $externalPh = PanelRole::externalPlaceholders();
        $where = "WHERE LOWER(role_name) NOT IN ({$externalPh})";
        $params = PanelRole::EXTERNAL;

        if ($search !== null && $search !== '') {
            $where .= ' AND role_name LIKE ?';
            $params[] = '%' . $search . '%';
        }

        $sql = "SELECT COUNT(*) AS count FROM {$this->table} {$where}";
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int) ($result['count'] ?? 0);
    }

    public function findById(int $roleId): ?array
    {
        $sql = "SELECT role_id, role_name FROM {$this->table} WHERE role_id = ?";
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute([$roleId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
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
    public function assignRolesToUser(int $userId, array $roleIds): bool
    {
        $this->getConnection()->beginTransaction();
        try {
            // Xóa roles cũ
            $sql = "DELETE FROM user_roles WHERE user_id = ?";
            $stmt = $this->getConnection()->prepare($sql);
            $stmt->execute([$userId]);

            // Gán roles mới
            foreach ($roleIds as $roleId) {
                $sql = "INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)";
                $stmt = $this->getConnection()->prepare($sql);
                $stmt->execute([$userId, $roleId]);
            }

            $this->getConnection()->commit();
            return true;
        } catch (Exception $e) {
            $this->getConnection()->rollBack();
            throw $e;
        }
    }

    /**
     * Xóa role khỏi user
     */
    public function removeRoleFromUser(int $userId, int $roleId): bool
    {
        $sql = "DELETE FROM user_roles WHERE user_id = ? AND role_id = ?";
        $stmt = $this->getConnection()->prepare($sql);
        return $stmt->execute([$userId, $roleId]);
    }

    /**
     * Đếm tổng số roles
     */
    public function getCount(array $filters = []): int
    {
        $whereConditions = [];
        $params = [];

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $whereConditions[] = "role_name LIKE ?";
            $params[] = $search;
        }

        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        $sql = "SELECT COUNT(*) as count FROM {$this->table} {$whereClause}";
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) $result['count'];
    }

    /**
     * Kiểm tra role_name đã tồn tại chưa
     */
    private function isRoleNameExists(string $roleName, ?int $excludeRoleId = null): bool
    {
        $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE role_name = ?";
        $params = [$roleName];

        if ($excludeRoleId) {
            $sql .= " AND role_id != ?";
            $params[] = $excludeRoleId;
        }

        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) $result['count'] > 0;
    }

    /**
     * Kiểm tra role có user nào được gán không
     */
    private function hasAssignedUsers(int $roleId): bool
    {
        $sql = "SELECT COUNT(*) as count FROM user_roles WHERE role_id = ?";
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute([$roleId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) $result['count'] > 0;
    }
}
