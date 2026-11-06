<?php
// Stands in for the Dockerfile's rewrite rules under PHP's built-in server. Local tests only.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$routes = ['/authorize' => 'authorize.php', '/token' => 'token.php', '/callback' => 'callback.php', '/register' => 'register.php', '/.well-known/oauth-authorization-server' => 'metadata.php'];
if (isset($routes[$path])) {
    chdir(__DIR__ . '/../public');
    require __DIR__ . '/../public/' . $routes[$path];
    return true;
}
return false;
