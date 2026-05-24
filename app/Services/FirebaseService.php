<?php

declare(strict_types=1);

namespace App\Services;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Firebase\Messaging\AndroidConfig;
use Kreait\Firebase\Exception\MessagingException;
use Exception;

/**
 * FirebaseService - Handles Firebase Cloud Messaging (FCM) push notifications
 */
class FirebaseService
{
    private $messaging;
    private $factory;

    public function __construct()
    {
        try {
            $serviceAccountPath = __DIR__ . '/../config/firebase-service-account.json';
            
            if (!file_exists($serviceAccountPath)) {
                throw new Exception("Firebase service account file not found at: {$serviceAccountPath}");
            }

            $this->factory = (new Factory)->withServiceAccount($serviceAccountPath);
            $this->messaging = $this->factory->createMessaging();
        } catch (Exception $e) {
            error_log('[FirebaseService] Initialization error: ' . $e->getMessage());
            throw new Exception('Failed to initialize Firebase: ' . $e->getMessage());
        }
    }

    /**
     * Send push notification to a single device
     * 
     * @param string $fcmToken The FCM token of the target device
     * @param string $title Notification title
     * @param string $message Notification message body
     * @param array $data Additional data payload (optional)
     * @return bool True if successful, false otherwise
     */
    public function sendNotification(string $fcmToken, string $title, string $message, array $data = []): bool
    {
        try {
            if (empty($fcmToken)) {
                error_log('[FirebaseService] FCM token is empty');
                return false;
            }

            // Determine channel based on notification type
            $type = $data['type'] ?? '';
            $channelId = (strpos($type, 'chat') !== false) ? 'messages' : 'orders';

            $notification = Notification::create($title, $message);
            $fcmData = $this->normalizeFcmData($data);

            $cloudMessage = CloudMessage::withTarget('token', $fcmToken)
                ->withNotification($notification)
                ->withData($fcmData);

            // Android-specific configuration: high priority + correct channel for system notification
            $androidConfig = AndroidConfig::fromArray([
                'priority' => 'high',
                'notification' => [
                    'sound' => 'default',
                    'channel_id' => $channelId,
                    'default_sound' => true,
                    'default_vibrate_timings' => true,
                    'notification_priority' => 'PRIORITY_MAX',
                ],
            ]);
            $cloudMessage = $cloudMessage->withAndroidConfig($androidConfig);

            $result = $this->messaging->send($cloudMessage);

            error_log(
                '[FirebaseService] Notification sent successfully. Channel: '
                . $channelId
                . '. Message ID: '
                . $this->formatSendResult($result)
            );
            return true;

        } catch (MessagingException $e) {
            error_log('[FirebaseService] Messaging error: ' . $e->getMessage());
            return false;
        } catch (Exception $e) {
            error_log('[FirebaseService] Error sending notification: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send push notification to multiple devices
     * 
     * @param array $fcmTokens Array of FCM tokens
     * @param string $title Notification title
     * @param string $message Notification message body
     * @param array $data Additional data payload (optional)
     * @return array Results with success count and failed tokens
     */
    public function sendNotificationToMultiple(array $fcmTokens, string $title, string $message, array $data = []): array
    {
        $results = [
            'success_count' => 0,
            'failed_count' => 0,
            'failed_tokens' => []
        ];

        foreach ($fcmTokens as $token) {
            if ($this->sendNotification($token, $title, $message, $data)) {
                $results['success_count']++;
            } else {
                $results['failed_count']++;
                $results['failed_tokens'][] = $token;
            }
        }

        return $results;
    }

    /**
     * FCM data payload values must be strings.
     *
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    private function normalizeFcmData(array $data): array
    {
        $normalized = [];
        foreach ($data as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            if ($value === null) {
                $normalized[$key] = '';
            } elseif (is_scalar($value)) {
                $normalized[$key] = (string) $value;
            } elseif (is_array($value)) {
                $normalized[$key] = json_encode($value, JSON_UNESCAPED_UNICODE) ?: '[]';
            } else {
                $normalized[$key] = json_encode($value, JSON_UNESCAPED_UNICODE) ?: '';
            }
        }

        return $normalized;
    }

    private function formatSendResult(mixed $result): string
    {
        if (is_string($result)) {
            return $result;
        }
        if (is_scalar($result)) {
            return (string) $result;
        }

        return json_encode($result, JSON_UNESCAPED_UNICODE) ?: gettype($result);
    }
}

