<?php

namespace Selfauth;

/**
 * Database-backed, single-use, short-lived authorization codes bound to the
 * client, redirect_uri and PKCE challenge. Only a SHA-256 of the code is stored.
 */
class AuthCodes
{
    public const TTL = 600; // spec maximum is 10 minutes

    public static function ensureSchema(\PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS auth_codes (
            code_hash TEXT PRIMARY KEY,
            client_id TEXT NOT NULL,
            redirect_uri TEXT NOT NULL,
            scope TEXT NOT NULL DEFAULT "",
            code_challenge TEXT,
            me TEXT NOT NULL,
            expires_at INTEGER NOT NULL,
            used_at INTEGER
        )');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_auth_codes_exp ON auth_codes(expires_at)');
    }

    public static function issue(\PDO $pdo, string $clientId, string $redirectUri, string $scope, ?string $challenge, string $me): string
    {
        self::ensureSchema($pdo);
        $pdo->prepare('DELETE FROM auth_codes WHERE expires_at < :t')->execute(['t' => time() - 3600]);
        $code = bin2hex(random_bytes(32));
        $pdo->prepare('INSERT INTO auth_codes (code_hash, client_id, redirect_uri, scope, code_challenge, me, expires_at)
                       VALUES (:h,:c,:r,:s,:ch,:me,:e)')->execute([
            'h' => hash('sha256', $code), 'c' => $clientId, 'r' => $redirectUri,
            's' => $scope, 'ch' => $challenge, 'me' => $me, 'e' => time() + self::TTL,
        ]);
        return $code;
    }

    /**
     * Atomically consumes the code. Returns the row, or an error string:
     * 'invalid_grant' for unknown/expired/used/mismatched.
     * A replayed code also invalidates nothing else but is reported.
     *
     * @return array|string
     */
    public static function redeem(\PDO $pdo, string $code, string $clientId, string $redirectUri, ?string $verifier)
    {
        self::ensureSchema($pdo);
        $hash = hash('sha256', $code);
        $pdo->exec('BEGIN IMMEDIATE');
        try {
            $st = $pdo->prepare('SELECT * FROM auth_codes WHERE code_hash = :h');
            $st->execute(['h' => $hash]);
            $row = $st->fetch(\PDO::FETCH_ASSOC);
            if (!$row || $row['used_at'] !== null || $row['expires_at'] < time()) {
                $pdo->exec('COMMIT');
                return 'invalid_grant';
            }
            // Burn it regardless of whether the remaining checks pass.
            $pdo->prepare('UPDATE auth_codes SET used_at = :t WHERE code_hash = :h')->execute(['t' => time(), 'h' => $hash]);
            $pdo->exec('COMMIT');
        } catch (\Throwable $e) {
            $pdo->exec('ROLLBACK');
            throw $e;
        }
        if (!hash_equals($row['client_id'], $clientId) || !hash_equals($row['redirect_uri'], $redirectUri)) {
            return 'invalid_grant';
        }
        if ($row['code_challenge'] !== null) {
            if ($verifier === null || !preg_match('/^[A-Za-z0-9\-._~]{43,128}$/', $verifier)) {
                return 'invalid_grant';
            }
            $calc = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
            if (!hash_equals($row['code_challenge'], $calc)) {
                return 'invalid_grant';
            }
        }
        return $row;
    }
}
