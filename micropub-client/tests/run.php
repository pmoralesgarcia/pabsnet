<?php
// Dependency-free tests: php tests/run.php
putenv('MICROPUB_CLIENT_URL=https://post.example/');
putenv('MICROPUB_CLIENT_ONE_CLICK_ME=https://owner.example/');
require __DIR__ . '/../src/bootstrap.php';

use MicropubClient\{ClientError, Compose, Config, Discovery, IndieAuth, Micropub, SafeHttp};

$fail = 0; $n = 0;
function t(string $name, bool $ok): void { global $fail, $n; $n++; if (!$ok) { $fail++; echo "FAIL: $name\n"; } }
function refuses(callable $f): bool { try { $f(); return false; } catch (ClientError) { return true; } }

// website addresses
t('adds https and a path', Discovery::canonicalize('Example.COM') === 'https://example.com/');
t('keeps path and port', Discovery::canonicalize('http://a.example:8080/me') === 'http://a.example:8080/me');
foreach (['', 'not a url', 'ftp://a.example/', 'https://u:p@a.example/', 'https://a.example/#f', 'https://a.example/../x', 'javascript:alert(1)'] as $bad) t("refused address: $bad", Discovery::canonicalize($bad) === null);

// private addresses stay out of reach
foreach (['127.0.0.1', '10.0.0.1', '172.17.0.2', '192.168.1.1', '169.254.169.254', '100.64.0.1', '100.127.255.255', '0.0.0.0', '::1', 'fe80::1', 'fc00::1', '::ffff:127.0.0.1', '::ffff:10.0.0.1'] as $ip) t("private $ip", !SafeHttp::isPublicIp($ip));
foreach (['1.1.1.1', '93.184.216.34', '100.128.0.1', '2606:4700:4700::1111'] as $ip) t("public $ip", SafeHttp::isPublicIp($ip));
foreach (['http://127.0.0.1:1/', 'http://localhost/', 'http://169.254.169.254/latest/', 'file:///etc/passwd', 'gopher://1.1.1.1/', 'https://u:p@1.1.1.1/', 'http://[::1]/', 'http://1.1.1.1:8080/', 'http://1.1.1.1:22/'] as $url) t("request refused: $url", SafeHttp::request('GET', $url) === null);

// finding endpoints
$html = '<html><head><link rel="micropub" href="/mp"><link rel="me authorization_endpoint" href="https://auth.example/a"><link rel="token_endpoint" href="t.php"></head><body><a rel="indieauth-metadata" href="/meta">x</a></body></html>';
$links = Discovery::links([], $html, 'https://me.example/blog/');
t('finds relative micropub', $links['micropub'] === 'https://me.example/mp');
t('finds multi-value rel', $links['authorization_endpoint'] === 'https://auth.example/a');
t('resolves against the page', $links['token_endpoint'] === 'https://me.example/blog/t.php');
t('finds metadata on a link', $links['indieauth-metadata'] === 'https://me.example/meta');
$links = Discovery::links(['link' => ['</head-mp>; rel="micropub", <https://h.example/t>; rel=token_endpoint']], $html, 'https://me.example/');
t('link headers win over the page', $links['micropub'] === 'https://me.example/head-mp' && $links['token_endpoint'] === 'https://h.example/t');
t('empty page is fine', Discovery::links([], '', 'https://me.example/') === []);
t('https endpoint is safe', Discovery::isSafeEndpoint('https://a.example/x'));
t('http endpoint is refused', !Discovery::isSafeEndpoint('http://a.example/x'));
t('non-web endpoint is refused', !Discovery::isSafeEndpoint('javascript:alert(1)'));

