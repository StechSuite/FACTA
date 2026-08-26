<?php
/**
 * FACTA — Admin Login (username/password)
 *
 * This repo ships with Google OAuth disabled by default (see
 * includes/config.php) since it's tied to a specific live deployment's
 * credentials, not something a public MIT repo should hardcode. This is
 * the alternative: a simple username/password gate for the same
 * admin-only areas (Kurator, etc.) that is_admin() already protects —
 * see admin_login_credentials() in includes/functions.php for how the
 * default (admin/bismillah) can be overridden via config.admin.json.
 *
 * POST username, password → sets the same admin_secret cookie is_admin()
 * already checks, so no other admin-gated code needed to change at all.
 */
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Location: index.php?page=auth&error=method');
    exit;
}

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';
$creds = admin_login_credentials();

$ok = hash_equals($creds['username'], $username) && hash_equals($creds['password'], $password);

if (!$ok) {
    header('Location: index.php?page=auth&error=invalid');
    exit;
}

setcookie('admin_secret', ADMIN_SECRET, [
    'expires'  => time() + 60 * 60 * 24 * 30,
    'path'     => '/',
    'samesite' => 'Lax',
    'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'httponly' => true,
]);

header('Location: index.php?page=kurator');
exit;
