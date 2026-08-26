<?php
/**
 * FACTA — Helper Functions
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/morphology.php';

// App version. backlog.md in this repo is frozen pre-1.0.0 history from
// before the CoreAI-CPanel → FACTA extraction (see its own header note)
// — it no longer tracks this repo's live version, CHANGELOG.md does.
// So, unlike that private monorepo's copy of this function, this just
// returns the constant directly.
function get_app_version(): string {
    return APP_VERSION;
}

// Admin login credentials — config.admin.json overrides these if present
// (see config.admin.json.example); otherwise this hardcoded default is
// used, so the app has a working admin login with zero setup. This
// default is PUBLIC (it's in the public repo) — api/admin_login.php
// warns on every login while it's still active, and README.md says to
// change it before deploying anywhere reachable by the public.
function admin_login_credentials(): array {
    $default = ['username' => 'admin', 'password' => 'bismillah'];
    $file = __DIR__ . '/../config.admin.json';
    if (is_readable($file)) {
        $decoded = json_decode(file_get_contents($file) ?: '', true);
        if (is_array($decoded) && isset($decoded['username'], $decoded['password'])) {
            return ['username' => $decoded['username'], 'password' => $decoded['password']];
        }
    }
    return $default;
}

function admin_using_default_credentials(): bool {
    $creds = admin_login_credentials();
    return $creds['username'] === 'admin' && $creds['password'] === 'bismillah';
}

// Safe JSON response
// Simple admin gate (used for local Kurator tools)
function is_admin(): bool {
    if (isset($_COOKIE['admin_secret']) && hash_equals(ADMIN_SECRET, $_COOKIE['admin_secret'])) {
        return true;
    }
    $user = current_user();
    if (!$user) return false;
    return has_role($user['id'], 'admin');
}

function is_curator(): bool {
    $user = current_user();
    if (!$user) return false;
    return has_role($user['id'], 'admin') || has_role($user['id'], 'curator');
}

function is_logged_in(): bool {
    return current_user() !== null;
}

/** Resolve current user from bearer cookie. */
function current_user(): ?array {
    static $cached = null;
    if ($cached !== null) return $cached;

    $token = $_COOKIE['auth_token'] ?? null;
    if (!$token) { $cached = null; return null; }

    $user = Database::queryOne(
        "SELECT u.*, GROUP_CONCAT(r.name) as roles
         FROM users u
         LEFT JOIN user_roles ur ON ur.user_id = u.id
         LEFT JOIN roles r ON r.id = ur.role_id
         WHERE u.auth_token = ? AND (u.token_expires IS NULL OR u.token_expires > datetime('now'))
         GROUP BY u.id",
        [$token]
    );
    if ($user) {
        $user['roles'] = $user['roles'] ? explode(',', $user['roles']) : [];
    }
    $cached = $user ?: null;
    return $cached;
}

function has_role(int $userId, string $roleName): bool {
    $row = Database::queryOne(
        "SELECT 1 FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ? AND r.name = ?",
        [$userId, $roleName]
    );
    return (bool)$row;
}

function require_login(): void {
    if (!is_logged_in()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized. Please login first.']);
        exit;
    }
}

function require_admin(): void {
    if (!is_admin()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden. Admin access required.']);
        exit;
    }
}

function require_curator(): void {
    if (!is_curator()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden. Curator or admin access required.']);
        exit;
    }
}

function log_user_action(string $action, ?string $entity = null, ?int $entityId = null, ?array $details = null): void {
    $user = current_user();
    $userId = $user['id'] ?? null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
    Database::exec(
        "INSERT INTO user_logs (user_id, action, entity, entity_id, details, ip_address, user_agent)
         VALUES (?, ?, ?, ?, ?, ?, ?)",
        [$userId, $action, $entity, $entityId, $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null, $ip, $ua]
    );
}

