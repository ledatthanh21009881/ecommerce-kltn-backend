<?php

namespace App\Repositories;

class ShippingRepository
{
    private $db;

    public function __construct($pdo)
    {
        $this->db = $pdo;
    }

    /**
     * Lấy tất cả shipping methods với filter và sort
     */
    public function getAll($active = null, $sortBy = 'fee', $sortOrder = 'asc')
    {
        $sql = "SELECT * FROM shipping_methods WHERE 1=1";
        $params = [];

        // Filter by active status
        if ($active !== null) {
            $sql .= " AND is_active = ?";
            $params[] = $active ? 1 : 0;
        }

        // Sort
        $allowedSortFields = ['shipping_method_id', 'name', 'fee', 'estimated_days', 'is_active', 'created_at'];
        $sortBy = in_array($sortBy, $allowedSortFields) ? $sortBy : 'fee';
        $sortOrder = strtolower($sortOrder) === 'desc' ? 'DESC' : 'ASC';
        
        $sql .= " ORDER BY {$sortBy} {$sortOrder}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Lấy shipping method theo ID
     */
    public function getById($id)
    {
        $sql = "SELECT * FROM shipping_methods WHERE shipping_method_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);

        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Tạo shipping method mới
     */
    public function create($data)
    {
        $sql = "INSERT INTO shipping_methods (name, fee, estimated_days, is_active, created_at, updated_at) 
                VALUES (?, ?, ?, ?, NOW(), NOW())";
        
        $stmt = $this->db->prepare($sql);
        $success = $stmt->execute([
            $data['name'],
            $data['fee'],
            $data['estimated_days'],
            isset($data['is_active']) ? ($data['is_active'] ? 1 : 0) : 1
        ]);

        return $success ? $this->db->lastInsertId() : false;
    }

    /**
     * Cập nhật shipping method
     */
    public function update($id, $data)
    {
        $sql = "UPDATE shipping_methods SET 
                name = ?, 
                fee = ?, 
                estimated_days = ?, 
                is_active = ?,
                updated_at = NOW()
                WHERE shipping_method_id = ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $data['name'],
            $data['fee'],
            $data['estimated_days'],
            isset($data['is_active']) ? ($data['is_active'] ? 1 : 0) : 1,
            $id
        ]);
    }

    /**
     * Xóa shipping method
     */
    public function delete($id)
    {
        $sql = "DELETE FROM shipping_methods WHERE shipping_method_id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$id]);
    }

    /**
     * Cập nhật trạng thái active/inactive
     */
    public function updateStatus($id, $isActive)
    {
        $sql = "UPDATE shipping_methods SET is_active = ?, updated_at = NOW() WHERE shipping_method_id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$isActive, $id]);
    }

    /**
     * Kiểm tra tên shipping method đã tồn tại chưa
     */
    public function nameExists($name, $excludeId = null)
    {
        $sql = "SELECT COUNT(*) FROM shipping_methods WHERE name = ?";
        $params = [$name];

        if ($excludeId) {
            $sql .= " AND shipping_method_id != ?";
            $params[] = $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchColumn() > 0;
    }

    /**
     * Kiểm tra shipping method có đang được sử dụng trong orders không
     */
    public function isUsedInOrders($id)
    {
        $sql = "SELECT COUNT(*) FROM orders WHERE shipping_method_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);

        return $stmt->fetchColumn() > 0;
    }

    /**
     * Lấy thống kê shipping methods
     */
    public function getStats()
    {
        $sql = "SELECT 
                    COUNT(*) as total_methods,
                    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_methods,
                    SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive_methods,
                    AVG(fee) as average_fee,
                    MIN(fee) as min_fee,
                    MAX(fee) as max_fee
                FROM shipping_methods";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Lấy shipping methods đang active
     */
    public function getActiveMethods()
    {
        $sql = "SELECT * FROM shipping_methods WHERE is_active = 1 ORDER BY fee ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Tìm kiếm shipping methods theo tên
     */
    public function searchByName($searchTerm)
    {
        $sql = "SELECT * FROM shipping_methods WHERE name LIKE ? ORDER BY fee ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(["%{$searchTerm}%"]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
