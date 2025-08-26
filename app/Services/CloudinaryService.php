<?php
/**
 * CloudinaryService.php
 * Service để upload ảnh & video lên Cloudinary (SDK-based)
 */

declare(strict_types=1);

namespace App\Services;

use RuntimeException;
use Exception;
use Cloudinary\Cloudinary as CloudinarySDK;

class CloudinaryService
{
    private CloudinarySDK $cloudinary;

    /**
     * Khởi tạo Cloudinary service
     */
    public function __construct(array $options = [])
    {
        $cloudName = $options['cloud_name'] ?? $_ENV['CLOUDINARY_CLOUD_NAME'] ?? 'dknwpznzc';
        $apiKey    = $options['api_key']    ?? $_ENV['CLOUDINARY_API_KEY'] ?? '391689363897318';
        $apiSecret = $options['api_secret'] ?? $_ENV['CLOUDINARY_API_SECRET'] ?? 'nreEK5dIT1YS_ZEFP2Ug_soh4hM';

        if (!$cloudName || !$apiKey || !$apiSecret) {
            throw new RuntimeException(
                'Thiếu cấu hình Cloudinary. Hãy set CLOUD_NAME, API_KEY, API_SECRET.'
            );
        }

        $this->cloudinary = new CloudinarySDK([
            'cloud' => [
                'cloud_name' => $cloudName,
                'api_key'    => $apiKey,
                'api_secret' => $apiSecret,
            ]
        ]);
    }

    /**
     * Upload 1 ảnh từ đường dẫn local hoặc URL
     */
    public function uploadImage(string $source, array $options = []): array
    {
        try {
            $defaults = [
                'resource_type' => 'image',
                'folder' => 'products',
                'overwrite' => true,
                'use_filename' => true,
                'unique_filename' => false,
            ];
            
            $uploadOptions = array_merge($defaults, $options);
            
            $result = $this->cloudinary->uploadApi()->upload($source, $uploadOptions);
            
            return [
                'success' => true,
                'url' => $result['secure_url'] ?? $result['url'],
                'public_id' => $result['public_id'],
                'width' => $result['width'] ?? null,
                'height' => $result['height'] ?? null,
                'format' => $result['format'] ?? null,
                'bytes' => $result['bytes'] ?? null
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Upload từ base64 string
     */
    public function uploadFromBase64(string $base64Data, array $options = []): array
    {
        try {
            $defaults = [
                'resource_type' => 'image',
                'folder' => 'products',
                'overwrite' => true,
                'unique_filename' => true,
            ];
            
            // Cloudinary accepts data URIs directly
            if (!str_starts_with($base64Data, 'data:')) {
                $base64Data = 'data:image/jpeg;base64,' . $base64Data;
            }
            
            $uploadOptions = array_merge($defaults, $options);
            $result = $this->cloudinary->uploadApi()->upload($base64Data, $uploadOptions);
            
            return [
                'success' => true,
                'url' => $result['secure_url'] ?? $result['url'],
                'public_id' => $result['public_id'],
                'width' => $result['width'] ?? null,
                'height' => $result['height'] ?? null,
                'format' => $result['format'] ?? null,
                'bytes' => $result['bytes'] ?? null
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Upload từ uploaded file
     */
    public function uploadFromFile(array $file, array $options = []): array
    {
        try {
            if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
                throw new RuntimeException('Invalid uploaded file');
            }

            $defaults = [
                'resource_type' => 'image',
                'folder' => 'products',
                'overwrite' => true,
                'use_filename' => true,
                'unique_filename' => false,
            ];

            $uploadOptions = array_merge($defaults, $options);
            $result = $this->cloudinary->uploadApi()->upload($file['tmp_name'], $uploadOptions);
            
            return [
                'success' => true,
                'url' => $result['secure_url'] ?? $result['url'],
                'public_id' => $result['public_id'],
                'width' => $result['width'] ?? null,
                'height' => $result['height'] ?? null,
                'format' => $result['format'] ?? null,
                'bytes' => $result['bytes'] ?? null
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Upload media (ảnh/video) cho messenger
     */
    public function uploadMedia(array $file, array $options = []): array
    {
        try {
            // Validate file
            if (!isset($file['tmp_name']) || !file_exists($file['tmp_name'])) {
                throw new RuntimeException('Invalid file');
            }

            // Determine resource type based on file type
            $resourceType = 'image';
            if (isset($file['type'])) {
                if (str_starts_with($file['type'], 'video/')) {
                    $resourceType = 'video';
                } elseif (str_starts_with($file['type'], 'image/')) {
                    $resourceType = 'image';
                } else {
                    throw new RuntimeException('Unsupported file type');
                }
            }

            $defaults = [
                'resource_type' => $resourceType,
                'folder' => 'messenger',
                'overwrite' => true,
                'use_filename' => true,
                'unique_filename' => true,
            ];

            $uploadOptions = array_merge($defaults, $options);
            
            $result = $this->cloudinary->uploadApi()->upload($file['tmp_name'], $uploadOptions);
            
            return [
                'success' => true,
                'url' => $result['secure_url'] ?? $result['url'],
                'public_id' => $result['public_id'],
                'type' => $file['type'] ?? 'image/jpeg',
                'file_name' => $file['name'] ?? null,
                'file_size' => $file['size'] ?? null
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Upload từ URL
     */
    public function uploadFromUrl(string $url, array $options = []): array
    {
        try {
            // Validate URL
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                throw new RuntimeException('Invalid URL provided');
            }

            $defaults = [
                'resource_type' => 'image',
                'folder' => 'products',
                'overwrite' => true,
                'unique_filename' => true,
            ];

            $uploadOptions = array_merge($defaults, $options);
            $result = $this->cloudinary->uploadApi()->upload($url, $uploadOptions);
            
            return [
                'success' => true,
                'url' => $result['secure_url'] ?? $result['url'],
                'public_id' => $result['public_id'],
                'width' => $result['width'] ?? null,
                'height' => $result['height'] ?? null,
                'format' => $result['format'] ?? null,
                'bytes' => $result['bytes'] ?? null
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Tạo URL ảnh với transformations
     */
    public function imageUrl(string $publicId, array $transforms = []): string
    {
        return $this->cloudinary->image($publicId)->toUrl();
    }

    /**
     * Xóa file theo public_id
     */
    public function delete(string $publicId, string $resourceType = 'image'): array
    {
        try {
            $result = $this->cloudinary->uploadApi()->destroy($publicId, [
                'resource_type' => $resourceType
            ]);
            
            return [
                'success' => true,
                'result' => $result
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Tạo public_id theo pattern
     */
    public static function makePublicId(string $prefix, string $slug, ?int $index = null): string
    {
        $base = trim($prefix, '/').'/'.trim($slug, '/');
        return $index === null ? $base : $base.'-'.$index;
    }

    /**
     * Validate image file
     */
    public function validateImageFile(array $file): bool
    {
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $maxSize = 10 * 1024 * 1024; // 10MB

        if (!in_array($file['type'], $allowedTypes)) {
            return false;
        }

        if ($file['size'] > $maxSize) {
            return false;
        }

        return true;
    }

    /**
     * Validate image URL
     */
    public function validateImageUrl(string $url): bool
    {
        $headers = @get_headers($url, true);
        if (!$headers) {
            return false;
        }

        $contentType = $headers['Content-Type'] ?? '';
        if (is_array($contentType)) {
            $contentType = end($contentType);
        }

        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        return in_array($contentType, $allowedTypes);
    }
}
