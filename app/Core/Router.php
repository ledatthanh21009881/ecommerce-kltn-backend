<?php
namespace App\Core;

class Router {
    private Request $req; 
    private Response $res; 
    private Container $c;
    private array $routes = []; 
    private array $middlewares = [];
    
    public function __construct(Request $r, Response $w, Container $c) {
        $this->req = $r;
        $this->res = $w;
        $this->c = $c;
    }
    
    public function use($mw) { 
        $this->middlewares[] = $mw; 
    }
    
    public function add(string $m, string $p, $h, array $mw = []) { 
        $this->routes[] = ['method' => $m, 'path' => $p, 'handler' => $h, 'middlewares' => $mw]; 
    }
    
    public function get($p, $h, $mw = []) { $this->add('GET', $p, $h, $mw); }
    public function post($p, $h, $mw = []) { $this->add('POST', $p, $h, $mw); }
    public function put($p, $h, $mw = []) { $this->add('PUT', $p, $h, $mw); }
    public function delete($p, $h, $mw = []) { $this->add('DELETE', $p, $h, $mw); }
    public function patch($p, $h, $mw = []) { $this->add('PATCH', $p, $h, $mw); }
    public function options($p, $h, $mw = []) { $this->add('OPTIONS', $p, $h, $mw); }
    
    public function getRoutes(): array {
        return $this->routes;
    }
    
    public function dispatch(): void {
    $m = $this->req->method();
    $p = rtrim($this->req->path(), '/') ?: '/';

    // Ensure CORS headers are added for all responses, including OPTIONS
    if ($m === 'OPTIONS') {
        $this->res->setHeader('Access-Control-Allow-Origin', '*');
        $this->res->setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS, DELETE, PUT, PATCH');
        $this->res->setHeader('Access-Control-Allow-Headers', 'Authorization, Content-Type, Accept');
        $this->res->setHeader('Access-Control-Max-Age', '86400');
        $this->res->json([], 204);
        return;
    }

    foreach($this->routes as $r) {
        if($r['method'] === $m && $r['path'] === $p) {
            $this->executeRoute($r);
            return;
        }

        if($r['method'] === $m && $this->matchesPattern($r['path'], $p)) {
            $this->executeRoute($r);
            return;
        }
    }

    $this->res->json(['message' => 'Not Found'], 404);
}
    
    private function matchesPattern(string $pattern, string $path): bool {
        // Convert {param} to regex pattern
        $regexPattern = preg_replace('/\{[^}]+\}/', '([^/]+)', $pattern);
        $regexPattern = '#^' . $regexPattern . '$#';
        
        if (preg_match($regexPattern, $path, $matches)) {
            // Extract parameters and set them in request
            preg_match_all('/\{([^}]+)\}/', $pattern, $paramMatches);
            
            if (isset($paramMatches[1])) {
                foreach ($paramMatches[1] as $index => $paramName) {
                    if (isset($matches[$index + 1])) {
                        $this->req->setAttribute($paramName, $matches[$index + 1]);
                    }
                }
            }
            return true;
        }
        return false;
    }
    
    private function executeRoute(array $r): void {
        $stack = array_merge($this->middlewares, $r['middlewares']);
        $handler = $r['handler'];
        
        $next = function() use($handler) {
            if (is_array($handler)) { 
                [$cls, $fn] = $handler; 
                $x = new $cls($this->c); 
                return $x->$fn($this->req, $this->res); 
            }
            if (is_callable($handler)) {
                return $handler($this->req, $this->res, $this->c);
            }
        };
        
        $runner = array_reduce(array_reverse($stack), function($n, $mw) {
            return function() use($mw, $n) { 
                return $mw->handle($this->req, $this->res, $n); 
            };
        }, $next);
        
        $runner();
    }
}
