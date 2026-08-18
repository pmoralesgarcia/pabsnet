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

// purpose === 'login': resume the IndieAuth flow exactly as the password
// branch of index.php does -- issue a signed code and bounce back to the
// client application's redirect_uri.
$params = $result['indieauth_params'] ?? null;
if (!is_array($params) || !isset($params['client_id'], $params['redirect_uri'])) {
    Support::errorPage('Session Expired', 'Your sign-in session expired before SSO completed; please try again.');
}

$client_id = $params['client_id'];
$redirect_uri = $params['redirect_uri'];
$state_param = $params['state'] ?? null;
$scope = $params['scope'] ?? null;

$signInLog = new SignInLog($pdo);
$clientIp = Support::clientIp();

$signedCode = Auth::createSignedCode(SELFAUTH_APP_KEY, SELFAUTH_USER_URL . $redirect_uri . $client_id, 5 * 60, $scope ?? '');

$final_redir = $redirect_uri;
$final_redir .= (strpos($redirect_uri, '?') === false) ? '?' : '&';
$parameters = ['code' => $signedCode, 'me' => SELFAUTH_USER_URL];
if ($state_param !== null) {
    $parameters['state'] = $state_param;
}
$final_redir .= http_build_query($parameters);

$signInLog->record($client_id, $redirect_uri, $scope, $clientIp, $_SERVER['HTTP_USER_AGENT'] ?? null, true);

EventBus::fire($pdo, 'signin.success', [
    'client_id' => $client_id,
    'redirect_uri' => $redirect_uri,
    'scope' => $scope,
    'ip' => $clientIp,
    'method' => 'oidc',
    'identity' => (string) $identity,
], 'Successful sign-in (SSO) from ' . $clientIp . ' for client ' . $client_id);

if (function_exists('syslog') && getenv('SELFAUTH_SYSLOG_SUCCESS') === 'true') {
    syslog(LOG_INFO, sprintf('IndieAuth: SSO login from %s for %s', $clientIp, SELFAUTH_USER_URL));
}

header('Location: ' . $final_redir, true, 302);
exit;
