<?php

require_once __DIR__ . '/inc.php';

use Selfauth\AccessTokens;
use Selfauth\Blocklist;
use Selfauth\Session;
use Selfauth\SignInLog;
use Selfauth\Support;

Session::requireAuth();

$pdo = $GLOBALS['selfauth_pdo'];
$log = new SignInLog($pdo);
$blocklist = new Blocklist($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_require_csrf();
    admin_require_role('manager');
    $action = filter_input(INPUT_POST, 'action', FILTER_UNSAFE_RAW);

    if ($action === 'delete') {
        $id = (int) filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $log->delete($id);
        admin_set_flash('ok', 'Entry removed.');
    } elseif ($action === 'revoke_token') {
        admin_require_role('owner');
        AccessTokens::revokeById($pdo, (int) filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT));
        admin_set_flash('ok', 'Access revoked. That app can no longer use this token.');
    } elseif ($action === 'clear') {
        $log->clear();
        admin_set_flash('ok', 'Sign-in log cleared.');
    } elseif ($action === 'block') {
        $type = filter_input(INPUT_POST, 'type', FILTER_UNSAFE_RAW);
        $pattern = (string) filter_input(INPUT_POST, 'pattern', FILTER_UNSAFE_RAW);
        if (in_array($type, ['client_id', 'redirect_uri', 'ip'], true) && $pattern !== '') {
            $blocklist->add($type, $pattern, 'Added from sign-in log');
            admin_set_flash('ok', 'Added to blocklist: ' . $pattern);
        }
    }
    header('Location: index.php');
    exit;
}

$counts = $log->counts();
$entries = $log->recent(100);
$canAct = Session::hasRole('manager');
$isOwner = Session::hasRole('owner');
$apps = [];
$pastApps = [];
if ($isOwner) {
    foreach (AccessTokens::all($pdo) as $token) {
        if ($token['revoked_at'] === null && (int) $token['expires_at'] > time()) {
            $apps[] = $token;
        } else {
            $pastApps[] = $token;
        }
    }
}
$when = fn($t) => $t === null ? 'Never' : date('M j, Y', (int) $t);

admin_header('Apps & sign-ins', 'index.php');
?>
<h1>Apps &amp; sign-ins</h1>
<div class="stats">
    <?php if ($isOwner) : ?><div class="stat"><div class="n"><?php echo count($apps); ?></div><div class="l">Connected apps</div></div><?php endif; ?>
    <div class="stat"><div class="n"><?php echo $counts['total']; ?></div><div class="l">Sign-in attempts</div></div>
    <div class="stat"><div class="n" style="color:var(--ok)"><?php echo $counts['success']; ?></div><div class="l">Successful</div></div>
    <div class="stat"><div class="n" style="color:var(--danger)"><?php echo $counts['failed']; ?></div><div class="l">Failed / blocked</div></div>
</div>

<?php admin_render_flash(); ?>

