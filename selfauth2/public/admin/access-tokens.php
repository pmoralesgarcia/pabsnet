<?php

require_once __DIR__ . '/inc.php';

use Selfauth\AccessTokens;
use Selfauth\Session;
use Selfauth\Support;

Session::requireAuth();
admin_require_role('owner');

$pdo = $GLOBALS['selfauth_pdo'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_require_csrf();
    admin_require_role('owner');
    AccessTokens::revokeById($pdo, (int) filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT));
    admin_set_flash('ok', 'Access revoked. That app can no longer use this token.');
    header('Location: access-tokens.php');
    exit;
}

$tokens = AccessTokens::all($pdo);
$when = fn($t) => $t === null ? '—' : date('Y-m-d H:i', (int) $t);

admin_header('App Access', 'access-tokens.php');
?>
<h1>App access</h1>
<p class="muted">
    Apps you approved that hold an access token, for example to post through Micropub.
    Revoke one to cut it off straight away.
</p>

<?php admin_render_flash(); ?>

<div class="card">
    <?php if ($tokens === []) : ?>
        <p class="muted">No app has a token yet.</p>
    <?php else : ?>
    <table>
        <thead><tr><th>App</th><th>Permissions</th><th>Issued</th><th>Last used</th><th>Expires</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($tokens as $t) : ?>
            <?php $active = $t['revoked_at'] === null && (int) $t['expires_at'] > time(); ?>
            <tr>
                <td><?php echo Support::e($t['client_id']); ?></td>
                <td><?php echo Support::e($t['scope']); ?></td>
                <td><?php echo Support::e($when($t['created_at'])); ?></td>
                <td><?php echo Support::e($when($t['last_used_at'])); ?></td>
                <td><?php echo Support::e($when($t['expires_at'])); ?></td>
                <td>
                    <?php if ($active) : ?>
                    <form method="POST">
                        <?php echo admin_csrf_field(); ?>
                        <input type="hidden" name="id" value="<?php echo (int) $t['id']; ?>">
                        <input type="submit" value="Revoke">
                    </form>
                    <?php else : ?>
                        <?php echo $t['revoked_at'] !== null ? 'Revoked' : 'Expired'; ?>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
<?php
admin_footer();
