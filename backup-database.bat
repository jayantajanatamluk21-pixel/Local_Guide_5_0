@echo off
setlocal EnableExtensions
cd /d "%~dp0"
set "PHP_EXE="
for /f "delims=" %%P in ('where php.exe 2^>nul') do if not defined PHP_EXE set "PHP_EXE=%%P"
for %%P in ("C:\php\php.exe" "C:\xampp\php\php.exe" "D:\xampp\php\php.exe" "%USERPROFILE%\Downloads\php-8.5.12-1-nts-Win32-vs17-x64\php.exe") do if not defined PHP_EXE if exist "%%~P" set "PHP_EXE=%%~P"
if not defined PHP_EXE for /d %%D in ("%USERPROFILE%\Downloads\php-*-nts-Win32-vs17-x64") do if not defined PHP_EXE if exist "%%~D\php.exe" set "PHP_EXE=%%~D\php.exe"
if not defined PHP_EXE (
  echo PHP 8.2 or newer was not found. Run run-windows.bat first to confirm your PHP setup.
  pause
  exit /b 1
)
"%PHP_EXE%" -r "exit(PHP_VERSION_ID >= 80200 && extension_loaded('pdo_sqlite') ? 0 : 1);" >nul 2>nul
if errorlevel 1 (
  echo PHP 8.2+ with pdo_sqlite is required. Check php.ini.
  pause
  exit /b 1
)
echo Creating and verifying a LocalGuide database backup...
"%PHP_EXE%" tools\backup-database.php
if errorlevel 1 echo Backup failed. Review the error above.
pause
