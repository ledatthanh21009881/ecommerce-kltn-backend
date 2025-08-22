@echo off
echo ========================================
echo    DUNG TOAN BO DU AN SHOPSWIFT
echo ========================================
echo.

REM Tat tat ca cac process PHP va Node.js
echo [INFO] Dang tat tat ca cac process PHP va Node.js...
taskkill /f /im php.exe >nul 2>&1
taskkill /f /im node.exe >nul 2>&1

REM Tat process tren port 8000
echo [INFO] Dang kiem tra va tat process tren port 8000...
netstat -ano | findstr :8000 > nul
if %errorlevel% equ 0 (
    for /f "tokens=5" %%a in ('netstat -ano ^| findstr :8000') do (
        taskkill /f /pid %%a >nul 2>&1
        echo [INFO] Da tat process tren port 8000 (PID: %%a)
    )
) else (
    echo [INFO] Khong co process nao tren port 8000
)

REM Tat process tren port 3000
echo [INFO] Dang kiem tra va tat process tren port 3000...
netstat -ano | findstr :3000 > nul
if %errorlevel% equ 0 (
    for /f "tokens=5" %%a in ('netstat -ano ^| findstr :3000') do (
        taskkill /f /pid %%a >nul 2>&1
        echo [INFO] Da tat process tren port 3000 (PID: %%a)
    )
) else (
    echo [INFO] Khong co process nao tren port 3000
)

echo.
echo ========================================
echo [SUCCESS] Toan bo du an da duoc dung!
echo ========================================
echo.
echo [INFO] Backend (PHP): Da dung
echo [INFO] Frontend (Next.js): Da dung
echo [INFO] MySQL Database: Van chay (neu co)
echo.
pause
