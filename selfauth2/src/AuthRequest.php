<?php

namespace Selfauth;

/**
 * Parses and validates an IndieAuth authorization request, and completes it
 * (issues a code and redirects back to the client).
 */
class AuthRequest
{
    public ?string $clientId = null;
    public ?string $redirectUri = null;
    public ?string $state = null;
    public ?string $challenge = null;
    public array $requestedScopes = [];
    public array $client = [];
    public bool $redirectVerified = false;
    public bool $crossOrigin = false;
    public ?string $error = null;        // fatal, shown on page (cannot redirect safely)
    public ?string $softError = null;    // can be returned via redirect
    public ?string $softErrorDescription = null;

    /** What the admin portal's one-click sign-in may grant without asking. */
    public const TRUSTED_CLIENT_SCOPES = ['profile', 'create', 'draft', 'media'];

    /** Whether this is the configured client, returning to its exact callback. */
    public static function isTrustedClient(?string $clientId, ?string $redirectUri): bool
    {
        $trusted = self::trustedClientId();
        return $trusted !== null && $clientId === $trusted && $redirectUri === $trusted . 'callback.php';
    }

    public static function trustedClientId(): ?string
    {
        $value = trim((string) getenv('SELFAUTH_TRUSTED_CLIENT_ID'));
        if ($value === '') {
            return null;
        }
        return ClientId::canonicalize(rtrim($value, '/') . '/');
    }

    public static function requirePkce(): bool
    {
        return filter_var(getenv('SELFAUTH_REQUIRE_PKCE') ?: 'true', FILTER_VALIDATE_BOOLEAN);
    }

    public static function fromQuery(array $q, bool $discover = true): self
    {
        $r = new self();
        $clientId = ClientId::canonicalize((string) ($q['client_id'] ?? ''));
        if ($clientId === null) {
            $r->error = 'The client_id is not a valid URL. It must be an http(s) URL with a path, and no fragment, credentials or dot segments.';
            return $r;
        }
        $r->clientId = $clientId;

        $redirect = (string) ($q['redirect_uri'] ?? '');
        if (!ClientId::validRedirectUri($redirect)) {
            $r->error = 'The redirect_uri is missing or not a valid URL.';
            return $r;
        }
        $r->redirectUri = $redirect;

        $me = (string) ($q['me'] ?? '');
        if ($me !== '' && rtrim(strtolower($me), '/') !== rtrim(strtolower(SELFAUTH_USER_URL), '/')) {
            $r->error = 'This endpoint signs in ' . SELFAUTH_USER_URL . ', but the app asked for a different identity.';
            return $r;
        }

        // Verify the redirect_uri belongs to the client.
        $r->client = $discover ? ClientDiscovery::discover($clientId) : ['name' => null, 'logo' => null, 'url' => null, 'redirect_uris' => [], 'fetched' => false];
        $check = ClientId::redirectAllowed($clientId, $redirect, $r->client['redirect_uris']);
        $r->crossOrigin = !$check['ok'];
        if (!$check['ok']) {
            $r->error = 'The redirect_uri is on a different site than the app and the app does not list it as allowed. Sign-in was stopped so your login cannot be sent somewhere unexpected.';
            return $r;
        }
        $r->redirectVerified = true;

        // From here on, errors may be sent to the (verified) redirect_uri.
        $state = $q['state'] ?? null;
        if ($state !== null && !preg_match('/^[\x20-\x7E]{1,512}$/', (string) $state)) {
            $r->softError = 'invalid_request';
            $r->softErrorDescription = 'state contains invalid characters';
            return $r;
        }
        $r->state = $state === null ? null : (string) $state;

        $rt = $q['response_type'] ?? 'code';
        if (!in_array($rt, ['code', 'id', ''], true)) {
            $r->softError = 'unsupported_response_type';
            $r->softErrorDescription = 'response_type must be "code"';
            return $r;
        }

        $scope = (string) ($q['scope'] ?? '');
        if ($scope !== '' && !preg_match('/^[\x21\x23-\x5B\x5D-\x7E]+( [\x21\x23-\x5B\x5D-\x7E]+)*$/', $scope)) {
            $r->softError = 'invalid_scope';
            $r->softErrorDescription = 'scope contains invalid characters';
            return $r;
        }
        $r->requestedScopes = Scopes::parse($scope);
        if (count($r->requestedScopes) > 20) {
            $r->softError = 'invalid_scope';
            $r->softErrorDescription = 'too many scopes requested';
            return $r;
        }

        $challenge = (string) ($q['code_challenge'] ?? '');
        $method = (string) ($q['code_challenge_method'] ?? '');
        if ($challenge === '') {
            if (self::requirePkce()) {
                $r->softError = 'invalid_request';
                $r->softErrorDescription = 'code_challenge is required (PKCE with S256)';
                return $r;
            }
        } else {
            if ($method !== 'S256') {
                $r->softError = 'invalid_request';
                $r->softErrorDescription = 'code_challenge_method must be S256';
                return $r;
            }
            if (!preg_match('/^[A-Za-z0-9\-._~]{43}$/', $challenge)) {
                $r->softError = 'invalid_request';
                $r->softErrorDescription = 'code_challenge must be a base64url SHA-256 (43 characters)';
                return $r;
            }
            $r->challenge = $challenge;
        }
        return $r;
    }

