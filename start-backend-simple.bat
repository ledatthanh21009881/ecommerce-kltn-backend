@echo off
echo ========================================
echo    KHOI DONG BACKEND (PHP)
echo ========================================
echo.

cd /d "D:\Khoa_Luan_Tan_Phuc\code\ecommerce"
echo [INFO] Dang khoi dong Backend tai: %CD%
echo [INFO] Server se chay tai: http://localhost:8000
echo.

php -S localhost:8000 -t public public/router.php

echo.
echo [INFO] Backend da dung.
pause
