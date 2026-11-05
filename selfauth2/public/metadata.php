<?php

require_once __DIR__ . '/../src/bootstrap.php';

use Selfauth\Scopes;

// IndieAuth server metadata. Clients find this via
// <link rel="indieauth-metadata"> on the owner's homepage.
$endpoint = SELFAUTH_APP_URL;
$base = rtrim($endpoint, '/') . '/';
$scopes = array_values(array_filter(array_keys(Scopes::catalog()), [Scopes::class, 'allowed']));

header('Content-Type: application/json');
header('Cache-Control: public, max-age=3600');
header('X-Content-Type-Options: nosniff');
echo json_encode([
    'issuer' => $endpoint,
    'authorization_endpoint' => $endpoint,
    'token_endpoint' => $base . 'token.php',
    'introspection_endpoint' => $base . 'introspect.php',
    'introspection_endpoint_auth_methods_supported' => ['Bearer'],
    'revocation_endpoint' => $base . 'revoke.php',
    'revocation_endpoint_auth_methods_supported' => ['none'],
    'code_challenge_methods_supported' => ['S256'],
    'response_types_supported' => ['code'],
    'grant_types_supported' => ['authorization_code'],
    'scopes_supported' => $scopes,
    'authorization_response_iss_parameter_supported' => true,
    'service_documentation' => 'https://indieauth.spec.indieweb.org/',
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
