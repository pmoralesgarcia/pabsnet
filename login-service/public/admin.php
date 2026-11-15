<?php

require __DIR__ . '/../src/bootstrap.php';

use Login\ClientError;
use Login\Config;
use Login\IndieAuth;

// The admin's shortcut from the Selfauth portal. A plain link may start sign-in here because
// it can only ever sign in as the admin, which needs the admin's own server to agree.
Config::securityHeaders();
Config::startSession();

$admin = Config::admin();
if ($admin === null) {
    error_page('This service has no admin.', 404);
}
if (Config::isAdmin($_SESSION['owner'] ?? null)) {
    header('Location: /register', true, 303);
    exit;
}
try {
    if (!Config::allow('register', 20, 60)) {
        throw new ClientError('Too many attempts. Wait a minute and try again.');
    }
    header('Location: ' . IndieAuth::begin($admin, ['type' => 'register']), true, 303);
} catch (ClientError $problem) {
    error_page($problem->getMessage());
}
