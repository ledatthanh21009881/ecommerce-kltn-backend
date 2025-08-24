<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Controller, Request, Response, Container};
use App\Support\ResponseHelper;
use Exception;

class AdminController extends Controller 
{
    public function __construct(Container $container)
    {
        parent::__construct($container);
    }
    
    /**
     * Admin Dashboard - Overview statistics
     */
    public function dashboard(Request $req, Response $res)
    {
        try {
            $pdo = $this->container->database()->getConnection();
            
            // Get basic statistics
            $stats = [];
            
            // Total users
            $stmt = $pdo->query("SELECT COUNT(*) as total_users FROM users");
            $stats['total_users'] = $stmt->fetch()['total_users'];
            
            // Total customers
            $stmt = $pdo->query("SELECT COUNT(*) as total_customers FROM customers");
            $stats['total_customers'] = $stmt->fetch()['total_customers'];
            
            // Total orders
            $stmt = $pdo->query("SELECT COUNT(*) as total_orders FROM orders");
            $stats['total_orders'] = $stmt->fetch()['total_orders'];
            
            // Total products
            $stmt = $pdo->query("SELECT COUNT(*) as total_products FROM products");
            $stats['total_products'] = $stmt->fetch()['total_products'];
            
            // Recent orders (last 10)
            $stmt = $pdo->query("
                SELECT o.order_id, o.total_amount, o.status, o.created_at,
                       u.first_name, u.last_name, u.email
                FROM orders o
                JOIN users u ON o.user_id = u.user_id
                ORDER BY o.created_at DESC
                LIMIT 10
            ");
            $recent_orders = $stmt->fetchAll();
            
            // Monthly revenue (current month)
            $stmt = $pdo->query("
                SELECT COALESCE(SUM(total_amount), 0) as monthly_revenue
                FROM orders 
                WHERE MONTH(created_at) = MONTH(CURRENT_DATE())
                  AND YEAR(created_at) = YEAR(CURRENT_DATE())
                  AND status IN ('completed', 'delivered')
            ");
            $stats['monthly_revenue'] = $stmt->fetch()['monthly_revenue'];
            
            return $res->json(ResponseHelper::success([
                'stats' => $stats,
                'recent_orders' => $recent_orders
            ], 'Dashboard data retrieved successfully'));
            
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to load dashboard: ' . $e->getMessage()));
        }
    }
    
    /**
     * Get all users with pagination
     */
    public function getUsers(Request $req, Response $res)
    {
        try {
            $page = (int)($req->query('page') ?? 1);
            $limit = (int)($req->query('limit') ?? 10);
            $search = $req->query('search', '');
            $role = $req->query('role', '');
            
            $offset = ($page - 1) * $limit;
            
            $pdo = $this->container->database()->getConnection();
            
            // Build query with filters
            $whereConditions = ['1=1'];
            $params = [];
            
            if ($search) {
                $whereConditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR a.account_name LIKE ?)";
                $searchTerm = "%{$search}%";
                $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
            }
            
            if ($role) {
                $whereConditions[] = "r.role_name = ?";
                $params[] = $role;
            }
            
            $whereClause = implode(' AND ', $whereConditions);
            
            // Get total count
            $countSql = "
                SELECT COUNT(DISTINCT u.user_id) as total
                FROM users u
                JOIN accounts a ON u.account_id = a.account_id
                LEFT JOIN user_roles ur ON u.user_id = ur.user_id
                LEFT JOIN roles r ON ur.role_id = r.role_id
                WHERE {$whereClause}
            ";
            $stmt = $pdo->prepare($countSql);
            $stmt->execute($params);
            $total = $stmt->fetch()['total'];
            
            // Get users data
            $sql = "
                SELECT u.user_id, u.first_name, u.last_name, u.email, u.phone, u.gender, u.birthdate,
                       a.account_name, a.last_login_at, a.created_at, a.is_active,
                       GROUP_CONCAT(r.role_name) as roles
                FROM users u
                JOIN accounts a ON u.account_id = a.account_id
                LEFT JOIN user_roles ur ON u.user_id = ur.user_id
                LEFT JOIN roles r ON ur.role_id = r.role_id
                WHERE {$whereClause}
                GROUP BY u.user_id
                ORDER BY u.user_id DESC
                LIMIT ? OFFSET ?
            ";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_merge($params, [$limit, $offset]));
            $users = $stmt->fetchAll();
            
            // Process roles for each user
            foreach ($users as &$user) {
                $user['roles'] = $user['roles'] ? explode(',', $user['roles']) : [];
            }
            
            return $res->json(ResponseHelper::paginated($users, $total, $limit, $page));
            
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to get users: ' . $e->getMessage()));
        }
    }
    
    /**
     * Update user status (activate/deactivate)
     */
    public function updateUserStatus(Request $req, Response $res)
    {
        $userId = $req->query('id');
        $data = $req->json();
        
        if (!$userId) {
            return $res->json(ResponseHelper::error('User ID is required'));
        }
        
        if (!isset($data['is_active'])) {
            return $res->json(ResponseHelper::error('Status is required'));
        }
        
        try {
            $pdo = $this->container->database()->getConnection();
            
            // Get user's account_id
            $stmt = $pdo->prepare("SELECT account_id FROM users WHERE user_id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if (!$user) {
                return $res->json(ResponseHelper::notFound('User not found'));
            }
            
            // Update account status
            $stmt = $pdo->prepare("UPDATE accounts SET is_active = ? WHERE account_id = ?");
            $stmt->execute([$data['is_active'], $user['account_id']]);
            
            $status = $data['is_active'] ? 'activated' : 'deactivated';
            
            return $res->json(ResponseHelper::success(null, "User {$status} successfully"));
            
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to update user status: ' . $e->getMessage()));
        }
    }
    
    /**
     * Get system activity logs
     */
    public function getActivityLogs(Request $req, Response $res)
    {
        try {
            $page = (int)($req->query('page') ?? 1);
            $limit = (int)($req->query('limit') ?? 20);
            $offset = ($page - 1) * $limit;
            
            $pdo = $this->container->database()->getConnection();
            
            // For now, we'll show recent login activities from accounts table
            // In a real system, you would have a dedicated activity_logs table
            $stmt = $pdo->prepare("
                SELECT a.account_name, a.last_login_at, u.first_name, u.last_name,
                       'login' as action, a.last_login_at as created_at
                FROM accounts a
                JOIN users u ON a.account_id = u.account_id
                WHERE a.last_login_at IS NOT NULL
                ORDER BY a.last_login_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$limit, $offset]);
            $logs = $stmt->fetchAll();
            
            // Get total count
            $stmt = $pdo->query("
                SELECT COUNT(*) as total
                FROM accounts a
                JOIN users u ON a.account_id = u.account_id
                WHERE a.last_login_at IS NOT NULL
            ");
            $total = $stmt->fetch()['total'];
            
            return $res->json(ResponseHelper::paginated($logs, $total, $limit, $page));
            
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to get activity logs: ' . $e->getMessage()));
        }
    }

    /**
     * Get user statistics
     */
    public function getUserStats(Request $req, Response $res)
    {
        try {
            $pdo = $this->container->database()->getConnection();
            
            // Get total users
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
            $totalUsers = $stmt->fetch()['total'];
            
            // Get active users
            $stmt = $pdo->query("
                SELECT COUNT(*) as active 
                FROM users u 
                JOIN accounts a ON u.account_id = a.account_id 
                WHERE a.is_active = 1
            ");
            $activeUsers = $stmt->fetch()['active'];
            
            // Get users by role
            $stmt = $pdo->query("
                SELECT r.role_name, COUNT(*) as count
                FROM users u
                JOIN user_roles ur ON u.user_id = ur.user_id
                JOIN roles r ON ur.role_id = r.role_id
                GROUP BY r.role_name
            ");
            $roleStats = $stmt->fetchAll();
            
            $stats = [
                'total_users' => $totalUsers,
                'active_users' => $activeUsers,
                'admins' => 0,
                'managers' => 0,
                'staff' => 0,
                'customers' => 0
            ];
            
            foreach ($roleStats as $role) {
                $roleName = strtolower($role['role_name']);
                if (isset($stats[$roleName])) {
                    $stats[$roleName] = $role['count'];
                }
            }
            
            return $res->json(ResponseHelper::success($stats));
            
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to get user stats: ' . $e->getMessage()));
        }
    }

    /**
     * Create new user
     */
    public function createUser(Request $req, Response $res)
    {
        try {
            $data = $req->json();
            
            // Validate required fields
            $required = ['account_name', 'password', 'first_name', 'last_name', 'email', 'role_ids'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    return $res->json(ResponseHelper::error("Field '{$field}' is required"));
                }
            }
            
            $pdo = $this->container->database()->getConnection();
            $pdo->beginTransaction();
            
            try {
                // Create account
                $stmt = $pdo->prepare("
                    INSERT INTO accounts (account_name, password, account_type, is_active, created_at) 
                    VALUES (?, ?, 'local', 1, NOW())
                ");
                $stmt->execute([
                    $data['account_name'],
                    password_hash($data['password'], PASSWORD_DEFAULT)
                ]);
                $accountId = $pdo->lastInsertId();
                
                // Create user
                $stmt = $pdo->prepare("
                    INSERT INTO users (account_id, first_name, last_name, email, phone) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $accountId,
                    $data['first_name'],
                    $data['last_name'],
                    $data['email'],
                    $data['phone'] ?? null
                ]);
                $userId = $pdo->lastInsertId();
                
                // Assign roles
                if (is_array($data['role_ids'])) {
                    foreach ($data['role_ids'] as $roleId) {
                        $stmt = $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
                        $stmt->execute([$userId, $roleId]);
                    }
                }
                
                $pdo->commit();
                return $res->json(ResponseHelper::success(null, 'User created successfully'));
                
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to create user: ' . $e->getMessage()));
        }
    }

    /**
     * Update user
     */
    public function updateUser(Request $req, Response $res)
    {
        try {
            $userId = $req->getAttribute('id');
            $data = $req->json();
            
            if (!$userId) {
                return $res->json(ResponseHelper::error('User ID is required'));
            }
            
            $pdo = $this->container->database()->getConnection();
            
            // Check if user exists
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE user_id = ?");
            $stmt->execute([$userId]);
            if (!$stmt->fetch()) {
                return $res->json(ResponseHelper::notFound('User not found'));
            }
            
            // Update user info
            $updateFields = [];
            $params = [];
            
            if (isset($data['first_name'])) {
                $updateFields[] = "first_name = ?";
                $params[] = $data['first_name'];
            }
            if (isset($data['last_name'])) {
                $updateFields[] = "last_name = ?";
                $params[] = $data['last_name'];
            }
            if (isset($data['email'])) {
                $updateFields[] = "email = ?";
                $params[] = $data['email'];
            }
            if (isset($data['phone'])) {
                $updateFields[] = "phone = ?";
                $params[] = $data['phone'];
            }
            
            if (!empty($updateFields)) {
                $params[] = $userId;
                $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE user_id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
            }
            
            // Update roles if provided
            if (isset($data['role_ids']) && is_array($data['role_ids'])) {
                // Remove existing roles
                $stmt = $pdo->prepare("DELETE FROM user_roles WHERE user_id = ?");
                $stmt->execute([$userId]);
                
                // Add new roles
                foreach ($data['role_ids'] as $roleId) {
                    $stmt = $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
                    $stmt->execute([$userId, $roleId]);
                }
            }
            
            return $res->json(ResponseHelper::success(null, 'User updated successfully'));
            
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to update user: ' . $e->getMessage()));
        }
    }

    /**
     * Delete user
     */
    public function deleteUser(Request $req, Response $res)
    {
        try {
            $userId = $req->getAttribute('id');
            
            if (!$userId) {
                return $res->json(ResponseHelper::error('User ID is required'));
            }
            
            $pdo = $this->container->database()->getConnection();
            
            // Check if user exists
            $stmt = $pdo->prepare("SELECT account_id FROM users WHERE user_id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if (!$user) {
                return $res->json(ResponseHelper::notFound('User not found'));
            }
            
            $pdo->beginTransaction();
            
            try {
                // Delete user roles
                $stmt = $pdo->prepare("DELETE FROM user_roles WHERE user_id = ?");
                $stmt->execute([$userId]);
                
                // Delete user
                $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
                $stmt->execute([$userId]);
                
                // Delete account
                $stmt = $pdo->prepare("DELETE FROM accounts WHERE account_id = ?");
                $stmt->execute([$user['account_id']]);
                
                $pdo->commit();
                return $res->json(ResponseHelper::success(null, 'User deleted successfully'));
                
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to delete user: ' . $e->getMessage()));
        }
    }
}
