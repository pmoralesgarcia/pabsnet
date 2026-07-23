<?php

require_once __DIR__ . '/../../src/bootstrap.php';

use Selfauth\Session;
use Selfauth\Support;

Session::start();

function admin_nav(string $active): string
{
    $items = [
        'index.php' => 'Sign-ins',
        'mentions.php' => 'Webmentions',
        'blocklist.php' => 'Blocklist',
        'settings.php' => 'Settings',
    ];
    $html = '<div class="nav"><b>Selfauth Admin</b>';
    foreach ($items as $href => $label) {
        $class = $href === $active ? ' class="active"' : '';
        $html .= '<a href="' . $href . '"' . $class . '>' . $label . '</a>';
    }
    $html .= '<a href="logout.php">Log out</a></div>';
    return $html;
}

function admin_header(string $title, string $active): void
{
    echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . Support::e($title) . ' - Selfauth Admin</title>';
    echo '<link rel="stylesheet" href="assets/style.css"></head><body>';
    echo admin_nav($active);
    echo '<main>';
}

function admin_footer(): void
{
    echo '</main></body></html>';
}

function admin_flash_message(): ?array
{
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function admin_set_flash(string $type, string $text): void
{
    $_SESSION['flash'] = ['type' => $type, 'text' => $text];
}

function admin_render_flash(): void
{
    $flash = admin_flash_message();
    if ($flash) {
        echo '<div class="msg ' . Support::e($flash['type']) . '">' . Support::e($flash['text']) . '</div>';
    }
}

function admin_csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . Support::e(Session::csrfToken()) . '">';
}

function admin_require_csrf(): void
{
    $token = filter_input(INPUT_POST, '_csrf', FILTER_UNSAFE_RAW);
    if (!Session::verifyCsrf($token)) {
        Support::errorPage('Invalid Request', 'CSRF check failed, please go back and try again.', '400 Bad Request');
    }
}
