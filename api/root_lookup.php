<?php
/**
 * FACTA — API: Root Lookup (typeahead for the AND-search builder,
 * backlog-1.01.Alpha.023.md §2c option B/C)
 *
 * Given an Indonesian/English word, returns candidate roots ranked by
 * how central the word is to each root's curated meaning (see
 * match_roots_by_gloss()) — used both by the "AND search" term
 * builder and by the disambiguation prompt shown under an ambiguous
 * plain-text search.
 */

require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$q = trim($_GET['q'] ?? '');
if (mb_strlen($q, 'UTF-8') < 2) {
    json_response(['query' => $q, 'roots' => []]);
}

$matches = match_roots_by_gloss($q, 15);
$out = array_map(function ($r) {
    return [
        'root_id' => (int)$r['id'],
        'root_ar' => $r['root_ar'],
        'root_en' => $r['root_en'],
        'meaning_ar' => $r['meaning_ar'],
        'meaning_en' => $r['meaning_en'],
        'meaning_id' => $r['meaning_id'],
    ];
}, $matches);

json_response(['query' => $q, 'roots' => $out]);
