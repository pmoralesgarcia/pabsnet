<?php

require_once __DIR__ . '/../src/bootstrap.php';

use Selfauth\OidcClient;
use Selfauth\Session;
use Selfauth\Support;

$purpose = filter_input(INPUT_GET, 'purpose', FILTER_UNSAFE_RAW);
$purpose = in_array($purpose, ['login', 'admin'], true) ? $purpose : 'login';

if ($purpose === 'admin') {
    if (!SELFAUTH_OIDC_ADMIN_ENABLED) {
        Support::errorPage('Not Available', 'External (OIDC) admin login is not enabled on this instance.', '404 Not Found');
    }
    if (Session::isAuthenticated()) {
        header('Location: admin/index.php');
        exit;
    }
    $indieauthParams = [];
} else {
    if (!SELFAUTH_LOGIN_OIDC_ENABLED) {
        Support::errorPage('Not Available', 'External (OIDC) sign-in is not enabled on this instance.', '404 Not Found');
    }

    // Re-validate exactly like index.php does -- these values came back
    // to us as a link from that already-validated page, but never trust
    // GET params without checking them again at the point they're used.
    $client_id = filter_input(INPUT_GET, 'client_id', FILTER_VALIDATE_URL);
    $redirect_uri = filter_input(INPUT_GET, 'redirect_uri', FILTER_VALIDATE_URL);
    $state = Support::filterInputRegexp(INPUT_GET, 'state', '@^[\x20-\x7E]*$@');
    $scope = Support::filterInputRegexp(INPUT_GET, 'scope', '@^([\x21\x23-\x5B\x5D-\x7E]+( [\x21\x23-\x5B\x5D-\x7E]+)*)?$@');

    if (!is_string($client_id) || !is_string($redirect_uri) || $state === false || $scope === false) {
        Support::errorPage('Faulty Request', 'The sign-in request is missing or has invalid client_id/redirect_uri/state/scope parameters.');
    }

    $pdo = $GLOBALS['selfauth_pdo'];
    $blocklist = new \Selfauth\Blocklist($pdo);
    if ($blocklist->isClientBlocked($client_id) || $blocklist->isRedirectBlocked($redirect_uri)) {
        Support::errorPage('Forbidden', 'This client application or redirect destination has been blocked by the endpoint owner.', '403 Forbidden');
    }

    $indieauthParams = [
        'client_id' => $client_id,
        'redirect_uri' => $redirect_uri,
        'state' => $state === '' ? null : $state,
        'scope' => $scope === '' ? null : $scope,
    ];
}

$client = OidcClient::fromEnv($GLOBALS['selfauth_pdo'], $GLOBALS['selfauth_settings']);

try {
    $url = $client->buildAuthorizationUrl($purpose, $indieauthParams);
} catch (\Throwable $e) {
    Support::errorPage('OIDC Error', 'Could not start SSO login: ' . $e->getMessage());
}

header('Location: ' . $url, true, 302);
exit;
