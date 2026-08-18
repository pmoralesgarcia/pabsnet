<?php

require_once __DIR__ . '/inc.php';

use Selfauth\Session;
use Selfauth\Support;
use Selfauth\Webhook;

Session::requireAuth();
admin_require_role('owner');

$pdo = $GLOBALS['selfauth_pdo'];
$webhooks = new Webhook($pdo);
$newSecret = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_require_csrf();
    admin_require_role('owner');
    $action = filter_input(INPUT_POST, 'action', FILTER_UNSAFE_RAW);

    if ($action === 'create') {
        $url = trim((string) filter_input(INPUT_POST, 'url', FILTER_UNSAFE_RAW));
        $events = filter_input(INPUT_POST, 'events', FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY) ?: [];

        try {
            $created = $webhooks->add($url, $events);
            $newSecret = $created['secret'];
            admin_set_flash('ok', 'Webhook created — copy the signing secret now, it will not be shown again.');
        } catch (\InvalidArgumentException $e) {
            admin_set_flash('error', $e->getMessage());
        }
    } elseif ($action === 'remove') {
        $id = (int) filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $webhooks->remove($id);
        admin_set_flash('ok', 'Webhook removed.');
    } elseif ($action === 'toggle') {
        $id = (int) filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $enabled = filter_input(INPUT_POST, 'enabled', FILTER_VALIDATE_INT) === 1;
        $webhooks->setEnabled($id, $enabled);
        admin_set_flash('ok', $enabled ? 'Webhook enabled.' : 'Webhook disabled.');
    }

    if ($newSecret === null) {
        header('Location: webhooks.php');
        exit;
    }
}

$hooks = $webhooks->all();

admin_header('Webhooks', 'webhooks.php');
?>
<h1>Webhooks</h1>
<p class="muted">
    Selfauth POSTs a JSON payload to each enabled, subscribed webhook when an event happens. Every request is signed:
    <code>X-Selfauth-Signature: sha256=&lt;hmac&gt;</code> over <code>"{X-Selfauth-Timestamp}.{raw body}"</code>, keyed
    by the webhook's secret — verify it with <code>hash_equals()</code> before trusting the payload.
</p>

<?php admin_render_flash(); ?>

<?php if ($newSecret !== null) : ?>
<div class="card" style="border-color:var(--ok);">
    <h2>Signing secret</h2>
    <p>Copy this now — it cannot be retrieved again:</p>
    <pre style="background:#f0f0f0; padding:12px; border-radius:6px; overflow-wrap:anywhere;"><?php echo Support::e($newSecret); ?></pre>
</div>
<?php endif; ?>

<div class="card">
    <h2>Add a webhook</h2>
    <form method="POST">
        <?php echo admin_csrf_field(); ?>
        <input type="hidden" name="action" value="create">
        <div class="form-line">
            <label>URL</label>
            <input type="url" name="url" placeholder="https://example.com/hooks/selfauth" required>
        </div>
        <div class="form-line" style="margin-top:8px;">
            <label>Events</label><br>
            <?php foreach (Webhook::EVENTS as $event) : ?>
                <label style="display:inline-block; margin:4px 12px 0 0; font-weight:normal;">
                    <input type="checkbox" name="events[]" value="<?php echo Support::e($event); ?>"> <?php echo Support::e($event); ?>
                </label>
            <?php endforeach; ?>
        </div>
        <div class="form-line" style="margin-top:12px;"><input type="submit" value="Add webhook"></div>
    </form>
</div>

<div class="card">
    <h2>Existing webhooks (<?php echo count($hooks); ?>)</h2>
    <?php if (empty($hooks)) : ?>
        <div class="empty">No webhooks configured.</div>
    <?php else : ?>
    <table>
        <tr><th>URL</th><th>Events</th><th>Status</th><th>Last delivery</th><th></th></tr>
        <?php foreach ($hooks as $h) : ?>
        <tr>
            <td data-label="URL" style="max-width:220px; overflow-wrap:anywhere;"><?php echo Support::e($h['url']); ?></td>
            <td data-label="Events" class="muted"><?php echo Support::e($h['events']); ?></td>
            <td data-label="Status"><?php echo $h['enabled'] ? '<span class="badge ok">enabled</span>' : '<span class="badge bad">disabled</span>'; ?></td>
            <td data-label="Last delivery" class="muted"><?php echo Support::e(($h['last_triggered_at'] ?? 'never') . ($h['last_status'] ? ' (' . $h['last_status'] . ')' : '')); ?></td>
            <td style="white-space:nowrap;">
                <form class="inline" method="POST">
                    <?php echo admin_csrf_field(); ?>
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="id" value="<?php echo (int) $h['id']; ?>">
                    <input type="hidden" name="enabled" value="<?php echo $h['enabled'] ? '0' : '1'; ?>">
                    <button type="submit" class="secondary" style="padding:4px 8px;font-size:.8em;"><?php echo $h['enabled'] ? 'Disable' : 'Enable'; ?></button>
                </form>
                <form class="inline" method="POST" onsubmit="return confirm('Remove this webhook?');">
                    <?php echo admin_csrf_field(); ?>
                    <input type="hidden" name="action" value="remove">
                    <input type="hidden" name="id" value="<?php echo (int) $h['id']; ?>">
                    <button type="submit" class="danger" style="padding:4px 8px;font-size:.8em;">Remove</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
</div>
<?php admin_footer(); ?>
