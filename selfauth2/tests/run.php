<?php
// Minimal dependency-free tests: php tests/run.php
spl_autoload_register(function ($c) {
    $f = __DIR__ . '/../src/' . str_replace('Selfauth\\', '', $c) . '.php';
    if (is_file($f)) require $f;
});
require __DIR__ . '/FakeGitHub.php';
require __DIR__ . '/FakeB2.php';
use Selfauth\{AccessTokens, AuthCodes, B2Store, ClientId, GitHubStore, Micropub, MicropubException, SafeHttp, Scopes};

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

// access tokens
$pdo = new PDO('sqlite::memory:'); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$issued = AccessTokens::issue($pdo, 'https://c.example/', 'create profile', 'https://me.example/');
t('token issued with default ttl', $issued['expires_in'] === AccessTokens::DEFAULT_TTL);
t('plaintext token not stored', (int) $pdo->query("SELECT COUNT(*) FROM access_tokens WHERE token_hash = '{$issued['token']}'")->fetchColumn() === 0);
$row = AccessTokens::find($pdo, $issued['token']);
t('token found with scope', $row !== null && $row['scope'] === 'create profile');
t('unknown token not found', AccessTokens::find($pdo, 'sfat_nope') === null);
t('introspection has required fields', ($i = AccessTokens::introspection($row))['active'] === true && $i['me'] === 'https://me.example/' && $i['exp'] > time());
t('use is recorded', AccessTokens::authenticate($pdo, $issued['token'])['id'] === $row['id'] && (int) $pdo->query('SELECT COUNT(*) FROM access_tokens WHERE last_used_at IS NOT NULL')->fetchColumn() === 1);
AccessTokens::revoke($pdo, $issued['token']);
t('revoked token rejected', AccessTokens::find($pdo, $issued['token']) === null);
$expiring = AccessTokens::issue($pdo, 'https://c.example/', 'create', 'https://me.example/', 100);
$pdo->exec('UPDATE access_tokens SET expires_at = ' . (time() - 1));
t('expired token rejected', AccessTokens::find($pdo, $expiring['token']) === null);
$byId = AccessTokens::issue($pdo, 'https://c.example/', 'create', 'https://me.example/');
AccessTokens::revokeById($pdo, (int) AccessTokens::find($pdo, $byId['token'])['id']);
t('revoke by id works', AccessTokens::find($pdo, $byId['token']) === null);

// micropub, against a fake repository
$dir = 'data/site/content/3-blog';
$hand = "---\nTitle: Hand Written\nPublished: 2026-01-02\nLanguage: en\nImage: https://static.example/a.jpg\nStatus: public\n---\nMy own words.\n";
$gh = new FakeGitHub(["$dir/2026-01-02-hand-written.md" => $hand, "$dir/page.md" => "---\nTitle: Blog\n---\n", 'data/site/secret.md' => 'x']);
$b2 = new FakeB2();
$b2Store = new B2Store('fake-key', 'fake-app', 'fake-bucket', 'https://b2.test', [$b2, 'handle']);
$mp = new Micropub(new GitHubStore('me/site', 'main', 'pat', 'https://gh.test', [$gh, 'handle']), [
    'site' => 'https://me.example/', 'author' => 'Me', 'content_path' => 'data/site/content',
    'sections' => ['blog' => '3-blog'], 'language' => 'en', 'media_prefix' => 'media/micropub', 'media_url' => 'https://cdn.example/',
], $b2Store);
$entry = fn(array $p) => ['type' => ['h-entry'], 'properties' => $p];
$fileFor = function (string $url) use ($gh, $dir) {
    foreach ($gh->files as $path => $c) {
        if (str_starts_with($path, "$dir/") && Micropub::fileSlug(basename($path)) === basename($url)) return $c;
    }
    return null;
};
$rejects = function (callable $f, int $status) { try { $f(); return false; } catch (MicropubException $e) { return $e->status === $status; } };

