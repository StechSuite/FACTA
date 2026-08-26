<?php
/**
 * FACTA — Header Template
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/i18n.php';

// Start session for auth state (idempotent)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentLang = current_lang();
$currentDir = lang_dir();
$currentUser = current_user();
?>
<!DOCTYPE html>
<html lang="<?=$currentLang?>" dir="<?=$currentDir?>" data-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="description" content="FACTA — Finding Association in Collection of Text Alquran">
<title><?=htmlspecialchars(APP_NAME)?> — Finding Association in Collection of Text Alquran</title>
<link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
<?php /* cache-bust by file mtime, not APP_VERSION — the constant can lag behind
         asset edits, leaving browsers on stale CSS/JS (bit us in v1.01.Alpha.004) */ ?>
<link rel="stylesheet" href="assets/css/style.css?v=<?=filemtime(__DIR__ . '/../assets/css/style.css')?>">
<?php
// Font-size settings (Settings sliders / Book Mode A-/A+ write these
// cookies). The vars are consumed by BOOK MODE styles only — the normal
// reading view keeps fixed sizes. Clamped to the sliders' ranges.
$fsAr = max(18, min(48, (int)($_COOKIE['font_size_ar'] ?? 28)));
$fsTr = max(12, min(24, (int)($_COOKIE['font_size_translation'] ?? 16)));
?>
<style>:root{--font-ar:<?=$fsAr?>px;--font-tr:<?=$fsTr?>px}</style>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&display=swap" rel="stylesheet">
<script>
const BASE_URL='<?=BASE_URL?>',UI_LANG='<?=$currentLang?>',UI_DIR='<?=$currentDir?>';
const I18N_JS=<?=json_encode([
    'word_info' => t('word_info'),
    'derived_forms' => t('derived_forms'),
    'synonyms' => t('synonyms'),
    'antonyms' => t('antonyms'),
    'related_words' => t('related_words'),
    'associations' => t('associations'),
    'show_ayahs' => t('show_ayahs'),
    'only_this_combo' => t('only_this_combo'),
    'tree_view' => t('tree_view'),
    'breadcrumb_view' => t('breadcrumb_view'),
    'assoc_expand' => t('assoc_expand'),
    'assoc_search_placeholder' => t('assoc_search_placeholder'),
    'keep_typing' => t('keep_typing'),
    'and_search_go' => t('and_search_go'),
    'root_search' => t('root_search'),
    'no_results' => t('no_results'),
    'loading' => t('loading'),
    'morphology_disclaimer' => t('morphology_disclaimer'),
], JSON_UNESCAPED_UNICODE)?>;
// Language switcher
document.addEventListener('DOMContentLoaded', function(){
  const ls=document.getElementById('langSelect');
  if(ls){
    ls.addEventListener('change',function(e){
      const lang=e.target.value;
      document.cookie='ui_lang='+encodeURIComponent(lang)+';path=/;max-age='+(60*60*24*365);
      const url=new URL(location.href);
      url.searchParams.set('lang',lang);
      location.href=url.toString();
    });
  }
});
</script>
</head>
<body>
<div class="app" id="app">

