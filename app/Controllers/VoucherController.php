<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Controller, Request, Response, Container};
use App\Domain\Vouchers\{Voucher, VoucherRepository};
use App\Support\ResponseHelper;
use Exception;

class VoucherController extends Controller 
{
    private VoucherRepository $voucherRepository;

    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->voucherRepository = new VoucherRepository($container->database()->getConnection());
    }

    /**
     * Get all vouchers with pagination and search
     */
    public function index(Request $req, Response $res)
    {
        try {
            $page = (int)($req->query('page') ?? 1);
            $limit = (int)($req->query('limit') ?? 10);
            $search = $req->query('search', '');
            
            $result = $this->voucherRepository->findAll($page, $limit, $search);
            
            // Convert Voucher objects to arrays
            $vouchers = array_map(fn($v) => $v->toArray(), $result['items']);
            
            return $res->json(ResponseHelper::paginated($vouchers, $result['total'], $limit, $page));
            
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to get vouchers: ' . $e->getMessage()));
        }
    }

    /**
     * Get voucher by ID
     */
    public function show(Request $req, Response $res)
    {
        try {
            $id = (int)$req->getAttribute('id');
            
            $voucher = $this->voucherRepository->findById($id);
            if (!$voucher) {
                return $res->json(ResponseHelper::notFound('Voucher not found'));
            }
            
            return $res->json(ResponseHelper::success($voucher->toArray()));
            
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to get voucher: ' . $e->getMessage()));
        }
    }

    /**
     * Create new voucher
     */
    public function store(Request $req, Response $res)
    {
        try {
            $data = $req->json();
            
            // Validate required fields
            $required = ['code', 'discount_amount', 'discount_type', 'start_date', 'end_date'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    return $res->json(ResponseHelper::error("Field '{$field}' is required"));
                }
            }
            
            // Validate discount type
            if (!in_array($data['discount_type'], ['percent', 'amount'])) {
                return $res->json(ResponseHelper::error("Discount type must be 'percent' or 'amount'"));
            }
            
            // Validate discount amount
            if ($data['discount_type'] === 'percent' && ($data['discount_amount'] < 0 || $data['discount_amount'] > 100)) {
                return $res->json(ResponseHelper::error("Percent discount must be between 0 and 100"));
            }
            
            if ($data['discount_type'] === 'amount' && $data['discount_amount'] < 0) {
                return $res->json(ResponseHelper::error("Amount discount cannot be negative"));
            }
            
            // Check if code already exists
            $existingVoucher = $this->voucherRepository->findByCode($data['code']);
            if ($existingVoucher) {
                return $res->json(ResponseHelper::error("Voucher code already exists"));
            }
            
            // Create voucher
            $voucher = new Voucher(
                voucher_id: null,
                code: $data['code'],
                discount_amount: (float)$data['discount_amount'],
                discount_type: $data['discount_type'],
                max_usage: $data['max_usage'] ?? null,
                usage_per_user: $data['usage_per_user'] ?? null,
                start_date: $data['start_date'],
                end_date: $data['end_date'],
                version: 0,
                min_order_total: (float)($data['min_order_total'] ?? 0),
                status: $data['status'] ?? 'active'
            );
            
            $voucherId = $this->voucherRepository->create($voucher);
            
            return $res->json(ResponseHelper::success(['voucher_id' => $voucherId], 'Voucher created successfully'));
            
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to create voucher: ' . $e->getMessage()));
        }
    }

    /**
     * Update voucher
     */
    public function update(Request $req, Response $res)
    {
        try {
            $id = (int)$req->getAttribute('id');
            $data = $req->json();
            
            // Check if voucher exists
            $existingVoucher = $this->voucherRepository->findById($id);
            if (!$existingVoucher) {
                return $res->json(ResponseHelper::notFound('Voucher not found'));
            }
            
            // Validate discount type if provided
            if (isset($data['discount_type']) && !in_array($data['discount_type'], ['percent', 'amount'])) {
                return $res->json(ResponseHelper::error("Discount type must be 'percent' or 'amount'"));
            }
            
            // Validate discount amount if provided
            if (isset($data['discount_amount'])) {
                $discountType = $data['discount_type'] ?? $existingVoucher->discount_type;
                if ($discountType === 'percent' && ($data['discount_amount'] < 0 || $data['discount_amount'] > 100)) {
                    return $res->json(ResponseHelper::error("Percent discount must be between 0 and 100"));
                }
                if ($discountType === 'amount' && $data['discount_amount'] < 0) {
                    return $res->json(ResponseHelper::error("Amount discount cannot be negative"));
                }
            }
            
            // Check if code already exists (if changing code)
            if (isset($data['code']) && $data['code'] !== $existingVoucher->code) {
                $codeExists = $this->voucherRepository->findByCode($data['code']);
                if ($codeExists) {
                    return $res->json(ResponseHelper::error("Voucher code already exists"));
                }
            }
            
            // Update voucher
            $updatedVoucher = new Voucher(
                voucher_id: $id,
                code: $data['code'] ?? $existingVoucher->code,
                discount_amount: (float)($data['discount_amount'] ?? $existingVoucher->discount_amount),
                discount_type: $data['discount_type'] ?? $existingVoucher->discount_type,
                max_usage: $data['max_usage'] ?? $existingVoucher->max_usage,
                usage_per_user: $data['usage_per_user'] ?? $existingVoucher->usage_per_user,
                start_date: $data['start_date'] ?? $existingVoucher->start_date,
                end_date: $data['end_date'] ?? $existingVoucher->end_date,
                version: $existingVoucher->version,
                min_order_total: (float)($data['min_order_total'] ?? $existingVoucher->min_order_total),
                status: $data['status'] ?? $existingVoucher->status
            );
            
            $success = $this->voucherRepository->update($updatedVoucher);
            
            if ($success) {
                return $res->json(ResponseHelper::success(null, 'Voucher updated successfully'));
            } else {
                return $res->json(ResponseHelper::error('Failed to update voucher'));
            }
            
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to update voucher: ' . $e->getMessage()));
        }
    }

    /**
     * Delete voucher
     */
    public function destroy(Request $req, Response $res)
    {
        try {
            $id = (int)$req->getAttribute('id');
            
            // Check if voucher exists
            $voucher = $this->voucherRepository->findById($id);
            if (!$voucher) {
                return $res->json(ResponseHelper::notFound('Voucher not found'));
            }
            
            $success = $this->voucherRepository->delete($id);
            
            if ($success) {
                return $res->json(ResponseHelper::success(null, 'Voucher deleted successfully'));
            } else {
                return $res->json(ResponseHelper::error('Failed to delete voucher'));
            }
            
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to delete voucher: ' . $e->getMessage()));
        }
    }

    /**
     * Get voucher statistics
     */
    public function stats(Request $req, Response $res)
    {
        try {
            $stats = $this->voucherRepository->getStats();
            return $res->json(ResponseHelper::success($stats));
            
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to get voucher stats: ' . $e->getMessage()));
        }
    }

    /**
     * Get voucher usage statistics
     */
    public function usageStats(Request $req, Response $res)
    {
        try {
            $id = (int)$req->getAttribute('id');
            
            // Check if voucher exists
            $voucher = $this->voucherRepository->findById($id);
            if (!$voucher) {
                return $res->json(ResponseHelper::notFound('Voucher not found'));
            }
            
            $usageStats = $this->voucherRepository->getUsageStats($id);
            return $res->json(ResponseHelper::success($usageStats));
            
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to get usage stats: ' . $e->getMessage()));
        }
    }

    /**
     * Toggle voucher status
     */
    public function toggleStatus(Request $req, Response $res)
    {
        try {
            $id = (int)$req->getAttribute('id');
            
            // Check if voucher exists
            $voucher = $this->voucherRepository->findById($id);
            if (!$voucher) {
                return $res->json(ResponseHelper::notFound('Voucher not found'));
            }
            
            // Toggle status
            $newStatus = $voucher->status === 'active' ? 'inactive' : 'active';
            
            $updatedVoucher = new Voucher(
                voucher_id: $id,
                code: $voucher->code,
                discount_amount: $voucher->discount_amount,
                discount_type: $voucher->discount_type,
                max_usage: $voucher->max_usage,
                usage_per_user: $voucher->usage_per_user,
                start_date: $voucher->start_date,
                end_date: $voucher->end_date,
                version: $voucher->version,
                min_order_total: $voucher->min_order_total,
                status: $newStatus
            );
            
            $success = $this->voucherRepository->update($updatedVoucher);
            
            if ($success) {
                $action = $newStatus === 'active' ? 'activated' : 'deactivated';
                return $res->json(ResponseHelper::success(null, "Voucher {$action} successfully"));
            } else {
                return $res->json(ResponseHelper::error('Failed to update voucher status'));
            }
            
        } catch (Exception $e) {
            return $res->json(ResponseHelper::serverError('Failed to toggle voucher status: ' . $e->getMessage()));
        }
    }
}
