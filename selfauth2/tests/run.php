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
t('missing post is invalid_request', $rejects(fn() => $mp->source('https://me.example/blog/nope'), 400));
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

// routing: articles to the blog, everything else to notes
$notesDir = 'data/site/content/4-notes';
$rg = new FakeGitHub(["$dir/page.md" => "---\nTitle: Blog\nLayout: blog-start\n---\n", "$dir/roll.md" => "---\nTitle: Roll\nLayout: blogroll\n---\n", "$dir/2020-01-01-old.md" => "---\nTitle: Old\n---\nNo layout.\n", "$notesDir/page.md" => "---\nTitle: Notes\nLayout: notes-start\n---\n", "$notesDir/archive.md" => "---\nTitle: Archive\nLayout: notes-archive\n---\n"]);
$rp = new Micropub(new GitHubStore('me/site', 'main', 'pat', 'https://gh.test', [$rg, 'handle']), ['site' => 'https://me.example/', 'author' => 'Me', 'content_path' => 'data/site/content', 'sections' => ['blog' => '3-blog', 'notes' => '4-notes'], 'media_prefix' => 'media/micropub', 'media_url' => 'https://cdn.example'], $b2Store);
$routedFile = function (string $url) use ($rg) {
    foreach ($rg->files as $path => $c) { if (Micropub::fileSlug(basename($path)) === basename($url) && str_contains($path, str_contains($url, '/notes/') ? '4-notes' : '3-blog')) return $c; }
    return '';
};
$routes = [
    'article' => [['name' => ['A Real Article'], 'content' => ['Long body text here.']], 'blog', 'blog', false],
    'article with html content' => [['name' => ['Html Article'], 'content' => [['html' => '<p>Some <b>html</b> body</p>']]], 'blog', 'blog', false],
    'article with a hero photo' => [['name' => ['Trip Report'], 'content' => ['We went.'], 'photo' => ['https://i.example/a.jpg']], 'blog', 'blog', false],
    'plain note' => [['content' => ['Just a thought']], 'notes', 'note', true],
    'note named with its own text' => [['name' => ['Just another thought'], 'content' => ['Just another thought, and more']], 'notes', 'note', true],
    'note named with truncated text' => [['name' => ['Trailing dots...'], 'content' => ['Trailing dots and then the rest']], 'notes', 'note', true],
    'like' => [['like-of' => ['https://x.example/1']], 'notes', 'note', true],
    'titled like with a comment' => [['name' => ['Testing Micropub'], 'like-of' => ['https://x.example/2'], 'content' => ['Awesome link!']], 'notes', 'note', false],
    'reply' => [['in-reply-to' => ['https://x.example/3'], 'content' => ['Agreed']], 'notes', 'note', true],
    'titled reply' => [['name' => ['A Reply'], 'in-reply-to' => ['https://x.example/4'], 'content' => ['Long reply']], 'notes', 'note', false],
    'repost' => [['repost-of' => ['https://x.example/5']], 'notes', 'note', true],
    'bookmark with its page title' => [['name' => ['Good Page'], 'bookmark-of' => ['https://x.example/6'], 'content' => ['Worth reading']], 'notes', 'note', false],
    'rsvp' => [['in-reply-to' => ['https://x.example/7'], 'rsvp' => ['yes']], 'notes', 'note', true],
    'photo' => [['photo' => ['https://i.example/b.jpg']], 'notes', 'note', true],
    'captioned photo' => [['photo' => ['https://i.example/c.jpg'], 'content' => ['Sunset']], 'notes', 'note', true],
    'titled photo without text' => [['name' => ['Sunset Pic'], 'photo' => ['https://i.example/d.jpg']], 'notes', 'note', false],
    'video' => [['name' => ['A Clip'], 'video' => ['https://i.example/v.mp4'], 'content' => ['Watch']], 'notes', 'note', false],
    'audio' => [['audio' => ['https://i.example/a.mp3']], 'notes', 'note', true],
    'checkin' => [['checkin' => ['https://x.example/place'], 'content' => ['Here']], 'notes', 'note', true],
    'listen' => [['name' => ['Song'], 'listen-of' => ['https://x.example/song'], 'content' => ['Great track']], 'notes', 'note', false],
    'title with no text' => [['name' => ['Only A Title']], 'notes', 'note', false],
];
foreach ($routes as $label => [$props, $section, $layout, $generated]) {
    $u = $rp->create($entry($props));
    $f = $routedFile($u);
    t("route $label: url", str_starts_with($u, "https://me.example/$section/"));
    t("route $label: layout", str_contains($f, "Layout: $layout\n"));
    t("route $label: generated title flag", str_contains($f, 'Title-Generated: true') === $generated);
}
$u = $rp->create($entry(['in-reply-to' => ['https://x.example/hwc'], 'rsvp' => ['yes']]));
t('rsvp uses site markup', str_contains($routedFile($u), 'RSVPs <span class="p-rsvp">yes</span> to <a href="https://x.example/hwc" class="u-in-reply-to">'));
t('rsvp title', str_contains($routedFile($u), 'Title: RSVP yes: https://x.example/hwc'));
$u = $rp->create($entry(['in-reply-to' => ['https://x.example/hwc2'], 'rsvp' => ['<b>x</b>']]));
t('unknown rsvp value is not rendered', !str_contains(explode("\n---\n", $routedFile($u), 2)[1], '<b>x</b>') && str_contains($routedFile($u), 'Replying to: '));

