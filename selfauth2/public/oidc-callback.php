<?php

require_once __DIR__ . '/../src/bootstrap.php';

use Selfauth\Auth;
use Selfauth\EventBus;
use Selfauth\OidcClient;
use Selfauth\Session;
use Selfauth\SignInLog;
use Selfauth\Support;

$pdo = $GLOBALS['selfauth_pdo'];

$error = filter_input(INPUT_GET, 'error', FILTER_UNSAFE_RAW);
if ($error !== null) {
    $description = filter_input(INPUT_GET, 'error_description', FILTER_UNSAFE_RAW);
    Support::errorPage('SSO Login Failed', trim($error . ': ' . ($description ?? '')), '400 Bad Request');
}

$code = filter_input(INPUT_GET, 'code', FILTER_UNSAFE_RAW);
$state = filter_input(INPUT_GET, 'state', FILTER_UNSAFE_RAW);

$client = OidcClient::fromEnv($pdo, $GLOBALS['selfauth_settings']);
if ($client === null) {
    Support::errorPage('Not Available', 'OIDC is not configured on this instance.', '404 Not Found');
}

try {
    $result = $client->handleCallback($code, $state);
} catch (\Throwable $e) {
    Support::errorPage('SSO Login Failed', $e->getMessage(), '403 Forbidden');
}

$claims = $result['claims'];
$identity = $claims['email'] ?? $claims['preferred_username'] ?? $claims['sub'];

if ($result['purpose'] === 'admin') {
    Session::login(SELFAUTH_USER_URL, 'oidc', (string) $identity, $result['role']);
    header('Location: admin/index.php');
    exit;
}

// purpose === 'login': resume the IndieAuth flow exactly like the password
// path in index.php -- issue a single-use PKCE-bound code and send the user
// back to the client.
$params = $result['indieauth_params'] ?? null;
if (!is_array($params) || !isset($params['client_id'], $params['redirect_uri'])) {
    Support::errorPage('Sign-in expired', 'Your sign-in expired before single sign-on finished. Start again from the app.');
}

\Selfauth\AuthRequest::complete($pdo, $params, (array) ($params['granted'] ?? []), 'oidc', (string) $identity);
