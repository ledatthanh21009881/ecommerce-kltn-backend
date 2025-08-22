<?php
namespace App\Core;

class Controller 
{ 
    protected Container $container; 
    
    public function __construct(Container $c)
    { 
        $this->container = $c; 
    }
    
    protected function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}
