<?php
/**
 * FACTA — Search Page with FTS5 + dynamic root/derived-word search
 */

$modeRaw = $_GET['mode'] ?? 'text';
$mode = in_array($modeRaw, ['root', 'word', 'assoc', 'and'], true) ? $modeRaw : 'text';

// ---- Language filter: MULTI-select (search + which translation
// lines display). Default: only the UI language (su/jv content = id).
// Old single-`lang` links (incl. instant-search "see all") still work.
$VALID_LANGS = ['ar', 'id', 'en'];
$selRaw = $_GET['langs'] ?? null;
if ($selRaw === null && isset($_GET['lang'])) $selRaw = [$_GET['lang']];
if ($selRaw === null) {
    $ui = current_lang();
    $selRaw = [in_array($ui, $VALID_LANGS, true) ? $ui : 'id'];
}
$selLangs = array_values(array_intersect($VALID_LANGS, (array)$selRaw));
if (!$selLangs) $selLangs = ['id'];
$trShow = array_values(array_intersect(['id', 'en'], $selLangs));

$mushafSort = fn($a, $b) => [(int)$a['surah_id'], (int)$a['ayah_number']] <=> [(int)$b['surah_id'], (int)$b['ayah_number']];

// ---- Text mode: literal FTS search across every selected language ----
$query = trim($_GET['q'] ?? '');
$results = [];
$topicResults = [];
$hasQuery = strlen($query) >= 2;
$wantDerived = isset($_GET['derived']);
$derivedResults = [];
$matchLangOf = []; // ayah_id => language that matched (for contextual highlight)

if ($mode === 'text' && $hasQuery) {
    $found = [];
    foreach ($selLangs as $l) {
        foreach (search_ayahs($query, $l, 50) as $row) {
            $aid = (int)$row['id'];
            if (!isset($found[$aid])) { $found[$aid] = $row; $matchLangOf[$aid] = $l; }
        }
    }
    $results = array_values($found);
    // >1 language: merged set uses predictable mushaf order; a single
    // language keeps the existing relevance order
    if (count($selLangs) > 1) usort($results, $mushafSort);
    $results = array_slice($results, 0, 60);
    $topicResults = search_topics($query);

    if ($wantDerived) {
        $existingIds = array_map(fn($r) => (int)$r['id'], $results);
        $dFound = [];
        foreach ($selLangs as $l) {
            if ($l === 'ar') {
                $rootGuess = ar_normalize($query);
                $hits = mb_strlen($rootGuess, 'UTF-8') >= 2 ? search_by_root($rootGuess, 50, $l) : [];
            } else {
                $hits = search_stem_derived($query, $l, 50);
            }
            foreach ($hits as $dh) {
                $aid = (int)$dh['ayah_id'];
                if (in_array($aid, $existingIds, true) || isset($dFound[$aid])) continue;
                $dFound[$aid] = $dh;
                if (!isset($matchLangOf[$aid])) $matchLangOf[$aid] = $l;
            }
        }
        $derivedResults = array_values($dFound);
        if (count($selLangs) > 1) usort($derivedResults, $mushafSort);
        $derivedResults = array_slice($derivedResults, 0, 60);
    }
}

// ---- Ambiguity prompt (backlog-1.01.Alpha.023.md §2c option C): a
// plain-text ID/EN query that maps to 2+ unrelated roots (Indonesian
// "lahir" = outward/apparent OR born, genuine polysemy — no exact-text
// search can guess which one the user means) offers a way to narrow
// down via the AND-search builder instead of showing mixed results. ----
$ambiguousRoots = [];
if ($mode === 'text' && $hasQuery && !preg_match('/\p{Arabic}/u', $query)) {
    $ambiguousRoots = match_roots_by_gloss($query, 6);
    if (count($ambiguousRoots) < 2) $ambiguousRoots = [];
}

// ---- Root mode: dynamic Arabic root/derived-word search ----
$rootQuery = trim($_GET['root'] ?? '');
$rootResults = [];
if ($mode === 'root' && $rootQuery !== '') {
    $rootResults = search_by_root($rootQuery, 200);
}

// ---- Word mode: EXACT derived-form filter (clicked from a derived-
// word card in the Word Info dialog) — only ayahs containing this
// precise word form, not the root's other derivations ----
$wordQuery = trim($_GET['w'] ?? '');
$wordDisplay = trim($_GET['d'] ?? '') ?: $wordQuery;

