<?php

require_once __DIR__ . '/../src/bootstrap.php';

use Selfauth\AccessTokens;
use Selfauth\AuthRequest;
use Selfauth\RateLimiter;
use Selfauth\Support;

/*
 * IndieAuth token revocation (RFC 7009). Knowing the token is the only
 * authorization needed, and unknown tokens get the same answer as known ones.
 */

AuthRequest::noStore();
header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    echo json_encode(['error' => 'invalid_request']);
    exit;
}

$pdo = $GLOBALS['selfauth_pdo'];
if (!(new RateLimiter($pdo))->allow('revoke:' . Support::clientIp(), 60, 60)) {
    http_response_code(429);
    echo json_encode(['error' => 'slow_down']);
    exit;
}

$token = (string) ($_POST['token'] ?? '');
if ($token !== '') {
    AccessTokens::revoke($pdo, $token);
}
http_response_code(200);
echo '{}';
