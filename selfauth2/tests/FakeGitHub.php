<?php

/**
 * In-memory stand-in for the parts of the GitHub API that GitHubStore uses, so
 * tests never touch a real repository. Rejects stale shas like the real API.
 */
class FakeGitHub
{
    /** @var array<string, string> path => content */
    public array $files;
    /** @var string[] commit messages, oldest first */
    public array $commits = [];

    public function __construct(array $files = [])
    {
        $this->files = $files;
    }

    /** @return array{0:int, 1:string} */
    public function handle(string $method, string $url, array $headers, ?string $body): array
    {
        if (!in_array(true, array_map(fn($h) => str_starts_with($h, 'Authorization: Bearer '), $headers), true)) {
            return [401, '{}'];
        }
        $parts = parse_url($url);
        $route = rawurldecode($parts['path'] ?? '');
        if (preg_match('#^/repos/[^/]+/[^/]+/git/trees/(.+)$#', $route, $m)) {
            return $this->tree($m[1]);
        }
        if (!preg_match('#^/repos/[^/]+/[^/]+/contents/(.+)$#', $route, $m)) {
            return [404, '{}'];
        }
        $path = $m[1];
        $input = $body === null ? [] : (array) json_decode($body, true);
        return match ($method) {
            'GET' => $this->read($path),
            'PUT' => $this->write($path, $input),
            'DELETE' => $this->remove($path, $input),
            default => [405, '{}'],
        };
    }

    private function read(string $path): array
    {
        if (isset($this->files[$path])) {
            return [200, json_encode(['encoding' => 'base64', 'content' => base64_encode($this->files[$path]), 'sha' => sha1($this->files[$path])])];
        }
        $entries = [];
        foreach (array_keys($this->files) as $file) {
            if (str_starts_with($file, $path . '/')) {
                $rest = explode('/', substr($file, strlen($path) + 1));
                $isDir = count($rest) > 1;
                $entries[$rest[0]] = ['name' => $rest[0], 'type' => $isDir ? 'dir' : 'file', 'sha' => $isDir ? bin2hex($path . '/' . $rest[0]) : sha1($this->files[$file])];
            }
        }
        return $entries === [] ? [404, '{}'] : [200, json_encode(array_values($entries))];
    }

    private function tree(string $sha): array
    {
        $dir = (string) hex2bin($sha);
        $items = [];
        foreach (array_keys($this->files) as $file) {
            if (str_starts_with($file, $dir . '/') && !str_contains(substr($file, strlen($dir) + 1), '/')) {
                $items[] = ['path' => substr($file, strlen($dir) + 1), 'type' => 'blob'];
            }
        }
        return [200, json_encode(['tree' => $items, 'truncated' => false])];
    }

    private function write(string $path, array $input): array
    {
        $exists = isset($this->files[$path]);
        if ($exists ? ($input['sha'] ?? null) !== sha1($this->files[$path]) : isset($input['sha'])) {
            return [$exists && !isset($input['sha']) ? 422 : 409, '{}'];
        }
        $this->files[$path] = (string) base64_decode((string) $input['content'], true);
        $this->commits[] = (string) $input['message'];
        return [$exists ? 200 : 201, '{}'];
    }

    private function remove(string $path, array $input): array
    {
        if (!isset($this->files[$path])) {
            return [404, '{}'];
        }
        if (($input['sha'] ?? null) !== sha1($this->files[$path])) {
            return [409, '{}'];
        }
        unset($this->files[$path]);
        $this->commits[] = (string) $input['message'];
        return [200, '{}'];
    }
}
