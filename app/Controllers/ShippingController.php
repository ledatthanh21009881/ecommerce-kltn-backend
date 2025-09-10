<?php

namespace App\Controllers;

use App\Core\{Controller, Request, Response, Container};
use App\Repositories\ShippingRepository;
use App\Support\JWT;
use App\Models\ActivityLog;

class ShippingController extends Controller
{
    private $shippingRepository;
    private $jwt;

    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->shippingRepository = new ShippingRepository($container->database()->getConnection());
        // Load JWT secret from config
        $config = require __DIR__ . '/../config/app.php';
        $this->jwt = new JWT($config['jwt']['secret']);
    }

    /**
     * Lấy danh sách shipping methods
     * GET /api/backend/v1/shipping
     */
    public function index(Request $req, Response $res)
    {
        try {
            // Lấy query parameters
            $active = $req->query('active');
            $sortBy = $req->query('sort_by', 'fee');
            $sortOrder = $req->query('sort_order', 'asc');

            $methods = $this->shippingRepository->getAll($active, $sortBy, $sortOrder);

            return $res->json([
                'success' => true,
                'message' => 'Shipping methods retrieved successfully',
                'data' => $methods
            ], 200);

        } catch (\Exception $e) {
            return $res->json([
                'success' => false,
                'message' => 'Failed to retrieve shipping methods: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lấy chi tiết một shipping method
     * GET /api/backend/v1/shipping/{id}
     */
    public function show(Request $req, Response $res)
    {
        try {
            $id = $req->params('id');
            $method = $this->shippingRepository->getById($id);

            if (!$method) {
                return $res->json([
                    'success' => false,
                    'message' => 'Shipping method not found'
                ], 404);
            }

            return $res->json([
                'success' => true,
                'message' => 'Shipping method retrieved successfully',
                'data' => $method
            ], 200);

        } catch (\Exception $e) {
            return $res->json([
                'success' => false,
                'message' => 'Failed to retrieve shipping method: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Tạo shipping method mới
     * POST /api/backend/v1/shipping
     */
    public function store()
    {
        try {
            // Lấy token từ header
            $token = $this->getBearerToken();
            if (!$token) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Authorization token required'
                ], 401);
                return;
            }

            // Verify token
            $payload = $this->jwt->decode($token);
            if (!$payload) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Invalid or expired token'
                ], 401);
                return;
            }

            // Lấy dữ liệu từ request
            $input = json_decode(file_get_contents('php://input'), true);

            // Validate input
            $validation = $this->validateShippingMethod($input);
            if (!$validation['valid']) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => $validation['message']
                ], 400);
                return;
            }

            // Kiểm tra tên trùng lặp
            if ($this->shippingRepository->nameExists($input['name'])) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Shipping method name already exists'
                ], 400);
                return;
            }

            // Tạo shipping method
            $methodId = $this->shippingRepository->create($input);

            if ($methodId) {
                // Log activity
                $this->logActivity($payload['user_id'], 'create', 'shipping_method', $methodId, $input);

                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Shipping method created successfully',
                    'data' => ['shipping_method_id' => $methodId]
                ], 201);
            } else {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Failed to create shipping method'
                ], 500);
            }

        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to create shipping method: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cập nhật shipping method
     * PUT /api/backend/v1/shipping/{id}
     */
    public function update($id)
    {
        try {
            // Lấy token từ header
            $token = $this->getBearerToken();
            if (!$token) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Authorization token required'
                ], 401);
                return;
            }

            // Verify token
            $payload = $this->jwt->decode($token);
            if (!$payload) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Invalid or expired token'
                ], 401);
                return;
            }

            // Kiểm tra shipping method tồn tại
            $existingMethod = $this->shippingRepository->getById($id);
            if (!$existingMethod) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Shipping method not found'
                ], 404);
                return;
            }

            // Lấy dữ liệu từ request
            $input = json_decode(file_get_contents('php://input'), true);

            // Validate input
            $validation = $this->validateShippingMethod($input, $id);
            if (!$validation['valid']) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => $validation['message']
                ], 400);
                return;
            }

            // Kiểm tra tên trùng lặp (trừ chính nó)
            if ($this->shippingRepository->nameExists($input['name'], $id)) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Shipping method name already exists'
                ], 400);
                return;
            }

            // Cập nhật shipping method
            $success = $this->shippingRepository->update($id, $input);

            if ($success) {
                // Log activity
                $this->logActivity($payload['user_id'], 'update', 'shipping_method', $id, $input);

                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Shipping method updated successfully'
                ], 200);
            } else {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Failed to update shipping method'
                ], 500);
            }

        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to update shipping method: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Xóa shipping method
     * DELETE /api/backend/v1/shipping/{id}
     */
    public function destroy($id)
    {
        try {
            // Lấy token từ header
            $token = $this->getBearerToken();
            if (!$token) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Authorization token required'
                ], 401);
                return;
            }

            // Verify token
            $payload = $this->jwt->decode($token);
            if (!$payload) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Invalid or expired token'
                ], 401);
                return;
            }

            // Kiểm tra shipping method tồn tại
            $existingMethod = $this->shippingRepository->getById($id);
            if (!$existingMethod) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Shipping method not found'
                ], 404);
                return;
            }

            // Kiểm tra xem có đang được sử dụng trong orders không
            if ($this->shippingRepository->isUsedInOrders($id)) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Cannot delete shipping method that is being used in orders'
                ], 400);
                return;
            }

            // Xóa shipping method
            $success = $this->shippingRepository->delete($id);

            if ($success) {
                // Log activity
                $this->logActivity($payload['user_id'], 'delete', 'shipping_method', $id, $existingMethod);

                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Shipping method deleted successfully'
                ], 200);
            } else {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Failed to delete shipping method'
                ], 500);
            }

        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to delete shipping method: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle trạng thái active/inactive
     * PATCH /api/backend/v1/shipping/{id}/toggle
     */
    public function toggleStatus($id)
    {
        try {
            // Lấy token từ header
            $token = $this->getBearerToken();
            if (!$token) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Authorization token required'
                ], 401);
                return;
            }

            // Verify token
            $payload = $this->jwt->decode($token);
            if (!$payload) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Invalid or expired token'
                ], 401);
                return;
            }

            // Kiểm tra shipping method tồn tại
            $existingMethod = $this->shippingRepository->getById($id);
            if (!$existingMethod) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Shipping method not found'
                ], 404);
                return;
            }

            // Toggle status
            $newStatus = $existingMethod['is_active'] ? 0 : 1;
            $success = $this->shippingRepository->updateStatus($id, $newStatus);

            if ($success) {
                // Log activity
                $this->logActivity($payload['user_id'], 'update', 'shipping_method', $id, [
                    'is_active' => $newStatus,
                    'action' => 'toggle_status'
                ]);

                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Shipping method status updated successfully',
                    'data' => ['is_active' => $newStatus]
                ], 200);
            } else {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Failed to update shipping method status'
                ], 500);
            }

        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to update shipping method status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validate shipping method data
     */
    private function validateShippingMethod($data, $excludeId = null)
    {
        if (!isset($data['name']) || empty(trim($data['name']))) {
            return ['valid' => false, 'message' => 'Name is required'];
        }

        if (!isset($data['fee']) || !is_numeric($data['fee'])) {
            return ['valid' => false, 'message' => 'Fee must be a valid number'];
        }

        if ($data['fee'] < 0) {
            return ['valid' => false, 'message' => 'Invalid fee - fee cannot be negative'];
        }

        if (!isset($data['estimated_days']) || !is_numeric($data['estimated_days'])) {
            return ['valid' => false, 'message' => 'Estimated days must be a valid number'];
        }

        if ($data['estimated_days'] < 1) {
            return ['valid' => false, 'message' => 'Estimated days must be at least 1'];
        }

        return ['valid' => true];
    }

    /**
     * Lấy Bearer token từ header
     */
    private function getBearerToken()
    {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        
        if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            return $matches[1];
        }
        
        return null;
    }

    /**
     * Log activity
     */
    private function logActivity($userId, $action, $entityType, $entityId, $data = null)
    {
        try {
            $activityLog = new ActivityLog();
            $activityLog->log($userId, $action, $entityType, $entityId, $data);
        } catch (\Exception $e) {
            // Log error but don't fail the main operation
            error_log('Failed to log activity: ' . $e->getMessage());
        }
    }
}
