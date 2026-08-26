<?php
/**
 * FACTA — Google OAuth 2.0 Handler
 *
 * Endpoints (all GET/POST via page router index.php?page=auth):
 *   ?action=login      → redirect ke Google OAuth consent screen
 *   ?action=callback   → handle redirect dari Google, buat/update user, set cookie
 *   ?action=logout     → hapus session + cookie
 *   ?action=status     → JSON: apakah user sudah login, nama, email, roles
 *
 * Environment vars:
 *   GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET (or set in $GOOGLE_OAUTH in config.php)
 */
require_once __DIR__ . '/../includes/functions.php';

// Start session (needed for state param)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$action = $_GET['action'] ?? ($_POST['action'] ?? 'status');

// Implicit callback: Google redirect ke redirect_uri tanpa ?page=auth&action=callback
// (karena Google Console hanya mengizinkan exact-match URI tanpa query string tambahan).
// Jika kita melihat ?code=... + ?state=..., anggap itu callback.
$isImplicitCallback = ($action !== 'callback' && !empty($_GET['code']) && !empty($_GET['state']));
if ($isImplicitCallback) {
    $action = 'callback';
}

if ($action === 'status') {
    $user = current_user();
    if ($user) {
        json_response([
            'logged_in' => true,
            'user' => [
                'id'    => $user['id'],
                'email' => $user['email'],
                'name'  => $user['name'],
                'avatar'=> $user['avatar_url'] ?? null,
                'roles' => $user['roles'] ?? [],
            ]
        ]);
    }
    json_response(['logged_in' => false]);
}

if ($action === 'logout') {
    $user = current_user();
    if ($user) {
        Database::exec("UPDATE users SET auth_token = NULL, token_expires = NULL WHERE id = ?", [$user['id']]);
        log_user_action('logout');
    }
    setcookie('auth_token', '', [
        'expires' => time() - 3600,
        'path'    => '/',
        'samesite'=> 'Lax',
        'secure'  => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly'=> true,
    ]);
    // Also clear admin_secret for clean slate
    setcookie('admin_secret', '', [
        'expires' => time() - 3600,
        'path'    => '/',
        'samesite'=> 'Lax',
    ]);
    if (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) {
        json_response(['ok' => true]);
    }
    header('Location: index.php');
    exit;
}

// ---- Google OAuth helpers ----
function get_google_config(): array {
    global $GOOGLE_OAUTH;
    $clientId     = $GOOGLE_OAUTH['client_id']     ?: (getenv('GOOGLE_CLIENT_ID')     ?: '');
    $clientSecret = $GOOGLE_OAUTH['client_secret'] ?: (getenv('GOOGLE_CLIENT_SECRET') ?: '');
    return [
        'client_id'     => $clientId,
        'client_secret' => $clientSecret,
        'redirect_uri'  => $GOOGLE_OAUTH['redirect_uri'],
        'scopes'        => $GOOGLE_OAUTH['scopes'],
    ];
}

function google_auth_url(array $cfg, string $state): string {
    $params = [
        'client_id'     => $cfg['client_id'],
        'redirect_uri'  => $cfg['redirect_uri'],
        'response_type' => 'code',
        'scope'         => implode(' ', $cfg['scopes']),
        'state'         => $state,
        'access_type'   => 'online',
        'prompt'        => 'select_account',
    ];
    return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
}

function _google_curl_ca(): string {
    $ca = __DIR__ . '/../data/cacert.pem';
    return file_exists($ca) ? $ca : '';
}

function google_token_exchange(array $cfg, string $code): array {
    $post = [
        'code'          => $code,
        'client_id'     => $cfg['client_id'],
        'client_secret' => $cfg['client_secret'],
        'redirect_uri'  => $cfg['redirect_uri'],
        'grant_type'    => 'authorization_code',
    ];
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    $ca = _google_curl_ca();
    if ($ca) { curl_setopt($ch, CURLOPT_CAINFO, $ca); }
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($err) throw new Exception('cURL error: ' . $err);
    if ($http >= 400) throw new Exception('Google token error: ' . $resp);
    $data = json_decode($resp, true);
    if (empty($data['access_token'])) throw new Exception('No access_token from Google');
    return $data;
}

function google_userinfo(string $accessToken): array {
    $ch = curl_init('https://www.googleapis.com/oauth2/v2/userinfo');
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    $ca = _google_curl_ca();
    if ($ca) { curl_setopt($ch, CURLOPT_CAINFO, $ca); }
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($err) throw new Exception('cURL error: ' . $err);
    if ($http >= 400) throw new Exception('Google userinfo error: ' . $resp);
    $data = json_decode($resp, true);
    if (empty($data['id']) || empty($data['email'])) throw new Exception('Incomplete userinfo');
    return $data;
}

