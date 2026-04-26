<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Controller, Request, Response, Container};
use App\Support\ResponseHelper;
use App\Support\CloudinaryService;
use PDO;

/**
 * Đọc/ghi cấu hình site từ bảng `site_settings` (key-value).
 */
class SiteSettingsController extends Controller
{
    private const DEFAULTS = [
        'store.site_name' => 'VIVIENNE',
        'store.site_description' => 'Your premium fashion destination',
        'store.contact_email' => 'contact@vivienne.com',
        'store.contact_phone' => '+84 123 456 789',
        'store.address' => '123 Fashion Street, District 1, Ho Chi Minh City',
        'store.timezone' => 'Asia/Ho_Chi_Minh',
        'store.currency' => 'VND',
        'branding.theme' => 'light',
        'branding.primary_color' => '#000000',
        'branding.favicon_url' => '/icon.png',
    ];

    /**
     * Bật/tắt theo từng kênh/loại mà hệ thống thực sự có (chuông admin, email hóa đơn, cảnh báo tồn).
     * Khóa cũ (email_notifications, …) vẫn đọc được qua normalizeNotificationSettings().
     */
    private const NOTIFICATION_DEFAULTS = [
        'admin_notify_new_order' => true,
        'admin_notify_payment' => true,
        'admin_notify_order_assigned' => true,
        'email_invoice' => true,
        'low_stock_alerts' => true,
        'low_stock_threshold' => 5,
    ];

    private const NOTIFICATION_KEY = 'admin.notification_settings';

    public function __construct(Container $container)
    {
        parent::__construct($container);
    }

    /**
     * GET /api/v1/public/site-settings — không cần auth, trả về thông tin public (footer, title, favicon).
     */
    public function publicSettings(Request $req, Response $res): void
    {
        try {
            $pdo = $this->container->database()->getConnection();
            $map = $this->fetchKeys(
                $pdo,
                [
                    'store.site_name',
                    'store.site_description',
                    'store.contact_email',
                    'store.contact_phone',
                    'store.address',
                    'branding.favicon_url',
                ]
            );

            $data = [
                'site_name' => $map['store.site_name'] ?? self::DEFAULTS['store.site_name'],
                'site_description' => $map['store.site_description'] ?? self::DEFAULTS['store.site_description'],
                'contact_email' => $map['store.contact_email'] ?? self::DEFAULTS['store.contact_email'],
                'contact_phone' => $map['store.contact_phone'] ?? self::DEFAULTS['store.contact_phone'],
                'address' => $map['store.address'] ?? self::DEFAULTS['store.address'],
                'favicon_url' => $map['branding.favicon_url'] ?? self::DEFAULTS['branding.favicon_url'],
            ];

            $res->json(ResponseHelper::success($data, 'OK'), 200);
        } catch (\Throwable $e) {
            $res->json(ResponseHelper::error('Failed to load site settings: ' . $e->getMessage(), 500), 500);
        }
    }

    /**
     * GET /api/backend/v1/settings — admin only.
     */
    public function adminGet(Request $req, Response $res): void
    {
        try {
            $pdo = $this->container->database()->getConnection();
            $map = $this->fetchKeys(
                $pdo,
                array_merge(
                    array_keys(self::DEFAULTS),
                    [self::NOTIFICATION_KEY]
                )
            );

            $notifications = self::NOTIFICATION_DEFAULTS;
            if (!empty($map[self::NOTIFICATION_KEY])) {
                $decoded = json_decode((string) $map[self::NOTIFICATION_KEY], true);
                if (is_array($decoded)) {
                    $notifications = $this->normalizeNotificationSettings($decoded);
                }
            }

            $data = [
                'general' => [
                    'site_name' => $map['store.site_name'] ?? self::DEFAULTS['store.site_name'],
                    'site_description' => $map['store.site_description'] ?? self::DEFAULTS['store.site_description'],
                    'contact_email' => $map['store.contact_email'] ?? self::DEFAULTS['store.contact_email'],
                    'contact_phone' => $map['store.contact_phone'] ?? self::DEFAULTS['store.contact_phone'],
                    'address' => $map['store.address'] ?? self::DEFAULTS['store.address'],
                    'timezone' => $map['store.timezone'] ?? self::DEFAULTS['store.timezone'],
                    'currency' => $map['store.currency'] ?? self::DEFAULTS['store.currency'],
                ],
                'notifications' => $notifications,
                'appearance' => [
                    'theme' => $map['branding.theme'] ?? self::DEFAULTS['branding.theme'],
                    'primary_color' => $map['branding.primary_color'] ?? self::DEFAULTS['branding.primary_color'],
                    'favicon_url' => $map['branding.favicon_url'] ?? self::DEFAULTS['branding.favicon_url'],
                ],
            ];

            $res->json(ResponseHelper::success($data, 'Settings loaded'), 200);
        } catch (\Throwable $e) {
            $res->json(ResponseHelper::error('Failed to load settings: ' . $e->getMessage(), 500), 500);
        }
    }

