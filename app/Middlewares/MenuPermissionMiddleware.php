<?php
declare(strict_types=1);

namespace App\Middlewares;

use App\Core\{Request, Response, Container};
use App\Support\ResponseHelper;
use App\Services\MenuPermissionService;

/**
 * Chặn API backend admin khi JWT không có menu path tương ứng.
 */
class MenuPermissionMiddleware
{
    /** @var array<string, string> prefix => permission_key */
    private const ROUTE_MENU_MAP = [
        '/api/backend/v1/orders' => 'menu.orders',
        '/api/backend/v1/users' => 'menu.users',
        '/api/backend/v1/accounts' => 'menu.account_management',
        '/api/backend/v1/products' => 'menu.products',
        '/api/backend/v1/categories' => 'menu.categories',
        '/api/backend/v1/inventory' => 'menu.inventory',
        '/api/backend/v1/inventory-new' => 'menu.inventory',
        '/api/backend/v1/inventory-simple' => 'menu.inventory',
        '/api/backend/v1/stock-adjustments' => 'menu.inventory',
        '/api/backend/v1/shipping' => 'menu.shipping',
        '/api/backend/v1/vouchers' => 'menu.promotions',
        '/api/backend/v1/suppliers' => 'menu.suppliers',
        '/api/backend/v1/purchase-receipts' => 'menu.purchase_receipts',
        '/api/backend/v1/content' => 'menu.content',
        '/api/backend/v1/payments' => 'menu.payments',
        '/api/backend/v1/tracking' => 'menu.tracking',
        '/api/backend/v1/settings' => 'menu.settings',
        '/api/backend/v1/admin/dashboard' => 'menu.dashboard',
        '/api/backend/v1/menu-permissions' => 'menu.roles',
        '/api/backend/v1/roles' => 'menu.roles',
    ];

    public function __construct(private Container $container) {}

    public function handle(Request $request, Response $response, callable $next)
    {
        $payload = $request->getAttribute('token_payload');
        if (!is_array($payload)) {
            return $response->json(ResponseHelper::unauthorized('Authentication required'));
        }

        if (!empty($payload['is_admin']) || in_array('admin', $payload['roles'] ?? [], true)) {
            return $next($request, $response);
        }

        $requiredKey = $this->resolveRequiredMenuKey($request->path());
        if ($requiredKey === null) {
            return $next($request, $response);
        }

        $userId = (int) ($payload['user_id'] ?? 0);
        if ($userId <= 0) {
            return $response->json(ResponseHelper::forbidden('Menu access denied'));
        }

        $menuService = new MenuPermissionService($this->container->database());
        $menus = $menuService->getMenusByUserId($userId);
        $keys = array_column($menus, 'key');

        if (!in_array($requiredKey, $keys, true)) {
            return $response->json(ResponseHelper::forbidden('You do not have permission to access this resource'));
        }

        return $next($request, $response);
    }

    private function resolveRequiredMenuKey(string $path): ?string
    {
        $path = rtrim($path, '/') ?: '/';
        $bestMatch = null;
        $bestLen = 0;

        foreach (self::ROUTE_MENU_MAP as $prefix => $key) {
            if (str_starts_with($path, $prefix) && strlen($prefix) > $bestLen) {
                $bestMatch = $key;
                $bestLen = strlen($prefix);
            }
        }

        return $bestMatch;
    }
}
