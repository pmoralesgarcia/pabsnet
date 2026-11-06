<?php

require __DIR__ . '/../src/bootstrap.php';

use MicropubClient\ClientError;
use MicropubClient\Compose;
use MicropubClient\Config;
use MicropubClient\Micropub;

Config::securityHeaders();
Config::startSession();

$user = $_SESSION['user'] ?? null;
$type = is_string($_REQUEST['type'] ?? null) && isset(Compose::TYPES[$_REQUEST['type']]) ? $_REQUEST['type'] : 'note';
$values = [];
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$posted = $_SESSION['posted'] ?? null;
unset($_SESSION['posted']);

if ($user !== null && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    try {
        if ($_POST === [] && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            throw new ClientError('That upload is too large. Photos can be up to 10 MB.');
        }
        Config::checkCsrf();
        $values = $_POST;
        if (!Config::allow('post', 30, 60)) {
            throw new ClientError('Too many posts in a row. Wait a minute and try again.');
        }

        $files = [];
        if (Compose::takesPhoto($type) && isset($_FILES['photo'])) {
            foreach ((array) $_FILES['photo']['tmp_name'] as $i => $tmp) {
                $error = ((array) $_FILES['photo']['error'])[$i];
                if ($error === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
                    throw new ClientError('Photos can be up to 10 MB.');
                }
                if ($error !== UPLOAD_ERR_OK || !is_uploaded_file($tmp)) {
                    throw new ClientError('That upload failed. Try again.');
                }
                $files[] = Micropub::checkedFile($tmp, (int) ((array) $_FILES['photo']['size'])[$i], (string) ((array) $_FILES['photo']['name'])[$i]);
            }
        }
        if (count($files) > 4) {
            throw new ClientError('You can upload up to 4 photos at a time.');
        }

        $allowed = array_column($user['syndicate'], 'uid');
        // Validate before uploading, so a mistake in the form leaves no stray files.
        $entry = Compose::toEntry($type, $_POST, [], $allowed, $files !== []);
        $client = new Micropub($user['token'], $user['micropub']);
        if ($files !== [] && $user['media'] !== null) {
            $urls = array_map(fn($file) => $client->upload($user['media'], $file), $files);
            $entry = Compose::toEntry($type, $_POST, $urls, $allowed);
            $url = $client->publish($entry);
        } elseif ($files !== []) {
            $url = $client->publishWithFiles($entry, $files);
        } else {
            $url = $client->publish($entry);
        }
        $_SESSION['posted'] = ['url' => $url, 'draft' => ($_POST['status'] ?? '') === 'draft'];
        header('Location: ./?type=' . rawurlencode($type), true, 303); // A refresh must not post twice.
        exit;
    } catch (ClientError $error) {
        $flash = ['error', $error->getMessage()];
    }
}

$labels = [
    'content' => 'Text', 'name' => 'Title', 'summary' => 'Summary', 'photo' => 'Upload photos', 'alt' => 'Photo description',
    'url' => 'Link', 'event' => 'Event name', 'rsvp' => 'Your RSVP', 'venue' => 'Place', 'locality' => 'City', 'region' => 'State or region',
    'latitude' => 'Latitude', 'longitude' => 'Longitude', 'song' => 'Song, album or show', 'artist' => 'Artist',
];
$linkLabels = ['reply' => 'Replying to', 'like' => 'Link you liked', 'repost' => 'Link you are reposting', 'bookmark' => 'Link to save', 'rsvp' => 'Link to the event', 'checkin' => 'Link to the place'];
$value = fn(string $key) => is_string($values[$key] ?? null) ? $values[$key] : '';

page_start('Post');
?>
<main>
<?php if ($user === null) : ?>
    <h1>Post to your website</h1>
    <p class="lead">Sign in with your website address to publish notes, photos, replies and more. Your posts go to your own site through <a href="https://indieweb.org/Micropub">Micropub</a>.</p>
    <?php if ($flash) : ?><p class="msg <?php echo e($flash[0]); ?>" role="alert"><?php echo e($flash[1]); ?></p><?php endif; ?>
    <form method="post" action="login.php" class="card">
        <input type="hidden" name="_csrf" value="<?php echo e(Config::csrfToken()); ?>">
        <label for="me">Your website</label>
        <input id="me" type="text" name="me" inputmode="url" autocomplete="url" autocapitalize="none" spellcheck="false" placeholder="example.com" required>
        <button type="submit">Sign in</button>
        <p class="hint">Your site needs IndieAuth and Micropub. We keep your sign-in only until you sign out, and we store no posts.</p>
    </form>