    /**
     * PUT /api/backend/v1/settings — admin only.
     */
    public function adminUpdate(Request $req, Response $res): void
    {
        $pdo = $this->container->database()->getConnection();
        try {
            $payload = $req->getAttribute('user') ?? [];
            $updatedBy = isset($payload['account_id']) ? (int) $payload['account_id'] : null;

            $input = $req->json();
            if ($input === []) {
                $res->json(ResponseHelper::error('Request body is required', 400), 400);
                return;
            }

            $general = is_array($input['general'] ?? null) ? $input['general'] : [];
            $notifications = is_array($input['notifications'] ?? null) ? $input['notifications'] : [];
            $appearance = is_array($input['appearance'] ?? null) ? $input['appearance'] : [];

            $siteName = $this->str($general, 'site_name', 255);
            $email = $this->str($general, 'contact_email', 255);
            if ($siteName === '' || $email === '') {
                throw new \InvalidArgumentException('Site name and contact email are required');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException('Invalid contact email');
            }

            $pdo->beginTransaction();

            $this->upsertString($pdo, 'store.site_name', $siteName, 'string', 1, $updatedBy);
            $this->upsertString($pdo, 'store.site_description', $this->str($general, 'site_description', 2000), 'string', 1, $updatedBy);
            $this->upsertString($pdo, 'store.contact_email', $email, 'string', 1, $updatedBy);
            $this->upsertString($pdo, 'store.contact_phone', $this->str($general, 'contact_phone', 50), 'string', 1, $updatedBy);
            $this->upsertString($pdo, 'store.address', $this->str($general, 'address', 4000), 'string', 1, $updatedBy);
            $tz = $this->str($general, 'timezone', 100);
            $this->upsertString(
                $pdo,
                'store.timezone',
                $tz !== '' ? $tz : self::DEFAULTS['store.timezone'],
                'string',
                0,
                $updatedBy
            );
            $cur = $this->str($general, 'currency', 10);
            $this->upsertString(
                $pdo,
                'store.currency',
                $cur !== '' ? $cur : self::DEFAULTS['store.currency'],
                'string',
                0,
                $updatedBy
            );

            $this->upsertString($pdo, 'branding.theme', $this->oneOf($this->str($appearance, 'theme', 20), ['light', 'dark', 'auto'], 'light'), 'string', 0, $updatedBy);
            $this->upsertString($pdo, 'branding.primary_color', $this->colorHex($this->str($appearance, 'primary_color', 20)), 'string', 0, $updatedBy);
            $this->upsertString($pdo, 'branding.favicon_url', $this->str($appearance, 'favicon_url', 2000), 'string', 1, $updatedBy);

            $notifPayload = $this->normalizeNotificationSettings(
                is_array($notifications) ? $notifications : []
            );
            $this->upsertString(
                $pdo,
                self::NOTIFICATION_KEY,
                json_encode($notifPayload, JSON_UNESCAPED_UNICODE),
                'json',
                0,
                $updatedBy
            );

            $pdo->commit();

            $this->adminGet($req, $res);
        } catch (\InvalidArgumentException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $res->json(ResponseHelper::error($e->getMessage(), 422), 422);
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $res->json(ResponseHelper::error('Failed to save settings: ' . $e->getMessage(), 500), 500);
        }
    }

