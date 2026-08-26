<?php
/**
 * FACTA — Bookmarks Page
 */

$bookmarks = get_bookmarks();
?>

<div class="fade-in">
  <div class="card" style="margin-bottom:24px">
    <div class="card-header">
      <div class="card-title"><span class="icon">🔖</span> <?=t('bookmarks')?></div>
    </div>
    <div class="card-body">
      <p>Kelola ayat-ayat yang telah Anda tandai untuk dibaca kembali.</p>
    </div>
  </div>

  <?php if (empty($bookmarks)): ?>
    <div class="empty-state">
      <div class="icon">🔖</div>
      <h3>Belum ada penanda</h3>
      <p>Tandai ayat saat membaca untuk melihatnya di sini.</p>
    </div>
  <?php else: ?>
    <div class="card">
      <?php foreach ($bookmarks as $bm): ?>
      <div class="bookmark-item" data-id="<?=$bm['id']?>">
        <div class="color" style="background:<?=$bm['color']?>"></div>
        <a href="index.php?page=surah&id=<?=$bm['surah_id']?>&ayah=<?=$bm['ayah_number']?>" class="info">
          <div class="loc"><?=$bm['name_id']?> (<?=$bm['name_en']?>) — Ayat <?=$bm['ayah_number']?></div>
          <div class="text"><?=$bm['text_ar']?></div>
        </a>
        <button class="delete" onclick="deleteBookmark(<?=$bm['id']?>,this)" title="Hapus">🗑️</button>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<script>
async function deleteBookmark(id, btn) {
  if (!confirm('Hapus penanda ini?')) return;
  try {
    const res = await fetch(`api/bookmark.php?id=${id}`, {method:'DELETE'});
    const data = await res.json();
    if (data.success) {
      btn.closest('.bookmark-item').remove();
      toast('Penanda dihapus', 'success');
      updateBookmarkCount();
    } else {
      toast('Gagal menghapus', 'error');
    }
  } catch(e) { toast('Error', 'error'); }
}
</script>
