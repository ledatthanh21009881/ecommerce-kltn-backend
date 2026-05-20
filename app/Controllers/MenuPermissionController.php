<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Controller, Request, Response, Container};
use App\Services\MenuPermissionService;
use App\Support\ResponseHelper;
use Exception;
use PDO;

class MenuPermissionController extends Controller
{
    private MenuPermissionService $menuService;

    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->menuService = new MenuPermissionService($container->database());
    }

    public function listAll(Request $req, Response $res)
    {
        try {
            $menus = $this->menuService->getAllMenus();
            return $res->json(ResponseHelper::success($menus));
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to list menus: ' . $e->getMessage()));
        }
    }

    public function getRoleMenus(Request $req, Response $res)
    {
        $roleId = (int) $req->getAttribute('id');
        if ($roleId <= 0) {
            return $res->json(ResponseHelper::error('Role ID is required'));
        }

        try {
            $menus = $this->menuService->getMenusByRoleId($roleId);
            return $res->json(ResponseHelper::success($menus));
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to get role menus: ' . $e->getMessage()));
        }
    }

    public function setRoleMenus(Request $req, Response $res)
    {
        if (!$this->canManageRolePermissions($req)) {
            return $res->json(ResponseHelper::forbidden('Only admin can update role menu permissions'));
        }

        $roleId = (int) $req->getAttribute('id');
        if ($roleId <= 0) {
            return $res->json(ResponseHelper::error('Role ID is required'));
        }

        $data = $req->json();
        $permissionIds = $data['permission_ids'] ?? [];
        if (!is_array($permissionIds)) {
            return $res->json(ResponseHelper::error('permission_ids must be an array'));
        }

        $permissionIds = array_map('intval', $permissionIds);

        try {
            $pdo = $this->container->database()->getConnection();
            $stmt = $pdo->prepare('SELECT role_id FROM roles WHERE role_id = ?');
            $stmt->execute([$roleId]);
            if (!$stmt->fetch()) {
                return $res->json(ResponseHelper::notFound('Role not found'));
            }

            $this->menuService->setRoleMenus($roleId, $permissionIds);
            return $res->json(ResponseHelper::success(null, 'Role menu permissions updated'));
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to update role menus: ' . $e->getMessage()));
        }
    }

    public function getRoleOrderActions(Request $req, Response $res)
    {
        $roleId = (int) $req->getAttribute('id');
        if ($roleId <= 0) {
            return $res->json(ResponseHelper::error('Role ID is required'));
        }

        try {
            $keys = $this->menuService->getOrderActionsByRoleId($roleId);
            return $res->json(ResponseHelper::success($keys));
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to get role order actions: ' . $e->getMessage()));
        }
    }

    public function setRoleOrderActions(Request $req, Response $res)
    {
        if (!$this->canManageRolePermissions($req)) {
            return $res->json(ResponseHelper::forbidden('Only admin can update role permissions'));
        }

        $roleId = (int) $req->getAttribute('id');
        if ($roleId <= 0) {
            return $res->json(ResponseHelper::error('Role ID is required'));
        }

        $data = $req->json();
        $permissionKeys = $data['permission_keys'] ?? [];
        if (!is_array($permissionKeys)) {
            return $res->json(ResponseHelper::error('permission_keys must be an array'));
        }

        $permissionKeys = array_map('strval', $permissionKeys);

        try {
            $pdo = $this->container->database()->getConnection();
            $stmt = $pdo->prepare('SELECT role_id FROM roles WHERE role_id = ?');
            $stmt->execute([$roleId]);
            if (!$stmt->fetch()) {
                return $res->json(ResponseHelper::notFound('Role not found'));
            }

            $this->menuService->setRoleOrderActions($roleId, $permissionKeys);
            return $res->json(ResponseHelper::success(null, 'Role order actions updated'));
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to update role order actions: ' . $e->getMessage()));
        }
    }

    private function canManageRolePermissions(Request $req): bool
    {
        $payload = $req->getAttribute('token_payload') ?? [];
        if (!is_array($payload)) {
            return false;
        }
        if (!empty($payload['is_admin']) || in_array('admin', $payload['roles'] ?? [], true)) {
            return true;
        }
        $userId = (int) ($payload['user_id'] ?? 0);
        if ($userId <= 0) {
            return false;
        }
        $menus = $this->menuService->getMenusByUserId($userId);
        $keys = array_column($menus, 'key');
        return in_array('menu.roles', $keys, true);
    }
}
