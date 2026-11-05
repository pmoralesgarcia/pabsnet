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

    // The consent page (index.php) stored the validated request and the
    // scopes the owner chose in the session. Nothing from the query string
    // is trusted here.
    Session::start();
    $pending = $_SESSION['ia_pending'] ?? null;
    unset($_SESSION['ia_pending']);
    if (!is_array($pending) || empty($pending['client_id']) || empty($pending['redirect_uri']) || (time() - (int) ($pending['created'] ?? 0)) > 600) {
        Support::errorPage('Sign-in expired', 'Start the sign-in again from the app you were using.');
    }

    $pdo = $GLOBALS['selfauth_pdo'];
    $blocklist = new \Selfauth\Blocklist($pdo);
    if ($blocklist->isClientBlocked($pending['client_id']) || $blocklist->isRedirectBlocked($pending['redirect_uri'])) {
        Support::errorPage('Access denied', 'This app or return address has been blocked by the site owner.', '403 Forbidden');
    }

    $indieauthParams = $pending;
}

$client = OidcClient::fromEnv($GLOBALS['selfauth_pdo'], $GLOBALS['selfauth_settings']);

try {
    $url = $client->buildAuthorizationUrl($purpose, $indieauthParams);
} catch (\Throwable $e) {
    Support::errorPage('OIDC Error', 'Could not start SSO login: ' . $e->getMessage());
}

header('Location: ' . $url, true, 302);
exit;
