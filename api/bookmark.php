<?php
/**
 * FACTA — API: Bookmarks (Create / Delete)
 */

require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    // Create bookmark
    $surahId = (int)($_POST['surah_id'] ?? 0);
    $ayahNumber = (int)($_POST['ayah_number'] ?? 0);

    if (!$surahId || !$ayahNumber) {
        json_response(['success' => false, 'message' => 'Missing parameters']);
    }

    try {
        // Get ayah_id
        $ayah = Database::queryOne("SELECT id FROM ayahs WHERE surah_id = ? AND ayah_number = ?", [$surahId, $ayahNumber]);
        $ayahId = $ayah['id'] ?? null;

        Database::exec(
            "INSERT INTO bookmarks (surah_id, ayah_number, ayah_id, color)
             VALUES (?, ?, ?, '#6366f1')
             ON CONFLICT(surah_id, ayah_number) DO NOTHING",
            [$surahId, $ayahNumber, $ayahId]
        );
        json_response(['success' => true, 'message' => 'Ayat ditandai']);
    } catch (Exception $e) {
        json_response(['success' => false, 'message' => $e->getMessage()]);
    }

} elseif ($method === 'DELETE') {
    // Delete bookmark
    parse_str(file_get_contents('php://input'), $data);
    $id = (int)($data['id'] ?? $_GET['id'] ?? 0);

    if (!$id) {
        json_response(['success' => false, 'message' => 'Missing ID']);
    }

    try {
        Database::exec("DELETE FROM bookmarks WHERE id = ?", [$id]);
        json_response(['success' => true, 'message' => 'Penanda dihapus']);
    } catch (Exception $e) {
        json_response(['success' => false, 'message' => $e->getMessage()]);
    }

} else {
    // List bookmarks
    json_response(['bookmarks' => get_bookmarks()]);
}
