<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Controller, Container, Request, Response};
use App\Domain\Products\{Product, Inventory};
use App\Support\ResponseHelper;
use PDO;

class InventoryController extends Controller
{
    private $pdo;
    private $product;
    private $inventory;

    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->pdo = $container->database()->getConnection();
        $this->product = new Product($container->database());
        $this->inventory = new Inventory($container->database());
    }

    /**
     * GET /api/v1/inventory - Lấy danh sách tồn kho
     */
    public function index(Request $req, Response $res)
    {
        try {
            // Lấy query parameters
            $filters = [
                'product_id' => $req->query('product_id'),
                'size_id' => $req->query('size_id'),
                'status' => $req->query('status'),
                'limit' => $req->query('limit', 1000),
                'offset' => $req->query('offset', 0)
            ];

            // Remove null values
            $filters = array_filter($filters, function($value) {
                return $value !== null && $value !== '';
            });

            // Get variants using domain model
            $variants = $this->inventory->getAllWithDetails($filters);
            $totalCount = $this->inventory->getCount($filters);

            return $res->json([
                'success' => true,
                'message' => 'Inventory list retrieved successfully',
                'status_code' => 200,
                'data' => [
                    'variants' => $variants,
                    'total_count' => $totalCount,
                    'limit' => (int)($filters['limit'] ?? 1000),
                    'offset' => (int)($filters['offset'] ?? 0)
                ]
            ]);

        } catch (\Exception $e) {
            return $res->json([
                'success' => false,
                'message' => "Failed to retrieve inventory: " . $e->getMessage(),
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * GET /api/v1/inventory/{variant_id} - Lấy chi tiết variant
     */
    public function show(Request $req, Response $res)
    {
        try {
            $variantId = $req->getAttribute('id');
            $variant = $this->inventory->getById($variantId);

            if (!$variant) {
                return $res->json([
                    'success' => false,
                    'message' => 'Variant not found',
                    'status_code' => 404
                ], 404);
            }

            return $res->json([
                'success' => true,
                'message' => 'Variant retrieved successfully',
                'status_code' => 200,
                'data' => $variant
            ]);

        } catch (\Exception $e) {
            return $res->json([
                'success' => false,
                'message' => "Failed to retrieve variant: " . $e->getMessage(),
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * POST /api/v1/inventory - Tạo variant mới
     */
    public function store(Request $req, Response $res)
    {
        try {
            $input = $req->json();
            
            // Debug logging
            error_log("Inventory store input: " . json_encode($input));

            // Validate input
            if (!$input['product_id'] || !$input['size_id'] || !$input['sku'] || !isset($input['stock_quantity']) || !$input['status']) {
                error_log("Missing required fields: " . json_encode($input));
                return $res->json(['success' => false, 'message' => 'Missing required fields', 'status_code' => 400], 400);
            }
            
            if (!is_numeric($input['product_id']) || !is_numeric($input['size_id']) || !is_numeric($input['stock_quantity'])) {
                return $res->json(['success' => false, 'message' => 'Invalid numeric fields', 'status_code' => 400], 400);
            }
            
            if ($input['stock_quantity'] < 0) {
                return $res->json(['success' => false, 'message' => 'Stock quantity cannot be negative', 'status_code' => 400], 400);
            }
            
            if (!in_array($input['status'], ['in_stock', 'out_of_stock'])) {
                return $res->json(['success' => false, 'message' => 'Invalid status', 'status_code' => 400], 400);
            }

            // Check if product exists
            if (!$this->inventory->productExists($input['product_id'])) {
                error_log("Product not found: " . $input['product_id']);
                return $res->json(['success' => false, 'message' => 'Product not found', 'status_code' => 400], 400);
            }

            // Check if size exists
            if (!$this->inventory->sizeExists($input['size_id'])) {
                error_log("Size not found: " . $input['size_id']);
                return $res->json(['success' => false, 'message' => 'Size not found', 'status_code' => 400], 400);
            }

            // Check if SKU already exists
            if ($this->inventory->skuExists($input['sku'])) {
                error_log("SKU already exists: " . $input['sku']);
                return $res->json(['success' => false, 'message' => 'SKU already exists', 'status_code' => 400], 400);
            }

            // Check if variant already exists for this product and size
            if ($this->inventory->variantExists($input['product_id'], $input['size_id'])) {
                error_log("Variant already exists for product: " . $input['product_id'] . " size: " . $input['size_id']);
                return $res->json(['success' => false, 'message' => 'Variant already exists for this product and size', 'status_code' => 400], 400);
            }

            // Create new variant
            $variantId = $this->inventory->create($input);

            // Log activity
            $this->logActivity('product_variant', $variantId, 'create', $input);

            // Get created variant
            $createdVariant = $this->inventory->getById($variantId);

            return $res->json([
                'success' => true,
                'message' => 'Variant created successfully',
                'status_code' => 201,
                'data' => $createdVariant
            ], 201);

        } catch (\Exception $e) {
            return $res->json([
                'success' => false,
                'message' => "Failed to create variant: " . $e->getMessage(),
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * PUT /api/v1/inventory/{variant_id} - Cập nhật variant
     */
    public function update(Request $req, Response $res)
    {
        try {
            $variantId = $req->getAttribute('id');
            $input = $req->json();

            // Check if variant exists
            $existingVariant = $this->inventory->getById($variantId);
            if (!$existingVariant) {
                return $res->json(['success' => false, 'message' => 'Variant not found', 'status_code' => 404], 404);
            }

            // Validate input
            if (array_key_exists('stock_quantity', $input)) {
                return $res->json([
                    'success' => false,
                    'message' => 'Direct stock updates are not allowed. Use purchase receipts to increase stock or stock adjustments to decrease stock.',
                    'status_code' => 400
                ], 400);
            }
            if (isset($input['status'])) {
                if (!in_array($input['status'], ['in_stock', 'out_of_stock'])) {
                    return $res->json(['success' => false, 'message' => 'Invalid status', 'status_code' => 400], 400);
                }
            }
            if (isset($input['size_id'])) {
                if (!is_numeric($input['size_id'])) {
                    return $res->json(['success' => false, 'message' => 'Invalid size ID', 'status_code' => 400], 400);
                }
                // Check if size exists
                if (!$this->inventory->sizeExists($input['size_id'])) {
                    return $res->json(['success' => false, 'message' => 'Size not found', 'status_code' => 400], 400);
                }
                // Check if variant already exists for this product and new size (excluding current variant)
                if ($this->inventory->variantExists($existingVariant['product_id'], $input['size_id'], $variantId)) {
                    return $res->json(['success' => false, 'message' => 'Variant already exists for this product and size', 'status_code' => 400], 400);
                }
            }
            if (isset($input['sku'])) {
                // Check if SKU already exists (excluding current variant)
                if ($this->inventory->skuExists($input['sku'], $variantId)) {
                    return $res->json(['success' => false, 'message' => 'SKU already exists', 'status_code' => 400], 400);
                }
            }

            // Update variant
            $success = $this->inventory->update($variantId, $input);
            
            if (!$success) {
                return $res->json(['success' => false, 'message' => 'No fields to update', 'status_code' => 400], 400);
            }

            // Log activity
            $this->logActivity('product_variant', $variantId, 'update', $input);

            // Get updated variant
            $updatedVariant = $this->inventory->getById($variantId);

            return $res->json(['success' => true, 'message' => 'Variant updated successfully', 'status_code' => 200, 'data' => $updatedVariant]);

        } catch (\Exception $e) {
            return $res->json(['success' => false, 'message' => 'Failed to update variant: ' . $e->getMessage(), 'status_code' => 500], 500);
        }
    }

    /**
     * DELETE /api/v1/inventory/{variant_id} - Xóa variant hoàn toàn
     */
    public function destroy(Request $req, Response $res)
    {
        try {
            $variantId = $req->getAttribute('id');
            
            // Check if variant exists
            $existingVariant = $this->inventory->getById($variantId);
            if (!$existingVariant) {
                return $res->json(['success' => false, 'message' => 'Variant not found', 'status_code' => 404], 404);
            }

            // Delete variant completely
            $this->inventory->delete($variantId);

            // Log activity
            $this->logActivity('product_variant', $variantId, 'delete', ['deleted' => true]);

            return $res->json(['success' => true, 'message' => 'Variant deleted successfully', 'status_code' => 200]);

        } catch (\Exception $e) {
            return $res->json(['success' => false, 'message' => 'Failed to delete variant: ' . $e->getMessage(), 'status_code' => 500], 500);
        }
    }



    /**
     * Helper method to log activity
     */
    private function logActivity($entityType, $entityId, $action, $data = null)
    {
        try {
            $userId = 1; // Default admin user ID, should get from JWT token in real implementation
            
            $sql = "INSERT INTO activity_logs (entity_type, entity_id, action, changed_by, data_after, created_at) 
                    VALUES (?, ?, ?, ?, ?, NOW())";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $entityType,
                $entityId,
                $action,
                $userId,
                $data ? json_encode($data) : null
            ]);
        } catch (\Exception $e) {
            // Log error but don't fail the main operation
            error_log("Failed to log activity: " . $e->getMessage());
        }
    }
}
