<?php

namespace Selfauth;

class ApiToken
{
    public const SCOPES = [
        'signins:read',
        'blocklist:read',
        'blocklist:write',
        'mentions:read',
        'mentions:write',
    ];

    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /** @return array{id:int, token:string} The plaintext token is only ever available here, at creation time. */
    public function create(string $label, array $scopes, ?int $expiresInDays = null): array
    {
        $scopes = array_values(array_intersect($scopes, self::SCOPES));
        if ($scopes === []) {
            throw new \InvalidArgumentException('Select at least one scope');
        }

        $token = 'sfa_' . bin2hex(random_bytes(24));
        $hash = hash('sha256', $token);
        $expiresAt = $expiresInDays ? gmdate('c', time() + $expiresInDays * 86400) : null;

        $stmt = $this->pdo->prepare(
            'INSERT INTO api_tokens (label, token_hash, scopes, created_at, expires_at)
             VALUES (:label, :hash, :scopes, :created_at, :expires_at)'
        );
        $stmt->execute([
            'label' => $label,
            'hash' => $hash,
            'scopes' => implode(',', $scopes),
            'created_at' => gmdate('c'),
            'expires_at' => $expiresAt,
        ]);

        return ['id' => (int) $this->pdo->lastInsertId(), 'token' => $token];
    }

    public function revoke(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE api_tokens SET revoked_at = ? WHERE id = ?');
        $stmt->execute([gmdate('c'), $id]);
    }

    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        $stmt = $this->pdo->query('SELECT id, label, scopes, created_at, last_used_at, expires_at, revoked_at FROM api_tokens ORDER BY created_at DESC');
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Validate a bearer token and required scope. Returns the token row
     * on success (and bumps last_used_at), or null if invalid/expired/
     * revoked/missing the scope. Uses a SHA-256 lookup (not bcrypt/argon2)
     * since the token itself is a 192-bit random secret, not a
     * human-guessable password -- a fast hash is fine and lets us look it
     * up by exact match instead of scanning every row.
     */
    public function authenticate(string $token, string $requiredScope): ?array
    {
        $hash = hash('sha256', $token);
        $stmt = $this->pdo->prepare('SELECT * FROM api_tokens WHERE token_hash = ?');
        $stmt->execute([$hash]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        if ($row['revoked_at'] !== null) {
            return null;
        }
        if ($row['expires_at'] !== null && strtotime($row['expires_at']) < time()) {
            return null;
        }
        $scopes = explode(',', $row['scopes']);
        if (!in_array($requiredScope, $scopes, true)) {
            return null;
        }

        $update = $this->pdo->prepare('UPDATE api_tokens SET last_used_at = ? WHERE id = ?');
        $update->execute([gmdate('c'), $row['id']]);

        return $row;
    }
}
