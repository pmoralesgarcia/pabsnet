<?php

require_once __DIR__ . '/../src/bootstrap.php';

use Selfauth\Auth;
use Selfauth\Blocklist;
use Selfauth\SignInLog;
use Selfauth\Support;

if (SELFAUTH_APP_URL === '' || SELFAUTH_APP_KEY === '' || SELFAUTH_USER_URL === '' || SELFAUTH_PASSWORD_HASH === '') {
    Support::errorPage(
        'Configuration Error',
        'Endpoint not yet configured. Set the SELFAUTH_APP_URL, SELFAUTH_USER_URL and SELFAUTH_ADMIN_PASSWORD environment variables (see README), or visit setup.php if running without Docker.'
    );
}

$pdo = $GLOBALS['selfauth_pdo'];
$blocklist = new Blocklist($pdo);
$signInLog = new SignInLog($pdo);
$clientIp = Support::clientIp();

if ($blocklist->isIpBlocked($clientIp)) {
    Support::errorPage('Forbidden', 'Your IP address is not permitted to use this endpoint.', '403 Forbidden');
}

// First handle verification of codes (the token/code exchange step).
$code = Support::filterInputRegexp(INPUT_POST, 'code', '@^[0-9a-f]+:[0-9a-f]{64}:@');

if ($code !== null) {
    $redirect_uri = filter_input(INPUT_POST, 'redirect_uri', FILTER_VALIDATE_URL);
    $client_id = filter_input(INPUT_POST, 'client_id', FILTER_VALIDATE_URL);

    if (!(is_string($code)
        && is_string($redirect_uri)
        && is_string($client_id)
        && Auth::verifySignedCode(SELFAUTH_APP_KEY, SELFAUTH_USER_URL . $redirect_uri . $client_id, $code))
    ) {
        Support::errorPage('Verification Failed', 'Given Code Was Invalid');
    }

    $response = ['me' => SELFAUTH_USER_URL];

    $code_parts = explode(':', $code, 3);
    if ($code_parts[2] !== '') {
        $response['scope'] = Auth::base64UrlDecode($code_parts[2]);
    }

    $accept_header = $_SERVER['HTTP_ACCEPT'] ?? '*/*';
    if ($accept_header === '') {
        $accept_header = '*/*';
    }

    $json = Support::getQValue('application/json', $accept_header);
    $form = Support::getQValue('application/x-www-form-urlencoded', $accept_header);

    if ($json === 0.0 && $form === 0.0) {
        Support::errorPage(
            'No Accepted Response Types',
            'The client accepts neither JSON nor Form encoded responses.',
            '406 Not Acceptable'
        );
    } elseif ($json >= $form) {
        header('Content-Type: application/json');
        exit(json_encode($response));
    } else {
        header('Content-Type: application/x-www-form-urlencoded');
        exit(http_build_query($response));
    }
}

// If this is not verification, collect all the client supplied data. Exit on errors.

$me = filter_input(INPUT_GET, 'me', FILTER_VALIDATE_URL);
$client_id = filter_input(INPUT_GET, 'client_id', FILTER_VALIDATE_URL);
$redirect_uri = filter_input(INPUT_GET, 'redirect_uri', FILTER_VALIDATE_URL);
$state = Support::filterInputRegexp(INPUT_GET, 'state', '@^[\x20-\x7E]*$@');
$response_type = Support::filterInputRegexp(INPUT_GET, 'response_type', '@^(id|code)?$@');
$scope = Support::filterInputRegexp(INPUT_GET, 'scope', '@^([\x21\x23-\x5B\x5D-\x7E]+( [\x21\x23-\x5B\x5D-\x7E]+)*)?$@');

