<?php

require_once __DIR__ . '/../../src/bootstrap.php';

use Selfauth\Session;
use Selfauth\Support;

Session::start();

/** The sections inside Settings. All of them are owner-only. */
function admin_settings_items(): array
{
    return [
        'settings.php?section=general' => 'General',
        'settings.php?section=security' => 'Login & security',
        'admins.php' => 'Admins',
        'api-tokens.php' => 'API tokens',
        'webhooks.php' => 'Webhooks',
        'settings.php?section=feeds' => 'Feeds',
        'settings.php?section=appearance' => 'Appearance',
    ];
}

function admin_nav_items(): array
{
    $items = [
        'index.php' => ['Apps & sign-ins', 'activity'],
        'mentions.php' => ['Webmentions', 'mention'],
    ];
    if (Session::hasRole('manager')) {
        $items['blocklist.php'] = ['Blocklist', 'block'];
    }
    $client = \Selfauth\AuthRequest::trustedClientId();
    if ($client !== null && Session::hasRole('owner')) {
        $items[$client . 'login.php?me=' . rawurlencode(SELFAUTH_USER_URL)] = ['Post', 'post'];
    }
    if (Session::hasRole('owner')) {
        $items['settings.php'] = ['Settings', 'settings'];
    }
    return $items;
}

function admin_icon(string $name): string
{
    $paths = [
        'activity' => '<path d="M3 12h4l3 8 4-16 3 8h4"/>',
        'mention' => '<path d="M21 12a9 9 0 1 0-4 7.5"/><circle cx="12" cy="12" r="3.5"/><path d="M15.5 8.5V13a2.75 2.75 0 0 0 5.5 0v-1"/>',
        'block' => '<circle cx="12" cy="12" r="9"/><path d="m5.7 5.7 12.6 12.6"/>',
        'post' => '<path d="M4 20h4L19 9l-4-4L4 16z"/><path d="m13 7 4 4"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M12 3v3m0 12v3M3 12h3m12 0h3M5.6 5.6l2.1 2.1m8.6 8.6 2.1 2.1M5.6 18.4l2.1-2.1m8.6-8.6 2.1-2.1"/>',
        'logout' => '<path d="M15 4h4a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-4"/><path d="M10 8l-4 4 4 4M6 12h10"/>',
    ];
    return '<svg class="icon" viewBox="0 0 24 24" aria-hidden="true">' . ($paths[$name] ?? '') . '</svg>';
}

function admin_nav(string $active): string
{
    $name = trim((string) $GLOBALS['selfauth_settings']->get('profile_name', ''));
    $site = (string) preg_replace('#^https?://#', '', rtrim(SELFAUTH_USER_URL, '/'));
    $photo = trim((string) $GLOBALS['selfauth_settings']->get('profile_photo', ''));
    $inSettings = isset(admin_settings_items()[$active]);

    $html = '<aside class="sidebar"><div class="account">';
    $html .= $photo !== ''
        ? '<img class="avatar" src="' . Support::e($photo) . '" alt="">'
        : '<span class="avatar">' . Support::e(mb_strtoupper(mb_substr($name !== '' ? $name : $site, 0, 1))) . '</span>';
    $html .= '<span class="account-text"><b>' . Support::e($name !== '' ? $name : $site) . '</b><small>';
    $html .= Session::role() === 'owner' ? Support::e($site) : Support::e(ucfirst(Session::role()));
    $html .= '</small></span></div><nav class="nav" aria-label="Main">';
    foreach (admin_nav_items() as $href => [$label, $icon]) {
        $isActive = $href === $active || ($href === 'settings.php' && $inSettings);
        $html .= '<a href="' . Support::e($href) . '"' . ($isActive ? ' class="active" aria-current="page"' : '') . '>' . admin_icon($icon) . Support::e($label) . '</a>';
    }
    return $html . '</nav><a class="logout" href="logout.php">' . admin_icon('logout') . 'Log out</a></aside>';
}

function admin_header(string $title, string $active): void
{
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . Support::e($title) . ' - Selfauth Admin</title>';
    echo '<link rel="stylesheet" href="assets/style.css">';
    if (trim(SELFAUTH_CUSTOM_CSS) !== '') {
        echo '<link rel="stylesheet" href="../custom.css.php">';
    }
    echo '</head><body>';
    // The login page has no menu: there is nothing to navigate to while signed out.
    if (!Session::isAuthenticated()) {
        echo '<main class="plain">';
        return;
    }
    echo '<div class="layout">' . admin_nav($active) . '<div class="page">';
    if (isset(admin_settings_items()[$active])) {
        $GLOBALS['admin_in_settings'] = true;
        echo '<header class="page-title"><h1>Settings</h1></header><div class="settings"><nav class="subnav" aria-label="Settings">';
        foreach (admin_settings_items() as $href => $label) {
            echo '<a href="' . Support::e($href) . '"' . ($href === $active ? ' class="active" aria-current="page"' : '') . '>' . Support::e($label) . '</a>';
        }
        echo '</nav><main class="settings-body">';
        return;
    }
    echo '<main>';
}

function admin_footer(): void
{
    if (!Session::isAuthenticated()) {
        echo '</main></body></html>';
        return;
    }
    echo !empty($GLOBALS['admin_in_settings']) ? '</main></div></div></div></body></html>' : '</main></div></div></body></html>';
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

/**
 * Require the session to have at least $minimum role ('viewer' <
 * 'manager' < 'owner'), else respond 403. Always call this server-side
 * for every state-changing action -- hiding a button in the UI is not
 * access control.
 */
function admin_require_role(string $minimum): void
{
    if (!Session::hasRole($minimum)) {
        Support::errorPage('Forbidden', 'Your admin role (' . Session::role() . ') does not have permission to do this.', '403 Forbidden');
    }
}
