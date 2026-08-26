<?php
/**
 * FACTA — Database Installer
 * Run this file once to set up the SQLite database
 */

define('ROOT', __DIR__);
set_time_limit(300); // full Quran seed (6236 ayat + 12472 terjemahan) butuh waktu
$DB_FILE = ROOT . '/data/smartquran.db';
$SCHEMA_FILE = ROOT . '/data/schema.sql';
$SEED_FILE = ROOT . '/data/seed.sql';
$SEED_QURAN_FILE = ROOT . '/data/seed_quran_full.sql';
$SEED_TOPICS_FILE = ROOT . '/data/seed_topics.sql';
$SEED_WORDS_FILE = ROOT . '/data/seed_words_full.sql';   // optional (word-by-word view + root search)
$SEED_ROOTS_FILE = ROOT . '/data/seed_roots.sql';         // optional (root dictionary)
$SEED_ROOT_REL_FILE = ROOT . '/data/seed_root_relations.sql'; // optional (root synonym/antonym relations)
$SEED_WORD_ROOTS_FILE = ROOT . '/data/seed_word_roots.sql';   // optional (imported root<->ayah map, ~1700 roots)
$SEED_ROOTS_AI_FILE = ROOT . '/data/seed_roots_ai_curated.sql'; // optional (AI-curated meanings, data/words-kurator-by-ai/)

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>FACTA — Instalasi Database</title>
<style>
:root{--bg:#0f172a;--card:#1e293b;--text:#e2e8f0;--muted:#94a3b8;--primary:#6366f1;--good:#10b981;--bad:#ef4444}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font:15px/1.6 system-ui,sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.install-card{background:var(--card);border:1px solid #334155;border-radius:16px;padding:32px;max-width:560px;width:100%;box-shadow:0 25px 50px -12px #00000040}
h1{margin:0 0 8px;font-size:24px}p{margin:0 0 20px;color:var(--muted)}
.step{padding:14px 16px;border-radius:10px;margin:0 0 10px;border:1px solid #334155;background:#0f172a;display:flex;align-items:center;gap:12px}
.step.done{border-color:var(--good);background:rgba(16,185,129,.08)}
.step.fail{border-color:var(--bad);background:rgba(239,68,68,.08)}
.step.skip{opacity:.6}
.icon{width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700}
.done .icon{background:var(--good);color:#fff}.fail .icon{background:var(--bad);color:#fff}.wait .icon,.skip .icon{background:#475569;color:#fff}
.step-text{flex:1}.step-title{font-weight:600}.step-desc{font-size:13px;color:var(--muted)}
.btn{display:inline-block;padding:10px 18px;border-radius:8px;background:var(--primary);color:#fff;text-decoration:none;font-weight:600;margin-top:10px}
pre{background:#0f172a;border:1px solid #334155;border-radius:8px;padding:12px;font:12px/1.5 monospace;overflow:auto;max-height:200px;color:#cbd5e1}
</style>
</head>
<body>
<div class="install-card">
<h1>🔧 FACTA Installer</h1>
<p>Setup database SQLite untuk aplikasi FACTA.</p>
<?php
$steps = [];
$allOk = true;

// Check SQLite3 extension
if (!extension_loaded('sqlite3')) {
    $steps[] = ['title'=>'SQLite3 Extension', 'status'=>'fail', 'desc'=>'Extension SQLite3 tidak tersedia di PHP. Aktifkan di php.ini.'];
    $allOk = false;
} else {
    $steps[] = ['title'=>'SQLite3 Extension', 'status'=>'done', 'desc'=>'Tersedia ✓'];
}

// Check PDO SQLite
if (!extension_loaded('pdo_sqlite')) {
    $steps[] = ['title'=>'PDO SQLite', 'status'=>'fail', 'desc'=>'Extension pdo_sqlite tidak tersedia. Aktifkan di php.ini.'];
    $allOk = false;
} else {
    $steps[] = ['title'=>'PDO SQLite', 'status'=>'done', 'desc'=>'Tersedia ✓'];
}

// Check writable data dir
if (!is_writable(dirname($DB_FILE))) {
    $steps[] = ['title'=>'Writable data/ directory', 'status'=>'fail', 'desc'=>'Folder data/ tidak bisa ditulis. Set permission 777 atau pastikan web server punya write access.'];
    $allOk = false;
} else {
    $steps[] = ['title'=>'Writable data/ directory', 'status'=>'done', 'desc'=>'Bisa ditulis ✓'];
}

// Check schema file exists
if (!file_exists($SCHEMA_FILE)) {
    $steps[] = ['title'=>'Schema file', 'status'=>'fail', 'desc'=>'File data/schema.sql tidak ditemukan.'];
    $allOk = false;
} else {
    $steps[] = ['title'=>'Schema file', 'status'=>'done', 'desc'=>'Ditemukan ✓'];
}

// Check seed file exists
if (!file_exists($SEED_FILE)) {
    $steps[] = ['title'=>'Seed file', 'status'=>'fail', 'desc'=>'File data/seed.sql tidak ditemukan.'];
    $allOk = false;
} else {
    $steps[] = ['title'=>'Seed file', 'status'=>'done', 'desc'=>'Ditemukan ✓'];
}

// Check full Quran seed exists
if (!file_exists($SEED_QURAN_FILE)) {
    $steps[] = ['title'=>'Full Quran seed', 'status'=>'fail', 'desc'=>'File data/seed_quran_full.sql tidak ditemukan. Generate dengan: python data/build_seed.py'];
    $allOk = false;
} else {
    $sizeMb = round(filesize($SEED_QURAN_FILE) / 1048576, 1);
    $steps[] = ['title'=>'Full Quran seed', 'status'=>'done', 'desc'=>"Ditemukan ({$sizeMb} MB) ✓"];
}

// Check topics seed exists
if (!file_exists($SEED_TOPICS_FILE)) {
    $steps[] = ['title'=>'Topics seed', 'status'=>'fail', 'desc'=>'File data/seed_topics.sql tidak ditemukan.'];
    $allOk = false;
} else {
    $steps[] = ['title'=>'Topics seed', 'status'=>'done', 'desc'=>'Ditemukan ✓'];
}

// Word-by-word seed & root dictionary are OPTIONAL (large, separately
// generated datasets) — missing them skips the feature, not the install.
if (file_exists($SEED_WORDS_FILE)) {
    $sizeMb = round(filesize($SEED_WORDS_FILE) / 1048576, 1);
    $steps[] = ['title'=>'Word-by-word seed', 'status'=>'done', 'desc'=>"Ditemukan ({$sizeMb} MB) ✓"];
} else {
    $steps[] = ['title'=>'Word-by-word seed', 'status'=>'skip', 'desc'=>'Tidak ditemukan (opsional). Generate dengan: python data/build_seed_words.py'];
}
if (file_exists($SEED_ROOTS_FILE)) {
    $steps[] = ['title'=>'Root dictionary seed', 'status'=>'done', 'desc'=>'Ditemukan ✓'];
} else {
    $steps[] = ['title'=>'Root dictionary seed', 'status'=>'skip', 'desc'=>'Tidak ditemukan (opsional).'];
}
if (file_exists($SEED_WORD_ROOTS_FILE)) {
    $sizeMb = round(filesize($SEED_WORD_ROOTS_FILE) / 1048576, 1);
    $steps[] = ['title'=>'Imported root-ayah map', 'status'=>'done', 'desc'=>"Ditemukan ({$sizeMb} MB) ✓"];
} else {
    $steps[] = ['title'=>'Imported root-ayah map', 'status'=>'skip', 'desc'=>'Tidak ditemukan (opsional). Generate dengan: python data/build_word_roots_import.py'];
}
if (file_exists($SEED_ROOTS_AI_FILE)) {
    $steps[] = ['title'=>'AI-curated root meanings', 'status'=>'done', 'desc'=>'Ditemukan ✓'];
} else {
    $steps[] = ['title'=>'AI-curated root meanings', 'status'=>'skip', 'desc'=>'Tidak ditemukan (opsional). Kurasi via data/words-kurator-by-ai/'];
}

// Run installation
if ($allOk && isset($_GET['run'])) {
    try {
        // Remove old DB if exists (for re-install)
        if (file_exists($DB_FILE)) {
            unlink($DB_FILE);
        }

        $pdo = new PDO('sqlite:' . $DB_FILE);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA foreign_keys = ON;');

        // Execute schema
        $schema = file_get_contents($SCHEMA_FILE);
        $pdo->exec($schema);
        $steps[] = ['title'=>'Create database schema', 'status'=>'done', 'desc'=>'Berhasil dibuat ✓'];

        // Execute core seed (languages, audio, settings)
        $seed = file_get_contents($SEED_FILE);
        $pdo->exec($seed);
        $steps[] = ['title'=>'Insert core data', 'status'=>'done', 'desc'=>'Bahasa, audio & pengaturan dimasukkan ✓'];

        // Execute full Quran seed (114 surahs, 6236 ayahs, translations)
        $seedQuran = file_get_contents($SEED_QURAN_FILE);
        $pdo->exec($seedQuran);
        $steps[] = ['title'=>'Insert full Quran', 'status'=>'done', 'desc'=>'114 surat, 6236 ayat + terjemahan ID & EN dimasukkan ✓'];

        // Execute topics seed (topics, root words, tafsir)
        $seedTopics = file_get_contents($SEED_TOPICS_FILE);
        $pdo->exec($seedTopics);
        $steps[] = ['title'=>'Insert topics & roots', 'status'=>'done', 'desc'=>'Topik semantik, root words & tafsir dimasukkan ✓'];

        // Execute root dictionary seed (optional)
        if (file_exists($SEED_ROOTS_FILE)) {
            $pdo->exec(file_get_contents($SEED_ROOTS_FILE));
            $steps[] = ['title'=>'Insert root dictionary', 'status'=>'done', 'desc'=>'~150 root kata Quran dimasukkan ✓'];
        }

        // Execute root relations seed (optional; needs root_words already seeded)
        if (file_exists($SEED_ROOT_REL_FILE)) {
            $pdo->exec(file_get_contents($SEED_ROOT_REL_FILE));
            $steps[] = ['title'=>'Insert root relations', 'status'=>'done', 'desc'=>'Relasi sinonim/antonim antar root dimasukkan ✓'];
        }

        // Execute word-by-word seed (optional; FK depends on ayahs already existing)
        if (file_exists($SEED_WORDS_FILE)) {
            $pdo->exec(file_get_contents($SEED_WORDS_FILE));
            $steps[] = ['title'=>'Insert word-by-word data', 'status'=>'done', 'desc'=>'Terjemahan per-kata AR/EN/ID dimasukkan ✓'];
        }

        // Execute imported root<->ayah map (optional; needs root_words + ayahs)
        if (file_exists($SEED_WORD_ROOTS_FILE)) {
            $pdo->exec(file_get_contents($SEED_WORD_ROOTS_FILE));
            $rootCount = (int)$pdo->query("SELECT COUNT(*) FROM root_words WHERE source='imported'")->fetchColumn();
            $steps[] = ['title'=>'Insert imported root-ayah map', 'status'=>'done', 'desc'=>"{$rootCount} root hasil impor + kemunculannya dimasukkan ✓"];
        }

        // Execute AI-curated meanings for imported roots (optional; from data/words-kurator-by-ai/)
        if (file_exists($SEED_ROOTS_AI_FILE)) {
            $pdo->exec(file_get_contents($SEED_ROOTS_AI_FILE));
            $curatedCount = (int)$pdo->query("SELECT COUNT(*) FROM root_words WHERE source='imported' AND meaning_id IS NOT NULL AND meaning_id != ''")->fetchColumn();
            $steps[] = ['title'=>'Insert AI-curated root meanings', 'status'=>'done', 'desc'=>"{$curatedCount} root hasil impor sudah punya arti ✓"];
        }

        // Verify counts
        $expected = ['surahs'=>114, 'ayahs'=>6236, 'translations'=>12472];
        $counts = [];
        $verifyOk = true;
        foreach (['surahs','ayahs','translations','topics','root_words','root_relations','settings','ayah_words'] as $t) {
            $count = (int)$pdo->query("SELECT COUNT(*) FROM {$t}")->fetchColumn();
            $counts[] = "{$t}: {$count}";
            if (isset($expected[$t]) && $count !== $expected[$t]) $verifyOk = false;
        }
        // ayah_words total word count isn't a fixed known invariant (unlike
        // ayahs/surahs) — only range-check it, and only when it was seeded.
        $wordCount = (int)$pdo->query("SELECT COUNT(*) FROM ayah_words")->fetchColumn();
        if ($wordCount > 0 && ($wordCount < 70000 || $wordCount > 85000)) $verifyOk = false;
        $steps[] = [
            'title'=>'Verification',
            'status'=>$verifyOk ? 'done' : 'fail',
            'desc'=>($verifyOk ? 'Lengkap ✓ — ' : 'Jumlah tidak sesuai! — ') . implode(', ', $counts)
        ];

        echo '<div style="margin:16px 0;padding:12px 16px;background:rgba(16,185,129,.12);border:1px solid var(--good);border-radius:10px;color:#34d399;font-weight:600">✅ Instalasi berhasil! FACTA siap digunakan.</div>';
        echo '<a href="index.php" class="btn">🚀 Buka FACTA</a>';

    } catch (Exception $e) {
        $steps[] = ['title'=>'Installation Error', 'status'=>'fail', 'desc'=>$e->getMessage()];
        echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
    }
}

// Render steps
foreach ($steps as $s) {
    $icon = $s['status'] === 'done' ? '✓' : ($s['status'] === 'fail' ? '✕' : '◯');
    echo "<div class=\"step {$s['status']}\"><div class=\"icon\">{$icon}</div><div class=\"step-text\"><div class=\"step-title\">{$s['title']}</div><div class=\"step-desc\">{$s['desc']}</div></div></div>";
}

if ($allOk && !isset($_GET['run'])) {
    echo '<a href="?run=1" class="btn">▶️ Jalankan Instalasi</a>';
} elseif (!$allOk) {
    echo '<div style="margin-top:16px;color:var(--bad);font-weight:600">❌ Mohon perbaiki error di atas sebelum melanjutkan.</div>';
}
?>
</div>
</body>
</html>
