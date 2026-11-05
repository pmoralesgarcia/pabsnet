<?php

require_once __DIR__ . '/../src/bootstrap.php';

use Selfauth\Auth;
use Selfauth\AuthCodes;
use Selfauth\AuthRequest;
use Selfauth\Blocklist;
use Selfauth\EventBus;
use Selfauth\RateLimiter;
use Selfauth\Scopes;
use Selfauth\Session;
use Selfauth\SignInLog;
use Selfauth\Support;

if (SELFAUTH_APP_URL === '' || SELFAUTH_APP_KEY === '' || SELFAUTH_USER_URL === '' || SELFAUTH_PASSWORD_HASH === '') {
    Support::errorPage(
        'Not set up yet',
        'Set SELFAUTH_APP_URL, SELFAUTH_USER_URL and SELFAUTH_ADMIN_PASSWORD (see the README), or open setup.php if you are not using Docker.'
    );
}

$pdo = $GLOBALS['selfauth_pdo'];
$blocklist = new Blocklist($pdo);
$signInLog = new SignInLog($pdo);
$rateLimiter = new RateLimiter($pdo);
$clientIp = Support::clientIp();

if ($blocklist->isIpBlocked($clientIp)) {
    EventBus::fire($pdo, 'signin.blocked', ['reason' => 'ip', 'ip' => $clientIp], 'Blocked sign-in attempt from IP ' . $clientIp);
    Support::errorPage('Access denied', 'Your IP address is blocked from this sign-in page.', '403 Forbidden');
}

/* ------------------------------------------------------------------
 * Step 2: the client redeems an authorization code (POST with `code`).
 * ------------------------------------------------------------------ */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['code'])) {
    AuthRequest::noStore();
    $respond = function (int $status, array $body) {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '*/*';
        $accept = $accept === '' ? '*/*' : $accept;
        $json = Support::getQValue('application/json', $accept);
        $form = Support::getQValue('application/x-www-form-urlencoded', $accept);
        http_response_code($status);
        if ($json === 0.0 && $form === 0.0) {
            http_response_code(406);
            exit;
        }
        if ($json >= $form) {
            header('Content-Type: application/json');
            exit(json_encode($body, JSON_UNESCAPED_SLASHES));
        }
        header('Content-Type: application/x-www-form-urlencoded');
        exit(http_build_query($body));
    };

    if (!$rateLimiter->allow('indieauth_redeem:' . $clientIp, 60, 60)) {
        $respond(429, ['error' => 'slow_down', 'error_description' => 'Too many requests']);
    }
    $grant = $_POST['grant_type'] ?? 'authorization_code';
    if ($grant !== 'authorization_code') {
        $respond(400, ['error' => 'unsupported_grant_type']);
    }
    $code = (string) $_POST['code'];
    $clientId = ClientIdOrNull((string) ($_POST['client_id'] ?? ''));
    $redirect = (string) ($_POST['redirect_uri'] ?? '');
    $verifier = isset($_POST['code_verifier']) ? (string) $_POST['code_verifier'] : null;
    if ($clientId === null || $redirect === '' || !preg_match('/^[0-9a-f]{64}$/', $code)) {
        $respond(400, ['error' => 'invalid_request', 'error_description' => 'code, client_id and redirect_uri are required']);
    }
    $row = AuthCodes::redeem($pdo, $code, $clientId, $redirect, $verifier);
    if (!is_array($row)) {
        $respond(400, ['error' => 'invalid_grant', 'error_description' => 'The code is invalid, expired, already used, or does not match']);
    }
    if ($row['code_challenge'] === null && AuthRequest::requirePkce()) {
        $respond(400, ['error' => 'invalid_grant', 'error_description' => 'PKCE is required']);
    }

    $granted = Scopes::parse($row['scope']);
    $out = ['me' => $row['me']];
    if (in_array('profile', $granted, true)) {
        $s = $GLOBALS['selfauth_settings'];
        $profile = array_filter([
            'name' => $s->get('profile_name'),
            'url' => $s->get('profile_url') ?: SELFAUTH_USER_URL,
            'photo' => $s->get('profile_photo'),
            'email' => in_array('email', $granted, true) ? $s->get('profile_email') : null,
        ], fn($v) => $v !== null && $v !== '');
        $out['profile'] = $profile;
    }
    if ($row['code_challenge'] === null && $row['scope'] !== '') {
        $out['scope'] = $row['scope']; // legacy clients (no PKCE) read granted scope here
    }
    $respond(200, $out);
}

function ClientIdOrNull(string $v): ?string
{
    return \Selfauth\ClientId::canonicalize($v);
}

/* ------------------------------------------------------------------
 * Step 1: the user's browser arrives with an authorization request.
 * ------------------------------------------------------------------ */
$req = AuthRequest::fromQuery($_GET);