// each section has its own slugs, and notes can be managed like posts
t('same slug allowed across sections', $rp->create($entry(['name' => ['Shared Slug'], 'content' => ['Blog body']])) === 'https://me.example/blog/shared-slug' && $rp->create($entry(['name' => ['Shared Slug']])) === 'https://me.example/notes/shared-slug');
t('notes slug avoids the archive page', $rp->create($entry(['name' => ['Archive']])) === 'https://me.example/notes/archive-2');
$u = $rp->create($entry(['content' => ['Editable note']]));
$rp->update($u, ['replace' => ['content' => ['Edited note']]]);
t('note can be updated in place', str_contains($routedFile($u), "Edited note\n") && str_contains($routedFile($u), 'Title: Edited note') && str_contains($routedFile($u), "Layout: note\n"));
t('note source hides generated title', !isset($rp->source($u)['properties']['name']));
$rp->delete($u);
t('note can be deleted', $routedFile($u) === '');

// section pages are not posts, even with a delete-scoped token
t('archive page cannot be deleted', $rejects(fn() => $rp->delete('https://me.example/notes/archive'), 400) && isset($rg->files["$notesDir/archive.md"]));
t('blogroll cannot be edited', $rejects(fn() => $rp->update('https://me.example/blog/roll', ['replace' => ['content' => ['x']]]), 400));
t('section page has no source', $rejects(fn() => $rp->source('https://me.example/notes/archive'), 400));
t('post with no layout is still editable', isset($rp->source('https://me.example/blog/old')['properties']['content']));

// post types: check-ins, listens and RSVPs
$venue = ['type' => ['h-card'], 'properties' => ['name' => ['Castro Theatre'], 'url' => ['https://x.example/castro'], 'locality' => ['San Francisco'], 'region' => ['CA'], 'country-name' => ['USA'], 'latitude' => ['37.7621'], 'longitude' => ['-122.4350']]];
$u = $rp->create($entry(['checkin' => [$venue]]));
$f = $routedFile($u);
t('checkin routes to notes', str_starts_with($u, 'https://me.example/notes/checked-in-at-castro-theatre') && str_contains($f, "Layout: note\n"));
t('checkin title', str_contains($f, "Title: Checked in at Castro Theatre\n") && str_contains($f, 'Title-Generated: true'));
t('checkin markup', str_contains($f, 'Checked in at <span class="p-checkin h-card"><a class="u-url" href="https://x.example/castro"><span class="p-name">Castro Theatre</span></a>, <span class="p-locality">San Francisco</span>, <span class="p-region">CA</span>, <span class="p-country-name">USA</span>'));
t('checkin coordinates', str_contains($f, '<data class="p-latitude" value="37.7621"></data>') && str_contains($f, '<data class="p-longitude" value="-122.435"></data>'));
t('checkin round trips nested', $rp->source($u)['properties']['checkin'][0]['properties']['name'] === ['Castro Theatre']);
$u = $rp->create($entry(['checkin' => ['https://x.example/venue'], 'content' => ['Great show']]));
t('checkin by url keeps comment', str_contains($routedFile($u), 'href="https://x.example/venue"') && str_contains($routedFile($u), "Great show\n"));
$u = $rp->create($entry(['checkin' => [['type' => ['h-card'], 'properties' => ['name' => ['<script>x</script>'], 'latitude' => ['"><b>']]]]]));
t('checkin values are escaped', !str_contains(explode("\n---\n", $routedFile($u), 2)[1], '<script>') && !str_contains($routedFile($u), 'p-latitude'));
t('empty checkin refused', $rejects(fn() => $rp->create($entry(['checkin' => [['type' => ['h-card'], 'properties' => []]]])), 400));

