<?php
declare(strict_types=1);

namespace App\Support;

class MessengerCloudinaryService 
{
    private string $cloudName;
    private string $apiKey;
    private string $apiSecret;
    
    public function __construct() 
    {
        // Cloudinary riêng cho messenger
        $this->cloudName = 'doywtb2gt';
        $this->apiKey = '526943482286627';
        $this->apiSecret = 'M90VcBXZxjVKAuYiLZeEPBwKnT8';
    }
    
    public function uploadBase64Image(string $base64Data, ?string $folder = null, ?string $resourceType = null): array
    {
        try {
            // Clean base64 data
            if (strpos($base64Data, 'data:') === 0) {
                $base64Data = preg_replace('/^data:[a-zA-Z]+\/[a-zA-Z]+;base64,/', '', $base64Data);
            }
            
            // Decode base64 to binary
            $imageData = base64_decode($base64Data);
            if ($imageData === false) {
                throw new \Exception("Invalid base64 data");
            }
            
            $timestamp = time();
            
            $params = [
                'timestamp' => $timestamp,
                'folder' => $folder ?? 'messenger',
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
            
            // Use video upload endpoint for videos, image for images
            $resourceType = $resourceType ?? 'image';
            $url = "https://api.cloudinary.com/v1_1/{$this->cloudName}/{$resourceType}/upload";
            
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
            
            // Determine MIME type from format
            $mimeType = 'image/' . ($result['format'] ?? 'jpeg');
            
            return [
                'success' => true,
                'public_id' => $result['public_id'],
                'url' => $result['secure_url'],
                'type' => $mimeType,
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

    public function uploadAudioFile($file, ?string $folder = null): array
    {
        try {
            $timestamp = time();
            
            $params = [
                'timestamp' => $timestamp,
                'folder' => $folder ?? 'messenger/audio',
                'use_filename' => true,
                'unique_filename' => true,
                'resource_type' => 'video' // Cloudinary uses 'video' resource type for audio files
            ];
            
            $signature = $this->generateSignature($params);
            $params['signature'] = $signature;
            $params['api_key'] = $this->apiKey;
            
            $fileData = [
                'file' => new \CURLFile($file['tmp_name'], $file['type'], $file['name'])
            ];
            
            $postData = array_merge($params, $fileData);
            
            $url = "https://api.cloudinary.com/v1_1/{$this->cloudName}/video/upload";
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Longer timeout for audio files
            
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
            
            // Determine MIME type from format
            $mimeType = 'audio/' . ($result['format'] ?? 'webm');
            
            return [
                'success' => true,
                'public_id' => $result['public_id'],
                'url' => $result['secure_url'],
                'type' => $mimeType,
                'duration' => $result['duration'] ?? 0,
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
        // Remove signature, file, api_key, and resource_type from params if present
        unset($params['signature'], $params['file'], $params['api_key'], $params['resource_type']);
        
        // Sort parameters
        ksort($params);
        
        // Build query string
        $query = [];
        foreach ($params as $key => $value) {
            if ($value !== null && $value !== '') {
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
    
    /**
     * Delete image from Cloudinary
     */
    public function deleteImage(string $publicId): array
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
                'message' => 'Image deleted successfully'
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
