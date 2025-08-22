<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Container;
use App\Domain\Shippers\Shipper;
use App\Support\JWT;
use Exception;

class ShipperController extends Controller
{
    private Shipper $shipperModel;
    private JWT $jwt;

    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->shipperModel = new Shipper($container->get('database'));
        $this->jwt = new JWT($_ENV['JWT_SECRET'] ?? 'your-secret-key-here');
    }

    /**
     * Get all shippers with pagination and filters
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
                'is_available' => $_GET['is_available'] ?? null,
                'min_rating' => $_GET['min_rating'] ?? null,
                'min_deliveries' => $_GET['min_deliveries'] ?? null
            ];

            // Remove null values
            $filters = array_filter($filters, function($value) {
                return $value !== null && $value !== '';
            });

            $shippers = $this->shipperModel->getAllWithDetails($filters, $limit, $offset);
            $total = $this->shipperModel->getCount($filters);

            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'shippers' => $shippers,
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
                'message' => 'Failed to fetch shippers: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a single shipper by ID
     */
    public function show(int $id): void
    {
        try {
            $shipper = $this->shipperModel->findById($id);
            
            if (!$shipper) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Shipper not found'
                ], 404);
                return;
            }

            // Get additional shipper data
            $shipper['deliveries'] = $this->shipperModel->getShipperDeliveries($id, 10, 0);
            
            // Get performance data for last 30 days
            $endDate = date('Y-m-d');
            $startDate = date('Y-m-d', strtotime('-30 days'));
            $shipper['performance'] = $this->shipperModel->getShipperPerformance($id, $startDate, $endDate);

            $this->jsonResponse([
                'success' => true,
                'data' => $shipper
            ]);
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to fetch shipper: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new shipper
     */
    public function store(): void
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            // Validate required fields
            $requiredFields = ['first_name', 'last_name', 'email', 'password', 'vehicle_info'];
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
            $existingShipper = $this->shipperModel->findByEmail($input['email']);
            if ($existingShipper) {
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

            $shipperId = $this->shipperModel->create($input);
            $shipper = $this->shipperModel->findById($shipperId);

            $this->jsonResponse([
                'success' => true,
                'message' => 'Shipper created successfully',
                'data' => $shipper
            ], 201);
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to create shipper: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an existing shipper
     */
    public function update(int $id): void
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            // Check if shipper exists
            $existingShipper = $this->shipperModel->findById($id);
            if (!$existingShipper) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Shipper not found'
                ], 404);
                return;
            }

            // If email is being updated, check if it's already taken
            if (isset($input['email']) && $input['email'] !== $existingShipper['email']) {
                $shipperWithEmail = $this->shipperModel->findByEmail($input['email']);
                if ($shipperWithEmail && $shipperWithEmail['user_id'] != $id) {
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

            $success = $this->shipperModel->update($id, $input);
            
            if ($success) {
                $updatedShipper = $this->shipperModel->findById($id);
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Shipper updated successfully',
                    'data' => $updatedShipper
                ]);
            } else {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Failed to update shipper'
                ], 500);
            }
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to update shipper: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a shipper
     */
    public function destroy(int $id): void
    {
        try {
            // Check if shipper exists
            $existingShipper = $this->shipperModel->findById($id);
            if (!$existingShipper) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Shipper not found'
                ], 404);
                return;
            }

            $success = $this->shipperModel->delete($id);
            
            if ($success) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Shipper deleted successfully'
                ]);
            } else {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Failed to delete shipper'
                ], 500);
            }
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to delete shipper: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available shippers
     */
    public function available(): void
    {
        try {
            $shippers = $this->shipperModel->getAvailableShippers();

            $this->jsonResponse([
                'success' => true,
                'data' => $shippers
            ]);
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to fetch available shippers: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get shipper deliveries
     */
    public function deliveries(int $id): void
    {
        try {
            $page = (int) ($_GET['page'] ?? 1);
            $limit = (int) ($_GET['limit'] ?? 10);
            $offset = ($page - 1) * $limit;

            // Check if shipper exists
            $existingShipper = $this->shipperModel->findById($id);
            if (!$existingShipper) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Shipper not found'
                ], 404);
                return;
            }

            $deliveries = $this->shipperModel->getShipperDeliveries($id, $limit, $offset);

            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'deliveries' => $deliveries,
                    'pagination' => [
                        'page' => $page,
                        'limit' => $limit
                    ]
                ]
            ]);
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to fetch shipper deliveries: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get shipper performance
     */
    public function performance(int $id): void
    {
        try {
            $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
            $endDate = $_GET['end_date'] ?? date('Y-m-d');

            // Check if shipper exists
            $existingShipper = $this->shipperModel->findById($id);
            if (!$existingShipper) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Shipper not found'
                ], 404);
                return;
            }

            $performance = $this->shipperModel->getShipperPerformance($id, $startDate, $endDate);

            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'performance' => $performance,
                    'period' => [
                        'start_date' => $startDate,
                        'end_date' => $endDate
                    ]
                ]
            ]);
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to fetch shipper performance: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update shipper rating
     */
    public function updateRating(int $id): void
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['rating']) || !is_numeric($input['rating'])) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Rating field is required and must be numeric'
                ], 400);
                return;
            }

            $rating = (float) $input['rating'];
            if ($rating < 0 || $rating > 5) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Rating must be between 0 and 5'
                ], 400);
                return;
            }

            // Check if shipper exists
            $existingShipper = $this->shipperModel->findById($id);
            if (!$existingShipper) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Shipper not found'
                ], 404);
                return;
            }

            $success = $this->shipperModel->updateRating($id, $rating);
            
            if ($success) {
                $updatedShipper = $this->shipperModel->findById($id);
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Rating updated successfully',
                    'data' => [
                        'rating' => $updatedShipper['rating']
                    ]
                ]);
            } else {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Failed to update rating'
                ], 500);
            }
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to update rating: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update shipper availability
     */
    public function updateAvailability(int $id): void
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['is_available'])) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'is_available field is required'
                ], 400);
                return;
            }

            // Check if shipper exists
            $existingShipper = $this->shipperModel->findById($id);
            if (!$existingShipper) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Shipper not found'
                ], 404);
                return;
            }

            $isAvailable = (bool) $input['is_available'];
            $success = $this->shipperModel->setAvailability($id, $isAvailable);
            
            if ($success) {
                $updatedShipper = $this->shipperModel->findById($id);
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Availability updated successfully',
                    'data' => [
                        'is_available' => $updatedShipper['is_available']
                    ]
                ]);
            } else {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Failed to update availability'
                ], 500);
            }
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to update availability: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update delivery stats
     */
    public function updateDeliveryStats(int $id): void
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['total_delivered']) || !is_numeric($input['total_delivered'])) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'total_delivered field is required and must be numeric'
                ], 400);
                return;
            }

            if (!isset($input['on_time_percentage']) || !is_numeric($input['on_time_percentage'])) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'on_time_percentage field is required and must be numeric'
                ], 400);
                return;
            }

            $totalDelivered = (int) $input['total_delivered'];
            $onTimePercentage = (float) $input['on_time_percentage'];

            if ($onTimePercentage < 0 || $onTimePercentage > 100) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'on_time_percentage must be between 0 and 100'
                ], 400);
                return;
            }

            // Check if shipper exists
            $existingShipper = $this->shipperModel->findById($id);
            if (!$existingShipper) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Shipper not found'
                ], 404);
                return;
            }

            $success = $this->shipperModel->updateDeliveryStats($id, $totalDelivered, $onTimePercentage);
            
            if ($success) {
                $updatedShipper = $this->shipperModel->findById($id);
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Delivery stats updated successfully',
                    'data' => [
                        'total_delivered' => $updatedShipper['total_delivered'],
                        'on_time_delivery_pct' => $updatedShipper['on_time_delivery_pct'],
                        'last_delivery_at' => $updatedShipper['last_delivery_at']
                    ]
                ]);
            } else {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Failed to update delivery stats'
                ], 500);
            }
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to update delivery stats: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get top shippers
     */
    public function topShippers(): void
    {
        try {
            $limit = (int) ($_GET['limit'] ?? 10);
            $shippers = $this->shipperModel->getTopShippers($limit);

            $this->jsonResponse([
                'success' => true,
                'data' => $shippers
            ]);
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to fetch top shippers: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get shipper statistics
     */
    public function statistics(): void
    {
        try {
            $statistics = $this->shipperModel->getShipperStatistics();

            $this->jsonResponse([
                'success' => true,
                'data' => $statistics
            ]);
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to fetch shipper statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search shippers
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
            $shippers = $this->shipperModel->getAllWithDetails($filters, 20, 0);

            $this->jsonResponse([
                'success' => true,
                'data' => $shippers
            ]);
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to search shippers: ' . $e->getMessage()
            ], 500);
        }
    }
}
