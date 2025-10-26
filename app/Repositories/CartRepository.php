<?php

/**
 * CartRepository
 * 
 * Repository pattern để xử lý tất cả database operations liên quan đến Cart.
 * Tách biệt logic database khỏi Controller và Model.
 * 
 * Chức năng:
 * - CRUD operations cho Cart và Cart Items
 * - Tính toán tổng tiền, tổng số lượng
 * - Kiểm tra stock availability
 * - Merge cart items khi thêm duplicate
 * 
 * Tái sử dụng:
 * - CartController sử dụng để xử lý cart operations
 * - Có thể dùng trong checkout process để validate cart
 * - Có thể extend để thêm logic discount, voucher trong tương lai
 * 
 * @package App\Repositories
 * @author ShopSwift Team
 */

namespace App\Repositories;

class CartRepository
{
    /** @var \PDO Database connection */
    private $db;

    /**
     * Constructor - Inject PDO connection
     * 
     * Tái sử dụng: Inject từ Container
     * 
     * @param \PDO $pdo Database connection
     */
    public function __construct($pdo)
    {
        $this->db = $pdo;
    }

    /**
     * Lấy cart của customer theo customer_id
     * 
     * Tái sử dụng: Gọi từ CartController khi cần lấy cart của user
     * 
     * @param int $customerId Customer ID
     * @return array|null Cart data hoặc null nếu không tồn tại
     */
    public function getCartByCustomerId($customerId)
    {
        $sql = "SELECT * FROM carts WHERE customer_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$customerId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Tạo cart mới cho customer
     */
    public function createCart($customerId)
    {
        $sql = "INSERT INTO carts (customer_id, created_at, updated_at) VALUES (?, NOW(), NOW())";
        $stmt = $this->db->prepare($sql);
        $success = $stmt->execute([$customerId]);
        return $success ? $this->db->lastInsertId() : false;
    }

    /**
     * Lấy cart items với thông tin sản phẩm
     */
    public function getCartItems($cartId)
    {
        $sql = "SELECT 
                    ci.item_id,
                    ci.cart_id,
                    ci.variant_id,
                    ci.quantity,
                    ci.unit_price_snapshot,
                    p.product_id,
                    p.product_name,
                    p.slug,
                    p.list_price,
                    p.compare_at_price,
                    s.size_name,
                    pv.sku,
                    pv.stock_quantity,
                    pv.status as variant_status,
                    pi.url as image_url,
                    pi.alt_text as image_alt
                FROM cart_items ci
                JOIN product_variants pv ON ci.variant_id = pv.variant_id
                JOIN products p ON pv.product_id = p.product_id
                JOIN sizes s ON pv.size_id = s.size_id
                LEFT JOIN product_images pi ON p.product_id = pi.product_id AND pi.is_main = 1
                WHERE ci.cart_id = ?
                ORDER BY ci.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$cartId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Thêm item vào cart
     */
    public function addItem($cartId, $variantId, $quantity, $unitPrice)
    {
        // Kiểm tra xem item đã tồn tại chưa
        $existingItem = $this->getCartItemByVariant($cartId, $variantId);
        
        if ($existingItem) {
            // Cập nhật quantity
            return $this->updateItemQuantity($existingItem['item_id'], $existingItem['quantity'] + $quantity);
        } else {
            // Thêm item mới
            $sql = "INSERT INTO cart_items (cart_id, variant_id, quantity, unit_price_snapshot, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, NOW(), NOW())";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([$cartId, $variantId, $quantity, $unitPrice]);
        }
    }

    /**
     * Cập nhật quantity của item
     */
    public function updateItemQuantity($itemId, $quantity)
    {
        if ($quantity <= 0) {
            return $this->removeItem($itemId);
        }

        $sql = "UPDATE cart_items SET quantity = ?, updated_at = NOW() WHERE item_id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$quantity, $itemId]);
    }

    /**
     * Xóa item khỏi cart
     */
    public function removeItem($itemId)
    {
        $sql = "DELETE FROM cart_items WHERE item_id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$itemId]);
    }

    /**
     * Lấy cart item theo variant
     */
    public function getCartItemByVariant($cartId, $variantId)
    {
        $sql = "SELECT * FROM cart_items WHERE cart_id = ? AND variant_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$cartId, $variantId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Lấy cart item theo ID
     */
    public function getCartItemById($itemId)
    {
        $sql = "SELECT * FROM cart_items WHERE item_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$itemId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Kiểm tra stock availability
     */
    public function checkStock($variantId, $quantity)
    {
        $sql = "SELECT stock_quantity, status FROM product_variants WHERE variant_id = ? AND is_active = 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$variantId]);
        $variant = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$variant) {
            return ['available' => false, 'message' => 'Product variant not found'];
        }

        if ($variant['status'] !== 'in_stock') {
            return ['available' => false, 'message' => 'Product is out of stock'];
        }

        if ($variant['stock_quantity'] < $quantity) {
            return [
                'available' => false, 
                'message' => 'Insufficient stock. Available: ' . $variant['stock_quantity']
            ];
        }

        return ['available' => true, 'stock' => $variant['stock_quantity']];
    }

    /**
     * Lấy thông tin variant với product details
     */
    public function getVariantDetails($variantId)
    {
        $sql = "SELECT 
                    pv.variant_id,
                    pv.product_id,
                    pv.size_id,
                    pv.sku,
                    pv.stock_quantity,
                    pv.status,
                    p.product_name,
                    p.slug,
                    p.list_price,
                    p.compare_at_price,
                    s.size_name
                FROM product_variants pv
                JOIN products p ON pv.product_id = p.product_id
                JOIN sizes s ON pv.size_id = s.size_id
                WHERE pv.variant_id = ? AND pv.is_active = 1 AND p.is_active = 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$variantId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Tính tổng tiền cart
     */
    public function calculateCartTotal($cartId)
    {
        $sql = "SELECT 
                    SUM(quantity * unit_price_snapshot) as subtotal,
                    COUNT(*) as item_count
                FROM cart_items 
                WHERE cart_id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$cartId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Xóa tất cả items trong cart
     */
    public function clearCart($cartId)
    {
        $sql = "DELETE FROM cart_items WHERE cart_id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$cartId]);
    }

    /**
     * Merge guest cart vào user cart
     */
    public function mergeGuestCart($userCartId, $guestItems)
    {
        foreach ($guestItems as $item) {
            $this->addItem(
                $userCartId, 
                $item['variant_id'], 
                $item['quantity'], 
                $item['unit_price_snapshot']
            );
        }
    }

    /**
     * Lấy cart summary
     */
    public function getCartSummary($cartId)
    {
        $items = $this->getCartItems($cartId);
        $total = $this->calculateCartTotal($cartId);
        
        return [
            'items' => $items,
            'item_count' => $total['item_count'] ?? 0,
            'subtotal' => $total['subtotal'] ?? 0,
            'total' => $total['subtotal'] ?? 0
        ];
    }
}
