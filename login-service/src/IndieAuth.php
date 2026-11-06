<?php

namespace Login;

/**
 * Signs a person in through their own IndieAuth server. This service is an ordinary
 * client there: it asks only who they are, so no token endpoint or token is involved.
 */
class IndieAuth
{
    private const PENDING_TTL = 600;

    /** @return array{me:string, authorization_endpoint:string, issuer:?string, iss_required:bool} */
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
        $found = ['me' => $page['url'], 'authorization_endpoint' => $links['authorization_endpoint'] ?? null, 'issuer' => null, 'iss_required' => false];

        if (isset($links['indieauth-metadata'])) {
            $response = SafeHttp::request('GET', $links['indieauth-metadata'], ['Accept: application/json'], null, true);
            $doc = $response !== null && $response['status'] === 200 ? json_decode($response['body'], true) : null;
            // The spec makes the issuer a prefix of the metadata URL, which stops one site speaking for another.
            if (is_array($doc) && is_string($doc['issuer'] ?? null) && is_string($doc['authorization_endpoint'] ?? null) && str_starts_with($links['indieauth-metadata'], $doc['issuer'])) {
                $found['authorization_endpoint'] = $doc['authorization_endpoint'];
                $found['issuer'] = $doc['issuer'];
                $found['iss_required'] = ($doc['authorization_response_iss_parameter_supported'] ?? false) === true;
            }
        }
        if ($found['authorization_endpoint'] === null) {
            throw new ClientError('That website does not list an IndieAuth server, so we cannot sign you in. It needs a rel="authorization_endpoint" or rel="indieauth-metadata" link.');
        }
        $scheme = strtolower((string) parse_url($found['authorization_endpoint'], PHP_URL_SCHEME));
        if ($scheme !== 'https' && !(SafeHttp::allowPrivate() && $scheme === 'http')) {
            throw new ClientError('That website\'s IndieAuth server is not on HTTPS, so signing in would not be safe.');
        }
        return $found;
    }

    /** @return array<string,string> rel => absolute URL. HTTP headers win over the page, as the spec orders them. */
    public static function links(array $headers, string $body, string $base): array
    {
        $wanted = ['indieauth-metadata', 'authorization_endpoint'];
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

    /** Starts sign-in and returns the URL to send the person to. $context says what to do afterwards. */
    public static function begin(string $input, array $context, string $scope = ''): string
    {
        $me = Urls::profile($input);
        if ($me === null) {
            throw new ClientError('That is not a valid website address. It needs a domain name, with no port or IP address.');
        }
        $found = self::discover($me);
        $state = bin2hex(random_bytes(16));
        $verifier = bin2hex(random_bytes(32));
        $pending = $_SESSION['upstream'] ?? [];
        $pending[$state] = $found + ['entered' => $me, 'verifier' => $verifier, 'created' => time(), 'context' => $context];
        $_SESSION['upstream'] = array_slice($pending, -5, null, true); // A person may have several tabs open.

        $params = [
            'response_type' => 'code',
            'client_id' => Config::issuer(),
            'redirect_uri' => Config::callbackUri(),
            'state' => $state,
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
            'me' => $found['me'],
        ];
        if ($scope !== '') {
            $params['scope'] = $scope;
        }
        $endpoint = $found['authorization_endpoint'];
        return $endpoint . (str_contains($endpoint, '?') ? '&' : '?') . http_build_query($params);
    }

    /** @return array{me:string, profile:?array, context:array} */
    public static function finish(array $query): array
    {
        $state = is_string($query['state'] ?? null) ? $query['state'] : '';
        $pending = $_SESSION['upstream'][$state] ?? null;
        unset($_SESSION['upstream'][$state]); // A state can only ever be used once.
        if (!is_array($pending)) {
            throw new ClientError('This sign-in could not be verified. Please start again.');
        }
        if (time() - $pending['created'] > self::PENDING_TTL) {
            throw new ClientError('This sign-in took too long. Please start again.');
        }
        if (is_string($query['error'] ?? null)) {
            throw new ClientError($query['error'] === 'access_denied' ? 'Sign-in was cancelled.' : 'Your server said: ' . mb_substr(is_string($query['error_description'] ?? null) ? $query['error_description'] : $query['error'], 0, 300));
        }
        // RFC 9207: the response must come from the server we sent the person to.
        if (isset($query['iss']) || $pending['iss_required']) {
            if ($pending['issuer'] === null || !is_string($query['iss'] ?? null) || !hash_equals($pending['issuer'], $query['iss'])) {
                throw new ClientError('The sign-in response came from an unexpected server.');
            }
        }
        if (!is_string($query['code'] ?? null) || $query['code'] === '') {
            throw new ClientError('Your server did not send a sign-in code.');
        }

        // Identity only, so the spec has this go to the authorization endpoint, not a token endpoint.
        $response = SafeHttp::request('POST', $pending['authorization_endpoint'], ['Accept: application/json', 'Content-Type: application/x-www-form-urlencoded'], http_build_query([
            'grant_type' => 'authorization_code',
            'code' => $query['code'],
            'client_id' => Config::issuer(),
            'redirect_uri' => Config::callbackUri(),
            'code_verifier' => $pending['verifier'],
        ]));
        $doc = $response === null ? null : json_decode($response['body'], true);
        if ($response === null || $response['status'] !== 200 || !is_string($doc['me'] ?? null)) {
            throw new ClientError('Your server did not confirm who you are. Please try again.');
        }
        $me = Urls::profile($doc['me']);
        if ($me === null) {
            throw new ClientError('Your server returned an address that is not a valid website.');
        }
        // A server may only vouch for a URL that names that same server.
        if ($me !== $pending['me'] && $me !== $pending['entered'] && self::discover($me)['authorization_endpoint'] !== $pending['authorization_endpoint']) {
            throw new ClientError('Your server returned a website it does not control.');
        }
        $profile = is_array($doc['profile'] ?? null) ? array_filter($doc['profile'], fn($v, $k) => in_array($k, ['name', 'url', 'photo', 'email'], true) && is_string($v), ARRAY_FILTER_USE_BOTH) : null;
        return ['me' => $me, 'profile' => $profile ?: null, 'context' => $pending['context']];
    }
}
