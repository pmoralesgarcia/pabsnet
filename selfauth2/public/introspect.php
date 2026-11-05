<?php

require_once __DIR__ . '/../src/bootstrap.php';

use Selfauth\AccessTokens;
use Selfauth\ApiToken;
use Selfauth\AuthRequest;
use Selfauth\RateLimiter;
use Selfauth\Support;

/*
 * IndieAuth token introspection (RFC 7662 plus the required `me`). The caller
 * authorizes with a bearer token: either an active access token, or an API
 * token with the tokens:introspect scope (create one under Admin > API Tokens).
 */

AuthRequest::noStore();
header('Content-Type: application/json');

function introspect_respond(int $status, array $body): never
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    introspect_respond(405, ['error' => 'invalid_request']);
}

$pdo = $GLOBALS['selfauth_pdo'];
if (!(new RateLimiter($pdo))->allow('introspect:' . Support::clientIp(), 120, 60)) {
    introspect_respond(429, ['error' => 'slow_down']);
}

$bearer = AccessTokens::bearer();
$authorized = $bearer !== null && (
    AccessTokens::authenticate($pdo, $bearer) !== null
    || (new ApiToken($pdo))->authenticate($bearer, 'tokens:introspect') !== null
);
if (!$authorized) {
    header('WWW-Authenticate: Bearer');
    introspect_respond(401, ['error' => 'invalid_token']);
}

$token = (string) ($_POST['token'] ?? '');
if ($token === '') {
    introspect_respond(400, ['error' => 'invalid_request', 'error_description' => 'token is required']);
}

$row = AccessTokens::find($pdo, $token);
introspect_respond(200, $row === null ? ['active' => false] : AccessTokens::introspection($row));
