<?php

namespace Selfauth;

/**
 * Validation and canonicalisation of IndieAuth client_id / redirect_uri URLs
 * following the IndieAuth specification's URL rules.
 */
class ClientId
{
    /**
     * Returns the canonical client_id, or null when it is not a valid one.
     * Rules: http/https, path present, no dot segments, no fragment, no
     * credentials, host is a domain name or a loopback IP (127.0.0.1 / [::1]).
     */
    public static function canonicalize(string $url): ?string
    {
        $url = trim($url);
        if ($url === '' || strlen($url) > 2048 || preg_match('/[\x00-\x20\x7f]/', $url)) {
            return null;
        }
        $p = parse_url($url);
        if ($p === false || empty($p['scheme']) || empty($p['host'])) {
            return null;
        }
        $scheme = strtolower($p['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            return null;
        }
        if (isset($p['fragment']) || str_contains($url, '#') || isset($p['user']) || isset($p['pass'])) {
            return null;
        }
        $host = strtolower($p['host']);
        if (!self::validHost($host)) {
            return null;
        }
        $path = $p['path'] ?? '';
        if ($path === '') {
            return null;
        }
        foreach (explode('/', $path) as $seg) {
            if ($seg === '.' || $seg === '..') {
                return null;
            }
        }
        $out = $scheme . '://' . $host;
        if (isset($p['port'])) {
            $default = $scheme === 'https' ? 443 : 80;
            if ($p['port'] < 1 || $p['port'] > 65535) {
                return null;
            }
            if ($p['port'] !== $default) {
                $out .= ':' . $p['port'];
            }
        }
        $out .= $path;
        if (isset($p['query'])) {
            $out .= '?' . $p['query'];
        }
        return $out;
    }

    public static function validHost(string $host): bool
    {
        if ($host === '[::1]') {
            return true;
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $host === '127.0.0.1';
        }
        if (str_starts_with($host, '[')) {
            return false;
        }
        return (bool) preg_match('/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/', $host);
    }

    public static function isLoopback(string $url): bool
    {
        $h = strtolower((string) parse_url($url, PHP_URL_HOST));
        return in_array($h, ['127.0.0.1', '[::1]', 'localhost'], true);
    }

    public static function origin(string $url): string
    {
        $p = parse_url($url);
        $o = strtolower(($p['scheme'] ?? '') . '://' . ($p['host'] ?? ''));
        if (isset($p['port'])) {
            $o .= ':' . $p['port'];
        }
        return $o;
    }

    /**
     * A redirect_uri may be sent to custom schemes for native apps, but is
     * otherwise http(s) with no fragment.
     */
    public static function validRedirectUri(string $uri): bool
    {
        if ($uri === '' || strlen($uri) > 2048 || preg_match('/[\x00-\x20\x7f]/', $uri) || str_contains($uri, '#')) {
            return false;
        }
        $p = parse_url($uri);
        if ($p === false || empty($p['scheme']) || isset($p['user']) || isset($p['pass'])) {
            return false;
        }
        $scheme = strtolower($p['scheme']);
        if (in_array($scheme, ['javascript', 'data', 'vbscript', 'file', 'blob'], true)) {
            return false;
        }
        if ($scheme === 'http' || $scheme === 'https') {
            return !empty($p['host']);
        }
        return true; // custom app scheme, verified via client metadata
    }

    /**
     * redirect_uri must share the client_id's origin, or be one the client
     * has published (metadata redirect_uris / <link rel="redirect_uri">).
     */
    public static function redirectAllowed(string $clientId, string $redirectUri, array $published): array
    {
        if (self::origin($clientId) === self::origin($redirectUri)) {
            return ['ok' => true, 'verified' => true];
        }
        foreach ($published as $allowed) {
            if (hash_equals($allowed, $redirectUri)) {
                return ['ok' => true, 'verified' => true];
            }
        }
        return ['ok' => false, 'verified' => false];
    }
}
