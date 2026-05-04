<?php
namespace App\Core;
class Response {
    private int $statusCode = 200;
    
    public function status(int $code): self {
        $this->statusCode = $code;
        return $this;
    }
    
    public function json($data, int $status = null): void
    {
        // Nhiều controller chỉ truyền mảng ResponseHelper (có status_code trong body) mà không truyền $status —
        // nếu luôn 200 thì client dùng response.ok sẽ báo thành công dù success: false (vd. validation 422).
        $statusCode = $status;
        if ($statusCode === null && is_array($data) && isset($data['status_code'])) {
            $statusCode = (int) $data['status_code'];
        }
        if ($statusCode === null || $statusCode < 100 || $statusCode > 599) {
            $statusCode = $this->statusCode;
        }
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
