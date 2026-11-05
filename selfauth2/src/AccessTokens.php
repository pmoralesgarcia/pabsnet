<?php

namespace Selfauth;

/**
 * IndieAuth access tokens issued by public/token.php. The token is a 256-bit
 * random secret and only its SHA-256 is stored, so a leaked database cannot be
 * replayed. Separate from ApiToken, which covers the admin scripting API.
 */
class AccessTokens
{
    public const DEFAULT_TTL = 7776000; // 90 days

    public static function ensureSchema(\PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS access_tokens (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            token_hash TEXT NOT NULL UNIQUE,
            client_id TEXT NOT NULL,
            scope TEXT NOT NULL DEFAULT "",
            me TEXT NOT NULL,
            created_at INTEGER NOT NULL,
            expires_at INTEGER NOT NULL,
            last_used_at INTEGER,
            revoked_at INTEGER
        )');
    }

    public static function ttl(): int
    {
        $ttl = (int) getenv('SELFAUTH_TOKEN_TTL');
        return $ttl > 0 ? $ttl : self::DEFAULT_TTL;
    }

    /** @return array{token:string, expires_in:int} The plaintext is only available here. */
    public static function issue(\PDO $pdo, string $clientId, string $scope, string $me, ?int $ttl = null): array
    {
        self::ensureSchema($pdo);
        $ttl = $ttl ?? self::ttl();
        $token = 'sfat_' . bin2hex(random_bytes(32));
        $now = time();
        $pdo->prepare('INSERT INTO access_tokens (token_hash, client_id, scope, me, created_at, expires_at)
                       VALUES (:h, :c, :s, :m, :n, :e)')->execute([
            'h' => hash('sha256', $token), 'c' => $clientId, 's' => $scope,
            'm' => $me, 'n' => $now, 'e' => $now + $ttl,
        ]);
        return ['token' => $token, 'expires_in' => $ttl];
    }

    /** Returns the row for an active token, or null if unknown, expired or revoked. */
    public static function find(\PDO $pdo, string $token): ?array
    {
        self::ensureSchema($pdo);
        $st = $pdo->prepare('SELECT * FROM access_tokens WHERE token_hash = :h');
        $st->execute(['h' => hash('sha256', $token)]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$row || $row['revoked_at'] !== null || (int) $row['expires_at'] <= time()) {
            return null;
        }
        return $row;
    }

    /** Like find(), but records the use. For a token presented as a credential. */
    public static function authenticate(\PDO $pdo, string $token): ?array
    {
        $row = self::find($pdo, $token);
        if ($row !== null) {
            $pdo->prepare('UPDATE access_tokens SET last_used_at = :t WHERE id = :i')
                ->execute(['t' => time(), 'i' => $row['id']]);
        }
        return $row;
    }

    public static function revoke(\PDO $pdo, string $token): void
    {
        self::ensureSchema($pdo);
        $pdo->prepare('UPDATE access_tokens SET revoked_at = :t WHERE token_hash = :h AND revoked_at IS NULL')
            ->execute(['t' => time(), 'h' => hash('sha256', $token)]);
    }

    public static function revokeById(\PDO $pdo, int $id): void
    {
        self::ensureSchema($pdo);
        $pdo->prepare('UPDATE access_tokens SET revoked_at = :t WHERE id = :i AND revoked_at IS NULL')
            ->execute(['t' => time(), 'i' => $id]);
    }

    /** @return array<int, array<string, mixed>> */
    public static function all(\PDO $pdo): array
    {
        self::ensureSchema($pdo);
        return $pdo->query('SELECT id, client_id, scope, me, created_at, expires_at, last_used_at, revoked_at
                            FROM access_tokens ORDER BY created_at DESC, id DESC')->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** The bearer token from the Authorization header, or null. */
    public static function bearer(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if ($header === '' && function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            $header = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        }
        return preg_match('/^Bearer\s+(\S+)$/i', $header, $m) ? $m[1] : null;
    }

    /** Spec response fields for an active token row. */
    public static function introspection(array $row): array
    {
        return [
            'active' => true,
            'me' => $row['me'],
            'client_id' => $row['client_id'],
            'scope' => $row['scope'],
            'exp' => (int) $row['expires_at'],
            'iat' => (int) $row['created_at'],
        ];
    }
}
