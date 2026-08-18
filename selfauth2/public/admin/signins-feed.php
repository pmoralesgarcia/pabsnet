<?php

require_once __DIR__ . '/../../src/bootstrap.php';

use Selfauth\Rss;
use Selfauth\SignInLog;
use Selfauth\Support;

$settings = $GLOBALS['selfauth_settings'];

// This feed necessarily reveals attempted-login metadata (IPs, clients),
// so unlike the webmention feed there is no "public" option -- only a
// token, generated on first use and regeneratable from Settings.
$token = $settings->get('signin_feed_token');
if ($token === null) {
    $token = bin2hex(random_bytes(24));
    $settings->set('signin_feed_token', $token);
}
$given = filter_input(INPUT_GET, 'token', FILTER_UNSAFE_RAW) ?? '';
if (!hash_equals($token, (string) $given)) {
    Support::errorPage('Forbidden', 'This feed requires the token shown on the admin Settings page.', '403 Forbidden');
}

$pdo = $GLOBALS['selfauth_pdo'];
$log = new SignInLog($pdo);
$entries = $log->recent(50);

$items = array_map(static function (array $e): array {
    $status = $e['success'] ? 'Successful sign-in' : 'Failed/blocked sign-in';
    return [
        'title' => $status . ' from ' . ($e['ip'] ?? 'unknown IP'),
        'link' => (string) ($e['client_id'] ?? SELFAUTH_APP_URL),
        'description' => sprintf(
            'Client: %s | Redirect: %s | Scope: %s | IP: %s',
            $e['client_id'] ?? '(none)',
            $e['redirect_uri'] ?? '(none)',
            $e['scope'] ?? '(none)',
            $e['ip'] ?? '(unknown)'
        ),
        'guid' => 'signin-' . $e['id'],
        'pubDate' => $e['occurred_at'],
    ];
}, $entries);

header('Content-Type: application/rss+xml; charset=UTF-8');
echo Rss::build([
    'title' => 'Selfauth sign-in activity',
    'link' => rtrim(SELFAUTH_APP_URL, '/') . '/admin/',
    'description' => 'Recent sign-in attempts to this Selfauth instance',
], $items);
