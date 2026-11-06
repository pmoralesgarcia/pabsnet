<?php

require __DIR__ . '/../src/bootstrap.php';

use Login\AuthRequest;
use Login\ClientError;
use Login\Config;
use Login\IndieAuth;
use Login\Store;

Config::securityHeaders();
Config::startSession();

try {
    $result = IndieAuth::finish($_GET);
} catch (ClientError $problem) {
    error_page($problem->getMessage());
}

if ($result['context']['type'] === 'register') {
    session_regenerate_id(true);
    $_SESSION['owner'] = $result['me'];
    header('Location: /register', true, 303);
    exit;
}

$request = $result['context']['request'];
if ($request['me'] !== null && $request['me'] !== $result['me']) {
    AuthRequest::redirectError($request['redirect_uri'], 'access_denied', 'You signed in as a different website than the one requested', $request['state']);
}
$code = Store::open()->issueCode($request, $result['me'], $request['scope'] === 'profile' ? $result['profile'] : null);
header('Location: ' . AuthRequest::backTo($request['redirect_uri'], ['code' => $code, 'state' => $request['state']]), true, 303);