function json_response(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Get all surahs
function get_surahs(): array {
    return Database::query("SELECT * FROM surahs ORDER BY number");
}

// Get single surah
function get_surah(int $id): ?array {
    return Database::queryOne("SELECT * FROM surahs WHERE id = ?", [$id]);
}

// Get ayahs of a surah with translation
function get_ayahs(int $surah_id, string $lang = 'id'): array {
    return Database::query(
        "SELECT a.*, t.text as translation_text, t.language_code
         FROM ayahs a
         LEFT JOIN translations t ON t.ayah_id = a.id AND t.language_code = ?
         WHERE a.surah_id = ?
         ORDER BY a.ayah_number",
        [$lang, $surah_id]
    );
}

// Get single ayah with full details
function get_ayah(int $surah_id, int $ayah_number): ?array {
    $lang = current_lang();
    $results = Database::query(
        "SELECT a.*, s.name_ar, s.name_en, s.name_id, s.name_transliteration,
                t.text as translation_text,
                tf.text as tafsir_text
         FROM ayahs a
         JOIN surahs s ON s.id = a.surah_id
         LEFT JOIN translations t ON t.ayah_id = a.id AND t.language_code = ?
         LEFT JOIN tafsirs tf ON tf.ayah_id = a.id AND tf.language_code = ?
         WHERE a.surah_id = ? AND a.ayah_number = ?",
        [$lang, $lang, $surah_id, $ayah_number]
    );
    return $results[0] ?? null;
}

// Get word-by-word breakdown of an ayah (Arabic + transliteration + EN/ID gloss)
function get_ayah_words(int $ayahId): array {
    return Database::query(
        "SELECT * FROM ayah_words WHERE ayah_id = ? ORDER BY position",
        [$ayahId]
    );
}

// Get topics for an ayah
function get_ayah_topics(int $ayah_id): array {
    return Database::query(
        "SELECT t.* FROM topics t
         JOIN topic_ayahs ta ON ta.topic_id = t.id
         WHERE ta.ayah_id = ? AND t.is_active = 1
         ORDER BY t.sort_order, t.name_en",
        [$ayah_id]
    );
}

// Get root words for an ayah. Imported-but-not-yet-AI-curated roots
// (source='imported', meaning_* still NULL) are excluded — a chip
// reading "root — " with nothing after the dash is worse than not
// showing that root at all; they'll appear here automatically once
// curated via data/words-kurator-by-ai/.
function get_ayah_roots(int $ayah_id): array {
    return Database::query(
        "SELECT rw.*, arw.word_form, arw.position FROM root_words rw
         JOIN ayah_root_words arw ON arw.root_word_id = rw.id
         WHERE arw.ayah_id = ?
           AND (rw.source != 'imported' OR (rw.meaning_id IS NOT NULL AND rw.meaning_id != ''))",
        [$ayah_id]
    );
}

/**
 * Match root_words by curated meaning text (ID/EN), for a non-Arabic
 * search word. Position-aware: a match near the START of the meaning
 * text (the primary gloss, e.g. "Anak, lahir" for ولد) scores far
 * higher than one buried in an explanatory sentence (e.g. "...
 * sehingga melahirkan kata ummah..." for أمم, which just uses the word
 * figuratively/incidentally — not what "lahir" actually means there).
 * Matches beyond $maxPosition words in are excluded entirely, not
 * just deprioritized, so disambiguation UIs (typeahead, Word Info
 * popup) don't surface clearly-incidental hits (backlog-1.01.Alpha.
 * 023.md §2d — found via a real "lahir" query nyasar-ing into 8
 * unrelated roots before this fix).
 *
 * Returns root_words rows (with an added _score) sorted best-first,
 * capped at $limit, already filtered by the curated/imported-with-
 * meaning rule used elsewhere (a blank-meaning imported root is never
 * a useful match here).
 */
function match_roots_by_gloss(string $word, int $limit = 10): array {
    $word = trim($word);
    if (mb_strlen($word, 'UTF-8') < 2) return [];

    $maxPosition = 6;
    $curatedFilter = "source != 'imported' OR (meaning_id IS NOT NULL AND meaning_id != '')";
    $allRoots = Database::query("SELECT * FROM root_words WHERE {$curatedFilter}");

    $matched = [];
    foreach ($allRoots as $r) {
        $best = null;
        foreach ([['meaning_id', 'id'], ['meaning_en', 'en']] as [$field, $flang]) {
            $terms = preg_split('/[^\p{L}\'-]+/u', $r[$field] ?? '', -1, PREG_SPLIT_NO_EMPTY);
            foreach ($terms as $idx => $t) {
                if ($idx > $maxPosition) break;
                if (word_stems_match($t, $word, $flang)) {
                    if ($best === null || $idx < $best) $best = $idx;
                    break;
                }
            }
        }
        if ($best !== null) {
            $r['_score'] = $maxPosition + 1 - $best;
            $matched[] = $r;
        }
    }

    usort($matched, function ($a, $b) {
        return ($b['_score'] ?? 0) <=> ($a['_score'] ?? 0)
            ?: ($b['source'] === 'curated' ? 1 : 0) <=> ($a['source'] === 'curated' ? 1 : 0)
            ?: ($b['frequency'] ?? 0) <=> ($a['frequency'] ?? 0);
    });
    return array_slice($matched, 0, $limit);
}

/**
 * Multi-level root co-occurrence ("Sering Muncul Bersama" drill-down,
 * backlog-1.01.Alpha.016.md). Given a set of already-selected root
 * ids S, finds the ayahs containing ALL of them (via ayah_root_words,
 * populated by data/build_word_roots_import.py), the next-level
 * candidate roots (other roots present in that ayah-set, grouped +
 * counted, each with one sample derived word form actually seen),
 * and whether any of those ayahs contain EXACTLY S and nothing more
 * (the "ONLY" terminal option). k=1 (a single root) is exactly the
 * same query the flat Level-1 associations used to run by hand.
 *
 * $childLimit <= 0 means unlimited — every co-occurring root is
 * returned, even ones seen only once (explicit user choice: this list
 * is meant to be complete, not a top-N preview). With only 1,655
 * roots total this is at most ~1,654 rows either way, and the query
 * itself stays fast (tens of ms) even for the busiest root.
 *
 * Returns ['ayah_ids'=>[...], 'ayah_count'=>N, 'only_count'=>M,
 *          'children'=>[{root_id,root_ar,root_en,meaning_ar,
 *                         meaning_en,meaning_id,count,sample_word_form}]]
 */
function root_co_occurrence(array $rootIds, int $childLimit = 0): array {
    $rootIds = array_values(array_unique(array_map('intval', $rootIds)));
    $k = count($rootIds);
    if ($k === 0) return ['ayah_ids' => [], 'ayah_count' => 0, 'only_count' => 0, 'children' => []];

    $rootPh = implode(',', array_fill(0, $k, '?'));

    // $k is inlined (not bound) — PDO binds params as TEXT by default,
    // and SQLite's HAVING COUNT(...) = ? silently returns zero rows
    // against a text-affinity bound param even though the identical
    // literal integer works fine. $k is our own count(), never user
    // input, so inlining is safe.
    $ayahRows = Database::query(
        "SELECT ayah_id FROM ayah_root_words
         WHERE root_word_id IN ({$rootPh})
         GROUP BY ayah_id
         HAVING COUNT(DISTINCT root_word_id) = {$k}",
        $rootIds
    );
    $ayahIds = array_map(fn($r) => (int)$r['ayah_id'], $ayahRows);
    $ayahCount = count($ayahIds);
    if ($ayahCount === 0) {
        return ['ayah_ids' => [], 'ayah_count' => 0, 'only_count' => 0, 'children' => []];
    }
    // Ayah ids are integers we just computed ourselves (never user
    // input) — safe to inline directly, avoiding the ~999-param bind
    // limit that a large common root's ayah-set would hit.
    $ayahInList = implode(',', $ayahIds);

    $onlyRows = Database::query(
        "SELECT ayah_id FROM ayah_root_words
         WHERE ayah_id IN ({$ayahInList})
         GROUP BY ayah_id
         HAVING COUNT(*) = {$k}"
    );
    $onlyAyahIds = array_map(fn($r) => (int)$r['ayah_id'], $onlyRows);
    $onlyCount = count($onlyAyahIds);

    $limitSql = $childLimit > 0 ? "LIMIT {$childLimit}" : '';
    $childRows = Database::query(
        "SELECT root_word_id, COUNT(*) AS cnt,
                (SELECT word_form FROM ayah_root_words w2
                 WHERE w2.root_word_id = ayah_root_words.root_word_id
                   AND w2.ayah_id IN ({$ayahInList})
                 LIMIT 1) AS sample_word_form
         FROM ayah_root_words
         WHERE ayah_id IN ({$ayahInList}) AND root_word_id NOT IN ({$rootPh})
         GROUP BY root_word_id
         ORDER BY cnt DESC
         {$limitSql}",
        $rootIds
    );

    $children = [];
    if ($childRows) {
        $childIds = array_map(fn($r) => (int)$r['root_word_id'], $childRows);
        $rw = Database::query(
            "SELECT id, root_ar, root_en, meaning_ar, meaning_en, meaning_id FROM root_words
             WHERE id IN (" . implode(',', $childIds) . ")"
        );
        $rwById = [];
        foreach ($rw as $r) $rwById[(int)$r['id']] = $r;
        foreach ($childRows as $c) {
            $id = (int)$c['root_word_id'];
            $r = $rwById[$id] ?? null;
            if (!$r) continue;
            $children[] = [
                'root_id' => $id,
                'root_ar' => $r['root_ar'],
                'root_en' => $r['root_en'],
                'meaning_ar' => $r['meaning_ar'],
                'meaning_en' => $r['meaning_en'],
                'meaning_id' => $r['meaning_id'],
                'count' => (int)$c['cnt'],
                'sample_word_form' => $c['sample_word_form'],
            ];
        }
    }

    return [
        'ayah_ids' => $ayahIds, 'ayah_count' => $ayahCount,
        'only_ayah_ids' => $onlyAyahIds, 'only_count' => $onlyCount,
        'children' => $children,
    ];
}

// Get topic tree (hierarchical)
function get_topic_tree(int $parent_id = null): array {
    $sql = "SELECT * FROM topics WHERE is_active = 1 ";
    $params = [];
    if ($parent_id === null) {
        $sql .= "AND parent_id IS NULL";
    } else {
        $sql .= "AND parent_id = ?";
        $params[] = $parent_id;
    }
    $sql .= " ORDER BY sort_order, name_en";
    return Database::query($sql, $params);
}

// Get ayahs by topic
function get_topic_ayahs(int $topic_id): array {
    $lang = current_lang();
    return Database::query(
        "SELECT a.*, s.name_en as surah_name, s.name_ar, t.text as translation_text
         FROM ayahs a
         JOIN topic_ayahs ta ON ta.ayah_id = a.id
         JOIN surahs s ON s.id = a.surah_id
         LEFT JOIN translations t ON t.ayah_id = a.id AND t.language_code = ?
         WHERE ta.topic_id = ?
         ORDER BY a.surah_id, a.ayah_number",
        [$lang, $topic_id]
    );
}

// Get related topics
function get_related_topics(int $topic_id): array {
    return Database::query(
        "SELECT t.*, tr.relation_type, tr.strength FROM topics t
         JOIN topic_relations tr ON (tr.target_topic_id = t.id OR tr.source_topic_id = t.id)
         WHERE (tr.source_topic_id = ? OR tr.target_topic_id = ?)
           AND t.id != ?
         ORDER BY tr.strength DESC",
        [$topic_id, $topic_id, $topic_id]
    );
}

// Search ayahs using FTS5
function search_ayahs(string $query, string $lang = 'ar', int $limit = 50): array {
    $query = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $query);
    $query = trim($query);
    if (strlen($query) < 2) return [];
    $ftsQuery = str_replace(' ', ' OR ', $query);

    if ($lang === 'ar') {
        // Search Arabic text
        $results = Database::query(
            "SELECT a.*, s.name_en, s.name_ar, s.name_id, s.name_transliteration,
                    t.text as translation_text
             FROM ayahs a
             JOIN ayahs_fts ON ayahs_fts.rowid = a.id
             JOIN surahs s ON s.id = a.surah_id
             LEFT JOIN translations t ON t.ayah_id = a.id AND t.language_code = ?
             WHERE ayahs_fts MATCH ?
             ORDER BY rank, a.surah_id, a.ayah_number
             LIMIT ?",
            [$lang, $ftsQuery, $limit]
        );
    } else {
        // Search translation text
        $results = Database::query(
            "SELECT a.*, s.name_en, s.name_ar, s.name_id, s.name_transliteration,
                    t.text as translation_text
             FROM translations t
             JOIN translations_fts ON translations_fts.rowid = t.id
             JOIN ayahs a ON a.id = t.ayah_id
             JOIN surahs s ON s.id = a.surah_id
             WHERE translations_fts MATCH ? AND t.language_code = ?
             ORDER BY rank, a.surah_id, a.ayah_number
             LIMIT ?",
            [$ftsQuery, $lang, $limit]
        );
    }
    return $results;
}

