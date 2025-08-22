<?php
/**
 * Test endpoint cho Cloudinary upload
 */

require_once '../vendor/autoload.php';

use App\Services\CloudinaryService;

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    // Load environment
    $dotenv = Vlucas\phpdotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
    
    if (!isset($_FILES['image'])) {
        throw new Exception('No image file uploaded');
    }
    
    $imageFile = $_FILES['image'];
    
    if ($imageFile['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Upload error: ' . $imageFile['error']);
    }
    
    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($imageFile['type'], $allowedTypes)) {
        throw new Exception('Invalid file type. Only JPEG, PNG, GIF, WEBP allowed.');
    }
    
    // Validate file size (5MB max)
    if ($imageFile['size'] > 5 * 1024 * 1024) {
        throw new Exception('File too large. Maximum size is 5MB.');
    }
    
    // Initialize Cloudinary service
    $cloudinary = new CloudinaryService();
    
    // Upload to Cloudinary
    $uploadOptions = [
        'folder' => 'test_uploads',
        'use_filename' => true,
        'unique_filename' => true,
        'transformation' => [
            'width' => 800,
            'height' => 600,
            'crop' => 'limit',
            'quality' => 'auto',
            'format' => 'auto'
        ]
    ];
    
    $result = $cloudinary->uploadImage($imageFile['tmp_name'], $uploadOptions);
    
    echo json_encode([
        'success' => true,
        'message' => 'Image uploaded successfully to Cloudinary',
        'data' => [
            'url' => $result['secure_url'],
            'public_id' => $result['public_id'],
            'width' => $result['width'],
            'height' => $result['height'],
            'format' => $result['format'],
            'bytes' => $result['bytes'],
            'created_at' => $result['created_at']
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Cloudinary upload failed: ' . $e->getMessage()
    ]);
}
