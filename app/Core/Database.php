<?php
declare(strict_types=1);

/**
 * Database
 * 
 * Quản lý kết nối database sử dụng Singleton pattern và PDO.
 * Cung cấp transaction management và các utility methods.
 * 
 * Chức năng:
 * - Singleton pattern để đảm bảo chỉ có 1 connection
 * - PDO connection management
 * - Transaction support (begin, commit, rollback)
 * - Last insert ID retrieval
 * 
 * Tái sử dụng:
 * - Tất cả Models, Repositories sử dụng Database này
 * - Inject vào Container và sử dụng qua dependency injection
 * - Sử dụng cho các operations cần transaction (Order, Payment, etc.)
 * 
 * @package App\Core
 * @author ShopSwift Team
 */

namespace App\Core;

use PDO;
use PDOException;

class Database
{
    /** @var PDO|null Singleton PDO instance */
    private static ?PDO $instance = null;
    
    /** @var array Database configuration */
    private array $config;
    
    /**
     * Constructor - Lưu config nhưng chưa connect
     * 
     * @param array $config Database configuration
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }
    
    /**
     * Lấy PDO connection instance (Singleton)
     * 
     * Tái sử dụng: Gọi từ tất cả Models/Repositories để có database connection
     * 
     * @return PDO PDO connection instance
     */
    public function getConnection(): PDO
    {
        if (self::$instance === null) {
            $this->connect();
        }
        
        return self::$instance;
    }
    
    /**
     * Kết nối đến database và tạo PDO instance
     * 
     * Tái sử dụng: Gọi tự động từ getConnection() khi cần
     * 
     * @throws PDOException Nếu connection fails
     */
    private function connect(): void
    {
        try {
            $connection = $this->config['connections'][$this->config['default']];
            
            $dsn = sprintf(
                '%s:host=%s;port=%s;dbname=%s;charset=%s',
                $connection['driver'],
                $connection['host'],
                $connection['port'],
                $connection['database'],
                $connection['charset']
            );
            
            self::$instance = new PDO(
                $dsn,
                $connection['username'],
                $connection['password'],
                $connection['options']
            );
            
        } catch (PDOException $e) {
            throw new PDOException("Database connection failed: " . $e->getMessage());
        }
    }
    
    /**
     * Bắt đầu database transaction
     * 
     * Tái sử dụng: Sử dụng khi cần atomic operations (tạo Order, Payment, etc.)
     * 
     * Example:
     * $db->beginTransaction();
     * try {
     *     // multiple operations
     *     $db->commit();
     * } catch (Exception $e) {
     *     $db->rollback();
     * }
     * 
     * @return bool Success status
     */
    public function beginTransaction(): bool
    {
        return $this->getConnection()->beginTransaction();
    }
    
    /**
     * Commit transaction
     * 
     * Tái sử dụng: Gọi sau khi tất cả operations thành công
     * 
     * @return bool Success status
     */
    public function commit(): bool
    {
        return $this->getConnection()->commit();
    }
    
    /**
     * Rollback transaction
     * 
     * Tái sử dụng: Gọi khi có lỗi để undo tất cả changes
     * 
     * @return bool Success status
     */
    public function rollback(): bool
    {
        return $this->getConnection()->rollback();
    }
    
    /**
     * Lấy ID của record vừa insert
     * 
     * Tái sử dụng: Sử dụng sau khi INSERT để lấy auto-increment ID
     * 
     * @return string Last insert ID
     */
    public function lastInsertId(): string
    {
        return $this->getConnection()->lastInsertId();
    }
}
