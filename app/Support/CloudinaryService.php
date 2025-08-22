<?php
declare(strict_types=1);

namespace App\Support;

class CloudinaryService 
{
    private string $cloudName;
    private string $apiKey;
    private string $apiSecret;
    
    public function __construct() 
    {
        // Sử dụng thông tin Cloudinary của bạn
        $this->cloudName = 'dknwpznzc';
        $this->apiKey = '391689363897318';
        $this->apiSecret = 'nreEK5dlT1YS_ZEFP2Ug_soh4hM';
    }
    
    public function uploadFromUrl(string $imageUrl, ?string $folder = null): array
    {
        try {
            $timestamp = time();
            
            $params = [
                'timestamp' => $timestamp,
                'folder' => $folder ?? 'products',
                'use_filename' => true,
                'unique_filename' => true
            ];
            
            $signature = $this->generateSignature($params);
            $params['signature'] = $signature;
            $params['api_key'] = $this->apiKey;
            $params['file'] = $imageUrl;
            
            $url = "https://api.cloudinary.com/v1_1/{$this->cloudName}/image/upload";
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($curlError) {
                throw new \Exception("cURL error: $curlError");
            }
            
            if ($httpCode !== 200) {
                throw new \Exception("Cloudinary upload failed with HTTP code: $httpCode. Response: $response");
            }
            
            $result = json_decode($response, true);
            if (!$result) {
                throw new \Exception("Invalid JSON response from Cloudinary: $response");
            }
            
            if (isset($result['error'])) {
                throw new \Exception("Cloudinary error: " . $result['error']['message']);
            }
            
            return [
                'success' => true,
                'public_id' => $result['public_id'],
                'url' => $result['secure_url'],
                'width' => $result['width'] ?? 0,
                'height' => $result['height'] ?? 0,
                'format' => $result['format'] ?? 'unknown'
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    public function uploadBase64Image(string $base64Data, ?string $folder = null): array
    {
        try {
            // Clean base64 data
            if (strpos($base64Data, 'data:') === 0) {
                $base64Data = preg_replace('/^data:image\/[a-zA-Z]+;base64,/', '', $base64Data);
            }
            
            // Decode base64 to binary
            $imageData = base64_decode($base64Data);
            if ($imageData === false) {
                throw new \Exception("Invalid base64 data");
            }
            
            $timestamp = time();
            
            $params = [
                'timestamp' => $timestamp,
                'folder' => $folder ?? 'products',
                'use_filename' => true,
                'unique_filename' => true
            ];
            
            $signature = $this->generateSignature($params);
            $params['signature'] = $signature;
            $params['api_key'] = $this->apiKey;
            
            // Create temporary file
            $tempFile = tempnam(sys_get_temp_dir(), 'cloudinary_');
            file_put_contents($tempFile, $imageData);
            
            $fileData = [
                'file' => new \CURLFile($tempFile)
            ];
            
            $postData = array_merge($params, $fileData);
            
            $url = "https://api.cloudinary.com/v1_1/{$this->cloudName}/image/upload";
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            // Clean up temp file
            unlink($tempFile);
            
            if ($curlError) {
                throw new \Exception("cURL error: $curlError");
            }
            
            if ($httpCode !== 200) {
                throw new \Exception("Cloudinary upload failed with HTTP code: $httpCode. Response: $response");
            }
            
            $result = json_decode($response, true);
            if (!$result) {
                throw new \Exception("Invalid JSON response from Cloudinary: $response");
            }
            
            if (isset($result['error'])) {
                throw new \Exception("Cloudinary error: " . $result['error']['message']);
            }
            
            return [
                'success' => true,
                'public_id' => $result['public_id'],
                'url' => $result['secure_url'],
                'width' => $result['width'] ?? 0,
                'height' => $result['height'] ?? 0,
                'format' => $result['format'] ?? 'unknown'
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    private function generateSignature(array $params): string
    {
        // Remove signature and file from params if present
        unset($params['signature'], $params['file'], $params['api_key']);
        
        // Sort parameters
        ksort($params);
        
        // Build query string
        $query = [];
        foreach ($params as $key => $value) {
            if (!empty($value)) {
                $query[] = $key . '=' . $value;
            }
        }
        
        $queryString = implode('&', $query) . $this->apiSecret;
        
        return hash('sha1', $queryString);
    }
    
    // Getter methods for testing
    public function getCloudName(): string
    {
        return $this->cloudName;
    }
    
    public function getApiKey(): string
    {
        return $this->apiKey;
    }
    
    public function getApiSecret(): string
    {
        return $this->apiSecret;
    }
    
    public function delete(string $publicId): array
    {
        try {
            $timestamp = time();
            
            $params = [
                'timestamp' => $timestamp,
                'public_id' => $publicId
            ];
            
            $signature = $this->generateSignature($params);
            $params['signature'] = $signature;
            $params['api_key'] = $this->apiKey;
            
            $url = "https://api.cloudinary.com/v1_1/{$this->cloudName}/image/destroy";
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($curlError) {
                throw new \Exception("cURL error: $curlError");
            }
            
            if ($httpCode !== 200) {
                throw new \Exception("Cloudinary delete failed with HTTP code: $httpCode. Response: $response");
            }
            
            $result = json_decode($response, true);
            if (!$result) {
                throw new \Exception("Invalid JSON response from Cloudinary: $response");
            }
            
            if (isset($result['error'])) {
                throw new \Exception("Cloudinary error: " . $result['error']['message']);
            }
            
            return [
                'success' => true,
                'result' => $result['result'] ?? 'deleted'
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    public function deleteByUrl(string $imageUrl): array
    {
        try {
            // Extract public_id from URL
            $publicId = $this->extractPublicIdFromUrl($imageUrl);
            if (!$publicId) {
                throw new \Exception("Could not extract public_id from URL: $imageUrl");
            }
            
            return $this->delete($publicId);
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    private function extractPublicIdFromUrl(string $imageUrl): ?string
    {
        // Parse Cloudinary URL to extract public_id
        // Example: https://res.cloudinary.com/dknwpznzc/image/upload/v1234567890/products/filename.jpg
        $pattern = '/\/v\d+\/([^.]+)/';
        if (preg_match($pattern, $imageUrl, $matches)) {
            return $matches[1];
        }
        
        // Alternative pattern for URLs without version
        $pattern2 = '/\/image\/upload\/([^.]+)/';
        if (preg_match($pattern2, $imageUrl, $matches)) {
            return $matches[1];
        }
        
        return null;
    }

    /**
     * Upload PDF file to Cloudinary
     */
    public function uploadPDF(string $filePath, ?string $folder = null): string
    {
        try {
            if (!file_exists($filePath)) {
                throw new \Exception("PDF file not found: $filePath");
            }

            $timestamp = time();
            
            $params = [
                'timestamp' => $timestamp,
                'folder' => $folder ?? 'invoices',
                'use_filename' => true,
                'unique_filename' => true,
                'resource_type' => 'raw' // For PDF files
            ];
            
            $signature = $this->generateSignature($params);
            $params['signature'] = $signature;
            $params['api_key'] = $this->apiKey;
            
            // Add file to params
            $params['file'] = new \CURLFile($filePath, 'application/pdf', basename($filePath));
            
            $url = "https://api.cloudinary.com/v1_1/{$this->cloudName}/raw/upload";
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Longer timeout for PDF uploads
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($curlError) {
                throw new \Exception("cURL error: $curlError");
            }
            
            if ($httpCode !== 200) {
                throw new \Exception("Cloudinary PDF upload failed with HTTP code: $httpCode. Response: $response");
            }
            
            $result = json_decode($response, true);
            if (!$result) {
                throw new \Exception("Invalid JSON response from Cloudinary: $response");
            }
            
            if (isset($result['error'])) {
                throw new \Exception("Cloudinary error: " . $result['error']['message']);
            }
            
            return $result['secure_url'];
            
        } catch (\Exception $e) {
            error_log("Cloudinary PDF upload error: " . $e->getMessage());
            throw $e;
        }
    }
}
