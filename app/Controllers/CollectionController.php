<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Controller, Request, Response, Container};
use App\Support\CloudinaryService;
use App\Support\ResponseHelper;
use PDO;
use Exception;

class CollectionController extends Controller
{
    private ?CloudinaryService $cloudinaryService = null;

    private function getCloudinaryService(): CloudinaryService
    {
        if ($this->cloudinaryService === null) {
            $this->cloudinaryService = new CloudinaryService();
        }
        return $this->cloudinaryService;
    }

    private function normalizeSlug(string $value): string
    {
        $value = trim(mb_strtolower($value));
        $value = preg_replace('/[^a-z0-9]+/u', '-', $value) ?? '';
        return trim($value, '-');
    }

    private function ensureUniqueSlug(PDO $pdo, string $slug, ?int $excludeId = null): string
    {
        $base = $slug ?: 'collection';
        $current = $base;
        $index = 1;

        while (true) {
            $sql = 'SELECT COUNT(*) AS total FROM collections WHERE slug = ?';
            $params = [$current];

            if ($excludeId) {
                $sql .= ' AND collection_id != ?';
                $params[] = $excludeId;
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ((int)($row['total'] ?? 0) === 0) return $current;

            $current = $base . '-' . $index++;
        }
    }

    private function loadCollectionWithDetails(PDO $pdo, int $collectionId): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM collections WHERE collection_id = ? LIMIT 1');
        $stmt->execute([$collectionId]);
        $collection = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$collection) return null;

        $imgStmt = $pdo->prepare('SELECT image_id, image_url, display_order, is_active, created_at FROM collection_images WHERE collection_id = ? ORDER BY display_order ASC, image_id ASC');
        $imgStmt->execute([$collectionId]);
        $collection['images'] = $imgStmt->fetchAll(PDO::FETCH_ASSOC);

        $productStmt = $pdo->prepare(
            "SELECT cp.id, cp.product_id, cp.display_order, p.product_name, p.slug
             FROM collection_products cp
             LEFT JOIN products p ON p.product_id = cp.product_id
             WHERE cp.collection_id = ?
             ORDER BY cp.display_order ASC, cp.id ASC"
        );
        $productStmt->execute([$collectionId]);
        $collection['products'] = $productStmt->fetchAll(PDO::FETCH_ASSOC);

