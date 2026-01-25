<?php
namespace App\Core;
class Response {
    private int $statusCode = 200;
    
    public function status(int $code): self {
        $this->statusCode = $code;
        return $this;
    }
    
    public function json($data, int $status=null): void {
        $statusCode = $status ?? $this->statusCode;
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }
    
    public function text(string $text, int $status=null): void {
        $statusCode = $status ?? $this->statusCode;
        http_response_code($statusCode);
        header('Content-Type: text/plain');
        echo $text;
    }
    
    public function setHeader(string $name, string $value): void {
        header("$name: $value");
    }
}