$url = $mp->create($entry(['name' => ['Hello World'], 'content' => ['Body **text**'], 'category' => ['a', 'b'], 'published' => ['2026-03-04T10:00:00Z']]));
$f = $fileFor($url);
t('article url', $url === 'https://me.example/blog/hello-world');
t('article is dated and in blog dir', count(array_filter(array_keys($gh->files), fn($p) => preg_match("#^$dir/2026-03-0[34]-hello-world\.md$#", $p))) === 1);
t('article frontmatter', str_contains($f, "Title: Hello World\n") && str_contains($f, "Author: Me\n") && str_contains($f, "Layout: blog\n") && str_contains($f, "Tag: a, b\n") && str_contains($f, "Status: public\n"));
t('article has no generated flag', !str_contains($f, 'Title-Generated'));
t('commit message follows the Post: convention', end($gh->commits) === 'Post: Hello World');
t('body kept', str_contains($f, "---\nBody **text**\n"));
t('same title gets a new slug', $mp->create($entry(['name' => ['Hello World'], 'content' => ['2']])) === 'https://me.example/blog/hello-world-2');
t('slug collides with hand post across dates', $mp->create($entry(['name' => ['Hand Written']])) === 'https://me.example/blog/hand-written-2');

$noteUrl = $mp->create($entry(['content' => ['Just a short note about nothing']]));
$f = $fileFor($noteUrl);
t('untitled post gets generated title', str_contains($f, "Title: Just a short note about nothing\nPublished") && str_contains($f, "Title-Generated: true\n"));
t('generated title is not exposed as name', !isset($mp->source($noteUrl)['properties']['name']));
$mp->update($noteUrl, ['replace' => ['name' => ['Real Name']]]);
$f = $fileFor($noteUrl);
t('naming a post clears the generated flag', str_contains($f, "Title: Real Name\n") && !str_contains($f, 'Title-Generated') && $mp->source($noteUrl)['properties']['name'] === ['Real Name']);

t('mp-slug used and not stored', ($u = $mp->create($entry(['name' => ['T'], 'mp-slug' => ['../../Evil Slug!']]))) === 'https://me.example/blog/evil-slug' && !str_contains($fileFor($u), 'mp-slug'));
t('page slug reserved', Micropub::slugify('Page', time()) === 'page-post');
t('unsluggable text falls back', str_starts_with(Micropub::slugify('日本語', time()), 'post-'));

$u = $mp->create($entry(['name' => ["Evil\nStatus: draft\nLayout: x"], 'category' => ["t\nImage: bad"], 'summary' => ["s\r\nFoo: bar"]]));
[$meta] = Micropub::parse($fileFor($u));
t('newlines cannot inject frontmatter', !isset($meta['Layout']) || $meta['Layout'] === 'blog');
t('injected keys absent', $meta['Status'] === 'public' && !isset($meta['Foo']) && !isset($meta['Image']));

t('post with only an unsafe url is refused', $rejects(fn() => $mp->create($entry(['like-of' => ['https://x.example/a?b=1&c="><script>'], 'content' => ['']])), 400));
$u = $mp->create($entry(['like-of' => ['https://x.example/a?b=1&c="><script>'], 'content' => ['Kept']]));
t('unsafe url is dropped from a post with content', !str_contains((string) $fileFor($u), '<script>') || str_contains((string) $fileFor($u), 'Micropub: '));
t('unsafe url never reaches the rendered body', !str_contains(explode("\n---\n", (string) $fileFor($u), 2)[1], '<script>'));
$u = $mp->create($entry(['like-of' => ['https://x.example/post'], 'photo' => [['value' => 'https://i.example/p.jpg', 'alt' => 'a "quoted" alt']], 'content' => ['Nice']]));
$f = $fileFor($u);
t('like uses site markup', str_contains($f, '<a href="https://x.example/post" class="u-like-of">'));
t('photo rendered and escaped', str_contains($f, 'src="https://i.example/p.jpg" alt="a &quot;quoted&quot; alt"'));
t('first photo becomes Image', str_contains($f, "Image: https://i.example/p.jpg\n"));
t('source round trips like-of and content', ($s = $mp->source($u)['properties'])['like-of'] === ['https://x.example/post'] && $s['content'] === ['Nice']);
t('javascript url refused', $rejects(fn() => $mp->create($entry(['like-of' => ['javascript:alert(1)']])), 400));
$mp->update($u, ['replace' => ['content' => ['Changed']]]);
$f = $fileFor($u);
t('context block regenerated once', substr_count($f, 'u-like-of') === 1 && str_contains($f, "Changed\n") && !str_contains($f, 'Nice'));

