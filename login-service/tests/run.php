<?php
// Dependency-free tests: php tests/run.php
putenv('LOGIN_URL=https://login.example');
putenv('LOGIN_CLIENTS=https://seeded.example/ https://two.example/app');
require __DIR__ . '/../src/bootstrap.php';

use Login\{AuthRequest, ClientError, Config, IndieAuth, SafeHttp, Store, Urls};

$fail = 0; $n = 0;
function t(string $name, bool $ok): void { global $fail, $n; $n++; if (!$ok) { $fail++; echo "FAIL: $name\n"; } }
function refuses(callable $f): bool { try { $f(); return false; } catch (ClientError) { return true; } }

// profile urls, from the spec's own examples
t('adds https and a path', Urls::profile('Example.COM') === 'https://example.com/');
foreach (['https://example.com/', 'https://example.com/username', 'https://example.com/users?id=100', 'http://example.com/'] as $good) t("valid profile $good", Urls::profile($good) === $good);
foreach (['example.com:8443', 'https://example.com:8443/', 'https://172.28.92.51/', 'https://[::1]/', 'https://example.com/#me', 'https://user:pass@example.com/', 'https://example.com/foo/../bar', 'https://example.com/./x', 'mailto:user@example.com', 'ftp://example.com/', '', 'not a url', 'javascript:alert(1)'] as $bad) t("invalid profile: $bad", Urls::profile($bad) === null);

// client ids
t('valid client', Urls::client('https://Site.example/app') === 'https://site.example/app');
t('client path defaults', Urls::client('https://site.example') === 'https://site.example/');
t('client may use a port', Urls::client('https://site.example:8443/') === 'https://site.example:8443/');
foreach (['http://site.example/', 'https://10.0.0.1/', 'https://u:p@site.example/', 'https://site.example/#f', 'https://site.example/a/../b', 'site.example', 'javascript:alert(1)', 'https://site.example/"><script>'] as $bad) t("invalid client: $bad", Urls::client($bad) === null);

// redirects stay on the site
t('same origin', Urls::sameOrigin('https://a.example/', 'https://a.example/cb?x=1'));
foreach (['https://b.example/cb', 'https://sub.a.example/cb', 'http://a.example/cb', 'https://a.example:8443/cb', 'https://a.example.evil.test/', 'https://a.example@evil.test/', '//evil.test/', 'javascript:alert(1)', 'https://a.example/#f', ''] as $bad) t("other origin refused: $bad", !Urls::sameOrigin('https://a.example/', $bad));

// private addresses stay out of reach
foreach (['127.0.0.1', '10.0.0.1', '172.17.0.2', '192.168.1.1', '169.254.169.254', '100.64.0.1', '::1', '::ffff:10.0.0.1'] as $ip) t("private $ip", !SafeHttp::isPublicIp($ip));
foreach (['http://127.0.0.1:1/', 'http://localhost/', 'http://169.254.169.254/', 'file:///etc/passwd', 'http://1.1.1.1:8080/'] as $url) t("request refused: $url", SafeHttp::request('GET', $url) === null);

// discovery needs no token endpoint
$links = IndieAuth::links([], '<link rel="authorization_endpoint" href="/auth"><link rel="micropub" href="/m">', 'https://me.example/');
t('finds only what sign-in needs', $links === ['authorization_endpoint' => 'https://me.example/auth']);
t('link header wins', IndieAuth::links(['link' => ['</h>; rel="authorization_endpoint"']], '<link rel="authorization_endpoint" href="/b">', 'https://me.example/')['authorization_endpoint'] === 'https://me.example/h');

// sites
$db = sys_get_temp_dir() . '/login-test-' . bin2hex(random_bytes(4)) . '.sqlite';
$store = new Store($db);
t('seeded site', $store->isRegistered('https://seeded.example/') && $store->isRegistered('https://two.example/app'));
t('unknown site', !$store->isRegistered('https://new.example/'));
t('cannot register another domain', refuses(fn() => $store->register('https://victim.example/', 'https://me.example/')));
t('lookalike domain refused', refuses(fn() => $store->register('https://me.example.evil.test/', 'https://me.example/')));
t('subdomain refused', refuses(fn() => $store->register('https://blog.me.example/', 'https://me.example/')));
t('http site refused', refuses(fn() => $store->register('http://me.example/', 'https://me.example/')));
t('own domain registers', $store->register('https://me.example/app', 'https://me.example/~pablo') === 'https://me.example/app' && $store->isRegistered('https://me.example/app'));
t('listed for its owner', $store->clientsOf('https://me.example/~pablo') === ['https://me.example/app']);
$store->unregister('https://me.example/app', 'https://other.example/');
t('others cannot remove it', $store->isRegistered('https://me.example/app'));
$store->unregister('https://me.example/app', 'https://me.example/~pablo');
t('owner can remove it', !$store->isRegistered('https://me.example/app'));

