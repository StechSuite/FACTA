<?php
/**
 * FACTA — Surah Reader Page
 */

$surahId = (int)($_GET['id'] ?? 1);
$targetAyah = (int)($_GET['ayah'] ?? 0);
$surah = get_surah($surahId);
if (!$surah) {
    echo '<div class="empty-state"><div class="icon">📄</div><h3>Surat tidak ditemukan</h3></div>';
    return;
}

$lang = current_lang();
$ayahs = get_ayahs($surahId, $lang);
$viewMode = ($_GET['view'] ?? 'ayah') === 'word' ? 'word' : 'ayah';

// Display toggles from Settings (previously saved but never honored)
$showArabic = ($_COOKIE['show_arabic'] ?? '1') !== '0';
$showTranslation = ($_COOKIE['show_translation'] ?? '1') !== '0';

// Reading mode: 'full' (whole surah, default) or 'paged' (set in Settings)
$readingMode = ($_COOKIE['reading_mode'] ?? 'paged') === 'full' ? 'full' : 'paged';
$pageSize = 20;
$totalAyahs = count($ayahs);
$totalPages = max(1, (int)ceil($totalAyahs / $pageSize));
if (isset($_GET['ayah_page'])) {
    $currentPage = max(1, min($totalPages, (int)$_GET['ayah_page']));
} elseif ($targetAyah > 0) {
    $currentPage = max(1, min($totalPages, (int)ceil($targetAyah / $pageSize)));
} else {
    $currentPage = 1;
}
$displayAyahs = $readingMode === 'paged' ? array_slice($ayahs, ($currentPage - 1) * $pageSize, $pageSize) : $ayahs;

// Update reading progress
update_progress($surahId, $targetAyah ?: 1);

// Navigation
$prevSurah = $surahId > 1 ? get_surah($surahId - 1) : null;
$nextSurah = $surahId < 114 ? get_surah($surahId + 1) : null;
?>

