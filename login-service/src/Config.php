<?php

namespace Login;

/** Deployment settings and per-request plumbing. */
class Config
{
    /** This service's public URL, with a trailing slash. It is the issuer sites check. */
    public static function issuer(): string
    {
        return rtrim((string) getenv('LOGIN_URL'), '/') . '/';
    }

    public static function callbackUri(): string
    {
        return self::issuer() . 'callback';
    }

    public static function registrationOpen(): bool
    {
        return getenv('LOGIN_REGISTRATION') !== 'closed';
    }

    public static function startSession(): void
    {
        $path = getenv('LOGIN_SESSION_PATH') ?: sys_get_temp_dir() . '/login-sessions';
        if (!is_dir($path)) {
            mkdir($path, 0700, true);
        }
        session_save_path($path);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.gc_maxlifetime', '3600');
        session_name('login_session');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => str_starts_with(self::issuer(), 'https://'),
            'httponly' => true,
            'samesite' => 'Lax', // The callback is a top-level navigation from the person's own server.
        ]);
        session_start();
    }

    public static function securityHeaders(): void
    {
        // form-action allows https because the sign-in form redirects to the person's own server,
        // and browsers apply form-action to redirects too.
        $allowHttp = SafeHttp::allowPrivate() ? ' http:' : '';
        header("Content-Security-Policy: default-src 'none'; style-src 'self'; img-src 'self'; form-action 'self' https:$allowHttp; base-uri 'none'; frame-ancestors 'none'");
        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: no-referrer');
        header('Cache-Control: no-store');
    }

    public static function csrfToken(): string
    {
        return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    }

    public static function checkCsrf(): void
    {
        $sent = $_POST['_csrf'] ?? null;
        if (!is_string($sent) || !isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $sent)) {
            throw new ClientError('This page expired. Go back, reload it and try again.');
        }
    }

    /** Traefik appends the real address last, so earlier values can be forged by the caller. */
    public static function clientIp(): string
    {
        if (getenv('LOGIN_TRUST_PROXY') === 'true' && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim(end($parts));
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /** Fixed-window limit, shared across workers through a locked file. */
    public static function allow(string $bucket, int $max, int $window): bool
    {
        $file = sys_get_temp_dir() . '/login-rate-' . hash('sha256', $bucket . self::clientIp());
        $handle = fopen($file, 'c+');
        if ($handle === false) {
            return true;
        }
        flock($handle, LOCK_EX);
        $state = json_decode((string) stream_get_contents($handle), true);
        $now = time();
        if (!is_array($state) || $state['start'] + $window <= $now) {
            $state = ['start' => $now, 'count' => 0];
        }
        $state['count']++;
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode($state));
        flock($handle, LOCK_UN);
        fclose($handle);
        return $state['count'] <= $max;
    }
}
