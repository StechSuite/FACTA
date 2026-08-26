<?php
/**
 * FACTA — API: Surah Ayahs (Book Mode data)
 * Returns every ayah of one surah (Arabic + active-language translation)
 * so Book Mode can build its slides client-side regardless of the
 * normal view's paged/full reading mode.
 */

require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$surahId = (int)($_GET['id'] ?? 0);
if ($surahId < 1 || $surahId > 114) {
    json_response(['error' => 'Invalid surah id'], 400);
}

$surah = get_surah($surahId);
if (!$surah) {
    json_response(['error' => 'Surah not found'], 404);
}

$lang = current_lang();
$ayahs = get_ayahs($surahId, $lang);

json_response([
    'surah_id' => $surahId,
    'number' => (int)$surah['number'],
    'name_ar' => $surah['name_ar'],
    'name_transliteration' => $surah['name_transliteration'],
    'verse_count' => (int)$surah['verse_count'],
    'ayahs' => array_map(fn($a) => [
        'id' => (int)$a['id'],
        'n' => (int)$a['ayah_number'],
        'ar' => $a['text_ar'],
        'tr' => $a['translation_text'] ?? '',
    ], $ayahs),
]);
