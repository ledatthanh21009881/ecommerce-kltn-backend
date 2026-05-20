<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Controller, Request, Response, Container};
use App\Domain\Roles\Role;
use App\Support\{ResponseHelper, PanelRole};
use Exception;

class RoleController extends Controller
{
    private Role $roleModel;

    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->roleModel = new Role($container->database());
    }

    /**
     * Lấy danh sách roles với phân trang
     */
    public function index(): void
    {
        try {
            $page = (int) ($_GET['page'] ?? 1);
            $limit = (int) ($_GET['limit'] ?? 20);
            $offset = ($page - 1) * $limit;

            $filters = [
                'search' => $_GET['search'] ?? null
            ];

            // Loại bỏ các filter null/empty
            $filters = array_filter($filters, function($value) {
                return $value !== null && $value !== '';
            });

            $roles = $this->roleModel->getAllWithPagination($filters, $limit, $offset);
            $total = $this->roleModel->getCount($filters);

            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'roles' => $roles,
                    'pagination' => [
                        'page' => $page,
                        'limit' => $limit,
                        'total' => $total,
                        'total_pages' => ceil($total / $limit)
                    ]
                ]
            ]);
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to fetch roles: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lấy chi tiết role
     */
    public function show(int $id): void
    {
        try {
            $role = $this->roleModel->findByIdWithDetails($id);
            if (!$role) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Role not found'
                ], 404);
                return;
            }

            $this->jsonResponse([
                'success' => true,
                'data' => $role
            ]);
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to fetch role: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Tạo role mới
     */
    public function store(): void
    {
        try {
            $data = $this->request->json();

            // Validation
            $errors = $this->validateRoleData($data, false);
            if (!empty($errors)) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $errors
                ], 400);
                return;
            }

            $roleId = $this->roleModel->create($data);

            $this->jsonResponse([
                'success' => true,
                'message' => 'Role created successfully',
                'data' => ['role_id' => $roleId]
            ], 201);
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to create role: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cập nhật role
     */
    public function update(int $id): void
    {
        try {
            $data = $this->request->json();

            // Validation
            $errors = $this->validateRoleData($data, false);
            if (!empty($errors)) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $errors
                ], 400);
                return;
            }

            $success = $this->roleModel->update($id, $data);

            if ($success) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Role updated successfully'
                ]);
            } else {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Role not found'
                ], 404);
            }
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to update role: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Xóa role
     */
    public function destroy(int $id): void
    {
        try {
            $success = $this->roleModel->delete($id);

            if ($success) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Role deleted successfully'
                ]);
            } else {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Role not found or has assigned users'
                ], 404);
            }
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to delete role: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lấy tất cả roles (cho dropdown)
     */
    public function getAll(Request $req, Response $res)
    {
        try {
            $roles = $this->roleModel->getAll();
            $scope = $req->query('scope');
            if ($scope === 'panel') {
                $roles = array_values(array_filter(
                    $roles,
                    static fn (array $r) => PanelRole::isPanelRole((string) ($r['role_name'] ?? ''))
                ));
            } elseif ($scope === 'external') {
                $roles = array_values(array_filter(
                    $roles,
                    static fn (array $r) => PanelRole::isExternal((string) ($r['role_name'] ?? ''))
                ));
            }

            return $res->json(ResponseHelper::success($roles));
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to fetch roles: ' . $e->getMessage()));
        }
    }

    public function listPanel(Request $req, Response $res)
    {
        try {
            $page = max(1, (int) ($req->query('page') ?? 1));
            $limit = min(100, max(1, (int) ($req->query('limit') ?? 20)));
            $offset = ($page - 1) * $limit;
            $search = $req->query('search');
            $search = is_string($search) && $search !== '' ? $search : null;

            $roles = $this->roleModel->getPanelRolesWithPagination($search, $limit, $offset);
            $total = $this->roleModel->getPanelRoleCount($search);

            return $res->json(ResponseHelper::success([
                'roles' => $roles,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'total_pages' => (int) ceil($total / $limit),
                ],
            ]));
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to fetch panel roles: ' . $e->getMessage()));
        }
    }

    public function storePanel(Request $req, Response $res)
    {
        try {
            $data = $req->json();
            $errors = $this->validateRoleData($data, true);
            if ($errors !== []) {
                return $res->json(ResponseHelper::error('Validation failed', 400, $errors));
            }

            $roleName = $this->normalizeRoleName((string) $data['role_name']);
            if (!PanelRole::isPanelRole($roleName)) {
                return $res->json(ResponseHelper::error('Cannot create external roles from admin panel', 400));
            }

            $displayName = $this->normalizeDisplayName((string) ($data['display_name'] ?? ''));
            $roleId = $this->roleModel->create([
                'role_name' => $roleName,
                'display_name' => $displayName,
            ]);

            return $res->json(ResponseHelper::success(['role_id' => $roleId], 'Role created successfully'), 201);
        } catch (Exception $e) {
            $msg = $e->getMessage();
            $code = str_contains($msg, 'already exists') ? 409 : 500;
            return $res->json(ResponseHelper::error($code === 409 ? $msg : 'Failed to create role: ' . $msg), $code);
        }
    }

    public function updatePanel(Request $req, Response $res)
    {
        $roleId = (int) $req->getAttribute('id');
        if ($roleId <= 0) {
            return $res->json(ResponseHelper::error('Role ID is required'), 400);
        }

        try {
            $existing = $this->roleModel->findById($roleId);
            if (!$existing) {
                return $res->json(ResponseHelper::notFound('Role not found'));
            }

            $data = $req->json();
            $errors = $this->validateRoleData($data, true);
            if ($errors !== []) {
                return $res->json(ResponseHelper::error('Validation failed', 400, $errors));
            }

            $roleName = $this->normalizeRoleName((string) $data['role_name']);
            if (strtolower((string) $existing['role_name']) === 'admin' && $roleName !== 'admin') {
                return $res->json(ResponseHelper::forbidden('Cannot rename the admin role'));
            }
            if (!PanelRole::isPanelRole($roleName)) {
                return $res->json(ResponseHelper::error('Invalid role name for panel'), 400);
            }

            $displayName = $this->normalizeDisplayName((string) ($data['display_name'] ?? ''));
            $this->roleModel->update($roleId, [
                'role_name' => $roleName,
                'display_name' => $displayName,
            ]);

            return $res->json(ResponseHelper::success(null, 'Role updated successfully'));
        } catch (Exception $e) {
            $msg = $e->getMessage();
            $code = str_contains($msg, 'already exists') ? 409 : 500;
            return $res->json(ResponseHelper::error($code === 409 ? $msg : 'Failed to update role: ' . $msg), $code);
        }
    }

    public function destroyPanel(Request $req, Response $res)
    {
        $roleId = (int) $req->getAttribute('id');
        if ($roleId <= 0) {
            return $res->json(ResponseHelper::error('Role ID is required'), 400);
        }

        try {
            $existing = $this->roleModel->findById($roleId);
            if (!$existing) {
                return $res->json(ResponseHelper::notFound('Role not found'));
            }

            if (strtolower((string) $existing['role_name']) === 'admin') {
                return $res->json(ResponseHelper::forbidden('Cannot delete the admin role'));
            }

            $deleted = $this->roleModel->delete($roleId);
            if (!$deleted) {
                return $res->json(ResponseHelper::notFound('Role not found'));
            }

            return $res->json(ResponseHelper::success(null, 'Role deleted successfully'));
        } catch (Exception $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'assigned users')) {
                return $res->json(ResponseHelper::error($msg), 409);
            }
            return $res->json(ResponseHelper::serverError('Failed to delete role: ' . $msg));
        }
    }

    private function normalizeRoleName(string $name): string
    {
        $name = strtolower(trim($name));
        $name = preg_replace('/\s+/', '_', $name) ?? $name;

        return $name;
    }

    private function normalizeDisplayName(string $name): string
    {
        return trim($name);
    }

    /**
     * Lấy tất cả roles (alias cho getAll)
     */
    public function getAllRoles(Request $req, Response $res)
    {
        return $this->getAll($req, $res);
    }

    /**
     * Lấy roles của user
     */
    public function getUserRoles(int $userId): void
    {
        try {
            $roles = $this->roleModel->getUserRoles($userId);

            $this->jsonResponse([
                'success' => true,
                'data' => $roles
            ]);
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to fetch user roles: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Gán roles cho user
     */
    public function assignRolesToUser(int $userId): void
    {
        try {
            $data = $this->request->json();
            $roleIds = $data['role_ids'] ?? [];

            if (empty($roleIds)) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Role IDs are required'
                ], 400);
                return;
            }

            $success = $this->roleModel->assignRolesToUser($userId, $roleIds);

            if ($success) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Roles assigned successfully'
                ]);
            } else {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Failed to assign roles'
                ], 500);
            }
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to assign roles: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Xóa role khỏi user
     */
    public function removeRoleFromUser(int $userId, int $roleId): void
    {
        try {
            $success = $this->roleModel->removeRoleFromUser($userId, $roleId);

            if ($success) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Role removed from user successfully'
                ]);
            } else {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Failed to remove role from user'
                ], 500);
            }
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to remove role from user: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Tìm kiếm roles
     */
    public function search(): void
    {
        try {
            $query = $_GET['q'] ?? '';
            $limit = (int) ($_GET['limit'] ?? 10);

            if (empty($query)) {
                $this->jsonResponse([
                    'success' => true,
                    'data' => []
                ]);
                return;
            }

            $filters = ['search' => $query];
            $roles = $this->roleModel->getAllWithPagination($filters, $limit, 0);

            $this->jsonResponse([
                'success' => true,
                'data' => $roles
            ]);
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to search roles: ' . $e->getMessage()
            ], 500);
        }
    }

    // Helper methods
    /**
     * @param bool $forPanel Admin panel API requires display_name; legacy /api/v1 may omit it.
     */
    private function validateRoleData(array $data, bool $forPanel = false): array
    {
        $errors = [];

        $roleName = isset($data['role_name']) ? trim((string) $data['role_name']) : '';
        if ($roleName === '') {
            $errors['role_name'] = 'Role name is required';
        } elseif (strlen($roleName) > 50) {
            $errors['role_name'] = 'Role name must be less than 50 characters';
        }

        $displayRaw = isset($data['display_name']) ? trim((string) $data['display_name']) : '';
        if ($forPanel) {
            if ($displayRaw === '') {
                $errors['display_name'] = 'Display name is required';
            } elseif (mb_strlen($displayRaw) > 255) {
                $errors['display_name'] = 'Display name must be at most 255 characters';
            }
        } elseif ($displayRaw !== '' && mb_strlen($displayRaw) > 255) {
            $errors['display_name'] = 'Display name must be at most 255 characters';
        }

        return $errors;
    }
}
