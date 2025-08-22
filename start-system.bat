@echo off
echo ========================================
echo    HE THONG ECOMMERCE - STARTUP
echo ========================================
echo.

REM Kiem tra va kill cac process cu
echo [INFO] Dang kiem tra va tat cac process cu...
taskkill /f /im php.exe >nul 2>&1
taskkill /f /im node.exe >nul 2>&1
timeout /t 2 >nul

REM Kiem tra PHP
echo [INFO] Kiem tra PHP...
php --version >nul 2>&1
if %errorlevel% neq 0 (
    echo [ERROR] PHP khong duoc cai dat hoac khong co trong PATH!
    echo Hay cai dat PHP va them vao PATH
    pause
    exit /b 1
)
echo [OK] PHP da san sang

REM Kiem tra Node.js
echo [INFO] Kiem tra Node.js...
node --version >nul 2>&1
if %errorlevel% neq 0 (
    echo [ERROR] Node.js khong duoc cai dat hoac khong co trong PATH!
    echo Hay cai dat Node.js va them vao PATH
    pause
    exit /b 1
)
echo [OK] Node.js da san sang

REM Kiem tra MySQL (optional)
echo [INFO] Kiem tra MySQL...
mysql --version >nul 2>&1
if %errorlevel% neq 0 (
    echo [WARNING] MySQL khong tim thay, nhung co the van chay duoc
) else (
    echo [OK] MySQL da san sang
)

echo.
echo [INFO] Dang khoi dong Backend (PHP)...
start "Backend Server" cmd /k "cd /d D:\Khoa_Luan_Tan_Phuc\code\ecommerce && echo [BACKEND] Starting PHP server... && php -S localhost:8000 -t public public/router.php"

echo [INFO] Dang cho Backend khoi dong...
timeout /t 5 >nul

echo [INFO] Kiem tra Backend...
curl -s http://localhost:8000/api/test/orders/1 >nul 2>&1
if %errorlevel% neq 0 (
    echo [WARNING] Backend chua san sang, nhung se tiep tuc...
) else (
    echo [OK] Backend da san sang
)

echo.
echo [INFO] Dang khoi dong Frontend (Next.js)...
start "Frontend Server" cmd /k "cd /d D:\Khoa_Luan_Tan_Phuc\code\web && echo [FRONTEND] Starting Next.js... && npm run dev"

echo [INFO] Dang cho Frontend khoi dong...
timeout /t 8 >nul

echo.
echo ========================================
echo    HE THONG DA DUOC KHOI DONG!
echo ========================================
echo.
echo Backend:  http://localhost:8000
echo Frontend: http://localhost:3000
echo Admin:    http://localhost:3000/admin
echo.
echo [Luu y]
echo - Neu gap loi CORS, hay restart lai Backend
echo - Neu gap loi port, hay kiem tra va tat process cu
echo - De dung he thong: nhan Ctrl+C trong moi cua so
echo.
echo Nhan phim bat ky de dong cua so nay...
pause >nul