if ($req->error !== null) {
    if ($req->clientId !== null && $blocklist->isClientBlocked($req->clientId)) {
        Support::errorPage('Access denied', 'This app has been blocked by the site owner.', '403 Forbidden');
    }
    Support::errorPage('Can’t sign in', $req->error);
}
if ($blocklist->isClientBlocked($req->clientId)) {
    $signInLog->record($req->clientId, $req->redirectUri, implode(' ', $req->requestedScopes) ?: null, $clientIp, $_SERVER['HTTP_USER_AGENT'] ?? null, false);
    EventBus::fire($pdo, 'signin.blocked', ['reason' => 'client_id', 'client_id' => $req->clientId, 'ip' => $clientIp], 'Blocked client attempted sign-in: ' . $req->clientId);
    Support::errorPage('Access denied', 'This app has been blocked by the site owner.', '403 Forbidden');
}
if ($blocklist->isRedirectBlocked($req->redirectUri)) {
    $signInLog->record($req->clientId, $req->redirectUri, implode(' ', $req->requestedScopes) ?: null, $clientIp, $_SERVER['HTTP_USER_AGENT'] ?? null, false);
    EventBus::fire($pdo, 'signin.blocked', ['reason' => 'redirect_uri', 'redirect_uri' => $req->redirectUri, 'ip' => $clientIp], 'Blocked redirect_uri attempted sign-in: ' . $req->redirectUri);
    Support::errorPage('Access denied', 'This return address has been blocked by the site owner.', '403 Forbidden');
}
if ($req->softError !== null) {
    $req->redirectError($req->softError, $req->softErrorDescription ?? '');
}

$params = $req->toArray();
$csrfData = $req->clientId . "\n" . $req->redirectUri . "\n" . ($req->state ?? '') . "\n" . ($req->challenge ?? '');
$message = null;
$posted = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';

// Scopes the user can actually grant, split by what this server allows.
$offered = [];
$unavailable = [];
foreach (Scopes::sorted($req->requestedScopes) as $s) {
    if (Scopes::allowed($s)) {
        $offered[] = $s;
    } else {
        $unavailable[] = $s;
    }
}

if ($posted) {
    $action = (string) ($_POST['action'] ?? 'password');
    $csrf = (string) ($_POST['_csrf'] ?? '');

    if (!Auth::verifySignedCode(SELFAUTH_APP_KEY, $csrfData, $csrf)) {
        $message = 'This sign-in page expired. Reload it and try again.';
    } elseif ($action === 'cancel') {
        $req->redirectError('access_denied', 'The user cancelled the sign-in');
    } else {
        $chosen = array_values(array_intersect($offered, array_map('strval', (array) ($_POST['scopes'] ?? []))));
        $chosen = Scopes::normalizeGranted($chosen);

        if ($action === 'sso' && SELFAUTH_LOGIN_SHOW_OIDC_BUTTON) {
            Session::start();
            $_SESSION['ia_pending'] = $params + ['granted' => $chosen, 'created' => time()];
            header('Location: oidc-login.php?purpose=login', true, 303);
            exit;
        }

        if (!SELFAUTH_LOGIN_SHOW_PASSWORD_FORM) {
            $message = 'Password sign-in is turned off. Use single sign-on.';
        } elseif (!$rateLimiter->allow('indieauth_login:' . $clientIp, 15, 60)) {
            $message = 'Too many attempts. Wait a minute, then try again.';
            http_response_code(429);
        } else {
            $password = (string) ($_POST['password'] ?? '');
            if (!Auth::verify($password, SELFAUTH_PASSWORD_HASH, SELFAUTH_USER_URL, SELFAUTH_APP_KEY)) {
                $signInLog->record($req->clientId, $req->redirectUri, implode(' ', $chosen) ?: null, $clientIp, $_SERVER['HTTP_USER_AGENT'] ?? null, false);
                EventBus::fire($pdo, 'signin.failed', ['client_id' => $req->clientId, 'redirect_uri' => $req->redirectUri, 'ip' => $clientIp, 'method' => 'password'], 'Failed sign-in attempt from ' . $clientIp . ' for client ' . $req->clientId);
                if (function_exists('syslog') && getenv('SELFAUTH_SYSLOG_FAILURE') === 'true') {
                    syslog(LOG_CRIT, sprintf('IndieAuth: login failure from %s for %s', $clientIp, SELFAUTH_USER_URL));
                }
                $message = 'That password isn’t right.';
                http_response_code(401);
            } else {
                if (Auth::needsRehash(SELFAUTH_PASSWORD_HASH)) {
                    $GLOBALS['selfauth_settings']->set('password_hash', Auth::hashPassword($password));
                }
                AuthRequest::complete($pdo, $params, $chosen, 'password');
            }
        }
    }
}

/* ------------------------------------------------------------------
 * Render the sign-in / consent page.
 * ------------------------------------------------------------------ */
$csrfToken = Auth::createSignedCode(SELFAUTH_APP_KEY, $csrfData, 10 * 60);
$selected = $posted ? array_map('strval', (array) ($_POST['scopes'] ?? [])) : null;
$showSso = SELFAUTH_LOGIN_SHOW_OIDC_BUTTON;
$showPassword = SELFAUTH_LOGIN_SHOW_PASSWORD_FORM;

$nonce = base64_encode(random_bytes(16));
header('Content-Security-Policy: default-src \'none\'; style-src \'self\' \'nonce-' . $nonce . '\'; img-src https: data:; base-uri \'none\'; frame-ancestors \'none\'');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
AuthRequest::noStore();

require __DIR__ . '/../src/views/consent.php';
