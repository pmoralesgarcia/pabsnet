<?php

require_once __DIR__ . '/../src/bootstrap.php';

use Selfauth\Support;
use Selfauth\Webmention;

if (!SELFAUTH_WEBMENTIONS_ENABLED) {
    Support::errorPage('Not Enabled', 'The webmention receiver is disabled on this endpoint.', '404 Not Found');
}
if (SELFAUTH_USER_URL === '') {
    Support::errorPage('Configuration Error', 'Endpoint not yet configured.');
}

$pdo = $GLOBALS['selfauth_pdo'];
$webmention = new Webmention($pdo, SELFAUTH_USER_URL);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// ---------------------------------------------------------------------
// GET /webmention.php?target=https://example.com/post
// A small public read API, similar to webmention.io's /api/mentions,
// returning verified mentions for a page in a jf2-ish JSON shape.
// ---------------------------------------------------------------------
if ($method === 'GET') {
    $target = filter_input(INPUT_GET, 'target', FILTER_VALIDATE_URL);
    if (!$target) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Provide a ?target= URL to fetch verified mentions for.']);
        exit;
    }

    $mentions = $webmention->forTarget($target);
    $children = array_map(static function (array $m): array {
        return [
            'type' => 'entry',
            'wm-property' => 'mention-of',
            'author' => [
                'type' => 'card',
                'name' => $m['author_name'],
                'photo' => $m['author_photo'],
                'url' => $m['author_url'],
            ],
            'url' => $m['source'],
            'name' => $m['title'],
            'content' => $m['content'] !== null ? ['text' => $m['content']] : null,
            'published' => $m['published_at'],
            'wm-target' => $m['target'],
            'wm-received' => $m['fetched_at'],
        ];
    }, $mentions);

    header('Content-Type: application/json');
    echo json_encode(['children' => $children]);
    exit;
}

// ---------------------------------------------------------------------
// POST source=...&target=...
// The actual Webmention receiver, per https://www.w3.org/TR/webmention/
// ---------------------------------------------------------------------
if ($method === 'POST') {
    $source = filter_input(INPUT_POST, 'source', FILTER_VALIDATE_URL);
    $target = filter_input(INPUT_POST, 'target', FILTER_VALIDATE_URL);

    if (!$source || !$target) {
        Support::errorPage('Bad Request', 'Both "source" and "target" parameters are required and must be valid URLs.', '400 Bad Request');
    }

    $result = $webmention->receive($source, $target);
    if (!$result['ok']) {
        Support::errorPage('Bad Request', $result['error'], '400 Bad Request');
    }

    // Best-effort synchronous verification with a short timeout so
    // well-behaved senders get an immediate accurate status. If it
    // doesn't finish in time it stays "pending" and bin/verify-mentions.php
    // (run via cron) will pick it up later, per the Webmention spec's
    // recommendation to verify asynchronously.
    $webmention->verify($result['id'], 5);

    $protocol = $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1';
    header($protocol . ' 202 Accepted');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'accepted', 'location' => null]);
    exit;
}

Support::errorPage('Method Not Allowed', 'Only GET and POST are supported.', '405 Method Not Allowed');
