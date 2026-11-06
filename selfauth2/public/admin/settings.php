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
        admin_set_flash('ok', 'Saved.');
    } elseif ($action === 'update_custom_css') {
        $css = (string) filter_input(INPUT_POST, 'custom_css', FILTER_UNSAFE_RAW);
        // Cheap guardrail: CSS can't execute script, but strip anything
        // that looks like an attempt to break out of the stylesheet
        // context (e.g. a literal </style> tag) since it's echoed
        // directly inside <style> on the public login page.
        $css = str_replace('<', '', $css);
        $settings->set('custom_css', $css);
        admin_set_flash('ok', 'Custom CSS updated.');
    } elseif ($action === 'update_profile') {
        $clean = function (string $key, int $max, bool $url = false): string {
            $v = trim((string) filter_input(INPUT_POST, $key, FILTER_UNSAFE_RAW));
            $v = mb_substr(preg_replace('/[\x00-\x1f\x7f]/u', '', $v), 0, $max);
            if ($v !== '' && $url && !preg_match('#^https?://[^\s]+$#i', $v)) {
                return '';
            }
            if ($key === 'profile_email' && $v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL)) {
                return '';
            }
            return $v;
        };
        // Each row saves one field, so a field that was not sent keeps its value.
        foreach (['profile_name' => [100, false], 'profile_photo' => [2048, true], 'profile_url' => [2048, true], 'profile_email' => [254, false]] as $key => [$max, $isUrl]) {
            if (isset($_POST[$key])) {
                $settings->set($key, $clean($key, $max, $isUrl));
            }
        }
        admin_set_flash('ok', 'Profile saved. Apps only see it when you grant the profile or email permission.');
    } elseif ($action === 'regenerate_webmention_feed_token') {
        $settings->set('webmention_feed_token', bin2hex(random_bytes(24)));
        admin_set_flash('ok', 'Webmention feed link regenerated -- the old link no longer works.');
    } elseif ($action === 'regenerate_signin_feed_token') {
        $settings->set('signin_feed_token', bin2hex(random_bytes(24)));
        admin_set_flash('ok', 'Sign-in feed link regenerated -- the old link no longer works.');
    }
    $return = ['change_password' => 'security', 'update_custom_css' => 'appearance', 'regenerate_webmention_feed_token' => 'feeds', 'regenerate_signin_feed_token' => 'feeds'];
    header('Location: settings.php?section=' . ($return[$action] ?? 'general'));
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

$sections = ['general', 'security', 'feeds', 'appearance'];
$section = (string) filter_input(INPUT_GET, 'section', FILTER_UNSAFE_RAW);
$section = in_array($section, $sections, true) ? $section : 'general';

/** One setting: its name and value, with an edit form that opens in place. */
$row = function (string $label, string $value, string $action, string $name, string $type, string $current, string $hint = '', int $max = 0) {
    ?>
    <details class="row">
        <summary>
            <span class="row-text"><b><?php echo Support::e($label); ?></b><span class="muted"><?php echo Support::e($value); ?></span></span>
            <span class="row-edit">Edit</span>
        </summary>
        <form method="POST" action="settings.php?section=general" class="row-form">
            <?php echo admin_csrf_field(); ?>
            <input type="hidden" name="action" value="<?php echo Support::e($action); ?>">
            <input type="<?php echo Support::e($type); ?>" name="<?php echo Support::e($name); ?>" value="<?php echo Support::e($current); ?>" aria-label="<?php echo Support::e($label); ?>"<?php echo $max > 0 ? ' maxlength="' . $max . '"' : ''; ?><?php echo $action === 'update_urls' ? ' required' : ''; ?>>
            <button type="submit">Save</button>
            <?php if ($hint !== '') : ?><p class="muted"><?php echo Support::e($hint); ?></p><?php endif; ?>
        </form>
    </details>
    <?php
};
$status = fn(bool $on, string $yes = 'Enabled', string $no = 'Disabled') => '<span class="badge ' . ($on ? 'ok' : 'off') . '">' . ($on ? $yes : $no) . '</span>';

