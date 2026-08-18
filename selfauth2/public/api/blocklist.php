<?php

require_once __DIR__ . '/../../src/bootstrap.php';

use Selfauth\ApiAuth;
use Selfauth\Blocklist;

header('Content-Type: application/json');

$pdo = $GLOBALS['selfauth_pdo'];
$method = $_SERVER['REQUEST_METHOD'];
$blocklist = new Blocklist($pdo);

if ($method === 'GET') {
    ApiAuth::requireScope($pdo, 'blocklist:read');
    echo json_encode(['blocklist' => $blocklist->all()]);
    exit;
}

if ($method === 'POST') {
    ApiAuth::requireScope($pdo, 'blocklist:write');
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $type = $input['type'] ?? null;
    $pattern = $input['pattern'] ?? null;
    $note = $input['note'] ?? null;

    if (!in_array($type, ['client_id', 'redirect_uri', 'ip'], true) || !is_string($pattern) || trim($pattern) === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Provide type (client_id|redirect_uri|ip) and a non-empty pattern']);
        exit;
    }
    $blocklist->add($type, $pattern, is_string($note) ? $note : null);
    http_response_code(201);
    echo json_encode(['status' => 'created']);
    exit;
}

if ($method === 'DELETE') {
    ApiAuth::requireScope($pdo, 'blocklist:write');
    $id = (int) filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Provide ?id= of the blocklist entry to remove']);
        exit;
    }
    $blocklist->remove($id);
    echo json_encode(['status' => 'deleted']);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Only GET, POST, DELETE are supported']);
