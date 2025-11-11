<?php
declare(strict_types=1);

/**
 * ContentCategoryController
 * 
 * Controller quản lý Categories cho Content Management System.
 * Xử lý CRUD operations cho content categories (dùng cho Blog & Editorial).
 * 
 * Chức năng:
 * - List categories
 * - Chi tiết category
 * - Tạo/sửa/xóa category
 * - Get categories by content
 * 
 * @package App\Controllers
 * @author ShopSwift Team
 */

namespace App\Controllers;

use App\Core\{Controller, Request, Response, Container};
use App\Support\ResponseHelper;
use PDO;
use Exception;

class ContentCategoryController extends Controller
{
    private PDO $pdo;

    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->pdo = $container->database()->getConnection();
    }

    /**
     * Helper: Generate slug from name
     */
    private function generateSlug(string $name): string
    {
        // Remove Vietnamese diacritics (reuse from ContentController pattern)
        $vietnamese = [
            'à', 'á', 'ạ', 'ả', 'ã', 'â', 'ầ', 'ấ', 'ậ', 'ẩ', 'ẫ', 'ă', 'ằ', 'ắ', 'ặ', 'ẳ', 'ẵ',
            'è', 'é', 'ẹ', 'ẻ', 'ẽ', 'ê', 'ề', 'ế', 'ệ', 'ể', 'ễ',
            'ì', 'í', 'ị', 'ỉ', 'ĩ',
            'ò', 'ó', 'ọ', 'ỏ', 'õ', 'ô', 'ồ', 'ố', 'ộ', 'ổ', 'ỗ', 'ơ', 'ờ', 'ớ', 'ợ', 'ở', 'ỡ',
            'ù', 'ú', 'ụ', 'ủ', 'ũ', 'ư', 'ừ', 'ứ', 'ự', 'ử', 'ữ',
            'ỳ', 'ý', 'ỵ', 'ỷ', 'ỹ',
            'đ',
            'À', 'Á', 'Ạ', 'Ả', 'Ã', 'Â', 'Ầ', 'Ấ', 'Ậ', 'Ẩ', 'Ẫ', 'Ă', 'Ằ', 'Ắ', 'Ặ', 'Ẳ', 'Ẵ',
            'È', 'É', 'Ẹ', 'Ẻ', 'Ẽ', 'Ê', 'Ề', 'Ế', 'Ệ', 'Ể', 'Ễ',
            'Ì', 'Í', 'Ị', 'Ỉ', 'Ĩ',
            'Ò', 'Ó', 'Ọ', 'Ỏ', 'Õ', 'Ô', 'Ồ', 'Ố', 'Ộ', 'Ổ', 'Ỗ', 'Ơ', 'Ờ', 'Ớ', 'Ợ', 'Ở', 'Ỡ',
            'Ù', 'Ú', 'Ụ', 'Ủ', 'Ũ', 'Ư', 'Ừ', 'Ứ', 'Ự', 'Ử', 'Ữ',
            'Ỳ', 'Ý', 'Ỵ', 'Ỷ', 'Ỹ',
            'Đ'
        ];
        
        $english = [
            'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a',
            'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e',
            'i', 'i', 'i', 'i', 'i',
            'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o',
            'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u',
            'y', 'y', 'y', 'y', 'y',
            'd',
            'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A',
            'E', 'E', 'E', 'E', 'E', 'E', 'E', 'E', 'E', 'E', 'E',
            'I', 'I', 'I', 'I', 'I',
            'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O',
            'U', 'U', 'U', 'U', 'U', 'U', 'U', 'U', 'U', 'U', 'U',
            'Y', 'Y', 'Y', 'Y', 'Y',
            'D'
        ];
        
        $text = str_replace($vietnamese, $english, $name);
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        $text = trim($text, '-');
        
        return $text;
    }

    /**
     * Helper: Make slug unique
     */
    private function makeSlugUnique(string $slug, ?int $excludeId = null): string
    {
        $baseSlug = $slug;
        $counter = 1;
        
        while (true) {
            $sql = "SELECT COUNT(*) as count FROM content_categories WHERE slug = ?";
            $params = [$slug];
            
            if ($excludeId) {
                $sql .= " AND category_id != ?";
                $params[] = $excludeId;
            }
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ((int)$result['count'] === 0) {
                break;
            }
            
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }

    /**
     * GET /api/backend/v1/content/categories - List categories
     */
    public function index(Request $req, Response $res): void
    {
        try {
            // Nếu có id query param, trả về chi tiết
            $id = $req->query('id');
            if ($id) {
                $this->show($req, $res);
                return;
            }

            $sql = "SELECT 
                        category_id,
                        name,
                        slug,
                        description,
                        created_at,
                        (SELECT COUNT(*) FROM content_category_relations WHERE category_id = cc.category_id) as content_count
                    FROM content_categories cc
                    ORDER BY name ASC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $res->json(ResponseHelper::success($categories, 'Categories retrieved successfully'));
            return;
        } catch (Exception $e) {
            error_log('Error fetching categories: ' . $e->getMessage());
            $res->status(200)->json([
                'success' => false,
                'message' => 'Failed to fetch categories',
                'status_code' => 200,
                'data' => []
            ]);
            return;
        }
    }

    /**
     * GET /api/backend/v1/content/categories/{id} - Chi tiết category
     */
    public function show(Request $req, Response $res): void
    {
        try {
            $id = (int)$req->param('id');
            
            if (!$id) {
                $res->json(ResponseHelper::badRequest('Category ID is required'));
                return;
            }

            $sql = "SELECT 
                        category_id,
                        name,
                        slug,
                        description,
                        created_at
                    FROM content_categories
                    WHERE category_id = ?";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$id]);
            $category = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$category) {
                $res->json(ResponseHelper::notFound('Category not found'));
                return;
            }

            // Get contents in this category
            $contentSql = "SELECT 
                            c.content_id,
                            c.title,
                            c.slug,
                            c.content_type,
                            c.status
                          FROM contents c
                          JOIN content_category_relations ccr ON c.content_id = ccr.content_id
                          WHERE ccr.category_id = ?
                          ORDER BY c.created_at DESC";
            $contentStmt = $this->pdo->prepare($contentSql);
            $contentStmt->execute([$id]);
            $category['contents'] = $contentStmt->fetchAll(PDO::FETCH_ASSOC);

            $res->json(ResponseHelper::success($category, 'Category retrieved successfully'));
            return;
        } catch (Exception $e) {
            error_log('Error fetching category: ' . $e->getMessage());
            $res->status(200)->json([
                'success' => false,
                'message' => 'Failed to fetch category',
                'status_code' => 200,
                'data' => null
            ]);
            return;
        }
    }

    /**
     * POST /api/backend/v1/content/categories - Tạo category mới
     */
    public function store(Request $req, Response $res): void
    {
        try {
            $data = $req->json();

            // Validation
            $errors = [];
            if (empty($data['name'])) {
                $errors['name'] = 'Name is required';
            }

            if (!empty($errors)) {
                $res->json(ResponseHelper::validationError($errors));
                return;
            }

            // Generate slug if not provided
            $slug = !empty($data['slug']) ? $data['slug'] : $this->generateSlug($data['name']);
            $slug = $this->makeSlugUnique($slug);

            // Insert category
            $sql = "INSERT INTO content_categories (name, slug, description) VALUES (?, ?, ?)";
            $params = [
                $data['name'],
                $slug,
                $data['description'] ?? null
            ];

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $categoryId = (int)$this->pdo->lastInsertId();

            // Get created category
            $req->setParam('id', (string)$categoryId);
            $this->show($req, $res);
        } catch (Exception $e) {
            error_log('Error creating category: ' . $e->getMessage());
            $res->status(200)->json([
                'success' => false,
                'message' => 'Failed to create category',
                'status_code' => 200,
                'data' => null
            ]);
            return;
        }
    }

    /**
     * PUT /api/backend/v1/content/categories/{id} - Cập nhật category
     */
    public function update(Request $req, Response $res): void
    {
        try {
            $id = (int)$req->param('id');
            
            if (!$id) {
                $res->json(ResponseHelper::badRequest('Category ID is required'));
                return;
            }

            // Check if category exists
            $checkSql = "SELECT category_id FROM content_categories WHERE category_id = ?";
            $checkStmt = $this->pdo->prepare($checkSql);
            $checkStmt->execute([$id]);
            if (!$checkStmt->fetch()) {
                $res->json(ResponseHelper::notFound('Category not found'));
                return;
            }

            $data = $req->json();

            // Build update query
            $updates = [];
            $params = [];

            if (isset($data['name'])) {
                $updates[] = "name = ?";
                $params[] = $data['name'];
            }

            if (isset($data['slug'])) {
                $slug = $this->makeSlugUnique($data['slug'], $id);
                $updates[] = "slug = ?";
                $params[] = $slug;
            } elseif (isset($data['name'])) {
                // Auto-generate slug from new name
                $slug = $this->generateSlug($data['name']);
                $slug = $this->makeSlugUnique($slug, $id);
                $updates[] = "slug = ?";
                $params[] = $slug;
            }

            if (isset($data['description'])) {
                $updates[] = "description = ?";
                $params[] = $data['description'];
            }

            if (empty($updates)) {
                $res->json(ResponseHelper::validationError(['data' => 'No fields to update']));
                return;
            }

            $params[] = $id;

            $sql = "UPDATE content_categories SET " . implode(", ", $updates) . " WHERE category_id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            // Get updated category
            $req->setParam('id', (string)$id);
            $this->show($req, $res);
        } catch (Exception $e) {
            error_log('Error updating category: ' . $e->getMessage());
            $res->status(200)->json([
                'success' => false,
                'message' => 'Failed to update category',
                'status_code' => 200,
                'data' => null
            ]);
            return;
        }
    }

    /**
     * DELETE /api/backend/v1/content/categories/{id} - Xóa category
     */
    public function destroy(Request $req, Response $res): void
    {
        try {
            $id = (int)$req->param('id');
            
            if (!$id) {
                $res->json(ResponseHelper::badRequest('Category ID is required'));
                return;
            }

            // Check if category exists
            $checkSql = "SELECT category_id FROM content_categories WHERE category_id = ?";
            $checkStmt = $this->pdo->prepare($checkSql);
            $checkStmt->execute([$id]);
            if (!$checkStmt->fetch()) {
                $res->json(ResponseHelper::notFound('Category not found'));
                return;
            }

            // Check if category has contents (cascade delete will handle relations)
            $countSql = "SELECT COUNT(*) as count FROM content_category_relations WHERE category_id = ?";
            $countStmt = $this->pdo->prepare($countSql);
            $countStmt->execute([$id]);
            $result = $countStmt->fetch(PDO::FETCH_ASSOC);
            $hasContents = (int)$result['count'] > 0;

            // Delete category (CASCADE will remove relations)
            $sql = "DELETE FROM content_categories WHERE category_id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$id]);

            $res->json(ResponseHelper::success([
                'deleted' => true,
                'had_contents' => $hasContents
            ], 'Category deleted successfully'));
            return;
        } catch (Exception $e) {
            error_log('Error deleting category: ' . $e->getMessage());
            $res->status(200)->json([
                'success' => false,
                'message' => 'Failed to delete category',
                'status_code' => 200,
                'data' => null
            ]);
            return;
        }
    }
}

