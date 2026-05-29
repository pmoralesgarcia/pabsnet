<?php
// 1. Security Headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Content-Security-Policy: default-src 'self'; style-src 'self' cdn.simplecss.org;");
header("Referrer-Policy: no-referrer");

// 2. Load Config from Environment
define('APP_URL',   getenv('SA_APP_URL'));
define('APP_KEY',   getenv('SA_APP_KEY'));
define('USER_HASH',  getenv('SA_USER_HASH'));
define('USER_URL',   getenv('SA_USER_URL'));

function error_page($header, $body, $http = '400 Bad Request') {
    $protocol = $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.0';
    header($protocol . ' ' . $http);
    die("<!doctype html><html><head><meta name='viewport' content='width=device-width, initial-scale=1'/><link rel='stylesheet' href='https://cdn.simplecss.org/simple.min.css'><title>Error</title></head><body><div style='text-align:center;margin-top:10%'><h1>Error: $header</h1><p>$body</p></div></body></html>");
}

if (!APP_URL || !APP_KEY || !USER_HASH || !USER_URL) {
    error_page('Configuration Error', 'Environment variables (SA_APP_URL, SA_APP_KEY, SA_USER_HASH, SA_USER_URL) are missing or empty.');
}

// 3. Helper Functions
function base64_url_encode($s) { return strtr(rtrim(base64_encode($s), '='), '+/', '-_'); }
function base64_url_decode($s) { $s = strtr($s, '-_', '+/'); $p = strlen($s) % 4; if ($p !== 0) $s .= str_repeat('=', 4 - $p); return base64_decode($s); }

function create_signed_code($key, $msg, $ttl = 31536000, $data = '') {
    $exp = time() + $ttl;
    $sig = hash_hmac('sha256', $msg . $exp . $data, $key);
    return dechex($exp) . ':' . $sig . ':' . base64_url_encode($data);
}

function verify_signed_code($key, $msg, $code) {
    $p = explode(':', $code, 3);
    if (count($p) !== 3 || time() > hexdec($p[0])) return false;
    $sig = hash_hmac('sha256', $msg . hexdec($p[0]) . base64_url_decode($p[2]), $key);
    return hash_equals($sig, $p[1]);
}

// 4. Handle IndieAuth Verification (POST from Client)
if (isset($_POST['code'])) {
    $r_uri = filter_input(INPUT_POST, 'redirect_uri', FILTER_VALIDATE_URL);
    $c_id = filter_input(INPUT_POST, 'client_id', FILTER_VALIDATE_URL);
    
    if (!$r_uri || !$c_id || !verify_signed_code(APP_KEY, USER_URL . $r_uri . $c_id, $_POST['code'])) {
        error_page('Verification Failed', 'Invalid or expired authorization code.');
    }
    
    $res = ['me' => USER_URL];
    $p = explode(':', $_POST['code'], 3);
    if ($p[2] !== '') $res['scope'] = base64_url_decode($p[2]);
    
    header('Content-Type: application/json');
    die(json_encode($res));
}

// 5. Handle Login UI (GET/POST)
$c_id  = filter_input(INPUT_GET, 'client_id', FILTER_VALIDATE_URL);
$r_uri = filter_input(INPUT_GET, 'redirect_uri', FILTER_VALIDATE_URL);
$state = filter_input(INPUT_GET, 'state', FILTER_SANITIZE_SPECIAL_CHARS);
$scope = filter_input(INPUT_GET, 'scope', FILTER_SANITIZE_SPECIAL_CHARS);

if (!$c_id || !$r_uri) error_page('Invalid Request', 'client_id and redirect_uri are required.');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    // CSRF Check
    if (!verify_signed_code(APP_KEY, $c_id . $r_uri . $state, $_POST['_csrf'] ?? '')) {
        error_page('Expired', 'Session expired. Please go back and try again.');
    }
    
    // Password Check (BCrypt)
    if (!password_verify($_POST['password'], USER_HASH)) {
        sleep(1); // Brute force protection
        error_page('Denied', 'Incorrect password.');
    }
    
    $f_scope = isset($_POST['scopes']) ? implode(' ', $_POST['scopes']) : '';
    $auth_code = create_signed_code(APP_KEY, USER_URL . $r_uri . $c_id, 300, $f_scope);
    
    $sep = (strpos($r_uri, '?') === false) ? '?' : '&';
    header('Location: ' . $r_uri . $sep . http_build_query(['code' => $auth_code, 'me' => USER_URL, 'state' => $state]));
    exit;
}

$csrf = create_signed_code(APP_KEY, $c_id . $r_uri . $state, 300);
?>
<!doctype html>
<html>
<head>
    <title>Log in to <?= htmlspecialchars(parse_url($c_id, PHP_URL_HOST)) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="stylesheet" href="https://cdn.simplecss.org/simple.min.css">
</head>
<body>
    <header><h1>Authenticate</h1></header>
    <main>
        <form method="POST">
            <p>Logging in as <strong><?= htmlspecialchars(USER_URL) ?></strong></p>
            <p>Application: <code><?= htmlspecialchars($c_id) ?></code></p>
            
            <?php if ($scope): ?>
            <fieldset>
                <legend>Grant Scopes</legend>
                <?php foreach(explode(' ', $scope) as $s): ?>
                <label><input type="checkbox" name="scopes[]" value="<?= htmlspecialchars($s) ?>" checked> <?= htmlspecialchars($s) ?></label><br>
                <?php endforeach; ?>
            </fieldset>
            <?php endif; ?>

            <input type="hidden" name="_csrf" value="<?= $csrf ?>">
            <label for="password">Password</label>
            <input type="password" name="password" id="password" required autofocus>
            <button type="submit" style="width:100%">Authorize</button>
        </form>
    </main>
</body>
</html>