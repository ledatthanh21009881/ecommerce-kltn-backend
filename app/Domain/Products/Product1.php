<?php
declare(strict_types=1);

namespace App\Domain\Products;

use App\Core\{Database, Model};
use Exception;
use PDO;

class Product extends Model
{
    protected string $table = 'products';
    protected string $primaryKey = 'product_id';
    
    public function __construct(Database $database)
    {
        parent::__construct($database);
    }
    
    /**
     * Get all products with pagination and filtering
     */
    public function getAll(array $filters = [], int $limit = 20, int $offset = 0): array
    {
        // Simple query first to test
        $sql = "SELECT p.*, c.category_name
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.category_id 
                WHERE p.status = 'active'";
        
        $params = [];
        
        if (!empty($filters['category_id'])) {
            $sql .= " AND p.category_id = ?";
            $params[] = $filters['category_id'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (p.product_name LIKE ? OR p.description LIKE ? OR p.short_description LIKE ?)";
            $searchTerm = "%{$filters['search']}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if (!empty($filters['is_featured'])) {
            $sql .= " AND p.is_featured = 1";
        }
        
        $sql .= " ORDER BY p.created_at DESC 
                  LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute($params);
        $products = $stmt->fetchAll();
        
        // Add basic pricing and image info
        foreach ($products as &$product) {
            // Get price range from variants if they exist
            $priceRange = $this->getPriceRange($product['product_id']);
            $product['min_price'] = $priceRange['min_price'];
            $product['max_price'] = $priceRange['max_price'];
            $product['total_stock'] = $priceRange['total_stock'];
            $product['variant_count'] = $this->getVariantCount($product['product_id']);
            
            // Get main image
            $product['main_image'] = $this->getMainImage($product['product_id']);
            
            // Get all images
            $product['images'] = $this->getProductImages($product['product_id']);
        }
        
        return $products;
    }
    
    /**
     * Get total count of products with filters
     */
    public function getCount(array $filters = []): int
    {
        $sql = "SELECT COUNT(DISTINCT p.product_id) 
                FROM products p 
                LEFT JOIN product_variants pv ON p.product_id = pv.product_id AND pv.is_active = 1
                WHERE p.status = 'active'";
        
        $params = [];
        
        if (!empty($filters['category_id'])) {
            $sql .= " AND p.category_id = ?";
            $params[] = $filters['category_id'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (p.product_name LIKE ? OR p.description LIKE ? OR p.short_description LIKE ?)";
            $searchTerm = "%{$filters['search']}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if (!empty($filters['is_featured'])) {
            $sql .= " AND p.is_featured = 1";
        }
        
        if (!empty($filters['min_price'])) {
            $sql .= " AND p.list_price >= ?";
            $params[] = $filters['min_price'];
        }
        
        if (!empty($filters['max_price'])) {
            $sql .= " AND p.list_price <= ?";
            $params[] = $filters['max_price'];
        }
        
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }
    
    /**
     * Get product by ID with full details
     */
    public function getById(int $id): ?array
    {
        $sql = "SELECT p.*, c.category_name,
                       c.slug as category_slug
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.category_id 
                WHERE p.product_id = ? AND p.status != 'draft'";
        
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute([$id]);
        $product = $stmt->fetch();
        
        if (!$product) {
            return null;
        }
        
        // Get product variants
        $product['variants'] = $this->getProductVariants($id);
        
        // Get product images
        $product['images'] = $this->getProductImages($id);
        $product['main_image'] = $this->getMainImage($id);
        
        // Get price range
        $priceRange = $this->getPriceRange($id);
        $product['min_price'] = $priceRange['min_price'];
        $product['max_price'] = $priceRange['max_price'];
        $product['total_stock'] = $priceRange['total_stock'];
        
        return $product;
    }
    
    /**
     * Get product by slug
     */
    public function getBySlug(string $slug): ?array
    {
        $sql = "SELECT p.*, c.category_name,
                       c.slug as category_slug
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.category_id 
                WHERE p.slug = ? AND p.status != 'draft'";
        
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute([$slug]);
        $product = $stmt->fetch();
        
        if (!$product) {
            return null;
        }
        
        // Get full product details
        return $this->getById($product['product_id']);
    }
    
    /**
     * Create new product
     */
    public function create(array $data): int
    {
        $sql = "INSERT INTO products (
                    product_name, slug, category_id, short_description, 
                    description, material, list_price, compare_at_price, 
                    cost_price, stock, status, is_featured
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute([
            $data['product_name'],
            $data['slug'] ?? $this->generateSlug($data['product_name']),
            $data['category_id'],
            $data['short_description'] ?? null,
            $data['description'] ?? null,
            $data['material'] ?? null,
            $data['list_price'] ?? 0, // Giá bán chính
            $data['compare_at_price'] ?? null, // Giá so sánh (gạch ngang)
            $data['cost_price'] ?? null, // Giá vốn
            $data['stock'] ?? 0, // Tồn kho tổng
            $data['status'] ?? 'active',
            $data['is_featured'] ?? false
        ]);
        
        return (int)$this->getConnection()->lastInsertId();
    }
    
    /**
     * Update product
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = [];
        
        $allowedFields = [
            'product_name', 'slug', 'category_id', 'short_description',
            'description', 'material', 'list_price', 'compare_at_price', 
            'cost_price', 'stock', 'status', 'is_featured'
        ];
        
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $params[] = $id;
        $sql = "UPDATE products SET " . implode(', ', $fields) . " WHERE product_id = ?";
        
        $stmt = $this->getConnection()->prepare($sql);
        return $stmt->execute($params);
    }
    
    /**
     * Delete product (hard delete)
     */
    public function delete(int $id): bool
    {
        $sql = "DELETE FROM products WHERE product_id = ?";
        $stmt = $this->getConnection()->prepare($sql);
        return $stmt->execute([$id]);
    }
    
    /**
     * Delete product and all related data (hard delete with cascade)
     */
    public function deleteWithCascade(int $id): bool
    {
        try {
            $pdo = $this->getConnection();
            
            // Begin transaction
            $pdo->beginTransaction();
            
            // Simple approach: just delete the product
            // Most tables should have ON DELETE CASCADE constraint
            $stmt = $pdo->prepare('DELETE FROM products WHERE product_id = ?');
            $result = $stmt->execute([$id]);
            
            // If that works, try to clean up related data manually
            if ($result) {
                // Try to delete product images (safe to fail)
                try {
                    $stmt = $pdo->prepare('DELETE FROM product_images WHERE product_id = ?');
                    $stmt->execute([$id]);
                } catch (Exception $e) {
                    // Ignore if table doesn't exist
                }
            }
            
            // Commit transaction
            $pdo->commit();
            
            return $result;
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $this->getConnection()->rollBack();
            throw new Exception('Failed to delete product: ' . $e->getMessage());
        }
    }
    
    /**
     * Soft delete product (set status to inactive)
     */
    public function softDelete(int $id): bool
    {
        $sql = "UPDATE products SET status = 'inactive' WHERE product_id = ?";
        $stmt = $this->getConnection()->prepare($sql);
        return $stmt->execute([$id]);
    }
    
    /**
     * Restore soft deleted product
     */
    public function restore(int $id): bool
    {
        $sql = "UPDATE products SET status = 'active' WHERE product_id = ?";
        $stmt = $this->getConnection()->prepare($sql);
        return $stmt->execute([$id]);
    }
    
    /**
     * Get product variants
     */
    public function getProductVariants(int $productId): array
    {
        $sql = "SELECT pv.*, 
                       s.size_name
                FROM product_variants pv
                LEFT JOIN sizes s ON pv.size_id = s.size_id
                WHERE pv.product_id = ? AND pv.is_active = 1
                ORDER BY s.size_name";
        
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute([$productId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get product images
     */
    public function getProductImages(int $productId): array
    {
        $sql = "SELECT * FROM product_images 
                WHERE product_id = ? 
                ORDER BY is_main DESC, position ASC";
        
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute([$productId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get main product image
     */
    public function getMainImage(int $productId): ?string
    {
        $sql = "SELECT url FROM product_images 
                WHERE product_id = ? AND is_main = 1 
                LIMIT 1";
        
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute([$productId]);
        $result = $stmt->fetchColumn();
        
        return $result ?: null;
    }
    
    /**
     * Get variant count for product
     */
    public function getVariantCount(int $productId): int
    {
        $sql = "SELECT COUNT(*) FROM product_variants WHERE product_id = ? AND is_active = 1";
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute([$productId]);
        return (int)$stmt->fetchColumn();
    }
    
    /**
     * Get price range for product (từ bảng products vì variants không có price)
     */
    public function getPriceRange(int $productId): array
    {
        // Get price from products table and total stock from variants
        $sql = "SELECT 
                    p.list_price as min_price,
                    p.list_price as max_price,
                    COALESCE(SUM(pv.stock_quantity), 0) as total_stock
                FROM products p
                LEFT JOIN product_variants pv ON p.product_id = pv.product_id AND pv.is_active = 1
                WHERE p.product_id = ?
                GROUP BY p.product_id";
        
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute([$productId]);
        $result = $stmt->fetch();
        
        return [
            'min_price' => $result['min_price'] ?? 0,
            'max_price' => $result['max_price'] ?? 0,
            'total_stock' => $result['total_stock'] ?? 0
        ];
    }
    
    /**
     * Get featured products
     */
    public function getFeatured(int $limit = 10): array
    {
        return $this->getAll(['is_featured' => true], $limit, 0);
    }
    
    /**
     * Search products
     */
    public function search(string $query, array $filters = [], int $limit = 20, int $offset = 0): array
    {
        $filters['search'] = $query;
        return $this->getAll($filters, $limit, $offset);
    }
    
    /**
     * Generate unique slug
     */
    private function generateSlug(string $name): string
    {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
        $originalSlug = $slug;
        $counter = 1;
        
        while ($this->slugExists($slug)) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }
    
    /**
     * Check if slug exists
     */
    private function slugExists(string $slug): bool
    {
        $sql = "SELECT COUNT(*) FROM products WHERE slug = ?";
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute([$slug]);
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Add product variant
     */
    public function addVariant(int $productId, array $variantData): int
    {
        $sql = "INSERT INTO product_variants (
                    product_id, size_id, sku, stock_quantity, status, is_active
                ) VALUES (?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute([
            $productId,
            $variantData['size_id'],
            $variantData['sku'] ?? null,
            $variantData['stock_quantity'] ?? 0,
            $variantData['status'] ?? 'in_stock',
            $variantData['is_active'] ?? true
        ]);
        
        return (int)$this->getConnection()->lastInsertId();
    }
    
    /**
     * Add product image
     */
    public function addImage(int $productId, array $imageData): int
    {
        $sql = "INSERT INTO product_images (
                    product_id, variant_id, url, position, image_type, is_main
                ) VALUES (?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute([
            $productId,
            $imageData['variant_id'] ?? null,
            $imageData['url'],
            $imageData['position'] ?? 0,
            $imageData['image_type'] ?? 'product',
            $imageData['is_main'] ?? false
        ]);
        
        return (int)$this->getConnection()->lastInsertId();
    }
    
    /**
     * Get product by ID with all details
     */
    public function getByIdWithDetails(int $id): ?array
    {
        // Lấy thông tin sản phẩm cơ bản với category
        $sql = "SELECT p.*, c.category_name, c.slug as category_slug
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.category_id 
                WHERE p.product_id = ?";
        
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute([$id]);
        $product = $stmt->fetch();
        
        if (!$product) {
            return null;
        }
        
        // Lấy tất cả variants với size information
        $product['variants'] = $this->getProductVariantsWithSizes($id);
        
        // Lấy tất cả ảnh
        $product['images'] = $this->getProductImages($id);
        $product['main_image'] = $this->getMainImage($id);
        
        // Lấy thông tin tồn kho từ variants
        $stockInfo = $this->getStockInfo($id);
        $product['total_variant_stock'] = $stockInfo['total_stock'];
        $product['available_sizes'] = $stockInfo['available_sizes'];
        
        // Thêm thông tin giá
        $product['has_compare_price'] = !empty($product['compare_at_price']);
        $product['discount_percent'] = 0;
        if ($product['has_compare_price'] && $product['compare_at_price'] > $product['list_price']) {
            $product['discount_percent'] = round((($product['compare_at_price'] - $product['list_price']) / $product['compare_at_price']) * 100);
        }
        
        return $product;
    }
    
    /**
     * Create product variant
     */
    public function createVariant(array $data): int
    {
        $sql = "INSERT INTO product_variants (product_id, size_id, sku, stock_quantity, status, is_active) 
                VALUES (?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute([
            $data['product_id'],
            $data['size_id'],
            $data['sku'] ?? null,
            $data['stock_quantity'] ?? 0,
            $data['status'] ?? 'in_stock',
            $data['is_active'] ?? true
        ]);
        
        return (int)$this->getConnection()->lastInsertId();
    }
    
    /**
     * Create product image
     */
    public function createImage(array $data): int
    {
        $sql = "INSERT INTO product_images (
                    product_id, variant_id, url, media_public_id, position, 
                    alt_text, image_type, is_main, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
        
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute([
            $data['product_id'],
            $data['variant_id'] ?? null,
            $data['url'],
            $data['media_public_id'] ?? null,
            $data['position'] ?? 0,
            $data['alt_text'] ?? null,
            $data['image_type'] ?? 'gallery',
            $data['is_main'] ?? 0
        ]);
        
        return (int)$this->getConnection()->lastInsertId();
    }
    
    /**
     * Get images by product ID
     */
    public function getImagesByProductId(int $productId): array
    {
        $sql = "SELECT * FROM product_images WHERE product_id = ? ORDER BY position, is_main DESC";
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute([$productId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Delete images by product ID
     */
    public function deleteImagesByProductId(int $productId): bool
    {
        $sql = "DELETE FROM product_images WHERE product_id = ?";
        $stmt = $this->getConnection()->prepare($sql);
        return $stmt->execute([$productId]);
    }
    
    /**
     * Delete variants by product ID
     */
    public function deleteVariantsByProductId(int $productId): bool
    {
        $sql = "DELETE FROM product_variants WHERE product_id = ?";
        $stmt = $this->getConnection()->prepare($sql);
        return $stmt->execute([$productId]);
    }
    
    /**
     * Delete reviews by product ID
     */
    public function deleteReviewsByProductId(int $productId): bool
    {
        $sql = "DELETE FROM product_reviews WHERE product_id = ?";
        $stmt = $this->getConnection()->prepare($sql);
        return $stmt->execute([$productId]);
    }
    
    /**
     * Delete cart items by product ID
     */
    public function deleteCartItemsByProductId(int $productId): bool
    {
        $sql = "DELETE FROM cart_items WHERE product_id = ?";
        $stmt = $this->getConnection()->prepare($sql);
        return $stmt->execute([$productId]);
    }
    
    /**
     * Delete wishlist items by product ID
     */
    public function deleteWishlistItemsByProductId(int $productId): bool
    {
        $sql = "DELETE FROM wishlist_items WHERE product_id = ?";
        $stmt = $this->getConnection()->prepare($sql);
        return $stmt->execute([$productId]);
    }
    
    /**
     * Begin database transaction
     */
    public function beginTransaction(): bool
    {
        return $this->getConnection()->beginTransaction();
    }
    
    /**
     * Commit database transaction
     */
    public function commit(): bool
    {
        return $this->getConnection()->commit();
    }
    
    /**
     * Rollback database transaction
     */
    public function rollback(): bool
    {
        return $this->getConnection()->rollback();
    }
    
    /**
     * Get product variants with size information
     */
    private function getProductVariantsWithSizes(int $productId): array
    {
        $sql = "SELECT pv.*, s.size_name
                FROM product_variants pv
                LEFT JOIN sizes s ON pv.size_id = s.size_id
                WHERE pv.product_id = ? AND pv.is_active = 1
                ORDER BY s.size_id";
        
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute([$productId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get stock info from variants
     */
    private function getStockInfo(int $productId): array
    {
        $sql = "SELECT 
                    SUM(pv.stock_quantity) as total_stock,
                    COUNT(DISTINCT s.size_id) as size_count
                FROM product_variants pv
                LEFT JOIN sizes s ON pv.size_id = s.size_id
                WHERE pv.product_id = ? AND pv.is_active = 1";
        
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute([$productId]);
        $stockData = $stmt->fetch();
        
        // Get available sizes
        $sizeSql = "SELECT s.size_name, pv.stock_quantity
                    FROM product_variants pv
                    LEFT JOIN sizes s ON pv.size_id = s.size_id
                    WHERE pv.product_id = ? AND pv.is_active = 1
                    ORDER BY s.size_id";
        
        $stmt = $this->getConnection()->prepare($sizeSql);
        $stmt->execute([$productId]);
        $availableSizes = $stmt->fetchAll();
        
        return [
            'total_stock' => (int)($stockData['total_stock'] ?? 0),
            'available_sizes' => $availableSizes
        ];
    }
    
    /**
     * Get all products with details for listing
     */
    public function getAllWithDetails(array $filters = [], int $limit = 20, int $offset = 0): array
    {
        $sql = "SELECT p.*, c.category_name, c.slug as category_slug
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.category_id 
                WHERE p.status = 'active'";
        
        $params = [];
        
        if (!empty($filters['category_id'])) {
            $sql .= " AND p.category_id = ?";
            $params[] = $filters['category_id'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (p.product_name LIKE ? OR p.description LIKE ? OR p.short_description LIKE ?)";
            $searchTerm = "%{$filters['search']}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if (!empty($filters['is_featured'])) {
            $sql .= " AND p.is_featured = 1";
        }
        
        if (!empty($filters['status'])) {
            $sql .= " AND p.status = ?";
            $params[] = $filters['status'];
        }
        
        $sql .= " ORDER BY p.created_at DESC 
                  LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute($params);
        $products = $stmt->fetchAll();
        
        // Add additional info for each product
        foreach ($products as &$product) {
            // Get main image
            $product['main_image'] = $this->getMainImage($product['product_id']);
            
            // Get all images
            $product['images'] = $this->getProductImages($product['product_id']);
            
            // Get variants info
            $stockInfo = $this->getStockInfo($product['product_id']);
            $product['total_variant_stock'] = $stockInfo['total_stock'];
            $product['available_sizes'] = $stockInfo['available_sizes'];
            $product['variant_count'] = count($this->getProductVariantsWithSizes($product['product_id']));
            
            // Add pricing info
            $product['has_compare_price'] = !empty($product['compare_at_price']);
            $product['discount_percent'] = 0;
            if ($product['has_compare_price'] && $product['compare_at_price'] > $product['list_price']) {
                $product['discount_percent'] = round((($product['compare_at_price'] - $product['list_price']) / $product['compare_at_price']) * 100);
            }
        }
        
        return $products;
    }
}
