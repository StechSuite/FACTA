<?php
/**
 * FACTA — Schema Migration Runner
 * Run this once to apply new schema changes to existing database.
 */
require 'includes/functions.php';

$dbPath = __DIR__ . '/data/smartquran.db';
$schemaPath = __DIR__ . '/data/schema.sql';

if (!file_exists($dbPath)) {
    die("Database not found: $dbPath\nRun install.php first.\n");
}
if (!file_exists($schemaPath)) {
    die("Schema file not found: $schemaPath\n");
}

$sql = file_get_contents($schemaPath);

// Split by statements (naive but works for SQLite schema)
$lines = explode("\n", $sql);
$batch = "";
$inTrigger = false;

foreach ($lines as $line) {
    $trim = trim($line);
    if ($trim === '' || str_starts_with($trim, '--')) continue;
    $batch .= $line . "\n";
    if (str_ends_with($trim, ';')) {
        try {
            Database::exec($batch);
            echo "OK: " . substr(str_replace("\n", " ", $batch), 0, 60) . "...\n";
        } catch (PDOException $e) {
            // Ignore duplicate table/column errors
            $msg = $e->getMessage();
            if (str_contains($msg, 'already exists') || str_contains($msg, 'duplicate column')) {
                echo "SKIP (already exists): " . substr(str_replace("\n", " ", $batch), 0, 60) . "\n";
            } else {
                echo "ERR: " . $msg . " | " . substr($batch, 0, 80) . "\n";
            }
        }
        $batch = "";
    }
}

// Verify
$tables = Database::query("SELECT name FROM sqlite_master WHERE type='table' AND name IN ('roles','users','user_roles','user_logs','user_history','user_bookmarks')");
echo "\nAuth tables created: " . count($tables) . "/6\n";

$roles = Database::query('SELECT * FROM roles');
echo "Roles seeded: " . json_encode($roles) . "\n";

$users = Database::query('SELECT id, email, name FROM users');
echo "Users: " . json_encode($users) . "\n";

echo "\nMigration complete.\n";