// sign-in pieces
t('client id and callback', Config::clientId() === 'https://post.example/' && Config::redirectUri() === 'https://post.example/callback.php');
t('pkce matches the spec example', IndieAuth::challenge('dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk') === 'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM');
t('one-click site', Config::oneClickMe() === 'https://owner.example/');
$_SESSION = [];
t('callback with no pending sign-in is refused', refuses(fn() => IndieAuth::finish(['state' => 'x', 'code' => 'y'])));
$_SESSION['pending'] = ['state' => 'good', 'created' => time(), 'iss_required' => true, 'issuer' => 'https://auth.example/'];
t('wrong state is refused', refuses(fn() => IndieAuth::finish(['state' => 'bad', 'code' => 'y'])));
t('pending state is single-use', !isset($_SESSION['pending']));
$_SESSION['pending'] = ['state' => 'good', 'created' => time(), 'iss_required' => true, 'issuer' => 'https://auth.example/'];
t('wrong issuer is refused', refuses(fn() => IndieAuth::finish(['state' => 'good', 'code' => 'y', 'iss' => 'https://evil.example/'])));
$_SESSION['pending'] = ['state' => 'good', 'created' => time(), 'iss_required' => true, 'issuer' => 'https://auth.example/'];
t('missing required issuer is refused', refuses(fn() => IndieAuth::finish(['state' => 'good', 'code' => 'y'])));
$_SESSION['pending'] = ['state' => 'good', 'created' => time() - 601, 'iss_required' => false, 'issuer' => null];
t('expired sign-in is refused', refuses(fn() => IndieAuth::finish(['state' => 'good', 'code' => 'y'])));
$_SESSION['pending'] = ['state' => 'good', 'created' => time(), 'iss_required' => false, 'issuer' => null];
t('server error is shown', refuses(fn() => IndieAuth::finish(['state' => 'good', 'error' => 'access_denied'])));

// each post type
$make = fn(string $type, array $post, ...$rest) => Compose::toEntry($type, $post, ...$rest)['properties'];
t('note', $make('note', ['content' => "a\r\nb"])['content'] === ["a\nb"]);
t('empty note refused', refuses(fn() => $make('note', ['content' => '  '])));
t('photo note with a coming file passes', isset(Compose::toEntry('note', [], [], [], true)['properties']));
$a = $make('article', ['name' => 'T', 'content' => 'B', 'summary' => 'S']);
t('article', $a['name'] === ['T'] && $a['content'] === ['B'] && $a['summary'] === ['S']);
t('article needs a title', refuses(fn() => $make('article', ['content' => 'B'])));
t('note ignores a stray title', !isset($make('note', ['content' => 'x', 'name' => 'T'])['name']));
foreach (['reply' => 'in-reply-to', 'like' => 'like-of', 'repost' => 'repost-of', 'bookmark' => 'bookmark-of'] as $type => $prop) {
    t("$type property", $make($type, ['url' => 'https://x.example/', 'content' => 'c'])[$prop] === ['https://x.example/']);
    t("$type needs a link", refuses(fn() => $make($type, ['content' => 'c'])));
    t("$type refuses a bad link", refuses(fn() => $make($type, ['url' => 'javascript:alert(1)', 'content' => 'c'])));
}
t('reply needs text', refuses(fn() => $make('reply', ['url' => 'https://x.example/'])));
t('like without text', !isset($make('like', ['url' => 'https://x.example/'])['content']));
$r = $make('rsvp', ['url' => 'https://x.example/e', 'rsvp' => 'yes']);
t('rsvp', $r['in-reply-to'] === ['https://x.example/e'] && $r['rsvp'] === ['yes']);
t('rsvp with event name', $make('rsvp', ['url' => 'https://x.example/e', 'rsvp' => 'no', 'event' => 'HWC'])['in-reply-to'][0]['properties']['name'] === ['HWC']);
t('rsvp refuses other values', refuses(fn() => $make('rsvp', ['url' => 'https://x.example/e', 'rsvp' => 'nope'])));
$c = $make('checkin', ['venue' => 'Castro', 'url' => 'https://x.example/c', 'locality' => 'SF', 'latitude' => '37.7', 'longitude' => '-122.4'])['checkin'][0];
t('checkin', $c['type'] === ['h-card'] && $c['properties']['name'] === ['Castro'] && $c['properties']['url'] === ['https://x.example/c'] && $c['properties']['latitude'] === ['37.7'] && !isset($c['properties']['region']));
t('checkin needs a place', refuses(fn() => $make('checkin', ['locality' => 'SF'])));
t('checkin refuses half coordinates', refuses(fn() => $make('checkin', ['venue' => 'V', 'latitude' => '1'])));
t('checkin refuses bad coordinates', refuses(fn() => $make('checkin', ['venue' => 'V', 'latitude' => '91', 'longitude' => '0'])) && refuses(fn() => $make('checkin', ['venue' => 'V', 'latitude' => 'x', 'longitude' => '0'])));
$l = $make('listen', ['song' => 'Everywhere', 'artist' => 'Michelle Branch'])['listen-of'][0];
t('listen', $l['type'] === ['h-cite'] && $l['properties']['name'] === ['Everywhere'] && $l['properties']['author'] === ['Michelle Branch'] && !isset($l['properties']['url']));
t('listen needs a name', refuses(fn() => $make('listen', ['artist' => 'A'])));
t('photo type needs a photo', refuses(fn() => $make('photo', ['content' => 'x'])));
t('photo by link', $make('photo', ['photo_url' => 'https://i.example/a.jpg'])['photo'] === ['https://i.example/a.jpg']);
t('photo with description', $make('photo', ['alt' => 'A cat'], ['https://i.example/a.jpg'])['photo'] === [['value' => 'https://i.example/a.jpg', 'alt' => 'A cat']]);
t('several photos skip the description', $make('photo', ['alt' => 'A cat'], ['https://i.example/a.jpg', 'https://i.example/b.jpg'])['photo'] === ['https://i.example/a.jpg', 'https://i.example/b.jpg']);
t('reply ignores a photo link', !isset($make('reply', ['url' => 'https://x.example/', 'content' => 'c', 'photo_url' => 'https://i.example/a.jpg'])['photo']));
t('unknown type refused', refuses(fn() => Compose::toEntry('nope', [])));

