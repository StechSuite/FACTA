<?php
/**
 * FACTA — API: Word Info (clicked highlighted word -> root popup)
 *
 * Given a word (Arabic, Indonesian, or English), returns the matching
 * curated Arabic root(s) with their meanings, the distinct derived
 * word forms found in the Quran (dynamic morphology matching, same
 * heuristic engine as root search), and the root's curated synonym /
 * antonym / related roots from root_relations.
 */

require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

// Two entry points:
//  - word=...      fresh click on a highlighted <mark> (existing
//                   behavior: fuzzy match against curated/imported
//                   roots by Arabic form or ID/EN gloss). Only the
//                   single best match is returned — an ambiguous
//                   translation word (e.g. "rezeki") can loosely match
//                   several roots' meaning text, but showing 2-3 whole
//                   Word Info cards for one click was more confusing
//                   than useful; ties broken by curated-before-imported
//                   (hand-reviewed meanings first) then frequency.
//  - context[]=... recursive drill-down from the "Sering Muncul
//                   Bersama" tree (backlog-1.01.Alpha.016.md): an
//                   ordered list of already-selected root ids, the
//                   LAST one is the "focus" root whose own meaning/
//                   derived-forms this response describes, but
//                   associations are computed over the WHOLE set (AND).
$word = trim($_GET['word'] ?? '');
$context = array_values(array_filter(array_map('intval', (array)($_GET['context'] ?? []))));

if (!$context && mb_strlen($word, 'UTF-8') < 2) {
    json_response(['word' => $word, 'roots' => []]);
}

// Imported-but-not-yet-AI-curated roots (source='imported', meaning_*
// still NULL) are excluded here — they're real matches but a popup
// with blank meaning fields is worse than not showing them at all.
// They remain fully usable elsewhere (root-search mode, the
// association tree) which don't depend on a meaning being present.
$curatedFilter = "source != 'imported' OR (meaning_id IS NOT NULL AND meaning_id != '')";

if ($context) {
    // Context mode: focus root is fixed (the last id in the path),
    // no fuzzy matching needed.
    $focusId = end($context);
    $focus = Database::queryOne("SELECT * FROM root_words WHERE id = ?", [$focusId]);
    $matched = $focus ? [$focus] : [];
} else {
    $isArabic = (bool)preg_match('/\p{Arabic}/u', $word);

    if ($isArabic) {
        $allRoots = Database::query("SELECT * FROM root_words WHERE {$curatedFilter}");
        $wc = ar_normalize($word);
        $matched = [];
        foreach ($allRoots as $r) {
            $rc = ar_normalize($r['root_ar']);
            if (ar_word_matches_root($wc, $rc)) {
                // rank contiguous-radical matches (e.g. "رحم" inside "الرحمن")
                // above looser in-order subsequence matches
                $r['_score'] = mb_strpos($wc, $rc) !== false ? 2 : 1;
                $matched[] = $r;
            }
        }
        usort($matched, function ($a, $b) {
            return ($b['_score'] ?? 0) <=> ($a['_score'] ?? 0)
                ?: ($b['source'] === 'curated' ? 1 : 0) <=> ($a['source'] === 'curated' ? 1 : 0)
                ?: ($b['frequency'] ?? 0) <=> ($a['frequency'] ?? 0);
        });
        $matched = array_slice($matched, 0, 1);
    } else {
        // Non-Arabic (translation) word: match against the root's ID/EN
        // meanings, position-aware so incidental mentions deep in a
        // definition sentence don't outrank/pollute the real match
        // (backlog-1.01.Alpha.023.md §2d).
        $matched = match_roots_by_gloss($word, 1);
    }
}

