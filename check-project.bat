@echo off
echo ========================================
echo    KIEM TRA TRANG THAI DU AN
echo ========================================
echo.

REM Kiem tra Backend (Port 8000)
echo [INFO] Kiem tra Backend (PHP) - Port 8000...
netstat -ano | findstr :8000 > nul
if %errorlevel% equ 0 (
    echo [SUCCESS] Backend: DANG CHAY
    for /f "tokens=5" %%a in ('netstat -ano ^| findstr :8000') do (
        echo [INFO] Process ID: %%a
    )
) else (
    echo [WARNING] Backend: KHONG CHAY
)

echo.

REM Kiem tra Frontend (Port 3000)
echo [INFO] Kiem tra Frontend (Next.js) - Port 3000...
netstat -ano | findstr :3000 > nul
if %errorlevel% equ 0 (
    echo [SUCCESS] Frontend: DANG CHAY
    for /f "tokens=5" %%a in ('netstat -ano ^| findstr :3000') do (
        echo [INFO] Process ID: %%a
    )
) else (
    echo [WARNING] Frontend: KHONG CHAY
)

echo.

REM Kiem tra MySQL Database (Port 3306)
echo [INFO] Kiem tra MySQL Database - Port 3306...
netstat -ano | findstr :3306 > nul
if %errorlevel% equ 0 (
    echo [SUCCESS] MySQL Database: DANG CHAY
) else (
    echo [WARNING] MySQL Database: KHONG CHAY
)

echo.

REM Kiem tra cac process PHP
echo [INFO] Kiem tra cac process PHP...
tasklist /fi "imagename eq php.exe" 2>nul | find "php.exe" >nul
if %errorlevel% equ 0 (
    echo [SUCCESS] PHP processes: DANG CHAY
    tasklist /fi "imagename eq php.exe"
) else (
    echo [WARNING] PHP processes: KHONG CHAY
)

echo.

REM Kiem tra cac process Node.js
echo [INFO] Kiem tra cac process Node.js...
tasklist /fi "imagename eq node.exe" 2>nul | find "node.exe" >nul
if %errorlevel% equ 0 (
    echo [SUCCESS] Node.js processes: DANG CHAY
    tasklist /fi "imagename eq node.exe"
) else (
    echo [WARNING] Node.js processes: KHONG CHAY
)

echo.
echo ========================================
echo [INFO] DU AN URLS:
echo ========================================
echo [INFO] Backend: http://localhost:8000
echo [INFO] Frontend: http://localhost:3000
echo [INFO] Admin Panel: http://localhost:3000/admin
echo [INFO] API Base: http://localhost:8000/api/v1
echo ========================================
echo.
pause
