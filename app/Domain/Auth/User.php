<?php
declare(strict_types=1);

namespace App\Domain\Auth;

use App\Core\Model;

class User extends Model
{
    protected string $table = 'users';
    protected string $primaryKey = 'user_id';
    
    protected array $fillable = [
        'account_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'gender',
        'birthdate',
        'avatar_url'
    ];
    
    protected array $hidden = [];
    
    public function findByEmail(string $email): ?array
    {
        return $this->findBy('email', $email);
    }
    
    public function findByAccountId(int $accountId): ?array
    {
        return $this->findBy('account_id', $accountId);
    }
    
    public function getUserWithAccount(int $userId): ?array
    {
        $sql = "SELECT u.*, a.account_name, a.last_login_at, a.is_active as account_active
                FROM users u 
                JOIN accounts a ON u.account_id = a.account_id 
                WHERE u.user_id = ?";
        
        $stmt = $this->query($sql, [$userId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }
    
    public function getUserWithRoles(int $userId): ?array
    {
        $sql = "SELECT u.*, a.account_name, GROUP_CONCAT(r.role_name) as roles
                FROM users u 
                JOIN accounts a ON u.account_id = a.account_id 
                LEFT JOIN user_roles ur ON u.user_id = ur.user_id
                LEFT JOIN roles r ON ur.role_id = r.role_id
                WHERE u.user_id = ?
                GROUP BY u.user_id";
        
        $stmt = $this->query($sql, [$userId]);
        $result = $stmt->fetch();
        
        if ($result) {
            $result['roles'] = $result['roles'] ? explode(',', $result['roles']) : [];
        }
        
        return $result ?: null;
    }
    
    public function hasRole(int $userId, string $roleName): bool
    {
        $sql = "SELECT COUNT(*) as count 
                FROM user_roles ur 
                JOIN roles r ON ur.role_id = r.role_id 
                WHERE ur.user_id = ? AND r.role_name = ?";
        
        $stmt = $this->query($sql, [$userId, $roleName]);
        $result = $stmt->fetch();
        
        return $result['count'] > 0;
    }
    
    public function isAdmin(int $userId): bool
    {
        return $this->hasRole($userId, 'admin');
    }
    
    public function isCustomer(int $userId): bool
    {
        return $this->hasRole($userId, 'customer');
    }
    
    public function isShipper(int $userId): bool
    {
        return $this->hasRole($userId, 'shipper');
    }
    
    public function getCustomerInfo(int $userId): ?array
    {
        $sql = "SELECT u.*, c.loyalty_points, c.total_orders, c.note as customer_note
                FROM users u 
                JOIN customers c ON u.user_id = c.user_id 
                WHERE u.user_id = ?";
        
        $stmt = $this->query($sql, [$userId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }
    
    public function getShipperInfo(int $userId): ?array
    {
        $sql = "SELECT u.*, s.vehicle_info, s.rating, s.on_time_delivery_pct, 
                       s.total_delivered, s.is_available, s.status as shipper_status
                FROM users u 
                JOIN shippers s ON u.user_id = s.user_id 
                WHERE u.user_id = ?";
        
        $stmt = $this->query($sql, [$userId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }
    
    public function getAddresses(int $userId): array
    {
        $sql = "SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, created_at DESC";
        $stmt = $this->query($sql, [$userId]);
        return $stmt->fetchAll();
    }
    
    public function getDefaultAddress(int $userId): ?array
    {
        $sql = "SELECT * FROM addresses WHERE user_id = ? AND is_default = 1 LIMIT 1";
        $stmt = $this->query($sql, [$userId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }
}