// ---- AND-search builder seed (backlog-1.01.Alpha.023.md §2c): arrives
// from the ambiguity prompt under a plain-text search — seed = the
// original query text (re-run through the typeahead on page load),
// pick = a specific root id already chosen (added as a term chip
// immediately, no extra click needed) ----
$andSeed = trim($_GET['seed'] ?? '');
$andPick = (int)($_GET['pick'] ?? 0);
$wordResults = [];
if ($mode === 'word' && $wordQuery !== '') {
    $wordResults = Database::query(
        "SELECT DISTINCT a.id AS ayah_id, a.text_ar, a.surah_id, a.ayah_number,
                s.name_ar, s.name_en, s.name_id, s.name_transliteration
         FROM ayah_words w
         JOIN ayahs a ON a.id = w.ayah_id
         JOIN surahs s ON s.id = a.surah_id
         WHERE w.text_ar_clean = ?
         ORDER BY a.surah_id, a.ayah_number
         LIMIT 200",
        [$wordQuery]
    );
}

// ---- Assoc mode: multi-level co-occurrence tree's final "show ayat"
// step (backlog-1.01.Alpha.016.md) — ayahs matching ALL selected roots
// (AND), highlighted by the SPECIFIC derived word form seen per ayah
// per root (not the whole root), via ayah_root_words. ----
$assocRootIds = array_values(array_filter(array_map('intval', (array)($_GET['roots'] ?? []))));
$assocOnly = isset($_GET['only']);
$assocResults = [];
$assocRootNames = [];
$assocWordsByAyah = []; // ayah_id => [word_form, ...] for highlighting
if ($mode === 'assoc' && $assocRootIds) {
    $co = root_co_occurrence($assocRootIds, 0);
    $ayahIds = $assocOnly ? $co['only_ayah_ids'] : $co['ayah_ids'];
    if ($ayahIds) {
        $rows = Database::query(
            "SELECT id, root_ar FROM root_words WHERE id IN (" . implode(',', $assocRootIds) . ")"
        );
        foreach ($rows as $row) $assocRootNames[(int)$row['id']] = $row['root_ar'];

        $ph = implode(',', array_fill(0, count($ayahIds), '?'));
        $assocResults = Database::query(
            "SELECT a.id AS ayah_id, a.text_ar, a.surah_id, a.ayah_number,
                    s.name_ar, s.name_en, s.name_id, s.name_transliteration
             FROM ayahs a
             JOIN surahs s ON s.id = a.surah_id
             WHERE a.id IN ({$ph})
             ORDER BY a.surah_id, a.ayah_number",
            $ayahIds
        );

        $wfRows = Database::query(
            "SELECT ayah_id, word_form FROM ayah_root_words
             WHERE ayah_id IN ({$ph}) AND root_word_id IN (" . implode(',', $assocRootIds) . ")",
            $ayahIds
        );
        foreach ($wfRows as $w) $assocWordsByAyah[(int)$w['ayah_id']][] = $w['word_form'];
    }
}

// Both translations for every hit (contextual cross-language highlighting)
$trMap = translations_for(array_merge(
    array_map(fn($r) => (int)$r['id'], $results),
    array_map(fn($r) => (int)$r['ayah_id'], $derivedResults),
    array_map(fn($r) => (int)$r['ayah_id'], $rootResults),
    array_map(fn($r) => (int)$r['ayah_id'], $wordResults),
    array_map(fn($r) => (int)$r['ayah_id'], $assocResults)
));

