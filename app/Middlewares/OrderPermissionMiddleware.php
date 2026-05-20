<?php
declare(strict_types=1);

namespace App\Middlewares;

use App\Core\{Request, Response, Container};
use App\Support\ResponseHelper;
use App\Services\MenuPermissionService;

/**
 * Chặn API backend admin orders theo quyền hành động (orders.manage / orders.assign_shipper).
 * GET list/show/statistics chỉ cần menu.orders (MenuPermissionMiddleware).
 */
class OrderPermissionMiddleware
{
    public function __construct(private Container $container) {}

    public function handle(Request $request, Response $response, callable $next)
    {
        $path = rtrim($request->path(), '/') ?: '/';
        if (!str_starts_with($path, '/api/backend/v1/orders')) {
            return $next($request, $response);
        }

        $payload = $request->getAttribute('token_payload');
        if (!is_array($payload)) {
            return $response->json(ResponseHelper::unauthorized('Authentication required'));
        }

        if (!empty($payload['is_admin']) || in_array('admin', $payload['roles'] ?? [], true)) {
            return $next($request, $response);
        }

        $requiredAction = $this->resolveRequiredOrderAction($request->method(), $path);
        if ($requiredAction === null) {
            return $next($request, $response);
        }

        $userId = (int) ($payload['user_id'] ?? 0);
        if ($userId <= 0) {
            return $response->json(ResponseHelper::forbidden('Order action access denied'));
        }

        $menuService = new MenuPermissionService($this->container->database());
        if (!$menuService->userHasOrderAction($userId, $requiredAction)) {
            return $response->json(ResponseHelper::forbidden('You do not have permission for this order action'));
        }

        return $next($request, $response);
    }

    private function resolveRequiredOrderAction(string $method, string $path): ?string
    {
        $method = strtoupper($method);

        if (preg_match('#/assign-shipper$#', $path) && $method === 'POST') {
            return 'orders.assign_shipper';
        }

        if (str_ends_with($path, '/available-shippers') && $method === 'GET') {
            return 'orders.assign_shipper';
        }

        if (preg_match('#/status$#', $path) && ($method === 'PUT' || $method === 'POST')) {
            return 'orders.manage';
        }

        if (preg_match('#/send-invoice$#', $path) && $method === 'POST') {
            return 'orders.manage';
        }

        if (preg_match('#/orders/\d+$#', $path) && $method === 'DELETE') {
            return 'orders.manage';
        }

        if (str_ends_with($path, '/export') && $method === 'GET') {
            return 'orders.manage';
        }

        return null;
    }
}
