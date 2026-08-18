<?php

require_once __DIR__ . '/inc.php';

use Selfauth\ApiToken;
use Selfauth\Session;
use Selfauth\Support;

Session::requireAuth();
admin_require_role('owner');

$pdo = $GLOBALS['selfauth_pdo'];
$apiTokens = new ApiToken($pdo);
$newToken = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_require_csrf();
    admin_require_role('owner');
    $action = filter_input(INPUT_POST, 'action', FILTER_UNSAFE_RAW);

    if ($action === 'create') {
        $label = trim((string) filter_input(INPUT_POST, 'label', FILTER_UNSAFE_RAW)) ?: 'Unnamed token';
        $scopes = filter_input(INPUT_POST, 'scopes', FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY) ?: [];
        $expiresInDays = filter_input(INPUT_POST, 'expires_in_days', FILTER_VALIDATE_INT) ?: null;

        try {
            $created = $apiTokens->create($label, $scopes, $expiresInDays);
            $newToken = $created['token'];
            admin_set_flash('ok', 'Token created — copy it now, it will not be shown again.');
        } catch (\InvalidArgumentException $e) {
            admin_set_flash('error', $e->getMessage());
        }
    } elseif ($action === 'revoke') {
        $id = (int) filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $apiTokens->revoke($id);
        admin_set_flash('ok', 'Token revoked.');
    }

    if ($newToken === null) {
        header('Location: api-tokens.php');
        exit;
    }
}

$tokens = $apiTokens->all();

admin_header('API Tokens', 'api-tokens.php');
?>
<h1>API tokens</h1>
<p class="muted">
    Bearer tokens for scripting against <code>/api/signins.php</code>, <code>/api/blocklist.php</code>, and
    <code>/api/mentions.php</code>. Send as <code>Authorization: Bearer &lt;token&gt;</code>. Requests are rate-limited
    per token. The token value is shown once, at creation — Selfauth only ever stores its SHA-256 hash.
</p>

<?php admin_render_flash(); ?>

<?php if ($newToken !== null) : ?>
<div class="card" style="border-color:var(--ok);">
    <h2>Your new token</h2>
    <p>Copy this now — it cannot be retrieved again:</p>
    <pre style="background:#f0f0f0; padding:12px; border-radius:6px; overflow-wrap:anywhere;"><?php echo Support::e($newToken); ?></pre>
</div>
<?php endif; ?>

<div class="card">
    <h2>Create a token</h2>
    <form method="POST">
        <?php echo admin_csrf_field(); ?>
        <input type="hidden" name="action" value="create">
        <div class="form-line">
            <label>Label</label>
            <input type="text" name="label" placeholder="e.g. My dashboard script" required>
        </div>
        <div class="form-line" style="margin-top:8px;">
            <label>Scopes</label><br>
            <?php foreach (ApiToken::SCOPES as $scope) : ?>
                <label style="display:inline-block; margin:4px 12px 0 0; font-weight:normal;">
                    <input type="checkbox" name="scopes[]" value="<?php echo Support::e($scope); ?>"> <?php echo Support::e($scope); ?>
                </label>
            <?php endforeach; ?>
        </div>
        <div class="form-line" style="margin-top:8px;">
            <label>Expires in (days, optional)</label>
            <input type="number" name="expires_in_days" placeholder="Leave blank for no expiry" min="1">
        </div>
        <div class="form-line" style="margin-top:12px;"><input type="submit" value="Create token"></div>
    </form>
</div>

<div class="card">
    <h2>Existing tokens (<?php echo count($tokens); ?>)</h2>
    <?php if (empty($tokens)) : ?>
        <div class="empty">No API tokens yet.</div>
    <?php else : ?>
    <table>
        <tr><th>Label</th><th>Scopes</th><th>Created</th><th>Last used</th><th>Expires</th><th>Status</th><th></th></tr>
        <?php foreach ($tokens as $t) : ?>
        <tr>
            <td data-label="Label"><?php echo Support::e($t['label']); ?></td>
            <td data-label="Scopes" class="muted"><?php echo Support::e($t['scopes']); ?></td>
            <td data-label="Created" class="muted"><?php echo Support::e($t['created_at']); ?></td>
            <td data-label="Last used" class="muted"><?php echo Support::e($t['last_used_at'] ?? 'never'); ?></td>
            <td data-label="Expires" class="muted"><?php echo Support::e($t['expires_at'] ?? 'never'); ?></td>
            <td data-label="Status"><?php echo $t['revoked_at'] ? '<span class="badge bad">revoked</span>' : '<span class="badge ok">active</span>'; ?></td>
            <td>
                <?php if (!$t['revoked_at']) : ?>
                <form method="POST" onsubmit="return confirm('Revoke this token? Anything using it will stop working immediately.');">
                    <?php echo admin_csrf_field(); ?>
                    <input type="hidden" name="action" value="revoke">
                    <input type="hidden" name="id" value="<?php echo (int) $t['id']; ?>">
                    <button type="submit" class="danger" style="padding:4px 8px;font-size:.8em;">Revoke</button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
</div>
<?php admin_footer(); ?>
