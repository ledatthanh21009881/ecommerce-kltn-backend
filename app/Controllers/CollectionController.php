<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Controller, Request, Response, Container};
use App\Support\ResponseHelper;
use PDO;
use Exception;

class CollectionController extends Controller
{
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
                    ORDER BY display_order ASC";
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
}
