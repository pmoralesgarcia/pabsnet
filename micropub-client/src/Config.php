<?php

namespace MicropubClient;

/** Deployment settings and per-request plumbing. */
class Config
{
    public static function clientId(): string
    {
        return rtrim((string) getenv('MICROPUB_CLIENT_URL'), '/') . '/';
    }

    public static function redirectUri(): string
    {
        return self::clientId() . 'callback.php';
    }

    /** The one site that may start sign-in from a plain link, for the admin portal's shortcut. */
    public static function oneClickMe(): ?string
    {
        return Discovery::canonicalize((string) getenv('MICROPUB_CLIENT_ONE_CLICK_ME'));
    }

    public static function startSession(): void
    {
        $path = getenv('MICROPUB_CLIENT_SESSION_PATH') ?: sys_get_temp_dir() . '/micropub-client-sessions';
        if (!is_dir($path)) {
            mkdir($path, 0700, true);
        }
        session_save_path($path);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.gc_maxlifetime', '43200');
        session_name('post_session');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => str_starts_with(self::clientId(), 'https://'),
            'httponly' => true,
            'samesite' => 'Lax', // The sign-in callback is a top-level navigation from another site.
        ]);
        session_start();
    }

    public static function securityHeaders(): void
    {
        // form-action allows https because a sign-in form redirects to the person's own server.
        header("Content-Security-Policy: default-src 'none'; style-src 'self'; img-src https: data:; form-action 'self' https:; base-uri 'none'; frame-ancestors 'none'");
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
            throw new ClientError('This page expired. Reload it and try again.');
        }
    }

    /** Traefik appends the real address last, so earlier values can be forged by the caller. */
    public static function clientIp(): string
    {
        if (getenv('MICROPUB_CLIENT_TRUST_PROXY') === 'true' && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
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
        $file = sys_get_temp_dir() . '/mpc-rate-' . hash('sha256', $bucket . self::clientIp());
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
