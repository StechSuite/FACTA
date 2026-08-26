<?php
/**
 * FACTA — User Profile API
 *
 * Endpoints (admin/auth required tergantung action):
 *   GET  ?action=me                      → profil user yang sedang login
 *   POST {action:'update', whatsapp, city, province, country} → update profil
 *   GET  ?action=bookmarks                → list bookmarks user login
 *   POST {action:'add_bookmark', surah_id, ayah_number, note, tags, color}
 *   POST {action:'remove_bookmark', surah_id, ayah_number}
 *   GET  ?action=history&limit=50&offset=0 → browsing history
 *   POST {action:'log_history', surah_id, ayah_number, url, title}
 */
require_once __DIR__ . '/../includes/functions.php';
require_login();

$action = $_GET['action'] ?? ($_POST['action'] ?? 'me');
$user = current_user();
$uid = (int)$user['id'];

if ($action === 'me') {
    json_response([
        'id'       => $user['id'],
        'email'    => $user['email'],
        'name'     => $user['name'],
        'avatar'   => $user['avatar_url'] ?? null,
        'whatsapp' => $user['whatsapp'] ?? null,
        'city'     => $user['city'] ?? null,
        'province' => $user['province'] ?? null,
        'country'  => $user['country'] ?? null,
        'roles'    => $user['roles'] ?? [],
    ]);
}

if ($action === 'update') {
    $in = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $fields = [];
    $params = [];
    $allowed = ['whatsapp' => 'whatsapp', 'city' => 'city', 'province' => 'province', 'country' => 'country'];
    foreach ($allowed as $key => $col) {
        if (array_key_exists($key, $in)) {
            $fields[] = "$col = ?";
            $params[] = $in[$key];
        }
    }
    if (!$fields) {
        json_response(['error' => 'No fields to update'], 400);
    }
    $params[] = $uid;
    Database::exec("UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?", $params);
    log_user_action('profile_update', 'users', $uid, ['fields' => array_keys($allowed)]);
    json_response(['ok' => true]);
}

if ($action === 'bookmarks') {
    $rows = Database::query(
        "SELECT ub.*, s.name_ar, s.name_en, s.name_id
         FROM user_bookmarks ub
         JOIN surahs s ON s.id = ub.surah_id
         WHERE ub.user_id = ?
         ORDER BY ub.created_at DESC",
        [$uid]
    );
    json_response(['bookmarks' => $rows]);
}

if ($action === 'add_bookmark') {
    $in = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $sid = (int)($in['surah_id'] ?? 0);
    $an  = (int)($in['ayah_number'] ?? 0);
    if (!$sid || !$an) json_response(['error' => 'Missing surah_id / ayah_number'], 400);
    Database::exec(
        "INSERT INTO user_bookmarks (user_id, surah_id, ayah_number, note, tags, color)
         VALUES (?, ?, ?, ?, ?, ?)
         ON CONFLICT(user_id, surah_id, ayah_number) DO UPDATE SET note=excluded.note, tags=excluded.tags, color=excluded.color, created_at=datetime('now')",
        [$uid, $sid, $an, $in['note'] ?? null, $in['tags'] ?? null, $in['color'] ?? '#1769e0']
    );
    json_response(['ok' => true]);
}

if ($action === 'remove_bookmark') {
    $in = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $sid = (int)($in['surah_id'] ?? 0);
    $an  = (int)($in['ayah_number'] ?? 0);
    Database::exec("DELETE FROM user_bookmarks WHERE user_id = ? AND surah_id = ? AND ayah_number = ?", [$uid, $sid, $an]);
    json_response(['ok' => true]);
}

if ($action === 'history') {
    $limit  = max(1, min(200, (int)($_GET['limit'] ?? 50)));
    $offset = max(0, (int)($_GET['offset'] ?? 0));
    $rows = Database::query(
        "SELECT uh.*, s.name_ar, s.name_en, s.name_id
         FROM user_history uh
         LEFT JOIN surahs s ON s.id = uh.surah_id
         WHERE uh.user_id = ?
         ORDER BY uh.viewed_at DESC LIMIT ? OFFSET ?",
        [$uid, $limit, $offset]
    );
    $total = (int)(Database::queryOne("SELECT COUNT(*) c FROM user_history WHERE user_id = ?", [$uid])['c'] ?? 0);
    json_response(['rows' => $rows, 'total' => $total, 'limit' => $limit, 'offset' => $offset]);
}

if ($action === 'log_history') {
    $in = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    Database::exec(
        "INSERT INTO user_history (user_id, surah_id, ayah_number, url, title)
         VALUES (?, ?, ?, ?, ?)",
        [$uid, $in['surah_id'] ?? null, $in['ayah_number'] ?? null, $in['url'] ?? null, $in['title'] ?? null]
    );
    // Keep history tidy: delete oldest beyond 500 entries per user
    Database::exec(
        "DELETE FROM user_history WHERE id IN (
            SELECT id FROM user_history WHERE user_id = ? ORDER BY viewed_at DESC LIMIT -1 OFFSET 500
        )",
        [$uid]
    );
    json_response(['ok' => true]);
}

json_response(['error' => 'Unknown action'], 400);
