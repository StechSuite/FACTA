<?php
/**
 * FACTA — API: Instant Search (header search-as-you-type dropdown)
 * Auto-detects Arabic vs. translation-language query, returns a short,
 * pre-highlighted list of top matches. The full search.php page (text
 * mode / root mode / derived-words) remains the authoritative search —
 * this is a lightweight preview layered on top of it.
 */

require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$query = trim($_GET['q'] ?? '');
if (mb_strlen($query, 'UTF-8') < 2) {
    json_response(['results' => [], 'total' => 0, 'lang' => 'ar', 'query' => $query]);
}

// Arabic script in the query -> search Arabic text; otherwise search the
// user's translation-language preference (falls back to current_lang()).
$isArabic = (bool)preg_match('/\p{Arabic}/u', $query);
$lang = $isArabic ? 'ar' : ($_COOKIE['translation_lang'] ?? current_lang());
if (!$isArabic && !in_array($lang, ['en', 'id'], true)) $lang = 'id';

$limit = 5;
$allResults = search_ayahs($query, $lang, 50); // small cap is enough for a preview + accurate count
$preview = array_slice($allResults, 0, $limit);

$out = [];
foreach ($preview as $r) {
    $arabic = $lang === 'ar' ? highlight_text($r['text_ar'], $query, 'ar') : $r['text_ar'];
    $translation = $r['translation_text'] ?? '';
    if ($translation && $lang !== 'ar') {
        $translation = highlight_text($translation, $query, $lang);
    }
    $out[] = [
        'surah_id' => (int)$r['surah_id'],
        'ayah_number' => (int)$r['ayah_number'],
        'surah_name' => $r['name_transliteration'],
        'surah_name_en' => $r['name_en'],
        'text_ar' => $arabic,
        'translation' => $translation,
    ];
}

json_response([
    'results' => $out,
    'total' => count($allResults),
    'lang' => $lang,
    'query' => $query,
]);
