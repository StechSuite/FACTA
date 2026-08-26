@echo off
REM ============================================================
REM SmartQuran — Deploy Script for cPanel (FTP upload)
REM
REM Builds the local database, then FTP-uploads the whole folder
REM to the configured cPanel target. Credentials come from
REM deploy.secrets.json (gitignored) — copy
REM deploy.secrets.json.example to that filename and fill in
REM the real values before running this script.
REM ============================================================

setlocal EnableDelayedExpansion
set "SOURCE_DIR=%~dp0"
set "SOURCE_DIR=%SOURCE_DIR:~0,-1%"

echo ============================================
echo SmartQuran cPanel Deploy Script
echo ============================================

REM ── 0. Load FTP credentials from JSON ──────
if not exist "%SOURCE_DIR%\deploy.secrets.json" (
    echo [ERROR] deploy.secrets.json not found.
    echo         Copy deploy.secrets.json.example to
    echo         deploy.secrets.json and fill in real values.
    pause
    exit /b 1
)
for /f "usebackq delims=" %%v in (`powershell -NoProfile -Command "(Get-Content '%SOURCE_DIR%\deploy.secrets.json' -Raw | ConvertFrom-Json).ftp.host"`) do set "FTP_SERVER=%%v"
for /f "usebackq delims=" %%v in (`powershell -NoProfile -Command "(Get-Content '%SOURCE_DIR%\deploy.secrets.json' -Raw | ConvertFrom-Json).ftp.user"`) do set "FTP_USER=%%v"
for /f "usebackq delims=" %%v in (`powershell -NoProfile -Command "(Get-Content '%SOURCE_DIR%\deploy.secrets.json' -Raw | ConvertFrom-Json).ftp.password"`) do set "FTP_PASSWORD=%%v"
for /f "usebackq delims=" %%v in (`powershell -NoProfile -Command "(Get-Content '%SOURCE_DIR%\deploy.secrets.json' -Raw | ConvertFrom-Json).ftp.remoteFolder"`) do set "FTP_TARGET_DIR=%%v"
if not defined FTP_SERVER (
    echo [ERROR] Could not read ftp.host from deploy.secrets.json.
    pause
    exit /b 1
)
REM Derive test URL from the target folder (strip the leading "app/" prefix)
set "TEST_URL=https://%FTP_TARGET_DIR:app/=%"

echo.
echo Source : %SOURCE_DIR%
echo Server : %FTP_SERVER%
echo Target : /%FTP_TARGET_DIR%/
echo.

REM ── 1. Checks ───────────────────────────────
echo [1/4] Checking PHP...
php -v >nul 2>&1
if errorlevel 1 (
    echo [ERROR] PHP not found in PATH.
    pause
    exit /b 1
)
REM No "!" in this command: EnableDelayedExpansion eats it and inverts the check
php -r "exit(extension_loaded('sqlite3') ? 0 : 1);" >nul 2>&1
if errorlevel 1 (
    echo [ERROR] SQLite3 extension not enabled in PHP.
    pause
    exit /b 1
)
echo OK.

REM ── 2. Sanity-check the seeder locally ─────
REM Rebuild a local copy first so a broken seed file is caught here,
REM not after uploading. The server builds its OWN database from the
REM uploaded seed files via install.php (see step 4) — smartquran.db
REM itself is a local dev artifact and is not uploaded.
echo.
echo [2/4] Verifying seeder locally ...
if exist "%SOURCE_DIR%\data\smartquran.db" del "%SOURCE_DIR%\data\smartquran.db"
php "%SOURCE_DIR%\run_install.php" >nul
if errorlevel 1 (
    echo [ERROR] Database build failed locally. Run "php run_install.php" manually to see the error.
    pause
    exit /b 1
)
echo Seeder OK.

REM ── 3. Upload via FTP (curl) ────────────────
echo.
echo [3/4] Uploading to %FTP_SERVER%/%FTP_TARGET_DIR%/ ...
echo         (this can take a few minutes — seed_words_full.sql / seed_quran_full.sql are several MB)
set "UPLOADED=0"
set "FAILED=0"
for /r "%SOURCE_DIR%" %%f in (*) do (
    set "LOCAL=%%f"
    set "REL=!LOCAL:%SOURCE_DIR%\=!"
    set "REL=!REL:\=/!"
    REM Skip local-only / generated / dev / secret files (server builds its own smartquran.db)
    echo !REL! | findstr /i /c:".git/" /c:".claude/" /c:"__pycache__/" /c:".words_cache/" /c:"deploy.secrets.json" /c:"config.keys.json" /c:"data/smartquran.db" /c:".db-shm" /c:".db-wal" /c:"data/words-kurator-by-ai/" >nul
    if errorlevel 1 (
        curl -s --ftp-create-dirs -u "%FTP_USER%:%FTP_PASSWORD%" -T "!LOCAL!" "ftp://%FTP_SERVER%/%FTP_TARGET_DIR%/!REL!"
        if errorlevel 1 (
            echo   [FAIL] !REL!
            set /a FAILED+=1
        ) else (
            set /a UPLOADED+=1
        )
    )
)
echo Uploaded: !UPLOADED! file(s), Failed: !FAILED!
if !FAILED! gtr 0 (
    echo [WARNING] Some files failed to upload — check connectivity/permissions and re-run.
)

REM ── 4. Trigger remote install ────────────────
echo.
echo [4/4] Deploy complete! One manual step left:
echo.
echo   Open %TEST_URL%/install.php in a browser and click
echo   "Jalankan Instalasi" to build the database on the server.
echo.
echo Test URL: %TEST_URL%
echo.
pause