// Renders one result's Arabic + both translations, all contextually
// highlighted through the word-by-word bridge (query language doesn't
// matter — matching Arabic forms AND their glosses light up everywhere).
function render_result_texts(int $ayahId, string $textAr, string $q, string $qLang, array $trMap, array $trShow, array $extraArWords = [], bool $exactWord = false): void {
    $ctx = contextual_matches($ayahId, $q, $qLang, $exactWord);
    $arSet = array_merge($ctx['ar'], $extraArWords, $qLang === 'ar' ? [$q] : []);
    $idTerms = array_merge($ctx['id'], $qLang === 'id' ? [$q] : []);
    $enTerms = array_merge($ctx['en'], $qLang === 'en' ? [$q] : []);
    $tr = $trMap[$ayahId] ?? [];
    echo '<div class="arabic">', highlight_words($textAr, $arSet, 'ar'), '</div>';
    if (in_array('id', $trShow, true) && !empty($tr['id'])) {
        echo '<div class="translation"><span class="tr-lang">ID</span> ', highlight_words($tr['id'], $idTerms, 'id', true), '</div>';
    }
    if (in_array('en', $trShow, true) && !empty($tr['en'])) {
        echo '<div class="translation"><span class="tr-lang">EN</span> ', highlight_words($tr['en'], $enTerms, 'en', true), '</div>';
    }
}

// Assoc mode: exact per-ayah word forms (one or more, one per selected
// root — from ayah_root_words, not a single query string), Arabic
// highlighted exactly; translation lines highlighted via each matched
// word's own gloss (looked up by normalized-form match against
// ayah_words, since the two tables come from different source pipelines
// and may differ in incidental formatting/tatweel).
function render_assoc_result_texts(int $ayahId, string $textAr, array $wordForms, array $trMap, array $trShow): void {
    $tr = $trMap[$ayahId] ?? [];
    $idTerms = [];
    $enTerms = [];
    if ($wordForms) {
        $normTargets = array_map('ar_normalize', $wordForms);
        $rows = Database::query("SELECT text_ar_clean, translation_id, translation_en FROM ayah_words WHERE ayah_id = ?", [$ayahId]);
        foreach ($rows as $w) {
            if (!in_array(ar_normalize($w['text_ar_clean']), $normTargets, true)) continue;
            foreach (gloss_terms($w['translation_id'], 'id') as $t) $idTerms[] = $t;
            foreach (gloss_terms($w['translation_en'], 'en') as $t) $enTerms[] = $t;
        }
    }
    echo '<div class="arabic">', highlight_words($textAr, $wordForms, 'ar'), '</div>';
    if (in_array('id', $trShow, true) && !empty($tr['id'])) {
        echo '<div class="translation"><span class="tr-lang">ID</span> ', highlight_words($tr['id'], $idTerms, 'id', true), '</div>';
    }
    if (in_array('en', $trShow, true) && !empty($tr['en'])) {
        echo '<div class="translation"><span class="tr-lang">EN</span> ', highlight_words($tr['en'], $enTerms, 'en', true), '</div>';
    }
}
?>

