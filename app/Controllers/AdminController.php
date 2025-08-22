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
}