// Search for ayahs containing a word derived from an Arabic root (dynamic
// runtime matching via includes/morphology.php — no root is precomputed
// or stored per word). $rootAr should already be 2-4 raw Arabic letters
// (e.g. typed via the hijaiyah keyboard); it is normalized here.
function search_by_root(string $rootAr, int $limit = 200, ?string $translationLang = null): array {
    $translationLang = $translationLang ?? current_lang();
    $root = ar_normalize($rootAr);
    $letters = preg_split('//u', $root, -1, PREG_SPLIT_NO_EMPTY);
    if (count($letters) < 2) return [];

    // Cheap SQL pre-filter: every root radical must appear somewhere in
    // the word (native LIKE scan) before the real, ordered subsequence
    // check runs in PHP on the much smaller candidate set.
    $where = [];
    $letterParams = [];
    foreach (array_unique($letters) as $ch) {
        $where[] = 'w.text_ar_clean LIKE ?';
        $letterParams[] = '%' . $ch . '%';
    }
    // Params must match SQL placeholder order: the LEFT JOIN's `?`
    // appears before the WHERE clause's, so $translationLang goes first.
    $params = array_merge([$translationLang], $letterParams);
    $candidates = Database::query(
        "SELECT w.ayah_id, w.text_ar AS matched_word, w.text_ar_clean, w.position,
                a.text_ar, a.surah_id, a.ayah_number,
                s.name_ar, s.name_en, s.name_id, s.name_transliteration,
                t.text AS translation_text
         FROM ayah_words w
         JOIN ayahs a ON a.id = w.ayah_id
         JOIN surahs s ON s.id = a.surah_id
         LEFT JOIN translations t ON t.ayah_id = a.id AND t.language_code = ?
         WHERE " . implode(' AND ', $where) . "
         ORDER BY a.surah_id, a.ayah_number
         LIMIT 5000",
        $params
    );

    $results = [];
    foreach ($candidates as $c) {
        if (!ar_word_matches_root($c['text_ar_clean'], $root)) continue;
        $aid = $c['ayah_id'];
        if (!isset($results[$aid])) {
            $results[$aid] = $c;
            $results[$aid]['matched_words'] = [];
        }
        $results[$aid]['matched_words'][] = $c['matched_word'];
        if (count($results) >= $limit) break;
    }
    return array_values($results);
}

