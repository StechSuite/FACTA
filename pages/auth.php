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
$googleEnabled = $GOOGLE_OAUTH['enabled'] ?? false;
$error = $_GET['error'] ?? null;
?>

<div style="max-width:560px;margin:40px auto;text-align:center">
  <h2>🔐 Autentikasi</h2>

  <?php if ($user): ?>
  <p style="color:var(--text-muted)">
    Anda sudah login sebagai <strong><?=htmlspecialchars($user['email'])?></strong>.
    <br><br>
    <a href="index.php?page=profile" class="btn btn-primary">👤 Lihat Profil</a>
    <a href="api/auth.php?action=logout" class="btn btn-secondary">🚪 Logout</a>
  </p>
  <?php elseif (is_admin()): ?>
  <p style="color:var(--text-muted)">
    Anda sudah login sebagai <strong>admin</strong>.
    <br><br>
    <a href="index.php?page=kurator" class="btn btn-primary">🛠️ Buka Kurator</a>
    <a href="api/auth.php?action=logout" class="btn btn-secondary">🚪 Logout</a>
  </p>
  <?php else: ?>

    <?php if ($googleEnabled): ?>
    <p style="color:var(--text-muted)">
      Silakan login untuk mengakses fitur lengkap (bookmark sync, AI Chat, Kurator, dll).
      <br><br>
      <a href="api/auth.php?action=login" class="btn btn-primary">🔑 Login dengan Google</a>
    </p>
    <div style="margin:24px 0;color:var(--text-muted);font-size:12px">— atau —</div>
    <?php else: ?>
    <p style="color:var(--text-muted)">
      Google OAuth belum dikonfigurasi di instalasi ini (lihat <code>includes/config.php</code> /
      <code>README.md</code>). Gunakan login admin di bawah untuk mengakses Kurator.
    </p>
    <?php endif; ?>

    <?php if ($error === 'invalid'): ?>
    <p style="color:#ef4444;font-size:13px">Username atau password admin salah.</p>
    <?php endif; ?>

    <?php if (admin_using_default_credentials()): ?>
    <p style="color:#f59e0b;font-size:12px;max-width:420px;margin:0 auto 12px">
      ⚠️ Masih pakai kredensial default (<code>admin</code> / <code>bismillah</code>).
      Ganti lewat <code>config.admin.json</code> (lihat <code>config.admin.json.example</code>)
      sebelum deploy ke tempat yang bisa diakses publik.
    </p>
    <?php endif; ?>

    <form method="post" action="api/admin_login.php" style="max-width:280px;margin:0 auto;display:flex;flex-direction:column;gap:10px">
      <input type="text" name="username" placeholder="Username" autocomplete="username" required
             style="padding:10px 14px;border-radius:8px;border:1px solid var(--border-color);background:var(--bg-secondary);color:var(--text-primary)">
      <input type="password" name="password" placeholder="Password" autocomplete="current-password" required
             style="padding:10px 14px;border-radius:8px;border:1px solid var(--border-color);background:var(--bg-secondary);color:var(--text-primary)">
      <button type="submit" class="btn btn-primary">🛠️ Login Admin</button>
    </form>

  <?php endif; ?>
</div>
