<?php

namespace Selfauth;

spl_autoload_register(function ($class) {
    $prefix = 'Selfauth\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

date_default_timezone_set(getenv('TZ') ?: 'UTC');
error_reporting(E_ALL);
ini_set('display_errors', getenv('SELFAUTH_DEBUG') === 'true' ? '1' : '0');

// ---------------------------------------------------------------------
// Legacy config.php support: if a config.php exists (classic, non-Docker
// installs), pull its constants in as env-var equivalents so the rest of
// the app only ever has to deal with one configuration source.
// ---------------------------------------------------------------------
$legacyConfig = __DIR__ . '/../config.php';
if (is_file($legacyConfig)) {
    require_once $legacyConfig;
    if (defined('APP_URL') && getenv('SELFAUTH_APP_URL') === false) {
        putenv('SELFAUTH_APP_URL=' . APP_URL);
    }
    if (defined('APP_KEY') && getenv('SELFAUTH_APP_KEY') === false) {
        putenv('SELFAUTH_APP_KEY=' . APP_KEY);
    }
    if (defined('USER_URL') && getenv('SELFAUTH_USER_URL') === false) {
        putenv('SELFAUTH_USER_URL=' . USER_URL);
    }
    if (defined('USER_HASH') && getenv('SELFAUTH_LEGACY_PASSWORD_HASH') === false) {
        putenv('SELFAUTH_LEGACY_PASSWORD_HASH=' . USER_HASH);
    }
}

$dbPath = getenv('SELFAUTH_DB_PATH') ?: (__DIR__ . '/../data/selfauth.sqlite');
$pdo = Database::connect($dbPath);
$settings = new Settings($pdo);

// Seed settings from environment on first boot only; after that the DB
// (editable from the admin portal) is authoritative.
$settings->seedFromEnv('app_url', getenv('SELFAUTH_APP_URL') ?: null);
$settings->seedFromEnv('user_url', getenv('SELFAUTH_USER_URL') ?: null);

$envAppKey = getenv('SELFAUTH_APP_KEY') ?: null;
if ($envAppKey) {
    $settings->seedFromEnv('app_key', $envAppKey);
} elseif (!$settings->has('app_key')) {
    // No app key supplied anywhere: generate one and persist it so it
    // survives restarts (it lives in the same data volume as the DB).
    $settings->set('app_key', bin2hex(random_bytes(32)));
}

// Bootstrap the admin/login password. Preferred: SELFAUTH_ADMIN_PASSWORD
// (plaintext, only ever read on first boot then hashed and forgotten).
// Also accepts a pre-hashed SELFAUTH_ADMIN_PASSWORD_HASH, or migrates a
// legacy raw MD5 USER_HASH from an old config.php.
if (!$settings->has('password_hash')) {
    $plaintext = getenv('SELFAUTH_ADMIN_PASSWORD') ?: null;
    $preHashed = getenv('SELFAUTH_ADMIN_PASSWORD_HASH') ?: null;
    $legacyHash = getenv('SELFAUTH_LEGACY_PASSWORD_HASH') ?: null;

    if ($plaintext) {
        $settings->set('password_hash', Auth::hashPassword($plaintext));
    } elseif ($preHashed) {
        $settings->set('password_hash', $preHashed);
    } elseif ($legacyHash) {
        // Kept in its legacy MD5 form; Auth::verify() understands this and
        // the login flow transparently rehashes it to Argon2id the first
        // time the user successfully logs in.
        $settings->set('password_hash', $legacyHash);
    }
}

define('SELFAUTH_APP_URL', $settings->get('app_url', ''));
define('SELFAUTH_APP_KEY', $settings->get('app_key', ''));
define('SELFAUTH_USER_URL', $settings->get('user_url', ''));
define('SELFAUTH_PASSWORD_HASH', $settings->get('password_hash', ''));
define('SELFAUTH_WEBMENTIONS_ENABLED', filter_var(getenv('SELFAUTH_WEBMENTIONS_ENABLED') ?: 'true', FILTER_VALIDATE_BOOLEAN));

$GLOBALS['selfauth_pdo'] = $pdo;
$GLOBALS['selfauth_settings'] = $settings;
