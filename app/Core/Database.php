<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;
    private array $config;
    
    public function __construct(array $config)
    {
        $this->config = $config;
    }
    
    public function getConnection(): PDO
    {
        if (self::$instance === null) {
            $this->connect();
        }
        
        return self::$instance;
    }
    
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
    
    public function beginTransaction(): bool
    {
        return $this->getConnection()->beginTransaction();
    }
    
    public function commit(): bool
    {
        return $this->getConnection()->commit();
    }
    
    public function rollback(): bool
    {
        return $this->getConnection()->rollback();
    }
    
    public function lastInsertId(): string
    {
        return $this->getConnection()->lastInsertId();
    }
}
