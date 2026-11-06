<?php

require __DIR__ . '/../src/bootstrap.php';

use MicropubClient\ClientError;
use MicropubClient\Config;
use MicropubClient\Discovery;
use MicropubClient\IndieAuth;

Config::securityHeaders();
Config::startSession();

try {
    if (!Config::allow('login', 20, 60)) {
        throw new ClientError('Too many sign-in attempts. Wait a minute and try again.');
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        Config::checkCsrf();
        $me = is_string($_POST['me'] ?? null) ? $_POST['me'] : '';
    } else {
        // A plain link can start sign-in for one configured site only. For anyone else it
        // would let a stranger's link sign a visitor in to the stranger's own website.
        $me = is_string($_GET['me'] ?? null) ? $_GET['me'] : '';
        $oneClick = Config::oneClickMe();
        if ($oneClick === null || Discovery::canonicalize($me) !== $oneClick) {
            header('Location: ./', true, 303);
            exit;
        }
    }
    $target = IndieAuth::begin($me);
    header('Location: ' . $target, true, 303);
} catch (ClientError $error) {
    $_SESSION['flash'] = ['error', $error->getMessage()];
    header('Location: ./', true, 303);
}
