@echo off
setlocal EnableExtensions
cd /d "%~dp0"
set "PHP_EXE="
for /f "delims=" %%P in ('where php.exe 2^>nul') do (
  if not defined PHP_EXE (
    "%%P" -r "exit(PHP_VERSION_ID >= 80200 ? 0 : 1);" >nul 2>nul
    if not errorlevel 1 set "PHP_EXE=%%P"
  )
)
for %%P in ("C:\php\php.exe" "C:\xampp\php\php.exe" "D:\xampp\php\php.exe" "%USERPROFILE%\Downloads\php-8.5.12-1-nts-Win32-vs17-x64\php.exe") do (
  if not defined PHP_EXE if exist "%%~P" (
    "%%~P" -r "exit(PHP_VERSION_ID >= 80200 ? 0 : 1);" >nul 2>nul
    if not errorlevel 1 set "PHP_EXE=%%~P"
  )
)
if not defined PHP_EXE for /d %%D in ("%USERPROFILE%\Downloads\php-*-nts-Win32-vs17-x64") do (
  if not defined PHP_EXE if exist "%%~D\php.exe" (
    "%%~D\php.exe" -r "exit(PHP_VERSION_ID >= 80200 ? 0 : 1);" >nul 2>nul
    if not errorlevel 1 set "PHP_EXE=%%~D\php.exe"
  )
)
if not defined PHP_EXE (
  echo PHP 8.2 or newer was not found. Check your PHP folder.
  pause
  exit /b 1
)
"%PHP_EXE%" -r "exit(extension_loaded('pdo_sqlite') ? 0 : 1);" >nul 2>nul
if errorlevel 1 (
  echo PDO SQLite is not enabled in the selected PHP installation.
  "%PHP_EXE%" --ini
  pause
  exit /b 1
)
echo Using PHP: "%PHP_EXE%"
"%PHP_EXE%" tools\set-admin-credentials.php
if errorlevel 1 echo Credential update did not complete. No plaintext password was written to project files.
pause
