<?php
declare(strict_types=1);

namespace App\Support;

class ResponseHelper
{
    public static function success(mixed $data = null, string $message = 'Success', int $statusCode = 200): array
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        
        $response = [
            'success' => true,
            'message' => $message,
            'status_code' => $statusCode,
        ];
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        return $response;
    }
    
    public static function error(string $message = 'Error', int $statusCode = 400, mixed $errors = null): array
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        
        $response = [
            'success' => false,
            'message' => $message,
            'status_code' => $statusCode,
        ];
        
        if ($errors !== null) {
            $response['errors'] = $errors;
        }
        
        return $response;
    }
    
    public static function paginated(array $data, int $total, int $perPage, int $currentPage): array
    {
        $lastPage = (int)ceil($total / $perPage);
        
        return self::success([
            'items' => $data,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $currentPage,
                'last_page' => $lastPage,
                'from' => ($currentPage - 1) * $perPage + 1,
                'to' => min($currentPage * $perPage, $total),
            ]
        ]);
    }
    
    public static function unauthorized(string $message = 'Unauthorized'): array
    {
        return self::error($message, 401);
    }
    
    public static function forbidden(string $message = 'Forbidden'): array
    {
        return self::error($message, 403);
    }
    
    public static function notFound(string $message = 'Not Found'): array
    {
        return self::error($message, 404);
    }
    
    public static function validationError(array $errors, string $message = 'Validation Error'): array
    {
        return self::error($message, 422, $errors);
    }
    
    public static function serverError(string $message = 'Internal Server Error'): array
    {
        return self::error($message, 500);
    }
    
    public static function json(array $data): void
    {
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
}
