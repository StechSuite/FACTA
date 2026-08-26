<?php
/**
 * FACTA — Main Router
 * Single entry point, SPA-like with PHP
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

// Detect current page
$page = $_GET['page'] ?? 'home';
$validPages = ['home','surah','juz','search','topics','topic','bookmarks','settings','ai_chat','install','kurator','auth','profile','guide'];
if (!in_array($page, $validPages)) $page = 'home';

// Check database exists (except for install page)
if ($page !== 'install') {
    $dbFile = __DIR__ . '/data/smartquran.db';
    if (!file_exists($dbFile)) {
        header('Location: install.php');
        exit;
    }
}

// Auth API actions bypass header/footer (API-like handler: JSON or
// redirect responses) — explicit ?page=auth&action=..., or the implicit
// Google OAuth callback (?code=...&state=..., no page param at all,
// since that's what's registered as the redirect_uri). Bare ?page=auth
// with no action falls through to the normal page router below, so
// pages/auth.php (the login form UI) actually renders.
$isImplicitOAuthCallback = !empty($_GET['code']) && !empty($_GET['state']);
if ($isImplicitOAuthCallback || ($page === 'auth' && !empty($_GET['action']))) {
    require_once __DIR__ . '/api/auth.php';
    exit;
}

// Include header
require_once __DIR__ . '/includes/header.php';

// Route to page
$pageFile = __DIR__ . '/pages/' . $page . '.php';
if (file_exists($pageFile)) {
    require_once $pageFile;
} else {
    echo '<div class="empty-state"><div class="icon">📄</div><h3>Halaman tidak ditemukan</h3></div>';
}

// Include footer
require_once __DIR__ . '/includes/footer.php';
