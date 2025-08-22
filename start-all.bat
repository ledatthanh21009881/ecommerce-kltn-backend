@echo off
echo ========================================
echo    KHOI DONG HE THONG ECOMMERCE
echo ========================================
echo.

REM Kiem tra PHP
php --version >nul 2>&1
if %errorlevel% neq 0 (
    echo [ERROR] PHP khong duoc cai dat hoac khong co trong PATH!
    echo Hay cai dat PHP va them vao PATH
    pause
    exit /b 1
)

REM Kiem tra Node.js
node --version >nul 2>&1
if %errorlevel% neq 0 (
    echo [ERROR] Node.js khong duoc cai dat hoac khong co trong PATH!
    echo Hay cai dat Node.js va them vao PATH
    pause
    exit /b 1
)

echo [INFO] Dang khoi dong Backend (PHP)...
start "Backend Server" cmd /k "cd /d %~dp0ecommerce && php -S localhost:8000 -t public"

echo [INFO] Dang cho Backend khoi dong...
timeout /t 3 >nul

echo [INFO] Dang khoi dong Frontend (Next.js)...
start "Frontend Server" cmd /k "cd /d %~dp0web && npm run dev"

echo.
echo [SUCCESS] He thong da duoc khoi dong!
echo.
echo Backend:  http://localhost:8000
echo Frontend: http://localhost:3000
echo Admin:    http://localhost:3000/admin
echo.
echo Nhan phim bat ky de dong cua so nay...
pause >nul
