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
            $token = $request->bearer();
            if (!$token) {
                return $response->json(ResponseHelper::unauthorized('Authorization token required'));
            }

            $payload = $this->jwt->decode($token);
            if (!$payload) {
                return $response->json(ResponseHelper::unauthorized('Invalid or expired token'));
            }

            $roles = $payload['roles'] ?? [];
            $isAdmin = !empty($payload['is_admin']) || in_array('admin', $roles, true);
            $allowedPaths = $payload['allowed_menu_paths'] ?? [];

            if (!$isAdmin && (!is_array($allowedPaths) || count($allowedPaths) === 0)) {
                return $response->json(ResponseHelper::forbidden('Admin access required'));
            }

            $request->setAttribute('user', $payload);
            $request->setAttribute('token_payload', $payload);

            return $next($request, $response);
        } catch (Exception $e) {
            return $response->json(ResponseHelper::unauthorized('Authentication failed: ' . $e->getMessage()));
        }
    }
}
