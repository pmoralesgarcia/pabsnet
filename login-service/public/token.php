<?php

require_once __DIR__ . '/../src/bootstrap.php';

use Login\Config;
use Login\Store;
use Login\Urls;

// Sites call this from their servers, and browser-based apps hold no secret here: PKCE protects the code.
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');
header('Cache-Control: no-store');

$respond = function (int $status, array $body) {
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
};
$method = $_SERVER['REQUEST_METHOD'] ?? '';
if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if ($method !== 'POST') {
    header('Allow: POST');
    $respond(405, ['error' => 'invalid_request', 'error_description' => 'Use POST']);
}
if (!Config::allow('token', 60, 60)) {
    $respond(429, ['error' => 'slow_down', 'error_description' => 'Too many requests']);
}
// IndieLogin leaves grant_type out and the IndieAuth spec requires it, so accept both.
if (isset($_POST['grant_type']) && $_POST['grant_type'] !== 'authorization_code') {
    $respond(400, ['error' => 'unsupported_grant_type']);
}
foreach (['code', 'client_id', 'redirect_uri', 'code_verifier'] as $field) {
    if (!is_string($_POST[$field] ?? null) || $_POST[$field] === '') {
        $respond(400, ['error' => 'invalid_request', 'error_description' => $field . ' is required']);
    }
}
$clientId = Urls::client($_POST['client_id']);
$row = $clientId === null ? null : Store::open()->redeem($_POST['code'], $clientId, $_POST['redirect_uri'], $_POST['code_verifier']);
if ($row === null) {
    $respond(400, ['error' => 'invalid_grant', 'error_description' => 'The code is invalid, expired, already used, or does not match']);
}
$respond(200, array_filter(['me' => $row['me'], 'profile' => $row['profile']], fn($v) => $v !== null));
