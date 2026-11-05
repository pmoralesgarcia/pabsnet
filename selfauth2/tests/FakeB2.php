<?php

/** In-memory stand-in for Backblaze B2's native upload API. */
class FakeB2
{
    /** @var array<string, array{type:string, bytes:string}> */
    public array $files = [];
    public int $authorizations = 0;
    /** Statuses to answer the next uploads with, before behaving normally. */
    public array $failUploads = [];
    private string $base;
    private string $keyId;
    private string $appKey;
    private string $bucketId;

    public function __construct(string $base = 'https://b2.test', string $keyId = 'fake-key', string $appKey = 'fake-app', string $bucketId = 'fake-bucket')
    {
        $this->base = $base;
        $this->keyId = $keyId;
        $this->appKey = $appKey;
        $this->bucketId = $bucketId;
    }

    /** @return array{0:int, 1:string} */
    public function handle(string $method, string $url, array $headers, ?string $body): array
    {
        $route = (string) parse_url($url, PHP_URL_PATH);
        $map = [];
        foreach ($headers as $header) {
            [$name, $value] = explode(': ', $header, 2);
            $map[strtolower($name)] = $value;
        }

        if ($route === '/b2api/v2/b2_authorize_account') {
            if (($map['authorization'] ?? '') !== 'Basic ' . base64_encode($this->keyId . ':' . $this->appKey)) {
                return [401, '{"code":"unauthorized"}'];
            }
            $this->authorizations++;
            return [200, json_encode(['apiUrl' => $this->base, 'authorizationToken' => 'account-token'])];
        }
        if ($route === '/b2api/v2/b2_get_upload_url') {
            if (($map['authorization'] ?? '') !== 'account-token') {
                return [401, '{}'];
            }
            if ((json_decode((string) $body, true)['bucketId'] ?? '') !== $this->bucketId) {
                return [400, '{"code":"bad_bucket_id"}'];
            }
            return [200, json_encode(['uploadUrl' => $this->base . '/upload/pod', 'authorizationToken' => 'upload-token'])];
        }
        if ($route === '/upload/pod') {
            if ($this->failUploads !== []) {
                return [array_shift($this->failUploads), '{}'];
            }
            if (($map['authorization'] ?? '') !== 'upload-token') {
                return [401, '{}'];
            }
            if (($map['x-bz-content-sha1'] ?? '') !== sha1((string) $body)) {
                return [400, '{"code":"bad_sha1"}'];
            }
            $this->files[rawurldecode($map['x-bz-file-name'] ?? '')] = ['type' => $map['content-type'] ?? '', 'bytes' => (string) $body];
            return [200, '{}'];
        }
        return [404, '{}'];
    }
}