// common options
$o = $make('note', ['content' => 'x', 'category' => ' a, ,b ', 'status' => 'draft', 'visibility' => 'unlisted', 'published' => '2026-01-02T03:04', 'slug' => 's']);
t('tags', $o['category'] === ['a', 'b']);
t('draft and visibility', $o['post-status'] === ['draft'] && $o['visibility'] === ['unlisted']);
t('date becomes iso 8601', str_starts_with($o['published'][0], '2026-01-02T03:04:00'));
t('slug is a command', $o['mp-slug'] === ['s']);
t('bad date refused', refuses(fn() => $make('note', ['content' => 'x', 'published' => 'never'])));
t('published is the default status', !isset($make('note', ['content' => 'x', 'status' => 'published'])['post-status']));
t('unknown visibility dropped', !isset($make('note', ['content' => 'x', 'visibility' => 'weird'])['visibility']));
t('only offered syndication targets', $make('note', ['content' => 'x', 'syndicate' => ['a', 'evil']], [], ['a', 'b'])['mp-syndicate-to'] === ['a']);
t('array input cannot break a field', refuses(fn() => $make('note', ['content' => ['x']])));

// wire format
$flat = ['content' => ['héllo & co'], 'category' => ['one']];
t('flat uses a form', Compose::isFlat($flat));
t('form body', Compose::formBody($flat) === 'h=entry&content=h%C3%A9llo%20%26%20co&category%5B%5D=one');
t('many values use brackets', Compose::formBody(['photo' => ['a', 'b']]) === 'h=entry&photo%5B%5D=a&photo%5B%5D=b');
t('nested needs json', !Compose::isFlat($make('listen', ['song' => 'S'])));

// uploads are judged by their bytes
$png = tempnam(sys_get_temp_dir(), 'mp'); file_put_contents($png, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));
$file = Micropub::checkedFile($png, filesize($png), '../../my pic.png');
t('real image passes', $file['type'] === 'image/png' && $file['name'] === 'my-pic.png');
$php = tempnam(sys_get_temp_dir(), 'mp'); file_put_contents($php, '<?php echo 1;');
t('php named as a photo refused', refuses(fn() => Micropub::checkedFile($php, filesize($php), 'x.png')));
$svg = tempnam(sys_get_temp_dir(), 'mp'); file_put_contents($svg, '<svg xmlns="http://www.w3.org/2000/svg"><script>1</script></svg>');
t('svg refused', refuses(fn() => Micropub::checkedFile($svg, filesize($svg), 'x.svg')));
t('oversize refused', refuses(fn() => Micropub::checkedFile($png, Micropub::MAX_UPLOAD_BYTES + 1, 'x.png')));
t('empty refused', refuses(fn() => Micropub::checkedFile($png, 0, 'x.png')));
array_map('unlink', [$png, $php, $svg]);

// server answers
t('scope error is friendly', str_contains(Micropub::errorMessage(['status' => 403, 'body' => '{"error":"insufficient_scope"}']), 'permission'));
t('expired token is friendly', str_contains(Micropub::errorMessage(['status' => 401, 'body' => '{}']), 'expired'));
t('server detail is capped', mb_strlen(Micropub::errorMessage(['status' => 400, 'body' => json_encode(['error_description' => str_repeat('x', 5000)])])) < 360);
t('html error page does not leak', !str_contains(Micropub::errorMessage(['status' => 500, 'body' => '<h1>boom</h1>']), 'boom'));

// escaping
t('escape covers attributes', e('"><script>\'') === '&quot;&gt;&lt;script&gt;&#039;');

echo $fail === 0 ? "OK: all $n passed\n" : "$fail of $n failed\n";
exit($fail === 0 ? 0 : 1);
