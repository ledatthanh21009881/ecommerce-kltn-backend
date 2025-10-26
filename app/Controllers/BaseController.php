<?php

/**
 * BaseController
 * 
 * Controller cơ sở cho tất cả các controllers trong hệ thống.
 * Cung cấp các phương thức chung để xử lý request/response và format JSON response.
 * 
 * Chức năng:
 * - Xử lý HTTP request/response cơ bản
 * - Format JSON response chuẩn (success/error)
 * - Dependency injection container
 * 
 * Tái sử dụng:
 * - Tất cả Controllers khác extend từ BaseController này
 * - Sử dụng methods success() và error() để trả về response chuẩn
 * - Override các methods này nếu cần custom format
 * 
 * @package App\Controllers
 * @author ShopSwift Team
 */

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class BaseController
{
    /** @var ServerRequestInterface HTTP request instance */
    protected $request;
    
    /** @var ResponseInterface HTTP response instance */
    protected $response;
    
    /** @var mixed Dependency injection container */
    protected $container;

    /**
     * Constructor - Khởi tạo BaseController với container
     * 
     * @param mixed $container Dependency injection container (optional)
     */
    public function __construct($container = null)
    {
        $this->container = $container;
    }

    /**
     * Set HTTP request instance
     * 
     * @param ServerRequestInterface $request
     * @return self Fluent interface
     */
    public function setRequest(ServerRequestInterface $request)
    {
        $this->request = $request;
        return $this;
    }

    /**
     * Set HTTP response instance
     * 
     * @param ResponseInterface $response
     * @return self Fluent interface
     */
    public function setResponse(ResponseInterface $response)
    {
        $this->response = $response;
        return $this;
    }

    /**
     * Trả về JSON response
     * 
     * Tái sử dụng: Gọi từ success() và error() methods
     * 
     * @param mixed $data Data to encode as JSON
     * @param int $statusCode HTTP status code (default: 200)
     * @return ResponseInterface
     */
    protected function json($data, $statusCode = 200)
    {
        $this->response->getBody()->write(json_encode($data));
        return $this->response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($statusCode);
    }

    /**
     * Trả về success response chuẩn
     * 
     * Format: {
     *   "success": true,
     *   "message": "Success",
     *   "status_code": 200,
     *   "data": {...}
     * }
     * 
     * Tái sử dụng: Sử dụng trong tất cả Controllers khi operation thành công
     * 
     * @param mixed $data Response data (optional)
     * @param string $message Success message (default: 'Success')
     * @param int $statusCode HTTP status code (default: 200)
     * @return ResponseInterface
     */
    protected function success($data = null, $message = 'Success', $statusCode = 200)
    {
        return $this->json([
            'success' => true,
            'message' => $message,
            'status_code' => $statusCode,
            'data' => $data
        ], $statusCode);
    }

    /**
     * Trả về error response chuẩn
     * 
     * Format: {
     *   "success": false,
     *   "message": "Error",
     *   "status_code": 400,
     *   "errors": {...} (optional)
     * }
     * 
     * Tái sử dụng: Sử dụng trong tất cả Controllers khi có lỗi
     * 
     * @param string $message Error message (default: 'Error')
     * @param int $statusCode HTTP status code (default: 400)
     * @param mixed $errors Additional error details (optional)
     * @return ResponseInterface
     */
    protected function error($message = 'Error', $statusCode = 400, $errors = null)
    {
        $response = [
            'success' => false,
            'message' => $message,
            'status_code' => $statusCode
        ];

        if ($errors) {
            $response['errors'] = $errors;
        }

        return $this->json($response, $statusCode);
    }
}
