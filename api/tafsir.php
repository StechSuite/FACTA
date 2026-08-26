<?php
/**
 * FACTA — API: Tafsir
 */

require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$surahId = (int)($_GET['surah_id'] ?? 0);
$ayahNumber = (int)($_GET['ayah'] ?? 0);
$lang = current_lang();

if (!$surahId || !$ayahNumber) {
    json_response(['error' => 'Missing parameters']);
}

$ayah = get_ayah($surahId, $ayahNumber);
if (!$ayah) {
    json_response(['error' => 'Ayah not found']);
}

json_response([
    'surah_id' => $surahId,
    'ayah_number' => $ayahNumber,
    'ayah_text' => $ayah['text_ar'] ?? null,
    'tafsir' => $ayah['tafsir_text'] ?? null,
    'author' => 'Ibn Kathir',
    'language' => $lang
]);
