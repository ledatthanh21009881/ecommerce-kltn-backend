<?php
declare(strict_types=1);

namespace App\Domain\Customers;

use App\Core\Model;
use PDO;

class Customer extends Model
{
    protected string $table = 'customers';
    protected string $primaryKey = 'user_id';
    
    protected array $fillable = [
        'user_id',
        'loyalty_points',
        'total_orders',
        'note',
        'created_at',
        'updated_at'
    ];

    public function getAll(): array
    {
        $sql = "
            SELECT 
                c.*,
                u.first_name,
                u.last_name,
                u.email,
                u.phone,
                u.gender,
                u.birthdate,
                u.avatar_url,
                a.account_name,
                a.last_login_at,
                a.is_active
            FROM {$this->table} c
            LEFT JOIN users u ON c.user_id = u.user_id
            LEFT JOIN accounts a ON u.account_id = a.account_id
            ORDER BY c.created_at DESC
        ";
        
        $stmt = $this->getConnection()->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllWithDetails(array $filters = [], int $limit = 20, int $offset = 0): array
    {
        $sql = "
            SELECT 
                c.*,
                u.first_name,
                u.last_name,
                u.email,
                u.phone,
                u.gender,
                u.birthdate,
                u.avatar_url,
                a.account_name,
                a.last_login_at,
                a.is_active,
                a.created_at as account_created_at,
                (SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.user_id) as total_orders_count,
                (SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.user_id AND o.status = 'completed') as completed_orders_count,
                (SELECT SUM(o.total_amount) FROM orders o WHERE o.customer_id = c.user_id AND o.status = 'completed') as total_spent
            FROM {$this->table} c
            LEFT JOIN users u ON c.user_id = u.user_id
            LEFT JOIN accounts a ON u.account_id = a.account_id
        ";
        
        $whereConditions = [];
        $params = [];
        
        if (!empty($filters['search'])) {
            $whereConditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
            $searchTerm = "%{$filters['search']}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if (!empty($filters['status'])) {
            $whereConditions[] = "a.is_active = ?";
            $params[] = $filters['status'] === 'active' ? 1 : 0;
        }
        
        if (!empty($filters['gender'])) {
            $whereConditions[] = "u.gender = ?";
            $params[] = $filters['gender'];
        }
        
        if (!empty($filters['min_orders'])) {
            $whereConditions[] = "c.total_orders >= ?";
            $params[] = $filters['min_orders'];
        }
        
        if (!empty($filters['min_points'])) {
            $whereConditions[] = "c.loyalty_points >= ?";
            $params[] = $filters['min_points'];
        }
        
        if (!empty($whereConditions)) {
            $sql .= " WHERE " . implode(" AND ", $whereConditions);
        }
        
        $sql .= " ORDER BY c.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array
    {
        $sql = "
            SELECT 
                c.*,
                u.first_name,
                u.last_name,
                u.email,
                u.phone,
                u.gender,
                u.birthdate,
                u.avatar_url,
                a.account_name,
                a.last_login_at,
                a.is_active,
                a.created_at as account_created_at
            FROM {$this->table} c
            LEFT JOIN users u ON c.user_id = u.user_id
            LEFT JOIN accounts a ON u.account_id = a.account_id
            WHERE c.user_id = ?
        ";
        
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $sql = "
            SELECT 
                c.*,
                u.first_name,
                u.last_name,
                u.email,
                u.phone,
                u.gender,
                u.birthdate,
                u.avatar_url,
                a.account_name,
                a.last_login_at,
                a.is_active
            FROM {$this->table} c
            LEFT JOIN users u ON c.user_id = u.user_id
            LEFT JOIN accounts a ON u.account_id = a.account_id
            WHERE u.email = ?
        ";
        
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute([$email]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function getCount(array $filters = []): int
    {
        $sql = "
            SELECT COUNT(*) 
            FROM {$this->table} c
            LEFT JOIN users u ON c.user_id = u.user_id
            LEFT JOIN accounts a ON u.account_id = a.account_id
        ";
        
        $whereConditions = [];
        $params = [];
        
        if (!empty($filters['search'])) {
            $whereConditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
            $searchTerm = "%{$filters['search']}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if (!empty($filters['status'])) {
            $whereConditions[] = "a.is_active = ?";
            $params[] = $filters['status'] === 'active' ? 1 : 0;
        }
        
        if (!empty($filters['gender'])) {
            $whereConditions[] = "u.gender = ?";
            $params[] = $filters['gender'];
        }
        
        if (!empty($filters['min_orders'])) {
            $whereConditions[] = "c.total_orders >= ?";
            $params[] = $filters['min_orders'];
        }
        
        if (!empty($filters['min_points'])) {
            $whereConditions[] = "c.loyalty_points >= ?";
            $params[] = $filters['min_points'];
        }
        
        if (!empty($whereConditions)) {
            $sql .= " WHERE " . implode(" AND ", $whereConditions);
        }
        
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function create(array $data): int
    {
        $this->getConnection()->beginTransaction();
        
        try {
            // Create account first
            $accountData = [
                'account_name' => $data['email'],
                'password' => password_hash($data['password'], PASSWORD_DEFAULT),
                'account_type' => 'local',
                'is_active' => 1
            ];
            
            $accountId = $this->createAccount($accountData);
            
            // Create user
            $userData = [
                'account_id' => $accountId,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'gender' => $data['gender'] ?? 'other',
                'birthdate' => $data['birthdate'] ?? null
            ];
            
            $userId = $this->createUser($userData);
            
            // Assign customer role
            $this->assignRole($userId, 2); // 2 = customer role
            
            // Create customer record
            $customerData = [
                'user_id' => $userId,
                'loyalty_points' => 0,
                'total_orders' => 0,
                'note' => $data['note'] ?? null
            ];
            
            $fields = array_keys($customerData);
            $placeholders = str_repeat('?,', count($fields) - 1) . '?';
            
            $sql = "INSERT INTO {$this->table} (" . implode(',', $fields) . ") VALUES ($placeholders)";
            
            $stmt = $this->getConnection()->prepare($sql);
            $stmt->execute(array_values($customerData));
            
            $this->getConnection()->commit();
            return $userId;
        } catch (Exception $e) {
            $this->getConnection()->rollBack();
            throw $e;
        }
    }

    public function update(int $id, array $data): bool
    {
        $this->getConnection()->beginTransaction();
        
        try {
            // Update customer record
            $customerFields = [];
            $customerParams = [];
            
            foreach (['loyalty_points', 'total_orders', 'note'] as $field) {
                if (isset($data[$field])) {
                    $customerFields[] = "$field = ?";
                    $customerParams[] = $data[$field];
                }
            }
            
            if (!empty($customerFields)) {
                $customerParams[] = $id;
                $sql = "UPDATE {$this->table} SET " . implode(', ', $customerFields) . " WHERE {$this->primaryKey} = ?";
                $stmt = $this->getConnection()->prepare($sql);
                $stmt->execute($customerParams);
            }
            
            // Update user record
            $userFields = [];
            $userParams = [];
            
            foreach (['first_name', 'last_name', 'email', 'phone', 'gender', 'birthdate'] as $field) {
                if (isset($data[$field])) {
                    $userFields[] = "$field = ?";
                    $userParams[] = $data[$field];
                }
            }
            
            if (!empty($userFields)) {
                $userParams[] = $id;
                $sql = "UPDATE users SET " . implode(', ', $userFields) . " WHERE user_id = ?";
                $stmt = $this->getConnection()->prepare($sql);
                $stmt->execute($userParams);
            }
            
            // Update account if needed
            if (isset($data['is_active'])) {
                $accountId = $this->getAccountIdByUserId($id);
                if ($accountId) {
                    $sql = "UPDATE accounts SET is_active = ? WHERE account_id = ?";
                    $stmt = $this->getConnection()->prepare($sql);
                    $stmt->execute([$data['is_active'] ? 1 : 0, $accountId]);
                }
            }
            
            $this->getConnection()->commit();
            return true;
        } catch (Exception $e) {
            $this->getConnection()->rollBack();
            throw $e;
        }
    }

    public function delete(int $id): bool
    {
        $this->getConnection()->beginTransaction();
        
        try {
            // Get account_id first
            $accountId = $this->getAccountIdByUserId($id);
            
            // Delete customer record
            $stmt = $this->getConnection()->prepare("DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?");
            $stmt->execute([$id]);
            
            // Delete user record
            $stmt = $this->getConnection()->prepare("DELETE FROM users WHERE user_id = ?");
            $stmt->execute([$id]);
            
            // Delete account record
            if ($accountId) {
                $stmt = $this->getConnection()->prepare("DELETE FROM accounts WHERE account_id = ?");
                $stmt->execute([$accountId]);
            }
            
            $this->getConnection()->commit();
            return true;
        } catch (Exception $e) {
            $this->getConnection()->rollBack();
            throw $e;
        }
    }

    public function updateLoyaltyPoints(int $userId, int $points): bool
    {
        $sql = "UPDATE {$this->table} SET loyalty_points = loyalty_points + ? WHERE user_id = ?";
        $stmt = $this->getConnection()->prepare($sql);
        return $stmt->execute([$points, $userId]);
    }

    public function incrementTotalOrders(int $userId): bool
    {
        $sql = "UPDATE {$this->table} SET total_orders = total_orders + 1 WHERE user_id = ?";
        $stmt = $this->getConnection()->prepare($sql);
        return $stmt->execute([$userId]);
    }

    public function getCustomerOrders(int $userId, int $limit = 10, int $offset = 0): array
    {
        $sql = "
            SELECT 
                o.*,
                COUNT(oi.item_id) as total_items
            FROM orders o
            LEFT JOIN order_items oi ON o.order_id = oi.order_id
            WHERE o.customer_id = ?
            GROUP BY o.order_id
            ORDER BY o.created_at DESC
            LIMIT ? OFFSET ?
        ";
        
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute([$userId, $limit, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCustomerAddresses(int $userId): array
    {
        $sql = "SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, address_id ASC";
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTopCustomers(int $limit = 10): array
    {
        $sql = "
            SELECT 
                c.*,
                u.first_name,
                u.last_name,
                u.email,
                (SELECT SUM(o.total_amount) FROM orders o WHERE o.customer_id = c.user_id AND o.status = 'completed') as total_spent
            FROM {$this->table} c
            LEFT JOIN users u ON c.user_id = u.user_id
            ORDER BY total_spent DESC
            LIMIT ?
        ";
        
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function createAccount(array $data): int
    {
        $fields = array_keys($data);
        $placeholders = str_repeat('?,', count($fields) - 1) . '?';
        
        $sql = "INSERT INTO accounts (" . implode(',', $fields) . ") VALUES ($placeholders)";
        
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute(array_values($data));
        
        return (int) $this->getConnection()->lastInsertId();
    }

    private function createUser(array $data): int
    {
        $fields = array_keys($data);
        $placeholders = str_repeat('?,', count($fields) - 1) . '?';
        
        $sql = "INSERT INTO users (" . implode(',', $fields) . ") VALUES ($placeholders)";
        
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute(array_values($data));
        
        return (int) $this->getConnection()->lastInsertId();
    }

    private function assignRole(int $userId, int $roleId): bool
    {
        $sql = "INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)";
        $stmt = $this->getConnection()->prepare($sql);
        return $stmt->execute([$userId, $roleId]);
    }

    private function getAccountIdByUserId(int $userId): ?int
    {
        $sql = "SELECT account_id FROM users WHERE user_id = ?";
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? (int) $result['account_id'] : null;
    }
}