<?php else : ?>
    <header class="bar">
        <span>Signed in as <a href="<?php echo e($user['me']); ?>"><?php echo e($user['name'] ?? preg_replace('#^https?://#', '', rtrim($user['me'], '/'))); ?></a></span>
        <form method="post" action="logout.php"><input type="hidden" name="_csrf" value="<?php echo e(Config::csrfToken()); ?>"><button class="quiet" type="submit">Sign out</button></form>
    </header>
    <nav class="tabs" aria-label="Post type">
        <?php foreach (Compose::TYPES as $key => $info) : ?>
            <a href="./?type=<?php echo e($key); ?>"<?php echo $key === $type ? ' aria-current="page"' : ''; ?>><?php echo e($info['label']); ?></a>
        <?php endforeach; ?>
    </nav>
    <?php if ($posted) : ?>
        <p class="msg ok" role="status"><?php echo $posted['draft'] ? 'Draft saved.' : 'Posted.'; ?>
        <?php if ($posted['url'] !== null) : ?> <a href="<?php echo e($posted['url']); ?>">View it</a><?php if (!$posted['draft']) : ?> (it may take a moment to appear)<?php endif; ?><?php endif; ?></p>
    <?php endif; ?>
    <?php if ($flash) : ?><p class="msg <?php echo e($flash[0]); ?>" role="alert"><?php echo e($flash[1]); ?></p><?php endif; ?>
    <form method="post" action="./" enctype="multipart/form-data" class="card">
        <input type="hidden" name="_csrf" value="<?php echo e(Config::csrfToken()); ?>">
        <input type="hidden" name="type" value="<?php echo e($type); ?>">
        <?php foreach (Compose::TYPES[$type]['fields'] as $field => $required) :
            $label = $field === 'url' ? ($linkLabels[$type] ?? 'Link') : $labels[$field];
            $req = $required ? ' required' : ''; ?>
            <?php if ($field === 'content') : ?>
                <label for="content"><?php echo e($label); ?></label>
                <textarea id="content" name="content" rows="<?php echo $type === 'article' ? 14 : 5; ?>"<?php echo $req; ?>><?php echo e($value('content')); ?></textarea>
            <?php elseif ($field === 'photo') : ?>
                <label for="photo"><?php echo e($label); ?></label>
                <input id="photo" type="file" name="photo[]" accept="image/jpeg,image/png,image/gif,image/webp" multiple>
                <label for="photo_url">Or link to a photo</label>
                <input id="photo_url" type="url" name="photo_url" value="<?php echo e($value('photo_url')); ?>">
            <?php elseif ($field === 'rsvp') : ?>
                <label for="rsvp"><?php echo e($label); ?></label>
                <select id="rsvp" name="rsvp" required>
                    <option value="">Choose…</option>
                    <?php foreach (Compose::RSVP_CHOICES as $choice => $text) : ?>
                        <option value="<?php echo e($choice); ?>"<?php echo $value('rsvp') === $choice ? ' selected' : ''; ?>><?php echo e($text); ?></option>
                    <?php endforeach; ?>
                </select>
            <?php else : ?>
                <label for="<?php echo e($field); ?>"><?php echo e($label); ?></label>
                <input id="<?php echo e($field); ?>" type="<?php echo $field === 'url' ? 'url' : 'text'; ?>" name="<?php echo e($field); ?>" value="<?php echo e($value($field)); ?>"<?php echo $req; ?>>
            <?php endif; ?>
        <?php endforeach; ?>

        <label for="category">Tags</label>
        <input id="category" type="text" name="category" placeholder="Separate with commas" value="<?php echo e($value('category')); ?>">

        <?php if ($user['syndicate'] !== []) : ?>
            <fieldset><legend>Also post to</legend>
                <?php foreach ($user['syndicate'] as $target) : ?>
                    <label class="check"><input type="checkbox" name="syndicate[]" value="<?php echo e($target['uid']); ?>"> <?php echo e($target['name']); ?></label>
                <?php endforeach; ?>
            </fieldset>
        <?php endif; ?>

        <details>
            <summary>More options</summary>
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="published">Publish</option>
                <option value="draft"<?php echo $value('status') === 'draft' ? ' selected' : ''; ?>>Save as draft</option>
            </select>
            <label for="visibility">Visibility</label>
            <select id="visibility" name="visibility">
                <option value="public">Public</option>
                <option value="unlisted"<?php echo $value('visibility') === 'unlisted' ? ' selected' : ''; ?>>Unlisted</option>
                <option value="private"<?php echo $value('visibility') === 'private' ? ' selected' : ''; ?>>Private</option>
            </select>
            <label for="published">Publish date</label>
            <input id="published" type="datetime-local" name="published" value="<?php echo e($value('published')); ?>">
            <label for="slug">URL slug</label>
            <input id="slug" type="text" name="slug" autocapitalize="none" value="<?php echo e($value('slug')); ?>">
        </details>

        <button type="submit"><?php echo $type === 'note' ? 'Post note' : 'Post ' . e(strtolower(Compose::TYPES[$type]['label'])); ?></button>
    </form>
<?php endif; ?>
</main>
<?php page_end();
