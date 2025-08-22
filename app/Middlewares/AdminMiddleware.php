<?php
declare(strict_types=1);

namespace App\Middlewares;

use App\Core\{Request, Response, Container};
use App\Support\{ResponseHelper, JWT};
use Exception;

class AdminMiddleware
{
    private JWT $jwt;
    
    public function __construct(Container $container)
    {
        $this->jwt = $container->jwt();
    }
    
    public function handle(Request $request, Response $response, callable $next)
    {
        try {
            // First check if user is authenticated
            $token = $request->bearer();
            if (!$token) {
                return $response->json(ResponseHelper::unauthorized('Authorization token required'));
            }
            
            // Decode and verify JWT token
            $payload = $this->jwt->decode($token);
            if (!$payload) {
                return $response->json(ResponseHelper::unauthorized('Invalid or expired token'));
            }
            
            // Check if user has admin role
            $roles = $payload['roles'] ?? [];
            if (!in_array('admin', $roles)) {
                return $response->json(ResponseHelper::forbidden('Admin access required'));
            }
            
            // Add user info to request for use in controllers
            $request->setAttribute('user', $payload);
            
            // Continue to the next middleware or controller
            return $next($request, $response);
            
        } catch (Exception $e) {
            return $response->json(ResponseHelper::unauthorized('Authentication failed: ' . $e->getMessage()));
        }
    }
}
