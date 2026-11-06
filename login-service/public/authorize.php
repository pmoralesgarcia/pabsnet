<?php

require __DIR__ . '/../src/bootstrap.php';

use Login\AuthRequest;
use Login\ClientError;
use Login\Config;
use Login\IndieAuth;
use Login\Store;
use Login\Urls;

// The IndieAuth spec redeems identity codes at this same address.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['code'])) {
    require __DIR__ . '/token.php';
    exit;
}

Config::securityHeaders();
Config::startSession();
$error = null;

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        Config::checkCsrf();
        if (!Config::allow('authorize', 20, 60)) {
            throw new ClientError('Too many sign-in attempts. Wait a minute and try again.');
        }
        $request = is_string($_POST['request'] ?? null) ? ($_SESSION['requests'][$_POST['request']] ?? null) : null;
        if ($request === null) {
            throw new ClientError('This sign-in expired. Go back to the site and start again.');
        }
        try {
            $me = $request['me'] ?? (is_string($_POST['me'] ?? null) ? $_POST['me'] : '');
            header('Location: ' . IndieAuth::begin($me, ['type' => 'authorize', 'request' => $request], $request['scope']), true, 303);
            exit;
        } catch (ClientError $problem) {
            $error = $problem->getMessage(); // Show it on the form, so they can correct the address.
            $rid = $_POST['request'];
        }
    } else {
        $request = AuthRequest::validate($_GET, Store::open());
        $rid = bin2hex(random_bytes(12));
        $_SESSION['requests'] = array_slice(($_SESSION['requests'] ?? []) + [$rid => $request], -5, null, true);
    }
} catch (ClientError $problem) {
    error_page($problem->getMessage());
}

$site = Urls::host($request['client_id']);
$cancel = AuthRequest::backTo($request['redirect_uri'], ['error' => 'access_denied', 'state' => $request['state']]);
page_start('Sign in to ' . $site);
?>
<h1>Sign in to <?php echo e($site); ?></h1>
<p class="lead">You'll confirm it's you on your own website's sign-in page. <?php echo e($site); ?> only learns your website address<?php echo $request['scope'] === 'profile' ? ', plus any profile details you choose to share' : ''; ?>.</p>
<?php if ($error !== null) : ?><p class="msg error" role="alert"><?php echo e($error); ?></p><?php endif; ?>
<form method="post" action="/authorize" class="card">
    <input type="hidden" name="_csrf" value="<?php echo e(Config::csrfToken()); ?>">
    <input type="hidden" name="request" value="<?php echo e($rid); ?>">
    <?php if ($request['me'] !== null) : ?>
        <p>Signing in as <b><?php echo e($request['me']); ?></b></p>
    <?php else : ?>
        <label for="me">Your website</label>
        <input id="me" type="text" name="me" inputmode="url" autocomplete="url" autocapitalize="none" spellcheck="false" placeholder="example.com" required value="<?php echo e(is_string($_POST['me'] ?? null) ? $_POST['me'] : ''); ?>">
    <?php endif; ?>
    <button type="submit">Continue</button>
    <a class="cancel" href="<?php echo e($cancel); ?>">Cancel and go back</a>
</form>
<?php page_end();
