<?php
declare(strict_types=1);

namespace App\Domain\Auth;

use App\Core\Model;

class Account extends Model
{
    protected string $table = 'accounts';
    protected string $primaryKey = 'account_id';
    
    protected array $fillable = [
        'account_name',
        'password',
        'account_type',
        'two_fa_enabled',
        'two_fa_secret',
        'is_active'
    ];
    
    protected array $hidden = [
        'password',
        'two_fa_secret',
        'password_reset_token'
    ];
    
    public function findByAccountName(string $accountName): ?array
    {
        return $this->findBy('account_name', $accountName);
    }
    
    public function verifyPassword(string $password, string $hashedPassword): bool
    {
        return password_verify($password, $hashedPassword);
    }
    
    public function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }
    
    public function createAccount(array $data): int
    {
        if (isset($data['password'])) {
            $data['password'] = $this->hashPassword($data['password']);
        }
        
        $data['created_at'] = date('Y-m-d H:i:s');
        
        return $this->create($data);
    }
    
    public function updatePassword(int $accountId, string $newPassword): bool
    {
        $hashedPassword = $this->hashPassword($newPassword);
        $sql = "UPDATE {$this->table} SET password = ?, password_changed_at = NOW() WHERE {$this->primaryKey} = ?";
        $stmt = $this->getConnection()->prepare($sql);
        return $stmt->execute([$hashedPassword, $accountId]);
    }
    
    public function updateLastLogin(int $accountId): bool
    {
        return $this->update($accountId, ['last_login_at' => date('Y-m-d H:i:s')]);
    }
    
    public function incrementFailedAttempts(int $accountId): bool
    {
        // Debug: Make sure table name is set
        if (empty($this->table)) {
            throw new \Exception("Table name is empty in Account model");
        }
        
        $sql = "UPDATE accounts SET failed_attempts = failed_attempts + 1, last_failed_login_at = NOW() WHERE account_id = ?";
        $stmt = $this->getConnection()->prepare($sql);
        return $stmt->execute([$accountId]);
    }
    
    public function resetFailedAttempts(int $accountId): bool
    {
        $sql = "UPDATE {$this->table} SET failed_attempts = 0, last_failed_login_at = NULL, locked_until = NULL WHERE {$this->primaryKey} = ?";
        $stmt = $this->getConnection()->prepare($sql);
        return $stmt->execute([$accountId]);
    }
    
    public function lockAccount(int $accountId, int $lockMinutes = 30): bool
    {
        $lockUntil = date('Y-m-d H:i:s', time() + ($lockMinutes * 60));
        $sql = "UPDATE {$this->table} SET locked_until = ? WHERE {$this->primaryKey} = ?";
        $stmt = $this->getConnection()->prepare($sql);
        return $stmt->execute([$lockUntil, $accountId]);
    }
    
    public function isLocked(array $account): bool
    {
        if (empty($account['locked_until'])) {
            return false;
        }
        
        $lockUntil = strtotime($account['locked_until']);
        return $lockUntil > time();
    }
    
    public function isActive(array $account): bool
    {
        return (bool)$account['is_active'];
    }
    
    public function generatePasswordResetToken(int $accountId): string
    {
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour
        
        $sql = "UPDATE {$this->table} SET password_reset_token = ?, reset_token_expires_at = ? WHERE {$this->primaryKey} = ?";
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute([$token, $expiresAt, $accountId]);
        
        return $token;
    }
    
    public function findByResetToken(string $token): ?array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE password_reset_token = ? 
                AND reset_token_expires_at > NOW() 
                LIMIT 1";
        
        $stmt = $this->query($sql, [$token]);
        $result = $stmt->fetch();
        return $result ?: null;
    }
    
    public function clearPasswordResetToken(int $accountId): bool
    {
        $sql = "UPDATE {$this->table} SET password_reset_token = NULL, reset_token_expires_at = NULL WHERE {$this->primaryKey} = ?";
        $stmt = $this->getConnection()->prepare($sql);
        return $stmt->execute([$accountId]);
    }
}
