<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Controller, Request, Response, Container};
use App\Domain\Roles\Role;
use App\Support\ResponseHelper;
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
            $errors = $this->validateRoleData($data);
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
            $errors = $this->validateRoleData($data);
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

            return $res->json(ResponseHelper::success($roles));
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to fetch roles: ' . $e->getMessage()));
        }
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
    private function validateRoleData(array $data): array
    {
        $errors = [];

        if (empty($data['role_name'])) {
            $errors['role_name'] = 'Role name is required';
        } elseif (strlen($data['role_name']) > 50) {
            $errors['role_name'] = 'Role name must be less than 50 characters';
        }

        return $errors;
    }
}
