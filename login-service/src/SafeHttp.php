<?php

namespace Login;

/**
 * Outbound HTTP for URLs that strangers give us. Resolves DNS itself, refuses
 * private addresses and unusual ports, pins the connection to the vetted IP
 * (which defeats DNS rebinding), and caps size and time.
 */
class SafeHttp
{
    /** Local tests only. The deployment must never set this. */
    public static function allowPrivate(): bool
    {
        return getenv('LOGIN_ALLOW_PRIVATE') === 'true';
    }

    public static function isPublicIp(string $ip): bool
    {
        if (str_starts_with(strtolower($ip), '::ffff:')) {
            $ip = substr($ip, 7); // An IPv4 address in IPv6 clothing.
        }
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $n = ip2long($ip);
            return !($n >= ip2long('100.64.0.0') && $n <= ip2long('100.127.255.255')); // Carrier-grade NAT.
        }
        return true;
    }

    /** @return string[] Public IPs for the host, or [] if any of its addresses is not public. */
    public static function resolve(string $host): array
    {
        $host = trim($host, '[]');
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return self::isPublicIp($host) ? [$host] : [];
        }
        $ips = [];
        foreach (@dns_get_record($host, DNS_A | DNS_AAAA) ?: [] as $record) {
            $ips[] = $record['ip'] ?? $record['ipv6'] ?? null;
        }
        $ips = array_values(array_filter($ips)) ?: (gethostbynamel($host) ?: []);
        foreach ($ips as $ip) {
            if (!self::isPublicIp($ip)) {
                return [];
            }
        }
        return $ips;
    }

    /**
     * @param string|array|null $body An array sends multipart/form-data.
     * @param bool $follow Follow redirects. Refused when an Authorization header is set,
     *                     so a token can never be carried to another host.
     * @return array{status:int, headers:array<string,string[]>, body:string, url:string}|null
     */
    public static function request(string $method, string $url, array $headers = [], string|array|null $body = null, bool $follow = false, int $timeout = 10): ?array
    {
        foreach ($headers as $header) {
            if (stripos($header, 'authorization:') === 0) {
                $follow = false;
            }
        }
        for ($hop = 0; $hop <= 4; $hop++) {
            $target = self::target($url);
            if ($target === null) {
                return null;
            }
            $received = [];
            $content = '';
            $max = 1048576;
            $curl = curl_init($url);
            curl_setopt_array($curl, [
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_USERAGENT => 'login.lifeofpablo.com IndieAuth client',
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_WRITEFUNCTION => function ($c, $chunk) use (&$content, $max) {
                    $content .= $chunk;
                    return strlen($content) > $max ? -1 : strlen($chunk);
                },
                CURLOPT_HEADERFUNCTION => function ($c, $line) use (&$received) {
                    if (str_contains($line, ':')) {
                        [$name, $value] = explode(':', $line, 2);
                        $received[strtolower(trim($name))][] = trim($value);
                    }
                    return strlen($line);
                },
            ]);
            if ($target !== []) {
                curl_setopt($curl, CURLOPT_RESOLVE, $target);
            }
            if ($body !== null) {
                curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
            }
            $ok = curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
            if ($ok === false) {
                return null;
            }
            $location = $received['location'][0] ?? null;
            if ($follow && $method === 'GET' && $status >= 300 && $status < 400 && $location !== null) {
                $url = self::resolveUrl($url, $location);
                continue;
            }
            return ['status' => $status, 'headers' => $received, 'body' => $content, 'url' => $url];
        }
        return null;
    }

    /** @return string[]|null curl's resolve list, [] for no pinning, or null if the URL is refused. */
    private static function target(string $url): ?array
    {
        $p = parse_url($url);
        $scheme = strtolower($p['scheme'] ?? '');
        if (!$p || empty($p['host']) || !in_array($scheme, ['http', 'https'], true) || isset($p['user']) || isset($p['pass'])) {
            return null;
        }
        if (self::allowPrivate()) {
            return [];
        }
        $port = $p['port'] ?? ($scheme === 'https' ? 443 : 80);
        if ($port !== 80 && $port !== 443) {
            return null;
        }
        $ips = self::resolve($p['host']);
        return $ips === [] ? null : [trim($p['host'], '[]') . ':' . $port . ':' . $ips[0]];
    }

    public static function resolveUrl(string $base, string $relative): string
    {
        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $relative)) {
            return $relative;
        }
        $b = parse_url($base);
        if (str_starts_with($relative, '//')) {
            return ($b['scheme'] ?? 'https') . ':' . $relative;
        }
        $origin = ($b['scheme'] ?? 'https') . '://' . ($b['host'] ?? '') . (isset($b['port']) ? ':' . $b['port'] : '');
        if (str_starts_with($relative, '/')) {
            return $origin . $relative;
        }
        $segments = [];
        foreach (explode('/', preg_replace('#/[^/]*$#', '/', $b['path'] ?? '/') . $relative) as $segment) {
            if ($segment === '..') {
                array_pop($segments);
            } elseif ($segment !== '.') {
                $segments[] = $segment;
            }
        }
        return $origin . implode('/', $segments);
    }
}