admin_header('Settings', 'settings.php?section=' . $section);
admin_render_flash();
?>

<?php if ($section === 'general') : ?>
<div class="card rows">
    <h2>Sign-in profile</h2>
    <p class="muted">Shared with an app only when you tick “profile” (name, photo, URL) or “email” on the sign-in screen. Leave a field empty to share nothing for it.</p>
    <?php
    $profileName = (string) $settings->get('profile_name', '');
    $profilePhoto = (string) $settings->get('profile_photo', '');
    $profileUrl = (string) $settings->get('profile_url', '');
    $profileEmail = (string) $settings->get('profile_email', '');
    $row('Name', $profileName ?: 'Not set', 'update_profile', 'profile_name', 'text', $profileName, '', 100);
    $row('Photo URL', $profilePhoto ?: 'Not set', 'update_profile', 'profile_photo', 'url', $profilePhoto);
    $row('Profile page', $profileUrl ?: 'Your personal URL', 'update_profile', 'profile_url', 'url', $profileUrl, 'Leave empty to use your personal URL.');
    $row('Email', $profileEmail ?: 'Not set', 'update_profile', 'profile_email', 'email', $profileEmail);
    ?>
</div>
<div class="card rows">
    <h2>Site</h2>
    <p class="muted">Seeded from <code>SELFAUTH_APP_URL</code> and <code>SELFAUTH_USER_URL</code> on first boot, and editable here afterwards.</p>
    <?php
    $row('Your personal URL (“me”)', SELFAUTH_USER_URL, 'update_urls', 'user_url', 'url', SELFAUTH_USER_URL);
    $row('App URL', SELFAUTH_APP_URL, 'update_urls', 'app_url', 'url', SELFAUTH_APP_URL, 'Where this endpoint is hosted.');
    ?>
</div>
<div class="card">
    <h2>Features</h2>
    <table class="kv">
        <tr><th>Webmentions</th><td><?php echo $status(SELFAUTH_WEBMENTIONS_ENABLED); ?></td></tr>
        <tr><th>Email notifications</th><td><?php echo $status(\Selfauth\Notifier::isEnabled()); ?></td></tr>
    </table>
</div>

<?php elseif ($section === 'security') : ?>
<div class="card">
    <h2>Change password</h2>
    <form method="POST" action="settings.php?section=security" class="stack">
        <?php echo admin_csrf_field(); ?>
        <input type="hidden" name="action" value="change_password">
        <div class="form-line"><label for="current_password">Current password</label><input id="current_password" type="password" name="current_password" autocomplete="current-password" required></div>
        <div class="form-line"><label for="new_password">New password</label><input id="new_password" type="password" name="new_password" autocomplete="new-password" required minlength="8"></div>
        <div class="form-line"><label for="confirm_password">Confirm new password</label><input id="confirm_password" type="password" name="confirm_password" autocomplete="new-password" required minlength="8"></div>
        <div class="form-line"><input type="submit" value="Update password"></div>
    </form>
</div>
<div class="card">
    <h2>This session</h2>
    <table class="kv">
        <tr><th>Signed in with</th><td><?php echo Session::authMethod() === 'oidc' ? 'SSO as ' . Support::e((string) Session::identity()) : 'Local password'; ?></td></tr>
        <tr><th>Password storage</th><td><?php echo Auth::isLegacyMd5Hash(SELFAUTH_PASSWORD_HASH) ? 'Legacy MD5 (upgrades automatically at next sign-in)' : 'Argon2id / bcrypt'; ?></td></tr>
    </table>
