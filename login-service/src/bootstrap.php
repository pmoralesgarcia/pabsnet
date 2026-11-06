<?php

spl_autoload_register(function ($class) {
    $prefix = 'Login\\';
    if (str_starts_with($class, $prefix)) {
        $file = __DIR__ . '/' . substr($class, strlen($prefix)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

ini_set('display_errors', '0');
date_default_timezone_set(getenv('TZ') ?: 'UTC');

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function page_start(string $title): void
{
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . e($title) . '</title><link rel="stylesheet" href="/style.css">';
    echo '<link rel="indieauth-metadata" href="' . e(\Login\Config::issuer()) . '.well-known/oauth-authorization-server">';
    echo '<link rel="redirect_uri" href="' . e(\Login\Config::callbackUri()) . '"></head><body><main>';
}

function page_end(): void
{
    // Identifies this service on the sign-in screens of the servers people use.
    echo '</main><footer class="h-app"><a class="u-url p-name" href="' . e(\Login\Config::issuer()) . '">Sign in with your website</a> uses <a href="https://indieauth.spec.indieweb.org/">IndieAuth</a>.</footer></body></html>';
}

function error_page(string $message, int $status = 400): never
{
    http_response_code($status);
    page_start('Could not sign you in');
    echo '<h1>Could not sign you in</h1><p class="msg error" role="alert">' . e($message) . '</p>';
    page_end();
    exit;
}
