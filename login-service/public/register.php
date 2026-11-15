<?php

require __DIR__ . '/../src/bootstrap.php';

use Login\ClientError;
use Login\Config;
use Login\IndieAuth;
use Login\Store;
use Login\Urls;

Config::securityHeaders();
Config::startSession();

$owner = $_SESSION['owner'] ?? null;
$isAdmin = Config::isAdmin($owner);
$error = null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    try {
        Config::checkCsrf();
        if (!Config::allow('register', 20, 60)) {
            throw new ClientError('Too many attempts. Wait a minute and try again.');
        }
        $action = $_POST['action'] ?? '';
        if ($action === 'signin') {
            header('Location: ' . IndieAuth::begin(is_string($_POST['me'] ?? null) ? $_POST['me'] : '', ['type' => 'register']), true, 303);
            exit;
        }
        if ($action === 'signout') {
            unset($_SESSION['owner']);
            session_regenerate_id(true);
        } elseif ($owner === null) {
            throw new ClientError('Sign in first.');
        } elseif ($action === 'add') {
            if (!Config::registrationOpen() && !$isAdmin) {
                throw new ClientError('Registration is closed right now.');
            }
            Store::open()->register(is_string($_POST['site'] ?? null) ? $_POST['site'] : '', $owner);
        } elseif ($action === 'remove') {
            Store::open()->unregister(is_string($_POST['site'] ?? null) ? $_POST['site'] : '', $owner);
        }
        header('Location: /register', true, 303);
        exit;
    } catch (ClientError $problem) {
        $error = $problem->getMessage();
    }
}

$removeForm = function (string $site) {
    ?>
    <form method="post" action="/register" class="inline">
        <input type="hidden" name="_csrf" value="<?php echo e(Config::csrfToken()); ?>">
        <input type="hidden" name="action" value="remove">
        <input type="hidden" name="site" value="<?php echo e($site); ?>">
        <button class="quiet" type="submit">Remove</button>
    </form>
    <?php
};

page_start($isAdmin ? 'Sites' : 'Register your site');
?>
<h1><?php echo $isAdmin ? 'Sites' : 'Register your site'; ?></h1>
<?php if ($error !== null) : ?><p class="msg error" role="alert"><?php echo e($error); ?></p><?php endif; ?>
<?php if (!Config::registrationOpen()) : ?><p class="msg" role="status">Registration is closed<?php echo $isAdmin ? ' to everyone else. You can still add sites.' : ' right now.'; ?></p><?php endif; ?>
<?php if ($owner === null) : ?>
    <p class="lead">Sign in with your website to prove you run it. You can then register sites on the same domain, so they can use this service.</p>
    <form method="post" action="/register" class="card">
        <input type="hidden" name="_csrf" value="<?php echo e(Config::csrfToken()); ?>">
        <input type="hidden" name="action" value="signin">
        <label for="me">Your website</label>
        <input id="me" type="text" name="me" inputmode="url" autocomplete="url" autocapitalize="none" spellcheck="false" placeholder="example.com" required>
        <button type="submit">Continue</button>
    </form>
<?php elseif ($isAdmin) : ?>
    <p class="lead">Signed in as <b><?php echo e($owner); ?></b>, the admin. Sites on <?php echo e(Urls::host($owner)); ?> and its subdomains can use this service without registering.</p>
    <div class="card">
        <?php $sites = Store::open()->allClients(); ?>
        <h2>Registered sites (<?php echo count($sites); ?>)</h2>
        <?php if ($sites === []) : ?><p class="hint">None yet.</p><?php endif; ?>
        <?php foreach ($sites as $site) : ?>
            <div class="site">
                <span><?php echo e($site['client_id']); ?><br><small class="hint">by <?php echo e($site['owner_me']); ?>, <?php echo e(date('M j, Y', (int) $site['created_at'])); ?></small></span>
                <?php $removeForm($site['client_id']); ?>
            </div>
        <?php endforeach; ?>
        <form method="post" action="/register">
            <input type="hidden" name="_csrf" value="<?php echo e(Config::csrfToken()); ?>">
            <input type="hidden" name="action" value="add">
            <label for="site">Add a site on any domain (client_id)</label>
            <input id="site" type="url" name="site" placeholder="https://example.com/" required>
            <button type="submit">Add site</button>
        </form>
    </div>
<?php else : ?>
    <p class="lead">Signed in as <b><?php echo e($owner); ?></b>. You can register sites on <?php echo e(Urls::host($owner)); ?>.</p>
    <div class="card">
        <?php $sites = Store::open()->clientsOf($owner); ?>
        <h2>Your sites</h2>
        <?php if ($sites === []) : ?><p class="hint">None yet.</p><?php endif; ?>
        <?php foreach ($sites as $site) : ?>
            <div class="site"><span><?php echo e($site); ?></span><?php $removeForm($site); ?></div>
        <?php endforeach; ?>
        <?php if (Config::registrationOpen()) : ?>
        <form method="post" action="/register">
            <input type="hidden" name="_csrf" value="<?php echo e(Config::csrfToken()); ?>">
            <input type="hidden" name="action" value="add">
            <label for="site">Site address (client_id)</label>
            <input id="site" type="url" name="site" placeholder="https://<?php echo e(Urls::host($owner)); ?>/" required>
            <button type="submit">Register site</button>
        </form>
        <?php endif; ?>
    </div>
<?php endif; ?>
<?php if ($owner !== null) : ?>
    <form method="post" action="/register">
        <input type="hidden" name="_csrf" value="<?php echo e(Config::csrfToken()); ?>">
        <input type="hidden" name="action" value="signout">
        <button class="quiet" type="submit">Sign out</button>
    </form>
<?php endif; ?>
<?php page_end();
