<?php

namespace Selfauth;

class Database
{
    private static ?\PDO $pdo = null;

    public static function connect(string $path): \PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0770, true);
        }

        $pdo = new \PDO('sqlite:' . $path);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA foreign_keys = ON');

        self::$pdo = $pdo;
        self::migrate($pdo);

        return $pdo;
    }

    private static function migrate(\PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS settings (
            key TEXT PRIMARY KEY,
            value TEXT NOT NULL
        )');

        $pdo->exec('CREATE TABLE IF NOT EXISTS signins (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            occurred_at TEXT NOT NULL,
            client_id TEXT,
            redirect_uri TEXT,
            scope TEXT,
            ip TEXT,
            user_agent TEXT,
            success INTEGER NOT NULL DEFAULT 0
        )');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_signins_occurred_at ON signins(occurred_at)');

        $pdo->exec("CREATE TABLE IF NOT EXISTS blocklist (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            type TEXT NOT NULL CHECK(type IN ('client_id','redirect_uri','ip')),
            pattern TEXT NOT NULL,
            note TEXT,
            created_at TEXT NOT NULL,
            UNIQUE(type, pattern)
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS webmentions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            source TEXT NOT NULL,
            target TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','verified','failed','spam','deleted')),
            mention_type TEXT,
            author_name TEXT,
            author_photo TEXT,
            author_url TEXT,
            title TEXT,
            content TEXT,
            published_at TEXT,
            fetched_at TEXT,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            UNIQUE(source, target)
        )");
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_webmentions_target ON webmentions(target)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_webmentions_status ON webmentions(status)');
    }

    public static function pdo(): \PDO
    {
        if (self::$pdo === null) {
            throw new \RuntimeException('Database not connected yet.');
        }
        return self::$pdo;
    }
}
