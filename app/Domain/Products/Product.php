<?php
declare(strict_types=1);

namespace App\Domain\Products;

use App\Core\Model;
use PDO;

class Product extends Model
{
    protected string $table = 'products';
    protected string $primaryKey = 'product_id';
    
    protected array $fillable = [
        'product_name',
        'product_description', 
        'category_id',
        'product_status',
        'product_slug',
        'created_at',
        'updated_at'
    ];

    public function getAll(): array
    {
        $stmt = $this->getConnection()->query("SELECT * FROM {$this->table} ORDER BY created_at DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllWithDetails(array $filters = [], int $limit = 20, int $offset = 0): array
    {
        $sql = "
            SELECT 
                p.*,
                c.category_name,
                p.list_price as min_price,
                p.list_price as max_price,
                (SELECT COUNT(*) FROM product_variants pv WHERE pv.product_id = p.product_id) as variant_count,
                (SELECT pi.url FROM product_images pi WHERE pi.product_id = p.product_id AND pi.is_main = 1 LIMIT 1) as main_image
            FROM {$this->table} p
            LEFT JOIN categories c ON p.category_id = c.category_id
        ";
        
        $whereConditions = [];
        $params = [];
        
        if (!empty($filters['category_id'])) {
            $whereConditions[] = "p.category_id = ?";
            $params[] = $filters['category_id'];
        }
        
        if (!empty($filters['search'])) {
            $whereConditions[] = "(p.product_name LIKE ? OR p.description LIKE ?)";
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
        }
        
        if (!empty($filters['status'])) {
            $whereConditions[] = "p.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['is_featured'])) {
            $whereConditions[] = "p.is_featured = ?";
            $params[] = $filters['is_featured'];
        }
        
        if (!empty($whereConditions)) {
            $sql .= " WHERE " . implode(" AND ", $whereConditions);
        }
        
        $sql .= " ORDER BY p.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute($params);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Thêm variants và images cho mỗi sản phẩm
        foreach ($products as &$product) {
            $product['variants'] = $this->getProductVariants($product['product_id']);
            $product['images'] = $this->getProductImages($product['product_id']);
        }
        
        return $products;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->getConnection()->prepare("SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->getConnection()->prepare("SELECT * FROM {$this->table} WHERE product_slug = ?");
        $stmt->execute([$slug]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function getProductVariants(int $productId): array
    {
        $sql = "
            SELECT 
                pv.*,
                s.size_name,
                s.size_id
            FROM product_variants pv
            LEFT JOIN sizes s ON pv.size_id = s.size_id
            WHERE pv.product_id = ? AND pv.is_active = 1
            ORDER BY s.size_id ASC
        ";
        
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute([$productId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getProductImages(int $productId): array
    {
        $sql = "
            SELECT * FROM product_images 
            WHERE product_id = ? 
            ORDER BY position ASC
        ";
        
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute([$productId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCount(array $filters = []): int
    {
        $sql = "SELECT COUNT(*) FROM {$this->table} p";
        
        $whereConditions = [];
        $params = [];
        
        if (!empty($filters['category_id'])) {
            $whereConditions[] = "p.category_id = ?";
            $params[] = $filters['category_id'];
        }
        
        if (!empty($filters['search'])) {
            $whereConditions[] = "(p.product_name LIKE ? OR p.description LIKE ?)";
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
        }
        
        if (!empty($filters['status'])) {
            $whereConditions[] = "p.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['is_featured'])) {
            $whereConditions[] = "p.is_featured = ?";
            $params[] = $filters['is_featured'];
        }
        
        if (!empty($whereConditions)) {
            $sql .= " WHERE " . implode(" AND ", $whereConditions);
        }
        
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function getByIdWithDetails(int $id): ?array
    {
        $sql = "
            SELECT 
                p.*,
                c.category_name,
                p.list_price as min_price,
                p.list_price as max_price
            FROM {$this->table} p
            LEFT JOIN categories c ON p.category_id = c.category_id
            WHERE p.product_id = ?
        ";
        
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute([$id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$product) {
            return null;
        }
        
        // Get variants
        $product['variants'] = $this->getProductVariants($id);
        
        // Get images
        $product['images'] = $this->getProductImages($id);
        
        return $product;
    }

    public function getPriceRange(int $productId): array
    {
        $sql = "SELECT list_price FROM products WHERE product_id = ?";
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute([$productId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $price = $result['list_price'] ?? 0;
        
        return [
            'min_price' => $price,
            'max_price' => $price
        ];
    }

    public function create(array $data): int
    {
        $fields = array_keys($data);
        $placeholders = str_repeat('?,', count($fields) - 1) . '?';
        
        $sql = "INSERT INTO {$this->table} (" . implode(',', $fields) . ") VALUES ($placeholders)";
        
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute(array_values($data));
        
        return (int) $this->getConnection()->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        foreach (array_keys($data) as $field) {
            $fields[] = "$field = ?";
        }
        
        $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE {$this->primaryKey} = ?";
        
        $values = array_values($data);
        $values[] = $id;
        
        $stmt = $this->getConnection()->prepare($sql);
        return $stmt->execute($values);
    }

    public function delete(int $id): bool
    {
        $pdo = $this->getConnection();
        
        // Delete product images
        $stmt = $pdo->prepare("DELETE FROM product_images WHERE product_id = ?");
        $stmt->execute([$id]);
        
        // Delete product variants
        $stmt = $pdo->prepare("DELETE FROM product_variants WHERE product_id = ?");
        $stmt->execute([$id]);
        
        // Delete product
        $stmt = $pdo->prepare("DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?");
        $stmt->execute([$id]);
        
        return true;
    }

    public function createProductWithDetails(array $productData, array $variants = [], array $images = []): int
    {
        $this->getConnection()->beginTransaction();
        
        try {
            // Create product
            $productId = $this->create($productData);
            
            // Create variants
            if (!empty($variants)) {
                foreach ($variants as $variant) {
                    $variant['product_id'] = $productId;
                    $this->createVariant($variant);
                }
            }
            
            // Create images
            if (!empty($images)) {
                foreach ($images as $index => $image) {
                    $image['product_id'] = $productId;
                    $image['position'] = $index;
                    $image['is_main'] = $index === 0 ? 1 : 0;
                    $this->createImage($image);
                }
            }
            
            $this->getConnection()->commit();
            return $productId;
        } catch (Exception $e) {
            $this->getConnection()->rollBack();
            throw $e;
        }
    }

    public function updateProductWithDetails(int $id, array $productData, array $variants = [], array $images = []): bool
    {
        $this->getConnection()->beginTransaction();
        
        try {
            // Update product
            $this->update($id, $productData);
            
            // Update variants (delete old, create new)
            $stmt = $this->getConnection()->prepare("DELETE FROM product_variants WHERE product_id = ?");
            $stmt->execute([$id]);
            
            if (!empty($variants)) {
                foreach ($variants as $variant) {
                    $variant['product_id'] = $id;
                    $this->createVariant($variant);
                }
            }
            
            // Update images (delete old, create new)
            $stmt = $this->getConnection()->prepare("DELETE FROM product_images WHERE product_id = ?");
            $stmt->execute([$id]);
            
            if (!empty($images)) {
                foreach ($images as $index => $image) {
                    $image['product_id'] = $id;
                    $image['position'] = $index;
                    $image['is_main'] = $index === 0 ? 1 : 0;
                    $this->createImage($image);
                }
            }
            
            $this->getConnection()->commit();
            return true;
        } catch (Exception $e) {
            $this->getConnection()->rollBack();
            throw $e;
        }
    }

    private function createVariant(array $data): int
    {
        $fields = array_keys($data);
        $placeholders = str_repeat('?,', count($fields) - 1) . '?';
        
        $sql = "INSERT INTO product_variants (" . implode(',', $fields) . ") VALUES ($placeholders)";
        
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute(array_values($data));
        
        return (int) $this->getConnection()->lastInsertId();
    }

    private function createImage(array $data): int
    {
        $fields = array_keys($data);
        $placeholders = str_repeat('?,', count($fields) - 1) . '?';
        
        $sql = "INSERT INTO product_images (" . implode(',', $fields) . ") VALUES ($placeholders)";
        
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute(array_values($data));
        
        return (int) $this->getConnection()->lastInsertId();
    }

    public function generateSlug(string $name, ?int $excludeId = null): string
    {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
        $originalSlug = $slug;
        $counter = 1;
        
        while ($this->slugExists($slug, $excludeId)) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }

    private function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE product_slug = ?";
        $params = [$slug];
        
        if ($excludeId !== null) {
            $sql .= " AND {$this->primaryKey} != ?";
            $params[] = $excludeId;
        }
        
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchColumn() > 0;
    }

    public function getByCategory(int $categoryId): array
    {
        $sql = "
            SELECT 
                p.*,
                c.category_name,
                (SELECT MIN(pv.variant_price) FROM product_variants pv WHERE pv.product_id = p.product_id) as min_price,
                (SELECT MAX(pv.variant_price) FROM product_variants pv WHERE pv.product_id = p.product_id) as max_price,
                (SELECT pi.image_url FROM product_images pi WHERE pi.product_id = p.product_id ORDER BY pi.image_order ASC LIMIT 1) as main_image
            FROM {$this->table} p
            LEFT JOIN categories c ON p.category_id = c.category_id
            WHERE p.category_id = ? AND p.product_status = 'active'
            ORDER BY p.created_at DESC
        ";
        
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute([$categoryId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function search(string $query): array
    {
        $sql = "
            SELECT 
                p.*,
                c.category_name,
                p.list_price as min_price,
                p.list_price as max_price,
                (SELECT pi.url FROM product_images pi WHERE pi.product_id = p.product_id AND pi.is_main = 1 LIMIT 1) as main_image
            FROM {$this->table} p
            LEFT JOIN categories c ON p.category_id = c.category_id
            WHERE (p.product_name LIKE ? OR p.description LIKE ?) 
                AND p.status = 'active'
            ORDER BY p.created_at DESC
        ";
        
        $searchTerm = "%$query%";
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute([$searchTerm, $searchTerm]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Additional methods for ProductController
    public function getImagesByProductId(int $productId): array
    {
        return $this->getProductImages($productId);
    }

    public function deleteVariantsByProductId(int $productId): bool
    {
        $stmt = $this->getConnection()->prepare("DELETE FROM product_variants WHERE product_id = ?");
        return $stmt->execute([$productId]);
    }

    public function deleteImagesByProductId(int $productId): bool
    {
        $stmt = $this->getConnection()->prepare("DELETE FROM product_images WHERE product_id = ?");
        return $stmt->execute([$productId]);
    }

    public function deleteReviewsByProductId(int $productId): bool
    {
        // Check if reviews table exists
        try {
            $stmt = $this->getConnection()->prepare("DELETE FROM product_reviews WHERE product_id = ?");
            return $stmt->execute([$productId]);
        } catch (Exception $e) {
            // Reviews table might not exist, return true
            return true;
        }
    }

    public function deleteCartItemsByProductId(int $productId): bool
    {
        // Check if cart_items table exists
        try {
            $stmt = $this->getConnection()->prepare("DELETE FROM cart_items WHERE product_id = ?");
            return $stmt->execute([$productId]);
        } catch (Exception $e) {
            // Cart_items table might not exist, return true
            return true;
        }
    }

    public function deleteWishlistItemsByProductId(int $productId): bool
    {
        // Check if wishlist_items table exists
        try {
            $stmt = $this->getConnection()->prepare("DELETE FROM wishlist_items WHERE product_id = ?");
            return $stmt->execute([$productId]);
        } catch (Exception $e) {
            // Wishlist_items table might not exist, return true
            return true;
        }
    }
}
