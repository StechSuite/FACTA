<?php
/**
 * FACTA — Help/Guide page.
 *
 * Single long page (all sections rendered server-side, works with no
 * JS at all) + a sidebar accordion (grouped <details>) for jumping to
 * any section via #anchor, plus a prev/next footer per section. See
 * includes/guide_content.php for the actual prose content and the
 * 2026-08-26 decision log (AR/EN/ID full content, SU/JV fall back to
 * ID; single page not per-section reload; high-level text by default
 * with a collapsible "Detail Teknis" for anyone who wants to go deeper).
 */
require_once __DIR__ . '/../includes/guide_content.php';

$lang = current_lang();
$groups = guide_groups();
$flat = guide_flatten_sections($groups);
$activeId = $_GET['section'] ?? ($flat[0]['id'] ?? '');

$deepDiveLabel = [
    'ar' => '🔧 تفاصيل تقنية',
    'en' => '🔧 Technical Detail',
    'id' => '🔧 Detail Teknis',
][$lang] ?? '🔧 Detail Teknis';
$prevLabel = ['ar' => '← السابق', 'en' => '← Previous', 'id' => '← Sebelumnya'][$lang] ?? '← Sebelumnya';
$nextLabel = ['ar' => 'التالي →', 'en' => 'Next →', 'id' => 'Berikutnya →'][$lang] ?? 'Berikutnya →';
$tocLabel = ['ar' => 'محتويات الدليل', 'en' => 'Guide Contents', 'id' => 'Daftar Isi Panduan'][$lang] ?? 'Daftar Isi Panduan';
?>
<div class="fade-in guide-page">
  <div class="card" style="margin-bottom:20px">
    <div class="card-header">
      <div class="card-title"><span class="icon">📘</span> <?=t('help_guide')?></div>
    </div>
  </div>

  <div class="guide-layout">
    <nav class="guide-sidebar" aria-label="<?=htmlspecialchars($tocLabel)?>">
      <div class="guide-sidebar-title"><?=htmlspecialchars($tocLabel)?></div>
      <?php foreach ($groups as $group): ?>
      <details class="guide-group" open>
        <summary><?=$group['icon']?> <?=htmlspecialchars(guide_text($group['title'], $lang))?></summary>
        <ul class="guide-group-list">
          <?php foreach ($group['sections'] as $section): ?>
          <li><a href="#<?=$section['id']?>" class="guide-nav-link" data-section="<?=$section['id']?>"><?=$section['icon']?> <?=htmlspecialchars(guide_text($section['title'], $lang))?></a></li>
          <?php endforeach; ?>
        </ul>
      </details>
      <?php endforeach; ?>
    </nav>

    <div class="guide-content">
      <?php foreach ($flat as $i => $section):
          $prev = $flat[$i - 1] ?? null;
          $next = $flat[$i + 1] ?? null;
      ?>
      <section class="guide-section" id="<?=$section['id']?>">
        <h2 class="guide-section-title"><?=$section['icon']?> <?=htmlspecialchars(guide_text($section['title'], $lang))?></h2>
        <div class="guide-section-body"><?=guide_text($section['body'], $lang)?></div>
        <?php if (!empty($section['deep_dive'])): ?>
        <details class="guide-deep-dive">
          <summary><?=htmlspecialchars($deepDiveLabel)?></summary>
          <div class="guide-section-body"><?=guide_text($section['deep_dive'], $lang)?></div>
        </details>
        <?php endif; ?>
        <div class="guide-prevnext">
          <?php if ($prev): ?>
          <a href="#<?=$prev['id']?>" class="guide-nav-btn guide-nav-prev" data-section="<?=$prev['id']?>">
            <?=$prevLabel?><br><span><?=$prev['icon']?> <?=htmlspecialchars(guide_text($prev['title'], $lang))?></span>
          </a>
          <?php else: ?><span></span><?php endif; ?>
          <?php if ($next): ?>
          <a href="#<?=$next['id']?>" class="guide-nav-btn guide-nav-next" data-section="<?=$next['id']?>">
            <?=$nextLabel?><br><span><?=$next['icon']?> <?=htmlspecialchars(guide_text($next['title'], $lang))?></span>
          </a>
          <?php else: ?><span></span><?php endif; ?>
        </div>
      </section>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<script>
(function() {
  const sections = document.querySelectorAll('.guide-section');
  const navLinks = document.querySelectorAll('.guide-nav-link');
  if (!sections.length || !navLinks.length) return;

  const setActive = (id) => {
    navLinks.forEach(a => a.classList.toggle('active', a.dataset.section === id));
  };

  // Highlight the current section in the sidebar as the reader scrolls —
  // progressive enhancement only, the page (nav, anchors, scrolling) is
  // fully usable without this if IntersectionObserver is unavailable.
  if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) setActive(entry.target.id);
      });
    }, { rootMargin: '-20% 0px -70% 0px' });
    sections.forEach(s => observer.observe(s));
  }

  if (location.hash) setActive(location.hash.slice(1));
})();
</script>
