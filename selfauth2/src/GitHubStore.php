<?php

namespace Selfauth;

/**
 * Minimal GitHub Contents API client: each write is one commit on the branch.
 * The transport is injectable so tests never touch the network.
 */
class GitHubStore
{
    private string $repo;
    private string $branch;
    private string $token;
    private string $api;
    /** @var callable(string, string, array, ?string): array{0:int, 1:string} */
    private $http;

    public function __construct(string $repo, string $branch, string $token, string $api = 'https://api.github.com', ?callable $http = null)
    {
        $this->repo = $repo;
        $this->branch = $branch;
        $this->token = $token;
        $this->api = rtrim($api, '/');
        $this->http = $http ?? [HttpClient::class, 'send'];
    }

    /** @return array{content:string, sha:string}|null Null if the file does not exist. */
    public function get(string $path): ?array
    {
        [$status, $data] = $this->call('GET', $this->contentsUrl($path) . '?ref=' . rawurlencode($this->branch));
        if ($status === 404) {
            return null;
        }
        $this->expect($status, [200], 'read ' . $path);
        if (($data['encoding'] ?? '') !== 'base64' || !is_string($data['content'] ?? null)) {
            throw new \RuntimeException('GitHub did not return the content of ' . $path);
        }
        $content = base64_decode($data['content'], true);
        if ($content === false) {
            throw new \RuntimeException('GitHub returned invalid base64 for ' . $path);
        }
        return ['content' => $content, 'sha' => (string) $data['sha']];
    }

    /** Creates a file, or replaces it when $sha (its current blob) is given. */
    public function put(string $path, string $content, string $message, ?string $sha = null): void
    {
        $body = ['message' => $message, 'content' => base64_encode($content), 'branch' => $this->branch];
        if ($sha !== null) {
            $body['sha'] = $sha;
        }
        [$status] = $this->call('PUT', $this->contentsUrl($path), json_encode($body));
        $this->expect($status, [200, 201], 'write ' . $path);
    }

    public function delete(string $path, string $sha, string $message): void
    {
        $body = json_encode(['message' => $message, 'sha' => $sha, 'branch' => $this->branch]);
        [$status] = $this->call('DELETE', $this->contentsUrl($path), $body);
        $this->expect($status, [200], 'delete ' . $path);
    }

    /**
     * File names directly inside a directory. Reads the directory's tree because
     * the Contents API stops listing at 1000 entries.
     * @return string[]
     */
    public function listDir(string $path): array
    {
        $parent = dirname($path);
        $name = basename($path);
        [$status, $entries] = $this->call('GET', $this->contentsUrl($parent) . '?ref=' . rawurlencode($this->branch));
        $this->expect($status, [200], 'list ' . $parent);
        $sha = null;
        foreach ($entries as $entry) {
            if (($entry['name'] ?? null) === $name && ($entry['type'] ?? null) === 'dir') {
                $sha = $entry['sha'];
                break;
            }
        }
        if ($sha === null) {
            throw new \RuntimeException('Directory not found in the repository: ' . $path);
        }
        [$status, $tree] = $this->call('GET', $this->api . '/repos/' . $this->repo . '/git/trees/' . rawurlencode($sha));
        $this->expect($status, [200], 'list ' . $path);
        if (!empty($tree['truncated'])) {
            throw new \RuntimeException('GitHub truncated the listing of ' . $path);
        }
        $names = [];
        foreach ($tree['tree'] ?? [] as $item) {
            if (($item['type'] ?? null) === 'blob') {
                $names[] = (string) $item['path'];
            }
        }
        return $names;
    }

    private function contentsUrl(string $path): string
    {
        $encoded = implode('/', array_map('rawurlencode', explode('/', $path)));
        return $this->api . '/repos/' . $this->repo . '/contents/' . $encoded;
    }

    /** @return array{0:int, 1:array} */
    private function call(string $method, string $url, ?string $body = null): array
    {
        $headers = [
            'Accept: application/vnd.github+json',
            'Authorization: Bearer ' . $this->token,
            'X-GitHub-Api-Version: 2022-11-28',
            'User-Agent: selfauth-micropub',
        ];
        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
        }
        [$status, $response] = ($this->http)($method, $url, $headers, $body);
        $decoded = json_decode($response, true);
        return [$status, is_array($decoded) ? $decoded : []];
    }

    private function expect(int $status, array $ok, string $action): void
    {
        if (in_array($status, $ok, true)) {
            return;
        }
        // A stale or missing sha. The client can see this and reload, unlike a server fault.
        if ($status === 409 || $status === 422) {
            throw new MicropubException(409, 'conflict', 'This post was changed elsewhere, or already exists. Reload it and try again.');
        }
        throw new \RuntimeException('GitHub could not ' . $action . ' (HTTP ' . $status . ')');
    }
}
