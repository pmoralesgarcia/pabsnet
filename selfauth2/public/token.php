<?php

require_once __DIR__ . '/../src/bootstrap.php';

use Selfauth\AccessTokens;
use Selfauth\AuthCodes;
use Selfauth\AuthRequest;
use Selfauth\Blocklist;
use Selfauth\ClientId;
use Selfauth\RateLimiter;
use Selfauth\Scopes;
use Selfauth\Support;

/*
 * IndieAuth token endpoint.
 *   POST grant_type=authorization_code  exchange a code for an access token
 *   POST action=revoke&token=...        legacy revocation (see revoke.php)
 *   GET with a bearer token             legacy verification for older clients
 *                                       (see introspect.php for the current way)
 */

AuthRequest::noStore();
header('Content-Type: application/json');

function token_respond(int $status, array $body): never
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_SLASHES);
    exit;
}

$pdo = $GLOBALS['selfauth_pdo'];
$clientIp = Support::clientIp();

if ((new Blocklist($pdo))->isIpBlocked($clientIp)) {
    token_respond(403, ['error' => 'access_denied']);
}
if (!(new RateLimiter($pdo))->allow('token_endpoint:' . $clientIp, 60, 60)) {
    token_respond(429, ['error' => 'slow_down', 'error_description' => 'Too many requests']);
}

$method = $_SERVER['REQUEST_METHOD'] ?? '';

if ($method === 'GET') {
    $bearer = AccessTokens::bearer();
    $row = $bearer === null ? null : AccessTokens::find($pdo, $bearer);
    if ($row === null) {
        header('WWW-Authenticate: Bearer');
        token_respond(401, ['error' => 'invalid_token']);
    }
    token_respond(200, ['me' => $row['me'], 'client_id' => $row['client_id'], 'scope' => $row['scope']]);
}

if ($method !== 'POST') {
    header('Allow: GET, POST');
    token_respond(405, ['error' => 'invalid_request']);
}

if (($_POST['action'] ?? '') === 'revoke') {
    AccessTokens::revoke($pdo, (string) ($_POST['token'] ?? ''));
    token_respond(200, []); // Same answer for unknown tokens (RFC 7009).
}

if (($_POST['grant_type'] ?? '') !== 'authorization_code') {
    token_respond(400, ['error' => 'unsupported_grant_type']);
}

$code = (string) ($_POST['code'] ?? '');
$clientId = ClientId::canonicalize((string) ($_POST['client_id'] ?? ''));
$redirect = (string) ($_POST['redirect_uri'] ?? '');
$verifier = isset($_POST['code_verifier']) ? (string) $_POST['code_verifier'] : null;
if ($clientId === null || $redirect === '' || !preg_match('/^[0-9a-f]{64}$/', $code)) {
    token_respond(400, ['error' => 'invalid_request', 'error_description' => 'code, client_id and redirect_uri are required']);
}

$row = AuthCodes::redeem($pdo, $code, $clientId, $redirect, $verifier);
if (!is_array($row)) {
    token_respond(400, ['error' => 'invalid_grant', 'error_description' => 'The code is invalid, expired, already used, or does not match']);
}
if ($row['code_challenge'] === null && AuthRequest::requirePkce()) {
    token_respond(400, ['error' => 'invalid_grant', 'error_description' => 'PKCE is required']);
}

// The spec forbids issuing a token for a code that carries no scope.
$granted = Scopes::parse($row['scope']);
if ($granted === []) {
    token_respond(400, ['error' => 'invalid_grant', 'error_description' => 'This code was not granted any scope, so it cannot be used for a token']);
}

$issued = AccessTokens::issue($pdo, $clientId, implode(' ', $granted), $row['me']);
$out = [
    'access_token' => $issued['token'],
    'token_type' => 'Bearer',
    'scope' => implode(' ', $granted),
    'me' => $row['me'],
    'expires_in' => $issued['expires_in'],
];
if (in_array('profile', $granted, true)) {
    $s = $GLOBALS['selfauth_settings'];
    $out['profile'] = array_filter([
        'name' => $s->get('profile_name'),
        'url' => $s->get('profile_url') ?: SELFAUTH_USER_URL,
        'photo' => $s->get('profile_photo'),
        'email' => in_array('email', $granted, true) ? $s->get('profile_email') : null,
    ], fn($v) => $v !== null && $v !== '');
}
token_respond(200, $out);
