@echo off
echo ========================================
echo    HE THONG ECOMMERCE - SHUTDOWN
echo ========================================
echo.

echo [INFO] Dang tat he thong an toan...
echo.

REM Kiem tra va tat Frontend (Next.js)
echo [INFO] Dang tat Frontend (Next.js)...
taskkill /f /im node.exe >nul 2>&1
if %errorlevel% equ 0 (
    echo [OK] Frontend da duoc tat
) else (
    echo [INFO] Frontend khong chay hoac da tat
)

REM Kiem tra va tat Backend (PHP)
echo [INFO] Dang tat Backend (PHP)...
taskkill /f /im php.exe >nul 2>&1
if %errorlevel% equ 0 (
    echo [OK] Backend da duoc tat
) else (
    echo [INFO] Backend khong chay hoac da tat
)

REM Cho mot chut de process tat hoan toan
echo [INFO] Dang cho process tat hoan toan...
timeout /t 3 >nul

REM Kiem tra port da duoc giai phong chua
echo [INFO] Kiem tra port...
netstat -ano | findstr :3000 >nul 2>&1
if %errorlevel% equ 0 (
    echo [WARNING] Port 3000 van con bi chiem
) else (
    echo [OK] Port 3000 da duoc giai phong
)

netstat -ano | findstr :8000 >nul 2>&1
if %errorlevel% equ 0 (
    echo [WARNING] Port 8000 van con bi chiem
) else (
    echo [OK] Port 8000 da duoc giai phong
)

echo.
echo ========================================
echo    HE THONG DA DUOC TAT AN TOAN!
echo ========================================
echo.
echo [Luu y]
echo - Neu port van bi chiem, hay restart may
echo - Nho commit code truoc khi tat may
echo - Backup database neu can thiet
echo.
echo Nhan phim bat ky de dong cua so nay...
pause >nul
