<?php

namespace Selfauth;

class Webhook
{
    public const EVENTS = [
        'signin.success',
        'signin.failed',
        'signin.blocked',
        'webmention.received',
        'webmention.verified',
    ];

    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function add(string $url, array $events, ?string $secret = null): array
    {
        if (!filter_var($url, FILTER_VALIDATE_URL) || !in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            throw new \InvalidArgumentException('Webhook URL must be a valid http(s) URL');
        }
        $events = array_values(array_intersect($events, self::EVENTS));
        if ($events === []) {
            throw new \InvalidArgumentException('Select at least one event');
        }
        $secret = $secret ?: bin2hex(random_bytes(24));

        $stmt = $this->pdo->prepare(
            'INSERT INTO webhooks (url, secret, events, enabled, created_at) VALUES (:url, :secret, :events, 1, :created_at)'
        );
        $stmt->execute([
            'url' => $url,
            'secret' => $secret,
            'events' => implode(',', $events),
            'created_at' => gmdate('c'),
        ]);

        return ['id' => (int) $this->pdo->lastInsertId(), 'secret' => $secret];
    }

    public function remove(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM webhooks WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function setEnabled(int $id, bool $enabled): void
    {
        $stmt = $this->pdo->prepare('UPDATE webhooks SET enabled = ? WHERE id = ?');
        $stmt->execute([$enabled ? 1 : 0, $id]);
    }

    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM webhooks ORDER BY created_at DESC');
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** @return array<int, array<string, mixed>> */
    private function enabledFor(string $event): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM webhooks WHERE enabled = 1');
        $stmt->execute();
        $all = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return array_values(array_filter($all, static function ($row) use ($event) {
            return in_array($event, explode(',', $row['events']), true);
        }));
    }

    /**
     * Deliver $event to every enabled, subscribed webhook. Fire-and-forget
     * with a short timeout -- this is called inline from the request that
     * triggered the event, so it must not be allowed to hang the response.
     */
    public function dispatch(string $event, array $payload): void
    {
        $hooks = $this->enabledFor($event);
        if ($hooks === []) {
            return;
        }

        $body = json_encode([
            'event' => $event,
            'sent_at' => gmdate('c'),
            'data' => $payload,
        ]);

        foreach ($hooks as $hook) {
            $this->deliver($hook, $body);
        }
    }

    private function deliver(array $hook, string $body): void
    {
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp . '.' . $body, $hook['secret']);

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $hook['url'],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-Selfauth-Event: ' . json_decode($body, true)['event'],
                'X-Selfauth-Timestamp: ' . $timestamp,
                // sha256=<hex hmac of "{timestamp}.{body}"> -- verify by
                // recomputing with your webhook secret and hash_equals().
                'X-Selfauth-Signature: sha256=' . $signature,
            ],
        ]);
        curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        $stmt = $this->pdo->prepare('UPDATE webhooks SET last_triggered_at = :t, last_status = :s WHERE id = :id');
        $stmt->execute([
            't' => gmdate('c'),
            's' => $status > 0 ? ('HTTP ' . $status) : ('error: ' . $error),
            'id' => $hook['id'],
        ]);
    }
}
