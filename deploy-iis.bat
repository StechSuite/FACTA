@echo off
REM ============================================
REM SmartQuran — Deploy Script for IIS
REM Reuses / recycles port 8885
REM ============================================

setlocal EnableDelayedExpansion

echo ============================================
echo SmartQuran IIS Deploy Script
echo ============================================

set "SOURCE_DIR=%~dp0"
set "SOURCE_DIR=%SOURCE_DIR:~0,-1%"
set "SITE_NAME=SmartQuran"
set "PORT=8885"
set "APP_POOL=SmartQuranPool"

echo.
echo Source : %SOURCE_DIR%
echo Port   : %PORT%
echo.

REM ── 1. Admin rights check ──────────────────
net session >nul 2>&1
if %errorlevel% neq 0 (
    echo [ERROR] This script must be run as Administrator.
    echo         Right-click ^> "Run as administrator"
    pause
    exit /b 1
)

REM ── 2. IIS check ───────────────────────────
echo [1/8] Checking IIS ...
if not exist "%SystemRoot%\System32\inetsrv\appcmd.exe" (
    echo [ERROR] IIS does not appear to be installed.
    echo         Enable: IIS ^> CGI ^> ISAPI Extensions ^> Management Tools
    pause
    exit /b 1
)
REM Check FastCGI module
if not exist "%SystemRoot%\System32\inetsrv\fcgiext.dll" (
    if not exist "%SystemRoot%\System32\inetsrv\iisfcgi.dll" (
        echo [WARNING] FastCGI module may not be installed.
        echo            Enable: IIS ^> Application Development Features ^> CGI
    )
)
echo IIS OK.

REM ── 3. PHP check ───────────────────────────
echo.
echo [2/8] Checking PHP ...
php -v >nul 2>&1
if %errorlevel% neq 0 (
    echo [ERROR] PHP not found in PATH.
    echo         Add PHP to PATH or use deploy-cpanel.bat instead.
    pause
    exit /b 1
)
REM No "!" in this command: EnableDelayedExpansion eats it and inverts the check
php -r "exit(extension_loaded('sqlite3') ? 0 : 1);" >nul 2>&1
if %errorlevel% neq 0 (
    echo [ERROR] PHP SQLite3 extension not enabled.
    pause
    exit /b 1
)
echo PHP OK.

REM ── 3b. Build database (seeder) ─────────────
echo.
echo [3/8] Building database (schema + seeder) ...
if exist "%SOURCE_DIR%\data\smartquran.db" del "%SOURCE_DIR%\data\smartquran.db"
php "%SOURCE_DIR%\run_install.php" >nul
if %errorlevel% neq 0 (
    echo [ERROR] Database build failed. Run "php run_install.php" manually to see the error.
    pause
    exit /b 1
)
echo Database built.

REM ── 4. Recycle port 8885 ───────────────────
echo.
echo [4/8] Recycling port %PORT% ...
REM Stop any existing IIS site on this port
for /f "tokens=*" %%a in ('"%SystemRoot%\System32\inetsrv\appcmd.exe" list site /bindings:http/*:%PORT%: 2^>nul') do (
    for /f tokens^=2^ delims^=^" %%b in ("%%a") do (
        echo         Stopping existing IIS site "%%b" ...
        "%SystemRoot%\System32\inetsrv\appcmd.exe" stop site /site.name:"%%b" >nul 2>&1
        timeout /t 1 >nul
    )
)
REM Kill any non-IIS process still holding the port
for /f "tokens=5" %%a in ('netstat -ano ^| findstr ":%PORT%" ^| findstr /V "LISTENING"') do (
    taskkill /PID %%a /F >nul 2>&1
)
for /f "tokens=5" %%a in ('netstat -ano ^| findstr ":%PORT%"') do (
    taskkill /PID %%a /F >nul 2>&1
)
echo Port %PORT% freed.

REM ── 5. Recycle / create App Pool ───────────
echo.
echo [5/8] Configuring Application Pool ...
"%SystemRoot%\System32\inetsrv\appcmd.exe" list apppool /name:%APP_POOL% >nul 2>&1
if %errorlevel% equ 0 (
    echo         Recycling existing app pool %APP_POOL% ...
    "%SystemRoot%\System32\inetsrv\appcmd.exe" stop apppool /apppool.name:%APP_POOL% >nul 2>&1
    timeout /t 1 >nul
    "%SystemRoot%\System32\inetsrv\appcmd.exe" delete apppool /apppool.name:%APP_POOL% >nul 2>&1
)
"%SystemRoot%\System32\inetsrv\appcmd.exe" add apppool /name:%APP_POOL% /managedRuntimeVersion:"" /processModel.identityType:ApplicationPoolIdentity >nul 2>&1
if %errorlevel% neq 0 (
    echo [ERROR] Failed to create application pool.
    pause
    exit /b 1
)
echo App pool "%APP_POOL%" ready.