<!-- Header -->
<header class="header">
  <div class="header-left">
    <button class="logo-icon menu-toggle-logo" id="menuToggle" title="Toggle sidebar" type="button">
      <img src="assets/favicon.svg" alt="<?=htmlspecialchars(APP_NAME)?>" width="26" height="26">
    </button>
    <a href="index.php" class="logo">
      <span><?=t('app_name')?></span>
    </a>
  </div>

  <div class="header-search">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
    <input type="text" id="globalSearch" placeholder="<?=t('search_placeholder')?>" autocomplete="off"
           data-no-results="<?=htmlspecialchars(t('no_results'))?>" data-see-all="<?=htmlspecialchars(t('see_all_results'))?>">
    <div class="instant-search-panel" id="instantSearchPanel"></div>
  </div>
  <!-- Mobile: search icon redirects to dedicated search page -->
  <a href="index.php?page=search" class="header-search-mobile" title="<?=t('search')?>" aria-label="<?=t('search')?>">
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
  </a>

  <div class="header-right">
    <select class="lang-select" id="langSelect" title="Language">
      <?php foreach ($UI_LANGUAGES as $code => $info): ?>
      <option value="<?=$code?>" <?=$code===$currentLang?'selected':''?>><?=$info['name']?></option>
      <?php endforeach; ?>
    </select>

    <button class="header-btn" id="themeToggle" title="Toggle theme">🌙</button>

    <a href="index.php?page=bookmarks" class="header-btn" title="<?=t('bookmarks')?>">
      🔖<span class="badge" id="bookmarkCount" style="display:none">0</span>
    </a>

    <a href="index.php?page=guide" class="header-btn" title="<?=t('help_guide')?>">
      ❓
    </a>

    <a href="index.php?page=settings" class="header-btn" title="<?=t('settings')?>">
      ⚙️
    </a>

    <?php if ($currentUser): ?>
      <div class="auth-profile" data-user-id="<?=$currentUser['id']?>">
        <a href="index.php?page=profile" title="Profil" style="display:flex;align-items:center;gap:4px;color:inherit;text-decoration:none">
          <img class="avatar" src="<?=htmlspecialchars($currentUser['avatar_url'] ?: 'assets/favicon.svg')?>" alt="">
          <span class="name"><?=htmlspecialchars($currentUser['name'] ?: $currentUser['email'])?></span>
        </a>
        <?php if (in_array('admin', $currentUser['roles'] ?? [])): ?>
          <span class="badge-role" title="Admin">admin</span>
        <?php endif; ?>
        <a href="index.php?page=auth&action=logout" class="logout" title="Logout">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg>
        </a>
      </div>
    <?php elseif (is_admin()): ?>
      <div class="auth-profile">
        <a href="index.php?page=kurator" title="Kurator" style="display:flex;align-items:center;gap:4px;color:inherit;text-decoration:none">
          <span class="name">admin</span>
        </a>
        <span class="badge-role" title="Admin">admin</span>
        <a href="index.php?page=auth&action=logout" class="logout" title="Logout">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg>
        </a>
      </div>
    <?php else: ?>
      <a href="index.php?page=auth" class="login-chip" title="Login">
        🔑 Login
      </a>
    <?php endif; ?>
  </div>
</header>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
  <div class="nav-section">
    <div class="nav-label">📚 <?=t('browse')?></div>
    <a href="index.php" class="nav-item <?=$page==='home'?'active':''?>">
      <span class="icon">🏠</span>
      <span class="text"><?=t('browse')?></span>
    </a>
    <a href="index.php?page=juz" class="nav-item <?=$page==='juz'?'active':''?>">
      <span class="icon">📑</span>
      <span class="text"><?=t('juz')?></span>
      <span class="count">30</span>
    </a>
  </div>

  <div class="nav-section">
    <div class="nav-label">🔍 <?=t('discover')?></div>
    <a href="index.php?page=search" class="nav-item <?=$page==='search'?'active':''?>">
      <span class="icon">🔎</span>
      <span class="text"><?=t('search')?></span>
    </a>
    <a href="index.php?page=topics" class="nav-item <?=$page==='topics'?'active':''?>">
      <span class="icon">🌳</span>
      <span class="text"><?=t('topics')?></span>
    </a>
  </div>

  <div class="nav-section">
    <div class="nav-label">🧠 AI</div>
    <a href="index.php?page=ai_chat" class="nav-item <?=$page==='ai_chat'?'active':''?>">
      <span class="icon">🤖</span>
      <span class="text"><?=t('ai_chat')?></span>
    </a>
  </div>

<?php if (is_admin() || is_curator()): ?>
  <div class="nav-section">
    <div class="nav-label">🔐 <?=t('kurator')?></div>
    <a href="index.php?page=kurator&tab=roots" class="nav-item <?=$page==='kurator'&&($_GET['tab']??'')==='roots'?'active':''?>">
      <span class="icon">🕋</span>
      <span class="text"><?=t('root_words')?></span>
    </a>
    <a href="index.php?page=kurator&tab=sarf" class="nav-item <?=$page==='kurator'&&($_GET['tab']??'')==='sarf'?'active':''?>">
      <span class="icon">🧬</span>
      <span class="text"><?=t('sarf_patterns')?></span>
    </a>
  </div>
<?php endif; ?>

  <div class="nav-section" style="margin-top:auto;padding-top:20px;border-top:1px solid var(--border-color)">
    <a href="index.php?page=bookmarks" class="nav-item <?=$page==='bookmarks'?'active':''?>">
      <span class="icon">🔖</span>
      <span class="text"><?=t('bookmarks')?></span>
    </a>
    <a href="index.php?page=settings" class="nav-item <?=$page==='settings'?'active':''?>">
      <span class="icon">⚙️</span>
      <span class="text"><?=t('settings')?></span>
    </a>
  </div>

  <div class="sidebar-version" title="<?=htmlspecialchars(APP_NAME)?> v<?=htmlspecialchars(get_app_version())?>">v<?=htmlspecialchars(get_app_version())?></div>
</aside>

<!-- Overlay for mobile -->
<div class="overlay" id="overlay"></div>

<!-- Main Content -->
<main class="main" id="main">