    public function toArray(): array
    {
        return [
            'client_id' => $this->clientId, 'redirect_uri' => $this->redirectUri,
            'state' => $this->state, 'code_challenge' => $this->challenge,
            'client_name' => $this->client['name'] ?? null,
        ];
    }

    public static function redirectUrl(string $base, array $params): string
    {
        return $base . (str_contains($base, '?') ? '&' : '?') . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    public function redirectError(string $error, string $description): never
    {
        $p = ['error' => $error, 'error_description' => $description];
        if ($this->state !== null) {
            $p['state'] = $this->state;
        }
        $p['iss'] = SELFAUTH_APP_URL;
        self::noStore();
        header('Location: ' . self::redirectUrl($this->redirectUri, $p), true, 303);
        exit;
    }

    /**
     * Issue the code and send the user back to the client.
     * @param string[] $granted already-normalised granted scopes
     */
    public static function complete(\PDO $pdo, array $params, array $granted, string $method, ?string $identity = null): never
    {
        $granted = Scopes::normalizeGranted($granted);
        $scopeStr = implode(' ', $granted);
        $code = AuthCodes::issue($pdo, $params['client_id'], $params['redirect_uri'], $scopeStr, $params['code_challenge'] ?? null, SELFAUTH_USER_URL);
        $out = ['code' => $code];
        if (($params['state'] ?? null) !== null) {
            $out['state'] = $params['state'];
        }
        $out['iss'] = SELFAUTH_APP_URL;

        $ip = Support::clientIp();
        (new SignInLog($pdo))->record($params['client_id'], $params['redirect_uri'], $scopeStr === '' ? null : $scopeStr, $ip, $_SERVER['HTTP_USER_AGENT'] ?? null, true);
        EventBus::fire($pdo, 'signin.success', array_filter([
            'client_id' => $params['client_id'], 'redirect_uri' => $params['redirect_uri'],
            'scope' => $scopeStr === '' ? null : $scopeStr, 'ip' => $ip, 'method' => $method, 'identity' => $identity,
        ], fn($v) => $v !== null), 'Successful sign-in (' . $method . ') from ' . $ip . ' for client ' . $params['client_id']);
        if (function_exists('syslog') && getenv('SELFAUTH_SYSLOG_SUCCESS') === 'true') {
            syslog(LOG_INFO, sprintf('IndieAuth: %s login from %s for %s', $method, $ip, SELFAUTH_USER_URL));
        }
        self::noStore();
        header('Location: ' . self::redirectUrl($params['redirect_uri'], $out), true, 303);
        exit;
    }

    public static function noStore(): void
    {
        header('Cache-Control: no-store');
        header('Pragma: no-cache');
    }
}
