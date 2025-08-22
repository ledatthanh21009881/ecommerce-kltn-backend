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

            // Validate input
            if (!$input['product_id'] || !$input['size_id'] || !$input['sku'] || !isset($input['stock_quantity']) || !$input['status']) {
                return $res->json(['success' => false, 'message' => 'Missing required fields', 'status_code' => 400], 400);
            }
            
            if (!is_numeric($input['product_id']) || !is_numeric($input['size_id']) || !is_numeric($input['stock_quantity'])) {
                return ResponseHelper::error("Invalid numeric fields", 400);
            }
            
            if ($input['stock_quantity'] < 0) {
                return ResponseHelper::error("Stock quantity cannot be negative", 400);
            }
            
            if (!in_array($input['status'], ['in_stock', 'out_of_stock'])) {
                return ResponseHelper::error("Invalid status", 400);
            }

            // Check if product exists
            if (!$this->inventory->productExists($input['product_id'])) {
                return ResponseHelper::error("Product not found", 400);
            }

            // Check if size exists
            if (!$this->inventory->sizeExists($input['size_id'])) {
                return ResponseHelper::error("Size not found", 400);
            }

            // Check if SKU already exists
            if ($this->inventory->skuExists($input['sku'])) {
                return ResponseHelper::error("SKU already exists", 400);
            }

            // Check if variant already exists for this product and size
            if ($this->inventory->variantExists($input['product_id'], $input['size_id'])) {
                return ResponseHelper::error("Variant already exists for this product and size", 400);
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
                return ResponseHelper::error("Variant not found", 404);
            }

            // Validate input
            if (isset($input['stock_quantity'])) {
                if (!is_numeric($input['stock_quantity']) || $input['stock_quantity'] < 0) {
                    return ResponseHelper::error("Invalid stock quantity", 400);
                }
            }
            if (isset($input['status'])) {
                if (!in_array($input['status'], ['in_stock', 'out_of_stock'])) {
                    return ResponseHelper::error("Invalid status", 400);
                }
            }
            if (isset($input['sku'])) {
                // Check if SKU already exists (excluding current variant)
                if ($this->inventory->skuExists($input['sku'], $variantId)) {
                    return ResponseHelper::error("SKU already exists", 400);
                }
            }

            // Update variant
            $success = $this->inventory->update($variantId, $input);
            
            if (!$success) {
                return ResponseHelper::error("No fields to update", 400);
            }

            // Log activity
            $this->logActivity('product_variant', $variantId, 'update', $input);

            // Get updated variant
            $updatedVariant = $this->inventory->getById($variantId);

            return ResponseHelper::success($updatedVariant, "Variant updated successfully");

        } catch (\Exception $e) {
            return ResponseHelper::error("Failed to update variant: " . $e->getMessage(), 500);
        }
    }

    /**
     * DELETE /api/v1/inventory/{variant_id} - Vô hiệu hóa variant
     */
    public function destroy(Request $req, Response $res)
    {
        try {
            $variantId = $req->getAttribute('id');
            
            // Check if variant exists
            $existingVariant = $this->inventory->getById($variantId);
            if (!$existingVariant) {
                return ResponseHelper::error("Variant not found", 404);
            }

            // Deactivate variant
            $this->inventory->deactivate($variantId);

            // Log activity
            $this->logActivity('product_variant', $variantId, 'delete', ['deactivated' => true]);

            return ResponseHelper::success(null, "Variant deactivated successfully");

        } catch (\Exception $e) {
            return ResponseHelper::error("Failed to deactivate variant: " . $e->getMessage(), 500);
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
