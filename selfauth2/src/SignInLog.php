<?php

namespace Selfauth;

class SignInLog
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function record(?string $clientId, ?string $redirectUri, ?string $scope, string $ip, ?string $userAgent, bool $success): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO signins (occurred_at, client_id, redirect_uri, scope, ip, user_agent, success)
             VALUES (:occurred_at, :client_id, :redirect_uri, :scope, :ip, :user_agent, :success)'
        );
        $stmt->execute([
            'occurred_at' => gmdate('c'),
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'scope' => $scope,
            'ip' => $ip,
            'user_agent' => $userAgent,
            'success' => $success ? 1 : 0,
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    public function recent(int $limit = 100): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM signins ORDER BY id DESC LIMIT :limit');
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function counts(): array
    {
        $total = (int) $this->pdo->query('SELECT COUNT(*) FROM signins')->fetchColumn();
        $success = (int) $this->pdo->query('SELECT COUNT(*) FROM signins WHERE success = 1')->fetchColumn();
        $failed = $total - $success;
        return ['total' => $total, 'success' => $success, 'failed' => $failed];
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM signins WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function clear(): void
    {
        $this->pdo->exec('DELETE FROM signins');
    }
}
