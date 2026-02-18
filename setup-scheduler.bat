@echo off
chcp 65001 >nul
echo Deleting existing task...
schtasks /Delete /TN "LaravelScheduler-MEO" /F 2>nul
if %errorlevel% equ 0 (
    echo Existing task deleted
) else (
    echo No existing task found
)

echo.
echo Creating new task...
schtasks /Create /TN "LaravelScheduler-MEO" /TR "C:\laragon\www\meo\run-scheduler.bat" /SC MINUTE /MO 1 /ST 00:00 /RL HIGHEST /F

if %errorlevel% equ 0 (
    echo Task created successfully
) else (
    echo Task creation failed - Please run as Administrator
    pause
    exit /b 1
)

echo.
echo Verifying task...
schtasks /Query /TN "LaravelScheduler-MEO" /V /FO LIST

echo.
echo Running test execution...
schtasks /Run /TN "LaravelScheduler-MEO"

echo.
echo Done!
pause
