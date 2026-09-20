@echo off
REM ============================================================
REM  release-apk.bat - Upload APK ke GitHub Releases (Windows)
REM  Jalankan dengan double-click atau dari Command Prompt.
REM  Butuh Git Bash (biasanya sudah terinstall bareng Git).
REM ============================================================
setlocal

where bash >nul 2>nul
if errorlevel 1 (
    echo ERROR: Git Bash tidak ditemukan.
    echo Install Git for Windows: https://git-scm.com/download/win
    pause
    exit /b 1
)

where gh >nul 2>nul
if errorlevel 1 (
    echo ERROR: gh CLI tidak ditemukan.
    echo Jalankan: winget install GitHub.cli  lalu  gh auth login
    pause
    exit /b 1
)

echo Menjalankan release-apk.sh dengan argumen: %*
bash "%~dp0release-apk.sh" %*
echo.
pause