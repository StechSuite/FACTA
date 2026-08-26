<?php
/**
 * FACTA — Settings Page
 */

$settings = Database::query("SELECT * FROM settings ORDER BY key");

$cTheme = $_COOKIE['theme'] ?? 'dark';
$cUiLang = $_COOKIE['ui_lang'] ?? 'id';
$cTransLang = $_COOKIE['translation_lang'] ?? 'id';
$cShowArabic = $_COOKIE['show_arabic'] ?? '1';
$cShowTranslation = $_COOKIE['show_translation'] ?? '1';
$cShowTajweed = $_COOKIE['show_tajweed'] ?? '0';
$cAudioReciter = $_COOKIE['audio_reciter'] ?? '1';
$cReadingMode = $_COOKIE['reading_mode'] ?? 'paged';
$cBrowseMode = $_COOKIE['browse_mode'] ?? 'slider';
$cAyatPerSlide = $_COOKIE['book_ayat_per_slide'] ?? 'auto';
?>

<div class="fade-in">
  <div class="card" style="margin-bottom:24px">
    <div class="card-header">
      <div class="card-title"><span class="icon">⚙️</span> <?=t('settings')?></div>
    </div>
  </div>

  <div style="max-width:600px">
    <!-- Appearance -->
    <div class="settings-group">
      <div class="settings-label">🎨 Tampilan</div>

      <div class="settings-row">
        <div>
          <div class="label"><?=t('dark_mode')?> / <?=t('light_mode')?></div>
          <div class="desc">Ganti tema terang/gelap</div>
        </div>
        <div class="control">
          <div class="toggle-switch <?=$cTheme==='light'?'':'on'?>" id="themeToggleSetting" onclick="toggleThemeSetting()"></div>
        </div>
      </div>

      <div class="settings-row">
        <div>
          <div class="label"><?=t('font_size')?> Arab</div>
          <div class="desc">Ukuran teks Arab di 📕 <?=t('book_mode')?></div>
        </div>
        <div class="control">
          <input type="range" class="range-slider" min="18" max="48" value="<?=$_COOKIE['font_size_ar']??28?>" id="fontSizeAr" onchange="saveSetting('font_size_ar',this.value)">
        </div>
      </div>

      <div class="settings-row">
        <div>
          <div class="label"><?=t('font_size')?> Terjemahan</div>
          <div class="desc">Ukuran terjemahan di 📕 <?=t('book_mode')?></div>
        </div>
        <div class="control">
          <input type="range" class="range-slider" min="12" max="24" value="<?=$_COOKIE['font_size_translation']??16?>" id="fontSizeTrans" onchange="saveSetting('font_size_translation',this.value)">
        </div>
      </div>
    </div>

    <!-- Language -->
    <div class="settings-group">
      <div class="settings-label">🌐 <?=t('language')?></div>

      <div class="settings-row">
        <div>
          <div class="label">Bahasa UI</div>
          <div class="desc">Bahasa antarmuka aplikasi</div>
        </div>
        <div class="control">
          <select class="lang-select" id="uiLangSelect" onchange="saveSetting('ui_language',this.value)">
            <option value="ar" <?=$cUiLang==='ar'?'selected':''?>>العربية</option>
            <option value="en" <?=$cUiLang==='en'?'selected':''?>>English</option>
            <option value="id" <?=$cUiLang==='id'?'selected':''?>>Indonesia</option>
            <option value="su" <?=$cUiLang==='su'?'selected':''?>>Basa Sunda</option>
            <option value="jv" <?=$cUiLang==='jv'?'selected':''?>>Basa Jawa</option>
          </select>
        </div>
      </div>

      <div class="settings-row">
        <div>
          <div class="label">Bahasa Terjemahan</div>
          <div class="desc">Terjemahan default saat membaca</div>
        </div>
        <div class="control">
          <select class="lang-select" id="transLangSelect" onchange="saveSetting('translation_lang',this.value)">
            <option value="en" <?=$cTransLang==='en'?'selected':''?>>English</option>
            <option value="id" <?=$cTransLang==='id'?'selected':''?>>Indonesia</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Display -->
    <div class="settings-group">
      <div class="settings-label">📖 Tampilan Bacaan</div>

      <div class="settings-row">
        <div>
          <div class="label">Teks Arab</div>
        </div>
        <div class="control">
          <div class="toggle-switch <?=$cShowArabic==='0'?'':'on'?>" onclick="toggleSetting('show_arabic',this)"></div>
        </div>
      </div>

      <div class="settings-row">
        <div>
          <div class="label">Terjemahan</div>
        </div>
        <div class="control">
          <div class="toggle-switch <?=$cShowTranslation==='0'?'':'on'?>" onclick="toggleSetting('show_translation',this)"></div>
        </div>
      </div>

      <div class="settings-row">
        <div>
          <div class="label">Tajwid Warna</div>
        </div>
        <div class="control">
          <div class="toggle-switch <?=$cShowTajweed==='1'?'on':''?>" onclick="toggleSetting('show_tajweed',this)"></div>
        </div>
      </div>

      <div class="settings-row">
        <div>
          <div class="label"><?=t('reading_mode')?></div>
          <div class="desc"><?=t('reading_mode_full')?> / <?=t('reading_mode_paged')?> (20 ayat)</div>
        </div>
        <div class="control">
          <select class="lang-select" id="readingModeSelect" onchange="saveSetting('reading_mode',this.value)">
            <option value="full" <?=$cReadingMode==='full'?'selected':''?>><?=t('reading_mode_full')?></option>
            <option value="paged" <?=$cReadingMode==='paged'?'selected':''?>><?=t('reading_mode_paged')?></option>
          </select>
        </div>
      </div>

      <div class="settings-row">
        <div>
          <div class="label"><?=t('browse_mode')?></div>
          <div class="desc"><?=t('browse_slider')?> / <?=t('browse_list')?></div>
        </div>
        <div class="control">
          <select class="lang-select" id="browseModeSelect" onchange="saveSetting('browse_mode',this.value)">
            <option value="slider" <?=$cBrowseMode==='slider'?'selected':''?>><?=t('browse_slider')?></option>
            <option value="list" <?=$cBrowseMode==='list'?'selected':''?>><?=t('browse_list')?></option>
          </select>
        </div>
      </div>

      <div class="settings-row">
        <div>
          <div class="label">📕 <?=t('book_mode')?> — <?=t('ayat_per_slide')?></div>
          <div class="desc"><?=t('auto')?> = sebanyak yang muat di layar, ayat selalu utuh</div>
        </div>
        <div class="control">
          <select class="lang-select" id="ayatPerSlideSelect" onchange="saveSetting('book_ayat_per_slide',this.value)">
            <option value="auto" <?=$cAyatPerSlide==='auto'?'selected':''?>><?=t('auto')?></option>
            <?php foreach ([1,3,5,10] as $n): ?>
            <option value="<?=$n?>" <?=$cAyatPerSlide==(string)$n?'selected':''?>><?=$n?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </div>

    <!-- Audio -->
    <div class="settings-group">
      <div class="settings-label">🔊 Audio</div>

      <div class="settings-row">
        <div>
          <div class="label">Qari Default</div>
        </div>
        <div class="control">
          <select class="lang-select" id="reciterSelect" onchange="saveSetting('audio_reciter',this.value)">
            <option value="1" <?=$cAudioReciter==='1'?'selected':''?>>Mishari Rashid Alafasy</option>
            <option value="2" <?=$cAudioReciter==='2'?'selected':''?>>Abdul Basit Murattal</option>
          </select>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function toggleThemeSetting() {
  const html = document.documentElement;
  const current = html.getAttribute('data-theme') || 'dark';
  const next = current === 'dark' ? 'light' : 'dark';
  html.setAttribute('data-theme', next);
  saveSetting('theme', next);
}

function toggleSetting(key, el) {
  const isOn = el.classList.contains('on');
  el.classList.toggle('on');
  saveSetting(key, isOn ? '0' : '1');
}

function saveSetting(key, value) {
  document.cookie = key + '=' + encodeURIComponent(value) + ';path=/;max-age=31536000';
  toast('Pengaturan disimpan', 'success');
}
</script>