<div class="fade-in">
  <!-- Surah Header -->
  <div class="surah-header">
    <?php
        $surahName = $lang === 'en' ? ($surah['name_en'] ?? $surah['name_id']) : ($surah['name_id'] ?? $surah['name_en']);
        $revelation = $surah['revelation_type'] === 'meccan' ? t('meccan') : t('medinan');
    ?>
    <div class="surah-name-ar"><?=$surah['name_ar']?></div>
    <div class="surah-name-en"><?=$surah['name_transliteration']?> — <?=$surahName?></div>
    <div class="surah-meta">
      <span>📍 <?=$revelation?></span>
      <span>📄 <?=$surah['verse_count']?> <?=t('ayah')?></span>
      <span>📑 <?=t('juz')?> <?=$surah['juz_number']?></span>
    </div>
    <?php if ($surah['number'] !== 9): // Not Surah At-Tawbah ?>
    <div class="bismillah">بِسْمِ ٱللَّهِ ٱلرَّحْمَـٰنِ ٱلرَّحِيمِ</div>
    <?php endif; ?>
    <div style="margin-top:14px;display:flex;gap:8px;justify-content:center;flex-wrap:wrap">
      <a href="?page=surah&id=<?=$surahId?>&view=ayah" class="btn <?=$viewMode==='ayah'?'btn-primary':'btn-secondary'?>">📖 <?=t('translation')?></a>
      <a href="?page=surah&id=<?=$surahId?>&view=word" class="btn <?=$viewMode==='word'?'btn-primary':'btn-secondary'?>">🔤 <?=t('word_by_word')?></a>
      <button type="button" class="btn btn-secondary" onclick="openBookMode()">📕 <?=t('book_mode')?></button>
    </div>
  </div>

  <div id="normalReading">
  <?php if ($viewMode === 'word'): ?>
  <p style="font-size:12px;color:var(--text-muted);margin:0 0 14px">ⓘ <?=t('morphology_disclaimer')?></p>
  <?php endif; ?>

  <!-- Ayahs -->
  <div class="card">
    <?php foreach ($displayAyahs as $a): ?>
    <div class="ayah-item" id="ayah-<?=$a['ayah_number']?>" data-ayah="<?=$a['ayah_number']?>">
      <div class="ayah-number"><?=to_arabic_number($a['ayah_number'])?></div>

      <?php if ($viewMode === 'word'): ?>
      <div class="word-grid">
        <?php foreach (get_ayah_words($a['id']) as $w): ?>
        <?php
          $gloss = $lang === 'en' ? $w['translation_en'] : $w['translation_id'];
          $residual = ar_guess_residual($w['text_ar_clean']);
        ?>
        <div class="word-cell">
          <?php if ($residual): ?>
          <a href="index.php?page=search&mode=root&root=<?=urlencode($residual)?>" class="word-ar" title="<?=htmlspecialchars(t('similar_words'))?>"><?=$w['text_ar']?></a>
          <?php else: ?>
          <div class="word-ar"><?=$w['text_ar']?></div>
          <?php endif; ?>
          <?php if ($w['transliteration']): ?>
          <div class="word-translit"><?=htmlspecialchars($w['transliteration'])?></div>
          <?php endif; ?>
          <?php if ($gloss): ?>
          <div class="word-gloss"><?=htmlspecialchars($gloss)?></div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <?php if ($showArabic): ?>
      <div class="ayah-text-ar"><?=$a['text_ar']?></div>
      <?php endif; ?>

      <?php if ($showTranslation && $a['translation_text']): ?>
      <div class="ayah-text-<?=$lang?>"><?=$a['translation_text']?></div>
      <?php endif; ?>

      <?php $roots = get_ayah_roots($a['id']); if ($roots): ?>
      <div class="root-chips">
        <?php foreach ($roots as $rw):
            $meaning = $lang === 'en' ? ($rw['meaning_en'] ?? $rw['meaning_id']) : ($rw['meaning_id'] ?? $rw['meaning_en']);
            $meaningOther = $lang === 'en' ? $rw['meaning_id'] : $rw['meaning_en'];
        ?>
        <span class="root-chip" title="<?=htmlspecialchars($meaningOther ?? '')?>">✓ <?=$rw['root_ar']?> — <?=$meaning?></span>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <?php endif; ?>

      <div class="ayah-actions">
        <button class="ayah-btn" data-icon="🔖" onclick="bookmarkAyah(<?=$surahId?>,<?=$a['ayah_number']?>)"><span class="label"><?=t('bookmark')?></span></button>
        <button class="ayah-btn" data-icon="📋" onclick="copyAyah(<?=$surahId?>,<?=$a['ayah_number']?>)"><span class="label"><?=t('copy')?></span></button>
        <button class="ayah-btn" data-icon="▶️" onclick="playAudio(<?=$surah['number']?>,<?=$a['ayah_number']?>)"><span class="label"><?=t('play_audio')?></span></button>
        <button class="ayah-btn" data-icon="📖" onclick="showTafsir(<?=$surahId?>,<?=$a['ayah_number']?>)"><span class="label"><?=t('tafsir')?></span></button>
        <button class="ayah-btn" data-icon="🔗" onclick="shareAyah(<?=$surahId?>,<?=$a['ayah_number']?>)"><span class="label"><?=t('share')?></span></button>
        <a href="index.php?page=topics&ayah=<?=$a['id']?>" class="ayah-btn" data-icon="🌳"><span class="label"><?=t('related_topics')?></span></a>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <?php if ($readingMode === 'paged' && $totalPages > 1): ?>
  <div style="display:flex;justify-content:center;align-items:center;gap:12px;margin-top:16px">
    <?php if ($currentPage > 1): ?>
    <a href="?page=surah&id=<?=$surahId?>&view=<?=$viewMode?>&ayah_page=<?=$currentPage-1?>" class="btn btn-secondary">← <?=t('prev_page')?></a>
    <?php else: ?>
    <span></span>
    <?php endif; ?>
    <span style="font-size:13px;color:var(--text-muted)"><?=t('page_of')?> <?=$currentPage?> / <?=$totalPages?></span>
    <?php if ($currentPage < $totalPages): ?>
    <a href="?page=surah&id=<?=$surahId?>&view=<?=$viewMode?>&ayah_page=<?=$currentPage+1?>" class="btn btn-secondary"><?=t('next_page')?> →</a>
    <?php else: ?>
    <span></span>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- Navigation -->
  <div style="display:flex;justify-content:space-between;margin-top:24px;gap:12px">
    <?php if ($prevSurah): ?>
    <a href="index.php?page=surah&id=<?=$prevSurah['id']?>" class="btn btn-secondary">
      ← <?=$prevSurah['name_transliteration']?>
    </a>
    <?php else: ?>
    <span></span>
    <?php endif; ?>

    <?php if ($nextSurah): ?>
    <a href="index.php?page=surah&id=<?=$nextSurah['id']?>" class="btn btn-secondary">
      <?=$nextSurah['name_transliteration']?> →
    </a>
    <?php endif; ?>
  </div>
  </div><!-- /normalReading -->

  <!-- Book Mode (PowerPoint-style slides: inline panel + optional fullscreen) -->
  <div class="book-mode" id="bookMode" hidden>
    <div class="book-toolbar">
      <button type="button" class="book-btn" onclick="closeBookMode()" title="<?=t('close')?>">✕</button>
      <span class="book-title">📕 <?=$surah['name_transliteration']?></span>
      <span class="book-indicator" id="bookIndicator"></span>
      <span class="book-tools">
        <button type="button" class="book-btn" onclick="bookFont(-2)" title="<?=t('font_size')?> −">A−</button>
        <button type="button" class="book-btn" onclick="bookFont(2)" title="<?=t('font_size')?> +">A+</button>
        <select class="lang-select" id="bookPerSlide" onchange="bookSetPerSlide(this.value)" title="<?=t('ayat_per_slide')?>">
          <option value="auto"><?=t('auto')?></option>
          <option value="1">1</option>
          <option value="3">3</option>
          <option value="5">5</option>
          <option value="10">10</option>
        </select>
        <button type="button" class="book-btn" onclick="bookToggleFullscreen()" title="<?=t('fullscreen')?>">⛶</button>
      </span>
    </div>
    <div class="book-viewport" id="bookViewport">
      <div class="book-track" id="bookTrack"></div>
      <button type="button" class="book-nav book-nav-prev" id="bookPrevBtn" onclick="bookPrev()">❮</button>
      <button type="button" class="book-nav book-nav-next" id="bookNextBtn" onclick="bookNext()">❯</button>
      <button type="button" class="book-share-sel" id="bookShareSel" hidden onclick="bookShareSelected()">🔗 <?=t('share')?> (<span id="bookShareSelCount">0</span>)</button>
    </div>
  </div>
