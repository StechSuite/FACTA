<?php
/**
 * FACTA — Auth Page (UI fallback)
 *
 * Router sebenarnya sudah mem-bypass halaman ini untuk action=login/callback/logout/status
 * dan langsung memanggil api/auth.php. Halaman ini menangani ?page=auth tanpa action
 * dengan redirect ke home + fallback pesan.
 */
require_once __DIR__ . '/../includes/functions.php';

$action = $_GET['action'] ?? 'status';

if ($action === 'login') {
    header('Location: api/auth.php?action=login');
    exit;
}
if ($action === 'logout') {
    header('Location: api/auth.php?action=logout');
    exit;
}
if ($action === 'callback') {
    header('Location: api/auth.php?action=callback' . ($_SERVER['QUERY_STRING'] ? '&' . preg_replace('/^action=callback&?/', '', $_SERVER['QUERY_STRING']) : ''));
    exit;
}

// Fallback: tampilkan status login sebagai UI page (bila diakses langsung)
$user = current_user();
?>

<div style="max-width:560px;margin:40px auto;text-align:center">
  <h2>🔐 Autentikasi</h2>
  <p style="color:var(--text-muted)">
    <?php if ($user): ?>
      Anda sudah login sebagai <strong><?=htmlspecialchars($user['email'])?></strong>.
      <br><br>
      <a href="index.php?page=profile" class="kurator-btn">👤 Lihat Profil</a>
      <a href="api/auth.php?action=logout" class="kurator-btn secondary">🚪 Logout</a>
    <?php else: ?>
      Silakan login untuk mengakses fitur lengkap (bookmark sync, AI Chat, Kurator, dll).
      <br><br>
      <a href="api/auth.php?action=login" class="kurator-btn">🔑 Login dengan Google</a>
    <?php endif; ?>
  </p>
</div>
