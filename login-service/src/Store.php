<?php

namespace Login;

/** Registered sites and short-lived sign-in codes, in one SQLite file. */
class Store
{
    public const CODE_TTL = 60;

    private \PDO $pdo;

    public function __construct(string $path)
    {
        $this->pdo = new \PDO('sqlite:' . $path);
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('PRAGMA busy_timeout = 3000');
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS clients (
            client_id TEXT PRIMARY KEY, owner_me TEXT NOT NULL, created_at INTEGER NOT NULL
        )');
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS codes (
            code_hash TEXT PRIMARY KEY, client_id TEXT NOT NULL, redirect_uri TEXT NOT NULL, code_challenge TEXT NOT NULL,
            me TEXT NOT NULL, profile TEXT, expires_at INTEGER NOT NULL, used_at INTEGER
        )');
    }

    public static function open(): self
    {
        return new self(getenv('LOGIN_DB_PATH') ?: sys_get_temp_dir() . '/login.sqlite');
    }

    /** Whether a site may use the service: listed in LOGIN_CLIENTS, or registered by its owner. */
    public function isRegistered(string $clientId): bool
    {
        foreach (preg_split('/[\s,]+/', (string) getenv('LOGIN_CLIENTS'), -1, PREG_SPLIT_NO_EMPTY) as $seed) {
            if (Urls::client($seed) === $clientId) {
                return true;
            }
        }
        $st = $this->pdo->prepare('SELECT 1 FROM clients WHERE client_id = ?');
        $st->execute([$clientId]);
        return (bool) $st->fetchColumn();
    }

    /** Only someone signed in with a website on the same host can register a site. */
    public function register(string $input, string $me): string
    {
        $clientId = Urls::client($input);
        if ($clientId === null) {
            throw new ClientError('Enter your site\'s full address, starting with https://');
        }
        if (Urls::host($clientId) !== Urls::host($me)) {
            throw new ClientError('You signed in as ' . Urls::host($me) . ', so you can only register sites on that domain.');
        }
        $this->pdo->prepare('INSERT OR IGNORE INTO clients (client_id, owner_me, created_at) VALUES (?, ?, ?)')->execute([$clientId, $me, time()]);
        return $clientId;
    }

    /** @return string[] */
    public function clientsOf(string $me): array
    {
        $st = $this->pdo->prepare('SELECT client_id FROM clients WHERE owner_me = ? ORDER BY client_id');
        $st->execute([$me]);
        return $st->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function unregister(string $clientId, string $me): void
    {
        $this->pdo->prepare('DELETE FROM clients WHERE client_id = ? AND owner_me = ?')->execute([$clientId, $me]);
    }

    public function issueCode(array $request, string $me, ?array $profile): string
    {
        $this->pdo->prepare('DELETE FROM codes WHERE expires_at < ?')->execute([time() - 3600]);
        $code = bin2hex(random_bytes(32));
        $this->pdo->prepare('INSERT INTO codes (code_hash, client_id, redirect_uri, code_challenge, me, profile, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?)')->execute([
            hash('sha256', $code), $request['client_id'], $request['redirect_uri'], $request['code_challenge'],
            $me, $profile === null ? null : json_encode($profile), time() + self::CODE_TTL,
        ]);
        return $code;
    }

    /**
     * Burns the code first, so a wrong guess at the verifier also kills it.
     * @return array{me:string, profile:?array}|null
     */
    public function redeem(string $code, string $clientId, string $redirectUri, string $verifier): ?array
    {
        $hash = hash('sha256', $code);
        $burn = $this->pdo->prepare('UPDATE codes SET used_at = ? WHERE code_hash = ? AND used_at IS NULL AND expires_at >= ?');
        $burn->execute([time(), $hash, time()]);
        if ($burn->rowCount() !== 1) {
            return null;
        }
        $st = $this->pdo->prepare('SELECT * FROM codes WHERE code_hash = ?');
        $st->execute([$hash]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        if (!$row || !hash_equals($row['client_id'], $clientId) || !hash_equals($row['redirect_uri'], $redirectUri)
            || !preg_match('/^[A-Za-z0-9\-._~]{43,128}$/', $verifier) || !hash_equals($row['code_challenge'], $challenge)) {
            return null;
        }
        return ['me' => $row['me'], 'profile' => $row['profile'] === null ? null : json_decode($row['profile'], true)];
    }
}
