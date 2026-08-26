@echo off
REM ============================================
REM Fix PHP FastCGI Handler for SmartQuran on IIS
REM Run as Administrator
REM
REM Prefer diagnose-fix-iis.bat instead — it covers this same
REM site-level handler fix plus module/php-cgi checks, self-
REM elevation, and a live test, in one script. Kept here for
REM reference / narrow site-handler-only fix (site must already
REM exist — run deploy-iis.bat first if it doesn't).
REM ============================================

setlocal EnableDelayedExpansion

echo ============================================
echo Fix PHP FastCGI Handler
echo ============================================

set "SITE_NAME=SmartQuran"

REM ── Admin rights check ─────────────────────
net session >nul 2>&1
if %errorlevel% neq 0 (
    echo [ERROR] Run this script as Administrator.
    pause
    exit /b 1
)

REM ── Find php-cgi.exe ───────────────────────
echo [1/3] Looking for php-cgi.exe ...
set "PHP_CGI="

REM 1. Check same folder as php.exe
for /f "delims=" %%p in ('where php.exe 2^>nul') do (
    set "PHP_DIR=%%~dpp"
    if exist "!PHP_DIR!php-cgi.exe" set "PHP_CGI=!PHP_DIR!php-cgi.exe"
)

REM 2. Search ProgramFiles
if not defined PHP_CGI (
    for /f "delims=" %%p in ('dir /s /b "%ProgramFiles%\php*\php-cgi.exe" 2^>nul') do (
        set "PHP_CGI=%%p"
    )
)

REM 3. Search ProgramFiles(x86)
if not defined PHP_CGI (
    for /f "delims=" %%p in ('dir /s /b "%ProgramFiles(x86)%\php*\php-cgi.exe" 2^>nul') do (
        set "PHP_CGI=%%p"
    )
)

REM 4. Search C:\php
if not defined PHP_CGI (
    for /f "delims=" %%p in ('dir /s /b "C:\php*\php-cgi.exe" 2^>nul') do (
        set "PHP_CGI=%%p"
    )
)

REM 5. Search C:\Users\HendiWibowo\.local\bin
if not defined PHP_CGI (
    if exist "C:\Users\HendiWibowo\.local\bin\php-cgi.exe" (
        set "PHP_CGI=C:\Users\HendiWibowo\.local\bin\php-cgi.exe"
    )
)

if not defined PHP_CGI (
    echo [ERROR] php-cgi.exe not found.
    echo         Please enter the full path manually:
    set /p PHP_CGI="Path to php-cgi.exe: "
)

if not exist "%PHP_CGI%" (
    echo [ERROR] File not found: %PHP_CGI%
    pause
    exit /b 1
)

echo Found: %PHP_CGI%

REM ── Register FastCGI application ───────────
echo.
echo [2/3] Registering FastCGI application ...
"%SystemRoot%\System32\inetsrv\appcmd.exe" set config /section:system.webServer/fastCgi /+[fullPath='%PHP_CGI%'] >nul 2>&1
if %errorlevel% neq 0 (
    echo [INFO] FastCGI application may already exist, continuing ...
)

REM ── Add handler mapping ────────────────────
echo.
echo [3/3] Adding handler mapping for *.php ...
"%SystemRoot%\System32\inetsrv\appcmd.exe" set config "%SITE_NAME%" /section:system.webServer/handlers /+[name='PHP_via_FastCGI',path='*.php',verb='GET,HEAD,POST',modules='FastCgiModule',scriptProcessor='%PHP_CGI%',resourceType='Either',requireAccess='Script'] /commit:apphost >nul 2>&1
if %errorlevel% neq 0 (
    echo [ERROR] Failed to add handler mapping.
    echo         Try manually via IIS Manager:
    echo         IIS ^> Sites ^> %SITE_NAME% ^> Handler Mappings ^> Add Module Mapping
    pause
    exit /b 1
)

REM ── Restart site ───────────────────────────
echo.
echo Restarting IIS site ...
"%SystemRoot%\System32\inetsrv\appcmd.exe" stop site /site.name:%SITE_NAME% >nul 2>&1
timeout /t 1 >nul
"%SystemRoot%\System32\inetsrv\appcmd.exe" start site /site.name:%SITE_NAME% >nul 2>&1

echo.
echo ============================================
echo  FIX COMPLETE
echo ============================================
echo.
echo  Try now: http://localhost:8885/install.php
echo.
pause
