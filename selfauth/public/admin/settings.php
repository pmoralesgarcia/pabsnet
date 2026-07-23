<?php

require_once __DIR__ . '/inc.php';

use Selfauth\Auth;
use Selfauth\Session;
use Selfauth\Support;

Session::requireAuth();

$settings = $GLOBALS['selfauth_settings'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_require_csrf();
    $action = filter_input(INPUT_POST, 'action', FILTER_UNSAFE_RAW);

    if ($action === 'change_password') {
        $current = (string) filter_input(INPUT_POST, 'current_password', FILTER_UNSAFE_RAW);
        $new = (string) filter_input(INPUT_POST, 'new_password', FILTER_UNSAFE_RAW);
        $confirm = (string) filter_input(INPUT_POST, 'confirm_password', FILTER_UNSAFE_RAW);

        if (!Auth::verify($current, SELFAUTH_PASSWORD_HASH, SELFAUTH_USER_URL, SELFAUTH_APP_KEY)) {
            admin_set_flash('error', 'Current password is incorrect.');
        } elseif (strlen($new) < 8) {
            admin_set_flash('error', 'New password must be at least 8 characters.');
        } elseif ($new !== $confirm) {
            admin_set_flash('error', 'New password and confirmation do not match.');
        } else {
            $settings->set('password_hash', Auth::hashPassword($new));
            admin_set_flash('ok', 'Password updated.');
        }
    } elseif ($action === 'update_urls') {
        $appUrl = filter_input(INPUT_POST, 'app_url', FILTER_VALIDATE_URL);
        $userUrl = filter_input(INPUT_POST, 'user_url', FILTER_VALIDATE_URL);
        if ($appUrl) {
            $settings->set('app_url', $appUrl);
        }
        if ($userUrl) {
            $settings->set('user_url', $userUrl);
        }
        admin_set_flash('ok', 'Updated. Reload to see changes take effect.');
    }
    header('Location: settings.php');
    exit;
}

admin_header('Settings', 'settings.php');
?>
<h1>Settings</h1>
<?php admin_render_flash(); ?>

<div class="card">
    <h2>Change password</h2>
    <form method="POST">
        <?php echo admin_csrf_field(); ?>
        <input type="hidden" name="action" value="change_password">
        <div class="form-line"><label>Current password</label><input type="password" name="current_password" required></div>
        <div class="form-line" style="margin-top:8px;"><label>New password</label><input type="password" name="new_password" required minlength="8"></div>
        <div class="form-line" style="margin-top:8px;"><label>Confirm new password</label><input type="password" name="confirm_password" required minlength="8"></div>
        <div class="form-line" style="margin-top:12px;"><input type="submit" value="Update password"></div>
    </form>
</div>

<div class="card">
    <h2>Endpoint URLs</h2>
    <p class="muted">These are seeded from <code>SELFAUTH_APP_URL</code> / <code>SELFAUTH_USER_URL</code> on first boot, and can be edited here afterwards.</p>
    <form method="POST">
        <?php echo admin_csrf_field(); ?>
        <input type="hidden" name="action" value="update_urls">
        <div class="form-line"><label>App URL (where this endpoint is hosted)</label><input type="url" name="app_url" value="<?php echo Support::e(SELFAUTH_APP_URL); ?>" required></div>
        <div class="form-line" style="margin-top:8px;"><label>Your personal URL ("me")</label><input type="url" name="user_url" value="<?php echo Support::e(SELFAUTH_USER_URL); ?>" required></div>
        <div class="form-line" style="margin-top:12px;"><input type="submit" value="Save"></div>
    </form>
</div>

<div class="card">
    <h2>About this install</h2>
    <table>
        <tr><th>Password hash algorithm</th><td><?php echo Auth::isLegacyMd5Hash(SELFAUTH_PASSWORD_HASH) ? 'Legacy MD5 (will upgrade automatically on next successful login)' : 'Argon2id / bcrypt (password_hash)'; ?></td></tr>
        <tr><th>Webmentions</th><td><?php echo SELFAUTH_WEBMENTIONS_ENABLED ? 'Enabled' : 'Disabled (set SELFAUTH_WEBMENTIONS_ENABLED=true)'; ?></td></tr>
    </table>
</div>
<?php admin_footer(); ?>