REM ── 5b. Register PHP FastCGI handler ───────
echo.
echo [5b/8] Registering PHP FastCGI handler ...
for /f "delims=" %%p in ('where php-cgi.exe 2^>nul') do (
    set "PHP_CGI=%%p"
)
if not defined PHP_CGI (
    for /f "delims=" %%p in ('dir /s /b "%ProgramFiles%\php*\php-cgi.exe" 2^>nul') do (
        set "PHP_CGI=%%p"
    )
)
if not defined PHP_CGI (
    for /f "delims=" %%p in ('dir /s /b "%ProgramFiles(x86)%\php*\php-cgi.exe" 2^>nul') do (
        set "PHP_CGI=%%p"
    )
)
if not defined PHP_CGI (
    for /f "delims=" %%p in ('dir /s /b "C:\php*\php-cgi.exe" 2^>nul') do (
        set "PHP_CGI=%%p"
    )
)
if defined PHP_CGI (
    echo         Found php-cgi.exe at: !PHP_CGI!
    REM Add FastCGI application if not exists
    "%SystemRoot%\System32\inetsrv\appcmd.exe" set config /section:system.webServer/fastCgi /+[fullPath='!PHP_CGI!'] >nul 2>&1
    REM Add handler mapping for *.php
    "%SystemRoot%\System32\inetsrv\appcmd.exe" set config "%SITE_NAME%" /section:system.webServer/handlers /+[name='PHP_via_FastCGI',path='*.php',verb='GET,HEAD,POST',modules='FastCgiModule',scriptProcessor='!PHP_CGI!',resourceType='Either',requireAccess='Script'] /commit:apphost >nul 2>&1
    if %errorlevel% neq 0 (
        echo [WARNING] Could not auto-register handler. Try manual setup:
        echo           IIS Manager ^> Sites ^> %SITE_NAME% ^> Handler Mappings ^> Add Module Mapping
    ) else (
        echo         PHP FastCGI handler registered.
    )
) else (
    echo [WARNING] php-cgi.exe not found. Please set FastCGI manually:
    echo           IIS Manager ^> Sites ^> %SITE_NAME% ^> Handler Mappings ^> Add Module Mapping
    echo           Request path: *.php
    echo           Module: FastCgiModule
    echo           Executable: C:\Path\To\php-cgi.exe
)

REM ── 6. Recycle / create Site ───────────────
echo.
echo [6/8] Configuring IIS Site ...
"%SystemRoot%\System32\inetsrv\appcmd.exe" list site /name:%SITE_NAME% >nul 2>&1
if %errorlevel% equ 0 (
    echo         Recycling existing site %SITE_NAME% ...
    "%SystemRoot%\System32\inetsrv\appcmd.exe" stop site /site.name:%SITE_NAME% >nul 2>&1
    timeout /t 1 >nul
    "%SystemRoot%\System32\inetsrv\appcmd.exe" delete site /site.name:%SITE_NAME% >nul 2>&1
)
"%SystemRoot%\System32\inetsrv\appcmd.exe" add site /name:%SITE_NAME% /bindings:http/*:%PORT%: /physicalPath:"%SOURCE_DIR%" >nul 2>&1
if %errorlevel% neq 0 (
    echo [ERROR] Failed to create IIS site.
    pause
    exit /b 1
)
"%SystemRoot%\System32\inetsrv\appcmd.exe" set site /site.name:%SITE_NAME% /[path='/'].applicationPool:%APP_POOL% >nul 2>&1
echo Site "%SITE_NAME%" created on port %PORT%.

REM ── 7. Directory browsing ──────────────────
echo.
echo [7/8] Enabling directory browsing (for CSS/JS assets) ...
"%SystemRoot%\System32\inetsrv\appcmd.exe" set config "%SITE_NAME%" /section:directoryBrowse /enabled:true /commit:apphost >nul 2>&1
echo Directory browsing enabled.

REM ── 8. Permissions ─────────────────────────
echo.
echo [8/8] Setting folder permissions ...
icacls "%SOURCE_DIR%" /grant IIS_IUSRS:(OI)(CI)RX /T >nul 2>&1
icacls "%SOURCE_DIR%\data" /grant IIS_IUSRS:(OI)(CI)M /T >nul 2>&1
echo Permissions granted to IIS_IUSRS.

REM ── 9. Start site ──────────────────────────
echo.
echo Starting site ...
"%SystemRoot%\System32\inetsrv\appcmd.exe" start site /site.name:%SITE_NAME% >nul 2>&1
"%SystemRoot%\System32\inetsrv\appcmd.exe" start apppool /apppool.name:%APP_POOL% >nul 2>&1

REM ── 10. Verify ─────────────────────────────
echo.
echo ============================================
echo  DEPLOY COMPLETE
echo ============================================
echo.
echo  URL      : http://localhost:%PORT%/
echo  Physical : %SOURCE_DIR%
echo  Pool     : %APP_POOL%
echo  Database : Built automatically (see step 3/8 above)
echo.
if defined PHP_CGI (
    echo  PHP CGI  : !PHP_CGI!
    echo  Handler  : Auto-registered via FastCGI
) else (
    echo  NOTE: PHP FastCGI handler NOT auto-registered.
    echo        Please register manually in IIS Manager:
    echo        IIS ^> Sites ^> %SITE_NAME% ^> Handler Mappings ^> Add Module Mapping
    echo        Request path: *.php
    echo        Module: FastCgiModule
    echo        Executable: C:\Path\To\php-cgi.exe
)
echo.
pause
