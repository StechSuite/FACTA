<?php
/**
 * FACTA — Home / Browse Page
 */

$lang = current_lang();
$surahs = get_surahs();
$lastRead = get_last_read();
?>

<div class="fade-in">
  <!-- Welcome / Last Read -->
  <div class="card" style="margin-bottom:24px">
    <div class="card-header">
      <div class="card-title"><span class="icon">📖</span> <?=t('browse_quran_title')?></div>
      <?php if ($lastRead): ?>
      <a href="index.php?page=surah&id=<?=$lastRead['surah_id']?>&ayah=<?=$lastRead['ayah_number']?>" class="btn btn-secondary">
        ▶️ <?=t('continue_reading')?>
      </a>
      <?php endif; ?>
    </div>
    <div class="card-body">
      <p><?=sprintf(t('home_choose_surah'), count($surahs))?></p>
      <?php if ($lastRead):
          $lastReadName = $lang === 'en' ? ($lastRead['name_en'] ?? $lastRead['name_id']) : ($lastRead['name_id'] ?? $lastRead['name_en']);
      ?>
      <div style="margin-top:12px;padding:12px 16px;background:var(--bg-glass);border-radius:10px;border:1px solid var(--border-color)">
        <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px">📌 <?=t('last_read')?></div>
        <div style="font-weight:600">
          <?=t('surah')?> <?=$lastReadName?> — <?=t('ayah')?> <?=$lastRead['ayah_number']?>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Surah Grid (sliding pages) -->
  <div class="slider">
    <div class="slider-track" id="surahGrid">
      <?php foreach ($surahs as $s):
          $surahName = $lang === 'en' ? ($s['name_en'] ?? $s['name_id']) : ($s['name_id'] ?? $s['name_en']);
          $revelation = $s['revelation_type'] === 'meccan' ? t('meccan') : t('medinan');
      ?>
      <a href="index.php?page=surah&id=<?=$s['id']?>" class="surah-card">
        <div class="num"><?=$s['number']?></div>
        <div class="name-ar"><?=$s['name_ar']?></div>
        <div class="name-en"><?=$s['name_transliteration']?> — <?=$surahName?></div>
        <div class="meta">
          <span><?=$revelation?></span>
          <span><?=$s['verse_count']?> <?=t('ayah')?></span>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
    <div class="slider-controls" id="surahSliderControls">
      <button type="button" class="slider-arrow slider-prev">‹</button>
      <div class="slider-dots"></div>
      <button type="button" class="slider-arrow slider-next">›</button>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const grid = document.getElementById('surahGrid');
  const surahSlider = initSlider(grid, document.getElementById('surahSliderControls'));

  // Quick filter — drops the slider into a plain scrollable grid of matches
  const searchInput = document.getElementById('globalSearch');
  if (searchInput && grid) {
    searchInput.addEventListener('input', function() {
      const q = this.value.toLowerCase().trim();
      const cards = grid.querySelectorAll('.surah-card');
      cards.forEach(c => {
        const text = c.textContent.toLowerCase();
        c.style.display = text.includes(q) ? '' : 'none';
      });
      surahSlider?.setFiltering(q.length > 0);
    });
  }
});
</script>