</div>

<!-- Tafsir Modal -->
<div class="modal-overlay" id="tafsirModal">
  <div class="modal">
    <div class="modal-header">
      <h3>📖 <?=t('tafsir')?></h3>
      <button class="modal-close" onclick="closeModal('tafsirModal')">✕</button>
    </div>
    <div class="modal-body" id="tafsirBody">
      <div class="loading-spinner"></div>
    </div>
  </div>
</div>

<!-- Share Modal -->
<div class="modal-overlay" id="shareModal">
  <div class="modal">
    <div class="modal-header">
      <h3>🔗 <?=t('share')?></h3>
      <button class="modal-close" onclick="closeModal('shareModal')">✕</button>
    </div>
    <div class="modal-body">
      <div class="share-grid">
        <a class="share-option" id="shareWhatsapp" href="#" target="_blank" rel="noopener">💬 WhatsApp</a>
        <a class="share-option" id="shareTelegram" href="#" target="_blank" rel="noopener">✈️ Telegram</a>
        <a class="share-option" id="shareTwitter" href="#" target="_blank" rel="noopener">𝕏 Twitter</a>
        <a class="share-option" id="shareFacebook" href="#" target="_blank" rel="noopener">📘 Facebook</a>
        <a class="share-option" id="shareEmail" href="#">✉️ Email</a>
        <button type="button" class="share-option" onclick="copyShareLink()">📋 <?=t('copy_link')?></button>
      </div>
    </div>
  </div>