// requests
$valid = ['client_id' => 'https://seeded.example/', 'redirect_uri' => 'https://seeded.example/cb', 'state' => 's', 'code_challenge' => str_repeat('a', 43), 'code_challenge_method' => 'S256'];
$r = AuthRequest::validate($valid, $store);
t('valid request', $r['client_id'] === 'https://seeded.example/' && $r['scope'] === '' && $r['me'] === null);
t('profile scope kept', AuthRequest::validate($valid + ['scope' => 'read profile'], $store)['scope'] === 'profile');
t('other scopes dropped', AuthRequest::validate($valid + ['scope' => 'create delete'], $store)['scope'] === '');
t('me hint', AuthRequest::validate($valid + ['me' => 'person.example'], $store)['me'] === 'https://person.example/');
t('bad client refused here', refuses(fn() => AuthRequest::validate(['client_id' => 'nope'] + $valid, $store)));
t('foreign redirect refused here', refuses(fn() => AuthRequest::validate(['redirect_uri' => 'https://evil.example/'] + $valid, $store)));
t('unregistered refused here', refuses(fn() => AuthRequest::validate(['client_id' => 'https://new.example/', 'redirect_uri' => 'https://new.example/cb'] + $valid, $store)));
t('back to site carries iss', AuthRequest::backTo('https://a.example/cb?x=1', ['code' => 'c']) === 'https://a.example/cb?x=1&code=c&iss=https%3A%2F%2Flogin.example%2F');

// codes
$v = str_repeat('v', 64); $ch = rtrim(strtr(base64_encode(hash('sha256', $v, true)), '+/', '-_'), '=');
$req = ['client_id' => 'https://seeded.example/', 'redirect_uri' => 'https://seeded.example/cb', 'code_challenge' => $ch];
$issue = fn(?array $p = null) => $store->issueCode($req, 'https://person.example/', $p);
$ok = fn(string $c) => $store->redeem($c, 'https://seeded.example/', 'https://seeded.example/cb', $v);
$c = $issue();
t('code not stored in the clear', !str_contains((string) file_get_contents($db), $c));
$got = $ok($c);
t('code redeems once', $got !== null && $got['me'] === 'https://person.example/' && $got['profile'] === null);
t('replay refused', $ok($c) === null);
t('wrong verifier refused', $store->redeem($c = $issue(), 'https://seeded.example/', 'https://seeded.example/cb', str_repeat('x', 64)) === null);
t('failed attempt burns it', $ok($c) === null);
t('wrong client refused', $store->redeem($c = $issue(), 'https://evil.example/', 'https://seeded.example/cb', $v) === null);
t('wrong redirect refused', $store->redeem($c = $issue(), 'https://seeded.example/', 'https://seeded.example/other', $v) === null);
t('short verifier refused', $store->redeem($issue(), 'https://seeded.example/', 'https://seeded.example/cb', 'short') === null);
t('unknown code refused', $ok('nope') === null);
t('profile kept', $ok($issue(['name' => 'P']))['profile'] === ['name' => 'P']);
$c = $issue();
(new PDO('sqlite:' . $db))->exec('UPDATE codes SET expires_at = ' . (time() - 1));
t('expired refused', $ok($c) === null);

// upstream responses
$_SESSION = [];
t('no pending sign-in refused', refuses(fn() => IndieAuth::finish(['state' => 'x', 'code' => 'y'])));
$pending = ['created' => time(), 'iss_required' => true, 'issuer' => 'https://auth.example/', 'context' => []];
$_SESSION['upstream'] = ['good' => $pending];
t('wrong issuer refused', refuses(fn() => IndieAuth::finish(['state' => 'good', 'code' => 'y', 'iss' => 'https://evil.example/'])));
t('state is single-use', !isset($_SESSION['upstream']['good']));
$_SESSION['upstream'] = ['good' => $pending];
t('missing required issuer refused', refuses(fn() => IndieAuth::finish(['state' => 'good', 'code' => 'y'])));
$_SESSION['upstream'] = ['good' => ['created' => time() - 601] + $pending];
t('expired sign-in refused', refuses(fn() => IndieAuth::finish(['state' => 'good', 'code' => 'y', 'iss' => 'https://auth.example/'])));

t('issuer normalised', Config::issuer() === 'https://login.example/' && Config::callbackUri() === 'https://login.example/callback');
t('escape covers attributes', e('"><script>\'') === '&quot;&gt;&lt;script&gt;&#039;');
@unlink($db);
echo $fail === 0 ? "OK: all $n passed\n" : "$fail of $n failed\n";
exit($fail === 0 ? 0 : 1);
