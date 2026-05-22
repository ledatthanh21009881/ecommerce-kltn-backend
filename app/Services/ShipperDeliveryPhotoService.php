<?php

declare(strict_types=1);

namespace App\Services;

use Exception;

class ShipperDeliveryPhotoService
{
    private CloudinaryService $cloudinary;

    public function __construct(?CloudinaryService $cloudinary = null)
    {
        $this->cloudinary = $cloudinary ?? new CloudinaryService();
    }

    /**
     * Resolve photo input to a public HTTPS URL for storage in DB.
     *
     * @param array<string, mixed> $payload
     */
    public function resolveFromPayload(int $orderId, array $payload): ?string
    {
        $base64 = $payload['photo_base64'] ?? $payload['photoBase64'] ?? null;
        if (is_string($base64) && trim($base64) !== '') {
            return $this->uploadBase64($orderId, trim($base64));
        }

        $candidates = [
            $payload['photo_url'] ?? null,
            $payload['photoUrl'] ?? null,
            $payload['photo'] ?? null,
            $payload['confirmation_photo'] ?? null,
        ];

        foreach ($candidates as $value) {
            if (!is_string($value) || trim($value) === '') {
                continue;
            }
            $value = trim($value);
            return $this->resolveUrlString($orderId, $value);
        }

        return null;
    }

    public function resolveUrlString(int $orderId, string $value): string
    {
        if (str_starts_with($value, 'https://')) {
            return $value;
        }

        if (str_starts_with($value, 'http://')) {
            return $value;
        }

        if (preg_match('#^(file|content)://#i', $value)) {
            throw new Exception(
                'Local photo URIs are not supported. Send photo_base64 from the shipper app.',
                422
            );
        }

        if (str_starts_with($value, 'data:image/')) {
            return $this->uploadBase64($orderId, $value);
        }

        // Raw base64 without data URI prefix
        if (strlen($value) > 200 && !str_contains($value, ' ')) {
            return $this->uploadBase64($orderId, $value);
        }

        throw new Exception('Invalid photo format. Provide photo_base64 or an HTTPS URL.', 422);
    }

    private function uploadBase64(int $orderId, string $base64Data): string
    {
        $folder = 'shipper-delivery-proofs/order_' . $orderId;
        $result = $this->cloudinary->uploadFromBase64($base64Data, [
            'folder' => $folder,
            'resource_type' => 'image',
        ]);

        if (empty($result['success']) || empty($result['url'])) {
            $message = $result['error'] ?? 'Cloudinary upload failed';
            throw new Exception('Failed to upload delivery photo: ' . $message, 500);
        }

        return (string) $result['url'];
    }
}
