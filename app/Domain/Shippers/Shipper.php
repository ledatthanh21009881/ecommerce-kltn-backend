<?php
declare(strict_types=1);

namespace App\Domain\Shippers;

use App\Core\Model;
use PDO;

class Shipper extends Model
{
    protected string $table = 'shippers';
    protected string $primaryKey = 'user_id';
    
    protected array $fillable = [
        'user_id',
        'vehicle_info',
        'rating',
        'on_time_delivery_pct',
        'last_delivery_at',
        'total_delivered',
        'note',
        'created_at',
        'updated_at',
        'is_available',
        'status'
    ];

    public function getAll(): array
    {
        $sql = "
            SELECT 
                s.*,
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
            FROM {$this->table} s
            LEFT JOIN users u ON s.user_id = u.user_id
            LEFT JOIN accounts a ON u.account_id = a.account_id
            ORDER BY s.created_at DESC
        ";
        
        $stmt = $this->getConnection()->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllWithDetails(array $filters = [], int $limit = 20, int $offset = 0): array
    {
        $sql = "
            SELECT 
                s.*,
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
                (SELECT COUNT(*) FROM shipping_tracking st WHERE st.shipper_id = s.user_id) as total_assignments,
                (SELECT COUNT(*) FROM shipping_tracking st WHERE st.shipper_id = s.user_id AND st.confirmed_delivery_at IS NOT NULL) as completed_deliveries,
                (SELECT AVG(sp.avg_rating) FROM shipper_performance sp WHERE sp.shipper_id = s.user_id) as avg_performance_rating
            FROM {$this->table} s
            LEFT JOIN users u ON s.user_id = u.user_id
            LEFT JOIN accounts a ON u.account_id = a.account_id
        ";
        
        $whereConditions = [];
        $params = [];
        
        if (!empty($filters['search'])) {
            $whereConditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR s.vehicle_info LIKE ?)";
            $searchTerm = "%{$filters['search']}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if (!empty($filters['status'])) {
            $whereConditions[] = "s.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['is_available'])) {
            $whereConditions[] = "s.is_available = ?";
            $params[] = $filters['is_available'] ? 1 : 0;
        }
        
        if (!empty($filters['min_rating'])) {
            $whereConditions[] = "s.rating >= ?";
            $params[] = $filters['min_rating'];
        }
        
        if (!empty($filters['min_deliveries'])) {
            $whereConditions[] = "s.total_delivered >= ?";
            $params[] = $filters['min_deliveries'];
        }
        
        if (!empty($whereConditions)) {
            $sql .= " WHERE " . implode(" AND ", $whereConditions);
        }
        
        $sql .= " ORDER BY s.created_at DESC LIMIT ? OFFSET ?";
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
                s.*,
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
            FROM {$this->table} s
            LEFT JOIN users u ON s.user_id = u.user_id
            LEFT JOIN accounts a ON u.account_id = a.account_id
            WHERE s.user_id = ?
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
                s.*,
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
            FROM {$this->table} s
            LEFT JOIN users u ON s.user_id = u.user_id
            LEFT JOIN accounts a ON u.account_id = a.account_id
            WHERE u.email = ?
        ";
        
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute([$email]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function getAvailableShippers(): array
    {
        $sql = "
            SELECT 
                s.*,
                u.first_name,
                u.last_name,
                u.email,
                u.phone
            FROM {$this->table} s
            LEFT JOIN users u ON s.user_id = u.user_id
            WHERE s.is_available = 1 AND s.status = 'active'
            ORDER BY s.rating DESC, s.on_time_delivery_pct DESC
        ";
        
        $stmt = $this->getConnection()->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCount(array $filters = []): int
    {
        $sql = "
            SELECT COUNT(*) 
            FROM {$this->table} s
            LEFT JOIN users u ON s.user_id = u.user_id
            LEFT JOIN accounts a ON u.account_id = a.account_id
        ";
        
        $whereConditions = [];
        $params = [];
        
        if (!empty($filters['search'])) {
            $whereConditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR s.vehicle_info LIKE ?)";
            $searchTerm = "%{$filters['search']}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if (!empty($filters['status'])) {
            $whereConditions[] = "s.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['is_available'])) {
            $whereConditions[] = "s.is_available = ?";
            $params[] = $filters['is_available'] ? 1 : 0;
        }
        
        if (!empty($filters['min_rating'])) {
            $whereConditions[] = "s.rating >= ?";
            $params[] = $filters['min_rating'];
        }
        
        if (!empty($filters['min_deliveries'])) {
            $whereConditions[] = "s.total_delivered >= ?";
            $params[] = $filters['min_deliveries'];
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
            
            // Assign shipper role
            $this->assignRole($userId, 3); // 3 = shipper role
            
            // Create shipper record
            $shipperData = [
                'user_id' => $userId,
                'vehicle_info' => $data['vehicle_info'] ?? null,
                'rating' => 0.00,
                'on_time_delivery_pct' => 0.00,
                'total_delivered' => 0,
                'note' => $data['note'] ?? null,
                'is_available' => 1,
                'status' => 'active'
            ];
            
            $fields = array_keys($shipperData);
            $placeholders = str_repeat('?,', count($fields) - 1) . '?';
            
            $sql = "INSERT INTO {$this->table} (" . implode(',', $fields) . ") VALUES ($placeholders)";
            
            $stmt = $this->getConnection()->prepare($sql);
            $stmt->execute(array_values($shipperData));
            
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
            // Update shipper record
            $shipperFields = [];
            $shipperParams = [];
            
            foreach (['vehicle_info', 'rating', 'on_time_delivery_pct', 'total_delivered', 'note', 'is_available', 'status'] as $field) {
                if (isset($data[$field])) {
                    $shipperFields[] = "$field = ?";
                    $shipperParams[] = $data[$field];
                }
            }
            
            if (!empty($shipperFields)) {
                $shipperParams[] = $id;
                $sql = "UPDATE {$this->table} SET " . implode(', ', $shipperFields) . " WHERE {$this->primaryKey} = ?";
                $stmt = $this->getConnection()->prepare($sql);
                $stmt->execute($shipperParams);
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
            
            // Delete shipper record
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

    public function updateRating(int $userId, float $rating): bool
    {
        $sql = "UPDATE {$this->table} SET rating = ? WHERE user_id = ?";
        $stmt = $this->getConnection()->prepare($sql);
        return $stmt->execute([$rating, $userId]);
    }

    public function updateDeliveryStats(int $userId, int $totalDelivered, float $onTimePercentage): bool
    {
        $sql = "UPDATE {$this->table} SET total_delivered = ?, on_time_delivery_pct = ?, last_delivery_at = NOW() WHERE user_id = ?";
        $stmt = $this->getConnection()->prepare($sql);
        return $stmt->execute([$totalDelivered, $onTimePercentage, $userId]);
    }

    public function setAvailability(int $userId, bool $isAvailable): bool
    {
        $sql = "UPDATE {$this->table} SET is_available = ? WHERE user_id = ?";
        $stmt = $this->getConnection()->prepare($sql);
        return $stmt->execute([$isAvailable ? 1 : 0, $userId]);
    }

    public function getShipperDeliveries(int $userId, int $limit = 10, int $offset = 0): array
    {
        $sql = "
            SELECT 
                st.*,
                o.order_id,
                o.total_amount,
                o.status as order_status,
                o.created_at as order_created_at
            FROM shipping_tracking st
            LEFT JOIN orders o ON st.order_id = o.order_id
            WHERE st.shipper_id = ?
            ORDER BY st.last_updated DESC
            LIMIT ? OFFSET ?
        ";
        
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute([$userId, $limit, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getShipperPerformance(int $userId, string $startDate, string $endDate): array
    {
        $sql = "
            SELECT 
                sp.*
            FROM shipper_performance sp
            WHERE sp.shipper_id = ? 
                AND sp.period_start_date >= ? 
                AND sp.period_end_date <= ?
            ORDER BY sp.period_start_date DESC
        ";
        
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute([$userId, $startDate, $endDate]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTopShippers(int $limit = 10): array
    {
        $sql = "
            SELECT 
                s.*,
                u.first_name,
                u.last_name,
                u.email,
                u.phone
            FROM {$this->table} s
            LEFT JOIN users u ON s.user_id = u.user_id
            WHERE s.status = 'active'
            ORDER BY s.rating DESC, s.on_time_delivery_pct DESC, s.total_delivered DESC
            LIMIT ?
        ";
        
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getShipperStatistics(): array
    {
        $sql = "
            SELECT 
                COUNT(*) as total_shippers,
                COUNT(CASE WHEN is_available = 1 THEN 1 END) as available_shippers,
                COUNT(CASE WHEN status = 'active' THEN 1 END) as active_shippers,
                AVG(rating) as avg_rating,
                AVG(on_time_delivery_pct) as avg_on_time_pct,
                SUM(total_delivered) as total_deliveries
            FROM {$this->table}
        ";
        
        $stmt = $this->getConnection()->query($sql);
        return $stmt->fetch(PDO::FETCH_ASSOC);
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