if (!is_string($client_id)) {
    Support::errorPage('Faulty Request', 'There was an error with the request. The "client_id" field is invalid.');
}
if (!is_string($redirect_uri)) {
    Support::errorPage('Faulty Request', 'There was an error with the request. The "redirect_uri" field is invalid.');
}
if ($state === false) {
    Support::errorPage('Faulty Request', 'There was an error with the request. The "state" field contains invalid data.');
}
if ($response_type === false) {
    Support::errorPage('Faulty Request', 'There was an error with the request. The "response_type" field must be "code".');
}
if ($scope === false) {
    Support::errorPage('Faulty Request', 'There was an error with the request. The "scope" field contains invalid data.');
}
if ($scope === '') {
    $scope = null;
}

if ($blocklist->isClientBlocked($client_id)) {
    $signInLog->record($client_id, $redirect_uri, $scope, $clientIp, $_SERVER['HTTP_USER_AGENT'] ?? null, false);
    Support::errorPage('Forbidden', 'This client application has been blocked by the endpoint owner.', '403 Forbidden');
}
if ($blocklist->isRedirectBlocked($redirect_uri)) {
    $signInLog->record($client_id, $redirect_uri, $scope, $clientIp, $_SERVER['HTTP_USER_AGENT'] ?? null, false);
    Support::errorPage('Forbidden', 'This redirect destination has been blocked by the endpoint owner.', '403 Forbidden');
}

// If the user submitted a password, get ready to redirect back to the callback.

$pass_input = filter_input(INPUT_POST, 'password', FILTER_UNSAFE_RAW);

if ($pass_input !== null) {
    $csrf_code = filter_input(INPUT_POST, '_csrf', FILTER_UNSAFE_RAW);

    if ($csrf_code === null || !Auth::verifySignedCode(SELFAUTH_APP_KEY, $client_id . $redirect_uri . $state, $csrf_code)) {
        Support::errorPage('Invalid CSRF Code', 'Usually this means you took too long to log in. Please try again.');
    }

    $verified = Auth::verify($pass_input, SELFAUTH_PASSWORD_HASH, SELFAUTH_USER_URL, SELFAUTH_APP_KEY);

    if (!$verified) {
        $signInLog->record($client_id, $redirect_uri, $scope, $clientIp, $_SERVER['HTTP_USER_AGENT'] ?? null, false);

        if (function_exists('syslog') && getenv('SELFAUTH_SYSLOG_FAILURE') === 'true') {
            syslog(LOG_CRIT, sprintf('IndieAuth: login failure from %s for %s', $clientIp, $me));
        }

        Support::errorPage('Login Failed', 'Invalid password.');
    }

    // Successful login: transparently upgrade a legacy MD5 hash (or any
    // hash whose cost parameters are now considered outdated) to a fresh
    // Argon2id hash.
    if (Auth::needsRehash(SELFAUTH_PASSWORD_HASH)) {
        $GLOBALS['selfauth_settings']->set('password_hash', Auth::hashPassword($pass_input));
    }

    $scope = Support::filterInputRegexp(INPUT_POST, 'scopes', '@^[\x21\x23-\x5B\x5D-\x7E]+$@', FILTER_REQUIRE_ARRAY);

    if ($scope !== null) {
        if ($scope === false || in_array(false, $scope, true)) {
            Support::errorPage('Invalid Scopes', 'The scopes provided contained illegal characters.');
        }
        $scope = implode(' ', $scope);
    }

    $code = Auth::createSignedCode(SELFAUTH_APP_KEY, SELFAUTH_USER_URL . $redirect_uri . $client_id, 5 * 60, $scope ?? '');

    $final_redir = $redirect_uri;
    $final_redir .= (strpos($redirect_uri, '?') === false) ? '?' : '&';
    $parameters = ['code' => $code, 'me' => SELFAUTH_USER_URL];
    if ($state !== null) {
        $parameters['state'] = $state;
    }
    $final_redir .= http_build_query($parameters);

    $signInLog->record($client_id, $redirect_uri, $scope, $clientIp, $_SERVER['HTTP_USER_AGENT'] ?? null, true);

    if (function_exists('syslog') && getenv('SELFAUTH_SYSLOG_SUCCESS') === 'true') {
        syslog(LOG_INFO, sprintf('IndieAuth: login from %s for %s', $clientIp, $me));
    }

    header('Location: ' . $final_redir, true, 302);
    exit();
}

