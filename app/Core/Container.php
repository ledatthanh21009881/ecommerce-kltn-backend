<?php
declare(strict_types=1);

namespace App\Core;

use Dotenv\Dotenv;
use App\Support\JWT;

class Container 
{
    private array $config = [];
    private array $instances = [];

    public function __construct(string $configPath)
    {
        $this->config = ['paths' => ['config' => $configPath]];
        foreach (glob($configPath . '/*.php') as $file) {
            $key = basename($file, '.php');
            $this->config[$key] = require $file;
        }
    }
    
    public function bootEnv(string $root): void
    {
        if (file_exists($root . '/.env')) {
            Dotenv::createImmutable($root)->safeLoad();
        }
    }
    
    public function config(string $key, mixed $default = null): mixed
    {
        $value = $this->config;
        foreach (explode('.', $key) as $part) {
            if (!isset($value[$part])) {
                return $default;
            }
            $value = $value[$part];
        }
        return $value;
    }
    
    public function database(): Database
    {
        if (!isset($this->instances['database'])) {
            $config = $this->config('database');
            $this->instances['database'] = new Database($config);
        }
        return $this->instances['database'];
    }
    
    public function jwt(): JWT
    {
        if (!isset($this->instances['jwt'])) {
            $config = $this->config('app.jwt');
            $this->instances['jwt'] = new JWT(
                $config['secret'],
                $config['algorithm'],
                $config['expire_time']
            );
        }
        return $this->instances['jwt'];
    }
    
    public function get(string $key): mixed
    {
        return $this->instances[$key] ?? null;
    }
    
    public function set(string $key, mixed $instance): void
    {
        $this->instances[$key] = $instance;
    }
    
    public function has(string $key): bool
    {
        return isset($this->instances[$key]);
    }
    
    // Backward compatibility
    public function db(): \PDO
    {
        return $this->database()->getConnection();
    }
}
