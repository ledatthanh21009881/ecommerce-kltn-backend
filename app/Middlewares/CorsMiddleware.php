<?php
declare(strict_types=1);

namespace App\Middlewares;

use App\Core\{Request, Response};

class CorsMiddleware {
    public function handle(Request $req, Response $res, callable $next)
    {
        // Get origin
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
        
        // Allow specific origins or all
        $allowedOrigins = [
            'http://localhost:3003',
            'http://localhost:3001',
            'http://localhost:3000', 
            'http://localhost:3002',
            'http://localhost:3004',
            'http://192.168.68.112:3002',
            'http://127.0.0.1:3003',
            'http://127.0.0.1:3001',
            'http://127.0.0.1:3000',
            'http://localhost:8080',
            'http://127.0.0.1:8080',
            // Add Next.js default ports
            'http://localhost:3001',
            'http://localhost:3004',
            'http://localhost:3005'
        ];
        
        // For development - allow all origins
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH');
        header('Access-Control-Allow-Credentials: false'); // Set to false when using wildcard origin
        
        // Handle preflight OPTIONS request
        if ($req->method() === 'OPTIONS') {
            http_response_code(204);
            exit();
        }
        
        return $next($req, $res);
    }
}
