<?php
declare(strict_types=1);

/**
 * SupplierController
 * 
 * Controller quản lý nhà cung cấp (Suppliers).
 * Xử lý CRUD operations và thống kê cho Supplier Management.
 * 
 * Chức năng:
 * - List suppliers với search, filter, pagination
 * - Chi tiết supplier
 * - Tạo/sửa/xóa supplier
 * - Thống kê suppliers theo status
 * 
 * Tái sử dụng:
 * - BaseController cho response formatting
 * - ResponseHelper cho pagination
 * - Database class cho transactions
 * 
 * @package App\Controllers
 * @author ShopSwift Team
 */

namespace App\Controllers;

use App\Core\{Controller, Request, Response, Container};
use App\Support\ResponseHelper;
use PDO;
use Exception;

class SupplierController extends Controller
{
    private PDO $pdo;

    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->pdo = $container->database()->getConnection();
    }

    /**
     * GET /api/v1/suppliers - Lấy danh sách suppliers với search, filter, pagination
     * 
     * Query params:
     * - search: Tìm kiếm theo tên, contact, email
     * - status: Lọc theo status (active, inactive, suspended)
     * - page: Số trang (default: 1)
     * - limit: Số items/trang (default: 20)
     * - id: Lấy chi tiết 1 supplier (show method)
     */
    public function index(Request $req, Response $res)
    {
        try {
            // Nếu có id query param, trả về chi tiết supplier
            $id = $req->query('id');
            if ($id) {
                return $this->show($req, $res);
            }

            // Pagination
            $page = max(1, (int)($req->query('page') ?? 1));
            $limit = max(1, min(100, (int)($req->query('limit') ?? 20)));
            $offset = ($page - 1) * $limit;

            // Filters
            $search = $req->query('search');
            $status = $req->query('status');

            // Build query
            $where = [];
            $params = [];

            if ($search) {
                $where[] = "(supplier_name LIKE ? OR contact_name LIKE ? OR email LIKE ? OR phone LIKE ?)";
                $searchParam = "%{$search}%";
                $params[] = $searchParam;
                $params[] = $searchParam;
                $params[] = $searchParam;
                $params[] = $searchParam;
            }

            if ($status && in_array($status, ['active', 'inactive', 'suspended'])) {
                $where[] = "status = ?";
                $params[] = $status;
            }

            $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

            // Get total count
            $countSql = "SELECT COUNT(*) as total FROM suppliers {$whereClause}";
            $countStmt = $this->pdo->prepare($countSql);
            $countStmt->execute($params);
            $total = (int)$countStmt->fetch(PDO::FETCH_ASSOC)['total'];

            // Get suppliers
            $sql = "SELECT 
                        supplier_id,
                        supplier_name,
                        contact_name,
                        phone,
                        email,
                        address,
                        status,
                        created_at,
                        updated_at
                    FROM suppliers 
                    {$whereClause}
                    ORDER BY created_at DESC
                    LIMIT ? OFFSET ?";
            
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $res->json(ResponseHelper::paginated($suppliers, $total, $limit, $page));

        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to fetch suppliers: ' . $e->getMessage()));
        }
    }

    /**
     * GET /api/v1/suppliers?id={id} - Lấy chi tiết supplier
     */
    public function show(Request $req, Response $res)
    {
        try {
            $id = (int)($req->query('id') ?? 0);

            if ($id <= 0) {
                return $res->json(ResponseHelper::validationError(['id' => 'Invalid supplier ID']));
            }

            $sql = "SELECT 
                        supplier_id,
                        supplier_name,
                        contact_name,
                        phone,
                        email,
                        address,
                        status,
                        created_at,
                        updated_at
                    FROM suppliers 
                    WHERE supplier_id = ?";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$id]);
            $supplier = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$supplier) {
                return $res->json(ResponseHelper::notFound('Supplier not found'));
            }

            return $res->json(ResponseHelper::success($supplier, 'Supplier retrieved successfully'));

        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to fetch supplier: ' . $e->getMessage()));
        }
    }

    /**
     * POST /api/v1/suppliers - Tạo supplier mới
     */
    public function store(Request $req, Response $res)
    {
        try {
            $data = $req->json();

            // Validation
            $errors = [];
            if (empty($data['supplier_name'])) {
                $errors['supplier_name'] = 'Supplier name is required';
            }
            if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Invalid email format';
            }
            if (!empty($data['status']) && !in_array($data['status'], ['active', 'inactive', 'suspended'])) {
                $errors['status'] = 'Invalid status';
            }

            if (!empty($errors)) {
                return $res->json(ResponseHelper::validationError($errors));
            }

            // Insert supplier
            $sql = "INSERT INTO suppliers (supplier_name, contact_name, phone, email, address, status) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->pdo->prepare($sql);
            $success = $stmt->execute([
                $data['supplier_name'],
                $data['contact_name'] ?? null,
                $data['phone'] ?? null,
                $data['email'] ?? null,
                $data['address'] ?? null,
                $data['status'] ?? 'active'
            ]);

            if (!$success) {
                return $res->json(ResponseHelper::serverError('Failed to create supplier'));
            }

            $supplierId = (int)$this->pdo->lastInsertId();

            // Get created supplier
            return $this->showById($res, $supplierId, 'Supplier created successfully', 201);

        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to create supplier: ' . $e->getMessage()));
        }
    }

    /**
     * PUT /api/v1/suppliers?id={id} - Cập nhật supplier
     */
    public function update(Request $req, Response $res)
    {
        try {
            $id = (int)($req->query('id') ?? 0);
            $data = $req->json();

            if ($id <= 0) {
                return $res->json(ResponseHelper::validationError(['id' => 'Invalid supplier ID']));
            }

            // Check if supplier exists
            $checkSql = "SELECT supplier_id FROM suppliers WHERE supplier_id = ?";
            $checkStmt = $this->pdo->prepare($checkSql);
            $checkStmt->execute([$id]);
            if (!$checkStmt->fetch()) {
                return $res->json(ResponseHelper::notFound('Supplier not found'));
            }

            // Validation
            $errors = [];
            if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Invalid email format';
            }
            if (!empty($data['status']) && !in_array($data['status'], ['active', 'inactive', 'suspended'])) {
                $errors['status'] = 'Invalid status';
            }

            if (!empty($errors)) {
                return $res->json(ResponseHelper::validationError($errors));
            }

            // Build update query
            $updates = [];
            $params = [];

            if (isset($data['supplier_name'])) {
                $updates[] = "supplier_name = ?";
                $params[] = $data['supplier_name'];
            }
            if (isset($data['contact_name'])) {
                $updates[] = "contact_name = ?";
                $params[] = $data['contact_name'];
            }
            if (isset($data['phone'])) {
                $updates[] = "phone = ?";
                $params[] = $data['phone'];
            }
            if (isset($data['email'])) {
                $updates[] = "email = ?";
                $params[] = $data['email'];
            }
            if (isset($data['address'])) {
                $updates[] = "address = ?";
                $params[] = $data['address'];
            }
            if (isset($data['status'])) {
                $updates[] = "status = ?";
                $params[] = $data['status'];
            }

            if (empty($updates)) {
                return $res->json(ResponseHelper::validationError(['data' => 'No fields to update']));
            }

            $updates[] = "updated_at = NOW()";
            $params[] = $id;

            $sql = "UPDATE suppliers SET " . implode(", ", $updates) . " WHERE supplier_id = ?";
            $stmt = $this->pdo->prepare($sql);
            $success = $stmt->execute($params);

            if (!$success) {
                return $res->json(ResponseHelper::serverError('Failed to update supplier'));
            }

            return $this->showById($res, $id, 'Supplier updated successfully');

        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to update supplier: ' . $e->getMessage()));
        }
    }

    /**
     * DELETE /api/v1/suppliers?id={id} - Xóa supplier
     */
    public function delete(Request $req, Response $res)
    {
        try {
            $id = (int)($req->query('id') ?? 0);

            if ($id <= 0) {
                return $res->json(ResponseHelper::validationError(['id' => 'Invalid supplier ID']));
            }

            // Check if supplier exists
            $checkSql = "SELECT supplier_id FROM suppliers WHERE supplier_id = ?";
            $checkStmt = $this->pdo->prepare($checkSql);
            $checkStmt->execute([$id]);
            if (!$checkStmt->fetch()) {
                return $res->json(ResponseHelper::notFound('Supplier not found'));
            }

            // Check if supplier has purchase receipts
            $checkReceiptsSql = "SELECT COUNT(*) as count FROM purchase_receipts WHERE supplier_id = ?";
            $checkReceiptsStmt = $this->pdo->prepare($checkReceiptsSql);
            $checkReceiptsStmt->execute([$id]);
            $receiptCount = (int)$checkReceiptsStmt->fetch(PDO::FETCH_ASSOC)['count'];

            if ($receiptCount > 0) {
                return $res->json(ResponseHelper::error(
                    "Cannot delete supplier with {$receiptCount} purchase receipt(s). Please delete or reassign receipts first.",
                    400
                ));
            }

            // Delete supplier
            $sql = "DELETE FROM suppliers WHERE supplier_id = ?";
            $stmt = $this->pdo->prepare($sql);
            $success = $stmt->execute([$id]);

            if (!$success) {
                return $res->json(ResponseHelper::serverError('Failed to delete supplier'));
            }

            return $res->json(ResponseHelper::success(null, 'Supplier deleted successfully'));

        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to delete supplier: ' . $e->getMessage()));
        }
    }

    /**
     * GET /api/v1/suppliers/stats - Thống kê suppliers
     */
    public function getStats(Request $req, Response $res)
    {
        try {
            $sql = "SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                        SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive,
                        SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) as suspended
                    FROM suppliers";
            
            $stmt = $this->pdo->query($sql);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);

            // Convert strings to integers
            $stats['total'] = (int)$stats['total'];
            $stats['active'] = (int)$stats['active'];
            $stats['inactive'] = (int)$stats['inactive'];
            $stats['suspended'] = (int)$stats['suspended'];

            return $res->json(ResponseHelper::success($stats, 'Stats retrieved successfully'));

        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to fetch stats: ' . $e->getMessage()));
        }
    }

    /**
     * Helper method: Lấy supplier by ID
     */
    private function showById(Response $res, int $id, string $message = 'Supplier retrieved successfully', int $statusCode = 200)
    {
        $sql = "SELECT 
                    supplier_id,
                    supplier_name,
                    contact_name,
                    phone,
                    email,
                    address,
                    status,
                    created_at,
                    updated_at
                FROM suppliers 
                WHERE supplier_id = ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        $supplier = $stmt->fetch(PDO::FETCH_ASSOC);

        return $res->json(ResponseHelper::success($supplier, $message), $statusCode);
    }
}