    /**
     * POST /api/backend/v1/settings/favicon — admin only.
     * Body: { favicon_base64?: string, favicon_url?: string }
     */
    public function uploadFavicon(Request $req, Response $res): void
    {
        $pdo = $this->container->database()->getConnection();
        try {
            $payload = $req->getAttribute('user') ?? [];
            $updatedBy = isset($payload['account_id']) ? (int) $payload['account_id'] : null;
            $input = $req->json();

            $base64 = isset($input['favicon_base64']) ? trim((string) $input['favicon_base64']) : '';
            $url = isset($input['favicon_url']) ? trim((string) $input['favicon_url']) : '';

            if ($base64 === '' && $url === '') {
                $res->json(ResponseHelper::error('favicon_base64 or favicon_url is required', 400), 400);
                return;
            }

            if ($base64 !== '') {
                if (!preg_match('/^data:(image\\/png|image\\/jpeg|image\\/jpg|image\\/webp|image\\/x-icon|image\\/vnd\\.microsoft\\.icon);base64,/', $base64)) {
                    $res->json(ResponseHelper::error('Unsupported favicon image format', 422), 422);
                    return;
                }
            }

            if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) {
                $res->json(ResponseHelper::error('Invalid favicon URL', 422), 422);
                return;
            }

            $cloudinary = new CloudinaryService();
            $upload = $base64 !== ''
                ? $cloudinary->uploadBase64Image($base64, 'branding/favicon', 'image')
                : $cloudinary->uploadFromUrl($url, 'branding/favicon');

            if (!($upload['success'] ?? false) || empty($upload['url'])) {
                $err = isset($upload['error']) ? (string) $upload['error'] : 'Upload failed';
                $res->json(ResponseHelper::error('Favicon upload failed: ' . $err, 422), 422);
                return;
            }

            $pdo->beginTransaction();
            $this->upsertString(
                $pdo,
                'branding.favicon_url',
                (string) $upload['url'],
                'string',
                1,
                $updatedBy
            );
            $pdo->commit();

            $res->json(ResponseHelper::success([
                'favicon_url' => (string) $upload['url'],
                'public_id' => $upload['public_id'] ?? null,
            ], 'Favicon uploaded successfully'), 200);
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $res->json(ResponseHelper::error('Failed to upload favicon: ' . $e->getMessage(), 500), 500);
        }
    }

    private function fetchKeys(PDO $pdo, array $keys): array
    {
        if ($keys === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM site_settings WHERE setting_key IN ({$placeholders})");
        $stmt->execute($keys);
        $out = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $out[$row['setting_key']] = $row['setting_value'];
        }
        return $out;
    }

    private function upsertString(
        PDO $pdo,
        string $key,
        string $value,
        string $valueType,
        int $isPublic,
        ?int $updatedBy
    ): void {
        $sql = "INSERT INTO site_settings (setting_key, setting_value, value_type, description, is_public, updated_by)
                VALUES (?, ?, ?, NULL, ?, ?)
                ON DUPLICATE KEY UPDATE
                  setting_value = VALUES(setting_value),
                  value_type = VALUES(value_type),
                  is_public = VALUES(is_public),
                  updated_by = VALUES(updated_by),
                  updated_at = CURRENT_TIMESTAMP";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$key, $value, $valueType, $isPublic, $updatedBy]);
    }

    private function str(array $src, string $key, int $maxLen): string
    {
        if (!array_key_exists($key, $src)) {
            return '';
        }
        $s = is_string($src[$key]) ? trim($src[$key]) : (string) $src[$key];
        if (strlen($s) > $maxLen) {
            throw new \InvalidArgumentException("Field {$key} is too long");
        }
        return $s;
    }

    private function oneOf(string $v, array $allowed, string $default): string
    {
        $v = strtolower($v) ?: $default;
        return in_array($v, $allowed, true) ? $v : $default;
    }

    private function colorHex(string $v): string
    {
        if ($v === '') {
            return self::DEFAULTS['branding.primary_color'];
        }
        if (preg_match('/^#[0-9A-Fa-f]{6}$/', $v)) {
            return $v;
        }
        throw new \InvalidArgumentException('Invalid primary color (use #RRGGBB)');
    }

    /**
     * @param array<string, mixed> $raw
     * @return array<string, bool|int>
     */
    private function normalizeNotificationSettings(array $raw): array
    {
        $out = self::NOTIFICATION_DEFAULTS;
        foreach (array_keys(self::NOTIFICATION_DEFAULTS) as $k) {
            if (array_key_exists($k, $raw)) {
                if ($k === 'low_stock_threshold') {
                    $out[$k] = max(0, min(999999, (int) $raw[$k]));
                } else {
                    $out[$k] = (bool) $raw[$k];
                }
            }
        }
        if (!array_key_exists('email_invoice', $raw)) {
            if (array_key_exists('order_confirmation', $raw)) {
                $out['email_invoice'] = (bool) $raw['order_confirmation'];
            } elseif (array_key_exists('email_notifications', $raw)) {
                $out['email_invoice'] = (bool) $raw['email_notifications'];
            }
        }

        return $out;
    }
}
