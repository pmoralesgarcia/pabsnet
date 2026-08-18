<?php

require_once __DIR__ . '/../src/bootstrap.php';

use Selfauth\Rss;
use Selfauth\Support;
use Selfauth\Webmention;

if (!SELFAUTH_WEBMENTIONS_ENABLED) {
    Support::errorPage('Not Available', 'The webmention receiver (and its feed) is disabled on this instance.', '404 Not Found');
}

$settings = $GLOBALS['selfauth_settings'];
$public = filter_var(getenv('SELFAUTH_WEBMENTION_FEED_PUBLIC') ?: 'false', FILTER_VALIDATE_BOOLEAN);

if (!$public) {
    // Secure by default: require a per-install secret token in the URL,
    // generated on first use and manageable/regeneratable from the admin
    // Settings page, rather than exposing everyone who's mentioned you
    // (and their content) to the open internet.
    $token = $settings->get('webmention_feed_token');
    if ($token === null) {
        $token = bin2hex(random_bytes(24));
        $settings->set('webmention_feed_token', $token);
    }
    $given = filter_input(INPUT_GET, 'token', FILTER_UNSAFE_RAW) ?? '';
    if (!hash_equals($token, (string) $given)) {
        Support::errorPage('Forbidden', 'This feed is private. Find your feed URL (with token) in the admin Settings page, or set SELFAUTH_WEBMENTION_FEED_PUBLIC=true to make it public.', '403 Forbidden');
    }
}

$pdo = $GLOBALS['selfauth_pdo'];
$webmention = new Webmention($pdo, SELFAUTH_USER_URL);
$mentions = $webmention->byStatus('verified', 50);

$items = array_map(static function (array $m): array {
    $title = $m['title'] ?: ('Mention from ' . parse_url($m['source'], PHP_URL_HOST));
    $desc = trim(($m['author_name'] ? $m['author_name'] . ': ' : '') . ($m['content'] ?? ''));
    return [
        'title' => $title,
        'link' => $m['source'],
        'description' => $desc !== '' ? $desc : ('Mentioned ' . $m['target']),
        'guid' => $m['source'] . '|' . $m['target'],
        'pubDate' => $m['published_at'] ?: $m['created_at'],
    ];
}, $mentions);

header('Content-Type: application/rss+xml; charset=UTF-8');
echo Rss::build([
    'title' => 'Webmentions for ' . SELFAUTH_USER_URL,
    'link' => SELFAUTH_USER_URL,
    'description' => 'Verified webmentions received via Selfauth',
], $items);
