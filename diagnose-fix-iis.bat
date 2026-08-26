@echo off
REM ============================================================
REM SmartQuran — Diagnose & Fix Local IIS Configuration
REM
REM Run this whenever the site returns 404.3 / 500 / doesn't load
REM on IIS. It checks IIS + PHP + FastCGI + the SmartQuran site's
REM handler mapping, and fixes whatever is missing.
REM
REM Self-elevates via UAC if not already run as Administrator —
REM just double-click this file.
REM ============================================================

net session >nul 2>&1
if %errorlevel% neq 0 (
    echo Requesting Administrator rights via UAC ...
    powershell -NoProfile -Command "Start-Process -FilePath '%~f0' -Verb RunAs"
    exit /b
)

setlocal EnableDelayedExpansion
cd /d "%~dp0"

set "SOURCE_DIR=%~dp0"
set "SOURCE_DIR=%SOURCE_DIR:~0,-1%"
set "SITE_NAME=SmartQuran"
set "APPCMD=%SystemRoot%\System32\inetsrv\appcmd.exe"

echo ============================================================
echo  SmartQuran — Diagnose ^& Fix Local IIS
echo ============================================================
echo.

REM ── 1. IIS / appcmd present? ────────────────
echo [1/6] Checking IIS installation ...
if not exist "%APPCMD%" (
    echo   MISSING: IIS / appcmd.exe not found.
    echo            Enable: Control Panel ^> Turn Windows features on/off ^>
    echo            IIS ^> World Wide Web Services ^> Application Development
    echo            Features ^> CGI, and IIS ^> Web Management Tools.
    goto :fail
)
echo   OK.

REM ── 2. Find php-cgi.exe ─────────────────────
echo.
echo [2/6] Locating php-cgi.exe ...
set "PHP_CGI="
for /f "delims=" %%p in ('where php-cgi.exe 2^>nul') do set "PHP_CGI=%%p"
if not defined PHP_CGI if exist "C:\Users\HendiWibowo\.local\bin\php-cgi.exe" set "PHP_CGI=C:\Users\HendiWibowo\.local\bin\php-cgi.exe"
if not defined PHP_CGI (
    for /f "delims=" %%p in ('dir /s /b "%ProgramFiles%\php*\php-cgi.exe" 2^>nul') do set "PHP_CGI=%%p"
)
if not defined PHP_CGI (
    for /f "delims=" %%p in ('dir /s /b "%ProgramFiles(x86)%\php*\php-cgi.exe" 2^>nul') do set "PHP_CGI=%%p"
)
if not defined PHP_CGI (
    for /f "delims=" %%p in ('dir /s /b "C:\php*\php-cgi.exe" 2^>nul') do set "PHP_CGI=%%p"
)
if not defined PHP_CGI (
    echo   MISSING: php-cgi.exe not found anywhere. Install PHP or add it to PATH.
    goto :fail
)
echo   OK: !PHP_CGI!

REM ── 3. FastCGI module registered? ───────────
echo.
echo [3/6] Checking FastCGI module ...
"%APPCMD%" list modules | findstr /i /c:"FastCgiModule" >nul
if errorlevel 1 (
    echo   MISSING — enabling IIS-CGI feature and registering module ...
    dism /online /enable-feature /featurename:IIS-CGI /all /norestart >nul 2>&1
    "%APPCMD%" install module /name:FastCgiModule /image:"%SystemRoot%\System32\inetsrv\iisfcgi.dll" >nul 2>&1
    if errorlevel 1 "%APPCMD%" add module /name:FastCgiModule >nul 2>&1
    "%APPCMD%" list modules | findstr /i /c:"FastCgiModule" >nul
    if errorlevel 1 (
        echo   FAILED to register FastCgiModule.
        goto :fail
    )
    echo   FIXED.
) else (
    echo   OK.
)

REM ── 4. php-cgi.exe registered as FastCGI app? ─
echo.
echo [4/6] Registering php-cgi.exe as FastCGI application ...
icacls "!PHP_CGI!\.." /grant "IIS_IUSRS:(OI)(CI)RX" >nul 2>&1
"%APPCMD%" set config /section:system.webServer/fastCgi /-"[fullPath='!PHP_CGI!']" >nul 2>&1
"%APPCMD%" set config /section:system.webServer/fastCgi /+"[fullPath='!PHP_CGI!']" >nul 2>&1
if %errorlevel% neq 0 (
    echo   FAILED to register FastCGI application.
    goto :fail
)
echo   OK.

REM ── 5. Handler mapping (site-level if it exists, else server-level) ─
echo.
echo [5/6] Adding *.php handler mapping ...
"%APPCMD%" list site /name:%SITE_NAME% >nul 2>&1
if %errorlevel% equ 0 (
    echo   Site "%SITE_NAME%" found — mapping at site level.
    "%APPCMD%" set config "%SITE_NAME%" /section:system.webServer/handlers /-"[name='PHP_via_FastCGI']" >nul 2>&1
    "%APPCMD%" set config "%SITE_NAME%" /section:system.webServer/handlers /+[name='PHP_via_FastCGI',path='*.php',verb='GET,HEAD,POST',modules='FastCgiModule',scriptProcessor='!PHP_CGI!',resourceType='Either',requireAccess='Script'] /commit:apphost >nul 2>&1
) else (
    echo   Site "%SITE_NAME%" not found yet — mapping at server level instead.
    echo   (Run deploy-iis.bat first to create the site.)
    "%APPCMD%" set config /section:system.webServer/handlers /-"[name='PHP_via_FastCGI']" >nul 2>&1
    "%APPCMD%" set config /section:system.webServer/handlers /+"[name='PHP_via_FastCGI',path='*.php',verb='GET,HEAD,POST',modules='FastCgiModule',scriptProcessor='!PHP_CGI!',resourceType='Either']" >nul 2>&1
)
if %errorlevel% neq 0 (
    echo   FAILED to add handler mapping.
    goto :fail
)
echo   OK.

REM ── 6. Restart & test ───────────────────────
echo.
echo [6/6] Restarting site and testing ...
"%APPCMD%" list site /name:%SITE_NAME% >nul 2>&1
if %errorlevel% equ 0 (
    "%APPCMD%" stop site /site.name:%SITE_NAME% >nul 2>&1
    timeout /t 1 >nul
    "%APPCMD%" start site /site.name:%SITE_NAME% >nul 2>&1
    for /f "tokens=2 delims=:" %%p in ('"%APPCMD%" list site /name:%SITE_NAME% /text:bindings') do set "PORT_RAW=%%p"
) else (
    iisreset >nul
)

set "TEST_URL=http://localhost:8885/install.php"
for /f %%i in ('curl -s -o nul -w "%%{http_code}" %TEST_URL% 2^>nul') do set "HTTP=%%i"
echo   Tested %TEST_URL% — HTTP %HTTP%

echo.
echo ============================================================
if "%HTTP%"=="200" (
    echo  BERHASIL — IIS + PHP FastCGI sudah berjalan.
) else (
    echo  Konfigurasi selesai, tapi respons masih HTTP %HTTP%.
    echo  - 404.3 : jalankan deploy-iis.bat dulu untuk membuat site-nya.
    echo  - 500   : cek error PHP langsung di %TEST_URL% lewat browser.
    echo  - 000   : site belum start, atau port beda dari 8885 ^(cek deploy-iis.bat^).
)
echo ============================================================
echo.
pause
exit /b 0

:fail
echo.
echo Diagnosis dihentikan karena error di atas.
echo.
pause
exit /b 1
