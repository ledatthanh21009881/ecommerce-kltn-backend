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
     * Admin Dashboard — ?from=&to= (doanh thu + KPI); ?top_from=&top_to= (top SP, mặc định = from/to).
     * Mặc định không query: tháng trước. Tối đa 366 ngày mỗi khoảng.
     */
    public function dashboard(Request $req, Response $res)
    {
        try {
            $pdo = $this->container->database()->getConnection();

            $fromQ = $req->query('from');
            $toQ = $req->query('to');

            if ($fromQ && $toQ) {
                $fromDt = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $fromQ);
                $toDt = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $toQ);
                if (!$fromDt || !$toDt || $fromDt->format('Y-m-d') !== $fromQ || $toDt->format('Y-m-d') !== $toQ) {
                    return $res->json(ResponseHelper::error('Invalid from/to; use Y-m-d', 400), 400);
                }
                $fromStr = $fromDt->format('Y-m-d');
                $toStr = $toDt->format('Y-m-d');
            } else {
                $firstThis = new \DateTimeImmutable('first day of this month');
                $toImmutable = $firstThis->modify('-1 day');
                $fromImmutable = $toImmutable->modify('first day of this month');
                $fromStr = $fromImmutable->format('Y-m-d');
                $toStr = $toImmutable->format('Y-m-d');
            }

            if ($fromStr > $toStr) {
                return $res->json(ResponseHelper::error('from must be before or equal to to', 400), 400);
            }

            $fromTs = strtotime($fromStr . ' 00:00:00');
            $toTs = strtotime($toStr . ' 00:00:00');
            $daySpan = (int) (($toTs - $fromTs) / 86400) + 1;
            if ($daySpan > 366) {
                return $res->json(ResponseHelper::error('Date range cannot exceed 366 days', 400), 400);
            }

            $topFromQ = $req->query('top_from');
            $topToQ = $req->query('top_to');
            if (($topFromQ !== null && $topFromQ !== '') && ($topToQ !== null && $topToQ !== '')) {
                $tf = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $topFromQ);
                $tt = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $topToQ);
                if (
                    !$tf || !$tt
                    || $tf->format('Y-m-d') !== (string) $topFromQ
                    || $tt->format('Y-m-d') !== (string) $topToQ
                ) {
                    return $res->json(ResponseHelper::error('Invalid top_from/top_to; use Y-m-d', 400), 400);
                }
                $topFromStr = $tf->format('Y-m-d');
                $topToStr = $tt->format('Y-m-d');
            } else {
                $topFromStr = $fromStr;
                $topToStr = $toStr;
            }
            if ($topFromStr > $topToStr) {
                return $res->json(ResponseHelper::error('top_from must be before or equal to top_to', 400), 400);
            }
            $topFromTs = strtotime($topFromStr . ' 00:00:00');
            $topToTs = strtotime($topToStr . ' 00:00:00');
            $topDaySpan = (int) (($topToTs - $topFromTs) / 86400) + 1;
            if ($topDaySpan > 366) {
                return $res->json(ResponseHelper::error('Top products range cannot exceed 366 days', 400), 400);
            }

            // Đơn & doanh thu trong khoảng (trừ đơn hủy/hoàn)
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) AS c,
                    COALESCE(SUM(total_amount), 0) AS rev
                FROM orders
                WHERE DATE(created_at) BETWEEN ? AND ?
                  AND status NOT IN ('cancelled', 'returned')
            ");
            $stmt->execute([$fromStr, $toStr]);
            $agg = $stmt->fetch(\PDO::FETCH_ASSOC) ?: ['c' => 0, 'rev' => 0];

            // Chi phí nhập trong khoảng (chỉ phiếu đã xác nhận)
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(pi.subtotal), 0) AS purchase_cost
                FROM purchase_receipts pr
                INNER JOIN purchase_items pi ON pr.receipt_id = pi.receipt_id
                WHERE pr.status = 'confirmed'
                  AND DATE(pr.updated_at) BETWEEN ? AND ?
            ");
            $stmt->execute([$fromStr, $toStr]);
            $purchaseAgg = $stmt->fetch(\PDO::FETCH_ASSOC) ?: ['purchase_cost' => 0];
            $totalPurchaseCost = (float) ($purchaseAgg['purchase_cost'] ?? 0);

            $stmt = $pdo->query('SELECT COUNT(*) AS c FROM products');
            $total_products = (int) ($stmt->fetch()['c'] ?? 0);

            $stmt = $pdo->query('SELECT COUNT(*) AS c FROM users');
            $total_users = (int) ($stmt->fetch()['c'] ?? 0);

            // Doanh thu theo ngày trong khoảng
            $stmt = $pdo->prepare("
                SELECT DATE(created_at) AS d, COALESCE(SUM(total_amount), 0) AS revenue
                FROM orders
                WHERE DATE(created_at) BETWEEN ? AND ?
                  AND status NOT IN ('cancelled', 'returned')
                GROUP BY DATE(created_at)
            ");
            $stmt->execute([$fromStr, $toStr]);
            $byDay = [];
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $byDay[(string) $row['d']] = (float) $row['revenue'];
            }

            $revenue_series = [];
            $cursor = new \DateTimeImmutable($fromStr);
            $end = new \DateTimeImmutable($toStr);
            while ($cursor <= $end) {
                $key = $cursor->format('Y-m-d');
                $revenue_series[] = [
                    'date' => $key,
                    'revenue' => $byDay[$key] ?? 0.0,
                ];
                $cursor = $cursor->modify('+1 day');
            }

            // Chi phí nhập theo ngày trong khoảng (chỉ phiếu confirmed)
            $stmt = $pdo->prepare("
                SELECT DATE(pr.updated_at) AS d, COALESCE(SUM(pi.subtotal), 0) AS purchase_cost
                FROM purchase_receipts pr
                INNER JOIN purchase_items pi ON pr.receipt_id = pi.receipt_id
                WHERE pr.status = 'confirmed'
                  AND DATE(pr.updated_at) BETWEEN ? AND ?
                GROUP BY DATE(pr.updated_at)
            ");
            $stmt->execute([$fromStr, $toStr]);
            $costByDay = [];
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $costByDay[(string) $row['d']] = (float) $row['purchase_cost'];
            }

            $purchase_cost_series = [];
            $cursor = new \DateTimeImmutable($fromStr);
            while ($cursor <= $end) {
                $key = $cursor->format('Y-m-d');
                $purchase_cost_series[] = [
                    'date' => $key,
                    'purchase_cost' => $costByDay[$key] ?? 0.0,
                ];
                $cursor = $cursor->modify('+1 day');
            }

            // Top sản phẩm theo doanh thu trong khoảng (bỏ đơn hủy/hoàn)
            $stmt = $pdo->prepare("
                SELECT 
                    p.product_id,
                    p.product_name,
                    SUM(oi.quantity) AS sales_count,
                    SUM(oi.quantity * oi.unit_price) AS revenue,
                    0 AS view_count
                FROM order_items oi
                INNER JOIN orders o ON oi.order_id = o.order_id
                INNER JOIN product_variants pv ON oi.variant_id = pv.variant_id
                INNER JOIN products p ON pv.product_id = p.product_id
                WHERE o.status NOT IN ('cancelled', 'returned')
                  AND DATE(o.created_at) BETWEEN ? AND ?
                GROUP BY p.product_id, p.product_name
                ORDER BY revenue DESC
                LIMIT 5
            ");
            $stmt->execute([$topFromStr, $topToStr]);
            $top_raw = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $top_products = [];
            foreach ($top_raw as $row) {
                $top_products[] = [
                    'product_id' => (int) $row['product_id'],
                    'product_name' => $row['product_name'],
                    'sales_count' => (int) $row['sales_count'],
                    'revenue' => (float) $row['revenue'],
                    'view_count' => (int) $row['view_count'],
                ];
            }

            // Top sản phẩm nhập nhiều trong khoảng (chỉ phiếu confirmed)
            $stmt = $pdo->prepare("
                SELECT
                    p.product_id,
                    p.product_name,
                    SUM(pi.quantity) AS purchase_quantity,
                    SUM(pi.subtotal) AS purchase_cost
                FROM purchase_items pi
                INNER JOIN purchase_receipts pr ON pi.receipt_id = pr.receipt_id
                INNER JOIN product_variants pv ON pi.variant_id = pv.variant_id
                INNER JOIN products p ON pv.product_id = p.product_id
                WHERE pr.status = 'confirmed'
                  AND DATE(pr.updated_at) BETWEEN ? AND ?
                GROUP BY p.product_id, p.product_name
                ORDER BY purchase_quantity DESC, purchase_cost DESC
                LIMIT 5
            ");
            $stmt->execute([$topFromStr, $topToStr]);
            $top_purchased_raw = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $top_purchased_products = [];
            foreach ($top_purchased_raw as $row) {
                $top_purchased_products[] = [
                    'product_id' => (int) $row['product_id'],
                    'product_name' => (string) $row['product_name'],
                    'purchase_quantity' => (int) $row['purchase_quantity'],
                    'purchase_cost' => (float) $row['purchase_cost'],
                ];
            }

            $bestSellingProduct = null;
            if (!empty($top_products)) {
                $bestSellingProduct = [
                    'product_id' => (int) $top_products[0]['product_id'],
                    'product_name' => (string) $top_products[0]['product_name'],
                    'sales_count' => (int) $top_products[0]['sales_count'],
                    'revenue' => (float) $top_products[0]['revenue'],
                ];
            }

            $grossProfit = (float) ($agg['rev'] ?? 0) - $totalPurchaseCost;

            return $res->json(ResponseHelper::success([
                'total_orders' => (int) ($agg['c'] ?? 0),
                'total_revenue' => (float) ($agg['rev'] ?? 0),
                'total_purchase_cost' => $totalPurchaseCost,
                'gross_profit' => $grossProfit,
                'total_products' => $total_products,
                'total_users' => $total_users,
                'date_from' => $fromStr,
                'date_to' => $toStr,
                'top_date_from' => $topFromStr,
                'top_date_to' => $topToStr,
                'revenue_series' => $revenue_series,
                'purchase_cost_series' => $purchase_cost_series,
                'top_products' => $top_products,
                'top_purchased_products' => $top_purchased_products,
                'best_selling_product' => $bestSellingProduct,
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

    /**
     * GET /api/backend/v1/users/{id}/addresses — Địa chỉ đã lưu của user (admin tạo đơn)
     */
    public function getUserAddresses(Request $req, Response $res)
    {
        try {
            $userId = (int) $req->getAttribute('id');
            if ($userId <= 0) {
                return $res->json(ResponseHelper::validationError(['id' => 'Invalid user id']));
            }

            $pdo = $this->container->database()->getConnection();
            $sql = "SELECT address_id, user_id, receiver_name, phone, address_line, ward, district, province, is_default, lat, lng
                    FROM addresses WHERE user_id = ? ORDER BY is_default DESC, address_id DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$userId]);
            $list = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($list as &$row) {
                $row['address_id'] = (int) $row['address_id'];
                $row['user_id'] = (int) $row['user_id'];
                $row['is_default'] = (int) $row['is_default'];
            }
            unset($row);

            return $res->json(ResponseHelper::success($list));
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to fetch addresses: ' . $e->getMessage()));
        }
    }
}
