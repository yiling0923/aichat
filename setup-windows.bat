@echo off
REM One-click setup for XAMPP on Windows. Run this from the project folder (C:\xampp\htdocs\aichat).
REM Needs: XAMPP running (Apache + MySQL), PHP and Composer on the PATH.
REM This file has not been tested on Windows. If a step fails, run the commands from README.md by hand.

setlocal
cd /d "%~dp0"

echo [1/5] Creating the database "aichat" (needs MySQL running in XAMPP)...
set MYSQL=C:\xampp\mysql\bin\mysql.exe
if exist "%MYSQL%" (
    "%MYSQL%" -u root -e "CREATE DATABASE IF NOT EXISTS aichat CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
) else (
    echo    Could not find %MYSQL%. Create the database "aichat" in phpMyAdmin, then run this file again.
    pause
    exit /b 1
)

echo [2/5] Installing PHP libraries...
call composer install
if errorlevel 1 goto failed

echo [3/5] Creating the .env file...
if not exist ".env" copy ".env.example" ".env"

echo [4/5] Generating the app key...
php artisan key:generate
if errorlevel 1 goto failed

echo [5/5] Creating the tables and sample data...
php artisan migrate --seed
if errorlevel 1 goto failed

echo.
echo Done. Open these in your browser:
echo   Test chat:    http://localhost/aichat/public/chat/lumiere
echo   Staff inbox:  http://localhost/aichat/public/login   (owner@example.com / password)
pause
exit /b 0

:failed
echo.
echo Something failed. See the "If something goes wrong" table in README.md.
pause
exit /b 1
