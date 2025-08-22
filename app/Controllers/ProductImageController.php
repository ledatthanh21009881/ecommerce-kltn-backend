<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Controller, Request, Response, Container};
use App\Support\ResponseHelper;
use App\Core\Validator;
use Exception;

class ProductImageController extends Controller 
{
    public function __construct(Container $container)
    {
        parent::__construct($container);
    }
    
    /**
     * Add images to product
     */
    public function addImages(Request $req, Response $res)
    {
        $user = $req->getAttribute('user');
        
        // Check if user is admin
        if (!in_array('admin', $user['roles'] ?? [])) {
            return $res->json(ResponseHelper::forbidden('Admin access required'));
        }
        
        try {
            $productId = $req->getAttribute('id') ?? $req->query('product_id');
            if (!$productId) {
                return $res->json(ResponseHelper::error('Product ID is required'));
            }
            
            // Check if product exists
            $pdo = $this->container->database()->getConnection();
            $stmt = $pdo->prepare('SELECT product_id FROM products WHERE product_id = ?');
            $stmt->execute([$productId]);
            if (!$stmt->fetch()) {
                return $res->json(ResponseHelper::notFound('Product not found'));
            }
            
            $data = $req->json();
            
            // Basic validation
            if (!isset($data['images']) || !is_array($data['images']) || empty($data['images'])) {
                return $res->json(ResponseHelper::error('Images array is required and cannot be empty'));
            }
            
            // Validate each image
            foreach ($data['images'] as $index => $imageData) {
                if (!isset($imageData['url']) || empty($imageData['url'])) {
                    return $res->json(ResponseHelper::error("Image URL is required for image at index $index"));
                }
                
                if (!filter_var($imageData['url'], FILTER_VALIDATE_URL)) {
                    return $res->json(ResponseHelper::error("Invalid URL format for image at index $index"));
                }
            }
            
            $images = $data['images'];
            $addedImages = [];
            
            foreach ($images as $imageData) {
                $sql = "INSERT INTO product_images (
                            product_id, url, position, image_type, is_main
                        ) VALUES (?, ?, ?, ?, ?)";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $productId,
                    $imageData['url'],
                    $imageData['position'] ?? 0,
                    $imageData['image_type'] ?? 'product',
                    $imageData['is_main'] ?? false
                ]);
                
