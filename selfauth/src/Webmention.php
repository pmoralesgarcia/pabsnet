<?php

namespace Selfauth;

/**
 * A minimal Webmention receiver (https://www.w3.org/TR/webmention/),
 * similar in spirit to webmention.io: accepts POST source/target pairs,
 * verifies the source actually links to the target, extracts some basic
 * h-entry/OGP metadata for display, and exposes a small JSON API for
 * pulling mentions of a given target URL.
 */
class Webmention
{
    private \PDO $pdo;
    private string $userUrlHost;

    public function __construct(\PDO $pdo, string $userUrl)
    {
        $this->pdo = $pdo;
        $this->userUrlHost = (string) parse_url($userUrl, PHP_URL_HOST);
    }

    /**
     * Validate a submitted webmention and store it as pending.
     * Returns ['ok' => bool, 'error' => string|null, 'id' => int|null]
     */
    public function receive(string $source, string $target): array
    {
        if (!filter_var($source, FILTER_VALIDATE_URL) || !filter_var($target, FILTER_VALIDATE_URL)) {
            return ['ok' => false, 'error' => 'source and target must both be valid URLs'];
        }
        if ($source === $target) {
            return ['ok' => false, 'error' => 'source and target must not be identical'];
        }

        $targetHost = parse_url($target, PHP_URL_HOST);
        if (!$targetHost || strcasecmp($targetHost, $this->userUrlHost) !== 0) {
            return ['ok' => false, 'error' => 'target is not on this domain'];
        }

        $now = gmdate('c');
        $stmt = $this->pdo->prepare(
            'INSERT INTO webmentions (source, target, status, created_at, updated_at)
             VALUES (:source, :target, :status, :created_at, :updated_at)
             ON CONFLICT(source, target) DO UPDATE SET status = :status, updated_at = :updated_at'
        );
        $stmt->execute([
            'source' => $source,
            'target' => $target,
            'status' => 'pending',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $id = (int) $this->pdo->lastInsertId();
        if ($id === 0) {
            $find = $this->pdo->prepare('SELECT id FROM webmentions WHERE source = ? AND target = ?');
            $find->execute([$source, $target]);
            $id = (int) $find->fetchColumn();
        }

        return ['ok' => true, 'error' => null, 'id' => $id];
    }

    /**
     * Attempt to verify a single pending/failed webmention by fetching the
     * source and confirming it contains a link to the target. Extracts
     * lightweight metadata (title, author, content snippet) on success.
     * Safe to call from a request (short timeout) or from a cron job.
     */
    public function verify(int $id, int $timeoutSeconds = 8): bool
    {
        $stmt = $this->pdo->prepare('SELECT * FROM webmentions WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return false;
        }

        $html = $this->fetch($row['source'], $timeoutSeconds);
        $now = gmdate('c');

        if ($html === null || !$this->containsLinkTo($html, $row['target'])) {
            $update = $this->pdo->prepare(
                "UPDATE webmentions SET status = 'failed', fetched_at = :fetched_at, updated_at = :updated_at WHERE id = :id"
            );
            $update->execute(['fetched_at' => $now, 'updated_at' => $now, 'id' => $id]);
            return false;
        }

        $meta = $this->extractMetadata($html, $row['source']);

        $update = $this->pdo->prepare(
            "UPDATE webmentions SET
                status = 'verified',
                mention_type = :mention_type,
                author_name = :author_name,
                author_photo = :author_photo,
                author_url = :author_url,
                title = :title,
                content = :content,
                published_at = :published_at,
                fetched_at = :fetched_at,
                updated_at = :updated_at
             WHERE id = :id"
        );
        $update->execute([
            'mention_type' => $meta['type'],
            'author_name' => $meta['author_name'],
            'author_photo' => $meta['author_photo'],
            'author_url' => $meta['author_url'],
            'title' => $meta['title'],
            'content' => $meta['content'],
            'published_at' => $meta['published_at'],
            'fetched_at' => $now,
            'updated_at' => $now,
            'id' => $id,
        ]);

        return true;
    }

    private function fetch(string $url, int $timeoutSeconds): ?string
    {
        if (!function_exists('curl_init')) {
            return null;
        }
        // Basic SSRF guardrails: only allow http(s) and refuse to resolve to
        // obviously internal addresses.
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (!in_array($scheme, ['http', 'https'], true)) {
            return null;
        }
        $host = parse_url($url, PHP_URL_HOST);
        if ($host === null || $this->isPrivateHost($host)) {
            return null;
        }

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => $timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => min(4, $timeoutSeconds),
            CURLOPT_USERAGENT => 'Selfauth-Webmention/1.0 (+webmention receiver)',
            CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml'],
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        ]);
        $body = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($body === false || $status >= 400) {
            return null;
        }
        return $body;
    }

