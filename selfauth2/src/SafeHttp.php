<?php

namespace Selfauth;

/**
 * Outbound HTTP GET with SSRF protection: resolves DNS itself, rejects
 * private/reserved ranges, pins the connection to the vetted IP (defeats DNS
 * rebinding), re-validates every redirect, and caps size and time.
 */
class SafeHttp
{
    public static function isPublicIp(string $ip): bool
    {
        return (bool) filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }

    /** @return string[] public IPs for host, or [] if any address is non-public */
    public static function resolve(string $host): array
    {
        $host = trim($host, '[]');
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return self::isPublicIp($host) ? [$host] : [];
        }
        $ips = [];
        foreach (@dns_get_record($host, DNS_A | DNS_AAAA) ?: [] as $r) {
            $ips[] = $r['ip'] ?? $r['ipv6'] ?? null;
        }
        $ips = array_values(array_filter($ips));
        if (!$ips) {
            $v4 = gethostbynamel($host) ?: [];
            $ips = $v4;
        }
        foreach ($ips as $ip) {
            if (!self::isPublicIp($ip)) {
                return [];
            }
        }
        return $ips;
    }

    /**
     * @return array{status:int,body:string,type:string,url:string}|null
     */
    public static function get(string $url, string $accept = '*/*', int $maxBytes = 524288, int $timeout = 5, ?array $allowIps = null): ?array
    {
        if (!function_exists('curl_init')) {
            return null;
        }
        for ($hop = 0; $hop <= 4; $hop++) {
            $p = parse_url($url);
            if (!$p || empty($p['host']) || !in_array(strtolower($p['scheme'] ?? ''), ['http', 'https'], true)) {
                return null;
            }
            if (isset($p['user']) || isset($p['pass'])) {
                return null;
            }
            $port = $p['port'] ?? (strtolower($p['scheme']) === 'https' ? 443 : 80);
            $ips = $allowIps ?? self::resolve($p['host']);
            if (!$ips) {
                return null;
            }
            $host = trim($p['host'], '[]');
            $body = '';
            $curl = curl_init($url);
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_USERAGENT => 'Selfauth-IndieAuth/2.0',
                CURLOPT_HTTPHEADER => ['Accept: ' . $accept],
                CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_RESOLVE => [$host . ':' . $port . ':' . $ips[0]],
                CURLOPT_WRITEFUNCTION => function ($c, $chunk) use (&$body, $maxBytes) {
                    $body .= $chunk;
                    return strlen($body) > $maxBytes ? -1 : strlen($chunk);
                },
            ]);
            $location = null;
            curl_setopt($curl, CURLOPT_HEADERFUNCTION, function ($c, $h) use (&$location) {
                if (stripos($h, 'location:') === 0) {
                    $location = trim(substr($h, 9));
                }
                return strlen($h);
            });
            $ok = curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $type = (string) curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
            curl_close($curl);

            if ($status >= 300 && $status < 400 && $location) {
                $url = self::resolveUrl($url, $location);
                continue;
            }
            if ($ok === false) {
                return null;
            }
            return ['status' => $status, 'body' => $body, 'type' => $type, 'url' => $url];
        }
        return null;
    }

    public static function resolveUrl(string $base, string $rel): string
    {
        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $rel)) {
            return $rel;
        }
        $b = parse_url($base);
        $origin = $b['scheme'] . '://' . $b['host'] . (isset($b['port']) ? ':' . $b['port'] : '');
        if (str_starts_with($rel, '//')) {
            return $b['scheme'] . ':' . $rel;
        }
        if (str_starts_with($rel, '/')) {
            return $origin . $rel;
        }
        $dir = preg_replace('#/[^/]*$#', '/', $b['path'] ?? '/');
        $path = $dir . $rel;
        // normalise dot segments
        $out = [];
        foreach (explode('/', $path) as $seg) {
            if ($seg === '..') {
                array_pop($out);
            } elseif ($seg !== '.') {
                $out[] = $seg;
            }
        }
        return $origin . implode('/', $out);
    }
}
