<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Domain\Customers\Customer;
use App\Support\JWT;
use Exception;

class CustomerController extends Controller
{
    private Customer $customerModel;
    private JWT $jwt;

    public function __construct()
    {
        parent::__construct();
        $this->customerModel = new Customer();
        $this->jwt = new JWT();
    }

    /**
     * Get all customers with pagination and filters
     */
    public function index(): void
    {
        try {
            $page = (int) ($_GET['page'] ?? 1);
            $limit = (int) ($_GET['limit'] ?? 20);
            $offset = ($page - 1) * $limit;

            $filters = [
                'search' => $_GET['search'] ?? null,
                'status' => $_GET['status'] ?? null,
                'gender' => $_GET['gender'] ?? null,
                'min_orders' => $_GET['min_orders'] ?? null,
                'min_points' => $_GET['min_points'] ?? null
            ];

            // Remove null values
            $filters = array_filter($filters, function($value) {
                return $value !== null && $value !== '';
            });

            $customers = $this->customerModel->getAllWithDetails($filters, $limit, $offset);
            $total = $this->customerModel->getCount($filters);

            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'customers' => $customers,
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
                'message' => 'Failed to fetch customers: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a single customer by ID
     */
    public function show(int $id): void
    {
        try {
            $customer = $this->customerModel->findById($id);
            
            if (!$customer) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Customer not found'
                ], 404);
                return;
            }

            // Get additional customer data
            $customer['orders'] = $this->customerModel->getCustomerOrders($id, 10, 0);
            $customer['addresses'] = $this->customerModel->getCustomerAddresses($id);

            $this->jsonResponse([
                'success' => true,
                'data' => $customer
            ]);
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to fetch customer: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new customer
     */
    public function store(): void
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            // Validate required fields
            $requiredFields = ['first_name', 'last_name', 'email', 'password'];
            foreach ($requiredFields as $field) {
                if (empty($input[$field])) {
                    $this->jsonResponse([
                        'success' => false,
                        'message' => "Field '$field' is required"
                    ], 400);
                    return;
                }
            }

            // Check if email already exists
            $existingCustomer = $this->customerModel->findByEmail($input['email']);
            if ($existingCustomer) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Email already exists'
                ], 400);
                return;
            }

            // Validate email format
            if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Invalid email format'
                ], 400);
                return;
            }

            // Validate password strength
            if (strlen($input['password']) < 6) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Password must be at least 6 characters long'
                ], 400);
                return;
            }

            $customerId = $this->customerModel->create($input);
            $customer = $this->customerModel->findById($customerId);

            $this->jsonResponse([
                'success' => true,
                'message' => 'Customer created successfully',
                'data' => $customer
            ], 201);
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to create customer: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an existing customer
     */
    public function update(int $id): void
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            // Check if customer exists
            $existingCustomer = $this->customerModel->findById($id);
            if (!$existingCustomer) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Customer not found'
                ], 404);
                return;
            }

            // If email is being updated, check if it's already taken
            if (isset($input['email']) && $input['email'] !== $existingCustomer['email']) {
                $customerWithEmail = $this->customerModel->findByEmail($input['email']);
                if ($customerWithEmail && $customerWithEmail['user_id'] != $id) {
                    $this->jsonResponse([
                        'success' => false,
                        'message' => 'Email already exists'
                    ], 400);
                    return;
                }

                // Validate email format
                if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
                    $this->jsonResponse([
                        'success' => false,
                        'message' => 'Invalid email format'
                    ], 400);
                    return;
                }
            }

            $success = $this->customerModel->update($id, $input);
            
            if ($success) {
                $updatedCustomer = $this->customerModel->findById($id);
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Customer updated successfully',
                    'data' => $updatedCustomer
                ]);
            } else {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Failed to update customer'
                ], 500);
            }
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to update customer: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a customer
     */
    public function destroy(int $id): void
    {
        try {
            // Check if customer exists
            $existingCustomer = $this->customerModel->findById($id);
            if (!$existingCustomer) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Customer not found'
                ], 404);
                return;
            }

            $success = $this->customerModel->delete($id);
            
            if ($success) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Customer deleted successfully'
                ]);
            } else {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Failed to delete customer'
                ], 500);
            }
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to delete customer: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get customer orders
     */
    public function orders(int $id): void
    {
        try {
            $page = (int) ($_GET['page'] ?? 1);
            $limit = (int) ($_GET['limit'] ?? 10);
            $offset = ($page - 1) * $limit;

            // Check if customer exists
            $existingCustomer = $this->customerModel->findById($id);
            if (!$existingCustomer) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Customer not found'
                ], 404);
                return;
            }

            $orders = $this->customerModel->getCustomerOrders($id, $limit, $offset);

            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'orders' => $orders,
                    'pagination' => [
                        'page' => $page,
                        'limit' => $limit
                    ]
                ]
            ]);
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to fetch customer orders: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get customer addresses
     */
    public function addresses(int $id): void
    {
        try {
            // Check if customer exists
            $existingCustomer = $this->customerModel->findById($id);
            if (!$existingCustomer) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Customer not found'
                ], 404);
                return;
            }

            $addresses = $this->customerModel->getCustomerAddresses($id);

            $this->jsonResponse([
                'success' => true,
                'data' => $addresses
            ]);
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to fetch customer addresses: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update customer loyalty points
     */
    public function updateLoyaltyPoints(int $id): void
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['points']) || !is_numeric($input['points'])) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Points field is required and must be numeric'
                ], 400);
                return;
            }

            // Check if customer exists
            $existingCustomer = $this->customerModel->findById($id);
            if (!$existingCustomer) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Customer not found'
                ], 404);
                return;
            }

            $success = $this->customerModel->updateLoyaltyPoints($id, (int) $input['points']);
            
            if ($success) {
                $updatedCustomer = $this->customerModel->findById($id);
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Loyalty points updated successfully',
                    'data' => [
                        'loyalty_points' => $updatedCustomer['loyalty_points']
                    ]
                ]);
            } else {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Failed to update loyalty points'
                ], 500);
            }
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to update loyalty points: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get top customers
     */
    public function topCustomers(): void
    {
        try {
            $limit = (int) ($_GET['limit'] ?? 10);
            $customers = $this->customerModel->getTopCustomers($limit);

            $this->jsonResponse([
                'success' => true,
                'data' => $customers
            ]);
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to fetch top customers: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search customers
     */
    public function search(): void
    {
        try {
            $query = $_GET['q'] ?? '';
            
            if (empty($query)) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Search query is required'
                ], 400);
                return;
            }

            $filters = ['search' => $query];
            $customers = $this->customerModel->getAllWithDetails($filters, 20, 0);

            $this->jsonResponse([
                'success' => true,
                'data' => $customers
            ]);
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to search customers: ' . $e->getMessage()
            ], 500);
        }
    }
}