// Search for ayahs containing a word whose base form (via a light
// stemmer) matches the base form of $query, for EN/ID content search —
// additive to the existing literal FTS search, not a replacement.
function search_stem_derived(string $query, string $lang, int $limit = 100): array {
    if (!in_array($lang, ['en', 'id'], true)) return [];
    $query = trim($query);
    if (mb_strlen($query, 'UTF-8') < 2) return [];

    $col = $lang === 'en' ? 'w.translation_en' : 'w.translation_id';
    $candidates = Database::query(
        "SELECT w.ayah_id, {$col} AS gloss, a.text_ar, a.surah_id, a.ayah_number,
                s.name_ar, s.name_en, s.name_id, s.name_transliteration
         FROM ayah_words w
         JOIN ayahs a ON a.id = w.ayah_id
         JOIN surahs s ON s.id = a.surah_id
         WHERE {$col} LIKE ?
         ORDER BY a.surah_id, a.ayah_number
         LIMIT 5000",
        ['%' . $query . '%']
    );

    $results = [];
    foreach ($candidates as $c) {
        $words = preg_split('/[^\p{L}\'-]+/u', $c['gloss'] ?? '', -1, PREG_SPLIT_NO_EMPTY);
        $matched = null;
        foreach ($words as $w) {
            if (word_stems_match($w, $query, $lang)) { $matched = $w; break; }
        }
        if ($matched === null) continue;
        $c['translation_text'] = $c['gloss'];
        $aid = $c['ayah_id'];
        if (!isset($results[$aid])) {
            $results[$aid] = $c;
            $results[$aid]['matched_words'] = [];
        }
        $results[$aid]['matched_words'][] = $matched;
        if (count($results) >= $limit) break;
    }
    return array_values($results);
}