</div>

<script>
// Scroll to target ayah
<?php if ($targetAyah > 0): ?>
document.addEventListener('DOMContentLoaded', () => {
  const el = document.getElementById('ayah-<?=$targetAyah?>');
  if (el) el.scrollIntoView({behavior:'smooth',block:'center'});
});
<?php endif; ?>

// Splits long text into chunks (breaking at word boundaries) so the
// tafsir modal can page through it instead of needing to scroll.
function paginateText(text, chunkSize = 500) {
  if (!text || text.length <= chunkSize) return [text || ''];
  const chunks = [];
  let remaining = text;
  while (remaining.length > chunkSize) {
    let cut = remaining.lastIndexOf(' ', chunkSize);
    if (cut <= 0) cut = chunkSize;
    chunks.push(remaining.slice(0, cut).trim());
    remaining = remaining.slice(cut).trim();
  }
  if (remaining) chunks.push(remaining);
  return chunks;
}

async function showTafsir(surahId, ayahNum) {
  const modal = document.getElementById('tafsirModal');
  const body = document.getElementById('tafsirBody');
  modal.classList.add('active');
  body.innerHTML = '<div class="loading-spinner"></div>';
  try {
    const res = await fetch(`api/tafsir.php?surah_id=${surahId}&ayah=${ayahNum}`);
    const data = await res.json();
    if (data.tafsir) {
      const chunks = paginateText(data.tafsir);
      let page = 0;
      const renderPage = () => {
        const pagerHtml = chunks.length > 1 ? `
          <div style="display:flex;align-items:center;justify-content:center;gap:12px;margin-top:14px">
            <button type="button" class="slider-arrow" id="tafsirPrev">‹</button>
            <span style="font-size:12px;color:var(--text-muted)">${page + 1} / ${chunks.length}</span>
            <button type="button" class="slider-arrow" id="tafsirNext">›</button>
          </div>` : '';
        body.innerHTML = `<div style="font-family:var(--font-arabic);font-size:20px;text-align:right;direction:rtl;margin-bottom:16px">${data.ayah_text||''}</div>
                          <hr style="border-color:var(--border-color);margin:16px 0">
                          <div style="font-size:14px;line-height:1.8">${escapeHtml(chunks[page])}</div>
                          <div style="margin-top:16px;font-size:12px;color:var(--text-muted)">— ${data.author||''}</div>
                          ${pagerHtml}`;
        if (chunks.length > 1) {
          document.getElementById('tafsirPrev').disabled = page === 0;
          document.getElementById('tafsirPrev').onclick = () => { page = Math.max(0, page - 1); renderPage(); };
          document.getElementById('tafsirNext').disabled = page === chunks.length - 1;
          document.getElementById('tafsirNext').onclick = () => { page = Math.min(chunks.length - 1, page + 1); renderPage(); };
        }
      };
      renderPage();
    } else {
      body.innerHTML = '<div class="empty-state"><p>Tafsir tidak tersedia untuk ayat ini.</p></div>';
    }
  } catch (e) {
    body.innerHTML = '<div class="empty-state"><p>Gagal memuat tafsir.</p></div>';
  }
}

function closeModal(id) {
  document.getElementById(id).classList.remove('active');
}

