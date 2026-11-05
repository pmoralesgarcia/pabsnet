<?php

require_once __DIR__ . '/../src/bootstrap.php';

use Selfauth\Micropub;
use Selfauth\MicropubException;
use Selfauth\MicropubHttp;
use Selfauth\Scopes;

MicropubHttp::start();
$pdo = $GLOBALS['selfauth_pdo'];
$tokenRow = MicropubHttp::authenticate($pdo);
$method = $_SERVER['REQUEST_METHOD'] ?? '';

MicropubHttp::guarded(function () use ($tokenRow, $method) {
    if ($method === 'GET') {
        $query = (string) ($_GET['q'] ?? '');
        if ($query === 'config') {
            $media = Micropub::mediaConfigured() ? ['media-endpoint' => rtrim(SELFAUTH_APP_URL, '/') . '/micropub-media.php'] : [];
            MicropubHttp::json(200, $media + [
                'syndicate-to' => [],
                'q' => ['config', 'source', 'syndicate-to'],
                'post-types' => [
                    ['type' => 'note', 'name' => 'Note'],
                    ['type' => 'article', 'name' => 'Article'],
                    ['type' => 'photo', 'name' => 'Photo'],
                    ['type' => 'like', 'name' => 'Like'],
                    ['type' => 'reply', 'name' => 'Reply'],
                    ['type' => 'repost', 'name' => 'Repost'],
                    ['type' => 'bookmark', 'name' => 'Bookmark'],
                ],
            ]);
        }
        if ($query === 'syndicate-to') {
            MicropubHttp::json(200, ['syndicate-to' => []]);
        }
        if ($query === 'source') {
            $url = (string) ($_GET['url'] ?? '');
            if ($url === '') {
                throw new MicropubException(400, 'invalid_request', 'url is required');
            }
            $only = array_map('strval', (array) ($_GET['properties'] ?? []));
            MicropubHttp::json(200, MicropubHttp::server()->source($url, $only));
        }
        throw new MicropubException(400, 'invalid_request', 'Unknown query');
    }

    if ($method !== 'POST') {
        header('Allow: GET, POST, OPTIONS');
        throw new MicropubException(405, 'invalid_request', 'Use GET or POST');
    }

    $contentType = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]));
    $isJson = $contentType === 'application/json';
    if ($isJson) {
        $input = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($input)) {
            throw new MicropubException(400, 'invalid_request', 'The request body is not valid JSON');
        }
    } else {
        $input = $_POST;
    }

    $server = MicropubHttp::server();
    $action = $input['action'] ?? null;

    if ($action !== null) {
        $url = (string) ($input['url'] ?? '');
        if ($url === '') {
            throw new MicropubException(400, 'invalid_request', 'url is required');
        }
        if ($action === 'delete') {
            MicropubHttp::requireScope($tokenRow, ['delete']);
            $server->delete($url);
            http_response_code(204);
            return;
        }
        if ($action === 'update') {
            MicropubHttp::requireScope($tokenRow, ['update']);
            if (!$isJson) {
                throw new MicropubException(400, 'invalid_request', 'Updates must be sent as JSON');
            }
            $server->update($url, array_intersect_key($input, array_flip(['replace', 'add', 'delete'])));
            http_response_code(204);
            return;
        }
        throw new MicropubException(400, 'invalid_request', 'Unsupported action');
    }

    MicropubHttp::requireScope($tokenRow, ['create', 'draft']);
    $mf2 = $isJson ? $input : Micropub::fromForm($input);
    if (!is_array($mf2['properties'] ?? null)) {
        throw new MicropubException(400, 'invalid_request', 'properties is required');
    }
    if (!in_array('create', Scopes::parse($tokenRow['scope']), true)) {
        $mf2['properties']['post-status'] = ['draft']; // A draft-only token can never publish.
    }
    if (!$isJson) {
        foreach (['photo', 'video', 'audio'] as $field) {
            $urls = MicropubHttp::uploadsFrom($server, $field);
            if ($urls !== []) {
                $mf2['properties'][$field] = array_merge($mf2['properties'][$field] ?? [], $urls);
            }
        }
    }
    $url = $server->create($mf2);
    // 202: the post exists once the commit has deployed, which takes a moment.
    header('Location: ' . $url);
    MicropubHttp::json(202, ['url' => $url]);
});
