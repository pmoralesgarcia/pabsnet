<?php

namespace MicropubClient;

/** The IndieAuth sign-in flow, with PKCE. Nothing here outlives the session. */
class IndieAuth
{
    private const SCOPE = 'profile create draft media';
    private const PENDING_TTL = 600;

    /** Starts a sign-in and returns the URL to send the person to. */
    public static function begin(string $input): string
    {
        $me = Discovery::canonicalize($input);
        if ($me === null) {
            throw new ClientError('That does not look like a website address.');
        }
        $found = Discovery::discover($me);
        $state = bin2hex(random_bytes(16));
        $verifier = bin2hex(random_bytes(32));
        $_SESSION['pending'] = $found + ['entered' => $me, 'state' => $state, 'verifier' => $verifier, 'created' => time()];

        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => Config::clientId(),
            'redirect_uri' => Config::redirectUri(),
            'state' => $state,
            'code_challenge' => self::challenge($verifier),
            'code_challenge_method' => 'S256',
            'scope' => self::SCOPE,
            'me' => $found['me'],
        ]);
        $endpoint = $found['authorization_endpoint'];
        return $endpoint . (str_contains($endpoint, '?') ? '&' : '?') . $query;
    }

    public static function challenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    /** Checks the callback and signs the person in. */
    public static function finish(array $query): void
    {
        $pending = $_SESSION['pending'] ?? null;
        unset($_SESSION['pending']); // A state can only ever be used once.
        if (!is_array($pending) || !is_string($query['state'] ?? null) || !hash_equals($pending['state'], $query['state'])) {
            throw new ClientError('This sign-in could not be verified. Please start again.');
        }
        if (time() - $pending['created'] > self::PENDING_TTL) {
            throw new ClientError('This sign-in took too long. Please start again.');
        }
        if (is_string($query['error'] ?? null)) {
            $why = is_string($query['error_description'] ?? null) ? $query['error_description'] : $query['error'];
            throw new ClientError($query['error'] === 'access_denied' ? 'Sign-in was cancelled.' : 'Your server said: ' . $why);
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

        $response = SafeHttp::request('POST', $pending['token_endpoint'], ['Accept: application/json', 'Content-Type: application/x-www-form-urlencoded'], http_build_query([
            'grant_type' => 'authorization_code',
            'code' => $query['code'],
            'client_id' => Config::clientId(),
            'redirect_uri' => Config::redirectUri(),
            'code_verifier' => $pending['verifier'],
        ]));
        $token = $response === null ? null : json_decode($response['body'], true);
        if ($response === null || $response['status'] !== 200 || !is_string($token['access_token'] ?? null) || !is_string($token['me'] ?? null)) {
            $why = is_array($token) && is_string($token['error_description'] ?? null) ? ' It said: ' . $token['error_description'] : '';
            throw new ClientError('Your server did not give us a token.' . $why);
        }

        $me = self::verifiedMe($token['me'], $pending);
        session_regenerate_id(true);
        $client = new Micropub($token['access_token'], $pending['micropub']);
        $config = $client->config();
        $_SESSION['user'] = [
            'me' => $me,
            'token' => $token['access_token'],
            'scope' => is_string($token['scope'] ?? null) ? $token['scope'] : '',
            'name' => is_string($token['profile']['name'] ?? null) ? $token['profile']['name'] : null,
            'micropub' => $pending['micropub'],
            'media' => $config['media'],
            'syndicate' => $config['syndicate'],
            'revocation' => $pending['revocation_endpoint'],
            'token_endpoint' => $pending['token_endpoint'],
        ];
    }

    /** A server may only vouch for a URL that names that same server. */
    private static function verifiedMe(string $returned, array $pending): string
    {
        $me = Discovery::canonicalize($returned);
        if ($me === null) {
            throw new ClientError('Your server returned an address that is not a website.');
        }
        if ($me !== $pending['me'] && $me !== $pending['entered']) {
            $again = Discovery::discover($me);
            if ($again['authorization_endpoint'] !== $pending['authorization_endpoint']) {
                throw new ClientError('Your server returned a website it does not control.');
            }
        }
        return $me;
    }

    /** Best effort: signing out here should still work if their server is down. */
    public static function revoke(array $user): void
    {
        $body = http_build_query($user['revocation'] !== null ? ['token' => $user['token']] : ['action' => 'revoke', 'token' => $user['token']]);
        SafeHttp::request('POST', $user['revocation'] ?? $user['token_endpoint'], ['Content-Type: application/x-www-form-urlencoded'], $body, false, 5);
    }
}
