<?php

require __DIR__ . '/../src/bootstrap.php';

use MicropubClient\ClientError;
use MicropubClient\Config;
use MicropubClient\IndieAuth;

Config::securityHeaders();
Config::startSession();

try {
    IndieAuth::finish($_GET);
} catch (ClientError $error) {
    $_SESSION['flash'] = ['error', $error->getMessage()];
}
header('Location: ./', true, 303);
