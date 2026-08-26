<?php
/**
 * FACTA — User Profile Page
 *
 * Menampilkan profil user login + form edit (whatsapp, kota, provinsi, negara).
 * Redirect ke login jika belum login (dengan pesan).
 */
require_once __DIR__ . '/../includes/functions.php';

if (!is_logged_in()) {
    echo '<div style="max-width:480px;margin:60px auto;text-align:center">';
    echo '<h2>🔐 Login Diperlukan</h2>';
    echo '<p style="color:var(--text-muted)">Silakan login dengan akun Google untuk mengakses profil Anda.</p>';
    echo '<br>';
    echo '<a href="api/auth.php?action=login" class="kurator-btn">🔑 Login dengan Google</a>';
    echo '</div>';
    return;
}

$user = current_user();
$pageTitle = 'Profil — ' . APP_NAME;
?>

<style>
.profile-card{max-width:640px;margin:0 auto;background:var(--bg-card);border:1px solid var(--border-color);border-radius:var(--radius);padding:24px}
.profile-header{display:flex;align-items:center;gap:16px;margin-bottom:20px}
.profile-avatar{width:72px;height:72px;border-radius:50%;object-fit:cover;border:2px solid var(--border-color)}
.profile-meta{flex:1}
.profile-name{font-size:18px;font-weight:700;margin:0}
.profile-email{font-size:13px;color:var(--text-muted);margin-top:2px}
.profile-roles{display:flex;gap:6px;margin-top:6px;flex-wrap:wrap}
.profile-role{font-size:11px;padding:2px 8px;border-radius:99px;background:var(--bg-secondary);border:1px solid var(--border-color);color:var(--text-secondary)}
.profile-role.admin{background:rgba(52,211,153,.12);border-color:#34d399;color:#34d399}

.profile-form{display:grid;gap:14px}
.profile-form label{font-size:13px;color:var(--text-muted);display:flex;flex-direction:column;gap:4px}
.profile-form input,.profile-form select{width:100%;padding:8px 10px;border-radius:6px;border:1px solid var(--border-color);background:var(--bg-secondary);color:var(--text-primary);font:inherit}
.profile-form input:focus,.profile-form select:focus{outline:none;border-color:var(--primary);box-shadow:0 0 0 3px rgba(99,102,241,.2)}
.profile-actions{display:flex;gap:10px;justify-content:flex-end;margin-top:10px}

#profileMsg{font-size:13px;margin-top:8px}
#profileMsg.ok{color:var(--accent-green)}
#profileMsg.err{color:var(--accent-red)}
</style>

<div class="profile-card">
  <div class="profile-header">
    <img src="<?=htmlspecialchars($user['avatar_url'] ?: 'assets/favicon.svg')?>" alt="" class="profile-avatar" id="pAvatar">
    <div class="profile-meta">
      <div class="profile-name"><?=htmlspecialchars($user['name'] ?: $user['email'])?></div>
      <div class="profile-email"><?=htmlspecialchars($user['email'])?></div>
      <div class="profile-roles">
        <?php foreach ($user['roles'] ?? [] as $role): ?>
          <span class="profile-role <?=$role?>"><?=htmlspecialchars($role)?></span>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <h3 style="margin-bottom:12px;font-size:16px">Edit Profil</h3>
  <form class="profile-form" id="frmProfile" onsubmit="return false">
    <label>Nomor WhatsApp
      <input type="text" id="pWhatsapp" placeholder="+6281234567890" value="<?=htmlspecialchars($user['whatsapp'] ?? '')?>">
    </label>
    <label>Kota
      <input type="text" id="pCity" placeholder="Jakarta" value="<?=htmlspecialchars($user['city'] ?? '')?>">
    </label>
    <label>Provinsi
      <input type="text" id="pProvince" placeholder="DKI Jakarta" value="<?=htmlspecialchars($user['province'] ?? '')?>">
    </label>
    <label>Negara
      <select id="pCountry">
        <option value="">— Pilih Negara —</option>
        <option value="ID" <?=($user['country'] ?? '')==='ID'?'selected':''?>>🇮🇩 Indonesia</option>
        <option value="MY" <?=($user['country'] ?? '')==='MY'?'selected':''?>>🇲🇾 Malaysia</option>
        <option value="SA" <?=($user['country'] ?? '')==='SA'?'selected':''?>>🇸🇦 Saudi Arabia</option>
        <option value="AE" <?=($user['country'] ?? '')==='AE'?'selected':''?>>🇦🇪 UAE</option>
        <option value="SG" <?=($user['country'] ?? '')==='SG'?'selected':''?>>🇸🇬 Singapore</option>
        <option value="US" <?=($user['country'] ?? '')==='US'?'selected':''?>>🇺🇸 United States</option>
        <option value="GB" <?=($user['country'] ?? '')==='GB'?'selected':''?>>🇬🇧 United Kingdom</option>
        <option value="TR" <?=($user['country'] ?? '')==='TR'?'selected':''?>>🇹🇷 Türkiye</option>
        <option value="PK" <?=($user['country'] ?? '')==='PK'?'selected':''?>>🇵🇰 Pakistan</option>
        <option value="BD" <?=($user['country'] ?? '')==='BD'?'selected':''?>>🇧🇩 Bangladesh</option>
        <option value="EG" <?=($user['country'] ?? '')==='EG'?'selected':''?>>🇪🇬 Egypt</option>
        <option value="OTHER" <?=($user['country'] ?? '')==='OTHER'?'selected':''?>>Lainnya</option>
      </select>
    </label>
    <div class="profile-actions">
      <span id="profileMsg"></span>
      <button class="kurator-btn" id="btnSaveProfile">💾 Simpan</button>
    </div>
  </form>

  <hr style="border-color:var(--border-color);margin:20px 0">

  <div style="display:flex;justify-content:space-between;align-items:center">
    <span style="font-size:13px;color:var(--text-muted)">Akun Google — <?=htmlspecialchars($user['email'])?></span>
    <a href="index.php?page=auth&action=logout" class="kurator-btn secondary">🚪 Logout</a>
  </div>
</div>

<script>
(function() {
  const API = 'api/user.php';
  const msg = document.getElementById('profileMsg');

  function showOk(txt) { msg.textContent = txt; msg.className = 'ok'; }
  function showErr(txt) { msg.textContent = txt; msg.className = 'err'; }

  document.getElementById('btnSaveProfile').addEventListener('click', async function() {
    const body = {
      action: 'update',
      whatsapp: document.getElementById('pWhatsapp').value.trim(),
      city: document.getElementById('pCity').value.trim(),
      province: document.getElementById('pProvince').value.trim(),
      country: document.getElementById('pCountry').value,
    };
    try {
      const res = await fetch(API, { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(body) });
      const d = await res.json();
      if (!res.ok || d.error) { showErr(d.error || 'Gagal menyimpan'); return; }
      showOk('✓ Profil berhasil disimpan.');
    } catch(e) {
      showErr('Gagal menyimpan: ' + e.message);
    }
  });
})();
</script>