                $imageId = $pdo->lastInsertId();
                $addedImages[] = [
                    'image_id' => $imageId,
                    'product_id' => (int)$productId,
                    'url' => $imageData['url'],
                    'position' => $imageData['position'] ?? 0,
                    'image_type' => $imageData['image_type'] ?? 'product',
                    'is_main' => $imageData['is_main'] ?? false
                ];
            }
            
            return $res->json(ResponseHelper::success($addedImages, 'Images added successfully'), 201);
            
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to add images: ' . $e->getMessage()));
        }
    }
    
    /**
     * Get product images
     */
    public function getImages(Request $req, Response $res)
    {
        try {
            $productId = $req->getAttribute('id') ?? $req->query('product_id');
            if (!$productId) {
                return $res->json(ResponseHelper::error('Product ID is required'));
            }
            
            $pdo = $this->container->database()->getConnection();
            $sql = "SELECT * FROM product_images 
                    WHERE product_id = ? 
                    ORDER BY is_main DESC, position ASC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$productId]);
            $images = $stmt->fetchAll();
            
            return $res->json(ResponseHelper::success($images));
            
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to fetch images: ' . $e->getMessage()));
        }
    }
    
    /**
     * Update image
     */
    public function updateImage(Request $req, Response $res)
    {
        $user = $req->getAttribute('user');
        
        // Check if user is admin
        if (!in_array('admin', $user['roles'] ?? [])) {
            return $res->json(ResponseHelper::forbidden('Admin access required'));
        }
        
        try {
            $imageId = $req->getAttribute('id') ?? $req->query('image_id');
            if (!$imageId) {
                return $res->json(ResponseHelper::error('Image ID is required'));
            }
            
            $data = $req->json();
            
            // Validate fields
            $rules = [
                'url' => 'string',
                'position' => 'integer',
                'is_main' => 'boolean',
                'image_type' => 'string'
            ];
            
            $validator = new Validator($data, $rules);
            
            if (!$validator->validate()) {
                return $res->json(ResponseHelper::error('Validation failed', 400, $validator->getErrors()));
            }
            
            $pdo = $this->container->database()->getConnection();
            
            // Check if image exists
            $stmt = $pdo->prepare('SELECT * FROM product_images WHERE image_id = ?');
            $stmt->execute([$imageId]);
            $image = $stmt->fetch();
            
            if (!$image) {
                return $res->json(ResponseHelper::notFound('Image not found'));
            }
            
            $fields = [];
            $params = [];
            
            $allowedFields = ['url', 'position', 'image_type', 'is_main'];
            
            foreach ($allowedFields as $field) {
                if (array_key_exists($field, $data)) {
                    $fields[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }
            
            if (empty($fields)) {
                return $res->json(ResponseHelper::error('No fields to update'));
            }
            
            $params[] = $imageId;
            $sql = "UPDATE product_images SET " . implode(', ', $fields) . " WHERE image_id = ?";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            // Get updated image
            $stmt = $pdo->prepare('SELECT * FROM product_images WHERE image_id = ?');
            $stmt->execute([$imageId]);
            $updatedImage = $stmt->fetch();
            
            return $res->json(ResponseHelper::success($updatedImage, 'Image updated successfully'));
            
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to update image: ' . $e->getMessage()));
        }
    }
    
    /**
     * Delete image
     */
    public function deleteImage(Request $req, Response $res)
    {
        $user = $req->getAttribute('user');
        
        // Check if user is admin
        if (!in_array('admin', $user['roles'] ?? [])) {
            return $res->json(ResponseHelper::forbidden('Admin access required'));
        }
        
        try {
            $imageId = $req->getAttribute('id') ?? $req->query('image_id');
            if (!$imageId) {
                return $res->json(ResponseHelper::error('Image ID is required'));
            }
            
            $pdo = $this->container->database()->getConnection();
            
            // Check if image exists
            $stmt = $pdo->prepare('SELECT * FROM product_images WHERE image_id = ?');
            $stmt->execute([$imageId]);
            $image = $stmt->fetch();
            
            if (!$image) {
                return $res->json(ResponseHelper::notFound('Image not found'));
            }
            
            // Delete image
            $stmt = $pdo->prepare('DELETE FROM product_images WHERE image_id = ?');
            $stmt->execute([$imageId]);
            
            return $res->json(ResponseHelper::success(null, 'Image deleted successfully'));
            
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to delete image: ' . $e->getMessage()));
        }
    }
    
    /**
     * Set main image
     */
    public function setMainImage(Request $req, Response $res)
    {
        $user = $req->getAttribute('user');
        
        // Check if user is admin
        if (!in_array('admin', $user['roles'] ?? [])) {
            return $res->json(ResponseHelper::forbidden('Admin access required'));
        }
        
        try {
            $imageId = $req->getAttribute('id') ?? $req->query('image_id');
            if (!$imageId) {
                return $res->json(ResponseHelper::error('Image ID is required'));
            }
            
            $pdo = $this->container->database()->getConnection();
            
            // Check if image exists
            $stmt = $pdo->prepare('SELECT * FROM product_images WHERE image_id = ?');
            $stmt->execute([$imageId]);
            $image = $stmt->fetch();
            
            if (!$image) {
                return $res->json(ResponseHelper::notFound('Image not found'));
            }
            
            $productId = $image['product_id'];
            
            // Begin transaction
            $pdo->beginTransaction();
            
            try {
                // Remove main flag from all images of this product
                $stmt = $pdo->prepare('UPDATE product_images SET is_main = 0 WHERE product_id = ?');
                $stmt->execute([$productId]);
                
                // Set this image as main
                $stmt = $pdo->prepare('UPDATE product_images SET is_main = 1 WHERE image_id = ?');
                $stmt->execute([$imageId]);
                
                $pdo->commit();
                
                return $res->json(ResponseHelper::success($image, 'Main image updated successfully'));
                
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to set main image: ' . $e->getMessage()));
        }
    }
}
