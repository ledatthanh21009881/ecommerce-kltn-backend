<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Controller, Request, Response, Container};
use App\Domain\Users\User;
use App\Domain\Roles\Role;
use App\Support\JWT;
use App\Support\ResponseHelper;
use Exception;

class UserController extends Controller
{
    private User $userModel;
    private Role $roleModel;
    private JWT $jwt;

    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->userModel = new User($container->database());
        $this->roleModel = new Role($container->database());
        $this->jwt = $container->jwt();
    }

    /**
     * Lấy danh sách users với phân trang và filter
     */
    public function index(Request $req, Response $res)
    {
        try {
            $page = (int) ($req->query('page') ?? 1);
            $limit = (int) ($req->query('limit') ?? 20);
            $offset = ($page - 1) * $limit;

            $filters = [
                'search' => $req->query('search') ?? null,
                'role' => $req->query('role') ?? null,
                'is_active' => isset($_GET['is_active']) ? (bool) $_GET['is_active'] : null
            ];

            // Loại bỏ các filter null/empty
            $filters = array_filter($filters, function($value) {
                return $value !== null && $value !== '';
            });

            $users = $this->userModel->getAllWithDetails($filters, $limit, $offset);
            $total = $this->userModel->getCount($filters);

            return $res->json(ResponseHelper::paginated($users, $total, $limit, $page));
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to fetch users: ' . $e->getMessage()));
        }
    }

    /**
     * Lấy chi tiết user
     */
    public function show(Request $req, Response $res)
    {
        try {
            $id = (int) $req->getAttribute('id');
            $user = $this->userModel->findByIdWithDetails($id);
            if (!$user) {
                return $res->json(ResponseHelper::notFound('User not found'));
            }

            return $res->json(ResponseHelper::success($user));
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to fetch user: ' . $e->getMessage()));
        }
    }

    /**
     * Tạo user mới
     */
    public function store(Request $req, Response $res)
    {
        try {
            $data = $req->json();

            // Validation
            $errors = $this->validateUserData($data);
            if (!empty($errors)) {
                return $res->json(ResponseHelper::error('Validation failed', $errors), 400);
            }

            // Kiểm tra email và account_name unique
            if ($this->isEmailExists($data['email'])) {
                return $res->json(ResponseHelper::error('Email already exists'), 400);
            }

            if ($this->isAccountNameExists($data['account_name'])) {
                return $res->json(ResponseHelper::error('Account name already exists'), 400);
            }

            $userId = $this->userModel->create($data);

            return $res->json(ResponseHelper::success(['user_id' => $userId], 'User created successfully'), 201);
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to create user: ' . $e->getMessage()));
        }
    }

    /**
     * Cập nhật user
     */
    public function update(Request $req, Response $res)
    {
        try {
            $id = (int) $req->getAttribute('id');
            $data = $req->json();

            // Validation
            $errors = $this->validateUserUpdateData($data);
            if (!empty($errors)) {
                return $res->json(ResponseHelper::error('Validation failed', $errors), 400);
            }

            $success = $this->userModel->update($id, $data);

            if ($success) {
                return $res->json(ResponseHelper::success(null, 'User updated successfully'));
            } else {
                return $res->json(ResponseHelper::notFound('User not found'));
            }
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to update user: ' . $e->getMessage()));
        }
    }

    /**
     * Xóa user
     */
    public function destroy(Request $req, Response $res)
    {
        try {
            $id = (int) $req->getAttribute('id');
            $success = $this->userModel->delete($id);

            if ($success) {
                return $res->json(ResponseHelper::success(null, 'User deleted successfully'));
            } else {
                return $res->json(ResponseHelper::notFound('User not found'));
            }
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to delete user: ' . $e->getMessage()));
        }
    }

    /**
     * Khóa/Mở khóa user
     */
    public function toggleLock(Request $req, Response $res)
    {
        try {
            $id = (int) $req->getAttribute('id');
            $data = $req->json();
            $lockedUntil = $data['locked_until'] ?? null;

            $success = $this->userModel->toggleLock($id, $lockedUntil);

            if ($success) {
                return $res->json(ResponseHelper::success(null, $lockedUntil ? 'User locked successfully' : 'User unlocked successfully'));
            } else {
                return $res->json(ResponseHelper::notFound('User not found'));
            }
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to toggle user lock: ' . $e->getMessage()));
        }
    }

    /**
     * Bật/Tắt 2FA
     */
    public function toggle2FA(int $id): void
    {
        try {
            $data = $this->request->json();
            $enabled = (bool) ($data['enabled'] ?? false);
            $secret = $data['secret'] ?? null;

            $success = $this->userModel->toggle2FA($id, $enabled, $secret);

            if ($success) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => $enabled ? '2FA enabled successfully' : '2FA disabled successfully'
                ]);
            } else {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to toggle 2FA: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lấy roles của user
     */
    public function getUserRoles(int $id): void
    {
        try {
            $roles = $this->userModel->getUserRoles($id);

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
    public function assignRoles(int $id): void
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

            $success = $this->userModel->assignRoles($id, $roleIds);

            if ($success) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Roles assigned successfully'
                ]);
            } else {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to assign roles: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lấy thống kê users
     */
    public function getStats(Request $req, Response $res)
    {
        try {
            $stats = $this->userModel->getStats();
            return $res->json(ResponseHelper::success($stats));
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to fetch user stats: ' . $e->getMessage()));
        }
    }

    /**
     * Tìm kiếm users
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
            $users = $this->userModel->getAllWithDetails($filters, $limit, 0);

            $this->jsonResponse([
                'success' => true,
                'data' => $users
            ]);
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to search users: ' . $e->getMessage()
            ], 500);
        }
    }

    // Helper methods
    private function validateUserData(array $data): array
    {
        $errors = [];

        if (empty($data['account_name'])) {
            $errors['account_name'] = 'Account name is required';
        }

        if (empty($data['password'])) {
            $errors['password'] = 'Password is required';
        } elseif (strlen($data['password']) < 6) {
            $errors['password'] = 'Password must be at least 6 characters';
        }

        if (empty($data['first_name'])) {
            $errors['first_name'] = 'First name is required';
        }

        if (empty($data['last_name'])) {
            $errors['last_name'] = 'Last name is required';
        }

        if (empty($data['email'])) {
            $errors['email'] = 'Email is required';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email format';
        }

        if (!empty($data['role_ids']) && !is_array($data['role_ids'])) {
            $errors['role_ids'] = 'Role IDs must be an array';
        }

        return $errors;
    }

    private function validateUserUpdateData(array $data): array
    {
        $errors = [];

        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email format';
        }

        if (!empty($data['role_ids']) && !is_array($data['role_ids'])) {
            $errors['role_ids'] = 'Role IDs must be an array';
        }

        return $errors;
    }

    private function isEmailExists(string $email): bool
    {
        $sql = "SELECT COUNT(*) as count FROM users WHERE email = ?";
        $stmt = $this->userModel->getDatabase()->getConnection()->prepare($sql);
        $stmt->execute([$email]);
        $result = $stmt->fetch();
        return (int) $result['count'] > 0;
    }

    private function isAccountNameExists(string $accountName): bool
    {
        $sql = "SELECT COUNT(*) as count FROM accounts WHERE account_name = ?";
        $stmt = $this->userModel->getDatabase()->getConnection()->prepare($sql);
        $stmt->execute([$accountName]);
        $result = $stmt->fetch();
        return (int) $result['count'] > 0;
    }
}