// Both translations (id + en) for a set of ayahs, one query.
function translations_for(array $ayahIds): array {
    $ayahIds = array_values(array_unique(array_map('intval', $ayahIds)));
    if (!$ayahIds) return [];
    $ph = implode(',', array_fill(0, count($ayahIds), '?'));
    $rows = Database::query(
        "SELECT ayah_id, language_code, text FROM translations
         WHERE language_code IN ('id','en') AND ayah_id IN ({$ph})",
        $ayahIds
    );
    $map = [];
    foreach ($rows as $r) $map[$r['ayah_id']][$r['language_code']] = $r['text'];
    return $map;
}

/**
 * Contextual highlight sets for one search hit, bridged through the
 * word-by-word data: which Arabic word forms in this ayah match the
 * query (including derived forms, via the same morphology engine as
 * root search), and which gloss terms should light up in EACH
 * translation language — regardless of which language was searched.
 * Returns ['ar'=>[word forms], 'id'=>[terms], 'en'=>[terms]].
 */
function contextual_matches(int $ayahId, string $query, string $lang, bool $exact = false): array {
    $out = ['ar' => [], 'id' => [], 'en' => []];
    $qwords = preg_split('/\s+/u', trim($query), -1, PREG_SPLIT_NO_EMPTY);
    if (!$qwords) return $out;
    $rows = Database::query(
        "SELECT text_ar, text_ar_clean, translation_id, translation_en
         FROM ayah_words WHERE ayah_id = ?",
        [$ayahId]
    );
    if (!$rows) return $out;

    $collect = function (array $r) use (&$out) {
        $out['ar'][] = $r['text_ar'];
        foreach (gloss_terms($r['translation_id'], 'id') as $t) $out['id'][] = $t;
        foreach (gloss_terms($r['translation_en'], 'en') as $t) $out['en'][] = $t;
    };

    if ($lang === 'ar' && $exact) {
        // Word mode: only the precise clean form, never sibling derivations
        $qn = ar_normalize($qwords[0]);
        foreach ($rows as $r) {
            if ($r['text_ar_clean'] === $qn) $collect($r);
        }
        foreach ($out as $k => $v) $out[$k] = array_values(array_unique($v));
        return $out;
    }

    if ($lang === 'ar') {
        $variants = [];
        foreach ($qwords as $q) {
            $qn = ar_normalize($q);
            if (mb_strlen($qn, 'UTF-8') < 2) continue;
            $variants[] = $qn;
            // also try without a definite article so "الرحمن" still
            // matches forms like "للرحمن" whose stripped residual lacks ال
            if (mb_strpos($qn, 'ال') === 0 && mb_strlen($qn, 'UTF-8') > 4) {
                $variants[] = mb_substr($qn, 2, null, 'UTF-8');
            }
        }
        foreach ($rows as $r) {
            foreach ($variants as $qn) {
                if (ar_word_matches_root($r['text_ar_clean'], $qn)) { $collect($r); break; }
            }
        }
    } else {
        $field = $lang === 'en' ? 'translation_en' : 'translation_id';
        foreach ($rows as $r) {
            $terms = preg_split('/[^\p{L}\'-]+/u', $r[$field] ?? '', -1, PREG_SPLIT_NO_EMPTY);
            foreach ($terms as $t) {
                foreach ($qwords as $q) {
                    if (word_stems_match($t, $q, $lang)) { $collect($r); continue 3; }
                }
            }
        }
    }

    foreach ($out as $k => $v) $out[$k] = array_values(array_unique($v));
    return $out;
}

