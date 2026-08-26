<?php
/**
 * FACTA — Kurator Sarf API
 *
 * Endpoints (admin-only):
 *   GET  ?action=classified&limit=50&offset=0  → list classified word forms
 *   POST {action:'toggle_verify', id:123}          → toggle verified flag
 *   GET  ?action=stats                             → per-pattern counts + overall stats
 */
require_once __DIR__ . '/../includes/functions.php';

if (!is_admin() && !is_curator()) {
    json_response(['error' => 'Forbidden'], 403);
}

$action = $_GET['action'] ?? ($_POST['action'] ?? 'stats');

// toggle_verify: admin only
if ($action === 'toggle_verify' && !is_admin()) {
    json_response(['error' => 'Forbidden — admin only'], 403);
}

if ($action === 'stats') {
    $totalPatterns = (int)(Database::queryOne('SELECT COUNT(*) c FROM sarf_patterns')['c'] ?? 0);
    $totalClassified = (int)(Database::queryOne('SELECT COUNT(DISTINCT ayah_root_word_id) c FROM word_form_patterns')['c'] ?? 0);
    $totalPending = (int)(Database::queryOne("SELECT COUNT(*) c FROM ayah_root_words WHERE word_form IS NOT NULL AND word_form!='' AND id NOT IN (SELECT ayah_root_word_id FROM word_form_patterns)")['c'] ?? 0);
    $totalVerified = (int)(Database::queryOne("SELECT COUNT(*) c FROM word_form_patterns WHERE verified = 1")['c'] ?? 0);
    $totalUnverified = (int)(Database::queryOne("SELECT COUNT(*) c FROM word_form_patterns WHERE verified = 0 OR verified IS NULL")['c'] ?? 0);

    $patternCounts = Database::query(
        "SELECT sp.id, sp.pattern_ar, sp.pattern_type, sp.bab, sp.description_id, sp.sort_order,
                COUNT(wfp.id) as count,
                SUM(CASE WHEN wfp.verified = 1 THEN 1 ELSE 0 END) as verified_count
         FROM sarf_patterns sp
         LEFT JOIN word_form_patterns wfp ON wfp.pattern_id = sp.id
         GROUP BY sp.id
         ORDER BY sp.sort_order, sp.id"
    );

    json_response([
        'total_patterns' => $totalPatterns,
        'total_classified' => $totalClassified,
        'total_pending' => $totalPending,
        'total_verified' => $totalVerified,
        'total_unverified' => $totalUnverified,
        'patterns' => $patternCounts,
    ]);
}

if ($action === 'classified') {
    $limit = max(1, min(200, (int)($_GET['limit'] ?? 50)));
    $offset = max(0, (int)($_GET['offset'] ?? 0));
    $patternFilter = $_GET['pattern_id'] ?? null;
    $verifiedFilter = $_GET['verified'] ?? null;

    $sql = "SELECT wfp.id, arw.word_form, rw.root_ar, sp.pattern_ar, sp.pattern_type, sp.id as pattern_id,
                   wfp.confidence, wfp.verified, wfp.ai_reasoning, wfp.created_at
            FROM word_form_patterns wfp
            JOIN ayah_root_words arw ON arw.id = wfp.ayah_root_word_id
            JOIN root_words rw ON rw.id = arw.root_word_id
            JOIN sarf_patterns sp ON sp.id = wfp.pattern_id
            WHERE 1=1";
    $params = [];

    if ($patternFilter) {
        $sql .= " AND sp.id = ?";
        $params[] = (int)$patternFilter;
    }
    if ($verifiedFilter !== null && $verifiedFilter !== '') {
        if ((int)$verifiedFilter === 1) {
            $sql .= " AND wfp.verified = ?";
            $params[] = 1;
        } else {
            $sql .= " AND (wfp.verified = ? OR wfp.verified IS NULL)";
            $params[] = 0;
        }
    }

    $sql .= " ORDER BY wfp.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;

    $rows = Database::query($sql, $params);

    $countSql = "SELECT COUNT(*) c FROM word_form_patterns wfp
                 JOIN ayah_root_words arw ON arw.id = wfp.ayah_root_word_id
                 JOIN sarf_patterns sp ON sp.id = wfp.pattern_id
                 WHERE 1=1";
    $countParams = [];
    if ($patternFilter) {
        $countSql .= " AND sp.id = ?";
        $countParams[] = (int)$patternFilter;
    }
    if ($verifiedFilter !== null && $verifiedFilter !== '') {
        if ((int)$verifiedFilter === 1) {
            $countSql .= " AND wfp.verified = ?";
            $countParams[] = 1;
        } else {
            $countSql .= " AND (wfp.verified = ? OR wfp.verified IS NULL)";
            $countParams[] = 0;
        }
    }
    $totalRows = (int)(Database::queryOne($countSql, $countParams)['c'] ?? 0);

    json_response([
        'rows' => $rows,
        'total' => $totalRows,
        'limit' => $limit,
        'offset' => $offset,
    ]);
}

if ($action === 'toggle_verify') {
    $in = json_decode(file_get_contents('php://input'), true);
    $id = (int)($in['id'] ?? 0);
    if (!$id) {
        json_response(['error' => 'Missing id'], 400);
    }

    $current = Database::queryOne('SELECT verified FROM word_form_patterns WHERE id = ?', [$id]);
    if (!$current) {
        json_response(['error' => 'Not found'], 404);
    }

    $newValue = ((int)$current['verified'] === 1) ? 0 : 1;
    Database::exec('UPDATE word_form_patterns SET verified = ? WHERE id = ?', [$newValue, $id]);
    json_response(['id' => $id, 'verified' => $newValue]);
}

json_response(['error' => 'Unknown action'], 400);
