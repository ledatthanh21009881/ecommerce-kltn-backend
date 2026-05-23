<?php
namespace App\Core;
class Response {
    private int $statusCode = 200;
    
    public function status(int $code): self {
        $this->statusCode = $code;
        return $this;
    }
    
    public function json($data, int $status=null): void {
        // Ưu tiên $status truyền trực tiếp; nếu không, đọc status_code trong body
        // (đa số call site dùng ResponseHelper trả mảng có status_code nhưng quên truyền vào json()).
        $statusCode = $status
            ?? (is_array($data) && isset($data['status_code']) && is_int($data['status_code'])
                ? $data['status_code']
                : $this->statusCode);
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
