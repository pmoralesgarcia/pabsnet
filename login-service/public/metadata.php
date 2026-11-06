<?php

require __DIR__ . '/../src/bootstrap.php';

use Login\Config;

$url = Config::issuer();
header('Content-Type: application/json');
header('Cache-Control: public, max-age=3600');
header('X-Content-Type-Options: nosniff');
echo json_encode([
    'issuer' => $url,
    'authorization_endpoint' => $url . 'authorize',
    'token_endpoint' => $url . 'token',
    'code_challenge_methods_supported' => ['S256'],
    'response_types_supported' => ['code'],
    'grant_types_supported' => ['authorization_code'],
    'scopes_supported' => ['profile'],
    'authorization_response_iss_parameter_supported' => true,
    'service_documentation' => $url,
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
