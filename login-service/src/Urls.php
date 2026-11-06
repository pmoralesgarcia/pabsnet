<?php

namespace Login;

/** URL rules from the IndieAuth spec, sections 3.2 and 3.3. */
class Urls
{
    /** A person's website. Null unless it is a valid profile URL. */
    public static function profile(string $input): ?string
    {
        $input = trim($input);
        if ($input === '' || preg_match('/\s/', $input)) {
            return null;
        }
        if (!preg_match('#^[a-z][a-z0-9+.-]*://#i', $input)) {
            $input = 'https://' . $input;
        }
        $p = self::parts($input);
        if ($p === null) {
            return null;
        }
        // The spec allows neither ports nor IP addresses in a profile URL. Tests run on loopback.
        if (!SafeHttp::allowPrivate() && (isset($p['port']) || filter_var(trim($p['host'], '[]'), FILTER_VALIDATE_IP))) {
            return null;
        }
        return self::build($p);
    }

    /** A site's client_id. HTTPS, because a code must never travel in the clear. */
    public static function client(string $input): ?string
    {
        $p = self::parts(trim($input));
        if ($p === null) {
            return null;
        }
        if (!SafeHttp::allowPrivate() && ($p['scheme'] !== 'https' || filter_var(trim($p['host'], '[]'), FILTER_VALIDATE_IP))) {
            return null;
        }
        return self::build($p);
    }

    /** Sites here must send people back to their own origin, as IndieLogin requires. */
    public static function sameOrigin(string $clientId, string $redirectUri): bool
    {
        $a = self::parts($clientId);
        $b = self::parts($redirectUri);
        return $a !== null && $b !== null && $a['scheme'] === $b['scheme'] && $a['host'] === $b['host'] && ($a['port'] ?? null) === ($b['port'] ?? null);
    }

    public static function host(string $url): string
    {
        return (string) (self::parts($url)['host'] ?? '');
    }

    private static function parts(string $url): ?array
    {
        if ($url === '' || preg_match('/[\s<>"\']/', $url)) {
            return null;
        }
        $p = parse_url($url);
        if (!is_array($p) || empty($p['host']) || isset($p['user']) || isset($p['pass']) || isset($p['fragment'])) {
            return null;
        }
        $p['scheme'] = strtolower($p['scheme'] ?? '');
        if (!in_array($p['scheme'], ['http', 'https'], true)) {
            return null;
        }
        $p['host'] = strtolower($p['host']);
        $p['path'] = $p['path'] ?? '/';
        return preg_match('#(^|/)\.\.?(/|$)#', $p['path']) ? null : $p;
    }

    private static function build(array $p): string
    {
        return $p['scheme'] . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '') . $p['path'] . (isset($p['query']) ? '?' . $p['query'] : '');
    }
}