async function bookmarkAyah(surahId, ayahNum) {
  try {
    const res = await fetch('api/bookmark.php', {
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body:`surah_id=${surahId}&ayah_number=${ayahNum}`
    });
    const data = await res.json();
    toast(data.message, data.success ? 'success' : 'info');
    updateBookmarkCount();
  } catch(e) { toast('Gagal menandai ayat', 'error'); }
}

function copyAyah(surahId, ayahNum) {
  const item = document.getElementById(`ayah-${ayahNum}`);
  if (!item) return;
  const arabic = item.querySelector('.ayah-text-ar').textContent.trim();
  const trans = item.querySelector('[class^="ayah-text-"]:not(.ayah-text-ar)')?.textContent.trim() || '';
  const text = `${arabic}\n\n${trans}`;
  navigator.clipboard.writeText(text).then(() => toast('Ayat disalin ke clipboard', 'success'));
}

let currentAudio = null;
function playAudio(surahNum, ayahNum) {
  if (currentAudio) { currentAudio.pause(); currentAudio = null; }
  currentAudio = new Audio(`https://everyayah.com/data/Alafasy_128kbps/${String(surahNum).padStart(3,'0')}${String(ayahNum).padStart(3,'0')}.mp3`);
  currentAudio.play().catch(()=>toast('Gagal memutar audio','error'));
}

