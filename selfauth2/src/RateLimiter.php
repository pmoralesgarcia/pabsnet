<?php

namespace Selfauth;

class RateLimiter
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Returns true if the caller is within budget (and records the hit),
     * false if they've exceeded $maxAttempts within $windowSeconds.
     * $bucket should already include the identifying scope, e.g.
     * "login:203.0.113.4" or "api:token123".
     */
    public function allow(string $bucket, int $maxAttempts, int $windowSeconds): bool
    {
        $now = time();
        $windowStart = intdiv($now, $windowSeconds) * $windowSeconds;

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('SELECT window_start, count FROM rate_limits WHERE bucket = ?');
            $stmt->execute([$bucket]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$row || (int) $row['window_start'] !== $windowStart) {
                $upsert = $this->pdo->prepare(
                    'INSERT INTO rate_limits (bucket, window_start, count) VALUES (:bucket, :window_start, 1)
                     ON CONFLICT(bucket) DO UPDATE SET window_start = :window_start, count = 1'
                );
                $upsert->execute(['bucket' => $bucket, 'window_start' => $windowStart]);
                $this->pdo->commit();
                return true;
            }

            $count = (int) $row['count'];
            if ($count >= $maxAttempts) {
                $this->pdo->commit();
                return false;
            }

            $update = $this->pdo->prepare('UPDATE rate_limits SET count = count + 1 WHERE bucket = ?');
            $update->execute([$bucket]);
            $this->pdo->commit();
            return true;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            // Fail open rather than locking the owner out if the rate
            // limit table itself has a problem.
            return true;
        }
    }

    /** Occasionally called to keep the table from growing forever. */
    public function gc(int $olderThanSeconds = 86400): void
    {
        $cutoff = time() - $olderThanSeconds;
        $stmt = $this->pdo->prepare('DELETE FROM rate_limits WHERE window_start < ?');
        $stmt->execute([$cutoff]);
    }
}