<div class="fade-in">
  <div class="card" style="margin-bottom:24px">
    <div class="card-header">
      <div class="card-title"><span class="icon">🔎</span> <?=t('search')?></div>
      <div class="mode-tabs-scroll">
        <a href="?page=search&mode=text" class="btn <?=$mode==='text'?'btn-primary':'btn-secondary'?>">🔎 <?=t('search')?></a>
        <a href="?page=search&mode=root" class="btn <?=$mode==='root'?'btn-primary':'btn-secondary'?>">🔤 <?=t('root_search')?></a>
        <a href="?page=search&mode=and" class="btn <?=$mode==='and'?'btn-primary':'btn-secondary'?>">🔀 <?=t('and_search')?></a>
      </div>
    </div>
    <div class="card-body">
      <?php if ($mode === 'text'): ?>
      <form method="get" action="index.php" style="display:flex;gap:10px;flex-wrap:wrap">
        <input type="hidden" name="page" value="search">
        <input type="hidden" name="mode" value="text">
        <input type="text" name="q" value="<?=htmlspecialchars($query)?>" placeholder="<?=t('search_placeholder')?>" class="search-box" style="margin:0;flex:1">
        <button type="submit" class="btn btn-primary">Cari</button>
        <div class="lang-checks">
          <?php foreach (['ar' => 'Arab', 'id' => 'Indonesia', 'en' => 'English'] as $code => $labelL): ?>
          <label class="lang-check">
            <input type="checkbox" name="langs[]" value="<?=$code?>" <?=in_array($code, $selLangs, true) ? 'checked' : ''?>>
            <?=$labelL?>
          </label>
          <?php endforeach; ?>
        </div>
        <label style="display:flex;align-items:center;gap:6px;font-size:13px;color:var(--text-secondary);width:100%">
          <input type="checkbox" name="derived" value="1" <?=$wantDerived?'checked':''?>> <?=t('derived_words')?>
        </label>
      </form>
      <?php elseif ($mode === 'word'): ?>
      <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
        <span style="font-family:var(--font-arabic);font-size:26px;direction:rtl"><?=htmlspecialchars($wordDisplay)?></span>
        <span style="font-size:13px;color:var(--text-muted)">— hanya ayat yang memuat bentuk kata ini persis</span>
        <a href="?page=search&mode=root" class="btn btn-secondary" style="margin-left:auto">🔤 <?=t('root_search')?></a>
      </div>
      <?php elseif ($mode === 'assoc'): ?>
      <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
        <span style="font-size:13px;color:var(--text-muted)"><?=t('assoc_ayahs_with')?>:</span>
        <?php foreach ($assocRootNames as $rid => $rar): ?>
        <span class="ayah-btn" style="font-family:var(--font-arabic);font-size:18px;direction:rtl;cursor:default">🔤 <?=htmlspecialchars($rar)?></span>
        <?php endforeach; ?>
        <a href="?page=search&mode=root" class="btn btn-secondary" style="margin-left:auto">🔤 <?=t('root_search')?></a>
      </div>
      <?php elseif ($mode === 'and'): ?>
      <div id="andBuilder" data-seed="<?=htmlspecialchars($andSeed)?>" data-pick="<?=(int)$andPick?>">
        <p style="font-size:12px;color:var(--text-muted);margin:0 0 10px"><?=t('and_search_hint')?></p>
        <div class="and-terms" id="andTerms"></div>
        <div class="and-input-row">
          <input type="text" id="andSearchInput" placeholder="🔍 <?=htmlspecialchars(t('and_search_placeholder'))?>" autocomplete="off">
          <div class="assoc-search-dropdown" id="andSearchDropdown"></div>
        </div>
        <button type="button" id="andSearchBtn" class="btn btn-primary" style="margin-top:10px" disabled>🔎 <?=t('and_search_go')?></button>
      </div>
      <?php else: ?>
      <form method="get" action="index.php">
        <input type="hidden" name="page" value="search">
        <input type="hidden" name="mode" value="root">
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
          <input type="text" name="root" id="rootInput" value="<?=htmlspecialchars($rootQuery)?>" readonly
                 placeholder="<?=t('type_root')?>" class="search-box"
                 style="margin:0;flex:1;text-align:right;direction:rtl;font-family:var(--font-arabic);font-size:22px">
          <button type="button" class="btn btn-secondary" onclick="openModal('keyboardModal')">⌨️ <?=t('hijaiyah_keyboard')?></button>
          <button type="submit" class="btn btn-primary">Cari</button>
        </div>
      </form>
      <p style="font-size:12px;color:var(--text-muted);margin-top:10px">ⓘ <?=t('morphology_disclaimer')?></p>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($mode === 'text'): ?>
    <?php if ($ambiguousRoots): ?>
      <div class="card ambiguity-prompt" style="margin-bottom:16px">
        <div class="card-body" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
          <span style="font-size:13px;color:var(--text-secondary)"><?=t('ambiguous_query_prefix')?> "<strong><?=htmlspecialchars($query)?></strong>" <?=t('ambiguous_query_suffix')?>:</span>
          <?php foreach ($ambiguousRoots as $ar): ?>
          <a class="rel-chip" href="index.php?page=search&mode=and&seed=<?=urlencode($query)?>&pick=<?=$ar['id']?>">
            <span class="ar"><?=htmlspecialchars($ar['root_ar'])?></span>
            <span><?=htmlspecialchars(mb_strimwidth($ar['meaning_id'] ?: $ar['meaning_en'], 0, 40, '…'))?></span>
          </a>
          <?php endforeach; ?>
          <a class="ayah-btn" style="margin-left:auto" href="index.php?page=search&mode=and&seed=<?=urlencode($query)?>">🔀 <?=t('and_search_more')?></a>
        </div>
      </div>
    <?php endif; ?>
    <?php if ($hasQuery && empty($results)): ?>
      <div class="empty-state">
        <div class="icon">🔍</div>
        <h3><?=t('no_results')?></h3>
        <p>Coba kata kunci lain atau periksa ejaan.</p>
      </div>
    <?php elseif ($hasQuery): ?>
      <div class="card">
        <div class="card-header">
          <div><strong><?=count($results)?></strong> hasil untuk "<?=htmlspecialchars($query)?>"</div>
        </div>
        <?php foreach ($results as $r): ?>
        <a href="index.php?page=surah&id=<?=$r['surah_id']?>&ayah=<?=$r['ayah_number']?>" class="search-result">
          <div class="location">Surat <?=$r['name_transliteration']?> (<?=$r['name_en']?>) — Ayat <?=$r['ayah_number']?></div>
          <?php render_result_texts((int)$r['id'], $r['text_ar'], $query, $matchLangOf[(int)$r['id']] ?? $selLangs[0], $trMap, $trShow); ?>
        </a>
        <?php endforeach; ?>
      </div>

      <?php if (!empty($derivedResults)): ?>
      <div class="card" style="margin-top:20px">
        <div class="card-header">
          <div class="card-title">🔤 <?=t('derived_words')?> (<?=count($derivedResults)?>)</div>
        </div>
        <div class="card-body" style="padding-top:0">
          <p style="font-size:12px;color:var(--text-muted);margin:0 0 12px">ⓘ <?=t('morphology_disclaimer')?></p>
        </div>
        <?php foreach ($derivedResults as $r): ?>
        <a href="index.php?page=surah&id=<?=$r['surah_id']?>&ayah=<?=$r['ayah_number']?>" class="search-result">
          <div class="location">Surat <?=$r['name_transliteration']?> (<?=$r['name_en']?>) — Ayat <?=$r['ayah_number']?>
            <span style="color:var(--text-muted)">— <?=htmlspecialchars(implode(', ', array_unique($r['matched_words'])))?></span>
          </div>
          <?php $mLang = $matchLangOf[(int)$r['ayah_id']] ?? $selLangs[0]; ?>
          <?php render_result_texts((int)$r['ayah_id'], $r['text_ar'], $query, $mLang, $trMap, $trShow, $mLang === 'ar' ? $r['matched_words'] : []); ?>
        </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <?php if (!empty($topicResults)): ?>
      <div class="card" style="margin-top:20px">
        <div class="card-header">
          <div class="card-title">🌳 Topik Terkait</div>
        </div>
        <div class="card-body">
          <div style="display:flex;flex-wrap:wrap;gap:8px">
            <?php foreach ($topicResults as $t): ?>
            <a href="index.php?page=topic&id=<?=$t['id']?>" class="ayah-btn">
              <span><?=$t['icon']?></span> <?=$t['name_id'] ?? $t['name_en']?>
            </a>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <?php endif; ?>

    <?php else: ?>
      <div class="empty-state">
        <div class="icon">🔍</div>
        <h3>Cari di Al-Quran</h3>
        <p>Ketik kata kunci untuk mencari ayat, terjemahan, atau topik.</p>
      </div>
    <?php endif; ?>
  <?php elseif ($mode === 'word'): ?>
    <?php if (empty($wordResults)): ?>
      <div class="empty-state">
        <div class="icon">🔤</div>
        <h3><?=t('no_results')?></h3>
      </div>
    <?php else: ?>
      <div class="card">
        <div class="card-header">
          <div><strong><?=count($wordResults)?></strong> ayat memuat kata "<span style="font-family:var(--font-arabic);direction:rtl"><?=htmlspecialchars($wordDisplay)?></span>"</div>
        </div>
        <?php foreach ($wordResults as $r): ?>
        <a href="index.php?page=surah&id=<?=$r['surah_id']?>&ayah=<?=$r['ayah_number']?>" class="search-result">
          <div class="location">Surat <?=$r['name_transliteration']?> (<?=$r['name_en']?>) — Ayat <?=$r['ayah_number']?></div>
          <?php render_result_texts((int)$r['ayah_id'], $r['text_ar'], $wordQuery, 'ar', $trMap, $trShow, [$wordDisplay], true); ?>
        </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  <?php elseif ($mode === 'assoc'): ?>
    <?php if (!$assocRootIds): ?>
      <div class="empty-state">
        <div class="icon">🌳</div>
        <h3><?=t('no_results')?></h3>
      </div>
    <?php elseif (empty($assocResults)): ?>
      <div class="empty-state">
        <div class="icon">🌳</div>
        <h3><?=t('no_results')?></h3>
        <p><?=t('assoc_no_ayahs')?></p>
      </div>
    <?php else: ?>
      <div class="card">
        <div class="card-header">
          <div><strong><?=count($assocResults)?></strong> <?=t('assoc_ayahs_count_label')?></div>
        </div>
        <?php foreach ($assocResults as $r): ?>
        <a href="index.php?page=surah&id=<?=$r['surah_id']?>&ayah=<?=$r['ayah_number']?>" class="search-result">
          <div class="location">Surat <?=$r['name_transliteration']?> (<?=$r['name_en']?>) — Ayat <?=$r['ayah_number']?></div>
          <?php render_assoc_result_texts((int)$r['ayah_id'], $r['text_ar'], $assocWordsByAyah[(int)$r['ayah_id']] ?? [], $trMap, $trShow); ?>
        </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  <?php elseif ($mode === 'and'): ?>
    <div class="empty-state" id="andEmptyState">
      <div class="icon">🔀</div>
      <h3><?=t('and_search')?></h3>
      <p><?=t('and_search_hint')?></p>
    </div>
  <?php else: ?>
    <?php if ($rootQuery !== '' && empty($rootResults)): ?>
      <div class="empty-state">
        <div class="icon">🔤</div>
        <h3><?=t('no_results')?></h3>
        <p>Tidak ditemukan ayat dengan kata turunan dari akar "<?=htmlspecialchars($rootQuery)?>".</p>
      </div>
    <?php elseif ($rootQuery !== ''): ?>
      <div class="card">
        <div class="card-header">
          <div><strong><?=count($rootResults)?></strong> ayat dengan turunan akar "<span style="font-family:var(--font-arabic);direction:rtl"><?=htmlspecialchars($rootQuery)?></span>"</div>
        </div>
        <?php foreach ($rootResults as $r): ?>
        <a href="index.php?page=surah&id=<?=$r['surah_id']?>&ayah=<?=$r['ayah_number']?>" class="search-result">
          <div class="location">Surat <?=$r['name_transliteration']?> (<?=$r['name_en']?>) — Ayat <?=$r['ayah_number']?>
            <span style="color:var(--text-muted)">— <?=htmlspecialchars(implode(', ', array_unique($r['matched_words'])))?></span>
          </div>
          <?php render_result_texts((int)$r['ayah_id'], $r['text_ar'], $rootQuery, 'ar', $trMap, $trShow, $r['matched_words']); ?>
        </a>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="empty-state">
        <div class="icon">🔤</div>
        <h3><?=t('root_search')?></h3>
        <p><?=t('type_root')?></p>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>

