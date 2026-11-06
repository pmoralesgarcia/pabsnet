<?php

spl_autoload_register(function ($class) {
    $prefix = 'MicropubClient\\';
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
    echo '<title>' . e($title) . '</title><link rel="stylesheet" href="style.css">';
    echo '<link rel="redirect_uri" href="' . e(\MicropubClient\Config::redirectUri()) . '"></head><body>';
}

function page_end(): void
{
    // Identifies this app on the sign-in screens of the servers people use.
    echo '<footer class="h-app"><a class="u-url p-name" href="' . e(\MicropubClient\Config::clientId()) . '">Post</a> is a <a href="https://indieweb.org/Micropub">Micropub</a> client. Posts go to your own website.</footer></body></html>';
}
