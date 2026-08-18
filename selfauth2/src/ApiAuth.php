<?php

namespace Selfauth;

class ApiAuth
{
    /**
     * Reads the Authorization: Bearer header, validates it against the
     * given scope, applies a per-token rate limit, and either returns the
     * token row or sends a 401/429 JSON error response and exits.
     */
    public static function requireScope(\PDO $pdo, string $scope): array
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if ($header === '' && function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            $header = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        }

        if (!preg_match('/^Bearer\s+(\S+)$/i', $header, $m)) {
            self::deny(401, 'Missing or malformed Authorization: Bearer header');
        }
        $token = $m[1];

        $limiter = new RateLimiter($pdo);
        if (!$limiter->allow('api:' . hash('sha256', $token), 120, 60)) {
            self::deny(429, 'Rate limit exceeded');
        }

        $apiTokens = new ApiToken($pdo);
        $row = $apiTokens->authenticate($token, $scope);
        if ($row === null) {
            self::deny(403, 'Invalid, expired, revoked, or insufficiently-scoped token');
        }

        return $row;
    }

    private static function deny(int $status, string $message): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode(['error' => $message]);
        exit;
    }
}
