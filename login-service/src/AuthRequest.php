<?php

namespace Login;

/** A site's request to sign someone in. */
class AuthRequest
{
    /**
     * Problems with the site's identity are shown here and never redirected, because
     * the redirect address cannot be trusted until it has passed these checks.
     * @return array{client_id:string, redirect_uri:string, state:string, code_challenge:string, scope:string, me:?string}
     */
    public static function validate(array $q, Store $store): array
    {
        $clientId = Urls::client(is_string($q['client_id'] ?? null) ? $q['client_id'] : '');
        if ($clientId === null) {
            throw new ClientError('This site sent an invalid client_id, so we cannot sign you in.');
        }
        $redirect = is_string($q['redirect_uri'] ?? null) ? $q['redirect_uri'] : '';
        if (!Urls::sameOrigin($clientId, $redirect)) {
            throw new ClientError('This site asked to send you back to an address outside its own domain, so we stopped.');
        }
        if (!$store->isRegistered($clientId)) {
            throw new ClientError(Urls::host($clientId) . ' is not registered with this service. Its owner can add it on the registration page.');
        }

        $problem = null;
        if (isset($q['response_type']) && $q['response_type'] !== 'code') {
            $problem = ['unsupported_response_type', 'response_type must be code'];
        } elseif (!is_string($q['state'] ?? null) || $q['state'] === '') {
            $problem = ['invalid_request', 'state is required'];
        } elseif (!is_string($q['code_challenge'] ?? null) || !preg_match('/^[A-Za-z0-9\-_]{43,128}$/', $q['code_challenge'])) {
            $problem = ['invalid_request', 'A PKCE code_challenge is required'];
        } elseif (($q['code_challenge_method'] ?? '') !== 'S256') {
            $problem = ['invalid_request', 'code_challenge_method must be S256'];
        }
        if ($problem !== null) {
            self::redirectError($redirect, $problem[0], $problem[1], is_string($q['state'] ?? null) ? $q['state'] : null);
        }

        $me = is_string($q['me'] ?? null) && $q['me'] !== '' ? Urls::profile($q['me']) : null;
        // This service only identifies people, so profile is the only scope worth passing on.
        $wantsProfile = is_string($q['scope'] ?? null) && in_array('profile', preg_split('/\s+/', $q['scope']), true);
        return [
            'client_id' => $clientId, 'redirect_uri' => $redirect, 'state' => $q['state'],
            'code_challenge' => $q['code_challenge'], 'scope' => $wantsProfile ? 'profile' : '', 'me' => $me,
        ];
    }

    public static function backTo(string $redirect, array $params): string
    {
        return $redirect . (str_contains($redirect, '?') ? '&' : '?') . http_build_query($params + ['iss' => Config::issuer()]);
    }

    public static function redirectError(string $redirect, string $error, string $description, ?string $state): never
    {
        $params = ['error' => $error, 'error_description' => $description];
        if ($state !== null) {
            $params['state'] = $state;
        }
        header('Location: ' . self::backTo($redirect, $params), true, 303);
        exit;
    }
}
