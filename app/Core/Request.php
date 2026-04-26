<?php
declare(strict_types=1);

namespace App\Core;

class Request 
{
    private array $attributes = [];
    
    public function method(): string 
    { 
        return $_SERVER['REQUEST_METHOD'] ?? 'GET'; 
    }
    
    public function path(): string 
    { 
        $uri = $_SERVER['REQUEST_URI'] ?? '/'; 
        return parse_url($uri, PHP_URL_PATH) ?? '/'; 
    }
    
    public function header(string $name): ?string 
    { 
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name)); 
        return $_SERVER[$key] ?? null; 
    }
    
    public function json(): array 
    { 
        // Check for mocked input first
        if (isset($GLOBALS['mock_input'])) {
            $raw = $GLOBALS['mock_input'];
        } else {
            $raw = file_get_contents('php://input') ?: ''; 
        }
        $data = json_decode($raw, true); 
        return is_array($data) ? $data : []; 
    }
    
    public function query(string $key = null, mixed $default = null): mixed
    { 
        if ($key === null) return $_GET; 
        return $_GET[$key] ?? $default; 
    }

    /** @return array<string, mixed> */
    public function getQueryParams(): array
    {
        $q = $this->query();
        return is_array($q) ? $q : [];
    }

    public function getPathParam(string $name): ?string
    {
        $v = $this->getAttribute($name);
        if ($v === null) {
            return null;
        }
        return is_scalar($v) ? (string) $v : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getBody(): array
    {
        $contentType = $this->header('Content-Type') ?? '';
        if (str_contains(strtolower($contentType), 'application/json')) {
            return $this->json();
        }
        if (!empty($_POST)) {
            return $_POST;
        }
        $raw = file_get_contents('php://input') ?: '';
        if ($raw === '') {
            return [];
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }
    
    public function body(): array 
    { 
        return $_POST; 
    }
    
    public function bearer(): ?string 
    { 
        $auth = $this->header('Authorization'); 
        if (!$auth) return null; 
        return str_starts_with($auth, 'Bearer ') ? substr($auth, 7) : null; 
    }
    
    public function getAttribute(string $name): mixed
    {
        return $this->attributes[$name] ?? null;
    }
    
    public function setAttribute(string $name, mixed $value): void
    {
        $this->attributes[$name] = $value;
    }
    
    public function hasAttribute(string $name): bool
    {
        return isset($this->attributes[$name]);
    }

    /**
     * Set query parameter (for internal use)
     */
    public function setQueryParam(string $key, mixed $value): void
    {
        $_GET[$key] = $value;
    }

    /**
     * Set param (alias for setQueryParam for backward compatibility)
     */
    public function setParam(string $key, mixed $value): void
    {
        $this->setQueryParam($key, $value);
    }

    /**
     * Get route parameter (from attributes or query params)
     */
    public function param(string $key, mixed $default = null): mixed
    {
        // First check attributes (for route parameters)
        if ($this->hasAttribute($key)) {
            return $this->getAttribute($key);
        }
        // Then check query params
        return $this->query($key, $default);
    }
}