let shareData = null;
function openShareModal(bodyText, url) {
  const text = `${bodyText}\n\n${url}`;
  shareData = { url, bodyText, text };

  if (navigator.share) {
    navigator.share({ text: bodyText, url }).catch(() => {});
    return;
  }

  document.getElementById('shareWhatsapp').href = `https://wa.me/?text=${encodeURIComponent(text)}`;
  document.getElementById('shareTelegram').href = `https://t.me/share/url?url=${encodeURIComponent(url)}&text=${encodeURIComponent(bodyText)}`;
  document.getElementById('shareTwitter').href = `https://twitter.com/intent/tweet?text=${encodeURIComponent(text)}`;
  document.getElementById('shareFacebook').href = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}`;
  document.getElementById('shareEmail').href = `mailto:?subject=${encodeURIComponent('FACTA')}&body=${encodeURIComponent(text)}`;
  document.getElementById('shareModal').classList.add('active');
}

function shareAyah(surahId, ayahNum) {
  const item = document.getElementById(`ayah-${ayahNum}`);
  if (!item) return;
  const arabicEl = item.querySelector('.ayah-text-ar');
  const arabic = arabicEl ? arabicEl.textContent.trim() : '';
  const trans = item.querySelector('[class^="ayah-text-"]:not(.ayah-text-ar)')?.textContent.trim() || '';
  const url = `${BASE_URL}/index.php?page=surah&id=${surahId}&ayah=${ayahNum}`;
  openShareModal(trans ? `${arabic}\n\n${trans}` : arabic, url);
}

function copyShareLink() {
  if (!shareData) return;
  navigator.clipboard.writeText(shareData.url).then(() => toast('Tautan disalin', 'success'));
}

// ============================================================
// Book Mode — PowerPoint-style reading slides (standard slideshow
// semantics: ArrowRight / swipe-left / ❯ = next). Auto mode packs as
// many WHOLE ayahs as fit the viewport at the current font size
// (measured from real rendered heights); an ayah is never split —
// one longer than the screen gets its own scrolling slide.
// ============================================================
const BOOK = {
  surahId: <?=$surahId?>,
  data: null,
  slides: [],
  current: 0,
  open: false,
  focusN: <?=$targetAyah ?: 1?>,
  selected: new Set(),   // ayah numbers picked for multi-share
};

// Modals/toasts live in <body>; inside true fullscreen only descendants
// of the fullscreen element render, so adopt them into the book while
// it's open (and give them back — a hidden book would swallow them).
function bookAdoptOverlays(intoBook) {
  const host = intoBook ? document.getElementById('bookMode') : document.body;
  ['tafsirModal', 'shareModal', 'toastContainer'].forEach(id => {
    const el = document.getElementById(id);
    if (el) host.appendChild(el);
  });
}

function bookEls() {
  return {
    root: document.getElementById('bookMode'),
    track: document.getElementById('bookTrack'),
    viewport: document.getElementById('bookViewport'),
    indicator: document.getElementById('bookIndicator'),
    perSel: document.getElementById('bookPerSlide'),
    prev: document.getElementById('bookPrevBtn'),
    next: document.getElementById('bookNextBtn'),
  };
}

async function openBookMode() {
  const e = bookEls();
  document.getElementById('normalReading').style.display = 'none';
  e.root.hidden = false;
  BOOK.open = true;
  bookAdoptOverlays(true);
  e.perSel.value = getCookie('book_ayat_per_slide') || 'auto';
  if (!BOOK.data) {
    e.track.innerHTML = '<div class="loading-spinner" style="margin:40px auto"></div>';
    try {
      const res = await fetch(`api/surah_ayahs.php?id=${BOOK.surahId}`);
      BOOK.data = await res.json();
    } catch (err) {
      toast('Gagal memuat data', 'error');
      closeBookMode();
      return;
    }
  }
  bookBuild(BOOK.focusN);
}

function closeBookMode() {
  if (document.fullscreenElement) document.exitFullscreen().catch(() => {});
  const e = bookEls();
  e.root.hidden = true;
  e.root.classList.remove('fs');
  document.getElementById('normalReading').style.display = '';
  BOOK.open = false;
  bookAdoptOverlays(false);
}

function arabicNum(num) {
  const m = {'0':'٠','1':'١','2':'٢','3':'٣','4':'٤','5':'٥','6':'٦','7':'٧','8':'٨','9':'٩'};
  return String(num).replace(/[0-9]/g, d => m[d]);
}

function bookAyahEl(a, forMeasure) {
  const div = document.createElement('div');
  div.className = 'book-ayah';
  const ar = document.createElement('div');
  ar.className = 'book-ayah-ar';
  ar.innerHTML = `${a.ar} <span class="book-ayah-num">﴿${arabicNum(a.n)}﴾</span>`;
  div.appendChild(ar);
  if (forMeasure) return div;

  // Tap the ayah -> translation + compact action row
  const extra = document.createElement('div');
  extra.className = 'book-ayah-extra';
  extra.hidden = true;
  if (a.tr) {
    const tr = document.createElement('div');
    tr.className = 'book-ayah-tr';
    tr.textContent = a.tr;
    extra.appendChild(tr);
  }
  extra.appendChild(bookActions(a));
  div.appendChild(extra);
  div.onclick = (ev) => {
    if (ev.target.closest('.book-ayah-extra')) return; // don't collapse on button/text clicks
    extra.hidden = !extra.hidden;
  };
  return div;
}

function bookActions(a) {
  const w = document.createElement('div');
  w.className = 'book-ayah-actions';
  const mk = (icon, title, fn) => {
    const b = document.createElement('button');
    b.type = 'button';
    b.className = 'book-act';
    b.textContent = icon;
    b.title = title;
    b.onclick = (ev) => { ev.stopPropagation(); fn(b); };
    return b;
  };
  const sel = mk('☑', <?=json_encode(t('select_ayah'), JSON_UNESCAPED_UNICODE)?>, (b) => bookToggleSelect(a.n, b));
  sel.classList.add('book-act-select');
  if (BOOK.selected.has(a.n)) sel.classList.add('on');
  w.append(
    sel,
    mk('🔖', <?=json_encode(t('bookmark'), JSON_UNESCAPED_UNICODE)?>, () => bookmarkAyah(<?=$surahId?>, a.n)),
    mk('📋', <?=json_encode(t('copy'), JSON_UNESCAPED_UNICODE)?>, () => bookCopy(a)),
    mk('▶️', <?=json_encode(t('play_audio'), JSON_UNESCAPED_UNICODE)?>, () => playAudio(<?=$surah['number']?>, a.n)),
    mk('📖', <?=json_encode(t('tafsir'), JSON_UNESCAPED_UNICODE)?>, () => showTafsir(<?=$surahId?>, a.n)),
    mk('🔗', <?=json_encode(t('share'), JSON_UNESCAPED_UNICODE)?>, () => bookShareOne(a)),
  );
  const topics = document.createElement('a');
  topics.className = 'book-act';
  topics.textContent = '🌳';
  topics.title = <?=json_encode(t('related_topics'), JSON_UNESCAPED_UNICODE)?>;
  topics.href = `index.php?page=topics&ayah=${a.id}`;
  topics.onclick = (ev) => ev.stopPropagation();
  w.appendChild(topics);
  return w;
}

function bookCopy(a) {
  const text = a.tr ? `${a.ar}\n\n${a.tr}` : a.ar;
  navigator.clipboard.writeText(text).then(() => toast('Ayat disalin ke clipboard', 'success'));
}

function bookShareOne(a) {
  const url = `${BASE_URL}/index.php?page=surah&id=${BOOK.surahId}&ayah=${a.n}`;
  openShareModal(a.tr ? `${a.ar}\n\n${a.tr}` : a.ar, url);
}

function bookToggleSelect(n, btn) {
  if (BOOK.selected.has(n)) BOOK.selected.delete(n);
  else BOOK.selected.add(n);
  btn.classList.toggle('on', BOOK.selected.has(n));
  bookUpdateShareSel();
}

function bookUpdateShareSel() {
  const pill = document.getElementById('bookShareSel');
  pill.hidden = BOOK.selected.size === 0;
  document.getElementById('bookShareSelCount').textContent = BOOK.selected.size;
}

function bookShareSelected() {
  const ns = [...BOOK.selected].sort((a, b) => a - b);
  if (!ns.length) return;
  const name = BOOK.data.name_transliteration;
  const parts = ns.map(n => {
    const a = BOOK.data.ayahs.find(x => x.n === n);
    if (!a) return null;
    return `${a.ar}\n${a.tr ? a.tr + '\n' : ''}(QS. ${name}: ${n})`;
  }).filter(Boolean);
  const url = `${BASE_URL}/index.php?page=surah&id=${BOOK.surahId}&ayah=${ns[0]}`;
  openShareModal(parts.join('\n\n'), url);
}

function bookBuild(focusN) {
  const e = bookEls();
  const perRaw = getCookie('book_ayat_per_slide') || 'auto';
  const ayahs = BOOK.data.ayahs;
  e.track.innerHTML = '';

  let groups = [];
  if (perRaw !== 'auto') {
    const per = Math.max(1, parseInt(perRaw) || 1);
    for (let i = 0; i < ayahs.length; i += per) groups.push(ayahs.slice(i, i + per));
  } else {
    const meas = document.createElement('div');
    meas.className = 'book-slide book-measure';
    meas.style.width = e.viewport.clientWidth + 'px';
    e.viewport.appendChild(meas);
    // available height = viewport minus the slide's real padding (differs
    // per breakpoint) minus a small safety buffer
    const mcs = getComputedStyle(meas);
    const avail = e.viewport.clientHeight
      - parseFloat(mcs.paddingTop) - parseFloat(mcs.paddingBottom) - 8;
    const inner = document.createElement('div');
    inner.className = 'book-slide-inner';
    meas.appendChild(inner);
    let group = [], acc = 0;
    for (const a of ayahs) {
      inner.innerHTML = '';
      inner.appendChild(bookAyahEl(a, true));
      const h = inner.offsetHeight + 18;
      if (group.length && acc + h > avail) { groups.push(group); group = []; acc = 0; }
      group.push(a);
      acc += h;
      if (group.length === 1 && acc > avail) { groups.push(group); group = []; acc = 0; }
    }
    if (group.length) groups.push(group);
    meas.remove();
  }

  BOOK.slides = groups;
  groups.forEach(g => {
    const slide = document.createElement('div');
    slide.className = 'book-slide';
    const inner = document.createElement('div');
    inner.className = 'book-slide-inner';
    g.forEach(a => inner.appendChild(bookAyahEl(a, false)));
    slide.appendChild(inner);
    e.track.appendChild(slide);
  });

  const idx = groups.findIndex(g => g.some(a => a.n === focusN));
  BOOK.current = idx >= 0 ? idx : 0;
  bookRender();
}

function bookRender() {
  const e = bookEls();
  e.track.style.transform = `translateX(-${BOOK.current * 100}%)`;
  const g = BOOK.slides[BOOK.current] || [];
  const range = g.length
    ? (g[0].n === g[g.length - 1].n ? `${g[0].n}` : `${g[0].n}–${g[g.length - 1].n}`)
    : '';
  e.indicator.textContent = `${BOOK.current + 1}/${BOOK.slides.length} · Ayat ${range}`;
  e.next.disabled = BOOK.current >= BOOK.slides.length - 1;
  e.prev.disabled = BOOK.current <= 0;
  if (g.length) BOOK.focusN = g[0].n;
}

function bookNext() { if (BOOK.current < BOOK.slides.length - 1) { BOOK.current++; bookRender(); } }
function bookPrev() { if (BOOK.current > 0) { BOOK.current--; bookRender(); } }

function bookFont(delta) {
  let v = parseInt(getCookie('font_size_ar') || '28') + delta;
  v = Math.max(18, Math.min(48, v));
  setCookie('font_size_ar', v, 365);
  document.documentElement.style.setProperty('--font-ar', v + 'px');
  bookBuild(BOOK.focusN);
}

function bookSetPerSlide(v) {
  setCookie('book_ayat_per_slide', v, 365);
  bookBuild(BOOK.focusN);
}

function bookToggleFullscreen() {
  const e = bookEls();
  if (document.fullscreenElement) { document.exitFullscreen().catch(() => {}); return; }
  e.root.classList.add('fs');
  const p = e.root.requestFullscreen ? e.root.requestFullscreen() : Promise.resolve();
  Promise.resolve(p).catch(() => {}).finally(() => setTimeout(() => bookBuild(BOOK.focusN), 120));
}

document.addEventListener('fullscreenchange', () => {
  if (!BOOK.open) return;
  if (!document.fullscreenElement) {
    bookEls().root.classList.remove('fs');
    setTimeout(() => bookBuild(BOOK.focusN), 120);
  }
});

document.addEventListener('keydown', (ev) => {
  if (!BOOK.open) return;
  // Escape always works, even with focus on the toolbar's select/input.
  // In fullscreen the browser also exits natively; our explicit call
  // covers environments where that doesn't fire (harmless double-exit).
  if (ev.key === 'Escape') {
    if (document.fullscreenElement) document.exitFullscreen().catch(() => {});
    else closeBookMode();
    return;
  }
  if (/INPUT|SELECT|TEXTAREA/.test(ev.target.tagName)) return;
  if (ev.key === 'ArrowRight') { ev.preventDefault(); bookNext(); }
  else if (ev.key === 'ArrowLeft') { ev.preventDefault(); bookPrev(); }
});

(function () {
  let sx = null;
  const vp = document.getElementById('bookViewport');
  vp.addEventListener('touchstart', (ev) => { sx = ev.touches[0].clientX; }, { passive: true });
  vp.addEventListener('touchend', (ev) => {
    if (sx === null) return;
    const dx = ev.changedTouches[0].clientX - sx;
    if (Math.abs(dx) > 40) (dx < 0 ? bookNext() : bookPrev()); // swipe kiri = maju
    sx = null;
  }, { passive: true });
})();

let bookResizeTimer = null;
window.addEventListener('resize', () => {
  if (!BOOK.open) return;
  clearTimeout(bookResizeTimer);
  bookResizeTimer = setTimeout(() => bookBuild(BOOK.focusN), 300);
});
</script>
