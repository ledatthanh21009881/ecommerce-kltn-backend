<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Controller, Request, Response, Container};
use App\Support\ResponseHelper;
use PDO;
use Exception;

class StockAdjustmentController extends Controller
{
    private PDO $pdo;

    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->pdo = $container->database()->getConnection();
    }

    public function index(Request $req, Response $res)
    {
        try {
            $id = (int)($req->query('id') ?? 0);
            if ($id > 0) {
                return $this->show($req, $res);
            }

            $page = max(1, (int)($req->query('page') ?? 1));
            $limit = max(1, min(100, (int)($req->query('limit') ?? 20)));
            $offset = ($page - 1) * $limit;
            $status = $req->query('status');

            $where = [];
            $params = [];
            if ($status && in_array($status, ['draft', 'confirmed', 'cancelled'])) {
                $where[] = "sa.status = ?";
                $params[] = $status;
            }
            $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

            $countSql = "SELECT COUNT(*) as total FROM stock_adjustments sa {$whereClause}";
            $countStmt = $this->pdo->prepare($countSql);
            $countStmt->execute($params);
            $total = (int)$countStmt->fetch(PDO::FETCH_ASSOC)['total'];

            $sql = "SELECT
                        sa.adjustment_id,
                        sa.reason,
                        sa.status,
                        sa.note,
                        sa.created_by,
                        sa.confirmed_by,
                        sa.confirmed_at,
                        sa.created_at,
                        sa.updated_at,
                        (SELECT COUNT(*) FROM stock_adjustment_items sai WHERE sai.adjustment_id = sa.adjustment_id) as item_count,
                        (SELECT GROUP_CONCAT(DISTINCT p.product_name ORDER BY p.product_name SEPARATOR ', ')
                         FROM stock_adjustment_items sai
                         JOIN product_variants pv ON sai.variant_id = pv.variant_id
                         JOIN products p ON pv.product_id = p.product_id
                         WHERE sai.adjustment_id = sa.adjustment_id) as product_names,
                        (SELECT GROUP_CONCAT(DISTINCT s.size_name ORDER BY s.size_name SEPARATOR ', ')
                         FROM stock_adjustment_items sai
                         JOIN product_variants pv ON sai.variant_id = pv.variant_id
                         JOIN sizes s ON pv.size_id = s.size_id
                         WHERE sai.adjustment_id = sa.adjustment_id) as size_names,
                        (SELECT COALESCE(SUM(sai.quantity_change), 0) FROM stock_adjustment_items sai WHERE sai.adjustment_id = sa.adjustment_id) as total_change
                    FROM stock_adjustments sa
                    {$whereClause}
                    ORDER BY sa.created_at DESC
                    LIMIT ? OFFSET ?";

            $params[] = $limit;
            $params[] = $offset;
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($items as &$item) {
                $item['adjustment_id'] = (int)$item['adjustment_id'];
                $item['item_count'] = (int)$item['item_count'];
                $item['total_change'] = (int)$item['total_change'];
            }

            return $res->json(ResponseHelper::paginated($items, $total, $limit, $page));
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to fetch stock adjustments: ' . $e->getMessage()));
        }
    }

    public function show(Request $req, Response $res)
    {
        try {
            $id = (int)($req->query('id') ?? 0);
            if ($id <= 0) {
                return $res->json(ResponseHelper::validationError(['id' => 'Invalid adjustment ID']));
            }

            return $this->showById($res, $id);
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to fetch stock adjustment: ' . $e->getMessage()));
        }
    }

    public function store(Request $req, Response $res)
    {
        try {
            $data = $req->json();
            $errors = [];

            if (empty($data['reason'])) {
                $errors['reason'] = 'Reason is required';
            }
            if (empty($data['items']) || !is_array($data['items'])) {
                $errors['items'] = 'At least one item is required';
            }
            if (!empty($errors)) {
                return $res->json(ResponseHelper::validationError($errors));
            }

            $db = $this->container->database();
            $db->beginTransaction();
            try {
                $createdBy = $this->getCurrentUserId($req);
                $insertAdjustment = $this->pdo->prepare(
                    "INSERT INTO stock_adjustments (reason, status, note, created_by) VALUES (?, 'draft', ?, ?)"
                );
                $insertAdjustment->execute([
                    trim((string)$data['reason']),
                    $data['note'] ?? null,
                    $createdBy
                ]);
                $adjustmentId = (int)$this->pdo->lastInsertId();

                $insertItem = $this->pdo->prepare(
                    "INSERT INTO stock_adjustment_items (adjustment_id, variant_id, quantity_change, note) VALUES (?, ?, ?, ?)"
                );

                $seenVariantIds = [];
                foreach ($data['items'] as $item) {
                    $variantId = (int)($item['variant_id'] ?? 0);
                    $quantityChange = (int)($item['quantity_change'] ?? 0);
                    if ($variantId <= 0) {
                        throw new Exception('Invalid variant_id');
                    }
                    if ($quantityChange === 0) {
                        throw new Exception('Quantity change cannot be 0');
                    }
                    if ($quantityChange > 0) {
                        throw new Exception('Stock adjustment only allows decreasing quantity. Use purchase receipts to increase stock.');
                    }
                    if (in_array($variantId, $seenVariantIds, true)) {
                        throw new Exception('Duplicate variants are not allowed');
                    }
                    $seenVariantIds[] = $variantId;

                    $variantStmt = $this->pdo->prepare("SELECT variant_id FROM product_variants WHERE variant_id = ?");
                    $variantStmt->execute([$variantId]);
                    if (!$variantStmt->fetch(PDO::FETCH_ASSOC)) {
                        throw new Exception("Variant ID {$variantId} not found");
                    }

                    $insertItem->execute([
                        $adjustmentId,
                        $variantId,
                        $quantityChange,
                        $item['note'] ?? null
                    ]);
                }

                $db->commit();
                return $this->showById($res, $adjustmentId, 'Stock adjustment created successfully', 201);
            } catch (Exception $e) {
                $db->rollback();
                throw $e;
            }
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to create stock adjustment: ' . $e->getMessage()));
        }
    }

    public function confirm(Request $req, Response $res)
    {
        try {
            $id = (int)($req->query('id') ?? 0);
            if ($id <= 0) {
                return $res->json(ResponseHelper::validationError(['id' => 'Invalid adjustment ID']));
            }

            $checkStmt = $this->pdo->prepare("SELECT status FROM stock_adjustments WHERE adjustment_id = ?");
            $checkStmt->execute([$id]);
            $adjustment = $checkStmt->fetch(PDO::FETCH_ASSOC);
            if (!$adjustment) {
                return $res->json(ResponseHelper::notFound('Stock adjustment not found'));
            }
            if ($adjustment['status'] === 'confirmed') {
                return $res->json(ResponseHelper::error('Stock adjustment already confirmed', 400));
            }
            if ($adjustment['status'] === 'cancelled') {
                return $res->json(ResponseHelper::error('Cannot confirm cancelled stock adjustment', 400));
            }

            $db = $this->container->database();
            $db->beginTransaction();
            try {
                $itemsStmt = $this->pdo->prepare("SELECT variant_id, quantity_change FROM stock_adjustment_items WHERE adjustment_id = ?");
                $itemsStmt->execute([$id]);
                $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
                if (empty($items)) {
                    throw new Exception('Stock adjustment has no items');
                }

                $affectedProductIds = [];
                foreach ($items as $item) {
                    $variantId = (int)$item['variant_id'];
                    $delta = (int)$item['quantity_change'];
                    if ($delta >= 0) {
                        throw new Exception('Invalid adjustment line detected. Only negative quantity changes are allowed.');
                    }

                    $variantStmt = $this->pdo->prepare("SELECT product_id, stock_quantity FROM product_variants WHERE variant_id = ? FOR UPDATE");
                    $variantStmt->execute([$variantId]);
                    $variant = $variantStmt->fetch(PDO::FETCH_ASSOC);
                    if (!$variant) {
                        throw new Exception("Variant ID {$variantId} not found");
                    }

                    $newQty = (int)$variant['stock_quantity'] + $delta;
                    if ($newQty < 0) {
                        throw new Exception("Insufficient stock for variant ID {$variantId}");
                    }

                    $updateVariantStmt = $this->pdo->prepare(
                        "UPDATE product_variants
                         SET stock_quantity = ?,
                             status = CASE WHEN ? > 0 THEN 'in_stock' ELSE 'out_of_stock' END
                         WHERE variant_id = ?"
                    );
                    $updateVariantStmt->execute([$newQty, $newQty, $variantId]);
                    $affectedProductIds[] = (int)$variant['product_id'];
                }

                $affectedProductIds = array_values(array_unique($affectedProductIds));
                if (!empty($affectedProductIds)) {
                    $placeholders = implode(',', array_fill(0, count($affectedProductIds), '?'));
                    $updateProductsSql = "UPDATE products p
                                          SET stock = (
                                              SELECT COALESCE(SUM(stock_quantity), 0)
                                              FROM product_variants
                                              WHERE product_id = p.product_id
                                          )
                                          WHERE p.product_id IN ({$placeholders})";
                    $updateProductsStmt = $this->pdo->prepare($updateProductsSql);
                    $updateProductsStmt->execute($affectedProductIds);
                }

                $confirmedBy = $this->getCurrentUserId($req);
                $updateAdjustmentStmt = $this->pdo->prepare(
                    "UPDATE stock_adjustments
                     SET status = 'confirmed', confirmed_by = ?, confirmed_at = NOW(), updated_at = NOW()
                     WHERE adjustment_id = ?"
                );
                $updateAdjustmentStmt->execute([$confirmedBy, $id]);

                $db->commit();
                return $this->showById($res, $id, 'Stock adjustment confirmed successfully');
            } catch (Exception $e) {
                $db->rollback();
                throw $e;
            }
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to confirm stock adjustment: ' . $e->getMessage()));
        }
    }

    public function delete(Request $req, Response $res)
    {
        try {
            $id = (int)($req->query('id') ?? 0);
            if ($id <= 0) {
                return $res->json(ResponseHelper::validationError(['id' => 'Invalid adjustment ID']));
            }

            $checkStmt = $this->pdo->prepare("SELECT status FROM stock_adjustments WHERE adjustment_id = ?");
            $checkStmt->execute([$id]);
            $adjustment = $checkStmt->fetch(PDO::FETCH_ASSOC);
            if (!$adjustment) {
                return $res->json(ResponseHelper::notFound('Stock adjustment not found'));
            }
            if ($adjustment['status'] === 'confirmed') {
                return $res->json(ResponseHelper::error('Cannot delete confirmed stock adjustment', 400));
            }

            $db = $this->container->database();
            $db->beginTransaction();
            try {
                $deleteItemsStmt = $this->pdo->prepare("DELETE FROM stock_adjustment_items WHERE adjustment_id = ?");
                $deleteItemsStmt->execute([$id]);

                $deleteAdjustmentStmt = $this->pdo->prepare("DELETE FROM stock_adjustments WHERE adjustment_id = ?");
                $deleteAdjustmentStmt->execute([$id]);

                $db->commit();
                return $res->json(ResponseHelper::success(null, 'Stock adjustment deleted successfully'));
            } catch (Exception $e) {
                $db->rollback();
                throw $e;
            }
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to delete stock adjustment: ' . $e->getMessage()));
        }
    }

    public function cancel(Request $req, Response $res)
    {
        try {
            $id = (int)($req->query('id') ?? 0);
            if ($id <= 0) {
                return $res->json(ResponseHelper::validationError(['id' => 'Invalid adjustment ID']));
            }

            $checkStmt = $this->pdo->prepare("SELECT status FROM stock_adjustments WHERE adjustment_id = ?");
            $checkStmt->execute([$id]);
            $adjustment = $checkStmt->fetch(PDO::FETCH_ASSOC);
            if (!$adjustment) {
                return $res->json(ResponseHelper::notFound('Stock adjustment not found'));
            }
            if ($adjustment['status'] === 'confirmed') {
                return $res->json(ResponseHelper::error('Cannot cancel confirmed stock adjustment', 400));
            }
            if ($adjustment['status'] === 'cancelled') {
                return $res->json(ResponseHelper::error('Stock adjustment already cancelled', 400));
            }

            $updateStmt = $this->pdo->prepare(
                "UPDATE stock_adjustments SET status = 'cancelled', updated_at = NOW() WHERE adjustment_id = ?"
            );
            $updateStmt->execute([$id]);

            return $this->showById($res, $id, 'Stock adjustment cancelled successfully');
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to cancel stock adjustment: ' . $e->getMessage()));
        }
    }

    private function showById(Response $res, int $id, string $message = 'Stock adjustment retrieved successfully', int $statusCode = 200)
    {
        $headerStmt = $this->pdo->prepare("SELECT * FROM stock_adjustments WHERE adjustment_id = ?");
        $headerStmt->execute([$id]);
        $adjustment = $headerStmt->fetch(PDO::FETCH_ASSOC);
        if (!$adjustment) {
            return $res->json(ResponseHelper::notFound('Stock adjustment not found'), 404);
        }

        $itemsStmt = $this->pdo->prepare(
            "SELECT
                sai.item_id,
                sai.adjustment_id,
                sai.variant_id,
                sai.quantity_change,
                sai.note,
                pv.product_id,
                pv.sku,
                pv.stock_quantity as current_stock,
                p.product_name,
                s.size_name
             FROM stock_adjustment_items sai
             JOIN product_variants pv ON sai.variant_id = pv.variant_id
             JOIN products p ON pv.product_id = p.product_id
             JOIN sizes s ON pv.size_id = s.size_id
             WHERE sai.adjustment_id = ?
             ORDER BY sai.item_id"
        );
        $itemsStmt->execute([$id]);
        $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

        $adjustment['adjustment_id'] = (int)$adjustment['adjustment_id'];
        $adjustment['item_count'] = count($items);
        $adjustment['total_change'] = (int)array_sum(array_map(static fn(array $item): int => (int)$item['quantity_change'], $items));
        $adjustment['items'] = $items;

        return $res->json(ResponseHelper::success($adjustment, $message), $statusCode);
    }

    private function getCurrentUserId(Request $req): ?int
    {
        $user = $req->getAttribute('user');
        if (is_array($user) && isset($user['user_id'])) {
            return (int)$user['user_id'];
        }
        if (is_object($user) && isset($user->user_id)) {
            return (int)$user->user_id;
        }
        return null;
    }
}

