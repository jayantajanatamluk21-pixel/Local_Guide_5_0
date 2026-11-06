@echo off
setlocal EnableExtensions
cd /d "%~dp0"
echo This creates a daily Windows Task Scheduler entry at 02:00.
set /p "CONFIRM=Install daily LocalGuide database backup? (Y/N): "
if /i not "%CONFIRM%"=="Y" exit /b 0
set "TASK_ACTION=cmd.exe /c ""%~dp0run-scheduled-backup.bat"""
schtasks /Create /SC DAILY /ST 02:00 /TN "LocalGuideDatabaseBackup" /TR "%TASK_ACTION%" /F
if errorlevel 1 (echo Could not create the scheduled task. Check Task Scheduler permissions.& pause & exit /b 1)
echo Scheduled task created. Verify it in Windows Task Scheduler and inspect storage\scheduled-backup.log after the first run.
pause