$out = [];
foreach ($matched as $r) {
    $rootClean = ar_normalize($r['root_ar']);
    $letters = preg_split('//u', $rootClean, -1, PREG_SPLIT_NO_EMPTY);

    // Distinct derived word forms (dynamic, same LIKE-prefilter + PHP
    // morphology check pattern as search_by_root)
    $where = [];
    $params = [];
    foreach (array_unique($letters) as $ch) {
        $where[] = 'text_ar_clean LIKE ?';
        $params[] = '%' . $ch . '%';
    }
    $candidates = Database::query(
        "SELECT ayah_id, text_ar, text_ar_clean, translation_id, translation_en
         FROM ayah_words
         WHERE " . implode(' AND ', $where) . "
         LIMIT 5000",
        $params
    );
    $forms = [];
    $matchedAyahs = [];
    foreach ($candidates as $c) {
        if (!ar_word_matches_root($c['text_ar_clean'], $rootClean)) continue;
        $matchedAyahs[(int)$c['ayah_id']] = true;
        $key = $c['text_ar_clean'];
        if (!isset($forms[$key])) {
            $forms[$key] = [
                'text_ar' => $c['text_ar'],
                'clean' => $c['text_ar_clean'],
                'gloss_id' => $c['translation_id'],
                'gloss_en' => $c['translation_en'],
                'count' => 0,
            ];
        }
        $forms[$key]['count']++;
    }
    usort($forms, fn($a, $b) => $b['count'] <=> $a['count']);
    $derivedCleanSet = array_flip(array_column($forms, 'clean'));
    $forms = array_slice(array_values($forms), 0, 24);

    // "Sering Muncul Bersama" / multi-level drill-down (backlog-1.01.
    // Alpha.016.md): root-grouped co-occurrence, not raw word forms —
    // e.g. one "أمن (Aman, iman) ×15" entry instead of separate
    // ءامنوا/يؤمنون/etc. chips. $path is the full accumulated AND-set:
    // just this root at level 1 (fresh click), or the whole context
    // (this root's ancestors + itself) when drilling deeper.
    $path = $context ?: [(int)$r['id']];
    // No cap — show every co-occurring root, even ones seen only once
    // (explicit user choice: this list is meant to be complete).
    $co = root_co_occurrence($path);
    $associations = array_map(function ($c) {
        return [
            'root_id' => $c['root_id'],
            'text_ar' => $c['root_ar'],
            'clean' => $c['root_ar'],
            'gloss_id' => $c['meaning_id'],
            'gloss_en' => $c['meaning_en'],
            'count' => $c['count'],
            'sample_word_form' => $c['sample_word_form'],
        ];
    }, $co['children']);
    $ayahCount = $co['ayah_count'];
    $onlyCount = $co['only_count'];
    // Context path as root_ar (for the breadcrumb) + ids (for the next
    // fetch) — the frontend never needs to re-derive this.
    $pathInfo = array_map(function ($id) {
        $row = Database::queryOne("SELECT root_ar FROM root_words WHERE id = ?", [$id]);
        return ['root_id' => $id, 'root_ar' => $row['root_ar'] ?? ''];
    }, $path);

    // Curated relations, both directions
    $relations = Database::query(
        "SELECT rw.root_ar, rw.root_en, rw.meaning_id, rw.meaning_en, rr.relation_type
         FROM root_relations rr
         JOIN root_words rw ON rw.id = CASE WHEN rr.source_root_id = ? THEN rr.target_root_id ELSE rr.source_root_id END
         WHERE rr.source_root_id = ? OR rr.target_root_id = ?",
        [$r['id'], $r['id'], $r['id']]
    );
    $synonyms = array_values(array_filter($relations, fn($x) => $x['relation_type'] === 'synonym'));
    $antonyms = array_values(array_filter($relations, fn($x) => $x['relation_type'] === 'antonym'));
    $related = array_values(array_filter($relations, fn($x) => $x['relation_type'] === 'related'));

    $out[] = [
        'root_id' => (int)$r['id'],
        'root_ar' => $r['root_ar'],
        'root_en' => $r['root_en'],
        'meaning_ar' => $r['meaning_ar'],
        'meaning_en' => $r['meaning_en'],
        'meaning_id' => $r['meaning_id'],
        'derived' => $forms,
        'associations' => $associations,
        'ayah_count' => $ayahCount,
        'only_count' => $onlyCount,
        'path' => $pathInfo,
        'synonyms' => $synonyms,
        'antonyms' => $antonyms,
        'related' => $related,
    ];
}

json_response(['word' => $word, 'roots' => $out]);
