@echo off
setlocal EnableExtensions
cd /d "%~dp0"

rem PHP lookup order: PATH, C:\php, XAMPP, then a downloaded PHP NTS folder.
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

if not defined PHP_EXE (
  for /d %%D in ("%USERPROFILE%\Downloads\php-*-nts-Win32-vs17-x64") do (
    if not defined PHP_EXE if exist "%%~D\php.exe" (
      "%%~D\php.exe" -r "exit(PHP_VERSION_ID >= 80200 ? 0 : 1);" >nul 2>nul
      if not errorlevel 1 set "PHP_EXE=%%~D\php.exe"
    )
  )
)

if not defined PHP_EXE (
  echo PHP 8.2 or newer was not found.
  echo Checked PATH, C:\php, common XAMPP folders and Downloads PHP NTS folders.
  echo Install a compiled PHP Windows ZIP or add its folder to PATH.
  pause
  exit /b 1
)

echo Using PHP executable:
echo   "%PHP_EXE%"
"%PHP_EXE%" -v

"%PHP_EXE%" -r "exit(extension_loaded('pdo_sqlite') && extension_loaded('sqlite3') ? 0 : 1);" >nul 2>nul
if errorlevel 1 (
  echo.
  echo SQLite support is missing from the selected PHP installation.
  "%PHP_EXE%" --ini
  echo Enable extension=pdo_sqlite and extension=sqlite3 in the loaded php.ini.
  pause
  exit /b 1
)

"%PHP_EXE%" -r "exit(extension_loaded('fileinfo') ? 0 : 1);" >nul 2>nul
if errorlevel 1 (
  echo.
  echo fileinfo is missing. Enable extension=fileinfo in php.ini.
  "%PHP_EXE%" --ini
  pause
  exit /b 1
)

if not exist storage mkdir storage
if not exist public\uploads mkdir public\uploads

echo.
echo ============================================================
echo LocalGuide 5.0 is starting.
echo.
echo PUBLIC WEBSITE:
echo   http://127.0.0.1:8000/
echo.
echo ADMIN PORTAL ROUTE (PRIVATE / INVITE REQUIRED):
echo   http://127.0.0.1:8000/admin/hidden-portal-auth/
echo.
echo IMPORTANT:
echo   The admin route intentionally returns 404 without a valid
echo   invitation token. To generate your private, tokenized admin URL,
echo   run generate-admin-invite.bat from this project folder.
echo   Do not share that private invitation URL publicly.
echo.
echo Both URLs use this same PHP server; the admin portal is a
echo separate protected route, not a second server or port.
echo.
echo Press Ctrl+C to stop.
echo ============================================================
"%PHP_EXE%" -S 127.0.0.1:8000 -t public