// Search topics
function search_topics(string $query): array {
    $ftsQuery = str_replace(' ', ' OR ', $query);
    return Database::query(
        "SELECT t.* FROM topics t
         JOIN topics_fts ON topics_fts.rowid = t.id
         WHERE topics_fts MATCH ? AND t.is_active = 1
         ORDER BY rank
         LIMIT 50",
        [$ftsQuery]
    );
}

// Get bookmarks
function get_bookmarks(): array {
    return Database::query(
        "SELECT b.*, s.name_en, s.name_ar, s.name_id, a.text_ar
         FROM bookmarks b
         JOIN surahs s ON s.id = b.surah_id
         LEFT JOIN ayahs a ON a.id = b.ayah_id
         ORDER BY b.created_at DESC"
    );
}

// Get reading progress
function get_last_read(): ?array {
    return Database::queryOne(
        "SELECT r.*, s.name_en, s.name_ar, s.name_id FROM reading_progress r
         JOIN surahs s ON s.id = r.surah_id
         ORDER BY r.updated_at DESC"
    );
}

// Update reading progress
function update_progress(int $surah_id, int $ayah_number): void {
    Database::exec(
        "INSERT INTO reading_progress (surah_id, ayah_number, updated_at)
         VALUES (?, ?, datetime('now'))
         ON CONFLICT(surah_id) DO UPDATE SET
            ayah_number = excluded.ayah_number,
            updated_at = excluded.updated_at",
        [$surah_id, $ayah_number]
    );
}

// Build audio URL from CDN
function audio_url(int $surah_number, int $ayah_number, string $base_url): string {
    return rtrim($base_url, '/') . '/' . sprintf('%03d%03d.mp3', $surah_number, $ayah_number);
}

// Number to Arabic (for surah numbers)
function to_arabic_number(int $num): string {
    $arabic = ['0'=>'٠','1'=>'١','2'=>'٢','3'=>'٣','4'=>'٤','5'=>'٥','6'=>'٦','7'=>'٧','8'=>'٨','9'=>'٩'];
    return strtr((string)$num, $arabic);
}
