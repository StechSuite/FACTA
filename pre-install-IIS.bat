@echo off
REM ============================================================
REM  pre-install-IIS.bat  (server-level FastCGI registration only)
REM
REM  Prefer diagnose-fix-iis.bat instead — it covers this same
REM  step plus site-level handler mapping, module checks, and a
REM  live test, in one script. Kept here for reference / narrow
REM  server-level-only registration.
REM
REM  Mendaftarkan PHP (FastCGI) ke IIS supaya file .php bisa
REM  dilayani (memperbaiki error HTTP 404.3).
REM
REM  Cukup double-click file ini. Jika belum berjalan sebagai
REM  Administrator, script akan meminta elevasi (UAC) sendiri.
REM ============================================================

REM --- Self-elevate jika belum Administrator ---
net session >nul 2>&1
if %errorlevel% neq 0 (
    echo Meminta hak Administrator lewat UAC...
    powershell -NoProfile -Command "Start-Process -FilePath '%~f0' -Verb RunAs"
    exit /b
)

setlocal
cd /d "%~dp0"
set "PHP_DIR=C:\Users\HendiWibowo\.local\bin"
set "PHP_CGI=%PHP_DIR%\php-cgi.exe"
set "APPCMD=%windir%\System32\inetsrv\appcmd.exe"
set "TEST_URL=http://localhost:8885/install.php"

echo.
echo [1/6] Verifikasi file yang dibutuhkan...
if not exist "%PHP_CGI%" (
    echo   GAGAL: %PHP_CGI% tidak ditemukan.
    goto :fail
)
if not exist "%APPCMD%" (
    echo   GAGAL: appcmd.exe tidak ditemukan. Pastikan IIS terpasang.
    goto :fail
)
echo   OK.

echo.
echo [2/6] Mengaktifkan modul FastCGI IIS (fitur IIS-CGI)...
REM Enable fitur Windows-nya (tidak apa-apa jika sudah aktif).
dism /online /enable-feature /featurename:IIS-CGI /all /norestart >nul 2>&1
REM Jika modul tetap belum terdaftar di applicationHost.config, daftarkan manual.
"%APPCMD%" list modules | findstr /i /c:"FastCgiModule" >nul
if errorlevel 1 (
    "%APPCMD%" install module /name:FastCgiModule /image:"%windir%\System32\inetsrv\iisfcgi.dll" >nul 2>&1
    if errorlevel 1 "%APPCMD%" add module /name:FastCgiModule >nul 2>&1
)
"%APPCMD%" list modules | findstr /i /c:"FastCgiModule" >nul
if errorlevel 1 (
    echo   GAGAL: modul FastCgiModule tidak bisa didaftarkan ke IIS.
    goto :fail
)
echo   OK.

echo.
echo [3/6] Memberi akses baca IIS_IUSRS ke folder PHP...
icacls "%PHP_DIR%" /grant "IIS_IUSRS:(OI)(CI)RX" >nul
if %errorlevel% neq 0 (
    echo   GAGAL: icacls error %errorlevel%.
    goto :fail
)
echo   OK.

echo.
echo [4/6] Mendaftarkan php-cgi.exe sebagai aplikasi FastCGI...
REM Hapus dulu entri lama (jika ada) supaya script aman dijalankan ulang.
"%APPCMD%" set config /section:system.webServer/fastCgi /-"[fullPath='%PHP_CGI%']" >nul 2>&1
"%APPCMD%" set config /section:system.webServer/fastCgi /+"[fullPath='%PHP_CGI%']"
if %errorlevel% neq 0 (
    echo   GAGAL: registrasi FastCGI error %errorlevel%.
    goto :fail
)
echo   OK.

echo.
echo [5/6] Menambahkan handler mapping *.php...
"%APPCMD%" set config /section:system.webServer/handlers /-"[name='PHP_via_FastCGI']" >nul 2>&1
"%APPCMD%" set config /section:system.webServer/handlers /+"[name='PHP_via_FastCGI',path='*.php',verb='GET,HEAD,POST',modules='FastCgiModule',scriptProcessor='%PHP_CGI%',resourceType='Either']"
if %errorlevel% neq 0 (
    echo   GAGAL: penambahan handler error %errorlevel%.
    goto :fail
)
echo   OK.

echo.
echo [6/6] Restart IIS lalu menguji %TEST_URL% ...
iisreset >nul
for /f %%i in ('curl -s -o nul -w "%%{http_code}" %TEST_URL%') do set "HTTP=%%i"
echo   HTTP status: %HTTP%
if "%HTTP%"=="200" (
    echo.
    echo   BERHASIL! PHP sudah berjalan di IIS.
) else (
    echo.
    echo   Registrasi selesai, tapi respons masih %HTTP%.
    echo   - 404.3 : handler belum aktif, coba jalankan ulang script ini.
    echo   - 500   : cek error PHP di halaman %TEST_URL% lewat browser.
)

echo.
pause
exit /b 0

:fail
echo.
echo Script berhenti karena error di atas.
echo.
pause
exit /b 1
