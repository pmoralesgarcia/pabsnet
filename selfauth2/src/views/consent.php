<?php
/** @var Selfauth\AuthRequest $req */
use Selfauth\ClientId;
use Selfauth\Scopes;
use Selfauth\Support;

$e = fn(?string $v) => Support::e($v);
$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
$clientHost = (string) parse_url($req->clientId, PHP_URL_HOST);
$clientPath = (string) parse_url($req->clientId, PHP_URL_PATH);
$redirHost = (string) parse_url($req->redirectUri, PHP_URL_HOST);
$redirScheme = strtolower((string) parse_url($req->redirectUri, PHP_URL_SCHEME));
$redirDiffers = ClientId::origin($req->clientId) !== ClientId::origin($req->redirectUri);
$isCustomScheme = !in_array($redirScheme, ['http', 'https'], true);
$meHost = preg_replace('#^https?://#', '', rtrim(SELFAUTH_USER_URL, '/'));
$hasMeta = !empty($req->client['name']) || !empty($req->client['logo']);
$noScopes = count($offered) === 0 && count($unavailable) === 0;
$risk = Scopes::riskSummary($offered);
$riskLabels = [Scopes::LOW => 'Low risk', Scopes::MEDIUM => 'Can read or organise', Scopes::HIGH => 'Can change your site'];
$initial = strtoupper(mb_substr($req->client['name'] ?? $clientHost, 0, 1));
if (!preg_match('/\p{L}/u', $initial)) {
    $initial = '?';
}
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="robots" content="noindex">
    <title>Sign in to <?= $e($clientHost) ?></title>
    <link rel="stylesheet" href="<?= $e($base) ?>/assets/signin.css">
    <style nonce="<?= $e($nonce) ?>"><?= SELFAUTH_CUSTOM_CSS ?></style>
</head>
<body>
<main class="sheet" aria-labelledby="title">
    <header class="parties">
        <div class="party">
            <?php if (!empty($req->client['logo'])) : ?>
                <img class="mark" src="<?= $e($req->client['logo']) ?>" alt="" width="48" height="48" referrerpolicy="no-referrer">
            <?php else : ?>
                <span class="mark mark-initial" aria-hidden="true"><?= $e($initial) ?></span>
            <?php endif; ?>
            <span class="party-name"><?= $e($req->client['name'] ?? $clientHost) ?></span>
            <span class="party-host"><?= $e($clientHost . ($clientPath !== '/' ? $clientPath : '')) ?></span>
        </div>
        <span class="link" aria-hidden="true"></span>
        <div class="party party-you">
            <span class="mark mark-you" aria-hidden="true"><?= $e(strtoupper(substr($meHost, 0, 1))) ?></span>
            <span class="party-name">You</span>
            <span class="party-host"><?= $e($meHost) ?></span>
        </div>
    </header>

    <h1 id="title">
        <?php if ($noScopes || (!$offered && $unavailable)) : ?>
            <?= $e($req->client['name'] ?? $clientHost) ?> wants to check that you are <?= $e($meHost) ?>
        <?php else : ?>
            <?= $e($req->client['name'] ?? $clientHost) ?> is asking to sign you in
        <?php endif; ?>
    </h1>

    <?php if (!$hasMeta) : ?>
        <p class="note note-warn" role="note">This app hasn’t published a name or logo. Only continue if you recognise <strong><?= $e($clientHost) ?></strong>.</p>
    <?php endif; ?>

    <?php if ($message !== null) : ?>
        <p class="note note-error" role="alert"><?= $e($message) ?></p>
    <?php endif; ?>

    <form method="post" action="" autocomplete="on">
        <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">

        <?php if ($offered) : ?>
        <fieldset class="perms">
            <legend>What it can do<?php if ($risk === Scopes::HIGH) : ?> <span class="tag tag-high">Includes changes to your site</span><?php endif; ?></legend>
            <?php foreach ($offered as $i => $s) :
                $info = Scopes::info($s);
                $on = $selected === null ? Scopes::defaultOn($s) : in_array($s, $selected, true);
            ?>
            <label class="perm perm-<?= $e($info['risk']) ?>" for="scope_<?= $i ?>">
                <input type="checkbox" id="scope_<?= $i ?>" name="scopes[]" value="<?= $e($s) ?>"<?= $on ? ' checked' : '' ?>>
                <span class="switch" aria-hidden="true"></span>
                <span class="perm-text">
                    <span class="perm-title"><?= $e($info['title']) ?> <code><?= $e($s) ?></code></span>
                    <span class="perm-desc"><?= $e($info['desc']) ?></span>
                    <?php if ($info['risk'] !== Scopes::LOW) : ?><span class="tag tag-<?= $e($info['risk']) ?>"><?= $e($riskLabels[$info['risk']]) ?></span><?php endif; ?>
                </span>
            </label>
            <?php endforeach; ?>
        </fieldset>
        <?php elseif ($noScopes) : ?>
            <p class="plain">It will only learn that you own <strong><?= $e($meHost) ?></strong>. No other information is shared.</p>
        <?php endif; ?>

        <?php if ($unavailable) : ?>
        <div class="note note-quiet">
            <strong>Not available here:</strong>
            <?php foreach ($unavailable as $s) : ?><code><?= $e($s) ?></code> <?php endforeach; ?>
            Selfauth doesn’t grant these, so the app won’t receive them.
        </div>
        <?php endif; ?>

        <dl class="where">
            <dt>You’ll be sent back to</dt>
            <dd>
                <span class="where-host<?= $redirDiffers ? ' where-differs' : '' ?>"><?= $e($isCustomScheme ? $redirScheme . '://' : $redirHost) ?></span>
                <?php if ($redirDiffers && !$isCustomScheme) : ?><span class="where-note">Different from the app’s own site. <?= $e($clientHost) ?> lists it as allowed.</span><?php endif; ?>
                <?php if ($isCustomScheme) : ?><span class="where-note">This opens an app on your device.</span><?php endif; ?>
            </dd>
        </dl>

        <?php if ($showPassword) : ?>
        <div class="field">
            <label for="password">Password for <?= $e($meHost) ?></label>
            <input type="text" name="username" value="<?= $e($meHost) ?>" autocomplete="username" hidden>
            <input type="password" id="password" name="password" autocomplete="current-password" required<?= $message === null ? ' autofocus' : '' ?>>
        </div>
        <button class="btn btn-primary" type="submit" name="action" value="password">Sign in</button>
        <?php endif; ?>

        <?php if ($showSso) : ?>
            <?php if ($showPassword) : ?><p class="or"><span>or</span></p><?php endif; ?>
            <button class="btn <?= $showPassword ? 'btn-secondary' : 'btn-primary' ?>" type="submit" name="action" value="sso" formnovalidate>Sign in with single sign-on</button>
        <?php endif; ?>

        <button class="btn btn-quiet" type="submit" name="action" value="cancel" formnovalidate>Cancel and go back</button>
    </form>
</main>
</body>
</html>
