@echo off
cd /d "%~dp0"
start "OrderDesk Server" "C:\xampp\php\php.exe" -S 127.0.0.1:8000 -t .
timeout /t 2 /nobreak >nul
start "" "http://127.0.0.1:8000/login.php"
