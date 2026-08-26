<?php
require_once __DIR__ . '/includes/functions.php';

// Create word_form_patterns table if missing
$ddl = "CREATE TABLE IF NOT EXISTS word_form_patterns (
    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
    ayah_root_word_id   INTEGER NOT NULL,
    pattern_id          INTEGER NOT NULL,
    confidence          REAL DEFAULT 0.8,
    verified            INTEGER DEFAULT 0,
    verified_by         TEXT,
    verified_at         TEXT,
    ai_reasoning        TEXT,
    created_at          TEXT DEFAULT (datetime('now')),
    FOREIGN KEY (ayah_root_word_id) REFERENCES ayah_root_words(id) ON DELETE CASCADE,
    FOREIGN KEY (pattern_id) REFERENCES sarf_patterns(id) ON DELETE CASCADE,
    UNIQUE(ayah_root_word_id, pattern_id)
)";
try {
    Database::query($ddl);
    echo "Table word_form_patterns created OK\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// Verify
$c = Database::query("SELECT COUNT(*) c FROM word_form_patterns")[0]['c'];
echo "Current rows in word_form_patterns: {$c}\n";
