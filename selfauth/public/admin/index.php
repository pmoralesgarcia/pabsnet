<?php

require_once __DIR__ . '/inc.php';

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
    $action = filter_input(INPUT_POST, 'action', FILTER_UNSAFE_RAW);

    if ($action === 'delete') {
        $id = (int) filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $log->delete($id);
        admin_set_flash('ok', 'Entry removed.');
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

admin_header('Sign-ins', 'index.php');
?>
<h1>Sign-in activity</h1>
<div class="stats">
    <div class="stat"><div class="n"><?php echo $counts['total']; ?></div><div class="l">Total attempts</div></div>
    <div class="stat"><div class="n" style="color:var(--ok)"><?php echo $counts['success']; ?></div><div class="l">Successful</div></div>
    <div class="stat"><div class="n" style="color:var(--danger)"><?php echo $counts['failed']; ?></div><div class="l">Failed / blocked</div></div>
</div>

<?php admin_render_flash(); ?>

<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <h2 style="margin:0;">Recent sign-ins (last 100)</h2>
        <form method="POST" onsubmit="return confirm('Clear the entire sign-in log?');">
            <?php echo admin_csrf_field(); ?>
            <input type="hidden" name="action" value="clear">
            <button type="submit" class="secondary">Clear log</button>
        </form>
    </div>
    <?php if (empty($entries)) : ?>
        <div class="empty">No sign-ins recorded yet.</div>
    <?php else : ?>
    <table>
        <tr><th>When</th><th>Status</th><th>Client</th><th>Redirect</th><th>Scope</th><th>IP</th><th></th></tr>
        <?php foreach ($entries as $e) : ?>
        <tr>
            <td class="muted"><?php echo Support::e($e['occurred_at']); ?></td>
            <td><span class="badge <?php echo $e['success'] ? 'ok' : 'bad'; ?>"><?php echo $e['success'] ? 'Success' : 'Failed'; ?></span></td>
            <td style="max-width:220px; overflow-wrap:anywhere;"><?php echo Support::e($e['client_id']); ?></td>
            <td style="max-width:220px; overflow-wrap:anywhere;"><?php echo Support::e($e['redirect_uri']); ?></td>
            <td><?php echo Support::e($e['scope']); ?></td>
            <td><?php echo Support::e($e['ip']); ?></td>
            <td style="white-space:nowrap;">
                <?php if ($e['client_id']) : ?>
                <form class="inline" method="POST" title="Block this client">
                    <?php echo admin_csrf_field(); ?>
                    <input type="hidden" name="action" value="block">
                    <input type="hidden" name="type" value="client_id">
                    <input type="hidden" name="pattern" value="<?php echo Support::e(parse_url($e['client_id'], PHP_URL_HOST)); ?>">
                    <button type="submit" class="danger" style="padding:4px 8px;font-size:.8em;">Block client</button>
                </form>
                <?php endif; ?>
                <form class="inline" method="POST" title="Block this IP">
                    <?php echo admin_csrf_field(); ?>
                    <input type="hidden" name="action" value="block">
                    <input type="hidden" name="type" value="ip">
                    <input type="hidden" name="pattern" value="<?php echo Support::e($e['ip']); ?>">
                    <button type="submit" class="danger" style="padding:4px 8px;font-size:.8em;">Block IP</button>
                </form>
                <form class="inline" method="POST">
                    <?php echo admin_csrf_field(); ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo (int) $e['id']; ?>">
                    <button type="submit" class="secondary" style="padding:4px 8px;font-size:.8em;">Remove</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
</div>
<?php admin_footer(); ?>