t('draft status', str_contains($fileFor($mp->create($entry(['name' => ['D'], 'post-status' => ['draft']]))), "Status: draft\n"));
t('private becomes draft', str_contains($fileFor($mp->create($entry(['name' => ['P'], 'visibility' => ['private']]))), "Status: draft\n"));
t('unlisted', str_contains($fileFor($mp->create($entry(['name' => ['U'], 'visibility' => ['unlisted']]))), "Status: unlisted\n"));
t('empty post refused', $rejects(fn() => $mp->create($entry(['content' => ['  ']])), 400));
t('h-card refused', $rejects(fn() => $mp->create(['type' => ['h-card'], 'properties' => []]), 400));
t('scalar property refused', $rejects(fn() => $mp->create(['type' => ['h-entry'], 'properties' => ['name' => 'x']]), 400));
$form = Micropub::fromForm(['h' => 'entry', 'content' => 'hi', 'category' => ['a', 'b'], 'access_token' => 'secret', 'mp-slug' => 's']);
t('form is normalised without the token', $form['type'] === ['h-entry'] && $form['properties']['content'] === ['hi'] && $form['properties']['category'] === ['a', 'b'] && !isset($form['properties']['access_token']));

// hand edits survive updates
$handUrl = 'https://me.example/blog/hand-written';
$mp->update($handUrl, ['add' => ['category' => ['new']]]);
$f = $fileFor($handUrl);
t('unmanaged keys kept', str_contains($f, "Language: en\n") && str_contains($f, "Image: https://static.example/a.jpg\n") && str_contains($f, "Published: 2026-01-02\n"));
t('body kept on metadata update', str_contains($f, "My own words.\n"));
t('add works', str_contains($f, "Tag: new\n"));
t('status untouched unless asked', str_contains($f, "Status: public\n"));
$mp->update($handUrl, ['replace' => ['post-status' => ['draft']]]);
t('status changed when asked', str_contains($fileFor($handUrl), "Status: draft\n"));
$mp->update($handUrl, ['delete' => ['category']]);
t('delete property', !str_contains($fileFor($handUrl), 'Tag:'));
$mp->update($handUrl, ['add' => ['category' => ['x', 'y']]]);
$mp->update($handUrl, ['delete' => ['category' => ['x']]]);
t('delete one value', str_contains($fileFor($handUrl), "Tag: y\n"));
t('empty update refused', $rejects(fn() => $mp->update($handUrl, []), 400));
t('malformed update refused', $rejects(fn() => $mp->update($handUrl, ['replace' => ['name' => 'x']]), 400));
t('malformed delete refused', $rejects(fn() => $mp->update($handUrl, ['delete' => [['x']]]), 400));
t('source filter', array_keys($mp->source($handUrl, ['name'])['properties']) === ['name']);

// urls cannot reach other files
foreach (['https://evil.example/blog/hand-written', 'https://me.example/blog/../secret', 'https://me.example/blog/page', 'https://me.example/other/hand-written', 'https://me.example/blog/a/b', 'not a url'] as $bad) t("bad url refused: $bad", $rejects(fn() => $mp->delete($bad), 400));
t('missing post is 404', $rejects(fn() => $mp->source('https://me.example/blog/nope'), 404));
t('page.md untouched', isset($gh->files["$dir/page.md"]));
$mp->delete($handUrl);
t('delete removes file', $fileFor($handUrl) === null && isset($gh->files['data/site/secret.md']));

// stale writes fail instead of overwriting
$stale = new class($gh) {
    public function __construct(private FakeGitHub $gh) {}
    public function handle($m, $u, $h, $b) {
        if ($m === 'PUT') { $this->gh->files[rawurldecode(explode('/contents/', parse_url($u)['path'])[1])] .= 'edit'; }
        return $this->gh->handle($m, $u, $h, $b);
    }
};
$racing = new Micropub(new GitHubStore('me/site', 'main', 'pat', 'https://gh.test', [$stale, 'handle']), ['site' => 'https://me.example/', 'author' => '', 'content_path' => 'data/site/content', 'sections' => ['blog' => '3-blog']]);
t('stale write is a 409 the client can act on', $rejects(fn() => $racing->update('https://me.example/blog/hello-world', ['replace' => ['content' => ['x']]]), 409));

