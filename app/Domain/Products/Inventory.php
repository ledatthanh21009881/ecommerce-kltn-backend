<?php

namespace App\Domain\Products;

use App\Core\Database;
use PDO;

class Inventory
{
    private Database $database;

    public function __construct(Database $database)
    {
        $this->database = $database;
    }

    /**
     * Lấy danh sách tất cả variants với thông tin product và size
     */
    public function getAllWithDetails($filters = [])
    {
        $pdo = $this->database->getConnection();
        
        $sql = "SELECT 
                    pv.variant_id,
                    pv.product_id,
                    pv.size_id,
                    pv.sku,
                    pv.stock_quantity,
                    pv.status,
                    pv.is_active,
                    p.product_name,
                    s.size_name
                FROM product_variants pv
                INNER JOIN products p ON pv.product_id = p.product_id
                INNER JOIN sizes s ON pv.size_id = s.size_id
                WHERE pv.is_active = 1";

        $params = [];

        // Add filters
        if (!empty($filters['product_id'])) {
            $sql .= " AND pv.product_id = ?";
            $params[] = $filters['product_id'];
        }

        if (!empty($filters['size_id'])) {
            $sql .= " AND pv.size_id = ?";
            $params[] = $filters['size_id'];
        }

        if (!empty($filters['status'])) {
            $sql .= " AND pv.status = ?";
            $params[] = $filters['status'];
        }

        $sql .= " ORDER BY p.product_name, s.size_name";

        // Add pagination
        if (isset($filters['limit'])) {
            $sql .= " LIMIT ?";
            $params[] = (int)$filters['limit'];
        }

        if (isset($filters['offset'])) {
            $sql .= " OFFSET ?";
            $params[] = (int)$filters['offset'];
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lấy tổng số variants
     */
    public function getCount($filters = [])
    {
        $pdo = $this->database->getConnection();
        
        $sql = "SELECT COUNT(*) FROM product_variants pv 
                INNER JOIN products p ON pv.product_id = p.product_id 
                INNER JOIN sizes s ON pv.size_id = s.size_id 
                WHERE pv.is_active = 1";

        $params = [];

        if (!empty($filters['product_id'])) {
            $sql .= " AND pv.product_id = ?";
            $params[] = $filters['product_id'];
        }

        if (!empty($filters['size_id'])) {
            $sql .= " AND pv.size_id = ?";
            $params[] = $filters['size_id'];
        }

        if (!empty($filters['status'])) {
            $sql .= " AND pv.status = ?";
            $params[] = $filters['status'];
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    /**
     * Lấy chi tiết variant theo ID
     */
    public function getById($variantId)
    {
        $pdo = $this->database->getConnection();
        
        $sql = "SELECT 
                    pv.variant_id,
                    pv.product_id,
                    pv.size_id,
                    pv.sku,
                    pv.stock_quantity,
                    pv.status,
                    pv.is_active,
                    p.product_name,
                    p.short_description,
                    s.size_name
                FROM product_variants pv
                INNER JOIN products p ON pv.product_id = p.product_id
                INNER JOIN sizes s ON pv.size_id = s.size_id
                WHERE pv.variant_id = ? AND pv.is_active = 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$variantId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Tạo variant mới
     */
    public function create($data)
    {
        $pdo = $this->database->getConnection();
        
        $sql = "INSERT INTO product_variants (product_id, size_id, sku, stock_quantity, status, is_active) 
                VALUES (?, ?, ?, ?, ?, 1)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['product_id'],
            $data['size_id'],
            $data['sku'],
            $data['stock_quantity'],
            $data['status']
        ]);

        return $pdo->lastInsertId();
    }

    /**
     * Cập nhật variant
     */
    public function update($variantId, $data)
    {
        $pdo = $this->database->getConnection();
        
        $updateFields = [];
        $params = [];

        if (isset($data['sku'])) {
            $updateFields[] = "sku = ?";
            $params[] = $data['sku'];
        }

        if (isset($data['stock_quantity'])) {
            $updateFields[] = "stock_quantity = ?";
            $params[] = $data['stock_quantity'];
        }

        if (isset($data['status'])) {
            $updateFields[] = "status = ?";
            $params[] = $data['status'];
        }

        if (empty($updateFields)) {
            return false;
        }

        $params[] = $variantId;
        $sql = "UPDATE product_variants SET " . implode(', ', $updateFields) . " WHERE variant_id = ?";
        
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Vô hiệu hóa variant (soft delete)
     */
    public function deactivate($variantId)
    {
        $pdo = $this->database->getConnection();
        
        $sql = "UPDATE product_variants SET is_active = 0 WHERE variant_id = ?";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$variantId]);
    }

    /**
     * Kiểm tra product có tồn tại không
     */
    public function productExists($productId)
    {
        $pdo = $this->database->getConnection();
        
        $sql = "SELECT product_id FROM products WHERE product_id = ? AND is_active = 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$productId]);
        return $stmt->fetch() !== false;
    }

    /**
     * Kiểm tra size có tồn tại không
     */
    public function sizeExists($sizeId)
    {
        $pdo = $this->database->getConnection();
        
        $sql = "SELECT size_id FROM sizes WHERE size_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$sizeId]);
        return $stmt->fetch() !== false;
    }

    /**
     * Kiểm tra SKU đã tồn tại chưa
     */
    public function skuExists($sku, $excludeVariantId = null)
    {
        $pdo = $this->database->getConnection();
        
        $sql = "SELECT variant_id FROM product_variants WHERE sku = ? AND is_active = 1";
        $params = [$sku];
        
        if ($excludeVariantId) {
            $sql .= " AND variant_id != ?";
            $params[] = $excludeVariantId;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch() !== false;
    }

    /**
     * Kiểm tra variant đã tồn tại cho product và size này chưa
     */
    public function variantExists($productId, $sizeId, $excludeVariantId = null)
    {
        $pdo = $this->database->getConnection();
        
        $sql = "SELECT variant_id FROM product_variants WHERE product_id = ? AND size_id = ? AND is_active = 1";
        $params = [$productId, $sizeId];
        
        if ($excludeVariantId) {
            $sql .= " AND variant_id != ?";
            $params[] = $excludeVariantId;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch() !== false;
    }
}
