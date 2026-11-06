<?php

namespace MicropubClient;

/** Talks to one person's Micropub server. Anything it answers is untrusted. */
class Micropub
{
    public const MAX_UPLOAD_BYTES = 10485760;
    private const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    private string $token;
    private string $endpoint;

    public function __construct(string $token, string $endpoint)
    {
        $this->token = $token;
        $this->endpoint = $endpoint;
    }

    /** @return array{media:?string, syndicate:array<int,array{uid:string,name:string}>} */
    public function config(): array
    {
        $url = $this->endpoint . (str_contains($this->endpoint, '?') ? '&' : '?') . 'q=config';
        $response = SafeHttp::request('GET', $url, $this->headers());
        $doc = $response !== null && $response['status'] === 200 ? json_decode($response['body'], true) : null;
        $media = is_array($doc) && is_string($doc['media-endpoint'] ?? null) && Discovery::isSafeEndpoint($doc['media-endpoint']) ? $doc['media-endpoint'] : null;
        $syndicate = [];
        foreach (is_array($doc) && is_array($doc['syndicate-to'] ?? null) ? $doc['syndicate-to'] : [] as $target) {
            if (is_string($target['uid'] ?? null) && is_string($target['name'] ?? null)) {
                $syndicate[] = ['uid' => $target['uid'], 'name' => $target['name']];
            }
        }
        return ['media' => $media, 'syndicate' => $syndicate];
    }

    /** Sends a post and returns its URL, or null if the server did not give a usable one. */
    public function publish(array $entry): ?string
    {
        // The spec only requires servers to accept forms, so JSON is kept for what forms cannot express.
        if (Compose::isFlat($entry['properties'])) {
            $headers = [...$this->headers(), 'Content-Type: application/x-www-form-urlencoded'];
            $body = Compose::formBody($entry['properties']);
        } else {
            $headers = [...$this->headers(), 'Content-Type: application/json'];
            $body = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        return $this->location(SafeHttp::request('POST', $this->endpoint, $headers, $body, false, 30), 'post');
    }

    /** Spec fallback for servers with no media endpoint: send the post and its files together. */
    public function publishWithFiles(array $entry, array $files): ?string
    {
        if (!Compose::isFlat($entry['properties'])) {
            throw new ClientError('Your server has no media endpoint, so this kind of post cannot include an uploaded photo. Link to the photo instead.');
        }
        $fields = [];
        foreach (Compose::formPairs($entry['properties']) as $i => [$key, $value]) {
            $fields[$key === 'h' || !str_ends_with($key, '[]') ? $key : substr($key, 0, -2) . "[$i]"] = $value;
        }
        foreach (array_values($files) as $i => $file) {
            $fields[count($files) === 1 ? 'photo' : "photo[$i]"] = new \CURLFile($file['path'], $file['type'], $file['name']);
        }
        return $this->location(SafeHttp::request('POST', $this->endpoint, $this->headers(), $fields, false, 90), 'post');
    }

    /** @return string The stored file's URL. */
    public function upload(string $mediaEndpoint, array $file): string
    {
        $fields = ['file' => new \CURLFile($file['path'], $file['type'], $file['name'])];
        $url = $this->location(SafeHttp::request('POST', $mediaEndpoint, $this->headers(), $fields, false, 90), 'upload');
        if ($url === null) {
            throw new ClientError('Your media server stored the file but did not say where.');
        }
        return $url;
    }

    /** Checks an upload's real contents, not what the browser claims. */
    public static function checkedFile(string $tmpPath, int $size, string $clientName): array
    {
        if ($size <= 0) {
            throw new ClientError('That file is empty.');
        }
        if ($size > self::MAX_UPLOAD_BYTES) {
            throw new ClientError('Photos can be up to 10 MB.');
        }
        $type = (new \finfo(FILEINFO_MIME_TYPE))->file($tmpPath);
        if (!in_array($type, self::IMAGE_TYPES, true)) {
            throw new ClientError('Photos must be JPEG, PNG, GIF or WebP.');
        }
        $name = preg_replace('/[^\w.-]+/', '-', basename($clientName)) ?: 'photo';
        return ['path' => $tmpPath, 'type' => $type, 'name' => $name];
    }

    private function headers(): array
    {
        return ['Authorization: Bearer ' . $this->token, 'Accept: application/json'];
    }

    private function location(?array $response, string $action): ?string
    {
        if ($response === null) {
            throw new ClientError('We could not reach your server to ' . $action . '. Nothing was sent, or it may have timed out, so check your site before trying again.');
        }
        if ($response['status'] < 200 || $response['status'] >= 300) {
            throw new ClientError(self::errorMessage($response));
        }
        $location = $response['headers']['location'][0] ?? null;
        if ($location === null) {
            return null;
        }
        $absolute = SafeHttp::resolveUrl($this->endpoint, $location);
        return preg_match('#^https?://[^\s<>"\']+$#i', $absolute) ? $absolute : null;
    }

    public static function errorMessage(array $response): string
    {
        $doc = json_decode($response['body'], true);
        $detail = is_array($doc) ? ($doc['error_description'] ?? $doc['error'] ?? null) : null;
        if (($doc['error'] ?? null) === 'insufficient_scope') {
            return 'You did not give this app permission to do that. Sign out, then sign in again and allow it.';
        }
        if ($response['status'] === 401) {
            return 'Your sign-in has expired or was revoked. Sign out and sign in again.';
        }
        $text = is_string($detail) ? ': ' . mb_substr($detail, 0, 300) : '';
        return 'Your server refused this (HTTP ' . $response['status'] . ')' . $text;
    }
}