<?php if ($isOwner) : ?>
<div class="card" id="apps">
    <h2>Connected apps</h2>
    <p class="muted">Apps you approved that hold an access token, for example to post through Micropub. Revoke one to cut it off straight away.</p>
    <?php if ($apps === []) : ?>
        <div class="empty">No app is connected.</div>
    <?php else : ?>
    <table>
        <tr><th>App</th><th>Permissions</th><th>Connected</th><th>Last used</th><th>Expires</th><th></th></tr>
        <?php foreach ($apps as $t) : ?>
        <tr>
            <td data-label="App" class="wrap"><?php echo Support::e($t['client_id']); ?></td>
            <td data-label="Permissions"><?php echo Support::e($t['scope']); ?></td>
            <td data-label="Connected" class="muted"><?php echo Support::e($when($t['created_at'])); ?></td>
            <td data-label="Last used" class="muted"><?php echo Support::e($when($t['last_used_at'])); ?></td>
            <td data-label="Expires" class="muted"><?php echo Support::e($when($t['expires_at'])); ?></td>
            <td>
                <form class="inline" method="POST">
                    <?php echo admin_csrf_field(); ?>
                    <input type="hidden" name="action" value="revoke_token">
                    <input type="hidden" name="id" value="<?php echo (int) $t['id']; ?>">
                    <button type="submit" class="danger small">Revoke</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
    <?php if ($pastApps !== []) : ?>
    <details class="past">
        <summary>Revoked and expired (<?php echo count($pastApps); ?>)</summary>
        <table>
            <tr><th>App</th><th>Permissions</th><th>Connected</th><th>Status</th></tr>
            <?php foreach ($pastApps as $t) : ?>
            <tr>
                <td data-label="App" class="wrap"><?php echo Support::e($t['client_id']); ?></td>
                <td data-label="Permissions"><?php echo Support::e($t['scope']); ?></td>
                <td data-label="Connected" class="muted"><?php echo Support::e($when($t['created_at'])); ?></td>
                <td data-label="Status"><span class="badge off"><?php echo $t['revoked_at'] !== null ? 'Revoked' : 'Expired'; ?></span></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </details>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
        <h2 style="margin:0;">Recent sign-ins (last 100)</h2>
        <?php if ($canAct) : ?>
        <form method="POST" onsubmit="return confirm('Clear the entire sign-in log?');">
            <?php echo admin_csrf_field(); ?>
            <input type="hidden" name="action" value="clear">
            <button type="submit" class="secondary">Clear log</button>
        </form>
        <?php endif; ?>
    </div>
    <p class="muted">Prefer a feed reader? Private RSS feed (<?php echo $isOwner ? '<a href="settings.php?section=feeds">find the link in Settings</a>' : 'the owner has the link'; ?>).</p>
    <?php if (empty($entries)) : ?>
        <div class="empty">No sign-ins recorded yet.</div>
    <?php else : ?>
    <table>
        <tr><th>When</th><th>Status</th><th>Client</th><th>Redirect</th><th>Scope</th><th>IP</th><?php if ($canAct) : ?><th></th><?php endif; ?></tr>
        <?php foreach ($entries as $e) : ?>
        <tr>
            <td data-label="When" class="muted"><?php echo Support::e($e['occurred_at']); ?></td>
            <td data-label="Status"><span class="badge <?php echo $e['success'] ? 'ok' : 'bad'; ?>"><?php echo $e['success'] ? 'Success' : 'Failed'; ?></span></td>
            <td data-label="Client" style="max-width:220px; overflow-wrap:anywhere;"><?php echo Support::e($e['client_id']); ?></td>
            <td data-label="Redirect" style="max-width:220px; overflow-wrap:anywhere;"><?php echo Support::e($e['redirect_uri']); ?></td>
            <td data-label="Scope"><?php echo Support::e($e['scope']); ?></td>
            <td data-label="IP"><?php echo Support::e($e['ip']); ?></td>
            <?php if ($canAct) : ?>
            <td style="white-space:nowrap;">
                <?php if ($e['client_id']) : ?>
                <form class="inline" method="POST" title="Block this client">
                    <?php echo admin_csrf_field(); ?>
                    <input type="hidden" name="action" value="block">
                    <input type="hidden" name="type" value="client_id">
                    <input type="hidden" name="pattern" value="<?php echo Support::e(parse_url($e['client_id'], PHP_URL_HOST)); ?>">
                    <button type="submit" class="danger small">Block client</button>
                </form>
                <?php endif; ?>
                <form class="inline" method="POST" title="Block this IP">
                    <?php echo admin_csrf_field(); ?>
                    <input type="hidden" name="action" value="block">
                    <input type="hidden" name="type" value="ip">
                    <input type="hidden" name="pattern" value="<?php echo Support::e($e['ip']); ?>">
                    <button type="submit" class="danger small">Block IP</button>
                </form>
                <form class="inline" method="POST">
                    <?php echo admin_csrf_field(); ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo (int) $e['id']; ?>">
                    <button type="submit" class="secondary small">Remove</button>
                </form>
            </td>
            <?php endif; ?>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
</div>
<?php admin_footer(); ?>
