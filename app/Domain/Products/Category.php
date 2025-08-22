<?php
declare(strict_types=1);

namespace App\Domain\Products;

use App\Core\Model;
use PDO;

class Category extends Model
{
    protected string $table = 'categories';
    protected string $primaryKey = 'category_id';

    /**
     * Get all categories with hierarchical structure
     */
    public function getAllHierarchical(): array
    {
        $sql = "
            SELECT 
                c1.category_id,
                c1.category_name,
                c1.slug,
                c1.parent_id,
                c1.position,
                c1.is_active,
                c2.category_name as parent_name
            FROM categories c1
            LEFT JOIN categories c2 ON c1.parent_id = c2.category_id
            WHERE c1.is_active = 1
            ORDER BY c1.parent_id ASC, c1.position ASC
        ";
        
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute();
        
        return $this->buildHierarchy($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Get all categories (flat list)
     */
    public function getAll(): array
    {
        $sql = "
            SELECT 
                c1.category_id,
                c1.category_name,
                c1.slug,
                c1.parent_id,
                c1.position,
                c1.is_active,
                c2.category_name as parent_name,
                COUNT(c3.category_id) as children_count
            FROM categories c1
            LEFT JOIN categories c2 ON c1.parent_id = c2.category_id
            LEFT JOIN categories c3 ON c1.category_id = c3.parent_id AND c3.is_active = 1
            WHERE c1.is_active = 1
            GROUP BY c1.category_id
            ORDER BY c1.parent_id ASC, c1.position ASC
        ";
        
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get category by ID with parent info
     */
    public function find(int $id): ?array
    {
        $sql = "
            SELECT 
                c1.category_id,
                c1.category_name,
                c1.slug,
                c1.parent_id,
                c1.position,
                c1.is_active,
                c2.category_name as parent_name
            FROM categories c1
            LEFT JOIN categories c2 ON c1.parent_id = c2.category_id
            WHERE c1.category_id = ?
        ";
        
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute([$id]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Get category by slug
     */
    public function findBySlug(string $slug): ?array
    {
        $sql = "
            SELECT 
                c1.category_id,
                c1.category_name,
                c1.slug,
                c1.parent_id,
                c1.position,
                c1.is_active,
                c2.category_name as parent_name
            FROM categories c1
            LEFT JOIN categories c2 ON c1.parent_id = c2.category_id
            WHERE c1.slug = ? AND c1.is_active = 1
        ";
        
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute([$slug]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Get main categories (parent_id is NULL)
     */
    public function getMainCategories(): array
    {
        $sql = "
            SELECT 
                category_id,
                category_name,
                slug,
                position,
                is_active
            FROM categories
            WHERE parent_id IS NULL AND is_active = 1
            ORDER BY position ASC
        ";
        
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get children categories by parent ID
     */
    public function getChildren(int $parentId): array
    {
        $sql = "
            SELECT 
                category_id,
                category_name,
                slug,
                parent_id,
                position,
                is_active
            FROM categories
            WHERE parent_id = ? AND is_active = 1
            ORDER BY position ASC
        ";
        
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute([$parentId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Create new category
     */
    public function create(array $data): int
    {
        // Generate slug if not provided
        if (empty($data['slug'])) {
            $data['slug'] = $this->generateSlug($data['category_name']);
        }
        
        // Get next position
        if (empty($data['position'])) {
            $data['position'] = $this->getNextPosition($data['parent_id'] ?? null);
        }
        
        $sql = "
            INSERT INTO categories (category_name, slug, parent_id, position, is_active)
            VALUES (?, ?, ?, ?, ?)
        ";
        
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute([
            $data['category_name'],
            $data['slug'],
            $data['parent_id'] ?? null,
            $data['position'],
            $data['is_active'] ?? 1
        ]);
        
        return (int) $this->getConnection()->lastInsertId();
    }

    /**
     * Update category
     */
    public function update(int $id, array $data): bool
    {
        // Generate slug if category_name changed but slug not provided
        if (!empty($data['category_name']) && empty($data['slug'])) {
            $data['slug'] = $this->generateSlug($data['category_name']);
        }
        
        $fields = [];
        $values = [];
        
        $allowedFields = ['category_name', 'slug', 'parent_id', 'position', 'is_active'];
        
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = ?";
                $values[] = $data[$field];
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $values[] = $id;
        
        $sql = "UPDATE categories SET " . implode(', ', $fields) . " WHERE category_id = ?";
        
        $stmt = $this->getConnection()->prepare($sql);
        return $stmt->execute($values);
    }

    /**
     * Delete category (soft delete by setting is_active = 0)
     */
    public function delete(int $id): bool
    {
        // Check if category has children
        $children = $this->getChildren($id);
        if (!empty($children)) {
            throw new \Exception('Cannot delete category that has sub-categories');
        }
        
        // Check if category has products
        $productCount = $this->getProductCount($id);
        if ($productCount > 0) {
            throw new \Exception('Cannot delete category that has products');
        }
        
        $sql = "UPDATE categories SET is_active = 0 WHERE category_id = ?";
        $stmt = $this->getConnection()->prepare($sql);
        return $stmt->execute([$id]);
    }

    /**
     * Hard delete category
     */
    public function hardDelete(int $id): bool
    {
        $sql = "DELETE FROM categories WHERE category_id = ?";
        $stmt = $this->getConnection()->prepare($sql);
        return $stmt->execute([$id]);
    }

    /**
     * Generate slug from category name
     */
    private function generateSlug(string $name): string
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9\-\s]/', '', $slug);
        $slug = preg_replace('/[\s\-]+/', '-', $slug);
        $slug = trim($slug, '-');
        
        // Check if slug exists and make it unique
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
        $sql = "SELECT COUNT(*) FROM categories WHERE slug = ?";
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute([$slug]);
        
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Get next position for a parent
     */
    private function getNextPosition(?int $parentId): int
    {
        $sql = "SELECT COALESCE(MAX(position), 0) + 1 FROM categories WHERE parent_id " . 
               ($parentId ? "= ?" : "IS NULL");
        
        $stmt = $this->getConnection()->prepare($sql);
        
        if ($parentId) {
            $stmt->execute([$parentId]);
        } else {
            $stmt->execute();
        }
        
        return (int) $stmt->fetchColumn();
    }

    /**
     * Get product count for category
     */
    private function getProductCount(int $categoryId): int
    {
        $sql = "SELECT COUNT(*) FROM products WHERE category_id = ?";
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute([$categoryId]);
        
        return (int) $stmt->fetchColumn();
    }

    /**
     * Build hierarchical structure from flat array
     */
    private function buildHierarchy(array $categories): array
    {
        $tree = [];
        $lookup = [];
        
        // Create lookup array
        foreach ($categories as $category) {
            $lookup[$category['category_id']] = $category;
            $lookup[$category['category_id']]['children'] = [];
        }
        
        // Build tree
        foreach ($categories as $category) {
            if ($category['parent_id'] === null) {
                $tree[] = &$lookup[$category['category_id']];
            } else {
                if (isset($lookup[$category['parent_id']])) {
                    $lookup[$category['parent_id']]['children'][] = &$lookup[$category['category_id']];
                }
            }
        }
        
        return $tree;
    }

    /**
     * Reorder categories
     */
    public function reorder(array $categoryOrders): bool
    {
        $this->getConnection()->beginTransaction();
        
        try {
            foreach ($categoryOrders as $order) {
                $sql = "UPDATE categories SET position = ? WHERE category_id = ?";
                $stmt = $this->getConnection()->prepare($sql);
                $stmt->execute([$order['position'], $order['category_id']]);
            }
            
            $this->getConnection()->commit();
            return true;
        } catch (\Exception $e) {
            $this->getConnection()->rollback();
            throw $e;
        }
    }
}
