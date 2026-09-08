@echo off
setlocal EnableExtensions
chcp 65001 >nul
title Hello Store - Cloudflare Tunnel

echo ============================================================
echo   Hello Store - Cloudflare Tunnel (otomatis)
echo ============================================================
echo.

REM ============================================================
REM  1. Cek Laravel jalan di port 8000
REM ============================================================
netstat -an | findstr ":8000" | findstr "LISTENING" >nul
if errorlevel 1 (
    echo [!] Laravel tidak terdeteksi di port 8000.
    echo     Jalankan dulu di terminal lain:  php artisan serve
    echo     atau:  composer dev
    echo.
    pause
    exit /b 1
)
echo [OK] Laravel terdeteksi di port 8000.
echo.

REM ============================================================
REM  2. Jalanin tunnel di window terpisah (minimized)
REM ============================================================
if exist tunnel.log del tunnel.log 2>nul
start "HelloStore-Tunnel" /min cmd /c ""C:\cloudflared-bin\cloudflared.exe" tunnel --url http://localhost:8000 --no-autoupdate > tunnel.log 2>&1"
echo [..] Menunggu tunnel aktif (maksimal 90 detik)...

REM ============================================================
REM  3. Tunggu sampai URL trycloudflare.com muncul di log
REM ============================================================
set "TUNNEL_URL="
for /L %%i in (1,1,90) do (
    for /f "usebackq tokens=*" %%L in (`powershell -NoProfile -Command "[regex]::Match((Get-Content -Path tunnel.log -Raw), 'https://[A-Za-z0-9\-]+\.trycloudflare\.com').Value" 2^>nul`) do set "TUNNEL_URL=%%L"
    if defined TUNNEL_URL goto :parsed
    timeout /t 1 /nobreak >nul
)

:parsed
if not defined TUNNEL_URL (
    echo [!] Gagal mendapatkan URL tunnel dalam 90 detik.
    echo     Cek window "HelloStore-Tunnel" untuk error.
    pause
    exit /b 1
)

echo [OK] Tunnel aktif: %TUNNEL_URL%
echo.

REM ============================================================
REM  4. Copy URL ke clipboard
REM ============================================================
echo %TUNNEL_URL%| clip
echo [OK] URL sudah di-copy ke clipboard.
echo.

REM ============================================================
REM  5. Update mobile_api_url di database settings
REM ============================================================
php update-tunnel-url.php %TUNNEL_URL%
if errorlevel 1 (
    echo [!] Gagal update database.
) else (
    echo [OK] mobile_api_url berhasil diupdate.
)
echo.

REM ============================================================
REM  6. Selesai
REM ============================================================
echo ============================================================
echo   SELESAI!
echo   URL baru  : %TUNNEL_URL%
echo   Clipboard : sudah ter-copy
echo   Database  : mobile_api_url sudah diperbarui
echo.
echo   App di HP akan auto-detect URL baru saat dibuka ulang.
echo   Biarkan window "HelloStore-Tunnel" tetap terbuka.
echo ============================================================
echo.
pause