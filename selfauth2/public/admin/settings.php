<?php

require_once __DIR__ . '/inc.php';

use Selfauth\Auth;
use Selfauth\OidcClient;
use Selfauth\Session;
use Selfauth\Support;

Session::requireAuth();
admin_require_role('owner');

$settings = $GLOBALS['selfauth_settings'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_require_csrf();
    admin_require_role('owner');
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
    } elseif ($action === 'update_custom_css') {
        $css = (string) filter_input(INPUT_POST, 'custom_css', FILTER_UNSAFE_RAW);
        // Cheap guardrail: CSS can't execute script, but strip anything
        // that looks like an attempt to break out of the stylesheet
        // context (e.g. a literal </style> tag) since it's echoed
        // directly inside <style> on the public login page.
        $css = str_ireplace(['</style', '<script'], '', $css);
        $settings->set('custom_css', $css);
        admin_set_flash('ok', 'Custom CSS updated.');
    } elseif ($action === 'regenerate_webmention_feed_token') {
        $settings->set('webmention_feed_token', bin2hex(random_bytes(24)));
        admin_set_flash('ok', 'Webmention feed link regenerated -- the old link no longer works.');
    } elseif ($action === 'regenerate_signin_feed_token') {
        $settings->set('signin_feed_token', bin2hex(random_bytes(24)));
        admin_set_flash('ok', 'Sign-in feed link regenerated -- the old link no longer works.');
    }
    header('Location: settings.php');
    exit;
}

$webmentionFeedToken = $settings->get('webmention_feed_token');
if ($webmentionFeedToken === null) {
    $webmentionFeedToken = bin2hex(random_bytes(24));
    $settings->set('webmention_feed_token', $webmentionFeedToken);
}
$signinFeedToken = $settings->get('signin_feed_token');
if ($signinFeedToken === null) {
    $signinFeedToken = bin2hex(random_bytes(24));
    $settings->set('signin_feed_token', $signinFeedToken);
}
$feedPublic = filter_var(getenv('SELFAUTH_WEBMENTION_FEED_PUBLIC') ?: 'false', FILTER_VALIDATE_BOOLEAN);

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
        <tr><th>Current session</th><td><?php echo Session::authMethod() === 'oidc' ? 'Signed in via SSO as ' . Support::e((string) Session::identity()) : 'Signed in with local password'; ?></td></tr>
        <tr><th>Email notifications</th><td><?php echo \Selfauth\Notifier::isEnabled() ? 'Enabled' : 'Disabled (set SELFAUTH_SMTP_HOST, SELFAUTH_NOTIFY_EMAIL, SELFAUTH_NOTIFY_EVENTS)'; ?></td></tr>
    </table>
</div>

<div class="card">
    <h2>External login (OIDC / OAuth2)</h2>
    <table>
        <tr>
            <th>IndieAuth "me" sign-in</th>
            <td>
                <?php if (SELFAUTH_LOGIN_OIDC_ENABLED) : ?>
                    Enabled — other websites can see you authenticate via SSO. Only the owner allow-list below can succeed here.
                <?php elseif (SELFAUTH_LOGIN_OIDC_MISCONFIGURED) : ?>
                    <span style="color:var(--danger)">Enabled but misconfigured</span> — needs an owner allow-list.
                <?php else : ?>
                    Not enabled. Set <code>SELFAUTH_LOGIN_OIDC_ENABLED=true</code> to allow it.
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <th>Admin portal sign-in</th>
            <td>
                <?php if (SELFAUTH_OIDC_ADMIN_ENABLED) : ?>
                    Enabled (issuer: <code><?php echo Support::e((string) getenv('SELFAUTH_OIDC_ISSUER')); ?></code>).
                <?php elseif (SELFAUTH_OIDC_MISCONFIGURED) : ?>
                    <span style="color:var(--danger)">Enabled but misconfigured</span> — needs an owner allow-list or at least one delegate.
                <?php else : ?>
                    Not enabled. Set <code>SELFAUTH_ADMIN_OIDC_ENABLED=true</code> to allow it.
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <th>Owner allow-list</th>
            <td>
                Emails: <?php echo Support::e(implode(', ', OidcClient::allowedEmails()) ?: '(none)'); ?><br>
                Subjects: <?php echo Support::e(implode(', ', OidcClient::allowedSubjects()) ?: '(none)'); ?>
            </td>
        </tr>
    </table>
    <p class="muted">Manage additional admin-portal-only identities on the <a href="admins.php">Admins</a> page. See the README for the full <code>SELFAUTH_OIDC_*</code> / <code>SELFAUTH_LOGIN_OIDC_ENABLED</code> / <code>SELFAUTH_ADMIN_OIDC_ENABLED</code> variable list.</p>
</div>

<div class="card">
    <h2>Private feed links</h2>
    <p class="muted">These URLs include a secret token — treat them like a password. Regenerating invalidates the old link.</p>
    <table>
        <tr>
            <th>Webmentions RSS</th>
            <td>
                <?php if ($feedPublic) : ?>
                    <code><?php echo Support::e(rtrim(SELFAUTH_APP_URL, '/')); ?>/feed.php</code> (public, per <code>SELFAUTH_WEBMENTION_FEED_PUBLIC=true</code>)
                <?php else : ?>
                    <code><?php echo Support::e(rtrim(SELFAUTH_APP_URL, '/')); ?>/feed.php?token=<?php echo Support::e((string) $webmentionFeedToken); ?></code>
                    <form method="POST" style="margin-top:6px;">
                        <?php echo admin_csrf_field(); ?>
                        <input type="hidden" name="action" value="regenerate_webmention_feed_token">
                        <button type="submit" class="secondary" style="padding:4px 8px; font-size:.8em;">Regenerate</button>
                    </form>
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <th>Sign-in activity RSS</th>
            <td>
                <code><?php echo Support::e(rtrim(SELFAUTH_APP_URL, '/')); ?>/admin/signins-feed.php?token=<?php echo Support::e((string) $signinFeedToken); ?></code>
                <form method="POST" style="margin-top:6px;">
                    <?php echo admin_csrf_field(); ?>
                    <input type="hidden" name="action" value="regenerate_signin_feed_token">
                    <button type="submit" class="secondary" style="padding:4px 8px; font-size:.8em;">Regenerate</button>
                </form>
            </td>
        </tr>
    </table>
</div>

<div class="card">
    <h2>Custom CSS</h2>
    <p class="muted">Applied to both the admin portal and the public login page. Owner-only, since it can visually alter the login screen.</p>
    <form method="POST">
        <?php echo admin_csrf_field(); ?>
        <input type="hidden" name="action" value="update_custom_css">
        <textarea name="custom_css" rows="8" placeholder="/* e.g. :root{ --accent:#7c3aed; } */"><?php echo Support::e(SELFAUTH_CUSTOM_CSS); ?></textarea>
        <div class="form-line" style="margin-top:12px;"><input type="submit" value="Save custom CSS"></div>
    </form>
</div>
<?php admin_footer(); ?>
