<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Controller, Request, Response, Container};
use App\Domain\Products\Product;
use App\Support\CloudinaryService;
use App\Support\ResponseHelper;
use App\Core\Validator;
use Exception;
use PDO;

class ProductController extends Controller 
{
    private Product $productModel;
    private ?CloudinaryService $cloudinaryService = null;
    
    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->productModel = new Product($container->database());
        // CloudinaryService sẽ được khởi tạo khi cần thiết
    }
    
    /**
     * Khởi tạo CloudinaryService khi cần thiết
     */
    private function getCloudinaryService(): CloudinaryService
    {
        if ($this->cloudinaryService === null) {
            $this->cloudinaryService = new CloudinaryService();
        }
        return $this->cloudinaryService;
    }
    
    /**
     * GET /api/products - Lấy danh sách sản phẩm hoặc sizes với filter, pagination
     * Query params:
     * - type=size: Lấy danh sách sizes
     * - type=size&available=1: Lấy sizes có sẵn (được sử dụng trong products)
     * - Không có type: Lấy danh sách products (mặc định)
     */
    public function index(Request $req, Response $res)
    {
        try {
            $type = $req->query('type');
            
            // Nếu type=size, xử lý Size operations
            if ($type === 'size') {
                $available = $req->query('available');
                
                if ($available) {
                    // GET /api/v1/products?type=size&available=1
                    $sizes = $this->getAvailableSizes();
                    return $res->json(ResponseHelper::success($sizes));
                } else {
                    // GET /api/v1/products?type=size
                    $sizes = $this->getAllSizes();
                    return $res->json(ResponseHelper::success($sizes));
                }
            }
            
            // Mặc định xử lý Product operations
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
            return $res->json(ResponseHelper::serverError('Failed to fetch data: ' . $e->getMessage()));
        }
    }
    
    /**
     * GET /api/products/{id} - Xem chi tiết sản phẩm hoặc size
     * Query params:
     * - type=size: Lấy chi tiết size
     * - Không có type: Lấy chi tiết product (mặc định)
     */
    public function show(Request $req, Response $res)
    {
        try {
            $id = $req->getAttribute('id') ?? $req->query('id');
            if (!$id) {
                return $res->json(ResponseHelper::error('ID is required'));
            }
            
            $type = $req->query('type');
            
            // Nếu type=size, xử lý Size operations
            if ($type === 'size') {
                // GET /api/v1/products/{id}?type=size
                $size = $this->getSizeById((int)$id);
                
                if (!$size) {
                    return $res->json(ResponseHelper::notFound('Size not found'));
                }
                
                return $res->json(ResponseHelper::success($size));
            }
            
            // Mặc định xử lý Product operations
            $product = $this->productModel->getByIdWithDetails((int)$id);
            
            if (!$product) {
                return $res->json(ResponseHelper::notFound('Product not found'));
            }
            
            return $res->json(ResponseHelper::success($product));
            
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to fetch data: ' . $e->getMessage()));
        }
    }
    
    /**
     * POST /api/products - Tạo sản phẩm hoặc size mới
     * Query params:
     * - type=size: Tạo size mới
     * - Không có type: Tạo product mới (mặc định)
     */
    public function store(Request $req, Response $res)
    {
        $user = $req->getAttribute('user');
        
        // Check admin access - support both array and string roles
        $userRoles = $user['roles'] ?? [];
        $isAdmin = false;
        
        if (is_array($userRoles)) {
            $isAdmin = in_array('admin', $userRoles);
        } elseif (is_string($userRoles)) {
            $isAdmin = $userRoles === 'admin';
        }
        
        // Also check account_type for admin authentication
        if (!$isAdmin && isset($user['account_type'])) {
            $isAdmin = $user['account_type'] === 'admin';
        }
        
        if (!$isAdmin) {
            return $res->json(ResponseHelper::forbidden('Admin access required'));
        }
        
        $type = $req->query('type');
        
        // Nếu type=size, xử lý Size operations
        if ($type === 'size') {
            return $this->createSize($req, $res);
        }
        
        try {
            $data = $req->json();
            
            // Validate required fields cho cấu trúc database mới
            $rules = [
                'product_name' => 'required|string',
                'category_id' => 'required|integer',
                'short_description' => 'string',
                'description' => 'string',
                'material' => 'string',
                'list_price' => 'required|numeric',
                'compare_at_price' => 'numeric',
                'cost_price' => 'numeric',
                'stock' => 'integer',
                'status' => 'string',
                'is_featured' => 'boolean',
                'variants' => 'array',
                'images' => 'array'
            ];
            
            $validator = new Validator($data, $rules);
            
            if (!$validator->validate()) {
                return $res->json(ResponseHelper::error('Validation failed', 400, $validator->getErrors()));
            }
            
            // Bắt đầu transaction để đảm bảo tính toàn vẹn dữ liệu
            $pdo = $this->container->database()->getConnection();
            $pdo->beginTransaction();
            
            try {
                // 1. Tạo sản phẩm với cấu trúc database mới
                $productData = [
                    'product_name' => $data['product_name'],
                    'slug' => $this->generateSlug($data['product_name']),
                    'category_id' => $data['category_id'],
                    'short_description' => $data['short_description'] ?? null,
                    'description' => $data['description'] ?? null,
                    'material' => $data['material'] ?? null,
                    'list_price' => $data['list_price'], // Giá bán chính
                    'compare_at_price' => $data['compare_at_price'] ?? null, // Giá so sánh (gạch ngang)
                    'cost_price' => $data['cost_price'] ?? null, // Giá vốn
                    'stock' => 0, // Tồn kho chỉ tăng qua phiếu nhập
                    'status' => $data['status'] ?? 'active',
                    'is_featured' => $data['is_featured'] ?? false
                ];
                
                $productId = $this->productModel->create($productData);
                
                if (!$productId) {
                    throw new Exception('Failed to create product');
                }
                
                // 2. Xử lý variants nếu có (với size_id, sku, stock_quantity)
                if (!empty($data['variants']) && is_array($data['variants'])) {
                    foreach ($data['variants'] as $index => $variant) {
                        if (!isset($variant['size_id'])) {
                            throw new Exception("Variant at index {$index} missing size_id");
                        }
                        
                        $variantData = [
                            'product_id' => $productId,
                            'size_id' => $variant['size_id'],
                            'sku' => $variant['sku'] ?? null,
                            'stock_quantity' => 0,
                            'status' => $variant['status'] ?? 'in_stock',
                            'is_active' => $variant['is_active'] ?? true
                        ];
                        
                        $variantId = $this->createVariant($variantData);
                        if (!$variantId) {
                            throw new Exception("Failed to create variant at index {$index}");
                        }
                    }
                }
                
                // 3. Xử lý ảnh nếu có (với cấu trúc mới)
                if (!empty($data['images']) && is_array($data['images'])) {
                    foreach ($data['images'] as $index => $imageData) {
                        $uploadResult = $this->handleImageUpload($imageData, $productId);
                        
                        if (!$uploadResult['success']) {
                            // Log warning nhưng không fail toàn bộ transaction
                            error_log("Image upload failed for product {$productId}, image {$index}: " . ($uploadResult['error'] ?? 'Unknown error'));
                            continue;
                        }
                        
                        $imageRecord = [
                            'product_id' => $productId,
                            'variant_id' => $imageData['variant_id'] ?? null,
                            'url' => $uploadResult['url'],
                            'media_public_id' => $uploadResult['public_id'] ?? null,
                            'position' => $index,
                            'alt_text' => $imageData['alt_text'] ?? null,
                            'image_type' => $imageData['image_type'] ?? 'gallery',
                            'is_main' => ($imageData['image_type'] ?? 'gallery') === 'thumbnail' ? 1 : 0
                        ];
                        
                        $imageId = $this->createImage($imageRecord);
                        if (!$imageId) {
                            error_log("Failed to create image record for product {$productId}, image {$index}");
                        }
                    }
                }
                
                // Commit transaction
                $pdo->commit();
                
                // Lấy sản phẩm với đầy đủ thông tin để trả về
                $product = $this->productModel->getByIdWithDetails($productId);
                return $res->json(ResponseHelper::success($product, 'Product created successfully with all related data'), 201);
                
            } catch (Exception $e) {
                // Rollback transaction nếu có lỗi
                $pdo->rollback();
                throw $e;
            }
            
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to create product: ' . $e->getMessage()));
        }
    }
    
    /**
     * PUT /api/products/{id} - Cập nhật sản phẩm hoặc size
     * Query params:
     * - type=size: Cập nhật size
     * - Không có type: Cập nhật product (mặc định)
     */
    public function update(Request $req, Response $res)
    {
        $user = $req->getAttribute('user');
        
        // Debug: log user data để kiểm tra
        error_log("User data in update: " . json_encode($user));
        error_log("User roles: " . json_encode($user['roles'] ?? 'no roles'));
        
        // Check admin access - support both array and string roles
        $userRoles = $user['roles'] ?? [];
        $isAdmin = false;
        
        if (is_array($userRoles)) {
            $isAdmin = in_array('admin', $userRoles);
        } elseif (is_string($userRoles)) {
            $isAdmin = $userRoles === 'admin';
        }
        
        // Also check account_type for admin authentication
        if (!$isAdmin && isset($user['account_type'])) {
            $isAdmin = $user['account_type'] === 'admin';
        }
        
        if (!$isAdmin) {
            return $res->json(ResponseHelper::forbidden('Admin access required'));
        }
        
        $type = $req->query('type');
        
        // Nếu type=size, xử lý Size operations
        if ($type === 'size') {
            return $this->updateSize($req, $res);
        }
        
        try {
            $id = $req->getAttribute('id') ?? $req->query('id');
            if (!$id) {
                return $res->json(ResponseHelper::error('Product ID is required'));
            }
            
            $product = $this->productModel->findById((int)$id);
            if (!$product) {
                return $res->json(ResponseHelper::notFound('Product not found'));
            }
            
            $data = $req->json();
            
            // Bắt đầu transaction để đảm bảo tính toàn vẹn dữ liệu
            $pdo = $this->container->database()->getConnection();
            $pdo->beginTransaction();
            
            try {
                // 1. Cập nhật thông tin sản phẩm với cấu trúc database mới
                $productData = [];
                $allowedFields = [
                    'product_name', 'category_id', 'short_description', 'description', 
                    'material', 'list_price', 'compare_at_price', 'cost_price', 
                    'status', 'is_featured'
                ];
                
                foreach ($allowedFields as $field) {
                    if (isset($data[$field])) {
                        $productData[$field] = $data[$field];
                    }
                }
                
                if (isset($data['product_name'])) {
                    $productData['slug'] = $this->generateSlug($data['product_name']);
                }
                
                if (!empty($productData)) {
                    $updateResult = $this->productModel->update((int)$id, $productData);
                    if (!$updateResult) {
                        throw new Exception('Failed to update product basic information');
                    }
                }
                
                // 2. Cập nhật variants (với cấu trúc database mới)
                if (isset($data['variants']) && is_array($data['variants'])) {
                    $variantStmt = $pdo->prepare(
                        'SELECT size_id, sku, stock_quantity FROM product_variants WHERE product_id = ?'
                    );
                    $variantStmt->execute([(int)$id]);
                    $existingVariants = $variantStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                    $stockBySizeSku = [];
                    foreach ($existingVariants as $ev) {
                        $key = (int)($ev['size_id'] ?? 0) . ':' . trim((string)($ev['sku'] ?? ''));
                        $stockBySizeSku[$key] = (int)($ev['stock_quantity'] ?? 0);
                    }

                    // Xóa variants cũ
                    $deleteVariantsResult = $this->productModel->deleteVariantsByProductId((int)$id);
                    if (!$deleteVariantsResult) {
                        throw new Exception('Failed to delete old variants');
                    }
                    
                    // Tạo variants mới
                    foreach ($data['variants'] as $index => $variant) {
                        if (!isset($variant['size_id'])) {
                            throw new Exception("Variant at index {$index} missing size_id");
                        }

                        $stockKey = (int)$variant['size_id'] . ':' . trim((string)($variant['sku'] ?? ''));
                        $preservedStock = $stockBySizeSku[$stockKey] ?? 0;
                        
                        $variantData = [
                            'product_id' => $id,
                            'size_id' => $variant['size_id'],
                            'sku' => $variant['sku'] ?? null,
                            'stock_quantity' => $preservedStock,
                            'status' => $variant['status'] ?? 'in_stock',
                            'is_active' => $variant['is_active'] ?? true
                        ];
                        
                        $variantId = $this->createVariant($variantData);
                        if (!$variantId) {
                            throw new Exception("Failed to create variant at index {$index}");
                        }
                    }
                }
                
                // 3. Cập nhật ảnh (với cấu trúc database mới)
                if (isset($data['images']) && is_array($data['images'])) {
                    // Lấy ảnh cũ để xóa khỏi Cloudinary
                    $oldImages = $this->productModel->getImagesByProductId((int)$id);
                    
                    // Xóa ảnh cũ khỏi Cloudinary (chỉ những ảnh được upload lên Cloudinary)
                    foreach ($oldImages as $oldImage) {
                        if ($oldImage['media_public_id']) {
                            try {
                                $this->getCloudinaryService()->delete($oldImage['media_public_id']);
                            } catch (Exception $e) {
                                // Log warning nhưng không fail transaction
                                error_log("Failed to delete image from Cloudinary: " . $e->getMessage());
                            }
                        }
                    }
                    
                    // Xóa records ảnh cũ
                    $deleteImagesResult = $this->productModel->deleteImagesByProductId((int)$id);
                    if (!$deleteImagesResult) {
                        throw new Exception('Failed to delete old images');
                    }
                    
                    // Upload và tạo ảnh mới
                    foreach ($data['images'] as $index => $imageData) {
                        $uploadResult = $this->handleImageUpload($imageData, (int)$id);
                        
                        if (!$uploadResult['success']) {
                            // Log warning nhưng không fail toàn bộ transaction
                            error_log("Image upload failed for product {$id}, image {$index}: " . ($uploadResult['error'] ?? 'Unknown error'));
                            continue;
                        }
                        
                        $imageRecord = [
                            'product_id' => $id,
                            'variant_id' => $imageData['variant_id'] ?? null,
                            'url' => $uploadResult['url'],
                            'media_public_id' => $uploadResult['public_id'] ?? null,
                            'position' => $index,
                            'alt_text' => $imageData['alt_text'] ?? null,
                            'image_type' => $imageData['image_type'] ?? 'gallery',
                            'is_main' => ($imageData['image_type'] ?? 'gallery') === 'thumbnail' ? 1 : 0
                        ];
                        
                        $imageId = $this->createImage($imageRecord);
                        if (!$imageId) {
                            error_log("Failed to create image record for product {$id}, image {$index}");
                        }
                    }
                }
                
                // Commit transaction
                $pdo->commit();
                
                // Lấy sản phẩm với đầy đủ thông tin để trả về
                $updatedProduct = $this->productModel->getByIdWithDetails((int)$id);
                return $res->json(ResponseHelper::success($updatedProduct, 'Product updated successfully with all related data'));
                
            } catch (Exception $e) {
                // Rollback transaction nếu có lỗi
                $pdo->rollback();
                throw $e;
            }
            
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to update product: ' . $e->getMessage()));
        }
    }
    
    /**
     * DELETE /api/products/{id} - Xóa sản phẩm hoặc size
     * Query params:
     * - type=size: Xóa size
     * - Không có type: Xóa product (mặc định)
     */
    public function destroy(Request $req, Response $res)
    {
        $user = $req->getAttribute('user');
        
        // Check admin access - support both array and string roles
        $userRoles = $user['roles'] ?? [];
        $isAdmin = false;
        
        if (is_array($userRoles)) {
            $isAdmin = in_array('admin', $userRoles);
        } elseif (is_string($userRoles)) {
            $isAdmin = $userRoles === 'admin';
        }
        
        // Also check account_type for admin authentication
        if (!$isAdmin && isset($user['account_type'])) {
            $isAdmin = $user['account_type'] === 'admin';
        }
        
        if (!$isAdmin) {
            return $res->json(ResponseHelper::forbidden('Admin access required'));
        }
        
        $type = $req->query('type');
        
        // Nếu type=size, xử lý Size operations
        if ($type === 'size') {
            return $this->deleteSize($req, $res);
        }
        
        try {
            $id = $req->getAttribute('id') ?? $req->query('id');
            if (!$id) {
                return $res->json(ResponseHelper::error('Product ID is required'));
            }
            
            $product = $this->productModel->findById((int)$id);
            if (!$product) {
                return $res->json(ResponseHelper::notFound('Product not found'));
            }
            
            // Bắt đầu transaction để đảm bảo tính toàn vẹn dữ liệu
            $pdo = $this->container->database()->getConnection();
            $pdo->beginTransaction();
            
            try {
                // 1. Lấy và xóa ảnh khỏi Cloudinary trước
                $images = $this->productModel->getImagesByProductId((int)$id);
                foreach ($images as $image) {
                    if ($image['media_public_id']) {
                        try {
                            $this->getCloudinaryService()->delete($image['media_public_id']);
                        } catch (Exception $e) {
                            // Log warning nhưng không fail transaction
                            error_log("Failed to delete image from Cloudinary: " . $e->getMessage());
                        }
                    }
                }
                
                // 2. Xóa tất cả dữ liệu liên quan theo thứ tự đúng (tránh foreign key constraint)
                
                // Xóa images
                $deleteImagesResult = $this->productModel->deleteImagesByProductId((int)$id);
                if (!$deleteImagesResult) {
                    throw new Exception('Failed to delete product images');
                }
                
                // Xóa variants
                $deleteVariantsResult = $this->productModel->deleteVariantsByProductId((int)$id);
                if (!$deleteVariantsResult) {
                    throw new Exception('Failed to delete product variants');
                }
                
                // Cuối cùng xóa sản phẩm
                $deleteProductResult = $this->productModel->delete((int)$id);
                if (!$deleteProductResult) {
                    throw new Exception('Failed to delete product');
                }
                
                // Commit transaction
                $pdo->commit();
                
                return $res->json(ResponseHelper::success(null, 'Product and all related data deleted successfully'));
                
            } catch (Exception $e) {
                // Rollback transaction nếu có lỗi
                $pdo->rollback();
                throw $e;
            }
            
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to delete product: ' . $e->getMessage()));
        }
    }
    
    /**
     * GET /api/v1/products/featured - Lấy sản phẩm nổi bật
     */
    public function featured(Request $req, Response $res)
    {
        try {
            $limit = (int)($req->query('limit') ?? 10);
            
            $filters = ['is_featured' => 1];
            $products = $this->productModel->getAllWithDetails($filters, $limit, 0);
            
            return $res->json(ResponseHelper::success($products));
            
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to fetch featured products: ' . $e->getMessage()));
        }
    }
    
    /**
     * GET /api/v1/products/search - Tìm kiếm sản phẩm
     */
    public function search(Request $req, Response $res)
    {
        try {
            $query = $req->query('q');
            if (!$query) {
                return $res->json(ResponseHelper::error('Search query is required'));
            }
            
            $page = (int)($req->query('page') ?? 1);
            $limit = (int)($req->query('limit') ?? 20);
            $offset = ($page - 1) * $limit;
            
            $filters = ['search' => $query];
            $products = $this->productModel->getAllWithDetails($filters, $limit, $offset);
            $total = $this->productModel->getCount($filters);
            
            return $res->json(ResponseHelper::paginated($products, $total, $limit, $page));
            
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to search products: ' . $e->getMessage()));
        }
    }
    
    /**
     * Xử lý upload ảnh - hỗ trợ file upload và URL
     */
    private function handleImageUpload(array $imageData, int $productId): array
    {
        try {
            // Trường hợp 1: Upload file lên Cloudinary (base64 data)
            if (isset($imageData['file'])) {
                $uploadResult = $this->getCloudinaryService()->uploadBase64Image($imageData['file'], 'shopswift/products');
                
                if ($uploadResult['success']) {
                    return [
                        'success' => true,
                        'url' => $uploadResult['url'],
                        'public_id' => $uploadResult['public_id'] ?? null
                    ];
                } else {
                    return ['success' => false, 'error' => $uploadResult['error'] ?? 'Upload failed'];
                }
            }
            
            // Trường hợp 2: Upload từ URL lên Cloudinary
            if (isset($imageData['upload_url']) && filter_var($imageData['upload_url'], FILTER_VALIDATE_URL)) {
                $uploadResult = $this->getCloudinaryService()->uploadFromUrl($imageData['upload_url'], 'shopswift/products');
                
                if ($uploadResult['success']) {
                    return [
                        'success' => true,
                        'url' => $uploadResult['url'],
                        'public_id' => $uploadResult['public_id'] ?? null
                    ];
                } else {
                    return ['success' => false, 'error' => $uploadResult['error'] ?? 'Upload from URL failed'];
                }
            }
            
            // Trường hợp 3: Sử dụng URL có sẵn (không upload)
            if (isset($imageData['url']) && filter_var($imageData['url'], FILTER_VALIDATE_URL)) {
                return [
                    'success' => true,
                    'url' => $imageData['url'],
                    'public_id' => null // Không có public_id vì không upload
                ];
            }
            
            return ['success' => false, 'error' => 'No valid image data provided (file, upload_url, or url required)'];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Tạo slug từ tên sản phẩm (unique)
     */
    private function generateSlug(string $name): string
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
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
     * Check if slug exists in database
     */
    private function slugExists(string $slug): bool
    {
        $pdo = $this->container->database()->getConnection();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE slug = ?");
        $stmt->execute([$slug]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Create variant
     */
    private function createVariant(array $data): int
    {
        $pdo = $this->container->database()->getConnection();
        $fields = array_keys($data);
        $placeholders = str_repeat('?,', count($fields) - 1) . '?';
        
        $sql = "INSERT INTO product_variants (" . implode(',', $fields) . ") VALUES ($placeholders)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_values($data));
        
        return (int) $pdo->lastInsertId();
    }

         /**
      * Create image
      */
     private function createImage(array $data): int
     {
         $pdo = $this->container->database()->getConnection();
         $fields = array_keys($data);
         $placeholders = str_repeat('?,', count($fields) - 1) . '?';
         
         $sql = "INSERT INTO product_images (" . implode(',', $fields) . ") VALUES ($placeholders)";
         
         $stmt = $pdo->prepare($sql);
         $stmt->execute(array_values($data));
         
         return (int) $pdo->lastInsertId();
     }

           // ==================== SIZE OPERATION METHODS ====================
 
      /**
       * Private method để tạo size mới
       */
      private function createSize(Request $req, Response $res)
      {
          try {
              $data = $req->json();
              
              // Validate required fields
              $rules = [
                  'size_name' => 'required|max:10'
              ];
              
              $validator = new Validator($data, $rules);
              
              if (!$validator->validate()) {
                  return $res->json(ResponseHelper::error('Validation failed', 400, $validator->getErrors()));
              }
              
              // Check if size name already exists
              $existingSize = $this->getSizeByName($data['size_name']);
              if ($existingSize) {
                  return $res->json(ResponseHelper::error('Size name already exists'));
              }
              
              $sizeId = $this->createSizeRecord($data);
              
              if (!$sizeId) {
                  return $res->json(ResponseHelper::serverError('Failed to create size'));
              }
              
              $size = $this->getSizeById($sizeId);
              return $res->json(ResponseHelper::success($size, 'Size created successfully'), 201);
              
          } catch (Exception $e) {
              return $res->json(ResponseHelper::serverError('Failed to create size: ' . $e->getMessage()));
          }
      }
      
      /**
       * Private method để cập nhật size
       */
      private function updateSize(Request $req, Response $res)
      {
          try {
              $id = $req->getAttribute('id') ?? $req->query('id');
              if (!$id) {
                  return $res->json(ResponseHelper::error('Size ID is required'));
              }
              
              $size = $this->getSizeById((int)$id);
              if (!$size) {
                  return $res->json(ResponseHelper::notFound('Size not found'));
              }
              
              $data = $req->json();
              
              // Validate required fields
              $rules = [
                  'size_name' => 'required|max:10'
              ];
              
              $validator = new Validator($data, $rules);
              
              if (!$validator->validate()) {
                  return $res->json(ResponseHelper::error('Validation failed', 400, $validator->getErrors()));
              }
              
              // Check if size name already exists (excluding current size)
              $existingSize = $this->getSizeByName($data['size_name']);
              if ($existingSize && $existingSize['size_id'] != $id) {
                  return $res->json(ResponseHelper::error('Size name already exists'));
              }
              
              $updateResult = $this->updateSizeRecord((int)$id, $data);
              
              if (!$updateResult) {
                  return $res->json(ResponseHelper::serverError('Failed to update size'));
              }
              
              $updatedSize = $this->getSizeById((int)$id);
              return $res->json(ResponseHelper::success($updatedSize, 'Size updated successfully'));
              
          } catch (Exception $e) {
              return $res->json(ResponseHelper::serverError('Failed to update size: ' . $e->getMessage()));
          }
      }
      
      /**
       * Private method để xóa size
       */
      private function deleteSize(Request $req, Response $res)
      {
          try {
              $id = $req->getAttribute('id') ?? $req->query('id');
              if (!$id) {
                  return $res->json(ResponseHelper::error('Size ID is required'));
              }
              
              $size = $this->getSizeById((int)$id);
              if (!$size) {
                  return $res->json(ResponseHelper::notFound('Size not found'));
              }
              
              $deleteResult = $this->deleteSizeRecord((int)$id);
              
              if (!$deleteResult) {
                  return $res->json(ResponseHelper::serverError('Failed to delete size'));
              }
              
              return $res->json(ResponseHelper::success(null, 'Size deleted successfully'));
              
          } catch (Exception $e) {
              return $res->json(ResponseHelper::serverError('Failed to delete size: ' . $e->getMessage()));
          }
      }

     // ==================== SIZE HELPER METHODS ====================

     /**
      * Get all sizes
      */
     private function getAllSizes(): array
     {
         $pdo = $this->container->database()->getConnection();
         $stmt = $pdo->query("SELECT * FROM sizes ORDER BY size_id ASC");
         return $stmt->fetchAll(PDO::FETCH_ASSOC);
     }

     /**
      * Get size by ID
      */
     private function getSizeById(int $id): ?array
     {
         $pdo = $this->container->database()->getConnection();
         $stmt = $pdo->prepare("SELECT * FROM sizes WHERE size_id = ?");
         $stmt->execute([$id]);
         $result = $stmt->fetch(PDO::FETCH_ASSOC);
         return $result ?: null;
     }

     /**
      * Get size by name
      */
     private function getSizeByName(string $name): ?array
     {
         $pdo = $this->container->database()->getConnection();
         $stmt = $pdo->prepare("SELECT * FROM sizes WHERE size_name = ?");
         $stmt->execute([$name]);
         $result = $stmt->fetch(PDO::FETCH_ASSOC);
         return $result ?: null;
     }

           /**
       * Create size record
       */
      private function createSizeRecord(array $data): int
      {
          $pdo = $this->container->database()->getConnection();
          $fields = array_keys($data);
          $placeholders = str_repeat('?,', count($fields) - 1) . '?';
          
          $sql = "INSERT INTO sizes (" . implode(',', $fields) . ") VALUES ($placeholders)";
          
          $stmt = $pdo->prepare($sql);
          $stmt->execute(array_values($data));
          
          return (int) $pdo->lastInsertId();
      }

           /**
       * Update size record
       */
      private function updateSizeRecord(int $id, array $data): bool
      {
          $pdo = $this->container->database()->getConnection();
          $fields = [];
          foreach (array_keys($data) as $field) {
              $fields[] = "$field = ?";
          }
          
          $sql = "UPDATE sizes SET " . implode(', ', $fields) . " WHERE size_id = ?";
          
          $values = array_values($data);
          $values[] = $id;
          
          $stmt = $pdo->prepare($sql);
          return $stmt->execute($values);
      }

           /**
       * Delete size record
       */
      private function deleteSizeRecord(int $id): bool
      {
          $pdo = $this->container->database()->getConnection();
          
          // Check if size is used in any product variants
          $stmt = $pdo->prepare("SELECT COUNT(*) FROM product_variants WHERE size_id = ?");
          $stmt->execute([$id]);
          $count = $stmt->fetchColumn();
          
          if ($count > 0) {
              throw new \Exception("Cannot delete size: it is used in {$count} product variants");
          }
          
          $stmt = $pdo->prepare("DELETE FROM sizes WHERE size_id = ?");
          return $stmt->execute([$id]);
      }

     /**
      * Get available sizes (used in products)
      */
     private function getAvailableSizes(): array
     {
         $pdo = $this->container->database()->getConnection();
         $sql = "
             SELECT DISTINCT s.*
             FROM sizes s
             INNER JOIN product_variants pv ON s.size_id = pv.size_id
             WHERE pv.is_active = 1 AND pv.stock_quantity > 0
             ORDER BY s.size_id ASC
         ";
         
         $stmt = $pdo->prepare($sql);
         $stmt->execute();
         return $stmt->fetchAll(PDO::FETCH_ASSOC);
     }

    /**
     * GET /api/v1/products/variants - Lấy tất cả product variants
     */
    public function getAllVariants(Request $req, Response $res)
    {
        try {
            $pdo = $this->container->database()->getConnection();
            
            $sql = "SELECT 
                        pv.variant_id,
                        pv.product_id,
                        pv.size_id,
                        pv.sku,
                        pv.stock_quantity,
                        pv.status,
                        p.product_name,
                        p.list_price,
                        s.size_name,
                        CONCAT(p.product_name, ' - ', s.size_name) as display_name
                    FROM product_variants pv
                    JOIN products p ON pv.product_id = p.product_id
                    JOIN sizes s ON pv.size_id = s.size_id
                    WHERE pv.is_active = 1
                    ORDER BY p.product_name, s.size_name";
            
            $stmt = $pdo->query($sql);
            $variants = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Convert strings to numbers
            foreach ($variants as &$variant) {
                $variant['variant_id'] = (int)$variant['variant_id'];
                $variant['product_id'] = (int)$variant['product_id'];
                $variant['size_id'] = (int)$variant['size_id'];
                $variant['stock_quantity'] = (int)$variant['stock_quantity'];
                $variant['list_price'] = isset($variant['list_price']) ? (float)$variant['list_price'] : 0.0;
            }
            
            return $res->json(ResponseHelper::success($variants, 'Variants retrieved successfully'));
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to fetch variants: ' . $e->getMessage()));
        }
    }
}
