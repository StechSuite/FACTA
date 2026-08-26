<?php
/**
 * FACTA — Database Connection Helper
 */

require_once __DIR__ . '/config.php';

class Database {
    private static ?PDO $instance = null;

    public static function get(): PDO {
        global $DB_PATH;
        if (self::$instance === null) {
            if (!file_exists($DB_PATH)) {
                throw new Exception('Database not found. Please run install.php first.');
            }
            self::$instance = new PDO('sqlite:' . $DB_PATH);
            self::$instance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$instance->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            self::$instance->exec("PRAGMA foreign_keys = ON;");
        }
        return self::$instance;
    }

    public static function close(): void {
        self::$instance = null;
    }

    public static function exec(string $sql, array $params = []): int {
        $stmt = self::get()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public static function query(string $sql, array $params = []): array {
        $stmt = self::get()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function queryOne(string $sql, array $params = []): ?array {
        $results = self::query($sql . ' LIMIT 1', $params);
        return $results[0] ?? null;
    }

    public static function lastInsertId(): string {
        return self::get()->lastInsertId();
    }
}
