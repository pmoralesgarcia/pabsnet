<?php

namespace MicropubClient;

/** Finds a website's IndieAuth and Micropub endpoints from its URL. */
class Discovery
{
    /** Normalises what someone typed into a profile URL, or null if it cannot be one. */
    public static function canonicalize(string $input): ?string
    {
        $input = trim($input);
        if ($input === '' || preg_match('/\s/', $input)) {
            return null;
        }
        if (!preg_match('#^[a-z][a-z0-9+.-]*://#i', $input)) {
            $input = 'https://' . $input;
        }
        $p = parse_url($input);
        $scheme = strtolower($p['scheme'] ?? '');
        if (!$p || empty($p['host']) || !in_array($scheme, ['http', 'https'], true)
            || isset($p['user']) || isset($p['pass']) || isset($p['fragment'])) {
            return null;
        }
        $path = $p['path'] ?? '/';
        if (preg_match('#(^|/)\.\.?(/|$)#', $path)) {
            return null;
        }
        return $scheme . '://' . strtolower($p['host']) . (isset($p['port']) ? ':' . $p['port'] : '') . $path . (isset($p['query']) ? '?' . $p['query'] : '');
    }

    /** @return array{me:string, authorization_endpoint:string, token_endpoint:string, micropub:string, revocation_endpoint:?string, issuer:?string, iss_required:bool} */
    public static function discover(string $me): array
    {
        $page = SafeHttp::request('GET', $me, ['Accept: text/html, application/xhtml+xml'], null, true);
        if ($page === null) {
            throw new ClientError('We could not reach that website. Check the address and try again.');
        }
        if ($page['status'] >= 400) {
            throw new ClientError('That website answered with an error (HTTP ' . $page['status'] . ').');
        }
        $links = self::links($page['headers'], $page['body'], $page['url']);
        $found = [
            'me' => $page['url'],
            'authorization_endpoint' => $links['authorization_endpoint'] ?? null,
            'token_endpoint' => $links['token_endpoint'] ?? null,
            'micropub' => $links['micropub'] ?? null,
            'revocation_endpoint' => null,
            'issuer' => null,
            'iss_required' => false,
        ];

        if (isset($links['indieauth-metadata'])) {
            $metadata = self::metadata($links['indieauth-metadata']);
            if ($metadata !== null) {
                $found = array_merge($found, $metadata);
            }
        }
        if ($found['authorization_endpoint'] === null) {
            throw new ClientError('That website does not list an IndieAuth server, so we cannot sign you in.');
        }
        if ($found['token_endpoint'] === null) {
            throw new ClientError('That website does not list a token endpoint, which is needed to post.');
        }
        if ($found['micropub'] === null) {
            throw new ClientError('That website does not list a Micropub endpoint, so there is nowhere to post.');
        }
        foreach (['authorization_endpoint', 'token_endpoint', 'micropub'] as $key) {
            if (!self::isSafeEndpoint($found[$key])) {
                throw new ClientError('That website lists an endpoint that is not HTTPS, so signing in would not be safe.');
            }
        }
        if ($found['revocation_endpoint'] !== null && !self::isSafeEndpoint($found['revocation_endpoint'])) {
            $found['revocation_endpoint'] = null;
        }
        return $found;
    }

    /** @return array<string,string> rel => absolute URL. HTTP headers win over the page, as the spec orders them. */
    public static function links(array $headers, string $body, string $base): array
    {
        $wanted = ['indieauth-metadata', 'authorization_endpoint', 'token_endpoint', 'micropub'];
        $links = [];
        foreach ($headers['link'] ?? [] as $header) {
            preg_match_all('/<([^>]*)>\s*((?:;\s*[\w*-]+(?:=(?:"[^"]*"|[^;,]*))?\s*)*)/', $header, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                if (preg_match('/;\s*rel=(?:"([^"]*)"|([^;,\s]*))/i', $match[2], $rel)) {
                    foreach (preg_split('/\s+/', strtolower($rel[1] !== '' ? $rel[1] : ($rel[2] ?? ''))) as $name) {
                        if (in_array($name, $wanted, true) && !isset($links[$name])) {
                            $links[$name] = SafeHttp::resolveUrl($base, $match[1]);
                        }
                    }
                }
            }
        }
        if (trim($body) === '') {
            return $links;
        }
        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?>' . $body, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        foreach ((new \DOMXPath($dom))->query('//link[@rel][@href] | //a[@rel][@href]') as $element) {
            foreach (preg_split('/\s+/', strtolower(trim($element->getAttribute('rel')))) as $name) {
                if (in_array($name, $wanted, true) && !isset($links[$name])) {
                    $links[$name] = SafeHttp::resolveUrl($base, $element->getAttribute('href'));
                }
            }
        }
        return $links;
    }

    /** The server's metadata document, or null to fall back to the page's own links. */
    private static function metadata(string $url): ?array
    {
        $response = SafeHttp::request('GET', $url, ['Accept: application/json'], null, true);
        $doc = $response !== null && $response['status'] === 200 ? json_decode($response['body'], true) : null;
        if (!is_array($doc) || !is_string($doc['issuer'] ?? null) || !is_string($doc['authorization_endpoint'] ?? null)) {
            return null;
        }
        // The spec makes the issuer a prefix of the metadata URL, which stops one site speaking for another.
        if (!str_starts_with($url, $doc['issuer'])) {
            return null;
        }
        $out = [
            'issuer' => $doc['issuer'],
            'authorization_endpoint' => $doc['authorization_endpoint'],
            'iss_required' => ($doc['authorization_response_iss_parameter_supported'] ?? false) === true,
        ];
        foreach (['token_endpoint', 'revocation_endpoint'] as $key) {
            if (is_string($doc[$key] ?? null)) {
                $out[$key] = $doc[$key];
            }
        }
        return $out;
    }

    public static function isSafeEndpoint(string $url): bool
    {
        $p = parse_url($url);
        $scheme = strtolower(is_array($p) ? ($p['scheme'] ?? '') : '');
        return $scheme === 'https' && !empty($p['host']) || (SafeHttp::allowPrivate() && $scheme === 'http');
    }
}
