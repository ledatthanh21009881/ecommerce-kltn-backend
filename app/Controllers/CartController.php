<?php

namespace App\Controllers;

use App\Core\{Controller, Request, Response, Container};
use App\Repositories\CartRepository;
use App\Support\JWT;
// use App\Models\ActivityLog; // Removed - class not found

class CartController extends Controller
{
    private $cartRepository;
    private $jwt;

    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->cartRepository = new CartRepository($container->database()->getConnection());
        // Load JWT secret from config
        $config = require __DIR__ . '/../config/app.php';
        $this->jwt = new JWT($config['jwt']['secret']);
    }

    /**
     * Lấy giỏ hàng
     * GET /api/backend/v1/cart
     */
    public function index(Request $req, Response $res)
    {
        try {
            $token = $this->getBearerToken();
            
            if ($token) {
                // User đã login
                $payload = $this->jwt->decode($token);
                if (!$payload) {
                    return $res->json([
                        'success' => false,
                        'message' => 'Invalid or expired token'
                    ], 401);
                }

                $customerId = $payload['user_id'];
                $cart = $this->cartRepository->getCartByCustomerId($customerId);
                
                if (!$cart) {
                    // Tạo cart mới
                    $cartId = $this->cartRepository->createCart($customerId);
                    $cart = ['cart_id' => $cartId, 'customer_id' => $customerId];
                }

                $summary = $this->cartRepository->getCartSummary($cart['cart_id']);
                
                return $res->json([
                    'success' => true,
                    'message' => 'Cart retrieved successfully',
                    'data' => $summary
                ], 200);
            } else {
                // Guest user - trả về empty cart
                return $res->json([
                    'success' => true,
                    'message' => 'Guest cart retrieved successfully',
                    'data' => [
                        'items' => [],
                        'item_count' => 0,
                        'subtotal' => 0,
                        'total' => 0
                    ]
                ], 200);
            }

        } catch (\Exception $e) {
            return $res->json([
                'success' => false,
                'message' => 'Failed to retrieve cart: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Thêm item vào giỏ hàng
     * POST /api/backend/v1/cart/add
     */
    public function addItem(Request $req, Response $res)
    {
        try {
            $input = $req->json();
            $variantId = $input['variant_id'] ?? null;
            $quantity = $input['quantity'] ?? 1;

            // Validate input
            if (!$variantId || !is_numeric($variantId)) {
                return $res->json([
                    'success' => false,
                    'message' => 'Variant ID is required'
                ], 400);
            }

            if ($quantity <= 0) {
                return $res->json([
                    'success' => false,
                    'message' => 'Quantity must be greater than 0'
                ], 400);
            }

            // Lấy thông tin variant
            $variant = $this->cartRepository->getVariantDetails($variantId);
            if (!$variant) {
                return $res->json([
                    'success' => false,
                    'message' => 'Product variant not found'
                ], 404);
            }

            // Kiểm tra stock
            $stockCheck = $this->cartRepository->checkStock($variantId, $quantity);
            if (!$stockCheck['available']) {
                return $res->json([
                    'success' => false,
                    'message' => $stockCheck['message']
                ], 400);
            }

            $token = $this->getBearerToken();
            
            if ($token) {
                // User đã login
                $payload = $this->jwt->decode($token);
                if (!$payload) {
                    return $res->json([
                        'success' => false,
                        'message' => 'Invalid or expired token'
                    ], 401);
                }

                $customerId = $payload['user_id'];
                $cart = $this->cartRepository->getCartByCustomerId($customerId);
                
                if (!$cart) {
                    $cartId = $this->cartRepository->createCart($customerId);
                } else {
                    $cartId = $cart['cart_id'];
                }

                // Thêm item vào cart
                $success = $this->cartRepository->addItem($cartId, $variantId, $quantity, $variant['list_price']);
                
                if ($success) {
                    // Log activity
                    $this->logActivity($payload['user_id'], 'add_to_cart', 'cart_item', $variantId, [
                        'product_name' => $variant['product_name'],
                        'size' => $variant['size_name'],
                        'quantity' => $quantity
                    ]);

                    $summary = $this->cartRepository->getCartSummary($cartId);
                    
                    return $res->json([
                        'success' => true,
                        'message' => 'Item added to cart successfully',
                        'data' => $summary
                    ], 200);
                } else {
                    return $res->json([
                        'success' => false,
                        'message' => 'Failed to add item to cart'
                    ], 500);
                }
            } else {
                // Guest user - trả về thông tin để frontend lưu localStorage
                return $res->json([
                    'success' => true,
                    'message' => 'Item ready for guest cart',
                    'data' => [
                        'variant_id' => $variantId,
                        'quantity' => $quantity,
                        'unit_price' => $variant['list_price'],
                        'product_name' => $variant['product_name'],
                        'size_name' => $variant['size_name'],
                        'sku' => $variant['sku']
                    ]
                ], 200);
            }

        } catch (\Exception $e) {
            return $res->json([
                'success' => false,
                'message' => 'Failed to add item to cart: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cập nhật quantity của item
     * PUT /api/backend/v1/cart/update/{item_id}
     */
    public function updateItem(Request $req, Response $res)
    {
        try {
            $itemId = $req->param('item_id');
            $input = $req->json();
            $quantity = $input['quantity'] ?? null;

            if (!is_numeric($quantity) || $quantity < 0) {
                return $res->json([
                    'success' => false,
                    'message' => 'Invalid quantity'
                ], 400);
            }

            $token = $this->getBearerToken();
            if (!$token) {
                return $res->json([
                    'success' => false,
                    'message' => 'Authentication required'
                ], 401);
            }

            $payload = $this->jwt->decode($token);
            if (!$payload) {
                return $res->json([
                    'success' => false,
                    'message' => 'Invalid or expired token'
                ], 401);
            }

            // Kiểm tra item thuộc về user
            $item = $this->cartRepository->getCartItemById($itemId);
            if (!$item) {
                return $res->json([
                    'success' => false,
                    'message' => 'Cart item not found'
                ], 404);
            }

            $cart = $this->cartRepository->getCartByCustomerId($payload['user_id']);
            if (!$cart || $cart['cart_id'] != $item['cart_id']) {
                return $res->json([
                    'success' => false,
                    'message' => 'Unauthorized access to cart item'
                ], 403);
            }

            // Kiểm tra stock nếu quantity > 0
            if ($quantity > 0) {
                $stockCheck = $this->cartRepository->checkStock($item['variant_id'], $quantity);
                if (!$stockCheck['available']) {
                    return $res->json([
                        'success' => false,
                        'message' => $stockCheck['message']
                    ], 400);
                }
            }

            // Cập nhật quantity
            $success = $this->cartRepository->updateItemQuantity($itemId, $quantity);
            
            if ($success) {
                $summary = $this->cartRepository->getCartSummary($cart['cart_id']);
                
                return $res->json([
                    'success' => true,
                    'message' => 'Cart item updated successfully',
                    'data' => $summary
                ], 200);
            } else {
                return $res->json([
                    'success' => false,
                    'message' => 'Failed to update cart item'
                ], 500);
            }

        } catch (\Exception $e) {
            return $res->json([
                'success' => false,
                'message' => 'Failed to update cart item: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Xóa item khỏi giỏ hàng
     * DELETE /api/backend/v1/cart/remove/{item_id}
     */
    public function removeItem(Request $req, Response $res)
    {
        try {
            $itemId = $req->param('item_id');

            $token = $this->getBearerToken();
            if (!$token) {
                return $res->json([
                    'success' => false,
                    'message' => 'Authentication required'
                ], 401);
            }

            $payload = $this->jwt->decode($token);
            if (!$payload) {
                return $res->json([
                    'success' => false,
                    'message' => 'Invalid or expired token'
                ], 401);
            }

            // Kiểm tra item thuộc về user
            $item = $this->cartRepository->getCartItemById($itemId);
            if (!$item) {
                return $res->json([
                    'success' => false,
                    'message' => 'Cart item not found'
                ], 404);
            }

            $cart = $this->cartRepository->getCartByCustomerId($payload['user_id']);
            if (!$cart || $cart['cart_id'] != $item['cart_id']) {
                return $res->json([
                    'success' => false,
                    'message' => 'Unauthorized access to cart item'
                ], 403);
            }

            // Xóa item
            $success = $this->cartRepository->removeItem($itemId);
            
            if ($success) {
                $summary = $this->cartRepository->getCartSummary($cart['cart_id']);
                
                return $res->json([
                    'success' => true,
                    'message' => 'Item removed from cart successfully',
                    'data' => $summary
                ], 200);
            } else {
                return $res->json([
                    'success' => false,
                    'message' => 'Failed to remove item from cart'
                ], 500);
            }

        } catch (\Exception $e) {
            return $res->json([
                'success' => false,
                'message' => 'Failed to remove item from cart: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Xóa toàn bộ giỏ hàng
     * DELETE /api/backend/v1/cart/clear
     */
    public function clearCart(Request $req, Response $res)
    {
        try {
            $token = $this->getBearerToken();
            if (!$token) {
                return $res->json([
                    'success' => false,
                    'message' => 'Authentication required'
                ], 401);
            }

            $payload = $this->jwt->decode($token);
            if (!$payload) {
                return $res->json([
                    'success' => false,
                    'message' => 'Invalid or expired token'
                ], 401);
            }

            $customerId = $payload['user_id'];
            $cart = $this->cartRepository->getCartByCustomerId($customerId);
            
            if (!$cart) {
                return $res->json([
                    'success' => true,
                    'message' => 'Cart is already empty',
                    'data' => ['items' => [], 'item_count' => 0, 'subtotal' => 0, 'total' => 0]
                ], 200);
            }

            // Xóa tất cả items trong cart
            $success = $this->cartRepository->clearCart($cart['cart_id']);
            
            if ($success) {
                return $res->json([
                    'success' => true,
                    'message' => 'Cart cleared successfully',
                    'data' => ['items' => [], 'item_count' => 0, 'subtotal' => 0, 'total' => 0]
                ], 200);
            } else {
                return $res->json([
                    'success' => false,
                    'message' => 'Failed to clear cart'
                ], 500);
            }

        } catch (\Exception $e) {
            return $res->json([
                'success' => false,
                'message' => 'Failed to clear cart: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync guest cart với user cart khi login
     * POST /api/backend/v1/cart/sync
     */
    public function syncCart(Request $req, Response $res)
    {
        try {
            $token = $this->getBearerToken();
            if (!$token) {
                return $res->json([
                    'success' => false,
                    'message' => 'Authentication required'
                ], 401);
            }

            $payload = $this->jwt->decode($token);
            if (!$payload) {
                return $res->json([
                    'success' => false,
                    'message' => 'Invalid or expired token'
                ], 401);
            }

            $input = $req->json();
            $guestItems = $input['items'] ?? [];

            $customerId = $payload['user_id'];
            $cart = $this->cartRepository->getCartByCustomerId($customerId);
            
            if (!$cart) {
                $cartId = $this->cartRepository->createCart($customerId);
            } else {
                $cartId = $cart['cart_id'];
            }

            // Merge guest cart
            if (!empty($guestItems)) {
                $this->cartRepository->mergeGuestCart($cartId, $guestItems);
            }

            $summary = $this->cartRepository->getCartSummary($cartId);
            
            return $res->json([
                'success' => true,
                'message' => 'Cart synced successfully',
                'data' => $summary
            ], 200);

        } catch (\Exception $e) {
            return $res->json([
                'success' => false,
                'message' => 'Failed to sync cart: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lấy Bearer token từ header
     */
    private function getBearerToken()
    {
        // Try different ways to get Authorization header
        $authHeader = '';
        
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        } else {
            // Fallback for servers without getallheaders
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        }
        
        if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            return $matches[1];
        }
        
        return null;
    }

    /**
     * Log activity
     */
    private function logActivity($userId, $action, $entityType, $entityId, $data = null)
    {
        // ActivityLog class not available - skip logging
        // This is not critical for cart functionality
        return;
    }
}
