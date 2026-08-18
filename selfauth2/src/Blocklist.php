<?php

namespace Selfauth;

class Blocklist
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function add(string $type, string $pattern, ?string $note = null): void
    {
        if (!in_array($type, ['client_id', 'redirect_uri', 'ip'], true)) {
            throw new \InvalidArgumentException('Invalid blocklist type');
        }
        $stmt = $this->pdo->prepare(
            'INSERT OR IGNORE INTO blocklist (type, pattern, note, created_at) VALUES (:type, :pattern, :note, :created_at)'
        );
        $stmt->execute([
            'type' => $type,
            'pattern' => trim($pattern),
            'note' => $note,
            'created_at' => gmdate('c'),
        ]);
    }

    public function remove(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM blocklist WHERE id = ?');
        $stmt->execute([$id]);
    }

    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM blocklist ORDER BY created_at DESC');
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * A pattern matches if it is an exact match of the host, or a
     * "*.example.com" style wildcard suffix match. Patterns are matched
     * against the hostname portion of URLs, not the whole URL, so
     * blocking "evil.example" blocks every path/scheme on that host.
     */
    private function hostMatches(string $host, string $pattern): bool
    {
        $host = strtolower($host);
        $pattern = strtolower(trim($pattern));

        if ($pattern === '') {
            return false;
        }
        if (str_starts_with($pattern, '*.')) {
            $suffix = substr($pattern, 1); // keep the leading dot
            return $host === substr($pattern, 2) || str_ends_with($host, $suffix);
        }
        return $host === $pattern;
    }

    public function isClientBlocked(string $clientId): bool
    {
        $host = parse_url($clientId, PHP_URL_HOST);
        if ($host === null || $host === false) {
            return false;
        }
        foreach ($this->patternsOfType('client_id') as $pattern) {
            if ($this->hostMatches($host, $pattern)) {
                return true;
            }
        }
        return false;
    }

    public function isRedirectBlocked(string $redirectUri): bool
    {
        $host = parse_url($redirectUri, PHP_URL_HOST);
        if ($host === null || $host === false) {
            return false;
        }
        foreach ($this->patternsOfType('redirect_uri') as $pattern) {
            if ($this->hostMatches($host, $pattern)) {
                return true;
            }
        }
        return false;
    }

    public function isIpBlocked(string $ip): bool
    {
        foreach ($this->patternsOfType('ip') as $pattern) {
            if ($this->ipMatches($ip, $pattern)) {
                return true;
            }
        }
        return false;
    }

    private function ipMatches(string $ip, string $pattern): bool
    {
        $pattern = trim($pattern);
        if ($pattern === $ip) {
            return true;
        }
        // CIDR support, e.g. 203.0.113.0/24
        if (str_contains($pattern, '/')) {
            return self::ipInCidr($ip, $pattern);
        }
        return false;
    }

    public static function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = array_pad(explode('/', $cidr, 2), 2, null);
        if ($bits === null || !is_numeric($bits)) {
            return false;
        }
        $ipBin = @inet_pton($ip);
        $subnetBin = @inet_pton($subnet);
        if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }
        $bits = (int) $bits;
        $bytes = intdiv($bits, 8);
        $remainderBits = $bits % 8;

        if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($subnetBin, 0, $bytes)) {
            return false;
        }
        if ($remainderBits === 0) {
            return true;
        }
        $mask = ~(0xFF >> $remainderBits) & 0xFF;
        return (ord($ipBin[$bytes]) & $mask) === (ord($subnetBin[$bytes]) & $mask);
    }

    private function patternsOfType(string $type): array
    {
        $stmt = $this->pdo->prepare('SELECT pattern FROM blocklist WHERE type = ?');
        $stmt->execute([$type]);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }
}
