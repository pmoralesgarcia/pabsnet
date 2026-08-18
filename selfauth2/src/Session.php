<?php

namespace Selfauth;

class Session
{
    private static bool $started = false;

    public static function start(): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }

        $savePath = getenv('SELFAUTH_SESSION_PATH') ?: (sys_get_temp_dir());
        if (!is_dir($savePath)) {
            mkdir($savePath, 0770, true);
        }
        session_save_path($savePath);

        $secure = filter_var(getenv('SELFAUTH_COOKIE_SECURE') ?: 'true', FILTER_VALIDATE_BOOLEAN);

        session_name('selfauth_admin');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
        self::$started = true;
    }

    public static function login(string $userUrl, string $method = 'password', ?string $identity = null, string $role = 'owner'): void
    {
        self::start();
        session_regenerate_id(true);
        $_SESSION['admin_authenticated'] = true;
        $_SESSION['admin_user'] = $userUrl;
        $_SESSION['admin_login_at'] = time();
        $_SESSION['admin_auth_method'] = $method;
        $_SESSION['admin_identity'] = $identity;
        $_SESSION['admin_role'] = $role;
    }

    public static function authMethod(): string
    {
        self::start();
        return $_SESSION['admin_auth_method'] ?? 'password';
    }

    public static function identity(): ?string
    {
        self::start();
        return $_SESSION['admin_identity'] ?? null;
    }

    /** 'owner' (full access) | 'manager' (act, but not manage settings/admins) | 'viewer' (read-only) */
    public static function role(): string
    {
        self::start();
        return $_SESSION['admin_role'] ?? 'owner';
    }

    private const ROLE_RANK = ['viewer' => 1, 'manager' => 2, 'owner' => 3];

    public static function hasRole(string $minimum): bool
    {
        $have = self::ROLE_RANK[self::role()] ?? 0;
        $need = self::ROLE_RANK[$minimum] ?? 99;
        return $have >= $need;
    }

    public static function isAuthenticated(): bool
    {
        self::start();
        return !empty($_SESSION['admin_authenticated']);
    }

    public static function requireAuth(string $loginUrl = 'login.php'): void
    {
        if (!self::isAuthenticated()) {
            header('Location: ' . $loginUrl);
            exit;
        }
    }

    public static function logout(): void
    {
        self::start();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    public static function csrfToken(): string
    {
        self::start();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function verifyCsrf(?string $token): bool
    {
        self::start();
        return is_string($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}
