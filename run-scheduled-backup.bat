@echo off
setlocal EnableExtensions
cd /d "%~dp0"
set "PHP_EXE="
for /f "delims=" %%P in ('where php.exe 2^>nul') do if not defined PHP_EXE set "PHP_EXE=%%P"
for /d %%D in ("%USERPROFILE%\Downloads\php-*-nts-Win32-vs17-x64") do if not defined PHP_EXE if exist "%%~D\php.exe" set "PHP_EXE=%%~D\php.exe"
if not defined PHP_EXE exit /b 1
"%PHP_EXE%" tools\backup-database.php >> storage\scheduled-backup.log 2>&1
exit /b %ERRORLEVEL%
