<?php

namespace Selfauth;

/** Request plumbing shared by the Micropub and media endpoints. */
class MicropubHttp
{
    /** Browser-based clients need CORS. Tokens are bearer, never cookies, so `*` is safe. */
    public static function start(): void
    {
        AuthRequest::noStore();
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Headers: Authorization, Content-Type');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Expose-Headers: Location');
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }

    public static function json(int $status, array $body): never
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function fail(int $status, string $error, string $description = ''): never
    {
        self::json($status, array_filter(['error' => $error, 'error_description' => $description]));
    }

    /** Sends the error and exits unless the request carries a usable token. */
    public static function authenticate(\PDO $pdo): array
    {
        $header = AccessTokens::bearer();
        $form = isset($_POST['access_token']) ? (string) $_POST['access_token'] : null;
        if ($header !== null && $form !== null) {
            self::fail(400, 'invalid_request', 'Send the token in the header or the body, not both');
        }
        $token = $header ?? $form;
        if ($token === null || $token === '') {
            header('WWW-Authenticate: Bearer');
            self::fail(401, 'unauthorized', 'No access token was provided');
        }
        if (!(new RateLimiter($pdo))->allow('micropub:' . hash('sha256', $token), 60, 60)) {
            self::fail(429, 'slow_down', 'Too many requests');
        }
        $row = AccessTokens::authenticate($pdo, $token);
        if ($row === null) {
            header('WWW-Authenticate: Bearer error="invalid_token"');
            self::fail(401, 'invalid_token', 'The token is invalid, expired or revoked');
        }
        if (rtrim($row['me'], '/') !== rtrim(SELFAUTH_USER_URL, '/')) {
            self::fail(403, 'forbidden', 'This token is not for this site');
        }
        return $row;
    }

    /** @param string[] $any The token needs at least one of these. */
    public static function requireScope(array $row, array $any): void
    {
        if (!array_intersect($any, Scopes::parse($row['scope']))) {
            header('WWW-Authenticate: Bearer error="insufficient_scope", scope="' . implode(' ', $any) . '"');
            self::fail(403, 'insufficient_scope', 'This token needs one of: ' . implode(', ', $any));
        }
    }

    public static function server(): Micropub
    {
        $micropub = Micropub::fromEnv(SELFAUTH_USER_URL);
        if ($micropub === null) {
            self::fail(503, 'temporarily_unavailable', 'Micropub is not configured on this server');
        }
        return $micropub;
    }

    /** Runs the handler and turns exceptions into responses. GitHub details stay in the log. */
    public static function guarded(callable $handler): never
    {
        try {
            $handler();
            exit;
        } catch (MicropubException $e) {
            self::fail($e->status, $e->error, $e->getMessage());
        } catch (\Throwable $e) {
            error_log('Micropub error: ' . $e->getMessage());
            self::fail(502, 'server_error', 'The post could not be saved to the repository. Try again.');
        }
    }

    /** Commits each uploaded file under a form field. @return string[] Public URLs. */
    public static function uploadsFrom(Micropub $micropub, string $field): array
    {
        $file = $_FILES[$field] ?? null;
        if ($file === null) {
            return [];
        }
        $urls = [];
        foreach ((array) $file['tmp_name'] as $i => $tmp) {
            $error = (array) $file['error'];
            if ($error[$i] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($error[$i] === UPLOAD_ERR_INI_SIZE || $error[$i] === UPLOAD_ERR_FORM_SIZE) {
                throw new MicropubException(413, 'invalid_request', 'The file is larger than 10 MB');
            }
            if ($error[$i] !== UPLOAD_ERR_OK || !is_uploaded_file($tmp)) {
                throw new MicropubException(400, 'invalid_request', 'The upload failed');
            }
            $urls[] = $micropub->storeMedia($tmp);
        }
        return $urls;
    }
}
