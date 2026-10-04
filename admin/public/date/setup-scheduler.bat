@echo off
REM ============================================================================
REM Setup Nepali Date Auto-Update Task Scheduler
REM This script creates a Windows Task Scheduler job to update the Nepali date daily
REM Run this as Administrator
REM ============================================================================

echo.
echo ============================================================================
echo Phool Delivery - Nepali Date Auto-Update Scheduler Setup
echo ============================================================================
echo.

REM Check if running as Administrator
NET SESSION >nul 2>&1
if %errorlevel% neq 0 (
    echo ERROR: This script requires Administrator privileges!
    echo Please run Command Prompt as Administrator.
    echo.
    pause
    exit /b 1
)

echo Setting up Windows Task Scheduler...
echo.

REM Get the current date and format it
for /f "tokens=2-4 delims=/ " %%a in ('date /t') do (set mydate=%%c-%%a-%%b)
for /f "tokens=1-2 delims=/:" %%a in ('time /t') do (set mytime=%%a:%%b)

echo Current Date/Time: %mydate% %mytime%
echo.

REM Create the task
REM This task will run the auto-update script every day at 00:00 (midnight)

echo Creating scheduled task...
echo.

REM First, delete existing task if it exists
schtasks /delete /tn "Phool Delivery\Nepali Date Auto-Update" /f 2>nul

REM Create the task
schtasks /create /tn "Phool Delivery\Nepali Date Auto-Update" ^
    /tr "C:\xampp\php\php.exe C:\xampp\htdocs\phool-delivery\admin\public\date\auto-update.php" ^
    /sc daily /st 00:00 /f

echo.
if %errorlevel% equ 0 (
    echo ✓ Task created successfully!
    echo.
    echo Task Details:
    echo ============================================
    echo Task Name: Phool Delivery - Nepali Date Auto-Update
    echo Folder: Phool Delivery
    echo Trigger: Daily at 00:00 (Midnight)
    echo Program: C:\xampp\php\php.exe
    echo Args: C:\xampp\htdocs\phool-delivery\admin\public\date\auto-update.php
    echo ============================================
    echo.
) else (
    echo ✗ Failed to create task!
    echo Error Code: %errorlevel%
)

echo.
echo Testing the update script...
echo.

REM Run the script once to test
C:\xampp\php\php.exe C:\xampp\htdocs\phool-delivery\admin\public\date\auto-update.php

echo.
echo ============================================================================
echo Setup Complete!
echo ============================================================================
echo.
echo Next Steps:
echo 1. Verify the task was created: Open Task Scheduler and check under
echo    "Phool Delivery" folder for "Nepali Date Auto-Update"
echo 2. Check the log file at: C:\xampp\htdocs\phool-delivery\admin\public\date\nepali_date_update.log
echo 3. Visit the calendar page to verify it's working:
echo    http://localhost/phool-delivery/admin/public/nepali_calendar.php
echo 4. Database table should be updated automatically every midnight
echo.
pause
