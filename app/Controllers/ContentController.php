<?php
declare(strict_types=1);

/**
 * ContentController
 * 
 * Controller quản lý Content Management System (CMS).
 * Xử lý CRUD operations cho Pages, Blog Posts, FAQs, Policies, Editorial content.
 * 
 * Chức năng:
 * - List contents với filters (type, status, search, pagination)
 * - Chi tiết content (by ID hoặc slug)
 * - Tạo/sửa/xóa content
 * - Publish content
 * - Stats cho dashboard
 * - Auto-publish scheduled content
 * 
 * @package App\Controllers
 * @author ShopSwift Team
 */

namespace App\Controllers;

use App\Core\{Controller, Request, Response, Container};
use App\Support\ResponseHelper;
use PDO;
use Exception;

class ContentController extends Controller
{
    private PDO $pdo;

    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->pdo = $container->database()->getConnection();
    }

    /**
     * Helper: Generate slug from title
     */
    private function generateSlug(string $title): string
    {
        // Remove Vietnamese diacritics
        $title = $this->removeVietnameseDiacritics($title);
        
        // Convert to lowercase
        $title = strtolower($title);
        
        // Replace spaces and special chars with hyphens
        $title = preg_replace('/[^a-z0-9]+/', '-', $title);
        
        // Remove leading/trailing hyphens
        $title = trim($title, '-');
        
        return $title;
    }

    /**
     * Helper: Remove Vietnamese diacritics
     */
    private function removeVietnameseDiacritics(string $text): string
    {
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
        
        return str_replace($vietnamese, $english, $text);
    }

    /**
     * Helper: Make slug unique
     */
    private function makeSlugUnique(string $slug, ?int $excludeId = null): string
    {
        $baseSlug = $slug;
        $counter = 1;
        
        while (true) {
            $sql = "SELECT COUNT(*) as count FROM contents WHERE slug = ?";
            $params = [$slug];
            
            if ($excludeId) {
                $sql .= " AND content_id != ?";
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
     * Auto-publish scheduled content
     */
    private function autoPublishScheduled(): void
    {
        try {
            $sql = "UPDATE contents 
                    SET status = 'published' 
                    WHERE status = 'scheduled' 
                    AND publish_at IS NOT NULL 
                    AND publish_at <= NOW()";
            $this->pdo->exec($sql);
        } catch (Exception $e) {
            error_log('Auto-publish error: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/backend/v1/content - List contents
     * 
     * Query params:
     * - id: Chi tiết 1 content (show method)
     * - slug: Lấy content theo slug
     * - content_type: Filter by type (page, blog, faq, policy, editorial)
     * - status: Filter by status (draft, published, archived, scheduled)
     * - search: Search in title and content
     * - page, limit: Pagination
     */
    public function index(Request $req, Response $res): void
    {
        try {
            // Auto-publish scheduled content
            $this->autoPublishScheduled();

            // Nếu có id query param, trả về chi tiết
            $id = $req->query('id');
            if ($id) {
                $this->show($req, $res);
                return;
            }

            // Nếu có slug query param, trả về chi tiết theo slug
            $slug = $req->query('slug');
            if ($slug) {
                $this->getBySlug($req, $res);
                return;
            }

            // Pagination
            $page = max(1, (int)($req->query('page') ?? 1));
            $limit = max(1, min(100, (int)($req->query('limit') ?? 20)));
            $offset = ($page - 1) * $limit;

            // Filters
            $contentType = $req->query('content_type');
            $status = $req->query('status');
            $search = $req->query('search');
            $includeArchived = $req->query('include_archived') === 'true' || $req->query('include_archived') === '1';

            // Build query
            $where = [];
            $params = [];

            // Mặc định loại bỏ archived content (soft deleted)
            // Chỉ hiển thị archived nếu:
            // 1. User explicitly request (include_archived=true)
            // 2. User filter status cụ thể là 'archived'
            $isFilteringArchived = ($status === 'archived');
            
            if (!$includeArchived && !$isFilteringArchived) {
                $where[] = "c.status != 'archived'";
            }

            if ($contentType && in_array($contentType, ['page', 'blog', 'faq', 'policy', 'editorial'])) {
                $where[] = "c.content_type = ?";
                $params[] = $contentType;
            }

            if ($status && in_array($status, ['draft', 'published', 'archived', 'scheduled'])) {
                $where[] = "c.status = ?";
                $params[] = $status;
            }

            if ($search) {
                $where[] = "(c.title LIKE ? OR c.content LIKE ? OR c.excerpt LIKE ?)";
                $searchTerm = '%' . $search . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }

            $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

            // Get total count
            $countSql = "SELECT COUNT(*) as total FROM contents c {$whereClause}";
            $countStmt = $this->pdo->prepare($countSql);
            $countStmt->execute($params);
            $total = (int)$countStmt->fetch(PDO::FETCH_ASSOC)['total'];

            // Get contents with author info
            $sql = "SELECT 
                        c.content_id,
                        c.title,
                        c.slug,
                        c.content_type,
                        c.content,
                        c.excerpt,
                        c.featured_image,
                        c.status,
                        c.publish_at,
                        c.display_start,
                        c.display_end,
                        c.meta_title,
                        c.meta_description,
                        c.meta_keywords,
                        c.view_count,
                        c.created_at,
                        c.updated_at,
                        u.user_id as author_id,
                        CONCAT(u.first_name, ' ', u.last_name) as author_name
                    FROM contents c
                    LEFT JOIN users u ON c.author_id = u.user_id
                    {$whereClause}
                    ORDER BY c.created_at DESC
                    LIMIT ? OFFSET ?";
            
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $contents = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get categories for each content
            foreach ($contents as &$content) {
                $contentId = (int)$content['content_id'];
                $catSql = "SELECT 
                            cc.category_id,
                            cc.name as category_name,
                            cc.slug as category_slug
                          FROM content_category_relations ccr
                          JOIN content_categories cc ON ccr.category_id = cc.category_id
                          WHERE ccr.content_id = ?";
                $catStmt = $this->pdo->prepare($catSql);
                $catStmt->execute([$contentId]);
                $content['categories'] = $catStmt->fetchAll(PDO::FETCH_ASSOC);
            }

            $res->json(ResponseHelper::paginated($contents, $total, $limit, $page));
        } catch (Exception $e) {
            error_log('Error fetching contents: ' . $e->getMessage());
            $res->status(200)->json([
                'success' => false,
                'message' => 'Failed to fetch contents',
                'status_code' => 200,
                'data' => []
            ]);
            return;
        }
    }

    /**
     * GET /api/backend/v1/content/{id} - Chi tiết content
     */
    public function show(Request $req, Response $res): void
    {
        try {
            $id = (int)$req->param('id');
            
            if (!$id) {
                $res->json(ResponseHelper::badRequest('Content ID is required'));
                return;
            }

            // Get content with author
            $sql = "SELECT 
                        c.*,
                        u.user_id as author_id,
                        CONCAT(u.first_name, ' ', u.last_name) as author_name
                    FROM contents c
                    LEFT JOIN users u ON c.author_id = u.user_id
                    WHERE c.content_id = ?";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$id]);
            $content = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$content) {
                $res->json(ResponseHelper::notFound('Content not found'));
                return;
            }

            // Get categories
            $catSql = "SELECT 
                        cc.category_id,
                        cc.name as category_name,
                        cc.slug as category_slug
                      FROM content_category_relations ccr
                      JOIN content_categories cc ON ccr.category_id = cc.category_id
                      WHERE ccr.content_id = ?";
            $catStmt = $this->pdo->prepare($catSql);
            $catStmt->execute([$id]);
            $content['categories'] = $catStmt->fetchAll(PDO::FETCH_ASSOC);

            // Increment view count
            $updateSql = "UPDATE contents SET view_count = view_count + 1 WHERE content_id = ?";
            $updateStmt = $this->pdo->prepare($updateSql);
            $updateStmt->execute([$id]);

            $res->json(ResponseHelper::success($content, 'Content retrieved successfully'));
            return;
        } catch (Exception $e) {
            error_log('Error fetching content: ' . $e->getMessage());
            $res->status(200)->json([
                'success' => false,
                'message' => 'Failed to fetch content',
                'status_code' => 200,
                'data' => null
            ]);
            return;
        }
    }

    /**
     * GET /api/backend/v1/content/slug/{slug} - Lấy content theo slug (cho frontend)
     */
    public function getBySlug(Request $req, Response $res): void
    {
        try {
            $slug = $req->param('slug');
            
            if (!$slug) {
                $res->json(ResponseHelper::badRequest('Slug is required'));
                return;
            }

            // Auto-publish scheduled content
            $this->autoPublishScheduled();

            // Get content with author
            $sql = "SELECT 
                        c.*,
                        u.user_id as author_id,
                        CONCAT(u.first_name, ' ', u.last_name) as author_name
                    FROM contents c
                    LEFT JOIN users u ON c.author_id = u.user_id
                    WHERE c.slug = ? AND c.status = 'published'";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$slug]);
            $content = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$content) {
                $res->json(ResponseHelper::notFound('Content not found'));
                return;
            }

            // Check display date range
            $now = date('Y-m-d H:i:s');
            if ($content['display_start'] && $content['display_start'] > $now) {
                $res->json(ResponseHelper::notFound('Content not yet published'));
                return;
            }
            if ($content['display_end'] && $content['display_end'] < $now) {
                $res->json(ResponseHelper::notFound('Content has expired'));
                return;
            }

            // Get categories
            $catSql = "SELECT 
                        cc.category_id,
                        cc.name as category_name,
                        cc.slug as category_slug
                      FROM content_category_relations ccr
                      JOIN content_categories cc ON ccr.category_id = cc.category_id
                      WHERE ccr.content_id = ?";
            $catStmt = $this->pdo->prepare($catSql);
            $catStmt->execute([(int)$content['content_id']]);
            $content['categories'] = $catStmt->fetchAll(PDO::FETCH_ASSOC);

            // Increment view count
            $updateSql = "UPDATE contents SET view_count = view_count + 1 WHERE content_id = ?";
            $updateStmt = $this->pdo->prepare($updateSql);
            $updateStmt->execute([(int)$content['content_id']]);

            $res->json(ResponseHelper::success($content, 'Content retrieved successfully'));
            return;
        } catch (Exception $e) {
            error_log('Error fetching content by slug: ' . $e->getMessage());
            $res->status(200)->json([
                'success' => false,
                'message' => 'Failed to fetch content',
                'status_code' => 200,
                'data' => null
            ]);
            return;
        }
    }

    /**
     * POST /api/backend/v1/content - Tạo content mới
     */
    public function store(Request $req, Response $res): void
    {
        try {
            $data = $req->json();

            // Validation
            $errors = [];
            if (empty($data['title']) || trim($data['title']) === '') {
                $errors['title'] = 'Title is required';
            }
            if (empty($data['content_type']) || !in_array($data['content_type'], ['page', 'blog', 'faq', 'policy', 'editorial'])) {
                $errors['content_type'] = 'Valid content type is required';
            }
            // Check content: strip HTML tags and check if there's actual text content
            $contentText = isset($data['content']) ? trim(strip_tags($data['content'])) : '';
            if (empty($contentText)) {
                $errors['content'] = 'Content is required';
            }

            if (!empty($errors)) {
                $res->json(ResponseHelper::validationError($errors));
                return;
            }

            // Generate slug if not provided
            $slug = !empty($data['slug']) ? $data['slug'] : $this->generateSlug($data['title']);
            $slug = $this->makeSlugUnique($slug);

            // Determine status
            $status = $data['status'] ?? 'draft';
            if (isset($data['publish_at']) && $data['publish_at']) {
                $publishAt = date('Y-m-d H:i:s', strtotime($data['publish_at']));
                if ($publishAt > date('Y-m-d H:i:s')) {
                    $status = 'scheduled';
                } else {
                    $status = 'published';
                    $publishAt = date('Y-m-d H:i:s');
                }
            } else {
                $publishAt = $status === 'published' ? date('Y-m-d H:i:s') : null;
            }

            // Insert content
            $sql = "INSERT INTO contents (
                        title, slug, content_type, content, excerpt,
                        featured_image, status, publish_at,
                        display_start, display_end,
                        meta_title, meta_description, meta_keywords,
                        author_id
                    ) VALUES (
                        ?, ?, ?, ?, ?,
                        ?, ?, ?,
                        ?, ?,
                        ?, ?, ?,
                        ?
                    )";
            
            $params = [
                $data['title'],
                $slug,
                $data['content_type'],
                $data['content'],
                $data['excerpt'] ?? null,
                $data['featured_image'] ?? null,
                $status,
                $publishAt,
                !empty($data['display_start']) ? date('Y-m-d H:i:s', strtotime($data['display_start'])) : null,
                !empty($data['display_end']) ? date('Y-m-d H:i:s', strtotime($data['display_end'])) : null,
                $data['meta_title'] ?? null,
                $data['meta_description'] ?? null,
                $data['meta_keywords'] ?? null,
                !empty($data['author_id']) ? (int)$data['author_id'] : null,
            ];

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $contentId = (int)$this->pdo->lastInsertId();

            // Insert categories (many-to-many)
            if (!empty($data['category_ids']) && is_array($data['category_ids'])) {
                $catSql = "INSERT INTO content_category_relations (content_id, category_id) VALUES (?, ?)";
                $catStmt = $this->pdo->prepare($catSql);
                foreach ($data['category_ids'] as $categoryId) {
                    $catStmt->execute([$contentId, (int)$categoryId]);
                }
            }

            // Get created content
            $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            $req->setParam('id', (string)$contentId);
            $this->show($req, $res);
        } catch (Exception $e) {
            error_log('Error creating content: ' . $e->getMessage());
            $res->status(200)->json([
                'success' => false,
                'message' => 'Failed to create content',
                'status_code' => 200,
                'data' => null
            ]);
            return;
        }
    }

    /**
     * PUT /api/backend/v1/content/{id} - Cập nhật content
     */
    public function update(Request $req, Response $res): void
    {
        try {
            $id = (int)$req->param('id');
            
            if (!$id) {
                $res->json(ResponseHelper::badRequest('Content ID is required'));
                return;
            }

            // Check if content exists
            $checkSql = "SELECT content_id FROM contents WHERE content_id = ?";
            $checkStmt = $this->pdo->prepare($checkSql);
            $checkStmt->execute([$id]);
            if (!$checkStmt->fetch()) {
                $res->json(ResponseHelper::notFound('Content not found'));
                return;
            }

            $data = $req->json();

            // Validation
            if (isset($data['content_type']) && !in_array($data['content_type'], ['page', 'blog', 'faq', 'policy', 'editorial'])) {
                $res->json(ResponseHelper::validationError(['content_type' => 'Invalid content type']));
                return;
            }

            // Build update query
            $updates = [];
            $params = [];

            if (isset($data['title'])) {
                $updates[] = "title = ?";
                $params[] = $data['title'];
            }

            if (isset($data['slug'])) {
                $slug = $this->makeSlugUnique($data['slug'], $id);
                $updates[] = "slug = ?";
                $params[] = $slug;
            }

            if (isset($data['content_type'])) {
                $updates[] = "content_type = ?";
                $params[] = $data['content_type'];
            }

            if (isset($data['content'])) {
                $updates[] = "content = ?";
                $params[] = $data['content'];
            }

            if (isset($data['excerpt'])) {
                $updates[] = "excerpt = ?";
                $params[] = $data['excerpt'];
            }

            if (isset($data['featured_image'])) {
                $updates[] = "featured_image = ?";
                $params[] = $data['featured_image'];
            }

            if (isset($data['status'])) {
                $updates[] = "status = ?";
                $params[] = $data['status'];
            }

            if (isset($data['publish_at'])) {
                if ($data['publish_at']) {
                    $publishAt = date('Y-m-d H:i:s', strtotime($data['publish_at']));
                    $updates[] = "publish_at = ?";
                    $params[] = $publishAt;
                    
                    // Auto-update status if publish_at is in the future
                    if ($publishAt > date('Y-m-d H:i:s')) {
                        $updates[] = "status = 'scheduled'";
                    } else {
                        $updates[] = "status = 'published'";
                    }
                } else {
                    $updates[] = "publish_at = NULL";
                }
            }

            if (isset($data['display_start'])) {
                $updates[] = "display_start = ?";
                $params[] = !empty($data['display_start']) ? date('Y-m-d H:i:s', strtotime($data['display_start'])) : null;
            }

            if (isset($data['display_end'])) {
                $updates[] = "display_end = ?";
                $params[] = !empty($data['display_end']) ? date('Y-m-d H:i:s', strtotime($data['display_end'])) : null;
            }

            if (isset($data['meta_title'])) {
                $updates[] = "meta_title = ?";
                $params[] = $data['meta_title'];
            }

            if (isset($data['meta_description'])) {
                $updates[] = "meta_description = ?";
                $params[] = $data['meta_description'];
            }

            if (isset($data['meta_keywords'])) {
                $updates[] = "meta_keywords = ?";
                $params[] = $data['meta_keywords'];
            }

            if (isset($data['author_id'])) {
                $updates[] = "author_id = ?";
                $params[] = !empty($data['author_id']) ? (int)$data['author_id'] : null;
            }

            if (empty($updates)) {
                $res->json(ResponseHelper::validationError(['data' => 'No fields to update']));
                return;
            }

            $updates[] = "updated_at = NOW()";
            $params[] = $id;

            $sql = "UPDATE contents SET " . implode(", ", $updates) . " WHERE content_id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            // Update categories if provided
            if (isset($data['category_ids']) && is_array($data['category_ids'])) {
                // Delete existing relations
                $deleteSql = "DELETE FROM content_category_relations WHERE content_id = ?";
                $deleteStmt = $this->pdo->prepare($deleteSql);
                $deleteStmt->execute([$id]);

                // Insert new relations
                $catSql = "INSERT INTO content_category_relations (content_id, category_id) VALUES (?, ?)";
                $catStmt = $this->pdo->prepare($catSql);
                foreach ($data['category_ids'] as $categoryId) {
                    $catStmt->execute([$id, (int)$categoryId]);
                }
            }

            // Get updated content
            $req->setParam('id', (string)$id);
            $this->show($req, $res);
        } catch (Exception $e) {
            error_log('Error updating content: ' . $e->getMessage());
            $res->status(200)->json([
                'success' => false,
                'message' => 'Failed to update content',
                'status_code' => 200,
                'data' => null
            ]);
            return;
        }
    }

    /**
     * DELETE /api/backend/v1/content/{id} - Xóa content (hard delete - xóa thật record)
     */
    public function destroy(Request $req, Response $res): void
    {
        try {
            // Debug: Log request info
            error_log('[DELETE Backend] Request path: ' . $req->path());
            error_log('[DELETE Backend] Request method: ' . $req->method());
            error_log('[DELETE Backend] Param ID: ' . $req->param('id'));
            error_log('[DELETE Backend] Query ID: ' . $req->query('id'));
            
            $id = (int)$req->param('id');
            error_log('[DELETE Backend] Extracted ID: ' . $id);
            
            if (!$id) {
                error_log('[DELETE Backend] ERROR: No ID provided');
                $errorResponse = ResponseHelper::error('Content ID is required', 400);
                error_log('[DELETE Backend] Error response: ' . json_encode($errorResponse));
                $res->json($errorResponse);
                return;
            }

            // Check if content exists
            $checkSql = "SELECT content_id FROM contents WHERE content_id = ?";
            $checkStmt = $this->pdo->prepare($checkSql);
            $checkStmt->execute([$id]);
            $exists = $checkStmt->fetch();
            error_log('[DELETE Backend] Content exists: ' . ($exists ? 'YES (ID: ' . $id . ')' : 'NO (ID: ' . $id . ')'));
            
            if (!$exists) {
                error_log('[DELETE Backend] ERROR: Content not found');
                $notFoundResponse = ResponseHelper::notFound('Content not found');
                error_log('[DELETE Backend] Not found response: ' . json_encode($notFoundResponse));
                $res->json($notFoundResponse);
                return;
            }

            // Hard delete: Xóa thật record khỏi database
            // Xóa content_category_relations trước (foreign key constraint - ON DELETE CASCADE nên có thể không cần)
            $deleteCatSql = "DELETE FROM content_category_relations WHERE content_id = ?";
            $deleteCatStmt = $this->pdo->prepare($deleteCatSql);
            $deleteCatResult = $deleteCatStmt->execute([$id]);
            $catRowsAffected = $deleteCatStmt->rowCount();
            error_log('[DELETE Backend] Delete categories relations: ' . ($deleteCatResult ? 'SUCCESS' : 'FAILED') . ', Rows: ' . $catRowsAffected);
            
            // Xóa content record
            $deleteSql = "DELETE FROM contents WHERE content_id = ?";
            $deleteStmt = $this->pdo->prepare($deleteSql);
            $executeResult = $deleteStmt->execute([$id]);
            $rowsAffected = $deleteStmt->rowCount();
            error_log('[DELETE Backend] Delete result: ' . ($executeResult ? 'SUCCESS' : 'FAILED'));
            error_log('[DELETE Backend] Rows affected: ' . $rowsAffected);

            if (!$executeResult || $rowsAffected === 0) {
                error_log('[DELETE Backend] ERROR: Delete failed or no rows affected');
                $errorResponse = ResponseHelper::error('Failed to delete content', 500);
                error_log('[DELETE Backend] Error response: ' . json_encode($errorResponse));
                $res->json($errorResponse);
                return;
            }

            // Return success response
            $successResponse = ResponseHelper::success(null, 'Content deleted successfully');
            error_log('[DELETE Backend] Success response: ' . json_encode($successResponse));
            $res->json($successResponse);
            return;
        } catch (Exception $e) {
            error_log('[DELETE Backend] EXCEPTION: ' . $e->getMessage());
            error_log('[DELETE Backend] Stack trace: ' . $e->getTraceAsString());
            $errorResponse = [
                'success' => false,
                'message' => 'Failed to delete content: ' . $e->getMessage(),
                'status_code' => 200,
                'data' => null
            ];
            error_log('[DELETE Backend] Exception response: ' . json_encode($errorResponse));
            $res->status(200)->json($errorResponse);
            return;
        }
    }

    /**
     * POST /api/backend/v1/content/{id}/publish - Publish content
     */
    public function publish(Request $req, Response $res): void
    {
        try {
            $id = (int)$req->param('id');
            
            if (!$id) {
                $res->json(ResponseHelper::badRequest('Content ID is required'));
                return;
            }

            // Check if content exists
            $checkSql = "SELECT content_id FROM contents WHERE content_id = ?";
            $checkStmt = $this->pdo->prepare($checkSql);
            $checkStmt->execute([$id]);
            if (!$checkStmt->fetch()) {
                $res->json(ResponseHelper::notFound('Content not found'));
                return;
            }

            // Update status to published
            $sql = "UPDATE contents SET status = 'published', publish_at = NOW(), updated_at = NOW() WHERE content_id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$id]);

            // Get updated content
            $req->setParam('id', (string)$id);
            $this->show($req, $res);
        } catch (Exception $e) {
            error_log('Error publishing content: ' . $e->getMessage());
            $res->status(200)->json([
                'success' => false,
                'message' => 'Failed to publish content',
                'status_code' => 200,
                'data' => null
            ]);
            return;
        }
    }

    /**
     * GET /api/backend/v1/content/stats - Stats cho dashboard
     */
    public function getStats(Request $req, Response $res): void
    {
        try {
            // Auto-publish scheduled content
            $this->autoPublishScheduled();

            // Get stats
            $sql = "SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN content_type = 'page' THEN 1 ELSE 0 END) as pages,
                        SUM(CASE WHEN content_type = 'blog' THEN 1 ELSE 0 END) as blogs,
                        SUM(CASE WHEN content_type = 'faq' THEN 1 ELSE 0 END) as faqs,
                        SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) as published
                    FROM contents
                    WHERE status != 'archived'";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);

            $response = [
                'total' => (int)($stats['total'] ?? 0),
                'pages' => (int)($stats['pages'] ?? 0),
                'blogs' => (int)($stats['blogs'] ?? 0),
                'faqs' => (int)($stats['faqs'] ?? 0),
                'published' => (int)($stats['published'] ?? 0)
            ];

            $res->json(ResponseHelper::success($response, 'Stats retrieved successfully'));
            return;
        } catch (Exception $e) {
            error_log('Error fetching stats: ' . $e->getMessage());
            $res->status(200)->json([
                'success' => false,
                'message' => 'Failed to fetch stats',
                'status_code' => 200,
                'data' => [
                    'total' => 0,
                    'pages' => 0,
                    'blogs' => 0,
                    'faqs' => 0,
                    'published' => 0
                ]
            ]);
            return;
        }
    }
}