function generate_token(): string {
    return bin2hex(random_bytes(32));
}

// ---- Login redirect ----
if ($action === 'login') {
    $cfg = get_google_config();
    if (!$cfg['client_id'] || !$cfg['client_secret']) {
        if (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) {
            json_response(['error' => 'Google OAuth belum dikonfigurasi. Set GOOGLE_CLIENT_ID dan GOOGLE_CLIENT_SECRET.'], 503);
        }
        die('<p style="padding:40px;text-align:center">Google OAuth belum dikonfigurasi. Hubungi admin.</p>');
    }
    $state = bin2hex(random_bytes(16));
    $_SESSION['oauth_state'] = $state;
    $url = google_auth_url($cfg, $state);
    // Google Console hanya mengizinkan redirect_uri tanpa query string, jadi kita
    // harus redirect ke index.php tanpa query, lalu router akan menangkap ?code=...
    // sebagai implicit callback (karena code + state masuk via $_GET). Karena itu,
    // kita TIDAK append redirect_uri di sini — sudah exact match di config.
    header('Location: ' . $url);
    exit;
}

// ---- Callback ----
if ($action === 'callback') {
    $code  = $_GET['code']  ?? null;
    $state = $_GET['state'] ?? null;
    $error = $_GET['error'] ?? null;

    if ($error) {
        die('<p style="padding:40px;text-align:center">Login dibatalkan/ditolak oleh Google: ' . htmlspecialchars($error) . '</p>');
    }
    if (!$code || !$state || !isset($_SESSION['oauth_state']) || !hash_equals($_SESSION['oauth_state'], $state)) {
        die('<p style="padding:40px;text-align:center">Invalid state parameter. Silakan coba lagi.</p>');
    }
    unset($_SESSION['oauth_state']);

    $cfg = get_google_config();
    try {
        $tokenData = google_token_exchange($cfg, $code);
        $gUser     = google_userinfo($tokenData['access_token']);
    } catch (Exception $e) {
        die('<p style="padding:40px;text-align:center">Gagal login: ' . htmlspecialchars($e->getMessage()) . '</p>');
    }

    $googleId   = $gUser['id'];
    $email      = $gUser['email'];
    $name       = $gUser['name']  ?? $email;
    $avatar     = $gUser['picture'] ?? null;

    // Upsert user
    $existing = Database::queryOne("SELECT * FROM users WHERE email = ?", [$email]);
    $authToken = generate_token();
    $expires   = date('Y-m-d H:i:s', time() + 60 * 60 * 24 * 30); // 30 days

    if ($existing) {
        Database::exec(
            "UPDATE users SET google_id = ?, name = ?, avatar_url = ?, auth_token = ?, token_expires = ?, last_login_at = datetime('now') WHERE id = ?",
            [$googleId, $name, $avatar, $authToken, $expires, $existing['id']]
        );
        $userId = $existing['id'];
    } else {
        Database::exec(
            "INSERT INTO users (google_id, email, name, avatar_url, auth_token, token_expires, last_login_at) VALUES (?, ?, ?, ?, ?, ?, datetime('now'))",
            [$googleId, $email, $name, $avatar, $authToken, $expires]
        );
        $userId = Database::lastInsertId();
        // Auto-grant role 'user' untuk registrasi baru
        $roleUser = Database::queryOne("SELECT id FROM roles WHERE name = 'user'");
        if ($roleUser) {
            Database::exec("INSERT OR IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)", [$userId, $roleUser['id']]);
        }
    }

    // Hardcoded: email hendi135@gmail.com pasti dapat role admin (idempotent)
    if ($email === 'hendi135@gmail.com') {
        $roleAdmin = Database::queryOne("SELECT id FROM roles WHERE name = 'admin'");
        if ($roleAdmin) {
            Database::exec("INSERT OR IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)", [$userId, $roleAdmin['id']]);
        }
    }

    log_user_action('login', 'users', $userId, ['email' => $email, 'provider' => 'google']);

    setcookie('auth_token', $authToken, [
        'expires'  => time() + 60 * 60 * 24 * 30,
        'path'     => '/',
        'samesite' => 'Lax',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
    ]);

    header('Location: index.php');
    exit;
}

json_response(['error' => 'Unknown action'], 400);
