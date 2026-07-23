#!/usr/bin/env php
<?php

// Re-verifies pending webmentions in the background. Run this periodically
// via cron (or the cron loop baked into the Docker image, see
// docker/entrypoint.sh), e.g. every 5 minutes:
//
//   */5 * * * * php /app/bin/verify-mentions.php >> /proc/1/fd/1 2>&1

require_once __DIR__ . '/../src/bootstrap.php';

use Selfauth\Webmention;

if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "This script is intended to be run from the command line.\n");
    exit(1);
}

if (SELFAUTH_USER_URL === '') {
    fwrite(STDERR, "Selfauth is not configured yet, skipping.\n");
    exit(0);
}

$pdo = $GLOBALS['selfauth_pdo'];
$webmention = new Webmention($pdo, SELFAUTH_USER_URL);

$pending = $webmention->pending(100);
$verified = 0;

foreach ($pending as $row) {
    $ok = $webmention->verify((int) $row['id'], 10);
    if ($ok) {
        $verified++;
    }
}

echo sprintf("Checked %d pending webmention(s), %d verified.\n", count($pending), $verified);
