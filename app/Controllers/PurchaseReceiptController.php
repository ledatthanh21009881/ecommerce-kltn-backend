<?php
declare(strict_types=1);

/**
 * PurchaseReceiptController
 * 
 * Controller quản lý phiếu nhập hàng (Purchase Receipts) từ Suppliers.
 * Xử lý CRUD operations và confirm receipt để cập nhật stock.
 * 
 * Chức năng:
 * - List purchase receipts với filter
 * - Chi tiết receipt với items
 * - Tạo/sửa/xóa receipt
 * - Confirm receipt và tự động cập nhật stock
 * - Lịch sử nhập hàng theo supplier
 * 
 * Tái sử dụng:
 * - BaseController cho response formatting
 * - Database class cho transactions (quan trọng cho confirm)
 * - ResponseHelper cho pagination
 * 
 * @package App\Controllers
 * @author ShopSwift Team
 */

namespace App\Controllers;

use App\Core\{Controller, Request, Response, Container};
use App\Support\ResponseHelper;
use PDO;
use Exception;

class PurchaseReceiptController extends Controller
{
    private PDO $pdo;

    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->pdo = $container->database()->getConnection();
    }

    /**
     * GET /api/v1/purchase-receipts - Lấy danh sách purchase receipts
     * 
     * Query params:
     * - id: Chi tiết 1 receipt (show method)
     * - supplier_id: Lọc theo supplier
     * - status: Lọc theo status (pending, confirmed, cancelled)
     * - page, limit: Pagination
     */
    public function index(Request $req, Response $res)
    {
        try {
            // Nếu có id query param, trả về chi tiết
            $id = $req->query('id');
            if ($id) {
                return $this->show($req, $res);
            }

            // Pagination
            $page = max(1, (int)($req->query('page') ?? 1));
            $limit = max(1, min(100, (int)($req->query('limit') ?? 20)));
            $offset = ($page - 1) * $limit;

            // Filters
            $supplierId = $req->query('supplier_id');
            $status = $req->query('status');

            // Build query
            $where = [];
            $params = [];

            if ($supplierId) {
                $where[] = "pr.supplier_id = ?";
                $params[] = (int)$supplierId;
            }

            if ($status && in_array($status, ['pending', 'confirmed', 'cancelled'])) {
                $where[] = "pr.status = ?";
                $params[] = $status;
            }

            $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

            // Get total count
            $countSql = "SELECT COUNT(*) as total FROM purchase_receipts pr {$whereClause}";
            $countStmt = $this->pdo->prepare($countSql);
            $countStmt->execute($params);
            $total = (int)$countStmt->fetch(PDO::FETCH_ASSOC)['total'];

            // Get receipts with supplier info
            $sql = "SELECT 
                        pr.receipt_id,
                        pr.supplier_id,
                        pr.note,
                        pr.status,
                        pr.created_at,
                        pr.updated_at,
                        s.supplier_name,
                        s.contact_name as supplier_contact,
                        s.phone as supplier_phone,
                        s.email as supplier_email,
                        (SELECT COUNT(*) FROM purchase_items WHERE receipt_id = pr.receipt_id) as item_count,
                        (SELECT COALESCE(SUM(subtotal), 0) FROM purchase_items WHERE receipt_id = pr.receipt_id) as total_amount
                    FROM purchase_receipts pr
                    LEFT JOIN suppliers s ON pr.supplier_id = s.supplier_id
                    {$whereClause}
                    ORDER BY pr.created_at DESC
                    LIMIT ? OFFSET ?";
            
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $receipts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Convert string numbers to integers/floats
            foreach ($receipts as &$receipt) {
                $receipt['receipt_id'] = (int)$receipt['receipt_id'];
                $receipt['supplier_id'] = (int)$receipt['supplier_id'];
                $receipt['item_count'] = (int)$receipt['item_count'];
                $receipt['total_amount'] = (float)$receipt['total_amount'];
            }

            return $res->json(ResponseHelper::paginated($receipts, $total, $limit, $page));

        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to fetch purchase receipts: ' . $e->getMessage()));
        }
    }

    /**
     * GET /api/v1/purchase-receipts?id={id} - Chi tiết receipt với items
     */
    public function show(Request $req, Response $res)
    {
        try {
            $id = (int)($req->query('id') ?? 0);

            if ($id <= 0) {
                return $res->json(ResponseHelper::validationError(['id' => 'Invalid receipt ID']));
            }

            // Get receipt info
            $sql = "SELECT 
                        pr.*,
                        s.supplier_name,
                        s.contact_name as supplier_contact,
                        s.phone as supplier_phone,
                        s.email as supplier_email,
                        s.address as supplier_address
                    FROM purchase_receipts pr
                    LEFT JOIN suppliers s ON pr.supplier_id = s.supplier_id
                    WHERE pr.receipt_id = ?";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$id]);
            $receipt = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$receipt) {
                return $res->json(ResponseHelper::notFound('Purchase receipt not found'));
            }

            // Get receipt items
            $itemsSql = "SELECT 
                            pi.item_id,
                            pi.receipt_id,
                            pi.variant_id,
                            pi.quantity,
                            pi.unit_price,
                            pi.subtotal,
                            pi.note,
                            pv.sku,
                            pv.stock_quantity as current_stock,
                            pv.status as variant_status,
                            p.product_id,
                            p.product_name,
                            s.size_name
                        FROM purchase_items pi
                        JOIN product_variants pv ON pi.variant_id = pv.variant_id
                        JOIN products p ON pv.product_id = p.product_id
                        JOIN sizes s ON pv.size_id = s.size_id
                        WHERE pi.receipt_id = ?
                        ORDER BY pi.item_id";
            
            $itemsStmt = $this->pdo->prepare($itemsSql);
            $itemsStmt->execute([$id]);
            $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

            // Calculate total
            $totalAmount = array_sum(array_column($items, 'subtotal'));

            $receipt['receipt_id'] = (int)$receipt['receipt_id'];
            $receipt['supplier_id'] = (int)$receipt['supplier_id'];
            $receipt['items'] = $items;
            $receipt['total_amount'] = (float)$totalAmount;
            $receipt['item_count'] = count($items);

            return $res->json(ResponseHelper::success($receipt, 'Purchase receipt retrieved successfully'));

        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to fetch purchase receipt: ' . $e->getMessage()));
        }
    }

    /**
     * POST /api/v1/purchase-receipts - Tạo purchase receipt mới
     */
    public function store(Request $req, Response $res)
    {
        try {
            $data = $req->json();

            // Validation
            $errors = [];
            if (empty($data['supplier_id'])) {
                $errors['supplier_id'] = 'Supplier ID is required';
            }
            if (empty($data['items']) || !is_array($data['items']) || count($data['items']) === 0) {
                $errors['items'] = 'At least one item is required';
            }

            if (!empty($errors)) {
                return $res->json(ResponseHelper::validationError($errors));
            }

            // Validate supplier exists
            $supplierCheck = $this->pdo->prepare("SELECT supplier_id FROM suppliers WHERE supplier_id = ?");
            $supplierCheck->execute([$data['supplier_id']]);
            if (!$supplierCheck->fetch()) {
                return $res->json(ResponseHelper::validationError(['supplier_id' => 'Supplier not found']));
            }

            // Start transaction
            $db = $this->container->database();
            $db->beginTransaction();

            try {
                // Create receipt
                $receiptSql = "INSERT INTO purchase_receipts (supplier_id, note, status) VALUES (?, ?, 'pending')";
                $receiptStmt = $this->pdo->prepare($receiptSql);
                $receiptStmt->execute([
                    $data['supplier_id'],
                    $data['note'] ?? null
                ]);

                $receiptId = (int)$this->pdo->lastInsertId();

                // Insert items
                $itemSql = "INSERT INTO purchase_items (receipt_id, variant_id, quantity, unit_price, note) VALUES (?, ?, ?, ?, ?)";
                $itemStmt = $this->pdo->prepare($itemSql);

                foreach ($data['items'] as $item) {
                    // Validate variant exists
                    $variantCheck = $this->pdo->prepare("SELECT variant_id FROM product_variants WHERE variant_id = ?");
                    $variantCheck->execute([$item['variant_id']]);
                    if (!$variantCheck->fetch()) {
                        throw new Exception("Variant ID {$item['variant_id']} not found");
                    }

                    $itemStmt->execute([
                        $receiptId,
                        $item['variant_id'],
                        $item['quantity'],
                        $item['unit_price'],
                        $item['note'] ?? null
                    ]);
                }

                $db->commit();

                // Return created receipt
                return $this->showById($res, $receiptId, 'Purchase receipt created successfully', 201);

            } catch (Exception $e) {
                $db->rollback();
                throw $e;
            }

        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to create purchase receipt: ' . $e->getMessage()));
        }
    }

    /**
     * POST /api/v1/purchase-receipts/confirm?id={id} - Confirm receipt và cập nhật stock
     * 
     * Quan trọng: Sử dụng transaction để đảm bảo data consistency
     */
    public function confirm(Request $req, Response $res)
    {
        try {
            $id = (int)($req->query('id') ?? 0);

            if ($id <= 0) {
                return $res->json(ResponseHelper::validationError(['id' => 'Invalid receipt ID']));
            }

            // Check receipt exists and status
            $checkSql = "SELECT status FROM purchase_receipts WHERE receipt_id = ?";
            $checkStmt = $this->pdo->prepare($checkSql);
            $checkStmt->execute([$id]);
            $receipt = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if (!$receipt) {
                return $res->json(ResponseHelper::notFound('Purchase receipt not found'));
            }

            if ($receipt['status'] === 'confirmed') {
                return $res->json(ResponseHelper::error('Receipt already confirmed', 400));
            }

            if ($receipt['status'] === 'cancelled') {
                return $res->json(ResponseHelper::error('Cannot confirm cancelled receipt', 400));
            }

            // Start transaction
            $db = $this->container->database();
            $db->beginTransaction();

            try {
                // 1. Update receipt status
                $updateSql = "UPDATE purchase_receipts SET status = 'confirmed', updated_at = NOW() WHERE receipt_id = ?";
                $updateStmt = $this->pdo->prepare($updateSql);
                $updateStmt->execute([$id]);

                // 2. Get all items
                $itemsSql = "SELECT variant_id, quantity FROM purchase_items WHERE receipt_id = ?";
                $itemsStmt = $this->pdo->prepare($itemsSql);
                $itemsStmt->execute([$id]);
                $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

                // 3. Update stock for each variant
                $stockSql = "UPDATE product_variants 
                            SET stock_quantity = stock_quantity + ?, 
                                status = CASE 
                                    WHEN stock_quantity + ? > 0 THEN 'in_stock' 
                                    ELSE 'out_of_stock' 
                                END
                            WHERE variant_id = ?";
                $stockStmt = $this->pdo->prepare($stockSql);

                foreach ($items as $item) {
                    $quantity = (int)$item['quantity'];
                    $stockStmt->execute([$quantity, $quantity, $item['variant_id']]);
                }

                // 4. Update product total stock
                $productStockSql = "UPDATE products p
                                   SET stock = (
                                       SELECT COALESCE(SUM(stock_quantity), 0)
                                       FROM product_variants
                                       WHERE product_id = p.product_id
                                   )
                                   WHERE product_id IN (
                                       SELECT DISTINCT pv.product_id
                                       FROM purchase_items pi
                                       JOIN product_variants pv ON pi.variant_id = pv.variant_id
                                       WHERE pi.receipt_id = ?
                                   )";
                $productStockStmt = $this->pdo->prepare($productStockSql);
                $productStockStmt->execute([$id]);

                $db->commit();

                // Return updated receipt
                return $this->showById($res, $id, 'Purchase receipt confirmed and stock updated successfully');

            } catch (Exception $e) {
                $db->rollback();
                throw $e;
            }

        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to confirm purchase receipt: ' . $e->getMessage()));
        }
    }

    /**
     * PUT /api/v1/purchase-receipts?id={id} - Cập nhật receipt
     */
    public function update(Request $req, Response $res)
    {
        try {
            $id = (int)($req->query('id') ?? 0);
            $data = $req->json();

            if ($id <= 0) {
                return $res->json(ResponseHelper::validationError(['id' => 'Invalid receipt ID']));
            }

            // Check receipt exists and not confirmed
            $checkSql = "SELECT status FROM purchase_receipts WHERE receipt_id = ?";
            $checkStmt = $this->pdo->prepare($checkSql);
            $checkStmt->execute([$id]);
            $receipt = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if (!$receipt) {
                return $res->json(ResponseHelper::notFound('Purchase receipt not found'));
            }

            if ($receipt['status'] === 'confirmed') {
                return $res->json(ResponseHelper::error('Cannot update confirmed receipt', 400));
            }

            // Build update query
            $updates = [];
            $params = [];

            if (isset($data['supplier_id'])) {
                $updates[] = "supplier_id = ?";
                $params[] = $data['supplier_id'];
            }
            if (isset($data['note'])) {
                $updates[] = "note = ?";
                $params[] = $data['note'];
            }
            if (isset($data['status']) && in_array($data['status'], ['pending', 'cancelled'])) {
                $updates[] = "status = ?";
                $params[] = $data['status'];
            }

            if (empty($updates)) {
                return $res->json(ResponseHelper::validationError(['data' => 'No fields to update']));
            }

            $updates[] = "updated_at = NOW()";
            $params[] = $id;

            $sql = "UPDATE purchase_receipts SET " . implode(", ", $updates) . " WHERE receipt_id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return $this->showById($res, $id, 'Purchase receipt updated successfully');

        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to update purchase receipt: ' . $e->getMessage()));
        }
    }

    /**
     * DELETE /api/v1/purchase-receipts?id={id} - Xóa receipt
     */
    public function delete(Request $req, Response $res)
    {
        try {
            $id = (int)($req->query('id') ?? 0);

            if ($id <= 0) {
                return $res->json(ResponseHelper::validationError(['id' => 'Invalid receipt ID']));
            }

            // Check receipt exists and not confirmed
            $checkSql = "SELECT status FROM purchase_receipts WHERE receipt_id = ?";
            $checkStmt = $this->pdo->prepare($checkSql);
            $checkStmt->execute([$id]);
            $receipt = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if (!$receipt) {
                return $res->json(ResponseHelper::notFound('Purchase receipt not found'));
            }

            if ($receipt['status'] === 'confirmed') {
                return $res->json(ResponseHelper::error('Cannot delete confirmed receipt', 400));
            }

            // Start transaction
            $db = $this->container->database();
            $db->beginTransaction();

            try {
                // Delete items first (foreign key constraint)
                $deleteItemsSql = "DELETE FROM purchase_items WHERE receipt_id = ?";
                $deleteItemsStmt = $this->pdo->prepare($deleteItemsSql);
                $deleteItemsStmt->execute([$id]);

                // Delete receipt
                $deleteReceiptSql = "DELETE FROM purchase_receipts WHERE receipt_id = ?";
                $deleteReceiptStmt = $this->pdo->prepare($deleteReceiptSql);
                $deleteReceiptStmt->execute([$id]);

                $db->commit();

                return $res->json(ResponseHelper::success(null, 'Purchase receipt deleted successfully'));

            } catch (Exception $e) {
                $db->rollback();
                throw $e;
            }

        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to delete purchase receipt: ' . $e->getMessage()));
        }
    }

    /**
     * GET /api/v1/purchase-receipts/by-supplier?supplier_id={id} - Lịch sử nhập hàng từ supplier
     */
    public function getBySupplier(Request $req, Response $res)
    {
        try {
            $supplierId = (int)($req->query('supplier_id') ?? 0);

            if ($supplierId <= 0) {
                return $res->json(ResponseHelper::validationError(['supplier_id' => 'Invalid supplier ID']));
            }

            // Build query for receipts by supplier
            $page = max(1, (int)($req->query('page') ?? 1));
            $limit = max(1, min(100, (int)($req->query('limit') ?? 20)));
            $offset = ($page - 1) * $limit;

            $status = $req->query('status');
            $where = ["pr.supplier_id = ?"];
            $params = [$supplierId];

            if ($status && in_array($status, ['pending', 'confirmed', 'cancelled'])) {
                $where[] = "pr.status = ?";
                $params[] = $status;
            }

            $whereClause = "WHERE " . implode(" AND ", $where);

            // Get total count
            $countSql = "SELECT COUNT(*) as total FROM purchase_receipts pr {$whereClause}";
            $countStmt = $this->pdo->prepare($countSql);
            $countStmt->execute($params);
            $total = (int)$countStmt->fetch(PDO::FETCH_ASSOC)['total'];

            // Get receipts
            $sql = "SELECT 
                        pr.receipt_id,
                        pr.supplier_id,
                        pr.note,
                        pr.status,
                        pr.created_at,
                        pr.updated_at,
                        s.supplier_name,
                        (SELECT COUNT(*) FROM purchase_items WHERE receipt_id = pr.receipt_id) as item_count,
                        (SELECT COALESCE(SUM(subtotal), 0) FROM purchase_items WHERE receipt_id = pr.receipt_id) as total_amount
                    FROM purchase_receipts pr
                    LEFT JOIN suppliers s ON pr.supplier_id = s.supplier_id
                    {$whereClause}
                    ORDER BY pr.created_at DESC
                    LIMIT ? OFFSET ?";
            
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $receipts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($receipts as &$receipt) {
                $receipt['receipt_id'] = (int)$receipt['receipt_id'];
                $receipt['supplier_id'] = (int)$receipt['supplier_id'];
                $receipt['item_count'] = (int)$receipt['item_count'];
                $receipt['total_amount'] = (float)$receipt['total_amount'];
            }

            return $res->json(ResponseHelper::paginated($receipts, $total, $limit, $page));

        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to fetch receipts by supplier: ' . $e->getMessage()));
        }
    }

    /**
     * Helper method: Lấy receipt by ID
     */
    private function showById(Response $res, int $id, string $message = 'Purchase receipt retrieved successfully', int $statusCode = 200)
    {
        // Get receipt info
        $sql = "SELECT 
                    pr.*,
                    s.supplier_name,
                    s.contact_name as supplier_contact,
                    s.phone as supplier_phone,
                    s.email as supplier_email,
                    s.address as supplier_address
                FROM purchase_receipts pr
                LEFT JOIN suppliers s ON pr.supplier_id = s.supplier_id
                WHERE pr.receipt_id = ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        $receipt = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$receipt) {
            return $res->json(ResponseHelper::notFound('Purchase receipt not found'), 404);
        }

        // Get receipt items
        $itemsSql = "SELECT 
                        pi.item_id,
                        pi.receipt_id,
                        pi.variant_id,
                        pi.quantity,
                        pi.unit_price,
                        pi.subtotal,
                        pi.note,
                        pv.sku,
                        pv.stock_quantity as current_stock,
                        pv.status as variant_status,
                        p.product_id,
                        p.product_name,
                        s.size_name
                    FROM purchase_items pi
                    JOIN product_variants pv ON pi.variant_id = pv.variant_id
                    JOIN products p ON pv.product_id = p.product_id
                    JOIN sizes s ON pv.size_id = s.size_id
                    WHERE pi.receipt_id = ?
                    ORDER BY pi.item_id";
        
        $itemsStmt = $this->pdo->prepare($itemsSql);
        $itemsStmt->execute([$id]);
        $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

        $totalAmount = array_sum(array_column($items, 'subtotal'));

        $receipt['receipt_id'] = (int)$receipt['receipt_id'];
        $receipt['supplier_id'] = (int)$receipt['supplier_id'];
        $receipt['items'] = $items;
        $receipt['total_amount'] = (float)$totalAmount;
        $receipt['item_count'] = count($items);

        return $res->json(ResponseHelper::success($receipt, $message), $statusCode);
    }
}