    private function isPrivateHost(string $host): bool
    {
        if (in_array(strtolower($host), ['localhost'], true)) {
            return true;
        }
        $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return true; // couldn't resolve, don't risk it
        }
        return !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }

    private function containsLinkTo(string $html, string $target): bool
    {
        $candidates = [$target];
        // Also accept a trailing-slash-insensitive match.
        $candidates[] = rtrim($target, '/');
        $candidates[] = rtrim($target, '/') . '/';

        foreach ($candidates as $candidate) {
            if (str_contains($html, htmlspecialchars($candidate, ENT_QUOTES)) || str_contains($html, $candidate)) {
                return true;
            }
        }
        return false;
    }

    /** @return array{type:string,author_name:?string,author_photo:?string,author_url:?string,title:?string,content:?string,published_at:?string} */
    private function extractMetadata(string $html, string $source): array
    {
        $meta = [
            'type' => 'mention',
            'author_name' => null,
            'author_photo' => null,
            'author_url' => null,
            'title' => null,
            'content' => null,
            'published_at' => null,
        ];

        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();
        $xpath = new \DOMXPath($doc);

        $titleNode = $xpath->query('//title');
        if ($titleNode && $titleNode->length > 0) {
            $meta['title'] = trim($titleNode->item(0)->textContent);
        }

        $ogTitle = $xpath->query('//meta[@property="og:title"]/@content');
        if ($ogTitle && $ogTitle->length > 0) {
            $meta['title'] = trim($ogTitle->item(0)->textContent);
        }

        $ogImage = $xpath->query('//meta[@property="og:image"]/@content');
        if ($ogImage && $ogImage->length > 0) {
            $meta['author_photo'] = trim($ogImage->item(0)->textContent);
        }

        $authorMeta = $xpath->query('//meta[@name="author"]/@content');
        if ($authorMeta && $authorMeta->length > 0) {
            $meta['author_name'] = trim($authorMeta->item(0)->textContent);
        }

        // Very small microformats2 h-card/h-entry heuristic: look for
        // .p-author .p-name / .u-photo, .e-content, .dt-published.
        $authorName = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " p-author ")]//*[contains(concat(" ", normalize-space(@class), " "), " p-name ")]');
        if ($authorName && $authorName->length > 0) {
            $meta['author_name'] = trim($authorName->item(0)->textContent);
        }
        $content = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " e-content ")]');
        if ($content && $content->length > 0) {
            $meta['content'] = mb_substr(trim($content->item(0)->textContent), 0, 1000);
        }
        $published = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " dt-published ")]/@datetime');
        if ($published && $published->length > 0) {
            $meta['published_at'] = trim($published->item(0)->textContent);
        }

        if (!$meta['author_url']) {
            $meta['author_url'] = parse_url($source, PHP_URL_SCHEME) . '://' . parse_url($source, PHP_URL_HOST);
        }

        return $meta;
    }

    /** @return array<int, array<string, mixed>> */
    public function pending(int $limit = 50): array
    {
        return $this->byStatus('pending', $limit);
    }

    /** @return array<int, array<string, mixed>> */
    public function byStatus(string $status, int $limit = 100): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM webmentions WHERE status = :status ORDER BY id DESC LIMIT :limit');
        $stmt->bindValue(':status', $status);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** @return array<int, array<string, mixed>> */
    public function all(int $limit = 200): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM webmentions ORDER BY id DESC LIMIT :limit');
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Mentions for a given target, verified only (public API), like webmention.io's /api/mentions */
    public function forTarget(string $target): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM webmentions WHERE target = ? AND status = 'verified' ORDER BY published_at DESC, id DESC");
        $stmt->execute([$target]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function setStatus(int $id, string $status): void
    {
        if (!in_array($status, ['pending', 'verified', 'failed', 'spam', 'deleted'], true)) {
            throw new \InvalidArgumentException('Invalid status');
        }
        $stmt = $this->pdo->prepare('UPDATE webmentions SET status = :status, updated_at = :updated_at WHERE id = :id');
        $stmt->execute(['status' => $status, 'updated_at' => gmdate('c'), 'id' => $id]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM webmentions WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function counts(): array
    {
        $stmt = $this->pdo->query('SELECT status, COUNT(*) as c FROM webmentions GROUP BY status');
        $counts = ['pending' => 0, 'verified' => 0, 'failed' => 0, 'spam' => 0, 'deleted' => 0];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $counts[$row['status']] = (int) $row['c'];
        }
        return $counts;
    }
}