<!-- Hijaiyah Keyboard Modal -->
<div class="modal-overlay" id="keyboardModal">
  <div class="modal">
    <div class="modal-header">
      <h3>⌨️ <?=t('hijaiyah_keyboard')?></h3>
      <button class="modal-close" onclick="closeModal('keyboardModal')">✕</button>
    </div>
    <div class="modal-body">
      <div class="hijaiyah-grid" id="hijaiyahGrid"></div>
      <div style="display:flex;gap:8px;margin-top:14px">
        <button type="button" class="btn btn-secondary" onclick="kbBackspace()">⌫ <?=t('backspace')?></button>
        <button type="button" class="btn btn-secondary" onclick="kbClear()">🗑 <?=t('clear')?></button>
        <button type="button" class="btn btn-primary" onclick="closeModal('keyboardModal')" style="margin-left:auto"><?=t('close')?></button>
      </div>
    </div>
  </div>
</div>

<script>
const HIJAIYAH = ['ا','ب','ت','ث','ج','ح','خ','د','ذ','ر','ز','س','ش','ص','ض','ط','ظ','ع','غ','ف','ق','ك','ل','م','ن','و','ه','ي'];
const rootInput = document.getElementById('rootInput');
const hijaiyahGrid = document.getElementById('hijaiyahGrid');
if (hijaiyahGrid) {
  HIJAIYAH.forEach(ch => {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'hijaiyah-key';
    btn.textContent = ch;
    btn.onclick = () => { if (rootInput && rootInput.value.length < 4) rootInput.value += ch; };
    hijaiyahGrid.appendChild(btn);
  });
}
function kbBackspace() { if (rootInput) rootInput.value = rootInput.value.slice(0, -1); }
function kbClear() { if (rootInput) rootInput.value = ''; }
function openModal(id) { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }

