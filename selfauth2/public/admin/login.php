<?php

require_once __DIR__ . '/inc.php';

use Selfauth\Auth;
use Selfauth\RateLimiter;
use Selfauth\Session;
use Selfauth\Support;

if (Session::isAuthenticated()) {
    header('Location: index.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && SELFAUTH_ADMIN_SHOW_PASSWORD_FORM) {
    admin_require_csrf();

    $rateLimiter = new RateLimiter($GLOBALS['selfauth_pdo']);
    if (!$rateLimiter->allow('admin_login:' . Support::clientIp(), 10, 60)) {
        $error = 'Too many attempts. Please wait a minute and try again.';
    } else {
        $password = (string) filter_input(INPUT_POST, 'password', FILTER_UNSAFE_RAW);

        if (SELFAUTH_PASSWORD_HASH !== '' && Auth::verify($password, SELFAUTH_PASSWORD_HASH, SELFAUTH_USER_URL, SELFAUTH_APP_KEY)) {
            if (Auth::needsRehash(SELFAUTH_PASSWORD_HASH)) {
                $GLOBALS['selfauth_settings']->set('password_hash', Auth::hashPassword($password));
            }
            Session::login(SELFAUTH_USER_URL, 'password');
            header('Location: index.php');
            exit;
        }
        $error = 'Invalid password.';
    }
}

admin_header('Log in', '');
?>
<div class="login-wrap card">
    <h1>Selfauth Admin</h1>
    <p class="muted">Logging in as <?php echo Support::e(SELFAUTH_USER_URL); ?></p>
    <?php if ($error) : ?><div class="msg error"><?php echo Support::e($error); ?></div><?php endif; ?>

    <?php if (SELFAUTH_OIDC_MISCONFIGURED) : ?>
        <div class="msg error">SSO login is enabled but not fully configured (check <code>SELFAUTH_OIDC_ISSUER</code>, <code>SELFAUTH_OIDC_CLIENT_ID</code>, and that at least one of <code>SELFAUTH_OIDC_ALLOWED_SUBJECTS</code> / <code>SELFAUTH_OIDC_ALLOWED_EMAILS</code> is set). Falling back to password login.</div>
    <?php endif; ?>

    <?php if (SELFAUTH_ADMIN_SHOW_OIDC_BUTTON) : ?>
        <a class="sso-button" href="../oidc-login.php?purpose=admin" style="display:block; text-align:center; margin-bottom:<?php echo SELFAUTH_ADMIN_SHOW_PASSWORD_FORM ? '16px' : '0'; ?>;">
            <button type="button" style="width:100%;" onclick="window.location='../oidc-login.php?purpose=admin'">Log in with SSO</button>
        </a>
    <?php endif; ?>

    <?php if (SELFAUTH_ADMIN_SHOW_PASSWORD_FORM && SELFAUTH_ADMIN_SHOW_OIDC_BUTTON) : ?>
        <div class="muted" style="text-align:center; margin:12px 0;">or</div>
    <?php endif; ?>

    <?php if (SELFAUTH_ADMIN_SHOW_PASSWORD_FORM) : ?>
    <form method="POST" action="">
        <?php echo admin_csrf_field(); ?>
        <div class="form-line">
            <label for="password">Password</label>
            <input type="password" name="password" id="password" autofocus required>
        </div>
        <div class="form-line" style="margin-top:12px;">
            <input type="submit" value="Log in" style="width:100%">
        </div>
    </form>
    <?php endif; ?>
</div>
<?php admin_footer(); ?>
