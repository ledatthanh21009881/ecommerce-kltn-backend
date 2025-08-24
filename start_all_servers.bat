@echo off
echo Starting all servers...

REM Start WebSocket server in background
start "WebSocket Server" cmd /k "php websocket_server.php"

REM Wait 2 seconds for WebSocket to start
timeout /t 2 /nobreak > nul

REM Start PHP development server
echo Starting PHP development server...
php -S localhost:8000 -t public public/router.php

pause
