<?php

namespace Selfauth;

/**
 * Settings are stored in the database so they survive container restarts and
 * can be changed from the admin portal (e.g. password changes) without
 * having to edit environment variables and redeploy. On first boot they are
 * seeded from environment variables.
 */
class Settings
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $stmt = $this->pdo->prepare('SELECT value FROM settings WHERE key = ?');
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        return $value === false ? $default : $value;
    }

    public function set(string $key, string $value): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO settings (key, value) VALUES (:key, :value)
             ON CONFLICT(key) DO UPDATE SET value = excluded.value'
        );
        $stmt->execute(['key' => $key, 'value' => $value]);
    }

    public function has(string $key): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM settings WHERE key = ?');
        $stmt->execute([$key]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Seed a setting from an environment variable, but only if it isn't
     * already present in the database. The database always wins after the
     * first boot so admin-portal changes (like a new password) persist.
     */
    public function seedFromEnv(string $key, ?string $envValue): void
    {
        if ($envValue !== null && $envValue !== '' && !$this->has($key)) {
            $this->set($key, $envValue);
        }
    }
}
