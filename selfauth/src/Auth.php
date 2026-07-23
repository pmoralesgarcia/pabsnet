<?php

namespace Selfauth;

/**
 * Password storage.
 *
 * The original Selfauth stored a single unsalted MD5 digest of
 * (normalized-url + password + app-key). That is no longer acceptable:
 * MD5 is fast and broken, and there was no per-install salt beyond the
 * app key. This class uses PASSWORD_ARGON2ID (falling back to bcrypt if
 * the Argon2 extension isn't compiled in) via PHP's password_hash API,
 * which handles salting, work factor tuning, and future-proof algorithm
 * identifiers for us.
 *
 * verify() also transparently accepts a legacy MD5 hash so existing
 * installs keep working; the caller can then rehash and store a modern
 * hash going forward (see needsRehash()/legacy detection below).
 */
class Auth
{
    public static function algo(): string
    {
        return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
    }

    public static function hashPassword(string $password): string
    {
        $algo = self::algo();
        $options = $algo === PASSWORD_ARGON2ID
            ? ['memory_cost' => 1 << 16, 'time_cost' => 4, 'threads' => 2]
            : ['cost' => 12];

        return password_hash($password, $algo, $options);
    }

    public static function isLegacyMd5Hash(string $hash): bool
    {
        return (bool) preg_match('/^[a-f0-9]{32}$/', $hash);
    }

    /**
     * Verify a password against a stored hash. Understands both modern
     * password_hash() output and the legacy raw-MD5 scheme used by
     * Selfauth < 2.0, so existing installs don't get locked out during
     * upgrade.
     */
    public static function verify(string $password, string $storedHash, string $userUrl, string $appKey): bool
    {
        if (self::isLegacyMd5Hash($storedHash)) {
            $normalizedUser = trim(preg_replace('/^https?:\/\//', '', $userUrl), '/');
            $legacyHash = md5($normalizedUser . $password . $appKey);
            return hash_equals($storedHash, $legacyHash);
        }

        return password_verify($password, $storedHash);
    }

    public static function needsRehash(string $storedHash): bool
    {
        if (self::isLegacyMd5Hash($storedHash)) {
            return true;
        }
        return password_needs_rehash($storedHash, self::algo());
    }

    // Signed codes always have a time-to-live, by default 5 minutes for
    // auth codes, 2 minutes for CSRF tokens, or 1 year for anything else
    // (kept for backwards API compatibility). HMAC-SHA256 was already a
    // solid, modern MAC construction, so this part is left as-is.
    public static function createSignedCode(string $key, string $message, int $ttl = 31536000, string $appendedData = ''): string
    {
        $expires = time() + $ttl;
        $body = $message . $expires . $appendedData;
        $signature = hash_hmac('sha256', $body, $key);
        return dechex($expires) . ':' . $signature . ':' . self::base64UrlEncode($appendedData);
    }

    public static function verifySignedCode(string $key, string $message, string $code): bool
    {
        $codeParts = explode(':', $code, 3);
        if (count($codeParts) !== 3) {
            return false;
        }
        $expires = hexdec($codeParts[0]);
        if (time() > $expires) {
            return false;
        }
        $body = $message . $expires . self::base64UrlDecode($codeParts[2]);
        $signature = hash_hmac('sha256', $body, $key);
        return hash_equals($signature, $codeParts[1]);
    }

    // URL Safe Base64 per https://tools.ietf.org/html/rfc7515#appendix-C
    public static function base64UrlEncode(?string $string): string
    {
        $string = base64_encode($string ?? '');
        $string = rtrim($string, '=');
        return strtr($string, '+/', '-_');
    }

    public static function base64UrlDecode(string $string): string
    {
        $string = strtr($string, '-_', '+/');
        $padding = strlen($string) % 4;
        if ($padding !== 0) {
            $string .= str_repeat('=', 4 - $padding);
        }
        return (string) base64_decode($string);
    }
}