// If neither password nor a code was submitted, we need to ask the user to authenticate.

$csrf_code = Auth::createSignedCode(SELFAUTH_APP_KEY, $client_id . $redirect_uri . $state, 2 * 60);
$client_meta = Support::clientInfo($client_id);

?><!doctype html>
<html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Login</title>
        <style>
h1{text-align:center;margin-top:3%;}
body {text-align:center;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;}
fieldset, pre {width:400px; margin-left:auto; margin-right:auto;margin-bottom:50px; background-color:#FFC; min-height:1em; box-sizing:border-box; padding:10px; overflow-wrap:anywhere;}
.client-title {width:400px; margin-left:auto; margin-right:auto; min-height:1em;}
.client-meta {width:400px; margin-left:auto; margin-right:auto;margin-bottom:50px; min-height:1em;}
fieldset {text-align:left;}
.form-login{
margin-left:auto;
width:300px;
margin-right:auto;
text-align:center;
margin-top:20px;
border:solid 1px black;
padding:20px;
box-sizing:border-box;
}
.form-line{ margin:5px 0 0 0;}
.submit{width:100%}
.yellow{background-color:#FFC}
input[type=password]{width:100%;box-sizing:border-box;padding:6px;}
        </style>
    </head>
    <body>
        <form method="POST" action="">
            <h1>Authenticate</h1>
            <div>You are attempting to login with client <pre><?php echo Support::e($client_id); ?></pre></div>
            <?php if (isset($client_meta)) : ?>
            <div class="client-title">
                <?php if (!empty($client_meta['client_logo'])) : ?><img src="<?php echo Support::e($client_meta['client_logo']); ?>" alt="[logo]" height="40" /><?php endif; ?>
                <span><?php echo Support::e($client_meta['client_name'] ?? ''); ?></span>
            </div>
            <div class="client-meta">
                <?php if (!empty($client_meta['client_uri'])) : ?><a href="<?php echo Support::e($client_meta['client_uri']); ?>">Webpage</a><?php endif; ?>
                <?php if (!empty($client_meta['client_tos'])) : ?><a href="<?php echo Support::e($client_meta['client_tos']); ?>">Terms of Service</a><?php endif; ?>
                <?php if (!empty($client_meta['client_policy'])) : ?><a href="<?php echo Support::e($client_meta['client_policy']); ?>">Privacy Policy</a><?php endif; ?>
            </div>
            <?php endif; ?>
            <?php if ($scope !== null && strlen($scope) > 0) : ?>
            <div>It is requesting the following scopes, uncheck any you do not wish to grant:</div>
            <fieldset>
                <legend>Scopes</legend>
                <?php foreach (explode(' ', $scope) as $n => $checkbox) : ?>
                <div>
                    <input id="scope_<?php echo $n; ?>" type="checkbox" name="scopes[]" value="<?php echo Support::e($checkbox); ?>" checked>
                    <label for="scope_<?php echo $n; ?>"><?php echo Support::e($checkbox); ?></label>
                </div>
                <?php endforeach; ?>
            </fieldset>
            <?php endif; ?>
            <div>After login you will be redirected to  <pre><?php echo Support::e($redirect_uri); ?></pre></div>
            <div class="form-login">
                <input type="hidden" name="_csrf" value="<?php echo Support::e($csrf_code); ?>" />
                <p class="form-line">
                    Logging in as:<br />
                    <span class="yellow"><?php echo Support::e(SELFAUTH_USER_URL); ?></span>
                </p>
                <div class="form-line">
                    <label for="password">Password:</label><br />
                    <input type="password" name="password" id="password" autofocus />
                </div>
                <div class="form-line">
                    <input class="submit" type="submit" name="submit" value="Submit" />
                </div>
            </div>
        </form>
    </body>
</html>
