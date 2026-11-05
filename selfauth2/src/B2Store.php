<?php

namespace Selfauth;

/**
 * Backblaze B2 uploads through the native API. Authorizes per upload, which is
 * plenty for a personal site. Upload URLs can go stale or hit a busy pod, and
 * B2's answer to that is to fetch a fresh one and retry.
 */
class B2Store
{
    private string $keyId;
    private string $appKey;
    private string $bucketId;
    private string $api;
    /** @var callable(string, string, array, ?string): array{0:int, 1:string} */
    private $http;

    public function __construct(string $keyId, string $appKey, string $bucketId, string $api = 'https://api.backblazeb2.com', ?callable $http = null)
    {
        $this->keyId = $keyId;
        $this->appKey = $appKey;
        $this->bucketId = $bucketId;
        $this->api = rtrim($api, '/');
        $this->http = $http ?? [HttpClient::class, 'send'];
    }

    public function upload(string $name, string $bytes, string $contentType): void
    {
        $sha1 = sha1($bytes);
        $encoded = implode('/', array_map('rawurlencode', explode('/', $name)));
        $lastError = '';

        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $target = $this->uploadTarget();
                [$status] = ($this->http)('POST', $target['uploadUrl'], [
                    'Authorization: ' . $target['authorizationToken'],
                    'X-Bz-File-Name: ' . $encoded,
                    'X-Bz-Content-Sha1: ' . $sha1,
                    'Content-Type: ' . $contentType,
                ], $bytes);
            } catch (\RuntimeException $e) {
                $lastError = $e->getMessage();
                continue;
            }
            if ($status >= 200 && $status < 300) {
                return;
            }
            $lastError = 'HTTP ' . $status;
            if ($status !== 401 && $status !== 408 && $status < 500) {
                break; // A bad request will not get better on retry.
            }
        }
        throw new \RuntimeException('B2 upload failed for ' . $name . ' (' . $lastError . ')');
    }

    /** @return array{uploadUrl:string, authorizationToken:string} */
    private function uploadTarget(): array
    {
        $auth = $this->call('GET', $this->api . '/b2api/v2/b2_authorize_account', [
            'Authorization: Basic ' . base64_encode($this->keyId . ':' . $this->appKey),
        ], null, 'authorize');
        $target = $this->call('POST', rtrim((string) ($auth['apiUrl'] ?? ''), '/') . '/b2api/v2/b2_get_upload_url', [
            'Authorization: ' . ($auth['authorizationToken'] ?? ''),
            'Content-Type: application/json',
        ], json_encode(['bucketId' => $this->bucketId]), 'get an upload URL');
        if (!isset($target['uploadUrl'], $target['authorizationToken'])) {
            throw new \RuntimeException('B2 returned no upload URL');
        }
        return $target;
    }

    /** Errors name the step and status only, so credentials can never reach a log. */
    private function call(string $method, string $url, array $headers, ?string $body, string $step): array
    {
        [$status, $response] = ($this->http)($method, $url, $headers, $body);
        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException('B2 could not ' . $step . ' (HTTP ' . $status . ')');
        }
        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('B2 returned an unreadable response to ' . $step);
        }
        return $decoded;
    }
}
