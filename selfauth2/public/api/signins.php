<?php

require_once __DIR__ . '/../../src/bootstrap.php';

use Selfauth\ApiAuth;
use Selfauth\SignInLog;

header('Content-Type: application/json');

$pdo = $GLOBALS['selfauth_pdo'];
ApiAuth::requireScope($pdo, 'signins:read');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Only GET is supported']);
    exit;
}

$limit = min(500, max(1, (int) (filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT) ?: 100)));
$log = new SignInLog($pdo);

echo json_encode([
    'counts' => $log->counts(),
    'signins' => $log->recent($limit),
]);