</div>
<div class="card">
    <h2>External login (OIDC / OAuth2)</h2>
    <table class="kv">
        <tr>
            <th>IndieAuth “me” sign-in</th>
            <td>
                <?php if (SELFAUTH_LOGIN_OIDC_MISCONFIGURED) : ?>
                    <span class="badge bad">Misconfigured</span> Needs an owner allow-list.
                <?php else : ?>
                    <?php echo $status(SELFAUTH_LOGIN_OIDC_ENABLED, 'Connected', 'Not enabled'); ?>
                    <?php if (!SELFAUTH_LOGIN_OIDC_ENABLED) : ?><span class="muted">Set <code>SELFAUTH_LOGIN_OIDC_ENABLED=true</code> to allow it.</span><?php endif; ?>
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <th>Admin portal sign-in</th>
            <td>
                <?php if (SELFAUTH_OIDC_MISCONFIGURED) : ?>
                    <span class="badge bad">Misconfigured</span> Needs an owner allow-list or at least one delegate.
                <?php else : ?>
                    <?php echo $status(SELFAUTH_OIDC_ADMIN_ENABLED, 'Connected', 'Not enabled'); ?>
                    <?php if (SELFAUTH_OIDC_ADMIN_ENABLED) : ?><span class="muted"><code><?php echo Support::e((string) getenv('SELFAUTH_OIDC_ISSUER')); ?></code></span><?php else : ?><span class="muted">Set <code>SELFAUTH_ADMIN_OIDC_ENABLED=true</code> to allow it.</span><?php endif; ?>
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
    <p class="muted">Add identities that only reach the admin portal under <a href="admins.php">Admins</a>. The README lists every <code>SELFAUTH_OIDC_*</code> setting.</p>
</div>

<?php elseif ($section === 'feeds') : ?>
<div class="card">
    <h2>Private feed links</h2>
    <p class="muted">These URLs include a secret token, so treat them like a password. Regenerating stops the old link working.</p>
    <table class="kv">
        <tr>
            <th>Webmentions RSS</th>
            <td>
                <?php if ($feedPublic) : ?>
                    <code><?php echo Support::e(rtrim(SELFAUTH_APP_URL, '/')); ?>/feed.php</code> <span class="muted">Public, because <code>SELFAUTH_WEBMENTION_FEED_PUBLIC=true</code>.</span>
                <?php else : ?>
                    <code><?php echo Support::e(rtrim(SELFAUTH_APP_URL, '/')); ?>/feed.php?token=<?php echo Support::e((string) $webmentionFeedToken); ?></code>
                    <form method="POST" action="settings.php?section=feeds" class="after-code">
                        <?php echo admin_csrf_field(); ?>
                        <input type="hidden" name="action" value="regenerate_webmention_feed_token">
                        <button type="submit" class="secondary small">Regenerate</button>
                    </form>
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <th>Sign-in activity RSS</th>
            <td>
                <code><?php echo Support::e(rtrim(SELFAUTH_APP_URL, '/')); ?>/admin/signins-feed.php?token=<?php echo Support::e((string) $signinFeedToken); ?></code>
                <form method="POST" action="settings.php?section=feeds" class="after-code">
                    <?php echo admin_csrf_field(); ?>
                    <input type="hidden" name="action" value="regenerate_signin_feed_token">
                    <button type="submit" class="secondary small">Regenerate</button>
                </form>
            </td>
        </tr>
    </table>
</div>

<?php else : ?>
<div class="card">
    <h2>Custom CSS</h2>
    <p class="muted">Applied to both the admin portal and the public login page. Owner-only, since it can visually alter the login screen.</p>
    <form method="POST" action="settings.php?section=appearance" class="stack">
        <?php echo admin_csrf_field(); ?>
        <input type="hidden" name="action" value="update_custom_css">
        <textarea name="custom_css" rows="10" aria-label="Custom CSS" placeholder="/* e.g. :root{ --accent:#7c3aed; } */"><?php echo Support::e(SELFAUTH_CUSTOM_CSS); ?></textarea>
        <div class="form-line"><input type="submit" value="Save custom CSS"></div>
    </form>
</div>
<?php endif; ?>
<?php admin_footer(); ?>
