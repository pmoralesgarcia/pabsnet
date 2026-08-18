<?php

require_once __DIR__ . '/../../src/bootstrap.php';

use Selfauth\ApiAuth;
use Selfauth\Webmention;

header('Content-Type: application/json');

$pdo = $GLOBALS['selfauth_pdo'];
$method = $_SERVER['REQUEST_METHOD'];
$webmention = new Webmention($pdo, SELFAUTH_USER_URL);

if ($method === 'GET') {
    ApiAuth::requireScope($pdo, 'mentions:read');
    $status = filter_input(INPUT_GET, 'status', FILTER_UNSAFE_RAW) ?: 'all';
    $limit = min(500, max(1, (int) (filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT) ?: 100)));
    $mentions = $status === 'all' ? $webmention->all($limit) : $webmention->byStatus($status, $limit);
    echo json_encode(['counts' => $webmention->counts(), 'mentions' => $mentions]);
    exit;
}

if ($method === 'POST') {
    ApiAuth::requireScope($pdo, 'mentions:write');
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int) ($input['id'] ?? 0);
    $action = $input['action'] ?? null;

    if ($id <= 0 || !in_array($action, ['approve', 'spam', 'recheck', 'delete'], true)) {
        http_response_code(400);
        echo json_encode(['error' => 'Provide id and action (approve|spam|recheck|delete)']);
        exit;
    }

    if ($action === 'approve') {
        $webmention->setStatus($id, 'verified');
    } elseif ($action === 'spam') {
        $webmention->setStatus($id, 'spam');
    } elseif ($action === 'delete') {
        $webmention->delete($id);
    } elseif ($action === 'recheck') {
        $webmention->verify($id, 8);
    }
    echo json_encode(['status' => 'ok']);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Only GET and POST are supported']);
