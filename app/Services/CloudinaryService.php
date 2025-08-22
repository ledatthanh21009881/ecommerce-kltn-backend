<?php
/**
 * CloudinaryService.php
 * Service để upload ảnh & video lên Cloudinary (cURL-based)
 */

declare(strict_types=1);

namespace App\Services;

use RuntimeException;
use Exception;

class CloudinaryService
{
    private array $config;

    /**
     * Khởi tạo Cloudinary service
     */
    public function __construct(array $options = [])
    {
        $cloudName = $options['cloud_name'] ?? $_ENV['CLOUDINARY_CLOUD_NAME'] ?? 'dknwpznzc';
        $apiKey    = $options['api_key']    ?? $_ENV['CLOUDINARY_API_KEY'] ?? '391689363897318';
        $apiSecret = $options['api_secret'] ?? $_ENV['CLOUDINARY_API_SECRET'] ?? 'nreEK5dlTIYS_ZEFP2Ug_soh4hM';

        if (!$cloudName || !$apiKey || !$apiSecret) {
            throw new RuntimeException(
                'Thiếu cấu hình Cloudinary. Hãy set CLOUD_NAME, API_KEY, API_SECRET.'
            );
        }

        $this->config = [
            'cloud_name' => $cloudName,
            'api_key'    => $apiKey,
            'api_secret' => $apiSecret,
        ];
    }

    /**
     * Upload 1 ảnh từ đường dẫn local hoặc URL
     */
    public function uploadImage(string $source, array $options = []): array
    {
        $defaults = [
            'resource_type'   => 'image',
            'folder'          => 'products',
            'overwrite'       => true,
            'use_filename'    => true,
            'unique_filename' => false,
        ];
        
        return $this->uploadWithCurl($source, array_merge($defaults, $options));
    }

    /**
     * Upload từ base64 string
     */
    public function uploadFromBase64(string $base64Data, array $options = []): array
    {
        $defaults = [
            'resource_type'   => 'image',
            'folder'          => 'products',
            'overwrite'       => true,
            'unique_filename' => true,
        ];
        
        // Cloudinary accepts data URIs directly
        if (!str_starts_with($base64Data, 'data:')) {
            $base64Data = 'data:image/jpeg;base64,' . $base64Data;
        }
        
        return $this->uploadWithCurl($base64Data, array_merge($defaults, $options));
    }

    /**
     * Upload từ uploaded file
     */
    public function uploadFromFile(array $file, array $options = []): array
    {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new RuntimeException('Invalid uploaded file');
        }

        $defaults = [
            'resource_type'   => 'image',
            'folder'          => 'products',
            'overwrite'       => true,
            'use_filename'    => true,
            'unique_filename' => false,
        ];

        return $this->uploadWithCurl($file['tmp_name'], array_merge($defaults, $options));
    }

    /**
     * Upload từ URL
     */
    public function uploadFromUrl(string $url, array $options = []): array
    {
        // Validate URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('Invalid URL provided');
        }

        $defaults = [
            'resource_type'   => 'image',
            'folder'          => 'products',
            'overwrite'       => true,
            'unique_filename' => true,
        ];

        return $this->uploadWithCurl($url, array_merge($defaults, $options));
    }

    /**
     * Tạo URL ảnh với transformations
     */
    public function imageUrl(string $publicId, array $transforms = []): string
    {
        return $this->buildImageUrl($publicId, $transforms);
    }

    /**
     * Xóa file theo public_id
     */
    public function delete(string $publicId, string $resourceType = 'image'): array
    {
        return $this->deleteWithCurl($publicId, $resourceType);
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

    /**
     * Upload with cURL as fallback
     */
    private function uploadWithCurl(string $source, array $options): array
    {
        try {
            $url = "https://api.cloudinary.com/v1_1/{$this->config['cloud_name']}/image/upload";
            
            $postData = [
                'file' => $source,
                'api_key' => $this->config['api_key'],
                'timestamp' => time(),
            ];
            
            // Add options
            foreach ($options as $key => $value) {
                $postData[$key] = $value;
            }
            
            // Generate signature
            $postData['signature'] = $this->generateSignature($postData);
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error) {
                return [
                    'success' => false,
                    'error' => "cURL error: $error"
                ];
            }
            
            if ($httpCode !== 200) {
                return [
                    'success' => false,
                    'error' => "Cloudinary upload failed: HTTP $httpCode"
                ];
            }
            
            $result = json_decode($response, true);
            if (!$result) {
                return [
                    'success' => false,
                    'error' => "Invalid Cloudinary response"
                ];
            }
            
            if (isset($result['error'])) {
                return [
                    'success' => false,
                    'error' => $result['error']['message'] ?? 'Cloudinary error'
                ];
            }
            
            return [
                'success' => true,
                'url' => $result['secure_url'] ?? $result['url'],
                'public_id' => $result['public_id'] ?? null,
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
     * Delete with cURL as fallback
     */
    private function deleteWithCurl(string $publicId, string $resourceType): array
    {
        $url = "https://api.cloudinary.com/v1_1/{$this->config['cloud_name']}/image/destroy";
        
        $postData = [
            'public_id' => $publicId,
            'api_key' => $this->config['api_key'],
            'timestamp' => time(),
            'resource_type' => $resourceType,
        ];
        
        // Generate signature
        $postData['signature'] = $this->generateSignature($postData);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($response, true) ?: [];
    }
    
    /**
     * Build image URL manually
     */
    private function buildImageUrl(string $publicId, array $transforms): string
    {
        $baseUrl = "https://res.cloudinary.com/{$this->config['cloud_name']}/image/upload";
        
        $transformStr = '';
        if (!empty($transforms)) {
            $parts = [];
            if (isset($transforms['width'])) $parts[] = "w_{$transforms['width']}";
            if (isset($transforms['height'])) $parts[] = "h_{$transforms['height']}";
            if (isset($transforms['crop'])) $parts[] = "c_{$transforms['crop']}";
            if (isset($transforms['quality'])) $parts[] = "q_{$transforms['quality']}";
            if (isset($transforms['format'])) $parts[] = "f_{$transforms['format']}";
            
            if (!empty($parts)) {
                $transformStr = implode(',', $parts) . '/';
            }
        }
        
        return "$baseUrl/$transformStr$publicId";
    }
    
    /**
     * Generate signature for API calls
     */
    private function generateSignature(array $params): string
    {
        // Remove signature and file from params
        unset($params['signature'], $params['file']);
        
        // Sort params
        ksort($params);
        
        // Build query string
        $query = '';
        foreach ($params as $key => $value) {
            $query .= "$key=$value&";
        }
        $query = rtrim($query, '&');
        
        // Add secret and hash
        $query .= $this->config['api_secret'];
        
        return sha1($query);
    }
}
