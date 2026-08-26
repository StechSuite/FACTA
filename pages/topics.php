<?php
/**
 * FACTA — Topics Navigator with Semantic Tree
 */

require_once __DIR__ . '/../includes/functions.php';

$viewMode = $_GET['view'] ?? 'tree'; // tree | graph
$selectedTopicId = (int)($_GET['id'] ?? 0);

$roots = get_topic_tree(null);

// If topic selected, get details
$selectedTopic = null;
$topicAyahs = [];
$relatedTopics = [];
if ($selectedTopicId > 0) {
    $selectedTopic = Database::queryOne("SELECT * FROM topics WHERE id = ?", [$selectedTopicId]);
    $topicAyahs = get_topic_ayahs($selectedTopicId);
    $relatedTopics = get_related_topics($selectedTopicId);
}
?>

<div class="fade-in">
  <div class="card" style="margin-bottom:24px">
    <div class="card-header">
      <div class="card-title"><span class="icon">🌳</span> <?=t('topics')?> — <?=t('topic_tree')?></div>
      <div style="display:flex;gap:8px">
        <a href="?page=topics&view=tree<?=$selectedTopicId?'&id='.$selectedTopicId:''?>" class="btn <?=$viewMode==='tree'?'btn-primary':'btn-secondary'?>">🌳 Tree</a>
        <a href="?page=topics&view=graph<?=$selectedTopicId?'&id='.$selectedTopicId:''?>" class="btn <?=$viewMode==='graph'?'btn-primary':'btn-secondary'?>">🕸️ Graph</a>
      </div>
    </div>
  </div>

  <div style="display:grid;grid-template-columns: 320px 1fr;gap:20px">
    <!-- Topic Tree Sidebar -->
    <div class="card" style="align-self:start">
      <div class="topic-tree" id="topicTree">
        <?php foreach ($roots as $root): ?>
        <div class="tree-branch" data-id="<?=$root['id']?>">
          <div class="node <?=$selectedTopicId==$root['id']?'active':''?>" onclick="location.href='?page=topics&id=<?=$root['id']?>'">
            <span class="icon"><?=$root['icon']?></span>
            <span class="label"><?=$root['name_id'] ?? $root['name_en']?></span>
          </div>
          <?php
          $children = get_topic_tree($root['id']);
          if ($children):
          ?>
          <div class="children expanded">
            <?php foreach ($children as $child): ?>
            <div class="node <?=$selectedTopicId==$child['id']?'active':''?>" onclick="location.href='?page=topics&id=<?=$child['id']?>'">
              <span class="icon"><?=$child['icon'] ?? '•'?></span>
              <span class="label"><?=$child['name_id'] ?? $child['name_en']?></span>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Detail Panel -->
    <div>
      <?php if ($selectedTopic): ?>
      <div class="card" style="margin-bottom:20px">
        <div class="card-header">
          <div class="card-title">
            <span style="font-size:24px"><?=$selectedTopic['icon']?></span>
            <?=$selectedTopic['name_id'] ?? $selectedTopic['name_en']?>
          </div>
          <div class="ayah-btn" style="background:<?=$selectedTopic['color']?>20;color:<?=$selectedTopic['color']?>">
            <?=$selectedTopic['name_ar'] ?? ''?>
          </div>
        </div>
        <?php if ($selectedTopic['description']): ?>
        <div class="card-body" style="margin-bottom:16px"><?=$selectedTopic['description']?></div>
        <?php endif; ?>

        <?php if (!empty($relatedTopics)): ?>
        <div style="padding-top:16px;border-top:1px solid var(--border-color)">
          <div style="font-size:12px;font-weight:700;color:var(--text-muted);margin-bottom:8px;text-transform:uppercase">Relasi Topik</div>
          <div style="display:flex;flex-wrap:wrap;gap:6px">
            <?php foreach ($relatedTopics as $rt): ?>
            <a href="?page=topics&id=<?=$rt['id']?>" class="relation-badge relation-<?=$rt['relation_type']?>">
              <?=$rt['relation_type']?>: <?=$rt['name_id'] ?? $rt['name_en']?>
            </a>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
      </div>

      <?php if (!empty($topicAyahs)): ?>
      <div class="card">
        <div class="card-header">
          <div class="card-title">📜 Ayat Terkait (<?=count($topicAyahs)?>)</div>
        </div>
        <?php foreach ($topicAyahs as $ta): ?>
        <a href="index.php?page=surah&id=<?=$ta['surah_id']?>&ayah=<?=$ta['ayah_number']?>" class="search-result">
          <div class="location"><?=$ta['surah_name']?> — Ayat <?=$ta['ayah_number']?></div>
          <div class="arabic"><?=$ta['text_ar']?></div>
          <?php if ($ta['translation_text']): ?>
          <div class="translation"><?=$ta['translation_text']?></div>
          <?php endif; ?>
        </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <?php else: ?>
      <div class="empty-state">
        <div class="icon">🌳</div>
        <h3>Jelajahi Topik Qurani</h3>
        <p>Klik topik di samping untuk melihat ayat-ayat terkait dan relasi semantik antar konsep.</p>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
