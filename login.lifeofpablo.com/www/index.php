<?php
/**
 * Selfauth - Environment Powered & Hardened
 */

// 1. Security Headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Content-Security-Policy: default-src 'self'; style-src 'self' cdn.simplecss.org;");
header("Referrer-Policy: no-referrer");

// 2. Configuration Loading (Env Vars > config.php)
$env_vars = [
    'APP_URL'   => getenv('SA_APP_URL'),
    'APP_KEY'   => getenv('SA_APP_KEY'),
    'USER_HASH' => getenv('SA_USER_HASH'),
    'USER_URL'  => getenv('SA_USER_URL'),
];

$configfile = __DIR__ . '/config.php';
if (file_exists($configfile)) {
    include_once $configfile;
}

// Map Env Vars to Constants if not already defined by config.php
foreach ($env_vars as $key => $value) {
    if (!defined($key) && $value !== false) {
        define($key, $value);
    }
}

function error_page($header, $body, $http = '400 Bad Request') {
    $protocol = $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.0';
    header($protocol . ' ' . $http);
    die("<!doctype html><html><head><meta name='viewport' content='width=device-width, initial-scale=1' /><link rel='stylesheet' href='https://cdn.simplecss.org/simple.min.css'><title>Error</title></head><body><div style='text-align:center;margin-top:10%'><h1>Error: $header</h1><p>$body</p></div></body></html>");
}

if (!defined('APP_URL') || !defined('APP_KEY') || !defined('USER_HASH') || !defined('USER_URL')) {
    error_page('Configuration Error', 'Missing environment variables: SA_APP_URL, SA_APP_KEY, SA_USER_HASH, or SA_USER_URL.');
}

// 3. Security & Helper Functions
if (!function_exists('hash_equals')) {
    function hash_equals($k, $u) {
        if (strlen($k) !== strlen($u)) return false;
        $res = 0;
        for ($i = 0; $i < strlen($k); $i++) { $res |= (ord($k[$i]) ^ ord($u[$i])); }
        return $res === 0;
    }
}

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

function verify_password($pass) { return password_verify($pass, USER_HASH); }

function filter_input_regexp($t, $v, $r, $f = null) {
    return filter_input($t, $v, FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => $r], 'flags' => $f]);
}

function get_q_value($mime, $accept) {
    $full = preg_replace('@^([^/]+\/).+$@', '$1*', $mime);
    $regex = '/(?<=^|,)\s*(\*\/\*|' . preg_quote($full, '/') . '|' . preg_quote($mime, '/') . ')\s*(?:[^,]*?;\s*q\s*=\s*([0-9.]+))?\s*(?:,|$)/';
    preg_match_all($regex, $accept, $m);
    $types = array_combine($m[1], $m[2]);
    $q = $types[$mime] ?? $types[$full] ?? $types['*/*'] ?? 0;
    return $q === '' ? 1 : floatval($q);
}

// 4. Logic: Code Verification (POST)
$code = filter_input_regexp(INPUT_POST, 'code', '@^[0-9a-f]+:[0-9a-f]{64}:@');
if ($code !== null) {
    $r_uri = filter_input(INPUT_POST, 'redirect_uri', FILTER_VALIDATE_URL);
    $c_id = filter_input(INPUT_POST, 'client_id', FILTER_VALIDATE_URL);

    if (!($r_uri && $c_id && verify_signed_code(APP_KEY, USER_URL . $r_uri . $c_id, $code))) {
        error_page('Verification Failed', 'Invalid Code');
    }

    $res = ['me' => USER_URL];
    $p = explode(':', $code, 3);
    if ($p[2] !== '') $res['scope'] = base64_url_decode($p[2]);

    $acc = $_SERVER['HTTP_ACCEPT'] ?? '*/*';
    if (get_q_value('application/json', $acc) >= get_q_value('application/x-www-form-urlencoded', $acc)) {
        header('Content-Type: application/json'); echo json_encode($res);
    } else {
        header('Content-Type: application/x-www-form-urlencoded'); echo http_build_query($res);
    }
    exit;
}

// 5. Logic: Login UI (GET/POST)
$c_id = filter_input(INPUT_GET, 'client_id', FILTER_VALIDATE_URL);
$r_uri = filter_input(INPUT_GET, 'redirect_uri', FILTER_VALIDATE_URL);
$state = filter_input_regexp(INPUT_GET, 'state', '@^[\x20-\x7E]*$@');
$scope = filter_input_regexp(INPUT_GET, 'scope', '@^([\x21\x23-\x5B\x5D-\x7E]+( [\x21\x23-\x5B\x5D-\x7E]+)*)?$@');

if (!$c_id || !$r_uri) error_page('Faulty Request', 'Invalid client_id or redirect_uri.');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if (!verify_signed_code(APP_KEY, $c_id . $r_uri . $state, $_POST['_csrf'] ?? '')) {
        error_page('Invalid CSRF', 'Session expired.');
    }
    if (!verify_password($_POST['password'])) {
        sleep(1); error_page('Login Failed', 'Invalid password.');
    }

    $s_arr = filter_input_regexp(INPUT_POST, 'scopes', '@^[\x21\x23-\x5B\x5D-\x7E]+$@', FILTER_REQUIRE_ARRAY);
    $f_scope = $s_arr ? implode(' ', $s_arr) : '';
    $auth_code = create_signed_code(APP_KEY, USER_URL . $r_uri . $c_id, 300, $f_scope);
    
    $sep = (strpos($r_uri, '?') === false) ? '?' : '&';
    header('Location: ' . $r_uri . $sep . http_build_query(['code' => $auth_code, 'me' => USER_URL, 'state' => $state]), true, 302);
    exit;
}

$csrf = create_signed_code(APP_KEY, $c_id . $r_uri . $state, 120);
?>
<!doctype html>
<html>
<head>
    <title>Authorize</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="stylesheet" href="https://cdn.simplecss.org/simple.min.css">
</head>
<body style="text-align:center">
    <form method="POST" style="margin:20px auto; width:340px; border:1px solid #ccc; padding:20px; border-radius:8px;">
        <h2>Authenticate</h2>
        <p>Login for: <strong><?= htmlspecialchars(USER_URL) ?></strong></p>
        <?php if ($scope): ?>
        <fieldset style="text-align:left">
            <legend>Scopes</legend>
            <?php foreach(explode(' ', $scope) as $n => $s): ?>
            <label><input type="checkbox" name="scopes[]" value="<?= htmlspecialchars($s) ?>" checked> <?= htmlspecialchars($s) ?></label><br>
            <?php endforeach; ?>
        </fieldset>
        <?php endif; ?>
        <input type="hidden" name="_csrf" value="<?= $csrf ?>">
        <label>Password:</label>
        <input type="password" name="password" required autofocus style="width:100%">
        <button type="submit" style="width:100%; margin-top:10px">Authorize</button>
    </form>
</body>
</html>