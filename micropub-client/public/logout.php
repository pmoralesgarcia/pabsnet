<?php

require __DIR__ . '/../src/bootstrap.php';

use MicropubClient\ClientError;
use MicropubClient\Config;
use MicropubClient\IndieAuth;

Config::securityHeaders();
Config::startSession();

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        Config::checkCsrf();
        if (isset($_SESSION['user'])) {
            IndieAuth::revoke($_SESSION['user']);
        }
        $_SESSION = [];
        session_regenerate_id(true);
    }
} catch (ClientError $error) {
    $_SESSION['flash'] = ['error', $error->getMessage()];
}
header('Location: ./', true, 303);
