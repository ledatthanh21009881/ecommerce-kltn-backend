<?php

namespace App\Controllers;

use App\Core\{Controller, Request, Response, Container};
use App\Repositories\ShippingRepository;

class ShippingController extends Controller
{
    private ShippingRepository $shippingRepository;

    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->shippingRepository = new ShippingRepository($container->database()->getConnection());
    }

    /**
     * GET /api/backend/v1/shipping
     */
    public function index(Request $req, Response $res)
    {
        try {
            $active = $req->query('active');
            $sortBy = $req->query('sort_by', 'fee');
            $sortOrder = $req->query('sort_order', 'asc');

            $methods = $this->shippingRepository->getAll($active, $sortBy, $sortOrder);

            return $res->json([
                'success' => true,
                'message' => 'Shipping methods retrieved successfully',
                'data' => $methods,
            ], 200);
        } catch (\Exception $e) {
            return $res->json([
                'success' => false,
                'message' => 'Failed to retrieve shipping methods: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/backend/v1/shipping/{id}
     */
    public function show(Request $req, Response $res)
    {
        try {
            $id = (int) $req->param('id');
            $method = $this->shippingRepository->getById($id);

            if (!$method) {
                return $res->json([
                    'success' => false,
                    'message' => 'Shipping method not found',
                ], 404);
            }

            return $res->json([
                'success' => true,
                'message' => 'Shipping method retrieved successfully',
                'data' => $method,
            ], 200);
        } catch (\Exception $e) {
            return $res->json([
                'success' => false,
                'message' => 'Failed to retrieve shipping method: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/backend/v1/shipping
     */
    public function store(Request $req, Response $res)
    {
        try {
            $input = $req->json() ?? [];

            $validation = $this->validateShippingMethod($input);
            if (!$validation['valid']) {
                return $res->json([
                    'success' => false,
                    'message' => $validation['message'],
                ], 400);
            }

            if ($this->shippingRepository->nameExists($input['name'])) {
                return $res->json([
                    'success' => false,
                    'message' => 'Shipping method name already exists',
                ], 400);
            }

            $methodId = $this->shippingRepository->create($input);

            if (!$methodId) {
                return $res->json([
                    'success' => false,
                    'message' => 'Failed to create shipping method',
                ], 500);
            }

            $this->logActivity($req, 'create', 'shipping_method', (int) $methodId, $input);

            return $res->json([
                'success' => true,
                'message' => 'Shipping method created successfully',
                'data' => ['shipping_method_id' => (int) $methodId],
            ], 201);
        } catch (\Throwable $e) {
            return $res->json([
                'success' => false,
                'message' => 'Failed to create shipping method: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * PUT /api/backend/v1/shipping/{id}
     */
    public function update(Request $req, Response $res)
    {
        try {
            $id = (int) $req->param('id');
            $input = $req->json() ?? [];

            $existingMethod = $this->shippingRepository->getById($id);
            if (!$existingMethod) {
                return $res->json([
                    'success' => false,
                    'message' => 'Shipping method not found',
                ], 404);
            }

            $validation = $this->validateShippingMethod($input, $id);
            if (!$validation['valid']) {
                return $res->json([
                    'success' => false,
                    'message' => $validation['message'],
                ], 400);
            }

            if ($this->shippingRepository->nameExists($input['name'], $id)) {
                return $res->json([
                    'success' => false,
                    'message' => 'Shipping method name already exists',
                ], 400);
            }

            $success = $this->shippingRepository->update($id, $input);

            if (!$success) {
                return $res->json([
                    'success' => false,
                    'message' => 'Failed to update shipping method',
                ], 500);
            }

            $this->logActivity($req, 'update', 'shipping_method', $id, $input);

            return $res->json([
                'success' => true,
                'message' => 'Shipping method updated successfully',
            ], 200);
        } catch (\Throwable $e) {
            return $res->json([
                'success' => false,
                'message' => 'Failed to update shipping method: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * DELETE /api/backend/v1/shipping/{id}
     */
    public function destroy(Request $req, Response $res)
    {
        try {
            $id = (int) $req->param('id');

            $existingMethod = $this->shippingRepository->getById($id);
            if (!$existingMethod) {
                return $res->json([
                    'success' => false,
                    'message' => 'Shipping method not found',
                ], 404);
            }

            if ($this->shippingRepository->isUsedInOrders($id)) {
                return $res->json([
                    'success' => false,
                    'message' => 'Cannot delete shipping method that is being used in orders',
                ], 400);
            }

            $success = $this->shippingRepository->delete($id);

            if (!$success) {
                return $res->json([
                    'success' => false,
                    'message' => 'Failed to delete shipping method',
                ], 500);
            }

            $this->logActivity($req, 'delete', 'shipping_method', $id, $existingMethod);

            return $res->json([
                'success' => true,
                'message' => 'Shipping method deleted successfully',
            ], 200);
        } catch (\Throwable $e) {
            return $res->json([
                'success' => false,
                'message' => 'Failed to delete shipping method: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * PATCH /api/backend/v1/shipping/{id}/toggle
     */
    public function toggleStatus(Request $req, Response $res)
    {
        try {
            $id = (int) $req->param('id');

            $existingMethod = $this->shippingRepository->getById($id);
            if (!$existingMethod) {
                return $res->json([
                    'success' => false,
                    'message' => 'Shipping method not found',
                ], 404);
            }

            $newStatus = $existingMethod['is_active'] ? 0 : 1;
            $success = $this->shippingRepository->updateStatus($id, $newStatus);

            if (!$success) {
                return $res->json([
                    'success' => false,
                    'message' => 'Failed to update shipping method status',
                ], 500);
            }

            $this->logActivity($req, 'update', 'shipping_method', $id, [
                'is_active' => $newStatus,
                'action' => 'toggle_status',
            ]);

            return $res->json([
                'success' => true,
                'message' => 'Shipping method status updated successfully',
                'data' => ['is_active' => $newStatus],
            ], 200);
        } catch (\Throwable $e) {
            return $res->json([
                'success' => false,
                'message' => 'Failed to update shipping method status: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function validateShippingMethod(array $data, $excludeId = null): array
    {
        if (!isset($data['name']) || empty(trim((string) $data['name']))) {
            return ['valid' => false, 'message' => 'Name is required'];
        }

        if (!isset($data['fee']) || !is_numeric($data['fee'])) {
            return ['valid' => false, 'message' => 'Fee must be a valid number'];
        }

        if ((float) $data['fee'] < 0) {
            return ['valid' => false, 'message' => 'Invalid fee - fee cannot be negative'];
        }

        if (!isset($data['estimated_days']) || !is_numeric($data['estimated_days'])) {
            return ['valid' => false, 'message' => 'Estimated days must be a valid number'];
        }

        if ((int) $data['estimated_days'] < 1) {
            return ['valid' => false, 'message' => 'Estimated days must be at least 1'];
        }

        return ['valid' => true];
    }

    private function resolveActorUserId(Request $req): ?int
    {
        $user = $req->getAttribute('user');
        if (is_array($user) && !empty($user['user_id'])) {
            return (int) $user['user_id'];
        }

        $payload = $req->getAttribute('token_payload');
        if (is_array($payload) && !empty($payload['user_id'])) {
            return (int) $payload['user_id'];
        }

        return null;
    }

    private function logActivity(Request $req, string $action, string $entityType, int $entityId, $data = null): void
    {
        // ActivityLog model is not available in this project — skip silently.
        $userId = $this->resolveActorUserId($req);
        if ($userId === null) {
            return;
        }
        error_log(sprintf(
            '[ShippingController] activity user=%d action=%s entity=%s:%d',
            $userId,
            $action,
            $entityType,
            $entityId
        ));
    }
}
