<?php
// Minimal dependency-free tests: php tests/run.php
spl_autoload_register(function ($c) {
    $f = __DIR__ . '/../src/' . str_replace('Selfauth\\', '', $c) . '.php';
    if (is_file($f)) require $f;
});
use Selfauth\{AuthCodes, ClientId, SafeHttp, Scopes};

$fail = 0; $n = 0;
function t(string $name, bool $ok): void { global $fail, $n; $n++; if (!$ok) { $fail++; echo "FAIL: $name\n"; } }

// client_id rules
foreach (['https://app.example/', 'https://app.example/a/b?x=1', 'http://127.0.0.1:8080/', 'http://[::1]/x', 'HTTPS://App.Example:443/'] as $u) t("valid $u", ClientId::canonicalize($u) !== null);
foreach (['https://app.example', 'https://app.example/#f', 'https://u:p@app.example/', 'https://app.example/a/../b', 'https://app.example/./', 'ftp://app.example/', 'https://10.0.0.1/', 'https://[fe80::1]/', 'javascript:alert(1)', "https://app.example/ a", ''] as $u) t("invalid $u", ClientId::canonicalize($u) === null);
t('canonical lowercases host and drops default port', ClientId::canonicalize('HTTPS://App.Example:443/x') === 'https://app.example/x');

// redirect_uri
t('same origin ok', ClientId::redirectAllowed('https://a.example/', 'https://a.example/cb', [])['ok']);
t('other origin rejected', !ClientId::redirectAllowed('https://a.example/', 'https://b.example/cb', [])['ok']);
t('published other origin ok', ClientId::redirectAllowed('https://a.example/', 'https://b.example/cb', ['https://b.example/cb'])['ok']);
t('subdomain is a different origin', !ClientId::redirectAllowed('https://a.example/', 'https://x.a.example/cb', [])['ok']);
t('javascript redirect rejected', !ClientId::validRedirectUri('javascript:alert(1)'));
t('fragment redirect rejected', !ClientId::validRedirectUri('https://a.example/cb#x'));
t('custom scheme allowed', ClientId::validRedirectUri('myapp://cb'));

// SSRF
foreach (['127.0.0.1', '10.1.2.3', '192.168.0.1', '172.16.5.5', '169.254.169.254', '0.0.0.0', '::1', 'fc00::1', 'fe80::1'] as $ip) t("private $ip", !SafeHttp::isPublicIp($ip));
foreach (['93.184.216.34', '1.1.1.1', '2606:4700:4700::1111'] as $ip) t("public $ip", SafeHttp::isPublicIp($ip));
t('resolve rejects literal private ip', SafeHttp::resolve('127.0.0.1') === []);
t('get refuses loopback', SafeHttp::get('http://127.0.0.1:1/') === null);
t('get refuses file scheme', SafeHttp::get('file:///etc/passwd') === null);
t('get refuses metadata ip', SafeHttp::get('http://169.254.169.254/latest/') === null);
t('relative url resolve', SafeHttp::resolveUrl('https://a.example/x/y', '../z.png') === 'https://a.example/z.png');

// scopes
t('parse dedupes', Scopes::parse('profile  email profile') === ['profile', 'email']);
t('email without profile dropped', Scopes::normalizeGranted(['email']) === []);
t('email with profile kept', Scopes::normalizeGranted(['email', 'profile']) === ['email', 'profile']);
t('unknown scope not allowed by default', !Scopes::allowed('rm-rf'));
t('delete is high risk and off by default', Scopes::info('delete')['risk'] === 'high' && !Scopes::defaultOn('delete'));
t('profile on by default', Scopes::defaultOn('profile'));
t('identity sorts before access', Scopes::sorted(['delete', 'profile', 'email'])[0] === 'profile');
t('risk summary', Scopes::riskSummary(['profile', 'delete']) === 'high' && Scopes::riskSummary(['profile']) === 'low');

// auth codes (in-memory sqlite)
$pdo = new PDO('sqlite::memory:'); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$v = str_repeat('a', 64); $ch = rtrim(strtr(base64_encode(hash('sha256', $v, true)), '+/', '-_'), '=');
$code = AuthCodes::issue($pdo, 'https://c.example/', 'https://c.example/cb', 'profile', $ch, 'https://me.example/');
t('code is 64 hex', (bool) preg_match('/^[0-9a-f]{64}$/', $code));
t('plaintext code not stored', (int) $pdo->query("SELECT COUNT(*) FROM auth_codes WHERE code_hash = '$code'")->fetchColumn() === 0);
t('wrong verifier fails', AuthCodes::redeem($pdo, $code, 'https://c.example/', 'https://c.example/cb', str_repeat('b', 64)) === 'invalid_grant');
t('burned after failed attempt', AuthCodes::redeem($pdo, $code, 'https://c.example/', 'https://c.example/cb', $v) === 'invalid_grant');
$code = AuthCodes::issue($pdo, 'https://c.example/', 'https://c.example/cb', 'profile', $ch, 'https://me.example/');
t('wrong client fails', AuthCodes::redeem($pdo, $code, 'https://evil.example/', 'https://c.example/cb', $v) === 'invalid_grant');
$code = AuthCodes::issue($pdo, 'https://c.example/', 'https://c.example/cb', 'profile', $ch, 'https://me.example/');
t('wrong redirect fails', AuthCodes::redeem($pdo, $code, 'https://c.example/', 'https://c.example/other', $v) === 'invalid_grant');
$code = AuthCodes::issue($pdo, 'https://c.example/', 'https://c.example/cb', 'profile', $ch, 'https://me.example/');
t('missing verifier fails', AuthCodes::redeem($pdo, $code, 'https://c.example/', 'https://c.example/cb', null) === 'invalid_grant');
$code = AuthCodes::issue($pdo, 'https://c.example/', 'https://c.example/cb', 'profile', $ch, 'https://me.example/');
$ok = AuthCodes::redeem($pdo, $code, 'https://c.example/', 'https://c.example/cb', $v);
t('correct redeem works', is_array($ok) && $ok['me'] === 'https://me.example/');
t('replay fails', AuthCodes::redeem($pdo, $code, 'https://c.example/', 'https://c.example/cb', $v) === 'invalid_grant');
$code = AuthCodes::issue($pdo, 'https://c.example/', 'https://c.example/cb', '', $ch, 'https://me.example/');
$pdo->exec('UPDATE auth_codes SET expires_at = ' . (time() - 1));
t('expired fails', AuthCodes::redeem($pdo, $code, 'https://c.example/', 'https://c.example/cb', $v) === 'invalid_grant');

echo $fail === 0 ? "OK: all $n passed\n" : "$fail of $n failed\n";
exit($fail === 0 ? 0 : 1);
