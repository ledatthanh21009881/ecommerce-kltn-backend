<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Controller, Request, Response, Container};
use App\Domain\Products\Product;
use App\Services\CloudinaryService;
use App\Support\ResponseHelper;
use App\Core\Validator;
use Exception;

class ProductController extends Controller 
{
    private Product $productModel;
    private CloudinaryService $cloudinaryService;
    
    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->productModel = new Product($container->database());
        $this->cloudinaryService = new CloudinaryService();
    }
    
    /**
     * GET /api/products - Lấy danh sách sản phẩm với filter, pagination
     */
    public function index(Request $req, Response $res)
    {
        try {
            $page = (int)($req->query('page') ?? 1);
            $limit = (int)($req->query('limit') ?? 20);
            $offset = ($page - 1) * $limit;
            
            $filters = [];
            if ($req->query('category')) {
                $filters['category_id'] = $req->query('category');
            }
            if ($req->query('search')) {
                $filters['search'] = $req->query('search');
            }
            if ($req->query('status')) {
                $filters['status'] = $req->query('status');
            }
            if ($req->query('is_featured')) {
                $filters['is_featured'] = $req->query('is_featured');
            }
            
            $products = $this->productModel->getAllWithDetails($filters, $limit, $offset);
            $total = $this->productModel->getCount($filters);
            
            return $res->json(ResponseHelper::paginated($products, $total, $limit, $page));
            
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to fetch products: ' . $e->getMessage()));
        }
    }
    
    /**
     * GET /api/products/{id} - Xem chi tiết sản phẩm với ảnh và variants
     */
    public function show(Request $req, Response $res)
    {
        try {
            $id = $req->getAttribute('id') ?? $req->query('id');
            if (!$id) {
                return $res->json(ResponseHelper::error('Product ID is required'));
            }
            
            $product = $this->productModel->getByIdWithDetails((int)$id);
            
            if (!$product) {
                return $res->json(ResponseHelper::notFound('Product not found'));
            }
            
            return $res->json(ResponseHelper::success($product));
            
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to fetch product: ' . $e->getMessage()));
        }
    }
    
    /**
     * POST /api/products - Tạo sản phẩm mới với ảnh và variants
     */
    public function store(Request $req, Response $res)
    {
        $user = $req->getAttribute('user');
        
        if (!in_array('admin', $user['roles'] ?? [])) {
            return $res->json(ResponseHelper::forbidden('Admin access required'));
        }
        
        try {
            $data = $req->json();
            
            // Validate required fields
            $rules = [
                'product_name' => 'required|string',
                'category_id' => 'required|integer',
                'short_description' => 'string',
                'description' => 'string',
                'material' => 'string',
                'is_featured' => 'boolean',
                'variants' => 'array',
                'images' => 'array'
            ];
            
            $validator = new Validator($data, $rules);
            
            if (!$validator->validate()) {
                return $res->json(ResponseHelper::error('Validation failed', 400, $validator->getErrors()));
            }
            
            try {
                // 1. Tạo sản phẩm
                $productData = [
                    'product_name' => $data['product_name'],
                    'slug' => $this->generateSlug($data['product_name']),
                    'category_id' => $data['category_id'],
                    'short_description' => $data['short_description'] ?? null,
                    'description' => $data['description'] ?? null,
                    'material' => $data['material'] ?? null,
                    'status' => $data['status'] ?? 'active',
                    'is_featured' => $data['is_featured'] ?? false
                ];
                
                $productId = $this->productModel->create($productData);
                
                // 2. Xử lý variants nếu có
                if (!empty($data['variants'])) {
                    foreach ($data['variants'] as $variant) {
                        $variantData = [
                            'product_id' => $productId,
                            'size_id' => $variant['size_id'],
                            'price' => $variant['price'],
                            'stock_quantity' => $variant['stock_quantity'] ?? 0
                        ];
                        $this->productModel->createVariant($variantData);
                    }
                }
                
                // 3. Xử lý ảnh nếu có
                if (!empty($data['images'])) {
                    foreach ($data['images'] as $index => $imageData) {
                        $uploadResult = $this->handleImageUpload($imageData, $productId);
                        if ($uploadResult['success']) {
                            $imageRecord = [
                                'product_id' => $productId,
                                'url' => $uploadResult['url'],
                                'media_public_id' => $uploadResult['public_id'] ?? null,
                                'position' => $index,
                                'alt_text' => $imageData['alt_text'] ?? null,
                                'image_type' => $imageData['image_type'] ?? 'gallery',
                                'is_main' => $index === 0 ? 1 : 0
                            ];
                            $this->productModel->createImage($imageRecord);
                        }
                    }
                }
                
                $product = $this->productModel->getByIdWithDetails($productId);
                return $res->json(ResponseHelper::success($product, 'Product created successfully'), 201);
                
            } catch (Exception $e) {
                throw $e;
            }
            
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to create product: ' . $e->getMessage()));
        }
    }
    
    /**
     * PUT /api/products/{id} - Cập nhật sản phẩm với ảnh và variants
     */
    public function update(Request $req, Response $res)
    {
        $user = $req->getAttribute('user');
        
        if (!in_array('admin', $user['roles'] ?? [])) {
            return $res->json(ResponseHelper::forbidden('Admin access required'));
        }
        
        try {
            $id = $req->getAttribute('id') ?? $req->query('id');
            if (!$id) {
                return $res->json(ResponseHelper::error('Product ID is required'));
            }
            
            $product = $this->productModel->getById((int)$id);
            if (!$product) {
                return $res->json(ResponseHelper::notFound('Product not found'));
            }
            
            $data = $req->json();
            
            try {
                // 1. Cập nhật thông tin sản phẩm
                $productData = [];
                $allowedFields = ['product_name', 'category_id', 'short_description', 'description', 'material', 'status', 'is_featured'];
                
                foreach ($allowedFields as $field) {
                    if (isset($data[$field])) {
                        $productData[$field] = $data[$field];
                    }
                }
                
                if (isset($data['product_name'])) {
                    $productData['slug'] = $this->generateSlug($data['product_name']);
                }
                
                if (!empty($productData)) {
                    $this->productModel->update((int)$id, $productData);
                }
                
                // 2. Cập nhật variants
                if (isset($data['variants'])) {
                    // Xóa variants cũ và tạo variants mới
                    $this->productModel->deleteVariantsByProductId((int)$id);
                    
                    foreach ($data['variants'] as $variant) {
                        $variantData = [
                            'product_id' => $id,
                            'size_id' => $variant['size_id'],
                            'price' => $variant['price'],
                            'stock_quantity' => $variant['stock_quantity'] ?? 0
                        ];
                        $this->productModel->createVariant($variantData);
                    }
                }
                
                // 3. Cập nhật ảnh
                if (isset($data['images'])) {
                    // Lấy ảnh cũ để xóa khỏi Cloudinary
                    $oldImages = $this->productModel->getImagesByProductId((int)$id);
                    
                    // Xóa ảnh cũ khỏi Cloudinary
                    foreach ($oldImages as $oldImage) {
                        if ($oldImage['media_public_id']) {
                            $this->cloudinaryService->delete($oldImage['media_public_id']);
                        }
                    }
                    
                    // Xóa records ảnh cũ
                    $this->productModel->deleteImagesByProductId((int)$id);
                    
                    // Upload và tạo ảnh mới
                    foreach ($data['images'] as $index => $imageData) {
                        $uploadResult = $this->handleImageUpload($imageData, (int)$id);
                        if ($uploadResult['success']) {
                            $imageRecord = [
                                'product_id' => $id,
                                'url' => $uploadResult['url'],
                                'media_public_id' => $uploadResult['public_id'] ?? null,
                                'position' => $index,
                                'alt_text' => $imageData['alt_text'] ?? null,
                                'image_type' => $imageData['image_type'] ?? 'gallery',
                                'is_main' => $index === 0 ? 1 : 0
                            ];
                            $this->productModel->createImage($imageRecord);
                        }
                    }
                }
                
                $updatedProduct = $this->productModel->getByIdWithDetails((int)$id);
                return $res->json(ResponseHelper::success($updatedProduct, 'Product updated successfully'));
                
            } catch (Exception $e) {
                throw $e;
            }
            
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to update product: ' . $e->getMessage()));
        }
    }
    
    /**
     * DELETE /api/products/{id} - Xóa sản phẩm và tất cả dữ liệu liên quan
     */
    public function destroy(Request $req, Response $res)
    {
        $user = $req->getAttribute('user');
        
        if (!in_array('admin', $user['roles'] ?? [])) {
            return $res->json(ResponseHelper::forbidden('Admin access required'));
        }
        
        try {
            $id = $req->getAttribute('id') ?? $req->query('id');
            if (!$id) {
                return $res->json(ResponseHelper::error('Product ID is required'));
            }
            
            $product = $this->productModel->getById((int)$id);
            if (!$product) {
                return $res->json(ResponseHelper::notFound('Product not found'));
            }
            
            try {
                // 1. Lấy và xóa ảnh khỏi Cloudinary
                $images = $this->productModel->getImagesByProductId((int)$id);
                foreach ($images as $image) {
                    if ($image['media_public_id']) {
                        $this->cloudinaryService->delete($image['media_public_id']);
                    }
                }
                
                // 2. Xóa records ảnh
                $this->productModel->deleteImagesByProductId((int)$id);
                
                // 3. Xóa variants
                $this->productModel->deleteVariantsByProductId((int)$id);
                
                // 4. Xóa sản phẩm
                $this->productModel->delete((int)$id);
                
                return $res->json(ResponseHelper::success(null, 'Product deleted successfully'));
                
            } catch (Exception $e) {
                throw $e;
            }
            
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to delete product: ' . $e->getMessage()));
        }
    }
    
    /**
     * Xử lý upload ảnh - hỗ trợ file upload và URL
     */
    private function handleImageUpload(array $imageData, int $productId): array
    {
        try {
            // Nếu có file data (base64 hoặc file path)
            if (isset($imageData['file'])) {
                $options = [
                    'folder' => 'shopswift/products',
                    'public_id' => "product_{$productId}_" . uniqid()
                ];
                
                return $this->cloudinaryService->uploadImage($imageData['file'], $options);
            }
            
            // Nếu có URL trực tiếp
            if (isset($imageData['url']) && filter_var($imageData['url'], FILTER_VALIDATE_URL)) {
                return [
                    'success' => true,
                    'url' => $imageData['url'],
                    'public_id' => null
                ];
            }
            
            return ['success' => false, 'error' => 'No valid image data provided'];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Tạo slug từ tên sản phẩm
     */
    private function generateSlug(string $name): string
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        return trim($slug, '-');
    }
}
