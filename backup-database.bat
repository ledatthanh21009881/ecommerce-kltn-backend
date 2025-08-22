@echo off
echo ========================================
echo    BACKUP DATABASE ECOMMERCE
echo ========================================
echo.

REM Tao ten file backup voi timestamp
set timestamp=%date:~-4,4%%date:~-10,2%%date:~-7,2%_%time:~0,2%%time:~3,2%%time:~6,2%
set timestamp=%timestamp: =0%
set backup_file=backup_shopswiftv2_%timestamp%.sql

echo [INFO] Dang backup database 'shopswiftv2'...
echo [INFO] File backup: %backup_file%
echo.

REM Backup database
mysqldump -u root -p shopswiftv2 > %backup_file%

if %errorlevel% equ 0 (
    echo [OK] Backup thanh cong!
    echo [INFO] File backup: %backup_file%
    echo [INFO] Kich thuoc: 
    dir %backup_file% | findstr %backup_file%
) else (
    echo [ERROR] Backup that bai!
    echo [INFO] Kiem tra:
    echo - MySQL service co dang chay khong
    echo - Username/password co dung khong
    echo - Database 'shopswiftv2' co ton tai khong
)

echo.
echo Nhan phim bat ky de dong cua so nay...
pause >nul