        return $collection;
    }

    /**
     * GET /api/collections
     * List active collections ordered by display_order
     */
    public function index(Request $req, Response $res)
    {
        try {
            $pdo = $this->container->database()->getConnection();
            $sql = "SELECT collection_id, collection_name, slug, short_description
                    FROM collections
                    WHERE is_active = 1
                    ORDER BY display_order ASC, collection_id DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $res->json(ResponseHelper::success($rows));
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError($e->getMessage()), 500);
        }
    }

    /**
     * GET /api/collections/{slug}
     * Get collection by slug
     */
    public function showBySlug(Request $req, Response $res)
    {
        try {
            $slug = $req->getAttribute('slug');
            if (!$slug) {
                return $res->json(ResponseHelper::validationError(['slug' => 'Slug required']), 400);
            }
            $pdo = $this->container->database()->getConnection();
            $sql = "SELECT * FROM collections WHERE slug = ? AND is_active = 1 LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$slug]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return $res->json(ResponseHelper::notFound('Collection not found'), 404);
            }
            return $res->json(ResponseHelper::success($row));
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError($e->getMessage()), 500);
        }
    }

    /**
     * GET /api/collections/{collection_id}/images
     * Get images for a collection
     */
    public function getImages(Request $req, Response $res)
    {
        try {
            $collectionId = (int) $req->getAttribute('collection_id');
            if ($collectionId <= 0) {
                return $res->json(ResponseHelper::validationError(['collection_id' => 'Invalid collection_id']), 400);
            }
            $pdo = $this->container->database()->getConnection();
            $sql = "SELECT image_url FROM collection_images
                    WHERE collection_id = ? AND is_active = 1
                    ORDER BY display_order ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$collectionId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $res->json(ResponseHelper::success($rows));
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError($e->getMessage()), 500);
        }
    }

    /**
     * GET /api/backend/v1/collections
     * List all collections for admin.
     */
    public function adminIndex(Request $req, Response $res)
    {
        try {
            $pdo = $this->container->database()->getConnection();
            $sql = "SELECT
                        c.collection_id,
                        c.collection_name,
                        c.slug,
                        c.short_description,
                        c.display_order,
                        c.is_active,
                        c.created_at,
                        c.updated_at,
                        (SELECT COUNT(*) FROM collection_images ci WHERE ci.collection_id = c.collection_id AND ci.is_active = 1) AS images_count,
                        (SELECT COUNT(*) FROM collection_products cp WHERE cp.collection_id = c.collection_id) AS products_count
                    FROM collections c
                    ORDER BY c.display_order ASC, c.collection_id DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $res->json(ResponseHelper::success($rows, 'Collections retrieved successfully'));
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError($e->getMessage()), 500);
        }
    }

    /**
     * GET /api/backend/v1/collections/{id}
     * Get full collection details for admin.
     */
    public function adminShow(Request $req, Response $res)
    {
        try {
            $collectionId = (int)($req->param('id') ?? 0);
            if ($collectionId <= 0) {
                return $res->json(ResponseHelper::validationError(['id' => 'Invalid id']), 400);
            }

            $pdo = $this->container->database()->getConnection();
            $collection = $this->loadCollectionWithDetails($pdo, $collectionId);
            if (!$collection) {
                return $res->json(ResponseHelper::notFound('Collection not found'), 404);
            }

            return $res->json(ResponseHelper::success($collection, 'Collection retrieved successfully'));
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError($e->getMessage()), 500);
        }
    }

    /**
     * POST /api/backend/v1/collections
     * Create collection for admin.
     */
    public function adminStore(Request $req, Response $res)
    {
        try {
            $payload = $req->json();
            $name = trim((string)($payload['collection_name'] ?? ''));
            if ($name === '') {
                return $res->json(ResponseHelper::validationError(['collection_name' => 'Collection name is required']), 400);
            }

            $pdo = $this->container->database()->getConnection();
            $slugInput = trim((string)($payload['slug'] ?? ''));
            $slug = $this->normalizeSlug($slugInput !== '' ? $slugInput : $name);
            $slug = $this->ensureUniqueSlug($pdo, $slug);

            $stmt = $pdo->prepare(
                'INSERT INTO collections (collection_name, slug, short_description, display_order, is_active, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, NOW(), NOW())'
            );
            $nextDisplayOrder = (int)$pdo->query('SELECT COALESCE(MAX(display_order), 0) + 1 AS next_display_order FROM collections')->fetch(PDO::FETCH_ASSOC)['next_display_order'];
            $stmt->execute([
                $name,
                $slug,
                trim((string)($payload['short_description'] ?? '')) ?: null,
                (int)($payload['display_order'] ?? $nextDisplayOrder),
                (int)($payload['is_active'] ?? 1) ? 1 : 0,
            ]);

            $collectionId = (int)$pdo->lastInsertId();
            $this->syncImages($pdo, $collectionId, $payload['images'] ?? []);

            $collection = $this->loadCollectionWithDetails($pdo, $collectionId);
            return $res->json(ResponseHelper::success($collection, 'Collection created successfully'));
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError($e->getMessage()), 500);
        }
    }

    /**
     * PUT /api/backend/v1/collections/{id}
     * Update collection and nested resources.
     */
    public function adminUpdate(Request $req, Response $res)
    {
        try {
            $collectionId = (int)($req->param('id') ?? 0);
            if ($collectionId <= 0) {
                return $res->json(ResponseHelper::validationError(['id' => 'Invalid id']), 400);
            }

            $payload = $req->json();
            $pdo = $this->container->database()->getConnection();

            $existing = $this->loadCollectionWithDetails($pdo, $collectionId);
            if (!$existing) {
                return $res->json(ResponseHelper::notFound('Collection not found'), 404);
            }

            $name = trim((string)($payload['collection_name'] ?? $existing['collection_name']));
            if ($name === '') {
                return $res->json(ResponseHelper::validationError(['collection_name' => 'Collection name is required']), 400);
            }

            $slugInput = trim((string)($payload['slug'] ?? $existing['slug']));
            $slug = $this->normalizeSlug($slugInput !== '' ? $slugInput : $name);
            $slug = $this->ensureUniqueSlug($pdo, $slug, $collectionId);

            $stmt = $pdo->prepare(
                'UPDATE collections
                 SET collection_name = ?, slug = ?, short_description = ?, is_active = ?, updated_at = NOW()
                 WHERE collection_id = ?'
            );
            $stmt->execute([
                $name,
                $slug,
                trim((string)($payload['short_description'] ?? $existing['short_description'] ?? '')) ?: null,
                (int)($payload['is_active'] ?? $existing['is_active']) ? 1 : 0,
                $collectionId,
            ]);

            if (array_key_exists('images', $payload)) {
                $this->syncImages($pdo, $collectionId, $payload['images']);
            }

            $collection = $this->loadCollectionWithDetails($pdo, $collectionId);
            return $res->json(ResponseHelper::success($collection, 'Collection updated successfully'));
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError($e->getMessage()), 500);
        }
    }

    /**
     * DELETE /api/backend/v1/collections/{id}
     */
    public function adminDestroy(Request $req, Response $res)
    {
        try {
            $collectionId = (int)($req->param('id') ?? 0);
            if ($collectionId <= 0) {
                return $res->json(ResponseHelper::validationError(['id' => 'Invalid id']), 400);
            }

            $pdo = $this->container->database()->getConnection();
            $existsStmt = $pdo->prepare('SELECT collection_id FROM collections WHERE collection_id = ? LIMIT 1');
            $existsStmt->execute([$collectionId]);
            if (!$existsStmt->fetch(PDO::FETCH_ASSOC)) {
                return $res->json(ResponseHelper::notFound('Collection not found'), 404);
            }

            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM collection_images WHERE collection_id = ?')->execute([$collectionId]);
            $pdo->prepare('DELETE FROM collection_products WHERE collection_id = ?')->execute([$collectionId]);
            $pdo->prepare('DELETE FROM collections WHERE collection_id = ?')->execute([$collectionId]);
            $pdo->commit();

            return $res->json(ResponseHelper::success(['deleted' => true], 'Collection deleted successfully'));
        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return $res->json(ResponseHelper::serverError($e->getMessage()), 500);
        }
    }

    /**
     * PUT /api/backend/v1/collections/reorder
     * Reorder collections by drag-and-drop list.
     */
    public function adminReorder(Request $req, Response $res)
    {
        try {
            $payload = $req->json();
            $items = $payload['items'] ?? null;

            if (!is_array($items) || count($items) === 0) {
                return $res->json(ResponseHelper::validationError(['items' => 'Items array is required']), 400);
            }

            $pdo = $this->container->database()->getConnection();
            $ids = [];
            foreach ($items as $item) {
                if (!is_array($item)) {
                    return $res->json(ResponseHelper::validationError(['items' => 'Invalid reorder payload']), 400);
                }
                $id = (int)($item['collection_id'] ?? 0);
                if ($id <= 0) {
                    return $res->json(ResponseHelper::validationError(['collection_id' => 'Invalid collection_id']), 400);
                }
                if (in_array($id, $ids, true)) {
                    return $res->json(ResponseHelper::validationError(['items' => 'Duplicate collection_id detected']), 400);
                }
                $ids[] = $id;
            }

            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $checkStmt = $pdo->prepare("SELECT collection_id FROM collections WHERE collection_id IN ($placeholders)");
            $checkStmt->execute($ids);
            $existingIds = array_map('intval', array_column($checkStmt->fetchAll(PDO::FETCH_ASSOC), 'collection_id'));
            sort($existingIds);
            $sortedIds = $ids;
            sort($sortedIds);
            if ($existingIds !== $sortedIds) {
                return $res->json(ResponseHelper::validationError(['items' => 'Some collections do not exist']), 400);
            }

            $pdo->beginTransaction();
            $updateStmt = $pdo->prepare('UPDATE collections SET display_order = ?, updated_at = NOW() WHERE collection_id = ?');
            foreach ($ids as $index => $id) {
                $updateStmt->execute([$index + 1, $id]);
            }
            $pdo->commit();

            return $res->json(ResponseHelper::success(['updated' => true], 'Collections reordered successfully'));
        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return $res->json(ResponseHelper::serverError($e->getMessage()), 500);
        }
    }

    private function syncImages(PDO $pdo, int $collectionId, mixed $images): void
    {
        if (!is_array($images)) {
            return;
        }

        $pdo->prepare('DELETE FROM collection_images WHERE collection_id = ?')->execute([$collectionId]);

        $insert = $pdo->prepare(
            'INSERT INTO collection_images (collection_id, image_url, display_order, is_active, created_at)
             VALUES (?, ?, ?, ?, NOW())'
        );

        foreach ($images as $index => $image) {
            if (!is_array($image)) continue;
            $url = trim((string)($image['image_url'] ?? ''));
            $base64File = trim((string)($image['file'] ?? ''));

            if ($url === '' && $base64File !== '') {
                $uploadResult = $this->getCloudinaryService()->uploadBase64Image($base64File, 'shopswift/collections');
                if (!($uploadResult['success'] ?? false) || empty($uploadResult['url'])) {
                    throw new Exception($uploadResult['error'] ?? 'Collection image upload failed');
                }
                $url = (string)$uploadResult['url'];
            }

            if ($url === '') continue;

            $insert->execute([
                $collectionId,
                $url,
                (int)($image['display_order'] ?? ($index + 1)),
                (int)($image['is_active'] ?? 1) ? 1 : 0,
            ]);
        }
    }

    private function syncProducts(PDO $pdo, int $collectionId, mixed $products): void
    {
        if (!is_array($products)) {
            return;
        }

        $pdo->prepare('DELETE FROM collection_products WHERE collection_id = ?')->execute([$collectionId]);

        $insert = $pdo->prepare(
            'INSERT INTO collection_products (collection_id, product_id, display_order, created_at)
             VALUES (?, ?, ?, NOW())'
        );

        foreach ($products as $index => $item) {
            $productId = 0;
            $displayOrder = $index + 1;

            if (is_array($item)) {
                $productId = (int)($item['product_id'] ?? 0);
                $displayOrder = (int)($item['display_order'] ?? $displayOrder);
            } else {
                $productId = (int)$item;
            }

            if ($productId <= 0) continue;
            $insert->execute([$collectionId, $productId, $displayOrder]);
        }
    }
}
