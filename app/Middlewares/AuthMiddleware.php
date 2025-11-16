<?php
declare(strict_types=1);

namespace App\Middlewares;

use App\Core\{Request, Response, Container};
use App\Support\{JWT, ResponseHelper};
use App\Domain\Auth\User;
use Exception;

class AuthMiddleware 
{
    private Container $container;
    private JWT $jwt;
    private User $userModel;
    
    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->jwt = $container->jwt();
        $this->userModel = new User($container->database());
    }
    
    public function handle(Request $req, Response $res, callable $next)
    {
        $token = $req->bearer();
        
        if (!$token) {
            return $res->json(ResponseHelper::unauthorized('Authorization token required'));
        }
        
        // Decode JWT token
        $payload = $this->jwt->decode($token);
        
        if (!$payload) {
            return $res->json(ResponseHelper::unauthorized('Invalid or expired token'));
        }
        
        try {
            $pdo = $this->container->database()->getConnection();
            $user = null;
            
            // Debug: log payload to see what we get
            error_log("AuthMiddleware payload: " . json_encode($payload));
            
            // Priority: If user_id is available, query users table first (for shipper/customer auth)
            if (isset($payload['user_id'])) {
                $sql = "SELECT u.*, 
                               GROUP_CONCAT(r.role_name) as roles,
                               a.account_name,
                               a.is_active as account_active
                        FROM users u 
                        JOIN accounts a ON u.account_id = a.account_id
                        LEFT JOIN user_roles ur ON u.user_id = ur.user_id
                        LEFT JOIN roles r ON ur.role_id = r.role_id
                        WHERE u.user_id = ? AND a.is_active = 1
                        GROUP BY u.user_id";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$payload['user_id']]);
                $user = $stmt->fetch();
                
                if ($user) {
                    // Parse roles from users table query
                    $user['roles'] = $user['roles'] ? explode(',', $user['roles']) : ($payload['roles'] ?? ['customer']);
                    error_log("AuthMiddleware user after users query: " . json_encode($user));
                }
            }
            
            // If no user found and account_id is available, try accounts table (for admin login)
            if (!$user && isset($payload['account_id'])) {
                $sql = "SELECT account_id as user_id, account_name, account_type, is_active as account_active
                        FROM accounts 
                        WHERE account_id = ? AND is_active = 1";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$payload['account_id']]);
                $user = $stmt->fetch();
                
                if ($user) {
                    // Set roles based on account_type or from token
                    $user['roles'] = $payload['roles'] ?? [$user['account_type'] ?? 'user'];
                    $user['account_type'] = $user['account_type']; // Preserve account_type
                    error_log("AuthMiddleware user after account query: " . json_encode($user));
                }
            }
            
            if (!$user) {
                return $res->json(ResponseHelper::unauthorized('User not found or account inactive'));
            }
            
            // Set user in request attributes
            $req->setAttribute('user', $user);
            $req->setAttribute('token_payload', $payload);
            
            return $next($req, $res);
            
        } catch (Exception $e) {
            return $res->json(ResponseHelper::unauthorized('Authentication failed: ' . $e->getMessage()));
        }
    }
}
