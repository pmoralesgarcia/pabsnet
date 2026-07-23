<?php

require_once __DIR__ . '/inc.php';

use Selfauth\Auth;
use Selfauth\Session;
use Selfauth\Support;

if (Session::isAuthenticated()) {
    header('Location: index.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_require_csrf();

    // Very small brute-force throttle.
    $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
    $_SESSION['login_last_attempt'] = time();
    if ($_SESSION['login_attempts'] > 10 && (time() - ($_SESSION['login_last_attempt'] ?? 0)) < 60) {
        $error = 'Too many attempts. Please wait a minute and try again.';
    } else {
        $password = (string) filter_input(INPUT_POST, 'password', FILTER_UNSAFE_RAW);

        if (SELFAUTH_PASSWORD_HASH !== '' && Auth::verify($password, SELFAUTH_PASSWORD_HASH, SELFAUTH_USER_URL, SELFAUTH_APP_KEY)) {
            if (Auth::needsRehash(SELFAUTH_PASSWORD_HASH)) {
                $GLOBALS['selfauth_settings']->set('password_hash', Auth::hashPassword($password));
            }
            unset($_SESSION['login_attempts']);
            Session::login(SELFAUTH_USER_URL);
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
</div>
<?php admin_footer(); ?>