// media
$png = tempnam(sys_get_temp_dir(), 'mp'); file_put_contents($png, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));
$commitsBefore = count($gh->commits); $filesBefore = count($gh->files);
$mediaUrl = $mp->storeMedia($png);
t('media url and extension', (bool) preg_match('#^https://cdn\.example/media/micropub/\d{4}/\d{2}/[0-9a-f]{16}\.png$#', $mediaUrl));
$key = substr($mediaUrl, strlen('https://cdn.example/'));
t('media stored in b2 intact', ($b2->files[$key]['bytes'] ?? null) === file_get_contents($png) && $b2->files[$key]['type'] === 'image/png');
t('media never touches the repository', count($gh->commits) === $commitsBefore && count($gh->files) === $filesBefore);
$b2->failUploads = [503]; $count = count($b2->files);
t('busy pod is retried with a fresh upload url', is_string($mp->storeMedia($png)) && count($b2->files) === $count + 1 && $b2->failUploads === []);
$b2->failUploads = [401];
t('expired upload token is retried', is_string($mp->storeMedia($png)));
$b2->failUploads = [503, 503];
try { $mp->storeMedia($png); t('second failure gives up', false); } catch (RuntimeException $e) { t('second failure gives up', !($e instanceof MicropubException) && !str_contains($e->getMessage(), 'fake-app')); }
$b2->failUploads = [400]; $before = $b2->authorizations;
try { $b2Store->upload('a/b.png', 'x', 'image/png'); t('bad request is not retried', false); } catch (RuntimeException $e) { t('bad request is not retried', $b2->authorizations === $before + 1); }
$wrong = new B2Store('fake-key', 'wrong-secret', 'fake-bucket', 'https://b2.test', [$b2, 'handle']);
try { $wrong->upload('a/b.png', 'x', 'image/png'); t('bad credentials fail', false); } catch (RuntimeException $e) { t('bad credentials fail without leaking', !str_contains($e->getMessage(), 'wrong-secret')); }
$otherBucket = new B2Store('fake-key', 'fake-app', 'nope', 'https://b2.test', [$b2, 'handle']);
try { $otherBucket->upload('a/b.png', 'x', 'image/png'); t('wrong bucket fails', false); } catch (RuntimeException $e) { t('wrong bucket fails', true); }
$b2Store->upload('media/a b/c.png', 'x', 'image/png');
t('file names are percent-encoded per segment', isset($b2->files['media/a b/c.png']));
t('without b2 media is unavailable', $rejects(fn() => $racing->storeMedia($png), 503));
$svg = tempnam(sys_get_temp_dir(), 'mp'); file_put_contents($svg, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
t('svg refused', $rejects(fn() => $mp->storeMedia($svg), 415));
$php = tempnam(sys_get_temp_dir(), 'mp'); file_put_contents($php, '<?php echo 1;');
t('php refused', $rejects(fn() => $mp->storeMedia($php), 415));
$empty = tempnam(sys_get_temp_dir(), 'mp');
t('empty refused', $rejects(fn() => $mp->storeMedia($empty), 400));
array_map('unlink', [$png, $svg, $php, $empty]);

// details borrowed from the Datenstrom editor script
t('accents become readable slugs', Micropub::slugify('Cañón en Größe, café', time()) === 'canon-en-groesse-cafe');
t('curly apostrophe matches existing slugs', Micropub::slugify('Bay Area’s 11th', time()) === 'bay-area-s-11th');
$u = $mp->create($entry(['name' => ['Crlf'], 'content' => ["one\r\ntwo"]]));
t('windows line endings normalised', str_contains($fileFor($u), "one\ntwo\n"));
t('language written when configured', str_contains($fileFor($u), "Language: en\n"));
// file format
t('no frontmatter parses', Micropub::parse('plain') === [[], 'plain']);
[$m2, $b2] = Micropub::parse("---\nTitle: A: B\nodd line\n---\n---\nbody");
t('frontmatter parse keeps colons and odd lines', $m2['Title'] === 'A: B' && in_array('odd line', $m2, true) && $b2 === "---\nbody");

echo $fail === 0 ? "OK: all $n passed\n" : "$fail of $n failed\n";
exit($fail === 0 ? 0 : 1);