// AND-search builder (backlog-1.01.Alpha.023.md §2c): type a word,
// pick which root/sense you mean from the typeahead, repeat, then
// jump to mode=assoc (already built for the co-occurrence tree) with
// every picked root — same "show ayahs containing ALL of these" query,
// just reached by typing instead of clicking through Word Info.
const andBuilderEl = document.getElementById('andBuilder');
if (andBuilderEl) {
    let andTerms = [];
    const andInput = document.getElementById('andSearchInput');
    const andDropdown = document.getElementById('andSearchDropdown');
    const andGoBtn = document.getElementById('andSearchBtn');

    function renderAndTerms() {
        document.getElementById('andTerms').innerHTML = andTerms.map((t, i) => `
          <span class="and-term-chip">
            <span class="ar">${escapeHtml(t.root_ar)}</span>
            <span class="word">"${escapeHtml(t.word)}"</span>
            <button type="button" class="and-term-remove" data-idx="${i}">✕</button>
          </span>`).join('');
        document.querySelectorAll('.and-term-remove').forEach(btn => {
            btn.onclick = () => { andTerms.splice(+btn.dataset.idx, 1); renderAndTerms(); };
        });
        andGoBtn.disabled = andTerms.length < 1;
        const emptyState = document.getElementById('andEmptyState');
        if (emptyState) emptyState.style.display = andTerms.length ? 'none' : '';
    }

    function addAndTerm(rootId, rootAr, word) {
        if (andTerms.some(t => t.root_id === rootId)) return;
        andTerms.push({ root_id: rootId, root_ar: rootAr, word });
        renderAndTerms();
        andInput.value = '';
        andDropdown.classList.remove('active');
        andDropdown.innerHTML = '';
    }

    let andDebounce = null;
    andInput.addEventListener('input', () => {
        const q = andInput.value.trim();
        clearTimeout(andDebounce);
        if (q.length < 2) { andDropdown.classList.remove('active'); andDropdown.innerHTML = ''; return; }
        andDebounce = setTimeout(async () => {
            try {
                const res = await fetch(`api/root_lookup.php?q=${encodeURIComponent(q)}`);
                const data = await res.json();
                if (!data.roots.length) {
                    andDropdown.innerHTML = `<div class="assoc-search-empty">${escapeHtml(I18N_JS.no_results)}</div>`;
                } else {
                    andDropdown.innerHTML = data.roots.map(r => {
                        const meaning = (UI_LANG === 'en' ? r.meaning_en : r.meaning_id) || '';
                        return `<div class="assoc-search-item" data-root-id="${r.root_id}" data-root-ar="${escapeHtml(r.root_ar)}">
                          <span class="ar">${escapeHtml(r.root_ar)}</span><span class="gloss">${escapeHtml(meaning)}</span>
                        </div>`;
                    }).join('');
                    andDropdown.querySelectorAll('.assoc-search-item').forEach(item => {
                        item.onclick = () => addAndTerm(+item.dataset.rootId, item.dataset.rootAr, q);
                    });
                }
                andDropdown.classList.add('active');
            } catch (e) { andDropdown.classList.remove('active'); }
        }, 300);
    });
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.and-input-row')) andDropdown.classList.remove('active');
    });

    andGoBtn.onclick = () => {
        const qs = andTerms.map(t => `roots[]=${t.root_id}`).join('&');
        location.href = `index.php?page=search&mode=assoc&${qs}`;
    };

    // Arriving from the ambiguity prompt under a plain-text search:
    // seed = original query (re-run through the typeahead), pick = a
    // specific root already chosen there (added immediately, no extra click).
    const seed = andBuilderEl.dataset.seed;
    const pickId = +andBuilderEl.dataset.pick;
    if (pickId) {
        fetch(`api/root_lookup.php?q=${encodeURIComponent(seed)}`).then(r => r.json()).then(data => {
            const match = data.roots.find(r => r.root_id === pickId);
            if (match) addAndTerm(pickId, match.root_ar, seed);
        });
    } else if (seed) {
        andInput.value = seed;
        andInput.dispatchEvent(new Event('input'));
    }
}
</script>
