<?php
declare(strict_types=1);

namespace App\Domain\Auth;

use App\Core\Model;

class RefreshToken extends Model
{
    protected string $table = 'refresh_tokens';
    protected string $primaryKey = 'token_id';
    
    protected array $fillable = [
        'user_id',
        'refresh_token',
        'expires_at'
    ];
    
    public function createToken(int $userId, string $token, int $expiresInSeconds = 2592000): int // 30 days default
    {
        $expiresAt = date('Y-m-d H:i:s', time() + $expiresInSeconds);
        
        return $this->create([
            'user_id' => $userId,
            'refresh_token' => $token,
            'expires_at' => $expiresAt
        ]);
    }
    
    public function findByToken(string $token): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE refresh_token = ? AND expires_at > NOW() AND revoked_at IS NULL LIMIT 1";
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute([$token]);
        
        $result = $stmt->fetch();
        return $result ?: null;
    }
    
    public function revokeToken(string $token): bool
    {
        $sql = "UPDATE {$this->table} SET revoked_at = NOW() WHERE refresh_token = ?";
        $stmt = $this->getConnection()->prepare($sql);
        return $stmt->execute([$token]);
    }
    
    public function revokeAllUserTokens(int $userId): bool
    {
        $sql = "UPDATE {$this->table} SET revoked_at = NOW() WHERE user_id = ? AND revoked_at IS NULL";
        $stmt = $this->getConnection()->prepare($sql);
        return $stmt->execute([$userId]);
    }
    
    public function cleanExpiredTokens(): int
    {
        $sql = "DELETE FROM {$this->table} WHERE expires_at < NOW() OR revoked_at IS NOT NULL";
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute();
        return $stmt->rowCount();
    }
    
    public function generateRefreshToken(): string
    {
        return bin2hex(random_bytes(32));
    }
}
