<?php

require_once __DIR__ . '/../src/bootstrap.php';

use Selfauth\MicropubException;
use Selfauth\MicropubHttp;

MicropubHttp::start();
$pdo = $GLOBALS['selfauth_pdo'];
$tokenRow = MicropubHttp::authenticate($pdo);

MicropubHttp::guarded(function () use ($tokenRow) {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Allow: POST, OPTIONS');
        throw new MicropubException(405, 'invalid_request', 'Use POST');
    }
    MicropubHttp::requireScope($tokenRow, ['media', 'create']);
    $urls = MicropubHttp::uploadsFrom(MicropubHttp::server(), 'file');
    if (count($urls) !== 1) {
        throw new MicropubException(400, 'invalid_request', 'Send one file in the "file" field');
    }
    header('Location: ' . $urls[0]);
    MicropubHttp::json(201, ['url' => $urls[0]]);
});
