<?php

namespace Selfauth;

/**
 * A minimal Authorization Code + PKCE (S256) OIDC client, written to work
 * against Kanidm's OIDC provider (and any other spec-compliant provider).
 * Discovery documents and JWKS are cached in the settings table for an
 * hour so every page load doesn't re-fetch them.
 *
 * OIDC is used for two distinct purposes, and this class keeps them
 * strictly separate:
 *
 *  - "login": authenticating as the site owner to the IndieAuth
 *    endpoint itself (index.php) -- i.e. proving you are "me" to some
 *    other website. Only identities on the owner allow-list
 *    (SELFAUTH_OIDC_ALLOWED_EMAILS / SELFAUTH_OIDC_ALLOWED_SUBJECTS) can
 *    ever succeed here.
 *  - "admin": signing into the /admin/ portal. The owner allow-list
 *    always grants full ("owner") access; additionally, identities the
 *    owner has explicitly added via the Admins page (stored in the
 *    `admins` table) get "manager" or "viewer" access. Delegates can
 *    never trigger an IndieAuth "me" login, no matter their role.
 */
class OidcClient
{
    private \PDO $pdo;
    private Settings $settings;
    private string $issuer;
    private string $clientId;
    private ?string $clientSecret;
    private string $redirectUri;
    private string $scopes;

    public function __construct(\PDO $pdo, Settings $settings, string $issuer, string $clientId, ?string $clientSecret, string $redirectUri, string $scopes)
    {
        $this->pdo = $pdo;
        $this->settings = $settings;
        $this->issuer = rtrim($issuer, '/');
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret ?: null;
        $this->redirectUri = $redirectUri;
        $this->scopes = $scopes;
    }

    public static function isConfigured(): bool
    {
        return (bool) getenv('SELFAUTH_OIDC_ISSUER') && (bool) getenv('SELFAUTH_OIDC_CLIENT_ID');
    }

    /**
     * "login" is only safe once the owner allow-list is set (that's who
     * "me" refers to). "admin" is safe once the owner allow-list is set
     * OR at least one delegate exists (added via the admin portal, which
     * itself required the owner to log in first).
     */
    public static function isEnabled(string $purpose): bool
    {
        if (!self::isConfigured() || !self::purposeToggleOn($purpose)) {
            return false;
        }
        $hasOwnerList = self::allowedSubjects() !== [] || self::allowedEmails() !== [];
        if ($purpose === 'login') {
            return $hasOwnerList;
        }
        // purpose === 'admin'
        return $hasOwnerList || self::hasDelegatesConfigured();
    }

    public static function isMisconfigured(string $purpose): bool
    {
        return self::purposeToggleOn($purpose) && !self::isEnabled($purpose);
    }

    private static function purposeToggleOn(string $purpose): bool
    {
        $var = $purpose === 'login' ? 'SELFAUTH_LOGIN_OIDC_ENABLED' : 'SELFAUTH_ADMIN_OIDC_ENABLED';
        return filter_var(getenv($var) ?: 'false', FILTER_VALIDATE_BOOLEAN);
    }

    private static function hasDelegatesConfigured(): bool
    {
        try {
            $pdo = Database::pdo();
        } catch (\Throwable $e) {
            return false;
        }
        return (bool) $pdo->query('SELECT 1 FROM admins LIMIT 1')->fetchColumn();
    }

    /** @return string[] */
    public static function allowedSubjects(): array
    {
        return self::splitList(getenv('SELFAUTH_OIDC_ALLOWED_SUBJECTS') ?: '');
    }

    /** @return string[] */
    public static function allowedEmails(): array
    {
        return array_map('strtolower', self::splitList(getenv('SELFAUTH_OIDC_ALLOWED_EMAILS') ?: ''));
    }