$u = $rp->create($entry(['listen-of' => ['https://x.example/song']]));
t('listen by url', str_starts_with($u, 'https://me.example/notes/') && str_contains($routedFile($u), 'Listened to: <a href="https://x.example/song" class="u-listen-of">'));
$song = ['type' => ['h-cite'], 'properties' => ['name' => ['Everywhere'], 'author' => ['Michelle Branch'], 'url' => ['https://x.example/everywhere']]];
$u = $rp->create($entry(['listen-of' => [$song]]));
$f = $routedFile($u);
t('listen h-cite markup', str_contains($f, '<a href="https://x.example/everywhere" class="u-listen-of h-cite"><span class="p-name">Everywhere</span></a> by <span class="p-author">Michelle Branch</span>'));
t('listen title and slug', str_contains($f, "Title: Listened to: Everywhere by Michelle Branch\n") && str_contains($u, '/notes/listened-to-everywhere-by-michelle-branch'));
$u = $rp->create($entry(['listen-of' => [['type' => ['h-cite'], 'properties' => ['name' => ['No Link'], 'author' => [['type' => ['h-card'], 'properties' => ['name' => ['Nested Artist']]]]]]]]));
t('listen without url, nested author', str_contains($routedFile($u), '<span class="p-listen-of h-cite"><span class="p-name">No Link</span> by <span class="p-author">Nested Artist</span></span>'));
t('watch and read render', str_contains($routedFile($rp->create($entry(['watch-of' => ['https://x.example/film']]))), 'u-watch-of') && str_contains($routedFile($rp->create($entry(['read-of' => ['https://x.example/book']]))), 'u-read-of'));

$u = $rp->create($entry(['in-reply-to' => ['https://x.example/e1'], 'rsvp' => ['YES']]));
t('rsvp value is case-insensitive', str_contains($routedFile($u), '<span class="p-rsvp">yes</span>'));
foreach (['no', 'maybe', 'interested'] as $v) t("rsvp $v", str_contains($routedFile($rp->create($entry(['in-reply-to' => ["https://x.example/$v"], 'rsvp' => [$v]]))), '<span class="p-rsvp">' . $v . '</span>'));
$u = $rp->create($entry(['in-reply-to' => [['type' => ['h-cite'], 'properties' => ['name' => ['Homebrew Website Club'], 'url' => ['https://x.example/hwc3']]]], 'rsvp' => ['yes']]));
t('rsvp to a named event', str_contains($routedFile($u), "Title: RSVP yes: Homebrew Website Club\n") && str_contains($routedFile($u), 'RSVPs <span class="p-rsvp">yes</span> to <a href="https://x.example/hwc3"'));

// location never exposes coordinates on the page
$u = $rp->create($entry(['content' => ['At home'], 'location' => ['geo:37.77,-122.41']]));
t('geo location is kept but not shown', !str_contains(explode("\n---\n", $routedFile($u), 2)[1], '37.77') && $rp->source($u)['properties']['location'] === ['geo:37.77,-122.41']);
$u = $rp->create($entry(['content' => ['Coffee'], 'location' => [['type' => ['h-adr'], 'properties' => ['locality' => ['Oakland'], 'region' => ['CA'], 'latitude' => ['37.8']]]]]));
t('named location is shown without coordinates', str_contains($routedFile($u), 'Location: <span class="p-location">Oakland, CA</span>') && !str_contains(explode("\n---\n", $routedFile($u), 2)[1], '37.8'));
t('plain location is shown', str_contains($routedFile($rp->create($entry(['content' => ['Hi'], 'location' => ['The Mission']]))), 'The Mission</span>'));

