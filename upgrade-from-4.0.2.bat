@echo off
setlocal EnableExtensions
cd /d "%~dp0"
echo ============================================================
echo LocalGuide 5.0 - SAFE DATA TRANSFER FROM 4.0.2
echo ============================================================
echo.
echo This tool copies data from your old project into this NEW 5.0 folder.
echo It never edits or deletes the source project.
echo Stop the old PHP server before continuing.
echo.
set /p "OLDROOT=Enter full path to your existing 4.0.2 project folder: "
if not exist "%OLDROOT%\storage\metroguide.sqlite" (
  echo Database not found: "%OLDROOT%\storage\metroguide.sqlite"
  echo No data was copied. Confirm the old project folder path.
  pause
  exit /b 1
)
if exist "storage\metroguide.sqlite" (
  echo A database already exists in this LocalGuide 5.0 folder.
  echo To avoid accidental overwrite, this script will not replace it.
  pause
  exit /b 1
)
if not exist storage mkdir storage
if not exist public\uploads mkdir public\uploads
copy /y "%OLDROOT%\storage\metroguide.sqlite" "storage\metroguide.sqlite" >nul
if errorlevel 1 (echo Database copy failed.& pause & exit /b 1)
rem Preserve any committed WAL transactions left after the old server is stopped. SQLite rebuilds SHM automatically.
if exist "%OLDROOT%\storage\metroguide.sqlite-wal" copy /y "%OLDROOT%\storage\metroguide.sqlite-wal" "storage\metroguide.sqlite-wal" >nul
if errorlevel 1 (echo SQLite WAL copy failed. The old source remains unchanged.& pause & exit /b 1)
if exist "%OLDROOT%\public\uploads" (
  robocopy "%OLDROOT%\public\uploads" "public\uploads" /E /COPY:DAT /R:2 /W:1 /NFL /NDL /NJH /NJS /NP
  if errorlevel 8 (echo Upload transfer failed. The old source remains unchanged.& pause & exit /b 1)
)
if exist "%OLDROOT%\.env" (
  if not exist ".env" copy /y "%OLDROOT%\.env" ".env" >nul
  if exist ".env" echo Existing .env preserved or copied. Review APP_URL and keep all secrets private.
)
echo.
echo Data transfer finished. The 4.0.2 source project was not modified.
echo Any SQLite WAL file was copied with the database so committed transactions remain available.
echo Next: run verify-upgrade.bat, then run-windows.bat.
echo The first PHP database connection applies additive SQLite migrations. Keep a separate backup of the old project.
pause