    private static function splitList(string $csv): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $csv))));
    }

    public static function fromEnv(\PDO $pdo, Settings $settings): ?self
    {
        if (!self::isConfigured()) {
            return null;
        }
        $appUrl = rtrim($settings->get('app_url', '') ?? '', '/');
        // A single shared redirect URI for both purposes keeps IdP-side
        // client registration simple; which flow we're in travels in the
        // (server-side) session, not the URL.
        $redirect = getenv('SELFAUTH_OIDC_REDIRECT_URI') ?: ($appUrl . '/oidc-callback.php');

        return new self(
            $pdo,
            $settings,
            (string) getenv('SELFAUTH_OIDC_ISSUER'),
            (string) getenv('SELFAUTH_OIDC_CLIENT_ID'),
            getenv('SELFAUTH_OIDC_CLIENT_SECRET') ?: null,
            $redirect,
            getenv('SELFAUTH_OIDC_SCOPES') ?: 'openid profile email'
        );
    }

    private function discover(): array
    {
        $cacheKey = 'oidc_discovery_' . md5($this->issuer);
        $cached = $this->settings->get($cacheKey);
        if ($cached !== null) {
            $data = json_decode($cached, true);
            if (is_array($data) && ($data['_cached_at'] ?? 0) > time() - 3600) {
                return $data;
            }
        }

        $doc = $this->httpGetJson($this->issuer . '/.well-known/openid-configuration');
        if (!isset($doc['authorization_endpoint'], $doc['token_endpoint'], $doc['jwks_uri'])) {
            throw new \RuntimeException('OIDC discovery document is missing required fields');
        }
        $doc['_cached_at'] = time();
        $this->settings->set($cacheKey, json_encode($doc));
        return $doc;
    }

    private function jwks(string $jwksUri): array
    {
        $cacheKey = 'oidc_jwks_' . md5($jwksUri);
        $cached = $this->settings->get($cacheKey);
        if ($cached !== null) {
            $data = json_decode($cached, true);
            if (is_array($data) && ($data['_cached_at'] ?? 0) > time() - 3600) {
                unset($data['_cached_at']);
                return $data;
            }
        }
        $jwks = $this->httpGetJson($jwksUri);
        $toCache = $jwks;
        $toCache['_cached_at'] = time();
        $this->settings->set($cacheKey, json_encode($toCache));
        return $jwks;
    }

    private function httpGetJson(string $url): array
    {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        $body = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($body === false || $status >= 400) {
            throw new \RuntimeException("Failed to fetch $url: HTTP $status $error");
        }
        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw new \RuntimeException("Invalid JSON from $url");
        }
        return $data;
    }

    /**
     * Start the login flow for the given purpose ('login' or 'admin').
     * $indieauthParams, when purpose === 'login', must carry the
     * already-validated client_id/redirect_uri/state/scope from the
     * original IndieAuth request so the callback can resume it.
     */
    public function buildAuthorizationUrl(string $purpose, array $indieauthParams = []): string
    {
        $discovery = $this->discover();

        $state = bin2hex(random_bytes(16));
        $nonce = bin2hex(random_bytes(16));
        $codeVerifier = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');

        Session::start();
        $_SESSION['oidc_state'] = $state;
        $_SESSION['oidc_nonce'] = $nonce;
        $_SESSION['oidc_code_verifier'] = $codeVerifier;
        $_SESSION['oidc_started_at'] = time();
        $_SESSION['oidc_purpose'] = $purpose;
        $_SESSION['oidc_indieauth_params'] = $purpose === 'login' ? $indieauthParams : null;

        $params = [
            'response_type' => 'code',
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'scope' => $this->scopes,
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ];

        return $discovery['authorization_endpoint'] . '?' . http_build_query($params);
    }

    /**
     * Handle the redirect back from the IdP. Returns
     * ['claims' => ..., 'purpose' => 'login'|'admin', 'role' => 'owner'|'manager'|'viewer', 'indieauth_params' => array|null]
     * Throws on any failure (bad state, bad signature, wrong
     * audience/issuer, expired token, or an identity that isn't
     * authorized for the purpose it's being used for).
     */
    public function handleCallback(?string $code, ?string $state): array
    {
        Session::start();

        $expectedState = $_SESSION['oidc_state'] ?? null;
        $nonce = $_SESSION['oidc_nonce'] ?? null;
        $codeVerifier = $_SESSION['oidc_code_verifier'] ?? null;
        $startedAt = $_SESSION['oidc_started_at'] ?? 0;
        $purpose = $_SESSION['oidc_purpose'] ?? null;
        $indieauthParams = $_SESSION['oidc_indieauth_params'] ?? null;

        unset(
            $_SESSION['oidc_state'],
            $_SESSION['oidc_nonce'],
            $_SESSION['oidc_code_verifier'],
            $_SESSION['oidc_started_at'],
            $_SESSION['oidc_purpose'],
            $_SESSION['oidc_indieauth_params']
        );

        if ($code === null || $state === null || $expectedState === null || $codeVerifier === null || $purpose === null) {
            throw new \RuntimeException('Missing login state; please try logging in again.');
        }
        if (!hash_equals($expectedState, $state)) {
            throw new \RuntimeException('State mismatch; please try logging in again.');
        }
        if (time() - $startedAt > 600) {
            throw new \RuntimeException('Login took too long; please try again.');
        }

        $discovery = $this->discover();
        $tokens = $this->exchangeCode($discovery['token_endpoint'], $code, $codeVerifier);

        if (empty($tokens['id_token'])) {
            throw new \RuntimeException('Token response did not include an id_token');
        }

        $jwks = $this->jwks($discovery['jwks_uri']);
        $claims = Jwt::decodeAndVerify($tokens['id_token'], $jwks);

        $this->validateClaims($claims, $discovery, $nonce);
        $role = $this->resolveRole($claims, $purpose);

        return ['claims' => $claims, 'purpose' => $purpose, 'role' => $role, 'indieauth_params' => $indieauthParams];
    }

    private function exchangeCode(string $tokenEndpoint, string $code, string $codeVerifier): array
    {
        $params = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->redirectUri,
            'client_id' => $this->clientId,
            'code_verifier' => $codeVerifier,
        ];

        $headers = ['Accept: application/json', 'Content-Type: application/x-www-form-urlencoded'];
        $curl = curl_init();
        $opts = [
            CURLOPT_URL => $tokenEndpoint,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ];

        if ($this->clientSecret !== null) {
            // Confidential client: prefer HTTP Basic auth (client_secret_basic),
            // which both the OIDC spec and Kanidm list first.
            $opts[CURLOPT_USERPWD] = $this->clientId . ':' . $this->clientSecret;
        }
        $opts[CURLOPT_HTTPHEADER] = $headers;

        curl_setopt_array($curl, $opts);
        $body = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($body === false || $status >= 400) {
            throw new \RuntimeException('Token exchange failed (HTTP ' . $status . ')');
        }
        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw new \RuntimeException('Token endpoint returned invalid JSON');
        }
        return $data;
    }

    private function validateClaims(array $claims, array $discovery, ?string $expectedNonce): void
    {
        if (($claims['iss'] ?? null) !== ($discovery['issuer'] ?? $this->issuer)) {
            throw new \RuntimeException('ID token issuer mismatch');
        }

        $aud = $claims['aud'] ?? null;
        $audOk = $aud === $this->clientId || (is_array($aud) && in_array($this->clientId, $aud, true));
        if (!$audOk) {
            throw new \RuntimeException('ID token audience mismatch');
        }

        if (!isset($claims['exp']) || time() > (int) $claims['exp']) {
            throw new \RuntimeException('ID token has expired');
        }

        if ($expectedNonce !== null && ($claims['nonce'] ?? null) !== $expectedNonce) {
            throw new \RuntimeException('ID token nonce mismatch');
        }
    }

    /**
     * Even though the signature/issuer/audience all check out, that only
     * proves the person authenticated with your Kanidm instance -- not
     * that they're allowed to act as this Selfauth instance. This is
     * where that authorization decision is made, and it's purpose-aware:
     * "login" (the IndieAuth "me" identity) always requires the owner
     * allow-list; "admin" also accepts a delegate role from the DB.
     */
    private function resolveRole(array $claims, string $purpose): string
    {
        $sub = (string) ($claims['sub'] ?? '');
        $email = strtolower((string) ($claims['email'] ?? ''));

        $isOwner = ($sub !== '' && in_array($sub, self::allowedSubjects(), true))
            || ($email !== '' && in_array($email, self::allowedEmails(), true));

        if ($isOwner) {
            return 'owner';
        }

        if ($purpose === 'admin') {
            $delegates = new Delegates($this->pdo);
            $role = $delegates->roleFor($email !== '' ? $email : null, $sub !== '' ? $sub : null);
            if ($role !== null) {
                return $role;
            }
        }

        throw new \RuntimeException('This identity is not authorized for ' . ($purpose === 'login' ? 'sign-in' : 'admin access') . ' on this Selfauth instance.');
    }
}
