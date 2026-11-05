<?php

namespace Selfauth;

/**
 * Micropub server that stores posts as Yellow CMS Markdown files in a GitHub
 * repository. Each write is a commit, and pushing is what publishes the site.
 *
 * Frontmatter stays the source of truth for the fields Yellow understands, and
 * the Markdown body for `content`, so hand edits survive a Micropub update.
 * Anything else (photo, like-of, ...) is kept as JSON in a `Micropub:` line and
 * rendered into a marked block at the top of the body, regenerated on each write.
 */
class Micropub
{
    public const MAX_UPLOAD_BYTES = 10485760; // 10 MB
    private const MEDIA_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];
    private const SLUG_LETTERS = [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'å' => 'a', 'ä' => 'ae', 'æ' => 'ae',
        'ç' => 'c', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i', 'ñ' => 'n',
        'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ø' => 'o', 'ö' => 'oe', 'œ' => 'oe',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'ue', 'ý' => 'y', 'ÿ' => 'y', 'ß' => 'ss',
    ];
    private const LAYOUTS = ['blog' => 'blog', 'notes' => 'note'];
    /** Having any of these makes a post something other than an article, even with a title. */
    private const NON_ARTICLE = ['in-reply-to', 'rsvp', 'checkin', 'video', 'audio'];
    private const RSVP_VALUES = ['yes', 'no', 'maybe', 'interested'];
    /** Marks a Title made from the post's text, which clients must not see as a real name. */
    private const GENERATED_TITLE = 'Title-Generated';
    private const BLOCK_OPEN = '<!-- micropub:context -->';
    private const BLOCK_CLOSE = '<!-- /micropub:context -->';
    private const INTERACTIONS = [
        'like-of' => ['Liked', 'u-like-of'],
        'in-reply-to' => ['Replying to', 'u-in-reply-to'],
        'repost-of' => ['Reposted', 'u-repost-of'],
        'bookmark-of' => ['Bookmarked', 'u-bookmark-of'],
    ];
    /** Properties stored in their own frontmatter key or the body, not the JSON line. */
    private const NATIVE = ['name', 'content', 'category', 'published', 'summary', 'post-status', 'visibility'];

    private GitHubStore $store;
    private ?B2Store $media;
    private array $cfg;

    /** @param array{site:string, author:string, language?:string, content_path:string, sections:array<string,string>, media_prefix?:string, media_url?:string} $cfg */
    public function __construct(GitHubStore $store, array $cfg, ?B2Store $media = null)
    {
        $this->store = $store;
        $this->cfg = $cfg;
        $this->media = $media;
    }

    /** Media needs all of these, while posts work without them. */
    public static function mediaConfigured(): bool
    {
        foreach (['B2_KEY_ID', 'B2_APP_KEY', 'B2_BUCKET_ID', 'MEDIA_URL'] as $name) {
            if ((string) getenv('SELFAUTH_MICROPUB_' . $name) === '') {
                return false;
            }
        }
        return true;
    }

    /** Null unless enabled and fully configured. The token is never read elsewhere. */
    public static function fromEnv(string $site): ?self
    {
        $token = (string) getenv('SELFAUTH_MICROPUB_GITHUB_TOKEN');
        $repo = (string) getenv('SELFAUTH_MICROPUB_GITHUB_REPO');
        if (!self::isEnabled() || $token === '' || !preg_match('#^[\w.-]+/[\w.-]+$#', $repo) || $site === '') {
            return null;
        }
        $store = new GitHubStore(
            $repo,
            getenv('SELFAUTH_MICROPUB_GITHUB_BRANCH') ?: 'main',
            $token,
            getenv('SELFAUTH_MICROPUB_GITHUB_API') ?: 'https://api.github.com'
        );
        $media = !self::mediaConfigured() ? null : new B2Store(
            (string) getenv('SELFAUTH_MICROPUB_B2_KEY_ID'),
            (string) getenv('SELFAUTH_MICROPUB_B2_APP_KEY'),
            (string) getenv('SELFAUTH_MICROPUB_B2_BUCKET_ID'),
            getenv('SELFAUTH_MICROPUB_B2_API') ?: 'https://api.backblazeb2.com'
        );
        return new self($store, [
            'site' => $site,
            'author' => (string) getenv('SELFAUTH_MICROPUB_AUTHOR'),
            'language' => (string) getenv('SELFAUTH_MICROPUB_LANGUAGE'),
            'content_path' => trim(getenv('SELFAUTH_MICROPUB_CONTENT_PATH') ?: 'content', '/'),
            'sections' => array_filter([
                'blog' => getenv('SELFAUTH_MICROPUB_BLOG_DIR') ?: 'blog',
                'notes' => (string) getenv('SELFAUTH_MICROPUB_NOTES_DIR'),
            ], 'strlen'),
            'media_prefix' => trim(getenv('SELFAUTH_MICROPUB_MEDIA_PREFIX') ?: 'media/micropub', '/'),
            'media_url' => rtrim((string) getenv('SELFAUTH_MICROPUB_MEDIA_URL'), '/'),
        ], $media);
    }

    public static function isEnabled(): bool
    {
        return filter_var(getenv('SELFAUTH_MICROPUB_ENABLED') ?: 'false', FILTER_VALIDATE_BOOLEAN);
    }

    // ------------------------------------------------------------------
    // Request normalisation
    // ------------------------------------------------------------------

    /** Form-encoded body -> mf2 JSON shape. */
    public static function fromForm(array $post): array
    {
        $props = [];
        foreach ($post as $key => $value) {
            if (in_array($key, ['access_token', 'h', 'action', 'url'], true)) {
                continue;
            }
            $isList = is_array($value) && array_is_list($value);
            $props[$key] = $isList ? $value : [$value];
        }
        return ['type' => ['h-' . ($post['h'] ?? 'entry')], 'properties' => $props];
    }

    /** Checks the mf2 shape, and strips server commands out of the properties. */
    private static function checkedProperties(array $mf2): array
    {
        if (($mf2['type'] ?? null) !== ['h-entry']) {
            throw new MicropubException(400, 'invalid_request', 'Only h-entry posts are supported');
        }
        $props = $mf2['properties'] ?? null;
        if (!is_array($props)) {
            throw new MicropubException(400, 'invalid_request', 'properties is required');
        }
        foreach ($props as $key => $values) {
            if (!is_string($key) || !is_array($values) || !array_is_list($values)) {
                throw new MicropubException(400, 'invalid_request', 'Each property must be a list of values');
            }
        }
        return $props;
    }

    // ------------------------------------------------------------------
    // Operations
    // ------------------------------------------------------------------

    /** @return string The new post's URL. */
    public function create(array $mf2): string
    {
        $props = self::checkedProperties($mf2);
        $slugHint = self::firstString($props['mp-slug'] ?? []);
        $props = self::withoutCommands($props);
        $name = trim(self::firstString($props['name'] ?? []));
        $body = self::contentOf($props);
        if ($name === '' && trim($body) === '' && !self::hasContext($props)) {
            throw new MicropubException(400, 'invalid_request', 'A post needs a name, content, a photo or a link');
        }

        // Articles go to the blog and everything else to notes. Without a notes section, all go to the blog.
        $section = !self::isArticle($props, $name, $body) && isset($this->cfg['sections']['notes']) ? 'notes' : 'blog';
        $hasRealName = $name !== '' && !self::nameRepeatsText($name, $body);
        $published = self::parseDate(self::firstString($props['published'] ?? []));
        $title = $hasRealName ? $name : self::titleFrom($props, $body);
        $slug = $this->uniqueSlug($section, self::slugify($slugHint !== '' ? $slugHint : $title, $published));
        $path = $this->sectionPath($section) . '/' . date('Y-m-d', $published) . '-' . $slug . '.md';

        $meta = ['Title' => self::oneLine($title), 'Published' => date('Y-m-d H:i:s', $published)];
        if ($this->cfg['author'] !== '') {
            $meta['Author'] = self::oneLine($this->cfg['author']);
        }
        if (($this->cfg['language'] ?? '') !== '') {
            $meta['Language'] = self::oneLine($this->cfg['language']);
        }
        $meta['Layout'] = self::LAYOUTS[$section];
        if (!$hasRealName) {
            $meta[self::GENERATED_TITLE] = 'true';
        }
        $file = self::render($meta, $props, $body, true, true);
        $this->store->put($path, $file, 'Post: ' . self::oneLine($title));
        return $this->urlFor($section, $slug);
    }

    /** @param array{replace?:array, add?:array, delete?:array} $changes */
    public function update(string $url, array $changes): void
    {
        [$path, $file] = $this->locate($url);
        [$meta, $body] = self::parse($file['content']);
        $props = self::toProperties($meta, $body);
        $touched = [];

        foreach (['replace', 'add'] as $op) {
            if (!isset($changes[$op])) {
                continue;
            }
            if (!is_array($changes[$op]) || array_is_list($changes[$op])) {
                throw new MicropubException(400, 'invalid_request', $op . ' must map properties to lists of values');
            }
            foreach ($changes[$op] as $key => $values) {
                if (!is_array($values) || !array_is_list($values)) {
                    throw new MicropubException(400, 'invalid_request', $op . ' values must be lists');
                }
                $props[$key] = $op === 'replace' ? $values : array_merge($props[$key] ?? [], $values);
                $touched[] = $key;
            }
        }
        if (isset($changes['delete'])) {
            if (!is_array($changes['delete'])) {
                throw new MicropubException(400, 'invalid_request', 'delete must be a list or a map');
            }
            foreach ($changes['delete'] as $key => $values) {
                if (array_is_list($changes['delete'])) {
                    if (!is_string($values)) {
                        throw new MicropubException(400, 'invalid_request', 'delete must list property names');
                    }
                    unset($props[$values]);
                    $touched[] = $values;
                    continue;
                }
                if (!is_array($values)) {
                    throw new MicropubException(400, 'invalid_request', 'delete values must be lists');
                }
                $props[$key] = array_values(array_filter($props[$key] ?? [], fn($v) => !in_array($v, $values, true)));
                if ($props[$key] === []) {
                    unset($props[$key]);
                }
                $touched[] = $key;
            }
        }
        if ($touched === []) {
            throw new MicropubException(400, 'invalid_request', 'Nothing to update');
        }
        $props = self::withoutCommands($props);

        if (in_array('name', $touched, true)) {
            $title = trim(self::firstString($props['name'] ?? []));
            if ($title !== '') {
                $meta['Title'] = self::oneLine($title);
                unset($meta[self::GENERATED_TITLE]);
            }
        }
        if (in_array('published', $touched, true) && isset($props['published'])) {
            $meta['Published'] = date('Y-m-d H:i:s', self::parseDate(self::firstString($props['published'])));
        }
        $touchesStatus = (bool) array_intersect(['post-status', 'visibility'], $touched);
        $content = in_array('content', $touched, true) ? self::contentOf($props) : self::stripBlock($body);
        if (isset($meta[self::GENERATED_TITLE]) && in_array('content', $touched, true)) {
            $meta['Title'] = self::oneLine(self::titleFrom($props, $content));
        }
        $this->store->put($path, self::render($meta, $props, $content, $touchesStatus, false), 'Update post: ' . self::oneLine((string) ($meta['Title'] ?? basename($path))), $file['sha']);
    }

    public function delete(string $url): void
    {
        [$path, $file] = $this->locate($url);
        [$meta] = self::parse($file['content']);
        $this->store->delete($path, $file['sha'], 'Delete post: ' . self::oneLine((string) ($meta['Title'] ?? basename($path))));
    }

    /** @param string[] $only Limit to these properties. */
    public function source(string $url, array $only = []): array
    {
        [, $file] = $this->locate($url);
        [$meta, $body] = self::parse($file['content']);
        $props = self::toProperties($meta, $body);
        if ($only !== []) {
            return ['properties' => array_intersect_key($props, array_flip($only))];
        }
        return ['type' => ['h-entry'], 'properties' => $props];
    }

    /** Validates an uploaded image and stores it in B2. @return string Its public URL. */
    public function storeMedia(string $tmpPath): string
    {
        if ($this->media === null) {
            throw new MicropubException(503, 'temporarily_unavailable', 'Media uploads are not configured on this server');
        }
        $size = filesize($tmpPath);
        if ($size === false || $size === 0) {
            throw new MicropubException(400, 'invalid_request', 'The uploaded file is empty');
        }
        if ($size > self::MAX_UPLOAD_BYTES) {
            throw new MicropubException(413, 'invalid_request', 'The file is larger than 10 MB');
        }
        // Trust the bytes, not the client's type or name. SVG is left out on purpose: it can carry scripts.
        $type = (new \finfo(FILEINFO_MIME_TYPE))->file($tmpPath);
        $ext = self::MEDIA_TYPES[$type] ?? null;
        if ($ext === null) {
            throw new MicropubException(415, 'invalid_request', 'Only JPEG, PNG, GIF and WebP images are accepted');
        }
        $name = date('Y/m') . '/' . bin2hex(random_bytes(8)) . '.' . $ext;
        $key = ($this->cfg['media_prefix'] ?? 'media/micropub') . '/' . $name;
        $this->media->upload($key, (string) file_get_contents($tmpPath), $type);
        return rtrim((string) ($this->cfg['media_url'] ?? ''), '/') . '/' . $key;
    }

    // ------------------------------------------------------------------
    // File format
    // ------------------------------------------------------------------

    /** @return array{0:array, 1:string} Frontmatter (unparsable lines under numeric keys) and body. */
    public static function parse(string $text): array
    {
        if (!preg_match('/\A---\r?\n(.*?)\r?\n---[ \t]*(?:\r?\n|\z)(.*)\z/s', $text, $m)) {
            return [[], $text];
        }
        $meta = [];
        foreach (preg_split('/\r?\n/', $m[1]) as $line) {
            if (preg_match('/^([A-Za-z][\w-]*):[ \t]*(.*)$/', $line, $kv)) {
                $meta[$kv[1]] = $kv[2];
            } else {
                $meta[] = $line;
            }
        }
        return [$meta, $m[2]];
    }

    public static function toProperties(array $meta, string $body): array
    {
        $props = [];
        $extra = json_decode((string) ($meta['Micropub'] ?? ''), true);
        if (is_array($extra)) {
            $props = $extra;
        }
        if (!isset($meta[self::GENERATED_TITLE]) && ($meta['Title'] ?? '') !== '') {
            $props['name'] = [$meta['Title']];
        }
        if (($meta['Published'] ?? '') !== '') {
            $props['published'] = [$meta['Published']];
        }
        if (($meta['Tag'] ?? '') !== '') {
            $props['category'] = array_values(array_filter(array_map('trim', explode(',', $meta['Tag'])), 'strlen'));
        }
        if (($meta['Description'] ?? '') !== '') {
            $props['summary'] = [$meta['Description']];
        }
        $status = strtolower((string) ($meta['Status'] ?? ''));
        if ($status === 'draft') {
            $props['post-status'] = ['draft'];
        } elseif ($status === 'public' || $status === 'unlisted') {
            $props['post-status'] = ['published'];
            if ($status === 'unlisted') {
                $props['visibility'] = ['unlisted'];
            }
        }
        $content = trim(self::stripBlock($body));
        if ($content !== '') {
            $props['content'] = [$content];
        }
        return $props;
    }

    /** Writes the properties into the frontmatter. Keys it does not manage are kept in place. */
    private static function render(array $meta, array $props, string $content, bool $setStatus, bool $isNew): string
    {
        foreach (['category' => 'Tag', 'summary' => 'Description'] as $prop => $key) {
            $values = array_map(fn($v) => self::oneLine(is_scalar($v) ? (string) $v : ''), $props[$prop] ?? []);
            $values = array_values(array_filter($values, 'strlen'));
            if ($values === []) {
                unset($meta[$key]);
            } else {
                $meta[$key] = implode(', ', $values);
            }
        }
        if ($setStatus || $isNew) {
            $meta['Status'] = self::statusOf($props);
        }

        $extra = array_diff_key($props, array_flip(self::NATIVE));
        if ($extra === []) {
            unset($meta['Micropub']);
        } else {
            // json_encode escapes newlines, so this can never break out of its line.
            $meta['Micropub'] = json_encode($extra, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        $photo = self::urlOf(($props['photo'] ?? [null])[0]);
        if ($photo !== null && !isset($meta['Image'])) {
            $meta['Image'] = $photo;
        }

        $lines = ['---'];
        foreach ($meta as $key => $value) {
            $lines[] = is_int($key) ? $value : $key . ': ' . $value;
        }
        $lines[] = '---';
        $block = self::contextBlock($props);
        return implode("\n", $lines) . "\n" . ($block === '' ? '' : $block . "\n\n") . trim($content) . "\n";
    }

    private static function statusOf(array $props): string
    {
        if (self::firstString($props['post-status'] ?? []) === 'draft') {
            return 'draft';
        }
        $visibility = self::firstString($props['visibility'] ?? []);
        // Yellow has no private posts, and a draft is the only state that stays hidden.
        return match ($visibility) {
            'private' => 'draft',
            'unlisted' => 'unlisted',
            default => 'public',
        };
    }

    private static function hasContext(array $props): bool
    {
        return self::contextBlock($props) !== '';
    }

    private static function contextBlock(array $props): string
    {
        $parts = [];
        foreach (self::INTERACTIONS as $prop => [$label, $class]) {
            foreach ($props[$prop] ?? [] as $value) {
                $url = self::urlOf($value);
                if ($url !== null) {
                    $safe = htmlspecialchars($url, ENT_QUOTES);
                    $rsvp = $prop === 'in-reply-to' ? self::rsvpOf($props) : null;
                    $intro = $rsvp !== null ? 'RSVPs <span class="p-rsvp">' . $rsvp . '</span> to ' : $label . ': ';
                    $parts[] = $intro . '<a href="' . $safe . '" class="' . $class . '">' . $safe . '</a>';
                }
            }
        }
        foreach ($props['photo'] ?? [] as $value) {
            $url = self::urlOf($value);
            if ($url !== null) {
                $alt = is_array($value) ? (string) ($value['alt'] ?? '') : '';
                $parts[] = '<img class="u-photo" src="' . htmlspecialchars($url, ENT_QUOTES) . '" alt="' . htmlspecialchars(self::oneLine($alt), ENT_QUOTES) . '">';
            }
        }
        foreach (['video', 'audio'] as $tag) {
            foreach ($props[$tag] ?? [] as $value) {
                $url = self::urlOf($value);
                if ($url !== null) {
                    $parts[] = '<' . $tag . ' class="u-' . $tag . '" controls src="' . htmlspecialchars($url, ENT_QUOTES) . '"></' . $tag . '>';
                }
            }
        }
        return $parts === [] ? '' : self::BLOCK_OPEN . "\n" . implode("\n\n", $parts) . "\n" . self::BLOCK_CLOSE;
    }

    private static function stripBlock(string $body): string
    {
        $pattern = '/\A\s*' . preg_quote(self::BLOCK_OPEN, '/') . '.*?' . preg_quote(self::BLOCK_CLOSE, '/') . '\s*/s';
        return (string) preg_replace($pattern, '', $body);
    }

    // ------------------------------------------------------------------
    // Paths and URLs
    // ------------------------------------------------------------------

    /** @return array{0:string, 1:array{content:string, sha:string}} */
    private function locate(string $url): array
    {
        [$section, $slug] = $this->parseUrl($url);
        foreach ($this->store->listDir($this->sectionPath($section)) as $name) {
            if (self::fileSlug($name) === $slug) {
                $path = $this->sectionPath($section) . '/' . $name;
                $file = $this->store->get($path);
                if ($file !== null && self::isPost($file['content'])) {
                    return [$path, $file];
                }
            }
        }
        throw new MicropubException(404, 'not_found', 'No post at that URL');
    }

    /** @return array{0:string, 1:string} Section and slug. Rejects anything not shaped like a post URL. */
    private function parseUrl(string $url): array
    {
        $site = parse_url($this->cfg['site']);
        $parts = parse_url($url);
        $sameHost = is_array($parts) && strtolower($parts['host'] ?? '') === strtolower($site['host'] ?? '');
        $sections = implode('|', array_map(fn($s) => preg_quote($s, '#'), array_keys($this->cfg['sections'])));
        if (!$sameHost || !preg_match('#^/(' . $sections . ')/([a-z0-9][a-z0-9_-]*)/?$#', $parts['path'] ?? '', $m) || $m[2] === 'page') {
            throw new MicropubException(400, 'invalid_request', 'That is not the URL of a post on this site');
        }
        return [$m[1], $m[2]];
    }

    private function urlFor(string $section, string $slug): string
    {
        return rtrim($this->cfg['site'], '/') . '/' . $section . '/' . $slug;
    }

    private function sectionPath(string $section): string
    {
        return $this->cfg['content_path'] . '/' . $this->cfg['sections'][$section];
    }

    private function uniqueSlug(string $section, string $slug): string
    {
        $taken = array_map([self::class, 'fileSlug'], $this->store->listDir($this->sectionPath($section)));
        $candidate = $slug;
        for ($i = 2; in_array($candidate, $taken, true); $i++) {
            $candidate = $slug . '-' . $i;
        }
        return $candidate;
    }

    /** Section pages such as the archive or blogroll share these folders but are not posts. */
    private static function isPost(string $content): bool
    {
        [$meta] = self::parse($content);
        foreach ($meta as $key => $value) {
            if (is_string($key) && strtolower($key) === 'layout') {
                return in_array(strtolower(trim($value)), self::LAYOUTS, true);
            }
        }
        return true; // No layout means Yellow's default, and at least one old post relies on that.
    }

    /** What Yellow serves a file as: no date prefix, no extension. */
    public static function fileSlug(string $name): string
    {
        return (string) preg_replace(['/\.md$/', '/^\d{4}-\d{2}-\d{2}-/'], '', $name);
    }

    public static function slugify(string $text, int $when): string
    {
        // So "Cañón en Größe" reads as canon-en-groesse, not ca-n-en-gr-e.
        $text = strtr(mb_strtolower($text), self::SLUG_LETTERS);
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', $text), '-');
        $slug = trim(substr($slug, 0, 60), '-');
        if ($slug === '' || !preg_match('/^[a-z0-9]/', $slug)) {
            return 'post-' . date('His', $when);
        }
        return $slug === 'page' ? 'page-post' : $slug;
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private static function withoutCommands(array $props): array
    {
        return array_filter($props, fn($k) => !str_starts_with((string) $k, 'mp-'), ARRAY_FILTER_USE_KEY);
    }

    private static function firstString(array $values): string
    {
        return isset($values[0]) && is_scalar($values[0]) ? (string) $values[0] : '';
    }

    /** `content` may be a string, or {html: ...} / {value: ...}. Yellow's Markdown accepts HTML. */
    private static function contentOf(array $props): string
    {
        $value = $props['content'][0] ?? '';
        if (is_array($value)) {
            $value = $value['html'] ?? $value['value'] ?? '';
        }
        return is_scalar($value) ? str_replace("\r\n", "\n", (string) $value) : '';
    }

    /**
     * An article has a real title and body text, and is not an interaction. Unlike
     * strict post type discovery, a photo alone does not rule it out, because a
     * titled post with text and a hero image is still a blog post.
     */
    private static function isArticle(array $props, string $name, string $body): bool
    {
        if ($name === '' || self::plainText($body) === '' || self::nameRepeatsText($name, $body)) {
            return false;
        }
        foreach (array_keys($props) as $key) {
            if (in_array($key, self::NON_ARTICLE, true) || str_ends_with((string) $key, '-of')) {
                return false;
            }
        }
        return true;
    }

    /** Some clients send the start of a note's text as its name, which is not a title. */
    private static function nameRepeatsText(string $name, string $body): bool
    {
        $text = mb_strtolower(self::plainText($body));
        $start = mb_strtolower(rtrim(self::plainText($name), ".… \t"));
        return $text !== '' && $start !== '' && str_starts_with($text, $start);
    }

    private static function plainText(string $html): string
    {
        return trim((string) preg_replace('/\s+/', ' ', strip_tags($html)));
    }

    private static function rsvpOf(array $props): ?string
    {
        $value = strtolower(self::firstString($props['rsvp'] ?? []));
        return in_array($value, self::RSVP_VALUES, true) ? $value : null;
    }

    private static function titleFrom(array $props, string $body): string
    {
        $text = trim((string) preg_replace('/\s+/', ' ', strip_tags($body)));
        if ($text === '') {
            foreach (self::INTERACTIONS as $prop => [$label]) {
                $url = self::urlOf(($props[$prop] ?? [null])[0]);
                if ($url !== null) {
                    $rsvp = $prop === 'in-reply-to' ? self::rsvpOf($props) : null;
                    return ($rsvp !== null ? 'RSVP ' . $rsvp : $label) . ': ' . $url;
                }
            }
            return 'Photo';
        }
        return mb_strlen($text) > 60 ? rtrim(mb_substr($text, 0, 60)) . '…' : $text;
    }

    /** Only http(s) URLs reach the page, so no javascript: links. Accepts {value: url}. */
    private static function urlOf(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = $value['value'] ?? null;
        }
        if (!is_string($value) || !preg_match('#^https?://[^\s<>"\']+$#i', $value)) {
            return null;
        }
        return $value;
    }

    private static function parseDate(string $value): int
    {
        $time = $value === '' ? false : strtotime($value);
        return $time === false ? time() : $time;
    }

    /** Frontmatter values must stay on one line, or a value could inject new keys. */
    private static function oneLine(string $value): string
    {
        return trim((string) preg_replace('/[\r\n\x{2028}\x{2029}\x{85}]+/u', ' ', $value));
    }
}
