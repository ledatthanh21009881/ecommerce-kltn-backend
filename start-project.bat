yy@echo off
echo ========================================
echo    KHOI DONG TOAN BO DU AN SHOPSWIFT
echo ========================================
echo.

REM Tat tat ca cac process cu
echo [INFO] Dang tat cac process cu...
taskkill /f /im php.exe >nul 2>&1
taskkill /f /im node.exe >nul 2>&1

REM Kiem tra va tat port 8000
netstat -ano | findstr :8000 > nul
if %errorlevel% equ 0 (
    echo [INFO] Dang tat process tren port 8000...
    for /f "tokens=5" %%a in ('netstat -ano ^| findstr :8000') do (
        taskkill /f /pid %%a >nul 2>&1
    )
)

REM Kiem tra va tat port 3000
netstat -ano | findstr :3000 > nul
if %errorlevel% equ 0 (
    echo [INFO] Dang tat process tren port 3000...
    for /f "tokens=5" %%a in ('netstat -ano ^| findstr :3000') do (
        taskkill /f /pid %%a >nul 2>&1
    )
)

timeout /t 3 >nul

REM Kiem tra file .env
if not exist ".env" (
    echo [WARNING] Khong tim thay file .env!
    echo [INFO] Dang copy tu .env.example...
    copy .env.example .env >nul 2>&1
    if exist ".env" (
        echo [SUCCESS] Da tao file .env
    ) else (
        echo [ERROR] Khong the tao file .env!
        pause
        exit /b 1
    )
)

echo [INFO] Dang khoi dong Backend (PHP)...
echo.

REM Khoi dong Backend trong background
start "Backend Server" cmd /k "cd /d "%~dp0" && php -S localhost:8000 -t public public/router.php"

echo [INFO] Dang cho Backend khoi dong...
timeout /t 5 >nul

echo [INFO] Dang khoi dong Frontend (Next.js)...
echo.

REM Khoi dong Frontend trong background
start "Frontend Server" cmd /k "cd /d "%~dp0web" && npm run dev"

echo.
echo ========================================
echo [SUCCESS] Du an da duoc khoi dong!
echo ========================================
echo.
echo [INFO] Backend: http://localhost:8000
echo [INFO] Frontend: http://localhost:3000
echo [INFO] Admin Panel: http://localhost:3000/admin
echo.
echo [INFO] API Test: http://localhost:8000/api/v1/test
echo [INFO] Login API: http://localhost:8000/api/v1/auth/login
echo.
echo [INFO] Cac server da duoc mo trong cua so rieng.
echo [INFO] De dung server, chay file: stop-project.bat
echo.
pause
