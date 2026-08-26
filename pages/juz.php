<?php
/**
 * FACTA — Juz List Page
 * Boundaries derived from ayahs.juz_number (full 30 juz)
 */

$juzData = Database::query(
    "SELECT juz_number AS juz,
            MIN(id) AS start_id,
            MAX(id) AS end_id
     FROM ayahs
     WHERE juz_number IS NOT NULL
     GROUP BY juz_number
     ORDER BY juz_number"
);
$surahs = get_surahs();
$surahMap = array_column($surahs, null, 'number');

// Resolve start/end ayah rows in one query
$bounds = [];
if ($juzData) {
    $ids = [];
    foreach ($juzData as $j) { $ids[] = (int)$j['start_id']; $ids[] = (int)$j['end_id']; }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    foreach (Database::query("SELECT id, surah_id, ayah_number FROM ayahs WHERE id IN ({$placeholders})", $ids) as $row) {
        $bounds[$row['id']] = $row;
    }
}
?>

<div class="fade-in">
  <div class="card" style="margin-bottom:24px">
    <div class="card-header">
      <div class="card-title">📑 Daftar Juz</div>
    </div>
    <div class="card-body">
      <p>Al-Quran terbagi dalam 30 Juz. Klik untuk membuka surat dan ayat pembuka juz tersebut.</p>
    </div>
  </div>

  <div class="slider">
    <div class="slider-track" id="juzGrid">
      <?php foreach ($juzData as $j): ?>
      <?php
      $start = $bounds[$j['start_id']] ?? null;
      $end = $bounds[$j['end_id']] ?? null;
      if (!$start || !$end) continue;
      $startSurah = $surahMap[$start['surah_id']] ?? null;
      $endSurah = $surahMap[$end['surah_id']] ?? null;
      ?>
      <a href="index.php?page=surah&id=<?=$start['surah_id']?>&ayah=<?=$start['ayah_number']?>" class="surah-card">
        <div class="num"><?=$j['juz']?></div>
        <div class="name-ar" style="font-size:22px">الجزء <?=to_arabic_number((int)$j['juz'])?></div>
        <div class="name-en">Juz <?=$j['juz']?></div>
        <div class="meta">
          <span>Dimulai: <?=$startSurah['name_transliteration'] ?? ''?> : <?=$start['ayah_number']?></span>
        </div>
        <div class="meta">
          <span>Berakhir: <?=$endSurah['name_transliteration'] ?? ''?> : <?=$end['ayah_number']?></span>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
    <div class="slider-controls" id="juzSliderControls">
      <button type="button" class="slider-arrow slider-prev">‹</button>
      <div class="slider-dots"></div>
      <button type="button" class="slider-arrow slider-next">›</button>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  initSlider(document.getElementById('juzGrid'), document.getElementById('juzSliderControls'));
});
</script>