// spec: q=source
$u = $rp->create($entry(['name' => ['Html Post'], 'content' => [['html' => '<p>Rich</p>']]]));
t('html content returns as html object', $rp->source($u)['properties']['content'] === [['html' => '<p>Rich</p>']]);
$rp->update($u, ['add' => ['category' => ['x']]]);
t('html flag survives a metadata update', $rp->source($u)['properties']['content'] === [['html' => '<p>Rich</p>']]);
$rp->update($u, ['replace' => ['content' => ['plain now']]]);
t('plain replace clears html flag', $rp->source($u)['properties']['content'] === ['plain now'] && !str_contains($routedFile($u), 'Content-Html'));
t('plain content returns as string', is_string($rp->source($rp->create($entry(['content' => ['Plain']])))['properties']['content'][0]));
t('source has type without a filter', $rp->source($u)['type'] === ['h-entry']);
t('source filter has properties only', !isset($rp->source($u, ['content'])['type']));
t('published is iso 8601', (bool) preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', $rp->source($u)['properties']['published'][0]));

// spec: update
$u = $rp->create($entry(['name' => ['Named Note']]));
$rp->update($u, ['delete' => ['name']]);
t('deleting name leaves an untitled post', str_contains($routedFile($u), 'Title-Generated: true') && !isset($rp->source($u)['properties']['name']));
$u = $rp->create($entry(['content' => ['Unknown'], 'made-up' => ['ignored']]));
t('unknown properties do not block a post or reach the page', !str_contains(explode("\n---\n", $routedFile($u), 2)[1], 'ignored'));
t('mp commands are not stored', !str_contains($routedFile($rp->create($entry(['content' => ['Cmd'], 'mp-syndicate-to' => ['https://x.example']]))), 'mp-syndicate'));

// q=source list extension
$items = $rp->recent(3);
t('recent honors limit', count($items) === 3);
t('recent items have type and url', $items[0]['type'] === ['h-entry'] && str_starts_with($items[0]['properties']['url'][0], 'https://me.example/'));
$dates = array_map(fn($i) => $i['properties']['published'][0] ?? '', $rp->recent(50));
t('recent skips section pages', !array_filter($rp->recent(50), fn($i) => str_ends_with($i['properties']['url'][0], '/archive') || str_ends_with($i['properties']['url'][0], '/page')));

// one-click sign-in trusts one client at one exact callback
putenv('SELFAUTH_TRUSTED_CLIENT_ID=');
t('no trusted client by default', !Selfauth\AuthRequest::isTrustedClient('https://post.example/', 'https://post.example/callback.php'));
putenv('SELFAUTH_TRUSTED_CLIENT_ID=https://post.example');
t('trusted client at its callback', Selfauth\AuthRequest::isTrustedClient('https://post.example/', 'https://post.example/callback.php'));
t('wrong callback is not trusted', !Selfauth\AuthRequest::isTrustedClient('https://post.example/', 'https://post.example/other.php'));
t('other origin callback is not trusted', !Selfauth\AuthRequest::isTrustedClient('https://post.example/', 'https://evil.example/callback.php'));
t('other client is not trusted', !Selfauth\AuthRequest::isTrustedClient('https://evil.example/', 'https://post.example/callback.php'));
t('lookalike host is not trusted', !Selfauth\AuthRequest::isTrustedClient('https://post.example.evil.test/', 'https://post.example.evil.test/callback.php'));
t('one-click scopes exclude editing and deleting', !array_intersect(['update', 'delete'], Selfauth\AuthRequest::TRUSTED_CLIENT_SCOPES));
putenv('SELFAUTH_TRUSTED_CLIENT_ID');

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
